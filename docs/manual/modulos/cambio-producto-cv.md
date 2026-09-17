---
titulo: Cambios de productos
resumen: Registra lo que un cliente devuelve y lo que recibe a cambio, en un solo documento, por unidad y NUP.
categoria: Ventas
ruta_modulo: modulos/cambio-producto-cv
tipo: modulo
visibilidad: todos
etiquetas: cambio de producto, cambios de productos, listado de cambios, producto que entra, producto que sale, entra y sale, buscar cambio, buscador, filtros, filtrar cambios, buscar por producto, documento de origen, chips, garantia, reposicion, devolucion con reposicion, canje, buscar por nup, nup, serial, numero de serie, lote, buscar por factura, numero de factura, factura de venta, numero de factura de venta, factura de consignacion, facturacion de consignaciones, buscar por consignacion, numero de consignacion, entregar desde consignacion, existencias, catalogo, bodega, bodega de origen, diferencia a favor, saldo de consignacion, mercaderia en consignacion, inventario, asiento a costo, pdf del cambio, exportar excel, registro en facturacion de consignaciones, facturado por cambio, reposicion facturada, secuencial facturacion consignaciones, sin factura, fecha de emision, fecha del cambio, cambios migrados, nup en el listado, columna nup
version: 1.13
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
  En pantalla y en el PDF se identifican por el **número de la factura de
  venta** que generó esa factura de consignación, que es el comprobante que
  tiene el cliente.
- **Productos que entrega a cambio** → salen hacia el cliente. Pueden tomarse
  de **tres sitios**: de una **consignación** que el cliente ya tiene en su
  poder, de las **existencias** de una bodega (eligiendo lote y NUP) o del
  **catálogo** de productos.
- La **diferencia** entre lo entregado y lo devuelto es solo **informativa**:
  el documento no genera cobro ni cuenta por cobrar. No aparece en el
  formulario, el PDF, el Excel ni el listado; solo se puede filtrar por ella
  desde la ventana de filtros del listado.

## Requisitos previos

- Un punto de emisión con el secuencial **Cambios de productos** configurado en
  *Empresa → Secuenciales*. Sin eso no se puede emitir el documento.
- Si el cambio **entrega desde una consignación**, ese mismo punto de emisión
  necesita también el secuencial **Facturación consignaciones ventas**: lo
  entregado queda registrado en *Facturación de consignaciones* con un número de
  esa serie (ver *Registro en Facturación de consignaciones*).
- Permisos del módulo asignados en *Configuración → Permisos de módulos*.
- Para el asiento contable: las cuentas de *Inventario* y *Costo de ventas*
  del concepto **Ventas** y, si se entrega desde consignación, la cuenta
  *Mercadería en consignación* del concepto **Consignación** (en
  *Configuración contable*).

## Cómo se usa

1. Pulse **Nuevo**. La fecha, la serie y el número se proponen solos.
2. **Busque lo que el cliente devuelve** en el buscador de la primera tabla.
   No hace falta elegir antes el cliente: escriba el **NUP**, el **lote**, el
   **número de la factura de venta** (completo `001-001-000000123` o solo
   `123`; también sirve el de la factura de consignación) o el
   nombre del producto. El resultado se agrupa por documento y muestra **cada
   ítem por separado**; pulse el ítem que se devuelve (o **Agregar todos**).
   El cliente del cambio queda fijado con el de ese documento.
   Si prefiere empezar por el cliente, elíjalo en el campo **Cliente**: desde
   ese momento el buscador solo muestra los documentos de ese cliente, y con
   el campo vacío lista todo lo que tiene pendiente.
3. Ajuste la **cantidad** a devolver si es menor que el saldo pendiente: se
   propone el saldo y nunca puede superarlo (el máximo aparece al pasar el
   mouse sobre la cantidad). La columna **Bodega** indica de qué bodega salió
   la unidad: a esa bodega vuelve a entrar.
4. **Busque lo que se entrega a cambio** en el buscador de la segunda tabla.
   Los resultados salen en tres grupos:
   - **Consignación**: ítems de las consignaciones *entregadas* que el
     cliente todavía tiene en su poder. Se buscan por **número de
     consignación**, NUP, lote o producto. Bodega, lote y NUP son los de la
     consignación y no se editan.
   - **Existencias en bodega**: unidades con stock, una fila por bodega, lote
     y NUP. Al agregarla, la bodega, el lote y el NUP quedan precargados
     (editables).
   - **Catálogo**: cualquier producto, aunque no tenga stock registrado. Se
     elige la bodega y, si aplica, se escriben lote y NUP.
