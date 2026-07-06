<?php
declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Genera el PDF (RIDE) del Comprobante de Retención en Ventas.
 *
 * Fuente preferida: el XML autorizado que guardamos (comprobanteRetencion del
 * SRI, v1.0.0 o v2.0.0). El XML es el documento oficial que emite el CLIENTE
 * (agente de retención), por eso:
 *   - El EMISOR es el cliente. Como el cliente no tiene logo en el sistema, en
 *     el área del logo se imprime "NO TIENE LOGO" en letras rojas.
 *   - El SUJETO RETENIDO es la empresa.
 * Para retenciones manuales sin XML se arma el mismo diseño con los datos de BD.
 */
class RetencionVentaPdfService
{
    private const MARGEN_H = 10;
    private const FUENTE   = 'helvetica';

    // ── Desde XML (fuente preferida) ─────────────────────────────

    public function generarDesdeXml(string $xml): void
    {
        ['cab' => $h, 'lineas' => $lineas] = $this->parsearXml($xml);
        $pdf = $this->construir($h, $lineas);
        $pdf->Output('Retencion_Venta_' . $this->numero($h) . '.pdf', 'D');
    }

    public function generarBytesDesdeXml(string $xml): string
    {
        ['cab' => $h, 'lineas' => $lineas] = $this->parsearXml($xml);
        return $this->construir($h, $lineas)->Output('retencion_venta.pdf', 'S');
    }

    // ── Desde datos de BD (fallback para retenciones manuales) ───

    public function generar(array $cabecera, array $lineas, array $empresa): void
    {
        [$h, $ln] = $this->normalizarDesdeDb($cabecera, $lineas, $empresa);
        $pdf = $this->construir($h, $ln);
        $pdf->Output('Retencion_Venta_' . $this->numero($h) . '.pdf', 'D');
    }

    public function generarBytes(array $cabecera, array $lineas, array $empresa): string
    {
        [$h, $ln] = $this->normalizarDesdeDb($cabecera, $lineas, $empresa);
        return $this->construir($h, $ln)->Output('retencion_venta.pdf', 'S');
    }

    // ─────────────────────────────────────────────────────────────
    // PARSEO DEL XML → estructura normalizada
    // ─────────────────────────────────────────────────────────────

