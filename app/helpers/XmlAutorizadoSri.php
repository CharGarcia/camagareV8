<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Formato de ENTREGA del XML autorizado: el mismo que descarga el portal SRI en línea.
 *
 * El sistema guarda en `detalle_xml` un sobre propio (`<autorizaciones><autorizacion>…`,
 * `<ambiente>PRODUCCION</ambiente>` sin tilde; ver SriEnvioService::buildXmlDetalleCompleto).
 * Hay receptores que no lo aceptan (DINADEC, 22-10-2026), así que al entregarlo se
 * reescribe tal como lo descarga el SRI (muestra del portal, 22-10-2026):
 *
 *   <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
 *   <autorizacion>
 *   <estado>AUTORIZADO</estado>
 *   <numeroAutorizacion>…</numeroAutorizacion>
 *   <fechaAutorizacion>…</fechaAutorizacion>
 *   <ambiente>PRODUCCIÓN</ambiente>
 *   <comprobante><![CDATA[<?xml …?><factura …>…</factura>]]></comprobante>
 *   <mensajes/>
 *   </autorizacion>
 *
 * El nombre del archivo NO cambia: cada módulo conserva el suyo (`factura_001-001-…xml`).
 *
 * La conversión se hace SOLO al entregar (descarga, correo, descarga masiva); lo guardado
 * en BD no cambia, porque otros módulos leen ese sobre. El comprobante firmado viaja
 * intacto (mismo texto, misma firma): solo cambia el sobre que lo envuelve.
 */
final class XmlAutorizadoSri
{
    /**
     * Convierte el XML guardado al formato de descarga del SRI.
     * Acepta el sobre propio, la raíz `<autorizacion>` o una respuesta del WS
     * (`RespuestaAutorizacionComprobante` / `respuestaComprobante`). Devuelve el XML sin
     * cambios si es un comprobante sin sobre de autorización (no autorizado) o si no se
     * puede leer.
     */
    public static function formatoSri(string $xml): string
    {
        $xml = (string) preg_replace('/^\xEF\xBB\xBF/', '', $xml);
        if (trim($xml) === '' || stripos($xml, '<autorizacion') === false) {
            return $xml;
        }

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE)) {
            return $xml;
        }

        // El portal entrega UNA autorización: la AUTORIZADO si hay varias, si no la primera.
        $elegida = null;
        foreach ($dom->getElementsByTagName('autorizacion') as $aut) {
            $estado = '';
            foreach ($aut->childNodes as $h) {
                if ($h instanceof \DOMElement && $h->localName === 'estado') {
                    $estado = strtoupper(trim($h->textContent));
                }
            }
            if ($elegida === null || ($estado === 'AUTORIZADO' && $elegida[0] !== 'AUTORIZADO')) {
                $elegida = [$estado, $aut];
            }
        }
        if ($elegida === null) {
            return $xml;
        }

        $campos = ['estado' => '', 'numeroAutorizacion' => '', 'fechaAutorizacion' => '', 'ambiente' => '', 'comprobante' => ''];
        $mensajes = '';
        foreach ($elegida[1]->childNodes as $hijo) {
            if (!$hijo instanceof \DOMElement) {
                continue;
            }
            if (array_key_exists($hijo->localName, $campos)) {
                $campos[$hijo->localName] = $hijo->textContent;
            } elseif ($hijo->localName === 'mensajes') {
                $mensajes = self::mensajes($dom, $hijo);
            }
        }
        $comprobante = trim($campos['comprobante']);
        if ($comprobante === '' || preg_match('//u', $comprobante) !== 1) {
            return $xml; // no arriesgar el contenido firmado
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . "<autorizacion>\n"
            . '<estado>' . self::esc(trim($campos['estado'])) . "</estado>\n"
            . '<numeroAutorizacion>' . self::esc(trim($campos['numeroAutorizacion'])) . "</numeroAutorizacion>\n"
            . '<fechaAutorizacion>' . self::esc(trim($campos['fechaAutorizacion'])) . "</fechaAutorizacion>\n"
            . '<ambiente>' . self::ambiente($campos['ambiente'], $comprobante) . "</ambiente>\n"
            . '<comprobante>' . self::cdata($comprobante) . "</comprobante>\n"
            . ($mensajes !== '' ? $mensajes : '<mensajes/>') . "\n"
            . "</autorizacion>\n";
    }

    /**
     * Envía el XML como descarga en formato SRI, con el nombre de siempre del módulo
     * (p. ej. `factura_001-001-000000009.xml`). No hace exit: lo decide quien llama.
     */
    public static function descargar(string $xml, string $nombre): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        echo self::formatoSri($xml);
    }

    /** `PRODUCCIÓN` / `PRUEBAS` como lo escribe el SRI; si el sobre no lo trae, del comprobante. */
    private static function ambiente(string $sobre, string $comprobante): string
    {
        $a = strtoupper(trim($sobre));
        if ($a === '' && preg_match('/<ambiente>\s*([12])\s*<\/ambiente>/', $comprobante, $m)) {
            $a = $m[1] === '2' ? 'PRODUCCION' : 'PRUEBAS';
        }
        if (str_starts_with($a, 'PRODUCC') || $a === '2') {
            return 'PRODUCCIÓN';
        }
        if (str_starts_with($a, 'PRUEBA') || $a === '1') {
            return 'PRUEBAS';
        }
        return self::esc(trim($sobre));
    }

    /** CDATA; un `]]>` dentro del texto se parte en dos secciones (no debería ocurrir). */
    private static function cdata(string $texto): string
    {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $texto) . ']]>';
    }

    private static function esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_NOQUOTES, 'UTF-8');
    }

    /** `<mensajes>` con su contenido tal cual; vacío → ''. */
    private static function mensajes(\DOMDocument $dom, \DOMElement $nodo): string
    {
        foreach ($nodo->childNodes as $h) {
            if ($h instanceof \DOMElement) {
                return (string) $dom->saveXML($nodo);
            }
        }
        return '';
    }
}
