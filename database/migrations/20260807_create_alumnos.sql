-- ============================================================================
-- Módulo: Alumnos
-- Ruta MVC: modulos/alumnos  (catálogos: modulos/alumnos-campus, modulos/alumnos-niveles)
--
-- Registra alumnos de instituciones educativas (centro infantil, escuela,
-- colegio). El alumno se factura A NOMBRE de un Cliente ya existente
-- (representante/padre) — no se duplica identificación de facturación.
-- Campus y Nivel/Curso son catálogos propios y simples por empresa, con alta
-- rápida desde el mismo modal del alumno (mismo patrón que Categoría/Marca en
-- Productos).
--
-- Reglas del sistema (CLAUDE.md):
--   - Multiempresa: todas las tablas llevan id_empresa.
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by.
--   - alumnos_periodos permite entrar/salir/volver a matricular al mismo
--     alumno (un año estudia, otro no, y luego regresa). El listado principal
--     resuelve campus/nivel "actuales" con un JOIN al período vigente — no se
--     cachea en la cabecera, para no desincronizarse (ver §8 CLAUDE.md).
--   - Máximo UN período abierto (fecha_salida IS NULL) por alumno, forzado con
--     un índice único parcial.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Campus (catálogo simple por empresa, con alta rápida desde el modal).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alumnos_campus (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    nombre              VARCHAR(150) NOT NULL,
    direccion           VARCHAR(300),
    estado              VARCHAR(20) NOT NULL DEFAULT 'activo',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- 2. Niveles / cursos (catálogo simple por empresa, con alta rápida).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alumnos_niveles (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    nombre              VARCHAR(150) NOT NULL,
    orden               INTEGER NOT NULL DEFAULT 0,
    estado              VARCHAR(20) NOT NULL DEFAULT 'activo',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- 3. Cabecera del alumno.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alumnos (
    id                          SERIAL PRIMARY KEY,
    id_empresa                  INTEGER NOT NULL,

    -- Datos Generales
    codigo_alumno                VARCHAR(30),
    nombres                       VARCHAR(150) NOT NULL,
    apellidos                     VARCHAR(150) NOT NULL,
    tipo_identificacion           VARCHAR(2),      -- código identificador_comprador_vendedor (05 cédula, 06 pasaporte...)
    numero_identificacion         VARCHAR(20),
    fecha_nacimiento              DATE,
    sexo                          CHAR(1),         -- M=Masculino, F=Femenino, O=Otro
    nacionalidad                  VARCHAR(80),
    foto_ruta                     VARCHAR(300),
    estado_academico              VARCHAR(20) NOT NULL DEFAULT 'activo', -- activo|retirado|egresado|suspendido

    -- Representante y Facturación
    id_cliente                    INTEGER NOT NULL,   -- FK clientes(id): representante que se factura
    relacion_representante        VARCHAR(30),        -- padre|madre|tutor|abuelo|otro
    id_punto_emision              INTEGER,            -- FK empresa_punto_emision(id): serie preferida

    -- Salud y Emergencia
    tipo_sangre                   VARCHAR(5),
    alergias_condiciones          TEXT,
    contacto_emergencia_nombre    VARCHAR(150),
    contacto_emergencia_telefono  VARCHAR(30),

    observaciones                 TEXT,

    created_at                    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by                    INTEGER,
    updated_by                    INTEGER,
    eliminado                     BOOLEAN DEFAULT FALSE,
    deleted_at                    TIMESTAMP,
    deleted_by                    INTEGER
);

-- ---------------------------------------------------------------------------
-- 4. Matrícula: historial de períodos (permite salir y volver a matricular).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alumnos_periodos (
    id                  SERIAL PRIMARY KEY,
    id_alumno           INTEGER NOT NULL REFERENCES alumnos(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    id_campus           INTEGER,
    id_nivel            INTEGER,
    anio_lectivo        VARCHAR(20),
    fecha_ingreso        DATE NOT NULL,
    fecha_salida         DATE,                     -- NULL = período abierto (matrícula vigente)
    motivo_salida         VARCHAR(30),              -- retiro_voluntario|cambio_institucion|no_pago|graduacion|otro
    estado               VARCHAR(15) NOT NULL DEFAULT 'activo', -- activo|finalizado
    observacion          VARCHAR(300),
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by           INTEGER,
    updated_by           INTEGER,
    eliminado            BOOLEAN DEFAULT FALSE,
    deleted_at           TIMESTAMP,
    deleted_by           INTEGER
);

-- ---------------------------------------------------------------------------
-- 5. Horario del alumno (individual, no heredado de un curso compartido).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alumnos_horarios (
    id                  SERIAL PRIMARY KEY,
    id_alumno           INTEGER NOT NULL REFERENCES alumnos(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    dia_semana          SMALLINT NOT NULL,          -- 1=lunes .. 7=domingo
    hora_inicio         TIME NOT NULL,
    hora_fin            TIME NOT NULL,
    jornada             VARCHAR(15),                -- matutina|vespertina|nocturna
    observacion         VARCHAR(200),
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- 6. Servicios/productos predeterminados a facturar al alumno.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alumnos_servicios (
    id                  SERIAL PRIMARY KEY,
    id_alumno           INTEGER NOT NULL REFERENCES alumnos(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    id_producto         INTEGER NOT NULL,
    cantidad_default    NUMERIC(14,2) NOT NULL DEFAULT 1,
    precio_override     NUMERIC(14,2),              -- NULL = usa el precio base del producto
    frecuencia          VARCHAR(15) NOT NULL DEFAULT 'mensual', -- mensual|unica_vez|periodo
    activo              BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- 7. Documentos adjuntos del alumno.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alumnos_documentos (
    id                  SERIAL PRIMARY KEY,
    id_alumno           INTEGER NOT NULL REFERENCES alumnos(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    tipo_documento       VARCHAR(30) NOT NULL,      -- partida_nacimiento|cedula|foto_carnet|certificado_medico|contrato|otro
    nombre_archivo       VARCHAR(200),
    ruta_archivo         VARCHAR(300) NOT NULL,
    fecha_carga           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_usuario            INTEGER,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado             BOOLEAN DEFAULT FALSE,
    deleted_at            TIMESTAMP,
    deleted_by            INTEGER
);

-- ---------------------------------------------------------------------------
-- Índices
-- ---------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_alumnos_campus_empresa   ON alumnos_campus (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_niveles_empresa  ON alumnos_niveles (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_empresa          ON alumnos (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_cliente          ON alumnos (id_cliente);
CREATE INDEX IF NOT EXISTS idx_alumnos_estado_academico ON alumnos (id_empresa, estado_academico, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_periodos_alumno  ON alumnos_periodos (id_alumno, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_periodos_campus  ON alumnos_periodos (id_campus);
CREATE INDEX IF NOT EXISTS idx_alumnos_periodos_nivel   ON alumnos_periodos (id_nivel);
CREATE INDEX IF NOT EXISTS idx_alumnos_horarios_alumno  ON alumnos_horarios (id_alumno, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_servicios_alumno ON alumnos_servicios (id_alumno, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_servicios_prod   ON alumnos_servicios (id_producto);
CREATE INDEX IF NOT EXISTS idx_alumnos_documentos_alumno ON alumnos_documentos (id_alumno, eliminado);

-- Código de alumno único por empresa (cuando se asigna).
CREATE UNIQUE INDEX IF NOT EXISTS uq_alumnos_codigo
    ON alumnos (id_empresa, codigo_alumno)
    WHERE eliminado = false AND codigo_alumno IS NOT NULL;

-- Un campus/nivel no se repite por nombre dentro de la empresa.
CREATE UNIQUE INDEX IF NOT EXISTS uq_alumnos_campus_nombre
    ON alumnos_campus (id_empresa, UPPER(nombre))
    WHERE eliminado = false;

CREATE UNIQUE INDEX IF NOT EXISTS uq_alumnos_niveles_nombre
    ON alumnos_niveles (id_empresa, UPPER(nombre))
    WHERE eliminado = false;

-- Máximo un período ABIERTO (matrícula vigente) por alumno.
CREATE UNIQUE INDEX IF NOT EXISTS uq_alumnos_periodo_abierto
    ON alumnos_periodos (id_alumno)
    WHERE fecha_salida IS NULL AND eliminado = false;

-- ============================================================================
-- Registro del submódulo y permisos: se hace MANUALMENTE (regla del proyecto).
--   INSERT/UPDATE en submodulos_menu con ruta = 'modulos/alumnos',
--   'modulos/alumnos-campus' y 'modulos/alumnos-niveles', luego asignar
--   permisos en /config/permisos-modulos.
-- ============================================================================
