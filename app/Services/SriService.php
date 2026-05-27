<?php

declare(strict_types=1);

namespace App\Services;

use SoapClient;
use Exception;

class SriService
{
    /**
     * Consulta el documento autorizado al SRI mediante la clave de acceso 
     * y retorna el XML completo.
     *
     * @param string $claveAcceso
     * @param string $ambiente 'produccion' o 'pruebas' (por defecto producción)
     * @return array
     */
    public function obtenerComprobanteXml(string $claveAcceso): array
    {
        // Detectar ambiente de la clave de acceso (posición 24)
        // 1 = Pruebas, 2 = Producción
        $ambienteDigit = substr($claveAcceso, 23, 1);
        
        if ($ambienteDigit === '1') {
            $url = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
        } else {
            $url = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
        }

        ini_set('default_socket_timeout', '15'); // Evitar que se quede procesando infinitamente

        try {
            if (class_exists('SoapClient')) {
                return $this->requestViaSoap($url, $claveAcceso);
            } elseif (function_exists('curl_init')) {
                return $this->requestViaCurl($url, $claveAcceso);
            } else {
                return $this->requestViaStream($url, $claveAcceso);
            }
        } catch (Exception $e) {
            return [
                'ok' => false,
                'estado' => 'ERROR',
                'mensaje' => 'Error de conexión con el SRI: ' . $e->getMessage()
            ];
        }
    }

    private function requestViaSoap(string $url, string $claveAcceso): array
    {
        $client = new SoapClient($url, [
            'exceptions' => true,
            'connection_timeout' => 10,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ])
        ]);

        $result = $client->autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso]);

        if (isset($result->RespuestaAutorizacionComprobante->autorizaciones->autorizacion)) {
            $aut = $result->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;
            if (is_array($aut)) $aut = $aut[0];
            return $this->procesarAutorizacionObj($aut);
        }

        return [
            'ok' => false,
            'estado' => 'NO AUTORIZADO',
            'mensaje' => 'No se encontró la autorización para la clave de acceso proporcionada.'
        ];
    }

    private function requestViaCurl(string $url, string $claveAcceso): array
    {
        $postUrl = str_replace('?wsdl', '', $url);
        $xmlRequest = $this->buildRawSoapRequest($claveAcceso);
        $ch = curl_init($postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ""'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        return $this->procesarRawSoapResponse($response);
    }

    private function requestViaStream(string $url, string $claveAcceso): array
    {
        $postUrl = str_replace('?wsdl', '', $url);
        $xmlRequest = $this->buildRawSoapRequest($claveAcceso);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: text/xml; charset=utf-8\r\nSOAPAction: \"\"\r\n",
                'content' => $xmlRequest,
                'timeout' => 15,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Error de conexión HTTP.");
        }
        return $this->procesarRawSoapResponse($response);
    }

    private function buildRawSoapRequest(string $claveAcceso): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
   <soapenv:Header/>
   <soapenv:Body>
      <autorizacionComprobante xmlns="http://ec.gob.sri.ws.autorizacion">
         <claveAccesoComprobante xmlns="">' . htmlspecialchars($claveAcceso) . '</claveAccesoComprobante>
      </autorizacionComprobante>
   </soapenv:Body>
</soapenv:Envelope>';
    }

    private function procesarRawSoapResponse(string $responseXml): array
    {
        $responseXml = trim($responseXml);
        $totalLen = strlen($responseXml);
        
        // Asegurarnos de que no esté vacío
        if (empty($responseXml)) {
            throw new Exception("El servidor del SRI devolvió una respuesta vacía.");
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($responseXml)) {
            $errors = libxml_get_errors();
            $errorMsg = "";
            foreach ($errors as $error) {
                $errorMsg .= trim($error->message) . " (Line: {$error->line}, Col: {$error->column}); ";
            }
            libxml_clear_errors();
            
            $preview = htmlspecialchars(substr($responseXml, 0, 500));
            throw new Exception("Error al procesar XML del SRI (Len: $totalLen). Errores: $errorMsg. Inicio del contenido: " . $preview);
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('ns2', 'http://ec.gob.sri.ws.autorizacion');

        // Verificar si es un SOAP Fault
        $faults = $xpath->query('//soap:Fault');
        if ($faults && $faults->length > 0) {
            $faultStr = $xpath->evaluate('string(//faultstring)', $faults->item(0));
            throw new Exception("El servidor del SRI rechazó la solicitud (SOAP Fault): " . htmlspecialchars((string)$faultStr));
        }

        // Buscar el nodo autorizacion
        $nodes = $xpath->query('//autorizacion');
        if ($nodes && $nodes->length > 0) {
            $node = $nodes->item(0);
            
            $estado = $xpath->evaluate('string(estado)', $node);
            $fechaAut = $xpath->evaluate('string(fechaAutorizacion)', $node);
            $numAut = $xpath->evaluate('string(numeroAutorizacion)', $node);
            $comprobante = $xpath->evaluate('string(comprobante)', $node);

            if ($estado === 'AUTORIZADO') {
                return [
                    'ok' => true,
                    'estado' => $estado,
                    'fecha_autorizacion' => $fechaAut,
                    'numero_autorizacion' => $numAut,
                    'xml' => $comprobante
                ];
            }

            return [
                'ok' => false,
                'estado' => $estado,
                'mensaje' => 'El comprobante no está AUTORIZADO. Estado devuelto: ' . $estado
            ];
        }

        return [
            'ok' => false,
            'estado' => 'NO AUTORIZADO',
            'mensaje' => 'No se encontró la información de autorización en la respuesta del SRI.'
        ];
    }

    private function procesarAutorizacionObj(object $aut): array
    {
        $estado = (string) $aut->estado;

        if ($estado === 'AUTORIZADO') {
            return [
                'ok' => true,
                'estado' => $estado,
                'fecha_autorizacion' => isset($aut->fechaAutorizacion) ? (string)$aut->fechaAutorizacion : '',
                'numero_autorizacion' => isset($aut->numeroAutorizacion) ? (string)$aut->numeroAutorizacion : '',
                'xml' => (string) ($aut->comprobante ?? '')
            ];
        }

        return [
            'ok' => false,
            'estado' => $estado,
            'mensaje' => 'El comprobante no está AUTORIZADO. Estado devuelto: ' . $estado
        ];
    }
}
