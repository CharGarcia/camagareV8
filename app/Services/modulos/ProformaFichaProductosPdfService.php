<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF tipo catálogo con la imagen, código/nombre, cantidad e información adicional
 * de cada línea de una proforma. Es un adjunto OPCIONAL del correo, independiente
 * del PDF comercial que genera ProformaPdfService.
 */
class ProformaFichaProductosPdfService
{
    private array $accent     = [31, 78, 121];
    private array $grisLinea  = [210, 214, 220];
    private array $grisTexto  = [110, 116, 124];
    private array $grisFondo  = [246, 247, 249];

    private const COLS   = 4;
    private const GAP    = 5.0;
    private const CARD_H = 41.0;
    private const IMG_H  = 20.0;

    /** Devuelve '' si no hay líneas con descripción (no tiene sentido generar el PDF). */
    public function generar(array $cabecera, array $detalles, array $empresa, string $outputDest = 'S'): string
    {
        $detalles = array_values(array_filter(
            $detalles,
            static fn($d) => trim((string) ($d['descripcion'] ?? '')) !== ''
        ));
        if (empty($detalles)) {
            return '';
        }

        $numero = ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-'
                . str_pad((string) ($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('CaMaGaRe');
        $pdf->SetAuthor((string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'CaMaGaRe'));
        $pdf->SetTitle('Ficha de productos - Proforma ' . $numero);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $this->encabezado($pdf, $empresa, $numero);

        $mL   = 14;
        $w    = 182;
        $colW = ($w - self::GAP * (self::COLS - 1)) / self::COLS;

        $col = 0;
        $y   = $pdf->GetY();
        foreach ($detalles as $d) {
            if ($col === 0 && $y + self::CARD_H > $pdf->getPageHeight() - 14) {
                $pdf->AddPage();
                $y = $pdf->GetY();
            }
            $x = $mL + $col * ($colW + self::GAP);
            $this->tarjeta($pdf, $x, $y, $colW, $d);

            $col++;
            if ($col >= self::COLS) {
                $col = 0;
                $y  += self::CARD_H + self::GAP;
            }
        }

        return (string) $pdf->Output('Ficha_Productos_' . $numero . '.pdf', $outputDest);
    }

    private function encabezado(TCPDF $pdf, array $empresa, string $numero): void
    {
        $mL = 14; $w = 182;
        $nombre = (string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'Empresa');

        $pdf->SetTextColor(...$this->accent);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY($mL, 14);
        $pdf->Cell($w, 7, 'Ficha de productos', 0, 1, 'L');

        $pdf->SetTextColor(...$this->grisTexto);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetX($mL);
        $pdf->Cell($w, 5, $nombre . '  ·  Proforma N.º ' . $numero, 0, 1, 'L');

        $yLinea = $pdf->GetY() + 2;
        $pdf->SetDrawColor(...$this->accent);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($mL, $yLinea, $mL + $w, $yLinea);
        $pdf->SetY($yLinea + 4);
    }

    private function tarjeta(TCPDF $pdf, float $x, float $y, float $w, array $d): void
    {
        $pdf->SetDrawColor(...$this->grisLinea);
        $pdf->SetLineWidth(0.2);
        $pdf->RoundedRect($x, $y, $w, self::CARD_H, 1.5, '1111', 'D');

        $pad     = 2.0;
        $boxImgW = $w - $pad * 2;
        $imgTop  = $y + $pad;

        $imgPath = $this->resolverImagen((string) ($d['producto_imagen'] ?? ''));
        if ($imgPath !== '') {
            [$iw, $ih] = $this->tamanoContenido($imgPath, $boxImgW, self::IMG_H);
            $imgX = $x + ($w - $iw) / 2;
            $imgY = $imgTop + (self::IMG_H - $ih) / 2;
            $pdf->Image($imgPath, $imgX, $imgY, $iw, $ih, '', '', '', false, 300, '', false, false, 0, 'CM');
        } else {
            $pdf->SetFillColor(...$this->grisFondo);
            $pdf->RoundedRect($x + $pad, $imgTop, $boxImgW, self::IMG_H, 1, '1111', 'F');
            $pdf->SetTextColor(...$this->grisTexto);
            $pdf->SetFont('helvetica', 'I', 6);
            $pdf->SetXY($x + $pad, $imgTop + self::IMG_H / 2 - 1.5);
            $pdf->Cell($boxImgW, 3, 'Sin imagen', 0, 0, 'C');
        }

        $yTexto = $imgTop + self::IMG_H + 1.3;

        // Código + nombre
        $codigo = trim((string) ($d['codigo_principal'] ?? ''));
        $nombre = (string) ($d['producto_nombre'] ?? $d['descripcion'] ?? '');
        $titulo = $codigo !== '' ? ($codigo . ' — ' . $nombre) : $nombre;

        $pdf->SetTextColor(40, 44, 52);
        $pdf->SetFont('helvetica', 'B', 6.3);
        $pdf->SetXY($x + $pad, $yTexto);
        $pdf->MultiCell($w - $pad * 2, 2.6, $titulo, 0, 'L', false, 1, '', '', true, 0, false, true, 5.2, 'T');

        // Cantidad
        $pdf->SetTextColor(...$this->grisTexto);
        $pdf->SetFont('helvetica', '', 5.8);
        $pdf->SetX($x + $pad);
        $pdf->Cell($w - $pad * 2, 2.8, 'Cant.: ' . $this->num((float) ($d['cantidad'] ?? 0)), 0, 1, 'L');

        // Información adicional (se acota para no desbordar la tarjeta)
        $adicional = trim((string) ($d['info_adicional'] ?? ''));
        if (mb_strlen($adicional) > 90) {
            $adicional = mb_substr($adicional, 0, 89) . '…';
        }
        if ($adicional !== '') {
            $pdf->SetTextColor(45, 49, 57);
            $pdf->SetFont('helvetica', 'I', 5.8);
            $pdf->SetX($x + $pad);
            $maxH = ($y + self::CARD_H - 1) - $pdf->GetY();
            $pdf->MultiCell($w - $pad * 2, 2.6, $adicional, 0, 'L', false, 1, '', '', true, 0, false, true, max(2.6, $maxH), 'T');
        }
    }

    /** Ancho/alto (mm) que preserva la proporción de la imagen dentro de una caja (contain). */
    private function tamanoContenido(string $path, float $maxW, float $maxH): array
    {
        $info = @getimagesize($path);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return [$maxW, $maxH];
        }
        $ratio = $info[0] / $info[1];
        $w = $maxW;
        $h = $w / $ratio;
        if ($h > $maxH) {
            $h = $maxH;
            $w = $h * $ratio;
        }
        return [$w, $h];
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ','), '0'), '.');
    }

    private function resolverImagen(string $ruta): string
    {
        if (trim($ruta) === '') return '';
        $cleanRuta = ltrim($ruta, '/');
        if (strpos($cleanRuta, 'public/') === 0) $cleanRuta = substr($cleanRuta, strlen('public/'));

        $testPath = \MVC_ROOT . '/public/' . $cleanRuta;
        return is_file($testPath) ? $testPath : '';
    }
}
