-- ============================================================================
-- Nombre del producto: hasta 300 caracteres (modulos/productos)
-- ----------------------------------------------------------------------------
-- Contexto:
--   El SRI admite hasta 300 caracteres en la descripción de cada ítem del
--   comprobante (Ficha Técnica offline v2.34, campo <descripcion>; mismo límite
--   en los XSD de factura, nota de crédito, guía y liquidación). El nombre del
--   producto es lo que viaja en esa etiqueta, así que el sistema pasa a aceptar
--   nombres de hasta 300 caracteres (antes: 200 en pantalla y varchar(255) en
--   la base).
--
--   Se amplía la columna del catálogo y las dos columnas que guardan una COPIA
--   del nombre del producto (sin esto, usar un producto de nombre largo en esos
--   módulos fallaría al guardar con "value too long"):
--     1) productos.nombre                                varchar(255) -> 300
--     2) saldos_iniciales_consignaciones.producto_nombre  varchar(255) -> 300
--     3) firmas_electronicas.nombre_producto             varchar(200) -> 300
--   Las tablas de detalle de los documentos (ventas_detalle,
--   notas_credito_detalle, guias_remision_detalle, recibos_venta_detalle,
--   proformas_detalle, compras_detalle, ...) ya admiten 300 o más.
--
-- Qué hace:
--   Solo AMPLÍA el largo máximo de 3 columnas de texto. NO modifica ni borra
--   datos. Ampliar un varchar no reescribe la tabla ni sus índices: es un cambio
--   de metadatos, instantáneo.
--   Es idempotente: si una columna ya admite 300 o más (o no tiene límite), la
--   salta; si una tabla no existe en esta base, también. Se puede reejecutar.
--
-- Reversible: sí, mientras ningún nombre supere el largo anterior, p. ej.:
--   ALTER TABLE productos ALTER COLUMN nombre TYPE varchar(255);
--   (si ya hay nombres más largos, PostgreSQL lo rechaza sin tocar nada).
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   Query Tool -> pegar TODO el archivo -> F5. Se ejecuta de una sola vez.
--   En la pestaña "Messages" sale una línea por columna con lo que se hizo.
--
--   Cada ALTER necesita un bloqueo exclusivo MUY breve de su tabla: espera a que
--   terminen las consultas que en ese instante están leyendo `productos`. Por
--   eso se fija lock_timeout: si en 10 segundos no consigue el bloqueo, se
--   cancela TODO sin cambiar nada (error "canceling statement due to lock
--   timeout") y basta con volver a ejecutarlo, idealmente con poca actividad.
--   Así nunca deja a los usuarios esperando detrás del ALTER.
--
--   Si PostgreSQL responde que la columna la usa una vista ("cannot alter type
--   of a column used by a view or rule"), tampoco se aplica nada: hay una vista
--   en esta base que no existe en desarrollo; envíe el mensaje completo.
--
--   ORDEN DEL DESPLIEGUE: ejecutar este SQL ANTES de subir el código. Con el
--   código nuevo y la base sin ampliar, guardar un nombre de 256 a 300
--   caracteres fallaría.
-- ============================================================================

SET lock_timeout = '10s';

DO $$
DECLARE
    r       record;
    v_largo integer;
BEGIN
    FOR r IN
        SELECT *
          FROM (VALUES
                ('productos',                       'nombre'),
                ('saldos_iniciales_consignaciones', 'producto_nombre'),
                ('firmas_electronicas',             'nombre_producto')
               ) AS t(tabla, columna)
    LOOP
        SELECT c.character_maximum_length
          INTO v_largo
          FROM information_schema.columns c
         WHERE c.table_schema = 'public'
           AND c.table_name   = r.tabla
           AND c.column_name  = r.columna
           AND c.data_type    = 'character varying';

        IF NOT FOUND THEN
            RAISE NOTICE '%.%: no existe en esta base o no es varchar; se omite.', r.tabla, r.columna;
        ELSIF v_largo IS NULL OR v_largo >= 300 THEN
            RAISE NOTICE '%.%: ya admite % caracteres; sin cambios.',
                r.tabla, r.columna, COALESCE(v_largo::text, 'cualquier cantidad de');
        ELSE
            EXECUTE format('ALTER TABLE public.%I ALTER COLUMN %I TYPE varchar(300)', r.tabla, r.columna);
            RAISE NOTICE '%.%: ampliada de % a 300 caracteres.', r.tabla, r.columna, v_largo;
        END IF;
    END LOOP;
END $$;

RESET lock_timeout;

-- ----------------------------------------------------------------------------
-- Comprobación (seleccionar y ejecutar aparte): deben salir 3 filas, todas con
-- largo_maximo = 300 (menos filas si alguna tabla no existe en esta base).
-- ----------------------------------------------------------------------------
-- SELECT table_name, column_name, character_maximum_length AS largo_maximo
--   FROM information_schema.columns
--  WHERE table_schema = 'public'
--    AND (table_name::text, column_name::text) IN (
--            ('productos',                       'nombre'),
--            ('saldos_iniciales_consignaciones', 'producto_nombre'),
--            ('firmas_electronicas',             'nombre_producto'))
--  ORDER BY table_name;
