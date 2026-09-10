---
titulo: Notas de débito
resumen: Documento que aumenta el valor pendiente de una factura de venta ya autorizada (intereses, cargos, valores no cobrados en su momento).
categoria: Ventas
ruta_modulo: modulos/nota_debito
tipo: modulo
visibilidad: todos
etiquetas: nota de debito, notas de debito, cargo adicional, interes por mora, sri
version: 1.4
orden: 31
estado: activo
---

La **nota de débito** es el documento con el que se **aumenta** lo que un
cliente debe por una factura ya emitida: intereses por mora, gastos no
facturados en su momento, o cualquier valor adicional que corresponde cobrar
después. Es el reverso de la nota de crédito: mientras esta rebaja, la nota de
débito **suma** al saldo pendiente de la factura.

A diferencia de la factura o la nota de crédito, no tiene detalle de
productos: se sustenta en uno o más **motivos** (razón + valor) que en
conjunto forman el subtotal del documento.

## Solo sobre facturas autorizadas

Únicamente se puede emitir una nota de débito sobre una factura en estado
**autorizado**.

## Cómo se emite

1. Seleccione el cliente.
2. Elija la **factura o documento a modificar** (o escriba el número
   manualmente si no aparece en el listado).
3. Agregue uno o más **motivos** (razón + valor); su suma forma el subtotal.
4. Seleccione la **tarifa de IVA** aplicable, si corresponde.
5. Opcionalmente, agregue una o más **formas de pago**.
6. Revise el total y guarde.
7. Envíe al SRI.

## Editar y eliminar

Solo se pueden **editar** o **eliminar** notas de débito en estado
**borrador**. Una vez enviada y autorizada, el documento es definitivo ante el
SRI.

## Efecto en la cartera y la contabilidad

A diferencia de la nota de crédito, la nota de débito **no afecta
inventario** (no tiene productos). Sí **aumenta el saldo por cobrar** de la
factura relacionada (en Cuentas por Cobrar) y genera un asiento contable en el
mismo sentido que una factura: debita Cuentas por Cobrar y acredita Ventas
(más IVA Ventas si aplica).

## Exportar el documento

En la barra de acciones superior del modal, además de **PDF** y **XML**, hay
un botón **Excel** (icono verde) que descarga los motivos, totales y forma de
pago de esa nota de débito puntual. Solo se habilita con el documento ya
guardado.

## Exportar el listado

Los botones **Excel** y **PDF** de la parte superior del listado exportan las
notas de débito que coinciden con el buscador y el orden aplicados en ese
momento (no solo la página visible).

## Errores frecuentes

- **"Solo se pueden generar notas de débito para facturas en estado
  autorizado"**: la factura aún no fue autorizada por el SRI.
- **"Solo se pueden editar Notas de Débito en estado borrador"**: ya fue
  enviada.
- **"La suma de los pagos no coincide con el valor total"**: si se ingresan
  formas de pago, su suma debe cuadrar exactamente con el total del documento.

## La fecha de emisión y el envío al SRI

Para enviar al SRI, la **fecha de emisión debe ser la de hoy**. Si intenta enviar
la nota de débito fechada otro día, el envío se detiene antes de salir —sin gastar
un intento contra el SRI— con el aviso *"la fecha de emisión de la nota de débito
(…) debe ser la fecha actual"*. Ponga la fecha de hoy, guarde y vuelva a enviar.

## Períodos contables cerrados

Un documento que mueve cartera, inventario o contabilidad no puede tocar un
período ya cerrado. El sistema lo comprueba en las cuatro operaciones:

| Operación | Qué se comprueba |
|-----------|------------------|
| Emitir | Que la fecha de emisión no caiga en un período cerrado |
| Modificar | La fecha nueva **y** aquella con la que está registrado |
| Anular | La fecha del documento (anular revierte sus movimientos) |
| Eliminar | La fecha del documento |

Al modificar se revisan las dos fechas a propósito: mover un documento de un
mes cerrado a uno abierto lo alteraría igual. Los períodos se abren y se
cierran en **Contabilidad → Períodos Contables**; reabrir el período permite
la operación de inmediato.

## Historial de cambios

- **1.4** — El envío al SRI comprueba ahora que la **fecha de emisión sea la de
  hoy**, como ya hacían factura de venta y liquidación de compra. Antes el documento
  salía con cualquier fecha y era el propio SRI quien lo rechazaba.
- **1.3** — El módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar una nota de débito cuyo período esté cerrado. Antes no se
  comprobaba en ninguna de las cuatro operaciones.
- **1.2** — Corregido el botón **Nueva Nota de Débito**: después de enviar una
  nota al SRI, al crear la siguiente el modal conservaba datos de la anterior
  (estado en la pestaña **SRI**, ambiente y tipo de emisión, y el contenido de
  las pestañas **Asiento contable** y **Factura relacionada**), y quedaba
  abierto en la pestaña **SRI** si el envío había sido rechazado. Ya no hace
  falta recargar la pantalla: el modal arranca limpio y en borrador.
- **1.1** — Botón **Excel** en la barra de acciones del modal, para exportar
  motivos, totales y forma de pago de una nota de débito puntual.
- **1.0** — Versión inicial (emisión de notas de débito de venta).
