-- =============================================================================
-- Cargas de Inventario: líneas con NUP mal aplicadas y cargas tipo "Ajuste"
-- (modulos/cargas-inventario)
--
-- Contexto (revisión del 17-09-2026):
--   1) NUP. Al aprobar una carga, cada línea con NUP se aplica como UN movimiento de
--      cantidad 1 por cada serie de la celda (una por renglón), sin mirar la columna
--      cantidad. Una línea "cantidad 10, NUP ABC" entró al kardex con 1 unidad; una
--      línea "cantidad 1, NUP A↵B" entró con 2. La importación nunca comparó cantidad
--      contra número de series.
--   2) AJUSTE. El manual dice "Ajuste: corrige a la cantidad indicada", pero al
--      aprobar se SUMA la cantidad (igual que una entrada). Además esos movimientos
--      quedan con tipo 'ajuste' en el kardex, y el costo promedio, FIFO/LIFO y el
--      costeo de ventas solo cuentan tipo 'entrada': su costo no entra al promedio.
--
-- Qué hace: SOLO LECTURA. No modifica nada.
--
-- Cómo usarlo en pgAdmin:
--   - Cambie el 0 de `id_empresa` en la primera línea de cada consulta por el id de
--     una empresa para ver solo esa (0 = todas).
--   - Seleccione UNA consulta (de "-- 1)" hasta su punto y coma) y ejecútela con F5.
--     Si ejecuta todo el archivo, pgAdmin muestra solo el resultado de la última.
--   - Las cargas migradas del sistema anterior no aparecen en 2) y 3): su kardex se
--     migró aparte y no está enlazado a la carga (no les afecta ninguno de los dos casos).
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1) RESUMEN por empresa
--    lineas_nup_descuadradas   : líneas (no eliminadas) cuya cantidad ≠ número de series.
--    ..._aprobadas             : de ellas, las que YA movieron el kardex.
--    ..._pendientes            : las que se aplicarían mal si se aprueban hoy.
--    cargas_ajuste_aplicadas   : cargas tipo Ajuste aprobadas con movimientos en el kardex.
--    unidades_ajuste_sumadas   : unidades que esas cargas SUMARON al stock.
-- -----------------------------------------------------------------------------
WITH p AS (SELECT 0::int AS id_empresa),
lineas_nup AS (
    SELECT c.id_empresa, c.estado,
           d.cantidad,
           (SELECT COUNT(*) FROM regexp_split_to_table(d.nup, E'\n') s
             WHERE btrim(s, E' \t\r') <> '') AS series
      FROM inventario_cargas c
      JOIN inventario_cargas_detalle d ON d.id_carga = c.id AND d.eliminado = false
     WHERE c.eliminado = false
       AND COALESCE(btrim(d.nup), '') <> ''
       AND (SELECT id_empresa FROM p) IN (0, c.id_empresa)
),
ajustes AS (
    SELECT c.id_empresa, c.id,
           SUM(k.cantidad) AS unidades
      FROM inventario_cargas c
      JOIN inventario_kardex k
        ON k.id_empresa = c.id_empresa AND k.referencia_tipo = 'carga_inventario'
       AND k.referencia_id = c.id AND k.eliminado = false
     WHERE c.eliminado = false AND c.estado = 'aprobada' AND c.tipo_movimiento = 'ajuste'
       AND (SELECT id_empresa FROM p) IN (0, c.id_empresa)
     GROUP BY c.id_empresa, c.id
)
SELECT e.id AS id_empresa, e.nombre AS empresa,
       COALESCE(n.descuadradas, 0)            AS lineas_nup_descuadradas,
       COALESCE(n.descuadradas_aprobadas, 0)  AS lineas_nup_descuadradas_aprobadas,
       COALESCE(n.descuadradas_pendientes, 0) AS lineas_nup_descuadradas_pendientes,
       COALESCE(a.cargas, 0)                  AS cargas_ajuste_aplicadas,
       COALESCE(a.unidades, 0)                AS unidades_ajuste_sumadas
  FROM empresas e
  LEFT JOIN (SELECT id_empresa,
                    COUNT(*) FILTER (WHERE cantidad <> series)                            AS descuadradas,
                    COUNT(*) FILTER (WHERE cantidad <> series AND estado = 'aprobada')    AS descuadradas_aprobadas,
                    COUNT(*) FILTER (WHERE cantidad <> series AND estado = 'pendiente')   AS descuadradas_pendientes
               FROM lineas_nup GROUP BY id_empresa) n ON n.id_empresa = e.id
  LEFT JOIN (SELECT id_empresa, COUNT(*) AS cargas, SUM(unidades) AS unidades
               FROM ajustes GROUP BY id_empresa) a ON a.id_empresa = e.id
 WHERE COALESCE(n.descuadradas, 0) + COALESCE(a.cargas, 0) > 0
 ORDER BY e.id;


