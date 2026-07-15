-- ============================================================
-- Módulo Auditoría Contable — listado legible
-- Guarda en cada incidencia el número del documento (serie-secuencial) y el
-- nombre de la entidad (cliente / proveedor / empleado), para no resolverlos
-- con decenas de JOIN en cada lectura y poder buscar/ordenar por ellos.
-- Los llena la propia auditoría al detectar (AuditoriaContableRepository::enriquecerHallazgos).
-- Idempotente: se puede ejecutar varias veces.
-- ============================================================

ALTER TABLE auditoria_contable_incidencias
    ADD COLUMN IF NOT EXISTS documento_numero VARCHAR(60),
    ADD COLUMN IF NOT EXISTS entidad_nombre   VARCHAR(200);

-- Búsqueda por nombre de cliente/proveedor y por número de documento
CREATE INDEX IF NOT EXISTS idx_aci_entidad_nombre
    ON auditoria_contable_incidencias (id_empresa, tipo_ambiente, entidad_nombre)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_aci_documento_numero
    ON auditoria_contable_incidencias (id_empresa, tipo_ambiente, documento_numero)
    WHERE eliminado = false;

-- Listado principal: separa lo pendiente de lo ya corregido
CREATE INDEX IF NOT EXISTS idx_aci_estado_revision_fecha
    ON auditoria_contable_incidencias (id_empresa, tipo_ambiente, estado_revision, fecha_documento)
    WHERE eliminado = false;

-- NOTA: tras aplicar esto, vuelva a pulsar «Ejecutar auditoría» para que las
-- incidencias existentes se completen con el número y el nombre.
