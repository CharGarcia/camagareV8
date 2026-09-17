-- ============================================================================
-- Cargas de Inventario: anulación de cargas aprobadas (modulos/cargas-inventario)
-- ----------------------------------------------------------------------------
-- Contexto:
--   Una carga aprobada ya movió el stock y hasta ahora no había forma de
--   deshacerla. Desde esta versión se ANULA: se reversan sus movimientos del
--   kardex (si sus productos no se usaron después) y el documento queda en el
--   listado con estado "Anulada", quién la anuló, cuándo y el motivo. Para
--   corregir una carga aprobada se anula y se importa el archivo corregido
--   como carga nueva.
--
-- Qué hace:
--   1) Permite el estado 'anulada' en inventario_cargas.estado (reemplaza el
--      CHECK de estado por uno que lo incluye).
--   2) Agrega las columnas anulada_por, anulada_at y motivo_anulacion.
--
--   NO modifica ni borra datos. NO toca ninguna fila existente.
--
-- Orden de despliegue: ejecutar ESTE SQL ANTES de subir el código. Si el código
--   llega primero, el listado y el detalle siguen funcionando, pero el botón
--   Anular responde que falta aplicar este script (y no toca el stock).
--
-- Reversible: sí, mientras no haya cargas anuladas (ver bloque REVERTIR al final).
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   Query Tool → pegar TODO este archivo → ejecutar (F5), de una sola vez.
--   Es idempotente: se puede volver a ejecutar sin error.
--   Bloquea la tabla inventario_cargas solo un instante (es pequeña).
-- ============================================================================

-- 1) Estado 'anulada' ----------------------------------------------------------
--    El CHECK original se creó en línea (nombre automático
--    inventario_cargas_estado_check). Se busca por su definición para no depender
--    del nombre, y se vuelve a crear con el estado nuevo.
DO $$
DECLARE
    r record;
BEGIN
    FOR r IN
        SELECT conname
          FROM pg_constraint
         WHERE conrelid = 'public.inventario_cargas'::regclass
           AND contype = 'c'
           AND pg_get_constraintdef(oid) ILIKE '%estado%'
    LOOP
        EXECUTE format('ALTER TABLE public.inventario_cargas DROP CONSTRAINT %I', r.conname);
    END LOOP;
END $$;

ALTER TABLE public.inventario_cargas
    ADD CONSTRAINT inventario_cargas_estado_check
    CHECK (estado IN ('pendiente', 'aprobada', 'rechazada', 'anulada'));

-- 2) Datos de la anulación -----------------------------------------------------
ALTER TABLE public.inventario_cargas ADD COLUMN IF NOT EXISTS anulada_por      INTEGER;
ALTER TABLE public.inventario_cargas ADD COLUMN IF NOT EXISTS anulada_at       TIMESTAMP;
ALTER TABLE public.inventario_cargas ADD COLUMN IF NOT EXISTS motivo_anulacion TEXT;

COMMENT ON COLUMN public.inventario_cargas.anulada_por      IS 'Usuario que anuló la carga aprobada (reversó su stock)';
COMMENT ON COLUMN public.inventario_cargas.anulada_at       IS 'Fecha y hora de la anulación';
COMMENT ON COLUMN public.inventario_cargas.motivo_anulacion IS 'Motivo escrito al anular';


-- ============================================================================
-- COMPROBACIÓN (ejecutar aparte)
-- ============================================================================
-- Deben salir 3 filas:
-- SELECT column_name, data_type
--   FROM information_schema.columns
--  WHERE table_name = 'inventario_cargas'
--    AND column_name IN ('anulada_por', 'anulada_at', 'motivo_anulacion');
--
-- Debe salir 1 fila y su definición debe incluir 'anulada':
-- SELECT conname, pg_get_constraintdef(oid)
--   FROM pg_constraint
--  WHERE conrelid = 'public.inventario_cargas'::regclass AND contype = 'c'
--    AND pg_get_constraintdef(oid) ILIKE '%estado%';


-- ============================================================================
-- REVERTIR (solo si no hay cargas anuladas)
-- ============================================================================
-- Primero comprobar que da 0:
-- SELECT COUNT(*) FROM public.inventario_cargas WHERE estado = 'anulada';
--
-- ALTER TABLE public.inventario_cargas DROP CONSTRAINT IF EXISTS inventario_cargas_estado_check;
-- ALTER TABLE public.inventario_cargas
--     ADD CONSTRAINT inventario_cargas_estado_check
--     CHECK (estado IN ('pendiente', 'aprobada', 'rechazada'));
-- ALTER TABLE public.inventario_cargas
--     DROP COLUMN IF EXISTS motivo_anulacion,
--     DROP COLUMN IF EXISTS anulada_at,
--     DROP COLUMN IF EXISTS anulada_por;
