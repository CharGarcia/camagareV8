-- ============================================================================
-- Novedades: marca "Aporta al IESS" (Sí / No)
-- ----------------------------------------------------------------------------
-- Igual que los rubros fijos del empleado, los ingresos variables pueden ir con
-- IESS o sin IESS. La marca aplica a Otros Ingresos (1) y a las horas nocturnas
-- (4), suplementarias (5) y extraordinarias (6); en los demás tipos queda NULL.
--
-- NULL = la novedad no trae la marca (registrada antes de esta opción, migrada o
-- cargada con una plantilla anterior). El rol aplica entonces lo de siempre
-- según el tipo: las horas SÍ aportan y Otros Ingresos NO. Por eso este script
-- no modifica datos: solo agrega la columna. Se puede ejecutar más de una vez.
--
-- Orden de despliegue: ejecutar este SQL y luego actualizar el código. El
-- código también funciona sin la columna (la ignora), pero sin ella no se puede
-- guardar la marca.
-- ============================================================================

ALTER TABLE novedades ADD COLUMN IF NOT EXISTS aporta_iess BOOLEAN;

COMMENT ON COLUMN novedades.aporta_iess IS
    'Aporta al IESS (Otros Ingresos y horas 4/5/6). NULL = sin marca: horas sí, Otros Ingresos no.';
