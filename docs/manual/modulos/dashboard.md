---
titulo: Tablero
resumen: Pantalla de inicio con los indicadores y avisos del día.
categoria: Primeros pasos
ruta_modulo: modulos/dashboard
tipo: modulo
visibilidad: todos
etiquetas: tablero, dashboard, inicio, resumen, indicadores, avisos, pantalla principal, home, ordenar tarjetas, mover tarjetas, arrastrar, reubicar, personalizar tablero, filtros fijos, ancho de tarjetas, redimensionar, cambiar tamaño, columnas
version: 1.1
orden: 2
estado: activo
---

El **tablero** es la pantalla de inicio: el resumen de cómo va la empresa y lo
que requiere atención hoy.

## Qué muestra

Depende de los módulos que tenga asignados cada usuario, pero en general:
indicadores de ventas y cobros, documentos pendientes y avisos de cosas que
requieren acción.

## Lo que ve cada usuario

El tablero **respeta los permisos**: solo muestra información de los módulos a
los que la persona tiene acceso, y de la empresa activa. Dos usuarios verán
tableros distintos, y es correcto.

Si además no tiene el permiso de *acceso total*, los indicadores reflejan solo
sus propios registros.

## Arme el tablero a su gusto

Todas las tarjetas del tablero —tanto los indicadores de arriba como los
paneles de gráficos y tablas— se pueden **mover de lugar** y **cambiar de
ancho**. Cada usuario guarda su propio tablero: lo que usted acomode no le
cambia nada a nadie más, y lo encontrará igual la próxima vez que entre, desde
cualquier equipo o navegador.

### Mover una tarjeta

1. Pase el cursor sobre la tarjeta. En su **esquina superior izquierda** aparece
   un pequeño agarre (⣿).
2. Mantenga presionado ese agarre y arrastre la tarjeta hasta donde la quiere.
   Las demás se van corriendo para dejarle el espacio.
3. Suelte. Se guarda solo.

Puede soltarla en **cualquier posición**: al final de una fila que quedó a
medias, entre dos tarjetas, o debajo de la última. No hace falta que "encaje"
en la fila; si no cabe, se acomoda en la siguiente. Un indicador puede quedar
entre los paneles y un panel entre los indicadores: no hay zonas separadas.

### Cambiar el ancho

El tablero es una cuadrícula de **12 columnas**. Cada tarjeta ocupa un número de
columnas: 12 es todo el ancho de la pantalla, 6 es la mitad, 4 es un tercio y 2
es el mínimo.

1. Pase el cursor sobre la tarjeta: en su **borde derecho** aparece una barrita
   vertical.
2. Arrástrela hacia la derecha para ensancharla o hacia la izquierda para
   angostarla. Mientras arrastra, un cartelito muestra cuántas columnas de 12
   está ocupando.
3. Suelte. También se guarda solo.

Si la suma de anchos de una fila no llega a 12, queda un espacio en blanco a la
derecha: es normal, y se corrige ensanchando alguna tarjeta o moviendo otra a
esa fila.

Para volver todo como venía de fábrica —orden y anchos— use el enlace
**Restablecer tablero**, en el texto pequeño debajo del título.

> En celulares y tablets el tablero se ordena solo (indicadores de a dos por
> fila y paneles a todo el ancho), así que ahí el ancho manual no se aplica. El
> orden que usted definió sí se respeta.

## Los filtros quedan siempre a la vista

La barra de filtros (período, rango de fechas, tendencia y tipo de gráfico) se
queda **fija bajo el menú** mientras usted baja por el tablero, de modo que
puede cambiar el período sin volver arriba.

## Si el tablero sale vacío

Suele ser una de tres cosas:

1. No hay **empresa activa** seleccionada.
2. El usuario no tiene módulos asignados todavía.
3. Es una empresa nueva sin movimientos.

## Errores frecuentes

- **Los números no coinciden con los reportes**: revise el periodo que muestra
  cada indicador y si le falta el permiso de acceso total.
- **Entré a un módulo y volví al tablero**: es lo que ocurre cuando no se tiene
  permiso sobre ese módulo. Pida el acceso al administrador.

## Historial de cambios

- **1.1** — Las tarjetas se pueden arrastrar a cualquier posición y cambiarles el
  ancho; el tablero se guarda por usuario y la barra de filtros quedó fija bajo el menú.
- **1.0** — Versión inicial.
