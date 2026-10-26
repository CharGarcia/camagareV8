---
titulo: Chat de WhatsApp
resumen: Conversaciones de WhatsApp con los clientes desde el propio sistema.
categoria: Herramientas
ruta_modulo: modulos/whatsapp-chat
tipo: modulo
visibilidad: todos
etiquetas: whatsapp, chat, mensajes, clientes, enviar factura, plantillas, atencion al cliente, nuevo chat, iniciar conversacion, 24 horas
version: 1.5
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

## Iniciar un chat nuevo

El botón **Nuevo chat** (junto a *Chats*, arriba de la lista) abre una ventana para
escribirle a alguien que todavía no tiene conversación:

1. **Buscar cliente**: escriba nombre, cédula/RUC o teléfono y elija al cliente; se
   llenan el celular y el nombre. Si su teléfono registrado no es un celular 09…, se
   avisa y hay que escribir el celular a mano. También puede escribir directamente
   el **celular** (10 dígitos, por ejemplo 0958924835) y el nombre del contacto.
2. **Plantilla aprobada**: elija una de las plantillas aprobadas por Meta.
3. **Variables**: complete cada `{{1}}`, `{{2}}`… (y el final del enlace si la
   plantilla tiene un botón con enlace). Si el texto empieza con «Hola {{1}}», la
   primera variable se llena con el nombre del contacto. A la derecha se ve la
   **vista previa**; lo que falta aparece resaltado en amarillo.
4. **Enviar**: el chat se crea (o se abre el que ya existía con ese número) con el
   mensaje enviado. Cuando el cliente responda, se le puede escribir libremente.

Las plantillas con un archivo en el encabezado (imagen, documento o video) o con
variables con nombre aparecen como «no disponible aquí».

## La ventana de 24 horas

WhatsApp solo permite escribir texto libre, adjuntar archivos o usar respuestas
rápidas durante las **24 horas siguientes al último mensaje del cliente**. Si
pasaron más de 24 horas (o el cliente nunca escribió), en lugar del cuadro de
texto aparece el aviso y el botón **Enviar plantilla**, que abre la misma ventana
ya dirigida a ese chat. En cuanto el cliente conteste, vuelve el cuadro de texto.

## Permisos

Este módulo se rige por los permisos del submódulo, como el resto del sistema:

| Permiso | Qué habilita |
|---|---|
| Ver | Abrir la bandeja, leer conversaciones y ver las respuestas rápidas |
| Crear | Enviar mensajes, adjuntar archivos, crear respuestas rápidas, iniciar chats nuevos y enviar plantillas |
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
- **"Pasaron más de 24 horas desde el último mensaje del cliente"**: WhatsApp no
  deja enviar texto libre; use **Enviar plantilla** (ver *La ventana de 24 horas*).
- **No aparece el botón Nuevo chat**: requiere el permiso *Crear* del módulo.
- **"No hay plantillas aprobadas por Meta"**: créelas en *Plantillas de WhatsApp* y
  espere a que Meta las apruebe.
- **"No tiene permiso para esta acción"**: pida que le asignen el submódulo en
  *Permisos de módulos*. Antes el módulo se abría sin permiso asignado; ahora lo
  exige, igual que los demás.

## Historial de cambios

- **1.5** — Con la plantilla del sistema `iniciar_conversacion` (créela en Plantillas
  de WhatsApp → Plantillas del sistema), {{1}} se llena con el nombre del contacto
  y {{2}} con el nombre de la empresa.
- **1.4** — Botón **Nuevo chat**: iniciar una conversación buscando un cliente o
  escribiendo el celular, con una plantilla aprobada, sus variables y vista previa.
  Si pasaron 24 h desde el último mensaje del cliente, el cuadro de texto se cambia
  por el aviso y el botón **Enviar plantilla**.
- **1.3** — El chat ocupa toda la pantalla, de borde a borde y hasta el filo inferior; la
  página ya no tiene scroll vertical: solo se desplazan la lista de chats y los mensajes.
- **1.2** — El ícono de WhatsApp de la barra superior queda siempre visible para
  quien tiene el módulo asignado, aunque no haya mensajes sin leer (la cifra roja
  sigue apareciendo solo cuando los hay). Nueva sección sobre el ícono.
- **1.1** — El módulo pasa a exigir el permiso del submódulo (antes entraba
  cualquier usuario con sesión). Nueva sección *Permisos*.
- **1.0** — Versión inicial.
