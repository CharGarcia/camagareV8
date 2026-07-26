---
titulo: Formas de cobro y pago
resumen: Medios por los que entra o sale el dinero: efectivo, bancos, tarjetas y anticipos.
categoria: Tesorería
ruta_modulo: modulos/formas_cobros_pagos
tipo: modulo
visibilidad: todos
etiquetas: formas de pago, formas de cobro, efectivo, caja, banco, tarjeta, payphone, anticipo, transferencia, cheque
version: 1.0
orden: 70
estado: activo
---

Las **formas de cobro y pago** son los medios por los que el dinero entra o sale:
la caja, cada cuenta bancaria, las tarjetas, los anticipos. Cada una lleva su
propio saldo y su cuenta contable.

Son la base de los ingresos, los egresos y los traspasos: todo movimiento de
dinero indica por qué forma pasó.

## Tipos

El tipo determina cómo se comporta la forma de pago:

| Tipo | Uso |
|------|-----|
| Efectivo | Caja física |
| Tarjeta | Cobro con tarjeta presente (datáfono). Sirve para cobros y pagos |
| Payphone | Cobro en línea. **Solo para ingresos** |
| Anticipo | Dinero entregado por adelantado, que después se aplica a documentos |

La separación entre **Tarjeta** y **Payphone** es deliberada: una es la tarjeta
física en el local y la otra el cobro en línea, y no se comportan igual.

## Anticipos

Una forma de pago de tipo **anticipo** no tiene un saldo bancario: acumula lo
entregado por adelantado (por un cliente o a un proveedor) y se va consumiendo a
medida que se aplica a documentos.

Su saldo es lo recibido menos lo ya aplicado. Por eso una forma de tipo anticipo
no sirve como origen de un traspaso.

## Cuenta contable

Cada forma de pago apunta a una cuenta contable. Es lo que hace que un cobro en
efectivo y uno por banco terminen en cuentas distintas sin que nadie lo indique
en cada documento.

## Errores frecuentes

- **No aparece al registrar un cobro**: puede estar inactiva, o ser de un tipo que
  no admite esa operación (Payphone no admite egresos).
- **"No se pudo determinar el saldo de la forma de pago de origen"** en un
  traspaso: es de tipo anticipo o está inactiva.
- **Los cobros van a la cuenta contable equivocada**: revise la cuenta asignada a
  esa forma de pago.

## Historial de cambios

- **1.0** — Versión inicial.
