---
titulo: Ingresos
resumen: Registro del dinero que entra: cobros de facturas, anticipos y otros ingresos, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/ingresos
tipo: modulo
visibilidad: todos
etiquetas: ingresos, cobro, cobrar, recibo, dinero que entra, anticipo, deposito, efectivo, transferencia, caja
version: 1.0
orden: 10
estado: activo
---

El módulo de **Ingresos** registra todo el dinero que entra a la empresa: el
cobro de una factura, un anticipo de cliente o cualquier otro ingreso. Cada
ingreso genera su asiento contable y, cuando cobra documentos, reduce las cuentas
por cobrar.

## Las tres partes de un ingreso

Un ingreso siempre tiene tres partes y **las tres deben cuadrar entre sí**:

1. **Cabecera**: fecha, secuencial, tipo de ingreso y de quién se recibe.
2. **Detalle**: qué se está cobrando (documentos pendientes o conceptos libres).
3. **Formas de cobro**: cómo entró el dinero (efectivo, transferencia, cheque…).

La suma del detalle y la suma de las formas de cobro deben ser **iguales al total
del ingreso**. Si no cuadran, el sistema no deja guardar y dice exactamente qué
suma no coincide.

## Cómo se registra un cobro

1. Pulse **Nuevo**.
2. Revise la fecha y el secuencial (se propone el siguiente).
3. Elija el **tipo de ingreso** y complete **Recibo de** (de quién viene el dinero).
4. En el detalle, busque los **documentos pendientes** del cliente y marque los
   que está cobrando, total o parcialmente.
5. En formas de cobro, indique cómo entró el dinero. Puede combinar varias.
6. Guarde.

Si es un ingreso que no cobra ninguna factura, elija el **concepto** que
corresponda en lugar de documentos pendientes.

## Campos obligatorios

| Campo | Regla |
|-------|-------|
| Fecha de emisión | Obligatoria |
| Secuencial | Obligatorio |
| Tipo de ingreso | Obligatorio |
| Recibo de | Obligatorio |
| Concepto | Obligatorio cuando es "otros ingresos" |
| Detalle | Al menos una línea, con monto mayor a cero |
| Formas de cobro | Al menos una, con monto mayor a cero |
| Total | Mayor a cero |

## El periodo contable manda

No se puede **registrar, modificar ni anular** un ingreso si su periodo contable
está cerrado. El sistema lo comprueba en las tres operaciones, y en la
modificación valida tanto el periodo original como el nuevo si se cambia la
fecha.

Si necesita corregir un ingreso de un periodo cerrado, hay que reabrir el periodo
desde Periodos Contables (con el criterio del contador) o registrar el ajuste en
un periodo abierto.

## Anular, no eliminar

Un ingreso registrado **se anula**, no se borra. Al anularlo:

- Se libera el saldo de los documentos que había cobrado.
- Se anula su asiento contable.

**Caso especial — pagos con tarjeta**: si el ingreso vino de un cobro con
tarjeta, no se puede anular desde aquí. Primero hay que **reversar el pago desde
la factura, en la pestaña Pagos**; al hacerlo, el ingreso se anula solo. El
sistema lo avisa con ese mensaje si lo intenta al revés.

## Asiento contable

Cada ingreso genera su asiento automáticamente según la configuración contable de
la empresa. Al modificarlo, el asiento se regenera; al anularlo, se anula.

En las líneas de concepto general se puede elegir la cuenta contable por línea,
cuando el concepto no tiene una cuenta fija.

## Permisos

Con **acceso total** se ven los ingresos de toda la empresa; sin él, cada usuario
ve solo los que registró. En una caja con varios turnos esto suele ser lo
deseable; para el contador o el administrador, active el acceso total.

## Errores frecuentes

- **"La suma de los detalles no coincide con el total"**: revise las líneas del
  detalle; suele faltar un documento o sobrar un centavo por redondeo.
- **"La suma de las formas de cobro no coincide con el total"**: el dinero
  declarado no llega al total cobrado. Ajuste los montos por forma de pago.
- **"El periodo contable está cerrado"**: la fecha cae en un mes ya cerrado.
- **"Debes reversar el pago con tarjeta primero"**: vaya a la factura, pestaña
  Pagos, y reverse ahí.
- **No encuentro la factura a cobrar**: compruebe que está a nombre de ese
  cliente, que no está ya cobrada y que no fue anulada.

## Historial de cambios

- **1.0** — Versión inicial.
