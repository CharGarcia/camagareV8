<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF de una ORDEN DE COMPRA (documento interno, no electrónico ante el SRI).
 * A4 vertical: encabezado con logo + datos de la empresa, caja con los datos
 * de la orden y el proveedor, tabla de ítems (código/descripción/cantidad/
 * precio unitario/IVA/subtotal), resumen de subtotales por tarifa de IVA con el
 * TOTAL con impuestos, y observaciones.
 */
class OrdenCompraPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    public function generar(array $cabecera, array $detalles, array $empresa, string $outputDest = 'I')
    {
        $numero = (string) ($cabecera['numero_orden'] ?? '');

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Orden de Compra ' . $numero);
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, (string) ($cabecera['estado'] ?? 'borrador'));
        $y = $this->dibujarDatosOrden($cabecera, $y + 3);
        $y = $this->dibujarTablaDetalle($detalles, $y + 3);
        $this->dibujarObservaciones($cabecera, $y + 3);

        $nombre = 'OrdenCompra_' . ($numero !== '' ? $numero : 'comprobante') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function dibujarEncabezado(array $empresa, string $numero, string $estado): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y0  = 10;

        $izqW = 110;
        $derW = $this->contentW - $izqW - 2;
        $derX = $mL + $izqW + 2;

        // Logo (opcional)
        $logoPath = $this->resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 24, 0, '', '', 'T', false, 300);
            $textoX = $mL + 27;
        }

        // Datos de la empresa
        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell($mL + $izqW - $textoX, 5, strtoupper((string) ($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetX($textoX);
        $pdf->SetFont('helvetica', '', 8);
        $lineas = array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
            (string) ($empresa['correo'] ?? $empresa['email'] ?? ''),
        ]);
        foreach ($lineas as $ln) {
            $pdf->SetX($textoX);
            $pdf->MultiCell($mL + $izqW - $textoX, 4, $ln, 0, 'L', false, 1);
        }

        // Caja del comprobante (derecha)
        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell($derW, 5, 'ORDEN DE COMPRA', 0, 1, 'C');

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, $numero !== '' ? $numero : '—', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 5, 'Estado: ' . ucfirst($estado), 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    private function dibujarDatosOrden(array $c, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $fmtFecha = function ($v): string {
            if (empty($v)) return '';
            $ts = strtotime((string) $v);
            return $ts ? date('d/m/Y', $ts) : (string) $v;
        };

        $boxH = 26;
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'DF');

        $lblW = 30; $valW = 63; $lbl2W = 30;
        $val2W = $w - 4 - $lblW - $valW - $lbl2W;

        $par = function (string $l1, string $v1, string $l2, string $v2) use ($pdf, $mL, $lblW, $valW, $lbl2W, $val2W) {
            $pdf->SetX($mL + 2);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($lblW, 5, $l1, 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->Cell($valW, 5, $this->ajustarTexto($v1, $valW), 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($lbl2W, 5, $l2, 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->Cell($val2W, 5, $this->ajustarTexto($v2, $val2W), 0, 1, 'L');
        };

        $pdf->SetXY($mL + 2, $y + 1.5);
        $par('Proveedor:', (string) ($c['proveedor_nombre'] ?? '—'), 'Fecha orden:', $fmtFecha($c['fecha_orden'] ?? ''));
        $par('Identificación:', (string) ($c['proveedor_identificacion'] ?? ''), 'Fecha recepción:', $fmtFecha($c['fecha_recepcion'] ?? '') ?: '—');
        $par('', '', 'Estado:', ucfirst((string) ($c['estado'] ?? '')));

        return $y + $boxH;
    }

    private function dibujarTablaDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $cols = [
            ['t' => 'Código',      'w' => 25, 'a' => 'L', 'k' => 'codigo'],
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L', 'k' => 'descripcion'],
            ['t' => 'Cantidad',    'w' => 20, 'a' => 'R', 'k' => 'cantidad'],
            ['t' => 'P. Unitario', 'w' => 25, 'a' => 'R', 'k' => 'precio_unitario'],
            ['t' => 'IVA',         'w' => 16, 'a' => 'C', 'k' => 'iva'],
            ['t' => 'Subtotal',    'w' => 28, 'a' => 'R', 'k' => 'subtotal'],
        ];

        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(28.0, $this->contentW - $fixed);
        $descIdx = 1;
        foreach ($cols as $i => &$c) { if ($c['w'] === 0) { $c['w'] = $flex; $descIdx = $i; } }
        unset($c);

        // Encabezado
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

        // Filas
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Sin ítems.', 1, 1, 'C');
            return $pdf->GetY();
        }

        $alt = false;
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $cantidad  = (float) ($d['cantidad'] ?? 0);
            $precio    = (float) ($d['precio_unitario'] ?? 0);
            $subtotal  = round($cantidad * $precio, 2);
            $pctIva    = (float) ($d['porcentaje_iva'] ?? 0);

            // La nota de la línea (instrucción para el proveedor sobre ESE ítem) se imprime
            // como una línea más dentro de la celda de descripción, no como columna propia:
            // suele estar vacía y una columna extra restaría ancho a la descripción en todas
            // las filas.
            $nota = trim((string) ($d['notas'] ?? ''));

            $vals = [];
            foreach ($cols as $c) {
                $vals[] = match ($c['k']) {
                    'cantidad'        => number_format($cantidad, 2),
                    'precio_unitario' => number_format($precio, 2),
                    'iva'             => $this->formatearPorcentaje($pctIva) . '%',
                    'subtotal'        => number_format($subtotal, 2),
                    'descripcion'     => (string) ($d['descripcion'] ?? '')
                                         . ($nota !== '' ? "\nNota: " . $nota : ''),
                    default           => (string) ($d[$c['k']] ?? ''),
                };
            }

            // getNumLines() cuenta las líneas reales con la fuente activa, incluidos los
            // saltos explícitos de la nota; la estimación por GetStringWidth que había antes
            // ignoraba los "\n" y dejaba la nota fuera del recuadro de la fila.
            $descW = $cols[$descIdx]['w'];
            $nLin  = max(1, (int) $pdf->getNumLines((string) $vals[$descIdx], $descW));
            $h     = max(5.5, $nLin * 4.2);

            $x = $mL;
            $yRow = $pdf->GetY();
            foreach ($cols as $i => $c) {
                $pdf->SetXY($x, $yRow);
                if ($i === $descIdx) {
                    $pdf->MultiCell($c['w'], $h, $vals[$i], 1, $c['a'], true, 0, '', '', true, 0, false, true, $h, 'M');
                } else {
                    $pdf->Cell($c['w'], $h, $vals[$i], 1, 0, $c['a'], true);
                }
                $x += $c['w'];
            }
            $pdf->SetXY($mL, $yRow + $h);
        }

        // Resumen de totales: base sin impuestos, bases e IVA por tarifa, y TOTAL.
        // El cálculo (y su redondeo a centavos) vive en el Service, para que el PDF, el
        // Excel, el modal y la página de aprobación del proveedor cuadren al centavo.
        $t       = OrdenCompraService::calcularTotales($detalles);
        $valW    = $cols[count($cols) - 1]['w'];
        $lblW    = 52; // holgado: las etiquetas incluyen el nombre de la tarifa ("Subtotal No objeto de impuesto")
        $lblX    = $mL + $this->contentW - $valW - $lblW;

        $fila = function (string $etiqueta, float $valor, bool $destacada = false) use ($pdf, $lblX, $lblW, $valW): void {
            $pdf->SetX($lblX);
            if ($destacada) {
                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->SetFillColor(235, 237, 240);
            } else {
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetFillColor(255, 255, 255);
            }
            // La etiqueta se recorta al ancho de su celda con la fuente ya aplicada:
            // Cell() no ajusta el texto y una tarifa de nombre largo se saldría del recuadro.
            $pdf->Cell($lblW, 5, $this->ajustarTexto($etiqueta, $lblW - 2), 1, 0, 'R', true);
            $pdf->Cell($valW, 5, number_format($valor, 2), 1, 1, 'R', true);
        };

        $pdf->SetDrawColor(160, 160, 160);
        $fila('SUBTOTAL', $t['subtotal']);
        foreach ($t['grupos'] as $g) {
            $fila('Subtotal ' . $g['label'], $g['base']);
        }
        foreach ($t['grupos'] as $g) {
            if ($g['porcentaje'] > 0) {
                $fila('IVA ' . $this->formatearPorcentaje($g['porcentaje']) . '%', $g['iva']);
            }
        }
        if ($t['total_iva'] <= 0) {
            $fila('IVA', 0.0);
        }
        $fila('TOTAL', $t['total'], true);

        return $pdf->GetY();
    }

    /** 15.00 → "15"; 0.00 → "0"; 12.50 → "12.5" (para etiquetas de tarifa). */
    private function formatearPorcentaje(float $pct): string
    {
        $txt = number_format($pct, 2, '.', '');
        return rtrim(rtrim($txt, '0'), '.') ?: '0';
    }

    private function dibujarObservaciones(array $c, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $obs = trim((string) ($c['observaciones'] ?? ''));
        if ($obs === '') {
            return;
        }

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($w, 5, 'Observaciones:', 0, 1, 'L');
        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell($w, 4.5, $obs, 0, 'L', false, 1);
    }

    /** Resuelve la ruta en disco del logo (maneja el prefijo web /sistema/public). */
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

    /** Recorta un texto (con …) para que quepa en $ancho mm con la fuente actual. */
    private function ajustarTexto(string $txto, float $ancho): string
    {
        $txto = trim($txto);
        if ($txto === '' || $this->pdf->GetStringWidth($txto) <= $ancho) {
            return $txto;
        }
        while ($txto !== '' && $this->pdf->GetStringWidth($txto . '…') > $ancho) {
            $txto = mb_substr($txto, 0, -1);
        }
        return rtrim($txto) . '…';
    }
}
