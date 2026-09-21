-- ============================================================================
-- Referencia de las formas de cobro/pago: hasta 255 caracteres
-- (modulos/ingresos, modulos/egresos)
-- ----------------------------------------------------------------------------
-- Contexto:
--   El campo "Referencia / Glosa General" del modal de Ingresos (y su gemelo
--   "Nº Referencia / Comprobante" en Egresos) se usa como glosa del cobro, no
--   solo como número de comprobante. La columna era varchar(100) y PostgreSQL
--   no trunca solo: al guardar un texto más largo abortaba el INSERT con
--     SQLSTATE[22001] value too long for type character varying(100)
--   y se perdía el ingreso/egreso entero (la transacción revierte cabecera y
--   detalles). Pasó en producción el 21-09-2026 al registrar un ingreso.
--
--   Se amplían las dos columnas que guardan esa referencia:
--     1) ingresos_pagos.referencia   varchar(100) -> 255
--     2) egresos_pagos.referencia    varchar(100) -> 255
--   Se incluye egresos_pagos aunque el error se vio en ingresos: es el mismo
--   campo del modal gemelo y, además, ahí lo llena también código nuestro
--   (ComprasController manda las observaciones del pago cuando no hay número
--   de operación), así que revienta sin que el usuario escriba de más.
--
-- Qué hace:
--   Solo AMPLÍA el largo máximo de 2 columnas de texto. NO modifica ni borra
--   datos. Ampliar un varchar no reescribe la tabla ni sus índices: es un
--   cambio de metadatos, instantáneo.
--   Es idempotente: si una columna ya admite 255 o más (o no tiene límite), la
--   salta; si una tabla no existe en esta base, también. Se puede reejecutar.
--
-- Reversible: sí, mientras ninguna referencia supere el largo anterior:
--   ALTER TABLE ingresos_pagos ALTER COLUMN referencia TYPE varchar(100);
--   ALTER TABLE egresos_pagos  ALTER COLUMN referencia TYPE varchar(100);
--   (si ya hay referencias más largas, PostgreSQL lo rechaza sin tocar nada).
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   Query Tool -> pegar TODO el archivo -> F5. Se ejecuta de una sola vez.
--   En la pestaña "Messages" sale una línea por columna con lo que se hizo.
--
--   Cada ALTER necesita un bloqueo exclusivo MUY breve de su tabla: espera a
--   que terminen las consultas que en ese instante leen `ingresos_pagos`. Por
--   eso se fija lock_timeout: si en 10 segundos no consigue el bloqueo, se
--   cancela TODO sin cambiar nada (error "canceling statement due to lock
--   timeout") y basta con volver a ejecutarlo, idealmente con poca actividad.
--
--   ORDEN DEL DESPLIEGUE: se puede ejecutar antes o después de subir el código,
--   sin romper nada. El código nuevo lee el largo real de la columna
--   (BaseRepository::caparTexto) y recorta a lo que la base admita: con el SQL
--   aún sin aplicar guardaría 100 caracteres en vez de 255, pero NO falla.
--   Lo natural es ejecutarlo primero, para que el tope de 255 del formulario y
--   el de la base coincidan desde el primer guardado.
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
                ('ingresos_pagos', 'referencia'),
                ('egresos_pagos',  'referencia')
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
        ELSIF v_largo IS NULL OR v_largo >= 255 THEN
            RAISE NOTICE '%.%: ya admite % caracteres; sin cambios.',
                r.tabla, r.columna, COALESCE(v_largo::text, 'cualquier cantidad de');
        ELSE
            EXECUTE format('ALTER TABLE public.%I ALTER COLUMN %I TYPE varchar(255)', r.tabla, r.columna);
            RAISE NOTICE '%.%: ampliada de % a 255 caracteres.', r.tabla, r.columna, v_largo;
        END IF;
    END LOOP;
END $$;

RESET lock_timeout;

-- ----------------------------------------------------------------------------
-- Comprobación (seleccionar y ejecutar aparte): deben salir 2 filas, ambas con
-- largo_maximo = 255.
-- ----------------------------------------------------------------------------
-- SELECT table_name, column_name, character_maximum_length AS largo_maximo
--   FROM information_schema.columns
--  WHERE table_schema = 'public'
--    AND (table_name::text, column_name::text) IN (
--            ('ingresos_pagos', 'referencia'),
--            ('egresos_pagos',  'referencia'))
--  ORDER BY table_name;
