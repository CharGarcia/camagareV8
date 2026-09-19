---
titulo: Ingresos
resumen: Registro del dinero que entra: cobros de facturas, anticipos y otros ingresos, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/ingresos
tipo: modulo
visibilidad: todos
etiquetas: ingresos, cobro, cobrar, buscar ingreso, buscador, filtros, filtrar ingresos, filtrar por forma de cobro, buscar por factura cobrada, buscar por cheque, buscar por transferencia, filtro de fechas, chips, editar ingreso, modificar ingreso, corregir ingreso, cambiar monto cobrado, quitar factura del ingreso, periodo cerrado, solo lectura, no deja editar, no puedo modificar, ordenar por dos columnas, ordenar por recibi de y fecha, recibo, dinero que entra, anticipo, deposito, efectivo, transferencia, caja, excel, exportar, combinar conceptos, mezclar conceptos, otros conceptos, varios documentos, cobro sin factura, tipo real, tipo de ingreso, numero de ingreso, serie, secuencial, numero repetido, numero duplicado, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, orden de formas de cobro, saldo de la forma de cobro, saldo disponible, ocultar saldo, aparecen documentos que no busque, resultados que no corresponden, buscar por numero de documento cobrado, cuenta del anticipo, anticipo sin cuenta, cuenta contable del concepto, cuenta por defecto, falta cuenta contable, cobrar factura y dejar anticipo, excedente como anticipo, listado no se actualiza, no aparece el ingreso guardado, no se ve el cambio, vuelve a la primera pagina, se pierde la pagina, refrescar listado, recargar tabla, fila resaltada, observaciones automaticas, observaciones se llenan solas, observaciones se completan solas, glosa del ingreso, concepto del comprobante, descripcion del cobro, cobro factura de venta
version: 2.9
orden: 10
estado: activo
---

El módulo de **Ingresos** registra todo el dinero que entra a la empresa: el
cobro de una factura, un anticipo de cliente o cualquier otro ingreso. Cada
ingreso genera su asiento contable y, cuando cobra documentos, reduce las cuentas
por cobrar.

## Las tres partes de un ingreso

Un ingreso siempre tiene tres partes y **las tres deben cuadrar entre sí**:

1. **Cabecera**: fecha, secuencial, tipo de ingreso y de quién se recibe.
2. **Detalle**: qué se está cobrando (documentos pendientes o conceptos libres).
3. **Formas de cobro**: cómo entró el dinero (efectivo, transferencia, cheque…).

La suma del detalle y la suma de las formas de cobro deben ser **iguales al total
del ingreso**. Si no cuadran, el sistema no deja guardar y dice exactamente qué
suma no coincide.

## Cómo se registra un cobro

1. Pulse **Nuevo**.
2. Revise la fecha y el secuencial (se propone el siguiente).
3. Elija el **tipo de ingreso** y complete **Recibo de** (de quién viene el dinero).
4. En el detalle, busque los **documentos pendientes** del cliente y marque los
   que está cobrando, total o parcialmente.
5. En formas de cobro, indique cómo entró el dinero. Puede combinar varias.
6. Guarde.

Si es un ingreso que no cobra ninguna factura, elija el **concepto** que
corresponda en lugar de documentos pendientes.

La lista de **formas de cobro** sigue el **Orden** configurado en
[Formas de cobro y pago](formas-cobros-pagos.md) (las que no tienen orden van al
final, por nombre), y el saldo de cada forma se ve junto a su nombre solo si allí
tiene marcado **Mostrar saldo**.

## Combinar varios conceptos en un mismo ingreso

Un ingreso puede cobrar **a la vez** una factura de venta, un recibo de venta
y/o "Otros conceptos" (dinero recibido sin documento) — no hace falta un
ingreso separado por cada tipo. Los botones de concepto de la barra superior
ya no son excluyentes: cada uno **agrega** documentos a lo ya cargado, en vez
de reemplazarlo.

