-- ============================================================================
--  Índices para el reporte de Consignaciones (modulos/reporte_inventarios,
--  pestaña "Consignaciones") y, de paso, para los listados de consignaciones.
--
--  PROBLEMA QUE RESUELVEN
--  ----------------------
--  La consulta de saldos calcula, POR CADA LÍNEA de consignación:
--    1) cuánto se retornó   -> retornos_cv_detalles            (índice ya existía)
--    2) cuánto se facturó   -> consignaciones_facturas_detalles(índice ya existía)
--    3) el costo unitario   -> inventario_kardex               (NO había índice)
--  El punto 3 es el que mata el reporte: sin índice por referencia, cada línea
--  recorre todos los movimientos de kardex de ese producto. Con 2.000 líneas y
--  productos con miles de movimientos son decenas de millones de filas leídas.
--
--  Además consignaciones_ventas no tenía índice por empresa/fecha ni por
--  responsable de traslado, y consignaciones_ventas_detalles no tenía ninguno
--  fuera de la PK: el cruce cabecera-detalle se resolvía leyendo la tabla
--  completa (de TODAS las empresas) y ordenándola en memoria.
--
--  CÓMO EJECUTARLO EN PRODUCCIÓN
--  -----------------------------
--  Tal como está, cada CREATE INDEX bloquea las escrituras de esa tabla mientras
--  se construye. En inventario_kardex (la tabla grande) eso puede durar. Para
--  crearlo sin bloquear, ejecutar ESA sentencia suelta (fuera de transacción y
--  sin BEGIN/COMMIT) cambiando "CREATE INDEX IF NOT EXISTS" por
--  "CREATE INDEX CONCURRENTLY IF NOT EXISTS".
--
--  Es idempotente: se puede volver a ejecutar sin efecto.
-- ============================================================================

-- ── 0. COMPROBACIÓN PREVIA (opcional pero recomendada) ───────────────────────
--    El repositorio ahora filtra también por consignaciones_ventas_detalles.id_empresa
--    (antes solo filtraba la cabecera). Esta consulta debe devolver 0. Si devuelve
--    más de 0, hay detalles con la empresa mal grabada y habría que corregirlos
--    antes de desplegar el código, o esas líneas dejarían de verse en el reporte.
--
--    SELECT count(*) AS detalles_con_empresa_inconsistente
--      FROM consignaciones_ventas_detalles d
--      JOIN consignaciones_ventas c ON c.id = d.id_consignacion
--     WHERE d.id_empresa IS DISTINCT FROM c.id_empresa;
--
--    Corrección, si hiciera falta:
--    UPDATE consignaciones_ventas_detalles d
--       SET id_empresa = c.id_empresa
--      FROM consignaciones_ventas c
--     WHERE c.id = d.id_consignacion
--       AND d.id_empresa IS DISTINCT FROM c.id_empresa;

-- ── 1. Kardex: búsqueda del costo por documento de origen ────────────────────
--    Lo usa la subconsulta de costo_unitario del reporte y cualquier otra
--    consulta que resuelva "movimientos de ESTE documento".
CREATE INDEX IF NOT EXISTS idx_kardex_referencia
    ON inventario_kardex (id_empresa, referencia_tipo, referencia_id, id_producto)
    WHERE eliminado = false;

-- ── 2. Cabecera de consignaciones ────────────────────────────────────────────
--    Listado por empresa ordenado por fecha (el orden por defecto del reporte).
CREATE INDEX IF NOT EXISTS idx_cons_ventas_empresa_fecha
    ON consignaciones_ventas (id_empresa, fecha_emision DESC)
    WHERE eliminado = false;

--    Filtro "Responsable traslado" de la pestaña.
CREATE INDEX IF NOT EXISTS idx_cons_ventas_responsable
    ON consignaciones_ventas (id_empresa, id_responsable_traslado)
    WHERE eliminado = false;

--    Filtros "Cliente" y "Vendedor" de la pestaña.
CREATE INDEX IF NOT EXISTS idx_cons_ventas_cliente
    ON consignaciones_ventas (id_empresa, id_cliente)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_cons_ventas_vendedor
    ON consignaciones_ventas (id_empresa, id_vendedor)
    WHERE eliminado = false;

-- ── 3. Detalle de consignaciones ─────────────────────────────────────────────
--    Cruce cabecera -> detalle (no existía ningún índice fuera de la PK).
CREATE INDEX IF NOT EXISTS idx_cons_ventas_det_consignacion
    ON consignaciones_ventas_detalles (id_consignacion)
    WHERE eliminado = false;

--    Entrada por empresa + filtros de producto / bodega de la pestaña.
CREATE INDEX IF NOT EXISTS idx_cons_ventas_det_empresa_producto
    ON consignaciones_ventas_detalles (id_empresa, id_producto)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_cons_ventas_det_empresa_bodega
    ON consignaciones_ventas_detalles (id_empresa, id_bodega)
    WHERE eliminado = false;

-- ── 4. Refrescar estadísticas para que el planificador use lo nuevo ──────────
ANALYZE inventario_kardex;
ANALYZE consignaciones_ventas;
ANALYZE consignaciones_ventas_detalles;
