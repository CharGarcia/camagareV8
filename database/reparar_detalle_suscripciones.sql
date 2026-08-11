-- ============================================================
-- Rellenar el detalle faltante de las suscripciones que quedaron
-- "vacías" en empresa 46 (estab 002) tras la primera corrida de
-- copiar_suscripciones_por_ruc.sql (cabecera copiada, 0 líneas,
-- porque los productos aún no existían ahí). Ahora que los productos
-- ya están registrados en la empresa 46, esto arma las líneas.
--
-- No toca cabeceras: solo inserta en suscripciones_detalle para las
-- suscripciones de destino que hoy tienen 0 líneas activas, tomando
-- el detalle de su original en empresa 8 (que quedó 'cancelado' en
-- la corrida anterior) y resolviendo el producto en destino por código.
--
-- Idempotente: una suscripción de destino que ya tiene alguna línea
-- ya no entra en el filtro "vacías", así que correr esto de nuevo no
-- duplica nada.
-- ============================================================

DO $$
DECLARE
    v_id_empresa_origen  INTEGER := 8;
    v_id_empresa_destino INTEGER := 46;
    v_id_usuario         INTEGER := 2;

    r_dest RECORD;
    r_det  RECORD;
    v_id_susc_origen      INTEGER;
    v_id_producto_destino INTEGER;
    v_reparadas INTEGER := 0;
    v_lineas    INTEGER := 0;
    v_sin_origen INTEGER := 0;
BEGIN
    FOR r_dest IN
        SELECT d.id, d.id_cliente, d.fecha_inicio, d.proximo_cobro, d.id_periodicidad,
               cd.identificacion AS ruc_cliente, cd.nombre AS nombre_cliente
        FROM suscripciones d
        JOIN clientes cd ON cd.id = d.id_cliente
        WHERE d.id_empresa = v_id_empresa_destino
          AND d.eliminado = false
          AND NOT EXISTS (
              SELECT 1 FROM suscripciones_detalle sd
              WHERE sd.id_suscripcion = d.id AND sd.eliminado = false
          )
    LOOP
        -- Ubicar la suscripción original (cancelada) en empresa 8 que le dio origen
        SELECT s.id INTO v_id_susc_origen
        FROM suscripciones s
        JOIN clientes co ON co.id = s.id_cliente
        WHERE s.id_empresa = v_id_empresa_origen
          AND s.estado = 'cancelado'
          AND s.fecha_inicio = r_dest.fecha_inicio
          AND s.proximo_cobro = r_dest.proximo_cobro
          AND s.id_periodicidad = r_dest.id_periodicidad
          AND regexp_replace(co.identificacion, '[^0-9]', '', 'g')
            = regexp_replace(r_dest.ruc_cliente, '[^0-9]', '', 'g')
        LIMIT 1;

        IF v_id_susc_origen IS NULL THEN
            RAISE NOTICE 'SIN ORIGEN: suscripcion destino id=% (cliente "% - %") no encontró su original cancelada en empresa %',
                r_dest.id, r_dest.ruc_cliente, r_dest.nombre_cliente, v_id_empresa_origen;
            v_sin_origen := v_sin_origen + 1;
            CONTINUE;
        END IF;

        FOR r_det IN
            SELECT sd.*, p.codigo AS codigo_producto
            FROM suscripciones_detalle sd
            JOIN productos p ON p.id = sd.id_producto
            WHERE sd.id_suscripcion = v_id_susc_origen
              AND sd.eliminado = false
        LOOP
            SELECT dp.id INTO v_id_producto_destino
            FROM productos dp
            WHERE dp.id_empresa = v_id_empresa_destino
              AND dp.eliminado = false
              AND lower(trim(dp.codigo)) = lower(trim(r_det.codigo_producto))
            LIMIT 1;

            IF v_id_producto_destino IS NULL THEN
                RAISE NOTICE '  -> sigue SIN el producto "%" en empresa % (suscripcion destino id=%)',
                    r_det.codigo_producto, v_id_empresa_destino, r_dest.id;
                CONTINUE;
            END IF;

            INSERT INTO suscripciones_detalle (
                id_suscripcion, id_empresa, id_producto, descripcion,
                cantidad, precio_unitario, porcentaje_iva, id_tarifa_iva,
                orden, created_by, created_at, eliminado
            ) VALUES (
                r_dest.id, v_id_empresa_destino, v_id_producto_destino, r_det.descripcion,
                r_det.cantidad, r_det.precio_unitario, r_det.porcentaje_iva, r_det.id_tarifa_iva,
                r_det.orden, v_id_usuario, CURRENT_TIMESTAMP, false
            );
            v_lineas := v_lineas + 1;
        END LOOP;

        v_reparadas := v_reparadas + 1;
    END LOOP;

    RAISE NOTICE '=== Suscripciones revisadas: % | líneas insertadas: % | sin original encontrado: % ===',
        v_reparadas, v_lineas, v_sin_origen;
END $$;

-- ------------------------------------------------------------
-- Verificación: no debería quedar ninguna suscripción activa en
-- destino sin al menos una línea de detalle.
-- ------------------------------------------------------------
SELECT d.id, d.id_cliente, c.nombre, c.identificacion
FROM suscripciones d
JOIN clientes c ON c.id = d.id_cliente
WHERE d.id_empresa = 46
  AND d.eliminado = false
  AND NOT EXISTS (
      SELECT 1 FROM suscripciones_detalle sd
      WHERE sd.id_suscripcion = d.id AND sd.eliminado = false
  );
