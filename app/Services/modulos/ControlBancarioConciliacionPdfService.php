<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF de Conciliación Bancaria: portada, resumen del período, detalle de
 * créditos/débitos, cheques emitidos en circulación / cobrados en el período,
 * y firmas de Realizado por / Revisado por.
 *
 * A4 vertical. Reutiliza el patrón de resolución de logo de ComprobanteCajaPdfService.
 */
class ControlBancarioConciliacionPdfService
{
    private TCPDF $pdf;
    private float $marginL = 12;
    private float $marginR = 12;
    private float $contentW = 186;

    public function generar(array $reporte, array $empresa): void
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema Contable');
        $this->pdf->SetAuthor((string) ($empresa['nombre'] ?? ''));
        $this->pdf->SetTitle('Conciliación Bancaria');
        $this->pdf->SetMargins($this->marginL, 12, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 20);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        $this->dibujarPortada($reporte, $empresa);

        $this->pdf->AddPage();
        $y = $this->dibujarResumen($reporte, 12);
        $y = $this->dibujarTabla('DETALLE DE CRÉDITOS (entradas)', $reporte['creditos'], 'debe', $y + 6, false);
        $y = $this->dibujarTabla('DETALLE DE DÉBITOS (salidas)', $reporte['debitos'], 'haber', $y + 6, false);
        $y = $this->dibujarTabla('CHEQUES EMITIDOS EN CIRCULACIÓN (no cobrados por el banco)', $reporte['cheques_no_cobrados'], 'haber', $y + 6, false, true);
        $y = $this->dibujarTabla('CHEQUES COBRADOS POR EL BANCO EN EL PERÍODO', $reporte['cheques_cobrados'], 'haber', $y + 6, true, true);

        $this->dibujarFirmas($y + 10);

