---
titulo: Órdenes de producción
resumen: Registra la fabricación: consume los insumos de la bodega, da entrada a lo producido con su costo real y genera los asientos.
categoria: Producción
ruta_modulo: modulos/produccion-ordenes
tipo: modulo
visibilidad: todos
etiquetas: orden de produccion, orden de fabricacion, op, buscar orden, filtrar ordenes, fabricar, producir, consumir materia prima, consumo de insumos, consumo por peso, bobina, pesar, registro de lote, batch record, lote de produccion, caducidad, costo de produccion, costo real, costo unitario, merma anormal, coproductos, subproductos, cerrar orden, anular orden, trazabilidad de lotes, retiro de mercado, productos en proceso, asiento de produccion
version: 1.10
orden: 2
estado: activo
---

La **orden de producción** registra lo que se fabrica: qué insumos salieron de la
bodega, qué se obtuvo y cuánto costó. Usa el mismo inventario y kardex que el
resto del sistema, con movimientos propios (*Consumo de producción* y *Entrega
de producción*).

## Qué es y para qué sirve

Cada fabricación es una **orden de producción**, por etapas: se planifica, se registran los consumos (parciales, por peso, con lote, insumos fuera de fórmula) y se cierra con lo que realmente se obtuvo.

## Requisitos previos

- La **Configuración de Producción** guardada.
- Una **fórmula vigente** del producto.
- Stock de los insumos en la bodega de insumos (salvo que la configuración permita consumir sin stock).

## Cómo se usa

1. Pulse **Nueva**, elija la fórmula, la cantidad, las bodegas, el lote y la caducidad. Pulse **Crear orden**: queda *Planificada*.
   - La tabla de lo que se va a consumir muestra cada insumo en la **unidad en que se escribió la fórmula** (por ejemplo GRAMO). Si esa unidad no es la del producto, debajo se ve la equivalencia en la unidad del inventario (por ejemplo «= 1,275 Kilogramo»), que es lo que se descuenta del stock. El stock y los faltantes también se muestran en la unidad de la fórmula.
2. Abra la orden, pestaña **Consumir**: indique lo que salió de cada insumo.
   - Use **Por peso** para consumir por diferencia de peso (peso inicial − peso final), por ejemplo una bobina o un saco.
   - Elija el **lote** o deje *Automático* (el que vence primero).
   - Puede agregar un **insumo fuera de fórmula**.
   - Puede registrar varios consumos en distintos momentos.
3. Pestaña **Cerrar**: indique lo obtenido de cada salida (principal, coproductos, subproductos, desperdicio), la merma anormal y la mano de obra y costos indirectos.
   - Marque **Consumir lo pendiente según la fórmula** si no registró todos los consumos.
   - Pulse **Cerrar orden** en la barra de arriba (botón verde, junto a Anular).
   - Indique la **fecha de cierre** (no puede ser futura ni anterior a la orden) y pulse **Verificar**.
   - El sistema revisa **todo sin cerrar todavía**: que haya cantidad del producto principal, que haya consumos (o *Consumir lo pendiente* marcado), y que el stock, los lotes exigidos, el período contable y los costos permitan cerrar. Muestra una lista con ✓ o ✗ en cada punto.
   - Si algo falla, dice qué corregir y no cierra. Si todo está bien, muestra lo que entrará al inventario (cantidad, bodega y costo) y pide confirmar con **Sí, cerrar orden**.
4. Use el botón **PDF** para imprimir la orden / registro de lote. Tiene el mismo diseño que los demás documentos: logo y datos de la empresa, el número de la orden en un recuadro, las secciones de la orden, espacios de firma (Elaboró / Pesó, Verificó, Aprobó) y el número de página. Si la orden está anulada, lo indica en grande.

**Unidades dentro de la orden**: en las pestañas **Plan**, **Consumir** y **Cerrar** (y en el PDF), cada insumo y salida se muestra y se escribe en la unidad en que se armó la fórmula (por ejemplo GRAMO), con su equivalencia en la unidad del inventario. El sistema convierte solo: lo que se descuenta o entra al stock va siempre en la unidad del producto. Los **movimientos** (kardex) se ven en la unidad del producto. Un insumo agregado fuera de fórmula usa la unidad del producto.

## Asiento contable de la orden

