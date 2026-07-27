---
titulo: Trazabilidad de productos
resumen: Historia completa de un producto: de qué compra entró y en qué venta salió.
categoria: Reportes
ruta_modulo: modulos/reporte_trazabilidad_productos
tipo: modulo
visibilidad: todos
etiquetas: trazabilidad, seguimiento, historia del producto, de donde vino, lote, entradas y salidas, auditoria de stock, quien cambio el producto, cambios de la ficha
version: 1.1
orden: 80
estado: activo
---

La **trazabilidad** sigue el rastro completo de un producto: por qué compra entró
cada unidad, a qué bodega fue y en qué venta salió.

## Para qué sirve

Para tres situaciones concretas:

- **Un reclamo de calidad**: saber de qué proveedor y de qué compra vino lo que
  se vendió a ese cliente.
- **Un descuadre de stock**: ver la secuencia completa de movimientos hasta
  encontrar el que sobra o falta.
- **Una auditoría**: demostrar de dónde salió cada unidad.

## Cómo se consulta

Se elige el producto y el rango de fechas, y el reporte muestra la cadena de
movimientos en orden: cada entrada con su documento de origen y cada salida con
su documento de destino.

## Qué significa cada tipo de línea

La línea de tiempo mezcla tres clases de evento, cada una con su color al inicio
de la fila:

| Evento | Qué es | Afecta el stock |
|---|---|---|
| **Producto creado / Ficha modificada / Producto eliminado** (morado) | Cambios en la ficha del producto | No |
| **Entrada** (verde) y **Salida** (roja) | Movimiento real de inventario con su documento | Sí |
| **Pedido, Proforma, Orden de compra, Guía de remisión** (gris) | El producto aparece en el documento, pero todavía no se movió nada | No |

## Cambios de la ficha: cómo se leen

Cuando alguien modifica el producto, la fila lista **campo por campo** qué había
antes y qué quedó después, en el idioma del sistema y no en el de la base de
datos. Por ejemplo:

> **Unidad de medida:** ~~LIBRA~~ → **KILOGRAMO**
> **Tarifa de IVA:** ~~12%~~ → **15%**
> **Se puede usar en:** ~~Compras, Ventas~~ → **Ventas**

Detalles a tener en cuenta:

- **Los valores se muestran con su nombre**, nunca con el número interno del
  catálogo. Si aparece un código en vez de un nombre, es que ese catálogo fue
  eliminado después del cambio.
- **`(vacío)`** significa que el campo se quedó sin dato: `Tipo de medida: UNIDAD
  → (vacío)` es "se quitó el tipo de medida".
- **"Se guardó la ficha sin cambios en sus datos principales"**: se abrió el
  producto y se grabó, pero lo que cambió fue un anexo (precios, bodegas,
  componentes o variantes), no la ficha. Es normal y no indica un error.
- **"1 registro (con cambios)"** en Componentes o Variantes: la cantidad de
  líneas es la misma, pero su contenido cambió.

## Requisitos

Solo funciona con productos **inventariables** y con líneas de compra
**vinculadas al catálogo**. Una compra sin vincular no aparece en la cadena,
porque el sistema no sabe que ese código del proveedor es este producto.

## Errores frecuentes

- **La cadena empieza a mitad**: hay compras anteriores sin vincular al catálogo,
  o el stock inicial se cargó por saldos iniciales.
- **El producto no aparece**: no es inventariable.
- **Un cambio de ficha no dice nada útil**: si la línea solo indica que se guardó
  sin cambios, revise las pestañas de precios, bodegas, componentes y variantes
  del producto; esos anexos se guardan aparte de la ficha.

## Historial de cambios

- **1.1** — Los cambios de la ficha se leen en lenguaje del sistema: nombres de
  campo y de catálogo en vez de códigos internos, valores con significado
  (`Producto`/`Servicio`, `Activo`/`Inactivo`, `Se puede usar en: Compras,
  Ventas`) y se dejaron de listar cambios que nunca ocurrieron. El PDF y el Excel
  incluyen ese mismo detalle.
- **1.0** — Versión inicial.
