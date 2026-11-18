-- ============================================================================
-- Novedades: marca "ya pagado" registrada a mano (registro para todos los empleados)
-- ----------------------------------------------------------------------------
-- QUÉ HACE: agrega a `novedades` la columna `desembolso_manual`.
--
-- POR QUÉ: un Anticipo, una Quincena o una cuota de Préstamo Empresa que ya se
-- entregó fuera del sistema lleva `desembolsado_migrado = true` (el rol la descuenta
-- directo, sin egreso). Hasta ahora esa marca sin `id_carga` solo la ponía la
-- migración del sistema anterior, y por eso se mostraba como "Pagada" y no se
-- podía editar ni eliminar. Desde el modal de Novedades (modo "Todos los
-- empleados", selector "Ya pagado") se puede registrar a mano: esas novedades
-- llevan `desembolso_manual = true` y se editan, eliminan y muestran como
-- cualquier otra (igual que las cargadas por Excel).
--
-- Sin esta columna el sistema sigue funcionando, pero el registro masivo rechaza
-- la opción "Ya pagado" con un aviso.
--
-- Ejecutar en pgAdmin ANTES de desplegar el código. Es idempotente.
-- ============================================================================

ALTER TABLE novedades
    ADD COLUMN IF NOT EXISTS desembolso_manual BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN novedades.desembolso_manual IS
    'true: el usuario marcó la novedad (Anticipo/Quincena/Préstamo Empresa) como ya pagada fuera del sistema al registrarla. No es migrada: se edita y elimina normalmente.';

-- Verificación: debe devolver una fila.
-- SELECT column_name, data_type, column_default
--   FROM information_schema.columns
--  WHERE table_name = 'novedades' AND column_name = 'desembolso_manual';
