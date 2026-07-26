---
titulo: Cuentas por pagar
resumen: Qué le debe la empresa a sus proveedores, con sus vencimientos, y registro del pago.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_pagar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por pagar, cxp, deudas, proveedores, saldo pendiente, vencimiento, pagar, obligaciones
version: 1.0
orden: 50
estado: activo
---

**Cuentas por pagar** es el espejo de las cuentas por cobrar: qué facturas de
compra siguen sin pagarse, de qué proveedor y cuándo vencen.

## De dónde sale el saldo

Del conjunto de:

- Las **compras** registradas y no pagadas.
- Los **saldos iniciales** de proveedores cargados al empezar.

Menos lo ya pagado mediante egresos.

El **vencimiento** se calcula con el *plazo* configurado en la ficha del
proveedor. Si un documento vence antes de lo que esperaba, ese es el campo a
revisar.

## Notas de crédito y débito del proveedor

Las notas de crédito y débito que emite el proveedor **no aparecen como
documentos sueltos** en este listado: se restan (o suman) directamente al saldo
de la factura que modifican. Así el listado muestra lo que realmente se le debe a
cada proveedor, y no tres líneas que hay que compensar mentalmente.

## Registrar el pago

Se registra desde el propio listado. Equivale a crear un egreso: reduce el saldo
del documento, deja constancia de la forma de pago y genera el asiento contable.

También queda disponible el **historial de pagos** de cada documento, útil cuando
una factura se pagó en varias partes.

## Errores frecuentes

- **Un documento aparece vencido antes de tiempo**: revise el *plazo* del
  proveedor.
- **El saldo no coincide con lo que dice el proveedor**: compruebe si hay notas
  de crédito aplicadas a esa factura.
- **Pagué y sigue pendiente**: verifique que el egreso quedó aplicado a ese
  documento y no registrado como concepto general.

## Historial de cambios

- **1.0** — Versión inicial.
