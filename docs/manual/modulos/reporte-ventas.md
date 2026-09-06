---
titulo: Reporte de ventas
resumen: Ventas del periodo con filtros por cliente, vendedor, producto y estado, agrupables y exportables.
categoria: Reportes
ruta_modulo: modulos/reporte_ventas
tipo: modulo
visibilidad: todos
etiquetas: reporte de ventas, ventas, cuanto vendi, por cliente, por vendedor, por producto, estadisticas, exportar, pdf, excel, establecimientos, sucursales, matriz, mismo ruc, consolidado por ruc
version: 1.2
orden: 10
estado: activo
---

El **reporte de ventas** responde a la pregunta de cuánto se vendió, a quién y de
qué, en el periodo que se indique.

## Filtros

| Filtro | Para qué |
|--------|----------|
| Fecha desde / hasta | El periodo a consultar |
| Cliente | Ventas de un cliente concreto |
| Vendedor | Ventas de un vendedor concreto. Las notas de crédito no llevan vendedor propio: se les atribuye el vendedor de la factura que modifican, así que sí entran en el filtro (también en *Facturas − NC*) |
| Producto | Ventas de un producto concreto |
| Estado | Borrador, autorizada o anulada |

Los filtros se combinan: *las ventas del producto X al cliente Y en marzo*.

## El estado importa

Es el filtro que más confunde y el que más cambia las cifras:

- **Autorizada**: la venta real, aprobada por el SRI. Es lo que hay que mirar
  para saber cuánto se vendió.
- **Borrador**: emitida pero aún no enviada. Todavía puede cambiar.
- **Anulada**: dejada sin efecto. No es venta.

Si el reporte no coincide con lo esperado, revise primero qué estados está
incluyendo.

## Consolidar varios establecimientos (solo desde la matriz)

Cuando un mismo RUC tiene varios establecimientos registrados como empresas
distintas (matriz y sucursales), el reporte muestra por defecto solo las ventas
de la empresa activa. Desde la **matriz** aparece el filtro **Establecimientos**,
el mismo que tienen Cuentas por Cobrar, Cuentas por Pagar y el Reporte
Consolidado:

- **Solo este (matriz)**: comportamiento normal.
- **Consolidado (N establec.)**: junta las ventas de todos los establecimientos
  del mismo RUC a los que el usuario tiene acceso. Tarjetas, tabla, gráfico, PDF
  y Excel consolidan de la misma forma; las agrupaciones (por cliente, producto,
  fecha o mes) suman los establecimientos en una sola fila.

Reglas:

- El filtro **solo aparece en la matriz** del grupo (la empresa marcada como
  matriz en *Empresas*) y solo si existe al menos otro establecimiento accesible.
- En el **detallado**, cada factura lleva un **badge con el código del
  establecimiento** (001, 002, …) delante del número. En el PDF y el Excel del
  detallado se agrega la columna **Estab.** y el encabezado indica *Alcance:
  Consolidado por RUC*.
- Los filtros **Cliente** y **Producto** se cruzan entre establecimientos por
  identificación y por código de producto (cada establecimiento tiene su propia
  lista). El filtro **Vendedor** es por establecimiento: en consolidado solo
  acota las ventas del establecimiento donde existe ese vendedor.
- En la agrupación **por producto**, un mismo producto puede aparecer una vez
  por establecimiento si su código no coincide entre ellos.
- Cada establecimiento se filtra por **su propio ambiente** (producción o
  pruebas), no por el de la matriz.

## Agrupación

Los resultados se pueden agrupar (por cliente, por producto, por periodo) para
pasar del detalle al resumen sin cambiar de pantalla. Es lo que permite ver de un
vistazo qué cliente compra más o qué producto rota mejor.

## Exportar

El reporte se exporta a **PDF** y **Excel**. El Excel es el que conviene cuando
se va a seguir analizando por fuera.

## Errores frecuentes

- **Las cifras no coinciden con la contabilidad**: revise el estado incluido; los
  borradores no son ventas y las anuladas no cuentan.
- **Falta una venta**: compruebe su fecha de emisión y que no esté anulada.
- **No veo las ventas de otros vendedores**: sin el permiso de *acceso total*
  cada usuario ve solo lo que registró.

## Historial de cambios

- **1.2** — Nuevo filtro **Establecimientos** (solo desde la matriz del grupo
  RUC) para consolidar las ventas de todas las sucursales del mismo RUC: badge
  del establecimiento en el detallado, columna "Estab." en PDF y Excel, y línea
  "Alcance" en el encabezado. Los filtros Cliente y Producto cruzan por
  identificación y código entre establecimientos.
- **1.1** — Selector de **Vendedor** en los filtros (segunda fila, antes de Producto); los campos de esa fila se compactaron para caber en una sola línea. Las notas de crédito toman el vendedor de la factura que modifican, tanto para el filtro como para la columna Vendedor del detallado.
- **1.0** — Versión inicial.
