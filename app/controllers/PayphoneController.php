<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\PayphoneRepository;
use App\Services\PayphoneService;

/**
 * PayphoneController
 *
 * Controlador PÚBLICO que maneja los retornos de Payphone tras el pago.
 * No requiere sesión de usuario (clientes externos pagan desde el portal).
 *
 * Rutas:
 *   GET  /payphone/retorno     → el cliente llega aquí tras pagar (Payphone lo redirige)
 *   GET  /payphone/cancelacion → el cliente llega aquí si cancela el pago
 */
class PayphoneController extends Controller
{
    private PayphoneService $pp;

    public function __construct()
    {
        parent::__construct();
        $this->pp = new PayphoneService(new PayphoneRepository());
    }

    /**
     * Retorno tras pago (éxito o fallo técnico).
     * Payphone redirige aquí con GET: ?clientTransactionId=...&id=...
     */
    public function retorno(): void
    {
        $ctid      = trim($_GET['clientTransactionId'] ?? '');
        $paymentId = (int) ($_GET['id'] ?? 0);

        if ($ctid === '' || $paymentId <= 0) {
            $this->mostrarError('Parámetros de retorno inválidos.');
            return;
        }

        try {
            $resultado = $this->pp->confirmarPago($ctid, $paymentId, 0);
        } catch (\Throwable $e) {
            $this->mostrarError('Error al confirmar el pago: ' . $e->getMessage());
            return;
        }

        if (!$resultado['ok']) {
            $this->mostrarError($resultado['mensaje'] ?? 'Error al procesar el pago.');
            return;
        }

        $trans  = $resultado['transaccion'];
        $estado = $resultado['estado']; // aprobado | cancelado | rechazado | error | pendiente

        // Si hay url_exito definida y el pago fue aprobado → redirigir al módulo
        if ($estado === 'aprobado' && !empty($trans['url_exito'])) {
            $sep = str_contains($trans['url_exito'], '?') ? '&' : '?';
            header('Location: ' . $trans['url_exito'] . $sep . 'ctid=' . urlencode($ctid));
            exit;
        }

        // Mostrar página de resultado genérica
        $this->view('publica.payphone.resultado', [
            'estado'      => $estado,
            'transaccion' => $trans,
            'resultado'   => $resultado,
        ]);
    }

    /**
     * Página pública de pago: muestra la Cajita de Pagos al cliente.
     * Ruta: /pago/{clientTransactionId}
     */
    public function pago(): void
    {
        $ctid = trim($_GET['token'] ?? '');

        if ($ctid === '') {
            $this->view('publica.payphone.pago', ['estado' => 'error', 'widgetConfig' => null]);
            return;
        }

        try {
            $trans = $this->pp->getTransaccionByClientId($ctid);

            if (!$trans) {
                $this->view('publica.payphone.pago', ['estado' => 'error', 'widgetConfig' => null]);
                return;
            }

            // Si ya fue procesada mostrar resultado directamente
            if ($trans['estado'] !== 'pendiente') {
                $this->view('publica.payphone.pago', [
                    'estado'        => $trans['estado'],
                    'widgetConfig'  => null,
                    'descripcion'   => $trans['descripcion'] ?? '',
                    'monto'         => \App\Services\PayphoneService::centavosADolares((int) $trans['monto']),
                    'empresa_nombre'=> '',
                ]);
                return;
            }

            $widgetConfig = $this->pp->getWidgetConfigFromTransaction($trans);

            $this->view('publica.payphone.pago', [
                'estado'        => null,
                'widgetConfig'  => $widgetConfig,
                'descripcion'   => $trans['descripcion'] ?? '',
                'monto'         => \App\Services\PayphoneService::centavosADolares((int) $trans['monto']),
                'empresa_nombre'=> '',
            ]);
        } catch (\Throwable $e) {
            $this->view('publica.payphone.pago', ['estado' => 'error', 'widgetConfig' => null]);
        }
    }

    /**
     * Retorno de la Cajita de Pagos (widget embebido).
     * Payphone redirige aquí con GET: ?clientTransactionId=...&id=...
     * La confirmación usa el endpoint exclusivo de cajita.
     */
    public function cajitaRetorno(): void
    {
        $ctid      = trim($_GET['clientTransactionId'] ?? '');
        $paymentId = (int) ($_GET['id'] ?? 0);

        if ($ctid === '' || $paymentId <= 0) {
            $this->mostrarError('Parámetros de retorno inválidos.');
            return;
        }

        try {
            $resultado = $this->pp->confirmarCajitaPago($ctid, $paymentId, 0);
        } catch (\Throwable $e) {
            $this->mostrarError('Error al confirmar el pago: ' . $e->getMessage());
            return;
        }

        if (!$resultado['ok']) {
            $this->mostrarError($resultado['mensaje'] ?? 'Error al procesar el pago.');
            return;
        }

        $trans  = $resultado['transaccion'];
        $estado = $resultado['estado'];

        if ($estado === 'aprobado' && !empty($trans['url_exito'])) {
            $sep = str_contains($trans['url_exito'], '?') ? '&' : '?';
            header('Location: ' . $trans['url_exito'] . $sep . 'ctid=' . urlencode($ctid));
            exit;
        }

        $this->view('publica.payphone.resultado', [
            'estado'      => $estado,
            'transaccion' => $trans,
            'resultado'   => $resultado,
        ]);
    }

    /**
     * Cancelación: el cliente hizo clic en "Cancelar" en Payphone.
     * Payphone redirige aquí con GET: ?clientTransactionId=...
     */
    public function cancelacion(): void
    {
        $ctid = trim($_GET['clientTransactionId'] ?? '');

        // Actualizar estado a cancelado si la transacción sigue pendiente
        if ($ctid !== '') {
            try {
                $trans = $this->pp->getTransaccionByClientId($ctid);
                if ($trans && $trans['estado'] === 'pendiente') {
                    // Marcar como cancelado sin llamar a la API de confirmación
                    (new PayphoneRepository())->actualizarResultado($ctid, [
                        'transaction_status' => 'Cancelled',
                        'estado'             => 'cancelado',
                        'response_data'      => ['origen' => 'cancelacion_cliente'],
                    ]);
                    $trans = $this->pp->getTransaccionByClientId($ctid);
                }
            } catch (\Throwable) {
                $trans = null;
            }
        } else {
            $trans = null;
        }

        $this->view('publica.payphone.resultado', [
            'estado'      => 'cancelado',
            'transaccion' => $trans,
            'resultado'   => [],
        ]);
    }

    // ─── PRIVADOS ─────────────────────────────────────────────────────────────

    private function mostrarError(string $mensaje): void
    {
        $this->view('publica.payphone.resultado', [
            'estado'      => 'error',
            'transaccion' => null,
            'resultado'   => ['mensaje' => $mensaje],
        ]);
    }
}
