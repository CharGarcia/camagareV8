<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\IdentificacionTercero;
use App\repositories\modulos\ReporteCarteraRepository;
use App\repositories\modulos\ClienteRepository;
use App\repositories\modulos\ProveedorRepository;
use App\models\Empresa;

class ReporteCarteraController extends BaseModuloController
{
    private ReporteCarteraRepository $repository;
    private const RUTA_MODULO = 'modulos/reporte_cartera';

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteCarteraRepository();
    }

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos/reporte_cartera/index', [
            'titulo'     => 'Reporte de Cartera',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => self::RUTA_MODULO,
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // FILTROS
    // ────────────────────────────────────────────────────────────────
    private function getFiltrosDesdeRequest(): array
    {
        $tipo = strtoupper(trim((string) ($_REQUEST['tipo'] ?? 'CLIENTE')));

        $idsRaw = $_REQUEST['id_entidad'] ?? [];
        if (!is_array($idsRaw)) {
            $idsRaw = $idsRaw !== '' ? [$idsRaw] : [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsRaw))));

        return [
            'tipo'        => $tipo === 'PROVEEDOR' ? 'PROVEEDOR' : 'CLIENTE',
            'ids'         => $ids,
            'todos'       => !empty($_REQUEST['todos']),
            'fecha_desde' => trim((string) ($_REQUEST['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_REQUEST['fecha_hasta'] ?? '')),
            // Número de documento (factura, recibo, compra, saldo inicial...) elegido
            // en el buscador "Documento": limita el estado de cuenta a ese documento
            // y a los abonos que lo cancelan.
            'documento'   => mb_substr(trim((string) ($_REQUEST['documento'] ?? '')), 0, 60),
        ];
    }

    /**
     * IDs de entidad a incluir: la selección manual, o —si "todos" está
     * activo— todos los clientes/proveedores con saldo pendiente a la fecha
     * de corte (una sola consulta agregada, no una por entidad).
     */
    private function resolverIds(int $idEmpresa, array $filtros): array
    {
        if (!$filtros['todos']) {
            // Si el usuario eligió a mano las dos fichas del mismo tercero (cédula y RUC),
            // se deja una sola: construirLedger() ya abarca las dos y de lo contrario
            // saldría el mismo estado de cuenta repetido.
            $ids = $this->unaEntidadPorTercero($idEmpresa, $filtros['tipo'], $filtros['ids']);
            return $this->soloEntidadesPropias($idEmpresa, $filtros['tipo'], $ids);
        }

        $fechaHasta = $filtros['fecha_hasta'] !== '' ? $filtros['fecha_hasta'] : null;
        $rows = $filtros['tipo'] === 'PROVEEDOR'
            ? $this->repository->getProveedoresConSaldoPendiente($idEmpresa, $fechaHasta)
            : $this->repository->getClientesConSaldoPendiente($idEmpresa, $fechaHasta);

        return $this->soloEntidadesPropias($idEmpresa, $filtros['tipo'], array_map(fn($r) => (int) $r['id'], $rows));
    }

    /**
     * Registros propios (§6) en este reporte: quien NO tiene acceso total ('t')
     * solo consulta el estado de cuenta de los clientes/proveedores con los que
     * él trabajó (los que tienen al menos un documento suyo).
     *
     * El estado de cuenta en sí sale COMPLETO, con todos los movimientos del
     * tercero: es su saldo real, tiene que cuadrar con Cuentas por Cobrar/Pagar y
     * es lo que se le envía por correo. Recortarlo a "lo que yo registré" daría un
     * saldo que no existe. Como punto único, esto cubre la pantalla, el PDF, el
     * Excel y el envío por correo, que parten todos de construirLedgers().
     *
     * @param  int[] $ids
     * @return int[]
     */
    /**
     * Lo mismo, aplicado a las filas de un buscador (clientes/proveedores): deja
     * solo las fichas con las que el usuario trabajó, conservando el orden.
     *
     * @param  array<int, array<string, mixed>> $filas
     * @return array<int, array<string, mixed>>
     */
    private function soloFichasPropias(int $idEmpresa, string $tipo, array $filas): array
    {
        if (!$filas) {
            return $filas;
        }
        $permitidos = array_flip($this->soloEntidadesPropias(
            $idEmpresa,
            $tipo,
            array_map(static fn ($f) => (int) ($f['id'] ?? 0), $filas)
        ));
        return array_values(array_filter($filas, static fn ($f) => isset($permitidos[(int) ($f['id'] ?? 0)])));
    }

    private function soloEntidadesPropias(int $idEmpresa, string $tipo, array $ids): array
    {
        $perm = $this->getPermisos();
        if (!empty($perm['todo']) || !$ids) {
            return $ids; // ve toda la empresa (incluye nivel 3)
        }
        return $this->repository->filtrarEntidadesConDocumentosDe(
            $idEmpresa,
            $tipo,
            $ids,
            (int) ($_SESSION['id_usuario'] ?? 0)
        );
    }

    /**
     * Deja una sola ficha por tercero real dentro de una selección manual, quedándose con
     * la primera de cada grupo. Evita que el mismo estado de cuenta se pinte dos veces
     * cuando el usuario elige la ficha de la cédula y la del RUC.
     *
     * @param  int[] $ids
     * @return int[]
     */
    private function unaEntidadPorTercero(int $idEmpresa, string $tipo, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (count($ids) < 2) {
            return $ids;
        }
        $out = [];
        $vistos = [];
        $entidades = $this->repository->getEntidadesPorIds($idEmpresa, $tipo, $ids); // una consulta para todas
        foreach ($ids as $id) {
            $ent = $entidades[$id] ?? null;
            $clave = IdentificacionTercero::claveGrupo($ent['identificacion'] ?? null, 'id:' . $id);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = $id;
        }
        return $out;
    }

    /**
     * Una fila por tercero real en el resultado de un buscador, recortada a $limite.
     * Entre la cédula y el RUC gana la primera que llegue en el orden del listado.
     *
     * @param  array<int, array<string, mixed>> $filas
     * @return array<int, array<string, mixed>>
     */
    private function unaFichaPorTercero(array $filas, int $limite): array
    {
        $out = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $clave = IdentificacionTercero::claveGrupo(
                isset($fila['identificacion']) ? (string) $fila['identificacion'] : null,
                'id:' . (int) ($fila['id'] ?? 0)
            );
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = $fila;
            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }

    private function etiquetaEntidad(string $tipo): string
    {
        return $tipo === 'PROVEEDOR' ? 'Proveedor' : 'Cliente';
    }

    // ────────────────────────────────────────────────────────────────
    // PERSONALIZACIÓN POR EMPRESA (botón «Personalizar»)
    // Catálogo: App\Helpers\ColumnasReporte ('modulos/reporte_cartera', vista ESTADO).
    // Pantalla, Excel y PDF se pintan recorriendo las columnas visibles de cada medio.
    // ────────────────────────────────────────────────────────────────
    private const VISTA = 'ESTADO';

    /**
     * Columnas visibles en cada medio ('pantalla', 'excel', 'pdf': clave => definición) y
     * orientación del PDF ('horizontal'), según la personalización de la empresa.
     */
    private function columnasReporte(int $idEmpresa): array
    {
        return \App\Services\modulos\ReportePersonalizacionService::crear()
            ->resolver($idEmpresa, self::RUTA_MODULO, self::VISTA);
    }

    /** Valor de cada columna de un movimiento, por clave del catálogo (importes: float o null). */
    private function valoresMovimiento(array $m): array
    {
        $monto = (float) $m['monto'];
        return [
            'fecha'     => date('d-m-Y', strtotime((string) $m['fecha'])),
            'tipo'      => ucfirst(strtolower(str_replace('_', ' ', (string) $m['origen']))),
            'documento' => (string) ($m['numero_documento'] ?? ''),
            'detalle'   => (string) ($m['detalle'] ?? ''),
            'deuda'     => $m['tipo_movimiento'] === 'CARGO' ? $monto : null,
            'abono'     => $m['tipo_movimiento'] === 'ABONO' ? $monto : null,
            'saldo'     => (float) $m['saldo'],
        ];
    }

    /** Claves de las columnas descriptivas (las que no son importes), en su orden. */
    private static function clavesDescriptivas(array $columnas): array
    {
        return array_keys(array_filter($columnas, static fn (array $c): bool => $c['suma'] === null));
    }

    // ────────────────────────────────────────────────────────────────
    // CONSTRUCCIÓN DE LOS ESTADOS DE CUENTA (uno por entidad seleccionada)
    // ────────────────────────────────────────────────────────────────
    /**
     * Un estado de cuenta por cada tercero seleccionado, todos resueltos POR LOTE: una
     * consulta trae los movimientos y el saldo anterior de todos (antes eran dos consultas
     * pesadas por cada cliente/proveedor, y con "Todos" el reporte tardaba minutos).
     *
     * El movimiento de cada tercero se arma sobre todas sus fichas —el mismo contribuyente
     * puede estar registrado dos veces, una con la cédula y otra con el RUC (esa cédula +
     * '001')—, así que el kardex sale completo en una sola sección en vez de partido en dos
     * con saldos que no cuadran. La cabecera es la de la ficha elegida, que es la que el
     * buscador mostró.
     */
    private function construirLedgers(int $idEmpresa, array $filtros): array
    {
        $ids = $this->resolverIds($idEmpresa, $filtros);
        if (!$ids) {
            return [];
        }
        $tipo      = $filtros['tipo'];
        $entidades = $this->repository->getEntidadesPorIds($idEmpresa, $tipo, $ids);
        $ids       = array_values(array_filter($ids, static fn (int $id): bool => isset($entidades[$id])));
        if (!$ids) {
            return [];
        }
        $grupos = $this->repository->gruposDeEntidades($idEmpresa, $tipo, $ids);
        $fichas = $grupos ? array_merge(...array_values($grupos)) : [];

        $estados = $this->repository->getEstadosCuenta(
            $idEmpresa,
            $tipo,
            $fichas,
            $filtros['fecha_desde'] !== '' ? $filtros['fecha_desde'] : null,
            $filtros['fecha_hasta'] !== '' ? $filtros['fecha_hasta'] : null,
            ($filtros['documento'] ?? '') !== '' ? $filtros['documento'] : null
        );

        $ledgers = [];
        foreach ($ids as $id) {
            $saldoAnterior = 0.0;
            $movimientos   = [];
            $conDatos      = 0;
            foreach ($grupos[$id] ?? [$id] as $ficha) {
                if (!isset($estados[$ficha])) {
                    continue;
                }
                $saldoAnterior += $estados[$ficha]['saldo_anterior'];
                if ($estados[$ficha]['movimientos']) {
                    $movimientos = $movimientos ? array_merge($movimientos, $estados[$ficha]['movimientos']) : $estados[$ficha]['movimientos'];
                    $conDatos++;
                }
            }
            // Dos fichas con movimientos: se intercalan con el orden del kardex
            // (fecha, cargos antes que abonos, id del documento).
            if ($conDatos > 1) {
                usort($movimientos, static fn (array $a, array $b): int
                    => [$a['fecha'], -(int) $a['signo'], (int) $a['id_orden']] <=> [$b['fecha'], -(int) $b['signo'], (int) $b['id_orden']]);
            }
            $ledgers[] = $this->armarLedger($entidades[$id], $saldoAnterior, $movimientos);
        }
        return $ledgers;
    }

    /** Saldo corriente y totales del estado de cuenta de un tercero. */
    private function armarLedger(array $entidad, float $saldoAnterior, array $movimientos): array
    {
        $saldo = $saldoAnterior;
        $totalCargos = 0.0;
        $totalAbonos = 0.0;
        foreach ($movimientos as &$m) {
            $monto = (float) $m['monto'];
            if ($m['tipo_movimiento'] === 'CARGO') {
                $saldo += $monto;
                $totalCargos += $monto;
            } else {
                $saldo -= $monto;
                $totalAbonos += $monto;
            }
            $m['saldo'] = $saldo;
        }
        unset($m);

        return [
            'entidad'        => $entidad,
            'saldo_anterior' => $saldoAnterior,
            'movimientos'    => $movimientos,
            'total_cargos'   => $totalCargos,
            'total_abonos'   => $totalAbonos,
            'saldo_final'    => $saldo,
        ];
    }

    private function calcularEstadisticas(array $ledgers): array
    {
        $totalCargos = 0.0;
        $totalAbonos = 0.0;
        $totalSaldo  = 0.0;
        foreach ($ledgers as $l) {
            $totalCargos += $l['total_cargos'];
            $totalAbonos += $l['total_abonos'];
            $totalSaldo  += $l['saldo_final'];
        }

        return [
            'entidades'    => count($ledgers),
            'total_cargos' => $totalCargos,
            'total_abonos' => $totalAbonos,
            'saldo_total'  => $totalSaldo,
        ];
    }

    private function formatearRango(array $filtros): string
    {
        $d = $filtros['fecha_desde'];
        $h = $filtros['fecha_hasta'];
        if ($d !== '' && $h !== '') return date('d-m-Y', strtotime($d)) . ' al ' . date('d-m-Y', strtotime($h));
        if ($d !== '') return 'Desde ' . date('d-m-Y', strtotime($d));
        if ($h !== '') return 'Hasta ' . date('d-m-Y', strtotime($h));
        return 'Todas las fechas';
    }

    /**
     * Documentos (cargos) de las entidades seleccionadas, para el buscador
     * del filtro "Documento". Sin entidad seleccionada y sin "Todos" no hay
     * nada que listar: el documento se busca en base al cliente/proveedor.
     */
    public function getDocumentosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros   = $this->getFiltrosDesdeRequest();
            $q         = mb_substr(trim((string) ($_REQUEST['q'] ?? '')), 0, 60);

            if (empty($filtros['ids']) && !$filtros['todos']) {
                echo json_encode(['ok' => true, 'data' => [], 'mensaje' => 'Seleccione primero un ' . $this->etiquetaEntidad($filtros['tipo']) . '.']);
                exit;
            }

            // Sobre todas las fichas del tercero: el documento buscado puede estar
            // registrado bajo la cédula aunque el usuario haya elegido el RUC.
            $idsDoc = $filtros['todos']
                ? []
                : $this->repository->expandirEntidades($idEmpresa, $filtros['tipo'], $filtros['ids']);
            $rows = $this->repository->getDocumentosEntidad($idEmpresa, $filtros['tipo'], $idsDoc, $q, 20);
            $data = array_map(fn($r) => [
                'numero'  => (string) $r['numero'],
                'origen'  => ucfirst(strtolower(str_replace('_', ' ', (string) $r['origen']))),
                'fecha'   => !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : '',
                'total'   => number_format((float) $r['total'], 2),
                'entidad' => (string) ($r['nombre_entidad'] ?? ''),
            ], $rows);

            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // GENERAR (AJAX)
    // ────────────────────────────────────────────────────────────────
    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            if (empty($filtros['ids']) && !$filtros['todos']) {
                echo json_encode([
                    'ok'    => true,
                    'html'  => '<div class="text-center text-muted py-5"><i class="bi bi-funnel fs-3 d-block mb-2"></i>Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ', o marque "Todos" para generar el estado de cuenta.</div>',
                    'stats' => ['entidades' => 0, 'total_cargos' => 0, 'total_abonos' => 0, 'saldo_total' => 0],
                ]);
                exit;
            }

            $ledgers = $this->construirLedgers($idEmpresa, $filtros);
            $stats   = $this->calcularEstadisticas($ledgers);

            ob_start();
            if (empty($ledgers)) {
                $mensaje = $filtros['todos']
                    ? 'No hay ' . strtolower($this->etiquetaEntidad($filtros['tipo'])) . 's con saldo pendiente a la fecha de corte seleccionada.'
                    : 'No se encontraron datos para la selección actual.';
                echo '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>' . htmlspecialchars($mensaje) . '</div>';
            } else {
                $columnas = $this->columnasReporte($idEmpresa)['pantalla'];
                foreach ($ledgers as $ledger) {
                    echo $this->renderSeccionScreen($ledger, $filtros['tipo'], $filtros['documento'] ?? '', $columnas);
                }
            }
            $html = (string) ob_get_clean();

            echo json_encode(['ok' => true, 'html' => $html, 'stats' => $stats]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Tarjeta del estado de cuenta de una entidad. `$columnas` son las visibles en pantalla
     * (clave => definición del catálogo): se pinta una celda por clave.
     */
    private function renderSeccionScreen(array $ledger, string $tipo, string $documento, array $columnas): string
    {
        $e = $ledger['entidad'];
        $etiqueta = $this->etiquetaEntidad($tipo);
        $deudor = $ledger['saldo_final'] > 0.005;
        $idEntidad = (int) ($e['id'] ?? 0);
        $emailEntidad = (string) ($e['email'] ?? '');

        $nCols    = count($columnas);
        $conSaldo = isset($columnas['saldo']);
        // La etiqueta TOTALES ocupa las columnas descriptivas; los importes van al final.
        $nLider   = count(self::clavesDescriptivas($columnas));
        // Clases de las celdas de cada columna (las mismas de siempre).
        $clases = [
            'fecha' => 'small text-nowrap', 'tipo' => 'small', 'documento' => 'small', 'detalle' => 'small',
            'deuda' => 'text-end small text-danger', 'abono' => 'text-end small text-success', 'saldo' => 'text-end small fw-bold',
        ];

        ob_start();
        ?>
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="fw-bold"><?= htmlspecialchars($e['nombre'] ?? '') ?></span>
                    <small class="text-muted d-block">
                        <?= htmlspecialchars($etiqueta) ?> · <?= htmlspecialchars($e['identificacion'] ?? '') ?>
                        <?= $emailEntidad !== '' ? ' · ' . htmlspecialchars($emailEntidad) : '' ?>
                        <?= $documento !== '' ? ' · <span class="badge bg-info bg-opacity-10 text-info border border-info">Documento: ' . htmlspecialchars($documento) . '</span>' : '' ?>
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge <?= $deudor ? 'bg-danger' : 'bg-success' ?> bg-opacity-10 <?= $deudor ? 'text-danger' : 'text-success' ?> border <?= $deudor ? 'border-danger' : 'border-success' ?>">
                        Saldo: $<?= number_format($ledger['saldo_final'], 2) ?>
                    </span>
                    <!-- Acciones del estado de cuenta de ESTA entidad, con el diseño de los demás reportes -->
                    <div class="btn-group btn-group-sm" role="group" aria-label="Acciones">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RC_exportarPDF(<?= $idEntidad ?>)" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RC_exportarExcel(<?= $idEntidad ?>)" title="Descargar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="window.RC_abrirModalCorreo(<?= $idEntidad ?>, <?= htmlspecialchars(json_encode($emailEntidad), ENT_QUOTES) ?>)" title="Enviar por correo">
                            <i class="bi bi-envelope"></i> Correo
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="text-secondary small">
                                <?php foreach ($columnas as $col): ?>
                                    <th<?= $col['alinear'] === 'der' ? ' class="text-end"' : '' ?>><?= htmlspecialchars($col['titulo']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-light">
                                <?php if ($conSaldo): ?>
                                    <td colspan="<?= $nCols - 1 ?>" class="fw-bold small">Saldo Anterior</td>
                                    <td class="text-end fw-bold small"><?= number_format($ledger['saldo_anterior'], 2) ?></td>
                                <?php else: // sin la columna Saldo, el saldo anterior va junto a su etiqueta ?>
                                    <td colspan="<?= $nCols ?>" class="fw-bold small">Saldo Anterior: <?= number_format($ledger['saldo_anterior'], 2) ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php if (empty($ledger['movimientos'])): ?>
                                <tr><td colspan="<?= $nCols ?>" class="text-center text-muted py-3">Sin movimientos en el período seleccionado.</td></tr>
                            <?php else: foreach ($ledger['movimientos'] as $m): $v = $this->valoresMovimiento($m); ?>
                                <tr>
                                    <?php foreach ($columnas as $clave => $col): ?>
                                        <td class="<?= $clases[$clave] ?? 'small' ?>"><?= $col['suma'] !== null
                                            ? ($v[$clave] !== null ? number_format($v[$clave], 2) : '')
                                            : htmlspecialchars((string) $v[$clave]) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <?php if ($nLider < $nCols): // sin importes visibles no hay fila de totales ?>
                        <tfoot>
                            <tr class="table-secondary">
                                <th colspan="<?= $nLider ?>" class="text-end small">TOTALES:</th>
                                <?php foreach ($columnas as $clave => $col): if ($col['suma'] === null) continue; ?>
                                    <th class="<?= $clave === 'saldo' ? 'text-end small' : $clases[$clave] ?>"><?= $clave === 'saldo' ? '$' : '' ?><?= number_format((float) $ledger[$col['suma']], 2) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    // ────────────────────────────────────────────────────────────────
    // AUTOCOMPLETAR
    // ────────────────────────────────────────────────────────────────
    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        // Se pide holgado y se colapsa después: las dos fichas del mismo contribuyente
        // (cédula y RUC) deben ocupar una sola entrada del dropdown.
        $repo = new ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 30, 'nombre', 'ASC');

        // Registros propios (§6): sin acceso total solo se ofrecen los clientes
        // con los que el usuario trabajó (ver soloEntidadesPropias()).
        $filas = $this->soloFichasPropias($idEmpresa, 'CLIENTE', $result['rows'] ?? []);

        $data = array_map(fn($row) => [
            'id'             => $row['id'],
            'nombre'         => $row['nombre'] ?? '',
            'identificacion' => $row['identificacion'] ?? '',
        ], $this->unaFichaPorTercero($filas, 15));

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        // Se pide holgado y se colapsa después: las dos fichas del mismo contribuyente
        // (cédula y RUC) deben ocupar una sola entrada del dropdown.
        $repo = new ProveedorRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 30, 'razon_social', 'ASC');

        // Registros propios (§6): sin acceso total solo se ofrecen los proveedores
        // con los que el usuario trabajó (ver soloEntidadesPropias()).
        $filas = $this->soloFichasPropias($idEmpresa, 'PROVEEDOR', $result['rows'] ?? []);

        $data = array_map(fn($row) => [
            'id'             => $row['id'],
            'nombre'         => $row['razon_social'] ?? '',
            'identificacion' => $row['identificacion'] ?? '',
        ], $this->unaFichaPorTercero($filas, 15));

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // PDF / EXCEL / CORREO
    // ────────────────────────────────────────────────────────────────
    /**
     * Arma el PDF (descarga y correo) con el formato común de los reportes
     * (App\Helpers\ReportePdf: encabezado con el logo, caja de datos, banda de indicadores,
     * listado y totales): un estado de cuenta por cliente/proveedor, cada uno en hoja nueva,
     * escrito por bloques (App\Helpers\PdfPorBloques): una fila por writeHTML, con la
     * cabecera de columnas repetida en cada hoja. En un solo writeHTML el tiempo crece al
     * cuadrado de los movimientos, y "Todos" con muchos clientes tardaba minutos.
     *
     * Columnas y orientación: las que la empresa dejó para el PDF (botón «Personalizar»).
     */
    private function generarPdf(int $idEmpresa, array $ledgers, string $nombreEmpresa, array $filtros): \Spipu\Html2Pdf\Html2Pdf
    {
        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $cfg        = $this->columnasReporte($idEmpresa);
        $columnas   = $cfg['pdf'];
        $horizontal = (bool) $cfg['horizontal'];
        $html2pdf   = new \Spipu\Html2Pdf\Html2Pdf($horizontal ? 'L' : 'P', 'A4', 'es');

        $etiqueta = $this->etiquetaEntidad($filtros['tipo']);
        $rango    = $this->formatearRango($filtros);
        $h        = static fn ($v): string => htmlspecialchars((string) $v);

        // Anchos por columna (suman 100%), repetidos en el <th> y en cada <td>: sin ellos
        // Html2Pdf ensancha la columna hasta que su texto quepa en una línea y la tabla se
        // sale de la hoja. El ancho de las columnas ocultas se reparte entre las visibles.
        $w  = \App\Helpers\ColumnasReporte::anchosPdf(array_map(static fn (array $c): float => (float) $c['ancho'], $columnas));
        $pt = \App\Helpers\ColumnasReporte::fuentePdf(count($columnas), $horizontal);
        // Html2Pdf no parte una palabra sin espacios más ancha que su columna (un número de
        // documento largo): esa se corta a mano por su ancho real.
        $txt = static fn (string $t, string $clave): string
            => \App\Helpers\ReportePdf::texto($t, \App\Helpers\ReportePdf::anchoPt($w[$clave], $horizontal), $pt);

        $thead = '<tr>';
        foreach ($columnas as $clave => $col) {
            $thead .= "<th style='width:{$w[$clave]}%;'>" . $h($col['corto']) . '</th>';
        }
        $thead .= '</tr>';

        $conSaldo = isset($columnas['saldo']);
        $wLider   = 0.0;
        foreach (self::clavesDescriptivas($columnas) as $clave) {
            $wLider += $w[$clave];
        }
        $wLider = round($wLider, 2);

        // El encabezado (logo incluido) es el mismo en todas las hojas: se arma una vez y
        // solo cambia el nombre del cliente/proveedor.
        $marca      = '@@TERCERO@@';
        $encabezado = \App\Helpers\ReportePdf::encabezado($idEmpresa, $nombreEmpresa, 'Estado de Cuenta - ' . $etiqueta, $marca);
        $css        = ' .saldo-row td { background: #f4f7fa; font-weight: bold; }';

        // Una <page> por cliente/proveedor: cada estado de cuenta empieza en hoja nueva. Los
        // estilos van solo en el primero; siguen vigentes en los writeHTML siguientes.
        foreach (array_values($ledgers) as $i => $ledger) {
            $e      = $ledger['entidad'];
            $deudor = $ledger['saldo_final'] > 0.005;

            $datos = ['Identificación' => (string) ($e['identificacion'] ?? ''), 'Período' => $rango];
            if (($filtros['documento'] ?? '') !== '') {
                $datos['Documento'] = (string) $filtros['documento'];
            }
            $cabecera = str_replace($marca, $h($e['nombre'] ?? ''), $encabezado)
                . \App\Helpers\ReportePdf::filtros($datos)
                . \App\Helpers\ReportePdf::indicadores([
                    ['Saldo Anterior', '$' . number_format($ledger['saldo_anterior'], 2)],
                    ['Deuda Generada', '$' . number_format($ledger['total_cargos'], 2)],
                    ['Abonos', '$' . number_format($ledger['total_abonos'], 2)],
                    ['Saldo', '$' . number_format($ledger['saldo_final'], 2), false, $deudor ? '#b02a37' : '#146c43'],
                ]);
            $inicio = \App\Helpers\ReportePdf::inicio($pt, $cabecera, $css);
            if ($i > 0) {
                $inicio = substr($inicio, (int) strpos($inicio, '<page'));
            }

            // Saldo anterior: en la columna Saldo; si la empresa la ocultó, junto a su etiqueta.
            $saldoAnt = number_format($ledger['saldo_anterior'], 2);
            $filas = [$conSaldo
                ? "<tr class='saldo-row'><td class='text-end' style='width:" . round(100 - $w['saldo'], 2) . "%;'>Saldo Anterior</td>"
                    . "<td class='text-end' style='width:{$w['saldo']}%;'>{$saldoAnt}</td></tr>"
                : "<tr class='saldo-row'><td class='text-end' style='width:100%;'>Saldo Anterior: {$saldoAnt}</td></tr>"];

            $n = 0;
            foreach ($ledger['movimientos'] as $m) {
                $v     = $this->valoresMovimiento($m);
                $zebra = (++$n % 2 === 0) ? 'background:#f6f8fa;' : '';
                $tds   = '';
                foreach ($columnas as $clave => $col) {
                    if ($col['suma'] !== null) {
                        $valor = $v[$clave] !== null ? number_format($v[$clave], 2) : '';
                    } elseif ($clave === 'documento' || $clave === 'detalle') {
                        $valor = $txt((string) $v[$clave], $clave);
                    } else {
                        $valor = $h($v[$clave]);
                    }
                    $cls  = \App\Helpers\ColumnasReporte::claseAlinear($col['alinear']);
                    $tds .= '<td' . ($cls !== '' ? " class='{$cls}'" : '') . " style='width:{$w[$clave]}%;{$zebra}'>{$valor}</td>";
                }
                $filas[] = "<tr>{$tds}</tr>";
            }

            // Totales en tabla aparte, al final; sin importes visibles no hay fila de totales.
            $celdas = '';
            foreach ($columnas as $clave => $col) {
                if ($col['suma'] !== null) {
                    $celdas .= "<td class='text-end' style='width:{$w[$clave]}%;'>" . ($clave === 'saldo' ? '$' : '')
                             . number_format((float) $ledger[$col['suma']], 2) . '</td>';
                }
            }
            $final = $celdas !== ''
                ? "<table class='tot'><tr><td class='text-end' style='width:{$wLider}%;'>TOTALES:</td>{$celdas}</tr></table>"
                : '';

            \App\Helpers\PdfPorBloques::escribir($html2pdf, $inicio, $thead, $filas, $final);
        }

        return $html2pdf;
    }

    /**
     * Columnas del Excel según la personalización de la empresa.
     *
     * @return array{0: array, 1: array} [headers, secciones] para ReportService::exportToExcelSeccionado()
     */
    private function datosExportExcel(int $idEmpresa, array $ledgers): array
    {
        $columnas = $this->columnasReporte($idEmpresa)['excel'];
        $headers  = [];
        foreach ($columnas as $col) {
            foreach ($col['excel'] as $rotulo) {
                $headers[] = $rotulo;
            }
        }

        // "Saldo Anterior" va en la columna Tipo (o en la primera descriptiva si se ocultó) y
        // "TOTALES" en la última descriptiva, justo antes de los importes.
        $descriptivas = self::clavesDescriptivas($columnas);
        $colSaldoAnt  = isset($columnas['tipo']) ? 'tipo' : $descriptivas[0];
        $colTotales   = $descriptivas[count($descriptivas) - 1];
        $conSaldo     = isset($columnas['saldo']);
        $conImportes  = count($descriptivas) < count($columnas);

        $secciones = [];
        foreach ($ledgers as $ledger) {
            $e = $ledger['entidad'];

            $saldoAnt = round($ledger['saldo_anterior'], 2);
            $fila = [];
            foreach ($columnas as $clave => $col) {
                $fila[] = match (true) {
                    $clave === $colSaldoAnt => $conSaldo ? 'Saldo Anterior' : 'Saldo Anterior: ' . number_format($saldoAnt, 2),
                    $clave === 'saldo'      => $saldoAnt,
                    default                 => '',
                };
            }
            $filas = [$fila];

            foreach ($ledger['movimientos'] as $m) {
                $v    = $this->valoresMovimiento($m);
                $fila = [];
                foreach ($columnas as $clave => $col) {
                    $fila[] = $col['suma'] !== null
                        ? ($v[$clave] !== null ? round($v[$clave], 2) : '')
                        : $v[$clave];
                }
                $filas[] = $fila;
            }

            $seccion = [
                'titulo' => trim(($e['nombre'] ?? '') . ' - ' . ($e['identificacion'] ?? '')),
                'filas'  => $filas,
            ];
            if ($conImportes) {
                $resumen = [];
                foreach ($columnas as $clave => $col) {
                    $resumen[] = match (true) {
                        $col['suma'] !== null  => round((float) $ledger[$col['suma']], 2),
                        $clave === $colTotales => 'TOTALES',
                        default                => '',
                    };
                }
                $seccion['resumen'] = $resumen;
            }
            $secciones[] = $seccion;
        }

        return [$headers, $secciones];
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        if (empty($filtros['ids']) && !$filtros['todos']) {
            $this->errorExport('Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ', o marque "Todos", para exportar.');
        }

        try {
            $ledgers = $this->construirLedgers($idEmpresa, $filtros);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->errorExport('Error al generar Excel: ' . $e->getMessage());
        }

        if (empty($ledgers)) {
            $this->errorExport($filtros['todos']
                ? 'No hay ' . strtolower($this->etiquetaEntidad($filtros['tipo'])) . 's con saldo pendiente a la fecha de corte seleccionada.'
                : 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ' para exportar.');
        }

        try {
            $empresa = (new Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            [$headers, $secciones] = $this->datosExportExcel($idEmpresa, $ledgers);

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcelSeccionado('Cartera', $headers, $secciones, 'Reporte de Cartera', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->errorExport('Error al generar Excel: ' . $e->getMessage());
        }
    }

    /**
     * Termina una exportación con un error. A una petición AJAX (CMG_pdfDocumento /
     * CMG_descargar envían X-Requested-With) le responde JSON {ok, error}, que es lo que
     * esas funciones muestran al usuario; abierta directo en el navegador, texto plano.
     */
    private function errorExport(string $mensaje): never
    {
        if (ob_get_length()) {
            ob_clean();
        }
        if ($this->esAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo $mensaje;
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        if (empty($filtros['ids']) && !$filtros['todos']) {
            $this->errorExport('Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ', o marque "Todos", para exportar.');
        }

        try {
            $ledgers = $this->construirLedgers($idEmpresa, $filtros);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->errorExport('Error al generar PDF: ' . $e->getMessage());
        }

        if (empty($ledgers)) {
            $this->errorExport($filtros['todos']
                ? 'No hay ' . strtolower($this->etiquetaEntidad($filtros['tipo'])) . 's con saldo pendiente a la fecha de corte seleccionada.'
                : 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ' para exportar.');
        }

        try {
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE CARTERA';

            $html2pdf = $this->generarPdf($idEmpresa, $ledgers, $nombreEmpresa, $filtros);
            $html2pdf->output('ReporteCartera_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->errorExport('Error al generar PDF: ' . $e->getMessage());
        }
    }

    /** Envía el estado de cuenta (con los filtros actuales) por correo, adjuntando PDF y/o Excel. */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        // Soltar el candado de la sesión antes de armar el reporte y enviarlo por correo: si no, las demás
        // peticiones del usuario hacen fila hasta que termine.
        session_write_close();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            $correosRaw = trim($_POST['correos'] ?? '');
            $destinatarios = array_values(array_filter(
                array_map('trim', explode(',', $correosRaw)),
                fn($c) => filter_var($c, FILTER_VALIDATE_EMAIL) !== false
            ));

            if (empty($destinatarios)) {
                echo json_encode(['ok' => false, 'mensaje' => 'Ingrese al menos un correo válido.']);
                exit;
            }

            if (empty($filtros['ids']) && !$filtros['todos']) {
                echo json_encode(['ok' => false, 'mensaje' => 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ', o marque "Todos", antes de enviar.']);
                exit;
            }

            $ledgers = $this->construirLedgers($idEmpresa, $filtros);
            if (empty($ledgers)) {
                $mensaje = $filtros['todos']
                    ? 'No hay ' . strtolower($this->etiquetaEntidad($filtros['tipo'])) . 's con saldo pendiente a la fecha de corte seleccionada.'
                    : 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ' antes de enviar.';
                echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
                exit;
            }

            $adjuntar = $_POST['adjuntar'] ?? 'pdf'; // pdf | excel | ambos

            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Reporte de Cartera';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $adjuntosPaths = [];
            $tmpBase = sys_get_temp_dir() . '/rc_' . $idEmpresa . '_' . time();

            if ($adjuntar === 'pdf' || $adjuntar === 'ambos') {
                $pdfPath = $tmpBase . '.pdf';
                $html2pdf = $this->generarPdf($idEmpresa, $ledgers, $nombreEmpresa, $filtros);
                $html2pdf->output($pdfPath, 'F');
                $adjuntosPaths[$pdfPath] = 'ReporteCartera_' . date('Ymd') . '.pdf';
            }

            if ($adjuntar === 'excel' || $adjuntar === 'ambos') {
                [$headers, $secciones] = $this->datosExportExcel($idEmpresa, $ledgers);
                $excelPath = $tmpBase . '.xlsx';
                (new \App\Services\ReportService())->guardarExcelSeccionadoEnArchivo($headers, $secciones, 'Reporte de Cartera', $nombreEmpresa, $excelPath);
                $adjuntosPaths[$excelPath] = 'ReporteCartera_' . date('Ymd') . '.xlsx';
            }

            $stats = $this->calcularEstadisticas($ledgers);
            $etiqueta = $this->etiquetaEntidad($filtros['tipo']);

            $asunto = 'Estado de Cuenta - ' . $nombreEmpresa;
            $cuerpo = '
                <div style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:auto;">
                    <h2 style="color:#2563eb;">Reporte de Cartera</h2>
                    <p>Adjunto el estado de cuenta de <strong>' . htmlspecialchars($nombreEmpresa) . '</strong>.</p>'
                    . (($filtros['documento'] ?? '') !== '' ? '<p>Documento: <strong>' . htmlspecialchars($filtros['documento']) . '</strong></p>' : '') . '
                    <table style="border-collapse:collapse;font-size:14px;">
                        <tr><td style="padding:4px 12px;color:#666;">' . htmlspecialchars($etiqueta) . 's incluidos</td><td style="padding:4px 12px;"><strong>' . (int) $stats['entidades'] . '</strong></td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Total deuda generada</td><td style="padding:4px 12px;">$ ' . number_format($stats['total_cargos'], 2) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Total abonos</td><td style="padding:4px 12px;">$ ' . number_format($stats['total_abonos'], 2) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Saldo final</td><td style="padding:4px 12px;"><strong>$ ' . number_format($stats['saldo_total'], 2) . '</strong></td></tr>
                    </table>
                    <p style="color:#888;font-size:12px;margin-top:24px;">Reporte generado el ' . date('d-m-Y H:i:s') . '.</p>
                </div>';

            $ok = enviar_correo_reporte($destinatarios, $asunto, $cuerpo, $adjuntosPaths);

            foreach (array_keys($adjuntosPaths) as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            echo json_encode($ok
                ? ['ok' => true, 'mensaje' => 'Reporte enviado a ' . implode(', ', $destinatarios)]
                : ['ok' => false, 'mensaje' => $GLOBALS['LAST_EMAIL_ERROR'] ?? 'No se pudo enviar el correo. Verifica la configuración de correo.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }
}
