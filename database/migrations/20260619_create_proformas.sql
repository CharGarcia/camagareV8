-- =============================================================================
-- Migración: Módulo de Proformas
-- Fecha: 2026-06-19
-- Descripción: Tablas para proformas (cotizaciones) sin SRI, sin inventario
-- =============================================================================

-- ─── TABLA CABECERA ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS proformas_cabecera (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL,
    id_establecimiento    INTEGER NOT NULL,
    id_punto_emision      INTEGER NOT NULL,
    id_cliente            INTEGER NOT NULL,
    id_usuario            INTEGER NOT NULL,
    id_vendedor           INTEGER,
    fecha_emision         DATE NOT NULL,
    establecimiento       VARCHAR(3) NOT NULL,
    punto_emision         VARCHAR(3) NOT NULL,
    secuencial            VARCHAR(9) NOT NULL,
    tipo_ambiente         VARCHAR(1) NOT NULL DEFAULT '1',
    dias_vigencia         INTEGER NOT NULL DEFAULT 15,
    total_sin_impuestos   NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_descuento       NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ice             NUMERIC(14,2) NOT NULL DEFAULT 0,
    importe_total         NUMERIC(14,2) NOT NULL DEFAULT 0,
    moneda                VARCHAR(10) NOT NULL DEFAULT 'DOLAR',
    estado                VARCHAR(20) NOT NULL DEFAULT 'borrador',
    observaciones         TEXT,
    id_factura_convertida INTEGER,
    fecha_convertida      TIMESTAMP,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by            INTEGER,
    updated_by            INTEGER,
    eliminado             BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at            TIMESTAMP,
    deleted_by            INTEGER
);

COMMENT ON TABLE proformas_cabecera IS 'Cabecera de proformas/cotizaciones (sin autorización SRI ni inventario)';
COMMENT ON COLUMN proformas_cabecera.tipo_ambiente IS 'Compatibilidad con SecuencialRepository (1=Pruebas, 2=Producción)';
COMMENT ON COLUMN proformas_cabecera.estado IS 'borrador | aprobada | rechazada | convertida | anulada';
COMMENT ON COLUMN proformas_cabecera.id_factura_convertida IS 'FK a ventas_cabecera cuando se convierte a factura';

-- ─── TABLA DETALLE ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS proformas_detalle (
    id                          SERIAL PRIMARY KEY,
    id_proforma                 INTEGER NOT NULL REFERENCES proformas_cabecera(id),
    id_producto                 INTEGER,
    id_unidad_medida            INTEGER,
    codigo_principal            VARCHAR(50) NOT NULL DEFAULT '',
    codigo_auxiliar             VARCHAR(50),
    descripcion                 VARCHAR(500) NOT NULL,
    cantidad                    NUMERIC(14,4) NOT NULL DEFAULT 1,
    precio_unitario             NUMERIC(14,6) NOT NULL DEFAULT 0,
    descuento                   NUMERIC(14,2) NOT NULL DEFAULT 0,
    precio_total_sin_impuesto   NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_tarifa_iva               INTEGER NOT NULL DEFAULT 0,
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE proformas_detalle IS 'Detalle de ítems en la proforma (productos/servicios sin control de stock)';

-- ─── TABLA IMPUESTOS DEL DETALLE ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS proformas_detalle_impuestos (
    id                  SERIAL PRIMARY KEY,
    id_proforma_detalle INTEGER NOT NULL REFERENCES proformas_detalle(id),
    codigo_impuesto     VARCHAR(5) NOT NULL,
    codigo_porcentaje   VARCHAR(5) NOT NULL,
    tarifa              NUMERIC(10,2) NOT NULL DEFAULT 0,
    base_imponible      NUMERIC(14,2) NOT NULL DEFAULT 0,
    valor               NUMERIC(14,2) NOT NULL DEFAULT 0
);

COMMENT ON TABLE proformas_detalle_impuestos IS 'Impuestos (IVA/ICE) por ítem de la proforma';

-- ─── TABLA INFO ADICIONAL ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS proformas_adicional (
    id          SERIAL PRIMARY KEY,
    id_proforma INTEGER NOT NULL REFERENCES proformas_cabecera(id),
    nombre      VARCHAR(300) NOT NULL,
    valor       VARCHAR(300) NOT NULL
);

COMMENT ON TABLE proformas_adicional IS 'Información adicional de la proforma (campo-valor libre)';

-- ─── ÍNDICES ─────────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_proformas_empresa        ON proformas_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_proformas_cliente        ON proformas_cabecera(id_cliente);
CREATE INDEX IF NOT EXISTS idx_proformas_estado         ON proformas_cabecera(estado);
CREATE INDEX IF NOT EXISTS idx_proformas_fecha          ON proformas_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_proformas_eliminado      ON proformas_cabecera(eliminado);
CREATE INDEX IF NOT EXISTS idx_proformas_punto_sec      ON proformas_cabecera(id_punto_emision, secuencial, tipo_ambiente);
CREATE UNIQUE INDEX IF NOT EXISTS uq_proformas_numero   ON proformas_cabecera(id_empresa, id_establecimiento, id_punto_emision, secuencial) WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_proformas_det_proforma   ON proformas_detalle(id_proforma);
CREATE INDEX IF NOT EXISTS idx_proformas_imp_detalle    ON proformas_detalle_impuestos(id_proforma_detalle);
CREATE INDEX IF NOT EXISTS idx_proformas_adic_proforma  ON proformas_adicional(id_proforma);

-- ─── SUBMODULO EN MENU ───────────────────────────────────────────────────────
-- Insertar en submodulos_menu si no existe
-- Ajustar id_modulo según el módulo de VENTAS de tu instalación (ej: 308)
DO $$
DECLARE
    v_id_modulo INTEGER;
    v_existe    INTEGER;
BEGIN
    -- Buscar el módulo de ventas por nombre
    SELECT id INTO v_id_modulo FROM modulos_menu WHERE LOWER(nombre) LIKE '%venta%' LIMIT 1;
    IF v_id_modulo IS NULL THEN
        SELECT id INTO v_id_modulo FROM modulos_menu LIMIT 1;
    END IF;

    SELECT COUNT(*) INTO v_existe FROM submodulos_menu WHERE ruta = 'modulos/proformas';
    IF v_existe = 0 THEN
        INSERT INTO submodulos_menu (nombre, ruta, icono, id_modulo, orden, activo)
        VALUES ('Proformas', 'modulos/proformas', 'bi bi-file-earmark-text', v_id_modulo, 50, true)
        ON CONFLICT DO NOTHING;
    END IF;
END $$;
