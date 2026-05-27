<?php
declare(strict_types=1);

namespace App\Services\modulos;

use Exception;

/**
 * Servicio de integración con Kushki para cobros recurrentes.
 *
 * Documentación Kushki: https://docs.kushki.com
 *
 * Requiere en config/app.php:
 *   'kushki_private_key'    => 'xxxxxxxx',
 *   'kushki_public_key'     => 'xxxxxxxx',
 *   'kushki_ambiente'       => 'uat' | 'production',
 *   'kushki_moneda'         => 'USD',
 */
class KushkiService
{
    private string $privateKey;
    private string $ambiente;
    private string $moneda;
    private string $baseUrl;

    public function __construct()
    {
        $config = require MVC_CONFIG . '/app.php';

        $this->privateKey = $config['kushki_private_key'] ?? '';
        $this->ambiente   = $config['kushki_ambiente']    ?? 'uat';
        $this->moneda     = $config['kushki_moneda']      ?? 'USD';

        // URL base de la API de Kushki
        $this->baseUrl = $this->ambiente === 'production'
            ? 'https://api.kushkipagos.com'
            : 'https://api-uat.kushkipagos.com';
    }

    /**
     * Convierte un token de un solo uso (obtenido desde JS Kushki) en una suscripción/token permanente.
     * El token permanente se almacena en suscripciones.kushki_token.
     *
     * @param string $onetimeToken Token de un solo uso enviado desde el frontend
     * @return array ['token' => string, 'last4' => string, 'brand' => string, 'card_holder_name' => string]
     */
    public function crearTokenSuscripcion(string $onetimeToken): array
    {
        $payload = [
            'token'    => $onetimeToken,
            'currency' => $this->moneda,
        ];

        $response = $this->post('/v1/tokens/subscription', $payload);

        if (empty($response['subscriptionId'])) {
            throw new Exception('Kushki no devolvió un subscriptionId válido. Respuesta: ' . json_encode($response));
        }

        return [
            'token'            => $response['subscriptionId'],
            'last4'            => $response['lastFourDigits']    ?? '',
            'brand'            => $response['paymentBrand']      ?? '',
            'card_holder_name' => $response['cardHolderName']    ?? '',
        ];
    }

    /**
     * Ejecuta un cobro usando el token permanente de suscripción.
     *
     * @param string $subscriptionToken Token permanente de Kushki
     * @param float  $monto             Monto a cobrar
     * @param string $descripcion       Descripción del cargo (ej: "Suscripción Plan Pro - Mayo 2026")
     * @return array ['transaction_id' => string, 'estado' => 'exitoso'|'fallido', 'response' => array]
     */
    public function cobrar(string $subscriptionToken, float $monto, string $descripcion): array
    {
        $payload = [
            'amount' => [
                'subtotalIva'     => 0,
                'subtotalIva0'    => round($monto, 2),
                'iva'             => 0,
                'ice'             => 0,
                'currency'        => $this->moneda,
            ],
            'subscriptionId'  => $subscriptionToken,
            'fullResponse'    => true,
            'metadata'        => ['description' => $descripcion],
        ];

        try {
            $response = $this->post('/v1/subscriptions/charges', $payload);

            $transactionId = $response['ticketNumber']     ?? ($response['transactionId'] ?? '');
            $aprobado      = isset($response['approvalCode']) || ($response['responseCode'] ?? '') === '000';

            return [
                'transaction_id' => $transactionId,
                'estado'         => $aprobado ? 'exitoso' : 'fallido',
                'response'       => $response,
            ];
        } catch (Exception $e) {
            return [
                'transaction_id' => '',
                'estado'         => 'fallido',
                'response'       => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Realiza un POST a la API de Kushki.
     */
    private function post(string $endpoint, array $payload): array
    {
        if (empty($this->privateKey)) {
            throw new Exception('Kushki: private_key no configurada en config/app.php.');
        }

        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Private-Merchant-Id: ' . $this->privateKey,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => $this->ambiente === 'production',
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Kushki cURL error: $error");
        }

        $data = json_decode($raw ?: '{}', true);

        if ($status >= 400) {
            $msg = $data['message'] ?? $data['error'] ?? "HTTP $status";
            throw new Exception("Kushki API error ($status): $msg");
        }

        return $data ?? [];
    }
}
