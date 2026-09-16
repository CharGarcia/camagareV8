---
titulo: Cuentas por cobrar
resumen: Qué le deben los clientes, desde cuándo, y registro del cobro sobre el mismo listado.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_cobrar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por cobrar, cxc, cartera, deudas de clientes, saldo pendiente, vencido, morosidad, cobrar, recibos de venta, tipo de documento, envio masivo, estado de cuenta, recordatorio de pago, fecha de corte, saldo a una fecha, fecha hasta, vendedor, cartera por vendedor, filtrar por vendedor, producto, cartera por producto, filtrar por producto, que deben por un producto, consolidado, establecimientos, sucursales, matriz, mismo ruc, cartera consolidada, todas las sucursales, serie, punto de emision, serie inactiva, registrar cobro, cedula y ruc, cliente duplicado, proveedor duplicado, mismo cliente dos veces, mismo proveedor dos veces, identificacion repetida, ruc es la cedula mas 001, unificar fichas, cartera partida en dos, tildes, acentos, eñe, buscar sin tildes, no encuentra al cliente, no aparece el cliente, buscar por apellido, buscar por varias palabras, mayor, mayor del cliente, cartera como mayor, agrupado por cliente, subtotal por cliente, total general, seccion por cliente, no carga al entrar, boton aplicar, aplicar filtros, listado vacio al entrar, detalle por cliente, columnas del detalle, nc, abonos, retenciones, dias vencidos, asesor, vendedor del documento, fecha un dia antes, ordenar, ordenamiento, orden alfabetico, a-z, z-a, ordenar por cliente, ordenar por saldo, ordenar por vencimiento, clic en la columna, ordenar la tabla, ordenar el excel, ordenar el pdf, flecha de la columna, saldo junto al nombre, saldo del cliente, pdf vertical, pdf horizontal, orientacion del pdf, hoja vertical, pdf apaisado, acceso total, permiso de ver todos, registros propios, solo mis facturas, no veo las facturas de otro, cada usuario ve lo suyo, documentos migrados no aparecen, cartera del vendedor, mis clientes, clientes asignados, vendedor vinculado, usuario del sistema, el vendedor no ve nada, asesor solo ve sus clientes, logo, logo en el pdf, logo de la empresa, encabezado del pdf, filtros del pdf, filtros aplicados, quitar filtros del pdf, nivel de usuario, administrador ve todo, el vendedor ve la cartera de todos
version: 2.14
orden: 40
estado: activo
---

**Cuentas por cobrar** es la cartera de la empresa: qué facturas siguen sin
cobrarse, de qué cliente y cuántos días llevan vencidas.

## El listado se consulta al presionar Aplicar

Al entrar al módulo **no se consulta nada**: la tabla muestra la invitación
*«Elija los filtros y presione Aplicar»* y las tarjetas de arriba quedan en
cero. Primero se arman los filtros (documento, estado, vendedor, fechas,
cliente, producto, establecimientos) y recién al presionar **Aplicar** el
sistema va a buscar la cartera.

- Así se evita la consulta pesada de "toda la cartera" cada vez que alguien
  abre el módulo de paso, y se pueden elegir varios filtros sin que la pantalla
  se recargue en cada cambio.
- **Antes del primer Aplicar** ningún filtro dispara la consulta: cambiar el
  estado, el vendedor o agregar un producto solo prepara la búsqueda.
- **Después del primer Aplicar** el módulo se comporta como siempre: cambiar un
  filtro vuelve a consultar de inmediato.
- El botón **Limpiar** deja los filtros en sus valores por defecto; si todavía
  no se aplicó nada, tampoco consulta.

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

## Buscar el cliente: tildes, ñ y varias palabras

El buscador **Cliente** de la tarjeta de filtros encuentra al cliente aunque no
se escriban las tildes ni la eñe: `PENA` encuentra a *MENDOZA PEÑA*, `COMPANIA`
encuentra a *COMPAÑIA…* y `MOVIL` encuentra a *ARMARIO MÓVIL*. También funciona
al revés: escribir la tilde o la eñe aunque la ficha esté guardada sin ellas.

