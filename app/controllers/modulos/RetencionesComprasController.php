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

        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($establecimientos as $est) {
            $pts = $empresaModel->getPuntosEmision((int)$est['id']);
            foreach ($pts as $p) {
                $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Retenciones de compras');
                if (empty($config['id'])) {
                    continue;
                }
                $p['cod_establecimiento'] = $est['codigo'];
                $puntos[] = $p;
            }
        }

        // Series con documentos REALES (para el filtro "Serie" del buscador); $puntos
        // arriba es solo "qué serie puedo usar en un documento NUEVO" (ver comentario
        // en el bucle anterior).
        $seriesFiltro = $this->repository->getSeriesDistintas($idEmpresa);

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
            'seriesFiltro'     => $seriesFiltro,
            'sustentos'        => $this->repository->getSustentosTributarios(),
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

                $estadoCorreo = $r['estado_correo'] ?? 'pendiente';
                $correoClass  = $estadoCorreo === 'enviado'
                    ? 'bg-success bg-opacity-10 text-success border-success'
                    : 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                $correoBadge  = '<span class="badge ' . $correoClass . ' border border-opacity-25">' . ucfirst($estadoCorreo) . '</span>';

                $tipoDocNombre = match ((string)($r['tipo_doc_sustento'] ?? '01')) {
                    '01' => 'Factura', '03' => 'Liquidación', '05' => 'Nota débito',
                    default => (string)($r['tipo_doc_sustento'] ?? '—'),
                };

                echo '<tr class="ret-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="window.RET_abrirModal(this)">
                        <td class="ps-3" data-col="numero"><code>' . htmlspecialchars($numero) . '</code></td>
                        <td data-col="fecha_emision">' . $fecha . '</td>
                        <td class="fw-medium text-truncate" data-col="proveedor_nombre" style="max-width:200px">' . htmlspecialchars($r['proveedor_nombre'] ?? '—') . '</td>
                        <td data-col="proveedor_ruc"><small class="text-muted">' . htmlspecialchars($r['proveedor_ruc'] ?? '—') . '</small></td>
                        <td data-col="tipo_doc_sustento"><small class="text-muted">' . htmlspecialchars($tipoDocNombre) . '</small></td>
                        <td data-col="num_doc_sustento"><small class="text-muted">' . htmlspecialchars($r['num_doc_sustento'] ?? '—') . '</small></td>
                        <td data-col="periodo_fiscal"><small class="text-muted">' . htmlspecialchars($r['periodo_fiscal'] ?? '—') . '</small></td>
                        <td class="text-end fw-bold" data-col="total_retenido">$' . number_format((float)($r['total_retenido'] ?? 0), 2) . '</td>
                        <td class="text-center" data-col="estado_correo">' . $correoBadge . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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

            $empresaModel = new Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            // El logo y las direcciones viven en empresa_establecimiento, no en empresas.
            // Enriquecer $empresa con los datos del establecimiento de la retención (mismo criterio que factura).
            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            $est = null;
            if (!empty($cabecera['id_establecimiento'])) {
                foreach ($establecimientos as $e) {
                    if ((int)$e['id'] === (int)$cabecera['id_establecimiento']) { $est = $e; break; }
                }
            }
            if ($est === null && !empty($establecimientos)) {
                $est = $establecimientos[0];
            }
            if ($est) {
                if (!empty($est['logo_ruta'])) {
                    $empresa['logo_ruta'] = $est['logo_ruta'];
                }
                $empresa['direccion_matriz']          = $empresa['direccion'] ?? '';
                $empresa['direccion_establecimiento'] = $est['direccion'] ?? '';
            }

            $this->generarPdfRetencionCompra($idEmpresa, $cabecera, $lineas, $empresa, 'D');
        } catch (\Throwable $e) {
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    /**
     * Genera el PDF de una retención en compras usando la plantilla activa
     * (tipo 'retencion_compra') del módulo Plantillas de Documentos; si la
     * empresa no tiene una configurada, usa el diseño original hardcodeado.
     */
    private function generarPdfRetencionCompra(int $idEmpresa, array $cabecera, array $lineas, array $empresa, string $outputDest)
    {
        $renderer  = new \App\Services\PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'retencion_compra');
        if ($plantilla) {
            return $renderer->generar($plantilla, $cabecera, $lineas, [], [], $empresa, $outputDest);
        }
        $pdfService = new \App\Services\modulos\RetencionCompraPdfService();
        if ($outputDest === 'S') {
            return $pdfService->generarBytes($cabecera, $lineas, $empresa);
        }
        return $pdfService->generar($cabecera, $lineas, $empresa);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — exportar Excel
    // ─────────────────────────────────────────────────────────────────────────

    public function exportExcelDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $cabecera = $this->repository->getPorIdSri($id, $idEmpresa);
            if (!$cabecera) { http_response_code(404); echo 'Retención no encontrada'; exit; }

            $lineas = $this->repository->getDetalle($id);

            $numero = str_pad((string)($cabecera['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
                    . str_pad((string)($cabecera['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
                    . str_pad((string)($cabecera['secuencial']      ?? ''),   9, '0', STR_PAD_LEFT);

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Retención');

            $sheet->setCellValue('A1', strtoupper((string)($cabecera['empresa_razon_social'] ?? '')));
            $sheet->mergeCells('A1:E1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'COMPROBANTE DE RETENCIÓN N.° ' . $numero);
            $sheet->mergeCells('A2:E2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($cabecera['fecha_emision']) ? date('d-m-Y', strtotime((string)$cabecera['fecha_emision'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('C3', 'Proveedor: ' . (string)($cabecera['proveedor_razon_social'] ?? ''));
            $sheet->setCellValue('A4', 'RUC/Identificación: ' . (string)($cabecera['proveedor_identificacion'] ?? ''));

            $tipoDoc = match ($cabecera['tipo_doc_sustento'] ?? '01') {
                '01' => 'Factura',
                '03' => 'Liquidación de Compra',
                '05' => 'Nota de Débito',
                default => (string)($cabecera['tipo_doc_sustento'] ?? ''),
            };
            $sheet->setCellValue('C4', 'Doc. Sustento: ' . $tipoDoc . ' ' . (string)($cabecera['num_doc_sustento'] ?? ''));

            $headerRow = 6;
            $headers = ['Impuesto', 'Código', 'Concepto', 'Base Imponible', '%', 'Valor Retenido'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . $headerRow, $h);
                $col++;
            }
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];
            $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->applyFromArray($headerStyle);

            $row = $headerRow + 1;
            $totRenta = 0.0; $totIva = 0.0; $totIsd = 0.0;
            foreach ($lineas as $l) {
                $codImp = strtoupper((string)($l['codigo_impuesto'] ?? '1'));
                $impuesto = match ($codImp) {
                    '1', 'RENTA' => 'Renta (IR)',
                    '2', 'IVA'   => 'IVA',
                    '6', 'ISD'   => 'ISD',
                    default      => $codImp,
                };
                $concepto = (string)($l['concepto'] ?? $l['sri_concepto'] ?? '');
                $valRet   = (float)($l['valor_retenido'] ?? 0);

                $sheet->setCellValueExplicit('A' . $row, $impuesto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, (string)($l['codigo_retencion'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C' . $row, $concepto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('D' . $row, (float)($l['base_imponible'] ?? 0));
                $sheet->setCellValue('E' . $row, (float)($l['porcentaje_retener'] ?? 0));
                $sheet->setCellValue('F' . $row, $valRet);
                $row++;

                if ($codImp === '1' || $codImp === 'RENTA')      $totRenta += $valRet;
                elseif ($codImp === '2' || $codImp === 'IVA')    $totIva   += $valRet;
                elseif ($codImp === '6' || $codImp === 'ISD')    $totIsd   += $valRet;
            }

            $sheet->getStyle('D' . ($headerRow + 1) . ':D' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('F' . ($headerRow + 1) . ':F' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');

            $totalGeneral = $totRenta + $totIva + $totIsd;
            if ($totalGeneral <= 0) {
                $totalGeneral = (float)($cabecera['total_retenido'] ?? 0);
            }

            $row += 1;
            $totales = ['Total Retenido Renta (IR)' => $totRenta, 'Total Retenido IVA' => $totIva];
            if ($totIsd > 0) $totales['Total Retenido ISD'] = $totIsd;
            $totales['TOTAL RETENIDO'] = $totalGeneral;

            foreach ($totales as $label => $valor) {
                $sheet->setCellValue('E' . $row, $label);
                $sheet->getStyle('E' . $row)->getFont()->setBold(true);
                $sheet->setCellValue('F' . $row, $valor);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'Retencion_' . $numero . '.xlsx';

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

            $numero = ($cabecera['establecimiento'] ?? '001') . '-' . ($cabecera['punto_emision'] ?? '001') . '-' . str_pad((string)$cabecera['secuencial'], 9, '0', STR_PAD_LEFT);

            // Servir el XML autorizado ya persistido si existe (igual que factura de venta).
            if (!empty($cabecera['detalle_xml'])) {
                header('Content-Type: application/xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="ret_' . $numero . '.xml"');
                echo $cabecera['detalle_xml'];
                exit;
            }

            // Fallback: regenerar el XML (comprobante aún no autorizado).
            $lineas  = $this->repository->getDetalle($id);
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];

            $docSustento        = $this->repository->getDatosDocSustento($cabecera);
            $dirEstablecimiento = $cabecera['estab_direccion'] ?? $cabecera['punto_direccion'] ?? null;

            $xmlService = new \App\Services\Xml\XmlRetencionCompraService();
            $xmlString  = $xmlService->generar($cabecera, $lineas, $empresa, $dirEstablecimiento, $docSustento);

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="ret_' . $numero . '.xml"');
            echo $xmlString;
        } catch (\Throwable $e) {
            die('Error al generar XML: ' . $e->getMessage());
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — enviar / reenviar por correo (igual que factura de venta)
    // ─────────────────────────────────────────────────────────────────────────

    public function reenviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            while (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $cabecera = $this->repository->getPorIdSri($id, $idEmpresa);
            if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
                while (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'Retención no encontrada.']);
                exit;
            }

            if (($cabecera['estado'] ?? '') !== 'autorizada') {
                while (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'La retención debe estar autorizada para enviar el correo.']);
                exit;
            }

            $lineas = $this->repository->getDetalle($id);

            $empresaModel = new Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            // Enriquecer con logo/direcciones del establecimiento (igual que exportPdfDoc)
            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            $est = null;
            if (!empty($cabecera['id_establecimiento'])) {
                foreach ($establecimientos as $e) {
                    if ((int)$e['id'] === (int)$cabecera['id_establecimiento']) { $est = $e; break; }
                }
            }
            if ($est === null && !empty($establecimientos)) $est = $establecimientos[0];
            if ($est) {
                if (!empty($est['logo_ruta'])) $empresa['logo_ruta'] = $est['logo_ruta'];
                $empresa['direccion_matriz']          = $empresa['direccion'] ?? '';
                $empresa['direccion_establecimiento'] = $est['direccion'] ?? '';
            }

            // PDF de la retención
            $pdfString = $this->generarPdfRetencionCompra($idEmpresa, $cabecera, $lineas, $empresa, 'S');

            // XML: usar el autorizado guardado; si no existe, regenerar
            $xmlString = $cabecera['detalle_xml'] ?? '';
            $numAut    = $cabecera['numero_autorizacion'] ?? '';
            if (empty($xmlString)) {
                $docSustento = $this->repository->getDatosDocSustento($cabecera);
                $dirEst      = $cabecera['estab_direccion'] ?? $cabecera['punto_direccion'] ?? null;
                $xmlString   = (new \App\Services\Xml\XmlRetencionCompraService())
                    ->generar($cabecera, $lineas, $empresa, $dirEst, $docSustento);
                try { $this->repository->updateDetalleXml($id, $xmlString); } catch (\Throwable $e) {}
            }

            // Nombre del destinatario para el cuerpo del correo
            if (empty($cabecera['proveedor_nombre'])) {
                $cabecera['proveedor_nombre'] = $cabecera['proveedor_razon_social'] ?? '';
            }

            $correosDestino = trim($_POST['correos'] ?? '');

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarSiAplica($idEmpresa, 'retencion_compra', $cabecera, $xmlString, $pdfString, $numAut, true, $correosDestino);

            while (ob_get_level() > 0) ob_end_clean();

            if ($enviado) {
                $db = \App\core\Database::getConnection();
                $db->prepare("UPDATE retencion_compra_cabecera SET estado_correo = 'enviado', updated_at = NOW() WHERE id = ?")->execute([$id]);
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración o el correo del destinatario.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            while (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — asiento contable de la retención (pestaña Asiento Contable)
    // ─────────────────────────────────────────────────────────────────────────

    public function getAsientoContableAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
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

            $idAsiento = (int) ($cabecera['id_asiento_contable'] ?? 0);
            $asiento   = $idAsiento > 0 ? $this->service->getAsientoRegistrado($idAsiento, $idEmpresa) : [];

            // Si no hay asiento vigente (no existe o está anulado), generarlo y enlazarlo ahora.
            if (empty($asiento) || ($asiento['estado'] ?? '') === 'anulado') {
                try {
                    $dataAsiento = $cabecera;
                    $dataAsiento['id_usuario'] = (int) $_SESSION['id_usuario'];
                    $this->service->procesarAsientoContable($id, $dataAsiento);

                    $cabecera  = $this->repository->getPorId($id, $idEmpresa);
                    $idAsiento = (int) ($cabecera['id_asiento_contable'] ?? 0);
                    $asiento   = $idAsiento > 0 ? $this->service->getAsientoRegistrado($idAsiento, $idEmpresa) : [];
                } catch (\Throwable $e) {
                    error_log('[RetencionCompra] Asiento no generado al consultar: ' . $e->getMessage());
                }
            }

            // Mostrar el asiento REGISTRADO (relación por id_asiento_contable).
            if (!empty($asiento)) {
                $detalles = array_map(static fn($d) => [
                    'cuenta_codigo' => $d['codigo_cuenta'] ?? '',
                    'cuenta_nombre' => $d['nombre_cuenta'] ?? '',
                    'debe'          => (float) ($d['debe'] ?? 0),
                    'haber'         => (float) ($d['haber'] ?? 0),
                ], $asiento['detalles'] ?? []);

                echo json_encode([
                    'ok'         => true,
                    'registrado' => true,
                    'numero'     => $asiento['numero_comprobante'] ?? '',
                    'estado'     => $asiento['estado'] ?? '',
                    'detalles'   => $detalles,
                ]);
                exit;
            }

            // No se pudo registrar (faltan cuentas / descuadre): mostrar el sugerido como referencia.
            $detalles = $this->service->obtenerAsientoSugerido($idEmpresa, $id);
            echo json_encode(['ok' => true, 'registrado' => false, 'detalles' => $detalles]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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

            $docSustento = $this->repository->getDatosDocSustento($retencion);

            $xml = (new \App\Services\Xml\XmlRetencionCompraService())
                ->generar($retencion, $lineas, $empresa, $dirEstablecimiento, $docSustento);

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
                    WHERE id_empresa = :id_empresa AND estado = 'borrador' AND eliminado = false AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
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
