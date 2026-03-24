<?php
/**
 * Controlador AsignarEmpresas - Asignar empresas a usuarios
 * Super admin: asigna a admins y usuarios. Cualquier empresa.
 * Admin: asigna solo a usuarios finales. Solo empresas que tiene asignadas.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\EmpresaAsignada;

class AsignarEmpresasController extends Controller
{
    private EmpresaAsignada $model;
    private const BASE_PATH = '/config/asignar-empresas';
    private const PER_PAGE = 10;

    public function __construct()
    {
        parent::__construct();
        $this->model = new EmpresaAsignada();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $buscar = trim($_GET['b'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $data = $this->model->getUsuariosAsignables($idActual, $nivel, $buscar, $page, self::PER_PAGE);
        $totalPages = (int) ceil($data['total'] / self::PER_PAGE);

        $this->viewWithLayout('layouts.main', 'asignarEmpresas.index', [
            'titulo' => 'Asignar empresas a usuarios',
            'rows' => $data['rows'],
            'total' => $data['total'],
            'page' => $page,
            'totalPages' => $totalPages,
            'buscar' => $buscar,
            'nivel' => $nivel,
        ]);
    }

    /**
     * AJAX: empresas asignadas a un usuario (retorna JSON)
     */
    public function empresasUsuarioJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idUsuario = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($idUsuario <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
        }

        $idActual = (int) $_SESSION['id_usuario'];
        $nivel = (int) $_SESSION['nivel'];
        $empresas = $this->model->getEmpresasDeUsuario($idUsuario, $idActual, $nivel);

        $html = '';
        foreach ($empresas as $e) {
            $puedeQuitar = !empty($e['puede_quitar']);
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($e['nombre_comercial'] ?? '') . '</td>';
            $html .= '<td><code>' . htmlspecialchars($e['ruc'] ?? '') . '</code></td>';
            $html .= '<td class="text-end">';
            if ($puedeQuitar) {
                $html .= '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-empresa cmg-btn-table" data-id="' . (int)($e['id_registro'] ?? 0) . '" title="Quitar"><i class="bi bi-trash"></i></button>';
            } else {
                $html .= '<span class="text-muted small">Asignada por otro</span>';
            }
            $html .= '</td></tr>';
        }

        $this->json(['html' => $html, 'total' => count($empresas)]);
    }

    /**
     * AJAX: empresas disponibles para asignar (retorna JSON)
     */
    public function empresasDisponiblesJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idUsuario = (int) ($_GET['id_usuario'] ?? 0);
        $buscar = trim($_GET['q'] ?? '');
        $idActual = (int) $_SESSION['id_usuario'];
        $nivel = (int) $_SESSION['nivel'];

        if ($idUsuario <= 0) {
            $this->json(['empresas' => []]);
            return;
        }

        $empresas = $this->model->getEmpresasDisponiblesParaAsignar($idActual, $nivel, $idUsuario, $buscar);
        $this->json(['empresas' => $empresas]);
    }

    public function asignar(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $idActual = (int) $_SESSION['id_usuario'];
        $nivel = (int) $_SESSION['nivel'];

        $redirectTo = trim($_POST['redirect'] ?? '');
        $targetUrl = in_array($redirectTo, ['usuarios-sistema', 'asignar-empresas', 'empresas-sistema'], true)
            ? BASE_URL . '/config/' . $redirectTo
            : BASE_URL . self::BASE_PATH;
        $msgKey = match ($redirectTo) {
            'usuarios-sistema' => 'usuarios_msg',
            'empresas-sistema' => 'empresas_msg',
            default => 'asignar_msg',
        };

        if ($idEmpresa <= 0 || $idUsuario <= 0) {
            $_SESSION[$msgKey] = ['danger', 'Datos incompletos.'];
            $this->redirect($targetUrl);
        }

        if (!$this->puedeAsignar($idActual, $nivel, $idUsuario, $idEmpresa)) {
            $_SESSION[$msgKey] = ['danger', 'No tiene permiso para asignar esa empresa.'];
            $this->redirect($targetUrl);
        }

        try {
            if ($this->model->asignar($idEmpresa, $idUsuario, $idActual)) {
                $_SESSION[$msgKey] = ['success', 'Empresa asignada correctamente.'];
            } else {
                $_SESSION[$msgKey] = ['danger', 'La empresa ya estaba asignada.'];
            }
        } catch (\Throwable $e) {
            $_SESSION[$msgKey] = ['danger', 'Error: ' . $e->getMessage()];
        }
        $this->redirect($targetUrl);
    }

    public function quitar(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idRegistro = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $idActual = (int) $_SESSION['id_usuario'];
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $redirectTo = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
        $targetUrl = in_array($redirectTo, ['usuarios-sistema', 'asignar-empresas', 'empresas-sistema'], true)
            ? BASE_URL . '/config/' . $redirectTo
            : BASE_URL . self::BASE_PATH;
        $msgKey = match ($redirectTo) {
            'usuarios-sistema' => 'usuarios_msg',
            'empresas-sistema' => 'empresas_msg',
            default => 'asignar_msg',
        };

        if ($idRegistro <= 0) {
            $_SESSION[$msgKey] = ['danger', 'ID inválido.'];
            $this->redirect($targetUrl);
        }

        try {
            if ($this->model->quitar($idRegistro, $idActual, $nivel)) {
                $_SESSION[$msgKey] = ['success', 'Empresa quitada correctamente.'];
            } else {
                $_SESSION[$msgKey] = ['danger', 'No puede quitar esa asignación.'];
            }
        } catch (\Throwable $e) {
            $_SESSION[$msgKey] = ['danger', 'Error: ' . $e->getMessage()];
        }
        $this->redirect($targetUrl);
    }

    private function puedeAsignar(int $idActual, int $nivel, int $idUsuarioDestino, int $idEmpresa): bool
    {
        if ($nivel >= 3) return true;

        $empresas = $this->model->getEmpresasDisponiblesParaAsignar($idActual, $nivel, $idUsuarioDestino, '');
        foreach ($empresas as $e) {
            if ((int) ($e['id_empresa'] ?? 0) === $idEmpresa) return true;
        }
        return false;
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['asignar_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
