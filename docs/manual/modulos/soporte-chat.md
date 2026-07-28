---
titulo: Chat de Soporte
resumen: Burbuja de ayuda en todas las pantallas para consultar al equipo de soporte, y la bandeja desde la que se responde.
categoria: Herramientas
ruta_modulo: modulos/soporte-chat
tipo: modulo
visibilidad: todos
etiquetas: soporte, chat, ayuda, consulta, mesa de ayuda, asistencia, contactar soporte, hablar con alguien, burbuja, no me funciona, reportar problema, ticket
version: 1.0
orden: 30
estado: activo
---

El **chat de soporte** conecta a quien usa el sistema con el equipo que lo
atiende. Tiene dos caras: una **burbuja** que aparece en todas las pantallas para
preguntar, y una **bandeja** desde la que el equipo responde.

Si lo que busca es conversar con sus propios clientes, ese es el módulo
**Chat de WhatsApp**. Este chat es para pedir ayuda **sobre el sistema**.

## Pedir ayuda desde cualquier pantalla

La burbuja azul de la esquina inferior derecha está en todas las pantallas. No
hace falta ningún permiso: cualquier persona con sesión abierta puede usarla.

1. Pulse la burbuja y luego **Nueva consulta**.
2. Escriba el asunto (opcional) y cuente el problema con el mayor detalle que
   pueda.
3. Pulse **Enviar consulta**.

Junto con su mensaje viaja **la pantalla desde la que escribió**. Por eso conviene
abrir la burbuja sin salir de donde ocurrió el problema: el equipo ve de entrada
en qué parte del sistema estaba y no tiene que preguntarlo.

La burbuja no aparece en las pantallas que funcionan aparte del sistema, como el
punto de venta, la cocina, las mesas y las comandas.

## Qué pasa después de enviar la consulta

Cada conversación pasa por estos estados:

| Estado | Qué significa |
|--------|---------------|
| En espera | Llegó y todavía nadie la ha tomado |
| Atendiendo | Alguien del equipo ya la está viendo |
| Resuelta | El equipo dio por resuelto el tema |
| Cerrada | La conversación terminó y ya no admite mensajes |

Cuando le respondan, aparece un globo rojo con el número de mensajes sin leer
sobre la burbuja. No hace falta tener el chat abierto ni recargar la página: el
aviso llega solo.

Dentro de la burbuja puede volver a la lista para ver todas sus consultas
anteriores, con la última respuesta de cada una.

## Adjuntar una captura o un archivo

El botón del clip permite enviar archivos de hasta **10 MB**. Se aceptan
imágenes (PNG, JPG, GIF, WEBP), PDF, XML, TXT, CSV, Excel, Word y ZIP.

Una captura de pantalla suele ahorrar varios mensajes de ida y vuelta: si el
sistema le muestra un error, adjunte la imagen de lo que ve.

Las imágenes se muestran dentro de la conversación; los demás archivos se
descargan al pulsarlos. Solo pueden verlos quien abrió la conversación y el
equipo de soporte.

## Calificar la atención

Cuando el equipo marca su consulta como **resuelta**, la burbuja le ofrece
puntuar la atención de una a cinco estrellas, con un comentario opcional.

Si prefiere no hacerlo, pulse **Ahora no** y vuelve a la caja de escritura.

## La bandeja del equipo de soporte

Es la pantalla del módulo, en `Chat de Soporte`. Ahí se leen y responden las
consultas.

A la izquierda está la lista de conversaciones, con la empresa y la persona que
escribió, el estado y un contador rojo si hay mensajes sin leer. Las que están
**en espera** suben automáticamente al principio.

Arriba hay un buscador —por empresa, usuario o asunto— y filtros por estado,
**Solo mías** y **Archivadas**.

A la derecha se abre la conversación. Bajo el asunto aparece una etiqueta con la
pantalla desde la que escribió la persona. Los botones de la cabecera permiten:

- **Tomar**: asignarse la conversación.
- **Resolver**: darla por resuelta; a la persona le aparecerá la calificación.
- **Cerrar**: terminarla. Ya nadie podrá escribir en ella.
- **Eliminar**: quitarla del sistema (requiere permiso de eliminación).

No hace falta pulsar *Tomar* antes de escribir: al responder, la conversación
queda asignada automáticamente a quien contestó.

## Quién puede atender las consultas

Atiende quien tenga **asignado el submódulo "Chat de Soporte"** en la empresa
desde la que trabaja. Se asigna en la pantalla de permisos, igual que cualquier
otro módulo. Los superadministradores entran siempre.

Se puede asignar a **varias empresas**: todas verán la misma bandeja y podrán
responder. Conviene tenerlo presente, porque la bandeja muestra las consultas de
todas las empresas del sistema, no solo las de la empresa que atiende.

