-- ============================================================================
-- Módulo: CHAT DE SOPORTE en vivo  (modulos/soporte-chat)
-- Fecha: 2026-07-28
-- ----------------------------------------------------------------------------
-- Soporte DEL PRODUCTO: el usuario del ERP abre una burbuja desde cualquier
-- pantalla y el equipo de CaMaGaRe responde desde una bandeja. La IA actúa como
-- COPILOTO del agente (sugiere el borrador, el humano lo edita y envía); por eso
-- no hay rol 'ia' en los mensajes: lo que se envía siempre lo firma una persona,
-- y solo se marca con sugerida_por_ia para poder medir cuánto aportó.
--
-- Reglas del sistema aplicadas (CLAUDE.md):
--   - soporte_conversaciones / soporte_mensajes son OPERATIVAS: llevan
--     id_empresa (la del usuario que consulta) + auditoría completa (§5).
--   - soporte_config es CONFIGURACIÓN GLOBAL del sistema: sin id_empresa (§4).
--
-- EXCEPCIÓN DOCUMENTADA A §4 (multiempresa): la bandeja del agente consulta
-- estas tablas SIN filtrar por id_empresa — el equipo de soporte atiende a todas
-- las empresas. El filtro que sí se aplica es de nivel: solo nivel 3 o usuarios
-- de la empresa con es_administradora_suscripciones = true. El filtro por
-- id_empresa se mantiene íntegro en el lado del usuario (su propia burbuja).
-- ============================================================================


-- --- 1. Conversaciones --------------------------------------------------------

CREATE TABLE IF NOT EXISTS soporte_conversaciones (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER      NOT NULL REFERENCES empresas(id),  -- empresa del usuario que consulta

    -- QUIÉN ATIENDE, separado de id_empresa (quién pregunta). Hoy va siempre en
    -- NULL: atienden TODAS las empresas que tengan asignado el submódulo del
    -- chat, así que no hay un destino único que registrar. La columna queda
    -- preparada para el día que el reparto sea por empresa.
    id_empresa_destino  INTEGER      REFERENCES empresas(id),

    -- Origen de la conversación. 'interno' = burbuja del ERP (único caso hoy).
    -- 'web' queda reservado para un futuro widget público en la web de un
    -- cliente, donde quien escribe no es un usuario del sistema.
    canal               VARCHAR(10)  NOT NULL DEFAULT 'interno',

    asunto              VARCHAR(200),
    estado              VARCHAR(20)  NOT NULL DEFAULT 'espera',         -- espera | atendiendo | resuelta | cerrada
    id_agente_asignado  INTEGER,                                        -- usuario del equipo de soporte que la tomó

    -- Contexto automático: desde qué pantalla se abrió la burbuja. Es lo que
    -- permite al agente saber dónde está parado el usuario sin preguntar, y a la
    -- IA acotar la búsqueda al manual de ESE módulo en vez de al manual entero.
    origen_url          TEXT,
    origen_modulo       VARCHAR(100),                                   -- ruta MVC, ej: 'modulos/compras'

    -- Estado de lectura por lado (los contadores los mantiene el Service, no un trigger).
    sin_leer_usuario    INTEGER      NOT NULL DEFAULT 0,
    sin_leer_agente     INTEGER      NOT NULL DEFAULT 0,

    -- Vista previa para la bandeja: evita una subconsulta por fila en el listado.
    ultimo_mensaje      TEXT,
    ultimo_mensaje_at   TIMESTAMP,

    -- Métricas de atención.
    primera_respuesta_at TIMESTAMP,                                     -- primer mensaje de un agente
    cerrada_at           TIMESTAMP,
    calificacion         SMALLINT,                                      -- 1..5, la pone el usuario al cerrar
    calificacion_comentario VARCHAR(500),

    -- Archivado: saca la conversación de la bandeja SIN borrar nada (el
    -- histórico se conserva y se sigue pudiendo consultar). Lo marca el cron
    -- pasados soporte_config.dias_archivar_cerradas desde el cierre.
    archivada            BOOLEAN   NOT NULL DEFAULT FALSE,
    archivada_at         TIMESTAMP,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,                                                 -- usuario que abrió la conversación
    updated_by INTEGER,
    eliminado  BOOLEAN   NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT ck_soporte_conv_estado
        CHECK (estado IN ('espera', 'atendiendo', 'resuelta', 'cerrada')),
    CONSTRAINT ck_soporte_conv_calificacion
        CHECK (calificacion IS NULL OR calificacion BETWEEN 1 AND 5),
    CONSTRAINT ck_soporte_conv_canal
        CHECK (canal IN ('interno', 'web'))
);

COMMENT ON TABLE  soporte_conversaciones IS 'Hilos de chat de soporte del ERP: un usuario consulta, el equipo responde.';
COMMENT ON COLUMN soporte_conversaciones.id_empresa    IS 'Empresa del usuario que consulta. La bandeja del agente NO filtra por esta columna (excepción a §4).';
COMMENT ON COLUMN soporte_conversaciones.origen_modulo IS 'Ruta MVC desde donde se abrió la burbuja; alimenta el contexto del copiloto de IA.';

