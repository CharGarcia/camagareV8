---
titulo: Declaración de IVA
resumen: Cálculo de la declaración de IVA del periodo, su asiento y el egreso del pago.
categoria: Impuestos
ruta_modulo: modulos/declaracion_iva
tipo: modulo
visibilidad: todos
etiquetas: iva, declaracion de iva, formulario 104, impuesto, credito tributario, saldo a favor, pagar iva
version: 1.0
orden: 10
estado: activo
---

Este módulo arma la **declaración de IVA** del periodo a partir de las ventas y
compras registradas, y permite dejarla guardada con su asiento contable y el
egreso del pago.

## Periodo mensual o semestral

La declaración puede ser **mensual** o **semestral**, según cómo declare la
empresa. Al crearla se indica el tipo y el periodo:

- Mensual: año y **mes** (1 a 12).
- Semestral: año y **semestre**.

## El recorrido

1. **Cree la declaración** del periodo.
2. **Calcule**: el sistema toma las ventas y compras del periodo.
3. **Revise** las cifras contra sus registros.
4. **Guarde** la declaración.
5. **Genere el asiento** contable (es un paso aparte).
6. **Genere el egreso** del pago, eligiendo a quién se paga y con qué concepto.

## Saldo a favor

Cuando el periodo termina con **saldo a favor**, el sistema lo arrastra
automáticamente al periodo siguiente como crédito tributario. No hay que anotarlo
a mano.

## Con egreso generado, se bloquea

Una vez generado el egreso del pago, **la declaración no se puede modificar**. Si
necesita corregirla, primero hay que **anular el egreso desde el módulo de
Egresos**; el sistema lo indica con ese mismo mensaje.

Es la misma lógica de los décimos: no se cambia lo que ya se pagó.

## Errores frecuentes

- **"Esta declaración ya tiene un egreso generado"**: anule el egreso para poder
  modificarla.
- **"El tipo de período debe ser mensual o semestral"**: revise el tipo elegido.
- **Las cifras no coinciden con lo que espera**: compruebe que todas las facturas
  y compras del periodo estén registradas, y que ninguna tenga fecha fuera del
  periodo.

## Historial de cambios

- **1.0** — Versión inicial.
