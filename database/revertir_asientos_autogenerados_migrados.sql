-- =============================================================================
-- Revierte (soft-delete) los asientos AUTOGENERADOS por el sincronizador sobre documentos que la
-- MIGRACIÓN INSERTÓ. Los busca por asiento.id_referencia_origen (no por el enlace del documento),
-- así también atrapa los que quedaron HUÉRFANOS tras re-importar la contabilidad (cuando el enlace
-- id_asiento_contable se movió hacia el asiento 'migracion').
-- NO toca los 'migracion' ni los de documentos VINCULADOS (nativos). Ajustar v_emp. Idempotente.
-- Viene en dry-run (ROLLBACK); revisa los NOTICE y luego cambia a COMMIT.
-- =============================================================================
BEGIN;
DO $$
DECLARE
  v_emp  INT := 8;   -- tu empresa (producción)
  v_user INT := 2;   -- tu usuario (producción)
  v_asi  INT[];
  v_docs TEXT[][] := ARRAY[
    ARRAY['compras_cabecera','id_asiento_contable'], ARRAY['ventas_cabecera','id_asiento_contable'],
    ARRAY['ingresos_cabecera','id_asiento_contable'], ARRAY['egresos_cabecera','id_asiento_contable'],
    ARRAY['retencion_venta_cabecera','id_asiento_contable'], ARRAY['retencion_compra_cabecera','id_asiento_contable'],
    ARRAY['notas_credito_cabecera','id_asiento_contable'], ARRAY['recibos_venta_cabecera','id_asiento_contable'],
    ARRAY['liquidaciones_cabecera','id_asiento_contable'], ARRAY['consignaciones_ventas','id_asiento_contable'],
    ARRAY['cambios_producto_cv','id_asiento_contable'], ARRAY['retornos_cv','id_asiento_contable'],
    ARRAY['rol_cabecera','id_asiento'], ARRAY['auditoria_contable_incidencias','id_asiento']
  ];
  dd TEXT[]; v_cnt INT;
BEGIN
  WITH mo(modulo_origen, entidad) AS (VALUES
    ('factura_venta','facturas'),('compra','compras'),('ingreso','ingresos'),('egreso','egresos'),
    ('nota_credito','notas_credito'),('retencion_venta','retenciones_venta'),
    ('retencion_compra','retenciones_compra'),('recibo_venta','recibos'),
    ('liquidacion_compra','liquidaciones'),('consignacion_venta','consignaciones')
  )
  SELECT array_agg(a.id) INTO v_asi
  FROM asientos_contables_cabecera a
  JOIN mo ON mo.modulo_origen = a.modulo_origen
  JOIN migracion_mysql_map m ON m.id_empresa=a.id_empresa AND m.entidad=mo.entidad
       AND m.id_destino=a.id_referencia_origen AND m.vinculado IS NOT TRUE
  WHERE a.id_empresa=v_emp AND a.eliminado=false AND a.modulo_origen<>'migracion';

  IF v_asi IS NULL THEN RAISE NOTICE 'Nada que revertir.'; RETURN; END IF;

  UPDATE asientos_contables_detalle SET eliminado=true, deleted_at=now(), deleted_by=v_user
   WHERE id_asiento=ANY(v_asi) AND eliminado=false;
  UPDATE asientos_contables_cabecera SET eliminado=true, deleted_at=now(), deleted_by=v_user
   WHERE id=ANY(v_asi) AND modulo_origen<>'migracion' AND eliminado=false;

  -- limpiar cualquier enlace de documento que todavía apunte a estos asientos
  FOREACH dd SLICE 1 IN ARRAY v_docs LOOP
    EXECUTE format('UPDATE %I SET %I = NULL WHERE %I = ANY($1)', dd[1], dd[2], dd[2]) USING v_asi;
  END LOOP;

  RAISE NOTICE 'Asientos autogenerados revertidos: %.', array_length(v_asi,1);
END $$;
ROLLBACK;   -- cambia por COMMIT cuando el NOTICE se vea bien