-- Compatibilidad con una ejecución anterior de esta migración: CREATE TABLE IF
-- NOT EXISTS no añade columnas a una tabla que ya existe, así que las columnas
-- de archivado se agregan aquí. VAN ANTES DE LOS ÍNDICES a propósito: los
-- índices parciales de abajo filtran por 'archivada' y fallarían si no existe.
ALTER TABLE soporte_conversaciones ADD COLUMN IF NOT EXISTS archivada          BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE soporte_conversaciones ADD COLUMN IF NOT EXISTS archivada_at       TIMESTAMP;
ALTER TABLE soporte_conversaciones ADD COLUMN IF NOT EXISTS id_empresa_destino INTEGER REFERENCES empresas(id);
ALTER TABLE soporte_conversaciones ADD COLUMN IF NOT EXISTS canal              VARCHAR(10) NOT NULL DEFAULT 'interno';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_soporte_conv_canal') THEN
        ALTER TABLE soporte_conversaciones
            ADD CONSTRAINT ck_soporte_conv_canal CHECK (canal IN ('interno', 'web'));
    END IF;
END $$;

-- Listado del usuario: sus propias conversaciones.
CREATE INDEX IF NOT EXISTS idx_soporte_conv_usuario
    ON soporte_conversaciones (created_by, eliminado, ultimo_mensaje_at DESC);

-- Bandeja del agente: sin filtro de empresa, ordenada por actividad. El índice
-- excluye las archivadas, así la bandeja no se degrada cuando el histórico crezca.
--
-- Se hace DROP + CREATE, no CREATE IF NOT EXISTS: si una ejecución anterior de
-- esta migración ya los creó, lo hizo con la condición vieja (solo 'eliminado'),
-- y CREATE INDEX IF NOT EXISTS no redefine un índice que ya existe — se quedaría
-- sin filtrar por 'archivada' en silencio. Recrearlos es barato.
DROP INDEX IF EXISTS idx_soporte_conv_bandeja;
CREATE INDEX idx_soporte_conv_bandeja
    ON soporte_conversaciones (estado, ultimo_mensaje_at DESC)
    WHERE eliminado = FALSE AND archivada = FALSE;

-- Cola de trabajo de cada agente.
DROP INDEX IF EXISTS idx_soporte_conv_agente;
CREATE INDEX idx_soporte_conv_agente
    ON soporte_conversaciones (id_agente_asignado, estado)
    WHERE eliminado = FALSE AND archivada = FALSE;

-- Barrido diario del cron: cerradas que ya cumplieron el plazo de archivado.
CREATE INDEX IF NOT EXISTS idx_soporte_conv_archivar
    ON soporte_conversaciones (cerrada_at)
    WHERE archivada = FALSE AND eliminado = FALSE AND cerrada_at IS NOT NULL;

-- Reportes por empresa (qué empresa consulta más, y sobre qué).
CREATE INDEX IF NOT EXISTS idx_soporte_conv_empresa
    ON soporte_conversaciones (id_empresa, eliminado, estado);


-- --- 2. Mensajes --------------------------------------------------------------

CREATE TABLE IF NOT EXISTS soporte_mensajes (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER      NOT NULL REFERENCES empresas(id),
    id_conversacion INTEGER      NOT NULL REFERENCES soporte_conversaciones(id) ON DELETE CASCADE,
    rol             VARCHAR(20)  NOT NULL,                              -- usuario | agente | sistema
    contenido       TEXT         NOT NULL,

    -- Adjuntos: se sirven por un endpoint PHP que valida la pertenencia,
    -- nunca por URL directa (mismo criterio que WhatsappChatController::serveMedia).
    adjunto         VARCHAR(255),                                       -- nombre del archivo en storage
    adjunto_nombre  VARCHAR(255),                                       -- nombre original mostrado al usuario
    adjunto_mime    VARCHAR(100),
    adjunto_bytes   BIGINT,

    -- Copiloto: TRUE si el texto salió de una sugerencia de la IA (aunque el
    -- agente lo haya editado). Permite medir después qué tanto sirve.
    sugerida_por_ia BOOLEAN      NOT NULL DEFAULT FALSE,
    fuentes         JSONB,                                              -- mismo formato que ia_mensajes.fuentes; nunca el texto completo

    leido_at        TIMESTAMP,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado  BOOLEAN   NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT ck_soporte_msg_rol
        CHECK (rol IN ('usuario', 'agente', 'sistema'))
);

COMMENT ON TABLE  soporte_mensajes IS 'Mensajes de cada conversación de soporte, incluidos los avisos automáticos (rol=sistema).';
COMMENT ON COLUMN soporte_mensajes.sugerida_por_ia IS 'El contenido partió de una sugerencia del copiloto; el envío siempre lo hace una persona.';

-- Carga del hilo (la consulta más frecuente).
CREATE INDEX IF NOT EXISTS idx_soporte_msg_conversacion
    ON soporte_mensajes (id_conversacion, created_at);

