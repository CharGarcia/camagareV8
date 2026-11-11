<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\ComprobantePdf;
use DOMDocument;
use DOMElement;

/**
 * Arma, con los datos leídos de un PDF y confirmados por el usuario, un XML con
 * la estructura de los comprobantes del SRI dentro de su sobre de autorización.
 *
 * El registro del sistema (DocumentoAutomatedRegisterService) trabaja a partir
 * de ese XML, así que al entregárselo se reutilizan todas sus reglas
 * (duplicados, ignorados, periodo cerrado, retención y egreso automáticos,
 * aprobación de compras) sin duplicar lógica.
 *
 * El resultado NO es el XML firmado del SRI: lo genera el sistema y lleva un
 * comentario que lo dice. Solo lo consume el registro y queda como respaldo.
 */
final class DescargaPdfXmlBuilder
{
    public const MARCA = 'Generado por el sistema a partir del PDF (RIDE) del comprobante. No es el XML firmado por el emisor.';

    /** @param array $doc Documento normalizado y validado (ComprobantePdf::normalizar + DescargaPdfRules). */
    public static function construir(array $doc): string
    {
        $comprobante = match ($doc['cod_doc']) {
            '01' => self::factura($doc),
            '03' => self::liquidacion($doc),
            '04' => self::notaCredito($doc),
            '05' => self::notaDebito($doc),
            '07' => self::retencion($doc),
            default => throw new \InvalidArgumentException('Tipo de comprobante no soportado.'),
        };

        $clave = (string) $doc['clave_acceso'];
        $numAut = $doc['numero_autorizacion'] !== '' ? (string) $doc['numero_autorizacion'] : $clave;
        $partes = ComprobantePdf::desglosarClave($clave);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->appendChild($dom->createComment(' ' . self::MARCA . ' '));
        $aut = $dom->createElement('autorizacion');
        $dom->appendChild($aut);
        self::nodo($aut, 'estado', 'AUTORIZADO');
        self::nodo($aut, 'numeroAutorizacion', $numAut);
        self::nodo($aut, 'fechaAutorizacion', self::fechaIso($doc['fecha_autorizacion'] ?: $doc['fecha_emision']));
        self::nodo($aut, 'ambiente', ($partes['ambiente'] ?? '2') === '1' ? 'PRUEBAS' : 'PRODUCCIÓN');
        $comp = $dom->createElement('comprobante');
        $comp->appendChild($dom->createCDATASection($comprobante));
        $aut->appendChild($comp);
        $aut->appendChild($dom->createElement('mensajes'));

        return (string) $dom->saveXML();
    }

    // ── Comprobantes ─────────────────────────────────────────────────────────

    private static function factura(array $d): string
    {
        [$dom, $raiz] = self::raiz('factura', '1.1.0');
        self::infoTributaria($raiz, $d);

        $info = self::hijo($raiz, 'infoFactura');
        self::nodo($info, 'fechaEmision', $d['fecha_emision']);
        self::nodoOpc($info, 'dirEstablecimiento', $d['emisor']['dir_establecimiento']);
        self::nodoOpc($info, 'obligadoContabilidad', $d['emisor']['obligado_contabilidad']);
        self::nodo($info, 'tipoIdentificacionComprador', ComprobantePdf::tipoIdentificacion($d['receptor']['identificacion']));
        self::nodo($info, 'razonSocialComprador', $d['receptor']['razon_social']);
        self::nodo($info, 'identificacionComprador', $d['receptor']['identificacion']);
        self::nodoOpc($info, 'direccionComprador', $d['receptor']['direccion']);
        self::nodo($info, 'totalSinImpuestos', self::n($d['totales']['subtotal']));
        self::nodo($info, 'totalDescuento', self::n($d['totales']['descuento']));
        self::totalConImpuestos($info, $d);
        self::nodo($info, 'propina', self::n($d['totales']['propina']));
        self::nodo($info, 'importeTotal', self::n($d['totales']['total']));
        self::nodo($info, 'moneda', 'DOLAR');
        self::pagos($info, $d);

        self::detalles($raiz, $d, 'codigoPrincipal', 'codigoAuxiliar');
        self::infoAdicional($raiz, $d);
        return (string) $dom->saveXML();
    }

