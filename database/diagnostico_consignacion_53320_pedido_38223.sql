-- =====================================================================
-- Diagnóstico (SOLO LECTURA): consignación 53320 vs. pedido 38223,
-- producto OSGD 05, empresa RUC 1792708389001.
-- El pedido marca "Parcial 4/5" porque suma únicamente las líneas de
-- consignación que llevan id_pedido_detalle apuntando a SU línea.
-- =====================================================================

-- 1) Líneas del producto en la consignación y a qué línea de pedido apuntan.
--    Si una sale con id_pedido_detalle NULL (o con otro pedido), esa es la unidad suelta.
SELECT cv.id AS id_consignacion,
       cv.establecimiento || '-' || cv.punto_emision || '-' || cv.secuencial AS consignacion,
       cv.estado,
       cvd.id AS id_linea_cons, cvd.cantidad, cvd.lote, cvd.fecha_caducidad, cvd.nup,
       cvd.id_pedido_detalle,
       p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial AS pedido_vinculado
FROM consignaciones_ventas cv
JOIN empresas e                     ON e.id = cv.id_empresa
JOIN consignaciones_ventas_detalles cvd ON cvd.id_consignacion = cv.id AND cvd.eliminado = false
JOIN productos pr                   ON pr.id = cvd.id_producto
LEFT JOIN pedidos_detalle pd        ON pd.id = cvd.id_pedido_detalle
LEFT JOIN pedidos_cabecera p        ON p.id = pd.id_pedido
WHERE e.ruc = '1792708389001'
  AND cv.eliminado = false
  AND ltrim(cv.secuencial, '0') = '53320'
  AND pr.codigo = 'OSGD 05'
ORDER BY cvd.id;

-- 2) Líneas del producto en el pedido, con lo que cada una tiene consumido.
SELECT p.id AS id_pedido,
       p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial AS pedido,
       pd.id AS id_pedido_detalle, pd.cantidad,
       COALESCE((SELECT SUM(cvd.cantidad)
                 FROM consignaciones_ventas_detalles cvd
                 JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                 WHERE cvd.id_pedido_detalle = pd.id
                   AND cv.eliminado = false AND cvd.eliminado = false), 0) AS consignado
FROM pedidos_cabecera p
JOIN empresas e         ON e.id = p.id_empresa
JOIN pedidos_detalle pd ON pd.id_pedido = p.id AND pd.eliminado = false
JOIN productos pr       ON pr.id = pd.id_producto
WHERE e.ruc = '1792708389001'
  AND p.eliminado = false
  AND ltrim(p.secuencial, '0') = '38223'
  AND pr.codigo = 'OSGD 05';

-- 3) Historial de la consignación: si hay ACTUALIZAR_CONSIGNACION después de crearla,
--    la unidad suelta pudo agregarse a mano en una edición posterior.
SELECT ls.created_at, ls.id_usuario, ls.accion
FROM log_sistema ls
JOIN consignaciones_ventas cv ON cv.id = ls.id_registro
JOIN empresas e ON e.id = cv.id_empresa
WHERE ls.tabla_afectada = 'consignaciones_ventas'
  AND e.ruc = '1792708389001'
  AND ltrim(cv.secuencial, '0') = '53320'
ORDER BY ls.created_at;

-- =====================================================================
-- 4) CORRECCIÓN — correr SOLO si la consulta 1 mostró la línea con
--    id_pedido_detalle NULL y la 2 mostró una sola línea de pedido con saldo.
--    Vincula las líneas sueltas de OSGD 05 de la consignación 53320 a la
--    línea del pedido 38223, sin pasar del saldo pendiente de esa línea.
--    Si no se cumple todo, no actualiza nada (0 filas).
--    En los pasos 4 y 5, reemplazar <ID_USUARIO> por el id del superadmin que corrige.
-- =====================================================================
BEGIN;

