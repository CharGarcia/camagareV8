-- Empresas por REGALÍA: no se les cobra suscripción del sistema.
-- Se marcan en Configuración → Empresas del sistema → Editar → Cobro y vigencia.
-- Mientras la regalía esté vigente (sin fecha "hasta", o con fecha >= hoy) no reciben
-- avisos ni el modal de vencimiento, y la ficha de Empresa muestra «Plan sin costo».
-- Idempotente: se puede ejecutar más de una vez.

ALTER TABLE empresas ADD COLUMN IF NOT EXISTS sin_cobro_suscripcion BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS sin_cobro_motivo      VARCHAR(200);
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS sin_cobro_hasta       DATE;

COMMENT ON COLUMN empresas.sin_cobro_suscripcion IS 'Regalía: la empresa no paga suscripción del sistema';
COMMENT ON COLUMN empresas.sin_cobro_motivo      IS 'Motivo interno de la regalía (no lo ve el cliente)';
COMMENT ON COLUMN empresas.sin_cobro_hasta       IS 'Fin de la regalía (NULL = indefinida); al pasar, vuelven los avisos';
