-- =============================================================================
-- Declaración de IVA (F104): casillero 902 "Total impuesto a pagar" en el formulario
-- ----------------------------------------------------------------------------
-- Hasta ahora el "IVA a pagar" que usa el egreso salía SOLO de un cálculo interno
-- (DeclaracionIvaService::guardarDeclaracion()), sin ningún campo en el propio
-- Resumen 104 donde el usuario pudiera verlo/ajustarlo antes de guardar — a
-- diferencia de 615/617/481/484/486, que sí son editables en el formulario.
-- Este script agrega el casillero 902 a la estructura, editable, mismo patrón
-- que esos: se autocalcula por defecto, pero el usuario puede sobreescribirlo
-- en el formulario, y ese valor final (autocalculado o ajustado a mano) es el
-- que se guarda en total_a_pagar y usa el egreso.
--
-- Aditivo y no destructivo. Idempotente.
-- =============================================================================

BEGIN;

INSERT INTO sri_casilleros_etiquetas (casillero_bruto, seccion, descripcion, orden, indent, bold, tipo, fuente_valor, editable, eliminado, created_at)
SELECT '902', '400_LIQ', 'Total impuesto a pagar', 170, 0, true, 'valor', 'documentos', true, false, now()
WHERE NOT EXISTS (SELECT 1 FROM sri_casilleros_etiquetas WHERE casillero_bruto = '902' AND eliminado = false);

COMMIT;
