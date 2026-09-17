-- ============================================================================
-- Formas de cobro y pago: "Mostrar saldo" y "Orden" en Ingresos y Egresos
-- (modulos/formas_cobros_pagos)
-- ----------------------------------------------------------------------------
-- Qué hace:
--   Agrega a empresa_formas_pago dos columnas que definen cómo se ofrece cada
--   forma al registrar un Ingreso o un Egreso:
--     * mostrar_saldo: si junto al nombre de la forma se ve su saldo actual
--                      (en un anticipo, el saldo del cliente/proveedor elegido).
--     * orden:         posición de la forma en esa lista (1 = primera). Las que
--                      no tienen orden (NULL) van al final, por nombre.
--
-- Datos:
--   No modifica filas. Las formas existentes quedan con mostrar_saldo = TRUE y
--   sin orden, es decir, exactamente como se ven hoy (saldo visible y lista por
--   nombre) hasta que se configuren desde el módulo.
--
-- Orden de despliegue:
--   Ejecutar ANTES de subir el código. Igual, si el código llega primero no se
--   rompe nada: mientras falten las columnas, Ingresos/Egresos listan por nombre
--   con el saldo visible y el modal de la forma no guarda estos dos campos.
--
-- Reversible: sí (se pierde solo la configuración de estos dos campos):
--   ALTER TABLE empresa_formas_pago DROP CONSTRAINT IF EXISTS chk_formas_pago_orden;
--   ALTER TABLE empresa_formas_pago DROP COLUMN IF EXISTS orden;
--   ALTER TABLE empresa_formas_pago DROP COLUMN IF EXISTS mostrar_saldo;
--
-- Cómo ejecutarlo en pgAdmin:
--   Query Tool → pegar TODO el archivo → F5. Idempotente: se puede ejecutar
--   varias veces sin error. Es instantáneo (agregar una columna con valor por
--   defecto constante no reescribe la tabla).
-- ============================================================================

ALTER TABLE empresa_formas_pago
    ADD COLUMN IF NOT EXISTS mostrar_saldo BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE empresa_formas_pago
    ADD COLUMN IF NOT EXISTS orden INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_formas_pago_orden') THEN
        ALTER TABLE empresa_formas_pago
            ADD CONSTRAINT chk_formas_pago_orden
            CHECK (orden IS NULL OR orden BETWEEN 1 AND 9999);
    END IF;
END $$;

COMMENT ON COLUMN empresa_formas_pago.mostrar_saldo IS
    'Ingresos/Egresos muestran el saldo de la forma junto a su nombre al registrar un cobro/pago.';
COMMENT ON COLUMN empresa_formas_pago.orden IS
    'Posición de la forma en la lista de Ingresos/Egresos (1 = primera). NULL = al final, por nombre.';

-- ----------------------------------------------------------------------------
-- OPCIONAL (no se ejecuta, está comentado): si prefiere que ninguna forma
-- muestre el saldo hasta marcarla una por una en el módulo, descomente y
-- ejecute esta línea. Afecta a TODAS las empresas de la base.
-- ----------------------------------------------------------------------------
-- UPDATE empresa_formas_pago SET mostrar_saldo = FALSE WHERE eliminado = FALSE;

-- ----------------------------------------------------------------------------
-- Comprobación (deben salir 2 filas: mostrar_saldo NOT NULL DEFAULT true y
-- orden nullable), y 1 fila con la restricción chk_formas_pago_orden:
-- ----------------------------------------------------------------------------
-- SELECT column_name, data_type, is_nullable, column_default
-- FROM information_schema.columns
-- WHERE table_name = 'empresa_formas_pago'
--   AND column_name IN ('mostrar_saldo', 'orden');
--
-- SELECT conname, pg_get_constraintdef(oid)
-- FROM pg_constraint
-- WHERE conname = 'chk_formas_pago_orden';
