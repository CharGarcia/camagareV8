<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\TransferenciaInventarioRepository;
use App\Rules\modulos\TransferenciaInventarioRules;
use App\Services\LogSistemaService;
use App\Services\modulos\TransferenciaActaPdfService;
use App\Services\modulos\TransferenciaInventarioService;

/**
 * Confirmación pública de la recepción de una transferencia de inventario.
 *
 * Ruta SIN login: /recepcion-transferencia/{token}[/confirmar|/rechazar]. La
 * autorización es el token secreto que viaja en el correo con el acta; la
 * empresa dueña del documento se resuelve por ese token, y el repositorio exige
 * que siga activa (§6 de las reglas del sistema).
 *
 * Confirmar NO mueve stock: la transferencia es de un solo paso y el inventario
 * ya se movió al registrarla. Lo que se guarda aquí es la conformidad del
 * destino, con nombre, fecha, IP y comentario.
 */
class RecepcionTransferenciaController extends Controller
{
    private TransferenciaInventarioService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new TransferenciaInventarioService(
            new TransferenciaInventarioRepository(),
            new InventarioRepository(),
            new TransferenciaInventarioRules(),
            new LogSistemaService()
        );
    }

    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        $doc   = $token !== '' ? $this->service->getRecepcionPorToken($token) : null;

        if (!$doc) {
            $this->resultado('error', TransferenciaInventarioService::MSG_TOKEN_INVALIDO);
            return;
        }
        if (($doc['estado'] ?? '') === 'anulada') {
            $this->resultado('info', 'La transferencia ' . $doc['numero'] . ' fue anulada. No hay nada que confirmar.');
            return;
        }

        $recepcion = (string) ($doc['recepcion_estado'] ?? 'pendiente');
        if ($recepcion === 'recibida' || $recepcion === 'rechazada') {
            $this->view('recepcion_transferencia.pagina', [
                'vista' => 'detalle',
                'doc'   => $doc,
                'token' => $token,
                'yaResuelta' => true,
            ]);
            return;
        }

        $this->view('recepcion_transferencia.pagina', [
            'vista'      => 'detalle',
            'doc'        => $doc,
            'token'      => $token,
            'yaResuelta' => false,
        ]);
    }

    /**
     * Acta en PDF desde el enlace del correo, sin login. Es el mismo documento
     * que descarga el sistema (TransferenciaActaPdfService): sirve para abrirla
     * cuando el cliente de correo bloquea o pierde el adjunto. Se muestra en el
     * navegador, y si la transferencia se anuló, el acta lo advierte en rojo.
     */
    public function pdf(): void
    {
        $token = trim($_GET['token'] ?? '');
        $doc   = $token !== '' ? $this->service->getRecepcionPorToken($token) : null;

        if (!$doc) {
            $this->resultado('error', TransferenciaInventarioService::MSG_TOKEN_INVALIDO);
            return;
        }

        try {
            $completo = $this->service->getPorId((int) $doc['id'], (int) $doc['id_empresa']);
            if (!$completo) {
                $this->resultado('error', TransferenciaInventarioService::MSG_TOKEN_INVALIDO);
                return;
            }
            (new TransferenciaActaPdfService())->generar($completo, (int) $doc['id_empresa'], 'I');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->resultado('error', 'No se pudo generar el acta en PDF.');
        }
        exit;
    }

    public function confirmar(): void
    {
        $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
        try {
            $res = $this->service->confirmarRecepcionPorToken(
                $token,
                trim($_POST['nombre'] ?? ''),
                trim($_POST['comentario'] ?? ''),
                $this->ipCliente()
            );
            $this->resultado('ok', 'Recepción confirmada. La transferencia ' . $res['numero'] . ' queda registrada como recibida conforme.');
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    public function rechazar(): void
    {
        $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
        try {
            $res = $this->service->rechazarRecepcionPorToken(
                $token,
                trim($_POST['nombre'] ?? ''),
                trim($_POST['motivo'] ?? ''),
                $this->ipCliente()
            );
            $this->resultado('ok', 'Se registró el rechazo de la transferencia ' . $res['numero'] . '. La bodega que la envió será notificada en el sistema.');
        } catch (\Throwable $e) {
            $this->resultado('error', $e->getMessage());
        }
    }

    private function resultado(string $tipo, string $mensaje): void
    {
        $this->view('recepcion_transferencia.pagina', [
            'vista'   => 'resultado',
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
        ]);
    }

    /** IP del cliente (detrás del proxy llega una lista: se guarda la primera). */
    private function ipCliente(): string
    {
        $reenviada = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($reenviada !== '') {
            return trim(explode(',', $reenviada)[0]);
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
