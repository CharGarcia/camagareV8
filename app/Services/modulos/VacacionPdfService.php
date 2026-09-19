<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoNovedades;
use TCPDF;

/**
 * PDF del módulo Vacaciones:
 *   - generarSolicitud()      la solicitud del empleado (A4 vertical, con firmas)
 *   - generarDetalleEmpleado() su detalle completo: períodos, saldo y las vacaciones
 *                              registradas con sus valores
 *
 * Un solo servicio para los dos documentos, con el encabezado y el logo
 * compartidos (mismo criterio que TallerPdfHelper): así ambos hablan el mismo
 * idioma visual sin repetir helpers.
 */
class VacacionPdfService
{
    /** Etiquetas de la columna Situación (las mismas de public/js/modulos/vacaciones_periodos.js). */
    private const SITUACION = [
        'pendiente'      => 'Pendiente',
        'parcial'        => 'Parcial',
        'tomado'         => 'Vacaciones tomadas',
        'pagado'         => 'Pagado antes del sistema',
        'gozado'         => 'Gozado en el sistema',
        'en_curso'       => 'En curso',
        'fuera_de_rango' => 'Ya no calza con la antigüedad',
    ];

    /**
     * Solicitud de vacaciones del empleado.
     *
     * @param array  $sol     Solicitud (VacacionSolicitudRepository::getDetalle)
     * @param array  $empresa Datos de la empresa (con logo_ruta del establecimiento)
     * @param string $dest    'I' inline, 'D' descargar, 'S' string
     */
    public function generarSolicitud(array $sol, array $empresa, string $dest = 'I')
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''));
        $f = fn($v) => $v ? date('d-m-Y', strtotime((string) $v)) : '—';
        $fh = fn($v) => $v ? date('d-m-Y H:i:s', strtotime((string) $v)) : '—';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetTitle('Solicitud de vacaciones - ' . ($sol['empleado_nombre'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $conLogo = $this->dibujarLogoSiExiste($pdf, $empresa, 14, 14, 32);

        $estados = [
            'enviada'   => 'Enviada al empleado (sin llenar)',
            'pendiente' => 'Pendiente de aprobación',
            'aprobada'  => 'APROBADA',
            'rechazada' => 'RECHAZADA',
            'cancelada' => 'Anulada',
        ];
        $estado = $estados[$sol['estado'] ?? ''] ?? ucfirst((string) ($sol['estado'] ?? ''));

        $html = $this->estilos();
        $html .= $this->htmlEncabezadoEmpresa($empresa, 'SOLICITUD DE VACACIONES', 'N° ' . str_pad((string) ($sol['id'] ?? 0), 6, '0', STR_PAD_LEFT), $conLogo);

        $html .= '<div class="sect">DATOS DEL EMPLEADO</div>';
        $html .= '<table class="info" cellpadding="0">'
            . '<tr><td width="18%" style="color:#555;">Empleado</td><td width="47%"><b>' . $h($sol['empleado_nombre'] ?? '') . '</b></td>'
            . '<td width="13%" style="color:#555;">Cédula</td><td width="22%">' . $h($sol['empleado_identificacion'] ?? '') . '</td></tr>'
            . '<tr><td style="color:#555;">Cargo</td><td>' . $h($sol['empleado_cargo'] ?? '—') . '</td>'
            . '<td style="color:#555;">Contacto</td><td>' . $h($sol['contacto'] ?? '—') . '</td></tr>'
            . '</table><br>';

        $html .= '<div class="sect">VACACIONES SOLICITADAS</div>';
        $html .= '<table class="g" cellpadding="0">'
            . '<tr><th width="25%">Desde</th><th width="25%">Hasta</th><th width="25%">Días solicitados</th><th width="25%">Fecha de solicitud</th></tr>'
            . '<tr>'
            . '<td width="25%" align="center">' . $h($f($sol['fecha_desde'] ?? null)) . '</td>'
            . '<td width="25%" align="center">' . $h($f($sol['fecha_hasta'] ?? null)) . '</td>'
            . '<td width="25%" align="center"><b>' . $this->dias($sol['dias_solicitados'] ?? 0) . '</b></td>'
            . '<td width="25%" align="center">' . $h($fh($sol['solicitado_at'] ?? null)) . '</td>'
            . '</tr></table><br>';

        $motivo = trim((string) ($sol['motivo'] ?? ''));
        $html .= '<div class="sect">MOTIVO</div>';
        $html .= '<table class="g" cellpadding="0"><tr><td width="100%" style="min-height:14px;">'
            . ($motivo !== '' ? $h($motivo) : '<span style="color:#888;">Sin motivo indicado.</span>')
            . '</td></tr></table><br>';

        $html .= '<div class="sect">RESOLUCIÓN</div>';
        $html .= '<table class="info" cellpadding="0">'
            . '<tr><td width="18%" style="color:#555;">Estado</td><td width="47%"><b>' . $h($estado) . '</b></td>'
            . '<td width="13%" style="color:#555;">Fecha</td><td width="22%">' . $h($fh($sol['resuelto_at'] ?? null)) . '</td></tr>'
            . '<tr><td style="color:#555;">Resuelta por</td><td>' . $h($sol['resuelto_nombre'] ?? '—') . '</td>'
            . '<td style="color:#555;">Enviada por</td><td>' . $h($sol['enviado_nombre'] ?? '—') . '</td></tr>'
            . '<tr><td style="color:#555;">Comentario</td><td colspan="3">' . $h(($sol['comentario'] ?? '') !== '' ? $sol['comentario'] : '—') . '</td></tr>'
            . '</table><br><br><br>';

        $html .= '<table cellpadding="0"><tr>'
            . '<td width="45%" align="center" style="border-top:0.5px solid #333; font-size:8px;">' . $h($sol['empleado_nombre'] ?? '') . '<br>Empleado (solicita)</td>'
            . '<td width="10%"></td>'
            . '<td width="45%" align="center" style="border-top:0.5px solid #333; font-size:8px;">' . $h(($sol['resuelto_nombre'] ?? '') !== '' ? $sol['resuelto_nombre'] : '&nbsp;') . '<br>Autoriza</td>'
            . '</tr></table>';

        $html .= '<br><span class="sub">Documento interno. Impreso el ' . date('d-m-Y H:i:s') . '.</span>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $arch = 'Solicitud_Vacaciones_' . preg_replace('/[^A-Za-z0-9]/', '_', (string) ($sol['empleado_identificacion'] ?? 'empleado')) . '.pdf';
        return $pdf->Output($arch, $dest);
    }

    /**
     * Detalle de vacaciones de un empleado: resumen de saldo, cuadro de períodos y
     * las vacaciones registradas con sus días y valores (lo pagado sale con el
     * valor de cada una y el total).
     *
     * @param array  $emp         Empleado (nombres_apellidos, identificacion, sueldo_base)
     * @param array  $info        VacacionService::getInfoEmpleado()
     * @param array  $vacaciones  Vacaciones registradas del empleado
     * @param array  $empresa     Datos de la empresa
     * @param string $dest        'I' inline, 'D' descargar, 'S' string
     */
    public function generarDetalleEmpleado(array $emp, array $info, array $vacaciones, array $empresa, string $dest = 'I')
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''));
        $m = fn($v) => number_format((float) $v, 2);
        $f = fn($v) => $v ? date('d-m-Y', strtotime((string) $v)) : '—';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetTitle('Detalle de vacaciones - ' . ($emp['nombres_apellidos'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $conLogo = $this->dibujarLogoSiExiste($pdf, $empresa, 12, 12, 32);

        $html  = $this->estilos();
        $html .= $this->htmlEncabezadoEmpresa($empresa, 'DETALLE DE VACACIONES', $h($emp['nombres_apellidos'] ?? ''), $conLogo);

        // ── Resumen ──
        $html .= '<div class="sect">RESUMEN</div>';
        $html .= '<table class="info" cellpadding="0">'
            . '<tr><td width="18%" style="color:#555;">Empleado</td><td width="47%"><b>' . $h($emp['nombres_apellidos'] ?? '') . '</b></td>'
            . '<td width="15%" style="color:#555;">Cédula</td><td width="20%">' . $h($emp['identificacion'] ?? '') . '</td></tr>'
            . '<tr><td style="color:#555;">Fecha de ingreso</td><td>' . $h($f($info['fecha_ingreso'] ?? null)) . '</td>'
            . '<td style="color:#555;">Antigüedad</td><td>' . $h($info['antiguedad'] ?? '—') . '</td></tr>'
            . '<tr><td style="color:#555;">Sueldo base</td><td>$ ' . $m($emp['sueldo_base'] ?? 0) . '</td>'
            . '<td style="color:#555;">Derecho del año</td><td>' . $this->dias($info['derecho_anio_actual'] ?? 0) . '</td></tr>'
            . '</table><br>';

        $html .= '<table class="g" cellpadding="0"><tr>'
            . '<th width="25%">Derecho acumulado</th><th width="25%">Tomados/pagados antes</th>'
            . '<th width="25%">Gozados en el sistema</th><th width="25%">Saldo pendiente</th></tr><tr>'
            . '<td width="25%" align="center">' . $this->dias($info['total_derecho'] ?? 0) . '</td>'
            . '<td width="25%" align="center">' . $this->dias($info['dias_marcados'] ?? 0) . '</td>'
            . '<td width="25%" align="center">' . $this->dias($info['dias_gozados_total'] ?? 0) . '</td>'
            . '<td width="25%" align="center" class="tot">' . $this->dias($info['saldo'] ?? 0) . '</td>'
            . '</tr></table><br>';

        // ── Períodos (años de trabajo) ──
        $html .= '<div class="sect">PERÍODOS (AÑOS DE TRABAJO)</div>';
        $html .= '<table class="g" cellpadding="0"><thead><tr>'
            . '<th width="6%">#</th><th width="14%">Desde</th><th width="14%">Hasta</th>'
            . '<th width="12%">Derecho</th><th width="14%">Antes del sistema</th>'
            . '<th width="13%">En el sistema</th><th width="12%">Pendiente</th><th width="15%">Situación</th>'
            . '</tr></thead><tbody>';

        $periodos = $info['periodos'] ?? [];
        if (empty($periodos)) {
            $html .= '<tr><td width="100%" align="center" style="color:#888;">Sin períodos para mostrar (el empleado no tiene fecha de ingreso registrada).</td></tr>';
        } else {
            foreach ($periodos as $p) {
                $derecho = !empty($p['completo']) || ($p['situacion'] ?? '') === 'fuera_de_rango'
                    ? $this->dias($p['dias_derecho'] ?? 0)
                    : $this->dias($p['dias_acumulados'] ?? 0) . ' de ' . $this->dias($p['dias_derecho'] ?? 0);
                $antes = !empty($p['marca']) ? $this->dias($p['marca']['dias'] ?? 0) : '—';
                $html .= '<tr>'
                    . '<td width="6%" align="center">' . (int) ($p['numero'] ?? 0) . '</td>'
                    . '<td width="14%" align="center">' . $h($f($p['fecha_inicio'] ?? null)) . '</td>'
                    . '<td width="14%" align="center">' . $h($f($p['fecha_fin'] ?? null)) . '</td>'
                    . '<td width="12%" align="center">' . $derecho . '</td>'
                    . '<td width="14%" align="center">' . $antes . '</td>'
                    . '<td width="13%" align="center">' . (((float) ($p['dias_sistema'] ?? 0)) > 0 ? $this->dias($p['dias_sistema']) : '—') . '</td>'
                    . '<td width="12%" align="center">' . (((float) ($p['pendiente'] ?? 0)) > 0 ? $this->dias($p['pendiente']) : '—') . '</td>'
                    . '<td width="15%">' . $h(self::SITUACION[$p['situacion'] ?? ''] ?? ($p['situacion'] ?? '')) . '</td>'
                    . '</tr>';
            }
        }
        $html .= '</tbody></table><br>';

        // ── Vacaciones registradas (con valores) ──
        $html .= '<div class="sect">VACACIONES REGISTRADAS</div>';
        $html .= '<table class="g" cellpadding="0"><thead><tr>'
            . '<th width="13%">Desde</th><th width="13%">Hasta</th><th width="9%">Días</th>'
            . '<th width="11%">Derecho</th><th width="13%" align="right">Valor</th>'
            . '<th width="14%">Mes del rol</th><th width="11%">Estado</th><th width="16%">Observación</th>'
            . '</tr></thead><tbody>';

        $totDias = 0.0; $totValor = 0.0; $totPagado = 0.0;
        if (empty($vacaciones)) {
            $html .= '<tr><td width="100%" align="center" style="color:#888;">No hay vacaciones registradas en el sistema.</td></tr>';
        } else {
            foreach ($vacaciones as $v) {
                $anulada = ($v['estado'] ?? '') === 'anulado';
                if (!$anulada) {
                    $totDias  += (float) ($v['dias_gozados'] ?? 0);
                    $totValor += (float) ($v['valor'] ?? 0);
                    if (($v['estado'] ?? '') === 'pagado') {
                        $totPagado += (float) ($v['valor'] ?? 0);
                    }
                }
                $mes = CatalogoNovedades::MESES[(int) ($v['periodo_mes'] ?? 0)] ?? '';
                $html .= '<tr' . ($anulada ? ' style="color:#999;"' : '') . '>'
                    . '<td width="13%" align="center">' . $h($f($v['fecha_desde'] ?? null)) . '</td>'
                    . '<td width="13%" align="center">' . $h($f($v['fecha_hasta'] ?? null)) . '</td>'
                    . '<td width="9%" align="center">' . $this->dias($v['dias_gozados'] ?? 0) . '</td>'
                    . '<td width="11%" align="center">' . $this->dias($v['dias_derecho'] ?? 0) . '</td>'
                    . '<td width="13%" align="right">$ ' . $m($v['valor'] ?? 0) . '</td>'
                    . '<td width="14%" align="center">' . $h(trim($mes . ' ' . (string) ($v['periodo_anio'] ?? ''))) . '</td>'
                    . '<td width="11%" align="center">' . $h(ucfirst((string) ($v['estado'] ?? ''))) . '</td>'
                    . '<td width="16%">' . $h(($v['observacion'] ?? '') !== '' ? $v['observacion'] : '—') . '</td>'
                    . '</tr>';
            }
            $html .= '<tr class="tot">'
                . '<td width="26%" colspan="2">TOTALES (sin anuladas)</td>'
                . '<td width="9%" align="center">' . $this->dias($totDias) . '</td>'
                . '<td width="11%"></td>'
                . '<td width="13%" align="right">$ ' . $m($totValor) . '</td>'
                . '<td width="41%" colspan="3"></td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';

        if ($totPagado > 0) {
            $html .= '<br><table class="g" cellpadding="0"><tr>'
                . '<td width="70%" align="right" class="tot">VALOR YA PAGADO POR VACACIONES</td>'
                . '<td width="30%" align="right" class="tot">$ ' . $m($totPagado) . '</td></tr></table>';
        }

        $html .= '<br><span class="sub">El valor de cada vacación se calcula con el sueldo base del empleado '
            . '(sueldo ÷ 30 × días gozados). Las vacaciones anuladas no suman en los totales.</span>'
            . '<br><span class="sub">Impreso el ' . date('d-m-Y H:i:s') . '.</span>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $arch = 'Vacaciones_' . preg_replace('/[^A-Za-z0-9]/', '_', (string) ($emp['identificacion'] ?? 'empleado')) . '.pdf';
        return $pdf->Output($arch, $dest);
    }

    // ─── Compartido ──────────────────────────────────────────────────────────

    /** Días sin ceros de relleno: 7,5 / 15 / 0. */
    private function dias($v): string
    {
        $n = round((float) $v, 2);
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }

    private function estilos(): string
    {
        return '<style>
            .t { font-size:13px; font-weight:bold; }
            .sub { font-size:8px; color:#555; }
            .sect { background-color:#e9ecef; font-weight:bold; font-size:9px; padding:4px; }
            table.info td { font-size:8.5px; padding:3px 4px; }
            table.g th { background-color:#f1f3f5; font-size:8px; font-weight:bold; padding:3px 5px; border:0.5px solid #ccc; }
            table.g td { font-size:8.5px; padding:3px 5px; border:0.5px solid #ddd; }
            .tot { font-weight:bold; background-color:#f8f9fa; }
        </style>';
    }

    /** Encabezado: datos de la empresa a la izquierda y título del documento a la derecha. */
    private function htmlEncabezadoEmpresa(array $empresa, string $titulo, string $subtitulo, bool $conLogo): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''));
        $empNom = $h($empresa['razon_social'] ?? $empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'Empresa');
        $empRuc = $h($empresa['ruc'] ?? '');
        $empDir = $h($empresa['direccion'] ?? '');
        $empTel = $h($empresa['telefono'] ?? '');

        $logoCell   = $conLogo ? '<td width="20%">&nbsp;</td>' : '';
        $anchoTexto = $conLogo ? '45%' : '65%';

        $datos = '<span class="t">' . $empNom . '</span><br><span class="sub">RUC: ' . $empRuc . '</span>';
        if ($empDir !== '') $datos .= '<br><span class="sub">' . $empDir . '</span>';
        if ($empTel !== '') $datos .= '<br><span class="sub">Tel: ' . $empTel . '</span>';

        return '<table cellpadding="0"><tr>'
            . $logoCell
            . '<td width="' . $anchoTexto . '">' . $datos . '</td>'
            . '<td width="35%" align="right"><span class="t">' . htmlspecialchars($titulo) . '</span><br><span class="sub">' . $subtitulo . '</span></td>'
            . '</tr></table><br>';
    }

    /** Dibuja el logo si existe el archivo. Mismo patrón que RolPagoPdfService. */
    private function dibujarLogoSiExiste(TCPDF $pdf, array $empresa, float $x, float $y, float $w): bool
    {
        $ruta = trim((string) ($empresa['logo_ruta'] ?? $empresa['logo'] ?? ''));
        if ($ruta === '') return false;

        $limpia = ltrim($ruta, '/');
        foreach (['sistema/public/', 'sistema/', 'public/'] as $prefijo) {
            if (strpos($limpia, $prefijo) === 0) {
                $limpia = substr($limpia, strlen($prefijo));
                break;
            }
        }

        $rutaAbsoluta = '';
        foreach ([MVC_ROOT . '/public/' . $limpia, MVC_ROOT . '/' . $limpia] as $candidato) {
            if (is_file($candidato)) { $rutaAbsoluta = $candidato; break; }
        }
        if ($rutaAbsoluta === '') return false;

        $pdf->Image($rutaAbsoluta, $x, $y, $w, 0, '', '', 'T', false, 300, '', false, false, 0, 'T');
        return true;
    }
}
