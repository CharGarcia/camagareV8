-- =====================================================================================
-- Bitácora: evento CREAR de los documentos cargados por XML / descarga del SRI (históricos)
-- =====================================================================================
-- QUÉ HACE
--   Los documentos que entraron por un XML o por la descarga automática del SRI no
--   dejaban evento "Crear" en log_sistema (se corrigió en el código el 09-11-2026). Este
--   script crea ese evento para los que ya estaban cargados, con la fecha y el usuario de
--   carga que guarda el propio documento (created_at / created_by).
--
-- TABLAS
--   1. compras_cabecera        (facturas, liquidaciones, NC y ND recibidas)
--   2. ventas_cabecera         (facturas emitidas)
--   3. liquidaciones_cabecera  (liquidaciones emitidas)
--   4. notas_credito_cabecera  (NC emitidas)
--   5. nota_debito_cabecera    (ND emitidas)
--   Las retenciones (compra y venta) NO se incluyen: siempre registraron su evento Crear.
--
-- A QUÉ DOCUMENTOS APLICA
--   - Compras: tipo_registro = 'electronico' y con el XML guardado (detalle_xml). Las
--     migradas del sistema anterior no guardan XML y quedan fuera.
--   - Las otras cuatro tablas no tienen tipo_registro: se toman los documentos CON XML
--     guardado y SIN evento Crear. Los que se emiten desde el sistema registran su Crear
--     al guardarse, así que los que quedan son los que entraron por XML. Es una
--     deducción, y por eso el evento lo dice en su nota.
--   - En todas: solo los que todavía NO tienen evento Crear. Incluye los eliminados.
--
-- TOCA DATOS: sí, solo INSERTA filas en log_sistema. No modifica ningún documento.
-- IDEMPOTENTE: sí. Volver a ejecutarlo no duplica eventos.
-- CÓMO SE RECONOCEN: user_agent = 'Reconstruido por SQL 2026-11-09' y, en el detalle del
--   evento, "reconstruido": true.
-- REVERSIBLE: sí →
--   DELETE FROM log_sistema WHERE user_agent = 'Reconstruido por SQL 2026-11-09';
--
-- Pegar completo en el Query Tool de pgAdmin y ejecutar (F5).
-- =====================================================================================

-- 1. Compras -------------------------------------------------------------------------
INSERT INTO log_sistema
    (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
     datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT COALESCE(c.created_by, c.id_usuario, 0), c.id_empresa, 'CREAR', 'compras_cabecera', c.id, NULL,
       jsonb_build_object(
           'id_compra',    c.id,
           'total',        c.importe_total,
           'origen',       'Documento electrónico (XML / descarga SRI)',
           'clave_acceso', c.numero_autorizacion,
           'reconstruido', true,
           'nota',         'Evento creado después, con la fecha de carga guardada en el documento'),
       NULL, 'Reconstruido por SQL 2026-11-09', c.created_at
  FROM compras_cabecera c
 WHERE c.tipo_registro = 'electronico'
   AND c.detalle_xml IS NOT NULL AND length(c.detalle_xml::text) > 0
   AND c.created_at IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM log_sistema l
                    WHERE l.tabla_afectada = 'compras_cabecera'
                      AND l.id_registro = c.id AND LOWER(l.accion) = 'crear');

-- 2. Facturas de venta ---------------------------------------------------------------
INSERT INTO log_sistema
    (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
     datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT COALESCE(d.created_by, d.id_usuario, 0), d.id_empresa, 'CREAR', 'ventas_cabecera', d.id, NULL,
       jsonb_build_object(
           'id_venta',     d.id,
           'total',        d.importe_total,
           'origen',       'Documento electrónico (XML / descarga SRI)',
           'clave_acceso', d.clave_acceso,
           'reconstruido', true,
           'nota',         'Evento creado después, con la fecha de carga guardada en el documento. Origen deducido: tenía XML y no tenía evento Crear'),
       NULL, 'Reconstruido por SQL 2026-11-09', d.created_at
  FROM ventas_cabecera d
 WHERE d.detalle_xml IS NOT NULL AND length(d.detalle_xml::text) > 0
   AND d.created_at IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM log_sistema l
                    WHERE l.tabla_afectada = 'ventas_cabecera'
                      AND l.id_registro = d.id AND LOWER(l.accion) = 'crear');

