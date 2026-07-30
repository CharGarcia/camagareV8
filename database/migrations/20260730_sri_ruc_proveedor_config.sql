-- ============================================================================
-- RUC del proveedor del sistema de facturación — configurable desde /config
-- Res. NAC-DGERCGC26-00000027 (Art. 5) + Ficha Técnica v2.34, Anexo 26
-- ----------------------------------------------------------------------------
-- Todo comprobante electrónico debe llevar en <infoAdicional> el campo
-- "RUC Proveedor" con el RUC del proveedor del sistema (CaMaGaRe). El valor se
-- guarda en una tabla de configuración GLOBAL (sin id_empresa, §4 CLAUDE.md) y
-- se edita en /config/sri-proveedor (solo nivel 3). Si la tabla no existe o la
-- clave está vacía, el sistema cae al valor de config/app.php
-- ('sri_ruc_proveedor_sistema') como respaldo.
-- ============================================================================
BEGIN;

-- Tabla genérica de configuración global del sistema (clave/valor).
CREATE TABLE IF NOT EXISTS configuracion_sistema (
    id SERIAL PRIMARY KEY,
    clave VARCHAR(100) NOT NULL,
    valor TEXT,
    descripcion TEXT,
    eliminado BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_configuracion_sistema_clave
    ON configuracion_sistema (clave) WHERE eliminado = FALSE;

-- Semilla: RUC del proveedor (CaMaGaRe).
INSERT INTO configuracion_sistema (clave, valor, descripcion)
SELECT 'sri_ruc_proveedor_sistema',
       '1792708389001',
       'RUC del proveedor del sistema de facturación electrónica. Va como campo "RUC Proveedor" en la información adicional de todo comprobante electrónico (Res. NAC-DGERCGC26-00000027, Ficha v2.34 Anexo 26).'
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_sistema
    WHERE clave = 'sri_ruc_proveedor_sistema' AND eliminado = FALSE
);

-- Tarjeta en /config (solo nivel 3) con su enlace.
INSERT INTO configuracion_opciones (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
SELECT 'RUC Proveedor (SRI)',
       'RUC del proveedor del sistema de facturación que va en la información adicional de todos los comprobantes electrónicos (Res. NAC-DGERCGC26-00000027).',
       'patch-check', 'danger', 3, 92, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_opciones WHERE nombre = 'RUC Proveedor (SRI)'
);

INSERT INTO configuracion_opcion_enlaces (id_opcion, etiqueta, ruta, clase_btn, orden)
SELECT o.id, 'Configurar RUC', '/config/sri-proveedor', 'danger', 0
FROM configuracion_opciones o
WHERE o.nombre = 'RUC Proveedor (SRI)'
  AND NOT EXISTS (
      SELECT 1 FROM configuracion_opcion_enlaces e
      WHERE e.id_opcion = o.id AND e.ruta = '/config/sri-proveedor'
  );

COMMIT;
