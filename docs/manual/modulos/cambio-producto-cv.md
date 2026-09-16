---
titulo: Cambios de productos
resumen: Registra lo que un cliente devuelve y lo que recibe a cambio, en un solo documento, por unidad y NUP.
categoria: Ventas
ruta_modulo: modulos/cambio-producto-cv
tipo: modulo
visibilidad: todos
etiquetas: cambio de producto, cambios de productos, buscar cambio, buscador, filtros, filtrar cambios, buscar por producto, documento de origen, chips, garantia, reposicion, devolucion con reposicion, canje, buscar por nup, nup, serial, numero de serie, lote, buscar por factura, numero de factura, factura de consignacion, facturacion de consignaciones, buscar por consignacion, numero de consignacion, entregar desde consignacion, existencias, catalogo, diferencia a favor, saldo de consignacion, mercaderia en consignacion, inventario, asiento a costo
version: 1.4
orden: 47
estado: activo
---

Un **cambio de productos** registra, en un solo documento y para un solo
cliente, dos cosas a la vez: los productos que el cliente **devuelve** (por
ejemplo, uno que salió con falla) y los productos que **recibe a cambio**. Es el
documento de garantías, reposiciones y canjes. Se relaciona con
[Facturación de consignaciones](modulos/facturacion-cv) (de ahí salen los ítems que se
devuelven), con [Consignaciones](modulos/consignacion-venta) y
[Retornos de consignación](modulos/retornos-cv) (de una consignación puede
salir lo que se entrega a cambio) y con Inventario y Contabilidad.

## Qué es y para qué sirve

El cambio se hace **por unidad**: cada ítem tiene su lote y su **NUP** (número
único de producto o serial), así que el documento no dice "3 unidades del
producto X" sino cuál unidad exacta vuelve y cuál unidad exacta se entrega.

- **Productos que devuelve** → vuelven al inventario (entrada a la bodega de
  la que salieron). Vienen de una **factura de consignación** (documento del
  módulo *Facturación de consignaciones* en estado *facturada*; no de facturas
  de venta directas) o de un
  **cambio anterior** (lo que se entregó en un cambio se puede volver a cambiar).
- **Productos que entrega a cambio** → salen hacia el cliente. Pueden tomarse
  de **tres sitios**: de una **consignación** que el cliente ya tiene en su
  poder, de las **existencias** de una bodega (eligiendo lote y NUP) o del
  **catálogo** de productos.
- La **diferencia** entre lo entregado y lo devuelto es solo **informativa**:
  el documento no genera cobro ni cuenta por cobrar.

## Requisitos previos

- Un punto de emisión con el secuencial **Cambios de productos** configurado en
  *Empresa → Secuenciales*. Sin eso no se puede emitir el documento.
- Permisos del módulo asignados en *Configuración → Permisos de módulos*.
- Para el asiento contable: las cuentas de *Inventario* y *Costo de ventas*
  del concepto **Ventas** y, si se entrega desde consignación, la cuenta
  *Mercadería en consignación* del concepto **Consignación** (en
  *Configuración contable*).

## Cómo se usa

1. Pulse **Nuevo**. La fecha, la serie y el número se proponen solos.
2. **Busque lo que el cliente devuelve** en el buscador de la primera tabla.
   No hace falta elegir antes el cliente: escriba el **NUP**, el **lote**, el
   **número de la factura de consignación** (completo `001-001-000000123` o
   solo `123`) o el
   nombre del producto. El resultado se agrupa por documento y muestra **cada
   ítem por separado**; pulse el ítem que se devuelve (o **Agregar todos**).
   El cliente del cambio queda fijado con el de ese documento.
   Si prefiere empezar por el cliente, elíjalo en el campo **Cliente**: desde
   ese momento el buscador solo muestra los documentos de ese cliente, y con
   el campo vacío lista todo lo que tiene pendiente.
3. Ajuste la **cantidad** a devolver si es menor que el saldo (nunca puede
   superarlo).
4. **Busque lo que se entrega a cambio** en el buscador de la segunda tabla.
   Los resultados salen en tres grupos:
   - **Consignación**: ítems de las consignaciones *entregadas* que el
     cliente todavía tiene en su poder. Se buscan por **número de
     consignación**, NUP, lote o producto. Bodega, lote y NUP son los de la
     consignación y no se editan; el precio y el IVA se proponen desde la
     consignación y se pueden cambiar.
   - **Existencias en bodega**: unidades con stock, una fila por bodega, lote
     y NUP. Al agregarla, la bodega, el lote y el NUP quedan precargados
     (editables).
   - **Catálogo**: cualquier producto, aunque no tenga stock registrado. Se
     elige la bodega y, si aplica, se escriben lote y NUP.
