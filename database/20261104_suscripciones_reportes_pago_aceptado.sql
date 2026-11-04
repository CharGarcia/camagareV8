-- Pagos de suscripción reportados: nuevo estado 'aceptado'.
-- Antes el reporte solo podía quedar 'en_revision' o 'rechazado': si el pago era correcto,
-- la controladora registraba el cobro pero el aviso «Pagos reportados» de su navbar
-- seguía contándolo hasta 3 días, y la única forma de quitarlo era Rechazar (que avisa
-- al cliente de un rechazo que no existe).
--   'aceptado' con revisado_by     = la controladora pulsó «Pago verificado».
--   'aceptado' con revisado_by NULL = cierre automático: se registró el cobro y la fecha
--                                     límite del cliente avanzó.
-- Idempotente: se puede ejecutar más de una vez.

ALTER TABLE suscripciones_reportes_pago DROP CONSTRAINT IF EXISTS chk_susc_rep_pago_estado;
ALTER TABLE suscripciones_reportes_pago
    ADD CONSTRAINT chk_susc_rep_pago_estado CHECK (estado IN ('en_revision', 'rechazado', 'aceptado'));

-- Pendientes por controladora (contador del navbar y cierre automático).
CREATE INDEX IF NOT EXISTS idx_susc_rep_pago_ctrl_revision
    ON suscripciones_reportes_pago (id_empresa_controladora, created_at)
    WHERE eliminado = false AND estado = 'en_revision';

COMMENT ON COLUMN suscripciones_reportes_pago.estado IS 'en_revision | rechazado | aceptado (revisado_by NULL = cerrado solo al registrarse el cobro)';
