<?php
declare(strict_types=1);

namespace App\Services\Xml;

use App\Helpers\SriIvaHelper;
use DOMDocument;
use DOMElement;

/**
 * Genera el XML del Comprobante de Retención en Compras (codDoc = 07)
 * Compatible con SRI Ecuador — esquema OFFLINE v2.0.0 (formato ATS).
 *
 * Desde el 29-nov-2022 el SRI solo autoriza el comprobante de retención en
 * versión 2.0.0, que reemplaza el bloque <impuestos> por <docsSustento> y
 * exige los datos económicos del documento sustento (totales e IVA).
 */
class XmlRetencionCompraService
{
    // Mapeo de codigo_impuesto (BD, texto o numérico) → codigo de impuesto de retención (XML)
    private const COD_IMPUESTO = [
        'RENTA' => '1',
        'IVA'   => '2',
        'ISD'   => '6',
    ];

    // Mapeo de tipo_id_proveedor (BD) → tipoIdentificacionSujetoRetenido (XML, tabla 7)
    private const COD_TIPO_ID = [
        '01' => '04', // RUC
        '02' => '05', // Cédula
        '03' => '06', // Pasaporte
        '04' => '04', // RUC (valor directo)
        '05' => '05', // Cédula (valor directo)
        '06' => '06', // Pasaporte (valor directo)
    ];

    /**
     * @param array      $cabecera           Fila de retencion_compra_cabecera (con joins de proveedor/empresa).
     * @param array      $lineas             Líneas de retención (retencion_compra_detalle).
     * @param array      $empresa            Fila de empresas (agente de retención).
     * @param string|null $dirEstablecimiento Dirección del establecimiento; si null usa empresa.direccion.
     * @param array|null $docSustento        Datos económicos del documento sustento
     *                                       (ver RetencionCompraRepository::getDatosDocSustento).
     */
    public function generar(
        array $cabecera,
        array $lineas,
        array $empresa,
        ?string $dirEstablecimiento = null,
        ?array $docSustento = null
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('comprobanteRetencion');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '2.0.0');
        $dom->appendChild($root);

        $root->appendChild($this->buildInfoTributaria($dom, $cabecera, $empresa));
        $root->appendChild($this->buildInfoCompRetencion($dom, $cabecera, $empresa, $dirEstablecimiento, $docSustento));
        $root->appendChild($this->buildDocsSustento($dom, $cabecera, $lineas, $docSustento));

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

