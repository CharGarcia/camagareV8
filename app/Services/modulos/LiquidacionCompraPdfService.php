<?php
declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Genera el PDF de la Liquidación de Compra de Bienes y Prestación de Servicios
 * (SRI codDoc = 03). Usa TCPDF — mismo patrón/diseño que RetencionCompraPdfService
 * y FacturaVentaPdfService (encabezado con logo, RUC, autorización, clave y barcode).
 *
 * @param array $cabecera       Fila de getPorId() (con joins de proveedor y sustento)
 * @param array $detalles       Filas de liquidaciones_detalle, cada una con clave 'impuestos'
 * @param array $pagos          Filas de liquidaciones_pagos
 * @param array $infoAdicional  Filas de liquidaciones_adicional
 * @param array $empresa        Fila de empresas (enriquecida con logo/direcciones del establecimiento)
 */
class LiquidacionCompraPdfService
{
    private const MARGEN_H = 10;
    private const FUENTE   = 'helvetica';

    // ── Descarga directa ─────────────────────────────────────────

    public function generar(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): void
    {
        $pdf = $this->construir($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        $num = str_pad((string)($cabecera['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
             . str_pad((string)($cabecera['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
             . str_pad((string)($cabecera['secuencial']       ?? ''),   9, '0', STR_PAD_LEFT);
        $pdf->Output('Liquidacion_' . $num . '.pdf', 'D');
    }

    // ── Retornar bytes (para adjuntar en correo) ─────────────────

    public function generarBytes(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): string
    {
        $pdf = $this->construir($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        return $pdf->Output('liquidacion.pdf', 'S');
    }

    // ── Constructor interno ──────────────────────────────────────

    private function construir(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): \TCPDF
    {
        require_once MVC_ROOT . '/vendor/autoload.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor($empresa['nombre'] ?? '');
        $pdf->SetTitle('Liquidación de Compra');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGEN_H, self::MARGEN_H, self::MARGEN_H);
        $pdf->SetAutoPageBreak(true, self::MARGEN_H);
        $pdf->AddPage();

        $this->dibujarEncabezado($pdf, $cabecera, $empresa);
        $this->dibujarProveedor($pdf, $cabecera);
        $this->dibujarDetalles($pdf, $detalles, $empresa);
        $this->dibujarPie($pdf, $cabecera, $detalles, $pagos, $infoAdicional);

        return $pdf;
    }

    // ── Encabezado: empresa + datos comprobante (mismo diseño que retención/factura) ──

    private function dibujarEncabezado(\TCPDF $pdf, array $cab, array $emp): void
    {
        $mL       = self::MARGEN_H;
        $contentW = $pdf->getPageWidth() - 2 * self::MARGEN_H; // 190mm
        $izqW     = 85;
        $derW     = $contentW - $izqW - 2;   // 103mm
        $derX     = $mL + $izqW + 2;

        $yTop           = 8;
        $yLogo          = $yTop;
        $boxHeight      = 73.5;
        $logoAreaHeight = $boxHeight * 0.40;

        // Resolver ruta del logo (mismo criterio que factura/retención)
        $logoPath = '';
        $rutasPosibles = [];
        if (!empty($emp['logo_ruta'])) $rutasPosibles[] = $emp['logo_ruta'];
        if (!empty($emp['logo']))      $rutasPosibles[] = $emp['logo'];

        foreach ($rutasPosibles as $ruta) {
            $cleanRuta = ltrim($ruta, '/');
            if (strpos($cleanRuta, 'sistema/public/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('sistema/public/'));
            } elseif (strpos($cleanRuta, 'sistema/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('sistema/'));
            }
            if (strpos($cleanRuta, 'public/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('public/'));
            }
            $candidatos = [
                \MVC_ROOT . '/public/' . $cleanRuta,
                \MVC_ROOT . '/' . $cleanRuta,
            ];
            foreach ($candidatos as $testPath) {
                if (file_exists($testPath)) { $logoPath = $testPath; break 2; }
            }
        }

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);

        if ($logoPath) {
            $pdf->Image($logoPath, $mL + 2, $yLogo + 2, $izqW - 4, $logoAreaHeight - 4, '', '', '', false, 300, '', false, false, 0, 'CM');
        } else {
            $pdf->SetFont(self::FUENTE, 'B', 18);
            $pdf->SetTextColor(160, 160, 160);
            $pdf->SetXY($mL + 2, $yLogo + ($logoAreaHeight / 2) - 5);
            $pdf->Cell($izqW - 4, 15, 'SIN LOGO', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $yTopIzqBox = $yLogo + $logoAreaHeight;

        // ── Caja izquierda (contenido) ───────────────────────────────────────
        $yIzq = $yTopIzqBox + 3;

        $nomComercial = trim($emp['nombre_comercial'] ?? '');
        $nomRazon     = trim($emp['nombre'] ?? '');
        if ($nomComercial) {
            $pdf->SetFont(self::FUENTE, 'B', 9);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->MultiCell($izqW - 4, 5, $nomComercial, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }
        if ($nomRazon && $nomRazon !== $nomComercial) {
            $pdf->SetFont(self::FUENTE, '', 8);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->MultiCell($izqW - 4, 4.5, $nomRazon, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Dirección Matriz
        $dirMat = trim($emp['direccion_matriz'] ?? $emp['direccion'] ?? '');
        if ($dirMat) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->Cell(22, 4, 'Dirección Matriz:', 0, 0, 'L');
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->MultiCell($izqW - 26, 4, $dirMat, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Dirección Sucursal
        $dirSuc = trim($emp['direccion_establecimiento'] ?? $emp['direccion_sucursal'] ?? '');
        if (empty($dirSuc)) $dirSuc = trim($emp['direccion'] ?? '');
        if ($dirSuc && $dirSuc !== $dirMat) {
            $yBefore = $pdf->GetY();
            $pdf->SetXY($mL + 2, $yBefore);
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->MultiCell(20, 3.5, "Dirección\nSucursal:", 0, 'L', false, 1);
            $yAfterLabel = $pdf->GetY();
            $pdf->SetXY($mL + 22, $yBefore);
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->MultiCell($izqW - 24, 3.5, $dirSuc, 0, 'L', false, 1);
            $yIzq = max($yAfterLabel, $pdf->GetY());
        }

        // Contribuyente Especial
        $resCont = trim((string)($emp['resolucion_contribuyente'] ?? $emp['contribuyente_especial'] ?? ''));
        if ($resCont) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, '', 7.5);
            $pdf->Cell(30, 4.5, 'Contribuyente Especial', 0, 0, 'L');
            $pdf->SetFont(self::FUENTE, 'B', 7.5);
            $pdf->Cell($izqW - 32, 4.5, $resCont, 0, 1, 'L');
            $yIzq = $pdf->GetY();
        }

        // Obligado a llevar contabilidad
        $oblStr  = strtoupper(trim((string)($emp['obligado_contabilidad'] ?? 'NO')));
        $oblabel = ($oblStr === 'SI' || $oblStr === '1' || $oblStr === 'TRUE') ? 'SI' : 'NO';
        $pdf->SetXY($mL + 2, $yIzq);
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->Cell(55, 4.5, 'OBLIGADO A LLEVAR CONTABILIDAD', 0, 0, 'L');
        $pdf->SetFont(self::FUENTE, 'B', 7.5);
        $pdf->Cell($izqW - 57, 4.5, $oblabel, 0, 1, 'L');
        $yIzq = $pdf->GetY() + 1;

        // Agente de Retención
        $agenteRet = \App\Helpers\SriEmisorHelper::agenteRetencionNumero($emp);
        if ($agenteRet !== '') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, '', 7.5);
            $pdf->Cell(55, 4.5, 'Agente de Retención Resolución No.', 0, 0, 'L');
            $pdf->SetFont(self::FUENTE, 'B', 7.5);
            $pdf->Cell($izqW - 57, 4.5, $agenteRet, 0, 1, 'L');
            $yIzq = $pdf->GetY() + 1;
        }

        // Régimen RIMPE
        $rimpe = \App\Helpers\SriEmisorHelper::regimenRimpeLeyenda($emp);
        if ($rimpe) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, 'B', 7.5);
            $pdf->MultiCell($izqW - 4, 4.5, $rimpe, 0, 'L', false, 1);
            $yIzq = $pdf->GetY() + 1;
        }

        $yIzq += 2;

        // ── Caja derecha ──────────────────────────────────────────────────────
        $yDer = $yTop;

        // R.U.C.
        $pdf->SetFont(self::FUENTE, '', 8);
        $pdf->SetXY($derX + 2, $yDer + 2);
        $pdf->Cell(14, 5, 'R.U.C.:', 0, 0, 'L');
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->Cell($derW - 16, 5, $emp['ruc'] ?? '', 0, 1, 'L');
        $yDer += 8;

        // Título del comprobante (nombre oficial SRI para codDoc 03)
        $pdf->SetFont(self::FUENTE, 'B', 9.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->MultiCell($derW - 4, 4.8, 'LIQUIDACIÓN DE COMPRA DE BIENES Y PRESTACIÓN DE SERVICIOS', 0, 'L', false, 1);
        $yDer = $pdf->GetY() + 1;

        // Número
        $num = str_pad((string)($cab['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
             . str_pad((string)($cab['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
             . str_pad((string)($cab['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        $pdf->SetFont(self::FUENTE, '', 8);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell(7, 5, 'No.', 0, 0, 'L');
        $pdf->Cell($derW - 9, 5, $num, 0, 1, 'L');
        $yDer += 6;

        // NÚMERO DE AUTORIZACIÓN
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'NÚMERO DE AUTORIZACIÓN', 0, 1, 'L');
        $yDer += 5;

        // Clave de acceso / autorización como texto
        $claveAcceso = trim((string)($cab['numero_autorizacion'] ?? '')) ?: trim((string)($cab['clave_acceso'] ?? ''));
        if ($claveAcceso) {
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->MultiCell($derW - 4, 4, $claveAcceso, 0, 'L', false, 1);
            $yDer = $pdf->GetY() + 1;
        }

        // Fecha y hora de autorización
        if (!empty($cab['fecha_autorizacion'])) {
            $fa = date('d/m/Y H:i:s', strtotime($cab['fecha_autorizacion']));
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont(self::FUENTE, '', 7.5);
            $pdf->Cell(32, 4.5, 'FECHA Y HORA', 0, 0, 'L');
            $pdf->Cell($derW - 34, 4.5, $fa, 0, 1, 'L');
            $yDer += 4.5;
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell(32, 4.5, 'AUTORIZACIÓN:', 0, 1, 'L');
            $yDer += 4.5;
        }

        // Ambiente
        $tipoAmb  = (string)($cab['tipo_ambiente'] ?? $emp['tipo_ambiente'] ?? '1');
        $ambiente = ($tipoAmb === '2') ? 'PRODUCCIÓN' : 'PRUEBAS';
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->Cell(22, 4.5, 'AMBIENTE:', 0, 0, 'L');
        $pdf->SetFont(self::FUENTE, 'B', 7.5);
        $pdf->Cell($derW - 24, 4.5, $ambiente, 0, 1, 'L');
        $yDer += 4.5;

        // Emisión
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->Cell(22, 4.5, 'EMISIÓN:', 0, 0, 'L');
        $pdf->SetFont(self::FUENTE, 'B', 7.5);
        $pdf->Cell($derW - 24, 4.5, 'NORMAL', 0, 1, 'L');
        $yDer += 5;

        // CLAVE DE ACCESO (etiqueta + barcode)
        $claveBarcode = trim((string)($cab['clave_acceso'] ?? ''));
        if ($claveBarcode) {
            $pdf->SetFont(self::FUENTE, '', 7.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 4.5, 'CLAVE DE ACCESO', 0, 1, 'L');
            $yDer += 5;

            $barcodeH = 12;
            $pdf->write1DBarcode(
                $claveBarcode, 'C128', $derX + 2, $yDer, $derW - 1, $barcodeH, 0.4,
                ['position' => 'R', 'text' => false, 'stretcharray' => '', 'stretch' => true], 'N'
            );
            $yDer += $barcodeH + 1;
            $pdf->SetFont(self::FUENTE, '', 5.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 3.5, $claveBarcode, 0, 1, 'C');
            $yDer += 4;
        }

        $yDer += 2;

        // ── Bordes (alineados al fondo) ───────────────────────────────────────
        $yBottom = max($yIzq, $yDer);
        $pdf->RoundedRect($mL, $yTopIzqBox, $izqW, $yBottom - $yTopIzqBox, 3, '1111', 'D');
        $pdf->RoundedRect($derX, $yTop, $derW, $yBottom - $yTop, 3, '1111', 'D');

        $pdf->SetXY($mL, $yBottom + 3);
    }

    // ── Datos del proveedor ──────────────────────────────────────

    private function dibujarProveedor(\TCPDF $pdf, array $cab): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;
        $y     = $pdf->GetY();

        $pdf->SetXY($x, $y);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($pageW, 5, 'DATOS DEL PROVEEDOR', 1, 1, 'C', true);

        $pdf->SetFont(self::FUENTE, '', 7.5);
        $halfW = $pageW / 2;

        $pdf->SetX($x);
        $pdf->Cell($halfW * 0.35, 4.5, 'Razón Social:', 'LB', 0, 'L');
        $pdf->Cell($halfW * 0.65, 4.5, $cab['proveedor_nombre'] ?? '', 'RB', 0, 'L');
        $pdf->Cell($halfW * 0.35, 4.5, 'Identificación:', 'LB', 0, 'L');
        $pdf->Cell($halfW * 0.65, 4.5, $cab['proveedor_ruc'] ?? '', 'RB', 1, 'L');

        $pdf->SetX($x);
        $pdf->Cell($pageW * 0.175, 4.5, 'Fecha Emisión:', 'LB', 0, 'L');
        $fe = !empty($cab['fecha_emision']) ? date('d/m/Y', strtotime($cab['fecha_emision'])) : '';
        $pdf->Cell($pageW * 0.325, 4.5, $fe, 'B', 0, 'L');
        $pdf->Cell($pageW * 0.175, 4.5, 'Dirección:', 'LB', 0, 'L');
        $pdf->Cell($pageW * 0.325, 4.5, $cab['proveedor_direccion'] ?? '', 'RB', 1, 'L');

        $pdf->Ln(2);
    }

    // ── Tabla de detalles ────────────────────────────────────────

    private function dibujarDetalles(\TCPDF $pdf, array $detalles, array $emp): void
    {
        $decCant   = max(0, min(6, (int)($emp['decimales_cantidad'] ?? 2)));
        $decPrecio = max(0, min(6, (int)($emp['decimales_precio']   ?? 2)));

        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;

        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($pageW, 5, 'DETALLE', 1, 1, 'C', true);

        $anchos = [
            'codigo'  => $pageW * 0.12,
            'desc'    => $pageW * 0.40,
            'cant'    => $pageW * 0.10,
            'precio'  => $pageW * 0.13,
            'descto'  => $pageW * 0.11,
            'total'   => $pageW * 0.14,
        ];

        $pdf->SetFont(self::FUENTE, 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetX($x);
        $pdf->Cell($anchos['codigo'], 5, 'CÓDIGO',        1, 0, 'C', true);
        $pdf->Cell($anchos['desc'],   5, 'DESCRIPCIÓN',   1, 0, 'C', true);
        $pdf->Cell($anchos['cant'],   5, 'CANT.',         1, 0, 'C', true);
        $pdf->Cell($anchos['precio'], 5, 'P. UNIT.',      1, 0, 'R', true);
        $pdf->Cell($anchos['descto'], 5, 'DESC.',         1, 0, 'R', true);
        $pdf->Cell($anchos['total'],  5, 'SUBTOTAL',      1, 1, 'R', true);

        $pdf->SetFont(self::FUENTE, '', 7);
        $pdf->SetFillColor(255, 255, 255);

        foreach ($detalles as $d) {
            $codigo = trim((string)($d['codigo_principal'] ?? ''));
            $desc   = (string)($d['descripcion'] ?? '');

            $pdf->SetX($x);
            $yBefore = $pdf->GetY();
            $nLineas = max(ceil(mb_strlen($desc) / 55), 1);
            $h = max(4.5, $nLineas * 4.5);

            $pdf->MultiCell($anchos['codigo'], $h, $codigo,                                            1, 'C', false, 0);
            $pdf->MultiCell($anchos['desc'],   $h, $desc,                                              1, 'L', false, 0);
            $pdf->MultiCell($anchos['cant'],   $h, number_format((float)($d['cantidad'] ?? 0), $decCant),         1, 'C', false, 0);
            $pdf->MultiCell($anchos['precio'], $h, number_format((float)($d['precio_unitario'] ?? 0), $decPrecio), 1, 'R', false, 0);
            $pdf->MultiCell($anchos['descto'], $h, number_format((float)($d['descuento'] ?? 0), 2),               1, 'R', false, 0);
            $pdf->MultiCell($anchos['total'],  $h, number_format((float)($d['precio_total_sin_impuesto'] ?? 0), 2), 1, 'R', false, 1);

            $pdf->SetY(max($pdf->GetY(), $yBefore + $h));
        }

        $pdf->Ln(2);
    }

    // ── Pie: dos columnas — izquierda (Info Adicional + Observaciones +
    //    Forma de pago) / derecha (totales), mismo diseño que factura de venta ──

    private function dibujarPie(\TCPDF $pdf, array $cab, array $detalles, array $pagos, array $infoAdicional): void
    {
        $mL = self::MARGEN_H;
        $cW = $pdf->getPageWidth() - 2 * self::MARGEN_H;

        // Agrupar por concepto de IVA (codigo_porcentaje del SRI), con la MISMA
        // lógica que el XML/RIDE de factura: la tarifa 0 puede ser 0% (0),
        // No Objeto (6) o Exento (7) — son tipos distintos, no el mismo subtotal.
        $subtotMap = []; // codigo_porcentaje => base imponible (SUBTOTAL X%)
        $ivaMap    = []; // codigo_porcentaje => valor IVA
        $tarifaMap = []; // codigo_porcentaje => tarifa numérica (para la etiqueta)
        $noObjIva  = 0.0;
        $exentoIva = 0.0;
        foreach ($detalles as $d) {
            foreach ($d['impuestos'] ?? [] as $imp) {
                if ((int)($imp['codigo_impuesto'] ?? 0) !== 2) continue; // solo IVA
                $tar  = (float)($imp['tarifa'] ?? 0);
                $base = (float)($imp['base_imponible'] ?? 0);
                $val  = (float)($imp['valor'] ?? 0);

                // Para tarifa > 0 el código se deriva de la tarifa real (evita códigos
                // desactualizados); para tarifa 0 se respeta el guardado (0/6/7).
                $codPct = $tar > 0
                    ? \App\Helpers\SriIvaHelper::codigoPorcentaje($tar)
                    : (string)($imp['codigo_porcentaje'] ?? '0');

                if ($codPct === '6') {            // No objeto de IVA
                    $noObjIva += $base;
                } elseif ($codPct === '7') {      // Exento de IVA
                    $exentoIva += $base;
                } else {                          // 0%, 5%, 15%, ...
                    $subtotMap[$codPct] = ($subtotMap[$codPct] ?? 0.0) + $base;
                    $ivaMap[$codPct]    = ($ivaMap[$codPct]    ?? 0.0) + $val;
                    $tarifaMap[$codPct] = $tar;
                }
            }
        }
        ksort($subtotMap);
        ksort($ivaMap);

        $subtotalSinImp = (float)($cab['total_sin_impuestos'] ?? (array_sum($subtotMap) + $noObjIva + $exentoIva));
        $totalDcto      = (float)($cab['total_descuento'] ?? 0);
        $totalIva       = array_sum($ivaMap);
        $total          = (float)($cab['importe_total'] ?? ($subtotalSinImp + $totalIva));

        $y = $pdf->GetY() + 1;
        if ($y > 230) { $pdf->AddPage(); $y = 12; }

        // Layout de dos columnas (igual que factura).
        $totW = 72;
        $izqW = $cW - $totW - 2;
        $totX = $mL + $izqW + 2;
        $lh   = 5;
        $lblW = 54;
        $valW = $totW - $lblW;

        // ── Columna derecha: totales ──────────────────────────────────────────
        $yTot = $y;
        $pdf->SetLineWidth(0.3);

        // Subtotales por concepto de IVA (etiqueta con la tarifa real).
        foreach ($subtotMap as $codPct => $base) {
            $lbl = 'SUBTOTAL ' . self::tarLabel($tarifaMap[$codPct] ?? 0.0) . '%';
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, $lbl, $base);
            $yTot += $lh;
        }
        if ($noObjIva > 0) {
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL NO OBJETO DE IVA', $noObjIva);
            $yTot += $lh;
        }
        if ($exentoIva > 0) {
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL EXENTO DE IVA', $exentoIva);
            $yTot += $lh;
        }
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL SIN IMPUESTOS', $subtotalSinImp);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'TOTAL DESCUENTO', $totalDcto);
        $yTot += $lh;
        // IVA por concepto (etiqueta con la tarifa real).
        foreach ($ivaMap as $codPct => $val) {
            $lbl = 'IVA ' . self::tarLabel($tarifaMap[$codPct] ?? 0.0) . '%';
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, $lbl, $val);
            $yTot += $lh;
        }
        // VALOR TOTAL (negrita, con fondo)
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->SetFillColor(210, 210, 210);
        $pdf->SetXY($totX, $yTot);
        $pdf->Cell($lblW, $lh, 'VALOR TOTAL', 1, 0, 'L', true);
        $pdf->Cell($valW, $lh, number_format($total, 2), 1, 1, 'R', true);
        $yTot += $lh;

        // ── Columna izquierda: Info Adicional + Observaciones + Forma de pago ──
        $yIzq = $y;

        // Información Adicional
        if (!empty($infoAdicional)) {
            $pdf->SetFont(self::FUENTE, 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'Información Adicional', 1, 1, 'C', true);
            $yIzq += $lh;

            $etiqW = 40;
            $valIW = $izqW - $etiqW;
            $pdf->SetFillColor(255, 255, 255);
            foreach ($infoAdicional as $info) {
                if (empty($info['nombre']) && ($info['valor'] ?? '') === '') continue;
                $pdf->SetXY($mL, $yIzq);
                $pdf->SetFont(self::FUENTE, 'B', 7);
                $pdf->Cell($etiqW, $lh, (string)($info['nombre'] ?? ''), 1, 0, 'L');
                $pdf->SetFont(self::FUENTE, '', 7);
                $pdf->MultiCell($valIW, $lh, (string)($info['valor'] ?? ''), 1, 'L', false, 1);
                $yIzq = $pdf->GetY();
            }
        }

        // Observaciones
        if (!empty($cab['observaciones'])) {
            $yIzq += 1;
            $pdf->SetFont(self::FUENTE, 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'Observaciones', 1, 1, 'C', true);
            $yIzq += $lh;
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($mL, $yIzq);
            $pdf->MultiCell($izqW, 4.5, (string)$cab['observaciones'], 1, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Forma de pago
        if (!empty($pagos)) {
            $yIzq += 1;
            $wNombre = $izqW - 28 - 22 - 22;
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($wNombre, $lh, 'Forma de pago', 1, 0, 'C', true);
            $pdf->Cell(28,       $lh, 'Valor',        1, 0, 'C', true);
            $pdf->Cell(22,       $lh, 'Días Crédito', 1, 0, 'C', true);
            $pdf->Cell(22,       $lh, 'Plazo',        1, 1, 'C', true);
            $yIzq += $lh;

            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->SetFillColor(255, 255, 255);
            foreach ($pagos as $p) {
                $nombreP  = self::formaPagoLabel((string)($p['forma_pago'] ?? ''));
                $valorP   = number_format((float)($p['total'] ?? 0), 2);
                $dias     = (int)($p['plazo'] ?? 0);
                $unidad   = trim((string)($p['unidad_tiempo'] ?? 'dias'));
                $plazoLbl = $dias > 0 ? $dias . ' ' . $unidad : '—';
                $diasLbl  = $dias > 0 ? (string)$dias : '0';

                $numLines = max(1, $pdf->getNumLines($nombreP, $wNombre));
                $rowH     = $numLines * $lh;

                $pdf->SetXY($mL, $yIzq);
                $pdf->MultiCell($wNombre, $rowH, $nombreP, 1, 'L', false, 0);
                $pdf->Cell(28, $rowH, $valorP,   1, 0, 'R');
                $pdf->Cell(22, $rowH, $diasLbl,  1, 0, 'C');
                $pdf->Cell(22, $rowH, $plazoLbl, 1, 1, 'C');
                $yIzq += $rowH;
            }
        }

        $pdf->SetY(max($yTot, $yIzq) + 2);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function filaTotales(\TCPDF $pdf, float $x, float $y, float $lblW, float $valW, float $h, string $lbl, float $val): void
    {
        $pdf->SetFont(self::FUENTE, '', 7);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($x, $y);
        $pdf->Cell($lblW, $h, $lbl, 1, 0, 'L');
        $pdf->Cell($valW, $h, number_format($val, 2), 1, 0, 'R');
    }

    /** Etiqueta de tarifa sin decimales si es entera (15, 12, 5), con decimales si no. */
    private static function tarLabel(float $tar): string
    {
        return $tar == (int)$tar ? (string)(int)$tar : number_format($tar, 2);
    }

    private static function formaPagoLabel(string $cod): string
    {
        return match ($cod) {
            '01' => '01 - Sin utilización del sistema financiero',
            '15' => '15 - Compensación de deudas',
            '16' => '16 - Tarjeta de débito',
            '17' => '17 - Dinero electrónico',
            '18' => '18 - Tarjeta prepago',
            '19' => '19 - Tarjeta de crédito',
            '20' => '20 - Otros con utilización del sistema financiero',
            '21' => '21 - Endoso de títulos',
            default => $cod !== '' ? $cod : 'Sin especificar',
        };
    }
}
