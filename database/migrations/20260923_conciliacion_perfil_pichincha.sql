-- ============================================================
-- Conciliación de Cobros: perfil de mapeo para el archivo Excel de
-- movimientos de BANCO PICHINCHA (config/conciliacion-perfiles).
--
-- Requiere antes: 20260923_conciliacion_perfiles_global.sql
-- (id_empresa nullable) y el código que soporta en Excel las
-- claves opcionales "tipo"/"tipo_credito" (ConciliacionImportService).
--
-- Estructura del archivo (validada con mov-22-09-2026, hoja "Movimientos"):
--   fila 1 títulos -> fila_inicio = 1
--   col 0 Concepto   "DIRECTA DE <NOMBRE>", "INTERBANCARIA DE <NOMBRE>" -> descripcion
--   col 1 Fecha      "22/09/2026" -> d/m/Y
--   col 2 Documento  -> referencia
--   col 3 Monto      siempre positivo -> monto
--   col 4 Tipo       'C' crédito / 'D' débito -> tipo + tipo_credito 'C'
--
-- Idempotente: no inserta si ya existe un perfil con ese nombre.
-- ============================================================

INSERT INTO conciliacion_perfiles (
    id_banco, nombre_perfil, tipo_archivo, fila_inicio, formato_fecha,
    separador_decimal, mapeo_columnas, activo
)
SELECT
    (SELECT id FROM bancos_ecuador WHERE codigo_banco = '0010' LIMIT 1),
    'Banco Pichincha - Excel (movimientos)',
    'EXCEL',
    1,
    'd/m/Y',
    '.',
    '{"fecha": {"col": 1}, "descripcion": {"col": 0}, "monto": {"col": 3},
      "referencia": {"col": 2}, "tipo": {"col": 4}, "tipo_credito": "C"}'::jsonb,
    TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM conciliacion_perfiles
    WHERE nombre_perfil = 'Banco Pichincha - Excel (movimientos)' AND eliminado = FALSE
);

-- Verificación
SELECT p.id, p.nombre_perfil, b.nombre_banco, p.fila_inicio, p.formato_fecha, p.mapeo_columnas
FROM conciliacion_perfiles p
LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
WHERE p.nombre_perfil = 'Banco Pichincha - Excel (movimientos)';
