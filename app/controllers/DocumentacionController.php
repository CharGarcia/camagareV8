<?php
/**
 * Controlador Documentacion — Manual del Sistema (módulo GLOBAL).
 *
 * Rutas (el Router mapea kebab-case → camelCase):
 *   GET  /documentacion                 index()    Visor (ventana aparte) — cualquier usuario
 *   GET  /documentacion?slug=…                     Abre directamente un artículo
 *   GET  /documentacion?ruta=modulos/x             Ayuda contextual: abre el manual de ese módulo
 *   GET  /documentacion/completo        completo() Todo el manual en una página (imprimir/PDF)
 *   GET  /documentacion/arbol           arbol()    JSON del índice visible — cualquier usuario
 *   GET  /documentacion/buscar?q=       buscar()   JSON de resultados — cualquier usuario
 *   GET  /documentacion/articulo?slug=  articulo() JSON del artículo — cualquier usuario
 *   POST /documentacion/feedback        feedback() ¿Te resultó útil? — cualquier usuario
 *   GET  /documentacion/gestion         gestion()  Administración — SOLO nivel 3
 *   GET  /documentacion/obtener?id=     obtener()  JSON para el modal de edición — SOLO nivel 3
 *   POST /documentacion/store           store()    Crear  — SOLO nivel 3
 *   POST /documentacion/update          update()   Editar — SOLO nivel 3
 *   POST /documentacion/delete          delete()   Eliminar (lógico) — SOLO nivel 3
 *   POST /documentacion/sincronizar     sincronizar() Publicar docs/manual/*.md — SOLO nivel 3
 *   GET  /documentacion/doctor          doctor()   Qué falta por documentar — SOLO nivel 3
 *
 * Es un catálogo global: las tablas de documentación NO llevan id_empresa.
 * Qué ve cada usuario lo decide DocumentacionService::contexto(), que se traduce
 * a condiciones SQL en el modelo (nunca se filtra en la vista).
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Documentacion;
use App\Services\DocumentacionService;

class DocumentacionController extends Controller
{
    private Documentacion $model;
    private DocumentacionService $service;

    public function __construct()
    {
        parent::__construct();
        $this->model   = new Documentacion();
        $this->service = new DocumentacionService();
    }

    // ────────────────────────────────────────────────────────────────────
    //  Visor (cualquier usuario autenticado) — se abre en ventana aparte
    // ────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();

        // Deep-link: ?slug=modulos/clientes  ó  ?ruta=modulos/clientes (contextual).
        $slug = trim((string) ($_GET['slug'] ?? ''));
        if ($slug === '') {
            $ruta = trim((string) ($_GET['ruta'] ?? ''));
            if ($ruta !== '') {
                $slug = (string) ($this->service->slugPorRutaModulo($ruta) ?? '');
            }
        }

        $this->view('documentacion.visor', [
            'titulo'       => 'Manual del Sistema',
            'esSuperadmin' => $this->esSuperadmin(),
            'slugInicial'  => $slug,
            'anclaInicial' => trim((string) ($_GET['ancla'] ?? '')),
        ]);
    }

    /**
     * Manual completo en una sola página, pensado para imprimir o guardar como
     * PDF desde el navegador. Cada usuario obtiene solo los artículos que puede
     * ver: la visibilidad se aplica igual que en el visor.
     */
    public function completo(): void
    {
        $this->requireAuth();

        $this->view('documentacion.completo', [
            'titulo'     => 'Manual del Sistema',
            'categorias' => $this->service->manualCompleto(),
            'empresa'    => (string) ($_SESSION['nombre_empresa'] ?? ''),
        ]);
    }

    /** JSON con el índice del manual agrupado por categoría. */
    public function arbol(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->json(['ok' => true, 'categorias' => $this->service->arbol()]);
    }

    /** JSON con los resultados de búsqueda (fragmento resaltado incluido). */
    public function buscar(): void
    {
        $this->prepararJson();
        $this->requireAuth();

        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            $this->json(['ok' => true, 'resultados' => [], 'termino' => $q]);
        }

        $rows = $this->service->buscar($q, 25);
        $resultados = array_map(function (array $r): array {
            return [
                'slug'           => (string) $r['slug'],
                'titulo'         => (string) $r['titulo'],
                'categoria'      => (string) ($r['categoria'] ?? ''),
                'seccion_titulo' => (string) ($r['seccion_titulo'] ?? ''),
                'ancla'          => (string) ($r['ancla'] ?? ''),
                'fragmento'      => $this->fragmentoSeguro((string) ($r['fragmento'] ?? '')),
            ];
        }, $rows);

        $this->json(['ok' => true, 'resultados' => $resultados, 'termino' => $q]);
    }

    /** JSON del artículo completo: contenido, índice y videos relacionados. */
    public function articulo(): void
    {
        $this->prepararJson();
        $this->requireAuth();

        $slug = trim((string) ($_GET['slug'] ?? ''));
        if ($slug === '') {
            $this->json(['ok' => false, 'error' => 'Falta la dirección del artículo.']);
        }

        $art = $this->service->articulo($slug);
        if ($art === null) {
            $this->json(['ok' => false, 'error' => 'El artículo no existe o no está disponible para su usuario.'], 404);
        }

        $base = rtrim(BASE_URL ?? '', '/');
        $ts = !empty($art['updated_at']) ? strtotime((string) $art['updated_at']) : false;

        $this->json(['ok' => true, 'articulo' => [
            'id'          => (int) $art['id'],
            'slug'        => (string) $art['slug'],
            'titulo'      => (string) $art['titulo'],
            'resumen'     => (string) ($art['resumen'] ?? ''),
            'contenido'   => (string) ($art['contenido_html'] ?? ''),
            'categoria'   => (string) ($art['categoria'] ?? ''),
            'ruta_modulo' => (string) ($art['ruta_modulo'] ?? ''),
            'version'     => (string) ($art['version'] ?? ''),
            'utiles'      => (int) ($art['utiles'] ?? 0),
            'no_utiles'   => (int) ($art['no_utiles'] ?? 0),
            'actualizado' => $ts ? date('d-m-Y H:i:s', $ts) : '',
            'secciones'   => array_map(static fn(array $s): array => [
                'nivel'  => (int) $s['nivel'],
                'titulo' => (string) $s['titulo'],
                'ancla'  => (string) $s['ancla'],
            ], $art['secciones'] ?? []),
            'videos'      => array_map(static fn(array $v): array => [
                'id'     => (int) $v['id'],
                'titulo' => (string) $v['titulo'],
                'src'    => $base . '/videos-ayuda/stream?id=' . (int) $v['id'],
            ], $art['videos'] ?? []),
        ]]);
    }

    /** Voto "¿te resultó útil?" del usuario actual sobre un artículo. */
    public function feedback(): void
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

        $util = filter_var($_POST['util'] ?? false, FILTER_VALIDATE_BOOLEAN);
        try {
            $totales = $this->service->feedback(
                $id,
                (int) $_SESSION['id_usuario'],
                $util,
                trim((string) ($_POST['comentario'] ?? '')) ?: null
            );
            $this->json(['ok' => true] + $totales);
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

        $ordenCol = trim((string) ($_GET['sort'] ?? 'categoria'));
        $ordenDir = strtoupper(trim((string) ($_GET['dir'] ?? 'asc')));
        $buscar   = trim((string) ($_GET['b'] ?? ''));
        if (!in_array($ordenCol, Documentacion::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'categoria';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->view('documentacion.gestion', [
            'titulo'      => 'Gestión del Manual del Sistema',
            'rows'        => $rows,
            'rowsHtml'    => $this->renderFilasGestionHtml($rows),
            'categorias'  => $this->model->getCategorias(),
            'videos'      => $this->model->getVideosDisponibles(),
            'sinResultado' => $this->model->getBusquedasSinResultado(30),
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'buscar'      => $buscar,
        ]);
    }

    /**
     * AJAX: listado de artículos del manual (tabla), para búsqueda y
     * ordenamiento en tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function gestionSearch(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();

        $ordenCol = trim((string) ($_GET['sort'] ?? $_POST['sort'] ?? 'categoria'));
        $ordenDir = strtoupper(trim((string) ($_GET['dir'] ?? $_POST['dir'] ?? 'ASC')));
        $buscar   = trim((string) ($_GET['b'] ?? $_POST['b'] ?? ''));
        if (!in_array($ordenCol, Documentacion::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'categoria';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->json([
            'ok' => true,
            'rows' => $this->renderFilasGestionHtml($rows),
        ]);
    }

    private function badgeVisibilidad(string $v): string
    {
        return match ($v) {
            'superadmin' => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Superadmin</span>',
            'admin'      => '<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25">Admin</span>',
            default      => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Todos</span>',
        };
    }

    private function badgeEstado(string $e): string
    {
        return match ($e) {
            'borrador' => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Borrador</span>',
            'obsoleto' => '<span class="badge bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25">Obsoleto</span>',
            default    => '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Activo</span>',
        };
    }

    private function fmtFechaGestion($v): string
    {
        if (empty($v)) {
            return '-';
        }
        $ts = strtotime((string) $v);
        return $ts ? date('d-m-Y H:i:s', $ts) : '-';
    }

    /**
     * Renderiza el <tbody> completo (filas o mensaje de "sin resultados").
     * Usado tanto por la carga inicial (vista) como por gestionSearchAjax.
     */
    private function renderFilasGestionHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="11" class="text-center text-muted py-4">Todavía no hay artículos. Cree el primero con «Nuevo artículo».</td></tr>';
        }
        $base = rtrim(BASE_URL ?? '', '/');
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr class="dg-row" data-id="' . (int) $r['id'] . '">';
            $html .= '<td class="text-center">' . (int) $r['orden'] . '</td>';
            $html .= '<td><div class="fw-medium">' . htmlspecialchars((string) $r['titulo']) . '</div><div class="dg-slug">' . htmlspecialchars((string) $r['slug']) . '</div></td>';
            $html .= '<td>' . htmlspecialchars((string) ($r['categoria'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($r['tipo'] ?? '')) . '</td>';
            $html .= '<td class="text-center">' . $this->badgeVisibilidad((string) ($r['visibilidad'] ?? 'todos')) . '</td>';
            $html .= '<td class="text-center">' . $this->badgeEstado((string) ($r['estado'] ?? 'activo')) . '</td>';
            $html .= '<td class="text-center">';
            if ((($r['origen'] ?? 'manual')) === 'archivo') {
                $html .= '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" title="' . htmlspecialchars((string) ($r['archivo_origen'] ?? '')) . '">Archivo</span>';
            } else {
                $html .= '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Pantalla</span>';
            }
            $html .= '</td>';
            $html .= '<td class="text-center">' . (int) ($r['vistas'] ?? 0) . '</td>';
            $html .= '<td class="text-center text-nowrap"><span class="text-success"><i class="bi bi-hand-thumbs-up"></i> ' . (int) ($r['utiles'] ?? 0) . '</span> <span class="text-danger ms-2"><i class="bi bi-hand-thumbs-down"></i> ' . (int) ($r['no_utiles'] ?? 0) . '</span></td>';
            $html .= '<td class="text-nowrap small">' . $this->fmtFechaGestion($r['updated_at'] ?? null) . '</td>';
            $html .= '<td class="text-end text-nowrap">'
                . '<a href="' . $base . '/documentacion?slug=' . urlencode((string) $r['slug']) . '" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm py-0 px-1" title="Ver en el manual"><i class="bi bi-eye"></i></a> '
                . '<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 dg-editar" title="Editar"><i class="bi bi-pencil"></i></button>'
                . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    /** JSON de un artículo para precargar el modal de edición. */
    public function obtener(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();

        $id = (int) ($_GET['id'] ?? 0);
        $art = $id > 0 ? $this->model->find($id) : null;
        if ($art === null) {
            $this->json(['ok' => false, 'error' => 'El artículo no existe.'], 404);
        }

        $this->json(['ok' => true, 'articulo' => [
            'id'                      => (int) $art['id'],
            'slug'                    => (string) $art['slug'],
            'titulo'                  => (string) $art['titulo'],
            'resumen'                 => (string) ($art['resumen'] ?? ''),
            'contenido_html'          => (string) ($art['contenido_html'] ?? ''),
            'categoria'               => (string) ($art['categoria'] ?? ''),
            'ruta_modulo'             => (string) ($art['ruta_modulo'] ?? ''),
            'tipo'                    => (string) ($art['tipo'] ?? 'modulo'),
            'visibilidad'             => (string) ($art['visibilidad'] ?? 'todos'),
            'requiere_permiso_modulo' => !empty($art['requiere_permiso_modulo'])
                                          && $art['requiere_permiso_modulo'] !== 'f',
            'etiquetas'               => (string) ($art['etiquetas'] ?? ''),
            'version'                 => (string) ($art['version'] ?? ''),
            'orden'                   => (int) ($art['orden'] ?? 0),
            'estado'                  => (string) ($art['estado'] ?? 'activo'),
            'origen'                  => (string) ($art['origen'] ?? 'manual'),
            'archivo_origen'          => (string) ($art['archivo_origen'] ?? ''),
            'videos'                  => $this->model->getIdsVideos((int) $art['id']),
        ]]);
    }

    public function store(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }

        try {
            $id = $this->service->crear($this->datosDesdePost(), (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Artículo creado correctamente.', 'id' => $id]);
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
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }

        try {
            $this->service->actualizar($id, $this->datosDesdePost(), (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Artículo actualizado correctamente.']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Diagnóstico del manual: qué pantallas del sistema siguen sin documentar y
     * qué artículos tienen problemas. Ver DocumentacionDoctorService.
     */
    public function doctor(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();

        try {
            $diagnostico = (new \App\Services\DocumentacionDoctorService())->diagnostico();
            $this->json(['ok' => true, 'diagnostico' => $diagnostico]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Publica los artículos que viven como .md en docs/manual/ (contenido como
     * código). Ver DocumentacionSyncService para las reglas de sobrescritura.
     */
    public function sincronizar(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }

        try {
            $resumen = (new \App\Services\DocumentacionSyncService())
                ->sincronizar((int) $_SESSION['id_usuario']);
            $this->json(['ok' => $resumen['ok'], 'resumen' => $resumen]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $this->prepararJson();
        $this->requireAuth();
        $this->requireSuperadmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido.'], 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }

        try {
            $this->service->eliminar($id, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Artículo eliminado correctamente.']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Prepara el fragmento resaltado para insertarse con innerHTML.
     *
     * ts_headline NO escapa nada: devuelve el texto tal cual, con las marcas
     * StartSel/StopSel insertadas. Y contenido_texto es texto plano, así que
     * puede contener "<" o "&" literales del artículo (p. ej. un ejemplo de
     * código). Se escapa TODO y después se devuelve la vida solo a <mark>.
     */
    private function fragmentoSeguro(string $fragmento): string
    {
        if ($fragmento === '') {
            return '';
        }
        $seguro = htmlspecialchars($fragmento, ENT_QUOTES, 'UTF-8');
        return str_replace(['&lt;mark&gt;', '&lt;/mark&gt;'], ['<mark>', '</mark>'], $seguro);
    }

    /** @return array<string,mixed> */
    private function datosDesdePost(): array
    {
        return [
            'slug'                    => $_POST['slug'] ?? '',
            'titulo'                  => $_POST['titulo'] ?? '',
            'resumen'                 => $_POST['resumen'] ?? '',
            'contenido_html'          => $_POST['contenido_html'] ?? '',
            'categoria'               => $_POST['categoria'] ?? '',
            'ruta_modulo'             => $_POST['ruta_modulo'] ?? '',
            'tipo'                    => $_POST['tipo'] ?? 'modulo',
            'visibilidad'             => $_POST['visibilidad'] ?? 'todos',
            'requiere_permiso_modulo' => filter_var($_POST['requiere_permiso_modulo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'etiquetas'               => $_POST['etiquetas'] ?? '',
            'version'                 => $_POST['version'] ?? '',
            'orden'                   => (int) ($_POST['orden'] ?? 0),
            'estado'                  => $_POST['estado'] ?? 'activo',
            // El origen y el archivo NO se editan desde la pantalla: los fija el
            // sincronizador (Fase 2). Todo lo escrito aquí es 'manual' y por eso
            // el sincronizador nunca lo sobrescribe.
            'origen'                  => 'manual',
            // Siempre presente (aunque vacío) para que el Service sepa que la
            // pantalla sí gestiona los videos y deba reemplazarlos.
            'videos'                  => array_map('intval', (array) ($_POST['videos'] ?? [])),
        ];
    }

    private function esSuperadmin(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 0) >= 3;
    }

    private function requireSuperadmin(): void
    {
        if ($this->esSuperadmin()) {
            return;
        }
        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        if ($esAjax) {
            $this->json(['ok' => false, 'error' => 'Solo el superadministrador puede gestionar el manual.'], 403);
        }
        http_response_code(403);
        echo 'Acceso restringido al superadministrador.';
        exit;
    }

    /**
     * Deja la salida lista para responder JSON limpio: descarta cualquier warning
     * de PHP que estuviera en el búfer y evita que nuevos warnings lo contaminen.
     */
    private function prepararJson(): void
    {
        if (ob_get_level() > 0) {
            @ob_clean();
        }
        ini_set('display_errors', '0');
    }
}
