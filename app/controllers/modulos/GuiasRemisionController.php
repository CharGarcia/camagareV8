<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\GuiaRemisionRepository;
use App\Rules\modulos\GuiaRemisionRules;
use App\Services\modulos\GuiaRemisionService;
use App\Services\LogSistemaService;
use App\models\Empresa;

class GuiasRemisionController extends BaseModuloController
{
    private GuiaRemisionService    $service;
    private GuiaRemisionRepository $repo;

    protected function getRutaModulo(): string
    {
        return 'modulos/guias_remision';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new GuiaRemisionRepository();
        $this->service = new GuiaRemisionService($this->repo, new GuiaRemisionRules(), new LogSistemaService());
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $perm       = $this->getPermisos();
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;
        $result = $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total  = $result['total'];

        $empresaModel     = new Empresa();
        $empresaData      = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        foreach ($establecimientos as $est) {
            foreach ($empresaModel->getPuntosEmision((int) $est['id']) as $p) {
                $puntos[] = $p;
            }
        }

        $this->viewWithLayout('layouts.main', 'modulos/guias_remision/index', [
            'titulo'          => 'Guías de Remisión',
            'perm'            => $perm,
            'rows'            => $result['rows'],
            'total'           => $total,
            'page'            => $page,
            'totalPages'      => (int) ceil($total / $perPage),
            'perPage'         => $perPage,
            'from'            => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'              => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'          => $buscar,
            'ordenCol'        => $ordenCol,
            'ordenDir'        => $ordenDir,
            'vistaConfig'     => $prefsVista,
            'base'            => BASE_URL,
            'rutaModulo'      => $this->getRutaModulo(),
            'empresa'         => $empresaData,
            'establecimientos'=> $establecimientos,
            'puntos'          => $puntos,
            'fullWidth'       => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? 'fecha_emision');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;
        $result = $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = (int) ceil($total / $perPage);
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-truck fs-3 d-block mb-2"></i>No se encontraron guías de remisión.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData     = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $numero      = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
                $fecha       = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $estado      = $r['estado'] ?? 'borrador';
                $estadoClass = match($estado) {
                    'autorizado'      => 'bg-success bg-opacity-10 text-success border-success',
                    'anulado'         => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'borrador'        => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                    'no_autorizado',
                    'devuelta'        => 'bg-warning bg-opacity-10 text-warning border-warning',
                    default           => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $estadoBadge = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst(str_replace('_', ' ', $estado)) . '</span>';

                echo "<tr class='gr-row' role='button' tabindex='0' data-row='{$rowData}' onclick='abrirModalGR(this)'>
                    <td class='ps-3' data-col='numero'><code class='text-secondary'>{$numero}</code></td>
                    <td data-col='fecha_emision'>{$fecha}</td>
                    <td class='fw-medium text-truncate' data-col='cliente_nombre' style='max-width:180px'>" . htmlspecialchars($r['cliente_nombre'] ?? '—') . "</td>
                    <td data-col='cliente_ruc'><small class='text-muted'>" . htmlspecialchars($r['cliente_ruc'] ?? '—') . "</small></td>
                    <td data-col='transportista_nombre' class='text-truncate' style='max-width:150px'>" . htmlspecialchars($r['transportista_nombre'] ?? '—') . "</td>
                    <td data-col='placa'>" . htmlspecialchars($r['placa'] ?? '—') . "</td>
                    <td data-col='motivo_traslado' class='text-truncate' style='max-width:150px'>" . htmlspecialchars($r['motivo_traslado'] ?? '—') . "</td>
                    <td data-col='fecha_inicio_transporte'>" . (!empty($r['fecha_inicio_transporte']) ? date('d-m-Y', strtotime($r['fecha_inicio_transporte'])) : '—') . "</td>
                    <td data-col='usuario_nombre'>" . htmlspecialchars($r['usuario_nombre'] ?? '—') . "</td>
                    <td class='text-center pe-3' data-col='estado'>{$estadoBadge}</td>
                </tr>";
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDis = ($page <= 1)           ? 'disabled' : '';
        $nextDis = ($page >= $totalPages) ? 'disabled' : '';
        echo "<button type='button' class='btn btn-outline-secondary' {$prevDis} onclick='GR_cambiarPagina(" . ($page - 1) . ")'><i class='bi bi-chevron-left'></i></button>
              <button type='button' class='btn btn-outline-secondary' {$nextDis} onclick='GR_cambiarPagina(" . ($page + 1) . ")'><i class='bi bi-chevron-right'></i></button>";
        $paginationHtml = ob_get_clean();

        $urlBase = BASE_URL . '/' . $this->getRutaModulo();
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "{$from}-{$to}/{$total}",
            'total'      => $total,
            'pdf_url'    => $urlBase . '/export-pdf?b=' . urlencode($buscar) . "&sort={$ordenCol}&dir={$ordenDir}",
            'excel_url'  => $urlBase . '/export-excel?b=' . urlencode($buscar) . "&sort={$ordenCol}&dir={$ordenDir}",
        ]);
        exit;
    }

    public function getGuiaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']); exit; }

