---
titulo: Reporte consolidado de transacciones
resumen: Compras, ventas, retenciones, notas de crédito/débito y liquidaciones en un solo reporte, con Excel detallado por hoja.
categoria: Reportes
ruta_modulo: modulos/reporte_consolidado
tipo: modulo
visibilidad: todos
etiquetas: reporte consolidado, todas las transacciones, resumen general, compras, ventas, facturas, recibos, retenciones, notas de credito, notas de debito, liquidaciones de compra, cierre de periodo, establecimientos, sucursales, matriz, mismo ruc, consolidado por ruc, notas de venta, rise, personalizar columnas, pdf con logo
version: 1.4
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
| Compras | Todos los comprobantes de compra que son deuda con el proveedor: facturas (`01`), **notas de venta** (`02`), documentos de instituciones financieras (`12`), servicios básicos (`18`), comprobantes por reembolso (`41`), etc. La columna Tipo muestra el nombre del comprobante cuando no es factura |
| Retenciones de Compra | Retenciones que la empresa practicó a sus proveedores |
| Facturas de Venta | Facturas emitidas a clientes |
| Recibos de Venta | Recibos de venta (documento interno, sin autorización SRI) |
| Retenciones de Venta | Retenciones que los clientes le practicaron a la empresa |
| Notas de Crédito | Las que la empresa emite a clientes **y** las que recibe de proveedores, en la misma hoja (columna "Origen" distingue Venta/Compra) |
| Notas de Débito | Igual que Notas de Crédito: emitidas y recibidas juntas, distinguidas por "Origen" |
| Liquidaciones de Compra | Liquidaciones de compra (documento propio, no es una compra normal) |

Las Notas de Crédito y Débito **recibidas de proveedores** se registran en el
sistema como una compra especial (`compras_cabecera` con `tipo_comprobante`
`04`/`05`) — por eso no aparecen también dentro de la hoja "Compras", para que
ningún monto se cuente dos veces entre hojas. La guía de remisión (`06`) y el
comprobante de retención (`07`) tampoco van en "Compras": no son compras.

El reporte **siempre incluye los 8 tipos** de documento del período elegido; no
hay casillas para excluir alguno.

## Filtros

- **Rango de fechas** (obligatorio, por defecto el mes en curso).
- **Buscar**: nombre o identificación del tercero, o número de documento.
- **Incluir anulados**: desactivado por defecto — los documentos anulados no sirven
  para un cuadre financiero, pero puede activarse si se necesita verlos.

Cambiar un filtro **no** recarga el reporte: los documentos se consultan solo al
pulsar **Mostrar**.

## Cómo se usa

1. Ajuste el período (Año / Mes o Fecha Desde / Fecha Hasta) y, si hace falta,
   los demás filtros.
2. Haga clic en **Mostrar**. La tabla y los indicadores (documentos, total
   ventas, total compras, neto) se actualizan. Al abrir el módulo la tabla está
   vacía hasta pulsar Mostrar.
3. La tabla en pantalla y el PDF muestran un resumen a nivel de documento
   (cabecera). Para ver el detalle línea por línea (productos, impuestos,
   retenciones), descargue el **Excel**.

## Personalizar columnas (botón «Personalizar»)

Los administradores (niveles 2 y 3) ven el botón **Personalizar** junto al
título. Ahí deciden, **para toda la empresa**, qué columnas salen en la
**pantalla** y en el **PDF** (Tipo, Fecha, Tercero, Identificación, Subtotal,
IVA, Total, Estado; el Número siempre se muestra) y la orientación del PDF. Si
la columna Identificación no se muestra en pantalla, la identificación aparece
debajo del nombre del tercero. El Excel no cambia: es el detalle por hoja. Guía
general: *Personalizar reportes*.

## Exportar

- **PDF**: con el mismo diseño que los demás reportes — logo y nombre de la
  empresa, caja *Filtros aplicados*, indicadores por tipo de documento (cantidad
  y total), indicadores de ventas, compras y neto, y el listado a nivel de
  cabecera (una fila por documento) con las columnas elegidas en Personalizar.
  Incluye todos los documentos del período (sin tope de filas).
- **Excel**: un libro con **8 hojas**, una por tipo de documento, cada una con el
  detalle línea por línea (producto, cantidad, impuestos, retenciones, etc.)
  según lo que tenga esa fuente.

## Permisos

Solo requiere permiso de **ver** (`r`) sobre el módulo — es un reporte de solo
lectura, no crea, modifica ni elimina nada. No aplica la distinción de
"registros propios" (§6 de las reglas del sistema): siempre muestra los
documentos de toda la empresa a quien tenga acceso al reporte.

## Errores frecuentes

- **Un documento no aparece**: revise que su fecha de emisión esté dentro del
  período, que no esté anulado (o marque "Incluir anulados") y que sea de
  producción (los de pruebas no salen en los reportes).
- **La tabla está vacía al entrar**: es normal; pulse **Mostrar**.
- **El total no cuadra con un reporte individual**: los reportes individuales
  (Compras, Ventas) pueden tener otros filtros por defecto (por ejemplo,
  incluir NC de compra dentro del mismo total). Compare con el mismo rango de
  fechas y revise el desglose por tipo antes de asumir una diferencia real.

## Historial de cambios

- **1.4** — Las **notas de venta** y demás comprobantes de compra (instituciones financieras, servicios básicos, reembolsos…) ahora se incluyen en Compras (antes solo las facturas). Se quitaron las casillas *Documentos a incluir*: siempre van todos los documentos del período. El reporte se consulta solo al pulsar **Mostrar** (antes «Buscar», y cargaba solo). Nuevo botón **Personalizar** para elegir columnas de pantalla y PDF. El PDF usa el diseño estándar con logo, filtros aplicados e indicadores. El Excel de Compras agrega la columna *Tipo Comprobante*.

- **1.3** — El reporte muestra solo documentos de **producción**, aunque la empresa esté configurada en pruebas (ver el artículo *Los reportes solo muestran documentos de producción*).
- **1.2** — Corregido: en el detalle de retenciones, una línea cuyo código aparece más de una
  vez en el catálogo del SRI salía **duplicada**. Además, el concepto de las retenciones de
  renta se toma por su **código ATS** (antes podía mostrar otro concepto, p. ej. 323I en lugar
  de 323).

- **1.1** — Nuevo filtro **Establecimientos** (solo desde la matriz del grupo
  RUC) para consolidar los documentos de todas las sucursales del mismo RUC:
  badge del establecimiento en la tabla, columna "Estab." en el PDF y en las
  hojas del Excel, y línea "Alcance" en el encabezado.
- **1.0** — Versión inicial.
