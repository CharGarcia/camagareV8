---
titulo: Jornadas
resumen: Asignación de qué horario le toca a cada empleado en cada punto de servicio.
categoria: Asistencia
ruta_modulo: modulos/jornadas
tipo: modulo
visibilidad: todos
etiquetas: jornadas, asignar horario, turno del empleado, planificacion, rol de turnos, punto de servicio, exportar, pdf, excel, reporte, horas trabajadas, atrasos
version: 1.1
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

## Exportar a PDF y Excel

Los botones **PDF** y **Excel** de la barra del listado descargan **lo que los
filtros están mostrando**, no todo el módulo: si filtra por empleado, por estado
(faltas, incompletas) o por rango de fechas, el archivo sale con ese mismo
recorte y el filtro aplicado queda impreso en la cabecera.

- **PDF**: empleado, identificación, fecha, punto, entrada, salida, horas,
  atraso y estado, con una fila final de totales (horas trabajadas, minutos de
  atraso y minutos extra del conjunto exportado).
- **Excel**: incluye además los minutos extra por fila y la observación de cada
  jornada — por ejemplo el motivo por el que quedó incompleta.

Es la forma práctica de llevar el respaldo del período a la reunión de nómina o
de entregárselo al contador junto con el rol.

## Errores frecuentes

- **Las marcaciones no generan atrasos**: falta la jornada que asigne el horario.
- **Un empleado aparece con atrasos en su día libre**: la jornada tiene marcados
  días de la semana que no le corresponden; revise el horario asignado.
- **No aparece el punto de servicio**: verifique que esté registrado y activo.

## Historial de cambios

- **1.1** — Se agregan las exportaciones a PDF y Excel del listado según los
  filtros de búsqueda, con totales de horas, atrasos y minutos extra.
- **1.0** — Versión inicial.
