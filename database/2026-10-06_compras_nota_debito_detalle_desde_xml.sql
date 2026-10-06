-- =====================================================================================
-- Compras: reconstruir el detalle e impuestos de las NOTAS DE DÉBITO (tipo 05) que
-- quedaron sin desglose, a partir del XML autorizado guardado en detalle_xml.
--
-- QUÉ HACE
--   Hasta el 11-09-2026 el registro automático de comprobantes del SRI no escribía
--   compras_detalle ni compras_detalle_impuestos para las notas de débito (el XML del
--   SRI trae <motivos><motivo> en vez de <detalles><detalle>, y los impuestos van en
--   <infoNotaDebito><impuestos>). El total de la cabecera quedó bien, pero el ATS las
--   rechaza con "al menos una base (baseNoGraIva, baseImponible, baseImpGrav o
--   baseImpExe) debe ser mayor a 0.00" y el Reporte de Compras no suma su IVA.
--   Este script recorre las compras tipo 05 cuyo detalle_xml contiene <notaDebito> y:
--     a) si NO tienen ninguna línea en compras_detalle: crea una línea por <motivo>
--        y adjunta a la primera los impuestos de <infoNotaDebito><impuestos>;
--     b) si SÍ tienen líneas pero NINGUNA tiene impuesto IVA (código 2): adjunta a la
--        primera línea los impuestos del XML.
--   Exactamente el mismo criterio que hoy aplica el registro automático
--   (DocumentoAutomatedRegisterService, caso codDoc 05).
--
-- TOCA DATOS: sí (INSERT en compras_detalle y compras_detalle_impuestos). No modifica
--   ni borra nada existente. Idempotente: una compra que ya tiene IVA no se toca.
-- REVERSIBLE: sí. Cada compra reparada sale en un NOTICE con su id; para revertir,
--   borrar los impuestos y las líneas de esa compra (bloque comentado al final).
-- CÓMO: pegar todo en el Query Tool de pgAdmin y ejecutar (F5). Los avisos (NOTICE)
--   salen en la pestaña "Messages": una línea por compra reparada u omitida.
-- =====================================================================================

DO $$
DECLARE
    r            RECORD;
    v_inner      TEXT;
    v_xml        XML;
    v_id_det     INTEGER;
    v_primero    INTEGER;
    v_motivo     XML;
    v_imp        XML;
    v_razon      TEXT;
    v_valor      NUMERIC;
    v_cod        TEXT;
    v_codp       TEXT;
    v_tarifa     NUMERIC;
    v_base       NUMERIC;
    v_val        NUMERIC;
    v_total_ice  NUMERIC;
    n_reparadas  INTEGER := 0;
    n_omitidas   INTEGER := 0;
    n_lineas     INTEGER;
