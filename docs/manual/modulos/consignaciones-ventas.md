---
titulo: Consignaciones de venta
resumen: Mercadería entregada a un cliente que sigue siendo de la empresa hasta que se vende.
categoria: Ventas
ruta_modulo: modulos/consignaciones-ventas
tipo: modulo
visibilidad: todos
etiquetas: consignacion, consignaciones, mercaderia en consignacion, entrega, deposito, liquidar, facturar consignacion, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion
version: 1.2
orden: 45
estado: activo
---

Una **consignación** es mercadería entregada a un cliente que **sigue siendo de la
empresa** hasta que él la vende. No es una venta: es un traslado de custodia.

Por eso no genera factura al entregarla; la factura llega después, cuando el
cliente reporta lo vendido.

## El recorrido

1. **Entrega**: se registra qué se le deja al cliente.
2. **Seguimiento**: se controla qué le queda y qué vendió.
3. **Facturación**: lo vendido se factura desde el módulo de facturación de
   consignaciones.

## La entrega

Al registrar la entrega queda constancia de la mercadería, la fecha y el cliente.
Cuando la entrega se hace desde la aplicación móvil, se guarda además la
evidencia: ubicación, hora y firma de quien recibe.

Marcar una entrega como realizada desde la web también deja registro del usuario,
la hora y el canal.

## Contabilidad

Una consignación **no es una venta**, así que su asiento no registra ingresos: es
una **reclasificación de inventario a costo**, es decir, mercadería que sale del
almacén propio pero sigue siendo un activo de la empresa.

## Exportar

En la pestaña General del comprobante, junto al botón **PDF**, hay un botón
**Excel** que descarga el detalle de la consignación (producto, bodega, lote,
NUP, cantidad entregada, retornada y facturada) en una hoja de cálculo.
Requiere que la consignación esté guardada.

## Errores frecuentes

- **La consignación no aparece en ventas**: es correcto, no es una venta hasta
  que se factura.
- **El stock bajó pero no hay venta**: es el comportamiento esperado; la
  mercadería salió de la bodega.
- **No puedo facturar lo consignado**: use el módulo de facturación de
  consignaciones, no el de facturas de venta.

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
  junto al de PDF: descarga el detalle de la consignación en una hoja de
  cálculo.
- **1.0** — Versión inicial.
