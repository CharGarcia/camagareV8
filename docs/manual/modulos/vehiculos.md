---
titulo: Vehículos
resumen: Ficha de cada vehículo con su historial de órdenes de Car-Wash, los recordatorios de su próxima cita y el control de mantenimientos por kilometraje (cambio de aceite).
categoria: Servicios
ruta_modulo: modulos/vehiculos
tipo: modulo
visibilidad: todos
etiquetas: vehiculos, vehiculo, carro, auto, placa, propietario, dueño, consulta sri, consultar placa, datos del vehiculo por placa, matricula, marca, año, historial del vehiculo, transacciones, visitas, ordenes car wash, lavado, taller, proxima cita, recordatorio, recordar cita, agregar recordatorio, nuevo recordatorio, crear recordatorio, recordatorio manual, motivo, cambio de aceite, mantenimiento, aviso al cliente, whatsapp, correo, automatizacion, buscar placa, placa sin guion, buscar sin guion, buscar item, buscar servicio, buscar producto, categoria, marca del producto, filtrar por marca, filtrar por categoria, cuando se cambio el aceite, filtros, mantenimientos, proximo cambio de aceite, aceite de motor, aceite de caja, kilometraje, km, cada cuantos km, intervalo, motor, cilindraje, registrar cambio de aceite, cambio hecho en otro lugar, vencido, servicios de mantenimiento, misma marca, sugerido, aviso de cambio de aceite, avisar cambio de aceite, recordar mantenimiento, km estimado, kilometraje estimado, aviso automatico de mantenimiento, plantilla whatsapp mantenimiento, mantenimiento vencido, filtrar vencidos
version: 1.14
orden: 11
estado: activo
---

El módulo de **Vehículos** guarda la ficha de cada vehículo que atiende la empresa
(placa, marca, propietario y contacto). Lo usan el **Servicio de car wash** y el
**Taller**: al registrar una orden se elige el vehículo de aquí.

## Qué es y para qué sirve

- Tener un solo registro por placa, con los datos de contacto del propietario.
- Ver **todo lo que se le ha hecho** a un vehículo: cada orden de Car-Wash con sus
  servicios, productos, valores y el documento en que se cobró.
- **Recordar al cliente su próxima cita** por correo o por WhatsApp, a mano o de forma
  automática.

## Requisitos previos

- Para las pestañas Transacciones y Recordatorios, el vehículo debe estar guardado.
- Las citas salen de la **próxima cita** que se fija en cada orden de Car-Wash.
- Para enviar correos, la empresa debe tener configurado su correo de salida; para el
  envío automático por WhatsApp, la API de WhatsApp y una plantilla aprobada por Meta.

## Cómo se usa

1. Pulse **Nuevo** o haga clic en un vehículo del listado.
2. En la pestaña **General** escriba primero la **placa** (única por empresa); el
   sistema trae la marca, el modelo y el año desde el SRI. Complete chasis, estado,
   propietario, correo y teléfono, y pulse **Guardar**.
3. Con el vehículo guardado, use las pestañas **Transacciones** y **Recordatorios**.

Cada usuario puede ocultar las pestañas que no use con el engranaje a la derecha de
las pestañas.

## Formato de la placa (AAA-1111)

En un vehículo **nuevo**, la placa se escribe con el formato `AAA-1111`: 3 letras,
guion y 4 números. El campo solo acepta letras en las 3 primeras posiciones y
números en las 4 últimas, pasa las letras a mayúsculas y pone el guion solo. Las
placas antiguas de 3 números se completan con un 0 al salir del campo
(`ABC-123` → `ABC-0123`).

Al **editar** un vehículo ya guardado no se aplica la máscara, para no estropear
placas registradas en otro formato (motos, vehículos migrados). La placa se
considera la misma con o sin guion: `ABC-1234` y `ABC1234` no pueden registrarse
dos veces, y el buscador del listado y los de Car-Wash y Taller encuentran la placa
escrita de cualquiera de las dos formas.

## Traer marca, modelo y año desde el SRI (consulta por placa)

