---
titulo: Fórmulas de producción
resumen: Define con qué se fabrica cada producto: insumos, lo que rinde, coproductos, subproductos, desperdicio, mano de obra y costos indirectos, con su costo estimado.
categoria: Producción
ruta_modulo: modulos/produccion-formulas
tipo: modulo
visibilidad: todos
etiquetas: formula, receta, unidad del insumo, gramos, kilos, convertir unidad, borrador, recuperar formula, no se guardo, codigo de formula, descripcion de formula, unidad de medida de la formula, buscar formula, filtrar formulas, en que formulas se usa un insumo, donde se usa un producto, lista de materiales, bom, bill of materials, escandallo, ficha tecnica, insumos, materia prima, rendimiento, rinde, merma, coproducto, subproducto, desperdicio, retazo, granel, envasado, formula en porcentaje, mano de obra, costos indirectos, cif, costo estimado, version de formula, activar formula
version: 1.6
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

1. Pulse **Nueva** y elija el **producto que se fabrica**. Si el producto aún no existe, créelo con el botón de la caja (**Registrar nuevo producto**) de la barra superior: al guardarlo queda puesto como producto que se fabrica.
2. Indique cuánto **rinde** la fórmula (por ejemplo 1 caja, 120 panes o un lote de 100 kg). El **código** lo pone el sistema al guardar y la **descripción** es opcional. Cada campo de la cabecera tiene un ícono **?** con su explicación.
3. En **Insumos**, agregue cada insumo con su forma de cantidad y su merma.
4. En **Otras salidas**, agregue coproductos, subproductos o desperdicio si los hay.
5. En **Mano de obra y CIF**, agregue los costos estándar.
6. Revise el **Costo estimado** y pulse **Guardar**: la fórmula queda en *borrador*.
7. Pulse **Activar** para poder producir con ella.

## Buscar y filtrar fórmulas

El buscador del listado funciona igual que en Proveedores:

- Escriba en el cuadro para buscar por **código**, **descripción** o **producto** de la fórmula.
- Pulse el **embudo** para abrir todos los filtros. Los filtros activos se muestran como etiquetas junto al buscador; cada una se quita con su ×.

| Grupo | Filtros |
|-------|---------|
| Fórmula | Código, descripción, **estado** (borrador, vigente, inactiva) y versión |
| Producto que fabrica | Código y nombre del producto, rinde (cantidad base) y vida útil en días |
| Contenido | **Usa el insumo** (fórmulas que consumen un producto, por código o nombre), **Produce** (coproducto, subproducto o desperdicio), número de insumos y de salidas, y método de reparto del costo |
| Registro | Fecha de registro, última modificación y usuario que la registró |

Por ejemplo, para saber en qué recetas se usa la harina, abra el embudo y escriba *harina* en **Usa el insumo**.

## Crear un producto desde la fórmula

En la barra superior del modal está el botón **Registrar nuevo producto** (ícono de caja), igual que en Facturas de Venta. Abre la ficha de Productos encima de la fórmula; al guardar el producto nuevo, la ficha se cierra sola y el producto queda puesto en la fórmula:

- Si la fórmula todavía no tiene **producto que se fabrica**, queda como ese producto.
- Si ya lo tiene y está abierta la pestaña **Otras salidas**, entra en la primera salida vacía o en una nueva (como coproducto; cambie el tipo si es subproducto o desperdicio).
- En cualquier otra pestaña, entra en el primer **insumo** vacío o en uno nuevo.

El botón solo aparece si el usuario tiene permiso de **Crear** en **Productos** y la fórmula se puede modificar. Los servicios no llevan inventario, así que un servicio creado así no se coloca en la fórmula (sale un aviso).

## Unidad de cada insumo

La columna **Unidad** de las tablas de Insumos y Otras salidas muestra la unidad del producto. Si en **Unidades de medida** hay otras del **mismo tipo** (peso, volumen, longitud…), aparece un selector para escribir la cantidad en la que sea más cómoda:

