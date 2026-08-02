---
titulo: Notas de crédito
resumen: Documento que anula o rebaja total o parcialmente una factura de venta ya autorizada.
categoria: Ventas
ruta_modulo: modulos/notas_credito
tipo: modulo
visibilidad: todos
etiquetas: nota de credito, notas de credito, devolucion, descuento, anular factura, corregir factura, sri
version: 1.2
orden: 30
estado: activo
---

La **nota de crédito** es el documento con el que se corrige una factura ya
emitida: una devolución de mercadería, un descuento posterior o un error en el
valor facturado.

No confundir con anular: la anulación deja la factura sin efecto por completo; la
nota de crédito rebaja una parte (o el total) dejando el rastro de por qué.

## Solo sobre facturas autorizadas

Únicamente se puede emitir una nota de crédito sobre una factura en estado
**autorizado**. Si la factura todavía está en borrador o fue rechazada por el
SRI, corrija la factura directamente: no hace falta nota de crédito.

## No puede superar el total de la factura

La suma de **todas** las notas de crédito de una factura no puede exceder su
importe total. Si lo intenta, el sistema muestra el total acumulado y el de la
factura, y no deja guardar.

Esto contempla el caso de varias notas parciales: la tercera nota no puede
llevarse por delante lo que ya rebajaron las dos anteriores.

## Cómo se emite

1. Desde la factura, genere la nota de crédito.
2. Indique el **motivo** de la devolución o el ajuste.
3. Deje solo las líneas y cantidades que se devuelven o rebajan.
4. Revise el total.
5. Guarde y envíe al SRI.

## Editar y eliminar

Solo se pueden **editar** o **eliminar** notas de crédito en estado **borrador**.
Una vez enviada y autorizada, el documento es definitivo ante el SRI: si está
mal, hay que gestionarlo como cualquier comprobante autorizado erróneo.

## Efecto en el inventario y la cartera

Una nota de crédito por devolución **devuelve la mercadería al inventario** y
reduce el saldo por cobrar de esa factura.

## Exportar el documento

En la barra de acciones superior del modal, además de **PDF** y **XML**, hay
un botón **Excel** (icono verde) que descarga el detalle y los totales de esa
nota de crédito puntual. Igual que PDF y XML, solo se habilita cuando el
documento ya está guardado.

## Exportar el listado

Los botones **Excel** y **PDF** de la parte superior del listado exportan las
notas de crédito que coinciden con el buscador y el orden aplicados en ese
momento (no solo la página visible).

## Errores frecuentes

- **"Solo se pueden generar notas de crédito para facturas en estado
  autorizado"**: la factura aún no fue autorizada por el SRI.
- **"La suma de las notas de crédito excede el total de la factura"**: ya hay
  notas anteriores sobre esa factura; revise cuánto queda por rebajar.
- **"Solo se pueden editar Notas de Crédito en estado borrador"**: ya fue
  enviada.

## Historial de cambios

- **1.2** — Botón **Excel** en la barra de acciones del modal, para exportar
  el detalle y totales de una nota de crédito puntual (antes solo existía
  para el listado completo).
- **1.1** — Agregada la exportación a Excel y PDF del listado (no existía).
- **1.0** — Versión inicial.
