<?php

declare(strict_types=1);

namespace App\Services\Xml;

use DOMDocument;
use DOMElement;

/**
 * Genera el XML del Anexo Transaccional Simplificado (ATS) — raíz <iva>.
 *
 * Esta clase es un serializador "tonto": recibe el informante y un arreglo de
 * documentos YA NORMALIZADOS por AtsService (campos como cadenas en el formato
 * exacto del SRI) y los renderiza respetando el orden de campos de la ficha
 * técnica del SRI.
 *
 * Alcance de esta versión: bloque <compras> (facturas, liquidaciones y N/C–N/D)
 * con su sub-bloque <air> y los datos del comprobante de retención. No incluye
 * ventas, exportaciones, anulados, RECAP, fideicomisos ni rendimientos.
 */
class XmlAtsService
{
    /**
     * @param array $informante Claves: id_informante, razon_social, anio, mes,
     *                          num_estab_ruc, regimen_microempresa (bool)
     * @param array $documentos Lista de documentos normalizados (ver AtsService::mapearDocumento)
     */
    public function generar(array $informante, array $documentos): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        // standalone="no" como en el anexo del SRI
        $dom->xmlStandalone = false;

        $iva = $dom->createElement('iva');
        $dom->appendChild($iva);

        $this->buildInformante($dom, $iva, $informante);

        if ($documentos !== []) {
            $compras = $dom->createElement('compras');
            foreach ($documentos as $doc) {
                $compras->appendChild($this->buildDetalleCompra($dom, $doc));
            }
            $iva->appendChild($compras);
        }

