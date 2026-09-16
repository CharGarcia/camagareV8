---
titulo: Agenda de citas
resumen: Calendario de citas con clientes, con su estado y su cobro.
categoria: Servicios
ruta_modulo: modulos/citas-agenda
tipo: modulo
visibilidad: todos
etiquetas: citas, agenda, calendario, reservas, turnos, cliente, horario de atencion, recordatorio, buscar cita, buscador, filtros, filtrar citas, lista de citas, chips, exportar citas
version: 1.1
orden: 20
estado: activo
---

La **agenda de citas** organiza las reservas con clientes: quién viene, cuándo,
para qué servicio y con qué profesional.

## Antes de usarla

Hay que configurar primero la disponibilidad en el módulo de configuración de
citas: horarios de atención, duración de cada servicio y quién los presta. La
agenda respeta esa configuración al ofrecer los espacios libres.

## Cómo se agenda

1. Elija el día y el espacio libre.
2. Seleccione el **cliente** (o regístrelo si es nuevo).
3. Indique el **servicio**.
4. Confirme la cita.

## Estados

Una cita avanza por estados: agendada, confirmada, atendida o cancelada. Mantener
el estado al día es lo que permite después saber cuántas citas se pierden por
inasistencia.

## Cobro

Las citas pueden llevar su cobro asociado, ya sea en el momento o por
adelantado, según cómo trabaje la empresa.

## Buscar y filtrar la lista de citas

En la vista **Lista**, arriba de la tabla hay un solo grupo: el botón del
**embudo**, el cuadro de búsqueda y los botones de columnas, PDF y Excel. Los
selectores *Estado*, *Tipo de cita* y *Recurso* de la parte superior son solo
del **Calendario**; en la Lista esos mismos criterios están en la ventana de
filtros.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y la lista se filtra
sola, sin menús ni sugerencias. Busca en las columnas de la cita: fecha de
inicio (como *26-05-2026* o *2026-05-26*), cliente, recurso y título. Además
busca en la identificación del cliente, los datos del cliente que reservó por
el portal (nombre, identificación, correo, teléfono), las notas, el usuario que
registró la cita y la referencia de sus pagos. Las columnas **Tipo**, **Estado**
y **Origen** no entran en la búsqueda libre: para filtrar por ellas use la
ventana de filtros. Puede escribir varias palabras en cualquier orden y no
importan mayúsculas ni tildes. Para limpiar, borre el texto o pulse Escape en el
cuadro. Mientras busca, aparece un **círculo girando** al final del cuadro y la
tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento.
La ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Cita | Fecha de inicio (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado, origen (interno o portal), tipo de cita, recurso, con o sin pago registrado, fecha de fin, fecha de registro |
| Cliente | Cliente, identificación, usuario que registró, título, notas |

Los selectores *Tipo de cita*, *Recurso* y *Usuario que registró* listan solo
lo que ya aparece en alguna cita de la empresa (incluidos tipos y recursos que
hoy estén inactivos).

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. Los botones PDF y Excel exportan lo que la lista
muestra con los filtros aplicados.

## Errores frecuentes

- **No hay espacios disponibles**: revise la configuración de horarios y la
  duración del servicio.
- **Se agendaron dos citas a la misma hora**: compruebe la configuración de
  disponibilidad del profesional.
- **El cliente no aparece**: regístrelo en Clientes.

## Historial de cambios

- **1.1** — Nuevo buscador de la vista Lista: el cuadro ya no despliega
  sugerencias; lo que se escribe se busca en las columnas de la cita (salvo
  Tipo, Estado y Origen) y además en notas, datos del cliente del portal,
  usuario y referencia de pagos. Los filtros pasan a una **ventana propia**
  (botón del embudo, se aplican con *Aplicar*) con criterios nuevos: tipo de
  cita, recurso y usuario como listas, origen, con/sin pago, fecha de fin y
  fecha de registro. Los filtros Estado/Tipo/Recurso de arriba quedan solo para
  el Calendario, y los campos Desde/Hasta de la Lista se reemplazan por el rango
  de fecha de inicio de la ventana. Nueva sección *Buscar y filtrar la lista de
  citas*.
- **1.0** — Versión inicial.
