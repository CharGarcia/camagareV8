---
titulo: Retornos de consignación
resumen: Devolución de la mercadería consignada que el cliente no vendió.
categoria: Ventas
ruta_modulo: modulos/retornos-cv
tipo: modulo
visibilidad: todos
etiquetas: retorno, retornos, devolucion de consignacion, mercaderia no vendida, reingreso, saldo consignado, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion
version: 1.2
orden: 46
estado: activo
---

Un **retorno** es la devolución de la mercadería consignada que el cliente no
logró vender. Es la entrada espejo de la consignación: lo que salió del almacén
vuelve a entrar.

## Cómo funciona

1. Se elige el cliente y la consignación de la que devuelve.
2. Se indican los productos y cantidades que regresan.
3. Al registrar el retorno, la mercadería **vuelve al inventario**.

## Devoluciones parciales

No hace falta devolver todo de una vez: se pueden registrar varios retornos
parciales sobre la misma consignación. El sistema lleva el **saldo** de lo que
sigue en poder del cliente.

Ese saldo es el dato clave: cuadra siempre lo entregado con lo vendido más lo
devuelto.

## Exportar

En la barra de acciones del comprobante, junto al botón **PDF**, hay un botón
**Excel** que descarga el detalle del retorno (código, descripción, lote, NUP
y cantidad) en una hoja de cálculo. Requiere que el retorno esté guardado.

## Errores frecuentes

- **El saldo no cuadra**: revise si falta registrar un retorno o si hay
  mercadería vendida sin facturar.
- **El stock no subió**: compruebe la bodega de destino del retorno.
- **No aparece la consignación**: puede estar ya liquidada por completo.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Con ese modo activo, **al cambiar la fecha del documento su número se recalcula
solo**, para que caiga en el periodo correcto. Una vez guardado, el número queda
fijo aunque después se le cambie la fecha, y los documentos ya emitidos conservan
siempre el que tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Historial de cambios

- **1.2** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.1** — Nuevo botón **Excel** en la barra de acciones del comprobante,
  junto al de PDF: descarga el detalle del retorno en una hoja de cálculo.
- **1.0** — Versión inicial.
