---
titulo: Campañas Masivas de WhatsApp
resumen: Envío de una plantilla aprobada de WhatsApp a muchos clientes a la vez, uno por uno y con su avance en pantalla.
categoria: Herramientas
ruta_modulo: modulos/whatsapp-campanas
tipo: modulo
visibilidad: todos
etiquetas: campanas, campana, campana whatsapp, campanas masivas, envio masivo, mensajes masivos, whatsapp masivo, difusion, lista de difusion, publicidad, promocion, marketing, anuncio, comunicado, enviar a todos los clientes, enviar a varios clientes, mensaje a clientes, plantilla aprobada, meta, variables, mapeo de variables, destinatarios, seleccionar clientes, iniciar campana, envio uno a uno, progreso, exitosos, fallidos
version: 1.0
orden: 0
estado: activo
---

Este módulo envía **un mismo mensaje de WhatsApp a muchos clientes** de una sola
vez: se elige una plantilla aprobada por Meta, se marcan los clientes y se pulsa
**Iniciar Campaña**. El sistema envía los mensajes uno por uno y muestra el avance
en pantalla. Cada mensaje enviado queda en la conversación del cliente en el
[Chat de WhatsApp](modulos/whatsapp-chat).

## Qué es y para qué sirve

Sirve para comunicados, promociones o avisos generales a la cartera de clientes.
Como WhatsApp no permite escribirle primero a un cliente con texto libre, la
campaña siempre usa una **plantilla aprobada por Meta** (ver
[Plantillas de WhatsApp](modulos/plantillas-whatsapp)). Si la plantilla tiene
variables (`{{1}}`, `{{2}}`…), usted decide qué dato de cada cliente va en cada
una, y el sistema lo rellena cliente por cliente.

## Requisitos previos

- La empresa debe tener configurada su conexión de **WhatsApp Business API** en
  **Configuración de WhatsApp** (Token de Acceso y Phone ID). Sin eso, la pantalla
  solo muestra el aviso *«WhatsApp no está configurado»*.
- Al menos una plantilla en estado **APPROVED** en
  [Plantillas de WhatsApp](modulos/plantillas-whatsapp). Use **Sincronizar** en
  ese módulo para traer el estado actualizado desde Meta.
- Clientes **activos** con **teléfono** registrado en su ficha. Los clientes sin
  teléfono no aparecen en la lista.

## Cómo se usa

La pantalla tiene tres bloques.

### 1. Configurar Mensaje

1. En **Seleccionar Plantilla Aprobada**, elija la plantilla. Se muestra con su
   nombre e idioma, por ejemplo *promocion_mayo (es)*.
2. Si la plantilla tiene variables en el cuerpo del mensaje, aparece **Mapeo de
   Variables**: para cada **Variable {{1}}**, **Variable {{2}}**…, elija qué dato
   del cliente se coloca ahí: **Nombre del Cliente**, **Identificación (RUC/CI)**
   o **Teléfono**.

### 2. Destinatarios

1. La tabla lista los clientes activos con teléfono, ordenados por nombre, con
   **Nombre del Cliente**, **Identificación** y **Teléfono**.
2. Marque los clientes uno por uno (puede hacer clic en cualquier parte de la
   fila) o use la casilla del encabezado para marcarlos todos.
3. El cuadro **Buscar cliente...** filtra la tabla por nombre, identificación o
   teléfono. La casilla del encabezado marca o desmarca **solo los clientes que
   quedan visibles** con el filtro, así que puede buscar, marcar todos los
   resultados, cambiar la búsqueda y seguir sumando.
4. El contador **N seleccionados** muestra cuántos clientes recibirán el mensaje.

### 3. Iniciar Envío Masivo

1. Pulse **Iniciar Campaña** y confirme. El sistema pregunta: *«Se enviarán
   mensajes a N clientes. El proceso no debe ser interrumpido.»*
2. Aparece una barra de progreso (*Enviando X de Y* y el porcentaje) y un registro
   línea por línea: *Enviando a {cliente} ({teléfono})...* seguido de **OK**,
   **ERROR** (con el motivo) o **ERROR RED**.
