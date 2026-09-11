---
titulo: Cuentas por cobrar
resumen: Qué le deben los clientes, desde cuándo, y registro del cobro sobre el mismo listado.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_cobrar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por cobrar, cxc, cartera, deudas de clientes, saldo pendiente, vencido, morosidad, cobrar, recibos de venta, tipo de documento, envio masivo, estado de cuenta, recordatorio de pago, fecha de corte, saldo a una fecha, fecha hasta, vendedor, cartera por vendedor, filtrar por vendedor, producto, cartera por producto, filtrar por producto, que deben por un producto, consolidado, establecimientos, sucursales, matriz, mismo ruc, cartera consolidada, todas las sucursales, serie, punto de emision, serie inactiva, registrar cobro
version: 2.2
orden: 40
estado: activo
---

**Cuentas por cobrar** es la cartera de la empresa: qué facturas siguen sin
cobrarse, de qué cliente y cuántos días llevan vencidas.

## De dónde sale el saldo

El listado se arma con:

- Las **facturas de venta** emitidas y no cobradas.
- Los **recibos de venta** (comprobante interno) con saldo pendiente. No se
  incluyen los anulados ni los que ya se convirtieron en factura (en ese caso
  la deuda pasa a la factura).
- Los **saldos iniciales** cargados al empezar a usar el sistema.

Y se descuenta lo que ya se cobró: cada ingreso que cobra un documento reduce
su saldo. En las facturas también restan las notas de crédito y las retenciones
que le practicó el cliente, y suman las notas de débito; los recibos de venta
solo se reducen con cobros.

Fórmula de cada factura:

```
saldo = total de la factura + notas de débito − cobros − retenciones − notas de crédito
```

### Cómo se enlazan las retenciones y las notas con la factura

- Una **retención** se aplica a la factura desde la que se registró o, si vino
  del SRI, a la factura que figura como *documento de sustento* en sus líneas.
  Si una misma retención sustenta **varias facturas**, cada factura recibe
  solo el valor retenido de **sus** líneas, no el total de la retención.
- Las **notas de crédito y débito** se aplican a la factura que consta como
  *documento modificado*.
- En ambos casos el número se compara **normalizado** (sin guiones y con ceros a la izquierda): `001-001-1120`,
  `001001000001120` y `001-001-000001120` son la misma factura. Un formato
  distinto ya no deja la retención o la nota sin descontar.
- La misma regla la usan el buscador de documentos pendientes de **Ingresos** y
  el estado de pago de **Facturas de Venta**, así que los tres muestran el
  mismo saldo para la misma factura.

## Filtrar por tipo de documento

El filtro **Documento** permite ver todo junto o solo un tipo:

- **Todos**: facturas, recibos de venta y saldos iniciales.
- **Facturas de venta**: solo facturas.
- **Recibos de venta**: solo recibos.
- **Saldos iniciales**: solo los saldos de apertura.

Las tarjetas superiores, el gráfico de antigüedad y las exportaciones a PDF y
Excel respetan este filtro. La columna **Origen** de la tabla indica de qué
tipo es cada fila.

## Filtrar por vendedor

El filtro **Vendedor** deja ver solo la cartera de un vendedor: las facturas y
los recibos de venta que tienen a ese vendedor asignado en el documento. La
lista muestra todos los vendedores de la empresa, incluidos los inactivos,
porque un vendedor dado de baja puede seguir teniendo cartera pendiente.

Las tarjetas superiores, el gráfico de antigüedad y las exportaciones a PDF y
Excel respetan el filtro, igual que el de tipo de documento y el de cliente.
El **Excel** incluye además la columna **Vendedor** con el nombre del vendedor
asignado a cada factura o recibo (vacía en los saldos iniciales).

> Los **saldos iniciales** no tienen vendedor, así que al elegir un vendedor
> quedan fuera del listado y de los totales. Con **Todos** vuelven a aparecer.

## Filtrar por producto

El buscador **Producto** de la tarjeta de filtros funciona como el de Cliente:
al escribir dos o más letras aparece la lista de productos de la empresa (por
nombre o código) y al elegir uno queda como una etiqueta; se pueden elegir
varios. La cartera se acota a los documentos que tienen al menos una línea de
ese producto. Sirve para ver qué se debe por un producto o una familia y para
sacar el PDF o el Excel de esa cartera: el encabezado de ambos indica los
productos filtrados.

