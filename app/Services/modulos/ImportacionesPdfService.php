<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Genera el PDF de una Importación individual: documento interno (no es un
 * comprobante electrónico SRI), así que no lleva clave de acceso/autorización/
 * barcode. A4 vertical, mismo estilo (cajas redondeadas, tabla con encabezado
 * oscuro) que ComprobanteCajaPdfService.
 *
 * @param array $cabecera Fila de ImportacionesService::getPorId() (con 'detalles', 'gastos', 'facturas_exterior' anidados)
 * @param array $empresa  Fila de empresas (enriquecida con logo/dirección del establecimiento)
 */
class ImportacionesPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    private array $estadoLabels = [
        'borrador'             => 'Borrador',
        'en_transito'          => 'En tránsito',
        'registrada'           => 'Registrada',
        'pendiente_aprobacion' => 'Pendiente de aprobación',
        'nacionalizada'        => 'Nacionalizada',
        'cerrada'              => 'Cerrada',
        'anulada'              => 'Anulada',
    ];

    /** Genera el PDF y lo envía al navegador para descarga directa. */
    public function generar(array $cabecera, array $empresa): void
    {
        $this->render($cabecera, $empresa);
        $num = $this->numeroDocumento($cabecera);
        $this->pdf->Output('Importacion_' . $num . '.pdf', 'D');
    }

    /** Genera el PDF y lo devuelve como string (para adjuntar en correo). */
    public function generarBytes(array $cabecera, array $empresa): string
    {
        $this->render($cabecera, $empresa);
        return $this->pdf->Output('importacion.pdf', 'S');
    }

    private function numeroDocumento(array $cab): string
    {
        return str_pad((string) ($cab['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
             . str_pad((string) ($cab['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
             . str_pad((string) ($cab['secuencial']      ?? ''),   9, '0', STR_PAD_LEFT);
    }

    private function render(array $cabecera, array $empresa): void
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Importación ' . $this->numeroDocumento($cabecera));
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $cabecera);
        $y = $this->dibujarDatosGenerales($cabecera, $y + 3);
        $y = $this->dibujarTablaProductos($cabecera['detalles'] ?? [], $y + 3);
        $y = $this->dibujarTablaGastos($cabecera['gastos'] ?? [], $y + 3);
        $this->dibujarTotales($cabecera, $y + 3);
    }

    // ── Encabezado: empresa (con logo opcional) + caja de N.° / estado ──────

    private function dibujarEncabezado(array $empresa, array $cab): float
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
        $pdf->MultiCell($mL + $izqW - $textoX, 5, strtoupper((string) ($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetX($textoX);
        $pdf->SetFont('helvetica', '', 8);
        $lineas = array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
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
        $pdf->Cell($derW, 5, 'IMPORTACIÓN', 0, 1, 'C');

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, $this->numeroDocumento($cab), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $estado = $this->estadoLabels[$cab['estado'] ?? ''] ?? ucfirst((string) ($cab['estado'] ?? ''));
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, $estado, 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    // ── Caja de datos generales: proveedor exterior, agente, bodega, fechas ──

    private function dibujarDatosGenerales(array $cab, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $fEmb = $this->fmtFecha($cab['fecha_embarque'] ?? null);
        $fLle = $this->fmtFecha($cab['fecha_llegada'] ?? null);
        $fNac = $this->fmtFecha($cab['fecha_nacionalizacion'] ?? null);

        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $boxH = 28;
        $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'DF');

        $labelW = 34;
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL + 2, $y + 2);
        $pdf->Cell($labelW, 5, 'Proveedor exterior:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(90, 5, $this->ajustarTexto((string) ($cab['proveedor_nombre'] ?? '—'), 90), 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(18, 5, 'Ref. DAI:', 0, 0, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, (string) ($cab['referencia_dai'] ?? '—'), 0, 1, 'L');

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($labelW, 5, 'Agente afianzado:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(90, 5, $this->ajustarTexto((string) ($cab['agente_nombre'] ?? '—'), 90), 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(18, 5, 'Incoterm:', 0, 0, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, (string) ($cab['incoterm'] ?? '—'), 0, 1, 'L');

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($labelW, 5, 'Bodega destino:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(90, 5, $this->ajustarTexto((string) ($cab['bodega_nombre'] ?? '—'), 90), 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(18, 5, '', 0, 0, 'R');
        $pdf->Cell(0, 5, '', 0, 1, 'L');

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($labelW, 5, 'Fecha embarque:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(30, 5, $fEmb, 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(28, 5, 'Fecha llegada:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(30, 5, $fLle, 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(30, 5, 'Nacionalización:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, $fNac, 0, 1, 'L');

        return $y + $boxH;
    }

    // ── Tabla de productos (FOB) ─────────────────────────────────────────

    private function dibujarTablaProductos(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $cols = [
            ['t' => 'Descripción',    'w' => 0,  'a' => 'L'],
            ['t' => 'Cant.',          'w' => 16, 'a' => 'C'],
            ['t' => 'P. Unit. FOB',   'w' => 22, 'a' => 'R'],
            ['t' => 'Total FOB',      'w' => 22, 'a' => 'R'],
            ['t' => 'Act. Fijo',      'w' => 16, 'a' => 'C'],
            ['t' => 'Costo Unit.',    'w' => 22, 'a' => 'R'],
            ['t' => 'Costo Total',    'w' => 22, 'a' => 'R'],
        ];
        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(30.0, $this->contentW - $fixed);
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
        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Sin líneas de producto.', 1, 1, 'C');
        }
        $alt = false;
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $esAf = !empty($d['es_activo_fijo']) && $d['es_activo_fijo'] !== 'f';
            $desc = (string) ($d['producto_nombre'] ?? $d['descripcion'] ?? '');
            $vals = [
                $desc,
                number_format((float) ($d['cantidad'] ?? 0), 2),
                number_format((float) ($d['precio_unitario_fob'] ?? 0), 4),
                number_format((float) ($d['precio_total_fob'] ?? 0), 2),
                $esAf ? 'Sí' : 'No',
                number_format((float) ($d['costo_unitario_nacionalizado'] ?? 0), 4),
                number_format((float) ($d['costo_total_nacionalizado'] ?? 0), 2),
            ];

            $descW = $cols[0]['w'];
            $nLin  = max(1, (int) ceil(max(1, $pdf->GetStringWidth($vals[0])) / max(1, $descW - 2)));
            $h     = max(5.0, $nLin * 4.2);

            $x = $mL;
            $yRow = $pdf->GetY();
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

    // ── Tabla de gastos de nacionalización ───────────────────────────────

    private function dibujarTablaGastos(array $gastos, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(90, 90, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(90, 90, 90);
        $pdf->Cell($this->contentW, 5.5, 'GASTOS DE NACIONALIZACIÓN', 1, 1, 'C', true);

        $cols = [
            ['t' => 'Tipo',        'w' => 42, 'a' => 'L'],
            ['t' => 'Origen',      'w' => 32, 'a' => 'L'],
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L'],
            ['t' => 'Monto',       'w' => 26, 'a' => 'R'],
        ];
        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(30.0, $this->contentW - $fixed);
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = $flex; } }
        unset($c);

        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 7);
        foreach ($cols as $c) {
            $pdf->Cell($c['w'], 5, $c['t'], 1, 0, $c['a'] === 'R' ? 'R' : 'L', true);
        }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        if (empty($gastos)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 5, 'Sin gastos registrados.', 1, 1, 'C');
        }
        $tiposLabel = [
            'arancel_ad_valorem'  => 'Arancel Ad-Valorem', 'fodinfa' => 'FODINFA',
            'iva_importacion'     => 'IVA de importación', 'isd' => 'ISD',
            'flete_internacional' => 'Flete internacional', 'seguro' => 'Seguro',
            'agente_afianzado'    => 'Agente afianzado', 'almacenaje' => 'Almacenaje',
            'transporte_interno'  => 'Transporte interno', 'otro' => 'Otro',
        ];
        $origenLabel = ['dai_manual' => 'Manual DAI', 'compra_vinculada' => 'Compra vinculada', 'liquidacion_vinculada' => 'Liquidación vinculada'];
        foreach ($gastos as $g) {
            $pdf->SetX($mL);
            $pdf->Cell($cols[0]['w'], 5, $tiposLabel[$g['tipo_gasto'] ?? ''] ?? (string) ($g['tipo_gasto'] ?? ''), 1, 0, 'L');
            $pdf->Cell($cols[1]['w'], 5, $origenLabel[$g['origen'] ?? ''] ?? (string) ($g['origen'] ?? ''), 1, 0, 'L');
            $pdf->Cell($cols[2]['w'], 5, $this->ajustarTexto((string) ($g['descripcion'] ?? ''), $cols[2]['w'] - 2), 1, 0, 'L');
            $pdf->Cell($cols[3]['w'], 5, number_format((float) ($g['monto'] ?? 0), 2), 1, 1, 'R');
        }

        return $pdf->GetY();
    }

    // ── Totales ───────────────────────────────────────────────────────────

    private function dibujarTotales(array $cab, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $filas = [
            ['Subtotal FOB', (float) ($cab['subtotal_fob'] ?? 0), false],
            ['Gastos capitalizables', (float) ($cab['total_gastos_capitalizables'] ?? 0), false],
            ['IVA', (float) ($cab['total_iva'] ?? 0), false],
            ['ISD', (float) ($cab['total_isd'] ?? 0), false],
            ['Otros gastos', (float) ($cab['total_otros_gastos'] ?? 0), false],
            ['COSTO TOTAL NACIONALIZADO', (float) ($cab['costo_total_nacionalizado'] ?? 0), true],
        ];

        $tW = 96;
        $tX = $mL + $this->contentW - $tW;
        $labelW = 65;

        foreach ($filas as $f) {
            [$label, $valor, $destacado] = $f;
            $pdf->SetXY($tX, $y);
            if ($destacado) {
                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->SetFillColor(60, 70, 90);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetDrawColor(60, 70, 90);
                $pdf->Cell($labelW, 7, $label, 1, 0, 'L', true);
                $pdf->Cell($tW - $labelW, 7, '$ ' . number_format($valor, 2), 1, 1, 'R', true);
                $pdf->SetTextColor(0, 0, 0);
            } else {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->SetDrawColor(180, 180, 180);
                $pdf->Cell($labelW, 5.5, $label, 1, 0, 'L', true);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->Cell($tW - $labelW, 5.5, number_format($valor, 2), 1, 1, 'R', true);
            }
            $y = $pdf->GetY();
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    private function fmtFecha(?string $fecha): string
    {
        if (empty($fecha)) return '—';
        $ts = strtotime($fecha);
        return $ts ? date('d/m/Y', $ts) : $fecha;
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
