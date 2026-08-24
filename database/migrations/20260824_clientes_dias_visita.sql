-- ============================================================================
-- Clientes: días, frecuencia y ventana de visita del vendedor (rutero)
-- ----------------------------------------------------------------------------
-- Permite registrar en la ficha del cliente qué días lo visita el vendedor, con
-- qué frecuencia (semanal / quincenal / mensual), en qué semanas del mes, en qué
-- orden dentro de la ruta del día y en qué horario atiende.
--
-- Convenciones:
--   dias_visita    -> SMALLINT[] con ISO-8601: 1=Lunes ... 7=Domingo
--   semanas_visita -> SMALLINT[] con la semana del mes: 1..5
--                     (solo aplica a frecuencia QUINCENAL / MENSUAL; en SEMANAL
--                      se guarda NULL porque se visita todas las semanas)
--
-- Tabla operativa (multiempresa): estas columnas viven en `clientes`, que ya
-- tiene id_empresa + auditoría + eliminado. No se crean tablas nuevas.
-- ============================================================================

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS dias_visita        SMALLINT[]   NULL,
    ADD COLUMN IF NOT EXISTS frecuencia_visita  VARCHAR(20)  NULL,
    ADD COLUMN IF NOT EXISTS semanas_visita     SMALLINT[]   NULL,
    ADD COLUMN IF NOT EXISTS orden_visita       SMALLINT     NULL,
    ADD COLUMN IF NOT EXISTS hora_visita_desde  TIME         NULL,
    ADD COLUMN IF NOT EXISTS hora_visita_hasta  TIME         NULL,
    ADD COLUMN IF NOT EXISTS observacion_visita VARCHAR(255) NULL;

COMMENT ON COLUMN clientes.dias_visita        IS 'Días de visita del vendedor. ISO-8601: 1=Lunes .. 7=Domingo.';
COMMENT ON COLUMN clientes.frecuencia_visita  IS 'SEMANAL | QUINCENAL | MENSUAL. NULL = sin ruta de visita definida.';
COMMENT ON COLUMN clientes.semanas_visita     IS 'Semanas del mes (1..5) en que aplica la visita. Solo QUINCENAL/MENSUAL.';
COMMENT ON COLUMN clientes.orden_visita       IS 'Secuencia del cliente dentro de la ruta del día (menor = se visita antes).';
COMMENT ON COLUMN clientes.hora_visita_desde  IS 'Inicio de la ventana horaria en que el cliente atiende.';
COMMENT ON COLUMN clientes.hora_visita_hasta  IS 'Fin de la ventana horaria en que el cliente atiende.';
COMMENT ON COLUMN clientes.observacion_visita IS 'Nota para el vendedor: cómo llegar, con quién preguntar, restricciones.';

-- ── Restricciones de dominio ────────────────────────────────────────────────
-- Los CHECK con NULL evalúan a NULL y por tanto NO bloquean: los clientes sin
-- ruta de visita definida quedan válidos sin necesidad de backfill.

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_clientes_dias_visita') THEN
        ALTER TABLE clientes ADD CONSTRAINT chk_clientes_dias_visita
            CHECK (dias_visita <@ ARRAY[1,2,3,4,5,6,7]::smallint[]);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_clientes_semanas_visita') THEN
        ALTER TABLE clientes ADD CONSTRAINT chk_clientes_semanas_visita
            CHECK (semanas_visita <@ ARRAY[1,2,3,4,5]::smallint[]);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_clientes_frecuencia_visita') THEN
        ALTER TABLE clientes ADD CONSTRAINT chk_clientes_frecuencia_visita
            CHECK (frecuencia_visita IN ('SEMANAL', 'QUINCENAL', 'MENSUAL'));
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_clientes_orden_visita') THEN
        ALTER TABLE clientes ADD CONSTRAINT chk_clientes_orden_visita
            CHECK (orden_visita >= 0);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_clientes_ventana_visita') THEN
        ALTER TABLE clientes ADD CONSTRAINT chk_clientes_ventana_visita
            CHECK (hora_visita_desde IS NULL OR hora_visita_hasta IS NULL OR hora_visita_desde <= hora_visita_hasta);
    END IF;
END
$$;

-- ── Índices ─────────────────────────────────────────────────────────────────
-- GIN sobre el array: resuelve "3 = ANY(dias_visita)" y "dias_visita && ARRAY[...]"
-- que es exactamente la consulta de "¿a quién visito hoy?".
CREATE INDEX IF NOT EXISTS idx_clientes_dias_visita
    ON clientes USING GIN (dias_visita);

-- Parcial: solo los clientes vivos de cada empresa que sí tienen ruta definida.
CREATE INDEX IF NOT EXISTS idx_clientes_empresa_visita
    ON clientes (id_empresa, id_vendedor, orden_visita)
    WHERE eliminado = false AND dias_visita IS NOT NULL;