- Si se escribe un texto y se presiona **Enter** sin elegir de la lista, se
  filtra por ese texto sobre el **nombre o código de las líneas** (útil para
  familias: "ACEITE" trae todos los aceites).
- Aplica a **facturas y recibos de venta**, buscando en sus líneas de detalle.
- En el consolidado de establecimientos, el producto elegido se cruza con los
  productos de las sucursales por **código**.
- Los **saldos iniciales** no tienen líneas, así que mientras haya un producto
  escrito quedan fuera del listado, de las tarjetas y del gráfico de antigüedad
  (igual que ocurre con el filtro Vendedor).
- El saldo que se muestra es el **saldo completo del documento**, no la parte
  correspondiente al producto: un cobro no se reparte por línea.
- Se combina con el resto de filtros (estado, fechas, cliente, vendedor y
  establecimientos).

### Vista "Por producto"

Junto a los botones **Detallado** y **Por cliente** está **Por producto**:
muestra la cartera agrupada por producto, un grupo por código (o por nombre si
la línea no tiene código), igual que la vista por cliente. Cada grupo indica
cuántos documentos pendientes contienen el producto, la **cantidad** vendida en
ellos, el **valor del producto** en esos documentos (base más impuestos de la
línea), y el **cobrado** y el **saldo** de los documentos. Al desplegar el grupo
aparecen los documentos con sus acciones normales (cobrar, historial, correo).

- Sin filtro de producto, la vista lista **todos los productos** presentes en la
  cartera pendiente. Con productos elegidos o texto escrito, solo esos.
- Un documento con varios productos aparece en cada uno de ellos, así que el
  saldo de los grupos no se suma entre sí.
- Los saldos iniciales no tienen líneas y no entran en esta vista; se avisa al
  pie cuántos quedaron fuera.
- **PDF y Excel** en esta vista salen como **resumen por producto**: una sola
  fila por producto con código, nombre, número de documentos, cantidad, valor
  del producto, total de los documentos, cobrado y saldo, sin el detalle de
  documentos ni clientes (igual que un resumen agrupado). Para ver el detalle de
  documentos se exporta desde la vista Detallado con el filtro de producto.

## Consolidado de establecimientos (solo desde la matriz)

Cuando un mismo RUC tiene varios establecimientos registrados como empresas
distintas (matriz y sucursales), la cartera de cada uno vive por separado. Desde
la **matriz** se puede ver la cartera de todo el grupo en una sola pantalla con el
filtro **Establecimientos**:

- **Solo este (matriz)**: comportamiento normal, únicamente los documentos de la
  empresa activa.
- **Consolidado (N establec.)**: suma los documentos de todos los
  establecimientos del mismo RUC a los que el usuario tiene acceso. Las tarjetas
  (documentos, saldo, vencido, al día), el gráfico de antigüedad, la vista *Por
  cliente*, el PDF y el Excel consolidan de la misma forma. Aparece una tarjeta
  extra con la cantidad de establecimientos incluidos.

Reglas:

- El filtro **solo aparece en la matriz** del grupo (la empresa marcada como
  matriz en *Empresas*) y solo si existe al menos otro establecimiento accesible.
  En una sucursal no se muestra.
- Un usuario que no es superadministrador solo ve los establecimientos que tiene
  asignados; los demás no entran al consolidado aunque compartan RUC.
- Cada documento muestra un **badge con el código del establecimiento** (001,
  002, …) al inicio de la columna *Documento*; al pasar el mouse se ve el nombre.
- **Cobrar un documento de otra sucursal desde la matriz**: el botón de cobro
  de la fila abre el mismo modal, pero el ingreso se registra **en los libros de
  la sucursal dueña del documento**: sus series (puntos de emisión), su
  secuencial de ingresos, sus conceptos, sus formas de cobro y su contabilidad.
  El modal lo avisa con una franja azul con el nombre del establecimiento. La
  matriz no registra nada propio: no hay asiento intercompañías.
- Para cobrar en una sucursal el usuario necesita permiso de **crear** en
  Cuentas por Cobrar **en esa sucursal** (superadministrador siempre puede). Si
  no lo tiene, el botón aparece deshabilitado con el aviso "Sin permiso para
  registrar cobros en el establecimiento…".
