-- =============================================================================
-- Actualiza estado_correo = 'enviado' en los DOCUMENTOS YA MIGRADOS que quedaron
-- AUTORIZADOS (regla: si el documento está autorizado, el correo se da por enviado).
--
-- Solo toca documentos migrados por la herramienta (via migracion_mysql_map) y solo
-- los que aún NO están en 'enviado'. NO cambia el estado del documento (eso depende
-- del estado_sri del sistema viejo y solo se corrige re-migrando).
--
-- Vocabulario de estado: masculino (autorizado) en facturas/NC/liquidaciones/guías;
-- femenino (autorizada) en retención de compra.
--
-- Ejecutar en PostgreSQL (pgAdmin). Idempotente: se puede correr varias veces.
-- =============================================================================

BEGIN;

-- 1) Facturas de venta
UPDATE ventas_cabecera v
   SET estado_correo = 'enviado', updated_at = now()
 WHERE v.estado = 'autorizado'
   AND v.estado_correo IS DISTINCT FROM 'enviado'
   AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.entidad = 'facturas' AND m.id_destino = v.id AND m.id_empresa = v.id_empresa);

-- 2) Notas de crédito
UPDATE notas_credito_cabecera n
   SET estado_correo = 'enviado', updated_at = now()
 WHERE n.estado = 'autorizado'
   AND n.estado_correo IS DISTINCT FROM 'enviado'
   AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.entidad = 'notas_credito' AND m.id_destino = n.id AND m.id_empresa = n.id_empresa);

-- 3) Liquidaciones de compra
UPDATE liquidaciones_cabecera l
   SET estado_correo = 'enviado', updated_at = now()
 WHERE l.estado = 'autorizado'
   AND l.estado_correo IS DISTINCT FROM 'enviado'
   AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.entidad = 'liquidaciones' AND m.id_destino = l.id AND m.id_empresa = l.id_empresa);

-- 4) Retención de compra (estado femenino: 'autorizada')
UPDATE retencion_compra_cabecera r
   SET estado_correo = 'enviado', updated_at = now()
 WHERE r.estado = 'autorizada'
   AND r.estado_correo IS DISTINCT FROM 'enviado'
   AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.entidad = 'retenciones_compra' AND m.id_destino = r.id AND m.id_empresa = r.id_empresa);

-- 5) Guías de remisión
UPDATE guias_remision_cabecera g
   SET estado_correo = 'enviado', updated_at = now()
 WHERE g.estado = 'autorizado'
   AND g.estado_correo IS DISTINCT FROM 'enviado'
   AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.entidad = 'guias' AND m.id_destino = g.id AND m.id_empresa = g.id_empresa);

COMMIT;

-- =============================================================================
-- Verificación (opcional): estado vs estado_correo de los migrados
-- =============================================================================
-- SELECT 'facturas'            AS doc, estado, estado_correo, COUNT(*) FROM ventas_cabecera            v WHERE EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.entidad='facturas'           AND m.id_destino=v.id AND m.id_empresa=v.id_empresa) GROUP BY estado, estado_correo
-- UNION ALL
-- SELECT 'notas_credito',       estado, estado_correo, COUNT(*) FROM notas_credito_cabecera     n WHERE EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.entidad='notas_credito'      AND m.id_destino=n.id AND m.id_empresa=n.id_empresa) GROUP BY estado, estado_correo
-- UNION ALL
-- SELECT 'liquidaciones',       estado, estado_correo, COUNT(*) FROM liquidaciones_cabecera     l WHERE EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.entidad='liquidaciones'      AND m.id_destino=l.id AND m.id_empresa=l.id_empresa) GROUP BY estado, estado_correo
-- UNION ALL
-- SELECT 'retenciones_compra',  estado, estado_correo, COUNT(*) FROM retencion_compra_cabecera  r WHERE EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.entidad='retenciones_compra' AND m.id_destino=r.id AND m.id_empresa=r.id_empresa) GROUP BY estado, estado_correo
-- UNION ALL
-- SELECT 'guias',               estado, estado_correo, COUNT(*) FROM guias_remision_cabecera    g WHERE EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.entidad='guias'              AND m.id_destino=g.id AND m.id_empresa=g.id_empresa) GROUP BY estado, estado_correo
-- ORDER BY 1, 2, 3;
