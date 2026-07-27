-- ============================================================================
-- Módulo: Taller Mecánico — Órdenes de Trabajo (OT)
-- Ruta MVC: modulos/taller  (catálogo de departamentos: modulos/taller-departamentos)
--
-- Un vehículo ingresa al taller, el asesor registra la recepción (checklist de
-- accesorios, daños previos, fotos y motivo de ingreso) y a partir de ahí la OT
-- recorre los DEPARTAMENTOS del taller (diagnóstico, mecánica, enderezada,
-- pintura, armado, control de calidad…). En cada departamento el operario, desde
-- una tablet, registra el trabajo realizado y agrega los servicios y repuestos
-- que consumió. Todo queda trazado (quién, cuándo, en qué departamento) para
-- emitir el informe técnico al entregar el vehículo y luego facturar.
--
-- Reglas del sistema (CLAUDE.md):
--   - Multiempresa: todas las tablas llevan id_empresa.
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by.
--   - La OT no genera asiento contable propio: lo produce el documento de venta
--     (Factura/Recibo) al emitirse, igual que el módulo Car-Wash.
--   - numero_orden usa secuenciales por punto de emisión (tipo de documento
--     'Ordenes de taller' en SecuencialRepository).
--
-- Decisiones de diseño acordadas:
--   1) INVENTARIO: el repuesto descuenta stock cuando la línea queda APROBADA
--      (refTipo 'taller_orden' en el kardex). Al facturar se revierte y el
--      documento hace su propia salida, igual que Car-Wash.
--   2) APROBACIÓN OBLIGATORIA: ninguna línea se ejecuta ni se factura sin
--      aprobación del cliente registrada (quién, cuándo y por qué medio).
--   3) TÉCNICOS: se guarda el usuario del sistema que registró (auditoría) y,
--      además, el empleado que ejecutó el trabajo (productividad/comisiones).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Departamentos del taller (catálogo configurable por empresa).
--    NO es un enum fijo: cada taller arma los suyos. Mismo patrón que
--    estaciones_impresion (KDS). Cada departamento tiene su pantalla de tablet.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS taller_departamentos (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    nombre              VARCHAR(100) NOT NULL,
    codigo              VARCHAR(20),
    descripcion         VARCHAR(300),
    color               VARCHAR(20)  DEFAULT '#0d6efd',   -- color de la columna en el tablero
    icono               VARCHAR(50)  DEFAULT 'bi-tools',  -- ícono Bootstrap Icons
    orden               INTEGER      NOT NULL DEFAULT 0,  -- orden sugerido del flujo
    es_diagnostico      BOOLEAN      NOT NULL DEFAULT FALSE, -- departamento que emite el diagnóstico
    es_control_calidad  BOOLEAN      NOT NULL DEFAULT FALSE, -- departamento de verificación final
    activo              BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- 2. Cabecera de la Orden de Trabajo.
--    Guarda snapshot del vehículo al ingresar (el vehículo puede cambiar luego)
--    y los datos de contacto del usuario del vehículo, que muchas veces NO es el
--    propietario (flota, empresa, familiar). Eso ya existía en el sistema viejo
--    (encabezado_mecanica) y se conserva.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS taller_ordenes (
    id                      SERIAL PRIMARY KEY,
    id_empresa              INTEGER NOT NULL,
    -- numeración con reglas de secuencial (igual que recibo de venta / car-wash)
    id_establecimiento      INTEGER,
    id_punto_emision        INTEGER,
    establecimiento         VARCHAR(3),
    punto_emision           VARCHAR(3),
    secuencial              VARCHAR(20),
    tipo_ambiente           VARCHAR(1) DEFAULT '1',
    numero_orden            VARCHAR(25) NOT NULL,     -- establecimiento-punto-secuencial

    id_vehiculo             INTEGER NOT NULL,
    id_cliente              INTEGER,                  -- opcional al recibir; obligatorio al facturar
    id_bodega               INTEGER,                  -- bodega por defecto para los repuestos

    -- snapshot del vehículo al ingresar
    placa                   VARCHAR(20),
    marca                   VARCHAR(100),
    modelo                  VARCHAR(100),
    anio                    VARCHAR(10),
    color                   VARCHAR(50),
    chasis                  VARCHAR(100),
    motor                   VARCHAR(100),
    kilometraje             INTEGER,
    nivel_combustible       VARCHAR(10),              -- E, 1/4, 1/2, 3/4, F

    -- contacto: quién usa el vehículo (puede no ser el propietario ni el cliente)
    nombre_usuario          VARCHAR(200),
    telefono_contacto       VARCHAR(50),
    correo_contacto         VARCHAR(150),

    -- responsables
    id_asesor               INTEGER,                  -- usuario que recibe el vehículo
    id_empleado_asesor      INTEGER,                  -- empleado asesor de servicio
    id_empleado_jefe        INTEGER,                  -- jefe de taller responsable

    -- tiempos
    fecha_ingreso           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_estimada_entrega  TIMESTAMP,
    fecha_entrega           TIMESTAMP,

    -- flujo
    tipo_servicio           VARCHAR(20) DEFAULT 'correctivo', -- mantenimiento|correctivo|colision|garantia|revision
    prioridad               VARCHAR(10) DEFAULT 'normal',     -- baja|normal|alta|urgente
    estado                  VARCHAR(20) NOT NULL DEFAULT 'recepcion',
        -- recepcion|diagnostico|presupuesto|aprobada|en_proceso|control_calidad|
        -- terminada|entregada|facturada|anulada
    id_departamento_actual  INTEGER,                  -- dónde está físicamente el vehículo

    -- contenido técnico
    motivo_ingreso          TEXT,                     -- lo que reporta el cliente ("suena al frenar")
    diagnostico_texto       TEXT,                     -- lo que encuentra el técnico
    observaciones           TEXT,
    recomendaciones         TEXT,                     -- lo que queda pendiente para una próxima visita

    -- aprobación del presupuesto por el cliente (OBLIGATORIA antes de ejecutar)
    aprobado                BOOLEAN NOT NULL DEFAULT FALSE,
    aprobado_por            VARCHAR(200),             -- nombre de quien aprobó del lado del cliente
    aprobado_medio          VARCHAR(20),              -- presencial|telefono|whatsapp|correo|sistema
    aprobado_fecha          TIMESTAMP,
    aprobado_usuario        INTEGER,                  -- usuario del sistema que registró la aprobación
    aprobado_observacion    TEXT,

    -- siniestro / aseguradora (habitual en enderezada y pintura)
    es_siniestro            BOOLEAN NOT NULL DEFAULT FALSE,
    aseguradora             VARCHAR(150),
    numero_siniestro        VARCHAR(60),
    deducible               NUMERIC(14,2) DEFAULT 0,
    ajustador               VARCHAR(150),

    -- entrega y post-venta
    garantia_dias           INTEGER DEFAULT 0,
    garantia_km             INTEGER DEFAULT 0,
    proximo_mantenimiento_km INTEGER,
    proxima_cita            DATE,
    entregado_a             VARCHAR(200),             -- quién retiró el vehículo
    kilometraje_salida      INTEGER,

    info_adicional          JSONB,                    -- [{nombre, valor}] campos extra (estilo factura)

    -- totales calculados de los detalles aprobados y facturables
    subtotal_repuestos      NUMERIC(14,2) NOT NULL DEFAULT 0,
    subtotal_mano_obra      NUMERIC(14,2) NOT NULL DEFAULT 0,
    subtotal                NUMERIC(14,2) NOT NULL DEFAULT 0,
    descuento               NUMERIC(14,2) NOT NULL DEFAULT 0,
    iva                     NUMERIC(14,2) NOT NULL DEFAULT 0,
    total                   NUMERIC(14,2) NOT NULL DEFAULT 0,

    -- documento de venta generado desde la OT
    tipo_documento          VARCHAR(10),              -- 'FACTURA' | 'RECIBO'
    id_documento            INTEGER,                  -- id en ventas_cabecera o recibos_venta_cabecera
    numero_documento        VARCHAR(20),

    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by              INTEGER,
    updated_by              INTEGER,
    eliminado               BOOLEAN DEFAULT FALSE,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER
);

-- ---------------------------------------------------------------------------
-- 3. Detalle: repuestos, mano de obra, insumos y trabajos de terceros.
--    ESTA ES LA TABLA CLAVE DEL FLUJO POR DEPARTAMENTOS: cada línea sabe en qué
--    departamento se agregó, qué usuario la registró y qué empleado la ejecutó.
--    Nada se ejecuta ni se factura sin estado_linea = 'aprobada'/'ejecutada'.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS taller_ordenes_detalle (
    id                  SERIAL PRIMARY KEY,
    id_orden            INTEGER NOT NULL REFERENCES taller_ordenes(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,

    -- trazabilidad del pedido del usuario: qué departamento y quién lo agregó
    id_departamento     INTEGER,
    id_usuario_registro INTEGER,                  -- usuario del sistema (auditoría)
    id_empleado_tecnico INTEGER,                  -- empleado que ejecutó (productividad)
    fecha_registro      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    tipo_linea          VARCHAR(15) NOT NULL DEFAULT 'repuesto',
        -- repuesto | mano_obra | insumo | tercero
    id_producto         INTEGER,                  -- null si es libre
    es_libre            BOOLEAN NOT NULL DEFAULT FALSE,
    descripcion         VARCHAR(300) NOT NULL,
    id_bodega           INTEGER,

    cantidad            NUMERIC(18,6) NOT NULL DEFAULT 1,
    horas               NUMERIC(10,2) DEFAULT 0,  -- para mano de obra
    precio_unitario     NUMERIC(18,6) NOT NULL DEFAULT 0,
    costo_unitario      NUMERIC(18,6) NOT NULL DEFAULT 0,  -- para medir margen de la OT
    descuento           NUMERIC(14,2) NOT NULL DEFAULT 0,
    porcentaje_iva      NUMERIC(5,2)  NOT NULL DEFAULT 0,
    valor_iva           NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_linea         NUMERIC(14,2) NOT NULL DEFAULT 0,
    id_tarifa_iva       INTEGER,

    -- aprobación por línea (el cliente puede aprobar unas cosas y rechazar otras)
    estado_linea        VARCHAR(12) NOT NULL DEFAULT 'sugerida',
        -- sugerida | aprobada | rechazada | ejecutada
    motivo_rechazo      VARCHAR(300),

    -- un repuesto traído por el cliente se registra pero NO se factura
    facturable          BOOLEAN NOT NULL DEFAULT TRUE,
    provisto_cliente    BOOLEAN NOT NULL DEFAULT FALSE,

    -- control del descuento de stock (ver decisión 1 de la cabecera)
    inventario_aplicado BOOLEAN NOT NULL DEFAULT FALSE,
    id_kardex           INTEGER,

    observacion         VARCHAR(300),
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- 4. Etapas: el recorrido del vehículo por los departamentos.
--    Una fila por cada paso. Es lo que permite saber cuánto tiempo estuvo el
--    vehículo en pintura y qué hizo exactamente ese departamento.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS taller_ordenes_etapas (
    id                      SERIAL PRIMARY KEY,
    id_orden                INTEGER NOT NULL REFERENCES taller_ordenes(id) ON DELETE CASCADE,
    id_empresa              INTEGER NOT NULL,
    id_departamento         INTEGER NOT NULL,
    secuencia               INTEGER NOT NULL DEFAULT 0,

    estado                  VARCHAR(12) NOT NULL DEFAULT 'pendiente',
        -- pendiente | en_proceso | terminada | omitida

    fecha_asignacion        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio            TIMESTAMP,
    fecha_fin               TIMESTAMP,
    id_usuario_inicio       INTEGER,
    id_usuario_fin          INTEGER,
    id_empleado_responsable INTEGER,

    trabajo_realizado       TEXT,                 -- lo que escribe el operario
    observaciones           TEXT,

    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by              INTEGER,
    updated_by              INTEGER,
    eliminado               BOOLEAN DEFAULT FALSE,
    deleted_at              TIMESTAMP,
    deleted_by              INTEGER
);

-- ---------------------------------------------------------------------------
-- 5. Bitácora: todo lo que ocurrió, en orden cronológico. Sustituye a la vieja
--    tabla observaciones_mecanica y es la materia prima del informe técnico.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS taller_ordenes_bitacora (
    id                  SERIAL PRIMARY KEY,
    id_orden            INTEGER NOT NULL REFERENCES taller_ordenes(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    id_departamento     INTEGER,
    id_usuario          INTEGER,
    fecha               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_evento         VARCHAR(25) NOT NULL DEFAULT 'nota',
        -- ingreso|diagnostico|nota|cambio_estado|cambio_departamento|linea_agregada|
        -- linea_eliminada|aprobacion|rechazo|foto|entrega|facturacion
    concepto            VARCHAR(150) NOT NULL,
    detalle             TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado           BOOLEAN DEFAULT FALSE
);

-- ---------------------------------------------------------------------------
-- 6. Checklist de recepción: inventario de accesorios y estado de carrocería.
--    La plantilla es configurable por empresa; al crear la OT se copia a la
--    tabla de la orden para que quede congelada como evidencia del ingreso.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS taller_checklist_plantilla (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    grupo               VARCHAR(30) NOT NULL DEFAULT 'accesorios',
        -- accesorios | carroceria | documentos | niveles
    item                VARCHAR(150) NOT NULL,
    orden               INTEGER NOT NULL DEFAULT 0,
    activo              BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

CREATE TABLE IF NOT EXISTS taller_ordenes_checklist (
    id                  SERIAL PRIMARY KEY,
    id_orden            INTEGER NOT NULL REFERENCES taller_ordenes(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    grupo               VARCHAR(30) NOT NULL DEFAULT 'accesorios',
    item                VARCHAR(150) NOT NULL,
    valor               VARCHAR(20) NOT NULL DEFAULT 'no',   -- si | no | na  (o bueno|regular|malo)
    observacion         VARCHAR(300),
    orden               INTEGER NOT NULL DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado           BOOLEAN DEFAULT FALSE
);

-- ---------------------------------------------------------------------------
-- 7. Fotos: evidencia del estado del vehículo y del trabajo por departamento.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS taller_ordenes_fotos (
    id                  SERIAL PRIMARY KEY,
    id_orden            INTEGER NOT NULL REFERENCES taller_ordenes(id) ON DELETE CASCADE,
    id_empresa          INTEGER NOT NULL,
    id_departamento     INTEGER,
    momento             VARCHAR(15) NOT NULL DEFAULT 'ingreso', -- ingreso|proceso|entrega
    ruta_archivo        VARCHAR(300) NOT NULL,
    nombre_original     VARCHAR(200),
    descripcion         VARCHAR(300),
    id_usuario          INTEGER,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- Índices
-- ---------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_taller_dep_empresa      ON taller_departamentos (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_ord_empresa      ON taller_ordenes (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_ord_estado       ON taller_ordenes (id_empresa, estado, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_ord_depto        ON taller_ordenes (id_departamento_actual, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_ord_vehiculo     ON taller_ordenes (id_vehiculo);
CREATE INDEX IF NOT EXISTS idx_taller_ord_cliente      ON taller_ordenes (id_cliente);
CREATE INDEX IF NOT EXISTS idx_taller_ord_punto        ON taller_ordenes (id_punto_emision, tipo_ambiente);
CREATE INDEX IF NOT EXISTS idx_taller_det_orden        ON taller_ordenes_detalle (id_orden, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_det_depto        ON taller_ordenes_detalle (id_departamento);
CREATE INDEX IF NOT EXISTS idx_taller_det_tecnico      ON taller_ordenes_detalle (id_empleado_tecnico);
CREATE INDEX IF NOT EXISTS idx_taller_etapa_orden      ON taller_ordenes_etapas (id_orden, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_etapa_depto      ON taller_ordenes_etapas (id_departamento, estado, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_bit_orden        ON taller_ordenes_bitacora (id_orden, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_chk_orden        ON taller_ordenes_checklist (id_orden, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_chk_plantilla    ON taller_checklist_plantilla (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_taller_foto_orden       ON taller_ordenes_fotos (id_orden, eliminado);

-- Unicidad del secuencial por empresa + punto de emisión + ambiente (como car-wash).
CREATE UNIQUE INDEX IF NOT EXISTS uq_taller_secuencial
    ON taller_ordenes (id_empresa, id_punto_emision, secuencial, tipo_ambiente)
    WHERE eliminado = false;

-- Un departamento no se repite por nombre dentro de la empresa.
CREATE UNIQUE INDEX IF NOT EXISTS uq_taller_dep_nombre
    ON taller_departamentos (id_empresa, UPPER(nombre))
    WHERE eliminado = false;

-- ============================================================================
-- Ampliación de la tabla vehiculos.
-- Hoy solo guarda marca/placa/chasis/anio/propietario/correo/telefono, que
-- alcanza para el car-wash pero no para una mecánica. Se agregan las columnas
-- que el taller necesita. Son todas opcionales: no rompen nada existente.
-- ============================================================================
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS modelo             VARCHAR(100);
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS color              VARCHAR(50);
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS tipo_vehiculo      VARCHAR(50);   -- auto, camioneta, moto, camión, bus…
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS motor              VARCHAR(100);
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS cilindraje         VARCHAR(30);
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS transmision        VARCHAR(30);   -- manual | automática
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS combustible        VARCHAR(30);   -- gasolina | diésel | híbrido | eléctrico | gas
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS kilometraje_actual INTEGER;
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS id_cliente         INTEGER;       -- vincula el vehículo con el cliente del sistema
ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS observaciones      TEXT;

CREATE INDEX IF NOT EXISTS idx_vehiculos_cliente ON vehiculos (id_cliente);

-- ============================================================================
-- Datos base sugeridos.
-- Ejecutar SOLO si se desea precargar los departamentos y el checklist típicos
-- de un taller. Reemplazar :ID_EMPRESA por la empresa correspondiente
-- (en producción la empresa migrada es la 8) y :ID_USUARIO por el usuario.
-- ============================================================================
-- INSERT INTO taller_departamentos (id_empresa, nombre, codigo, color, icono, orden, es_diagnostico, es_control_calidad, created_by, updated_by) VALUES
--   (:ID_EMPRESA, 'Diagnóstico',            'DIAG', '#6f42c1', 'bi-clipboard-pulse',  10, TRUE,  FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Mecánica general',       'MEC',  '#0d6efd', 'bi-tools',            20, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Suspensión y dirección', 'SUSP', '#0dcaf0', 'bi-cone-striped',     30, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Frenos',                 'FRE',  '#dc3545', 'bi-record-circle',    40, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Electricidad',           'ELE',  '#ffc107', 'bi-lightning-charge', 50, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Aire acondicionado',     'AC',   '#20c997', 'bi-snow',             60, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Desarme',                'DES',  '#6c757d', 'bi-box-seam',         70, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Enderezada',             'END',  '#fd7e14', 'bi-hammer',           80, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Preparación',            'PREP', '#795548', 'bi-brush',            90, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Pintura',                'PIN',  '#e83e8c', 'bi-palette',         100, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Pulido',                 'PUL',  '#17a2b8', 'bi-stars',           110, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Armado',                 'ARM',  '#198754', 'bi-nut',             120, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Lavado',                 'LAV',  '#0dcaf0', 'bi-droplet-half',    130, FALSE, FALSE, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'Control de calidad',     'CC',   '#198754', 'bi-patch-check',     140, FALSE, TRUE,  :ID_USUARIO, :ID_USUARIO);
--
-- INSERT INTO taller_checklist_plantilla (id_empresa, grupo, item, orden, created_by, updated_by) VALUES
--   (:ID_EMPRESA, 'accesorios', 'Llanta de emergencia',    10, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'accesorios', 'Gata',                    20, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'accesorios', 'Llave de ruedas',         30, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'accesorios', 'Triángulos',              40, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'accesorios', 'Extintor',                50, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'accesorios', 'Botiquín',                60, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'accesorios', 'Radio / pantalla',        70, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'accesorios', 'Alfombras',               80, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'accesorios', 'Herramientas',            90, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'documentos', 'Matrícula',              100, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'documentos', 'SOAT / seguro',          110, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'carroceria', 'Rayones / abolladuras',  120, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'carroceria', 'Parabrisas',             130, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'carroceria', 'Espejos',                140, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'carroceria', 'Luces',                  150, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'niveles',    'Nivel de aceite',        160, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'niveles',    'Nivel de refrigerante',  170, :ID_USUARIO, :ID_USUARIO),
--   (:ID_EMPRESA, 'niveles',    'Líquido de frenos',      180, :ID_USUARIO, :ID_USUARIO);

-- ============================================================================
-- Registro del submódulo y permisos: se hace MANUALMENTE (regla del proyecto).
--   UPDATE/INSERT en submodulos_menu con ruta = 'modulos/taller'
--   y ruta = 'modulos/taller-departamentos', luego asignar permisos en
--   /config/permisos-modulos.
-- Secuencial: crear el tipo de documento 'Ordenes de taller' para el punto de
--   emisión que corresponda (ya está mapeado en SecuencialRepository).
-- ============================================================================
