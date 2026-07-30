<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * RIDE de Guía de Remisión (codDoc 06).
 *
 * Replica el mismo modelo visual del RIDE de Factura de Venta
 * (App\Services\modulos\FacturaVentaPdfService) adaptado a la información propia
 * de una guía: transportista, traslado, destinatario y documento de sustento.
 * La guía no tiene totales, por eso el pie solo lleva información adicional,
 * observaciones y la leyenda configurada por la empresa.
 */
class GuiaRemisionPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 10;
    private float $marginR  = 10;
    private float $contentW = 190;

    /** Decimales de cantidad configurados por la empresa. */
    private int $decCantidad = 2;

    /** Códigos de documento de sustento del SRI (tabla 4). */
    private const DOCS_SUSTENTO = [
        '01' => 'FACTURA',
        '03' => 'LIQUIDACIÓN DE COMPRA',
        '04' => 'NOTA DE CRÉDITO',
        '05' => 'NOTA DE DÉBITO',
        '06' => 'GUÍA DE REMISIÓN',
        '07' => 'COMPROBANTE DE RETENCIÓN',
    ];

    /**
     * @param string $outputDest Destino TCPDF: 'D' descarga, 'I' inline, 'S' string.
     */
    public function generar(
        array $cabecera,
        array $detalles,
        array $infoAdicional,
        array $empresa,
        string $outputDest = 'D'
    ) {
        $this->renderizar($cabecera, $detalles, $infoAdicional, $empresa);
        $filename = 'guia_remision_' . $this->numeroGuia($cabecera) . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($filename, 'S');
        }
        $this->pdf->Output($filename, $outputDest);
    }

    /** Devuelve el PDF de la guía como string (para adjuntar en correos). */
    public function generarBytes(array $cabecera, array $detalles, array $infoAdicional, array $empresa): string
    {
        $this->renderizar($cabecera, $detalles, $infoAdicional, $empresa);
        return $this->pdf->Output('', 'S');
    }

    private function renderizar(array $cabecera, array $detalles, array $infoAdicional, array $empresa): void
    {
        // RUC del proveedor del sistema (Res. NAC-DGERCGC26-00000027): ya viene
        // guardado en $infoAdicional desde GuiaRemisionService::crear() para los
        // documentos nuevos; no se inyecta aquí para no aplicarlo a los ya emitidos.

        $this->decCantidad = max(0, min(6, (int)($empresa['decimales_cantidad'] ?? 2)));
        $cabecera          = $this->completarDatosSri($cabecera);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Guía de Remisión ' . $this->numeroGuia($cabecera));
        $this->pdf->SetMargins($this->marginL, 5, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);

        $y = $this->dibujarEncabezado($empresa, $cabecera);
        $y = $this->dibujarTransportista($cabecera, $y + 2);
        $y = $this->dibujarDestinatario($cabecera, $y + 2);
        $y = $this->dibujarDetalle($detalles, $y + 2);
        $this->dibujarPie($cabecera, $infoAdicional, $empresa, $y + 2);
    }

    // ─── ENCABEZADO ──────────────────────────────────────────────────────────
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

        // Régimen RIMPE (solo emprendedor / negocio popular; el general no se muestra)
        $rimpe = \App\Helpers\SriEmisorHelper::regimenRimpeLeyenda($empresa);
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

        // GUÍA DE REMISIÓN (título)
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 7, 'GUÍA DE REMISIÓN', 0, 1, 'L');
        $yDer += 7;

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell(7, 5, 'No.', 0, 0, 'L');
        $pdf->Cell($derW - 9, 5, $this->numeroGuia($cabecera), 0, 1, 'L');
        $yDer += 6;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'NÚMERO DE AUTORIZACIÓN', 0, 1, 'L');
        $yDer += 5;

        // El número de autorización del SRI es la propia clave de acceso.
        $claveAcceso = trim((string)($cabecera['clave_acceso'] ?? ''));
        $numAut      = trim((string)($cabecera['numero_autorizacion'] ?? '')) ?: $claveAcceso;
        if ($numAut) {
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->MultiCell($derW - 4, 4, $numAut, 0, 'L', false, 1);
            $yDer = $pdf->GetY() + 1;
        }

        if (!empty($cabecera['fecha_autorizacion'])) {
            $ts        = strtotime((string)$cabecera['fecha_autorizacion']);
            $fechaAut  = $ts ? date('d/m/Y H:i:s', $ts) : (string)$cabecera['fecha_autorizacion'];
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(32, 4.5, 'FECHA Y HORA DE', 0, 0, 'L');
            $pdf->Cell($derW - 34, 4.5, $fechaAut, 0, 1, 'L');
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

    // ─── TRANSPORTISTA Y TRASLADO ────────────────────────────────────────────
    private function dibujarTransportista(array $cab, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;
        $lh  = 5;

        $pdf->SetLineWidth(0.3);

        $fechaEmi = $this->fechaCorta($cab['fecha_emision'] ?? null);
        $fechaIni = $this->fechaCorta($cab['fecha_inicio_transporte'] ?? null);
        $fechaFin = $this->fechaCorta($cab['fecha_fin_transporte'] ?? null);

        $yBox = $y;

        // Fila 1: Razón Social / Nombres y Apellidos del transportista
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1.5);
        $pdf->Cell(48, $lh, 'Razón Social / Nombres Transportista:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 50, $lh, $cab['transportista_nombre'] ?? '', 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 2: Identificación | Placa | Fecha emisión
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(20, $lh, 'Identificación:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(40, $lh, $cab['transportista_ruc'] ?? '', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(25, $lh, 'Placa / Matrícula:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(30, $lh, mb_strtoupper((string)($cab['placa'] ?? '')), 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, $lh, 'Fecha emisión:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 141, $lh, $fechaEmi, 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 3: Fecha inicio | Fecha fin del transporte
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(35, $lh, 'Fecha inicio transporte:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(25, $lh, $fechaIni, 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(32, $lh, 'Fecha fin transporte:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 96, $lh, $fechaFin, 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 4: Punto de partida
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(25, $lh, 'Punto de partida:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->MultiCell($cW - 29, $lh, $cab['direccion_partida'] ?? '', 0, 'L', false, 1);
        $yBox = max($pdf->GetY(), $yBox + $lh) + 1;

        $pdf->Rect($mL, $y, $cW, $yBox - $y, 'D');
        return $yBox;
    }

    // ─── DESTINATARIO ────────────────────────────────────────────────────────
    private function dibujarDestinatario(array $cab, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;
        $lh  = 5;

        $yBox = $y;

        // Fila 1: Razón Social / Nombres y Apellidos del destinatario
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1.5);
        $pdf->Cell(48, $lh, 'Razón Social / Nombres Destinatario:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 50, $lh, $cab['cliente_nombre'] ?? '', 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 2: Identificación | Cód. establecimiento destino
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(20, $lh, 'Identificación:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell(40, $lh, $cab['cliente_ruc'] ?? '', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(40, $lh, 'Cód. establecimiento destino:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($cW - 104, $lh, $cab['cod_establecimiento_destino'] ?? '', 0, 1, 'L');
        $yBox += $lh + 1;

        // Fila 3: Punto de llegada (dirección de destino; si no hay, la del cliente)
        $dirDestino = trim((string)($cab['direccion_destino'] ?? ''));
        if ($dirDestino === '') $dirDestino = trim((string)($cab['cliente_direccion'] ?? ''));
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox + 1);
        $pdf->Cell(25, $lh, 'Punto de llegada:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->MultiCell($cW - 29, $lh, $dirDestino, 0, 'L', false, 1);
        $yBox = max($pdf->GetY(), $yBox + $lh) + 1;

        // Fila 4: Motivo del traslado
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yBox);
        $pdf->Cell(28, $lh, 'Motivo del traslado:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->MultiCell($cW - 32, $lh, $cab['motivo_traslado'] ?? '', 0, 'L', false, 1);
        $yBox = max($pdf->GetY(), $yBox + $lh);

        // Fila 5 (opcional): Ruta | Documento aduanero único
        $ruta      = trim((string)($cab['ruta'] ?? ''));
        $docAduana = trim((string)($cab['doc_aduanero_unico'] ?? ''));
        if ($ruta !== '' || $docAduana !== '') {
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetXY($mL + 2, $yBox);
            $pdf->Cell(12, $lh, 'Ruta:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell(68, $lh, $ruta, 0, 0, 'L');

            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(38, $lh, 'Documento aduanero único:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($cW - 122, $lh, $docAduana, 0, 1, 'L');
            $yBox += $lh;
        }

        // Fila 6 (opcional): documento de sustento
        $numSust = trim((string)($cab['num_doc_sustento'] ?? ''));
        $codSust = trim((string)($cab['cod_doc_sustento'] ?? ''));
        if ($numSust !== '' || $codSust !== '') {
            $tipoSust  = self::DOCS_SUSTENTO[$codSust] ?? ($codSust !== '' ? 'CÓDIGO ' . $codSust : '');
            $autSust   = trim((string)($cab['num_autorizacion_doc_sustento'] ?? ''));
            $fechaSust = $this->fechaCorta($cab['fecha_emision_doc_sustento'] ?? null);

            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetXY($mL + 2, $yBox);
            $pdf->Cell(30, $lh, 'Doc. de sustento:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell(35, $lh, $tipoSust, 0, 0, 'L');

            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(10, $lh, 'No.:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell(35, $lh, $numSust, 0, 0, 'L');

            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(22, $lh, 'Fecha emisión:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($cW - 136, $lh, $fechaSust, 0, 1, 'L');
            $yBox += $lh;

            if ($autSust !== '') {
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetXY($mL + 2, $yBox);
                $pdf->Cell(45, $lh, 'Autorización doc. de sustento:', 0, 0, 'L');
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->Cell($cW - 49, $lh, $autSust, 0, 1, 'L');
                $yBox += $lh;
            }
        }

        $yBox += 1;
        $pdf->Rect($mL, $y, $cW, $yBox - $y, 'D');
        return $yBox;
    }

    // ─── DETALLE ─────────────────────────────────────────────────────────────
    // Columnas RIDE de guía: Cod.Principal | Cod.Auxiliar | Cantidad | Descripción
    private function dibujarDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $cols = [
            ['key' => 'codp', 'titulo' => "Cod.\nPrincipal", 'w' => 30, 'align' => 'L'],
            ['key' => 'coda', 'titulo' => "Cod.\nAuxiliar",  'w' => 30, 'align' => 'L'],
            ['key' => 'cant', 'titulo' => "Cantidad",        'w' => 25, 'align' => 'R'],
            ['key' => 'desc', 'titulo' => "Descripción",     'w' => 105, 'align' => 'L'],
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

            $vals = [
                'codp' => $d['codigo_principal'] ?? '',
                'coda' => $d['codigo_auxiliar']  ?? '',
                'cant' => number_format((float)($d['cantidad'] ?? 0), $this->decCantidad),
                'desc' => $d['descripcion'] ?? '',
            ];

            $nDesc = max(1, (int)ceil($pdf->GetStringWidth($vals['desc']) / ($cols[3]['w'] - 2)));
            $ch    = max(5, $nDesc * 4.5);

            // Salto de página manual: la fila no debe partirse a la mitad.
            if ($pdf->GetY() + $ch > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                $pdf->SetXY($mL, 12);
            }

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
    // Información adicional + Observaciones + leyenda configurada por la empresa.
    private function dibujarPie(array $cab, array $infoAdicional, array $empresa, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;
        $lh  = 5;

        if ($y > 250) { $pdf->AddPage(); $y = 12; }

        $izqW = $cW;

        if (!empty($infoAdicional)) {
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $y);
            $pdf->Cell($izqW, $lh, 'Información Adicional', 1, 1, 'C', true);
            $y += $lh;

            $etiqW = 45;
            $valIW = $izqW - $etiqW;
            $pdf->SetFillColor(255, 255, 255);
            foreach ($infoAdicional as $info) {
                $pdf->SetXY($mL, $y);
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->Cell($etiqW, $lh, $info['nombre'] ?? '', 1, 0, 'L');
                $pdf->SetFont('helvetica', '', 7);
                $pdf->MultiCell($valIW, $lh, $info['valor'] ?? '', 1, 'L', false, 1);
                $y = $pdf->GetY();
            }
        }

        if (!empty($cab['observaciones'])) {
            $y += 1;
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $y);
            $pdf->Cell($izqW, $lh, 'Observaciones', 1, 1, 'C', true);
            $y += $lh;
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($mL, $y);
            $pdf->MultiCell($izqW, 4.5, $cab['observaciones'], 1, 'L', false, 1);
            $y = $pdf->GetY();
        }

        // Mensaje personalizado (leyenda PDF de la empresa)
        $leyendaTitulo  = $empresa['leyenda_pdf_titulo']  ?? '';
        $leyendaMensaje = $empresa['leyenda_pdf_mensaje'] ?? '';
        if (!empty($leyendaTitulo) || !empty($leyendaMensaje)) {
            $y += 4;
            if ($y + 30 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                $y = $pdf->GetY() + 4;
            }
            if (!empty($leyendaTitulo)) {
                $pdf->SetFont('helvetica', 'B', 7.5);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->SetXY($mL, $y);
                $pdf->Cell($cW, $lh, mb_strtoupper($leyendaTitulo, 'UTF-8'), 1, 1, 'C', true);
                $y += $lh;
            }
            if (!empty($leyendaMensaje)) {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetXY($mL, $y);
                $pdf->MultiCell($cW, 4.5, $leyendaMensaje, 1, 'L', false, 1);
            }
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    /**
     * Completa clave de acceso, número y fecha de autorización desde el sobre
     * del SRI guardado en `detalle_xml` cuando las columnas están vacías (guías
     * migradas o autorizadas antes de que se persistieran esos campos). Lo que
     * ya viene en la cabecera manda: aquí solo se rellenan los huecos.
     */
    private function completarDatosSri(array $cab): array
    {
        $xml = trim((string)($cab['detalle_xml'] ?? ''));
        if ($xml === '') return $cab;

        $leer = static function (string $tag) use ($xml): string {
            $patron = '#<' . $tag . '>\s*(?:<!\[CDATA\[)?\s*([^<\]]+?)\s*(?:\]\]>)?\s*</' . $tag . '>#i';
            return preg_match($patron, $xml, $m) ? trim($m[1]) : '';
        };

        if (trim((string)($cab['numero_autorizacion'] ?? '')) === '') {
            $v = $leer('numeroAutorizacion');
            if ($v !== '') $cab['numero_autorizacion'] = $v;
        }
        if (trim((string)($cab['fecha_autorizacion'] ?? '')) === '') {
            $v = $leer('fechaAutorizacion');
            if ($v !== '') $cab['fecha_autorizacion'] = $v;
        }
        if (trim((string)($cab['clave_acceso'] ?? '')) === '') {
            $v = $leer('claveAcceso');
            if ($v !== '') $cab['clave_acceso'] = $v;
        }

        return $cab;
    }

    private function fechaCorta($valor): string
    {
        if (empty($valor)) return '';
        $ts = strtotime((string)$valor);
        return $ts ? date('d/m/Y', $ts) : (string)$valor;
    }

    private function numeroGuia(array $cab): string
    {
        $est = str_pad((string)($cab['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $pto = str_pad((string)($cab['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT);
        $sec = str_pad((string)($cab['secuencial']      ?? '1'), 9, '0', STR_PAD_LEFT);
        return "{$est}-{$pto}-{$sec}";
    }
}