    private function parsearXml(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $obj  = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        if ($obj === false) {
            throw new \RuntimeException('El XML de la retención no es válido.');
        }

        // El XML puede venir de dos formas:
        //  a) el comprobante pelado <comprobanteRetencion> (sin autorización), o
        //  b) el sobre <autorizacion> del SRI, que trae fechaAutorizacion /
        //     numeroAutorizacion y adentro el comprobante (CDATA o inline).
        $fechaAut = '';
        $numAut   = '';
        $fa = $obj->xpath('//fechaAutorizacion');
        if ($fa && count($fa)) $fechaAut = trim((string)$fa[0]);
        $na = $obj->xpath('//numeroAutorizacion');
        if ($na && count($na)) $numAut = trim((string)$na[0]);

        // Resolver el nodo comprobanteRetencion real.
        $comp = null;
        if ($obj->getName() === 'comprobanteRetencion') {
            $comp = $obj;
        } else {
            // Embebido en <comprobante> como CDATA / texto escapado.
            $cs = $obj->xpath('//comprobante');
            if ($cs && count($cs)) {
                $inner = trim((string)$cs[0]);
                if ($inner !== '') {
                    $innerObj = simplexml_load_string($inner);
                    if ($innerObj !== false && $innerObj->getName() === 'comprobanteRetencion') {
                        $comp = $innerObj;
                    }
                }
            }
            // O como nodo hijo inline.
            if ($comp === null) {
                $cr = $obj->xpath('//comprobanteRetencion');
                if ($cr && count($cr)) $comp = $cr[0];
            }
        }
        if ($comp === null) {
            throw new \RuntimeException('No se encontró el comprobante de retención en el XML.');
        }

        $it = $comp->infoTributaria;
        $ic = $comp->infoCompRetencion;

        $h = [
            'razon_social_emisor'   => (string)($it->razonSocial ?? ''),
            'nombre_comercial'      => (string)($it->nombreComercial ?? ''),
            'ruc_emisor'            => (string)($it->ruc ?? ''),
            'dir_matriz'            => (string)($it->dirMatriz ?? ''),
            'agente_retencion'      => (string)($it->agenteRetencion ?? ''),
            'ambiente'              => (string)($it->ambiente ?? '1'),
            'clave_acceso'          => (string)($it->claveAcceso ?? ''),
            'establecimiento'       => (string)($it->estab ?? '001'),
            'punto_emision'         => (string)($it->ptoEmi ?? '001'),
            'secuencial'            => (string)($it->secuencial ?? ''),
            'fecha_emision'         => (string)($ic->fechaEmision ?? ''),
            'dir_establecimiento'   => (string)($ic->dirEstablecimiento ?? ''),
            'obligado_contabilidad' => (string)($ic->obligadoContabilidad ?? ''),
            'periodo_fiscal'        => (string)($ic->periodoFiscal ?? ''),
            'razon_social_sujeto'   => (string)($ic->razonSocialSujetoRetenido ?? ''),
            'identificacion_sujeto' => (string)($ic->identificacionSujetoRetenido ?? ''),
            'numero_autorizacion'   => $numAut,
            'fecha_autorizacion'    => $fechaAut,
            'origen'                => 'electronico',
        ];

        $lineas = [];

        // v1.0.0 → <impuestos><impuesto>
        if (isset($comp->impuestos->impuesto)) {
            foreach ($comp->impuestos->impuesto as $imp) {
                $lineas[] = $this->lineaXml($imp, (string)$imp->numDocSustento);
            }
        }
        // v2.0.0 → <docsSustento><docSustento><impuestosDocSustento><impuestoDocSustento>
        elseif (isset($comp->docsSustento->docSustento)) {
            foreach ($comp->docsSustento->docSustento as $doc) {
                $num = (string)($doc->numDocSustento ?? '');
                foreach ($doc->impuestosDocSustento->impuestoDocSustento as $imp) {
                    $lineas[] = $this->lineaXml($imp, $num);
                }
            }
        }

        return ['cab' => $h, 'lineas' => $lineas];
    }

