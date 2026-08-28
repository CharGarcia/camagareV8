---
titulo: Puntos de servicio
resumen: Lugares donde el personal marca su asistencia, con geocerca para validar la ubicación.
categoria: Asistencia
ruta_modulo: modulos/puntos-servicio
tipo: modulo
visibilidad: todos
etiquetas: puntos de servicio, geocerca, ubicacion, gps, marcar asistencia, sede, cliente, guardias
version: 1.1
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

- **1.1** — El QR del punto ahora guarda la dirección completa con el dominio; los
  QR anteriores no se podían abrir al escanearlos. Se documenta el QR del punto.
- **1.0** — Versión inicial.
