-- ============================================================================
-- Módulo: Documentación / Manual del Sistema
-- Ruta MVC: /documentacion   (controlador GLOBAL, NO operativo)
--
-- Catálogo GLOBAL: el manual es el mismo para todas las empresas, por eso
-- NINGUNA de estas tablas lleva id_empresa (CLAUDE.md §4).
--
-- Reglas del sistema aplicadas:
--   - Global: sin id_empresa (catálogo/configuración global).
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by (log_sistema vía Service).
--
-- Acceso:
--   - Leer el manual: cualquier usuario autenticado (ícono del navbar).
--   - Crear / editar / eliminar / sincronizar: SOLO superadministrador (nivel 3).
--
-- VISIBILIDAD (quién ve qué artículo). Tres capas que se aplican EN EL SQL,
-- nunca solo en la vista, para que el buscador tampoco filtre fragmentos de
-- artículos prohibidos:
--   1. documentacion.visibilidad = todos | admin | superadmin
--      (todos = nivel 1+, admin = nivel 2+, superadmin = solo nivel 3).
--   2. Los artículos cuyo slug/ruta_modulo empieza por 'config/' se fuerzan a
--      visibilidad='superadmin' desde el Service (no depende de marcarlo a mano).
--   3. requiere_permiso_modulo = TRUE → además hay que tener permiso de ver
--      ruta_modulo (App\Helpers\Permisos::puedeVer), así cada usuario ve solo la
--      documentación de los módulos que tiene asignados.
--
-- BÚSQUEDA: texto completo nativo de PostgreSQL en español (tsvector con pesos
-- + índice GIN + ts_rank + ts_headline), mismo patrón que ia_documento_chunks.
-- El tsvector se alimenta de contenido_texto (texto PLANO, no del HTML): así
-- ts_headline devuelve fragmentos legibles y no trozos de etiquetas.
--
-- Ejecutar una sola vez por base de datos.
-- ============================================================================