Junto a la placa está el botón **SRI**. Escriba la placa y púlselo: el sistema
consulta el SRI y llena **Marca y modelo** (por ejemplo
`GREAT WALL M4 MT AC 1.5 5P 4X2 TM`) y **Año**. Debajo de la placa se ve lo que
respondió el SRI (marca, modelo, año y país).

- En un vehículo **nuevo**, al completar la placa y salir del campo, la consulta se
  hace sola si *Marca y modelo* está vacío, y solo llena los campos vacíos. El
  botón, en cambio, reemplaza lo que ya esté escrito.
- El SRI **no entrega el propietario**, el chasis ni el teléfono: esos se siguen
  escribiendo a mano.
- Es una ayuda: si el SRI no responde o no encuentra la placa, aparece un aviso y
  los datos se escriben a mano como siempre. Nada se guarda hasta pulsar **Guardar**.

## Buscar y filtrar el listado

El cuadro de búsqueda busca en todas las columnas (marca, placa, chasis, año,
propietario, correo, teléfono, modelo, color y fecha de registro). Se pueden escribir
varias palabras en cualquier orden y no importan mayúsculas ni tildes.

La placa se encuentra con o sin guion: `pdj4685` encuentra `PDJ-4685` y
`PDJ-4685` encuentra una placa guardada como `PDJ4685`. También sirve una parte
(por ejemplo `4685`).

El botón del **embudo** abre la ventana de **filtros**: placa, marca, modelo, color,
chasis, año (desde / hasta), estado, propietario, correo, teléfono, fecha de registro,
usuario que lo registró y, de Car-Wash, **con o sin órdenes**, **última visita** y
**próxima cita** (útil para ver qué vehículos tienen cita esta semana). Cada filtro
aplicado aparece como una etiqueta en el cuadro de búsqueda; la **×** lo quita.

El listado se puede ordenar por cualquier columna, ocultar columnas y exportar a PDF y
Excel con los filtros aplicados.

## Pestaña Transacciones

Muestra todas las **órdenes de Car-Wash** del vehículo, de la más reciente a la más
antigua, con las columnas **Fecha, N° Orden, Cliente, Total, Documento** (factura o
recibo) y **Estado**. Un clic en la orden despliega sus ítems (código, descripción,
categoría y marca del producto, cantidad × precio, descuento y total) y, al pie, el
kilometraje con el que ingresó.

Arriba se resume: número de visitas, total (sin las órdenes anuladas), fecha de la
última visita y la próxima cita.

### Buscar un ítem en las órdenes

Para saber en qué órdenes se aplicó un servicio o producto:

- **Buscar ítem**: escriba el código o parte de la descripción, la categoría o la
  marca (por ejemplo *aceite* o *filtro aire*). Se aceptan varias palabras en
  cualquier orden y no importan mayúsculas ni tildes. También sirve el número de
  orden o de la factura o recibo.
- **Categoría** y **Marca**: listas con las categorías y marcas de los productos que
  se le han aplicado a este vehículo. Se combinan con el texto: por ejemplo, marca
  *Mobil* + texto *aceite* deja solo las órdenes con un aceite de esa marca.

Solo quedan a la vista las órdenes que tienen lo buscado, **desplegadas** y mostrando
**únicamente los ítems que coinciden** (resaltados en amarillo); los demás ítems de
esa orden se ocultan. Si la orden se encontró por su número de orden o de documento,
se ven todos sus ítems. Al lado se indica cuántas órdenes coinciden (por ejemplo
*3 de 12 órdenes*). **Limpiar** borra el texto y los filtros, y las órdenes vuelven
a encogerse con todos sus ítems.

Si abre el vehículo desde la pantalla de Car-Wash, el enlace **Abrir la orden** abre
esa orden.

## Pestaña Recordatorios

**Citas y recordatorios del vehículo:** en una sola lista, ordenada por fecha, salen:

- la **próxima cita de cada orden** de Car-Wash (columna *Orden / Motivo* con el
  número de orden), y
- los **recordatorios agregados a mano** (con el ícono de marcador y su motivo).

Cada fila se marca como *próxima*, *hoy* o *pasada*, con el correo y el teléfono a
los que se avisará y la fecha del último aviso. Botones:

