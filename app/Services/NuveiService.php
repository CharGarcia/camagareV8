<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\NuveiAuthHelper;
use App\repositories\NuveiRepository;

/**
 * NuveiService
 *
 * Servicio global para integración con Nuvei (Paymentez, Ecuador).
 * Reutilizable desde cualquier módulo del sistema — mismo espíritu que PayphoneService.
 *
 * Fase 1 (infraestructura base): configuración por empresa, prueba de conexión
 * y recepción/registro crudo del webhook. Los flujos de Checkout, Add Card,
 * recurrencia, Refund y Link to Pay se agregan en fases posteriores.
 *
 * ─── CREDENCIALES ────────────────────────────────────────────────────────────
 *  Nuvei separa dos "aplicaciones" (productos) con credenciales propias:
 *   - "server": usada por Checkout + Add Card/recurrencia (host ccapi).
 *   - "linktopay": usada exclusivamente por Link to Pay (host noccapi).
 *
 * ─── AUTENTICACIÓN ───────────────────────────────────────────────────────────
 *  Cada llamada exige un header Auth-Token generado con NuveiAuthHelper::token(),
 *  válido ~15 segundos — se genera de nuevo en cada request, nunca se cachea.
 */
class NuveiService
{
    private const CCAPI_PROD    = 'https://ccapi.paymentez.com';
    private const CCAPI_SANDBOX = 'https://ccapi-stg.paymentez.com';
    private const NOCCAPI_PROD    = 'https://noccapi.paymentez.com';
    private const NOCCAPI_SANDBOX = 'https://noccapi-stg.paymentez.com';

    private const ENDPOINT_INIT_REFERENCE = '/v2/transaction/init_reference/';
    private const ENDPOINT_LINKTOPAY_INIT = '/linktopay/init_order/';

    public function __construct(
        private NuveiRepository $repo
    ) {}

    // ─── CONFIGURACIÓN ────────────────────────────────────────────────────────

    /**
     * Obtiene la configuración de Nuvei para una empresa.
     * Lanza excepción si no está configurada.
     */
    public function getConfig(int $idEmpresa): array
    {
        $cfg = $this->repo->getConfig($idEmpresa);
        if (!$cfg) {
            throw new \RuntimeException('Nuvei no está configurado para esta empresa.');
        }
        return $cfg;
    }

    /**
     * Guarda o actualiza las credenciales de Nuvei para una empresa.
     */
    public function guardarConfig(array $d): void
    {
        if (empty($d['id_empresa'])) {
            throw new \InvalidArgumentException('Se requiere id_empresa.');
        }
        $this->repo->upsertConfig($d);
    }

