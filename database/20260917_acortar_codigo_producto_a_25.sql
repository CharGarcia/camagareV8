-- ============================================================================
-- Acorta a 25 caracteres el código de un producto (límite del SRI) y arrastra
-- ese cambio a las copias que guardan las proformas y sus plantillas.
-- ----------------------------------------------------------------------------
-- Contexto:
--   Los XSD del SRI (factura, NC, ND, guía de remisión y liquidación) definen
--   codigoPrincipal/codigoAuxiliar con maxLength 25, y las tablas de detalle de
--   los documentos que emitimos (ventas_detalle, recibos_venta_detalle,
--   notas_credito_detalle, guias_remision_detalle) son varchar(25). El catálogo de
--   Productos acepta hasta 50, así que un producto con código más largo se cotiza
--   en una proforma pero falla al convertirla en factura:
--   "Línea #N: el código tiene 26 caracteres y el SRI admite como máximo 25".
--
--   Por qué no basta con corregir el producto: al convertir una proforma en
--   factura se copia `proformas_detalle.codigo_principal` (la foto guardada en la
--   línea), no el código vivo del maestro. Las plantillas de proforma guardan lo
--   mismo y lo vuelven a sembrar en cada proforma nueva. Los demás módulos
--   (consignaciones, pedidos, POS…) leen el código vivo del producto, así que con
--   renombrar el maestro quedan corregidos.
--
-- Qué hace:
--   1. Renombra el código del producto (valida largo, que exista y que el nuevo no
--      esté ocupado en la empresa).
--   2. Actualiza las líneas de proformas y de plantillas de proforma de esa empresa
--      que tengan guardado un código de más de 25 caracteres de ese producto.
--   3. Deja el antes/después en log_sistema (accion 'ACORTAR_CODIGO_SRI').
--   NO toca documentos ya emitidos (sus columnas son de 25: no pueden tener el
--   código largo) ni compras_detalle (ahí va el código DEL PROVEEDOR, no el nuestro).
--
-- Antes de ejecutar: correr database/diagnosticos/20260917_producto_codigo_mayor_25.sql
-- y elegir el código nuevo (si el proveedor ya usa uno de 25 o menos, ese es el mejor).
--
-- Idempotente: reejecutarlo no vuelve a tocar nada (0 filas la 2.ª vez).
-- Reversible con lo que queda en log_sistema:
--   UPDATE productos p
--      SET codigo = ls.datos_anteriores ->> 'codigo', updated_at = now()
--     FROM log_sistema ls
--    WHERE ls.accion = 'ACORTAR_CODIGO_SRI' AND ls.tabla_afectada = 'productos'
--      AND p.id = ls.id_registro;
--   (las líneas de proforma se dejan con el código nuevo a propósito: con el código
--    viejo volverían a impedir la facturación).
-- ============================================================================

DO $$
DECLARE
    v_emp    int  := 54;                             -- << empresa
    v_prod   int  := 68309;                          -- << producto a corregir
    v_nuevo  text := 'HAC-HFW1219SLN-IL-A-PRO';      -- << código nuevo (máximo 25 caracteres)
    v_user   int  := 2;                              -- << usuario que ejecuta (producción: 2)
    v_actual text;
    v_nombre text;
    v_prof   int := 0;
    v_plant  int := 0;
