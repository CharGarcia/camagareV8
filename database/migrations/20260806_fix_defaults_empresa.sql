-- ============================================================================
-- Corrige defaults de columna que afectan a empresas creadas de aquí en
-- adelante (no toca empresas ya existentes). Idempotente.
--
-- 1) empresas.tipo ("Tipo de Contribuyente"): default de columna era '01',
--    esquema que no corresponde a los id reales del catálogo tipo_empresa
--    (1=Persona Natural, 2=PN Obligada a contabilidad, 3=Sociedad,
--    4=Contribuyente especial, 5=Sector público). Empresa::crear() ya pasa
--    'tipo' explícito en el INSERT, así que este default rara vez se usa en la
--    práctica (solo protege inserts directos fuera del flujo normal).
--
-- 2) empresas.tipo_ambiente: default era 1 (Pruebas). Empresa::crear() NO
--    incluye esta columna en su INSERT, así que las empresas nuevas dependen
--    100% de este default. Debe ser 2 (Producción). Este es el cambio que
--    realmente requiere correr esta migración.
-- ============================================================================

ALTER TABLE empresas
    ALTER COLUMN tipo SET DEFAULT '1';

ALTER TABLE empresas
    ALTER COLUMN tipo_ambiente SET DEFAULT 2;
