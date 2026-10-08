-- ============================================================================
-- Anexo RDEP: control del envío del Formulario 107 por correo al trabajador.
-- Guarda la fecha y los destinatarios del último envío de cada fila, para ver en
-- la grilla a quién ya se le mandó. El historial completo queda en log_sistema
-- (acción ENVIAR_107). Requiere database/migrations/20261008_create_anexo_rdep.sql.
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

ALTER TABLE anexo_rdep_detalle
    ADD COLUMN IF NOT EXISTS f107_enviado_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS f107_enviado_a  VARCHAR(300);

COMMENT ON COLUMN anexo_rdep_detalle.f107_enviado_at IS 'Fecha y hora del último envío del Formulario 107 por correo.';
COMMENT ON COLUMN anexo_rdep_detalle.f107_enviado_a  IS 'Correo(s) a los que se envió el Formulario 107 la última vez.';

-- Verificación: deben salir 2 filas
SELECT column_name, data_type
FROM information_schema.columns
WHERE table_name = 'anexo_rdep_detalle' AND column_name IN ('f107_enviado_at', 'f107_enviado_a')
ORDER BY column_name;
