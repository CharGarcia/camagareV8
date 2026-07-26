---
titulo: Menú
resumen: Carta del restaurante: los platos y bebidas que se piden en las comandas.
categoria: Restaurante
ruta_modulo: modulos/menu
tipo: modulo
visibilidad: todos
etiquetas: menu, carta, platos, bebidas, restaurante, precio, iva, receta, comanda
version: 1.0
orden: 30
estado: activo
---

El **menú** es la carta: los platos y bebidas que el personal elige al tomar una
comanda. Un ítem del menú puede estar vinculado a un producto del catálogo o
existir solo en la carta.

## Cómo se registra un ítem

1. Pulse **Nuevo**.
2. Escriba el **nombre** (máximo 200 caracteres).
3. Indique el **precio** (no puede ser negativo).
4. Vincule un producto del catálogo, o elija la **tarifa de IVA** del ítem.
5. Guarde.

## Vinculado a un producto, o no

Es la decisión importante de este módulo:

- **Vinculado a un producto**: el ítem hereda del producto su tarifa de IVA y,
  si es inventariable, su movimiento de stock. Úselo para lo que se vende tal
  cual se compra: bebidas embotelladas, productos empacados.
- **Sin producto**: hay que **indicar la tarifa de IVA obligatoriamente**, porque
  no hay de dónde heredarla. Úselo para platos preparados, que no existen como
  producto del inventario.

## Errores frecuentes

- **"Selecciona la tarifa de IVA del ítem"**: el ítem no tiene producto vinculado,
  así que la tarifa hay que indicarla a mano.
- **"El precio no puede ser negativo"**: revise el valor.
- **El plato no descuenta inventario**: es lo esperado si no tiene producto
  vinculado.

## Historial de cambios

- **1.0** — Versión inicial.
