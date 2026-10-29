---
titulo: Campus (Alumnos)
resumen: Catálogo de campus o sedes donde estudian los alumnos, con el número de alumnos matriculados en cada uno.
categoria: Ventas
ruta_modulo: modulos/alumnos-campus
tipo: modulo
visibilidad: todos
etiquetas: campus, sede, sucursal educativa, local, plantel, alumnos, estudiantes, colegio, escuela, centro infantil, matricula
version: 1.1
orden: 1
estado: activo
---

Los **campus** identifican la sede física donde estudia cada alumno (Campus
Norte, Campus Sur, sede única, etc.). Se usan en la pestaña **Matrícula** del
módulo [Alumnos](modulos/alumnos) y junto con los
[Niveles / Cursos](modulos/alumnos-niveles).

## Qué es y para qué sirve

Es un catálogo propio de cada empresa: cada institución registra sus sedes.
Cada matrícula de un alumno indica en qué campus y en qué nivel/curso estudia
durante ese período. El listado muestra cuántos alumnos tienen hoy una
matrícula vigente en cada campus.

## Requisitos previos

- Una empresa activa seleccionada.
- Permiso de **ver** el módulo Campus (y de **crear** para registrar uno nuevo).

## Cómo se usa

1. Pulse **Nuevo**, o use el botón **+** junto al selector de Campus dentro
   del modal de un alumno. Desde el alumno, al guardar el campus se cierra su
   ventana y queda **seleccionado en el alumno**, sin pasos extra.
2. Escriba el **nombre** y, opcionalmente, la **dirección**.
3. Guarde. Desde el listado la ventana sigue abierta para seguir editando.

Para modificar un campus, haga clic en su fila.

### El listado

- **Buscador**: escriba parte del nombre o de la dirección. El botón del
  embudo abre los filtros por nombre, dirección, alumnos vigentes, estado,
  fecha de registro y usuario que lo registró.
- **Ordenar**: clic en el encabezado de una columna; con **Shift + clic**
  encadena hasta tres columnas (por ejemplo, estado y luego nombre).
- **Columnas**: el botón de columnas permite ocultar las que no use.
- **PDF / Excel**: descargan el listado con el mismo filtro y orden de la
  pantalla.
- **Alumnos**: número de alumnos con matrícula vigente en el campus. Al hacer
  clic en el número se abre el módulo Alumnos filtrado por ese campus.

## Campos del formulario

| Campo | Obligatorio | Descripción |
|---|---|---|
| Nombre | Sí | Se guarda en mayúsculas. No puede repetirse en la empresa. Máximo 150 caracteres. |
| Dirección | No | Ubicación del campus. Máximo 300 caracteres. |
| Estado | Sí | **Activo** o **Inactivo**. Un campus inactivo no aparece para elegir en nuevas matrículas, pero conserva su historial. |

## Permisos

- **Ver**: abrir el listado y exportarlo.
- **Crear**: registrar campus (también desde el modal del alumno).
- **Actualizar**: modificar nombre, dirección o estado.
- **Eliminar**: el botón **Eliminar** solo aparece en este módulo, no en la
  ventana que se abre desde un alumno.
- Sin **acceso total**, el usuario ve solo los campus que él registró.

## Reglas de negocio

- El nombre no se repite dentro de la empresa (sin distinguir mayúsculas).
- La eliminación es **lógica** y está bloqueada mientras el campus tenga
  alumnos matriculados en algún período, vigente o histórico. Si ya no se
  usa, márquelo como **Inactivo**.
- La columna **Alumnos** cuenta solo matrículas vigentes (sin fecha de
  salida) de alumnos no eliminados.
- Toda creación, modificación y eliminación queda registrada en la bitácora
  del sistema.

## Integraciones con otros módulos

- [Alumnos](modulos/alumnos): pestaña **Matrícula** y campus actual del
  alumno; filtro `campus:` del buscador de alumnos.
- [Niveles / Cursos](modulos/alumnos-niveles): se eligen junto con el campus
  en cada matrícula.

## Errores frecuentes

- **"No se puede eliminar el campus porque tiene N alumno(s) matriculados"**:
  reasigne esas matrículas a otro campus o inactive el campus.
- **"Ya existe un campus con el nombre …"**: ya hay otro campus con ese
  nombre en la empresa; use otro nombre o edite el existente.

## Historial de cambios

- **1.1** — Columna de alumnos vigentes con enlace al módulo Alumnos;
  buscador con filtros, orden por varias columnas, exportación a PDF y Excel;
  el campus creado desde un alumno queda seleccionado; Eliminar solo con
  permiso y desde el propio módulo.
- **1.0** — Versión inicial.
