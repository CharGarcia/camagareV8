-- ============================================================
-- Asientos migrados de retenciones de venta › completar Tercero y Documento en sus líneas
--
-- Qué hace: en las líneas de asientos migrados (modulo_origen = 'migracion') cuyo asiento
-- tiene enlazada una retención de venta (retencion_venta_cabecera.id_asiento_contable),
-- copia el cliente y el número de la retención a la línea cuando están vacíos.
-- Es el mismo relleno de database/migrations/20260725_backfill_asientos_detalle_tercero.sql,
-- pero solo para retenciones de venta y sin SQL dinámico (no depende de otras tablas).
--
-- Toca datos: sí, asientos_contables_detalle (tipo_entidad, id_entidad, documento_referencia).
-- No destructivo: solo escribe donde el valor está en NULL/vacío; idempotente.
-- Reversible: no hay valor anterior que restaurar (eran NULL); para deshacer, volver a NULL
-- esas columnas en las líneas listadas por la consulta de comprobación.
-- Caso origen: RETVEN53924 (asiento 206794), empresa RUC 1793196214001.
-- ============================================================

BEGIN;

UPDATE asientos_contables_detalle ad
   SET tipo_entidad         = COALESCE(ad.tipo_entidad, 'cliente'),
       id_entidad           = COALESCE(ad.id_entidad, t.id_cliente),
       documento_referencia = COALESCE(NULLIF(ad.documento_referencia, ''),
                                       NULLIF(concat_ws('-', t.establecimiento, t.punto_emision, t.secuencial), '')),
       updated_at           = CURRENT_TIMESTAMP
  FROM asientos_contables_cabecera ac
  JOIN retencion_venta_cabecera t ON t.id_asiento_contable = ac.id
                                 AND t.id_empresa = ac.id_empresa
                                 AND t.eliminado = false
 WHERE ad.id_asiento = ac.id
   AND ac.modulo_origen = 'migracion'
   AND ac.eliminado = false
   AND ad.eliminado = false
   AND (ad.tipo_entidad IS NULL OR ad.id_entidad IS NULL OR NULLIF(ad.documento_referencia, '') IS NULL);

COMMIT;

-- Comprobación (RETVEN53924): debe salir cliente | 62314 | 046-001-000013552
-- SELECT ad.tipo_entidad, ad.id_entidad, ad.documento_referencia
--   FROM asientos_contables_detalle ad
--   JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
--  WHERE ad.id_asiento = 206794 AND pc.codigo = '1.1.2.04.003' AND ad.eliminado = false;
