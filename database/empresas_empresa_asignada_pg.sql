-- PostgreSQL: empresas + empresa_asignada (requeridas tras el login)
-- Ejecutar DESPUÉS de database/usuarios_pg.sql (necesita public.usuarios)

-- ========== empresas ==========
CREATE TABLE empresas (
    id                      SERIAL PRIMARY KEY,
    nombre                  VARCHAR(255) NOT NULL,
    nombre_comercial        VARCHAR(255) NOT NULL DEFAULT '',
    ruc                     VARCHAR(20)  NOT NULL,
    establecimiento         VARCHAR(50)  NOT NULL,
    direccion               VARCHAR(255) NOT NULL DEFAULT '',
    telefono                VARCHAR(50)  NOT NULL DEFAULT '',
    tipo                    VARCHAR(10)  NOT NULL DEFAULT '01',
    nom_rep_legal           VARCHAR(255) NOT NULL DEFAULT '',
    ced_rep_legal           VARCHAR(20)  NOT NULL DEFAULT '',
    mail                    VARCHAR(150) NOT NULL DEFAULT '',
    cod_prov                VARCHAR(10)  NOT NULL DEFAULT '',
    cod_ciudad              VARCHAR(10)  NOT NULL DEFAULT '',
    estado                  VARCHAR(2)   NOT NULL DEFAULT '1',
    fecha_agregado          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario              INTEGER      NOT NULL DEFAULT 0,
    nombre_contador         VARCHAR(255) NOT NULL DEFAULT '',
    ruc_contador            VARCHAR(20)  NOT NULL DEFAULT '',
    valor_cobro             NUMERIC(15, 4) NULL,
    periodo_vigencia_desde  DATE NULL,
    periodo_vigencia_hasta  DATE NULL,
    estado_pago             VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_empresas_ruc_establecimiento ON empresas (ruc, establecimiento) WHERE (eliminado = false);

-- ========== empresa_asignada ==========
CREATE TABLE empresa_asignada (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER NOT NULL REFERENCES empresas (id) ON DELETE CASCADE,
    id_usuario      INTEGER NOT NULL REFERENCES usuarios (id) ON DELETE CASCADE,
    usu_asignador   INTEGER NOT NULL DEFAULT 0,
    fecha_agregado  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_empresa_asignada_usuario_empresa UNIQUE (id_empresa, id_usuario)
);

CREATE INDEX idx_empresa_asignada_usuario ON empresa_asignada (id_usuario);
CREATE INDEX idx_empresa_asignada_empresa ON empresa_asignada (id_empresa);

-- ========== Datos mínimos de ejemplo (ajusta id de usuario) ==========
-- Si tu usuario tiene id = 2 y nivel < 3, necesita al menos una fila en empresa_asignada.
-- Descomenta y ajusta:

-- INSERT INTO empresas (nombre, nombre_comercial, ruc, establecimiento, estado, id_usuario)
-- VALUES ('Demo', 'Mi empresa', '0999999999001', '001', '1', 2);
--
-- INSERT INTO empresa_asignada (id_empresa, id_usuario, usu_asignador)
-- VALUES (1, 2, 2);
--
-- SELECT setval(pg_get_serial_sequence('empresas', 'id'), (SELECT COALESCE(MAX(id), 1) FROM empresas));
-- SELECT setval(pg_get_serial_sequence('empresa_asignada', 'id'), (SELECT COALESCE(MAX(id), 1) FROM empresa_asignada));
