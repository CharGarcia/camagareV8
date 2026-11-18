---
titulo: Bodegas
resumen: Lugares donde se almacena la mercadería; cada movimiento de inventario ocurre en una bodega.
categoria: Inventario
ruta_modulo: modulos/bodegas
tipo: modulo
visibilidad: todos
etiquetas: bodegas, bodega, almacen, deposito, sucursal, ubicacion, stock por bodega, saldo por bodega, existencias por bodega, valor del inventario por bodega, cuanto hay en cada bodega, saldo al costo, saldo a precio de venta, establecimiento de la bodega, local, en que establecimiento esta la bodega, acceso por usuario, quitar acceso a una bodega, bodegas asignadas, no veo una bodega
version: 1.4
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

1. Pulse **Nueva**.
2. Escriba el **nombre** (máximo 100 caracteres).
3. Indique el **establecimiento** donde está físicamente la bodega (opcional).
4. Deje encendido el interruptor **Estado** (*Activo*); apáguelo para dejar la
   bodega inactiva.
5. Pulse **Guardar**.

El nombre es obligatorio. Los avisos (datos que faltan, errores, confirmación
de guardado o de eliminación) se muestran en una ventana emergente.

Mientras se guarda aparece el aviso **Guardando bodega...** (o **Actualizando
bodega...** si ya existía); espere a que termine. Al guardar, la ficha **no se
cierra**: queda abierta (una bodega nueva pasa a modo edición) y un siguiente
**Guardar** actualiza esa misma bodega, no crea otra. Ciérrela con la **X** o
**Cerrar** cuando termine.

## Saldo de cada bodega

El listado muestra, para cada bodega, cuánto inventario tiene hoy:

| Columna | Qué indica |
|---|---|
| **Productos** | Cuántos productos tienen saldo (distinto de cero) en la bodega. |
| **Unidades** | Suma de las cantidades en existencia de todos esos productos. |
| **Saldo al costo** | Existencias × último costo registrado en el kardex de cada producto. Si un producto no tiene costo en el kardex, se usa el costo de su ficha. Es el mismo criterio de la pestaña *Existencias* del Reporte de Inventarios. |
| **Saldo a precio de venta** | Existencias × precio de venta de la ficha del producto (**sin IVA**). Sirve para estimar cuánto se vendería la mercadería de esa bodega. |

Al pie de la tabla aparece la fila **Total** con la suma de **todas** las
bodegas que cumplen la búsqueda (no solo las de la página visible). Al abrir
una bodega, la ficha muestra el mismo resumen en el recuadro **Saldo actual de
la bodega**.

Detalles a tener en cuenta:

- El saldo se calcula con los movimientos reales del kardex, en el **ambiente
  SRI actual de la empresa** (pruebas o producción), igual que el stock que ven
  Facturas, Transferencias e Inventario.
- Las **unidades** suman cantidades de productos distintos (cajas, kilos,
  unidades…): sirven como referencia de volumen, no como una medida exacta.
- Un saldo **negativo** (en rojo) significa que salió más de lo que entró en
  esa bodega; revise el kardex del producto.
- Lo que está **consignado** a clientes no se cuenta: ya salió de la bodega.

## Buscar y filtrar el listado

El buscador funciona igual que en Proveedores y demás listados: lo que se
escribe busca en el nombre de la bodega y en su establecimiento. El botón del
**embudo** abre la ventana de filtros:

- **Bodega**: nombre, establecimiento y estado (activo / inactivo).
- **Saldo**: con o sin existencias, y rangos de unidades, saldo al costo y saldo
  a precio de venta (por ejemplo, las bodegas con más de 1.000 al costo).
- **Registro**: fecha de creación y usuario que la registró.

Los filtros activos se ven como etiquetas junto al buscador. Se puede ordenar
por cualquier columna con clic en el encabezado, y por varias a la vez con
**Shift + clic**. El PDF y el Excel salen con el mismo filtro, el mismo orden,
las columnas visibles y la fila de totales.

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
- **El saldo de la bodega no coincide con el Reporte de Inventarios**: el
  reporte muestra solo documentos de producción y puede sumar lo consignado en la
  columna *Stock Total*; aquí se ve el ambiente actual de la empresa y sin
  consignaciones.
- **El saldo a precio de venta sale en cero**: los productos no tienen precio de
  venta en su ficha.

## Historial de cambios

- **1.4** — El listado muestra el **saldo de cada bodega**: productos con existencias, unidades, saldo al costo y saldo a precio de venta, con fila de totales; el mismo resumen aparece en la ficha. Nuevo buscador con **filtros** (como Proveedores), orden por varias columnas y PDF/Excel con los saldos. Los mensajes se muestran en ventanas emergentes (también la confirmación de eliminar).
- **1.3** — El **Estado** de la ficha pasa a ser un interruptor (*Activo*), igual que en Productos, Clientes y demás catálogos; la estrella de favorito sigue funcionando. Al guardar, la ficha ya no se cierra y muestra el aviso *Guardando / Actualizando bodega...* mientras el servidor responde.
- **1.2** — El acceso por usuario ahora también filtra la **información** del
  *Reporte de inventarios* (existencias, movimientos, valorización,
  consignaciones y auditoría), no solo los selectores de bodega.
- **1.1** — Se agrega el campo **Establecimiento**: indica en qué local está la
  bodega y permite que las Transferencias de Inventario detecten los traslados
  entre establecimientos.
- **1.0** — Versión inicial.
