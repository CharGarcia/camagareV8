<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF de UNA cotización del Cotizador de Planes (botón PDF del modal).
 *
 * Mismo diseño que los demás documentos (ref.: SuscripcionPdfService): A4 vertical,
 * franja de color, encabezado con logo y datos de la empresa, recuadro con el N.° y
 * el estado; tarjetas de Cliente y Datos de la cotización; un bloque por
 * establecimiento con sus módulos y servicios; resumen anual / mensual; notas; pie
 * con fecha de generación y número de página.
 *
 * Los valores salen de lo GUARDADO (precios y parámetros copiados en la
 * cotización), calculados con CotizadorPlanesCalculo: el PDF dice lo mismo que la
 * pantalla y que la proforma.
 */
class CotizadorPlanesPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    private const COLOR     = [31, 78, 121];
    private const COLOR_SUA = [236, 242, 249];
    private const GRIS_TXT  = [90, 98, 110];

    private const ESTADOS = [
        'borrador' => ['Borrador',          [108, 117, 125]],
        'proforma' => ['Proforma generada', [25, 135, 84]],
        'anulada'  => ['Anulada',           [220, 53, 69]],
    ];

    /**
     * @param array $cot     ficha de la cotización (CotizadorPlanesService::getFicha)
     * @param array $calc    resultado de CotizadorPlanesCalculo::calcular() sobre lo guardado
     * @param array $empresa fila de empresas con logo_ruta del establecimiento
     * @param int   $diasVigencia días de validez de la oferta (configuración del cotizador)
     */
    public function generar(array $cot, array $calc, array $empresa, int $diasVigencia, string $outputDest = 'I')
    {
        $numero = str_pad((string) (int) ($cot['numero'] ?? 0), 6, '0', STR_PAD_LEFT);

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor((string) ($empresa['nombre'] ?? ''));
        $this->pdf->SetTitle('Cotización ' . $numero);
        $this->pdf->SetMargins($this->marginL, 12, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 18);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, (string) ($cot['estado'] ?? 'borrador'));
        $y = $this->dibujarTarjetas($cot, $calc, $diasVigencia, $y + 5);
        foreach (($cot['establecimientos'] ?? []) as $i => $e) {
            $y = $this->dibujarEstablecimiento($e, $calc['establecimientos'][$i] ?? [], count($cot['establecimientos']) > 1, $y + 5);
        }
        $y = $this->dibujarResumen($cot, $calc, $y + 5);
        $this->dibujarNotas($cot, $calc, $y + 5);
        $this->dibujarPies((string) ($empresa['nombre'] ?? ''), $numero);

        $nombre = 'Cotizacion_' . $numero . '.pdf';
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
        $y0  = 12;

        $pdf->SetFillColor(...self::COLOR);
        $pdf->Rect(0, 0, 210, 5, 'F');

        $izqW = 118;
        $derW = $this->contentW - $izqW - 4;
        $derX = $mL + $izqW + 4;

        $logoPath = $this->resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 30, 24, '', '', 'T', false, 300, '', false, false, 0, 'LT', false, false);
            $textoX = $mL + 34;
        }

        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->MultiCell($mL + $izqW - $textoX, 5.5, mb_strtoupper((string) ($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetTextColor(...self::GRIS_TXT);
        $pdf->SetFont('helvetica', '', 8);
        $lineas = array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
            (string) ($empresa['correo'] ?? $empresa['email'] ?? $empresa['mail'] ?? ''),
        ]);
        foreach ($lineas as $ln) {
            $pdf->SetX($textoX);
            $pdf->MultiCell($mL + $izqW - $textoX, 4, $ln, 0, 'L', false, 1);
        }
        $yIzq = $pdf->GetY();
        $pdf->SetTextColor(0, 0, 0);

        $boxH = 32;
        $pdf->SetFillColor(...self::COLOR_SUA);
        $pdf->SetDrawColor(...self::COLOR);
        $pdf->SetLineWidth(0.3);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 2, '1111', 'DF');
        $pdf->SetFillColor(...self::COLOR);
        $pdf->RoundedRect($derX, $y0, $derW, 8, 2, '1001', 'F');

        $pdf->SetXY($derX, $y0 + 1.5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($derW, 5, 'COTIZACIÓN', 0, 1, 'C');

        $pdf->SetXY($derX, $y0 + 10);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(...self::GRIS_TXT);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($derW, 7, $numero, 0, 1, 'C');

        [$txtEstado, $colEstado] = self::ESTADOS[strtolower($estado)] ?? [ucfirst($estado), [108, 117, 125]];
        $pdf->SetFont('helvetica', 'B', 8);
        $pilW = $pdf->GetStringWidth(mb_strtoupper($txtEstado)) + 10;
        $pilX = $derX + ($derW - $pilW) / 2;
        $pilY = $y0 + 23;
        $pdf->SetFillColor(...$colEstado);
        $pdf->RoundedRect($pilX, $pilY, $pilW, 5.5, 2.75, '1111', 'F');
        $pdf->SetXY($pilX, $pilY);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($pilW, 5.5, mb_strtoupper($txtEstado), 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $yFin = max($yIzq, $y0 + $boxH) + 3;
        $pdf->SetDrawColor(...self::COLOR);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($mL, $yFin, $mL + $this->contentW, $yFin);
        return $yFin;
    }

    /** Cliente | Datos de la cotización. */
    private function dibujarTarjetas(array $c, array $calc, int $diasVigencia, float $y): float
    {
        $gap  = 4;
        $colW = ($this->contentW - $gap) / 2;
        $tipo = ['persona_natural' => 'Persona natural', 'sociedad' => 'Sociedad'][$c['tipo_contribuyente'] ?? ''] ?? '';

        $cliente = [
            ['Razón social', (string) ($c['razon_social'] ?? '')],
            ['RUC / Cédula', (string) ($c['identificacion'] ?? '')],
            ['Contacto',     (string) ($c['contacto_nombre'] ?? '')],
            ['Correo',       (string) ($c['correo'] ?? '')],
            ['Teléfono',     (string) ($c['telefono'] ?? '')],
            ['Tipo',         $tipo],
        ];
        $fecha = $this->fecha($c['fecha'] ?? null);
        $valida = !empty($c['fecha']) && strtotime((string) $c['fecha'])
            ? date('d-m-Y', strtotime((string) $c['fecha'] . ' +' . max(1, $diasVigencia) . ' days'))
            : '';
        $datos = [
            ['Fecha',            $fecha],
            ['Válida hasta',     $valida],
            ['Modalidad',        ['semestral' => 'Semestral', 'mensual' => 'Mensual'][$c['modalidad'] ?? ''] ?? 'Anual'],
            ['Establecimientos', (string) count($c['establecimientos'] ?? [])],
            ['Usuarios',         (int) $calc['usuarios'] . ' (' . (int) $calc['usuarios_incluidos'] . ' incluidos)'],
            ['Vendedor',         (string) ($c['vendedor'] ?? '')],
        ];

        $h1 = $this->dibujarTarjeta('CLIENTE', $cliente, $this->marginL, $y, $colW);
        $h2 = $this->dibujarTarjeta('DATOS DE LA COTIZACIÓN', $datos, $this->marginL + $colW + $gap, $y, $colW);
        return $y + max($h1, $h2);
    }

    private function dibujarTarjeta(string $titulo, array $pares, float $x, float $y, float $w): float
    {
        $pdf  = $this->pdf;
        $lblW = 27;
        $valW = $w - $lblW - 5;

        $pdf->SetFont('helvetica', '', 8.5);
        $alto = 9;
        foreach ($pares as [, $v]) {
            $alto += max(1, $pdf->getNumLines($v !== '' ? $v : '—', $valW)) * 4.3;
        }
        $alto += 2;

        $pdf->SetDrawColor(210, 218, 228);
        $pdf->SetLineWidth(0.25);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->RoundedRect($x, $y, $w, $alto, 2, '1111', 'DF');
        $pdf->SetFillColor(...self::COLOR_SUA);
        $pdf->RoundedRect($x, $y, $w, 7, 2, '1001', 'F');

        $pdf->SetXY($x + 3, $y + 1);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($w - 6, 5, $titulo, 0, 1, 'L');

        $yy = $y + 9;
        foreach ($pares as [$l, $v]) {
            $pdf->SetXY($x + 3, $yy);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetTextColor(...self::GRIS_TXT);
            $pdf->Cell($lblW, 4.3, $l, 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->MultiCell($valW, 4.3, $v !== '' ? $v : '—', 0, 'L', false, 1, $x + 3 + $lblW, $yy);
            $yy = $pdf->GetY();
        }
        $pdf->SetTextColor(0, 0, 0);
        return $alto;
    }

    /** Un establecimiento: tabla de módulos y servicios y su valor. */
    private function dibujarEstablecimiento(array $e, array $f, bool $variosLocales, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        $titulo = 'ESTABLECIMIENTO ' . $e['codigo'] . (($e['nombre'] ?? '') !== '' ? ' — ' . mb_strtoupper((string) $e['nombre']) : '');
        if ($variosLocales && !empty($f['es_principal'])) {
            $titulo .= '  (PRINCIPAL)';
        }
        if (($e['paquete_nombre'] ?? '') !== '') {
            $titulo .= '  ·  PAQUETE ' . mb_strtoupper((string) $e['paquete_nombre'])
                . (($e['paquete_tipo_precio'] ?? '') === 'fijo'
                    ? ' ($ ' . number_format((float) ($e['paquete_precio_anual'] ?? 0), 2, '.', ',') . ' AL AÑO)'
                    : ((float) ($e['paquete_descuento_pct'] ?? 0) > 0 ? ' (-' . $this->pct((float) $e['paquete_descuento_pct']) . ')' : ''));
        }
        $y = $this->tituloSeccion($titulo, $y, 20);

        $cols = [
            ['t' => '#',            'w' => 8,  'a' => 'C'],
            ['t' => 'Grupo',        'w' => 34, 'a' => 'L'],
            ['t' => 'Módulo / servicio', 'w' => 0, 'a' => 'L'],
            ['t' => 'Se cobra',     'w' => 26, 'a' => 'C'],
            ['t' => 'Precio anual', 'w' => 28, 'a' => 'R'],
        ];
        $fijo = 0.0;
        foreach ($cols as $c) { $fijo += $c['w']; }
        $cols[2]['w'] = $this->contentW - $fijo;

        $cabecera = function (float $yEnc) use ($pdf, $cols, $mL): float {
            $pdf->SetXY($mL, $yEnc);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(...self::COLOR);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetDrawColor(...self::COLOR);
            $pdf->SetLineWidth(0.2);
            foreach ($cols as $c) {
                $pdf->Cell($c['w'], 6.5, $c['t'], 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(30, 30, 30);
            return $yEnc + 6.5;
        };
        $y = $cabecera($y);

        $pdf->SetDrawColor(220, 226, 234);
        $n = 0;
        foreach (($e['items'] ?? []) as $it) {
            $n++;
            $nombre = (string) $it['nombre'] . (!empty($it['de_paquete']) ? ' (paquete)' : '') . (!empty($it['por_dependencia']) ? ' (requerido)' : '');
            $hFila = max(5.5, $pdf->getNumLines($nombre, $cols[2]['w'] - 2) * 4.2 + 1.3);
            if ($y + $hFila > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                $y = $cabecera($pdf->GetY());
            }
            $fill = $n % 2 === 0;
            $pdf->SetFillColor(248, 250, 252);
            $precio = (float) $it['precio_anual'];
            $pctPaq = (!empty($it['de_paquete']) && ($it['cobro'] ?? '') !== 'unico') ? (float) ($it['descuento_paquete_pct'] ?? 0) : 0.0;
            if ($pctPaq > 0) {
                $precio = round($precio - round($precio * $pctPaq / 100, 2), 2);
            }
            if (!empty($it['incluido'])) {
                $precio = 0.0; // incluido en el paquete de precio fijo o en otro módulo
            }
            $vals = [
                (string) $n,
                ($it['cobro'] ?? '') === 'unico' ? 'Servicios' : (string) ($it['grupo'] ?? ''),
                $nombre,
                ($it['cobro'] ?? '') === 'unico' ? 'Una sola vez' : 'Cada periodo',
                $precio > 0 ? '$ ' . number_format($precio, 2, '.', ',') . ($pctPaq > 0 ? ' (-' . $this->pct($pctPaq) . ')' : '') : 'Incluido',
            ];
            $x = $mL;
            foreach ($cols as $k => $c) {
                $pdf->MultiCell($c['w'], $hFila, $vals[$k], 'B', $c['a'], $fill, 0, $x, $y, true, 0, false, true, $hFila, 'M');
                $x += $c['w'];
            }
            $y += $hFila;
        }

        // Valor del establecimiento (una línea) y, debajo, los descuentos que tuvo.
        $pdf->SetXY($mL, $y + 1);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($this->contentW, 5, 'Anual $ ' . number_format((float) ($f['anual'] ?? 0), 2, '.', ',')
            . '  ·  Semestral $ ' . number_format((float) ($f['semestral'] ?? 0), 2, '.', ',')
            . '  ·  Mensual $ ' . number_format((float) ($f['mensual'] ?? 0), 2, '.', ','), 0, 1, 'R');
        $notas = [];
        if (!empty($f['paquete_precio'])) {
            $notas[] = 'Paquete ' . (string) ($e['paquete_nombre'] ?? '') . ': $ ' . number_format((float) $f['paquete_precio'], 2, '.', ',') . ' al año';
        }
        if (!empty($f['descuento_paquete'])) {
            $notas[] = 'Descuento del paquete: $ ' . number_format((float) $f['descuento_paquete'], 2, '.', ',');
        }
        if (!empty($f['descuento_establecimiento'])) {
            $notas[] = 'Descuento por establecimiento adicional: $ ' . number_format((float) $f['descuento_establecimiento'], 2, '.', ',') . ' (plan anual)';
        }
        $altoNotas = 0;
        if ($notas) {
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(...self::GRIS_TXT);
            $pdf->Cell($this->contentW, 4, implode('  ·  ', $notas), 0, 1, 'R');
            $altoNotas = 4;
        }
        $pdf->SetTextColor(0, 0, 0);
        return $y + 7 + $altoNotas;
    }

    /** Resumen: anual vs. mensual, usuarios, descuento y pago único. */
    private function dibujarResumen(array $c, array $calc, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $y = $this->tituloSeccion('RESUMEN DEL PLAN', $y, 40);

        $filas = [];
        foreach (($calc['establecimientos'] ?? []) as $f) {
            $filas[] = ['Establecimiento ' . $f['codigo'] . (($f['nombre'] ?? '') !== '' ? ' — ' . $f['nombre'] : ''), $f['anual'], $f['semestral'], $f['mensual']];
        }
        if ((int) $calc['usuarios_adicionales'] > 0) {
            $filas[] = ['Usuarios adicionales (' . (int) $calc['usuarios_adicionales'] . ')', $calc['usuarios_anual'], $calc['usuarios_semestral'], $calc['usuarios_mensual']];
        }
        if ((float) $calc['descuento_vendedor_pct'] > 0) {
            $pct = rtrim(rtrim(number_format((float) $calc['descuento_vendedor_pct'], 2, '.', ''), '0'), '.');
            $filas[] = ['Descuento comercial (' . $pct . ' %)', -$calc['descuento_vendedor_anual'], -$calc['descuento_vendedor_semestral'], -$calc['descuento_vendedor_mensual']];
        }

        $wCol = 34;
        $wDesc = $this->contentW - 3 * $wCol;
        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(...self::COLOR);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(...self::COLOR);
        $pdf->Cell($wDesc, 6.5, 'Concepto', 1, 0, 'C', true);
        $pdf->Cell($wCol, 6.5, 'Plan anual', 1, 0, 'C', true);
        $pdf->Cell($wCol, 6.5, 'Plan semestral', 1, 0, 'C', true);
        $pdf->Cell($wCol, 6.5, 'Plan mensual', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetDrawColor(220, 226, 234);
        foreach ($filas as [$txt, $a, $sem, $m]) {
            $pdf->SetX($mL);
            $pdf->Cell($wDesc, 5.5, $txt, 'B', 0, 'L');
            $pdf->Cell($wCol, 5.5, $this->money((float) $a), 'B', 0, 'R');
            $pdf->Cell($wCol, 5.5, $this->money((float) $sem), 'B', 0, 'R');
            $pdf->Cell($wCol, 5.5, $this->money((float) $m), 'B', 1, 'R');
        }

        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(...self::COLOR_SUA);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($wDesc, 7, 'TOTAL', 0, 0, 'R', true);
        $pdf->Cell($wCol, 7, $this->money((float) $calc['total_anual']) . ' / año', 0, 0, 'R', true);
        $pdf->Cell($wCol, 7, $this->money((float) $calc['total_semestral']) . ' / sem.', 0, 0, 'R', true);
        $pdf->Cell($wCol, 7, $this->money((float) $calc['total_mensual']) . ' / mes', 0, 1, 'R', true);

        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(...self::GRIS_TXT);
        $txtAhorro = (float) $calc['ahorro_anual'] > 0
            ? 'En el año: 2 semestres = ' . $this->money((float) $calc['dos_semestres']) . ' y 12 mensualidades = ' . $this->money((float) $calc['doce_mensualidades'])
              . '. Pagando anual ahorra hasta ' . $this->money((float) $calc['ahorro_anual']) . '.'
            : '';
        $pdf->Cell($this->contentW, 5, $txtAhorro, 0, 1, 'R');

        if ((float) $calc['total_unico'] > 0) {
            $pdf->SetX($mL);
            $pdf->SetFont('helvetica', 'B', 8.5);
            $pdf->SetTextColor(...self::COLOR);
            $pdf->Cell($this->contentW, 6, 'Pago único (implementación y servicios): ' . $this->money((float) $calc['total_unico']), 0, 1, 'R');
        }
        $pdf->SetTextColor(0, 0, 0);

        $modalidad = in_array($c['modalidad'] ?? '', ['semestral', 'mensual'], true) ? $c['modalidad'] : 'anual';
        $pdf->SetX($mL);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->Cell($this->contentW, 6, 'Modalidad propuesta: plan ' . $modalidad . '.', 0, 1, 'L');
        return $pdf->GetY();
    }

    private function dibujarNotas(array $c, array $calc, float $y): void
    {
        $pdf = $this->pdf;
        $p = $calc['parametros'] ?? [];
        $pct = fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . ' %';
        $notas = [
            'Valores sin IVA.',
            'El plan semestral cuesta ' . $pct($p['recargo_semestral_pct'] ?? 0) . ' más que el anual y el mensual ' . $pct($p['recargo_mensual_pct'] ?? 0) . ' más.',
            'Los establecimientos adicionales del mismo RUC tienen ' . $pct($p['descuento_establecimiento_pct'] ?? 0)
                . ' de descuento en sus módulos base, solo en plan anual; el establecimiento con los módulos base de mayor valor paga el precio completo.',
            'Incluye ' . (int) ($p['usuarios_por_establecimiento'] ?? 0) . ' usuarios por establecimiento, compartidos en el RUC. Usuario adicional: '
                . $this->money((float) ($p['precio_usuario_adicional_anual'] ?? 0)) . ' al año.',
            CotizadorPlanesService::CONDICION_RENOVACION,
        ];
        if (trim((string) ($c['observaciones'] ?? '')) !== '') {
            $notas[] = trim((string) $c['observaciones']);
        }

        // Mínimo bajo: el título y las primeras notas quedan en esta hoja y el resto sigue en
        // la siguiente, en vez de mandar toda la sección a una hoja casi vacía.
        $y = $this->tituloSeccion('CONDICIONES', $y, 9);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(...self::GRIS_TXT);
        $pdf->SetY($y);
        foreach ($notas as $n) {
            // Viñeta en su propia celda: las líneas largas quedan alineadas al texto, no a la viñeta.
            $pdf->SetX($this->marginL + 2);
            $pdf->Cell(4, 4.2, '•', 0, 0, 'L');
            $pdf->MultiCell($this->contentW - 6, 4.2, $n, 0, 'L', false, 1, $this->marginL + 6, $pdf->GetY());
        }
        $pdf->SetTextColor(0, 0, 0);
    }

    private function dibujarPies(string $empresa, string $numero): void
    {
        $pdf   = $this->pdf;
        $total = $pdf->getNumPages();
        for ($i = 1; $i <= $total; $i++) {
            $pdf->setPage($i);
            $pdf->SetAutoPageBreak(false);
            $yPie = $pdf->getPageHeight() - 12;
            $pdf->SetDrawColor(210, 218, 228);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($this->marginL, $yPie, $this->marginL + $this->contentW, $yPie);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(...self::GRIS_TXT);
            $pdf->SetXY($this->marginL, $yPie + 1);
            $pdf->Cell($this->contentW / 2, 4, $empresa . ' · Cotización N.° ' . $numero, 0, 0, 'L');
            $pdf->Cell($this->contentW / 2, 4, 'Generado el ' . date('d-m-Y H:i:s') . '   ·   Página ' . $i . ' de ' . $total, 0, 0, 'R');
        }
        $pdf->SetTextColor(0, 0, 0);
    }

    private function tituloSeccion(string $titulo, float $y, float $minAlto): float
    {
        $pdf = $this->pdf;
        if ($y + 7 + $minAlto > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }
        $pdf->SetFillColor(...self::COLOR);
        $pdf->Rect($this->marginL, $y + 0.8, 1.2, 4.4, 'F');
        $pdf->SetXY($this->marginL + 3, $y);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(...self::COLOR);
        $pdf->Cell($this->contentW - 3, 6, $titulo, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        return $y + 7;
    }

    private function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' %';
    }

    private function money(float $v): string
    {
        return ($v < 0 ? '-$ ' : '$ ') . number_format(abs($v), 2, '.', ',');
    }

    private function fecha($v, string $vacio = '—'): string
    {
        if (empty($v)) {
            return $vacio;
        }
        $ts = strtotime((string) $v);
        return $ts ? date('d-m-Y', $ts) : (string) $v;
    }

    /** Misma resolución de la ruta del logo que los demás PDF (ref.: SuscripcionPdfService). */
    private function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string) $ruta, '/');
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
}
