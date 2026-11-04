---
titulo: Configuración de Producción
resumen: Activa el módulo Producción en la empresa y define las bodegas por defecto, las funciones que se usan (lotes, cuarentena, consumo sin stock) y las cuentas contables de los asientos de producción.
categoria: Producción
ruta_modulo: modulos/produccion-configuracion
tipo: modulo
visibilidad: todos
etiquetas: configuracion de produccion, activar produccion, fabricacion, manufactura, planta, bodega de materia prima, bodega de producto terminado, cuarentena, exigir lote, consumir sin stock, cuentas de produccion, productos en proceso, mano de obra aplicada, costos indirectos, cif, merma, reparto de coproductos, asiento de produccion
version: 1.0
orden: 0
estado: activo
---

Es el primer paso para usar **Producción**. Mientras la empresa no guarde esta
configuración, Fórmulas, Órdenes y Reportes de producción muestran un aviso y no
permiten registrar nada. Guardarla **no cambia nada** en el resto del sistema:
ventas, compras, inventario y contabilidad siguen exactamente igual.

## Qué es y para qué sirve

Define cómo trabaja la producción en la empresa:

- **Bodegas por defecto** de insumos, de producto terminado y de cuarentena (cada orden puede elegir otras).
- **Funciones** que se encienden solo si la empresa las necesita: exigir lote en los insumos, usar cuarentena, permitir consumir sin stock.
- **Reparto del costo** cuando una fórmula tiene coproductos.
- **Cuentas contables** para generar los asientos de producción.

## Requisitos previos

- Bodegas creadas en el módulo **Bodegas**.
- Para contabilizar: un plan de cuentas con cuentas de movimiento.

## Cómo se usa

1. Elija las bodegas por defecto.
2. Encienda solo las funciones que use su planta.
3. Si quiere asientos automáticos, encienda **Generar asientos de producción** y elija las cuentas.
4. Pulse **Guardar**. Desde ese momento puede crear fórmulas y órdenes.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Bodega de insumos | No | De dónde salen las materias primas al consumir. |
| Bodega de producto terminado | No | A dónde entra lo producido. |
| Bodega de cuarentena | Si usa cuarentena | A dónde entra lo producido mientras Calidad no lo libera. |
| Exigir lote en los insumos | No | Al consumir, cada insumo con lotes debe indicar de cuál sale. Si está apagado, se toma el que vence primero. |
| Usar cuarentena | No | Lo producido entra a la bodega de cuarentena. Se libera con una **Transferencia de inventario** a la bodega de producto terminado. |
| Permitir consumir sin stock | No | Apagado por defecto. Si se enciende, un insumo puede quedar en negativo. |
| Reparto del costo entre coproductos | Sí | Valor de venta relativo (recomendado), unidades físicas o % fijo de la fórmula. |
| Generar asientos de producción | No | Enciende la contabilización automática. |
| Cuentas | Si contabiliza | Materia prima, Productos en proceso y Producto terminado son obligatorias. Mano de obra, Costos indirectos y Merma se piden cuando la orden tiene esos importes. |

## Permisos

- **Ver**: consultar la configuración.
- **Actualizar**: guardarla.

## Reglas de negocio

- Las cuentas deben ser **cuentas de movimiento** de la empresa.
- Si un producto tiene su propia **cuenta de inventario** en su ficha, el asiento usa esa en lugar de la de materia prima o producto terminado.
- Las cuentas de producción viven aquí y no en Configuración Contable: así las empresas que no producen no ven conceptos ni avisos nuevos.

## Integraciones con otros módulos

- **Bodegas** y **Transferencias de inventario** (liberar la cuarentena).
- **Asientos contables**: los asientos de producción aparecen con origen *produccion_consumo* y *produccion_cierre*.

## Errores frecuentes

- **«Para contabilizar la producción configure: …»**: encendió los asientos sin elegir las cuentas obligatorias.
- **«Para usar cuarentena elija la bodega de cuarentena»**: encendió la cuarentena sin bodega.

## Historial de cambios

- **1.0** — Versión inicial.
