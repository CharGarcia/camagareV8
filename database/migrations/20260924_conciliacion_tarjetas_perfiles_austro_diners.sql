-- ============================================================
-- Conciliación de Tarjetas: perfiles de lectura para el datáfono
-- del BANCO DEL AUSTRO (Visa / Mastercard) y de DINERS CLUB
-- (config/conciliacion-tarjetas-perfiles).
--
-- Requiere antes: 20260924_conciliacion_tarjetas_perfiles_global.sql
-- (columnas tipo_procesadora / id_banco, id_empresa nullable).
--
-- Validados con archivos reales de sep-2026 (comercio HANAMI):
--
-- 1) "Banco del Austro - Liquidaciones (hoja Listado)"  — .xls
--    Se lee la PRIMERA hoja (la activa), "Listado": una fila por
--    depósito diario. Las hojas por día ("01-09", "02-09"…) con el
--    detalle por recap NO se leen.
--      fila 0 títulos                          -> fila_inicio = 1
--      col 1 Fecha        "9/1/2026" (mes/día/año) -> m/d/Y
--      col 2 No. Transacc. (cantidad de vouchers) -> referencia
--      col 3 Total        -> monto_bruto
--      col 4 Comision     -> comision
--      col 5 Retencion    -> retencion_ir
--      col 6 Descuento    -> otros_descuentos
--      col 7 Liquidacion  -> monto_neto (lo que entra al banco)
--    Nivel "deposito": cada línea es un total diario, no un cobro.
--
-- 2) "Diners Club - Reporte de establecimiento" — .xlsx
--    Hoja "ReporteEstablecimiento…": una fila por vale (transacción).
--      fila 0 títulos                          -> fila_inicio = 1
--      col 1  Fecha del vale  "20260829"        -> Ymd
--      col 9  Número vale     -> referencia
--      col 10 Número tarjeta (enmascarado) -> descripcion
--      col 32 Valor Total Bruto        -> monto_bruto
--      col 33 Valor Total Comisión     -> comision
--      col 34 Valor Total Retención IVA -> retencion_iva
--      col 35 Valor Total Retención IRF -> retencion_ir
--      col 36 Valor Total Pago         -> monto_neto
--      col 42 Número de autorización   -> autorizacion
--    Se usan las columnas "Total" (no las "Cuota") para que un
--    consumo diferido se cruce por su valor completo.
--
-- Bancos por código (bancos_ecuador): AUSTRO 0035, DINERS CLUB 9967.
-- Idempotente: no inserta si ya existe un perfil con ese nombre.
-- Listo para pgAdmin (F5).
-- ============================================================

INSERT INTO conciliacion_tarjetas_perfiles (
    tipo_procesadora, id_banco, nombre_perfil, tipo_archivo, nivel, fila_inicio,
    formato_fecha, separador_decimal, mapeo_columnas, activo
)
SELECT
    'TARJETA',
    (SELECT id FROM bancos_ecuador WHERE codigo_banco = '0035' LIMIT 1),
    'Banco del Austro - Liquidaciones (hoja Listado)',
    'EXCEL',
    'deposito',
    1,
    'm/d/Y',
    '.',
    '{"fecha": {"col": 1}, "referencia": {"col": 2}, "monto_bruto": {"col": 3},
      "comision": {"col": 4}, "retencion_ir": {"col": 5}, "otros_descuentos": {"col": 6},
      "monto_neto": {"col": 7}}'::jsonb,
    TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM conciliacion_tarjetas_perfiles
    WHERE nombre_perfil = 'Banco del Austro - Liquidaciones (hoja Listado)' AND eliminado = FALSE
);

INSERT INTO conciliacion_tarjetas_perfiles (
    tipo_procesadora, id_banco, nombre_perfil, tipo_archivo, nivel, fila_inicio,
    formato_fecha, separador_decimal, mapeo_columnas, activo
)
SELECT
    'TARJETA',
    (SELECT id FROM bancos_ecuador WHERE codigo_banco = '9967' LIMIT 1),
    'Diners Club - Reporte de establecimiento',
    'EXCEL',
    'transaccion',
    1,
    'Ymd',
    '.',
    '{"fecha": {"col": 1}, "referencia": {"col": 9}, "descripcion": {"col": 10},
      "monto_bruto": {"col": 32}, "comision": {"col": 33}, "retencion_iva": {"col": 34},
      "retencion_ir": {"col": 35}, "monto_neto": {"col": 36}, "autorizacion": {"col": 42}}'::jsonb,
    TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM conciliacion_tarjetas_perfiles
    WHERE nombre_perfil = 'Diners Club - Reporte de establecimiento' AND eliminado = FALSE
);

-- Verificación (deben salir 2 filas, cada una con su banco):
-- SELECT p.id, p.nombre_perfil, p.tipo_procesadora, b.nombre_banco, p.nivel, p.formato_fecha
--   FROM conciliacion_tarjetas_perfiles p
--   LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
--  WHERE p.nombre_perfil IN ('Banco del Austro - Liquidaciones (hoja Listado)',
--                            'Diners Club - Reporte de establecimiento');
