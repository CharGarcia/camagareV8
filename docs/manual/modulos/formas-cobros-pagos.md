---
titulo: Formas de cobro y pago
resumen: Medios por los que entra o sale el dinero: efectivo, bancos, tarjetas y anticipos.
categoria: Tesorería
ruta_modulo: modulos/formas_cobros_pagos
tipo: modulo
visibilidad: todos
etiquetas: formas de pago, formas de cobro, efectivo, caja, banco, tarjeta, payphone, anticipo, transferencia, cheque
version: 1.2
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

Al crear una empresa, las formas por defecto nacen **sin cuenta**, porque en ese
momento todavía no existe el plan de cuentas. Se les asigna sola al pulsar
**Cargar Plan Modelo** en [Plan de cuentas](plan-cuentas.md): Efectivo recibe
Caja General, y las dos de Anticipos sus cuentas de anticipo. **Tarjeta,
Payphone y Nuvei quedan a propósito sin cuenta**: por esas vías el dinero no
entra al instante ni por su valor nominal (llega con la liquidación del
procesador, ya neta de comisión), así que la cuenta se decide por empresa y se
asigna aquí a mano. Mientras no la tengan, un cobro por esas vías genera un
asiento sin la contrapartida del dinero.

**Una cuenta de tipo Banco/Cheque no puede compartirse con una forma que no
sea bancaria.** [Control Bancario](control-bancario.md) arma el mayor de una
cuenta bancaria filtrando directamente por esa cuenta contable, sin importar
qué forma de pago se usó. Si una forma de **Efectivo**, **Tarjeta** u otro tipo
no bancario reutiliza la cuenta de un banco, sus movimientos se mezclan en la
conciliación de ese banco como si fueran transacciones bancarias reales. El
sistema bloquea el guardado en ese caso con el mensaje "Esa cuenta contable ya
está asignada a...". Sí es válido que **dos formas bancarias** (Banco y
Cheque) compartan la misma cuenta: representan la misma cuenta física vista
por dos medios (p. ej. "Cheques Pichincha" y "Transferencias Pichincha").

## Errores frecuentes

- **No aparece al registrar un cobro**: puede estar inactiva, o ser de un tipo que
  no admite esa operación (Payphone no admite egresos).
- **"No se pudo determinar el saldo de la forma de pago de origen"** en un
  traspaso: es de tipo anticipo o está inactiva.
- **Los cobros van a la cuenta contable equivocada**: revise la cuenta asignada a
  esa forma de pago.
- **"Esa cuenta contable ya está asignada a..."**: está intentando guardar una
  forma no bancaria (Efectivo, Tarjeta...) con la misma cuenta contable que ya
  usa una forma de tipo Banco o Cheque. Elija una cuenta distinta para la forma
  no bancaria, o si en realidad es un movimiento de banco, cambie su tipo a
  Banco y complete los datos bancarios.
- **En Control Bancario aparecen movimientos que no son del banco** (de una
  forma Efectivo/Tarjeta, etc.): la causa es la anterior pero ya sucedida —
  edite esa forma de pago y cambie su cuenta contable a una que no sea la del
  banco (o conviértala a tipo Banco si de verdad corresponde a esa cuenta).

## Historial de cambios

- **1.2** — La carga del plan modelo ahora asigna sola la cuenta de las formas
  por defecto (Efectivo y las dos de Anticipos). Tarjeta, Payphone y Nuvei
  siguen sin cuenta a propósito.
- **1.1** — Nueva validación: una forma NO bancaria ya no puede guardar la
  misma cuenta contable que una forma de tipo Banco/Cheque (causaba que sus
  movimientos aparecieran mezclados en Control Bancario).
- **1.0** — Versión inicial.
