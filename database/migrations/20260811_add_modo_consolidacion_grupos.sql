-- Agrega el modo de cálculo a los grupos de Balances Consolidados:
--   SUMA  (default, comportamiento actual): el concepto existe de forma
--         independiente en cada establecimiento (caja, CxC, etc.) y se suma.
--   UNICA: el concepto es el mismo registro para toda la empresa (Capital,
--         Reservas de Patrimonio) y no debe sumarse entre establecimientos;
--         se toma el valor de un solo establecimiento (id_empresa_fuente).
-- Ver docs/manual/modulos/balances-consolidados.md

ALTER TABLE consolidacion_grupos
    ADD COLUMN modo_consolidacion VARCHAR(10) NOT NULL DEFAULT 'SUMA';

ALTER TABLE consolidacion_grupos
    ADD CONSTRAINT consolidacion_grupos_modo_check
    CHECK (modo_consolidacion IN ('SUMA', 'UNICA'));

ALTER TABLE consolidacion_grupos
    ADD COLUMN id_empresa_fuente INTEGER NULL REFERENCES empresas(id);
