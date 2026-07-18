-- ============================================================================
-- Módulo: Cargar Transferencias (modulos/transferencias)
-- ----------------------------------------------------------------------------
-- Lotes de transferencia bancaria armados a partir de pagos YA registrados en
-- Egresos (incluye los generados por Roles de Pago, tipo_documento='ROL' en
-- egresos_detalle) cuya línea de pago tiene tipo_operacion_bancaria='TRANSFERENCIA'.
-- No se generan egresos nuevos aquí; solo se agrupan, aprueban y exportan a un
-- archivo (Excel/TXT) con el formato del banco elegido.
--
-- Prevención de duplicados: un pago se considera "reservado" apenas se agrega
-- a un lote en cualquier estado activo (no solo confirmado). Ver el anti-join
-- en TransferenciaLoteRepository::getPagosDisponibles(), que excluye pagos ya
-- presentes en un detalle de lote cuyo estado NOT IN ('RECHAZADO','ANULADO').
-- ============================================================================

CREATE TABLE IF NOT EXISTS transferencias_lotes (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INT NOT NULL REFERENCES empresas(id),
    numero                  INT NOT NULL,
    tipo_lote               VARCHAR(20) NOT NULL DEFAULT 'AMBOS', -- PROVEEDORES / NOMINA / AMBOS
    id_forma_pago_origen    INT NULL REFERENCES empresa_formas_pago(id),
    id_banco_formato        INT NULL REFERENCES bancos_ecuador(id),
    fecha_pago              DATE NULL,
    monto_total             NUMERIC(12,2) NOT NULL DEFAULT 0,
    cantidad_pagos          INT NOT NULL DEFAULT 0,
    estado                  VARCHAR(24) NOT NULL DEFAULT 'BORRADOR',
    -- BORRADOR, PENDIENTE_APROBACION, APROBADO, RECHAZADO, GENERADO, CONFIRMADO, ANULADO
    observaciones           TEXT NULL,
    aprobado_por            INT NULL REFERENCES usuarios(id),
    aprobado_at             TIMESTAMP NULL,
    rechazado_por           INT NULL REFERENCES usuarios(id),
    rechazado_at            TIMESTAMP NULL,
    motivo_rechazo          TEXT NULL,
    token_aprobacion        VARCHAR(64) NULL,
    archivo_generado_path   VARCHAR(255) NULL,
    archivo_generado_at     TIMESTAMP NULL,
    archivo_generado_by     INT NULL REFERENCES usuarios(id),
    confirmado_por          INT NULL REFERENCES usuarios(id),
    confirmado_at           TIMESTAMP NULL,
    motivo_anulacion        TEXT NULL,
    anulado_por             INT NULL REFERENCES usuarios(id),
    anulado_at              TIMESTAMP NULL,
    created_by              INT NULL REFERENCES usuarios(id),
    updated_by              INT NULL REFERENCES usuarios(id),
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NULL,
    eliminado               BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at              TIMESTAMP NULL,
    deleted_by              INT NULL REFERENCES usuarios(id)
);

CREATE INDEX IF NOT EXISTS idx_transferencias_lotes_empresa
    ON transferencias_lotes(id_empresa) WHERE eliminado = FALSE;

CREATE UNIQUE INDEX IF NOT EXISTS uq_transferencias_lotes_numero
    ON transferencias_lotes(id_empresa, numero);

CREATE UNIQUE INDEX IF NOT EXISTS uq_transferencias_lotes_token
    ON transferencias_lotes(token_aprobacion) WHERE token_aprobacion IS NOT NULL;

CREATE TABLE IF NOT EXISTS transferencias_lotes_detalle (
    id                  SERIAL PRIMARY KEY,
    id_lote             INT NOT NULL REFERENCES transferencias_lotes(id),
    id_empresa          INT NOT NULL REFERENCES empresas(id),
    id_egreso           INT NOT NULL REFERENCES egresos_cabecera(id),
    id_egreso_pago      INT NOT NULL REFERENCES egresos_pagos(id),
    tipo_beneficiario   VARCHAR(20) NOT NULL, -- PROVEEDOR / EMPLEADO
    id_proveedor        INT NULL REFERENCES proveedores(id),
    id_empleado         INT NULL REFERENCES empleados(id),
    nombre_beneficiario VARCHAR(255) NULL,
    identificacion      VARCHAR(20) NULL,
    id_banco_ecuador    INT NULL REFERENCES bancos_ecuador(id),
    tipo_cuenta         VARCHAR(20) NULL,
    numero_cuenta       VARCHAR(40) NULL,
    monto               NUMERIC(12,2) NOT NULL DEFAULT 0,
    concepto            VARCHAR(255) NULL,
    created_by          INT NULL REFERENCES usuarios(id),
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    eliminado           BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE INDEX IF NOT EXISTS idx_transferencias_lotes_detalle_lote
    ON transferencias_lotes_detalle(id_lote) WHERE eliminado = FALSE;

CREATE INDEX IF NOT EXISTS idx_transferencias_lotes_detalle_pago
    ON transferencias_lotes_detalle(id_egreso_pago) WHERE eliminado = FALSE;

-- ─── Configuración de aprobación (mismo patrón que inv_requiere_aprobacion) ───
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS transf_requiere_aprobacion BOOLEAN DEFAULT FALSE;
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS transf_notificar_correo BOOLEAN DEFAULT TRUE;
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS transf_usuarios_aprobadores JSONB DEFAULT '[]';
