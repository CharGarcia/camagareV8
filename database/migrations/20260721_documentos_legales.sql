-- ============================================================================
-- Documentos legales del sistema: Acuerdo de uso de datos + Contrato de uso.
--
-- Se envían por correo al CREAR una empresa y también manualmente (reenvío)
-- para las empresas ya existentes. El destinatario es el correo de la empresa.
-- La aceptación se registra con token, fecha, IP y navegador (evidencia auditable).
--
-- documentos_legales      -> CONFIGURACIÓN GLOBAL (sin id_empresa), versionada.
-- empresas_documentos_envios -> registro por empresa de cada envío/aceptación.
--
-- Idempotente: se puede correr varias veces sin error.
-- ============================================================================

-- ─── 1) Textos legales versionados (global, editable por super admin) ────────
CREATE TABLE IF NOT EXISTS documentos_legales (
    id           SERIAL PRIMARY KEY,
    tipo         VARCHAR(30)  NOT NULL,           -- 'acuerdo_datos' | 'contrato_uso'
    version      INTEGER      NOT NULL DEFAULT 1,
    titulo       VARCHAR(255) NOT NULL,
    contenido    TEXT         NOT NULL,           -- HTML (soporta {{placeholders}})
    vigente      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by   INTEGER,
    updated_at   TIMESTAMP,
    updated_by   INTEGER,
    eliminado    BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);

CREATE INDEX IF NOT EXISTS idx_doc_legales_tipo_vigente
    ON documentos_legales (tipo, vigente) WHERE eliminado = FALSE;

-- Solo UNA versión vigente por tipo
CREATE UNIQUE INDEX IF NOT EXISTS uq_doc_legales_vigente
    ON documentos_legales (tipo) WHERE vigente = TRUE AND eliminado = FALSE;

CREATE UNIQUE INDEX IF NOT EXISTS uq_doc_legales_tipo_version
    ON documentos_legales (tipo, version) WHERE eliminado = FALSE;

-- ─── 2) Envíos y aceptaciones por empresa ────────────────────────────────────
CREATE TABLE IF NOT EXISTS empresas_documentos_envios (
    id                   SERIAL PRIMARY KEY,
    id_empresa           INTEGER      NOT NULL,
    id_acuerdo           INTEGER      REFERENCES documentos_legales (id),
    id_contrato          INTEGER      REFERENCES documentos_legales (id),
    correo_destino       VARCHAR(255) NOT NULL,
    token                VARCHAR(64)  NOT NULL,
    estado               VARCHAR(20)  NOT NULL DEFAULT 'enviado',  -- enviado | aceptado
    enviado_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviado_by           INTEGER,
    aceptado_at          TIMESTAMP,
    aceptado_ip          VARCHAR(64),
    aceptado_user_agent  TEXT,
    aceptado_nombre      VARCHAR(255),
    aceptado_identificacion VARCHAR(30),
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by   INTEGER,
    updated_at   TIMESTAMP,
    updated_by   INTEGER,
    eliminado    BOOLEAN   NOT NULL DEFAULT FALSE,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_emp_doc_envios_token
    ON empresas_documentos_envios (token);
CREATE INDEX IF NOT EXISTS idx_emp_doc_envios_empresa
    ON empresas_documentos_envios (id_empresa) WHERE eliminado = FALSE;

-- ─── 3) Textos base (v1). EDITABLES desde /config/documentos-legales ─────────
-- IMPORTANTE: es una redacción BASE de referencia. Revísela con su asesor legal
-- y ajústela desde la pantalla de configuración (crea una versión nueva).
-- Placeholders disponibles: {{empresa_nombre}}, {{empresa_ruc}},
-- {{empresa_direccion}}, {{empresa_representante}}, {{fecha}}, {{sistema_nombre}}

INSERT INTO documentos_legales (tipo, version, titulo, contenido, vigente)
SELECT 'acuerdo_datos', 1, 'Acuerdo de Tratamiento y Uso de Datos Personales',
'<h3>ACUERDO DE TRATAMIENTO Y USO DE DATOS PERSONALES</h3>
<p>En la ciudad, a {{fecha}}, entre <b>{{sistema_nombre}}</b> (en adelante, "el Proveedor") y <b>{{empresa_nombre}}</b>, con RUC <b>{{empresa_ruc}}</b>, domiciliada en {{empresa_direccion}}, representada por {{empresa_representante}} (en adelante, "el Cliente"), se celebra el presente acuerdo:</p>
<p><b>PRIMERA — Objeto.</b> El presente acuerdo regula el tratamiento de los datos personales que el Cliente incorpore al sistema, conforme a la Ley Orgánica de Protección de Datos Personales (LOPDP) del Ecuador y su reglamento.</p>
<p><b>SEGUNDA — Roles.</b> El Cliente actúa como <i>responsable del tratamiento</i> de los datos que registra (clientes, proveedores, empleados). El Proveedor actúa como <i>encargado del tratamiento</i>, y trata dichos datos únicamente siguiendo las instrucciones del Cliente y para prestar el servicio contratado.</p>
<p><b>TERCERA — Finalidad.</b> Los datos se tratan exclusivamente para operar el sistema: emisión de comprobantes electrónicos, contabilidad, inventarios, nómina y demás módulos contratados, así como para cumplir obligaciones legales y tributarias ante el SRI.</p>
<p><b>CUARTA — Confidencialidad.</b> El Proveedor guardará reserva sobre la información del Cliente y no la cederá ni comercializará a terceros, salvo obligación legal o requerimiento de autoridad competente.</p>
<p><b>QUINTA — Seguridad.</b> El Proveedor aplica medidas técnicas y organizativas razonables: control de acceso por usuario y perfil, cifrado en tránsito, respaldos periódicos y registro de auditoría de las operaciones.</p>
<p><b>SEXTA — Derechos del titular.</b> Los titulares de los datos podrán ejercer sus derechos de acceso, rectificación, actualización, eliminación, oposición y portabilidad. El Cliente es el canal primario para atender dichas solicitudes; el Proveedor prestará la asistencia técnica necesaria.</p>
<p><b>SÉPTIMA — Conservación y devolución.</b> Terminada la relación contractual, el Cliente podrá solicitar la exportación de su información. Los datos se conservarán por los plazos que exija la normativa tributaria y contable vigente.</p>
<p><b>OCTAVA — Subencargados.</b> El Proveedor podrá apoyarse en proveedores de infraestructura (alojamiento en la nube) que garanticen un nivel de protección equivalente.</p>
<p><b>NOVENA — Incidentes.</b> El Proveedor notificará al Cliente, sin dilación indebida, cualquier vulneración de seguridad que afecte sus datos personales.</p>
<p><b>DÉCIMA — Aceptación.</b> La aceptación electrónica de este documento deja constancia de fecha, hora y dirección IP, y tiene plena validez conforme a la Ley de Comercio Electrónico, Firmas Electrónicas y Mensajes de Datos.</p>'
, TRUE
WHERE NOT EXISTS (SELECT 1 FROM documentos_legales WHERE tipo = 'acuerdo_datos' AND eliminado = FALSE);

