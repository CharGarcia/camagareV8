---
titulo: Límite del plazo de crédito (máximo 10 años)
resumen: Por qué el plazo de crédito de clientes, proveedores, facturas de venta y compras no puede pasar de 3.650 días, y qué pasa con los plazos que llegan en el XML del proveedor.
categoria: Ventas
tipo: concepto
visibilidad: todos
etiquetas: plazo, plazo de credito, dias de credito, credito, vencimiento, fecha de vencimiento, maximo, limite, tope, 3650, 10 años, telefono en el plazo, timestamp out of range, error dashboard, cuentas por cobrar, cuentas por pagar, clientes, proveedores, facturas, compras
version: 1.0
orden: 6
estado: activo
---

El **plazo de crédito** (días que tiene el cliente para pagar, o que tiene la
empresa para pagarle al proveedor) admite como máximo **3.650 días (10 años)**.
El límite aplica a:

- la ficha del **cliente** (*Plazo de Crédito (Días)*),
- la ficha del **proveedor** (*Plazo*, en días, meses o años),
- los **días de crédito** y el plazo de las formas de pago de la **factura de venta**,
- los **días de crédito** de la **compra** registrada a mano.

## Por qué existe el límite

Con el plazo, el sistema calcula la **fecha de vencimiento** de cada documento
(fecha de emisión + días de crédito). Si en el campo se escribe por error un número
de teléfono (por ejemplo `0993024389`) o una fecha (`20261231`), el vencimiento queda
millones de años en el futuro. La base de datos no puede representar esa fecha y la
consulta falla con el error **«timestamp out of range»**, que dejaba sin cargar el
**Dashboard** y **Cuentas por Cobrar / por Pagar** de toda la empresa.

El plazo de la ficha del cliente se copia a cada factura que se le emite, así que un
solo dato mal escrito se repite en todas sus facturas.

## Qué ve el usuario

Al guardar un plazo mayor a 3.650 días aparece el mensaje:

> … el máximo permitido es 3.650 días (10 años). Revise que no se haya escrito un teléfono o una fecha en ese campo.

Corrija el valor y vuelva a guardar. Un plazo de 10 años en *años* o de 120 meses en
*meses* también es válido; lo que cuenta es el equivalente en días.

## Compras electrónicas (plazo que viene del XML)

En una compra electrónica el plazo no lo escribe el usuario: viene en el XML que el
proveedor emitió y el SRI autorizó, así que el sistema **no rechaza** el comprobante.
Si ese plazo pasa del límite, se guarda **en el tope** (3.650 días, o su equivalente
en meses o años). El XML del proveedor se conserva intacto.

## Documentos antiguos con un plazo fuera de rango

Los documentos registrados antes de este límite no se modifican solos. Para ubicarlos:

- en **Clientes**, busque `plazo:>3650`;
- en **Proveedores**, busque `plazo:>3650`;
- en **Facturas de Venta**, busque `dias_credito:>3650`.

Aunque queden datos así, Cuentas por Cobrar, Cuentas por Pagar y el Dashboard ya no
fallan: para calcular el vencimiento usan como máximo 100 años. Una factura con un
plazo fuera de rango no se puede volver a guardar hasta corregirlo.

## Historial de cambios

- **1.0** — Se crea el límite de 3.650 días para el plazo de crédito en clientes, proveedores, facturas de venta y compras.
