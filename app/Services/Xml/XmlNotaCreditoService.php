<?php

declare(strict_types=1);

namespace App\Services\Xml;

/**
 * Genera el XML de Nota de Crédito según el esquema SRI Ecuador v1.1.0.
 */
class XmlNotaCreditoService
{
    public function generar(
        array $cabecera,
        array $detalles,
        array $infoAdicional,
        array $empresa,
        ?string $dirEstablecimiento = null
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $nc = $dom->createElement('notaCredito');
        $nc->setAttribute('id', 'comprobante');
        $nc->setAttribute('version', '1.1.0');
        $dom->appendChild($nc);

        $nc->appendChild($this->buildInfoTributaria($dom, $cabecera, $empresa));
        $nc->appendChild($this->buildInfoNotaCredito($dom, $cabecera, $detalles, $empresa, $dirEstablecimiento));
        $nc->appendChild($this->buildDetalles($dom, $detalles));

        $infoAdicionalEl = $this->buildInfoAdicional($dom, $infoAdicional);
        if ($infoAdicionalEl !== null) {
            $nc->appendChild($infoAdicionalEl);
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
        $this->txt($dom, $el, 'codDoc',          '04'); // Nota de Crédito
        $this->txt($dom, $el, 'estab',           $cab['establecimiento'] ?? '001');
        $this->txt($dom, $el, 'ptoEmi',          $cab['punto_emision']  ?? '001');
        $this->txt($dom, $el, 'secuencial',      str_pad((string)($cab['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT));
        $this->txt($dom, $el, 'dirMatriz',       $emp['direccion'] ?? '');

        return $el;
    }

    private function buildInfoNotaCredito(
        \DOMDocument $dom,
        array $cab,
        array $detalles,
        array $emp,
        ?string $dirEstablecimiento
    ): \DOMElement {
        $el = $dom->createElement('infoNotaCredito');

        $fechaEmision = !empty($cab['fecha_emision']) ? date('d/m/Y', strtotime($cab['fecha_emision'])) : '';
        $this->txt($dom, $el, 'fechaEmision',        $fechaEmision);
        $this->txt($dom, $el, 'dirEstablecimiento',  $dirEstablecimiento ?? $emp['direccion'] ?? '');

        $tipoId = $cab['cliente_tipo_id'] ?? '05';
        $this->txt($dom, $el, 'tipoIdentificacionComprador', (string)$tipoId);
        $this->txt($dom, $el, 'razonSocialComprador',        $cab['cliente_nombre'] ?? '');
        $this->txt($dom, $el, 'identificacionComprador',     $cab['cliente_ruc'] ?? '');

        if (!empty($cab['contribuyente_especial']) || !empty($emp['contribuyente_especial'])) {
            $this->txt($dom, $el, 'contribuyenteEspecial', $cab['contribuyente_especial'] ?? $emp['contribuyente_especial'] ?? '');
        }

        $obligado = !empty($emp['obligado_contabilidad']) ? strtoupper((string)$emp['obligado_contabilidad']) : 'NO';
        $this->txt($dom, $el, 'obligadoContabilidad', $obligado);

        $this->txt($dom, $el, 'codDocModificado',        '01'); // Factura
        $this->txt($dom, $el, 'numDocModificado',        $cab['num_doc_modificado'] ?? '');
        
        $fechaSustento = !empty($cab['fecha_emision_docs_sustento']) ? date('d/m/Y', strtotime($cab['fecha_emision_docs_sustento'])) : '';
        $this->txt($dom, $el, 'fechaEmisionDocSustento', $fechaSustento);

        $this->txt($dom, $el, 'totalSinImpuestos', $this->dec2($cab['total_sin_impuestos'] ?? 0));
        $this->txt($dom, $el, 'valorModificacion', $this->dec2($cab['importe_total']       ?? 0));
        $this->txt($dom, $el, 'moneda',            strtoupper($cab['moneda'] ?? 'DOLAR'));

        $el->appendChild($this->buildTotalConImpuestos($dom, $detalles));
        $this->txt($dom, $el, 'motivo', $cab['motivo'] ?? 'Devolución');

        return $el;
    }

    private function buildTotalConImpuestos(\DOMDocument $dom, array $detalles): \DOMElement
    {
        $el = $dom->createElement('totalConImpuestos');
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
            $this->txt($dom, $det, 'codigoInterno', $d['codigo_principal'] ?? '');
            $this->txt($dom, $det, 'descripcion',   $d['descripcion'] ?? '');
            $this->txt($dom, $det, 'cantidad',      $this->dec6($d['cantidad']      ?? 0));
            $this->txt($dom, $det, 'precioUnitario', $this->dec6($d['precio_unitario'] ?? 0));
            $this->txt($dom, $det, 'descuento',      $this->dec2($d['descuento']     ?? 0));
            $this->txt($dom, $det, 'precioTotalSinImpuesto', $this->dec2($d['precio_total_sin_impuesto'] ?? 0));

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
        if (empty($infoAdicional)) return null;
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

    private function dec2($val): string { return number_format((float)$val, 2, '.', ''); }
    private function dec6($val): string { return number_format((float)$val, 6, '.', ''); }
}
