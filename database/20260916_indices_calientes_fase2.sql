-- =============================================================================
-- 20260916_indices_calientes_fase2.sql
-- -----------------------------------------------------------------------------
-- Qué hace  : crea 6 índices que NO existen en producción, elegidos con los datos
--             reales de pg_stat_statements (24-08 → 16-09-2026) y comprobando antes
--             la lista de índices existentes de cada tabla (no hay equivalentes):
--   1. ventas_adicional (id_venta)       — 0 usos de índice, 142 mil recorridos
--      completos de 340 mil filas. Abrir factura, PDF/XML y la migración MySQL
--      (DELETE por factura: 98 mil veces, 34 ms cada una).
--   2. ventas_pagos (id_venta)           — 0 usos de índice, 117 mil recorridos.
--   3. compras_adicional (id_compra)     — 0 usos de índice, 100 mil recorridos.
--   4. liquidaciones_pagos (id_cabecera) — 0 usos de índice, 44 mil recorridos.
--   5. consignaciones_ventas_detalles (id_consignacion, id_producto) — lo que MÁS
--      tiempo de base consumió: ≈6,4 h en los UPDATE de fecha_caducidad de la
--      migración (MigracionMysqlService.php:2065-2066) y ≈1,2 h en un SELECT por
--      (consignación, producto). Los UPDATE no filtran `eliminado`, así que el índice
--      parcial idx_cons_ventas_det_consignacion no les sirve.
--   6. ventas_detalle (id_producto)      — "¿el producto tiene ventas?": 14 mil
--      llamadas de 25 ms recorriendo la tabla.
-- Toca datos: NO. Solo crea índices y actualiza estadísticas (ANALYZE).
-- Bloqueo   : CREATE INDEX normal bloquea INSERT/UPDATE/DELETE de esas tablas
--             mientras se construye (segundos: la mayor tiene 343 mil filas). Las
--             lecturas siguen funcionando. Ejecutar fuera de horario (noche).
-- Reversible: sí, ver REVERTIR al final.
-- Cómo      : pgAdmin → Query Tool sobre producción → F5 una sola vez.
--             Idempotente: si se ejecuta dos veces no hace nada la segunda.
-- =============================================================================

CREATE INDEX IF NOT EXISTS idx_ventas_adicional_venta
    ON ventas_adicional (id_venta);

CREATE INDEX IF NOT EXISTS idx_ventas_pagos_venta
    ON ventas_pagos (id_venta);

CREATE INDEX IF NOT EXISTS idx_compras_adicional_compra
    ON compras_adicional (id_compra);

CREATE INDEX IF NOT EXISTS idx_liquidaciones_pagos_cabecera
    ON liquidaciones_pagos (id_cabecera);

CREATE INDEX IF NOT EXISTS idx_cons_ventas_det_consignacion_producto
    ON consignaciones_ventas_detalles (id_consignacion, id_producto);

CREATE INDEX IF NOT EXISTS idx_ventas_detalle_producto
    ON ventas_detalle (id_producto);

-- Estadísticas al día para que el planificador use los índices desde ya
ANALYZE ventas_adicional;
ANALYZE ventas_pagos;
ANALYZE compras_adicional;
ANALYZE liquidaciones_pagos;
ANALYZE consignaciones_ventas_detalles;
ANALYZE ventas_detalle;


-- ── COMPROBACIÓN (seleccionar y F5) — deben salir 6 filas con valido = true ──
/*
SELECT c.relname AS indice, t.relname AS tabla, i.indisvalid AS valido,
       pg_size_pretty(pg_relation_size(c.oid)) AS tamano
FROM pg_index i
JOIN pg_class c ON c.oid = i.indexrelid
JOIN pg_class t ON t.oid = i.indrelid
WHERE c.relname IN ('idx_ventas_adicional_venta', 'idx_ventas_pagos_venta',
                    'idx_compras_adicional_compra', 'idx_liquidaciones_pagos_cabecera',
                    'idx_cons_ventas_det_consignacion_producto', 'idx_ventas_detalle_producto')
ORDER BY t.relname;
*/

-- ── USO (en 2-3 días) — idx_scan debe ser mayor que 0 en todos ──────────────
/*
SELECT relname AS tabla, indexrelname AS indice, idx_scan AS usos
FROM pg_stat_user_indexes
WHERE indexrelname IN ('idx_ventas_adicional_venta', 'idx_ventas_pagos_venta',
                       'idx_compras_adicional_compra', 'idx_liquidaciones_pagos_cabecera',
                       'idx_cons_ventas_det_consignacion_producto', 'idx_ventas_detalle_producto')
ORDER BY relname;
*/

-- ── REVERTIR (solo si hiciera falta) ────────────────────────────────────────
/*
DROP INDEX IF EXISTS idx_ventas_adicional_venta;
DROP INDEX IF EXISTS idx_ventas_pagos_venta;
DROP INDEX IF EXISTS idx_compras_adicional_compra;
DROP INDEX IF EXISTS idx_liquidaciones_pagos_cabecera;
DROP INDEX IF EXISTS idx_cons_ventas_det_consignacion_producto;
DROP INDEX IF EXISTS idx_ventas_detalle_producto;
*/

-- ── VARIANTE SIN BLOQUEO (opcional, si hubiera que hacerlo en horario laboral) ─
-- CONCURRENTLY no puede ir en un script de varias sentencias: seleccionar UNA línea
-- por vez y F5. Si una falla, queda un índice inválido: DROP INDEX y repetir.
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ventas_adicional_venta ON ventas_adicional (id_venta);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ventas_pagos_venta ON ventas_pagos (id_venta);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_compras_adicional_compra ON compras_adicional (id_compra);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_liquidaciones_pagos_cabecera ON liquidaciones_pagos (id_cabecera);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_cons_ventas_det_consignacion_producto ON consignaciones_ventas_detalles (id_consignacion, id_producto);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ventas_detalle_producto ON ventas_detalle (id_producto);


-- =============================================================================
-- PASO APARTE — apagar JIT (otro día, para medir cada cambio por separado)
-- -----------------------------------------------------------------------------
-- Por qué  : en los listados de Facturas con abonos, PostgreSQL gasta entre el 55 % y
--            el 77 % del tiempo compilando la consulta (JIT) antes de ejecutarla (en
--            Compras, ~18 %): ~2.900 s solo en las 10 consultas más afectadas. En un
--            ERP (muchas consultas medianas) JIT casi nunca compensa. No cambia
--            ningún resultado.
-- Efecto   : conexiones nuevas del usuario doadmin (la app y pgAdmin). Las que el
--            pool ya tiene abiertas lo toman al renovarse.
-- Revertir : ALTER ROLE doadmin RESET jit;
-- =============================================================================
-- ALTER ROLE doadmin SET jit = off;
