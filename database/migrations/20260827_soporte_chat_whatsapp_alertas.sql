-- ============================================================================
--  Chat de Soporte — aviso por WhatsApp de las consultas sin atender
--  Fecha: 2026-08-27
-- ----------------------------------------------------------------------------
--  Ejecutar DESPUÉS de 20260728_create_soporte_chat.sql
--
--  Dos cambios:
--
--   1. El aviso de consultas sin atender pasa a ser prácticamente inmediato
--      (1 minuto). El cron corre cada minuto y el propio servicio evita
--      reenviar mientras la lista no cambie, así que bajar el umbral no
--      genera correos repetidos.
--
--   2. El mismo aviso puede salir además por WhatsApp. Se apoya en lo que ya
--      existe en el módulo de WhatsApp: las credenciales de Meta viven en
--      empresa_whatsapp_config y los números que reciben avisos, en
--      whatsapp_aviso_numeros — aquí solo se agrega un número explícito
--      opcional (mismo papel que correo_alertas) y, si se quiere, la plantilla
--      aprobada con la que iniciar la conversación.
-- ============================================================================

-- --- 1. Aviso casi inmediato --------------------------------------------------

ALTER TABLE soporte_config ALTER COLUMN minutos_alerta_sin_atender SET DEFAULT 1;

UPDATE soporte_config
   SET minutos_alerta_sin_atender = 1,
       updated_at                 = CURRENT_TIMESTAMP
 WHERE id = 1;


-- --- 2. Aviso por WhatsApp ----------------------------------------------------

-- Número que recibe el aviso. Vacío —lo normal— usa los números registrados en
-- whatsapp_aviso_numeros de la empresa que atiende, para no llevar dos listas.
ALTER TABLE soporte_config ADD COLUMN IF NOT EXISTS whatsapp_alertas VARCHAR(30);

-- Plantilla aprobada en Meta para el aviso. Vacío = mensaje de texto libre, que
-- Meta solo entrega dentro de la ventana de 24 h desde el último mensaje del
-- destinatario; con plantilla el aviso llega siempre.
--   {{1}} = empresa que pide soporte   {{2}} = usuario   {{3}} = asunto
ALTER TABLE soporte_config ADD COLUMN IF NOT EXISTS whatsapp_plantilla        VARCHAR(150);
ALTER TABLE soporte_config ADD COLUMN IF NOT EXISTS whatsapp_plantilla_idioma VARCHAR(10) DEFAULT 'es';

COMMENT ON COLUMN soporte_config.whatsapp_alertas
    IS 'Número que recibe el aviso de consultas sin atender. Vacío = los de whatsapp_aviso_numeros de la empresa que atiende.';
COMMENT ON COLUMN soporte_config.whatsapp_plantilla
    IS 'Plantilla aprobada en Meta para el aviso ({{1}} empresa, {{2}} usuario, {{3}} asunto). Vacío = texto libre (ventana de 24 h).';
