---
titulo: Inventario
resumen: Existencias por bodega y kardex con todos los movimientos de cada producto.
categoria: Inventario
ruta_modulo: modulos/inventario
tipo: modulo
visibilidad: todos
etiquetas: inventario, stock, existencias, kardex, movimientos, ajuste, entradas, salidas, bodega, costo, buscar movimientos, buscador, filtros, filtrar movimientos, buscar por lote, buscar por serial, movimientos por bodega, chips
version: 1.3
orden: 20
estado: activo
---

El módulo de **Inventario** responde dos preguntas: *cuánto tengo* de cada
producto y *por qué* tengo esa cantidad. Lo primero es el **stock**; lo segundo,
el **kardex**.

## Stock y kardex

- **Stock**: la existencia actual de cada producto en cada bodega. Es una foto.
- **Kardex**: el historial de movimientos que llevó a esa cantidad, con su costo.
  Es la película.

Cuando el stock no cuadra, la respuesta siempre está en el kardex.

## Buscar y filtrar los movimientos

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel. Al lado queda el interruptor
**Ver anulados**, que muestra solo los movimientos anulados.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del listado: fecha (tal
como se ve, por ejemplo *02-09-2026*), producto y su código, bodega, cantidad,
unidad de medida, lote, fecha de caducidad, NUP/serial, usuario y observaciones.
También encuentra el producto por su código auxiliar o de barras. Las columnas
**Tipo** (entrada o salida) y el **Origen** del movimiento no entran en la
búsqueda libre: para filtrar por ellas use la ventana de filtros. Puede escribir
varias palabras en cualquier orden y no importan mayúsculas ni tildes. Para
limpiar, borre el texto o pulse Escape en el cuadro. Mientras busca, aparece un
**círculo girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento.
La ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Movimiento | Fecha del movimiento (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), tipo (entrada / salida), origen (factura de venta, compra, ajuste manual…), bodega, usuario, unidad de medida, observaciones |
| Producto | Producto, código, categoría |
| Lote y serie | Lote, NUP / serial, fecha de caducidad, con o sin lote, con o sin NUP / serial |
| Valores | Cantidad, costo unitario y costo total (cada uno con mínimo y máximo) |

El selector de *Origen* lista solo los orígenes que ya tienen movimientos en la
empresa, y *Categoría* solo las categorías de productos con movimientos. La
*Cantidad* se filtra sin signo, igual que se ve en la columna (una salida de 5
unidades es cantidad 5).

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. Los botones de **PDF** y **Excel** exportan con
la misma búsqueda y filtros que haya en pantalla.

## De dónde salen los movimientos

Casi ningún movimiento se registra a mano: los generan otros módulos.

| Movimiento | Lo origina |
|------------|------------|
| Entrada | Procesar las entradas de una **compra** |
| Salida | Emitir una **factura de venta** o un recibo |
| Entrada | Una **nota de crédito** de venta (devolución del cliente) |
| Entrada / salida | Traslados entre bodegas |
| Entrada / salida | **Ajuste manual** |

Por eso, si el stock de un producto está mal, lo primero es mirar qué documento
generó el movimiento equivocado, no corregir el número a mano.

## Ajuste manual

El ajuste sirve para cuadrar el sistema con la realidad física: mercadería
dañada, faltantes tras un conteo, sobrantes. Registra un movimiento con su
motivo, de modo que quede el rastro de quién ajustó y por qué.

Un ajuste no es un atajo para corregir un error de otro documento: si la compra
entró mal, corrija la compra.

## Solo productos inventariables

Únicamente los productos marcados como **inventariables** en su ficha generan
movimientos. Un servicio, o un producto no inventariable, se factura sin
problema pero no aparece en el kardex ni tiene stock.

## Errores frecuentes

- **Compré y el stock no subió**: registrar la compra no mueve el stock. Hay que
  **procesar las entradas** y, antes, vincular cada línea con un producto del
  catálogo.
- **El producto no aparece en el kardex**: no está marcado como inventariable.
- **El stock está en la bodega equivocada**: revise la bodega elegida al procesar
  la entrada.
- **El costo del kardex no es el que esperaba**: el costo entra con el documento
  que originó el movimiento; revise el precio de esa compra.

## Historial de cambios

- **1.3** — Nuevo buscador de movimientos: el cuadro ya no despliega
  sugerencias; lo que se escribe se busca en todas las columnas del listado
  (fecha, producto, bodega, cantidad, medida, lote, caducidad, NUP, usuario y
  observaciones), salvo Tipo y Origen. Los filtros pasan a una **ventana propia**
  (botón del embudo, se aplican con *Aplicar*) con criterios nuevos: categoría
  del producto, observaciones, fecha de caducidad, con/sin lote, con/sin NUP,
  cantidad, costo unitario y costo total. Los filtros activos se ven como
  etiquetas dentro del cuadro y la tabla se atenúa mientras busca. Además, los
  botones de **PDF** y **Excel** ahora respetan la búsqueda y los filtros
  (antes exportaban sin ellos).
- **1.2** — Si la base todavía no tiene los índices de los selectores "Origen" y
  "Usuario", el módulo ya no abre **más lento** que antes de la versión 1.1: la
  búsqueda por índice sin su índice releía el kardex una vez por cada usuario
  (medido: 2,4 s con 300.000 movimientos), y ahora en ese caso se hace una sola
  pasada. **Requiere ejecutar** `database/20260916_reporte_inventarios_indices_ajuste.sql`,
  que reemplaza al SQL de la versión 1.1; con él los selectores cargan en ~1 ms.
- **1.1** — El módulo **abre más rápido**. Los selectores "Origen" y
  "Usuario" del filtro se llenaban recorriendo todos los movimientos de
  kardex de la empresa; ahora se resuelven por índice. **Requiere ejecutar**
  `database/20260914_indices_reporte_inventarios_arranque.sql`; sin él el
  módulo funciona igual, solo que sin la mejora de velocidad.
- **1.0** — Versión inicial.