INSERT INTO documentos_legales (tipo, version, titulo, contenido, vigente)
SELECT 'contrato_uso', 1, 'Contrato de Prestación de Servicios y Uso del Sistema',
'<h3>CONTRATO DE PRESTACIÓN DE SERVICIOS Y USO DEL SISTEMA</h3>
<p>En la ciudad, a {{fecha}}, entre <b>{{sistema_nombre}}</b> (en adelante, "el Proveedor") y <b>{{empresa_nombre}}</b>, con RUC <b>{{empresa_ruc}}</b>, domiciliada en {{empresa_direccion}}, representada por {{empresa_representante}} (en adelante, "el Cliente"), se celebra el presente contrato:</p>
<p><b>PRIMERA — Objeto.</b> El Proveedor concede al Cliente una licencia de uso, no exclusiva e intransferible, del sistema de gestión empresarial, en la modalidad de servicio en línea (SaaS), por el tiempo que se mantenga vigente la suscripción.</p>
<p><b>SEGUNDA — Alcance.</b> La licencia habilita el uso de los módulos contratados para las empresas y establecimientos registrados por el Cliente. No incluye el código fuente ni autoriza su copia, redistribución, sublicencia o ingeniería inversa.</p>
<p><b>TERCERA — Suscripción y pago.</b> El Cliente pagará el valor y la periodicidad acordados. La falta de pago faculta al Proveedor a suspender el acceso, previo aviso, conservando la información del Cliente durante el plazo legal.</p>
<p><b>CUARTA — Obligaciones del Cliente.</b> Usar el sistema conforme a la ley; mantener la confidencialidad de sus credenciales; verificar la veracidad de la información que registra; y ser responsable de las declaraciones y comprobantes que emita ante el SRI.</p>
<p><b>QUINTA — Obligaciones del Proveedor.</b> Mantener el servicio disponible en condiciones razonables; aplicar respaldos periódicos; brindar soporte por los canales establecidos; y notificar con anticipación los mantenimientos programados.</p>
<p><b>SEXTA — Disponibilidad.</b> El Proveedor procurará la máxima continuidad del servicio, sin que ello constituya una garantía de disponibilidad ininterrumpida, pudiendo existir interrupciones por mantenimiento, fuerza mayor o fallas de terceros proveedores.</p>
<p><b>SÉPTIMA — Propiedad intelectual.</b> El sistema, su código, diseño, marcas y documentación son de propiedad exclusiva del Proveedor. La información y los datos cargados por el Cliente son de propiedad del Cliente.</p>
<p><b>OCTAVA — Responsabilidad.</b> El Proveedor no responde por el uso indebido del sistema, por errores en la información ingresada por el Cliente, ni por sanciones derivadas del incumplimiento tributario del Cliente.</p>
<p><b>NOVENA — Vigencia y terminación.</b> El contrato rige mientras la suscripción esté activa. Cualquiera de las partes puede darlo por terminado notificando con antelación razonable. A la terminación, el Cliente podrá solicitar la exportación de su información.</p>
<p><b>DÉCIMA — Protección de datos.</b> El tratamiento de datos personales se rige por el Acuerdo de Tratamiento y Uso de Datos Personales, que forma parte integrante de este contrato.</p>
<p><b>DÉCIMA PRIMERA — Aceptación.</b> La aceptación electrónica de este documento deja constancia de fecha, hora y dirección IP, y tiene plena validez conforme a la Ley de Comercio Electrónico, Firmas Electrónicas y Mensajes de Datos.</p>'
, TRUE
WHERE NOT EXISTS (SELECT 1 FROM documentos_legales WHERE tipo = 'contrato_uso' AND eliminado = FALSE);

COMMENT ON TABLE documentos_legales IS 'Textos legales versionados (global). Solo una versión vigente por tipo.';
COMMENT ON TABLE empresas_documentos_envios IS 'Envío y aceptación de documentos legales por empresa (evidencia: fecha, IP, user agent).';