BEGIN
    FOR r IN
        SELECT c.id, c.id_empresa, c.establecimiento_prov, c.punto_emision_prov,
               c.secuencial_prov, c.detalle_xml,
               EXISTS (SELECT 1 FROM compras_detalle d WHERE d.id_compra = c.id) AS tiene_detalle
          FROM compras_cabecera c
         WHERE c.tipo_comprobante = '05'
           AND c.eliminado = false
           AND c.detalle_xml IS NOT NULL
           AND (c.detalle_xml LIKE '%<notaDebito%' OR c.detalle_xml LIKE '%&lt;notaDebito%')
           AND NOT EXISTS (
                SELECT 1
                  FROM compras_detalle d
                  JOIN compras_detalle_impuestos di ON di.id_compra_detalle = d.id
                 WHERE d.id_compra = c.id
                   AND di.codigo_impuesto = '2'
           )
         ORDER BY c.id
    LOOP
        BEGIN
            -- 1) Extraer el <notaDebito>…</notaDebito> del XML guardado (puede venir
            --    suelto, dentro de <autorizacion><comprobante><![CDATA[…]]> o escapado).
            v_inner := substring(r.detalle_xml FROM '(<notaDebito.*</notaDebito>)');
            IF v_inner IS NULL THEN
                v_inner := (xpath('/autorizacion/comprobante/text()', r.detalle_xml::xml))[1]::text;
                v_inner := regexp_replace(v_inner, '^\s*<!\[CDATA\[', '');
                v_inner := regexp_replace(v_inner, '\]\]>\s*$', '');
                v_inner := substring(v_inner FROM '(<notaDebito.*</notaDebito>)');
            END IF;
            IF v_inner IS NULL THEN
                RAISE NOTICE 'OMITIDA compra % (%-%-%): detalle_xml sin <notaDebito> legible',
                    r.id, r.establecimiento_prov, r.punto_emision_prov, r.secuencial_prov;
                n_omitidas := n_omitidas + 1;
                CONTINUE;
            END IF;
            v_xml := v_inner::xml;

            -- 2) Líneas: una por <motivo> (solo si la compra no tiene ninguna).
            v_primero := NULL;
            n_lineas  := 0;
            IF NOT r.tiene_detalle THEN
                FOREACH v_motivo IN ARRAY xpath('/notaDebito/motivos/motivo', v_xml) LOOP
                    v_razon := COALESCE((xpath('/motivo/razon/text()', v_motivo))[1]::text, '');
                    v_valor := COALESCE(NULLIF(BTRIM((xpath('/motivo/valor/text()', v_motivo))[1]::text), ''), '0')::numeric;
                    INSERT INTO compras_detalle
                        (id_compra, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
                    VALUES (r.id, '', v_razon, 1, v_valor, 0, v_valor)
                    RETURNING id INTO v_id_det;
                    IF v_primero IS NULL THEN v_primero := v_id_det; END IF;
                    n_lineas := n_lineas + 1;
                END LOOP;
            ELSE
                SELECT d.id INTO v_primero FROM compras_detalle d WHERE d.id_compra = r.id ORDER BY d.id LIMIT 1;
            END IF;

            IF v_primero IS NULL THEN
                RAISE NOTICE 'OMITIDA compra % (%-%-%): el XML no trae <motivos>',
                    r.id, r.establecimiento_prov, r.punto_emision_prov, r.secuencial_prov;
                n_omitidas := n_omitidas + 1;
                CONTINUE;
            END IF;

            -- 3) Impuestos de cabecera, adjuntos a la primera línea.
            FOREACH v_imp IN ARRAY xpath('/notaDebito/infoNotaDebito/impuestos/impuesto', v_xml) LOOP
                v_cod    := BTRIM((xpath('/impuesto/codigo/text()', v_imp))[1]::text);
                v_codp   := BTRIM((xpath('/impuesto/codigoPorcentaje/text()', v_imp))[1]::text);
                v_tarifa := COALESCE(NULLIF(BTRIM((xpath('/impuesto/tarifa/text()', v_imp))[1]::text), '')::numeric,
                                     CASE v_codp WHEN '2' THEN 12 WHEN '3' THEN 14 WHEN '4' THEN 15
                                                 WHEN '5' THEN 5 WHEN '8' THEN 8 WHEN '10' THEN 13 ELSE 0 END);
                v_base   := COALESCE(NULLIF(BTRIM((xpath('/impuesto/baseImponible/text()', v_imp))[1]::text), ''), '0')::numeric;
                v_val    := COALESCE(NULLIF(BTRIM((xpath('/impuesto/valor/text()', v_imp))[1]::text), ''), '0')::numeric;
                IF v_cod IS NULL OR v_cod = '' THEN CONTINUE; END IF;
                INSERT INTO compras_detalle_impuestos
                    (id_compra_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
                VALUES (v_primero, v_cod, COALESCE(v_codp, '0'), v_tarifa, v_base, v_val);
            END LOOP;

            -- 4) total_ice de la cabecera, igual que el registro automático.
            SELECT COALESCE(SUM(di.valor), 0) INTO v_total_ice
              FROM compras_detalle_impuestos di
              JOIN compras_detalle d ON d.id = di.id_compra_detalle
             WHERE d.id_compra = r.id AND di.codigo_impuesto = '3';
            IF v_total_ice > 0 THEN
                UPDATE compras_cabecera SET total_ice = v_total_ice WHERE id = r.id;
            END IF;

            n_reparadas := n_reparadas + 1;
            RAISE NOTICE 'REPARADA compra % (%-%-%, empresa %): % linea(s) creadas, impuestos adjuntados',
                r.id, r.establecimiento_prov, r.punto_emision_prov, r.secuencial_prov, r.id_empresa, n_lineas;
        EXCEPTION WHEN OTHERS THEN
            -- Un XML mal formado no debe tumbar la corrida completa: se avisa y se sigue.
            RAISE NOTICE 'OMITIDA compra % (%-%-%): %', r.id, r.establecimiento_prov,
                r.punto_emision_prov, r.secuencial_prov, SQLERRM;
            n_omitidas := n_omitidas + 1;
        END;
    END LOOP;
    RAISE NOTICE 'Notas de débito reparadas: %, omitidas: %', n_reparadas, n_omitidas;
END $$;

-- -------------------------------------------------------------------------------------
-- COMPROBACIÓN: notas de débito que SIGUEN sin base de IVA (deben ser 0 filas, o solo
-- las que el bloque anterior reportó como OMITIDAS: esas hay que completarlas a mano
-- abriendo la compra en el módulo Compras y registrando el IVA en sus líneas).
-- -------------------------------------------------------------------------------------
-- SELECT c.id, c.id_empresa, c.establecimiento_prov || '-' || c.punto_emision_prov || '-' || c.secuencial_prov AS numero,
--        p.identificacion, c.fecha_emision, c.importe_total,
--        (c.detalle_xml IS NOT NULL) AS tiene_xml,
--        (SELECT COUNT(*) FROM compras_detalle d WHERE d.id_compra = c.id) AS lineas
--   FROM compras_cabecera c
--   JOIN proveedores p ON p.id = c.id_proveedor
--  WHERE c.tipo_comprobante = '05' AND c.eliminado = false
--    AND NOT EXISTS (SELECT 1 FROM compras_detalle d
--                      JOIN compras_detalle_impuestos di ON di.id_compra_detalle = d.id
--                     WHERE d.id_compra = c.id AND di.codigo_impuesto = '2' AND di.base_imponible > 0)
--  ORDER BY c.fecha_emision;

-- -------------------------------------------------------------------------------------
-- REVERSIÓN (solo si hiciera falta): borrar lo creado para UNA compra reparada.
-- Reemplazar 999 por el id que salió en el NOTICE "REPARADA compra 999".
-- -------------------------------------------------------------------------------------
-- DELETE FROM compras_detalle_impuestos WHERE id_compra_detalle IN (SELECT id FROM compras_detalle WHERE id_compra = 999);
-- DELETE FROM compras_detalle WHERE id_compra = 999;   -- solo si la compra no tenía líneas antes