-- Recuento de no leídos por lado.
CREATE INDEX IF NOT EXISTS idx_soporte_msg_sin_leer
    ON soporte_mensajes (id_conversacion, rol)
    WHERE leido_at IS NULL AND eliminado = FALSE;


-- --- 3. Configuración global del chat ------------------------------------------
-- Sin id_empresa: es configuración del sistema, no de cada empresa (§4).
-- Fila única (singleton) para no tener que resolver "cuál de todas" en el código.

CREATE TABLE IF NOT EXISTS soporte_config (
    id                        INTEGER PRIMARY KEY DEFAULT 1,

    activo                    BOOLEAN     NOT NULL DEFAULT TRUE,        -- apaga la burbuja en todo el sistema
    copiloto_activo           BOOLEAN     NOT NULL DEFAULT TRUE,        -- habilita el botón "Sugerir respuesta"

    mensaje_bienvenida        TEXT,
    mensaje_fuera_horario     TEXT,

    -- Horario de atención. dias_atencion es una lista separada por comas con la
    -- convención ISO de PostgreSQL (1=lunes … 7=domingo), la misma que devuelve
    -- EXTRACT(ISODOW FROM ...). Se guarda como texto y no como SMALLINT[] a
    -- propósito: PDO devuelve los arrays de Postgres como string '{1,2,3}' y
    -- obligaría a otra conversión en PHP, igual que pasa con los booleanos
    -- 't'/'f'. Con texto plano se resuelve con un explode(',').
    dias_atencion             VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
    hora_inicio               TIME        NOT NULL DEFAULT '08:00',
    hora_fin                  TIME        NOT NULL DEFAULT '18:00',

    -- Minutos sin atender tras los cuales el cron avisa por correo (0 = no avisar).
    minutos_alerta_sin_atender INTEGER    NOT NULL DEFAULT 15,
    correo_alertas            VARCHAR(150),

    -- Días desde el cierre tras los cuales el cron archiva la conversación
    -- (0 = no archivar nunca). Archivar no borra: solo la saca de la bandeja.
    dias_archivar_cerradas    INTEGER     NOT NULL DEFAULT 90,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,

    CONSTRAINT ck_soporte_config_unica CHECK (id = 1),
    CONSTRAINT ck_soporte_config_horario CHECK (hora_fin > hora_inicio),
    -- Al ser texto, el formato lo garantiza la BD: dígitos 1..7 separados por comas.
    CONSTRAINT ck_soporte_config_dias CHECK (dias_atencion ~ '^[1-7](,[1-7])*$')
);

COMMENT ON TABLE soporte_config IS 'Configuración global del chat de soporte (fila única). No lleva id_empresa: es del sistema.';
COMMENT ON COLUMN soporte_config.dias_atencion IS 'Días de atención separados por comas, convención ISODOW (1=lunes … 7=domingo). Texto, no array, para no añadir conversiones en PDO.';

-- Compatibilidad con una ejecución anterior (mismo motivo que en la tabla de
-- conversaciones): añadir la columna que falte y convertir dias_atencion si
-- quedó creada como SMALLINT[] en la primera versión de esta migración.
ALTER TABLE soporte_config ADD COLUMN IF NOT EXISTS dias_archivar_cerradas INTEGER NOT NULL DEFAULT 90;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name  = 'soporte_config'
          AND column_name = 'dias_atencion'
          AND data_type   = 'ARRAY'
    ) THEN
        ALTER TABLE soporte_config ALTER COLUMN dias_atencion DROP DEFAULT;
        ALTER TABLE soporte_config ALTER COLUMN dias_atencion TYPE VARCHAR(20)
              USING array_to_string(dias_atencion, ',');
        ALTER TABLE soporte_config ALTER COLUMN dias_atencion SET DEFAULT '1,2,3,4,5';

        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint WHERE conname = 'ck_soporte_config_dias'
        ) THEN
            ALTER TABLE soporte_config
                ADD CONSTRAINT ck_soporte_config_dias
                CHECK (dias_atencion ~ '^[1-7](,[1-7])*$');
        END IF;
    END IF;
END $$;

INSERT INTO soporte_config (id, mensaje_bienvenida, mensaje_fuera_horario)
VALUES (
    1,
    'Hola, ¿en qué podemos ayudarte? Cuéntanos tu consulta y te respondemos enseguida.',
    'En este momento estamos fuera del horario de atención. Deja tu consulta aquí y te responderemos apenas volvamos.'
)
ON CONFLICT (id) DO NOTHING;


-- ============================================================================
-- PENDIENTE MANUAL (no se ejecuta aquí, a propósito):
--
--   1. Registrar el submódulo en submodulos_menu con:
--        nombre_submodulo = 'Chat de Soporte'
--        ruta             = 'modulos/soporte-chat'
--      y asignar permisos en /config/permisos-modulos SOLO al equipo de soporte
--      (nivel 3 o usuarios de la empresa administradora). El resto de usuarios
--      NO necesita permiso: la burbuja es para todos y no depende del submódulo.
--
--   2. Añadir 'modulos/soporte-chat' a config/modulos_mvc.php con id_submodulo => 0.
-- ============================================================================
