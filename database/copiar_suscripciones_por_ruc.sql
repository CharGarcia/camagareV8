-- ============================================================
-- Copiar suscripciones de una empresa (controladora) a otra,
-- resolviendo el cliente y los productos por RUC/código en destino.
-- ============================================================
-- Contexto: en `suscripciones`, id_empresa = empresa CONTROLADORA
-- (quien administra/cobra) e id_cliente apunta a `clientes`, tabla
-- particionada por empresa. Por eso no se puede copiar id_cliente
-- ni id_producto tal cual: se vuelven a resolver en destino por
-- clientes.identificacion (RUC) y productos.codigo, igual que hace
-- la carga masiva de suscripciones.
--
-- NO se copian (quedan en blanco / default en destino):
--   - Datos de cobro con tarjeta: kushki_*, payphone_*, pasarela_tarjeta,
--     id_nuvei_tarjeta (son tokens/credenciales del gateway atados a la
--     empresa origen; la suscripción destino queda en forma_cobro
--     original pero SIN método de pago vinculado -> reconfigurar en UI).
--   - suscripciones_pagos / suscripciones_notificaciones (historial de
--     cobros y avisos): están ligados a facturas/recibos de la empresa
--     origen, no tiene sentido moverlos a otra empresa.
--
-- Filas que NO tienen contraparte en destino (cliente sin ese RUC, o
-- producto sin ese código) se omiten y se reportan con RAISE NOTICE:
-- revisar el mensaje al final antes de dar por hecho que copió todo.
--
-- Este caso concreto: RUC 1793204600001, establecimiento 001 -> 002
-- (empresas hermanas, mismo RUC), solo suscripciones en estado 'activo'.
-- Las suscripciones de origen que SÍ se copiaron quedan en estado
-- 'cancelado' (las omitidas por no tener cliente/RUC en destino se
-- dejan tal cual, para no cancelar algo que no tiene equivalente).
-- ============================================================

DO $$
DECLARE
    v_ruc            TEXT    := '1793204600001'; -- <<< EDITAR si aplica a otro RUC
    v_estab_origen   TEXT    := '001';            -- <<< EDITAR establecimiento origen
    v_estab_destino  TEXT    := '002';            -- <<< EDITAR establecimiento destino
    v_id_usuario     INTEGER := 2;                 -- id_usuario que queda como created_by

    v_id_empresa_origen  INTEGER;
    v_id_empresa_destino INTEGER;
    r_susc      RECORD;
    r_det       RECORD;
    v_id_cliente_destino INTEGER;
    v_id_producto_destino INTEGER;
    v_nueva_id  INTEGER;
    v_copiadas  INTEGER := 0;
    v_omitidas  INTEGER := 0;
