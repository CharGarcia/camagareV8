---
titulo: Roles de pago
resumen: Cálculo del sueldo de cada empleado en un periodo, con sus ingresos y descuentos.
categoria: Nómina
ruta_modulo: modulos/roles-pago
tipo: modulo
visibilidad: todos
etiquetas: rol de pago, roles, nomina, sueldo, quincena, semanal, mensual, pago de empleados, descuentos, liquido a recibir, observacion, observaciones, detalle de novedad, motivo del descuento
version: 1.2
orden: 30
estado: activo
---

El **rol de pago** calcula lo que cobra cada empleado en un periodo: el sueldo,
más lo que suman las novedades a favor, menos los descuentos.

## Tipos de rol

Un mismo módulo cubre los tres ritmos de pago:

| Tipo | Periodo | Dato adicional |
|------|---------|----------------|
| Mensual | Un mes completo | — |
| Quincenal | Media quincena | **Quincena**: 1 o 2 |
| Semanal | Una semana | **Semana**: de 1 a 5 |

El mes debe estar entre 1 y 12, y el año ser válido.

## Neteo entre roles

Cuando se paga por quincenas o semanas, lo ya entregado en el periodo se
descuenta del siguiente rol: el sistema **netea** para que el empleado no cobre
dos veces lo mismo. Por eso el orden de generación importa: primero la quincena
1, después la 2.

## De dónde salen las cifras

- El **sueldo** viene de la ficha del empleado.
- Los **ingresos y descuentos variables** vienen de las **novedades** del periodo:
  horas extra, faltas, anticipos, préstamos.
- Las **vacaciones** gozadas en el periodo se reflejan también.

Por eso, antes de generar un rol conviene revisar que todas las novedades del
periodo estén registradas: lo que no esté cargado, no se paga ni se descuenta.

## Ficha del empleado dentro del rol

Al abrir el detalle de un empleado en una corrida ya generada, el encabezado del
modal tiene un botón rojo para el **PDF** del rol individual y, junto a él, uno
verde para el **Excel**: una tabla Concepto/Ingreso/Egreso con los mismos
rubros del PDF, más el neto a recibir.

En el desglose de ingresos y egresos, cuando el rubro proviene de una novedad
(hora extra, anticipo, cuota de préstamo, falta, etc.) se muestra debajo del
concepto la **observación** que se escribió al registrar esa novedad, en letra
pequeña. Así se ve el motivo sin salir del rol. Si la novedad se guardó sin
observación, no aparece nada adicional. Lo mismo se imprime en el **PDF** del
rol individual del empleado.

En los **Excel** (el del rol completo y el de la ficha individual) la observación
se agrega dentro de la misma celda del concepto, separada por un guion:
`Horas extra 50% (6h) — cobertura del feriado`. Aplica tanto a la hoja principal
como a las hojas *Novedades* y *Otros Detalles*.

## Errores frecuentes

- **"La quincena debe ser 1 o 2"** / **"La semana debe estar entre 1 y 5"**:
  revise el periodo elegido.
- **Falta una hora extra en el rol**: la novedad está imputada a otro mes o año.
- **El líquido no coincide con lo esperado**: compare con las novedades del
  periodo; casi siempre es una novedad no registrada o imputada al periodo
  equivocado.

## Historial de cambios

- **1.2** — El desglose de ingresos/egresos del empleado (modal, PDF individual y Excel) muestra la observación de la novedad que originó cada rubro.
- **1.1** — Botón para exportar a Excel la ficha individual del empleado, junto al de PDF.
- **1.0** — Versión inicial.
