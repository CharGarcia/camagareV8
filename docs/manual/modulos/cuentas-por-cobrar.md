---
titulo: Cuentas por cobrar
resumen: Qué le deben los clientes, desde cuándo, y registro del cobro sobre el mismo listado.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_cobrar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por cobrar, cxc, cartera, deudas de clientes, saldo pendiente, vencido, morosidad, cobrar
version: 1.0
orden: 40
estado: activo
---

**Cuentas por cobrar** es la cartera de la empresa: qué facturas siguen sin
cobrarse, de qué cliente y cuántos días llevan vencidas.

## De dónde sale el saldo

El listado se arma con:

- Las **facturas de venta** emitidas y no cobradas.
- Los **saldos iniciales** cargados al empezar a usar el sistema.

Y se descuenta lo que ya se cobró: cada ingreso que cobra una factura reduce su
saldo, igual que las notas de crédito y las retenciones que le practicó el
cliente.

## Días vencidos

Cada documento muestra los **días vencidos**, calculados desde su fecha de
vencimiento. Un documento con días vencidos en cero está pendiente pero todavía
en plazo.

Puede filtrar entre ver solo lo pendiente, solo lo vencido o todo.

## Registrar el cobro

El cobro se registra desde el propio listado, sin salir a otro módulo. Lo que se
registra aquí es exactamente lo mismo que un ingreso: reduce el saldo del
documento y genera su asiento.

## Por qué un saldo no cuadra

Casi siempre por una de estas tres razones, en este orden de frecuencia:

1. **Falta registrar la retención** que le practicó el cliente. La factura queda
   con ese saldo pendiente para siempre.
2. **Falta la nota de crédito** de una devolución ya acordada.
3. El cobro se registró **contra otro documento** del mismo cliente.

## Errores frecuentes

- **Un cliente aparece debiendo algo que ya pagó**: revise si el ingreso quedó
  aplicado a esa factura concreta.
- **El saldo es menor de lo esperado**: puede haber notas de crédito aplicadas.
- **No veo las facturas de otro vendedor**: sin el permiso de *acceso total*,
  cada usuario ve solo los documentos que él creó.

## Historial de cambios

- **1.0** — Versión inicial.