Además busca **por palabras sueltas y en cualquier orden**: `PEÑA MENDOZA`
encuentra a *MENDOZA PEÑA DANILO*, sin necesidad de escribir el nombre completo
ni en el mismo orden en que está guardado. Cada palabra puede aparecer en el
nombre o en la identificación, así que `1716782832001` también sirve.

Basta con escribir **dos letras** para que aparezca la lista. Al elegir un
cliente queda como una etiqueta y se pueden elegir varios.

## Quién ve qué: nivel del usuario y permiso de Acceso total

La cartera respeta el nivel del usuario y el permiso **Acceso total** del módulo
(*Configuración → Permisos por módulo*), con la misma regla que el Reporte de
Ventas y el Reporte de Ventas por Vendedor:

- **Administradores (nivel 2) y superadministradores (nivel 3)**: ven la cartera
  completa de la empresa, tengan o no marcado *Acceso total*.
- **Usuarios de nivel 1 con acceso total**: también ven la cartera completa.
- **Nivel 1 sin acceso total y el usuario es un vendedor**: ve **solo lo de su
  vendedor**, es decir los documentos que llevan **su nombre** en el campo
  *Vendedor* y, si un documento no tiene vendedor, los de los **clientes que tiene
  asignados** (campo *Vendedor* de la ficha del cliente). Nunca ve los que llevan
  el nombre de otro vendedor, aunque el cliente sea suyo. Los saldos iniciales no
  llevan vendedor: entran si el cliente es suyo.
- **Nivel 1 sin acceso total y el usuario no es vendedor** (un cajero, un
  digitador): ve solo los documentos que **él registró** — las facturas y los
  recibos de venta que emitió y los saldos iniciales que cargó.
- En los dos casos, lo que no sale en la tabla tampoco entra en las tarjetas de
  arriba, en el gráfico de antigüedad, en las vistas *Por cliente* y *Por
  producto*, ni en el PDF y el Excel: todo parte del mismo listado. Tampoco se
  puede llegar a un documento ajeno por otras vías: registrar un cobro, ver el
  historial o mandar el recordatorio por correo o WhatsApp responde *No tiene
  permiso sobre este registro: no pertenece a su cartera*. En el **envío
  masivo**, los documentos ajenos que hubieran quedado seleccionados simplemente
  se omiten.
- A estos usuarios **el filtro *Vendedor* les queda fijo**: el vendedor ve su
  propio nombre y quien no es vendedor ve *Sin vendedor vinculado*. No pueden
  elegir otro, y el sistema ignora cualquier otro vendedor que se le envíe. En el
  encabezado del Excel y del PDF, *Vendedor* muestra su nombre.

**Cómo sabe el sistema qué vendedor es el usuario.** Se resuelve en este orden:
primero el campo **Usuario del sistema** de la ficha del vendedor (módulo
Vendedores), que es lo que el administrador declaró a mano; si no está, la
**cédula del usuario** contra la identificación del vendedor (también calza si
uno tiene el RUC de persona natural y el otro la cédula). En consolidado por
RUC se busca el vendedor en cada establecimiento. Para quien ve la cartera
completa, el filtro *Vendedor* de la pantalla sirve para acotar y no cambia quién
ve qué.

Dos advertencias para usuarios sin vendedor: los documentos que se **migraron**
desde el sistema anterior quedaron a nombre del usuario que corrió la
migración, así que solo él (o alguien con acceso total) los verá; y un saldo
inicial cargado sin usuario registrado no aparece para nadie que no tenga
acceso total.

## Ordenar el listado

La cartera se abre **ordenada por cliente, de la A a la Z**. Para verla de otra
forma, haga clic en el título de la columna: el primer clic ordena de menor a
mayor (A-Z, la fecha más antigua, el monto más bajo) y volver a hacer clic en la
misma columna invierte el orden. La flecha azul del título indica por cuál
columna está ordenada la tabla y en qué sentido.

