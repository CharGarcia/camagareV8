---
titulo: Fórmulas de producción
resumen: Define con qué se fabrica cada producto: insumos, lo que rinde, coproductos, subproductos, desperdicio, mano de obra y costos indirectos, con su costo estimado.
categoria: Producción
ruta_modulo: modulos/produccion-formulas
tipo: modulo
visibilidad: todos
etiquetas: formula, receta, lista de materiales, bom, bill of materials, escandallo, ficha tecnica, insumos, materia prima, rendimiento, rinde, merma, coproducto, subproducto, desperdicio, retazo, granel, envasado, formula en porcentaje, mano de obra, costos indirectos, cif, costo estimado, version de formula, activar formula
version: 1.0
orden: 1
estado: activo
---

La **fórmula** dice qué se consume para fabricar un producto y qué se obtiene.
Sirve para cualquier tipo de producción: cajas de cartulina, muebles, cosméticos,
alimentos, pan, cortes de carne, etc. Cada orden de producción copia la fórmula
vigente y la escala a la cantidad que se va a fabricar.

## Qué es y para qué sirve

Una fórmula tiene:

- **Producto que se fabrica** (salida principal) y **cuánto rinde** con las cantidades escritas.
- **Insumos**: materias primas, empaques, subensambles.
- **Otras salidas** (opcional):
  - **coproductos**: también son objetivo y se llevan parte del costo (cortes de carne, tallas);
  - **subproductos**: secundarios, entran al inventario a su valor neto y rebajan el costo de los demás (retazos, cascarilla);
  - **desperdicio**: solo se registra la cantidad.
- **Mano de obra y costos indirectos** estándar.
- **Vida útil**, para calcular la caducidad del lote.
- **Instrucciones de fabricación**, que salen impresas en el registro de lote.

## Requisitos previos

- La **Configuración de Producción** guardada.
- Los productos creados en **Productos** como bienes inventariables, incluido el producto que se fabrica.

## Cómo se usa

1. Pulse **Nueva** y elija el **producto que se fabrica**.
2. Indique cuánto **rinde** la fórmula (por ejemplo 1 caja, 120 panes o un lote de 100 kg).
3. En **Insumos**, agregue cada insumo con su forma de cantidad y su merma.
4. En **Otras salidas**, agregue coproductos, subproductos o desperdicio si los hay.
5. En **Mano de obra y CIF**, agregue los costos estándar.
6. Revise el **Costo estimado** y pulse **Guardar**: la fórmula queda en *borrador*.
7. Pulse **Activar** para poder producir con ella.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Código, Nombre | Sí | Identifican la fórmula. |
| Producto que se fabrica | Sí | La salida principal. |
| Rinde | Sí | Cuánto sale con las cantidades escritas. |
| Vida útil (días) | No | La caducidad del lote es la fecha de la orden más estos días. |
| Forma de cantidad | Sí | **Proporcional**: cantidad para lo que rinde la fórmula, escala con la orden. **% del lote**: porcentaje de la cantidad a producir (fórmulas de proceso). **Fija por orden**: no escala. |
| Merma % | No | Pérdida normal del insumo. Se suma al teórico y queda dentro del costo. |
| Lote | No | Exige indicar el lote de este insumo al consumir. |
| % / Valor | Según tipo | Coproducto: % fijo del costo (si el reparto es por %). Subproducto: valor neto por unidad. |
| Base de cálculo (costos) | Sí | Por unidad producida, fijo por orden, por hora (horas para lo que rinde) o % de la materia prima. |

## Permisos

- **Ver**, **Crear**, **Actualizar** (editar, activar, inactivar, nueva versión), **Eliminar**.
- Sin **acceso total**, cada usuario ve y gestiona solo las fórmulas que creó.

## Reglas de negocio

- Solo hay **una versión vigente** por producto. Activar una versión deja inactiva la anterior.
- Una fórmula **usada en órdenes no se modifica**: se crea una **Nueva versión** (las órdenes guardan la receta con la que se fabricaron).
- Si los insumos están todos en % del lote, deben sumar 100 %.
- El producto que se fabrica no puede ser insumo de su propia fórmula, y no se permiten ciclos entre fórmulas vigentes.
- **Doble descuento**: si el producto tiene **componentes de venta** en su ficha (los que se descuentan al venderlo, como en kits o platos del restaurante), la fórmula no se puede activar. Si no, sus insumos se descontarían al fabricar y otra vez al vender.

## Integraciones con otros módulos

- **Productos**: de ahí salen los insumos y salidas (y su costo, para el estimado).
- **Órdenes de producción**: copian y escalan la fórmula vigente.

## Errores frecuentes

- **«…tiene componentes de venta en su ficha…»**: quite los componentes del producto en Productos o use otro producto.
- **«…debe sumar 100 %»**: revise los porcentajes de los insumos.
- **«Esta versión ya se usó en órdenes…»**: use **Nueva versión**.

## Historial de cambios

- **1.0** — Versión inicial.
