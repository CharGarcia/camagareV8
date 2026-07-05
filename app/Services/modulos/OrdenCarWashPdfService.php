<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF de la Orden de Servicio Car-Wash (A4 vertical).
 *
 * Toma como referencia el encabezado del comprobante de Ingresos
 * (ComprobanteCajaPdfService): logo + datos de empresa a la izquierda y una caja
 * con el título, N.° y estado a la derecha. Luego: datos del vehículo/cliente,
 * tabla de servicios/productos, totales + monto en letras, información adicional
 * y firmas.
 */
class OrdenCarWashPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    /** @param string $outputDest 'I' inline, 'D' descarga, 'S' string */
    public function generar(array $orden, array $empresa, string $outputDest = 'I')
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Orden Car-Wash ' . ($orden['numero_orden'] ?? ''));
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $orden);
        $y = $this->dibujarDatosOrden($orden, $y + 3);
        $y = $this->dibujarTablaDetalle($orden['detalles'] ?? [], $y + 3);
        $y = $this->dibujarTotales($orden, $y + 3);
        $y = $this->dibujarInfoAdicional($orden['info_adicional'] ?? [], $y + 4);
        $this->dibujarFirmas($orden, $y + 4);

        $nombre = 'Orden_CarWash_' . (($orden['numero_orden'] ?? '') !== '' ? $orden['numero_orden'] : 'orden') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    // ─── Encabezado (referencia: comprobante de ingresos) ─────────────────────
    private function dibujarEncabezado(array $empresa, array $orden): float
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

        // Caja del comprobante (derecha)
        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($derW, 5, 'ORDEN CAR-WASH', 0, 1, 'C');

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $numero = trim((string)($orden['numero_orden'] ?? ''));
        $pdf->Cell($derW, 6, $numero !== '' ? $numero : '—', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell($derW, 4, 'Estado: ' . ucfirst((string)($orden['estado'] ?? 'borrador')), 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    // ─── Datos del vehículo / cliente ─────────────────────────────────────────
    private function dibujarDatosOrden(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $fecha = '';
        if (!empty($orden['fecha_ingreso'])) {
            $ts = strtotime((string)$orden['fecha_ingreso']);
            $fecha = $ts ? date('d/m/Y H:i', $ts) : (string)$orden['fecha_ingreso'];
        }
        $proxCita = '';
        if (!empty($orden['proxima_cita'])) {
            $ts = strtotime((string)$orden['proxima_cita']);
            $proxCita = $ts ? date('d/m/Y', $ts) : (string)$orden['proxima_cita'];
        }
        $vehiculo = trim((string)($orden['placa'] ?? '') . '  ' . (string)($orden['marca'] ?? '') . ' ' . (string)($orden['modelo'] ?? ''));
        $cliente  = trim((string)($orden['cliente_nombre'] ?? ''));
        $comb     = (string)($orden['nivel_combustible'] ?? '');
        $km       = ($orden['kilometraje'] ?? '') !== '' && $orden['kilometraje'] !== null ? (string)$orden['kilometraje'] . ' km' : '';

        $boxH = 26;
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'DF');

        $lbl = function (string $t) use ($pdf) { $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(24, 5, $t, 0, 0, 'L'); };
        $val = function (string $t, float $wv, int $ln = 0) use ($pdf) { $pdf->SetFont('helvetica', '', 9); $pdf->Cell($wv, 5, $t, 0, $ln, 'L'); };

        $pdf->SetXY($mL + 2, $y + 2);
        $lbl('Vehículo:'); $val($this->ajustarTexto($vehiculo !== '' ? $vehiculo : '—', 88), 88);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(18, 5, 'Fecha:', 0, 0, 'R');
        $val($fecha, 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Cliente:'); $val($this->ajustarTexto($cliente !== '' ? $cliente : '— (sin cliente) —', 88), 88);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(18, 5, 'Ident.:', 0, 0, 'R');
        $val((string)($orden['cliente_identificacion'] ?? ''), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Kilometraje:'); $val($km !== '' ? $km : '—', 60);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(22, 5, 'Combustible:', 0, 0, 'L');
        $val($comb !== '' ? $comb : '—', 28);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(20, 5, 'Próx. cita:', 0, 0, 'R');
        $val($proxCita !== '' ? $proxCita : '—', 0, 1);

        return $y + $boxH;
    }

    // ─── Tabla de servicios / productos ───────────────────────────────────────
    private function dibujarTablaDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $cols = [
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L'],
            ['t' => 'Cant.',       'w' => 18, 'a' => 'R'],
            ['t' => 'P. Unit',     'w' => 24, 'a' => 'R'],
            ['t' => 'Desc.',       'w' => 20, 'a' => 'R'],
            ['t' => 'IVA %',       'w' => 16, 'a' => 'R'],
            ['t' => 'Total',       'w' => 26, 'a' => 'R'],
        ];
        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(40.0, $this->contentW - $fixed);
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = $flex; } }
        unset($c);

        // Encabezado
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->SetLineWidth(0.2);
        foreach ($cols as $c) { $pdf->Cell($c['w'], 6, $c['t'], 1, 0, 'C', true); }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Sin servicios ni productos.', 1, 1, 'C');
        }
        $alt = false;
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $vals = [
                (string)($d['descripcion'] ?? ''),
                number_format((float)($d['cantidad'] ?? 0), 2),
                number_format((float)($d['precio_unitario'] ?? 0), 2),
                number_format((float)($d['descuento'] ?? 0), 2),
                number_format((float)($d['porcentaje_iva'] ?? 0), 0) . '%',
                number_format((float)($d['total_linea'] ?? 0), 2),
            ];

            $descW = $cols[0]['w'];
            $nLin  = max(1, (int)ceil(max(1, $pdf->GetStringWidth($vals[0])) / max(1, $descW - 2)));
            $h     = max(5.0, $nLin * 4.2);

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

    // ─── Totales + monto en letras ────────────────────────────────────────────
    private function dibujarTotales(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $subtotal  = (float)($orden['subtotal'] ?? 0);
        $descuento = (float)($orden['descuento'] ?? 0);
        $iva       = (float)($orden['iva'] ?? 0);
        $total     = (float)($orden['total'] ?? 0);

        $tW = 66;
        $tX = $mL + $this->contentW - $tW;

        $fila = function (string $lbl, float $val, bool $fuerte = false) use ($pdf, $tX, $tW) {
            $pdf->SetX($tX);
            $pdf->SetFont('helvetica', $fuerte ? 'B' : '', $fuerte ? 10 : 8);
            if ($fuerte) {
                $pdf->SetFillColor(60, 70, 90);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetDrawColor(60, 70, 90);
                $pdf->Cell(34, 7, $lbl, 1, 0, 'C', true);
                $pdf->Cell($tW - 34, 7, '$ ' . number_format($val, 2), 1, 1, 'R', true);
                $pdf->SetTextColor(0, 0, 0);
            } else {
                $pdf->Cell(34, 5, $lbl, 0, 0, 'R');
                $pdf->Cell($tW - 34, 5, number_format($val, 2), 0, 1, 'R');
            }
        };

        $pdf->SetXY($tX, $y);
        $fila('Subtotal', $subtotal);
        if ($descuento > 0) $fila('Descuento', $descuento);
        $fila('IVA', $iva);
        $fila('TOTAL', $total, true);
        $yTotales = $pdf->GetY(); // fondo de la caja de totales (derecha)

        // Monto en letras (izquierda)
        $letras = $this->montoEnLetras($total);
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->MultiCell($this->contentW - $tW - 4, 4, 'SON: ' . $letras . ' DÓLARES', 0, 'L', false, 1);
        $yLetras = $pdf->GetY();

        // La siguiente sección debe empezar debajo de lo más bajo de ambos lados.
        return max($yTotales, $yLetras);
    }

    // ─── Información adicional ─────────────────────────────────────────────────
    private function dibujarInfoAdicional(array $info, float $y): float
    {
        $info = array_values(array_filter($info, fn($i) => trim((string)($i['nombre'] ?? '')) !== '' && trim((string)($i['valor'] ?? '')) !== ''));
        if (empty($info)) {
            return $y;
        }
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(90, 90, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(90, 90, 90);
        $pdf->Cell($this->contentW, 5.5, 'INFORMACIÓN ADICIONAL', 1, 1, 'C', true);

        $wCon = 55; $wDet = $this->contentW - $wCon;
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($info as $i) {
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($wCon, 5, $this->ajustarTexto((string)$i['nombre'], $wCon - 2), 1, 0, 'L');
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell($wDet, 5, $this->ajustarTexto((string)$i['valor'], $wDet - 2), 1, 1, 'L');
        }

        return $pdf->GetY();
    }

    // ─── Firmas ───────────────────────────────────────────────────────────────
    private function dibujarFirmas(array $orden, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $colW = $this->contentW / 2;

        $yLinea = $y + 20;
        if ($yLinea > 272) { $yLinea = 272; }

        $firmas = [
            ['Entregado por', ''],
            ['Cliente / Recibí conforme', trim((string)($orden['cliente_nombre'] ?? ''))],
        ];

        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 10, $yLinea, $x + $colW - 10, $yLinea);
        }
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) { $pdf->Cell($colW, 4, $f[0], 0, 0, 'C'); }

        $pdf->SetFont('helvetica', '', 7.5);
        $yName = $yLinea + 5;
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->SetXY($x + 3, $yName);
            $pdf->MultiCell($colW - 6, 3.4, $f[1] !== '' ? $f[1] : ' ', 0, 'C', false, 0, '', '', true, 0, false, true, 0, 'T');
        }
    }

    // ─── Helpers (reutilizados del comprobante de caja) ───────────────────────
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

    private function montoEnLetras(float $monto): string
    {
        require_once \MVC_ROOT . '/app/validadores/numero_letras.php';
        $str = number_format($monto, 2, '.', '');
        if (function_exists('num_letras')) {
            $txt = trim((string) num_letras($str));
            return preg_replace('/\s+/', ' ', $txt);
        }
        return $str;
    }
}