Si la contabilización de Producción está encendida (Configuración Contable → Módulos que contabilizan) y las cuentas están configuradas (Configuración Contable → **Producción**, o el botón **Configurar cuentas** de la Configuración de Producción), cada orden genera sus asientos con prefijo **PR-**:

- **Un asiento por cada consumo**: Productos en proceso (Debe) contra Inventario de materia prima (Haber).
- **Un asiento al cerrar**: Inventario de producto terminado (Debe) contra Productos en proceso, Mano de obra aplicada y Costos indirectos (Haber); la merma anormal va a su cuenta de gasto.

La pestaña **Asiento** de la orden (visible para quien tiene acceso a Contabilidad → Asientos Contables) tiene un selector con los asientos de la orden. Quien además puede **actualizar** asientos puede corregir el asiento elegido (cuentas, valores, centro de costo, referencia), agregar líneas y pulsar **Guardar asiento**; debe cuadrar Debe = Haber. Un asiento corregido a mano ya no se rehace solo; con **Restaurar automático** se descarta la edición y se vuelve a armar con las cuentas configuradas.

Si falta una cuenta, la orden igual se registra: arriba aparece el aviso del asiento pendiente y el botón **Generar asiento** para reintentar después de configurarla.

## Pestaña Información

Quién creó la orden y quién hizo la última acción (con fecha y hora), la línea de tiempo de lo que pasó (creación, consumos, cierre, anulación) y los movimientos de inventario que generó, también los anulados. Solo la ven los administradores (niveles 2 y 3). Ver [Pestaña Información](guias/pestana-informacion).

## Buscar y filtrar órdenes

El listado tiene el mismo buscador que Proveedores:

- Escriba en el cuadro para buscar por **número**, **producto**, **lote**, **responsable** u **observaciones**.
- Pulse el **embudo** para abrir todos los filtros. Los filtros activos se muestran como etiquetas junto al buscador; cada una se quita con su ×.

| Grupo | Filtros |
|-------|---------|
| Orden | Número, **estado** (planificada, en proceso, cerrada, anulada), tipo, responsable, fecha de la orden y fecha de cierre |
| Producto | Código y nombre del producto, lote y caducidad |
| Cantidades y costos | Planificado, producido, costo total y merma anormal |
| Bodegas y contabilidad | Bodega de insumos, bodega de destino y si la orden tiene asiento |
| Registro | Fecha de registro y usuario que la registró |

Las columnas, el PDF y el Excel están pegados al final del buscador. El PDF y el Excel respetan los filtros aplicados.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fórmula vigente | Sí | La receta con la que se fabrica. Al hacer clic en el campo se listan las fórmulas vigentes; escriba para filtrar por código, nombre o producto. |
| Cantidad a producir | Sí | Del producto principal. La fórmula se escala a esta cantidad. |
| Fecha | Sí | Fecha de la orden. No puede ser futura. |
| Bodega de insumos / de destino | Sí | De dónde salen los insumos y a dónde entra lo producido (cuarentena si está activa). |
| Lote | No | Lote de lo producido. Si se deja vacío, es el número de la orden. |
| Caducidad | No | Si la fórmula tiene vida útil, se sugiere sola. Si cambia de fórmula, la sugerencia se actualiza; si la escribió a mano, se respeta. |
| Merma anormal | No | Unidades del producto principal perdidas fuera de lo normal: su costo va a gasto. |

## Permisos

- **Crear**: nueva orden.
- **Actualizar**: consumir, cerrar y generar asientos.
- **Eliminar**: anular y eliminar.
- Sin **acceso total**, cada usuario ve y gestiona solo las órdenes que creó.

## Reglas de negocio

- **Costo real**: materiales consumidos (al costo del kardex con el método del establecimiento: promedio, FIFO o LIFO) + mano de obra + costos indirectos.
- **Coproductos**: el costo se reparte por valor de venta, unidades o % fijo. Los **subproductos** entran a su valor neto y rebajan el costo de los demás.
- **Merma**: la normal (la de la fórmula) sube el costo unitario; la **anormal** se separa y va a gasto.
- Lo que entra al inventario cuadra al centavo con el costo de la orden.
- **Un clic = un registro**: un doble clic o un reintento tras «Error de conexión» no consume ni produce dos veces.
- Los movimientos de producción **no se pueden editar ni anular desde Inventario**: se anula la orden.
- **Anular** revierte las entregas y los consumos y anula los asientos. No se puede si lo producido ya se vendió o se usó, ni si la fecha está en un período contable cerrado.
- **Eliminar** solo se permite en una orden planificada sin consumos o en una anulada.
- El listado muestra las órdenes del ambiente actual de la empresa. Los reportes muestran solo producción.

