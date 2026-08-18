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

### Cómo leer la columna Subtotal

La columna **Subtotal** de cada línea muestra el valor **neto: cantidad x precio
unitario, menos el descuento de esa línea, y sin IVA**. Es el mismo criterio de
la factura de venta y el mismo valor que viaja al XML del SRI y al RIDE.

En el pie, el **Subtotal** es el bruto (antes de descuentos) y el descuento se
resta en su propia línea, igual que en la factura de venta. Es decir: la suma de
la columna Subtotal del detalle equivale a **Subtotal menos (-) Descuento**. Si
la nota no tiene descuentos, ambos valores coinciden.

Los **Subtotal 15%**, **Subtotal 0%**, etc. del pie son bases imponibles netas:
ya tienen el descuento aplicado, de modo que el IVA que se muestra debajo es
exactamente el porcentaje de esa base.

## Si se cierra el modal sin guardar

Mientras se captura una nota de crédito nueva, el sistema va guardando un
borrador local en el navegador. Si el modal se cierra sin guardar (o si falla el
guardado por un corte de red), al volver a **Nueva nota de crédito** aparece un
aviso con el nombre del cliente y dos opciones: **Cargar borrador**, que repone
cliente, motivo, documento modificado, las líneas del detalle con sus cantidades,
precios, descuentos y tarifa de IVA, y la información adicional; o **Nueva
nota**, que descarta el borrador y empieza en blanco.

El borrador es por usuario y por empresa, y se descarta solo al guardar la nota
o al elegir "Nueva nota".

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

- **1.4** — Corregida la recuperación del borrador local: al elegir "Cargar
  borrador" el modal se abría vacío (el aviso además mostraba el cliente como
  "desconocido"). Ahora repone todos los campos y las líneas del detalle.
- **1.3** — Corregida la columna **Subtotal** del detalle en el modal: mostraba
  el valor con el IVA incluido, por lo que no cuadraba con el Subtotal del pie
  ni con el documento emitido. Ahora muestra el neto sin impuestos. Los
  subtotales por tarifa del pie pasan a calcularse sobre la base con descuento
  aplicado, y todos los importes se redondean a 2 decimales línea por línea.
- **1.2** — Botón **Excel** en la barra de acciones del modal, para exportar
  el detalle y totales de una nota de crédito puntual (antes solo existía
  para el listado completo).
- **1.1** — Agregada la exportación a Excel y PDF del listado (no existía).
- **1.0** — Versión inicial.
