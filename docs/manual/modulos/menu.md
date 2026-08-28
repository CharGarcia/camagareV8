---
titulo: Menú
resumen: Carta del restaurante: los platos y bebidas que se piden en las comandas.
categoria: Restaurante
ruta_modulo: modulos/menu
tipo: modulo
visibilidad: todos
etiquetas: menu, carta, platos, bebidas, restaurante, precio, iva, tarifa iva, producto vinculado, categoria, categorias, estacion, cocina, barra, enviar a, receta, comanda
version: 1.3
orden: 30
estado: activo
---

El **menú** es la carta: los platos y bebidas que el personal elige al tomar una
comanda. Un ítem del menú puede estar vinculado a un producto del catálogo o
existir solo en la carta.

## Cómo se registra un ítem

1. Pulse **Nuevo**.
2. Escriba el **nombre** (máximo 200 caracteres).
3. Si corresponde, elija el **producto vinculado**: al seleccionarlo se copian su
   precio, su tarifa de IVA y su categoría, y se calcula el precio con impuestos.
4. Revise la **tarifa de IVA** y la **categoría**, que puede cambiar. El
   **precio** solo se escribe si el ítem no tiene producto vinculado.
5. Elija la estación de **Enviar a**.
6. Guarde.

## El precio: quién lo define

- **Con producto vinculado**: el precio lo pone el producto y aquí queda de solo
  lectura, junto con el precio con impuestos. Para cambiarlo, se edita el
  producto en **Productos**. Así un mismo artículo no termina con dos precios
  distintos según dónde se lo mire.
- **Sin producto**: se escribe a mano, como cualquier precio de carta. También
  puede escribir el **precio con impuestos** y el precio base se calcula solo.

La **tarifa de IVA** y la **categoría** son distintas: se copian del producto
como punto de partida, pero quedan editables — son del ítem de la carta.

## Categorías: son las de Productos

El menú **no tiene una lista de categorías propia**: usa las mismas de
**Productos**. Se crean y se editan en ese módulo, y aquí solo se eligen.

Antes existía una lista aparte, solo del menú. Al unificarlas, los ítems que ya
estaban clasificados **quedaron sin categoría**: las dos listas no eran
equivalentes y conservar la anterior habría dejado cada plato en una categoría
que no le corresponde. Hay que volver a asignarlas una vez.

## A qué cocina o barra se envía cada plato

Lo define el campo **Enviar a** del propio ítem. Antes salía de la categoría, de
modo que toda una categoría iba a la misma estación; ahora se elige plato por
plato, así dos ítems de la misma categoría pueden ir a estaciones distintas.

Un ítem **sin estación** no pasa por preparación: se puede entregar directo, sin
esperar a que cocina o barra lo marquen como listo. Es lo correcto para una
bebida embotellada, por ejemplo.

Las estaciones se crean en la pestaña **Estaciones** de este mismo modal.

## Vinculado a un producto, o no

Es la decisión importante de este módulo:

- **Vinculado a un producto**: el ítem mueve el stock del producto si es
  inventariable, toma su precio (que ya no se edita aquí) y copia su tarifa de
  IVA y su categoría. Úselo para lo que se vende tal cual se compra: bebidas
  embotelladas, productos empacados.
- **Sin producto**: hay que **indicar la tarifa de IVA obligatoriamente**, porque
  no hay de dónde copiarla. Úselo para platos preparados, que no existen como
  producto del inventario.

## La tarifa de IVA de la carta manda

La tarifa que quede guardada en el ítem es la que se usa **en todo el recorrido**:
es la que ve el mesero en la comanda, la que suma el total de la mesa y la que
sale en la factura o el recibo.

Al vincular un producto, su tarifa se copia como **propuesta**, no como
imposición: si después la cambia aquí, manda la del ítem (a diferencia del
precio, que sí queda fijado por el producto). Por eso un mismo
producto puede facturarse con un IVA distinto según se venda por la carta o por
el POS de mostrador — si eso no es lo que busca, deje en el ítem la misma tarifa
que tiene el producto.

Solo cuando el ítem **no** tiene tarifa propia se usa la del producto vinculado.

## Errores frecuentes

- **"Selecciona la tarifa de IVA del ítem"**: el ítem no tiene producto vinculado,
  así que la tarifa hay que indicarla a mano.
- **El plato sale en la comanda con otro IVA del que le puse**: revise la tarifa
  del ítem en este módulo — es la que manda. Si está vacía, se usa la del
  producto vinculado.
- **El plato no llega a la pantalla de cocina**: no tiene estación en **Enviar
  a**. Sin estación, el ítem se entrega directo sin pasar por preparación.
- **No encuentro una categoría en la lista**: se crea en el módulo **Productos →
  Categorías**, no aquí.
- **No me deja escribir el precio**: el ítem tiene un producto vinculado, así que
  el precio sale de él. Cámbielo en **Productos**, o quite el vínculo (Backspace
  sobre el buscador de producto) si el plato debe llevar precio propio.
- **"El precio no puede ser negativo"**: revise el valor.
- **El plato no descuenta inventario**: es lo esperado si no tiene producto
  vinculado.

## Historial de cambios

- **1.3** — El precio de un ítem con producto vinculado ya no se edita en la
  carta: lo define el producto. Al vincularlo también se copia su categoría.
- **1.2** — Las categorías del menú pasan a ser las de Productos (se quitó la
  pestaña *Categorías* del modal y los ítems quedaron sin categoría, hay que
  reasignarlos). La estación de cocina/barra se configura ahora en cada ítem,
  con el campo *Enviar a*.
- **1.1** — La tarifa de IVA del ítem manda sobre la del producto vinculado en la
  comanda y en el comprobante (antes se guardaba pero se ignoraba). Al elegir el
  producto se copian su precio y su tarifa, y el formulario pone primero el
  producto vinculado.
- **1.0** — Versión inicial.
