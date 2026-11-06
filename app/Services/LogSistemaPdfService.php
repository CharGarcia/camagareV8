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
            . '<th width="11%">Fecha</th>'
            . '<th width="11%">Usuario</th>'
            . '<th width="12%">Empresa</th>'
            . '<th width="9%">Acción</th>'
            . '<th width="11%">Módulo</th>'
            . '<th width="12%">Documento</th>'
            . '<th width="19%">Referencia</th>'
            . '<th width="6%" align="center">Registro</th>'
            . '<th width="9%">IP</th>'
            . '</tr></thead><tbody>';

        if (empty($rows)) {
            $html .= '<tr><td colspan="9" align="center">Sin registros para los filtros seleccionados.</td></tr>';
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
                    . '<td>' . htmlspecialchars((string) ($r['numero_documento'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($r['referencia'] ?? '')) . '</td>'
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

    /**
     * PDF del DETALLE de un evento: lo mismo que muestra el modal "Detalle del evento"
     * (cabecera, cambios, documento afectado, datos registrados y JSON original), todo
     * desplegado. A4 vertical.
     *
     * @param array  $d       Resultado de LogSistemaConsultaService::getDetalle().
     * @param array  $empresa Datos de la empresa activa para el encabezado.
     * @param string $generadoPor Nombre del usuario que genera el PDF.
     * @param string $dest    Destino TCPDF: 'I' inline, 'D' descargar, 'S' string.
     */
    public function generarDetalle(array $d, array $empresa, string $generadoPor = '', string $dest = 'I')
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor('CaMaGaRe');
        $pdf->SetTitle('Detalle del evento #' . (int) $d['id']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterData([0, 0, 0], [200, 200, 200]);
        $pdf->setFooterFont(['helvetica', '', 7]);
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();

        $h = static fn ($v): string => htmlspecialchars((string) $v);

        $empNom = $h($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'Sistema');
        $empRuc = $h($empresa['ruc'] ?? '');

        $fecha    = date('d-m-Y H:i:s', strtotime((string) $d['created_at']));
        $usuario  = ($d['usuario_nombre'] ?? '') !== '' ? $d['usuario_nombre'] : '#' . (int) $d['id_usuario'];
        $empEv    = ($d['empresa_nombre'] ?? '') !== ''
            ? $d['empresa_nombre']
            : ($d['id_empresa'] === null ? 'Global (sin empresa)' : '#' . (int) $d['id_empresa']);
        $documento = $d['documento'] ?? null;
        $referencia = trim((string) ($d['referencia'] ?? ''));

        $html = '<style>
            h1 { font-size: 13px; font-weight: bold; }
            h2 { font-size: 9px; font-weight: bold; color:#555; }
            .sub { font-size: 8px; color: #555; }
            table.k td { font-size:8px; padding:2px 3px; }
            td.lbl { color:#666; }
            table.g th { background-color:#e9ecef; font-size:7.5px; font-weight:bold; padding:3px 4px; border:0.5px solid #ccc; }
            table.g td { font-size:7.5px; padding:2.5px 4px; border:0.5px solid #ddd; }
            td.cambio { background-color:#fff3cd; }
            .antes { color:#b02a37; }
            .despues { color:#146c43; }
            .muted { color:#888; }
            .json { font-family: courier; font-size:6.5px; }
        </style>';

        // Encabezado
        $html .= '<table cellpadding="0"><tr>'
            . '<td width="55%"><h1>' . $empNom . '</h1><span class="sub">RUC: ' . $empRuc . '</span></td>'
            . '<td width="45%" align="right"><h1>DETALLE DEL EVENTO #' . (int) $d['id'] . '</h1>'
            . '<span class="sub">Generado: ' . date('d-m-Y H:i:s') . ($generadoPor !== '' ? ' &nbsp;·&nbsp; por ' . $h($generadoPor) : '') . '</span></td>'
            . '</tr></table><br>';

        // Datos del evento (dos columnas, como en el modal)
        $pares = [
            ['Fecha', $fecha], ['Usuario', $usuario],
            ['Empresa', $empEv], ['Acción', AuditoriaEtiquetas::accion((string) $d['accion'])],
            ['Módulo', AuditoriaEtiquetas::tabla((string) ($d['tabla_afectada'] ?? ''))],
        ];
        if ($documento !== null) {
            $pares[] = ['Número de documento', ($documento['numero'] ?? '-') . (!empty($documento['eliminado']) ? ' (Eliminado)' : '')];
        }
        if ($referencia !== '') {
            $pares[] = ['Referencia', $referencia];
        }
        $pares[] = ['Registro', $d['id_registro'] !== null ? '#' . (int) $d['id_registro'] : '-'];
        $pares[] = ['IP', $d['ip_usuario'] ?? '-'];

        $html .= '<table class="k" cellpadding="0">';
        foreach (array_chunk($pares, 2) as $par) {
            $html .= '<tr>';
            foreach ($par as [$lbl, $val]) {
                $html .= '<td width="16%" class="lbl">' . $h($lbl) . ':</td><td width="34%"><b>' . $h($val) . '</b></td>';
            }
            if (count($par) === 1) {
                $html .= '<td width="16%"></td><td width="34%"></td>';
            }
            $html .= '</tr>';
        }
        $html .= '<tr><td width="16%" class="lbl">Navegador:</td><td width="84%">' . $h($d['user_agent'] ?? '-') . '</td></tr>';
        $html .= '</table><br>';

        // Cambios
        $html .= '<h2>CAMBIOS</h2>';
        if (empty($d['cambios'])) {
            $html .= '<p class="muted" style="font-size:8px;">Sin cambios de campos registrados para esta acción.</p>';
        } else {
            $html .= '<table class="g" cellpadding="0"><thead><tr>'
                . '<th width="30%">Campo</th><th width="35%">Antes</th><th width="35%">Después</th>'
                . '</tr></thead><tbody>';
            foreach ($d['cambios'] as $c) {
                $html .= '<tr><td width="30%">' . $h($c['campo'] ?? '') . '</td>'
                    . '<td width="35%" class="antes">' . $h($c['antes'] ?? '-') . '</td>'
                    . '<td width="35%" class="despues">' . $h($c['despues'] ?? '-') . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        $html .= '<br>';

        // Documento afectado (como está hoy)
        $doc = $d['documento_detalle'] ?? null;
        if ($doc !== null) {
            $html .= $this->htmlDocumentoDetalle($doc, !empty($documento['eliminado']));
        }

        // Datos registrados en el evento
        $datos      = $d['datos'] ?? [];
        $conAntes   = $d['antes_json'] !== null;
        $conDespues = $d['despues_json'] !== null;
        $dosCol     = $conAntes && $conDespues;
        if (!empty($datos)) {
            $html .= '<h2>DATOS REGISTRADOS EN EL EVENTO' . ($dosCol ? ' <span class="muted">— resaltados los que cambiaron</span>' : '') . '</h2>';
            $html .= '<table class="g" cellpadding="0"><thead><tr><th width="24%">Campo</th>'
                . ($dosCol ? '<th width="38%">Antes</th><th width="38%">Después</th>' : '<th width="76%">Valor</th>')
                . '</tr></thead><tbody>';
            foreach ($datos as $fila) {
                $cls = !empty($fila['cambio']) ? ' class="cambio"' : '';
                $html .= '<tr><td width="24%"' . $cls . '>' . $h($fila['campo']) . '</td>';
                if ($dosCol) {
                    $html .= '<td width="38%"' . $cls . '>' . $this->celdaDatoPdf($fila['antes']) . '</td>'
                        . '<td width="38%"' . $cls . '>' . $this->celdaDatoPdf($fila['despues']) . '</td>';
                } else {
                    $html .= '<td width="76%">' . $this->celdaDatoPdf($conDespues ? $fila['despues'] : $fila['antes']) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table><br>';
        }

        $pdf->writeHTML($html, true, false, true, false, '');

        // JSON original (texto plano, sin interpretar)
        if ($conAntes || $conDespues) {
            $json = '<h2>JSON ORIGINAL</h2>';
            if ($conAntes) {
                $json .= '<div class="muted" style="font-size:7px;">datos_anteriores</div>'
                    . '<div style="font-family:courier;font-size:6.5px;">' . nl2br($h($d['antes_json'])) . '</div><br>';
            }
            if ($conDespues) {
                $json .= '<div class="muted" style="font-size:7px;">datos_nuevos</div>'
                    . '<div style="font-family:courier;font-size:6.5px;">' . nl2br($h($d['despues_json'])) . '</div>';
            }
            $pdf->writeHTML('<style>h2{font-size:9px;font-weight:bold;color:#555;} .muted{color:#888;}</style>' . $json, true, false, true, false, '');
        }

        return $pdf->Output('auditoria_evento_' . (int) $d['id'] . '.pdf', $dest);
    }

    /** Bloque "Documento afectado — como está hoy" del PDF de detalle. */
    private function htmlDocumentoDetalle(array $doc, bool $eliminado): string
    {
        $h = static fn ($v): string => htmlspecialchars((string) $v);

        $tercero = $doc['tercero'] !== null
            ? $doc['tercero'] . (!empty($doc['tercero_identificacion']) ? ' (' . $doc['tercero_identificacion'] . ')' : '')
            : '';

        $html = '<h2>DOCUMENTO AFECTADO <span class="muted">— como está hoy</span></h2>';
        $pares = array_filter([
            ['Tipo', $doc['etiqueta'] ?? ''],
            ['Número', ((string) ($doc['numero'] ?? '')) . ($eliminado ? ' (Eliminado)' : '')],
            ['Fecha', $doc['fecha'] ?? ''],
            ['Estado', $doc['estado'] ?? ''],
            ['Tercero', $tercero],
        ], static fn ($p) => trim((string) $p[1]) !== '');
        foreach ($doc['campos'] ?? [] as $c) {
            if (trim((string) $c['valor']) !== '') {
                $pares[] = [(string) $c['label'], (string) $c['valor']];
            }
        }
        $html .= '<table class="k" cellpadding="0">';
        foreach ($pares as [$lbl, $val]) {
            $html .= '<tr><td width="20%" class="lbl">' . $h($lbl) . ':</td><td width="80%"><b>' . $h($val) . '</b></td></tr>';
        }
        $html .= '</table><br>';

        $cols = $doc['columnas'] ?? [];
        if (!empty($cols)) {
            $w = round(100 / count($cols), 2) . '%';
            $html .= '<table class="g" cellpadding="0"><thead><tr>';
            foreach ($cols as $c) {
                $html .= '<th width="' . $w . '"' . (!empty($c['numerica']) ? ' align="right"' : '') . '>' . $h($c['label']) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            if (empty($doc['lineas'])) {
                $html .= '<tr><td width="100%" colspan="' . count($cols) . '" align="center">Este documento no tiene líneas registradas.</td></tr>';
            } else {
                foreach ($doc['lineas'] as $fila) {
                    $html .= '<tr>';
                    foreach ($fila as $i => $valor) {
                        $html .= '<td width="' . $w . '"' . (!empty($cols[$i]['numerica']) ? ' align="right"' : '') . '>' . $h($valor) . '</td>';
                    }
                    $html .= '</tr>';
                }
            }
            $html .= '</tbody></table><br>';
        }

        $totales = $doc['totales'] ?? [];
        if (!empty($totales) || ($doc['observaciones'] ?? null) !== null) {
            $html .= '<table cellpadding="0"><tr><td width="60%" style="font-size:8px;">'
                . (($doc['observaciones'] ?? null) !== null ? '<b>Observaciones:</b> ' . $h($doc['observaciones']) : '')
                . '</td><td width="40%"><table class="k" cellpadding="0">';
            foreach ($totales as $i => $t) {
                $ult = $i === count($totales) - 1;
                $html .= '<tr><td width="60%" class="lbl">' . $h($t['label']) . '</td><td width="40%" align="right">'
                    . ($ult ? '<b>' . $h($t['valor']) . '</b>' : $h($t['valor'])) . '</td></tr>';
            }
            $html .= '</table></td></tr></table><br>';
        }

        return $html;
    }

    /** Celda de "Datos registrados": texto, o tabla anidada para listas/objetos. */
    private function celdaDatoPdf($celda): string
    {
        if ($celda === null) {
            return '<i class="muted">No registrado</i>';
        }
        if (is_array($celda)) {
            $cols = $celda['columnas'] ?? [];
            if (empty($cols)) {
                return '';
            }
            $w = round(100 / count($cols), 2) . '%';
            $html = '<table class="g" cellpadding="0"><thead><tr>';
            foreach ($cols as $c) {
                $html .= '<th width="' . $w . '">' . htmlspecialchars((string) $c) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($celda['filas'] ?? [] as $fila) {
                $html .= '<tr>';
                foreach ($fila as $valor) {
                    $html .= '<td width="' . $w . '">' . htmlspecialchars((string) $valor) . '</td>';
                }
                $html .= '</tr>';
            }
            return $html . '</tbody></table>';
        }
        return htmlspecialchars((string) $celda);
    }
}
