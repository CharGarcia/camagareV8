<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\DeclaracionRetencionesRepository;
use App\Services\modulos\DeclaracionRetencionesService;

class DeclaracionRetencionesController extends BaseModuloController
{
    private DeclaracionRetencionesRepository $repository;
    private DeclaracionRetencionesService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/declaracion_retenciones';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new DeclaracionRetencionesRepository();
        $this->service    = new DeclaracionRetencionesService($this->repository);
    }

    private function periodo(): array
    {
        $anio = (string) ($_GET['anio'] ?? date('Y', strtotime('first day of last month')));
        $mes  = str_pad((string) ($_GET['mes'] ?? date('m', strtotime('first day of last month'))), 2, '0', STR_PAD_LEFT);
        $fechaDesde = "{$anio}-{$mes}-01";
        $fechaHasta = date('Y-m-t', strtotime($fechaDesde));
        return [$anio, $mes, $fechaDesde, $fechaHasta];
    }

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        [$anio, $mes] = $this->periodo();

        $this->viewWithLayout('layouts.main', 'modulos/declaracion_retenciones/index', [
            'titulo'     => 'Declaración de Retenciones en la Fuente (Formulario 103 SRI)',
            'fullWidth'  => true,
            'perm'       => $this->getPermisos(),
            'anio'       => (int) $anio,
            'mes'        => $mes,
            'anios'      => $this->repository->getAniosConRetenciones($idEmpresa),
            'base'       => BASE_URL,
            'rutaModulo' => $this->getRutaModulo(),
        ]);
    }

    public function sincronizarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        [$anio, $mes, $fechaDesde, $fechaHasta] = $this->periodo();

        try {
            if ((int) ($_GET['sincronizar'] ?? 0) === 1) {
                $this->service->sincronizarPeriodo($idEmpresa, $anio, $mes, $idUsuario);
            }

            echo json_encode([
                'ok'                 => true,
                'resumen_completo'   => $this->service->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta),
                'detalle_documentos' => $this->repository->getDetalleDocumentos($idEmpresa, $fechaDesde, $fechaHasta),
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ==========================================================================
    // Declaración guardada: verificar duplicado, guardar, asiento y egreso
    // ==========================================================================

    public function verificarDeclaradoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $anio = (int) ($_GET['anio'] ?? 0);
        $mes  = (int) ($_GET['mes'] ?? 0);

        try {
            $declaracion = $this->service->verificarDeclarado($idEmpresa, $anio, $mes);
            echo json_encode(['ok' => true, 'declarado' => $declaracion !== null, 'declaracion' => $declaracion]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function guardarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $declaracion = $this->service->guardarDeclaracion([
                'id_empresa'    => $idEmpresa,
                'usuario_id'    => $idUsuario,
                'periodo_anio'  => (int) ($_POST['anio'] ?? 0),
                'periodo_mes'   => (int) ($_POST['mes'] ?? 0),
                'observaciones' => trim((string) ($_POST['observaciones'] ?? '')) ?: null,
            ]);
            echo json_encode(['ok' => true, 'declaracion' => $declaracion]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function generarAsientoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idDeclaracion = (int) ($_POST['id_declaracion'] ?? 0);

        try {
            $resultado = $this->service->generarAsientoDeclaracion($idDeclaracion, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true] + $resultado);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function generarEgresoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idDeclaracion = (int) ($_POST['id_declaracion'] ?? 0);

        try {
            $idEgreso = $this->service->generarEgreso($idDeclaracion, $idEmpresa, $idUsuario, [
                'id_proveedor'            => (int) ($_POST['id_proveedor'] ?? 0),
                'id_egreso_concepto'      => (int) ($_POST['id_egreso_concepto'] ?? 0),
                'id_forma_pago'           => (int) ($_POST['id_forma_pago'] ?? 0),
                'id_punto_emision'        => (int) ($_POST['id_punto_emision'] ?? 0),
                'fecha'                   => $_POST['fecha'] ?? date('Y-m-d'),
                'tipo_operacion_bancaria' => $_POST['tipo_operacion_bancaria'] ?? '',
                'numero_cheque'           => $_POST['numero_cheque'] ?? '',
                'fecha_cobro'             => $_POST['fecha_cobro'] ?? '',
            ]);
            echo json_encode(['ok' => true, 'id_egreso' => $idEgreso]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Datos auxiliares para el modal de "Generar egreso": conceptos, formas de pago y puntos de emisión. */
    public function datosEgresoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $empresaModel = new \App\models\Empresa();
            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            $puntos = [];
            if (!empty($establecimientos)) {
                $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
            }

            $fpRepo = new \App\repositories\modulos\FormaPagoRepository();
            $formasPago = $fpRepo->getFormasFiltradas($idEmpresa, 'EGRESO');

            $egRepo = new \App\repositories\modulos\EgresoRepository();
            $conceptos = $egRepo->getConceptosEgreso($idEmpresa);

            echo json_encode(['ok' => true, 'puntos_emision' => $puntos, 'formas_pago' => $formasPago, 'conceptos' => $conceptos]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');

        try {
            $repo = new \App\repositories\modulos\ProveedorRepository();
            $result = $repo->getListado($idEmpresa, $q, 1, 15, 'razon_social', 'ASC');
            echo json_encode(['ok' => true, 'data' => $result['rows']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Vista previa del siguiente secuencial de Egresos para el punto de emisión elegido en el modal. */
    public function getSecuencialEgresoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);

        try {
            $secService = new \App\Services\SecuencialService();
            $res = $secService->obtenerSiguienteSecuencial($idPunto, 'Egresos');
            echo json_encode(array_merge(['ok' => true], $res));
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Sugiere el siguiente número de cheque para la forma de pago elegida en el modal de egreso. */
    public function getUltimoChequeAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idForma = (int) ($_GET['id_forma_pago'] ?? 0);
        if ($idForma <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Forma de pago inválida']);
            exit;
        }

        try {
            $egSvc = new \App\Services\modulos\EgresoService(
                new \App\repositories\modulos\EgresoRepository(),
                new \App\Rules\modulos\EgresoRules(),
                new \App\Services\LogSistemaService()
            );
            $ultimo = $egSvc->getUltimoNumeroCheque($idForma);

            $siguiente = '';
            if ($ultimo && preg_match('/^(\d+)$/', $ultimo, $matches)) {
                $siguiente = str_pad((string) ((int) $matches[1] + 1), strlen($ultimo), '0', STR_PAD_LEFT);
            }

            echo json_encode(['ok' => true, 'ultimo' => $ultimo, 'siguiente' => $siguiente]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function actualizarCasilleroAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $nuevoCasillero = trim((string) ($_POST['casillero'] ?? ''));

        if ($id <= 0 || $nuevoCasillero === '') {
            echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos']);
            exit;
        }

        try {
            $this->repository->actualizarCasilleroManual($id, $nuevoCasillero);
            echo json_encode(['ok' => true, 'mensaje' => 'Casillero actualizado exitosamente']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    private function datosEmpresa(int $idEmpresa): array
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare("SELECT nombre, nombre_comercial, ruc FROM empresas WHERE id = ?");
        $st->execute([$idEmpresa]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private const MESES = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
        '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
    ];

    public function pdf(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        [$anio, $mes, $fechaDesde, $fechaHasta] = $this->periodo();

        $empresa = $this->datosEmpresa($idEmpresa);
        $resumen = $this->service->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta);

        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $money = fn($v) => number_format((float) $v, 2);
        $layout = $resumen['layout'];
        $valores = $resumen['valores'];

        $seccionTitulos = [
            'NACIONAL'        => 'POR PAGOS EFECTUADOS A RESIDENTES Y ESTABLECIMIENTOS PERMANENTES',
            'EXT_CONVENIO'    => 'PAGOS AL EXTERIOR — CON CONVENIO DE DOBLE TRIBUTACIÓN',
            'EXT_SINCONVENIO' => 'PAGOS AL EXTERIOR — SIN CONVENIO DE DOBLE TRIBUTACIÓN',
            'EXT_PARAISO'     => 'PAGOS AL EXTERIOR — PARAÍSOS FISCALES O REGÍMENES FISCALES PREFERENTES',
            'INFORMATIVO'     => 'VALORES A PAGAR Y FORMA DE PAGO (informativo)',
        ];

        ob_start(); ?>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width:100%; border-collapse:collapse; font-size:8pt; }
            th { background:#f2f2f2; border:1px solid #999; padding:3px; }
            td { border:1px solid #999; padding:3px; }
            .r { text-align:right; } .c { text-align:center; }
            .head { text-align:center; margin-bottom:8px; }
            .seccion td { background:#dfe7f3; font-weight:bold; }
            .bold td { font-weight:bold; }
            .cas { text-align:center; color:#555; }
        </style>
        <div class="head">
            <h3><?= htmlspecialchars($empresa['nombre_comercial'] ?: $empresa['nombre'] ?? '') ?></h3>
            <p>RUC: <?= htmlspecialchars($empresa['ruc'] ?? '') ?></p>
            <h4>FORMULARIO 103 — DECLARACIÓN DE RETENCIONES EN LA FUENTE DEL IMPUESTO A LA RENTA</h4>
            <p>Período: <?= self::MESES[$mes] ?? $mes ?> <?= $anio ?></p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>CONCEPTO</th>
                    <th class="cas">CAS.</th>
                    <th class="r">BASE IMPONIBLE</th>
                    <th class="cas">CAS.</th>
                    <th class="r">VALOR RETENIDO</th>
                </tr>
            </thead>
            <tbody>
                <?php $seccionActual = null; foreach ($layout as $f): ?>
                    <?php if ($f['seccion'] !== $seccionActual): $seccionActual = $f['seccion']; ?>
                        <tr class="seccion"><td colspan="5"><?= htmlspecialchars($seccionTitulos[$seccionActual] ?? $seccionActual) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($f['tipo'] === 'informativo'): ?>
                        <tr>
                            <td colspan="3"><?= htmlspecialchars($f['descripcion']) ?></td>
                            <td class="cas"><?= htmlspecialchars($f['casillero_valor'] ?? '') ?></td>
                            <td class="r">&nbsp;</td>
                        </tr>
                    <?php else: ?>
                        <tr class="<?= !empty($f['bold']) ? 'bold' : '' ?>">
                            <td><?= htmlspecialchars($f['descripcion']) ?></td>
                            <td class="cas"><?= htmlspecialchars($f['casillero_base'] ?? '') ?></td>
                            <td class="r"><?= $f['casillero_base'] ? '$' . $money($valores[$f['casillero_base']] ?? 0) : '' ?></td>
                            <td class="cas"><?= htmlspecialchars($f['casillero_valor'] ?? '') ?></td>
                            <td class="r"><?= $f['casillero_valor'] ? '$' . $money($valores[$f['casillero_valor']] ?? 0) : '' ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size:7pt; margin-top:10px;">Generado por el sistema el <?= date('d-m-Y H:i:s') ?>. Documento de apoyo para la declaración; no reemplaza la declaración presentada en el portal del SRI.</p>
        <?php
        $html = ob_get_clean();
        try {
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output("Formulario103_{$anio}_{$mes}.pdf", 'D');
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function excel(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        [$anio, $mes, $fechaDesde, $fechaHasta] = $this->periodo();

        $empresa = $this->datosEmpresa($idEmpresa);
        $resumen = $this->service->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta);
        $lineas  = $this->repository->getDetalleLineasRenta($idEmpresa, $fechaDesde, $fechaHasta);

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

            // ── HOJA 1: RESUMEN POR CASILLERO ──────────────────────────────
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Resumen 103');

            $sheet1->setCellValue('A1', mb_strtoupper($empresa['nombre_comercial'] ?: ($empresa['nombre'] ?? ''), 'UTF-8'));
            $sheet1->mergeCells('A1:E1');
            $sheet1->setCellValue('A2', 'Formulario 103 — ' . (self::MESES[$mes] ?? $mes) . " {$anio} — RUC " . ($empresa['ruc'] ?? ''));
            $sheet1->mergeCells('A2:E2');
            $sheet1->getStyle('A1:A2')->getFont()->setBold(true);

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D6EFD']],
            ];
            $sheet1->setCellValue('A4', 'Concepto');
            $sheet1->setCellValue('B4', 'Casillero base');
            $sheet1->setCellValue('C4', 'Base imponible');
            $sheet1->setCellValue('D4', 'Casillero valor');
            $sheet1->setCellValue('E4', 'Valor retenido');
            $sheet1->getStyle('A4:E4')->applyFromArray($headerStyle);
            $sheet1->getColumnDimension('A')->setWidth(65);
            $sheet1->getColumnDimension('C')->setWidth(16);
            $sheet1->getColumnDimension('E')->setWidth(16);

            $rowIdx = 5;
            $valores = $resumen['valores'];
            foreach ($resumen['layout'] as $f) {
                $sheet1->setCellValue('A' . $rowIdx, str_repeat('    ', (int) ($f['indent'] ?? 0)) . $f['descripcion']);
                if ($f['tipo'] !== 'informativo') {
                    if (!empty($f['casillero_base'])) {
                        $sheet1->setCellValueExplicit('B' . $rowIdx, $f['casillero_base'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet1->setCellValue('C' . $rowIdx, (float) ($valores[$f['casillero_base']] ?? 0));
                        $sheet1->getStyle('C' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    if (!empty($f['casillero_valor'])) {
                        $sheet1->setCellValueExplicit('D' . $rowIdx, $f['casillero_valor'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet1->setCellValue('E' . $rowIdx, (float) ($valores[$f['casillero_valor']] ?? 0));
                        $sheet1->getStyle('E' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }
                if (!empty($f['bold'])) $sheet1->getStyle("A{$rowIdx}:E{$rowIdx}")->getFont()->setBold(true);
                $rowIdx++;
            }

            // ── HOJA 2: DETALLE DE RETENCIONES DE COMPRA (RENTA) ───────────
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Detalle Retenciones Compra');
            $headers2 = ['Fecha emisión', 'Comprobante', 'Doc. sustento', 'Proveedor', 'RUC/CI', 'Código retención',
                         'Concepto', 'Base imponible', '% Retenido', 'Valor retenido', 'Casillero base', 'Casillero valor'];
            foreach ($headers2 as $i => $h) {
                $sheet2->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . '1', $h);
            }
            $sheet2->getStyle('A1:L1')->applyFromArray($headerStyle);

            $rowIdx = 2;
            foreach ($lineas as $l) {
                $comprobante = trim(($l['establecimiento'] ?? '') . '-' . ($l['punto_emision'] ?? '') . '-' . ($l['secuencial'] ?? ''), '-');
                $sheet2->setCellValue('A' . $rowIdx, $l['fecha_emision']);
                $sheet2->setCellValueExplicit('B' . $rowIdx, $comprobante, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('C' . $rowIdx, $l['num_doc_sustento'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet2->setCellValue('D' . $rowIdx, $l['proveedor_nombre'] ?? '');
                $sheet2->setCellValueExplicit('E' . $rowIdx, $l['proveedor_ruc'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('F' . $rowIdx, $l['codigo_retencion'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet2->setCellValue('G' . $rowIdx, $l['concepto'] ?? '');
                $sheet2->setCellValue('H' . $rowIdx, (float) ($l['base_imponible'] ?? 0));
                $sheet2->setCellValue('I' . $rowIdx, (float) ($l['porcentaje_retener'] ?? 0));
                $sheet2->setCellValue('J' . $rowIdx, (float) ($l['valor_retenido'] ?? 0));
                $sheet2->setCellValueExplicit('K' . $rowIdx, $l['casillero_base'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('L' . $rowIdx, $l['casillero_valor'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet2->getStyle('H' . $rowIdx . ':J' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
                $rowIdx++;
            }
            foreach (['A'=>14,'B'=>16,'C'=>16,'D'=>32,'E'=>14,'F'=>12,'G'=>34,'H'=>14,'I'=>10,'J'=>14,'K'=>10,'L'=>10] as $col => $w) {
                $sheet2->getColumnDimension($col)->setWidth($w);
            }

            $spreadsheet->setActiveSheetIndex(0);
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="Formulario103_' . $anio . '_' . $mes . '.xlsx"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }
}
