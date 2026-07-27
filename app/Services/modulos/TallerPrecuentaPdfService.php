<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\Booleano;
use TCPDF;

/**
 * Precuenta del taller (A4 vertical).
 *
 * Es la cuenta que se le muestra al cliente ANTES de facturar: qué se le va a
 * cobrar y cuánto, para que revise y confirme. Equivale a la precuenta de un
 * restaurante — no tiene valor tributario y así lo advierte de forma visible.
 *
 * Solo entran las líneas aprobadas o ejecutadas que además son facturables: lo
 * rechazado y lo que trajo el cliente quedan fuera del total, aunque sí
 * aparezcan en el informe técnico.
 */
class TallerPrecuentaPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186;

    /** @param string $outputDest 'I' inline, 'D' descarga, 'S' string */
    public function generar(array $orden, array $empresa, string $outputDest = 'I')
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Precuenta ' . ($orden['numero_orden'] ?? ''));
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $orden);
        $y = $this->dibujarDatos($orden, $y + 3);
        $y = $this->dibujarDetalle($orden, $y + 3);
        $y = $this->dibujarTotales($orden, $y + 3);
        $this->dibujarAviso($orden, $y + 4);

        $nombre = 'Precuenta_' . (($orden['numero_orden'] ?? '') !== '' ? $orden['numero_orden'] : 'OT') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    /** Líneas que realmente se van a cobrar. */
    public static function lineasCobrables(array $orden): array
    {
        return array_values(array_filter(
            $orden['detalles'] ?? [],
            fn($d) => Booleano::es($d['facturable'] ?? false)
                   && in_array((string) ($d['estado_linea'] ?? ''), ['aprobada', 'ejecutada'], true)
                   && (float) ($d['cantidad'] ?? 0) > 0
        ));
    }

    // ─── Encabezado ───────────────────────────────────────────────────────────
    private function dibujarEncabezado(array $empresa, array $orden): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y0  = 10;

        $izqW = 110;
        $derW = $this->contentW - $izqW - 2;
        $derX = $mL + $izqW + 2;

        $logoPath = TallerPdfHelper::resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 24, 0, '', '', 'T', false, 300);
            $textoX = $mL + 27;
        }

        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell($mL + $izqW - $textoX, 5, strtoupper((string) ($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetX($textoX);
        $pdf->SetFont('helvetica', '', 8);
        foreach (array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
        ]) as $ln) {
            $pdf->SetX($textoX);
            $pdf->MultiCell($mL + $izqW - $textoX, 4, $ln, 0, 'L', false, 1);
        }

        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($derW, 5, 'PRECUENTA', 0, 1, 'C');

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'Orden N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, trim((string) ($orden['numero_orden'] ?? '—')), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell($derW, 4, date('d/m/Y H:i'), 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    // ─── Vehículo y cliente ───────────────────────────────────────────────────
    private function dibujarDatos(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $boxH = 16;
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $this->contentW, $boxH, 1.5, '1111', 'DF');

        $lbl = function (string $t, float $w = 22) use ($pdf) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($w, 5, $t, 0, 0, 'L');
        };
        $val = function (string $t, float $w, int $ln = 0) use ($pdf) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell($w, 5, $t, 0, $ln, 'L');
        };

        $vehiculo = trim((string) ($orden['marca'] ?? '') . ' ' . (string) ($orden['modelo'] ?? '') . ' ' . (string) ($orden['anio'] ?? ''));

        $pdf->SetXY($mL + 2, $y + 2);
        $lbl('Placa:');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(38, 5, strtoupper((string) ($orden['placa'] ?? '—')), 0, 0, 'L');
        $lbl('Vehículo:', 20);
        $val(TallerPdfHelper::ajustar($pdf, $vehiculo !== '' ? $vehiculo : '—', 80), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Cliente:');
        $val(TallerPdfHelper::ajustar($pdf, (string) ($orden['cliente_nombre'] ?? '—'), 90), 90);
        $lbl('Ident.:', 18);
        $val((string) ($orden['cliente_identificacion'] ?? '—'), 0, 1);

        return $y + $boxH;
    }

    // ─── Lo que se va a cobrar ────────────────────────────────────────────────
    private function dibujarDetalle(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'DETALLE A PAGAR');

        $cols = [
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L'],
            ['t' => 'Tipo',        'w' => 24, 'a' => 'C'],
            ['t' => 'Cant.',       'w' => 18, 'a' => 'R'],
            ['t' => 'P. Unit',     'w' => 24, 'a' => 'R'],
            ['t' => 'Total',       'w' => 26, 'a' => 'R'],
        ];
        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = max(40.0, $this->contentW - $fixed); } }
        unset($c);

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->SetLineWidth(0.2);
        foreach ($cols as $c) { $pdf->Cell($c['w'], 6, $c['t'], 1, 0, 'C', true); }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);

        $lineas = self::lineasCobrables($orden);
        if (empty($lineas)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Todavía no hay trabajos aprobados que cobrar.', 1, 1, 'C');
            return $pdf->GetY();
        }

        $alt = false;
        foreach ($lineas as $d) {
            if ($pdf->GetY() > 240) {
                $pdf->AddPage();
                $pdf->SetY(15);
            }
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $vals = [
                (string) ($d['descripcion'] ?? ''),
                TallerPdfHelper::etiquetaTipoLinea((string) ($d['tipo_linea'] ?? '')),
                number_format((float) ($d['cantidad'] ?? 0), 2),
                number_format((float) ($d['precio_unitario'] ?? 0), 2),
                number_format((float) ($d['total_linea'] ?? 0), 2),
            ];

            $descW = $cols[0]['w'];
            $nLin  = max(1, (int) ceil(max(1, $pdf->GetStringWidth($vals[0])) / max(1, $descW - 2)));
            $h     = max(5.5, $nLin * 4.4);

            $x = $mL; $yRow = $pdf->GetY();
            foreach ($cols as $i => $c) {
                $pdf->SetXY($x, $yRow);
                if ($i === 0) {
                    $pdf->MultiCell($c['w'], $h, $vals[$i], 1, $c['a'], true, 0, '', '', true, 0, false, true, $h, 'M');
                } else {
                    $pdf->Cell($c['w'], $h, $vals[$i], 1, 0, $c['a'], true);
                }
                $x += $c['w'];
            }
            $pdf->SetXY($mL, $yRow + $h);
        }

        return $pdf->GetY();
    }

    // ─── Totales ──────────────────────────────────────────────────────────────
    private function dibujarTotales(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $tW = 70;
        $tX = $mL + $this->contentW - $tW;

        $fila = function (string $lbl, float $val, bool $fuerte = false) use ($pdf, $tX, $tW) {
            $pdf->SetX($tX);
            $pdf->SetFont('helvetica', $fuerte ? 'B' : '', $fuerte ? 11 : 9);
            if ($fuerte) {
                $pdf->SetFillColor(60, 70, 90);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetDrawColor(60, 70, 90);
                $pdf->Cell(36, 8, $lbl, 1, 0, 'C', true);
                $pdf->Cell($tW - 36, 8, '$ ' . number_format($val, 2), 1, 1, 'R', true);
                $pdf->SetTextColor(0, 0, 0);
            } else {
                $pdf->Cell(36, 5.5, $lbl, 0, 0, 'R');
                $pdf->Cell($tW - 36, 5.5, number_format($val, 2), 0, 1, 'R');
            }
        };

        $pdf->SetXY($tX, $y);
        $fila('Repuestos', (float) ($orden['subtotal_repuestos'] ?? 0));
        $fila('Mano de obra', (float) ($orden['subtotal_mano_obra'] ?? 0));
        if ((float) ($orden['descuento'] ?? 0) > 0) $fila('Descuento', (float) $orden['descuento']);
        $fila('IVA', (float) ($orden['iva'] ?? 0));
        $fila('TOTAL A PAGAR', (float) ($orden['total'] ?? 0), true);
        $yTotales = $pdf->GetY();

        $letras = TallerPdfHelper::montoEnLetras((float) ($orden['total'] ?? 0));
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->MultiCell($this->contentW - $tW - 4, 4, 'SON: ' . $letras . ' DÓLARES', 0, 'L', false, 1);

        return max($yTotales, $pdf->GetY());
    }

    // ─── Aviso: esto no es un comprobante ─────────────────────────────────────
    private function dibujarAviso(array $orden, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        if ($y > 255) {
            $pdf->AddPage();
            $y = 20;
        }

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetFillColor(255, 243, 205);
        $pdf->SetDrawColor(200, 170, 80);
        $pdf->SetLineWidth(0.2);
        $pdf->MultiCell($this->contentW, 6,
            ' DOCUMENTO SIN VALOR TRIBUTARIO — Es un detalle previo de los valores a pagar. '
            . 'El comprobante válido se entrega al momento del pago.',
            1, 'C', true, 1);

        // Trabajos pendientes de aprobación: el cliente debe saber que el total
        // puede crecer si autoriza lo que falta.
        $pendientes = array_values(array_filter(
            $orden['detalles'] ?? [],
            fn($d) => (string) ($d['estado_linea'] ?? '') === 'sugerida'
        ));
        if (!empty($pendientes)) {
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->MultiCell($this->contentW, 4,
                ' Hay ' . count($pendientes) . ' trabajo(s) sugerido(s) que el cliente todavía no aprueba; '
                . 'no están incluidos en este total.',
                0, 'L', false, 1);
        }
    }
}
