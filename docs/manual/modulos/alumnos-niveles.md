---
titulo: Niveles / Cursos (Alumnos)
resumen: Catálogo de niveles o cursos (Inicial, Primero de Básica, Décimo, etc.) en los que se matriculan los alumnos, con el número de alumnos de cada uno.
categoria: Ventas
ruta_modulo: modulos/alumnos-niveles
tipo: modulo
visibilidad: todos
etiquetas: nivel, curso, grado, año lectivo, paralelo, sección, alumnos, estudiantes, colegio, escuela, centro infantil, matricula
version: 1.1
orden: 2
estado: activo
---

Los **niveles/cursos** son configurables libremente por cada empresa: no hay
una lista fija, porque un centro infantil, una escuela y un colegio manejan
nomenclaturas distintas (Maternal, Primero de Básica, Décimo, Bachillerato,
etc.). Se usan en la pestaña **Matrícula** del módulo
[Alumnos](modulos/alumnos), junto con el [Campus](modulos/alumnos-campus).

## Qué es y para qué sirve

Cada matrícula de un alumno indica en qué nivel/curso y en qué campus estudia
durante ese período. El campo **Orden** define cómo se listan los niveles (de
menor a mayor grado) en el listado y en los selectores del alumno. El listado
muestra cuántos alumnos tienen hoy una matrícula vigente en cada nivel.

## Requisitos previos

- Una empresa activa seleccionada.
- Permiso de **ver** el módulo Niveles / Cursos (y de **crear** para
  registrar uno nuevo).

## Cómo se usa

1. Pulse **Nuevo**, o use el botón **+** junto al selector de Nivel/Curso
   dentro del modal de un alumno. Desde el alumno, al guardar el nivel se
   cierra su ventana y queda **seleccionado en el alumno**, sin pasos extra.
2. Escriba el **nombre** y un **orden** (1 para el primer grado, 2 para el
   siguiente, etc.).
3. Guarde. Desde el listado la ventana sigue abierta para seguir editando.

Para modificar un nivel, haga clic en su fila.

### El listado

- **Buscador**: escriba parte del nombre. El botón del embudo abre los
  filtros por nombre, orden, alumnos vigentes, estado, fecha de registro y
  usuario que lo registró.
- **Ordenar**: por defecto por **Orden**; clic en otro encabezado para
  cambiarlo y **Shift + clic** para encadenar hasta tres columnas.
- **Columnas**: el botón de columnas permite ocultar las que no use.
- **PDF / Excel**: descargan el listado con el mismo filtro y orden de la
  pantalla.
- **Alumnos**: número de alumnos con matrícula vigente en el nivel. Al hacer
  clic en el número se abre el módulo Alumnos filtrado por ese nivel.

## Campos del formulario

| Campo | Obligatorio | Descripción |
|---|---|---|
| Nombre | Sí | Se guarda en mayúsculas. No puede repetirse en la empresa. Máximo 150 caracteres. |
| Orden | No | Número que ordena los niveles de menor a mayor. Por defecto 0. |
| Estado | Sí | **Activo** o **Inactivo**. Un nivel inactivo no aparece para elegir en nuevas matrículas, pero conserva su historial. |

## Permisos

- **Ver**: abrir el listado y exportarlo.
- **Crear**: registrar niveles (también desde el modal del alumno).
- **Actualizar**: modificar nombre, orden o estado.
- **Eliminar**: el botón **Eliminar** solo aparece en este módulo, no en la
  ventana que se abre desde un alumno.
- Sin **acceso total**, el usuario ve solo los niveles que él registró.

## Reglas de negocio

- El nombre no se repite dentro de la empresa (sin distinguir mayúsculas).
- La eliminación es **lógica** y está bloqueada mientras el nivel tenga
  alumnos matriculados en algún período, vigente o histórico. Si ya no se
  usa, márquelo como **Inactivo**.
- La columna **Alumnos** cuenta solo matrículas vigentes (sin fecha de
  salida) de alumnos no eliminados.
- Toda creación, modificación y eliminación queda registrada en la bitácora
  del sistema.

## Integraciones con otros módulos

- [Alumnos](modulos/alumnos): pestaña **Matrícula** y nivel actual del
  alumno; filtro `nivel:` del buscador de alumnos.
- [Campus](modulos/alumnos-campus): se elige junto con el nivel en cada
  matrícula.

## Errores frecuentes

- **"No se puede eliminar el nivel/curso porque tiene N alumno(s)
  matriculados"**: reasigne esas matrículas a otro nivel o inactive el nivel.
- **"Ya existe un nivel/curso con el nombre …"**: ya hay otro nivel con ese
  nombre en la empresa; use otro nombre o edite el existente.

## Historial de cambios

- **1.1** — Columna de alumnos vigentes con enlace al módulo Alumnos;
  buscador con filtros, orden por varias columnas, exportación a PDF y Excel;
  el nivel creado desde un alumno queda seleccionado; Eliminar solo con
  permiso y desde el propio módulo.
- **1.0** — Versión inicial.
