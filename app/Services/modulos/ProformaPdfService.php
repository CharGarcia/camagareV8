<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Genera el PDF de una Proforma con un diseño limpio y el logo de la empresa.
 * Reutilizable para descarga, correo y WhatsApp (outputDest 'D' | 'I' | 'S' | 'F').
 */
class ProformaPdfService
{
    /** Color de acento (azul corporativo). */
    private array $accent = [31, 78, 121];
    private array $accentSoft = [223, 232, 244];
    private array $grisLinea = [210, 214, 220];
    private array $grisTexto = [110, 116, 124];

    /** Decimales configurados por la empresa/establecimiento (igual que el RIDE de factura). */
    private int $decCantidad = 2;
    private int $decPrecio   = 2;

    public function generar(array $cabecera, array $detalles, array $adicional, array $empresa, string $outputDest = 'D'): string
    {
        $numero = ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-'
                . str_pad((string) ($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

        // Decimales configurados por la empresa (los mismos que usa la pantalla y el RIDE
        // de Facturas de Venta), acotados a 0..6 igual que el XML.
        $this->decCantidad = max(0, min(6, (int) ($empresa['decimales_cantidad'] ?? 2)));
        $this->decPrecio   = max(0, min(6, (int) ($empresa['decimales_precio']   ?? 2)));

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('CaMaGaRe');
        $pdf->SetAuthor((string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'CaMaGaRe'));
        $pdf->SetTitle('Proforma ' . $numero);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();

        $this->encabezado($pdf, $cabecera, $empresa, $numero);
        $this->cajaCliente($pdf, $cabecera);
        $this->tablaDetalle($pdf, $detalles, $empresa);
        $this->totales($pdf, $cabecera, $detalles);
        $this->pieAdicional($pdf, $cabecera, $adicional);
        $this->pieDocumento($pdf, $empresa);

        return (string) $pdf->Output('Proforma_' . $numero . '.pdf', $outputDest);
    }

    // ── Encabezado: logo + datos empresa (izq) y bloque PROFORMA (der) ──────────
    private function encabezado(TCPDF $pdf, array $cabecera, array $empresa, string $numero): void
    {
        $mL = 14; $w = 182;
        $y0 = 14;
        $izqW = $w * 0.58;
        $derW = $w - $izqW;

        // Alto de la tarjeta PROFORMA (se usa también como alto del logo).
        $boxH = 30;

        // ── Logo: ocupa todo el espacio de la izquierda — a lo ancho hasta donde arranca
        //    la tarjeta PROFORMA, y a lo alto desde el borde superior hasta el nombre de la
        //    empresa. Fitbox 'LM' = contain (no deforma), pegado a la izquierda y centrado
        //    verticalmente dentro de esa caja.
        $logoPath = $this->resolverLogo($empresa);
        $logoW = $izqW - 4; $logoH = $boxH;
        if ($logoPath) {
            $pdf->Image($logoPath, $mL, $y0, $logoW, $logoH, '', '', '', false, 300, '', false, false, 0, 'LM');
        }

        // ── Datos de la empresa (debajo del logo) ──
        $xTexto = $mL;
        $yTexto = $y0 + ($logoPath ? $logoH + 1.5 : 0);
        $pdf->SetXY($xTexto, $yTexto);
        $pdf->SetTextColor(...$this->accent);
        $pdf->SetFont('helvetica', 'B', 12);
        $nombre = (string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? 'Empresa');
        $pdf->MultiCell($izqW, 5, $nombre, 0, 'L', false, 1);

        $pdf->SetTextColor(...$this->grisTexto);
        $pdf->SetFont('helvetica', '', 8);
        if (!empty($empresa['nombre']) && ($empresa['nombre'] !== $nombre)) {
            $pdf->SetX($xTexto);
            $pdf->MultiCell($izqW, 4, (string) $empresa['nombre'], 0, 'L', false, 1);
        }
        if (!empty($empresa['ruc'])) {
            $pdf->SetX($xTexto);
            $pdf->MultiCell($izqW, 4, 'RUC: ' . $empresa['ruc'], 0, 'L', false, 1);
        }
        $dir = (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? '');
        if ($dir !== '') {
            $pdf->SetX($xTexto);
            $pdf->MultiCell($izqW, 4, $dir, 0, 'L', false, 1);
        }
        $contacto = [];
        if (!empty($empresa['telefono'])) $contacto[] = 'Tel: ' . $empresa['telefono'];
        $correoEmp = $empresa['correo'] ?? ($empresa['mail'] ?? ($empresa['email'] ?? ''));
        if (!empty($correoEmp)) $contacto[] = $correoEmp;
        if ($contacto) {
            $pdf->SetX($xTexto);
            $pdf->MultiCell($izqW, 4, implode('  ·  ', $contacto), 0, 'L', false, 1);
        }

        // Fondo real donde termina el bloque de la empresa (se captura ANTES de dibujar
        // el bloque PROFORMA, porque sus Cell con ln=1 reposicionan el cursor Y hacia arriba).
        $yEmpresaBottom = $pdf->GetY();

        // ── Bloque PROFORMA (derecha) ──
        $xDer = $mL + $izqW;
        $pdf->SetFillColor(...$this->accent);
        $pdf->RoundedRect($xDer, $y0, $derW, $boxH, 2.2, '1111', 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetXY($xDer, $y0 + 3);
        $pdf->Cell($derW, 8, 'PROFORMA', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetXY($xDer, $y0 + 12.5);
        $pdf->Cell($derW, 5, 'N.º  ' . $numero, 0, 1, 'C');

        $fecha = date('d-m-Y', strtotime((string) ($cabecera['fecha_emision'] ?? 'now')));
        $pdf->SetXY($xDer, $y0 + 17.5);
        $pdf->Cell($derW, 5, 'Fecha: ' . $fecha, 0, 1, 'C');

        $dias = (int) ($cabecera['dias_vigencia'] ?? 15);
        $vence = date('d-m-Y', strtotime((string) ($cabecera['fecha_emision'] ?? 'now') . " +{$dias} days"));
        $pdf->SetXY($xDer, $y0 + 22.5);
        $pdf->Cell($derW, 5, 'Válida hasta: ' . $vence, 0, 1, 'C');

        // Línea de acento bajo el encabezado: debajo de lo más bajo entre el texto de la
        // empresa y el bloque PROFORMA (así la caja del cliente nunca se solapa).
        $yLinea = max($yEmpresaBottom, $y0 + $boxH) + 2;
        $pdf->SetLineWidth(0.6);
        $pdf->SetDrawColor(...$this->accent);
        $pdf->Line($mL, $yLinea, $mL + $w, $yLinea);
        $pdf->SetY($yLinea + 3);
    }

    // ── Caja del cliente ────────────────────────────────────────────────────────
    private function cajaCliente(TCPDF $pdf, array $cabecera): void
    {
        $mL = 14; $w = 182;
        $y = $pdf->GetY();
        $h = 20;

        $pdf->SetFillColor(...$this->accentSoft);
        $pdf->SetDrawColor(...$this->grisLinea);
        $pdf->SetLineWidth(0.2);
        $pdf->RoundedRect($mL, $y, $w, $h, 1.5, '1111', 'DF');

        $pdf->SetTextColor(...$this->accent);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL + 3, $y + 2);
        $pdf->Cell(30, 4, 'CLIENTE', 0, 0, 'L');

        $pdf->SetTextColor(40, 44, 52);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetXY($mL + 3, $y + 6.5);
        $pdf->Cell($w - 6, 5, (string) ($cabecera['cliente_nombre'] ?? '—'), 0, 1, 'L');

        $pdf->SetTextColor(...$this->grisTexto);
        $pdf->SetFont('helvetica', '', 8);
        $linea2 = [];
        if (!empty($cabecera['cliente_ruc']))   $linea2[] = 'RUC/CI: ' . $cabecera['cliente_ruc'];
        if (!empty($cabecera['cliente_email'])) $linea2[] = $cabecera['cliente_email'];
        $pdf->SetXY($mL + 3, $y + 12);
        $pdf->Cell($w - 6, 4, implode('   ·   ', $linea2), 0, 1, 'L');

        $pdf->SetY($y + $h + 4);
    }

    // ── Tabla de detalle ────────────────────────────────────────────────────────
    private function tablaDetalle(TCPDF $pdf, array $detalles, array $empresa): void
    {
        $mL = 14; $w = 182;
        // Anchos: #, Descripción, Cant, P.Unit, Desc, IVA%, Subtotal
        $cols = [10, 74, 16, 24, 18, 16, 24];

        // Encabezado
        $pdf->SetFillColor(...$this->accent);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetX($mL);
        $headers = ['#', 'DESCRIPCIÓN', 'CANT.', 'P. UNIT.', 'DESC.', 'IVA', 'SUBTOTAL'];
        $aligns  = ['C', 'L', 'C', 'R', 'R', 'C', 'R'];
        foreach ($headers as $i => $htxt) {
            $pdf->Cell($cols[$i], 7, $htxt, 0, ($i === count($headers) - 1 ? 1 : 0), $aligns[$i], true);
        }

        // Filas
        $pdf->SetFont('helvetica', '', 8);
        $fill = false;
        foreach ($detalles as $i => $d) {
            // Cantidad y precio con los decimales configurados por la empresa (los mismos
            // que muestra el modal); el subtotal es el valor guardado de la línea, no se
            // recalcula, igual que hace el RIDE de Facturas de Venta.
            $ivaPct = $this->tarifaIva($d);
            $rowData = [
                (string) ($i + 1),
                (string) ($d['descripcion'] ?? ''),
                number_format((float) ($d['cantidad'] ?? 0), $this->decCantidad, '.', ','),
                '$' . number_format((float) ($d['precio_unitario'] ?? 0), $this->decPrecio, '.', ','),
                '$' . $this->num((float) ($d['descuento'] ?? 0)),
                $this->pct($ivaPct) . '%',
                '$' . $this->num($this->baseLinea($d)),
            ];

            // Alto dinámico según la descripción
            $descLines = $pdf->getNumLines($rowData[1], $cols[1]);
            $rowH = max(6, 4.6 * max(1, $descLines));

            // Salto de página si no cabe
            if ($pdf->GetY() + $rowH > $pdf->getPageHeight() - 40) {
                $pdf->AddPage();
                $pdf->SetX($mL);
                $pdf->SetFillColor(...$this->accent);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 8);
                foreach ($headers as $k => $htxt) {
                    $pdf->Cell($cols[$k], 7, $htxt, 0, ($k === count($headers) - 1 ? 1 : 0), $aligns[$k], true);
                }
                $pdf->SetFont('helvetica', '', 8);
            }

            $x0 = $mL; $y0 = $pdf->GetY();
            if ($fill) {
                $pdf->SetFillColor(245, 247, 250);
                $pdf->Rect($mL, $y0, $w, $rowH, 'F');
            }
            $pdf->SetTextColor(45, 49, 57);

            // #
            $pdf->SetXY($x0, $y0); $pdf->Cell($cols[0], $rowH, $rowData[0], 0, 0, 'C');
            $x0 += $cols[0];
            // Descripción (multilínea, centrada vertical)
            $pdf->SetXY($x0, $y0);
            $pdf->MultiCell($cols[1], $rowH, $rowData[1], 0, 'L', false, 0, '', '', true, 0, false, true, $rowH, 'M');
            $x0 += $cols[1];
            foreach ([2, 3, 4, 5, 6] as $ci) {
                $pdf->SetXY($x0, $y0);
                $pdf->Cell($cols[$ci], $rowH, $rowData[$ci], 0, 0, $aligns[$ci]);
                $x0 += $cols[$ci];
            }
            $pdf->SetY($y0 + $rowH);

            // Línea separadora tenue
            $pdf->SetDrawColor(...$this->grisLinea);
            $pdf->SetLineWidth(0.1);
            $pdf->Line($mL, $pdf->GetY(), $mL + $w, $pdf->GetY());

            $fill = !$fill;
        }
        $pdf->SetY($pdf->GetY() + 3);
    }

    // ── Totales (caja a la derecha) ─────────────────────────────────────────────
    /**
     * Mismo bloque de totales que muestra el modal (proformas_modal.js → _calcularTotales):
     * Subtotal (bruto), un "Subtotal {tarifa}%" por cada tarifa de IVA presente, el
     * descuento y un "(+) IVA {tarifa}%" por cada tarifa mayor a cero.
     *
     * Los valores NO se recalculan: salen de los impuestos guardados en cada línea y de
     * los totales de la cabecera —que la pantalla calculó con el modo de IVA configurado
     * por la empresa (línea por línea o al subtotal)—, igual que hace el RIDE de Facturas
     * de Venta. Así el PDF no puede divergir de la proforma.
     */
    private function totales(TCPDF $pdf, array $cabecera, array $detalles = []): void
    {
        $mL = 14; $w = 182;
        $boxW = 76;
        $x = $mL + $w - $boxW;

        $des   = \App\Helpers\ProformaTotales::desglosar($cabecera, $detalles);
        $filas = \App\Helpers\ProformaTotales::filas($des);
        $total = $des['total'];
        // La caja creció con el desglose: si ya no cabe en la página, se pasa a la siguiente.
        $altoCaja = count($filas) * 6 + 14;
        if ($pdf->GetY() + $altoCaja > $pdf->getPageHeight() - 20) {
            $pdf->AddPage();
        }
        $y = $pdf->GetY();

        $pdf->SetFont('helvetica', '', 9);
        foreach ($filas as $f) {
            $pdf->SetTextColor(...$this->grisTexto);
            $pdf->SetXY($x, $y);
            $pdf->Cell($boxW * 0.5, 6, $f[0], 0, 0, 'L');
            $pdf->SetTextColor(45, 49, 57);
            $pdf->Cell($boxW * 0.5, 6, '$' . $this->num($f[1]), 0, 1, 'R');
            $y += 6;
        }

        // TOTAL destacado
        $pdf->SetFillColor(...$this->accent);
        $pdf->RoundedRect($x, $y + 1, $boxW, 9, 1.5, '1111', 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY($x, $y + 1);
        $pdf->Cell($boxW * 0.5, 9, '  TOTAL', 0, 0, 'L');
        $pdf->Cell($boxW * 0.5 - 2, 9, '$' . $this->num($total), 0, 1, 'R');

        $pdf->SetY($y + 14);
    }

    // ── Observaciones + info adicional ──────────────────────────────────────────
    private function pieAdicional(TCPDF $pdf, array $cabecera, array $adicional): void
    {
        $mL = 14; $w = 182;
        $obs = trim((string) ($cabecera['observaciones'] ?? ''));

        $extra = [];
        foreach ($adicional as $a) {
            $n = trim((string) ($a['nombre'] ?? ''));
            $v = trim((string) ($a['valor'] ?? ''));
            if ($n !== '' && $v !== '') $extra[] = $n . ': ' . $v;
        }

        if ($obs === '' && empty($extra)) return;

        $pdf->SetY($pdf->GetY() + 2);
        $pdf->SetTextColor(...$this->accent);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetX($mL);
        $pdf->Cell($w, 5, 'OBSERVACIONES', 0, 1, 'L');

        $pdf->SetTextColor(...$this->grisTexto);
        $pdf->SetFont('helvetica', '', 8);
        if ($obs !== '') {
            $pdf->SetX($mL);
            $pdf->MultiCell($w, 4.4, $obs, 0, 'L', false, 1);
        }
        foreach ($extra as $line) {
            $pdf->SetX($mL);
            $pdf->MultiCell($w, 4.4, '· ' . $line, 0, 'L', false, 1);
        }
    }

    // ── Pie de documento ────────────────────────────────────────────────────────
    private function pieDocumento(TCPDF $pdf, array $empresa): void
    {
        $mL = 14; $w = 182;
        // Desactivar el salto automático: escribir en el margen inferior con auto-break
        // activo generaría una página en blanco extra.
        $pdf->SetAutoPageBreak(false);

        $pdf->SetY(-15);
        $pdf->SetDrawColor(...$this->grisLinea);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($mL, $pdf->GetY(), $mL + $w, $pdf->GetY());

        $pdf->SetY($pdf->GetY() + 1);
        $pdf->SetTextColor(...$this->grisTexto);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->Cell($w * 0.6, 4, 'Documento no tributario · Proforma sin validez de factura.', 0, 0, 'L');
        $pdf->Cell($w * 0.4, 4, 'Generado el ' . date('d-m-Y H:i'), 0, 1, 'R');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────
    private function num(float $v): string
    {
        return number_format($v, 2, '.', ',');
    }


    /** Porcentaje sin ceros sobrantes: 15 → "15", 12.50 → "12.5". */
    private function pct(float $v): string
    {
        return \App\Helpers\ProformaTotales::pct($v);
    }

    /** Tarifa de IVA de una línea (código de impuesto 2); 0 si la línea no la tiene. */
    private function tarifaIva(array $d): float
    {
        return \App\Helpers\ProformaTotales::tarifaIva($d);
    }

    /** Base imponible de la línea, tal como se guardó. */
    private function baseLinea(array $d): float
    {
        return \App\Helpers\ProformaTotales::baseLinea($d);
    }

    private function resolverLogo(array $empresa): string
    {
        $rutasPosibles = [];
        if (!empty($empresa['logo_ruta'])) $rutasPosibles[] = (string) $empresa['logo_ruta'];
        if (!empty($empresa['logo']))      $rutasPosibles[] = (string) $empresa['logo'];

        foreach ($rutasPosibles as $ruta) {
            $cleanRuta = ltrim($ruta, '/');
            if (strpos($cleanRuta, 'sistema/public/') === 0)      $cleanRuta = substr($cleanRuta, strlen('sistema/public/'));
            elseif (strpos($cleanRuta, 'sistema/') === 0)          $cleanRuta = substr($cleanRuta, strlen('sistema/'));
            if (strpos($cleanRuta, 'public/') === 0)               $cleanRuta = substr($cleanRuta, strlen('public/'));

            foreach ([\MVC_ROOT . '/public/' . $cleanRuta, \MVC_ROOT . '/' . $cleanRuta] as $testPath) {
                if (is_file($testPath)) return $testPath;
            }
        }
        return '';
    }
}
