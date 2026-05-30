<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\PayphoneRepository;

/**
 * PayphoneService
 *
 * Servicio global para integración con Payphone (Ecuador).
 * Reutilizable desde cualquier módulo del sistema.
 *
 * ─── FLUJO DE PAGO ───────────────────────────────────────────────────────────
 *  1. prepararPago()   → llama a /api/button/Prepare → retorna payWithCard URL
 *  2. El usuario paga en Payphone (redirección o iframe)
 *  3. Payphone redirige a responseUrl con ?clientTransactionId=...&id=...
 *  4. confirmarPago()  → llama a /api/button/Confirm → verifica y guarda resultado
 *
 * ─── MONTOS ──────────────────────────────────────────────────────────────────
 *  Payphone trabaja en CENTAVOS (integer).
 *  Usar dolaresACentavos() para convertir: 12.50 → 1250
 *
 * ─── USO EN MÓDULOS ──────────────────────────────────────────────────────────
 *  $pp = new PayphoneService(new PayphoneRepository());
 *
 *  // Preparar pago
 *  $resultado = $pp->prepararPago($idEmpresa, [
 *      'monto'         => 1250,          // centavos
 *      'descripcion'   => 'Cita #45',
 *      'modulo'        => 'citas',
 *      'id_referencia' => 45,
 *      'url_retorno'   => BASE_URL . '/payphone/retorno',
 *      'url_cancelacion' => BASE_URL . '/payphone/cancelacion',
 *      'id_usuario'    => 0,             // 0 = cliente externo
 *  ]);
 *  // $resultado['ok'] === true  → $resultado['pay_url'] para redirigir al cliente
 *  // $resultado['client_transaction_id'] para identificar la transacción
 *
 *  // Confirmar pago (en el controlador de retorno)
 *  $conf = $pp->confirmarPago($idEmpresa, $clientTransactionId, $paymentId);
 *  // $conf['estado'] === 'aprobado' | 'cancelado' | 'rechazado' | 'error'
 */
class PayphoneService
{
    private const BASE_URL_PROD      = 'https://pay.payphonetodoesposible.com';
    private const BASE_URL_SANDBOX   = 'https://pay.payphonetodoesposible.com'; // Payphone usa el mismo endpoint
    private const BASE_URL_CAJITA    = 'https://paymentbox.payphonetodoesposible.com';
    private const ENDPOINT_PREPARE   = '/api/button/Prepare';
    private const ENDPOINT_CONFIRM   = '/api/button/V2/Confirm';
    private const ENDPOINT_CAJITA_CONFIRM = '/api/confirm';

    /** Mapa de transactionStatus de Payphone a estados internos */
    private const STATUS_MAP = [
        'Approved'  => 'aprobado',
        'Cancelled' => 'cancelado',
        'Reversed'  => 'cancelado',
        'Declined'  => 'rechazado',
        'Error'     => 'error',
        'InProcess' => 'pendiente',
    ];

    public function __construct(
        private PayphoneRepository $repo
    ) {}

    // ─── CONFIGURACIÓN ────────────────────────────────────────────────────────

    /**
     * Obtiene la configuración de Payphone para una empresa.
     * Lanza excepción si no está configurada o no está activa.
     */
    public function getConfig(int $idEmpresa): array
    {
        $cfg = $this->repo->getConfig($idEmpresa);
        if (!$cfg) {
            throw new \RuntimeException('Payphone no está configurado para esta empresa.');
        }
        if (!(bool) $cfg['activo']) {
            throw new \RuntimeException('El servicio de Payphone está desactivado para esta empresa.');
        }
        return $cfg;
    }

    /**
     * Guarda o actualiza las credenciales de Payphone para una empresa.
     */
    public function guardarConfig(array $d): void
    {
        if (empty($d['token'])) {
            throw new \InvalidArgumentException('El token de Payphone es obligatorio.');
        }
        if (empty($d['id_empresa'])) {
            throw new \InvalidArgumentException('Se requiere id_empresa.');
        }
        $this->repo->upsertConfig($d);
    }

