-- =====================================================================================
-- Backfill: completar la serie (establecimiento + punto de emisión) de los documentos
-- migrados desde el sistema anterior que quedaron sin ella.
--
-- POR QUÉ
--   SecuencialRepository::getSecuencialesUsados() filtra por id_punto_emision. Un documento
--   con esa FK en NULL es INVISIBLE para el generador de secuenciales: al emitir el primer
--   documento nuevo el sistema arranca desde el secuencial inicial y vuelve a repartir
--   números que ya existen en los migrados.
--
-- QUÉ SERIE SE ASIGNA
--   La serie ACTIVA de la empresa: establecimiento activo + punto de emisión activo de MENOR
--   código, EXCLUYENDO el punto dedicado a "Facturas de reembolso" (ese punto existe solo para
--   esa familia de codDoc del SRI y no debe recibir documentos de otros tipos).
--   Los activos se priorizan en el ORDER BY en vez de filtrarse, para que una empresa con todo
--   marcado inactivo siga obteniendo una serie.
--
-- QUÉ **NO** SE TOCA
--   * Documentos autorizados por el SRI (facturas, notas de crédito, guías, liquidaciones,
--     retenciones, proformas, recibos): ésos SIEMPRE traen su serie real del sistema anterior y
--     ya se migran con ella. Este script no los modifica. La sección 9 solo los AUDITA.
--   * Cualquier fila que ya tenga serie: los UPDATE usan COALESCE/NULLIF y filtran por serie
--     incompleta, nunca pisan un valor existente.
--   * Filas sin secuencial: sin número no hay documento que numerar. Se reportan (sección 8).
--
-- IDEMPOTENTE: se puede ejecutar varias veces; la segunda pasada no encuentra nada que hacer.
--
-- Contraparte en el código: MigracionMysqlService::serieDefecto() aplica esta misma regla
-- durante la migración, y SecuencialRepository::getSerieDefecto() es la fuente de la regla.
-- =====================================================================================

BEGIN;

-- ── 1. Serie por defecto de cada empresa ─────────────────────────────────────────────
CREATE TEMP TABLE _serie_defecto ON COMMIT DROP AS
SELECT DISTINCT ON (es.id_empresa)
       es.id_empresa,
       es.id                                                        AS id_establecimiento,
       LPAD(REGEXP_REPLACE(es.codigo,      '[^0-9]', '', 'g'), 3, '0') AS establecimiento,
       p.id                                                         AS id_punto_emision,
       LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') AS punto_emision
  FROM empresa_punto_emision p
  INNER JOIN empresa_establecimiento es ON es.id = p.id_establecimiento
 WHERE es.eliminado = false
   AND p.eliminado  = false
   AND NOT EXISTS (
        SELECT 1 FROM empresa_secuencial s
         WHERE s.id_punto_emision = p.id
           AND s.eliminado = false
           AND s.tipo_documento = 'Facturas de reembolso'
   )
 ORDER BY es.id_empresa,
          CASE WHEN LOWER(COALESCE(es.estado, '')) = 'activo' THEN 0 ELSE 1 END,
          CASE WHEN LOWER(COALESCE(p.estado,  '')) = 'activo' THEN 0 ELSE 1 END,
          LPAD(REGEXP_REPLACE(es.codigo,      '[^0-9]', '', 'g'), 3, '0'),
          LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0'),
          p.id;

SELECT 'Serie por defecto por empresa' AS reporte, id_empresa,
       establecimiento || '-' || punto_emision AS serie
  FROM _serie_defecto ORDER BY id_empresa;


-- ── 2. Ingresos ──────────────────────────────────────────────────────────────────────
-- El viejo (ingresos_egresos) solo guarda numero_ing_egr. Se completa la serie y se recompone
-- numero_ingreso al formato de los nativos (EEE-PPP-SSSSSSSSS).
UPDATE ingresos_cabecera i
   SET id_establecimiento = COALESCE(i.id_establecimiento, sd.id_establecimiento),
       id_punto_emision   = COALESCE(i.id_punto_emision,   sd.id_punto_emision),
       establecimiento    = COALESCE(NULLIF(i.establecimiento, ''), sd.establecimiento),
       punto_emision      = COALESCE(NULLIF(i.punto_emision,   ''), sd.punto_emision),
       numero_ingreso     = COALESCE(NULLIF(i.establecimiento, ''), sd.establecimiento) || '-'
                         || COALESCE(NULLIF(i.punto_emision,   ''), sd.punto_emision)   || '-'
                         || LPAD(REGEXP_REPLACE(i.secuencial, '[^0-9]', '', 'g'), 9, '0'),
       updated_at = now()
  FROM _serie_defecto sd
 WHERE sd.id_empresa = i.id_empresa
   AND i.eliminado   = false
   AND COALESCE(i.secuencial, '') <> ''
   AND (i.id_punto_emision IS NULL
        OR COALESCE(i.establecimiento, '') = ''
        OR COALESCE(i.punto_emision,   '') = '');


