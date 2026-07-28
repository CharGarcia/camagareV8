---
titulo: Videollamadas
resumen: Crea salas de reunión por video dentro del sistema, con participantes, enlace para compartir y registro de asistencia.
categoria: Comunicaciones
ruta_modulo: modulos/videollamadas
tipo: modulo
visibilidad: todos
etiquetas: videollamada, video llamada, videoconferencia, reunion, reuniones, meet, zoom, llamada, conferencia, camara, microfono, sala, sala de espera, juntas, capacitacion, compartir pantalla, chat, levantar la mano
version: 1.4
orden: 0
estado: activo
---

Permite crear salas de reunión por video sin salir del sistema: se agenda, se
invita a los participantes, se comparte el enlace y queda constancia de quién
entró y cuándo. La sala se abre en una ventana aparte, igual que los videos de
ayuda, para poder seguir trabajando en el sistema mientras dura la reunión.

## Qué es y para qué sirve

Sirve para reuniones de trabajo entre usuarios del sistema y, cuando se habilita,
con personas externas como clientes o proveedores. Los casos típicos son la
revisión de un cierre contable con el contador, la atención a un cliente y las
capacitaciones internas.

El módulo depende de la empresa activa: cada empresa ve únicamente sus propias
reuniones.

**El video viaja directo entre los navegadores de los participantes**, no por el
servidor del sistema. Eso mantiene la conversación privada y no consume recursos
del servidor, pero impone un límite de participantes por reunión (ver *Reglas de
negocio*).

## Requisitos previos

- Tener permisos sobre el módulo (ver *Permisos*).
- Un navegador moderno con cámara y micrófono. La sala incluye un botón para
  probarlos antes de empezar.
- Que el administrador haya configurado un **servidor TURN** para la empresa. Sin
  él, algunas personas no logran conectarse: las que están en redes de oficina
  con cortafuegos o usando internet móvil. La sala avisa cuando falta esta
  configuración.

## Cómo se usa

1. Entre al módulo **Videollamadas** y pulse **Nueva reunión**.
2. Escriba el título y elija el tipo:
   - **Instantánea**: para reunirse ahora mismo.
   - **Programada**: pide fecha y hora.
   - **Permanente**: una sala fija que se reutiliza siempre, por ejemplo "Sala de Soporte".
3. En la pestaña **Participantes**, agregue a las personas. El anfitrión se agrega
   solo, no hace falta buscarlo.
4. Guarde. El sistema genera un **código de sala** parecido a `bcd-fghj-kmn`.
5. Pulse el botón de entrar (la flecha verde) para abrir la sala en una ventana
   nueva, o el de copiar enlace para enviárselo a los demás.
6. La primera vez, el navegador pide permiso para usar la cámara y el micrófono.
   Hay que aceptarlo: sin permiso no se puede participar con video.
7. Al terminar, el anfitrión pulsa **Finalizar** para cerrar la reunión.

## Invitar a personas de fuera

Para que alguien sin cuenta en el sistema pueda entrar —un cliente, un
proveedor, el contador externo—:

1. Active **Permitir invitados externos** en la pestaña General.
2. En **Participantes**, pulse **Invitado** y escriba su nombre y su correo.
3. Guarde y pulse el botón del **sobre** en la barra superior del modal.

Cada persona recibe un correo con **su propio enlace**. Al abrirlo entra directo
a la reunión: no crea cuenta, no instala nada, solo acepta el permiso de cámara y
micrófono que pide el navegador.

Los usuarios del sistema también reciben su correo, pero con la dirección del
módulo, porque ellos entran con su sesión normal.

**El enlace de un invitado es personal y no debe compartirse.** Es su llave de
entrada: quien lo tenga, entra con su nombre. Si la reunión tiene sala de espera,
igual necesita que el anfitrión lo admita.

Solo el anfitrión (o alguien con acceso total) puede enviar las invitaciones.

## Recordatorio automático

Las reuniones **programadas** avisan solas: quince minutos antes, cada
participante con correo recibe un recordatorio con el mismo enlace.

Se envía **una sola vez por reunión**, así que no hay riesgo de que la gente
reciba el mismo aviso repetido. Las reuniones instantáneas y las permanentes no
generan recordatorio, porque no tienen una hora de inicio que anunciar.

## Dentro de la sala

Su propia imagen aparece pequeña en la esquina inferior derecha; los demás
ocupan la pantalla y se reacomodan solos según cuántos sean.

Abajo hay seis botones:

| Botón | Qué hace |
|-------|----------|
| Micrófono | Se silencia o se activa. En rojo significa silenciado |
| Cámara | Apaga o enciende su video. Los demás dejan de verlo, pero siguen oyéndolo |
| Pantalla | Comparte lo que ve en su monitor. El navegador pregunta qué quiere compartir; para dejar de hacerlo, vuelva a pulsar el botón |
| Mano | Levanta la mano para pedir la palabra. A los demás les aparece un ícono amarillo sobre su video |
| Chat | Abre el panel de mensajes. Si llegan mensajes con el panel cerrado, sale un contador rojo |
| Colgar | Sale de la reunión y cierra la ventana |

