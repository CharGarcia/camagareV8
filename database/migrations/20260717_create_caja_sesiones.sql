-- =====================================================================
-- Módulo Punto de Venta (POS) — Fase 1: Apertura/Cierre de caja
--
-- Primer bloque, prerrequisito de las 3 pantallas de venta (Grid Retail,
-- Mapa de puntos, Táctil rápido): sin sesión de caja abierta no hay venta.
--
-- Aplicar manualmente (dev y luego producción), como el resto de módulos
-- del sistema. Después de aplicar, registrar el submódulo en
-- submodulos_menu / modulos_asignados (eso lo hace el usuario aparte).
-- =====================================================================

CREATE TABLE IF NOT EXISTS caja_sesiones (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL,
    id_punto_emision      INTEGER NOT NULL,
    id_usuario            INTEGER NOT NULL, -- cajero que abrió el turno

    fondo_inicial         NUMERIC(12,2) NOT NULL DEFAULT 0,
    fecha_apertura        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    fecha_cierre          TIMESTAMP,
    monto_esperado        NUMERIC(12,2),   -- fondo inicial + efectivo vendido en el turno
    monto_contado         NUMERIC(12,2),   -- arqueo físico declarado por el cajero
    diferencia            NUMERIC(12,2),   -- monto_contado - monto_esperado
    observaciones_cierre  TEXT,

    estado                VARCHAR(20) NOT NULL DEFAULT 'abierta', -- abierta | cerrada

    -- Auditoría estándar (obligatoria en toda tabla operativa, CLAUDE.md §5)
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by            INTEGER,
    updated_by            INTEGER,
    eliminado             BOOLEAN DEFAULT FALSE,
    deleted_at            TIMESTAMP,
    deleted_by            INTEGER,

    CONSTRAINT fk_caja_sesion_empresa        FOREIGN KEY (id_empresa)       REFERENCES empresas(id),
    CONSTRAINT fk_caja_sesion_punto_emision  FOREIGN KEY (id_punto_emision) REFERENCES empresa_punto_emision(id),
    CONSTRAINT fk_caja_sesion_usuario        FOREIGN KEY (id_usuario)       REFERENCES usuarios(id),
    CONSTRAINT ck_caja_sesion_estado         CHECK (estado IN ('abierta', 'cerrada'))
);

-- Un punto de emisión no puede tener dos turnos abiertos a la vez.
CREATE UNIQUE INDEX IF NOT EXISTS ux_caja_sesion_abierta
    ON caja_sesiones (id_punto_emision)
    WHERE estado = 'abierta' AND eliminado = false;

CREATE INDEX IF NOT EXISTS ix_caja_sesion_empresa
    ON caja_sesiones (id_empresa) WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS ix_caja_sesion_usuario
    ON caja_sesiones (id_usuario) WHERE eliminado = false;
