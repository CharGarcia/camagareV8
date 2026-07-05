<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Modelo general (por defecto) del PDF de un Retorno de Consignación en Ventas.
 *
 * A4 vertical. Reutiliza el mismo ENCABEZADO del comprobante de caja
 * (Ingresos/Egresos): logo + datos de empresa a la izquierda y una caja con el
 * título y número del documento a la derecha.
 *
 * Cuerpo: datos del cliente, tabla de productos devueltos (Código, Descripción,
 * Lote, NUP, Cantidad), observaciones y motivo, y dos firmas
 * (Realizado por / Recibido por).
 *
 * Cuando la empresa tenga una plantilla activa (módulo Plantillas de Documentos,
 * tipo 'retorno_cv') se usa PlantillasPdfRendererService; este es el respaldo estándar.
 */
class RetornoCvPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    public function generar(array $cabecera, array $detalles, array $empresa, string $outputDest = 'I')
    {
        $numero = trim(((string)($cabecera['serie'] ?? '')) . '-' . ((string)($cabecera['secuencial'] ?? '')), '-');

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor((string)($empresa['nombre'] ?? ''));
        $this->pdf->SetTitle('Retorno de Consignación ' . $numero);
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, (string)($cabecera['estado'] ?? 'Emitida'));
        $y = $this->dibujarDatosCliente($cabecera, $y + 3);
        $y = $this->dibujarTablaDetalle($detalles, $y + 3);
        $y = $this->dibujarObservacionesMotivo($cabecera, $y + 3);
        $this->dibujarFirmas($cabecera, $y);

        $nombre = 'Retorno_' . ($numero !== '' ? $numero : 'comprobante') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function dibujarEncabezado(array $empresa, string $numero, string $estado): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y0  = 10;

        $izqW = 110;
        $derW = $this->contentW - $izqW - 2;
        $derX = $mL + $izqW + 2;

        // Logo (opcional)
        $logoPath = $this->resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 24, 0, '', '', 'T', false, 300);
            $textoX = $mL + 27;
        }

        // Datos de la empresa
        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell($mL + $izqW - $textoX, 5, strtoupper((string)($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetX($textoX);
        $pdf->SetFont('helvetica', '', 8);
        $lineas = array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string)($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
            (string)($empresa['correo'] ?? $empresa['email'] ?? ''),
        ]);
        foreach ($lineas as $ln) {
            $pdf->SetX($textoX);
            $pdf->MultiCell($mL + $izqW - $textoX, 4, $ln, 0, 'L', false, 1);
        }

        // Caja del comprobante (derecha)
        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell($derW, 5, 'RETORNO DE CONSIGNACIÓN', 0, 1, 'C');

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, $numero !== '' ? $numero : '—', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 5, 'Estado: ' . ucfirst($estado), 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    private function dibujarDatosCliente(array $c, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $fmtFecha = function ($v): string {
            if (empty($v)) return '';
            $ts = strtotime((string)$v);
            return $ts ? date('d/m/Y', $ts) : (string)$v;
        };

        $boxH = 18;
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'DF');

        $lblW = 30; $valW = 63; $lbl2W = 30;
        $val2W = $w - 4 - $lblW - $valW - $lbl2W;

        $par = function (string $l1, string $v1, string $l2, string $v2) use ($pdf, $mL, $lblW, $valW, $lbl2W, $val2W) {
            $pdf->SetX($mL + 2);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($lblW, 5, $l1, 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->Cell($valW, 5, $this->ajustarTexto($v1, $valW), 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($lbl2W, 5, $l2, 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->Cell($val2W, 5, $this->ajustarTexto($v2, $val2W), 0, 1, 'L');
        };

        $pdf->SetXY($mL + 2, $y + 1.5);
        $par('Cliente:', (string)($c['cliente_nombre'] ?? '—'), 'Fecha retorno:', $fmtFecha($c['fecha_retorno'] ?? ''));
        $par('Identificación:', (string)($c['cliente_identificacion'] ?? ''), 'Realizado por:', (string)($c['usuario_nombre'] ?? '—'));
        $par('Dirección:', (string)($c['cliente_direccion'] ?? ''), 'Resp. traslado:', (string)($c['responsable_traslado_nombre'] ?? '—'));

        return $y + $boxH;
    }

    /** Tabla: Código | Descripción | Lote | NUP | Cantidad. */
    private function dibujarTablaDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $cols = [
            ['t' => 'Código',      'w' => 28, 'a' => 'L', 'k' => 'producto_codigo'],
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L', 'k' => 'producto_nombre'],
            ['t' => 'Lote',        'w' => 30, 'a' => 'L', 'k' => 'lote'],
            ['t' => 'NUP',         'w' => 30, 'a' => 'L', 'k' => 'nup'],
            ['t' => 'Cantidad',    'w' => 22, 'a' => 'R', 'k' => 'cantidad'],
        ];

        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(40.0, $this->contentW - $fixed);
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = $flex; } }
        unset($c);
        $descIdx = 1;

        // Encabezado
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->SetLineWidth(0.2);
        foreach ($cols as $c) {
            $pdf->Cell($c['w'], 6, $c['t'], 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Sin productos.', 1, 1, 'C');
            return $pdf->GetY();
        }

        $alt = false;
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $vals = [];
            foreach ($cols as $c) {
                $raw = $d[$c['k']] ?? '';
                if ($c['k'] === 'cantidad') {
                    $vals[] = number_format((float)$raw, 2);
                } else {
                    $vals[] = trim((string)$raw) !== '' ? (string)$raw : '—';
                }
            }

            $descW = $cols[$descIdx]['w'];
            $nLin  = max(1, (int)ceil(max(1, $pdf->GetStringWidth((string)$vals[$descIdx])) / max(1, $descW - 2)));
            $h     = max(5.0, $nLin * 4.2);

            $x = $mL;
            $yRow = $pdf->GetY();
            foreach ($cols as $i => $c) {
                $pdf->SetXY($x, $yRow);
                if ($i === $descIdx) {
                    $pdf->MultiCell($c['w'], $h, $vals[$i], 1, $c['a'], true, 0, '', '', true, 0, false, true, $h, 'M');
                } else {
                    $pdf->Cell($c['w'], $h, $vals[$i], 1, 0, $c['a'], true);
                }
                $x += $c['w'];
            }
            $pdf->SetXY($mL, $yRow + $h);
        }

        return $pdf->GetY();
    }

    private function dibujarObservacionesMotivo(array $c, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $motivo = trim((string)($c['motivo'] ?? ''));
        $obs    = trim((string)($c['observaciones'] ?? ''));

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(24, 5, 'Motivo:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->MultiCell($w - 24, 5, $motivo !== '' ? $motivo : '—', 0, 'L', false, 1);

        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(24, 5, 'Observaciones:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->MultiCell($w - 24, 5, $obs !== '' ? $obs : '—', 0, 'L', false, 1);

        return $pdf->GetY();
    }

    /** Dos firmas: Realizado por / Recibido por. */
    private function dibujarFirmas(array $c, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $colW = $this->contentW / 2;

        $yLinea = $y + 24;
        if ($yLinea > 272) { $yLinea = 272; }

        $firmas = [
            ['Realizado por', trim((string)($c['usuario_nombre'] ?? ''))],
            ['Recibido por',  trim((string)($c['cliente_nombre'] ?? ''))],
        ];

        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 10, $yLinea, $x + $colW - 10, $yLinea);
        }
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) {
            $pdf->Cell($colW, 4, $f[0], 0, 0, 'C');
        }
        $pdf->SetFont('helvetica', '', 7.5);
        $yName = $yLinea + 5;
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->SetXY($x + 4, $yName);
            $pdf->MultiCell($colW - 8, 3.4, $f[1] !== '' ? $f[1] : ' ', 0, 'C', false, 0, '', '', true, 0, false, true, 0, 'T');
        }
    }

    /** Resuelve la ruta en disco del logo (maneja el prefijo web /sistema/public). */
    private function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string)$ruta, '/');
            if (strpos($clean, 'sistema/public/') === 0) {
                $clean = substr($clean, strlen('sistema/public/'));
            } elseif (strpos($clean, 'sistema/') === 0) {
                $clean = substr($clean, strlen('sistema/'));
            }
            if (strpos($clean, 'public/') === 0) {
                $clean = substr($clean, strlen('public/'));
            }
            foreach ([\MVC_ROOT . '/public/' . $clean, \MVC_ROOT . '/' . $clean] as $cand) {
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
        return '';
    }

    /** Recorta un texto (con …) para que quepa en $ancho mm con la fuente actual. */
    private function ajustarTexto(string $txto, float $ancho): string
    {
        $txto = trim($txto);
        if ($txto === '' || $this->pdf->GetStringWidth($txto) <= $ancho) {
            return $txto;
        }
        while ($txto !== '' && $this->pdf->GetStringWidth($txto . '…') > $ancho) {
            $txto = mb_substr($txto, 0, -1);
        }
        return rtrim($txto) . '…';
    }
}
