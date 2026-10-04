---
titulo: Migración desde MySQL
resumen: Trae empresas, usuarios y catálogos del sistema anterior (MySQL) al sistema nuevo, empresa por empresa.
categoria: Configuración global
ruta_modulo: config/migrar-mysql
tipo: modulo
visibilidad: superadmin
etiquetas: migracion, migrar, sistema anterior, mysql, vendedor asignado, vendedor del cliente, clientes sin vendedor, vendedores migracion, asignacion de vendedor, migrar empresas, establecimientos migracion, ruc base, elegir establecimiento, fusionar establecimientos, cliente separado, serie, series, punto de emision, secuencial, numeracion, numero repetido, ingresos sin serie, egresos sin serie, pedidos sin serie, liquidacion pendiente de pago, liquidaciones de compra migradas, pagos migrados, egresos migrados, pago no aparece, cuentas por pagar migradas, compra pendiente de pago, compra pagada sale pendiente, pago no cruza, retencion en borrador, marcas, marca del producto, productos sin marca, catalogo de marcas, migrar marcas, cambios de productos migrados, cambio sin factura, factura del cambio, nup del cambio, recambio, registro de cambio, facturacion de consignacion migrada, unidad duplicada para devolver, iva inflado, iva multiplicado, iva x1000, asiento de compra mal, iva del asiento mayor, nota de credito compra asiento, contabilidad migrada, alumnos, migrar alumnos, estudiantes, campus, niveles, cursos, horarios, pension, servicios del alumno, descuento del alumno, alumnos activos, alumnos pasivos, representante del alumno, vehiculos, migrar vehiculos, placas, placa, chasis, chasis 123456789, año 2022, propietario privado, vehiculos faltantes, vehiculo sin orden, car wash migracion, cobros de recibos, recibo pendiente migrado, recibo sin abono, pago de recibo no cruza, recibos de venta migrados, saldo de recibo, liquidaciones 2020, liquidaciones antiguas pendientes, pagada en el sistema anterior, liquidacion sin pago migrada, egreso sin asiento, asiento no migrado, asiento contable faltante, desde, re-sincronizar contabilidad, registrado tarde, anulado en el sistema anterior, ingreso anulado con asiento, egreso anulado con asiento, asiento migrado vivo, asiento de documento anulado, categorias, categoria del producto, productos sin categoria, grupo de producto, grupo familiar, migrar categorias
version: 1.19
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

### Liquidaciones hasta 2020: pagadas en el sistema anterior

El sistema anterior no registraba los pagos de las liquidaciones de compra hasta
2020, así que las de esos años llegaban **pendientes de pago** aunque estaban
pagadas. Al migrar **Liquidaciones de compra**, las que cumplen todo esto quedan
marcadas como **pagadas en el sistema anterior** (saldo $0.00):

- vinieron del sistema anterior (no las que ya existían aquí y solo se vincularon);
- fecha de emisión hasta el **31-12-2020**;
- vigentes (no anuladas);
- con saldo pendiente, **total o parcial**, descontando pagos y retenciones.

No se crea ningún egreso: no mueven caja ni bancos, ni cambian asientos,
declaraciones o ATS. El resultado lo informa como *liquidaciones hasta 2020
marcadas como pagadas*. Volver a ejecutar Liquidaciones marca también las migradas
antes; no desmarca ni duplica nada. Para marcar lo ya migrado sin volver a migrar,
el SQL `database/migrations/20260930_liquidaciones_pagada_sistema_anterior.sql`
hace lo mismo en todas las empresas.

## Cobros de recibos de venta

En el sistema anterior, el cobro de un recibo de venta es una línea del ingreso
que apunta al recibo. Al migrar **Cobros (ingresos)**, cada una de esas líneas se
**cruza con su recibo migrado**: el recibo muestra su abono y su saldo en
**Recibos de venta** y en **Cuentas por cobrar**, y el cobro llega como tipo
*Recibo de venta*, con el cliente del recibo y el número del recibo en el detalle.

- Migre **Recibos de venta antes que Cobros**. Si un cobro apunta a un recibo que
  todavía no está migrado, la línea queda sin cruzar y el resultado lo avisa
  (*pagos de recibos sin cruzar*): migre los recibos y vuelva a ejecutar Cobros.
