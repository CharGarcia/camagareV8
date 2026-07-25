BEGIN;

CREATE TEMP TABLE pares AS
SELECT c.id AS id_compra, ac_nat.id AS id_nativo, ac_mig.id AS id_migracion
FROM compras_cabecera c
JOIN migracion_mysql_map mm ON mm.id_empresa = c.id_empresa AND mm.entidad = 'compras'
     AND mm.id_destino = c.id AND mm.vinculado = true
JOIN asientos_contables_cabecera ac_nat
     ON ac_nat.id_empresa = c.id_empresa AND ac_nat.modulo_origen = 'compra'
    AND ac_nat.id_referencia_origen = c.id AND ac_nat.eliminado = false
JOIN asientos_contables_cabecera ac_mig
     ON ac_mig.id_empresa = c.id_empresa AND ac_mig.modulo_origen = 'migracion'
    AND ac_mig.tipo_comprobante = 'compras' AND ac_mig.id_referencia_origen = c.id
    AND ac_mig.eliminado = false
WHERE c.id_empresa = 8;

-- 1) Vista previa (debería dar ~39 filas)
SELECT * FROM pares ORDER BY id_compra;

-- 2) Aplicar el cambio
UPDATE asientos_contables_cabecera SET estado = 'anulado', updated_at = now(), updated_by = 2
WHERE id IN (SELECT id_nativo FROM pares);

UPDATE asientos_contables_cabecera SET estado = 'contabilizado', updated_at = now(), updated_by = 2
WHERE id IN (SELECT id_migracion FROM pares);

UPDATE compras_cabecera c SET id_asiento_contable = p.id_migracion, updated_at = now(), updated_by = 2
FROM pares p WHERE c.id = p.id_compra;

-- 3) Verificación
SELECT id_nativo, (SELECT estado FROM asientos_contables_cabecera WHERE id = p.id_nativo) AS estado_nativo,
       id_migracion, (SELECT estado FROM asientos_contables_cabecera WHERE id = p.id_migracion) AS estado_migracion
FROM pares p;

-- Si todo se ve bien (nativo=anulado, migracion=contabilizado): COMMIT;
-- Si algo no cuadra: ROLLBACK;
