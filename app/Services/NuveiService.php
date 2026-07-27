<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\NuveiAuthHelper;
use App\repositories\NuveiRepository;
use App\repositories\NuveiTarjetaRepository;

/**
 * NuveiService
 *
 * Servicio global para integración con Nuvei (Paymentez, Ecuador).
 * Reutilizable desde cualquier módulo del sistema — mismo espíritu que PayphoneService.
 *
 * Cubre Checkout (pago único), Refund, Link to Pay y Add Card + débito con
 * token (recurrencia, usado por el módulo de Suscripciones).
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
    private const ENDPOINT_REFUND         = '/order/refund/';
    private const ENDPOINT_DEBIT          = '/v2/transaction/debit/';

    private NuveiTarjetaRepository $tarjetaRepo;

    public function __construct(
        private NuveiRepository $repo,
        ?NuveiTarjetaRepository $tarjetaRepo = null
    ) {
        $this->tarjetaRepo = $tarjetaRepo ?? new NuveiTarjetaRepository();
    }

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
            'session_id' => bin2hex(random_bytes(16)),
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

        return ['ok' => false, 'mensaje' => $this->extraerMensajeError($resp, 'Error desconocido al conectar con Nuvei.')];
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

        $urlRetorno = function_exists('url_absoluta') ? url_absoluta('/nuvei/linktopay-retorno') : (rtrim(BASE_URL, '/') . '/nuvei/linktopay-retorno');

        $payload = [
            'user'  => ['id' => 'test', 'email' => 'test@test.com', 'name' => 'Test', 'last_name' => 'Test'],
            'order' => [
                'dev_reference'     => 'test-' . time(),
                'description'       => 'Prueba de conexión',
                'amount'            => 1.00,
                'currency'          => 'USD',
                'installments_type' => 0,
            ],
            'configuration' => [
                'partial_payment'         => false,
                'expiration_time'         => 3600,
                'allowed_payment_methods' => ['All'],
                'success_url' => $urlRetorno,
                'failure_url' => $urlRetorno,
                'pending_url' => $urlRetorno,
                'review_url'  => $urlRetorno,
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

        return ['ok' => false, 'mensaje' => $this->extraerMensajeError($resp, 'Error desconocido al conectar con Nuvei Link to Pay.')];
    }

    // ─── CHECKOUT (pago único) ────────────────────────────────────────────────

    /**
     * Prepara una transacción de Checkout: registra el intento como 'pendiente'
     * y llama a init_reference. La 'reference' devuelta es la que necesita el
     * modal JS (PaymentCheckout) para abrirse en el navegador del cliente.
     *
     * Parámetros de $params:
     *  - monto           (float)  OBLIGATORIO — en dólares (ej: 10.50)
     *  - descripcion     (string) Descripción para el cliente
     *  - modulo          (string) Nombre del módulo ('factura_venta', etc.)
     *  - id_referencia   (int)    ID del registro relacionado
     *  - id_forma_cobro  (int)    Forma de cobro tipo NUVEI usada
     *  - id_cliente      (int)    Cliente del sistema (para el user.id de Nuvei)
     *  - email           (string) Correo del cliente
     *  - url_exito       (string) URL final a la que redirigir tras el pago aprobado
     *  - id_usuario      (int)    0 si es cliente externo
     *
     * Retorna:
     *  ['ok' => true,  'dev_reference' => '...', 'reference' => '...', 'checkout_url' => '...']
     *  ['ok' => false, 'mensaje' => '...']
     */
    public function prepararCheckout(int $idEmpresa, array $params): array
    {
        if (empty($params['monto']) || (float) $params['monto'] <= 0) {
            throw new \InvalidArgumentException('El monto debe ser mayor a cero.');
        }

        $cfg = $this->getConfig($idEmpresa);
        if (empty($cfg['activo_checkout'])) {
            throw new \RuntimeException('El checkout de Nuvei está desactivado para esta empresa.');
        }
        if (empty($cfg['app_code_server']) || empty($cfg['app_key_server'])) {
            throw new \RuntimeException('Faltan las credenciales de Checkout/Add Card de Nuvei.');
        }

        $monto = round((float) $params['monto'], 2);

        $devReference = sprintf(
            'nv-%d-%s-%s-%s',
            $idEmpresa,
            $params['modulo']        ?? 'gen',
            $params['id_referencia'] ?? '0',
            uniqid('', true)
        );

        // Guardar transacción como pendiente ANTES de llamar a la API
        $this->repo->crearTransaccion([
            'id_empresa'      => $idEmpresa,
            'tipo'            => 'checkout',
            'dev_reference'   => $devReference,
            'modulo'          => $params['modulo']        ?? 'general',
            'id_referencia'   => $params['id_referencia'] ?? null,
            'id_forma_cobro'  => $params['id_forma_cobro'] ?? null,
            'id_cliente'      => $params['id_cliente']    ?? null,
            'email'           => $params['email']         ?? null,
            'descripcion'     => $params['descripcion']   ?? null,
            'monto'           => $monto,
            'moneda'          => 'USD',
            'url_exito'       => $params['url_exito']     ?? null,
            'id_usuario'      => $params['id_usuario']    ?? null,
        ]);

        $resp = $this->llamarInitReference($cfg, $monto, (string) ($params['descripcion'] ?? ''), $devReference, $idEmpresa, $params['id_cliente'] ?? null, $params['email'] ?? '');

        if (empty($resp['reference'])) {
            $msg = $this->extraerMensajeError($resp, 'Error al preparar el pago con Nuvei.');
            $this->repo->actualizarResultado($devReference, [
                'estado'        => 'error',
                'response_data' => $resp,
            ]);
            return ['ok' => false, 'mensaje' => $msg];
        }

        $this->repo->actualizarReference($devReference, (string) $resp['reference']);

        return [
            'ok'            => true,
            'dev_reference' => $devReference,
            'reference'     => (string) $resp['reference'],
            'checkout_url'  => $resp['checkout_url'] ?? null,
        ];
    }

    /**
     * Regenera la 'reference' de Checkout de una transacción que sigue 'pendiente'
     * en nuestra base, usando los mismos datos (monto/descripción/dev_reference).
     *
     * Motivo: la 'reference' que devuelve init_reference caduca del lado de Nuvei
     * en una ventana corta (no documentada exactamente, pero se observó que un
     * enlace de correo abierto tiempo después ya no permite completar el pago
     * dentro del modal, sin que llegue ningún onResponse a nuestro servidor —
     * es Nuvei rechazando la reference vieja en su propio widget). Por eso la
     * página pública /nuvei/pago/{token} llama a este método en cada carga en
     * vez de reutilizar la 'reference' guardada: así el enlace enviado por
     * correo sigue funcionando sin importar cuánto tiempo haya pasado desde
     * que se envió, sin necesidad de generar ni enviar un enlace nuevo.
     *
     * Idempotente en el sentido de que reutiliza el mismo dev_reference (mismo
     * registro local); solo cambia la 'reference' del lado de Nuvei.
     */
    public function refrescarReferenciaCheckout(array $trans): array
    {
        if (($trans['estado'] ?? '') !== 'pendiente') {
            return ['ok' => false, 'mensaje' => 'La transacción ya no está pendiente.'];
        }

        $idEmpresa = (int) $trans['id_empresa'];
        $cfg       = $this->getConfig($idEmpresa);

        $resp = $this->llamarInitReference(
            $cfg,
            (float) $trans['monto'],
            (string) ($trans['descripcion'] ?? ''),
            (string) $trans['dev_reference'],
            $idEmpresa,
            $trans['id_cliente'] ?? null,
            (string) ($trans['email'] ?? '')
        );

        if (empty($resp['reference'])) {
            return ['ok' => false, 'mensaje' => $this->extraerMensajeError($resp, 'No se pudo renovar el enlace de pago con Nuvei.')];
        }

        $this->repo->actualizarReference((string) $trans['dev_reference'], (string) $resp['reference']);

        return ['ok' => true, 'reference' => (string) $resp['reference']];
    }

    /**
     * Llamada compartida a init_reference (usada por prepararCheckout() al
     * generar el enlace la primera vez, y por refrescarReferenciaCheckout()
     * cada vez que el cliente vuelve a abrir el enlace).
     */
    private function llamarInitReference(array $cfg, float $monto, string $descripcion, string $devReference, int $idEmpresa, $idCliente, string $email): array
    {
        $userId = sprintf('emp%d-cli%s', $idEmpresa, (string) ($idCliente ?? '0'));

        $payload = [
            'locale'     => 'es',
            'session_id' => bin2hex(random_bytes(16)),
            'order'      => [
                'amount'            => $monto,
                'description'       => $descripcion,
                'vat'               => 0,
                'dev_reference'     => $devReference,
                'installments_type' => 0,
            ],
            'user' => [
                'id'    => $userId,
                'email' => $email,
            ],
        ];

        return $this->apiPost(
            $this->ccapiBaseUrl($cfg['ambiente']) . self::ENDPOINT_INIT_REFERENCE,
            $payload,
            NuveiAuthHelper::token($cfg['app_code_server'], $cfg['app_key_server'])
        );
    }

    /**
     * Confirma el resultado de un Checkout a partir del objeto que entrega
     * el callback onResponse del modal JS en el navegador del cliente:
     *   { transaction: { status: 'success'|'failure', id: 'CB-81011', status_detail: 3 } }
     * La aprobación se valida con la regla que exige Nuvei: status==='success'
     * Y status_detail===3. Idempotente: un estado final no se reprocesa.
     *
     * Nota: mientras no se confirme el schema/firma del webhook (ver Fase 1),
     * este es el único canal de confirmación disponible — el mismo mecanismo
     * que la propia documentación de Nuvei describe como estándar para Checkout.
     */
    public function confirmarCheckout(string $devReference, array $transactionData, int $idUsuario = 0): array
    {
        $trans = $this->repo->getTransaccionByDevReference($devReference);
        if (!$trans) {
            return ['ok' => false, 'mensaje' => 'Transacción no encontrada.'];
        }

        if (!in_array($trans['estado'], ['pendiente', 'error'], true)) {
            return ['ok' => true, 'estado' => $trans['estado'], 'transaccion' => $trans];
        }

        if (!empty($transactionData['error'])) {
            $this->repo->actualizarResultado($devReference, [
                'estado'        => 'error',
                'response_data' => $transactionData,
                'id_usuario'    => $idUsuario,
            ]);
            return [
                'ok'          => true,
                'estado'      => 'error',
                'transaccion' => $this->repo->getTransaccionByDevReference($devReference),
            ];
        }

        $status       = (string) ($transactionData['status'] ?? '');
        $statusDetail = (int) ($transactionData['status_detail'] ?? 0);
        $aprobado     = ($status === 'success' && $statusDetail === 3);

        $this->repo->actualizarResultado($devReference, [
            'nuvei_transaction_id' => $transactionData['id'] ?? null,
            'status_detail'        => $statusDetail,
            'estado'               => $aprobado ? 'aprobado' : 'rechazado',
            'authorization_code'   => $transactionData['authorization_code'] ?? null,
            'response_data'        => $transactionData,
            'id_usuario'           => $idUsuario,
        ]);

        $trans = $this->repo->getTransaccionByDevReference($devReference);

        return [
            'ok'          => true,
            'estado'      => $aprobado ? 'aprobado' : 'rechazado',
            'aprobado'    => $aprobado,
            'transaccion' => $trans,
        ];
    }

    // ─── REEMBOLSO ────────────────────────────────────────────────────────────

    /**
     * Solicita a Nuvei el reembolso (total o parcial) de una transacción aprobada.
     * Endpoint: POST /order/refund/ en el host noccapi.
     * Obligatorio por requisito de los bancos (ver nota de Nuvei en la integración).
     */
    public function refund(string $devReference, ?float $montoParcial = null, int $idUsuario = 0): array
    {
        $trans = $this->repo->getTransaccionByDevReference($devReference);
        if (!$trans) {
            return ['ok' => false, 'mensaje' => 'Transacción no encontrada.'];
        }
        if ($trans['estado'] === 'reverso') {
            return ['ok' => true, 'estado' => 'reverso', 'transaccion' => $trans];
        }
        if ($trans['estado'] !== 'aprobado') {
            return ['ok' => false, 'mensaje' => 'Solo se puede reembolsar una transacción aprobada.'];
        }
        if (empty($trans['nuvei_transaction_id'])) {
            return ['ok' => false, 'mensaje' => 'No se encontró el identificador de la transacción en Nuvei.'];
        }

        $cfg = $this->getConfig((int) $trans['id_empresa']);
        if (empty($cfg['app_code_server']) || empty($cfg['app_key_server'])) {
            return ['ok' => false, 'mensaje' => 'Faltan las credenciales de Nuvei para procesar el reembolso.'];
        }

        $payload = ['transaction' => ['id' => $trans['nuvei_transaction_id']]];
        if ($montoParcial !== null && $montoParcial > 0 && $montoParcial < (float) $trans['monto']) {
            $payload['order'] = ['amount' => round($montoParcial, 2)];
        }

        $resp = $this->apiPost(
            $this->noccapiBaseUrl($cfg['ambiente']) . self::ENDPOINT_REFUND,
            $payload,
            NuveiAuthHelper::token($cfg['app_code_server'], $cfg['app_key_server'])
        );

        $exito = ($resp['status'] ?? '') === 'success';
        if (!$exito) {
            return [
                'ok'       => false,
                'mensaje'  => $this->extraerMensajeError($resp, 'Nuvei rechazó el reembolso.'),
                'response' => $resp,
            ];
        }

        $this->repo->actualizarResultado($devReference, [
            'estado'        => 'reverso',
            'response_data' => ['origen' => 'reembolso', 'respuesta' => $resp],
            'id_usuario'    => $idUsuario,
        ]);

        $trans = $this->repo->getTransaccionByDevReference($devReference);

        return ['ok' => true, 'estado' => 'reverso', 'transaccion' => $trans, 'response' => $resp];
    }

    // ─── ADD CARD (tokenización + débito recurrente) ─────────────────────────

    /**
     * Genera una solicitud de registro de tarjeta: un enlace público
     * (/nuvei/tarjeta/{token}) que hospeda el widget de tokenización de Nuvei.
     * No llama a la API de Nuvei (la tokenización ocurre en el navegador del
     * cliente) — solo registra la solicitud como 'pendiente'.
     *
     * Parámetros de $params: id_cliente (obligatorio), modulo, id_referencia,
     * email, id_usuario.
     */
    public function prepararSolicitudTarjeta(int $idEmpresa, array $params): array
    {
        if (empty($params['id_cliente'])) {
            throw new \InvalidArgumentException('Se requiere id_cliente.');
        }

        $cfg = $this->getConfig($idEmpresa);
        if (empty($cfg['activo_addcard'])) {
            throw new \RuntimeException('Add Card de Nuvei está desactivado para esta empresa.');
        }
        if (empty($cfg['app_code_server']) || empty($cfg['app_key_server'])) {
            throw new \RuntimeException('Faltan las credenciales de Checkout/Add Card de Nuvei.');
        }

        $token = sprintf(
            'nvt-%d-%s-%s-%s',
            $idEmpresa,
            $params['modulo']        ?? 'gen',
            $params['id_referencia'] ?? '0',
            uniqid('', true)
        );

        $this->tarjetaRepo->crearSolicitud([
            'id_empresa'    => $idEmpresa,
            'id_cliente'    => (int) $params['id_cliente'],
            'modulo'        => $params['modulo']        ?? 'general',
            'id_referencia' => $params['id_referencia'] ?? null,
            'token'         => $token,
            'email'         => $params['email']         ?? null,
            'id_usuario'    => $params['id_usuario']    ?? null,
        ]);

        return ['ok' => true, 'token' => $token];
    }

    public function getSolicitudTarjeta(string $token): ?array
    {
        return $this->tarjetaRepo->getSolicitudPorToken($token);
    }

    /**
     * Persiste la tarjeta tokenizada por el SDK Add Card en el navegador del
     * cliente (payment_sdk_stable.min.js → pg_sdk.tokenize()) y marca la
     * solicitud como completada. Idempotente: si la solicitud ya no está
     * 'pendiente', no crea una tarjeta duplicada.
     *
     * $cardData es el objeto 'card' que devuelve tokenize(): token, bin,
     * number (últimos 4), type (marca), expiry_month/year, status, holder_name.
     */
    public function guardarTarjetaDesdeSolicitud(string $token, array $cardData, int $idUsuario = 0): array
    {
        $sol = $this->tarjetaRepo->getSolicitudPorToken($token);
        if (!$sol) {
            return ['ok' => false, 'mensaje' => 'Solicitud no encontrada.'];
        }
        if ($sol['estado'] !== 'pendiente') {
            return ['ok' => true, 'ya_procesada' => true, 'solicitud' => $sol];
        }
        if (empty($cardData['token'])) {
            return ['ok' => false, 'mensaje' => 'No se recibió el token de la tarjeta.'];
        }

        $idEmpresa   = (int) $sol['id_empresa'];
        $idCliente   = (int) $sol['id_cliente'];
        $nuveiUserId = sprintf('emp%d-cli%d', $idEmpresa, $idCliente);

        $idTarjeta = $this->tarjetaRepo->crearTarjeta([
            'id_empresa'   => $idEmpresa,
            'id_cliente'   => $idCliente,
            'nuvei_user_id'=> $nuveiUserId,
            'token'        => (string) $cardData['token'],
            'bin'          => $cardData['bin']          ?? null,
            'ultimos4'     => $cardData['number']       ?? null,
            'marca'        => $cardData['type']         ?? null,
            'holder_name'  => $cardData['holder_name']  ?? null,
            'expiry_month' => $cardData['expiry_month'] ?? null,
            'expiry_year'  => $cardData['expiry_year']  ?? null,
            'status'       => $cardData['status']       ?? 'valid',
            'id_usuario'   => $idUsuario,
        ]);

        $this->tarjetaRepo->marcarSolicitudCompletada($token, $idTarjeta);

        return ['ok' => true, 'id_nuvei_tarjeta' => $idTarjeta, 'solicitud' => $sol];
    }

    public function getTarjetasCliente(int $idEmpresa, int $idCliente): array
    {
        return $this->tarjetaRepo->getTarjetasCliente($idEmpresa, $idCliente);
    }

    public function eliminarTarjeta(int $idEmpresa, int $idNuveiTarjeta, int $idUsuario): array
    {
        $tarjeta = $this->tarjetaRepo->getTarjetaPorId($idNuveiTarjeta);
        if (!$tarjeta || (int) $tarjeta['id_empresa'] !== $idEmpresa) {
            return ['ok' => false, 'mensaje' => 'Tarjeta no encontrada.'];
        }

        $cfg = $this->getConfig($idEmpresa);
        if (!empty($cfg['app_code_server']) && !empty($cfg['app_key_server'])) {
            // Best-effort: si Nuvei rechaza el delete (ya no existe, etc.) igual
            // se elimina localmente para que deje de ofrecerse como opción.
            $this->apiPost(
                $this->ccapiBaseUrl($cfg['ambiente']) . '/v2/card/delete/',
                ['card' => ['token' => $tarjeta['token']], 'user' => ['id' => $tarjeta['nuvei_user_id']]],
                NuveiAuthHelper::token($cfg['app_code_server'], $cfg['app_key_server'])
            );
        }

        $this->tarjetaRepo->eliminarTarjeta($idNuveiTarjeta, $idUsuario);
        return ['ok' => true];
    }

    /**
     * Débito con token guardado — modo RECURRENCIA (sin 3DS, según la propia
     * tabla de compatibilidad de Nuvei: la recurrencia no usa autenticación 3DS).
     * Usado por Suscripciones para el cobro automático periódico.
     */
    public function debitarConToken(int $idEmpresa, int $idNuveiTarjeta, float $monto, string $descripcion, string $modulo, ?int $idReferencia = null, int $idUsuario = 0): array
    {
        $cfg = $this->getConfig($idEmpresa);
        if (empty($cfg['activo_addcard'])) {
            return ['ok' => false, 'mensaje' => 'Add Card/recurrencia de Nuvei está desactivado para esta empresa.'];
        }
        if (empty($cfg['app_code_server']) || empty($cfg['app_key_server'])) {
            return ['ok' => false, 'mensaje' => 'Faltan las credenciales de Nuvei.'];
        }

        $tarjeta = $this->tarjetaRepo->getTarjetaPorId($idNuveiTarjeta);
        if (!$tarjeta || (int) $tarjeta['id_empresa'] !== $idEmpresa) {
            return ['ok' => false, 'mensaje' => 'Tarjeta no encontrada.'];
        }

        $monto        = round($monto, 2);
        $devReference = sprintf('nvd-%d-%s-%s-%s', $idEmpresa, $modulo, $idReferencia ?? '0', uniqid('', true));

        $this->repo->crearTransaccion([
            'id_empresa'       => $idEmpresa,
            'tipo'             => 'debito_token',
            'dev_reference'    => $devReference,
            'modulo'           => $modulo,
            'id_referencia'    => $idReferencia,
            'id_cliente'       => $tarjeta['id_cliente'],
            'id_nuvei_tarjeta' => $idNuveiTarjeta,
            'descripcion'      => $descripcion,
            'monto'            => $monto,
            'moneda'           => 'USD',
            'id_usuario'       => $idUsuario,
        ]);

        $payload = [
            'user'  => ['id' => $tarjeta['nuvei_user_id'], 'email' => ''],
            'order' => [
                'amount'            => $monto,
                'description'       => $descripcion,
                'dev_reference'     => $devReference,
                'vat'               => 0,
                'installments'      => 1,
                'installments_type' => 0,
                'taxable_amount'    => $monto,
                'tax_percentage'    => 0,
            ],
            'card' => ['token' => $tarjeta['token']],
        ];

        $resp = $this->apiPost(
            $this->ccapiBaseUrl($cfg['ambiente']) . self::ENDPOINT_DEBIT,
            $payload,
            NuveiAuthHelper::token($cfg['app_code_server'], $cfg['app_key_server'])
        );

        $trx          = $resp['transaction'] ?? [];
        $status       = (string) ($trx['status'] ?? '');
        $statusDetail = (int) ($trx['status_detail'] ?? 0);
        $aprobado     = ($status === 'success' && $statusDetail === 3);

        $this->repo->actualizarResultado($devReference, [
            'nuvei_transaction_id' => $trx['id'] ?? null,
            'status_detail'        => $statusDetail,
            'estado'               => $aprobado ? 'aprobado' : 'rechazado',
            'authorization_code'   => $trx['authorization_code'] ?? null,
            'response_data'        => $resp,
            'id_usuario'           => $idUsuario,
        ]);

        $trans = $this->repo->getTransaccionByDevReference($devReference);

        return [
            'ok'          => true,
            'estado'      => $aprobado ? 'aprobado' : 'rechazado',
            'aprobado'    => $aprobado,
            'transaccion' => $trans,
        ];
    }

    // ─── CANCELACIÓN ──────────────────────────────────────────────────────────

    /**
     * Cancela una solicitud de pago que aún está PENDIENTE (enlace enviado pero no pagado).
     * No llama a la API de Nuvei (no hubo cobro): solo marca la transacción como cancelada.
     */
    public function cancelarPendiente(string $devReference, int $idUsuario = 0): array
    {
        $trans = $this->repo->getTransaccionByDevReference($devReference);
        if (!$trans) {
            return ['ok' => false, 'mensaje' => 'Transacción no encontrada.'];
        }
        if ($trans['estado'] === 'cancelado') {
            return ['ok' => true, 'estado' => 'cancelado', 'transaccion' => $trans];
        }
        if ($trans['estado'] !== 'pendiente') {
            return ['ok' => false, 'mensaje' => 'Solo se puede cancelar una solicitud de pago pendiente.'];
        }

        $this->repo->actualizarResultado($devReference, [
            'estado'        => 'cancelado',
            'response_data' => ['origen' => 'cancelacion_manual'],
            'id_usuario'    => $idUsuario,
        ]);

        $trans = $this->repo->getTransaccionByDevReference($devReference);
        return ['ok' => true, 'estado' => 'cancelado', 'transaccion' => $trans];
    }

    // ─── CONSULTAS ────────────────────────────────────────────────────────────

    public function getTransaccion(string $devReference): ?array
    {
        return $this->repo->getTransaccionByDevReference($devReference);
    }

    public function getTransaccionesPorReferencia(int $idEmpresa, string $modulo, int $idReferencia): array
    {
        return $this->repo->getTransaccionesPorReferencia($idEmpresa, $modulo, $idReferencia);
    }

    /** Retorna la etiqueta legible del estado interno. */
    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'aprobado'  => 'Aprobado',
            'cancelado' => 'Cancelado',
            'rechazado' => 'Rechazado',
            'pendiente' => 'Pendiente',
            'reverso'   => 'Reembolsado',
            'error'     => 'Error',
            default     => ucfirst($estado),
        };
    }

    /** Retorna la clase Bootstrap del badge según el estado. */
    public static function claseBadgeEstado(string $estado): string
    {
        return match ($estado) {
            'aprobado'  => 'success',
            'cancelado' => 'secondary',
            'rechazado' => 'danger',
            'pendiente' => 'warning',
            'reverso'   => 'dark',
            'error'     => 'danger',
            default     => 'secondary',
        };
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

    /**
     * Extrae un mensaje de error legible de una respuesta de Nuvei. La API
     * devuelve a veces un string simple ('message'/'detail') y a veces un
     * diccionario de errores de validación por campo (detail/error como array
     * anidado) — en ese caso se serializa a JSON en vez de intentar castear
     * el array directamente a string (evita "Array to string conversion").
     */
    private function extraerMensajeError(array $resp, string $default): string
    {
        foreach (['detail', 'message'] as $campo) {
            if (isset($resp[$campo]) && is_string($resp[$campo])) {
                return $resp[$campo];
            }
        }

        if (isset($resp['error'])) {
            if (is_string($resp['error'])) {
                return $resp['error'];
            }
            if (is_array($resp['error']) && isset($resp['error']['description']) && is_string($resp['error']['description'])) {
                return $resp['error']['description'];
            }
        }

        foreach (['detail', 'error'] as $campo) {
            if (isset($resp[$campo]) && is_array($resp[$campo])) {
                return json_encode($resp[$campo]);
            }
        }

        return $default;
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