Arriba, junto al título, se ve el estado de la conexión y cuánta gente hay
dentro. Si aparece **"En llamada (por relay TURN)"** significa que su red no
permitía la conexión directa y el sistema tuvo que usar el servidor de reenvío;
la llamada funciona igual.

### El chat de la reunión

Los mensajes viajan **directo entre los navegadores**, sin pasar por el servidor.
Eso tiene una consecuencia importante: **no se guardan**. Al cerrar la sala
desaparecen, y quien entra a mitad de la reunión no ve lo escrito antes. Si algo
tiene que quedar registrado, anótelo fuera del chat.

### La sala de espera

Cuando la reunión tiene activada la sala de espera, quien llega no entra
directamente:

- **El invitado** ve una pantalla de aviso y entra solo cuando lo autorizan. No
  tiene que hacer nada más que esperar.
- **El anfitrión** ve arriba a la derecha un recuadro amarillo con quienes están
  esperando, y decide uno por uno con **Admitir** o **No**.

El anfitrión y los moderadores nunca pasan por la espera, por una razón práctica:
alguien tiene que estar dentro para poder admitir a los demás.

Cada admisión y cada rechazo quedan registrados en la bitácora de la reunión.

### La calidad se ajusta sola

A medida que entra gente, el sistema **baja automáticamente la resolución y la
calidad del video** de todos. No es un fallo: en este tipo de llamada cada
participante envía su video a todos los demás por separado, así que con seis
personas cada uno estaría subiendo cinco videos a la vez. Bajar la calidad es lo
que permite que la reunión siga fluida en conexiones normales.

Con dos personas se usa la máxima calidad; de ahí en adelante va bajando por
tramos. Al compartir pantalla no se reduce, para que el texto siga legible.

## Configuración

El botón del engranaje, arriba del listado, **solo lo ve el superadministrador**.
Guarda credenciales de servicios contratados y afecta a todo el sistema, así que
no basta con tener permiso de modificar reuniones.

Tiene dos pestañas.

### Global

Los servidores que **heredan todas las empresas**. Se cargan una sola vez aquí,
no empresa por empresa.

| Ajuste | Para qué sirve |
|--------|----------------|
| Servidores STUN | Ayudan a los navegadores a descubrir su dirección pública. El de Google es gratuito y suele bastar |
| Servidores TURN | El plan B cuando la conexión directa no es posible |
| Usuario y credencial TURN | Datos de acceso al servidor TURN |
| TURN Key ID y Token de API | Si se cargan, el sistema pide a Cloudflare una credencial nueva y de corta duración en cada reunión. Es la opción más segura |
| Participantes y duración por defecto | Valores con los que arrancan las empresas nuevas |
| Permitir servidores propios | Si se apaga, ninguna empresa puede usar un proveedor distinto |

### Esta empresa

Los **límites** son siempre propios: cuánta gente cabe, cuánto puede durar una
reunión y a partir de cuántos participantes haría falta otro motor.

Los **servidores** solo hay que llenarlos si esta empresa debe usar un proveedor
distinto al del resto. Si se dejan vacíos, hereda los globales.

La herencia funciona **por bloque, no campo por campo**: o la empresa define su
servidor TURN completo (dirección, usuario y credencial), o hereda el global
entero. Mezclar la dirección de un proveedor con la credencial de otro daría una
configuración que no conecta con ninguno.

Arriba del todo, un resumen muestra qué servidores se están usando realmente y si
son **propios** o **heredados**.

### Sobre las credenciales

Se guardan cifradas y **nunca se vuelven a mostrar**: si deja el campo vacío al
guardar, se conserva la que ya estaba. Para quitarla hay un botón de papelera
junto a cada una.

El botón **Probar configuración** dice cuántos servidores quedaron disponibles y
avisa si falta el TURN.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Título de la reunión | Sí | Nombre con el que aparece en el listado y en la ventana de la sala |
| Tipo | Sí | Instantánea, programada o permanente |
| Fecha y hora de inicio | Solo si es programada | Cuándo empieza la reunión |
| Duración estimada | No | Cuánto se espera que dure, en minutos. Es informativo |
| Anfitrión | Sí | Quien puede iniciar y finalizar la reunión. Por defecto, quien la crea |
| Máximo de participantes | Sí | Cuántas personas caben. El motor interno admite hasta 8 |
| Descripción | No | Agenda o notas de la reunión |
| Sala de espera | No | Si se activa, el anfitrión admite a cada persona antes de que entre |
| Permitir invitados externos | No | Habilita agregar personas sin cuenta en el sistema, que entran por enlace |
| Grabar la reunión | No | Deja marcada la reunión para grabarse. Requiere habilitar la grabación en la configuración del módulo |

## Permisos

- **Ver**: entrar al módulo y abrir las reuniones.
- **Crear**: crear reuniones nuevas.
- **Modificar**: editar una reunión y finalizarla.
- **Eliminar**: eliminar reuniones que no estén en curso.
- **Acceso total**: ve las reuniones de **toda la empresa**. Sin este permiso,
  cada usuario ve solo las reuniones que él mismo creó. Además, con acceso total
  se puede iniciar y finalizar una reunión de la que no se es anfitrión.

