-- =============================================================================
-- Integración de Importaciones con la Declaración de IVA
-- ----------------------------------------------------------------------------
-- El IVA pagado en aduana al nacionalizar una importación (importaciones_gastos,
-- tipo_gasto='iva_importacion', origen='dai_manual') es crédito tributario real
-- (casillero oficial 605/615 "adquisiciones E IMPORTACIONES"), pero hasta ahora
-- no se sumaba en ningún lado del módulo de Declaración de IVA.
--
-- 1) 'deducible' en importaciones_cabecera: mismo mecanismo que ya tiene
--    compras_cabecera, para poder excluir una importación puntual del cálculo
--    de IVA si hiciera falta. Por defecto SIEMPRE deducible.
-- 2) Casillero visual: se agrega 'importacion' como tipo_documento en la pestaña
--    Form 104 IVA de Empresa (tabla empresa_casilleros_iva_sri, ya genérica por
--    tipo_documento -- no requiere cambio de esquema, solo lo habilita la vista).
--
-- Idempotente, aditivo.
-- =============================================================================

BEGIN;

ALTER TABLE importaciones_cabecera
    ADD COLUMN IF NOT EXISTS deducible VARCHAR(30) NOT NULL DEFAULT 'declaracion_iva';

-- Casillero visual (sección 500) para el IVA en importaciones, oficial 504-505
-- ("Importaciones de bienes gravados tarifa diferente de cero"). Solo se usa el
-- de impuesto (505): no hay base imponible desglosada en importaciones_gastos.
INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, eliminado, created_at)
SELECT '504', '500', 'Importaciones de bienes gravados tarifa diferente de cero (base referencial)', 65, 0, false, 'valor', 'documentos', false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '504' AND eliminado = false);

INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, eliminado, created_at)
SELECT '505', '500', 'Importaciones de bienes gravados tarifa diferente de cero (IVA / crédito tributario)', 70, 0, true, 'valor', 'documentos', false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '505' AND eliminado = false);

COMMIT;
