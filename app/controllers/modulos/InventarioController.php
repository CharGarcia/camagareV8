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

        // Filtros adicionales
        $desde      = $_GET['desde'] ?? '';
        $hasta      = $_GET['hasta'] ?? '';
        $tipo_mov   = $_GET['tipo_mov'] ?? '';
        $id_prod    = $_GET['id_producto'] ?? '';
        $id_bod     = $_GET['id_bodega'] ?? '';

        $empresa   = (new Empresa())->getPorId($idEmpresa) ?? [];
        
        $filtros = $this->getFiltrosDesdeRequest($buscar, $ordenCol, $ordenDir, $desde, $hasta, $id_prod, $id_bod);

        // Obtener datos del Kardex
        $kardexData = $this->service->getKardex($idEmpresa, $filtros, $page, $perPage);
        $rows       = $kardexData['rows'];
        $total      = $kardexData['total'];
        $totalPages = (int) ceil($total / $perPage);

        // Catálogos para filtros
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas    = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);
        $productos  = $this->getProductosInventariables($idEmpresa);
        $usuarios   = (new InventarioRepository())->getUsuariosConMovimientos($idEmpresa);
        $tipoRef    = (new InventarioRepository())->getTiposReferencia($idEmpresa);
        $umRepo     = new \App\repositories\modulos\UnidadesMedidaRepository();
        $medidas    = $umRepo->getActive($idEmpresa);

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

                $acciones = '';
                if ($this->getPermisos()['actualizar']) {
                    $acciones .= '<button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="editarMovimiento(' . $row['id'] . ')" title="Editar"><i class="bi bi-pencil"></i></button>';
                }
                if ($this->getPermisos()['eliminar']) {
                    $acciones .= '<button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarMovimiento(' . $row['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>';
                }

                echo '<tr class="inventario-row" onclick="editarMovimiento(' . $row['id'] . ')">
                        <td class="ps-3 small text-nowrap" data-col="fecha_movimiento">' . $fecha . '</td>
                        <td data-col="producto_nombre">
                            <div class="fw-bold text-dark mb-0">' . htmlspecialchars($row['producto_nombre']) . '</div>
                            <small class="text-muted">' . htmlspecialchars($row['producto_codigo']) . '</small>
                        </td>
                        <td class="small" data-col="bodega_nombre">' . htmlspecialchars($row['bodega_nombre']) . '</td>
                        <td class="text-center" data-col="tipo_movimiento">
                            <span class="badge ' . $badgeClass . ' rounded-pill px-2" style="font-size:0.7rem;">' . $label . '</span>
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

        $queryStr = http_build_query($filtros);

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
            'desde'           => $_GET['desde'] ?? $_POST['desde'] ?? $explicit[3] ?? '',
            'hasta'           => $_GET['hasta'] ?? $_POST['hasta'] ?? $explicit[4] ?? '',
            'tipo_movimiento' => $_GET['tipo_mov'] ?? $_POST['tipo_mov'] ?? $explicit[5] ?? '',
            'id_producto'     => $_GET['id_producto'] ?? $_POST['id_producto'] ?? $explicit[6] ?? '',
            'id_bodega'       => $_GET['id_bodega'] ?? $_POST['id_bodega'] ?? $explicit[7] ?? '',
            'id_usuario'      => $_GET['id_usuario'] ?? $_POST['id_usuario'] ?? '',
            'numero_lote'     => $_GET['numero_lote'] ?? $_POST['numero_lote'] ?? '',
            'nup'             => $_GET['nup'] ?? $_POST['nup'] ?? '',
            'referencia_tipo' => $_GET['referencia_tipo'] ?? $_POST['referencia_tipo'] ?? '',
            'id_medida'       => $_GET['id_medida'] ?? $_POST['id_medida'] ?? '',
        ];
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
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: left; }
                td { border: 1px solid #ccc; padding: 6px; }
                .text-end { text-align: right; }
                .header { text-align: center; margin-bottom: 20px; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Movimientos de Inventario</h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Bodega</th>
                        <th>Tipo</th>
                        <th class="text-end">Cant.</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= date('d-m-Y H:i:s', strtotime($r['fecha_movimiento'])) ?></td>
                        <td><?= htmlspecialchars($r['producto_nombre'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['bodega_nombre'] ?? '') ?></td>
                        <td><?= strtoupper($r['tipo_movimiento']) ?></td>
                        <td class="text-end">
                            <span class="<?= $r['tipo_movimiento'] === 'entrada' ? 'text-success' : 'text-danger' ?>">
                                <?= $r['tipo_movimiento'] === 'entrada' ? '+' : '-' ?><?= number_format(abs((float)$r['cantidad']), 2) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $html = ob_get_clean();
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream("Inventario_" . date('Ymd') . ".pdf", ["Attachment" => false]);
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
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
            
            if (!$prod || empty($prod['id_medida'])) {
                echo json_encode(['ok' => false, 'mensaje' => 'El producto no tiene una medida base asignada.']);
                exit;
            }

            $umRepo = new \App\repositories\modulos\UnidadesMedidaRepository();
            $medidas = $umRepo->getUnidadesMismoTipo((int)$prod['id_medida'], $idEmpresa);

            echo json_encode(['ok' => true, 'medidas' => $medidas, 'id_medida_base' => $prod['id_medida']]);
        } catch (\Throwable $e) {
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
            $this->service->eliminarMovimiento($id, $idEmpresa, $idUsuario, false, (int)$_SESSION['nivel']);
            echo json_encode(['ok' => true, 'mensaje' => 'Movimiento eliminado correctamente.']);
        } catch (\Throwable $e) {
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
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
