<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * PDF de la Orden de Servicio Car-Wash (A4 vertical).
 *
 * Encabezado como el comprobante de Ingresos (logo + empresa a la izquierda, caja con
 * título, N.° y estado a la derecha) y datos del vehículo/cliente. El CUERPO (tabla de
 * ítems), los TOTALES y la INFORMACIÓN ADICIONAL copian el diseño del RIDE de la
 * factura (FacturaVentaPdfService): mismas columnas, tipografía, tabla de totales SRI
 * a la derecha e información adicional a la izquierda. Al final, las firmas.
 */
class OrdenCarWashPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 10;
    private float $marginR  = 10;
    private float $contentW = 190; // 210 - 10 - 10 (igual que el RIDE de factura)

    /** Decimales configurados por la empresa (cantidad y precio unitario), como en factura. */
    private int $decCantidad = 2;
    private int $decPrecio   = 2;

    /**
     * Tipografía del cuerpo, IGUAL que FacturaVentaPdfService: tabla de ítems, totales e
     * información adicional se ven como en el RIDE de la factura.
     */
    private const FUENTE_CUERPO           = 9.0;
    private const FUENTE_ENCABEZADO_TABLA = 8.5;
    private const LINEA_CUERPO            = 4.2;
    private const ALTO_MIN_FILA           = 4.8;

    /** @param string $outputDest 'I' inline, 'D' descarga, 'S' string */
    public function generar(array $orden, array $empresa, string $outputDest = 'I')
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Orden Car-Wash ' . ($orden['numero_orden'] ?? ''));
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        // Normalizar a Unicode NFC (texto pegado desde macOS), como el RIDE de factura.
        $orden   = \App\Helpers\TextoUnicodeHelper::nfcArray($orden);
        $empresa = \App\Helpers\TextoUnicodeHelper::nfcArray($empresa);
        $this->decCantidad = max(0, min(6, (int) ($empresa['decimales_cantidad'] ?? 2)));
        $this->decPrecio   = max(0, min(6, (int) ($empresa['decimales_precio'] ?? 2)));

        $y = $this->dibujarEncabezado($empresa, $orden);
        $y = $this->dibujarDatosOrden($orden, $y + 3);
        $y = $this->dibujarTablaDetalle($orden['detalles'] ?? [], $y + 2);
        $y = $this->dibujarPie($orden, $empresa, $y + 2);
        $this->dibujarFirmas($orden, $y + 4);

        $nombre = 'Orden_CarWash_' . (($orden['numero_orden'] ?? '') !== '' ? $orden['numero_orden'] : 'orden') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    // ─── Encabezado (referencia: comprobante de ingresos) ─────────────────────
    private function dibujarEncabezado(array $empresa, array $orden, string $titulo = 'ORDEN CAR-WASH', string $subtitulo = ''): float
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
        $pdf->Cell($derW, 5, $titulo, 0, 1, 'C');
        if ($subtitulo !== '') {
            $pdf->SetX($derX);
            $pdf->Cell($derW, 4, $subtitulo, 0, 1, 'C');
        }

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($derW, 4, 'N.°', 0, 1, 'C');
        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $numero = trim((string)($orden['numero_orden'] ?? ''));
        $pdf->Cell($derW, 6, $numero !== '' ? $numero : '—', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($derX);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell($derW, 4, 'Estado: ' . ucfirst((string)($orden['estado'] ?? 'borrador')), 0, 1, 'C');

        return max($pdf->GetY(), $y0 + $boxH);
    }

    // ─── Datos del vehículo / cliente ─────────────────────────────────────────
    private function dibujarDatosOrden(array $orden, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $w   = $this->contentW;

        $fecha = '';
        if (!empty($orden['fecha_ingreso'])) {
            $ts = strtotime((string)$orden['fecha_ingreso']);
            $fecha = $ts ? date('d/m/Y H:i', $ts) : (string)$orden['fecha_ingreso'];
        }
        $proxCita = '';
        if (!empty($orden['proxima_cita'])) {
            $ts = strtotime((string)$orden['proxima_cita']);
            $proxCita = $ts ? date('d/m/Y', $ts) : (string)$orden['proxima_cita'];
        }
        $vehiculo = trim((string)($orden['placa'] ?? '') . '  ' . (string)($orden['marca'] ?? '') . ' ' . (string)($orden['modelo'] ?? ''));
        $cliente  = trim((string)($orden['cliente_nombre'] ?? ''));
        $comb     = (string)($orden['nivel_combustible'] ?? '');
        $km       = ($orden['kilometraje'] ?? '') !== '' && $orden['kilometraje'] !== null ? (string)$orden['kilometraje'] . ' km' : '';

        $boxH = 26;
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($mL, $y, $w, $boxH, 1.5, '1111', 'DF');

        $lbl = function (string $t) use ($pdf) { $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(24, 5, $t, 0, 0, 'L'); };
        $val = function (string $t, float $wv, int $ln = 0) use ($pdf) { $pdf->SetFont('helvetica', '', 9); $pdf->Cell($wv, 5, $t, 0, $ln, 'L'); };

        $pdf->SetXY($mL + 2, $y + 2);
        $lbl('Vehículo:'); $val($this->ajustarTexto($vehiculo !== '' ? $vehiculo : '—', 88), 88);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(18, 5, 'Fecha:', 0, 0, 'R');
        $val($fecha, 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Cliente:'); $val($this->ajustarTexto($cliente !== '' ? $cliente : '— (sin cliente) —', 88), 88);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(18, 5, 'Ident.:', 0, 0, 'R');
        $val((string)($orden['cliente_identificacion'] ?? ''), 0, 1);

        $pdf->SetXY($mL + 2, $pdf->GetY());
        $lbl('Kilometraje:'); $val($km !== '' ? $km : '—', 60);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(22, 5, 'Combustible:', 0, 0, 'L');
        $val($comb !== '' ? $comb : '—', 28);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(20, 5, 'Próx. cita:', 0, 0, 'R');
        $val($proxCita !== '' ? $proxCita : '—', 0, 1);

        return $y + $boxH;
    }

    // ─── Tabla de servicios / productos (diseño del RIDE de factura) ──────────
    // Mismas columnas, anchos, tipografía, colores y salto de página que
    // FacturaVentaPdfService::dibujarDetalle().
    private function dibujarTablaDetalle(array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        // Filas en el formato del RIDE: código, base sin impuesto y "Detalle Adicional"
        // con lote / caducidad / NUP (como los imprime la factura).
        $filas = [];
        foreach ($detalles as $d) {
            $extra = array_filter([
                !empty($d['lote']) && $d['lote'] !== 'sin_lote' ? 'Lote: ' . $d['lote'] : '',
                !empty($d['fecha_caducidad']) ? 'Cad: ' . date('d-m-Y', strtotime((string) $d['fecha_caducidad'])) : '',
                !empty($d['nup']) ? 'NUP: ' . $d['nup'] : '',
            ]);
            $filas[] = [
                'codigo_principal' => (string) ($d['producto_codigo'] ?? ''),
                'codigo_auxiliar'  => (string) ($d['producto_codigo_auxiliar'] ?? ''),
                'cantidad'         => (float) ($d['cantidad'] ?? 0),
                'descripcion'      => (string) ($d['descripcion'] ?? ''),
                'detalle'          => implode(' | ', $extra),
                'precio_unitario'  => (float) ($d['precio_unitario'] ?? 0),
                'descuento'        => (float) ($d['descuento'] ?? 0),
                'precio_total'     => round((float) ($d['total_linea'] ?? 0) - (float) ($d['valor_iva'] ?? 0), 2),
            ];
        }

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
        // Como la factura: "Cód. Auxiliar" y "Detalle Adicional" solo si algún ítem los tiene.
        if (!array_filter($filas, fn($f) => trim($f['codigo_auxiliar']) !== '')) {
            $cols = array_values(array_filter($cols, fn($c) => $c['key'] !== 'coda'));
        }
        if (!array_filter($filas, fn($f) => trim($f['detalle']) !== '')) {
            $cols = array_values(array_filter($cols, fn($c) => $c['key'] !== 'deta'));
        }

        // Ancho de los códigos según su contenido (acotado), como en la factura.
        $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
        $campoCod = ['codp' => 'codigo_principal', 'coda' => 'codigo_auxiliar'];
        $maxCod   = ['codp' => 42.0, 'coda' => 26.0];
        foreach ($cols as &$c) {
            if (!isset($campoCod[$c['key']])) continue;
            $wTexto = 0.0;
            foreach ($filas as $f) {
                $txt = trim($f[$campoCod[$c['key']]]);
                if ($txt !== '') $wTexto = max($wTexto, $pdf->GetStringWidth($txt));
            }
            $c['w'] = round(max((float) $c['w'], min($maxCod[$c['key']], $wTexto + 2.0)), 1);
        }
        unset($c);
        // La Descripción absorbe la diferencia para sumar exactamente contentW.
        $sumaW = array_sum(array_column($cols, 'w'));
        foreach ($cols as &$c) {
            if ($c['key'] === 'desc') { $c['w'] = max(28.0, $c['w'] + ($cW - $sumaW)); break; }
        }
        unset($c);

        $hdrH = 9.8;
        $dibujarEncabezado = function (float $yEnc) use ($pdf, $cols, $mL, $hdrH): float {
            $pdf->SetFont('helvetica', 'B', self::FUENTE_ENCABEZADO_TABLA);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.2);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($mL, $yEnc);
            foreach ($cols as $col) {
                $pdf->MultiCell($col['w'], $hdrH, $col['titulo'], 1, 'C', true, 0, '', '', true, 0, false, true, $hdrH, 'M');
            }
            $pdf->Ln();
            return $yEnc + $hdrH;
        };
        $dibujarEncabezado($y);

        $wDesc = 0.0; $wDeta = 0.0;
        foreach ($cols as $c) {
            if ($c['key'] === 'desc') $wDesc = (float) $c['w'];
            if ($c['key'] === 'deta') $wDeta = (float) $c['w'];
        }

        $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
        if (empty($filas)) {
            $pdf->SetX($mL);
            $pdf->Cell($cW, self::ALTO_MIN_FILA, 'Sin servicios ni productos.', 1, 1, 'C');
        }
        $alt = false;
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();
        foreach ($filas as $f) {
            $bg = $alt ? [250, 250, 250] : [255, 255, 255];
            $alt = !$alt;
            $pdf->SetFillColor(...$bg);

            $vals = [
                'codp' => $f['codigo_principal'],
                'coda' => $f['codigo_auxiliar'],
                'cant' => number_format($f['cantidad'], $this->decCantidad),
                'desc' => $f['descripcion'],
                'deta' => $f['detalle'],
                'pu'   => number_format($f['precio_unitario'], $this->decPrecio),
                'dcto' => number_format($f['descuento'], 2),
                'ptot' => number_format($f['precio_total'], 2),
            ];
            $nDesc = $wDesc > 0 ? max(1, $pdf->getNumLines($vals['desc'], $wDesc)) : 1;
            $nDeta = $wDeta > 0 ? max(1, $pdf->getNumLines($vals['deta'], $wDeta)) : 1;
            $ch    = max(self::ALTO_MIN_FILA, max($nDesc, $nDeta) * self::LINEA_CUERPO);

            $yRow = $pdf->GetY();
            // Salto de página controlado + encabezado repetido (como la factura).
            if ($yRow + $ch > $limiteY) {
                $pdf->AddPage();
                $yRow = $dibujarEncabezado($pdf->GetY());
                $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
                $pdf->SetFillColor(...$bg);
            }
            $xCur = $mL;
            foreach ($cols as $col) {
                $val = $vals[$col['key']];
                $pdf->SetXY($xCur, $yRow);
                if ($col['key'] === 'desc' || $col['key'] === 'deta') {
                    $pdf->MultiCell($col['w'], $ch, $val, 1, $col['align'], true, 0, '', '', true, 0, false, true, 0, 'M');
                } elseif ($col['key'] === 'codp' || $col['key'] === 'coda') {
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

    // ─── Pie: Información Adicional (izquierda) + totales (derecha) ───────────
    // Mismo diseño que FacturaVentaPdfService::dibujarPie(). Devuelve la Y más baja.
    private function dibujarPie(array $orden, array $empresa, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $cW  = $this->contentW;

        // Bases e IVA por tarifa (código SRI), separando no objeto (6) y exento (7).
        $subtotMap = []; $ivaMap = []; $tarifaMap = [];
        $noObjIva = 0.0; $exentoIva = 0.0;
        foreach (($orden['detalles'] ?? []) as $d) {
            $base = round((float) ($d['total_linea'] ?? 0) - (float) ($d['valor_iva'] ?? 0), 2);
            $tar  = (float) ($d['porcentaje_iva'] ?? 0);
            $iva  = (float) ($d['valor_iva'] ?? 0);
            $cod  = $tar > 0 ? \App\Helpers\SriIvaHelper::codigoPorcentaje($tar) : (string) ($d['tarifa_codigo'] ?? '0');
            if ($cod === '6') { $noObjIva += $base; continue; }
            if ($cod === '7') { $exentoIva += $base; continue; }
            $subtotMap[$cod] = ($subtotMap[$cod] ?? 0.0) + $base;
            $ivaMap[$cod]    = ($ivaMap[$cod] ?? 0.0) + $iva;
            $tarifaMap[$cod] = $tar;
        }
        ksort($subtotMap); ksort($ivaMap);

        // Las cifras de la cabecera mandan (son las que pasan al documento de venta).
        $subtotalSinImp = (float) ($orden['subtotal'] ?? (array_sum($subtotMap) + $noObjIva + $exentoIva));
        $totalDcto      = (float) ($orden['descuento'] ?? 0);
        $total          = (float) ($orden['total'] ?? ($subtotalSinImp + array_sum($ivaMap)));
        // Si el IVA sumado por línea difiere en centavos del total, se absorbe en el grupo
        // de mayor IVA para que SUBTOTAL + IVA cuadre con VALOR TOTAL (igual que el RIDE).
        if (!empty($ivaMap)) {
            $desfase = round(round($total - $subtotalSinImp, 2) - array_sum($ivaMap), 2);
            if (abs($desfase) >= 0.01 && abs($desfase) <= 0.05) {
                $kMax = array_keys($ivaMap, max($ivaMap))[0];
                $ivaMap[$kMax] = round($ivaMap[$kMax] + $desfase, 2);
            }
        }

        if ($y > 212) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }

        $totW = 74;
        $izqW = $cW - $totW - 2;
        $totX = $mL + $izqW + 2;
        $lh   = 5;
        $lblW = 52;
        $valW = $totW - $lblW;
        $paginaPie = $pdf->getPage();

        // ── Derecha: totales ──
        $yTot = $y;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);
        $etq = fn(float $t) => $t == (int) $t ? (string) (int) $t : number_format($t, 2);
        foreach ($subtotMap as $cod => $base) {
            $this->filaTotales($totX, $yTot, $lblW, $valW, $lh, 'SUBTOTAL ' . $etq($tarifaMap[$cod] ?? 0) . '%', $base);
            $yTot += $lh;
        }
        foreach ([['SUBTOTAL NO OBJETO DE IVA', $noObjIva], ['SUBTOTAL EXENTO DE IVA', $exentoIva],
                  ['SUBTOTAL SIN IMPUESTOS', $subtotalSinImp], ['TOTAL DESCUENTO', $totalDcto], ['ICE', 0.0]] as [$l, $v]) {
            $this->filaTotales($totX, $yTot, $lblW, $valW, $lh, $l, $v);
            $yTot += $lh;
        }
        foreach ($ivaMap as $cod => $iva) {
            $this->filaTotales($totX, $yTot, $lblW, $valW, $lh, 'IVA ' . $etq($tarifaMap[$cod] ?? 0) . '%', $iva);
            $yTot += $lh;
        }
        $pdf->SetFont('helvetica', 'B', self::FUENTE_CUERPO);
        $pdf->SetFillColor(210, 210, 210);
        $pdf->SetXY($totX, $yTot);
        $pdf->Cell($lblW, $lh, 'VALOR TOTAL', 1, 0, 'L', true);
        $pdf->Cell($valW, $lh, number_format($total, 2), 1, 1, 'R', true, '', 1);
        $yTot += $lh;

        // ── Izquierda: Información Adicional ──
        $yIzq    = $y;
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();
        $info = array_values(array_filter($orden['info_adicional'] ?? [],
            fn($i) => trim((string) ($i['nombre'] ?? '')) !== '' && trim((string) ($i['valor'] ?? '')) !== ''));
        if (!empty($info)) {
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
            foreach ($info as $i) {
                $nombre = (string) $i['nombre'];
                $valor  = (string) $i['valor'];
                $pdf->SetFont('helvetica', 'B', self::FUENTE_CUERPO);
                $nNom = max(1, $pdf->getNumLines($nombre, $etiqW));
                $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
                $nVal = max(1, $pdf->getNumLines($valor, $valIW));
                $h    = max(self::ALTO_MIN_FILA, max($nNom, $nVal) * self::LINEA_CUERPO);
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

        // Observaciones (recuadro como en factura; p. ej. las órdenes migradas).
        $obs = trim((string) ($orden['observaciones'] ?? ''));
        if ($obs !== '') {
            $yIzq += 1;
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            if ($yIzq + $lh + min(max(1, $pdf->getNumLines($obs, $izqW)), 2) * self::LINEA_CUERPO > $limiteY) {
                $pdf->AddPage();
                $yIzq = $pdf->GetY();
            }
            $pdf->SetFont('helvetica', 'B', self::FUENTE_ENCABEZADO_TABLA);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetXY($mL, $yIzq);
            $pdf->Cell($izqW, $lh, 'Observaciones', 1, 1, 'C', true);
            $yIzq += $lh;
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $pdf->SetXY($mL, $yIzq);
            $pdf->MultiCell($izqW, self::LINEA_CUERPO, $obs, 1, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        $yFinal = $pdf->getPage() > $paginaPie ? $yIzq : max($yIzq, $yTot);

        // Leyenda personalizada del PDF (Empresa), igual que la factura.
        $leyendaTitulo  = (string) ($empresa['leyenda_pdf_titulo'] ?? '');
        $leyendaMensaje = (string) ($empresa['leyenda_pdf_mensaje'] ?? '');
        if ($leyendaTitulo !== '' || $leyendaMensaje !== '') {
            $yFinal += 4;
            if ($yFinal + 30 > $limiteY) {
                $pdf->AddPage();
                $yFinal = $pdf->GetY() + 4;
            }
            if ($leyendaTitulo !== '') {
                $pdf->SetFont('helvetica', 'B', 7.5);
                $pdf->SetFillColor(230, 230, 230);
                $pdf->SetXY($mL, $yFinal);
                $pdf->Cell($cW, $lh, mb_strtoupper($leyendaTitulo, 'UTF-8'), 1, 1, 'C', true);
                $yFinal += $lh;
            }
            if ($leyendaMensaje !== '') {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetXY($mL, $yFinal);
                $pdf->MultiCell($cW, 4.5, $leyendaMensaje, 1, 'L', false, 1);
                $yFinal = $pdf->GetY();
            }
        }

        return $yFinal;
    }

    private function filaTotales(float $x, float $y, float $lblW, float $valW, float $h, string $lbl, float $val): void
    {
        $pdf = $this->pdf;
        $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($x, $y);
        $pdf->Cell($lblW, $h, $lbl, 1, 0, 'L', false, '', 1);
        $pdf->Cell($valW, $h, number_format($val, 2), 1, 0, 'R', false, '', 1);
    }

    // ─── ACTA DE INGRESO DEL VEHÍCULO ─────────────────────────────────────────
    /**
     * PDF aparte que deja constancia de CÓMO INGRESA el vehículo al taller: datos del
     * vehículo y del cliente, condiciones de ingreso (texto con formato de la pestaña
     * "Condiciones de ingreso"), novedades (Info. Adicional) y los servicios que se
     * espera realizar, con firmas de recepción y entrega. Se imprime y se envía por
     * correo desde la orden. No muestra información tributaria (no es un comprobante).
     *
     * @param string $outputDest 'I' inline, 'D' descarga, 'S' string
     */
    public function generarIngreso(array $orden, array $empresa, string $outputDest = 'D')
    {
        $orden   = \App\Helpers\TextoUnicodeHelper::nfcArray($orden);
        $empresa = \App\Helpers\TextoUnicodeHelper::nfcArray($empresa);
        $this->decCantidad = max(0, min(6, (int) ($empresa['decimales_cantidad'] ?? 2)));

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor($empresa['nombre'] ?? '');
        $this->pdf->SetTitle('Acta de ingreso ' . ($orden['numero_orden'] ?? ''));
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();

        $y = $this->dibujarEncabezado($empresa, $orden, 'ACTA DE INGRESO', 'DEL VEHÍCULO');
        $y = $this->ingresoDatosVehiculo($orden, $y + 3);
        $y = $this->ingresoDatosCliente($orden, $y + 2);
        $y = $this->ingresoCondiciones($orden, $y + 2);
        $y = $this->ingresoNovedades($orden, $y + 2);
        $y = $this->ingresoServicios($orden, $y + 2);
        $y = $this->ingresoDeclaracion($orden, $y + 3);
        $this->dibujarFirmas($orden, $y + 2, ['Recibido por (taller)', 'Entrega el vehículo (cliente)']);

        $nombre = 'Acta_Ingreso_' . (($orden['numero_orden'] ?? '') !== '' ? $orden['numero_orden'] : 'orden') . '.pdf';
        if ($outputDest === 'S') {
            return $this->pdf->Output($nombre, 'S');
        }
        $this->pdf->Output($nombre, $outputDest);
    }

    /** Barra de título de sección (mismo estilo que los títulos del RIDE de factura). */
    private function tituloSeccion(string $titulo, float $y): float
    {
        $pdf = $this->pdf;
        if ($y + 16 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }
        $pdf->SetFont('helvetica', 'B', self::FUENTE_ENCABEZADO_TABLA);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($this->marginL, $y);
        $pdf->Cell($this->contentW, 5, $titulo, 1, 1, 'L', true);
        return $y + 5;
    }

    /** Pares etiqueta/valor en N columnas con borde (datos del vehículo / cliente). */
    private function grillaDatos(array $pares, float $y, int $columnas = 2): float
    {
        $pdf  = $this->pdf;
        $colW = $this->contentW / $columnas;
        $etqW = 30;
        $h    = 5.5;
        $pares = array_values($pares);
        for ($i = 0; $i < count($pares); $i += $columnas) {
            $x = $this->marginL;
            for ($c = 0; $c < $columnas; $c++) {
                [$etq, $val] = $pares[$i + $c] ?? ['', ''];
                $pdf->SetXY($x, $y);
                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->Cell($etqW, $h, $etq, 'LTB', 0, 'L');
                $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
                $pdf->Cell($colW - $etqW, $h, $this->ajustarTexto($val !== '' ? $val : '—', $colW - $etqW - 2), 'RTB', 0, 'L');
                $x += $colW;
            }
            $y += $h;
        }
        return $y;
    }

    private function ingresoDatosVehiculo(array $o, float $y): float
    {
        $fecha = !empty($o['fecha_ingreso']) ? date('d-m-Y H:i:s', strtotime((string) $o['fecha_ingreso'])) : '';
        $km    = ($o['kilometraje'] ?? null) !== null && $o['kilometraje'] !== '' ? number_format((float) $o['kilometraje'], 0, ',', '.') . ' km' : '';
        $comb  = ['E' => 'Vacío (E)', '1/4' => '1/4', '1/2' => '1/2', '3/4' => '3/4', 'F' => 'Lleno (F)'][(string) ($o['nivel_combustible'] ?? '')] ?? '';
        $y = $this->tituloSeccion('DATOS DEL VEHÍCULO', $y);
        return $this->grillaDatos([
            ['Placa',          (string) ($o['placa'] ?: ($o['vehiculo_placa'] ?? ''))],
            ['Fecha ingreso',  $fecha],
            ['Marca',          (string) ($o['marca'] ?: ($o['vehiculo_marca'] ?? ''))],
            ['Modelo',         (string) ($o['modelo'] ?: ($o['vehiculo_modelo'] ?? ''))],
            ['Año',            (string) ($o['vehiculo_anio'] ?? '')],
            ['Color',          (string) ($o['vehiculo_color'] ?? '')],
            ['Chasis',         (string) ($o['vehiculo_chasis'] ?? '')],
            ['Motor',          (string) ($o['vehiculo_motor'] ?? '')],
            ['Kilometraje',    $km],
            ['Combustible',    $comb],
        ], $y);
    }

    private function ingresoDatosCliente(array $o, float $y): float
    {
        $y = $this->tituloSeccion('DATOS DEL CLIENTE', $y);
        return $this->grillaDatos([
            ['Cliente',        (string) ($o['cliente_nombre'] ?? '')],
            ['Identificación', (string) ($o['cliente_identificacion'] ?? '')],
            ['Teléfono',       (string) ($o['cliente_telefono'] ?? '')],
            ['Correo',         (string) ($o['cliente_email'] ?? '')],
        ], $y);
    }

    /** Condiciones de ingreso: texto con formato del editor (como las condiciones de la proforma). */
    private function ingresoCondiciones(array $o, float $y): float
    {
        $pdf  = $this->pdf;
        $html = trim((string) ($o['condiciones_html'] ?? ''));
        $y = $this->tituloSeccion('CONDICIONES DE INGRESO DEL VEHÍCULO', $y);
        $pdf->SetXY($this->marginL, $y + 1);
        $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
        if ($html === '' || trim(html_entity_decode(strip_tags($html))) === '') {
            $pdf->SetTextColor(110, 116, 124);
            $pdf->MultiCell($this->contentW, self::LINEA_CUERPO, 'No se registraron condiciones de ingreso.', 0, 'L', false, 1);
            $pdf->SetTextColor(0, 0, 0);
        } else {
            $pdf->writeHTMLCell($this->contentW, 0, $this->marginL, $y + 1, ProformaCondicionesPdfService::htmlParaTcpdf($html), 0, 1, false, true, 'L', true);
        }
        $yFin = $pdf->GetY() + 1;
        // Recuadro alrededor del bloque (solo si quedó en la misma página).
        if ($yFin > $y) {
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($this->marginL, $y, $this->contentW, $yFin - $y);
        }
        return $yFin;
    }

    /** Novedades y demás información adicional de la orden (sin el correo del cliente). */
    private function ingresoNovedades(array $o, float $y): float
    {
        $info = array_values(array_filter($o['info_adicional'] ?? [], fn($i) =>
            trim((string) ($i['nombre'] ?? '')) !== '' && trim((string) ($i['valor'] ?? '')) !== ''
            && strcasecmp(trim((string) $i['nombre']), 'Correo del cliente') !== 0));
        foreach (($o['novedades'] ?? []) as $n) {
            if (trim((string) ($n['descripcion'] ?? '')) !== '') $info[] = ['nombre' => 'Novedad', 'valor' => $n['descripcion']];
        }
        if (!$info) return $y;

        $pdf = $this->pdf;
        $y = $this->tituloSeccion('NOVEDADES / OBSERVACIONES', $y);
        $etiqW = 50; $valW = $this->contentW - $etiqW;
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();
        foreach ($info as $i) {
            $pdf->SetFont('helvetica', 'B', self::FUENTE_CUERPO);
            $nNom = max(1, $pdf->getNumLines((string) $i['nombre'], $etiqW));
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $nVal = max(1, $pdf->getNumLines((string) $i['valor'], $valW));
            $h = max(self::ALTO_MIN_FILA, max($nNom, $nVal) * self::LINEA_CUERPO);
            if ($y + $h > $limiteY) { $pdf->AddPage(); $y = $pdf->GetY(); }
            $pdf->SetFont('helvetica', 'B', self::FUENTE_CUERPO);
            $pdf->SetXY($this->marginL, $y);
            $pdf->MultiCell($etiqW, $h, (string) $i['nombre'], 1, 'L', false, 0, '', '', true, 0, false, true, $h, 'M');
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $pdf->MultiCell($valW, $h, (string) $i['valor'], 1, 'L', false, 1, '', '', true, 0, false, true, $h, 'M');
            $y += $h;
        }
        return $y;
    }

    /** Servicios y productos que se espera realizar, con el valor estimado (incluye IVA). */
    private function ingresoServicios(array $o, float $y): float
    {
        $pdf = $this->pdf;
        $det = array_values(array_filter($o['detalles'] ?? [], fn($d) => (float) ($d['cantidad'] ?? 0) > 0));
        $y = $this->tituloSeccion('SERVICIOS Y PRODUCTOS SOLICITADOS', $y);
        $wN = 10; $wCod = 26; $wCant = 22; $wVal = 28;
        $wDesc = $this->contentW - $wN - $wCod - $wCant - $wVal;
        $enc = function (float $yE) use ($pdf, $wN, $wCod, $wDesc, $wCant, $wVal): float {
            $pdf->SetFont('helvetica', 'B', self::FUENTE_ENCABEZADO_TABLA);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetXY($this->marginL, $yE);
            foreach ([[$wN, '#'], [$wCod, 'Código'], [$wDesc, 'Descripción'], [$wCant, 'Cantidad'], [$wVal, 'Valor estimado']] as [$w, $t]) {
                $pdf->Cell($w, 5, $t, 1, 0, 'C', true);
            }
            return $yE + 5;
        };
        $y = $enc($y);
        if (!$det) {
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $pdf->SetXY($this->marginL, $y);
            $pdf->Cell($this->contentW, self::ALTO_MIN_FILA, 'Sin servicios ni productos registrados.', 1, 1, 'C');
            return $y + self::ALTO_MIN_FILA;
        }
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();
        $total = 0.0;
        foreach ($det as $k => $d) {
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $desc = (string) ($d['descripcion'] ?? '');
            $h = max(self::ALTO_MIN_FILA, max(1, $pdf->getNumLines($desc, $wDesc)) * self::LINEA_CUERPO);
            if ($y + $h > $limiteY) { $pdf->AddPage(); $y = $enc($pdf->GetY()); $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO); }
            $pdf->SetXY($this->marginL, $y);
            $pdf->Cell($wN, $h, (string) ($k + 1), 1, 0, 'C');
            $pdf->Cell($wCod, $h, (string) ($d['producto_codigo'] ?? ''), 1, 0, 'L', false, '', 1);
            $pdf->MultiCell($wDesc, $h, $desc, 1, 'L', false, 0, '', '', true, 0, false, true, $h, 'M');
            $pdf->Cell($wCant, $h, number_format((float) $d['cantidad'], $this->decCantidad), 1, 0, 'R');
            $pdf->Cell($wVal, $h, number_format((float) ($d['total_linea'] ?? 0), 2), 1, 1, 'R');
            $total += (float) ($d['total_linea'] ?? 0);
            $y += $h;
        }
        $totalOrden = (float) ($o['total'] ?? $total);
        $pdf->SetFont('helvetica', 'B', self::FUENTE_CUERPO);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetXY($this->marginL + $this->contentW - $wVal - 52, $y);
        $pdf->Cell(52, 5, 'TOTAL ESTIMADO (incluye IVA)', 1, 0, 'L', true);
        $pdf->Cell($wVal, 5, number_format($totalOrden, 2), 1, 1, 'R', true);
        $y += 5;

        if (!empty($o['proxima_cita'])) {
            $pdf->SetFont('helvetica', '', self::FUENTE_CUERPO);
            $pdf->SetXY($this->marginL, $y + 1);
            $pdf->Cell($this->contentW, 5, 'Próxima cita sugerida: ' . date('d-m-Y', strtotime((string) $o['proxima_cita'])), 0, 1, 'L');
            $y += 6;
        }
        return $y;
    }

    private function ingresoDeclaracion(array $o, float $y): float
    {
        $pdf = $this->pdf;
        $txt = 'Con su firma, el cliente confirma que el vehículo ingresa en las condiciones descritas en este '
             . 'documento y solicita los servicios detallados. El valor es referencial: el valor final es el de la '
             . 'factura o recibo que se emita al entregar el vehículo.';
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY($this->marginL, $y);
        $pdf->MultiCell($this->contentW, 4, $txt, 0, 'J', false, 1);
        $pdf->SetTextColor(0, 0, 0);
        return $pdf->GetY();
    }

    // ─── Firmas ───────────────────────────────────────────────────────────────
    private function dibujarFirmas(array $orden, float $y, array $etiquetas = ['Entregado por', 'Cliente / Recibí conforme']): void
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;
        $colW = $this->contentW / 2;

        $yLinea = $y + 20;
        if ($yLinea > 272) {
            $pdf->AddPage();
            $yLinea = $pdf->GetY() + 20;
        }

        $firmas = [
            [$etiquetas[0], ''],
            [$etiquetas[1], trim((string)($orden['cliente_nombre'] ?? ''))],
        ];

        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 10, $yLinea, $x + $colW - 10, $yLinea);
        }
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) { $pdf->Cell($colW, 4, $f[0], 0, 0, 'C'); }

        $pdf->SetFont('helvetica', '', 7.5);
        $yName = $yLinea + 5;
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->SetXY($x + 3, $yName);
            $pdf->MultiCell($colW - 6, 3.4, $f[1] !== '' ? $f[1] : ' ', 0, 'C', false, 0, '', '', true, 0, false, true, 0, 'T');
        }
    }

    // ─── Helpers (reutilizados del comprobante de caja) ───────────────────────
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
