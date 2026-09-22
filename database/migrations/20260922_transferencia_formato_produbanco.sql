-- ============================================================================
-- Formato de Transferencia Bancaria: PRODUBANCO (Cash Management)
-- ----------------------------------------------------------------------------
-- Catálogo global editable en /config/transferencia-formatos (nivel 3).
-- Layout tomado de la plantilla oficial del banco ("cargas cash.xls", hoja
-- ANTICIPOS): 20 columnas fijas. El archivo generado lleva UNA fila de
-- encabezado con los nombres de columna (fila 4 de la plantilla) y los datos
-- desde la fila 2 — las filas decorativas de la plantilla (título, numeración
-- 1..20 y MANDATORIO/OPCIONAL) no se reproducen.
--
-- Reglas no evidentes tomadas de los comentarios de la plantilla del banco:
--   col  4  NUMERO DE COMPROBANTE DE PAGO : opcional; se deja vacía, igual que
--           en el archivo que el banco acepta (texto fijo sin valor).
--   col  2  NUMERO DE CUENTA DE EMPRESA : cuenta emisora, la de la forma de
--           pago de origen del lote; se escribe tal cual está registrada.
--   col  7  VALOR        : entero sin punto ni separadores; los 2 últimos
--                          dígitos son los decimales ($180.00 -> 18000).
--   col  9  CODIGO DE BANCO : 4 dígitos con ceros a la izquierda
--                          (coincide con bancos_ecuador.codigo_banco).
--   col 10  TIPO DE CUENTA  : AHO (ahorros) / CTE (corriente).
--   col 12  TIPO DE DOCUMENTO : C (cédula, empleados) / R (RUC, proveedores).
--   col 14  NOMBRES      : máx. 40 caracteres, sin Ñ, tildes ni puntuación.
--   col 19  REFERENCIA   : motivo del pago, máx. 200.
--   col 20  REFERENCIA ADICIONAL : correo electrónico del beneficiario
--                          (el banco lo usa para notificar el pago), máx. 100.
--
-- Requiere el origen de dato `correo`, agregado en la misma entrega
-- (TransferenciaFormatoService::ORIGEN_DATO + TransferenciaFormatoConfigurable
-- + TransferenciaLoteRepository::getDetalle).
--
-- Idempotente: no hace nada si el formato ya existe.
-- ============================================================================

INSERT INTO transferencia_formatos (
    id_banco, nombre, descripcion, tipo_archivo, delimitador,
    incluye_encabezado, nombre_hoja, campos, clase_formatter, estado
)
SELECT
    (SELECT id FROM bancos_ecuador WHERE codigo_banco = '0036' LIMIT 1),
    'Produbanco (Cash Management)',
    'Plantilla de carga de pagos de Produbanco: 20 columnas, una fila de encabezado con los nombres de columna. Sirve para proveedores y para nómina (el mismo layout).',
    'xlsx',
    NULL,
    true,
    'ANTICIPOS',
    '[
  {"orden":1,"etiqueta":"TIPO: PAGOS","origen_dato":"texto_fijo","valor_fijo":"PA","tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":2,"etiqueta":"NUMERO DE CUENTA DE EMPRESA","origen_dato":"cuenta_empresa","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":3,"etiqueta":"NUMERO SECUENCIAL","origen_dato":"secuencial","valor_fijo":null,"tipo_dato":"numero","formato_numero":null,"decimales":0,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":4,"etiqueta":"NUMERO DE COMPROBANTE DE PAGO","origen_dato":"texto_fijo","valor_fijo":"","tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":5,"etiqueta":"CODIGO DE EMPLEADO","origen_dato":"identificacion","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":20,"mapeo_valores":null},
  {"orden":6,"etiqueta":"MONEDA","origen_dato":"moneda","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":7,"etiqueta":"VALOR","origen_dato":"monto","valor_fijo":null,"tipo_dato":"numero","formato_numero":"entero_centavos","decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":8,"etiqueta":"FORMA DE PAGO","origen_dato":"texto_fijo","valor_fijo":"CTA","tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":9,"etiqueta":"CODIGO DE BANCO","origen_dato":"codigo_banco","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":4,"relleno_caracter":"0","alineacion":"derecha","mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":10,"etiqueta":"TIPO DE CUENTA","origen_dato":"tipo_cuenta","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":{"ahorros":"AHO","corriente":"CTE","AHORROS":"AHO","CORRIENTE":"CTE","virtual":"AHO","otro":"AHO"}},
  {"orden":11,"etiqueta":"NUMERO DE CUENTA","origen_dato":"numero_cuenta","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":12,"etiqueta":"TIPO DE DOCUMENTO DE EMPLEADO","origen_dato":"tipo_beneficiario","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":{"PROVEEDOR":"R","EMPLEADO":"C"}},
  {"orden":13,"etiqueta":"NUMERO DE CEDULA DE EMPLEADO","origen_dato":"identificacion","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":13,"mapeo_valores":null},
  {"orden":14,"etiqueta":"NOMBRES DE EMPLEADO","origen_dato":"nombre_beneficiario","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":true,"quitar_tildes":true,"solo_alfanumerico":true,"max_caracteres":40,"mapeo_valores":null},
  {"orden":15,"etiqueta":"DIRECCION EMPLEADO","origen_dato":"texto_fijo","valor_fijo":"","tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":16,"etiqueta":"CIUDAD EMPLEADO","origen_dato":"texto_fijo","valor_fijo":"","tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":17,"etiqueta":"TELEFONO EMPLEADO","origen_dato":"telefono","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":18,"etiqueta":"LOCALIDAD DE COBRO","origen_dato":"texto_fijo","valor_fijo":"","tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":null,"mapeo_valores":null},
  {"orden":19,"etiqueta":"REFERENCIA","origen_dato":"concepto","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":true,"quitar_tildes":true,"solo_alfanumerico":false,"max_caracteres":200,"mapeo_valores":null},
  {"orden":20,"etiqueta":"REFERENCIA ADICIONAL","origen_dato":"correo","valor_fijo":null,"tipo_dato":"texto","formato_numero":null,"decimales":2,"longitud_fija":null,"relleno_caracter":null,"alineacion":null,"mayusculas":false,"quitar_tildes":false,"solo_alfanumerico":false,"max_caracteres":100,"mapeo_valores":null}
]'::jsonb,
    NULL,
    'activo'
WHERE NOT EXISTS (
    SELECT 1 FROM transferencia_formatos
    WHERE nombre = 'Produbanco (Cash Management)' AND eliminado = false
);