- Si los cobros ya se habían migrado y los recibos aparecen **pendientes aunque
  estaban pagados**, vuelva a ejecutar **Cobros**: los cobros ya migrados se
  reconstruyen y quedan cruzados con sus recibos, sin duplicarse.
- Un cobro que paga facturas y recibos a la vez queda como cobro de factura, pero
  cada línea cruza con su propio documento.
- **Estado del recibo**: se respeta el del sistema anterior. *Abierto* llega como
  **Borrador**, *Anulado* como **Anulado** (no aparece en Cuentas por cobrar ni
  admite cobros) y *Cerrado* (ya cobrado por completo) como **Emitido**. Al volver
  a ejecutar Recibos, los ya migrados se corrigen: solo se avanza el estado
  (Borrador → Emitido o Anulado; Emitido → Anulado); nunca se reabre un recibo ni se
  cambia uno facturado.
- Además, los recibos que siguen en **Borrador** y ya tienen un cobro cruzado
  (no anulado), aunque sea parcial, pasan a **Emitido** al terminar la migración de
  Cobros: quedan cerrados y ya no se editan (se pueden seguir cobrando, facturando
  o anulando). Los recibos que ya existían en el sistema y solo se vincularon no se
  tocan.

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

## Categorías de los productos

En el sistema anterior la categoría de un producto o servicio está en un
catálogo aparte (*grupos de producto*), y cada producto se asigna a su grupo en
otra tabla; la ficha del producto no la guarda. Al migrar **Productos y
servicios**:

- Cada grupo usado por algún producto se crea en **Categorías** de la empresa.
  Si ya existe una categoría con el mismo nombre (sin distinguir mayúsculas),
  se reutiliza; si estaba eliminada, se reactiva.
- Cada producto queda con su categoría. En los productos que **creó la
  migración** manda la del sistema anterior; en los que ya existían y solo se
  **vincularon** por código, la categoría se completa **solo si no tenían
  ninguna** (nunca se pisa una puesta a mano).
- El resultado informa cuántos productos quedaron con categoría y cuántas
  categorías se crearon. Volver a correr **Productos** completa lo que falte
  sin duplicar.

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
- El sistema anterior **no guardaba el NUP de lo devuelto** (ni en el cambio,
  ni en la factura, ni en el inventario). La migración lo toma de la unidad
  vendida en la facturación de consignación de esa factura cuando hay **una
  sola** posible: mismo producto, mismo lote (o sin lote) y todavía sin
  devolver, sin contar las facturaciones que el sistema anterior creaba con
  cada cambio. Si la factura vendió varias unidades de ese producto, se usa
  una segunda pista: una unidad vendida solo vuelve a aparecer en otra
  consignación si regresó a la empresa. Por cada factura y producto, si las
  unidades que volvieron a consignarse desde el primero de esos cambios son
  tantas como los cambios, y ordenando por fecha a cada cambio le corresponde
  una sola, se asigna esa. Si algo no calza (volvieron más o menos unidades
  que cambios, o dos cambios podrían tener la misma unidad), no se asigna
  nada y queda sin NUP. Cada vez que se ejecuta, vuelve a revisar las que
  quedaron sin NUP. Para ver cuántas quedan y por qué:
  `database/diagnosticos/20260916_cambios_migrados_nup_devoluciones.sql`
  (solo lectura).

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

## Asientos de compra con el IVA multiplicado por 1000

El sistema anterior grabó algunos asientos de compra —casi todos de **notas de
crédito** de 2025 y 2026— con el IVA **mil veces mayor** que el real. Por ejemplo,
una nota de crédito de $248,69 + IVA $37,30 quedó en el diario con IVA
$37.303,20 y Cuentas por pagar $37.551,89. El documento está bien; solo el
asiento venía mal.

- Al migrar **Contabilidad**, la herramienta detecta ese patrón (el IVA
  equivale a una tarifa de entre 4.000 % y 16.000 % de la base) y lo corrige
  al importar: divide el IVA para 1000 y ajusta Cuentas por pagar en la misma
  diferencia, así el asiento queda cuadrado.
- El resumen de la migración avisa cuántos asientos se corrigieron y muestra
  los primeros, con el IVA antes y después.
- Los asientos descuadrados que **no** siguen ese patrón no se tocan: se
  revisan a mano.
