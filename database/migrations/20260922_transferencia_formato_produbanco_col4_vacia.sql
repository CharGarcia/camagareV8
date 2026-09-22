-- ============================================================================
-- Produbanco (Cash Management): columna 4 "NUMERO DE COMPROBANTE DE PAGO" vacía
-- ----------------------------------------------------------------------------
-- Qué hace : en el formato 'Produbanco (Cash Management)' cambia la columna
--            con orden 4 de "Número de egreso" a "Texto fijo" sin valor, para
--            que el archivo la deje en blanco como el archivo que el banco acepta.
--            No toca ninguna otra columna ni la configuración del formato
--            (tipo de archivo, delimitador, encabezado, prefijos).
-- Toca datos: sí, solo la fila de transferencia_formatos de Produbanco
--            (catálogo global, sin id_empresa).
-- Idempotente: sí; si la columna ya está vacía no cambia nada.
-- Reversible : sí; volver a poner origen_dato = 'numero_egreso' y
--            valor_fijo = null en el elemento con orden 4 (mismo UPDATE
--            invirtiendo los valores), o editarlo en /config/transferencia-formatos.
-- Aplica a  : archivos generados DESPUÉS de ejecutarlo; los ya generados no cambian.
-- ============================================================================

UPDATE transferencia_formatos tf
SET campos = (
        SELECT jsonb_agg(
                   CASE WHEN (c->>'orden')::int = 4
                        THEN c || '{"origen_dato":"texto_fijo","valor_fijo":""}'::jsonb
                        ELSE c
                   END
                   ORDER BY (c->>'orden')::int
               )
        FROM jsonb_array_elements(tf.campos) AS c
    ),
    updated_at = CURRENT_TIMESTAMP
WHERE tf.nombre = 'Produbanco (Cash Management)'
  AND tf.eliminado = false
  AND EXISTS (
        SELECT 1 FROM jsonb_array_elements(tf.campos) AS c
        WHERE (c->>'orden')::int = 4
          AND c->>'origen_dato' <> 'texto_fijo'
  );

-- Comprobación: debe salir 1 fila con origen_dato = texto_fijo y valor_fijo vacío.
-- SELECT tf.id, tf.nombre, c->>'etiqueta' AS etiqueta, c->>'origen_dato' AS origen_dato, c->>'valor_fijo' AS valor_fijo
-- FROM transferencia_formatos tf, jsonb_array_elements(tf.campos) AS c
-- WHERE tf.nombre = 'Produbanco (Cash Management)' AND tf.eliminado = false
--   AND (c->>'orden')::int = 4;
