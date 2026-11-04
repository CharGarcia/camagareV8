---
titulo: Reportes de producción
resumen: Lo producido por producto, el consumo de insumos teórico contra real, el detalle de las órdenes cerradas con su costo, rendimiento y merma, y el costo de producción mes a mes.
categoria: Producción
ruta_modulo: modulos/produccion-reportes
tipo: modulo
visibilidad: todos
etiquetas: reporte de produccion, cuanto se produjo, produccion por producto, consumo de materia prima, consumo teorico vs real, desperdicio, merma, rendimiento, costo de produccion, costo unitario, desvio de consumo, eficiencia, ordenes cerradas, excel de produccion, pdf de produccion, costo por mes, costo mensual, costos mensuales, costo de produccion mensual, comparativo mensual, mes a mes, tendencia de costos
version: 1.1
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

1. Elija el rango de fechas de cierre y, si quiere, un producto fabricado.
2. Pulse **Mostrar**.
3. Cambie de pestaña o descargue el PDF o el Excel.

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
