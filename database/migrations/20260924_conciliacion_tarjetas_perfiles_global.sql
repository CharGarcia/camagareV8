-- ============================================================
-- Conciliación de Tarjetas: los perfiles de lectura del estado de
-- cuenta pasan a ser un catálogo GLOBAL
-- (config/conciliacion-tarjetas-perfiles, solo nivel 3).
--
-- Antes cada empresa creaba sus perfiles desde el propio módulo
-- (modulos/conciliacion-tarjetas → Configuración → Perfiles) y cada
-- perfil apuntaba a una forma de cobro DE ESA EMPRESA (id_forma_cobro).
-- El formato del archivo depende de la procesadora (Payphone, Nuvei,
-- datáfono de cada banco), no de la empresa, así que ahora se configuran
-- una sola vez y todas las empresas eligen entre ellos al cargar el
-- estado de cuenta.
--
-- En lugar de la forma de cobro, el perfil se asocia a:
--   • tipo_procesadora: PAYPHONE / NUVEI / TARJETA (NULL = cualquiera).
--     Es el mismo empresa_formas_pago.tipo con el que el módulo decide
--     qué concilia.
--   • id_banco (opcional): para el datáfono, cuyo reporte cambia según
--     el banco/adquirente. NULL = cualquier banco.
--
-- No destructivo: id_empresa e id_forma_cobro se conservan (solo dejan
-- de ser obligatorias / de usarse) como rastro de los perfiles antiguos.
-- Los perfiles que ya existieran heredan el tipo y el banco de su forma
-- de cobro y quedan disponibles para todas las empresas.
--
-- Requiere 20260821_conciliacion_tarjetas.sql aplicado antes.
-- Idempotente: se puede ejecutar más de una vez. Listo para pgAdmin (F5).
-- ============================================================

ALTER TABLE conciliacion_tarjetas_perfiles
    ADD COLUMN IF NOT EXISTS tipo_procesadora VARCHAR(10);

ALTER TABLE conciliacion_tarjetas_perfiles
    ADD COLUMN IF NOT EXISTS id_banco INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_ctperf_tipo_procesadora') THEN
        ALTER TABLE conciliacion_tarjetas_perfiles
            ADD CONSTRAINT chk_ctperf_tipo_procesadora
            CHECK (tipo_procesadora IS NULL OR tipo_procesadora IN ('PAYPHONE','NUVEI','TARJETA'));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_ctperf_banco') THEN
        ALTER TABLE conciliacion_tarjetas_perfiles
            ADD CONSTRAINT fk_ctperf_banco FOREIGN KEY (id_banco) REFERENCES bancos_ecuador(id);
    END IF;
END $$;

-- Perfiles antiguos (si los hay): heredan tipo y banco de su forma de cobro.
UPDATE conciliacion_tarjetas_perfiles p
   SET tipo_procesadora = UPPER(fp.tipo),
       id_banco         = COALESCE(p.id_banco, fp.id_banco)
  FROM empresa_formas_pago fp
 WHERE fp.id = p.id_forma_cobro
   AND p.tipo_procesadora IS NULL
   AND UPPER(fp.tipo) IN ('PAYPHONE','NUVEI','TARJETA');

ALTER TABLE conciliacion_tarjetas_perfiles ALTER COLUMN id_empresa DROP NOT NULL;

COMMENT ON COLUMN conciliacion_tarjetas_perfiles.id_empresa IS
    'Obsoleto (catálogo global desde 2026-09-24): empresa que creó el perfil cuando era por empresa. No se filtra por esta columna.';
COMMENT ON COLUMN conciliacion_tarjetas_perfiles.id_forma_cobro IS
    'Obsoleto (catálogo global desde 2026-09-24): reemplazado por tipo_procesadora + id_banco.';
COMMENT ON COLUMN conciliacion_tarjetas_perfiles.tipo_procesadora IS
    'PAYPHONE / NUVEI / TARJETA (empresa_formas_pago.tipo). NULL = sirve para cualquier procesadora.';
COMMENT ON COLUMN conciliacion_tarjetas_perfiles.id_banco IS
    'Banco/adquirente del datáfono (bancos_ecuador). NULL = cualquier banco.';

CREATE INDEX IF NOT EXISTS idx_ctperf_global
    ON conciliacion_tarjetas_perfiles (eliminado, activo, tipo_procesadora);

-- Comprobación (debe listar tipo_procesadora e id_banco, e id_empresa con is_nullable = YES):
-- SELECT column_name, is_nullable FROM information_schema.columns
--  WHERE table_name = 'conciliacion_tarjetas_perfiles'
--    AND column_name IN ('id_empresa','tipo_procesadora','id_banco');
