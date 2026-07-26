---
titulo: Jornadas
resumen: Asignación de qué horario le toca a cada empleado en cada punto de servicio.
categoria: Asistencia
ruta_modulo: modulos/jornadas
tipo: modulo
visibilidad: todos
etiquetas: jornadas, asignar horario, turno del empleado, planificacion, rol de turnos, punto de servicio
version: 1.0
orden: 40
estado: activo
---

Las **jornadas** unen las tres piezas del control de asistencia: **qué empleado**
trabaja **en qué punto de servicio** con **qué horario**.

Sin la jornada asignada, las marcaciones de un empleado no tienen contra qué
compararse: no hay atraso ni falta posible.

## Requisitos previos

Antes de asignar una jornada necesita tener creados:

1. El **empleado**.
2. El **punto de servicio** donde va a trabajar.
3. El **horario** que le corresponde.

## Cómo se asigna

1. Pulse **Nuevo**.
2. Elija el **empleado**.
3. Elija el **punto de servicio**.
4. Elija el **horario**.
5. Indique el periodo de vigencia.
6. Guarde.

## Turnos rotativos

Cuando el personal rota de turno o de sede, se registra una jornada por cada
periodo. Así el sistema sabe que en enero le tocaba el turno de mañana en una
sede y en febrero el de noche en otra, y evalúa cada marcación contra el horario
que correspondía ese día.

## Errores frecuentes

- **Las marcaciones no generan atrasos**: falta la jornada que asigne el horario.
- **Un empleado aparece con atrasos en su día libre**: la jornada tiene marcados
  días de la semana que no le corresponden; revise el horario asignado.
- **No aparece el punto de servicio**: verifique que esté registrado y activo.

## Historial de cambios

- **1.0** — Versión inicial.
