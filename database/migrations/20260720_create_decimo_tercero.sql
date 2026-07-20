-- ============================================================================
-- Módulo Décimo Tercero Sueldo (Nómina) — cálculo del acumulado anual +
-- declaración al Ministerio del Trabajo (Sistema de Salarios en Línea)
-- ----------------------------------------------------------------------------
-- Solo calcula el caso empleados.decimo_tercero = 'acumula' (el 'mensualiza' ya
-- se paga cada mes dentro del rol, ver RolCalculoService). A diferencia del
-- Décimo Cuarto (SBU parejo), el valor es variable por empleado: la suma de
-- todo lo percibido en el período (1-dic año anterior a 30-nov) / 12. Esa suma
-- ("total_ganado") se agrega desde rol_detalle_rubro (roles MENSUAL del
-- período), con la opción de sumar TODOS los ingresos o solo los que aportan
-- IESS (columna base_calculo), y es editable por si falta algún mes de rol.
--
-- Sin diferencia por región (fecha límite única: 24 de diciembre, para todo
-- el país). El PAGO no se hace desde aquí: aparece como documento pendiente
-- en Egresos → Nómina (tipo_documento = 'DECIMO_TERCERO'), cuya contrapartida
-- debita directo "Décimo Tercero por Pagar" (DECIMOTERCEROPORPAGARNOMINA, ya
-- sembrado en asientos_tipo) para cancelar lo que RolProvisionService ya
-- provisionó mes a mes — hay que configurarle su cuenta contable.
--
-- Operativa multiempresa, eliminación lógica y auditoría. SIN tipo_ambiente
-- (igual que vacaciones/décimo cuarto: no es comprobante SRI).
-- ============================================================================

-- 1. Cabecera: una corrida por empresa + año (nacional, sin región)
CREATE TABLE IF NOT EXISTS decimo_tercero_cabecera (
    id                SERIAL PRIMARY KEY,
    id_empresa        INTEGER NOT NULL,
    anio              SMALLINT NOT NULL,
    fecha_desde       DATE NOT NULL,
    fecha_hasta       DATE NOT NULL,
    fecha_limite_pago DATE NOT NULL,
    base_calculo      VARCHAR(20) NOT NULL DEFAULT 'solo_iess', -- todos / solo_iess
    total_empleados   INTEGER NOT NULL DEFAULT 0,
    total_valor       NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado            VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador/calculado (anulado = eliminado true)
    eliminado         BOOLEAN NOT NULL DEFAULT false,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by        INTEGER,
    updated_by        INTEGER,
    deleted_at        TIMESTAMP,
    deleted_by        INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_decimo_tercero_periodo
    ON decimo_tercero_cabecera (id_empresa, anio)
    WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_decimo_tercero_cabecera_empresa
    ON decimo_tercero_cabecera (id_empresa) WHERE eliminado = false;

-- 2. Detalle: una fila por empleado. Sin fecha_jubilacion (ese campo es
--    exclusivo del archivo de Décimo Cuarto; el de tercero no lo trae).
CREATE TABLE IF NOT EXISTS decimo_tercero_detalle (
    id                     SERIAL PRIMARY KEY,
    id_cabecera            INTEGER NOT NULL REFERENCES decimo_tercero_cabecera(id) ON DELETE CASCADE,
    id_empresa             INTEGER NOT NULL,
    id_empleado            INTEGER NOT NULL,
    identificacion         VARCHAR(25) NOT NULL,
    nombres                VARCHAR(150) NOT NULL,
    apellidos              VARCHAR(150) NOT NULL,
    sexo                   CHAR(1),
    codigo_ocupacion       VARCHAR(20),
    dias_laborados         SMALLINT NOT NULL DEFAULT 0,
    total_ganado           NUMERIC(12,2) NOT NULL DEFAULT 0, -- editable (snapshot agregado desde rol_detalle_rubro)
    valor                  NUMERIC(12,2) NOT NULL DEFAULT 0, -- total_ganado / 12; 0 si mensualiza=true
    mensualiza             BOOLEAN NOT NULL DEFAULT false,
    tipo_pago              CHAR(2) NOT NULL DEFAULT 'A',      -- P/A/RP/RA
    jornada_parcial        BOOLEAN NOT NULL DEFAULT false,
    horas_jornada_parcial  NUMERIC(4,1),
    discapacidad           BOOLEAN NOT NULL DEFAULT false,
    valor_retencion        NUMERIC(10,2) NOT NULL DEFAULT 0,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by             INTEGER,
    updated_by             INTEGER
);

CREATE INDEX IF NOT EXISTS idx_decimo_tercero_detalle_cab ON decimo_tercero_detalle (id_cabecera);
CREATE INDEX IF NOT EXISTS idx_decimo_tercero_detalle_emp ON decimo_tercero_detalle (id_empresa, id_empleado);

-- Menú y permisos (submodulos_menu / modulos_asignados) quedan a cargo del
-- usuario, fuera de esta migración.
