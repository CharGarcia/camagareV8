---
titulo: Menú
resumen: Carta del restaurante: los platos y bebidas que se piden en las comandas.
categoria: Restaurante
ruta_modulo: modulos/menu
tipo: modulo
visibilidad: todos
etiquetas: menu, carta, platos, bebidas, restaurante, precio, iva, tarifa iva, producto vinculado, categoria, categorias, estacion, cocina, barra, preparar en, enviar a, receta, comanda, impresora, imprimir ordenes, impresora de cocina, ancho de papel, copias, 58mm, 80mm
version: 1.8
orden: 30
estado: activo
---

El **menú** es la carta: los platos y bebidas que el personal elige al tomar una
comanda. Cada ítem apunta a un producto del catálogo, del que toma su precio, su
foto y el movimiento de inventario.

## Cómo se registra un ítem

1. Pulse **Nuevo**.
2. Escriba el **nombre** (máximo 200 caracteres).
3. Elija el **producto vinculado** (obligatorio): al seleccionarlo se copian su
   precio, su tarifa de IVA, su categoría y su foto, y se calcula el precio con
   impuestos.
4. Revise la **tarifa de IVA** y la **categoría**, que puede cambiar.
5. Elija dónde se prepara, en **Preparar en**.
6. Guarde.

## El precio lo define el producto

El **precio y el precio con impuestos son de solo lectura**: los pone el producto
vinculado. Para cambiarlos se edita el producto en **Productos**. Así un mismo
artículo no termina con dos precios distintos según dónde se lo mire.

La **tarifa de IVA** y la **categoría** son distintas: se copian del producto
como punto de partida, pero quedan editables — son del ítem de la carta.

## Categorías: son las de Productos

El menú **no tiene una lista de categorías propia**: usa las mismas de
**Productos**. Se crean y se editan en ese módulo, y aquí solo se eligen.

Antes existía una lista aparte, solo del menú. Al unificarlas, los ítems que ya
estaban clasificados **quedaron sin categoría**: las dos listas no eran
equivalentes y conservar la anterior habría dejado cada plato en una categoría
que no le corresponde. Hay que volver a asignarlas una vez.

## Dónde se prepara cada plato

Lo define el campo **Preparar en** del propio ítem. Antes salía de la categoría, de
modo que toda una categoría iba a la misma estación; ahora se elige plato por
plato, así dos ítems de la misma categoría pueden ir a estaciones distintas.

Un ítem **sin estación** no pasa por preparación: se puede entregar directo, sin
esperar a que cocina o barra lo marquen como listo. Es lo correcto para una
bebida embotellada, por ejemplo.

Las estaciones se crean en la pestaña **Estaciones** de este mismo modal.

## Las estaciones se configuran en Configuración Restaurante

La pestaña *Estaciones* de este modal **ya no existe**: el catálogo se administra
en **Configuración Restaurante**, junto con la impresora de cada estación y la
estación predeterminada. Aquí queda el selector *Preparar en*, que sigue siendo
por ítem.

Lo que se configura allí, por estación:

| Opción | Para qué |
| --- | --- |
| Papel | 58 u 80 mm, según la impresora térmica de esa estación. Ajusta el tamaño de letra del ticket. |
| Copias | Cuántas veces sale cada orden (por ejemplo 2: una para quien prepara y otra para el pase). |
| Sale sola al enviar a cocina | Marcada, la orden se imprime al enviar la comanda. Sin marcar, solo se imprime cuando alguien la pide desde la comanda. |

Quien saca el papel es la **pantalla de preparación (KDS) de esa estación**, que
debe estar abierta en un equipo con la impresora conectada: el sistema no puede
imprimir por su cuenta en la red del restaurante. El detalle está en el manual
del KDS.

Una estación puede quedarse **solo como pantalla** (es lo que viene por
defecto): sin marcar esa casilla, nada cambia respecto a como funcionaba antes.
## Todo ítem va vinculado a un producto

El **producto vinculado es obligatorio**. La carta es una forma de presentar el
catálogo, no un catálogo aparte: del producto salen el precio, la foto y el
movimiento de inventario, y sin él el ítem **no se puede cobrar** — al cerrar la
cuenta el sistema rechaza cualquier línea que no apunte a un producto.

Si el plato no existe todavía como producto, créelo primero en **Productos**.
Para un plato preparado, lo habitual es un producto **compuesto** (un combo con
sus componentes): así, al facturar, se descuenta el inventario de cada
ingrediente.

## La foto es la del producto

La foto que se ve en la carta es la **del producto vinculado**: son el mismo
artículo, no tiene sentido que tengan dos fotos distintas según dónde se lo mire.

Al elegir el producto, su foto se trae al modal. Y si la **cambia desde aquí**,
se actualiza también en **Productos** — es la misma foto, no una copia. Cámbiela
sabiendo eso: afecta al catálogo, a la búsqueda de productos y a donde sea que
esa foto se muestre.

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

- **"Selecciona el producto vinculado"**: es obligatorio. Si el plato no existe
  como producto, créelo primero en **Productos**. Los ítems cargados antes de
  esta regla siguen funcionando, pero al editarlos habrá que vincularlos para
  poder guardar.
- **El plato sale en la comanda con otro IVA del que le puse**: revise la tarifa
  del ítem en este módulo — es la que manda. Si está vacía, se usa la del
  producto vinculado.
- **El plato no llega a la pantalla de cocina**: no tiene estación en **Preparar
  en**. Sin estación, el ítem se entrega directo sin pasar por preparación.
- **No encuentro una categoría en la lista**: se crea en el módulo **Productos →
  Categorías**, no aquí.
- **No me deja escribir el precio**: es lo esperado — el precio lo define el
  producto vinculado. Cámbielo en **Productos**.
- **"El precio no puede ser negativo"**: revise el valor.

## Historial de cambios

- **1.8** — La pestaña *Estaciones* sale de este modal: el catálogo se administra
  en el módulo **Configuración Restaurante**. Aquí queda el selector *Preparar en*.
- **1.7** — Cada estación puede configurar su impresora: si imprime las órdenes
  en papel, el ancho (58/80 mm), las copias y si la orden sale sola al enviar a
  cocina o solo a pedido.
- **1.6** — La columna y el campo de la estación pasan a llamarse "Preparar en".
- **1.5** — El producto vinculado pasa a ser obligatorio: un ítem de la carta
  siempre apunta a un producto del catálogo.
- **1.4** — La foto del ítem es la del producto vinculado: se trae al elegirlo y,
  al cambiarla desde la carta, se actualiza también en Productos. El listado
  muestra una columna "Enviar a" con la estación de cada plato.
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