## Integraciones con otros módulos

- **Inventario / Kardex**: salidas *Consumo de producción* y entradas *Entrega de producción*, con lote y caducidad.
- **Contabilidad** (cuentas en Configuración Contable → Producción; se apaga en *Módulos que contabilizan*):
  - cada consumo genera **Productos en proceso contra Materia prima**;
  - el cierre genera **Producto terminado (+ Merma) contra Productos en proceso, Mano de obra aplicada y Costos indirectos aplicados**.
  - Si falta una cuenta, la orden se guarda igual, muestra el aviso y ofrece **Generar asiento**.
- **Trazabilidad**: la pestaña *Trazabilidad* muestra qué lotes de insumo se usaron y qué lotes salieron.

## Errores frecuentes

- **«Stock insuficiente de …»**: falta el insumo en la bodega de insumos. Registre la compra o la transferencia.
- **«… maneja lotes: indique de qué lote sale»**: la configuración o la fórmula exige lote para ese insumo.
- **«No se puede anular: … ya se vendió o se usó»**: reverse primero los documentos que consumieron lo producido.
- **«Asiento pendiente: …»**: complete las cuentas en Configuración Contable → Producción y pulse **Generar asiento** (las órdenes cerradas pendientes también se contabilizan solas desde el aviso de asientos pendientes).

## Historial de cambios

- **1.0** — Versión inicial.
- **1.1** — Las cuentas de los asientos se configuran en **Configuración Contable → Producción**; los asientos se numeran **PR-** y las órdenes cerradas sin asiento entran en el aviso de asientos pendientes.
- **1.2** — La pantalla ocupa todo el ancho de la ventana, como los demás módulos. El aviso de empresa sin configurar ya no lleva enlace a la Configuración de Producción.
- **1.3** — Nueva orden / Producción rápida: la caducidad sugerida se actualiza al cambiar de fórmula; la tabla ya no muestra datos de una cantidad anterior al cambiar rápido; lo escrito en *Producido real* se conserva si solo cambia la bodega de insumos y, si cambia la fórmula o la cantidad, se avisa que volvió a lo teórico.
- **1.4** — Nueva orden / Producción rápida: al hacer clic en *Fórmula vigente* se listan las fórmulas sin tener que escribir; la fecha es obligatoria y no puede ser futura; la tabla de lo que se va a consumir solo muestra el stock de una bodega a la que el usuario tiene acceso y solo con fórmulas vigentes.
- **1.5** — Nuevo diseño del listado, igual que Proveedores: buscador con **filtros** (embudo) en lugar de la tarjeta de filtros y totales. El botón pasa a llamarse **Nueva** y se quita el botón de **Producción rápida**.
- **1.6** — Nueva orden: los insumos y salidas se muestran en la unidad en que se escribió la fórmula, con su equivalencia en la unidad del inventario. Se quita el aviso informativo de arriba del modal.
- **1.7** — Dentro de la orden (Plan, Consumir, Cerrar y PDF) las cantidades se ven y se escriben en la unidad de la fórmula. Nuevo PDF con el diseño de los demás documentos (logo, número en recuadro, firmas y número de página). Las tablas de la orden y de Nueva tienen el diseño del detalle de Facturas de Venta.
- **1.8** — **Cerrar orden** pasa a la barra de arriba. Al pulsarlo pide la fecha de cierre y verifica todo antes de cerrar (lista de comprobación y resumen de lo que entra al inventario).
- **1.9** — La pestaña **Asiento** usa la pieza común de los documentos: selector con los asientos de la orden (consumos y cierre), edición y **Guardar asiento** para quien puede actualizar asientos, y **Restaurar automático**.
- **1.10** — Los datos de quién creó y modificó la orden pasan a la pestaña **Información** (con su historial y los movimientos de inventario); se quita la línea del pie del modal.