Se puede ordenar por **Documento**, **Origen**, **Cliente**, **F.Emisión**,
**F.Vencimiento**, **Total**, **Cobrado**, **Saldo** y **Estado** (por días
vencidos).

- Cuando dos filas coinciden en la columna elegida, aparece primero la de
  vencimiento más próximo.
- Las filas sin dato en esa columna (por ejemplo, un documento sin fecha de
  vencimiento) van siempre al final, se ordene de mayor a menor o al revés.
- Las mayúsculas y las tildes no cambian el orden: *Álvarez* y *ALVAREZ* quedan
  juntos.
- El orden elegido **queda guardado para usted**: la próxima vez que entre al
  módulo, el listado se abre así.
- El **PDF y el Excel salen en el mismo orden** que la pantalla. El PDF se
  genera en **hoja vertical** (A4 retrato), en cualquiera de las vistas.

En las vistas *Por cliente* y *Por producto* las secciones salen siempre en
**orden alfabético** (el cliente o el producto, de la A a la Z), tanto en
pantalla como en el PDF y el Excel; el orden que elija en las cabeceras acomoda
los documentos dentro de cada sección.

## El PDF: logo y filtros del encabezado

El PDF, en cualquiera de las vistas (*Detallado*, *Por cliente* y *Por
producto*), empieza con el **logo de la empresa a la izquierda del nombre**; al
centro van el nombre de la empresa, el título del reporte y la fecha en que se
generó. Es el logo del establecimiento principal (el primero activo, normalmente
el 001), el que se sube en **Empresa**, pestaña **Establecimiento**. Si no hay
logo cargado, el encabezado sale solo con el nombre, centrado.

Debajo va el recuadro de **filtros aplicados**, reducido a lo que acota la
cartera:

- **Vendedor** y **Período** aparecen siempre.
- **Producto** y **Cliente** aparecen solo cuando se filtró por ellos.
- La vista, el alcance (establecimientos), el tipo de documento y el estado no
  se imprimen en el PDF.

El **Excel** conserva la descripción completa de los filtros, incluidos la
vista, el alcance, el tipo de documento y el estado.

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
porque un vendedor dado de baja puede seguir teniendo cartera pendiente. A un
usuario de nivel 1 sin *Acceso total* no le aparece la lista: el filtro queda fijo
en su propio vendedor (ver *Quién ve qué*).

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
- Encuentra igual **sin tildes ni eñe** y por palabras en cualquier orden, como
  el buscador de Cliente.
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

## Vista "Por cliente": la cartera como el mayor de una cuenta

El botón **Por cliente** resume la cartera en **una línea por cliente**: su
identificación, cuántos documentos tiene y sus totales —total, NC, abonos,
retenciones y, sobre todo, el **saldo por cobrar** de ese cliente, en rojo
mientras deba algo—. Al desplegar una línea se lee como el **mayor de una
cuenta contable**: debajo aparecen los documentos de ese cliente. Al final del
listado va la fila **TOTAL GENERAL** con la suma de todos.

- El listado arranca **plegado**: de un vistazo se ve cuánto debe cada cliente.
  Un clic en su línea despliega los documentos y otro la vuelve a plegar; el
  saldo del cliente se sigue viendo en los dos estados.
- El **saldo va junto al nombre**, en una etiqueta roja (o verde si ya no debe
  nada), además de su columna: así se lee de inmediato sin recorrer la fila
  hasta el final.
- Dentro de cada cliente los documentos van en **orden cronológico** por fecha
  de emisión; los clientes se ordenan **alfabéticamente (A-Z)**.
- Cada documento conserva sus acciones normales (cobrar, historial, correo,
  WhatsApp).

**El detalle de cada cliente** no repite las columnas del listado general (el
cliente ya es la cabecera de la sección): muestra **fecha, n. de documento,
total, NC, abonos, retenciones, saldo, días y asesor**.

