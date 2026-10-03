-- =====================================================================================
-- Unificar forma de pago "Banco Guayaquil NO" (id 542) en "Banco Guayaquil" (id 528)
-- Empresa: KMCONNECT S.A.S. — RUC 1793212004001 — id_empresa 50
-- =====================================================================================
-- Qué hace:   mueve a la forma 528 todo lo que hoy apunta a la 542:
--               egresos_pagos.id_forma_pago        (79 filas según diagnóstico)
--               ingresos_pagos.id_forma_cobro      (11 filas)
--               saldos_iniciales_bancos            (1 fila, 29.32 al 2026-07-31)
--             No toca montos, fechas, cheques ni referencias.
-- Contable:   ambas formas usan la cuenta 7297 (también en asientos_programados), así que
--             los asientos ya generados y Control Bancario NO cambian.
-- Seguridad:  todo va en un solo bloque DO = una sola transacción. Si algún control falla
--             (formas de otra empresa, cuentas distintas, la 528 ya tiene saldo inicial),
--             no se cambia NADA.
-- Reversible: sí. Cada fila cambiada queda en respaldo_unificar_forma_542 (ver REVERTIR).
-- Auditoría:  este cambio NO queda en log_sistema.
-- =====================================================================================


-- -------------------------------------------------------------------------------------
-- PASO 1 — UNIFICAR (seleccione desde aquí hasta el END $$; y F5)
-- -------------------------------------------------------------------------------------
DO $$
DECLARE
    v_origen  CONSTANT int := 542;   -- Banco Guayaquil NO
    v_destino CONSTANT int := 528;   -- Banco Guayaquil
    v_empresa CONSTANT int := 50;
    n_egr int; n_ing int; n_sal int;
BEGIN
    -- Controles: ambas formas son de la empresa 50, BANCO, y comparten cuenta contable.
    IF (SELECT count(*) FROM empresa_formas_pago
         WHERE id IN (v_origen, v_destino) AND id_empresa = v_empresa) <> 2 THEN
        RAISE EXCEPTION 'Las formas 542/528 no pertenecen ambas a la empresa 50. No se cambió nada.';
    END IF;
    IF (SELECT count(DISTINCT id_cuenta_contable) FROM empresa_formas_pago
         WHERE id IN (v_origen, v_destino)) <> 1 THEN
        RAISE EXCEPTION 'Las formas tienen cuentas contables distintas. No se cambió nada.';
    END IF;
    IF EXISTS (SELECT 1 FROM saldos_iniciales_bancos
                WHERE id_empresa = v_empresa AND id_forma_pago = v_destino) THEN
        RAISE EXCEPTION 'Banco Guayaquil (528) ya tiene saldo inicial; chocaría con el UNIQUE. No se cambió nada.';
    END IF;

    -- Respaldo
    CREATE TABLE IF NOT EXISTS respaldo_unificar_forma_542 (
        tabla        text,
        id_fila      int,
        forma_antes  int,
        forma_nueva  int,
        respaldado   timestamp DEFAULT now()
    );

    INSERT INTO respaldo_unificar_forma_542 (tabla, id_fila, forma_antes, forma_nueva)
    SELECT 'egresos_pagos', id, id_forma_pago, v_destino FROM egresos_pagos WHERE id_forma_pago = v_origen
    UNION ALL
    SELECT 'ingresos_pagos', id, id_forma_cobro, v_destino FROM ingresos_pagos WHERE id_forma_cobro = v_origen
    UNION ALL
    SELECT 'saldos_iniciales_bancos', id, id_forma_pago, v_destino FROM saldos_iniciales_bancos
     WHERE id_forma_pago = v_origen AND id_empresa = v_empresa;

    -- Cambio
    UPDATE egresos_pagos  SET id_forma_pago  = v_destino WHERE id_forma_pago  = v_origen;
    GET DIAGNOSTICS n_egr = ROW_COUNT;

    UPDATE ingresos_pagos SET id_forma_cobro = v_destino WHERE id_forma_cobro = v_origen;
    GET DIAGNOSTICS n_ing = ROW_COUNT;

    UPDATE saldos_iniciales_bancos
       SET id_forma_pago = v_destino, updated_at = now()
     WHERE id_forma_pago = v_origen AND id_empresa = v_empresa;
    GET DIAGNOSTICS n_sal = ROW_COUNT;

    RAISE NOTICE 'Egresos pagos: %, Ingresos pagos: %, Saldos iniciales: %', n_egr, n_ing, n_sal;
END $$;


-- -------------------------------------------------------------------------------------
-- PASO 2 — (OPCIONAL) retirar "Banco Guayaquil NO" para que nadie vuelva a usarla.
--          Eliminación lógica. Si prefiere solo inactivarla, quite "eliminado = true,
--          deleted_at = now()," y deje solo activo = false.
-- -------------------------------------------------------------------------------------
UPDATE empresa_formas_pago
   SET activo = false, eliminado = true, deleted_at = now(), updated_at = now()
 WHERE id = 542 AND id_empresa = 50;


-- -------------------------------------------------------------------------------------
-- COMPROBACIÓN — debe salir 0 en las tres primeras y 91 en la última.
-- -------------------------------------------------------------------------------------
-- SELECT 'egresos_pagos 542'  AS que, count(*) FROM egresos_pagos  WHERE id_forma_pago  = 542
-- UNION ALL SELECT 'ingresos_pagos 542', count(*) FROM ingresos_pagos WHERE id_forma_cobro = 542
-- UNION ALL SELECT 'saldos_ini 542',     count(*) FROM saldos_iniciales_bancos WHERE id_forma_pago = 542
-- UNION ALL SELECT 'respaldadas',        count(*) FROM respaldo_unificar_forma_542;


-- -------------------------------------------------------------------------------------
-- REVERTIR (solo si hiciera falta) — primero reactivar la 542 si se ejecutó el PASO 2:
-- -------------------------------------------------------------------------------------
-- UPDATE empresa_formas_pago SET activo = true, eliminado = false, deleted_at = NULL WHERE id = 542;
-- UPDATE egresos_pagos  p SET id_forma_pago  = r.forma_antes FROM respaldo_unificar_forma_542 r WHERE r.tabla = 'egresos_pagos'  AND r.id_fila = p.id;
-- UPDATE ingresos_pagos p SET id_forma_cobro = r.forma_antes FROM respaldo_unificar_forma_542 r WHERE r.tabla = 'ingresos_pagos' AND r.id_fila = p.id;
-- UPDATE saldos_iniciales_bancos p SET id_forma_pago = r.forma_antes FROM respaldo_unificar_forma_542 r WHERE r.tabla = 'saldos_iniciales_bancos' AND r.id_fila = p.id;
