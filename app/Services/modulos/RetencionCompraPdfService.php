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
        $this->dibujarTotales($pdf, $cabecera);

        if (!empty($cabecera['observaciones'])) {
            $this->dibujarObservaciones($pdf, $cabecera['observaciones']);
        }

        return $pdf;
    }

    // ── Encabezado: empresa + datos comprobante ──────────────────

    private function dibujarEncabezado(\TCPDF $pdf, array $cab, array $emp): void
    {
        $pageW  = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $colL   = $pageW * 0.55;  // caja empresa (izquierda)
        $colR   = $pageW * 0.43;  // caja SRI (derecha)
        $startX = self::MARGEN_H;
        $startY = self::MARGEN_H;

        // Caja izquierda: logo + datos empresa
        $pdf->SetXY($startX, $startY);
        $logoPath = MVC_ROOT . '/storage/' . ($emp['logo_ruta'] ?? '');
        if (!empty($emp['logo_ruta']) && file_exists($logoPath)) {
            $pdf->Image($logoPath, $startX + 1, $startY + 2, 25, 0, '', '', '', false, 150);
            $pdf->SetXY($startX + 27, $startY + 2);
        } else {
            $pdf->SetXY($startX + 2, $startY + 2);
        }

        $pdf->SetFont(self::FUENTE, 'B', 9);
        $pdf->MultiCell($colL - 4, 4, mb_strtoupper($emp['nombre'] ?? '', 'UTF-8'), 0, 'L');
        $pdf->SetFont(self::FUENTE, '', 7);
        if (!empty($emp['nombre_comercial'])) {
            $pdf->SetX($startX + 2);
            $pdf->MultiCell($colL - 4, 3.5, $emp['nombre_comercial'], 0, 'L');
        }
        $pdf->SetX($startX + 2);
        $pdf->MultiCell($colL - 4, 3.5,
            'Dirección: ' . ($emp['direccion'] ?? ''), 0, 'L');
        $pdf->SetX($startX + 2);
        $pdf->MultiCell($colL - 4, 3.5,
            'Teléfono: ' . ($emp['telefono'] ?? '') . '   Correo: ' . ($emp['email'] ?? ''), 0, 'L');

        $altoCajaL = $pdf->GetY() - $startY + 2;

        // Caja derecha: datos SRI
        $xR = $startX + $colL + $pageW * 0.02;
        $yR = $startY;

        $pdf->SetXY($xR, $yR);
        $pdf->SetFont(self::FUENTE, 'B', 7.5);
        $pdf->Cell($colR, 5, 'RUC: ' . ($emp['ruc'] ?? ''), 1, 1, 'C');
        $pdf->SetX($xR);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->Cell($colR, 5, 'COMPROBANTE DE RETENCIÓN', 1, 1, 'C');
        $pdf->SetX($xR);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $num = ($cab['establecimiento'] ?? '001') . '-'
             . ($cab['punto_emision']   ?? '001') . '-'
             . str_pad($cab['secuencial'] ?? '', 9, '0', STR_PAD_LEFT);
        $pdf->Cell($colR, 5, 'No. ' . $num, 1, 1, 'C');

        $pdf->SetX($xR);
        $pdf->SetFont(self::FUENTE, '', 6.5);
        $pdf->Cell($colR, 4, 'NÚMERO DE AUTORIZACIÓN', 1, 1, 'C');
        $pdf->SetX($xR);
        $pdf->SetFont(self::FUENTE, '', 6.5);
        $pdf->MultiCell($colR, 4, $cab['numero_autorizacion'] ?? 'PENDIENTE', 1, 'C');

        $pdf->SetX($xR);
        $pdf->SetFont(self::FUENTE, '', 6.5);
        $fa = $cab['fecha_autorizacion']
            ? date('d/m/Y H:i:s', strtotime($cab['fecha_autorizacion']))
            : 'PENDIENTE';
        $pdf->Cell($colR, 4, 'Fecha y Hora de Autorización: ' . $fa, 1, 1, 'L');

        $ambLabel = ($cab['tipo_ambiente'] ?? '1') === '2' ? 'PRODUCCIÓN' : 'PRUEBAS';
        $pdf->SetX($xR);
        $pdf->Cell($colR, 4, 'Ambiente: ' . $ambLabel, 1, 1, 'L');

        $emisionLabel = ($cab['tipo_emision'] ?? '1') === '1' ? 'NORMAL' : 'CONTINGENCIA';
        $pdf->SetX($xR);
        $pdf->Cell($colR, 4, 'Emisión: ' . $emisionLabel, 1, 1, 'L');

        $altoCajaR = $pdf->GetY() - $yR;
        $altoTotal = max($altoCajaL, $altoCajaR) + 1;

        // Dibujar borde caja izquierda
        $pdf->Rect($startX, $startY, $colL, $altoTotal);

        // Clave de acceso (barcode)
        if (!empty($cab['clave_acceso'])) {
            $pdf->SetXY($startX, $startY + $altoTotal + 1);
            $pdf->SetFont(self::FUENTE, 'B', 6.5);
            $pdf->Cell($pageW, 4, 'CLAVE DE ACCESO', 0, 1, 'C');
            $pdf->SetX($startX);
            $pdf->write1DBarcode($cab['clave_acceso'], 'C128', $startX, $pdf->GetY(), $pageW, 10, 0.4, ['stretch' => true, 'fitwidth' => true]);
            $pdf->Ln(11);
            $pdf->SetFont(self::FUENTE, '', 6);
            $pdf->Cell($pageW, 3, $cab['clave_acceso'], 0, 1, 'C');
        } else {
            $pdf->Ln($altoTotal + 2);
        }

        $pdf->Ln(2);
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

        // Cabeceras de tabla
        $pdf->SetFont(self::FUENTE, 'B', 7);
        $pdf->SetX($x);
        $anchos = [
            'impuesto'    => $pageW * 0.12,
            'codigo'      => $pageW * 0.10,
            'concepto'    => $pageW * 0.30,
            'base'        => $pageW * 0.16,
            'porcentaje'  => $pageW * 0.14,
            'valor'       => $pageW * 0.18,
        ];
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell($anchos['impuesto'],   5, 'IMPUESTO',     1, 0, 'C', true);
        $pdf->Cell($anchos['codigo'],     5, 'CÓDIGO',       1, 0, 'C', true);
        $pdf->Cell($anchos['concepto'],   5, 'CONCEPTO',     1, 0, 'C', true);
        $pdf->Cell($anchos['base'],       5, 'BASE IMPON.',  1, 0, 'R', true);
        $pdf->Cell($anchos['porcentaje'], 5, '%',            1, 0, 'C', true);
        $pdf->Cell($anchos['valor'],      5, 'VALOR RET.',   1, 1, 'R', true);

        $pdf->SetFont(self::FUENTE, '', 7);
        $pdf->SetFillColor(255, 255, 255);

        foreach ($lineas as $l) {
            $codImp = $l['codigo_impuesto'] ?? '1';
            $impuesto = match($codImp) {
                '1', 'RENTA' => 'Renta (IR)',
                '2', 'IVA'   => 'IVA',
                '6', 'ISD'   => 'ISD',
                default       => $codImp,
            };

            $concepto = $l['concepto'] ?? ($l['sri_concepto'] ?? '');

            $pdf->SetX($x);
            $yBefore = $pdf->GetY();
            $nLineas = max(
                ceil(strlen($concepto) / 40),
                1
            );
            $h = max(4.5, $nLineas * 4.5);

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

    private function dibujarTotales(\TCPDF $pdf, array $cab): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;
        $labelW = $pageW * 0.60;
        $valorW = $pageW * 0.40;

        $pdf->SetX($x + $labelW - $pageW * 0.30);
        $pdf->SetFont(self::FUENTE, 'B', 8);

        $items = [
            ['Total Retenido Renta (IR):',  (float)($cab['total_retenido_renta'] ?? 0)],
            ['Total Retenido IVA:',          (float)($cab['total_retenido_iva']   ?? 0)],
            ['TOTAL RETENIDO:',              (float)($cab['total_retenido']       ?? 0)],
        ];

        foreach ($items as $idx => [$label, $valor]) {
            $esTotal = $idx === count($items) - 1;
            $pdf->SetX($x + $pageW * 0.50);
            if ($esTotal) $pdf->SetFont(self::FUENTE, 'B', 8.5);
            else $pdf->SetFont(self::FUENTE, '', 7.5);
            $pdf->Cell($pageW * 0.30, 5, $label, 'LTB', 0, 'R');
            $pdf->Cell($pageW * 0.20, 5, '$' . number_format($valor, 2), 'TBR', 1, 'R');
        }

        $pdf->Ln(3);
    }

    // ── Observaciones ────────────────────────────────────────────

    private function dibujarObservaciones(\TCPDF $pdf, string $texto): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;

        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, 'B', 7.5);
        $pdf->Cell($pageW, 4.5, 'OBSERVACIONES:', 'LTR', 1, 'L');
        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->MultiCell($pageW, 4.5, $texto, 'LBR', 'L');
    }
}
