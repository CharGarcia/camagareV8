<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MayoresRepository;
use App\Services\ReportService;
use TCPDF;

class MayoresService
{
    private MayoresRepository $repository;
    private ReportService $reportService;

    public function __construct(MayoresRepository $repository, ReportService $reportService)
    {
        $this->repository = $repository;
        $this->reportService = $reportService;
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        return $this->repository->getAniosDisponibles($idEmpresa);
    }

    public function getCentrosCostoActivos(int $idEmpresa): array
    {
        return $this->repository->getCentrosCostoActivos($idEmpresa);
    }

    public function getProyectosActivos(int $idEmpresa): array
    {
        return $this->repository->getProyectosActivos($idEmpresa);
    }

    /**
     * Arma el Mayor agrupado por cuenta. El saldo acumulado arranca en 0 por cada cuenta
     * dentro del rango filtrado (sin arrastre de periodos anteriores: el saldo inicial se
     * registra manualmente como asiento de apertura, igual criterio que Estados Financieros).
     */
    public function generarMayor(int $idEmpresa, array $filtros): array
    {
        $movimientos = $this->repository->getMovimientos($idEmpresa, $filtros);

        $cuentas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($movimientos as $mov) {
            $idCuenta = (int) $mov['id_cuenta'];
            $codigo = (string) $mov['codigo_cuenta'];

            if (!isset($cuentas[$idCuenta])) {
                $cuentas[$idCuenta] = [
                    'id_cuenta' => $idCuenta,
                    'codigo' => $codigo,
                    'nombre' => $mov['nombre_cuenta'],
                    'movimientos' => [],
                    'saldo_arrastrado' => 0.0,
                    'subtotal_debe' => 0.0,
                    'subtotal_haber' => 0.0,
                ];
            }

            $debe = (float) $mov['debe'];
            $haber = (float) $mov['haber'];
            $prefijo = $codigo !== '' ? $codigo[0] : '1';
            // 1=Activo (Deudora), 2=Pasivo (Acreedora), 3=Patrimonio (Acreedora), 4=Ingresos (Acreedora), 5=Costos (Deudora), 6=Gastos (Deudora)
            $naturaleza = in_array($prefijo, ['1', '5', '6']) ? 'deudora' : 'acreedora';

            $cuentas[$idCuenta]['saldo_arrastrado'] += $naturaleza === 'deudora' ? ($debe - $haber) : ($haber - $debe);
            $cuentas[$idCuenta]['subtotal_debe'] += $debe;
            $cuentas[$idCuenta]['subtotal_haber'] += $haber;

            $cuentas[$idCuenta]['movimientos'][] = [
                'id_asiento' => $mov['id_asiento'],
                'fecha_asiento' => $mov['fecha_asiento'],
                'numero_comprobante' => $mov['numero_comprobante'],
                'documento_referencia' => $mov['documento_referencia'],
                'referencia_detalle' => $mov['referencia_detalle'],
                'concepto' => $mov['concepto'],
                'tercero' => $mov['nombre_entidad'],
                // Documento origen: con esto la vista enlaza "Documento Ref." a su modal.
                'modulo_documento' => $mov['modulo_documento'],
                'id_documento' => $mov['id_documento'],
                'debe' => $debe,
                'haber' => $haber,
                'saldo_acumulado' => $cuentas[$idCuenta]['saldo_arrastrado'],
            ];

            $totalDebe += $debe;
            $totalHaber += $haber;
        }

        $resultado = [];
        foreach ($cuentas as $c) {
            $resultado[] = [
                'id_cuenta' => $c['id_cuenta'],
                'codigo' => $c['codigo'],
                'nombre' => $c['nombre'],
                'movimientos' => $c['movimientos'],
                'subtotal_debe' => $c['subtotal_debe'],
                'subtotal_haber' => $c['subtotal_haber'],
                'saldo_final' => $c['saldo_arrastrado'],
            ];
        }

        return [
            'cuentas' => $resultado,
            'totales' => [
                'debe' => $totalDebe,
                'haber' => $totalHaber,
            ],
        ];
    }

