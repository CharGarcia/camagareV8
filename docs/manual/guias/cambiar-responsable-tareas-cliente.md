---
titulo: Cambiar el responsable de todas las tareas de un cliente
resumen: Desde Detalle por cliente, en Tareas y Obligaciones, se reemplaza a un responsable por otro en todas las tareas de ese cliente (por realizar, vencidas y realizadas) de una sola vez.
categoria: Herramientas
tipo: guia
visibilidad: todos
etiquetas: cambiar responsable, reasignar responsable, reemplazar responsable, traspasar tareas, pasar tareas a otro, responsable de empresa, responsable de cliente, cambio masivo de responsable, tareas y obligaciones, detalle por cliente, obligaciones del cliente, empleado que se fue, vacaciones
version: 1.1
orden: 41
estado: activo
---

Cuando una persona deja de atender a un cliente (cambió de cartera, salió de
vacaciones o dejó la empresa), no hace falta abrir sus tareas una por una: en
**Tareas y Obligaciones → Detalle por cliente** se reemplaza a ese responsable
por otro en todas las tareas del cliente con un solo paso.

## Cómo cambiar el responsable

1. Entre a **Tareas y Obligaciones** y abra la pestaña **Detalle por cliente**.
2. Haga clic en el cliente. Se abre la ventana con sus obligaciones vigentes.
3. En el recuadro **Cambiar responsable en todas sus tareas**:
   - En **Responsable actual** elija a la persona que quiere reemplazar (la
     lista muestra los responsables que hoy tienen las obligaciones del cliente).
   - En **Nuevo responsable** escriba al menos dos letras y elija al usuario o
     responsable de la lista. Con Retroceso o Supr se borra la selección.
4. Pulse **Cambiar** y confirme.

El sistema avisa en cuántas tareas hizo el cambio y la tabla de obligaciones se
actualiza con el nuevo responsable.

## Qué tareas cambian y cuáles no

| Tarea | ¿Cambia el responsable? |
|-------|-------------------------|
| Por realizar | Sí |
| Vencida | Sí |
| Realizada (continua o finalizada) | Sí |
| Cancelada | No |
| Archivada | No |

- Los demás responsables de cada tarea se mantienen; solo se reemplaza a la
  persona elegida.
- Si el nuevo responsable ya estaba en una tarea, no se duplica: simplemente
  se quita al anterior.
- Las tareas que se generen después (por periodicidad) heredan al nuevo
  responsable, porque copian los responsables de la tarea que se marca como
  realizada.

## Quién puede hacerlo

- El superadministrador cambia el responsable en todas las tareas del cliente.
- Los demás usuarios solo en las tareas que ellos crearon o en las que figuran
  como responsables, igual que lo que ven en el listado.

Cada tarea modificada queda registrada en el historial del sistema con los
responsables de antes y de después.

## Errores frecuentes

- **Ese responsable no tiene tareas por realizar, vencidas ni realizadas en este cliente**: todas sus
  tareas de ese cliente están canceladas o archivadas, o no son
  visibles para usted.
- **El nuevo responsable es el mismo que el actual**: elija otra persona.
- **Seleccione el nuevo responsable**: hay que elegirlo de la lista; escribir el
  nombre sin seleccionarlo no basta.

## Historial de cambios

- **1.0** — Versión inicial: cambio de responsable por cliente y pestaña del
  módulo recordada sin parámetros en la dirección.
- **1.1** — El cambio de responsable también se aplica a las tareas realizadas.
