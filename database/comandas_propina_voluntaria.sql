-- ============================================================================
-- Propina voluntaria en comandas (POS Restaurante).
--
-- El campo <propina> del comprobante electrónico es UNO SOLO y ya lo ocupa el
-- recargo por servicio (además topado al 10% del subtotal por la Ficha Técnica
-- del SRI). Para una propina voluntaria adicional no queda dónde ponerla, así
-- que se emite como una LÍNEA MÁS del detalle: un producto de tipo servicio,
-- con IVA 0%, cuyo precio es el monto que deja el cliente.
--
-- Esta columna guarda cuál es ese producto en cada establecimiento. Sin ella
-- configurada, el input de propina no aparece en la comanda.
--
-- El producto lo crea el usuario en Productos: tipo servicio (no inventariable),
-- precio 0 y tarifa de IVA 0%.
-- ============================================================================

BEGIN;

ALTER TABLE empresa_establecimiento
    ADD COLUMN IF NOT EXISTS id_producto_propina INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'empresa_establecimiento_id_producto_propina_fkey'
    ) THEN
        ALTER TABLE empresa_establecimiento
            ADD CONSTRAINT empresa_establecimiento_id_producto_propina_fkey
            FOREIGN KEY (id_producto_propina) REFERENCES productos(id);
    END IF;
END $$;

COMMENT ON COLUMN empresa_establecimiento.id_producto_propina IS
    'Producto (servicio, IVA 0%) con el que se emite la propina voluntaria de las comandas como línea del detalle. NULL = función desactivada.';

COMMIT;

-- Verificación:
-- SELECT id, nombre, id_producto_propina FROM empresa_establecimiento WHERE eliminado = false;
