<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\CitaPortalRepository;
use App\Services\modulos\CitaPortalService;
use App\Services\LogSistemaService;

/**
 * Controlador PÚBLICO — sin autenticación.
 * Portal de reserva de citas accesible mediante /reservas/{slug}
 */
class ReservasController extends Controller
{
    private CitaPortalService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CitaPortalService(
            new CitaPortalRepository(),
            new LogSistemaService()
        );
    }

    // ─── PORTAL PRINCIPAL ─────────────────────────────────────────────────────

    public function index(): void
    {
        $slug = trim($_GET['slug'] ?? '');

        try {
            $portalConfig = $this->service->getConfigBySlug($slug);
        } catch (\Exception $e) {
            $this->renderError($e->getMessage());
            return;
        }

        $catalogos = $this->service->getCatalogos((int) $portalConfig['id_empresa']);

        $this->view('publica.reservas.portal', [
            'portalConfig' => $portalConfig,
            'tipos'        => $catalogos['tipos'],
            'recursos'     => $catalogos['recursos'],
            'slug'         => $slug,
        ]);
    }

    // ─── AJAX: disponibilidad de slots ────────────────────────────────────────

    public function disponibilidad(): void
    {
        header('Content-Type: application/json');
        $slug = trim($_GET['slug'] ?? '');

        try {
            $portalConfig  = $this->service->getConfigBySlug($slug);
            $idEmpresa     = (int) $portalConfig['id_empresa'];
            $fecha         = trim($_GET['fecha']         ?? '');
            $idTipoCita    = (int) ($_GET['id_tipo_cita'] ?? 0);
            $idRecurso     = (int) ($_GET['id_recurso']   ?? 0) ?: null;

            if (!$fecha || !$idTipoCita) throw new \Exception('Parámetros incompletos.');

            $slots = $this->service->getDisponibilidad(
                $idEmpresa, $fecha, $idTipoCita, $idRecurso,
                (int) ($portalConfig['max_dias_anticipacion']  ?? 30),
                (int) ($portalConfig['min_horas_anticipacion'] ?? 2)
            );
            echo json_encode(['ok' => true, 'slots' => $slots]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: verificar si el cliente existe ─────────────────────────────────

    public function verificarCliente(): void
    {
        header('Content-Type: application/json');
        $slug = trim($_GET['slug'] ?? '');

        try {
            $portalConfig  = $this->service->getConfigBySlug($slug);
            $identificacion = trim($_POST['identificacion'] ?? '');
            $email          = trim($_POST['email']          ?? '');
            $resultado      = $this->service->verificarCliente($identificacion, $email, (int) $portalConfig['id_empresa']);
            echo json_encode(['ok' => true] + $resultado);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: crear reserva ──────────────────────────────────────────────────

    public function reservar(): void
    {
        header('Content-Type: application/json');
        $slug = trim($_GET['slug'] ?? '');

        try {
            $portalConfig = $this->service->getConfigBySlug($slug);

            $data = [
                'id_tipo_cita'  => (int) ($_POST['id_tipo_cita']  ?? 0),
                'id_recurso'    => (int) ($_POST['id_recurso']    ?? 0) ?: null,
                'id_cliente'    => (int) ($_POST['id_cliente']    ?? 0) ?: null,
                'fecha_inicio'  => trim($_POST['fecha_inicio']  ?? ''),
                'fecha_fin'     => trim($_POST['fecha_fin']      ?? ''),
                'titulo'        => trim($_POST['titulo']         ?? ''),
                'notas'         => trim($_POST['notas']          ?? ''),
                // Datos cliente nuevo
                'nombres'       => trim($_POST['nombres']        ?? ''),
                'apellidos'     => trim($_POST['apellidos']      ?? ''),
                'email'         => trim($_POST['email']          ?? ''),
                'telefono'      => trim($_POST['telefono']       ?? ''),
                'identificacion'=> trim($_POST['identificacion'] ?? ''),
            ];

            $idCita = $this->service->reservar($data, $portalConfig);
            $cita   = $this->service->getCitaById($idCita);

            echo json_encode([
                'ok'      => true,
                'mensaje' => 'Tu cita ha sido registrada exitosamente.',
                'id'      => $idCita,
                'estado'  => $cita['estado'] ?? 'pendiente',
                'requiere_confirmacion' => (bool) ($portalConfig['requiere_confirmacion'] ?? false),
            ]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── CONFIRMACIÓN ─────────────────────────────────────────────────────────

    public function confirmacion(): void
    {
        $slug   = trim($_GET['slug'] ?? '');
        $idCita = (int) ($_GET['id']   ?? 0);

        try {
            $portalConfig = $this->service->getConfigBySlug($slug);
            $cita         = $idCita > 0 ? $this->service->getCitaById($idCita) : null;
        } catch (\Exception $e) {
            $this->renderError($e->getMessage());
            return;
        }

        $this->view('publica.reservas.confirmacion', [
            'portalConfig' => $portalConfig,
            'cita'         => $cita,
            'slug'         => $slug,
        ]);
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    private function renderError(string $mensaje): void
    {
        $this->view('publica.reservas.error', ['mensaje' => $mensaje]);
    }
}