BEGIN
    IF v_id_usuario = 0 THEN
        RAISE EXCEPTION 'Editar v_id_usuario antes de ejecutar';
    END IF;

    SELECT id INTO v_id_empresa_origen
    FROM empresas
    WHERE eliminado = false
      AND regexp_replace(ruc, '[^0-9]', '', 'g') = regexp_replace(v_ruc, '[^0-9]', '', 'g')
      AND lpad(trim(establecimiento), 3, '0') = v_estab_origen
    LIMIT 1;

    SELECT id INTO v_id_empresa_destino
    FROM empresas
    WHERE eliminado = false
      AND regexp_replace(ruc, '[^0-9]', '', 'g') = regexp_replace(v_ruc, '[^0-9]', '', 'g')
      AND lpad(trim(establecimiento), 3, '0') = v_estab_destino
    LIMIT 1;

    IF v_id_empresa_origen IS NULL THEN
        RAISE EXCEPTION 'No se encontró empresa con RUC % establecimiento %', v_ruc, v_estab_origen;
    END IF;
    IF v_id_empresa_destino IS NULL THEN
        RAISE EXCEPTION 'No se encontró empresa con RUC % establecimiento %', v_ruc, v_estab_destino;
    END IF;

    RAISE NOTICE 'Origen: empresa id=% (estab %) -> Destino: empresa id=% (estab %)',
        v_id_empresa_origen, v_estab_origen, v_id_empresa_destino, v_estab_destino;

    FOR r_susc IN
        SELECT s.*, c.identificacion AS ruc_cliente, c.nombre AS nombre_cliente
        FROM suscripciones s
        JOIN clientes c ON c.id = s.id_cliente
        WHERE s.id_empresa = v_id_empresa_origen
          AND s.eliminado = false
          AND s.estado = 'activo'
    LOOP
        -- Resolver cliente en destino por RUC (mismo criterio que
        -- SuscripcionesRepository::getResumenPorControladoraYRuc)
        SELECT dc.id INTO v_id_cliente_destino
        FROM clientes dc
        WHERE dc.id_empresa = v_id_empresa_destino
          AND dc.eliminado = false
          AND regexp_replace(dc.identificacion, '[^0-9]', '', 'g')
            = regexp_replace(r_susc.ruc_cliente, '[^0-9]', '', 'g')
        LIMIT 1;

        IF v_id_cliente_destino IS NULL THEN
            RAISE NOTICE 'OMITIDA suscripcion id=% (cliente "% - %"): no existe cliente con ese RUC en empresa destino %',
                r_susc.id, r_susc.ruc_cliente, r_susc.nombre_cliente, v_id_empresa_destino;
            v_omitidas := v_omitidas + 1;
            CONTINUE;
        END IF;

        INSERT INTO suscripciones (
            id_empresa, id_cliente, id_periodicidad,
            fecha_inicio, fecha_fin, proximo_cobro,
            forma_cobro, estado, tipo_comprobante,
            observaciones, info_adicional,
            intentos_fallidos, created_by, created_at, eliminado
        ) VALUES (
            v_id_empresa_destino, v_id_cliente_destino, r_susc.id_periodicidad,
            r_susc.fecha_inicio, r_susc.fecha_fin, r_susc.proximo_cobro,
            r_susc.forma_cobro, r_susc.estado, r_susc.tipo_comprobante,
            r_susc.observaciones, r_susc.info_adicional,
            0, v_id_usuario, CURRENT_TIMESTAMP, false
        )
        RETURNING id INTO v_nueva_id;

        FOR r_det IN
            SELECT sd.*, p.codigo AS codigo_producto
            FROM suscripciones_detalle sd
            JOIN productos p ON p.id = sd.id_producto
            WHERE sd.id_suscripcion = r_susc.id
              AND sd.eliminado = false
        LOOP
            SELECT dp.id INTO v_id_producto_destino
            FROM productos dp
            WHERE dp.id_empresa = v_id_empresa_destino
              AND dp.eliminado = false
              AND lower(trim(dp.codigo)) = lower(trim(r_det.codigo_producto))
            LIMIT 1;

            IF v_id_producto_destino IS NULL THEN
                RAISE NOTICE '  -> línea OMITIDA (producto "%"): no existe ese código en empresa destino %',
                    r_det.codigo_producto, v_id_empresa_destino;
                CONTINUE;
            END IF;

            INSERT INTO suscripciones_detalle (
                id_suscripcion, id_empresa, id_producto, descripcion,
                cantidad, precio_unitario, porcentaje_iva, id_tarifa_iva,
                orden, created_by, created_at, eliminado
            ) VALUES (
                v_nueva_id, v_id_empresa_destino, v_id_producto_destino, r_det.descripcion,
                r_det.cantidad, r_det.precio_unitario, r_det.porcentaje_iva, r_det.id_tarifa_iva,
                r_det.orden, v_id_usuario, CURRENT_TIMESTAMP, false
            );
        END LOOP;

        -- La suscripción de origen queda cancelada: ya está reemplazada
        -- por la copia activa en el establecimiento destino.
        UPDATE suscripciones
        SET estado = 'cancelado', updated_by = v_id_usuario, updated_at = CURRENT_TIMESTAMP
        WHERE id = r_susc.id;

        v_copiadas := v_copiadas + 1;
    END LOOP;

    RAISE NOTICE '=== Resumen: % suscripciones copiadas, % omitidas (sin cliente con ese RUC en destino) ===',
        v_copiadas, v_omitidas;
END $$;
