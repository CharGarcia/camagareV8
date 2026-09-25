-- ============================================================================
-- Módulo Alumnos: facturación de servicios desde la ficha del alumno.
--
-- Qué hace:
--   Crea alumnos_facturas: qué factura se generó desde el alumno, para qué mes
--      (periodo) y a qué cliente. Sirve para la pestaña Facturas del alumno y
--      para avisar si un mes ya se facturó. `items` guarda los productos facturados.
--   Agrega alumnos.info_adicional, alumnos_servicios.detalle y
--   alumnos_servicios.id_tarifa_iva (vacías).
-- No modifica ni borra datos existentes.
-- Idempotente: se puede ejecutar más de una vez.
-- Reversible:
--   DROP TABLE IF EXISTS alumnos_facturas;
--   ALTER TABLE alumnos DROP COLUMN IF EXISTS info_adicional;
--   ALTER TABLE alumnos_servicios DROP COLUMN IF EXISTS detalle;
--   ALTER TABLE alumnos_servicios DROP COLUMN IF EXISTS id_tarifa_iva;
-- Orden de despliegue: ejecutar ANTES de desplegar el código. Si el código llega
--   primero, el alumno se guarda igual y el botón «Generar factura» avisa que
--   falta este script.
--
-- Ejecutar en pgAdmin (Query Tool, F5) sobre la base del sistema.
-- ============================================================================

BEGIN;

-- Información adicional que llevan las facturas del alumno:
--   alumnos.info_adicional     → filas concepto/detalle del documento (jsonb).
--                                NULL = por defecto «Alumno: {alumno}».
--   alumnos_servicios.detalle  → texto de cada ítem (bajo la descripción).
--   alumnos_servicios.id_tarifa_iva → IVA elegido en la línea (tarifa_iva.id).
--                                NULL = el IVA del producto.
ALTER TABLE alumnos ADD COLUMN IF NOT EXISTS info_adicional JSONB;
ALTER TABLE alumnos_servicios ADD COLUMN IF NOT EXISTS detalle VARCHAR(300);
ALTER TABLE alumnos_servicios ADD COLUMN IF NOT EXISTS id_tarifa_iva INTEGER;

CREATE TABLE IF NOT EXISTS alumnos_facturas (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_alumno       INTEGER NOT NULL REFERENCES alumnos(id) ON DELETE CASCADE,
    id_cliente      INTEGER NOT NULL,
    id_factura      INTEGER NOT NULL,          -- ventas_cabecera.id
    periodo         DATE NOT NULL,             -- primer día del mes facturado
    importe         NUMERIC(14,2) NOT NULL DEFAULT 0,
    items           JSONB,                     -- [{id_producto}]
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_alumnos_facturas_alumno
    ON alumnos_facturas (id_empresa, id_alumno, eliminado);
CREATE INDEX IF NOT EXISTS idx_alumnos_facturas_factura
    ON alumnos_facturas (id_factura);

COMMIT;

-- Comprobación (debe salir 1 fila con tres valores no nulos; y 3 al contar columnas):
-- SELECT count(*) FROM information_schema.columns
--  WHERE (table_name = 'alumnos' AND column_name = 'info_adicional')
--     OR (table_name = 'alumnos_servicios' AND column_name IN ('detalle', 'id_tarifa_iva'));
-- SELECT to_regclass('public.alumnos_facturas') AS tabla,
--        (SELECT column_name FROM information_schema.columns WHERE table_name = 'alumnos' AND column_name = 'info_adicional') AS col_alumno,
--        (SELECT column_name FROM information_schema.columns WHERE table_name = 'alumnos_servicios' AND column_name = 'detalle') AS col_servicio;
