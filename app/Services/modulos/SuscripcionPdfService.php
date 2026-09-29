<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF con el detalle de UNA suscripción (botón PDF del modal de Suscripciones).
 *
 * A4 vertical: encabezado con logo y datos de la empresa + recuadro con el N.° y el estado;
 * tarjetas de Cliente y Datos de la suscripción; tabla de productos/servicios que se
 * facturan en cada cobro con sus totales (subtotal por tarifa, IVA y total por cobro);
 * información adicional, forma de cobro y observaciones; historial de cobros; pie con
 * fecha de generación y número de página.
 */
class SuscripcionPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    /** Color principal del documento (azul) y su versión suave para fondos. */
    private const COLOR     = [31, 78, 121];
    private const COLOR_SUA = [236, 242, 249];
    private const GRIS_TXT  = [90, 98, 110];

    private const ESTADOS = [
        'activo'     => ['Activo',     [25, 135, 84]],
        'pausado'    => ['Pausado',    [230, 150, 0]],
        'suspendido' => ['Suspendido', [220, 53, 69]],
        'cancelado'  => ['Cancelado',  [108, 117, 125]],
    ];

    public function generar(array $cabecera, array $detalle, array $pagos, array $empresa, string $outputDest = 'I')
    {
        $numero = str_pad((string) (int) ($cabecera['id'] ?? 0), 6, '0', STR_PAD_LEFT);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor((string) ($empresa['nombre'] ?? ''));
        $this->pdf->SetTitle('Suscripción ' . $numero);
        $this->pdf->SetMargins($this->marginL, 12, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 18);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, (string) ($cabecera['estado'] ?? 'activo'));
        $y = $this->dibujarTarjetas($cabecera, $y + 5);
        $y = $this->dibujarDetalle($detalle, $y + 5);
        $y = $this->dibujarInfoAdicional($cabecera, $y + 5);
        $y = $this->dibujarCobro($cabecera, $y + 5);
        $this->dibujarPagos($pagos, $y + 5);
        $this->dibujarPies((string) ($empresa['nombre'] ?? ''), $numero);

        $nombre = 'Suscripcion_' . $numero . '.pdf';
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
        $y0  = 12;

        // Franja superior con el color del documento.
        $pdf->SetFillColor(...self::COLOR);
        $pdf->Rect(0, 0, 210, 5, 'F');

        $izqW = 118;
        $derW = $this->contentW - $izqW - 4;
        $derX = $mL + $izqW + 4;

        $logoPath = $this->resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 30, 24, '', '', 'T', false, 300, '', false, false, 0, 'LT', false, false);
            $textoX = $mL + 34;
        }

        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->MultiCell($mL + $izqW - $textoX, 5.5, mb_strtoupper((string) ($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetTextColor(...self::GRIS_TXT);
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
        $yIzq = $pdf->GetY();
        $pdf->SetTextColor(0, 0, 0);

        // Recuadro derecho: tipo de documento, número y estado.
        $boxH = 32;
        $pdf->SetFillColor(...self::COLOR_SUA);
        $pdf->SetDrawColor(...self::COLOR);
        $pdf->SetLineWidth(0.3);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 2, '1111', 'DF');
        $pdf->SetFillColor(...self::COLOR);
        $pdf->RoundedRect($derX, $y0, $derW, 8, 2, '1001', 'F');

        $pdf->SetXY($derX, $y0 + 1.5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($derW, 5, 'SUSCRIPCIÓN', 0, 1, 'C');

        $pdf->SetXY($derX, $y0 + 10);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(...self::GRIS_TXT);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($derW, 7, $numero, 0, 1, 'C');

        // Píldora con el estado.
        [$txtEstado, $colEstado] = self::ESTADOS[strtolower($estado)] ?? [ucfirst($estado), [108, 117, 125]];
        $pdf->SetFont('helvetica', 'B', 8);
        $pilW = $pdf->GetStringWidth(mb_strtoupper($txtEstado)) + 10;
        $pilX = $derX + ($derW - $pilW) / 2;
        $pilY = $y0 + 23;
        $pdf->SetFillColor(...$colEstado);
        $pdf->RoundedRect($pilX, $pilY, $pilW, 5.5, 2.75, '1111', 'F');
        $pdf->SetXY($pilX, $pilY);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($pilW, 5.5, mb_strtoupper($txtEstado), 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $yFin = max($yIzq, $y0 + $boxH) + 3;
        $pdf->SetDrawColor(...self::COLOR);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($mL, $yFin, $mL + $this->contentW, $yFin);
        return $yFin;
    }

    /** Dos tarjetas lado a lado: Cliente | Datos de la suscripción. */
    private function dibujarTarjetas(array $c, float $y): float
    {
        $gap  = 4;
        $colW = ($this->contentW - $gap) / 2;

        $cliente = [
            ['Nombre',         (string) ($c['nombre_cliente'] ?? '')],
            ['RUC / Cédula',   (string) ($c['identificacion_cliente'] ?? '')],
            ['Correo',         (string) ($c['email_cliente'] ?? '')],
            ['Teléfono',       (string) ($c['telefono_cliente'] ?? '')],
            ['Dirección',      (string) ($c['direccion_cliente'] ?? '')],
        ];
        $comprobante = ($c['tipo_comprobante'] ?? 'factura') === 'recibo' ? 'Recibo de Venta' : 'Factura de Venta';
        $datos = [
            ['Periodicidad',   (string) ($c['nombre_periodicidad'] ?? '')],
            ['Comprobante',    $comprobante],
            ['Fecha inicio',   $this->fecha($c['fecha_inicio'] ?? null)],
            ['Fecha fin',      $this->fecha($c['fecha_fin'] ?? null, 'Indefinida')],
            ['Próximo cobro',  $this->fecha($c['proximo_cobro'] ?? null)],
        ];

        $h1 = $this->dibujarTarjeta('CLIENTE', $cliente, $this->marginL, $y, $colW);
        $h2 = $this->dibujarTarjeta('DATOS DE LA SUSCRIPCIÓN', $datos, $this->marginL + $colW + $gap, $y, $colW);
        return $y + max($h1, $h2);
    }

    /** Tarjeta con título en banda de color y pares etiqueta/valor. Devuelve su alto. */
    private function dibujarTarjeta(string $titulo, array $pares, float $x, float $y, float $w): float
    {
        $pdf  = $this->pdf;
        $lblW = 25;
        $valW = $w - $lblW - 5;

        $pdf->SetFont('helvetica', '', 8.5);
        $alto = 9;
        foreach ($pares as [, $v]) {
            $alto += max(1, $pdf->getNumLines($v !== '' ? $v : '—', $valW)) * 4.3;
        }
        $alto += 2;

        $pdf->SetDrawColor(210, 218, 228);
        $pdf->SetLineWidth(0.25);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->RoundedRect($x, $y, $w, $alto, 2, '1111', 'DF');
        $pdf->SetFillColor(...self::COLOR_SUA);
        $pdf->RoundedRect($x, $y, $w, 7, 2, '1001', 'F');

        $pdf->SetXY($x + 3, $y + 1);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($w - 6, 5, $titulo, 0, 1, 'L');

        $yy = $y + 9;
        foreach ($pares as [$l, $v]) {
            $pdf->SetXY($x + 3, $yy);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetTextColor(...self::GRIS_TXT);
            $pdf->Cell($lblW, 4.3, $l, 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->MultiCell($valW, 4.3, $v !== '' ? $v : '—', 0, 'L', false, 1, $x + 3 + $lblW, $yy);
            $yy = $pdf->GetY();
        }
        $pdf->SetTextColor(0, 0, 0);
        return $alto;
    }

    /** Tabla de productos/servicios con totales por cobro. */
    private function dibujarDetalle(array $detalle, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $y = $this->tituloSeccion('PRODUCTOS / SERVICIOS QUE SE FACTURAN EN CADA COBRO', $y, 20);

        $cols = [
            ['t' => '#',            'w' => 8,  'a' => 'C'],
            ['t' => 'Código',       'w' => 24, 'a' => 'L'],
            ['t' => 'Descripción',  'w' => 0,  'a' => 'L'],
            ['t' => 'Cant.',        'w' => 16, 'a' => 'R'],
            ['t' => 'P. Unitario',  'w' => 22, 'a' => 'R'],
            ['t' => 'IVA',          'w' => 14, 'a' => 'C'],
            ['t' => 'Subtotal',     'w' => 24, 'a' => 'R'],
        ];
        $fijo = 0.0;
        foreach ($cols as $c) { $fijo += $c['w']; }
        $cols[2]['w'] = $this->contentW - $fijo;

        $cabecera = function (float $yEnc) use ($pdf, $cols, $mL): float {
            $pdf->SetXY($mL, $yEnc);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(...self::COLOR);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetDrawColor(...self::COLOR);
            $pdf->SetLineWidth(0.2);
            foreach ($cols as $c) {
                $pdf->Cell($c['w'], 6.5, $c['t'], 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(30, 30, 30);
            return $yEnc + 6.5;
        };
        $cabecera($y);

        $subtotal   = 0.0;
        $iva        = 0.0;
        $porTarifa  = [];

        if (empty($detalle)) {
            $pdf->SetX($mL);
            $pdf->SetDrawColor(210, 218, 228);
            $pdf->Cell($this->contentW, 7, 'La suscripción no tiene productos ni servicios.', 1, 1, 'C');
        }

        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();
        foreach (array_values($detalle) as $i => $d) {
            $cant   = (float) ($d['cantidad'] ?? 0);
            $precio = (float) ($d['precio_unitario'] ?? 0);
            $pct    = (float) ($d['porcentaje_iva'] ?? 0);
            $sub    = round($cant * $precio, 2);
            $subtotal += $sub;
            $iva      += round($sub * $pct / 100, 2);
            $clave     = $this->numLibre($pct) . '%';
            $porTarifa[$clave] = ($porTarifa[$clave] ?? 0) + $sub;

            $desc = trim((string) ($d['descripcion'] ?? '')) !== '' ? (string) $d['descripcion'] : (string) ($d['nombre_producto'] ?? '');
            $vals = [
                (string) ($i + 1),
                (string) ($d['codigo_producto'] ?? ''),
                $desc !== '' ? $desc : '—',
                $this->numLibre($cant),
                $this->numLibre($precio, 2),
                $clave,
                number_format($sub, 2, '.', ','),
            ];

            $h    = max(6.0, max(1, $pdf->getNumLines($vals[2], $cols[2]['w'] - 1)) * 4.2 + 1.5);
            $yRow = $pdf->GetY();
            if ($yRow + $h > $limiteY) {
                $pdf->AddPage();
                $yRow = $cabecera($pdf->GetY());
            }
            $pdf->SetFillColor(...($i % 2 ? [247, 249, 252] : [255, 255, 255]));
            $pdf->SetDrawColor(220, 226, 234);

            $x = $mL;
            foreach ($cols as $k => $c) {
                $pdf->SetXY($x, $yRow);
                if ($k === 2) {
                    $pdf->MultiCell($c['w'], $h, $vals[$k], 'B', $c['a'], true, 0, '', '', true, 0, false, true, $h, 'M');
                } else {
                    $pdf->Cell($c['w'], $h, $vals[$k], 'B', 0, $c['a'], true, '', 1);
                }
                $x += $c['w'];
            }
            $pdf->SetXY($mL, $yRow + $h);
        }

        // Totales a la derecha.
        $filas = [];
        ksort($porTarifa, SORT_NATURAL);
        foreach ($porTarifa as $tarifa => $monto) {
            $filas[] = ['Subtotal ' . $tarifa, $monto, false];
        }
        if (count($porTarifa) > 1) {
            $filas[] = ['Subtotal sin impuestos', $subtotal, false];
        }
        $filas[] = ['IVA', $iva, false];
        $filas[] = ['TOTAL POR COBRO', $subtotal + $iva, true];

        $totW = 72;
        $totX = $mL + $this->contentW - $totW;
        $yT   = $pdf->GetY() + 2;
        if ($yT + count($filas) * 6 + 2 > $limiteY) {
            $pdf->AddPage();
            $yT = $pdf->GetY();
        }
        foreach ($filas as [$lbl, $monto, $grande]) {
            $pdf->SetXY($totX, $yT);
            if ($grande) {
                $pdf->SetFillColor(...self::COLOR);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 9.5);
                $pdf->Cell($totW - 28, 7.5, ' ' . $lbl, 0, 0, 'L', true);
                $pdf->Cell(28, 7.5, '$ ' . number_format($monto, 2, '.', ',') . ' ', 0, 1, 'R', true);
                $yT += 7.5;
            } else {
                $pdf->SetTextColor(...self::GRIS_TXT);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->Cell($totW - 28, 5.5, ' ' . $lbl, 'B', 0, 'L');
                $pdf->SetTextColor(30, 30, 30);
                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->Cell(28, 5.5, number_format($monto, 2, '.', ',') . ' ', 'B', 1, 'R');
                $yT += 5.5;
            }
        }
        $pdf->SetTextColor(0, 0, 0);
        return $yT;
    }

    private function dibujarInfoAdicional(array $c, float $y): float
    {
        $raw   = $c['info_adicional'] ?? null;
        $filas = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        $filas = array_values(array_filter($filas, static fn ($f) =>
            is_array($f) && (trim((string) ($f['concepto'] ?? '')) !== '' || trim((string) ($f['detalle'] ?? '')) !== '')));
        if (empty($filas)) {
            return $y - 5;
        }

        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y   = $this->tituloSeccion('INFORMACIÓN ADICIONAL', $y, 14);

        $lblW = 55;
        $valW = $this->contentW - $lblW;
        $pdf->SetDrawColor(220, 226, 234);
        foreach ($filas as $i => $f) {
            $det = trim((string) ($f['detalle'] ?? ''));
            $pdf->SetFont('helvetica', '', 8.5);
            $h = max(6.0, max(1, $pdf->getNumLines($det !== '' ? $det : '—', $valW - 2)) * 4.2 + 1.5);
            if ($y + $h > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                $y = $pdf->GetY();
            }
            $pdf->SetFillColor(...($i % 2 ? [255, 255, 255] : [247, 249, 252]));
            $pdf->SetXY($mL, $y);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(...self::GRIS_TXT);
            $pdf->MultiCell($lblW, $h, trim((string) ($f['concepto'] ?? '')), 'B', 'L', true, 0, '', '', true, 0, false, true, $h, 'M');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->MultiCell($valW, $h, $det !== '' ? $det : '—', 'B', 'L', true, 1, '', '', true, 0, false, true, $h, 'M');
            $y += $h;
        }
        $pdf->SetTextColor(0, 0, 0);
        return $y;
    }

    /** Forma de cobro (con la tarjeta registrada, si aplica) y observaciones. */
    private function dibujarCobro(array $c, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $forma = ($c['forma_cobro'] ?? 'credito') === 'tarjeta' ? 'Tarjeta (cobro automático)' : 'Crédito (pago manual)';
        $tarjeta = '';
        if (($c['forma_cobro'] ?? '') === 'tarjeta') {
            if (($c['pasarela_tarjeta'] ?? '') === 'nuvei' && !empty($c['nuvei_ultimos4'])) {
                $tarjeta = trim(($c['nuvei_marca'] ?? '') . ' **** ' . $c['nuvei_ultimos4']) . ' (Nuvei)';
            } elseif (!empty($c['kushki_card_last4'])) {
                $tarjeta = trim(($c['kushki_card_brand'] ?? '') . ' **** ' . $c['kushki_card_last4']) . ' (Kushki)';
            } else {
                $tarjeta = 'Sin tarjeta registrada';
            }
        }
        $obs = trim((string) ($c['observaciones'] ?? ''));

        $pares = [['Forma de cobro', $forma]];
        if ($tarjeta !== '') {
            $pares[] = ['Tarjeta', $tarjeta];
        }
        $pares[] = ['Observaciones', $obs];
        $creador = trim((string) ($c['usuario_creador'] ?? ''));
        $pares[] = ['Registrada por', ($creador !== '' ? $creador . ' — ' : '') . $this->fechaHora($c['created_at'] ?? null)];

        $pdf->SetFont('helvetica', '', 8.5);
        $alto = 9 + 2;
        foreach ($pares as [, $v]) {
            $alto += max(1, $pdf->getNumLines($v !== '' ? $v : '—', $this->contentW - 35)) * 4.3;
        }
        if ($y + $alto > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }
        return $y + $this->dibujarTarjeta('COBRO Y OBSERVACIONES', $pares, $mL, $y, $this->contentW);
    }

    private function dibujarPagos(array $pagos, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y   = $this->tituloSeccion('HISTORIAL DE COBROS', $y, 20);

        $cols = [
            ['t' => 'Fecha cobro', 'w' => 28, 'a' => 'C', 'k' => 'fecha'],
            ['t' => 'Documento',   'w' => 30, 'a' => 'L', 'k' => 'tipo'],
            ['t' => 'Número',      'w' => 0,  'a' => 'L', 'k' => 'numero'],
            ['t' => 'Estado doc.', 'w' => 30, 'a' => 'C', 'k' => 'estado_doc'],
            ['t' => 'Cobro',       'w' => 26, 'a' => 'C', 'k' => 'estado'],
            ['t' => 'Monto',       'w' => 28, 'a' => 'R', 'k' => 'monto'],
        ];
        $fijo = 0.0;
        foreach ($cols as $c) { $fijo += $c['w']; }
        $cols[2]['w'] = $this->contentW - $fijo;

        $cabecera = function (float $yEnc) use ($pdf, $cols, $mL): float {
            $pdf->SetXY($mL, $yEnc);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(...self::COLOR);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetDrawColor(...self::COLOR);
            foreach ($cols as $c) {
                $pdf->Cell($c['w'], 6.5, $c['t'], 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(30, 30, 30);
            return $yEnc + 6.5;
        };
        $cabecera($y);

        if (empty($pagos)) {
            $pdf->SetX($mL);
            $pdf->SetDrawColor(210, 218, 228);
            $pdf->SetTextColor(...self::GRIS_TXT);
            $pdf->Cell($this->contentW, 7, 'Aún no se han registrado cobros de esta suscripción.', 1, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            return;
        }

        $coloresCobro = ['exitoso' => [25, 135, 84], 'pendiente' => [230, 150, 0], 'fallido' => [220, 53, 69]];
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();
        $total   = 0.0;
        foreach (array_values($pagos) as $i => $p) {
            $estado = strtolower((string) ($p['estado'] ?? ''));
            $monto  = (float) ($p['monto'] ?? 0);
            if ($estado === 'exitoso') {
                $total += $monto;
            }
            $vals = [
                'fecha'      => $this->fecha($p['fecha_cobro'] ?? null),
                'tipo'       => ($p['tipo_documento'] ?? '') === 'recibo' ? 'Recibo de venta' : 'Factura',
                'numero'     => trim((string) ($p['factura_numero'] ?? '')) !== '' ? (string) $p['factura_numero'] : '—',
                'estado_doc' => trim((string) ($p['estado_factura'] ?? '')) !== '' ? ucfirst(strtolower((string) $p['estado_factura'])) : '—',
                'estado'     => ucfirst($estado),
                'monto'      => number_format($monto, 2, '.', ','),
            ];

            $yRow = $pdf->GetY();
            if ($yRow + 6 > $limiteY) {
                $pdf->AddPage();
                $yRow = $cabecera($pdf->GetY());
            }
            $pdf->SetFillColor(...($i % 2 ? [247, 249, 252] : [255, 255, 255]));
            $pdf->SetDrawColor(220, 226, 234);
            $x = $mL;
            foreach ($cols as $c) {
                $pdf->SetXY($x, $yRow);
                if ($c['k'] === 'estado') {
                    $pdf->SetTextColor(...($coloresCobro[$estado] ?? [30, 30, 30]));
                    $pdf->SetFont('helvetica', 'B', 8);
                }
                $pdf->Cell($c['w'], 6, $vals[$c['k']], 'B', 0, $c['a'], true, '', 1);
                $pdf->SetTextColor(30, 30, 30);
                $pdf->SetFont('helvetica', '', 8);
                $x += $c['w'];
            }
            $pdf->SetXY($mL, $yRow + 6);
        }

        $yT = $pdf->GetY();
        if ($yT + 7 > $limiteY) {
            $pdf->AddPage();
            $yT = $pdf->GetY();
        }
        $pdf->SetXY($mL, $yT);
        $pdf->SetFillColor(...self::COLOR_SUA);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($this->contentW - 28, 7, 'Total cobrado (' . count(array_filter($pagos, static fn ($p) => strtolower((string) ($p['estado'] ?? '')) === 'exitoso')) . ' cobros exitosos)  ', 0, 0, 'R', true);
        $pdf->Cell(28, 7, '$ ' . number_format($total, 2, '.', ',') . ' ', 0, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
    }

    /** Pie en cada página: empresa, fecha de generación y "Página X de Y". */
    private function dibujarPies(string $empresa, string $numero): void
    {
        $pdf   = $this->pdf;
        $total = $pdf->getNumPages();
        for ($i = 1; $i <= $total; $i++) {
            // setPage() restaura el salto automático de esa hoja: se apaga después, o el
            // pie (debajo del margen inferior) se iría a una hoja nueva.
            $pdf->setPage($i);
            $pdf->SetAutoPageBreak(false);
            $yPie = $pdf->getPageHeight() - 12;
            $pdf->SetDrawColor(210, 218, 228);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($this->marginL, $yPie, $this->marginL + $this->contentW, $yPie);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(...self::GRIS_TXT);
            $pdf->SetXY($this->marginL, $yPie + 1);
            $pdf->Cell($this->contentW / 2, 4, $empresa . ' · Suscripción N.° ' . $numero, 0, 0, 'L');
            $pdf->Cell($this->contentW / 2, 4, 'Generado el ' . date('d-m-Y H:i:s') . '   ·   Página ' . $i . ' de ' . $total, 0, 0, 'R');
        }
        $pdf->SetTextColor(0, 0, 0);
    }

    /** Título de sección con una barra de acento; salta de página si no cabe $minAlto. */
    private function tituloSeccion(string $titulo, float $y, float $minAlto): float
    {
        $pdf = $this->pdf;
        if ($y + 7 + $minAlto > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }
        $pdf->SetFillColor(...self::COLOR);
        $pdf->Rect($this->marginL, $y + 0.8, 1.2, 4.4, 'F');
        $pdf->SetXY($this->marginL + 3, $y);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($this->contentW - 3, 6, $titulo, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        return $y + 7;
    }

    private function fecha($v, string $vacio = '—'): string
    {
        if (empty($v)) {
            return $vacio;
        }
        $ts = strtotime((string) $v);
        return $ts ? date('d-m-Y', $ts) : (string) $v;
    }

    private function fechaHora($v): string
    {
        if (empty($v)) {
            return '—';
        }
        $ts = strtotime((string) $v);
        return $ts ? date('d-m-Y H:i:s', $ts) : (string) $v;
    }

    /** Hasta 6 decimales sin ceros sobrantes (mínimo $minDec). */
    private function numLibre($v, int $minDec = 0): string
    {
        $s   = rtrim(rtrim(number_format((float) $v, 6, '.', ','), '0'), '.');
        $dec = strpos($s, '.') === false ? 0 : strlen($s) - strpos($s, '.') - 1;
        return $dec < $minDec ? number_format((float) $v, $minDec, '.', ',') : $s;
    }

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
