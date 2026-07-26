---
titulo: Pantalla de cocina (KDS)
resumen: Pantalla donde la cocina ve los pedidos de las comandas y los marca como listos.
categoria: Restaurante
ruta_modulo: modulos/kds
tipo: modulo
visibilidad: todos
etiquetas: kds, cocina, pantalla de cocina, pedidos, comandas, preparacion, listo, despacho
version: 1.0
orden: 40
estado: activo
---

El **KDS** (pantalla de cocina) muestra en tiempo real lo que se ha pedido desde
las comandas, para que la cocina lo prepare sin depender de tickets en papel.

## Cómo funciona

1. El salón toma la comanda y envía los ítems a cocina.
2. Aparecen en la pantalla, en orden de llegada.
3. La cocina marca cada uno cuando está **listo**.
4. El salón ve que puede servirlo.

## Para qué sirve de verdad

Para dos cosas: que no se pierda ningún pedido, y que se vea **cuánto lleva
esperando cada uno**. Un plato que lleva demasiado tiempo en pantalla es un
cliente que se está impacientando, y eso se detecta antes de que reclame.

## Recomendaciones de uso

- Una pantalla por área de preparación (cocina fría, parrilla, bar) si el volumen
  lo justifica.
- Marcar como listo **cuando de verdad lo está**: una pantalla que no refleja la
  realidad deja de usarse en una semana.

## Errores frecuentes

- **No llegan pedidos**: revise que la comanda se haya enviado a cocina y que la
  pantalla esté configurada para esa área.
- **Quedan pedidos antiguos en pantalla**: no se marcaron como listos.

## Historial de cambios

- **1.0** — Versión inicial.
