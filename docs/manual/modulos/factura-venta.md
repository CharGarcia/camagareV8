---
titulo: Facturas de Venta
resumen: Emisión de facturas electrónicas: creación, envío al SRI, PDF, correo y anulación.
categoria: Ventas
ruta_modulo: modulos/factura-venta
tipo: modulo
visibilidad: todos
etiquetas: factura, facturar, venta, sri, comprobante electronico, xml, anular, nota de credito, whatsapp, link de pago, payphone, nuvei
version: 1.1
orden: 20
estado: activo
---

El módulo de **Facturas de Venta** emite los comprobantes electrónicos de venta y
gestiona su ciclo completo: creación, autorización en el SRI, entrega al cliente
y anulación.

## Antes de empezar

Para emitir facturas electrónicas la empresa necesita tener configurado:

- Los datos tributarios de la empresa y su establecimiento.
- La **firma electrónica** vigente.
- El **secuencial** del documento y el ambiente (pruebas o producción).
- El cliente y los productos o servicios que va a facturar.

## Crear una factura

1. Pulse **Nuevo**.
2. Elija el cliente. Si no existe, regístrelo primero en el módulo de Clientes.
3. Agregue las líneas de detalle: producto, cantidad, precio y descuento.
4. Revise los totales y el IVA calculado.
5. Guarde la factura.

Una factura guardada queda en borrador hasta que se envía al SRI.

## Barra de acciones del documento

En la parte superior del formulario están las acciones sobre el documento ya
guardado: generar el **PDF**, ver el **XML**, enviarlo por **correo** o por
**WhatsApp** y remitirlo al **SRI**. Cada acción comprueba primero que la factura
esté guardada.

## Enviar la factura y el enlace de pago por WhatsApp

El botón de WhatsApp pide la plantilla aprobada y el número del cliente. Con las
plantillas de factura, el mensaje sale con el **PDF adjunto**.

Además hay dos plantillas especiales que, en lugar del PDF, envían un **enlace de
pago con tarjeta**: `link_pago_payphone` y `link_pago_nuvei`, una por cada
pasarela. Al elegirlas, el sistema muestra el **saldo pendiente** de la factura y
genera el enlace por ese valor:

- Solo se ofrecen si la factura está **autorizada** y tiene saldo pendiente.
- El enlace siempre es por el **total pendiente**: por esta vía no se cobra
  parcialmente.
- No se envía un segundo enlace si ya hay uno pendiente de los últimos **15
  minutos**.
- Requiere una **forma de cobro** de ese tipo (Payphone o Nuvei) activa y
  configurada en la empresa.
- El enlace de Payphone es de un solo uso y caduca a los pocos minutos; el de
  Nuvei se renueva cada vez que el cliente lo abre y deja de servir cuando el
  pago se registra.

Cuando el cliente paga, el cobro aparece en la pestaña **Pagos** de la factura,
igual que si el enlace se hubiera enviado por correo. Las plantillas se crean en
el módulo de Plantillas de WhatsApp.

## Envío al SRI

Al enviar, el sistema firma el XML y lo transmite al Servicio de Rentas Internas.
El comprobante puede quedar autorizado o devuelto con observaciones. Si es
devuelto, corrija lo que indique el mensaje y vuelva a enviar.

Cuando hay muchos documentos pendientes conviene usar el envío en lote, que los
procesa en segundo plano.

## Anular una factura

Una factura **autorizada** no se elimina: se anula, y esa anulación se informa al
SRI. Si lo que se necesita es corregir valores o devolver mercadería, el
documento correcto es una **nota de crédito**, no la anulación.

Anular una factura revierte también los movimientos asociados (inventario, cobro
y asiento contable) según la configuración de la empresa.

## Errores frecuentes

- **Firma caducada**: renueve el certificado y vuelva a cargarlo en la empresa.
- **Secuencial repetido**: revise el secuencial configurado para el
  establecimiento y punto de emisión.
- **El cliente no recibe el correo**: verifique la dirección registrada en su ficha.

## Historial de cambios

- **1.1** — Se documenta el envío por WhatsApp y el enlace de pago con tarjeta,
  disponible ahora también con **Nuvei** además de Payphone.
- **1.0** — Versión inicial.
