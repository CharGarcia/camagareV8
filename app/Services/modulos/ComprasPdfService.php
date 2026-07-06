<?php
declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Genera un PDF de una Compra con el mismo formato/estilo del RIDE de Facturas
 * de Venta (FacturaVentaPdfService), pero con la información de la compra:
 *  - El EMISOR del documento es el PROVEEDOR (caja izquierda).
 *  - El ADQUIRENTE es la empresa activa (caja de datos).
 *  - En el área del logo NO se dibuja el logo: se muestra "NO TIENE LOGO" en rojo.
 */
class ComprasPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 10;
    private float $marginR  = 10;
    private float $pageW    = 210;
    private float $contentW = 190;

    private int $decCantidad = 2;
    private int $decPrecio   = 2;

    /** Mapa de tipos de comprobante (código SRI => nombre para el título). */
    private array $tiposComprobante = [
        '01' => 'FACTURA',
        '02' => 'NOTA DE VENTA',
        '03' => 'LIQUIDACIÓN DE COMPRA',
        '04' => 'NOTA DE CRÉDITO',
        '05' => 'NOTA DE DÉBITO',
        '06' => 'GUÍA DE REMISIÓN',
        '07' => 'COMPROBANTE DE RETENCIÓN',
        '09' => 'TIQUE MÁQ. REGISTRADORA',
        '11' => 'PASAJE',
        '12' => 'INST. FINANCIERA',
        '15' => 'COMP. REEMBOLSO',
        '16' => 'COMP. SOCIO PASAJERO',
        '18' => 'DOCUMENTO IMPORTACIÓN',
        '19' => 'COMP. COMBUSTIBLE',
        '20' => 'LIQUIDACIÓN GAS',
        '21' => 'NOTA DE CRÉDITO RISE',
        '41' => 'COMP. REEMB. EXTERIOR',
        '42' => 'COMP. SERVICIO',
        '43' => 'LIQUIDACIÓN IMP.',
        '47' => 'NOTA DE CRÉDITO PRESTAMISTA',
        '48' => 'NOTA DE DÉBITO PRESTAMISTA',
    ];

    public function generar(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa, string $outputDest = 'D')
    {
        $this->renderizar($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        $num = $this->numeroDocumento($cabecera);
        if ($outputDest === 'S') {
            return $this->pdf->Output('Compra_' . $num . '.pdf', 'S');
        }
        $this->pdf->Output('Compra_' . $num . '.pdf', $outputDest);
    }

    /** Genera el PDF y lo devuelve como string (para guardado en disco / correo). */
    public function generarBytes(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): string
    {
        $this->renderizar($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        return $this->pdf->Output('', 'S');
    }

    private function renderizar(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): void
    {
        $this->decCantidad = max(0, (int)($empresa['decimales_cantidad'] ?? 2));
        $this->decPrecio   = max(0, (int)($empresa['decimales_precio']   ?? 2));

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Compra ' . $this->numeroDocumento($cabecera));
        $this->pdf->SetMargins($this->marginL, 5, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);

        $y = $this->dibujarEncabezado($empresa, $cabecera);
        $y = $this->dibujarDatosAdquirente($empresa, $cabecera, $y + 2);
        $y = $this->dibujarDetalle($detalles, $y + 2);
        $this->dibujarPie($cabecera, $detalles, $pagos, $infoAdicional, $empresa, $y + 2);
    }

    // ─── ENCABEZADO ──────────────────────────────────────────────────────────
    // Caja izquierda: EMISOR = PROVEEDOR (con "NO TIENE LOGO" en rojo).
    // Caja derecha:   tipo de comprobante + número + autorización.
    private function dibujarEncabezado(array $empresa, array $cabecera): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $izqW = 85;
        $derW = $this->contentW - $izqW - 2; // 103mm
        $derX = $mL + $izqW + 2;

        $yTop  = 8;
        $yLogo = $yTop;

        $boxHeight      = 73.5;
        $logoAreaHeight = $boxHeight * 0.40;

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);

        // ── Área del logo: SIEMPRE "NO TIENE LOGO" en rojo (por pedido) ────────
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(220, 53, 69); // rojo Bootstrap (danger)
        $pdf->SetXY($mL + 2, $yLogo + ($logoAreaHeight / 2) - 5);
        $pdf->Cell($izqW - 4, 15, 'NO TIENE LOGO', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $yTopIzqBox = $yLogo + $logoAreaHeight;

        // ── Caja izquierda (datos del PROVEEDOR / emisor) ─────────────────────
        $yIzq = $yTopIzqBox + 3;

        $provNombre = trim($cabecera['proveedor_nombre'] ?? '');
        if ($provNombre) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->MultiCell($izqW - 4, 5, $provNombre, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Identificación del proveedor
        $provRuc  = trim($cabecera['proveedor_ruc'] ?? '');
        $provTipo = trim($cabecera['proveedor_nombre_tipo_id'] ?? 'Identificación');
        if ($provRuc) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell(28, 4.5, $provTipo . ':', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell($izqW - 30, 4.5, $provRuc, 0, 1, 'L');
            $yIzq = $pdf->GetY();
        }

        // Dirección del proveedor
        $provDir = trim($cabecera['proveedor_direccion'] ?? '');
        if ($provDir) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(18, 4, 'Dirección:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 22, 4, $provDir, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Correo del proveedor
        $provMail = trim($cabecera['proveedor_email'] ?? '');
        if ($provMail) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(14, 4, 'Correo:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 18, 4, $provMail, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        $yIzq += 2;

        // ── Caja derecha (datos del comprobante) ──────────────────────────────
        $yDer = $yTop;

        // Fila: R.U.C. del proveedor
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer + 2);
        $pdf->Cell(14, 5, 'R.U.C.:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($derW - 16, 5, $provRuc, 0, 1, 'L');
        $yDer += 8;

        // Tipo de comprobante (grande)
        $tipoCod    = (string)($cabecera['tipo_comprobante'] ?? '01');
        $tituloComp = $this->tiposComprobante[$tipoCod] ?? 'DOCUMENTO DE COMPRA';
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->MultiCell($derW - 4, 7, $tituloComp, 0, 'L', false, 1);
        $yDer = $pdf->GetY() + 1;

        // Número del documento
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell(7, 5, 'No.', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($derW - 9, 5, $this->numeroDocumento($cabecera), 0, 1, 'L');
        $yDer += 6;

        // NÚMERO DE AUTORIZACIÓN
        $autoriz = trim((string)($cabecera['numero_autorizacion'] ?? ''));
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'NÚMERO DE AUTORIZACIÓN', 0, 1, 'L');
        $yDer += 5;
        if ($autoriz) {
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->MultiCell($derW - 4, 4, $autoriz, 0, 'L', false, 1);
            $yDer = $pdf->GetY() + 1;
        }

        // Fecha de emisión
        $fechaEmi = $this->formatearFecha($cabecera['fecha_emision'] ?? '');
        if ($fechaEmi) {
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(28, 4.5, 'FECHA EMISIÓN:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($derW - 30, 4.5, $fechaEmi, 0, 1, 'L');
            $yDer += 5;
        }

        // Fecha de autorización (solo si el XML trae el sobre <autorizacion>)
        $fechaAut = trim((string)($cabecera['fecha_autorizacion'] ?? ''));
        if ($fechaAut !== '') {
            // Se usa DateTime para respetar el offset del XML (hora local Ecuador),
            // evitando que date()/strtotime la conviertan a la zona del servidor.
            try {
                $fechaAutTxt = (new \DateTime($fechaAut))->format('d/m/Y H:i:s');
            } catch (\Throwable $e) {
                $fechaAutTxt = $fechaAut;
            }
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(32, 4.5, 'FECHA AUTORIZACIÓN:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell($derW - 34, 4.5, $fechaAutTxt, 0, 1, 'L');
            $yDer += 5;
        }

        // AMBIENTE
        $tipoAmb  = (string)($cabecera['tipo_ambiente'] ?? $empresa['tipo_ambiente'] ?? '1');
        $ambiente = ($tipoAmb === '2') ? 'PRODUCCIÓN' : 'PRUEBAS';
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, 4.5, 'AMBIENTE:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($derW - 24, 4.5, $ambiente, 0, 1, 'L');
        $yDer += 5;

        // Código de barras si la autorización es una clave de acceso (49 dígitos)
        if ($autoriz !== '' && strlen($autoriz) === 49 && ctype_digit($autoriz)) {
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 4.5, 'CLAVE DE ACCESO', 0, 1, 'L');
            $yDer += 5;
            $barcodeH = 12;
            $pdf->write1DBarcode(
                $autoriz, 'C128', $derX + 2, $yDer, $derW - 1, $barcodeH, 0.4,
                ['position' => 'R', 'text' => false, 'stretcharray' => '', 'stretch' => true], 'N'
            );
            $yDer += $barcodeH + 1;
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 3.5, $autoriz, 0, 1, 'C');
            $yDer += 4;
        }

        $yDer += 2;

        // ── Bordes ────────────────────────────────────────────────────────────
        $yBottom = max($yIzq, $yDer);
        $pdf->RoundedRect($mL, $yTopIzqBox, $izqW, $yBottom - $yTopIzqBox, 3, '1111', 'D');
        $pdf->RoundedRect($derX, $yTop, $derW, $yBottom - $yTop, 3, '1111', 'D');

        return $yBottom;
    }

    // ─── DATOS DEL ADQUIRENTE (empresa activa = comprador) ─────────────────────
    private function dibujarDatosAdquirente(array $empresa, array $cab, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $pdf->SetLineWidth(0.3);
        $lh = 5;

        $fecha = $this->formatearFecha($cab['fecha_emision'] ?? '');
        $yBox  = $y;

        // Fila 1: Razón Social del adquirente
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1.5);
        $pdf->Cell(48, $lh, 'Adquirente (Razón Social):', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 50, $lh, $empresa['nombre'] ?? '', 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 2: Identificación (RUC empresa) | Fecha registro
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(20, $lh, 'Identificación:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(40, $lh, $empresa['ruc'] ?? '', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(24, $lh, 'Fecha registro:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 86, $lh, $this->formatearFecha($cab['fecha_registro'] ?? '') ?: $fecha, 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 3: Dirección de la empresa
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(15, $lh, 'Dirección:', 0, 0, 'L');
        $dir = trim($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? '');
        $pdf->Cell($cW - 17, $lh, $dir, 0, 1, 'L');
        $yBox += $lh + 2;

        $pdf->Rect($mL, $y, $cW, $yBox - $y, 'D');

        return $yBox;
    }

    // ─── DETALLE ─────────────────────────────────────────────────────────────
    private function dibujarDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $cols = [
            ['key' => 'codp', 'titulo' => "Cod.\nPrincipal",  'w' => 22, 'align' => 'L'],
            ['key' => 'cant', 'titulo' => "Cantidad",         'w' => 18, 'align' => 'R'],
            ['key' => 'desc', 'titulo' => "Descripción",      'w' => 82, 'align' => 'L'],
            ['key' => 'pu',   'titulo' => "Precio\nUnitario", 'w' => 24, 'align' => 'R'],
            ['key' => 'dcto', 'titulo' => "Descuento",        'w' => 20, 'align' => 'R'],
            ['key' => 'ptot', 'titulo' => "Precio\nTotal",    'w' => 24, 'align' => 'R'],
        ];

        // Ajustar Descripción para que la suma sea exactamente contentW
        $sumaW = array_sum(array_column($cols, 'w'));
        if ($sumaW !== (int)$cW) {
            foreach ($cols as &$c) {
                if ($c['key'] === 'desc') { $c['w'] += ((int)$cW - $sumaW); break; }
            }
            unset($c);
        }

        // Encabezado
        $pdf->SetFont('helvetica', 'B', 6.5);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetXY($mL, $y);
        foreach ($cols as $col) {
            $pdf->MultiCell($col['w'], 7.6, $col['titulo'], 1, 'C', true, 0, '', '', true, 0, false, true, 7.6, 'M');
        }
        $pdf->Ln();

        $hdrH = 7.6;
        $y   += $hdrH;

        // Filas
        $pdf->SetFont('helvetica', '', 7);
        $altColor = false;

        foreach ($detalles as $d) {
            $bg = $altColor ? [250, 250, 250] : [255, 255, 255];
            $altColor = !$altColor;
            $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);

            $pu   = (float)($d['precio_unitario'] ?? 0);
            $dcto = (float)($d['descuento'] ?? 0);
            $ptot = (float)($d['precio_total_sin_impuesto'] ?? 0);

            $vals = [
                'codp' => $d['codigo_principal'] ?? ($d['producto_codigo'] ?? ''),
                'cant' => number_format((float)($d['cantidad'] ?? 0), $this->decCantidad),
                'desc' => $d['descripcion'] ?? ($d['producto_nombre'] ?? ''),
                'pu'   => number_format($pu, $this->decPrecio),
                'dcto' => number_format($dcto, 2),
                'ptot' => number_format($ptot, 2),
            ];

            $idxDesc = 2;
            $nDesc = max(1, (int)ceil($pdf->GetStringWidth($vals['desc']) / ($cols[$idxDesc]['w'] - 2)));
            $ch    = max(5, $nDesc * 4.5);

            $xCur = $mL;
            $yRow = $pdf->GetY();

            foreach ($cols as $col) {
                $val = $vals[$col['key']];
                $pdf->SetXY($xCur, $yRow);
                if ($col['key'] === 'desc') {
                    $pdf->MultiCell($col['w'], $ch, $val, 1, $col['align'], true, 0, '', '', true, 0, false, true, 0, 'M');
                } else {
                    $pdf->Cell($col['w'], $ch, $val, 1, 0, $col['align'], true);
                }
                $xCur += $col['w'];
            }
            $pdf->SetXY($mL, $yRow + $ch);
        }

        return $pdf->GetY();
    }

    // ─── PIE ─────────────────────────────────────────────────────────────────
    private function dibujarPie(array $cab, array $detalles, array $pagos, array $infoAdicional, array $empresa, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        // Totales por concepto de IVA (misma lógica que factura/XML).
        $subtotMap = []; $ivaMap = []; $tarifaMap = [];
        $totalIce = 0.0; $totalDcto = 0.0; $noObjIva = 0.0; $exentoIva = 0.0;

        foreach ($detalles as $d) {
            $totalDcto += (float)($d['descuento'] ?? 0);
            $tieneImp   = false;
            foreach ($d['impuestos'] ?? [] as $imp) {
                $cod  = (string)($imp['codigo_impuesto'] ?? '');
                $tar  = (float)($imp['tarifa'] ?? 0);
                $val  = (float)($imp['valor'] ?? 0);
                $base = (float)($imp['base_imponible'] ?? $d['precio_total_sin_impuesto'] ?? 0);

                if ($cod === '2') { // IVA
                    $codPct = $tar > 0
                        ? \App\Helpers\SriIvaHelper::codigoPorcentaje($tar)
                        : (string)($imp['codigo_porcentaje'] ?? '0');

                    if ($codPct === '6') {
                        $noObjIva += $base;
                    } elseif ($codPct === '7') {
                        $exentoIva += $base;
                    } else {
                        $subtotMap[$codPct] = ($subtotMap[$codPct] ?? 0.0) + $base;
                        $ivaMap[$codPct]    = ($ivaMap[$codPct]    ?? 0.0) + $val;
                        $tarifaMap[$codPct] = $tar;
                    }
                    $tieneImp = true;
                } elseif ($cod === '3') {
                    $totalIce += $val;
                }
            }
            if (!$tieneImp) {
                $subtotMap['0'] = ($subtotMap['0'] ?? 0.0) + (float)($d['precio_total_sin_impuesto'] ?? 0);
                $ivaMap['0']    = ($ivaMap['0'] ?? 0.0);
                $tarifaMap['0'] = 0.0;
            }
        }
        ksort($subtotMap);
        ksort($ivaMap);

        $totalIva = array_sum($ivaMap);
        $propina  = (float)($cab['propina'] ?? 0);

        $subtotalSinImp = isset($cab['total_sin_impuestos'])
            ? (float)$cab['total_sin_impuestos']
            : array_sum($subtotMap) + $noObjIva + $exentoIva;
        $total = isset($cab['importe_total'])
            ? (float)$cab['importe_total']
            : $subtotalSinImp + $totalIva + $totalIce + $propina;

        if ($y > 212) { $pdf->AddPage(); $y = 12; }

        $totW = 72;
        $izqW = $cW - $totW - 2;
        $totX = $mL + $izqW + 2;
        $lh   = 5;

        // ── Columna derecha: tabla de totales ─────────────────────────────────
        $yTot = $y;
        $pdf->SetLineWidth(0.3);
        $lblW = 54;
        $valW = $totW - $lblW;

        foreach ($subtotMap as $codPct => $base) {
            $tarPct   = $tarifaMap[$codPct] ?? 0.0;
            $tarLabel = $tarPct == (int)$tarPct ? (string)(int)$tarPct : number_format($tarPct, 2);
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, "SUBTOTAL {$tarLabel}%", $base);
            $yTot += $lh;
        }
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL NO OBJETO DE IVA', $noObjIva);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL EXENTO DE IVA', $exentoIva);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL SIN IMPUESTOS', $subtotalSinImp);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'TOTAL DESCUENTO', $totalDcto);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'ICE', $totalIce);
        $yTot += $lh;

        foreach ($ivaMap as $codPct => $ivaVal) {
            $tarPct   = $tarifaMap[$codPct] ?? 0.0;
            $tarLabel = $tarPct == (int)$tarPct ? (string)(int)$tarPct : number_format($tarPct, 2);
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, "IVA {$tarLabel}%", $ivaVal);
            $yTot += $lh;
        }

        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'PROPINA', $propina);
        $yTot += $lh;

        // VALOR TOTAL
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(210, 210, 210);
        $pdf->SetXY($totX, $yTot);
        $pdf->Cell($lblW, $lh, 'VALOR TOTAL', 1, 0, 'L', true);
        $pdf->Cell($valW, $lh, number_format($total, 2), 1, 1, 'R', true);
        $yTot += $lh;

        // ── Columna izquierda: Información Adicional + Observaciones + Pagos ───
        $yIzq = $y;

        if (!empty($infoAdicional)) {
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'Información Adicional', 1, 1, 'C', true);
            $yIzq += $lh;

            $etiqW = 40;
            $valIW = $izqW - $etiqW;
            $pdf->SetFillColor(255, 255, 255);
            foreach ($infoAdicional as $info) {
                $pdf->SetXY($mL, $yIzq);
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->Cell($etiqW, $lh, $info['nombre'] ?? '', 1, 0, 'L');
                $pdf->SetFont('helvetica', '', 7);
                $pdf->MultiCell($valIW, $lh, $info['valor'] ?? '', 1, 'L', false, 1);
                $yIzq = $pdf->GetY();
            }
        }

        if (!empty($cab['observaciones'])) {
            $yIzq += 1;
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'Observaciones', 1, 1, 'C', true);
            $yIzq += $lh;
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($mL, $yIzq);
            $pdf->MultiCell($izqW, 4.5, $cab['observaciones'], 1, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        if (!empty($pagos)) {
            $yIzq += 1;
            $wNombre = $izqW - 28 - 22 - 22;
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($wNombre, $lh, 'Forma de pago', 1, 0, 'C', true);
            $pdf->Cell(28, $lh, 'Valor',        1, 0, 'C', true);
            $pdf->Cell(22, $lh, 'Días Crédito', 1, 0, 'C', true);
            $pdf->Cell(22, $lh, 'Plazo',        1, 1, 'C', true);
            $yIzq += $lh;

            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);
            foreach ($pagos as $p) {
                $nombreP = $p['forma_pago_nombre'] ?? ($p['nombre_forma_pago'] ?? ($p['forma_pago'] ?? ''));
                $valorP  = number_format((float)($p['total'] ?? 0), 2);
                $dias    = (int)($p['plazo'] ?? 0);
                $unidad  = trim($p['unidad_tiempo'] ?? 'dias');
                $plazoLbl = $dias > 0 ? $dias . ' ' . $unidad : '—';
                $diasLbl  = $dias > 0 ? (string)$dias : '0';

                $numLines = $pdf->getNumLines($nombreP, $wNombre);
                if ($numLines < 1) $numLines = 1;
                $rowH = $numLines * $lh;

                $pdf->SetXY($mL, $yIzq);
                $pdf->MultiCell($wNombre, $rowH, $nombreP, 1, 'L', false, 0);
                $pdf->Cell(28, $rowH, $valorP,  1, 0, 'R');
                $pdf->Cell(22, $rowH, $diasLbl, 1, 0, 'C');
                $pdf->Cell(22, $rowH, $plazoLbl, 1, 1, 'C');
                $yIzq += $rowH;
            }
        }

    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────
    private function filaTotales(
        TCPDF $pdf, float $x, float $y,
        float $lblW, float $valW, float $h,
        string $lbl, float $val
    ): void {
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($x, $y);
        $pdf->Cell($lblW, $h, $lbl, 1, 0, 'L');
        $pdf->Cell($valW, $h, number_format($val, 2), 1, 0, 'R');
    }

    private function numeroDocumento(array $cab): string
    {
        $est = str_pad((string)($cab['establecimiento_prov'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $pto = str_pad((string)($cab['punto_emision_prov']   ?? '001'), 3, '0', STR_PAD_LEFT);
        $sec = str_pad((string)($cab['secuencial_prov']      ?? '000000001'), 9, '0', STR_PAD_LEFT);
        return "{$est}-{$pto}-{$sec}";
    }

    private function formatearFecha($fecha): string
    {
        $fecha = trim((string)$fecha);
        if ($fecha === '') return '';
        $ts = strtotime($fecha);
        return $ts ? date('d/m/Y', $ts) : $fecha;
    }
}
