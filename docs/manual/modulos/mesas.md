---
titulo: Mesas
resumen: Mesas del local, sobre las que se abren las comandas del servicio de restaurante.
categoria: Restaurante
ruta_modulo: modulos/mesas
tipo: modulo
visibilidad: todos
etiquetas: mesas, mesa, restaurante, salon, comanda, ocupada, disponible, tablero, cocina, barra, pantalla de preparacion, kds
version: 1.1
orden: 10
estado: activo
---

Las **mesas** son los puestos del local sobre los que se abre una comanda. Es un
catálogo mínimo: nombre y poco más, pero es lo que permite saber qué está ocupado
y qué está libre.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** de la mesa (máximo 100 caracteres).
3. Guarde.

Conviene usar nombres que el personal reconozca de inmediato: `Mesa 1`,
`Terraza 3`, `Barra 2`.

## Disponible u ocupada

Una mesa con una comanda abierta está **ocupada** y no admite otra comanda: al
intentarlo, el sistema avisa de que *la mesa no está disponible*. Se libera al
cerrar o anular la comanda.

## El tablero del salón

Además del listado, las mesas tienen un **tablero**: el plano del local, con cada
mesa en su sitio y su estado a la vista. Es la pantalla con la que trabaja el
salón durante el servicio.

Cada mesa ocupada muestra su consumo **con impuestos y recargo por servicio
incluidos**: el mismo valor que el pie de la comanda y que la factura, no la
suma de precios sin IVA.

En su barra superior hay accesos directos a la **pantalla de preparación**
(cocina, barra o las estaciones que tenga el local): un botón por estación
cuando hay tres o menos, y un desplegable **Preparación** cuando hay más. Se
abren en una pestaña nueva, así que el tablero queda intacto detrás y se vuelve
a él cerrando la pestaña.

Dos detalles a tener en cuenta:

- Los botones solo aparecen si el local tiene **estaciones creadas** (se crean en
  Menú → pestaña *Estaciones*).
- Solo los ve quien tenga permiso de lectura sobre la **pantalla de preparación**.
  Si un mesero no los ve y sus compañeros sí, es cuestión de permisos, no del
  tablero.

## Errores frecuentes

- **"La mesa no está disponible"**: tiene una comanda abierta. Ciérrela o
  anúlela antes de abrir otra.
- **Una mesa quedó ocupada sin nadie sentado**: hay una comanda abierta olvidada;
  búsquela en Comandas y ciérrela.

## Historial de cambios

- **1.1** — El importe de cada mesa se muestra con impuestos incluidos, y el
  tablero del salón muestra accesos directos a la pantalla de
  preparación, uno por estación.
- **1.0** — Versión inicial.
