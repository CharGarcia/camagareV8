---
titulo: Pasar al registro anterior o al siguiente
resumen: Flechas ‹ › en el encabezado de las ventanas abiertas desde un listado, para recorrer los registros sin volver a la tabla.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: siguiente, anterior, registro siguiente, registro anterior, flechas, navegar, recorrer, pasar al siguiente, siguiente factura, siguiente cliente, siguiente documento, flechas del modal, alt flecha, atajo de teclado, cambios sin guardar, descartar cambios, se perdieron los cambios
version: 1.0
orden: 35
estado: activo
---

## Qué son las flechas ‹ › del encabezado

Cuando abre un registro haciendo clic en una fila de un listado (una factura, un
cliente, un producto, un ingreso...), la ventana muestra dos flechas en el
encabezado, junto a la **X** de cerrar:

- **‹** abre el registro **anterior** del listado.
- **›** abre el registro **siguiente**.

Atajo de teclado: **Alt + ←** y **Alt + →**.

Funciona en todos los módulos con listado, sin configurar nada. La ventana no
se cierra: solo cambian los datos del registro.

## Qué orden sigue

El de la tabla **tal como la está viendo**: con la misma búsqueda, filtros y
orden de columnas. La fila del registro abierto queda resaltada en el listado.

- Al llegar a la última fila de la página, la flecha **›** pasa sola a la
  página siguiente y abre su primer registro; **‹** hace lo mismo hacia atrás.
- Cada flecha se desactiva cuando ya no hay más registros en esa dirección.
- Si abrió la ventana con un botón de la fila (por ejemplo, un ícono de
  pagos), las flechas repiten ese mismo botón en la fila vecina.
- Las flechas **no aparecen** al crear un registro nuevo, ni en ventanas que
  no se abrieron desde una fila del listado.

## Cambios sin guardar

Si modificó algo en la ventana y no pulsó **Guardar** / **Actualizar**, al usar
las flechas el sistema avisa **Cambios sin guardar**:

- **Seguir editando**: se queda en el registro actual, con sus cambios.
- **Descartar y continuar**: pierde esos cambios y pasa al otro registro.

Cuentan como cambios lo que se escribe o selecciona, y agregar o quitar líneas.
Solo mirar las pestañas o descargar el PDF no cuenta.

## Errores frecuentes

- **No veo las flechas.** La ventana se abrió con **Nuevo** o desde otro lugar
  que no es una fila del listado. Ábrala haciendo clic en la fila.
- **La flecha está gris.** Es el primer (o último) registro del listado con los
  filtros actuales.
- **La ventana cambia de tipo.** Si la fila vecina es otro tipo de documento
  que se abre en otra ventana, la actual se cierra y se abre la que corresponde.

## Historial de cambios

- **1.0** — Flechas anterior / siguiente en todas las ventanas abiertas desde un
  listado, con aviso de cambios sin guardar.
