---
titulo: Trazabilidad de productos
resumen: Historia completa de un producto: de qué compra entró y en qué venta salió.
categoria: Reportes
ruta_modulo: modulos/reporte_trazabilidad_productos
tipo: modulo
visibilidad: todos
etiquetas: trazabilidad, seguimiento, historia del producto, de donde vino, lote, entradas y salidas, auditoria de stock
version: 1.0
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

## Requisitos

Solo funciona con productos **inventariables** y con líneas de compra
**vinculadas al catálogo**. Una compra sin vincular no aparece en la cadena,
porque el sistema no sabe que ese código del proveedor es este producto.

## Errores frecuentes

- **La cadena empieza a mitad**: hay compras anteriores sin vincular al catálogo,
  o el stock inicial se cargó por saldos iniciales.
- **El producto no aparece**: no es inventariable.

## Historial de cambios

- **1.0** — Versión inicial.
