-- ============================================================
-- Revertir la corrida de copiar_suscripciones_por_ruc.sql que dejó
-- 49 suscripciones "vacías" en empresa 46 (estab 002): la cabecera
-- se copió pero NINGUNA línea de detalle, porque los códigos de
-- producto (FE2023, FE20231, 202301, S002) no existen en esa empresa.
--
-- Este script:
--   1) Reactiva ('activo') en empresa 8 (estab 001) las suscripciones
--      que quedaron 'cancelado' y que tienen su contraparte vacía en 46.
--   2) Borra esas suscripciones vacías en empresa 46 (identificadas por
--      no tener NINGUNA línea en suscripciones_detalle).
--
-- Antes de correr: revisar el SELECT de verificación (primero, comentado
-- como DO NOTICE) para confirmar que son exactamente 49 filas.
-- ============================================================

DO $$
DECLARE
    v_id_empresa_origen  INTEGER := 8;
    v_id_empresa_destino INTEGER := 46;
    v_id_usuario         INTEGER := 2;
    v_revertidas INTEGER := 0;
    v_borradas   INTEGER := 0;
BEGIN
    -- 1) Reactivar en origen las que tienen contraparte vacía en destino
    WITH destino_vacias AS (
        SELECT d.id, d.id_cliente, d.fecha_inicio, d.proximo_cobro, d.id_periodicidad
        FROM suscripciones d
        WHERE d.id_empresa = v_id_empresa_destino
          AND d.eliminado = false
          AND NOT EXISTS (
              SELECT 1 FROM suscripciones_detalle sd
              WHERE sd.id_suscripcion = d.id AND sd.eliminado = false
          )
    ),
    origen_a_revertir AS (
        SELECT s.id
        FROM suscripciones s
        JOIN clientes co ON co.id = s.id_cliente
        JOIN destino_vacias dv ON dv.fecha_inicio = s.fecha_inicio
                               AND dv.proximo_cobro = s.proximo_cobro
                               AND dv.id_periodicidad = s.id_periodicidad
        JOIN clientes cd ON cd.id = dv.id_cliente
                         AND regexp_replace(cd.identificacion, '[^0-9]', '', 'g')
                           = regexp_replace(co.identificacion, '[^0-9]', '', 'g')
        WHERE s.id_empresa = v_id_empresa_origen
          AND s.estado = 'cancelado'
    )
    UPDATE suscripciones
    SET estado = 'activo', updated_by = v_id_usuario, updated_at = CURRENT_TIMESTAMP
    WHERE id IN (SELECT id FROM origen_a_revertir);
    GET DIAGNOSTICS v_revertidas = ROW_COUNT;

    -- 2) Borrar las vacías en destino
    DELETE FROM suscripciones d
    WHERE d.id_empresa = v_id_empresa_destino
      AND d.eliminado = false
      AND NOT EXISTS (
          SELECT 1 FROM suscripciones_detalle sd
          WHERE sd.id_suscripcion = d.id AND sd.eliminado = false
      );
    GET DIAGNOSTICS v_borradas = ROW_COUNT;

    RAISE NOTICE '=== Revertidas a activo en empresa %: % | Borradas en empresa %: % ===',
        v_id_empresa_origen, v_revertidas, v_id_empresa_destino, v_borradas;
END $$;

-- ------------------------------------------------------------
-- Diagnóstico: códigos de producto que usan las suscripciones
-- activas de la empresa 8 y que NO existen (o están eliminados)
-- en la empresa 46. Úsalo para saber qué replicar en Productos.
-- ------------------------------------------------------------
SELECT DISTINCT p.codigo, p.nombre, p.precio_base
FROM suscripciones s
JOIN suscripciones_detalle sd ON sd.id_suscripcion = s.id AND sd.eliminado = false
JOIN productos p ON p.id = sd.id_producto
WHERE s.id_empresa = 8
  AND s.eliminado = false
  AND s.estado = 'activo'
  AND NOT EXISTS (
      SELECT 1 FROM productos dp
      WHERE dp.id_empresa = 46
        AND dp.eliminado = false
        AND lower(trim(dp.codigo)) = lower(trim(p.codigo))
  )
ORDER BY p.codigo;