3. **No cierre ni recargue la pestaña** hasta que termine: los mensajes se envían
   desde su navegador, uno tras otro, con una pequeña pausa entre cada uno.
4. Al terminar se muestra *«Campaña finalizada. Exitosos: N, Fallidos: M»* y una
   ventana **Campaña Finalizada** con el mismo resumen. Al cerrarla, la página se
   recarga y la selección de clientes vuelve a quedar vacía.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Seleccionar Plantilla Aprobada | Sí | Plantilla de WhatsApp aprobada por Meta que se enviará. Solo aparecen las aprobadas y activas de la empresa. |
| Variable {{n}} | Si la plantilla tiene variables | Dato del cliente que se pone en esa variable: Nombre del Cliente, Identificación (RUC/CI) o Teléfono. |
| Destinatarios | Sí, al menos uno | Clientes marcados en la tabla. |

## Permisos

- **Ver**: entrar al módulo, ver las plantillas aprobadas y la lista de clientes.
- **Crear**: necesario para enviar los mensajes de la campaña. Sin este permiso
  cada envío se rechaza.

La lista de destinatarios muestra todos los clientes activos con teléfono de la
empresa.

## Reglas de negocio

- Solo se puede usar una plantilla en estado **APPROVED**; el texto del mensaje lo
  define la plantilla y no se puede escribir texto libre en la campaña.
- La campaña rellena únicamente las **variables del cuerpo** del mensaje, con los
  datos de la ficha de cada cliente. No adjunta documentos.
- El número se envía con el código de Ecuador: si el teléfono del cliente empieza
  con 0, ese 0 se reemplaza por 593; si no empieza con 593, se le antepone 593.
  Revise que los teléfonos de las fichas estén bien escritos.
- Un error con un cliente no detiene la campaña: se anota como fallido y se sigue
  con el siguiente.
- Cada mensaje enviado con éxito se guarda en la conversación de ese número en el
  **Chat de WhatsApp** (si no existía, se crea), junto con el texto de la
  plantilla con sus variables ya reemplazadas. En la lista de chats aparece como último mensaje *Campaña:* seguido
  del nombre de la plantilla.
- El módulo no guarda un registro aparte de cada campaña ni permite programarla
  para más tarde: el envío ocurre en el momento y lo enviado queda en el Chat de
  WhatsApp.

## Integraciones con otros módulos

- **[Plantillas de WhatsApp](modulos/plantillas-whatsapp)**: de ahí salen las
  plantillas aprobadas que se pueden usar.
- **[Chat de WhatsApp](modulos/whatsapp-chat)**: cada mensaje enviado queda
  guardado en la conversación del cliente.
- **Clientes**: nombre, identificación y teléfono se toman de la ficha de cada
  cliente.
- **Configuración de WhatsApp**: credenciales de la cuenta de WhatsApp Business.

## Errores frecuentes

- **«WhatsApp no está configurado»**: falta el Token de Acceso o el Phone ID en
  Configuración de WhatsApp.
- **«No hay clientes con teléfono registrado.»** (en la tabla): ningún cliente
  activo tiene teléfono en su ficha.
- **No aparece la plantilla en la lista**: no está aprobada por Meta, está
  inactiva o el estado no se ha sincronizado. Revísela en Plantillas de WhatsApp.
- **«Debes seleccionar una plantilla»** o **«Debes seleccionar al menos un
  cliente»**: complete el bloque correspondiente antes de pulsar **Iniciar
  Campaña**.
- **ERROR (Configuración incompleta. Faltan credenciales.)** en el registro: la
  configuración de WhatsApp de la empresa está incompleta.
- **ERROR (Error enviando mensaje: …)** en el registro: Meta rechazó ese envío; el
  texto que sigue es el motivo que devolvió Meta.
- **ERROR RED** en el registro: se perdió la comunicación con el servidor durante
  ese envío. Ese cliente se cuenta entre los fallidos.

## Historial de cambios

- **1.0** — Versión inicial del manual.
