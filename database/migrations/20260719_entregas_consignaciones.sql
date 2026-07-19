-- App móvil (Fase 3): evidencia de entrega de Consignaciones en venta (GPS + firma).
-- Deploy manual: subir y ejecutar contra la BD de producción cuando corresponda.

-- 1) Evidencia de entrega. Tabla separada (no columnas sueltas en consignaciones_ventas):
--    permite reintentos desde el celular sin pisar evidencia previa, y da idempotencia
--    real para la sincronización (UNIQUE por uuid_cliente) — un reenvío por timeout de
--    red no crea una entrega duplicada.
CREATE TABLE IF NOT EXISTS consignaciones_ventas_entregas (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER NOT NULL REFERENCES empresas(id),
    id_consignacion   INTEGER NOT NULL REFERENCES consignaciones_ventas(id),
    uuid_cliente      VARCHAR(64) NOT NULL,      -- generado en el celular; idempotencia
    latitud           NUMERIC(10,7),
    longitud          NUMERIC(10,7),
    precision_m       NUMERIC(8,2),
    firma_path        VARCHAR(255),               -- storage/entregas/empresa_{id}/...
    capturado_en      TIMESTAMP NOT NULL,          -- hora del celular (puede ser anterior si fue offline)
    dispositivo_id    VARCHAR(128),
    canal             VARCHAR(10) NOT NULL DEFAULT 'movil',
    estado            VARCHAR(20) NOT NULL DEFAULT 'valida',
    observaciones     TEXT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by        INTEGER,
    updated_by        INTEGER,
    eliminado         BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at        TIMESTAMP,
    deleted_by        INTEGER,
    UNIQUE (id_empresa, uuid_cliente)
);
CREATE INDEX IF NOT EXISTS idx_cv_entregas_consignacion
    ON consignaciones_ventas_entregas (id_consignacion);

-- 2) Puntero denormalizado a la evidencia confirmada (mismo patrón que id_asiento_contable).
ALTER TABLE consignaciones_ventas ADD COLUMN IF NOT EXISTS id_entrega_confirmada INTEGER NULL;

-- =============================================================================
-- 3) Submódulo "Entregas" — REFERENCIA, ajústala a tu proceso habitual de alta de
--    submódulos (no la ejecuto yo). Va separado de "Consignación venta" (id 8) a
--    propósito: un repartidor puede tener SOLO este permiso, sin ver/crear
--    consignaciones. No existe página web para esta ruta (es solo de la app) — es
--    un patrón válido, el sistema de permisos no exige que haya un controlador
--    real detrás de la ruta.
-- =============================================================================
-- INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, status)
-- VALUES ('Entregas', 'modulos/entregas-consignaciones', 15, 0, 1);
--
-- Luego, en /config/permisos-modulos (o como manejes tú los permisos), asignar al
-- repartidor: ver=true, y "todo"=false si debe ver solo lo suyo (ver §usuarios_responsables_traslado).