-- ── 3. Egresos ───────────────────────────────────────────────────────────────────────
-- Igual que ingresos. egresos_cabecera no tiene columna id_establecimiento.
UPDATE egresos_cabecera e
   SET id_punto_emision = COALESCE(e.id_punto_emision, sd.id_punto_emision),
       establecimiento  = COALESCE(NULLIF(e.establecimiento, ''), sd.establecimiento),
       punto_emision    = COALESCE(NULLIF(e.punto_emision,   ''), sd.punto_emision),
       numero_egreso    = COALESCE(NULLIF(e.establecimiento, ''), sd.establecimiento) || '-'
                       || COALESCE(NULLIF(e.punto_emision,   ''), sd.punto_emision)   || '-'
                       || LPAD(REGEXP_REPLACE(e.secuencial, '[^0-9]', '', 'g'), 9, '0'),
       updated_at = now()
  FROM _serie_defecto sd
 WHERE sd.id_empresa = e.id_empresa
   AND e.eliminado   = false
   AND COALESCE(e.secuencial, '') <> ''
   AND (e.id_punto_emision IS NULL
        OR COALESCE(e.establecimiento, '') = ''
        OR COALESCE(e.punto_emision,   '') = '');


-- ── 4. Pedidos ───────────────────────────────────────────────────────────────────────
-- pedidos_cabecera tiene uq_pedidos_secuencial (id_empresa, id_punto_emision, secuencial,
-- tipo_ambiente) WHERE eliminado = false. Hoy no protege a los migrados porque su
-- id_punto_emision es NULL (en un índice único, dos NULL no colisionan), y el sistema viejo
-- numera POR ESTABLECIMIENTO: el mismo número puede existir dos veces. Por eso se excluyen:
--   a) los que chocarían con un pedido que YA tiene ese número en el punto destino, y
--   b) los duplicados dentro del propio lote (se conserva el de menor id).
-- Lo excluido queda sin serie y se lista en la sección 8 para resolverlo a mano.
UPDATE pedidos_cabecera p
   SET id_establecimiento = COALESCE(p.id_establecimiento, sd.id_establecimiento),
       id_punto_emision   = sd.id_punto_emision,
       establecimiento    = COALESCE(NULLIF(p.establecimiento, ''), sd.establecimiento),
       punto_emision      = COALESCE(NULLIF(p.punto_emision,   ''), sd.punto_emision),
       updated_at = now()
  FROM _serie_defecto sd
 WHERE sd.id_empresa      = p.id_empresa
   AND p.eliminado        = false
   AND p.id_punto_emision IS NULL
   AND COALESCE(p.secuencial, '') <> ''
   AND NOT EXISTS (
        SELECT 1 FROM pedidos_cabecera o
         WHERE o.id_empresa       = p.id_empresa
           AND o.id_punto_emision = sd.id_punto_emision
           AND o.secuencial       = p.secuencial
           AND o.tipo_ambiente IS NOT DISTINCT FROM p.tipo_ambiente
           AND o.eliminado = false
   )
   AND p.id = (
        SELECT MIN(q.id) FROM pedidos_cabecera q
         WHERE q.id_empresa       = p.id_empresa
           AND q.eliminado        = false
           AND q.id_punto_emision IS NULL
           AND q.secuencial       = p.secuencial
           AND q.tipo_ambiente IS NOT DISTINCT FROM p.tipo_ambiente
   );


