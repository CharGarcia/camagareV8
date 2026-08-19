-- ---------------------------------------------------------------------------
-- Servicio de restaurante (el "10%") cobrado como PROPINA
--
-- El recargo por servicio de bares y restaurantes viaja en el comprobante
-- electrónico en el campo <propina> de infoFactura: va DESPUÉS del IVA (no
-- forma base imponible) y la Ficha Técnica del SRI valida que no supere el
-- 10% del subtotal. No existe ningún otro campo libre en los subtotales del
-- XML, así que este es el único lugar correcto para ese valor.
--
-- Configuración por establecimiento:
--   servicio_restaurante            no | obligatorio | opcional
--       no          → no se cobra servicio (comportamiento actual)
--       obligatorio → toda comanda lo lleva y no se puede quitar desde el salón
--       opcional    → toda comanda lo lleva, pero el mesero puede quitarlo
--                     cuando el cliente no quiere pagarlo
--   servicio_restaurante_porcentaje  0.01 .. 10.00 (por defecto 10)
--
-- En la comanda se guarda un SNAPSHOT del porcentaje: cambiar la
-- configuración no altera las cuentas que ya están abiertas en el salón.
-- ---------------------------------------------------------------------------

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS servicio_restaurante            VARCHAR(15)   NOT NULL DEFAULT 'no',
    ADD COLUMN IF NOT EXISTS servicio_restaurante_porcentaje NUMERIC(5,2)  NOT NULL DEFAULT 10;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_empresa_est_servicio_restaurante') THEN
        ALTER TABLE empresa_establecimiento
            ADD CONSTRAINT chk_empresa_est_servicio_restaurante
            CHECK (servicio_restaurante IN ('no', 'obligatorio', 'opcional'));
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_empresa_est_servicio_porcentaje') THEN
        ALTER TABLE empresa_establecimiento
            ADD CONSTRAINT chk_empresa_est_servicio_porcentaje
            CHECK (servicio_restaurante_porcentaje >= 0 AND servicio_restaurante_porcentaje <= 10);
    END IF;
END $$;

ALTER TABLE comandas
    ADD COLUMN IF NOT EXISTS aplica_servicio     BOOLEAN      NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS porcentaje_servicio NUMERIC(5,2) NOT NULL DEFAULT 0;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_comandas_porcentaje_servicio') THEN
        ALTER TABLE comandas
            ADD CONSTRAINT chk_comandas_porcentaje_servicio
            CHECK (porcentaje_servicio >= 0 AND porcentaje_servicio <= 10);
    END IF;
END $$;

COMMENT ON COLUMN empresa_establecimiento.servicio_restaurante IS
    'Recargo por servicio del POS Restaurante: no | obligatorio | opcional. Se cobra en el campo <propina> del comprobante.';
COMMENT ON COLUMN empresa_establecimiento.servicio_restaurante_porcentaje IS
    'Porcentaje del recargo por servicio sobre el subtotal. Máximo 10: el SRI rechaza una propina mayor al 10% del subtotal.';
COMMENT ON COLUMN comandas.aplica_servicio IS
    'La comanda cobra recargo por servicio. Si el establecimiento lo tiene en "opcional", el mesero puede apagarlo.';
COMMENT ON COLUMN comandas.porcentaje_servicio IS
    'Snapshot del porcentaje vigente al abrir la comanda: cambiar la configuración no altera cuentas ya abiertas.';