- **"Documentos"** y **"Otros conceptos"** se muestran siempre juntos: use el
  buscador de documentos pendientes para las facturas/recibos, y el botón
  **"+ Agregar línea"** de "Otros conceptos" para el resto.
- **Cuenta contable obligatoria por línea manual cuando se mezcla**: si el
  ingreso combina un documento de módulo con líneas de "Otros conceptos", cada
  línea manual debe traer su propia cuenta contable (buscador integrado en esa
  misma cuadrícula). Sin eso, el sistema no sabe si ese ingreso es parte de la
  cartera del documento (p. ej. Cuentas por Cobrar de la factura) o una cuenta
  totalmente distinta, así que lo exige explícito antes de guardar.
- El total del ingreso es la suma de **ambos bloques**.

### Botones que solo aparecen si hay algo que cobrar

Los botones de concepto ligados a un documento (**Factura de venta**,
**Recibo de venta**, **Factura de reembolso**) solo se muestran si la empresa
tiene al menos un documento pendiente de ese tipo. Si no hay ninguna factura,
recibo o reembolso pendiente, el botón correspondiente no aparece.

Los demás conceptos (los que no dependen de buscar un documento, como
**Anticipo Cliente**, o cualquiera del desplegable "Otro concepto…")
**siempre se muestran**, sin importar si hay pendientes o no.

### La columna "Tipo" del listado muestra el tipo real, no el botón usado

La columna **Tipo** del listado muestra el tipo **real** de lo que
efectivamente se cobró (Factura de Venta, Recibo de Venta, Factura de
Reembolso), calculado a partir de los documentos del detalle — no el botón de
concepto que se usó para armarlo. Si el ingreso combina más de un tipo (ver
"Combinar varios conceptos" arriba), la columna los junta con `+`. Si es un
concepto sin documento (Anticipo Cliente, Préstamos…), muestra directamente el
nombre del concepto elegido.

## Cuenta contable de las líneas de "Otros conceptos"

Cada línea de "Otros conceptos" lleva su cuenta contable, y el sistema la propone
sola a partir del **concepto** que se pulsa: es la cuenta que ese concepto tiene
en *Configuración Contable → Ingresos y Egresos* (la misma que se ve en
*Opciones de Ingreso/Egreso* y la que usa el asiento).

- **Anticipo Cliente** pone su cuenta aunque ya haya facturas o recibos
  cargados: así se cobra una factura y el excedente queda como anticipo en la
  misma operación, sin buscar la cuenta a mano. Si no queda ninguna línea, el
  botón agrega una.
- La cuenta propuesta llega a las líneas que no tienen cuenta y a las que siguen
  en blanco (sin descripción ni monto). **Nunca cambia** una cuenta elegida a
  mano en el buscador, ni la de una línea ya escrita con otro concepto.
- **Factura de venta** y **Recibo de venta** no proponen cuenta: la suya es
  Cuentas por Cobrar, que el asiento toma del propio documento. Con documentos
  ya cargados tampoco la propone **Factura de reembolso**. En esos casos, la
  línea sin documento necesita que se elija la cuenta.
- Si un concepto no propone ninguna cuenta, revise que la tenga asignada en
  *Configuración Contable → Ingresos y Egresos*.

## Observaciones que se completan solas

Mientras arma un ingreso **nuevo**, el campo **Observaciones Generales** se va
llenando solo con lo que se carga en el detalle, sin escribir nada:

| Lo que se carga | Texto en Observaciones |
|-----------------|------------------------|
| Una factura de venta | `Cobro factura de venta 001-001-000000001` |
| Varias facturas de venta | `Cobro facturas de venta 001-001-000000001, 001-001-000000002` |
| Una factura y un recibo | `Cobro factura de venta 001-001-000000001; recibo de venta 001-002-000000004` |
| Una factura de reembolso | `Cobro factura de reembolso 001-001-000000009` |
| Un saldo inicial | `Cobro saldo inicial` y el número del documento |
| Una línea de "Otros conceptos" | Su descripción, tal como se escribe |

- Si quita un documento, lo desmarca o deja su monto en cero, sale del texto:
  ya no se va a cobrar.
