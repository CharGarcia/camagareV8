---
titulo: Marcaciones
resumen: Registro de entradas y salidas del personal, con su método y ubicación.
categoria: Asistencia
ruta_modulo: modulos/marcaciones
tipo: modulo
visibilidad: todos
etiquetas: marcaciones, marcar, entrada, salida, asistencia, reloj, gps, ubicacion, distancia, metros, punto de servicio, atrasos, faltas, credencial, qr, celular, rostro, reconocimiento facial, sospechosa, exportar, pdf, excel, reporte, buscar marcaciones, buscador, filtros, filtrar marcaciones, marcaciones sospechosas, fuera del radio, sin gps, chips
version: 1.3
orden: 30
estado: activo
---

Las **marcaciones** son los registros de entrada y salida del personal. De ellas
salen los atrasos, las faltas y las horas trabajadas que después alimentan las
novedades y el rol de pago.

## Qué guarda cada marcación

| Dato | Detalle |
|------|---------|
| Empleado | Quién marcó |
| Punto | El punto de servicio donde se marcó |
| Tipo | Entrada o salida |
| Método | Cómo se registró la marcación |
| Fecha y hora | Cuándo |
| Ubicación | Latitud y longitud enviadas por el teléfono |
| Distancia | Metros entre el teléfono y el punto de servicio |

## El punto y la distancia

La columna **Punto** dice dónde se marcó. Las marcaciones hechas con el QR
siempre traen su punto; las registradas a mano lo traen solo si se eligió uno al
crearlas, y en caso contrario la columna muestra *Registro manual*.

La columna **Distancia** son los metros que separaban al teléfono del punto de
servicio en el momento de marcar. Se calcula **siempre que haya coordenadas de
los dos lados**, exija o no GPS el punto, porque es el dato que permite ver de un
vistazo si alguien marcó desde donde debía. Si la distancia supera el radio del
punto, se muestra en rojo.

Cuando no hay número, la columna explica por qué:

| Lo que muestra | Qué significa | Qué hacer |
|----------------|---------------|-----------|
| *sin ubicación del punto* | El punto de servicio no tiene coordenadas cargadas | Póngalas en *Puntos de servicio* (botón **Usar mi ubicación** estando en el sitio) |
| *sin GPS* | El teléfono no envió su ubicación al marcar | El empleado debe permitir la ubicación en el navegador |
| — | La marcación no tiene punto asociado (registro manual) | Nada: no hay contra qué medir |

Ese cruce entre la ubicación del teléfono y la del punto es lo que permite
detectar una marcación hecha desde otro sitio. El sistema valida además que las
coordenadas sean posibles (latitud entre -90 y 90, longitud entre -180 y 180).

> Marcar fuera del radio solo convierte la marca en **sospechosa** cuando el
> punto tiene activado *Exige GPS*. Si no lo exige, la distancia se guarda como
> dato informativo y la marca sigue siendo válida.

## Marcación desde el celular

1. El empleado abre **una sola vez** su enlace personal (pestaña *Credenciales*
   de su ficha): el teléfono queda vinculado a su credencial.
2. Al llegar al sitio escanea el **QR del punto de servicio**. Se abre la
   pantalla de marcación con su nombre ya cargado.
3. Toma la selfie, permite la ubicación y pulsa **Entrada** o **Salida**.

Si el teléfono todavía no tiene credencial, la pantalla pide identificarse: se
puede pegar el **código personal** o el **enlace completo**, y el sistema
comprueba el código en ese momento — si no sirve, lo dice ahí mismo y no cuando
el empleado ya creía haber marcado.

### Verificación por rostro

Cuando el empleado tiene el rostro registrado, la pantalla lo compara con la
cámara **antes** de enviar la marca:

- Si **no se detecta ninguna cara**, avisa *"No se reconoció el rostro"* y no
  registra nada: el empleado se acomoda y vuelve a intentar.
- Si **la cara no coincide** con la registrada, avisa *"No pudimos confirmar que
  eres tú"* y tampoco registra.
- A partir del **tercer intento fallido** aparece además **Marcar de todas
  formas**: la marca sí se registra, pero queda con estado **sospechosa** y con
  el motivo en la observación, para que el supervisor la revise. Así nadie se
  queda sin marcar por mala luz o por un registro facial defectuoso.

Una marcación hecha fuera de la geocerca del punto también queda **sospechosa**,
con la distancia en la observación.

## Marcaciones manuales

Cuando alguien no pudo marcar (olvido, teléfono sin batería, fallo del equipo),
la marcación se registra manualmente con **Registrar marcación**: empleado, tipo,
fecha y hora, y de forma opcional el **punto de servicio** donde estaba. Indicar
el punto es recomendable — sin él la bitácora no puede decir dónde estuvo esa
persona. Al guardar se recalcula la jornada de ese día.

