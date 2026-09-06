---
titulo: Reporte consolidado de transacciones
resumen: Compras, ventas, retenciones, notas de crédito/débito y liquidaciones en un solo reporte, con Excel detallado por hoja.
categoria: Reportes
ruta_modulo: modulos/reporte_consolidado
tipo: modulo
visibilidad: todos
etiquetas: reporte consolidado, todas las transacciones, resumen general, compras, ventas, facturas, recibos, retenciones, notas de credito, notas de debito, liquidaciones de compra, cierre de periodo, establecimientos, sucursales, matriz, mismo ruc, consolidado por ruc
version: 1.1
orden: 51
estado: activo
---

Este reporte junta en una sola pantalla, un solo PDF y un solo Excel los 8 tipos
de documentos transaccionales del sistema, para tener la foto completa de un
período sin abrir cada reporte por separado.

## Qué es y para qué sirve

Sirve para una revisión general de un período (por ejemplo, antes de cerrar un
mes o entregar información al contador): cuántos documentos hubo de cada tipo,
cuánto suman, y el detalle línea por línea de cada uno en el Excel. No reemplaza
a los reportes individuales (Reporte de Ventas, Reporte de Compras, Reporte de
Retenciones, etc.) — estos siguen siendo la fuente para análisis más específicos
(por producto, por vendedor, por agrupación); el consolidado es la vista de
conjunto.

## Consolidar varios establecimientos (solo desde la matriz)

Cuando un mismo RUC tiene varios establecimientos registrados como empresas
distintas (matriz y sucursales), el reporte muestra por defecto solo los
documentos de la empresa activa. Desde la **matriz** aparece el filtro
**Establecimientos**, el mismo que tienen Cuentas por Cobrar y Cuentas por
Pagar:

- **Solo este (matriz)**: comportamiento normal.
- **Consolidado (N establec.)**: junta los documentos de todos los
  establecimientos del mismo RUC a los que el usuario tiene acceso. Las tarjetas
  (documentos, ventas, compras, neto), la tabla, el PDF y las ocho hojas del
  Excel consolidan de la misma forma.

Reglas:

- El filtro **solo aparece en la matriz** del grupo (la empresa marcada como
  matriz en *Empresas*) y solo si existe al menos otro establecimiento accesible.
  En una sucursal no se muestra.
- Un usuario que no es superadministrador solo ve los establecimientos que tiene
  asignados.
- En la tabla, cada documento lleva un **badge con el código del
  establecimiento** (001, 002, …) delante del número; al pasar el mouse se ve el
  nombre de la empresa. En el PDF y en cada hoja del Excel se agrega la columna
  **Estab.** y el encabezado indica *Alcance: Consolidado por RUC* con la lista
  de establecimientos.
- Cada establecimiento se filtra por **su propio ambiente** (producción o
  pruebas), no por el de la matriz.
- El selector **Año** sigue tomando los años con documentos de la empresa
  activa; con Fecha Desde / Fecha Hasta se puede consultar cualquier período.

## Qué documentos incluye y de dónde sale cada uno

| Hoja / grupo | Fuente |
|---|---|
| Compras | Facturas de compra (`compras_cabecera`, tipo `01`) |
| Retenciones de Compra | Retenciones que la empresa practicó a sus proveedores |
| Facturas de Venta | Facturas emitidas a clientes |
| Recibos de Venta | Recibos de venta (documento interno, sin autorización SRI) |
| Retenciones de Venta | Retenciones que los clientes le practicaron a la empresa |
| Notas de Crédito | Las que la empresa emite a clientes **y** las que recibe de proveedores, en la misma hoja (columna "Origen" distingue Venta/Compra) |
| Notas de Débito | Igual que Notas de Crédito: emitidas y recibidas juntas, distinguidas por "Origen" |
| Liquidaciones de Compra | Liquidaciones de compra (documento propio, no es una compra normal) |

Las Notas de Crédito y Débito **recibidas de proveedores** se registran en el
sistema como una compra especial (`compras_cabecera` con `tipo_comprobante`
`04`/`05`) — por eso no aparecen también dentro de la hoja "Compras": ésta se
limita a las facturas de compra normales para que ningún monto se cuente dos
veces entre hojas.

## Filtros

- **Rango de fechas** (obligatorio, por defecto el mes en curso).
- **Buscar**: nombre o identificación del tercero, o número de documento.
- **Incluir anulados**: desactivado por defecto — los documentos anulados no sirven
  para un cuadre financiero, pero puede activarse si se necesita verlos.
- **Documentos a incluir**: casillas para armar el reporte solo con un
  subconjunto de los 8 tipos (por ejemplo, solo lo relacionado a ventas).

## Cómo se usa

1. Ajuste el rango de fechas y, si hace falta, los demás filtros.
2. Haga clic en **Buscar**. La tabla y los indicadores (documentos, total ventas,
   total compras, neto) se actualizan.
3. La tabla en pantalla y el PDF muestran un resumen a nivel de documento
   (cabecera). Para ver el detalle línea por línea (productos, impuestos,
   retenciones), descargue el **Excel**.

## Exportar

- **PDF**: resumen con indicadores por tipo de documento y el listado a nivel de
  cabecera (una fila por documento).
- **Excel**: un libro con **8 hojas**, una por tipo de documento, cada una con el
  detalle línea por línea (producto, cantidad, impuestos, retenciones, etc.)
  según lo que tenga esa fuente.

## Permisos

Solo requiere permiso de **ver** (`r`) sobre el módulo — es un reporte de solo
lectura, no crea, modifica ni elimina nada. No aplica la distinción de
"registros propios" (§6 de las reglas del sistema): siempre muestra los
documentos de toda la empresa a quien tenga acceso al reporte.

## Errores frecuentes

- **Un documento no aparece**: revise que su casilla esté marcada en
  "Documentos a incluir" y que no esté anulado (o marque "Incluir anulados").
- **El total no cuadra con un reporte individual**: los reportes individuales
  (Compras, Ventas) pueden tener otros filtros por defecto (por ejemplo,
  incluir NC de compra dentro del mismo total). Compare con el mismo rango de
  fechas y revise el desglose por tipo antes de asumir una diferencia real.

## Historial de cambios

- **1.1** — Nuevo filtro **Establecimientos** (solo desde la matriz del grupo
  RUC) para consolidar los documentos de todas las sucursales del mismo RUC:
  badge del establecimiento en la tabla, columna "Estab." en el PDF y en las
  hojas del Excel, y línea "Alcance" en el encabezado.
- **1.0** — Versión inicial.