- **Si escribe su propio texto, ese manda**: desde ese momento el campo no se
  vuelve a tocar, aunque agregue o quite documentos. Si lo borra por completo,
  se vuelve a llenar solo con el siguiente documento o línea que cargue.
- Al **editar** un ingreso guardado, las observaciones siguen actualizándose
  solas únicamente si son el texto automático tal cual. Un texto escrito a mano,
  o un ingreso antiguo sin observaciones, no se cambia.

Es el texto que se ve en la columna *Observaciones* del listado y como
*Concepto* en el comprobante PDF y Excel.

## Campos obligatorios

| Campo | Regla |
|-------|-------|
| Fecha de emisión | Obligatoria |
| Secuencial | Obligatorio |
| Tipo de ingreso | Obligatorio |
| Recibo de | Obligatorio |
| Concepto | Obligatorio cuando es "otros ingresos" |
| Detalle | Al menos una línea, con monto mayor a cero |
| Formas de cobro | Al menos una, con monto mayor a cero |
| Total | Mayor a cero |

## Editar un ingreso ya guardado

Al abrir un ingreso desde el listado, el modal se titula **Editar Ingreso** y
permite corregir prácticamente todo, sea cual sea su tipo (cobro de factura,
recibo de venta, reembolso, anticipo u otro concepto):

- La **fecha de emisión** y el campo **Recibo de**.
- Las **observaciones** (ver *Observaciones que se completan solas*).
- Los **documentos cobrados**: quitar uno, cambiar el monto cobrado o agregar
  otro documento pendiente del mismo tipo (el botón del concepto activo en la
  barra superior vuelve a abrir el buscador). Al editar, el buscador y los
  saldos ya descuentan lo que este mismo ingreso cobraba, así que el saldo
  disponible es el real.
- Las líneas de **Otros conceptos**, con su cuenta contable.
- Las **formas de cobro**.

Lo único que no cambia es la **identidad del documento**: la serie, el
secuencial y el concepto de cabecera (el que define el tipo del ingreso y su
asiento). Si el concepto está mal, anule el ingreso y registre uno nuevo.

Al pulsar **Actualizar** el sistema vuelve a validar el cuadre, el saldo real de
cada documento (por si otro ingreso lo cobró mientras tanto), el periodo
contable y regenera el asiento contable con los datos nuevos. El cambio queda en
el historial de auditoría con los datos anteriores y los nuevos.

## Qué pasa en el listado al guardar

Al pulsar **Guardar** (ingreso nuevo) o **Actualizar** (ingreso editado), el
modal se cierra y el listado se actualiza **sin moverse de donde estaba**: sigue
en la misma página, con el mismo texto de búsqueda, los mismos filtros y el mismo
orden. La fila del ingreso guardado se resalta en verde unos segundos, ya con los
datos nuevos (fecha, Recibo de, observaciones, monto y tipo).

Si ese ingreso no cae en la página que se está viendo —por ejemplo, uno nuevo
mientras se ve la página 3, o uno que ya no cumple el filtro activo—, se muestra
igual arriba de todo para que se vea qué se guardó. Al buscar, filtrar o cambiar
de página vuelve a su lugar.

## El periodo contable manda

No se puede **registrar, modificar ni anular** un ingreso si su periodo contable
está cerrado. El sistema lo comprueba en las tres operaciones, y en la
modificación valida tanto el periodo original como el nuevo si se cambia la
fecha.

Cuando el periodo de la fecha del ingreso ya está cerrado, el modal **abre en
solo lectura**: muestra la etiqueta **PERIODO CERRADO** junto al número y un
aviso amarillo con el motivo, todos los campos quedan bloqueados y no aparecen
los botones **Actualizar** ni **Anular**. Así el bloqueo se ve al abrir, y no
recién al intentar guardar.

Si necesita corregir un ingreso de un periodo cerrado, hay que reabrir el periodo
desde Periodos Contables (con el criterio del contador) o registrar el ajuste en
un periodo abierto.

