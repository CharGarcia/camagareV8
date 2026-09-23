-- ============================================================
-- Conciliación de Cobros: perfil de mapeo para el estado de cuenta
-- en Excel de PRODUBANCO (config/conciliacion-perfiles).
--
-- Requiere antes: 20260923_conciliacion_perfiles_global.sql
-- (id_empresa nullable) y el código que soporta en Excel las
-- claves opcionales "tipo"/"tipo_credito" y "descripcion_extra"
-- (ConciliacionImportService::normalizarFilaExcel).
--
-- Estructura del archivo (validada con un extracto real de sep-2026):
--   filas 1-9  datos de la cuenta, fila 10 títulos  -> fila_inicio = 10
--   col 0 FECHA        "09/01/2026 05:04:00 AM" (mes/día/año) -> m/d/Y h:i:s A
--   col 1 REFERENCIA   -> referencia
--   col 2 REFERENCIA2  nombre de quien paga -> descripcion
--   col 3 DESCRIPCION  tipo de transacción -> descripcion_extra
--   col 4 +/-          '+' crédito / '-' débito -> tipo + tipo_credito '+'
--   col 5 VALOR        siempre positivo -> monto
--
-- Idempotente: no inserta si ya existe un perfil con ese nombre.
-- ============================================================

INSERT INTO conciliacion_perfiles (
    id_banco, nombre_perfil, tipo_archivo, fila_inicio, formato_fecha,
    separador_decimal, mapeo_columnas, activo
)
SELECT
    (SELECT id FROM bancos_ecuador WHERE codigo_banco = '0036' LIMIT 1),
    'Produbanco - Excel (estado de cuenta)',
    'EXCEL',
    10,
    'm/d/Y h:i:s A',
    '.',
    '{"fecha": {"col": 0}, "descripcion": {"col": 2}, "descripcion_extra": {"col": 3},
      "monto": {"col": 5}, "referencia": {"col": 1}, "tipo": {"col": 4}, "tipo_credito": "+"}'::jsonb,
    TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM conciliacion_perfiles
    WHERE nombre_perfil = 'Produbanco - Excel (estado de cuenta)' AND eliminado = FALSE
);

-- Verificación
SELECT p.id, p.nombre_perfil, b.nombre_banco, p.fila_inicio, p.formato_fecha, p.mapeo_columnas
FROM conciliacion_perfiles p
LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
WHERE p.nombre_perfil = 'Produbanco - Excel (estado de cuenta)';
