---
titulo: Configuración de Producción
resumen: Activa el módulo Producción en la empresa y define las bodegas por defecto y las funciones que se usan (lotes, cuarentena, consumo sin stock). Las cuentas contables van en Configuración Contable → Producción.
categoria: Producción
ruta_modulo: modulos/produccion-configuracion
tipo: modulo
visibilidad: todos
etiquetas: configuracion de produccion, activar produccion, fabricacion, manufactura, planta, bodega de materia prima, bodega de producto terminado, cuarentena, exigir lote, consumir sin stock, cuentas de produccion, donde van las cuentas de produccion, productos en proceso, reparto de coproductos, asiento de produccion
version: 1.5
orden: 0
estado: activo
---

Es el primer paso para usar **Producción**. Mientras la empresa no guarde esta
configuración, Fórmulas, Órdenes y Reportes de producción muestran un aviso y no
permiten registrar nada. Guardarla **no cambia nada** en el resto del sistema:
ventas, compras e inventario siguen exactamente igual. Solo aparece, para esta
empresa, la sección **Producción** en Configuración Contable.

## Qué es y para qué sirve

Define cómo trabaja la producción en la empresa:

- **Bodegas por defecto** de insumos, de producto terminado y de cuarentena (cada orden puede elegir otras).
- **Funciones** que se encienden solo si la empresa las necesita: exigir lote en los insumos, usar cuarentena, permitir consumir sin stock.
- **Reparto del costo** cuando una fórmula tiene coproductos.

## Requisitos previos

- Bodegas creadas en el módulo **Bodegas**, o créelas desde esta misma pantalla con el botón **+** de cada selector.

## Cómo se usa

1. Elija las bodegas por defecto. Si falta alguna, pulse el **+** junto al selector: se abre la ficha de Bodegas; al guardarla se cierra sola y la nueva bodega queda elegida en ese selector (y disponible en los otros dos).
2. Encienda solo las funciones que use su planta.
3. Pulse **Guardar**. Desde ese momento puede crear fórmulas y órdenes.
4. Configure las cuentas contables en [Configuración Contable](modulos/configuracion-contable), tipo de asiento **Producción**.

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

## Dónde se configuran las cuentas contables

La tarjeta **Cuentas contables** de esta pantalla muestra el estado de las seis cuentas de Producción (configurada o no, con su código y nombre) y si la contabilización está encendida. El botón **Configurar cuentas** abre Configuración Contable directamente en **Producción**. Las obligatorias (*) son Inventario de materia prima, Productos en proceso e Inventario de producto terminado; Mano de obra, Costos indirectos y Merma anormal solo hacen falta si las órdenes las tienen.

En **Configuración Contable**, tipo de asiento **Producción**: materia prima, productos en
proceso, producto terminado, mano de obra aplicada, costos indirectos aplicados y merma
anormal. Ese tipo aparece en cuanto guarda esta configuración. Para dejar de generar asientos
de producción, apague **Producción** en *Módulos que contabilizan* (Configuración Contable).

## Permisos

- **Ver**: consultar la configuración.
- **Actualizar**: guardarla.
- El botón **+** para crear una bodega solo aparece si el usuario tiene permiso de **Crear** en el módulo **Bodegas**.

## Integraciones con otros módulos

- **Bodegas** y **Transferencias de inventario** (liberar la cuarentena).
- **Configuración Contable**: cuentas y interruptor de los asientos de producción.

## Errores frecuentes

- **«Para usar cuarentena elija la bodega de cuarentena»**: encendió la cuarentena sin bodega.
- **No veo Producción en Configuración Contable**: guarde primero esta configuración.

## Historial de cambios

- **1.0** — Versión inicial.
- **1.1** — Las cuentas contables y el interruptor de los asientos pasan a **Configuración Contable → Producción**.
- **1.2** — La pantalla ocupa todo el ancho de la ventana, como los demás módulos.
- **1.3** — Botón **+** junto a cada selector de bodega para crear la bodega sin salir de la pantalla; la nueva queda elegida.
- **1.4** — Cada función de la tarjeta **Funciones** tiene un ícono **?** que explica, con ejemplos, qué pasa al encenderla o apagarla.
- **1.5** — Tarjeta **Cuentas contables** con el estado de cada cuenta y acceso directo a Configuración Contable → Producción.
