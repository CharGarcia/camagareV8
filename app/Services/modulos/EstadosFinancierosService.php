<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\EstadosFinancierosRepository;
use App\Services\ReportService;
use Exception;
use TCPDF;

class EstadosFinancierosService
{
    private EstadosFinancierosRepository $repository;
    private ReportService $reportService;

    public function __construct(EstadosFinancierosRepository $repository, ReportService $reportService)
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
     * Procesa y estructura el Estado de Resultados
     */
    public function getEstadoResultados(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        $saldos = $this->repository->getSaldos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

        $ingresos = [];
        $costos = [];
        $gastos = [];

        $totalIngresos = 0.0;
        $totalCostos = 0.0;
        $totalGastos = 0.0;

        // 1. Calcular saldos directos
        foreach ($saldos as &$saldo) {
            $codigoStr = (string)$saldo['codigo'];
            $debe = (float)$saldo['total_debe'];
            $haber = (float)$saldo['total_haber'];

            if (str_starts_with($codigoStr, '4')) {
                $saldo['saldo_directo'] = $haber - $debe;
                $totalIngresos += $saldo['saldo_directo'];
            } elseif (str_starts_with($codigoStr, '5')) {
                $saldo['saldo_directo'] = $debe - $haber;
                $totalCostos += $saldo['saldo_directo'];
            } elseif (str_starts_with($codigoStr, '6')) {
                $saldo['saldo_directo'] = $debe - $haber;
                $totalGastos += $saldo['saldo_directo'];
            } else {
                $saldo['saldo_directo'] = 0;
            }
        }
        unset($saldo);

        // 2. Acumular saldos de hijos a padres (Rollup)
        foreach ($saldos as &$padre) {
            $suma = 0;
            $prefijo = $padre['codigo'] . '.';
            foreach ($saldos as $hijo) {
                if (str_starts_with((string)$hijo['codigo'], $prefijo)) {
                    $suma += $hijo['saldo_directo'];
                }
            }
            $padre['saldo_final'] = $padre['saldo_directo'] + $suma;
        }
        unset($padre);

        // 3. Filtrar por nivel y eliminar ceros
        foreach ($saldos as $saldo) {
            if ((int)$saldo['nivel'] > $nivelReporte) {
                continue;
            }
            if (round($saldo['saldo_final'], 2) == 0) {
                continue;
            }

            $codigoStr = (string)$saldo['codigo'];
            if (str_starts_with($codigoStr, '4')) {
                $ingresos[] = $saldo;
            } elseif (str_starts_with($codigoStr, '5')) {
                $costos[] = $saldo;
            } elseif (str_starts_with($codigoStr, '6')) {
                $gastos[] = $saldo;
            }
        }

        $utilidadBruta = $totalIngresos - $totalCostos;
        $utilidadNeta = $utilidadBruta - $totalGastos;

        return [
            'ingresos' => $ingresos,
            'costos' => $costos,
            'gastos' => $gastos,
            'totales' => [
                'ingresos' => $totalIngresos,
                'costos' => $totalCostos,
                'gastos' => $totalGastos,
                'utilidad_bruta' => $utilidadBruta,
                'utilidad_neta' => $utilidadNeta
            ]
        ];
    }

