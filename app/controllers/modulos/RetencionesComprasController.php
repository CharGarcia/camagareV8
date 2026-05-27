<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\RetencionCompraService;
use App\repositories\modulos\RetencionCompraRepository;
use App\Rules\modulos\RetencionCompraRules;
use App\Services\LogSistemaService;
use App\models\Empresa;

class RetencionesComprasController extends BaseModuloController
{
    private RetencionCompraService    $service;
    private RetencionCompraRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/retenciones_compras';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new RetencionCompraRepository();
        $this->service    = new RetencionCompraService(
            $this->repository,
            new RetencionCompraRules(),
            new LogSistemaService()
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);

        $puntos = [];
        foreach ($establecimientos as $est) {
            $pts = $empresaModel->getPuntosEmision((int)$est['id']);
            foreach ($pts as $p) {
                $p['cod_establecimiento'] = $est['codigo'];
                $puntos[] = $p;
            }
        }

        $this->viewWithLayout('layouts.main', 'modulos/retenciones_compras/index', [
            'titulo'           => 'Retenciones en Compras',
            'perm'             => $perm,
            'rows'             => $result['rows'],
            'total'            => $total,
            'page'             => $page,
            'totalPages'       => $totalPages,
            'perPage'          => $perPage,
            'from'             => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'               => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'           => $buscar,
            'ordenCol'         => $ordenCol,
            'ordenDir'         => $ordenDir,
            'vistaConfig'      => $prefsVista,
            'base'             => BASE_URL,
            'rutaModulo'       => $this->getRutaModulo(),
            'empresa'          => $empresaData,
            'establecimientos' => $establecimientos,
            'puntos'           => $puntos,
            'fullWidth'        => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — búsqueda / paginación
    // ─────────────────────────────────────────────────────────────────────────

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fa-regular fa-file-lines fs-3 d-block mb-2"></i>No se encontraron retenciones.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData  = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $numero   = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '');
                $fecha    = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $estado   = $r['estado'] ?? 'borrador';
                $estadoClass = match ($estado) {
                    'autorizada'     => 'bg-success bg-opacity-10 text-success border-success',
                    'anulada'        => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'no_autorizada'  => 'bg-warning bg-opacity-10 text-warning border-warning',
                    'borrador'       => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                    default          => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $estadoBadge = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst(str_replace('_', ' ', $estado)) . '</span>';

                echo '<tr class="ret-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="window.RET_abrirModal(this)">
                        <td class="ps-3"><code>' . htmlspecialchars($numero) . '</code></td>
                        <td>' . $fecha . '</td>
                        <td class="fw-medium text-truncate" style="max-width:200px">' . htmlspecialchars($r['proveedor_nombre'] ?? '—') . '</td>
                        <td><small class="text-muted">' . htmlspecialchars($r['proveedor_ruc'] ?? '—') . '</small></td>
                        <td><small class="text-muted">' . htmlspecialchars($r['num_doc_sustento'] ?? '—') . '</small></td>
                        <td><small class="text-muted">' . htmlspecialchars($r['periodo_fiscal'] ?? '—') . '</small></td>
                        <td class="text-end fw-bold">$' . number_format((float)($r['total_retenido'] ?? 0), 2) . '</td>
                        <td class="text-center pe-3">' . $estadoBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" ' . $prevDisabled . ' onclick="window.RET_cambiarPagina(' . ($page - 1) . ')"><i class="fa-solid fa-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm" ' . $nextDisabled . ' onclick="window.RET_cambiarPagina(' . ($page + 1) . ')"><i class="fa-solid fa-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
        ]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — obtener una retención
    // ─────────────────────────────────────────────────────────────────────────

    public function getByIdAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            echo json_encode(['ok' => false, 'mensaje' => 'Retención no encontrada']);
            exit;
        }

        $lineas = $this->repository->getDetalle($id);

        echo json_encode(['ok' => true, 'cabecera' => $cabecera, 'lineas' => $lineas]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — guardar (crear/actualizar)
    // ─────────────────────────────────────────────────────────────────────────

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = isset($_POST['data']) ? json_decode($_POST['data'], true) : $_POST;

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            // Resolver establecimiento y punto de emisión desde el punto de emisión
            if (!empty($data['id_punto_emision'])) {
                $db = \App\core\Database::getConnection();
                $st = $db->prepare("
                    SELECT p.id_establecimiento, p.codigo_punto, e.codigo AS cod_establecimiento
                    FROM empresa_punto_emision p
                    JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                    WHERE p.id = ? LIMIT 1
                ");
                $st->execute([$data['id_punto_emision']]);
                $puntoRow = $st->fetch(\PDO::FETCH_ASSOC);

                if ($puntoRow) {
                    if (empty($data['id_establecimiento'])) $data['id_establecimiento'] = $puntoRow['id_establecimiento'];
                    if (empty($data['establecimiento']))    $data['establecimiento']    = $puntoRow['cod_establecimiento'];
                    if (empty($data['punto_emision']))      $data['punto_emision']      = $puntoRow['codigo_punto'];
                }
            }

            $idExistente = !empty($data['id']) ? (int)$data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                $id      = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Retención actualizada exitosamente.';
            } else {
                $this->requireCrear();
                $id      = $this->service->crear($data);
                $mensaje = 'Retención guardada exitosamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — eliminar
    // ─────────────────────────────────────────────────────────────────────────

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Retención eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — anular
    // ─────────────────────────────────────────────────────────────────────────

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Retención anulada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — autorizar SRI
    // ─────────────────────────────────────────────────────────────────────────

    public function autorizarSRIAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $envioService = new \App\Services\Sri\SriEnvioService();
            $resultado    = $envioService->enviarRetencionCompra($id, $idEmpresa, $idUsuario);

            echo json_encode([
                'ok'                  => $resultado['ok'],
                'estado'              => $resultado['estado'],
                'mensaje'             => $resultado['mensaje'],
                'numero_autorizacion' => $resultado['numero_autorizacion'] ?? '',
                'fecha_autorizacion'  => $resultado['fecha_autorizacion']  ?? '',
                'errores'             => $resultado['errores'] ?? [],
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — exportar PDF
    // ─────────────────────────────────────────────────────────────────────────

    public function exportPdfDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $cabecera = $this->repository->getPorIdSri($id, $idEmpresa);
            if (!$cabecera) {
                die('Retención no encontrada');
            }

            $lineas = $this->repository->getDetalle($id);

            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];

            $pdfService = new \App\Services\modulos\RetencionCompraPdfService();
            $pdfService->generar($cabecera, $lineas, $empresa);
        } catch (\Throwable $e) {
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — exportar XML
    // ─────────────────────────────────────────────────────────────────────────

    public function exportXmlDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $cabecera = $this->repository->getPorIdSri($id, $idEmpresa);
            if (!$cabecera) {
                die('Retención no encontrada');
            }

            $lineas  = $this->repository->getDetalle($id);
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];

            $xmlService = new \App\Services\Xml\XmlRetencionCompraService();
            $xmlString  = $xmlService->generar($cabecera, $lineas, $empresa);

            $numero = ($cabecera['establecimiento'] ?? '001') . '-' . ($cabecera['punto_emision'] ?? '001') . '-' . str_pad((string)$cabecera['secuencial'], 9, '0', STR_PAD_LEFT);
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="ret_' . $numero . '.xml"');
            echo $xmlString;
        } catch (\Throwable $e) {
            die('Error al generar XML: ' . $e->getMessage());
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — catálogos
    // ─────────────────────────────────────────────────────────────────────────

    public function getRetencionesSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $impuesto = $_GET['impuesto'] ?? null;
        $buscar   = trim($_GET['q'] ?? '');
        $fecha    = $_GET['fecha'] ?? null;

        $data = $this->repository->getRetencionesSri($impuesto, $buscar ?: null, $fecha);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function buscarComprasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $data = $this->repository->buscarComprasDisponibles($idEmpresa, $buscar);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto   = (int) ($_GET['id_punto_emision'] ?? $_GET['id_punto'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$idPunto) {
            echo json_encode(['ok' => false, 'mensaje' => 'Punto de emisión requerido']);
            exit;
        }

        try {
            $secService = new \App\Services\SecuencialService();
            $result     = $secService->obtenerSiguienteSecuencial($idPunto, 'Retenciones de compras');
            echo json_encode(array_merge(['ok' => true], $result));
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getHistorialSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $logs = (new \App\models\SriEnvioLog())->getPorComprobante('retencion_compra', $id, $idEmpresa);
        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }

    public function getAuditoriaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID no proporcionado']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $logService = new \App\Services\LogSistemaService();
        $logs = $logService->getHistorial('retenciones_compras', $id, $idEmpresa);

        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }

    public function descargarXmlAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID no válido'; exit; }

        $retencion = $this->repository->getPorIdSri($id, $idEmpresa);
        if (!$retencion) { http_response_code(404); echo 'Retención no encontrada'; exit; }

        $claveAcceso = $retencion['clave_acceso'] ?? $id;
        $filename    = 'retencion_' . $claveAcceso . '.xml';

        // Servir desde detalle_xml si existe
        if (!empty($retencion['detalle_xml'])) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $retencion['detalle_xml'];
            exit;
        }

        // Fallback: regenerar y persistir
        try {
            $lineas = $this->repository->getDetalle($id);

            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($retencion['id_establecimiento'])) {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$retencion['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            }

            $xml = (new \App\Services\Xml\XmlRetencionCompraService())
                ->generar($retencion, $lineas, $empresa, $dirEstablecimiento);

            $this->repository->updateDetalleXml($id, $xml);

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $xml;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error generando XML: ' . $e->getMessage();
        }
        exit;
    }

    public function getPorCompraAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $idCompra      = (int) ($_GET['id_compra'] ?? 0);
        $idLiquidacion = (int) ($_GET['id_liquidacion'] ?? 0);
        $idEmpresa     = (int) $_SESSION['id_empresa'];
        
        if (!$idCompra && !$idLiquidacion) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID de documento no válido']);
            return;
        }
        
        $rows = $this->repository->getPorCompra($idCompra, $idEmpresa, $idLiquidacion ?: null);
        echo json_encode(['ok' => true, 'rows' => $rows]);
    }

    public function countBorradoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT COUNT(*) FROM retencion_compra_cabecera
                    WHERE id_empresa = :id_empresa AND estado = 'borrador' AND eliminado = false";
            $st  = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            $count = (int) $st->fetchColumn();
            echo json_encode(['ok' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'count' => 0]);
        }
        exit;
    }
}
