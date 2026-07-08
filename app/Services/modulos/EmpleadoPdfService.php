<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Genera la Ficha del Empleado en PDF (A4 vertical).
 *
 * Estructura: encabezado con la empresa + título, datos generales, datos
 * laborales, datos bancarios, historial de periodos y rubros fijos.
 * Formateador puro (no accede a BD); recibe el detalle ya cargado.
 */
class EmpleadoPdfService
{
    /**
     * @param array  $emp     Detalle del empleado (incluye 'periodos' y 'rubros').
     * @param array  $empresa Datos de la empresa para el encabezado.
     * @param string $dest    Destino TCPDF: 'I' inline, 'D' descargar, 'S' string.
     */
    public function generar(array $emp, array $empresa, string $dest = 'I')
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor('CaMaGaRe');
        $pdf->SetTitle('Ficha Empleado - ' . ($emp['nombres_apellidos'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        $empNom = htmlspecialchars((string)($empresa['razon_social'] ?? $empresa['nombre_comercial'] ?? 'Empresa'));
        $empRuc = htmlspecialchars((string)($empresa['ruc'] ?? ''));

        $html = '<style>
            h1 { font-size: 13px; font-weight: bold; }
            .sub { font-size: 8px; color: #555; }
            .sect { background-color:#e9ecef; font-weight:bold; font-size:9px; padding:4px; }
            table.d td { font-size: 8.5px; padding: 3px 4px; border-bottom: 0.5px solid #dddddd; }
            table.d td.lbl { color:#555; width:22%; }
            table.g th { background-color:#f1f3f5; font-size:8px; font-weight:bold; padding:3px 4px; border:0.5px solid #ccc; }
            table.g td { font-size:8px; padding:3px 4px; border:0.5px solid #ddd; }
        </style>';

        // Encabezado
        $html .= '<table cellpadding="0"><tr>
            <td width="70%"><h1>' . $empNom . '</h1><span class="sub">RUC: ' . $empRuc . '</span></td>
            <td width="30%" align="right"><h1>FICHA DEL EMPLEADO</h1></td>
        </tr></table><br>';

        // Datos generales
        $html .= '<div class="sect">DATOS GENERALES</div>';
        $html .= '<table class="d" cellpadding="0">'
            . $this->fila('Identificación', ($this->cap($emp['tipo_id'] ?? '')) . ': ' . ($emp['identificacion'] ?? ''),
                          'Estado', $this->cap($emp['estado'] ?? ''))
            . $this->fila('Nombres y Apellidos', $emp['nombres_apellidos'] ?? '', 'Sexo', $emp['sexo'] ?? '')
            . $this->fila('Fecha Nacimiento', $this->fecha($emp['fecha_nacimiento'] ?? ''), 'Teléfono', $emp['telefono'] ?? '')
            . $this->fila('Correo', $emp['email'] ?? '', 'Contacto Emergencia', $emp['contacto_emergencia'] ?? '')
            . $this->fila('Dirección', $emp['direccion'] ?? '', '', '')
            . '</table><br>';

        // Datos laborales
        $html .= '<div class="sect">DATOS LABORALES</div>';
        $html .= '<table class="d" cellpadding="0">'
            . $this->fila('Cargo', $emp['cargo'] ?? '', 'Departamento', $emp['departamento'] ?? '')
            . $this->fila('Región', $this->cap($emp['region'] ?? ''), 'Cód. Sectorial IESS', $emp['codigo_sectorial_iess'] ?? '')
            . $this->fila('Lugar de Trabajo', $emp['lugar_trabajo'] ?? '', 'Horario', $emp['horario_trabajo'] ?? '')
            . $this->fila('Fondos de Reserva', $this->fondos($emp['fondos_reserva'] ?? ''), 'Aporta IESS', $this->boolSiNo($emp['aporta_iess'] ?? false))
            . $this->fila('Décimo Tercero', $this->cap($emp['decimo_tercero'] ?? ''), 'Décimo Cuarto', $this->cap($emp['decimo_cuarto'] ?? ''))
            . $this->fila('Aporte Personal (%)', $this->num($emp['aporte_personal'] ?? 0, 4), 'Aporte Patronal (%)', $this->num($emp['aporte_patronal'] ?? 0, 4))
            . $this->fila('Sueldo Base', '$ ' . $this->num($emp['sueldo_base'] ?? 0), 'V. Semanal', '$ ' . $this->num($emp['valor_semanal'] ?? 0))
            . $this->fila('V. Quincena', '$ ' . $this->num($emp['valor_quincena'] ?? 0), '', '')
            . '</table><br>';

        // Datos bancarios
        $html .= '<div class="sect">DATOS BANCARIOS</div>';
        $html .= '<table class="d" cellpadding="0">'
            . $this->fila('Banco', $emp['nombre_banco'] ?? '—', 'Tipo Cuenta', $this->cap($emp['tipo_cuenta'] ?? ''))
            . $this->fila('Número de Cuenta', $emp['numero_cuenta'] ?? '', '', '')
            . '</table><br>';

        // Periodos
        $periodos = $emp['periodos'] ?? [];
        $html .= '<div class="sect">HISTORIAL LABORAL</div>';
        if (empty($periodos)) {
            $html .= '<table class="d"><tr><td>Sin periodos registrados.</td></tr></table><br>';
        } else {
            $html .= '<table class="g" cellpadding="0"><tr><th>Ingreso</th><th>Salida</th><th>Motivo</th></tr>';
            foreach ($periodos as $p) {
                $html .= '<tr><td>' . $this->fecha($p['fecha_ingreso'] ?? '') . '</td>'
                    . '<td>' . $this->fecha($p['fecha_salida'] ?? '') . '</td>'
                    . '<td>' . htmlspecialchars((string)($p['motivo_salida'] ?? '')) . '</td></tr>';
            }
            $html .= '</table><br>';
        }

        // Rubros
        $rubros = $emp['rubros'] ?? [];
        $html .= '<div class="sect">RUBROS FIJOS</div>';
        if (empty($rubros)) {
            $html .= '<table class="d"><tr><td>Sin rubros registrados.</td></tr></table>';
        } else {
            $html .= '<table class="g" cellpadding="0"><tr><th>Tipo</th><th>Nombre</th><th align="right">Valor</th><th>Aporta IESS</th></tr>';
            foreach ($rubros as $r) {
                $html .= '<tr><td>' . $this->cap($r['tipo'] ?? '') . '</td>'
                    . '<td>' . htmlspecialchars((string)($r['nombre'] ?? '')) . '</td>'
                    . '<td align="right">$ ' . $this->num($r['valor'] ?? 0) . '</td>'
                    . '<td>' . $this->boolSiNo($r['aporta_iess'] ?? false) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $pdf->writeHTML($html, true, false, true, false, '');

        $nombreArch = 'Ficha_Empleado_' . preg_replace('/[^A-Za-z0-9]/', '_', (string)($emp['identificacion'] ?? 'empleado')) . '.pdf';
        return $pdf->Output($nombreArch, $dest);
    }

    // ─── Helpers de formato ──────────────────────────────────────────────────

    private function fila(string $l1, $v1, string $l2, $v2): string
    {
        $c1 = '<td class="lbl">' . htmlspecialchars($l1) . '</td><td>' . htmlspecialchars((string)$v1) . '</td>';
        if ($l2 === '') {
            $c2 = '<td class="lbl"></td><td></td>';
        } else {
            $c2 = '<td class="lbl">' . htmlspecialchars($l2) . '</td><td>' . htmlspecialchars((string)$v2) . '</td>';
        }
        return '<tr>' . $c1 . $c2 . '</tr>';
    }

    private function cap(string $s): string
    {
        $s = str_replace('_', ' ', trim($s));
        return $s === '' ? '—' : mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }

    private function num($v, int $dec = 2): string
    {
        return number_format((float)$v, $dec, '.', ',');
    }

    private function fecha($v): string
    {
        $v = trim((string)$v);
        if ($v === '' || str_starts_with($v, '0000')) return '—';
        $ts = strtotime($v);
        return $ts ? date('d-m-Y', $ts) : $v;
    }

    private function boolSiNo($v): string
    {
        if (is_string($v)) return in_array(strtolower($v), ['t', 'true', '1', 'si', 'sí'], true) ? 'Sí' : 'No';
        return $v ? 'Sí' : 'No';
    }

    private function fondos(string $v): string
    {
        return match ($v) {
            'no_se_paga' => 'No se paga',
            'rol'        => 'En Rol Mensual',
            'planilla'   => 'Planilla IESS',
            default      => $this->cap($v),
        };
    }
}
