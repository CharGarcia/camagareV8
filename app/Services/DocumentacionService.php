<?php
/**
 * Servicio Documentacion — lógica de negocio del Manual del Sistema.
 *
 * Responsabilidades (CLAUDE.md §3, §7, §8):
 *   - Resolver QUÉ puede ver cada usuario (contexto de visibilidad) y pasarlo al
 *     modelo para que el filtro se aplique en SQL, nunca en la vista.
 *   - Forzar la regla de seguridad de la documentación de configuración.
 *   - Sanear el HTML, generar anclas, índice (secciones) y texto plano de búsqueda.
 *   - Orquestar transacción y auditoría (log_sistema) en crear/actualizar/eliminar.
 *
 * Sólo el superadministrador (nivel 3) invoca las operaciones de escritura; la
 * validación de nivel se hace en el controlador.
 */

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Permisos;
use App\models\Documentacion;

class DocumentacionService
{
    private const TABLA = 'documentacion';

    private \PDO $db;
    private Documentacion $model;
    private LogSistemaService $log;

    /** Caché del contexto de visibilidad dentro del request. */
    private ?array $ctx = null;

    public function __construct()
    {
        $this->db    = \App\core\Database::getConnection();
        $this->model = new Documentacion();
        $this->log   = new LogSistemaService();
    }

    // ────────────────────────────────────────────────────────────────────
    //  Contexto de visibilidad
    // ────────────────────────────────────────────────────────────────────

    /**
     * Arma, una sola vez por request, qué puede ver el usuario de la sesión:
     * su nivel y la lista de rutas de módulo sobre las que tiene permiso de ver.
     *
     * El nivel 3 recibe 'rutas_permitidas' => null (sin restricción). Para el
     * resto se evalúa Permisos::puedeVer() sobre las rutas que realmente
     * aparecen en el manual — son unas pocas decenas y el helper cachea por
     * request, así que es una operación barata.
     *
     * @return array{nivel:int,rutas_permitidas:?array<int,string>}
     */
    public function contexto(): array
    {
        if ($this->ctx !== null) {
            return $this->ctx;
        }

        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel >= 3) {
            return $this->ctx = ['nivel' => $nivel, 'rutas_permitidas' => null];
        }

        $permitidas = [];
        foreach ($this->model->getRutasModuloDistintas() as $ruta) {
            if (Permisos::puedeVer($ruta)) {
                $permitidas[] = $ruta;
            }
        }