BEGIN
    v_nuevo := trim(v_nuevo);

    IF char_length(v_nuevo) = 0 OR char_length(v_nuevo) > 25 THEN
        RAISE EXCEPTION 'El código nuevo debe tener entre 1 y 25 caracteres (tiene %): %',
                        char_length(v_nuevo), v_nuevo;
    END IF;

    SELECT p.codigo, p.nombre INTO v_actual, v_nombre
      FROM productos p
     WHERE p.id = v_prod AND p.id_empresa = v_emp AND p.eliminado = false;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'No existe el producto % en la empresa % (o está eliminado).', v_prod, v_emp;
    END IF;

    IF v_actual = v_nuevo THEN
        RAISE NOTICE 'El producto % ya tiene el código % — no se toca el maestro.', v_prod, v_nuevo;
    ELSE
        IF EXISTS (SELECT 1 FROM productos
                    WHERE id_empresa = v_emp AND eliminado = false
                      AND id <> v_prod AND lower(codigo) = lower(v_nuevo)) THEN
            RAISE EXCEPTION 'La empresa % ya tiene otro producto con el código % — elija otro.', v_emp, v_nuevo;
        END IF;

        UPDATE productos
           SET codigo = v_nuevo, updated_at = now(), updated_by = v_user
         WHERE id = v_prod AND id_empresa = v_emp;

        INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                                 datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
        VALUES (v_user, v_emp, 'ACORTAR_CODIGO_SRI', 'productos', v_prod,
                jsonb_build_object('codigo', v_actual, 'largo', char_length(v_actual), 'nombre', v_nombre),
                jsonb_build_object('codigo', v_nuevo,  'largo', char_length(v_nuevo),
                                   'motivo', 'El SRI admite 25 caracteres en codigoPrincipal'),
                NULL, 'SQL 20260917_acortar_codigo_producto_a_25', now());
    END IF;

    -- Copia guardada en la línea de la proforma (es la que usa "convertir a factura")
    UPDATE proformas_detalle pd
       SET codigo_principal = v_nuevo
      FROM proformas_cabecera pc
     WHERE pc.id = pd.id_proforma
       AND pc.id_empresa = v_emp
       AND pd.id_producto = v_prod
       AND char_length(pd.codigo_principal) > 25;
    GET DIAGNOSTICS v_prof = ROW_COUNT;

    -- Plantillas de proforma: si no se corrigen, vuelven a sembrar el código largo
    UPDATE proformas_plantillas_detalle pld
       SET codigo_principal = v_nuevo
      FROM proformas_plantillas pl
     WHERE pl.id = pld.id_plantilla
       AND pl.id_empresa = v_emp
       AND pld.id_producto = v_prod
       AND char_length(pld.codigo_principal) > 25;
    GET DIAGNOSTICS v_plant = ROW_COUNT;

    IF v_prof > 0 OR v_plant > 0 THEN
        INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                                 datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
        VALUES (v_user, v_emp, 'ACORTAR_CODIGO_SRI', 'proformas_detalle', v_prod,
                jsonb_build_object('codigo', v_actual),
                jsonb_build_object('codigo', v_nuevo,
                                   'lineas_proforma', v_prof, 'lineas_plantilla', v_plant),
                NULL, 'SQL 20260917_acortar_codigo_producto_a_25', now());
    END IF;

    RAISE NOTICE 'Producto % (%): % → %  |  líneas de proforma: %  |  líneas de plantilla: %',
                 v_prod, v_nombre, v_actual, v_nuevo, v_prof, v_plant;
END $$;

-- Comprobación (es la última sentencia: pgAdmin muestra este resultado).
-- Debe salir el código nuevo, con largo <= 25 y las dos cuentas en 0.
SELECT p.id,
       p.codigo,
       char_length(p.codigo) AS largo,
       p.nombre,
       (SELECT COUNT(*) FROM proformas_detalle pd
          JOIN proformas_cabecera pc ON pc.id = pd.id_proforma
         WHERE pc.id_empresa = p.id_empresa AND pd.id_producto = p.id
           AND char_length(pd.codigo_principal) > 25)            AS lineas_proforma_largas,
       (SELECT COUNT(*) FROM proformas_plantillas_detalle pld
          JOIN proformas_plantillas pl ON pl.id = pld.id_plantilla
         WHERE pl.id_empresa = p.id_empresa AND pld.id_producto = p.id
           AND char_length(pld.codigo_principal) > 25)           AS lineas_plantilla_largas
  FROM productos p
 WHERE p.id = 68309;
