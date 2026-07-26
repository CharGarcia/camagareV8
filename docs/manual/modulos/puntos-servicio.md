---
titulo: Puntos de servicio
resumen: Lugares donde el personal marca su asistencia, con geocerca para validar la ubicación.
categoria: Asistencia
ruta_modulo: modulos/puntos-servicio
tipo: modulo
visibilidad: todos
etiquetas: puntos de servicio, geocerca, ubicacion, gps, marcar asistencia, sede, cliente, guardias
version: 1.0
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

## Errores frecuentes

- **"El radio de geocerca debe estar entre 10 y 5000 metros"**: ajuste el valor.
- **"Si el punto exige GPS, debe registrar su latitud y longitud"**: complete las
  coordenadas o desactive la exigencia de GPS.
- **El personal no puede marcar estando en el sitio**: el radio puede ser
  demasiado pequeño, o las coordenadas del punto estar mal tomadas.

## Historial de cambios

- **1.0** — Versión inicial.
