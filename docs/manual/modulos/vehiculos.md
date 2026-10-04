---
titulo: Vehículos
resumen: Ficha de cada vehículo con su historial de órdenes de Car-Wash y los recordatorios de su próxima cita.
categoria: Servicios
ruta_modulo: modulos/vehiculos
tipo: modulo
visibilidad: todos
etiquetas: vehiculos, vehiculo, carro, auto, placa, propietario, dueño, consulta sri, consultar placa, datos del vehiculo por placa, matricula, marca, año, historial del vehiculo, transacciones, visitas, ordenes car wash, lavado, taller, proxima cita, recordatorio, recordar cita, aviso al cliente, whatsapp, correo, automatizacion, buscar placa, filtros
version: 1.5
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
dos veces, y los buscadores de vehículos de Car-Wash y Taller encuentran la placa
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

El botón del **embudo** abre la ventana de **filtros**: placa, marca, modelo, color,
chasis, año (desde / hasta), estado, propietario, correo, teléfono, fecha de registro,
usuario que lo registró y, de Car-Wash, **con o sin órdenes**, **última visita** y
**próxima cita** (útil para ver qué vehículos tienen cita esta semana). Cada filtro
aplicado aparece como una etiqueta en el cuadro de búsqueda; la **×** lo quita.

El listado se puede ordenar por cualquier columna, ocultar columnas y exportar a PDF y
Excel con los filtros aplicados.

## Pestaña Transacciones

Muestra todas las **órdenes de Car-Wash** del vehículo, de la más reciente a la más
antigua: fecha, número de orden, cliente, kilometraje, servicios y productos, total,
documento emitido (factura o recibo) y estado. Un clic en la orden despliega sus
líneas (código, descripción, cantidad × precio, descuento y total).

Arriba se resume: número de visitas, total (sin las órdenes anuladas), fecha de la
última visita y la próxima cita.

Si abre el vehículo desde la pantalla de Car-Wash, el enlace **Abrir la orden** abre
esa orden.

## Pestaña Recordatorios

**Citas del vehículo:** cada orden con próxima cita, marcada como *próxima*, *hoy* o
*pasada*, con el correo y el teléfono a los que se avisará y la fecha del último
aviso. Botones:

- **Correo**: abre una ventana con el destinatario, el asunto y el mensaje ya
  escritos (se pueden cambiar) y el sistema lo envía.
- **WhatsApp**: abre WhatsApp (web o aplicación) con el mensaje listo para enviar. El
  sistema deja constancia del envío.

**Recordatorios enviados:** historial de todos los avisos del vehículo: cuándo, de
qué cita, por qué canal, a quién, si se envió o dio error, si fue manual o
automático y qué usuario lo hizo.

El correo y el teléfono se toman de la ficha del **vehículo** y, si no los tiene, del
**cliente** de la orden. El saludo usa el nombre del cliente o, si la orden es a
Consumidor Final, el del propietario del vehículo.

## Recordatorios automáticos

En **Automatizaciones** cree una tarea del módulo **Car-Wash**:

- **Recordatorio de próxima cita (Correo)**: con los días de anticipación, el asunto y
  el mensaje (etiquetas {cliente} {placa} {marca} {fecha_cita} {empresa} {orden}).
- **Recordatorio de próxima cita (WhatsApp)**: con los días de anticipación y la
  plantilla aprobada por Meta. Variables del cuerpo, en orden: {{1}} cliente,
  {{2}} placa, {{3}} fecha de la cita, {{4}} empresa, {{5}} N.° de orden.
- **Recordatorio de próxima cita (Correo y WhatsApp)**: los dos canales en la misma
  tarea (correo a quien tenga correo, WhatsApp a quien tenga teléfono).

Programada a diario, avisa las citas que caen entre hoy y hoy + los días indicados.
**Cada cita se avisa una sola vez por canal**, aunque la tarea corra varias veces.
Los envíos aparecen en el historial de la pestaña Recordatorios como *Automático*.
Si en una ejecución ninguno sale (por ejemplo, el correo de la empresa no está
configurado o falta la API de WhatsApp), la ejecución queda como **Error** en el
historial de la automatización, con el motivo; esas citas se vuelven a intentar en la
siguiente ejecución.

**Qué se necesita para que salgan:** para correo, el correo de salida configurado en
Empresa; para WhatsApp, la API de WhatsApp configurada y una plantilla aprobada por
Meta con las variables en el orden indicado.

## Campos del formulario

| Campo | Para qué sirve |
|-------|----------------|
| Placa | Identifica al vehículo; única por empresa (con o sin guion). Formato `AAA-1111` en vehículos nuevos. Obligatorio. |
| Marca y modelo | Marca y modelo del vehículo en un solo campo; se llena desde el SRI. Obligatorio. |
| Chasis / Año | Datos del vehículo. Opcionales; si se escribe el año debe estar entre 1900 y 2100. |
| Estado | Activo o inactivo (los inactivos no aparecen al buscar en las órdenes). |
| Propietario | Dueño del vehículo. Opcional. |
| Correo / Teléfono | Contacto para los recordatorios (teléfono de 10 dígitos). |

## Permisos

- **Ver**: listado, ficha, transacciones y recordatorios.
- **Crear / Modificar / Eliminar**: la ficha del vehículo. **Enviar recordatorios**
  requiere permiso de modificar.
- **Acceso total**: sin él, el usuario ve solo los vehículos y las órdenes que él
  registró.

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

- **Las pestañas Transacciones y Recordatorios están deshabilitadas**: el vehículo
  aún no se ha guardado.
- **No aparecen citas**: la próxima cita se fija en la orden de Car-Wash.
- **"Ni el vehículo ni el cliente tienen correo registrado"**: escriba el correo en la
  ventana de envío o regístrelo en la ficha del vehículo o del cliente.
- **El recordatorio sale con error**: revise la configuración de correo de la empresa;
  el detalle del error aparece al pasar el mouse sobre la etiqueta *Error*.

## Historial de cambios

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
