<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\TransferenciaLoteRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\TransferenciaLoteRules;
use App\Services\LogSistemaService;
use App\Services\modulos\Transferencias\TransferenciaFormatterFactory;
use App\core\Database;

/**
 * Lógica de negocio de Lotes de Transferencia.
 *
 * Flujo:
 *   1. Se arma el lote (BORRADOR) seleccionando pagos de Egresos/Roles de Pago
 *      ya registrados con tipo_operacion_bancaria='TRANSFERENCIA' y aún no
 *      reservados por otro lote activo.
 *   2. Enviar a aprobación: si la empresa no exige aprobación, se auto-aprueba;
 *      si la exige, queda PENDIENTE_APROBACION y se notifica a los aprobadores.
 *   3. Aprobado → se genera el archivo (Excel/TXT según el banco) → GENERADO.
 *   4. El usuario confirma manualmente que subió el archivo al banco → CONFIRMADO.
 *      Solo entonces las líneas quedan definitivamente fuera del pool de pagos
 *      disponibles (antes de confirmar, ya estaban reservadas por el lote activo).
 */
class TransferenciaLoteService
{
    private TransferenciaLoteRepository $repo;
    private TransferenciaLoteRules $rules;
    private LogSistemaService $log;
    private ?EmpresaRepository $empRepo = null;

