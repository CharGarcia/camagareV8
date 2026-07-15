-- ============================================================
-- Fix de dato: el concepto AJUSTEREDONDEOCOMPRA (Ajuste por redondeo
-- en compras) tenía tipo_cuenta = 'ingreso,costo,gasto', incluyendo
-- "ingreso" sin sentido para un ajuste de compras (parece copiado por
-- error del equivalente de ventas). Debe ser solo 'costo,gasto'.
-- Idempotente: no falla si ya se corrigió.
-- ============================================================

UPDATE asientos_tipo
SET tipo_cuenta = 'costo,gasto'
WHERE codigo = 'AJUSTEREDONDEOCOMPRA'
  AND tipo_cuenta = 'ingreso,costo,gasto';