    /**
     * Procesa y estructura el Estado de Situación Financiera
     */
    public function getEstadoSituacionFinanciera(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        // Para la situación financiera necesitamos el saldo acumulado (inicial + movimiento)
        $saldos = $this->repository->getSaldos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

        $activos = [];
        $pasivos = [];
        $patrimonio = [];

        $totalActivos = 0.0;
        $totalPasivos = 0.0;
        $totalPatrimonio = 0.0;

        // 1. Calcular saldos directos
        foreach ($saldos as &$saldo) {
            $codigoStr = (string)$saldo['codigo'];
            $debe = (float)$saldo['inicial_debe'] + (float)$saldo['total_debe'];
            $haber = (float)$saldo['inicial_haber'] + (float)$saldo['total_haber'];

            if (str_starts_with($codigoStr, '1')) {
                $saldo['saldo_directo'] = $debe - $haber;
                $totalActivos += $saldo['saldo_directo'];
            } elseif (str_starts_with($codigoStr, '2')) {
                $saldo['saldo_directo'] = $haber - $debe;
                $totalPasivos += $saldo['saldo_directo'];
            } elseif (str_starts_with($codigoStr, '3')) {
                $saldo['saldo_directo'] = $haber - $debe;
                $totalPatrimonio += $saldo['saldo_directo'];
            } else {
                $saldo['saldo_directo'] = 0;
            }
        }
        unset($saldo);

        // 2. Acumular saldos de hijos a padres (Rollup)
        foreach ($saldos as &$padre) {
            $suma = 0;
            $prefijo = $padre['codigo'] . '.';
            foreach ($saldos as $hijo) {
                if (str_starts_with((string)$hijo['codigo'], $prefijo)) {
                    $suma += $hijo['saldo_directo'];
                }
            }
            $padre['saldo_final'] = $padre['saldo_directo'] + $suma;
        }
        unset($padre);

        // 3. Filtrar por nivel y eliminar ceros
        foreach ($saldos as $saldo) {
            if ((int)$saldo['nivel'] > $nivelReporte) {
                continue;
            }
            if (round($saldo['saldo_final'], 2) == 0) {
                continue;
            }

            $codigoStr = (string)$saldo['codigo'];
            if (str_starts_with($codigoStr, '1')) {
                $activos[] = $saldo;
            } elseif (str_starts_with($codigoStr, '2')) {
                $pasivos[] = $saldo;
            } elseif (str_starts_with($codigoStr, '3')) {
                $patrimonio[] = $saldo;
            }
        }

        // Obtener la Utilidad del Ejercicio
        $resultados = $this->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
        $utilidadNeta = $resultados['totales']['utilidad_neta'];
        
        $lblNeta = $utilidadNeta >= 0 ? 'Utilidad del Ejercicio' : 'Pérdida del Ejercicio';
        $patrimonio[] = [
            'codigo' => '',
            'nombre' => $lblNeta,
            'saldo_final' => $utilidadNeta,
            'nivel' => 1
        ];
        
        $totalPatrimonio += $utilidadNeta;
        $totalPasivoPatrimonio = $totalPasivos + $totalPatrimonio;

        return [
            'activos' => $activos,
            'pasivos' => $pasivos,
            'patrimonio' => $patrimonio,
            'totales' => [
                'activos' => $totalActivos,
                'pasivos' => $totalPasivos,
                'patrimonio' => $totalPatrimonio,
                'pasivo_patrimonio' => $totalPasivoPatrimonio
            ]
        ];
    }

    public function exportarSri(string $tipo, array $datos, string $empresaNombre, string $rangoFechas, string $rucEmpresa = ''): void
    {
        $agrupadoSri = [];
        $sectores = $tipo === 'resultados' ? ['ingresos', 'costos', 'gastos'] : ['activos', 'pasivos', 'patrimonio'];
        
        foreach ($sectores as $sec) {
            if (!isset($datos[$sec])) continue;
            foreach ($datos[$sec] as $item) {
                if ((int)$item['nivel'] === 5 && !empty($item['codigo_sri'])) {
                    $sri = $item['codigo_sri'];
                    if (!isset($agrupadoSri[$sri])) {
                        $agrupadoSri[$sri] = 0.0;
                    }
                    $agrupadoSri[$sri] += (float)$item['saldo_final'];
                }
            }
        }

        ksort($agrupadoSri);

        $filename = 'reporte_sri_imp_renta_' . ($rucEmpresa ?: 'sin_ruc') . '.xml';
        
        // Limpiar el búfer de salida
        if (ob_get_length()) ob_end_clean();
        
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
        echo "<detallesDeclaracion>\n";
        
        foreach ($agrupadoSri as $codSri => $valor) {
            echo "<detalle concepto=\"{$codSri}\">" . number_format($valor, 2, '.', '') . "</detalle>\n";
        }
        
        if (!empty($rucEmpresa)) {
            echo "<detalle concepto=\"80\">{$rucEmpresa}</detalle>\n";
        }
        
        echo "</detallesDeclaracion>\n";
        
        exit;
    }