## Anular, no eliminar

Un ingreso registrado **se anula**, no se borra. Al anularlo:

- Se libera el saldo de los documentos que había cobrado.
- Se anula su asiento contable.

**Caso especial — pagos con tarjeta**: si el ingreso vino de un cobro con
tarjeta, no se puede anular desde aquí. Primero hay que **reversar el pago desde
la factura, en la pestaña Pagos**; al hacerlo, el ingreso se anula solo. El
sistema lo avisa con ese mensaje si lo intenta al revés.

## El número del documento

Cada documento lleva una **serie** (establecimiento y punto de emisión, por
ejemplo `001-101`) y un **secuencial**, que juntos forman el Nº de documento:
`001-101-000000123`.

El número que se ve al abrir el formulario es solo una **vista previa**: el
definitivo lo asigna el sistema en el momento de guardar, tomando el siguiente
libre de esa serie. Por eso, si dos personas abren el formulario a la vez, el
segundo en guardar recibe el número siguiente y no el mismo — el documento no
se pierde ni se rechaza, simplemente sale con el número que le toca.

Si se elimina un documento, su número queda libre y el sistema lo vuelve a
ofrecer al siguiente que se cree en esa serie, para que la numeración no
quede con saltos.

### Numeración por fecha de emisión

Por defecto el número es un **correlativo corrido** que nunca se reinicia
(`000000017`). En **Empresa → Secuenciales** se puede configurar, para este tipo
de documento y por cada punto de emisión, que el correlativo **vuelva a empezar
en cada periodo** según la **fecha de emisión** del documento:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Cuando ese modo está activo, **al cambiar la fecha del documento su número se
recalcula solo**, para que caiga en el periodo correcto. Una vez guardado, el
número queda fijo aunque después se le cambie la fecha. Los documentos ya
emitidos conservan siempre el número que tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y
cuántos documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Asiento contable

Cada ingreso genera su asiento automáticamente según la configuración contable de
la empresa. Al modificarlo, el asiento se regenera; al anularlo, se anula.

Las líneas de "Otros conceptos" van al asiento con la cuenta de cada línea: la
que propone el concepto o la que se elija a mano (ver *Cuenta contable de las
líneas de "Otros conceptos"*).

## Buscar y filtrar el listado

Arriba de la tabla hay dos piezas: el botón **Filtros** y el cuadro de búsqueda.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del ingreso: número, serie,
secuencial, fecha, "Recibo de" (sea el texto escrito, el cliente o el concepto),
observaciones y monto. Puede escribir varias palabras en cualquier orden
(`perez mayo`) y no importan mayúsculas ni tildes. Para limpiar, borre el texto o
pulse Escape en el cuadro.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** El cuadro devuelve
solo ingresos donde se vea por qué coinciden; el resto se consulta en la ventana
de filtros:

| Dato | Dónde se busca |
|------|----------------|
| N° de las facturas o recibos cobrados | Pestaña *Detalles* |
| Identificación / RUC del cliente | Pestaña *Ingreso* → **RUC / cédula** |
| Usuario que registró | Pestaña *Ingreso* |
| Tipo y Estado | Pestaña *Ingreso* |

La pestaña *Detalles* busca dentro de los ingresos —documentos cobrados, su
descripción, montos y cuenta contable, y también las formas de cobro con su
referencia, cheque y operación bancaria— y muestra **qué línea coincidió**,
mientras que desde el cuadro el ingreso aparecía sin que se viera el motivo.
Mientras busca, aparece un **círculo girando** al final del cuadro; cuando desaparece, el listado ya muestra el resultado. Mientras tanto la tabla se ve atenuada, también al cambiar de página o de orden.

**Filtros.** Pulse **Filtros** para abrir la ventana con todos los criterios,
repartidos en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada
se aplica hasta ese momento, así que puede combinar varios sin que la tabla se
recargue a cada paso. Cada pestaña muestra cuántos filtros suyos están activos.

