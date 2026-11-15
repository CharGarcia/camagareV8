---
titulo: Portal de reservas
resumen: Página pública donde los clientes reservan sus citas sin usuario ni contraseña; desde aquí se comparte el enlace, el código QR y el código para la página web.
categoria: Servicios
ruta_modulo: modulos/citas-portal
tipo: modulo
visibilidad: todos
etiquetas: portal de reservas, portal de citas, reservas en linea, reservar cita, agendar en linea, citas por internet, pagina publica, link de reservas, enlace de reservas, compartir enlace, codigo qr, iframe, insertar en mi web, pagina web, widget de reservas, slug, horarios disponibles, turnos, turnos en linea, autoagendamiento, cliente reserva solo, confirmacion manual, reserva pendiente, reserva confirmada, anticipacion, precio, anticipo, whatsapp, email, ultimas reservas, estadisticas
version: 1.0
orden: 0
estado: activo
---

El **Portal de reservas** es una página pública en la que los clientes de la
empresa eligen el tipo de cita, el día y la hora, y dejan su reserva sin
necesidad de usuario ni contraseña. Las reservas llegan a la
[Agenda de citas](modulos/citas-agenda) como citas de origen **portal**.

Este módulo es el panel del portal: muestra si está activo, el enlace para
compartir, el código QR, el código para insertarlo en la página web de la
empresa y las últimas reservas recibidas.

## Qué es y para qué sirve

- Que los clientes reserven por su cuenta a cualquier hora, viendo solo los
  horarios que están libres.
- Compartir el enlace del portal por WhatsApp, correo o con un código QR.
- Poner el portal dentro de la página web de la empresa con un código
  **iframe**.
- Ver de un vistazo cuántas reservas han llegado por el portal y en qué estado
  están.

## Requisitos previos

En el módulo **Configuración de citas** (opción propia del menú) debe estar
configurado:

- Los **tipos de cita** activos (nombre, duración y, si aplica, precio). Si no
  hay ninguno, el portal muestra *No hay tipos de cita disponibles en este
  momento.*
- Los **horarios** de atención por día de la semana.
- Los **recursos** (personas, salas o equipos), si la empresa trabaja con ellos.
- La pestaña **Portal de Reservas**, con el portal guardado y marcado como
  activo (ver abajo).

## Cómo activar el portal

1. Entre a **Configuración de citas** → pestaña **Portal de Reservas** (o pulse
   **Configurar portal** en este módulo).
2. Escriba el **Slug (URL amigable)**: es la última parte de la dirección del
   portal, por ejemplo `mi-empresa`. Solo letras minúsculas, números y guiones,
   con un mínimo de 3 caracteres.
3. Opcional: **Título del portal**, **Color principal** y **Mensaje de
   bienvenida**.
4. Indique **Máx. días de anticipación** y **Mín. horas de anticipación**.
5. Marque **Portal activo (visible al público)** y, si quiere revisar cada
   reserva antes de aceptarla, **Requiere confirmación manual**.
6. Pulse **Guardar configuración del portal**.

La dirección pública del portal queda así:

```
https://(dirección del sistema)/reservas/(slug)
```

## La pantalla del portal

Si el portal todavía no se ha configurado, el módulo muestra *El portal aún no
está configurado* y el botón **Ir a configuración**. Una vez configurado se ve:

- **Tarjetas**: **Total portal** (todas las reservas hechas por el portal),
  **Pendientes**, **Confirmadas** y **Últimos 30 días**.
- **Estado del portal**: **Activo** o **Inactivo**. Si está inactivo, aparece el
  botón **Activar portal**, que lleva a la configuración.
- **Link público** (solo con el portal activo): botón para copiarlo, botón para
  abrir el portal, botones **WhatsApp** y **Email** para compartirlo y el
  **Código QR** del enlace.
- **Código para tu web (iframe)** (solo con el portal activo): el código listo
  para pegar en la página web de la empresa, con el botón **Copiar código**.