    private static function liquidacion(array $d): string
    {
        [$dom, $raiz] = self::raiz('liquidacionCompra', '1.1.0');
        self::infoTributaria($raiz, $d);

        $info = self::hijo($raiz, 'infoLiquidacionCompra');
        self::nodo($info, 'fechaEmision', $d['fecha_emision']);
        self::nodoOpc($info, 'dirEstablecimiento', $d['emisor']['dir_establecimiento']);
        self::nodoOpc($info, 'obligadoContabilidad', $d['emisor']['obligado_contabilidad']);
        self::nodo($info, 'tipoIdentificacionProveedor', ComprobantePdf::tipoIdentificacion($d['receptor']['identificacion']));
        self::nodo($info, 'razonSocialProveedor', $d['receptor']['razon_social']);
        self::nodo($info, 'identificacionProveedor', $d['receptor']['identificacion']);
        self::nodoOpc($info, 'direccionProveedor', $d['receptor']['direccion']);
        self::nodo($info, 'totalSinImpuestos', self::n($d['totales']['subtotal']));
        self::nodo($info, 'totalDescuento', self::n($d['totales']['descuento']));
        self::totalConImpuestos($info, $d);
        self::nodo($info, 'importeTotal', self::n($d['totales']['total']));
        self::nodo($info, 'moneda', 'DOLAR');
        self::pagos($info, $d);

        self::detalles($raiz, $d, 'codigoPrincipal', 'codigoAuxiliar');
        self::infoAdicional($raiz, $d);
        return (string) $dom->saveXML();
    }

    private static function notaCredito(array $d): string
    {
        [$dom, $raiz] = self::raiz('notaCredito', '1.1.0');
        self::infoTributaria($raiz, $d);

        $info = self::hijo($raiz, 'infoNotaCredito');
        self::nodo($info, 'fechaEmision', $d['fecha_emision']);
        self::nodoOpc($info, 'dirEstablecimiento', $d['emisor']['dir_establecimiento']);
        self::nodo($info, 'tipoIdentificacionComprador', ComprobantePdf::tipoIdentificacion($d['receptor']['identificacion']));
        self::nodo($info, 'razonSocialComprador', $d['receptor']['razon_social']);
        self::nodo($info, 'identificacionComprador', $d['receptor']['identificacion']);
        self::nodoOpc($info, 'obligadoContabilidad', $d['emisor']['obligado_contabilidad']);
        self::nodo($info, 'codDocModificado', $d['doc_modificado']['cod_doc'] ?: '01');
        self::nodo($info, 'numDocModificado', $d['doc_modificado']['numero']);
        self::nodo($info, 'fechaEmisionDocSustento', $d['doc_modificado']['fecha']);
        self::nodo($info, 'totalSinImpuestos', self::n($d['totales']['subtotal']));
        self::nodo($info, 'valorModificacion', self::n($d['totales']['total']));
        self::nodo($info, 'moneda', 'DOLAR');
        self::totalConImpuestos($info, $d);
        self::nodo($info, 'motivo', $d['doc_modificado']['motivo']);

        self::detalles($raiz, $d, 'codigoInterno', 'codigoAdicional');
        self::infoAdicional($raiz, $d);
        return (string) $dom->saveXML();
    }

