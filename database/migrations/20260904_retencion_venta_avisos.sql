-- ============================================================================
-- Reporte de Retenciones de Venta Pendientes — avisos enviados por correo
-- ----------------------------------------------------------------------------
-- Registra cada aviso de "factura sin comprobante de retención" enviado al
-- cliente desde el módulo modulos/reporte_retenciones_pendientes, para poder
-- mostrar en el reporte cuántos avisos lleva cada factura y cuándo fue el
-- último, y para filtrar "sin aviso" / "con aviso".
--
-- Un envío AGRUPADO (un correo por cliente con varias facturas) genera una
-- fila por factura incluida, todas con el mismo lote (id_lote), para poder
-- reconstruir qué facturas viajaron juntas en ese correo.
--
-- Tabla operativa: lleva id_empresa y campos de auditoría (CLAUDE.md §5).
-- Ejecutar completo en pgAdmin (idempotente).
-- ============================================================================

CREATE TABLE IF NOT EXISTS retencion_venta_avisos (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER      NOT NULL,
    id_venta        INTEGER      NOT NULL,          -- factura de venta avisada
    id_cliente      INTEGER,                        -- cliente al momento del envío
    tipo_envio      VARCHAR(10)  NOT NULL DEFAULT 'INDIVIDUAL'
                        CHECK (tipo_envio IN ('INDIVIDUAL', 'AGRUPADO')),
    id_lote         VARCHAR(40),                    -- agrupa las filas de un mismo correo agrupado
    correo_destino  VARCHAR(500) NOT NULL,          -- destinatarios reales (separados por coma)
    asunto          VARCHAR(255),
    fecha_envio     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Auditoría obligatoria
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN   NOT NULL DEFAULT FALSE,
    deleted_at  TIMESTAMP,
    deleted_by  INTEGER,

    CONSTRAINT fk_ret_vta_avisos_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT fk_ret_vta_avisos_venta   FOREIGN KEY (id_venta)   REFERENCES ventas_cabecera(id)
);

-- Consulta principal del reporte: avisos por factura dentro de la empresa.
CREATE INDEX IF NOT EXISTS idx_ret_vta_avisos_empresa_venta
    ON retencion_venta_avisos (id_empresa, id_venta)
    WHERE eliminado = false;

-- Historial por cliente (envíos agrupados).
CREATE INDEX IF NOT EXISTS idx_ret_vta_avisos_empresa_cliente
    ON retencion_venta_avisos (id_empresa, id_cliente, fecha_envio DESC)
    WHERE eliminado = false;

COMMENT ON TABLE  retencion_venta_avisos IS 'Avisos por correo al cliente de facturas de venta sin comprobante de retención (módulo Reporte de Retenciones de Venta Pendientes).';
COMMENT ON COLUMN retencion_venta_avisos.tipo_envio IS 'INDIVIDUAL = un correo por factura; AGRUPADO = un correo por cliente con varias facturas (mismo id_lote).';
