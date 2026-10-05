-- =============================================================================
-- 20261005_establecimiento_mostrar_columna_adicional.sql
-- Empresa → Facturación: interruptor "Mostrar la columna Adicional en la factura"
-- -----------------------------------------------------------------------------
-- Qué hace  : agrega a empresa_establecimiento la columna
--             mostrar_columna_adicional_factura VARCHAR(10) DEFAULT 'true'.
-- Por qué    : la columna "Adicional" (detalle adicional por ítem) de la tabla de
--             ítems del modal de Factura de Venta ocupa espacio en empresas que no
--             la usan. Con este interruptor cada establecimiento decide si se ve.
--             Por defecto se muestra (como hasta ahora); solo se oculta si el
--             usuario lo apaga. Lo guardado en info_adicional no cambia.
-- Toca datos: NO (solo agrega la columna con su valor por defecto).
-- Orden      : primero este SQL, luego el código. El código tolera que la columna
--             no exista todavía (la trata como 'true').
-- Cómo       : pgAdmin → Query Tool sobre producción → pegar → F5. Idempotente.
-- =============================================================================
ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS mostrar_columna_adicional_factura VARCHAR(10) DEFAULT 'true';

-- Comprobación (opcional): debe devolver una fila
-- SELECT column_name, column_default FROM information_schema.columns
--  WHERE table_name = 'empresa_establecimiento' AND column_name = 'mostrar_columna_adicional_factura';
