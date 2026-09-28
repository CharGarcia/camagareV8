---
titulo: Vehículos
resumen: Ficha de cada vehículo con su historial de órdenes de Car-Wash y los recordatorios de su próxima cita.
categoria: Servicios
ruta_modulo: modulos/vehiculos
tipo: modulo
visibilidad: todos
etiquetas: vehiculos, vehiculo, carro, auto, placa, propietario, dueño, historial del vehiculo, transacciones, visitas, ordenes car wash, lavado, taller, proxima cita, recordatorio, recordar cita, aviso al cliente, whatsapp, correo, automatizacion, buscar placa, filtros
version: 1.1
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
2. En la pestaña **General** registre marca, placa (única por empresa), chasis, año,
   estado, propietario, correo y teléfono, y pulse **Guardar**.
3. Con el vehículo guardado, use las pestañas **Transacciones** y **Recordatorios**.

Cada usuario puede ocultar las pestañas que no use con el engranaje a la derecha de
las pestañas.

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

Programada a diario, avisa las citas que caen entre hoy y hoy + los días indicados.
**Cada cita se avisa una sola vez por canal**, aunque la tarea corra varias veces.
Los envíos aparecen en el historial de la pestaña Recordatorios como *Automático*.

## Campos del formulario

| Campo | Para qué sirve |
|-------|----------------|
| Marca | Marca del vehículo. Obligatorio. |
| Placa | Identifica al vehículo; única por empresa. Obligatorio. |
| Chasis / Año | Datos del vehículo. |
| Estado | Activo o inactivo (los inactivos no aparecen al buscar en las órdenes). |
| Propietario | Dueño del vehículo. Obligatorio. |
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

- **Las pestañas Transacciones y Recordatorios están deshabilitadas**: el vehículo
  aún no se ha guardado.
- **No aparecen citas**: la próxima cita se fija en la orden de Car-Wash.
- **"Ni el vehículo ni el cliente tienen correo registrado"**: escriba el correo en la
  ventana de envío o regístrelo en la ficha del vehículo o del cliente.
- **El recordatorio sale con error**: revise la configuración de correo de la empresa;
  el detalle del error aparece al pasar el mouse sobre la etiqueta *Error*.

## Historial de cambios

- **1.1** — Nuevas pestañas **Transacciones** (órdenes de Car-Wash del vehículo con su
  detalle y resumen) y **Recordatorios** (próximas citas con envío por correo o
  WhatsApp e historial de avisos). Nueva automatización **Car-Wash → Recordatorio de
  próxima cita** (correo o WhatsApp), que avisa una sola vez por cita. El listado usa
  el buscador estándar con **ventana de filtros** (incluye última visita, próxima cita
  y con / sin órdenes) y ya ordena por Correo, Teléfono y Fecha de registro.
- **1.0** — Versión inicial.
