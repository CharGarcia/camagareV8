<?php
declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

class NotaCreditoPdfService
{
    private TCPDF $pdf;
    private float $marginL  = 10;
    private float $marginR  = 10;
    private float $contentW = 190;

    public function generar(array $nc, array $detalles, array $empresa): void
    {
        $this->renderizar($nc, $detalles, $empresa);
        $num = $this->numeroNC($nc);
        $this->pdf->Output('NC_' . $num . '.pdf', 'D');
    }

    public function generarBytes(array $nc, array $detalles, array $empresa): string
    {
        $this->renderizar($nc, $detalles, $empresa);
        return $this->pdf->Output('', 'S');
    }

    private function renderizar(array $nc, array $detalles, array $empresa): void
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
        $y = $this->dibujarInfoModificacion($nc, $y + 2);
        $y = $this->dibujarDetalle($detalles, $y + 2);
        $this->dibujarPie($nc, $detalles, $y + 2);
    }

    private function dibujarEncabezado(array $empresa, array $nc): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $izqW = 85;
        $derW = $this->contentW - $izqW - 2;
        $derX = $mL + $izqW + 2;
        $yTop = 8;

        // Caja Izquierda (Logo y Datos Empresa)
        $yIzq = $yTop + 3;
        $logoPath = !empty($empresa['logo']) ? \MVC_ROOT . '/' . ltrim($empresa['logo'], '/') : '';
        if ($logoPath && file_exists($logoPath)) {
            $pdf->Image($logoPath, $mL + 2, $yIzq, 40, 0);
            $yIzq += 25;
        } else {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->Cell($izqW - 4, 10, $empresa['nombre'] ?? 'EMPRESA', 0, 1, 'C');
            $yIzq += 12;
        }

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL + 2, $yIzq);
        $pdf->MultiCell($izqW - 4, 4, $empresa['nombre'] ?? '', 0, 'L');
        $yIzq = $pdf->GetY();

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY($mL + 2, $yIzq);
        $pdf->MultiCell($izqW - 4, 4, "Dirección Matriz: " . ($empresa['direccion'] ?? ''), 0, 'L');
        $yIzq = $pdf->GetY();

        $obl = strtoupper($empresa['obligado_contabilidad'] ?? 'NO');
        $pdf->SetXY($mL + 2, $yIzq);
        $pdf->Cell($izqW - 4, 4, "Obligado a llevar contabilidad: " . $obl, 0, 1, 'L');
        $yIzq = $pdf->GetY() + 2;
        $pdf->Rect($mL, $yTop, $izqW, $yIzq - $yTop, 'D');

        // Caja Derecha (SRI)
        $yDer = $yTop;
        $pdf->SetXY($derX + 2, $yDer + 2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($derW - 4, 6, "R.U.C.: " . ($empresa['ruc'] ?? ''), 0, 1);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell($derW - 4, 7, "NOTA DE CRÉDITO", 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($derW - 4, 5, "No. " . $this->numeroNC($nc), 0, 1);
        
        $clave = $nc['clave_acceso'] ?? '';
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell($derW - 4, 4, "NÚMERO DE AUTORIZACIÓN:", 0, 1);
        $pdf->MultiCell($derW - 4, 4, $clave, 0, 'L');
        
        $amb = ($nc['tipo_ambiente'] ?? '1') === '2' ? 'PRODUCCIÓN' : 'PRUEBAS';
        $pdf->Cell($derW - 4, 4, "AMBIENTE: " . $amb, 0, 1);
        $pdf->Cell($derW - 4, 4, "EMISIÓN: NORMAL", 0, 1);

        if ($clave) {
            $pdf->write1DBarcode($clave, 'C128', $derX + 2, $pdf->GetY() + 2, $derW - 4, 12);
        }
        
        $yDer = $pdf->GetY() + 18;
        $pdf->Rect($derX, $yTop, $derW, $yDer - $yTop, 'D');

        return max($yIzq, $yDer);
    }

    private function dibujarDatosCliente(array $nc, float $y): float
    {
        $pdf = $this->pdf;
        $mL = $this->marginL;
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($this->contentW, 6, "Razón Social / Nombres y Apellidos: " . ($nc['cliente_nombre'] ?? ''), 1, 1);
        $pdf->Cell($this->contentW / 2, 6, "Identificación: " . ($nc['cliente_ruc'] ?? ''), 1, 0);
        $pdf->Cell($this->contentW / 2, 6, "Fecha Emisión: " . ($nc['fecha_emision'] ?? ''), 1, 1);
        return $pdf->GetY();
    }

    private function dibujarInfoModificacion(array $nc, float $y): float
    {
        $pdf = $this->pdf;
        $mL = $this->marginL;
        $pdf->Rect($mL, $y, $this->contentW, 14, 'D');
        $pdf->SetXY($mL + 2, $y + 2);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(60, 5, "Comprobante que se modifica:", 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(100, 5, "FACTURA " . ($nc['num_doc_modificado'] ?? ''), 0, 1);
        
        $pdf->SetXY($mL + 2, $y + 7);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(60, 5, "Fecha Emisión (Comprobante a modificar):", 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(100, 5, $nc['fecha_emision_docs_sustento'] ?? '', 0, 1);
        
        $pdf->SetXY($mL + 2, $y + 12);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(60, 5, "Razón de Modificación:", 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(100, 5, $nc['motivo'] ?? '', 0, 1);
        
        return $y + 18;
    }

    private function dibujarDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL = $this->marginL;
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(240, 240, 240);
        
        $w = [25, 85, 20, 20, 20, 20];
        $headers = ['Código', 'Descripción', 'Cantidad', 'P. Unitario', 'Descuento', 'Total'];
        
        foreach ($headers as $i => $h) {
            $pdf->Cell($w[$i], 6, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 7);
        foreach ($detalles as $d) {
            $pdf->Cell($w[0], 6, $d['codigo_principal'] ?? '', 1);
            $pdf->Cell($w[1], 6, $d['descripcion'] ?? '', 1);
            $pdf->Cell($w[2], 6, number_format((float)$d['cantidad'], 2), 1, 0, 'C');
            $pdf->Cell($w[3], 6, number_format((float)$d['precio_unitario'], 2), 1, 0, 'R');
            $pdf->Cell($w[4], 6, number_format((float)$d['descuento'], 2), 1, 0, 'R');
            $pdf->Cell($w[5], 6, number_format((float)$d['precio_total_sin_impuesto'], 2), 1, 1, 'R');
        }
        return $pdf->GetY();
    }

    private function dibujarPie(array $nc, array $detalles, float $y): void
    {
        $pdf = $this->pdf;
        $mL = $this->marginL;
        
        $totW = 60;
        $izqW = $this->contentW - $totW - 5;
        $totX = $mL + $izqW + 5;

        // Totales
        $yTot = $y;
        $subtotal = (float)($nc['total_sin_impuestos'] ?? 0);
        $descuento = (float)($nc['total_descuento'] ?? 0);
        $total = (float)($nc['importe_total'] ?? 0);
        $iva = $total - ($subtotal - $descuento);

        $this->filaTot($pdf, $totX, $yTot, $totW, "SUBTOTAL SIN IMPUESTOS", $subtotal);
        $yTot += 5;
        $this->filaTot($pdf, $totX, $yTot, $totW, "DESCUENTO", $descuento);
        $yTot += 5;
        $this->filaTot($pdf, $totX, $yTot, $totW, "IVA", $iva);
        $yTot += 5;
        $pdf->SetFont('helvetica', 'B', 9);
        $this->filaTot($pdf, $totX, $yTot, $totW, "VALOR TOTAL", $total);

        // Info Adicional
        if (!empty($nc['observaciones'])) {
            $pdf->SetXY($mL, $y);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($izqW, 6, "Información Adicional", 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->MultiCell($izqW, 6, $nc['observaciones'], 1);
        }
    }

    private function filaTot($pdf, $x, $y, $w, $label, $val) {
        $pdf->SetXY($x, $y);
        $pdf->Cell($w * 0.7, 5, $label, 1);
        $pdf->Cell($w * 0.3, 5, number_format($val, 2), 1, 0, 'R');
    }

    private function numeroNC(array $nc): string
    {
        return ($nc['establecimiento'] ?? '001') . '-' . ($nc['punto_emision'] ?? '001') . '-' . str_pad((string)$nc['secuencial'], 9, '0', STR_PAD_LEFT);
    }
}
