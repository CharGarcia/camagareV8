<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\Booleano;
use App\repositories\modulos\BodegaRepository;
use App\repositories\modulos\CargaInventarioRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\BodegaRules;
use App\Rules\modulos\CargaInventarioRules;
use App\Services\LogSistemaService;
use App\Traits\PeriodoContableTrait;
use App\core\Database;

/**
 * Lógica de negocio de Cargas de Inventario.
 *
 * Flujo:
 *   1. Importación (Excel/CSV) → crea la carga (cabecera + detalle) con validación
 *      por línea. La carga queda "validada" solo si TODAS las líneas están OK.
 *   2. Si el módulo Aprobaciones NO exige aprobación y la carga está validada,
 *      se aplica al kardex de inmediato (estado 'aprobada').
 *   3. Si exige aprobación → queda 'pendiente' y se notifica a los aprobadores.
 *   4. Aprobar → aplica cada línea al kardex (InventarioService::ajusteManual).
 *      Solo puede aprobarse si está comprobada (validada = true). En una carga de
 *      ajuste cada línea es un conteo físico: se registra solo la diferencia con el
 *      saldo del momento, como entrada o salida.
 *   5. Anular (solo aprobadas) → reversa sus movimientos del kardex si sus productos
 *      no se usaron después y la deja 'anulada'. Una carga aprobada no se edita:
 *      se anula y el archivo corregido se importa como carga nueva.
 */
class CargaInventarioService
{
    use PeriodoContableTrait;

    /** Tope de conflictos que se listan al usuario cuando una carga no se puede anular. */
    private const MAX_CONFLICTOS = 100;

    private const MENSAJE_FALTA_SQL_ANULACION = 'Para anular cargas aprobadas falta aplicar en la base de datos el script '
        . 'database/migrations/20260917_cargas_inventario_anulacion.sql.';

    /** Sufijo de los errores que aparecen recién al aprobar (la carga se importó con otras reglas). */
    private const CORREGIR_E_IMPORTAR = ' Elimine la carga y vuelva a importarla corregida.';

    private CargaInventarioRepository $repo;
    private CargaInventarioRules $rules;
    private LogSistemaService $log;
    private ?InventarioService $invService = null;
    private ?EmpresaRepository $empRepo = null;
    private ?AprobacionesService $aprobService = null;
    private ?BodegaService $bodegaService = null;

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

    private function bodegaService(): BodegaService
    {
        if ($this->bodegaService === null) {
            $this->bodegaService = new BodegaService(new BodegaRepository(), new BodegaRules(), $this->log);
        }
        return $this->bodegaService;
    }

