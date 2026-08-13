<?php
/**
 * Controlador UsuariosSistema - Gestión de usuarios del sistema
 * Tabla usuarios. Muestra datos del usuario y empresas asignadas.
 * Usa el modal de crear usuario existente (nombre + correo).
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Usuario;

class UsuariosSistemaController extends Controller
{
    private Usuario $model;
    private const BASE_PATH = '/config/usuarios-sistema';

    public function __construct()
    {
        parent::__construct();
        $this->model = new Usuario();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));
        $perPage = 20;

        if (!in_array($ordenCol, Usuario::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $result = $this->model->getTodosParaListado($idActual, $nivel, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $msg = $_SESSION['usuarios_msg'] ?? null;
        unset($_SESSION['usuarios_msg']);

        // Límite de usuarios para admins
        $limiteUsuarios = null;
        $idEmpresaActual = (int) ($_SESSION['id_empresa'] ?? 0);
        $modelAsignada = new \App\models\EmpresaAsignada();
        if ($idEmpresaActual > 0) {
            $limiteUsuarios = $modelAsignada->getLimiteUsuariosEmpresa($idEmpresaActual);
        }

        // Empresas candidatas para asignar al crear un usuario nuevo: superadmin ve
        // todas las empresas activas, admin solo las que él mismo tiene asignadas.
        $empresasParaCrear = $nivel >= 3
            ? $modelAsignada->getTodasEmpresasParaSelect()
            : $modelAsignada->getEmpresasDeUsuario($idActual);

        $this->viewWithLayout('layouts.main', 'usuariosSistema.index', [
            'titulo' => 'Usuarios del sistema',
            'rows' => $rows,
            'rowsHtml' => $this->renderFilasHtml($rows),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'buscar' => $buscar,
            'nivel' => $nivel,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'msg' => $msg,
            'limiteUsuarios' => $limiteUsuarios,
            'empresasParaCrear' => $empresasParaCrear,
            'idEmpresaActual' => $idEmpresaActual,
        ]);
    }

    /**
     * AJAX: listado de usuarios (tabla + paginación), para búsqueda y ordenamiento
     * en tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax / AsignarEmpresasController::searchAjax.
     */
    public function searchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $perPage = 20;

        if (!in_array($ordenCol, Usuario::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $result = $this->model->getTodosParaListado($idActual, $nivel, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = $total > 0 ? min($page * $perPage, $total) : 0;

        $rowsHtml = $this->renderFilasHtml($rows);

        ob_start();
        if ($totalPages > 1) {
            $prevDisabled = ($page <= 1) ? 'disabled' : '';
            $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
            echo '<button type="button" class="btn btn-sm btn-outline-secondary" ' . $prevDisabled . ' onclick="USRSIS_cambiarPagina(' . ($page - 1) . ')" aria-label="Anterior"><i class="fas fa-angle-left"></i></button>'
               . '<button type="button" class="btn btn-sm btn-outline-secondary" ' . $nextDisabled . ' onclick="USRSIS_cambiarPagina(' . ($page + 1) . ')" aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>';
        }
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok' => true,
            'rows' => $rowsHtml,
            'pagination' => $paginationHtml,
            'info' => "$from-$to/$total",
            'totalPages' => $totalPages,
        ]);
        exit;
    }

    /**
     * Renderiza el <tbody> completo (filas o mensaje de "sin resultados").
     * Usado tanto por la carga inicial (vista) como por searchAjax, para no
     * duplicar el marcado.
     */
    private function renderFilasHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-people fs-3 d-block mb-2"></i>No hay usuarios registrados.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $html .= $this->renderFilaUsuario($r);
        }
        return $html;
    }

    /**
     * Renderiza una fila <tr> de la tabla de usuarios.
     */
    private function renderFilaUsuario(array $r): string
    {
        $nivelU = (int) ($r['nivel'] ?? 1);
        $estado = (int) ($r['estado'] ?? 1);
        $empresas = $r['empresas'] ?? [];
        $rv = $r['registrado'] ?? false;
        $registrado = ($rv === true || $rv === 't' || $rv === '1' || $rv === 1 || $rv === 'true');
        $mv = $r['puede_app_movil'] ?? false;
        $puedeAppMovil = ($mv === true || $mv === 't' || $mv === '1' || $mv === 1 || $mv === 'true');
        $nivelTexto = $nivelU >= 3 ? 'Super Admin' : ($nivelU >= 2 ? 'Administrador' : 'Usuario');
        $nivelClase = $nivelU >= 3 ? 'danger' : ($nivelU >= 2 ? 'info' : 'secondary');

        $html = '<tr class="usuario-row" role="button" tabindex="0"'
            . ' data-id="' . (int) ($r['id'] ?? 0) . '"'
            . ' data-nombre="' . htmlspecialchars($r['nombre'] ?? '') . '"'
            . ' data-cedula="' . htmlspecialchars($r['cedula'] ?? '') . '"'
            . ' data-mail="' . htmlspecialchars($r['mail'] ?? '') . '"'
            . ' data-nivel="' . $nivelU . '"'
            . ' data-estado="' . $estado . '"'
            . ' data-empresas="' . count($empresas) . '"'
            . ' data-puede-app-movil="' . ($puedeAppMovil ? '1' : '0') . '"'
            . ' data-token="' . htmlspecialchars($r['token'] ?? '') . '">';
        $html .= '<td>' . htmlspecialchars($r['nombre'] ?? '') . '</td>';
        $html .= '<td><code>' . htmlspecialchars($r['cedula'] ?? '') . '</code></td>';
        $html .= '<td>' . htmlspecialchars($r['mail'] ?? '-') . '</td>';
        $html .= '<td><span class="badge bg-' . $nivelClase . '">' . $nivelTexto . '</span></td>';
        $html .= '<td>';
        if (!$registrado) {
            $html .= '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning" title="El usuario aún no ha completado su registro">'
                . '<i class="bi bi-hourglass-split"></i> Pendiente registro</span>'
                . '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 ms-1"'
                . ' title="Reenviar correo de invitación a ' . htmlspecialchars($r['mail'] ?? '') . '"'
                . ' onclick="event.stopPropagation(); reenviarInvitacionUsuario(' . (int) ($r['id'] ?? 0) . ', this);">'
                . '<i class="bi bi-send"></i></button>';
        } elseif ($estado) {
            $html .= '<span class="badge bg-success">Activo</span>';
        } else {
            $html .= '<span class="badge bg-secondary">Inactivo</span>';
        }
        $html .= '</td>';
        $html .= '<td class="text-center">';
        if ($puedeAppMovil) {
            $html .= '<span class="badge bg-success bg-opacity-10 text-success border border-success" title="Puede iniciar sesión en la app móvil"><i class="bi bi-phone"></i> Sí</span>';
        } else {
            $html .= '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">No</span>';
        }
        $html .= '</td>';
        $html .= '<td class="text-center"><span class="badge bg-light text-dark">' . count($empresas) . '</span></td>';
        $html .= '</tr>';

        return $html;
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $mail = trim($_POST['mail'] ?? '');
        $nivel = (int) ($_POST['nivel'] ?? 1);
        $estado = !empty($_POST['estado']);

        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $nivelActual = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivelActual < 3 && $nivel > 1) {
            $this->json(['ok' => false, 'msg' => 'Solo el super administrador puede asignar nivel de administrador.']);
        }

        // El checkbox "Puede usar app móvil" solo se renderiza en el modal cuando
        // quien edita es nivel 3, así que un POST de un admin nivel < 3 nunca lo trae
        // — null le indica al modelo que no toque esa columna (preserva el valor actual).
        $puedeAppMovil = $nivelActual >= 3 ? !empty($_POST['puede_app_movil']) : null;

        try {
            if ($this->model->actualizar($id, $mail, $nivel, $estado ? 1 : 0, $puedeAppMovil)) {
                $this->json(['ok' => true, 'msg' => 'Usuario actualizado correctamente.']);
            } else {
                $this->json(['ok' => false, 'msg' => 'No se realizaron cambios o hubo un error al actualizar.']);
            }
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID de usuario no válido.']);
        }

        if ($id === $idActual) {
            $this->json(['ok' => false, 'msg' => 'No puede eliminarse a sí mismo.']);
        }

        try {
            if ($this->model->eliminar($id, $idActual)) {
                $this->json(['ok' => true, 'msg' => 'Usuario eliminado correctamente.']);
            } else {
                $this->json(['ok' => false, 'msg' => 'No se pudo eliminar el usuario.']);
            }
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'msg' => 'Error inesperado: ' . $e->getMessage()]);
        }
    }

    public function reenviarInvitacion(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'msg' => 'Método no permitido']);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $row = $this->model->getDatosInvitacion($id);

        if (!$row) {
            $this->json(['ok' => false, 'msg' => 'Usuario no encontrado.']);
        }

        // Guía por el estado real de registro, no por el token (el token también
        // se usa en recuperación de contraseña de usuarios ya registrados).
        $rv = $row['registrado'] ?? false;
        $registrado = ($rv === true || $rv === 't' || $rv === '1' || $rv === 1 || $rv === 'true');
        if ($registrado) {
            $this->json(['ok' => false, 'msg' => 'El usuario ya completó su registro.']);
        }

        // Usuario pendiente: reutiliza su token de invitación o regenera uno si falta.
        $token = trim((string) ($row['token'] ?? ''));
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $this->model->actualizarToken($id, $token);
        }

        $nombre = $row['nombre'];
        $correo = $row['mail'];

        try {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $urlEmail = urlencode($correo);
            $urlInvite = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/registro/index/' . $urlEmail . '/' . $token;

            require_once MVC_APP . '/helpers/mail.php';
            if (enviar_correo_nuevo_usuario($nombre, $correo, $urlInvite)) {
                $this->json(['ok' => true, 'msg' => 'Invitación reenviada correctamente a ' . $correo]);
            } else {
                $err = $GLOBALS['LAST_EMAIL_ERROR'] ?? 'Error al enviar el correo.';
                $this->json(['ok' => false, 'msg' => $err]);
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['usuarios_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
