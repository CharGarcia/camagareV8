---
titulo: Migración desde MySQL
resumen: Trae empresas, usuarios y catálogos del sistema anterior (MySQL) al sistema nuevo, empresa por empresa.
categoria: Configuración global
ruta_modulo: config/migrar-mysql
tipo: modulo
visibilidad: superadmin
etiquetas: migracion, migrar, sistema anterior, mysql, vendedor asignado, vendedor del cliente, clientes sin vendedor, vendedores migracion, asignacion de vendedor, migrar empresas, establecimientos migracion, ruc base, elegir establecimiento, fusionar establecimientos, cliente separado, serie, series, punto de emision, secuencial, numeracion, numero repetido, ingresos sin serie, egresos sin serie, pedidos sin serie, liquidacion pendiente de pago, liquidaciones de compra migradas, pagos migrados, egresos migrados, pago no aparece, cuentas por pagar migradas, compra pendiente de pago, compra pagada sale pendiente, pago no cruza, retencion en borrador, marcas, marca del producto, productos sin marca, catalogo de marcas, migrar marcas, cambios de productos migrados, cambio sin factura, factura del cambio, nup del cambio, recambio, registro de cambio, facturacion de consignacion migrada, unidad duplicada para devolver
version: 1.8
orden: 2
estado: activo
---

**Migración desde MySQL** (`Configuración → Migración MySQL`) trae los datos
del sistema anterior (una base MySQL) hacia este sistema, exclusivo del
**superadministrador**. Se migra empresa por empresa: primero la empresa
(con su establecimiento, un usuario administrador opcional y las asignaciones
de usuarios), y luego, ya con la empresa creada, el resto de catálogos
(clientes, productos, inventario, documentos, etc.) desde las demás pestañas
de la herramienta.

## Migrar empresas: cada establecimiento es un cliente distinto

En esta plataforma, una "empresa" es siempre **un RUC + un establecimiento**.
El mismo RUC puede repetirse en varias empresas del sistema nuevo (una por
cada establecimiento), porque cada establecimiento es, para nosotros, un
**cliente distinto** — aunque compartan el mismo RUC legal, son suscripciones
y negocios independientes.

El sistema anterior guardaba cada establecimiento de un contribuyente como
una fila separada en su tabla de empresas, todas compartiendo los primeros 10
dígitos del RUC ("RUC base") y difiriendo en los 3 dígitos finales (el código
de establecimiento, ej. `001`, `002`). Al listar empresas por migrar, la
herramienta agrupa esas filas por RUC base para mostrarlas juntas, pero **por
defecto cada una se migra como su propia empresa nueva** — no se combinan.

- **Si el RUC base tiene una sola fila activa**, no hay nada que decidir: se
  migra directo, como una empresa.
- **Si tiene más de una** (badge amarillo con la cantidad, junto a la columna
  "Estab."), aparece debajo un selector por cada establecimiento encontrado
  (código, nombre, dirección), con tres opciones:
  - **Cliente separado** (la opción por defecto): se migra como su propia
    empresa nueva, independiente de los demás.
  - **Fusionar con [otro establecimiento de la misma base]**: sus datos NO
    generan una empresa propia — se descartan por completo, y la empresa
    resultante es la del establecimiento elegido como destino, con **sus
    propios datos** (no se combinan campos de ambos). Úsela solo cuando está
    seguro de que ambas filas del sistema anterior son, en realidad, el mismo
    negocio (por ejemplo, datos que quedaron fragmentados por error en el
    sistema anterior) — no porque compartan RUC.
  - **No migrar**: ese establecimiento no se migra en absoluto (ni solo, ni
    fusionado). Útil para filas viejas, de prueba, o que ya no corresponden a
    un negocio real.
- El resultado de la migración muestra, en **Avisos**, qué establecimientos
  se fusionaron en cuáles.
- Migrar es **por establecimiento**, no por toda la base: si más adelante
  aparecen establecimientos nuevos para un RUC ya parcialmente migrado (o se
  vuelve a listar), la herramienta solo ofrece los que todavía no existen en
  el sistema nuevo — los que ya se migraron no vuelven a aparecer, pero el
  resto de la base sigue disponible.

## Series de los documentos migrados

Cada documento del sistema lleva una **serie** (establecimiento + punto de
emisión, por ejemplo `001-101`) y un **secuencial**. La migración los trata de
dos maneras distintas, según lo que traiga el sistema anterior:

- **Documentos que ya tienen serie propia** — facturas, notas de crédito,
  recibos, proformas, guías de remisión, liquidaciones de compra, retenciones
  y consignaciones. Son los documentos **autorizados por el SRI** y los que se
  numeran con serie en el sistema anterior: se migran **exactamente con la
  serie y el secuencial que ya tenían**. La migración nunca los renumera.
  - Caso especial, **retenciones en venta**: el número de esos documentos no
    es de la empresa sino del **cliente** que hizo la retención (cada agente
    de retención numera con su propia serie). Por eso dos clientes distintos
    pueden tener, por ejemplo, la retención `001-002-000000938` y las dos son
    documentos válidos. La migración las distingue por **cliente + número**,
    no solo por número. Si una corrida anterior había dejado la segunda como
    "vinculada" a la del otro cliente (sin insertarla), al volver a migrar
    Retenciones en venta se deshace ese vínculo y se inserta el documento
    faltante; no hace falta usar *Eliminar migrados*.
- **Documentos sin serie en el sistema anterior** — ingresos, egresos, pedidos
  y cambios de producto. El sistema anterior solo les guarda un número
  correlativo. La migración les asigna la **serie activa de la empresa**: el
  establecimiento activo y el **punto de emisión activo de menor número**,
  saltando el punto reservado a *Facturas de reembolso*. El número original se
  conserva como secuencial; en Ingresos y Egresos el "Nº documento" pasa a
  mostrarse completo (`001-001-000000123`), igual que los emitidos aquí.

Esto importa porque el sistema calcula el **siguiente número disponible**
mirando los documentos ya emitidos **en ese punto de emisión**. Un documento
migrado sin punto de emisión es invisible para ese cálculo, y el sistema
volvería a repartir números ya usados.

### Números repetidos

En el sistema anterior los ingresos, egresos y pedidos se numeran **por
establecimiento**: una empresa con dos establecimientos puede tener dos
documentos con el mismo número. Al quedar todos en una sola serie, esos
números chocan. La migración completa la serie del primero y **deja sin serie
al repetido**, avisando al final del proceso con el mensaje *"N quedaron sin
serie: ese número ya está usado en el punto de emisión de destino"*. Esos
documentos hay que revisarlos a mano: nada se borra ni se renumera solo.

## Pagos de liquidaciones de compra

En el sistema anterior una **liquidación de compra** se registraba también
como compra, y el egreso que la pagaba apuntaba a esa compra. Aquí las
liquidaciones tienen su propio módulo, así que al migrar **Pagos (egresos)**
esos pagos se reconocen y se **enlazan a la liquidación**, buscándola por su
número (por ejemplo `001-002-000000045`) dentro de la misma empresa:

- En el egreso, la línea aparece como **Liquidación** (no como Compra) y su
  número abre la liquidación. Si todo lo que paga el egreso son liquidaciones,
  el egreso queda con tipo *Liquidación*, igual que uno registrado aquí.
- La liquidación deja de salir en **Cuentas por Pagar** y en *Liquidaciones de
  Compra - Pendientes de Pago* (Egresos) cuando lo pagado cubre su saldo, y el
  pago se ve en su pestaña **Pagos**.
- Si los egresos se migran **antes** que las liquidaciones, el pago queda
  marcado como liquidación pero sin enlazar; al migrar después **Liquidaciones
  de compra** se enlaza solo. El resultado de cada corrida informa cuántos
  pagos se enlazaron y cuántos quedaron sin enlazar.
- Si el mismo número existe dos veces (por ejemplo en pruebas y en
  producción), se usa la liquidación del ambiente de la empresa y, si aún hay
  dos, la del mismo proveedor; si sigue siendo ambiguo, el pago no se enlaza.

### Pagos de compras: cuándo se pierde el enlace

Los pagos de **facturas de compra** se enlazan a la compra por el registro
interno de la migración (qué documento del sistema anterior corresponde a cuál
de aquí). Ese enlace se pierde en dos situaciones:

- **Los pagos se migraron antes que las compras** (o la compra cayó en
  *omitidos/errores* y se trajo después): la línea del pago queda sin
  documento. Volver a migrar **Pagos (egresos)** lo completa.
- **Las compras se borraron con *Eliminar migrados* y se volvieron a migrar**:
  reciben números internos nuevos y los pagos siguen apuntando a los viejos.
  También se completa volviendo a migrar Pagos (egresos).

