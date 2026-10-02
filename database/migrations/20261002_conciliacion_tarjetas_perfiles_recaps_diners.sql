-- ============================================================
-- Conciliación de Tarjetas: perfiles para los RECAPS de DINERS /
-- INTERDIN (un libro Excel con una hoja por marca: DINERS,
-- DISCOVER, VISA, MASTERD CARD) — config/conciliacion-tarjetas-perfiles.
--
-- 1) Dos opciones nuevas del perfil (solo Excel):
--    • hoja: qué hoja leer. NULL = la hoja activa (comportamiento de
--      siempre); '*' = todas las hojas, y el nombre de cada una (la
--      marca) va a la descripción de la línea; otro valor = la hoja con
--      ese nombre (sin distinguir mayúsculas/espacios, y basta con que
--      esté contenido: 'MASTER' encuentra 'MASTERD CARD').
--    • solo_columnas_visibles: ignora las columnas ocultas del Excel y
--      numera las que quedan desde 0 en el orden en que se ven.
--    Los perfiles existentes quedan igual (hoja NULL, visibles FALSE).
--
-- 2) Cinco perfiles: uno con todas las marcas y uno por marca, para
--    la empresa que concilia cada marca en una forma de cobro distinta.
--    Validados con "RECAPS SEPTIEMBRE.xlsx" (sep-2026): 32 vales,
--    bruto 5.225,76 · comisión 461,74 · ret. IR 104,51 · neto 4.659,51.
--
--    Columnas VISIBLES del archivo (el resto viene oculto):
--      0 Fecha del vale   "20260825" -> fecha (Ymd; es la del cobro)
--      1 Fecha del pago   "20260901" -> fecha_pago (a la descripción)
--      2 Número Recap o Lote         -> lote       (a la descripción)
--      3 Número vale                 -> referencia
--      4 Valor Bruto Cuota           -> monto_bruto
--      5 Valor Comisión Cuota        -> comision
--      6 Valor Retención IRF Cuota   -> retencion_ir
--      7 Valor Pago Cuota            -> monto_neto (bruto − comisión − ret. IR)
--    La autorización (col. AN) viene oculta: el cruce automático usa
--    el número de vale (si el cajero lo anotó en la referencia del
--    cobro) y, si no, valor + fecha del vale.
--
-- Sin banco (id_banco NULL): la forma de cobro de tarjeta suele tener
-- el banco donde se deposita, no Diners, y con banco el perfil no se
-- ofrecería. Tipo de procesadora: TARJETA (datáfono).
--
-- Requiere antes: 20260924_conciliacion_tarjetas_perfiles_global.sql.
-- Idempotente. Listo para pgAdmin (F5).
-- ============================================================

ALTER TABLE conciliacion_tarjetas_perfiles
    ADD COLUMN IF NOT EXISTS hoja VARCHAR(60);

ALTER TABLE conciliacion_tarjetas_perfiles
    ADD COLUMN IF NOT EXISTS solo_columnas_visibles BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN conciliacion_tarjetas_perfiles.hoja IS
    'Excel: hoja a leer. NULL = la activa; ''*'' = todas (el nombre de la hoja va a la descripción); otro = la hoja cuyo nombre lo contiene.';
COMMENT ON COLUMN conciliacion_tarjetas_perfiles.solo_columnas_visibles IS
    'Excel: ignora las columnas ocultas; el mapeo cuenta solo las visibles, desde 0.';

INSERT INTO conciliacion_tarjetas_perfiles (
    tipo_procesadora, id_banco, nombre_perfil, tipo_archivo, nivel, fila_inicio,
    formato_fecha, separador_decimal, hoja, solo_columnas_visibles, mapeo_columnas, activo
)
SELECT 'TARJETA', NULL, v.nombre, 'EXCEL', 'transaccion', 1,
       'Ymd', '.', v.hoja, TRUE,
       '{"fecha": {"col": 0}, "fecha_pago": {"col": 1}, "lote": {"col": 2}, "referencia": {"col": 3},
         "monto_bruto": {"col": 4}, "comision": {"col": 5}, "retencion_ir": {"col": 6},
         "monto_neto": {"col": 7}}'::jsonb,
       TRUE
FROM (VALUES
    ('Diners/Interdin - Recaps (todas las marcas)', '*'),
    ('Diners/Interdin - Recaps: hoja DINERS',       'DINERS'),
    ('Diners/Interdin - Recaps: hoja DISCOVER',     'DISCOVER'),
    ('Diners/Interdin - Recaps: hoja VISA',         'VISA'),
    ('Diners/Interdin - Recaps: hoja MASTERCARD',   'MASTER')
) AS v(nombre, hoja)
WHERE NOT EXISTS (
    SELECT 1 FROM conciliacion_tarjetas_perfiles p
    WHERE p.nombre_perfil = v.nombre AND p.eliminado = FALSE
);

-- Verificación (deben salir 5 filas):
-- SELECT id, nombre_perfil, hoja, solo_columnas_visibles, formato_fecha, mapeo_columnas
--   FROM conciliacion_tarjetas_perfiles
--  WHERE nombre_perfil LIKE 'Diners/Interdin - Recaps%' AND eliminado = FALSE
--  ORDER BY id;
