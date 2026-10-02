-- ============================================================
-- Conciliación de Tarjetas: corrige el NETO del perfil
-- "Diners Club - Reporte de establecimiento"
-- (config/conciliacion-tarjetas-perfiles).
--
-- El perfil tomaba como neto la columna 36 "Valor Total Pago". En el
-- reporte de Diners esa columna es bruto − comisión, SIN descontar la
-- retención IR (recaps sep-2026: 40,82 − 1,89 = 38,93, pero se
-- depositan 40,82 − 1,89 − 0,82 = 38,11, que es "Valor Pago Cuota").
-- Como el lector respeta el neto del archivo cuando viene mapeado, cada
-- vale quedaba con un descuadre igual a su retención IR.
--
-- Corrección: se quita "monto_neto" del mapeo. Sin él, el sistema
-- calcula neto = bruto − comisión − IVA comisión − ret. IR − ret. IVA
-- − otros descuentos (columnas que el perfil ya lee).
--
-- No relee archivos ya cargados: las conciliaciones en BORRADOR que
-- usaron este perfil deben volver a cargar el archivo (consulta al
-- final). Las CERRADAS no se tocan.
--
-- Idempotente. Listo para pgAdmin (F5).
-- ============================================================

UPDATE conciliacion_tarjetas_perfiles
   SET mapeo_columnas = mapeo_columnas - 'monto_neto',
       updated_at     = CURRENT_TIMESTAMP
 WHERE nombre_perfil = 'Diners Club - Reporte de establecimiento'
   AND eliminado = FALSE
   AND mapeo_columnas ? 'monto_neto';

-- Verificación (mapeo sin "monto_neto"):
-- SELECT id, nombre_perfil, mapeo_columnas
--   FROM conciliacion_tarjetas_perfiles
--  WHERE nombre_perfil = 'Diners Club - Reporte de establecimiento' AND eliminado = FALSE;

-- Conciliaciones cargadas con este perfil (las de estado 'borrador' deben
-- volver a cargar el archivo para que el neto se recalcule):
-- SELECT c.id_empresa, c.numero, c.estado, c.fecha_conciliacion, c.nombre_archivo,
--        c.total_neto, c.neto_depositado, c.diferencia
--   FROM conciliacion_tarjetas_cabecera c
--   JOIN conciliacion_tarjetas_perfiles p ON p.id = c.id_perfil
--  WHERE p.nombre_perfil = 'Diners Club - Reporte de establecimiento'
--    AND c.eliminado = FALSE
--  ORDER BY c.estado, c.fecha_conciliacion DESC;
