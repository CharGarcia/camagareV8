---
titulo: Comandas
resumen: Pedido de una mesa: lo que consume el cliente antes de cobrarle.
categoria: Restaurante
ruta_modulo: modulos/comandas
tipo: modulo
visibilidad: todos
etiquetas: comandas, comanda, pedido, mesa, restaurante, cocina, anular, cerrar cuenta
version: 1.0
orden: 20
estado: activo
---

Una **comanda** es el pedido de una mesa: lo que el cliente va consumiendo,
mientras lo consume. Se abre al sentarse, se le van añadiendo productos y se
cierra al cobrar.

## El recorrido

1. Elija una **mesa disponible** y abra la comanda.
2. Añada los ítems: productos del catálogo o platos del menú.
3. Envíe a cocina lo que corresponda.
4. Al terminar, cierre la comanda y cobre.

## Solo se modifica si está abierta

Una comanda cerrada **no admite cambios**: el sistema avisa de que *no está
abierta*. Si hay que corregir algo después de cerrarla, la corrección va sobre el
documento de venta, no sobre la comanda.

## Anular con motivo

Anular una comanda **que ya tiene ítems** exige indicar un **motivo**. No es
burocracia: es lo que permite después distinguir una mesa que se levantó sin
consumir de una anulación irregular.

Una comanda vacía se anula sin más.

## Validaciones

| Regla | Detalle |
|-------|---------|
| Mesa disponible | No se abre una comanda sobre una mesa ocupada |
| Comanda abierta | Solo se modifica mientras está abierta |
| Ítem | Hay que seleccionar un producto o un ítem del menú |
| Cantidad | Mayor a cero |
| Motivo de anulación | Obligatorio si la comanda ya tiene ítems |

## Errores frecuentes

- **"La mesa no está disponible"**: ya tiene una comanda abierta.
- **"La comanda no está abierta; no se puede modificar"**: ya fue cerrada.
- **"Indica un motivo para anularla"**: la comanda tiene consumos registrados.

## Historial de cambios

- **1.0** — Versión inicial.
