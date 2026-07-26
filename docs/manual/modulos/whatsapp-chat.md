---
titulo: Chat de WhatsApp
resumen: Conversaciones de WhatsApp con los clientes desde el propio sistema.
categoria: Herramientas
ruta_modulo: modulos/whatsapp-chat
tipo: modulo
visibilidad: todos
etiquetas: whatsapp, chat, mensajes, clientes, enviar factura, plantillas, atencion al cliente
version: 1.0
orden: 20
estado: activo
---

Este módulo permite conversar por **WhatsApp** con los clientes sin salir del
sistema, y enviarles documentos directamente desde el módulo que los emite.

## Antes de usarlo

La empresa necesita tener configurada su conexión de WhatsApp en el módulo de
configuración correspondiente. Sin esa configuración, los botones de envío no
funcionan.

## Envío de documentos

Desde la barra de acciones de facturas, recibos y otros documentos hay un botón
de WhatsApp que envía el comprobante al cliente. El número que se usa es el
registrado en su ficha.

## Plantillas

Los mensajes recurrentes se definen como **plantillas**, para no reescribirlos
cada vez y mantener un tono uniforme. Además, las plataformas de WhatsApp exigen
plantillas aprobadas para iniciar una conversación.

## Errores frecuentes

- **El mensaje no se envía**: revise la configuración de WhatsApp de la empresa y
  que el cliente tenga un número válido en su ficha.
- **El cliente no recibe el documento**: compruebe el formato del número
  (con código de país, sin espacios ni guiones).

## Historial de cambios

- **1.0** — Versión inicial.
