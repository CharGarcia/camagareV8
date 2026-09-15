-- =====================================================================================
-- Proformas: verificar que NO se repitan los números de secuencial.
--
-- Ejecutar en pgAdmin (Query Tool) sobre la base de PRODUCCIÓN, bloque por bloque.
-- Los bloques A, B, C y D son de SOLO LECTURA: no modifican nada.
-- El bloque E crea el índice único que impide el problema a nivel de base; ejecutarlo
-- solo después de que B y C no devuelvan ninguna fila.
--
-- Contexto: al crear una proforma el sistema ya toma un candado por punto de emisión
-- (pg_advisory_xact_lock dentro de SecuencialService::obtenerSiguienteSecuencial) y
-- comprueba el número antes de insertar, así que dos usuarios simultáneos no pueden
-- quedarse con el mismo. Lo que estos bloques verifican es lo que ese candado NO cubre:
-- números que ya venían repetidos de antes (migraciones, cargas manuales) y si la base
-- tiene puesto el cinturón de seguridad final.
-- =====================================================================================


-- ── A) ¿Existe el índice único que impide números repetidos? ─────────────────────────
-- Esperado: una fila con uq_proformas_numero. Si no devuelve nada, la base NO está
-- protegida y hay que ejecutar el bloque E.
SELECT indexname, indexdef
  FROM pg_indexes
 WHERE tablename = 'proformas_cabecera'
 ORDER BY indexname;


-- ── B) Números repetidos EXACTOS (mismo texto en la misma serie) ─────────────────────
-- Esperado: 0 filas.
SELECT c.id_empresa,
       c.id_establecimiento,
       c.id_punto_emision,
       c.establecimiento || '-' || c.punto_emision AS serie,
       c.secuencial,
       COUNT(*)                                    AS repeticiones,
       array_agg(c.id ORDER BY c.id)               AS ids,
       array_agg(c.fecha_emision ORDER BY c.id)    AS fechas,
       array_agg(c.estado ORDER BY c.id)           AS estados
  FROM proformas_cabecera c
 WHERE c.eliminado = false
 GROUP BY 1, 2, 3, 4, 5
HAVING COUNT(*) > 1
 ORDER BY 1, 2, 3, 5;


-- ── C) Números repetidos LÓGICOS (mismo número, distinto formato de texto) ───────────
-- '16' y '000000016' son el MISMO número, pero como la columna es de texto ni el índice
-- único ni la comprobación previa los veían iguales. Esperado: 0 filas.
SELECT c.id_empresa,
       c.id_establecimiento,
       c.id_punto_emision,
       CAST(TRIM(c.secuencial) AS BIGINT)            AS numero,
       COUNT(*)                                      AS repeticiones,
       array_agg(c.id ORDER BY c.id)                 AS ids,
       array_agg(c.secuencial ORDER BY c.id)         AS textos_guardados
  FROM proformas_cabecera c
 WHERE c.eliminado = false
   AND TRIM(c.secuencial) ~ '^[0-9]+$'
 GROUP BY 1, 2, 3, 4
HAVING COUNT(*) > 1
 ORDER BY 1, 2, 3, 4;


-- ── D) Proformas fuera del control de la numeración ──────────────────────────────────
-- Dos casos que dejan un número "invisible" para el sistema:
--   * id_punto_emision NULL  → el motor no cuenta ese número como usado, así que lo
--     volverá a entregar a la próxima proforma de esa serie; y el índice único tampoco
--     protege (en PostgreSQL un NULL nunca choca con otro).
--   * secuencial no numérico o fuera del formato de 9 dígitos → el motor lo ignora al
--     calcular el siguiente disponible.
-- Esperado: 0 filas. Si aparecen, asignarles su punto de emisión real (Reasignar
-- establecimiento) o normalizar el número antes de crear el índice del bloque E.
SELECT c.id,
       c.id_empresa,
       c.id_establecimiento,
       c.id_punto_emision,
       c.establecimiento || '-' || c.punto_emision AS serie,
       c.secuencial,
       LENGTH(TRIM(c.secuencial))                  AS largo,
       c.fecha_emision,
       c.estado,
       CASE WHEN c.id_punto_emision IS NULL             THEN 'sin punto de emisión'
            WHEN TRIM(c.secuencial) !~ '^[0-9]+$'       THEN 'secuencial no numérico'
            ELSE 'formato distinto de 9 dígitos'
       END                                         AS motivo
  FROM proformas_cabecera c
 WHERE c.eliminado = false
   AND (c.id_punto_emision IS NULL
        OR TRIM(c.secuencial) !~ '^[0-9]+$'
        OR LENGTH(TRIM(c.secuencial)) <> 9)
 ORDER BY c.id_empresa, c.id_establecimiento, c.secuencial;


-- ── E) Cinturón de seguridad: índice único de la serie ───────────────────────────────
-- Ejecutar SOLO si el bloque A no lo listó y los bloques B y C no devolvieron filas.
-- Si hay duplicados, PostgreSQL rechaza la creación indicando la clave repetida.
-- Es el mismo índice que define 20260619_create_proformas.sql (mismo nombre y misma
-- definición, para no crear un duplicado con otro nombre).
CREATE UNIQUE INDEX IF NOT EXISTS uq_proformas_numero
    ON proformas_cabecera (id_empresa, id_establecimiento, id_punto_emision, secuencial)
 WHERE eliminado = false;
