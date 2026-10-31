-- ============================================================================
-- Cambio temporal de IVA en productos (empresas de turismo — IVA 8% en feriados)
-- ----------------------------------------------------------------------------
-- Base legal: art. innumerado a continuación del art. 65 LRTI. Por decreto
-- ejecutivo, el Presidente reduce al 8% la tarifa de IVA de los servicios
-- turísticos (art. 5 Ley de Turismo) en feriados. Normas de emisión del SRI:
-- Res. NAC-DGERCGC22-00000010 (reformada por la 22-00000011 y la 23-00000005).
--
-- es_empresa_turismo: marca en la EMPRESA. Solo la define el superadmin en
-- config/empresas-sistema (igual que factura_operadora_transporte). Apagado =
-- el botón "Cambio temporal de IVA" no aparece en Productos.
--
-- iva_cambios_temporales: cada empresa programa "tarifa actual → tarifa nueva"
-- entre dos fechas. El cron (app/cron/cron_runner.php) lo aplica al llegar la
-- fecha desde y lo restaura al pasar la fecha hasta. El detalle guarda la tarifa
-- que tenía cada producto, para devolverle exactamente esa.
-- ============================================================================

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS es_empresa_turismo VARCHAR(10) DEFAULT 'false';

UPDATE empresas
   SET es_empresa_turismo = COALESCE(es_empresa_turismo, 'false');

CREATE TABLE IF NOT EXISTS iva_cambios_temporales (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER NOT NULL,
    id_tarifa_actual  INTEGER NOT NULL,           -- tarifa_iva.id que se reemplaza
    id_tarifa_nueva   INTEGER NOT NULL,           -- tarifa_iva.id que se pone
    aplica_a          VARCHAR(10) NOT NULL DEFAULT 'todos',  -- todos | servicios | bienes
    fecha_desde       DATE NOT NULL,
    fecha_hasta       DATE NOT NULL,
    observaciones     VARCHAR(500),
    estado            VARCHAR(15) NOT NULL DEFAULT 'programado', -- programado | aplicado | restaurado | cancelado | vencido
    aplicado_at       TIMESTAMP,
    restaurado_at     TIMESTAMP,
    total_productos   INTEGER NOT NULL DEFAULT 0, -- productos cambiados al aplicar
    total_restaurados INTEGER NOT NULL DEFAULT 0,
    total_omitidos    INTEGER NOT NULL DEFAULT 0, -- cambiados a mano en el período: no se restauran
    created_at        TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMP,
    created_by        INTEGER,
    updated_by        INTEGER,
    eliminado         BOOLEAN NOT NULL DEFAULT false,
    deleted_at        TIMESTAMP,
    deleted_by        INTEGER,
    CONSTRAINT iva_cambios_temporales_fechas_chk CHECK (fecha_hasta >= fecha_desde),
    CONSTRAINT iva_cambios_temporales_aplica_chk CHECK (aplica_a IN ('todos', 'servicios', 'bienes'))
);

-- El cron busca en cada minuto lo que toca aplicar o restaurar.
CREATE INDEX IF NOT EXISTS idx_iva_cambios_temporales_pendientes
    ON iva_cambios_temporales (estado, fecha_desde, fecha_hasta)
    WHERE eliminado = false AND estado IN ('programado', 'aplicado');

CREATE INDEX IF NOT EXISTS idx_iva_cambios_temporales_empresa
    ON iva_cambios_temporales (id_empresa, fecha_desde DESC)
    WHERE eliminado = false;

CREATE TABLE IF NOT EXISTS iva_cambios_temporales_detalle (
    id                 SERIAL PRIMARY KEY,
    id_cambio          INTEGER NOT NULL REFERENCES iva_cambios_temporales (id),
    id_empresa         INTEGER NOT NULL,
    id_producto        INTEGER NOT NULL,
    id_tarifa_anterior INTEGER NOT NULL,   -- la que tenía el producto antes del cambio
    id_tarifa_aplicada INTEGER NOT NULL,
    restaurado         BOOLEAN NOT NULL DEFAULT false,
    omitido            BOOLEAN NOT NULL DEFAULT false, -- su tarifa se cambió a mano durante el período
    restaurado_at      TIMESTAMP,
    created_at         TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMP,
    created_by         INTEGER,
    updated_by         INTEGER,
    eliminado          BOOLEAN NOT NULL DEFAULT false,
    deleted_at         TIMESTAMP,
    deleted_by         INTEGER
);

CREATE INDEX IF NOT EXISTS idx_iva_cambios_temporales_detalle_cambio
    ON iva_cambios_temporales_detalle (id_cambio)
    WHERE eliminado = false;
