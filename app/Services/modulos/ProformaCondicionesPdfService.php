<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF anexo con las "Condiciones" de una proforma: el texto enriquecido que el
 * usuario escribe en la sub-pestaña Condiciones del modal. Es un documento
 * independiente del PDF comercial (ProformaPdfService): NO se imprime dentro de
 * la proforma, se descarga por separado y se adjunta al correo junto a ella.
 */
class ProformaCondicionesPdfService
{
    private array $accent    = [31, 78, 121];
    private array $grisTexto = [110, 116, 124];

    /** Devuelve '' si la proforma no tiene condiciones (no hay nada que anexar). */
    public function generar(array $cabecera, array $empresa, string $outputDest = 'S'): string
    {
        $html = trim((string) ($cabecera['condiciones_html'] ?? ''));
        if ($html === '' || trim(html_entity_decode(strip_tags($html))) === '') {
            return '';
        }

        $numero = ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-'
                . str_pad((string) ($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('CaMaGaRe');
        $pdf->SetAuthor((string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'CaMaGaRe'));
        $pdf->SetTitle('Condiciones - Proforma ' . $numero);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();

        $this->encabezado($pdf, $empresa, $cabecera, $numero);

        $pdf->SetTextColor(40, 44, 52);
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->writeHTML($this->htmlParaTcpdf($html), true, false, true, false, '');

        return (string) $pdf->Output('Condiciones_' . $numero . '.pdf', $outputDest);
    }

    private function encabezado(TCPDF $pdf, array $empresa, array $cabecera, string $numero): void
    {
        $mL = 14; $w = 182;
        $nombre  = (string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'Empresa');
        $cliente = trim((string) ($cabecera['cliente_nombre'] ?? ''));
        $fecha   = (string) ($cabecera['fecha_emision'] ?? '');
        if ($fecha !== '') {
            $ts    = strtotime($fecha);
            $fecha = $ts ? date('d-m-Y', $ts) : $fecha;
        }

        $pdf->SetTextColor(...$this->accent);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY($mL, 14);
        $pdf->Cell($w, 7, 'Condiciones de la proforma', 0, 1, 'L');

        $pdf->SetTextColor(...$this->grisTexto);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetX($mL);
        $pdf->Cell($w, 5, $nombre . '  ·  Proforma N.º ' . $numero . ($fecha !== '' ? '  ·  ' . $fecha : ''), 0, 1, 'L');
        if ($cliente !== '') {
            $pdf->SetX($mL);
            $pdf->Cell($w, 5, 'Cliente: ' . $cliente, 0, 1, 'L');
        }

        $yLinea = $pdf->GetY() + 2;
        $pdf->SetDrawColor(...$this->accent);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($mL, $yLinea, $mL + $w, $yLinea);
        $pdf->SetY($yLinea + 5);
    }

    /**
     * Adapta el HTML que produce el editor (Quill) a lo que entiende TCPDF:
     * la alineación y la sangría vienen como clases `ql-align-*` / `ql-indent-*`
     * y TCPDF solo aplica estilos en línea.
     */
    private function htmlParaTcpdf(string $html): string
    {
        $html = preg_replace_callback('/<([a-z][a-z0-9]*)\b([^>]*)>/i', static function (array $m): string {
            $tag   = strtolower($m[1]);
            $attrs = $m[2];
            $style = '';
            if (preg_match('/\bstyle\s*=\s*"([^"]*)"/i', $attrs, $s)) {
                $style = rtrim(trim($s[1]), ';') . ';';
            }
            if (preg_match('/ql-align-(center|right|justify)/', $attrs, $a)) {
                $style .= 'text-align:' . $a[1] . ';';
            }
            if (preg_match('/ql-indent-(\d)/', $attrs, $i)) {
                $style .= 'padding-left:' . ((int) $i[1] * 8) . 'mm;';
            }
            $attrs = preg_replace('/\s(class|style)\s*=\s*"[^"]*"/i', '', $attrs);
            if ($style !== ';' && $style !== '') {
                $attrs .= ' style="' . $style . '"';
            }
            return '<' . $tag . $attrs . '>';
        }, $html) ?? $html;

        // Párrafo vacío de Quill → línea en blanco real.
        $html = str_replace('<p><br></p>', '<p>&nbsp;</p>', $html);

        $css = '<style>
            p { margin: 0 0 4px 0; line-height: 1.35; }
            h1 { font-size: 15pt; color: rgb(31,78,121); margin: 6px 0 4px 0; }
            h2 { font-size: 13pt; color: rgb(31,78,121); margin: 6px 0 4px 0; }
            h3 { font-size: 11pt; margin: 5px 0 3px 0; }
            li { line-height: 1.35; }
            blockquote { color: rgb(110,116,124); margin-left: 6mm; }
            a { color: rgb(31,78,121); }
        </style>';

        return $css . $html;
    }
}
