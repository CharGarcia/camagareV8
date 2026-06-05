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

        if ($estado === 'aprobado') {
            $this->procesarAprobacion($trans);
        }

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
     * Acciones específicas por módulo cuando un pago se aprueba.
     * Para factura_venta: genera automáticamente el Ingreso (cobro) vinculado.
     */
    private function procesarAprobacion(array $trans): void
    {
        try {
            if (($trans['modulo'] ?? '') === 'factura_venta') {
                $svc = new \App\Services\modulos\FacturaVentaService(
                    new \App\repositories\modulos\FacturaVentaRepository(),
                    new \App\Rules\modulos\FacturaVentaRules(),
                    new \App\Services\LogSistemaService()
                );
                $svc->generarIngresoDesdePayphone($trans);
            } elseif (($trans['modulo'] ?? '') === 'suscripciones') {
                $this->registrarMetodoSuscripcion($trans);
            }
        } catch (\Throwable $e) {
            // No bloquear la pantalla de éxito del cliente si falla el registro
            error_log('[Payphone] Error procesando aprobación: ' . $e->getMessage());
        }
    }

    /**
     * Guarda en la suscripción el método de pago Payphone tras un pago aprobado.
     * No se almacenan datos sensibles: solo referencia y datos no sensibles (últimos 4, marca).
     */
    private function registrarMetodoSuscripcion(array $trans): void
    {
        $idSusc    = (int) ($trans['id_referencia'] ?? 0);
        $idEmpresa = (int) ($trans['id_empresa'] ?? 0);
        if ($idSusc <= 0 || $idEmpresa <= 0) {
            return;
        }

        // Extraer datos no sensibles de la respuesta de Payphone (si vienen)
        $resp = $trans['response_data'] ?? [];
        if (is_string($resp)) {
            $resp = json_decode($resp, true) ?: [];
        }
        $last4 = (string) ($resp['lastDigits'] ?? $resp['cardLastDigits'] ?? $resp['last4'] ?? '');
        $brand = (string) ($resp['cardBrand'] ?? $resp['cardType'] ?? '');

        $svc = new \App\Services\modulos\SuscripcionesService(
            new \App\repositories\modulos\SuscripcionesRepository(),
            new \App\Rules\modulos\SuscripcionesRules(),
            new \App\Services\LogSistemaService()
        );
        $svc->guardarMetodoPayphone($idSusc, $idEmpresa, [
            'client_tx_id' => $trans['client_transaction_id'] ?? null,
            'estado'       => 'registrada',
            'last4'        => $last4 !== '' ? substr($last4, -4) : null,
            'brand'        => $brand !== '' ? $brand : null,
        ], 0);
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

        if ($estado === 'aprobado') {
            $this->procesarAprobacion($trans);
        }

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
