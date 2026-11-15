-- ============================================================================
-- Documentos para no descargar (Descargas SRI): alcance del bloqueo.
--
-- Hasta ahora, una clave en documentos_ignorados_sri bloqueaba la descarga en
-- TODOS los establecimientos que comparten el RUC. Con solo_establecimiento = true
-- el bloqueo aplica únicamente a la empresa (establecimiento) que lo registró:
-- sirve para eliminar una compra en un establecimiento y registrarla en otro del
-- mismo RUC sin que la descarga automática la vuelva a traer al primero.
--
-- motivo: por qué se agregó ('ANULADO_SRI' | 'OTRO_ESTABLECIMIENTO' | 'MANUAL' | NULL = a mano).
--
-- Idempotente: se puede ejecutar más de una vez. Aplicar en producción ANTES del deploy.
-- ============================================================================

ALTER TABLE documentos_ignorados_sri
    ADD COLUMN IF NOT EXISTS solo_establecimiento boolean NOT NULL DEFAULT false;

ALTER TABLE documentos_ignorados_sri
    ADD COLUMN IF NOT EXISTS motivo varchar(30);

COMMENT ON COLUMN documentos_ignorados_sri.solo_establecimiento IS
    'true = el bloqueo aplica solo a id_empresa; false = a todos los establecimientos del mismo RUC.';
COMMENT ON COLUMN documentos_ignorados_sri.motivo IS
    'ANULADO_SRI | OTRO_ESTABLECIMIENTO (desde Compras) | MANUAL o NULL (agregado a mano en Descargas SRI).';