-- ── 5. Cambios de producto ───────────────────────────────────────────────────────────
-- cambio_productos_facturados no tiene número ni serie en el viejo (el secuencial se derivó
-- del id_cambio). Se completa la serie activa de la empresa.
UPDATE cambios_producto_cv c
   SET id_punto_emision = COALESCE(c.id_punto_emision, sd.id_punto_emision),
       establecimiento  = COALESCE(NULLIF(c.establecimiento, ''), sd.establecimiento),
       punto_emision    = COALESCE(NULLIF(c.punto_emision,   ''), sd.punto_emision),
       serie            = COALESCE(NULLIF(c.establecimiento, ''), sd.establecimiento) || '-'
                       || COALESCE(NULLIF(c.punto_emision,   ''), sd.punto_emision),
       updated_at = now()
  FROM _serie_defecto sd
 WHERE sd.id_empresa = c.id_empresa
   AND c.eliminado   = false
   AND (c.id_punto_emision IS NULL
        OR COALESCE(c.establecimiento, '') = ''
        OR COALESCE(c.punto_emision,   '') = '');


-- ── 6. Consignaciones y derivados: enlazar el punto REAL de su serie ─────────────────
-- Estos documentos SÍ traen serie propia del viejo (serie_sucursal) y la migración la guardó en
-- establecimiento/punto_emision — pero dejó id_punto_emision en NULL. No se les asigna la serie
-- por defecto: se enlaza el punto que corresponde a la serie que ya tienen.
UPDATE consignaciones_ventas c
   SET id_punto_emision = p.id, updated_at = now()
  FROM empresa_punto_emision p
  INNER JOIN empresa_establecimiento es ON es.id = p.id_establecimiento
 WHERE c.id_punto_emision IS NULL
   AND c.eliminado = false
   AND es.id_empresa = c.id_empresa
   AND es.eliminado = false AND p.eliminado = false
   AND COALESCE(c.establecimiento, '') <> '' AND COALESCE(c.punto_emision, '') <> ''
   AND LPAD(REGEXP_REPLACE(es.codigo,      '[^0-9]', '', 'g'), 3, '0') = LPAD(REGEXP_REPLACE(c.establecimiento, '[^0-9]', '', 'g'), 3, '0')
   AND LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') = LPAD(REGEXP_REPLACE(c.punto_emision,   '[^0-9]', '', 'g'), 3, '0');

UPDATE retornos_cv r
   SET id_punto_emision = p.id, updated_at = now()
  FROM empresa_punto_emision p
  INNER JOIN empresa_establecimiento es ON es.id = p.id_establecimiento
 WHERE r.id_punto_emision IS NULL
   AND r.eliminado = false
   AND es.id_empresa = r.id_empresa
   AND es.eliminado = false AND p.eliminado = false
   AND COALESCE(r.establecimiento, '') <> '' AND COALESCE(r.punto_emision, '') <> ''
   AND LPAD(REGEXP_REPLACE(es.codigo,      '[^0-9]', '', 'g'), 3, '0') = LPAD(REGEXP_REPLACE(r.establecimiento, '[^0-9]', '', 'g'), 3, '0')
   AND LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') = LPAD(REGEXP_REPLACE(r.punto_emision,   '[^0-9]', '', 'g'), 3, '0');

UPDATE consignaciones_facturas f
   SET id_punto_emision = p.id, updated_at = now()
  FROM empresa_punto_emision p
  INNER JOIN empresa_establecimiento es ON es.id = p.id_establecimiento
 WHERE f.id_punto_emision IS NULL
   AND f.eliminado = false
   AND es.id_empresa = f.id_empresa
   AND es.eliminado = false AND p.eliminado = false
   AND COALESCE(f.establecimiento, '') <> '' AND COALESCE(f.punto_emision, '') <> ''
   AND LPAD(REGEXP_REPLACE(es.codigo,      '[^0-9]', '', 'g'), 3, '0') = LPAD(REGEXP_REPLACE(f.establecimiento, '[^0-9]', '', 'g'), 3, '0')
   AND LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') = LPAD(REGEXP_REPLACE(f.punto_emision,   '[^0-9]', '', 'g'), 3, '0');


-- ── 7. Consignaciones y derivados sin serie utilizable → serie por defecto ───────────
-- Sobrantes de la sección 6: sin establecimiento/punto en el texto, o con una serie que no
-- corresponde a ningún punto existente de la empresa.
UPDATE consignaciones_ventas c
   SET id_punto_emision = sd.id_punto_emision,
       establecimiento  = COALESCE(NULLIF(c.establecimiento, ''), sd.establecimiento),
       punto_emision    = COALESCE(NULLIF(c.punto_emision,   ''), sd.punto_emision),
       serie            = COALESCE(NULLIF(c.establecimiento, ''), sd.establecimiento) || '-'
                       || COALESCE(NULLIF(c.punto_emision,   ''), sd.punto_emision),
       updated_at = now()
  FROM _serie_defecto sd
 WHERE sd.id_empresa = c.id_empresa AND c.eliminado = false AND c.id_punto_emision IS NULL;

