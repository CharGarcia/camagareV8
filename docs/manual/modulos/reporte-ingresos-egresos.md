---
titulo: Reporte de ingresos y egresos
resumen: Todo el movimiento de dinero del periodo en un solo listado, filtrable y exportable.
categoria: Reportes
ruta_modulo: modulos/reporte_ingresos_egresos
tipo: modulo
visibilidad: todos
etiquetas: reporte de ingresos y egresos, movimiento de dinero, cobros y pagos, por tercero, forma de pago, concepto, exportar
version: 1.0
orden: 30
estado: activo
---

Este reporte junta **ingresos y egresos en un mismo listado**, al nivel de cada
línea de detalle. Es la vista completa del movimiento de dinero: qué entró, qué
salió, de quién y por qué concepto.

## Filtros combinables

| Filtro | Para qué |
|--------|----------|
| Fechas | El periodo |
| Tercero | Un cliente, proveedor o empleado concreto |
| Forma de pago | Efectivo, banco, tarjeta… |
| Operación bancaria | Transferencia, cheque, depósito |
| Concepto | El motivo del ingreso o egreso |
| Tipo de documento | Qué documento originó el movimiento |
| Monto | Rango de valores |
| Texto libre | Búsqueda sobre la descripción |

Todos se combinan entre sí. La consulta típica —*todo lo que le pagué a este
proveedor por transferencia este trimestre*— sale con tres filtros.

## Dos formas de ver

- **Por detalle**: cada línea de cada documento. Es la vista de auditoría.
- **Por tercero**: agrupado por cliente, proveedor o empleado. Es la vista de
  resumen, para saber cuánto se movió con cada uno.

## Exportar

Disponible en **PDF** y **Excel**.

## Para qué se usa

Para responder preguntas que ningún otro módulo responde de una vez: cuánto se le
ha pagado en total a un proveedor, cuánto entró por tarjeta el mes pasado, qué
movimientos hubo por encima de cierto monto.

## Errores frecuentes

- **Falta un movimiento**: revise las fechas; el reporte usa la fecha del ingreso
  o egreso, no la del documento cobrado o pagado.
- **Los totales no cuadran con el flujo de caja**: compruebe si tiene filtros
  activos que estén acotando el resultado.

## Historial de cambios

- **1.0** — Versión inicial.
