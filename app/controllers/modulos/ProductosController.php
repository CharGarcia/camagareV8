<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ProductoRepository;
use App\Rules\modulos\ProductoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ProductoService;
use App\repositories\modulos\InventarioRepository;
use App\Services\modulos\InventarioService;
use App\repositories\modulos\EmpresaRepository;
use Exception;

class ProductosController extends BaseModuloController
{
    private ProductoService $service;
    private const RUTA_MODULO = 'modulos/productos';

    public function __construct()
    {
        parent::__construct();
        $repository = new ProductoRepository();
        $rules = new ProductoRules();
        $logService = new LogSistemaService();
        
        $invRepo = new InventarioRepository();
        $invService = new InventarioService($invRepo, $logService);
        
        $this->service = new ProductoService($repository, $rules, $logService, $invService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $moduloKey = basename(self::RUTA_MODULO);
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($moduloKey);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];

        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.productos.index', [
            'titulo'     => 'Productos',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'vistaConfig'=> $prefsVista,
            'fullWidth'  => true
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $moduloKey = basename(self::RUTA_MODULO);
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($moduloKey);
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-box fs-3 d-block mb-2"></i>No se encontraron productos.</td></tr>';
        } else {
                foreach ($rows as $r) {
                $statusBadge = ((int)($r['status'] ?? 1) == 1)
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10">Activo</span>'
                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10">Inactivo</span>';

                $tipoBadge = (($r['tipo_produccion'] ?? '01') == '01') 
                    ? '<td class="text-center" data-col="tipo_produccion"><span class="badge rounded-pill bg-light text-dark border small"><i class="bi bi-box text-primary me-1"></i> Bien</span></td>'
                    : '<td class="text-center" data-col="tipo_produccion"><span class="badge rounded-pill bg-light text-dark border small"><i class="bi bi-gear-wide-connected text-info me-1"></i> Servicio</span></td>';

                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="producto-row" role="button" data-row=\'' . $dataAttr . '\' onclick="abrirModalProductoEditar(this)">
                        <td class="ps-3 fw-bold" data-col="codigo">' . htmlspecialchars((string)($r['codigo'] ?? '')) . '</td>
                        <td data-col="codigo_auxiliar">' . htmlspecialchars((string)($r['codigo_auxiliar'] ?? '—')) . '</td>
                        <td data-col="codigo_barras">' . htmlspecialchars((string)($r['codigo_barras'] ?? '—')) . '</td>
                        <td data-col="nombre" class="text-wrap" style="max-width:300px"><span class="fw-medium">' . htmlspecialchars((string)($r['nombre'] ?? '')) . '</span></td>
                        ' . $tipoBadge . '
                        <td data-col="nombre_categoria">' . htmlspecialchars((string)($r['nombre_categoria'] ?? '—')) . '</td>
                        <td data-col="nombre_marca">' . htmlspecialchars((string)($r['nombre_marca'] ?? '—')) . '</td>
                        <td data-col="nombre_medida">' . htmlspecialchars((string)($r['nombre_medida'] ?? '—')) . '</td>
                        <td class="text-end fw-medium" data-col="precio_base">$' . number_format((float)($r['precio_base'] ?? 0), 2) . '</td>
                        <td class="text-center" data-col="nombre_tarifa_iva"><span class="small">' . htmlspecialchars((string)($r['nombre_tarifa_iva'] ?? '—')) . '</span></td>
                        <td class="text-end text-muted" data-col="valor_iva">$' . number_format((float)($r['valor_iva'] ?? 0), 2) . '</td>
                        <td class="text-end text-muted" data-col="valor_ice">$' . number_format((float)($r['valor_ice'] ?? 0), 2) . '</td>
                        <td class="text-end fw-bold text-primary" data-col="pvp">$' . number_format((float)($r['pvp'] ?? 0), 2) . '</td>
                        <td class="text-center" data-col="inventariable">' . (($r['inventariable'] ?? false) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>') . '</td>
                        <td class="text-end" data-col="stock_minimo">' . number_format((float)($r['stock_minimo'] ?? 0), 2) . '</td>
                        <td class="text-end" data-col="stock_maximo">' . number_format((float)($r['stock_maximo'] ?? 0), 2) . '</td>
                        <td class="text-center pe-3" data-col="status">' . $statusBadge . '</td>
                      </tr>';
                }
            }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'        => true,
            'rows'      => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'      => "$from-$to/$total",
            'total'     => $total,
            'pdf_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url' => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir"
        ]);
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($id <= 0) throw new Exception('ID no válido');

            $repo = new ProductoRepository();
            $producto = $repo->getDetalleCompleto($id, $idEmpresa);

            if (!$producto) throw new Exception('Producto no encontrado');

            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            $enUso = $repo->estaUsadoEnDocumentos($id, $idEmpresa);

            echo json_encode([
                'ok' => true,
                'data' => [
                    'creado_at' => $fmt($producto['created_at'] ?? null),
                    'creado_por' => $producto['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at' => $fmt($producto['updated_at'] ?? null),
                    'actualizado_por' => $producto['actualizado_por_nombre'] ?? '—',
                    'inventarios' => $producto['inventarios'] ?? [],
                    'precios' => $producto['precios'] ?? [],
                    'componentes' => $producto['componentes'] ?? [],
                    'variantes' => $producto['variantes'] ?? [],
                    'costo_producto' => $producto['costo_producto'] ?? 0,
                    'stock_actual_general' => $producto['stock_actual_general'] ?? 0,
                    'stock_minimo' => $producto['stock_minimo'] ?? 0,
                    'stock_maximo' => $producto['stock_maximo'] ?? 0,
                    'imagen' => $producto['imagen'] ?? '',

                    'en_uso' => $enUso,
                    'homologaciones' => $producto['homologaciones'] ?? [],
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function searchAjaxSimple(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        $tipo = trim($_GET['tipo'] ?? '');
        $exclude = (int) ($_GET['exclude'] ?? 0);

        try {
            $repo = new ProductoRepository();
            $data = $repo->searchSimple($idEmpresa, $q, 10, $tipo, $exclude);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function uploadImage(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            if (empty($_FILES['image'])) throw new Exception('No se envió ninguna imagen.');

            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed)) throw new Exception('Formato de imagen no permitido.');
            if ($file['size'] > 2 * 1024 * 1024) throw new Exception('La imagen excede los 2MB.');

            $uploadDir = MVC_ROOT . '/public/uploads/productos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName = uniqid('prod_') . '.' . $ext;
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                echo json_encode(['ok' => true, 'path' => 'public/uploads/productos/' . $fileName]);
            } else {
                throw new Exception('Error al mover el archivo al servidor.');
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }


    public function catalogos(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        if (empty($_SESSION['id_empresa'])) {
            echo json_encode(['ok' => false, 'error' => 'No hay una sesión de empresa activa.']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $db = \App\core\Database::getConnection();

        $data = [
            'categorias' => [],
            'marcas' => [],
            'tipos_medida' => [],

            'tarifas_iva' => [],
            'bodegas' => []
        ];

        $unidadesRepo = new \App\repositories\modulos\UnidadesMedidaRepository();

        // 1. Asegurar medidas base
        try {
            $medidasService = new \App\Services\modulos\MedidasService();
            $medidasService->asegurarMedidasBase($idEmpresa, $idUsuario);
        } catch (\Throwable $e) {}

        // 2. Categorías
        try {
            $st = $db->prepare("SELECT id, nombre FROM categorias WHERE id_empresa = ? AND eliminado = false ORDER BY nombre ASC");
            $st->execute([$idEmpresa]);
            $data['categorias'] = $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        // 3. Marcas
        try {
            $st = $db->prepare("SELECT id, nombre FROM marcas WHERE id_empresa = ? AND eliminado = false ORDER BY nombre ASC");
            $st->execute([$idEmpresa]);
            $data['marcas'] = $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        // 4. Tipos de Medida
        try {
            $data['tipos_medida'] = $unidadesRepo->getTiposActivos($idEmpresa);
        } catch (\Throwable $e) {}



        // 6. Tarifas IVA (Globales, no filtran por empresa)
        try {
            $st = $db->query("SELECT id, tarifa AS nombre, porcentaje_iva AS porcentaje FROM tarifa_iva WHERE status = 1 ORDER BY tarifa ASC");
            $data['tarifas_iva'] = $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        // 7. Bodegas
        try {
            $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
            $data['bodegas'] = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);
        } catch (\Throwable $e) {}

        // 8. ICE de la empresa
        try {
            $data['ices'] = (new EmpresaRepository())->getIces($idEmpresa);
        } catch (\Throwable $e) {}

        // 9. Todas las unidades para componentes
        try {
            $data['unidades_todas'] = $unidadesRepo->getActivas($idEmpresa);
        } catch (\Throwable $e) {}

        // 10. IDs de medida default para tipo '01' (tipo medida "Unidad", unidad "Unidad")
        try {
            $medidasServiceDefault = new \App\Services\modulos\MedidasService();
            $data['medida_default'] = $medidasServiceDefault->getMedidaDefaultUnidad($idEmpresa);
        } catch (\Throwable $e) {}

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getUnidadesPorTipoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idTipo = (int) ($_GET['id_tipo'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($idTipo <= 0) throw new \Exception('ID de tipo no válido');

            $medidasService = new \App\services\modulos\MedidasService();
            $unidades = $medidasService->listarUnidadesPorTipo($idEmpresa, $idTipo);

            echo json_encode([
                'ok' => true,
                'data' => $unidades
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getSiguienteCodigoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipo = $_GET['tipo'] ?? '01'; // '01' o '02'

        try {
            $nextCodigo = $this->service->getSiguienteCodigo($idEmpresa, $tipo);
            echo json_encode(['ok' => true, 'codigo' => $nextCodigo]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                    font-size: 8pt;
                    table-layout: fixed;
                }

                th {
                    background: #f2f2f2;
                    border: 1px solid #ccc;
                    padding: 4px;
                    text-align: left;
                }

                td {
                    border: 1px solid #ccc;
                    padding: 4px;
                    overflow: hidden;
                    word-wrap: break-word;
                }

                .header {
                    text-align: center;
                    margin-bottom: 15px;
                    width: 100%;
                }

                h1 {
                    margin: 0;
                    font-size: 14pt;
                    color: #333;
                }

                h2 {
                    margin: 3px 0 0 0;
                    color: #666;
                    font-size: 10pt;
                    text-transform: uppercase;
                }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Catálogo de Productos</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%">Código</th>
                            <th style="width: 45%">Nombre / Razón</th>
                            <th style="width: 15%">Categoría</th>
                            <th style="width: 15%">Marca</th>
                            <th style="width: 10%">Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($r['codigo'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['nombre_categoria'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($r['nombre_marca'] ?? '-')) ?></td>
                                <td>$<?= number_format((float)($r['precio_base'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Productos_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Código', 'Código Barras', 'Nombre', 'Categoría', 'Marca', 'Precio Base', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string)($r['codigo'] ?? ''),
                    (string)($r['codigo_barras'] ?? ''),
                    (string)($r['nombre'] ?? ''),
                    (string)($r['nombre_categoria'] ?? ''),
                    (string)($r['nombre_marca'] ?? ''),
                    number_format((float)($r['precio_base'] ?? 0), 2),
                    ((int)($r['status'] ?? 1) === 1 ? 'Activo' : 'Inactivo')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Productos', $headers, $exportData, 'Lista de Productos', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Producto creado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new Exception('ID inválido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Producto actualizado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id_eliminar'] ?? 0);

        try {
            if ($id <= 0) throw new Exception('ID no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Producto eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteHomologacionAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new Exception('ID de homologación no válido.');
            $this->service->eliminarHomologacion($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Homologación eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'codigo'                => trim($_POST['codigo'] ?? ''),
            'nombre'                => trim($_POST['nombre'] ?? ''),
            'codigo_auxiliar'       => trim($_POST['codigo_auxiliar'] ?? ''),
            'codigo_barras'         => trim($_POST['codigo_barras'] ?? ''),
            'precio_base'           => empty($_POST['precio_base']) ? 0 : (float)$_POST['precio_base'],
            'tipo_produccion'       => trim($_POST['tipo_produccion'] ?? '01'),
            'tarifa_iva'            => empty($_POST['tarifa_iva']) ? 2 : (int)$_POST['tarifa_iva'],
            'id_medida'             => empty($_POST['id_medida']) ? null : (int)$_POST['id_medida'],
            'id_tipo_medida'        => empty($_POST['id_tipo_medida']) ? null : (int)$_POST['id_tipo_medida'],
            'status'                => (int) ($_POST['status'] ?? 1),
            'id_ice'                => empty($_POST['id_ice']) ? null : (int)$_POST['id_ice'],
            'valor_ice'             => empty($_POST['valor_ice']) ? null : (float)$_POST['valor_ice'],
            'codigo_ice'            => trim($_POST['codigo_ice'] ?? ''),
            'nombre_ice'            => trim($_POST['nombre_ice'] ?? ''),
            'inventariable'         => isset($_POST['inventariable']) && $_POST['inventariable'] == '1',

            'id_categoria'          => empty($_POST['id_categoria']) ? null : (int)$_POST['id_categoria'],
            'id_marca'              => empty($_POST['id_marca']) ? null : (int)$_POST['id_marca'],
            'imagen'                => trim($_POST['imagen'] ?? ''),
            'costo_producto'        => empty($_POST['costo_producto']) ? 0 : (float)$_POST['costo_producto'],
            'inventarios'           => !empty($_POST['inventarios']) ? json_decode($_POST['inventarios'], true) : [],
            'precios'               => !empty($_POST['precios']) ? json_decode($_POST['precios'], true) : [],
            'componentes'           => !empty($_POST['componentes']) ? json_decode($_POST['componentes'], true) : [],
            'variantes'             => !empty($_POST['variantes']) ? json_decode($_POST['variantes'], true) : [],
            'opciones'              => json_encode([
                'compra' => isset($_POST['opc_compra']) && $_POST['opc_compra'] === '1',
                'venta'  => isset($_POST['opc_venta'])  && $_POST['opc_venta']  === '1',
            ]),
        ];
    }
}
