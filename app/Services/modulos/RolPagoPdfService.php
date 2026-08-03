<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoRol;
use App\models\CatalogoNovedades;
use TCPDF;

/**
 * Genera los PDF del módulo Roles de Pago: el recibo individual de un empleado
 * (A4 vertical) y la planilla general con todos los empleados (A4 horizontal).
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
        $conLogo = $this->dibujarLogoSiExiste($pdf, $empresa, 14, 14, 32);

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

        $html .= $this->htmlEncabezadoEmpresa($empresa, 'ROL DE PAGO', $h($tipo . ' — ' . $periodo), $conLogo);

        $html .= '<div class="sect">DATOS DEL EMPLEADO</div>';
        $html .= '<table class="info" cellpadding="0"><tr>'
            . '<td width="15%" style="color:#555;">Empleado</td><td width="50%"><b>' . $h($lin['nombres_apellidos']) . '</b></td>'
            . '<td width="12%" style="color:#555;">Cédula</td><td width="23%">' . $h($lin['identificacion']) . '</td></tr>'
            . '<tr><td style="color:#555;">Cargo</td><td>' . $h($lin['cargo'] ?? '—') . '</td>'
            . '<td style="color:#555;">Días</td><td>' . $h($lin['dias_trabajados']) . '</td></tr>'
            . '<tr><td style="color:#555;">Sueldo Base</td><td>$ ' . $m($lin['sueldo_base'] ?? 0) . '</td>'
            . '<td style="color:#555;">Fecha Pago</td><td>' . $h(!empty($cab['fecha_pago']) ? date('d-m-Y', strtotime((string) $cab['fecha_pago'])) : '—') . '</td></tr></table><br>';

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

    /**
     * Planilla general del rol (todos los empleados) en A4 horizontal, con el
     * mayor detalle posible por empleado y firmas de "Realizado por" / "Aprobado por".
     *
     * @param array  $rol     Cabecera del rol con 'detalle' (líneas por empleado).
     * @param array  $empresa Datos de la empresa.
     * @param string $dest    'I' inline, 'D' descargar, 'S' string.
     */
    public function generarGeneral(array $rol, array $empresa, string $dest = 'I')
    {
        $mes = CatalogoNovedades::MESES[(int) ($rol['periodo_mes'] ?? 0)] ?? ($rol['periodo_mes'] ?? '');
        $num = (int) ($rol['numero_periodo'] ?? 0) > 0 ? ' #' . (int) $rol['numero_periodo'] : '';
        $periodo = trim($mes . ' ' . ($rol['periodo_anio'] ?? '') . $num);
        $tipo = CatalogoRol::nombreTipo((string) ($rol['tipo_rol'] ?? ''));

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetTitle('Rol de Pago - ' . $periodo);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();
        $conLogo = $this->dibujarLogoSiExiste($pdf, $empresa, 10, 10, 32);

        $h = fn($v) => htmlspecialchars((string) ($v ?? ''));
        $m = fn($v) => number_format((float) $v, 2);

        $html = '<style>
            .t { font-size:13px; font-weight:bold; }
            .sub { font-size:8px; color:#555; }
            table.g th { background-color:#e9ecef; font-size:7px; font-weight:bold; padding:2.5px 3px; border:0.5px solid #bbb; }
            table.g td { font-size:7px; padding:2.5px 3px; border:0.5px solid #ddd; }
            .tot { font-weight:bold; background-color:#f1f3f5; }
        </style>';

        $html .= $this->htmlEncabezadoEmpresa($empresa, 'ROL DE PAGO', $h($tipo . ' — ' . $periodo), $conLogo);

        $html .= '<table class="g" cellpadding="0"><tr>'
            . '<th width="17%">Empleado</th>'
            . '<th width="9%">Identificación</th>'
            . '<th width="10%">Cargo</th>'
            . '<th width="5%" align="center">Días</th>'
            . '<th width="9%" align="right">Sueldo Base</th>'
            . '<th width="9%" align="right">Ingresos</th>'
            . '<th width="9%" align="right">Aporte IESS</th>'
            . '<th width="8%" align="right">IR</th>'
            . '<th width="9%" align="right">Otros Egresos</th>'
            . '<th width="9%" align="right">Total Egresos</th>'
            . '<th width="6%" align="right">Neto</th></tr>';

        $tIng = 0.0; $tIess = 0.0; $tIr = 0.0; $tEgr = 0.0; $tNeto = 0.0;
        foreach (($rol['detalle'] ?? []) as $d) {
            $iess = (float) ($d['aporte_iess'] ?? 0);
            $ir   = (float) ($d['retencion_renta'] ?? 0);
            $egr  = (float) ($d['total_egresos'] ?? 0);
            $otros = max(0.0, $egr - $iess - $ir);
            $html .= '<tr>'
                . '<td>' . $h($d['nombres_apellidos'] ?? '') . '</td>'
                . '<td>' . $h($d['identificacion'] ?? '') . '</td>'
                . '<td>' . $h($d['cargo'] ?? '—') . '</td>'
                . '<td align="center">' . $h($d['dias_trabajados'] ?? '') . '</td>'
                . '<td align="right">' . $m($d['sueldo_base'] ?? 0) . '</td>'
                . '<td align="right">' . $m($d['total_ingresos'] ?? 0) . '</td>'
                . '<td align="right">' . $m($iess) . '</td>'
                . '<td align="right">' . $m($ir) . '</td>'
                . '<td align="right">' . $m($otros) . '</td>'
                . '<td align="right">' . $m($egr) . '</td>'
                . '<td align="right"><b>' . $m($d['neto'] ?? 0) . '</b></td></tr>';
            $tIng += (float) ($d['total_ingresos'] ?? 0);
            $tIess += $iess; $tIr += $ir; $tEgr += $egr; $tNeto += (float) ($d['neto'] ?? 0);
        }

        $html .= '<tr class="tot"><td colspan="5">TOTALES</td>'
            . '<td align="right">' . $m($tIng) . '</td>'
            . '<td align="right">' . $m($tIess) . '</td>'
            . '<td align="right">' . $m($tIr) . '</td>'
            . '<td align="right"></td>'
            . '<td align="right">' . $m($tEgr) . '</td>'
            . '<td align="right">' . $m($tNeto) . '</td></tr>';
        $html .= '</table><br><br><br>';

        $html .= '<table cellpadding="0"><tr>'
            . '<td width="30%" align="center" style="border-top:0.5px solid #333; font-size:8px;">Realizado por</td>'
            . '<td width="5%"></td>'
            . '<td width="30%" align="center" style="border-top:0.5px solid #333; font-size:8px;">Aprobado por</td>'
            . '<td width="5%"></td>'
            . '<td width="30%" align="center" style="border-top:0.5px solid #333; font-size:8px;">Contabilidad</td></tr></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $arch = 'Rol_' . preg_replace('/[^A-Za-z0-9]/', '_', $periodo) . '.pdf';
        return $pdf->Output($arch, $dest);
    }

    /**
     * Encabezado común: razón social/RUC/dirección/teléfono a la izquierda (deja
     * hueco a la izquierda si ya se dibujó el logo con dibujarLogoSiExiste),
     * título del documento a la derecha.
     */
    private function htmlEncabezadoEmpresa(array $empresa, string $titulo, string $subtitulo, bool $conLogo): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''));
        $empNom = $h($empresa['razon_social'] ?? $empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'Empresa');
        $empRuc = $h($empresa['ruc'] ?? '');
        $empDir = $h($empresa['direccion'] ?? '');
        $empTel = $h($empresa['telefono'] ?? '');

        $logoCell = $conLogo ? '<td width="20%">&nbsp;</td>' : '';
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

    /**
     * Dibuja el logo de la empresa en (x, y) si existe el archivo, con ancho fijo
     * $w (alto proporcional, centrado). Devuelve true si lo dibujó — el llamador
     * usa eso para reservar el hueco correspondiente en la tabla HTML del encabezado.
     */
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