5. Pulse **Guardar**. El documento se emite, mueve el inventario y genera el
   asiento.

Las tablas del formulario **no muestran precios, IVA ni totales**, solo
unidades. Las dos tablas llevan *Origen, Producto, Bodega, Lote, NUP y
Cantidad*.

La columna **Origen** del formulario (y del PDF y el Excel del cambio) dice de
dónde viene cada línea:

- **Lo que se devuelve**: *Factura 001-001-000000123*, el número de la **factura
  de venta afectada**. Si la unidad llegó en un cambio anterior, se muestra
  igual la factura de venta de la que vino (el número de ese cambio aparece al
  pasar el mouse); solo si no se encuentra la factura se ve *Cambio …*. Los
  cambios registrados antes del 16-09-2026, cuando lo devuelto se tomaba de la
  factura de venta directa, también muestran esa factura, y los **migrados del
  sistema anterior** muestran la que ese sistema guardaba (con su NUP cuando no
  hay duda de cuál unidad es). Dice *Sin factura* cuando no hay factura de
  venta que mostrar: en un cambio migrado cuya factura no está en el sistema, o
  cuando la factura de consignación de la unidad **no tiene factura de venta
  enlazada** (pasa con algunas migradas). En el buscador, esa factura de
  consignación aparece como *Sin factura · F. consig. …* con su número propio.
- **Lo que se entrega**: *Consignación 001-001-000000012*, el número de la
  consignación de la que se tomó la unidad; o *Existencias*, *Catálogo* o
  *Bodega* si sale de bodega.

## Registro en Facturación de consignaciones

Cuando el cliente se lleva a cambio una unidad que tenía **en consignación**,
esa unidad pasa a estar vendida: reemplaza a la que devolvió. Por eso, al
**emitir** el cambio, el sistema crea automáticamente un documento en
[Facturación de consignaciones](modulos/facturacion-cv), en estado
**Facturada**, con la **factura de venta** de la unidad devuelta.

- **No se crea una factura de venta nueva** y la factura original **no se
  modifica**. El registro tampoco mueve inventario ni genera asiento: eso ya lo
  hace el cambio.
- Solo lleva lo entregado **desde consignación**. Lo que sale de bodega o del
  catálogo no se registra ahí.
- Cada unidad que sale va con la factura de la unidad que entra **en la misma
  posición** (la primera con la primera, la segunda con la segunda…, igual que
  en el listado); las que sobran van con la factura de la última que entra. Se
  crea **un registro por factura de venta**. Si lo devuelto viene de un cambio
  anterior, se usa la factura de venta de origen de esa unidad.
- El registro toma **su propio número** de la serie *Facturación consignaciones
  ventas* del punto de emisión del cambio, y en sus observaciones dice de qué
  cambio viene.
- En la consignación, esa unidad cuenta como **facturada** (con el número de la
  factura de venta), no como "entregada a cambio", y se descuenta una sola vez
  del saldo.
- En Facturación de consignaciones se ve con la etiqueta **Cambio** y es de
  **solo lectura**: no se edita, duplica ni elimina desde allí.
- Si el cambio pasa a **Borrador**, se **anula** o se **elimina**, su registro
  queda **Anulado** y la unidad vuelve al saldo de la consignación. Si se vuelve a
  **emitir**, se crea un registro nuevo con otro número.

## El listado: lo que entra y lo que sale

La pantalla principal no muestra un cambio por fila sino **los productos**. La
primera columna, con encabezado **celeste**, es la **Fecha** de emisión del
cambio; luego, a la **izquierda**, con encabezados en **rojo**, el producto que
**entra** (lo que el cliente devuelve) y a la **derecha**, con encabezados en
**verde**, el producto que **sale** (lo que recibe a cambio).

| Lado | Columnas |
|------|----------|
| Fecha (celeste) | Fecha de emisión del cambio |
| Entra (rojo) | Cantidad, Producto (con el código debajo), Lote, NUP (el de la unidad en la facturación de consignación), Bodega a la que entra, Factura (número de la factura de venta de la que vino el cambio: la de la unidad que entra, también si llegó en un cambio anterior; en las filas que solo tienen producto que sale, en gris, la factura del cambio; *Sin factura* si no hay factura de venta que mostrar) |
| Sale (verde) | Cantidad, Producto (con el código debajo), Lote, NUP, Bodega de la que sale, Cliente, Observaciones del cambio |

