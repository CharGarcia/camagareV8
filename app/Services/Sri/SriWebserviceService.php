<?php

declare(strict_types=1);

namespace App\Services\Sri;

/**
 * Comunicación SOAP con los WebServices del SRI Ecuador para la
 * recepción y autorización de comprobantes electrónicos (método offline).
 *
 * Endpoints (Ficha Técnica SRI v2.32):
 *   Pruebas    → https://celcer.sri.gob.ec/comprobantes-electronicos-ws/...
 *   Producción → https://cel.sri.gob.ec/comprobantes-electronicos-ws/...
 *
 * Requiere extensión PHP: curl.
 */
class SriWebserviceService
{
    // ── Endpoints ─────────────────────────────────────────────────────────────
    private const ENDPOINTS = [
        '1' => [ // Pruebas
            'recepcion'    => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline',
            'autorizacion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline',
        ],
        '2' => [ // Producción
            'recepcion'    => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline',
            'autorizacion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline',
        ],
    ];

    private int $timeoutSegundos;

    public function __construct(int $timeoutSegundos = 30)
    {
        $this->timeoutSegundos = $timeoutSegundos;
    }

    // ── Recepción ──────────────────────────────────────────────────────────────

    /**
     * Envía el XML firmado al WS de recepción del SRI.
     *
     * @param  string $xmlFirmado   XML firmado en XAdES-BES
     * @param  string $tipoAmbiente '1' pruebas | '2' producción
     * @return array  ['estado' => 'RECIBIDA'|'DEVUELTA', 'errores' => [...]]
     */
    public function enviarRecepcion(string $xmlFirmado, string $tipoAmbiente = '1'): array
    {
        $url  = self::ENDPOINTS[$tipoAmbiente]['recepcion'] ?? self::ENDPOINTS['1']['recepcion'];
        $body = $this->buildRecepcionEnvelope($xmlFirmado);
        $resp = $this->soapPost($url, $body, '');

        return $this->parseRecepcionResponse($resp);
    }

    /**
     * Construye el sobre SOAP para validarComprobante.
     * El parámetro xml es xsd:base64Binary → se codifica en base64.
     */
    private function buildRecepcionEnvelope(string $xml): string
    {
        $xmlB64 = base64_encode($xml);
        return <<<SOAP
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope
    xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:ec="http://ec.gob.sri.ws.recepcion">
  <soapenv:Header/>
  <soapenv:Body>
    <ec:validarComprobante>
      <xml>{$xmlB64}</xml>
    </ec:validarComprobante>
  </soapenv:Body>
</soapenv:Envelope>
SOAP;
    }

