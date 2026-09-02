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
            $dom = $this->cargarXmlRespuesta($rawXml);
            if ($dom === null) {
                // HTML de mantenimiento, cuerpo vacío, texto plano… no hay nada que parsear.
                $result['errores'][] = $this->errorRespuestaNoXml($rawXml);
                return $result;
            }
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('rec', 'http://ec.gob.sri.ws.recepcion');

            $estadoNodes = $xpath->query('//RespuestaRecepcionComprobante/estado');
            if ($estadoNodes && $estadoNodes->length > 0) {
                $result['estado'] = trim($estadoNodes->item(0)->textContent);
            }

            // Extraer mensajes de error si fue DEVUELTA.
            // Ojo: debe ser 'mensajes/mensaje'. Con '//mensaje' también entra el
            // <mensaje> de texto que va DENTRO de cada mensaje y se cuela una
            // entrada vacía en la lista de errores.
            $mensajes = $xpath->query('//mensajes/mensaje');
            foreach ($mensajes as $m) {
                $result['errores'][] = [
                    'id'      => trim($xpath->query('identificador', $m)->item(0)?->textContent ?? ''),
                    'mensaje' => trim($xpath->query('mensaje',       $m)->item(0)?->textContent ?? ''),
                    'tipo'    => trim($xpath->query('tipo',          $m)->item(0)?->textContent ?? ''),
                    'info'    => trim($xpath->query('informacionAdicional', $m)->item(0)?->textContent ?? ''),
                ];
            }

            // Respuesta sin mensajes y que no fue RECIBIDA: SOAP Fault, XML sin el nodo
            // <estado>, o DEVUELTA sin detalle. Sin esto el usuario ve "devuelta con
            // errores" y una lista vacía (sri_envio_log.detalle_json = []).
            if ($result['estado'] !== 'RECIBIDA' && empty($result['errores'])) {
                $result['errores'][] = $this->errorSinDetalle($xpath, $rawXml, $result['estado']);
            }
        } catch (\Throwable $e) {
            $result['errores'][] = ['id' => '', 'mensaje' => 'Error al parsear respuesta: ' . $e->getMessage(), 'tipo' => 'ERROR', 'info' => $this->fragmentoRespuesta($rawXml)];
        }

        return $result;
    }

    // ── Respuestas no estándar del SRI ────────────────────────────────────────

    /**
     * Carga la respuesta como XML sin emitir warnings de libxml. Devuelve null si el
     * cuerpo está vacío o no es XML bien formado (p. ej. una página HTML del balanceador).
     */
    private function cargarXmlRespuesta(string $rawXml): ?\DOMDocument
    {
        if (trim($rawXml) === '') {
            return null;
        }
        $prev = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            $ok  = $dom->loadXML($rawXml);
            libxml_clear_errors();
            return $ok ? $dom : null;
        } finally {
            libxml_use_internal_errors($prev);
        }
    }

    /** Entrada de error para una respuesta que no es XML (o viene vacía). */
    private function errorRespuestaNoXml(string $rawXml): array
    {
        $frag = $this->fragmentoRespuesta($rawXml);
        return [
            'id'      => '',
            'mensaje' => 'RESPUESTA INESPERADA DEL SRI',
            'tipo'    => 'ERROR',
            'info'    => 'El servicio del SRI no devolvió una respuesta válida (posible mantenimiento o intermitencia). '
                       . 'Intente nuevamente en unos minutos. '
                       . ($frag === '' ? 'Respuesta recibida: (vacía).' : 'Respuesta recibida: ' . $frag),
        ];
    }

    /**
     * Entrada de error cuando el XML es válido pero no trae <mensajes>: SOAP Fault
     * (se rescata el faultstring), estado DEVUELTA sin detalle, o XML sin <estado>.
     */
    private function errorSinDetalle(\DOMXPath $xpath, string $rawXml, string $estado): array
    {
        $fault = $xpath->query('//*[local-name()="faultstring"]')->item(0)?->textContent ?? '';
        $fault = trim($fault);
        if ($fault !== '') {
            return [
                'id'      => '',
                'mensaje' => 'ERROR REPORTADO POR EL SERVICIO DEL SRI',
                'tipo'    => 'ERROR',
                'info'    => $fault . ' — Suele deberse a intermitencia o mantenimiento del SRI; intente nuevamente en unos minutos.',
            ];
        }

        if ($estado === 'DEVUELTA') {
            return [
                'id'      => '',
                'mensaje' => 'DEVUELTA SIN DETALLE',
                'tipo'    => 'ERROR',
                'info'    => 'El SRI devolvió el comprobante pero no incluyó el motivo. '
                           . 'Verifique el estado del comprobante en el portal del SRI y reintente el envío.',
            ];
        }

        return [
            'id'      => '',
            'mensaje' => 'RESPUESTA INESPERADA DEL SRI',
            'tipo'    => 'ERROR',
            'info'    => 'La respuesta del SRI no tiene el formato esperado (sin estado ni mensajes). '
                       . 'Intente nuevamente en unos minutos. Respuesta recibida: ' . $this->fragmentoRespuesta($rawXml),
        ];
    }

    /** Fragmento legible (sin etiquetas, acotado) del cuerpo recibido, para el historial. */
    private function fragmentoRespuesta(string $raw, int $max = 400): string
    {
        $txt = trim(preg_replace('/\s+/', ' ', strip_tags($raw)) ?? '');
        if ($txt === '') {
            return '';
        }
        return mb_strlen($txt) > $max ? mb_substr($txt, 0, $max) . '…' : $txt;
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
            $dom = $this->cargarXmlRespuesta($rawXml);
            if ($dom === null) {
                $result['errores'][] = $this->errorRespuestaNoXml($rawXml);
                return $result;
            }
            $xpath = new \DOMXPath($dom);

            $autorizaciones = $xpath->query('//autorizacion');
            if (!$autorizaciones || $autorizaciones->length === 0) {
                // Un SOAP Fault también cae aquí: rescatar el faultstring para que el
                // historial diga qué respondió el SRI. Sin fault, se mantiene el
                // mensaje histórico (SriEnvioService lo trata como "aún sin resolución").
                $fault = trim($xpath->query('//*[local-name()="faultstring"]')->item(0)?->textContent ?? '');
                $result['errores'][] = $fault !== ''
                    ? ['id' => '', 'mensaje' => 'ERROR REPORTADO POR EL SERVICIO DEL SRI', 'tipo' => 'ERROR', 'info' => $fault]
                    : ['id' => '', 'mensaje' => 'Sin autorizaciones en respuesta', 'tipo' => 'ERROR', 'info' => ''];
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
                    'id'      => trim($xpath->query('identificador',        $m)->item(0)?->textContent ?? ''),
                    'mensaje' => trim($xpath->query('mensaje',             $m)->item(0)?->textContent ?? ''),
                    'tipo'    => trim($xpath->query('tipo',                $m)->item(0)?->textContent ?? ''),
                    'info'    => trim($xpath->query('informacionAdicional', $m)->item(0)?->textContent ?? ''),
                ];
            }

            // Rechazo explícito sin motivo: dejar constancia en vez de una lista vacía.
            if (in_array(strtoupper($estado), ['NO AUTORIZADO', 'RECHAZADO'], true) && empty($result['errores'])) {
                $result['errores'][] = [
                    'id'      => '',
                    'mensaje' => 'NO AUTORIZADO SIN DETALLE',
                    'tipo'    => 'ERROR',
                    'info'    => 'El SRI no autorizó el comprobante pero no incluyó el motivo. Verifique el comprobante en el portal del SRI.',
                ];
            }
        } catch (\Throwable $e) {
            $result['errores'][] = ['id' => '', 'mensaje' => 'Error al parsear respuesta: ' . $e->getMessage(), 'tipo' => 'ERROR', 'info' => $this->fragmentoRespuesta($rawXml)];
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

        // El frente del SRI (balanceador/WAF) a veces no procesa la petición y contesta
        // con un 302 hacia una IP (https://181.113.227.222) que cierra la conexión, o
        // resetea la conexión directamente. Son fallos transitorios: el siguiente intento
        // suele responder bien. Por eso: NO se siguen redirecciones (un WS SOAP nunca
        // redirige legítimamente; seguirla convertía el POST en un GET a esa IP y
        // terminaba en "Connection was reset"), y se reintenta también cuando la
        // respuesta es una redirección o no es un sobre SOAP.
        $maxIntentos = 3;
        $intento = 0;
        $response = false;
        $error = '';
        $httpCode = 0;
        $motivo = '';

        while ($intento < $maxIntentos) {
            $intento++;
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
                CURLOPT_FOLLOWLOCATION => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($response === false || !empty($error)) {
                $motivo = "cURL: $error";
            } elseif ($httpCode >= 300 && $httpCode < 400) {
                $motivo = "HTTP $httpCode (redirección) → " . $this->fragmentoRespuesta($response, 200);
            } elseif ($httpCode < 500 && !str_contains($response, 'Envelope')) {
                $motivo = "HTTP $httpCode sin sobre SOAP → " . $this->fragmentoRespuesta($response, 200);
            } else {
                $motivo = '';
                break; // Respuesta SOAP (o un HTTP 5xx que se evalúa abajo)
            }

            error_log("[SRI soapPost] Intento {$intento}/{$maxIntentos} fallido contra $url: $motivo");
            if ($intento < $maxIntentos) {
                sleep(2); // Esperar 2 segundos antes del siguiente intento
            }
        }

        if ($motivo !== '') {
            // El detalle técnico va solo al log del servidor, no al usuario.
            error_log("[SRI soapPost] Sin respuesta válida de $url tras {$maxIntentos} intentos: $motivo");
            $mensajeUsuario = "No fue posible comunicarse con los servidores del SRI tras {$maxIntentos} intentos. Es muy probable que los servicios del SRI se encuentren intermitentes o en mantenimiento. Intente nuevamente en unos minutos.";
            throw new \RuntimeException($mensajeUsuario);
        }

        if ($httpCode >= 500) {
            // SOAP Fault viene en el cuerpo aunque sea HTTP 500 — retornarlo igual
            // para que parseRecepcionResponse() pueda extraer el mensaje de error
            if (!empty($response) && str_contains($response, 'Fault')) {
                return $response;
            }
            throw new \RuntimeException("Los servicios del SRI reportaron un error interno (HTTP $httpCode). Respuesta: " . substr($response, 0, 800));
        }

        return $response;
    }
}