En ambos casos Cuentas por Pagar muestra la compra pendiente aunque el pago
exista (ver *Errores frecuentes*).

## Vendedor asignado a cada cliente

El sistema anterior guarda, en la ficha de cada cliente, el **vendedor
asignado**. La migración lo trae tal cual al campo *Vendedor asignado* del
cliente en este sistema.

Para que pueda resolverlo, **Vendedores se migra antes que Clientes** — ese es
el orden en que aparecen en la lista de datos por migrar, y conviene marcar
las dos casillas juntas. Si se migran los clientes sin haber migrado antes los
vendedores, los clientes entran **sin vendedor**; basta con migrar
**Vendedores** y volver a correr **Clientes** para completarlos.

Al volver a correr Clientes, la migración **solo rellena** el vendedor de los
clientes que no tienen ninguno. Nunca pisa una asignación hecha a mano en este
sistema, ni la de un cliente que ya existía aquí y se vinculó al del sistema
anterior. El resumen de la migración informa cuántos clientes quedaron con su
vendedor.

## Marcas de los productos

El sistema anterior no guarda la marca dentro de la ficha del producto: tiene
un **catálogo de marcas** aparte y una tabla que relaciona cada producto con
su marca. En este sistema la marca vive en el propio producto (campo *Marca*)
y el catálogo está en **Marcas**. La migración reconstruye las dos cosas:

- Trae el **catálogo de marcas** de la empresa (una entrada por marca, con el
  nombre en mayúsculas y en estado activo).
- Escribe la **marca de cada producto** migrado.

Para que pueda resolverlo, **Marcas se migra antes que Productos** — ese es el
orden en que aparecen en la lista de datos por migrar, y conviene marcar las
dos casillas juntas. El resumen de la migración informa cuántos productos
quedaron con su marca.

Si los productos ya se habían migrado antes (por ejemplo, con una versión
anterior de la herramienta, que no traía marcas), **no hay que volver a migrar
Productos**: al correr **Marcas** se completan también los productos que ya
estaban migrados.

Qué respeta la migración:

- **Marca repetida**: si la marca ya existe en la empresa (mismo nombre, sin
  importar mayúsculas o espacios), se **vincula** a la existente en lugar de
  duplicarla. Eso incluye la misma marca traída desde dos establecimientos del
  mismo RUC.
- **Productos que ya existían en este sistema** (los que la migración vincula
  por código, no los que inserta): solo se les completa la marca **si no
  tienen ninguna**. Una marca puesta a mano aquí nunca se pisa.
- Volver a correr **Marcas** no duplica nada: corrige y completa.

## Cambios de productos: factura, NUP y Facturación de consignaciones

Cada cambio del sistema anterior se migra con lo que devolvió el cliente y lo
que recibió a cambio. Además, la migración completa lo que ese sistema
guardaba de cada cambio y que antes no se traía:

- **Factura de lo devuelto**: el sistema anterior guardaba el número de la
  factura de venta. La migración enlaza lo devuelto con esa factura ya
  migrada, así el número se ve en el listado, el formulario y el PDF del
  cambio, y se puede buscar por él. Si en esa factura hay **una sola** unidad
  que calce (mismo producto y lote, todavía sin devolver), la enlaza a esa
  unidad y trae su NUP. En un **recambio** (el cliente devuelve lo que recibió
  en un cambio anterior de la misma factura) la enlaza a ese cambio.
- **Lo entregado a cambio**: con cada cambio, el sistema anterior registraba
  la unidad entregada como una facturación de consignación, que llega con
  **Facturación de consignación**. La migración la enlaza al cambio como su
  **registro**, igual que hace un cambio emitido aquí: lo entregado muestra la
  consignación de la que salió y su NUP, y en Facturación de consignaciones
  ese documento aparece marcado como *Cambio*. Sin ese enlace, la misma unidad
  aparecía dos veces para devolver.
- El sistema anterior **no guardaba el NUP de lo devuelto**: solo se completa
  cuando no hay duda de cuál unidad es.

**Facturas de venta** y **Facturación de consignación** se migran antes que
**Cambios de productos** (es el orden de la lista). Si se migraron en otro
orden, o los cambios se migraron con una versión anterior de la herramienta,
basta con **volver a ejecutar Cambios de productos**: completa los que ya
estaban migrados, sin duplicarlos. **No use *Eliminar migrados*** para esto:
los cambios hechos en este sistema que devolvieron una unidad de un cambio
migrado perderían su enlace.