- Para los asientos que ya estaban migrados antes de esta corrección, basta
  volver a correr **Contabilidad** (reconstruye el detalle ya corregido), o
  usar el script `database/migrations/20260923_corregir_iva_x1000_asientos_migrados.sql`
  sin volver a migrar.

## Asientos de pagos y cobros, y el filtro "Desde"

El asiento de cada pago (egreso) o cobro (ingreso) del sistema anterior se migra
con **Contabilidad** y queda **enlazado** a su documento (botón de asiento del
egreso o ingreso):

- El enlace usa el vínculo real del sistema anterior (el asiento que el egreso
  tiene registrado), no el código del asiento: en algunas empresas ese código
  traía otro número y el egreso quedaba "sin asiento" aunque el asiento sí se
  había migrado.
- No importa el orden: si **Pagos (egresos)** o **Cobros (ingresos)** se migran
  después de la Contabilidad, se enlazan solos con su asiento ya migrado (el
  resultado lo informa como *documentos enlazados con su asiento contable*).
- La pestaña **Asiento contable** del documento (egreso, ingreso, factura o
  recibo de venta) muestra ese asiento migrado, con su número del sistema
  anterior (por ejemplo `EGR184544`) y su fecha original. El número interno del
  asiento en el sistema anterior (por ejemplo 635015) no se conserva.
- **"Desde" incluye lo registrado tarde.** En Contabilidad, Pagos y Cobros, la
  fecha *Desde* trae lo fechado desde ese día **y también lo registrado (o
  editado) en el sistema anterior desde ese día**, aunque tenga una fecha
  anterior. Así, una re-sincronización con un "Desde" reciente ya no deja fuera
  un egreso del 18 de junio que se registró el 15 de julio, ni su asiento. El
  *Hasta* sigue filtrando por la fecha del documento.
- Hay egresos que **no tienen asiento que migrar**: en el sistema anterior nunca
  se contabilizaron, o su asiento quedó **vacío** al editarlo (figura como
  *Editado* sin líneas). Esos se quedan sin asiento.
- **Documentos anulados en el sistema anterior después de migrar.** Si un pago
  o cobro se anula en el sistema anterior **después** de haber migrado la
  Contabilidad, allá su asiento se desvincula (el documento queda en 0,00), pero
  aquí el asiento migrado seguía contabilizado. Al volver a migrar **Pagos
  (egresos)** o **Cobros (ingresos)**, el documento llega anulado y **su asiento
  migrado se anula** en la misma operación (el resultado lo informa como
  *documentos anulados en el sistema anterior*). Si el asiento cae en un período
  contable cerrado, ese documento se cuenta como error y no cambia: abra el
  período y vuelva a migrar. Solo se anulan asientos de la migración; el asiento
  propio de un documento nativo no se toca.

Diagnóstico de solo lectura para producción:
`database/diagnosticos/20261001_egresos_migrados_sin_asiento.sql`.

## Vehículos: uno por placa

La entidad **Vehículos (uno por placa)** trae al módulo **Vehículos** todas las
placas del sistema anterior. Ese sistema no tenía un catálogo de vehículos: guardaba
una copia del vehículo en cada orden de servicio. Por eso el resumen cuenta **placas
distintas** (no filas), y cada placa llega como **un solo vehículo** con:

- los datos de su orden **más reciente** (marca, chasis, año, propietario), campo por
  campo: si la última orden dejó un campo vacío, se toma el de la orden anterior;
- el **cliente** de la orden más reciente que tenga uno.

Qué hace con cada placa:

- **Placa nueva**: se crea el vehículo.
- **Placa que ya estaba registrada a mano** en el sistema nuevo (con o sin guion,
  p. ej. `PIW0394` y `PIW-0394` se consideran la misma): se **vincula** y no se
  cambia ningún dato.
- **Placa que creó una migración anterior** (esta entidad o la de Órdenes de
  servicio): se **actualiza** con los datos más recientes. Si alguien editó el
  vehículo después de migrarlo, se respetan sus cambios y solo se completan los
  campos vacíos. El resultado lo informa como *vehículos actualizados*.

**Valores de relleno**: el formulario del sistema anterior venía precargado con
chasis `123456789`, año `2022` y propietario `Privado`, y casi nadie los cambiaba.
Esos valores **no se migran** (el campo queda vacío). El año 2022 se descarta solo
cuando el chasis también es el de relleno: con un chasis real, 2022 es un año real.
Al volver a ejecutar la entidad, también se limpian los vehículos migrados antes
con esos valores.

