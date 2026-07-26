---
titulo: Horarios
resumen: Turnos de trabajo con su hora de entrada, salida, tolerancia y días de la semana.
categoria: Asistencia
ruta_modulo: modulos/horarios
tipo: modulo
visibilidad: todos
etiquetas: horarios, turnos, jornada, hora de entrada, tolerancia, atrasos, dias de la semana, rotativo
version: 1.0
orden: 20
estado: activo
---

Los **horarios** definen los turnos de trabajo: a qué hora se entra, a qué hora
se sale, cuántos minutos de tolerancia hay y qué días de la semana aplica.

Son la referencia contra la que se comparan las marcaciones: sin horario no hay
atraso posible, porque no hay contra qué medir.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** del horario (`Administrativo`, `Turno noche`).
3. Indique las **horas** de entrada y salida, en formato `HH:MM`.
4. Fije la **tolerancia** en minutos.
5. Marque los **días de la semana** en que aplica.
6. Guarde.

## Validaciones

| Campo | Regla |
|-------|-------|
| Nombre | Obligatorio |
| Horas | Formato `HH:MM` (admite segundos) |
| Tolerancia | Entre **0 y 240 minutos** |
| Horas de jornada | Entre 0 y 24 |
| Días de la semana | Números del **1 (lunes) al 7 (domingo)** |

## La tolerancia

Son los minutos de gracia antes de contar un atraso. Con tolerancia 10, quien
entra a las 8:07 llega a tiempo; quien entra a las 8:11 llega tarde.

Poner una tolerancia alta no es "ser flexible": es cambiar la hora de entrada
real. Si la entrada es a las 8:15, póngala a las 8:15.

## Errores frecuentes

- **"La tolerancia debe estar entre 0 y 240 minutos"**: revise el valor.
- **"Los días de la semana deben ser números del 1 al 7"**: use 1 para lunes y 7
  para domingo.
- **Un empleado sale siempre como atrasado**: revise el horario asignado; puede
  tener uno que no corresponde a su turno.

## Historial de cambios

- **1.0** — Versión inicial.
