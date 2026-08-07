---
titulo: Niveles / Cursos (Alumnos)
resumen: Catálogo de niveles o cursos (Inicial, Primero de Básica, Décimo, etc.) en los que se matriculan los alumnos.
categoria: Ventas
ruta_modulo: modulos/alumnos-niveles
tipo: modulo
visibilidad: todos
etiquetas: nivel, curso, grado, paralelo, alumnos, colegio, escuela, centro infantil, matricula
version: 1.0
orden: 2
estado: activo
---

Los **niveles/cursos** son configurables libremente por cada empresa: no hay
una lista fija, porque un centro infantil, una escuela y un colegio manejan
nomenclaturas distintas (Maternal, Primero de Básica, Décimo, Bachillerato,
etc.). Se usan en la pestaña **Matrícula** del módulo
[Alumnos](modulos/alumnos).

## Cómo se registra

1. Pulse **Nuevo**, o use el botón **+** junto al selector de Nivel/Curso
   dentro del modal de un alumno (se crea sin salir del formulario del
   alumno).
2. Escriba el **nombre** y, opcionalmente, un **orden** (para que la lista se
   muestre de menor a mayor grado).
3. Guarde.

## Eliminar

La eliminación es **lógica** y está bloqueada mientras el nivel/curso tenga
alumnos matriculados en algún período (activo o histórico).

## Errores frecuentes

- **"No se puede eliminar el nivel/curso porque tiene alumnos matriculados"**:
  reasigne o cierre esas matrículas antes de eliminarlo.

## Historial de cambios

- **1.0** — Versión inicial.
