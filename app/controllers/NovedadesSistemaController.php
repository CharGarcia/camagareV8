<?php
/**
 * Controlador NovedadesSistema - Novedades / noticias GLOBALES del sistema.
 *
 * Rutas (el Router mapea kebab-case → camelCase):
 *   GET  /novedades-sistema                   index()           Lista completa — cualquier usuario
 *   GET  /novedades-sistema/estado            estado()          JSON: vigentes + pendientes + si abrir la ventana
 *   POST /novedades-sistema/marcar-leidas     marcarLeidas()    "Entendido" — cualquier usuario
 *   GET  /config/novedades-sistema            gestion()         Administración — SOLO nivel 3 (vía ConfigController)
 *   GET  /novedades-sistema/gestion-search    gestionSearch()   Filas del listado (AJAX) — SOLO nivel 3
 *   GET  /novedades-sistema/detalle?id=       detalle()         JSON de una novedad (para editar) — SOLO nivel 3
 *   POST /novedades-sistema/store             store()           Crear — SOLO nivel 3
 *   POST /novedades-sistema/update            update()          Editar — SOLO nivel 3
 *   POST /novedades-sistema/cambiar-estado    cambiarEstado()   Publicar / archivar / borrador — SOLO nivel 3
 *   POST /novedades-sistema/delete            delete()          Eliminar (lógico) — SOLO nivel 3
 *   GET  /novedades-sistema/lecturas-detalle  lecturasDetalle() Quién la leyó — SOLO nivel 3
 *
 * Catálogo global: las tablas NO llevan id_empresa. La ventana modal que ve el
 * usuario al ingresar vive en app/views/partials/novedades_modal.php.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\NovedadSistema;
use App\Services\NovedadSistemaService;

class NovedadesSistemaController extends Controller
{
    public const TIPOS_LABEL = [
        'nuevo'      => 'Nuevo',
        'mejora'     => 'Mejora',
        'aviso'      => 'Aviso',
        'correccion' => 'Corrección',
    ];

    public const ESTADOS_LABEL = [
        'borrador'  => 'Borrador',
        'publicada' => 'Publicada',
        'archivada' => 'Archivada',
    ];

    /** Ruta con la que se guardan las preferencias de vista (columnas, anchos, orden). */
    private const RUTA_MODULO = 'config/novedades-sistema';

    private const PER_PAGE = 20;

    /** Columnas del listado (data-col => etiqueta) para el dropdown de columnas visibles. */
    private const COLUMNAS_TABLA = [
        'publicado_at'  => 'Publicación',
        'tipo'          => 'Tipo',
        'titulo'        => 'Título',
        'modulo'        => 'Módulo',
        'vigente_hasta' => 'Vigente hasta',
        'estado'        => 'Estado',
        'leidas'        => 'Leída por',
    ];

    /** Pestañas del modal (id del panel => etiqueta), ocultables por usuario. */
    private const PESTANAS_MODAL = [
        'nvPaneForm'     => 'Novedad',
        'nvPaneAdjuntos' => 'Adjuntos',
        'nvPaneLeidos'   => 'Leída por',
    ];

    private NovedadSistema $model;
    private NovedadSistemaService $service;

    /** Adjuntos agrupados por id de novedad (se llena antes de formatear una lista). */
    private array $adjuntosMap = [];

    public function __construct()
    {
        parent::__construct();
        $this->model   = new NovedadSistema();
        $this->service = new NovedadSistemaService();
    }

    // ────────────────────────────────────────────────────────────────────
    //  Lado del usuario (cualquier usuario autenticado)
    // ────────────────────────────────────────────────────────────────────

    /** Lista completa de novedades vigentes ("Ver todas las novedades"). */
    public function index(): void
    {
        $this->requireAuth();
        $estado = $this->service->estadoParaUsuario((int) $_SESSION['id_usuario']);
        $this->cargarAdjuntosDe($estado['novedades']);
        $this->viewWithLayout('layouts.main', 'novedadesSistema.index', [
            'titulo'     => 'Novedades del sistema',
            'novedades'  => array_map([$this, 'formatearParaUsuario'], $estado['novedades']),
            'pendientes' => $estado['pendientes'],
            'esSuperadmin' => $this->esSuperadmin(),
        ]);
    }

    /** JSON con el estado de la ventana para el usuario actual. */
    public function estado(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        try {
            $estado = $this->service->estadoParaUsuario((int) $_SESSION['id_usuario']);
            $this->cargarAdjuntosDe($estado['novedades']);

            // La tarjeta se abre sola UNA vez por inicio de sesión (en la primera
            // pantalla tras el login), no en cada página. La marca vive en la
            // sesión PHP, así que se reinicia al volver a ingresar. Solo se
            // consume cuando la página puede abrirla (auto=1); las vistas que
            // desactivan la apertura automática no gastan el turno.
            $mostrar = $estado['mostrar'];
            if ($mostrar && !empty($_GET['auto'])) {
                if (!empty($_SESSION['novedades_tarjeta_mostrada'])) {
                    $mostrar = false;
                } else {
                    $_SESSION['novedades_tarjeta_mostrada'] = true;
                }
            } elseif ($mostrar) {
                $mostrar = false;
            }

            $this->json([
                'ok'         => true,
                'mostrar'    => $mostrar,
                'pendientes' => $estado['pendientes'],
                'novedades'  => array_map([$this, 'formatearParaUsuario'], $estado['novedades']),
            ]);
        } catch (\Throwable $e) {
            // Si la tabla aún no existe (SQL sin aplicar), no romper la pantalla.
            $this->json(['ok' => false, 'mostrar' => false, 'pendientes' => 0, 'novedades' => [], 'error' => $e->getMessage()]);
        }
    }

    /** "Entendido": marca como leídas todas las vigentes (o las indicadas en ids[]). */
    public function marcarLeidas(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->soloPost();
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        try {
            $nuevas = $this->service->marcarLeidas(
                (int) $_SESSION['id_usuario'],
                $this->idEmpresaSesion(),
                $ids,
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
            $this->json(['ok' => true, 'nuevas' => $nuevas]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Gestión (SOLO superadministrador, nivel 3)
    // ────────────────────────────────────────────────────────────────────

    public function gestion(): void
    {
        $this->requireAuth();
        $this->requireSuperadmin();

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        [$ordenCol, $ordenDir, $buscar, $page] = $this->parametrosListado($prefsVista);
        $total = $this->model->contar($buscar);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $totalUsuarios = $this->model->contarUsuariosActivos();

        $this->viewWithLayout('layouts.main', 'novedadesSistema.gestion', [
            'titulo'         => 'Novedades del sistema',
            'rutaModulo'     => self::RUTA_MODULO,
            'rowsHtml'       => $this->renderFilasHtml($rows, $totalUsuarios),
            'paginacionHtml' => $this->renderPaginacionHtml($page, $totalPages),
            'infoPaginacion' => $this->infoPaginacion($page, $total),
            'totalUsuarios'  => $totalUsuarios,
            'ordenCol'       => $ordenCol,
            'ordenDir'       => $ordenDir,
            'buscar'         => $buscar,
            'tipos'          => self::TIPOS_LABEL,
            'estados'        => self::ESTADOS_LABEL,
            'vistaConfig'    => $prefsVista,
            'columnasTabla'  => self::COLUMNAS_TABLA,
            'pestanasModal'  => self::PESTANAS_MODAL,
            'submodulos'     => (new \App\models\ModuloSubmodulo())->getRutasConNombre(),
        ]);
    }

    /** AJAX: filas + paginación del listado (búsqueda, orden y página sin recargar). */
    public function gestionSearch(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        [$ordenCol, $ordenDir, $buscar, $page] = $this->parametrosListado([]);
        $total = $this->model->contar($buscar);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $this->json([
            'ok'         => true,
            'rows'       => $this->renderFilasHtml($rows, $this->model->contarUsuariosActivos()),
            'pagination' => $this->renderPaginacionHtml($page, $totalPages),
            'info'       => $this->infoPaginacion($page, $total),
        ]);
    }

    /** JSON de una novedad (para cargar el formulario de edición). */
    public function detalle(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $id = (int) ($_GET['id'] ?? 0);
        $n = $id > 0 ? $this->model->find($id) : null;
        if ($n === null) {
            $this->json(['ok' => false, 'error' => 'La novedad no existe.']);
        }
        $this->json(['ok' => true, 'novedad' => [
            'id'            => (int) $n['id'],
            'tipo'          => (string) $n['tipo'],
            'titulo'        => (string) $n['titulo'],
            'resumen'       => (string) ($n['resumen'] ?? ''),
            'contenido'     => (string) $n['contenido'],
            'modulo'        => (string) ($n['modulo'] ?? ''),
            'ruta_modulo'   => (string) ($n['ruta_modulo'] ?? ''),
            'enlace'        => (string) ($n['enlace'] ?? ''),
            'estado'        => (string) $n['estado'],
            'adjuntos'      => $this->formatearAdjuntos($this->model->getAdjuntos($id)),
            'vigente_hasta' => (string) ($n['vigente_hasta'] ?? ''),
            'publicado_at'  => $this->fmtFechaHora($n['publicado_at'] ?? null),
            'leidas'        => $this->model->contarLecturas($id),
            'total_usuarios' => $this->model->contarUsuariosActivos(),
        ]]);
    }

    public function store(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $this->soloPost();
        try {
            $id = $this->service->crear($_POST, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Novedad guardada.', 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function update(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $this->soloPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        try {
            $this->service->actualizar($id, $_POST, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Novedad actualizada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function cambiarEstado(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $this->soloPost();
        $id = (int) ($_POST['id'] ?? 0);
        $estado = (string) ($_POST['estado'] ?? '');
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        try {
            $this->service->cambiarEstado($id, $estado, (int) $_SESSION['id_usuario']);
            $label = self::ESTADOS_LABEL[$estado] ?? $estado;
            $this->json(['ok' => true, 'msg' => 'La novedad ahora está: ' . $label . '.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $this->soloPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        try {
            $this->service->eliminar($id, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Novedad eliminada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Adjuntos
    // ────────────────────────────────────────────────────────────────────

    /** JSON: adjuntos de una novedad (pestaña Adjuntos). Solo superadmin. */
    public function adjuntos(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        $this->json(['ok' => true, 'adjuntos' => $this->formatearAdjuntos($this->model->getAdjuntos($id))]);
    }

    /** POST multipart: sube uno o varios archivos (campo archivos[]). Solo superadmin. */
    public function adjuntoSubir(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $this->soloPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        // post_max_size excedido: PHP vacía $_POST y $_FILES sin avisar.
        if (empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->json(['ok' => false, 'error' => 'Los archivos superan el tamaño máximo que admite el servidor.']);
        }
        try {
            $res = $this->service->subirAdjuntos($id, $this->normalizarFiles($_FILES['archivos'] ?? []), (int) $_SESSION['id_usuario']);
            $this->json([
                'ok'       => true,
                'subidos'  => count($res['subidos']),
                'errores'  => $res['errores'],
                'adjuntos' => $this->formatearAdjuntos($this->model->getAdjuntos($id)),
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** POST: elimina un adjunto. Solo superadmin. */
    public function adjuntoEliminar(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $this->soloPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        try {
            $this->service->eliminarAdjunto($id, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Adjunto eliminado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Descarga (o muestra, si es imagen y viene ?inline=1) un adjunto.
     * Cualquier usuario autenticado, solo de novedades publicadas; el
     * superadmin puede ver los de cualquier estado.
     */
    public function adjunto(): void
    {
        $this->requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $a = $id > 0 ? $this->model->findAdjunto($id) : null;
        $visible = $a !== null
            && !filter_var($a['novedad_eliminada'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && (($a['novedad_estado'] ?? '') === 'publicada' || $this->esSuperadmin());
        if (!$visible) {
            http_response_code(404);
            exit('Adjunto no disponible.');
        }
        $ruta = $this->service->storagePath() . '/' . basename((string) $a['archivo']);
        if (!is_file($ruta)) {
            http_response_code(404);
            exit('El archivo ya no está en el servidor.');
        }
        // Liberar el bloqueo de sesión antes de transmitir (ver VideosAyuda::stream).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $mime = (string) ($a['mime_type'] ?: 'application/octet-stream');
        $inline = !empty($_GET['inline']) && str_starts_with($mime, 'image/');
        $nombre = str_replace(['"', "\r", "\n"], '', (string) $a['nombre_original']);
        if (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($ruta));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nombre . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($ruta);
        exit;
    }

    /** Quién leyó una novedad. */
    public function lecturasDetalle(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }
        $rows = $this->model->getLecturasDetalle($id);
        $this->json(['ok' => true, 'lecturas' => array_map(fn(array $r): array => [
            'usuario'  => (string) ($r['usuario'] ?? ''),
            'empresa'  => (string) ($r['empresa'] ?? ''),
            'leido_at' => $this->fmtFechaHora($r['leido_at'] ?? null),
        ], $rows)]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

    /** Estructura que consume el modal y la lista (fechas ya formateadas). */
    private function formatearParaUsuario(array $n): array
    {
        $base = rtrim(BASE_URL ?? '', '/');
        $enlace = trim((string) ($n['enlace'] ?? ''));
        $ruta   = trim((string) ($n['ruta_modulo'] ?? ''), '/');
        // Enlace libre: URL externa tal cual, o ruta interna colgada de BASE_URL.
        $urlEnlace = '';
        if ($enlace !== '') {
            $urlEnlace = str_starts_with($enlace, '/') ? $base . $enlace : $enlace;
        }
        return [
            'id'         => (int) $n['id'],
            'tipo'       => (string) $n['tipo'],
            'tipo_label' => self::TIPOS_LABEL[$n['tipo']] ?? ucfirst((string) $n['tipo']),
            'titulo'     => (string) $n['titulo'],
            'resumen'    => (string) ($n['resumen'] ?? ''),
            'contenido'  => (string) $n['contenido'],   // HTML ya saneado en el Service al guardar
            'modulo'     => (string) ($n['modulo'] ?? ''),
            'url_modulo' => $ruta !== '' ? $base . '/' . $ruta : '',
            // El manual abre el artículo del módulo relacionado por deep-link ?ruta=…
            'url_manual' => $ruta !== '' ? $base . '/documentacion?ruta=' . rawurlencode($ruta) : '',
            'url_enlace' => $urlEnlace,
            'enlace_externo' => $urlEnlace !== '' && !str_starts_with($enlace, '/'),
            'adjuntos'   => $this->formatearAdjuntos($this->adjuntosMap[(int) $n['id']] ?? []),
            'fecha'      => $this->fmtFecha($n['publicado_at'] ?? null),
            'leida'      => filter_var($n['leida'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Orden, búsqueda y página del listado. Si no viene orden en la petición se
     * usa el guardado por el usuario en su vista (`__ordenCol__` / `__ordenDir__`).
     *
     * @return array{0:string,1:string,2:string,3:int}
     */
    private function parametrosListado(array $prefsVista): array
    {
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? (string) ($prefsVista['__ordenCol__'] ?? 'publicado_at'));
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? (string) ($prefsVista['__ordenDir__'] ?? 'DESC')));
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        if (!in_array($ordenCol, NovedadSistema::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'publicado_at';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'DESC';
        }
        return [$ordenCol, $ordenDir, $buscar, $page];
    }

    /** Botones anterior / siguiente (mismo patrón que Proveedores). */
    private function renderPaginacionHtml(int $page, int $totalPages): string
    {
        $prev = $page <= 1
            ? '<button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>'
            : '<button type="button" class="btn btn-outline-secondary" onclick="NV_fetchSearch(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>';
        $next = $page >= $totalPages
            ? '<button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>'
            : '<button type="button" class="btn btn-outline-secondary" onclick="NV_fetchSearch(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        return $prev . $next;
    }

    /** Texto "desde-hasta/total" de la paginación. */
    private function infoPaginacion(int $page, int $total): string
    {
        $from = $total > 0 ? (($page - 1) * self::PER_PAGE) + 1 : 0;
        $to   = $total > 0 ? min($page * self::PER_PAGE, $total) : 0;
        return $from . '-' . $to . '/' . $total;
    }

    /** Renderiza el <tbody> del listado de gestión. */
    private function renderFilasHtml(array $rows, int $totalUsuarios): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-megaphone fs-3 d-block mb-2"></i>No se encontraron novedades. Use "Nueva" para redactar la primera.</td></tr>';
        }
        $chip = [
            'nuevo'      => 'success',
            'mejora'     => 'info',
            'aviso'      => 'warning',
            'correccion' => 'primary',
        ];
        $estadoCls = [
            'borrador'  => 'secondary',
            'publicada' => 'success',
            'archivada' => 'warning',
        ];
        $html = '';
        foreach ($rows as $r) {
            $id     = (int) $r['id'];
            $tipo   = (string) ($r['tipo'] ?? 'nuevo');
            $estado = (string) ($r['estado'] ?? 'borrador');
            $c      = $chip[$tipo] ?? 'secondary';
            $ec     = $estadoCls[$estado] ?? 'secondary';
            $leidas = (int) ($r['leidas'] ?? 0);

            $html .= '<tr class="nv-row" role="button" tabindex="0" data-id="' . $id . '" data-estado="' . htmlspecialchars($estado) . '" data-titulo="' . htmlspecialchars((string) $r['titulo']) . '" title="Clic para editar">';
            $html .= '<td class="ps-3 text-nowrap text-muted small" data-col="publicado_at">' . ($this->fmtFechaHora($r['publicado_at'] ?? null) ?: '<span class="text-muted">—</span>') . '</td>';
            $html .= '<td data-col="tipo"><span class="badge bg-' . $c . ' bg-opacity-10 text-' . $c . ' border border-' . $c . ' border-opacity-25">' . htmlspecialchars(self::TIPOS_LABEL[$tipo] ?? $tipo) . '</span></td>';
            $html .= '<td class="text-truncate" style="max-width:360px" data-col="titulo"><div class="fw-medium text-truncate">' . htmlspecialchars((string) $r['titulo']) . '</div>';
            if (!empty($r['resumen'])) {
                $html .= '<small class="text-muted d-block text-truncate">' . htmlspecialchars((string) $r['resumen']) . '</small>';
            }
            $html .= '</td>';
            // Módulo: nombre del submódulo + su ruta (enlace directo al módulo).
            $rutaMod = trim((string) ($r['ruta_modulo'] ?? ''), '/');
            if (!empty($r['modulo'])) {
                $celdaModulo = '<div class="text-truncate">' . htmlspecialchars((string) $r['modulo']) . '</div>';
                if ($rutaMod !== '') {
                    $celdaModulo .= '<small class="text-muted d-block text-truncate"><i class="bi bi-box-arrow-up-right me-1"></i>' . htmlspecialchars($rutaMod) . '</small>';
                }
            } else {
                $celdaModulo = '<span class="text-muted">—</span>';
            }
            $html .= '<td class="text-truncate" style="max-width:200px" data-col="modulo">' . $celdaModulo . '</td>';

            // Vigencia: fecha + indicador (vencida / vence en N días / sin caducidad).
            $html .= '<td class="text-nowrap" data-col="vigente_hasta">' . $this->badgeVigencia($r['vigente_hasta'] ?? null, $estado) . '</td>';
            $html .= '<td class="text-center" data-col="estado"><span class="badge bg-' . $ec . ' bg-opacity-10 text-' . $ec . ' border border-' . $ec . ' border-opacity-25">' . htmlspecialchars(self::ESTADOS_LABEL[$estado] ?? $estado) . '</span></td>';
            $html .= '<td class="text-center pe-3" data-col="leidas"><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-eye me-1"></i>' . $leidas . ' / ' . $totalUsuarios . '</span></td>';
            $html .= '</tr>';
        }
        return $html;
    }

    /** Carga en $this->adjuntosMap los adjuntos de una lista de novedades (una sola consulta). */
    private function cargarAdjuntosDe(array $novedades): void
    {
        try {
            $this->adjuntosMap = $this->model->getAdjuntosPorNovedades(array_column($novedades, 'id'));
        } catch (\Throwable $e) {
            $this->adjuntosMap = []; // tabla de adjuntos aún no creada: la novedad se muestra igual
        }
    }

    /**
     * Estructura de un adjunto para el navegador (url de descarga, tamaño legible, ícono).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function formatearAdjuntos(array $rows): array
    {
        $base = rtrim(BASE_URL ?? '', '/');
        return array_map(function (array $a) use ($base): array {
            $mime = (string) ($a['mime_type'] ?? '');
            $ext  = strtolower(pathinfo((string) $a['nombre_original'], PATHINFO_EXTENSION));
            $esImagen = str_starts_with($mime, 'image/');
            $id = (int) $a['id'];
            return [
                'id'        => $id,
                'nombre'    => (string) $a['nombre_original'],
                'tamano'    => $this->fmtTamano($a['tamano_bytes'] ?? 0),
                'icono'     => $this->iconoAdjunto($ext, $esImagen),
                'es_imagen' => $esImagen,
                'url'       => $base . '/novedades-sistema/adjunto?id=' . $id,
                'url_vista' => $esImagen ? $base . '/novedades-sistema/adjunto?id=' . $id . '&inline=1' : '',
            ];
        }, $rows);
    }

    /** Ícono Bootstrap según el tipo de archivo. */
    private function iconoAdjunto(string $ext, bool $esImagen): string
    {
        if ($esImagen) {
            return 'bi-file-earmark-image text-info';
        }
        return match ($ext) {
            'pdf'                 => 'bi-file-earmark-pdf text-danger',
            'xls', 'xlsx', 'csv'  => 'bi-file-earmark-spreadsheet text-success',
            'doc', 'docx'         => 'bi-file-earmark-word text-primary',
            'ppt', 'pptx'         => 'bi-file-earmark-slides text-warning',
            'zip'                 => 'bi-file-earmark-zip text-secondary',
            default               => 'bi-file-earmark-text text-secondary',
        };
    }

    private function fmtTamano(mixed $bytes): string
    {
        $b = (float) $bytes;
        if ($b <= 0) {
            return '';
        }
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($b >= 1024 && $i < count($u) - 1) {
            $b /= 1024;
            $i++;
        }
        return number_format($b, $b < 10 && $i > 0 ? 1 : 0) . ' ' . $u[$i];
    }

    /**
     * Convierte $_FILES['archivos'] (con name[]/tmp_name[]/… en arrays) en una
     * lista de entradas independientes, una por archivo.
     *
     * @return array<int,array<string,mixed>>
     */
    private function normalizarFiles(array $files): array
    {
        if (empty($files) || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $out = [];
        foreach ($files['name'] as $i => $name) {
            $out[] = [
                'name'     => $name,
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
        }
        return $out;
    }

    /** Celda de "Vigente hasta": fecha con badge de estado de la vigencia. */
    private function badgeVigencia(mixed $vigenteHasta, string $estado): string
    {
        $ts = $vigenteHasta ? strtotime((string) $vigenteHasta) : false;
        if (!$ts) {
            return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25"><i class="bi bi-infinity me-1"></i>Sin caducidad</span>';
        }
        $fecha = date('d-m-Y', $ts);
        $hoy = strtotime(date('Y-m-d'));
        $dias = (int) floor(($ts - $hoy) / 86400);
        if ($dias < 0) {
            $badge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1" title="Ya no se muestra a los usuarios">Vencida</span>';
        } elseif ($dias === 0) {
            $badge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-1">Vence hoy</span>';
        } elseif ($dias <= 7 && $estado === 'publicada') {
            $badge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-1">Vence en ' . $dias . ' ' . ($dias === 1 ? 'día' : 'días') . '</span>';
        } else {
            $badge = '';
        }
        return '<span>' . $fecha . '</span>' . $badge;
    }

    private function fmtFechaHora(mixed $v): string
    {
        $ts = $v ? strtotime((string) $v) : false;
        return $ts ? date('d-m-Y H:i:s', $ts) : '';
    }

    private function fmtFecha(mixed $v): string
    {
        $ts = $v ? strtotime((string) $v) : false;
        return $ts ? date('d-m-Y', $ts) : '';
    }

    private function idEmpresaSesion(): ?int
    {
        return isset($_SESSION['id_empresa']) && (int) $_SESSION['id_empresa'] > 0 ? (int) $_SESSION['id_empresa'] : null;
    }

    private function esSuperadmin(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 0) >= 3;
    }

    private function requireSuperadmin(): void
    {
        if (!$this->esSuperadmin()) {
            $this->json(['ok' => false, 'error' => 'Solo el superadministrador puede administrar las novedades.'], 403);
        }
    }

    private function soloPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }
    }

    /** Blinda la respuesta JSON contra salida previa accidental. */
    private function prepararJson(): void
    {
        if (ob_get_level() > 0) {
            @ob_clean();
        }
        @ini_set('display_errors', '0');
        header('Content-Type: application/json; charset=utf-8');
    }
}
