-- Bloqueo de edición concurrente entre módulos relacionados (p. ej. Pedidos <-> Consignaciones
-- de Venta): mientras un usuario tiene un registro abierto para editar/usar, otro usuario que
-- intente editar/usar el MISMO registro desde cualquier módulo ve un aviso y queda en solo lectura.
--
-- Es estado técnico efímero (como una sesión de trabajo), por eso NO lleva los campos de
-- auditoría obligatorios de la sección 5 (eliminado, deleted_at, created_by, etc.): se borra
-- físicamente al liberar el bloqueo o al expirar por inactividad (heartbeat vencido).

CREATE TABLE IF NOT EXISTS registros_en_uso (
    id BIGSERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    tabla VARCHAR(60) NOT NULL,
    id_registro INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    nombre_usuario VARCHAR(150) NOT NULL,
    modulo_contexto VARCHAR(60) NULL,
    fecha_inicio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_ultimo_ping TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_registros_en_uso UNIQUE (id_empresa, tabla, id_registro)
);

CREATE INDEX IF NOT EXISTS idx_registros_en_uso_ping ON registros_en_uso (fecha_ultimo_ping);
