---
titulo: Cheques posfechados en la contabilidad
resumen: Cómo se contabiliza un cheque con fecha futura, recibido o emitido, desde que se registra hasta que el banco lo cobra, y qué hacer si el banco lo devuelve (protesto).
categoria: Tesorería
tipo: guia
visibilidad: todos
etiquetas: cheque posfechado, cheques posfechados, cheque a fecha, cheque con fecha futura, cheques posfechados por cobrar, cheques posfechados por pagar, cuenta puente, cheque no cobrado, cheque en transito, cheque protestado, protesto, cheque devuelto, cheque rebotado, cheque sin fondos, fondos insuficientes, fecha banco, asiento de cobro, cobro del cheque, bancos no cuadra, factura pagada con cheque, cliente pago con cheque, reabrir factura, anular ingreso
version: 1.0
orden: 40
estado: activo
---

Un **cheque posfechado** es el que lleva una fecha posterior a la del ingreso o
egreso que lo registra: el cliente lo entrega hoy, pero el banco solo lo paga desde
la fecha escrita en el cheque. Mientras tanto ese dinero **no está en el banco**.

El sistema puede llevar esos cheques a una **cuenta puente** en lugar de a
Bancos, y pasarlos a Bancos recién cuando el banco los cobra. Así el saldo
contable del banco coincide con el extracto.

## Activarlo: las cuentas puente

En **Contabilidad → Configuración Contable**, tipo **Cobros y Pagos**, sección
**Cheques posfechados**, asigne:

| Concepto | Naturaleza | Para qué |
|---|---|---|
| Cheques posfechados por cobrar | Activo (Debe) | Cheques **recibidos** de clientes con fecha futura |
| Cheques posfechados por pagar | Pasivo (Haber) | Cheques **emitidos** a proveedores o empleados con fecha futura |

- Es **opcional**: sin cuenta, los cheques siguen yendo directo a Bancos, como
  siempre.
- Aplica a los ingresos y egresos con fecha **desde el día en que se asigna la
  cuenta** (la pantalla lo muestra como *Aplica desde dd-mm-aaaa*). Lo anterior no
  cambia: si hace falta, se ajusta con asientos manuales.
- Quitar la cuenta y volver a ponerla mueve esa fecha de inicio al nuevo día.

## Qué asientos genera

Ejemplo: el 03-10 un cliente paga una factura de $500 con un cheque fechado al
15-11.

| Momento | Asiento |
|---|---|
| **Ingreso** (03-10) | Debe *Cheques posfechados por cobrar* 500 / Haber *Cuentas por cobrar* 500 |
| **El banco lo cobra**: se registra la Fecha Banco en Control Bancario (p. ej. 16-11) | Debe *Bancos* 500 / Haber *Cheques posfechados por cobrar* 500, con fecha 16-11 |

Con un cheque **emitido** es el espejo: el egreso acredita *Cheques posfechados
por pagar* y, al registrar la Fecha Banco, el asiento de cobro pasa ese valor a
Bancos.

- El asiento de cobro tiene numeración propia (**CH-**) y aparece en el Libro
  Diario con origen *Cobro de cheque posfechado*.
- Si se **cambia** la Fecha Banco, el mismo asiento se mueve a la nueva fecha; si
  se **quita**, el asiento se anula. El mes de la Fecha Banco debe estar abierto.
- Un cheque **al día** (misma fecha que el ingreso o egreso) va directo a Bancos.

## La factura queda pagada, con un aviso

Con el ingreso registrado la factura figura **Pagada**: el cliente entregó el
cheque. En el listado de **Facturas de Venta**, junto al estado de pago aparece un
ícono de calendario mientras el banco no haya cobrado el cheque.

## Si el banco devuelve el cheque (protesto)

En **Control Bancario → Cheques Posfechados**, pestaña **Recibidos**, el botón
**Protestado** de la fila del cheque pide la fecha y el motivo y:

1. **Anula el ingreso** que registró el cheque. La factura vuelve a quedar
   **pendiente** en Cuentas por Cobrar, Cartera y Facturas de Venta.
2. Anula el asiento del ingreso: la cuenta por cobrar del cliente vuelve y la
   cuenta puente queda en cero.
3. Marca el cheque como **protestado**, con fecha y motivo (queda en el historial
   del sistema).

Condiciones:

- El **mes del ingreso** debe estar abierto. Si está cerrado, reábralo en
  **Contabilidad → Períodos Contables**, registre el protesto y vuelva a cerrarlo.
- El cheque no debe tener Fecha Banco. Si se la pusieron por error, quítela antes.
- Si el ingreso tiene **otras formas de cobro** además del cheque (por ejemplo
  efectivo y cheque), el sistema no sabe a qué documento devolver el monto: edite
  el ingreso, quite el cheque y ajuste lo cobrado de cada documento.
- Requiere permiso de modificar en Control Bancario y de eliminar en Ingresos.
- Las comisiones del banco o la multa al cliente se registran aparte (egreso o
  nota de débito).

## Editar o anular un ingreso o egreso con el cheque ya cobrado

- **Anular** el ingreso o egreso anula también el asiento de cobro de sus cheques.
- **Editar** no se permite mientras un cheque posfechado tenga Fecha Banco: al
  guardar se reemplazan las líneas de pago y el asiento de cobro quedaría suelto.
  Quite primero la Fecha Banco en Control Bancario, edite y vuelva a ponerla.

## Comprobar con la contabilidad

En **Control Bancario → Comprobar con Contabilidad**, un cheque posfechado no
aparece en la fecha del ingreso o egreso, sino como **Cobro de cheque posfechado**
en su Fecha Banco, cruzado con su asiento de cobro.

## Historial de cambios

- **1.0** — Versión inicial: cuentas puente de cheques posfechados, asiento de
  cobro con la Fecha Banco, protesto de cheques recibidos e indicador en Facturas de
  Venta.
