-- ============================================================================
-- NÓMINA — SCRIPT CONSOLIDADO PARA PRODUCCIÓN
-- ----------------------------------------------------------------------------
-- Ejecutar UNA vez en la BD de producción. Es idempotente y NO destructivo:
-- puede re-ejecutarse sin error (usa IF NOT EXISTS / WHERE NOT EXISTS).
-- No borra datos existentes.
--
-- Cubre: Empleados (+ periodos, rubros fijos), Novedades, Roles de Pago,
-- Vacaciones y los conceptos contables de nómina.
--
-- DESPUÉS de ejecutar:
--   1) Asignar permisos de los submódulos de Nómina en /config/permisos-modulos.
--   2) En Configuración Contable → Nómina, asignar las cuentas del plan a cada
--      concepto (para poder contabilizar los roles).
--
-- NOTA: los UPDATE de submodulos_menu (ids 170, 172, 47) asumen que esas filas
--       del menú ya existen en producción (submódulos legacy de Nómina). Si no
--       existieran, no fallan pero el módulo no aparecería en el menú: en ese
--       caso crear/ajustar el submódulo con ruta = la indicada.
-- ============================================================================



-- ############################################################################
-- ## 1. EMPLEADOS (tabla base)
-- ############################################################################
-- database/modulos_empleados.sql
-- Creación de tabla operativa de empleados con multitenancy y auditoría completa.

CREATE TABLE IF NOT EXISTS empleados (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL REFERENCES empresas(id),
    id_usuario_sistema INTEGER NULL REFERENCES usuarios(id), -- Opcional: relación con el usuario de sistema
    
    tipo_id VARCHAR(20) NOT NULL, -- cedula, pasaporte
    identificacion VARCHAR(25) NOT NULL,
    nombres_apellidos VARCHAR(255) NOT NULL,
    direccion TEXT,
    email VARCHAR(100),
    telefono VARCHAR(50),
    contacto_emergencia TEXT,
    fecha_nacimiento DATE,
    sexo CHAR(1), -- M (Masculino), F (Femenino), O (Otro)
    estado VARCHAR(20) DEFAULT 'activo', -- activo, inactivo
    
    id_banco_ecuador INTEGER NULL, -- Referencia a bancos_ecuador(id_bancos)
    tipo_cuenta VARCHAR(20), -- ahorros, corriente, virtual
    numero_cuenta VARCHAR(50),

    -- Campos de control de auditoría requeridos por la arquitectura
    eliminado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    deleted_by INTEGER,

    CONSTRAINT uk_empleado_identificacion_empresa UNIQUE (identificacion, id_empresa)
);

