-- ============================================================
-- FIX: Corrige la FK incorrecta en ingresos_cabecera
-- La restricción fk_ingreso_concepto apuntaba a empresa_ingreso_conceptos
-- (tabla obsoleta). Todo el sistema usa empresa_opciones_ingreso_egreso.
-- Se elimina la FK incorrecta (igual que egresos_cabecera, que no tiene FK
-- para id_egreso_concepto y lo maneja a nivel de aplicación).
-- ============================================================

ALTER TABLE ingresos_cabecera
    DROP CONSTRAINT IF EXISTS fk_ingreso_concepto;
