---
titulo: Reporte de Pedidos
resumen: Análisis de pedidos por cliente, producto, estado, responsable de entrega y fecha, con gráfico y exportación.
categoria: Reportes
ruta_modulo: modulos/reporte_pedidos
tipo: modulo
visibilidad: todos
etiquetas: reporte de pedidos, pedidos por cliente, pedidos por producto, pedidos por estado, pedidos pendientes, pedidos procesados, exportar pedidos, cantidad pedida
version: 1.0
orden: 31
estado: activo
---

Este reporte analiza los **Pedidos** guardados en el módulo de Ventas → Pedidos:
cuántos hay, en qué estado, qué cantidad se pidió y a quién. Mismo diseño que el
**Reporte de Ventas** (filtros, tarjetas de resumen, gráfico y exportación), pero
sin valores monetarios ni IVA — Pedidos es un documento interno, no fiscal.

## Filtros combinables

| Filtro | Para qué |
|--------|----------|
| Fechas (o Mes/Año) | El periodo, sobre la fecha del pedido |
| Cliente | Uno o varios clientes (selección múltiple con chips) |
| Producto | Texto libre sobre el nombre/código del producto pedido |
| Estado | Pendiente, Procesado, Anulado o Todos |
| Responsable de Entrega | Quién trasladó/entregó el pedido |
| Buscar | Número de pedido u observaciones |

Todos se combinan entre sí.

## Formas de agrupar

- **Detallado**: cada pedido, uno por fila.
- **Por Cliente**, **Por Producto**, **Por Estado**, **Por Resp. Entrega**,
  **Por Fecha** y **Por Mes**: resumen con número de pedidos y cantidad total
  pedida en cada grupo.

## Tarjetas de resumen

Total de pedidos (con el desglose de pendientes y anulados), procesados,
cantidad total pedida y número de clientes distintos — todo recalculado según
los filtros activos.

## Exportar

Disponible en **PDF** y **Excel**, respetando los filtros y la agrupación
elegida en pantalla.

## Permisos

Requiere el permiso **ver** del submódulo. Si el usuario no tiene **acceso
total**, el reporte solo considera los pedidos que él mismo creó (igual que el
listado de Pedidos).

## Para qué se usa

Para responder preguntas como: cuántos pedidos están pendientes de procesar,
qué cliente pide más seguido, qué responsable de entrega tiene más pedidos
asignados, o cuánta cantidad de un producto se ha pedido en el mes.

## Errores frecuentes

- **No aparecen pedidos que sé que existen**: revise el filtro de Estado (por
  defecto no filtra, pero pudo quedar seleccionado de una consulta anterior) y
  las fechas — el reporte usa la fecha del pedido, no la de entrega.
- **La cantidad no cuadra con lo que veo en el pedido**: el reporte suma la
  cantidad de todas las líneas del pedido; revise si alguna línea fue
  eliminada al editar.

## Historial de cambios

- **1.0** — Versión inicial.