- Cada fila **empareja** un producto que entra con uno que sale del mismo
  cambio, en el orden en que se registraron (el primero con el primero, el
  segundo con el segundo…). Si un cambio devuelve dos productos y entrega uno,
  sale en dos filas y la segunda queda vacía del lado verde. Cliente y
  observaciones aparecen en todas las filas del cambio.
- Un cambio **anulado** aparece **tachado** y uno en **borrador**, atenuado en
  cursiva. Para ver solo los emitidos, filtre por estado.
- Un clic en cualquier fila abre el cambio completo.
- Todas las columnas se ordenan con un clic en el encabezado; las filas que no
  tienen producto de ese lado quedan al final. Sin orden elegido, van del cambio
  más reciente al más antiguo.
- El contador de la paginación cuenta **filas**, no cambios.
- **PDF y Excel** exportan exactamente estas filas con el filtro y el orden de
  pantalla (en Excel el código va en su propia columna).

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en lo que muestra el listado —productos
que entran y salen (código, nombre, lote, NUP y bodega), número de la **factura
de venta** (también sirve el de la factura de consignación), cliente y
observaciones— y además en la fecha y el número del cambio, el RUC o cédula, el
motivo, la diferencia, el responsable de traslado y el usuario que lo registró.
La búsqueda es **por cambio**: si un cambio coincide, se listan todas sus filas.
El **estado** no entra en la búsqueda libre: para filtrar por él use la ventana
de filtros. Puede escribir varias palabras en cualquier orden y no importan
mayúsculas ni tildes. Mientras busca, aparece un **círculo girando** al final
del cuadro y la tabla se ve atenuada.

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
| Productos que devuelve | Sí (al menos uno) | Ítems de facturas de consignación (estado *facturada*) o de cambios anteriores con saldo pendiente. Columnas: origen (n.º de la factura de venta o del cambio), producto, bodega, lote, NUP y cantidad (propone el saldo pendiente y no puede superarlo). |
| Productos que entrega a cambio | No | Ítems desde consignación, existencias o catálogo. Columnas: origen, producto, bodega, lote, NUP y cantidad. |
| Estado | Solo al editar | Borrador, Emitida o Anulada. Está a la derecha de la barra de botones (PDF, Excel, correo y WhatsApp). |

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
  stock**; queda registrada como **facturada** en Facturación de
  consignaciones, dentro de la factura de venta de lo devuelto.
- **Entrega desde bodega o catálogo**: sale de la bodega elegida (si el
  producto es inventariable y el control de inventario de facturación está
  activo).
- **Estados**: solo *Emitida* mantiene los movimientos de inventario y el
  asiento. Pasar a *Borrador* o *Anulada* los reversa y libera los saldos;
  volver a *Emitida* revalida saldos y los vuelve a aplicar. Solo se editan
  documentos en *Borrador*.
- **Numeración**: el número que se ve al abrir es una vista previa; el
  definitivo se asigna al guardar y no se puede repetir en la misma serie.
- **Diferencia**: se calcula a precio de venta —el de la línea de origen, con
  su IVA; para lo que sale de bodega o del catálogo, el primer precio de lista
  del producto— y es informativa. Solo se usa en los filtros del listado; el
  formulario, el PDF, el Excel y el listado no muestran precios ni totales.

## Integraciones con otros módulos

- **Inventario**: las devoluciones generan una **entrada** y las entregas
  desde bodega una **salida**, ambas con referencia *Cambio de producto*
  (visibles en el kardex y en el reporte de inventario). Las entregas desde
  consignación no mueven stock.
- **Contabilidad**: asiento **a costo** (costo promedio del producto en su
  bodega): *Inventario* contra *Costo de ventas* por el neto entre lo devuelto
  y lo entregado desde bodega; lo entregado desde consignación sale de
  *Mercadería en consignación* contra *Costo de ventas*. Se puede revisar y
  completar en la pestaña **Asiento contable** antes de guardar. Los cambios
  **migrados** del sistema anterior no llevan asiento por ninguna vía (ese
  sistema no contabilizaba los cambios): la pestaña lo indica y no deja armar
  uno.
- **Facturación de consignaciones**: lo entregado desde una consignación queda
  como documento **Facturada** con la factura de venta de lo devuelto (etiqueta
  *Cambio*, solo lectura). Ver *Registro en Facturación de consignaciones*.
