-- MIGRATION: Módulo Traspasos de Fondos (Tesorería)
-- Mueve saldo entre dos formas de pago/cobro en un solo paso: genera un asiento
-- DEBE=cuenta forma destino / HABER=cuenta forma origen (ver AsientoBuilderService::generarAsientoTraspaso).
-- -----------------------------------------------------
BEGIN;

CREATE TABLE IF NOT EXISTS traspasos_cabecera (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,

    fecha_emision DATE NOT NULL,
    numero_traspaso VARCHAR(50) NOT NULL,
    id_punto_emision INTEGER,
    establecimiento VARCHAR(5),
    punto_emision VARCHAR(5),
    secuencial VARCHAR(9),

    id_forma_origen INTEGER NOT NULL,
    id_forma_destino INTEGER NOT NULL,
    monto NUMERIC(18,6) NOT NULL DEFAULT 0,
    observaciones TEXT,

    id_asiento_contable INTEGER,
    estado VARCHAR(20) DEFAULT 'registrado', -- registrado, anulado
    tipo_ambiente VARCHAR(1),
    eliminado BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER,

    CONSTRAINT fk_traspaso_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_traspaso_forma_origen FOREIGN KEY (id_forma_origen) REFERENCES empresa_formas_pago(id),
    CONSTRAINT fk_traspaso_forma_destino FOREIGN KEY (id_forma_destino) REFERENCES empresa_formas_pago(id),
    CONSTRAINT chk_traspaso_formas_distintas CHECK (id_forma_origen <> id_forma_destino)
);

CREATE INDEX IF NOT EXISTS idx_traspasos_empresa ON traspasos_cabecera(id_empresa);
CREATE INDEX IF NOT EXISTS idx_traspasos_fecha ON traspasos_cabecera(fecha_emision);
CREATE INDEX IF NOT EXISTS idx_traspasos_num ON traspasos_cabecera(numero_traspaso);
CREATE INDEX IF NOT EXISTS idx_traspasos_forma_origen ON traspasos_cabecera(id_forma_origen);
CREATE INDEX IF NOT EXISTS idx_traspasos_forma_destino ON traspasos_cabecera(id_forma_destino);

COMMIT;

-- ─────────────────────────────────────────────────────────────────────────
-- PENDIENTE MANUAL (no lo ejecuta esta migración, según flujo del proyecto):
--   1. Crear el módulo padre "Tesorería" en modulos_menu (si no existe) y el
--      submódulo "Traspasos" en submodulos_menu con ruta = 'modulos/traspasos'.
--   2. Asignar permisos en /config/permisos-modulos.
--   3. Actualizar config/modulos_mvc.php -> 'modulos/traspasos' -> id_submodulo
--      con el id real que quede registrado en submodulos_menu.
-- ─────────────────────────────────────────────────────────────────────────
