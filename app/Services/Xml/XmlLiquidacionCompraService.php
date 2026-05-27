<?php

declare(strict_types=1);

namespace App\Services\Xml;

/**
 * Genera el XML de Liquidación de Compra de Bienes y Prestación de Servicios
 * según el esquema SRI Ecuador v1.1.0 (codDoc = 03).
 *
 * Se emite cuando la empresa compra bienes o servicios a personas naturales
 * que no están obligadas a emitir comprobantes de venta.
 */
class XmlLiquidacionCompraService
{
    /**
     * @param array       $cabecera           Fila de liquidaciones_cabecera (con joins de proveedor)
     * @param array       $detalles           Filas de liquidaciones_detalle, cada una con clave 'impuestos'
     * @param array       $pagos              Filas de liquidaciones_pagos
     * @param array       $infoAdicional      Filas de liquidaciones_adicional
     * @param array       $empresa            Fila de empresas
     * @param string|null $dirEstablecimiento Dirección del establecimiento; si null usa empresa.direccion
     */
    public function generar(
        array   $cabecera,
        array   $detalles,
        array   $pagos,
        array   $infoAdicional,
        array   $empresa,
        ?string $dirEstablecimiento = null
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('liquidacionCompra');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $dom->appendChild($root);

        $root->appendChild($this->buildInfoTributaria($dom, $cabecera, $empresa));
        $root->appendChild($this->buildInfoLiquidacion($dom, $cabecera, $detalles, $pagos, $empresa, $dirEstablecimiento));
        $root->appendChild($this->buildDetalles($dom, $detalles));

        $infoAdicionalEl = $this->buildInfoAdicional($dom, $infoAdicional, $cabecera);
        if ($infoAdicionalEl !== null) {
            $root->appendChild($infoAdicionalEl);
        }

        return $dom->saveXML();
    }

    // ── infoTributaria ────────────────────────────────────────────────────────

