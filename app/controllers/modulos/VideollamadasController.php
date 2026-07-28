<?php

/**
 * Controlador del módulo Videollamadas.
 *
 * Rutas (el Router mapea kebab-case → camelCase):
 *   GET  /modulos/videollamadas                  index()          Listado
 *   GET  /modulos/videollamadas/searchAjax       searchAjax()     Buscador + paginación
 *   GET  /modulos/videollamadas/getSalaAjax      getSalaAjax()    Una sala + participantes
 *   POST /modulos/videollamadas/guardarAjax      guardarAjax()    Crear / actualizar
 *   POST /modulos/videollamadas/eliminarAjax     eliminarAjax()   Eliminación lógica
 *   POST /modulos/videollamadas/iniciarAjax      iniciarAjax()    Abre la sala (deja el id en sesión)
 *   POST /modulos/videollamadas/finalizarAjax    finalizarAjax()  Cierra la reunión
 *   GET  /modulos/videollamadas/sala             sala()           Vista standalone (ventana aparte)
 *   GET  /modulos/videollamadas/credencialesAjax credencialesAjax() Servidores ICE de la sala
 *
 * Sin lógica de negocio: todo se delega a VideollamadaService.
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\VideollamadaRepository;
use App\Rules\modulos\VideollamadaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\VideollamadaService;
use App\Services\modulos\videollamadas\SenalizacionService;

class VideollamadasController extends BaseModuloController
{
    /** Clave de sesión donde viaja la sala que se abre en ventana aparte (URLs sin parámetros). */
    private const SESSION_SALA = 'vc_sala_actual';

    /**
     * Identificador de par (peer) del usuario dentro de la sala.
     * Lo genera el servidor y vive en la sesión: el navegador nunca lo envía,
     * así no puede hacerse pasar por otro participante.
     */
    private const SESSION_PEER = 'vc_peer_id';

    private VideollamadaService $service;
    private VideollamadaRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/videollamadas';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new VideollamadaRepository();
        $this->service    = new VideollamadaService(
            $this->repository,
            new VideollamadaRules(),
            new LogSistemaService()
        );
    }

    // ────────────────────────────────────────────────────────────────────
    //  Listado
    // ────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_inicio');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();

        // Registros propios: sin permiso de acceso total, el usuario solo ve las
        // salas que él creó (§6 de CLAUDE.md).
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $this->viewWithLayout('layouts.main', 'modulos/videollamadas/index', [
            'titulo'      => 'Videollamadas',
            'perm'        => $perm,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'usuarios'    => $this->repository->getUsuariosEmpresa($idEmpresa),
            'maxMesh'     => VideollamadaRules::MAX_PARTICIPANTES_MESH,
            'idUsuario'   => (int) $_SESSION['id_usuario'],
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_inicio');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">'
               . '<i class="bi bi-camera-video fs-3 d-block mb-2"></i>No se encontraron reuniones.</td></tr>';
        } else {
            // Mismo partial que usa el render inicial: así el HTML de la fila
            // no se desincroniza entre la carga de página y la búsqueda.
            foreach ($rows as $r) {
                include MVC_APP . '/views/modulos/videollamadas/_fila.php';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDis = ($page <= 1) ? 'disabled' : '';
        $nextDis = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" ' . $prevDis . ' onclick="window.VC_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm" ' . $nextDis . ' onclick="window.VC_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        $this->json([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  CRUD
    // ────────────────────────────────────────────────────────────────────

    public function getSalaAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $sala = $this->service->getPorId($id, $idEmpresa);
        if ($sala === null) {
            $this->json(['ok' => false, 'mensaje' => 'La reunión no existe o no pertenece a esta empresa.']);
        }

        $this->json(['ok' => true, 'data' => $sala]);
    }

    public function guardarAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        // El permiso depende de si es alta o edición.
        if ($id > 0) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }

        try {
            $data = $this->datosDesdePost();

            if ($id > 0) {
                $this->service->actualizar($id, $data);
                $mensaje = 'Reunión actualizada.';
            } else {
                $id = $this->service->crear($data);
                $mensaje = 'Reunión creada.';
            }

            $this->json(['ok' => true, 'id' => $id, 'mensaje' => $mensaje]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Reunión eliminada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Sala
    // ────────────────────────────────────────────────────────────────────

    /**
     * Marca la sala como en curso y la deja fijada en sesión para que la
     * ventana aparte la abra sin parámetros en la URL.
     */
    public function iniciarAjax(): void
    {
        $this->requireLeer();

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $perm      = $this->getPermisos();

            $sala = $this->service->iniciar($id, $idEmpresa, $idUsuario, !empty($perm['todo']));

            $_SESSION[self::SESSION_SALA] = $id;

            $this->json([
                'ok'     => true,
                'url'    => rtrim(BASE_URL ?? '', '/') . '/' . $this->getRutaModulo() . '/sala',
                'codigo' => $sala['codigo'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function finalizarAjax(): void
    {
        $this->requireActualizar();

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $perm      = $this->getPermisos();

            $this->service->finalizar($id, $idEmpresa, $idUsuario, !empty($perm['todo']));
            $this->json(['ok' => true, 'mensaje' => 'Reunión finalizada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Vista de la sala, en ventana aparte (igual que el visor de videos de ayuda).
     *
     * Es una vista STANDALONE: arma su propio <head>, así que incluye
     * partials/csrf.php o sus peticiones responderían 419.
     */
    public function sala(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idSala    = (int) ($_SESSION[self::SESSION_SALA] ?? 0);

        if ($idSala <= 0) {
            $this->redirect(rtrim(BASE_URL ?? '', '/') . '/' . $this->getRutaModulo());
        }

        $sala = $this->service->getPorId($idSala, $idEmpresa);
        if ($sala === null) {
            unset($_SESSION[self::SESSION_SALA]);
            $this->redirect(rtrim(BASE_URL ?? '', '/') . '/' . $this->getRutaModulo());
        }

        $this->view('modulos/videollamadas/sala', [
            'titulo'     => $sala['titulo'],
            'sala'       => $sala,
            'idUsuario'  => $idUsuario,
            'rutaModulo' => $this->getRutaModulo(),
            'esAnfitrion' => (int) $sala['id_anfitrion'] === $idUsuario,
        ]);
    }

    /**
     * Servidores ICE (STUN/TURN) para que el navegador establezca la conexión.
     *
     * Libera el lock de sesión antes de responder: la sala consulta este
     * endpoint al entrar y no debe bloquear el resto de peticiones del usuario.
     */
    public function credencialesAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idSala    = (int) ($_GET['id'] ?? $_SESSION[self::SESSION_SALA] ?? 0);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $credenciales = $this->service->getCredenciales($idSala, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'data' => $credenciales]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Configuración de la empresa (servidores STUN/TURN y límites)
    // ────────────────────────────────────────────────────────────────────

    public function getConfigAjax(): void
    {
        $this->requireLeer();

        try {
            $config = $this->service->getConfigParaVista(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            $this->json(['ok' => true, 'data' => $config, 'max_mesh' => VideollamadaRules::MAX_PARTICIPANTES_MESH]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function guardarConfigAjax(): void
    {
        $this->requireActualizar();

        try {
            $this->service->guardarConfig(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                $_POST
            );
            $this->json(['ok' => true, 'mensaje' => 'Configuración guardada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Prueba que la configuración entregue servidores utilizables.
     * Es lo que confirma si el TURN quedó bien puesto antes de una reunión real.
     */
    public function probarTurnAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $config    = $this->service->getConfig($idEmpresa, $idUsuario);
            $proveedor = new \App\Services\modulos\videollamadas\ProveedorInterno();
            $cred      = $proveedor->obtenerCredenciales(['codigo' => ''], $config, []);

            $stun = 0;
            $turn = 0;
            foreach ($cred['ice_servers'] as $srv) {
                $urls = is_array($srv['urls']) ? $srv['urls'] : [$srv['urls']];
                foreach ($urls as $u) {
                    if (str_starts_with((string) $u, 'turn')) {
                        $turn++;
                    } else {
                        $stun++;
                    }
                }
            }

            $this->json([
                'ok'    => true,
                'stun'  => $stun,
                'turn'  => $turn,
                'aviso' => $turn === 0
                    ? 'No hay TURN disponible. Entre el 10% y el 20% de las llamadas no va a conectar.'
                    : '',
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Señalización WebRTC
    //
    //  Estos tres endpoints son el "buzón" por el que los navegadores se ponen
    //  de acuerdo antes de hablarse directo. Se consultan una vez por segundo
    //  durante la negociación, así que TODOS liberan el lock de sesión de
    //  inmediato: si no, el usuario vería congelarse el resto del sistema.
    //
    //  Regla: polling corto, NUNCA long-polling. Apache corre en prefork con
    //  mod_php y una petición que espera bloquea un proceso completo.
    // ────────────────────────────────────────────────────────────────────

    /**
     * El navegador anuncia que entró a la sala.
     * Devuelve su identificador de par, el cursor del buzón, los servidores ICE
     * y quiénes están ya dentro.
     */
    public function entrarAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nombre    = (string) ($_SESSION['nombre'] ?? 'Usuario');
        $idSala    = (int) ($_POST['id'] ?? $_SESSION[self::SESSION_SALA] ?? 0);

        // El identificador de par lo genera el SERVIDOR y vive en la sesión: así
        // el navegador no puede hacerse pasar por otro participante.
        $peerId = 'u' . $idUsuario . '-' . bin2hex(random_bytes(5));
        $_SESSION[self::SESSION_PEER] = $peerId;

        // Ya no se escribe más en la sesión: liberar el lock cuanto antes.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $sala = $this->service->getPorId($idSala, $idEmpresa);
            if ($sala === null) {
                $this->json(['ok' => false, 'mensaje' => 'La reunión no existe o no pertenece a esta empresa.']);
            }
            if (in_array($sala['estado'], ['finalizada', 'cancelada'], true)) {
                $this->json(['ok' => false, 'mensaje' => 'Esta reunión ya terminó.']);
            }

            $senal = new SenalizacionService();
            $senal->marcarPresencia($idSala, $peerId, [
                'id_usuario' => $idUsuario,
                'nombre'     => $nombre,
                'anfitrion'  => (int) $sala['id_anfitrion'] === $idUsuario,
            ]);

            $this->service->registrarEntrada($idSala, $idEmpresa, $idUsuario);

            $this->json([
                'ok'           => true,
                'peer_id'      => $peerId,
                'cursor'       => $senal->getSecuenciaActual($idSala),
                'presentes'    => $senal->getPresentes($idSala, $peerId),
                'credenciales' => $this->service->getCredenciales($idSala, $idEmpresa, $idUsuario),
                'max'          => (int) $sala['max_participantes'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Latido y recogida del buzón: refresca la presencia y devuelve los mensajes
     * nuevos dirigidos a este participante.
     */
    public function senalesAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nombre    = (string) ($_SESSION['nombre'] ?? 'Usuario');
        $peerId    = (string) ($_SESSION[self::SESSION_PEER] ?? '');
        $idSala    = (int) ($_GET['id'] ?? $_SESSION[self::SESSION_SALA] ?? 0);
        $desde     = (int) ($_GET['cursor'] ?? 0);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($peerId === '') {
            $this->json(['ok' => false, 'reentrar' => true, 'mensaje' => 'La sesión de la sala se perdió.']);
        }

        try {
            $senal = new SenalizacionService();
            $senal->marcarPresencia($idSala, $peerId, [
                'id_usuario' => $idUsuario,
                'nombre'     => $nombre,
            ]);

            $recibido = $senal->recibir($idSala, $peerId, $desde);
            $estado   = $this->repository->getEstado($idSala, $idEmpresa);

            $this->json([
                'ok'        => true,
                'cursor'    => $recibido['cursor'],
                'mensajes'  => $recibido['mensajes'],
                'presentes' => $senal->getPresentes($idSala, $peerId),
                'estado'    => $estado,
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Deja un mensaje (oferta, respuesta o candidato ICE) para otro participante. */
    public function enviarSenalAjax(): void
    {
        $this->requireLeer();

        $peerId = (string) ($_SESSION[self::SESSION_PEER] ?? '');
        $idSala = (int) ($_POST['id'] ?? $_SESSION[self::SESSION_SALA] ?? 0);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($peerId === '') {
            $this->json(['ok' => false, 'reentrar' => true]);
        }

        $tipo = (string) ($_POST['tipo'] ?? '');
        if (!in_array($tipo, ['offer', 'answer', 'ice', 'bye', 'estado'], true)) {
            $this->json(['ok' => false, 'mensaje' => 'Tipo de señal no válido.']);
        }

        $payload = json_decode((string) ($_POST['payload'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $senal = new SenalizacionService();
            $seq = $senal->enviar($idSala, $peerId, (string) ($_POST['para'] ?? ''), $tipo, $payload);
            $this->json(['ok' => true, 'seq' => $seq]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Salida explícita: quita la presencia y avisa a los demás para que cierren su conexión. */
    public function salirAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $peerId    = (string) ($_SESSION[self::SESSION_PEER] ?? '');
        $idSala    = (int) ($_POST['id'] ?? $_SESSION[self::SESSION_SALA] ?? 0);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($peerId === '' || $idSala <= 0) {
            $this->json(['ok' => true]);
        }

        try {
            $senal = new SenalizacionService();
            $senal->enviar($idSala, $peerId, '', 'bye', []);
            $senal->quitarPresencia($idSala, $peerId);
            $this->service->registrarSalida($idSala, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            // La salida es best-effort: si falla, el TTL de presencia lo resuelve solo.
            error_log('Videollamadas::salirAjax ' . $e->getMessage());
        }

        $this->json(['ok' => true]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Apoyo
    // ────────────────────────────────────────────────────────────────────

    /** Arma el arreglo de datos desde $_POST, ya saneado para el service. */
    private function datosDesdePost(): array
    {
        $participantes = [];
        if (!empty($_POST['participantes'])) {
            $decodificado = json_decode((string) $_POST['participantes'], true);
            if (is_array($decodificado)) {
                $participantes = $decodificado;
            }
        }

        return [
            'id_empresa'        => (int) $_SESSION['id_empresa'],
            'usuario_id'        => (int) $_SESSION['id_usuario'],
            'titulo'            => trim((string) ($_POST['titulo'] ?? '')),
            'descripcion'       => trim((string) ($_POST['descripcion'] ?? '')) ?: null,
            'tipo'              => $_POST['tipo'] ?? 'instantanea',
            'fecha_inicio'      => trim((string) ($_POST['fecha_inicio'] ?? '')) ?: null,
            'fecha_fin'         => trim((string) ($_POST['fecha_fin'] ?? '')) ?: null,
            'duracion_minutos'  => (int) ($_POST['duracion_minutos'] ?? 0),
            'id_anfitrion'      => (int) ($_POST['id_anfitrion'] ?? 0),
            'sala_espera'       => !empty($_POST['sala_espera']),
            'permite_invitados' => !empty($_POST['permite_invitados']),
            'max_participantes' => (int) ($_POST['max_participantes'] ?? 6),
            'grabar'            => !empty($_POST['grabar']),
            'participantes'     => $participantes,
        ];
    }

}
