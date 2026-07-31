<?php
declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * RIDE de "Factura de Reembolso". Replica el modelo visual del RIDE de Nota
 * de Débito (App\Services\modulos\NotaDebitoPdfService) — mismo encabezado,
 * clave de acceso con código de barras, bloque de cliente y totales — pero
 * con tabla de detalle (líneas libres) en vez de "motivos", y una sección
 * adicional "Comprobantes reembolsados" con los terceros (sustento ATS 41).
 */
class FacturaReembolsoPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 10;
    private float $marginR  = 10;
    private float $contentW = 190;

    /**
     * @param string $outputDest Destino TCPDF: 'D' descarga, 'I' inline, 'S' string.
     */
    public function generar(array $cab, array $detalles, array $terceros, array $pagos, array $infoAdicional, array $empresa, string $outputDest = 'D')
    {
        $this->renderizar($cab, $detalles, $terceros, $pagos, $infoAdicional, $empresa);
        $num = $this->numeroFR($cab);
        if ($outputDest === 'S') {
            return $this->pdf->Output('FR_' . $num . '.pdf', 'S');
        }
        $this->pdf->Output('FR_' . $num . '.pdf', $outputDest);
    }

    /** Genera el PDF y lo devuelve como string (para guardado/correo). */
    public function generarBytes(array $cab, array $detalles, array $terceros, array $pagos, array $infoAdicional, array $empresa): string
    {
        $this->renderizar($cab, $detalles, $terceros, $pagos, $infoAdicional, $empresa);
        return $this->pdf->Output('', 'S');
    }

    private function renderizar(array $cab, array $detalles, array $terceros, array $pagos, array $infoAdicional, array $empresa): void
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Factura de Reembolso ' . $this->numeroFR($cab));
        $this->pdf->SetMargins($this->marginL, 5, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);

        $y = $this->dibujarEncabezado($empresa, $cab);
        $y = $this->dibujarDatosCliente($cab, $y + 2);
        $y = $this->dibujarDetalles($detalles, $y + 2);
        $y = $this->dibujarTerceros($terceros, $y + 2);
        if (!empty($pagos)) {
            $y = $this->dibujarPagos($pagos, $y + 2);
        }
        $this->dibujarPie($cab, $detalles, $terceros, $infoAdicional, $y + 2);
    }

    // ─── ENCABEZADO ──────────────────────────────────────────────────────────
    private function dibujarEncabezado(array $empresa, array $cabecera): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $izqW = 85;
        $derW = $this->contentW - $izqW - 2;
        $derX = $mL + $izqW + 2;

        $yTop = 8;
        $yLogo = $yTop;

        $boxHeight = 73.5;
        $logoAreaHeight = $boxHeight * 0.40;

        $logoPath = '';
        $rutasPosibles = [];
        if (!empty($empresa['logo_ruta'])) $rutasPosibles[] = $empresa['logo_ruta'];
        if (!empty($empresa['logo']))      $rutasPosibles[] = $empresa['logo'];

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
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetTextColor(160, 160, 160);
            $pdf->SetXY($mL + 2, $yLogo + ($logoAreaHeight / 2) - 5);
            $pdf->Cell($izqW - 4, 15, 'SIN LOGO', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $yTopIzqBox = $yLogo + $logoAreaHeight;
        $yIzq = $yTopIzqBox + 3;

        $nomComercial = trim($empresa['nombre_comercial'] ?? '');
        $nomRazon     = trim($empresa['nombre'] ?? '');
        if ($nomComercial) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->MultiCell($izqW - 4, 5, $nomComercial, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }
        if ($nomRazon && $nomRazon !== $nomComercial) {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->MultiCell($izqW - 4, 4.5, $nomRazon, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        $dirMat = trim($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? '');
        if ($dirMat) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(22, 4, 'Dirección Matriz:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 26, 4, $dirMat, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        $dirSuc = trim($empresa['direccion_establecimiento'] ?? $empresa['direccion_sucursal'] ?? '');
        if (empty($dirSuc)) $dirSuc = trim($cabecera['direccion_establecimiento'] ?? '');
        if (empty($dirSuc)) $dirSuc = trim($empresa['direccion'] ?? '');
        if ($dirSuc) {
            $yBefore = $pdf->GetY();
            $pdf->SetXY($mL + 2, $yBefore);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->MultiCell(20, 3.5, "Dirección\nSucursal:", 0, 'L', false, 1);
            $yAfterLabel = $pdf->GetY();

            $pdf->SetXY($mL + 22, $yBefore);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 24, 3.5, $dirSuc, 0, 'L', false, 1);
            $yAfterValue = $pdf->GetY();
            $yIzq = max($yAfterLabel, $yAfterValue);
        }

        $correoEmp = trim((string)($empresa['mail'] ?? $empresa['email'] ?? $empresa['correo'] ?? ''));
        if ($correoEmp !== '') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->MultiCell(20, 3.5, "Correo:", 0, 'L', false, 1);
            $yLbl = $pdf->GetY();
            $pdf->SetXY($mL + 22, $yIzq);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 24, 3.5, $correoEmp, 0, 'L', false, 1);
            $yIzq = max($yLbl, $pdf->GetY());
        }

        $telEmp = trim((string)($empresa['telefono'] ?? ''));
        if ($telEmp !== '') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->MultiCell(20, 3.5, "Teléfono:", 0, 'L', false, 1);
            $yLbl = $pdf->GetY();
            $pdf->SetXY($mL + 22, $yIzq);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 24, 3.5, $telEmp, 0, 'L', false, 1);
            $yIzq = max($yLbl, $pdf->GetY());
        }

        $resCont = trim($empresa['resolucion_contribuyente'] ?? '');
        if ($resCont) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(30, 4.5, 'Contribuyente Especial', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($izqW - 32, 4.5, $resCont, 0, 1, 'L');
            $yIzq = $pdf->GetY();
        }

        $oblStr  = strtoupper(trim((string)($empresa['obligado_contabilidad'] ?? 'NO')));
        $oblabel = ($oblStr === 'SI' || $oblStr === '1' || $oblStr === 'TRUE') ? 'SI' : 'NO';
        $pdf->SetXY($mL + 2, $yIzq);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(55, 4.5, 'OBLIGADO A LLEVAR CONTABILIDAD', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($izqW - 57, 4.5, $oblabel, 0, 1, 'L');
        $yIzq = $pdf->GetY() + 1;

        $agenteRet = trim((string)($empresa['agente_retencion'] ?? ''));
        if ($agenteRet !== '' && $agenteRet !== '0' && strtoupper($agenteRet) !== 'NO' && strtoupper($agenteRet) !== 'N/A') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(55, 4.5, 'Agente de Retención Resolución No.', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($izqW - 57, 4.5, $agenteRet, 0, 1, 'L');
            $yIzq = $pdf->GetY() + 1;
        }

        $rimpe = \App\Helpers\SriEmisorHelper::regimenRimpeLeyenda($empresa);
        if ($rimpe) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->MultiCell($izqW - 4, 4.5, $rimpe, 0, 'L', false, 1);
            $yIzq = $pdf->GetY() + 1;
        }

        $yIzq += 2;

        $yDer = $yTop;

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer + 2);
        $pdf->Cell(14, 5, 'R.U.C.:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($derW - 16, 5, $empresa['ruc'] ?? '', 0, 1, 'L');
        $yDer += 8;

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 7, 'FACTURA (REEMBOLSO)', 0, 1, 'L');
        $yDer += 7;

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell(7, 5, 'No.', 0, 0, 'L');
        $pdf->Cell($derW - 9, 5, $this->numeroFR($cabecera), 0, 1, 'L');
        $yDer += 6;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'NÚMERO DE AUTORIZACIÓN', 0, 1, 'L');
        $yDer += 5;

        $claveAcceso = trim($cabecera['clave_acceso'] ?? '');
        if ($claveAcceso) {
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->MultiCell($derW - 4, 4, $claveAcceso, 0, 'L', false, 1);
            $yDer = $pdf->GetY() + 1;
        }

        if (!empty($cabecera['fecha_autorizacion'])) {
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(32, 4.5, 'FECHA Y HORA DE', 0, 0, 'L');
            $pdf->Cell($derW - 34, 4.5, $cabecera['fecha_autorizacion'], 0, 1, 'L');
            $yDer += 4.5;
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell(32, 4.5, 'AUTORIZACIÓN:', 0, 1, 'L');
            $yDer += 4.5;
        }

        $tipoAmb  = (string)($cabecera['tipo_ambiente'] ?? $empresa['tipo_ambiente'] ?? '1');
        $ambiente = ($tipoAmb === '2') ? 'PRODUCCIÓN' : 'PRUEBAS';
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, 4.5, 'AMBIENTE:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($derW - 24, 4.5, $ambiente, 0, 1, 'L');
        $yDer += 4.5;

        $emisionCode = (string)($cabecera['tipo_emision'] ?? $empresa['tipo_emision'] ?? '1');
        $tipoEmision = ($emisionCode === '2') ? 'INDISPONIBILIDAD' : 'NORMAL';
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, 4.5, 'EMISIÓN:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($derW - 24, 4.5, $tipoEmision, 0, 1, 'L');
        $yDer += 5;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'CLAVE DE ACCESO', 0, 1, 'L');
        $yDer += 5;

        if ($claveAcceso) {
            $barcodeH = 12;
            $pdf->write1DBarcode(
                $claveAcceso, 'C128', $derX + 2, $yDer, $derW - 1, $barcodeH, 0.4,
                ['position' => 'R', 'text' => false, 'stretcharray' => '', 'stretch' => true], 'N'
            );
            $yDer += $barcodeH + 1;
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 3.5, $claveAcceso, 0, 1, 'C');
            $yDer += 4;
        }

        $yDer += 2;

        $yBottom = max($yIzq, $yDer);
        $pdf->RoundedRect($mL, $yTopIzqBox, $izqW, $yBottom - $yTopIzqBox, 3, '1111', 'D');
        $pdf->RoundedRect($derX, $yTop, $derW, $yBottom - $yTop, 3, '1111', 'D');

        return $yBottom;
    }

    // ─── DATOS DEL CLIENTE ────────────────────────────────────────────────────
    private function dibujarDatosCliente(array $cab, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $pdf->SetLineWidth(0.3);
        $lh = 5;

        $fecha = '';
        if (!empty($cab['fecha_emision'])) {
            $ts = strtotime($cab['fecha_emision']);
            $fecha = $ts ? date('d/m/Y', $ts) : $cab['fecha_emision'];
        }

        $yBox = $y;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1.5);
        $pdf->Cell(48, $lh, 'Razón Social / Nombres y Apellidos:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 50, $lh, $cab['cliente_nombre'] ?? '', 0, 1, 'L');
        $yBox += $lh + 1;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(20, $lh, 'Identificación:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(60, $lh, $cab['cliente_ruc'] ?? '', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, $lh, 'Fecha emisión:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 104, $lh, $fecha, 0, 1, 'L');
        $yBox += $lh + 1;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(15, $lh, 'Dirección:', 0, 0, 'L');
        $pdf->Cell($cW - 17, $lh, $cab['cliente_direccion'] ?? '', 0, 1, 'L');
        $yBox += $lh + 2;

        $pdf->Rect($mL, $y, $cW, $yBox - $y, 'D');
        return $yBox;
    }

    // ─── DETALLE (líneas libres) ───────────────────────────────────────────────
    private function dibujarDetalles(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $wCant  = 18;
        $wPrec  = 25;
        $wSub   = 28;
        $wDesc  = $cW - $wCant - $wPrec - $wSub;

        $pdf->SetFont('helvetica', 'B', 6.5);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetXY($mL, $y);
        $pdf->Cell($wDesc, 6, 'Descripción', 1, 0, 'C', true);
        $pdf->Cell($wCant, 6, 'Cant.', 1, 0, 'C', true);
        $pdf->Cell($wPrec, 6, 'P. Unitario', 1, 0, 'C', true);
        $pdf->Cell($wSub, 6, 'Subtotal', 1, 1, 'C', true);
        $y += 6;

        $pdf->SetFont('helvetica', '', 7);
        $altColor = false;
        foreach ($detalles as $d) {
            $bg = $altColor ? [250, 250, 250] : [255, 255, 255];
            $altColor = !$altColor;
            $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);

            $desc = (string)($d['descripcion'] ?? '');
            $nLineas = max(1, (int)ceil($pdf->GetStringWidth($desc) / ($wDesc - 2)));
            $ch = max(5, $nLineas * 4.5);

            $yRow = $pdf->GetY();
            $pdf->SetXY($mL, $yRow);
            $pdf->MultiCell($wDesc, $ch, $desc, 1, 'L', true, 0, '', '', true, 0, false, true, 0, 'M');
            $pdf->SetXY($mL + $wDesc, $yRow);
            $pdf->Cell($wCant, $ch, number_format((float)($d['cantidad'] ?? 0), 2), 1, 0, 'C', true);
            $pdf->Cell($wPrec, $ch, number_format((float)($d['precio_unitario'] ?? 0), 2), 1, 0, 'R', true);
            $pdf->Cell($wSub, $ch, number_format((float)($d['precio_total_sin_impuesto'] ?? 0), 2), 1, 0, 'R', true);
            $pdf->SetXY($mL, $yRow + $ch);
        }

        return $pdf->GetY();
    }

    /** Catálogo de codDocReembolso (Ficha Técnica SRI — sustento del reembolso, no es la Tabla 3 general). */
    private const TIPOS_DOC_REEMBOLSO = [
        '01' => 'FACTURA',
        '02' => 'NOTA VENTA',
        '03' => 'LIQUID. COMPRA',
        '04' => 'NOTA CRÉDITO',
        '05' => 'NOTA DÉBITO',
        '06' => 'GUÍA REMISIÓN',
        '07' => 'RETENCIÓN',
    ];

    // ─── COMPROBANTES REEMBOLSADOS (terceros — sustento ATS 41) ────────────────
    // Desglosa cada comprobante de tercero por tarifa de IVA (15%/5%/8%/0%),
    // igual al formato "Detalle de comprobante de reembolso" usado en RIDEs
    // de referencia de otros emisores. Tarifas fuera de este set (p. ej. tarifas
    // históricas 12%/14%) se agrupan en la columna 0% — no afecta el XML/cálculo
    // fiscal real, es solo la representación visual del PDF.
    private function dibujarTerceros(array $terceros, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        if ($y > 220) { $pdf->AddPage(); $y = 12; }

        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(220, 230, 245);
        $pdf->SetXY($mL, $y);
        $pdf->Cell($cW, 5.5, 'Detalle de comprobante de reembolso', 1, 1, 'L', true);
        $y += 5.5;

        $wFec   = 16;
        $wIdent = 24;
        $wTipo  = 22;
        $wNum   = 26;
        $wRate  = 15;
        $wImp   = 15;
        $wTotal = $cW - $wFec - $wIdent - $wTipo - $wNum - (4 * $wRate) - $wImp;

        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetXY($mL, $y);
        $pdf->Cell($wFec, 6, 'Fecha', 1, 0, 'C', true);
        $pdf->Cell($wIdent, 6, 'Identif.', 1, 0, 'C', true);
        $pdf->Cell($wTipo, 6, 'Tipo', 1, 0, 'C', true);
        $pdf->Cell($wNum, 6, 'Número', 1, 0, 'C', true);
        $pdf->Cell($wRate, 6, 'Base 15%', 1, 0, 'C', true);
        $pdf->Cell($wRate, 6, 'Base 5%', 1, 0, 'C', true);
        $pdf->Cell($wRate, 6, 'Base 8%', 1, 0, 'C', true);
        $pdf->Cell($wRate, 6, 'Base 0%', 1, 0, 'C', true);
        $pdf->Cell($wImp, 6, 'Impuestos', 1, 0, 'C', true);
        $pdf->Cell($wTotal, 6, 'Total', 1, 1, 'C', true);
        $y += 6;

        $pdf->SetFont('helvetica', '', 6.5);
        $altColor = false;
        foreach ($terceros as $t) {
            $bg = $altColor ? [250, 250, 250] : [255, 255, 255];
            $altColor = !$altColor;
            $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);

            $doc  = ($t['estab_doc_reembolso'] ?? '') . '-' . ($t['pto_emi_doc_reembolso'] ?? '') . '-' . ($t['secuencial_doc_reembolso'] ?? '');
            $fecha = '';
            if (!empty($t['fecha_emision_doc_reembolso'])) {
                $ts = strtotime((string)$t['fecha_emision_doc_reembolso']);
                $fecha = $ts ? date('d/m/Y', $ts) : (string)$t['fecha_emision_doc_reembolso'];
            }
            $tipoCod = (string)($t['cod_doc_reembolso'] ?? '');
            $tipo    = self::TIPOS_DOC_REEMBOLSO[$tipoCod] ?? $tipoCod;

            $base15 = 0.0; $base5 = 0.0; $base8 = 0.0; $base0 = 0.0; $impuestos = 0.0;
            foreach ($t['impuestos'] ?? [] as $imp) {
                $tarifa = (int) round((float)($imp['tarifa'] ?? 0));
                $base   = (float)($imp['base_imponible'] ?? 0);
                $impuestos += (float)($imp['valor'] ?? 0);
                if ($tarifa === 15) $base15 += $base;
                elseif ($tarifa === 5) $base5 += $base;
                elseif ($tarifa === 8) $base8 += $base;
                else $base0 += $base;
            }
            $total = $base15 + $base5 + $base8 + $base0 + $impuestos;

            $pdf->SetXY($mL, $y);
            $pdf->Cell($wFec, 5, $fecha, 1, 0, 'C', true);
            $pdf->Cell($wIdent, 5, (string)($t['identificacion_proveedor_reembolso'] ?? ''), 1, 0, 'C', true);
            $pdf->Cell($wTipo, 5, $tipo, 1, 0, 'C', true);
            $pdf->Cell($wNum, 5, $doc, 1, 0, 'C', true);
            $pdf->Cell($wRate, 5, number_format($base15, 2), 1, 0, 'R', true);
            $pdf->Cell($wRate, 5, number_format($base5, 2), 1, 0, 'R', true);
            $pdf->Cell($wRate, 5, number_format($base8, 2), 1, 0, 'R', true);
            $pdf->Cell($wRate, 5, number_format($base0, 2), 1, 0, 'R', true);
            $pdf->Cell($wImp, 5, number_format($impuestos, 2), 1, 0, 'R', true);
            $pdf->Cell($wTotal, 5, number_format($total, 2), 1, 1, 'R', true);
            $y += 5;
        }

        return $y;
    }

    // ─── PAGOS ──────────────────────────────────────────────────────────────
    private function dibujarPagos(array $pagos, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $cols = [
            ['titulo' => 'Forma de Pago', 'w' => $cW - 90],
            ['titulo' => 'Total',         'w' => 30],
            ['titulo' => 'Plazo',         'w' => 30],
            ['titulo' => 'Unidad Tiempo', 'w' => 30],
        ];

        $pdf->SetFont('helvetica', 'B', 6.5);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetXY($mL, $y);
        foreach ($cols as $c) {
            $pdf->Cell($c['w'], 6, $c['titulo'], 1, 0, 'C', true);
        }
        $pdf->Ln();
        $y += 6;

        $pdf->SetFont('helvetica', '', 7);
        $altColor = false;
        foreach ($pagos as $p) {
            $bg = $altColor ? [250, 250, 250] : [255, 255, 255];
            $altColor = !$altColor;
            $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);

            $pdf->SetXY($mL, $y);
            $pdf->Cell($cols[0]['w'], 5, (string)($p['nombre_forma_pago'] ?? $p['forma_pago'] ?? ''), 1, 0, 'L', true);
            $pdf->Cell($cols[1]['w'], 5, number_format((float)($p['total'] ?? 0), 2), 1, 0, 'R', true);
            $pdf->Cell($cols[2]['w'], 5, (string)($p['plazo'] ?? ''), 1, 0, 'C', true);
            $pdf->Cell($cols[3]['w'], 5, (string)($p['unidad_tiempo'] ?? ''), 1, 1, 'C', true);
            $y += 5;
        }

        return $pdf->GetY();
    }

    // ─── PIE ─────────────────────────────────────────────────────────────────
    private function dibujarPie(array $cab, array $detalles, array $terceros, array $infoAdicional, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $subtotMap = []; $ivaMap = []; $tarifaMap = [];
        foreach ($detalles as $d) {
            foreach ($d['impuestos'] ?? [] as $imp) {
                if ((string)($imp['codigo_impuesto'] ?? '') !== '2') continue;
                $codPct = (string)($imp['codigo_porcentaje'] ?? '0');
                $subtotMap[$codPct] = ($subtotMap[$codPct] ?? 0.0) + (float)($imp['base_imponible'] ?? 0);
                $ivaMap[$codPct]    = ($ivaMap[$codPct]    ?? 0.0) + (float)($imp['valor'] ?? 0);
                $tarifaMap[$codPct] = (float)($imp['tarifa'] ?? 0);
            }
        }
        ksort($subtotMap);
        ksort($ivaMap);

        $totalIva = array_sum($ivaMap);
        $subtotalSinImp = isset($cab['total_sin_impuestos']) ? (float)$cab['total_sin_impuestos'] : array_sum($subtotMap);
        $total = isset($cab['importe_total']) ? (float)$cab['importe_total'] : $subtotalSinImp + $totalIva;

        $totalReembBase = 0.0; $totalReembIva = 0.0;
        foreach ($terceros as $t) {
            $totalReembBase += (float)($t['base_imponible_total'] ?? 0);
            $totalReembIva  += (float)($t['impuesto_total'] ?? 0);
        }

        if ($y > 220) { $pdf->AddPage(); $y = 12; }

        $totW = 72;
        $izqW = $cW - $totW - 2;
        $totX = $mL + $izqW + 2;
        $lh   = 5;
        $lblW = 54;
        $valW = $totW - $lblW;

        $yTot = $y;
        $pdf->SetLineWidth(0.3);

        foreach ($subtotMap as $codPct => $base) {
            $tarPct   = $tarifaMap[$codPct] ?? 0.0;
            $tarLabel = $tarPct == (int)$tarPct ? (string)(int)$tarPct : number_format($tarPct, 2);
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, "SUBTOTAL {$tarLabel}%", $base);
            $yTot += $lh;
        }
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL SIN IMPUESTOS', $subtotalSinImp);
        $yTot += $lh;

        foreach ($ivaMap as $codPct => $ivaVal) {
            $tarPct   = $tarifaMap[$codPct] ?? 0.0;
            $tarLabel = $tarPct == (int)$tarPct ? (string)(int)$tarPct : number_format($tarPct, 2);
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, "IVA {$tarLabel}%", $ivaVal);
            $yTot += $lh;
        }

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(210, 210, 210);
        $pdf->SetXY($totX, $yTot);
        $pdf->Cell($lblW, $lh, 'VALOR TOTAL', 1, 0, 'L', true);
        $pdf->Cell($valW, $lh, number_format($total, 2), 1, 1, 'R', true);
        $yTot += $lh + 2;

        // Informativo: total reembolsado a terceros (no forma parte de totalConImpuestos propio).
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'BASE REEMBOLSADA', $totalReembBase);
        $yTot += $lh;
        $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'IVA REEMBOLSADO', $totalReembIva);

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

    private function numeroFR(array $cab): string
    {
        $est = str_pad((string)($cab['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $pto = str_pad((string)($cab['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT);
        $sec = str_pad((string)($cab['secuencial']      ?? '1'), 9, '0', STR_PAD_LEFT);
        return "{$est}-{$pto}-{$sec}";
    }
}
