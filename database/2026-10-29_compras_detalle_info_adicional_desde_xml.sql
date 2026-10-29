-- =====================================================================================
-- Compras: llenar el "Adicional" de cada ítem (compras_detalle.info_adicional) de las
-- compras YA REGISTRADAS, leyéndolo del XML del SRI guardado en la propia compra
-- (compras_cabecera.detalle_xml).
--
-- REQUISITO: haber corrido antes database/2026-10-29_compras_detalle_info_adicional.sql.
--
-- QUÉ HACE
--   Recorre las facturas (01) y notas de crédito (04) de compra cuyo XML trae
--   <detallesAdicionales> y copia a cada línea el mismo texto que hoy guarda el registro
--   automático (App\Helpers\DetalleAdicionalXmlHelper):
--     - cada <detAdicional nombre="X" valor="Y"/> queda "X: Y"; si el nombre es el
--       genérico "Detalle" (o viene vacío) queda solo el valor;
--     - varios se unen con " | "; máximo 300 caracteres.
--   Las líneas de la compra se emparejan con los <detalle> del XML EN ORDEN (las líneas
--   se crearon en el orden del XML). Para no poner el adicional en la línea equivocada,
--   una compra se OMITE (y se avisa) si:
--     - no tiene el mismo número de líneas que el XML (se agregaron o quitaron líneas), o
--     - alguna pareja no coincide en cantidad y precio unitario.
--
-- TOCA DATOS: sí, solo UPDATE de compras_detalle.info_adicional, y solo en líneas que
--   hoy lo tienen vacío (no pisa lo que alguien ya escribió). Idempotente: se puede
--   correr varias veces.
-- VISTA PREVIA: con v_aplicar := false (valor por defecto) NO cambia nada: solo lista
--   en "Messages" qué compras se actualizarían y cuáles se omiten. Revisar y luego
--   cambiar a true y volver a ejecutar.
-- ALCANCE: todas las empresas. Para una sola, poner su id en v_id_empresa.
-- CÓMO: pegar todo en el Query Tool de pgAdmin y ejecutar (F5).
-- =====================================================================================

DO $$
DECLARE
    v_aplicar     BOOLEAN := false;   -- false = vista previa; true = actualizar
    v_id_empresa  INTEGER := NULL;    -- NULL = todas las empresas

    r             RECORD;
    v_inner       TEXT;
    v_xml         XML;
    v_dets        XML[];
    v_lineas      INTEGER[];
    v_det         XML;
    v_da          XML;
    v_partes      TEXT[];
    v_nombre      TEXT;
    v_valor       TEXT;
    v_texto       TEXT;
    v_cant_xml    NUMERIC;
    v_pu_xml      NUMERIC;
    v_cant_bd     NUMERIC;
    v_pu_bd       NUMERIC;
    v_ok          BOOLEAN;
    v_lineas_act  INTEGER;
    i             INTEGER;
    n_compras     INTEGER := 0;
    n_lineas      INTEGER := 0;
    n_omitidas    INTEGER := 0;
