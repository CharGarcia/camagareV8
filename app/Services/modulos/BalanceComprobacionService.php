<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\BalanceComprobacionRepository;
use App\Services\ReportService;
use TCPDF;

class BalanceComprobacionService
{
    private BalanceComprobacionRepository $repository;
    private ReportService $reportService;

    public function __construct(BalanceComprobacionRepository $repository, ReportService $reportService)
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
     * Balance de comprobación del rango de fechas: debe/haber por cuenta (con rollup opcional
     * a cuentas agrupadoras hasta $nivelReporte) y su saldo neto (deudor o acreedor). Sin saldo
     * inicial: solo se calculan los movimientos dentro del rango filtrado.
     */
    public function generar(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        $saldos = $this->repository->getSaldos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

        // Cuadre general: suma de los movimientos reales (nivel de detalle), independiente del
        // nivel de agrupación elegido para la tabla.
        $totalDebeGeneral = 0.0;
        $totalHaberGeneral = 0.0;
        foreach ($saldos as $s) {
            $totalDebeGeneral += (float) $s['total_debe'];
            $totalHaberGeneral += (float) $s['total_haber'];
        }

        // Rollup: cada cuenta acumula el movimiento de sus hijas por prefijo de código.
        foreach ($saldos as &$cuenta) {
            $cuenta['debe_rollup'] = (float) $cuenta['total_debe'];
            $cuenta['haber_rollup'] = (float) $cuenta['total_haber'];
        }
        unset($cuenta);

        foreach ($saldos as &$padre) {
            $prefijo = $padre['codigo'] . '.';
            foreach ($saldos as $hijo) {
                if (str_starts_with((string) $hijo['codigo'], $prefijo)) {
                    $padre['debe_rollup'] += (float) $hijo['total_debe'];
                    $padre['haber_rollup'] += (float) $hijo['total_haber'];
                }
            }
        }
        unset($padre);

        $cuentas = [];
        foreach ($saldos as $cuenta) {
            if ((int) $cuenta['nivel'] > $nivelReporte) {
                continue;
            }

            $debe = round($cuenta['debe_rollup'], 2);
            $haber = round($cuenta['haber_rollup'], 2);
            if ($debe === 0.0 && $haber === 0.0) {
                continue;
            }

            $neto = $debe - $haber;
            $cuentas[] = [
                'id_cuenta' => (int) $cuenta['id_cuenta'],
                'codigo' => $cuenta['codigo'],
                'nombre' => $cuenta['nombre'],
                'nivel' => (int) $cuenta['nivel'],
                'debe' => $debe,
                'haber' => $haber,
                'saldo_deudor' => $neto > 0 ? $neto : 0.0,
                'saldo_acreedor' => $neto < 0 ? abs($neto) : 0.0,
            ];
        }

        $totalDebe = round($totalDebeGeneral, 2);
        $totalHaber = round($totalHaberGeneral, 2);

        return [
            'cuentas' => $cuentas,
            'totales' => [
                'debe' => $totalDebe,
                'haber' => $totalHaber,
                'saldo_deudor' => round(array_sum(array_column($cuentas, 'saldo_deudor')), 2),
                'saldo_acreedor' => round(array_sum(array_column($cuentas, 'saldo_acreedor')), 2),
            ],
            'cuadrado' => abs($totalDebe - $totalHaber) < 0.01,
        ];
    }

    public function exportarExcel(array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $headers = ['Código', 'Cuenta', 'Debe', 'Haber', 'Saldo Deudor', 'Saldo Acreedor'];
        $filas = [];

        foreach ($datos['cuentas'] as $cuenta) {
            $filas[] = [
                $cuenta['codigo'],
                $cuenta['nombre'],
                $cuenta['debe'],
                $cuenta['haber'],
                $cuenta['saldo_deudor'],
                $cuenta['saldo_acreedor'],
            ];
        }

        $filas[] = ['', 'TOTALES', $datos['totales']['debe'], $datos['totales']['haber'], $datos['totales']['saldo_deudor'], $datos['totales']['saldo_acreedor']];

        $this->reportService->exportToExcel('Balance_Comprobacion', $headers, $filas, 'Balance Comprobación', "{$empresaNombre} - Balance de Comprobación ({$rangoFechas})");
    }

    public function exportarPdf(array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Contable');
        $pdf->SetAuthor($empresaNombre);
        $pdf->SetTitle('Balance de Comprobación');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, strtoupper($empresaNombre), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'BALANCE DE COMPROBACIÓN', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Período: ' . $rangoFechas, 0, 1, 'C');
        $pdf->Ln(5);

        $formatoDinero = function ($val) {
            return number_format((float) $val, 2, '.', ',');
        };

        $html = '<table border="1" cellpadding="3">
                    <thead>
                        <tr style="background-color:#f0f0f0; font-weight:bold; font-size:9px;">
                            <th width="12%">Código</th>
                            <th width="38%">Cuenta</th>
                            <th width="12%" align="right">Debe</th>
                            <th width="12%" align="right">Haber</th>
                            <th width="13%" align="right">Saldo Deudor</th>
                            <th width="13%" align="right">Saldo Acreedor</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($datos['cuentas'] as $cuenta) {
            $html .= '<tr style="font-size:8px;">
                        <td>' . htmlspecialchars($cuenta['codigo']) . '</td>
                        <td>' . htmlspecialchars($cuenta['nombre']) . '</td>
                        <td align="right">' . $formatoDinero($cuenta['debe']) . '</td>
                        <td align="right">' . $formatoDinero($cuenta['haber']) . '</td>
                        <td align="right">' . $formatoDinero($cuenta['saldo_deudor']) . '</td>
                        <td align="right">' . $formatoDinero($cuenta['saldo_acreedor']) . '</td>
                    </tr>';
        }

        $html .= '<tr style="font-weight:bold; font-size:9px; background-color:#343a40; color:#ffffff;">
                    <td colspan="2" align="right">TOTALES</td>
                    <td align="right">' . $formatoDinero($datos['totales']['debe']) . '</td>
                    <td align="right">' . $formatoDinero($datos['totales']['haber']) . '</td>
                    <td align="right">' . $formatoDinero($datos['totales']['saldo_deudor']) . '</td>
                    <td align="right">' . $formatoDinero($datos['totales']['saldo_acreedor']) . '</td>
                </tr>';

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'Balance_Comprobacion_' . date('YmdHis') . '.pdf';
        if (ob_get_length()) ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;
    }
}
