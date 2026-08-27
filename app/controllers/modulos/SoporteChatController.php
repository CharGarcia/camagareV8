<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\SoporteChatRepository;
use App\Rules\modulos\SoporteChatRules;
use App\Services\LogSistemaService;
use App\Services\modulos\SoporteChatService;

/**
 * Chat de soporte del ERP.
 *
 * Dos públicos, dos niveles de exigencia:
 *
 *   - LA BURBUJA la usa cualquier usuario con sesión y empresa activa. Sus
 *     endpoints llaman a requireEmpresaSesion(), NO a requireLeer(): pedir
 *     permiso sobre el submódulo dejaría sin soporte justo a quien más lo
 *     necesita (un nivel 1 con dos módulos asignados).
 *
 *   - LA BANDEJA es el módulo propiamente dicho y sí exige permiso (requireAgente),
 *     y encima de eso el Service exige ser agente de soporte. Ese permiso se
 *     acepta desde CUALQUIER empresa del usuario, no solo la activa: la bandeja
 *     no es por empresa (ver SoporteChatService::esAgente()).
 *
 * Los endpoints compartidos (mensajes, enviar, pulso) solo piden sesión: quién
 * puede ver qué conversación lo decide el Service con la sesión en la mano,
 * nunca un parámetro del cliente.
 */