    private static function notaDebito(array $d): string
    {
        [$dom, $raiz] = self::raiz('notaDebito', '1.0.0');
        self::infoTributaria($raiz, $d);

        $info = self::hijo($raiz, 'infoNotaDebito');
        self::nodo($info, 'fechaEmision', $d['fecha_emision']);
        self::nodoOpc($info, 'dirEstablecimiento', $d['emisor']['dir_establecimiento']);
        self::nodo($info, 'tipoIdentificacionComprador', ComprobantePdf::tipoIdentificacion($d['receptor']['identificacion']));
        self::nodo($info, 'razonSocialComprador', $d['receptor']['razon_social']);
        self::nodo($info, 'identificacionComprador', $d['receptor']['identificacion']);
        self::nodoOpc($info, 'obligadoContabilidad', $d['emisor']['obligado_contabilidad']);
        self::nodo($info, 'codDocModificado', $d['doc_modificado']['cod_doc'] ?: '01');
        self::nodo($info, 'numDocModificado', $d['doc_modificado']['numero']);
        self::nodo($info, 'fechaEmisionDocSustento', $d['doc_modificado']['fecha']);
        self::nodo($info, 'totalSinImpuestos', self::n($d['totales']['subtotal']));

        // En la nota de débito los impuestos van en la cabecera, sin desglose por motivo.
        $grupos = $d['impuestos'] ?: [['cod_iva' => '0', 'base' => $d['totales']['subtotal'], 'valor' => 0.0]];
        $imps = self::hijo($info, 'impuestos');
        foreach ($grupos as $g) {
            $imp = self::hijo($imps, 'impuesto');
            self::nodo($imp, 'codigo', '2');
            self::nodo($imp, 'codigoPorcentaje', $g['cod_iva']);
            self::nodo($imp, 'tarifa', self::n(ComprobantePdf::tarifaIva($g['cod_iva'])));
            self::nodo($imp, 'baseImponible', self::n($g['base']));
            self::nodo($imp, 'valor', self::n($g['valor']));
        }
        self::nodo($info, 'valorTotal', self::n($d['totales']['total']));
        self::pagos($info, $d);

        $motivos = self::hijo($raiz, 'motivos');
        foreach ($d['motivos'] as $m) {
            $mo = self::hijo($motivos, 'motivo');
            self::nodo($mo, 'razon', $m['razon']);
            self::nodo($mo, 'valor', self::n($m['valor']));
        }
        self::infoAdicional($raiz, $d);
        return (string) $dom->saveXML();
    }

    private static function retencion(array $d): string
    {
        [$dom, $raiz] = self::raiz('comprobanteRetencion', '1.0.0');
        self::infoTributaria($raiz, $d);

        $info = self::hijo($raiz, 'infoCompRetencion');
        self::nodo($info, 'fechaEmision', $d['fecha_emision']);
        self::nodoOpc($info, 'dirEstablecimiento', $d['emisor']['dir_establecimiento']);
        self::nodoOpc($info, 'obligadoContabilidad', $d['emisor']['obligado_contabilidad']);
        self::nodo($info, 'tipoIdentificacionSujetoRetenido', ComprobantePdf::tipoIdentificacion($d['receptor']['identificacion']));
        self::nodo($info, 'razonSocialSujetoRetenido', $d['receptor']['razon_social']);
        self::nodo($info, 'identificacionSujetoRetenido', $d['receptor']['identificacion']);
        self::nodo($info, 'periodoFiscal', $d['periodo_fiscal']);

        $imps = self::hijo($raiz, 'impuestos');
        foreach ($d['retenciones'] as $r) {
            $imp = self::hijo($imps, 'impuesto');
            self::nodo($imp, 'codigo', ComprobantePdf::IMPUESTOS_RETENCION[$r['impuesto']] ?? '1');
            self::nodo($imp, 'codigoRetencion', $r['codigo_retencion']);
            self::nodo($imp, 'baseImponible', self::n($r['base']));
            self::nodo($imp, 'porcentajeRetener', self::n($r['porcentaje']));
            self::nodo($imp, 'valorRetenido', self::n($r['valor']));
            // Los datos del sustento son opcionales en la versión 1.0.0: lo que el PDF
            // no trae se omite (no se rellena con valores supuestos).
            self::nodoOpc($imp, 'codDocSustento', $r['cod_doc_sustento']);
            self::nodoOpc($imp, 'numDocSustento', ComprobantePdf::digitos($r['num_doc_sustento']));
            self::nodoOpc($imp, 'fechaEmisionDocSustento', $r['fecha_doc_sustento']);
        }
        self::infoAdicional($raiz, $d);
        return (string) $dom->saveXML();
    }