-- -----------------------------------------------------------------------------
-- 2) LÍNEAS CON NUP cuya cantidad no coincide con el número de series
--    en_kardex         : lo que esa línea movió de verdad (con signo; vacío si la
--                        carga no está aprobada).
--    deberia_mover     : lo que decía la línea (con signo según el tipo de carga).
--    diferencia_stock  : en_kardex - deberia_mover. Positivo = el sistema tiene de
--                        más; negativo = tiene de menos (por esa línea).
-- -----------------------------------------------------------------------------
WITH p AS (SELECT 0::int AS id_empresa),
lineas AS (
    SELECT c.id_empresa, c.id AS id_carga, c.numero, c.tipo_movimiento, c.estado, c.aprobada_at,
           d.id AS id_linea, d.id_producto, d.id_bodega, d.cantidad, d.nup,
           ARRAY(SELECT btrim(s, E' \t\r') FROM regexp_split_to_table(d.nup, E'\n') s
                  WHERE btrim(s, E' \t\r') <> '') AS series,
           -- Fila del Excel: las líneas se guardan en el orden del archivo (fila 1 = encabezado).
           ROW_NUMBER() OVER (PARTITION BY c.id ORDER BY d.id) + 1 AS fila_excel
      FROM inventario_cargas c
      JOIN inventario_cargas_detalle d ON d.id_carga = c.id AND d.eliminado = false
     WHERE c.eliminado = false
       AND COALESCE(btrim(d.nup), '') <> ''
       AND (SELECT id_empresa FROM p) IN (0, c.id_empresa)
)
SELECT l.id_empresa, e.nombre AS empresa,
       l.numero AS carga, l.estado, l.tipo_movimiento AS tipo,
       to_char(l.aprobada_at, 'DD-MM-YYYY HH24:MI:SS') AS aprobada,
       l.fila_excel,
       pr.codigo AS codigo_producto, pr.nombre AS producto, b.nombre AS bodega,
       l.cantidad, cardinality(l.series) AS series, l.nup,
       k.en_kardex,
       CASE WHEN l.tipo_movimiento = 'salida' THEN -l.cantidad ELSE l.cantidad END AS deberia_mover,
       k.en_kardex - CASE WHEN l.tipo_movimiento = 'salida' THEN -l.cantidad ELSE l.cantidad END AS diferencia_stock
  FROM lineas l
  JOIN empresas e ON e.id = l.id_empresa
  LEFT JOIN productos pr ON pr.id = l.id_producto
  LEFT JOIN bodegas   b  ON b.id  = l.id_bodega
  LEFT JOIN LATERAL (
        SELECT SUM(kx.cantidad) AS en_kardex
          FROM inventario_kardex kx
         WHERE kx.id_empresa = l.id_empresa
           AND kx.referencia_tipo = 'carga_inventario' AND kx.referencia_id = l.id_carga
           AND kx.id_producto = l.id_producto AND kx.id_bodega = l.id_bodega
           AND kx.eliminado = false
           AND btrim(kx.nup, E' \t\r') = ANY (l.series)
       ) k ON l.estado = 'aprobada'
 WHERE l.cantidad <> cardinality(l.series)
 ORDER BY l.id_empresa, l.numero, l.fila_excel;