        $cabecera = $this->repo->getPorId($id);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Guía no encontrada']); exit;
        }

        echo json_encode([
            'ok'             => true,
            'cabecera'       => $cabecera,
            'detalles'       => $this->repo->getDetalles($id),
            'info_adicional' => $this->repo->getInfoAdicional($id),
        ]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!is_array($data)) $data = $_POST;

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $id = !empty($data['id']) ? (int) $data['id'] : 0;

            if ($id > 0) {
                $this->requireActualizar();
                $this->service->actualizar($id, $data);
                echo json_encode(['ok' => true, 'mensaje' => 'Guía de remisión actualizada correctamente.', 'id' => $id]);
            } else {
                $this->requireCrear();
                $newId = $this->service->crear($data);
                echo json_encode(['ok' => true, 'mensaje' => 'Guía de remisión guardada correctamente.', 'id' => $newId]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

            $guia = $this->repo->getPorId($id);
            if (!$guia || (int)($guia['id_empresa'] ?? 0) !== $idEmpresa) {
                echo json_encode(['ok' => false, 'mensaje' => 'Guía no encontrada.']); exit;
            }

            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Guía de remisión eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Guía anulada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function enviarSriAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

            $sriService = new \App\Services\Sri\SriEnvioService();
            $resultado  = $sriService->enviarGuiaRemision($id, $idEmpresa, $idUsuario);

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

    public function exportarPdfAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $cabecera = $this->repo->getPorId($id);
            if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Guía no encontrada'; exit;
            }

            $detalles      = $this->repo->getDetalles($id);
            $infoAdicional = $this->repo->getInfoAdicional($id);
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'guia_remision');
            if ($plantilla) {
                $renderer->generar($plantilla, $cabecera, $detalles, [], $infoAdicional, $empresa);
            } else {
                (new \App\Services\modulos\GuiaRemisionPdfService())->generar($cabecera, $detalles, $infoAdicional, $empresa);
            }
        } catch (\Throwable $e) {
            http_response_code(500); echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function exportarXmlAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $cabecera = $this->repo->getPorId($id);
            if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Guía no encontrada'; exit;
            }

            $numero   = ($cabecera['establecimiento'] ?? '001') . '-'
                      . ($cabecera['punto_emision']   ?? '001') . '-'
                      . str_pad((string)($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
            $filename = 'guia_remision_' . $numero . '.xml';

            // Servir desde detalle_xml si existe
            if (!empty($cabecera['detalle_xml'])) {
                header('Content-Type: application/xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo $cabecera['detalle_xml'];
                exit;
            }

            // Fallback: regenerar y persistir
            $detalles      = $this->repo->getDetalles($id);
            $infoAdicional = $this->repo->getInfoAdicional($id);
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            }

            $xmlString = (new \App\Services\Xml\XmlGuiaRemisionService())
                ->generar($cabecera, $detalles, $infoAdicional, $empresa, $dirEstablecimiento);

            $this->repo->updateDetalleXml($id, $xmlString);

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $xmlString;
        } catch (\Throwable $e) {
            http_response_code(500); echo 'Error al generar XML: ' . $e->getMessage();
        }
        exit;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new \App\repositories\modulos\ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 10, 'nombre', 'ASC');
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getTransportistasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\TransportistaRepository();
        echo json_encode(['ok' => true, 'data' => $repo->buscarParaSelect($idEmpresa, $buscar)]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        // Solo tipo_produccion = '01' (productos físicos, no servicios tipo '02')
        $data = $repo->searchSimple($idEmpresa, $buscar, 20, '01');
        // Mapear campos para compatibilidad con el JS del módulo
        $rows = array_map(fn($p) => [
            'id'     => $p['id'],
            'codigo' => $p['codigo'],
            'nombre' => $p['nombre'],
        ], $data);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $empresaModel     = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos((int)$_SESSION['id_empresa']);
        echo json_encode(['ok' => true, 'data' => $establecimientos]);
        exit;
    }

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEst  = (int) ($_GET['id_establecimiento'] ?? 0);
        $puntos = (new Empresa())->getPuntosEmision($idEst);
        echo json_encode(['ok' => true, 'data' => $puntos]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $res = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Guía de remisión');
        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    public function getHistorialSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { echo json_encode(['ok' => false, 'data' => []]); exit; }

        $logs = (new \App\models\SriEnvioLog())->getPorComprobante('guia_remision', $id, $idEmpresa);
        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }

    public function getFacturasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new \App\repositories\modulos\FacturaVentaRepository();
        // Solo facturas autorizadas o vigentes
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'fecha_emision', 'DESC');
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getDetalleFacturaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID de factura requerido']); exit; }

        $repo = new \App\repositories\modulos\FacturaVentaRepository();
        $cab  = $repo->getPorId($id);

        if (!$cab || (int)$cab['id_empresa'] !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada']); exit;
        }

        $detalles = $repo->getDetalles($id);

        // Solo productos físicos (tipo_produccion = '01') — los servicios no se trasladan
        $detalles = array_values(array_filter($detalles, fn($d) => ($d['tipo_produccion'] ?? '') === '01'));

        echo json_encode([
            'ok'       => true,
            'cabecera' => $cab,
            'detalles' => $detalles
        ]);
        exit;
    }

    public function descargarXmlOriginalAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        $cab = $this->repo->getPorId($id);
        if (!$cab || (int)($cab['id_empresa'] ?? 0) !== $idEmpresa) {
            http_response_code(404); echo 'Guía no encontrada'; exit;
        }

        $xmlContent = $cab['detalle_xml'] ?? '';
        if (empty($xmlContent)) {
            http_response_code(404); echo 'Sin XML almacenado para esta guía de remisión'; exit;
        }

        $numero   = ($cab['establecimiento'] ?? '001') . '-'
                  . ($cab['punto_emision']   ?? '001') . '-'
                  . str_pad((string)($cab['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        $filename = 'guia_remision_' . $numero . '.xml';

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $xmlContent;
        exit;
    }

    public function countBorradoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT COUNT(*) FROM guias_remision_cabecera
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
