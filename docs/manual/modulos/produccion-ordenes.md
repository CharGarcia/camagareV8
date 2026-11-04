---
titulo: Órdenes de producción
resumen: Registra la fabricación: consume los insumos de la bodega, da entrada a lo producido con su costo real y genera los asientos. Incluye la Producción rápida para la producción diaria.
categoria: Producción
ruta_modulo: modulos/produccion-ordenes
tipo: modulo
visibilidad: todos
etiquetas: orden de produccion, orden de fabricacion, op, produccion rapida, fabricar, producir, consumir materia prima, consumo de insumos, consumo por peso, bobina, pesar, registro de lote, batch record, lote de produccion, caducidad, costo de produccion, costo real, costo unitario, merma anormal, coproductos, subproductos, cerrar orden, anular orden, trazabilidad de lotes, retiro de mercado, productos en proceso, asiento de produccion
version: 1.0
orden: 2
estado: activo
---

La **orden de producción** registra lo que se fabrica: qué insumos salieron de la
bodega, qué se obtuvo y cuánto costó. Usa el mismo inventario y kardex que el
resto del sistema, con movimientos propios (*Consumo de producción* y *Entrega
de producción*).

## Qué es y para qué sirve

Hay dos formas de producir:

- **Producción rápida**: un solo paso. Elige la fórmula y la cantidad, y el sistema consume los insumos (el lote que vence primero) y da entrada a lo producido. Ideal para la producción diaria (panadería, embotelladora).
- **Orden de producción**: por etapas. Se planifica, se registran los consumos (parciales, por peso, con lote, insumos fuera de fórmula) y se cierra con lo que realmente se obtuvo.

## Requisitos previos

- La **Configuración de Producción** guardada.
- Una **fórmula vigente** del producto.
- Stock de los insumos en la bodega de insumos (salvo que la configuración permita consumir sin stock).

## Cómo se usa

**Producción rápida**

1. Pulse **Producción rápida**, elija la fórmula y la cantidad.
2. Revise el cuadro: muestra lo que se va a consumir, el stock y los faltantes.
3. Ajuste lo realmente producido y la merma anormal si hace falta.
4. Pulse **Producir**.

**Orden de producción**

1. Pulse **Nueva orden**, elija la fórmula, la cantidad, las bodegas, el lote y la caducidad. Pulse **Crear orden**: queda *Planificada*.
2. Abra la orden, pestaña **Consumir**: indique lo que salió de cada insumo.
   - Use **Por peso** para consumir por diferencia de peso (peso inicial − peso final), por ejemplo una bobina o un saco.
   - Elija el **lote** o deje *Automático* (el que vence primero).
   - Puede agregar un **insumo fuera de fórmula**.
   - Puede registrar varios consumos en distintos momentos.
3. Pestaña **Cerrar**: indique lo obtenido de cada salida (principal, coproductos, subproductos, desperdicio), la merma anormal y la mano de obra y costos indirectos.
   - Marque **Consumir lo pendiente según la fórmula** si no registró todos los consumos.
   - Pulse **Cerrar orden**.
4. Use el botón **PDF** para imprimir la orden / registro de lote.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fórmula vigente | Sí | La receta con la que se fabrica. |
| Cantidad a producir | Sí | Del producto principal. La fórmula se escala a esta cantidad. |
| Bodega de insumos / de destino | Sí | De dónde salen los insumos y a dónde entra lo producido (cuarentena si está activa). |
| Lote | No | Lote de lo producido. Si se deja vacío, es el número de la orden. |
| Caducidad | No | Si la fórmula tiene vida útil, se sugiere sola. |
| Merma anormal | No | Unidades del producto principal perdidas fuera de lo normal: su costo va a gasto. |

## Permisos

- **Crear**: nueva orden y producción rápida.
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
- **Contabilidad**, si está encendida en la configuración:
  - cada consumo genera **Productos en proceso contra Materia prima**;
  - el cierre genera **Producto terminado (+ Merma) contra Productos en proceso, Mano de obra aplicada y Costos indirectos aplicados**.
  - Si falta una cuenta, la orden se guarda igual, muestra el aviso y ofrece **Generar asiento**.
- **Trazabilidad**: la pestaña *Trazabilidad* muestra qué lotes de insumo se usaron y qué lotes salieron.

## Errores frecuentes

- **«Stock insuficiente de …»**: falta el insumo en la bodega de insumos. Registre la compra o la transferencia.
- **«… maneja lotes: indique de qué lote sale»**: la configuración o la fórmula exige lote para ese insumo.
- **«No se puede anular: … ya se vendió o se usó»**: reverse primero los documentos que consumieron lo producido.
- **«Asiento pendiente: …»**: complete las cuentas en la Configuración de Producción y pulse **Generar asiento**.

## Historial de cambios

- **1.0** — Versión inicial.
