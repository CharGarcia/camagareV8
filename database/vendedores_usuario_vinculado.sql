-- ============================================================================
--  Vendedores: vínculo explícito con la cuenta de usuario del asesor
-- ----------------------------------------------------------------------------
--  Para qué: el Reporte de Ventas por Vendedor (modulos/reporte_ventas_vendedor)
--  limita a los usuarios de nivel 1 a las ventas de SU propio vendedor. Para
--  saber qué vendedor es cada usuario se cruzaba solo la cédula
--  (vendedores.identificacion = usuarios.cedula); esta columna permite además
--  fijar el vínculo a mano desde la ficha del vendedor, para los casos en que
--  la cédula no está cargada o no coincide.
--
--  NO confundir con la columna vendedores.id_usuario, que ya existía y guarda
--  el usuario que CREÓ el registro (lo escriben así tanto el alta manual como
--  el migrador de MySQL). Esa columna se deja como está.
--
--  Idempotente: se puede ejecutar varias veces sin efecto.
-- ============================================================================

ALTER TABLE vendedores ADD COLUMN IF NOT EXISTS id_usuario_vinculado INT NULL;

COMMENT ON COLUMN vendedores.id_usuario_vinculado IS
    'Cuenta de usuario que ES este vendedor (para "ver solo mis ventas"). NULL = se resuelve por cédula. No confundir con id_usuario, que guarda quién creó el registro.';

-- Un mismo usuario no puede ser dos vendedores dentro de la misma empresa
-- (sí puede serlo en empresas distintas).
CREATE UNIQUE INDEX IF NOT EXISTS ux_vendedores_usuario_vinculado
    ON vendedores (id_empresa, id_usuario_vinculado)
    WHERE id_usuario_vinculado IS NOT NULL AND eliminado = false;

-- Búsqueda del vendedor a partir del usuario logueado.
CREATE INDEX IF NOT EXISTS idx_vendedores_usuario_vinculado
    ON vendedores (id_usuario_vinculado)
    WHERE id_usuario_vinculado IS NOT NULL AND eliminado = false;

-- ----------------------------------------------------------------------------
--  OPCIONAL — sembrar el vínculo donde la cédula ya coincide, para no tener que
--  abrir esas fichas a mano. Deja intactos los vendedores ya vinculados y los
--  usuarios que calzan con más de un vendedor en la misma empresa.
-- ----------------------------------------------------------------------------
-- UPDATE vendedores v
--    SET id_usuario_vinculado = c.id_usuario
--   FROM (
--          SELECT v2.id AS id_vendedor, MIN(u.id) AS id_usuario, COUNT(*) AS cuantos
--            FROM vendedores v2
--            JOIN empresa_asignada ea ON ea.id_empresa = v2.id_empresa
--            JOIN usuarios u ON u.id = ea.id_usuario
--                           AND u.eliminado = false AND u.estado = 1
--                           AND regexp_replace(COALESCE(v2.identificacion,''), '[^0-9]', '', 'g') <> ''
--                           AND ( regexp_replace(COALESCE(v2.identificacion,''), '[^0-9]', '', 'g')
--                                 = regexp_replace(COALESCE(u.cedula,''), '[^0-9]', '', 'g')
--                              OR ( LENGTH(regexp_replace(COALESCE(v2.identificacion,''), '[^0-9]', '', 'g')) >= 10
--                               AND LENGTH(regexp_replace(COALESCE(u.cedula,''), '[^0-9]', '', 'g')) >= 10
--                               AND LEFT(regexp_replace(COALESCE(v2.identificacion,''), '[^0-9]', '', 'g'), 10)
--                                 = LEFT(regexp_replace(COALESCE(u.cedula,''), '[^0-9]', '', 'g'), 10) ) )
--           WHERE v2.eliminado = false AND v2.id_usuario_vinculado IS NULL
--           GROUP BY v2.id
--          HAVING COUNT(*) = 1
--        ) c
--  WHERE v.id = c.id_vendedor;