    // ── Bloques comunes ──────────────────────────────────────────────────────

    /** @return array{0: DOMDocument, 1: DOMElement} */
    private static function raiz(string $nombre, string $version): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $raiz = $dom->createElement($nombre);
        $raiz->setAttribute('id', 'comprobante');
        $raiz->setAttribute('version', $version);
        $dom->appendChild($raiz);
        return [$dom, $raiz];
    }

    private static function infoTributaria(DOMElement $raiz, array $d): void
    {
        $partes = ComprobantePdf::desglosarClave((string) $d['clave_acceso']);
        $it = self::hijo($raiz, 'infoTributaria');
        self::nodo($it, 'ambiente', $partes['ambiente'] ?? '2');
        self::nodo($it, 'tipoEmision', '1');
        self::nodo($it, 'razonSocial', $d['emisor']['razon_social']);
        self::nodoOpc($it, 'nombreComercial', $d['emisor']['nombre_comercial']);
        self::nodo($it, 'ruc', $d['emisor']['ruc']);
        self::nodo($it, 'claveAcceso', $d['clave_acceso']);
        self::nodo($it, 'codDoc', $d['cod_doc']);
        self::nodo($it, 'estab', $d['estab']);
        self::nodo($it, 'ptoEmi', $d['pto_emi']);
        self::nodo($it, 'secuencial', $d['secuencial']);
        self::nodo($it, 'dirMatriz', $d['emisor']['dir_matriz'] ?: $d['emisor']['dir_establecimiento']);
    }

    /**
     * IVA del total por tarifa: lo que el comprobante declara en sus totales o,
     * si la pantalla no lo trae, lo que resulta de las líneas.
     * @return list<array{cod_iva:string, base:float, valor:float}>
     */
    public static function gruposIva(array $d): array
    {
        if ($d['impuestos'] !== []) {
            return $d['impuestos'];
        }
        $bases = [];
        foreach ($d['detalles'] as $l) {
            $bases[$l['cod_iva']] = ($bases[$l['cod_iva']] ?? 0.0) + $l['subtotal'];
        }
        $out = [];
        foreach ($bases as $cod => $base) {
            $cod = (string) $cod;
            $out[] = ['cod_iva' => $cod, 'base' => round($base, 2),
                      'valor' => round(round($base, 2) * ComprobantePdf::tarifaIva($cod) / 100, 2)];
        }
        return $out;
    }

    private static function totalConImpuestos(DOMElement $info, array $d): void
    {
        $tci = self::hijo($info, 'totalConImpuestos');
        foreach (self::gruposIva($d) as $g) {
            $ti = self::hijo($tci, 'totalImpuesto');
            self::nodo($ti, 'codigo', '2');
            self::nodo($ti, 'codigoPorcentaje', $g['cod_iva']);
            self::nodo($ti, 'baseImponible', self::n($g['base']));
            self::nodo($ti, 'valor', self::n($g['valor']));
        }
    }

    /**
     * Líneas con su IVA. El IVA de cada línea es base × tarifa; la diferencia de
     * centavos contra el IVA del total (que el emisor calcula sobre el subtotal)
     * se carga a la última línea de esa tarifa, para que detalle y total cuadren.
     */
    private static function detalles(DOMElement $raiz, array $d, string $tagCodigo, string $tagAuxiliar): void
    {
        $ivaLinea = [];
        $sumaPorCod = [];
        $ultimaPorCod = [];
        foreach ($d['detalles'] as $i => $l) {
            $ivaLinea[$i] = round($l['subtotal'] * ComprobantePdf::tarifaIva($l['cod_iva']) / 100, 2);
            $sumaPorCod[$l['cod_iva']] = ($sumaPorCod[$l['cod_iva']] ?? 0.0) + $ivaLinea[$i];
            $ultimaPorCod[$l['cod_iva']] = $i;
        }
        foreach (self::gruposIva($d) as $g) {
            $cod = $g['cod_iva'];
            if (!isset($ultimaPorCod[$cod])) {
                continue;
            }
            $dif = round($g['valor'] - $sumaPorCod[$cod], 2);
            $tope = max(0.05, 0.01 * count($d['detalles']));
            if ($dif != 0.0 && abs($dif) <= $tope && $ivaLinea[$ultimaPorCod[$cod]] + $dif >= 0) {
                $ivaLinea[$ultimaPorCod[$cod]] = round($ivaLinea[$ultimaPorCod[$cod]] + $dif, 2);
            }
        }

        $dets = self::hijo($raiz, 'detalles');
        foreach ($d['detalles'] as $i => $l) {
            $det = self::hijo($dets, 'detalle');
            self::nodoOpc($det, $tagCodigo, $l['codigo']);
            self::nodoOpc($det, $tagAuxiliar, $l['codigo_auxiliar']);
            self::nodo($det, 'descripcion', $l['descripcion']);
            self::nodo($det, 'cantidad', self::n($l['cantidad'], 6));
            self::nodo($det, 'precioUnitario', self::n($l['precio_unitario'], 6));
            self::nodo($det, 'descuento', self::n($l['descuento']));
            self::nodo($det, 'precioTotalSinImpuesto', self::n($l['subtotal']));
            $imps = self::hijo($det, 'impuestos');
            $imp = self::hijo($imps, 'impuesto');
            self::nodo($imp, 'codigo', '2');
            self::nodo($imp, 'codigoPorcentaje', $l['cod_iva']);
            self::nodo($imp, 'tarifa', self::n(ComprobantePdf::tarifaIva($l['cod_iva'])));
            self::nodo($imp, 'baseImponible', self::n($l['subtotal']));
            self::nodo($imp, 'valor', self::n($ivaLinea[$i]));
        }
    }

    private static function pagos(DOMElement $info, array $d): void
    {
        if ($d['pagos'] === []) {
            return;
        }
        $pagos = self::hijo($info, 'pagos');
        foreach ($d['pagos'] as $p) {
            $pago = self::hijo($pagos, 'pago');
            self::nodo($pago, 'formaPago', $p['forma_pago']);
            self::nodo($pago, 'total', self::n($p['total']));
            if ($p['plazo'] > 0) {
                self::nodo($pago, 'plazo', (string) $p['plazo']);
                self::nodo($pago, 'unidadTiempo', $p['unidad_tiempo']);
            }
        }
    }

    private static function infoAdicional(DOMElement $raiz, array $d): void
    {
        if ($d['info_adicional'] === []) {
            return;
        }
        $ia = self::hijo($raiz, 'infoAdicional');
        foreach ($d['info_adicional'] as $c) {
            $campo = self::nodo($ia, 'campoAdicional', $c['valor']);
            $campo->setAttribute('nombre', $c['nombre']);
        }
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    private static function hijo(DOMElement $padre, string $nombre): DOMElement
    {
        $el = $padre->ownerDocument->createElement($nombre);
        $padre->appendChild($el);
        return $el;
    }

    private static function nodo(DOMElement $padre, string $nombre, string $valor): DOMElement
    {
        $el = $padre->ownerDocument->createElement($nombre);
        $el->appendChild($padre->ownerDocument->createTextNode($valor));
        $padre->appendChild($el);
        return $el;
    }

    private static function nodoOpc(DOMElement $padre, string $nombre, string $valor): void
    {
        if (trim($valor) !== '') {
            self::nodo($padre, $nombre, $valor);
        }
    }

    private static function n(float $v, int $decimales = 2): string
    {
        return number_format($v, $decimales, '.', '');
    }

    /** "d/m/Y [H:i:s]" → ISO 8601 con la zona de Ecuador, como lo entrega el SRI. */
    private static function fechaIso(string $fecha): string
    {
        $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $fecha, new \DateTimeZone('America/Guayaquil'))
            ?: \DateTime::createFromFormat('!d/m/Y', $fecha, new \DateTimeZone('America/Guayaquil'));
        return $dt ? $dt->format('Y-m-d\TH:i:sP') : '';
    }
}
