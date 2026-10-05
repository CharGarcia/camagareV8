---
titulo: Elegir cuántas filas se muestran por página
resumen: El selector 25 / 50 / 75 / 100 junto al paginador de cada listado, y cómo el sistema recuerda su elección.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: filas por pagina, registros por pagina, cuantas filas, mostrar mas filas, ver mas registros, paginador, paginacion, 25 50 75 100, solo veo 20, solo salen 25, cambiar cantidad de filas, listado corto, listado largo, flechas del paginador
version: 1.0
orden: 36
estado: activo
---

## Qué es el selector de filas por página

Todos los listados del sistema (Facturas de Venta, Clientes, Productos, Compras,
Ingresos, Egresos, Empleados, etc.) muestran sus registros por páginas. Junto a las
flechas **‹ ›** del paginador hay un selector pegado a ellas con cuatro opciones:
**25, 50, 75 y 100** filas por página.

De fábrica cada listado muestra **25** filas.

## Cómo se usa

1. Abra el listado.
2. En el selector junto a las flechas elija 25, 50, 75 o 100.
3. El listado vuelve a cargarse desde la primera página con esa cantidad.

## El sistema recuerda su elección

La cantidad elegida se guarda **para usted** y **por cada listado**, dentro de la
misma empresa: puede ver Facturas de 100 en 100 y Clientes de 25 en 25. La próxima
vez que abra ese listado se usa la cantidad que dejó, sin tener que volver a
elegirla. Otros usuarios no se ven afectados.

Se guarda con el mismo mecanismo que las columnas visibles y el orden de las
columnas (preferencias de vista), así que no requiere ninguna configuración por
parte del administrador.

## Qué listados lo tienen

Todos los listados principales con paginador. Las tablas pequeñas dentro de un
modal (por ejemplo, la pestaña *Transacciones* de la ficha de un cliente) mantienen
su tamaño fijo. En *Control Bancario* los movimientos se muestran completos a
propósito, así que no hay selector.

## Errores frecuentes

- **Cambié el valor y el listado se recargó con la búsqueda en blanco**: al elegir
  otra cantidad la página se recarga para aplicarla; en Facturas de Venta el
  listado se repinta sin recargar y la búsqueda se conserva.
- **No veo el selector**: ese listado no tiene paginador (reportes y pantallas de
  configuración muestran todo de una vez) o la tabla está dentro de un modal.

## Historial de cambios

- **1.0** — Selector 25/50/75/100 junto al paginador en todos los listados, con la
  elección guardada por usuario y listado.
