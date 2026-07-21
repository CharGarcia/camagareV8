<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ClienteRepository;
use App\Rules\modulos\ClienteRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ClienteService;
use App\models\IdentificadorCompradorVendedor;
use App\models\Provincia;
use App\models\Vendedor;
use App\models\Ciudad;
use App\Services\SriIdentificationService;

class ClientesController extends BaseModuloController
{
    private ClienteService $service;
    private const RUTA_MODULO = 'modulos/clientes';

    public function __construct()
    {
        parent::__construct();
        // Inyección manual de dependencias siguiendo el patrón del sistema
        $repository = new ClienteRepository();
        $rules = new ClienteRules();
        $logService = new LogSistemaService();
        $this->service = new ClienteService($repository, $rules, $logService);
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

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];

        // Formatear fechas para el modal
        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
            if (!empty($r['creado_at']))  $r['creado_at']  = date('d-m-Y H:i:s', strtotime($r['creado_at']));
            if (!empty($r['actualizado_at'])) $r['actualizado_at'] = date('d-m-Y H:i:s', strtotime($r['actualizado_at']));
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        // Permiso para crear vendedores
        $permVend = $this->permisosModuloPorRuta('modulos/vendedores');

        $this->viewWithLayout('layouts.main', 'modulos.clientes.index', [
            'titulo'     => 'Clientes',
            'perm'       => $perm,
            'canCreateVend' => $permVend['crear'],
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
            'fullWidth'  => true,
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

        // Renderizar Filas
        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron clientes.</td></tr>';
        } else {
            foreach ($rows as $r) {
                // Formatear fechas para el modal
                if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
                if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
                if (!empty($r['creado_at']))  $r['creado_at']  = date('d-m-Y H:i:s', strtotime($r['creado_at']));
                if (!empty($r['actualizado_at'])) $r['actualizado_at'] = date('d-m-Y H:i:s', strtotime($r['actualizado_at']));

                $clienteData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $statusBadge = ((int)($r['status'] ?? 1) == 1)
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';

                echo '<tr class="cliente-row" role="button" tabindex="0" data-cliente=\'' . $clienteData . '\' onclick="abrirModalClienteEditar(this)">
                        <td class="ps-3" data-col="identificacion"><code class="text-secondary">' . htmlspecialchars($r['identificacion'] ?? '') . '</code></td>
                        <td data-col="nombre_tipo_id">' . htmlspecialchars($r['nombre_tipo_id'] ?? $r['tipo_id'] ?? '—') . '</td>
                        <td class="fw-medium text-truncate" data-col="nombre" style="max-width:250px">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                        <td data-col="email">' . htmlspecialchars($r['email'] ?? '—') . '</td>
                        <td data-col="telefono">' . htmlspecialchars($r['telefono'] ?? '—') . '</td>
                        <td data-col="direccion" class="text-truncate" style="max-width:200px">' . htmlspecialchars($r['direccion'] ?? '—') . '</td>
                        <td data-col="plazo" class="text-center">' . htmlspecialchars((string)($r['plazo'] ?? '0')) . '</td>
                        <td data-col="nombre_provincia">' . htmlspecialchars($r['nombre_provincia'] ?? '—') . '</td>
                        <td data-col="nombre_ciudad">' . htmlspecialchars($r['nombre_ciudad'] ?? '—') . '</td>
                        <td data-col="nombre_vendedor">' . htmlspecialchars($r['nombre_vendedor'] ?? '—') . '</td>
                        <td class="text-center pe-3" data-col="status">' . $statusBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        // Renderizar Paginación
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
            'can_create_vendedor' => $this->permisosModuloPorRuta('modulos/vendedores')['crear'],
            'pdf_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url' => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir"
        ]);
        exit;
    }

