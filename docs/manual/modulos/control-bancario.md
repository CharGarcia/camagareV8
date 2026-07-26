---
titulo: Control bancario
resumen: Clasificación de los movimientos del banco y conciliación con lo registrado en el sistema.
categoria: Tesorería
ruta_modulo: modulos/control-bancario
tipo: modulo
visibilidad: todos
etiquetas: control bancario, conciliacion bancaria, estado de cuenta, banco, cheques, movimientos, cuadrar banco
version: 1.0
orden: 60
estado: activo
---

El **control bancario** sirve para cuadrar lo que dice el banco con lo que dice
el sistema: se cargan los movimientos del estado de cuenta, se clasifican y se
concilian contra los documentos registrados.

## Clasificar un movimiento

Cada movimiento del banco necesita:

| Dato | Regla |
|------|-------|
| Movimiento a clasificar | Obligatorio |
| Cuenta bancaria | Obligatoria |
| Tipo de transacción | Debe ser uno de los válidos |

## Cheques

Si el movimiento es un **cheque**, hacen falta dos datos más:

- Si fue **emitido o recibido**.
- El **número de cheque**.

Sin esos dos datos el sistema no deja clasificarlo, porque son los que permiten
cruzarlo con el pago o el cobro correspondiente.

## Para qué sirve conciliar

Un movimiento en el banco que no está en el sistema significa que falta registrar
algo: un cobro, un pago, una comisión. Al revés, un pago registrado que no aparece
en el banco puede ser un cheque no cobrado todavía.

La conciliación es lo que convierte el saldo contable en un saldo en el que se
puede confiar.

## Errores frecuentes

- **"Para un cheque debe indicar si fue emitido o recibido"**: falta ese dato.
- **"Debe indicar el número de cheque"**: es obligatorio para los movimientos de
  tipo cheque.
- **El saldo del banco no coincide con el contable**: revise los movimientos sin
  clasificar y los cheques girados que aún no se cobraron.

## Historial de cambios

- **1.0** — Versión inicial.
