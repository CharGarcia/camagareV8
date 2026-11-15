-- ============================================================================
-- Cotizador de Planes — MÓDULOS INCLUIDOS y PAQUETES CON PRECIO FIJO.
--
-- Ejecutar DESPUÉS de 2026-11-15_cotizador_planes_paquetes.sql. Sin este SQL la
-- pantalla del cotizador avisa que falta instalarlo.
--
-- Reglas (aprobadas 15-11-2026):
--  - Cada módulo tiene su precio cuando se vende solo. Con la casilla
--    «sin_costo_dependencia», el módulo va a $0 cuando lo requiere otro módulo
--    de la cotización (p. ej. Clientes al elegir Facturas).
--  - Cada paquete elige cómo se cobra:
--      'descuento' → suma de sus módulos menos descuento_pct (como hasta ahora);
--      'fijo'      → precio_anual propio; sus módulos recurrentes van incluidos
--                    a $0 (los pagos únicos del paquete se cobran aparte).
--
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

ALTER TABLE cotizador_planes_items
    ADD COLUMN IF NOT EXISTS sin_costo_dependencia BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE cotizador_planes_paquetes
    ADD COLUMN IF NOT EXISTS tipo_precio  VARCHAR(10)   NOT NULL DEFAULT 'descuento';
ALTER TABLE cotizador_planes_paquetes
    ADD COLUMN IF NOT EXISTS precio_anual NUMERIC(14,2) NOT NULL DEFAULT 0;
ALTER TABLE cotizador_planes_paquetes DROP CONSTRAINT IF EXISTS ck_cot_paq_tipo_precio;
ALTER TABLE cotizador_planes_paquetes
    ADD CONSTRAINT ck_cot_paq_tipo_precio CHECK (tipo_precio IN ('descuento', 'fijo') AND precio_anual >= 0);

-- Copia en la cotización (un cambio posterior del paquete no altera lo cotizado).
ALTER TABLE cotizaciones_planes_establecimientos
    ADD COLUMN IF NOT EXISTS paquete_tipo_precio  VARCHAR(10)   NOT NULL DEFAULT 'descuento';
ALTER TABLE cotizaciones_planes_establecimientos
    ADD COLUMN IF NOT EXISTS paquete_precio_anual NUMERIC(14,2) NOT NULL DEFAULT 0;

-- Ítem que va a $0: incluido en un paquete de precio fijo o en otro módulo.
ALTER TABLE cotizaciones_planes_detalle
    ADD COLUMN IF NOT EXISTS incluido BOOLEAN NOT NULL DEFAULT false;
