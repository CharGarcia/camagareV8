<?php
declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Genera el PDF del Comprobante de Retención en Compras.
 * Usa TCPDF — mismo patrón que FacturaVentaPdfService.
 */
class RetencionCompraPdfService
{
    private const MARGEN_H = 10;
    private const FUENTE    = 'helvetica';

    // ── Descarga directa ─────────────────────────────────────────

    public function generar(array $cabecera, array $lineas, array $empresa): void
    {
        $pdf = $this->construir($cabecera, $lineas, $empresa);
        $num = ($cabecera['establecimiento'] ?? '001') . '-'
             . ($cabecera['punto_emision']   ?? '001') . '-'
             . ($cabecera['secuencial']       ?? '000000001');
        $pdf->Output('Retencion_' . $num . '.pdf', 'D');
    }

    // ── Retornar bytes (para guardar en disco) ───────────────────

    public function generarBytes(array $cabecera, array $lineas, array $empresa): string
    {
        $pdf = $this->construir($cabecera, $lineas, $empresa);
        return $pdf->Output('retencion.pdf', 'S');
    }

    // ── Constructor interno ──────────────────────────────────────

    private function construir(array $cabecera, array $lineas, array $empresa): \TCPDF
    {
        require_once MVC_ROOT . '/vendor/autoload.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor($empresa['nombre'] ?? '');
        $pdf->SetTitle('Comprobante de Retención');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGEN_H, self::MARGEN_H, self::MARGEN_H);
        $pdf->SetAutoPageBreak(true, self::MARGEN_H);
        $pdf->AddPage();

        $this->dibujarEncabezado($pdf, $cabecera, $empresa);
        $this->dibujarProveedorSustento($pdf, $cabecera);
        $this->dibujarLineas($pdf, $lineas);

        $paginaTotales = $pdf->getPage();
        $yTotales      = $pdf->GetY();
        $this->dibujarTotales($pdf, $cabecera, $lineas);

        // Información adicional a la izquierda de los totales, como en la factura y la
        // liquidación. Si los totales saltaron de página, va debajo de ellos.
        $infoAdicional = $this->infoAdicional($cabecera);
        if (!empty($infoAdicional)) {
            $yFinTotales = $pdf->GetY();
            $alLado      = $pdf->getPage() === $paginaTotales;
            $pdf->SetY($alLado ? $yTotales : $yFinTotales);
            $this->dibujarInfoAdicional($pdf, $infoAdicional);
            $yFinInfo = $pdf->GetY() + 3;
            // Seguir debajo del más bajo de los dos bloques (salvo que la propia info
            // haya pasado a otra página: ahí manda su final).
            $pdf->SetY($alLado && $pdf->getPage() === $paginaTotales ? max($yFinInfo, $yFinTotales) : $yFinInfo);
        }

        if (!empty($cabecera['observaciones'])) {
            $this->dibujarObservaciones($pdf, $cabecera['observaciones']);
        }

