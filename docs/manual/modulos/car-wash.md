---
titulo: Servicio de car wash
resumen: Órdenes de lavado con tablero por estado, que luego se convierten en factura o recibo.
categoria: Servicios
ruta_modulo: modulos/car-wash
tipo: modulo
visibilidad: todos
etiquetas: car wash, lavado, lavadora de autos, orden de servicio, tablero, vehiculo, placa, estado del servicio, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion
version: 1.1
orden: 10
estado: activo
---

El módulo de **car wash** gestiona las órdenes de lavado: qué vehículo entró, qué
servicio se le hace, en qué estado está y quién lo atiende.

Está pensado para tablet: el tablero se maneja de pie, junto al vehículo.

## El tablero por estado

Las órdenes se organizan en columnas según su estado, de modo que de un vistazo
se sabe qué hay en cola, qué se está lavando y qué está listo para entregar.

Cambiar de estado es mover la orden: no hace falta abrir formularios para
actualizar el avance.

## El recorrido

1. **Recepción**: se registra el vehículo y el servicio solicitado.
2. **Proceso**: la orden avanza por los estados del tablero.
3. **Entrega y cobro**: la orden se convierte en **factura** o **recibo de
   venta**, según lo que pida el cliente.

## La orden no es el documento de venta

Una orden de lavado no es un comprobante: es el control interno del trabajo. La
venta existe cuando se genera la factura o el recibo a partir de ella.

## Errores frecuentes

- **La orden no aparece en ventas**: falta generar el documento de venta.
- **Una orden lleva días en el tablero**: se quedó sin cerrar; ciérrela o
  anúlela para que el tablero refleje la realidad.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Estos documentos no llevan una fecha de emisión editable: se numeran por la fecha
en que se registran. Los documentos ya emitidos conservan siempre el número que
tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Historial de cambios

- **1.1** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.0** — Versión inicial.
