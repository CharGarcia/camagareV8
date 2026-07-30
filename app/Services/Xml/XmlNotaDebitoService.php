<?php

declare(strict_types=1);

namespace App\Services\Xml;

/**
 * Genera el XML de Nota de Débito según el esquema SRI Ecuador v1.0.0.
 *
 * A diferencia de la Nota de Crédito, la ND no tiene nodo <detalles> (líneas
 * de producto): en su lugar lleva <motivos> (razón + valor, repetible) y
 * puede llevar <pagos> (forma de pago, repetible, opcional).
 */
class XmlNotaDebitoService
{
    public function generar(
        array $cabecera,
        array $motivos,
        array $impuestos,
        array $pagos,
        array $infoAdicional,
        array $empresa,
        ?string $dirEstablecimiento = null
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $nd = $dom->createElement('notaDebito');
        $nd->setAttribute('id', 'comprobante');
        $nd->setAttribute('version', '1.0.0');
        $dom->appendChild($nd);

        $nd->appendChild($this->buildInfoTributaria($dom, $cabecera, $empresa));
        $nd->appendChild($this->buildInfoNotaDebito($dom, $cabecera, $impuestos, $pagos, $dirEstablecimiento, $empresa));
        $nd->appendChild($this->buildMotivos($dom, $motivos));

        $infoAdicionalEl = $this->buildInfoAdicional($dom, $infoAdicional);
        if ($infoAdicionalEl !== null) {
            $nd->appendChild($infoAdicionalEl);
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
        $this->txt($dom, $el, 'codDoc',          '05'); // Nota de Débito
        $this->txt($dom, $el, 'estab',           $cab['establecimiento'] ?? '001');
        $this->txt($dom, $el, 'ptoEmi',          $cab['punto_emision']  ?? '001');
        $this->txt($dom, $el, 'secuencial',      str_pad((string)($cab['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT));
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

    private function buildInfoNotaDebito(
        \DOMDocument $dom,
        array $cab,
        array $impuestos,
        array $pagos,
        ?string $dirEstablecimiento,
        array $emp
    ): \DOMElement {
        $el = $dom->createElement('infoNotaDebito');

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

        $this->txt($dom, $el, 'codDocModificado', $cab['cod_doc_modificado'] ?? '01'); // Factura
        $this->txt($dom, $el, 'numDocModificado', $this->normalizarNumDoc((string)($cab['num_doc_modificado'] ?? '')));

        $fechaSustento = !empty($cab['fecha_emision_docs_sustento']) ? date('d/m/Y', strtotime($cab['fecha_emision_docs_sustento'])) : '';
        $this->txt($dom, $el, 'fechaEmisionDocSustento', $fechaSustento);

        $this->txt($dom, $el, 'totalSinImpuestos', $this->dec2($cab['total_sin_impuestos'] ?? 0));

        $el->appendChild($this->buildImpuestos($dom, $impuestos));

        $this->txt($dom, $el, 'valorTotal', $this->dec2($cab['importe_total'] ?? 0));

        if (!empty($pagos)) {
            $pagosEl = $dom->createElement('pagos');
            foreach ($pagos as $p) {
                $pagoEl = $dom->createElement('pago');
                $this->txt($dom, $pagoEl, 'formaPago', (string)($p['forma_pago'] ?? ''));
                $this->txt($dom, $pagoEl, 'total',     $this->dec2($p['total'] ?? 0));
                if (!empty($p['plazo'])) {
                    $this->txt($dom, $pagoEl, 'plazo',        $this->dec2($p['plazo']));
                    $this->txt($dom, $pagoEl, 'unidadTiempo', (string)($p['unidad_tiempo'] ?? 'dias'));
                }
                $pagosEl->appendChild($pagoEl);
            }
            $el->appendChild($pagosEl);
        }

        return $el;
    }

    private function normalizarNumDoc(string $num): string
    {
        $num = trim($num);
        if (preg_match('/^(\d{1,3})-(\d{1,3})-(\d{1,9})$/', $num, $m)) {
            return str_pad($m[1], 3, '0', STR_PAD_LEFT) . '-'
                 . str_pad($m[2], 3, '0', STR_PAD_LEFT) . '-'
                 . str_pad($m[3], 9, '0', STR_PAD_LEFT);
        }
        $d = preg_replace('/\D/', '', $num);
        if ($d !== '' && strlen($d) <= 15) {
            $d = str_pad($d, 15, '0', STR_PAD_LEFT);
            return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6, 9);
        }
        return $num;
    }

    private function buildImpuestos(\DOMDocument $dom, array $impuestos): \DOMElement
    {
        $el = $dom->createElement('impuestos');
        foreach ($impuestos as $imp) {
            $impEl = $dom->createElement('impuesto');
            $this->txt($dom, $impEl, 'codigo',           (string)($imp['codigo_impuesto']   ?? ''));
            $this->txt($dom, $impEl, 'codigoPorcentaje', (string)($imp['codigo_porcentaje'] ?? ''));
            $this->txt($dom, $impEl, 'tarifa',           $this->dec2($imp['tarifa']        ?? 0));
            $this->txt($dom, $impEl, 'baseImponible',    $this->dec2($imp['base_imponible'] ?? 0));
            $this->txt($dom, $impEl, 'valor',            $this->dec2($imp['valor']          ?? 0));
            $el->appendChild($impEl);
        }
        return $el;
    }

    private function buildMotivos(\DOMDocument $dom, array $motivos): \DOMElement
    {
        $el = $dom->createElement('motivos');
        foreach ($motivos as $m) {
            $motEl = $dom->createElement('motivo');
            $this->txt($dom, $motEl, 'razon', (string)($m['razon'] ?? ''));
            $this->txt($dom, $motEl, 'valor', $this->dec2($m['valor'] ?? 0));
            $el->appendChild($motEl);
        }
        return $el;
    }

    private function buildInfoAdicional(\DOMDocument $dom, array $infoAdicional): ?\DOMElement
    {
        if (empty($infoAdicional)) return null;
        $el = $dom->createElement('infoAdicional');
        $agregados = 0;
        foreach ($infoAdicional as $ia) {
            $nombre = trim((string)($ia['nombre'] ?? ''));
            $valor  = trim((string)($ia['valor'] ?? ''));
            if ($nombre === '' || $valor === '') continue;
            $campo = $dom->createElement('campoAdicional');
            $campo->setAttribute('nombre', $nombre);
            $campo->appendChild($dom->createTextNode($valor));
            $el->appendChild($campo);
            $agregados++;
            if ($agregados >= 15) break;
        }
        return $agregados > 0 ? $el : null;
    }

    private function txt(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElement($name);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private function dec2($val): string { return number_format((float)$val, 2, '.', ''); }
}