    public function exportarExcel(array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $headers = ['Fecha', 'Comprobante', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'];
        $secciones = [];

        foreach ($datos['cuentas'] as $cuenta) {
            $filas = [];
            foreach ($cuenta['movimientos'] as $mov) {
                $filas[] = [
                    $mov['fecha_asiento'],
                    $mov['numero_comprobante'] ?: 'S/N',
                    $mov['documento_referencia'] ?: '',
                    $mov['tercero'] ?: '',
                    $mov['referencia_detalle'] ?: $mov['concepto'] ?: '',
                    $mov['debe'],
                    $mov['haber'],
                    $mov['saldo_acumulado'],
                ];
            }

            $secciones[] = [
                'titulo'  => $cuenta['codigo'] . ' - ' . $cuenta['nombre'],
                'filas'   => $filas,
                'resumen' => ['', '', '', '', 'SUBTOTAL ' . $cuenta['codigo'], $cuenta['subtotal_debe'], $cuenta['subtotal_haber'], $cuenta['saldo_final']],
            ];
        }

        $filaFinal = ['', '', '', '', 'TOTAL GENERAL', $datos['totales']['debe'], $datos['totales']['haber'], ''];

        $this->reportService->exportToExcelSeccionado('Mayores', $headers, $secciones, 'Mayores', "{$empresaNombre} - Mayores ({$rangoFechas})", $filaFinal);
    }

    public function exportarPdf(array $datos, string $empresaNombre, string $rangoFechas): void
    {
        // Horizontal: en vertical, con las 8 columnas (Documento Ref. incluida), el número de
        // documento y el nombre del tercero se parten en varias líneas por fila.
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Contable');
        $pdf->SetAuthor($empresaNombre);
        $pdf->SetTitle('Mayores');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, strtoupper($empresaNombre), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'MAYORES', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Período: ' . $rangoFechas, 0, 1, 'C');
        $pdf->Ln(3);

        $formatoDinero = function ($val) {
            return number_format((float) $val, 2, '.', ',');
        };

        // TCPDF no hereda el font-size del <tr> a las celdas: sin esto las tablas se dibujan al
        // tamaño base (10) y el número de documento y el tercero se parten en varias líneas.
        $pdf->SetFont('helvetica', '', 8);

        // TCPDF dimensiona las columnas fila por fila: el width hay que repetirlo en cada celda,
        // porque si solo va en el encabezado las filas de datos se reparten en partes iguales y
        // quedan desalineadas (y el número de documento y el tercero se cortan en varias líneas).
        $an = ['8%', '11%', '17%', '17%', '21%', '9%', '9%', '8%'];
        $anSubtotal = '74%'; // las 5 primeras columnas, que el subtotal une con colspan

        foreach ($datos['cuentas'] as $cuenta) {
            // El título de la cuenta va en su propia tabla: un colspan en la primera fila también
            // hace que TCPDF ignore los anchos de las columnas de abajo.
            $pdf->writeHTML('<table border="1" cellpadding="2">
                        <tr style="background-color:#e9ecef; font-weight:bold; font-size:9px;">
                            <td>' . htmlspecialchars($cuenta['codigo'] . ' - ' . $cuenta['nombre']) . '</td>
                        </tr>
                    </table>', true, false, true, false, '');

            $html = '<table border="1" cellpadding="2">
                        <thead>
                            <tr style="background-color:#f8f9fa; font-weight:bold; font-size:8px;">
                                <th width="' . $an[0] . '">Fecha</th>
                                <th width="' . $an[1] . '">Comprobante</th>
                                <th width="' . $an[2] . '">Documento Ref.</th>
                                <th width="' . $an[3] . '">Tercero</th>
                                <th width="' . $an[4] . '">Glosa</th>
                                <th width="' . $an[5] . '" align="right">Debe</th>
                                <th width="' . $an[6] . '" align="right">Haber</th>
                                <th width="' . $an[7] . '" align="right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>';

            foreach ($cuenta['movimientos'] as $mov) {
                $glosa = $mov['referencia_detalle'] ?: $mov['concepto'];
                $html .= '<tr style="font-size:8px;">
                            <td width="' . $an[0] . '">' . htmlspecialchars((string) $mov['fecha_asiento']) . '</td>
                            <td width="' . $an[1] . '">' . htmlspecialchars((string) ($mov['numero_comprobante'] ?: 'S/N')) . '</td>
                            <td width="' . $an[2] . '">' . htmlspecialchars((string) $mov['documento_referencia']) . '</td>
                            <td width="' . $an[3] . '">' . htmlspecialchars((string) $mov['tercero']) . '</td>
                            <td width="' . $an[4] . '">' . htmlspecialchars((string) $glosa) . '</td>
                            <td width="' . $an[5] . '" align="right">' . $formatoDinero($mov['debe']) . '</td>
                            <td width="' . $an[6] . '" align="right">' . $formatoDinero($mov['haber']) . '</td>
                            <td width="' . $an[7] . '" align="right">' . $formatoDinero($mov['saldo_acumulado']) . '</td>
                        </tr>';
            }

            $html .= '<tr style="font-weight:bold; font-size:8px;">
                        <td colspan="5" width="' . $anSubtotal . '" align="right">SUBTOTAL</td>
                        <td width="' . $an[5] . '" align="right">' . $formatoDinero($cuenta['subtotal_debe']) . '</td>
                        <td width="' . $an[6] . '" align="right">' . $formatoDinero($cuenta['subtotal_haber']) . '</td>
                        <td width="' . $an[7] . '" align="right">' . $formatoDinero($cuenta['saldo_final']) . '</td>
                    </tr>';

            $html .= '</tbody></table><br>';

            $pdf->writeHTML($html, true, false, true, false, '');
        }

        $htmlTotal = '<table border="1" cellpadding="3">
                        <tr style="font-weight:bold; font-size:9px; background-color:#343a40; color:#ffffff;">
                            <td width="76%" align="right">TOTAL GENERAL</td>
                            <td width="12%" align="right">' . $formatoDinero($datos['totales']['debe']) . '</td>
                            <td width="12%" align="right">' . $formatoDinero($datos['totales']['haber']) . '</td>
                        </tr>
                    </table>';
        $pdf->writeHTML($htmlTotal, true, false, true, false, '');

        $filename = 'Mayores_' . date('YmdHis') . '.pdf';
        if (ob_get_length()) ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;
    }
}