- **Consignaciones / Retornos / Reporte de inventarios**: esa unidad **descuenta
  el saldo** de su línea como **facturada**: ya no se ofrece para retornar ni para
  facturar y aparece como *Facturación* (con el número de la factura de venta) en
  el resumen de la consignación y en la columna *Facturado* del reporte. Anular o
  pasar a borrador el cambio libera ese saldo. Además, la consignación queda con
  factura asociada y ya no se puede cambiar su estado.
- **PDF, Excel y correo** desde la barra de acciones del documento. El PDF (el
  mismo que se envía por correo) y el Excel listan lo devuelto y lo entregado
  con origen, código, descripción, bodega, lote, NUP y cantidad, **sin precios,
  totales ni diferencia**. Si la empresa tiene una plantilla propia activa en
  *Plantillas PDF*, el PDF usa esa plantilla tal como fue diseñada.

## Errores frecuentes

- **"Indique un NUP, un número de documento o un producto, o seleccione el
  cliente"**: sin cliente el buscador necesita al menos dos caracteres.
- **"El documento … pertenece a otro cliente"**: el cambio ya tiene un cliente
  distinto. Quite el cliente actual (Backspace en el campo Cliente) o registre
  otro cambio.
- **No aparece la factura**: solo se ofrecen **facturas de consignación** en
  estado **facturada** (las de venta directa no salen aquí), ítems
  que sean bienes (no servicios) y con saldo pendiente de devolver. Búsquela
  por el número de la factura de venta que generó la factura de consignación.
- **No aparece la consignación**: debe estar en estado **Entregada**, ser del
  mismo cliente del cambio y tener saldo (no retornado, no facturado, no
  entregado en otro cambio).
- **La factura dice *Sin factura***: no hay número de factura de venta que
  mostrar. Al pasar el mouse sobre el texto, el listado dice cuál de estos casos
  es:
  - *Cambio migrado sin factura enlazada*: se migró con una versión anterior de
    la herramienta, o su factura no está en el sistema. El superadministrador lo
    completa volviendo a ejecutar **Cambios de productos** en *Migración desde
    MySQL*. Si sigue igual, el resumen de la migración dice el motivo.
  - *La factura de consignación de esta unidad no tiene factura de venta
    enlazada*: no es un error del cambio.
- **Un cambio migrado no muestra los NUP o la consignación de lo entregado**:
  mismo caso y misma solución que el anterior. El sistema anterior no guardaba
  el NUP de lo devuelto: se completa cuando la factura vendió una sola unidad
  posible de ese producto o, si vendió varias, cuando se puede saber cuál
  volvió porque esa unidad se consignó de nuevo después del cambio. En el
  resto de casos no hay forma de saberlo y queda sin NUP.
- **"Secuencial no configurado"**: configure *Cambios de productos* en
  *Empresa → Secuenciales* para el punto de emisión.
- **"No se pudo registrar en Facturación de consignaciones lo entregado desde
  consignación… No hay secuencial configurado"**: el cambio entrega desde una
  consignación y a su punto de emisión le falta el secuencial *Facturación
  consignaciones ventas*. Configúrelo en *Empresa → Secuenciales* y vuelva a
  guardar; el cambio no se emite a medias.
- **"…la base de datos aún no lo admite: aplique
  database/migrations/20260916_facturacion_cv_registro_cambio.sql"**: falta
  actualizar la base de datos; avise al administrador del sistema. Mientras
  tanto se pueden emitir cambios que entregan desde bodega o catálogo.
- **El asiento tiene una cuenta vacía**: falta configurar la cuenta en
  *Configuración contable* (Inventario y Costo de ventas del concepto Ventas,
  Mercadería en consignación del concepto Consignación). Se puede completar en
  la pestaña Asiento contable.

## Historial de cambios

- **1.13** — En el PDF del cambio (el mismo del correo) y en su Excel, la
  **Bodega** pasa antes del *Lote*, igual que en el formulario: Origen, Código,
  Descripción, Bodega, Lote, NUP y Cantidad.

- **1.12** — En el formulario, **Lote** y **NUP** pasan a columnas separadas en
  las dos tablas (lo que se devuelve y lo que se entrega). El listado agrega
  también la columna **NUP** del producto que sale, después de su *Lote*, y la
  lleva a su PDF y Excel.