- El historial de cobros de un documento de otra sucursal sí se consulta desde
  la matriz. El correo y el WhatsApp de recordatorio **no**: usan la
  configuración de correo y las plantillas de la empresa activa, así que para
  esos documentos se envían desde la sucursal; tampoco entran en el envío masivo.
  Al hacer clic en la fila, el panel de detalle muestra solo el resumen.
- El buscador de **Cliente** busca en todos los establecimientos y muestra al
  cliente una sola vez por identificación; al elegirlo, el filtro alcanza sus
  documentos en todas las sucursales (el cruce es por RUC/cédula, porque cada
  establecimiento tiene su propia lista de clientes).
- El filtro **Vendedor** es por establecimiento: en consolidado solo acota los
  documentos del establecimiento donde existe ese vendedor.
- Cada establecimiento se filtra por **su propio ambiente** (producción o
  pruebas), no por el de la matriz.
- En el PDF y el Excel, el encabezado indica *Alcance: Consolidado por RUC* con
  la lista de establecimientos, y se agrega la columna **Estab.**

## Fecha Hasta como fecha de corte

El filtro **Fecha Hasta** no solo limita qué documentos se muestran (los
emitidos hasta esa fecha): también es la **fecha de corte del saldo**. Los
cobros, retenciones y notas de crédito o débito fechados **después** de esa
fecha no se descuentan, así el listado muestra lo que se debía **ese día**.

Ejemplo: una factura cobrada el 31 de mayo aparece pendiente, con su saldo
completo, en cualquier consulta con Fecha Hasta igual o anterior al 30 de mayo,
y desaparece de los pendientes a partir del 31.

Aplica por igual a facturas, recibos de venta y **saldos iniciales**; las
tarjetas, el gráfico de antigüedad y las exportaciones respetan el corte. Sin
Fecha Hasta, el saldo es el actual. La fecha que manda para un cobro es la
**fecha del ingreso**.

## Días vencidos

Cada documento muestra los **días vencidos**, calculados desde su fecha de
vencimiento. Un documento con días vencidos en cero está pendiente pero todavía
en plazo.

Puede filtrar entre ver solo lo pendiente, solo lo vencido o todo.

## Registrar el cobro

El cobro se registra desde el propio listado, sin salir a otro módulo, tanto
para facturas como para recibos de venta y saldos iniciales. Lo que se registra
aquí es exactamente lo mismo que un ingreso: reduce el saldo del documento y
genera su asiento.

### Serie del cobro: solo puntos de emisión activos

La lista **Serie** del modal muestra únicamente los puntos de emisión en estado
**activo**; los inactivos no aparecen. Es el mismo criterio de Ingresos y
Facturas de Venta, porque el cobro emite un ingreso nuevo con el secuencial de
esa serie. En el consolidado, la lista es la de la sucursal dueña del documento.

- Para usar una serie que no aparece, actívela en **Empresa**, pestaña
  **Puntos de Emisión**.
- Si la empresa no tiene ningún punto activo, la lista muestra *Sin series
  activas* y el cobro no se puede registrar.
- Si una serie se inactiva con el modal ya abierto, al guardar el sistema
  rechaza el cobro con el aviso *La serie (punto de emisión) no es válida o
  está inactiva*: cierre el modal y vuelva a abrirlo.

El recordatorio por **correo** funciona para facturas y recibos; el envío por
**WhatsApp** está disponible solo para facturas.

## Historial de la factura

El botón del reloj de cada fila abre el **Historial de Cobros**, que lista
**todos los movimientos que mueven el saldo del documento**, no solo los cobros
en efectivo o banco:

| Tipo | Qué es | Efecto |
| --- | --- | --- |
| Cobro | Ingreso registrado contra el documento | Abona |
| Retención | Retención en la fuente que le practicó el cliente | Abona |
| Nota de crédito | Devolución o descuento posterior | Abona |
| Nota de débito | Cargo adicional al cliente | **Suma** al saldo |

El pie de la tabla muestra el **total abonado**; si el documento tiene notas de
débito, aparece además una línea con los **cargos**, que no se restan del saldo
sino que lo aumentan.