- Migre **Clientes** antes, para que el vehículo quede enlazado a su cliente.
- Ejecútela **antes de Órdenes de servicio**: las órdenes usan el vehículo de su
  placa. Si ya migró las órdenes, ejecútela igual: corrige sus vehículos y trae las
  placas que faltaban (las de órdenes sin servicios, que no se migran).
- Se puede ejecutar varias veces sin duplicar nada.

## Órdenes de servicio (Car-Wash / mecánica)

La entidad **Órdenes de servicio (Car-Wash / mecánica)** trae las órdenes del módulo
*Orden mecánica* del sistema anterior al módulo **Servicio de car wash**: cada orden
con sus servicios y productos, el vehículo (usa el de su placa; si no existe, lo
crea sin los valores de relleno), el cliente, fechas de recepción y entrega,
próxima cita y observaciones. Conserva el número de orden como secuencial, en la
serie que más usaban sus facturas.

- Las órdenes **sin servicios ni productos** no se migran; sus vehículos sí llegan
  con la entidad **Vehículos**.
- Migre antes **Clientes**, **Vehículos**, **Productos**, **Bodegas**, **Facturas** y **Recibos**: así
  cada orden queda enlazada con la factura o el recibo en que se cobró (pestaña
  *Facturación* de la orden).
- Si Facturas o Recibos se migran después, vuelva a ejecutar las órdenes: se
  re-enlazan sus documentos sin duplicar nada.
- Las órdenes cerradas llegan como *Facturado*; las que estaban en taller o en
  espera, como *Borrador*. No mueven inventario (el kardex se migra aparte).
- Si el número de una orden ya está ocupado por otra en esa serie, se renumera al
  siguiente libre; si es la misma orden (misma placa y fecha), se vincula.

## Alumnos: campus, niveles y alumnos activos

Tres entidades traen el módulo de alumnos del sistema anterior al módulo
**Alumnos**. Ejecútelas en este orden, **después de Clientes y Productos**:

1. **Alumnos: campus** — los campus (sedes). Si un nombre se repite, o ya existe
   en el sistema nuevo, se vincula en vez de duplicarse.
2. **Alumnos: niveles / cursos** — los niveles (Inicial, Sala Cuna…), igual que
   los campus.
3. **Alumnos activos** — **solo los alumnos en estado Activo** del sistema
   anterior; los *Pasivos* no se migran. Cada alumno llega con:
   - **Nombres y apellidos**: el anterior los guardaba en un solo campo (nombres
     primero); los dos últimos bloques pasan a Apellidos («ANA MARÍA PÉREZ
     LÓPEZ» → Nombres «ANA MARÍA», Apellidos «PÉREZ LÓPEZ»). Con tres palabras
     no se puede saber si la del medio es nombre o apellido: revise esos casos.
   - Identificación (cédula o pasaporte), fecha de nacimiento y sexo.
   - **Cliente que factura** (el representante): el cliente del alumno, ya
     migrado. Si el alumno no tenía cliente, o su cliente todavía no se migró,
     queda con **Consumidor Final** y un aviso en sus observaciones para
     asignarlo a mano. El resumen lista cuáles fueron.
   - **Serie** de facturación (p. ej. 001-001), si existe en la empresa.
   - **Matrícula vigente** con su campus, nivel y fecha de ingreso.
   - **Horario**: el anterior lo guardaba como texto («8h30 a 14h00»); se
     registra de **lunes a viernes** con esas horas. Si el texto no tiene horas
     reconocibles (p. ej. «MAÑANA»), queda en las observaciones del alumno.
   - **Servicios a facturar** con su cantidad, **precio pactado** y **descuento**
     (valor en $ por servicio, que se resta en cada factura). Los servicios cuyo
     producto no está migrado se omiten y el resumen los cuenta.

Si un alumno ya existe en el sistema nuevo con la misma identificación, se
vincula y no se duplica. Volver a ejecutar no repite nada. Los descuentos
puntuales por mes del flujo antiguo de facturación masiva no se migran (el
sistema anterior tampoco los aplicaba al facturar por alumno).

## Errores frecuentes

