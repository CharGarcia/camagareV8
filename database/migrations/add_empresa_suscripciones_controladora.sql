-- ============================================================
-- Empresa administradora de suscripciones (script idempotente)
-- ============================================================
-- Enlaza cada empresa con la EMPRESA que controla su suscripción del
-- sistema (SaaS). El cruce real se hace por RUC contra los clientes de
-- esa empresa controladora (ver SuscripcionesRepository::getResumenPorControladoraYRuc).
--
--   id_empresa_suscripciones        → empresas.id de la empresa que controla la suscripción
--   es_administradora_suscripciones → marca la empresa que actúa como administradora
--                                     por defecto (se preselecciona al registrar).
-- ============================================================

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS id_empresa_suscripciones INTEGER NULL;

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS es_administradora_suscripciones BOOLEAN NOT NULL DEFAULT false;

-- Solo una empresa debería ser la administradora por defecto.
CREATE UNIQUE INDEX IF NOT EXISTS uq_empresas_admin_suscripciones
    ON empresas (es_administradora_suscripciones)
    WHERE es_administradora_suscripciones = true;
