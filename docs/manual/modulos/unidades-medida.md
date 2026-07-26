---
titulo: Unidades de medida
resumen: Unidades en las que se vende cada producto, agrupadas por tipo de medida.
categoria: Inventario
ruta_modulo: modulos/unidades-medida
tipo: modulo
visibilidad: todos
etiquetas: unidades, unidad de medida, medidas, kilo, litro, caja, unidad, peso, volumen, longitud
version: 1.0
orden: 60
estado: activo
---

Las **unidades de medida** definen en qué se vende cada producto: unidades,
cajas, kilos, litros, metros. Aparecen en la factura junto a la cantidad.

## Dos niveles: tipo y unidad

El catálogo tiene dos niveles:

- **Tipo de medida**: la magnitud. Peso, volumen, longitud, cantidad.
- **Unidad**: la medida concreta dentro de ese tipo. Dentro de peso: gramo, kilo,
  quintal.

Al crear una unidad hay que elegir a qué tipo pertenece. Esta separación permite
que, al configurar un producto, primero se elija la magnitud y después solo se
ofrezcan las unidades que tienen sentido para ella.

## Cómo se registra

1. Cree primero el **tipo de medida** si aún no existe (nombre de hasta 100
   caracteres; el código admite hasta 50).
2. Cree la **unidad** dentro de ese tipo.

## Errores frecuentes

- **"Debe seleccionar un tipo de medida"**: está creando una unidad sin indicar a
  qué magnitud pertenece.
- **No aparece la unidad al configurar un producto**: compruebe el tipo de medida
  elegido en el producto; solo se muestran las unidades de ese tipo.

## Historial de cambios

- **1.0** — Versión inicial.