- La receta pide **500 GRAMOS** de levadura y la levadura se lleva en el inventario en **KILOGRAMOS**: elija GRAMO y escriba 500.
- Al crear la orden (y en el **Costo estimado**), el sistema convierte a la unidad del producto: 500 g = **0,5 kg**. El stock, el kardex y la orden siguen siempre en la unidad del producto.
- La ★ marca la unidad del producto. Solo se ofrecen unidades del mismo tipo: no se puede pasar de kilos a litros.
- En **% del lote** no hay unidad: el porcentaje ya es de lo que se produce.

## Si se cierra sin guardar

Mientras llena una fórmula **nueva**, lo que escribe se copia en este navegador. Si cierra el modal, recarga la página o se corta la conexión antes de guardar, al pulsar **Nueva** el sistema pregunta si quiere **recuperarla** o **empezar de cero**. La copia se borra sola al guardar la fórmula. Es solo de ese navegador y de ese usuario: no se ve desde otra computadora.

## Pestaña Información

Quién creó la fórmula y quién la modificó por última vez (con fecha y hora) y el historial de lo que pasó (creación, cambios, activación, inactivación, nuevas versiones). Solo la ven los administradores (niveles 2 y 3). Ver [Pestaña Información](guias/pestana-informacion).

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Código | Automático | Lo pone el sistema al guardar (F-000001, F-000002…); no se escribe ni se cambia. Une las versiones de la receta: *Nueva versión* conserva el código. |
| Descripción | No | Etiqueta corta para reconocer la receta (p. ej. *Receta económica*). Si se deja vacía, se muestra el nombre del producto. |
| Producto que se fabrica | Sí | La salida principal. Su unidad de medida (la de la ficha del producto) es la unidad de la fórmula. |
| Rinde | Sí | Cuánto sale con las cantidades escritas, en la unidad del producto (kilos, litros, cajas…). Para producir en otra medida, cambie la unidad en la ficha del producto. |
| Vida útil (días) | No | La caducidad del lote es la fecha de la orden más estos días. |
| Unidad | Según el producto | La unidad en que escribe la cantidad del insumo o de la salida. Si el producto tiene otras unidades de su mismo tipo (kilo, gramo, quintal…), puede elegir cualquiera: la ★ es la unidad del producto (la del inventario). Al producir, la cantidad se convierte sola a la unidad del producto. En **% del lote** no hay unidad. |
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
- **1.1** — La pantalla ocupa todo el ancho de la ventana, como los demás módulos. El aviso de empresa sin configurar ya no lleva enlace a la Configuración de Producción.
- **1.2** — Buscador con **filtros** (embudo), igual que Proveedores: estado, versión, producto, rinde, vida útil, insumo que usa, lo que produce, número de insumos y salidas, reparto del costo, fechas y usuario. Se quita el selector de estado junto al buscador: ahora es un filtro más.
- **1.3** — Barra superior con el botón **Registrar nuevo producto** (como en Facturas de Venta): el producto creado queda puesto como producto que se fabrica, insumo u otra salida.
- **1.4** — El **código** de la fórmula lo genera el sistema (F-000001…) y no se edita; **Nombre** pasa a **Descripción**, opcional (vacía muestra el producto). Íconos **?** con ayuda en producto, código, descripción, rinde, vida útil y reparto del costo. Las fórmulas existentes conservan su código y su nombre (ahora descripción).
- **1.5** — Columna **Unidad** con selector: la cantidad de un insumo u otra salida se puede escribir en otra unidad del mismo tipo que la del producto (se convierte al producir). Una fórmula nueva sin guardar se conserva en el navegador y se ofrece recuperarla. Requiere `database/2026-11-19_produccion_formulas_unidad_medida.sql`.
- **1.6** — Los datos de quién creó y modificó la fórmula pasan a la pestaña **Información** (con su historial); se quita la línea del pie del modal.