-- -----------------------------------------------------------------------------
-- 3) CARGAS TIPO AJUSTE APROBADAS: qué sumaron al stock
--    saldo_antes / saldo_despues : saldo del producto en la bodega justo antes y
--                                  después de cada movimiento (tomado del kardex).
--    si_era_fijar_saldo_sobra    : si la intención era dejar el saldo EN la cantidad
--                                  cargada, cuántas unidades quedaron de más por esa
--                                  línea (= saldo que ya había antes).
--    costo_fuera_del_promedio    : valor de esas unidades que NO entra al costo
--                                  promedio (movimiento tipo 'ajuste').
--    Si una carga trae varias líneas del mismo producto y bodega, el saldo_antes de
--    cada una ya incluye las anteriores.
-- -----------------------------------------------------------------------------
WITH p AS (SELECT 0::int AS id_empresa)
SELECT c.id_empresa, e.nombre AS empresa,
       c.numero AS carga,
       to_char(c.aprobada_at, 'DD-MM-YYYY HH24:MI:SS') AS aprobada,
       pr.codigo AS codigo_producto, pr.nombre AS producto, b.nombre AS bodega,
       k.numero_lote AS lote, k.nup,
       k.cantidad          AS cantidad_sumada,
       k.stock_anterior    AS saldo_antes,
       k.stock_posterior   AS saldo_despues,
       k.stock_anterior    AS si_era_fijar_saldo_sobra,
       k.tipo_movimiento   AS tipo_en_kardex,
       CASE WHEN k.tipo_movimiento <> 'entrada' THEN k.costo_total ELSE 0 END AS costo_fuera_del_promedio,
       k.id AS id_movimiento_kardex
  FROM inventario_cargas c
  JOIN empresas e ON e.id = c.id_empresa
  JOIN inventario_kardex k
    ON k.id_empresa = c.id_empresa AND k.referencia_tipo = 'carga_inventario'
   AND k.referencia_id = c.id AND k.eliminado = false
  LEFT JOIN productos pr ON pr.id = k.id_producto
  LEFT JOIN bodegas   b  ON b.id  = k.id_bodega
 WHERE c.eliminado = false AND c.estado = 'aprobada' AND c.tipo_movimiento = 'ajuste'
   AND (SELECT id_empresa FROM p) IN (0, c.id_empresa)
 ORDER BY c.id_empresa, c.numero, k.id;


-- -----------------------------------------------------------------------------
-- 4) MOVIMIENTOS 'ajuste' DEL KARDEX FUERA DEL COSTO PROMEDIO (todas las fuentes)
--    Además de las cargas, el Kardex acepta tipo 'ajuste' por su endpoint aunque la
--    pantalla solo ofrezca entrada y salida. Un total en cero = nada que revisar.
-- -----------------------------------------------------------------------------
WITH p AS (SELECT 0::int AS id_empresa)
SELECT k.id_empresa, e.nombre AS empresa,
       COALESCE(k.referencia_tipo, '(sin origen)') AS origen,
       COUNT(*)                                     AS movimientos,
       SUM(k.cantidad)                              AS unidades,
       SUM(k.costo_total)                           AS costo_total,
       to_char(MIN(k.fecha_movimiento), 'DD-MM-YYYY') AS desde,
       to_char(MAX(k.fecha_movimiento), 'DD-MM-YYYY') AS hasta
  FROM inventario_kardex k
  JOIN empresas e ON e.id = k.id_empresa
 WHERE k.tipo_movimiento = 'ajuste' AND k.eliminado = false
   AND (SELECT id_empresa FROM p) IN (0, k.id_empresa)
 GROUP BY k.id_empresa, e.nombre, k.referencia_tipo
 ORDER BY k.id_empresa, origen;
