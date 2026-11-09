---
titulo: Bandeja social
resumen: Leer y responder los mensajes directos y comentarios de Instagram de la empresa, repartirlos entre el equipo y convertirlos en oportunidades del CRM.
categoria: Ventas
ruta_modulo: modulos/bandeja_social
tipo: modulo
visibilidad: todos
etiquetas: bandeja, bandeja social, instagram, mensajes de instagram, dm, mensaje directo, inbox, chat de instagram, comentarios, responder comentarios, respuesta privada, respuesta publica, redes sociales, atender clientes, ventana de 24 horas, no puedo responder, human agent, asignar conversacion, cerrar conversacion, respuestas rapidas, crm
version: 1.1
orden: 16
estado: activo
---

La **Bandeja social** reúne en un solo lugar los **mensajes directos** y los
**comentarios** que llegan a la cuenta de Instagram de la empresa. Desde aquí se
leen, se responden, se reparten entre el equipo y, cuando hay interés de compra,
se convierten en **oportunidades** del **CRM**.

## Qué es y para qué sirve

- No depender del celular de una persona: todo el equipo atiende la misma cuenta.
- Saber quién atiende cada conversación y qué está sin responder.
- Pasar al CRM a quien muestra interés, sin copiar datos a mano.

## Requisitos previos

- La cuenta de Instagram conectada en **Configuración del CRM → Canales**.
- Cada persona que escribe o comenta se registra sola como **contacto** del CRM.

## Cómo se usa

La pantalla tiene tres columnas (en el celular se ven una a la vez):

1. **Conversaciones**: arriba, *Todas / Mías / Sin asignar*; el tipo (mensajes o
   comentarios), el estado (abiertas, cerradas) y un buscador por nombre,
   @usuario o texto. En rojo, los mensajes sin leer.
2. **Chat**: los mensajes con su hora (d-m-Y H:i:s). Abajo se escribe la
   respuesta: **Enter** envía y **Shift + Enter** hace un salto de línea. El rayo
   abre las **respuestas rápidas** (las mismas del chat de WhatsApp).
3. **Contacto**: responsable, *Asignármela*, *Cerrar conversación*, sus
   oportunidades, **Crear oportunidad** y *Enlazar a otro contacto*.

La pantalla se actualiza sola cada 10 segundos. Al abrir una conversación queda
como leída.

En la barra superior del sistema, el ícono de **Instagram** avisa cuántas
conversaciones con mensajes sin leer le tocan (asignadas a usted o sin asignar),
aunque esté en otra pantalla.

### Responder un mensaje directo

Escriba y envíe. Bajo cada mensaje enviado se ve su estado: ✓ enviado, ✓✓ leído
por el cliente, o *No se envió* con el motivo y el botón **Reintentar**.

### Responder un comentario

Elija **Pública** (se publica bajo el comentario y la ve cualquiera) o
**Privada** (le llega a la persona como mensaje directo). Instagram permite **una
sola** respuesta privada por comentario y solo dentro de los **7 días** siguientes.
Cuando la persona contesta esa respuesta privada, la conversación sigue como
mensaje directo.

### La ventana de 24 horas

Instagram solo deja escribir a una persona dentro de las **24 horas** siguientes
a **su** último mensaje. La pantalla muestra cuánto tiempo queda. Pasado ese
plazo el cuadro de texto se bloquea con el aviso correspondiente y hay que
esperar a que el cliente vuelva a escribir.

Si la empresa tiene activado **«Responder hasta 7 días (Human Agent)»** en
Configuración del CRM, se puede responder hasta 7 días después, siempre escrito
por una persona.

## Permisos

- **Ver**: abrir la bandeja y leer.
- **Crear**: responder (mensajes y comentarios) y reintentar envíos.
- **Modificar**: asignar, cerrar o reabrir y enlazar a otro contacto.
- **Acceso total**: ve todas las conversaciones de la empresa. **Sin acceso
  total**, ve las asignadas a él y las que están **sin asignar**.
- **Crear oportunidad** requiere además permiso de crear en el **CRM**.

## Reglas de negocio

- Las conversaciones nuevas se reparten según lo elegido en Configuración del
  CRM: sin asignar, siempre a un usuario o por turnos (al que tenga menos
  conversaciones abiertas).
- Un mensaje nuevo del cliente reabre una conversación cerrada.
- Lo que la empresa responda desde la app de Instagram también aparece aquí, como
  enviado «Desde Instagram».
- Un doble clic en Enviar no manda el mensaje dos veces; *Crear oportunidad*
  tampoco crea dos.
- Las imágenes, videos y audios que envía el cliente se guardan en el sistema y
  solo los ve quien tiene acceso a la conversación.
- Por ahora se responde solo con **texto** (máximo unos 1000 caracteres).

## Integraciones con otros módulos

- **CRM**: cada persona es un contacto; *Crear oportunidad* la pone en el embudo
  con el contacto, su origen y la conversación enlazados.
- **Chat de WhatsApp**: comparte las respuestas rápidas (se administran allá).

## Errores frecuentes

- **«Han pasado más de 24 h desde el último mensaje del cliente…»**: regla de
  Instagram; espere a que el cliente escriba.
- **«El cliente todavía no ha escrito por mensaje directo…»**: Instagram no deja
  escribir primero.
- **«Ese comentario ya recibió su respuesta privada»**: solo se permite una.
- **«La cuenta de Instagram está desconectada o con el acceso vencido»**:
  reconéctela en Configuración del CRM.
- **«Esta conversación está asignada a otro usuario»**: sin acceso total solo se
  atienden las propias y las sin asignar.
- **Mensaje con «No se envió»**: pulse *Reintentar*; si se repite, revise el motivo
  que se muestra bajo el mensaje.

## Historial de cambios

- **1.1** — Aviso en la barra superior con las conversaciones sin leer que le tocan.
- **1.0** — Versión inicial: mensajes directos y comentarios de Instagram,
  respuestas públicas y privadas, ventana de 24 h / Human Agent, reparto,
  respuestas rápidas y creación de oportunidades.