    // ─── Listado / detalle ────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null, array $ordenMulti = []): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $ordenMulti);
    }

    /** Opciones de los selects del modal de filtros del listado (valores usados por la empresa). */
    public function getOpcionesFiltroListado(int $idEmpresa): array
    {
        return $this->repo->getOpcionesFiltroListado($idEmpresa);
    }

    /** Pestaña "Detalles" del modal de filtros: búsqueda libre dentro de las líneas de las cargas. */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        return $this->repo->buscarEnDetalles($idEmpresa, $q, $idUsuario, $limit);
    }

    public function getDetalleCompleto(int $idCarga, int $idEmpresa): ?array
    {
        $cab = $this->repo->getById($idCarga, $idEmpresa);
        if (!$cab) return null;
        $cab['detalle'] = $this->agregarEstimacionAjuste($cab, $this->repo->getDetalle($idCarga, $idEmpresa));

        // Nombre de quien anuló: se resuelve aparte y no en el JOIN de getById(), que así
        // sigue funcionando en una base donde aún no existe la columna anulada_por.
        $cab['anulado_por_nombre'] = null;
        if (!empty($cab['anulada_por'])) {
            $cab['anulado_por_nombre'] = $this->repo->getNombresUsuarios([(int) $cab['anulada_por']])[0]['nombre'] ?? null;
        }
        return $cab;
    }

    /**
     * Ajuste pendiente: a cada línea le agrega `saldo_actual` y `diferencia_estimada`, para
     * que quien aprueba vea cuánto se movería HOY. Es una estimación: al aprobar se recalcula
     * con el saldo de ese momento. Otras cargas: las líneas tal cual.
     */
    private function agregarEstimacionAjuste(array $cabecera, array $detalle): array
    {
        if (($cabecera['tipo_movimiento'] ?? '') !== 'ajuste' || ($cabecera['estado'] ?? '') !== 'pendiente') {
            return $detalle;
        }
        $saldos = $this->repo->getSaldosActualesAjuste((int) $cabecera['id'], (int) $cabecera['id_empresa']);
        foreach ($detalle as &$d) {
            if (array_key_exists((int) $d['id'], $saldos)) {
                $d['saldo_actual']        = $saldos[(int) $d['id']];
                $d['diferencia_estimada'] = round((float) $d['cantidad'] - $saldos[(int) $d['id']], 6);
            }
        }
        unset($d);
        return $detalle;
    }

    /** Cabecera sin líneas (para validar a quién pertenece antes de actuar sobre la carga). */
    public function getCabecera(int $idCarga, int $idEmpresa): ?array
    {
        return $this->repo->getById($idCarga, $idEmpresa);
    }

    /** Datos de referencia (productos y bodegas) para las hojas de la plantilla. */
    public function getReferenciasPlantilla(int $idEmpresa): array
    {
        return [
            'productos' => $this->repo->getProductosParaPlantilla($idEmpresa),
            'bodegas'   => $this->repo->getBodegasParaPlantilla($idEmpresa),
        ];
    }

    // ─── Configuración de aprobación (módulo Aprobaciones) ─────────────────────
    // Antes vivía en empresa_establecimiento.inv_*; ahora la resuelve el motor
    // de Aprobaciones por empresa (checkpoint 'carga_inventario').

    private function aprobacionesService(): AprobacionesService
    {
        if ($this->aprobService === null) {
            $this->aprobService = new AprobacionesService();
        }
        return $this->aprobService;
    }

    /**
     * @param float|null $monto Costo total de la carga. Si el checkpoint tiene
     *                          monto mínimo configurado, las cargas por debajo
     *                          de ese monto no piden aprobación.
     */
    public function getConfigAprobacion(int $idEmpresa, ?float $monto = null): array
    {
        return $this->aprobacionesService()->getConfigResuelta(
            AprobacionesService::CARGA_INVENTARIO,
            $idEmpresa,
            $monto
        );
    }

    /** ¿El usuario puede aprobar cargas? (aprobador configurado o super admin). */
    public function esAprobador(int $idUsuario, int $idEmpresa, int $nivel = 1): bool
    {
        return $this->aprobacionesService()->esAprobador(
            AprobacionesService::CARGA_INVENTARIO,
            $idEmpresa,
            $idUsuario,
            $nivel
        );
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
     * Crea una carga desde filas importadas. Cada fila: codigo_producto, bodega, cantidad,
     * costo_unitario?, numero_lote?, fecha_caducidad?, nup?, observacion?.
     *
     * En una carga de AJUSTE la cantidad es lo contado (conteo físico): admite 0, no admite
     * NUP, cada producto va una vez por bodega y lote, y si el producto tiene stock por lotes
     * en la bodega hay que indicar el lote contado. La diferencia se calcula al aprobar.
     */
    public function crearDesdeImportacion(int $idEmpresa, int $idUsuario, string $tipoMovimiento, ?string $observacion, array $filas): array
    {
        $this->rules->validarCabecera(['tipo_movimiento' => $tipoMovimiento, 'filas' => $filas]);
        $esAjuste = $tipoMovimiento === 'ajuste';

        // Validar cada línea. El Excel identifica el producto por CÓDIGO principal y la
        // bodega por NOMBRE (se resuelven al id interno).
        $lineas = [];
        foreach (array_values($filas) as $f) {
            $codProd = trim((string) ($f['codigo_producto'] ?? ''));
            $nomBod  = trim((string) ($f['bodega'] ?? ''));
            $cant    = (float) ($f['cantidad'] ?? 0);
            $nup     = ($f['nup'] ?? '') !== '' ? trim((string) $f['nup']) : null;
            $lote    = $esAjuste
                ? CargaInventarioRules::normalizarLote($f['numero_lote'] ?? null)
                : (($f['numero_lote'] ?? '') !== '' ? trim((string) $f['numero_lote']) : null);

            $idProd = $codProd !== '' ? $this->repo->getProductoIdPorCodigo($codProd, $idEmpresa) : 0;
            $idBod  = $nomBod  !== '' ? $this->repo->getBodegaIdPorNombre($nomBod, $idEmpresa)   : 0;

            $err = null;
            if ($codProd === '') {
                $err = 'Falta el código del producto.';
            } elseif ($idProd === 0) {
                $err = $this->repo->codigoEsServicio($codProd, $idEmpresa)
                    ? "El código \"{$codProd}\" corresponde a un servicio y no puede cargarse al inventario."
                    : "El producto con código \"{$codProd}\" no existe en la empresa.";
            } elseif ($nomBod === '') {
                $err = 'Falta la bodega.';
            } elseif ($idBod === 0) {
                $err = "La bodega \"{$nomBod}\" no existe en la empresa.";
            } elseif ($esAjuste) {
                $err = $this->rules->validarLineaAjuste($f['cantidad'] ?? null, $nup)
                    ?? ($lote === null ? $this->errorAjusteSinLote($idProd, $idBod, $idEmpresa) : null);
            } elseif ($cant <= 0) {
                $err = 'La cantidad debe ser mayor a cero.';
            } else {
                $err = $this->rules->validarCantidadSeries($cant, CargaInventarioRules::seriesDeNup($nup));
            }

            $lineas[] = [
                'id_producto'      => $idProd,
                'id_bodega'        => $idBod,
                'cantidad'         => $cant,
                'costo_unitario'   => (float) ($f['costo_unitario'] ?? 0),
                'numero_lote'      => $lote,
                'fecha_caducidad'  => ($f['fecha_caducidad'] ?? '') !== '' ? trim((string) $f['fecha_caducidad']) : null,
                'nup'              => $nup,
                'observacion'      => ($f['observacion'] ?? '') !== '' ? trim((string) $f['observacion']) : null,
                'cod_producto_raw' => $codProd,
                'cod_bodega_raw'   => $nomBod,
                'linea_valida'     => $err === null,
                'error_linea'      => $err,
            ];
        }

        // Ajuste: un mismo saldo no se cuenta dos veces en el archivo.
        if ($esAjuste) {
            foreach ($this->rules->erroresDuplicadosAjuste($lineas) as $i => $err) {
                if ($lineas[$i]['linea_valida']) {
                    $lineas[$i]['linea_valida'] = false;
                    $lineas[$i]['error_linea']  = $err;
                }
            }
        }

        $errores = [];
        foreach ($lineas as $i => $ln) {
            if (!$ln['linea_valida']) {
                $errores[] = 'Fila ' . ($i + 2) . ': ' . $ln['error_linea']; // +2: fila 1 = encabezados
            }
        }
        $todasOk = $errores === [];

        // La config se resuelve con el costo total de la carga: si el checkpoint
        // tiene monto mínimo, las cargas por debajo no piden aprobación.
        $costoTotal = array_sum(array_map(
            static fn(array $ln): float => (float) $ln['cantidad'] * (float) $ln['costo_unitario'],
            $lineas
        ));
        $cfg = $this->getConfigAprobacion($idEmpresa, $costoTotal);

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
            'lineas'   => count($lineas),
            'errores'  => $errores,
            'requiere_aprobacion' => $cfg['requiere'],
        ];
    }

    // ─── Aprobación / rechazo ───────────────────────────────────────────────────

    /**
     * Aprueba la carga y aplica cada línea al kardex. Solo si está comprobada.
     *
     * Entrada / salida: cada línea mueve su cantidad; si su celda NUP trae varias series, una
     * unidad por serie (la cantidad debe coincidir). Ajuste: cada línea fija el saldo contado
     * y solo se registra la diferencia (ver aplicarLineaAjuste()).
     *
     * @param bool $auto true cuando la aprobación es automática (no exige ser aprobador).
     */
    public function aprobar(int $idCarga, int $idEmpresa, int $idUsuario, bool $auto = false, int $nivel = 3): array
    {
        $db = Database::getConnection();
        $manejaTransaccion = !$db->inTransaction();
        if ($manejaTransaccion) {
            $db->beginTransaction();
        }
        try {
            // La cabecera se bloquea: dos aprobaciones simultáneas de la misma carga no la
            // aplican dos veces al kardex (la segunda espera y la encuentra ya aprobada).
            $carga = $this->repo->getByIdParaActualizar($idCarga, $idEmpresa);
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

            $detalle = array_values($this->repo->getDetalle($idCarga, $idEmpresa));
            if (empty($detalle)) {
                throw new \InvalidArgumentException('La carga no tiene líneas.');
            }

            $esAjuste = $carga['tipo_movimiento'] === 'ajuste';
            if ($esAjuste) {
                if (!$this->repo->soportaAjusteConteo()) {
                    throw new \RuntimeException('Para aprobar cargas de ajuste falta aplicar en la base de datos el script '
                        . 'database/migrations/20260917_cargas_inventario_ajuste_conteo.sql.');
                }
                // Las cargas importadas antes de estas reglas no pasaron por ellas.
                $this->validarAjusteAntesDeAplicar($detalle);
            }

            foreach ($detalle as $i => $d) {
                if (empty($d['linea_valida']) || $d['linea_valida'] === 'f') {
                    throw new \InvalidArgumentException('Hay líneas con error; no se puede aprobar.');
                }
                if ($esAjuste) {
                    $this->aplicarLineaAjuste($carga, $d, $i + 2, $idEmpresa, $idUsuario);
                } else {
                    $this->aplicarLineaMovimiento($carga, $d, $i + 2, $idEmpresa, $idUsuario);
                }
            }

            $this->repo->actualizarEstado($idCarga, $idEmpresa, 'aprobada', $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $auto ? 'aprobar_auto' : 'aprobar', 'inventario_cargas', $idCarga, ['estado' => 'pendiente'], ['estado' => 'aprobada']);

            if ($manejaTransaccion) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($manejaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['ok' => true, 'estado' => 'aprobada'];
    }

    /** Línea de entrada o salida: su cantidad, con una unidad por serie si trae varias. */
    private function aplicarLineaMovimiento(array $carga, array $d, int $fila, int $idEmpresa, int $idUsuario): void
    {
        $series = CargaInventarioRules::seriesDeNup($d['nup'] ?? null);
        $error  = $this->rules->validarCantidadSeries((float) $d['cantidad'], $series);
        if ($error !== null) {
            throw new \InvalidArgumentException("Fila {$fila}: {$error}" . self::CORREGIR_E_IMPORTAR);
        }

        $this->inventarioService()->ajusteManual([
            'id_producto'     => (int) $d['id_producto'],
            'id_bodega'       => (int) $d['id_bodega'],
            'tipo_movimiento' => $carga['tipo_movimiento'],
            'cantidad'        => (float) $d['cantidad'],
            'costo_unitario'  => (float) $d['costo_unitario'],
            'numero_lote'     => $d['numero_lote'] ?: null,
            'fecha_caducidad' => $d['fecha_caducidad'] ?: null,
            'nup'             => $series !== [] ? implode("\n", $series) : null,
            // Con una sola serie, un movimiento por la cantidad completa (como ventas y compras);
            // con varias, ajusteManual registra un movimiento de 1 por serie.
            'is_individual'   => count($series) > 1 ? '1' : '0',
            'observaciones'   => !empty($d['observacion']) ? $d['observacion'] : ('Carga de inventario #' . $carga['numero']),
            'referencia_tipo' => 'carga_inventario',
            'referencia_id'   => (int) $carga['id'],
        ], $idEmpresa, $idUsuario);
    }

    /**
     * Reglas del ajuste que no dependen del stock (cantidad contada, NUP, duplicados), para las
     * cargas pendientes importadas antes de que existieran. Corta en el primer error.
     */
    private function validarAjusteAntesDeAplicar(array $detalle): void
    {
        $lineas = [];
        foreach ($detalle as $i => $d) {
            $error = $this->rules->validarLineaAjuste($d['cantidad'] ?? null, $d['nup'] ?? null);
            if ($error !== null) {
                throw new \InvalidArgumentException('Fila ' . ($i + 2) . ": {$error}" . self::CORREGIR_E_IMPORTAR);
            }
            $lineas[] = [
                'id_producto' => (int) $d['id_producto'],
                'id_bodega'   => (int) $d['id_bodega'],
                'numero_lote' => CargaInventarioRules::normalizarLote($d['numero_lote'] ?? null),
            ];
        }
        foreach ($this->rules->erroresDuplicadosAjuste($lineas) as $i => $error) {
            throw new \InvalidArgumentException('Fila ' . ($i + 2) . ": {$error}" . self::CORREGIR_E_IMPORTAR);
        }
    }

    /**
     * Línea de un ajuste por conteo físico: la cantidad es lo contado. Con el stock bloqueado
     * se lee el saldo de ese momento —del lote si la línea trae lote, o el total del producto
     * en la bodega— y solo se registra la diferencia: entrada si sobra (al costo de la línea o,
     * si no trae, al costo promedio) o salida si falta (al costo promedio). Sin diferencia no
     * hay movimiento. Saldo y diferencia quedan en la línea.
     */
    private function aplicarLineaAjuste(array $carga, array $d, int $fila, int $idEmpresa, int $idUsuario): void
    {
        $idProducto = (int) $d['id_producto'];
        $idBodega   = (int) $d['id_bodega'];
        $lote       = CargaInventarioRules::normalizarLote($d['numero_lote'] ?? null);
        $contado    = (float) $d['cantidad'];
        $invRepo    = $this->inventarioService()->getRepository();

        $invRepo->lockStock($idProducto, $idBodega, $idEmpresa);
        if ($lote === null) {
            $error = $this->errorAjusteSinLote($idProducto, $idBodega, $idEmpresa);
            if ($error !== null) {
                throw new \InvalidArgumentException("Fila {$fila}: {$error}" . self::CORREGIR_E_IMPORTAR);
            }
        }

        $saldo      = $lote === null
            ? $invRepo->getStockActual($idProducto, $idBodega, $idEmpresa)
            : $invRepo->getStockLote($idProducto, $idBodega, $idEmpresa, $lote);
        $diferencia = round($contado - $saldo, 6);
        if (abs($diferencia) < 0.000001) {
            $diferencia = 0.0;
        }

        if ($diferencia !== 0.0) {
            $entra = $diferencia > 0;
            $costo = ($entra && (float) $d['costo_unitario'] > 0)
                ? (float) $d['costo_unitario']
                : $invRepo->getCostoPromedio($idProducto, $idBodega, $idEmpresa);

            // Al entrar a un lote que ya existe sin indicar caducidad, se conserva la del lote.
            $caducidad = null;
            if ($entra) {
                $caducidad = $d['fecha_caducidad'] ?: null;
                if ($caducidad === null && $lote !== null) {
                    $caducidad = $invRepo->getLoteMasAntiguo($idProducto, $idBodega, $idEmpresa, $lote, false)['fecha_caducidad'] ?? null;
                }
            }

            $obs = 'Ajuste por conteo físico, carga #' . (int) $carga['numero'] . ': saldo '
                 . CargaInventarioRules::numero($saldo) . ', contado ' . CargaInventarioRules::numero($contado);
            if (!empty($d['observacion'])) {
                $obs .= ' · ' . $d['observacion'];
            }

            $this->inventarioService()->ajusteManual([
                'id_producto'     => $idProducto,
                'id_bodega'       => $idBodega,
                // Entrada o salida (no 'ajuste'): así el costo cuenta en el costo promedio.
                'tipo_movimiento' => $entra ? 'entrada' : 'salida',
                'cantidad'        => abs($diferencia),
                'costo_unitario'  => $costo,
                'numero_lote'     => $lote,
                'fecha_caducidad' => $caducidad,
                'observaciones'   => $obs,
                'referencia_tipo' => 'carga_inventario',
                'referencia_id'   => (int) $carga['id'],
            ], $idEmpresa, $idUsuario);
        }

        $this->repo->registrarResultadoAjuste((int) $d['id'], $idEmpresa, $saldo, $diferencia);
    }

    /**
     * Conteo sin lote de un producto que tiene stock repartido en lotes en la bodega: no se
     * sabe de qué lote sale o a cuál entra la diferencia, así que hay que contar por lote.
     */
    private function errorAjusteSinLote(int $idProducto, int $idBodega, int $idEmpresa): ?string
    {
        $lotes = [];
        foreach ($this->inventarioService()->getRepository()->getLotesDisponibles($idProducto, $idBodega, $idEmpresa) as $l) {
            $nombre = CargaInventarioRules::normalizarLote($l['numero_lote'] ?? null);
            if ($nombre !== null) {
                $lotes[] = mb_substr($nombre, 0, 30);
            }
        }
        if ($lotes === []) {
            return null;
        }
        $muestra = implode(', ', array_slice($lotes, 0, 3)) . (count($lotes) > 3 ? '…' : '');
        return "El producto tiene stock por lotes en esta bodega ({$muestra}): indique el lote que contó, una línea por lote.";
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
        $carga['detalle'] = $this->agregarEstimacionAjuste($carga, $this->repo->getDetalle((int) $carga['id'], (int) $carga['id_empresa']));
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
            throw new \InvalidArgumentException('No se puede eliminar una carga ya aprobada (afectó el kardex). Si hay que deshacerla, anúlela.');
        }
        if ($carga['estado'] === 'anulada') {
            throw new \InvalidArgumentException('Una carga anulada no se elimina: queda en el listado como constancia de que su stock se reversó.');
        }
        $ok = $this->repo->eliminar($idCarga, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'inventario_cargas', $idCarga, $carga, ['eliminado' => true]);
        return $ok;
    }

    // ─── Anulación de una carga aprobada ────────────────────────────────────────

    /**
     * Comprueba, sin escribir nada, si la carga aprobada se puede anular. El modal lo
     * consulta antes de pedir el motivo; anular() repite todo con el stock bloqueado.
     *
     * @return array{puede: bool, mensaje: string, conflictos: list<string>, movimientos: int}
     */
    public function comprobarAnulacion(int $idCarga, int $idEmpresa, int $idUsuario, int $nivel): array
    {
        if (!$this->repo->soportaAnulacion()) {
            return ['puede' => false, 'mensaje' => self::MENSAJE_FALTA_SQL_ANULACION, 'conflictos' => [], 'movimientos' => 0];
        }

        $carga = $this->repo->getById($idCarga, $idEmpresa);
        try {
            $movimientos = $this->validarAnulable($carga, $idEmpresa, $idUsuario, $nivel);
        } catch (\InvalidArgumentException $e) {
            return ['puede' => false, 'mensaje' => $e->getMessage(), 'conflictos' => [], 'movimientos' => 0];
        }

        $conflictos = $this->conflictosDeUso($idCarga, $idEmpresa);
        return [
            'puede'       => $conflictos === [],
            'mensaje'     => $conflictos === [] ? '' : $this->mensajeEnUso($carga),
            'conflictos'  => $conflictos,
            'movimientos' => count($movimientos),
        ];
    }

    /**
     * Anula una carga aprobada: reversa sus movimientos del kardex (quedan marcados
     * "ANULADO", mismo criterio que el resto del sistema: no se crean movimientos de
     * reverso) y la deja 'anulada' con el motivo.
     *
     * No se anula si sus productos ya se usaron: si, sin la carga, el saldo de algún
     * producto/bodega, lote o NUP habría quedado negativo en algún momento desde que se
     * aplicó (InventarioRepository::getConsumoPosteriorPorReferencia).
     */
    public function anular(int $idCarga, int $idEmpresa, int $idUsuario, string $motivo, int $nivel): array
    {
        $this->rules->validarMotivoAnulacion($motivo);
        $motivo = trim($motivo);
        if (!$this->repo->soportaAnulacion()) {
            throw new \RuntimeException(self::MENSAJE_FALTA_SQL_ANULACION);
        }

        $inventario = $this->inventarioService();
        $db = Database::getConnection();
        $manejaTransaccion = !$db->inTransaction();
        if ($manejaTransaccion) {
            $db->beginTransaction();
        }

        try {
            // 1. La carga primero: dos anulaciones simultáneas no reversan el stock dos veces.
            $carga       = $this->repo->getByIdParaActualizar($idCarga, $idEmpresa);
            $movimientos = $this->validarAnulable($carga, $idEmpresa, $idUsuario, $nivel);

            // 2. El stock de cada producto/bodega, en orden fijo para no interbloquearse con
            //    otro proceso que tome los mismos. Desde aquí nadie más mueve esos productos,
            //    así que el historial que se revisa abajo no cambia hasta el COMMIT.
            $pares = [];
            foreach ($movimientos as $m) {
                $pares[(int) $m['id_producto'] . ':' . (int) $m['id_bodega']] = [(int) $m['id_producto'], (int) $m['id_bodega']];
            }
            usort($pares, static fn(array $a, array $b): int => $a <=> $b);
            foreach ($pares as [$idProducto, $idBodega]) {
                $inventario->getRepository()->lockStock($idProducto, $idBodega, $idEmpresa);
            }

            // 3. ¿Ya se usaron sus productos?
            $conflictos = $this->conflictosDeUso($idCarga, $idEmpresa);
            if ($conflictos !== []) {
                $primeros = array_slice($conflictos, 0, 5);
                $resto    = count($conflictos) - count($primeros);
                throw new \InvalidArgumentException(
                    $this->mensajeEnUso($carga) . ' ' . implode(' ', $primeros) . ($resto > 0 ? " (y {$resto} más)." : '')
                );
            }

            // 4. Reversa: cada movimiento sale del stock y queda "ANULADO" en el kardex.
            foreach ($movimientos as $m) {
                $inventario->eliminarMovimiento((int) $m['id'], $idEmpresa, $idUsuario, true);
            }

            if (!$this->repo->anular($idCarga, $idEmpresa, $idUsuario, $motivo)) {
                throw new \RuntimeException('No se pudo anular la carga: cambió de estado mientras se procesaba. Actualice e intente de nuevo.');
            }

            $this->log->registrar($idUsuario, $idEmpresa, 'anular', 'inventario_cargas', $idCarga,
                ['estado' => 'aprobada'],
                [
                    'estado'                 => 'anulada',
                    'motivo'                 => $motivo,
                    'movimientos_reversados' => array_map('intval', array_column($movimientos, 'id')),
                ]
            );

            if ($manejaTransaccion) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($manejaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return [
            'ok'          => true,
            'estado'      => 'anulada',
            'numero'      => (int) $carga['numero'],
            'movimientos' => count($movimientos),
        ];
    }

    /**
     * Todo lo que impide anular la carga, salvo el uso posterior de sus productos: estado,
     * quién anula, movimientos enlazados, ambiente, período contable y acceso a sus bodegas.
     *
     * @return array<int, array<string, mixed>> Movimientos de kardex que la anulación reversa.
     */
    private function validarAnulable(?array $carga, int $idEmpresa, int $idUsuario, int $nivel): array
    {
        $this->rules->validarAnulable($carga, $idUsuario, $nivel);

        if (!$this->esAprobador($idUsuario, $idEmpresa, $nivel)) {
            throw new \InvalidArgumentException('Solo los aprobadores de cargas de inventario pueden anular una carga aprobada.');
        }

        $movimientos = $this->repo->getMovimientosKardex((int) $carga['id'], $idEmpresa);
        // Un ajuste cuyo conteo coincidió en todo con el sistema se aplicó sin movimientos:
        // se puede anular (no hay stock que reversar). Sin movimientos por otra razón, no.
        if ($movimientos === [] && !$this->repo->tieneAjusteAplicado((int) $carga['id'], $idEmpresa)) {
            throw new \InvalidArgumentException(
                'Esta carga no tiene movimientos de inventario enlazados (por ejemplo, porque viene de la migración '
                . 'del sistema anterior): no hay stock que reversar desde aquí.'
            );
        }
        foreach ($movimientos as $m) {
            if (!Booleano::es($m['ambiente_vigente'] ?? false)) {
                throw new \InvalidArgumentException(
                    'La carga se aplicó en un ambiente (pruebas o producción) distinto del que la empresa usa hoy: no se puede anular desde aquí.'
                );
            }
        }

        // Período contable: la fecha de la carga y la del movimiento de stock (su aprobación).
        $fechas = [(string) $carga['fecha']];
        foreach ($movimientos as $m) {
            $fechas[] = substr((string) $m['fecha_movimiento'], 0, 10);
        }
        foreach (array_unique($fechas) as $fecha) {
            try {
                $this->validarPeriodoContable($fecha, $idEmpresa, 'No se puede anular la carga porque su período contable está cerrado.');
            } catch (\PDOException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw new \InvalidArgumentException($e->getMessage(), 0, $e);
            }
        }

        $bodegas = [];
        foreach ($movimientos as $m) {
            $bodegas[(int) $m['id_bodega']] = (string) ($m['bodega_nombre'] ?? '');
        }
        foreach ($bodegas as $idBodega => $nombre) {
            if (!$this->bodegaService()->validarAccesoUsuario($idUsuario, $idBodega, $idEmpresa, $nivel)) {
                throw new \InvalidArgumentException("No tiene acceso a la bodega \"{$nombre}\" de esta carga.");
            }
        }

        return $movimientos;
    }

    private function mensajeEnUso(array $carga): string
    {
        return 'No se puede anular la carga #' . (int) $carga['numero'] . ': sus productos ya se usaron después de aplicarla.';
    }

    /**
     * Uso posterior de los productos de la carga, un texto por producto/bodega, lote o NUP.
     *
     * @return list<string>
     */
    private function conflictosDeUso(int $idCarga, int $idEmpresa): array
    {
        $filas = $this->inventarioService()->getRepository()
            ->getConsumoPosteriorPorReferencia('carga_inventario', $idCarga, $idEmpresa);

        $textos = array_map(fn(array $f): string => $this->textoConflicto($f, $idCarga), $filas);
        if (count($textos) > self::MAX_CONFLICTOS) {
            $resto  = count($textos) - self::MAX_CONFLICTOS;
            $textos = array_slice($textos, 0, self::MAX_CONFLICTOS);
            $textos[] = "… y {$resto} más.";
        }
        return $textos;
    }

    /** Una fila de getConsumoPosteriorPorReferencia() en palabras del usuario. */
    private function textoConflicto(array $f, int $idCarga): string
    {
        $producto = trim(($f['producto_codigo'] ?? '') . ' - ' . ($f['producto_nombre'] ?? ''), ' -');
        $donde    = ($producto !== '' ? $producto : 'Producto #' . (int) $f['id_producto'])
                  . ' en ' . (trim((string) ($f['bodega_nombre'] ?? '')) ?: 'bodega #' . (int) $f['id_bodega']);
        $clave    = (string) ($f['clave'] ?? '');
        $saldo    = rtrim(rtrim(number_format((float) $f['saldo_sin'], 4, '.', ''), '0'), '.');
        $fecha    = !empty($f['fecha_movimiento']) ? date('d-m-Y H:i:s', strtotime((string) $f['fecha_movimiento'])) : '';

        // El movimiento que provoca el conflicto es de la propia carga cuando el saldo ya era
        // negativo al aplicarla: la carga cubrió salidas anteriores a ella.
        $esLaCarga = ($f['referencia_tipo'] ?? '') === 'carga_inventario' && (int) ($f['referencia_id'] ?? 0) === $idCarga;
        $documento = trim((string) ($f['observaciones'] ?? ''));
        if ($documento === '') {
            $documento = ucfirst(str_replace('_', ' ', strtolower((string) ($f['referencia_tipo'] ?? 'movimiento'))))
                       . (!empty($f['referencia_id']) ? ' #' . (int) $f['referencia_id'] : '');
        }

        switch ($f['nivel']) {
            case 'nup':
                if ((float) $f['cantidad_doc'] < 0) {
                    return "{$donde}, NUP {$clave}: la serie volvió a entrar el {$fecha} ({$documento}); si se anula la carga quedaría dos veces en stock.";
                }
                return $esLaCarga
                    ? "{$donde}, NUP {$clave}: la serie ya tenía salidas registradas antes de esta carga."
                    : "{$donde}, NUP {$clave}: la serie ya salió el {$fecha} ({$documento}).";
            case 'lote':
                return $esLaCarga
                    ? "{$donde}, lote {$clave}: antes de esta carga el saldo del lote ya era {$saldo}; la carga cubrió salidas anteriores."
                    : "{$donde}, lote {$clave}: sin esta carga el saldo del lote habría quedado en {$saldo} el {$fecha} ({$documento}).";
            default:
                return $esLaCarga
                    ? "{$donde}: antes de esta carga el saldo ya era {$saldo}; la carga cubrió salidas anteriores."
                    : "{$donde}: sin esta carga el saldo habría quedado en {$saldo} el {$fecha} ({$documento}).";
        }
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
