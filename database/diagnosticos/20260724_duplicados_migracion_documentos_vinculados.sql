-- Busca TODOS los casos (todas las empresas, todos los tipos de documento) donde un
-- documento NATIVO (migracion_mysql_map.vinculado=true, es decir: la migración solo lo
-- enlazó por número, no lo creó) terminó con DOS asientos activos: el suyo propio (nativo)
-- Y otro insertado por error desde la migración (modulo_origen='migracion').
--
-- Caso confirmado que originó esta búsqueda: compra id=157 (empresa 8), asiento nativo 109
-- (DI-000003, creado 2026-06-26) + asiento de migración 7719 (COM304061, creado 2026-07-13),
-- mismo monto $205.36.

WITH vinculados AS (
    SELECT id_empresa, entidad, id_destino
    FROM migracion_mysql_map
    WHERE vinculado = true
),
-- modulo_origen_nativo: tabla real del documento nativo.
-- tipo_comprobante_migracion: filtro OBLIGATORIO adicional -- el asiento de migración no
-- guarda a qué entidad pertenece (siempre modulo_origen='migracion'), así que
-- id_referencia_origen por sí solo puede coincidir por pura casualidad con un documento de
-- OTRA tabla (compras/ingresos/egresos tienen su propio autoincremental independiente). El
-- tipo_comprobante sí distingue el tipo real de comprobante migrado.
mapa_entidad_modulo (entidad, modulo_origen_nativo, tipo_comprobante_migracion) AS (
    VALUES
        ('compras', 'compra', 'compras'),
        ('retenciones_venta', 'retencion_venta', 'retenciones_ventas'),
        ('retenciones_compra', 'retencion_compra', 'retenciones_compras'),
        ('ingresos', 'ingreso', 'ingresos'),
        ('egresos', 'egreso', 'egresos'),
        ('facturas', 'factura_venta', 'ventas'),
        ('notas_credito', 'nota_credito', 'ventas'),
        ('recibos', 'recibo_venta', 'ventas')
)
SELECT
    v.id_empresa,
    v.entidad,
    v.id_destino                     AS id_documento,
    ac_nat.id                        AS id_asiento_nativo,
    ac_nat.numero_comprobante        AS comprobante_nativo,
    ac_nat.total_debe                AS monto_nativo,
    ac_nat.created_at                AS nativo_creado,
    ac_mig.id                        AS id_asiento_migracion,
    ac_mig.numero_comprobante        AS comprobante_migracion,
    ac_mig.total_debe                AS monto_migracion,
    ac_mig.created_at                AS migracion_creado
FROM vinculados v
JOIN mapa_entidad_modulo me ON me.entidad = v.entidad
JOIN asientos_contables_cabecera ac_nat
     ON ac_nat.id_empresa = v.id_empresa
    AND ac_nat.modulo_origen = me.modulo_origen_nativo
    AND ac_nat.id_referencia_origen = v.id_destino
    AND ac_nat.eliminado = false
    AND ac_nat.estado <> 'anulado'
JOIN asientos_contables_cabecera ac_mig
     ON ac_mig.id_empresa = v.id_empresa
    AND ac_mig.modulo_origen = 'migracion'
    AND ac_mig.tipo_comprobante = me.tipo_comprobante_migracion
    AND ac_mig.id_referencia_origen = v.id_destino
    AND ac_mig.eliminado = false
    AND ac_mig.estado <> 'anulado'
    AND ac_mig.total_debe = ac_nat.total_debe   -- mismo monto: descarta coincidencias falsas
ORDER BY v.id_empresa, v.entidad, v.id_destino;
