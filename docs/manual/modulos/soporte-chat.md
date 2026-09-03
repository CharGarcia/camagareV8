---
titulo: Chat de Soporte
resumen: Burbuja de ayuda en todas las pantallas para consultar al equipo de soporte, y la bandeja desde la que se responde.
categoria: Herramientas
ruta_modulo: modulos/soporte-chat
tipo: modulo
visibilidad: todos
etiquetas: soporte, chat, ayuda, aviso whatsapp, alerta soporte, consulta, mesa de ayuda, asistencia, contactar soporte, hablar con alguien, burbuja, no me funciona, reportar problema, ticket
version: 1.3
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

### Mover la burbuja de sitio

Si la burbuja tapa algo que necesita ver (un botón, el total de una tabla),
**arrástrela** con el ratón o con el dedo hasta donde le estorbe menos. Un toque
corto sigue abriendo el chat; solo se mueve cuando la arrastra.

- La posición se mantiene mientras dure su sesión, aunque cambie de pantalla o
  abra otra pestaña. Al **cerrar sesión y volver a entrar**, la burbuja regresa a
  la esquina inferior derecha.
- La burbuja nunca queda fuera de la ventana ni **debajo del menú superior**: si
  la arrastra hacia arriba se detiene justo bajo el navbar, y si achica la
  pantalla se reacomoda sola hacia adentro.
- El panel del chat se abre hacia el lado donde haya espacio (hacia la derecha
  si dejó la burbuja a la izquierda, hacia abajo si la dejó arriba).

### Quitar la burbuja por un rato

Si prefiere no verla, pase el ratón por encima de la burbuja y pulse la **x**
gris que aparece en su esquina (en pantallas táctiles la x está siempre a la
vista). La burbuja desaparece de todas las pantallas **hasta que cierre sesión y
vuelva a entrar**; entonces regresa sola a su esquina.

Quitarla no afecta a sus consultas: las conversaciones y las respuestas del
equipo siguen ahí y las verá al volver a tener la burbuja.

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

Atiende quien tenga **asignado el submódulo "Chat de Soporte"** en **cualquiera
de sus empresas**. Se asigna en la pantalla de permisos, igual que cualquier otro
módulo. Los superadministradores entran siempre.

No hace falta que sea la empresa desde la que está trabajando en ese momento:
basta con tenerlo asignado en una. Es a propósito, porque la bandeja **no es por
empresa** — muestra las consultas de todo el sistema —, así que quien atiende
soporte lo sigue haciendo aunque cambie de empresa para hacer otra cosa.

Mientras tenga ese acceso, el **ícono de soporte (auricular) del navbar está
siempre visible**, aunque no haya nada por atender: es el acceso directo a la
bandeja. La **cifra roja** sobre el ícono aparece solo cuando hay consultas en
espera o respuestas sin leer.

Solo cuentan las empresas **activas**: si la única empresa donde tenía el módulo
asignado se desactiva o se elimina, deja de atender.

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
| WhatsApp para avisos | Opcional. Número que recibe el mismo aviso por WhatsApp |
| Plantilla de WhatsApp | Opcional. Plantilla aprobada en Meta con la que se manda el aviso |
| Idioma | Idioma de esa plantilla (por defecto `es`) |
| Avisar tras (min) | Minutos en espera antes de mandar el aviso. 0 = no avisar |
| Archivar tras (días) | Días desde el cierre antes de archivar. 0 = nunca |

## Horario de atención y avisos por correo

Fuera del horario configurado, la burbuja muestra el mensaje correspondiente,
pero **la consulta se puede enviar igual**: queda esperando y se atiende al
volver.

Si el tiempo de espera es mayor que cero, el sistema avisa por correo de las
consultas que llevan demasiado tiempo sin atender. El aviso incluye la empresa,
la persona, el asunto, cuánto lleva esperando y si alguien la había tomado ya.

De fábrica ese tiempo es de **1 minuto**, es decir, prácticamente inmediato: el
proceso del servidor revisa cada minuto y el propio sistema evita repetir el
aviso mientras la lista no cambie.

El aviso llega al **correo de todas las empresas que tengan asignado el
submódulo** del chat, es decir, a las que atienden. No hay que configurar nada
más: basta con asignar el módulo.

