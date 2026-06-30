<?php
declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * RIDE de Nota de Crédito.
 *
 * Replica el mismo modelo visual del RIDE de Factura de Venta
 * (App\Services\modulos\FacturaVentaPdfService) adaptado a la información propia
 * de una nota de crédito: documento que se modifica, motivo y totales del SRI.
 */
class NotaCreditoPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 10;
    private float $marginR  = 10;
    private float $contentW = 190;

    /**
     * @param string $outputDest Destino TCPDF: 'D' descarga, 'I' inline, 'S' string.
     */
    public function generar(array $nc, array $detalles, array $empresa, array $infoAdicional = [], string $outputDest = 'D')
    {
        $this->renderizar($nc, $detalles, $infoAdicional, $empresa);
        $num = $this->numeroNC($nc);
        if ($outputDest === 'S') {
            return $this->pdf->Output('NC_' . $num . '.pdf', 'S');
        }
        $this->pdf->Output('NC_' . $num . '.pdf', $outputDest);
    }

    /** Genera el PDF y lo devuelve como string (para guardado en disco). */
    public function generarBytes(array $nc, array $detalles, array $empresa, array $infoAdicional = []): string
    {
        $this->renderizar($nc, $detalles, $infoAdicional, $empresa);
        return $this->pdf->Output('', 'S');
    }

    private function renderizar(array $nc, array $detalles, array $infoAdicional, array $empresa): void
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Nota de Crédito ' . $this->numeroNC($nc));
        $this->pdf->SetMargins($this->marginL, 5, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);

        $y = $this->dibujarEncabezado($empresa, $nc);
        $y = $this->dibujarDatosCliente($nc, $y + 2);
        $y = $this->dibujarDocModificado($nc, $y + 2);
        $y = $this->dibujarDetalle($detalles, $y + 2);
        $this->dibujarPie($nc, $detalles, $infoAdicional, $empresa, $y + 2);
    }

    // ─── ENCABEZADO ──────────────────────────────────────────────────────────
    private function dibujarEncabezado(array $empresa, array $cabecera): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $izqW = 85;
        $derW = $this->contentW - $izqW - 2; // 103mm
        $derX = $mL + $izqW + 2;

        $yTop = 8;
        $yLogo = $yTop;

        $boxHeight = 73.5;
        $logoAreaHeight = $boxHeight * 0.40;

        // Logo (misma resolución de rutas que la factura)
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

        // ── Caja izquierda (datos del emisor) ────────────────────────────────
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

        $rimpe = trim($empresa['regimen_rimpe'] ?? '');
        if ($rimpe) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->MultiCell($izqW - 4, 4.5, $rimpe, 0, 'L', false, 1);
            $yIzq = $pdf->GetY() + 1;
        }

        $yIzq += 2;

        // ── Caja derecha (datos del comprobante) ─────────────────────────────
        $yDer = $yTop;

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer + 2);
        $pdf->Cell(14, 5, 'R.U.C.:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($derW - 16, 5, $empresa['ruc'] ?? '', 0, 1, 'L');
        $yDer += 8;

        // NOTA DE CRÉDITO (título)
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 7, 'NOTA DE CRÉDITO', 0, 1, 'L');
        $yDer += 7;

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell(7, 5, 'No.', 0, 0, 'L');
        $pdf->Cell($derW - 9, 5, $this->numeroNC($cabecera), 0, 1, 'L');
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

        // Fila 1: Razón Social / Nombres y Apellidos
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1.5);
        $pdf->Cell(48, $lh, 'Razón Social / Nombres y Apellidos:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 50, $lh, $cab['cliente_nombre'] ?? '', 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 2: Identificación | Fecha emisión
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

        // Fila 3: Dirección
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(15, $lh, 'Dirección:', 0, 0, 'L');
        $pdf->Cell($cW - 17, $lh, $cab['cliente_direccion'] ?? '', 0, 1, 'L');
        $yBox += $lh + 2;

        $pdf->Rect($mL, $y, $cW, $yBox - $y, 'D');
        return $yBox;
    }

    // ─── DOCUMENTO QUE SE MODIFICA ────────────────────────────────────────────
    // Bloque obligatorio del RIDE de NC: comprobante modificado y motivo.
    private function dibujarDocModificado(array $cab, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;
        $lh  = 5;

        $fechaSust = '';
        if (!empty($cab['fecha_emision_docs_sustento'])) {
            $ts = strtotime($cab['fecha_emision_docs_sustento']);
            $fechaSust = $ts ? date('d/m/Y', $ts) : $cab['fecha_emision_docs_sustento'];
        }

        $yBox = $y;

        // Fila 1: Comprobante que se modifica | Número | Fecha emisión
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1.5);
        $pdf->Cell(45, $lh, 'Comprobante que se modifica:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(20, $lh, 'FACTURA', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(14, $lh, 'No.:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(45, $lh, $cab['num_doc_modificado'] ?? '', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, $lh, 'Fecha emisión:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 148, $lh, $fechaSust, 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 2: Razón de la modificación (motivo)
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(45, $lh, 'Razón de la modificación:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->MultiCell($cW - 49, $lh, $cab['motivo'] ?? '', 0, 'L', false, 1);
        $yBox = max($pdf->GetY(), $yBox + $lh) + 1;

        $pdf->Rect($mL, $y, $cW, $yBox - $y, 'D');
        return $yBox;
    }

    // ─── DETALLE ─────────────────────────────────────────────────────────────
    // Columnas RIDE de NC: Cod.Principal | Cod.Auxiliar | Cantidad | Descripción |
    // Detalle Adicional | Precio Unitario | Descuento | Precio Total
    private function dibujarDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $cols = [
            ['key' => 'codp', 'titulo' => "Cod.\nPrincipal",    'w' => 22, 'align' => 'L'],
            ['key' => 'coda', 'titulo' => "Cod.\nAuxiliar",     'w' => 20, 'align' => 'L'],
            ['key' => 'cant', 'titulo' => "Cantidad",           'w' => 18, 'align' => 'R'],
            ['key' => 'desc', 'titulo' => "Descripción",        'w' => 44, 'align' => 'L'],
            ['key' => 'deta', 'titulo' => "Detalle\nAdicional", 'w' => 28, 'align' => 'L'],
            ['key' => 'pu',   'titulo' => "Precio\nUnitario",   'w' => 22, 'align' => 'R'],
            ['key' => 'dcto', 'titulo' => "Descuento",          'w' => 18, 'align' => 'R'],
            ['key' => 'ptot', 'titulo' => "Precio\nTotal",      'w' => 18, 'align' => 'R'],
        ];

        // Ajustar Descripción para que la suma sea exactamente contentW
        $sumaW = array_sum(array_column($cols, 'w'));
        if ($sumaW !== (int)$cW) {
            foreach ($cols as &$c) {
                if ($c['key'] === 'desc') { $c['w'] += ((int)$cW - $sumaW); break; }
            }
            unset($c);
        }

        // Encabezado (2 líneas)
        $pdf->SetFont('helvetica', 'B', 6.5);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetXY($mL, $y);
        foreach ($cols as $col) {
            $pdf->MultiCell($col['w'], 7.6, $col['titulo'], 1, 'C', true, 0, '', '', true, 0, false, true, 7.6, 'M');
        }
        $pdf->Ln();
        $y += 7.6;

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
                'codp' => $d['codigo_principal'] ?? '',
                'coda' => $d['codigo_auxiliar']  ?? '',
                'cant' => number_format((float)($d['cantidad'] ?? 0), 2),
                'desc' => $d['descripcion'] ?? '',
                'deta' => $d['info_adicional'] ?? ($d['detalle_adicional'] ?? ''),
                'pu'   => number_format($pu, 2),
                'dcto' => number_format($dcto, 2),
                'ptot' => number_format($ptot, 2),
            ];

            // Índices de columnas multilinea (desc=3, deta=4)
            $nDesc = max(1, (int)ceil($pdf->GetStringWidth($vals['desc']) / ($cols[3]['w'] - 2)));
            $nDeta = max(1, (int)ceil($pdf->GetStringWidth($vals['deta']) / ($cols[4]['w'] - 2)));
            $ch    = max(5, max($nDesc, $nDeta) * 4.5);

            $xCur = $mL;
            $yRow = $pdf->GetY();
            foreach ($cols as $col) {
                $val = $vals[$col['key']];
                $pdf->SetXY($xCur, $yRow);
                if ($col['key'] === 'desc' || $col['key'] === 'deta') {
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
    // Izquierda: Información Adicional + Observaciones
    // Derecha:   tabla de totales SRI (misma lógica que la factura)
    private function dibujarPie(array $cab, array $detalles, array $infoAdicional, array $empresa, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        // Agrupación de bases e IVA por codigo_porcentaje (igual que el RIDE de factura)
        $subtotMap = []; $ivaMap = []; $tarifaMap = [];
        $totalIce  = 0.0; $totalDcto = 0.0; $noObjIva = 0.0; $exentoIva = 0.0;

        foreach ($detalles as $d) {
            $totalDcto += (float)($d['descuento'] ?? 0);
            $tieneImp = false;
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
                } elseif ($cod === '3') { // ICE
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

        // Totales de cabecera (los que van al XML autorizado); respaldo desde detalles.
        $subtotalSinImp = isset($cab['total_sin_impuestos'])
            ? (float)$cab['total_sin_impuestos']
            : array_sum($subtotMap) + $noObjIva + $exentoIva;
        if (isset($cab['total_descuento'])) {
            $totalDcto = (float)$cab['total_descuento'];
        }
        $total = isset($cab['importe_total'])
            ? (float)$cab['importe_total']
            : $subtotalSinImp + $totalIva + $totalIce;

        if ($y > 230) { $pdf->AddPage(); $y = 12; }

        $totW = 72;
        $izqW = $cW - $totW - 2;
        $totX = $mL + $izqW + 2;
        $lh   = 5;
        $lblW = 54;
        $valW = $totW - $lblW;

        // ── Columna derecha: totales ─────────────────────────────────────────
        $yTot = $y;
        $pdf->SetLineWidth(0.3);

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

        // VALOR TOTAL (negrita, fondo)
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(210, 210, 210);
        $pdf->SetXY($totX, $yTot);
        $pdf->Cell($lblW, $lh, 'VALOR TOTAL', 1, 0, 'L', true);
        $pdf->Cell($valW, $lh, number_format($total, 2), 1, 1, 'R', true);
        $yTot += $lh;

        // ── Columna izquierda: Información Adicional + Observaciones ──────────
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

    private function numeroNC(array $nc): string
    {
        $est = str_pad((string)($nc['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $pto = str_pad((string)($nc['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT);
        $sec = str_pad((string)($nc['secuencial']      ?? '1'), 9, '0', STR_PAD_LEFT);
        return "{$est}-{$pto}-{$sec}";
    }
}
