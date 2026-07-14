-- Corrige el tipo_comprobante de los asientos MIGRADOS: del valor viejo en MAYÚSCULAS
-- ('DIARIO', 'COMPRAS_SERVICIOS'…) al vocabulario del sistema nuevo en minúscula ('diario',
-- 'compras'…), para que la lista lo muestre bien (la vista usa text-capitalize: 'diario' -> "Diario").
-- Ajustar el id de empresa. Idempotente (solo toca los que tienen mayúsculas).

UPDATE asientos_contables_cabecera
   SET tipo_comprobante = CASE upper(trim(tipo_comprobante))
        WHEN 'COMPRAS_SERVICIOS'   THEN 'compras'
        WHEN 'VENTAS'              THEN 'ventas'
        WHEN 'INGRESOS'            THEN 'ingresos'
        WHEN 'EGRESOS'             THEN 'egresos'
        WHEN 'RETENCIONES_COMPRAS' THEN 'retenciones_compras'
        WHEN 'RETENCIONES_VENTAS'  THEN 'retenciones_ventas'
        WHEN 'RECIBOS'             THEN 'ventas'
        WHEN 'NC_VENTAS'           THEN 'ventas'
        WHEN 'DIARIO'              THEN 'diario'
        WHEN 'BALANCE_INICIAL'     THEN 'apertura'
        WHEN 'ROL_PAGOS'           THEN 'nomina'
        ELSE lower(trim(tipo_comprobante))
       END,
       updated_at = now()
 WHERE modulo_origen = 'migracion'
   AND id_empresa = 1                                   -- <<< AJUSTAR tu empresa
   AND tipo_comprobante <> lower(trim(tipo_comprobante)); -- solo los que tienen mayúsculas
