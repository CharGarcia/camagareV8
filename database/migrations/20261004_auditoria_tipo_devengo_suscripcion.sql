-- Agrega el tipo de hallazgo 'devengo_suscripcion' a auditoria_contable_incidencias.
--
-- Auditoría Contable revisa el devengado de suscripciones (NIIF 15):
--   1. Factura/recibo cuyo asiento no acredita a «Ingresos diferidos» / «por facturar» lo que
--      dice su cronograma (asiento generado antes del cronograma, cuenta cambiada, edición a mano).
--   2. Meses ya cumplidos que siguen sin devengar (no se corrió «Devengar mes»).
--   3. Saldo del cronograma ≠ saldo del mayor en las cuentas de «Suscripciones - Devengo».
--
-- Mientras este SQL no se aplique, la auditoría simplemente omite esas revisiones.
-- Idempotente. Listo para pgAdmin (F5).

BEGIN;

ALTER TABLE auditoria_contable_incidencias
    DROP CONSTRAINT IF EXISTS chk_aci_tipo;

ALTER TABLE auditoria_contable_incidencias
    ADD CONSTRAINT chk_aci_tipo CHECK (tipo_hallazgo IN (
        'faltante',
        'monto_no_coincide',
        'monto_informativo',
        'huerfano',
        'estado_incoherente',
        'ambiente_incoherente',
        'duplicado',
        'descuadrado',
        'cab_vs_detalle',
        'devengo_suscripcion'
    ));

COMMIT;

-- ── Comprobación (ejecutar aparte) ──────────────────────────────────────────
-- Debe contener 'devengo_suscripcion':
-- SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = 'chk_aci_tipo';
