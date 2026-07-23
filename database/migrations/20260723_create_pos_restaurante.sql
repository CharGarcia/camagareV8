-- ============================================================================
-- Módulo: POS Restaurantes — Fase 0 (modelo de datos + config)
-- Rutas MVC: modulos/mesas (ya existía el controlador, faltaba la tabla),
--            modulos/comandas, modulos/kds (fases siguientes)
--
-- Contexto:
--   Módulo INDEPENDIENTE del POS de mostrador (modulos/caja-pos), que sigue
--   intacto y no se modifica ni se toca en esta migración. Mesas/Comandas es
--   su propia área (modulos/mesas, modulos/comandas), con su propio permiso
--   por submódulo — no depende de ningún flag de configuración del mostrador.
--
--   Las comandas NO generan su propio documento de venta ni afectan
--   inventario/contabilidad directamente (igual que carwash_ordenes): al
--   cobrar, cada "grupo de cobro" de la comanda genera una Factura o un
--   Recibo de Venta reutilizando PosVentaService::cobrar(), que ya se
--   encarga de inventario, asiento contable e Ingreso de cobro.
--
-- Reglas del sistema:
--   - Multiempresa: todas las tablas llevan id_empresa.
--   - Eliminación lógica: eliminado / deleted_at / deleted_by.
--   - Auditoría: created_at/by, updated_at/by.
--   - numero_comanda es un correlativo interno (no pasa por SecuencialService,
--     no es un documento SRI).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Mesas: el controlador/service/repository ya existían (MesasController) pero
-- la tabla no estaba creada en el entorno. Se crea completa aquí; si ya
-- existiera (algún entorno donde sí se llegó a crear manualmente), el CREATE
-- IF NOT EXISTS no hace nada y el ALTER agrega la columna que falte.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mesas (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_usuario          INTEGER,
    nombre              VARCHAR(100) NOT NULL,
    estado              VARCHAR(20) NOT NULL DEFAULT 'disponible', -- disponible|ocupada|por_cobrar
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

ALTER TABLE mesas ADD COLUMN IF NOT EXISTS capacidad INTEGER;
-- Zona/piso en texto libre (ej. "Piso 1", "Terraza") — agrupa el tablero en pestañas.
ALTER TABLE mesas ADD COLUMN IF NOT EXISTS ubicacion VARCHAR(60);
-- Posición libre en el plano del tablero (porcentaje 0-100 del lienzo, no píxeles,
-- para que se mantenga proporcional entre dispositivos); NULL = sin acomodar aún.
ALTER TABLE mesas ADD COLUMN IF NOT EXISTS pos_x NUMERIC(6,2);
ALTER TABLE mesas ADD COLUMN IF NOT EXISTS pos_y NUMERIC(6,2);

CREATE INDEX IF NOT EXISTS idx_mesas_empresa ON mesas (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_mesas_estado  ON mesas (id_empresa, estado, eliminado);

-- ---------------------------------------------------------------------------
-- Categorías: destino de impresión (Cocina/Barra) por categoría de producto,
-- reemplazo moderno del "opciones_envio_impresion" del sistema anterior. Sin
-- tabla aparte: categorias ya es operativa por empresa.
-- ---------------------------------------------------------------------------
ALTER TABLE categorias
    ADD COLUMN IF NOT EXISTS destino_impresion VARCHAR(20) DEFAULT 'ninguno';
    -- valores: 'cocina' | 'barra' | 'ninguno' (no se envía a imprimir)

-- ---------------------------------------------------------------------------
-- Comandas: cabecera. Una comanda es de UNA mesa; puede tener varias rondas
-- de ítems mientras está 'abierta'. NO lleva tipo_documento/id_documento
-- porque el split de cuenta puede generar varios documentos — eso vive en
-- comanda_grupos_cobro.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comandas (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_mesa             INTEGER NOT NULL REFERENCES mesas(id),
    id_usuario_mesero   INTEGER NOT NULL,
    id_caja_sesion      INTEGER,
    numero_comanda      VARCHAR(20) NOT NULL,        -- correlativo interno por empresa, no fiscal
    estado              VARCHAR(20) NOT NULL DEFAULT 'abierta', -- abierta|cobrando|cerrada|anulada
    id_cliente          INTEGER,
    comensales          INTEGER,
    observaciones       TEXT,
    fecha_apertura      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre        TIMESTAMP,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE,
    deleted_at          TIMESTAMP,
    deleted_by          INTEGER
);

-- ---------------------------------------------------------------------------
-- Comanda_detalle: líneas de la comanda. destino_impresion es un snapshot de
-- categorias.destino_impresion al momento de agregar el ítem (no se recalcula
-- si luego cambia la categoría). id_grupo_cobro se asigna al hacer split por
-- ítems (queda NULL si el split es por partes iguales o si aún no se cobra).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comanda_detalle (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_comanda          INTEGER NOT NULL REFERENCES comandas(id) ON DELETE CASCADE,
    id_producto         INTEGER NOT NULL,
    descripcion         VARCHAR(300) NOT NULL,
    cantidad            NUMERIC(18,6) NOT NULL DEFAULT 1,
    precio_unitario     NUMERIC(18,6) NOT NULL DEFAULT 0,
    descuento           NUMERIC(14,2) NOT NULL DEFAULT 0,
    subtotal            NUMERIC(14,2) NOT NULL DEFAULT 0,
    observacion_item    VARCHAR(300),                -- ej. "sin cebolla"
    destino_impresion   VARCHAR(20),                 -- cocina|barra|ninguno (snapshot)
    estado_linea        VARCHAR(20) NOT NULL DEFAULT 'pendiente', -- pendiente|enviado|preparando|listo|entregado|anulado
    id_grupo_cobro      INTEGER,                     -- FK lógica a comanda_grupos_cobro (se agrega abajo)
    enviado_at          TIMESTAMP,
    listo_at            TIMESTAMP,
    entregado_at        TIMESTAMP,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE
);

-- ---------------------------------------------------------------------------
-- Comanda_grupos_cobro: uno o varios "cobros" de una misma comanda (split).
-- Cada grupo, al cobrarse, genera su propio documento vía PosVentaService.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comanda_grupos_cobro (
    id                  SERIAL PRIMARY KEY,
    id_empresa          INTEGER NOT NULL,
    id_comanda          INTEGER NOT NULL REFERENCES comandas(id) ON DELETE CASCADE,
    numero_grupo        INTEGER NOT NULL DEFAULT 1,
    etiqueta            VARCHAR(100),
    tipo_split          VARCHAR(20) NOT NULL DEFAULT 'items', -- items|partes_iguales
    monto_asignado      NUMERIC(14,2),               -- solo para partes_iguales
    propina             NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado              VARCHAR(20) NOT NULL DEFAULT 'pendiente', -- pendiente|cobrado|anulado
    tipo_documento      VARCHAR(10),                 -- 'FACTURA' | 'RECIBO'
    id_documento        INTEGER,
    numero_documento    VARCHAR(20),
    forma_pago          VARCHAR(5),
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by          INTEGER,
    updated_by          INTEGER,
    eliminado           BOOLEAN DEFAULT FALSE
);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_comanda_detalle_grupo') THEN
        ALTER TABLE comanda_detalle
            ADD CONSTRAINT fk_comanda_detalle_grupo
            FOREIGN KEY (id_grupo_cobro) REFERENCES comanda_grupos_cobro(id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_comandas_empresa      ON comandas (id_empresa, eliminado);
CREATE INDEX IF NOT EXISTS idx_comandas_estado        ON comandas (id_empresa, estado, eliminado);
CREATE INDEX IF NOT EXISTS idx_comandas_mesa          ON comandas (id_mesa, estado);
CREATE INDEX IF NOT EXISTS idx_comanda_det_comanda    ON comanda_detalle (id_comanda);
CREATE INDEX IF NOT EXISTS idx_comanda_det_estado     ON comanda_detalle (id_empresa, estado_linea, destino_impresion);
CREATE INDEX IF NOT EXISTS idx_comanda_grupos_comanda ON comanda_grupos_cobro (id_comanda);

-- Unicidad del correlativo interno por empresa.
CREATE UNIQUE INDEX IF NOT EXISTS uq_comandas_numero ON comandas (id_empresa, numero_comanda) WHERE eliminado = false;