    // ─── AJAX: Tipos de identificación (tipo = 1 = Comprador) ────────────────
    public function tiposId(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $model = new IdentificadorCompradorVendedor();
            $todos = $model->getAll('codigo', 'ASC');
            $filtrados = array_values(array_filter($todos, fn($r) => (int)($r['tipo'] ?? 0) === 1 && (int)($r['status'] ?? 1) === 1));
            echo json_encode(['ok' => true, 'data' => $filtrados]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: Todas las provincias ──────────────────────────────────────────
    public function provincias(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $model = new Provincia();
            $data  = $model->getTodas();
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: Vendedores de la empresa ────────────────────────────────────────
    public function vendedores(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $model     = new Vendedor();
            $data      = $model->getActivosPorEmpresa($idEmpresa);

            // Verificar si el usuario actual puede crear vendedores
            $permVend  = $this->permisosModuloPorRuta('modulos/vendedores');

            echo json_encode([
                'ok' => true,
                'data' => $data,
                'can_create_vendedor' => $permVend['crear']
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: Formas de Pago SRI ─────────────────────────────────────────────
    public function formasPagoSri(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $model = new \App\models\FormaPagoSri();
            $data  = $model->getAll('codigo', 'ASC');
            $data  = array_values(array_filter($data, fn($r) => (int)($r['status'] ?? 1) === 1));
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: Catálogos para el modal ─────────────────────────────────────────
    public function catalogos(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        if (empty($_SESSION['id_empresa'])) {
            echo json_encode(['ok' => false, 'error' => 'No hay una sesión de empresa activa.']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $data = [
            'tipos_id' => [],
            'provincias' => [],
            'vendedores' => [],
            'formas_pago_sri' => []
        ];

        try {
            // 1. Tipos de Identificación
            $modelTipos = new IdentificadorCompradorVendedor();
            $todos = $modelTipos->getAll('codigo', 'ASC');
            $data['tipos_id'] = array_values(array_filter($todos, fn($r) => (int)($r['tipo'] ?? 0) === 1 && (int)($r['status'] ?? 1) === 1));

            // 2. Provincias
            $modelProv = new Provincia();
            $data['provincias'] = $modelProv->getTodas();

            // 3. Vendedores
            $permVend  = $this->permisosModuloPorRuta('modulos/vendedores');
            $data['can_create_vendedor'] = $permVend['crear'];
            $modelVend = new Vendedor();
            $data['vendedores'] = $modelVend->getActivosPorEmpresa($idEmpresa);

            // 4. Formas de Pago SRI
            $modelFp = new \App\models\FormaPagoSri();
            $dataFp = $modelFp->getAll('codigo', 'ASC');
            $data['formas_pago_sri'] = array_values(array_filter($dataFp, fn($r) => (int)($r['status'] ?? 1) === 1));

            // 5. Formas de Cobro (Ingresos) excluyendo Payphone (cobro online, no seleccionable manualmente)
            $repoFormas = new \App\repositories\modulos\FormaPagoRepository();
            $formasCobro = $repoFormas->getFormasFiltradas($idEmpresa, 'INGRESO');
            $data['formas_cobros_pagos'] = array_values(array_filter($formasCobro, function($f) {
                return strtoupper($f['tipo'] ?? '') !== 'PAYPHONE';
            }));

            // 6. Conceptos de Ingreso (Para auto-cobro)
            $db = \App\core\Database::getConnection();
            $stC = $db->prepare("SELECT id, nombre, comportamiento FROM empresa_opciones_ingreso_egreso WHERE id_empresa = ? AND aplica_ingresos = TRUE AND eliminado = FALSE ORDER BY nombre ASC");
            $stC->execute([$idEmpresa]);
            $data['conceptos_ingreso'] = $stC->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: Ciudades por provincia ────────────────────────────────────────
    public function ciudades(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        $codProv = trim($_GET['cod_prov'] ?? $_POST['cod_prov'] ?? '');
        if ($codProv === '') {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }
        try {
            $model = new Ciudad();
            $data  = $model->getPorProvincia($codProv);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }


    // ─── Vista: Mapa de Clientes ─────────────────────────────────────────────
    public function mapa(): void
    {
        $this->requireLeer();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $repository = new ClienteRepository();

        $clientes       = $repository->getConCoordenadas($idEmpresa);
        $sinCoordenadas = $repository->countSinCoordenadas($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.clientes.mapa', [
            'titulo'         => 'Mapa de Clientes',
            'perm'           => $this->getPermisos(),
            'rutaModulo'     => self::RUTA_MODULO,
            'clientes'       => $clientes,
            'totalClientes'  => count($clientes),
            'sinCoordenadas' => $sinCoordenadas,
            'fullWidth'      => true,
        ]);
    }

    // ─── AJAX: Geocodificación con Nominatim ────────────────────────────────
    public function geocodificar(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');

        $direccion = trim($_POST['direccion'] ?? $_GET['direccion'] ?? '');
        if ($direccion === '') {
            echo json_encode(['ok' => false, 'error' => 'Ingrese una dirección para geocodificar.']);
            exit;
        }

        try {
            $svc    = new \App\Services\GeocodingService();
            $result = $svc->geocodificar($direccion);
            if ($result === null) {
                echo json_encode([
                    'ok'    => false,
                    'error' => "No se encontraron resultados para: \"{$direccion}\". "
                             . "Intente con solo la ciudad y provincia, o verifique que el servidor tenga acceso a internet.",
                ]);
            } else {
                echo json_encode([
                    'ok'   => true,
                    'data' => $result,
                    'msg'  => !empty($result['aproximada'])
                        ? 'Ubicación aproximada (solo ciudad/provincia): ' . $result['display_name']
                        : null,
                ]);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: Consulta SRI ──────────────────────────────────────────────────
    public function consultarSri(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        $identificacion = trim($_POST['identificacion'] ?? $_GET['identificacion'] ?? '');
        if ($identificacion === '') {
            echo json_encode(['ok' => false, 'error' => 'Identificación vacía.']);
            exit;
        }
        try {
            $svc    = new SriIdentificationService();
            $result = $svc->consultar($identificacion, (int) $_SESSION['id_empresa']);
            echo json_encode($result);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── CRUD ────────────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Cliente creado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data = $this->recogerDatosFormulario();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) {
                throw new \Exception('ID de cliente no válido.');
            }
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Cliente actualizado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id_eliminar'] ?? 0);

        try {
            if ($id <= 0) {
                throw new \Exception('ID de cliente no válido para eliminar.');
            }
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Cliente eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── EXPORTACIONES ────────────────────────────────────────────────────────

    public function estadisticas(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID de cliente no válido.']);
            exit;
        }

        try {
            $stats = $this->service->getEstadisticas($id, $idEmpresa);
            echo json_encode(['ok' => true, 'data' => $stats]);
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
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE CLIENTES';

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
                    <h2>Listado de Clientes</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%">Identificación</th>
                            <th style="width: 35%">Razón Social / Nombre</th>
                            <th style="width: 25%">Correo</th>
                            <th style="width: 15%">Teléfono</th>
                            <th style="width: 10%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($r['identificacion'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['email'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($r['telefono'] ?? '-')) ?></td>
                                <td><?= ((int)($r['status'] ?? 1) === 1 ? 'Activo' : 'Inactivo') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Clientes_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            $_SESSION['clientes_msg'] = ['danger', 'Error al generar PDF: ' . $e->getMessage()];
            $this->redirect(BASE_URL . '/' . self::RUTA_MODULO);
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

            $headers = [
                'Identificación',
                'Tipo identificación',
                'Nombre / Razón Social',
                'Email',
                'Teléfono',
                'Plazo (Días)',
                'Provincia',
                'Ciudad',
                'Vendedor',
                'Fecha de creación',
                'Fecha de actualización',
                'Estado'
            ];

            $exportData = [];
            foreach ($rows as $r) {
                $fechaC = !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : '-';
                $fechaU = !empty($r['updated_at']) ? date('d-m-Y H:i:s', strtotime($r['updated_at'])) : '-';

                $exportData[] = [
                    (string)($r['identificacion'] ?? ''),
                    (string)($r['nombre_tipo_id'] ?? $r['tipo_id'] ?? ''),
                    (string)($r['nombre'] ?? ''),
                    (string)($r['email'] ?? ''),
                    (string)($r['telefono'] ?? ''),
                    (string)($r['plazo'] ?? '0'),
                    (string)($r['nombre_provincia'] ?? $r['provincia'] ?? ''),
                    (string)($r['nombre_ciudad'] ?? $r['ciudad'] ?? ''),
                    (string)($r['nombre_vendedor'] ?? '-'),
                    $fechaC,
                    $fechaU,
                    ((int)($r['status'] ?? 1) === 1 ? 'Activo' : 'Inactivo')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Clientes', $headers, $exportData, 'Listado Clientes', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                $_SESSION['clientes_msg'] = ['danger', 'Error al generar Excel: ' . $e->getMessage()];
                $this->redirect(BASE_URL . '/' . self::RUTA_MODULO);
            }
            exit;
        }
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'nombre'           => trim($_POST['nombre'] ?? ''),
            'tipo_id'          => trim($_POST['tipo_id'] ?? ''),
            'identificacion'   => trim($_POST['identificacion'] ?? ''),
            'telefono'         => trim($_POST['telefono'] ?? '') !== '' ? trim($_POST['telefono']) : null,
            'email'            => trim($_POST['email'] ?? ''),
            'direccion'        => trim($_POST['direccion'] ?? '') !== '' ? trim($_POST['direccion']) : null,
            'plazo'            => isset($_POST['plazo']) && $_POST['plazo'] !== '' ? (int)$_POST['plazo'] : 0,
            'provincia'        => trim($_POST['provincia'] ?? '') !== '' ? trim($_POST['provincia']) : null,
            'ciudad'           => trim($_POST['ciudad'] ?? '') !== '' ? trim($_POST['ciudad']) : null,
            'status'           => (int) ($_POST['status'] ?? 1),
            'id_vendedor'      => isset($_POST['id_vendedor']) && $_POST['id_vendedor'] !== '' ? (int)$_POST['id_vendedor'] : null,
            'id_forma_pago_sri' => isset($_POST['id_forma_pago_sri']) && $_POST['id_forma_pago_sri'] !== '' ? (int)$_POST['id_forma_pago_sri'] : null,
            'id_forma_cobro_predeterminada' => isset($_POST['id_forma_cobro_predeterminada']) && $_POST['id_forma_cobro_predeterminada'] !== '' ? (int)$_POST['id_forma_cobro_predeterminada'] : null,
            'monto_maximo_auto_cobro' => isset($_POST['monto_maximo_auto_cobro']) && $_POST['monto_maximo_auto_cobro'] !== '' ? (float)$_POST['monto_maximo_auto_cobro'] : null,
            'latitud'          => isset($_POST['latitud']) && $_POST['latitud'] !== '' ? (float)$_POST['latitud'] : null,
            'longitud'         => isset($_POST['longitud']) && $_POST['longitud'] !== '' ? (float)$_POST['longitud'] : null,
        ];
    }
}