| Columna | Qué muestra |
|---|---|
| Fecha | Fecha de emisión del documento. |
| N. Documento | Número del comprobante. Los recibos de venta y los saldos iniciales llevan una marca (`REC`, `SI`); las facturas no. En consolidado antecede el código del establecimiento. |
| Total | Valor del documento. Si tuvo **nota de débito**, se indica al lado (`+valor`), porque esa nota suma al saldo. |
| NC | Notas de crédito aplicadas al documento. |
| Abonos | Cobros recibidos (efectivo, banco, tarjeta…). |
| Retenciones | Retenciones que practicó el cliente. |
| Saldo | Lo que falta cobrar: total + ND − NC − abonos − retenciones. En rojo si hay saldo. |
| Días | Días vencidos (en rojo); si aún no vence, muestra `—` y la fecha de vencimiento al pasar el mouse. |
| Asesor | Vendedor asignado al documento. |

- Un cliente cargado dos veces —con la cédula y con el RUC— forma **una sola
  sección** (ver *Un mismo cliente registrado con cédula y con RUC*).

### PDF y Excel de esta vista

Con la vista **Por cliente** activa, los botones **PDF** y **Excel** salen con
esa misma estructura, no como lista plana. A diferencia de la pantalla, en el
archivo **todas las secciones salen desplegadas** (con su detalle), esté como
esté el listado en ese momento:

- **PDF**: cabecera con la identificación, el nombre y el **saldo** del cliente
  (`1715920656001 - ALVAREZ RAZO FABRICIO GABRIEL · saldo: 1,234.56`), la tabla de
  sus documentos con **las mismas columnas de la pantalla** (fecha, n. de
  documento, total, NC, abonos, retenciones, saldo, días y asesor) y, al cierre
  del reporte, el **TOTAL GENERAL**. Cada cliente no lleva fila de subtotal: su
  saldo ya está en la cabecera de la sección. Arriba van el logo, los filtros
  aplicados (ver *El PDF: logo y filtros del encabezado*) y las tarjetas de
  resumen.
- **Excel**: una **sección por cliente** (título con su identificación, nombre y
  saldo, en el mismo formato del PDF), sus documentos con esas mismas columnas —más *Origen* y
  *Estado*, que en una hoja de cálculo no estorban— y el **TOTAL GENERAL** al
  final de la hoja. El cliente no va como columna: es el título de la sección,
  igual que la cuenta en el mayor.
- En **consolidado por RUC** ambos archivos agregan la columna **Estab.** con el
  establecimiento dueño de cada documento.
- Para la lista plana de siempre (una fila por documento, con cliente y
  RUC/cédula como columnas) se exporta desde la vista **Detallado**.

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
- En el **Excel** el encabezado indica *Alcance: Consolidado por RUC* con la
  lista de establecimientos (el PDF no imprime el alcance), y en los dos
  archivos se agrega la columna **Estab.**

## Un mismo cliente registrado con cédula y con RUC

Es habitual que el mismo cliente esté cargado **dos veces**: una ficha con la
**cédula** (10 dígitos) y otra con el **RUC** (13 dígitos), que en las personas
naturales es esa misma cédula seguida de **001**. Por ejemplo
`1717136574` y `1717136574001`. Son dos filas distintas en `clientes`, cada una con
sus propios documentos, aunque para efectos prácticos sean la misma persona.

Cuentas por cobrar los trata como **uno solo**:

- El **buscador de cliente** muestra **una sola entrada**, no dos. Se puede escribir
  la cédula o el RUC: en ambos casos aparece la misma opción (se muestra la ficha
  con el RUC, que es la identificación completa).
- Al elegirla, el listado trae los documentos de **las dos fichas**, y los totales
  de arriba suman las dos.
- La vista **Agrupado por cliente** los junta en **una sola tarjeta**, con su
  saldo total, en vez de dos tarjetas con la deuda partida.
