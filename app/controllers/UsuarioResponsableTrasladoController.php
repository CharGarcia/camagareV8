<?php
/**
 * Vincular usuarios (login) a responsables de traslado, para que la app móvil
 * (módulo Entregas de Consignaciones) le muestre a un repartidor sin "acceso
 * total" solo las entregas de los responsables que le hemos asignado aquí.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\EmpresaAsignada;
use App\Services\UsuarioResponsableTrasladoService;
use Throwable;

class UsuarioResponsableTrasladoController extends Controller
{
    private UsuarioResponsableTrasladoService $service;
    private EmpresaAsignada $empresaAsignadaModel;

    public function __construct()
    {
        parent::__construct();
        $this->service = new UsuarioResponsableTrasladoService();
        $this->empresaAsignadaModel = new EmpresaAsignada();
    }

    /** GET: empresas asignadas al usuario (para el selector "Empresa" de la pestaña). */
    public function empresasUsuarioJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idUsuario = (int) ($_GET['id'] ?? 0);
        if ($idUsuario <= 0) {
            $this->json(['empresas' => []]);
            return;
        }

        $rows = $this->empresaAsignadaModel->getEmpresasDeUsuario($idUsuario);
        $empresas = array_map(fn($r) => [
            'id_empresa' => (int) $r['id_empresa'],
            'nombre_comercial' => $r['nombre_comercial'],
        ], $rows);

        $this->json(['empresas' => $empresas]);
    }

    /** GET: responsables de traslado ya vinculados a este usuario en esta empresa. */
    public function listarJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idUsuario = (int) ($_GET['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_GET['id_empresa'] ?? 0);
        if ($idUsuario <= 0 || $idEmpresa <= 0) {
            $this->json(['rows' => []]);
            return;
        }

        $this->json(['rows' => $this->service->listar($idUsuario, $idEmpresa)]);
    }

    /** GET: responsables de traslado de la empresa que aún no están vinculados a este usuario. */
    public function disponiblesJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idUsuario = (int) ($_GET['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_GET['id_empresa'] ?? 0);
        if ($idUsuario <= 0 || $idEmpresa <= 0) {
            $this->json(['responsables' => []]);
            return;
        }

        $this->json(['responsables' => $this->service->disponibles($idUsuario, $idEmpresa)]);
    }

    public function vincular(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $idResponsable = (int) ($_POST['id_responsable_traslado'] ?? 0);
        $idActual = (int) $_SESSION['id_usuario'];

        if ($idUsuario <= 0 || $idEmpresa <= 0 || $idResponsable <= 0) {
            $this->json(['ok' => false, 'msg' => 'Datos incompletos.']);
            return;
        }

        try {
            $resultado = $this->service->vincular($idEmpresa, $idUsuario, $idResponsable, $idActual);
            $this->json([
                'ok' => true,
                'msg' => $resultado['creado'] ? 'Responsable vinculado correctamente.' : 'Ese responsable ya estaba vinculado.',
            ]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function desvincular(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $idActual = (int) $_SESSION['id_usuario'];

        if ($id <= 0 || $idEmpresa <= 0) {
            $this->json(['ok' => false, 'msg' => 'Datos incompletos.']);
            return;
        }

        try {
            $this->service->desvincular($id, $idEmpresa, $idActual);
            $this->json(['ok' => true, 'msg' => 'Responsable desvinculado.']);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $this->json(['ok' => false, 'msg' => 'No tiene permisos.']);
        }
    }
}
