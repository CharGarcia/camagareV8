<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\EntregasConsignacionesRepository;
use App\Services\modulos\EntregasConsignacionesService;

/**
 * Resumen de entregas de Consignaciones en Ventas: módulo de SOLO LECTURA (sin
 * crear/actualizar/eliminar) que unifica las evidencias de entrega (GPS + firma)
 * registradas desde la app móvil y las registradas manualmente desde el sistema.
 * El registro/edición de la entrega en sí sigue viviendo en modulos/consignaciones-ventas.
 */
class EntregasConsignacionesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/entregas-consignaciones';

    private EntregasConsignacionesService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new EntregasConsignacionesService(new EntregasConsignacionesRepository());
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /** Ids de responsables_traslado a los que restringir, según permiso "todo" del usuario. */
    private function filtroResponsablesActual(array $perm): ?array
    {
        return $this->service->resolverFiltroResponsables(
            (int) $_SESSION['id_usuario'],
            (int) $_SESSION['id_empresa'],
            !empty($perm['todo'])
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'capturado_en');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idsResponsables = $this->filtroResponsablesActual($perm);

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idsResponsables);
        $rows       = $this->prepararFilas($result['rows']);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $resumen = $this->service->getResumen($idEmpresa, $buscar, $idsResponsables);

        $anioActual = (int) date('Y');

        $this->viewWithLayout('layouts.main', 'modulos.entregas_consignaciones.index', [
            'titulo'      => 'Entregas de Consignaciones',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'resumen'     => $resumen,
            'anioDesde'   => $anioActual - 5,
            'anioHasta'   => $anioActual,
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
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'capturado_en');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage    = 20;

        $perm            = $this->getPermisos();
        $idsResponsables = $this->filtroResponsablesActual($perm);

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idsResponsables);
        $rows       = $this->prepararFilas($result['rows']);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-geo-alt fs-3 d-block mb-2"></i>No se encontraron entregas.</td></tr>';
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

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'resumen'    => $resumen,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/exportPdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/exportExcel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    /** Normaliza fechas y agrega firma_url/indicadores calculados a cada fila. */
    private function prepararFilas(array $rows): array
    {
        $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
        foreach ($rows as &$r) {
            if (!empty($r['fecha_emision'])) {
                $r['fecha_emision_fmt'] = date('d-m-Y', strtotime($r['fecha_emision']));
            }
            if (!empty($r['capturado_en'])) {
                $r['capturado_en_fmt'] = date('d-m-Y H:i:s', strtotime($r['capturado_en']));
            }
            $r['tiene_gps']   = ($r['latitud'] !== null && $r['longitud'] !== null);
            $r['tiene_firma'] = !empty($r['firma_path']);
            $r['firma_url']   = !empty($r['firma_path'])
                ? $base . '/' . self::RUTA_MODULO . '/firmaEntrega?id=' . (int) $r['id']
                : null;
        }
        unset($r);
        return $rows;
    }

    private function badgeCanal(string $canal): string
    {
        return $canal === 'web'
            ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-display me-1"></i>Web</span>'
            : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-phone me-1"></i>App móvil</span>';
    }

    private function iconoSiNo(bool $si): string
    {
        return $si
            ? '<i class="bi bi-check-circle-fill text-success" title="Sí"></i>'
            : '<i class="bi bi-dash-circle text-muted" title="No"></i>';
    }

    private function filaHtml(array $r): string
    {
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $numero   = htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? ''));

        return '<tr class="entc-row" role="button" tabindex="0" data-row=\'' . $dataJson . '\' onclick="entcAbrirDetalle(this)">
                    <td class="ps-3" data-col="capturado_en">' . htmlspecialchars($r['capturado_en_fmt'] ?? '—') . '</td>
                    <td data-col="secuencial" class="fw-bold text-primary">' . $numero . '</td>
                    <td data-col="cliente" class="text-truncate" style="max-width:220px" title="' . htmlspecialchars($r['cliente_nombre'] ?? '') . '">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                    <td data-col="responsable" class="text-truncate" style="max-width:160px">' . htmlspecialchars($r['responsable_traslado_nombre'] ?? '—') . '</td>
                    <td data-col="canal" class="text-center">' . $this->badgeCanal((string) ($r['canal'] ?? 'movil')) . '</td>
                    <td data-col="firma" class="text-center">' . $this->iconoSiNo(!empty($r['tiene_firma'])) . '</td>
                    <td data-col="gps" class="text-center">' . $this->iconoSiNo(!empty($r['tiene_gps'])) . '</td>
                    <td data-col="registrado_por" class="text-truncate" style="max-width:150px">' . htmlspecialchars($r['registrado_por'] ?? '—') . '</td>
                    <td data-col="observaciones" class="text-truncate" style="max-width:220px" title="' . htmlspecialchars($r['observaciones'] ?? '') . '">' . htmlspecialchars($r['observaciones'] ?? '—') . '</td>
                  </tr>';
    }

    /** Filas del listado con el filtro/orden actual, sin paginar (para exportar). */
    private function filasParaExport(): array
    {
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'capturado_en');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));

        $perm            = $this->getPermisos();
        $idsResponsables = $this->filtroResponsablesActual($perm);

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idsResponsables);
        return $this->prepararFilas($data['rows'] ?? []);
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExport();

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
                <div class="sub">Entregas de Consignaciones en Ventas &mdash; <?= date('d-m-Y H:i:s') ?></div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:12%">Fecha/hora entrega</th>
                            <th style="width:11%">Consignación</th>
                            <th style="width:19%">Cliente</th>
                            <th style="width:14%">Responsable</th>
                            <th style="width:9%">Canal</th>
                            <th style="width:6%">Firma</th>
                            <th style="width:6%">GPS</th>
                            <th style="width:12%">Registrado por</th>
                            <th style="width:11%">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $numero = ($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '');
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($r['capturado_en_fmt'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($numero) ?></td>
                            <td><?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($r['responsable_traslado_nombre'] ?? '—')) ?></td>
                            <td><?= ($r['canal'] ?? 'movil') === 'web' ? 'Web' : 'App móvil' ?></td>
                            <td><?= !empty($r['tiene_firma']) ? 'Sí' : 'No' ?></td>
                            <td><?= !empty($r['tiene_gps']) ? 'Sí' : 'No' ?></td>
                            <td><?= htmlspecialchars((string) ($r['registrado_por'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($r['observaciones'] ?? '')) ?></td>
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
        $rows = $this->filasParaExport();

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Fecha/hora entrega', 'Consignación', 'Cliente', 'Responsable', 'Canal', 'Firma', 'GPS', 'Registrado por', 'Observaciones'];

            $exportData = [];
            foreach ($rows as $r) {
                $numero = ($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '');
                $exportData[] = [
                    (string) ($r['capturado_en_fmt'] ?? '—'),
                    $numero,
                    (string) ($r['cliente_nombre'] ?? ''),
                    (string) ($r['responsable_traslado_nombre'] ?? '—'),
                    ($r['canal'] ?? 'movil') === 'web' ? 'Web' : 'App móvil',
                    !empty($r['tiene_firma']) ? 'Sí' : 'No',
                    !empty($r['tiene_gps']) ? 'Sí' : 'No',
                    (string) ($r['registrado_por'] ?? '—'),
                    (string) ($r['observaciones'] ?? ''),
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Entregas_consignaciones', $headers, $exportData, 'Entregas de Consignaciones', $nombreEmpresa);
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