- **Correo**: abre una ventana con el destinatario, el asunto y el mensaje ya
  escritos (se pueden cambiar) y el sistema lo envía.
- **WhatsApp**: abre WhatsApp (web o aplicación) con el mensaje listo para enviar. El
  sistema deja constancia del envío.
- **Editar** y **Eliminar**: solo en los recordatorios agregados a mano.

### Agregar un recordatorio

Para avisar al cliente de algo que no depende de una orden (cambio de aceite,
revisión de frenos, cambio de llantas, etc.):

1. Con el vehículo guardado, abra la pestaña **Recordatorios**.
2. Pulse **Agregar recordatorio**.
3. Elija la **fecha**, escriba el **motivo** y, si quiere, una **observación** (uso
   interno; no se envía al cliente).
4. Pulse **Guardar**. El recordatorio aparece en la lista y desde ahí se envía por
   correo o WhatsApp, igual que una cita de una orden.

El mensaje por defecto incluye el motivo (*"…tiene su próxima cita el 04-11-2026
para: Alineación"*). La fecha no puede ser anterior a hoy. Al eliminar un
recordatorio, los avisos que ya se enviaron siguen en el historial.

**Recordatorios enviados:** historial de todos los avisos del vehículo: cuándo, de
qué cita, por qué canal, a quién, si se envió o dio error, si fue manual o
automático y qué usuario lo hizo.

El correo y el teléfono se toman de la ficha del **vehículo** y, si no los tiene, del
**cliente** de la orden (en un recordatorio agregado, del cliente de la última orden
de Car-Wash del vehículo). El saludo usa el nombre del cliente o, si la orden es a
Consumidor Final o no hay cliente, el del propietario del vehículo.

## Recordatorios automáticos

En **Automatizaciones** cree una tarea del módulo **Car-Wash**:

- **Recordatorio de próxima cita (Correo)**: con los días de anticipación, el asunto y
  el mensaje (etiquetas {cliente} {placa} {marca} {fecha_cita} {empresa} {orden}
  {motivo}). {motivo} solo tiene valor en los recordatorios agregados a mano y
  {orden} solo en las citas de las órdenes. Si deja el mensaje vacío, cada aviso usa
  el mensaje por defecto (el de los recordatorios agregados lleva el motivo).
- **Recordatorio de próxima cita (WhatsApp)**: con los días de anticipación y la
  plantilla aprobada por Meta. Variables del cuerpo, en orden: {{1}} cliente,
  {{2}} placa, {{3}} fecha de la cita, {{4}} empresa, {{5}} N.° de orden (en un
  recordatorio agregado a mano, que no tiene orden, va su motivo).
- **Recordatorio de próxima cita (Correo y WhatsApp)**: los dos canales en la misma
  tarea (correo a quien tenga correo, WhatsApp a quien tenga teléfono).

Programada a diario, avisa las citas de las órdenes **y los recordatorios agregados a
mano** que caen entre hoy y hoy + los días indicados (los agregados a mano no
dependen del establecimiento de la automatización).
**Cada cita se avisa una sola vez por canal**, aunque la tarea corra varias veces.
Los envíos aparecen en el historial de la pestaña Recordatorios como *Automático*.
Si en una ejecución ninguno sale (por ejemplo, el correo de la empresa no está
configurado o falta la API de WhatsApp), la ejecución queda como **Error** en el
historial de la automatización, con el motivo; esas citas se vuelven a intentar en la
siguiente ejecución.

**Qué se necesita para que salgan:** para correo, el correo de salida configurado en
Empresa; para WhatsApp, la API de WhatsApp configurada y una plantilla aprobada por
Meta con las variables en el orden indicado.

## Pestaña Mantenimiento (próximo cambio de aceite y otros por km)

Muestra, por cada servicio de mantenimiento de la empresa (aceite de motor, aceite de
caja y los que se agreguen), el **intervalo** del vehículo (*Cada (km)*), el **último
cambio**, el **próximo cambio** y el **estado** (*Al día*, *Próximo* o *Vencido*),
calculado con el **último km conocido** del vehículo (el mayor entre sus órdenes de
Car-Wash y sus cambios registrados). La pestaña lleva un contador rojo con los vencidos.

- **Cada (km)**: cambiarlo lo deja como intervalo propio del vehículo (en blanco vuelve
  al sugerido). Si el vehículo no tiene uno propio, se sugiere el más usado entre los de
  la misma marca y modelo con el mismo **Motor**, luego de la misma marca y modelo y
  luego de la misma marca; si no hay ninguno, el del servicio. El botón del diagrama lo
  aplica a los demás vehículos de la misma marca y modelo o de toda la marca.
- **Registrar cambio** (botón **+** de cada servicio): para un cambio hecho en otro lugar o sin
  orden: km, fecha, próximo cambio (se calcula con el intervalo) y una observación.
- **Cambios realizados**: historial con la orden de Car-Wash en que se hizo o *A mano*, y la
  **Observación** completa de los registrados a mano.
  Los registrados a mano se pueden eliminar aquí; los de una orden se quitan desde la orden.
- **Servicios**: lista de servicios de la empresa (nombre, cada cuántos km, cuántos km
  antes avisar, orden, activo).

Normalmente el cambio se marca en la orden de Car-Wash, pestaña **Mantenimiento**
(ver el manual de Car-Wash).

## Avisos de próximo mantenimiento al cliente

**Km estimado de hoy.** El sistema solo conoce el km cuando el vehículo llega, así que lo
estima: toma el último km registrado (órdenes de Car-Wash y cambios) y le suma lo que el
vehículo suele recorrer por día, calculado con sus lecturas de km del último año (deben
tener al menos 14 días de diferencia). Si solo tiene un km registrado, se supone
**1.000 km al mes**. La pestaña Mantenimiento muestra el estimado y los km por día.

**Aviso automático.** En **Automatizaciones** cree una tarea **Car-Wash → Aviso de próximo
mantenimiento** (Correo, WhatsApp, o Correo y WhatsApp) y prográmela a diario. En cada
ejecución avisa a los clientes cuyos servicios están **próximos** (faltan los *km de aviso*
del servicio o menos) o **vencidos**:

| Parámetro | Para qué sirve |
|-----------|----------------|
| Si sigue vencido, volver a avisar cada (días) | Después del primer aviso, si el cambio no se hizo y el km estimado ya lo pasó, se recuerda cada estos días. 0 = solo el primer aviso. |
| Máximo de recordatorios por vencido | Tope de recordatorios para un mismo cambio (sin contar el primer aviso). |
| Km por mes de un vehículo con una sola lectura | Recorrido supuesto cuando el vehículo tiene un solo km registrado. |
| Servicios a avisar | Nombres separados por coma (p. ej. *Aceite de motor, Aceite de caja*); vacío = todos los activos. |
| Asunto / Mensaje (correo) | Etiquetas: {cliente} {placa} {marca} {servicios} {km_estimado} {empresa}. {servicios} es la lista con el km de cada uno. |
| Plantilla de WhatsApp | Aprobada por Meta. Variables del cuerpo en orden: {{1}} cliente, {{2}} placa, {{3}} servicios con su km, {{4}} empresa, {{5}} km estimado. El cuerpo no puede terminar en una variable. |

Se envía **un solo mensaje por vehículo** con todos sus servicios por avisar. Cada cambio se
avisa una vez por canal (y los recordatorios configurados); cuando se registra el cambio,
el próximo pasa a otro km y los avisos empiezan de nuevo. Un correo que falla no cuenta como
enviado y se reintenta en la siguiente ejecución.

Ejemplo de plantilla de WhatsApp (categoría *Utilidad*): *Hola {{1}}, a su vehículo {{2}}
le corresponde: {{3}}. Le esperamos en {{4}}. Responda a este mensaje para agendar.*

**Aviso manual.** En la pestaña Mantenimiento, los botones de **correo** y **WhatsApp**
arman el mensaje con los servicios próximos o vencidos (si no hay, con todos los que tienen
próximo cambio). El correo lo envía el sistema; WhatsApp se abre con el mensaje listo.

**Historial.** La tabla **Avisos enviados al cliente** muestra cada aviso (servicio, km,
canal, destinatario, estado, automático o manual). Destinatario: el correo y teléfono del
vehículo y, si no tiene, los del cliente de su última orden de Car-Wash.

**Filtro del listado.** En la ventana de filtros, **Mantenimiento** (*Vencido*, *Próximo*,
*Vencido o próximo*, *Al día*) muestra los vehículos según su servicio más urgente con el
km estimado de hoy: sirve para llamar a los clientes.

## Campos del formulario

| Campo | Para qué sirve |
|-------|----------------|
| Placa | Identifica al vehículo; única por empresa (con o sin guion). Formato `AAA-1111` en vehículos nuevos. Obligatorio. |
| Marca y modelo | Marca y modelo del vehículo en un solo campo; se llena desde el SRI. Obligatorio. |
| Motor | Motor del vehículo (p. ej. *1.6 GASOLINA*). Opcional; sirve para sugerir los km de mantenimiento según otros vehículos con el mismo motor. |
| Chasis / Año | Datos del vehículo. Opcionales; si se escribe el año debe estar entre 1900 y 2100. |
| Estado | Activo o inactivo (los inactivos no aparecen al buscar en las órdenes). |
| Propietario | Dueño del vehículo. Opcional. |
| Correo / Teléfono | Contacto para los recordatorios (teléfono de 10 dígitos). |

## Permisos

- **Ver**: listado, ficha, transacciones y recordatorios.
- **Crear / Modificar / Eliminar**: la ficha del vehículo. **Enviar recordatorios** y
  **agregar o editar** un recordatorio requieren permiso de modificar; **eliminarlo**,
  permiso de eliminar.
- **Mantenimiento**: cambiar *Cada (km)*, registrar un cambio y editar los Servicios
  requieren permiso de modificar; eliminar un cambio registrado a mano, permiso de eliminar.
- **Acceso total**: sin él, el usuario ve solo los vehículos, las órdenes y los
  recordatorios que él registró (y solo edita o elimina los suyos).

## Reglas de negocio

- La placa no se repite dentro de la empresa.
- Las transacciones y citas respetan el ambiente (pruebas / producción) de la empresa
  y no incluyen órdenes eliminadas; las anuladas se muestran pero no suman.
- El envío automático nunca repite el aviso de una cita por el mismo canal; el manual
  sí se puede repetir cuando el usuario lo decide.

## Integraciones con otros módulos

- **Servicio de car wash**: fuente de las transacciones y de las próximas citas.
- **Taller**: también usa esta ficha de vehículo.
- **Automatizaciones**: envío automático de recordatorios.
- **WhatsApp / Plantillas de WhatsApp**: envío automático con plantilla de Meta; los
  mensajes quedan en el Chat.

## Errores frecuentes

- **"El vehículo no existe en el SRI"**: la placa está mal escrita o el vehículo no
  está registrado en el SRI (por ejemplo, uno nuevo aún sin placa definitiva). Escriba
  los datos a mano.
- **"El SRI no respondió"**: el servicio del SRI está caído o lento. Vuelva a intentar
  más tarde o escriba los datos a mano.

- **Los botones de aviso están deshabilitados**: falta permiso de modificar, el vehículo no
  tiene próximos cambios, o falta el SQL `database/migrations/20261031_mantenimientos_avisos.sql`.
- **La automatización de mantenimiento queda en error**: ningún aviso salió; el motivo está
  en el log de la automatización y en *Avisos enviados al cliente* (pase el mouse sobre *Error*).
- **La pestaña Mantenimiento avisa que falta el SQL**: el administrador debe ejecutar
  `database/migrations/20261030_vehiculos_mantenimientos.sql`.
- **Las pestañas Transacciones, Recordatorios y Mantenimiento están deshabilitadas**: el vehículo
  aún no se ha guardado.
- **No aparecen citas**: la próxima cita se fija en la orden de Car-Wash; o agregue un
  recordatorio con **Agregar recordatorio**.
- **El botón Agregar recordatorio está deshabilitado**: falta permiso de modificar
  Vehículos, o (aviso amarillo en la pestaña) el administrador aún no aplicó la
  actualización de la base de datos.
- **"Ni el vehículo ni el cliente tienen correo registrado"**: escriba el correo en la
  ventana de envío o regístrelo en la ficha del vehículo o del cliente.
- **El recordatorio sale con error**: revise la configuración de correo de la empresa;
  el detalle del error aparece al pasar el mouse sobre la etiqueta *Error*.

## Historial de cambios

- **1.14** — El cambio hecho a mano se registra con el botón **+** de la línea de cada servicio (antes, un botón general con selector de servicio). La tabla *Cambios realizados* muestra la observación en su propia columna, completa.
- **1.13** — **Avisos de próximo mantenimiento**: automatización *Car-Wash → Aviso de próximo mantenimiento* (correo, WhatsApp o ambos) con primer aviso y recordatorios si sigue vencido; avisos manuales desde la pestaña Mantenimiento; historial de avisos; km estimado de hoy; filtro **Mantenimiento** en el listado. Requiere el SQL `database/migrations/20261031_mantenimientos_avisos.sql`.
- **1.12** — Nueva pestaña **Mantenimiento**: próximo cambio de aceite de motor, de caja y otros servicios por kilometraje, con intervalo por vehículo (sugerido por la marca, el modelo y el motor), registro manual de cambios y lista de **Servicios** de la empresa. Nuevo campo **Motor**.
- **1.11** — Pestaña **Recordatorios**: botón **Agregar recordatorio** (fecha + motivo + observación) para avisar al cliente sin necesidad de una orden; se editan, eliminan y envían por correo o WhatsApp desde la misma lista, y la automatización también los avisa.
- **1.10** — Pestaña **Transacciones**: al buscar o filtrar, cada orden muestra solo los ítems que coinciden; los demás ya no aparecen.
- **1.9** — Pestaña **Transacciones**: filtros por **Categoría** y **Marca** del producto (y el buscador también encuentra por categoría o marca); se quitaron las columnas Km y Servicios/productos (el km va al pie de cada orden desplegada).
- **1.8** — Pestaña **Transacciones**: nuevo cuadro **Buscar ítem** para encontrar en qué órdenes se aplicó un servicio o producto (por código o descripción); las órdenes encontradas se despliegan con el ítem resaltado.
- **1.7** — El buscador del listado encuentra la **placa sin guion** (`pdj4685` → `PDJ-4685`) y también con guion cuando se guardó sin él.
- **1.6** — En el listado, la primera columna es **Placa** y le sigue **Marca y
  modelo** (antes iba primero la marca). El mismo orden se aplica al exportar a PDF
  y Excel.
- **1.5** — La **placa** va primero y, en vehículos nuevos, usa el formato
  `AAA-1111`. El campo **Marca** pasa a llamarse **Marca y modelo** y se llena desde
  el SRI con la marca y el modelo. `ABC-1234` y `ABC1234` cuentan como la misma
  placa al validar duplicados y al buscar en Car-Wash y Taller.

- **1.4** — Botón **SRI** junto a la placa: trae marca y año del vehículo desde el
  SRI (consulta por placa). En un vehículo nuevo se consulta solo al salir de la placa.

- **1.3** — **Año** y **Propietario** dejan de ser obligatorios al crear o editar un
  vehículo (solo lo son marca y placa). Si no tiene año, el listado lo deja en blanco.

- **1.2** — Automatización **Correo y WhatsApp** en una sola tarea. Si ningún
  recordatorio sale, la ejecución queda como error con el motivo (antes figuraba como
  exitosa).
- **1.1** — Nuevas pestañas **Transacciones** (órdenes de Car-Wash del vehículo con su
  detalle y resumen) y **Recordatorios** (próximas citas con envío por correo o
  WhatsApp e historial de avisos). Nueva automatización **Car-Wash → Recordatorio de
  próxima cita** (correo o WhatsApp), que avisa una sola vez por cita. El listado usa
  el buscador estándar con **ventana de filtros** (incluye última visita, próxima cita
  y con / sin órdenes) y ya ordena por Correo, Teléfono y Fecha de registro.
- **1.0** — Versión inicial.
