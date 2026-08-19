-- Naturaleza de cuenta de los conceptos contables (catálogo GLOBAL `asientos_tipo`).
--
-- `tipo_cuenta` es la lista de clases del plan de cuentas que admite cada concepto. Cumple dos
-- funciones: filtra el buscador de cuentas de Configuración Contable y, desde el 19-08-2026,
-- alimenta la validación de servidor que impide guardar una cuenta de naturaleza incompatible
-- (AsientoProgramadoService::validarNaturalezaCuenta).
--
-- Esta migración corrige/completa el catálogo:
--   1. Las dos cuentas por cobrar de ventas declaraban 'activo,ingreso', así que el campo
--      "Cuenta por cobrar" ofrecía también las cuentas de Ventas (4.x). Ese fue el origen del
--      error de las empresas 1 y 37 — ver database/diagnosticos/20260819_cxc_ventas_cuenta_incorrecta.sql.
--   2. Dieciséis conceptos (Importaciones, Consignaciones, Factura de Reembolso) no declaraban
--      ninguna naturaleza: su buscador ofrecía TODO el plan y la validación no tenía con qué
--      comparar. Se les asigna la que corresponde a su papel en el asiento.
--
-- Valores admitidos (ver AsientoProgramadoService::CLASES_POR_TIPO_CUENTA):
--   activo=1, pasivo=2, patrimonio=3, ingreso=4, costo/gasto=5 o 6. Se pueden combinar con coma
--   cuando el concepto admite legítimamente más de una (p. ej. los ajustes por redondeo).
--
-- Tabla global (sin id_empresa): aplica a todas las empresas. Idempotente.

BEGIN;

-- ── 1. Cartera de ventas: solo activo ────────────────────────────────────────
UPDATE asientos_tipo
SET tipo_cuenta = 'activo', updated_at = NOW()
WHERE codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
  AND COALESCE(tipo_cuenta, '') <> 'activo';

-- ── 2. Conceptos que no declaraban naturaleza ────────────────────────────────
UPDATE asientos_tipo t
SET tipo_cuenta = v.tipo_cuenta, updated_at = NOW()
FROM (VALUES
    -- Importaciones
    ('INVENTARIOIMPORTACION',                  'activo'),               -- inventario nacionalizado
    ('INVENTARIOIMPORTACIONMATERIAPRIMA',      'activo'),
    ('INVENTARIOIMPORTACIONPRODUCTOTERMINADO', 'activo'),
    ('IVAIMPORTACION',                         'activo'),               -- crédito tributario
    ('ISDIMPORTACION',                         'costo,gasto'),          -- gasto financiero, no capitaliza
    ('OTROSGASTOSIMPORTACION',                 'costo,gasto'),
    ('RECLASIFICACIONGASTOIMPORTACION',        'costo,gasto'),          -- acredita el gasto ya registrado
    ('PORPAGARPROVEEDOREXTERIOR',              'pasivo'),
    ('PORPAGARTRIBUTOSADUANEROS',              'pasivo'),
    ('REDONDEOIMPORTACION',                    'ingreso,costo,gasto'),  -- ajuste de centavos, puede ir a favor o en contra
    -- Consignaciones
    ('CONSIGNACION_MERCADERIA',                'activo'),               -- mercadería en poder de terceros
    ('CONSIGNACION_INVENTARIO',                'activo'),
    -- Factura de reembolso
    ('FACTREEMB_CXC_CLIENTE',                  'activo'),
    ('FACTREEMB_INGRESO_HONORARIOS',           'ingreso'),
    ('FACTREEMB_IVA_VENTAS_HONORARIOS',        'pasivo'),
    -- Cuenta puente: según cómo la modele el plan de cada empresa puede ser un pasivo
    -- (lo que se debe al tercero) o una cuenta transitoria de activo. Se admiten ambas.
    ('FACTREEMB_PUENTE_TERCEROS',              'activo,pasivo')
) AS v(codigo, tipo_cuenta)
WHERE t.codigo = v.codigo
  AND t.eliminado = false
  AND COALESCE(TRIM(t.tipo_cuenta), '') = '';

-- ── 3. Control: no debe quedar ningún concepto sin naturaleza declarada ──────
SELECT tipo_asiento, codigo, referencia
FROM asientos_tipo
WHERE eliminado = false
  AND COALESCE(TRIM(tipo_cuenta), '') = ''
ORDER BY tipo_asiento, codigo;

COMMIT;
