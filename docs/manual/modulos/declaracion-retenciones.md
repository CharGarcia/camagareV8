---
titulo: Declaración de retenciones
resumen: Declaración mensual de las retenciones practicadas, con su asiento y el egreso del pago.
categoria: Impuestos
ruta_modulo: modulos/declaracion_retenciones
tipo: modulo
visibilidad: todos
etiquetas: retenciones, declaracion de retenciones, formulario 103, renta, impuesto a la renta, empleados, pagar retenciones
version: 1.0
orden: 20
estado: activo
---

Este módulo arma la **declaración mensual de retenciones**: lo retenido a
proveedores en las compras y lo retenido a los empleados en relación de
dependencia.

## Las dos fuentes

- **Compras**: las retenciones practicadas a proveedores en el periodo, tomadas
  de los comprobantes de retención emitidos.
- **Empleados**: la retención del impuesto a la renta del personal en relación de
  dependencia, calculada según su proyección anual.

## El recorrido

1. **Cree la declaración** del periodo (año y mes, de 1 a 12).
2. **Calcule**: el sistema reúne ambas fuentes.
3. **Revise** los valores por casillero.
4. **Guarde** la declaración.
5. **Genere el asiento** contable.
6. **Genere el egreso** del pago.

## Con egreso generado, se bloquea

Como en la declaración de IVA: si ya existe el egreso del pago, la declaración
**no se puede modificar**. Hay que anular el egreso desde el módulo de Egresos.

Tampoco se puede generar un egreso de una declaración **sin valor a pagar**: si
el periodo no arroja saldo, no hay nada que pagar.

## Errores frecuentes

- **"Esta declaración ya tiene un egreso generado"**: anule el egreso primero.
- **"Esta declaración no tiene valor a pagar; no se puede generar un egreso"**: el
  periodo cerró sin saldo a pagar.
- **Faltan retenciones de compras**: compruebe que los comprobantes de retención
  del periodo estén emitidos y con la fecha correcta.

## Historial de cambios

- **1.0** — Versión inicial.
