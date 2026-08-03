---
titulo: Control bancario
resumen: Clasificación de los movimientos del banco y conciliación con lo registrado en el sistema.
categoria: Tesorería
ruta_modulo: modulos/control-bancario
tipo: modulo
visibilidad: todos
etiquetas: control bancario, conciliacion bancaria, estado de cuenta, banco, cheques, movimientos, cuadrar banco
version: 1.2
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

## Tipo de transacción detectado automáticamente

Si el movimiento no tiene una clasificación manual guardada, el sistema intenta
adivinar el **tipo de transacción** antes de mostrar "Otro":

1. Si el ingreso/egreso que originó la línea ya trae el dato específico
   (depósito, transferencia, cheque, débito), se usa ese.
2. Si no, pero la línea sí corresponde a un ingreso o egreso real (incluyendo
   los **migrados** del sistema anterior), se usa el tipo de la cuenta bancaria:
   cuenta de tipo cheque → "Cheque"; cuenta bancaria → "Depósito" si el dinero
   **entra** a la cuenta o "Transferencia" si **sale**.
3. Solo queda como **"Otro"** cuando el asiento no tiene ningún ingreso/egreso
   detrás (asientos manuales o del diario general migrado sin esa clasificación).

## Errores frecuentes

- **"Para un cheque debe indicar si fue emitido o recibido"**: falta ese dato.
- **"Debe indicar el número de cheque"**: es obligatorio para los movimientos de
  tipo cheque.
- **El saldo del banco no coincide con el contable**: revise los movimientos sin
  clasificar y los cheques girados que aún no se cobraron.
- **Movimientos migrados aparecían todos como "Otro"** (incluso depósitos):
  el enlace a los pagos migrados se buscaba por un dato que los migrados no
  siempre tienen, y las corridas de migración antiguas guardaron el dato de
  origen en mayúsculas en vez de minúsculas, así que la comparación nunca
  hacía match. Corregido; además ahora un movimiento de entrada sin
  clasificar dice "Depósito" en vez de "Transferencia".

## Historial de cambios

- **1.2** — Corrección: la comparación que enlaza un asiento migrado con su
  ingreso/egreso original ahora ignora mayúsculas/minúsculas (las corridas de
  migración antiguas guardaron ese dato en mayúsculas), así que los migrados
  también reciben el tipo automático. Además, un movimiento bancario de
  entrada sin clasificar ahora dice "Depósito" en vez de "Transferencia".
- **1.1** — Corrección: los movimientos de ingresos/egresos migrados ya no caen
  siempre en "Otro"; heredan el tipo (Transferencia/Cheque) de la cuenta bancaria.
- **1.0** — Versión inicial.
