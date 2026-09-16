<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\repositories\modulos\EntregasConsignacionesRepository;
use App\Services\modulos\EntregasConsignacionesService;

/**
 * Entregas de Consignaciones en Ventas. Lista las consignaciones PENDIENTES de entregar
 * (por defecto) y, con el filtro de estado, las ya entregadas con su evidencia (GPS +
 * firma) registrada desde la app móvil o manualmente desde el sistema. Única escritura:
 * marcarEntregadaAjax() (permiso Actualizar), que marca una pendiente como Entregada
 * reutilizando el mismo flujo de modulos/consignaciones-ventas. No crea ni elimina nada.
 */
class EntregasConsignacionesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/entregas-consignaciones';

    private const ORDEN_COL_DEFECTO = 'fecha_emision';
    private const ORDEN_DIR_DEFECTO = 'DESC';

    /** Nº de columnas de la tabla (colspan de las filas de aviso/carga). */
    private const NUM_COLUMNAS = 15;

    private EntregasConsignacionesService $service;

    /** Cache por petición: ¿el usuario puede marcar entregas (permiso Actualizar del módulo)? */
    private ?bool $puedeMarcar = null;

    public function __construct()
    {
        parent::__construct();
        $this->service = new EntregasConsignacionesService(new EntregasConsignacionesRepository());
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /**
     * Ids de responsables_traslado a los que restringir el listado (null = todas).
     * Manda el vínculo usuario <-> responsable de config/usuarios-sistema: nivel 3
     * y usuarios sin vínculo ven todo; usuarios vinculados, solo lo suyo.
     */
    private function filtroResponsablesActual(): ?array
    {
        return $this->service->resolverFiltroResponsables(
            (int) $_SESSION['id_usuario'],
            (int) $_SESSION['id_empresa'],
            (int) ($_SESSION['nivel'] ?? 1)
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar  = trim($_GET['b'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $orden   = OrdenListado::leer($prefsVista, self::ORDEN_COL_DEFECTO, self::ORDEN_DIR_DEFECTO);
        $perPage = 20;

        $idsResponsables = $this->filtroResponsablesActual();

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $idsResponsables);
        $rows       = $this->prepararFilas($result['rows']);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $resumen = $this->service->getResumen($idEmpresa, $buscar, $idsResponsables);

        // Las filas se renderizan aquí (mismo HTML que searchAjax) para no duplicar el markup en la vista.
        $rowsHtml = '';
        if (empty($rows)) {
            $rowsHtml = $this->filaVacia($result['estado']);
        } else {
            foreach ($rows as $r) {
                $rowsHtml .= $this->filaHtml($r);
            }
        }

        $anioActual = (int) date('Y');

        $this->viewWithLayout('layouts.main', 'modulos.entregas_consignaciones.index', [
            'titulo'        => 'Entregas de Consignaciones',
            'perm'          => $perm,
            'rutaModulo'    => self::RUTA_MODULO,
            'rowsHtml'      => $rowsHtml,
            'total'         => $total,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'perPage'       => $perPage,
            'buscar'        => $buscar,
            'estadoEntrega' => $result['estado'],
            'puedeMarcar'   => $this->puedeMarcar(),
            'ordenCol'      => OrdenListado::primeraCol($orden, self::ORDEN_COL_DEFECTO),
            'ordenDir'      => OrdenListado::primeraDir($orden, self::ORDEN_DIR_DEFECTO),
            'vistaConfig'   => $prefsVista,
            'resumen'       => $resumen,
            'anioDesde'     => $anioActual - 5,
            'anioHasta'     => $anioActual,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? $_GET['q'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $orden      = OrdenListado::leer($prefsVista, self::ORDEN_COL_DEFECTO, self::ORDEN_DIR_DEFECTO);
        $perPage    = 20;

        $idsResponsables = $this->filtroResponsablesActual();

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $idsResponsables);
        $rows       = $this->prepararFilas($result['rows']);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo $this->filaVacia($result['estado']);
        } else {
            foreach ($rows as $r) {
                echo $this->filaHtml($r);
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="entcCambiarPagina(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="entcCambiarPagina(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        $resumen = $this->service->getResumen($idEmpresa, $buscar, $idsResponsables);

        $qsOrden = '&orden=' . urlencode(OrdenListado::aCadena($orden));

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'estado'     => $result['estado'],
            'resumen'    => $resumen,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/exportPdf?b=' . urlencode($buscar) . $qsOrden,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/exportExcel?b=' . urlencode($buscar) . $qsOrden,
        ]);
        exit;
    }

    /**
     * Marca una consignación pendiente como entregada (única acción de escritura del
     * módulo). Requiere el permiso Actualizar del submódulo. Recibe por POST: id de la
     * consignación, latitud/longitud/precision_m capturadas por el navegador (opcionales)
     * y una observación opcional. El alcance por responsable y el estado los valida el
     * service; la evidencia y la auditoría las crea ConsignacionVentaService::cambiarEstado().
     */
    public function marcarEntregadaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new \Exception('ID no válido.');
            }

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $datosEntrega = [
                'latitud'       => $_POST['latitud']       ?? null,
                'longitud'      => $_POST['longitud']      ?? null,
                'precision_m'   => $_POST['precision_m']   ?? null,
                'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            $this->service->marcarEntregada($id, $idEmpresa, $idUsuario, $this->filtroResponsablesActual(), $datosEntrega);
            echo json_encode(['ok' => true, 'msg' => 'Entrega registrada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** ¿Puede el usuario marcar entregas? Permiso Actualizar del submódulo (nivel 3 siempre). */
    private function puedeMarcar(): bool
    {
        if ($this->puedeMarcar === null) {
            $this->puedeMarcar = !empty($this->getPermisos()['actualizar']);
        }
        return $this->puedeMarcar;
    }

    /** Botón "Marcar entregada" de la fila: solo en pendientes y con permiso. */
    private function celdaAcciones(array $r): string
    {
        if (empty($r['pendiente']) || !$this->puedeMarcar()) {
            return '';
        }
        $id     = (int) ($r['id_consignacion'] ?? 0);
        $numero = htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? ''), ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="btn btn-sm btn-outline-success py-0 px-2 text-nowrap"
                        title="Marcar como entregada"
                        onclick="event.stopPropagation(); entcMarcarEntregada(' . $id . ', \'' . $numero . '\')">
                    <i class="bi bi-check2-circle me-1"></i>Entregar
                </button>';
    }

    /** Fila de "sin resultados" acorde al estado de entrega filtrado. */
    private function filaVacia(string $estado): string
    {
        $msg = match ($estado) {
            EntregasConsignacionesRepository::ESTADO_ENTREGADA => 'No se encontraron consignaciones entregadas.',
            EntregasConsignacionesRepository::ESTADO_TODAS     => 'No se encontraron consignaciones.',
            default                                            => 'No hay consignaciones pendientes de entregar.',
        };
        $icono = $estado === EntregasConsignacionesRepository::ESTADO_PENDIENTE ? 'bi-check2-circle' : 'bi-geo-alt';
        return '<tr><td colspan="' . self::NUM_COLUMNAS . '" class="text-center py-5 text-muted"><i class="bi ' . $icono . ' fs-3 d-block mb-2"></i>' . $msg . '</td></tr>';
    }

    /** Normaliza fechas y agrega firma_url/indicadores calculados a cada fila. */
    private function prepararFilas(array $rows): array
    {
        $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
        foreach ($rows as &$r) {
            $r['pendiente'] = (($r['estado_consignacion'] ?? '') === 'Emitida');

            if (!empty($r['fecha_emision'])) {
                $r['fecha_emision_fmt'] = date('d-m-Y', strtotime($r['fecha_emision']));
            }
            if (!empty($r['fecha_entrega'])) {
                $r['fecha_entrega_fmt'] = date('d-m-Y', strtotime($r['fecha_entrega']));
                $desde = !empty($r['hora_entrega_desde']) ? substr($r['hora_entrega_desde'], 0, 5) : '';
                $hasta = !empty($r['hora_entrega_hasta']) ? substr($r['hora_entrega_hasta'], 0, 5) : '';
                if ($desde !== '' || $hasta !== '') {
                    $r['fecha_entrega_fmt'] .= ' ' . trim($desde . ($hasta !== '' ? ' - ' . $hasta : ''));
                }
            }
            if (!empty($r['capturado_en'])) {
                $r['capturado_en_fmt'] = date('d-m-Y H:i:s', strtotime($r['capturado_en']));
            }
            $r['dias_espera'] = isset($r['dias_espera']) ? (int) $r['dias_espera'] : null;
            $r['tiene_gps']   = ($r['latitud'] !== null && $r['longitud'] !== null);
            $r['tiene_firma'] = !empty($r['firma_path']);
            $r['firma_url']   = !empty($r['firma_path']) && !empty($r['id'])
                ? $base . '/' . self::RUTA_MODULO . '/firmaEntrega?id=' . (int) $r['id']
                : null;
        }
        unset($r);
        return $rows;
    }

    private function badgeEstado(string $estado): string
    {
        return match ($estado) {
            'Emitida'   => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><i class="bi bi-hourglass-split me-1"></i>Pendiente</span>',
            'Entregada' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check2-circle me-1"></i>Entregada</span>',
            'Facturada' => '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-receipt me-1"></i>Facturada</span>',
            default     => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">' . htmlspecialchars($estado) . '</span>',
        };
    }

    private function badgeCanal(?string $canal): string
    {
        if ($canal === null || $canal === '') {
            return '<span class="text-muted">—</span>';
        }
        return $canal === 'web'
            ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-display me-1"></i>Web</span>'
            : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-phone me-1"></i>App móvil</span>';
    }

    private function iconoSiNo(bool $si, bool $aplica = true): string
    {
        if (!$aplica) {
            return '<span class="text-muted">—</span>';
        }
        return $si
            ? '<i class="bi bi-check-circle-fill text-success" title="Sí"></i>'
            : '<i class="bi bi-dash-circle text-muted" title="No"></i>';
    }

    /** Días en espera: en pendientes resalta las que llevan más tiempo sin entregar. */
    private function celdaDias(array $r): string
    {
        $dias = $r['dias_espera'];
        if ($dias === null) {
            return '—';
        }
        $clase = '';
        if (!empty($r['pendiente'])) {
            $clase = $dias >= 7 ? 'text-danger fw-bold' : ($dias >= 3 ? 'text-warning fw-semibold' : '');
        }
        return '<span class="' . $clase . '">' . $dias . '</span>';
    }

    private function filaHtml(array $r): string
    {
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $numero   = htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? ''));
        $tieneEnt = !empty($r['id']);

        return '<tr class="entc-row" role="button" tabindex="0" data-row=\'' . $dataJson . '\' onclick="entcAbrirDetalle(this)">
                    <td class="ps-3" data-col="fecha_emision">' . htmlspecialchars($r['fecha_emision_fmt'] ?? '—') . '</td>
                    <td data-col="secuencial" class="fw-bold text-primary">' . $numero . '</td>
                    <td data-col="cliente" class="text-truncate" style="max-width:220px" title="' . htmlspecialchars($r['cliente_nombre'] ?? '') . '">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                    <td data-col="direccion" class="text-truncate" style="max-width:220px" title="' . htmlspecialchars($r['cliente_direccion'] ?? '') . '">' . htmlspecialchars($r['cliente_direccion'] ?? '—') . '</td>
                    <td data-col="responsable" class="text-truncate" style="max-width:160px">' . htmlspecialchars($r['responsable_traslado_nombre'] ?? '—') . '</td>
                    <td data-col="fecha_entrega" class="text-nowrap">' . htmlspecialchars($r['fecha_entrega_fmt'] ?? '—') . '</td>
                    <td data-col="estado" class="text-center">' . $this->badgeEstado((string) ($r['estado_consignacion'] ?? '')) . '</td>
                    <td data-col="dias" class="text-center">' . $this->celdaDias($r) . '</td>
                    <td data-col="capturado_en" class="text-nowrap">' . htmlspecialchars($r['capturado_en_fmt'] ?? '—') . '</td>
                    <td data-col="canal" class="text-center">' . $this->badgeCanal($r['canal'] ?? null) . '</td>
                    <td data-col="firma" class="text-center">' . $this->iconoSiNo(!empty($r['tiene_firma']), $tieneEnt) . '</td>
                    <td data-col="gps" class="text-center">' . $this->iconoSiNo(!empty($r['tiene_gps']), $tieneEnt) . '</td>
                    <td data-col="registrado_por" class="text-truncate" style="max-width:150px">' . htmlspecialchars($r['registrado_por'] ?? '—') . '</td>
                    <td data-col="observaciones" class="text-truncate" style="max-width:220px" title="' . htmlspecialchars($r['observaciones'] ?? '') . '">' . htmlspecialchars($r['observaciones'] ?? '—') . '</td>
                    <td data-col="acciones" class="text-center pe-3">' . $this->celdaAcciones($r) . '</td>
                  </tr>';
    }

    /** Filas del listado con el filtro/orden actual, sin paginar (para exportar). */
    private function filasParaExport(): array
    {
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $orden      = OrdenListado::leer($prefsVista, self::ORDEN_COL_DEFECTO, self::ORDEN_DIR_DEFECTO);

        $idsResponsables = $this->filtroResponsablesActual();

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $orden, $idsResponsables);
        return [
            'rows'   => $this->prepararFilas($data['rows'] ?? []),
            'estado' => $data['estado'] ?? EntregasConsignacionesRepository::ESTADO_PENDIENTE,
        ];
    }

    /** Subtítulo del reporte según el estado de entrega filtrado. */
    private function tituloExport(string $estado): string
    {
        return match ($estado) {
            EntregasConsignacionesRepository::ESTADO_ENTREGADA => 'Consignaciones entregadas',
            EntregasConsignacionesRepository::ESTADO_TODAS     => 'Consignaciones pendientes y entregadas',
            default                                            => 'Consignaciones pendientes de entregar',
        };
    }

    private function estadoTexto(string $estado): string
    {
        return $estado === 'Emitida' ? 'Pendiente' : $estado;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        ['rows' => $rows, 'estado' => $estado] = $this->filasParaExport();

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
            ?>
            <style>
                table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:7pt; }
                th { background:#f2f2f2; border:1px solid #ccc; padding:3px; text-align:left; }
                td { border:1px solid #ccc; padding:3px; }
                h2 { font-family:Arial,sans-serif; font-size:12pt; margin:0 0 2px 0; }
                .sub { font-family:Arial,sans-serif; font-size:8pt; color:#555; margin-bottom:6px; }
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="6mm" backright="6mm">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <div class="sub">Entregas de Consignaciones en Ventas &mdash; <?= htmlspecialchars($this->tituloExport($estado)) ?> &mdash; <?= date('d-m-Y H:i:s') ?></div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:7%">Emisión</th>
                            <th style="width:8%">Consignación</th>
                            <th style="width:14%">Cliente</th>
                            <th style="width:14%">Dirección</th>
                            <th style="width:10%">Responsable</th>
                            <th style="width:9%">Entrega programada</th>
                            <th style="width:6%">Estado</th>
                            <th style="width:4%">Días</th>
                            <th style="width:9%">Fecha/hora entrega</th>
                            <th style="width:6%">Canal</th>
                            <th style="width:4%">Firma</th>
                            <th style="width:4%">GPS</th>
                            <th style="width:5%">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $numero = ($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '');
                        $tieneEnt = !empty($r['id']);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($r['fecha_emision_fmt'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($numero) ?></td>
                            <td><?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($r['cliente_direccion'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($r['responsable_traslado_nombre'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars($r['fecha_entrega_fmt'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($this->estadoTexto((string) ($r['estado_consignacion'] ?? ''))) ?></td>
                            <td><?= $r['dias_espera'] !== null ? (int) $r['dias_espera'] : '—' ?></td>
                            <td><?= htmlspecialchars($r['capturado_en_fmt'] ?? '—') ?></td>
                            <td><?= $tieneEnt ? (($r['canal'] ?? 'movil') === 'web' ? 'Web' : 'App móvil') : '—' ?></td>
                            <td><?= $tieneEnt ? (!empty($r['tiene_firma']) ? 'Sí' : 'No') : '—' ?></td>
                            <td><?= $tieneEnt ? (!empty($r['tiene_gps']) ? 'Sí' : 'No') : '—' ?></td>
                            <td><?= htmlspecialchars((string) ($r['registrado_por'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Entregas_consignaciones_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        ['rows' => $rows, 'estado' => $estado] = $this->filasParaExport();

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = [
                'Emisión', 'Consignación', 'Cliente', 'Dirección', 'Responsable', 'Entrega programada',
                'Estado', 'Días', 'Fecha/hora entrega', 'Canal', 'Firma', 'GPS', 'Registrado por', 'Observaciones',
            ];

            $exportData = [];
            foreach ($rows as $r) {
                $numero   = ($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '');
                $tieneEnt = !empty($r['id']);
                $exportData[] = [
                    (string) ($r['fecha_emision_fmt'] ?? '—'),
                    $numero,
                    (string) ($r['cliente_nombre'] ?? ''),
                    (string) ($r['cliente_direccion'] ?? ''),
                    (string) ($r['responsable_traslado_nombre'] ?? '—'),
                    (string) ($r['fecha_entrega_fmt'] ?? '—'),
                    $this->estadoTexto((string) ($r['estado_consignacion'] ?? '')),
                    $r['dias_espera'] !== null ? (string) $r['dias_espera'] : '—',
                    (string) ($r['capturado_en_fmt'] ?? '—'),
                    $tieneEnt ? (($r['canal'] ?? 'movil') === 'web' ? 'Web' : 'App móvil') : '—',
                    $tieneEnt ? (!empty($r['tiene_firma']) ? 'Sí' : 'No') : '—',
                    $tieneEnt ? (!empty($r['tiene_gps']) ? 'Sí' : 'No') : '—',
                    (string) ($r['registrado_por'] ?? '—'),
                    (string) ($r['observaciones'] ?? ''),
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel(
                'Entregas_consignaciones',
                $headers,
                $exportData,
                'Entregas de Consignaciones — ' . $this->tituloExport($estado),
                $nombreEmpresa
            );
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    /** Sirve la imagen de la firma de una entrega (validando la empresa activa; anti path-traversal en el service). */
    public function firmaEntrega(): void
    {
        $this->requireLeer();

        $idEntrega = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        // Sin acceso total, la firma solo se sirve si la entrega es de uno de los
        // responsables del usuario — igual que el listado, que ya la oculta.
        $idsResponsables = $this->filtroResponsablesActual();
        if ($idEntrega > 0 && !$this->service->puedeVerEntrega($idEntrega, $idEmpresa, $idsResponsables)) {
            http_response_code(404);
            echo 'Firma no encontrada';
            exit;
        }

        $rel = $idEntrega > 0 ? $this->service->getFirmaEntrega($idEntrega, $idEmpresa) : null;
        if (!$rel) { http_response_code(404); echo 'Firma no encontrada'; exit; }

        $abs = \MVC_ROOT . '/' . $rel;
        if (!is_file($abs)) { http_response_code(404); echo 'Archivo no encontrado'; exit; }

        $mime = 'image/png';
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg'], true)) $mime = 'image/jpeg';
        elseif ($ext === 'webp') $mime = 'image/webp';

        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=300');
        readfile($abs);
        exit;
    }
}
