-- ============================================================================
--  MÓDULO APROBACIONES — script completo para producción (pgAdmin)
-- ============================================================================
--  Consolida todo lo necesario, en orden y de forma IDEMPOTENTE:
--    Paso 1. Tablas base del motor (por si nunca se ejecutó el script de julio)
--    Paso 2. Eliminación lógica en aprobaciones_config
--    Paso 3. Catálogo de procesos aprobables (checkpoints)
--    Paso 4. Backfill de lo que estaba configurado en modulos/empresa
--    Paso 5. Aprobación de compras (campos y normalización del estado)
--    Paso 6. Consultas de verificación
--
--  Se puede ejecutar entero de una sola vez, y volver a ejecutar sin daño.
--  Reemplaza a: 20260716h_aprobaciones_produccion_final.sql,
--               20260818_aprobaciones_fase1.sql,
--               20260819_compras_aprobacion.sql
-- ============================================================================

BEGIN;

-- ============================================================================
--  PASO 1 — Tablas base del motor
-- ============================================================================

-- La bandeja de solicitudes se retiró del diseño: si quedó de una versión
-- intermedia, se elimina. Hoy el motor es solo configuración.
DROP TABLE IF EXISTS aprobaciones_solicitudes;

-- Catálogo GLOBAL de procesos aprobables. Lo define el desarrollo: cada fila
-- necesita código que la consulte antes de ejecutar el proceso.
CREATE TABLE IF NOT EXISTS aprobaciones_tipos (
    id            SERIAL PRIMARY KEY,
    codigo        VARCHAR(60)  NOT NULL UNIQUE,
    nombre        VARCHAR(150) NOT NULL,
    descripcion   TEXT,
    modulo_ruta   VARCHAR(100),
    activo        BOOLEAN NOT NULL DEFAULT true,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Configuración POR EMPRESA: qué procesos exigen aprobación y quién aprueba.
CREATE TABLE IF NOT EXISTS aprobaciones_config (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL REFERENCES empresas(id),
    id_tipo               INTEGER NOT NULL REFERENCES aprobaciones_tipos(id),
    requiere_aprobacion   BOOLEAN NOT NULL DEFAULT false,
    usuarios_aprobadores  JSONB   NOT NULL DEFAULT '[]'::jsonb,
    umbral_monto          NUMERIC(14,2),
    created_by            INTEGER,
    updated_by            INTEGER,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_empresa, id_tipo)
);

-- Se retiró el interruptor "notificar por correo": si la aprobación está
-- activa, siempre se avisa al aprobador.
ALTER TABLE aprobaciones_config DROP COLUMN IF EXISTS notificar_correo;

CREATE INDEX IF NOT EXISTS idx_aprob_config_empresa ON aprobaciones_config (id_empresa);


-- ============================================================================
--  PASO 2 — Eliminación lógica en aprobaciones_config
-- ============================================================================
--  La fila existe   = la empresa configuró esa aprobación.
--  eliminado = true = la empresa la quitó del listado (se puede recrear).

ALTER TABLE aprobaciones_config ADD COLUMN IF NOT EXISTS eliminado  BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE aprobaciones_config ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE aprobaciones_config ADD COLUMN IF NOT EXISTS deleted_by INTEGER;

CREATE INDEX IF NOT EXISTS idx_aprob_config_empresa_vivas
    ON aprobaciones_config (id_empresa) WHERE eliminado = false;


-- ============================================================================
--  PASO 3 — Catálogo de procesos aprobables
-- ============================================================================
--  Cargas de inventario e Importaciones quedan SEPARADAS: antes compartían un
--  solo interruptor (inv_requiere_aprobacion) y no se podían configurar aparte.