**Pestaña Ingreso** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado, tipo de ingreso, serie, Nº de ingreso, secuencial, monto (mínimo y máximo), concepto de ingreso, con o sin asiento contable, usuario que registró |
| Tercero | Recibo de, cliente, RUC / cédula, observaciones |

**Pestaña Detalles** (lo que hay dentro del ingreso). Es un único cuadro,
**Buscar libremente dentro de los ingresos**: escriba un número de factura o
recibo, una descripción, una cuenta contable, una forma de cobro, una
referencia, un número de cheque, una operación bancaria o un monto, y aparece
la lista de **cada línea o pago que coincide** con el ingreso al que pertenece
(número, fecha, "Recibo de" y estado). Un clic en la fila deja el listado
mostrando solo ese ingreso; el ícono de la derecha lo abre directamente. Por
ejemplo, *transferencia* lista todos los pagos por transferencia y en qué
ingreso están, y *001-001-000000011* muestra en qué ingreso se cobró esa
factura.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**, delante del texto que escribe. La **×** de la etiqueta quita solo
ese filtro, y con el cuadro vacío la tecla **Retroceso** quita el último. Pulsar
la etiqueta vuelve a abrir la ventana para ajustarla. El botón del embudo
muestra cuántos hay activos, y el botón **Limpiar filtros** de la ventana los
borra todos de una vez.

La búsqueda libre y los filtros se combinan entre sí, y los botones **PDF** y
**Excel** del listado exportan exactamente lo que se ve.

## Ordenar el listado

Pulse el título de una columna para ordenar por ella y vuelva a pulsarlo para
invertir el sentido. De fábrica el listado muestra **lo más reciente primero**.

Puede **encadenar hasta tres columnas**: mantenga presionada la tecla **Shift**
(⇧) y pulse el título de la segunda. Usos típicos en cobros:

| Para ver… | Ordene así |
|-----------|-----------|
| Todo lo cobrado a cada persona, en orden de fecha | *Recibí de*, luego Shift+clic en *Fecha* |
| Los cobros más altos dentro de cada tipo | *Tipo*, luego Shift+clic en *Monto* |
| Lo anulado y lo vigente por separado, por fecha | *Estado*, luego Shift+clic en *Fecha* |

El número pequeño junto a cada flecha indica qué columna manda (`1`) y cuál
desempata (`2`). Un tercer Shift+clic sobre la misma columna la saca del orden, y
un clic normal en cualquier encabezado vuelve a dejar una sola.

La columna **Recibí de** se ordena por lo que se ve: el texto libre si lo hay y,
si no, el nombre del cliente.

El orden se guarda para usted y los botones de PDF y Excel del listado exportan
con ese mismo orden. Detalles en *Cómo ordenar los listados*.

## Comprobante en PDF y Excel

Al abrir un ingreso ya guardado, la barra de acciones superior del modal
muestra el botón **PDF** (comprobante de ingreso) y, junto a él, el botón
**Excel**: descarga el mismo comprobante (cabecera, documentos cobrados y
formas de cobro) en un archivo `.xlsx`. Ambos botones quedan ocultos mientras
el ingreso es nuevo y no se ha guardado.

## Permisos

Con **acceso total** se ven los ingresos de toda la empresa; sin él, cada usuario
ve solo los que registró. En una caja con varios turnos esto suele ser lo
deseable; para el contador o el administrador, active el acceso total.

## Errores frecuentes

- **"La suma de los detalles no coincide con el total"**: revise las líneas del
  detalle; suele faltar un documento o sobrar un centavo por redondeo.
- **"La suma de las formas de cobro no coincide con el total"**: el dinero
  declarado no llega al total cobrado. Ajuste los montos por forma de pago.
- **"El periodo contable está cerrado"**: la fecha cae en un mes ya cerrado.
- **"Debes reversar el pago con tarjeta primero"**: vaya a la factura, pestaña
  Pagos, y reverse ahí.
- **No encuentro la factura a cobrar**: compruebe que está a nombre de ese
  cliente, que no está ya cobrada y que no fue anulada.