    public function exportarExcel(string $tipo, array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $headers = ['Código', 'Cuenta', 'Saldo'];
        $dataExport = [];

        if ($tipo === 'resultados') {
            $dataExport[] = ['INGRESOS', '', ''];
            foreach ($datos['ingresos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL INGRESOS', $datos['totales']['ingresos']];
            $dataExport[] = ['', '', ''];
            
            $dataExport[] = ['COSTOS', '', ''];
            foreach ($datos['costos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL COSTOS', $datos['totales']['costos']];
            $dataExport[] = ['', '', ''];
            
            $lblBruta = $datos['totales']['utilidad_bruta'] >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA';
            $dataExport[] = ['', $lblBruta, $datos['totales']['utilidad_bruta']];
            $dataExport[] = ['', '', ''];

            $dataExport[] = ['GASTOS', '', ''];
            foreach ($datos['gastos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL GASTOS', $datos['totales']['gastos']];
            $dataExport[] = ['', '', ''];
            
            $lblNeta = $datos['totales']['utilidad_neta'] >= 0 ? 'UTILIDAD DEL EJERCICIO' : 'PÉRDIDA DEL EJERCICIO';
            $dataExport[] = ['', $lblNeta, $datos['totales']['utilidad_neta']];
            
            $this->reportService->exportToExcel('Estado_Resultados', $headers, $dataExport, 'Estado Resultados', "{$empresaNombre} - Estado de Resultados ({$rangoFechas})");
        } else {
            $dataExport[] = ['ACTIVOS', '', ''];
            foreach ($datos['activos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL ACTIVOS', $datos['totales']['activos']];
            $dataExport[] = ['', '', ''];

            $dataExport[] = ['PASIVOS', '', ''];
            foreach ($datos['pasivos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL PASIVOS', $datos['totales']['pasivos']];
            $dataExport[] = ['', '', ''];

            $dataExport[] = ['PATRIMONIO', '', ''];
            foreach ($datos['patrimonio'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL PATRIMONIO', $datos['totales']['patrimonio']];
            $dataExport[] = ['', '', ''];

            $dataExport[] = ['', 'TOTAL PASIVO + PATRIMONIO', $datos['totales']['pasivo_patrimonio']];

            $this->reportService->exportToExcel('Estado_Situacion_Financiera', $headers, $dataExport, 'Situacion Financiera', "{$empresaNombre} - Estado de Situación Financiera ({$rangoFechas})");
        }
    }

    public function exportarPdf(string $tipo, array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Contable');
        $pdf->SetAuthor($empresaNombre);
        $tituloReporte = $tipo === 'resultados' ? 'Estado de Resultados' : 'Estado de Situación Financiera';
        $pdf->SetTitle($tituloReporte);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, strtoupper($empresaNombre), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, strtoupper($tituloReporte), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, "Período: " . $rangoFechas, 0, 1, 'C');
        $pdf->Ln(5);

        $html = '<table border="1" cellpadding="4">
                    <thead>
                        <tr style="background-color:#f0f0f0; font-weight:bold;">
                            <th width="20%">Código</th>
                            <th width="60%">Cuenta</th>
                            <th width="20%" align="right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>';

        $formatoDinero = function($val) {
            return number_format((float)$val, 2, '.', ',');
        };

        if ($tipo === 'resultados') {
            $html .= '<tr><td colspan="3" style="font-weight:bold;">INGRESOS</td></tr>';
            foreach ($datos['ingresos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL INGRESOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['ingresos'])}</td></tr>";
            
            $html .= '<tr><td colspan="3" style="font-weight:bold;">COSTOS</td></tr>';
            foreach ($datos['costos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL COSTOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['costos'])}</td></tr>";
            
            $lblBruta = $datos['totales']['utilidad_bruta'] >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA';
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">{$lblBruta}</td><td align=\"right\" style=\"font-weight:bold; color: ".($datos['totales']['utilidad_bruta'] < 0 ? 'red' : 'black').";\">{$formatoDinero($datos['totales']['utilidad_bruta'])}</td></tr>";

            $html .= '<tr><td colspan="3" style="font-weight:bold;">GASTOS</td></tr>';
            foreach ($datos['gastos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL GASTOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['gastos'])}</td></tr>";
            
            $lblNeta = $datos['totales']['utilidad_neta'] >= 0 ? 'UTILIDAD DEL EJERCICIO' : 'PÉRDIDA DEL EJERCICIO';
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">{$lblNeta}</td><td align=\"right\" style=\"font-weight:bold; color: ".($datos['totales']['utilidad_neta'] < 0 ? 'red' : 'black').";\">{$formatoDinero($datos['totales']['utilidad_neta'])}</td></tr>";
        } else {
            $html .= '<tr><td colspan="3" style="font-weight:bold;">ACTIVOS</td></tr>';
            foreach ($datos['activos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL ACTIVOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['activos'])}</td></tr>";
            
            $html .= '<tr><td colspan="3" style="font-weight:bold;">PASIVOS</td></tr>';
            foreach ($datos['pasivos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL PASIVOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['pasivos'])}</td></tr>";
            
            $html .= '<tr><td colspan="3" style="font-weight:bold;">PATRIMONIO</td></tr>';
            foreach ($datos['patrimonio'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL PATRIMONIO</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['patrimonio'])}</td></tr>";
            
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL PASIVO + PATRIMONIO</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['pasivo_patrimonio'])}</td></tr>";
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $filename = "{$tituloReporte}_".date('YmdHis').".pdf";
        // Limpiar el output buffer
        if (ob_get_length()) ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;
    }

    public function generarMayorAuxiliar(
        int $idEmpresa,
        string $codigoCuenta,
        string $fechaInicio,
        string $fechaFin,
        ?int $idCentroCosto = null,
        ?int $idProyecto = null
    ): array {
        $movimientos = $this->repository->getMayorAuxiliar($idEmpresa, $codigoCuenta, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
        $saldoInicial = $this->repository->getSaldoInicialCuenta($idEmpresa, $codigoCuenta, $fechaInicio, $idCentroCosto, $idProyecto);

        // 1=Activo (Deudora), 2=Pasivo (Acreedora), 3=Patrimonio (Acreedora), 4=Ingresos (Acreedora), 5=Costos (Deudora), 6=Gastos (Deudora)
        $prefijoCuenta = $codigoCuenta !== '' ? $codigoCuenta[0] : '1';
        $naturalezaCuenta = in_array($prefijoCuenta, ['1', '5', '6']) ? 'deudora' : 'acreedora';

        $debeIni = (float)$saldoInicial['debe'];
        $haberIni = (float)$saldoInicial['haber'];
        $saldoArrastrado = $naturalezaCuenta === 'deudora' ? ($debeIni - $haberIni) : ($haberIni - $debeIni);

        $resultado = [];
        if ($debeIni !== 0.0 || $haberIni !== 0.0) {
            $resultado[] = [
                'id_asiento' => null,
                'fecha_asiento' => null,
                'numero_comprobante' => null,
                'concepto' => 'Saldo Inicial (arrastrado antes del rango)',
                'referencia_detalle' => null,
                'documento_referencia' => null,
                'debe' => $debeIni,
                'haber' => $haberIni,
                'codigo_cuenta' => $codigoCuenta,
                'saldo_acumulado' => $saldoArrastrado,
                'es_saldo_inicial' => true,
            ];
        }

        foreach ($movimientos as $mov) {
            $debe = (float)$mov['debe'];
            $haber = (float)$mov['haber'];
            $cod = (string)$mov['codigo_cuenta'];
            $prefijo = $cod !== '' ? $cod[0] : '1';
            $naturaleza = in_array($prefijo, ['1', '5', '6']) ? 'deudora' : 'acreedora';

            if ($naturaleza === 'deudora') {
                $saldoArrastrado += ($debe - $haber);
            } else {
                $saldoArrastrado += ($haber - $debe);
            }

            $mov['saldo_acumulado'] = $saldoArrastrado;
            $mov['es_saldo_inicial'] = false;
            $resultado[] = $mov;
        }

        return $resultado;
    }
}