INSERT INTO aprobaciones_tipos (codigo, nombre, descripcion, modulo_ruta)
VALUES
    (
        'carga_inventario',
        'Cargas de inventario',
        'Si se activa, las cargas de inventario quedan pendientes y no afectan el stock hasta ser aprobadas.',
        'modulos/cargas-inventario'
    ),
    (
        'importaciones',
        'Nacionalización de importaciones',
        'Si se activa, la nacionalización queda pendiente y no se postea al kardex hasta ser aprobada.',
        'modulos/importaciones'
    ),
    (
        'pago_bancario',
        'Lotes de pago bancario',
        'Si se activa, el lote queda pendiente y no se puede generar el archivo bancario hasta ser aprobado.',
        'modulos/transferencias'
    ),
    (
        'aprobacion_compras',
        'Registro de compras',
        'Si se activa, la factura o liquidación de compra queda pendiente y no se puede pagar, procesar su inventario ni generar su asiento hasta ser aprobada. No aplica a notas de crédito ni de débito.',
        'modulos/compras'
    )
ON CONFLICT (codigo) DO UPDATE SET
    nombre      = EXCLUDED.nombre,
    descripcion = EXCLUDED.descripcion,
    modulo_ruta = EXCLUDED.modulo_ruta,
    updated_at  = CURRENT_TIMESTAMP;

-- Limpieza de checkpoints de versiones intermedias que ya no existen.
DELETE FROM aprobaciones_config
 WHERE id_tipo IN (SELECT id FROM aprobaciones_tipos WHERE codigo IN ('registro_compras', 'pago_compras'));
DELETE FROM aprobaciones_tipos WHERE codigo IN ('registro_compras', 'pago_compras');


-- ============================================================================
--  PASO 4 — Backfill de lo configurado en modulos/empresa
-- ============================================================================
--  La configuración vieja vivía en empresa_establecimiento (por ESTABLECIMIENTO);
--  el motor trabaja por EMPRESA. Se toma un establecimiento por empresa,
--  priorizando el que tenga la aprobación ACTIVADA y, en empate, el de menor id
--  (que es el que leía el sistema).
--
--  Solo se migran las empresas que alcanzaron a definir aprobadores: activar el
--  control sin nadie que apruebe dejaba los documentos trabados sin salida.

-- 4a) Cargas de inventario e Importaciones (ambas salían del mismo flag inv_*).
WITH origen AS (
    SELECT DISTINCT ON (e.id_empresa)
           e.id_empresa,
           COALESCE(e.inv_requiere_aprobacion, false)        AS requiere,
           COALESCE(e.inv_usuarios_aprobadores, '[]'::jsonb) AS aprobadores
      FROM empresa_establecimiento e
     WHERE e.eliminado = false
     ORDER BY e.id_empresa, COALESCE(e.inv_requiere_aprobacion, false) DESC, e.id ASC
),
tipos AS (
    SELECT id FROM aprobaciones_tipos WHERE codigo IN ('carga_inventario', 'importaciones')
)
INSERT INTO aprobaciones_config
    (id_empresa, id_tipo, requiere_aprobacion, usuarios_aprobadores, umbral_monto, eliminado, created_at, updated_at)
SELECT o.id_empresa, t.id, o.requiere, o.aprobadores, NULL, false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
  FROM origen o
 CROSS JOIN tipos t
 WHERE jsonb_array_length(o.aprobadores) > 0
ON CONFLICT (id_empresa, id_tipo) DO NOTHING;  -- si ya se configuró en el módulo, manda el módulo

-- 4b) Lotes de pago bancario (transf_*).
WITH origen AS (
    SELECT DISTINCT ON (e.id_empresa)
           e.id_empresa,
           COALESCE(e.transf_requiere_aprobacion, false)        AS requiere,
           COALESCE(e.transf_usuarios_aprobadores, '[]'::jsonb) AS aprobadores
      FROM empresa_establecimiento e
     WHERE e.eliminado = false
     ORDER BY e.id_empresa, COALESCE(e.transf_requiere_aprobacion, false) DESC, e.id ASC
)
INSERT INTO aprobaciones_config
    (id_empresa, id_tipo, requiere_aprobacion, usuarios_aprobadores, umbral_monto, eliminado, created_at, updated_at)
SELECT o.id_empresa, t.id, o.requiere, o.aprobadores, NULL, false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
  FROM origen o
 CROSS JOIN (SELECT id FROM aprobaciones_tipos WHERE codigo = 'pago_bancario') t
 WHERE jsonb_array_length(o.aprobadores) > 0
