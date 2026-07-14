-- Índice único parcial para el código de producto (por empresa, solo no eliminados).
-- Evita que el generador de consecutivos (getSiguienteCodigo) o una carga manual
-- creen dos productos activos con el mismo código dentro de una misma empresa.
--
-- Se usa lower(codigo) porque los códigos existentes mezclan mayúsculas y minúsculas
-- (p. ej. 'li01', 'mar01', 'ca05222') y 'P001' / 'p001' deben considerarse el mismo código.
--
-- IMPORTANTE: ejecutar ANTES el paso 1 (chequeo). Si devuelve filas, resolver esos
-- duplicados primero; de lo contrario el CREATE INDEX del paso 2 falla.

-- ─────────────────────────────────────────────────────────────────────────────
-- PASO 1 — CHEQUEO: ¿existen códigos duplicados activos? (debe devolver 0 filas)
-- ─────────────────────────────────────────────────────────────────────────────
SELECT id_empresa,
       lower(codigo) AS codigo_normalizado,
       count(*)      AS veces,
       string_agg(id::text, ', ' ORDER BY id) AS ids
FROM productos
WHERE eliminado = false
GROUP BY id_empresa, lower(codigo)
HAVING count(*) > 1
ORDER BY id_empresa, codigo_normalizado;

-- ─────────────────────────────────────────────────────────────────────────────
-- PASO 2 — Crear el índice único (solo si el paso 1 devolvió 0 filas)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE UNIQUE INDEX IF NOT EXISTS productos_codigo_unico_idx
    ON productos (id_empresa, lower(codigo))
    WHERE eliminado = false;

-- ─────────────────────────────────────────────────────────────────────────────
-- PASO 3 — Índice de apoyo para el generador de consecutivos.
-- getSiguienteCodigo busca el máximo de los códigos con formato P<n> / S<n>.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS productos_codigo_empresa_idx
    ON productos (id_empresa, codigo);