class SoporteChatController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/soporte-chat';

    private SoporteChatService $service;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new SoporteChatService(
            new SoporteChatRepository(),
            new SoporteChatRules(),
            new LogSistemaService(),
        );
    }

    // ── Vista de la bandeja (solo equipo de soporte) ─────────────────────────

    public function index(): void
    {
        $this->requireAgente('r');

        if (!$this->service->esAgente($this->idUsuario(), $this->idEmpresa(), $this->nivel())) {
            $this->redirect(rtrim(BASE_URL, '/') . '/home/index');
        }

        // El botón del copiloto solo se pinta si está habilitado en la config y
        // la empresa del agente tiene proveedor de IA: un botón que siempre
        // responde "no configurado" es peor que no tenerlo.
        $copiloto = false;
        try {
            $config = $this->service->getConfig();
            $copiloto = !empty($config['copiloto_activo'])
                && (new \App\Services\Ia\IaRagService())->estaConfigurado($this->idEmpresa());
        } catch (\Throwable $e) {
            $copiloto = false;
        }

        $this->viewWithLayout('layouts.main', 'modulos/soporte_chat/index', [
            'titulo'          => 'Chat de Soporte',
            'perm'            => $this->permisosAgente(),
            'rutaModulo'      => self::RUTA_MODULO,
            'fullWidth'       => true,
            'copilotoActivo'  => $copiloto,
        ]);
    }

    // ── Endpoints de la burbuja (cualquier usuario con sesión) ───────────────

    // La configuración del widget NO se sirve por AJAX: la resuelve el partial
    // al renderizar el layout (ver app/views/partials/soporte_widget.php), para
    // no gastar una petición por cada carga de página.

    public function misConversacionesAjax(): void
    {
        $this->requireEmpresaSesion();

        try {
            $datos = $this->service->listarMias($this->idUsuario(), $this->idEmpresa());
            $this->json(['ok' => true, 'data' => $datos]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function abrirAjax(): void
    {
        $this->requireEmpresaSesion();
        $input = $this->inputJson();

        try {
            $id = $this->service->abrirConversacion($this->idEmpresa(), $this->idUsuario(), [
                'mensaje'       => (string) ($input['mensaje'] ?? ''),
                'asunto'        => (string) ($input['asunto'] ?? ''),
                'origen_url'    => (string) ($input['origen_url'] ?? ''),
                'origen_modulo' => (string) ($input['origen_modulo'] ?? ''),
            ]);
            $this->json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ── Endpoints compartidos (el Service resuelve el acceso) ────────────────

    public function mensajesAjax(): void
    {
        $this->requireEmpresaSesion();

        $id      = (int) ($_GET['id'] ?? 0);
        $desdeId = (int) ($_GET['desde'] ?? 0);

        try {
            $mensajes = $this->service->listarMensajes($id, $this->idUsuario(), $this->idEmpresa(), $this->nivel(), $desdeId);
            $this->json(['ok' => true, 'data' => $mensajes]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function enviarAjax(): void
    {
        $this->requireEmpresaSesion();
        $input = $this->inputJson();

        $id        = (int) ($input['id'] ?? 0);
        $contenido = (string) ($input['contenido'] ?? '');

        try {
            $res = $this->service->enviarMensaje($id, $this->idUsuario(), $this->idEmpresa(), $this->nivel(), $contenido, [
                'sugerida_por_ia' => !empty($input['sugerida_por_ia']),
                // Desde dónde se escribe. Solo desempata cuando un agente
                // responde en su PROPIA conversación; el Service lo valida.
                'origen'          => (string) ($input['origen'] ?? ''),
            ]);
            $this->json(['ok' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Pulso del polling: devuelve solo el número de versión de la conversación.
     * Se sirve desde APCu, así que el ciclo normal no toca la base de datos.
     */
    public function pulsoAjax(): void
    {
        $this->requireEmpresaSesion();
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $this->json(['ok' => true, 'v' => $this->service->getVersion($id, $this->idUsuario(), $this->idEmpresa(), $this->nivel())]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function estadoAjax(): void
    {
        $this->requireEmpresaSesion();
        $input = $this->inputJson();

        try {
            $this->service->cambiarEstado(
                (int) ($input['id'] ?? 0),
                (string) ($input['estado'] ?? ''),
                $this->idUsuario(), $this->idEmpresa(), $this->nivel()
            );
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function calificarAjax(): void
    {
        $this->requireEmpresaSesion();
        $input = $this->inputJson();

        try {
            $comentario = trim((string) ($input['comentario'] ?? ''));
            $this->service->calificar(
                (int) ($input['id'] ?? 0),
                (int) ($input['calificacion'] ?? 0),
                $comentario !== '' ? $comentario : null,
                $this->idUsuario(), $this->idEmpresa(), $this->nivel()
            );
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ── Endpoints de la bandeja (agente) ─────────────────────────────────────

    public function bandejaAjax(): void
    {
        $this->requireAgente('r');

        try {
            $datos = $this->service->listarBandeja($this->idUsuario(), $this->idEmpresa(), $this->nivel(), [
                'estado'     => (string) ($_GET['estado'] ?? ''),
                'buscar'     => (string) ($_GET['buscar'] ?? ''),
                'archivadas' => !empty($_GET['archivadas']),
                'solo_mias'  => !empty($_GET['solo_mias']) ? $this->idUsuario() : 0,
            ]);
            $this->json(['ok' => true, 'data' => $datos]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function pulsoBandejaAjax(): void
    {
        $this->requireAgente('r');

        try {
            $this->json(['ok' => true, 'v' => $this->service->getVersionBandeja($this->idUsuario(), $this->idEmpresa(), $this->nivel())]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Copiloto: devuelve un borrador de respuesta. No guarda ni envía nada —
     * el agente lo recibe en su caja de texto y decide qué hacer con él.
     */
    public function sugerirAjax(): void
    {
        $this->requireAgente('r');
        $input = $this->inputJson();

        try {
            $res = $this->service->sugerirRespuesta(
                (int) ($input['id'] ?? 0),
                $this->idUsuario(), $this->idEmpresa(), $this->nivel()
            );
            $this->json(['ok' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function tomarAjax(): void
    {
        $this->requireAgente('u');
        $input = $this->inputJson();

        try {
            $this->service->tomarConversacion((int) ($input['id'] ?? 0), $this->idUsuario(), $this->idEmpresa(), $this->nivel());
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ── Respuestas rápidas (agente) ──────────────────────────────────────────

    public function respuestasRapidasAjax(): void
    {
        $this->requireAgente('r');

        try {
            $this->json(['ok' => true, 'data' => $this->service->listarRespuestasRapidas($this->idUsuario(), $this->idEmpresa(), $this->nivel())]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function guardarRespuestaRapidaAjax(): void
    {
        $this->requireAgente('w');
        $input = $this->inputJson();

        try {
            $id = $this->service->guardarRespuestaRapida(
                (int) ($input['id'] ?? 0),
                (string) ($input['titulo'] ?? ''),
                (string) ($input['contenido'] ?? ''),
                (string) ($input['tipo'] ?? 'personal'),
                $this->idUsuario(), $this->idEmpresa(), $this->nivel()
            );
            $this->json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function eliminarRespuestaRapidaAjax(): void
    {
        $this->requireAgente('d');
        $input = $this->inputJson();

        try {
            $this->service->eliminarRespuestaRapida((int) ($input['id'] ?? 0), $this->idUsuario(), $this->idEmpresa(), $this->nivel());
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ── Adjuntos ─────────────────────────────────────────────────────────────

    /** Sube un archivo a la conversación. Lo usan los dos lados. */
    public function adjuntarAjax(): void
    {
        $this->requireEmpresaSesion();

        try {
            $res = $this->service->adjuntar(
                (int) ($_POST['id'] ?? 0),
                $this->idUsuario(), $this->idEmpresa(), $this->nivel(),
                $_FILES['archivo'] ?? [],
                (string) ($_POST['texto'] ?? ''),
                (string) ($_POST['origen'] ?? '')
            );
            $this->json(['ok' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Descarga un adjunto. Se pide por id de MENSAJE, no por ruta: el acceso se
     * valida contra la conversación, así que no hay ruta que manipular.
     */
    public function adjuntoVer(): void
    {
        $this->requireEmpresaSesion();

        try {
            $a = $this->service->getAdjunto((int) ($_GET['id'] ?? 0), $this->idUsuario(), $this->idEmpresa(), $this->nivel());
        } catch (\Throwable $e) {
            http_response_code(404);
            echo 'Archivo no disponible';
            exit;
        }

        // Solo se muestran en línea las imágenes y los PDF; el resto se descarga.
        $inline = str_starts_with($a['mime'], 'image/') || $a['mime'] === 'application/pdf';

        header('Content-Type: ' . $a['mime']);
        header('Content-Length: ' . (string) filesize($a['ruta']));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($a['nombre']) . '"');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');

        while (ob_get_level()) {
            ob_end_clean();
        }
        readfile($a['ruta']);
        exit;
    }

    // ── Configuración del chat ───────────────────────────────────────────────

    public function configGet(): void
    {
        $this->requireAgente('r');

        try {
            $config = $this->service->getConfig();
            unset($config['created_at'], $config['updated_at'], $config['updated_by']);
            $this->json(['ok' => true, 'data' => $config]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function configStore(): void
    {
        $this->requireAgente('u');
        $input = $this->inputJson();

        try {
            $this->service->guardarConfig($input, $this->idUsuario(), $this->idEmpresa(), $this->nivel());
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireAgente('d');
        $input = $this->inputJson();

        try {
            $this->service->eliminarConversacion((int) ($input['id'] ?? 0), $this->idUsuario(), $this->idEmpresa(), $this->nivel());
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Permisos para la VISTA de la bandeja, con el mismo criterio que
     * requireAgente(): lo asignado en la empresa activa o, si ahí no hay nada, en
     * cualquier otra empresa del usuario. Si se usara getPermisos() a secas, un
     * agente que entra desde una empresa donde no tiene el módulo vería la bandeja
     * sin los botones de tomar/eliminar aunque los endpoints sí se lo permitan.
     *
     * @return array{ver:bool,crear:bool,actualizar:bool,eliminar:bool,todo:bool,id_submodulo:?int}
     */
    private function permisosAgente(): array
    {
        $perm  = $this->getPermisos();
        $letra = ['ver' => 'r', 'crear' => 'w', 'actualizar' => 'u', 'eliminar' => 'd', 'todo' => 't'];

        foreach ($letra as $clave => $l) {
            if (empty($perm[$clave])) {
                $perm[$clave] = \App\Helpers\Permisos::tienePermisoEnAlgunaEmpresa(self::RUTA_MODULO, $l);
            }
        }

        return $perm;
    }

    /**
     * Guard de la BANDEJA: exige el permiso $letra sobre el módulo en cualquiera de
     * las empresas del usuario, no solo en la activa.
     *
     * Va con el alcance del módulo (la bandeja recibe consultas de TODAS las
     * empresas, ver SoporteChatService::esAgente()): si el permiso se pidiera solo
     * en la empresa activa, quien atiende soporte perdería la bandeja —y el aviso
     * del navbar— apenas cambiara de empresa. Cuando no lo tiene en ninguna, se
     * delega en el guard estándar para responder igual que el resto del sistema
     * (403 en AJAX, redirección al dashboard en vista).
     *
     * @param string $letra r (ver), w (crear), u (actualizar), d (eliminar)
     */
    private function requireAgente(string $letra = 'r'): void
    {
        $this->requireEmpresaSesion();

        // Eliminar en la empresa demo se decide siempre en el guard estándar: ahí
        // vive el bloqueo central de esa cuenta y no debe poder saltarse por tener
        // el permiso en otra empresa.
        $esDemoBloqueante = $letra === 'd'
            && (int) ($_SESSION['nivel'] ?? 0) < 3
            && $this->empresaActualEsDemo();

        if (!$esDemoBloqueante
            && \App\Helpers\Permisos::tienePermisoEnAlgunaEmpresa(self::RUTA_MODULO, $letra)) {
            return;
        }

        $this->requirePermisoModulo(self::RUTA_MODULO, $letra);
    }

    private function idUsuario(): int
    {
        return (int) ($_SESSION['id_usuario'] ?? 0);
    }

    private function idEmpresa(): int
    {
        return (int) ($_SESSION['id_empresa'] ?? 0);
    }

    private function nivel(): int
    {
        return (int) ($_SESSION['nivel'] ?? 1);
    }

    /** @return array<string,mixed> */
    private function inputJson(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
