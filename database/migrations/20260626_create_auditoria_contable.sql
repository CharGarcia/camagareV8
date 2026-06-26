-- ============================================================
-- Módulo: Auditoría Contable
-- Ruta MVC: modulos/auditoria_contable
-- Multi-ambiente: tipo_ambiente '1' Pruebas, '2' Producción
-- Tablas: auditoria_contable_incidencias, auditoria_contable_corridas
-- ============================================================

-- ------------------------------------------------------------
-- 1) INCIDENCIAS: hallazgos detectados por la auditoría
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria_contable_incidencias (
    id              BIGSERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    tipo_ambiente   VARCHAR(1) NOT NULL DEFAULT '1',   -- '1' Pruebas, '2' Producción

    tipo_hallazgo   VARCHAR(30) NOT NULL,   -- ver CHECK abajo
    modulo_origen   VARCHAR(50) NOT NULL,   -- 'factura_venta','compra','liquidacion_compra','nota_credito','retencion_venta','ingreso','egreso',...
    id_documento    BIGINT,                 -- id en la tabla operativa (NULL si es asiento huérfano sin doc)
    id_asiento      BIGINT,                 -- id del asiento implicado (NULL si falta)

    monto_documento NUMERIC(15,2),
    monto_asiento   NUMERIC(15,2),
    diferencia      NUMERIC(15,2),

    detalle         TEXT,                   -- descripción legible del hallazgo
    fecha_documento DATE,                   -- para ubicar el período / facilitar filtros

    -- Gestión de la revisión por el usuario
    estado_revision VARCHAR(20) NOT NULL DEFAULT 'pendiente', -- 'pendiente','revisada','justificada','resuelta'
    nota_revision   TEXT,
    revisado_por    INTEGER,
    revisado_at     TIMESTAMP,

    detectado_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Campo de estado general (independiente de eliminado, §5)
    estado          VARCHAR(20) DEFAULT 'activo',

    -- Auditoría / eliminación lógica (§5)
    eliminado       BOOLEAN DEFAULT false,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER,

    CONSTRAINT chk_aci_tipo CHECK (tipo_hallazgo IN
        ('faltante','duplicado','monto_no_coincide','descuadrado',
         'cab_vs_detalle','huerfano','estado_incoherente','ambiente_incoherente')),
    CONSTRAINT chk_aci_estado_rev CHECK (estado_revision IN
        ('pendiente','revisada','justificada','resuelta'))
);

-- Clave lógica para el upsert del motor (una incidencia viva por doc+asiento+tipo+ambiente).
-- Índice único PARCIAL (solo registros no eliminados), con COALESCE para tolerar NULLs.
CREATE UNIQUE INDEX IF NOT EXISTS uq_aci_clave_logica
    ON auditoria_contable_incidencias
    (id_empresa, tipo_ambiente, tipo_hallazgo, modulo_origen,
     COALESCE(id_documento, 0), COALESCE(id_asiento, 0))
    WHERE eliminado = false;

-- Índices de búsqueda / listado
CREATE INDEX IF NOT EXISTS idx_aci_empresa
    ON auditoria_contable_incidencias (id_empresa, tipo_ambiente) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_aci_empresa_estado_rev
    ON auditoria_contable_incidencias (id_empresa, tipo_ambiente, estado_revision) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_aci_empresa_modulo_tipo
    ON auditoria_contable_incidencias (id_empresa, tipo_ambiente, modulo_origen, tipo_hallazgo) WHERE eliminado = false;


-- ------------------------------------------------------------
-- 2) CORRIDAS: historial de ejecuciones (auditoría y regeneración masiva)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria_contable_corridas (
    id              BIGSERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    tipo_ambiente   VARCHAR(1) NOT NULL DEFAULT '1',   -- '1' Pruebas, '2' Producción

    tipo_corrida    VARCHAR(20) NOT NULL,   -- 'auditoria' | 'regeneracion'
    modulo_origen   VARCHAR(50),            -- NULL = todos los orígenes
    fecha_desde     DATE,                   -- rango aplicado (regeneración acotada); NULL = sin límite
    fecha_hasta     DATE,

    -- Resumen de resultados
    total_documentos    INTEGER DEFAULT 0,  -- documentos analizados
    total_detectadas    INTEGER DEFAULT 0,  -- incidencias detectadas (auditoría)
    total_anulados      INTEGER DEFAULT 0,  -- asientos anulados (regeneración)
    total_regenerados   INTEGER DEFAULT 0,  -- asientos vueltos a generar
    total_omitidos      INTEGER DEFAULT 0,  -- saltados por período cerrado u otra salvaguarda

    estado          VARCHAR(20) DEFAULT 'ok',   -- 'ok' | 'error' | 'parcial'
    mensaje         TEXT,                       -- avisos / errores resumidos
    ejecutado_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Auditoría / eliminación lógica (§5)
    eliminado       BOOLEAN DEFAULT false,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER,

    CONSTRAINT chk_acc_tipo CHECK (tipo_corrida IN ('auditoria','regeneracion')),
    CONSTRAINT chk_acc_estado CHECK (estado IN ('ok','error','parcial'))
);

CREATE INDEX IF NOT EXISTS idx_acc_empresa_fecha
    ON auditoria_contable_corridas (id_empresa, tipo_ambiente, ejecutado_at) WHERE eliminado = false;


-- ------------------------------------------------------------
-- 3) Asegurar columna de ambiente en la cabecera de asientos
--    (ya se crea en runtime en EstadosFinancierosRepository; aquí queda explícito)
-- ------------------------------------------------------------
ALTER TABLE asientos_contables_cabecera ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) DEFAULT '1';


-- ------------------------------------------------------------
-- 4) Submódulo en el menú (idempotente por ruta), bajo «Contabilidad».
--    Si ya existe la ruta, no inserta nada.
-- ------------------------------------------------------------
INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
SELECT 'Auditoría contable',
       'modulos/auditoria_contable',
       (SELECT id FROM modulos_menu WHERE nombre_modulo = 'Contabilidad' LIMIT 1),
       0, 26, 1
WHERE NOT EXISTS (
    SELECT 1 FROM submodulos_menu WHERE ruta = 'modulos/auditoria_contable'
);

-- IMPORTANTE: el id_submodulo resultante debe coincidir con el de
-- config/modulos_mvc.php ('modulos/auditoria_contable' => ['id_submodulo' => 188]).
-- Verifíquelo y, si difiere, actualice ese valor en el archivo:
SELECT id AS id_submodulo, nombre_submodulo, ruta, id_modulo
FROM submodulos_menu WHERE ruta = 'modulos/auditoria_contable';
