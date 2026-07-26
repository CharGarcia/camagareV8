---
titulo: Reporte de ventas
resumen: Ventas del periodo con filtros por cliente, producto y estado, agrupables y exportables.
categoria: Reportes
ruta_modulo: modulos/reporte_ventas
tipo: modulo
visibilidad: todos
etiquetas: reporte de ventas, ventas, cuanto vendi, por cliente, por producto, estadisticas, exportar, pdf, excel
version: 1.0
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

- **1.0** — Versión inicial.
