<?php
declare(strict_types=1);

namespace App\Services\Xml;

use DOMDocument;
use DOMElement;

/**
 * Genera el XML del Comprobante de Retención en Compras (codDoc = 07)
 * Compatible con SRI Ecuador — esquema v1.0.0
 */
class XmlRetencionCompraService
{
    // Mapeo de impuesto_ret (BD) → codigo (XML SRI)
    private const COD_IMPUESTO = [
        'RENTA' => '1',
        'IVA'   => '2',
        'ISD'   => '6',
    ];

    // Mapeo de tipo_id_proveedor (BD) → tipoIdentificacionSujetoRetenido (XML)
    private const COD_TIPO_ID = [
        '01' => '04', // RUC
        '02' => '05', // Cédula
        '03' => '06', // Pasaporte
        '04' => '04', // RUC (valor directo)
        '05' => '05', // Cédula (valor directo)
        '06' => '06', // Pasaporte (valor directo)
    ];

    public function generar(
        array $cabecera,
        array $lineas,
        array $empresa,
        ?string $dirEstablecimiento = null
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('comprobanteRetencion');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.0.0');
        $dom->appendChild($root);

        $root->appendChild($this->buildInfoTributaria($dom, $cabecera, $empresa));
        $root->appendChild($this->buildInfoCompRetencion($dom, $cabecera, $empresa, $dirEstablecimiento));
        $root->appendChild($this->buildImpuestos($dom, $lineas));

        $infoAdicional = $this->buildInfoAdicional($dom, $cabecera);
        if ($infoAdicional->hasChildNodes()) {
            $root->appendChild($infoAdicional);
        }

        return $dom->saveXML();
    }

    // ── infoTributaria ───────────────────────────────────────────

    private function buildInfoTributaria(DOMDocument $dom, array $cab, array $emp): DOMElement
    {
        $node = $dom->createElement('infoTributaria');

        $tipoAmbiente = $cab['tipo_ambiente'] ?? ($emp['tipo_ambiente'] ?? '1');

        $this->addChild($dom, $node, 'ambiente',        $tipoAmbiente);
        $this->addChild($dom, $node, 'tipoEmision',     $cab['tipo_emision'] ?? '1');
        $this->addChild($dom, $node, 'razonSocial',     mb_strtoupper($emp['nombre'] ?? $emp['razon_social'] ?? '', 'UTF-8'));
        $this->addChild($dom, $node, 'nombreComercial',
            mb_strtoupper($emp['nombre_comercial'] ?? $emp['nombre'] ?? '', 'UTF-8'));
        $this->addChild($dom, $node, 'ruc',             $emp['ruc'] ?? '');
        $this->addChild($dom, $node, 'claveAcceso',     $cab['clave_acceso'] ?? '');
        $this->addChild($dom, $node, 'codDoc',          '07');
        $this->addChild($dom, $node, 'estab',           str_pad($cab['establecimiento'] ?? '001', 3, '0', STR_PAD_LEFT));
        $this->addChild($dom, $node, 'ptoEmi',          str_pad($cab['punto_emision']   ?? '001', 3, '0', STR_PAD_LEFT));
        $this->addChild($dom, $node, 'secuencial',      str_pad($cab['secuencial']      ?? '000000001', 9, '0', STR_PAD_LEFT));
        $this->addChild($dom, $node, 'dirMatriz',       $emp['direccion'] ?? '');

        return $node;
    }

    // ── infoCompRetencion ────────────────────────────────────────