- En el **envío masivo de recordatorios** se manda **un solo correo** con todos sus
  documentos, no dos correos con la mitad cada uno.

Esto es solo de consulta: **no se fusionan ni se modifican las fichas**, y cada
documento sigue perteneciendo a la ficha con la que se emitió. Si quiere dejar
una sola ficha de verdad, hay que hacerlo en el módulo de Clientes.

**Qué NO se agrupa**: solo se cruzan la cédula de 10 dígitos y su RUC terminado
en 001. Un RUC de sucursal (…002, …003), el consumidor final
(`9999999999999`), un pasaporte o cualquier otra identificación se comportan
como siempre: cada ficha por su lado. Las fichas **sin identificación** tampoco
se agrupan entre sí.

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
- **No veo las facturas de otro vendedor**: un usuario de nivel 1 sin el permiso
  de *acceso total* ve solo lo de su vendedor (documentos a su nombre y, sin
  vendedor, los de sus clientes) si es vendedor, y solo lo que él registró si no
  lo es (ver *Quién ve qué: nivel del usuario y permiso de Acceso total*). Si un
  vendedor no ve nada, revise que su ficha tenga el *Usuario del sistema* o la
  misma cédula que su usuario, y que los documentos lleven su nombre. Si faltan
  documentos **antiguos** a un usuario sin vendedor, suele ser porque se migraron
  a nombre de quien corrió la migración.
- **Un vendedor no ve una factura de su cliente**: si la factura lleva el nombre
  de otro vendedor, es de ese vendedor y no le aparece, aunque el cliente esté
  asignado a él.
- **Un vendedor ve la cartera de todos**: revise el nivel de su usuario (los
  niveles 2 y 3 ven la cartera completa) y que no tenga marcado *Acceso total*
  en este módulo.
- **Una serie no aparece en el modal de cobro**: está **inactiva**. Solo se
  ofrecen los puntos de emisión activos; actívela en Empresa, pestaña Puntos de
  Emisión (ver *Serie del cobro: solo puntos de emisión activos*).

## Historial de cambios

- **2.14** — Los **administradores (nivel 2)** ven la cartera completa aunque no
  tengan marcado *Acceso total*, igual que el superadministrador, y pueden
  cobrar, ver el historial y notificar cualquier documento. El permiso sigue
  decidiendo solo para los usuarios de nivel 1. El vendedor sin acceso total pasa
  a ver **solo lo de su vendedor**: los documentos a su nombre y, si no tienen
  vendedor, los de sus clientes asignados; ya no ve ni puede cobrar los documentos
  de sus clientes emitidos a nombre de otro vendedor. A estos usuarios el **filtro
  Vendedor** les aparece fijo en su nombre, también en el encabezado del Excel y
  del PDF. Es la misma regla del Reporte de Ventas y del Reporte de Ventas por
  Vendedor, que ahora comparten los tres módulos. Sección *Quién ve qué*
  actualizada.
- **2.13** — El **PDF** (en sus tres vistas) lleva el **logo de la empresa a la
  izquierda del nombre**. Su recuadro de filtros aplicados se acorta: ya no
  muestra la vista, el alcance, el tipo de documento ni el estado, y *Producto*
  y *Cliente* salen solo cuando se filtró por ellos. El Excel no cambia. Nueva
  sección *El PDF: logo y filtros del encabezado*.
- **2.12** — El permiso de **Acceso total** ahora se aplica **por vendedor**: sin
  él, el vendedor vinculado al usuario (campo *Usuario del sistema* de su ficha o
  la misma cédula) ve la cartera de sus **clientes asignados** y los documentos
  emitidos a su nombre, en la tabla, las tarjetas, la antigüedad, las vistas
  agrupadas, el PDF, el Excel, el cobro, el historial y los envíos. Quien no es
  vendedor sigue viendo solo lo que él registró. Antes (2.9) se miraba solo quién
  registró el documento, así que un asesor no veía las facturas de sus clientes
  hechas por caja. Sección *Quién ve qué* actualizada.
