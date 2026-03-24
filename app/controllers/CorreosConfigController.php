<?php
/**
 * Controlador CorreosConfig - Gestión de configuraciones de correo por propósito
 * Propósitos: recuperar_password, notificaciones, cobros, etc.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\CorreoConfig;

class CorreosConfigController extends Controller
{
    private CorreoConfig $model;
    private const BASE_PATH = '/config/correos-config';

    public function __construct()
    {
        parent::__construct();
        $this->model = new CorreoConfig();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'codigo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, CorreoConfig::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'correosConfig.index', [
            'titulo' => 'Configuración de correos',
            'rows' => $rows,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
            'codigosSugeridos' => CorreoConfig::CODIGOS_SUGERIDOS,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nombreRemitente = trim($_POST['nombre_remitente'] ?? '');
        $hostSmtp = trim($_POST['host_smtp'] ?? 'smtp.gmail.com');
        $puertoSmtp = (int) ($_POST['puerto_smtp'] ?? 587);
        $usuarioSmtp = trim($_POST['usuario_smtp'] ?? '');
        $passwordSmtp = (string) ($_POST['password_smtp'] ?? '');
        $encryption = trim($_POST['encryption'] ?? 'tls');
        $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;

        $err = $this->validar($codigo, $nombre, $email, $hostSmtp, $puertoSmtp, true, $passwordSmtp);
        if ($err !== '') {
            $this->responderError($err, $esAjax);
            return;
        }

        if ($this->model->existeCodigo($codigo, null)) {
            $this->responderError('Ya existe un correo configurado con el código "' . $codigo . '".', $esAjax);
            return;
        }

        try {
            $this->model->crear($codigo, $nombre, $email, $nombreRemitente, $hostSmtp, $puertoSmtp, $usuarioSmtp, $passwordSmtp, $encryption, $status);
            $this->responderOk('Configuración de correo creada correctamente.', $esAjax);
        } catch (\Throwable $e) {
            $this->responderError('Error al crear: ' . $e->getMessage(), $esAjax);
        }
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $id = (int) ($_POST['id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nombreRemitente = trim($_POST['nombre_remitente'] ?? '');
        $hostSmtp = trim($_POST['host_smtp'] ?? 'smtp.gmail.com');
        $puertoSmtp = (int) ($_POST['puerto_smtp'] ?? 587);
        $usuarioSmtp = trim($_POST['usuario_smtp'] ?? '');
        $passwordSmtp = trim($_POST['password_smtp'] ?? '');
        $encryption = trim($_POST['encryption'] ?? 'tls');
        $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;

        if ($id <= 0) {
            $this->responderError('ID inválido.', $esAjax);
            return;
        }

        $err = $this->validar($codigo, $nombre, $email, $hostSmtp, $puertoSmtp, false, null);
        if ($err !== '') {
            $this->responderError($err, $esAjax);
            return;
        }

        if ($this->model->existeCodigo($codigo, $id)) {
            $this->responderError('Ya existe otro correo con el código "' . $codigo . '".', $esAjax);
            return;
        }

        // Si la contraseña está vacía, mantener la actual (null)
        $passToUpdate = $passwordSmtp === '' ? null : $passwordSmtp;

        try {
            if ($this->model->actualizar($id, $codigo, $nombre, $email, $nombreRemitente, $hostSmtp, $puertoSmtp, $usuarioSmtp, $passToUpdate, $encryption, $status)) {
                $this->responderOk('Configuración de correo actualizada correctamente.', $esAjax);
            } else {
                $this->responderError('Error al actualizar.', $esAjax);
            }
        } catch (\Throwable $e) {
            $this->responderError('Error: ' . $e->getMessage(), $esAjax);
        }
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['correos_config_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id)) {
            $_SESSION['correos_config_msg'] = ['success', 'Configuración eliminada correctamente.'];
        } else {
            $_SESSION['correos_config_msg'] = ['danger', 'Error al eliminar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function validar(
        string $codigo,
        string $nombre,
        string $email,
        string $hostSmtp,
        int $puertoSmtp,
        bool $esCrear,
        ?string $passwordSmtp
    ): string {
        if ($codigo === '') {
            return 'El código es obligatorio. Use solo letras, números y guiones bajos (ej: recuperar_password).';
        }
        if (!preg_match('/^[a-z0-9_]+$/', $codigo)) {
            return 'El código debe contener solo letras minúsculas, números y guiones bajos.';
        }
        if ($nombre === '') {
            return 'El nombre es obligatorio.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'El correo electrónico no es válido.';
        }
        if ($hostSmtp === '') {
            return 'El host SMTP es obligatorio.';
        }
        if ($puertoSmtp < 1 || $puertoSmtp > 65535) {
            return 'El puerto SMTP debe estar entre 1 y 65535.';
        }
        if ($esCrear && $passwordSmtp !== null && $passwordSmtp === '') {
            return 'La contraseña SMTP es obligatoria al crear.';
        }
        return '';
    }

    private function responderError(string $msg, bool $esAjax): void
    {
        if ($esAjax) {
            $this->json(['ok' => false, 'error' => $msg]);
        } else {
            $_SESSION['correos_config_msg'] = ['danger', $msg];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
    }

    private function responderOk(string $msg, bool $esAjax): void
    {
        if ($esAjax) {
            $this->json(['ok' => true, 'msg' => $msg]);
        } else {
            $_SESSION['correos_config_msg'] = ['success', $msg];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['correos_config_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
