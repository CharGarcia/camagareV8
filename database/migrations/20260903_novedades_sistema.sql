-- ============================================================================
-- Novedades del sistema: avisos / noticias / nuevas implementaciones que se
-- muestran a los usuarios en una ventana modal al ingresar (solo en PC).
--
-- novedades_sistema           -> CONFIGURACIÓN GLOBAL (sin id_empresa). Las
--                                redacta y publica el superadministrador.
-- novedades_sistema_lecturas  -> quién leyó cada novedad (una fila por usuario
--                                y novedad; evidencia de "Entendido").
-- novedades_sistema_adjuntos  -> archivos descargables de cada novedad.
--
-- Idempotente: se puede correr varias veces sin error. Listo para pgAdmin.
-- ============================================================================

-- ─── 1) Novedades (global, versionadas por estado) ───────────────────────────
CREATE TABLE IF NOT EXISTS novedades_sistema (
    id              SERIAL PRIMARY KEY,
    tipo            VARCHAR(20)  NOT NULL DEFAULT 'nuevo',     -- nuevo | mejora | aviso | correccion
    titulo          VARCHAR(200) NOT NULL,
    resumen         VARCHAR(300),                              -- una línea para el listado
    contenido       TEXT         NOT NULL,                     -- HTML del editor (solo texto)
    modulo          VARCHAR(120),                              -- nombre del submódulo relacionado (se copia del catálogo)
    ruta_modulo     VARCHAR(150),                              -- ruta MVC del submódulo (submodulos_menu.ruta, ej. modulos/proformas)
    enlace          VARCHAR(500),                              -- enlace libre (https://… o ruta interna /…) que ve el usuario
    estado          VARCHAR(20)  NOT NULL DEFAULT 'borrador',  -- borrador | publicada | archivada
    publicado_at    TIMESTAMP,                                 -- se fija al publicar
    vigente_hasta   DATE         NOT NULL DEFAULT (CURRENT_DATE + 30), -- obligatoria: desde esa fecha deja de mostrarse
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_at      TIMESTAMP,
    updated_by      INTEGER,
    eliminado       BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

-- Por si la tabla ya existía de una versión anterior de este mismo archivo.
ALTER TABLE novedades_sistema ADD COLUMN IF NOT EXISTS ruta_modulo VARCHAR(150);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name = 'novedades_sistema' AND column_name = 'enlace_manual') THEN
        ALTER TABLE novedades_sistema RENAME COLUMN enlace_manual TO enlace;
        ALTER TABLE novedades_sistema ALTER COLUMN enlace TYPE VARCHAR(500);
    END IF;
END $$;
ALTER TABLE novedades_sistema ADD COLUMN IF NOT EXISTS enlace VARCHAR(500);
-- Vigencia obligatoria (las filas viejas sin fecha reciben 30 días desde hoy).
UPDATE novedades_sistema SET vigente_hasta = CURRENT_DATE + 30 WHERE vigente_hasta IS NULL;
ALTER TABLE novedades_sistema ALTER COLUMN vigente_hasta SET DEFAULT (CURRENT_DATE + 30);
ALTER TABLE novedades_sistema ALTER COLUMN vigente_hasta SET NOT NULL;

-- ─── 1b) Adjuntos descargables (PDF, Excel, imágenes, etc.) ──────────────────
CREATE TABLE IF NOT EXISTS novedades_sistema_adjuntos (
    id               SERIAL PRIMARY KEY,
    id_novedad       INTEGER      NOT NULL REFERENCES novedades_sistema (id),
    nombre_original  VARCHAR(255) NOT NULL,                    -- nombre que ve y descarga el usuario
    archivo          VARCHAR(255) NOT NULL,                    -- nombre físico en storage/novedades_sistema/
    mime_type        VARCHAR(120),
    tamano_bytes     BIGINT       NOT NULL DEFAULT 0,
    orden            INTEGER      NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by       INTEGER,
    eliminado        BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at       TIMESTAMP,
    deleted_by       INTEGER
);

CREATE INDEX IF NOT EXISTS idx_novedades_adjuntos_novedad
    ON novedades_sistema_adjuntos (id_novedad) WHERE eliminado = FALSE;

CREATE INDEX IF NOT EXISTS idx_novedades_sistema_publicadas
    ON novedades_sistema (estado, publicado_at DESC)
    WHERE eliminado = FALSE;

-- ─── 2) Lecturas: una fila por (novedad, usuario) ────────────────────────────
CREATE TABLE IF NOT EXISTS novedades_sistema_lecturas (
    id           SERIAL PRIMARY KEY,
    id_novedad   INTEGER   NOT NULL REFERENCES novedades_sistema (id),
    id_usuario   INTEGER   NOT NULL,
    id_empresa   INTEGER,                                      -- empresa activa al leer (informativo)
    leido_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip           VARCHAR(64),
    user_agent   TEXT,
    CONSTRAINT uq_novedades_lecturas UNIQUE (id_novedad, id_usuario)
);

CREATE INDEX IF NOT EXISTS idx_novedades_lecturas_usuario
    ON novedades_sistema_lecturas (id_usuario);

-- ─── 3) Limpieza: la tabla de aplazos ("recordar más tarde") ya no se usa ────
-- La regla final es: mientras una novedad siga sin leer, la tarjeta vuelve a
-- salir en cada inicio de sesión. Si una versión anterior de este archivo la
-- creó, se elimina.
DROP TABLE IF EXISTS novedades_sistema_aplazos;

-- La tarjeta de Configuración ("Novedades del sistema") la siembra sola el
-- sistema al abrir /config (ConfiguracionOpcion::asegurarOpcionesBase).
