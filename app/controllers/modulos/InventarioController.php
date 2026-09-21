<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\InventarioRepository;
use App\Services\LogSistemaService;
use App\Services\modulos\InventarioService;
use App\models\Empresa;

class InventarioController extends BaseModuloController
{
    private InventarioService $service;
    private const RUTA_MODULO = 'modulos/inventario';

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $repo       = new InventarioRepository();
        $log        = new LogSistemaService();
        $this->service = new InventarioService($repo, $log);
    }

    // ────────────────────────────────────────────────────────────────
    // LISTA PRINCIPAL (Movimientos de Inventario / Kardex)
    // ────────────────────────────────────────────────────────────────
    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        // Parámetros de búsqueda y paginación
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_movimiento');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage   = 20;

        $empresa   = (new Empresa())->getPorId($idEmpresa) ?? [];

        // Los filtros adicionales (desde, hasta, tipo, producto, bodega…) los lee
        // getFiltrosDesdeRequest() del propio request; solo se le pasan los tres
        // primeros porque el orden sí sale de las preferencias del usuario.
        $filtros = $this->getFiltrosDesdeRequest($buscar, $ordenCol, $ordenDir);

        // Obtener datos del Kardex
        $kardexData = $this->service->getKardex($idEmpresa, $filtros, $page, $perPage);
        $rows       = $kardexData['rows'];
        $total      = $kardexData['total'];
        $saldo      = $kardexData['saldo'] ?? 0;
        $totalPages = (int) ceil($total / $perPage);

        // Catálogos para filtros
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas    = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);
        $productos  = $this->getProductosInventariables($idEmpresa);
        $usuarios   = (new InventarioRepository())->getUsuariosConMovimientos($idEmpresa);
        $tipoRef    = (new InventarioRepository())->getTiposReferencia($idEmpresa);
        $umRepo     = new \App\repositories\modulos\UnidadesMedidaRepository();
        $medidas    = $umRepo->getActive($idEmpresa);
        $categorias = $this->service->getCategoriasConMovimientos($idEmpresa);

        $permisos   = $this->getPermisos();
        $base       = BASE_URL;
        $rutaModulo = self::RUTA_MODULO;

        $this->viewWithLayout('layouts/main', 'modulos/inventario/index', [
            'empresa'    => $empresa,
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'bodegas'    => $bodegas,
            'productos'  => $productos,
            'usuarios'   => $usuarios,
            'tipos_ref'  => $tipoRef,
            'medidas'    => $medidas,
            'categorias' => $categorias,
            'saldo'      => $saldo,
            'filtros'    => $filtros,
            'perm'       => $permisos,
            'base'       => $base,
            'rutaModulo' => $rutaModulo,
            'titulo'     => 'Movimientos de Inventario',
            'fullWidth'  => true,
            'vistaConfig'=> $prefsVista
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_movimiento');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage   = 20;

        $filtros = $this->getFiltrosDesdeRequest($buscar, $ordenCol, $ordenDir);

        $result = $this->service->getKardex($idEmpresa, $filtros, $page, $perPage);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $saldo  = $result['saldo'] ?? 0;
        $totalPages = (int) ceil($total / $perPage);

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        // Renderizar Filas
        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="11" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> No se encontraron movimientos con los filtros actuales</td></tr>';
        } else {
            foreach ($rows as $row) {
                $badgeClass = ($row['tipo_movimiento'] === 'entrada') ? 'badge-entrada' : 'badge-salida';
                $label = ($row['tipo_movimiento'] === 'entrada') ? 'ENTRADA' : 'SALIDA';
                $fecha = date('d-m-Y H:i:s', strtotime($row['fecha_movimiento']));
                $cad   = $row['fecha_caducidad'] ? date('d-m-Y', strtotime($row['fecha_caducidad'])) : '-';
                $signo = ($row['tipo_movimiento'] === 'entrada' ? '+' : '-');
                $color = ($row['tipo_movimiento'] === 'entrada' ? 'text-success' : 'text-danger');
                $anulado = !empty($row['eliminado']);

                echo '<tr class="inventario-row' . ($anulado ? ' opacity-50' : '') . '" onclick="editarMovimiento(' . $row['id'] . ')">
                        <td class="ps-3 small text-nowrap" data-col="fecha_movimiento">' . $fecha . '</td>
                        <td data-col="producto_nombre">
                            <div class="fw-bold text-dark mb-0">' . htmlspecialchars($row['producto_nombre']) . '</div>
                            <small class="text-muted">' . htmlspecialchars($row['producto_codigo']) . '</small>
                        </td>
                        <td class="small" data-col="bodega_nombre">' . htmlspecialchars($row['bodega_nombre']) . '</td>
                        <td class="text-center" data-col="tipo_movimiento">
                            <span class="badge ' . $badgeClass . ' rounded-pill px-2" style="font-size:0.7rem;">' . $label . '</span>
                            ' . ($anulado ? '<span class="badge bg-secondary rounded-pill px-2 ms-1" style="font-size:0.7rem;"><i class="bi bi-slash-circle me-1"></i>ANULADO</span>' : '') . '
                        </td>
                        <td class="text-end fw-bold" data-col="cantidad">
                            <span class="' . $color . '">' . $signo . number_format(abs((float)$row['cantidad']), 2) . '</span>
                        </td>
                        <td class="small" data-col="nombre_medida">
                            ' . htmlspecialchars($row['nombre_medida'] ?? '-') . '
                            ' . (!empty($row['abreviatura_medida']) ? '<small class="text-muted">(' . htmlspecialchars($row['abreviatura_medida']) . ')</small>' : '') . '
                        </td>
                        <td class="small" data-col="numero_lote">' . htmlspecialchars($row['numero_lote'] ?? '-') . '</td>
                        <td class="small" data-col="fecha_caducidad">' . $cad . '</td>
                        <td class="small" data-col="nup">' . htmlspecialchars($row['nup'] ?? '-') . '</td>
                        <td class="small" data-col="usuario_nombre">' . htmlspecialchars($row['usuario_nombre'] ?? '-') . '</td>
                        <td class="small text-truncate" style="max-width: 150px;" data-col="observaciones" title="' . htmlspecialchars($row['observaciones'] ?? '') . '">
                            ' . htmlspecialchars($row['observaciones'] ?? '-') . '
                        </td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        // Renderizar Paginación
        ob_start();
        $this->renderPagination($page, $totalPages);
        $paginationHtml = ob_get_clean();

        $queryStr = $this->queryExportacion($filtros);

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'saldo'      => number_format($saldo, 2),
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/exportPdf?' . $queryStr,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/exportExcel?' . $queryStr
        ]);
        exit;
    }

    private function getFiltrosDesdeRequest(...$explicit): array
    {
        $buscar   = $explicit[0] ?? trim($_GET['b'] ?? $_POST['b'] ?? '');
        $sort     = $explicit[1] ?? trim($_GET['sort'] ?? $_POST['sort'] ?? 'fecha_movimiento');
        $dir      = $explicit[2] ?? strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'desc'));

        return [
            'buscar'          => $buscar,
            'sort'            => $sort,
            'dir'             => $dir,
            'desde'           => $_GET['desde'] ?? $_POST['desde'] ?? '',
            'hasta'           => $_GET['hasta'] ?? $_POST['hasta'] ?? '',
            // `tipo_movimiento` es el nombre con el que queryExportacion() devuelve el
            // filtro a PDF/Excel; `tipo_mov` es el nombre histórico del formulario. Si
            // solo se lee `tipo_mov`, el PDF sale sin el filtro de entrada/salida.
            'tipo_movimiento' => $_GET['tipo_movimiento'] ?? $_POST['tipo_movimiento']
                                 ?? $_GET['tipo_mov'] ?? $_POST['tipo_mov'] ?? '',
            'id_producto'     => $_GET['id_producto'] ?? $_POST['id_producto'] ?? '',
            'id_bodega'       => $_GET['id_bodega'] ?? $_POST['id_bodega'] ?? '',
            'id_usuario'      => $_GET['id_usuario'] ?? $_POST['id_usuario'] ?? '',
            'numero_lote'     => $_GET['numero_lote'] ?? $_POST['numero_lote'] ?? '',
            'nup'             => $_GET['nup'] ?? $_POST['nup'] ?? '',
            'referencia_tipo' => $_GET['referencia_tipo'] ?? $_POST['referencia_tipo'] ?? '',
            'id_medida'       => $_GET['id_medida'] ?? $_POST['id_medida'] ?? '',
            'ver_anulados'    => !empty($_GET['ver_anulados'] ?? $_POST['ver_anulados'] ?? ''),
        ];
    }

    /**
     * Query string de los enlaces de PDF/Excel. exportPdf()/exportExcel() leen la búsqueda
     * en `b` (como el listado); antes se enviaba como `buscar` y las exportaciones salían
     * sin la búsqueda ni los filtros del buscador.
     */
    private function queryExportacion(array $filtros): string
    {
        $q = $filtros;
        unset($q['buscar']);
        return http_build_query(['b' => $filtros['buscar'] ?? ''] + $q);
    }

    private function renderPagination(int $page, int $totalPages): void
    {
        $disablePrev = $page <= 1 ? 'disabled' : '';
        $disableNext = $page >= $totalPages ? 'disabled' : '';
        
        echo '<button type="button" class="btn btn-outline-secondary" ' . $disablePrev . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $disableNext . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'fecha_movimiento');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'desc'));

        $filtros = $this->getFiltrosDesdeRequest($buscar, $ordenCol, $ordenDir);

        $result = $this->service->getKardex($idEmpresa, $filtros, 1, 5000);
        $rows   = $result['rows'];

        try {
            $empresa   = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE INVENTARIO';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 3px 3px; font-size: 7.5pt; text-align: left; }
                td { border: 1px solid #ccc; padding: 2px 3px; font-size: 7pt; overflow: hidden; word-wrap: break-word; }
                .text-end { text-align: right; }
                .entrada { color: #198754; }
                .salida { color: #dc3545; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
                .fecha-reporte { margin: 3px 0 0 0; color: #666; font-size: 8pt; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Movimientos de Inventario</h2>
                    <div class="fecha-reporte">Fecha de reporte: <?= date('d-m-Y H:i:s') ?></div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%">Fecha</th>
                            <th style="width: 27%">Producto</th>
                            <th style="width: 14%">Bodega</th>
                            <th style="width: 9%">Tipo</th>
                            <th style="width: 9%" class="text-end">Cant.</th>
                            <th style="width: 26%">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= date('d-m-Y H:i:s', strtotime((string)$r['fecha_movimiento'])) ?></td>
                            <td><?= htmlspecialchars((string)($r['producto_nombre'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($r['bodega_nombre'] ?? '')) ?></td>
                            <td><?= strtoupper((string)$r['tipo_movimiento']) ?></td>
                            <td class="text-end <?= $r['tipo_movimiento'] === 'entrada' ? 'entrada' : 'salida' ?>">
                                <?= $r['tipo_movimiento'] === 'entrada' ? '+' : '-' ?><?= number_format(abs((float)$r['cantidad']), 2) ?>
                            </td>
                            <td><?= htmlspecialchars((string)($r['observaciones'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="6" style="text-align:center">Sin movimientos para los filtros aplicados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $html = ob_get_clean();

            // Html2Pdf, no Dompdf: Dompdf no está instalado en vendor/ y reventaba en ejecución.
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('Inventario_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html');
            echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'fecha_movimiento');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'desc'));

        $filtros = $this->getFiltrosDesdeRequest($buscar, $ordenCol, $ordenDir);

        $result = $this->service->getKardex($idEmpresa, $filtros, 1, 10000);
        $rows   = $result['rows'];

        try {
            $empresa = (new Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $headers = ['Fecha', 'Producto', 'Código', 'Bodega', 'Tipo', 'Cantidad', 'Lote', 'Caducidad', 'Obs.'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    date('d-m-Y H:i:s', strtotime($r['fecha_movimiento'])),
                    (string)($r['producto_nombre'] ?? ''),
                    (string)($r['producto_codigo'] ?? ''),
                    (string)($r['bodega_nombre'] ?? ''),
                    strtoupper($r['tipo_movimiento']),
                    (float)$r['cantidad'],
                    (string)($r['numero_lote'] ?? ''),
                    $r['fecha_caducidad'] ? date('d-m-Y', strtotime($r['fecha_caducidad'])) : '',
                    (string)($r['observaciones'] ?? '')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Inventario', $headers, $exportData, 'Stock_Actual', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
        }
    }

    // ────────────────────────────────────────────────────────────────
    // AJUSTE MANUAL (POST AJAX)
    // ────────────────────────────────────────────────────────────────
    public function ajusteAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $data      = $_POST;

            if (empty($data['id_producto']) || empty($data['id_bodega'])) {
                echo json_encode(['ok' => false, 'mensaje' => 'Producto y bodega son obligatorios.']);
                exit;
            }
            if (empty($data['cantidad']) || (float)$data['cantidad'] <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'La cantidad debe ser mayor a cero.']);
                exit;
            }
            if (empty($data['tipo_movimiento']) || !in_array($data['tipo_movimiento'], ['entrada','salida','ajuste'])) {
                echo json_encode(['ok' => false, 'mensaje' => 'Tipo de movimiento no válido.']);
                exit;
            }

            if (!empty($data['id'])) {
                $this->requireActualizar();
                $this->service->actualizarMovimiento((int)$data['id'], $data, $idEmpresa, $idUsuario, (int)$_SESSION['nivel']);
                $msg = 'Ajuste actualizado correctamente.';
            } else {
                $this->requireCrear();
                $idKardex = $this->service->ajusteManual($data, $idEmpresa, $idUsuario);
                $msg = 'Ajuste registrado correctamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $msg]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // KARDEX AJAX (para paginación/filtros vía fetch)
    // ────────────────────────────────────────────────────────────────
    public function getKardexAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = [
            'id_producto' => (int) ($_GET['id_producto'] ?? 0) ?: null,
            'id_bodega'   => (int) ($_GET['id_bodega']   ?? 0) ?: null,
            'tipo'        => $_GET['tipo'] ?? '',
            'desde'       => $_GET['desde'] ?? '',
            'hasta'       => $_GET['hasta'] ?? '',
        ];
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $result  = $this->service->getKardex($idEmpresa, $filtros, $page, 50);
        echo json_encode(['ok' => true, 'data' => $result]);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // STOCK AJAX (para consulta rápida desde otros módulos)
    // ────────────────────────────────────────────────────────────────
    public function getStockAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega']   ?? 0);

        if (!$idProducto || !$idBodega) {
            echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros.']);
            exit;
        }

        $repo  = new InventarioRepository();
        $stock = $repo->getStockActual($idProducto, $idBodega, $idEmpresa);

        echo json_encode(['ok' => true, 'stock' => $stock]);
        exit;
    }

    public function getLotesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega']   ?? 0);

        if (!$idProducto || !$idBodega) {
            echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros.']);
            exit;
        }

        $repo  = new InventarioRepository();
        $lotes = $repo->getLotesDisponibles($idProducto, $idBodega, $idEmpresa);

        echo json_encode(['ok' => true, 'lotes' => $lotes]);
        exit;
    }

    public function getByIdAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $mov = $this->service->getById($id, $idEmpresa);
        if (!$mov) {
            echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el movimiento.']);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $mov]);
        exit;
    }

    /**
     * Comprobante PDF de un movimiento del kardex (ficha del registro).
     * Incluye los anulados: la ficha debe poder imprimirse igual, con su sello.
     */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $mov = $this->service->getById($id, $idEmpresa);
            if (!$mov) { http_response_code(404); echo 'Movimiento no encontrado'; exit; }

            $empresaModel     = new Empresa();
            $empresa          = $empresaModel->getPorId($idEmpresa) ?? [];
            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos[0]['logo_ruta'])) {
                $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
            }

            (new \App\Services\modulos\MovimientoInventarioPdfService())->generar($mov, $empresa, 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function getMedidasProductoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idEmpresa  = (int) $_SESSION['id_empresa'];

        if (!$idProducto) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID de producto no proporcionado.']);
            exit;
        }

        try {
            $prodRepo = new \App\repositories\modulos\ProductoRepository();
            $prod = $prodRepo->getDetalleCompleto($idProducto, $idEmpresa);

            $umRepo  = new \App\repositories\modulos\UnidadesMedidaRepository();
            $idBase  = ($prod && !empty($prod['id_medida'])) ? (int)$prod['id_medida'] : 0;
            $medidas = $idBase > 0 ? $umRepo->getUnidadesMismoTipo($idBase, $idEmpresa) : [];

            // Fallback: si el producto no tiene medida base o no hay unidades de su tipo,
            // se ofrecen todas las unidades activas de la empresa.
            if (empty($medidas)) {
                $medidas = $umRepo->getActive($idEmpresa);
                $idBase  = 0;
            }

            echo json_encode(['ok' => true, 'medidas' => $medidas, 'id_medida_base' => $idBase]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            // permitirAnularCompra=true: desde este módulo SÍ se permite anular un
            // traspaso generado por una Compra (pedido explícito); otros documentos
            // (facturas, recibos, etc.) siguen bloqueados — deben gestionarse desde
            // su propio módulo.
            $this->service->eliminarMovimiento($id, $idEmpresa, $idUsuario, false, (int)$_SESSION['nivel'], false, true);
            echo json_encode(['ok' => true, 'mensaje' => 'Movimiento anulado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function restaurarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->restaurarMovimiento($id, $idEmpresa, $idUsuario, true);
            echo json_encode(['ok' => true, 'mensaje' => 'Movimiento habilitado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // HELPERS
    // ────────────────────────────────────────────────────────────────
    private function getProductosInventariables(int $idEmpresa): array
    {
        try {
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT id, codigo, nombre FROM productos
                    WHERE id_empresa = :e AND eliminado = false AND inventariable = true
                    ORDER BY nombre";
            $st  = $db->prepare($sql);
            $st->execute([':e' => $idEmpresa]);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
    private function getBodegas(int $idEmpresa): array
    {
        try {
            $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
            return $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);
        } catch (\Throwable) {
            return [];
        }
    }

    // ────────────────────────────────────────────────────────────────
    // IMPORTAR EXCEL / CSV
    // ────────────────────────────────────────────────────────────────

    public function descargarPlantilla(): void
    {
        $this->requireLeer();
        $filename = 'plantilla_inventario.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF"; // BOM for Excel UTF-8
        $cols = ['id_producto', 'id_bodega', 'tipo_movimiento', 'cantidad', 'costo_unitario',
                 'numero_lote', 'fecha_fabricacion', 'fecha_caducidad', 'nup', 'observaciones'];
        echo implode(',', $cols) . "\n";
        echo "123,1,entrada,10.00,5.50,L001,2025-01-01,2026-12-31,,Carga inicial\n";
        exit;
    }

    public function importarExcelAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al subir el archivo.']);
            exit;
        }

        $tmpPath = $_FILES['archivo']['tmp_name'];
        $ext     = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv'])) {
            echo json_encode(['ok' => false, 'mensaje' => 'Solo se acepta CSV por ahora. Guarda el Excel como CSV.']);
            exit;
        }

        try {
            $handle     = fopen($tmpPath, 'r');
            $header     = fgetcsv($handle); // Leer cabecera
            $procesados = 0;
            $errores    = [];

            while (($row = fgetcsv($handle)) !== false) {
                if (empty(array_filter($row))) continue; // Fila vacía

                $data = array_combine($header, $row);

                if (empty($data['id_producto']) || empty($data['id_bodega']) || empty($data['cantidad'])) {
                    $errores[] = "Fila inválida: faltan campos obligatorios.";
                    continue;
                }

                $tipoDefault = $_POST['tipo_movimiento'] ?? 'entrada';
                $data['tipo_movimiento'] = $data['tipo_movimiento'] ?? $tipoDefault;

                $this->service->ajusteManual($data, $idEmpresa, $idUsuario);
                $procesados++;
            }

            fclose($handle);

            $msg = "Importación completada.";
            if (!empty($errores)) $msg .= " " . count($errores) . " filas con errores omitidas.";

            echo json_encode(['ok' => true, 'mensaje' => $msg, 'procesados' => $procesados]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
