---
titulo: Consignaciones de venta
resumen: Mercadería entregada a un cliente que sigue siendo de la empresa hasta que se vende.
categoria: Ventas
ruta_modulo: modulos/consignaciones-ventas
tipo: modulo
visibilidad: todos
etiquetas: consignacion, consignaciones, mercaderia en consignacion, entrega, deposito, liquidar, facturar consignacion
version: 1.0
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

## Errores frecuentes

- **La consignación no aparece en ventas**: es correcto, no es una venta hasta
  que se factura.
- **El stock bajó pero no hay venta**: es el comportamiento esperado; la
  mercadería salió de la bodega.
- **No puedo facturar lo consignado**: use el módulo de facturación de
  consignaciones, no el de facturas de venta.

## Historial de cambios

- **1.0** — Versión inicial.
