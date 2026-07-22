-- Combos de submódulos: catálogo global (solo superadmin) para armar "planes"
-- reutilizables de submódulos (ej. "Solo Facturación Electrónica") y aplicarlos
-- de un clic a un usuario desde /config/permisos-modulos.
-- Cada submódulo incluido se aplica con acceso total (r=1,w=1,u=1,d=1,t=1);
-- aplicar un combo SUMA a los permisos que el usuario ya tenga (no reemplaza).

CREATE TABLE IF NOT EXISTS combos_submodulos (
    id           SERIAL PRIMARY KEY,
    nombre       VARCHAR(150) NOT NULL,
    descripcion  TEXT NULL,
    precio       NUMERIC(10,2) NULL,
    clase_color  VARCHAR(20) NOT NULL DEFAULT 'primary',
    orden        INTEGER NOT NULL DEFAULT 0,
    activo       BOOLEAN NOT NULL DEFAULT true,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by   INTEGER NULL,
    updated_by   INTEGER NULL,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INTEGER NULL
);

CREATE TABLE IF NOT EXISTS combos_submodulos_items (
    id           SERIAL PRIMARY KEY,
    id_combo     INTEGER NOT NULL REFERENCES combos_submodulos(id) ON DELETE CASCADE,
    id_modulo    INTEGER NOT NULL,
    id_submodulo INTEGER NOT NULL,
    CONSTRAINT uk_combos_submodulos_items UNIQUE (id_combo, id_submodulo)
);

CREATE INDEX IF NOT EXISTS idx_combos_submodulos_items_combo ON combos_submodulos_items (id_combo);
