-- ============================================================================
-- Acceso a bodegas: pasar de "lista de permitidos" a "lista de denegados"
-- ============================================================================
-- ANTES (opt-in): un usuario nivel 1 solo veía las bodegas que tuviera
--   registradas en usuarios_bodegas. Sin registro = SIN acceso, por lo que un
--   usuario nuevo no veía ninguna bodega hasta configurarlo a mano.
--
-- AHORA (opt-out): TODOS los usuarios ven TODAS las bodegas de su empresa por
--   defecto. Solo se guarda un registro con denegado = true cuando se le QUITA
--   el acceso explícitamente desde Bodegas → pestaña "Accesos".
--
-- Semántica de usuarios_bodegas tras este cambio:
--   · Sin registro                      → CON acceso (por defecto)
--   · Registro con denegado = false     → CON acceso (además guarda es_default)
--   · Registro con denegado = true      → SIN acceso (revocado a propósito)
--
-- La columna es_default se mantiene igual (bodega predeterminada del usuario).
-- ============================================================================

BEGIN;

-- ── 1. Nueva columna ────────────────────────────────────────────────────────
ALTER TABLE usuarios_bodegas
    ADD COLUMN IF NOT EXISTS denegado BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN usuarios_bodegas.denegado IS
    'TRUE = acceso revocado explícitamente. Sin registro o FALSE = con acceso (por defecto).';

-- ── 2. Índice para el filtro del listado de bodegas permitidas ──────────────
CREATE INDEX IF NOT EXISTS idx_usuarios_bodegas_denegado
    ON usuarios_bodegas (id_empresa, id_usuario, id_bodega)
    WHERE denegado = TRUE;

-- ── 3. Migrar los datos existentes ──────────────────────────────────────────
-- Los registros previos representaban "acceso concedido": se dejan como
-- denegado = false (ya es el DEFAULT). No hay que crear filas de denegación:
-- al invertirse el modelo, todo lo que no tenga registro pasa a tener acceso,
-- que es justamente el comportamiento deseado.
UPDATE usuarios_bodegas
   SET denegado = FALSE
 WHERE denegado IS DISTINCT FROM FALSE;

-- Los registros que estaban soft-deleted (eliminado = true) eran accesos
-- revocados bajo el modelo anterior. Se reactivan como denegación explícita
-- para conservar la intención original de quien los quitó.
UPDATE usuarios_bodegas
   SET denegado   = TRUE,
       eliminado  = FALSE,
       deleted_at = NULL,
       deleted_by = NULL,
       updated_at = CURRENT_TIMESTAMP
 WHERE eliminado = TRUE;

COMMIT;

-- ── 4. VERIFICACIÓN ─────────────────────────────────────────────────────────
SELECT id_empresa,
       COUNT(*)                                  AS registros,
       COUNT(*) FILTER (WHERE denegado)          AS denegados,
       COUNT(*) FILTER (WHERE NOT denegado)      AS con_acceso_explicito
  FROM usuarios_bodegas
 GROUP BY id_empresa
 ORDER BY id_empresa;
