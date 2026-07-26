---
titulo: Reporte de inventarios
resumen: Existencias y movimientos por producto y bodega, para cuadrar y valorar el stock.
categoria: Reportes
ruta_modulo: modulos/reporte_inventarios
tipo: modulo
visibilidad: todos
etiquetas: reporte de inventario, existencias, stock por bodega, valorizacion, kardex, faltantes, exportar
version: 1.0
orden: 40
estado: activo
---

El **reporte de inventarios** muestra las existencias y los movimientos del
periodo, por producto y por bodega. Es la herramienta para el conteo físico y
para valorar lo que hay en almacén.

## Qué permite ver

- Existencias actuales por producto y bodega.
- Movimientos del periodo: qué entró, qué salió y de dónde vino cada movimiento.
- Valor del inventario según el costo registrado.

## Para el conteo físico

El uso más común: se imprime el listado de existencias, se cuenta en bodega, se
anotan las diferencias y se ajustan en Inventario. Es lo que convierte un conteo
en una corrección trazable en lugar de un número cambiado a mano.

## Solo productos inventariables

Únicamente aparecen los productos marcados como **inventariables**. Si un
artículo no está en el reporte, revise su ficha antes de dar por perdido el
stock.

## Exportar

Disponible en **PDF** y **Excel**. Para el conteo, el PDF es el más práctico.

## Errores frecuentes

- **Un producto no aparece**: no es inventariable.
- **El stock está en otra bodega**: revise el filtro de bodega.
- **El valor no coincide con la contabilidad**: compare contra el mayor de la
  cuenta de inventario; las diferencias suelen venir de compras sin procesar sus
  entradas.

## Historial de cambios

- **1.0** — Versión inicial.
