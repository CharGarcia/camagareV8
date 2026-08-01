<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF de un Asiento Contable individual (Libro Diario). A4 vertical: encabezado
 * empresa + datos del comprobante, tabla de líneas (cuenta, centro costo,
 * proyecto, documento/ref, debe, haber) y totales.
 */
class AsientoContablePdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    public function generar(array $asiento, array $empresa, string $outputDest = 'D')
    {
        require_once \MVC_ROOT . '/vendor/autoload.php';

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Asiento Contable ' . ($asiento['numero_comprobante'] ?? ''));
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $asiento);
        $y = $this->dibujarDatosAsiento($asiento, $y + 3);
        $y = $this->dibujarTablaDetalle($asiento['detalles'] ?? [], $y + 3);
        $this->dibujarTotales($asiento, $y + 2);

        $nombre = 'Asiento_' . ($asiento['numero_comprobante'] ?: 'comprobante') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    private function dibujarEncabezado(array $empresa, array $asiento): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y0  = 10;

        $izqW = 110;
        $derW = $this->contentW - $izqW - 2;
        $derX = $mL + $izqW + 2;

        $logoPath = $this->resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 24, 0, '', '', 'T', false, 300);
            $textoX = $mL + 27;
        }

        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell($mL + $izqW - $textoX, 5, strtoupper((string)($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetX($textoX);
        $pdf->SetFont('helvetica', '', 8);
        $lineas = array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string)($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
            (string)($empresa['correo'] ?? $empresa['email'] ?? ''),
        ]);
        foreach ($lineas as $ln) {
            $pdf->SetX($textoX);
            $pdf->MultiCell($mL + $izqW - $textoX, 4, $ln, 0, 'L', false, 1);
        }

        $boxH = 26;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($derW, 5, 'ASIENTO CONTABLE', 0, 1, 'C');

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, (string)($asiento['numero_comprobante'] ?: '—'), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 5, strtoupper(str_replace('_', ' ', (string)($asiento['tipo_comprobante'] ?? ''))), 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    private function dibujarDatosAsiento(array $asiento, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $fecha = '';
        if (!empty($asiento['fecha_asiento'])) {
            $ts = strtotime((string)$asiento['fecha_asiento']);
            $fecha = $ts ? date('d/m/Y', $ts) : (string)$asiento['fecha_asiento'];
        }
        $estado = ucfirst((string)($asiento['estado'] ?? ''));

        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, 14, 1.5, '1111', 'DF');

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL + 2, $y + 2);
        $pdf->Cell(18, 5, 'Fecha:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(60, 5, $fecha, 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(18, 5, 'Estado:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, $estado, 0, 1, 'L');

        $concepto = trim((string)($asiento['concepto'] ?? ''));
        $pdf->SetXY($mL + 2, $pdf->GetY());
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(20, 5, 'Concepto:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell($w - 24, 5, $concepto !== '' ? $concepto : '—', 0, 'L', false, 1);

        return max($pdf->GetY(), $y + 14);
    }

    private function dibujarTablaDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $cols = [
            ['t' => 'Cuenta Contable', 'w' => 0,  'a' => 'L'],
            ['t' => 'Centro Costo',    'w' => 28, 'a' => 'L'],
            ['t' => 'Proyecto',        'w' => 26, 'a' => 'L'],
            ['t' => 'Documento/Ref',   'w' => 26, 'a' => 'L'],
            ['t' => 'Debe',            'w' => 26, 'a' => 'R'],
            ['t' => 'Haber',           'w' => 26, 'a' => 'R'],
        ];
        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(40.0, $this->contentW - $fixed);
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = $flex; } }
        unset($c);

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->SetLineWidth(0.2);
        foreach ($cols as $c) {
            $pdf->Cell($c['w'], 6, $c['t'], 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        $alt = false;
        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Sin líneas.', 1, 1, 'C');
        }
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $cuenta = trim((string)($d['codigo_cuenta'] ?? '') . ' - ' . (string)($d['nombre_cuenta'] ?? ''), ' -');
            $vals = [
                $cuenta,
                (string)($d['nombre_centro_costo'] ?? ''),
                (string)($d['nombre_proyecto'] ?? ''),
                (string)($d['documento_referencia'] ?? ''),
                (float)($d['debe'] ?? 0) > 0 ? number_format((float)$d['debe'], 2) : '',
                (float)($d['haber'] ?? 0) > 0 ? number_format((float)$d['haber'], 2) : '',
            ];

            // Altura de fila = la mayor cantidad de líneas que necesite cualquier
            // columna (cuenta, centro costo, proyecto o documento/ref), para que el
            // texto se ajuste (wrap) en vez de recortarse con "…".
            $nLinMax = 1;
            foreach ($cols as $i => $c) {
                $nLinMax = max($nLinMax, $pdf->getNumLines($vals[$i] !== '' ? $vals[$i] : ' ', $c['w']));
            }
            $h = max(5.0, $nLinMax * 4.2);

            $x = $mL;
            $yRow = $pdf->GetY();
            foreach ($cols as $i => $c) {
                $pdf->SetXY($x, $yRow);
                $pdf->MultiCell($c['w'], $h, $vals[$i], 1, $c['a'], true, 0, '', '', true, 0, false, true, $h, 'M');
                $x += $c['w'];
            }
            $pdf->SetXY($mL, $yRow + $h);
        }

        return $pdf->GetY();
    }

    private function dibujarTotales(array $asiento, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $totDebe  = (float)($asiento['total_debe'] ?? 0);
        $totHaber = (float)($asiento['total_haber'] ?? 0);

        $wLbl = $this->contentW - 26 - 26;
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(235, 238, 242);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->Cell($wLbl, 6, 'TOTALES', 1, 0, 'R', true);
        $pdf->Cell(26, 6, number_format($totDebe, 2), 1, 0, 'R', true);
        $pdf->Cell(26, 6, number_format($totHaber, 2), 1, 1, 'R', true);
    }

    private function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string)$ruta, '/');
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
