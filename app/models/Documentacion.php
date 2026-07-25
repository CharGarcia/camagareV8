<?php
/**
 * Modelo Documentacion — Manual del Sistema (catálogo GLOBAL).
 * Tablas: documentacion, documentacion_secciones, documentacion_videos,
 *         documentacion_busquedas, documentacion_feedback (ninguna con id_empresa).
 *
 * Acceso a datos puro con PDO y consultas preparadas (CLAUDE.md §3).
 * La lógica de negocio (transacción, auditoría, saneado del HTML y la regla de
 * visibilidad de config/) vive en App\Services\DocumentacionService.
 *
 * ─── CONTEXTO DE VISIBILIDAD ($ctx) ─────────────────────────────────────────
 * Los métodos de lectura del visor reciben un $ctx que el Service arma desde la
 * sesión, y que se traduce a condiciones SQL (nunca se filtra en la vista):
 *
 *   [
 *     'nivel'            => 1|2|3,          // nivel del usuario
 *     'rutas_permitidas' => ['modulos/clientes', ...] | null,
 *   ]
 *
 * nivel 3 (superadmin) ve todo. nivel 2 ve 'todos'+'admin'. nivel 1 solo 'todos'.
 * 'rutas_permitidas' = null significa "no filtrar por módulo" (solo nivel 3);
 * un array vacío significa "no puede ver NINGÚN módulo", que es distinto.
 */

declare(strict_types=1);

namespace App\models;

class Documentacion extends BaseModel
{
    /** Columnas permitidas para ordenar el listado de gestión. */
    public const COLUMNAS_ORDEN = [
        'titulo', 'slug', 'categoria', 'tipo', 'visibilidad', 'orden',
        'estado', 'origen', 'vistas', 'updated_at', 'created_at',
    ];

    public const VISIBILIDADES = ['todos', 'admin', 'superadmin'];
    public const TIPOS         = ['modulo', 'guia', 'concepto', 'faq', 'novedad'];
    public const ESTADOS       = ['activo', 'borrador', 'obsoleto'];

    /** Opciones de ts_headline: fragmento corto con la coincidencia resaltada. */
    private const HEADLINE_OPTS =
        'StartSel=<mark>, StopSel=</mark>, MaxWords=30, MinWords=12, MaxFragments=1, FragmentDelimiter=" … "';

    // ────────────────────────────────────────────────────────────────────
    //  Lectura — visor
    // ────────────────────────────────────────────────────────────────────

