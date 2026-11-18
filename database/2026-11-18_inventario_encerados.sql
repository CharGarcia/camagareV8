-- ============================================================================
-- Inventario: «Encerar existencias» (dejar el stock en cero a una fecha)
-- ----------------------------------------------------------------------------
-- QUÉ HACE: crea la tabla `inventario_encerados`, la cabecera de cada encerado.
--
-- POR QUÉ: desde Movimientos de Inventario (/modulos/inventario) los usuarios de
-- nivel 2 y 3 pueden dejar en CERO, a una fecha de corte, las existencias de una,
-- varias o todas las bodegas. No se borra nada: por cada producto / bodega / lote /
-- NUP con saldo distinto de cero a esa fecha se registra en `inventario_kardex` un
-- movimiento que lo compensa (salida si el saldo es positivo, entrada si es
-- negativo), con referencia_tipo = 'encerado_inventario' y referencia_id = el id de
-- esta cabecera. No genera asiento contable.
--
-- La cabecera agrupa esos movimientos para poder REVERTIR el encerado completo
-- (se anulan todos sus movimientos y el stock vuelve a como estaba).
--
-- Sin esta tabla el botón «Encerar existencias» avisa que falta el SQL y no hace nada.
--
-- Ejecutar en pgAdmin ANTES de desplegar el código. Es idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS inventario_encerados (
    id                 SERIAL PRIMARY KEY,
    id_empresa         INTEGER      NOT NULL REFERENCES empresas(id),
    fecha_corte        DATE         NOT NULL,
    todas_bodegas      BOOLEAN      NOT NULL DEFAULT false,
    ids_bodegas        INTEGER[]    NOT NULL DEFAULT '{}',
    observacion        TEXT         NOT NULL,
    tipo_ambiente      VARCHAR(1)   NOT NULL DEFAULT '1',
    estado             VARCHAR(20)  NOT NULL DEFAULT 'aplicado',  -- aplicado | revertido
    total_movimientos  INTEGER      NOT NULL DEFAULT 0,
    total_productos    INTEGER      NOT NULL DEFAULT 0,
    unidades_salida    NUMERIC(18,4) NOT NULL DEFAULT 0,
    unidades_entrada   NUMERIC(18,4) NOT NULL DEFAULT 0,
    valor_salida       NUMERIC(18,2) NOT NULL DEFAULT 0,
    valor_entrada      NUMERIC(18,2) NOT NULL DEFAULT 0,
    revertido_at       TIMESTAMP    NULL,
    revertido_by       INTEGER      NULL,
    motivo_reversion   TEXT         NULL,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by         INTEGER      NULL,
    updated_by         INTEGER      NULL,
    eliminado          BOOLEAN      NOT NULL DEFAULT false,
    deleted_at         TIMESTAMP    NULL,
    deleted_by         INTEGER      NULL
);

CREATE INDEX IF NOT EXISTS idx_inventario_encerados_empresa
    ON inventario_encerados (id_empresa, eliminado, id DESC);

COMMENT ON TABLE inventario_encerados IS
    'Cabecera de cada «Encerar existencias» de /modulos/inventario. Sus movimientos viven en inventario_kardex con referencia_tipo = ''encerado_inventario'' y referencia_id = id. Revertir anula esos movimientos.';

-- Verificación: debe devolver una fila.
-- SELECT to_regclass('public.inventario_encerados');