Qué respeta la migración:

- Solo completa lo vacío. No cambia un enlace válido ni toca los cambios
  hechos en este sistema.
- Si se vuelven a migrar Facturas de venta o Facturación de consignación, los
  enlaces que quedaron apuntando a documentos borrados se rehacen: la
  facturación de consignación re-enlaza sola sus registros, y volver a correr
  Cambios de productos rehace el resto.
- Los cambios migrados **no llevan asiento contable** por ninguna vía
  (sincronización, Auditoría, pestaña *Asiento contable* o cambio de estado):
  el sistema anterior no contabilizaba los cambios de productos. Si alguno ya
  recibió uno antes de este ajuste, se detecta y se quita con
  `database/diagnosticos/20260916_cambios_migrados_con_asiento.sql`.
- El resumen informa cuántos productos devueltos quedaron con su factura,
  cuántos entregados con su registro, cuántos NUP se completaron y qué cambios
  siguen sin factura, con el motivo (por ejemplo, *la factura
  001-001-000012345 no está en el sistema*).

## Errores frecuentes

- **Los cambios de productos migrados dicen "Sin factura" o no muestran los
  NUP**: se migraron con una versión anterior de la herramienta, o antes que
  Facturas de venta o Facturación de consignación. Se corrige volviendo a
  ejecutar **Cambios de productos**, sin *Eliminar migrados* (ver *Cambios de
  productos: factura, NUP y Facturación de consignaciones*). Los que sigan sin
  factura aparecen en el resumen con el motivo.


- **Los clientes migrados no traen el vendedor asignado**: se migraron con una
  versión anterior de la herramienta, o se migraron antes que los Vendedores.
  Se corrige migrando **Vendedores** y volviendo a correr **Clientes**: no se
  duplica nada, solo se completa el vendedor de los que están sin él (ver
  *Vendedor asignado a cada cliente*).

- **Los productos migrados no traen marca**: se migraron con una versión
  anterior de la herramienta, o se migraron antes que las Marcas. Se corrige
  corriendo **Marcas**: completa el catálogo y la marca de los productos que
  ya estaban migrados, sin duplicar nada ni volver a migrar Productos (ver
  *Marcas de los productos*).

- **Un ingreso, egreso o pedido migrado no aparece con serie**: su número
  ya estaba usado por otro documento en el punto de emisión de destino (el
  sistema anterior numeraba por establecimiento). Hay que abrirlo y darle un
  número libre, o dejarlo así si es un duplicado real del sistema viejo.
- **La empresa no tiene punto de emisión activo**: los documentos sin serie
  propia no pueden completarse. Primero se crea el establecimiento y el punto
  de emisión en **Configuración → Empresas del sistema**, y luego se vuelve a
  correr la migración de esa entidad (completa la serie de lo ya migrado).
- **Una liquidación de compra migrada sale pendiente de pago aunque en el
  sistema anterior estaba pagada**: los egresos migrados antes de la versión
  1.4 de este artículo guardaban ese pago como una compra sin documento. Hay
  dos formas de corregirlo:
  - Volver a migrar **Pagos (egresos)** de la empresa: reconstruye el detalle
    de los egresos ya migrados con el enlace correcto. Ojo: también reconstruye
    sus formas de pago y el estado de sus cheques desde el sistema anterior; si
    ya se trabajó sobre esos egresos aquí (por ejemplo, cheques marcados como
    cobrados o conciliación bancaria), use la otra vía.
  - Sin volver a migrar: el script
    `database/20260910_enlazar_pagos_liquidaciones_migradas.sql` (solo toca las
    líneas del pago y deja rastro en el log del sistema). Antes conviene revisar
    qué va a enlazar con
    `database/diagnosticos/20260910_liquidaciones_migradas_pago_sin_enlace.sql`.
- **Una compra o liquidación migrada sale pendiente en Cuentas por Pagar
  aunque tiene su pago**: el diagnóstico
  `database/diagnosticos/20260910_cxp_migrados_pagos_no_cruzan.sql` (solo
  lectura) dice el motivo de cada documento pendiente. Los más comunes:
  - *Pago sin enlazar* (la línea del pago no tiene documento o apunta a uno
    borrado): lo corrige `database/20260910_reenlazar_pagos_migrados.sql` sin
    tocar formas de pago ni cheques; o volver a migrar Pagos (egresos).
  - *Retención en borrador*: la retención de compra que debía restar quedó en
    borrador (migradas antes del arreglo de estado). Volver a migrar
    **Retenciones en compra**.
  - *NC restada en el pago*: el egreso descontó la nota de crédito como línea
    negativa y la NC no existe como documento propio. Volver a migrar
    **Compras** (la inserta) y luego **Pagos (egresos)**.
  - *Sin pago en el sistema*: ningún pago apunta al documento. O está
    realmente pendiente, o ese egreso nunca se migró (revisar
    omitidos/errores al migrar Pagos).
