---
titulo: Horarios
resumen: Turnos de trabajo con su hora de entrada, salida, tolerancia y días de la semana.
categoria: Asistencia
ruta_modulo: modulos/horarios
tipo: modulo
visibilidad: todos
etiquetas: horarios, turnos, jornada, hora de entrada, tolerancia, atrasos, dias de la semana, rotativo, buscar turno, buscar horario, buscador, filtros, filtrar horarios, turno nocturno, empleado asignado, chips
version: 1.1
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

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y el botón de columnas.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del turno: nombre,
horario (por ejemplo *08:00*), tolerancia, horas y días (*Lun*, *Mié*…).
Además busca en el usuario que creó el turno y en el **nombre e identificación
de los empleados que lo tienen asignado**. La columna **Estado** no entra en la
búsqueda libre: para filtrar por ella use la ventana de filtros. Puede escribir
varias palabras en cualquier orden y no importan mayúsculas ni tildes. Para
limpiar, borre el texto o pulse Escape en el cuadro. Mientras busca, aparece un
**círculo girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento. La
ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Turno | Nombre, estado (activo / inactivo), turno nocturno (la salida es al día siguiente), hora de entrada, hora de salida e incluye el día (lunes a domingo) |
| Valores | Horas de jornada, tolerancia en minutos y número de días laborables (cada uno con mínimo y máximo) |
| Asignación | Empleado asignado (nombre o identificación) y usuario que registró |

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

> Quien no tenga **acceso total** solo ve los turnos que creó él mismo.

## Errores frecuentes

- **"La tolerancia debe estar entre 0 y 240 minutos"**: revise el valor.
- **"Los días de la semana deben ser números del 1 al 7"**: use 1 para lunes y 7
  para domingo.
- **Un empleado sale siempre como atrasado**: revise el horario asignado; puede
  tener uno que no corresponde a su turno.

## Historial de cambios

- **1.1** — Nuevo buscador del listado: búsqueda libre en todas las columnas
  (sin Estado) y en los empleados asignados, y botón embudo con la ventana de
  filtros (turno nocturno, horas de entrada y salida, día, horas de jornada,
  tolerancia, número de días, empleado asignado y usuario). Los filtros activos
  se ven como chips dentro del cuadro.
- **1.0** — Versión inicial.