    /**
     * Verifica las credenciales "server" (Checkout + Add Card) llamando a
     * init_reference con datos mínimos. Si Nuvei responde con checkout_url/reference,
     * el Auth-Token fue aceptado.
     */
    public function testConexion(int $idEmpresa): array
    {
        try {
            $cfg = $this->getConfig($idEmpresa);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        if (empty($cfg['app_code_server']) || empty($cfg['app_key_server'])) {
            return ['ok' => false, 'mensaje' => 'Faltan las credenciales de Checkout/Add Card (app_code/app_key).'];
        }

        $payload = [
            'locale'     => 'es',
            'session_id' => '',
            'order'      => [
                'amount'            => 1.00,
                'description'       => 'Prueba de conexión',
                'vat'               => 0,
                'dev_reference'     => 'test-' . time(),
                'installments_type' => 0,
            ],
            'user' => [
                'id'    => 'test',
                'email' => 'test@test.com',
            ],
        ];

        $resp = $this->apiPost(
            $this->ccapiBaseUrl($cfg['ambiente']) . self::ENDPOINT_INIT_REFERENCE,
            $payload,
            NuveiAuthHelper::token($cfg['app_code_server'], $cfg['app_key_server'])
        );

        if (isset($resp['checkout_url']) || isset($resp['reference'])) {
            return ['ok' => true, 'mensaje' => 'Conexión exitosa con Nuvei (Checkout/Add Card).'];
        }

        $msg = $resp['error']['description'] ?? ($resp['message'] ?? ($resp['error'] ?? 'Error desconocido al conectar con Nuvei.'));
        return ['ok' => false, 'mensaje' => (string) $msg];
    }

    /**
     * Verifica las credenciales de Link to Pay llamando a init_order con datos mínimos.
     */
    public function testConexionLinkToPay(int $idEmpresa): array
    {
        try {
            $cfg = $this->getConfig($idEmpresa);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        if (empty($cfg['app_code_linktopay']) || empty($cfg['app_key_linktopay'])) {
            return ['ok' => false, 'mensaje' => 'Faltan las credenciales de Link to Pay (app_code/app_key).'];
        }

        $payload = [
            'user'  => ['id' => 'test', 'email' => 'test@test.com', 'name' => 'Test', 'last_name' => 'Test'],
            'order' => [
                'dev_reference' => 'test-' . time(),
                'description'   => 'Prueba de conexión',
                'amount'        => 1.00,
                'currency'      => 'USD',
            ],
            'configuration' => [
                'partial_payment'         => false,
                'expiration_time'         => 3600,
                'allowed_payment_methods' => ['All'],
                'success_url'            => rtrim(BASE_URL, '/') . '/nuvei/linktopay-retorno',
                'failure_url'            => rtrim(BASE_URL, '/') . '/nuvei/linktopay-retorno',
                'pending_url'            => rtrim(BASE_URL, '/') . '/nuvei/linktopay-retorno',
                'review_url'             => rtrim(BASE_URL, '/') . '/nuvei/linktopay-retorno',
            ],
        ];

        $resp = $this->apiPost(
            $this->noccapiBaseUrl($cfg['ambiente']) . self::ENDPOINT_LINKTOPAY_INIT,
            $payload,
            NuveiAuthHelper::token($cfg['app_code_linktopay'], $cfg['app_key_linktopay'])
        );

        if (!empty($resp['success']) && !empty($resp['data']['payment']['payment_url'])) {
            return ['ok' => true, 'mensaje' => 'Conexión exitosa con Nuvei (Link to Pay).'];
        }

        $msg = $resp['detail'] ?? ($resp['message'] ?? ($resp['error'] ?? 'Error desconocido al conectar con Nuvei Link to Pay.'));
        return ['ok' => false, 'mensaje' => (string) $msg];
    }

    // ─── WEBHOOK ──────────────────────────────────────────────────────────────

    /**
     * Registra el payload crudo del webhook para auditoría/depuración.
     * NO se interpreta todavía el resultado (aprobado/rechazado) porque el
     * formato exacto y la firma de autenticidad del webhook de Nuvei aún no
     * están confirmados con el equipo de integraciones — ver plan de la Fase 1.
     */
    public function procesarWebhook(array $payload, array $headers = []): void
    {
        $devReference = $this->extraerDevReference($payload);
        $this->repo->logWebhook($payload, $headers, $devReference);
    }

    private function extraerDevReference(array $payload): ?string
    {
        foreach (['dev_reference', 'reference'] as $campo) {
            if (!empty($payload[$campo]) && is_string($payload[$campo])) {
                return $payload[$campo];
            }
        }
        if (!empty($payload['order']['dev_reference'])) {
            return (string) $payload['order']['dev_reference'];
        }
        if (!empty($payload['transaction']['dev_reference'])) {
            return (string) $payload['transaction']['dev_reference'];
        }
        return null;
    }

    // ─── UTILIDADES ───────────────────────────────────────────────────────────

    private function ccapiBaseUrl(string $ambiente): string
    {
        return $ambiente === 'production' ? self::CCAPI_PROD : self::CCAPI_SANDBOX;
    }

    private function noccapiBaseUrl(string $ambiente): string
    {
        return $ambiente === 'production' ? self::NOCCAPI_PROD : self::NOCCAPI_SANDBOX;
    }

    // ─── API INTERNA ──────────────────────────────────────────────────────────

    private function apiPost(string $url, array $payload, string $authToken): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Auth-Token: ' . $authToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['error' => 'Error de conexión: ' . $curlErr];
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return ['error' => 'Respuesta inválida de Nuvei (HTTP ' . $httpCode . ').'];
        }

        return $data;
    }
}
