<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoRol;
use App\models\CatalogoNovedades;
use TCPDF;

/**
 * Genera el rol de pago individual (recibo por empleado) en PDF A4.
 */
class RolPagoPdfService
{
    /**
     * @param array  $lin     Línea del empleado (incluye 'cabecera' y 'rubros').
     * @param array  $empresa Datos de la empresa.
     * @param string $dest    'I' inline, 'D' descargar, 'S' string.
     */
    public function generarEmpleado(array $lin, array $empresa, string $dest = 'I')
    {
        $cab = $lin['cabecera'] ?? [];
        $mes = CatalogoNovedades::MESES[(int) ($cab['periodo_mes'] ?? 0)] ?? ($cab['periodo_mes'] ?? '');
        $periodo = trim($mes . ' ' . ($cab['periodo_anio'] ?? ''));
        $tipo = CatalogoRol::nombreTipo((string) ($cab['tipo_rol'] ?? ''));

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetTitle('Rol de Pago - ' . ($lin['nombres_apellidos'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();

        $empNom = htmlspecialchars((string) ($empresa['razon_social'] ?? $empresa['nombre_comercial'] ?? 'Empresa'));
        $empRuc = htmlspecialchars((string) ($empresa['ruc'] ?? ''));
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''));
        $m = fn($v) => number_format((float) $v, 2);

        $ing = array_values(array_filter($lin['rubros'] ?? [], fn($r) => $r['tipo'] === 'ingreso'));
        $egr = array_values(array_filter($lin['rubros'] ?? [], fn($r) => $r['tipo'] === 'egreso'));

        $html = '<style>
            .t { font-size:13px; font-weight:bold; }
            .sub { font-size:8px; color:#555; }
            .sect { background-color:#e9ecef; font-weight:bold; font-size:9px; padding:4px; }
            table.info td { font-size:8.5px; padding:3px 4px; }
            table.g th { background-color:#f1f3f5; font-size:8px; font-weight:bold; padding:3px 5px; border:0.5px solid #ccc; }
            table.g td { font-size:8.5px; padding:3px 5px; border:0.5px solid #ddd; }
            .tot { font-weight:bold; background-color:#f8f9fa; }
        </style>';

        $html .= '<table cellpadding="0"><tr>
            <td width="65%"><span class="t">' . $empNom . '</span><br><span class="sub">RUC: ' . $empRuc . '</span></td>
            <td width="35%" align="right"><span class="t">ROL DE PAGO</span><br><span class="sub">' . $h($tipo . ' — ' . $periodo) . '</span></td>
        </tr></table><br>';

        $html .= '<div class="sect">DATOS DEL EMPLEADO</div>';
        $html .= '<table class="info" cellpadding="0"><tr>'
            . '<td width="15%" style="color:#555;">Empleado</td><td width="50%"><b>' . $h($lin['nombres_apellidos']) . '</b></td>'
            . '<td width="12%" style="color:#555;">Cédula</td><td width="23%">' . $h($lin['identificacion']) . '</td></tr>'
            . '<tr><td style="color:#555;">Cargo</td><td>' . $h($lin['cargo'] ?? '—') . '</td>'
            . '<td style="color:#555;">Días</td><td>' . $h($lin['dias_trabajados']) . '</td></tr></table><br>';

        // Tabla de dos columnas: Ingresos | Egresos
        $filas = max(count($ing), count($egr));
        $html .= '<table class="g" cellpadding="0"><tr>'
            . '<th width="35%">Ingresos</th><th width="15%" align="right">Valor</th>'
            . '<th width="35%">Egresos</th><th width="15%" align="right">Valor</th></tr>';
        for ($k = 0; $k < $filas; $k++) {
            $a = $ing[$k] ?? null; $b = $egr[$k] ?? null;
            $html .= '<tr>'
                . '<td width="35%">' . ($a ? $h($a['concepto']) : '') . '</td>'
                . '<td width="15%" align="right">' . ($a ? $m($a['valor']) : '') . '</td>'
                . '<td width="35%">' . ($b ? $h($b['concepto']) : '') . '</td>'
                . '<td width="15%" align="right">' . ($b ? $m($b['valor']) : '') . '</td></tr>';
        }
        $html .= '<tr class="tot"><td width="35%">TOTAL INGRESOS</td><td width="15%" align="right">' . $m($lin['total_ingresos']) . '</td>'
            . '<td width="35%">TOTAL EGRESOS</td><td width="15%" align="right">' . $m($lin['total_egresos']) . '</td></tr>';
        $html .= '</table><br>';

        $html .= '<table class="g" cellpadding="0"><tr>'
            . '<td width="70%" align="right" class="tot" style="font-size:10px;">NETO A RECIBIR</td>'
            . '<td width="30%" align="right" class="tot" style="font-size:11px;">$ ' . $m($lin['neto']) . '</td></tr></table><br><br>';

        $html .= '<table cellpadding="0"><tr>'
            . '<td width="45%" align="center" style="border-top:0.5px solid #333; font-size:8px;">Recibí conforme</td>'
            . '<td width="10%"></td>'
            . '<td width="45%" align="center" style="border-top:0.5px solid #333; font-size:8px;">Empleador</td></tr></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $arch = 'Rol_' . preg_replace('/[^A-Za-z0-9]/', '_', (string) ($lin['identificacion'] ?? 'empleado')) . '.pdf';
        return $pdf->Output($arch, $dest);
    }
}
