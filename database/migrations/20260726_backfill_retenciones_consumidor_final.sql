-- ============================================================================
-- BACKFILL de registros EXISTENTES (empresas ya creadas):
--   1) agente_retencion (retenciones): dejar válido según el SRI (solo dígitos,
--      máx 8). Los valores no numéricos ('NO', texto de resolución, etc.) pasan
--      a vacío; los numéricos se conservan (recortados a 8 dígitos por si acaso).
--   2) email del CONSUMIDOR FINAL: rellenar con el correo de su empresa cuando
--      esté vacío y la empresa tenga correo. NO sobrescribe correos ya puestos.
--
-- Seguro e idempotente: reejecutable sin efectos adversos.
-- ============================================================================

-- 1) RETENCIONES — normalizar empresas.agente_retencion al formato del SRI.
UPDATE empresas
   SET agente_retencion = LEFT(regexp_replace(COALESCE(agente_retencion, ''), '\D', '', 'g'), 8)
 WHERE COALESCE(agente_retencion, '') <> LEFT(regexp_replace(COALESCE(agente_retencion, ''), '\D', '', 'g'), 8);

-- 2) CONSUMIDOR FINAL — email = correo de la empresa (solo si está vacío).
UPDATE clientes c
   SET email = e.mail
  FROM empresas e
 WHERE c.id_empresa = e.id
   AND c.tipo_id = '07'
   AND c.identificacion = '9999999999999'
   AND c.eliminado = false
   AND COALESCE(c.email, '') = ''
   AND COALESCE(e.mail, '') <> '';
