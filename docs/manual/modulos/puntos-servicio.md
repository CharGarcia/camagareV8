---
titulo: Puntos de servicio
resumen: Lugares donde el personal marca su asistencia, con geocerca para validar la ubicación.
categoria: Asistencia
ruta_modulo: modulos/puntos-servicio
tipo: modulo
visibilidad: todos
etiquetas: puntos de servicio, geocerca, ubicacion, gps, marcar asistencia, sede, cliente, guardias, buscar punto, buscador, filtros, filtrar puntos de servicio, puntos sin coordenadas, chips
version: 1.2
orden: 10
estado: activo
---

Un **punto de servicio** es un lugar donde el personal debe marcar su asistencia:
la oficina, la sede de un cliente, un puesto de vigilancia.

Su función principal es delimitar **dónde** vale una marcación, mediante una
geocerca.

## La geocerca

La geocerca es un círculo alrededor del punto: una marcación solo se acepta si se
hace dentro de él.

- El **radio** se mide en metros y debe estar **entre 10 y 5.000**.
- Si el punto **exige GPS**, hay que registrar su **latitud y longitud**. Sin
  esas coordenadas no hay contra qué comparar.

Un radio muy pequeño genera rechazos por la imprecisión normal del GPS; uno muy
grande deja de servir como control. Para un local urbano, unas decenas de metros
suele ser razonable.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** del punto.
3. Indique si **exige GPS**.
4. Si lo exige, registre latitud, longitud y el **radio** de la geocerca.
5. Guarde.

## El código QR del punto

Cada punto tiene su propio **código QR**. Se abre con el botón de QR de la fila y
se puede imprimir para dejarlo fijo en el sitio.

- El QR contiene la **dirección completa** de la página de marcación, incluido el
  dominio del sistema, para que cualquier celular pueda abrirla al escanearlo.
- **Copiar enlace** copia esa misma dirección; **Copiar código** copia solo el
  código del punto, para enviárselo a alguien que no pueda escanear y lo escriba
  a mano en la aplicación.
- **Regenerar** crea un código nuevo y **anula el anterior**: los QR ya impresos
  de ese punto dejan de funcionar y hay que volver a imprimirlos.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y el botón de columnas.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del punto: nombre,
dirección y radio (por ejemplo *150 m*). Además busca en las coordenadas
(latitud y longitud) y en el usuario que registró el punto. Las columnas
**Estado** y **GPS** no entran en la búsqueda libre: para filtrar por ellas use
la ventana de filtros. Puede escribir varias palabras en cualquier orden y no
importan mayúsculas ni tildes. Para limpiar, borre el texto o pulse Escape en
el cuadro. Mientras busca, aparece un **círculo girando** al final del cuadro y
la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento.
La ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Punto | Nombre, dirección, estado (activo o inactivo), radio en metros (mínimo y máximo), exige GPS (sí o no) y ubicación (con o sin coordenadas) |
| Registro | Fecha de registro (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*) y usuario que registró |

El selector *Usuario que registró* lista solo a quienes ya crearon puntos en la
empresa. El filtro *Ubicación* sirve para encontrar los puntos a los que les
faltan coordenadas.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

Si no tiene **acceso total** al módulo, el listado muestra solo los puntos que
usted registró.

## Errores frecuentes

- **"El radio de geocerca debe estar entre 10 y 5000 metros"**: ajuste el valor.
- **"Si el punto exige GPS, debe registrar su latitud y longitud"**: complete las
  coordenadas o desactive la exigencia de GPS.
- **El personal no puede marcar estando en el sitio**: el radio puede ser
  demasiado pequeño, o las coordenadas del punto estar mal tomadas.
- **Al escanear el QR el celular no abre nada**: vuelva a abrir el QR del punto e
  imprímalo de nuevo. Los QR impresos antes de esta corrección guardaban una
  dirección incompleta (sin el dominio) y ningún lector podía abrirla.

## Historial de cambios

- **1.2** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en el nombre, la dirección, el radio, las
  coordenadas y el usuario que registró, salvo Estado y GPS. Los filtros pasan a
  una **ventana propia** (botón del embudo, se aplican con *Aplicar*) con
  criterios nuevos: radio, exige GPS, con o sin coordenadas, fecha de registro y
  usuario. El acceso *Activos* pasa a ser la opción del filtro Estado. Nueva
  sección *Buscar y filtrar el listado*.

- **1.1** — El QR del punto ahora guarda la dirección completa con el dominio; los
  QR anteriores no se podían abrir al escanearlos. Se documenta el QR del punto.
- **1.0** — Versión inicial.
