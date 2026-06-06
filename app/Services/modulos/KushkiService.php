<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\KushkiRepository;
use Exception;

/**
 * Servicio de integración con Kushki para cobros recurrentes.
 *
 * Documentación Kushki: https://docs.kushki.com/ec
 *
 * Las credenciales se cargan desde la tabla kushki_config (por empresa),
 * NO desde config/app.php, para soportar multitenancy.
 *
 * Uso:
 *   $kushki = new KushkiService($idEmpresa);
 *   $token  = $kushki->crearTokenSuscripcion($onetimeToken);
 *   $cobro  = $kushki->cobrar($token, 25.00, 'Plan Pro - Junio 2026');
 */
class KushkiService
{
    private string $privateKey;
    private string $publicKey;
    private string $ambiente;
    private string $moneda;
    private string $baseUrl;
    private int    $idEmpresa;

    /**
     * @param int $idEmpresa Empresa activa en sesión
     * @throws Exception si no hay configuración de Kushki para esa empresa
     */
    public function __construct(int $idEmpresa)
    {
        $this->idEmpresa = $idEmpresa;

        $repo   = new KushkiRepository();
        $config = $repo->getConfig($idEmpresa);

        if (!$config) {
            throw new Exception('Kushki no está configurado para esta empresa. Configure las credenciales en Configuración → Kushki.');
        }
        if (!(bool) $config['activo']) {
            throw new Exception('El servicio Kushki está desactivado para esta empresa.');
        }
        if (empty($config['private_key']) || empty($config['public_key'])) {
            throw new Exception('Las credenciales de Kushki están incompletas. Revise la configuración.');
        }

        $this->privateKey = $config['private_key'];
        $this->publicKey  = $config['public_key'];
        $this->ambiente   = $config['ambiente'] ?? 'uat';
        $this->moneda     = $config['moneda']   ?? 'USD';

        $this->baseUrl = $this->ambiente === 'production'
            ? 'https://api.kushkipagos.com'
            : 'https://api-uat.kushkipagos.com';
    }

    /**
     * Retorna la clave pública para usarla en el frontend (Kushki.js).
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Retorna el ambiente actual ('uat' o 'production').
     */
    public function getAmbiente(): string
    {
        return $this->ambiente;
    }

    /**
     * Verifica la conexión con Kushki usando la private key.
     * Llama a un endpoint de listado de tokens para verificar credenciales.
     * Retorna ['ok' => bool, 'mensaje' => string].
     */
    public function testConexion(): array
    {
        try {
            // Llamada de prueba: token mínimo inválido para verificar autenticación
            // Kushki responde 401 si la clave es inválida, 4xx/5xx de negocio si es válida
            $response = $this->post('/v1/tokens', [
                'totalAmount'  => 0,
                'currency'     => $this->moneda,
                'card'         => ['number' => '4242424242424242', 'expiryMonth' => '12', 'expiryYear' => '28', 'cvv' => '123', 'name' => 'TEST'],
            ]);

            // Si llega aquí sin lanzar excepción, la autenticación es válida
            return ['ok' => true, 'mensaje' => 'Conexión exitosa con Kushki. Credenciales válidas.'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            // Errores de negocio (tarjeta inválida, etc.) confirman que la clave es válida
            if (str_contains($msg, 'K004') || str_contains($msg, 'K002') || str_contains($msg, '422') || str_contains($msg, 'card')) {
                return ['ok' => true, 'mensaje' => 'Conexión exitosa con Kushki. Credenciales válidas.'];
            }
            // Error 401 = clave inválida
            if (str_contains($msg, '401') || str_contains($msg, 'Unauthorized') || str_contains($msg, 'unauthorized')) {
                return ['ok' => false, 'mensaje' => 'Credenciales inválidas. Verifique la clave pública y privada.'];
            }
            return ['ok' => false, 'mensaje' => 'No se pudo conectar con Kushki: ' . $msg];
        }
    }

    /**
     * Convierte un token de un solo uso (obtenido desde Kushki.js en el frontend)
     * en un token de suscripción permanente que puede usarse para cobros recurrentes.
     *
     * @param  string $onetimeToken Token efímero del frontend
     * @return array  ['token' => string, 'last4' => string, 'brand' => string, 'card_holder_name' => string]
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
            'last4'            => $response['lastFourDigits']  ?? '',
            'brand'            => $response['paymentBrand']    ?? '',
            'card_holder_name' => $response['cardHolderName']  ?? '',
        ];
    }

    /**
     * Ejecuta un cobro usando el token permanente de suscripción.
     * No requiere que el cliente esté presente.
     *
     * @param  string $subscriptionToken Token permanente guardado en suscripciones.kushki_token
     * @param  float  $monto             Monto a cobrar en dólares
     * @param  string $descripcion       Descripción del cargo
     * @return array  ['transaction_id' => string, 'estado' => 'exitoso'|'fallido', 'response' => array]
     */
    public function cobrar(string $subscriptionToken, float $monto, string $descripcion): array
    {
        $payload = [
            'amount' => [
                'subtotalIva'  => 0,
                'subtotalIva0' => round($monto, 2),
                'iva'          => 0,
                'ice'          => 0,
                'currency'     => $this->moneda,
            ],
            'subscriptionId' => $subscriptionToken,
            'fullResponse'   => true,
            'metadata'       => ['description' => $descripcion],
        ];

        try {
            $response = $this->post('/v1/subscriptions/charges', $payload);

            $transactionId = $response['ticketNumber'] ?? ($response['transactionId'] ?? '');
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

    // ─── HTTP interno ────────────────────────────────────────────────────────

    private function post(string $endpoint, array $payload): array
    {
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
