---
titulo: Mesas
resumen: Mesas del local, sobre las que se abren las comandas del servicio de restaurante.
categoria: Restaurante
ruta_modulo: modulos/mesas
tipo: modulo
visibilidad: todos
etiquetas: mesas, mesa, restaurante, salon, comanda, ocupada, disponible
version: 1.0
orden: 10
estado: activo
---

Las **mesas** son los puestos del local sobre los que se abre una comanda. Es un
catálogo mínimo: nombre y poco más, pero es lo que permite saber qué está ocupado
y qué está libre.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** de la mesa (máximo 100 caracteres).
3. Guarde.

Conviene usar nombres que el personal reconozca de inmediato: `Mesa 1`,
`Terraza 3`, `Barra 2`.

## Disponible u ocupada

Una mesa con una comanda abierta está **ocupada** y no admite otra comanda: al
intentarlo, el sistema avisa de que *la mesa no está disponible*. Se libera al
cerrar o anular la comanda.

## Errores frecuentes

- **"La mesa no está disponible"**: tiene una comanda abierta. Ciérrela o
  anúlela antes de abrir otra.
- **Una mesa quedó ocupada sin nadie sentado**: hay una comanda abierta olvidada;
  búsquela en Comandas y ciérrela.

## Historial de cambios

- **1.0** — Versión inicial.