        return $dom->saveXML();
    }

    // ── Informante ───────────────────────────────────────────────────────────

    private function buildInformante(DOMDocument $dom, DOMElement $iva, array $inf): void
    {
        $this->add($dom, $iva, 'TipoIDInformante', 'R');
        $this->add($dom, $iva, 'IdInformante', $inf['id_informante']);
        $this->add($dom, $iva, 'razonSocial', $inf['razon_social']);
        $this->add($dom, $iva, 'Anio', $inf['anio']);
        $this->add($dom, $iva, 'Mes', $inf['mes']);
        if (!empty($inf['regimen_microempresa'])) {
            $this->add($dom, $iva, 'regimenMicroempresa', 'SI');
        }
        $this->add($dom, $iva, 'numEstabRuc', $inf['num_estab_ruc']);
        $this->add($dom, $iva, 'totalVentas', '0.00');
        $this->add($dom, $iva, 'codigoOperativo', 'IVA');
    }

    // ── detalleCompras ───────────────────────────────────────────────────────

    private function buildDetalleCompra(DOMDocument $dom, array $d): DOMElement
    {
        $n = $dom->createElement('detalleCompras');

        $this->add($dom, $n, 'codSustento', $d['codSustento']);
        $this->add($dom, $n, 'tpIdProv', $d['tpIdProv']);
        $this->add($dom, $n, 'idProv', $d['idProv']);
        $this->add($dom, $n, 'tipoComprobante', $d['tipoComprobante']);

        // Solo para compra con pasaporte (tpIdProv = 03) en liquidaciones/notas de venta
        if (!empty($d['tipoProv'])) {
            $this->add($dom, $n, 'tipoProv', $d['tipoProv']);
            $this->add($dom, $n, 'denoProv', $d['denoProv']);
        }

        $this->add($dom, $n, 'parteRel', $d['parteRel']);
        $this->add($dom, $n, 'fechaRegistro', $d['fechaRegistro']);
        $this->add($dom, $n, 'establecimiento', $d['establecimiento']);
        $this->add($dom, $n, 'puntoEmision', $d['puntoEmision']);
        $this->add($dom, $n, 'secuencial', $d['secuencial']);
        $this->add($dom, $n, 'fechaEmision', $d['fechaEmision']);
        $this->add($dom, $n, 'autorizacion', $d['autorizacion']);
        $this->add($dom, $n, 'baseNoGraIva', $d['baseNoGraIva']);
        $this->add($dom, $n, 'baseImponible', $d['baseImponible']);
        $this->add($dom, $n, 'baseImpGrav', $d['baseImpGrav']);
        $this->add($dom, $n, 'baseImpExe', $d['baseImpExe']);
        $this->add($dom, $n, 'montoIce', $d['montoIce']);
        $this->add($dom, $n, 'montoIva', $d['montoIva']);
        $this->add($dom, $n, 'valRetBien10', $d['valRetBien10']);
        $this->add($dom, $n, 'valRetServ20', $d['valRetServ20']);
        $this->add($dom, $n, 'valorRetBienes', $d['valorRetBienes']);
        $this->add($dom, $n, 'valRetServ50', $d['valRetServ50']);
        $this->add($dom, $n, 'valorRetServicios', $d['valorRetServicios']);
        $this->add($dom, $n, 'valRetServ100', $d['valRetServ100']);
        $this->add($dom, $n, 'totbasesImpReemb', '0.00');

        // pagoExterior (obligatorio; pago local por defecto)
        $pe = $dom->createElement('pagoExterior');
        $this->add($dom, $pe, 'pagoLocExt', '01');
        $this->add($dom, $pe, 'paisEfecPago', 'NA');
        $this->add($dom, $pe, 'aplicConvDobTrib', 'NA');
        $this->add($dom, $pe, 'pagExtSujRetNorLeg', 'NA');
        $n->appendChild($pe);

        // Orden de bloques: en notas de débito (05) la forma de pago va primero;
        // en el resto, primero el documento modificado.
        if ($d['tipoComprobante'] === '05') {
            $this->appendFormasDePago($dom, $n, $d);
            $this->appendAir($dom, $n, $d);
            $this->appendDocModificado($dom, $n, $d);
        } else {
            $this->appendDocModificado($dom, $n, $d);
            $this->appendFormasDePago($dom, $n, $d);
            $this->appendAir($dom, $n, $d);
        }

        return $n;
    }

    private function appendFormasDePago(DOMDocument $dom, DOMElement $n, array $d): void
    {
        if (empty($d['formasDePago'])) {
            return;
        }
        $fp = $dom->createElement('formasDePago');
        foreach ($d['formasDePago'] as $codigo) {
            $this->add($dom, $fp, 'formaPago', $codigo);
        }
        $n->appendChild($fp);
    }

    private function appendAir(DOMDocument $dom, DOMElement $n, array $d): void
    {
        if (empty($d['air'])) {
            return;
        }
        $air = $dom->createElement('air');
        foreach ($d['air'] as $linea) {
            $da = $dom->createElement('detalleAir');
            $this->add($dom, $da, 'codRetAir', $linea['codRetAir']);
            $this->add($dom, $da, 'baseImpAir', $linea['baseImpAir']);
            $this->add($dom, $da, 'porcentajeAir', $linea['porcentajeAir']);
            $this->add($dom, $da, 'valRetAir', $linea['valRetAir']);
            $air->appendChild($da);
        }
        $n->appendChild($air);

        // Datos del comprobante de retención (siblings de <air>)
        if (!empty($d['retencionDoc'])) {
            $r = $d['retencionDoc'];
            $this->add($dom, $n, 'estabRetencion1', $r['estab']);
            $this->add($dom, $n, 'ptoEmiRetencion1', $r['pto']);
            $this->add($dom, $n, 'secRetencion1', $r['sec']);
            $this->add($dom, $n, 'autRetencion1', $r['aut']);
            $this->add($dom, $n, 'fechaEmiRet1', $r['fecha']);
        }
    }

    private function appendDocModificado(DOMDocument $dom, DOMElement $n, array $d): void
    {
        if (empty($d['docModificado'])) {
            return;
        }
        $m = $d['docModificado'];
        $this->add($dom, $n, 'docModificado', $m['docModificado']);
        $this->add($dom, $n, 'estabModificado', $m['estabModificado']);
        $this->add($dom, $n, 'ptoEmiModificado', $m['ptoEmiModificado']);
        $this->add($dom, $n, 'secModificado', $m['secModificado']);
        $this->add($dom, $n, 'autModificado', $m['autModificado']);
    }

    // ── helper ───────────────────────────────────────────────────────────────

    private function add(DOMDocument $dom, DOMElement $parent, string $name, $value): void
    {
        $parent->appendChild($dom->createElement($name, htmlspecialchars((string) $value, ENT_XML1, 'UTF-8')));
    }
}