- **Se fusionó un establecimiento por error**: no hay forma de deshacerlo
  desde acá — sus datos no se guardaron en ningún lado. Si de verdad hacía
  falta como cliente separado, se crea una empresa nueva a mano desde
  **Configuración → Empresas del sistema** con esos datos.
- **Un establecimiento no debía migrar como cliente separado, sino fusionarse**:
  al revés del caso anterior, si ya se migró como empresa propia y en
  realidad correspondía fusionarlo, hay que decidir manualmente qué hacer con
  esa empresa duplicada (eliminarla desde Empresas del sistema si no tiene
  datos reales todavía).

## Historial de cambios

- **1.8** — **Cambios de productos**: la migración enlaza lo devuelto con su
  factura de venta (el número que guardaba el sistema anterior), enlaza lo
  entregado con la facturación de consignación que el sistema anterior creaba
  con cada cambio (queda como su registro, igual que un cambio emitido aquí) y
  completa los NUP. Antes lo devuelto salía *Sin factura* y la unidad entregada
  aparecía dos veces para devolver. Volver a ejecutar Cambios de productos
  completa los ya migrados; Facturación de consignación re-enlaza sola sus
  registros. Los cambios migrados ya no reciben asiento contable por ninguna
  vía.
- **1.7** — Nueva entidad **Marcas**: se trae el catálogo de marcas del
  sistema anterior y se escribe la marca de cada producto (antes se perdían
  las dos cosas). **Marcas** se migra **antes** que Productos, y al correrla
  se completan también los productos que ya estaban migrados sin marca.
- **1.6** — Clientes: ahora se trae el **vendedor asignado** de cada cliente
  desde el sistema anterior (antes se perdía). **Vendedores** pasa a migrarse
  **antes** que Clientes, y al volver a correr Clientes se completa el
  vendedor de los que ya estaban migrados sin él, sin pisar las asignaciones
  hechas a mano.
- **1.5** — Se documenta cuándo se pierde el enlace pago↔compra (pagos
  migrados antes que las compras; compras borradas y vueltas a migrar) y el
  diagnóstico por motivo de las compras/liquidaciones migradas que siguen
  pendientes en Cuentas por Pagar, con el script que re-enlaza los pagos.
- **1.4** — Pagos (egresos) de **liquidaciones de compra**: la migración los
  enlaza a la liquidación por su número (antes quedaban como compra sin
  documento y la liquidación seguía pendiente de pago). Al migrar Liquidaciones
  de compra se enlazan los pagos que habían quedado sin enlazar. Se documentan
  las dos formas de corregir lo ya migrado.
- **1.3** — Retenciones en venta: la deduplicación pasa a ser por **cliente +
  número** (antes solo por número). Una retención con el mismo
  `estab-pto-secuencial` que otra de un cliente distinto se migraba como
  "vinculada" y nunca aparecía en el módulo; ahora se inserta, y las
  vinculaciones falsas de corridas anteriores se corrigen al re-migrar.
- **1.2** — Se documenta cómo se asigna la **serie** a los documentos
  migrados: los que ya la traen del sistema anterior (autorizados del SRI y
  consignaciones) la conservan intacta; los que no la traen (ingresos,
  egresos, pedidos, cambios de producto) reciben la serie activa de la
  empresa — establecimiento activo y punto de emisión de menor número, sin
  usar el punto de Facturas de reembolso. Se explica el caso de los números
  repetidos entre establecimientos.

- **1.1** — Cambio de modelo: cada establecimiento del sistema anterior se
  migra por defecto como su **propia empresa** (antes: se elegía uno solo por
  RUC base y el resto se descartaba). Se agrega la opción de **fusionar**
  explícitamente dos o más establecimientos en una sola empresa cuando
  realmente son el mismo negocio. La idempotencia pasa a ser por
  establecimiento, no por RUC base completo.

- **1.0** — Primera versión del artículo. Documentaba el modelo anterior:
  elegir un establecimiento por RUC base y descartar el resto.
