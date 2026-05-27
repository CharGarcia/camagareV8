<?php
declare(strict_types=1);

namespace App\controllers;

use App\controllers\BaseController;
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
class PayphoneController extends BaseController
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