- **Últimas reservas del portal**: las 15 reservas más recientes, con **#**,
  **Fecha cita**, **Cliente**, **Tipo**, **Estado** y **Registrada** (cuándo se
  hizo la reserva). Tiene un cuadro **Filtrar...** que busca en la tabla, orden
  por clic en cada encabezado y selector de columnas.
- **Ver agenda**: botón (arriba) y enlace (sobre la tabla) para ir a la [Agenda de citas](modulos/citas-agenda).

## Poner el portal en la página web

Copie el texto de **Código para tu web (iframe)** y péguelo en la página de su
sitio donde quiera que aparezca el formulario de reservas. El código muestra el
portal a todo el ancho disponible y con 700 píxeles de alto. El portal es la
única pantalla del sistema que se puede insertar así dentro de otra web.

## Qué ve y qué llena el cliente

El cliente abre el enlace y avanza por pasos:

1. **Tipo**: *¿Qué tipo de cita necesitas?* Ve cada tipo con su descripción,
   duración en minutos y, si tiene precio, el valor (y el porcentaje de anticipo
   cuando el tipo trabaja con anticipo).
2. **Recurso**: *¿Con quién / en qué sala?* Aparece solo cuando hay más de un
   recurso para ese tipo de cita. Puede elegir uno o **Sin preferencia**. Si el
   tipo tiene un único recurso, se asigna solo y este paso se salta.
3. **Fecha y hora**: elige el día en un calendario y luego uno de los
   **Horarios disponibles**. Los días pasados y los que superan los días máximos
   de anticipación aparecen desactivados.
4. **Tus datos**: escribe su **Cédula / RUC** o su **Email** y pulsa
   **Verificar**.
   - Si ya es cliente de la empresa, se muestra su nombre y la reserva queda a
     su nombre.
   - Si no lo encuentra, aparece *No encontramos tu cuenta. Completa tus datos:*
     con **Nombres** (obligatorio), **Apellidos**, **Cédula / RUC**, **Email** y
     **Teléfono**.
5. **Confirmar**: revisa el resumen (tipo de cita, fecha y hora, con quién,
   cliente y precio), puede escribir **Notas u observaciones (opcional)** y
   pulsa **Confirmar reserva**.

Al terminar ve la página **¡Reserva registrada!** con la fecha y hora, el tipo,
con quién, el estado y el **Nº de reserva**, y el botón **Nueva reserva**.

## Cómo se calculan los horarios disponibles

- Se toman los **horarios de atención** del día de la semana elegido. Si el
  cliente eligió un recurso que tiene horarios propios, se usan esos; si no, los
  horarios generales.
- Ese horario se divide en espacios del largo del **tipo de cita** (por ejemplo,
  de 30 en 30 minutos).
- Se quitan los espacios que se cruzan con otra cita ya agendada (las canceladas
  y las marcadas como *No asistió* no ocupan espacio). Si el cliente eligió un
  recurso, solo cuentan las citas de ese recurso; con **Sin preferencia** cuentan
  todas las citas de la empresa en ese día.
- Se quitan los espacios que empiezan antes de las **horas mínimas de
  anticipación** contadas desde ahora.

## Confirmación de la reserva

- Con **Requiere confirmación manual** activado, la cita entra a la agenda como
  **Pendiente** y el cliente ve el aviso *Tu reserva está pendiente de
  confirmación. La empresa se pondrá en contacto contigo.*
- Sin esa opción, la cita entra como **Confirmada** y el cliente ve *Tu reserva
  ha sido confirmada automáticamente.*

La reserva se gestiona después desde la [Agenda de citas](modulos/citas-agenda),
donde aparece con origen **portal**.

## Precio y pagos

El portal **muestra** el precio del tipo de cita y, si trabaja con anticipo, el
valor del anticipo, pero **no cobra**: la reserva se registra sin pago. Aunque
en la configuración esté marcado **Habilitar pagos online**, el portal no pide
ningún pago. Los cobros de la cita se registran en
[Pagos de citas](modulos/citas-pagos).