- **1.11** — En *Productos que devuelve* se quita la columna **Saldo** (la
  cantidad sigue proponiéndolo y no deja superarlo) y **Bodega** pasa antes de
  *Lote / NUP*, igual que en lo que se entrega. Los cambios migrados completan
  el NUP de lo devuelto en más casos: cuando la factura tiene una sola unidad
  posible y, si vendió varias, cuando se puede saber cuál volvió porque esa
  unidad se consignó de nuevo después del cambio. El listado agrega la columna
  **NUP** del producto que entra, después del *Lote* (también en su PDF y
  Excel); se puede ordenar y ocultar como las demás.

- **1.10** — Los cambios **migrados** del sistema anterior muestran la factura de
  venta de lo devuelto (ese sistema sí la guardaba), los NUP y, en lo entregado,
  la consignación de la que salió. Los que ya estaban migrados se completan
  volviendo a ejecutar **Cambios de productos** en *Migración desde MySQL*; solo
  dicen *Sin factura* si esa factura no está en el sistema. Los cambios migrados
  no llevan asiento contable por ninguna vía (antes se les podía generar al
  abrir la pestaña *Asiento contable* o al sincronizar la contabilidad). En el
  formulario, el **Estado** pasa a la derecha de la barra de botones (PDF,
  Excel, correo y WhatsApp) y *Observaciones* ocupa su lugar.
- **1.9** — Nueva primera columna **Fecha** (fecha de emisión del cambio, encabezado
  celeste) en el listado y en su PDF y Excel. Corregido: en los cambios
  registrados antes del 16-09-2026 (lo devuelto venía de la factura de venta
  directa) no se veía el número de la factura en el listado ni en la columna
  *Origen* del PDF y el formulario; ahora sí. Además, esas devoluciones antiguas
  ya no pueden descontar por error el saldo de otra línea de factura de
  consignación con el mismo número interno. Dice *Sin factura* cuando no hay
  factura de venta que mostrar: devoluciones de cambios migrados del sistema
  anterior (antes decía *Bodega*) y de facturas de consignación sin factura de
  venta enlazada (antes la celda quedaba vacía y el PDF decía solo *Factura*). En
  el buscador, esas facturas de consignación se identifican por su número propio.
- **1.8** — Columna **Origen**: lo que se devuelve muestra siempre la **factura de
  venta afectada**, también cuando la unidad llegó en un cambio anterior (antes
  salía el número de ese cambio); lo que se entrega desde consignación muestra
  *Consignación* con su **número** en la misma etiqueta. En el listado, la
  columna **Factura** se llena en todas las filas: en las que solo tienen
  producto que sale aparece, en gris, la factura de la que vino el cambio (la de
  la última unidad que entra, la misma con la que se registra en Facturación de
  consignaciones). El **Excel del cambio** queda como el PDF: sin precio
  unitario, total ni la fila de totales, y con la columna *Bodega*.
- **1.7** — Al emitir un cambio, lo entregado **desde consignación** queda
  registrado en **Facturación de consignaciones** como *Facturada* con la
  factura de venta de lo devuelto (emparejado en orden, un registro por factura),
  sin crear factura nueva ni tocar la original, y con número propio de la serie
  *Facturación consignaciones ventas*. En la consignación esa unidad cuenta como
  facturada, no como entregada a cambio. Pasar a borrador, anular o eliminar el
  cambio anula el registro. Nueva sección *Registro en Facturación de
  consignaciones*.
- **1.6** — Nuevo **listado de dos lados**: a la izquierda (encabezados en rojo)
  el producto que entra —cantidad, producto con su código, lote, bodega y
  factura de venta— y a la derecha (en verde) el que sale —cantidad, producto con
  su código, lote, bodega, cliente y observaciones—, una fila por pareja de
  productos del cambio. Ya no hay columnas de fecha, número, motivo, diferencia
  ni estado: los cambios anulados se ven tachados y los borradores atenuados. PDF
  y Excel del listado exportan las mismas columnas. La búsqueda libre encuentra
  también la factura de venta y la bodega, y la pestaña *Detalles* de filtros
  muestra la factura de venta como documento de origen.
- **1.5** — Formulario y PDF **sin montos**: en lo que se devuelve se quita la
  columna *Total*; en lo que se entrega, *Precio*, *IVA %* y *Total*; y
  desaparece la tarjeta de totales (total devuelto, total entregado,
  diferencia). La diferencia sigue en el listado. Lo que se devuelve muestra la
  columna **Bodega** (antes del saldo) y su origen es el **número de la factura
  de venta** (*Factura 001-001-…*) en lugar del de la factura de consignación;
  el buscador lo encuentra por ese número. El PDF sigue el mismo criterio:
  columnas Origen, Código, Descripción, Lote, NUP, Bodega y Cant.
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