        return $this->ctx = ['nivel' => $nivel, 'rutas_permitidas' => $permitidas];
    }

    // ────────────────────────────────────────────────────────────────────
    //  Lectura — visor
    // ────────────────────────────────────────────────────────────────────

    /**
     * Árbol del manual agrupado por categoría, listo para el sidebar.
     *
     * @return array<int,array{categoria:string,articulos:array<int,array<string,mixed>>}>
     */
    public function arbol(): array
    {
        $grupos = [];
        foreach ($this->model->getArbol($this->contexto()) as $a) {
            $cat = trim((string) ($a['categoria'] ?? '')) ?: 'General';
            if (!isset($grupos[$cat])) {
                $grupos[$cat] = ['categoria' => $cat, 'articulos' => []];
            }
            $grupos[$cat]['articulos'][] = [
                'id'      => (int) $a['id'],
                'slug'    => (string) $a['slug'],
                'titulo'  => (string) $a['titulo'],
                'resumen' => (string) ($a['resumen'] ?? ''),
                'tipo'    => (string) ($a['tipo'] ?? 'modulo'),
            ];
        }
        return array_values($grupos);
    }

    /**
     * Manual completo agrupado por categoría, con el contenido de cada artículo.
     * Respeta la misma visibilidad que el visor: cada usuario imprime solo lo
     * que puede leer.
     *
     * @return array<int,array{categoria:string,articulos:array<int,array<string,mixed>>}>
     */
    public function manualCompleto(): array
    {
        $grupos = [];
        foreach ($this->model->getArbolConContenido($this->contexto()) as $a) {
            $cat = trim((string) ($a['categoria'] ?? '')) ?: 'General';
            if (!isset($grupos[$cat])) {
                $grupos[$cat] = ['categoria' => $cat, 'articulos' => []];
            }
            $grupos[$cat]['articulos'][] = [
                'slug'      => (string) $a['slug'],
                'titulo'    => (string) $a['titulo'],
                'resumen'   => (string) ($a['resumen'] ?? ''),
                'contenido' => (string) ($a['contenido_html'] ?? ''),
                'version'   => (string) ($a['version'] ?? ''),
            ];
        }
        return array_values($grupos);
    }

    /**
     * Busca en el manual y registra el término (los que devuelven 0 resultados
     * alimentan el backlog de "qué falta documentar").
     *
     * @return array<int,array<string,mixed>>
     */
    public function buscar(string $termino, int $limite = 25): array
    {
        $termino = trim($termino);
        if ($termino === '') {
            return [];
        }

        $rows = $this->model->buscar($termino, $this->contexto(), $limite);

        try {
            $this->model->registrarBusqueda(
                $termino,
                count($rows),
                isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null,
                !empty($_SESSION['id_empresa']) ? (int) $_SESSION['id_empresa'] : null
            );
        } catch (\Throwable $e) {
            // La analítica nunca debe romper una búsqueda.
        }

        return $rows;
    }

    /**
     * Artículo completo para el visor: contenido, índice y videos relacionados.
     * Devuelve null si no existe o el usuario no tiene derecho a verlo.
     */
    public function articulo(string $slug): ?array
    {
        $art = $this->model->getPorSlug($slug, $this->contexto());
        if ($art === null) {
            return null;
        }

        $id = (int) $art['id'];
        $art['secciones'] = $this->model->getSecciones($id);
        $art['videos']    = $this->model->getVideos($id);

        try {
            $this->model->incrementarVista($id);
        } catch (\Throwable $e) {
            // Contador de lecturas: un fallo no debe impedir leer el artículo.
        }

        return $art;
    }

    /**
     * Secciones del manual relevantes para una pregunta, para que IA Soporte
     * responda con la documentación propia del sistema además de con los
     * documentos que haya cargado la empresa.
     *
     * Va con la visibilidad del usuario de la sesión aplicada en SQL: la IA no
     * puede citar un artículo que esa persona no tiene derecho a leer.
     *
     * @return array<int,array{slug:string,titulo:string,seccion:string,ancla:string,contenido:string}>
     */
    public function buscarParaIa(string $pregunta, int $limite = 6): array
    {
        try {
            $rows = $this->model->buscarSeccionesParaIa($pregunta, $this->contexto(), $limite);
        } catch (\Throwable $e) {
            // El manual es un complemento: si falla, la IA sigue respondiendo
            // con los documentos de la empresa.
            return [];
        }

        return array_map(static fn(array $r): array => [
            'slug'      => (string) $r['slug'],
            'titulo'    => (string) $r['titulo'],
            'seccion'   => (string) ($r['seccion_titulo'] ?? ''),
            'ancla'     => (string) ($r['ancla'] ?? ''),
            'contenido' => (string) ($r['contenido'] ?? ''),
        ], $rows);
    }

    /** Ayuda contextual: slug del artículo que documenta una ruta de módulo. */
    public function slugPorRutaModulo(string $rutaModulo): ?string
    {
        $rutaModulo = trim($rutaModulo);
        return $rutaModulo === '' ? null : $this->model->getSlugPorRutaModulo($rutaModulo, $this->contexto());
    }

    /**
     * Registra el voto "¿te resultó útil?" y devuelve los contadores al día.
     *
     * @return array{utiles:int,no_utiles:int}
     */
    public function feedback(int $idDocumentacion, int $idUsuario, bool $util, ?string $comentario = null): array
    {
        try {
            $this->db->beginTransaction();
            $this->model->guardarFeedback($idDocumentacion, $idUsuario, $util, $comentario);
            $totales = $this->model->recalcularFeedback($idDocumentacion);
            $this->db->commit();
            return $totales;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new \RuntimeException('No se pudo registrar su valoración: ' . $e->getMessage(), 0, $e);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Escritura (solo superadministrador)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Crea un artículo. Devuelve el id generado.
     *
     * @param array<string,mixed> $datos
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function crear(array $datos, int $idUsuario): int
    {
        $fila = $this->prepararFila($datos);

        if ($this->model->findPorSlug($fila['slug']) !== null) {
            throw new \InvalidArgumentException(
                'Ya existe un artículo con la dirección "' . $fila['slug'] . '". Use otra.'
            );
        }

        try {
            $this->db->beginTransaction();

            $fila['created_by'] = $idUsuario;
            $id = $this->model->crear($fila);
            $this->guardarSecciones($id, $fila['__secciones']);
            if ($fila['__videos'] !== null) {
                $this->model->reemplazarVideos($id, $fila['__videos'], $idUsuario);
            }

            $this->log->registrar($idUsuario, null, 'crear', self::TABLA, $id, null, [
                'slug'        => $fila['slug'],
                'titulo'      => $fila['titulo'],
                'visibilidad' => $fila['visibilidad'],
            ]);

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new \RuntimeException('No se pudo crear el artículo: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza un artículo existente.
     *
     * @param array<string,mixed> $datos
     */
    public function actualizar(int $id, array $datos, int $idUsuario): void
    {
        $actual = $this->model->find($id);
        if ($actual === null) {
            throw new \InvalidArgumentException('El artículo no existe.');
        }

        $fila = $this->prepararFila($datos);

        // El slug debe seguir siendo único entre los artículos vivos.
        $otro = $this->model->findPorSlug($fila['slug']);
        if ($otro !== null && (int) $otro['id'] !== $id) {
            throw new \InvalidArgumentException(
                'Ya existe otro artículo con la dirección "' . $fila['slug'] . '".'
            );
        }

        try {
            $this->db->beginTransaction();

            $fila['updated_by'] = $idUsuario;
            $this->model->actualizar($id, $fila);
            $this->guardarSecciones($id, $fila['__secciones']);
            if ($fila['__videos'] !== null) {
                $this->model->reemplazarVideos($id, $fila['__videos'], $idUsuario);
            }

            $this->log->registrar($idUsuario, null, 'actualizar', self::TABLA, $id, $actual, [
                'slug'        => $fila['slug'],
                'titulo'      => $fila['titulo'],
                'visibilidad' => $fila['visibilidad'],
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new \RuntimeException('No se pudo actualizar el artículo: ' . $e->getMessage(), 0, $e);
        }
    }

    /** Eliminación lógica del artículo (CLAUDE.md §5). */
    public function eliminar(int $id, int $idUsuario): void
    {
        $actual = $this->model->find($id);
        if ($actual === null) {
            throw new \InvalidArgumentException('El artículo no existe.');
        }

        try {
            $this->db->beginTransaction();
            $this->model->eliminarLogico($id, $idUsuario);
            $this->log->registrar($idUsuario, null, 'eliminar', self::TABLA, $id, $actual, null);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new \RuntimeException('No se pudo eliminar el artículo: ' . $e->getMessage(), 0, $e);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Preparación del contenido
    // ────────────────────────────────────────────────────────────────────

    /**
     * Valida y normaliza los datos de entrada, y deriva de una vez el HTML
     * saneado con anclas, el texto plano de búsqueda y el índice de secciones.
     *
     * Devuelve la fila lista para el modelo más la clave interna '__secciones'.
     *
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function prepararFila(array $d): array
    {
        $titulo = trim((string) ($d['titulo'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('El título es obligatorio.');
        }

        $slug = $this->normalizarSlug((string) ($d['slug'] ?? '')) ?: $this->normalizarSlug($titulo);
        if ($slug === '') {
            throw new \InvalidArgumentException('La dirección (slug) del artículo es obligatoria.');
        }

        $rutaModulo = $this->limpiar($d['ruta_modulo'] ?? null);

        // Saneado primero, anclas después: así los id que generamos nosotros no
        // dependen de la configuración Attr.EnableID del purificador.
        $html = $this->sanearHtml((string) ($d['contenido_html'] ?? ''));
        [$html, $secciones] = $this->inyectarAnclas($html);

        return [
            'slug'                    => $slug,
            'titulo'                  => $titulo,
            'resumen'                 => $this->limpiar($d['resumen'] ?? null),
            'contenido_md'            => $this->limpiar($d['contenido_md'] ?? null),
            'contenido_html'          => $html,
            'contenido_texto'         => $this->textoPlano($html),
            'categoria'               => $this->limpiar($d['categoria'] ?? null),
            'ruta_modulo'             => $rutaModulo,
            'tipo'                    => $this->opcion($d['tipo'] ?? null, Documentacion::TIPOS, 'modulo'),
            'visibilidad'             => $this->visibilidadEfectiva($slug, $rutaModulo, (string) ($d['visibilidad'] ?? 'todos')),
            'requiere_permiso_modulo' => !empty($d['requiere_permiso_modulo']),
            'etiquetas'               => $this->limpiar($d['etiquetas'] ?? null),
            'version'                 => $this->limpiar($d['version'] ?? null),
            'orden'                   => (int) ($d['orden'] ?? 0),
            'estado'                  => $this->opcion($d['estado'] ?? null, Documentacion::ESTADOS, 'activo'),
            'origen'                  => ($d['origen'] ?? 'manual') === 'archivo' ? 'archivo' : 'manual',
            'archivo_origen'          => $this->limpiar($d['archivo_origen'] ?? null),
            'hash_archivo'            => $this->limpiar($d['hash_archivo'] ?? null),
            '__secciones'             => $secciones,
            // null = "no se envió la lista", que NO es lo mismo que enviarla vacía.
            // El sincronizador no manda videos, así que los enlaces hechos a mano
            // desde la pantalla sobreviven a una sincronización.
            '__videos'                => array_key_exists('videos', $d)
                                            ? array_map('intval', (array) $d['videos'])
                                            : null,
        ];
    }

    /**
     * REGLA DE SEGURIDAD: la documentación de configuración es solo para el
     * superadministrador. Se aplica por la ruta del artículo, no por lo que
     * alguien haya marcado en el formulario, para que no dependa de recordarlo.
     */
    private function visibilidadEfectiva(string $slug, ?string $rutaModulo, string $visibilidad): string
    {
        if (str_starts_with($slug, 'config/') || str_starts_with((string) $rutaModulo, 'config/')) {
            return 'superadmin';
        }
        return $this->opcion($visibilidad, Documentacion::VISIBILIDADES, 'todos');
    }

    /** Regenera el índice de secciones del artículo (datos derivados). */
    private function guardarSecciones(int $id, array $secciones): void
    {
        $this->model->borrarSecciones($id);
        foreach ($secciones as $s) {
            $this->model->insertarSeccion($id, $s);
        }
    }

    /**
     * Limpia el HTML del artículo. Usa HTMLPurifier (disponible en vendor/) con
     * la caché de definiciones desactivada: purificamos solo al guardar, así que
     * el coste es irrelevante y a cambio no hace falta ningún directorio
     * escribible en el servidor.
     */
    private function sanearHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists(\HTMLPurifier_Config::class)) {
            return $this->sanearHtmlBasico($html);
        }

        try {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed',
                'p,br,hr,strong,b,em,i,u,s,code,pre,blockquote,'
                . 'h2,h3,h4,ul,ol,li,'
                . 'a[href|title|target|rel],img[src|alt|title|width|height],'
                . 'table,thead,tbody,tr,th,td,'
                . 'span[class],div[class],mark,small,kbd'
            );
            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('HTML.TargetBlank', true);

            return (new \HTMLPurifier($config))->purify($html);
        } catch (\Throwable $e) {
            // Si el purificador falla por cualquier motivo, no guardamos HTML sin limpiar.
            return $this->sanearHtmlBasico($html);
        }
    }

    /** Respaldo mínimo: elimina lo ejecutable si HTMLPurifier no está disponible. */
    private function sanearHtmlBasico(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button)\b[^>]*/?>#i', '', $html) ?? '';
        $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';
        $html = preg_replace('#(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2#i', '$1="#"', $html) ?? '';
        return $html;
    }

    /**
     * Recorre los encabezados h2/h3 del artículo, les asigna un id único (el
     * ancla) y devuelve el HTML modificado junto al índice de secciones con su
     * texto plano — que es lo que indexa el buscador para poder llevar al
     * usuario directamente a la sección correcta.
     *
     * Solo mira los nodos de primer nivel, que es como queda el HTML generado a
     * partir de Markdown (párrafos, listas y encabezados hermanos).
     *
     * @return array{0:string,1:array<int,array<string,mixed>>}
     */
    private function inyectarAnclas(string $html): array
    {
        if (trim($html) === '' || !class_exists(\DOMDocument::class)) {
            return [$html, []];
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previo = libxml_use_internal_errors(true);
        // El prólogo XML fuerza a libxml a leer el fragmento como UTF-8; el div
        // envolvente evita que se inventen <html>/<body>.
        $ok = $doc->loadHTML(
            '<?xml encoding="UTF-8">' . '<div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if (!$ok || $doc->documentElement === null) {
            return [$html, []];
        }

        $root = $doc->documentElement;
        $secciones = [];
        $usadas = [];
        $actual = -1; // índice de la sección que se está acumulando

        foreach (iterator_to_array($root->childNodes) as $nodo) {
            if ($nodo instanceof \DOMElement && in_array(strtolower($nodo->nodeName), ['h2', 'h3'], true)) {
                $titulo = trim($nodo->textContent);
                if ($titulo === '') {
                    continue;
                }
                $ancla = $this->anclaUnica($titulo, $usadas);
                $nodo->setAttribute('id', $ancla);

                $secciones[] = [
                    'nivel'     => strtolower($nodo->nodeName) === 'h3' ? 3 : 2,
                    'titulo'    => $titulo,
                    'ancla'     => $ancla,
                    'contenido' => '',
                    'orden'     => count($secciones),
                ];
                $actual = count($secciones) - 1;
                continue;
            }

            // Todo lo que sigue a un encabezado pertenece a esa sección.
            if ($actual >= 0) {
                $texto = trim($nodo->textContent ?? '');
                if ($texto !== '') {
                    $secciones[$actual]['contenido'] = trim($secciones[$actual]['contenido'] . ' ' . $texto);
                }
            }
        }

        // innerHTML del div envolvente.
        $salida = '';
        foreach ($root->childNodes as $hijo) {
            $salida .= $doc->saveHTML($hijo);
        }

        return [$salida, $secciones];
    }

    /** Genera un ancla legible y única dentro del artículo. */
    private function anclaUnica(string $titulo, array &$usadas): string
    {
        $base = $this->normalizarSlug($titulo);
        if ($base === '') {
            $base = 'seccion';
        }
        $ancla = $base;
        $n = 2;
        while (isset($usadas[$ancla])) {
            $ancla = $base . '-' . $n;
            $n++;
        }
        $usadas[$ancla] = true;
        return $ancla;
    }

    /** Texto plano del artículo: alimenta el tsvector y los fragmentos resaltados. */
    private function textoPlano(string $html): string
    {
        $texto = strip_tags(preg_replace('#<(br|/p|/li|/h[1-6]|/tr)\s*/?>#i', ' ', $html) ?? $html);
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }

    /**
     * Normaliza un slug o un ancla: minúsculas, sin acentos y sin caracteres
     * raros. Conserva la barra para poder usar rutas como 'modulos/clientes'.
     */
    private function normalizarSlug(string $valor): string
    {
        $valor = trim(mb_strtolower($valor, 'UTF-8'));
        if ($valor === '') {
            return '';
        }

        $valor = strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);
        $valor = preg_replace('/[^a-z0-9\/_-]+/', '-', $valor) ?? '';
        $valor = preg_replace('#-{2,}#', '-', $valor) ?? '';
        $valor = preg_replace('#/{2,}#', '/', $valor) ?? '';

        return trim($valor, '-/');
    }

    private function limpiar(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    /** Devuelve $valor si está en la lista de opciones válidas; si no, $porDefecto. */
    private function opcion(mixed $valor, array $validas, string $porDefecto): string
    {
        $valor = (string) ($valor ?? '');
        return in_array($valor, $validas, true) ? $valor : $porDefecto;
    }
}