- **"Falta cuenta contable"**: el ingreso mezcla facturas o recibos con una línea
  de "Otros conceptos" que no tiene cuenta. Elíjala en la columna *Cuenta
  contable* de esa línea; si es un anticipo, pulse **Anticipo Cliente** y se pone
  sola.

## Historial de cambios

- **2.9** — **Observaciones automáticas**: al registrar un ingreso,
  *Observaciones Generales* se llena sola con lo que se va cargando
  (`Cobro factura de venta 001-001-000000001`, recibos, reembolsos, saldos
  iniciales y las descripciones de "Otros conceptos"). Si el usuario escribe su
  propio texto, el sistema ya no lo cambia. Nueva sección *Observaciones que se
  completan solas*.

- **2.8** — Al guardar un ingreso nuevo o editado, el listado se actualiza en la
  misma página, con la búsqueda, los filtros y el orden que tenía, y resalta la
  fila del ingreso guardado con sus datos nuevos. Antes volvía siempre a la
  primera página, así que un ingreso editado en otra página (o con el listado
  ordenado por otra columna) no se veía actualizado. Nueva sección *Qué pasa en
  el listado al guardar*.

- **2.7** — Al pulsar **Anticipo Cliente**, las líneas de "Otros conceptos" toman
  la cuenta del anticipo aunque ya haya facturas o recibos cargados (antes
  quedaban sin cuenta y el guardado la exigía). La cuenta propuesta es la de
  *Configuración Contable → Ingresos y Egresos*: antes solo se leía la del módulo
  de Opciones, así que una cuenta asignada únicamente allá no aparecía. También
  reemplaza la que otro concepto haya dejado en una línea aún en blanco, sin tocar
  las elegidas a mano. **Factura de venta** y **Recibo de venta** ya no proponen
  su cuenta de cartera en esas líneas, y un concepto creado desde el propio modal
  propone su cuenta sin recargar la página.

- **2.6** — El cuadro encuentra el ingreso escribiendo su número completo `001-001-000000001`, aunque el
  documento tenga el número guardado sin la serie o con el secuencial sin los ceros de
  la izquierda (pasa en registros antiguos y migrados): el listado lo arma a partir de
  la serie y el secuencial.

- **2.5** — El cuadro de búsqueda del listado queda para lo que se ve en la tabla:
  número, serie, secuencial, fecha, "Recibo de", observaciones y monto. Los **números de
  las facturas o recibos cobrados** pasan a la pestaña *Detalles* del modal de filtros,
  que además muestra qué línea coincidió; la **identificación del cliente** y el
  **usuario que registró** se consultan en sus filtros. Antes el ingreso aparecía en la
  lista sin que se viera el motivo.

- **2.4** — La lista de **formas de cobro** respeta el **Orden** definido en
  *Formas de cobro y pago* (antes era siempre alfabética) y muestra el saldo solo
  de las formas que tienen marcado **Mostrar saldo**. Sin configurar nada, se ve
  igual que antes.
- **2.3** — **Búsqueda del listado más rápida**: el conteo y la página salen
  de una sola consulta y el tipo de cada ingreso (según sus documentos cobrados) se calcula solo para los 20 visibles. Las fechas y los montos solo se comparan
  cuando lo escrito tiene números, así que buscar un nombre o un producto responde
  antes. Si se sigue escribiendo, la búsqueda anterior se cancela. Los resultados
  son los mismos que antes.
- **2.2** — Nuevo buscador del listado: el cuadro de búsqueda ya no despliega
  sugerencias; lo que se escribe se busca **en todas las columnas** (incluidos
  los números de los documentos cobrados y el usuario que registró). Los
  filtros pasan a una **ventana propia** (botón *Filtros*, se aplican con
  *Aplicar*) con dos pestañas: **Ingreso** (filtros por campo de la cabecera,
  con criterios nuevos como concepto, con/sin asiento y
  usuario) y **Detalles**, un cuadro de **búsqueda libre dentro de los
  ingresos** (documentos cobrados y formas de cobro) que lista cada línea o
  pago que coincide y dice a qué ingreso pertenece. Los filtros activos se ven
  como etiquetas junto al cuadro. Nueva sección *Buscar y filtrar el listado*.