UPDATE retornos_cv r
   SET id_punto_emision = sd.id_punto_emision,
       establecimiento  = COALESCE(NULLIF(r.establecimiento, ''), sd.establecimiento),
       punto_emision    = COALESCE(NULLIF(r.punto_emision,   ''), sd.punto_emision),
       serie            = COALESCE(NULLIF(r.establecimiento, ''), sd.establecimiento) || '-'
                       || COALESCE(NULLIF(r.punto_emision,   ''), sd.punto_emision),
       updated_at = now()
  FROM _serie_defecto sd
 WHERE sd.id_empresa = r.id_empresa AND r.eliminado = false AND r.id_punto_emision IS NULL;

UPDATE consignaciones_facturas f
   SET id_punto_emision = sd.id_punto_emision,
       establecimiento  = COALESCE(NULLIF(f.establecimiento, ''), sd.establecimiento),
       punto_emision    = COALESCE(NULLIF(f.punto_emision,   ''), sd.punto_emision),
       serie            = COALESCE(NULLIF(f.establecimiento, ''), sd.establecimiento) || '-'
                       || COALESCE(NULLIF(f.punto_emision,   ''), sd.punto_emision),
       updated_at = now()
  FROM _serie_defecto sd
 WHERE sd.id_empresa = f.id_empresa AND f.eliminado = false AND f.id_punto_emision IS NULL;


-- ── 8. Qué quedó SIN completar (revisar a mano) ──────────────────────────────────────
SELECT 'Pendiente de serie' AS reporte, tabla, id_empresa, id, secuencial, motivo FROM (
    SELECT 'ingresos_cabecera'   AS tabla, id_empresa, id, secuencial,
           CASE WHEN COALESCE(secuencial,'') = '' THEN 'sin secuencial' ELSE 'empresa sin punto de emisión' END AS motivo
      FROM ingresos_cabecera   WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL
    SELECT 'egresos_cabecera', id_empresa, id, secuencial,
           CASE WHEN COALESCE(secuencial,'') = '' THEN 'sin secuencial' ELSE 'empresa sin punto de emisión' END
      FROM egresos_cabecera    WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL
    SELECT 'pedidos_cabecera', id_empresa, id, secuencial,
           CASE WHEN COALESCE(secuencial,'') = '' THEN 'sin secuencial'
                ELSE 'número ya usado en el punto destino (el viejo numeraba por establecimiento)' END
      FROM pedidos_cabecera    WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL
    SELECT 'cambios_producto_cv', id_empresa, id, secuencial, 'empresa sin punto de emisión'
      FROM cambios_producto_cv WHERE eliminado = false AND id_punto_emision IS NULL
) x ORDER BY tabla, id_empresa, id;


-- ── 9. Auditoría: documentos electrónicos sin serie (NO se tocan) ────────────────────
-- Deberían venir siempre con la serie autorizada del SRI. Si esta consulta devuelve filas,
-- hay que revisarlas una a una: asignarles la serie por defecto falsearía su numeración.
SELECT 'Electrónico sin serie (revisar)' AS reporte, tabla, id_empresa, id, secuencial FROM (
    SELECT 'ventas_cabecera'           AS tabla, id_empresa, id, secuencial FROM ventas_cabecera           WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL SELECT 'notas_credito_cabecera',    id_empresa, id, secuencial FROM notas_credito_cabecera    WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL SELECT 'nota_debito_cabecera',      id_empresa, id, secuencial FROM nota_debito_cabecera      WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL SELECT 'recibos_venta_cabecera',    id_empresa, id, secuencial FROM recibos_venta_cabecera    WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL SELECT 'proformas_cabecera',        id_empresa, id, secuencial FROM proformas_cabecera        WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL SELECT 'guias_remision_cabecera',   id_empresa, id, secuencial FROM guias_remision_cabecera   WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL SELECT 'liquidaciones_cabecera',    id_empresa, id, secuencial FROM liquidaciones_cabecera    WHERE eliminado = false AND id_punto_emision IS NULL
    UNION ALL SELECT 'retencion_compra_cabecera', id_empresa, id, secuencial FROM retencion_compra_cabecera WHERE eliminado = false AND id_punto_emision IS NULL
) y ORDER BY tabla, id_empresa, id;

COMMIT;