## Campos de la configuración del portal

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Slug (URL amigable) | Sí | Última parte de la dirección del portal. Letras minúsculas, números y guiones; mínimo 3 caracteres. No puede repetirse entre empresas. |
| Título del portal | No | Título que ve el cliente arriba. Si se deja vacío, el portal dice *Reserva tu cita*. |
| Color principal | No | Color de la cabecera y de los botones del portal. |
| Mensaje de bienvenida | No | Texto bajo el título. Si se deja vacío, se muestra *Selecciona el tipo de cita y elige la fecha y hora que prefieras.* |
| Máx. días de anticipación | Sí | Hasta cuántos días hacia adelante se puede reservar (de 1 a 365). |
| Mín. horas de anticipación | Sí | Cuántas horas antes, como mínimo, se puede reservar un horario (de 0 a 168). |
| Portal activo (visible al público) | No | Si está apagado, el enlace muestra *Portal no disponible*. |
| Requiere confirmación manual | No | Las reservas entran como Pendiente en lugar de Confirmada. |
| Habilitar pagos online | No | Se guarda con la configuración, pero hoy el portal no cobra en línea. |

## Permisos

- Para entrar a este módulo basta el permiso de **ver**.
- La configuración del portal se guarda en **Configuración de citas** y exige
  permiso de **crear** o **actualizar** en ese módulo.
- El cliente que reserva no necesita usuario: el portal es público.

## Reglas de negocio

- El portal solo abre si está **activo** y si la **empresa está activa**. Si la
  empresa está inactiva, el enlace se comporta igual que un portal que no existe.
- El cliente que se verifica se busca entre los clientes de la empresa por
  **cédula/RUC** o por **correo**.
- Si no es cliente, sus datos quedan guardados **con la reserva**; no se crea un
  cliente en el catálogo de Clientes.
- El sistema no envía correo ni mensaje al cliente al reservar: la página de
  confirmación, con su **Nº de reserva**, es su comprobante.

## Integraciones con otros módulos

- **Configuración de citas**: tipos de cita, recursos, horarios y la pestaña
  **Portal de Reservas**.
- [Agenda de citas](modulos/citas-agenda): recibe cada reserva como una cita de
  origen portal, donde se confirma, atiende o cancela.
- [Pagos de citas](modulos/citas-pagos): donde se registran los cobros de las
  citas reservadas.
- [Clientes](modulos/clientes): el portal reconoce a los clientes ya
  registrados.

## Errores frecuentes

- **El portal aún no está configurado**: falta guardar la pestaña Portal de
  Reservas en Configuración de citas.
- **Portal no disponible — El portal de reservas no existe o no está activo.**:
  el portal está apagado, el slug cambió o la empresa está inactiva.
- **No hay tipos de cita disponibles en este momento.**: no hay tipos de cita
  activos en Configuración de citas.
- **No hay horarios disponibles para esta fecha.**: ese día no tiene horario de
  atención, ya está todo ocupado o los horarios libres están dentro de las horas
  mínimas de anticipación.
- **Solo se pueden hacer reservas con hasta … días de anticipación.**: la fecha
  supera el máximo configurado.
- **No se puede reservar en fechas pasadas.**
- **Ingresa tu cédula o email para buscar.**: el cliente pulsó Verificar sin
  escribir ninguno de los dos.
- **El nombre es obligatorio.**: el cliente nuevo no escribió sus Nombres.
- **El slug ya está en uso por otra empresa. Elige uno diferente.**
- **El slug solo puede contener letras minúsculas, números y guiones (mínimo 3
  caracteres).**
- **Los días máximos de anticipación deben estar entre 1 y 365.** / **Las horas
  mínimas de anticipación deben estar entre 0 y 168.**

## Historial de cambios

- **1.0** — Versión inicial del manual.
