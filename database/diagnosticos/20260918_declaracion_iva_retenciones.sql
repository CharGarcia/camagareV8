-- =============================================================================
-- 20260918_declaracion_iva_retenciones.sql — SOLO LECTURA
-- -----------------------------------------------------------------------------
-- Qué hace  : muestra, para un período, con qué código relaciona el módulo
--             Declaración de IVA (F104) cada línea de retención de IVA y a qué
--             casillero la manda. Sigue la misma cadena que
--             RetencionVentaService / RetencionCompraService::sincronizarCasilleros():
--
--               línea.codigo_retencion  (código SRI: 9=10%, 10=20%, 1=30%, 11=50%, 2=70%, 3=100%)
--                 → retenciones_sri.codigo_ret   (solo filas con impuesto_ret = 'IVA')
--                 → retenciones_sri.id
--                 → empresa_casilleros_iva_sri   (tipo_documento = 'retencion_iva', codigo = ese id,
--                                                 una configuración POR establecimiento)
--                 → "Cas. Ventas"  (casillero_neto, normalmente 609)  retenciones que le hicieron
--                   "Cas. Compras" (casillero_bruto, 721…731)         retenciones que la empresa emitió
--
--             y dice en qué eslabón se corta cada línea.
-- Resultado : una sola grilla, por bloques:
--               0 EMPRESAS     establecimientos encontrados (o aviso si el RUC / id no existe)
--               1 CONFIG       catálogo de retenciones de IVA y casilleros configurados por establecimiento
--               2 VENTAS       cada línea de retención de IVA que le hicieron en el período
--               3 COMPRAS      cada línea de retención de IVA que la empresa emitió en el período
--               4 ESTRUCTURA   si el casillero tiene fila en el formulario (si no, no se ve)
--               5 SINCRONIZADO lo que hoy está en casilleros_declaracion_sri (lo que lee el formulario)
--               6 SECCIONES    secciones del formulario (el cálculo interno busca las secciones "400" y "500")
--               7 GUARDADA     declaración ya guardada del período, si la hay
-- Toca datos: NO. Solo SELECT.
-- Parámetros: CTE «par»: RUC (o id) de la empresa y fechas del período.
--             Se revisan todos los establecimientos del mismo RUC, igual que el módulo.
-- Cómo      : pgAdmin → Query Tool sobre producción → F5 → en la grilla Ctrl+A y copiar TODAS las filas.
-- =============================================================================
WITH par AS (
    SELECT ''::text           AS ruc,          -- ← RUC de la empresa (13 dígitos)
           NULL::int          AS id_empresa,   -- ← o, en vez del RUC, el id de la empresa
           DATE '2026-08-01'  AS desde,        -- ← primer día del período
           DATE '2026-08-31'  AS hasta         -- ← último día del período
),
grupo AS (   -- el F104 se presenta por RUC: todos los establecimientos del mismo RUC
    SELECT e.id, CAST(e.tipo_ambiente AS VARCHAR(1)) AS amb, e.establecimiento,
           COALESCE(NULLIF(TRIM(e.nombre_comercial), ''), e.nombre) AS nombre,
           COALESCE(e.es_matriz, false) AS matriz
    FROM empresas e
    CROSS JOIN par
    WHERE e.eliminado = false
      AND TRIM(e.ruc) = COALESCE(NULLIF(TRIM(par.ruc), ''),
                                 (SELECT TRIM(ruc) FROM empresas WHERE id = par.id_empresa))
),
cat AS (     -- catálogo que usa el sistema (impuesto_ret = 'IVA' exacto, como en el código)
    SELECT id, codigo_ret, porcentaje_ret, concepto_ret,
           COUNT(*) OVER (PARTITION BY codigo_ret)            AS n_codigo,
           COUNT(*) OVER (PARTITION BY ROUND(porcentaje_ret)) AS n_pct
    FROM retenciones_sri
    WHERE impuesto_ret = 'IVA'
),
cfg AS (     -- Empresa › Form 104 IVA › "Retenciones SRI - IVA"
    SELECT ec.id_empresa, ec.codigo::text AS id_ret,
           NULLIF(TRIM(ec.casillero_bruto), '') AS cas_compras,
           NULLIF(TRIM(ec.casillero_neto),  '') AS cas_ventas
    FROM empresa_casilleros_iva_sri ec
    JOIN grupo g ON g.id = ec.id_empresa
    WHERE ec.tipo_documento = 'retencion_iva' AND ec.eliminado = false
),
lin AS (     -- líneas de retención de IVA del período (V = le hicieron, C = emitió)
    SELECT 'V'::text AS lado, r.id AS id_doc, r.id_empresa, r.fecha_emision,
           r.tipo_ambiente::text AS amb_doc, NULL::text AS estado,
           CONCAT_WS('-', r.establecimiento, r.punto_emision, r.secuencial) AS numero,
           d.codigo_impuesto, d.codigo_retencion, d.porcentaje_retencion AS pct, d.valor_retenido AS valor
    FROM retencion_venta_cabecera r
    JOIN grupo g ON g.id = r.id_empresa
    JOIN retencion_venta_detalle d ON d.id_retencion = r.id
    CROSS JOIN par
    WHERE r.eliminado = false
      AND r.fecha_emision BETWEEN par.desde AND par.hasta
      AND (UPPER(COALESCE(d.codigo_impuesto, '')) IN ('2', 'IVA')
           OR d.codigo_retencion IN (SELECT codigo_ret FROM cat))   -- también IVA mal marcado como renta
    UNION ALL
    SELECT 'C', r.id, r.id_empresa, r.fecha_emision,
           r.tipo_ambiente::text, r.estado::text,
           CONCAT_WS('-', r.establecimiento, r.punto_emision, r.secuencial),
           d.codigo_impuesto, d.codigo_retencion, d.porcentaje_retener, d.valor_retenido
    FROM retencion_compra_cabecera r
    JOIN grupo g ON g.id = r.id_empresa
    JOIN retencion_compra_detalle d ON d.id_retencion = r.id
    CROSS JOIN par
    WHERE r.eliminado = false
      AND r.fecha_emision BETWEEN par.desde AND par.hasta
      AND (UPPER(COALESCE(d.codigo_impuesto, '')) IN ('2', 'IVA')
           OR d.codigo_retencion IN (SELECT codigo_ret FROM cat))
),
sync AS (    -- lo ya sincronizado por documento
    SELECT s.origen, s.id_origen::text AS id_origen, SUM(s.valor) AS valor
    FROM casilleros_declaracion_sri s
    JOIN grupo g ON g.id = s.id_empresa
    CROSS JOIN par
    WHERE s.origen IN ('retenciones_ventas', 'retenciones_compras')
      AND s.fecha BETWEEN par.desde AND par.hasta
    GROUP BY s.origen, s.id_origen::text
),
res AS (     -- resolución de la cadena código → id → casillero, línea por línea
    SELECT l.*, g.amb AS amb_emp,
           (SELECT string_agg(c.id::text, ',' ORDER BY c.id) FROM cat c WHERE c.codigo_ret = l.codigo_retencion) AS ids,
           (SELECT COUNT(*) FROM cat c WHERE c.codigo_ret = l.codigo_retencion)                                   AS n_ids,
           (SELECT string_agg(DISTINCT CASE WHEN l.lado = 'V' THEN f.cas_ventas ELSE f.cas_compras END, ',')
              FROM cat c
              JOIN cfg f ON f.id_ret = c.id::text AND f.id_empresa = l.id_empresa
             WHERE c.codigo_ret = l.codigo_retencion)                                                              AS casillero,
           COALESCE(sy.valor, 0) AS sincronizado
    FROM lin l
    JOIN grupo g ON g.id = l.id_empresa
    LEFT JOIN sync sy ON sy.origen = CASE l.lado WHEN 'V' THEN 'retenciones_ventas' ELSE 'retenciones_compras' END
                     AND sy.id_origen = l.id_doc::text
),
salida AS (
    -- 0 EMPRESAS -------------------------------------------------------------
    SELECT '0 EMPRESAS' AS bloque, g.id::text AS empresa,
           COALESCE(g.establecimiento::text, '') || ' - ' || COALESCE(g.nombre, '')
               || CASE WHEN g.matriz THEN ' (matriz)' ELSE '' END AS referencia,
           NULL::text AS cod_impuesto, NULL::text AS cod_retencion, NULL::text AS pct, NULL::text AS valor,
           NULL::text AS id_retenciones_sri, NULL::text AS casillero, NULL::text AS sincronizado_doc,
           'Ambiente ' || COALESCE(g.amb, 'NULL')
               || CASE g.amb WHEN '2' THEN ' (producción)' WHEN '1' THEN ' (pruebas)' ELSE '' END AS diagnostico,
           0 AS orden
    FROM grupo g

    UNION ALL
    SELECT '0 EMPRESAS', NULL, 'Sin empresa', NULL, NULL, NULL, NULL, NULL, NULL, NULL,
           'NO SE ENCONTRÓ ninguna empresa con ese RUC / id: complete el bloque «par» del inicio y vuelva a ejecutar',
           0
    WHERE NOT EXISTS (SELECT 1 FROM grupo)

    UNION ALL
    SELECT '0 CATÁLOGO', NULL, 'retenciones_sri', NULL, NULL, NULL, NULL, NULL, NULL, NULL,
           'NO HAY filas con impuesto_ret = ''IVA'': ninguna retención de IVA se puede relacionar. Valores existentes: '
               || COALESCE((SELECT string_agg(DISTINCT COALESCE(impuesto_ret, 'NULL'), ', ') FROM retenciones_sri), '(tabla vacía)'),
           0
    WHERE NOT EXISTS (SELECT 1 FROM cat)

    UNION ALL
    -- 1 CONFIG ---------------------------------------------------------------
    SELECT '1 CONFIG', g.id::text,
           c.concepto_ret,
           NULL, c.codigo_ret,
           ROUND(c.porcentaje_ret)::text || '%', NULL,
           c.id::text,
           'Compras: ' || COALESCE(f.cas_compras, '—') || ' | Ventas: ' || COALESCE(f.cas_ventas, '—'),
           NULL,
           COALESCE(NULLIF(CONCAT_WS(' · ',
               CASE WHEN c.codigo_ret NOT IN ('9', '10', '1', '11', '2', '3', '7', '8')
                    THEN 'Código ' || c.codigo_ret || ' no es un código SRI de retención de IVA' END,
               CASE WHEN c.n_pct > 1
                    THEN 'Hay ' || c.n_pct || ' filas de ' || ROUND(c.porcentaje_ret) || '%: en Empresa › Form 104 IVA se ven iguales (no muestra el código); los documentos solo usan la del código que traen' END,
               CASE WHEN c.n_codigo > 1 THEN 'Código repetido en ' || c.n_codigo || ' filas: el sistema toma solo una' END,
               CASE WHEN f.cas_ventas  IS NULL THEN 'Sin "Cas. Ventas"' END,
               CASE WHEN f.cas_compras IS NULL THEN 'Sin "Cas. Compras"' END,
               (SELECT 'Usado en ' || COUNT(*) || ' línea(s) del período' FROM lin WHERE lin.codigo_retencion = c.codigo_ret AND lin.id_empresa = g.id HAVING COUNT(*) > 0)
           ), ''), 'OK') AS diagnostico,
           1 AS orden
    FROM grupo g
    CROSS JOIN cat c
    LEFT JOIN cfg f ON f.id_empresa = g.id AND f.id_ret = c.id::text

    UNION ALL
    -- 1 CONFIG: filas de retenciones_sri que parecen de IVA pero el sistema no reconoce
    SELECT '1 CONFIG', NULL, rs.concepto_ret, NULL, rs.codigo_ret, ROUND(rs.porcentaje_ret)::text || '%', NULL,
           rs.id::text, NULL, NULL,
           'impuesto_ret = "' || rs.impuesto_ret || '" (no es exactamente IVA): el sistema no la usa',
           1
    FROM retenciones_sri rs
    WHERE UPPER(TRIM(rs.impuesto_ret)) = 'IVA' AND rs.impuesto_ret <> 'IVA'

    UNION ALL
    -- 2 VENTAS / 3 COMPRAS ------------------------------------------------------
    SELECT CASE r.lado WHEN 'V' THEN '2 VENTAS (609)' ELSE '3 COMPRAS (721-731)' END,
           r.id_empresa::text,
           r.numero || ' (' || to_char(r.fecha_emision, 'DD-MM-YYYY') || ')'
               || CASE WHEN r.lado = 'C' THEN ' estado ' || COALESCE(r.estado, 'NULL') ELSE '' END,
           r.codigo_impuesto, r.codigo_retencion,
           ROUND(r.pct)::text || '%', ROUND(r.valor::numeric, 2)::text,
           r.ids, r.casillero, ROUND(r.sincronizado::numeric, 2)::text,
           CASE
               WHEN UPPER(COALESCE(r.codigo_impuesto, '')) NOT IN ('2', 'IVA')
                   THEN 'SE IGNORA: la línea tiene codigo_impuesto = ' || COALESCE(r.codigo_impuesto, 'NULL') || ' (solo se toman 2 / IVA)'
               WHEN r.lado = 'C' AND COALESCE(r.estado, '') <> 'autorizada'
                   THEN 'NO ENTRA: la retención no está autorizada por el SRI'
               WHEN r.amb_doc IS DISTINCT FROM r.amb_emp
                   THEN 'NO ENTRA: documento del ambiente ' || COALESCE(r.amb_doc, 'NULL') || ', la empresa está en ' || COALESCE(r.amb_emp, 'NULL')
               WHEN r.n_ids = 0
                   THEN 'NO ENTRA: el código ' || COALESCE(r.codigo_retencion, 'NULL') || ' no existe en retenciones_sri como IVA'
               WHEN r.casillero IS NULL
                   THEN 'NO ENTRA: falta "' || CASE r.lado WHEN 'V' THEN 'Cas. Ventas' ELSE 'Cas. Compras' END
                        || '" en Empresa › Form 104 IVA › Retenciones SRI - IVA (fila id ' || r.ids || ') del establecimiento ' || r.id_empresa
               WHEN COALESCE(r.valor, 0) <= 0
                   THEN 'Sin valor: no genera casillero'
               WHEN r.sincronizado = 0 AND r.lado = 'C'
                   THEN 'Cadena OK → ' || r.casillero || ', pero no está sincronizado: presione GENERAR. Si el servidor aún no tiene la corrección del 18-09-2026 (estado "autorizada"), las retenciones de compra nunca se sincronizan'
               WHEN r.sincronizado = 0
                   THEN 'Cadena OK → ' || r.casillero || ', pero no está sincronizado: presione GENERAR en la Declaración de IVA'
               WHEN r.n_ids > 1
                   THEN 'OK, pero el código está repetido en retenciones_sri (ids ' || r.ids || ')'
               ELSE 'OK → ' || r.casillero
           END,
           CASE r.lado WHEN 'V' THEN 2 ELSE 3 END
    FROM res r

    UNION ALL
    -- 4 ESTRUCTURA: ¿el casillero tiene fila en el formulario? ------------------
    SELECT '4 ESTRUCTURA', NULL, 'Casillero ' || u.cas, NULL, NULL, NULL, NULL, NULL, u.cas, NULL,
           CASE
               WHEN e.id IS NULL
                   THEN 'FALTA la fila en /config/sri-casilleros-etiquetas: el valor existe pero no se ve en el formulario'
               WHEN e.tipo = 'titulo'
                   THEN 'La fila id ' || e.id || ' es de tipo "título": se calcula pero no se muestra'
               ELSE 'OK: fila id ' || e.id || ', sección ' || e.seccion
           END,
           4
    FROM (
        SELECT '609' AS cas
        UNION SELECT cas_ventas  FROM cfg WHERE cas_ventas  IS NOT NULL
        UNION SELECT cas_compras FROM cfg WHERE cas_compras IS NOT NULL
    ) u
    LEFT JOIN LATERAL (
        SELECT s.id, s.seccion, s.tipo
        FROM sri_casilleros_etiquetas s
        WHERE s.eliminado = false
          AND u.cas IN (COALESCE(s.casillero_bruto, ''), COALESCE(s.casillero_neto, ''), COALESCE(s.casillero_impuesto, ''))
        ORDER BY s.id
        LIMIT 1
    ) e ON true

    UNION ALL
    -- 5 SINCRONIZADO: lo que hoy lee el formulario para el período -------------
    SELECT '5 SINCRONIZADO', s.id_empresa::text, s.origen, NULL, NULL, NULL, ROUND(SUM(s.valor)::numeric, 2)::text,
           NULL, s.casillero, COUNT(*)::text || ' fila(s)',
           CASE WHEN s.tipo_ambiente IS NOT DISTINCT FROM g.amb
                THEN 'Cuenta en el formulario'
                ELSE 'NO cuenta: filas del ambiente ' || COALESCE(s.tipo_ambiente, 'NULL') || ' (empresa en ' || COALESCE(g.amb, 'NULL') || ')' END,
           5
    FROM casilleros_declaracion_sri s
    JOIN grupo g ON g.id = s.id_empresa
    CROSS JOIN par
    WHERE s.origen IN ('retenciones_ventas', 'retenciones_compras')
      AND s.fecha BETWEEN par.desde AND par.hasta
    GROUP BY s.id_empresa, s.origen, s.casillero, s.tipo_ambiente, g.amb

    UNION ALL
    -- 6 SECCIONES: el cálculo interno (arrastre 615/617 y valor a pagar sugerido)
    --   suma el IVA en ventas de la sección "400" y el crédito tributario de la "500".
    SELECT '6 SECCIONES', NULL, 'Sección "' || s.seccion || '"', NULL, NULL, NULL, NULL, NULL,
           s.casilleros, NULL,
           CASE s.seccion
               WHEN '400' THEN 'De aquí sale el IVA en ventas del cálculo interno'
               WHEN '500' THEN 'De aquí sale el crédito tributario del cálculo interno'
               ELSE '—'
           END,
           6
    FROM (
        SELECT seccion,
               string_agg(NULLIF(CONCAT_WS('/', NULLIF(casillero_bruto, ''), NULLIF(casillero_neto, ''),
                                                NULLIF(casillero_impuesto, '')), ''),
                          ', ' ORDER BY orden, id) AS casilleros
        FROM sri_casilleros_etiquetas
        WHERE eliminado = false
        GROUP BY seccion
    ) s

    UNION ALL
    SELECT '6 SECCIONES', NULL, 'Sección "' || x.cod || '"', NULL, NULL, NULL, NULL, NULL, NULL, NULL,
           'NO EXISTE: el cálculo interno toma ' || x.que || ' de la sección "' || x.cod
               || '"; sin ella vale 0 (afecta 615/617 y el valor a pagar sugerido)',
           6
    FROM (VALUES ('400', 'el IVA en ventas'), ('500', 'el crédito tributario')) AS x(cod, que)
    WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE eliminado = false AND seccion = x.cod)

    UNION ALL
    -- 7 GUARDADA: declaración ya guardada del período (base del asiento y del egreso)
    SELECT '7 GUARDADA', d.j ->> 'id_empresa',
           'Declaración id ' || (d.j ->> 'id') || ' (' || COALESCE(d.j ->> 'estado', '') || ')',
           NULL, NULL, NULL, NULL, NULL, NULL, NULL,
           'iva_ventas=' || COALESCE(d.j ->> 'iva_ventas', '—')
               || ' · crédito_compras=' || COALESCE(d.j ->> 'credito_tributario_compras', '—')
               || ' · retenciones_iva=' || COALESCE(d.j ->> 'retenciones_iva', '—')
               || ' · iva_a_pagar=' || COALESCE(d.j ->> 'iva_a_pagar', '—')
               || ' · total_a_pagar=' || COALESCE(d.j ->> 'total_a_pagar', '—')
               || ' · en el formulario guardado: 609=' || COALESCE(d.v ->> '609', '—')
               || ' 721=' || COALESCE(d.v ->> '721', '—') || ' 723=' || COALESCE(d.v ->> '723', '—')
               || ' 725=' || COALESCE(d.v ->> '725', '—') || ' 727=' || COALESCE(d.v ->> '727', '—')
               || ' 729=' || COALESCE(d.v ->> '729', '—') || ' 731=' || COALESCE(d.v ->> '731', '—')
               || ' 799=' || COALESCE(d.v ->> '799', '—') || ' 801=' || COALESCE(d.v ->> '801', '—')
               || ' 902=' || COALESCE(d.v ->> '902', '—'),
           7
    FROM (
        SELECT to_jsonb(dc) AS j, (to_jsonb(dc) ->> 'valores_casilleros')::jsonb AS v
        FROM declaracion_iva_cabecera dc
        JOIN grupo g ON g.id = dc.id_empresa
        CROSS JOIN par
        WHERE dc.eliminado = false
          AND dc.fecha_desde = par.desde
    ) d
)
SELECT bloque, empresa, referencia, cod_impuesto, cod_retencion, pct, valor,
       id_retenciones_sri, casillero, sincronizado_doc, diagnostico
FROM salida
ORDER BY orden, empresa NULLS FIRST, referencia;
