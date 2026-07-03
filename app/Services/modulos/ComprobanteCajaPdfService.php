<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Modelo general (por defecto) del comprobante de caja en PDF para
 * Ingresos (Recibo de Cobro) y Egresos (Recibo de Pago).
 *
 * A4 vertical. Estructura: encabezado empresa + comprobante, datos del sujeto,
 * tabla de documentos, tabla de formas de cobro/pago, total + monto en letras
 * y tres firmas (Realizado por / Aprobado por / Recibí conforme).
 *
 * Cuando la empresa tenga una plantilla activa (módulo Plantillas de Documentos)
 * se usa PlantillasPdfRendererService en su lugar; este es el respaldo estándar.
 */
class ComprobanteCajaPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    /** Genera el PDF de un INGRESO (Recibo de Cobro). */
    public function generarIngreso(array $cabecera, array $detalles, array $pagos, array $empresa, string $outputDest = 'I', ?array $asiento = null)
    {
        $sujeto = trim((string)($cabecera['recibo_de'] ?? $cabecera['cliente_nombre'] ?? $cabecera['recibo_cliente_nombre'] ?? ''));
        return $this->render([
            'flujo'          => 'ingreso',
            'titulo'         => 'COMPROBANTE DE INGRESO',
            'numero'         => (string)($cabecera['numero_ingreso'] ?? ''),
            'sujeto_label'   => 'Recibí de',
            'sujeto_nombre'  => $sujeto !== '' ? $sujeto : '—',
            'sujeto_id'      => (string)($cabecera['cliente_ruc'] ?? ''),
            'monto_col'      => 'Cobrado',
            'monto_key'      => 'monto_cobrado',
            'forma_key'      => 'forma_cobro_nombre',
            'recibi_label'   => 'Recibí conforme',
            'file_prefix'    => 'Ingreso',
        ], $cabecera, $detalles, $pagos, $empresa, $outputDest, $asiento);
    }

    /** Genera el PDF de un EGRESO (Recibo de Pago). */
    public function generarEgreso(array $cabecera, array $detalles, array $pagos, array $empresa, string $outputDest = 'I', ?array $asiento = null)
    {
        return $this->render([
            'flujo'          => 'egreso',
            'titulo'         => 'COMPROBANTE DE EGRESO',
            'numero'         => (string)($cabecera['numero_egreso'] ?? ''),
            'sujeto_label'   => 'Pagado a',
            'sujeto_nombre'  => trim((string)($cabecera['sujeto_nombre'] ?? '')) ?: '—',
            'sujeto_id'      => (string)($cabecera['sujeto_ruc'] ?? ''),
            'monto_col'      => 'Pagado',
            'monto_key'      => 'monto_pagado',
            'forma_key'      => 'forma_pago_nombre',
            'recibi_label'   => 'Recibí conforme',
            'file_prefix'    => 'Egreso',
        ], $cabecera, $detalles, $pagos, $empresa, $outputDest, $asiento);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function render(array $cfg, array $cabecera, array $detalles, array $pagos, array $empresa, string $outputDest, ?array $asiento = null)
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle($cfg['titulo'] . ' ' . $cfg['numero']);
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $cfg);
        $y = $this->dibujarDatosSujeto($cfg, $cabecera, $y + 3);
        $y = $this->dibujarTablaDetalle($cfg, $detalles, $y + 3);
        $y = $this->dibujarTablaPagos($cfg, $pagos, $y + 3);
        $y = $this->dibujarTotales($cabecera, $y + 3);
        $y = $this->dibujarAsiento($asiento, $y + 4);
        $this->dibujarFirmas($cfg, $cabecera, $y);

        $nombre = $cfg['file_prefix'] . '_' . ($cfg['numero'] !== '' ? $cfg['numero'] : 'comprobante') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    private function dibujarEncabezado(array $empresa, array $cfg): float
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
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($derW, 5, $cfg['titulo'], 0, 1, 'C');

        $fecha = '';
        // fecha se dibuja en datos del sujeto; aquí número y estado
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($derW, 6, $cfg['numero'] !== '' ? $cfg['numero'] : '—', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $estado = strtolower((string)($cfg['estado'] ?? ''));
        // El estado real lo obtenemos de la cabecera en datos del sujeto; badge de anulado:
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 7);

        return max($pdf->GetY(), $y0 + $boxH);
    }

    private function dibujarDatosSujeto(array $cfg, array $cabecera, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $fecha = '';
        if (!empty($cabecera['fecha_emision'])) {
            $ts = strtotime((string)$cabecera['fecha_emision']);
            $fecha = $ts ? date('d/m/Y', $ts) : (string)$cabecera['fecha_emision'];
        }
        $estado = ucfirst((string)($cabecera['estado'] ?? 'registrado'));

        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, 20, 1.5, '1111', 'DF');

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL + 2, $y + 2);
        $pdf->Cell(28, 5, $cfg['sujeto_label'] . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        // Ajustar el nombre al ancho disponible (90mm) para no invadir la columna Fecha.
        $pdf->Cell(90, 5, $this->ajustarTexto((string)$cfg['sujeto_nombre'], 90), 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(18, 5, 'Fecha:', 0, 0, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, $fecha, 0, 1, 'L');

        if (trim((string)$cfg['sujeto_id']) !== '') {
            $pdf->SetXY($mL + 2, $pdf->GetY());
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(28, 5, 'Identificación:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(90, 5, (string)$cfg['sujeto_id'], 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(18, 5, 'Estado:', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $estado, 0, 1, 'L');
        }

        $obs = trim((string)($cabecera['observaciones'] ?? ''));
        $pdf->SetXY($mL + 2, $pdf->GetY());
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(28, 5, 'Por concepto de:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell($w - 32, 5, $obs !== '' ? $obs : '—', 0, 'L', false, 1);

        return $y + 20;
    }

    private function dibujarTablaDetalle(array $cfg, array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $montoKey = $cfg['monto_key'];

        // Columnas (mm). Descripción es flexible.
        $cols = [
            ['t' => 'Tipo',        'w' => 20, 'a' => 'L'],
            ['t' => 'N.° Doc.',    'w' => 28, 'a' => 'L'],
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L'],
            ['t' => 'Monto Doc.',  'w' => 22, 'a' => 'R'],
            ['t' => 'Saldo Ant.',  'w' => 22, 'a' => 'R'],
            ['t' => $cfg['monto_col'], 'w' => 22, 'a' => 'R'],
            ['t' => 'Saldo Act.',  'w' => 22, 'a' => 'R'],
        ];
        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(24.0, $this->contentW - $fixed);
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = $flex; } }
        unset($c);

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

        // Filas
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(0, 0, 0);
        $alt = false;
        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Sin documentos.', 1, 1, 'C');
        }
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $vals = [
                (string)($d['tipo_documento'] ?? ''),
                (string)($d['numero_documento'] ?? ''),
                (string)($d['descripcion'] ?? ''),
                number_format((float)($d['monto_documento'] ?? 0), 2),
                number_format((float)($d['saldo_anterior'] ?? 0), 2),
                number_format((float)($d[$montoKey] ?? 0), 2),
                number_format((float)($d['saldo_actual'] ?? 0), 2),
            ];

            // Altura según descripción
            $descW = $cols[2]['w'];
            $nLin  = max(1, (int)ceil(max(1, $pdf->GetStringWidth($vals[2])) / max(1, $descW - 2)));
            $h     = max(5.0, $nLin * 4.2);

            $x = $mL;
            $yRow = $pdf->GetY();
            foreach ($cols as $i => $c) {
                $pdf->SetXY($x, $yRow);
                if ($i === 2) {
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

    private function dibujarTablaPagos(array $cfg, array $pagos, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $formaKey = $cfg['forma_key'];

        // Media tabla (izquierda): forma | referencia | valor
        $w1 = 60; $w2 = 66; $w3 = 30; // = 156, cabe en 186
        $totalW = $w1 + $w2 + $w3;

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(90, 90, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(90, 90, 90);
        $pdf->Cell($totalW, 5.5, 'FORMAS DE ' . ($cfg['flujo'] === 'ingreso' ? 'COBRO' : 'PAGO'), 1, 1, 'C', true);

        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell($w1, 5, 'Forma', 1, 0, 'L', true);
        $pdf->Cell($w2, 5, 'Referencia', 1, 0, 'L', true);
        $pdf->Cell($w3, 5, 'Valor', 1, 1, 'R', true);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        if (empty($pagos)) {
            $pdf->SetX($mL);
            $pdf->Cell($totalW, 5, 'Sin formas registradas.', 1, 1, 'C');
        }
        foreach ($pagos as $p) {
            $ref = trim((string)($p['referencia'] ?? ''));
            $tipoOp = trim((string)($p['tipo_operacion_bancaria'] ?? ''));
            if ($tipoOp !== '') {
                $extra = $tipoOp;
                if (strtoupper($tipoOp) === 'CHEQUE' && !empty($p['numero_cheque'])) {
                    $extra = 'CHEQUE #' . $p['numero_cheque'];
                }
                $ref = $ref !== '' ? ($extra . ' — ' . $ref) : $extra;
            }
            $pdf->SetX($mL);
            $pdf->Cell($w1, 5, (string)($p[$formaKey] ?? ''), 1, 0, 'L');
            $pdf->Cell($w2, 5, $ref, 1, 0, 'L');
            $pdf->Cell($w3, 5, number_format((float)($p['monto'] ?? 0), 2), 1, 1, 'R');
        }

        return $pdf->GetY();
    }

    private function dibujarTotales(array $cabecera, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $total = (float)($cabecera['monto_total'] ?? 0);

        // Caja total a la derecha
        $tW = 66;
        $tX = $mL + $this->contentW - $tW;
        $pdf->SetXY($tX, $y);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->Cell(30, 8, 'TOTAL', 1, 0, 'C', true);
        $pdf->Cell($tW - 30, 8, '$ ' . number_format($total, 2), 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);

        // Monto en letras (izquierda, debajo)
        $letras = $this->montoEnLetras($total);
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->MultiCell($this->contentW - $tW - 4, 4, 'SON: ' . $letras . ' DÓLARES', 0, 'L', false, 1);

        return max($pdf->GetY(), $y + 8);
    }

    /**
     * Dibuja el asiento contable bajo las formas de pago, SOLO si viene y está
     * cuadrado (Σ debe = Σ haber y al menos una línea). Si no, no dibuja nada.
     * Devuelve la Y final (o la de entrada si no se dibujó).
     */
    private function dibujarAsiento(?array $asiento, float $y): float
    {
        $lineas = $asiento['detalles'] ?? [];
        if (empty($lineas)) {
            return $y;
        }

        $sumDebe = 0.0; $sumHaber = 0.0;
        foreach ($lineas as $d) {
            $sumDebe  += (float)($d['debe'] ?? 0);
            $sumHaber += (float)($d['haber'] ?? 0);
        }
        // Debe estar cuadrado (y no ser un asiento vacío en ceros).
        if (round(abs($sumDebe - $sumHaber), 2) > 0.01 || round($sumDebe, 2) <= 0.0) {
            return $y;
        }

        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $wCod = 26; $wDebe = 28; $wHaber = 28;
        $wCta = $this->contentW - $wCod - $wDebe - $wHaber; // 104

        // Barra de título
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(60, 70, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(60, 70, 90);
        $pdf->SetLineWidth(0.2);
        $titulo = 'ASIENTO CONTABLE';
        if (!empty($asiento['numero_comprobante'])) {
            $titulo .= '  ·  N.° ' . $asiento['numero_comprobante'];
        }
        $pdf->Cell($this->contentW, 5.5, $titulo, 1, 1, 'C', true);

        // Encabezado de columnas
        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell($wCod, 5, 'Código', 1, 0, 'L', true);
        $pdf->Cell($wCta, 5, 'Cuenta', 1, 0, 'L', true);
        $pdf->Cell($wDebe, 5, 'Debe', 1, 0, 'R', true);
        $pdf->Cell($wHaber, 5, 'Haber', 1, 1, 'R', true);

        // Filas
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($lineas as $d) {
            $debe  = (float)($d['debe'] ?? 0);
            $haber = (float)($d['haber'] ?? 0);
            $pdf->SetX($mL);
            $pdf->Cell($wCod, 4.6, (string)($d['codigo_cuenta'] ?? ''), 1, 0, 'L');
            $pdf->Cell($wCta, 4.6, $this->ajustarTexto((string)($d['nombre_cuenta'] ?? ''), $wCta - 2), 1, 0, 'L');
            $pdf->Cell($wDebe, 4.6, $debe > 0 ? number_format($debe, 2) : '', 1, 0, 'R');
            $pdf->Cell($wHaber, 4.6, $haber > 0 ? number_format($haber, 2) : '', 1, 1, 'R');
        }

        // Totales
        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(235, 238, 242);
        $pdf->Cell($wCod + $wCta, 5, 'TOTALES', 1, 0, 'R', true);
        $pdf->Cell($wDebe, 5, number_format($sumDebe, 2), 1, 0, 'R', true);
        $pdf->Cell($wHaber, 5, number_format($sumHaber, 2), 1, 1, 'R', true);

        return $pdf->GetY();
    }

    private function dibujarFirmas(array $cfg, array $cabecera, float $y): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $colW = $this->contentW / 3;

        // Espacio moderado (~2 cm) sobre la línea para firmar, tras el contenido.
        $yLinea = $y + 20;
        if ($yLinea > 272) { $yLinea = 272; } // no invadir el pie de página

        $firmas = [
            ['Realizado por', trim((string)($cabecera['usuario_nombre'] ?? ''))],
            ['Aprobado por', ''],
            [$cfg['recibi_label'], (string)$cfg['sujeto_nombre']],
        ];

        // Líneas de firma
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 6, $yLinea, $x + $colW - 6, $yLinea);
        }
        // Etiquetas
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) {
            $pdf->Cell($colW, 4, $f[0], 0, 0, 'C');
        }

        // Nombres (envuelven dentro de su columna para no desbordar)
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

    private function montoEnLetras(float $monto): string
    {
        require_once \MVC_ROOT . '/app/validadores/numero_letras.php';
        $str = number_format($monto, 2, '.', '');
        if (function_exists('num_letras')) {
            $txt = trim((string) num_letras($str));
            // num_letras devuelve "... CON XX/100"; normalizar espacios dobles
            return preg_replace('/\s+/', ' ', $txt);
        }
        return $str;
    }
}