5. Revise el resumen (total devuelto, total entregado, diferencia) y pulse
   **Guardar**. El documento se emite, mueve el inventario y genera el asiento.

Al abrir un cambio ya guardado, cada línea muestra de dónde salió: *Fact. consig.
001-001-000000123*, *Cambio 001-001-000000004*, *Consignación 001-001-000000012*
o *Bodega*.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del cambio: fecha, número
(serie y secuencial), cliente, RUC o cédula, motivo y diferencia. Además busca
en las observaciones, el responsable de traslado, el usuario que lo registró,
los **productos devueltos y entregados** (código, nombre, lote y NUP) y el
número de los **documentos de origen** de las líneas (factura de consignación,
cambio anterior o consignación). La columna **Estado** no entra en la búsqueda
libre: para filtrar por ella use la ventana de filtros. Puede escribir varias
palabras en cualquier orden y no importan mayúsculas ni tildes. Mientras busca,
aparece un **círculo girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Cambio** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha del cambio (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, emitida, anulada), serie, Nº cambio, secuencial, documento de origen, con o sin asiento contable, responsable de traslado, usuario que registró |
| Valores | Diferencia, subtotal devuelto y subtotal entregado (cada uno con mínimo y máximo) |
| Cliente | Cliente, RUC / cédula, motivo, observaciones |

Los selectores *Serie*, *Responsable de traslado* y *Usuario que registró*
listan solo lo que la empresa ya usó en sus cambios.

**Pestaña Detalles** (lo que hay dentro del cambio). Es un único cuadro,
**Buscar libremente dentro de los cambios**: escriba un producto, un código, un
lote, un NUP, una fecha de caducidad, una bodega o el número del documento de
origen, y aparece la lista de **cada línea (devolución o entrega) que coincide**
con el cambio al que pertenece (número, fecha, cliente y estado). Un clic en la
fila deja el listado mostrando solo ese cambio; el ícono de la derecha lo abre
directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fecha | Sí | Fecha del cambio. Con numeración por periodo, cambiarla recalcula el número. |
| Serie | Sí | Punto de emisión con el secuencial *Cambios de productos* configurado. |
| Secuencial | Automático | Vista previa; el número definitivo lo asigna el sistema al guardar. |
| Cliente | Sí | Se fija solo con el primer ítem agregado (factura de consignación, cambio o consignación) o se elige a mano. Backspace en el campo lo limpia junto con las líneas que dependen de él. |
| Motivo / Observaciones | No | Texto libre. |
| Productos que devuelve | Sí (al menos uno) | Ítems de facturas de consignación (estado *facturada*) o de cambios anteriores con saldo pendiente. |
| Productos que entrega a cambio | No | Ítems desde consignación, existencias o catálogo. |
| Estado | Solo al editar | Borrador, Emitida o Anulada. |

## Permisos

- **Ver**: abrir el módulo y los documentos.
- **Crear**: emitir cambios nuevos.
- **Actualizar**: editar borradores, cambiar el estado. Basta este permiso para
  guardar un documento ya existente.
- **Eliminar**: eliminar el documento (revierte inventario y asiento).
- **Acceso total**: ve los cambios de toda la empresa. Sin él, el usuario solo
  ve y gestiona los que creó. El superadministrador siempre ve todo.

## Reglas de negocio

- **Saldo de lo devuelto**: cada ítem de factura de consignación o de cambio anterior tiene un
  saldo = cantidad original − lo ya devuelto en cambios emitidos. No se puede
  devolver más que ese saldo, y el sistema lo revalida al guardar y al volver a
  emitir.
- **Un solo cliente por documento**: todas las devoluciones y las entregas
  desde consignación deben ser del mismo cliente. Si se intenta agregar un
  ítem de otro cliente, el sistema avisa y no lo agrega.
- **Entrega desde consignación**: consume el saldo de esa línea de
  consignación (consignado − retornado − facturado − ya entregado en otros
  cambios) y no se puede entregar más que ese saldo. Como la mercadería ya
  salió de bodega con la consignación, esta entrega **no vuelve a mover el
  stock**; solo registra que la unidad pasó al cliente.
- **Entrega desde bodega o catálogo**: sale de la bodega elegida (si el
  producto es inventariable y el control de inventario de facturación está
  activo).
- **Estados**: solo *Emitida* mantiene los movimientos de inventario y el
  asiento. Pasar a *Borrador* o *Anulada* los reversa y libera los saldos;
  volver a *Emitida* revalida saldos y los vuelve a aplicar. Solo se editan
  documentos en *Borrador*.
