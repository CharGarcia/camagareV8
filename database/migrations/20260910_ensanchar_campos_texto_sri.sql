-- ============================================================================
-- Campos de texto que recibe el sistema desde los XML del SRI
-- Fecha: 2026-09-10
--
-- CONTEXTO
--   Al cargar una factura recibida con el servicio de Descargas del SRI, el
--   registro automático falla con:
--
--     XML obtenido pero error en registro: SQLSTATE[22001]: String data, right
--     truncated: 7 ERROR: value too long for type character varying(150)
--
--   El comprobante trae la dirección de matriz del emisor (<dirMatriz>), que la
--   ficha técnica del SRI admite hasta 300 caracteres, y se guarda en
--   proveedores.direccion, que era VARCHAR(150). PostgreSQL no trunca solo
--   (MySQL en modo laxo sí): aborta el INSERT entero, y con él el registro del
--   documento, aunque el XML se haya descargado bien.
--
--   Las columnas hermanas alimentadas por el mismo XML también quedaban por
--   debajo del máximo del SRI:
--
--     columna                        antes   SRI   se llena con
--     proveedores.direccion            150   300   <dirMatriz> / <direccionProveedor>
--     proveedores.razon_social         200   300   <razonSocial> / <razonSocialProveedor>
--     proveedores.nombre_comercial     200   300   <nombreComercial>
--     productos.nombre                 255   300   <descripcion> del detalle
--
--   clientes.direccion y clientes.nombre ya eran VARCHAR(300); esto deja a
--   proveedores y productos con el mismo criterio.
--
-- QUÉ HACE ESTE SCRIPT
--   Ensancha esas cuatro columnas a VARCHAR(300). No toca datos: ampliar el
--   largo de un varchar en PostgreSQL no reescribe la tabla ni valida filas.
--
--   El código además capa cualquier texto al largo REAL de su columna antes de
--   escribir (BaseRepository::caparTexto()), así que el registro ya no revienta
--   ni con este script sin aplicar — pero hasta aplicarlo las direcciones largas
--   se guardan cortadas a 150.
--
-- IDEMPOTENTE: se puede ejecutar varias veces sin efecto.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- PASO 1 — COMPROBAR (solo lectura). Ejecutar esto ANTES en cada base.
--   Si las cuatro filas dicen "OK - ya ensanchado", no hay nada que hacer.
-- ----------------------------------------------------------------------------
SELECT c.table_name  AS tabla,
       c.column_name AS columna,
       c.character_maximum_length AS largo_actual,
       CASE WHEN c.character_maximum_length >= 300 THEN 'OK - ya ensanchado'
            ELSE 'FALTA - ejecutar el ALTER' END AS estado
  FROM information_schema.columns c
 WHERE c.table_schema = 'public'
   AND (c.table_name, c.column_name) IN (
         ('proveedores','direccion'),
         ('proveedores','razon_social'),
         ('proveedores','nombre_comercial'),
         ('productos','nombre'))
 ORDER BY 1, 2;


-- ----------------------------------------------------------------------------
-- PASO 2 — APLICAR. Solo cambia el catálogo: no reescribe las tablas ni valida
--   filas, así que es inmediato aunque productos tenga muchos registros.
-- ----------------------------------------------------------------------------
ALTER TABLE proveedores ALTER COLUMN direccion        TYPE VARCHAR(300);
ALTER TABLE proveedores ALTER COLUMN razon_social     TYPE VARCHAR(300);
ALTER TABLE proveedores ALTER COLUMN nombre_comercial TYPE VARCHAR(300);

ALTER TABLE productos   ALTER COLUMN nombre           TYPE VARCHAR(300);

COMMENT ON COLUMN proveedores.direccion IS
    'Dirección del proveedor. 300 caracteres: es el máximo que la ficha técnica del SRI admite en <dirMatriz>/<direccionProveedor>, de donde la toma el registro automático de comprobantes.';


-- ----------------------------------------------------------------------------
-- PASO 3 — VERIFICAR: repetir la consulta del PASO 1. Las cuatro filas deben
--   decir "OK - ya ensanchado".
-- ----------------------------------------------------------------------------