ON CONFLICT (id_empresa, id_tipo) DO NOTHING;

-- NOTA: no se eliminan las columnas inv_* / transf_* de empresa_establecimiento.
-- Quedan como respaldo; ya nadie las escribe. Se pueden borrar más adelante con:
--   ALTER TABLE empresa_establecimiento
--     DROP COLUMN IF EXISTS inv_requiere_aprobacion,
--     DROP COLUMN IF EXISTS inv_usuarios_aprobadores,
--     DROP COLUMN IF EXISTS inv_notificar_correo,
--     DROP COLUMN IF EXISTS transf_requiere_aprobacion,
--     DROP COLUMN IF EXISTS transf_usuarios_aprobadores,
--     DROP COLUMN IF EXISTS transf_notificar_correo;


-- ============================================================================
--  PASO 5 — Aprobación de compras
-- ============================================================================
--  IMPORTANTE sobre compras_cabecera.estado: el sistema devolvía el literal
--  'registrado' en cada consulta en vez de leer una columna, así que el estado
--  de una compra nunca fue un dato real. Según cómo se haya creado la base, la
--  columna puede NO EXISTIR (bases creadas sin el script original del módulo) o
--  existir con el DEFAULT 'borrador' y filas históricas con ese valor.
--
--  Las dos sentencias siguientes cubren ambos casos: se crea si falta —con
--  DEFAULT 'registrado', de modo que todo lo existente queda correcto de una
--  vez— y luego se normaliza cualquier valor que no sea uno de los cuatro
--  válidos, por si la columna ya existía con 'borrador'.

ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS estado VARCHAR(20) NOT NULL DEFAULT 'registrado';

UPDATE compras_cabecera
   SET estado = 'registrado'
 WHERE estado IS NULL
    OR estado NOT IN ('registrado', 'anulado', 'pendiente_aprobacion', 'rechazada');

-- Campos del flujo de aprobación.
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS token_aprobacion VARCHAR(64);
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS aprobado_by      INTEGER;
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS aprobado_at      TIMESTAMP;
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS motivo_rechazo   TEXT;

-- El token identifica la compra desde el enlace del correo (sin sesión).
CREATE INDEX IF NOT EXISTS idx_compras_token_aprobacion
    ON compras_cabecera (token_aprobacion) WHERE token_aprobacion IS NOT NULL;

-- Las pendientes se consultan desde el listado y desde cada bloqueo.
CREATE INDEX IF NOT EXISTS idx_compras_pendientes
    ON compras_cabecera (id_empresa, estado) WHERE eliminado = false;

COMMIT;


-- ============================================================================
--  PASO 6 — Verificación (ejecutar por separado; son solo consultas)
-- ============================================================================

-- 6a) Procesos disponibles en el catálogo (deben salir 4):
SELECT codigo, nombre, modulo_ruta, activo
  FROM aprobaciones_tipos
 ORDER BY modulo_ruta, nombre;

-- 6b) Qué quedó configurado por empresa tras el backfill:
SELECT e.nombre           AS empresa,
       t.nombre           AS proceso,
       c.requiere_aprobacion AS activa,
       c.usuarios_aprobadores,
       c.umbral_monto
  FROM aprobaciones_config c
  JOIN aprobaciones_tipos t ON t.id = c.id_tipo
  JOIN empresas e           ON e.id = c.id_empresa
 WHERE c.eliminado = false
 ORDER BY e.nombre, t.nombre;

-- 6c) Estados de las compras (no debe quedar ninguna en 'borrador'):
SELECT estado, COUNT(*) AS cantidad
  FROM compras_cabecera
 WHERE eliminado = false
 GROUP BY estado
 ORDER BY cantidad DESC;

-- 6d) Las columnas del flujo de aprobación deben existir las 5:
SELECT column_name, data_type, column_default
  FROM information_schema.columns
 WHERE table_name = 'compras_cabecera'
   AND column_name IN ('estado', 'token_aprobacion', 'aprobado_by', 'aprobado_at', 'motivo_rechazo')
 ORDER BY column_name;
