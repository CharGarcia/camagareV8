<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Genera la línea de tiempo de trazabilidad de un producto en PDF (A4 horizontal).
 * Formateador puro (no accede a BD): recibe los datos ya armados por el Service.
 */
class TrazabilidadProductoPdfService
{
    public function generar(array $data, array $empresa, string $generadoPor, string $dest = 'I')
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor('CaMaGaRe');
        $pdf->SetTitle('Trazabilidad de producto');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterData([0, 0, 0], [200, 200, 200]);
        $pdf->setFooterFont(['helvetica', '', 7]);
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();

        $empNom = htmlspecialchars((string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'Sistema'));
        $empRuc = htmlspecialchars((string) ($empresa['ruc'] ?? ''));

        $producto = $data['producto'];
        $resumen  = $data['resumen'];
        $generado = htmlspecialchars($generadoPor);

        $html = '<style>
            h1 { font-size: 13px; font-weight: bold; }
            .sub { font-size: 8px; color: #555; }
            .meta { font-size: 8px; color: #444; }
            table.g th { background-color:#e9ecef; font-size:7.5px; font-weight:bold; padding:3px 4px; border:0.5px solid #ccc; }
            table.g td { font-size:7.5px; padding:2.5px 4px; border:0.5px solid #ddd; }
            .kpi { font-size:8px; }
            .aviso { color:#b00020; font-size:8px; }
        </style>';

        $html .= '<table cellpadding="0"><tr>'
            . '<td width="60%"><h1>' . $empNom . '</h1><span class="sub">RUC: ' . $empRuc . '</span></td>'
            . '<td width="40%" align="right"><h1>TRAZABILIDAD DE PRODUCTO</h1>'
            . '<span class="sub">Generado: ' . date('d-m-Y H:i:s') . ($generado !== '' ? ' &nbsp;·&nbsp; por ' . $generado : '') . '</span></td>'
            . '</tr></table>';

        $html .= '<table cellpadding="0"><tr><td class="meta">'
            . '<strong>' . htmlspecialchars($producto['codigo'] . ' - ' . $producto['nombre']) . '</strong>'
            . '</td></tr></table><br>';

        $html .= '<table cellpadding="0"><tr>'
            . '<td class="kpi" width="20%"><strong>Stock actual:</strong> ' . number_format((float) $resumen['stock_actual'], 2) . '</td>'
            . '<td class="kpi" width="20%"><strong>Entradas:</strong> ' . number_format((float) $resumen['total_entradas'], 2) . '</td>'
            . '<td class="kpi" width="20%"><strong>Salidas:</strong> ' . number_format((float) $resumen['total_salidas'], 2) . '</td>'
            . '<td class="kpi" width="20%"><strong>Costo prom.:</strong> ' . number_format((float) $resumen['costo_promedio'], 4) . '</td>'
            . '<td class="kpi" width="20%"><strong>Último mov.:</strong> ' . htmlspecialchars((string) ($resumen['ultimo_movimiento'] ?? '-')) . '</td>'
            . '</tr></table><br>';

        if (!empty($data['truncado'])) {
            $html .= '<div class="aviso">Nota: se muestran los movimientos más recientes dentro del límite de exportación. Acote el rango de fechas para ver el listado completo.</div><br>';
        }

        $html .= '<table class="g" cellpadding="0"><thead><tr>'
            . '<th width="12%">Fecha</th>'
            . '<th width="16%">Evento</th>'
            . '<th width="12%">Documento</th>'
            . '<th width="16%">Contraparte</th>'
            . '<th width="9%" align="center">Cantidad</th>'
            . '<th width="9%" align="center">Saldo</th>'
            . '<th width="9%">Lote</th>'
            . '<th width="9%">Bodega</th>'
            . '<th width="8%">Usuario</th>'
            . '</tr></thead><tbody>';

        foreach ($data['eventos'] as $e) {
            $cantidad = isset($e['cantidad']) ? number_format((float) $e['cantidad'], 2) : '-';
            $saldo    = isset($e['stock_posterior']) ? number_format((float) $e['stock_posterior'], 2) : '-';
            $html .= '<tr>'
                . '<td>' . htmlspecialchars((string) $e['fecha']) . '</td>'
                . '<td>' . htmlspecialchars((string) $e['titulo']) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['doc_numero'] ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['doc_contraparte'] ?? '-')) . '</td>'
                . '<td align="center">' . $cantidad . '</td>'
                . '<td align="center">' . $saldo . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['numero_lote'] ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['bodega'] ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['usuario'] ?? '-')) . '</td>'
                . '</tr>';
        }
        if (empty($data['eventos'])) {
            $html .= '<tr><td colspan="9" align="center">Sin eventos para este producto.</td></tr>';
        }
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $nombreArch = 'trazabilidad_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $producto['codigo']) . '_' . date('Ymd_His') . '.pdf';
        return $pdf->Output($nombreArch, $dest);
    }
}
