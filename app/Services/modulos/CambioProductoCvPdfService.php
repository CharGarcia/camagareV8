<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Modelo general (por defecto) del PDF de un Cambio de productos.
 *
 * A4 vertical. Mismo ENCABEZADO del comprobante de caja (Ingresos/Egresos).
 * Cuerpo: datos del cliente, tabla de "Productos que devuelve" y tabla de
 * "Productos que entrega a cambio" (por unidad: origen, lote, NUP, bodega y cantidad,
 * sin precios ni totales), observaciones/motivo y dos firmas (Realizado por / Recibido por).
 *
 * Cuando la empresa tenga una plantilla activa (tipo 'cambio_producto_cv') se
 * usa PlantillasPdfRendererService; este es el respaldo estándar.
 */
class CambioProductoCvPdfService
{
    private TCPDF $pdf;

    private float $marginL  = 12;
    private float $marginR  = 12;
    private float $contentW = 186; // 210 - 12 - 12

    public function generar(array $cabecera, array $detalles, array $empresa, string $outputDest = 'I')
    {
        $numero = trim(((string)($cabecera['serie'] ?? '')) . '-' . ((string)($cabecera['secuencial'] ?? '')), '-');

        // Origen de cada línea (factura de consignación / cambio / consignación / bodega): el cambio se hace
        // por unidad y NUP, así que el documento dice de dónde sale cada una.
        foreach ($detalles as &$d) { $d['origen_label'] = self::etiquetaOrigen($d); }
        unset($d);

        $devoluciones = array_values(array_filter($detalles, fn($d) => ($d['tipo_linea'] ?? '') === 'devolucion'));
        $entregas     = array_values(array_filter($detalles, fn($d) => ($d['tipo_linea'] ?? '') === 'entrega'));

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('Sistema');
        $this->pdf->SetAuthor((string)($empresa['nombre'] ?? ''));
        $this->pdf->SetTitle('Cambio de productos ' . $numero);
        $this->pdf->SetMargins($this->marginL, 10, $this->marginR);
        $this->pdf->SetAutoPageBreak(true, 15);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 9);

        $y = $this->dibujarEncabezado($empresa, $numero, (string)($cabecera['estado'] ?? 'Emitida'));
        $y = $this->dibujarDatosCliente($cabecera, $y + 3);
        $y = $this->dibujarTablaDetalle('Productos que devuelve', $devoluciones, $y + 3);
        $y = $this->dibujarTablaDetalle('Productos que entrega a cambio', $entregas, $y + 3);
        $y = $this->dibujarObservacionesMotivo($cabecera, $y + 4);
        $this->dibujarFirmas($cabecera, $y);

        $nombre = 'Cambio_' . ($numero !== '' ? $numero : 'comprobante') . '.pdf';
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

