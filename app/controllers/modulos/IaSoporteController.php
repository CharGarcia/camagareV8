<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\models\IaAgente;
use App\repositories\modulos\IaConfigRepository;
use App\repositories\modulos\IaConversacionRepository;
use App\repositories\modulos\IaDocumentoRepository;
use App\repositories\modulos\IaMensajeRepository;
use App\Rules\modulos\IaSoporteRules;
use App\Services\LogSistemaService;
use App\Services\modulos\IaSoporteService;

/**
 * IA Soporte — asistente legal/tributario/contable con IA (BYOK).
 * Cada empresa configura su propio proveedor/API key; el chat responde con
 * base en los PDFs que la empresa haya cargado, usando un "agente" (prompt
 * preconfigurado) del catálogo global ia_agentes.
 */
class IaSoporteController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/ia-soporte';

    private IaSoporteService $service;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new IaSoporteService(
            new IaConfigRepository(),
            new IaDocumentoRepository(),
            new IaConversacionRepository(),
            new IaMensajeRepository(),
            new IaSoporteRules(),
            new LogSistemaService(),
        );
    }

    // ── Vista principal ───────────────────────────────────────────────────────

    /**
     * Página STANDALONE (se abre en ventana aparte desde el ícono del navbar,
     * igual que Videos de Ayuda): no usa el layout principal, no muestra el
     * navbar/menú del sistema — solo el contenido del asistente de IA.
     */
    public function index(): void
    {
        $this->requireLeer();

        $this->view('modulos.ia_soporte.index', [
            'titulo'       => 'IA Soporte',
            'perm'         => $this->getPermisos(),
            'rutaModulo'   => self::RUTA_MODULO,
            'base'         => BASE_URL,
            'esSuperadmin' => (int) ($_SESSION['nivel'] ?? 0) >= 3,
        ]);
    }

    // ── Configuración BYOK ───────────────────────────────────────────────────

    public function configGet(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->service->getConfigEstado($idEmpresa)]);
        exit;
    }

    public function configStore(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->guardarConfig($idEmpresa, [
                'proveedor'   => trim((string) ($_POST['proveedor'] ?? 'openai')),
                'modelo_chat' => trim((string) ($_POST['modelo_chat'] ?? '')),
                'api_key'     => (string) ($_POST['api_key'] ?? ''),
            ], $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Configuración guardada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Catálogo global de agentes (solo lectura aquí) ──────────────────────

    public function agentesListar(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $st = $this->db->prepare(
            "SELECT id, nombre, descripcion, icono FROM ia_agentes
             WHERE eliminado = false AND activo = true ORDER BY orden ASC"
        );
        $st->execute();
        echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── Documentos ───────────────────────────────────────────────────────────

    public function documentosListar(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuarioFiltro = empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $rows = $this->service->listarDocumentos($idEmpresa, $idUsuarioFiltro);
        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) {
                $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            }
        }
        unset($r);

        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    public function documentoSubir(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->subirDocumento(
                $idEmpresa,
                [
                    'titulo'    => trim((string) ($_POST['titulo'] ?? '')),
                    'categoria' => trim((string) ($_POST['categoria'] ?? '')),
                ],
                $_FILES['archivo'] ?? [],
                $this->idsAgentesDesdePost(),
                $idUsuario
            );

            $lanzado = $this->lanzarWorker($id);

            echo json_encode([
                'ok'      => true,
                'id'      => $id,
                'lanzado' => $lanzado,
                'msg'     => $lanzado
                    ? 'Documento subido. Procesando en segundo plano…'
                    : 'Documento subido, pero no se pudo iniciar el procesamiento automático. '
                      . 'Ejecute: php scripts/procesar_documento_ia.php --documento=' . $id,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function documentoReintentar(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID de documento no válido.']);
            exit;
        }

        $lanzado = $this->lanzarWorker($id);
        echo json_encode([
            'ok'      => true,
            'lanzado' => $lanzado,
            'msg'     => $lanzado ? 'Reprocesando en segundo plano…' : 'No se pudo relanzar el procesamiento.',
        ]);
        exit;
    }

    public function documentoEliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id'] ?? 0);

        try {
            if ($id <= 0) {
                throw new \Exception('ID de documento no válido.');
            }
            $this->service->eliminarDocumento($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Documento eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function documentoAgentesActualizar(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id'] ?? 0);

        try {
            if ($id <= 0) {
                throw new \Exception('ID de documento no válido.');
            }
            $this->service->actualizarAgentesDocumento($id, $idEmpresa, $this->idsAgentesDesdePost(), $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Agentes actualizados correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** @return int[] */
    private function idsAgentesDesdePost(): array
    {
        $raw = $_POST['id_agentes'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $raw), fn (int $v) => $v > 0));
    }

    // ── Conversaciones ───────────────────────────────────────────────────────

    public function conversacionesListar(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuarioFiltro = empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;

        echo json_encode(['ok' => true, 'data' => $this->service->listarConversaciones($idEmpresa, $idUsuarioFiltro)]);
        exit;
    }

    public function conversacionCrear(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idAgente = (int) ($_POST['id_agente'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));

        try {
            if ($idAgente <= 0) {
                throw new \Exception('Debe seleccionar un agente.');
            }
            $id = $this->service->crearConversacion($idEmpresa, $idAgente, $titulo, $idUsuario);
            echo json_encode(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function conversacionEliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id'] ?? 0);

        try {
            if ($id <= 0) {
                throw new \Exception('ID de conversación no válido.');
            }
            $this->service->eliminarConversacion($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Conversación eliminada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Mensajes / chat ──────────────────────────────────────────────────────

    public function mensajesListar(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idConversacion = (int) ($_GET['id_conversacion'] ?? 0);

        try {
            $mensajes = $this->service->listarMensajes($idConversacion, $idEmpresa);
            foreach ($mensajes as &$m) {
                $m['created_at'] = !empty($m['created_at']) ? date('d-m-Y H:i:s', strtotime($m['created_at'])) : null;
            }
            unset($m);
            echo json_encode(['ok' => true, 'data' => $mensajes]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function mensajeEnviar(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idConversacion = (int) ($_POST['id_conversacion'] ?? 0);
        $pregunta = (string) ($_POST['pregunta'] ?? '');

        try {
            if ($idConversacion <= 0) {
                throw new \Exception('Conversación no válida.');
            }
            $resultado = $this->service->responder($idConversacion, $idEmpresa, $idUsuario, $pregunta);
            echo json_encode(['ok' => true, 'data' => $resultado], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Gestión de prompts (catálogo global de agentes) — solo superadmin ──

    public function promptsListar(): void
    {
        $this->requireLeer();
        $this->requireSuperadminAjax();
        header('Content-Type: application/json');

        $model = new IaAgente();
        $rows = $model->getAll('orden', 'ASC', '');
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function promptStore(): void
    {
        $this->requireLeer();
        $this->requireSuperadminAjax();
        header('Content-Type: application/json');

        $data = $this->recogerDatosPrompt();
        if ($data['nombre'] === '' || $data['prompt_sistema'] === '') {
            echo json_encode(['ok' => false, 'error' => 'El nombre y el prompt del sistema son obligatorios.']);
            exit;
        }

        try {
            $data['created_by'] = (int) $_SESSION['id_usuario'];
            $model = new IaAgente();
            $id = $model->crear($data);
            (new LogSistemaService())->registrar((int) $_SESSION['id_usuario'], null, 'crear', 'ia_agentes', $id, null, $data);
            echo json_encode(['ok' => true, 'id' => $id, 'msg' => 'Prompt creado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function promptUpdate(): void
    {
        $this->requireLeer();
        $this->requireSuperadminAjax();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->recogerDatosPrompt();
        if ($id <= 0 || $data['nombre'] === '' || $data['prompt_sistema'] === '') {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos.']);
            exit;
        }

        try {
            $model = new IaAgente();
            $antes = $model->find($id);
            $data['updated_by'] = (int) $_SESSION['id_usuario'];
            if (!$model->actualizar($id, $data)) {
                throw new \Exception('No se pudo actualizar el prompt.');
            }
            (new LogSistemaService())->registrar((int) $_SESSION['id_usuario'], null, 'actualizar', 'ia_agentes', $id, $antes, $data);
            echo json_encode(['ok' => true, 'msg' => 'Prompt actualizado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function promptEliminar(): void
    {
        $this->requireLeer();
        $this->requireSuperadminAjax();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID de prompt no válido.']);
            exit;
        }

        try {
            $model = new IaAgente();
            $antes = $model->find($id);
            if (!$antes || !$model->eliminarLogico($id, (int) $_SESSION['id_usuario'])) {
                throw new \Exception('No se pudo eliminar el prompt.');
            }
            (new LogSistemaService())->registrar((int) $_SESSION['id_usuario'], null, 'eliminar', 'ia_agentes', $id, $antes, null);
            echo json_encode(['ok' => true, 'msg' => 'Prompt eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosPrompt(): array
    {
        return [
            'nombre'         => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion'    => trim((string) ($_POST['descripcion'] ?? '')) ?: null,
            'icono'          => trim((string) ($_POST['icono'] ?? '')) ?: 'bi-robot',
            'prompt_sistema' => trim((string) ($_POST['prompt_sistema'] ?? '')),
            'orden'          => (int) ($_POST['orden'] ?? 0),
            'activo'         => !empty($_POST['activo']),
        ];
    }

    /**
     * El catálogo de agentes es global (no depende de id_empresa ni de los
     * permisos del submódulo): solo el superadministrador (nivel 3) lo edita.
     */
    private function requireSuperadminAjax(): void
    {
        if ((int) ($_SESSION['nivel'] ?? 0) < 3) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Solo el superadministrador puede gestionar los prompts.']);
            exit;
        }
    }

    // ── Helpers internos ─────────────────────────────────────────────────────

    /**
     * Lanza el worker CLI de indexado de PDF desligado del request (no bloquea).
     * Windows: start /B ; Linux/Unix: nohup ... &
     * (Mismo patrón que EnvioLoteSriController::lanzarWorker()).
     */
    private function lanzarWorker(int $idDocumento): bool
    {
        $phpBin = $this->resolverPhpBin();
        $script = MVC_ROOT . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'procesar_documento_ia.php';

        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                $cmd = 'start /B "" ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script) . ' --documento=' . $idDocumento;
                $handle = popen($cmd, 'r');
                if ($handle === false) {
                    return false;
                }
                pclose($handle);
                return true;
            }

            $cmd = 'nohup ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script)
                 . ' --documento=' . $idDocumento . ' > /dev/null 2>&1 &';
            @exec($cmd);
            return true;
        } catch (\Throwable $e) {
            error_log('[IaSoporte] No se pudo lanzar el worker del documento ' . $idDocumento . ': ' . $e->getMessage());
            return false;
        }
    }

    private function resolverPhpBin(): string
    {
        $cfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $bin = trim((string) ($cfg['sri_lote_php_bin'] ?? ''));
        if ($bin !== '') {
            return $bin;
        }
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            foreach (['C:\\xampp\\php\\php.exe'] as $cand) {
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
        return 'php';
    }
}
