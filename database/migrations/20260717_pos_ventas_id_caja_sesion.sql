-- =====================================================================
-- Módulo Punto de Venta (POS) — Vínculo venta ↔ turno de caja
--
-- Hasta ahora la única relación entre una Factura/Recibo generado por el
-- POS y el turno (caja_sesiones) que la cobró era un texto libre en
-- observaciones ("Venta POS — turno #N"). Esta columna da un enlace real,
-- necesario para el módulo Reportes POS (resumen de turnos/arqueo, ventas
-- por cajero, etc.). Es NULLABLE y solo se llena en ventas hechas desde el
-- POS: las facturas/recibos del flujo normal del sistema quedan en NULL.
--
-- Aplicar manualmente (dev y luego producción), como el resto de módulos
-- del sistema.
-- =====================================================================

ALTER TABLE ventas_cabecera
    ADD COLUMN IF NOT EXISTS id_caja_sesion INTEGER NULL
    REFERENCES caja_sesiones(id);

ALTER TABLE recibos_venta_cabecera
    ADD COLUMN IF NOT EXISTS id_caja_sesion INTEGER NULL
    REFERENCES caja_sesiones(id);

CREATE INDEX IF NOT EXISTS ix_ventas_cabecera_caja_sesion
    ON ventas_cabecera (id_caja_sesion) WHERE id_caja_sesion IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_recibos_venta_cabecera_caja_sesion
    ON recibos_venta_cabecera (id_caja_sesion) WHERE id_caja_sesion IS NOT NULL;

-- Backfill (idempotente, se puede correr varias veces): ventas del POS hechas
-- ANTES de esta migración solo tienen el turno en el texto de observaciones
-- ("Venta POS — turno #N"). Se recupera de ahí para que también aparezcan en
-- Reportes POS sin tener que volver a cobrarlas.
UPDATE ventas_cabecera
SET id_caja_sesion = (regexp_match(observaciones, 'turno #(\d+)'))[1]::int
WHERE id_caja_sesion IS NULL
  AND observaciones LIKE 'Venta POS — turno #%';

UPDATE recibos_venta_cabecera
SET id_caja_sesion = (regexp_match(observaciones, 'turno #(\d+)'))[1]::int
WHERE id_caja_sesion IS NULL
  AND observaciones LIKE 'Venta POS — turno #%';
