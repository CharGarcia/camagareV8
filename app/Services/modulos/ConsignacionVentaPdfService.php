<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Modelo general (por defecto) del comprobante en PDF de una CONSIGNACIÓN EN VENTAS.
 *
 * A4 vertical. Estructura: encabezado empresa + comprobante (mismo diseño que el
 * comprobante de Ingresos/Egresos), datos del cliente y del traslado, tabla de
 * productos, total valorizado + monto en letras y tres firmas
 * (Entregado por / Responsable de traslado / Recibí conforme).
 *
 * Cuando la empresa tenga una plantilla activa (módulo Plantillas de Documentos)
 * se usará PlantillasPdfRendererService en su lugar; este es el respaldo estándar.
 */
class ConsignacionVentaPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    public function generar(array $cabecera, array $detalles, array $empresa, string $outputDest = 'I')
    {
        $numero = trim((string)($cabecera['serie'] ?? '') . '-' . (string)($cabecera['secuencial'] ?? ''), '-');

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Consignación en Ventas ' . $numero);
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, (string)($cabecera['estado'] ?? 'Emitida'));
        $y = $this->dibujarDatosCliente($cabecera, $y + 3);
        $y = $this->dibujarTablaDetalle($detalles, $y + 3);
        $this->dibujarFirmas($cabecera, $empresa, $y);

        $nombre = 'Consignacion_' . ($numero !== '' ? $numero : 'comprobante') . '.pdf';
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
        $pdf->Cell($derW, 5, 'CONSIGNACIÓN EN VENTAS', 0, 1, 'C');

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
        $fmtHora = function ($v): string {
            $v = trim((string)$v);
            return $v !== '' ? substr($v, 0, 5) : '';
        };

        $entrega = trim($fmtFecha($c['fecha_entrega'] ?? '') . ' '
            . $fmtHora($c['hora_entrega_desde'] ?? '')
            . (($c['hora_entrega_hasta'] ?? '') ? ' - ' . $fmtHora($c['hora_entrega_hasta'] ?? '') : ''));

        $boxH = 34;
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'DF');

        $lblW = 30; $valW = 63; $lbl2W = 30; // col1 (label+val) + col2 (label+val)
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
        $par('Cliente:', (string)($c['cliente_nombre'] ?? '—'), 'Fecha emisión:', $fmtFecha($c['fecha_emision'] ?? ''));
        $par('Identificación:', (string)($c['cliente_identificacion'] ?? ''), 'Asesor:', (string)($c['vendedor_nombre'] ?? '—'));
        $par('Dirección:', (string)($c['cliente_direccion'] ?? ''), 'Resp. traslado:', (string)($c['responsable_traslado_nombre'] ?? '—'));
        $par('Punto partida:', (string)($c['punto_partida'] ?? ''), 'Punto llegada:', (string)($c['punto_llegada'] ?? ''));

        // Entrega + observaciones
        $obs = trim((string)($c['observaciones'] ?? ''));
        $pdf->SetX($mL + 2);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($lblW, 5, 'Entrega:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell($valW, 5, $this->ajustarTexto($entrega !== '' ? $entrega : '—', $valW), 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($lbl2W, 5, 'Observaciones:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell($val2W, 5, $this->ajustarTexto($obs !== '' ? $obs : '—', $val2W), 0, 1, 'L');

        return $y + $boxH;
    }

    private function dibujarTablaDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        // ¿Mostrar columnas de lote/caducidad? Solo si alguna línea las tiene.
        $mostrarLote = false; $mostrarCad = false;
        foreach ($detalles as $d) {
            if (trim((string)($d['lote'] ?? '')) !== '') $mostrarLote = true;
            if (trim((string)($d['fecha_caducidad'] ?? '')) !== '') $mostrarCad = true;
        }

        // Columnas: sin precios ni subtotales. Cantidad / Retorno / Facturados y una
        // columna "Acon" (acondicionamiento) para anotar a mano en la entrega.
        $cols = [
            ['t' => 'Código',      'w' => 18, 'a' => 'L', 'k' => 'producto_codigo'],
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L', 'k' => 'producto_nombre'],
            ['t' => 'Bodega',      'w' => 22, 'a' => 'L', 'k' => 'bodega_nombre'],
        ];
        if ($mostrarLote) $cols[] = ['t' => 'Lote',      'w' => 16, 'a' => 'L', 'k' => 'lote'];
        if ($mostrarCad)  $cols[] = ['t' => 'Caducidad', 'w' => 18, 'a' => 'C', 'k' => 'fecha_caducidad'];
        $cols[] = ['t' => 'NUP',        'w' => 16, 'a' => 'L', 'k' => 'nup'];
        $cols[] = ['t' => 'Cantidad',   'w' => 16, 'a' => 'R', 'k' => 'cantidad'];
        $cols[] = ['t' => 'Retorno',    'w' => 16, 'a' => 'R', 'k' => 'retornado'];
        $cols[] = ['t' => 'Facturados', 'w' => 18, 'a' => 'R', 'k' => 'facturado'];
        $cols[] = ['t' => 'Acon',       'w' => 12, 'a' => 'C', 'k' => '__acon__'];

        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(28.0, $this->contentW - $fixed);
        $descIdx = 1;
        foreach ($cols as $i => &$c) { if ($c['w'] === 0) { $c['w'] = $flex; $descIdx = $i; } }
        unset($c);

        // Encabezado
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->SetLineWidth(0.2);
        foreach ($cols as $c) {
            $pdf->Cell($c['w'], 6, $c['t'], 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Filas
        $pdf->SetFont('helvetica', '', 6.8);
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
                $k   = $c['k'];
                $raw = $d[$k] ?? '';
                if (in_array($k, ['cantidad', 'retornado', 'facturado'], true)) {
                    $vals[] = number_format((float)$raw, 2);
                } elseif ($k === 'fecha_caducidad') {
                    $ts = $raw ? strtotime((string)$raw) : false;
                    $vals[] = $ts ? date('d/m/Y', $ts) : (string)$raw;
                } elseif ($k === '__acon__') {
                    $vals[] = ''; // columna en blanco para anotar el acondicionamiento
                } else {
                    $vals[] = (string)$raw;
                }
            }

            // Altura según la descripción
            $descW = $cols[$descIdx]['w'];
            $nLin  = max(1, (int)ceil(max(1, $pdf->GetStringWidth((string)$vals[$descIdx])) / max(1, $descW - 2)));
            $h     = max(5.0, $nLin * 4.0);

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

    private function dibujarFirmas(array $cabecera, array $empresa, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $colW = $this->contentW / 3;

        $yLinea = $y + 22;
        if ($yLinea > 272) { $yLinea = 272; }

        $firmas = [
            ['Entregado por', strtoupper((string)($empresa['nombre'] ?? ''))],
            ['Responsable de traslado', (string)($cabecera['responsable_traslado_nombre'] ?? '')],
            ['Recibí conforme', (string)($cabecera['cliente_nombre'] ?? '')],
        ];

        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 6, $yLinea, $x + $colW - 6, $yLinea);
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
            $pdf->SetXY($x + 3, $yName);
            $pdf->MultiCell($colW - 6, 3.4, $f[1] !== '' ? $f[1] : ' ', 0, 'C', false, 0, '', '', true, 0, false, true, 0, 'T');
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
