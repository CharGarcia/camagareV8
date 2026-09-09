---
titulo: Jornadas
resumen: Resumen diario de cada empleado — horas trabajadas, atrasos, faltas y horas extra calculados desde sus marcaciones.
categoria: Asistencia
ruta_modulo: modulos/jornadas
tipo: modulo
visibilidad: todos
etiquetas: jornadas, horas trabajadas, atrasos, faltas, horas extra, suplementarias, asistencia diaria, resumen del dia, recalcular, jornada incompleta, requieren revision, sin salida, generar novedades, rol de pagos, exportar, pdf, excel
version: 2.0
orden: 40
estado: activo
---

Una **jornada** es el resumen de un día de trabajo de un empleado: a qué hora
entró, a qué hora salió, cuántas horas trabajó, cuánto se atrasó y cuántas horas
extra hizo. El sistema la calcula solo, cruzando las
[Marcaciones](modulos/marcaciones) del día con el horario que le tocaba.

Es la pieza que convierte el control de asistencia en dinero: de aquí salen las
novedades que después entran al rol de pagos.

## Qué es y para qué sirve

Hay **una jornada por empleado y por día**. No se crea a mano: nace del cruce
entre dos cosas que ya existen en el sistema.

| Pieza | De dónde sale | Qué aporta |
|-------|---------------|------------|
| Las marcaciones del día | [Marcaciones](modulos/marcaciones) | Las horas reales de entrada y salida |
| El horario vigente | [Horarios](modulos/horarios), asignado en la ficha del empleado | Contra qué se comparan: hora de entrada, tolerancia, días laborables |

Sin horario asignado no hay atraso ni falta posible: no habría contra qué medir.
Sin marcaciones, solo se registra la falta de los días laborables.

## Requisitos previos

1. El **empleado**, registrado y activo.
2. El **horario** creado en [Horarios](modulos/horarios).
3. La **asignación** del horario al empleado: se hace en la pestaña **Horario**
   de la ficha del empleado (turno, punto de servicio y vigencia desde/hasta),
   no en este módulo.
4. Las **marcaciones** del período, hechas desde el celular o registradas a
   mano.

> Si un empleado rota de turno o de sede, se agregan varias asignaciones con sus
> fechas de vigencia. El sistema evalúa cada día contra el horario que le
> correspondía **ese** día.

## Cómo se usa

En el día a día no hay que hacer nada: **cada marcación recalcula sola la
jornada de ese día**. El módulo se usa para revisar el resultado y para
corregirlo cuando falta información.

1. Abra **Jornadas** y busque el período o el empleado que le interesa.
2. Revise las filas en amarillo (**incompleta**) y rojo (**falta**).
3. Corrija las incompletas con el botón del lápiz (ver más abajo).
4. Cuando el período esté limpio, pulse **Generar Novedades** para pasarlo al
   rol.

### Recalcular

El botón **Recalcular** vuelve a procesar un rango de fechas: útil después de
cargar marcaciones atrasadas, de cambiar un horario o de corregir una
asignación.

- **Desde / Hasta**: el rango a procesar.
- **Solo jornadas incompletas**: en vez de recorrer todo el rango, refresca
  únicamente las que requieren revisión. Es lo indicado después de registrar
  varias marcaciones faltantes; al terminar dice cuántas quedaron resueltas y
  cuántas siguen pendientes.

### Requieren revisión

El botón **Requieren revisión** de la cabecera filtra las jornadas
**incompletas**: aquellas en las que falta una marcación. Es la lista de
pendientes antes de cerrar el mes.

### Corregir una jornada incompleta

En las filas incompletas aparece un botón de lápiz. Abre un cuadro para
registrar **la marcación que falta** (normalmente la salida): tipo, hora y una
observación opcional. Al guardar, la jornada del día se recalcula y el sistema
le dice si quedó resuelta o si todavía falta otra marca.

La marcación creada así queda registrada en
[Marcaciones](modulos/marcaciones) como manual, igual que si la hubiera hecho
desde ese módulo.

### Generar Novedades

Traduce las jornadas del período en novedades para la nómina. Se elige **mes**,
**año** y a qué **afecta**: *Rol de Pagos*, *Quincena* o *Pago Semanal*.

Es **manual a propósito**: nada se traslada al rol sin que alguien lo pida. Y se
puede repetir las veces que haga falta — no duplica, actualiza (ver *Reglas de
negocio*).

## Columnas del listado

| Columna | Qué muestra |
|---------|-------------|
| Empleado | De quién es la jornada |
| Fecha | El día resumido |
| Entrada | Hora de la primera entrada del día |
| Salida | Hora de la última salida (o la estimada, si hubo cierre automático) |
| Horas | Horas efectivamente trabajadas, ya descontados los breaks |
| Atraso | Minutos de retraso sobre la hora de entrada más la tolerancia |
| Extra | Minutos trabajados por encima de la jornada esperada |
| Estado | Completa, incompleta o falta |

Las columnas se pueden ocultar y reordenar por usuario desde el botón de
columnas, y el ancho de cada una se guarda para la próxima visita.

### Los estados

| Estado | Qué significa | Qué hacer |
|--------|---------------|-----------|
| **Completa** | Las marcaciones cuadran: hay entrada y salida | Nada |
| **Incompleta** | Falta una marcación, casi siempre la salida | Corregirla con el botón del lápiz |
| **Falta** | Día laborable sin ninguna marcación | Verificar si fue ausencia real o si el empleado no pudo marcar |

Al pasar el ratón sobre el estado de una jornada incompleta se ve el motivo
exacto en un aviso emergente.

