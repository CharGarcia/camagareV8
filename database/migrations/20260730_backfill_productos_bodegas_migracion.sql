-- Repara empresas migradas desde el sistema anterior: la migración de inventario
-- (MigracionMysqlService::migrarInventario) insertaba el historial en
-- inventario_kardex pero nunca sincronizaba productos_bodegas (la tabla de la
-- que leen las pestañas "Existencias" y "Valorización" del Reporte de
-- Inventarios). Resultado: esas dos pestañas aparecían vacías para productos
-- migrados aunque el Kardex sí mostraba sus movimientos.
--
-- Este script SOLO INSERTA filas de productos_bodegas que faltan (producto +
-- bodega con movimientos en el kardex pero sin fila en productos_bodegas). No
-- toca ninguna fila ya existente, así que es seguro de reejecutar y no puede
-- pisar un stock que ya se esté manteniendo bien por el flujo normal de la
-- aplicación.
--
-- El stock se calcula igual que en migrarInventario(): para movimientos de
-- origen 'migracion' la columna `cantidad` se guardó SIEMPRE positiva (el
-- signo lo da tipo_movimiento), mientras que en el resto del sistema
-- `cantidad` ya viene firmada (positiva = entrada, negativa = salida).

INSERT INTO productos_bodegas (id_empresa, id_producto, id_bodega, stock_actual, eliminado)
SELECT
    k.id_empresa,
    k.id_producto,
    k.id_bodega,
    SUM(
        CASE
            WHEN k.referencia_tipo = 'migracion' THEN
                CASE WHEN k.tipo_movimiento = 'entrada' THEN ABS(k.cantidad) ELSE -ABS(k.cantidad) END
            ELSE k.cantidad
        END
    ) AS stock_actual,
    false
FROM inventario_kardex k
WHERE k.eliminado = false
GROUP BY k.id_empresa, k.id_producto, k.id_bodega
HAVING NOT EXISTS (
    SELECT 1 FROM productos_bodegas pb
    WHERE pb.id_producto = k.id_producto AND pb.id_bodega = k.id_bodega
);
