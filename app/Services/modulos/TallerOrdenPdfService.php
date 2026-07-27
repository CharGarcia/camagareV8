<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF de la Orden de Trabajo del taller (A4 vertical).
 *
 * Es el documento que se imprime al RECIBIR el vehículo y que firma el cliente:
 * datos del vehículo y del contacto, motivo de ingreso, checklist de recepción
 * (accesorios, documentos, carrocería y niveles), presupuesto y las firmas de
 * autorización.
 *
 * Comparte el encabezado con el comprobante de Ingresos y con la orden de
 * Car-Wash para que todos los documentos del sistema se vean iguales.
 */
class TallerOrdenPdfService
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
        $this->pdf->SetTitle('Orden de Trabajo ' . ($orden['numero_orden'] ?? ''));
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $orden);
        $y = $this->dibujarDatosVehiculo($orden, $y + 3);
        $y = $this->dibujarMotivo($orden, $y + 3);
        $y = $this->dibujarChecklist($orden['checklist'] ?? [], $y + 3);
        $y = $this->dibujarDetalle($orden['detalles'] ?? [], $y + 3);
        $y = $this->dibujarTotales($orden, $y + 3);
        $this->dibujarAutorizacion($orden, $y + 4);

        $nombre = 'Orden_Taller_' . (($orden['numero_orden'] ?? '') !== '' ? $orden['numero_orden'] : 'OT') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
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

        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($derW, 5, 'ORDEN DE TRABAJO', 0, 1, 'C');

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $numero = trim((string) ($orden['numero_orden'] ?? ''));
        $pdf->Cell($derW, 6, $numero !== '' ? $numero : '—', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell($derW, 4, 'Estado: ' . TallerPdfHelper::etiquetaEstado((string) ($orden['estado'] ?? '')), 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    // ─── Vehículo, cliente y contacto ─────────────────────────────────────────
    private function dibujarDatosVehiculo(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $boxH = 38;
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'DF');

        $lbl = function (string $t, float $wl = 24) use ($pdf) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($wl, 5, $t, 0, 0, 'L');
        };
        $val = function (string $t, float $wv, int $ln = 0) use ($pdf) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell($wv, 5, $t, 0, $ln, 'L');
        };

        $vehiculo = trim((string) ($orden['marca'] ?? '') . ' ' . (string) ($orden['modelo'] ?? '') . ' ' . (string) ($orden['anio'] ?? ''));
        $km = ($orden['kilometraje'] ?? null) !== null && ($orden['kilometraje'] ?? '') !== ''
            ? number_format((float) $orden['kilometraje'], 0, ',', '.') . ' km' : '—';

        $pdf->SetXY($mL + 2, $y + 2);
        $lbl('Placa:');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(38, 5, strtoupper((string) ($orden['placa'] ?? '—')), 0, 0, 'L');
        $lbl('Vehículo:', 20);
        $val(TallerPdfHelper::ajustar($pdf, $vehiculo !== '' ? $vehiculo : '—', 62), 62);
        $lbl('Ingreso:', 18);
        $val(TallerPdfHelper::fecha($orden['fecha_ingreso'] ?? null, 'd/m/Y H:i'), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Color:');
        $val((string) ($orden['color'] ?? '—'), 38);
        $lbl('Chasis:', 20);
        $val(TallerPdfHelper::ajustar($pdf, (string) ($orden['chasis'] ?? '—'), 62), 62);
        $lbl('Estimada:', 18);
        $val(TallerPdfHelper::fecha($orden['fecha_estimada_entrega'] ?? null, 'd/m/Y H:i'), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Kilometraje:');
        $val($km, 38);
        $lbl('Motor:', 20);
        $val(TallerPdfHelper::ajustar($pdf, (string) ($orden['motor'] ?? '—'), 62), 62);
        $lbl('Comb.:', 18);
        $val((string) ($orden['nivel_combustible'] ?? '—'), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Cliente:');
        $val(TallerPdfHelper::ajustar($pdf, (string) ($orden['cliente_nombre'] ?? '— (sin cliente) —'), 96), 96);
        $lbl('Ident.:', 18);
        $val((string) ($orden['cliente_identificacion'] ?? '—'), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Contacto:');
        $val(TallerPdfHelper::ajustar($pdf, (string) ($orden['nombre_usuario'] ?? '—'), 60), 60);
        $lbl('Teléfono:', 20);
        $val((string) ($orden['telefono_contacto'] ?? '—'), 36);
        $lbl('Asesor:', 18);
        $val(TallerPdfHelper::ajustar($pdf, (string) ($orden['asesor_nombre'] ?? ''), 30), 0, 1);

        // Franja del siniestro, solo cuando aplica.
        if (\App\Helpers\Booleano::es($orden['es_siniestro'] ?? false)) {
            $pdf->SetXY($mL + 2, $pdf->GetY());
            $lbl('Siniestro:');
            $val(TallerPdfHelper::ajustar($pdf,
                trim((string) ($orden['aseguradora'] ?? '') . ' — N.° ' . (string) ($orden['numero_siniestro'] ?? '')), 96), 96);
            $lbl('Deducible:', 20);
            $val('$ ' . number_format((float) ($orden['deducible'] ?? 0), 2), 0, 1);
            $boxH += 5;
            $pdf->SetLineWidth(0.2);
            $pdf->SetDrawColor(120, 120, 120);
            $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'D');
        }

        return $y + $boxH;
    }

    // ─── Motivo de ingreso ────────────────────────────────────────────────────
    private function dibujarMotivo(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $motivo = trim((string) ($orden['motivo_ingreso'] ?? ''));
        if ($motivo === '') return $y;

        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'MOTIVO DE INGRESO / REQUERIMIENTO DEL CLIENTE');

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->MultiCell($this->contentW, 4.5, $motivo, 1, 'L', false, 1);

        return $pdf->GetY();
    }

    // ─── Checklist de recepción ───────────────────────────────────────────────
    private function dibujarChecklist(array $checklist, float $y): float
    {
        if (empty($checklist)) return $y;

        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'RECEPCIÓN DEL VEHÍCULO — INVENTARIO Y ESTADO');

        // Dos columnas para aprovechar la hoja.
        $colW  = $this->contentW / 2;
        $itemW = $colW - 22;
        $pdf->SetFont('helvetica', '', 7.5);

        $porGrupo = [];
        foreach ($checklist as $c) {
            $porGrupo[(string) ($c['grupo'] ?? 'accesorios')][] = $c;
        }

        $filas = [];
        foreach ($porGrupo as $grupo => $items) {
            $filas[] = ['grupo' => TallerPdfHelper::etiquetaGrupo($grupo)];
            foreach ($items as $i) {
                $filas[] = $i;
            }
        }

        $mitad = (int) ceil(count($filas) / 2);
        $izq   = array_slice($filas, 0, $mitad);
        $der   = array_slice($filas, $mitad);
        $yIni  = $y;
        $maxY  = $y;

        foreach ([[$izq, $mL], [$der, $mL + $colW]] as [$col, $x]) {
            $yCol = $yIni;
            foreach ($col as $f) {
                if (isset($f['grupo'])) {
                    $pdf->SetXY($x, $yCol);
                    $pdf->SetFont('helvetica', 'B', 7.5);
                    $pdf->SetFillColor(235, 238, 242);
                    $pdf->Cell($colW, 4.6, ' ' . $f['grupo'], 1, 0, 'L', true);
                    $yCol += 4.6;
                    continue;
                }
                $pdf->SetXY($x, $yCol);
                $pdf->SetFont('helvetica', '', 7.5);
                $texto = (string) ($f['item'] ?? '');
                if (!empty($f['observacion'])) {
                    $texto .= ' (' . $f['observacion'] . ')';
                }
                $pdf->Cell($itemW, 4.6, ' ' . TallerPdfHelper::ajustar($pdf, $texto, $itemW - 3), 1, 0, 'L');
                $pdf->SetFont('helvetica', 'B', 7.5);
                $pdf->Cell(22, 4.6, TallerPdfHelper::etiquetaValorChecklist((string) ($f['valor'] ?? 'no')), 1, 0, 'C');
                $yCol += 4.6;
            }
            $maxY = max($maxY, $yCol);
        }

        return $maxY;
    }

    // ─── Presupuesto (repuestos y mano de obra) ───────────────────────────────
    private function dibujarDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $y = TallerPdfHelper::tituloSeccion($pdf, $mL, $y, $this->contentW, 'REPUESTOS Y MANO DE OBRA');

        $cols = [
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L'],
            ['t' => 'Tipo',        'w' => 22, 'a' => 'C'],
            ['t' => 'Estado',      'w' => 20, 'a' => 'C'],
            ['t' => 'Cant.',       'w' => 15, 'a' => 'R'],
            ['t' => 'P. Unit',     'w' => 20, 'a' => 'R'],
            ['t' => 'Total',       'w' => 22, 'a' => 'R'],
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

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Aún sin repuestos ni trabajos registrados.', 1, 1, 'C');
            return $pdf->GetY();
        }

        $alt = false;
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $desc = (string) ($d['descripcion'] ?? '');
            if (\App\Helpers\Booleano::es($d['provisto_cliente'] ?? false)) {
                $desc .= '  [lo trae el cliente]';
            } elseif (\App\Helpers\Booleano::no($d['facturable'] ?? false)) {
                $desc .= '  [no facturable]';
            }

            $vals = [
                $desc,
                TallerPdfHelper::etiquetaTipoLinea((string) ($d['tipo_linea'] ?? '')),
                TallerPdfHelper::etiquetaEstadoLinea((string) ($d['estado_linea'] ?? '')),
                number_format((float) ($d['cantidad'] ?? 0), 2),
                number_format((float) ($d['precio_unitario'] ?? 0), 2),
                number_format((float) ($d['total_linea'] ?? 0), 2),
            ];

            $descW = $cols[0]['w'];
            $nLin  = max(1, (int) ceil(max(1, $pdf->GetStringWidth($vals[0])) / max(1, $descW - 2)));
            $h     = max(5.0, $nLin * 4.2);

            if ($pdf->GetY() + $h > 265) {
                $pdf->AddPage();
                $pdf->SetY(15);
            }

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
        $fila('Repuestos', (float) ($orden['subtotal_repuestos'] ?? 0));
        $fila('Mano de obra', (float) ($orden['subtotal_mano_obra'] ?? 0));
        if ((float) ($orden['descuento'] ?? 0) > 0) $fila('Descuento', (float) $orden['descuento']);
        $fila('IVA', (float) ($orden['iva'] ?? 0));
        $fila('TOTAL', (float) ($orden['total'] ?? 0), true);
        $yTotales = $pdf->GetY();

        $letras = TallerPdfHelper::montoEnLetras((float) ($orden['total'] ?? 0));
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->MultiCell($this->contentW - $tW - 4, 4, 'SON: ' . $letras . ' DÓLARES', 0, 'L', false, 1);
        $yLetras = $pdf->GetY();

        return max($yTotales, $yLetras);
    }

    // ─── Autorización y firmas ────────────────────────────────────────────────
    private function dibujarAutorizacion(array $orden, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        if ($y > 220) {
            $pdf->AddPage();
            $y = 20;
        }

        $texto = 'Autorizo la ejecución de los trabajos descritos en esta orden. Declaro que el inventario '
               . 'de accesorios y el estado del vehículo detallados arriba son correctos. Los trabajos '
               . 'adicionales que se detecten durante la reparación serán comunicados y requerirán mi '
               . 'aprobación previa.';
        if ((int) ($orden['garantia_dias'] ?? 0) > 0 || (int) ($orden['garantia_km'] ?? 0) > 0) {
            $texto .= ' Garantía de la reparación: '
                . ((int) ($orden['garantia_dias'] ?? 0) > 0 ? $orden['garantia_dias'] . ' días' : '')
                . ((int) ($orden['garantia_dias'] ?? 0) > 0 && (int) ($orden['garantia_km'] ?? 0) > 0 ? ' o ' : '')
                . ((int) ($orden['garantia_km'] ?? 0) > 0 ? number_format((float) $orden['garantia_km'], 0, ',', '.') . ' km' : '')
                . ', lo que ocurra primero.';
        }

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell($this->contentW, 3.5, $texto, 0, 'J', false, 1);

        $yLinea = $pdf->GetY() + 16;
        if ($yLinea > 272) $yLinea = 272;

        $colW = $this->contentW / 2;
        $firmas = [
            ['Asesor de servicio', trim((string) ($orden['asesor_nombre'] ?? ''))],
            ['Cliente / Autorizo los trabajos', trim((string) ($orden['cliente_nombre'] ?? ''))],
        ];

        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 10, $yLinea, $x + $colW - 10, $yLinea);
        }
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) { $pdf->Cell($colW, 4, $f[0], 0, 0, 'C'); }

        $pdf->SetFont('helvetica', '', 7.5);
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->SetXY($x + 3, $yLinea + 5);
            $pdf->MultiCell($colW - 6, 3.4, $f[1] !== '' ? $f[1] : ' ', 0, 'C', false, 0, '', '', true, 0, false, true, 0, 'T');
        }
    }
}