        return $pdf;
    }

    // ── Encabezado: empresa + datos comprobante (mismo diseño que factura) ──

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

        // Resolver ruta del logo (mismo criterio que factura)
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
        if (empty($dirSuc)) $dirSuc = trim($cab['estab_direccion'] ?? $cab['punto_direccion'] ?? '');
        if (empty($dirSuc)) $dirSuc = trim($emp['direccion'] ?? '');
        if ($dirSuc) {
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

        // Correo de la empresa (solo si existe), debajo de Dirección Sucursal
        $correoEmp = trim((string)($emp['mail'] ?? $emp['email'] ?? $emp['correo'] ?? ''));
        if ($correoEmp !== '') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->MultiCell(20, 3.5, "Correo:", 0, 'L', false, 1);
            $yLbl = $pdf->GetY();
            $pdf->SetXY($mL + 22, $yIzq);
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->MultiCell($izqW - 24, 3.5, $correoEmp, 0, 'L', false, 1);
            $yIzq = max($yLbl, $pdf->GetY());
        }

        // Teléfono de la empresa (solo si existe)
        $telEmp = trim((string)($emp['telefono'] ?? ''));
        if ($telEmp !== '') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->MultiCell(20, 3.5, "Teléfono:", 0, 'L', false, 1);
            $yLbl = $pdf->GetY();
            $pdf->SetXY($mL + 22, $yIzq);
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->MultiCell($izqW - 24, 3.5, $telEmp, 0, 'L', false, 1);
            $yIzq = max($yLbl, $pdf->GetY());
        }

        // Contribuyente Especial
        $resCont = trim($emp['resolucion_contribuyente'] ?? '');
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
        $agenteRet = trim((string)($emp['agente_retencion'] ?? ''));
        if ($agenteRet !== '' && $agenteRet !== '0' && strtoupper($agenteRet) !== 'NO' && strtoupper($agenteRet) !== 'N/A') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, '', 7.5);
            $pdf->Cell(55, 4.5, 'Agente de Retención Resolución No.', 0, 0, 'L');
            $pdf->SetFont(self::FUENTE, 'B', 7.5);
            $pdf->Cell($izqW - 57, 4.5, $agenteRet, 0, 1, 'L');
            $yIzq = $pdf->GetY() + 1;
        }

        // Régimen RIMPE (solo emprendedor / negocio popular; el general no se muestra)
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

        // Título del comprobante
        $pdf->SetFont(self::FUENTE, 'B', 11);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 7, 'COMPROBANTE DE RETENCIÓN', 0, 1, 'L');
        $yDer += 7;

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

        // Clave de acceso como texto
        $claveAcceso = trim($cab['clave_acceso'] ?? '');
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
            $pdf->Cell(32, 4.5, 'FECHA Y HORA DE', 0, 0, 'L');
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

        // CLAVE DE ACCESO (etiqueta)
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'CLAVE DE ACCESO', 0, 1, 'L');
        $yDer += 5;

        // Código de barras
        if ($claveAcceso) {
            $barcodeH = 12;
            $pdf->write1DBarcode(
                $claveAcceso, 'C128', $derX + 2, $yDer, $derW - 1, $barcodeH, 0.4,
                ['position' => 'R', 'text' => false, 'stretcharray' => '', 'stretch' => true], 'N'
            );
            $yDer += $barcodeH + 1;
            $pdf->SetFont(self::FUENTE, '', 5.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 3.5, $claveAcceso, 0, 1, 'C');
            $yDer += 4;
        }

        $yDer += 2;

        // ── Bordes (alineados al fondo) ───────────────────────────────────────
        $yBottom = max($yIzq, $yDer);
        $pdf->RoundedRect($mL, $yTopIzqBox, $izqW, $yBottom - $yTopIzqBox, 3, '1111', 'D');
        $pdf->RoundedRect($derX, $yTop, $derW, $yBottom - $yTop, 3, '1111', 'D');

        // Dejar el cursor debajo del encabezado para el siguiente bloque
        $pdf->SetXY($mL, $yBottom + 3);
    }

    // ── Proveedor y documento sustento ───────────────────────────

    private function dibujarProveedorSustento(\TCPDF $pdf, array $cab): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;
        $y     = $pdf->GetY();

        $pdf->SetXY($x, $y);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($pageW, 5, 'DATOS DEL SUJETO RETENIDO', 1, 1, 'C', true);

        $pdf->SetFont(self::FUENTE, '', 7.5);
        $halfW = $pageW / 2;

        $pdf->SetX($x);
        $pdf->Cell($halfW * 0.35, 4.5, 'Razón Social:', 'LB', 0, 'L');
        $pdf->Cell($halfW * 0.65, 4.5, $cab['proveedor_razon_social'] ?? '', 'RB', 0, 'L');
        $pdf->Cell($halfW * 0.35, 4.5, 'RUC / Identificación:', 'LB', 0, 'L');
        $pdf->Cell($halfW * 0.65, 4.5, $cab['proveedor_identificacion'] ?? '', 'RB', 1, 'L');

        $pdf->SetX($x);
        $pdf->Cell($pageW * 0.15, 4.5, 'Fecha Emisión:', 'LB', 0, 'L');
        $fe = $cab['fecha_emision']
            ? date('d/m/Y', strtotime($cab['fecha_emision'])) : '';
        $pdf->Cell($pageW * 0.20, 4.5, $fe, 'B', 0, 'L');
        $pdf->Cell($pageW * 0.15, 4.5, 'Período Fiscal:', 'LB', 0, 'L');
        $pdf->Cell($pageW * 0.25, 4.5, $cab['periodo_fiscal'] ?? '', 'B', 0, 'L');
        $pdf->Cell($pageW * 0.15, 4.5, 'Tipo Sustento:', 'LB', 0, 'L');
        $tipoDoc = match($cab['tipo_doc_sustento'] ?? '01') {
            '01' => 'Factura',
            '03' => 'Liquidación de Compra',
            '05' => 'Nota de Débito',
            default => $cab['tipo_doc_sustento'] ?? '',
        };
        $pdf->Cell($pageW * 0.10, 4.5, $tipoDoc, 'RB', 1, 'L');

        $pdf->SetX($x);
        $pdf->Cell($pageW * 0.20, 4.5, 'N° Doc. Sustento:', 'LB', 0, 'L');
        $pdf->Cell($pageW * 0.30, 4.5, $cab['num_doc_sustento'] ?? '', 'B', 0, 'L');
        $pdf->Cell($pageW * 0.20, 4.5, 'Fecha Doc. Sustento:', 'LB', 0, 'L');
        $fds = !empty($cab['fecha_emision_doc_sustento'])
            ? date('d/m/Y', strtotime($cab['fecha_emision_doc_sustento'])) : '';
        $pdf->Cell($pageW * 0.30, 4.5, $fds, 'RB', 1, 'L');

        $pdf->Ln(2);
    }

    // ── Tabla de líneas de retención ─────────────────────────────

    private function dibujarLineas(\TCPDF $pdf, array $lineas): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;

        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($pageW, 5, 'DETALLE DE RETENCIONES', 1, 1, 'C', true);

        $anchos = [
            'impuesto'    => $pageW * 0.12,
            'codigo'      => $pageW * 0.10,
            'concepto'    => $pageW * 0.30,
            'base'        => $pageW * 0.16,
            'porcentaje'  => $pageW * 0.14,
            'valor'       => $pageW * 0.18,
        ];

        // Cabeceras de tabla. Se encapsulan porque hay que repetirlas al inicio de cada
        // página cuando el detalle no cabe en una sola.
        $dibujarCabeceraTabla = function () use ($pdf, $x, $anchos): void {
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->SetX($x);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell($anchos['impuesto'],   5, 'IMPUESTO',     1, 0, 'C', true);
            $pdf->Cell($anchos['codigo'],     5, 'CÓDIGO',       1, 0, 'C', true);
            $pdf->Cell($anchos['concepto'],   5, 'CONCEPTO',     1, 0, 'C', true);
            $pdf->Cell($anchos['base'],       5, 'BASE IMPON.',  1, 0, 'R', true);
            $pdf->Cell($anchos['porcentaje'], 5, '%',            1, 0, 'C', true);
            $pdf->Cell($anchos['valor'],      5, 'VALOR RET.',   1, 1, 'R', true);

            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->SetFillColor(255, 255, 255);
        };

        $dibujarCabeceraTabla();
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();

        foreach ($lineas as $l) {
            $codImp = $l['codigo_impuesto'] ?? '1';
            $impuesto = match($codImp) {
                '1', 'RENTA' => 'Renta (IR)',
                '2', 'IVA'   => 'IVA',
                '6', 'ISD'   => 'ISD',
                default       => $codImp,
            };

            $concepto = $l['concepto'] ?? ($l['sri_concepto'] ?? '');

            $nLineas = max(
                ceil(strlen($concepto) / 40),
                1
            );
            // Si el concepto necesita más alto que la estimación por caracteres (pasa con
            // mayúsculas: 40 ya ocupan dos renglones), manda su alto real; si no, el texto
            // pisaría la fila siguiente y el control de salto de abajo fallaría.
            $h = max(4.5, $nLineas * 4.5, $pdf->getStringHeight($anchos['concepto'], $concepto, false, true, null, 1));

            // Salto de página CONTROLADO. Sin esto, la fila que no cabía se partía entre
            // dos páginas y el SetY() del final —calculado con la Y de la página anterior—
            // dejaba el cursor al fondo de la nueva, así que cada línea siguiente abría
            // otra página casi vacía (41 líneas daban 3 páginas; 46, 8).
            if ($pdf->GetY() + $h > $limiteY) {
                $pdf->AddPage();
                $dibujarCabeceraTabla();
            }

            $pdf->SetX($x);
            $yBefore = $pdf->GetY();

            $pdf->MultiCell($anchos['impuesto'],   $h, $impuesto,                                       1, 'C', false, 0);
            $pdf->MultiCell($anchos['codigo'],     $h, $l['codigo_retencion'] ?? '',                    1, 'C', false, 0);
            $pdf->MultiCell($anchos['concepto'],   $h, $concepto,                                       1, 'L', false, 0);
            $pdf->MultiCell($anchos['base'],       $h, number_format((float)($l['base_imponible']    ?? 0), 2), 1, 'R', false, 0);
            $pdf->MultiCell($anchos['porcentaje'], $h, number_format((float)($l['porcentaje_retener'] ?? 0), 2) . '%', 1, 'C', false, 0);
            $pdf->MultiCell($anchos['valor'],      $h, number_format((float)($l['valor_retenido']    ?? 0), 2), 1, 'R', false, 1);

            $pdf->SetY(max($pdf->GetY(), $yBefore + $h));
        }

        $pdf->Ln(2);
    }

    // ── Totales ──────────────────────────────────────────────────

    private function dibujarTotales(\TCPDF $pdf, array $cab, array $lineas = []): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;

        // Calcular totales por tipo de impuesto desde las líneas (fuente confiable).
        $totRenta = 0.0; $totIva = 0.0; $totIsd = 0.0;
        foreach ($lineas as $l) {
            $codImp = strtoupper((string)($l['codigo_impuesto'] ?? ''));
            $val    = (float)($l['valor_retenido'] ?? 0);
            if ($codImp === '1' || $codImp === 'RENTA')     $totRenta += $val;
            elseif ($codImp === '2' || $codImp === 'IVA')   $totIva   += $val;
            elseif ($codImp === '6' || $codImp === 'ISD')   $totIsd   += $val;
        }
        $totalGeneral = $totRenta + $totIva + $totIsd;
        if ($totalGeneral <= 0) {
            // Respaldo: usar el total guardado en cabecera si no hay líneas cargadas.
            $totalGeneral = (float)($cab['total_retenido'] ?? 0);
        }

        // Cuadro angosto alineado a la derecha.
        $labelW = $pageW * 0.22;
        $valorW = $pageW * 0.13;
        $boxW   = $labelW + $valorW;         // ~35% del ancho
        $boxX   = $x + $pageW - $boxW;        // pegado al margen derecho

        $items = [['Total Retenido Renta (IR):', $totRenta], ['Total Retenido IVA:', $totIva]];
        if ($totIsd > 0) $items[] = ['Total Retenido ISD:', $totIsd];
        $items[] = ['TOTAL RETENIDO:', $totalGeneral];

        // El recuadro no se parte entre dos páginas: cada Cell salta por su cuenta, así
        // que 'TOTAL RETENIDO' podía quedar solo en la siguiente. Si no cabe entero,
        // empieza en la página siguiente.
        $lh = 4.5;
        if ($pdf->GetY() + count($items) * $lh > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
        }

        foreach ($items as $idx => [$label, $valor]) {
            $esTotal = $idx === count($items) - 1;
            $pdf->SetX($boxX);
            $pdf->SetFont(self::FUENTE, $esTotal ? 'B' : '', $esTotal ? 8 : 7);
            $pdf->Cell($labelW, $lh, $label, 'LTB', 0, 'R');
            $pdf->Cell($valorW, $lh, '$' . number_format($valor, 2), 'TBR', 1, 'R');
        }

        $pdf->Ln(3);
    }

    // ── Información adicional ────────────────────────────────────

    /**
     * Filas de información adicional del RIDE: las mismas que XmlRetencionCompraService
     * pone en el <infoAdicional> del XML, para que el impreso refleje lo enviado al SRI.
     * Las observaciones, que el XML también lleva ahí, se imprimen en su propio bloque
     * (dibujarObservaciones), igual que en la factura.
     */
    private function infoAdicional(array $cab): array
    {
        $filas = [];

        // RUC del proveedor del sistema (Res. NAC-DGERCGC26-00000027): el valor congelado
        // en la cabecera al crear la retención. Las anteriores a ese cambio lo tienen en
        // NULL y no lo llevan (su XML tampoco).
        $ruc = trim((string) ($cab['ruc_proveedor_sistema'] ?? ''));
        if ($ruc !== '') {
            $filas[] = ['nombre' => \App\Helpers\SriProveedorHelper::CAMPO_NOMBRE, 'valor' => $ruc];
        }

        return $filas;
    }

    private function dibujarInfoAdicional(\TCPDF $pdf, array $filas): void
    {
        $x     = self::MARGEN_H;
        $ancho = ($pdf->getPageWidth() - 2 * self::MARGEN_H) * 0.60; // los totales ocupan el 35 % derecho
        $etiqW = 40;
        $lh    = 4.5;

        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($ancho, 5, 'INFORMACIÓN ADICIONAL', 1, 1, 'C', true);

        foreach ($filas as $fila) {
            $pdf->SetX($x);
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->Cell($etiqW, $lh, $fila['nombre'], 1, 0, 'L');
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->MultiCell($ancho - $etiqW, $lh, $fila['valor'], 1, 'L', false, 1);
        }
    }

    // ── Observaciones ────────────────────────────────────────────

    private function dibujarObservaciones(\TCPDF $pdf, string $texto): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;
        $lh    = 4.5;

        // Etiqueta y texto van juntos: si el recuadro no cabe en lo que queda de página,
        // empieza en la siguiente (la etiqueta podía quedar sola al pie, con el texto en
        // otra página). Un texto más largo que una página sigue de corrido: basta con que
        // quepa la etiqueta con su primer renglón.
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();
        $alto    = $lh + max($lh, $pdf->getStringHeight($pageW, $texto, false, true, null, 'LBR'));
        if ($alto > $limiteY - $pdf->getMargins()['top']) {
            $alto = 2 * $lh;
        }
        if ($pdf->GetY() + $alto > $limiteY) {
            $pdf->AddPage();
        }

        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, 'B', 7.5);
        $pdf->Cell($pageW, $lh, 'OBSERVACIONES:', 'LTR', 1, 'L');
        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->MultiCell($pageW, $lh, $texto, 'LBR', 'L');
    }
}