        $logoPath = $this->resolverLogo($empresa);
        $textoX   = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 24, 0, '', '', 'T', false, 300);
            $textoX = $mL + 27;
        }

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

        $boxH = 30;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->RoundedRect($derX, $y0, $derW, $boxH, 1.5, '1111', 'D');

        $pdf->SetXY($derX, $y0 + 2);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell($derW, 5, 'CAMBIO DE PRODUCTOS', 0, 1, 'C');

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
        $par('Cliente:', (string)($c['cliente_nombre'] ?? '—'), 'Fecha cambio:', $fmtFecha($c['fecha_cambio'] ?? ''));
        $par('Identificación:', (string)($c['cliente_identificacion'] ?? ''), 'Realizado por:', (string)($c['usuario_nombre'] ?? '—'));
        $par('Dirección:', (string)($c['cliente_direccion'] ?? ''), 'N.° documento:', (string)(($c['serie'] ?? '') . '-' . ($c['secuencial'] ?? '')));

        return $y + $boxH;
    }

    /**
     * Etiqueta del origen de una línea del cambio (también la usa el Excel):
     *  - lo que ENTRA: "Factura 001-001-000000123", la factura de venta afectada, también cuando la
     *    unidad llegó en un cambio anterior (factura_afectada, CambioProductoCvService::getDetalleCompleto);
     *    si no se encuentra, "Cambio 001-001-000000004"; "Sin factura" si no hay factura de venta
     *    que mostrar;
     *  - lo que SALE: "Consignación 001-001-000000012" (consignación de la que se tomó) o "Bodega"
     *    (existencias / catálogo).
     */
    public static function etiquetaOrigen(array $d): string
    {
        $factura = trim((string)($d['factura_afectada'] ?? ''));
        $tipo    = strtoupper((string)($d['origen_tipo'] ?? ''));
        $num     = trim((string)($d['origen_numero'] ?? ''));
        if (($d['tipo_linea'] ?? '') === 'devolucion') {
            if ($factura !== '') {
                return 'Factura ' . $factura;
            }
            // Sin factura de venta que mostrar: devolución migrada del sistema anterior (no guarda de
            // qué factura vino) o de una factura de consignación sin factura de venta enlazada.
            if ($tipo === '' || ($tipo === 'FACTURA' && $num === '')) {
                return 'Sin factura';
            }
        }
        $nombres = ['FACTURA' => 'Factura', 'CAMBIO' => 'Cambio', 'CONSIGNACION' => 'Consignación'];
        if (!isset($nombres[$tipo])) {
            return 'Bodega';
        }
        return trim($nombres[$tipo] . ' ' . $num);
    }

    /**
     * Tabla: Origen | Código | Descripción | Lote | NUP | Bodega | Cant. Sin precios ni
     * totales, igual que el modal: el cambio es por unidad y la bodega dice de dónde viene
     * (devolución) o de dónde sale (entrega) cada una.
     */
    private function dibujarTablaDetalle(string $titulo, array $detalles, float $y): float
    {
        $pdf = $this->pdf;
        $mL  = $this->marginL;

        // El título de la tabla no se queda solo al pie de una página: necesita sitio
        // para él, la fila de encabezados y al menos una línea de producto.
        if ($y + 16 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }

        $pdf->SetXY($mL, $y);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Cell($this->contentW, 5, $titulo, 0, 1, 'L');
        $y = $pdf->GetY();

        $cols = [
            ['t' => 'Origen',      'w' => 38, 'a' => 'L', 'k' => 'origen_label'],
            ['t' => 'Código',      'w' => 20, 'a' => 'L', 'k' => 'producto_codigo'],
            ['t' => 'Descripción', 'w' => 0,  'a' => 'L', 'k' => 'producto_nombre'],
            ['t' => 'Lote',        'w' => 20, 'a' => 'L', 'k' => 'lote'],
            ['t' => 'NUP',         'w' => 24, 'a' => 'L', 'k' => 'nup'],
            ['t' => 'Bodega',      'w' => 30, 'a' => 'L', 'k' => 'bodega_nombre'],
            ['t' => 'Cant.',       'w' => 14, 'a' => 'R', 'k' => 'cantidad'],
        ];

        $fixed = 0.0;
        foreach ($cols as $c) { $fixed += $c['w']; }
        $flex = max(30.0, $this->contentW - $fixed);
        foreach ($cols as &$c) { if ($c['w'] === 0) { $c['w'] = $flex; } }
        unset($c);
        $descIdx = 2;

        // Encabezado de la tabla. Se encapsula porque hay que repetirlo al inicio de
        // cada página cuando el detalle no cabe en una sola.
        $dibujarCabeceraTabla = function (float $yEnc) use ($pdf, $cols, $mL): float {
            $pdf->SetXY($mL, $yEnc);
            $pdf->SetFont('helvetica', 'B', 7.5);
            $pdf->SetFillColor(60, 70, 90);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetDrawColor(60, 70, 90);
            $pdf->SetLineWidth(0.2);
            foreach ($cols as $c) {
                $pdf->Cell($c['w'], 6, $c['t'], 1, 0, 'C', true);
            }
            $pdf->Ln();
            // Deja el lápiz listo para las filas (la fuente también la usa getNumLines).
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(0, 0, 0);
            return $yEnc + 6;
        };

        $dibujarCabeceraTabla($y);

        if (empty($detalles)) {
            $pdf->SetX($mL);
            $pdf->Cell($this->contentW, 6, 'Sin productos.', 1, 1, 'C');
            return $pdf->GetY();
        }

        // Alto útil de la página: por debajo de esta Y ya no cabe una fila entera.
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();

        $alt = false;
        foreach ($detalles as $d) {
            $bg = $alt ? [245, 247, 250] : [255, 255, 255];
            $alt = !$alt;

            $vals = [];
            foreach ($cols as $c) {
                $raw = $d[$c['k']] ?? '';
                if ($c['k'] === 'cantidad') {
                    $vals[] = number_format((float)$raw, 2);
                } else {
                    $vals[] = trim((string)$raw) !== '' ? (string)$raw : '—';
                }
            }

            // Altura según la descripción. getNumLines() da el número real de renglones
            // con la fuente y el ancho de la celda (la estimación por GetStringWidth se
            // quedaba corta y recortaba descripciones largas).
            $descW = $cols[$descIdx]['w'];
            $nLin  = max(1, $pdf->getNumLines((string)$vals[$descIdx], $descW));
            $h     = max(5.0, $nLin * 4.2);

            $yRow = $pdf->GetY();

            // Salto de página CONTROLADO. Sin esto, al pasarse del alto útil cada
            // SetXY() con una Y fuera de página dispara el salto automático de TCPDF y,
            // como aquí se hace un SetXY por COLUMNA, un cambio con muchas líneas
            // acababa generando una página casi vacía por celda (20 devueltas + 20
            // entregadas producían 12 páginas).
            if ($yRow + $h > $limiteY) {
                $pdf->AddPage();
                $yRow = $dibujarCabeceraTabla($pdf->GetY());
            }
            $pdf->SetFillColor(...$bg);

            $x = $mL;
            foreach ($cols as $i => $c) {
                $pdf->SetXY($x, $yRow);
                if ($i === $descIdx) {
                    $pdf->MultiCell($c['w'], $h, $vals[$i], 1, $c['a'], true, 0, '', '', true, 0, false, true, $h, 'M');
                } else {
                    // stretch = 1: un lote o código más largo que su columna se condensa
                    // dentro de la celda en vez de desbordarse sobre la siguiente.
                    $pdf->Cell($c['w'], $h, $vals[$i], 1, 0, $c['a'], true, '', 1);
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

        // Motivo y observaciones van juntos: si no caben en lo que queda de página,
        // el bloque entero pasa a la siguiente.
        $pdf->SetFont('helvetica', '', 8.5);
        $hBloque = (max(1, $pdf->getNumLines($motivo !== '' ? $motivo : '—', $w - 24))
                 +  max(1, $pdf->getNumLines($obs !== '' ? $obs : '—', $w - 24))) * 5;
        if ($y + $hBloque > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }

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

        // Alto del bloque: 20 mm hasta la línea de firma más el nombre debajo. Si no
        // cabe entero, las firmas pasan a una hoja nueva; antes se clavaban en 272 mm
        // y terminaban dibujándose encima del listado.
        if ($y + 30 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }
        $yLinea = $y + 20;

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
