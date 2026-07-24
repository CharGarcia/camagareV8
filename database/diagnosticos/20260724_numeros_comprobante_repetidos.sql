-- Diagnóstico: TODOS los numero_comprobante que se repiten en asientos ACTIVOS
-- (eliminado=false AND estado <> 'anulado'), sin importar modulo_origen/tipo.
--
-- Este es distinto del diagnóstico de "documento con 2+ asientos" (mismo
-- modulo_origen+id_referencia_origen): aquí buscamos el caso encontrado en
-- producción con 'DI-000003' — DOS DOCUMENTOS DISTINTOS que, por un bug en
-- generarNumeroComprobante() (AsientoContableRepository.php), terminaron
-- compartiendo el mismo número de comprobante visible (mismo prefijo+secuencia)
-- aunque son transacciones totalmente independientes, cada una con sus propias
-- cuentas. El fix de código ya evita que se generen NUEVAS colisiones para
-- 'compras' y 'retorno_consignacion'; esta consulta es para ver cuántas
-- colisiones YA EXISTEN en los datos históricos.

SELECT
    ac.id_empresa,
    ac.numero_comprobante,
    COUNT(*)                                              AS ocurrencias,
    STRING_AGG(ac.id::text, ', ' ORDER BY ac.id)           AS ids_asiento,
    STRING_AGG(DISTINCT ac.tipo_comprobante, ', ')         AS tipos_comprobante,
    STRING_AGG(DISTINCT ac.modulo_origen, ', ')            AS modulos_origen,
    SUM(ac.total_debe)                                     AS suma_total_debe
FROM asientos_contables_cabecera ac
WHERE ac.eliminado = false
  AND ac.estado <> 'anulado'
GROUP BY ac.id_empresa, ac.numero_comprobante
HAVING COUNT(*) > 1
ORDER BY ac.id_empresa, ac.numero_comprobante;
