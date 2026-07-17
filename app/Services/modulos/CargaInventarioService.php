<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CargaInventarioRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\CargaInventarioRules;
use App\Services\LogSistemaService;
use App\core\Database;

/**
 * Lógica de negocio de Cargas de Inventario.
 *
 * Flujo:
 *   1. Importación (Excel/CSV) → crea la carga (cabecera + detalle) con validación
 *      por línea. La carga queda "validada" solo si TODAS las líneas están OK.
 *   2. Si la config del establecimiento NO exige aprobación y la carga está validada,
 *      se aplica al kardex de inmediato (estado 'aprobada').
 *   3. Si exige aprobación → queda 'pendiente' y se notifica a los aprobadores.
 *   4. Aprobar → aplica cada línea al kardex (InventarioService::ajusteManual).
 *      Solo puede aprobarse si está comprobada (validada = true).
 */
class CargaInventarioService
{
    private CargaInventarioRepository $repo;
    private CargaInventarioRules $rules;
    private LogSistemaService $log;
    private ?InventarioService $invService = null;
    private ?EmpresaRepository $empRepo = null;

    public function __construct(
        ?CargaInventarioRepository $repo = null,
        ?CargaInventarioRules $rules = null,
        ?LogSistemaService $log = null
    ) {
        $this->repo  = $repo  ?? new CargaInventarioRepository();
        $this->rules = $rules ?? new CargaInventarioRules();
        $this->log   = $log   ?? new LogSistemaService();
    }

    private function inventarioService(): InventarioService
    {
        if ($this->invService === null) {
            $this->invService = new InventarioService(new InventarioRepository(), $this->log);
        }
        return $this->invService;
    }

    private function empresaRepo(): EmpresaRepository
    {
        if ($this->empRepo === null) {
            $this->empRepo = new EmpresaRepository();
        }
        return $this->empRepo;
    }

    // ─── Listado / detalle ────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalleCompleto(int $idCarga, int $idEmpresa): ?array
    {
        $cab = $this->repo->getById($idCarga, $idEmpresa);
        if (!$cab) return null;
        $cab['detalle'] = $this->repo->getDetalle($idCarga, $idEmpresa);
        return $cab;
    }

    /** Datos de referencia (productos y bodegas) para las hojas de la plantilla. */
    public function getReferenciasPlantilla(int $idEmpresa): array
    {
        return [
            'productos' => $this->repo->getProductosParaPlantilla($idEmpresa),
            'bodegas'   => $this->repo->getBodegasParaPlantilla($idEmpresa),
        ];
    }

    // ─── Configuración de aprobación (del establecimiento) ─────────────────────

    public function getConfigAprobacion(int $idEmpresa): array
    {
        $idEst = $this->empresaRepo()->getPrimerEstablecimientoId($idEmpresa);
        $cfg   = $idEst ? ($this->empresaRepo()->getEstablecimientoConfig($idEst) ?? []) : [];

        $requiere  = !empty($cfg['inv_requiere_aprobacion']) && $cfg['inv_requiere_aprobacion'] !== 'f';
        $notificar = !isset($cfg['inv_notificar_correo']) || ($cfg['inv_notificar_correo'] && $cfg['inv_notificar_correo'] !== 'f');
        $aprob     = json_decode($cfg['inv_usuarios_aprobadores'] ?? '[]', true);
        if (!is_array($aprob)) $aprob = [];
        $aprob = array_values(array_map('intval', $aprob));

        return ['requiere' => $requiere, 'notificar' => $notificar, 'aprobadores' => $aprob];
    }

    /** ¿El usuario puede aprobar cargas? (aprobador configurado o super admin). */
    public function esAprobador(int $idUsuario, int $idEmpresa, int $nivel = 1): bool
    {
        if ($nivel >= 3) return true;
        $cfg = $this->getConfigAprobacion($idEmpresa);
        return in_array($idUsuario, $cfg['aprobadores'], true);
    }

