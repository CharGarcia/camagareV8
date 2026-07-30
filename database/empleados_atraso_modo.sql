-- ============================================================================
--  Tratamiento de atrasos POR EMPLEADO (pestaña "Atrasos" de la ficha).
--  Define cómo se trata el atraso del empleado al generar novedades/rol:
--    'descuento'       -> se descuenta según las horas/minutos de atraso.
--    'no_descuenta'    -> no se descuenta ni se registra nada.
--    'informativo_reg' -> solo informativo: registra una novedad en $0.
--  NULL o vacío = 'no_descuenta' (valor por defecto del formulario).
-- ============================================================================

ALTER TABLE empleados
    ADD COLUMN IF NOT EXISTS atraso_modo VARCHAR(20);

COMMENT ON COLUMN empleados.atraso_modo IS
    'Tratamiento de atrasos del empleado: descuento | no_descuenta | informativo_reg. NULL = no se descuenta.';