> El buscador ofrece además el estado *Permiso*, reservado para ausencias
> justificadas. El cálculo automático no lo asigna: hoy solo produce los tres
> estados de la tabla.

## Búsqueda

El buscador combina texto libre (nombre o cédula del empleado) con filtros:

- `empleado:` nombre del empleado.
- `estado:` `completa`, `incompleta`, `falta` o `permiso`.
- `fecha:` una fecha o un rango, por ejemplo `fecha:2026-09-01..2026-09-30`.
- `atraso:` minutos de atraso, con rangos y comparadores (`atraso:>0`).
- `extra:` minutos extra, igual que el anterior.

Hay tres accesos rápidos: **Requieren revisión**, **Faltas** y **Con atraso**.

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

## Permisos

| Permiso | Qué habilita |
|---------|--------------|
| Ver | Consultar el listado y exportarlo a PDF y Excel |
| Crear | **Generar Novedades** hacia el rol |
| Actualizar | **Recalcular** y **corregir** jornadas incompletas |
| Acceso total | Ver las jornadas de toda la empresa; sin él, solo las de los registros que el propio usuario creó |

## Reglas de negocio

**Cómo se cuentan las horas.** El sistema empareja las marcaciones en orden
cronológico: *entrada* y *fin de break* abren un tramo, *salida* e *inicio de
break* lo cierran. La suma de los tramos son las horas trabajadas, así que los
descansos no se pagan como tiempo trabajado.

**Cuando falta la salida.** Si el empleado quedó "adentro", el sistema no sabe
cuánto se quedó. Cierra el tramo a la **hora de salida programada** de su
horario, nunca más tarde, y tope la jornada esperada: un olvido nunca genera
horas extra ni horas de más. La jornada queda marcada **incompleta** para que se
revise, con el detalle en la observación. Si el horario no tiene hora de salida,
el tramo abierto simplemente no se cuenta.

**Atrasos.** Se miden contra la hora de entrada del horario **más la
tolerancia**. Entrar dentro de la tolerancia no genera atraso.

**Horas extra.** Son las trabajadas por encima de la jornada esperada del
horario. No se calculan cuando hubo cierre automático: sin marca de salida real
no hay evidencia de sobretiempo.

**Faltas.** Un día laborable (según los días de la semana del horario) sin
ninguna marcación se registra como falta. Un día **no** laborable sin marcas no
genera ninguna fila: los descansos no ensucian el listado.

**Turnos de noche.** Si el horario cruza la medianoche, la hora de salida se
entiende del día siguiente y las horas se calculan correctamente a caballo entre
dos fechas.

**Generar Novedades no duplica.** Cada novedad generada lleva una marca interna
con su período y su origen. Al repetir la generación del mismo mes, el sistema
actualiza la que ya existía en vez de crear otra, y si el valor recalculado baja
a cero, la elimina. Las novedades cargadas a mano nunca se tocan. Si el rol de
ese período ya está pagado, esas novedades se omiten y el resumen final lo dice.

## Integraciones con otros módulos

- **[Marcaciones](modulos/marcaciones)**: son la materia prima. Cada marca
  creada, corregida o eliminada recalcula la jornada de ese día.
- **[Horarios](modulos/horarios)** y la ficha del empleado: aportan el turno, la
  tolerancia y los días laborables contra los que se mide.
- **[Novedades](modulos/novedades)**: aquí aterriza el resultado del período.
  Las faltas entran como *Días no laborados*, las horas extra como *Horas
  Suplementarias* y los atrasos según el **tratamiento** definido en la pestaña
  *Atrasos* de cada empleado (descuento calculado sobre el sueldo, fracción de
  día, registro informativo o nada).
- **[Roles de Pago](modulos/roles-pago)**: consume esas novedades. Al armar el
  rol, el sistema avisa si el período todavía tiene jornadas incompletas, para
  que no se pague sobre datos a medias.

## Errores frecuentes

- **El listado está vacío**: todavía no se ha recalculado ningún período, o el
  empleado no tiene horario asignado. Pulse **Recalcular** con el rango que
  necesita.
- **Un empleado no aparece ningún día**: no tiene horario asignado en la pestaña
  *Horario* de su ficha, o la vigencia de esa asignación no cubre esas fechas.
- **Las marcaciones no generan atrasos**: el horario no tiene hora de entrada, o
  el empleado entra dentro de la tolerancia.
- **Un empleado aparece con falta en su día libre**: el horario tiene marcados
  días de la semana que no le corresponden. Corrija el horario y recalcule.
- **Muchas jornadas incompletas**: es el síntoma clásico de gente que marca la
  entrada y olvida la salida. Filtre por **Requieren revisión**, corríjalas y
  vuelva a recalcular con *Solo jornadas incompletas*.
- **Las horas no cuadran con lo que dice el empleado**: revise si hubo cierre
  automático (la observación del estado lo indica). En ese caso la salida que se
  muestra es la programada, no una marca real.
- **"El rol de este período ya está pagado"**: las novedades de ese empleado se
  omiten a propósito. Si de verdad hay que corregirlo, primero se reabre el rol.

## Historial de cambios

- **2.0** — Artículo reescrito: describía la asignación de horarios (que hoy
  vive en la ficha del empleado) en vez del consolidado diario que es realmente
  este módulo. Se documentan el cálculo de horas, atrasos y faltas, el cierre
  automático de jornadas sin salida, Recalcular, la corrección de incompletas y
  la generación de novedades.
- **1.1** — Se agregan las exportaciones a PDF y Excel del listado según los
  filtros de búsqueda, con totales de horas, atrasos y minutos extra.
- **1.0** — Versión inicial.
