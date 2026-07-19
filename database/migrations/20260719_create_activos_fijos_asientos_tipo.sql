-- ============================================================
-- Catálogo de conceptos contables para Activos Fijos.
-- asientos_tipo es una tabla GLOBAL (sin id_empresa) — las cuentas
-- por empresa se asignan aparte en asientos_programados vía
-- Configuración Contable (tipos 'activos_fijos_alta' y
-- 'activos_fijos_depreciacion').
--
-- Nota: las cuentas de Activo, Depreciación Acumulada y Gasto por
-- Depreciación NO pasan por este catálogo — se leen directo de
-- activos_fijos_categorias (cada categoría define sus 3 cuentas).
-- Estas dos filas solo resuelven: (a) la contrapartida del alta
-- manual (sin factura de compra), y (b) el ajuste de redondeo del
-- lote mensual de depreciación.
-- Idempotente: no falla si ya se corrió antes.
-- ============================================================

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'activos_fijos_alta', 'Contrapartida de alta manual',
       'Cuenta que se acredita al dar de alta un activo fijo manual (sin factura de compra): caja, bancos, cuentas por pagar o capital, según corresponda',
       'CONTRAPARTIDAALTAACTIVOFIJO', 'activo,pasivo,patrimonio', 'haber', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'CONTRAPARTIDAALTAACTIVOFIJO');

INSERT INTO asientos_tipo (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, eliminado, created_at)
SELECT 'activos_fijos_depreciacion', 'Ajuste por redondeo',
       'Ajuste por redondeo del lote mensual de depreciación de activos fijos',
       'AJUSTEREDONDEODEPRECIACION', 'gasto', 'debe', false, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM asientos_tipo WHERE codigo = 'AJUSTEREDONDEODEPRECIACION');

-- Devuelve las filas insertadas para confirmar.
SELECT id, tipo_asiento, referencia, codigo FROM asientos_tipo
WHERE tipo_asiento IN ('activos_fijos_alta', 'activos_fijos_depreciacion') ORDER BY id;
