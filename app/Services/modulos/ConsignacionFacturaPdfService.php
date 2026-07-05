<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Modelo general (por defecto) del PDF del documento "Facturación de Consignación".
 *
 * A4 vertical. Encabezado tipo comprobante (logo + empresa a la izquierda, caja con
 * título/número a la derecha), datos del cliente a facturar + vendedor + factura
 * relacionada, tabla de productos (Código, Descripción, Lote, Cantidad, Precio,
 * Subtotal, IVA, Total), totales y observaciones.
 */
class ConsignacionFacturaPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186;

    public function generar(array $cabecera, array $detalles, array $empresa, string $outputDest = 'I')
    {
        $numero = trim(((string)($cabecera['serie'] ?? '')) . '-' . ((string)($cabecera['secuencial'] ?? '')), '-');

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor((string)($empresa['nombre'] ?? ''));
        $this->pdf->SetTitle('Facturación de Consignación ' . $numero);
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, (string)($cabecera['estado'] ?? 'borrador'));
        $y = $this->dibujarDatosCliente($cabecera, $y + 3);
        $y = $this->dibujarTablaDetalle($detalles, $y + 3);
        $y = $this->dibujarTotales($cabecera, $detalles, $y + 1);
        $y = $this->dibujarObservaciones($cabecera, $y + 3);
        $this->dibujarInfoAdicional($cabecera, $y + 2);

        $nombre = 'FacturacionConsignacion_' . ($numero !== '' ? $numero : 'comprobante') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    private function dibujarEncabezado(array $empresa, string $numero, string $estado): float
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

        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($derW, 5, 'FACTURACIÓN DE CONSIGNACIÓN', 0, 1, 'C');
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

    private function dibujarDatosCliente(array $c, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $fmtFecha = function ($v): string {
            if (empty($v)) return '';
            $ts = strtotime((string)$v);
            return $ts ? date('d/m/Y', $ts) : (string)$v;
        };

        $boxH = 18;
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
        $par('Cliente:', (string)($c['cliente_nombre'] ?? '—'), 'Fecha:', $fmtFecha($c['fecha_emision'] ?? ''));
        $par('Identificación:', (string)($c['cliente_identificacion'] ?? ''), 'Vendedor:', (string)($c['vendedor_nombre'] ?? '—'));
        $par('Dirección:', (string)($c['cliente_direccion'] ?? ''), 'Factura:', (string)($c['numero_factura'] ?? '—'));

        return $y + $boxH;
    }

    /** Tabla: Código | Descripción | Lote | Cant | Precio | Subtotal | IVA | Total. */
    private function dibujarTablaDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $cols = [
            ['t' => 'Código',      'w' => 20, 'a' => 'L', 'k' => 'producto_codigo'],
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L', 'k' => 'producto_nombre'],
            ['t' => 'Lote',        'w' => 18, 'a' => 'L', 'k' => 'lote'],
            ['t' => 'Cant.',       'w' => 15, 'a' => 'R', 'k' => 'cantidad'],
            ['t' => 'Precio',      'w' => 18, 'a' => 'R', 'k' => 'precio_unitario'],
            ['t' => 'Desc.',       'w' => 16, 'a' => 'R', 'k' => 'descuento'],
            ['t' => 'Subtotal',    'w' => 20, 'a' => 'R', 'k' => 'subtotal'],
            ['t' => 'IVA',         'w' => 16, 'a' => 'R', 'k' => 'valor_impuesto'],
            ['t' => 'Total',       'w' => 20, 'a' => 'R', 'k' => 'total'],
        ];

        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(30.0, $this->contentW - $fixed);
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = $flex; } }
        unset($c);
        $descIdx = 1;

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->SetLineWidth(0.2);
        foreach ($cols as $c) {
            $pdf->Cell($c['w'], 6, $c['t'], 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Sin productos.', 1, 1, 'C');
            return $pdf->GetY();
        }

        $alt = false;
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $vals = [];
            foreach ($cols as $c) {
                $raw = $d[$c['k']] ?? '';
                if (in_array($c['k'], ['cantidad'], true)) {
                    $vals[] = number_format((float)$raw, 2);
                } elseif (in_array($c['k'], ['precio_unitario', 'descuento', 'subtotal', 'valor_impuesto', 'total'], true)) {
                    $vals[] = number_format((float)$raw, 2);
                } else {
                    $v = trim((string)$raw);
                    $vals[] = ($v !== '' && $v !== 'sin_lote') ? $v : '—';
                }
            }

            $descW = $cols[$descIdx]['w'];
            $nLin  = max(1, (int)ceil(max(1, $pdf->GetStringWidth((string)$vals[$descIdx])) / max(1, $descW - 2)));
            $h     = max(5.0, $nLin * 4.2);

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

        return $pdf->GetY();
    }

    private function dibujarTotales(array $c, array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $bruto = 0.0; $desc = 0.0;
        foreach ($detalles as $d) {
            $bruto += (float)($d['precio_unitario'] ?? 0) * (float)($d['cantidad'] ?? 0);
            $desc  += (float)($d['descuento'] ?? 0);
        }

        $boxW = 70;
        $x = $mL + $w - $boxW;
        $lblW = 40; $valW = $boxW - $lblW;

        $rows = [
            ['Subtotal:',  round($bruto, 2)],
            ['Descuento:', round($desc, 2)],
            ['IVA:',       (float)($c['impuesto'] ?? 0)],
            ['TOTAL:',     (float)($c['total'] ?? 0)],
        ];
        $yy = $y;
        foreach ($rows as $i => $r) {
            $bold = ($i === count($rows) - 1);
            $pdf->SetXY($x, $yy);
            $pdf->SetFont('helvetica', 'B', $bold ? 9 : 8);
            $pdf->Cell($lblW, 5, $r[0], 0, 0, 'R');
            $pdf->SetFont('helvetica', $bold ? 'B' : '', $bold ? 9 : 8);
            $pdf->Cell($valW, 5, number_format($r[1], 2), 1, 0, 'R', $bold);
            $yy += 5;
        }
        return $yy;
    }

    private function dibujarObservaciones(array $c, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;
        $obs = trim((string)($c['observaciones'] ?? ''));
        if ($obs === '') return $y;

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(28, 5, 'Observaciones:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->MultiCell($w - 28, 5, $obs, 0, 'L', false, 1);
        return $pdf->GetY();
    }

    /** Bloque de Información Adicional [{nombre, valor}]. */
    private function dibujarInfoAdicional(array $c, float $y): void
    {
        $info = $c['info_adicional'] ?? [];
        if (is_string($info)) {
            $dec = json_decode($info, true);
            $info = is_array($dec) ? $dec : [];
        }
        if (!is_array($info) || empty($info)) return;

        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($w, 5, 'Información adicional', 0, 1, 'L');

        foreach ($info as $ia) {
            $nombre = trim((string)($ia['nombre'] ?? $ia['concepto'] ?? ''));
            $valor  = trim((string)($ia['valor'] ?? $ia['detalle'] ?? ''));
            if ($nombre === '' && $valor === '') continue;
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(40, 4.5, $this->ajustarTexto($nombre . ':', 40), 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->MultiCell($w - 40, 4.5, $valor, 0, 'L', false, 1);
        }
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
