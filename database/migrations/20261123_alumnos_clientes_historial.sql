-- ============================================================================
-- Alumnos: historial de clientes a los que se factura el alumno
-- ----------------------------------------------------------------------------
-- El alumno se factura a UN cliente (alumnos.id_cliente), pero puede cambiar de
-- cliente de un mes a otro. Esta tabla guarda cada período en que un cliente
-- fue el cliente del alumno: desde (NULL = desde que existe el alumno / fecha
-- desconocida) y hasta (NULL = es el cliente actual).
--
-- Sirve para la pestaña Transacciones del alumno: muestra el historial de
-- clientes y busca las facturas migradas o hechas a mano en Facturas de Venta
-- de TODOS los clientes que tuvo el alumno, no solo del actual.
--
-- Relleno inicial: se reconstruyen los cambios de cliente ya hechos a partir de
-- log_sistema (acción «actualizar» sobre la tabla alumnos). Los alumnos que
-- nunca cambiaron de cliente no necesitan fila: el cliente actual siempre cuenta.
--
-- No modifica ni borra datos existentes.
-- Idempotente: se puede ejecutar más de una vez (el relleno solo corre si la
--   tabla está vacía).
-- Reversible:
--   DROP TABLE IF EXISTS alumnos_clientes_historial;
-- Orden de despliegue: indiferente. Sin la tabla el código sigue funcionando
--   como antes (solo el cliente actual y los de las facturas generadas).
--
-- Ejecutar en pgAdmin (Query Tool, F5) sobre la base del sistema.
-- ============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS alumnos_clientes_historial (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_alumno       INTEGER NOT NULL REFERENCES alumnos(id) ON DELETE CASCADE,
    id_cliente      INTEGER NOT NULL,
    desde           TIMESTAMP,                 -- NULL = desconocido / desde el alta
    hasta           TIMESTAMP,                 -- NULL = cliente actual
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    eliminado       BOOLEAN DEFAULT FALSE,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE INDEX IF NOT EXISTS idx_alumnos_cli_hist_alumno
    ON alumnos_clientes_historial (id_empresa, id_alumno) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_alumnos_cli_hist_cliente
    ON alumnos_clientes_historial (id_empresa, id_cliente) WHERE eliminado = false;

-- Relleno desde log_sistema: cada cambio de id_cliente cierra el período del
-- cliente anterior (desde = cambio previo o alta del alumno) y el último cambio
-- deja abierto el período del cliente actual.
INSERT INTO alumnos_clientes_historial (id_empresa, id_alumno, id_cliente, desde, hasta, created_by, updated_by)
WITH cambios AS (
    SELECT l.id_empresa,
           l.id_registro AS id_alumno,
           CASE WHEN (l.datos_anteriores::jsonb ->> 'id_cliente') ~ '^[0-9]+$'
                THEN (l.datos_anteriores::jsonb ->> 'id_cliente')::int END AS anterior,
           CASE WHEN (l.datos_nuevos::jsonb ->> 'id_cliente') ~ '^[0-9]+$'
                THEN (l.datos_nuevos::jsonb ->> 'id_cliente')::int END AS nuevo,
           l.created_at,
           l.id_usuario
    FROM log_sistema l
    WHERE l.tabla_afectada = 'alumnos'
      AND l.accion = 'actualizar'
      AND l.id_registro IS NOT NULL
),
reales AS (
    SELECT c.*,
           LAG(c.created_at) OVER (PARTITION BY c.id_empresa, c.id_alumno ORDER BY c.created_at) AS cambio_previo,
           ROW_NUMBER()      OVER (PARTITION BY c.id_empresa, c.id_alumno ORDER BY c.created_at DESC) AS desde_el_final
    FROM cambios c
    JOIN alumnos a ON a.id = c.id_alumno AND a.id_empresa = c.id_empresa
    WHERE c.anterior IS NOT NULL AND c.nuevo IS NOT NULL AND c.anterior <> c.nuevo
)
-- Períodos cerrados: el cliente anterior de cada cambio.
SELECT r.id_empresa, r.id_alumno, r.anterior,
       COALESCE(r.cambio_previo, a.created_at), r.created_at, r.id_usuario, r.id_usuario
FROM reales r
JOIN alumnos a ON a.id = r.id_alumno
WHERE NOT EXISTS (SELECT 1 FROM alumnos_clientes_historial)
UNION ALL
-- Período abierto: el cliente del último cambio, si sigue siendo el del alumno.
SELECT r.id_empresa, r.id_alumno, r.nuevo, r.created_at, NULL, r.id_usuario, r.id_usuario
FROM reales r
JOIN alumnos a ON a.id = r.id_alumno AND a.id_cliente = r.nuevo
WHERE r.desde_el_final = 1
  AND NOT EXISTS (SELECT 1 FROM alumnos_clientes_historial);

COMMIT;
