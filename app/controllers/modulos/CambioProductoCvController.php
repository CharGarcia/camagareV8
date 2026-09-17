<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\repositories\modulos\CambioProductoCvRepository;
use App\repositories\modulos\ProductoRepository;
use App\Rules\modulos\CambioProductoCvRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CambioProductoCvService;
use Exception;

/**
 * Cambios de productos (`modulos/cambio-producto-cv`).
 *
 * Registra el cambio de productos de un cliente: lo que DEVUELVE (entrada de
 * inventario, desde una factura de consignación o un cambio anterior) y lo que RECIBE a
 * cambio (salida de inventario). La diferencia de valor es informativa.
 */
class CambioProductoCvController extends BaseModuloController
{
    private CambioProductoCvService $service;
    private const RUTA_MODULO = 'modulos/cambio-producto-cv';
    private const TIPO_SECUENCIAL = 'Cambios de productos';

    public function __construct()
    {
        parent::__construct();

        $repository = new CambioProductoCvRepository();
        $rules      = new CambioProductoCvRules();
        $logService = new LogSistemaService();
        $this->service = new CambioProductoCvService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /**
     * Cabecera del cambio de la empresa activa, cortando con 403 si el usuario
     * no tiene acceso total y el documento lo creó otro (mismo criterio que el
     * listado). Para las acciones que reciben un id suelto.
     */
    private function docPropioOCortar(int $id): ?array
    {
        $doc = $this->service->getPorId($id, (int) $_SESSION['id_empresa']);
        $this->requireRegistroPropio($doc);
        return $doc;
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $orden    = OrdenListado::leer($prefsVista, 'fecha_cambio', 'DESC');
        $ordenCol = OrdenListado::primeraCol($orden, 'fecha_cambio');
        $ordenDir = OrdenListado::primeraDir($orden);
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        // Filas = parejas "entra ↔ sale" de cada cambio (no un cambio por fila).
        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $orden);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $empresaData = $this->getEmpresaConfig($idEmpresa);

        // Serie unificada (establecimiento-punto): solo puntos con secuencial de cambios configurado.
        $empresaRepo    = new \App\repositories\modulos\EmpresaRepository();
        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($empresaRepo->getPuntosEmision($idEmpresa) as $p) {
            $cfg = $repoSecuencial->getConfigSecuencial((int) $p['id'], self::TIPO_SECUENCIAL);
            if (!empty($cfg['id'])) {
                $puntos[] = $p;
            }
        }

        // Series REALMENTE usadas en cambios de productos guardados, para el
        // filtro "Serie" del buscador — a diferencia de $puntos (solo sirve para
        // elegir la serie de un cambio NUEVO), esto incluye series de cualquier
        // establecimiento y aunque el punto ya no tenga secuencial configurado.
        $seriesFiltro = (new CambioProductoCvRepository())->getSeriesDistintas($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.cambio_producto_cv.index', [
            'titulo'       => 'Cambios de productos',
            'perm'         => $perm,
            'rutaModulo'   => self::RUTA_MODULO,
            'empresa'      => $empresaData,
            'puntos'       => $puntos,
            'seriesFiltro' => $seriesFiltro,
            // Selects del modal de filtros del listado (solo valores usados por la empresa).
            'opcionesFiltros' => $this->service->getOpcionesFiltros($idEmpresa),
            'rows'         => $rows,
            'total'        => $total,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'perPage'      => $perPage,
            'buscar'       => $buscar,
            'ordenCol'     => $ordenCol,
            'ordenDir'     => $ordenDir,
            'decCant'      => self::decimalesCantidad($empresaData),
            'vistaConfig'  => $prefsVista,
            'fullWidth'    => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar   = trim($_GET['b'] ?? $_GET['q'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $orden    = OrdenListado::leer($prefsVista, 'fecha_cambio', 'DESC');
        $ordenCol = OrdenListado::primeraCol($orden, 'fecha_cambio');
        $ordenDir = OrdenListado::primeraDir($orden);
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $orden);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        $rowsHtml = '';
        if (empty($rows)) {
            $rowsHtml = '<tr><td colspan="14" class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>No se encontraron cambios.</td></tr>';
        } else {
            $decCant = self::decimalesCantidad($this->getEmpresaConfig($idEmpresa));
            foreach ($rows as $r) {
                $rowsHtml .= $this->renderFila($r, $decCant);
            }
        }

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    /**
     * Pestaña "Detalles" del modal de filtros: búsqueda libre dentro de los cambios (líneas
     * devueltas y entregadas con lote/NUP, bodega y documento de origen). Devuelve
     * id/estado/serie/secuencial para abrir el modal del cambio (abrirModalCambioVer).
     */
    public function buscarDetallesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            echo json_encode(['rows' => []]);
            return;
        }

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        // 'FACTURA': el número es el de la factura de venta (CambioProductoCvRepository::sqlNumeroOrigen).
        $origenes = ['FACTURA' => 'Factura', 'CAMBIO' => 'Cambio anterior', 'CONSIGNACION' => 'Consignación'];
        $rows = [];
        foreach ($this->service->buscarEnDetalles($idEmpresa, $q, $idUsuarioFiltro, 50) as $r) {
            $origenDoc = $r['documento_origen'] ?? '';
            if ($origenDoc !== '' && isset($origenes[strtoupper((string) ($r['origen_tipo'] ?? ''))])) {
                $origenDoc = $origenes[strtoupper((string) $r['origen_tipo'])] . ' ' . $origenDoc;
            }
            $rows[] = [
                'origen'      => ($r['tipo_linea'] ?? '') === 'devolucion' ? 'Devolución' : 'Entrega',
                'tipo'        => $r['tipo'] ?? '',
                'descripcion' => $r['descripcion'] ?? '',
                'extra'       => $r['extra'] ?? '',
                'bodega'      => $r['bodega'] ?? '',
                'documento'   => $origenDoc,
                'cantidad'    => $r['cantidad'] !== null ? number_format((float) $r['cantidad'], 2) : '',
                'monto'       => $r['monto'] !== null ? number_format((float) $r['monto'], 2) : '',
                'id'          => (int) $r['id'],
                'serie'       => $r['serie'] ?? '',
                'secuencial'  => $r['secuencial'] ?? '',
                'numero'      => ($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? ''),
                'fecha'       => !empty($r['fecha_cambio']) ? date('d-m-Y', strtotime((string) $r['fecha_cambio'])) : '',
                'cliente'     => $r['cliente_nombre'] ?? '',
                'estado'      => (string) ($r['estado'] ?? ''),
            ];
        }
        echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Una fila del listado. El HTML vive en un único partial que incluyen la carga inicial
     * (index.php) y el refresco AJAX: escribirlo en dos sitios deja uno desfasado.
     */
    private function renderFila(array $r, int $decCant): string
    {
        ob_start();
        include MVC_APP . '/views/modulos/cambio_producto_cv/_fila.php';
        return (string) ob_get_clean();
    }

    /** Decimales de cantidad de la empresa (config ya resuelta por getEmpresaConfig), acotados a 0-6. */
    private static function decimalesCantidad(array $empresaConfig): int
    {
        return max(0, min(6, (int) ($empresaConfig['decimales_cantidad'] ?? 2)));
    }

    /** Filas del listado (parejas entra ↔ sale) con el filtro/orden actual, sin paginar (para exportar). */
    private function filasParaExport(): array
    {
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $orden      = OrdenListado::leer($prefsVista, 'fecha_cambio', 'DESC');

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        // perPage = 0 => sin LIMIT (todas las filas que calcen con el filtro actual).
        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, OrdenListado::primeraCol($orden, 'fecha_cambio'),
            OrdenListado::primeraDir($orden), $idUsuarioFiltro, $orden);
        return $data['rows'] ?? [];
    }

    /**
     * Columna Factura del listado, como texto para PDF/Excel: la factura de venta afectada por lo que
     * entra (también si la unidad llegó en un cambio anterior; si no se encuentra, "Cambio …") y, en
     * las filas que solo tienen lo que sale, la factura de la que vino el cambio. "Sin factura" si lo
     * que entra no tiene factura de venta que mostrar (igual que _fila.php).
     */
    private static function textoFacturaDev(array $r): string
    {
        $afectada = trim((string) ($r['dev_factura_afectada'] ?? ''));
        if ($afectada !== '') {
            return $afectada;
        }
        $num = trim((string) ($r['dev_origen_numero'] ?? ''));
        if ($num === '') {
            $facturaCambio = trim((string) ($r['factura_cambio'] ?? ''));
            // Devolución migrada sin factura enlazada o de una factura de consignación sin factura de venta.
            if ($facturaCambio === '' && $r['dev_cantidad'] !== null && in_array($r['dev_origen_tipo'] ?? '', ['', 'FACTURA'], true)) {
                return 'Sin factura';
            }
            return $facturaCambio;
        }
        return (($r['dev_origen_tipo'] ?? '') === 'CAMBIO' ? 'Cambio ' : '') . $num;
    }

    /** Exporta el listado (con el filtro/orden actual del buscador) a PDF. */
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

            $decCant = self::decimalesCantidad($this->getEmpresaConfig((int) $_SESSION['id_empresa']));
            $cant    = static fn($v): string => ($v === null || $v === '') ? '' : number_format((float) $v, $decCant);
            $h       = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

            ob_start();
            ?>
            <style>
                table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:7pt; }
                th { border:1px solid #ccc; padding:3px; text-align:left; color:#fff; }
                th.fecha { background:#4fc3f7; color:#0d3c55; }
                th.entra { background:#dc3545; }
                th.sale { background:#198754; }
                td { border:1px solid #ccc; padding:3px; vertical-align:top; }
                .r { text-align:right; }
                .c { text-align:center; }
                .cod { color:#666; font-size:6pt; }
                tr.anulada td { color:#999; text-decoration:line-through; }
                tr.borrador td { color:#777; font-style:italic; }
                h2 { font-family:Arial,sans-serif; font-size:12pt; margin:0 0 2px 0; }
                .sub { font-family:Arial,sans-serif; font-size:8pt; color:#555; margin-bottom:6px; }
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="6mm" backright="6mm">
                <h2><?= $h($nombreEmpresa) ?></h2>
                <div class="sub">Cambios de productos &mdash; <?= date('d-m-Y H:i:s') ?></div>
                <table>
                    <thead>
                        <tr>
                            <th class="fecha" rowspan="2" style="width:5%">Fecha</th>
                            <th class="entra c" colspan="6">Entra</th>
                            <th class="sale c" colspan="7">Sale</th>
                        </tr>
                        <tr>
                            <th class="entra r" style="width:4%">Cantidad</th>
                            <th class="entra" style="width:11%">Producto</th>
                            <th class="entra" style="width:5%">Lote</th>
                            <th class="entra" style="width:7%">NUP</th>
                            <th class="entra" style="width:6%">Bodega</th>
                            <th class="entra" style="width:9%">Factura</th>
                            <th class="sale r" style="width:4%">Cantidad</th>
                            <th class="sale" style="width:11%">Producto</th>
                            <th class="sale" style="width:5%">Lote</th>
                            <th class="sale" style="width:7%">NUP</th>
                            <th class="sale" style="width:6%">Bodega</th>
                            <th class="sale" style="width:12%">Cliente</th>
                            <th class="sale" style="width:8%">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $clase = ['Anulada' => 'anulada', 'Borrador' => 'borrador'][(string) ($r['estado'] ?? '')] ?? '';
                    ?>
                        <tr class="<?= $clase ?>">
                            <td><?= !empty($r['fecha_cambio']) ? date('d-m-Y', strtotime((string) $r['fecha_cambio'])) : '' ?></td>
                            <td class="r"><?= $cant($r['dev_cantidad'] ?? null) ?></td>
                            <td><?= $h($r['dev_producto_nombre'] ?? '') ?><?php if (($r['dev_producto_codigo'] ?? '') !== ''): ?><br><span class="cod"><?= $h($r['dev_producto_codigo']) ?></span><?php endif; ?></td>
                            <td><?= $h($r['dev_lote'] ?? '') ?></td>
                            <td><?= $h($r['dev_nup'] ?? '') ?></td>
                            <td><?= $h($r['dev_bodega'] ?? '') ?></td>
                            <td><?= $h(self::textoFacturaDev($r)) ?></td>
                            <td class="r"><?= $cant($r['ent_cantidad'] ?? null) ?></td>
                            <td><?= $h($r['ent_producto_nombre'] ?? '') ?><?php if (($r['ent_producto_codigo'] ?? '') !== ''): ?><br><span class="cod"><?= $h($r['ent_producto_codigo']) ?></span><?php endif; ?></td>
                            <td><?= $h($r['ent_lote'] ?? '') ?></td>
                            <td><?= $h($r['ent_nup'] ?? '') ?></td>
                            <td><?= $h($r['ent_bodega'] ?? '') ?></td>
                            <td><?= $h($r['cliente_nombre'] ?? '') ?></td>
                            <td><?= $h($r['observaciones'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Cambios_productos_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    /** Exporta el listado (con el filtro/orden actual del buscador) a Excel. */
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

            // Mismas columnas que el listado; en Excel el código va en su propia columna.
            $headers = [
                'Fecha',
                'Entra: cantidad', 'Entra: código', 'Entra: producto', 'Entra: lote', 'Entra: NUP', 'Entra: bodega', 'Entra: factura',
                'Sale: cantidad', 'Sale: código', 'Sale: producto', 'Sale: lote', 'Sale: NUP', 'Sale: bodega', 'Cliente', 'Observaciones',
            ];
            $numEntra = 7; // A: fecha; B-H: lo que entra; I-P: lo que sale

            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    !empty($r['fecha_cambio']) ? date('d-m-Y', strtotime((string) $r['fecha_cambio'])) : '',
                    $r['dev_cantidad'] !== null ? (float) $r['dev_cantidad'] : null,
                    (string) ($r['dev_producto_codigo'] ?? ''),
                    (string) ($r['dev_producto_nombre'] ?? ''),
                    (string) ($r['dev_lote'] ?? ''),
                    (string) ($r['dev_nup'] ?? ''),
                    (string) ($r['dev_bodega'] ?? ''),
                    self::textoFacturaDev($r),
                    $r['ent_cantidad'] !== null ? (float) $r['ent_cantidad'] : null,
                    (string) ($r['ent_producto_codigo'] ?? ''),
                    (string) ($r['ent_producto_nombre'] ?? ''),
                    (string) ($r['ent_lote'] ?? ''),
                    (string) ($r['ent_nup'] ?? ''),
                    (string) ($r['ent_bodega'] ?? ''),
                    (string) ($r['cliente_nombre'] ?? ''),
                    (string) ($r['observaciones'] ?? ''),
                ];
            }

            $decCant = self::decimalesCantidad($this->getEmpresaConfig((int) $_SESSION['id_empresa']));
            $fmtCant = '#,##0' . ($decCant > 0 ? '.' . str_repeat('0', $decCant) : '');

            $reportService = new \App\Services\ReportService();
            $spreadsheet = $reportService->construirSpreadsheet($headers, $exportData, 'Cambios de productos', 'Cambios de productos - ' . $nombreEmpresa, [], [2 => $fmtCant, 2 + $numEntra => $fmtCant]);

            // Encabezados en rojo (entra) y verde (sale), como en pantalla. La fila de
            // encabezados se ubica por su primer texto para no depender de dónde la deja ReportService.
            $sheet = $spreadsheet->getActiveSheet();
            $filaEnc = 0;
            for ($f = 1; $f <= 6; $f++) {
                if ((string) $sheet->getCell('A' . $f)->getValue() === $headers[0]) { $filaEnc = $f; break; }
            }
            if ($filaEnc > 0) {
                $ultimaEntra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1 + $numEntra);
                $primeraSale = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $numEntra);
                $ultimaSale  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
                $sheet->getStyle("A{$filaEnc}")->getFill()->getStartColor()->setRGB('4FC3F7');
                $sheet->getStyle("A{$filaEnc}")->getFont()->getColor()->setRGB('0D3C55');
                $sheet->getStyle("B{$filaEnc}:{$ultimaEntra}{$filaEnc}")->getFill()->getStartColor()->setRGB('DC3545');
                $sheet->getStyle("{$primeraSale}{$filaEnc}:{$ultimaSale}{$filaEnc}")->getFill()->getStartColor()->setRGB('198754');

                // Sin columna Estado: los cambios anulados van tachados y los borradores en cursiva gris.
                foreach ($rows as $i => $r) {
                    $estado = (string) ($r['estado'] ?? '');
                    if ($estado !== 'Anulada' && $estado !== 'Borrador') continue;
                    $fila  = $filaEnc + 1 + $i;
                    $fuente = $sheet->getStyle("A{$fila}:{$ultimaSale}{$fila}")->getFont();
                    $fuente->getColor()->setRGB('888888');
                    $estado === 'Anulada' ? $fuente->setStrikethrough(true) : $fuente->setItalic(true);
                }
            }

            $reportService->descargarSpreadsheet($spreadsheet, 'Cambios_productos');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    public function store(): void
    {
        // El body se lee ANTES del guard porque el permiso depende de la operación:
        // crear un documento nuevo exige 'w', pero editar uno existente exige solo 'u'
        // (mismo criterio que Ingresos y Facturas de Venta). Cuando esto empezaba con
        // requireCrear() incondicional, el permiso "Actualizar" no servía por sí solo:
        // quien tenía 'u' sin 'w' recibía 403 al guardar cualquier cambio.
        $input = json_decode(file_get_contents('php://input'), true);
        if (!empty($input['id'])) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }
        header('Content-Type: application/json');

        try {
            if (!$input) {
                throw new Exception("Datos no recibidos.");
            }

            $input['id_empresa'] = (int) $_SESSION['id_empresa'];
            $input['id_usuario'] = (int) $_SESSION['id_usuario'];
            $input['empresa_config'] = $this->getEmpresaConfig($input['id_empresa']);

            if (!empty($input['id'])) {
                $this->docPropioOCortar((int) $input['id']);
                $this->service->actualizar((int) $input['id'], $input['id_empresa'], $input);
                echo json_encode(['ok' => true, 'msg' => 'Cambio actualizado correctamente.']);
            } else {
                // El número lo asigna el servidor al guardar (no el que se vio al abrir el
                // modal), así que se informa cuál quedó.
                $id     = $this->service->crear($input);
                $numero = $this->service->getUltimoNumeroGenerado();
                echo json_encode([
                    'ok'     => true,
                    'msg'    => 'Cambio ' . ($numero ?? '') . ' registrado correctamente. El inventario ha sido actualizado.',
                    'id'     => $id,
                    'numero' => $numero,
                ]);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID no válido.");
            $this->docPropioOCortar($id);

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Cambio eliminado. El inventario ha sido reversado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cambiarEstadoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = trim($_POST['estado'] ?? '');
            if ($id <= 0) throw new Exception("ID no válido.");
            $this->docPropioOCortar($id);

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->cambiarEstado($id, $idEmpresa, $idUsuario, $estado, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado a ' . $estado . '.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $this->docPropioOCortar($id);
            $data = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$data) throw new Exception("Cambio no encontrado.");
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Devuelve el asiento contable (a costo) del cambio: el guardado si existe,
     * o la sugerencia (neto Inventario vs Costo de Ventas).
     */
    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idCambio  = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            if ($idCambio <= 0) {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
                exit;
            }

            $cab = $this->service->getPorId($idCambio, $idEmpresa) ?? [];
            $this->requireRegistroPropio($cab ?: null);
            $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);

            // Cambio migrado sin asiento: no se genera ni se ofrece vista previa.
            if ($idAsiento <= 0 && !empty($cab) && $this->service->esMigrado($idCambio, $idEmpresa)) {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false,
                                  'aviso' => 'Cambio migrado del sistema anterior: no lleva asiento contable, porque ese sistema no contabilizaba los cambios de productos.']);
                exit;
            }

            if ($idAsiento <= 0 && !empty($cab)) {
                try {
                    $this->service->procesarAsientoContable($idCambio, ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);
                    $cab = $this->service->getPorId($idCambio, $idEmpresa) ?? [];
                    $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
                } catch (\Throwable $e) {}
            }

            if ($idAsiento > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    new LogSistemaService()
                );
                $cabAsiento = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);
                $detalles = [];
                foreach (($cabAsiento['detalles'] ?? []) as $det) {
                    $detalles[] = [
                        'id_cuenta_contable'   => (int) $det['id_cuenta_contable'],
                        'cuenta_codigo'        => $det['codigo_cuenta'] ?? $det['cuenta_codigo'] ?? '',
                        'cuenta_nombre'        => $det['nombre_cuenta'] ?? $det['cuenta_nombre'] ?? '',
                        'debe'                 => (float) $det['debe'],
                        'haber'                => (float) $det['haber'],
                        'referencia_detalle'   => $det['referencia_detalle'] ?? '',
                        'documento_referencia' => $det['documento_referencia'] ?? '',
                    ];
                }
                echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => true]);
                exit;
            }

            $detalles = $this->service->obtenerAsientoSugerido($idEmpresa, $idCambio);
            echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Genera el PDF del cambio (modelo general, con hook de plantilla por empresa). */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $this->docPropioOCortar($id);
            $cambio = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$cambio) { http_response_code(404); echo 'Cambio no encontrado'; exit; }

            try {
                $db = \App\Core\Database::getConnection();
                $st = $db->prepare("SELECT nombre FROM usuarios WHERE id = :u LIMIT 1");
                $st->execute([':u' => (int) ($cambio['created_by'] ?? 0)]);
                $cambio['usuario_nombre'] = (string) ($st->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                $cambio['usuario_nombre'] = '';
            }

            $detalles = $cambio['detalles'] ?? [];
            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'cambio_producto_cv');
            if ($plantilla) {
                $renderer->generar($plantilla, $cambio, $detalles, [], [], $empresa, 'D');
            } else {
                (new \App\Services\modulos\CambioProductoCvPdfService())
                    ->generar($cambio, $detalles, $empresa, 'D');
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /**
     * Genera el Excel del cambio: mismas secciones y columnas que el PDF (Devuelve / Entrega con
     * Origen, Código, Descripción, Lote, NUP, Bodega y Cantidad), sin precios ni totales.
     */
    public function excel(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $this->docPropioOCortar($id);
            $cambio = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$cambio) { http_response_code(404); echo 'Cambio no encontrado'; exit; }

            $detalles     = $cambio['detalles'] ?? [];
            $devoluciones = array_values(array_filter($detalles, fn($d) => ($d['tipo_linea'] ?? '') === 'devolucion'));
            $entregas     = array_values(array_filter($detalles, fn($d) => ($d['tipo_linea'] ?? '') === 'entrega'));

            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $numero = trim((string)($cambio['serie'] ?? '') . '-' . (string)($cambio['secuencial'] ?? ''), '-');

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Cambio');

            $sheet->setCellValue('A1', strtoupper((string)($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'CAMBIO DE PRODUCTOS N.° ' . ($numero !== '' ? $numero : '—'));
            $sheet->mergeCells('A2:G2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($cambio['fecha_cambio']) ? date('d-m-Y', strtotime((string)$cambio['fecha_cambio'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('C3', 'Cliente: ' . (string)($cambio['cliente_nombre'] ?? ''));
            $sheet->setCellValue('A4', 'Identificación: ' . (string)($cambio['cliente_identificacion'] ?? ''));
            $sheet->setCellValue('C4', 'Estado: ' . ucfirst((string)($cambio['estado'] ?? '')));

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];

            $row = 6;
            $escribirTabla = function (string $titulo, array $filas) use ($sheet, $headerStyle, &$row) {
                $sheet->setCellValue('A' . $row, $titulo);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                // Igual que el PDF: el cambio se hace por unidad, así que cada fila dice de dónde
                // viene (Origen), cuál es (lote / NUP) y su bodega. Sin precios ni totales.
                $headers = ['Origen', 'Código', 'Descripción', 'Lote', 'NUP', 'Bodega', 'Cantidad'];
                $col = 'A';
                foreach ($headers as $h) { $sheet->setCellValue($col . $row, $h); $col++; }
                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($headerStyle);
                $row++;

                $inicio = $row;
                if (empty($filas)) {
                    $sheet->setCellValue('A' . $row, 'Sin productos.');
                    $sheet->mergeCells('A' . $row . ':G' . $row);
                    $row++;
                } else {
                    foreach ($filas as $d) {
                        $sheet->setCellValueExplicit('A' . $row, \App\Services\modulos\CambioProductoCvPdfService::etiquetaOrigen($d), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('B' . $row, (string)($d['producto_codigo'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('C' . $row, (string)($d['producto_nombre'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('D' . $row, (string)($d['lote'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('E' . $row, (string)($d['nup'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('F' . $row, (string)($d['bodega_nombre'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('G' . $row, (float)($d['cantidad'] ?? 0));
                        $row++;
                    }
                }
                $sheet->getStyle('G' . $inicio . ':G' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            };

            $escribirTabla('Productos que devuelve', $devoluciones);
            $escribirTabla('Productos que entrega a cambio', $entregas);

            $sheet->setCellValue('A' . $row, 'Motivo: ' . (string)($cambio['motivo'] ?? ''));
            $sheet->mergeCells('A' . $row . ':G' . $row);
            $row++;
            $sheet->setCellValue('A' . $row, 'Observaciones: ' . (string)($cambio['observaciones'] ?? ''));
            $sheet->mergeCells('A' . $row . ':G' . $row);

            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'Cambio_' . ($numero !== '' ? $numero : 'comprobante') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombre . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    /** Envía por correo SOLO el PDF del cambio. */
    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $this->docPropioOCortar($id);
            $cambio = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$cambio) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'Cambio no encontrado.']); exit; }

            try {
                $db = \App\Core\Database::getConnection();
                $st = $db->prepare("SELECT nombre FROM usuarios WHERE id = :u LIMIT 1");
                $st->execute([':u' => (int) ($cambio['created_by'] ?? 0)]);
                $cambio['usuario_nombre'] = (string) ($st->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                $cambio['usuario_nombre'] = '';
            }

            $detalles = $cambio['detalles'] ?? [];
            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'cambio_producto_cv');
            if ($plantilla) {
                $pdfString = $renderer->generar($plantilla, $cambio, $detalles, [], [], $empresa, 'S');
            } else {
                $pdfString = (new \App\Services\modulos\CambioProductoCvPdfService())->generar($cambio, $detalles, $empresa, 'S');
            }

            $numero = trim((string)($cambio['serie'] ?? '') . '-' . (string)($cambio['secuencial'] ?? ''), '-');

            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') {
                $correosDestino = (string)($cambio['cliente_email'] ?? '');
            }
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'El cliente no tiene correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            $clienteNombre = (string)($cambio['cliente_nombre'] ?? 'Cliente');
            $empresaNombre = (string)($empresa['nombre'] ?? '');
            $asunto = 'Cambio de productos ' . ($numero !== '' ? $numero : '') . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>"
                . "<p>Estimad@ " . htmlspecialchars($clienteNombre) . ",</p>"
                . "<p>Adjunto encontrará el comprobante del cambio de productos <strong>" . htmlspecialchars($numero) . "</strong>.</p>"
                . "<p>Saludos cordiales,<br>" . htmlspecialchars($empresaNombre) . "</p>"
                . "</div>";

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $clienteNombre, $asunto, $cuerpo, $pdfString,
                'Cambio_' . ($numero !== '' ? $numero : 'comprobante'), $empresaNombre
            );

            if (ob_get_level() > 0) ob_end_clean();
            if ($enviado) {
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración de correo o el destinatario.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    // ─── Buscadores ───────────────────────────────────────────────────────────

    /** Busca clientes por nombre o identificación. */
    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'data' => []]); exit; }

        try {
            // Búsqueda estándar del sistema: todas las palabras en cualquier orden y
            // sin distinguir tildes (ClienteRepository::buscarAutocomplete). Aquí se
            // buscan también los clientes inactivos: el cambio puede venir de una
            // factura de consignación antigua de un cliente que ya se dio de baja.
            $rows = (new \App\repositories\modulos\ClienteRepository())
                ->buscarAutocomplete($idEmpresa, $q, 15, false);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Líneas disponibles para devolver (facturas de consignación + cambios previos, con saldo), una por ítem.
     * Con `id_cliente` acota a ese cliente (y admite `q` vacío: todo lo pendiente del cliente);
     * sin cliente busca entre todos por NUP, lote, número de documento o producto, y el
     * navegador fija el cliente del cambio con el de la línea que se agregue.
     */
    public function buscarLineasOrigenAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $q         = trim($_GET['q'] ?? '');
            $excluir   = (int) ($_GET['excluir'] ?? 0);
            if ($idCliente <= 0 && $q === '') throw new Exception("Indique un NUP, un número de documento o un producto, o seleccione el cliente.");

            $rows = $this->service->getLineasDisponiblesCliente($idEmpresa, $idCliente > 0 ? $idCliente : null, $q, $excluir > 0 ? $excluir : null);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Qué se puede ENTREGAR a cambio, en una sola respuesta con tres grupos:
     *  - `consignaciones`: líneas de consignaciones Entregadas con saldo en poder del
     *    cliente (por número de consignación, cliente, NUP, lote o producto);
     *  - `inventario`: existencias por bodega / lote / NUP que coinciden con la búsqueda;
     *  - `catalogo`: productos del catálogo (bienes), aunque no tengan stock registrado.
     * Con `id_cliente` las consignaciones se acotan a ese cliente.
     */
    public function buscarEntregasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $q         = trim($_GET['q'] ?? '');
            $excluir   = (int) ($_GET['excluir'] ?? 0);
            if ($idCliente <= 0 && $q === '') throw new Exception("Indique un número de consignación, un NUP, un lote o un producto.");

            $consignaciones = $this->service->getLineasConsignacionDisponibles($idEmpresa, $q, $idCliente > 0 ? $idCliente : null, $excluir > 0 ? $excluir : null);

            $inventario = [];
            $catalogo   = [];
            if ($q !== '') {
                $inventario = $this->service->buscarInventario($idEmpresa, $q, 30);

                $repo = new ProductoRepository();
                $res  = $repo->getListado($idEmpresa, $q, 1, 15, 'nombre', 'ASC', null, null, true);
                foreach (($res['rows'] ?? []) as $p) {
                    // Solo bienes/productos, no servicios.
                    if ((string)($p['tipo_produccion'] ?? '01') === '02') continue;
                    $catalogo[] = [
                        'id'              => (int) $p['id'],
                        'codigo'          => $p['codigo'] ?? '',
                        'nombre'          => $p['nombre'] ?? '',
                        'inventariable'   => $p['inventariable'] ?? null,
                        'tipo_produccion' => $p['tipo_produccion'] ?? '01',
                    ];
                }
            }

            echo json_encode(['ok' => true, 'consignaciones' => $consignaciones, 'inventario' => $inventario, 'catalogo' => $catalogo]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Busca productos de catálogo (para la entrega). */
    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'data' => []]); exit; }

        try {
            $repo = new ProductoRepository();
            $res  = $repo->getListado($idEmpresa, $q, 1, 15, 'nombre', 'ASC', null, null, true);
            $data = [];
            foreach (($res['rows'] ?? []) as $p) {
                // Solo bienes/productos, no servicios.
                if ((string)($p['tipo_produccion'] ?? '01') === '02') continue;
                $data[] = [
                    'id'              => (int) $p['id'],
                    'codigo'          => $p['codigo'] ?? '',
                    'nombre'          => $p['nombre'] ?? '',
                    'inventariable'   => $p['inventariable'] ?? null,
                    'tipo_produccion' => $p['tipo_produccion'] ?? '01',
                ];
            }
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Precios de lista de un producto (para la entrega). */
    public function getPreciosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        try {
            $repo = new ProductoRepository();
            $precios = $idProducto > 0 ? $repo->getPrecios($idProducto, $idEmpresa) : [];
            echo json_encode(['ok' => true, 'data' => $precios]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Bodegas de la empresa (para elegir de dónde sale la entrega). */
    public function getBodegasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $db = \App\Core\Database::getConnection();
            $st = $db->prepare("SELECT id, nombre FROM bodegas WHERE id_empresa = :e AND eliminado = false ORDER BY nombre ASC");
            $st->execute([':e' => $idEmpresa]);
            echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Secuencial (mismo patrón que Consignaciones/Retornos) ────────────────

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $empresaModel = new \App\models\Empresa();
        $establecimientos = $empresaModel->getEstablecimientos((int) $_SESSION['id_empresa']);
        echo json_encode(['ok' => true, 'data' => $establecimientos]);
        exit;
    }

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEst = (int) ($_GET['id_establecimiento'] ?? 0);
        $empresaModel = new \App\models\Empresa();
        $puntos = $empresaModel->getPuntosEmision($idEst);

        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $puntosFiltrados = [];
        foreach ($puntos as $p) {
            $config = $repoSecuencial->getConfigSecuencial((int) $p['id'], self::TIPO_SECUENCIAL);
            if (!empty($config['id'])) {
                $puntosFiltrados[] = $p;
            }
        }
        echo json_encode(['ok' => true, 'data' => array_values($puntosFiltrados)]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);

        $repo = new \App\repositories\SecuencialRepository();
        $config = $repo->getConfigSecuencial($idPunto, self::TIPO_SECUENCIAL);

        if (empty($config['id'])) {
            echo json_encode([
                'ok'  => false,
                'msg' => 'No hay configuración de secuencial para "' . self::TIPO_SECUENCIAL . '" en este punto de emisión. Configúrelo en Empresa / Secuenciales.'
            ]);
            exit;
        }

        $secuencialService = new \App\Services\SecuencialService();
        // Fecha del documento: solo pesa si este tipo numera por fecha de emisión
        // (Empresa → Secuenciales); en modo consecutivo el servidor la ignora.
        $fecha = trim($_GET['fecha'] ?? '') ?: null;
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL, $fecha);
        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getEmpresaConfig(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData, $estConfig);
                }
            } catch (\Throwable $e) {}
        }
        return $empresaData;
    }

    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }
}