    /**
     * Verifica las credenciales llamando a la API.
     * Payphone no tiene un endpoint de "ping" — hacemos un intento
     * de Prepare con datos mínimos y esperamos 200 o un error de negocio (no auth).
     */
    public function testConexion(int $idEmpresa): array
    {
        try {
            $cfg = $this->getConfig($idEmpresa);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        $payload = [
            'amount'               => 100,
            'amountWithTax'        => 0,
            'amountWithoutTax'     => 100,
            'tax'                  => 0,
            'service'              => 0,
            'tip'                  => 0,
            'currency'             => 'USD',
            'clientTransactionId'  => 'test-' . time(),
            'responseUrl'          => 'https://test.local/retorno',
            'cancellationUrl'      => 'https://test.local/cancelacion',
        ];

        $resp = $this->apiPost(self::ENDPOINT_PREPARE, $payload, $cfg['token']);

        // Si llega paymentId o payWithCard, el token es válido
        if (isset($resp['paymentId']) || isset($resp['payWithCard'])) {
            return ['ok' => true, 'mensaje' => 'Conexión exitosa con Payphone.'];
        }

        $msg = $resp['message'] ?? ($resp['error'] ?? 'Error desconocido al conectar con Payphone.');
        return ['ok' => false, 'mensaje' => (string) $msg];
    }

    // ─── PAGO ─────────────────────────────────────────────────────────────────

    /**
     * Prepara una transacción de pago.
     *
     * Parámetros de $params:
     *  - monto           (int)    OBLIGATORIO — en centavos (ej: $10.50 = 1050)
     *  - descripcion     (string) Descripción para el cliente
     *  - modulo          (string) Nombre del módulo ('citas', 'facturas', etc.)
     *  - id_referencia   (int)    ID del registro relacionado
     *  - url_retorno     (string) URL donde Payphone redirige (siempre /payphone/retorno)
     *  - url_cancelacion (string) URL donde Payphone redirige si cancela (/payphone/cancelacion)
     *  - url_exito       (string) URL final a la que redirigir tras confirmar pago exitoso
     *  - id_usuario      (int)    0 si es cliente externo
     *  - impuesto        (int)    Monto de impuesto en centavos (opcional, default 0)
     *  - propina         (int)    Propina en centavos (opcional, default 0)
     *
     * Retorna:
     *  ['ok' => true,  'pay_url' => '...', 'client_transaction_id' => '...', 'payment_id' => 123]
     *  ['ok' => false, 'mensaje' => '...']
     */
    public function prepararPago(int $idEmpresa, array $params): array
    {
        if (empty($params['monto']) || (int) $params['monto'] <= 0) {
            throw new \InvalidArgumentException('El monto debe ser mayor a cero (en centavos).');
        }
        if (empty($params['url_retorno'])) {
            throw new \InvalidArgumentException('Se requiere url_retorno.');
        }
        if (empty($params['url_cancelacion'])) {
            throw new \InvalidArgumentException('Se requiere url_cancelacion.');
        }

        $cfg   = $this->getConfig($idEmpresa);
        $monto = (int) $params['monto'];
        $tax   = (int) ($params['impuesto'] ?? 0);
        $tip   = (int) ($params['propina']  ?? 0);
        $sinIva = $monto - $tax;

        // Generar clientTransactionId único
        $ctid = sprintf(
            'pp-%d-%s-%s-%s',
            $idEmpresa,
            $params['modulo']       ?? 'gen',
            $params['id_referencia'] ?? '0',
            uniqid('', true)
        );

        // Guardar transacción como pendiente ANTES de llamar a la API
        $this->repo->crearTransaccion([
            'id_empresa'           => $idEmpresa,
            'client_transaction_id'=> $ctid,
            'modulo'               => $params['modulo']        ?? 'general',
            'id_referencia'        => $params['id_referencia'] ?? null,
            'descripcion'          => $params['descripcion']   ?? null,
            'monto'                => $monto,
            'moneda'               => 'USD',
            'url_retorno'          => $params['url_retorno'],
            'url_cancelacion'      => $params['url_cancelacion'],
            'url_exito'            => $params['url_exito']     ?? null,
            'id_usuario'           => $params['id_usuario']    ?? null,
        ]);

        $payload = [
            'amount'              => $monto,
            'amountWithTax'       => $tax,
            'amountWithoutTax'    => $sinIva,
            'tax'                 => $tax,
            'service'             => 0,
            'tip'                 => $tip,
            'currency'            => 'USD',
            'clientTransactionId' => $ctid,
            'responseUrl'         => $params['url_retorno'],
            'cancellationUrl'     => $params['url_cancelacion'],
            'reference'           => $params['descripcion'] ?? '',
            'lang'                => 'es',
            'defaultMethod'       => 'card',
        ];

        if (!empty($cfg['store_id'])) {
            $payload['storeId'] = $cfg['store_id'];
        }

        $resp = $this->apiPost(self::ENDPOINT_PREPARE, $payload, $cfg['token']);

        if (empty($resp['paymentId'])) {
            $msg = $resp['message'] ?? ($resp['error'] ?? 'Error al preparar el pago con Payphone.');
            // Marcar como error en DB
            $this->repo->actualizarResultado($ctid, [
                'estado'             => 'error',
                'transaction_status' => 'Error',
                'response_data'      => $resp,
            ]);
            return ['ok' => false, 'mensaje' => (string) $msg];
        }

        // Guardar paymentId
        $this->repo->actualizarPaymentId($ctid, (int) $resp['paymentId']);

        return [
            'ok'                    => true,
            'pay_url'               => $resp['payWithCard']    ?? $resp['payWithPayPhone'] ?? '',
            'pay_url_payphone_app'  => $resp['payWithPayPhone'] ?? '',
            'payment_id'            => (int) $resp['paymentId'],
            'client_transaction_id' => $ctid,
        ];
    }

    /**
     * Confirma el resultado de un pago con Payphone.
     * Llamar desde el controlador de retorno (responseUrl).
     *
     * @param  string   $clientTransactionId  El ctid que viene en el GET/POST de Payphone
     * @param  int      $paymentId            El id que viene en el GET/POST de Payphone
     * @param  int      $idUsuario            0 si es acción automática / cliente externo
     *
     * Retorna:
     *  ['ok' => true,  'estado' => 'aprobado|cancelado|rechazado', 'transaccion' => [...]]
     *  ['ok' => false, 'mensaje' => '...']
     */
    public function confirmarPago(string $clientTransactionId, int $paymentId, int $idUsuario = 0): array
    {
        // Recuperar la transacción guardada
        $trans = $this->repo->getTransaccionByClientId($clientTransactionId);
        if (!$trans) {
            return ['ok' => false, 'mensaje' => 'Transacción no encontrada.'];
        }

        // Re-confirmar si pendiente o si un intento previo falló; estados finales idempotentes
        if (!in_array($trans['estado'], ['pendiente', 'error'], true)) {
            return [
                'ok'         => true,
                'estado'     => $trans['estado'],
                'transaccion'=> $trans,
            ];
        }

        $cfg  = $this->getConfig((int) $trans['id_empresa']);
        $resp = $this->apiPost(self::ENDPOINT_CONFIRM, [
            'id'                  => $paymentId,
            'clientTransactionId' => $clientTransactionId,
        ], $cfg['token']);

        $statusPayphone = $resp['transactionStatus'] ?? 'Error';
        $estadoInterno  = self::STATUS_MAP[$statusPayphone] ?? 'error';

        $this->repo->actualizarPaymentId($clientTransactionId, $paymentId);
        $resp['_debug'] = [
            'via'         => 'confirmarPago',
            'sent_id'     => $paymentId,
            'sent_ctid'   => $clientTransactionId,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'get_params'  => $_GET ?? [],
        ];

        $this->repo->actualizarResultado($clientTransactionId, [
            'transaction_id'     => $resp['transactionId']    ?? null,
            'transaction_status' => $statusPayphone,
            'estado'             => $estadoInterno,
            'authorization_code' => $resp['authorizationCode'] ?? null,
            'response_data'      => $resp,
            'id_usuario'         => $idUsuario,
        ]);

        $trans = $this->repo->getTransaccionByClientId($clientTransactionId);

        return [
            'ok'          => true,
            'estado'      => $estadoInterno,
            'aprobado'    => $estadoInterno === 'aprobado',
            'transaccion' => $trans,
            'response'    => $resp,
        ];
    }

    // ─── CAJITA DE PAGOS ─────────────────────────────────────────────────────

    /**
     * Prepara una transacción para la Cajita de Pagos (widget embebido).
     * No llama a la API de Payphone — simplemente registra la transacción
     * y devuelve los parámetros que el widget JS necesita.
     *
     * Parámetros de $params: igual que prepararPago() excepto url_retorno → url_cajita_retorno.
     *
     * Retorna:
     *  ['ok' => true, 'widget' => [...parámetros para PPaymentButtonBox...], 'client_transaction_id' => '...']
     *  ['ok' => false, 'mensaje' => '...']
     */
    public function prepararCajita(int $idEmpresa, array $params): array
    {
        if (empty($params['monto']) || (int) $params['monto'] <= 0) {
            throw new \InvalidArgumentException('El monto debe ser mayor a cero (en centavos).');
        }
        if (empty($params['url_retorno'])) {
            throw new \InvalidArgumentException('Se requiere url_retorno.');
        }
        if (empty($params['url_cancelacion'])) {
            throw new \InvalidArgumentException('Se requiere url_cancelacion.');
        }

        $cfg    = $this->getConfig($idEmpresa);
        $monto  = (int) $params['monto'];
        $tax    = (int) ($params['impuesto'] ?? 0);
        $tip    = (int) ($params['propina']  ?? 0);
        $sinIva = $monto - $tax;

        $ctid = sprintf(
            'ppb-%d-%s-%s-%s',
            $idEmpresa,
            $params['modulo']        ?? 'gen',
            $params['id_referencia'] ?? '0',
            uniqid('', true)
        );

        $this->repo->crearTransaccion([
            'id_empresa'            => $idEmpresa,
            'client_transaction_id' => $ctid,
            'modulo'                => $params['modulo']        ?? 'general',
            'id_referencia'         => $params['id_referencia'] ?? null,
            'descripcion'           => $params['descripcion']   ?? null,
            'monto'                 => $monto,
            'moneda'                => 'USD',
            'url_retorno'           => $params['url_retorno'],
            'url_cancelacion'       => $params['url_cancelacion'],
            'url_exito'             => $params['url_exito']     ?? null,
            'id_usuario'            => $params['id_usuario']    ?? null,
            'tipo_flujo'            => 'cajita',
        ]);

        $widget = [
            'token'               => $cfg['token'],
            'amount'              => $monto,
            'amountWithTax'       => $tax,
            'amountWithoutTax'    => $sinIva,
            'tax'                 => $tax,
            'service'             => 0,
            'tip'                 => $tip,
            'currency'            => 'USD',
            'clientTransactionId' => $ctid,
            'responseUrl'         => $params['url_retorno'],
            'cancellationUrl'     => $params['url_cancelacion'],
            'reference'           => $params['descripcion'] ?? '',
            'lang'                => 'es',
        ];

        if (!empty($cfg['store_id'])) {
            $widget['storeId'] = $cfg['store_id'];
        }
        if (!empty($params['telefono'])) {
            $widget['phoneNumber'] = $params['telefono'];
        }
        if (!empty($params['email'])) {
            $widget['email'] = $params['email'];
        }
        if (!empty($params['documento'])) {
            $widget['documentId']         = $params['documento'];
            $widget['identificationType'] = (int) ($params['tipo_documento'] ?? 1);
        }

        return [
            'ok'                    => true,
            'widget'                => $widget,
            'client_transaction_id' => $ctid,
        ];
    }

    /**
     * Normaliza la respuesta de un endpoint de confirmación a un transactionStatus.
     * Soporta transactionStatus textual y statusCode numérico (3=Approved, 2=Cancelled).
     */
    private function extraerStatusConfirm(array $resp): string
    {
        if (!empty($resp['transactionStatus'])) {
            return (string) $resp['transactionStatus'];
        }
        if (!empty($resp['status']) && is_string($resp['status'])) {
            return $resp['status'];
        }
        if (isset($resp['statusCode'])) {
            return match ((int) $resp['statusCode']) {
                3 => 'Approved',
                2 => 'Cancelled',
                default => 'Error',
            };
        }
        return 'Error';
    }

    /**
     * Confirma el resultado de un pago realizado con la Cajita de Pagos.
     * El widget box/v2.0 confirma vía /api/button/V2/Confirm; fallback a /api/confirm.
     */
    public function confirmarCajitaPago(string $clientTransactionId, int $paymentId, int $idUsuario = 0): array
    {
        $trans = $this->repo->getTransaccionByClientId($clientTransactionId);
        if (!$trans) {
            return ['ok' => false, 'mensaje' => 'Transacción no encontrada.'];
        }

        // Re-confirmar si está pendiente o si un intento previo falló (error transitorio).
        // Estados finales (aprobado/cancelado/rechazado) se devuelven tal cual (idempotente).
        if (!in_array($trans['estado'], ['pendiente', 'error'], true)) {
            return [
                'ok'          => true,
                'estado'      => $trans['estado'],
                'transaccion' => $trans,
            ];
        }

        $cfg = $this->getConfig((int) $trans['id_empresa']);

        // El widget box/v2.0 (PPaymentButtonBox) registra la transacción en el
        // sistema del Botón de pago, por lo que se confirma en /api/button/V2/Confirm
        // con clientTransactionId. Si por algún motivo no resuelve, se intenta el
        // endpoint exclusivo de la Cajita (/api/confirm con clientTxId).
        $resp = $this->apiPost(self::ENDPOINT_CONFIRM, [
            'id'                  => $paymentId,
            'clientTransactionId' => $clientTransactionId,
        ], $cfg['token']);

        $statusPayphone = $this->extraerStatusConfirm($resp);

        // Fallback al endpoint de cajita si el botón no devolvió un estado válido
        if ($statusPayphone === 'Error' && !empty($resp['errorCode'])) {
            $respCajita = $this->apiPostCajita(self::ENDPOINT_CAJITA_CONFIRM, [
                'id'        => $paymentId,
                'clientTxId'=> $clientTransactionId,
            ], $cfg['token']);
            $statusCajita = $this->extraerStatusConfirm($respCajita);
            if ($statusCajita !== 'Error') {
                $resp           = $respCajita;
                $statusPayphone = $statusCajita;
            }
        }

        $estadoInterno = self::STATUS_MAP[$statusPayphone] ?? 'error';

        // Guardar el id recibido para diagnóstico
        $this->repo->actualizarPaymentId($clientTransactionId, $paymentId);

        // Adjuntar diagnóstico a la respuesta cruda
        $resp['_debug'] = [
            'sent_id'        => $paymentId,
            'sent_ctid'      => $clientTransactionId,
            'request_uri'    => $_SERVER['REQUEST_URI'] ?? '',
            'get_params'     => $_GET ?? [],
        ];

        $this->repo->actualizarResultado($clientTransactionId, [
            'transaction_id'     => $resp['transactionId']    ?? null,
            'transaction_status' => $statusPayphone,
            'estado'             => $estadoInterno,
            'authorization_code' => $resp['authorizationCode'] ?? null,
            'response_data'      => $resp,
            'id_usuario'         => $idUsuario,
        ]);

        $trans = $this->repo->getTransaccionByClientId($clientTransactionId);

        return [
            'ok'          => true,
            'estado'      => $estadoInterno,
            'aprobado'    => $estadoInterno === 'aprobado',
            'transaccion' => $trans,
            'response'    => $resp,
        ];
    }

    /**
     * Reconstruye el array de configuración del widget a partir de una transacción guardada.
     * Usado en la página pública de pago para volver a renderizar la cajita.
     */
    public function getWidgetConfigFromTransaction(array $trans): array
    {
        $cfg    = $this->repo->getConfig((int) $trans['id_empresa']);
        $monto  = (int) $trans['monto'];

        $widget = [
            'token'               => $cfg['token'],
            'amount'              => $monto,
            'amountWithTax'       => 0,
            'amountWithoutTax'    => $monto,
            'tax'                 => 0,
            'service'             => 0,
            'tip'                 => 0,
            'currency'            => $trans['moneda'] ?? 'USD',
            'clientTransactionId' => $trans['client_transaction_id'],
            'responseUrl'         => $trans['url_retorno'],
            'cancellationUrl'     => $trans['url_cancelacion'] ?? $trans['url_retorno'],
            'reference'           => $trans['descripcion'] ?? '',
            'lang'                => 'es',
        ];

        if (!empty($cfg['store_id'])) {
            $widget['storeId'] = $cfg['store_id'];
        }

        return $widget;
    }

    // ─── CONSULTAS ────────────────────────────────────────────────────────────

    /**
     * Obtiene la última transacción de un registro específico.
     */
    public function getTransaccionPorReferencia(int $idEmpresa, string $modulo, int $idReferencia): ?array
    {
        $rows = $this->repo->getTransaccionesPorReferencia($idEmpresa, $modulo, $idReferencia);
        return $rows[0] ?? null;
    }

    /**
     * Obtiene todas las transacciones de un registro.
     */
    public function getTransaccionesPorReferencia(int $idEmpresa, string $modulo, int $idReferencia): array
    {
        return $this->repo->getTransaccionesPorReferencia($idEmpresa, $modulo, $idReferencia);
    }

    public function getTransaccionByClientId(string $clientTransactionId): ?array
    {
        return $this->repo->getTransaccionByClientId($clientTransactionId);
    }

    public function getListado(int $idEmpresa, string $modulo = '', string $estado = '', int $page = 1, int $perPage = 30): array
    {
        return $this->repo->getListado($idEmpresa, $modulo, $estado, $page, $perPage);
    }

    // ─── UTILIDADES ───────────────────────────────────────────────────────────

    /**
     * Convierte dólares con decimales a centavos enteros.
     * Ejemplo: dolaresACentavos(10.50) → 1050
     */
    public static function dolaresACentavos(float $dolares): int
    {
        return (int) round($dolares * 100);
    }

    /**
     * Convierte centavos a dólares con decimales.
     * Ejemplo: centavosADolares(1050) → 10.50
     */
    public static function centavosADolares(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    /**
     * Retorna la etiqueta legible del estado interno.
     */
    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'aprobado'  => 'Aprobado',
            'cancelado' => 'Cancelado',
            'rechazado' => 'Rechazado',
            'pendiente' => 'Pendiente',
            'error'     => 'Error',
            default     => ucfirst($estado),
        };
    }

    /**
     * Retorna la clase Bootstrap del badge según el estado.
     */
    public static function claseBadgeEstado(string $estado): string
    {
        return match ($estado) {
            'aprobado'  => 'success',
            'cancelado' => 'secondary',
            'rechazado' => 'danger',
            'pendiente' => 'warning',
            'error'     => 'danger',
            default     => 'secondary',
        };
    }

    // ─── API INTERNA ──────────────────────────────────────────────────────────

    private function apiPostCajita(string $endpoint, array $payload, string $token): array
    {
        $url = self::BASE_URL_CAJITA . $endpoint;
        $ch  = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
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
            return ['error' => 'Respuesta inválida de Payphone Cajita (HTTP ' . $httpCode . ').'];
        }

        return $data;
    }

    private function apiPost(string $endpoint, array $payload, string $token): array
    {
        $url = self::BASE_URL_PROD . $endpoint;
        $ch  = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
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
            return ['error' => 'Respuesta inválida de Payphone (HTTP ' . $httpCode . ').'];
        }

        return $data;
    }
}
