<?php

declare(strict_types=1);

namespace App\Services\Xml;

/**
 * Genera el XML de factura de venta según el esquema SRI Ecuador v1.1.0.
 * Diseñado para ser extensible hacia otros tipos de comprobante.
 */
class XmlFacturaVentaService
{
    /**
     * @param array       $cabecera         Fila de ventas_cabecera (con joins de cliente)
     * @param array       $detalles         Filas de ventas_detalle, cada una con clave 'impuestos'
     * @param array       $pagos            Filas de ventas_pagos
     * @param array       $infoAdicional    Filas de ventas_adicional
     * @param array       $empresa          Fila de empresas
     * @param string|null $dirEstablecimiento Dirección del establecimiento; si null usa empresa.direccion
     */
    public function generar(
        array $cabecera,
        array $detalles,
        array $pagos,
        array $infoAdicional,
        array $empresa,
        ?string $dirEstablecimiento = null
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $factura = $dom->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', '1.1.0');
        $dom->appendChild($factura);

        $factura->appendChild($this->buildInfoTributaria($dom, $cabecera, $empresa));
        $factura->appendChild($this->buildInfoFactura($dom, $cabecera, $detalles, $pagos, $empresa, $dirEstablecimiento));
        $factura->appendChild($this->buildDetalles($dom, $detalles));

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
        $this->txt($dom, $el, 'secuencial',      str_pad((string)($cab['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT));
        $this->txt($dom, $el, 'dirMatriz',       $emp['direccion'] ?? '');

        return $el;
    }

    private function buildInfoFactura(
        \DOMDocument $dom,
        array $cab,
        array $detalles,
        array $pagos,
        array $emp,
        ?string $dirEstablecimiento
    ): \DOMElement {
        $el = $dom->createElement('infoFactura');

        // fecha: almacenada como Y-m-d, SRI requiere dd/mm/yyyy
        $fechaEmision = '';
        if (!empty($cab['fecha_emision'])) {
            $ts = strtotime($cab['fecha_emision']);
            $fechaEmision = $ts ? date('d/m/Y', $ts) : $cab['fecha_emision'];
        }

        $this->txt($dom, $el, 'fechaEmision',        $fechaEmision);
        $this->txt($dom, $el, 'dirEstablecimiento',  $dirEstablecimiento ?? $emp['direccion'] ?? '');

        // Campos opcionales según el tipo de empresa
        if (!empty($cab['contribuyente_especial']) || !empty($emp['contribuyente_especial'])) {
            $this->txt($dom, $el, 'contribuyenteEspecial',
                $cab['contribuyente_especial'] ?? $emp['contribuyente_especial'] ?? '');
        }

        $obligado = !empty($emp['obligado_contabilidad'])
            ? strtoupper((string)$emp['obligado_contabilidad'])
            : 'NO';
        $this->txt($dom, $el, 'obligadoContabilidad', $obligado);

        // Datos del comprador
        $tipoId = $cab['cliente_tipo_id'] ?? '05'; // 04=RUC, 05=cédula, 06=pasaporte
        $this->txt($dom, $el, 'tipoIdentificacionComprador', (string)$tipoId);
        $this->txt($dom, $el, 'razonSocialComprador',        $cab['cliente_nombre'] ?? '');
        $this->txt($dom, $el, 'identificacionComprador',     $cab['cliente_ruc'] ?? '');

        $this->txt($dom, $el, 'totalSinImpuestos', $this->dec2($cab['total_sin_impuestos'] ?? 0));
        $this->txt($dom, $el, 'totalDescuento',    $this->dec2($cab['total_descuento']    ?? 0));

        // totalConImpuestos: agrupa impuestos de todos los detalles
        $el->appendChild($this->buildTotalConImpuestos($dom, $detalles));

        $this->txt($dom, $el, 'propina',      $this->dec2($cab['propina']      ?? 0));
        $this->txt($dom, $el, 'importeTotal', $this->dec2($cab['importe_total'] ?? 0));
        $this->txt($dom, $el, 'moneda',       strtoupper($cab['moneda'] ?? 'DOLAR'));

        // Pagos
        $pagosEl = $dom->createElement('pagos');
        foreach ($pagos as $p) {
            $pagoEl = $dom->createElement('pago');
            $this->txt($dom, $pagoEl, 'formaPago', (string)($p['forma_pago'] ?? '01'));
            $this->txt($dom, $pagoEl, 'total',     $this->dec2($p['total'] ?? 0));
            if (!empty($p['plazo']) && (int)$p['plazo'] > 0) {
                $this->txt($dom, $pagoEl, 'plazo',        (string)(int)$p['plazo']);
                $this->txt($dom, $pagoEl, 'unidadTiempo', $p['unidad_tiempo'] ?? 'dias');
            }
            $pagosEl->appendChild($pagoEl);
        }
        $el->appendChild($pagosEl);

        return $el;
    }

    private function buildTotalConImpuestos(\DOMDocument $dom, array $detalles): \DOMElement
    {
        $el = $dom->createElement('totalConImpuestos');

        // Agrupar por (codigo_impuesto, codigo_porcentaje)
        $grupos = [];
        foreach ($detalles as $d) {
            foreach ($d['impuestos'] ?? [] as $imp) {
                $key = ($imp['codigo_impuesto'] ?? '') . '|' . ($imp['codigo_porcentaje'] ?? '');
                if (!isset($grupos[$key])) {
                    $grupos[$key] = [
                        'codigo'           => $imp['codigo_impuesto']   ?? '',
                        'codigoPorcentaje' => $imp['codigo_porcentaje'] ?? '',
                        'baseImponible'    => 0.0,
                        'valor'            => 0.0,
                    ];
                }
                $grupos[$key]['baseImponible'] += (float)($imp['base_imponible'] ?? 0);
                $grupos[$key]['valor']         += (float)($imp['valor']          ?? 0);
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

            $this->txt($dom, $det, 'codigoPrincipal', $d['codigo_principal'] ?? '');
            if (!empty($d['codigo_auxiliar'])) {
                $this->txt($dom, $det, 'codigoAuxiliar', $d['codigo_auxiliar']);
            }
            $this->txt($dom, $det, 'descripcion',           $d['descripcion'] ?? '');
            $this->txt($dom, $det, 'cantidad',              $this->dec6($d['cantidad']      ?? 0));
            $this->txt($dom, $det, 'precioUnitario',        $this->dec6($d['precio_unitario'] ?? 0));
            $this->txt($dom, $det, 'descuento',             $this->dec2($d['descuento']     ?? 0));
            $this->txt($dom, $det, 'precioTotalSinImpuesto',$this->dec2($d['precio_total_sin_impuesto'] ?? 0));

            $impuestosEl = $dom->createElement('impuestos');
            foreach ($d['impuestos'] ?? [] as $imp) {
                $impEl = $dom->createElement('impuesto');
                $this->txt($dom, $impEl, 'codigo',           $imp['codigo_impuesto']   ?? '');
                $this->txt($dom, $impEl, 'codigoPorcentaje', $imp['codigo_porcentaje'] ?? '');
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

    /** Agrega un elemento texto hijo al padre. createTextNode escapa entidades automáticamente. */
    private function txt(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElement($name);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private function dec2(float|string $val): string
    {
        return number_format((float)$val, 2, '.', '');
    }

    private function dec6(float|string $val): string
    {
        return number_format((float)$val, 6, '.', '');
    }
}