    private function lineaXml(\SimpleXMLElement $imp, string $numDocSustento): array
    {
        return [
            'codigo_impuesto'   => (string)($imp->codigo ?? ''),
            'codigo_retencion'  => (string)($imp->codigoRetencion ?? ''),
            'base_imponible'    => (float)($imp->baseImponible ?? 0),
            'porcentaje'        => (float)($imp->porcentajeRetener ?? 0),
            'valor_retenido'    => (float)($imp->valorRetenido ?? 0),
            'num_doc_sustento'  => $numDocSustento,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // NORMALIZACIÓN DESDE BD (retención manual sin XML)
    // ─────────────────────────────────────────────────────────────

    private function normalizarDesdeDb(array $cab, array $lineas, array $emp): array
    {
        $h = [
            'razon_social_emisor'   => $cab['cliente_nombre'] ?? '',
            'nombre_comercial'      => $cab['cliente_nombre'] ?? '',
            'ruc_emisor'            => $cab['cliente_identificacion'] ?? '',
            'dir_matriz'            => $cab['cliente_direccion'] ?? '',
            'agente_retencion'      => '',
            'ambiente'              => (string)($cab['tipo_ambiente'] ?? $emp['tipo_ambiente'] ?? '1'),
            'clave_acceso'          => $cab['clave_acceso'] ?? '',
            'establecimiento'       => (string)($cab['establecimiento'] ?? '001'),
            'punto_emision'         => (string)($cab['punto_emision'] ?? '001'),
            'secuencial'            => (string)($cab['secuencial'] ?? ''),
            'fecha_emision'         => $cab['fecha_emision'] ?? '',
            'dir_establecimiento'   => '',
            'obligado_contabilidad' => '',
            'periodo_fiscal'        => $cab['periodo_fiscal'] ?? '',
            'razon_social_sujeto'   => $emp['nombre'] ?? '',
            'identificacion_sujeto' => $emp['ruc'] ?? '',
            'numero_autorizacion'   => '',
            'fecha_autorizacion'    => '',
            'origen'                => $cab['origen'] ?? 'manual',
        ];

        $ln = [];
        foreach ($lineas as $l) {
            $ln[] = [
                'codigo_impuesto'  => (string)($l['codigo_impuesto'] ?? ''),
                'codigo_retencion' => (string)($l['codigo_retencion'] ?? ''),
                'base_imponible'   => (float)($l['base_imponible'] ?? 0),
                'porcentaje'       => (float)($l['porcentaje_retencion'] ?? 0),
                'valor_retenido'   => (float)($l['valor_retenido'] ?? 0),
                'num_doc_sustento' => (string)($l['num_doc_sustento'] ?? ''),
            ];
        }
        return [$h, $ln];
    }

    // ─────────────────────────────────────────────────────────────
    // CONSTRUCCIÓN DEL PDF
    // ─────────────────────────────────────────────────────────────

    private function construir(array $h, array $lineas): \TCPDF
    {
        require_once MVC_ROOT . '/vendor/autoload.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor($h['razon_social_emisor'] ?? '');
        $pdf->SetTitle('Comprobante de Retención en Ventas');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGEN_H, self::MARGEN_H, self::MARGEN_H);
        $pdf->SetAutoPageBreak(true, self::MARGEN_H);
        $pdf->AddPage();

        $this->dibujarEncabezado($pdf, $h);
        $this->dibujarSujetoRetenido($pdf, $h);
        $this->dibujarLineas($pdf, $lineas);
        $this->dibujarTotales($pdf, $lineas);

        return $pdf;
    }

    private function numero(array $h): string
    {
        return str_pad((string)($h['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
             . str_pad((string)($h['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
             . str_pad((string)($h['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
    }

    private function fechaDisplay(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $v)) return $v; // ya viene d/m/Y (XML)
        $ts = strtotime($v);
        return $ts ? date('d/m/Y', $ts) : $v;
    }

    private function fechaHoraDisplay(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        $ts = strtotime($v);
        return $ts ? date('d/m/Y H:i:s', $ts) : $v;
    }

    // ── Encabezado: emisor (cliente) + datos del comprobante ─────

    private function dibujarEncabezado(\TCPDF $pdf, array $h): void
    {
        $mL       = self::MARGEN_H;
        $contentW = $pdf->getPageWidth() - 2 * self::MARGEN_H; // 190mm
        $izqW     = 85;
        $derW     = $contentW - $izqW - 2;
        $derX     = $mL + $izqW + 2;

        $yTop           = 8;
        $logoAreaHeight = 73.5 * 0.40;

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);

        // Sin logo del cliente: leyenda "NO TIENE LOGO" en rojo.
        $pdf->SetFont(self::FUENTE, 'B', 16);
        $pdf->SetTextColor(220, 0, 0);
        $pdf->SetXY($mL + 2, $yTop + ($logoAreaHeight / 2) - 5);
        $pdf->Cell($izqW - 4, 15, 'NO TIENE LOGO', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $yTopIzqBox = $yTop + $logoAreaHeight;

        // ── Caja izquierda: emisor (cliente / agente de retención) ──
        $yIzq = $yTopIzqBox + 3;

        $razon = trim($h['razon_social_emisor'] ?? '');
        if ($razon) {
            $pdf->SetFont(self::FUENTE, 'B', 9);
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->MultiCell($izqW - 4, 5, $razon, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        $ruc = trim($h['ruc_emisor'] ?? '');
        if ($ruc) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->Cell(22, 4, 'RUC:', 0, 0, 'L');
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->Cell($izqW - 26, 4, $ruc, 0, 1, 'L');
            $yIzq = $pdf->GetY();
        }

        $dir = trim($h['dir_matriz'] ?? '');
        if ($dir) {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->Cell(22, 4, 'Dir. Matriz:', 0, 0, 'L');
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->MultiCell($izqW - 26, 4, $dir, 0, 'L', false, 1);
            $yIzq = $pdf->GetY();
        }

        $obl = strtoupper(trim((string)($h['obligado_contabilidad'] ?? '')));
        if ($obl === 'SI' || $obl === 'NO') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->Cell(45, 4, 'OBLIGADO A LLEVAR CONTABILIDAD', 0, 0, 'L');
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->Cell($izqW - 49, 4, $obl, 0, 1, 'L');
            $yIzq = $pdf->GetY();
        }

        $agente = trim((string)($h['agente_retencion'] ?? ''));
        if ($agente !== '' && $agente !== '0' && strtoupper($agente) !== 'NO') {
            $pdf->SetXY($mL + 2, $yIzq);
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->Cell(45, 4, 'Agente de Retención Res. No.', 0, 0, 'L');
            $pdf->SetFont(self::FUENTE, 'B', 7);
            $pdf->Cell($izqW - 49, 4, $agente, 0, 1, 'L');
            $yIzq = $pdf->GetY();
        }

        $yIzq += 2;

        // ── Caja derecha: datos del comprobante ─────────────────────
        $yDer = $yTop;

        $pdf->SetFont(self::FUENTE, '', 8);
        $pdf->SetXY($derX + 2, $yDer + 2);
        $pdf->Cell(14, 5, 'R.U.C.:', 0, 0, 'L');
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->Cell($derW - 16, 5, $h['ruc_emisor'] ?? '', 0, 1, 'L');
        $yDer += 8;

        $pdf->SetFont(self::FUENTE, 'B', 11);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 7, 'COMPROBANTE DE RETENCIÓN', 0, 1, 'L');
        $yDer += 7;

        $pdf->SetFont(self::FUENTE, '', 8);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell(7, 5, 'No.', 0, 0, 'L');
        $pdf->Cell($derW - 9, 5, $this->numero($h), 0, 1, 'L');
        $yDer += 6;

        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->Cell($derW - 4, 4.5, 'NÚMERO DE AUTORIZACIÓN', 0, 1, 'L');
        $yDer += 5;

        $clave  = trim($h['clave_acceso'] ?? '');
        $autNum = trim((string)($h['numero_autorizacion'] ?? '')) ?: $clave;
        if ($autNum) {
            $pdf->SetFont(self::FUENTE, '', 7);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->MultiCell($derW - 4, 4, $autNum, 0, 'L', false, 1);
            $yDer = $pdf->GetY() + 1;
        }

        // Fecha y hora de autorización: solo si el XML la trae (sobre <autorizacion>).
        $fechaAut = $this->fechaHoraDisplay((string)($h['fecha_autorizacion'] ?? ''));
        if ($fechaAut !== '') {
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont(self::FUENTE, '', 7.5);
            $pdf->Cell($derW - 4, 4.5, 'FECHA Y HORA DE AUTORIZACIÓN:', 0, 1, 'L');
            $yDer += 4.5;
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->SetFont(self::FUENTE, 'B', 7.5);
            $pdf->Cell($derW - 4, 4.5, $fechaAut, 0, 1, 'L');
            $yDer += 5;
        }

        $ambiente = ((string)($h['ambiente'] ?? '1')) === '2' ? 'PRODUCCIÓN' : 'PRUEBAS';
        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->Cell(22, 4.5, 'AMBIENTE:', 0, 0, 'L');
        $pdf->SetFont(self::FUENTE, 'B', 7.5);
        $pdf->Cell($derW - 24, 4.5, $ambiente, 0, 1, 'L');
        $yDer += 4.5;

        $pdf->SetXY($derX + 2, $yDer);
        $pdf->SetFont(self::FUENTE, '', 7.5);
        $pdf->Cell(22, 4.5, 'EMISIÓN:', 0, 0, 'L');
        $pdf->SetFont(self::FUENTE, 'B', 7.5);
        $emision = ($h['origen'] ?? 'manual') === 'electronico' ? 'NORMAL' : 'MANUAL';
        $pdf->Cell($derW - 24, 4.5, $emision, 0, 1, 'L');
        $yDer += 5;

        if ($clave) {
            $pdf->SetFont(self::FUENTE, '', 7.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 4.5, 'CLAVE DE ACCESO', 0, 1, 'L');
            $yDer += 5;

            $barcodeH = 12;
            $pdf->write1DBarcode(
                $clave, 'C128', $derX + 2, $yDer, $derW - 1, $barcodeH, 0.4,
                ['position' => 'R', 'text' => false, 'stretcharray' => '', 'stretch' => true], 'N'
            );
            $yDer += $barcodeH + 1;
            $pdf->SetFont(self::FUENTE, '', 5.5);
            $pdf->SetXY($derX + 2, $yDer);
            $pdf->Cell($derW - 4, 3.5, $clave, 0, 1, 'C');
            $yDer += 4;
        }

        $yDer += 2;

        $yBottom = max($yIzq, $yDer);
        $pdf->RoundedRect($mL, $yTopIzqBox, $izqW, $yBottom - $yTopIzqBox, 3, '1111', 'D');
        $pdf->RoundedRect($derX, $yTop, $derW, $yBottom - $yTop, 3, '1111', 'D');

        $pdf->SetXY($mL, $yBottom + 3);
    }

    // ── Sujeto retenido (empresa) + período ──────────────────────

    private function dibujarSujetoRetenido(\TCPDF $pdf, array $h): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;

        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($pageW, 5, 'DATOS DEL SUJETO RETENIDO', 1, 1, 'C', true);

        $pdf->SetFont(self::FUENTE, '', 7.5);
        $halfW = $pageW / 2;

        $pdf->SetX($x);
        $pdf->Cell($halfW * 0.35, 4.5, 'Razón Social:', 'LB', 0, 'L');
        $pdf->Cell($halfW * 0.65, 4.5, $h['razon_social_sujeto'] ?? '', 'RB', 0, 'L');
        $pdf->Cell($halfW * 0.35, 4.5, 'RUC / Ident.:', 'LB', 0, 'L');
        $pdf->Cell($halfW * 0.65, 4.5, $h['identificacion_sujeto'] ?? '', 'RB', 1, 'L');

        $pdf->SetX($x);
        $pdf->Cell($pageW * 0.20, 4.5, 'Fecha Emisión:', 'LB', 0, 'L');
        $pdf->Cell($pageW * 0.30, 4.5, $this->fechaDisplay((string)($h['fecha_emision'] ?? '')), 'B', 0, 'L');
        $pdf->Cell($pageW * 0.20, 4.5, 'Período Fiscal:', 'LB', 0, 'L');
        $pdf->Cell($pageW * 0.30, 4.5, $h['periodo_fiscal'] ?? '', 'RB', 1, 'L');

        $pdf->Ln(2);
    }

    // ── Tabla de líneas de retención ─────────────────────────────

    private function dibujarLineas(\TCPDF $pdf, array $lineas): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;

        $pdf->SetX($x);
        $pdf->SetFont(self::FUENTE, 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($pageW, 5, 'DETALLE DE RETENCIONES', 1, 1, 'C', true);

        $anchos = [
            'impuesto'   => $pageW * 0.16,
            'codigo'     => $pageW * 0.12,
            'sustento'   => $pageW * 0.24,
            'base'       => $pageW * 0.16,
            'porcentaje' => $pageW * 0.14,
            'valor'      => $pageW * 0.18,
        ];

        $pdf->SetFont(self::FUENTE, 'B', 7);
        $pdf->SetX($x);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell($anchos['impuesto'],   5, 'IMPUESTO',      1, 0, 'C', true);
        $pdf->Cell($anchos['codigo'],     5, 'COD. RET.',     1, 0, 'C', true);
        $pdf->Cell($anchos['sustento'],   5, 'DOC. SUSTENTO', 1, 0, 'C', true);
        $pdf->Cell($anchos['base'],       5, 'BASE IMPON.',   1, 0, 'R', true);
        $pdf->Cell($anchos['porcentaje'], 5, '%',             1, 0, 'C', true);
        $pdf->Cell($anchos['valor'],      5, 'VALOR RET.',    1, 1, 'R', true);

        $pdf->SetFont(self::FUENTE, '', 7);
        $pdf->SetFillColor(255, 255, 255);

        foreach ($lineas as $l) {
            $codImp   = strtoupper((string)($l['codigo_impuesto'] ?? '1'));
            $impuesto = match($codImp) {
                '1', 'RENTA' => 'Renta (IR)',
                '2', 'IVA'   => 'IVA',
                '6', 'ISD'   => 'ISD',
                default       => $codImp,
            };

            $pdf->SetX($x);
            $pdf->Cell($anchos['impuesto'],   4.5, $impuesto,                                    1, 0, 'C');
            $pdf->Cell($anchos['codigo'],     4.5, $l['codigo_retencion'] ?? '',                 1, 0, 'C');
            $pdf->Cell($anchos['sustento'],   4.5, $l['num_doc_sustento'] ?? '',                 1, 0, 'C');
            $pdf->Cell($anchos['base'],       4.5, number_format((float)($l['base_imponible'] ?? 0), 2), 1, 0, 'R');
            $pdf->Cell($anchos['porcentaje'], 4.5, number_format((float)($l['porcentaje']    ?? 0), 2) . '%', 1, 0, 'C');
            $pdf->Cell($anchos['valor'],      4.5, number_format((float)($l['valor_retenido'] ?? 0), 2), 1, 1, 'R');
        }

        $pdf->Ln(2);
    }

    // ── Totales ──────────────────────────────────────────────────

    private function dibujarTotales(\TCPDF $pdf, array $lineas): void
    {
        $pageW = $pdf->getPageWidth() - 2 * self::MARGEN_H;
        $x     = self::MARGEN_H;

        $totRenta = 0.0; $totIva = 0.0; $totIsd = 0.0;
        foreach ($lineas as $l) {
            $codImp = strtoupper((string)($l['codigo_impuesto'] ?? ''));
            $val    = (float)($l['valor_retenido'] ?? 0);
            if ($codImp === '1' || $codImp === 'RENTA')     $totRenta += $val;
            elseif ($codImp === '2' || $codImp === 'IVA')   $totIva   += $val;
            elseif ($codImp === '6' || $codImp === 'ISD')   $totIsd   += $val;
        }
        $totalGeneral = $totRenta + $totIva + $totIsd;

        $labelW = $pageW * 0.22;
        $valorW = $pageW * 0.13;
        $boxX   = $x + $pageW - ($labelW + $valorW);

        $items = [['Total Retenido Renta (IR):', $totRenta], ['Total Retenido IVA:', $totIva]];
        if ($totIsd > 0) $items[] = ['Total Retenido ISD:', $totIsd];
        $items[] = ['TOTAL RETENIDO:', $totalGeneral];

        foreach ($items as $idx => [$label, $valor]) {
            $esTotal = $idx === count($items) - 1;
            $pdf->SetX($boxX);
            $pdf->SetFont(self::FUENTE, $esTotal ? 'B' : '', $esTotal ? 8 : 7);
            $pdf->Cell($labelW, 4.5, $label, 'LTB', 0, 'R');
            $pdf->Cell($valorW, 4.5, '$' . number_format($valor, 2), 'TBR', 1, 'R');
        }

        $pdf->Ln(3);
    }
}