Las reglas de enlace son las mismas con las que se calcula el saldo: si una
retención o una nota de crédito **no aparece aquí, tampoco está descontando** en
la columna Saldo. Es la forma más rápida de comprobar por qué una factura sigue
pendiente (ver *Por qué un saldo no cuadra*).

Los **saldos iniciales** también muestran sus retenciones y notas de crédito
(las que apuntan a su número de documento). Los **recibos de venta** solo llevan
cobros: no admiten retención ni nota de crédito.

## Envío masivo de recordatorios por correo

Marque los documentos con el casillero de cada fila (o el casillero **Todos**
de la cabecera) y use el botón **Envío Masivo Email**. Antes de enviar se abre
una ventana de **revisión por cliente**: cuántos documentos y qué saldo suma
cada uno, y el **correo destinatario** precargado desde la ficha del cliente.

En esa ventana puede:

- **Corregir el correo** de cualquier cliente o poner **varios destinatarios**
  separados por coma. El cambio aplica solo a ese envío (la ficha del cliente
  no se modifica).
- **Completar** el correo de un cliente que no lo tiene registrado (su casilla
  aparece resaltada).
- **Omitir** a un cliente dejando su correo vacío.

Al confirmar, el sistema envía **un solo correo por cliente** con la tabla
resumen de sus documentos pendientes — facturas y recibos mezclados — con
emisión, vencimiento, días vencidos, total y saldo de cada uno, más el
**total pendiente**.

Detalles del envío:

- Los documentos que ya no tienen saldo al momento del envío se excluyen del
  resumen automáticamente.
- Los saldos iniciales no participan del envío masivo (no tienen ficha de
  contacto); para ellos use el cobro directo.
- Cada envío queda registrado en la auditoría del sistema con los correos
  usados.

## Por qué un saldo no cuadra

Casi siempre por una de estas tres razones, en este orden de frecuencia:

1. **Falta registrar la retención** que le practicó el cliente. La factura queda
   con ese saldo pendiente para siempre.
2. **Falta la nota de crédito** de una devolución ya acordada.
3. El cobro se registró **contra otro documento** del mismo cliente.

Y dos casos que el reporte **no** descuenta a propósito:

- Una retención o nota de crédito cuyo documento de sustento **no existe** como
  factura ni como saldo inicial (por ejemplo, de una factura anterior al uso
  del sistema). Regístrela como saldo inicial o corrija el número.
- Una factura que sigue en **borrador** (aún sin autorizar): no aparece en el
  listado aunque tenga saldo.

## Errores frecuentes

- **Un cliente aparece debiendo algo que ya pagó**: revise si el ingreso quedó
  aplicado a esa factura concreta.
- **El saldo es menor de lo esperado**: puede haber notas de crédito aplicadas.
- **El modal de cobro muestra Nota Crédito 0.00 aunque emití la NC**: en los
  **saldos iniciales** era un error de la pantalla — la nota sí estaba
  descontada del saldo, pero el recuadro se mostraba siempre en cero; ya está
  corregido. En una **factura**, en cambio, significa que la nota no está
  enlazada: abra el **Historial de Cobros** y, si tampoco aparece ahí, revise en
  el módulo de Notas de Crédito el **documento modificado** (debe ser el número
  de la factura), que la NC no esté anulada y que se haya emitido desde el
  **mismo establecimiento** que la factura.
- **No veo las facturas de otro vendedor**: sin el permiso de *acceso total*,
  cada usuario ve solo los documentos que él creó.
- **Una serie no aparece en el modal de cobro**: está **inactiva**. Solo se
  ofrecen los puntos de emisión activos; actívela en Empresa, pestaña Puntos de
  Emisión (ver *Serie del cobro: solo puntos de emisión activos*).

## Historial de cambios

- **2.2** — La lista **Serie** del modal de cobro ya no ofrece puntos de emisión
  **inactivos**: solo los activos, igual que Ingresos y Facturas de Venta. El
  servidor también rechaza un cobro con una serie inactiva, y si la empresa no
  tiene ninguna activa la lista lo indica (*Sin series activas*). Nueva sección
  *Serie del cobro: solo puntos de emisión activos*.
- **2.1** — El listado abre más rápido: la consulta que arma la cartera dejó de
  recalcular por cada documento a qué ambiente pertenece su empresa, lo que en
  bases con muchos documentos degradaba la pantalla entera. También se corrigió el
  orden: las filas con la misma fecha de vencimiento ya no cambian de posición
  entre una carga y otra.