WITH emp AS (
    SELECT id FROM empresas WHERE ruc = '1792708389001'
), linea_pedido AS (
    SELECT pd.id, pd.cantidad
    FROM pedidos_cabecera p
    JOIN pedidos_detalle pd ON pd.id_pedido = p.id AND pd.eliminado = false
    JOIN productos pr       ON pr.id = pd.id_producto
    WHERE p.id_empresa = (SELECT id FROM emp)
      AND p.eliminado = false
      AND ltrim(p.secuencial, '0') = '38223'
      AND pr.codigo = 'OSGD 05'
), unica AS (
    -- Solo si el producto aparece en UNA línea del pedido (si hay dos, hay que elegir a mano).
    SELECT id, cantidad FROM linea_pedido WHERE (SELECT COUNT(*) FROM linea_pedido) = 1
), consumido AS (
    SELECT COALESCE(SUM(cvd.cantidad), 0) AS cant
    FROM consignaciones_ventas_detalles cvd
    JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
    WHERE cvd.id_pedido_detalle = (SELECT id FROM unica)
      AND cv.eliminado = false AND cvd.eliminado = false
), sueltas AS (
    SELECT cvd.id, cvd.cantidad
    FROM consignaciones_ventas cv
    JOIN consignaciones_ventas_detalles cvd ON cvd.id_consignacion = cv.id AND cvd.eliminado = false
    JOIN productos pr ON pr.id = cvd.id_producto
    WHERE cv.id_empresa = (SELECT id FROM emp)
      AND cv.eliminado = false
      AND ltrim(cv.secuencial, '0') = '53320'
      AND pr.codigo = 'OSGD 05'
      AND cvd.id_pedido_detalle IS NULL
), upd AS (
    UPDATE consignaciones_ventas_detalles d
    SET id_pedido_detalle = (SELECT id FROM unica)
    FROM sueltas s
    WHERE d.id = s.id
      AND (SELECT id FROM unica) IS NOT NULL
      AND (SELECT cant FROM consumido) + (SELECT SUM(cantidad) FROM sueltas) <= (SELECT cantidad FROM unica)
    RETURNING d.id, d.id_consignacion, d.id_empresa, d.cantidad, d.id_pedido_detalle
)
INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro, datos_anteriores, datos_nuevos, ip_usuario, user_agent)
SELECT <ID_USUARIO>, upd.id_empresa, 'VINCULAR_LINEA_PEDIDO', 'consignaciones_ventas_detalles', upd.id,
       jsonb_build_object('id_pedido_detalle', NULL, 'id_consignacion', upd.id_consignacion),
       jsonb_build_object('id_pedido_detalle', upd.id_pedido_detalle, 'id_consignacion', upd.id_consignacion, 'cantidad', upd.cantidad),
       '127.0.0.1', 'SQL manual (corrección vínculo pedido)'
FROM upd
RETURNING id_registro AS id_linea_cons, datos_nuevos;

-- 5) Recalcular el estado del pedido (misma regla que
--    ConsignacionVentaRepository::actualizarEstadoPedidosPorConsignado) y dejar rastro en
--    log_sistema. Reemplazar <ID_USUARIO> por el id del superadmin que hace la corrección
--    (sin reemplazarlo, la sentencia falla y no cambia nada).
WITH ped AS (
    SELECT p.id, p.id_empresa, p.estado
    FROM pedidos_cabecera p
    JOIN empresas e ON e.id = p.id_empresa
    WHERE e.ruc = '1792708389001' AND p.eliminado = false
      AND ltrim(p.secuencial, '0') = '38223'
), calc AS (
    SELECT ped.id, ped.id_empresa, ped.estado AS estado_anterior,
           CASE WHEN COALESCE(BOOL_AND(COALESCE(c.cant, 0) >= pd.cantidad), true)
                THEN 'Procesado' ELSE 'Pendiente' END AS nuevo_estado
    FROM ped
    LEFT JOIN pedidos_detalle pd ON pd.id_pedido = ped.id AND pd.eliminado = false
    LEFT JOIN LATERAL (
        SELECT SUM(cvd.cantidad) AS cant
        FROM consignaciones_ventas_detalles cvd
        JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
        WHERE cvd.id_pedido_detalle = pd.id AND cvd.eliminado = false
    ) c ON true
    GROUP BY ped.id, ped.id_empresa, ped.estado
), upd AS (
    UPDATE pedidos_cabecera p
    SET estado = calc.nuevo_estado, updated_at = CURRENT_TIMESTAMP, updated_by = <ID_USUARIO>
    FROM calc
    WHERE p.id = calc.id AND p.estado IS DISTINCT FROM calc.nuevo_estado
    RETURNING p.id, p.id_empresa, calc.estado_anterior, calc.nuevo_estado
)
INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro, datos_anteriores, datos_nuevos, ip_usuario, user_agent)
SELECT <ID_USUARIO>, upd.id_empresa, 'ESTADO_PEDIDO_POR_CONSIGNACION', 'pedidos_cabecera', upd.id,
       jsonb_build_object('estado', upd.estado_anterior, 'consignacion', '53320', 'motivo', 'Vínculo manual de línea OSGD 05'),
       jsonb_build_object('estado', upd.nuevo_estado,    'consignacion', '53320', 'motivo', 'Vínculo manual de línea OSGD 05'),
       '127.0.0.1', 'SQL manual (corrección vínculo pedido)'
FROM upd
RETURNING id_registro, datos_anteriores, datos_nuevos;

-- Revisar ambos RETURNING: si es lo esperado, COMMIT; si no, ROLLBACK.
-- COMMIT;
-- ROLLBACK;
