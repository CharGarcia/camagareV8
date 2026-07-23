<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\MenuRepository;
use App\Rules\modulos\MenuRules;
use App\Services\LogSistemaService;
use App\Services\modulos\MenuService;
use Exception;

/**
 * Menú (carta del restaurante). Catálogo con fotos, independiente del CRUD
 * de Productos pero que puede vincularse a un producto existente (incluye
 * productos compuestos/combos — ver comentario en el modelo de datos).
 */
class MenuController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/menu';
    private MenuService $service;

    public function __construct()
    {
        parent::__construct();
        $repo = new MenuRepository();
        $rules = new MenuRules();
        $logService = new LogSistemaService();
        $this->service = new MenuService($repo, $rules, $logService);
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
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'orden');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.menu.index', [
            'titulo'      => 'Menú',
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
            'fullWidth'   => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'orden');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;
        $base = rtrim(BASE_URL ?? '', '/');

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-egg-fried fs-3 d-block mb-2"></i>No se encontraron ítems.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $thumb = !empty($r['imagen'])
                    ? '<img src="' . htmlspecialchars($base . '/' . $r['imagen']) . '" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">'
                    : '<div class="bg-light rounded d-flex align-items-center justify-content-center text-muted" style="width:40px;height:40px;"><i class="bi bi-image"></i></div>';
                $dispBadge = !empty($r['disponible'])
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Disponible</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">No disponible</span>';
                $destBadge = !empty($r['destacado'])
                    ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><i class="bi bi-star-fill"></i> Destacado</span>'
                    : '<span class="text-muted small">—</span>';
                $pct = (float) ($r['porcentaje_iva'] ?? 0);
                $precioConIva = (float) ($r['precio'] ?? 0) * (1 + $pct / 100);
                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="menu-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalMenuEditar(this)">
                        <td class="ps-3" data-col="foto">' . $thumb . '</td>
                        <td class="fw-medium" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                        <td class="text-center" data-col="categoria">' . htmlspecialchars($r['categoria_nombre'] ?? '') . '</td>
                        <td class="text-end" data-col="precio">$' . number_format((float) ($r['precio'] ?? 0), 2) . '</td>
                        <td class="text-center" data-col="iva">' . ($pct > 0 ? number_format($pct, 0) . '%' : '—') . '</td>
                        <td class="text-end" data-col="precio_con_iva">$' . number_format($precioConIva, 2) . '</td>
                        <td class="text-center" data-col="producto">' . htmlspecialchars($r['producto_nombre'] ?? '—') . '</td>
                        <td class="text-center" data-col="destacado">' . $destBadge . '</td>
                        <td class="text-center pe-3" data-col="disponible">' . $dispBadge . '</td>
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

        $this->json([
            'ok' => true, 'rows' => $rowsHtml, 'pagination' => $paginationHtml,
            'info' => "$from-$to/$total", 'total' => $total,
            'pdf_url'   => $base . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar),
            'excel_url' => $base . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar),
        ]);
    }

    public function store(): void
    {
        $this->requireCrear();
        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crear($data);
            $this->json(['ok' => true, 'msg' => 'Ítem creado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function update(): void
    {
        $this->requireActualizar();
        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = $this->recogerDatosFormulario();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new Exception('ID no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            $this->json(['ok' => true, 'msg' => 'Ítem actualizado correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id_eliminar'] ?? 0);

        try {
            if ($id <= 0) throw new Exception('ID no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'msg' => 'Ítem eliminado correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'id_producto'   => (int) ($_POST['id_producto'] ?? 0),
            'nombre'        => trim($_POST['nombre'] ?? ''),
            'descripcion'   => trim($_POST['descripcion'] ?? ''),
            'precio'        => (float) ($_POST['precio'] ?? 0),
            'imagen'        => trim($_POST['imagen'] ?? ''),
            'id_categoria'  => (int) ($_POST['id_categoria'] ?? 0),
            'id_tarifa_iva' => (int) ($_POST['id_tarifa_iva'] ?? 0),
            'disponible'    => isset($_POST['disponible']),
            'destacado'     => isset($_POST['destacado']),
            'orden'         => (int) ($_POST['orden'] ?? 0),
        ];
    }

    /** Subir foto del plato/combo — mismo patrón que ProductosController::uploadImage(). */
    public function uploadImageAjax(): void
    {
        $this->requireCrear();

        try {
            if (empty($_FILES['image'])) throw new Exception('No se envió ninguna imagen.');

            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed, true)) throw new Exception('Formato de imagen no permitido.');
            if ($file['size'] > 2 * 1024 * 1024) throw new Exception('La imagen excede los 2MB.');

            $uploadDir = MVC_ROOT . '/public/uploads/menu/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName = uniqid('menu_') . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                // Ruta relativa al directorio público (mismo criterio que productos.imagen).
                $this->json(['ok' => true, 'path' => 'uploads/menu/' . $fileName]);
            } else {
                throw new Exception('Error al mover el archivo al servidor.');
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Buscador de productos para el selector "Producto vinculado" del modal. */
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        try {
            $repo = new \App\repositories\modulos\ProductoRepository();
            $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta', true);
            $this->json(['ok' => true, 'data' => $result['rows']]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
    }

    // ─── Categorías del menú (propias — pestaña "Categorías" del modal) ───────

    public function getMenuCategoriasAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->json(['ok' => true, 'data' => $this->service->getMenuCategorias($idEmpresa)]);
    }

    public function crearMenuCategoriaAjax(): void
    {
        $this->requireCrear();
        try {
            $id = $this->service->crearMenuCategoria([
                'id_empresa'            => (int) $_SESSION['id_empresa'],
                'id_usuario'            => (int) $_SESSION['id_usuario'],
                'nombre'                => trim($_POST['nombre'] ?? ''),
                'id_estacion_impresion' => (int) ($_POST['id_estacion_impresion'] ?? 0),
                'orden'                 => (int) ($_POST['orden'] ?? 0),
            ]);
            $this->json(['ok' => true, 'msg' => 'Categoría creada.', 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function actualizarMenuCategoriaAjax(): void
    {
        $this->requireActualizar();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Categoría no válida.');
            $this->service->actualizarMenuCategoria($id, (int) $_SESSION['id_empresa'], [
                'id_usuario'            => (int) $_SESSION['id_usuario'],
                'nombre'                => trim($_POST['nombre'] ?? ''),
                'id_estacion_impresion' => (int) ($_POST['id_estacion_impresion'] ?? 0),
                'orden'                 => (int) ($_POST['orden'] ?? 0),
            ]);
            $this->json(['ok' => true, 'msg' => 'Categoría actualizada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function eliminarMenuCategoriaAjax(): void
    {
        $this->requireEliminar();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Categoría no válida.');
            $this->service->eliminarMenuCategoria($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Categoría eliminada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Tarifas de IVA activas, para el ítem cuando no tiene producto vinculado. */
    public function getTarifasIvaAjax(): void
    {
        $this->requireLeer();
        $st = $this->db->query("SELECT id, codigo, tarifa, porcentaje_iva FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC");
        $this->json(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    // ─── Estaciones de impresión (compartidas: Productos + Menú + KDS) ────────

    public function getEstacionesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->json(['ok' => true, 'data' => $this->service->getEstaciones($idEmpresa)]);
    }

    public function crearEstacionAjax(): void
    {
        $this->requireCrear();
        try {
            $id = $this->service->crearEstacion([
                'id_empresa' => (int) $_SESSION['id_empresa'],
                'id_usuario' => (int) $_SESSION['id_usuario'],
                'nombre'     => trim($_POST['nombre'] ?? ''),
                'tipo'       => trim($_POST['tipo'] ?? 'cocina'),
                'orden'      => (int) ($_POST['orden'] ?? 0),
            ]);
            $this->json(['ok' => true, 'msg' => 'Estación creada.', 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function actualizarEstacionAjax(): void
    {
        $this->requireActualizar();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Estación no válida.');
            $this->service->actualizarEstacion($id, (int) $_SESSION['id_empresa'], [
                'id_usuario' => (int) $_SESSION['id_usuario'],
                'nombre'     => trim($_POST['nombre'] ?? ''),
                'tipo'       => trim($_POST['tipo'] ?? 'cocina'),
                'orden'      => (int) ($_POST['orden'] ?? 0),
            ]);
            $this->json(['ok' => true, 'msg' => 'Estación actualizada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function eliminarEstacionAjax(): void
    {
        $this->requireEliminar();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Estación no válida.');
            $this->service->eliminarEstacion($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Estación eliminada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['b'] ?? '');
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $rows = $this->service->getListado($idEmpresa, $buscar, 1, 0, 'orden', 'ASC', $idUsuarioFiltro)['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $nombreEmpresa = $empresaModel->getPorId($idEmpresa)['nombre'] ?? 'MENÚ';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Menú</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 26%">Nombre</th>
                            <th style="width: 16%">Categoría</th>
                            <th style="width: 10%">Precio</th>
                            <th style="width: 8%">IVA</th>
                            <th style="width: 12%">Precio c/IVA</th>
                            <th style="width: 16%">Producto</th>
                            <th style="width: 6%">Destac.</th>
                            <th style="width: 6%">Disp.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $pct = (float) ($r['porcentaje_iva'] ?? 0);
                                $precioConIva = (float) ($r['precio'] ?? 0) * (1 + $pct / 100);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($r['nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['categoria_nombre'] ?? '')) ?></td>
                                <td>$<?= number_format((float) ($r['precio'] ?? 0), 2) ?></td>
                                <td><?= $pct > 0 ? number_format($pct, 0) . '%' : '—' ?></td>
                                <td>$<?= number_format($precioConIva, 2) ?></td>
                                <td><?= htmlspecialchars((string) ($r['producto_nombre'] ?? '')) ?></td>
                                <td><?= !empty($r['destacado']) ? 'Sí' : 'No' ?></td>
                                <td><?= !empty($r['disponible']) ? 'Sí' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Menu_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['b'] ?? '');
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $rows = $this->service->getListado($idEmpresa, $buscar, 1, 0, 'orden', 'ASC', $idUsuarioFiltro)['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $nombreEmpresa = $empresaModel->getPorId($idEmpresa)['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $headers = ['Nombre', 'Categoría', 'Precio', 'IVA %', 'Precio c/IVA', 'Producto vinculado', 'Destacado', 'Disponible'];
            $exportData = [];
            foreach ($rows as $r) {
                $pct = (float) ($r['porcentaje_iva'] ?? 0);
                $precioConIva = (float) ($r['precio'] ?? 0) * (1 + $pct / 100);
                $exportData[] = [
                    (string) ($r['nombre'] ?? ''),
                    (string) ($r['categoria_nombre'] ?? ''),
                    number_format((float) ($r['precio'] ?? 0), 2),
                    number_format($pct, 0),
                    number_format($precioConIva, 2),
                    (string) ($r['producto_nombre'] ?? ''),
                    !empty($r['destacado']) ? 'Sí' : 'No',
                    !empty($r['disponible']) ? 'Sí' : 'No',
                ];
            }

            (new \App\Services\ReportService())->exportToExcel('Menu', $headers, $exportData, 'Menú', $nombreEmpresa);
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }
}