- **2.0** — El **Historial de Cobros** deja de listar solo los ingresos: ahora
  muestra también las **retenciones**, las **notas de crédito** (abonan) y las
  **notas de débito** (cargan), cada una con su tipo, número y monto, con el
  total abonado y los cargos separados en el pie. Aplica tanto a facturas como a
  saldos iniciales. Nueva sección *Historial de la factura*. Además: en el modal
  de cobro de un **saldo inicial**, el recuadro **Nota Crédito** mostraba
  siempre 0.00 aunque la nota sí estuviera descontada del saldo — ya muestra el
  valor real; y un ingreso pagado con **varias formas de cobro** ya no aparece
  repetido ni se suma dos veces en el total.
- **1.9** — Buscador **Producto** con lista y etiquetas (igual que Cliente) y
  nueva vista **Por producto**: la cartera agrupada por producto con cantidad,
  valor del producto, cobrado y saldo de los documentos, exportable a PDF y
  Excel. Aplica a facturas y recibos; los saldos iniciales no tienen líneas y
  quedan fuera con este filtro o en esta vista.
- **1.8** — Consolidado, fase 2: desde la matriz ya se puede **registrar el
  cobro** de una factura, recibo o saldo inicial de otra sucursal. El ingreso se
  registra en los libros de la sucursal dueña (sus series, secuencial, conceptos,
  formas de cobro y contabilidad) y exige permiso de crear en esa sucursal. El
  correo y el WhatsApp siguen enviándose desde la sucursal.
- **1.7** — Nuevo filtro **Establecimientos** para ver la cartera **consolidada
  de todos los establecimientos del mismo RUC**, disponible solo desde la
  **matriz** del grupo (fase 1, solo lectura): los documentos de las sucursales
  se listan con el badge de su establecimiento, suman en tarjetas, antigüedad,
  PDF y Excel, y permiten ver su historial, pero el cobro, el correo y el
  WhatsApp se registran desde la empresa dueña del documento. El buscador de
  cliente cruza por identificación entre establecimientos.
- **1.6** — El PDF y el Excel exportados muestran, bajo el encabezado, los
  **filtros aplicados** (tipo de documento, estado, vendedor, período y cliente),
  para que quien lo reciba sepa exactamente qué cartera está viendo. En el Excel
  los montos ahora son celdas numéricas con dos decimales y sin separador de
  miles, listas para sumar.
- **1.5** — Nuevo filtro **Vendedor** en la tarjeta de filtros: acota facturas y
  recibos al vendedor asignado en el documento. Aplica a listado, tarjetas,
  gráfico de antigüedad y exportaciones. Los saldos iniciales, que no tienen
  vendedor, se excluyen mientras haya un vendedor seleccionado. El Excel
  incorpora la columna **Vendedor**.
- **1.4** — Los **saldos iniciales** respetan la fecha de corte igual que las
  facturas: con Fecha Hasta, un cobro, retención o nota de crédito posterior a
  esa fecha ya no descuenta el saldo inicial (antes se usaba el acumulado
  cobrado sin importar la fecha). Aplica al listado, a las tarjetas y al
  gráfico de antigüedad.
- **1.3** — Corrección del cruce del saldo: (1) una retención que sustenta
  varias facturas ya no resta su total completo a cada una, sino solo el valor
  retenido de las líneas de cada factura; (2) las retenciones y las notas de
  crédito/débito se enlazan a la factura comparando el número **normalizado** (sin guiones, con ceros a la izquierda) del
  número, así un formato distinto (`001-001-1120`) ya no deja el abono sin
  descontar. La misma regla quedó compartida con Ingresos y Facturas de Venta.
- **1.2** — El envío masivo de correo ahora agrupa por cliente: un solo correo
  con la tabla resumen de todos sus documentos pendientes (facturas y recibos)
  y el total, con ventana previa para revisar, corregir o completar el correo
  de cada cliente antes de enviar.
- **1.1** — Se incorporan los recibos de venta al listado y se agrega el filtro
  **Documento** (Todos / Facturas de venta / Recibos de venta / Saldos
  iniciales). Cobro, historial y recordatorio por correo disponibles para
  recibos.
- **1.0** — Versión inicial.