    /**
     * Árbol del manual: artículos activos visibles para el usuario, ordenados
     * por categoría. La vista los agrupa; aquí solo se devuelven planos.
     *
     * @param array{nivel:int,rutas_permitidas:?array<int,string>} $ctx
     * @return array<int,array<string,mixed>>
     */
    public function getArbol(array $ctx): array
    {
        $params = [];
        $sql = "SELECT id, slug, titulo, resumen, categoria, ruta_modulo, tipo, version, orden
                FROM documentacion d
                WHERE d.eliminado = FALSE
                  AND d.estado = 'activo'"
             . $this->condicionVisibilidad($ctx, 'd', $params)
             . " ORDER BY d.categoria ASC NULLS LAST, d.orden ASC, d.titulo ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Igual que getArbol() pero trayendo el contenido: alimenta la vista del
     * manual completo (una sola página para imprimir o guardar como PDF).
     *
     * @param array{nivel:int,rutas_permitidas:?array<int,string>} $ctx
     * @return array<int,array<string,mixed>>
     */
    public function getArbolConContenido(array $ctx): array
    {
        $params = [];
        $sql = "SELECT id, slug, titulo, resumen, contenido_html, categoria, ruta_modulo, version
                FROM documentacion d
                WHERE d.eliminado = FALSE
                  AND d.estado = 'activo'"
             . $this->condicionVisibilidad($ctx, 'd', $params)
             . " ORDER BY d.categoria ASC NULLS LAST, d.orden ASC, d.titulo ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda de texto completo en español sobre artículos Y secciones.
     *
     * La tsquery se arma en modo OR (se reemplaza ' & ' por ' | ' en el texto de
     * plainto_tsquery), igual que en IaDocumentoRepository: una pregunta natural
     * como "cómo anulo una factura de venta" no debe exigir que las 5 palabras
     * aparezcan juntas; ts_rank ya prioriza al que más coincidencias tiene.
     *
     * Se devuelve UN resultado por artículo (DISTINCT ON), quedándose con el más
     * relevante — sea el artículo entero o una de sus secciones. Cuando gana una
     * sección, el resultado trae su ancla para saltar directo a ella.
     *
     * @param array{nivel:int,rutas_permitidas:?array<int,string>} $ctx
     * @return array<int,array<string,mixed>>
     */
    public function buscar(string $termino, array $ctx, int $limite = 25): array
    {
        $termino = trim($termino);
        if ($termino === '') {
            return [];
        }

        $params = [':q' => $termino];
        $vis = $this->condicionVisibilidad($ctx, 'd', $params);

        $sql = "WITH consulta AS (
                    SELECT NULLIF(replace(plainto_tsquery('spanish', :q)::text, ' & ', ' | '), '')::tsquery AS q
                )
                SELECT x.* FROM (
                    SELECT DISTINCT ON (r.id) r.*
                    FROM (
                        -- Coincidencia en el artículo
                        SELECT d.id, d.slug, d.titulo, d.categoria, d.tipo, d.ruta_modulo,
                               NULL::varchar AS seccion_titulo,
                               NULL::varchar AS ancla,
                               ts_rank(d.busqueda_tsv, c.q) AS relevancia,
                               ts_headline('spanish', COALESCE(NULLIF(d.contenido_texto, ''), d.resumen, ''),
                                           c.q, '" . self::HEADLINE_OPTS . "') AS fragmento
                        FROM documentacion d
                        CROSS JOIN consulta c
                        WHERE d.eliminado = FALSE
                          AND d.estado = 'activo'
                          AND c.q IS NOT NULL
                          AND d.busqueda_tsv @@ c.q
                          {$vis}

                        UNION ALL

                        -- Coincidencia en una sección concreta (devuelve el ancla)
                        SELECT d.id, d.slug, d.titulo, d.categoria, d.tipo, d.ruta_modulo,
                               s.titulo AS seccion_titulo,
                               s.ancla,
                               ts_rank(s.busqueda_tsv, c.q) AS relevancia,
                               ts_headline('spanish', COALESCE(s.contenido, ''),
                                           c.q, '" . self::HEADLINE_OPTS . "') AS fragmento
                        FROM documentacion_secciones s
                        INNER JOIN documentacion d ON d.id = s.id_documentacion
                        CROSS JOIN consulta c
                        WHERE d.eliminado = FALSE
                          AND d.estado = 'activo'
                          AND c.q IS NOT NULL
                          AND s.busqueda_tsv @@ c.q
                          {$vis}
                    ) r
                    ORDER BY r.id, r.relevancia DESC
                ) x
                ORDER BY x.relevancia DESC, x.titulo ASC
                LIMIT :limite";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $st->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        // Respaldo: el texto completo no encontró nada (errores de tipeo, códigos,
        // nombres de campo que el diccionario español descarta). Se cae a ILIKE.
        if ($rows === []) {
            $rows = $this->buscarIlike($termino, $ctx, $limite);
        }

        return $rows;
    }

    /**
     * Secciones del manual relevantes para una pregunta, pensadas para
     * alimentar el contexto de IA Soporte.
     *
     * A diferencia de buscar(), aquí NO se agrupa por artículo (varias secciones
     * del mismo artículo pueden ser útiles a la vez) y se devuelve el texto
     * completo de la sección, no un fragmento resaltado.
     *
     * Aplica la MISMA visibilidad que el visor: si un usuario no puede leer un
     * artículo, la IA tampoco puede citárselo.
     *
     * @param array{nivel:int,rutas_permitidas:?array<int,string>} $ctx
     * @return array<int,array<string,mixed>>
     */
    public function buscarSeccionesParaIa(string $pregunta, array $ctx, int $limite = 6): array
    {
        $pregunta = trim($pregunta);
        if ($pregunta === '') {
            return [];
        }

        $params = [':q' => $pregunta];
        $vis = $this->condicionVisibilidad($ctx, 'd', $params);

        $sql = "WITH consulta AS (
                    SELECT NULLIF(replace(plainto_tsquery('spanish', :q)::text, ' & ', ' | '), '')::tsquery AS q
                )
                SELECT d.slug, d.titulo, d.ruta_modulo,
                       s.titulo AS seccion_titulo, s.ancla, s.contenido,
                       ts_rank(s.busqueda_tsv, c.q) AS relevancia
                FROM documentacion_secciones s
                INNER JOIN documentacion d ON d.id = s.id_documentacion
                CROSS JOIN consulta c
                WHERE d.eliminado = FALSE
                  AND d.estado = 'activo'
                  AND c.q IS NOT NULL
                  AND s.busqueda_tsv @@ c.q
                  AND COALESCE(s.contenido, '') <> ''
                  {$vis}
                ORDER BY relevancia DESC, d.titulo ASC, s.orden ASC
                LIMIT :limite";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $st->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Respaldo del buscador: coincidencia literal por ILIKE. Devuelve la misma
     * forma de fila que buscar() para que el visor no tenga que distinguir.
     *
     * @param array{nivel:int,rutas_permitidas:?array<int,string>} $ctx
     * @return array<int,array<string,mixed>>
     */
    private function buscarIlike(string $termino, array $ctx, int $limite): array
    {
        $params = [':b' => '%' . $termino . '%'];
        $vis = $this->condicionVisibilidad($ctx, 'd', $params);

        $sql = "SELECT d.id, d.slug, d.titulo, d.categoria, d.tipo, d.ruta_modulo,
                       NULL::varchar AS seccion_titulo,
                       NULL::varchar AS ancla,
                       0::real AS relevancia,
                       COALESCE(d.resumen, left(COALESCE(d.contenido_texto, ''), 180)) AS fragmento
                FROM documentacion d
                WHERE d.eliminado = FALSE
                  AND d.estado = 'activo'
                  AND (d.titulo ILIKE :b OR d.etiquetas ILIKE :b OR d.resumen ILIKE :b
                       OR d.slug ILIKE :b OR d.contenido_texto ILIKE :b)
                  {$vis}
                ORDER BY d.titulo ASC
                LIMIT :limite";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $st->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Artículo completo por slug, respetando la visibilidad del usuario.
     *
     * @param array{nivel:int,rutas_permitidas:?array<int,string>} $ctx
     */
    public function getPorSlug(string $slug, array $ctx): ?array
    {
        $params = [':slug' => $slug];
        $sql = "SELECT d.id, d.slug, d.titulo, d.resumen, d.contenido_html, d.categoria,
                       d.ruta_modulo, d.tipo, d.version, d.etiquetas, d.vistas,
                       d.utiles, d.no_utiles, d.updated_at
                FROM documentacion d
                WHERE d.eliminado = FALSE
                  AND d.estado = 'activo'
                  AND d.slug = :slug"
             . $this->condicionVisibilidad($ctx, 'd', $params)
             . " LIMIT 1";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Artículo asociado a una ruta de módulo — es lo que resuelve la ayuda
     * contextual (el botón "?" del navbar abre el manual del módulo actual).
     *
     * @param array{nivel:int,rutas_permitidas:?array<int,string>} $ctx
     */
    public function getSlugPorRutaModulo(string $rutaModulo, array $ctx): ?string
    {
        $params = [':ruta' => $rutaModulo];
        $sql = "SELECT d.slug
                FROM documentacion d
                WHERE d.eliminado = FALSE
                  AND d.estado = 'activo'
                  AND d.ruta_modulo = :ruta"
             . $this->condicionVisibilidad($ctx, 'd', $params)
             . " ORDER BY d.orden ASC, d.id ASC LIMIT 1";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $slug = $st->fetchColumn();
        return $slug === false ? null : (string) $slug;
    }

    /**
     * Índice (tabla de contenido) de un artículo.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getSecciones(int $idDocumentacion): array
    {
        $st = $this->db->prepare(
            "SELECT nivel, titulo, ancla, orden
             FROM documentacion_secciones
             WHERE id_documentacion = :id
             ORDER BY orden ASC, id ASC"
        );
        $st->execute([':id' => $idDocumentacion]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Videos de ayuda enlazados al artículo (solo los activos del catálogo).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getVideos(int $idDocumentacion): array
    {
        $st = $this->db->prepare(
            "SELECT v.id, v.titulo, v.descripcion
             FROM documentacion_videos dv
             INNER JOIN videos_ayuda v ON v.id = dv.id_video
             WHERE dv.id_documentacion = :id
               AND v.eliminado = FALSE
               AND v.estado = 'activo'
             ORDER BY dv.orden ASC, v.titulo ASC"
        );
        $st->execute([':id' => $idDocumentacion]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Rutas de módulo distintas presentes en el manual (para resolver permisos una sola vez). */
    public function getRutasModuloDistintas(): array
    {
        $sql = "SELECT DISTINCT ruta_modulo
                FROM documentacion
                WHERE eliminado = FALSE
                  AND ruta_modulo IS NOT NULL
                  AND ruta_modulo <> ''";
        $st = $this->db->query($sql);
        return array_map(
            static fn(array $r): string => (string) $r['ruta_modulo'],
            $st->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    /** Contador rápido de lecturas. */
    public function incrementarVista(int $id): void
    {
        $st = $this->db->prepare(
            "UPDATE documentacion SET vistas = COALESCE(vistas, 0) + 1
             WHERE id = :id AND eliminado = FALSE"
        );
        $st->execute([':id' => $id]);
    }

    /**
     * Registra el término buscado. Las búsquedas con resultados = 0 son la lista
     * de lo que falta documentar (se muestra en la pantalla de gestión).
     */
    public function registrarBusqueda(string $termino, int $resultados, ?int $idUsuario, ?int $idEmpresa): void
    {
        $st = $this->db->prepare(
            "INSERT INTO documentacion_busquedas (termino, resultados, id_usuario, id_empresa)
             VALUES (:t, :r, :u, :e)"
        );
        $st->execute([
            ':t' => mb_substr($termino, 0, 250),
            ':r' => $resultados,
            ':u' => $idUsuario,
            ':e' => $idEmpresa,
        ]);
    }

    /** Guarda (o cambia) el voto de utilidad del usuario sobre un artículo. */
    public function guardarFeedback(int $idDocumentacion, int $idUsuario, bool $util, ?string $comentario): void
    {
        $st = $this->db->prepare(
            "INSERT INTO documentacion_feedback (id_documentacion, id_usuario, util, comentario)
             VALUES (:d, :u, :util, :c)
             ON CONFLICT (id_documentacion, id_usuario)
             DO UPDATE SET util = EXCLUDED.util,
                           comentario = EXCLUDED.comentario,
                           updated_at = CURRENT_TIMESTAMP"
        );
        $st->bindValue(':d', $idDocumentacion, \PDO::PARAM_INT);
        $st->bindValue(':u', $idUsuario, \PDO::PARAM_INT);
        $st->bindValue(':util', $util, \PDO::PARAM_BOOL);
        $st->bindValue(':c', $comentario, $comentario === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $st->execute();
    }

    /**
     * Recalcula los contadores rápidos desde la tabla de votos (fuente de verdad).
     *
     * @return array{utiles:int,no_utiles:int}
     */
    public function recalcularFeedback(int $idDocumentacion): array
    {
        $st = $this->db->prepare(
            "UPDATE documentacion d SET
                 utiles = f.si,
                 no_utiles = f.no
             FROM (
                 SELECT COUNT(*) FILTER (WHERE util)       AS si,
                        COUNT(*) FILTER (WHERE NOT util)   AS no
                 FROM documentacion_feedback WHERE id_documentacion = :d
             ) f
             WHERE d.id = :d
             RETURNING d.utiles, d.no_utiles"
        );
        $st->execute([':d' => $idDocumentacion]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: ['utiles' => 0, 'no_utiles' => 0];
        return ['utiles' => (int) $row['utiles'], 'no_utiles' => (int) $row['no_utiles']];
    }

    // ────────────────────────────────────────────────────────────────────
    //  Gestión (superadmin) — sin filtro de visibilidad: el nivel 3 ve todo
    // ────────────────────────────────────────────────────────────────────

    /**
     * Listado de la pantalla de gestión: todos los artículos no eliminados,
     * incluidos borradores y obsoletos.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(string $ordenCol = 'categoria', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'categoria';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT id, slug, titulo, resumen, categoria, ruta_modulo, tipo, visibilidad,
                       requiere_permiso_modulo, etiquetas, version, orden, estado, origen,
                       archivo_origen, vistas, utiles, no_utiles, updated_at, created_at
                FROM documentacion
                WHERE eliminado = FALSE";
        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (titulo ILIKE :b OR slug ILIKE :b OR categoria ILIKE :b
                           OR etiquetas ILIKE :b OR resumen ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }
        $sql .= " ORDER BY {$col} {$dir} NULLS LAST, orden ASC, titulo ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Un artículo por id, con todas sus columnas (para editar). */
    public function find(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM documentacion WHERE id = :id AND eliminado = FALSE LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Un artículo por slug SIN filtro de visibilidad (gestión y sincronizador). */
    public function findPorSlug(string $slug): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM documentacion WHERE slug = :slug AND eliminado = FALSE LIMIT 1"
        );
        $st->execute([':slug' => $slug]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Inserta un artículo. Retorna el id generado.
     *
     * @param array<string,mixed> $data
     */
    public function crear(array $data): int
    {
        $sql = "INSERT INTO documentacion
                    (slug, titulo, resumen, contenido_md, contenido_html, contenido_texto,
                     categoria, ruta_modulo, tipo, visibilidad, requiere_permiso_modulo,
                     etiquetas, version, orden, estado, origen, archivo_origen, hash_archivo,
                     created_by, updated_by)
                VALUES
                    (:slug, :titulo, :resumen, :contenido_md, :contenido_html, :contenido_texto,
                     :categoria, :ruta_modulo, :tipo, :visibilidad, :requiere_permiso_modulo,
                     :etiquetas, :version, :orden, :estado, :origen, :archivo_origen, :hash_archivo,
                     :created_by, :created_by)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $this->bindArticulo($st, $data);
        $st->bindValue(':created_by', $data['created_by'] ?? null, \PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    /**
     * Actualiza un artículo.
     *
     * @param array<string,mixed> $data
     */
    public function actualizar(int $id, array $data): bool
    {
        $sql = "UPDATE documentacion SET
                    slug = :slug,
                    titulo = :titulo,
                    resumen = :resumen,
                    contenido_md = :contenido_md,
                    contenido_html = :contenido_html,
                    contenido_texto = :contenido_texto,
                    categoria = :categoria,
                    ruta_modulo = :ruta_modulo,
                    tipo = :tipo,
                    visibilidad = :visibilidad,
                    requiere_permiso_modulo = :requiere_permiso_modulo,
                    etiquetas = :etiquetas,
                    version = :version,
                    orden = :orden,
                    estado = :estado,
                    origen = :origen,
                    archivo_origen = :archivo_origen,
                    hash_archivo = :hash_archivo,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $this->bindArticulo($st, $data);
        $st->bindValue(':updated_by', $data['updated_by'] ?? null, \PDO::PARAM_INT);
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        return $st->execute();
    }

    /** Eliminación lógica (CLAUDE.md §5). */
    public function eliminarLogico(int $id, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE documentacion
             SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND eliminado = FALSE"
        );
        return $st->execute([':u' => $idUsuario, ':id' => $id]);
    }

    // ── Secciones (datos derivados: se regeneran en cada guardado) ──────

    public function borrarSecciones(int $idDocumentacion): void
    {
        $st = $this->db->prepare("DELETE FROM documentacion_secciones WHERE id_documentacion = :id");
        $st->execute([':id' => $idDocumentacion]);
    }

    /**
     * @param array{nivel:int,titulo:string,ancla:string,contenido:?string,orden:int} $seccion
     */
    public function insertarSeccion(int $idDocumentacion, array $seccion): void
    {
        $st = $this->db->prepare(
            "INSERT INTO documentacion_secciones (id_documentacion, nivel, titulo, ancla, contenido, orden)
             VALUES (:d, :nivel, :titulo, :ancla, :contenido, :orden)"
        );
        $st->execute([
            ':d'         => $idDocumentacion,
            ':nivel'     => $seccion['nivel'],
            ':titulo'    => mb_substr($seccion['titulo'], 0, 250),
            ':ancla'     => mb_substr($seccion['ancla'], 0, 150),
            ':contenido' => $seccion['contenido'] ?? null,
            ':orden'     => $seccion['orden'],
        ]);
    }

    // ── Videos de ayuda enlazados ───────────────────────────────────────

    /**
     * Catálogo de videos que se pueden enlazar a un artículo (los activos).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getVideosDisponibles(): array
    {
        $sql = "SELECT id, titulo, categoria
                FROM videos_ayuda
                WHERE eliminado = FALSE AND estado = 'activo'
                ORDER BY categoria ASC NULLS FIRST, orden ASC, titulo ASC";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Ids de los videos enlazados a un artículo (para marcar el formulario). @return array<int,int> */
    public function getIdsVideos(int $idDocumentacion): array
    {
        $st = $this->db->prepare(
            "SELECT id_video FROM documentacion_videos WHERE id_documentacion = :id ORDER BY orden ASC"
        );
        $st->execute([':id' => $idDocumentacion]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Reemplaza los videos enlazados al artículo. Se ejecuta dentro de la
     * transacción del Service.
     *
     * @param array<int,int> $idsVideos
     */
    public function reemplazarVideos(int $idDocumentacion, array $idsVideos, ?int $idUsuario): void
    {
        $st = $this->db->prepare("DELETE FROM documentacion_videos WHERE id_documentacion = :id");
        $st->execute([':id' => $idDocumentacion]);

        if ($idsVideos === []) {
            return;
        }

        $ins = $this->db->prepare(
            "INSERT INTO documentacion_videos (id_documentacion, id_video, orden, created_by)
             VALUES (:d, :v, :o, :u)
             ON CONFLICT (id_documentacion, id_video) DO NOTHING"
        );
        $orden = 0;
        foreach (array_unique($idsVideos) as $idVideo) {
            $idVideo = (int) $idVideo;
            if ($idVideo <= 0) {
                continue;
            }
            $ins->execute([':d' => $idDocumentacion, ':v' => $idVideo, ':o' => $orden, ':u' => $idUsuario]);
            $orden++;
        }
    }

    // ── Apoyo al sincronizador (Fase 2) ─────────────────────────────────

    /**
     * Artículos que provienen de un archivo del repositorio. Sirve para detectar
     * cuáles quedaron huérfanos (su .md ya no existe) tras una sincronización.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getDeOrigenArchivo(): array
    {
        $sql = "SELECT id, slug, archivo_origen, estado
                FROM documentacion
                WHERE eliminado = FALSE AND origen = 'archivo'";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Marca un artículo como obsoleto: deja de verse en el manual pero no se
     * elimina, porque su contenido puede seguir siendo útil como referencia.
     */
    public function marcarObsoleto(int $id, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE documentacion
             SET estado = 'obsoleto', updated_by = :u, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND eliminado = FALSE AND estado <> 'obsoleto'"
        );
        return $st->execute([':u' => $idUsuario, ':id' => $id]);
    }

    // ── Apoyo a la gestión ──────────────────────────────────────────────

    /** Categorías ya usadas (para el datalist del modal). */
    public function getCategorias(): array
    {
        $sql = "SELECT DISTINCT categoria FROM documentacion
                WHERE eliminado = FALSE AND categoria IS NOT NULL AND categoria <> ''
                ORDER BY categoria ASC";
        return array_map(
            static fn(array $r): string => (string) $r['categoria'],
            $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    /**
     * Lo que la gente buscó y no encontró, agrupado por término. Es el backlog
     * de documentación pendiente.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getBusquedasSinResultado(int $limite = 50): array
    {
        $st = $this->db->prepare(
            "SELECT lower(termino) AS termino, COUNT(*) AS veces, MAX(created_at) AS ultima
             FROM documentacion_busquedas
             WHERE resultados = 0
             GROUP BY lower(termino)
             ORDER BY veces DESC, ultima DESC
             LIMIT :limite"
        );
        $st->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers internos
    // ────────────────────────────────────────────────────────────────────

    /**
     * Traduce el contexto de visibilidad a condiciones SQL. Devuelve el fragmento
     * a concatenar (empieza por " AND ...") y agrega sus parámetros a $params.
     *
     * Se aplica SIEMPRE en la consulta, nunca en la vista: así el buscador
     * tampoco puede devolver fragmentos de artículos que el usuario no debe ver.
     *
     * @param array{nivel:int,rutas_permitidas:?array<int,string>} $ctx
     * @param array<string,mixed> $params
     */
    private function condicionVisibilidad(array $ctx, string $alias, array &$params): string
    {
        $nivel = (int) ($ctx['nivel'] ?? 1);

        // Nivel 3 (superadministrador): acceso total, sin restricciones (CLAUDE.md §6).
        if ($nivel >= 3) {
            return '';
        }

        $cond = $nivel >= 2
            ? " AND {$alias}.visibilidad IN ('todos', 'admin')"
            : " AND {$alias}.visibilidad = 'todos'";

        // Filtro por permiso real del módulo documentado.
        $rutas = $ctx['rutas_permitidas'] ?? null;
        if (!is_array($rutas)) {
            return $cond;
        }

        $libre = "{$alias}.ruta_modulo IS NULL OR {$alias}.ruta_modulo = ''"
               . " OR {$alias}.requiere_permiso_modulo = FALSE";

        if ($rutas === []) {
            // No puede ver ningún módulo: solo artículos sin módulo o sin exigencia.
            return $cond . " AND ({$libre})";
        }

        $ph = [];
        foreach (array_values($rutas) as $i => $ruta) {
            $clave = ':rp' . $i;
            $ph[] = $clave;
            $params[$clave] = $ruta;
        }

        return $cond . " AND ({$libre} OR {$alias}.ruta_modulo IN (" . implode(', ', $ph) . '))';
    }

    /**
     * Vincula los campos comunes de crear/actualizar. requiere_permiso_modulo se
     * envía con PARAM_BOOL explícito: PostgreSQL rechaza '' o 0 en un boolean.
     *
     * @param array<string,mixed> $d
     */
    private function bindArticulo(\PDOStatement $st, array $d): void
    {
        $st->bindValue(':slug',            $d['slug']);
        $st->bindValue(':titulo',          $d['titulo']);
        $st->bindValue(':resumen',         $d['resumen'] ?? null);
        $st->bindValue(':contenido_md',    $d['contenido_md'] ?? null);
        $st->bindValue(':contenido_html',  $d['contenido_html'] ?? null);
        $st->bindValue(':contenido_texto', $d['contenido_texto'] ?? null);
        $st->bindValue(':categoria',       $d['categoria'] ?? null);
        $st->bindValue(':ruta_modulo',     $d['ruta_modulo'] ?? null);
        $st->bindValue(':tipo',            $d['tipo'] ?? 'modulo');
        $st->bindValue(':visibilidad',     $d['visibilidad'] ?? 'todos');
        $st->bindValue(':requiere_permiso_modulo', !empty($d['requiere_permiso_modulo']), \PDO::PARAM_BOOL);
        $st->bindValue(':etiquetas',       $d['etiquetas'] ?? null);
        $st->bindValue(':version',         $d['version'] ?? null);
        $st->bindValue(':orden',           (int) ($d['orden'] ?? 0), \PDO::PARAM_INT);
        $st->bindValue(':estado',          $d['estado'] ?? 'activo');
        $st->bindValue(':origen',          $d['origen'] ?? 'manual');
        $st->bindValue(':archivo_origen',  $d['archivo_origen'] ?? null);
        $st->bindValue(':hash_archivo',    $d['hash_archivo'] ?? null);
    }
}