Si prefiere que los avisos vayan a otra dirección —un alias del equipo, por
ejemplo— indíquela en *Correo para avisos* y se usará esa en lugar de las demás.

No se repite mientras la lista no cambie, así que no llena el buzón con el mismo
recordatorio.

## Aviso por WhatsApp

El mismo aviso puede salir **también por WhatsApp**, con el **nombre de la
empresa y de la persona** que están pidiendo soporte, el asunto y cuánto lleva
esperando. Es el aviso más rápido: llega al teléfono sin abrir el correo.

Se apoya en el módulo de **Chat de WhatsApp**, no monta nada aparte:

- **Quién lo envía**: una empresa que atienda soporte y tenga su WhatsApp
  configurado. Si ninguna lo tiene, se usa el WhatsApp de la empresa que está
  pidiendo el soporte. Si no hay ninguno de los dos, no se envía y el aviso por
  correo sigue igual.
- **A quién llega**: a los números registrados para recibir avisos en la
  configuración de WhatsApp de esa empresa — los mismos del aviso de chats sin
  leer, no hay que mantener otra lista. Si prefiere un número distinto solo para
  soporte, escríbalo en *WhatsApp para avisos*.
- **Con qué mensaje**: si deja vacía la *Plantilla de WhatsApp*, se manda un
  mensaje de texto normal. Tenga en cuenta que WhatsApp **solo entrega texto
  libre si ese número escribió al negocio en las últimas 24 horas**; para que
  llegue siempre, cree una plantilla aprobada en Meta con tres variables
  (`{{1}}` empresa, `{{2}}` usuario, `{{3}}` asunto) e indique su nombre ahí.

Un fallo de WhatsApp —credenciales caducadas, por ejemplo— nunca impide que el
aviso por correo salga.

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
- **Entro a la bandeja y me devuelve al inicio**: no tiene asignado el submódulo
  del chat en ninguna de sus empresas activas.
- **No veo el ícono de soporte en la barra superior**: aparece únicamente para
  quien atiende consultas. Si acaban de asignarle el módulo, puede tardar hasta
  un minuto en aparecer; recargue la página.
- **No aparece el botón Sugerir respuesta**: el copiloto está desactivado, o la
  empresa no tiene configurado su proveedor de IA en IA Soporte.
- **No llegan los avisos por correo**: los minutos de aviso están en 0, o ninguna
  empresa con el submódulo asignado tiene correo registrado en su ficha.
- **No llega el aviso por WhatsApp**: revise que la empresa que atiende tenga su
  WhatsApp configurado y números registrados para avisos. Si no usa plantilla,
  recuerde que WhatsApp solo entrega mensajes de texto a quien escribió al
  negocio en las últimas 24 horas.
- **El adjunto no se envía**: revise que no supere los 10 MB y que sea de un tipo
  permitido.

## Historial de cambios

- **1.3** — La burbuja de soporte se puede **arrastrar** a cualquier punto de la
  pantalla; la posición dura mientras la sesión esté abierta (al volver a entrar
  regresa a su esquina), nunca se mete bajo el navbar y el panel se abre hacia
  el lado donde hay espacio. También se puede **quitar** durante la sesión con
  la x de su esquina. Nuevas secciones *Mover la burbuja de sitio* y *Quitar la
  burbuja por un rato*.
- **1.2** — El aviso de consultas sin atender pasa a 1 minuto (prácticamente
  inmediato) y puede salir también por **WhatsApp**, con la empresa y la persona
  que piden soporte. Nueva sección *Aviso por WhatsApp* y tres ajustes nuevos en
  la configuración.
- **1.1** — Quien atiende soporte conserva el acceso desde cualquiera de sus
  empresas, no solo desde aquella en la que tiene el módulo asignado, y el ícono
  de soporte del navbar queda siempre visible mientras tenga ese acceso (la cifra
  roja sigue apareciendo solo cuando hay consultas pendientes).
- **1.0** — Versión inicial: burbuja de consulta, bandeja del equipo, adjuntos,
  respuestas rápidas, sugerencia con IA, calificación, avisos por correo y
  archivado automático.