-- 3. Liquidaciones de compra ---------------------------------------------------------
INSERT INTO log_sistema
    (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
     datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT COALESCE(d.created_by, d.id_usuario, 0), d.id_empresa, 'CREAR', 'liquidaciones_cabecera', d.id, NULL,
       jsonb_build_object(
           'id_liquidacion', d.id,
           'total',          d.importe_total,
           'origen',         'Documento electrónico (XML / descarga SRI)',
           'clave_acceso',   d.clave_acceso,
           'reconstruido',   true,
           'nota',           'Evento creado después, con la fecha de carga guardada en el documento. Origen deducido: tenía XML y no tenía evento Crear'),
       NULL, 'Reconstruido por SQL 2026-11-09', d.created_at
  FROM liquidaciones_cabecera d
 WHERE d.detalle_xml IS NOT NULL AND length(d.detalle_xml::text) > 0
   AND d.created_at IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM log_sistema l
                    WHERE l.tabla_afectada = 'liquidaciones_cabecera'
                      AND l.id_registro = d.id AND LOWER(l.accion) = 'crear');

-- 4. Notas de crédito ----------------------------------------------------------------
INSERT INTO log_sistema
    (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
     datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT COALESCE(d.created_by, d.id_usuario, 0), d.id_empresa, 'CREAR', 'notas_credito_cabecera', d.id, NULL,
       jsonb_build_object(
           'id_nota_credito', d.id,
           'total',           d.importe_total,
           'origen',          'Documento electrónico (XML / descarga SRI)',
           'clave_acceso',    d.clave_acceso,
           'reconstruido',    true,
           'nota',            'Evento creado después, con la fecha de carga guardada en el documento. Origen deducido: tenía XML y no tenía evento Crear'),
       NULL, 'Reconstruido por SQL 2026-11-09', d.created_at
  FROM notas_credito_cabecera d
 WHERE d.detalle_xml IS NOT NULL AND length(d.detalle_xml::text) > 0
   AND d.created_at IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM log_sistema l
                    WHERE l.tabla_afectada = 'notas_credito_cabecera'
                      AND l.id_registro = d.id AND LOWER(l.accion) = 'crear');

-- 5. Notas de débito -----------------------------------------------------------------
INSERT INTO log_sistema
    (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
     datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT COALESCE(d.created_by, d.id_usuario, 0), d.id_empresa, 'CREAR', 'nota_debito_cabecera', d.id, NULL,
       jsonb_build_object(
           'id_nota_debito', d.id,
           'total',          d.importe_total,
           'origen',         'Documento electrónico (XML / descarga SRI)',
           'clave_acceso',   d.clave_acceso,
           'reconstruido',   true,
           'nota',           'Evento creado después, con la fecha de carga guardada en el documento. Origen deducido: tenía XML y no tenía evento Crear'),
       NULL, 'Reconstruido por SQL 2026-11-09', d.created_at
  FROM nota_debito_cabecera d
 WHERE d.detalle_xml IS NOT NULL AND length(d.detalle_xml::text) > 0
   AND d.created_at IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM log_sistema l
                    WHERE l.tabla_afectada = 'nota_debito_cabecera'
                      AND l.id_registro = d.id AND LOWER(l.accion) = 'crear');

-- -------------------------------------------------------------------------------------
-- COMPROBACIÓN (ejecutar aparte, después): eventos creados por tabla y empresa.
-- -------------------------------------------------------------------------------------
-- SELECT tabla_afectada, id_empresa, COUNT(*) AS eventos,
--        MIN(created_at) AS desde, MAX(created_at) AS hasta
--   FROM log_sistema
--  WHERE user_agent = 'Reconstruido por SQL 2026-11-09'
--  GROUP BY tabla_afectada, id_empresa
--  ORDER BY tabla_afectada, id_empresa;
