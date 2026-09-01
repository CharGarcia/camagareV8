-- ============================================================================
-- Novedades: flag de conciliación por rol migrado (Pieza B)
-- ----------------------------------------------------------------------------
-- Al migrar Roles de Pago / Quincenas del sistema anterior, las novedades cuyo
-- (empleado, mes, año, aplica_en) coincide con un rol migrado se marcan como
-- conciliadas → se muestran "Pagada" en el listado de Novedades. Replica el
-- criterio del sistema viejo: "pagada = estar en el rol de ese período" (el rol
-- viejo era agregado, sin línea por novedad, así que no hay enlace directo).
--
-- Correr ANTES de desplegar el código de la Pieza B (el listado de Novedades y
-- la migración de roles ya referencian la columna).
-- ============================================================================

ALTER TABLE novedades
    ADD COLUMN IF NOT EXISTS conciliada_migrada BOOLEAN NOT NULL DEFAULT false;
