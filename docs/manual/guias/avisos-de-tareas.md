---
titulo: Avisos de tareas vencidas y por vencer
resumen: La campana de la barra superior lista las tareas y obligaciones vencidas o por vencer, y abre la que elija para atenderla.
categoria: Herramientas
tipo: guia
visibilidad: todos
etiquetas: campana, avisos de tareas, tareas vencidas, tareas por vencer, tareas atrasadas, obligaciones vencidas, obligaciones pendientes, vencimientos, recordatorio de tareas, notificaciones, alertas, pendientes, barra superior, navbar, correo diario de tareas, tareas y obligaciones, tarea no encontrada, quién ve las tareas, permisos de tareas
version: 1.1
orden: 40
estado: activo
---

La **campana** de la barra superior avisa de las tareas y obligaciones que ya
vencieron o que vencen en los próximos dos días. Al hacer clic se despliega la
lista de esas tareas y, al elegir una, se abre su ficha en **Tareas y
Obligaciones** para atenderla sin tener que buscarla.

## Qué indica el número de la campana

- La campana aparece solo cuando hay al menos una tarea vencida o por vencer.
- El **número** es la suma de ambas: tareas vencidas más tareas por vencer.
- Al pasar el mouse por encima se ve el desglose, por ejemplo *3 vencidas · 2
  por vencer*.
- Se actualiza sola cada pocos segundos; no hace falta recargar la página.

## Lista de tareas vencidas y por vencer

Al hacer clic en la campana se despliega la lista, separada en dos grupos:

| Grupo | Qué tareas entran | Orden |
|-------|-------------------|-------|
| **Vencidas** | Estado *Vencida*, o *Por realizar* con la fecha ya pasada | De la que venció más recientemente a la más antigua |
| **Por vencer** | Estado *Por realizar* con fecha de hoy, mañana o pasado mañana | De la más próxima a la más lejana |

Cada línea muestra la **obligación**, el **cliente**, la **fecha** de la tarea
y cuánto falta o cuánto hace que venció: *Vence hoy*, *Vence mañana*, *En 2
días*, *Hace 3 días*.

- Se listan hasta **15 tareas por grupo**. Si hay más, al final del grupo se
  indica cuántas quedaron fuera (*y 12 más en Tareas y Obligaciones*).
- **Ver todas las tareas**, al pie de la lista, abre el módulo completo con sus
  filtros.
- La lista se consulta de nuevo cada vez que se abre la campana, así que siempre
  refleja el estado actual.

## Abrir una tarea desde la lista

Al hacer clic en una tarea se abre **Tareas y Obligaciones** con la ficha de esa
tarea ya abierta: desde ahí se marca como realizada, se cambia la fecha o se
agregan notas y adjuntos. Si ya está en esa pantalla, la ficha se abre en el
acto, sin recargar.

En el celular la campana está en el menú lateral (botón ☰), en la ficha
**Tareas**: al tocarla se abre la misma lista en una ventana.

## Qué tareas ve cada usuario

- La campana es **personal**: cuenta y lista solo las tareas que **usted creó**
  o en las que figura como **responsable** (como usuario del sistema o por su
  correo). Vale también para el superadministrador: en **Tareas y
  Obligaciones** ve todas las tareas, pero su campana solo le avisa de las
  suyas.
- No depende de la empresa seleccionada: las tareas son las mismas en cualquier
  empresa.
- Las vencidas no tienen límite de antigüedad: una tarea que venció hace meses
  sigue en la lista hasta que se atienda.

## Quién puede abrir, modificar o eliminar una tarea

Una tarea solo se puede abrir, modificar o eliminar —y subir o borrar sus
adjuntos— si usted la **creó** o figura como **responsable**. El
superadministrador puede con todas. Con cualquier otra tarea el sistema
responde *Tarea no encontrada*, igual que si no existiera.

La misma regla aplica al detalle de un cliente en la pestaña **Clientes**:
muestra, y permite duplicar hacia otro cliente, solo las obligaciones de las
tareas que usted puede ver. Así coincide con el número de *vigentes* que
aparece en la lista de clientes.

## Cuándo sale una tarea de la lista

- Al marcarla como *Realizada y continúa*, *Realizada y finalizada* o
  *Cancelada* (esos estados además la archivan).
- Al mover su fecha más allá de pasado mañana: al guardar, el sistema la vuelve
  a poner *Por realizar* y deja de contar como vencida.
- Al eliminarla.

## Recordatorio diario por correo

Aparte de la campana, todos los días a partir de las **06:00** el sistema envía
a cada responsable un correo con sus tareas vencidas y por vencer (con el mismo
plazo de dos días). No hay que configurar nada.

## Errores frecuentes

- **La campana marca más tareas de las que esperaba**: cuenta también las
  tareas que usted creó aunque las atienda otra persona, y las vencidas de
  cualquier antigüedad. Revise el grupo *Vencidas* y cierre (como realizadas o
  canceladas) las que ya no apliquen.
- **"No se pudo abrir la tarea" — Tarea no encontrada**: la tarea se eliminó
  entre que se cargó la lista y el clic, o ya no está entre las suyas (por
  ejemplo, lo quitaron como responsable). Vuelva a abrir la campana para ver la
  lista actualizada.

## Historial de cambios

- **1.1** — Solo quien creó la tarea, sus responsables y el superadministrador
  pueden abrirla, modificarla, eliminarla o gestionar sus adjuntos; el detalle
  de un cliente muestra solo las obligaciones propias. La campana sigue siendo
  personal en todos los niveles.
- **1.0** — La campana despliega la lista de tareas vencidas y por vencer, y
  cada tarea abre directamente su ficha.
