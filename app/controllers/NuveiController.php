<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Empresa;
use App\repositories\NuveiRepository;
use App\Services\NuveiService;

/**
 * NuveiController
 *
 * Controlador PÚBLICO que maneja el webhook y las páginas públicas de Nuvei.
 * No requiere sesión de usuario (Nuvei notifica servidor-a-servidor y los
 * clientes externos pagan desde el portal).
 *
 * Rutas:
 *   POST /nuvei/webhook            → notificación server-a-server de Nuvei (solo logueo, ver Fase 1)
 *   GET  /nuvei/pago/{token}       → página pública con el modal de Checkout
 *   POST /nuvei/checkout-resultado → recibe el onResponse del modal (canal de confirmación real)
 */
class NuveiController extends Controller
{
    private NuveiService $nuvei;

    public function __construct()
    {
        parent::__construct();
        $this->nuvei = new NuveiService(new NuveiRepository());
    }

    public function webhook(): void
    {
        header('Content-Type: application/json');

        $raw     = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = ['_raw' => $raw];
        }

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];

        try {
            $this->nuvei->procesarWebhook($payload, $headers);
        } catch (\Throwable $e) {
            error_log('[Nuvei] Error procesando webhook: ' . $e->getMessage());
        }

        // Responder 200 siempre: mientras no se confirme la política de reintentos
        // de Nuvei, se prioriza no perder el registro crudo (ya quedó en nuvei_webhook_log).
        echo json_encode(['ok' => true]);
        exit;
    }

    /**
     * Página pública que hospeda el modal de Checkout de Nuvei.
     * Ruta: /nuvei/pago/{dev_reference}
     */
    public function pago(): void
    {
        $devReference = trim($_GET['token'] ?? '');

        if ($devReference === '') {
            $this->view('publica.nuvei.pago', ['estado' => 'error']);
            return;
        }

        try {
            $trans = $this->nuvei->getTransaccion($devReference);

            if (!$trans) {
                $this->view('publica.nuvei.pago', ['estado' => 'error']);
                return;
            }

            // No iniciar un cobro nuevo a nombre de una empresa inactiva. Si el pago
            // ya se había hecho antes de desactivarla, el callback/webhook de resultado
            // sigue sin bloquearse (no hay que perder un cobro ya ejecutado en Nuvei).
            if (!(new Empresa())->estaActiva((int) $trans['id_empresa'])) {
                $this->view('publica.nuvei.pago', ['estado' => 'error']);
                return;
            }

            if ($trans['estado'] !== 'pendiente') {
                $this->view('publica.nuvei.pago', [
                    'estado'         => $trans['estado'],
                    'descripcion'    => $trans['descripcion'] ?? '',
                    'monto'          => (float) $trans['monto'],
                    'empresa_nombre' => '',
                ]);
                return;
            }

            // La 'reference' guardada puede haber caducado del lado de Nuvei si el
            // cliente abre el enlace mucho después de enviado; se renueva en cada
            // carga para que el mismo enlace de correo siga funcionando siempre.
            $refresco = $this->nuvei->refrescarReferenciaCheckout($trans);
            if (!$refresco['ok']) {
                $this->view('publica.nuvei.pago', ['estado' => 'error']);
                return;
            }

            $cfg     = $this->nuvei->getConfig((int) $trans['id_empresa']);
            $envMode = ($cfg['ambiente'] ?? 'sandbox') === 'production' ? 'prod' : 'stg';

            $this->view('publica.nuvei.pago', [
                'estado'         => null,
                'reference'      => $refresco['reference'],
                'dev_reference'  => $devReference,
                'env_mode'       => $envMode,
                'descripcion'    => $trans['descripcion'] ?? '',
                'monto'          => (float) $trans['monto'],
                'empresa_nombre' => '',
                'url_resultado'  => rtrim(BASE_URL, '/') . '/nuvei/checkout-resultado',
            ]);
        } catch (\Throwable $e) {
            $this->view('publica.nuvei.pago', ['estado' => 'error']);
        }
    }

    /**
     * Recibe el resultado del modal de Checkout (callback onResponse del navegador).
     * Es el canal de confirmación real mientras el webhook solo loguea (ver Fase 1).
     */
    public function checkoutResultadoAjax(): void
    {
        header('Content-Type: application/json');

        $devReference    = trim($_POST['dev_reference'] ?? '');
        $transactionData = json_decode((string) ($_POST['transaction'] ?? ''), true);

        if ($devReference === '' || !is_array($transactionData)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Datos de confirmación inválidos.']);
            exit;
        }

        try {
            $resultado = $this->nuvei->confirmarCheckout($devReference, $transactionData, 0);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al confirmar el pago: ' . $e->getMessage()]);
            exit;
        }

        if (!$resultado['ok']) {
            echo json_encode($resultado);
            exit;
        }

        $trans  = $resultado['transaccion'];
        $estado = $resultado['estado'];

        if ($estado === 'aprobado' || $estado === 'rechazado') {
            $this->procesarResultado($trans, $estado === 'aprobado');
        }

        echo json_encode([
            'ok'        => true,
            'estado'    => $estado,
            'url_exito' => ($estado === 'aprobado' && !empty($trans['url_exito'])) ? $trans['url_exito'] : null,
        ]);
        exit;
    }

    /**
     * Acciones al resolverse una transacción de Checkout: genera el registro
     * de negocio si fue aprobada (según el módulo) y envía siempre el correo
     * de confirmación de pago (aprobado o rechazado) — requisito de Nuvei.
     */
    private function procesarResultado(array $trans, bool $aprobado): void
    {
        if ($aprobado) {
            try {
                if (($trans['modulo'] ?? '') === 'factura_venta') {
                    $svc = new \App\Services\modulos\FacturaVentaService(
                        new \App\repositories\modulos\FacturaVentaRepository(),
                        new \App\Rules\modulos\FacturaVentaRules(),
                        new \App\Services\LogSistemaService()
                    );
                    $svc->generarIngresoDesdeNuvei($trans);
                }
            } catch (\Throwable $e) {
                // No bloquear la pantalla de éxito del cliente si falla el registro
                error_log('[Nuvei] Error procesando aprobación: ' . $e->getMessage());
            }
        }

        try {
            $this->enviarCorreoConfirmacion($trans, $aprobado);
        } catch (\Throwable $e) {
            error_log('[Nuvei] Error enviando correo de confirmación: ' . $e->getMessage());
        }
    }

    /**
     * Correo de confirmación de pago (aprobado o rechazado) — requisito
     * explícito de Nuvei, con transaction_ID y código de autorización.
     */
    private function enviarCorreoConfirmacion(array $trans, bool $aprobado): void
    {
        $correo = trim((string) ($trans['email'] ?? ''));
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $idEmpresa     = (int) $trans['id_empresa'];
        $empresaModel  = new \App\models\Empresa();
        $empresaData   = $empresaModel->getPorId($idEmpresa) ?? [];
        $empresaNombre = $empresaData['nombre_comercial'] ?? $empresaData['razon_social'] ?? '';

        $clienteNombre = 'Cliente';
        if (!empty($trans['id_cliente'])) {
            try {
                $stCli = \App\core\Database::getConnection()->prepare(
                    "SELECT nombre FROM clientes WHERE id = ? AND id_empresa = ?"
                );
                $stCli->execute([(int) $trans['id_cliente'], $idEmpresa]);
                $nombre = $stCli->fetchColumn();
                if ($nombre) {
                    $clienteNombre = (string) $nombre;
                }
            } catch (\Throwable $e) {}
        }

        $emailSvc = new \App\Services\EnvioDocumentosSRIService();
        $emailSvc->enviarConfirmacionPagoNuvei(
            $idEmpresa,
            $correo,
            $clienteNombre,
            $empresaNombre,
            (float) $trans['monto'],
            (string) ($trans['descripcion'] ?? ''),
            $aprobado,
            (string) ($trans['nuvei_transaction_id'] ?? ''),
            (string) ($trans['authorization_code'] ?? '')
        );
    }

    /**
     * Página pública que hospeda el widget de tokenización Add Card de Nuvei.
     * Ruta: /nuvei/tarjeta/{token}
     */
    public function tarjeta(): void
    {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            $this->view('publica.nuvei.tarjeta', ['estado' => 'error']);
            return;
        }

        try {
            $sol = $this->nuvei->getSolicitudTarjeta($token);

            if (!$sol) {
                $this->view('publica.nuvei.tarjeta', ['estado' => 'error']);
                return;
            }

            // Mismo criterio que en pago(): no ofrecer el widget de tokenización de
            // tarjeta a nombre de una empresa inactiva.
            if (!(new Empresa())->estaActiva((int) $sol['id_empresa'])) {
                $this->view('publica.nuvei.tarjeta', ['estado' => 'error']);
                return;
            }

            if ($sol['estado'] !== 'pendiente') {
                $this->view('publica.nuvei.tarjeta', ['estado' => $sol['estado']]);
                return;
            }

            $cfg     = $this->nuvei->getConfig((int) $sol['id_empresa']);
            $envMode = ($cfg['ambiente'] ?? 'sandbox') === 'production' ? 'prod' : 'stg';

            $this->view('publica.nuvei.tarjeta', [
                'estado'        => null,
                'token'         => $token,
                'env_mode'      => $envMode,
                'app_code'      => $cfg['app_code_server'] ?? '',
                'app_key'       => $cfg['app_key_server']  ?? '',
                'id_cliente'    => (int) $sol['id_cliente'],
                'email'         => $sol['email'] ?? '',
                'url_resultado' => rtrim(BASE_URL, '/') . '/nuvei/tarjeta-resultado',
            ]);
        } catch (\Throwable $e) {
            $this->view('publica.nuvei.tarjeta', ['estado' => 'error']);
        }
    }

    /**
     * Recibe el token de tarjeta generado por el SDK Add Card en el navegador
     * del cliente y lo persiste. Si la solicitud viene de una suscripción,
     * vincula la tarjeta guardada a esa suscripción automáticamente.
     */
    public function tarjetaResultadoAjax(): void
    {
        header('Content-Type: application/json');

        $token    = trim($_POST['token'] ?? '');
        $cardData = json_decode((string) ($_POST['card'] ?? ''), true);

        if ($token === '' || !is_array($cardData)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Datos de la tarjeta inválidos.']);
            exit;
        }

        try {
            $resultado = $this->nuvei->guardarTarjetaDesdeSolicitud($token, $cardData, 0);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar la tarjeta: ' . $e->getMessage()]);
            exit;
        }

        if (!$resultado['ok']) {
            echo json_encode($resultado);
            exit;
        }

        $solicitud = $resultado['solicitud'] ?? null;
        if ($solicitud && ($solicitud['modulo'] ?? '') === 'suscripcion' && !empty($solicitud['id_referencia']) && !empty($resultado['id_nuvei_tarjeta'])) {
            try {
                $suscRepo = new \App\repositories\modulos\SuscripcionesRepository();
                $suscRepo->updateNuveiTarjeta((int) $solicitud['id_referencia'], (int) $resultado['id_nuvei_tarjeta']);
            } catch (\Throwable $e) {
                error_log('[Nuvei] Error vinculando tarjeta a suscripción: ' . $e->getMessage());
            }
        }

        echo json_encode(['ok' => true, 'mensaje' => 'Tarjeta registrada correctamente.']);
        exit;
    }
}
