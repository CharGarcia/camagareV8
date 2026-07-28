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
use App\Traits\SenalizacionSalaTrait;

class VideollamadasController extends BaseModuloController
{
    // Endpoints de señalización compartidos con el controlador público de invitados.
    use SenalizacionSalaTrait;

    /** Clave de sesión donde viaja la sala que se abre en ventana aparte (URLs sin parámetros). */
    private const SESSION_SALA = 'vc_sala_actual';

    /**
     * Identificador de par (peer) del usuario dentro de la sala.
     * Lo genera el servidor y vive en la sesión: el navegador nunca lo envía,
     * así no puede hacerse pasar por otro participante.
     */
    private const SESSION_PEER = 'vc_peer_id';

    /**
     * Si el usuario manda en la sala (anfitrión o acceso total).
     * Se resuelve UNA vez al entrar y se guarda en la sesión: resolverlo en cada
     * poll costaría dos consultas por segundo y participante solo para saber si
     * hay que mostrarle la cola de admisión.
     */
    private const SESSION_MANDA = 'vc_manda_sala';

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
            'esSuperadmin' => $this->esSuperadmin(),
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

    /** Manda a cada participante su invitación con el enlace que le corresponde. */
    public function enviarInvitacionesAjax(): void
    {
        $this->requireLeer();

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $sala = $this->service->getPorId($id, $idEmpresa);
            if ($sala === null) {
                $this->json(['ok' => false, 'mensaje' => 'La reunión no existe o no pertenece a esta empresa.']);
            }
            if (!$this->tieneAccesoTotal() && (int) $sala['id_anfitrion'] !== $idUsuario) {
                $this->json(['ok' => false, 'mensaje' => 'Solo el anfitrión puede enviar las invitaciones.']);
            }

            $r = $this->service->enviarInvitaciones($id, $idEmpresa, $idUsuario);

            $partes = [];
            if ($r['enviados'] > 0)   $partes[] = $r['enviados'] . ' invitación(es) enviada(s)';
            if ($r['fallidos'] > 0)   $partes[] = $r['fallidos'] . ' no se pudo(ieron) enviar';
            if ($r['sin_correo'] > 0) $partes[] = $r['sin_correo'] . ' sin correo registrado';

            $this->json([
                'ok'       => $r['enviados'] > 0,
                'mensaje'  => $partes === [] ? 'No hay participantes con correo.' : implode(', ', $partes) . '.',
                'detalle'  => $r,
            ]);
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
            'titulo'        => $sala['titulo'],
            'sala'          => $sala,
            'idUsuario'     => $idUsuario,
            'nombreUsuario' => (string) ($_SESSION['nombre'] ?? 'Usted'),
            'rutaModulo'    => $this->getRutaModulo(),
            'esAnfitrion'   => (int) $sala['id_anfitrion'] === $idUsuario || $this->tieneAccesoTotal(),
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
        $this->requireSuperadmin();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->json([
                'ok'       => true,
                'data'     => $this->service->getConfigParaVista($idEmpresa, $idUsuario),
                'global'   => $this->service->getConfigGlobalParaVista($idUsuario),
                'efectiva' => $this->resumenEfectiva($idEmpresa, $idUsuario),
                'max_mesh' => VideollamadaRules::MAX_PARTICIPANTES_MESH,
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function guardarConfigAjax(): void
    {
        $this->requireSuperadmin();

        try {
            $this->service->guardarConfig(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                $_POST
            );
            $this->json(['ok' => true, 'mensaje' => 'Configuración de la empresa guardada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Configuración global: la comparten todas las empresas. */
    public function guardarConfigGlobalAjax(): void
    {
        $this->requireSuperadmin();

        try {
            $this->service->guardarConfigGlobal((int) $_SESSION['id_usuario'], $_POST);
            $this->json(['ok' => true, 'mensaje' => 'Configuración global guardada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** De dónde sale cada servidor que se está usando realmente. */
    private function resumenEfectiva(int $idEmpresa, int $idUsuario): array
    {
        $ef = $this->service->getConfigEfectiva($idEmpresa, $idUsuario);

        return [
            'stun_urls'    => $ef['stun_urls'],
            'turn_urls'    => $ef['turn_urls'],
            'origen_turn'  => $ef['origen_turn'],
            'origen_cloud' => $ef['origen_cloud'],
            'puede_anular' => $ef['puede_anular'],
            'hay_turn'     => $ef['turn_urls'] !== '' || $ef['turn_key_id'] !== '',
        ];
    }

    /**
     * Prueba que la configuración entregue servidores utilizables.
     * Es lo que confirma si el TURN quedó bien puesto antes de una reunión real.
     */
    public function probarTurnAjax(): void
    {
        $this->requireSuperadmin();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $config    = $this->service->getConfigEfectiva($idEmpresa, $idUsuario);
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
    //  Los endpoints (entrarAjax, senalesAjax, enviarSenalAjax, salirAjax) los
    //  aporta SenalizacionSalaTrait, que también usa el controlador público de
    //  invitados. Aquí solo se resuelve la identidad de quien llama.
    // ────────────────────────────────────────────────────────────────────

    /**
     * Identidad de un usuario del ERP dentro de la sala.
     *
     * Exige sesión y permiso de lectura del módulo, y libera el lock de sesión
     * antes de devolver: estos endpoints se consultan una vez por segundo y no
     * pueden dejar bloqueado el resto del sistema para ese usuario.
     */
    protected function contextoSala(bool $creandoPeer = false): array
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nombre    = (string) ($_SESSION['nombre'] ?? 'Usuario');
        $idSala    = (int) ($_POST['id'] ?? $_GET['id'] ?? $_SESSION[self::SESSION_SALA] ?? 0);

        if ($creandoPeer) {
            // El identificador de par lo genera el SERVIDOR y vive en la sesión:
            // así el navegador no puede hacerse pasar por otro participante.
            $peerId = 'u' . $idUsuario . '-' . bin2hex(random_bytes(5));
            $_SESSION[self::SESSION_PEER] = $peerId;

            $sala = $this->service->getPorId($idSala, $idEmpresa);
            $rol  = $sala !== null ? $this->rolEnSala($sala, $idUsuario) : 'participante';
            $manda = in_array($rol, ['anfitrion', 'moderador'], true);

            // Se deja resuelto para que el poll no recalcule permisos cada segundo.
            $_SESSION[self::SESSION_MANDA] = $manda;
        } else {
            $peerId = (string) ($_SESSION[self::SESSION_PEER] ?? '');
            $manda  = !empty($_SESSION[self::SESSION_MANDA]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($peerId === '') {
            $this->json(['ok' => false, 'reentrar' => true, 'mensaje' => 'La sesión de la sala se perdió.']);
        }

        return [
            'id_empresa'  => $idEmpresa,
            'id_sala'     => $idSala,
            'peer_id'     => $peerId,
            'nombre'      => $nombre,
            'id_usuario'  => $idUsuario,
            'manda_sala'  => $manda,
            'es_invitado' => false,
        ];
    }

    protected function alEntrarEnSala(array $ctx): void
    {
        $this->service->registrarEntrada($ctx['id_sala'], $ctx['id_empresa'], (int) $ctx['id_usuario']);
    }

    protected function alSalirDeSala(array $ctx): void
    {
        $this->service->registrarSalida($ctx['id_sala'], $ctx['id_empresa'], (int) $ctx['id_usuario']);
    }

    protected function salaService(): VideollamadaService
    {
        return $this->service;
    }

    protected function salaRepository(): VideollamadaRepository
    {
        return $this->repository;
    }

    /** El anfitrión (o quien tenga acceso total) deja pasar o rechaza a alguien. */
    public function admitirAjax(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idSala    = (int) ($_POST['id'] ?? $_SESSION[self::SESSION_SALA] ?? 0);
        $peerId    = (string) ($_POST['peer_id'] ?? '');
        $admitir   = filter_var($_POST['admitir'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $puede     = $this->tieneAccesoTotal();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($peerId === '') {
            $this->json(['ok' => false, 'mensaje' => 'Falta indicar a quién admitir.']);
        }

        try {
            $datos = $this->repository->getDatosPoll($idSala, $idEmpresa);
            if (!$puede && $datos['id_anfitrion'] !== $idUsuario) {
                $this->json(['ok' => false, 'mensaje' => 'Solo el anfitrión puede admitir participantes.']);
            }

            $senal = new SenalizacionService();
            $senal->resolverAdmision($idSala, $peerId, $admitir);

            $this->service->registrarAdmision($idSala, $idEmpresa, $idUsuario, $peerId, $admitir);

            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Apoyo
    // ────────────────────────────────────────────────────────────────────

    /** Rol del usuario dentro de una sala concreta. */
    private function rolEnSala(array $sala, int $idUsuario): string
    {
        if ((int) $sala['id_anfitrion'] === $idUsuario || $this->tieneAccesoTotal()) {
            return 'anfitrion';
        }

        foreach ($sala['participantes'] ?? [] as $p) {
            if ((int) ($p['id_usuario'] ?? 0) === $idUsuario) {
                return (string) ($p['rol'] ?? 'participante');
            }
        }

        return 'participante';
    }

    private function tieneAccesoTotal(): bool
    {
        return !empty($this->getPermisos()['todo']);
    }

    private function esSuperadmin(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 0) >= 3;
    }

    /**
     * La configuración guarda credenciales de servicios contratados y afecta a
     * todas las empresas, así que queda reservada al superadministrador: no
     * basta con tener permiso de actualizar reuniones.
     */
    private function requireSuperadmin(): void
    {
        $this->requireLeer();

        if (!$this->esSuperadmin()) {
            $this->json(['ok' => false, 'mensaje' => 'Solo el superadministrador puede ver o cambiar esta configuración.'], 403);
        }
    }

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