-- ────────────────────────────────────────────────────────────────────────────
-- 1) documentacion — el artículo (una página del manual)
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documentacion (
    id                      SERIAL PRIMARY KEY,

    -- Identificador estable del artículo. Es la clave del upsert al sincronizar
    -- desde los archivos .md y lo que viaja en los enlaces: /documentacion?slug=…
    -- Ej: 'modulos/clientes', 'config/permisos-modulos', 'guias/cerrar-el-mes'.
    slug                    VARCHAR(150) NOT NULL,

    titulo                  VARCHAR(200) NOT NULL,
    resumen                 TEXT,                          -- 1-2 líneas para el listado y los resultados

    contenido_md            TEXT,                          -- fuente Markdown (solo si vino de un archivo)
    contenido_html          TEXT,                          -- lo que se muestra (saneado con HTMLPurifier)
    contenido_texto         TEXT,                          -- texto plano: alimenta el tsvector y ts_headline

    categoria               VARCHAR(100),                  -- agrupa el árbol del visor: 'Ventas', 'Contabilidad'…
    ruta_modulo             VARCHAR(150),                  -- 'modulos/clientes' → permisos + ayuda contextual
    tipo                    VARCHAR(20)  DEFAULT 'modulo', -- modulo | guia | concepto | faq | novedad
    visibilidad             VARCHAR(20)  DEFAULT 'todos',  -- todos | admin | superadmin
    requiere_permiso_modulo BOOLEAN      DEFAULT TRUE,     -- cruzar con el permiso real de ruta_modulo

    etiquetas               TEXT,                          -- sinónimos que la gente buscaría, separados por comas
    version                 VARCHAR(20),                   -- versión del artículo (se muestra en el pie)
    orden                   INTEGER      DEFAULT 0,        -- orden dentro de la categoría
    estado                  VARCHAR(20)  DEFAULT 'activo', -- activo | borrador | obsoleto (NO reemplaza a eliminado)

    -- Origen del contenido (contenido híbrido):
    --   'archivo' → nació de docs/manual/**.md; el sincronizador PUEDE sobrescribirlo.
    --   'manual'  → se escribió desde /documentacion/gestion; el sincronizador NUNCA lo toca.
    origen                  VARCHAR(20)  DEFAULT 'manual',
    archivo_origen          VARCHAR(255),                  -- ruta relativa del .md (ej. 'modulos/clientes.md')
    hash_archivo            VARCHAR(64),                   -- sha256 del .md: detecta si cambió sin releer todo

    vistas                  INTEGER      DEFAULT 0,        -- contador rápido de lecturas
    utiles                  INTEGER      DEFAULT 0,        -- contador rápido de 👍 (fuente real: documentacion_feedback)
    no_utiles               INTEGER      DEFAULT 0,        -- contador rápido de 👎

    busqueda_tsv            TSVECTOR,                      -- lo llena el trigger de abajo

    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by              INTEGER,
    updated_by              INTEGER,
    eliminado               BOOLEAN   DEFAULT FALSE,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER,

    CONSTRAINT chk_documentacion_tipo
        CHECK (tipo IN ('modulo', 'guia', 'concepto', 'faq', 'novedad')),
    CONSTRAINT chk_documentacion_visibilidad
        CHECK (visibilidad IN ('todos', 'admin', 'superadmin')),
    CONSTRAINT chk_documentacion_estado
        CHECK (estado IN ('activo', 'borrador', 'obsoleto')),
    CONSTRAINT chk_documentacion_origen
        CHECK (origen IN ('archivo', 'manual'))
);

-- El slug es único entre los artículos VIVOS. Es índice parcial (no UNIQUE de
-- columna) a propósito: con eliminación lógica, un slug eliminado debe poder
-- volver a crearse sin chocar con el registro viejo.
CREATE UNIQUE INDEX IF NOT EXISTS uq_documentacion_slug
    ON documentacion (slug)
    WHERE eliminado = FALSE;

-- Árbol del visor: categoría → artículos visibles.
CREATE INDEX IF NOT EXISTS idx_documentacion_arbol
    ON documentacion (categoria, orden, titulo)
    WHERE eliminado = FALSE AND estado = 'activo';

-- Ayuda contextual: resolver el artículo del módulo en el que está el usuario.
CREATE INDEX IF NOT EXISTS idx_documentacion_ruta_modulo
    ON documentacion (ruta_modulo)
    WHERE eliminado = FALSE;

-- Sincronizador: localizar por archivo de origen.
CREATE INDEX IF NOT EXISTS idx_documentacion_archivo_origen
    ON documentacion (archivo_origen)
    WHERE eliminado = FALSE;

CREATE INDEX IF NOT EXISTS idx_documentacion_tsv
    ON documentacion USING GIN (busqueda_tsv);

-- Pesos de relevancia: A = título y etiquetas (lo que el usuario tiene en la
-- cabeza al buscar), B = resumen, C = cuerpo. ts_rank los pondera solo.
CREATE OR REPLACE FUNCTION documentacion_tsv_update() RETURNS trigger AS $$
BEGIN
    NEW.busqueda_tsv :=
        setweight(to_tsvector('spanish', COALESCE(NEW.titulo, '')),          'A') ||
        setweight(to_tsvector('spanish', COALESCE(NEW.etiquetas, '')),       'A') ||
        setweight(to_tsvector('spanish', COALESCE(NEW.resumen, '')),         'B') ||
        setweight(to_tsvector('spanish', COALESCE(NEW.contenido_texto, '')), 'C');
    RETURN NEW;
END
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_documentacion_tsv ON documentacion;
CREATE TRIGGER trg_documentacion_tsv
    BEFORE INSERT OR UPDATE OF titulo, etiquetas, resumen, contenido_texto
    ON documentacion
    FOR EACH ROW EXECUTE FUNCTION documentacion_tsv_update();


-- ────────────────────────────────────────────────────────────────────────────
-- 2) documentacion_secciones — un registro por cada ## / ### del artículo
--
-- Es lo que permite que el buscador devuelva "Clientes → Cómo importar desde
-- Excel" y salte al ancla, en vez de devolver el artículo entero.
--
-- Son datos DERIVADOS del artículo: el Service las borra y las vuelve a crear
-- en cada guardado, dentro de la misma transacción. Por eso no llevan
-- eliminación lógica ni auditoría propia (la del artículo padre las cubre).
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documentacion_secciones (
    id               SERIAL PRIMARY KEY,
    id_documentacion INTEGER NOT NULL REFERENCES documentacion(id) ON DELETE CASCADE,
    nivel            SMALLINT     DEFAULT 2,      -- 2 = h2, 3 = h3
    titulo           VARCHAR(250) NOT NULL,
    ancla            VARCHAR(150) NOT NULL,       -- id del encabezado en el HTML (#como-importar)
    contenido        TEXT,                        -- texto plano de la sección
    orden            INTEGER      DEFAULT 0,
    busqueda_tsv     TSVECTOR,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_documentacion_secciones_doc
    ON documentacion_secciones (id_documentacion, orden);

CREATE INDEX IF NOT EXISTS idx_documentacion_secciones_tsv
    ON documentacion_secciones USING GIN (busqueda_tsv);

CREATE OR REPLACE FUNCTION documentacion_seccion_tsv_update() RETURNS trigger AS $$
BEGIN
    NEW.busqueda_tsv :=
        setweight(to_tsvector('spanish', COALESCE(NEW.titulo, '')),    'A') ||
        setweight(to_tsvector('spanish', COALESCE(NEW.contenido, '')), 'B');
    RETURN NEW;
END
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_documentacion_seccion_tsv ON documentacion_secciones;
CREATE TRIGGER trg_documentacion_seccion_tsv
    BEFORE INSERT OR UPDATE OF titulo, contenido
    ON documentacion_secciones
    FOR EACH ROW EXECUTE FUNCTION documentacion_seccion_tsv_update();


-- ────────────────────────────────────────────────────────────────────────────
-- 3) documentacion_videos — puente con el catálogo de videos de ayuda
--    (un artículo puede mostrar uno o varios videos de /videos-ayuda)
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documentacion_videos (
    id               SERIAL PRIMARY KEY,
    id_documentacion INTEGER NOT NULL REFERENCES documentacion(id) ON DELETE CASCADE,
    id_video         INTEGER NOT NULL REFERENCES videos_ayuda(id)  ON DELETE CASCADE,
    orden            INTEGER   DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    CONSTRAINT uq_documentacion_videos UNIQUE (id_documentacion, id_video)
);

CREATE INDEX IF NOT EXISTS idx_documentacion_videos_doc
    ON documentacion_videos (id_documentacion, orden);


-- ────────────────────────────────────────────────────────────────────────────
-- 4) documentacion_busquedas — qué busca la gente en el manual
--
-- No es analítica decorativa: las búsquedas con resultados = 0 son la lista de
-- lo que HAY QUE DOCUMENTAR. La pantalla de gestión las muestra ordenadas por
-- frecuencia.
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documentacion_busquedas (
    id          BIGSERIAL PRIMARY KEY,
    termino     VARCHAR(250) NOT NULL,
    resultados  INTEGER   DEFAULT 0,
    id_usuario  INTEGER,
    id_empresa  INTEGER,                       -- empresa activa al buscar; informativo (la tabla es global)
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Consulta típica: "términos sin resultado agrupados por frecuencia".
CREATE INDEX IF NOT EXISTS idx_documentacion_busquedas_sin_resultado
    ON documentacion_busquedas (lower(termino))
    WHERE resultados = 0;

CREATE INDEX IF NOT EXISTS idx_documentacion_busquedas_fecha
    ON documentacion_busquedas (created_at DESC);


-- ────────────────────────────────────────────────────────────────────────────
-- 5) documentacion_feedback — "¿te resultó útil este artículo?"
--
-- Un voto por usuario y artículo (se puede cambiar de opinión: ON CONFLICT
-- actualiza). Los contadores rápidos viven en documentacion.utiles/no_utiles.
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documentacion_feedback (
    id               BIGSERIAL PRIMARY KEY,
    id_documentacion INTEGER NOT NULL REFERENCES documentacion(id) ON DELETE CASCADE,
    id_usuario       INTEGER NOT NULL,
    util             BOOLEAN NOT NULL,
    comentario       TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_documentacion_feedback UNIQUE (id_documentacion, id_usuario)
);

CREATE INDEX IF NOT EXISTS idx_documentacion_feedback_doc
    ON documentacion_feedback (id_documentacion, util);
