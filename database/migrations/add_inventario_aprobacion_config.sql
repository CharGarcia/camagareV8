-- ============================================================
-- Configuración de aprobación de cargas de inventario (idempotente)
-- ============================================================
-- Vive en empresa_establecimiento (junto a metodo_costeo). Define si las
-- cargas masivas de inventario requieren aprobación, quiénes aprueban
-- (varios usuarios) y si se les notifica por correo.
--
--   inv_requiere_aprobacion   → si las cargas quedan pendientes de aprobación.
--   inv_usuarios_aprobadores  → arreglo JSON de id_usuario que pueden aprobar.
--   inv_notificar_correo      → enviar correo a los aprobadores.
-- ============================================================

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS inv_requiere_aprobacion BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS inv_usuarios_aprobadores JSONB NOT NULL DEFAULT '[]'::jsonb;

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS inv_notificar_correo BOOLEAN NOT NULL DEFAULT true;
