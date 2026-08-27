---
titulo: Chat de WhatsApp
resumen: Conversaciones de WhatsApp con los clientes desde el propio sistema.
categoria: Herramientas
ruta_modulo: modulos/whatsapp-chat
tipo: modulo
visibilidad: todos
etiquetas: whatsapp, chat, mensajes, clientes, enviar factura, plantillas, atencion al cliente
version: 1.2
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

## Permisos

Este módulo se rige por los permisos del submódulo, como el resto del sistema:

| Permiso | Qué habilita |
|---|---|
| Ver | Abrir la bandeja, leer conversaciones y ver las respuestas rápidas |
| Crear | Enviar mensajes, adjuntar archivos y crear respuestas rápidas |
| Modificar | Editar respuestas rápidas existentes |
| Eliminar | Eliminar respuestas rápidas |

Las respuestas rápidas **personales** solo las edita o elimina quien las creó,
aunque otro usuario tenga el permiso.

A diferencia del chat de soporte, este módulo **sí es por empresa**: el permiso
se comprueba contra la empresa desde la que está trabajando, y las conversaciones
que ve son las de esa empresa.

## El ícono de WhatsApp en la barra superior

Mientras tenga el módulo asignado en la empresa activa, el **ícono de WhatsApp de
la barra superior está siempre visible**, aunque no haya mensajes nuevos: es el
acceso directo a la bandeja, no solo un aviso. La **cifra roja** sobre el ícono
aparece únicamente cuando hay mensajes sin leer.

## Errores frecuentes

- **El mensaje no se envía**: revise la configuración de WhatsApp de la empresa y
  que el cliente tenga un número válido en su ficha.
- **El cliente no recibe el documento**: compruebe el formato del número
  (con código de país, sin espacios ni guiones).
- **"No tiene permiso para esta acción"**: pida que le asignen el submódulo en
  *Permisos de módulos*. Antes el módulo se abría sin permiso asignado; ahora lo
  exige, igual que los demás.

## Historial de cambios

- **1.2** — El ícono de WhatsApp de la barra superior queda siempre visible para
  quien tiene el módulo asignado, aunque no haya mensajes sin leer (la cifra roja
  sigue apareciendo solo cuando los hay). Nueva sección sobre el ícono.
- **1.1** — El módulo pasa a exigir el permiso del submódulo (antes entraba
  cualquier usuario con sesión). Nueva sección *Permisos*.
- **1.0** — Versión inicial.