    public function __construct(
        ?TransferenciaLoteRepository $repo = null,
        ?TransferenciaLoteRules $rules = null,
        ?LogSistemaService $log = null
    ) {
        $this->repo  = $repo  ?? new TransferenciaLoteRepository();
        $this->rules = $rules ?? new TransferenciaLoteRules();
        $this->log   = $log   ?? new LogSistemaService();
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

    public function getDetalleCompleto(int $idLote, int $idEmpresa): ?array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) return null;
        $lote['detalle'] = $this->repo->getDetalle($idLote, $idEmpresa);
        return $lote;
    }

    public function getPagosDisponibles(int $idEmpresa, string $tipo, string $buscar): array
    {
        return $this->repo->getPagosDisponibles($idEmpresa, $tipo, $buscar);
    }

    // ─── Configuración de aprobación (del establecimiento) ─────────────────────

    public function getConfigAprobacion(int $idEmpresa): array
    {
        $idEst = $this->empresaRepo()->getPrimerEstablecimientoId($idEmpresa);
        $cfg   = $idEst ? ($this->empresaRepo()->getEstablecimientoConfig($idEst) ?? []) : [];

        $requiere  = !empty($cfg['transf_requiere_aprobacion']) && $cfg['transf_requiere_aprobacion'] !== 'f';
        $notificar = !isset($cfg['transf_notificar_correo']) || ($cfg['transf_notificar_correo'] && $cfg['transf_notificar_correo'] !== 'f');
        $aprob     = json_decode($cfg['transf_usuarios_aprobadores'] ?? '[]', true);
        if (!is_array($aprob)) $aprob = [];
        $aprob = array_values(array_map('intval', $aprob));

        return ['requiere' => $requiere, 'notificar' => $notificar, 'aprobadores' => $aprob];
    }

    public function esAprobador(int $idUsuario, int $idEmpresa, int $nivel = 1): bool
    {
        if ($nivel >= 3) return true;
        $cfg = $this->getConfigAprobacion($idEmpresa);
        return in_array($idUsuario, $cfg['aprobadores'], true);
    }

    public function getAprobadoresNombres(int $idEmpresa): array
    {
        $cfg = $this->getConfigAprobacion($idEmpresa);
        if (empty($cfg['aprobadores'])) return [];
        return array_column($this->repo->getNombresUsuarios($cfg['aprobadores']), 'nombre');
    }

    // ─── Armado del lote (BORRADOR) ─────────────────────────────────────────────

    public function crearLote(int $idEmpresa, int $idUsuario, array $data): int
    {
        $this->rules->validarCabecera($data);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $numero = $this->repo->siguienteNumero($idEmpresa);
            $idLote = $this->repo->crearCabecera([
                'id_empresa'           => $idEmpresa,
                'numero'               => $numero,
                'tipo_lote'            => $data['tipo_lote'],
                'id_forma_pago_origen' => $data['id_forma_pago_origen'],
                'id_banco_formato'     => $data['id_banco_formato'] ?? null,
                'fecha_pago'           => $data['fecha_pago'],
                'observaciones'        => $data['observaciones'] ?? null,
                'created_by'           => $idUsuario,
            ]);
            $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'transferencias_lotes', $idLote, null, [
                'numero' => $numero, 'tipo' => $data['tipo_lote'],
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return $idLote;
    }

    /**
     * Agrega líneas al lote a partir de ids de egresos_pagos. Revalida
     * disponibilidad dentro de la transacción para evitar carreras entre
     * dos usuarios armando lotes a la vez.
     */
    public function agregarLineas(int $idLote, int $idEmpresa, int $idUsuario, array $idsEgresoPago): array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if ($lote['estado'] !== 'BORRADOR') throw new \InvalidArgumentException('Solo se pueden agregar pagos a un lote en BORRADOR.');

        $idsEgresoPago = array_values(array_unique(array_map('intval', $idsEgresoPago)));
        if (empty($idsEgresoPago)) throw new \InvalidArgumentException('No se seleccionó ningún pago.');

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $noDisponibles = $this->repo->idsEgresoPagoNoDisponibles($idsEgresoPago, $idLote);
            if (!empty($noDisponibles)) {
                throw new \InvalidArgumentException('Uno o más pagos ya fueron incluidos en otro lote de pago bancario. Actualice la lista.');
            }

            foreach ($idsEgresoPago as $idPago) {
                $pago = $this->repo->getPagoPorId($idPago, $idEmpresa);
                if (!$pago) continue;
                $this->repo->crearDetalle([
                    'id_lote'             => $idLote,
                    'id_empresa'          => $idEmpresa,
                    'id_egreso'           => $pago['id_egreso'],
                    'id_egreso_pago'      => $pago['id_egreso_pago'],
                    'tipo_beneficiario'   => $pago['tipo_sujeto'],
                    'id_proveedor'        => $pago['id_proveedor'],
                    'id_empleado'         => $pago['id_empleado'],
                    'nombre_beneficiario' => $pago['beneficiario'],
                    'identificacion'      => $pago['identificacion'],
                    'id_banco_ecuador'    => $pago['id_banco'],
                    'tipo_cuenta'         => $pago['tipo_cuenta'],
                    'numero_cuenta'       => $pago['numero_cuenta'],
                    'monto'               => $pago['monto'],
                    'concepto'            => 'Pago egreso #' . $pago['numero_egreso'],
                    'created_by'          => $idUsuario,
                ]);
            }
            $this->repo->recalcularTotales($idLote, $idEmpresa);
            $this->log->registrar($idUsuario, $idEmpresa, 'agregar_lineas', 'transferencias_lotes', $idLote, null, ['pagos_agregados' => count($idsEgresoPago)]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        return $this->getDetalleCompleto($idLote, $idEmpresa) ?? [];
    }

    public function quitarLinea(int $idLote, int $idDetalle, int $idEmpresa, int $idUsuario): array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if ($lote['estado'] !== 'BORRADOR') throw new \InvalidArgumentException('Solo se pueden quitar pagos de un lote en BORRADOR.');

        $this->repo->eliminarDetalleLinea($idDetalle, $idLote, $idEmpresa);
        $this->repo->recalcularTotales($idLote, $idEmpresa);
        $this->log->registrar($idUsuario, $idEmpresa, 'quitar_linea', 'transferencias_lotes', $idLote, null, ['id_detalle' => $idDetalle]);

        return $this->getDetalleCompleto($idLote, $idEmpresa) ?? [];
    }

    public function actualizarCabecera(int $idLote, int $idEmpresa, int $idUsuario, array $data): bool
    {
        $this->rules->validarCabecera($data);
        return $this->repo->actualizarCabecera($idLote, $idEmpresa, $data, $idUsuario);
    }

    // ─── Aprobación ─────────────────────────────────────────────────────────────

    public function enviarAprobacion(int $idLote, int $idEmpresa, int $idUsuario): array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if ($lote['estado'] !== 'BORRADOR') throw new \InvalidArgumentException('El lote ya fue enviado a aprobación.');

        $detalle = $this->repo->getDetalle($idLote, $idEmpresa);
        $this->rules->validarTieneLineas($detalle);
        $errores = $this->rules->validarLineasCompletas($detalle);
        if (!empty($errores)) {
            throw new \InvalidArgumentException(implode(' ', $errores));
        }

        $cfg = $this->getConfigAprobacion($idEmpresa);
        if (!$cfg['requiere']) {
            $this->aprobar($idLote, $idEmpresa, $idUsuario, true);
            return ['estado' => 'APROBADO'];
        }

        $token = bin2hex(random_bytes(24));
        $this->repo->actualizarEstado($idLote, $idEmpresa, 'PENDIENTE_APROBACION', ['token_aprobacion' => $token]);
        $this->log->registrar($idUsuario, $idEmpresa, 'enviar_aprobacion', 'transferencias_lotes', $idLote, ['estado' => 'BORRADOR'], ['estado' => 'PENDIENTE_APROBACION']);

        if ($cfg['notificar']) {
            try { $this->notificarAprobadores($idEmpresa, $idLote, $cfg['aprobadores'], $token, $idUsuario); } catch (\Throwable $e) {}
        }

        return ['estado' => 'PENDIENTE_APROBACION'];
    }

    public function aprobar(int $idLote, int $idEmpresa, int $idUsuario, bool $auto = false, int $nivel = 3): array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if (!in_array($lote['estado'], ['BORRADOR', 'PENDIENTE_APROBACION'], true)) {
            throw new \InvalidArgumentException('Solo se pueden aprobar lotes en borrador o pendientes de aprobación.');
        }
        if (!$auto && $nivel < 3 && (int) ($lote['created_by'] ?? 0) === $idUsuario) {
            throw new \InvalidArgumentException('No puede aprobar un lote que usted mismo armó. Debe aprobarlo otro usuario autorizado.');
        }

        $this->repo->actualizarEstado($idLote, $idEmpresa, 'APROBADO', [
            'aprobado_por' => $idUsuario ?: null,
            'aprobado_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->log->registrar($idUsuario, $idEmpresa, $auto ? 'aprobar_auto' : 'aprobar', 'transferencias_lotes', $idLote, ['estado' => $lote['estado']], ['estado' => 'APROBADO']);

        return ['ok' => true, 'estado' => 'APROBADO'];
    }

    public function rechazar(int $idLote, int $idEmpresa, int $idUsuario, string $motivo, int $nivel = 3): array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if ($lote['estado'] !== 'PENDIENTE_APROBACION') throw new \InvalidArgumentException('Solo se pueden rechazar lotes pendientes de aprobación.');
        if ($nivel < 3 && (int) ($lote['created_by'] ?? 0) === $idUsuario) {
            throw new \InvalidArgumentException('No puede rechazar un lote que usted mismo armó.');
        }

        $this->repo->actualizarEstado($idLote, $idEmpresa, 'RECHAZADO', [
            'rechazado_por'  => $idUsuario,
            'rechazado_at'   => date('Y-m-d H:i:s'),
            'motivo_rechazo' => $motivo,
        ]);
        $this->log->registrar($idUsuario, $idEmpresa, 'rechazar', 'transferencias_lotes', $idLote, ['estado' => 'PENDIENTE_APROBACION'], ['estado' => 'RECHAZADO', 'motivo' => $motivo]);
        return ['ok' => true, 'estado' => 'RECHAZADO'];
    }

    // ─── Generación de archivo / confirmación ──────────────────────────────────

    public function generarArchivo(int $idLote, int $idEmpresa, int $idUsuario): array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if (!in_array($lote['estado'], ['APROBADO', 'GENERADO'], true)) {
            throw new \InvalidArgumentException('El lote debe estar aprobado para generar el archivo.');
        }

        $detalle = $this->repo->getDetalle($idLote, $idEmpresa);
        $this->rules->validarTieneLineas($detalle);

        $codigoBanco = null;
        if (!empty($lote['id_banco_formato'])) {
            $bancos = (new \App\repositories\modulos\FormaPagoRepository())->getBancosDisponibles();
            foreach ($bancos as $b) {
                if ((int) $b['id'] === (int) $lote['id_banco_formato']) { $codigoBanco = $b['nombre_banco']; break; }
            }
        }
        $formatter = TransferenciaFormatterFactory::getFormatter($codigoBanco);

        $dir = MVC_ROOT . '/storage/transferencias/' . $idEmpresa;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $rutaBase = $dir . '/lote_' . $lote['numero'] . '_' . date('YmdHis');

        $rutaFinal = $formatter->generar($lote, $detalle, $rutaBase);

        $this->repo->actualizarEstado($idLote, $idEmpresa, 'GENERADO', [
            'archivo_generado_path' => $rutaFinal,
            'archivo_generado_at'   => date('Y-m-d H:i:s'),
            'archivo_generado_by'   => $idUsuario,
        ]);
        $this->log->registrar($idUsuario, $idEmpresa, 'generar_archivo', 'transferencias_lotes', $idLote, null, ['archivo' => basename($rutaFinal)]);

        return ['ok' => true, 'estado' => 'GENERADO', 'archivo' => basename($rutaFinal)];
    }

    public function getRutaArchivo(int $idLote, int $idEmpresa): ?string
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote || empty($lote['archivo_generado_path'])) return null;
        return file_exists($lote['archivo_generado_path']) ? $lote['archivo_generado_path'] : null;
    }

    public function confirmarEnvio(int $idLote, int $idEmpresa, int $idUsuario): array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if ($lote['estado'] !== 'GENERADO') throw new \InvalidArgumentException('Debe generar el archivo antes de confirmar el envío.');

        $this->repo->actualizarEstado($idLote, $idEmpresa, 'CONFIRMADO', [
            'confirmado_por' => $idUsuario,
            'confirmado_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->log->registrar($idUsuario, $idEmpresa, 'confirmar_envio', 'transferencias_lotes', $idLote, ['estado' => 'GENERADO'], ['estado' => 'CONFIRMADO']);
        return ['ok' => true, 'estado' => 'CONFIRMADO'];
    }

    public function anular(int $idLote, int $idEmpresa, int $idUsuario, string $motivo): array
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if (!in_array($lote['estado'], ['CONFIRMADO', 'GENERADO', 'APROBADO', 'PENDIENTE_APROBACION'], true)) {
            throw new \InvalidArgumentException('Este lote no se puede anular en su estado actual.');
        }

        $this->repo->actualizarEstado($idLote, $idEmpresa, 'ANULADO', [
            'motivo_anulacion' => $motivo,
            'anulado_por'      => $idUsuario,
            'anulado_at'       => date('Y-m-d H:i:s'),
        ]);
        $this->log->registrar($idUsuario, $idEmpresa, 'anular', 'transferencias_lotes', $idLote, ['estado' => $lote['estado']], ['estado' => 'ANULADO', 'motivo' => $motivo]);
        return ['ok' => true, 'estado' => 'ANULADO'];
    }

    public function eliminar(int $idLote, int $idEmpresa, int $idUsuario): bool
    {
        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) throw new \InvalidArgumentException('Lote no encontrado.');
        if ($lote['estado'] !== 'BORRADOR') {
            throw new \InvalidArgumentException('Solo se pueden eliminar lotes en BORRADOR. Use "Anular" para lotes ya avanzados.');
        }
        $ok = $this->repo->eliminar($idLote, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'transferencias_lotes', $idLote, $lote, ['eliminado' => true]);
        return $ok;
    }

    // ─── Aprobación desde el enlace del correo (por token, sin sesión) ──────────

    public function getLotePorToken(string $token): ?array
    {
        $lote = $this->repo->getByToken($token);
        if (!$lote) return null;
        $lote['detalle'] = $this->repo->getDetalle((int) $lote['id'], (int) $lote['id_empresa']);
        $emp = $this->empresaRepo()->getEmisorConfig((int) $lote['id_empresa']) ?? [];
        $lote['empresa_nombre'] = $emp['nombre_comercial'] ?? ($emp['nombre'] ?? '');
        return $lote;
    }

    public function aprobarPorToken(string $token): array
    {
        $lote = $this->repo->getByToken($token);
        if (!$lote) throw new \InvalidArgumentException('Enlace inválido o ya utilizado.');
        if ($lote['estado'] !== 'PENDIENTE_APROBACION') {
            throw new \InvalidArgumentException('Este lote ya no está pendiente (estado: ' . $lote['estado'] . ').');
        }
        $idEmpresa = (int) $lote['id_empresa'];
        $idLote    = (int) $lote['id'];

        $cfg = $this->getConfigAprobacion($idEmpresa);
        $aprobadaPor = $cfg['aprobadores'][0] ?? 0;

        $res = $this->aprobar($idLote, $idEmpresa, $aprobadaPor, true);
        $this->repo->clearToken($idLote);
        return $res + ['numero' => $lote['numero']];
    }

    public function rechazarPorToken(string $token, string $motivo): array
    {
        $lote = $this->repo->getByToken($token);
        if (!$lote) throw new \InvalidArgumentException('Enlace inválido o ya utilizado.');
        if ($lote['estado'] !== 'PENDIENTE_APROBACION') {
            throw new \InvalidArgumentException('Este lote ya no está pendiente (estado: ' . $lote['estado'] . ').');
        }
        $idEmpresa = (int) $lote['id_empresa'];
        $idLote    = (int) $lote['id'];
        $cfg = $this->getConfigAprobacion($idEmpresa);
        $aprobadaPor = $cfg['aprobadores'][0] ?? 0;

        $this->repo->actualizarEstado($idLote, $idEmpresa, 'RECHAZADO', [
            'rechazado_por'  => $aprobadaPor ?: null,
            'rechazado_at'   => date('Y-m-d H:i:s'),
            'motivo_rechazo' => $motivo,
        ]);
        $this->repo->clearToken($idLote);
        $this->log->registrar($aprobadaPor, $idEmpresa, 'rechazar_correo', 'transferencias_lotes', $idLote, ['estado' => 'PENDIENTE_APROBACION'], ['estado' => 'RECHAZADO', 'motivo' => $motivo]);
        return ['ok' => true, 'estado' => 'RECHAZADO', 'numero' => $lote['numero']];
    }

    // ─── Notificación (correo a aprobadores) ────────────────────────────────────

    private function notificarAprobadores(int $idEmpresa, int $idLote, array $idsAprobadores, ?string $token = null, int $creadorId = 0): void
    {
        $idsAprobadores = array_values(array_filter($idsAprobadores, static fn($id) => (int) $id !== $creadorId));
        if (empty($idsAprobadores)) return;

        $usuarios = $this->repo->getNombresUsuarios($idsAprobadores);
        $correos  = array_values(array_filter(array_map(static fn($u) => trim((string) ($u['mail'] ?? '')), $usuarios)));
        if (empty($correos)) {
            $this->log->registrar(0, $idEmpresa, 'notificar_pendiente_sin_correo', 'transferencias_lotes', $idLote, null, ['aprobadores' => $idsAprobadores]);
            return;
        }

        $lote = $this->repo->getById($idLote, $idEmpresa);
        if (!$lote) return;

        $emp = $this->empresaRepo()->getEmisorConfig($idEmpresa) ?? [];
        $empNombre = $emp['nombre_comercial'] ?? ($emp['nombre'] ?? '');

        $publicUrl = (defined('APP_URL') && APP_URL !== '') ? APP_URL : (defined('BASE_URL') ? BASE_URL : '');
        $publicUrl = rtrim($publicUrl, '/');
        $url = $token ? ($publicUrl . '/aprobar-transferencia/' . $token) : ($publicUrl . '/modulos/transferencias');

        $data = [
            'numero'       => $lote['numero'],
            'tipo'         => $lote['tipo_lote'],
            'monto_total'  => number_format((float) $lote['monto_total'], 2),
            'cantidad'     => $lote['cantidad_pagos'],
            'empresa'      => $empNombre,
            'creador'      => $lote['creado_por_nombre'] ?? '',
            'url'          => $url,
        ];

        require_once MVC_APP . '/helpers/mail.php';
        $ok = \notificar_lote_transferencia_pendiente($correos, $data);

        $this->log->registrar(0, $idEmpresa, $ok ? 'notificar_pendiente_ok' : 'notificar_pendiente_error', 'transferencias_lotes', $idLote, null, [
            'correos' => $correos, 'error' => $ok ? null : ($GLOBALS['LAST_EMAIL_ERROR'] ?? null),
        ]);
    }
}