        $this->addChild($dom, $node, 'ambiente',        (string)$tipoAmbiente);
        $this->addChild($dom, $node, 'tipoEmision',     (string)($cab['tipo_emision'] ?? '1'));
        $this->addChild($dom, $node, 'razonSocial',     mb_strtoupper($emp['nombre'] ?? $emp['razon_social'] ?? '', 'UTF-8'));
        $this->addChild($dom, $node, 'nombreComercial',
            mb_strtoupper($emp['nombre_comercial'] ?? $emp['nombre'] ?? '', 'UTF-8'));
        $this->addChild($dom, $node, 'ruc',             (string)($emp['ruc'] ?? ''));
        $this->addChild($dom, $node, 'claveAcceso',     (string)($cab['clave_acceso'] ?? ''));
        $this->addChild($dom, $node, 'codDoc',          '07');
        $this->addChild($dom, $node, 'estab',           str_pad((string)($cab['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT));
        $this->addChild($dom, $node, 'ptoEmi',          str_pad((string)($cab['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT));
        $this->addChild($dom, $node, 'secuencial',      str_pad((string)($cab['secuencial']      ?? '000000001'), 9, '0', STR_PAD_LEFT));
        $this->addChild($dom, $node, 'dirMatriz',       (string)($emp['direccion'] ?? ''));

        // Agente de retención (nº resolución) y régimen RIMPE, al final de
        // infoTributaria (después de dirMatriz), según el XSD del SRI.
        $agente = \App\Helpers\SriEmisorHelper::agenteRetencionNumero($emp);
        if ($agente !== '') {
            $this->addChild($dom, $node, 'agenteRetencion', $agente);
        }
        $regimen = \App\Helpers\SriEmisorHelper::regimenRimpeLeyenda($emp);
        if ($regimen !== '') {
            $this->addChild($dom, $node, 'contribuyenteRimpe', $regimen);
        }

        return $node;
    }

    // ── infoCompRetencion ────────────────────────────────────────

    private function buildInfoCompRetencion(
        DOMDocument $dom,
        array $cab,
        array $emp,
        ?string $dirEstablecimiento,
        ?array $docSustento = null
    ): DOMElement {
        $node = $dom->createElement('infoCompRetencion');

        $this->addChild($dom, $node, 'fechaEmision', $this->fecha($cab['fecha_emision'] ?? ''));

        $dir = $dirEstablecimiento ?? $cab['punto_direccion'] ?? $cab['estab_direccion'] ?? $emp['direccion'] ?? '';
        if ($dir !== '') {
            $this->addChild($dom, $node, 'dirEstablecimiento', (string)$dir);
        }

        // Contribuyente especial: número de resolución (solo si aplica)
        $ce = $emp['resolucion_contribuyente'] ?? $emp['contribuyente_especial']
            ?? $cab['empresa_contribuyente_especial'] ?? '';
        if (!empty($ce)) {
            $this->addChild($dom, $node, 'contribuyenteEspecial', (string)$ce);
        }

        // Obligado a llevar contabilidad
        $olc = $emp['obligado_contabilidad'] ?? $cab['empresa_obligado_contabilidad'] ?? 'NO';
        if (is_bool($olc)) $olc = $olc ? 'SI' : 'NO';
        $this->addChild($dom, $node, 'obligadoContabilidad', strtoupper((string)$olc) === 'SI' ? 'SI' : 'NO');

        // Sujeto retenido (proveedor)
        $tipoIdProv = (string)($cab['proveedor_tipo_id'] ?? '04');
        $tipoProv   = self::COD_TIPO_ID[$tipoIdProv] ?? '04';
        $this->addChild($dom, $node, 'tipoIdentificacionSujetoRetenido', $tipoProv);

        // tipoSujetoRetenido SOLO aplica cuando la identificación es del exterior (08).
        // Para RUC/cédula/pasaporte (nacionales) NO debe especificarse (regla del SRI).
        if ($tipoProv === '08') {
            $this->addChild($dom, $node, 'tipoSujetoRetenido',
                $this->tipoSujetoRetenido($tipoProv, (string)($cab['proveedor_identificacion'] ?? '')));
        }

        // parteRel (SI/NO) — obligatorio en el esquema 2.0.0
        $this->addChild($dom, $node, 'parteRel', strtoupper((string)($docSustento['parteRel'] ?? 'NO')) === 'SI' ? 'SI' : 'NO');

        $this->addChild($dom, $node, 'razonSocialSujetoRetenido',
            mb_strtoupper($cab['proveedor_razon_social'] ?? '', 'UTF-8'));
        $this->addChild($dom, $node, 'identificacionSujetoRetenido',
            (string)($cab['proveedor_identificacion'] ?? ''));
        $this->addChild($dom, $node, 'periodoFiscal', (string)($cab['periodo_fiscal'] ?? ''));

        return $node;
    }

    // ── docsSustento ─────────────────────────────────────────────

    private function buildDocsSustento(
        DOMDocument $dom,
        array $cab,
        array $lineas,
        ?array $docSustento
    ): DOMElement {
        $node = $dom->createElement('docsSustento');
        $doc  = $dom->createElement('docSustento');

        // Código de sustento tributario (tabla 5) — OBLIGATORIO y debe ir primero.
        // Si no se conoce (documento manual o compra sin sustento tributario), se
        // usa 01 (Crédito tributario para declaración de IVA) por defecto.
        $codSustento = $docSustento['codSustento'] ?? null;
        if (empty($codSustento)) $codSustento = '01';
        $this->addChild($dom, $doc, 'codSustento', str_pad((string)$codSustento, 2, '0', STR_PAD_LEFT));

        $this->addChild($dom, $doc, 'codDocSustento', str_pad((string)($cab['tipo_doc_sustento'] ?? '01'), 2, '0', STR_PAD_LEFT));
        $this->addChild($dom, $doc, 'numDocSustento', $this->numDocSustento($cab['num_doc_sustento'] ?? ''));

        // Fecha de emisión del documento sustento: si está vinculado a una compra/liquidación,
        // se usa la fecha REAL de ese documento (la que conoce el SRI); si no, el campo capturado.
        $fechaSustento = !empty($docSustento['fechaEmisionDocSustento'])
            ? $docSustento['fechaEmisionDocSustento']
            : ($cab['fecha_emision_doc_sustento'] ?? '');
        $this->addChild($dom, $doc, 'fechaEmisionDocSustento', $this->fecha($fechaSustento));

        // Fecha de registro contable (opcional)
        $frc = $docSustento['fechaRegistroContable'] ?? null;
        if (!empty($frc)) {
            $this->addChild($dom, $doc, 'fechaRegistroContable', $this->fecha($frc));
        }

        // Número de autorización del documento sustento (opcional)
        $numAut = $docSustento['numAutDocSustento'] ?? ($cab['numero_autorizacion_sustento'] ?? null);
        if (!empty($numAut)) {
            $this->addChild($dom, $doc, 'numAutDocSustento', (string)$numAut);
        }

        $this->addChild($dom, $doc, 'pagoLocExt', (string)($docSustento['pagoLocExt'] ?? '01'));

        // Totales del documento sustento
        $totalSinImp  = (float)($docSustento['totalSinImpuestos'] ?? $this->sumarBases($lineas));
        $importeTotal = (float)($docSustento['importeTotal'] ?? $totalSinImp);
        $this->addChild($dom, $doc, 'totalSinImpuestos', $this->dec2($totalSinImp));
        $this->addChild($dom, $doc, 'importeTotal',      $this->dec2($importeTotal));

        // Impuestos del documento sustento (IVA)
        $doc->appendChild($this->buildImpuestosDocSustento($dom, $docSustento['impuestos'] ?? [], $totalSinImp));

        // Retenciones aplicadas
        $doc->appendChild($this->buildRetenciones($dom, $lineas));

        // Formas de pago del documento sustento (obligatorio en 2.0.0)
        $doc->appendChild($this->buildPagos($dom, $docSustento, $importeTotal));

        $node->appendChild($doc);
        return $node;
    }

    // ── pagos (formas de pago del documento sustento) ────────────

    private function buildPagos(DOMDocument $dom, ?array $docSustento, float $importeTotal): DOMElement
    {
        $node = $dom->createElement('pagos');

        // 01 = Sin utilización del sistema financiero (tabla 24 SRI) por defecto.
        $formaPago = (string)($docSustento['formaPago'] ?? '01');

        $pago = $dom->createElement('pago');
        $this->addChild($dom, $pago, 'formaPago', $formaPago);
        $this->addChild($dom, $pago, 'total',     $this->dec2($importeTotal));
        $node->appendChild($pago);

        return $node;
    }

    // ── impuestosDocSustento (IVA del documento sustento) ─────────

    private function buildImpuestosDocSustento(DOMDocument $dom, array $impuestos, float $totalSinImp): DOMElement
    {
        $node = $dom->createElement('impuestosDocSustento');

        // Fallback: si el documento sustento no aporta impuestos, declarar la base
        // total como tarifa 0 para no violar el XSD (requiere ≥ 1 impuesto).
        if (empty($impuestos)) {
            $impuestos = [[
                'codigo_impuesto'   => '2',
                'codigo_porcentaje' => '0',
                'tarifa'            => 0.0,
                'base_imponible'    => $totalSinImp,
                'valor'             => 0.0,
            ]];
        }

        foreach ($impuestos as $imp) {
            $tarifa = (float)($imp['tarifa'] ?? 0);
            $codPorc = $tarifa > 0
                ? SriIvaHelper::codigoPorcentaje($tarifa)
                : (string)($imp['codigo_porcentaje'] ?? '0');

            $el = $dom->createElement('impuestoDocSustento');
            $this->addChild($dom, $el, 'codImpuestoDocSustento', (string)($imp['codigo_impuesto'] ?? '2'));
            $this->addChild($dom, $el, 'codigoPorcentaje',       $codPorc);
            $this->addChild($dom, $el, 'baseImponible',          $this->dec2((float)($imp['base_imponible'] ?? 0)));
            $this->addChild($dom, $el, 'tarifa',                 $this->dec2($tarifa));
            $this->addChild($dom, $el, 'valorImpuesto',          $this->dec2((float)($imp['valor'] ?? 0)));
            $node->appendChild($el);
        }

        return $node;
    }

    // ── retenciones ──────────────────────────────────────────────

    private function buildRetenciones(DOMDocument $dom, array $lineas): DOMElement
    {
        $node = $dom->createElement('retenciones');

        foreach ($lineas as $linea) {
            $codImp = (string)($linea['codigo_impuesto'] ?? '');
            if (in_array(strtoupper($codImp), ['RENTA', 'IVA', 'ISD'], true)) {
                $codImp = self::COD_IMPUESTO[strtoupper($codImp)] ?? '1';
            }

            $el = $dom->createElement('retencion');
            $this->addChild($dom, $el, 'codigo',            $codImp);
            $this->addChild($dom, $el, 'codigoRetencion',   (string)($linea['codigo_retencion'] ?? ''));
            $this->addChild($dom, $el, 'baseImponible',     $this->dec2((float)($linea['base_imponible'] ?? 0)));
            $this->addChild($dom, $el, 'porcentajeRetener', $this->dec2((float)($linea['porcentaje_retener'] ?? 0)));
            $this->addChild($dom, $el, 'valorRetenido',     $this->dec2((float)($linea['valor_retenido'] ?? 0)));
            $node->appendChild($el);
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

    // ── Utilidades ───────────────────────────────────────────────

    private function addChild(DOMDocument $dom, DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    /**
     * tipoSujetoRetenido (tabla SRI): 01 Persona Natural, 02 Sociedad.
     * Sociedad si el RUC (tipoId 04) tiene 3er dígito 6 (público) o 9 (privada);
     * cédula/pasaporte y RUC de persona natural → 01.
     */
    private function tipoSujetoRetenido(string $tipoIdSri, string $identificacion): string
    {
        if ($tipoIdSri === '04' && strlen($identificacion) >= 3) {
            $tercerDigito = $identificacion[2];
            if ($tercerDigito === '9' || $tercerDigito === '6') {
                return '02';
            }
        }
        return '01';
    }

    /** Convierte Y-m-d (o timestamp) a dd/mm/yyyy exigido por el SRI. */
    private function fecha(string $valor): string
    {
        if ($valor === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            $ts = strtotime(substr($valor, 0, 10));
            return $ts ? date('d/m/Y', $ts) : $valor;
        }
        return $valor;
    }

    /** numDocSustento: exactamente 15 dígitos (estab + ptoEmi + secuencial), sin separadores. */
    private function numDocSustento(string $num): string
    {
        $digitos = preg_replace('/\D/', '', $num) ?? '';
        if (strlen($digitos) > 15) {
            $digitos = substr($digitos, -15); // conservar estab+pto+secuencial
        }
        return str_pad($digitos, 15, '0', STR_PAD_LEFT);
    }

    private function sumarBases(array $lineas): float
    {
        $total = 0.0;
        foreach ($lineas as $l) {
            $total += (float)($l['base_imponible'] ?? 0);
        }
        return $total;
    }

    private function dec2(float $val): string
    {
        return number_format($val, 2, '.', '');
    }
}
