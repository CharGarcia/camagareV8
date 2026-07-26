---
titulo: Reporte de restaurante
resumen: Consumo por mesa, platos más pedidos y anulaciones del servicio.
categoria: Reportes
ruta_modulo: modulos/reporte-restaurante
tipo: modulo
visibilidad: todos
etiquetas: reporte restaurante, comandas, mesas, platos mas vendidos, anulaciones, consumo, rotacion de mesas
version: 1.0
orden: 70
estado: activo
---

Este reporte analiza el servicio de restaurante a partir de las comandas: qué se
consumió, en qué mesas y qué se anuló.

## Qué muestra

- **Consumo por mesa**: cuánto se facturó en cada una.
- **Platos más pedidos**: qué sale de la carta y qué no.
- **Anulaciones**: comandas anuladas con su motivo.

## Las anulaciones son el dato de control

Como anular una comanda con consumos exige indicar un motivo, este reporte
permite revisar esos motivos juntos. Un patrón de anulaciones concentrado en un
turno o un usuario concreto es algo que conviene mirar de cerca.

## Platos que no rotan

El listado de lo menos pedido es tan útil como el de lo más pedido: son los
platos que ocupan carta e inventario sin venderse.

## Filtros

Por rango de fechas, mesa y usuario.

## Errores frecuentes

- **Faltan consumos del día**: hay comandas todavía abiertas; el reporte cuenta
  las cerradas.
- **Un plato no aparece**: no se pidió en el periodo, o se registró como producto
  suelto en lugar de ítem del menú.

## Historial de cambios

- **1.0** — Versión inicial.