- **Numeración**: el número que se ve al abrir es una vista previa; el
  definitivo se asigna al guardar y no se puede repetir en la misma serie.
- **Diferencia**: se calcula a precio de venta (con IVA) y es informativa.

## Integraciones con otros módulos

- **Inventario**: las devoluciones generan una **entrada** y las entregas
  desde bodega una **salida**, ambas con referencia *Cambio de producto*
  (visibles en el kardex y en el reporte de inventario). Las entregas desde
  consignación no mueven stock.
- **Contabilidad**: asiento **a costo** (costo promedio del producto en su
  bodega): *Inventario* contra *Costo de ventas* por el neto entre lo devuelto
  y lo entregado desde bodega; lo entregado desde consignación sale de
  *Mercadería en consignación* contra *Costo de ventas*. Se puede revisar y
  completar en la pestaña **Asiento contable** antes de guardar.
- **Consignaciones / Retornos / Facturación de consignaciones / Reporte de
  inventarios**: lo entregado a cambio desde una consignación **descuenta el
  saldo** de esa línea en todos ellos (ya no se ofrece para retornar ni para
  facturar, aparece como movimiento *Cambio de producto* en el resumen de la
  consignación y como columna *A cambio* en el reporte). Anular o pasar a
  borrador el cambio libera ese saldo.
- **PDF, Excel y correo** desde la barra de acciones del documento.

## Errores frecuentes

- **"Indique un NUP, un número de documento o un producto, o seleccione el
  cliente"**: sin cliente el buscador necesita al menos dos caracteres.
- **"El documento … pertenece a otro cliente"**: el cambio ya tiene un cliente
  distinto. Quite el cliente actual (Backspace en el campo Cliente) o registre
  otro cambio.
- **No aparece la factura**: solo se ofrecen **facturas de consignación** en
  estado **facturada** (las de venta directa no salen aquí), ítems
  que sean bienes (no servicios) y con saldo pendiente de devolver.
- **No aparece la consignación**: debe estar en estado **Entregada**, ser del
  mismo cliente del cambio y tener saldo (no retornado, no facturado, no
  entregado en otro cambio).
- **"Secuencial no configurado"**: configure *Cambios de productos* en
  *Empresa → Secuenciales* para el punto de emisión.
- **El asiento tiene una cuenta vacía**: falta configurar la cuenta en
  *Configuración contable* (Inventario y Costo de ventas del concepto Ventas,
  Mercadería en consignación del concepto Consignación). Se puede completar en
  la pestaña Asiento contable.

## Historial de cambios

- **1.4** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas del cambio y además en los
  productos devueltos y entregados (código, nombre, lote, NUP), los documentos
  de origen, el responsable y el usuario; la columna Estado ya no entra en la
  búsqueda libre. Los filtros pasan a una **ventana propia** (botón del embudo,
  se aplican con *Aplicar*) con dos pestañas: **Cambio** (filtros por campo, con
  criterios nuevos: fecha, Nº cambio, documento de origen, con/sin asiento,
  responsable y usuario como listas, diferencia, subtotales devuelto y
  entregado, RUC y observaciones) y **Detalles**, un cuadro de **búsqueda libre
  dentro de los cambios** que dice a qué cambio pertenece cada línea. Nueva
  sección *Buscar y filtrar el listado*.
- **1.3** — Lo que se devuelve se busca en las **facturas de consignación**
  (módulo Facturación de consignaciones, estado *facturada*), ya no en las
  facturas de venta directas. Lote, NUP, bodega, precio e IVA se copian de
  esa factura.

- **1.2** — Lo entregado a cambio desde una consignación descuenta el saldo de
  esa línea en Retornos, Facturación de consignaciones, el resumen de la
  consignación y el Reporte de inventarios.
- **1.1** — Búsqueda de lo que se devuelve por **NUP**, lote y número de
  factura o cambio, sin necesidad de fijar antes el cliente (el ítem lo fija);
  resultados agrupados por documento con cada ítem por separado y **Agregar
  todos**. Lo que se entrega a cambio se busca por **número de consignación**
  (ítems que el cliente tiene en consignación), por **existencias** de bodega
  (lote / NUP) o por catálogo; las líneas de entrega guardan lote y NUP y su
  origen. Asiento a costo con cuenta *Mercadería en consignación* para lo
  entregado desde consignación.
- **1.0** — Versión inicial: devoluciones desde factura o cambio anterior,
  entregas desde catálogo, estados, inventario, asiento a costo, PDF, Excel y
  correo.
