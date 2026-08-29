-- ============================================================================
-- Comandas: forma de pago SUGERIDA por el cliente desde el QR de la mesa.
--
-- Al pedir su cuenta desde el celular, el cliente indica cómo piensa pagar.
-- Es solo una sugerencia: el mesero la ve al cobrar y puede elegir otra —
-- quien decide la forma real del cobro sigue siendo él.
--
-- Va en columna propia y no en `forma_pago`: esa se llena al cobrar de verdad
-- (ComandaRepository::marcarGrupoCobrado) y machacarla con una sugerencia haría
-- imposible distinguir lo que se cobró de lo que el cliente pidió. Mismo
-- criterio que `tipo_documento_solicitado`, que ya existe al lado.
-- ============================================================================

BEGIN;

ALTER TABLE comanda_grupos_cobro
    ADD COLUMN IF NOT EXISTS id_forma_pago_sugerida INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'comanda_grupos_cobro_id_forma_pago_sugerida_fkey'
    ) THEN
        ALTER TABLE comanda_grupos_cobro
            ADD CONSTRAINT comanda_grupos_cobro_id_forma_pago_sugerida_fkey
            FOREIGN KEY (id_forma_pago_sugerida) REFERENCES empresa_formas_pago(id);
    END IF;
END $$;

COMMENT ON COLUMN comanda_grupos_cobro.id_forma_pago_sugerida IS
    'Forma de pago que sugirió el cliente al pedir su cuenta desde el QR. Solo informativa: el mesero elige la definitiva al cobrar (forma_pago).';

COMMIT;

-- Verificación:
-- SELECT id, etiqueta, origen, id_forma_pago_sugerida, forma_pago FROM comanda_grupos_cobro ORDER BY id DESC LIMIT 10;
