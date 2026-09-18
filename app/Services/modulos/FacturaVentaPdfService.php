<?php
declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

class FacturaVentaPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 10;
    private float $marginR  = 10;
    private float $pageW    = 210;
    private float $contentW = 190;

    /** Decimales configurados por la empresa (cantidad y precio unitario). */
    private int $decCantidad = 2;
    private int $decPrecio   = 2;

    /**
     * Tipografía del cuerpo del RIDE. La fija la tabla de ítems (celdas a 9 pt,
     * encabezados en negrita 8.5) y la comparten los datos del cliente y la
     * información adicional, para que los tres bloques se vean iguales: si se
     * cambia aquí, cambian los tres.
     */
    private const FUENTE_CUERPO           = 9.0;
    private const FUENTE_ENCABEZADO_TABLA = 8.5;
    private const LINEA_CUERPO            = 4.2;  // mm por línea de texto
    private const ALTO_MIN_FILA           = 4.8;  // mm, fila de una sola línea

    public function generar(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa, string $outputDest = 'D')
    {
        $this->renderizar($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        $num = ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-' . ($cabecera['secuencial'] ?? '');
        if ($outputDest === 'S') {
            return $this->pdf->Output('Factura_' . $num . '.pdf', 'S');
        }
        $this->pdf->Output('Factura_' . $num . '.pdf', $outputDest);
    }

    /** Genera el PDF y lo devuelve como string (para guardado en disco). */
    public function generarBytes(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): string
    {
        $this->renderizar($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        return $this->pdf->Output('', 'S');
    }

    private function renderizar(array $cabecera, array $detalles, array $pagos, array $infoAdicional, array $empresa): void
    {
        // Normalizar a Unicode NFC: texto pegado desde macOS suele llegar en
        // forma descompuesta (letra + marca diacrítica suelta) y la fuente
        // core de TCPDF no tiene glifo para la marca suelta (sale como "?"
        // pegado a la letra, p. ej. "MONSEN?OR"). Ver TextoUnicodeHelper.
        $cabecera      = \App\Helpers\TextoUnicodeHelper::nfcArray($cabecera);
        $detalles      = \App\Helpers\TextoUnicodeHelper::nfcArray($detalles);
        $pagos         = \App\Helpers\TextoUnicodeHelper::nfcArray($pagos);
        $infoAdicional = \App\Helpers\TextoUnicodeHelper::nfcArray($infoAdicional);
        $empresa       = \App\Helpers\TextoUnicodeHelper::nfcArray($empresa);

        // RUC del proveedor del sistema (Res. NAC-DGERCGC26-00000027): ya viene
        // guardado en $infoAdicional desde FacturaVentaService::crear() para los
        // documentos nuevos; no se inyecta aquí para no aplicarlo a los ya emitidos.

        // Decimales configurados por la empresa (igual que en el sistema/UI),
        // acotados a 0..6 igual que el XML para que ambos impriman lo mismo.
        $this->decCantidad = max(0, min(6, (int)($empresa['decimales_cantidad'] ?? 2)));
        $this->decPrecio   = max(0, min(6, (int)($empresa['decimales_precio']   ?? 2)));

        // Agrupación y etiquetas de ítems configuradas por la empresa. Mismo
        // servicio que usa XmlFacturaVentaService: PDF y XML no pueden divergir.
        $detalles = (new FacturaItemsPresentacionService())->preparar($detalles, $empresa);

        // Vendedor y Cajero: se derivan de la CABECERA si no vienen guardados
        // como fila de información adicional (ver conCamposDeCabecera).
        $infoAdicional = $this->conCamposDeCabecera($infoAdicional, $cabecera, $empresa);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Factura ' . $this->numeroFactura($cabecera));
        $this->pdf->SetMargins($this->marginL, 5, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);

        $y = $this->dibujarEncabezado($empresa, $cabecera);
        $y = $this->dibujarDatosCliente($cabecera, $y + 2);
        $y = $this->dibujarDetalle($detalles, $y + 2);
        $this->dibujarPie($cabecera, $detalles, $pagos, $infoAdicional, $empresa, $y + 2);
    }

    // ─── ENCABEZADO ──────────────────────────────────────────────────────────
    private function dibujarEncabezado(array $empresa, array $cabecera): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        // Dimensiones de las dos cajas
        $izqW = 85;
        $derW = $this->contentW - $izqW - 2; // 103mm
        $derX = $mL + $izqW + 2;

        $yTop = 8;
        $yLogo = $yTop;
        
        $boxHeight = 73.5;
        // Asignamos 40% para el logo y 60% para la caja para evitar que el texto quede apretado
        $logoAreaHeight = $boxHeight * 0.40;

        // Logo
        $logoPath = '';
        $rutasPosibles = [];
        if (!empty($empresa['logo_ruta'])) {
            $rutasPosibles[] = $empresa['logo_ruta'];
        }
        if (!empty($empresa['logo'])) {
            $rutasPosibles[] = $empresa['logo'];
        }

        foreach ($rutasPosibles as $ruta) {
            $cleanRuta = ltrim($ruta, '/');
            // Eliminar prefijos que duplicarían la ruta del servidor
            if (strpos($cleanRuta, 'sistema/public/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('sistema/public/'));
            } elseif (strpos($cleanRuta, 'sistema/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('sistema/'));
            }
            if (strpos($cleanRuta, 'public/') === 0) {
                $cleanRuta = substr($cleanRuta, strlen('public/'));
            }

            // Intentar primero en public/ (ubicación real en producción)
            $candidatos = [
                \MVC_ROOT . '/public/' . $cleanRuta,
                \MVC_ROOT . '/' . $cleanRuta,
            ];
            foreach ($candidatos as $testPath) {
                if (file_exists($testPath)) {
                    $logoPath = $testPath;
                    break 2;
                }
            }
        }
        
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);

        if ($logoPath) {
            // Se usa el ancho casi total de la zona izquierda y fitbox 'CM' para centrado vertical y horizontal
            $pdf->Image($logoPath, $mL + 2, $yLogo + 2, $izqW - 4, $logoAreaHeight - 4, '', '', '', false, 300, '', false, false, 0, 'CM');
        } else {
            // Placeholder "SIN LOGO"
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetTextColor(160, 160, 160);
            $pdf->SetXY($mL + 2, $yLogo + ($logoAreaHeight/2) - 5);
            $pdf->Cell($izqW - 4, 15, 'SIN LOGO', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
        
        $yTopIzqBox = $yLogo + $logoAreaHeight;

        // ── Caja izquierda (contenido) ───────────────────────────────────────
        $yIzq = $yTopIzqBox + 3;

        // Nombre comercial
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

        // Dirección Matriz
        $dirMat = trim($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? '');
        if ($dirMat) {
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(22, 4, 'Dirección Matriz:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->MultiCell($izqW - 26, 4, $dirMat, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Dirección Sucursal
        $dirSuc = trim($empresa['direccion_establecimiento'] ?? $empresa['direccion_sucursal'] ?? '');
        if (empty($dirSuc)) {
            $dirSuc = trim($cabecera['direccion_establecimiento'] ?? '');
        }
        if (empty($dirSuc)) {
            $dirSuc = trim($empresa['direccion'] ?? '');
        }
        
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

        // Correo de la empresa (solo si existe), debajo de Dirección Sucursal
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

        // Teléfono de la empresa (solo si existe)
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

        // Contribuyente Especial
        $resCont = trim($empresa['resolucion_contribuyente'] ?? '');
        if ($resCont) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(30, 4.5, 'Contribuyente Especial', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->Cell($izqW - 32, 4.5, $resCont, 0, 1, 'L');
            $yIzq = $pdf->GetY();
        }

        // Obligado a llevar contabilidad
        $oblStr  = strtoupper(trim((string)($empresa['obligado_contabilidad'] ?? 'NO')));
        $oblabel = ($oblStr === 'SI' || $oblStr === '1' || $oblStr === 'TRUE') ? 'SI' : 'NO';
        $pdf->SetXY($mL + 2, $yIzq);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(55, 4.5, 'OBLIGADO A LLEVAR CONTABILIDAD', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($izqW - 57, 4.5, $oblabel, 0, 1, 'L');
        $yIzq = $pdf->GetY() + 1;

        // Agente de Retención
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

        // ── Caja derecha ──────────────────────────────────────────────────────
        $yDer = $yTop;

        // Fila: R.U.C. + valor
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer + 2);
        $pdf->Cell(14, 5, 'R.U.C.:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($derW - 16, 5, $empresa['ruc'] ?? '', 0, 1, 'L');
        $yDer += 8;

        // FACTURA (grande)
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 7, 'FACTURA', 0, 1, 'L');
        $yDer += 7;

        // Número de factura
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell(7, 5, 'No.', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW - 9, 5, $this->numeroFactura($cabecera), 0, 1, 'L');
        $yDer += 6;

        // NÚMERO DE AUTORIZACIÓN (etiqueta)
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'NÚMERO DE AUTORIZACIÓN', 0, 1, 'L');
        $yDer += 5;

        // Clave de acceso como texto
        $claveAcceso = trim($cabecera['clave_acceso'] ?? '');
        if ($claveAcceso) {
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->MultiCell($derW - 4, 4, $claveAcceso, 0, 'L', false, 1);
            $yDer = $pdf->GetY() + 1;
        }

        // Fecha y hora de autorización
        if (!empty($cabecera['fecha_autorizacion'])) {
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(32, 4.5, 'FECHA Y HORA DE', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell($derW - 34, 4.5, $cabecera['fecha_autorizacion'], 0, 1, 'L');
            $yDer += 4.5;
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(32, 4.5, 'AUTORIZACIÓN:', 0, 1, 'L');
            $yDer += 4.5;
        }

        // AMBIENTE
        $tipoAmb = (string)($cabecera['tipo_ambiente'] ?? $empresa['tipo_ambiente'] ?? '1');
        $ambiente = ($tipoAmb === '2') ? 'PRODUCCIÓN' : 'PRUEBAS';
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, 4.5, 'AMBIENTE:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($derW - 24, 4.5, $ambiente, 0, 1, 'L');
        $yDer += 4.5;

        // EMISIÓN
        $emisionCode = (string)($cabecera['tipo_emision'] ?? $empresa['tipo_emision'] ?? '1');
        $tipoEmision = ($emisionCode === '1') ? 'NORMAL' : 'NORMAL';
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(22, 4.5, 'EMISIÓN:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->Cell($derW - 24, 4.5, $tipoEmision, 0, 1, 'L');
        $yDer += 5;

        // CLAVE DE ACCESO (etiqueta)
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'CLAVE DE ACCESO', 0, 1, 'L');
        $yDer += 5;

        // Código de barras (barcode)
        if ($claveAcceso) {
            $barcodeH = 12;
            $pdf->write1DBarcode(
                $claveAcceso,
                'C128',
                $derX + 2,
                $yDer,
                $derW - 1,
                $barcodeH,
                0.4,
                ['position' => 'R', 'text' => false, 'stretcharray' => '', 'stretch' => true],
                'N'
            );
            $yDer += $barcodeH + 1;
            // Número debajo del código de barras
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 3.5, $claveAcceso, 0, 1, 'C');
            $yDer += 4;
        }

        $yDer += 2;

        // ── Dibujar Bordes (Alineados al fondo) ───────────────────────────────
        $yBottom = max($yIzq, $yDer);

        // Borde izquierdo (empieza en yTopIzqBox, debajo del logo)
        $pdf->RoundedRect($mL, $yTopIzqBox, $izqW, $yBottom - $yTopIzqBox, 3, '1111', 'D');
        // Borde derecho
        $pdf->RoundedRect($derX, $yTop, $derW, $yBottom - $yTop, 3, '1111', 'D');

        return $yBottom;
    }

    // ─── DATOS DEL CLIENTE ────────────────────────────────────────────────────
    // Caja con borde, 3 filas

    private function dibujarDatosCliente(array $cab, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        $pdf->SetLineWidth(0.3);

        // Misma tipografía que la tabla de ítems (ver FUENTE_CUERPO): etiqueta
        // normal, valor en negrita. Los anchos se MIDEN con el texto real: las
        // celdas fijas de antes estaban calculadas para 7.5 pt y a este tamaño la
        // etiqueta se montaba sobre su valor (Cell() no recorta el texto).
        $fs    = self::FUENTE_CUERPO;
        $lineH = self::LINEA_CUERPO;
        $pads  = $pdf->getCellPaddings();
        $padLR = (float)$pads['L'] + (float)$pads['R'];
        $gap   = 1.5;           // etiqueta → valor
        $sep   = 5.0;           // entre un par y el siguiente
        $x0    = $mL + 2;       // sangría interior de la caja
        $util  = $cW - 4;       // ancho útil dentro de la caja

        $anchoTexto = function (string $txt, string $estilo) use ($pdf, $fs, $padLR): float {
            $pdf->SetFont('helvetica', $estilo, $fs);
            return $pdf->GetStringWidth($txt) + $padLR;
        };

        $fecha = '';
        if (!empty($cab['fecha_emision'])) {
            $ts = strtotime($cab['fecha_emision']);
            $fecha = $ts ? date('d/m/Y', $ts) : $cab['fecha_emision'];
        }

        $rowY = $y + 1.5;

        // Fila 1: Razón Social / Nombres y Apellidos. Un nombre largo se envuelve
        // en vez de salirse de la caja.
        $lbl  = 'Razón Social / Nombres y Apellidos:';
        $wLbl = $anchoTexto($lbl, '') + $gap;
        $pdf->SetXY($x0, $rowY);
        $pdf->Cell($wLbl, $lineH, $lbl, 0, 0, 'L');
        $nombre = (string)($cab['cliente_nombre'] ?? '');
        $pdf->SetFont('helvetica', 'B', $fs);
        $wNom = $util - $wLbl;
        $nNom = max(1, $pdf->getNumLines($nombre, $wNom));
        $pdf->SetXY($x0 + $wLbl, $rowY);
        $pdf->MultiCell($wNom, $lineH, $nombre, 0, 'L', false, 1);
        $rowY += $nNom * $lineH + 1;

        // Fila 2: Identificación | Fecha | Placa/Matrícula | Guía, en flujo. Si un
        // par no cabe en lo que queda de la línea pasa entero a la siguiente: la
        // etiqueta nunca se separa de su valor.
        $pares = [
            ['Identificación:', (string)($cab['cliente_ruc'] ?? '')],
            ['Fecha emisión:',  $fecha],
        ];
        if (!empty($cab['placa'])) {
            $pares[] = ['Placa / Matrícula:', (string)$cab['placa']];
        }
        $pares[] = ['Guía remisión:', (string)($cab['guia_remision'] ?? '')];

        $x = $x0;
        foreach ($pares as [$lbl, $val]) {
            $wL = $anchoTexto($lbl, '') + $gap;
            // Mínimo: que una etiqueta sin valor no quede pegada a la siguiente.
            $wV = min(max($anchoTexto($val, 'B'), 12.0), $util - $wL);
            if ($x > $x0 && $x + $wL + $wV > $x0 + $util) {
                $x = $x0;
                $rowY += $lineH + 1;
            }
            $pdf->SetFont('helvetica', '', $fs);
            $pdf->SetXY($x, $rowY);
            $pdf->Cell($wL, $lineH, $lbl, 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', $fs);
            // stretch 1: solo condensa si un valor no cupiera ni en la línea entera.
            $pdf->Cell($wV, $lineH, $val, 0, 0, 'L', false, '', 1);
            $x += $wL + $wV + $sep;
        }
        $rowY += $lineH + 1;

        // Fila 3: Dirección | Teléfono | Correo. Cualquiera puede traer texto largo
        // (dirección extensa, varios correos separados por coma): cada valor se
        // envuelve y la fila crece según el más alto de los tres.
        $wLDir = $anchoTexto('Direccion:', '') + $gap;
        $wLTel = $anchoTexto('Telefono:', '') + $gap;
        $wLCor = $anchoTexto('Correo:', '') + $gap;
        $wVDir = 60.0;
        $wVTel = 26.0;
        $wVCor = $util - $wLDir - $wVDir - $wLTel - $wVTel - $wLCor;

        $bloques = [
            [$wLDir, 'Direccion:', $wVDir, (string)($cab['cliente_direccion'] ?? '')],
            [$wLTel, 'Telefono:',  $wVTel, (string)($cab['cliente_telefono']  ?? '')],
            [$wLCor, 'Correo:',    $wVCor, (string)($cab['cliente_email']     ?? '')],
        ];
        $pdf->SetFont('helvetica', '', $fs);
        $x    = $x0;
        $nMax = 1;
        foreach ($bloques as [$wL, $lbl, $wV, $val]) {
            $pdf->SetXY($x, $rowY);
            $pdf->Cell($wL, $lineH, $lbl, 0, 0, 'L');
            $nMax = max($nMax, $pdf->getNumLines($val, $wV));
            $pdf->SetXY($x + $wL, $rowY);
            $pdf->MultiCell($wV, $lineH, $val, 0, 'L', false, 1);
            $x += $wL + $wV;
        }
        $yBox = $rowY + $nMax * $lineH + 1;

        // Borde de la caja
        $pdf->Rect($mL, $y, $cW, $yBox - $y, 'D');

        return $yBox;
    }

    // ─── DETALLE ─────────────────────────────────────────────────────────────
    // Columnas RIDE: Cod.Principal | Cod.Auxiliar | Cantidad | Descripción |
    // Detalle Adicional | Precio Unitario | Descuento | Precio Total
    // "Subsidio" y "Precio sin Subsidio" no se imprimen: el sistema no factura
    // bienes subsidiados, así que eran dos columnas fijas en 0.00 robándole
    // ancho al código y a la descripción (si algún día hay subsidio, sigue
    // apareciendo en el bloque de totales del pie).
    // "Cód. Auxiliar" y "Detalle Adicional" se ocultan si ningún ítem los trae.

    private function dibujarDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        // Anchos base (suman contentW). Descripción absorbe cualquier sobrante.
        $cols = [
            ['key' => 'codp', 'titulo' => "Cod.\nPrincipal",    'w' => 20.0, 'align' => 'L'],
            ['key' => 'coda', 'titulo' => "Cod.\nAuxiliar",     'w' => 18.0, 'align' => 'L'],
            ['key' => 'cant', 'titulo' => "Cantidad",           'w' => 16.0, 'align' => 'R'],
            ['key' => 'desc', 'titulo' => "Descripción",        'w' => 50.0, 'align' => 'L'],
            ['key' => 'deta', 'titulo' => "Detalle\nAdicional", 'w' => 28.0, 'align' => 'L'],
            ['key' => 'pu',   'titulo' => "Precio\nUnitario",   'w' => 22.0, 'align' => 'R'],
            ['key' => 'dcto', 'titulo' => "Descuento",          'w' => 18.0, 'align' => 'R'],
            ['key' => 'ptot', 'titulo' => "Precio\nTotal",      'w' => 18.0, 'align' => 'R'],
        ];

        // Ocultar la columna "Cód. Auxiliar" si ningún ítem tiene código auxiliar.
        // Su ancho lo reabsorbe la Descripción en el ajuste de abajo.
        $hayCodAux = false;
        foreach ($detalles as $d) {
            if (trim((string)($d['codigo_auxiliar'] ?? '')) !== '') { $hayCodAux = true; break; }
        }
        if (!$hayCodAux) {
            $cols = array_values(array_filter($cols, fn($c) => $c['key'] !== 'coda'));
        }

        // Ocultar "Detalle Adicional" si ningún ítem trae información (mismo
        // criterio que "Cód. Auxiliar"): su ancho lo reabsorbe la Descripción.
        // Ojo: se mira el valor YA preparado por FacturaItemsPresentacionService,
        // que es quien puede inyectar lote/caducidad/NUP en info_adicional.
        $hayDetalle = false;
        foreach ($detalles as $d) {
            $txt = trim((string)($d['info_adicional'] ?? ($d['detalle_adicional'] ?? '')));
            if ($txt !== '') { $hayDetalle = true; break; }
        }
        if (!$hayDetalle) {
            $cols = array_values(array_filter($cols, fn($c) => $c['key'] !== 'deta'));
        }

        // Ancho de las columnas de código según su CONTENIDO real: TCPDF no
        // recorta el texto de Cell(), así que un código largo se desbordaba
        // encima de la columna siguiente en vez de ensanchar la suya. Se mide
        // con la misma fuente de las filas y se acota entre el ancho base y un
        // máximo, para no dejar sin sitio a la Descripción.
        $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
        $campoCod = ['codp' => 'codigo_principal', 'coda' => 'codigo_auxiliar'];
        $maxCod   = ['codp' => 42.0,               'coda' => 26.0];
        $minCod   = [];
        foreach ($cols as &$c) {
            if (!isset($campoCod[$c['key']])) { continue; }
            $minCod[$c['key']] = (float)$c['w'];
            $wTexto = 0.0;
            foreach ($detalles as $d) {
                $txt = trim((string)($d[$campoCod[$c['key']]] ?? ''));
                if ($txt !== '') { $wTexto = max($wTexto, $pdf->GetStringWidth($txt)); }
            }
            // +2mm = padding izquierdo/derecho de la celda.
            $c['w'] = round(max((float)$c['w'], min($maxCod[$c['key']], $wTexto + 2.0)), 1);
        }
        unset($c);

        // Piso de la Descripción: si los códigos se llevaron demasiado ancho, se
        // les devuelve el exceso (primero al auxiliar, que es el prescindible).
        $minDesc  = 28.0;
        $wDescRes = $cW - array_sum(array_map(
            fn($c) => $c['key'] === 'desc' ? 0.0 : (float)$c['w'],
            $cols
        ));
        if ($wDescRes < $minDesc) {
            $porRecortar = $minDesc - $wDescRes;
            foreach (['coda', 'codp'] as $k) {
                if ($porRecortar <= 0.01) { break; }
                foreach ($cols as &$c) {
                    if ($c['key'] !== $k) { continue; }
                    $quita = min($porRecortar, max(0.0, (float)$c['w'] - ($minCod[$k] ?? 0.0)));
                    $c['w'] -= $quita;
                    $porRecortar -= $quita;
                }
                unset($c);
            }
        }

        // Ajustar Descripción para que la suma sea exactamente contentW
        $sumaW = array_sum(array_column($cols, 'w'));
        if (abs($sumaW - $cW) > 0.01) {
            foreach ($cols as &$c) {
                if ($c['key'] === 'desc') {
                    $c['w'] += ($cW - $sumaW);
                    break;
                }
            }
            unset($c);
        }

        // Encabezado (2 líneas). Se encapsula porque hay que repetirlo al inicio
        // de cada página cuando el detalle no cabe en una sola.
        $hdrH = 9.8; // 2 líneas * 4.9
        $dibujarEncabezado = function (float $yEnc) use ($pdf, $cols, $mL, $hdrH): float {
            $pdf->SetFont('helvetica', 'B', self::FUENTE_ENCABEZADO_TABLA);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yEnc);
            foreach ($cols as $col) {
                // Alto total 7.6 y alineación vertical 'M' (Middle)
                $pdf->MultiCell($col['w'], $hdrH, $col['titulo'], 1, 'C', true, 0, '', '', true, 0, false, true, $hdrH, 'M');
            }
            $pdf->Ln();
            return $yEnc + $hdrH;
        };

        $y = $dibujarEncabezado($y);

        // Anchos reales de las columnas multilinea (por key, no por índice: la
        // posición de 'desc'/'deta' cambia si se oculta la columna "Cód. Auxiliar").
        $wDesc = 0.0;
        $wDeta = 0.0;
        foreach ($cols as $c) {
            if ($c['key'] === 'desc') $wDesc = (float)$c['w'];
            if ($c['key'] === 'deta') $wDeta = (float)$c['w'];
        }

        // Filas de detalle
        $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
        $altColor = false;

        // Alto útil de la página para el detalle. Se deja sitio para el bloque de
        // totales/pie, que se dibuja después a partir de la Y que devuelve este
        // método.
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();

        foreach ($detalles as $d) {
            $bg = $altColor ? [250, 250, 250] : [255, 255, 255];
            $altColor = !$altColor;
            $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);

            $pu      = (float)($d['precio_unitario'] ?? 0);
            $dcto    = (float)($d['descuento'] ?? 0);
            $ptot    = (float)($d['precio_total_sin_impuesto'] ?? 0);

            $vals = [
                'codp' => $d['codigo_principal'] ?? '',
                'coda' => $d['codigo_auxiliar']  ?? '',
                'cant' => number_format((float)($d['cantidad'] ?? 0), $this->decCantidad),
                'desc' => $d['descripcion'] ?? '',
                'deta' => $d['info_adicional'] ?? ($d['detalle_adicional'] ?? ''),
                'pu'   => number_format($pu, $this->decPrecio),
                'dcto' => number_format($dcto, 2),
                'ptot' => number_format($ptot, 2),
            ];

            // Calcular altura de fila según columnas multilinea
            $nDesc = $wDesc > 0 ? max(1, $pdf->getNumLines($vals['desc'], $wDesc)) : 1;
            $nDeta = $wDeta > 0 ? max(1, $pdf->getNumLines($vals['deta'], $wDeta)) : 1;
            $ch    = max(self::ALTO_MIN_FILA, max($nDesc, $nDeta) * self::LINEA_CUERPO);

            $xCur = $mL;
            $yRow = $pdf->GetY();

            // Salto de página CONTROLADO. Sin esto, al pasarse del alto útil cada
            // SetXY() con una Y fuera de página dispara el salto automático de
            // TCPDF, y como aquí se hace un SetXY por COLUMNA, una sola factura
            // larga acababa generando una página casi vacía por celda (31 líneas
            // llegaron a producir 93 páginas).
            if ($yRow + $ch > $limiteY) {
                $pdf->AddPage();
                // Tras AddPage, GetY() ya está en el margen superior de la página.
                $yRow = $dibujarEncabezado($pdf->GetY());
                $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
                $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);
            }

            foreach ($cols as $col) {
                $val = $vals[$col['key']];
                $pdf->SetXY($xCur, $yRow);
                if ($col['key'] === 'desc' || $col['key'] === 'deta') {
                    // Alineación horizontal izquierda + vertical centrada (valign 'M')
                    $pdf->MultiCell($col['w'], $ch, $val, 1, $col['align'], true, 0, '', '', true, 0, false, true, 0, 'M');
                } elseif ($col['key'] === 'codp' || $col['key'] === 'coda') {
                    // stretch = 1: si un código excepcionalmente largo no cabe ni
                    // con el ancho calculado arriba (llegó al tope), TCPDF lo
                    // condensa para que se vea COMPLETO dentro de su celda, en vez
                    // de desbordarse encima de la columna siguiente.
                    $pdf->Cell($col['w'], $ch, $val, 1, 0, $col['align'], true, '', 1);
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
    // Izquierda: Información Adicional + Observaciones + Forma de pago
    // Derecha:   tabla de totales SRI
    private function dibujarPie(array $cab, array $detalles, array $pagos, array $infoAdicional, array $empresa, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        // ── Calcular totales por concepto de IVA (codigo_porcentaje) ─────────
        // Se agrupan y suman las bases imponibles y el IVA por codigo_porcentaje
        // del SRI, con la MISMA lógica que el XML autorizado (XmlFacturaVentaService)
        // para que el RIDE muestre exactamente las mismas cifras del comprobante.
        $subtotMap  = []; // codigo_porcentaje => base imponible (SUBTOTAL X%)
        $ivaMap     = []; // codigo_porcentaje => valor IVA
        $tarifaMap  = []; // codigo_porcentaje => tarifa numérica (para etiquetas)
        $totalIce   = 0.0;
        $totalDcto  = 0.0;
        $noObjIva   = 0.0;
        $exentoIva  = 0.0;
        $totalSubsidio = 0.0;

        foreach ($detalles as $d) {
            $totalDcto += (float)($d['descuento'] ?? 0);
            $totalSubsidio += (float)($d['subsidio'] ?? 0) * (float)($d['cantidad'] ?? 0);
            $tieneImp   = false;
            foreach ($d['impuestos'] ?? [] as $imp) {
                $cod     = (string)($imp['codigo_impuesto'] ?? '');
                $tar     = (float)($imp['tarifa'] ?? 0);
                $val     = (float)($imp['valor'] ?? 0);
                $base    = (float)($imp['base_imponible'] ?? $d['precio_total_sin_impuesto'] ?? 0);

                if ($cod === '2') { // IVA
                    // codigo_porcentaje canónico: para tarifa > 0 se deriva de la
                    // tarifa real (igual que el XML, evita códigos desactualizados);
                    // para tarifa 0 se respeta el guardado, que distingue 0% (0),
                    // no objeto (6) y exento (7) —todos con tarifa 0—.
                    $codPct = $tar > 0
                        ? \App\Helpers\SriIvaHelper::codigoPorcentaje($tar)
                        : (string)($imp['codigo_porcentaje'] ?? '0');

                    if ($codPct === '6') {            // No objeto de IVA
                        $noObjIva += $base;
                    } elseif ($codPct === '7') {      // Exento de IVA
                        $exentoIva += $base;
                    } else {                          // 0%, 5%, 12%, 15%, ...
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
                $subtotMap['0']  = ($subtotMap['0'] ?? 0.0) + (float)($d['precio_total_sin_impuesto'] ?? 0);
                $ivaMap['0']     = ($ivaMap['0']    ?? 0.0);
                $tarifaMap['0']  = 0.0;
            }
        }
        ksort($subtotMap);
        ksort($ivaMap);

        $totalIva       = array_sum($ivaMap);
        $propina        = (float)($cab['propina'] ?? 0);

        // Totales de cabecera: son las cifras que van al XML autorizado y al SRI.
        // El RIDE las toma de la cabecera (NO las recalcula) para mostrar EXACTAMENTE
        // los mismos valores del comprobante. Si algún flujo no las trae, se cae al
        // cálculo desde los detalles como respaldo.
        $subtotalSinImp = isset($cab['total_sin_impuestos'])
            ? (float)$cab['total_sin_impuestos']
            : array_sum($subtotMap) + $noObjIva + $exentoIva;
        if (isset($cab['total_descuento'])) {
            $totalDcto = (float)$cab['total_descuento'];
        }
        $total          = isset($cab['importe_total'])
            ? (float)$cab['importe_total']
            : $subtotalSinImp + $totalIva + $totalIce + $propina;

        // Reconciliar el IVA mostrado con el total del comprobante.
        // importe_total es la fuente de verdad (lo firmado y autorizado por el SRI). Si la
        // empresa calcula el IVA sobre el subtotal por tarifa, ese total puede diferir en
        // ±1 centavo del IVA sumado por línea que se acumuló arriba (redondeo por renglón).
        // Se absorbe el desfase en el grupo de mayor IVA para que, en el RIDE,
        // SUBTOTAL SIN IMPUESTOS + IVA (+ ICE + PROPINA) cuadre EXACTO con VALOR TOTAL.
        if (isset($cab['importe_total']) && !empty($ivaMap)) {
            $ivaObjetivo = round($total - $subtotalSinImp - $totalIce - $propina, 2);
            $desfase     = round($ivaObjetivo - array_sum($ivaMap), 2);
            if (abs($desfase) >= 0.01 && abs($desfase) <= 0.05) {
                $kMax = null; $vMax = -INF;
                foreach ($ivaMap as $k => $v) {
                    if ($v > $vMax) { $vMax = $v; $kMax = $k; }
                }
                if ($kMax !== null) {
                    $ivaMap[$kMax] = round($ivaMap[$kMax] + $desfase, 2);
                }
            }
        }
        $totalIva = array_sum($ivaMap);

        if ($y > 212) {
            $pdf->AddPage();
            $y = 12;
        }

        // Layout. La columna de totales mide 52 mm de etiqueta + 22 de valor: a
        // FUENTE_CUERPO la etiqueta más larga ("SUBTOTAL NO OBJETO DE IVA") ocupa
        // 49 mm y un importe de 9,999,999.99 unos 20.5 mm (con 18 mm, los importes
        // desde 1,000,000 se salían de la celda).
        $totW = 74;
        $izqW = $cW - $totW - 2;
        $totX = $mL + $izqW + 2;
        $lh   = 5;

        // ── Columna derecha: tabla de totales SRI ─────────────────────────────
        $yTot = $y;
        $pdf->SetLineWidth(0.3);

        $lblW = 52; // ancho etiqueta
        $valW = $totW - $lblW;

        // Subtotales por concepto de IVA (codigo_porcentaje)
        foreach ($subtotMap as $codPct => $base) {
            $tarPct   = $tarifaMap[$codPct] ?? 0.0;
            $tarLabel = $tarPct == (int)$tarPct ? (string)(int)$tarPct : number_format($tarPct, 2);
            $lbl = "SUBTOTAL {$tarLabel}%";
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, $lbl, $base);
            $yTot += $lh;
        }
        // Subtotal no objeto / exento
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

        // IVA por concepto (codigo_porcentaje)
        foreach ($ivaMap as $codPct => $ivaVal) {
            $tarPct   = $tarifaMap[$codPct] ?? 0.0;
            $tarLabel = $tarPct == (int)$tarPct ? (string)(int)$tarPct : number_format($tarPct, 2);
            $lbl = "IVA {$tarLabel}%";
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, $lbl, $ivaVal);
            $yTot += $lh;
        }

        // IRBPNR ya no se imprime: el sistema no emite ese impuesto y la fila
        // iba fija en 0.00, ocupando una línea de totales en todas las facturas.

        // SERVICIO (propina) solo si el establecimiento lo tiene activado en su
        // configuración (Empresa → Facturación → propina). Se imprime igual
        // cuando el documento ya trae propina > 0, para que un comprobante
        // emitido con servicio nunca lo oculte aunque después se apague el
        // interruptor o la config no llegue hasta este PDF.
        $propinaActiva = in_array((string)($empresa['mostrar_propina_factura'] ?? 'false'), ['t', 'true', '1'], true)
            || ($empresa['mostrar_propina_factura'] ?? false) === true;
        if ($propinaActiva || abs($propina) >= 0.005) {
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'SERVICIO', $propina);
            $yTot += $lh;
        }

        // VALOR TOTAL (negrita, fondo). Mismo tamaño que el resto: lo distinguen
        // la negrita y el fondo gris.
        $pdf->SetFont('helvetica', 'B', self::FUENTE_CUERPO);
        $pdf->SetFillColor(210, 210, 210);
        $pdf->SetXY($totX, $yTot);
        $pdf->Cell($lblW, $lh, 'VALOR TOTAL', 1, 0, 'L', true);
        $pdf->Cell($valW, $lh, number_format($total, 2), 1, 1, 'R', true, '', 1);
        $yTot += $lh;

        if ($totalSubsidio > 0) {
            $valorTotalSinSubsidio = $total + $totalSubsidio;
            $this->filaTotales($pdf, $totX, $yTot, $lblW, $valW, $lh, 'VALOR TOTAL SIN SUBSIDIO', $valorTotalSinSubsidio);
            $yTot += $lh;

            // AHORRO POR SUBSIDIO (2 líneas; cada una cabe en $lblW a este tamaño)
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($totX, $yTot);
            $pdf->MultiCell($lblW, $lh, "AHORRO POR SUBSIDIO:\n(Incluye IVA cuando corresponda)", 1, 'L', false, 0);
            
            $pdf->SetXY($totX + $lblW, $yTot);
            $pdf->Cell($valW, $lh * 2, number_format($totalSubsidio, 2), 1, 1, 'R', false, '', 1);
            $yTot += $lh * 2;
        }

        // ── Columna izquierda: Información Adicional + Forma de pago ──────────
        $yIzq      = $y;
        $paginaPie = $pdf->getPage();   // página donde quedaron los totales
        $limiteY   = $pdf->getPageHeight() - $pdf->getBreakMargin();

        // Información Adicional — misma tipografía que la tabla de ítems: título
        // en negrita FUENTE_ENCABEZADO_TABLA y filas en FUENTE_CUERPO.
        if (!empty($infoAdicional)) {
            $etiqW = 40;
            $valIW = $izqW - $etiqW;

            $dibujarTitulo = function (float $yT) use ($pdf, $mL, $izqW, $lh): float {
                $pdf->SetFont('helvetica', 'B', self::FUENTE_ENCABEZADO_TABLA);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->SetXY($mL, $yT);
                $pdf->Cell($izqW, $lh, 'Información Adicional', 1, 1, 'C', true);
                $pdf->SetFillColor(255, 255, 255);
                return $yT + $lh;
            };
            $yIzq = $dibujarTitulo($yIzq);

            foreach ($infoAdicional as $info) {
                $nombre = (string)($info['nombre'] ?? '');
                $valor  = (string)($info['valor']  ?? '');

                // Alto de la fila según el más largo de los dos textos, como en la
                // tabla de ítems: concepto y valor quedan en recuadros del mismo
                // alto. Antes el concepto iba en una celda de alto fijo y, si el
                // valor ocupaba dos líneas, los bordes quedaban desparejos.
                $pdf->SetFont('helvetica', 'B', self::FUENTE_CUERPO);
                $nNom = max(1, $pdf->getNumLines($nombre, $etiqW));
                $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
                $nVal = max(1, $pdf->getNumLines($valor, $valIW));
                $h    = max(self::ALTO_MIN_FILA, max($nNom, $nVal) * self::LINEA_CUERPO);

                // Salto de página controlado (mismo patrón que dibujarDetalle): el
                // automático de TCPDF podía caer entre las dos celdas de una fila y
                // partirla en dos páginas. En la página nueva se repite el título.
                if ($yIzq + $h > $limiteY) {
                    $pdf->AddPage();
                    $yIzq = $dibujarTitulo($pdf->GetY());
                }

                $pdf->SetFont('helvetica', 'B', self::FUENTE_CUERPO);
                $pdf->SetXY($mL, $yIzq);
                $pdf->MultiCell($etiqW, $h, $nombre, 1, 'L', false, 0, '', '', true, 0, false, true, $h, 'M');
                $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
                $pdf->MultiCell($valIW, $h, $valor, 1, 'L', false, 1, '', '', true, 0, false, true, $h, 'M');
                $yIzq += $h;
            }
        }

        // Observaciones. Se omite el recuadro cuando la información adicional ya
        // trae ese mismo texto bajo el concepto "Observaciones" (las facturas de
        // Facturación de Consignaciones lo llevan ahí porque es lo único que viaja
        // en el XML): si no, el RIDE imprimiría dos veces lo mismo.
        if (!empty($cab['observaciones']) && !$this->observacionesYaEnInfoAdicional($infoAdicional, (string) $cab['observaciones'])) {
            $yIzq += 1;
            // Título y primeras líneas juntos, con salto controlado. Con el salto
            // automático, si el bloque llegaba al pie el título quedaba SOLO en una
            // página y el texto en la siguiente (SetXY con la Y de la página
            // anterior): una página casi vacía. El texto largo sí puede seguir en
            // la página siguiente: lo parte el propio MultiCell.
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $nObs = max(1, $pdf->getNumLines((string) $cab['observaciones'], $izqW));
            if ($yIzq + $lh + min($nObs, 2) * self::LINEA_CUERPO > $limiteY) {
                $pdf->AddPage();
                $yIzq = $pdf->GetY();
            }
            // Misma tipografía que la tabla de ítems: título en negrita
            // FUENTE_ENCABEZADO_TABLA y texto a FUENTE_CUERPO.
            $pdf->SetFont('helvetica', 'B', self::FUENTE_ENCABEZADO_TABLA);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'Observaciones', 1, 1, 'C', true);
            $yIzq += $lh;
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($mL, $yIzq);
            $pdf->MultiCell($izqW, self::LINEA_CUERPO, $cab['observaciones'], 1, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        // Forma de pago
        if (!empty($pagos)) {
            $yIzq += 1;
            // Encabezado tabla pagos. Anchos medidos a la tipografía del cuerpo:
            // Valor cabe 9,999,999.99 (20.5 mm), "Días Crédito" 19.7 mm y "Meses"
            // 11.4 mm. Lo que sobra va al nombre de la forma de pago, que es lo
            // largo: "OTROS CON UTILIZACION DEL SISTEMA FINANCIERO" queda en dos
            // líneas en vez de tres.
            $wValor  = 24;
            $wDias   = 21;
            $wPlazo  = 16;
            $wNombre = $izqW - $wValor - $wDias - $wPlazo;
            // Encabezado y primera fila juntos: que el título no quede huérfano al
            // pie de la página.
            if ($yIzq + $lh * 2 > $limiteY) {
                $pdf->AddPage();
                $yIzq = $pdf->GetY();
            }
            $pdf->SetFont('helvetica', 'B', self::FUENTE_ENCABEZADO_TABLA);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($wNombre, $lh, 'Forma de pago', 1, 0, 'C', true);
            $pdf->Cell($wValor,  $lh, 'Valor',         1, 0, 'C', true);
            $pdf->Cell($wDias,   $lh, 'Días Crédito',  1, 0, 'C', true);
            $pdf->Cell($wPlazo,  $lh, 'Plazo',         1, 1, 'C', true);
            $yIzq += $lh;

            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $pdf->SetFillColor(255, 255, 255);
            foreach ($pagos as $p) {
                $nombreP = $p['nombre_forma_pago'] ?? ($p['forma_pago'] ?? '');
                $valorP  = number_format((float)($p['total'] ?? 0), 2);
                $dias    = (int)($p['plazo'] ?? 0);
                // "Plazo" es la UNIDAD de tiempo (Días / Meses / Años). La
                // cantidad ya va en la columna "Días Crédito", así que aquí no se
                // repite el número (antes salía "15 dias" en las dos columnas).
                $plazoLbl = $dias > 0 ? $this->etiquetaUnidadTiempo($p['unidad_tiempo'] ?? null) : '—';
                $diasLbl  = $dias > 0 ? (string)$dias : '0';

                // Calcular cuántas líneas ocupa el nombre de la forma de pago
                $numLines = $pdf->getNumLines($nombreP, $wNombre);
                // Si getNumLines retorna 0 o algo menor a 1, usar 1
                if ($numLines < 1) $numLines = 1;
                
                // Mismo alto de fila que la tabla de ítems.
                $rowH = max(self::ALTO_MIN_FILA, $numLines * self::LINEA_CUERPO);

                // Salto controlado: con el salto automático, una fila partida entre
                // páginas dejaba $yIzq en la página anterior y descolocaba el resto.
                if ($yIzq + $rowH > $limiteY) {
                    $pdf->AddPage();
                    $yIzq = $pdf->GetY();
                }

                $pdf->SetXY($mL, $yIzq);
                // MultiCell recibe un height mínimo ($rowH) y dibujará el borde hasta allí
                $pdf->MultiCell($wNombre, $rowH, $nombreP, 1, 'L', false, 0, '', '', true, 0, false, true, $rowH, 'M');

                // Las celdas adyacentes usarán el mismo alto total ($rowH)
                $pdf->Cell($wValor, $rowH, $valorP,   1, 0, 'R', false, '', 1);
                $pdf->Cell($wDias,  $rowH, $diasLbl,  1, 0, 'C');
                $pdf->Cell($wPlazo, $rowH, $plazoLbl, 1, 1, 'C');
                
                $yIzq += $rowH;
            }
        }

        // Mensaje Personalizado (Leyenda PDF)
        $leyendaTitulo  = $empresa['leyenda_pdf_titulo'] ?? '';
        $leyendaMensaje = $empresa['leyenda_pdf_mensaje'] ?? '';
        if (!empty($leyendaTitulo) || !empty($leyendaMensaje)) {
            // Posicionar debajo del máximo entre la columna izquierda y derecha
            // Si la columna izquierda siguió en otra página, los totales quedaron en
            // la anterior: en esta solo cuenta lo último de la columna izquierda.
            $yFinal = ($pdf->getPage() > $paginaPie ? $yIzq : max($yIzq, $yTot)) + 4;
            
            // Verificar salto de página manual si no cabe (aprox 30 unidades)
            if ($yFinal + 30 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                $yFinal = $pdf->GetY() + 4;
            }

            if (!empty($leyendaTitulo)) {
                $pdf->SetFont('helvetica', 'B', 7.5);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->SetXY($mL, $yFinal);
                $pdf->Cell($cW, $lh, mb_strtoupper($leyendaTitulo, 'UTF-8'), 1, 1, 'C', true);
                $yFinal += $lh;
            }
            if (!empty($leyendaMensaje)) {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetXY($mL, $yFinal);
                $pdf->MultiCell($cW, 4.5, $leyendaMensaje, 1, 'L', false, 1);
            }
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function filaTotales(
        TCPDF $pdf, float $x, float $y,
        float $lblW, float $valW, float $h,
        string $lbl, float $val
    ): void {
        $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($x, $y);
        // stretch 1 en ambas: solo condensa si un texto no cupiera en su celda.
        $pdf->Cell($lblW, $h, $lbl, 1, 0, 'L', false, '', 1);
        $pdf->Cell($valW, $h, number_format($val, 2), 1, 0, 'R', false, '', 1);
    }

    /**
     * Completa la Información Adicional con los campos que viven en la CABECERA
     * de la factura —Vendedor y Cajero— cuando no están guardados como fila en
     * `ventas_adicional`.
     *
     * Esa fila la crea únicamente el JavaScript del modal de Factura de Venta, y
     * solo mientras el establecimiento tenga activado el interruptor
     * correspondiente. Toda factura emitida por otra vía (consignaciones, POS,
     * API, cargas por Excel, migración) o guardada con el interruptor apagado
     * quedaba con el vendedor en `ventas_cabecera.id_vendedor` pero SIN salir en
     * el RIDE. Aquí se toma del documento, que es la fuente de verdad.
     *
     * - No duplica: si ya existe una fila con ese nombre, manda la guardada.
     * - Respeta la configuración del establecimiento (`mostrar_vendedor_factura`
     *   / `mostrar_cajero_factura`). Si la clave no llega —flujo que no cargó la
     *   config del establecimiento— se imprime, porque el dato está en la factura
     *   y no hay una configuración que diga lo contrario.
     */
    /**
     * ¿La información adicional ya trae las observaciones del documento? Compara
     * el texto sin espacios de más ni mayúsculas, para no repetir el mismo
     * contenido en dos recuadros del RIDE.
     */
    private function observacionesYaEnInfoAdicional(array $infoAdicional, string $observaciones): bool
    {
        $norm = static fn(string $s): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)), 'UTF-8');
        $obs  = $norm($observaciones);
        if ($obs === '') return false;

        foreach ($infoAdicional as $ia) {
            if (strcasecmp(trim((string) ($ia['nombre'] ?? '')), 'Observaciones') === 0
                && $norm((string) ($ia['valor'] ?? '')) === $obs) {
                return true;
            }
        }
        return false;
    }

    private function conCamposDeCabecera(array $infoAdicional, array $cabecera, array $empresa): array
    {
        $yaEsta = static function (string $nombre) use ($infoAdicional): bool {
            foreach ($infoAdicional as $ia) {
                if (mb_strtolower(trim((string)($ia['nombre'] ?? '')), 'UTF-8') === $nombre) {
                    return true;
                }
            }
            return false;
        };
        $activo = static function (string $flag) use ($empresa): bool {
            if (!array_key_exists($flag, $empresa)) { return true; }
            $v = $empresa[$flag];
            return $v === true || in_array((string)$v, ['t', 'true', '1'], true);
        };

        $campos = [
            ['nombre' => 'Vendedor', 'valor' => $cabecera['vendedor_nombre'] ?? '', 'flag' => 'mostrar_vendedor_factura'],
            ['nombre' => 'Cajero',   'valor' => $cabecera['usuario_nombre']  ?? '', 'flag' => 'mostrar_cajero_factura'],
        ];
        foreach ($campos as $c) {
            $valor = trim((string)$c['valor']);
            if ($valor === '' || $yaEsta(mb_strtolower($c['nombre'], 'UTF-8')) || !$activo($c['flag'])) {
                continue;
            }
            $infoAdicional[] = ['nombre' => $c['nombre'], 'valor' => $valor];
        }

        return $infoAdicional;
    }

    /**
     * Etiqueta legible de la unidad de tiempo del plazo de crédito.
     *
     * El valor guardado cambia según de dónde venga el pago: el selector del
     * módulo graba 'dias'|'meses'|'anios', pero los documentos migrados traen
     * 'DIAS', 'Días', 'MESES'… Se compara en minúsculas y sin acentos para que
     * todas esas variantes impriman la misma etiqueta.
     */
    private function etiquetaUnidadTiempo(?string $unidad): string
    {
        $u = mb_strtolower(trim((string)$unidad), 'UTF-8');
        $u = strtr($u, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        if ($u === '' || str_starts_with($u, 'dia') || str_starts_with($u, 'day')) {
            return 'Días';
        }
        if (str_starts_with($u, 'mes') || str_starts_with($u, 'month')) {
            return 'Meses';
        }
        if (str_starts_with($u, 'ani') || str_starts_with($u, 'ano') || str_starts_with($u, 'year')) {
            return 'Años';
        }
        // Valor desconocido: se imprime tal cual llegó, capitalizado.
        return mb_convert_case(trim((string)$unidad), MB_CASE_TITLE, 'UTF-8');
    }

    private function numeroFactura(array $cab): string
    {
        $est = str_pad($cab['establecimiento'] ?? '001', 3, '0', STR_PAD_LEFT);
        $pto = str_pad($cab['punto_emision']   ?? '001', 3, '0', STR_PAD_LEFT);
        $sec = str_pad($cab['secuencial']      ?? '000000001', 9, '0', STR_PAD_LEFT);
        return "{$est}-{$pto}-{$sec}";
    }
}