Estas marcaciones no tienen distancia (nadie envió una ubicación) y se
identifican como tales en la columna *Punto*: una asistencia llena de marcaciones
manuales deja de ser un control.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la marcación: empleado
(nombre o identificación), punto, fecha y hora (tal como se ven, por ejemplo
*25-07-2026 17:22*) y distancia (por ejemplo *37 m*). Además busca en la
observación (el motivo por el que quedó sospechosa), el dispositivo desde el que
se marcó y el usuario que registró una marcación manual. Las columnas **Tipo** y
**Estado**, y el método, no entran en la búsqueda libre: para filtrar por ellas
use la ventana de filtros. Puede escribir varias palabras en cualquier orden y
no importan mayúsculas ni tildes. Para limpiar, borre el texto o pulse Escape en
el cuadro. Mientras busca, aparece un **círculo girando** al final del cuadro y
la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento. La
ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Marcación | Fecha (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), tipo (entrada, salida, inicio o fin de break), estado (válida, sospechosa, anulada), método (QR del punto, ubicación, reconocimiento facial, registro manual), punto de servicio y nombre del punto |
| Ubicación | Distancia en metros (mínimo y máximo), fuera del radio del punto, con o sin GPS del celular y dispositivo |
| Empleado | Empleado, identificación, observación y usuario que registró la marcación manual |

El selector *Punto de servicio* y el de *Registrada por* listan solo lo que la
empresa ya usó en sus marcaciones. *Fuera del radio* aplica la misma regla que el
aviso rojo de la columna Distancia. El antiguo acceso rápido *Sospechosas* es
ahora la opción *Sospechosa* del estado.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Exportar a PDF y Excel

Los botones **PDF** y **Excel** de la barra del listado descargan **lo que los
filtros están mostrando**, no todo el módulo: si busca un empleado, un punto o un
rango de fechas, el archivo sale con ese mismo recorte, y el filtro aplicado
queda impreso en la cabecera del documento.

- **PDF**: pensado para imprimir o adjuntar — empleado, identificación, punto,
  fecha y hora, tipo, método, distancia y estado.
- **Excel**: incluye además latitud, longitud y la observación de cada marca
  (por ejemplo el motivo por el que quedó sospechosa), para analizar o cruzar
  datos por su cuenta.

## Errores frecuentes

- **"No se pudo identificar al empleado"**: la marcación no está asociada a
  ninguna ficha; revise que el empleado exista y esté activo.
- **"El tipo de marcación no es válido"** o **"El método no es válido"**: use uno
  de los valores admitidos.
- **La marcación se rechaza estando en el sitio**: revise el radio de la geocerca
  del punto de servicio.
- **Faltan marcaciones de un día**: puede que el dispositivo no tuviera conexión;
  regístrelas manualmente.
- **"La credencial guardada en este teléfono ya no es válida"**: la credencial
  del empleado fue regenerada o revocada. La pantalla olvida la credencial vieja
  y vuelve a pedir identificación; reenvíele su enlace o su código desde la
  pestaña *Credenciales* de su ficha.
- **"No se reconoció el rostro"**: la cámara no ve una cara completa. De frente,
  con buena luz y sin gorra ni lentes. Tras tres intentos se puede marcar igual,
  y esa marca queda como sospechosa.
- **"Este punto exige ubicación GPS"**: hay que permitir la ubicación en el
  navegador del teléfono; sin ella el punto no acepta la marca.
- **La columna Distancia sale vacía**: la propia columna dice el motivo (*sin
  ubicación del punto* o *sin GPS*). Vea la tabla de la sección *El punto y la
  distancia*.
- **La columna Punto dice "Registro manual"**: esa marca se creó a mano sin
  elegir punto de servicio. Al registrarlas, seleccione el punto.

## Historial de cambios

- **1.3** — Nuevo buscador del listado: búsqueda libre en todas las columnas
  (sin Tipo ni Estado), observación, dispositivo y usuario; botón embudo con la
  ventana de filtros (se suman método, punto de servicio, distancia, fuera del
  radio, con o sin GPS, dispositivo, identificación, observación y usuario). El
  acceso rápido *Sospechosas* pasa a ser una opción del estado. Los filtros
  activos se ven como chips dentro del cuadro.
- **1.2** — La distancia al punto ahora se calcula siempre que haya coordenadas
  del teléfono y del punto (antes solo con *Exige GPS* activado), y el listado
  explica por qué falta cuando no hay dato. El registro manual permite indicar el
  punto de servicio. Se agregan las exportaciones a PDF y Excel según los filtros.
- **1.1** — Se documenta la marcación desde el celular: identificación del
  teléfono, verificación por rostro con reintentos y marcas sospechosas.
- **1.0** — Versión inicial.