El nivel 3 (superadministrador) siempre ve todo.

## Reglas de negocio

- **Límite de participantes**: el motor interno admite **hasta 8 personas**, y por
  encima de 6 la calidad empieza a bajar en conexiones lentas. No es una
  restricción que se pueda subir cambiando el número: con cada persona que entra,
  todos los demás navegadores tienen que enviar una copia más de su video. Para
  reuniones más grandes hay que configurar un proveedor externo.
- **Código de sala único**: se genera automáticamente y no se puede editar. Usa un
  alfabeto sin vocales para que no se formen palabras y sea fácil de dictar.
- **Una reunión en curso no se puede eliminar**: primero hay que finalizarla.
- **Una reunión finalizada o cancelada no se puede modificar**: queda como
  registro histórico. Si necesita repetirla, cree una nueva.
- **El anfitrión siempre está entre los participantes**, aunque no se lo agregue a
  mano.
- **Solo el anfitrión inicia y finaliza** la reunión, salvo que otro usuario tenga
  permiso de acceso total.
- **Los invitados externos necesitan que la opción esté activada**. Cada uno
  recibe un enlace propio con su token de acceso.
- **Nada se borra**: eliminar una reunión la marca como eliminada, no la borra de
  la base de datos.

## Integraciones con otros módulos

- **Auditoría**: cada creación, cambio, inicio, finalización y eliminación queda
  en el registro del sistema (`log_sistema`) con el usuario y la fecha.
- **Bitácora de la reunión**: aparte de la auditoría, el módulo guarda su propia
  bitácora de lo que ocurre dentro de la sala (quién entró, quién salió, cuándo se
  inició la grabación). Es el insumo del reporte de asistencia.
- **Usuarios y empresas**: la lista de participantes se arma con los usuarios
  asignados a la empresa activa.

## Errores frecuentes

- **"El motor interno admite hasta 8 participantes"**: está intentando crear una
  sala para más gente de la que el sistema puede sostener sin un servidor de
  video. Reduzca el cupo o pida al administrador que configure un proveedor
  externo.
- **"La sala no permite invitados externos"**: agregó a alguien sin cuenta en el
  sistema sin haber activado la opción. Actívela en la pestaña General.
- **"Este enlace no es válido"** (lo ve el invitado): la reunión se eliminó o el
  enlace se copió incompleto. Vuelva a enviarle la invitación.
- **"Su acceso caducó"** (lo ve el invitado): cerró la ventana o pasó demasiado
  tiempo. Basta con volver a abrir el enlace del correo.
- **Un participante no recibió la invitación**: revise que tenga correo
  registrado en la pestaña Participantes. El mensaje de resultado dice cuántos
  quedaron sin correo.
- **"No se puede eliminar una reunión en curso"**: finalícela primero.
- **"El navegador bloqueó el acceso"** al probar la cámara: el navegador tiene
  denegado el permiso para este sitio. Se habilita desde el candado de la barra de
  direcciones.
- **No hay servidor TURN configurado**: aviso que aparece en la sala. No impide
  usarla, pero algunas personas no van a poder conectarse. Lo resuelve el
  administrador en la configuración del módulo.
- **"No se pudo establecer la conexión con [nombre]"**: los dos navegadores no
  encontraron un camino entre sí. Casi siempre es falta de servidor TURN, sobre
  todo si alguno está en una red de oficina o usando datos móviles.
- **"La cámara está siendo usada por otro programa"**: ciérrelo (Teams, Zoom,
  Meet u otra pestaña con la cámara abierta) y vuelva a entrar.
- **Se ve el video pero no se oye**: revise que el micrófono no esté silenciado
  (el botón se pone rojo) y que el volumen del equipo no esté al mínimo.
- **La reunión no aparece en el listado**: si no tiene permiso de acceso total,
  solo ve las reuniones que usted creó. Pida que lo agreguen como participante o
  que le den acceso total.

## Historial de cambios

- **1.4** — Invitados externos: cada uno recibe por correo su enlace personal y
  entra sin tener cuenta en el sistema. Envío de invitaciones desde el modal y
  recordatorio automático quince minutos antes de las reuniones programadas.
- **1.3** — Los servidores STUN/TURN pasan a configurarse una sola vez de forma
  global para todas las empresas, con la posibilidad de que una empresa use los
  suyos propios. La pantalla de configuración queda reservada al
  superadministrador.
- **1.2** — Sala de espera con admisión por el anfitrión, chat dentro de la
  reunión, levantar la mano y ajuste automático de calidad según cuántos
  participantes haya.
- **1.1** — Conexión de video y audio entre participantes, compartir pantalla,
  controles de micrófono y cámara, registro de entradas y salidas con tiempo de
  permanencia, y pantalla de configuración de servidores STUN/TURN con soporte
  de credenciales temporales.
- **1.0** — Versión inicial. Salas, participantes, invitados externos, códigos de
  sala, estados de la reunión, bitácora y auditoría. La sala se abre en ventana
  aparte y permite probar cámara y micrófono. La conexión de video entre
  participantes se habilita en la siguiente entrega del módulo.
