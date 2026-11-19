---
titulo: Reportes de producción
resumen: Lo producido por producto, el consumo de insumos teórico contra real, el detalle de las órdenes cerradas con su costo, rendimiento y merma, y el costo de producción mes a mes.
categoria: Producción
ruta_modulo: modulos/produccion-reportes
tipo: modulo
visibilidad: todos
etiquetas: filtros de produccion, personalizar reporte de produccion, pdf de produccion, reporte de produccion, cuanto se produjo, produccion por producto, consumo de materia prima, consumo teorico vs real, desperdicio, merma, rendimiento, costo de produccion, costo unitario, desvio de consumo, eficiencia, ordenes cerradas, excel de produccion, pdf de produccion, costo por mes, costo mensual, costos mensuales, costo de produccion mensual, comparativo mensual, mes a mes, tendencia de costos
version: 1.6
orden: 3
estado: activo
---

Resume la producción de un período a partir de las **órdenes cerradas** (por su
fecha de cierre). Como todo reporte del sistema, muestra solo documentos del
**ambiente de producción**.

## Qué es y para qué sirve

Tiene cuatro pestañas, cada una con PDF y Excel:

- **Producción por producto**: cuánto se obtuvo de cada producto (principal, coproducto, subproducto), contra lo teórico, con su costo y costo unitario.
- **Consumo de insumos**: lo consumido contra lo teórico de las fórmulas (que ya incluye la merma normal) y la diferencia en cantidad y en %.
- **Órdenes cerradas**: cada orden con lo planificado y producido, el rendimiento, la merma, los costos por concepto y el **desvío de consumo** valorado.
- **Costo por mes**: una fila por mes de cierre con el número de órdenes y los costos por concepto (materiales, mano de obra, CIF, subproductos, merma y costo total), más una fila **TOTAL**.

## Cómo se usa

1. Elija el rango de fechas de cierre y los filtros que necesite (ver la tabla de abajo).
2. Pulse **Mostrar**. **Limpiar** quita todos los filtros menos el período.
3. Cambie de pestaña o descargue el PDF o el Excel: ambos respetan los filtros y las columnas elegidas.

| Filtro | Qué hace |
|--------|----------|
| Cierre desde / hasta | Órdenes cerradas en ese período (por defecto, el mes en curso). |
| Fórmula | Buscador: escriba parte del código o del nombre y elija la fórmula. Solo las órdenes hechas con ella (incluye fórmulas inactivas). Borrar o Supr quita la fórmula elegida. |
| Bodega de destino | Bodega a la que entró lo producido. |
| Usuario | Quién registró la orden. Solo aparece para quien tiene acceso total. |
| Responsable | Texto del campo Responsable de la orden. |
| Producto fabricado | Buscador: escriba parte del código o del nombre y elija de la lista (solo productos que tienen fórmula u orden). Borrar o Supr quita el producto elegido. |
| Insumo | Buscador con lista (productos usados como insumo en fórmulas u órdenes). Muestra las órdenes que consumieron ese insumo y, en *Consumo de insumos*, solo ese insumo. Borrar o Supr lo quita. |

La tarjeta de arriba resume el período filtrado: órdenes cerradas, materiales, mano de obra + CIF, costo producido y merma anormal.

## Personalizar columnas y PDF

Un administrador (niveles 2 y 3) ve el botón **Personalizar** junto al título: elige, para **toda la empresa**, qué columnas salen en pantalla, Excel y PDF de cada pestaña, y si el PDF sale vertical u horizontal. En *Órdenes cerradas* hay además columnas opcionales apagadas por defecto: **Fórmula**, **Responsable** y **Bodega**. Ver [Personalizar reportes](guias/personalizar-reportes).

## El PDF

Tiene el mismo formato que los demás reportes: logo y nombre de la empresa, título y pestaña, la caja **Filtros aplicados**, los indicadores del período, el listado con sus columnas y una fila de **TOTALES** con los importes y el número de órdenes. *Producción por producto* y *Consumo de insumos* salen verticales; *Órdenes cerradas* y *Costo por mes*, horizontales (se puede cambiar con Personalizar).

## Costo por mes: ver la tendencia del costo de producción

Agrupa las órdenes cerradas por el **mes de su fecha de cierre**. Para comparar
varios meses, amplíe el rango de fechas (por defecto es el mes en curso). Los
meses sin órdenes cerradas no aparecen.

- **Materiales, Mano obra, CIF, Subprod., Costo merma y Costo total** son la suma
  de esos mismos valores de las órdenes del mes (los de la pestaña Órdenes
  cerradas), así que la fila TOTAL coincide con las tarjetas de arriba.
- **Producido** y **Costo unit.** solo se muestran cuando todas las órdenes del
  mes son del **mismo producto**: sumar unidades de productos distintos (kilos con
  litros, por ejemplo) no daría un dato real. Para ver el costo unitario mes a
  mes de un producto, escríbalo en el filtro de producto.

## Permisos

- **Ver**. Sin **acceso total**, el reporte incluye solo las órdenes que creó el usuario.

## Reglas de negocio

- Solo órdenes **cerradas** y del ambiente de **producción**.
- **Desvío de consumo** = (consumido − teórico) × costo unitario real de cada insumo. Si es positivo, se consumió más de lo previsto.

## Historial de cambios

- **1.0** — Versión inicial.
- **1.1** — Nueva pestaña **Costo por mes**: costo de producción por concepto, mes a mes, con fila de total.
- **1.2** — La pantalla ocupa todo el ancho de la ventana, como los demás módulos.
- **1.3** — Nuevos filtros (fórmula, bodega de destino, usuario, responsable e insumo), botón **Personalizar** (columnas de pantalla, Excel y PDF por empresa) y PDF con el formato común de reportes (logo, filtros aplicados, indicadores y totales).
- **1.4** — Se quitó el filtro Tipo (ya no existe la producción rápida); la Fórmula se elige con un buscador y todos los filtros y botones caben en una sola fila.
- **1.5** — El filtro Producto fabricado también se elige de una lista con buscador.
- **1.6** — El filtro Insumo también se elige de una lista con buscador.