        $filename = 'ConciliacionBancaria_' . date('Ymd_His') . '.pdf';
        if (ob_get_length()) {
            ob_end_clean();
        }
        $this->pdf->Output($filename, 'D');
        exit;
    }

    private function dibujarPortada(array $reporte, array $empresa): void
    {
        $pdf = $this->pdf;
        $pdf->AddPage();

        $logoPath = $this->resolverLogo($empresa);
        if ($logoPath !== '') {
            $pdf->Image($logoPath, 85, 30, 40, 0, '', '', 'T', false, 300);
            $pdf->SetY(75);
        } else {
            $pdf->SetY(50);
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(180, 0, 0);
            $pdf->Cell(0, 6, 'NO TIENE LOGO', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->Ln(6);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, mb_strtoupper((string) ($empresa['nombre'] ?? '')), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        if (!empty($empresa['ruc'])) {
            $pdf->Cell(0, 6, 'RUC: ' . $empresa['ruc'], 0, 1, 'C');
        }
        $direccion = (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? '');
        if ($direccion !== '') {
            $pdf->Cell(0, 6, $direccion, 0, 1, 'C');
        }

        $pdf->Ln(20);
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->Cell(0, 12, 'CONCILIACIÓN BANCARIA', 0, 1, 'C');

        $forma = $reporte['forma'];
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, $forma['nombre'] . (!empty($forma['nombre_banco']) ? ' — ' . $forma['nombre_banco'] : ''), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        if (!empty($forma['numero_cuenta'])) {
            $pdf->Cell(0, 6, 'N.° de cuenta: ' . $forma['numero_cuenta'], 0, 1, 'C');
        }
        if (!empty($forma['cuenta_codigo'])) {
            $pdf->Cell(0, 6, 'Cuenta contable: ' . $forma['cuenta_codigo'] . ' - ' . $forma['cuenta_nombre'], 0, 1, 'C');
        }

        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 11);
        $periodo = date('d-m-Y', strtotime($reporte['fecha_inicio'])) . '  al  ' . date('d-m-Y', strtotime($reporte['fecha_fin']));
        $pdf->Cell(0, 7, 'Período: ' . $periodo, 0, 1, 'C');

        $pdf->Ln(4);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 5, 'Generado el ' . date('d-m-Y H:i:s'), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

    private function dibujarResumen(array $reporte, float $y): float
    {
        $pdf = $this->pdf;
        $mL = $this->marginL;
        $r = $reporte['resumen'];
        $money = fn ($v) => number_format((float) $v, 2, '.', ',');

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell($this->contentW, 7, 'RESUMEN DEL PERÍODO', 0, 1, 'L');

        $w = $this->contentW / 4;
        $y = $pdf->GetY() + 1;
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($mL, $y);
        $pdf->Cell($w, 6, 'Saldo Inicial', 1, 0, 'C', true);
        $pdf->Cell($w, 6, 'Créditos', 1, 0, 'C', true);
        $pdf->Cell($w, 6, 'Débitos', 1, 0, 'C', true);
        $pdf->Cell($w, 6, 'Saldo Final', 1, 1, 'C', true);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetX($mL);
        $pdf->Cell($w, 7, '$ ' . $money($r['saldo_inicial']), 1, 0, 'C');
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell($w, 7, '$ ' . $money($r['creditos']), 1, 0, 'C');
        $pdf->SetTextColor(220, 53, 69);
        $pdf->Cell($w, 7, '$ ' . $money($r['debitos']), 1, 0, 'C');
        $pdf->SetTextColor(13, 110, 253);
        $pdf->Cell($w, 7, '$ ' . $money($r['saldo_final']), 1, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        return $pdf->GetY();
    }

    /**
     * Dibuja una tabla de movimientos o cheques. $campoMonto indica de qué columna sale el monto
     * (debe/haber). $esCheque agrega columna N.° Cheque; $conFechaBanco agrega Fecha de Cobro.
     */
    private function dibujarTabla(string $titulo, array $rows, string $campoMonto, float $y, bool $conFechaBanco, bool $esCheque = false): float
    {
        $pdf = $this->pdf;
        $mL = $this->marginL;
        $money = fn ($v) => number_format((float) $v, 2, '.', ',');

        if ($y > 250) {
            $pdf->AddPage();
            $y = 12;
        }

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($this->contentW, 6, $titulo, 0, 1, 'L');
        $y = $pdf->GetY();

        if ($esCheque) {
            $cols = $conFechaBanco
                ? [['t' => 'Emisión', 'w' => 22], ['t' => 'Cobro Banco', 'w' => 24], ['t' => 'N.° Cheque', 'w' => 26], ['t' => 'Beneficiario', 'w' => 0], ['t' => 'Monto', 'w' => 26, 'a' => 'R']]
                : [['t' => 'Emisión', 'w' => 22], ['t' => 'N.° Cheque', 'w' => 26], ['t' => 'Beneficiario', 'w' => 0], ['t' => 'Monto', 'w' => 26, 'a' => 'R']];
        } else {
            $cols = [['t' => 'Fecha', 'w' => 22], ['t' => 'Comprobante', 'w' => 28], ['t' => 'Tercero', 'w' => 45], ['t' => 'Glosa', 'w' => 0], ['t' => 'Monto', 'w' => 26, 'a' => 'R']];
        }
        $fixed = array_sum(array_column($cols, 'w'));
        foreach ($cols as &$c) {
            if ($c['w'] === 0) {
                $c['w'] = max(20, $this->contentW - $fixed);
            }
            $c['a'] = $c['a'] ?? 'L';
        }
        unset($c);

        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(90, 90, 90);
        $pdf->SetTextColor(255, 255, 255);
        foreach ($cols as $c) {
            $pdf->Cell($c['w'], 5.5, $c['t'], 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        if (empty($rows)) {
            $pdf->SetX($mL);
            $pdf->Cell(array_sum(array_column($cols, 'w')), 5.5, 'Sin registros.', 1, 1, 'C');
            return $pdf->GetY();
        }

        $total = 0.0;
        $alt = false;
        foreach ($rows as $r) {
            if ($pdf->GetY() > 265) {
                $pdf->AddPage();
                $pdf->SetX($mL);
            }
            $alt = !$alt;
            $pdf->SetFillColor(...($alt ? [245, 247, 250] : [255, 255, 255]));
            $monto = (float) $r[$campoMonto];
            $total += $monto;

            $vals = $esCheque
                ? ($conFechaBanco
                    ? [
                        !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                        !empty($r['fecha_banco']) ? date('d-m-Y', strtotime($r['fecha_banco'])) : '',
                        (string) ($r['numero_cheque'] ?? ''),
                        (string) ($r['nombre_entidad'] ?? ''),
                        '$ ' . $money($monto),
                    ]
                    : [
                        !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                        (string) ($r['numero_cheque'] ?? ''),
                        (string) ($r['nombre_entidad'] ?? ''),
                        '$ ' . $money($monto),
                    ])
                : [
                    !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                    (string) ($r['numero_comprobante'] ?: 'S/N'),
                    (string) ($r['nombre_entidad'] ?? ''),
                    (string) ($r['referencia_detalle'] ?: $r['concepto'] ?? ''),
                    '$ ' . $money($monto),
                ];

            $pdf->SetX($mL);
            foreach ($cols as $i => $c) {
                $pdf->Cell($c['w'], 5, $vals[$i], 1, 0, $c['a'], true);
            }
            $pdf->Ln();
        }

        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(235, 238, 242);
        $wSinMonto = array_sum(array_column($cols, 'w')) - end($cols)['w'];
        $pdf->Cell($wSinMonto, 5.5, 'TOTAL', 1, 0, 'R', true);
        $pdf->Cell(end($cols)['w'], 5.5, '$ ' . $money($total), 1, 1, 'R', true);

        return $pdf->GetY();
    }

    private function dibujarFirmas(float $y): void
    {
        $pdf = $this->pdf;
        $mL = $this->marginL;
        $colW = $this->contentW / 2;

        if ($y > 255) {
            $pdf->AddPage();
            $y = 20;
        }

        $yLinea = $y + 18;
        $pdf->Line($mL + 8, $yLinea, $mL + $colW - 8, $yLinea);
        $pdf->Line($mL + $colW + 8, $yLinea, $mL + $this->contentW - 8, $yLinea);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($mL, $yLinea + 2);
        $pdf->Cell($colW, 5, 'Realizado por', 0, 0, 'C');
        $pdf->Cell($colW, 5, 'Revisado por', 0, 1, 'C');
    }

    /** Resuelve la ruta en disco del logo de la empresa (mismo patrón que ComprobanteCajaPdfService). */
    private function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string) $ruta, '/');
            if (strpos($clean, 'sistema/public/') === 0) {
                $clean = substr($clean, strlen('sistema/public/'));
            } elseif (strpos($clean, 'sistema/') === 0) {
                $clean = substr($clean, strlen('sistema/'));
            }
            if (strpos($clean, 'public/') === 0) {
                $clean = substr($clean, strlen('public/'));
            }
            foreach ([\MVC_ROOT . '/public/' . $clean, \MVC_ROOT . '/' . $clean] as $cand) {
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
        return '';
    }
}