- **2.1** — Al abrir un ingreso guardado con una **serie que ya no se usa para
  emitir** (por ejemplo, migrados con `001-001` cuando la empresa ya trabaja con
  `001-101`, o un punto de emisión desactivado), el campo *Serie* mostraba en
  blanco; ahora muestra la serie original del documento. Además, al pulsar
  *Actualizar* el servidor conserva siempre la serie y el secuencial con que se
  guardó el ingreso (antes podía reescribirlos con la primera serie del combo).
- **2.0** — **Edición completa** de un ingreso guardado, sea cual sea su tipo:
  además de fecha, "Recibo de" y formas de cobro, ahora se pueden quitar o
  agregar documentos cobrados, cambiar sus montos, editar las observaciones y
  las líneas de "Otros conceptos" (antes, los ingresos que cobraban facturas
  tenían los documentos y las observaciones bloqueados). La única puerta es el
  **periodo contable**: si está cerrado, el modal abre en **solo lectura** con
  la etiqueta *PERIODO CERRADO* y un aviso del motivo, sin botones Actualizar
  ni Anular. Nueva sección *Editar un ingreso ya guardado*.
- **1.9** — El listado se puede **ordenar por hasta tres columnas a la vez**:
  Shift+clic en el título de la segunda columna la encadena a la primera (por
  ejemplo *Recibí de* y, dentro de cada uno, la *Fecha*). Cada encabezado activo
  muestra un número con su prioridad. El orden se guarda por usuario y se respeta
  al exportar a PDF y Excel. Nueva sección *Ordenar el listado*.
- **1.8** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.7** — El saldo pendiente de las facturas en el buscador de documentos usa
  la misma regla que Cuentas por Cobrar: una retención que sustenta varias
  facturas reparte lo retenido por línea (antes restaba su total a cada una), y
  las retenciones y notas de crédito/débito se enlazan a la factura comparando
  el número normalizado (sin guiones y con ceros a la izquierda).
- **1.6** — Corregido un caso en que el número seguía repitiéndose pese a la
  corrección anterior: los ingresos creados **automáticamente** (cobro con
  tarjeta al facturar, cobro de suscripciones) guardaban el secuencial sin los
  ceros a la izquierda, y la validación que impide repetir un número compara el
  texto, así que `16` y `000000016` pasaban como si fueran distintos. Ahora el
  formato lo fija el sistema al escribir, venga el ingreso del formulario o de
  un cobro automático.
- **1.5** — Se documenta cómo se asigna el **número del documento**: la serie
  y el secuencial definitivos los pone el sistema al guardar, no al abrir el
  formulario, así que dos ingresos creados a la vez ya no pueden salir con el
  mismo número.

- **1.4** — La columna "Tipo" del listado muestra el tipo real del documento
  (Factura de Venta, Recibo de Venta, Factura de Reembolso) en vez de repetir
  siempre el `tipo_ingreso` de cabecera, que podía no coincidir con lo
  realmente cobrado.
- **1.3** — Los botones de concepto ligados a documento (Factura de venta,
  Recibo de venta, Factura de reembolso) solo se muestran si hay algún
  pendiente de ese tipo en la empresa. Se quitó el botón "Agregar documentos"
  (redundante con los botones de concepto de la barra superior).
- **1.2** — Los conceptos del ingreso (Factura de venta, Recibo de venta, Otros
  conceptos...) dejan de ser excluyentes: se pueden combinar en un mismo
  ingreso (p. ej. el cobro de una factura + un ingreso sin documento). Exige
  cuenta contable explícita en las líneas manuales cuando se mezclan con un
  documento de módulo.
- **1.1** — Botón para exportar el comprobante a Excel, junto al de PDF, en la
  barra de acciones superior del modal.
- **1.0** — Versión inicial.
