<?php
/**
 * Controlador VideosAyuda - Módulo GLOBAL de videos de ayuda del sistema.
 *
 * Rutas (el Router mapea kebab-case → camelCase):
 *   GET  /videos-ayuda            index()   Visor (ventana aparte) — cualquier usuario
 *   GET  /videos-ayuda/lista      lista()   JSON de videos visibles — cualquier usuario
 *   GET  /videos-ayuda/stream?id= stream()  Reproducción con soporte HTTP Range — cualquier usuario
 *   GET  /videos-ayuda/gestion    gestion() Pantalla de administración — SOLO nivel 3
 *   POST /videos-ayuda/store      store()   Subir video   — SOLO nivel 3
 *   POST /videos-ayuda/update     update()  Editar video  — SOLO nivel 3
 *   POST /videos-ayuda/delete     delete()  Eliminar (lógico) — SOLO nivel 3
 *
 * Es un catálogo global: la tabla videos_ayuda NO lleva id_empresa.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\VideoAyuda;
use App\Services\VideoAyudaService;

class VideosAyudaController extends Controller
{
    private const STORAGE_DIR = 'storage/videos_ayuda';

    private VideoAyuda $model;
    private VideoAyudaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->model   = new VideoAyuda();
        $this->service = new VideoAyudaService();
    }

    // ────────────────────────────────────────────────────────────────────
    //  Visor (cualquier usuario autenticado) — se abre en ventana aparte
    // ────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $this->view('videosAyuda.visor', [
            'titulo'      => 'Videos de ayuda',
            'esSuperadmin' => $this->esSuperadmin(),
        ]);
    }

    /** JSON con los videos visibles (activos, no eliminados). */
    public function lista(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $buscar = trim($_GET['b'] ?? '');
        $idUsuario = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $rows = $this->model->getVisibles($buscar, $idUsuario);
        $base = rtrim(BASE_URL ?? '', '/');
        $videos = array_map(static function (array $r) use ($base): array {
            return [
                'id'          => (int) $r['id'],
                'titulo'      => (string) $r['titulo'],
                'descripcion' => (string) ($r['descripcion'] ?? ''),
                'categoria'   => (string) ($r['categoria'] ?? ''),
                'etiquetas'   => (string) ($r['etiquetas'] ?? ''),
                'likes'       => (int) ($r['likes'] ?? 0),
                'liked'       => filter_var($r['liked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'src'         => $base . '/videos-ayuda/stream?id=' . (int) $r['id'],
            ];
        }, $rows);
        $this->json(['ok' => true, 'videos' => $videos]);
    }

    /**
     * Transmite el archivo de video con soporte de HTTP Range (permite buscar/
     * adelantar sin cargar el archivo completo en memoria).
     */
    public function stream(): void
    {
        $this->requireAuth();

        // Liberar el bloqueo de la sesión de PHP ANTES de transmitir: si no, la
        // sesión queda bloqueada durante todo el streaming del video y cualquier
        // otra petición del mismo usuario (like, "Administrar", navegación) se
        // queda esperando hasta que termine el video.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $id = (int) ($_GET['id'] ?? 0);
        $video = $id > 0 ? $this->model->find($id) : null;
        if ($video === null || empty($video['archivo'])) {
            http_response_code(404);
            exit;
        }

        // basename: el nombre viene de BD, pero blindamos contra path traversal.
        $ruta = MVC_ROOT . '/' . self::STORAGE_DIR . '/' . basename((string) $video['archivo']);
        if (!is_file($ruta)) {
            http_response_code(404);
            exit;
        }

        $this->enviarConRange($ruta, (string) ($video['mime_type'] ?? 'video/mp4'));
    }

    /**
     * Registra una vista del video (se invoca desde el visor al iniciar la
     * reproducción). Cualquier usuario autenticado. Silencioso ante errores.
     */
    public function registrarVista(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }

        $idUsuario = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $idEmpresa = isset($_SESSION['id_empresa']) && (int) $_SESSION['id_empresa'] > 0
            ? (int) $_SESSION['id_empresa']
            : null;
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $ok = $this->service->registrarVista($id, $idUsuario, $idEmpresa, $ip, $ua);
        $this->json(['ok' => $ok]);
    }

    /**
     * Da/quita "me gusta" al video (toggle) para el usuario actual.
     * Cualquier usuario autenticado.
     */
    public function toggleLike(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        try {
            $res = $this->service->toggleLike($id, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'liked' => $res['liked'], 'likes' => $res['likes']]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Detalle de quién ha visto un video (agrupado por usuario). Solo superadmin.
     */
    public function vistasDetalle(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        $rows = $this->model->getVistasDetalle($id);
        $vistas = array_map(static function (array $r): array {
            $ultima = $r['ultima'] ?? null;
            $ts = $ultima ? strtotime((string) $ultima) : false;
            return [
                'usuario'        => (string) ($r['usuario'] ?? ''),
                'reproducciones' => (int) ($r['reproducciones'] ?? 0),
                'ultima'         => $ts ? date('d-m-Y H:i:s', $ts) : '',
            ];
        }, $rows);
        $this->json(['ok' => true, 'vistas' => $vistas]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Gestión (SOLO superadministrador, nivel 3)
    // ────────────────────────────────────────────────────────────────────

    public function gestion(): void
    {
        $this->requireAuth();
        $this->requireSuperadmin();

        $ordenCol = trim($_GET['sort'] ?? 'orden');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar   = trim($_GET['b'] ?? '');
        if (!in_array($ordenCol, VideoAyuda::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'orden';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->view('videosAyuda.gestion', [
            'titulo'   => 'Gestión de videos de ayuda',
            'rows'     => $rows,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar'   => $buscar,
        ]);
    }

    public function store(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }
        if ($this->postExcedido()) {
            $this->json(['ok' => false, 'error' => $this->mensajePostExcedido()]);
        }

        try {
            $id = $this->service->crear(
                $this->metaDesdePost(),
                $_FILES['archivo'] ?? [],
                (int) $_SESSION['id_usuario']
            );
            $this->json(['ok' => true, 'msg' => 'Video subido correctamente.', 'id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function update(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }
        if ($this->postExcedido()) {
            $this->json(['ok' => false, 'error' => $this->mensajePostExcedido()]);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }

        // El archivo es opcional en la edición (null = conservar el actual).
        $file = isset($_FILES['archivo']) && !empty($_FILES['archivo']['name'])
            ? $_FILES['archivo']
            : null;

        try {
            $this->service->actualizar($id, $this->metaDesdePost(), $file, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Video actualizado correctamente.']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }

        try {
            $this->service->eliminar($id, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Video eliminado correctamente.']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function metaDesdePost(): array
    {
        return [
            'titulo'      => $_POST['titulo'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'categoria'   => $_POST['categoria'] ?? '',
            'etiquetas'   => $_POST['etiquetas'] ?? '',
            'orden'       => (int) ($_POST['orden'] ?? 0),
            'estado'      => $_POST['estado'] ?? 'activo',
        ];
    }

    private function esSuperadmin(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 0) >= 3;
    }

    /**
     * Deja la salida lista para responder JSON limpio: descarta cualquier warning
     * de PHP que pudiera estar en el búfer (p. ej. "POST Content-Length exceeds")
     * y evita que nuevos warnings contaminen la respuesta.
     */
    private function prepararJson(): void
    {
        if (ob_get_level() > 0) {
            @ob_clean();
        }
        // Los errores siguen registrándose en el log; solo no se imprimen (romperían el JSON).
        ini_set('display_errors', '0');
    }

    /**
     * Detecta cuando el cuerpo POST superó post_max_size: el navegador envió datos
     * (CONTENT_LENGTH) pero PHP descartó $_POST y $_FILES.
     */
    private function postExcedido(): bool
    {
        $cl = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && $cl > 0
            && empty($_POST)
            && empty($_FILES);
    }

    private function mensajePostExcedido(): string
    {
        return 'El video supera el tamaño máximo que acepta el servidor (post_max_size = '
            . ini_get('post_max_size') . ', upload_max_filesize = ' . ini_get('upload_max_filesize')
            . '). Suba un archivo más pequeño o aumente estos límites en php.ini y reinicie Apache.';
    }

    private function requireSuperadmin(): void
    {
        if (!$this->esSuperadmin()) {
            $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Solo el superadministrador puede gestionar los videos de ayuda.'], 403);
            }
            http_response_code(403);
            echo 'Acceso restringido al superadministrador.';
            exit;
        }
    }

    /**
     * Envía un archivo soportando la cabecera Range (206 Partial Content).
     * No usa readfile() completo: transmite por bloques para no agotar la RAM.
     */
    private function enviarConRange(string $ruta, string $mime): void
    {
        // Descartar cualquier buffer de salida previo (el visor no debe recibir HTML).
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $tam = filesize($ruta);
        if ($tam === false) {
            http_response_code(500);
            exit;
        }

        $fp = fopen($ruta, 'rb');
        if ($fp === false) {
            http_response_code(500);
            exit;
        }

        $inicio = 0;
        $fin    = $tam - 1;
        $status = 200;

        $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';
        if ($rangeHeader !== '' && preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $m)) {
            if ($m[1] !== '') {
                $inicio = (int) $m[1];
            }
            if ($m[2] !== '') {
                $fin = (int) $m[2];
            } elseif ($m[1] === '') {
                // bytes=-N → últimos N bytes
                $inicio = max(0, $tam - (int) ($m[2] === '' ? 0 : $m[2]));
            }
            if ($inicio > $fin || $inicio >= $tam) {
                header('Content-Range: bytes */' . $tam);
                http_response_code(416); // Range Not Satisfiable
                fclose($fp);
                exit;
            }
            $fin = min($fin, $tam - 1);
            $status = 206;
        }

        $longitud = $fin - $inicio + 1;

        http_response_code($status);
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $longitud);
        if ($status === 206) {
            header('Content-Range: bytes ' . $inicio . '-' . $fin . '/' . $tam);
        }
        header('Cache-Control: private, max-age=3600');

        if ($inicio > 0) {
            fseek($fp, $inicio);
        }

        $buffer    = 8192;
        $restante  = $longitud;
        while ($restante > 0 && !feof($fp)) {
            $leer = ($restante > $buffer) ? $buffer : $restante;
            $datos = fread($fp, (int) $leer);
            if ($datos === false) {
                break;
            }
            echo $datos;
            flush();
            $restante -= strlen($datos);
        }
        fclose($fp);
        exit;
    }
}
