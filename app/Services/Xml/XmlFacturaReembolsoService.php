<?php

declare(strict_types=1);

namespace App\Services\Xml;

/**
 * Genera el XML de "Factura de Reembolso" según el esquema SRI Ecuador v1.1.0
 * de FACTURA (codDoc=01) — sigue siendo una Factura normal ante el SRI. La
 * clasificación ATS 41 "Comprobante de venta emitido por reembolso" vive
 * únicamente en el campo <codDocReembolso> de infoFactura (fijo '41' en este
 * módulo) más sus 3 totales agregados, y en el nodo raíz <reembolsos>.
 *
 * Clase standalone (no extiende XmlFacturaVentaService): mismo criterio que
 * Nota de Crédito/Nota de Débito en este proyecto, para no acoplar este
 * módulo a cambios futuros de Factura de Venta.
 */
class XmlFacturaReembolsoService
{
    private int $decCantidad = 2;
    private int $decPrecio   = 2;

    /**
     * @param array $cabecera      Fila de factura_reembolso_cabecera (con joins de cliente)
     * @param array $detalles      Filas de factura_reembolso_detalle, cada una con clave 'impuestos'
     * @param array $terceros      Filas de factura_reembolso_terceros, cada una con clave 'impuestos'
     * @param array $pagos         Filas de factura_reembolso_pagos
     * @param array $infoAdicional Filas de factura_reembolso_adicional
     * @param array $empresa       Fila de empresas
     */
    public function generar(
        array $cabecera,
        array $detalles,
        array $terceros,
        array $pagos,
        array $infoAdicional,
        array $empresa,
        ?string $dirEstablecimiento = null
    ): string {
        $this->decCantidad = max(0, min(6, (int) ($empresa['decimales_cantidad'] ?? 2)));
        $this->decPrecio   = max(0, min(6, (int) ($empresa['decimales_precio']   ?? 2)));

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $factura = $dom->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', '1.1.0');
        $dom->appendChild($factura);

        $factura->appendChild($this->buildInfoTributaria($dom, $cabecera, $empresa));
        $factura->appendChild($this->buildInfoFactura($dom, $cabecera, $detalles, $terceros, $pagos, $empresa, $dirEstablecimiento));
        $factura->appendChild($this->buildDetalles($dom, $detalles));

        // <reembolsos>: hermano de <detalles>, antes de <infoAdicional> (orden confirmado
        // en el XSD factura_V2.1.0.xsd). Siempre presente en este módulo (Rules exige ≥1 tercero).
        $reembolsosEl = $this->buildReembolsos($dom, $terceros);
        if ($reembolsosEl !== null) {
            $factura->appendChild($reembolsosEl);
        }

        $infoAdicionalEl = $this->buildInfoAdicional($dom, $infoAdicional);
        if ($infoAdicionalEl !== null) {
            $factura->appendChild($infoAdicionalEl);
        }

        return $dom->saveXML();
    }

