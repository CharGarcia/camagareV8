-- El recargo por servicio ("propina" del 10%) se calcula hoy sobre TODO el
-- subtotal del documento (ver PosVentaService::cobrar()). Hay líneas que no
-- deberían sumar a esa base aunque vayan en la misma factura: envases,
-- empaques, domicilio como línea de producto, etc. — no son "servicio de
-- mesa/salón", son costos del producto en sí.
-- Este flag, marcado en la ficha del producto, saca esa línea de la base del
-- recargo (igual que ya se hace con las líneas de propina voluntaria).
ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS excluir_recargo_servicio BOOLEAN NOT NULL DEFAULT false;
