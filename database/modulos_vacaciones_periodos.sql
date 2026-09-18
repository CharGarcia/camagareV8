-- ============================================================================
-- Vacaciones — períodos ya tomados o pagados antes de usar el sistema
-- ----------------------------------------------------------------------------
-- Para empleados que vienen de otro sistema con años de antigüedad: se marca
-- cada período de servicio (año de trabajo) que ya gozó o cobró, y sus días
-- dejan de contar en el saldo. Una fila por período marcado; los períodos
-- pendientes NO se guardan, se calculan desde la fecha de ingreso.
--
-- SIN tipo_ambiente, a propósito: es historia del empleado (como su fecha de
-- ingreso), no un documento de nómina. Debe valer igual en pruebas y en
-- producción, para que el ajuste no "desaparezca" al pasar la empresa a
-- producción, que es justo cuando se cargan los empleados que vienen de otro
-- sistema.
--
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

CREATE TABLE IF NOT EXISTS vacaciones_periodos (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    id_empleado     INTEGER NOT NULL,
    numero_periodo  SMALLINT NOT NULL,              -- año de servicio (1 = primer año)
    fecha_inicio    DATE NOT NULL,                  -- fechas del período al marcarlo (referencia)
    fecha_fin       DATE NOT NULL,
    dias_derecho    NUMERIC(6,2) NOT NULL,          -- derecho del período al marcarlo
    dias            NUMERIC(6,2) NOT NULL,          -- días que se dan por tomados/pagados
    estado          VARCHAR(20) NOT NULL,           -- tomado / pagado
    observacion     VARCHAR(255),
    eliminado       BOOLEAN NOT NULL DEFAULT false,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER,
    CONSTRAINT chk_vacaciones_periodos_estado CHECK (estado IN ('tomado', 'pagado')),
    CONSTRAINT chk_vacaciones_periodos_dias   CHECK (dias > 0 AND dias <= dias_derecho)
);

-- Un período se marca una sola vez por empleado (entre los vivos). También es el
-- índice de lectura: el modal siempre consulta por empresa + empleado.
CREATE UNIQUE INDEX IF NOT EXISTS uk_vacaciones_periodos_empleado
    ON vacaciones_periodos (id_empresa, id_empleado, numero_periodo)
    WHERE eliminado = false;