    private function buildInfoTributaria(\DOMDocument $dom, array $cab, array $emp): \DOMElement
    {
        $el = $dom->createElement('infoTributaria');

        $this->txt($dom, $el, 'ambiente',        $cab['tipo_ambiente'] ?? '1');
        $this->txt($dom, $el, 'tipoEmision',     $cab['tipo_emision']  ?? '1');
        $this->txt($dom, $el, 'razonSocial',     mb_strtoupper($emp['nombre'] ?? '', 'UTF-8'));
        $this->txt($dom, $el, 'nombreComercial', mb_strtoupper($emp['nombre_comercial'] ?? $emp['nombre'] ?? '', 'UTF-8'));
        $this->txt($dom, $el, 'ruc',             $emp['ruc'] ?? '');
        $this->txt($dom, $el, 'claveAcceso',     $cab['clave_acceso'] ?? '');
        $this->txt($dom, $el, 'codDoc',          '03');
        $this->txt($dom, $el, 'estab',           str_pad((string)($cab['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT));
        $this->txt($dom, $el, 'ptoEmi',          str_pad((string)($cab['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT));
        $this->txt($dom, $el, 'secuencial',      str_pad((string)($cab['secuencial']      ?? ''), 9, '0', STR_PAD_LEFT));
        $this->txt($dom, $el, 'dirMatriz',       $emp['direccion'] ?? '');

        return $el;
    }

    // ── infoLiquidacionCompra ─────────────────────────────────────────────────

    private function buildInfoLiquidacion(
        \DOMDocument $dom,
        array        $cab,
        array        $detalles,
        array        $pagos,
        array        $emp,
        ?string      $dirEstablecimiento
    ): \DOMElement {
        $el = $dom->createElement('infoLiquidacionCompra');

        // Fecha: Y-m-d → dd/mm/yyyy
        $fechaEmision = '';
        if (!empty($cab['fecha_emision'])) {
            $ts = strtotime($cab['fecha_emision']);
            $fechaEmision = $ts ? date('d/m/Y', $ts) : $cab['fecha_emision'];
        }

        $this->txt($dom, $el, 'fechaEmision',       $fechaEmision);
        $this->txt($dom, $el, 'dirEstablecimiento', $dirEstablecimiento ?? $emp['direccion'] ?? '');

        // Contribuyente especial (opcional)
        $ce = $cab['contribuyente_especial'] ?? $emp['contribuyente_especial'] ?? '';
        if (!empty($ce)) {
            $this->txt($dom, $el, 'contribuyenteEspecial', $ce);
        }

        // Obligado a llevar contabilidad
        $olc = $emp['obligado_contabilidad'] ?? 'NO';
        if (is_bool($olc)) $olc = $olc ? 'SI' : 'NO';
        $this->txt($dom, $el, 'obligadoContabilidad', strtoupper($olc) === 'SI' ? 'SI' : 'NO');

        // Tipo de proveedor: 01 = persona natural nacional, 02 = extranjero sin RUC
        $tipoIdProv = (string)($cab['proveedor_tipo_id'] ?? '05');
        $tipoProv   = in_array($tipoIdProv, ['06', '6'], true) ? '02' : '01';
        $this->txt($dom, $el, 'tipoProveedorLiquidacion', $tipoProv);

        // Tipo de identificación del proveedor
        $codTipoId = $this->mapTipoIdentificacion($tipoIdProv);
        $this->txt($dom, $el, 'tipoIdentificacionProveedor', $codTipoId);
        $this->txt($dom, $el, 'razonSocialProveedor',        mb_strtoupper($cab['proveedor_nombre'] ?? '', 'UTF-8'));
        $this->txt($dom, $el, 'identificacionProveedor',     $cab['proveedor_ruc'] ?? '');

        // Código de sustento tributario
        $this->txt($dom, $el, 'codigoSustento', (string)($cab['sustento_codigo'] ?? '01'));

        $this->txt($dom, $el, 'totalSinImpuestos', $this->dec2($cab['total_sin_impuestos'] ?? 0));
        $this->txt($dom, $el, 'totalDescuento',    $this->dec2($cab['total_descuento']     ?? 0));

        // totalConImpuestos (agrupa impuestos de todos los detalles)
        $el->appendChild($this->buildTotalConImpuestos($dom, $detalles));

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

    // ── totalConImpuestos ────────────────────────────────────────────────────

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
                        'tarifa'           => (float)($imp['tarifa']        ?? 0),
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
            $this->txt($dom, $totalImp, 'tarifa',           $this->dec2($g['tarifa']));
            $this->txt($dom, $totalImp, 'valor',            $this->dec2($g['valor']));
            $el->appendChild($totalImp);
        }

        return $el;
    }

    // ── detalles ─────────────────────────────────────────────────────────────

    private function buildDetalles(\DOMDocument $dom, array $detalles): \DOMElement
    {
        $el = $dom->createElement('detalles');

        foreach ($detalles as $d) {
            $det = $dom->createElement('detalle');

            $this->txt($dom, $det, 'codigoPrincipal', (string)($d['codigo_principal'] ?? ''));
            if (!empty($d['codigo_auxiliar'])) {
                $this->txt($dom, $det, 'codigoAuxiliar', $d['codigo_auxiliar']);
            }
            $this->txt($dom, $det, 'descripcion',            mb_strtoupper($d['descripcion'] ?? '', 'UTF-8'));
            $this->txt($dom, $det, 'cantidad',               $this->dec6($d['cantidad']       ?? 0));
            $this->txt($dom, $det, 'precioUnitario',         $this->dec6($d['precio_unitario'] ?? 0));
            $this->txt($dom, $det, 'descuento',              $this->dec2($d['descuento']       ?? 0));
            $this->txt($dom, $det, 'precioTotalSinImpuesto', $this->dec2($d['precio_total_sin_impuesto'] ?? 0));

            $impuestosEl = $dom->createElement('impuestos');
            foreach ($d['impuestos'] ?? [] as $imp) {
                $impEl = $dom->createElement('impuesto');
                $this->txt($dom, $impEl, 'codigo',           (string)($imp['codigo_impuesto']   ?? ''));
                $this->txt($dom, $impEl, 'codigoPorcentaje', (string)($imp['codigo_porcentaje'] ?? ''));
                $this->txt($dom, $impEl, 'tarifa',           $this->dec2($imp['tarifa']         ?? 0));
                $this->txt($dom, $impEl, 'baseImponible',    $this->dec2($imp['base_imponible'] ?? 0));
                $this->txt($dom, $impEl, 'valor',            $this->dec2($imp['valor']          ?? 0));
                $impuestosEl->appendChild($impEl);
            }
            $det->appendChild($impuestosEl);

            $el->appendChild($det);
        }

        return $el;
    }

    // ── infoAdicional ────────────────────────────────────────────────────────

    private function buildInfoAdicional(\DOMDocument $dom, array $infoAdicional, array $cab): ?\DOMElement
    {
        $campos = [];

        // Registros de la tabla liquidaciones_adicional
        foreach ($infoAdicional as $ia) {
            if (!empty($ia['nombre']) && !empty($ia['valor'])) {
                $campos[] = ['nombre' => $ia['nombre'], 'valor' => $ia['valor']];
            }
        }

        // Observaciones de cabecera como campo adicional
        if (!empty($cab['observaciones'])) {
            $campos[] = ['nombre' => 'Observaciones', 'valor' => $cab['observaciones']];
        }

        if (empty($campos)) {
            return null;
        }

        $el = $dom->createElement('infoAdicional');
        foreach ($campos as $c) {
            $campo = $dom->createElement('campoAdicional');
            $campo->setAttribute('nombre', $c['nombre']);
            $campo->appendChild($dom->createTextNode($c['valor']));
            $el->appendChild($campo);
        }

        return $el;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Mapea tipo_id_proveedor de la BD al código SRI de tipoIdentificacionProveedor.
     * Códigos SRI: 04=RUC, 05=Cédula, 06=Pasaporte, 07=Venta a consumidor final,
     *              08=Identificación exterior, 09=Placa.
     */
    private function mapTipoIdentificacion(string $tipoId): string
    {
        return match ($tipoId) {
            '04', '4' => '04', // RUC
            '05', '5' => '05', // Cédula
            '06', '6' => '06', // Pasaporte
            '07', '7' => '07', // Consumidor final
            '08', '8' => '08', // Identificación exterior
            '09', '9' => '09', // Placa
            default   => '05', // Cédula por defecto (persona natural)
        };
    }

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
