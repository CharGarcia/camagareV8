<?php
/**
 * Catálogo global de Formatos de Transferencia Bancaria (nivel 3).
 * Ver database/migrations/20260801_transferencia_formatos.sql y
 * app/Services/modulos/Transferencias/Formatters/TransferenciaFormatoConfigurable.php.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\TransferenciaFormatoService;
use App\models\BancoEcuador;

class TransferenciaFormatoController extends Controller
{
    private const BASE_PATH = '/config/transferencia-formatos';
    private TransferenciaFormatoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new TransferenciaFormatoService();
    }

    private function requireNivel(int $min): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a esta sección.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }

    public function index(): void
    {
        $this->requireNivel(3);

        $buscar = trim($_GET['b'] ?? '');
        $rows = $this->service->listar($buscar);

        $this->viewWithLayout('layouts.main', 'transferenciaFormatos.index', [
            'titulo'      => 'Formatos de Transferencia Bancaria',
            'fullWidth'   => true,
            'rows'        => $rows,
            'buscar'      => $buscar,
            'bancos'      => (new BancoEcuador())->getAll(),
            'origenDato'  => TransferenciaFormatoService::ORIGEN_DATO,
            'tiposArchivo'=> TransferenciaFormatoService::TIPOS_ARCHIVO,
        ]);
    }

    public function store(): void
    {
        $this->requireNivel(3);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->service->crear($this->datosDelPost(), (int) ($_SESSION['id_usuario'] ?? 0));
            $_SESSION['config_msg'] = ['success', 'Formato creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function update(): void
    {
        $this->requireNivel(3);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        try {
            $this->service->actualizar($id, $this->datosDelPost(), (int) ($_SESSION['id_usuario'] ?? 0));
            $_SESSION['config_msg'] = ['success', 'Formato actualizado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', 'Error al actualizar: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function delete(): void
    {
        $this->requireNivel(3);
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            $this->service->eliminar($id, (int) ($_SESSION['id_usuario'] ?? 0));
            $_SESSION['config_msg'] = ['success', 'Formato eliminado.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function activar(): void
    {
        $this->cambiarEstado('activo');
    }

    public function desactivar(): void
    {
        $this->cambiarEstado('inactivo');
    }

    private function cambiarEstado(string $estado): void
    {
        $this->requireNivel(3);
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            $this->service->cambiarEstado($id, $estado, (int) ($_SESSION['id_usuario'] ?? 0));
            $_SESSION['config_msg'] = ['success', 'Formato ' . ($estado === 'activo' ? 'activado' : 'desactivado') . '.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function datosDelPost(): array
    {
        $campos = json_decode((string) ($_POST['campos_json'] ?? '[]'), true);
        return [
            'id_banco'           => (int) ($_POST['id_banco'] ?? 0) ?: null,
            'nombre'             => trim($_POST['nombre'] ?? ''),
            'descripcion'        => trim($_POST['descripcion'] ?? ''),
            'tipo_archivo'       => trim($_POST['tipo_archivo'] ?? ''),
            'delimitador'        => trim($_POST['delimitador'] ?? ''),
            'incluye_encabezado' => !empty($_POST['incluye_encabezado']),
            'nombre_hoja'        => trim($_POST['nombre_hoja'] ?? ''),
            'estado'             => trim($_POST['estado'] ?? 'activo'),
            'campos'             => is_array($campos) ? $campos : [],
        ];
    }
}