- **El asiento de una compra migrada tiene el IVA mucho mayor que el
  documento**: ver *Asientos de compra con el IVA multiplicado por 1000*.

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

- **1.19** — **Productos y servicios**: se migra la **categoría** de cada
  producto (grupos de producto del sistema anterior), creando las categorías
  que falten.
- **1.18** — Al volver a migrar Pagos (egresos) o Cobros (ingresos), un
  documento que llega **anulado** del sistema anterior anula su asiento migrado
  si seguía contabilizado. Antes, un documento anulado allá después de migrar la
  Contabilidad quedaba aquí anulado pero con el asiento vivo.
- **1.17** — **Asientos de pagos y cobros**: se enlazan por el vínculo real del
  sistema anterior y también al migrar Pagos/Cobros después de la Contabilidad;
  el filtro **Desde** de Contabilidad, Pagos y Cobros incluye lo registrado tarde;
  la pestaña *Asiento contable* del documento muestra el asiento migrado.
- **1.16** — **Liquidaciones de compra**: las migradas hasta 2020 con saldo
  pendiente quedan **pagadas en el sistema anterior** (saldo $0.00, sin egreso),
  porque ese sistema no registraba sus pagos.
- **1.15** — **Cobros**: los pagos de recibos de venta se cruzan con su recibo
  migrado (antes llegaban como "otros ingresos" sin enlace y los recibos quedaban
  pendientes). Los recibos migrados con cobro cruzado pasan de Borrador a Emitido.
  **Recibos**: se migra su estado del sistema anterior (los anulados llegaban como
  Borrador y figuraban pendientes de cobro).
  Re-ejecutar Cobros corrige lo migrado antes.
- **1.14** — Nueva entidad **Vehículos (uno por placa)**: trae todas las placas del
  sistema anterior (también las de órdenes sin servicios, que antes se perdían), con
  los datos de su orden más reciente y su cliente. Ya no se migran los valores de
  relleno (chasis 123456789, año 2022, propietario "Privado"), y al re-ejecutarla se
  corrigen los vehículos migrados antes.

- **1.13** — Nuevas entidades **Alumnos: campus**, **Alumnos: niveles / cursos** y
  **Alumnos activos**: migran solo los alumnos activos, con su cliente
  (representante), serie, matrícula vigente, horario, servicios a facturar y
  descuentos.

- **1.12** — Nueva entidad **Órdenes de servicio (Car-Wash / mecánica)**: migra el
  módulo *Orden mecánica* del sistema anterior al Servicio de car wash, con su
  vehículo, servicios y la factura o recibo en que se emitió cada orden.
- **1.11** — **Contabilidad**: los asientos de compra que el sistema anterior
  grabó con el **IVA multiplicado por 1000** se corrigen al importar, y el
  resumen avisa cuáles fueron. Volver a migrar Contabilidad corrige los que ya
  estaban cargados; también hay un script para corregirlos sin re-migrar.

- **1.10** — La **facturación de consignaciones** y los **retornos de
  consignación** migrados ya traen la **fecha de vencimiento** de cada unidad:
  antes se quedaba vacía, y por eso los *Cambios de productos* devolvían
  mercadería al inventario sin esa fecha aunque la consignación sí la tuviera. La
  fecha se toma de la línea de consignación de la que salió cada unidad, igual que
  ya se hacía con la bodega, así que **volver a migrar completa lo que ya estaba
  cargado** sin necesidad de *Eliminar migrados*.

- **1.9** — **Cambios de productos**: se completan más NUP de lo devuelto. Ya
  no se toman como posibles las facturaciones que el sistema anterior creaba
  con cada cambio, la facturación se encuentra también por el número de
  factura cuando quedó sin enlace a la venta, y si el lote guardado no
  coincide se prueba con las unidades sin lote. Cuando la factura vendió
  varias unidades, se asigna la que volvió a consignarse después del cambio,
  solo si en esa factura y producto calza para todos los cambios. Cada
  ejecución vuelve a revisar lo que quedó sin NUP; el resumen informa cuántos
  se dedujeron así. Nuevos diagnósticos de solo lectura:
  `database/diagnosticos/20260916_cambios_migrados_nup_devoluciones.sql` y
  `database/diagnosticos/20260916_cambios_migrados_nup_por_reconsignacion.sql`.
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