    /** Nombres de los usuarios aprobadores configurados (para mostrar quién debe aprobar). */
    public function getAprobadoresNombres(int $idEmpresa): array
    {
        $cfg = $this->getConfigAprobacion($idEmpresa);
        if (empty($cfg['aprobadores'])) return [];
        return array_column($this->repo->getNombresUsuarios($cfg['aprobadores']), 'nombre');
    }

    // ─── Importación ──────────────────────────────────────────────────────────

    /**
     * Crea una carga desde filas importadas. Cada fila: id_producto, id_bodega,
     * cantidad, costo_unitario?, numero_lote?, fecha_caducidad?.
     */
    public function crearDesdeImportacion(int $idEmpresa, int $idUsuario, string $tipoMovimiento, ?string $observacion, array $filas): array
    {
        $this->rules->validarCabecera(['tipo_movimiento' => $tipoMovimiento, 'filas' => $filas]);

        $cfg = $this->getConfigAprobacion($idEmpresa);

        // Validar cada línea. El Excel identifica el producto por CÓDIGO principal y la
        // bodega por NOMBRE (se resuelven al id interno).
        $lineas   = [];
        $todasOk  = true;
        $errores  = [];
        foreach ($filas as $i => $f) {
            $codProd = trim((string) ($f['codigo_producto'] ?? ''));
            $nomBod  = trim((string) ($f['bodega'] ?? ''));
            $cant    = (float) ($f['cantidad'] ?? 0);

            $idProd = $codProd !== '' ? $this->repo->getProductoIdPorCodigo($codProd, $idEmpresa) : 0;
            $idBod  = $nomBod  !== '' ? $this->repo->getBodegaIdPorNombre($nomBod, $idEmpresa)   : 0;

            $err = null;
            if ($codProd === '') {
                $err = 'Falta el código del producto.';
            } elseif ($idProd === 0) {
                $err = "El producto con código \"{$codProd}\" no existe en la empresa.";
            } elseif ($nomBod === '') {
                $err = 'Falta la bodega.';
            } elseif ($idBod === 0) {
                $err = "La bodega \"{$nomBod}\" no existe en la empresa.";
            } elseif ($cant <= 0) {
                $err = 'La cantidad debe ser mayor a cero.';
            }

            if ($err !== null) {
                $todasOk = false;
                $errores[] = 'Fila ' . ($i + 2) . ': ' . $err; // +2: fila 1 = encabezados
            }
            $lineas[] = [
                'id_producto'      => $idProd,
                'id_bodega'        => $idBod,
                'cantidad'         => $cant,
                'costo_unitario'   => (float) ($f['costo_unitario'] ?? 0),
                'numero_lote'      => ($f['numero_lote'] ?? '') !== '' ? trim((string) $f['numero_lote']) : null,
                'fecha_caducidad'  => ($f['fecha_caducidad'] ?? '') !== '' ? trim((string) $f['fecha_caducidad']) : null,
                'nup'              => ($f['nup'] ?? '') !== '' ? trim((string) $f['nup']) : null,
                'observacion'      => ($f['observacion'] ?? '') !== '' ? trim((string) $f['observacion']) : null,
                'cod_producto_raw' => $codProd,
                'cod_bodega_raw'   => $nomBod,
                'linea_valida'     => $err === null,
                'error_linea'      => $err,
            ];
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $numero = $this->repo->siguienteNumero($idEmpresa);
            $idCarga = $this->repo->crearCabecera([
                'id_empresa'         => $idEmpresa,
                'numero'             => $numero,
                'fecha'              => date('Y-m-d'),
                'tipo_movimiento'    => $tipoMovimiento,
                'observacion'        => $observacion,
                'estado'             => 'pendiente',
                'validada'           => $todasOk,
                'errores_validacion' => $errores ? implode("\n", $errores) : null,
                'total_lineas'       => count($lineas),
                'created_by'         => $idUsuario,
            ]);

            foreach ($lineas as $ln) {
                $ln['id_carga']   = $idCarga;
                $ln['id_empresa'] = $idEmpresa;
                $ln['created_by'] = $idUsuario;
                $this->repo->crearDetalle($ln);
            }

            $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'inventario_cargas', $idCarga, null, [
                'numero' => $numero, 'tipo' => $tipoMovimiento, 'lineas' => count($lineas), 'validada' => $todasOk,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Auto-aprobación si no se exige aprobación y la carga está validada.
        $estadoFinal = 'pendiente';
        if (!$cfg['requiere'] && $todasOk) {
            $this->aprobar($idCarga, $idEmpresa, $idUsuario, true);
            $estadoFinal = 'aprobada';
        } else {
            // Queda pendiente: token para aprobar/rechazar desde el enlace del correo.
            $token = bin2hex(random_bytes(24));
            $this->repo->setToken($idCarga, $token);
            if ($cfg['requiere'] && $cfg['notificar']) {
                try { $this->notificarAprobadores($idEmpresa, $idCarga, $cfg['aprobadores'], $token, $idUsuario); } catch (\Throwable $e) {}
            }
        }

        return [
            'id'       => $idCarga,
            'numero'   => $numero,
            'estado'   => $estadoFinal,
            'validada' => $todasOk,
            'errores'  => $errores,
            'requiere_aprobacion' => $cfg['requiere'],
        ];
    }

    // ─── Aprobación / rechazo ───────────────────────────────────────────────────

    /**
     * Aprueba la carga y aplica cada línea al kardex. Solo si está comprobada.
     * @param bool $auto true cuando la aprobación es automática (no exige ser aprobador).
     */
    public function aprobar(int $idCarga, int $idEmpresa, int $idUsuario, bool $auto = false, int $nivel = 3): array
    {
        $carga = $this->repo->getById($idCarga, $idEmpresa);
        if (!$carga) {
            throw new \InvalidArgumentException('Carga no encontrada.');
        }
        if ($carga['estado'] !== 'pendiente') {
            throw new \InvalidArgumentException('Solo se pueden aprobar cargas en estado pendiente.');
        }
        // Segregación de funciones: quien crea la carga no puede aprobarla (salvo super admin).
        if (!$auto && $nivel < 3 && (int) ($carga['created_by'] ?? 0) === $idUsuario) {
            throw new \InvalidArgumentException('No puede aprobar una carga que usted mismo registró. Debe aprobarla otro usuario autorizado.');
        }
        $validada = !empty($carga['validada']) && $carga['validada'] !== 'f';
        if (!$validada) {
            throw new \InvalidArgumentException('La carga no está comprobada: corrija las líneas con error antes de aprobar.');
        }

        $detalle = $this->repo->getDetalle($idCarga, $idEmpresa);
        if (empty($detalle)) {
            throw new \InvalidArgumentException('La carga no tiene líneas.');
        }

        $inv = $this->inventarioService();
        $db  = Database::getConnection();
        $db->beginTransaction();
        try {
            foreach ($detalle as $d) {
                if (empty($d['linea_valida']) || $d['linea_valida'] === 'f') {
                    throw new \InvalidArgumentException('Hay líneas con error; no se puede aprobar.');
                }
                $nup = $d['nup'] ?? null;
                $inv->ajusteManual([
                    'id_producto'     => (int) $d['id_producto'],
                    'id_bodega'       => (int) $d['id_bodega'],
                    'tipo_movimiento' => $carga['tipo_movimiento'],
                    'cantidad'        => (float) $d['cantidad'],
                    'costo_unitario'  => (float) $d['costo_unitario'],
                    'numero_lote'     => $d['numero_lote'] ?: null,
                    'fecha_caducidad' => $d['fecha_caducidad'] ?: null,
                    'nup'             => $nup ?: null,
                    'is_individual'   => !empty($nup) ? '1' : '0',
                    'observaciones'   => !empty($d['observacion']) ? $d['observacion'] : ('Carga de inventario #' . $carga['numero']),
                    'referencia_tipo' => 'carga_inventario',
                    'referencia_id'   => $idCarga,
                ], $idEmpresa, $idUsuario);
            }

            $this->repo->actualizarEstado($idCarga, $idEmpresa, 'aprobada', $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $auto ? 'aprobar_auto' : 'aprobar', 'inventario_cargas', $idCarga, ['estado' => 'pendiente'], ['estado' => 'aprobada']);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        return ['ok' => true, 'estado' => 'aprobada'];
    }

    public function rechazar(int $idCarga, int $idEmpresa, int $idUsuario, string $motivo, int $nivel = 3): array
    {
        $carga = $this->repo->getById($idCarga, $idEmpresa);
        if (!$carga) {
            throw new \InvalidArgumentException('Carga no encontrada.');
        }
        if ($carga['estado'] !== 'pendiente') {
            throw new \InvalidArgumentException('Solo se pueden rechazar cargas pendientes.');
        }
        if ($nivel < 3 && (int) ($carga['created_by'] ?? 0) === $idUsuario) {
            throw new \InvalidArgumentException('No puede rechazar una carga que usted mismo registró. Si desea, puede eliminarla.');
        }
        $this->repo->actualizarEstado($idCarga, $idEmpresa, 'rechazada', null, $motivo);
        $this->log->registrar($idUsuario, $idEmpresa, 'rechazar', 'inventario_cargas', $idCarga, ['estado' => 'pendiente'], ['estado' => 'rechazada', 'motivo' => $motivo]);
        return ['ok' => true, 'estado' => 'rechazada'];
    }

    // ─── Aprobación desde el enlace del correo (por token, sin sesión) ──────────

    /** Carga + detalle a partir del token (para la página pública). Null si el token no es válido. */
    public function getCargaPorToken(string $token): ?array
    {
        $carga = $this->repo->getByToken($token);
        if (!$carga) return null;
        $carga['detalle'] = $this->repo->getDetalle((int) $carga['id'], (int) $carga['id_empresa']);
        $emp = $this->empresaRepo()->getEmisorConfig((int) $carga['id_empresa']) ?? [];
        $carga['empresa_nombre'] = $emp['nombre_comercial'] ?? ($emp['nombre'] ?? '');
        return $carga;
    }

    public function aprobarPorToken(string $token): array
    {
        $carga = $this->repo->getByToken($token);
        if (!$carga) {
            throw new \InvalidArgumentException('Enlace inválido o ya utilizado.');
        }
        if ($carga['estado'] !== 'pendiente') {
            throw new \InvalidArgumentException('Esta carga ya no está pendiente (estado: ' . $carga['estado'] . ').');
        }
        $idEmpresa = (int) $carga['id_empresa'];
        $idCarga   = (int) $carga['id'];

        // Contexto de sistema para aplicar al kardex (ruta pública sin sesión de usuario).
        $_SESSION['id_empresa'] = $idEmpresa;
        $_SESSION['nivel']      = 3;
        if (!isset($_SESSION['id_usuario'])) $_SESSION['id_usuario'] = 0;

        $cfg = $this->getConfigAprobacion($idEmpresa);
        $aprobadaPor = $cfg['aprobadores'][0] ?? 0;

        $res = $this->aprobar($idCarga, $idEmpresa, $aprobadaPor, true); // auto=true: canal confiable (token)
        $this->repo->clearToken($idCarga);
        return $res + ['numero' => $carga['numero']];
    }

    public function rechazarPorToken(string $token, string $motivo): array
    {
        $carga = $this->repo->getByToken($token);
        if (!$carga) {
            throw new \InvalidArgumentException('Enlace inválido o ya utilizado.');
        }
        if ($carga['estado'] !== 'pendiente') {
            throw new \InvalidArgumentException('Esta carga ya no está pendiente (estado: ' . $carga['estado'] . ').');
        }
        $idEmpresa = (int) $carga['id_empresa'];
        $idCarga   = (int) $carga['id'];
        $cfg = $this->getConfigAprobacion($idEmpresa);
        $aprobadaPor = $cfg['aprobadores'][0] ?? 0;

        $this->repo->actualizarEstado($idCarga, $idEmpresa, 'rechazada', null, $motivo);
        $this->repo->clearToken($idCarga);
        $this->log->registrar($aprobadaPor, $idEmpresa, 'rechazar_correo', 'inventario_cargas', $idCarga, ['estado' => 'pendiente'], ['estado' => 'rechazada', 'motivo' => $motivo]);
        return ['ok' => true, 'estado' => 'rechazada', 'numero' => $carga['numero']];
    }

    public function eliminar(int $idCarga, int $idEmpresa, int $idUsuario): bool
    {
        $carga = $this->repo->getById($idCarga, $idEmpresa);
        if (!$carga) {
            throw new \InvalidArgumentException('Carga no encontrada.');
        }
        if ($carga['estado'] === 'aprobada') {
            throw new \InvalidArgumentException('No se puede eliminar una carga ya aprobada (afectó el kardex).');
        }
        $ok = $this->repo->eliminar($idCarga, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'inventario_cargas', $idCarga, $carga, ['eliminado' => true]);
        return $ok;
    }

    // ─── Notificación (correo a aprobadores) ────────────────────────────────────

    /**
     * Notifica por correo a los aprobadores que hay una carga pendiente.
     * Best-effort: cualquier fallo de correo no interrumpe el flujo.
     */
    private function notificarAprobadores(int $idEmpresa, int $idCarga, array $idsAprobadores, ?string $token = null, int $creadorId = 0): void
    {
        // Segregación: no se notifica (para aprobar) al usuario que registró la carga.
        $idsAprobadores = array_values(array_filter($idsAprobadores, static fn($id) => (int) $id !== $creadorId));
        if (empty($idsAprobadores)) return;

        $usuarios = $this->repo->getNombresUsuarios($idsAprobadores);
        $correos  = array_values(array_filter(array_map(static fn($u) => trim((string) ($u['mail'] ?? '')), $usuarios)));
        if (empty($correos)) {
            $this->log->registrar(0, $idEmpresa, 'notificar_pendiente_sin_correo', 'inventario_cargas', $idCarga, null, ['aprobadores' => $idsAprobadores]);
            return;
        }

        $carga = $this->repo->getById($idCarga, $idEmpresa);
        if (!$carga) return;

        $emp = $this->empresaRepo()->getEmisorConfig($idEmpresa) ?? [];
        $empNombre = $emp['nombre_comercial'] ?? ($emp['nombre'] ?? '');

        // El correo necesita una URL absoluta (con dominio); BASE_URL es solo la
        // ruta relativa del subdirectorio, no sirve fuera del navegador.
        $publicUrl = (defined('APP_URL') && APP_URL !== '') ? APP_URL : (defined('BASE_URL') ? BASE_URL : '');
        $publicUrl = rtrim($publicUrl, '/');
        $url = $token ? ($publicUrl . '/aprobar-carga-inventario/' . $token) : ($publicUrl . '/modulos/cargas-inventario');

        $data = [
            'numero'       => $carga['numero'],
            'tipo'         => $carga['tipo_movimiento'],
            'fecha'        => !empty($carga['fecha']) ? date('d-m-Y', strtotime($carga['fecha'])) : '',
            'total_lineas' => $carga['total_lineas'],
            'empresa'      => $empNombre,
            'creador'      => $carga['creado_por_nombre'] ?? '',
            'url'          => $url,
        ];

        require_once MVC_APP . '/helpers/mail.php';
        $ok = notificar_carga_inventario_pendiente($correos, $data);

        $this->log->registrar(0, $idEmpresa, $ok ? 'notificar_pendiente_ok' : 'notificar_pendiente_error', 'inventario_cargas', $idCarga, null, [
            'correos' => $correos, 'error' => $ok ? null : ($GLOBALS['LAST_EMAIL_ERROR'] ?? null),
        ]);
    }
}
