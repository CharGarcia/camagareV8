<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AuditoriaEtiquetas;
use TCPDF;

/**
 * Genera el listado de Auditoría del sistema en PDF (A4 horizontal).
 *
 * Formateador puro (no accede a BD): recibe las filas ya cargadas, los datos
 * de la empresa para el encabezado y la metadata de la consulta (filtros, tope).
 */
class LogSistemaPdfService
{
    /**
     * @param array  $rows    Filas de la bitácora (con usuario_nombre, empresa_nombre, etc.).
     * @param array  $empresa Datos de la empresa activa para el encabezado.
     * @param array  $meta    ['filtro','rango','total','truncado','generado_por'].
     * @param string $dest    Destino TCPDF: 'I' inline, 'D' descargar, 'S' string.
     */
    public function generar(array $rows, array $empresa, array $meta, string $dest = 'I')
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor('CaMaGaRe');
        $pdf->SetTitle('Auditoría del sistema');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        // Pie con numeración de página
        $pdf->setFooterData([0, 0, 0], [200, 200, 200]);
        $pdf->setFooterFont(['helvetica', '', 7]);

        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();

        $empNom = htmlspecialchars((string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'Sistema'));
        $empRuc = htmlspecialchars((string) ($empresa['ruc'] ?? ''));

        $filtro   = trim((string) ($meta['filtro'] ?? ''));
        $rango    = htmlspecialchars((string) ($meta['rango'] ?? 'Últimos 30 días'));
        $total    = (int) ($meta['total'] ?? count($rows));
        $mostrado = count($rows);
        $truncado = !empty($meta['truncado']);
        $generado = htmlspecialchars((string) ($meta['generado_por'] ?? ''));

        $html = '<style>
            h1 { font-size: 13px; font-weight: bold; }
            .sub { font-size: 8px; color: #555; }
            .meta { font-size: 8px; color: #444; }
            table.g th { background-color:#e9ecef; font-size:7.5px; font-weight:bold; padding:3px 4px; border:0.5px solid #ccc; }
            table.g td { font-size:7.5px; padding:2.5px 4px; border:0.5px solid #ddd; }
            .aviso { color:#b00020; font-size:8px; }
        </style>';

        // Encabezado
        $html .= '<table cellpadding="0"><tr>'
            . '<td width="60%"><h1>' . $empNom . '</h1><span class="sub">RUC: ' . $empRuc . '</span></td>'
            . '<td width="40%" align="right"><h1>AUDITORÍA DEL SISTEMA</h1>'
            . '<span class="sub">Generado: ' . date('d-m-Y H:i:s') . ($generado !== '' ? ' &nbsp;·&nbsp; por ' . $generado : '') . '</span></td>'
            . '</tr></table>';

        // Metadata de la consulta
        $html .= '<table cellpadding="0"><tr><td class="meta">'
            . '<strong>Rango:</strong> ' . $rango
            . ($filtro !== '' ? ' &nbsp;|&nbsp; <strong>Filtro:</strong> ' . htmlspecialchars($filtro) : '')
            . ' &nbsp;|&nbsp; <strong>Registros:</strong> ' . $mostrado . ' de ' . $total
            . '</td></tr></table>';

        if ($truncado) {
            $html .= '<div class="aviso">Nota: se exportaron las primeras ' . $mostrado . ' de ' . $total
                . ' filas. Acote la búsqueda o el rango de fechas para exportar menos registros.</div>';
        }
        $html .= '<br>';

        // Tabla
        $html .= '<table class="g" cellpadding="0"><thead><tr>'
            . '<th width="14%">Fecha</th>'
            . '<th width="17%">Usuario</th>'
            . '<th width="20%">Empresa</th>'
            . '<th width="13%">Acción</th>'
            . '<th width="18%">Módulo</th>'
            . '<th width="8%" align="center">Registro</th>'
            . '<th width="10%">IP</th>'
            . '</tr></thead><tbody>';

        if (empty($rows)) {
            $html .= '<tr><td colspan="7" align="center">Sin registros para los filtros seleccionados.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $fecha    = date('d-m-Y H:i:s', strtotime((string) $r['created_at']));
                $usuario  = ($r['usuario_nombre'] ?? '') !== '' ? $r['usuario_nombre'] : '#' . (int) $r['id_usuario'];
                $empresaN = ($r['empresa_nombre'] ?? '') !== ''
                    ? $r['empresa_nombre']
                    : ($r['id_empresa'] === null ? 'Global' : '#' . (int) $r['id_empresa']);
                $registro = $r['id_registro'] !== null ? '#' . (int) $r['id_registro'] : '-';

                $html .= '<tr>'
                    . '<td>' . htmlspecialchars($fecha) . '</td>'
                    . '<td>' . htmlspecialchars((string) $usuario) . '</td>'
                    . '<td>' . htmlspecialchars((string) $empresaN) . '</td>'
                    . '<td>' . htmlspecialchars(AuditoriaEtiquetas::accion((string) $r['accion'])) . '</td>'
                    . '<td>' . htmlspecialchars(AuditoriaEtiquetas::tabla((string) ($r['tabla_afectada'] ?? ''))) . '</td>'
                    . '<td align="center">' . htmlspecialchars($registro) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($r['ip_usuario'] ?? '-')) . '</td>'
                    . '</tr>';
            }
        }
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $nombreArch = 'auditoria_sistema_' . date('Ymd_His') . '.pdf';
        return $pdf->Output($nombreArch, $dest);
    }
}
