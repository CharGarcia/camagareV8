<?php

declare(strict_types=1);

namespace App\Services\Xml;

/**
 * Genera el XML de Guía de Remisión según el esquema SRI Ecuador v1.1.0.
 * codDoc = 06
 */
class XmlGuiaRemisionService
{
    public function generar(
        array  $cabecera,
        array  $detalles,
        array  $infoAdicional,
        array  $empresa,
        ?string $dirEstablecimiento = null
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $gr = $dom->createElement('guiaRemision');
        $gr->setAttribute('id', 'comprobante');
        $gr->setAttribute('version', '1.1.0');
        $dom->appendChild($gr);

        $gr->appendChild($this->buildInfoTributaria($dom, $cabecera, $empresa));
        $gr->appendChild($this->buildInfoGuiaRemision($dom, $cabecera, $empresa, $dirEstablecimiento));
        $gr->appendChild($this->buildDestinatarios($dom, $cabecera, $detalles));

        $infoAdicionalEl = $this->buildInfoAdicional($dom, $infoAdicional);
        if ($infoAdicionalEl !== null) {
            $gr->appendChild($infoAdicionalEl);
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
        $this->txt($dom, $el, 'codDoc',          '06');
        $this->txt($dom, $el, 'estab',           $cab['establecimiento'] ?? '001');
        $this->txt($dom, $el, 'ptoEmi',          $cab['punto_emision']  ?? '001');
        $this->txt($dom, $el, 'secuencial',      str_pad((string)($cab['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT));
        $this->txt($dom, $el, 'dirMatriz',       $emp['direccion'] ?? '');
        return $el;
    }

    private function buildInfoGuiaRemision(
        \DOMDocument $dom,
        array $cab,
        array $emp,
        ?string $dirEstablecimiento
    ): \DOMElement {
        $el = $dom->createElement('infoGuiaRemision');

        $this->txt($dom, $el, 'dirEstablecimiento',             $dirEstablecimiento ?? $emp['direccion'] ?? '');
        $this->txt($dom, $el, 'dirPartida',                     $cab['direccion_partida'] ?? '');
        $this->txt($dom, $el, 'razonSocialTransportista',       $cab['transportista_nombre'] ?? '');
        $this->txt($dom, $el, 'tipoIdentificacionTransportista',$cab['transportista_tipo_id'] ?? '05');
        $this->txt($dom, $el, 'rucTransportista',               $cab['transportista_ruc'] ?? '');

        $obligado = !empty($emp['obligado_contabilidad']) ? strtoupper((string)$emp['obligado_contabilidad']) : 'NO';
        $this->txt($dom, $el, 'obligadoContabilidad', $obligado);

        $fechaIni = !empty($cab['fecha_inicio_transporte']) ? date('d/m/Y', strtotime($cab['fecha_inicio_transporte'])) : '';
        $fechaFin = !empty($cab['fecha_fin_transporte'])    ? date('d/m/Y', strtotime($cab['fecha_fin_transporte']))    : '';
        $this->txt($dom, $el, 'fechaIniTransporte', $fechaIni);
        $this->txt($dom, $el, 'fechaFinTransporte', $fechaFin);
        $this->txt($dom, $el, 'placa',              mb_strtoupper($cab['placa'] ?? ''));

        return $el;
    }

    private function buildDestinatarios(\DOMDocument $dom, array $cab, array $detalles): \DOMElement
    {
        $destinatarios = $dom->createElement('destinatarios');
        $destinatario  = $dom->createElement('destinatario');

        $this->txt($dom, $destinatario, 'identificacionDestinatario', $cab['cliente_ruc']       ?? '');
        $this->txt($dom, $destinatario, 'razonSocialDestinatario',    $cab['cliente_nombre']    ?? '');
        $this->txt($dom, $destinatario, 'dirDestinatario',            $cab['cliente_direccion'] ?? '');
        $this->txt($dom, $destinatario, 'motivoTraslado',             $cab['motivo_traslado']   ?? '');

        if (!empty($cab['doc_aduanero_unico'])) {
            $this->txt($dom, $destinatario, 'docAduaneroUnico', $cab['doc_aduanero_unico']);
        }
        if (!empty($cab['cod_establecimiento_destino'])) {
            $this->txt($dom, $destinatario, 'codEstabDestino', $cab['cod_establecimiento_destino']);
        }
        if (!empty($cab['ruta'])) {
            $this->txt($dom, $destinatario, 'ruta', $cab['ruta']);
        }
        if (!empty($cab['cod_doc_sustento'])) {
            $this->txt($dom, $destinatario, 'codDocSustento', $cab['cod_doc_sustento']);
        }
        if (!empty($cab['num_doc_sustento'])) {
            $this->txt($dom, $destinatario, 'numDocSustento', $cab['num_doc_sustento']);
        }
        if (!empty($cab['num_autorizacion_doc_sustento'])) {
            $this->txt($dom, $destinatario, 'numAutDocSustento', $cab['num_autorizacion_doc_sustento']);
        }
        if (!empty($cab['fecha_emision_doc_sustento'])) {
            $this->txt($dom, $destinatario, 'fechaEmisionDocSustento',
                date('d/m/Y', strtotime($cab['fecha_emision_doc_sustento'])));
        }

        // Detalles de productos
        $detallesEl = $dom->createElement('detalles');
        foreach ($detalles as $d) {
            $det = $dom->createElement('detalle');
            $this->txt($dom, $det, 'codigoInterno', $d['codigo_principal'] ?? '');
            if (!empty($d['codigo_auxiliar'])) {
                $this->txt($dom, $det, 'codigoAdicional', $d['codigo_auxiliar']);
            }
            $this->txt($dom, $det, 'descripcion', $d['descripcion'] ?? '');
            $this->txt($dom, $det, 'cantidad',    $this->dec6((float)($d['cantidad'] ?? 0)));
            $detallesEl->appendChild($det);
        }
        $destinatario->appendChild($detallesEl);
        $destinatarios->appendChild($destinatario);

        return $destinatarios;
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

    private function dec6(float $v): string { return number_format($v, 6, '.', ''); }
}