-- Índices para búsqueda y rendimiento en multitenancy
CREATE INDEX IF NOT EXISTS idx_empleados_empresa ON empleados(id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_empleados_identificacion ON empleados(identificacion);
CREATE INDEX IF NOT EXISTS idx_empleados_status ON empleados(estado);

-- Comentarios explicativos
COMMENT ON TABLE empleados IS 'Tabla operativa de empleados por empresa';
COMMENT ON COLUMN empleados.id_usuario_sistema IS 'Vínculo opcional con la cuenta de usuario del sistema';
COMMENT ON COLUMN empleados.sexo IS 'M=Masculino, F=Femenino, O=Otro';


-- ############################################################################
-- ## 1b. EMPLEADOS (columnas laborales + periodos + rubros fijos)
-- ############################################################################
-- Expansión del módulo de empleados para incluir información laboral, histórica y rubros fijos.

-- 1. Actualización de la tabla 'empleados'
ALTER TABLE empleados 
    ADD COLUMN IF NOT EXISTS fondos_reserva VARCHAR(20) DEFAULT 'no_se_paga', -- 'rol', 'planilla', 'no_se_paga'
    ADD COLUMN IF NOT EXISTS aporta_iess BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS decimo_tercero VARCHAR(20) DEFAULT 'acumula', -- 'mensualiza', 'acumula'
    ADD COLUMN IF NOT EXISTS decimo_cuarto VARCHAR(20) DEFAULT 'acumula', -- 'mensualiza', 'acumula'
    ADD COLUMN IF NOT EXISTS aporte_personal DECIMAL(10, 4) DEFAULT 9.45,
    ADD COLUMN IF NOT EXISTS aporte_patronal DECIMAL(10, 4) DEFAULT 11.15,
    ADD COLUMN IF NOT EXISTS sueldo_base DECIMAL(10, 2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS valor_quincena DECIMAL(10, 2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS forma_pago VARCHAR(20) DEFAULT 'mensual', -- 'semanal', 'quincenal', 'mensual'
    ADD COLUMN IF NOT EXISTS region VARCHAR(20) DEFAULT 'costa', -- 'costa', 'sierra', 'oriente', 'insular'
    ADD COLUMN IF NOT EXISTS cargo VARCHAR(100),
    ADD COLUMN IF NOT EXISTS lugar_trabajo VARCHAR(200),
    ADD COLUMN IF NOT EXISTS horario_trabajo VARCHAR(100),
    ADD COLUMN IF NOT EXISTS departamento VARCHAR(100),
    ADD COLUMN IF NOT EXISTS codigo_sectorial_iess VARCHAR(50);

-- 2. Tabla historial de periodos laborales
CREATE TABLE IF NOT EXISTS empleado_periodos (
    id SERIAL PRIMARY KEY,
    id_empleado INTEGER NOT NULL REFERENCES empleados(id),
    id_empresa INTEGER NOT NULL,
    fecha_ingreso DATE NOT NULL,
    fecha_salida DATE,
    motivo_salida TEXT,
    eliminado BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- 3. Tabla rubros fijos (ingresos y descuentos)
CREATE TABLE IF NOT EXISTS empleado_rubros_fijos (
    id SERIAL PRIMARY KEY,
    id_empleado INTEGER NOT NULL REFERENCES empleados(id),
    id_empresa INTEGER NOT NULL,
    tipo VARCHAR(20) NOT NULL, -- 'ingreso', 'descuento'
    nombre VARCHAR(100) NOT NULL,
    valor DECIMAL(10, 2) DEFAULT 0.00,
    aporta_iess BOOLEAN DEFAULT FALSE,
    frecuencia VARCHAR(20) DEFAULT 'mensual',
    eliminado BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Índices para mejorar rendimiento
CREATE INDEX IF NOT EXISTS idx_periodos_empleado ON empleado_periodos(id_empleado, id_empresa);
CREATE INDEX IF NOT EXISTS idx_rubros_empleado ON empleado_rubros_fijos(id_empleado, id_empresa);


-- ############################################################################
-- ## 1c. EMPLEADOS (valor_semanal; quita forma_pago)
-- ############################################################################
-- Actualización de la tabla 'empleados' para reflejar el nuevo esquema de pagos anticipados.

-- 1. Eliminar la columna 'forma_pago'
ALTER TABLE empleados DROP COLUMN IF EXISTS forma_pago;

-- 2. Asegurar que existan las columnas para los montos de anticipos (semanal y quincenal)
ALTER TABLE empleados 
    ADD COLUMN IF NOT EXISTS valor_semanal DECIMAL(10, 2) DEFAULT 0.00;

-- Nota: 'valor_quincena' ya fue agregada en la migración anterior.


-- ############################################################################
-- ## 1d. EMPLEADOS (unicidad de identificación respetando eliminación lógica)
-- ############################################################################
-- ============================================================================
-- Módulo Empleados — Unicidad de identificación respetando eliminación lógica
-- ----------------------------------------------------------------------------
-- Problema: la restricción UNIQUE (identificacion, id_empresa) era TOTAL, por
-- lo que un empleado eliminado lógicamente (eliminado = true) seguía bloqueando
-- volver a registrar esa misma cédula/identificación en la empresa.
--
-- Solución: índice UNIQUE PARCIAL que solo aplica a registros NO eliminados,
-- de modo que la unicidad convive con la eliminación lógica del sistema.
-- (Mismo criterio que las validaciones de negocio en EmpleadoRepository::existsByIdentificacion.)
-- ============================================================================

BEGIN;

-- 1. Eliminar la restricción/índice UNIQUE total anterior.
ALTER TABLE empleados DROP CONSTRAINT IF EXISTS uk_empleado_identificacion_empresa;
DROP INDEX IF EXISTS uk_empleado_identificacion_empresa;

-- 2. Crear índice UNIQUE parcial: solo empleados vivos.
CREATE UNIQUE INDEX IF NOT EXISTS uk_empleado_identificacion_empresa
    ON empleados (identificacion, id_empresa)
    WHERE eliminado = false;

COMMIT;


-- ############################################################################
-- ## 2. NOVEDADES (tabla base + ruta menú 170)
-- ############################################################################
-- ============================================================================
-- Módulo Novedades (Nómina) — registro de novedades por empleado
-- ----------------------------------------------------------------------------
-- Operativa (multiempresa), eliminación lógica y auditoría estándar.
-- NO lleva tipo_ambiente (es registro interno de nómina, no comprobante SRI).
-- Los catálogos de tipos y motivos de salida son fijos en código
-- (App\models\CatalogoNovedades).
-- ============================================================================

CREATE TABLE IF NOT EXISTS novedades (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER NOT NULL,
    id_empleado    INTEGER NOT NULL,
    tipo_codigo    VARCHAR(5)  NOT NULL,          -- 1..10, 14 (catálogo en código)
    tipo_nombre    VARCHAR(60) NOT NULL,          -- nombre del tipo (denormalizado)
    fecha          DATE        NOT NULL,          -- fecha de la novedad
    periodo_mes    SMALLINT    NOT NULL,          -- 1..12 (rol de pagos)
    periodo_anio   SMALLINT    NOT NULL,          -- año del rol
    valor          NUMERIC(14,2) NOT NULL DEFAULT 0, -- monto / horas / días según tipo
    motivo_codigo  VARCHAR(2),                    -- solo Aviso de salida (T,V,B,R,S,D,I,F,A)
    motivo_nombre  VARCHAR(120),
    observacion    TEXT,
    estado         VARCHAR(20) NOT NULL DEFAULT 'activo', -- activo / anulado
    eliminado      BOOLEAN     NOT NULL DEFAULT false,
    created_at     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    deleted_at     TIMESTAMP,
    deleted_by     INTEGER
);

CREATE INDEX IF NOT EXISTS idx_novedades_empresa  ON novedades (id_empresa) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_novedades_empleado ON novedades (id_empleado);
CREATE INDEX IF NOT EXISTS idx_novedades_periodo  ON novedades (id_empresa, periodo_anio, periodo_mes);

-- Menú: reutilizar el submódulo "Novedades" (id 170) del módulo Nómina (313),
-- apuntándolo a la ruta MVC del nuevo módulo.
UPDATE submodulos_menu
   SET ruta = 'modulos/novedades', status = 1
 WHERE id = 170;


-- ############################################################################
-- ## 2b. NOVEDADES (campo aplica_en: semanal/quincena/rol)
-- ############################################################################
-- ============================================================================
-- Módulo Novedades — campo "Afecta a" (a qué pago aplica la novedad)
-- ----------------------------------------------------------------------------
-- Valores: 'semanal' (pago semanal), 'quincena', 'rol' (rol de pagos mensual).
-- Por defecto 'rol'.
-- ============================================================================

ALTER TABLE novedades
    ADD COLUMN IF NOT EXISTS aplica_en VARCHAR(20) NOT NULL DEFAULT 'rol';


-- ############################################################################
-- ## 3. ROLES DE PAGO (cabecera + detalle + rubro; ruta menú 172)
-- ############################################################################
-- ============================================================================
-- Módulo Roles de Pago (Nómina) — corridas semanal / quincena / mensual
-- ----------------------------------------------------------------------------
-- Un módulo unificado con tipo_rol (SEMANAL/QUINCENA/MENSUAL). Operativa
-- multiempresa, eliminación lógica y auditoría. SIN tipo_ambiente (nómina
-- interna, no comprobante SRI). El mensual netea lo pagado en semanas/quincenas.
-- ============================================================================

-- 1. Cabecera de la corrida (una por empresa+tipo+período)
CREATE TABLE IF NOT EXISTS rol_cabecera (
    id                    SERIAL PRIMARY KEY,
    id_empresa            INTEGER NOT NULL,
    tipo_rol              VARCHAR(10) NOT NULL,        -- MENSUAL / QUINCENA / SEMANAL
    periodo_anio          SMALLINT NOT NULL,
    periodo_mes           SMALLINT NOT NULL,
    numero_periodo        SMALLINT NOT NULL DEFAULT 0, -- semana (1-5) / quincena (1-2) / 0 mensual
    fecha_desde           DATE,
    fecha_hasta           DATE,
    fecha_pago            DATE,
    descripcion           VARCHAR(150),
    estado                VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador/generado/pagado/contabilizado/anulado
    total_ingresos        NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_egresos         NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_neto            NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_aporte_patronal NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_asiento            INTEGER,
    eliminado             BOOLEAN NOT NULL DEFAULT false,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by            INTEGER,
    updated_by            INTEGER,
    deleted_at            TIMESTAMP,
    deleted_by            INTEGER
);

-- Evita dos corridas iguales (respeta eliminación lógica).
CREATE UNIQUE INDEX IF NOT EXISTS uk_rol_cabecera_periodo
    ON rol_cabecera (id_empresa, tipo_rol, periodo_anio, periodo_mes, numero_periodo)
    WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_rol_cabecera_empresa ON rol_cabecera (id_empresa) WHERE eliminado = false;

-- 2. Detalle: una línea por empleado en la corrida (totales por empleado)
CREATE TABLE IF NOT EXISTS rol_detalle (
    id               SERIAL PRIMARY KEY,
    id_rol           INTEGER NOT NULL REFERENCES rol_cabecera(id) ON DELETE CASCADE,
    id_empresa       INTEGER NOT NULL,
    id_empleado      INTEGER NOT NULL,
    dias_trabajados  NUMERIC(6,2) NOT NULL DEFAULT 30,
    sueldo_base      NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ingresos   NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_egresos    NUMERIC(14,2) NOT NULL DEFAULT 0,
    aporte_iess      NUMERIC(14,2) NOT NULL DEFAULT 0, -- personal (incluido en egresos)
    aporte_patronal  NUMERIC(14,2) NOT NULL DEFAULT 0, -- informativo (asiento)
    neto             NUMERIC(14,2) NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_rol_detalle_rol ON rol_detalle (id_rol);
CREATE INDEX IF NOT EXISTS idx_rol_detalle_empleado ON rol_detalle (id_empresa, id_empleado);

-- 3. Desglose auditable: conceptos (ingresos/egresos) por línea de empleado
CREATE TABLE IF NOT EXISTS rol_detalle_rubro (
    id           SERIAL PRIMARY KEY,
    id_detalle   INTEGER NOT NULL REFERENCES rol_detalle(id) ON DELETE CASCADE,
    id_empresa   INTEGER NOT NULL,
    tipo         VARCHAR(10) NOT NULL,   -- ingreso / egreso
    concepto     VARCHAR(120) NOT NULL,
    codigo       VARCHAR(10),            -- tipo_codigo de novedad, etc.
    origen       VARCHAR(20) NOT NULL DEFAULT 'novedad', -- sueldo/rubro_fijo/novedad/iess/fondos/decimo/neteo
    valor        NUMERIC(14,2) NOT NULL DEFAULT 0,
    aporta_iess  BOOLEAN NOT NULL DEFAULT false,
    id_novedad   INTEGER,                -- trazabilidad a novedades
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_rol_detalle_rubro_det ON rol_detalle_rubro (id_detalle);

-- Menú: reutilizar el submódulo "Roles de pagos" (id 172) del módulo Nómina (313),
-- apuntándolo a la ruta MVC del nuevo módulo unificado.
UPDATE submodulos_menu
   SET ruta = 'modulos/roles-pago', status = 1
 WHERE id = 172;


-- ############################################################################
-- ## 4. VACACIONES (tabla; ruta menú 47)
-- ############################################################################
-- ============================================================================
-- Módulo Vacaciones (Nómina) — registro de vacaciones por empleado con saldo
-- ----------------------------------------------------------------------------
-- Operativa multiempresa, eliminación lógica, auditoría. SIN tipo_ambiente
-- (interno de nómina, no comprobante SRI). Alimenta el rol de pagos mensual.
--
-- Derecho: 15 días/año; desde el 6º año de servicio +1 día por año adicional,
-- máx 30 (Art. 69 Código del Trabajo). Valor ≈ (sueldo/30) × días gozados.
-- ============================================================================

CREATE TABLE IF NOT EXISTS vacaciones (
    id            SERIAL PRIMARY KEY,
    id_empresa    INTEGER NOT NULL,
    id_empleado   INTEGER NOT NULL,
    fecha_desde   DATE NOT NULL,
    fecha_hasta   DATE NOT NULL,
    dias_gozados  NUMERIC(6,2) NOT NULL DEFAULT 0,
    dias_derecho  NUMERIC(6,2) NOT NULL DEFAULT 15,  -- derecho del periodo (snapshot)
    valor         NUMERIC(14,2) NOT NULL DEFAULT 0,
    periodo_mes   SMALLINT NOT NULL,                 -- rol mensual que alimenta
    periodo_anio  SMALLINT NOT NULL,
    afecta_rol    BOOLEAN NOT NULL DEFAULT true,     -- si su valor va al rol del periodo
    observacion   VARCHAR(255),
    estado        VARCHAR(20) NOT NULL DEFAULT 'registrado', -- registrado / pagado / anulado
    eliminado     BOOLEAN NOT NULL DEFAULT false,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by    INTEGER,
    updated_by    INTEGER,
    deleted_at    TIMESTAMP,
    deleted_by    INTEGER
);

CREATE INDEX IF NOT EXISTS idx_vacaciones_empresa  ON vacaciones (id_empresa) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vacaciones_empleado ON vacaciones (id_empresa, id_empleado) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vacaciones_periodo  ON vacaciones (id_empresa, periodo_anio, periodo_mes) WHERE eliminado = false;

-- Menú: reapuntar el submódulo "Vacaciones" (id 47) del módulo Nómina.
UPDATE submodulos_menu SET ruta = 'modulos/vacaciones', status = 1 WHERE id = 47;


-- ############################################################################
-- ## 5. CONTABILIDAD (15 conceptos de asiento tipo 'nomina')
-- ############################################################################
-- ============================================================================
-- Conceptos de asiento para NÓMINA (tipo_asiento = 'nomina') — catálogo global.
-- Habilitan la sección "Nómina" en Configuración Contable para que cada empresa
-- asigne la cuenta real (plan_cuentas) a cada renglón vía asientos_programados.
-- El asiento del rol cuadra por construcción (débitos = créditos).
-- ============================================================================

ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) NOT NULL DEFAULT 'debe';
ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS tipo_cuenta VARCHAR(20);

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
SELECT v.tipo_asiento, v.referencia, v.detalle, v.codigo, v.tipo_cuenta, v.debe_haber
FROM (VALUES
    -- DEBE (gastos)
    ('nomina', 'Gasto Sueldos y Salarios',   'Gasto por las remuneraciones (ingresos) del rol.',                 'GASTOSUELDOSNOMINA',          'gasto',  'debe'),
    ('nomina', 'Gasto Aporte Patronal IESS',  'Gasto por el aporte patronal al IESS.',                            'GASTOAPORTEPATRONALNOMINA',   'gasto',  'debe'),
    ('nomina', 'Gasto Décimo Tercero',        'Provisión mensual del décimo tercero (gasto).',                    'GASTODECIMOTERCERONOMINA',    'gasto',  'debe'),
    ('nomina', 'Gasto Décimo Cuarto',         'Provisión mensual del décimo cuarto (gasto).',                     'GASTODECIMOCUARTONOMINA',     'gasto',  'debe'),
    ('nomina', 'Gasto Vacaciones',            'Provisión mensual de vacaciones (gasto).',                         'GASTOVACACIONESNOMINA',       'gasto',  'debe'),
    ('nomina', 'Gasto Fondos de Reserva',     'Provisión / gasto de fondos de reserva.',                          'GASTOFONDOSRESERVANOMINA',    'gasto',  'debe'),
    ('nomina', 'Gasto Desahucio',             'Provisión mensual del desahucio (gasto).',                         'GASTODESAHUCIONOMINA',        'gasto',  'debe'),
    -- HABER (pasivos / cuentas por pagar-cobrar)
    ('nomina', 'IESS por Pagar',              'Aporte personal + patronal del IESS por pagar.',                   'IESSPORPAGARNOMINA',          'pasivo', 'haber'),
    ('nomina', 'Décimo Tercero por Pagar',    'Provisión del décimo tercero por pagar.',                          'DECIMOTERCEROPORPAGARNOMINA', 'pasivo', 'haber'),
    ('nomina', 'Décimo Cuarto por Pagar',     'Provisión del décimo cuarto por pagar.',                           'DECIMOCUARTOPORPAGARNOMINA',  'pasivo', 'haber'),
    ('nomina', 'Vacaciones por Pagar',        'Provisión de vacaciones por pagar.',                               'VACACIONESPORPAGARNOMINA',    'pasivo', 'haber'),
    ('nomina', 'Fondos de Reserva por Pagar', 'Fondos de reserva por pagar.',                                     'FONDOSRESERVAPORPAGARNOMINA', 'pasivo', 'haber'),
    ('nomina', 'Desahucio por Pagar',         'Provisión del desahucio por pagar.',                               'DESAHUCIOPORPAGARNOMINA',     'pasivo', 'haber'),
    ('nomina', 'Anticipos y Descuentos',      'Anticipos, préstamos y descuentos recuperados del empleado.',      'ANTICIPOSDESCUENTOSNOMINA',   'activo', 'haber'),
    ('nomina', 'Bancos / Líquido a Pagar',    'Líquido a pagar al empleado (banco o caja).',                      'BANCOSNOMINA',                'activo', 'haber')
) AS v(tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber)
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = v.codigo);


-- ############################################################################
-- ## 6. TIPO_AMBIENTE en documentos operativos (Novedades, Vacaciones, Rol)
-- ############################################################################
-- ============================================================================
-- Nómina — tipo_ambiente en documentos operativos (pruebas=1 / producción=2)
-- ----------------------------------------------------------------------------
-- Solo las tablas transaccionales llevan ambiente y filtran por él, como el
-- resto del sistema (facturas, compras). Empleados NO lleva (catálogo maestro
-- compartido). El valor se copia de empresas.tipo_ambiente al insertar y las
-- lecturas filtran por el ambiente actual de la empresa.
-- ============================================================================

ALTER TABLE novedades    ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1';
ALTER TABLE vacaciones   ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1';
ALTER TABLE rol_cabecera ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) NOT NULL DEFAULT '1';

CREATE INDEX IF NOT EXISTS idx_novedades_ambiente    ON novedades (id_empresa, tipo_ambiente) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_vacaciones_ambiente   ON vacaciones (id_empresa, tipo_ambiente) WHERE eliminado = false;
CREATE INDEX IF NOT EXISTS idx_rol_cabecera_ambiente ON rol_cabecera (id_empresa, tipo_ambiente) WHERE eliminado = false;

-- Recrear la unicidad de la corrida incluyendo el ambiente (no choca pruebas vs producción).
DROP INDEX IF EXISTS uk_rol_cabecera_periodo;
CREATE UNIQUE INDEX IF NOT EXISTS uk_rol_cabecera_periodo
    ON rol_cabecera (id_empresa, tipo_ambiente, tipo_rol, periodo_anio, periodo_mes, numero_periodo)
    WHERE eliminado = false;

