-- ============================================================================
-- Órdenes de Compra: estado "enviado" + aprobación del proveedor por correo
-- ============================================================================
-- Nuevo ciclo de vida:
--   borrador → [Enviar correo] → enviado (solo lectura desde aquí)
--            → [proveedor aprueba por correo, o botón "Aprobar" manual] → aprobado
--            → [se vincula con una compra desde Compras] → recibido
--   anulado puede darse desde borrador.
--
-- La columna estado ya es VARCHAR(20) sin CHECK, así que el nuevo valor
-- 'enviado' no requiere alterar su tipo — solo agregamos las columnas de
-- seguimiento de envío/aprobación, mismo patrón que
-- 20260728_proforma_aprobacion_cliente.sql.
-- ============================================================================

ALTER TABLE ordenes_compra
    ADD COLUMN IF NOT EXISTS aprobacion_token VARCHAR(64),
    ADD COLUMN IF NOT EXISTS fecha_envio      TIMESTAMP,
    ADD COLUMN IF NOT EXISTS fecha_aprobacion TIMESTAMP,
    ADD COLUMN IF NOT EXISTS aprobado_por     VARCHAR(150),
    ADD COLUMN IF NOT EXISTS aprobacion_ip    VARCHAR(45);

CREATE UNIQUE INDEX IF NOT EXISTS uq_ordenes_compra_aprob_token
    ON ordenes_compra (aprobacion_token)
    WHERE aprobacion_token IS NOT NULL;