BEGIN
    FOR r IN
        SELECT c.id, c.id_empresa, c.tipo_comprobante, c.establecimiento_prov,
               c.punto_emision_prov, c.secuencial_prov, c.detalle_xml
          FROM compras_cabecera c
         WHERE c.eliminado = false
           AND c.tipo_comprobante IN ('01', '04')
           AND c.detalle_xml IS NOT NULL
           AND (c.detalle_xml LIKE '%<detAdicional%' OR c.detalle_xml LIKE '%&lt;detAdicional%')
           AND (v_id_empresa IS NULL OR c.id_empresa = v_id_empresa)
           AND EXISTS (SELECT 1 FROM compras_detalle d
                        WHERE d.id_compra = c.id
                          AND (d.info_adicional IS NULL OR BTRIM(d.info_adicional) = ''))
         ORDER BY c.id
    LOOP
        BEGIN
            -- 1) Extraer el comprobante del XML guardado (puede venir suelto, dentro de
            --    <autorizacion><comprobante><![CDATA[…]]> o escapado).
            v_inner := substring(r.detalle_xml FROM '(<(factura|notaCredito)[ >].*</(factura|notaCredito)>)');
            IF v_inner IS NULL THEN
                v_inner := (xpath('/autorizacion/comprobante/text()', r.detalle_xml::xml))[1]::text;
                v_inner := regexp_replace(v_inner, '^\s*<!\[CDATA\[', '');
                v_inner := regexp_replace(v_inner, '\]\]>\s*$', '');
                v_inner := substring(v_inner FROM '(<(factura|notaCredito)[ >].*</(factura|notaCredito)>)');
            END IF;
            IF v_inner IS NULL THEN
                RAISE NOTICE 'OMITIDA compra % (%-%-%): no se pudo leer el comprobante del XML',
                    r.id, r.establecimiento_prov, r.punto_emision_prov, r.secuencial_prov;
                n_omitidas := n_omitidas + 1;
                CONTINUE;
            END IF;
            v_xml  := v_inner::xml;
            v_dets := xpath('/*/detalles/detalle', v_xml);

            -- 2) Líneas de la compra en el orden en que se crearon.
            SELECT array_agg(d.id ORDER BY d.id) INTO v_lineas
              FROM compras_detalle d WHERE d.id_compra = r.id;

            IF COALESCE(array_length(v_dets, 1), 0) <> COALESCE(array_length(v_lineas, 1), 0) THEN
                RAISE NOTICE 'OMITIDA compra % (%-%-%): el XML tiene % línea(s) y la compra %',
                    r.id, r.establecimiento_prov, r.punto_emision_prov, r.secuencial_prov,
                    COALESCE(array_length(v_dets, 1), 0), COALESCE(array_length(v_lineas, 1), 0);
                n_omitidas := n_omitidas + 1;
                CONTINUE;
            END IF;

            -- 3) Verificar que cada pareja (línea i del XML, línea i de la compra) sea la misma.
            v_ok := true;
            FOR i IN 1 .. array_length(v_dets, 1) LOOP
                v_cant_xml := COALESCE(NULLIF(BTRIM((xpath('/detalle/cantidad/text()', v_dets[i]))[1]::text), ''), '0')::numeric;
                v_pu_xml   := COALESCE(NULLIF(BTRIM((xpath('/detalle/precioUnitario/text()', v_dets[i]))[1]::text), ''), '0')::numeric;
                SELECT d.cantidad, d.precio_unitario INTO v_cant_bd, v_pu_bd
                  FROM compras_detalle d WHERE d.id = v_lineas[i];
                IF abs(v_cant_xml - v_cant_bd) > 0.000001 OR abs(v_pu_xml - v_pu_bd) > 0.000001 THEN
                    v_ok := false;
                    EXIT;
                END IF;
            END LOOP;
            IF NOT v_ok THEN
                RAISE NOTICE 'OMITIDA compra % (%-%-%): la línea % no coincide con el XML (cantidad/precio)',
                    r.id, r.establecimiento_prov, r.punto_emision_prov, r.secuencial_prov, i;
                n_omitidas := n_omitidas + 1;
                CONTINUE;
            END IF;

            -- 4) Armar el texto de cada línea y guardarlo donde esté vacío.
            v_lineas_act := 0;
            FOR i IN 1 .. array_length(v_dets, 1) LOOP
                v_partes := ARRAY[]::TEXT[];
                FOREACH v_da IN ARRAY xpath('/detalle/detallesAdicionales/detAdicional', v_dets[i]) LOOP
                    v_nombre := BTRIM(COALESCE((xpath('/detAdicional/@nombre', v_da))[1]::text, ''));
                    v_valor  := BTRIM(COALESCE((xpath('/detAdicional/@valor', v_da))[1]::text, ''));
                    IF v_valor = '' THEN CONTINUE; END IF;
                    v_partes := v_partes || CASE WHEN v_nombre = '' OR lower(v_nombre) = 'detalle'
                                                 THEN v_valor ELSE v_nombre || ': ' || v_valor END;
                END LOOP;
                v_texto := left(array_to_string(v_partes, ' | '), 300);
                IF v_texto = '' THEN CONTINUE; END IF;

                IF v_aplicar THEN
                    UPDATE compras_detalle
                       SET info_adicional = v_texto
                     WHERE id = v_lineas[i]
                       AND (info_adicional IS NULL OR BTRIM(info_adicional) = '');
                    IF FOUND THEN v_lineas_act := v_lineas_act + 1; END IF;
                ELSE
                    IF EXISTS (SELECT 1 FROM compras_detalle
                                WHERE id = v_lineas[i]
                                  AND (info_adicional IS NULL OR BTRIM(info_adicional) = '')) THEN
                        v_lineas_act := v_lineas_act + 1;
                    END IF;
                END IF;
            END LOOP;

            IF v_lineas_act > 0 THEN
                n_compras := n_compras + 1;
                n_lineas  := n_lineas + v_lineas_act;
                RAISE NOTICE '% compra % (%-%-%, empresa %): % línea(s)',
                    CASE WHEN v_aplicar THEN 'ACTUALIZADA' ELSE 'SE ACTUALIZARÍA' END,
                    r.id, r.establecimiento_prov, r.punto_emision_prov, r.secuencial_prov,
                    r.id_empresa, v_lineas_act;
            END IF;
        EXCEPTION WHEN OTHERS THEN
            -- Un XML mal formado no debe tumbar la corrida completa: se avisa y se sigue.
            RAISE NOTICE 'OMITIDA compra % (%-%-%): %', r.id, r.establecimiento_prov,
                r.punto_emision_prov, r.secuencial_prov, SQLERRM;
            n_omitidas := n_omitidas + 1;
        END;
    END LOOP;

    RAISE NOTICE '=== % — compras: %, líneas: %, omitidas: % ===',
        CASE WHEN v_aplicar THEN 'APLICADO' ELSE 'VISTA PREVIA (no se cambió nada)' END,
        n_compras, n_lineas, n_omitidas;
END $$;
