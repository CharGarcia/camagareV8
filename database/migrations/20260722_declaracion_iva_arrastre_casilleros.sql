-- =============================================================================
-- Arrastre editable de crédito tributario IVA (casilleros oficiales 605/606/615/617)
-- ----------------------------------------------------------------------------
-- Hasta ahora el arrastre de saldo a favor entre declaraciones era 100% interno
-- (credito_anterior_aplicado / saldo_favor) y no aparecía como casillero del
-- formulario 104. Este script agrega el desglose por origen (adquisiciones vs.
-- retenciones), tal como lo pide el formulario oficial:
--   605/606: Saldo crédito tributario del MES ANTERIOR (entrante, solo lectura)
--   615/617: Saldo crédito tributario para el PRÓXIMO MES (saliente, editable)
-- Se omite el tercer origen oficial (608/619, compensación IVA ventas en zonas
-- afectadas - Ley de Solidaridad) por ser un régimen especial poco usado.
--
-- Aditivo y no destructivo: las columnas credito_anterior_aplicado y saldo_favor
-- ya existentes se mantienen como las SUMAS de los nuevos campos, para no romper
-- el asiento contable ni el egreso ya construidos sobre esos totales.
-- Idempotente.
-- =============================================================================

BEGIN;

ALTER TABLE declaracion_iva_cabecera
    ADD COLUMN IF NOT EXISTS credito_anterior_compras     NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS credito_anterior_retenciones NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS saldo_favor_compras          NUMERIC(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS saldo_favor_retenciones      NUMERIC(14,2) NOT NULL DEFAULT 0;

-- -----------------------------------------------------------------------------
-- Casilleros nuevos en el catálogo del formulario (sri_casilleros_etiquetas).
-- Sección '600_CRED' nueva, orden después del 499 (el más alto hoy en el catálogo).
-- -----------------------------------------------------------------------------
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, eliminado, created_at)
SELECT '605', '600_CRED', 'Saldo crédito tributario del mes anterior por adquisiciones e importaciones', 170, 0, false, 'valor', 'arrastre_entrante_compras', false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '605' AND eliminado = false);

INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, eliminado, created_at)
SELECT '606', '600_CRED', 'Saldo crédito tributario del mes anterior por retenciones en la fuente de IVA que le han sido efectuadas', 180, 0, false, 'valor', 'arrastre_entrante_retenciones', false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '606' AND eliminado = false);

INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, eliminado, created_at)
SELECT '615', '600_CRED', 'Saldo crédito tributario para el próximo mes por adquisiciones e importaciones', 190, 0, true, 'valor', 'arrastre_saliente_compras', false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '615' AND eliminado = false);

INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, eliminado, created_at)
SELECT '617', '600_CRED', 'Saldo crédito tributario para el próximo mes por retenciones en la fuente de IVA que le han sido efectuadas', 200, 0, true, 'valor', 'arrastre_saliente_retenciones', false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '617' AND eliminado = false);

COMMIT;