    private function buildInfoCompRetencion(
        DOMDocument $dom,
        array $cab,
        array $emp,
        ?string $dirEstablecimiento
    ): DOMElement {
        $node = $dom->createElement('infoCompRetencion');

        // Fecha emisión: convertir de Y-m-d a dd/mm/yyyy
        $fe = $cab['fecha_emision'] ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fe)) {
            [$y, $m, $d] = explode('-', $fe);
            $fe = "{$d}/{$m}/{$y}";
        }

        $this->addChild($dom, $node, 'fechaEmisionComp', $fe);
        $this->addChild($dom, $node, 'dirEstablecimiento',
            $dirEstablecimiento ?? $cab['punto_direccion'] ?? $emp['direccion'] ?? '');

        // Contribuyente especial (solo si aplica)
        $ce = $emp['contribuyente_especial'] ?? $emp['empresa_contribuyente_especial'] ?? '';
        if (!empty($ce)) {
            $this->addChild($dom, $node, 'contribuyenteEspecial', $ce);
        }

        // Obligado a llevar contabilidad
        $olc = $emp['obligado_contabilidad'] ?? $emp['empresa_obligado_contabilidad'] ?? 'NO';
        if (is_bool($olc)) $olc = $olc ? 'SI' : 'NO';
        $this->addChild($dom, $node, 'obligadoContabilidad', strtoupper($olc) === 'SI' ? 'SI' : 'NO');

        // Sujeto retenido (proveedor)
        $tipoProv = $cab['proveedor_tipo_id'] ?? '04';
        $tipoProv = self::COD_TIPO_ID[$tipoProv] ?? '04';
        $this->addChild($dom, $node, 'tipoIdentificacionSujetoRetenido', $tipoProv);
        $this->addChild($dom, $node, 'razonSocialSujetoRetenido',
            mb_strtoupper($cab['proveedor_razon_social'] ?? '', 'UTF-8'));
        $this->addChild($dom, $node, 'identificacionSujetoRetenido',
            $cab['proveedor_identificacion'] ?? '');

        $this->addChild($dom, $node, 'periodoFiscal', $cab['periodo_fiscal'] ?? '');

        return $node;
    }

    // ── impuestos ────────────────────────────────────────────────

    private function buildImpuestos(DOMDocument $dom, array $lineas): DOMElement
    {
        $node = $dom->createElement('impuestos');

        foreach ($lineas as $linea) {
            $imp = $dom->createElement('impuesto');

            // Código de impuesto según tipo (1=IR, 2=IVA, 6=ISD)
            $codImp = $linea['codigo_impuesto'] ?? '';
            // Si viene como texto (RENTA/IVA/ISD), mapearlo
            if (in_array(strtoupper($codImp), ['RENTA', 'IVA', 'ISD'], true)) {
                $codImp = self::COD_IMPUESTO[strtoupper($codImp)] ?? '1';
            }

            $this->addChild($dom, $imp, 'codigo',             $codImp);
            $this->addChild($dom, $imp, 'codigoRetencion',    $linea['codigo_retencion'] ?? '');
            $this->addChild($dom, $imp, 'baseImponible',      number_format((float)($linea['base_imponible'] ?? 0), 2, '.', ''));
            $this->addChild($dom, $imp, 'porcentajeRetener',  number_format((float)($linea['porcentaje_retener'] ?? 0), 2, '.', ''));
            $this->addChild($dom, $imp, 'valorRetenido',      number_format((float)($linea['valor_retenido'] ?? 0), 2, '.', ''));

            // Documento de sustento por línea
            $this->addChild($dom, $imp, 'codDocSustento',             $linea['cod_doc_sustento'] ?? '01');
            $this->addChild($dom, $imp, 'numDocSustento',             $linea['num_doc_sustento'] ?? '');

            // Fecha doc sustento: Y-m-d → dd/mm/yyyy
            $fds = $linea['fecha_emision_doc_sustento'] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fds)) {
                [$fy, $fm, $fd] = explode('-', $fds);
                $fds = "{$fd}/{$fm}/{$fy}";
            }
            $this->addChild($dom, $imp, 'fechaEmisionDocSustento', $fds);

            $node->appendChild($imp);
        }

        return $node;
    }

    // ── infoAdicional ────────────────────────────────────────────

    private function buildInfoAdicional(DOMDocument $dom, array $cab): DOMElement
    {
        $node = $dom->createElement('infoAdicional');

        if (!empty($cab['observaciones'])) {
            $campo = $dom->createElement('campoAdicional');
            $campo->setAttribute('nombre', 'Observaciones');
            $campo->appendChild($dom->createTextNode($cab['observaciones']));
            $node->appendChild($campo);
        }

        return $node;
    }

    // ── Utilidad ─────────────────────────────────────────────────

    private function addChild(DOMDocument $dom, DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }
}
