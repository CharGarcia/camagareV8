-- ============================================================================
-- Guardado único por formulario (idempotencia de servidor) — tabla común
-- ============================================================================
-- QUÉ HACE    Crea guardados_formulario: cada formulario de documento NUEVO manda
--             una clave (token) y el Service, dentro de su transacción, registra
--             aquí qué documento creó con esa clave. Si la misma clave vuelve a
--             llegar (doble clic que se coló, o reintento después de "Error de
--             conexión" cuando el servidor sí había guardado) se devuelve el
--             documento ya creado en vez de crear otro.
--             La usan: Pedidos, Transferencias de inventario, Traspasos, Roles de
--             pago (generar), el cobro del POS, y las Facturas de venta, Proformas y
--             Pedidos creados desde la app móvil, y el cobro de facturas de la app. Pieza: App\Services\GuardadoUnicoService.
-- TOCA DATOS  No. Tabla nueva, vacía.
-- ORDEN       Aplicar ANTES de desplegar el código. Si el código llega primero no
--             se rompe: sin la tabla, cada módulo guarda como antes.
-- REVERSIBLE  Sí:  DROP TABLE IF EXISTS guardados_formulario;
-- USO         pgAdmin → Query Tool → F5. Idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS guardados_formulario (
    id           SERIAL PRIMARY KEY,
    id_empresa   INTEGER      NOT NULL,
    modulo       VARCHAR(60)  NOT NULL,   -- p. ej. 'pedidos', 'pos_cobro'
    token        VARCHAR(64)  NOT NULL,   -- clave que generó el formulario
    id_registro  INTEGER      NOT NULL,   -- documento creado con esa clave
    numero       VARCHAR(60),             -- número visible del documento (para el aviso)
    respuesta    TEXT,                    -- datos extra que el módulo necesita devolver (JSON)
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by   INTEGER,
    updated_at   TIMESTAMP,
    updated_by   INTEGER,
    eliminado    BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at   TIMESTAMP,
    deleted_by   INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_guardados_formulario_token
    ON guardados_formulario (id_empresa, modulo, token);

-- COMPROBACIÓN (debe devolver 2 filas: la tabla y el índice)
-- SELECT 'tabla' AS objeto, table_name AS nombre FROM information_schema.tables
--  WHERE table_name = 'guardados_formulario'
-- UNION ALL
-- SELECT 'indice', indexname FROM pg_indexes
--  WHERE tablename = 'guardados_formulario' AND indexname = 'uq_guardados_formulario_token';
