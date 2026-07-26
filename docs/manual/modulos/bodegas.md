---
titulo: Bodegas
resumen: Lugares donde se almacena la mercadería; cada movimiento de inventario ocurre en una bodega.
categoria: Inventario
ruta_modulo: modulos/bodegas
tipo: modulo
visibilidad: todos
etiquetas: bodegas, bodega, almacen, deposito, sucursal, ubicacion, stock por bodega, responsable
version: 1.0
orden: 30
estado: activo
---

Una **bodega** es un lugar donde se guarda mercadería. El stock nunca es "de la
empresa" a secas: siempre está en una bodega concreta, y todo movimiento de
inventario indica en cuál ocurre.

Con una sola sucursal basta una bodega. Cuando hay varios locales, vehículos de
reparto o un almacén separado del punto de venta, conviene una por cada uno: es
la única forma de saber dónde está realmente la mercadería.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** (máximo 100 caracteres).
3. Elija el **usuario responsable**.
4. Guarde.

Ambos campos son obligatorios.

## Acceso por usuario

Se puede limitar qué usuarios trabajan con cada bodega. Es útil cuando cada
sucursal debe ver solo su propia mercadería.

Si alguien reporta que no ve una bodega al procesar una entrada o al facturar,
revise esta configuración antes que sus permisos del módulo.

## Eliminar

La eliminación es **lógica**. Tenga en cuenta que una bodega con movimientos en
el kardex no debería eliminarse: perdería la referencia de dónde ocurrieron esos
movimientos. Si ya no se usa, es preferible dejarla sin stock y no operar con ella.

## Errores frecuentes

- **No aparece al procesar una entrada**: puede que el usuario no tenga acceso a
  esa bodega.
- **El stock está repartido y no cuadra**: consulte el stock por bodega en
  Inventario; lo más común es haber procesado una entrada en la bodega equivocada.

## Historial de cambios

- **1.0** — Versión inicial.
