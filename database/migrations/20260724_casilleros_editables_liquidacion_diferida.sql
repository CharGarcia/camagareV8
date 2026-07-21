-- =============================================================================
-- Casilleros editables genéricos + Liquidación diferida de IVA por ventas a
-- plazo (casilleros 480-499, artículo 67 LRTI)
-- ----------------------------------------------------------------------------
-- 1) 'editable' en sri_casilleros_etiquetas: propiedad genérica configurable
--    desde /config/sri-casilleros-etiquetas (reemplaza el mecanismo ad-hoc que
--    usaba fuente_valor='arrastre_saliente_*' solo para 615/617).
-- 2) 'usa_liquidacion_diferida_iva' en empresas: interruptor por empresa,
--    APAGADO por defecto. Solo cuando está encendido el 499 (impuesto a
--    liquidar este mes) sustituye al IVA en ventas neto en el cálculo del
--    IVA a pagar. Con el interruptor apagado (la mayoría de empresas), el
--    cálculo de iva_a_pagar queda exactamente igual que antes de este cambio.
-- 3) Columnas nuevas en declaracion_iva_cabecera para persistir 480-499.
-- 4) Casilleros 480/481/483/484/485/499 ya existían en el catálogo pero sin
--    fórmula ni fuente real (siempre mostraban 0); se completan sus fórmulas
--    y se marcan como editables los que corresponde. Se agregan 482 y 486,
--    que faltaban.
--
-- Idempotente, aditivo.
-- =============================================================================

BEGIN;

ALTER TABLE sri_casilleros_etiquetas
    ADD COLUMN IF NOT EXISTS editable BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS usa_liquidacion_diferida_iva BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE declaracion_iva_cabecera
    ADD COLUMN IF NOT EXISTS transferencias_contado    NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS transferencias_credito     NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS mes_pago_credito           SMALLINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS liquidacion_diferida_483   NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS liquidacion_diferida_484   NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS liquidacion_diferida_485   NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS liquidacion_diferida_499   NUMERIC(14,2) NOT NULL DEFAULT 0;

-- -----------------------------------------------------------------------------
-- Casilleros existentes (480/481/483/484/485/499): completar fórmula/editable.
-- 615/617 (arrastre saliente de crédito tributario) migran al mismo flag genérico
-- 'editable', reemplazando el chequeo específico por fuente_valor que tenían antes.
-- -----------------------------------------------------------------------------
UPDATE sri_casilleros_etiquetas SET editable = true
 WHERE casillero_bruto IN ('480', '481', '484', '486', '615', '617') AND eliminado = false;

UPDATE sri_casilleros_etiquetas SET formula_bruto = '482-484'
 WHERE casillero_bruto = '485' AND eliminado = false;

UPDATE sri_casilleros_etiquetas SET formula_bruto = '483+484'
 WHERE casillero_bruto = '499' AND eliminado = false;

-- -----------------------------------------------------------------------------
-- Casilleros faltantes: 482 (= trasládese campo 429) y 486 (mes de pago).
-- -----------------------------------------------------------------------------
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, formula_bruto, editable, eliminado, created_at)
SELECT '482', '400_LIQ', 'Total impuesto generado (trasládese campo 429)', 115, 0, false, 'valor', 'documentos', '429', false, false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '482' AND eliminado = false);

INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, editable, eliminado, created_at)
SELECT '486', '400_LIQ', 'Mes en que se paga el IVA de transferencias a crédito de este mes', 155, 0, false, 'valor', 'documentos', true, false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '486' AND eliminado = false);

COMMIT;
