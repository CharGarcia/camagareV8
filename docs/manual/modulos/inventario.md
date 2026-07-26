---
titulo: Inventario
resumen: Existencias por bodega y kardex con todos los movimientos de cada producto.
categoria: Inventario
ruta_modulo: modulos/inventario
tipo: modulo
visibilidad: todos
etiquetas: inventario, stock, existencias, kardex, movimientos, ajuste, entradas, salidas, bodega, costo
version: 1.0
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

- **1.0** — Versión inicial.
