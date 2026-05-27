<?php
declare(strict_types=1);

namespace App\Services;

class GeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT    = 'SistemaERP/1.0 (admin@sistema.local)';
    private const TIMEOUT       = 12;

    /**
     * Geocodifica una dirección usando Nominatim (OpenStreetMap).
     * Intenta primero con la dirección completa; si no hay resultado,
     * reintenta solo con ciudad + provincia para dar una ubicación aproximada.
     *
     * Retorna array con latitud, longitud, display_name y query_usada,
     * o lanza RuntimeException con detalle del error.
     */
    public function geocodificar(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        // Intento 1: dirección completa
        $resultado = $this->llamarNominatim($query);
        if ($resultado !== null) {
            $resultado['query_usada'] = $query;
            return $resultado;
        }

        // Intento 2: solo las dos últimas partes (ciudad + provincia),
        // descartando la calle específica para ampliar la búsqueda
        $partes = array_map('trim', explode(',', $query));
        if (count($partes) >= 2) {
            $querySimple = implode(', ', array_slice($partes, -2));
            if ($querySimple !== $query) {
                $resultado = $this->llamarNominatim($querySimple);
                if ($resultado !== null) {
                    $resultado['query_usada']    = $querySimple;
                    $resultado['aproximada']     = true;
                    return $resultado;
                }
            }
        }

        // Sin resultados en ambos intentos
        return null;
    }

    /**
     * Realiza una llamada HTTP a Nominatim y devuelve el primer resultado, o null.
     * Lanza RuntimeException si hay error de red o respuesta inesperada.
     */
    private function llamarNominatim(string $query): ?array
    {
        $url = self::NOMINATIM_URL . '?' . http_build_query([
            'q'              => $query,
            'format'         => 'json',
            'limit'          => 1,
            'addressdetails' => 0,
            'countrycodes'   => 'ec',   // Restringir resultados a Ecuador
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            // En entornos locales (XAMPP/WAMP) los certificados SSL suelen
            // no estar configurados; desactivamos la verificación en desarrollo.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        curl_close($ch);

        if ($response === false || $curlErrNo !== 0) {
            throw new \RuntimeException(
                'No se pudo conectar con el servicio de geocodificación. ' .
                'Verifique la conexión a internet del servidor. Error: ' . $curlError
            );
        }

        if ($httpCode === 429) {
            throw new \RuntimeException(
                'El servicio de geocodificación está saturado (límite de peticiones). Intente en unos segundos.'
            );
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException(
                "El servicio de geocodificación respondió con error HTTP {$httpCode}."
            );
        }

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
            return null; // Sin resultados para este query
        }

        return [
            'latitud'      => (float) $data[0]['lat'],
            'longitud'     => (float) $data[0]['lon'],
            'display_name' => (string) ($data[0]['display_name'] ?? ''),
        ];
    }
}
