-- Diagnóstico: inspeccionar, para uno o varios numero_comprobante puntuales,
-- todas las cabeceras que existen con ese número y las cuentas que usa cada una
-- en su detalle. Útil para ver POR QUÉ un mismo comprobante (ej. 'DI-000003',
-- 'COM304061') aparece más de una vez con cuentas distintas.
--
-- Edita la lista de la cláusula IN con los números que quieras revisar.

-- 1) Cabeceras: cuántas veces aparece cada número, en qué empresa, con qué estado
SELECT
    ac.id            AS id_asiento,
    ac.id_empresa,
    ac.numero_comprobante,
    ac.tipo_comprobante,
    ac.modulo_origen,
    ac.id_referencia_origen,
    ac.fecha_asiento,
    ac.estado,
    ac.eliminado,
    ac.total_debe,
    ac.total_haber,
    ac.created_at,
    ac.created_by
FROM asientos_contables_cabecera ac
WHERE ac.numero_comprobante IN ('DI-000003', 'COM304061')
ORDER BY ac.numero_comprobante, ac.id_empresa, ac.id;

-- 2) Detalle línea por línea de esas mismas cabeceras, con el código/nombre de cuenta,
--    para comparar visualmente qué cuenta usó cada ocurrencia del mismo comprobante.
SELECT
    ac.id             AS id_asiento,
    ac.numero_comprobante,
    ac.estado,
    ac.eliminado      AS asiento_eliminado,
    ad.id             AS id_detalle,
    ad.id_cuenta_contable,
    pc.codigo         AS cuenta_codigo,
    pc.nombre         AS cuenta_nombre,
    ad.debe,
    ad.haber,
    ad.referencia_detalle,
    ad.eliminado      AS detalle_eliminado
FROM asientos_contables_cabecera ac
JOIN asientos_contables_detalle ad
     ON ad.id_asiento = ac.id AND ad.id_empresa = ac.id_empresa
LEFT JOIN plan_cuentas pc
     ON pc.id = ad.id_cuenta_contable AND pc.id_empresa = ac.id_empresa
WHERE ac.numero_comprobante IN ('DI-000003', 'COM304061')
ORDER BY ac.numero_comprobante, ac.id_empresa, ac.id, ad.id;
