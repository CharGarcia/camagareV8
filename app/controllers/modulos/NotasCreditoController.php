<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\Services\modulos\NotaCreditoService;
use App\repositories\modulos\NotaCreditoRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\models\Empresa;
use App\repositories\modulos\BodegaRepository;

class NotasCreditoController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/notas_credito';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new NotaCreditoRepository();
        $rules            = new \App\Rules\modulos\NotaCreditoRules();
        $logService       = new \App\Services\LogSistemaService();
        $this->service    = new NotaCreditoService($this->repository, $rules, $logService);
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        
        $puntos = [];
        foreach ($establecimientos as $est) {
            $pts = $empresaModel->getPuntosEmision((int) $est['id']);
            foreach ($pts as $p) {
                $p['cod_establecimiento'] = $est['codigo'];
                $puntos[] = $p;
            }
        }

        $bodegaRepo = new BodegaRepository();
        $bodegas = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);

        $total = $result['total'];
        $this->viewWithLayout('layouts.main', 'modulos/notas_credito/index', [
            'titulo'      => 'Notas de Crédito',
            'perm'        => $perm,
            'rows'        => $result['rows'],
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'base'        => BASE_URL,
            'rutaModulo'  => $this->getRutaModulo(),
            'empresa'     => $empresaData,
            'establecimientos' => $establecimientos,
            'puntos'      => $puntos,
            'bodegas'     => $bodegas,
            'fullWidth'   => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
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
            echo '<tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-minus fs-3 d-block mb-2"></i>No se encontraron notas de crédito.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData      = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $numero       = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
                $fecha        = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '�€”';
                $estado       = $r['estado'] ?? 'borrador';
                $estadoClass  = match ($estado) {
                    'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
                    'anulado'    => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'borrador'   => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                    default      => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $estadoBadge  = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

                echo '<tr class="nc-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="window.NC_abrirModalNC(this)">
                        <td class="ps-3"><code>' . $numero . '</code></td>
                        <td>' . $fecha . '</td>
                        <td class="fw-medium text-truncate" style="max-width:200px">' . htmlspecialchars($r['cliente_nombre'] ?? '�€”') . '</td>
                        <td><small class="text-muted">' . htmlspecialchars($r['cliente_ruc'] ?? '�€”') . '</small></td>
                        <td><small class="text-muted">' . htmlspecialchars($r['num_doc_modificado'] ?? '�€”') . '</small></td>
                        <td class="text-end">$' . number_format((float)($r['total_sin_impuestos'] ?? 0), 2) . '</td>
                        <td class="text-end text-danger">$' . number_format((float)($r['total_descuento'] ?? 0), 2) . '</td>
                        <td class="text-end fw-bold">$' . number_format((float)($r['importe_total'] ?? 0), 2) . '</td>
                        <td class="text-truncate" style="max-width:180px">' . htmlspecialchars($r['motivo'] ?? '') . '</td>
                        <td>' . htmlspecialchars($r['usuario_nombre'] ?? '�€”') . '</td>
                        <td class="text-center pe-3">' . $estadoBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="window.NC_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="window.NC_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
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

    public function getNcAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Nota de crédito no encontrada']);
            exit;
        }

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }

        echo json_encode([
            'ok'       => true,
            'cabecera' => $cabecera,
            'detalles' => $detalles
        ]);
        exit;
    }

    public function buscarFacturasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        $idCliente = (int) ($_GET['id_cliente'] ?? 0);

        $facturaRepo = new FacturaVentaRepository();
        $result = $facturaRepo->getListado($idEmpresa, $buscar, 1, 20, 'fecha_emision', 'DESC', null); 
        
        $rows = array_filter($result['rows'], function($f) use ($idCliente) {
            return ($f['estado'] === 'autorizado' || $f['estado'] === 'aprobado') && 
                   ($idCliente === 0 || (int)$f['id_cliente'] === $idCliente);
        });

        echo json_encode(['ok' => true, 'data' => array_values($rows)]);
        exit;
    }

    public function getFacturaDetallesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idFactura = (int) ($_GET['id_factura'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $facturaRepo = new FacturaVentaRepository();
        $factura = $facturaRepo->getPorId($idFactura);

        if (!$factura || (int)$factura['id_empresa'] !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada']);
            exit;
        }

        $detalles = $facturaRepo->getDetalles($idFactura);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $facturaRepo->getImpuestosDetalle((int)$d['id']);
        }

        echo json_encode([
            'ok'       => true,
            'cabecera' => $factura,
            'detalles' => $detalles
        ]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $empresaModel = new Empresa();
            $data['empresa_config'] = $empresaModel->getPorId($data['id_empresa']) ?? [];

            if (!empty($data['id_punto_emision'])) {
                $db = \App\core\Database::getConnection();
                $st = $db->prepare("
                    SELECT p.id_establecimiento, p.codigo_punto, e.codigo AS cod_establecimiento
                    FROM empresa_punto_emision p
                    JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                    WHERE p.id = ?
                    LIMIT 1
                ");
                $st->execute([$data['id_punto_emision']]);
                $puntoRow = $st->fetch(\PDO::FETCH_ASSOC);

                if ($puntoRow) {
                    if (empty($data['id_establecimiento'])) {
                        $data['id_establecimiento'] = $puntoRow['id_establecimiento'];
                    }
                    if (empty($data['establecimiento'])) {
                        $data['establecimiento'] = $puntoRow['cod_establecimiento'];
                    }
                    if (empty($data['punto_emision'])) {
                        $data['punto_emision'] = $puntoRow['codigo_punto'];
                    }
                }
            }



            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                $id = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Nota de crédito actualizada exitosamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->crear($data);
                $mensaje = 'Nota de crédito guardada exitosamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Nota de crédito eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Nota de crédito anulada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getTarifasIvaAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $this->repository->getTarifasIva()]);
        exit;
    }

    public function autorizarSRIAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $envioService = new \App\Services\Sri\SriEnvioService();
            $resultado    = $envioService->enviarNotaCredito($id, $idEmpresa, $idUsuario);

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

    public function exportPdfDoc(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int)$nc['id_empresa'] !== $idEmpresa) {
                die('Nota de crédito no encontrada');
            }

            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }

            $empresaModel = new Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa) ?? [];

            $pdfService = new \App\Services\modulos\NotaCreditoPdfService();
            $pdfService->generar($nc, $detalles, $empresa);
        } catch (\Throwable $e) {
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    public function exportXmlDoc(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int)$nc['id_empresa'] !== $idEmpresa) {
                die('Nota de crédito no encontrada');
            }

            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }

            $empresaModel = new Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa) ?? [];

            $xmlService = new \App\Services\Xml\XmlNotaCreditoService();
            $xmlString  = $xmlService->generar($nc, $detalles, $empresa);

            $numero = ($nc['establecimiento'] ?? '001') . '-' . ($nc['punto_emision'] ?? '001') . '-' . str_pad((string)$nc['secuencial'], 9, '0', STR_PAD_LEFT);
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="nc_' . $numero . '.xml"');
            echo $xmlString;
        } catch (\Throwable $e) {
            die('Error al generar XML: ' . $e->getMessage());
        }
        exit;
    }

    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado exitosamente.']);
        exit;
    }

    public function getHistorialSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $logs = (new \App\models\SriEnvioLog())->getPorComprobante('nota_credito', $id, $idEmpresa);
        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }
    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $idPt = (int) ($_GET['id_punto'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $sql = "SELECT MAX(secuencial) FROM notas_credito_cabecera WHERE id_punto_emision = ? AND id_empresa = ?";
        $db = \App\core\Database::getConnection();
        $st = $db->prepare($sql);
        $st->execute([$idPt, $idEmpresa]);
        $max = (int) $st->fetchColumn();
        
        echo json_encode(['ok' => true, 'secuencial' => $max + 1]);
        exit;
    }

    public function descargarXmlOriginalAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        $nc = $this->repository->getPorId($id);
        if (!$nc || (int)($nc['id_empresa'] ?? 0) !== $idEmpresa) {
            http_response_code(404); echo 'Nota de crédito no encontrada'; exit;
        }

        $numero   = ($nc['establecimiento'] ?? '001') . '-'
                  . ($nc['punto_emision']   ?? '001') . '-'
                  . str_pad((string)($nc['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        $filename = 'nc_' . $numero . '.xml';

        // Servir desde detalle_xml si existe
        if (!empty($nc['detalle_xml'])) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $nc['detalle_xml'];
            exit;
        }

        // Fallback: regenerar y persistir
        try {
            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($nc['id_establecimiento'])) {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$nc['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            }

            $xml = (new \App\Services\Xml\XmlNotaCreditoService())
                ->generar($nc, $detalles, [], $empresa, $dirEstablecimiento);

            $this->repository->updateDetalleXml($id, $xml);

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $xml;
        } catch (\Throwable $e) {
            http_response_code(500); echo 'Error generando XML: ' . $e->getMessage();
        }
        exit;
    }

    public function countBorradoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT COUNT(*) FROM notas_credito_cabecera
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