    private function buildInfoTributaria(\DOMDocument $dom, array $cab, array $emp): \DOMElement
    {
        $el = $dom->createElement('infoTributaria');

        $this->txt($dom, $el, 'ambiente',        $cab['tipo_ambiente'] ?? '1');
        $this->txt($dom, $el, 'tipoEmision',     $cab['tipo_emision']  ?? '1');
        $this->txt($dom, $el, 'razonSocial',     $emp['nombre'] ?? '');
        $this->txt($dom, $el, 'nombreComercial', $emp['nombre_comercial'] ?? $emp['nombre'] ?? '');
        $this->txt($dom, $el, 'ruc',             $emp['ruc'] ?? '');
        $this->txt($dom, $el, 'claveAcceso',     $cab['clave_acceso'] ?? '');
        $this->txt($dom, $el, 'codDoc',          '01');
        $this->txt($dom, $el, 'estab',           $cab['establecimiento'] ?? '001');
        $this->txt($dom, $el, 'ptoEmi',          $cab['punto_emision']  ?? '001');
        $this->txt($dom, $el, 'secuencial',      str_pad((string) ($cab['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT));
        $this->txt($dom, $el, 'dirMatriz',       $emp['direccion'] ?? '');

        $agente = \App\Helpers\SriEmisorHelper::agenteRetencionNumero($emp);
        if ($agente !== '') {
            $this->txt($dom, $el, 'agenteRetencion', $agente);
        }
        $regimen = \App\Helpers\SriEmisorHelper::regimenRimpeLeyenda($emp);
        if ($regimen !== '') {
            $this->txt($dom, $el, 'contribuyenteRimpe', $regimen);
        }

        return $el;
    }

    private function buildInfoFactura(
        \DOMDocument $dom,
        array $cab,
        array $detalles,
        array $terceros,
        array $pagos,
        array $emp,
        ?string $dirEstablecimiento
    ): \DOMElement {
        $el = $dom->createElement('infoFactura');

        $fechaEmision = '';
        if (!empty($cab['fecha_emision'])) {
            $ts = strtotime($cab['fecha_emision']);
            $fechaEmision = $ts ? date('d/m/Y', $ts) : $cab['fecha_emision'];
        }

        $this->txt($dom, $el, 'fechaEmision',       $fechaEmision);
        $this->txt($dom, $el, 'dirEstablecimiento', $dirEstablecimiento ?? $emp['direccion'] ?? '');

        if (!empty($cab['contribuyente_especial']) || !empty($emp['contribuyente_especial'])) {
            $this->txt($dom, $el, 'contribuyenteEspecial', $cab['contribuyente_especial'] ?? $emp['contribuyente_especial'] ?? '');
        }

        $obligado = !empty($emp['obligado_contabilidad']) ? strtoupper((string) $emp['obligado_contabilidad']) : 'NO';
        $this->txt($dom, $el, 'obligadoContabilidad', $obligado);

        $tipoId = $cab['cliente_tipo_id'] ?? '05';
        $this->txt($dom, $el, 'tipoIdentificacionComprador', (string) $tipoId);
        $this->txt($dom, $el, 'razonSocialComprador',        $cab['cliente_nombre'] ?? '');
        $this->txt($dom, $el, 'identificacionComprador',     $cab['cliente_ruc'] ?? '');

        $this->txt($dom, $el, 'totalSinImpuestos', $this->dec2($cab['total_sin_impuestos'] ?? 0));
        $this->txt($dom, $el, 'totalDescuento',    $this->dec2($cab['total_descuento']    ?? 0));

        // Bloque ATS 41 (obligatorio por la Ficha Técnica SRI cuando codDocReembolso=41):
        // va entre totalDescuento y totalConImpuestos. Los 3 totales son montos monetarios,
        // calculados aquí en vivo desde $terceros (no se confía en lo guardado en cabecera,
        // igual criterio que buildTotalConImpuestos abajo).
        [$totalBase, $totalImpuesto] = $this->sumarTerceros($terceros);
        $this->txt($dom, $el, 'codDocReembolso',             '41');
        $this->txt($dom, $el, 'totalComprobantesReembolso',  $this->dec2($totalBase + $totalImpuesto));
        $this->txt($dom, $el, 'totalBaseImponibleReembolso', $this->dec2($totalBase));
        $this->txt($dom, $el, 'totalImpuestoReembolso',      $this->dec2($totalImpuesto));

        $el->appendChild($this->buildTotalConImpuestos($dom, $detalles, $cab));

        $this->txt($dom, $el, 'propina',      $this->dec2($cab['propina']      ?? 0));
        $this->txt($dom, $el, 'importeTotal', $this->dec2($cab['importe_total'] ?? 0));
        $this->txt($dom, $el, 'moneda',       strtoupper($cab['moneda'] ?? 'DOLAR'));

        $pagosEl = $dom->createElement('pagos');
        foreach ($pagos as $p) {
            $pagoEl = $dom->createElement('pago');
            $this->txt($dom, $pagoEl, 'formaPago', (string) ($p['forma_pago'] ?? '01'));
            $this->txt($dom, $pagoEl, 'total',     $this->dec2($p['total'] ?? 0));
            if (!empty($p['plazo']) && (int) $p['plazo'] > 0) {
                $this->txt($dom, $pagoEl, 'plazo',        (string) (int) $p['plazo']);
                $this->txt($dom, $pagoEl, 'unidadTiempo', $p['unidad_tiempo'] ?? 'dias');
            }
            $pagosEl->appendChild($pagoEl);
        }
        $el->appendChild($pagosEl);

        return $el;
    }

    /** @return array{0: float, 1: float} [totalBaseImponible, totalImpuesto] sumados de todos los terceros. */
    private function sumarTerceros(array $terceros): array
    {
        $base = 0.0;
        $impuesto = 0.0;
        foreach ($terceros as $t) {
            foreach ($t['impuestos'] ?? [] as $imp) {
                $base     += (float) ($imp['base_imponible'] ?? 0);
                $impuesto += (float) ($imp['valor']          ?? 0);
            }
        }
        return [round($base, 2), round($impuesto, 2)];
    }

    private function codigoPorcentajeImpuesto(array $imp): string
    {
        $codImpuesto = (string) ($imp['codigo_impuesto'] ?? '');
        $guardado    = (string) ($imp['codigo_porcentaje'] ?? '');
        $tarifa      = (float) ($imp['tarifa'] ?? 0);

        if ($codImpuesto === '2' && $tarifa > 0) {
            return \App\Helpers\SriIvaHelper::codigoPorcentaje($tarifa);
        }
        return $guardado;
    }

    private function buildTotalConImpuestos(\DOMDocument $dom, array $detalles, array $cab = []): \DOMElement
    {
        $el = $dom->createElement('totalConImpuestos');

        $grupos = [];
        foreach ($detalles as $d) {
            foreach ($d['impuestos'] ?? [] as $imp) {
                $codPorcentaje = $this->codigoPorcentajeImpuesto($imp);
                $key = ($imp['codigo_impuesto'] ?? '') . '|' . $codPorcentaje;
                if (!isset($grupos[$key])) {
                    $grupos[$key] = [
                        'codigo'           => $imp['codigo_impuesto'] ?? '',
                        'codigoPorcentaje' => $codPorcentaje,
                        'baseImponible'    => 0.0,
                        'valor'            => 0.0,
                    ];
                }
                $grupos[$key]['baseImponible'] += (float) ($imp['base_imponible'] ?? 0);
                $grupos[$key]['valor']         += (float) ($imp['valor']          ?? 0);
            }
        }

        foreach ($grupos as $g) {
            $totalImp = $dom->createElement('totalImpuesto');
            $this->txt($dom, $totalImp, 'codigo',           $g['codigo']);
            $this->txt($dom, $totalImp, 'codigoPorcentaje', $g['codigoPorcentaje']);
            $this->txt($dom, $totalImp, 'baseImponible',    $this->dec2($g['baseImponible']));
            $this->txt($dom, $totalImp, 'valor',            $this->dec2($g['valor']));
            $el->appendChild($totalImp);
        }

        return $el;
    }

    private function buildDetalles(\DOMDocument $dom, array $detalles): \DOMElement
    {
        $el = $dom->createElement('detalles');

        foreach ($detalles as $d) {
            $det = $dom->createElement('detalle');

            // Líneas libres (sin catálogo de productos): el código principal es la
            // propia descripción abreviada, como hacen otros documentos sin inventario.
            $this->txt($dom, $det, 'codigoPrincipal', (string) ($d['id'] ?? 'REEMB'));
            $this->txt($dom, $det, 'descripcion',            $d['descripcion'] ?? '');
            $this->txt($dom, $det, 'cantidad',               $this->decConfig($d['cantidad']        ?? 0, $this->decCantidad));
            $this->txt($dom, $det, 'precioUnitario',         $this->decConfig($d['precio_unitario']  ?? 0, $this->decPrecio));
            $this->txt($dom, $det, 'descuento',              $this->dec2($d['descuento']            ?? 0));
            $this->txt($dom, $det, 'precioTotalSinImpuesto', $this->dec2($d['precio_total_sin_impuesto'] ?? 0));

            $impuestosEl = $dom->createElement('impuestos');
            foreach ($d['impuestos'] ?? [] as $imp) {
                $impEl = $dom->createElement('impuesto');
                $this->txt($dom, $impEl, 'codigo',           $imp['codigo_impuesto'] ?? '');
                $this->txt($dom, $impEl, 'codigoPorcentaje', $this->codigoPorcentajeImpuesto($imp));
                $this->txt($dom, $impEl, 'tarifa',           $this->dec2($imp['tarifa']        ?? 0));
                $this->txt($dom, $impEl, 'baseImponible',    $this->dec2($imp['base_imponible'] ?? 0));
                $this->txt($dom, $impEl, 'valor',            $this->dec2($imp['valor']          ?? 0));
                $impuestosEl->appendChild($impEl);
            }
            $det->appendChild($impuestosEl);

            $el->appendChild($det);
        }

        return $el;
    }

    /**
     * Nodo <reembolsos> (hermano de <detalles>). Cada línea declara el
     * documento de un tercero que la empresa pagó a nombre del cliente.
     * Orden de campos EXACTO del XSD factura_V2.1.0.xsd — no reordenar.
     */
    private function buildReembolsos(\DOMDocument $dom, array $terceros): ?\DOMElement
    {
        if (empty($terceros)) {
            return null;
        }

        $el = $dom->createElement('reembolsos');
        foreach ($terceros as $t) {
            $det = $dom->createElement('reembolsoDetalle');

            $this->txt($dom, $det, 'tipoIdentificacionProveedorReembolso', (string) ($t['tipo_identificacion_proveedor_reembolso'] ?? ''));
            $this->txt($dom, $det, 'identificacionProveedorReembolso',     (string) ($t['identificacion_proveedor_reembolso']    ?? ''));
            if (!empty($t['cod_pais_pago_proveedor_reembolso'])) {
                $this->txt($dom, $det, 'codPaisPagoProveedorReembolso', (string) $t['cod_pais_pago_proveedor_reembolso']);
            }
            $this->txt($dom, $det, 'tipoProveedorReembolso', (string) ($t['tipo_proveedor_reembolso'] ?? ''));
            $this->txt($dom, $det, 'codDocReembolso',        (string) ($t['cod_doc_reembolso'] ?? ''));
            $this->txt($dom, $det, 'estabDocReembolso',      (string) ($t['estab_doc_reembolso'] ?? ''));
            $this->txt($dom, $det, 'ptoEmiDocReembolso',     (string) ($t['pto_emi_doc_reembolso'] ?? ''));
            $this->txt($dom, $det, 'secuencialDocReembolso', (string) ($t['secuencial_doc_reembolso'] ?? ''));

            $fechaDoc = '';
            if (!empty($t['fecha_emision_doc_reembolso'])) {
                $ts = strtotime((string) $t['fecha_emision_doc_reembolso']);
                $fechaDoc = $ts ? date('d/m/Y', $ts) : (string) $t['fecha_emision_doc_reembolso'];
            }
            $this->txt($dom, $det, 'fechaEmisionDocReembolso',   $fechaDoc);
            $this->txt($dom, $det, 'numeroautorizacionDocReemb', (string) ($t['numero_autorizacion_doc_reemb'] ?? ''));

            $impuestosEl = $dom->createElement('detalleImpuestos');
            foreach ($t['impuestos'] ?? [] as $imp) {
                $impEl = $dom->createElement('detalleImpuesto');
                $this->txt($dom, $impEl, 'codigo',                 (string) ($imp['codigo_impuesto']   ?? ''));
                $this->txt($dom, $impEl, 'codigoPorcentaje',       (string) ($imp['codigo_porcentaje'] ?? ''));
                $this->txt($dom, $impEl, 'tarifa',                 $this->dec2($imp['tarifa']          ?? 0));
                $this->txt($dom, $impEl, 'baseImponibleReembolso', $this->dec2($imp['base_imponible']  ?? 0));
                $this->txt($dom, $impEl, 'impuestoReembolso',      $this->dec2($imp['valor']           ?? 0));
                $impuestosEl->appendChild($impEl);
            }
            $det->appendChild($impuestosEl);

            // compensacionesReembolso (IVA agrícola) no se implementa en esta fase.

            $el->appendChild($det);
        }

        return $el;
    }

    private function buildInfoAdicional(\DOMDocument $dom, array $infoAdicional): ?\DOMElement
    {
        if (empty($infoAdicional)) {
            return null;
        }

        $el = $dom->createElement('infoAdicional');
        foreach ($infoAdicional as $ia) {
            $campo = $dom->createElement('campoAdicional');
            $campo->setAttribute('nombre', $ia['nombre'] ?? '');
            $campo->appendChild($dom->createTextNode($ia['valor'] ?? ''));
            $el->appendChild($campo);
        }

        return $el;
    }

    private function txt(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElement($name);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private function dec2(float|string $val): string
    {
        return number_format((float) $val, 2, '.', '');
    }

    private function decConfig(float|string $val, int $decConfig): string
    {
        $decConfig = max(0, min(6, $decConfig));
        $limpio  = rtrim(rtrim(number_format((float) $val, 6, '.', ''), '0'), '.');
        $pos     = strpos($limpio, '.');
        $realDec = $pos === false ? 0 : strlen($limpio) - $pos - 1;
        $dec     = max($decConfig, $realDec);
        return number_format((float) $val, $dec, '.', '');
    }
}
