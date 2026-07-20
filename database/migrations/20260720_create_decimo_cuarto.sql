-- ============================================================================
-- Módulo Décimo Cuarto Sueldo (Nómina) — cálculo del acumulado anual +
-- declaración al Ministerio del Trabajo (Sistema de Salarios en Línea)
-- ----------------------------------------------------------------------------
-- Solo calcula el caso empleados.decimo_cuarto = 'acumula' (el 'mensualiza' ya
-- se paga cada mes dentro del rol, ver RolCalculoService). El PAGO no se hace
-- desde aquí: el valor calculado aparece como documento pendiente en
-- Egresos → Nómina (igual que un anticipo), ver EgresoRepository
-- ::getDocumentosPendientesEmpleado()/buscarDocumentosPendientesEgreso()
-- (tipo_documento = 'DECIMO_CUARTO'). Esa contrapartida debita directamente la
-- cuenta "Décimo Cuarto por Pagar" (código DECIMOCUARTOPORPAGARNOMINA, ya
-- sembrado en asientos_tipo) en vez de la cuenta genérica del concepto de
-- Egreso, para cancelar el pasivo que RolProvisionService ya provisionó mes a
-- mes — hay que configurarle su cuenta contable en Configuración Contable.
-- Operativa multiempresa, eliminación lógica y auditoría. SIN tipo_ambiente
-- (igual que vacaciones: no es comprobante SRI).
-- ============================================================================

-- 1. Campos nuevos en empleados, usados como snapshot en decimo_cuarto_detalle
--    y para completar 3 de las 13 columnas del archivo del Ministerio.
ALTER TABLE empleados
    ADD COLUMN IF NOT EXISTS discapacidad BOOLEAN NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS fecha_jubilacion_patronal DATE,
    ADD COLUMN IF NOT EXISTS valor_retencion_judicial NUMERIC(10,2) NOT NULL DEFAULT 0;

-- 2. Cabecera: una corrida por empresa + grupo de región + año
CREATE TABLE IF NOT EXISTS decimo_cuarto_cabecera (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL,
    anio            SMALLINT NOT NULL,
    region_grupo    VARCHAR(20) NOT NULL, -- costa_insular / sierra_amazonia
    fecha_desde     DATE NOT NULL,
    fecha_hasta     DATE NOT NULL,
    fecha_limite_pago DATE NOT NULL,
    sbu_aplicado    NUMERIC(10,2) NOT NULL DEFAULT 0,
    total_empleados INTEGER NOT NULL DEFAULT 0,
    total_valor     NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado          VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador/calculado (anulado = eliminado true)
    eliminado       BOOLEAN NOT NULL DEFAULT false,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      INTEGER,
    updated_by      INTEGER,
    deleted_at      TIMESTAMP,
    deleted_by      INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_decimo_cuarto_periodo
    ON decimo_cuarto_cabecera (id_empresa, anio, region_grupo)
    WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_decimo_cuarto_cabecera_empresa
    ON decimo_cuarto_cabecera (id_empresa) WHERE eliminado = false;

-- 3. Detalle: una fila por empleado, con snapshot de los datos que exige el
--    archivo del Ministerio (para no depender de cambios futuros en empleados).
CREATE TABLE IF NOT EXISTS decimo_cuarto_detalle (
    id                     SERIAL PRIMARY KEY,
    id_cabecera            INTEGER NOT NULL REFERENCES decimo_cuarto_cabecera(id) ON DELETE CASCADE,
    id_empresa             INTEGER NOT NULL,
    id_empleado            INTEGER NOT NULL,
    identificacion         VARCHAR(25) NOT NULL,
    nombres                VARCHAR(150) NOT NULL,
    apellidos              VARCHAR(150) NOT NULL,
    sexo                   CHAR(1),
    codigo_ocupacion       VARCHAR(20),
    dias_laborados         SMALLINT NOT NULL DEFAULT 0,
    valor                  NUMERIC(12,2) NOT NULL DEFAULT 0, -- 0 si mensualiza=true (ya pagado en el rol)
    mensualiza             BOOLEAN NOT NULL DEFAULT false,
    tipo_pago              CHAR(2) NOT NULL DEFAULT 'A',      -- P/A/RP/RA
    jornada_parcial        BOOLEAN NOT NULL DEFAULT false,
    horas_jornada_parcial  NUMERIC(4,1),
    discapacidad           BOOLEAN NOT NULL DEFAULT false,
    fecha_jubilacion       DATE,
    valor_retencion        NUMERIC(10,2) NOT NULL DEFAULT 0,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by             INTEGER,
    updated_by             INTEGER
);

CREATE INDEX IF NOT EXISTS idx_decimo_cuarto_detalle_cab ON decimo_cuarto_detalle (id_cabecera);
CREATE INDEX IF NOT EXISTS idx_decimo_cuarto_detalle_emp ON decimo_cuarto_detalle (id_empresa, id_empleado);

-- Menú y permisos (submodulos_menu / modulos_asignados) quedan a cargo del
-- usuario, fuera de esta migración.

-- Tras ejecutar, obtener el id del submódulo creado y registrarlo en
-- config/modulos_mvc.php (clave 'modulos/decimo-cuarto' → id_submodulo):
--   SELECT id FROM submodulos_menu WHERE ruta = 'modulos/decimo-cuarto';
-- Luego asignar permisos (r,w,u,d,t) a los perfiles en /config/permisos-modulos.
