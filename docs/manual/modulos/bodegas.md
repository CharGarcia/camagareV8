---
titulo: Bodegas
resumen: Lugares donde se almacena la mercadería; cada movimiento de inventario ocurre en una bodega.
categoria: Inventario
ruta_modulo: modulos/bodegas
tipo: modulo
visibilidad: todos
etiquetas: bodegas, bodega, almacen, deposito, sucursal, ubicacion, stock por bodega, responsable, establecimiento de la bodega, local, en que establecimiento esta la bodega, acceso por usuario, quitar acceso a una bodega, bodegas asignadas, no veo una bodega
version: 1.2
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
4. Indique el **establecimiento** donde está físicamente la bodega (opcional).
5. Guarde.

El nombre y el responsable son obligatorios.

## Establecimiento de la bodega

Indica en cuál de los locales de la empresa (Empresa → Establecimientos) está la
bodega. Sirve sobre todo para las **Transferencias de Inventario**: cuando la
bodega de origen y la de destino pertenecen a establecimientos distintos, el
traslado se marca como *entre establecimientos* y habilita la generación de la
**guía de remisión**.

Si se deja en *Sin asignar*, la bodega sigue funcionando igual en todo lo demás;
solo que sus transferencias nunca se considerarán entre establecimientos.

## Acceso por usuario

Se puede limitar qué usuarios trabajan con cada bodega. Es útil cuando cada
sucursal debe ver solo su propia mercadería.

Por defecto todos los usuarios ven todas las bodegas de la empresa: aquí se
quita el acceso a las que no correspondan. El administrador (nivel 2) y el
superadministrador (nivel 3) las ven todas de todos modos.

Lo que se decide aquí vale también para la **información**, no solo para los
selectores: el *Reporte de inventarios* deja fuera de sus listados, indicadores
y exportaciones las bodegas sin acceso, aunque se deje el filtro en *Todas*.

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

- **1.2** — El acceso por usuario ahora también filtra la **información** del
  *Reporte de inventarios* (existencias, movimientos, valorización,
  consignaciones y auditoría), no solo los selectores de bodega.
- **1.1** — Se agrega el campo **Establecimiento**: indica en qué local está la
  bodega y permite que las Transferencias de Inventario detecten los traslados
  entre establecimientos.
- **1.0** — Versión inicial.