- **2.11** — Se quitó la fila **SUBTOTAL** de cada cliente en la vista *Por
  cliente*: su saldo ya aparece en la línea del cliente y en la cabecera de la
  sección del PDF y el Excel. El **TOTAL GENERAL** del listado se mantiene.
- **2.10** — En el PDF y el Excel de la vista *Por cliente*, la cabecera de cada
  sección muestra el **saldo** en lugar del número de documentos:
  `1715920656001 - ALVAREZ RAZO FABRICIO GABRIEL · saldo: 1,234.56`.
- **2.9** — El módulo respeta el permiso de **Acceso total**: quien no lo tiene
  ve solo los documentos que él registró, tanto en la tabla como en las tarjetas,
  el gráfico de antigüedad, las vistas agrupadas y las exportaciones, y ya no
  puede cobrar, consultar el historial ni notificar documentos de otro usuario.
  Antes el permiso no cambiaba nada: cualquiera con permiso de ver la cartera la
  veía completa. Nueva sección *Quién ve qué: el permiso de Acceso total*.
- **2.8** — En la vista *Por cliente* (y en la de *Por producto*) las secciones
  salen ahora en **orden alfabético**, no por saldo, tanto en pantalla como en
  el PDF y el Excel. La línea de cada cliente muestra además su **saldo junto al
  nombre**, en una etiqueta de color. El **PDF sale en hoja vertical** (antes
  horizontal) en las tres vistas.
- **2.7** — El listado se abre **ordenado por cliente de la A a la Z** (antes
  salía por fecha de vencimiento) y ahora se puede **ordenar por cualquier
  columna** haciendo clic en su título, como en el Reporte de Ventas. El orden
  elegido queda guardado para el usuario y **el PDF y el Excel salen con ese
  mismo orden**. Nueva sección *Ordenar el listado*.
- **2.6** — El **detalle de cada cliente** (vista *Por cliente*) pasa a mostrar
  **fecha, n. de documento, total, NC, abonos, retenciones, saldo, días y
  asesor**, en vez de repetir las columnas del listado general; el **PDF** y el
  **Excel** de esa vista salen con las mismas columnas. De paso se corrigió que
  las fechas del listado se mostraban **un día antes** del real (se interpretaban
  en horario UTC).
- **2.5** — La vista **Por cliente** ahora se lee como el **mayor de una
  cuenta**: cada cliente es una línea con su **saldo por cobrar** y, al
  desplegarla, aparecen sus documentos cerrados con una fila de **SUBTOTAL**;
  al final del listado, el **TOTAL GENERAL**. El **PDF** y el **Excel** de esa
  vista salen con la misma estructura (antes salían siempre como lista plana,
  y en pantalla la agrupación no mostraba subtotales ni total general). Además,
  al entrar al módulo **ya no se carga nada**: el listado se consulta al
  presionar **Aplicar**. Nuevas secciones *El listado se consulta al presionar
  Aplicar* y *Vista "Por cliente": la cartera como el mayor de una cuenta*.
- **2.4** — El buscador de **Cliente** (y el de **Producto**) ya no distingue
  tildes ni eñe —`PENA` encuentra a *MENDOZA PEÑA*, `COMPANIA` a *COMPAÑIA*— y
  busca por palabras sueltas en cualquier orden, igual que el resto de
  buscadores del sistema. Antes exigía escribir el texto exacto, con sus tildes
  y en el mismo orden. Nueva sección *Buscar el cliente: tildes, ñ y varias
  palabras*.
- **2.3** — Un mismo cliente cargado **dos veces** —una ficha con la cédula y otra con el
  RUC, que es esa cédula + `001`— deja de aparecer partido en dos: el buscador
  muestra una sola entrada, el listado trae los documentos de las dos fichas, la
  vista agrupada las junta en una tarjeta y el envío masivo manda un solo correo.
  Nueva sección *Un mismo cliente registrado con cédula y con RUC*.
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