    private function parseRecepcionResponse(string $rawXml): array
    {
        $result = ['estado' => 'ERROR', 'errores' => []];

        try {
            $dom = new \DOMDocument();
            $dom->loadXML($rawXml);
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('rec', 'http://ec.gob.sri.ws.recepcion');

            $estadoNodes = $xpath->query('//RespuestaRecepcionComprobante/estado');
            if ($estadoNodes && $estadoNodes->length > 0) {
                $result['estado'] = trim($estadoNodes->item(0)->textContent);
            }

            // Extraer mensajes de error si fue DEVUELTA
            $mensajes = $xpath->query('//mensaje');
            foreach ($mensajes as $m) {
                $result['errores'][] = [
                    'id'      => trim($xpath->query('identificador', $m)->item(0)?->textContent ?? ''),
                    'mensaje' => trim($xpath->query('mensaje',       $m)->item(0)?->textContent ?? ''),
                    'tipo'    => trim($xpath->query('tipo',          $m)->item(0)?->textContent ?? ''),
                    'info'    => trim($xpath->query('informacionAdicional', $m)->item(0)?->textContent ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $result['errores'][] = ['mensaje' => 'Error al parsear respuesta: ' . $e->getMessage(), 'tipo' => 'ERROR'];
        }

        return $result;
    }

    // ── Autorización ──────────────────────────────────────────────────────────

    /**
     * Consulta el estado de autorización de un comprobante en el SRI.
     *
     * @param  string $claveAcceso   Clave de acceso de 49 dígitos
     * @param  string $tipoAmbiente '1' pruebas | '2' producción
     * @return array  ['estado' => 'AUTORIZADO'|'NO AUTORIZADO'|'EN PROCESAMIENTO',
     *                 'numero_autorizacion' => '...',
     *                 'fecha_autorizacion'  => '...',
     *                 'xml_autorizado'      => '...',
     *                 'errores'            => [...]]
     */
    public function consultarAutorizacion(string $claveAcceso, string $tipoAmbiente = '1'): array
    {
        $url  = self::ENDPOINTS[$tipoAmbiente]['autorizacion'] ?? self::ENDPOINTS['1']['autorizacion'];
        $body = $this->buildAutorizacionEnvelope($claveAcceso);
        $resp = $this->soapPost($url, $body, '');

        return $this->parseAutorizacionResponse($resp);
    }

    private function buildAutorizacionEnvelope(string $claveAcceso): string
    {
        return <<<SOAP
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope
    xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:ec="http://ec.gob.sri.ws.autorizacion">
  <soapenv:Header/>
  <soapenv:Body>
    <ec:autorizacionComprobante>
      <claveAccesoComprobante>{$claveAcceso}</claveAccesoComprobante>
    </ec:autorizacionComprobante>
  </soapenv:Body>
</soapenv:Envelope>
SOAP;
    }

    private function parseAutorizacionResponse(string $rawXml): array
    {
        $result = [
            'estado'              => 'ERROR',
            'numero_autorizacion' => '',
            'fecha_autorizacion'  => '',
            'xml_autorizado'      => '',
            'errores'             => [],
        ];

        try {
            $dom = new \DOMDocument();
            $dom->loadXML($rawXml);
            $xpath = new \DOMXPath($dom);

            $autorizaciones = $xpath->query('//autorizacion');
            if (!$autorizaciones || $autorizaciones->length === 0) {
                $result['errores'][] = ['mensaje' => 'Sin autorizaciones en respuesta', 'tipo' => 'ERROR'];
                return $result;
            }

            $aut = $autorizaciones->item(0);

            $estado = trim($xpath->query('estado', $aut)->item(0)?->textContent ?? '');
            $result['estado'] = $estado;

            $numAut = trim($xpath->query('numeroAutorizacion', $aut)->item(0)?->textContent ?? '');
            $result['numero_autorizacion'] = $numAut;

            $fechaAut = trim($xpath->query('fechaAutorizacion', $aut)->item(0)?->textContent ?? '');
            $result['fecha_autorizacion'] = $fechaAut;

            // XML autorizado (viene en CDATA dentro de <comprobante>)
            $compNode = $xpath->query('comprobante', $aut)->item(0);
            if ($compNode) {
                $result['xml_autorizado'] = trim($compNode->textContent);
            }

            // Mensajes / advertencias
            $mensajes = $xpath->query('mensajes/mensaje', $aut);
            foreach ($mensajes as $m) {
                $result['errores'][] = [
                    'id'      => trim($xpath->query('identificador', $m)->item(0)?->textContent ?? ''),
                    'mensaje' => trim($xpath->query('mensaje',       $m)->item(0)?->textContent ?? ''),
                    'tipo'    => trim($xpath->query('tipo',          $m)->item(0)?->textContent ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $result['errores'][] = ['mensaje' => 'Error al parsear respuesta: ' . $e->getMessage(), 'tipo' => 'ERROR'];
        }

        return $result;
    }

    // ── HTTP / cURL ────────────────────────────────────────────────────────────

    /**
     * Realiza una llamada SOAP vía cURL y devuelve el cuerpo de la respuesta.
     */
    private function soapPost(string $url, string $body, string $soapAction = ''): string
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('La extensión cURL no está habilitada en PHP.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=UTF-8',
                'SOAPAction: "' . $soapAction . '"',
                'Content-Length: ' . strlen($body),
            ],
            CURLOPT_SSL_VERIFYPEER => false, // SRI usa certificados auto-firmados en pruebas
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => $this->timeoutSegundos,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new \RuntimeException("Error de conexión al SRI ($url): $error");
        }

        if ($httpCode >= 500) {
            // SOAP Fault viene en el cuerpo aunque sea HTTP 500 — retornarlo igual
            // para que parseRecepcionResponse() pueda extraer el mensaje de error
            if (!empty($response) && str_contains($response, 'Fault')) {
                return $response;
            }
            throw new \RuntimeException("El WS del SRI respondió con HTTP $httpCode. Respuesta: " . substr($response, 0, 800));
        }

        return $response;
    }
}
