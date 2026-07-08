-- ============================================================================
-- Nómina — tipo_ambiente en documentos operativos (pruebas=1 / producción=2)
-- ----------------------------------------------------------------------------
-- Solo las tablas transaccionales llevan ambiente y filtran por él, como el
-- resto del sistema (facturas, compras). Empleados NO lleva (catálogo maestro
-- compartido). El valor se copia de empresas.tipo_ambiente al insertar y las
-- lecturas filtran por el ambiente actual de la empresa.
-- ============================================================================

ALTER TABLE novedades    ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1';
ALTER TABLE vacaciones   ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1';
ALTER TABLE rol_cabecera ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1';

CREATE INDEX IF NOT EXISTS idx_novedades_ambiente    ON novedades (id_empresa, tipo_ambiente) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vacaciones_ambiente   ON vacaciones (id_empresa, tipo_ambiente) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_rol_cabecera_ambiente ON rol_cabecera (id_empresa, tipo_ambiente) WHERE eliminado = false;

-- Recrear la unicidad de la corrida incluyendo el ambiente (no choca pruebas vs producción).
DROP INDEX IF EXISTS uk_rol_cabecera_periodo;
CREATE UNIQUE INDEX IF NOT EXISTS uk_rol_cabecera_periodo
    ON rol_cabecera (id_empresa, tipo_ambiente, tipo_rol, periodo_anio, periodo_mes, numero_periodo)
    WHERE eliminado = false;
