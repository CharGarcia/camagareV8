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
            'rowsHtml' => $this->renderFilasHtml($rows),
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
            'codigosSugeridos' => CorreoConfig::CODIGOS_SUGERIDOS,
        ]);
    }

    /**
     * AJAX: listado de configuraciones de correo (tabla), para búsqueda y
     * ordenamiento en tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function searchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'codigo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        if (!in_array($ordenCol, CorreoConfig::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        echo json_encode([
            'ok' => true,
            'rows' => $this->renderFilasHtml($rows),
        ]);
        exit;
    }

    /**
     * Renderiza el <tbody> completo (filas o mensaje de "sin resultados").
     * Usado tanto por la carga inicial (vista) como por searchAjax.
     */
    private function renderFilasHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-envelope-at fs-3 d-block mb-2"></i>No hay configuraciones de correo. Cree una para recuperar contraseña, notificaciones, cobros, etc.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? $r['id_correo_config'] ?? 0);
            $status = (int) ($r['status'] ?? 1);
            $html .= '<tr class="correo-row" role="button" tabindex="0" data-id="' . $id . '"'
                . ' data-codigo="' . htmlspecialchars($r['codigo'] ?? '') . '"'
                . ' data-nombre="' . htmlspecialchars($r['nombre'] ?? '') . '"'
                . ' data-email="' . htmlspecialchars($r['email'] ?? '') . '"'
                . ' data-nombre-remitente="' . htmlspecialchars($r['nombre_remitente'] ?? '') . '"'
                . ' data-host-smtp="' . htmlspecialchars($r['host_smtp'] ?? '') . '"'
                . ' data-puerto-smtp="' . htmlspecialchars((string) ($r['puerto_smtp'] ?? '587')) . '"'
                . ' data-usuario-smtp="' . htmlspecialchars($r['usuario_smtp'] ?? '') . '"'
                . ' data-encryption="' . htmlspecialchars($r['encryption'] ?? 'tls') . '"'
                . ' data-status="' . $status . '">';
            $html .= '<td><code>' . htmlspecialchars($r['codigo'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($r['nombre'] ?? '') . '</td>';
            $html .= '<td><small>' . htmlspecialchars($r['email'] ?? '') . '</small></td>';
            $html .= '<td><small>' . (($r['host_smtp'] ?? '') !== '' ? htmlspecialchars($r['host_smtp']) : '-') . '</small></td>';
            $html .= '<td class="text-center">' . ((int) ($r['puerto_smtp'] ?? 0) ?: '-') . '</td>';
            $html .= '<td><span class="badge bg-secondary">' . (($r['encryption'] ?? '') !== '' ? htmlspecialchars($r['encryption']) : 'none') . '</span></td>';
            $html .= '<td class="text-center">' . ($status ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
            $html .= '<td class="text-end">'
                . '<form method="POST" action="' . BASE_URL . '/config/correosConfigDelete" class="d-inline" onsubmit="return confirm(&quot;¿Eliminar esta configuración de correo?&quot;);" onclick="event.stopPropagation();">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Eliminar"><i class="bi bi-trash"></i></button>'
                . '</form></td>';
            $html .= '</tr>';
        }
        return $html;
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