El permiso de **acceso total** no cambia nada aquí: quien entra a la bandeja ve
todas las conversaciones. Del lado de la burbuja, en cambio, cada persona ve
únicamente las suyas.

## Respuestas rápidas

Para las preguntas que se repiten, el botón del rayo abre las plantillas
guardadas. Al pulsar una, su texto pasa a la caja de escritura, donde puede
ajustarlo antes de enviar.

Hay dos tipos:

- **Del equipo**: las ve y las usa cualquier agente de la empresa.
- **Personales**: solo suyas. Nadie más las ve ni puede editarlas.

Para crear una, pulse **+ Equipo** o **+ Personal**. Si ya tenía algo escrito en
la caja, se precarga como contenido: es el momento natural de guardar una
respuesta que acaba de redactar.

## Sugerir respuesta con IA

El botón **Sugerir respuesta** redacta un borrador a partir del manual del
sistema y de los documentos cargados en el módulo **IA Soporte**. Debajo aparece
en qué se basó, para que pueda comprobarlo.

El borrador **no se envía solo**: cae en la caja de escritura para que lo lea, lo
corrija y decida. La respuesta que recibe la persona siempre la firma un humano.

Funciona a petición, no automáticamente: si resuelve la consulta de memoria, no
pulse el botón y no se consume nada.

El botón solo aparece si el copiloto está activado en la configuración y si la
empresa tiene configurado su proveedor de IA en IA Soporte.

## Configuración del chat

El engranaje de la bandeja abre los ajustes, que son **generales del sistema**:
valen para todas las empresas, no para cada una por separado.

| Ajuste | Qué hace |
|--------|----------|
| Chat activo | Apaga o enciende la burbuja en todo el sistema |
| Copiloto de IA | Muestra u oculta el botón *Sugerir respuesta* |
| Mensaje de bienvenida | Texto que se lee al abrir la burbuja |
| Mensaje fuera de horario | Aviso que se muestra fuera del horario de atención |
| Días y horario de atención | Cuándo se considera que hay alguien atendiendo |
| Correo para avisos | Opcional. Si se deja vacío, el aviso llega igual (ver más abajo) |
| Avisar tras (min) | Minutos en espera antes de mandar el aviso. 0 = no avisar |
| Archivar tras (días) | Días desde el cierre antes de archivar. 0 = nunca |

## Horario de atención y avisos por correo

Fuera del horario configurado, la burbuja muestra el mensaje correspondiente,
pero **la consulta se puede enviar igual**: queda esperando y se atiende al
volver.

Si el tiempo de espera es mayor que cero, el sistema avisa por correo de las
consultas que llevan demasiado tiempo sin atender. El aviso incluye la empresa,
la persona, el asunto, cuánto lleva esperando y si alguien la había tomado ya.

El aviso llega al **correo de todas las empresas que tengan asignado el
submódulo** del chat, es decir, a las que atienden. No hay que configurar nada
más: basta con asignar el módulo.

Si prefiere que los avisos vayan a otra dirección —un alias del equipo, por
ejemplo— indíquela en *Correo para avisos* y se usará esa en lugar de las demás.

No se repite mientras la lista no cambie, así que no llena el buzón con el mismo
recordatorio.

## Qué pasa con las conversaciones antiguas

Las conversaciones cerradas se **archivan** automáticamente pasado el plazo
configurado (90 días de forma predeterminada).

Archivar no borra nada: la conversación sale de la bandeja para no estorbar en el
trabajo diario, y se sigue consultando marcando **Archivadas** en los filtros.

## Errores frecuentes

- **No veo la burbuja de soporte**: el chat puede estar desactivado en la
  configuración, o está en una pantalla que funciona aparte del sistema (punto de
  venta, cocina, mesas, comandas).
- **No me deja escribir en una conversación**: está *cerrada*. Abra una consulta
  nueva; el historial anterior se conserva.
- **Entro a la bandeja y me devuelve al inicio**: la empresa desde la que trabaja
  no tiene asignado el submódulo del chat.
- **No aparece el botón Sugerir respuesta**: el copiloto está desactivado, o la
  empresa no tiene configurado su proveedor de IA en IA Soporte.
- **No llegan los avisos por correo**: los minutos de aviso están en 0, o ninguna
  empresa con el submódulo asignado tiene correo registrado en su ficha.
- **El adjunto no se envía**: revise que no supere los 10 MB y que sea de un tipo
  permitido.

## Historial de cambios

- **1.0** — Versión inicial: burbuja de consulta, bandeja del equipo, adjuntos,
  respuestas rápidas, sugerencia con IA, calificación, avisos por correo y
  archivado automático.
