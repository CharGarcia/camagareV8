<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ProveedorRepository;
use App\Rules\modulos\ProveedorRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ProveedorService;
use App\models\IdentificadorCompradorVendedor;
use App\models\TipoEmpresa;
use App\models\BancoEcuador;
use App\models\Provincia;
use App\models\Ciudad;
use App\Services\SriIdentificationService;
use App\Services\GeocodingService;

class ProveedoresController extends BaseModuloController
{
    private ProveedorService $service;
    private const RUTA_MODULO = 'modulos/proveedores';

    public function __construct()
    {
        parent::__construct();
        $repository = new ProveedorRepository();
        $rules = new ProveedorRules();
        $logService = new LogSistemaService();
        $this->service = new ProveedorService($repository, $rules, $logService);
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
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'razon_social');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];

        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
            if (!empty($r['creado_at']))  $r['creado_at']  = date('d-m-Y H:i:s', strtotime($r['creado_at']));
            if (!empty($r['actualizado_at'])) $r['actualizado_at'] = date('d-m-Y H:i:s', strtotime($r['actualizado_at']));
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.proveedores.index', [
            'titulo'     => 'Proveedores',
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
            'fullWidth'  => true,
        ]);
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? $_GET['b'] ?? '');
        
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, 1, 20, 'razon_social', 'ASC', $idUsuarioFiltro);
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? $_GET['q'] ?? $_POST['b'] ?? $_POST['q'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'razon_social');
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
            echo '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-truck fs-3 d-block mb-2"></i>No se encontraron proveedores.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
                if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));

                $provData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $statusBadge = ($r['status'] ?? true)
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';

                echo '<tr class="proveedor-row" role="button" tabindex="0" data-row=\'' . $provData . '\' onclick="abrirModalProveedorEditar(this)">
                        <td class="ps-3" data-col="identificacion"><code class="text-secondary">' . htmlspecialchars($r['identificacion'] ?? '') . '</code></td>
                        <td data-col="nombre_tipo_id">' . htmlspecialchars($r['nombre_tipo_id'] ?? '—') . '</td>
                        <td class="fw-medium text-truncate" data-col="razon_social" style="max-width:300px">' . htmlspecialchars($r['razon_social'] ?? '') . '</td>
                        <td data-col="nombre_comercial" class="text-truncate" style="max-width:200px">' . htmlspecialchars($r['nombre_comercial'] ?? '—') . '</td>
                        <td data-col="email">' . htmlspecialchars($r['email'] ?? '—') . '</td>
                        <td data-col="telefono">' . htmlspecialchars($r['telefono'] ?? '—') . '</td>
                        <td data-col="direccion" class="text-truncate" style="max-width:200px">' . htmlspecialchars($r['direccion'] ?? '—') . '</td>
                        <td data-col="plazo" class="text-center">' . (int)($r['plazo'] ?? 0) . '</td>
                        <td data-col="relacionado" class="text-center">' . (isset($r['relacionado']) && ($r['relacionado'] === '1' || $r['relacionado'] === 't' || $r['relacionado'] === 1 || $r['relacionado'] === true) ? 'Sí' : 'No') . '</td>
                        <td data-col="nombre_banco">' . htmlspecialchars($r['nombre_banco'] ?? '—') . '</td>
                        <td data-col="nombre_tipo_empresa">' . htmlspecialchars($r['nombre_tipo_empresa'] ?? '—') . '</td>
                        <td data-col="nombre_provincia">' . htmlspecialchars($r['nombre_provincia'] ?? '—') . '</td>
                        <td data-col="nombre_ciudad">' . htmlspecialchars($r['nombre_ciudad'] ?? '—') . '</td>
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

    public function catalogos(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        if (empty($_SESSION['id_empresa'])) {
            echo json_encode(['ok' => false, 'error' => 'No hay una sesión de empresa activa.']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        try {
            $data = [
                'tipos_id'      => [],
                'tipos_empresa' => [],
                'bancos'        => [],
                'provincias'    => []
            ];

            // 1. Tipos ID (Proveedor = 2)
            $db = \App\core\Database::getConnection();
            $sqlTipos = "SELECT codigo, nombre FROM identificador_comprador_vendedor WHERE tipo = 1 AND status = 1 ORDER BY codigo ASC";
            $data['tipos_id'] = $db->query($sqlTipos)->fetchAll(\PDO::FETCH_ASSOC);

            // 2. Tipos Empresa
            $sqlEmp = "SELECT id, nombre FROM tipo_empresa WHERE status = 1 ORDER BY nombre ASC";
            $data['tipos_empresa'] = $db->query($sqlEmp)->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Bancos
            $sqlBancos = "SELECT id, nombre_banco AS nombre FROM bancos_ecuador WHERE status = 1 ORDER BY nombre_banco ASC";
            $data['bancos'] = $db->query($sqlBancos)->fetchAll(\PDO::FETCH_ASSOC);

            // 4. Provincias
            $modelProv = new Provincia();
            $data['provincias'] = $modelProv->getTodas();

            // 5. Retenciones SRI
            $sqlRetRenta = "SELECT id, codigo_ret || ' - ' || concepto_ret AS nombre, porcentaje_ret FROM retenciones_sri WHERE impuesto_ret = 'RENTA' AND status = 1 ORDER BY codigo_ret ASC";
            $data['retenciones_renta'] = $db->query($sqlRetRenta)->fetchAll(\PDO::FETCH_ASSOC);

            $sqlRetIva = "SELECT id, codigo_ret || ' - ' || concepto_ret AS nombre, porcentaje_ret FROM retenciones_sri WHERE impuesto_ret = 'IVA' AND status = 1 ORDER BY codigo_ret ASC";
            $data['retenciones_iva'] = $db->query($sqlRetIva)->fetchAll(\PDO::FETCH_ASSOC);

            // 6. Sustento Tributario
            $sqlSustento = "SELECT id, codigo || ' - ' || nombre AS nombre FROM sustento_tributario WHERE status = 1 ORDER BY codigo ASC";
            $data['sustento_tributario'] = $db->query($sqlSustento)->fetchAll(\PDO::FETCH_ASSOC);

            // 7. Formas de Pago (Egresos)
            $repoFP = new \App\repositories\modulos\FormaPagoRepository();
            $data['formas_pago'] = $repoFP->getFormasFiltradas($idEmpresa, 'EGRESO');

            // 8. Conceptos de Egreso
            $sqlConceptos = "SELECT id, nombre, comportamiento FROM empresa_opciones_ingreso_egreso WHERE id_empresa = ? AND aplica_egresos = TRUE AND eliminado = FALSE ORDER BY nombre ASC";
            $stConceptos = $db->prepare($sqlConceptos);
            $stConceptos->execute([$idEmpresa]);
            $data['conceptos_egreso'] = $stConceptos->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function tiposId(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $db = \App\core\Database::getConnection();
            $sql = "SELECT codigo, nombre FROM identificador_comprador_vendedor WHERE tipo = 1 AND status = 1 ORDER BY codigo ASC";
            $st = $db->query($sql);
            $filtrados = $st->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $filtrados ?: []]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function bancos(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $db = \App\core\Database::getConnection();
            $sql = "SELECT id, nombre_banco FROM bancos_ecuador WHERE status = 1 ORDER BY nombre_banco ASC";
            $st = $db->query($sql);
            $bancos = $st->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $bancos ?: []]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function tiposEmpresa(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $db = \App\core\Database::getConnection();
            $sql = "SELECT id, nombre FROM tipo_empresa WHERE status = 1 ORDER BY nombre ASC";
            $st = $db->query($sql);
            $tipos = $st->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $tipos ?: []]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

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

    public function getRetencionesSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $db = \App\core\Database::getConnection();
        $q = trim($_GET['q'] ?? '');
        $tipo = trim($_GET['tipo'] ?? 'RENTA'); // RENTA o IVA

        $params = [':tipo' => $tipo];
        $where = "WHERE impuesto_ret = :tipo AND status = 1";
        
        if ($q !== '') {
            $where .= " AND (codigo_ret ILIKE :q OR concepto_ret ILIKE :q)";
            $params[':q'] = "%$q%";
        }

        $sql = "SELECT id, codigo_ret, concepto_ret, porcentaje_ret, 
                       codigo_ret || ' - ' || concepto_ret AS label
                FROM retenciones_sri 
                $where 
                ORDER BY codigo_ret ASC 
                LIMIT 50";
        
        $st = $db->prepare($sql);
        $st->execute($params);
        $data = $st->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido');

            $repo = new ProveedorRepository();
            $prov = $repo->getDetalleCompleto($id, $idEmpresa);

            if (!$prov) throw new \Exception('Proveedor no encontrado');

            $stats = $repo->getEstadisticas($id, $idEmpresa);

            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            echo json_encode([
                'ok' => true,
                'data' => [
                    'creado_at' => $fmt($prov['created_at'] ?? $prov['creado_at'] ?? null),
                    'creado_por' => $prov['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at' => $fmt($prov['updated_at'] ?? $prov['actualizado_at'] ?? null),
                    'actualizado_por' => $prov['actualizado_por_nombre'] ?? '—',
                    'compras_realizadas' => $stats['documentos_recibidos'],
                    'stats' => $stats
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
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
            $data['id'] = $id; 
            echo json_encode(['ok' => true, 'msg' => 'Proveedor creado correctamente.', 'id' => $id, 'data' => $data]);
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
            if ($id <= 0) throw new \Exception('ID del proveedor no es válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Proveedor actualizado correctamente.']);
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
            if ($id <= 0) throw new \Exception('ID del proveedor no es válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Proveedor eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function geocodificar(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $direccion = trim($_POST['direccion'] ?? '');
        if ($direccion === '') {
            echo json_encode(['ok' => false, 'error' => 'Dirección vacía.']);
            exit;
        }

        try {
            $svc    = new GeocodingService();
            $result = $svc->geocodificar($direccion);

            if ($result === null) {
                echo json_encode(['ok' => false, 'error' => 'No se encontraron coordenadas para: "' . $direccion . '". Intente con una dirección más específica.']);
                exit;
            }

            $msg = null;
            if (!empty($result['aproximada'])) {
                $msg = 'Ubicación aproximada basada en: "' . $result['query_usada'] . '". Ajuste el marcador en el mapa si es necesario.';
            }

            echo json_encode([
                'ok'   => true,
                'data' => ['latitud' => $result['latitud'], 'longitud' => $result['longitud'], 'display_name' => $result['display_name']],
                'msg'  => $msg,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al geocodificar: ' . $e->getMessage()]);
        }
        exit;
    }

    public function mapa(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $repo      = new ProveedorRepository();

        $proveedores    = $repo->getConCoordenadas($idEmpresa);
        $sinCoordenadas = $repo->countSinCoordenadas($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.proveedores.mapa', [
            'titulo'         => 'Mapa de Proveedores',
            'perm'           => $this->getPermisos(),
            'rutaModulo'     => self::RUTA_MODULO,
            'proveedores'    => $proveedores,
            'sinCoordenadas' => $sinCoordenadas,
            'fullWidth'      => true,
        ]);
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'razon_social');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE PROVEEDORES';

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
                    <h2>Listado de Proveedores</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%">Identificación</th>
                            <th style="width: 35%">Razón Social</th>
                            <th style="width: 20%">Email</th>
                            <th style="width: 15%">Teléfono</th>
                            <th style="width: 15%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($r['identificacion'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['razon_social'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['email'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($r['telefono'] ?? '-')) ?></td>
                                <td><?= (($r['status'] ?? true) ? 'Activo' : 'Inactivo') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Proveedores_' . date('Ymd_His') . '.pdf', 'D');
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
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'razon_social');
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

            $headers = ['Identificación', 'Tipo ID', 'Razón Social', 'Email', 'Teléfono', 'Plazo (Días)', 'Relacionado', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string)($r['identificacion'] ?? ''),
                    (string)($r['nombre_tipo_id'] ?? $r['tipo_id_proveedor'] ?? ''),
                    (string)($r['razon_social'] ?? ''),
                    (string)($r['email'] ?? ''),
                    (string)($r['telefono'] ?? ''),
                    (string)($r['plazo'] ?? '0'),
                    (!empty($r['relacionado']) ? 'Sí' : 'No'),
                    (($r['status'] ?? true) ? 'Activo' : 'Inactivo')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Proveedores', $headers, $exportData, 'Listado Proveedores', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'razon_social'      => trim($_POST['razon_social'] ?? ''),
            'nombre_comercial'  => trim($_POST['nombre_comercial'] ?? ''),
            'tipo_id_proveedor' => trim($_POST['tipo_id_proveedor'] ?? ''),
            'identificacion'    => trim($_POST['identificacion'] ?? ''),
            'email'             => trim($_POST['email'] ?? ''),
            'direccion'         => trim($_POST['direccion'] ?? ''),
            'telefono'          => trim($_POST['telefono'] ?? ''),
            'tipo_empresa'      => trim($_POST['tipo_empresa'] ?? ''),
            'provincia'         => trim($_POST['provincia'] ?? ''),
            'ciudad'            => trim($_POST['ciudad'] ?? ''),
            'plazo'             => empty($_POST['plazo']) ? 0 : (int)$_POST['plazo'],
            'unidad_tiempo'     => empty($_POST['unidad_tiempo']) ? 'DIAS' : trim($_POST['unidad_tiempo']),
            'relacionado'       => isset($_POST['relacionado']) && $_POST['relacionado'] == '1',
            'id_banco'          => empty($_POST['id_banco']) ? null : (int)$_POST['id_banco'],
            'tipo_cta'          => empty($_POST['tipo_cta']) ? null : (int)$_POST['tipo_cta'],
            'numero_cta'        => empty($_POST['numero_cta']) ? null : trim($_POST['numero_cta']),
            'status'            => isset($_POST['status']) && $_POST['status'] == '1',
            'id_forma_pago_predeterminada' => empty($_POST['id_forma_pago_predeterminada']) ? null : (int)$_POST['id_forma_pago_predeterminada'],
            'tipo_operacion_bancaria_predeterminada' => empty($_POST['tipo_operacion_bancaria_predeterminada']) ? null : trim($_POST['tipo_operacion_bancaria_predeterminada']),
            'monto_maximo_auto_pago'       => empty($_POST['monto_maximo_auto_pago']) ? null : (float)$_POST['monto_maximo_auto_pago'],
            'id_retencion_renta'           => empty($_POST['id_retencion_renta']) ? null : (int)$_POST['id_retencion_renta'],
            'id_retencion_iva'             => empty($_POST['id_retencion_iva']) ? null : (int)$_POST['id_retencion_iva'],
            'id_sustento_tributario'       => empty($_POST['id_sustento_tributario']) ? null : (int)$_POST['id_sustento_tributario'],
            'id_egreso_concepto_predeterminado' => empty($_POST['id_egreso_concepto_predeterminado']) ? null : (int)$_POST['id_egreso_concepto_predeterminado'],
            'latitud'  => isset($_POST['latitud'])  && $_POST['latitud']  !== '' ? (float)$_POST['latitud']  : null,
            'longitud' => isset($_POST['longitud']) && $_POST['longitud'] !== '' ? (float)$_POST['longitud'] : null,
        ];
    }
}
