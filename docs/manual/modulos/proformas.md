---
titulo: Proformas
resumen: Cotizaciones al cliente y su conversión en factura de venta.
categoria: Ventas
ruta_modulo: modulos/proformas
tipo: modulo
visibilidad: todos
etiquetas: proforma, proformas, ordenar por dos columnas, ordenar por estado y fecha, cotizacion, cotizar, presupuesto, oferta, duplicar, duplicar proforma, copiar proforma, repetir cotizacion, volver a cotizar, regresar a borrador, volver a borrador, reabrir proforma, reabrir, desaprobar, quitar aprobacion, editar proforma aprobada, convertir a factura, enviar a pedidos, generar pedido, pasar a pedido, crear pedido desde proforma, despacho, orden de despacho, items sin producto, concepto libre, linea sin producto, pestana pedidos, enviar por whatsapp, exportar excel, info productos, ficha de productos, catalogo, imagenes de productos, informacion adicional, plantillas, plantilla de proforma, guardar como plantilla, condiciones, terminos y condiciones, anexo, pdf de condiciones, texto con formato, clausulas, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, pdf de la proforma, codigo del producto en el pdf, columna codigo, observaciones en el pdf, numero repetido, secuencial repetido, secuencial duplicado, dos proformas con el mismo numero, buscar proforma, buscador, filtros, filtrar proformas, buscar por producto, proformas vencidas, proformas sin facturar, filtro de fechas, chips
version: 1.15
orden: 15
estado: activo
---

Una **proforma** es la cotización que se entrega al cliente antes de vender. No
tiene efecto tributario ni contable: no se envía al SRI, no mueve inventario y no
genera cuentas por cobrar. Cuando el cliente acepta, se convierte en factura con
un clic.

## Estados y su recorrido

Una proforma pasa por estos estados, y el sistema controla el orden:

| Desde | Puede pasar a |
|-------|---------------|
| Borrador | Aprobada, Anulada |
| Aprobada | Rechazada, Anulada, **Borrador** (solo administradores, ver *Regresar a borrador*) |

Cualquier otro salto se rechaza. El significado de cada uno:

- **Borrador**: se está preparando. **Es el único estado en el que se puede editar.**
- **Aprobada**: el cliente la aceptó. Ya se puede facturar.
- **Rechazada**: el cliente no la tomó. Queda como historial.
- **Anulada**: se descarta.
- **Convertida**: ya generó una factura.

## Cómo se usa

1. Pulse **Nuevo**.
2. Elija el cliente.
3. Agregue los productos con su cantidad, precio y descuento.
4. Guarde. La proforma queda en **borrador**.
5. Envíela al cliente. Si la acepta, cámbiela a **aprobada**.
6. Pulse **Convertir a factura**.

## Convertir en factura

Solo se factura una proforma **aprobada**. La factura se crea **en borrador**,
copiando cliente, productos, cantidades y precios, para que pueda revisarla antes
de enviarla al SRI.

Si intenta convertir una proforma que **ya generó una factura vigente**, el
sistema pide confirmación antes de crear otra. Es a propósito: evita duplicar una
venta por error, pero permite refacturar cuando de verdad hace falta (por ejemplo
si la factura anterior fue anulada).

## Enviar a pedidos

Cuando lo cotizado hay que **despachar** antes (o en vez) de facturar, el botón
del **carrito** de la barra superior del modal genera un **pedido** con los
productos de la proforma.

El pedido nace en estado **Pendiente**, con el mismo cliente, los mismos
productos, cantidades y precios. **No mueve inventario ni emite nada al SRI**: es
la orden de despacho de lo cotizado.

Su **fecha de pedido es la del momento en que se genera, con hora incluida**
(21-09-2026 15:46:27), no la fecha de la proforma: lo que interesa es cuándo se
mandó a despachar. La pestaña *Pedidos* la muestra con esa hora; el listado del
módulo Pedidos, como siempre, muestra solo el día.

Lo que **no** viaja desde la proforma son los datos de entrega —**fecha, horario
y responsable**—, porque la proforma no los tiene. Quedan vacíos y se completan
abriendo el pedido en el módulo **Pedidos**.

Condiciones para poder enviarlo:

| Requisito | Por qué |
|---|---|
| La proforma está **en borrador, aprobada o ya facturada** | No hace falta esperar a la aprobación: el pedido no factura ni emite nada, así que se puede ir preparando la entrega mientras la cotización se negocia. Una proforma *rechazada* o *anulada* sí se rechaza |
| **Todos** los ítems tienen un producto del catálogo | Un pedido se despacha, se consume desde Consignaciones y se factura **por producto**. Una línea de concepto libre (texto escrito a mano, sin producto) no tiene cómo viajar |
| Existe un punto de emisión con secuencial de **Pedidos** configurado | El pedido necesita su propio número |
| Permiso de **crear** en Proformas | Igual que convertir a factura |

> **Desde un borrador, el pedido es una copia del momento.** Si después edita la
> proforma —cambia cantidades, agrega o quita productos— el pedido **no se
> actualiza solo**: hay que corregirlo en el módulo Pedidos. El sistema lo
> advierte en el diálogo antes de generarlo.

A diferencia del pedido, la **factura** y el **recibo** sí exigen que la proforma
esté aprobada. La diferencia es deliberada: esos documentos sí tienen efecto
tributario.

**Si hay ítems sin producto no se crea nada.** El sistema corta la operación
entera y muestra la lista de líneas que hay que corregir. Para resolverlo,
regrese la proforma a borrador, asigne un producto a esas líneas y vuelva a
intentarlo. Se bloquea todo en lugar de saltarse esas líneas a propósito: un
pedido al que le faltan ítems en silencio se despacha incompleto.

Si la proforma **ya tiene un pedido**, el sistema pide confirmación antes de
crear otro — mismo criterio que con las facturas.

La proforma **no cambia de estado** al generar un pedido: *Convertida* significa
*facturada*, y un pedido no factura. La relación queda visible en la pestaña
**Pedidos** del modal, con el número, la fecha de entrega, el valor y el estado
de cada pedido generado.

> Los precios viajan al pedido para que el listado muestre el valor de lo
> cotizado. Tenga en cuenta que el modal del módulo Pedidos no captura precios:
> si vuelve a guardar ese pedido desde ahí, sus valores quedan en cero (el
> detalle de productos y cantidades se conserva).

## Editar

Solo se puede editar una proforma en **borrador**. Fuera de ese estado, el modal
se abre en modo solo lectura (cliente, detalle, información adicional y
vigencia bloqueados; sin botón Guardar) — no solo se rechaza al guardar, se ve
bloqueado desde que se abre. Si ya está aprobada y necesita cambiarla, tiene dos
caminos: anularla y crear una nueva, o —cuando el cambio es menor— convertirla a
factura y corregir en la factura antes de enviarla. Si es **administrador o
superadministrador**, tiene un tercer camino: regresarla a borrador.

## Regresar a borrador

El botón **↺ Regresar a borrador**, junto a *Anular* en la barra de acciones del
modal, devuelve una proforma **aprobada** al estado **borrador** para poder
corregirla sin perder el número ni volver a capturarla.

**Quién puede**: solo **administrador (nivel 2)** y **superadministrador (nivel 3)**,
además del permiso de *actualizar* sobre el módulo. A un usuario de nivel 1 el
botón ni siquiera le aparece, y si la petición llega por otra vía el servidor la
rechaza igual.

**Desde qué estados**: únicamente desde **aprobada**. Una proforma *rechazada*,
*anulada* o ya *convertida* en factura no se reabre — para esos casos, duplíquela.

Al reabrirla:

- La proforma vuelve a ser editable y aparece otra vez el botón **Guardar**.
- Conserva su número, su serie y su fecha de emisión.
- Hay que **volver a aprobarla** antes de facturarla.
- Si el cliente la había aprobado desde el correo, ese registro **se conserva**
  como historial, pero el aviso pasa a mostrarse en gris advirtiendo que la
  proforma se reabrió y debe aprobarse de nuevo. Si el cliente todavía tiene el
  enlace del correo, puede volver a aprobarla desde ahí.
- El cambio queda registrado en el historial del sistema (quién lo hizo y cuándo).

## Duplicar

El botón **Duplicar** (ícono de hojas, en la barra de acciones del modal) crea
una **nueva proforma en borrador** con los mismos datos de la que está abierta,
sin tocar la original. Sirve para volver a cotizar lo mismo: al mismo cliente
cuando la cotización venció, o a otro cliente cambiando solo ese dato.

Se copia: cliente, vendedor, vigencia, observaciones, condiciones, todos los
ítems con sus cantidades, precios, descuentos e IVA, y la información adicional.

**No** se copia nada propio del documento original:

| Dato | En la copia |
|---|---|
| Número (secuencial) | Uno **nuevo**, el siguiente de la misma serie |
| Fecha de emisión | La de **hoy** |
| Estado | **Borrador** (aunque la original estuviera aprobada, rechazada o anulada) |
| Aprobación del cliente por correo | Se descarta |
| Factura generada | No se arrastra |

Se puede duplicar una proforma en **cualquier** estado; lo que se copia es la
cotización, no el recorrido del documento. Al terminar, el modal se queda abierto
sobre la copia —ya en borrador y editable— para ajustar lo que haga falta antes
de aprobarla. Requiere permiso de **crear**.

## Decimales y cálculo del IVA

La proforma usa la **misma configuración de la empresa que las facturas de
venta**, que se define en *Empresa → Establecimientos*:

| Configuración | Qué controla |
|---|---|
| **Decimales de cantidad** | Cuántos decimales se muestran y se escriben en la columna *Cant.* |
| **Decimales de precio** | Cuántos decimales se muestran en *P. Sin Imp.* y *P. Con Imp.* |
| **Cálculo del IVA** | *Línea por línea* (se redondea el IVA de cada renglón y se suman) o *Al subtotal* (se calcula sobre la base acumulada de cada tarifa) |

Los importes (descuento, subtotal y totales) siempre llevan 2 decimales, y cada
paso del cálculo se redondea a 2 decimales, exactamente igual que en facturas.

Por eso la pantalla, el **PDF** y el **Excel** muestran las mismas cifras: las
salidas no recalculan nada, leen los valores guardados de la proforma. El pie de
totales es el mismo en las tres: **Subtotal** (antes de descuento), un
**Subtotal por cada tarifa de IVA**, **(-) Descuento**, un **(+) IVA por cada
tarifa** y el **TOTAL**.

> Si cambia la configuración de decimales o de cálculo del IVA, las proformas ya
> guardadas conservan los valores con los que se grabaron. Se actualizan cuando
> se vuelve a abrir y guardar la proforma.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la proforma: número,
secuencial, fecha, cliente, RUC / CI, vendedor, total y observaciones. Además
busca en el usuario que la registró y en los **códigos y descripciones de los
productos** cotizados. Las columnas **Estado** y **Correo** no entran en la
búsqueda libre: para filtrar por ellas use la ventana de filtros. Puede
escribir varias palabras en cualquier orden y no importan mayúsculas ni tildes.
Para limpiar, borre el texto o pulse Escape en el cuadro. Mientras busca,
aparece un **círculo girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Proforma** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado, correo (enviado o pendiente), serie, número, secuencial, días de vigencia, vigencia (vigente o vencida), facturada o sin facturar, aprobada por el cliente desde el correo, usuario que registró |
| Valores | Total, subtotal y descuento (cada uno con mínimo y máximo) |
| Cliente | Cliente, RUC / cédula, vendedor, observaciones |

El selector *Estado* trae los estados del recorrido (borrador, aprobada,
rechazada, convertida, anulada) y, si la empresa tiene proformas migradas con
otro estado (por ejemplo *Emitida*), también ese. *Vigencia* compara la fecha
de emisión más los días de vigencia con la fecha de hoy. *Vendedor* y *Usuario
que registró* listan solo a quienes ya aparecen en proformas de la empresa.

**Pestaña Detalles** (lo que hay dentro de la proforma). Es un único cuadro,
**Buscar libremente dentro de las proformas**: escriba un producto, un código,
una cantidad, un valor o un dato de la información adicional, y aparece la
lista de **cada línea que coincide** con la proforma a la que pertenece
(número, fecha, cliente y estado). Un clic en la fila deja el listado mostrando
solo esa proforma; el ícono de la derecha la abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Ordenar el listado

Pulse el título de una columna para ordenar por ella y vuelva a pulsarlo para
invertir el sentido. De fábrica el listado muestra lo más reciente primero.

Puede **encadenar hasta tres columnas**: mantenga presionada la tecla **Shift**
(⇧) y pulse el título de la segunda.

| Para ver… | Ordene así |
|-----------|-----------|
| Lo pendiente de aprobar, por antigüedad | *Estado*, luego Shift+clic en *Fecha* |
| Las proformas de cada cliente en el tiempo | *Cliente*, luego Shift+clic en *Fecha* |
| El trabajo de cada vendedor, por importe | *Vendedor*, luego Shift+clic en *Total* |

El número pequeño junto a cada flecha indica qué columna manda (`1`) y cuál
desempata (`2`). Un tercer Shift+clic sobre la misma columna la saca del orden, y
un clic normal en cualquier encabezado vuelve a dejar una sola.

El orden se guarda para usted y las exportaciones salen con ese mismo orden.
Detalles en *Cómo ordenar los listados*.

## El PDF de la proforma

El botón **PDF** de la barra de acciones del modal descarga la cotización con el
logo y los datos de la empresa, el bloque **PROFORMA** (número, fecha y hasta
cuándo es válida), el cliente y el detalle.

La tabla del detalle trae, por cada ítem: **Código**, **Descripción**, **Cant.**,
**P. Unit.**, **Desc.**, **IVA** y **Subtotal** — las mismas columnas que el
Excel. El código es el del producto tal como quedó guardado en la línea. Cuando
un texto no cabe en el ancho de su columna (un código largo, una descripción
extensa) **se parte en varias líneas dentro de su celda**: la fila crece y nada
se corta ni se monta sobre la columna vecina.

Al pie, las **observaciones** y la **información adicional** salen a la
**izquierda, a la misma altura que el bloque de totales**, aprovechando el
espacio que antes quedaba vacío junto a ellos.

Si la empresa tiene una **plantilla de PDF propia** configurada para proformas,
manda esa plantilla y el diseño descrito aquí no se usa.

## Exportar a Excel

Desde la proforma guardada, el botón **Excel** (junto al de PDF, en la barra de
acciones superior del modal) descarga la cotización en formato `.xlsx`: datos
de la empresa, número y fecha de la proforma, cliente, el detalle de ítems
(código, descripción, cantidad, precio unitario, descuento, IVA % y subtotal),
los totales, la información adicional y las observaciones. Igual que el PDF,
no está disponible en una proforma nueva sin guardar.

## Información adicional por producto e Info Productos

Cada línea del detalle tiene una columna **Adicional** para anotar un dato libre
sobre ese ítem (por ejemplo color, talla, condición o una aclaración para el
cliente). Ese mismo dato se ve también en la pestaña **Info Productos**, que
muestra en formato de catálogo — con la imagen guardada en la ficha del
producto — cada línea de la proforma; el campo de información adicional se
puede editar desde cualquiera de las dos pestañas, es el mismo dato. Los ítems
sin producto vinculado (líneas libres) o sin imagen cargada se muestran con un
marcador genérico.

El botón **Descargar PDF** de esa pestaña descarga la ficha de productos sin
necesidad de enviarla por correo primero.

Como cualquier otra pestaña, **Info Productos** se puede ocultar desde el
engranaje junto a las pestañas si no se usa.

## Ficha de productos en el correo

Al enviar la proforma por correo, aparece la opción **"Adjuntar ficha de
productos con imágenes"**. Si se marca, además del PDF de la proforma se envía
un segundo PDF tipo catálogo con la imagen, código/nombre, cantidad e
información adicional de cada línea — útil para que el cliente reconozca
visualmente lo cotizado. Es opcional y no se adjunta si no se marca la casilla.

## Condiciones: anexo en PDF aparte de la proforma

En el pie del modal, junto a **Info. Adicional** y **Vigencia**, está la
sub-pestaña **Condiciones**. Es un editor de texto con formato (títulos,
negrita, cursiva, subrayado, color, alineación, listas, sangría y enlaces)
pensado para escribir todo lo que la cotización necesite aclarar y que no cabe
en la proforma: garantías, forma y plazos de pago, tiempos de entrega,
exclusiones, cláusulas, etc.

Lo que se escribe ahí **no se imprime dentro de la proforma**. Se genera como un
**PDF anexo independiente** (`Condiciones_<número>.pdf`) con el nombre de la
empresa, el número de la proforma, la fecha y el cliente en la cabecera, y el
texto con su formato debajo.

- **Descargar PDF**: el botón dentro de la misma pestaña descarga el anexo. Se
  genera a partir de lo **guardado**, así que hay que guardar la proforma antes
  (si se editaron las condiciones y no se ha guardado, el PDF sale con la
  versión anterior).
- **Correo**: el anexo viaja **siempre** junto al PDF de la proforma cuando la
  proforma tiene condiciones guardadas; no hay que marcar nada. El diálogo de
  envío lo avisa con la línea "Se adjuntará también el PDF de condiciones". Si
  la proforma no tiene condiciones, simplemente no se adjunta.
- **WhatsApp**: la plantilla de Meta admite un único documento, así que la
  proforma va en el mensaje de plantilla y las condiciones se envían como un
  **segundo mensaje** con el PDF. Meta solo acepta ese segundo mensaje si el
  cliente escribió a la empresa en las últimas 24 horas (conversación abierta);
  si lo rechaza, la proforma ya salió y el sistema avisa que el anexo no pudo
  enviarse por ese canal, para que se envíe por correo.
- **Info. Adicional**: el sistema **no** agrega ninguna fila automática. Si
  quiere que en la proforma impresa conste que existe el anexo, agregue a su
  criterio una línea en Info. Adicional (por ejemplo, concepto `Condiciones` y
  detalle `Ver anexo adjunto`).

Las condiciones solo se editan mientras la proforma está en **borrador**, igual
que el resto del documento. No admite imágenes: el contenido se guarda como
texto y una imagen incrustada haría crecer el registro sin control.

## Plantillas

Una plantilla guarda una "foto" del **detalle de ítems**, la **información
adicional**, la **vigencia** y las **condiciones** para reutilizarla y armar una
proforma nueva más rápido. No incluye cliente ni fechas — eso se define en cada
proforma.

Para crear una: en la pestaña **Plantillas** pulsa **"Nueva plantilla"**. Se
abre un formulario propio (nombre, vigencia, detalle de ítems con buscador de
producto e información adicional por línea, e información adicional de
cabecera) — es independiente de la proforma que estés editando en ese momento.

Para usarla: en la pestaña **Plantillas** pulsa **Usar** sobre la que
necesites. Si el detalle actual ya tiene datos, se pide confirmación porque
**reemplaza** por completo el detalle, la información adicional, la vigencia y
las condiciones — no los combina. Al aplicarla, el modal cambia automáticamente a la pestaña
**Proforma** para que veas el resultado.

Para modificarla, pulsa el ícono de lápiz — abre el mismo formulario con sus
datos cargados. Eliminar una plantilla no afecta a las proformas que ya se
generaron con ella.

El IVA de cada línea de la plantilla se recalcula con la tarifa **vigente** al
usarla, no con la que tenía cuando se guardó — así una plantilla antigua no
aplica un IVA desactualizado.

## Enviar la proforma por WhatsApp

Desde la proforma guardada, el botón de **WhatsApp** la manda al cliente con su
PDF adjunto. Se elige la plantilla aprobada y el número (viene precargado el de
la ficha del cliente, con el código de país).

Para esto existe la plantilla del sistema **`proforma`**, que se crea en el módulo
de Plantillas de WhatsApp y rellena, en este orden, el **nombre del cliente**, el
**número de la proforma** y el **valor total**. Mientras Meta no la apruebe no
aparece en la lista. Las plantillas de enlace de pago no se ofrecen aquí: una
proforma todavía no es un cobro.

El mensaje enviado queda registrado en la conversación del cliente dentro del
Chat de WhatsApp.

## Eliminar

**Una proforma convertida no se puede eliminar.** Existe una factura que nació de
ella, y borrarla dejaría esa factura sin su origen. Anúlela si ya no aplica.

En el resto de casos la eliminación es lógica: desaparece del listado pero se
conserva en la base de datos con el usuario y la fecha.

## Permisos

Con **acceso total** se ven las proformas de toda la empresa; sin él, cada
vendedor ve solo las suyas — que suele ser justo lo que se quiere en un equipo
comercial.

Dos acciones dependen además del **nivel del usuario**, no solo del permiso:

| Acción | Requisito |
|---|---|
| **Duplicar** | Permiso de *crear* |
| **Regresar a borrador** | Permiso de *actualizar* **y** ser administrador (nivel 2) o superadministrador (nivel 3) |

## Serie y secuencial

La proforma se numera con la **serie** (establecimiento + punto de emisión) y su
**secuencial**. En el selector *Serie* del modal solo aparecen los puntos de
emisión que ya tienen configurado el secuencial del documento **Proformas** en
*Empresa → Puntos de emisión*; el resto se oculta porque no podrían numerar.

Al abrir una **proforma nueva** el sistema pide el siguiente número disponible de
esa serie y lo muestra en *Secuencial* (campo de solo lectura). Si detecta un
número faltante (hueco) en la numeración lo recupera y marca el campo en
**amarillo**; al pasar el cursor se indica el motivo.

Si la serie elegida **no tiene secuencial configurado**, o la empresa no tiene
ninguna serie disponible para proformas, el sistema **avisa apenas se abre el
modal** y **no deja guardar** hasta configurarlo. Es el mismo comportamiento que
en Facturas de Venta.

### Dos proformas no pueden quedarse con el mismo número

El número no se toma del que muestra la pantalla: al guardar, el servidor vuelve
a pedir el siguiente disponible de esa serie y **bloquea el punto de emisión**
hasta terminar de grabar. Por eso, si dos personas guardan una proforma de la
misma serie en el mismo instante, cada una recibe un número distinto aunque el
campo *Secuencial* les mostrara el mismo antes de guardar.

Además, la base de datos rechaza cualquier intento de repetir número dentro de
una serie. Si eso llega a ocurrir, el sistema avisa que **el número acaba de ser
tomado por otra proforma**: basta con volver a pulsar *Guardar* para que le
asigne el siguiente.

## Errores frecuentes

- **"La proforma debe estar aprobada para generar una factura"**: cámbiela a
  aprobada primero.
- **"Solo se pueden editar proformas en estado borrador"**: ya fue aprobada;
  anúlela y cree una nueva, o corrija en la factura resultante.
- **"No se puede eliminar una proforma ya convertida a factura"**: use anular.
- **"Secuencial no configurado"**: la serie elegida no tiene numeración para
  proformas, o no hay ninguna serie disponible. Configúrela en *Empresa → Puntos
  de emisión* antes de emitir.
- **El cliente no aparece**: regístrelo primero en Clientes, en esta misma empresa.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Con ese modo activo, **al cambiar la fecha del documento su número se recalcula
solo**, para que caiga en el periodo correcto. Una vez guardado, el número queda
fijo aunque después se le cambie la fecha, y los documentos ya emitidos conservan
siempre el que tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Historial de cambios

- **1.15** — El botón **Enviar a pedidos** ya funciona (antes solo avisaba
  "próximamente"). Genera un pedido en estado *Pendiente* con el cliente, los
  productos, las cantidades y los precios de la proforma; los datos de entrega se
  completan después en el módulo Pedidos, y con la **fecha y hora** del momento en que se
  genera. Se puede desde una proforma en **borrador**, **aprobada** o ya
  facturada —no desde una rechazada o anulada— y solo si **todos** los ítems tienen producto de
  catálogo: si hay líneas de concepto libre, no se crea nada y el sistema las
  nombra. Nueva pestaña **Pedidos** en el modal, con los pedidos generados desde
  esa proforma. Nueva sección *Enviar a pedidos*.
- **1.14** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en todas las columnas (incluidos vendedor, total y
  los productos cotizados, cada palabra por separado y sin importar tildes),
  salvo Estado y Correo. Los filtros pasan a una **ventana propia** (botón del
  embudo, se aplican con *Aplicar*) con dos pestañas: **Proforma** (filtros por
  campo, con criterios nuevos: correo, días de vigencia, vigente/vencida,
  facturada, aprobada por el cliente, usuario, subtotal, descuento y vendedor)
  y **Detalles** (búsqueda dentro de los productos y la información adicional).
  El filtro de estado encuentra también las proformas migradas con el estado en
  mayúsculas. Los filtros activos se ven como etiquetas dentro del cuadro y la
  tabla se atenúa mientras carga.
- **1.13** — El **PDF de la proforma** muestra el **código** de cada ítem en la
  primera columna, en lugar del número de línea (1, 2, 3…), igual que el Excel.
  Las celdas del detalle **parten el texto en varias líneas** cuando no cabe en
  su columna, así que un código o una descripción larga ya no se recorta ni pisa
  la columna siguiente. Las **observaciones y la información adicional** pasan a
  imprimirse **a la izquierda del bloque de totales**, a su misma altura. Nueva
  sección *El PDF de la proforma*. En *Serie y secuencial* se explica qué impide
  que dos proformas terminen con el mismo número.

- **1.12** — El listado se puede **ordenar por hasta tres columnas a la vez**:
  Shift+clic en el título de la segunda columna la encadena a la primera (por
  ejemplo *Estado* y, dentro de cada uno, la *Fecha*). Cada encabezado activo
  muestra un número con su prioridad. Nueva sección *Ordenar el listado*.

- **1.11** — El botón **Duplicar** ya funciona (antes solo avisaba "próximamente").
  Crea una copia en borrador con número nuevo de la misma serie y fecha de hoy,
  copiando cliente, ítems, condiciones e información adicional, y deja el modal
  abierto sobre la copia. Nuevo botón **Regresar a borrador** junto a *Anular*,
  que reabre una proforma aprobada para editarla conservando su número; es solo
  para **administrador y superadministrador**. Nuevas secciones *Duplicar* y
  *Regresar a borrador*.

- **1.10** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.9** — Nueva sub-pestaña **Condiciones** (junto a Vigencia): editor de texto
  con formato para las condiciones adicionales de la cotización. No se imprimen en
  la proforma: se generan como un **PDF anexo aparte**, descargable desde la misma
  pestaña, que acompaña siempre a la proforma al enviarla por correo (y por
  WhatsApp como segundo mensaje, cuando Meta lo permite).
  Las **plantillas** también guardan las condiciones y las precargan al usarlas.
  El sistema no agrega filas a Info. Adicional; esa referencia la escribe el
  usuario si la quiere.
- **1.8** — La proforma respeta la **configuración de la empresa** (decimales de
  cantidad, decimales de precio y modo de cálculo del IVA), la misma que usan las
  facturas de venta; antes usaba decimales fijos y sumaba el IVA siempre línea por
  línea. Los cálculos redondean a 2 decimales en cada paso, igual que en facturas, y
  el **PDF** y el **Excel** muestran el mismo pie de totales que la pantalla
  (Subtotal, subtotales por tarifa, descuento e IVA por tarifa) leyendo los valores
  guardados en vez de recalcularlos. Nueva sección *Decimales y cálculo del IVA*.
- **1.7** — El **PDF de la proforma** muestra los valores exactamente como se ven
  en pantalla: precio unitario con 4 decimales (antes se redondeaba a 2), subtotal
  de línea calculado igual que el modal (cantidad × precio − descuento) y el bloque
  de totales con el mismo desglose: **Subtotal**, un **Subtotal por cada tarifa de
  IVA**, el **(-) Descuento** y un **(+) IVA por cada tarifa**. El IVA ya no se
  deduce restando totales, se suma por línea.
- **1.6** — En el **PDF de la proforma** el logo de la empresa ocupa todo el
  espacio superior izquierdo: a lo ancho hasta donde arranca la tarjeta
  "PROFORMA" y a lo alto hasta el nombre de la empresa. La imagen se ajusta
  dentro de ese recuadro sin deformarse, así que un logo horizontal (proporción
  ancha) es el que mejor aprovecha el espacio.
- **1.5** — Se documenta la **serie y el secuencial**: solo se ofrecen series con
  numeración de proformas configurada, y el sistema avisa y bloquea el guardado
  cuando no la hay (incluido el caso de no tener ninguna serie disponible).
- **1.4** — Pestaña **Plantillas**: guarda detalle + información adicional +
  vigencia como plantilla reutilizable y la aplica para armar proformas más
  rápido.
- **1.3** — Pestaña **Info Productos** (catálogo con imagen por línea), campo de
  información adicional por producto persistido, y ficha de productos con
  imágenes como adjunto opcional del correo.
- **1.2** — Se agrega el botón **Excel** para exportar la proforma a `.xlsx`.
- **1.1** — Se documenta el envío de la proforma por WhatsApp y la plantilla del
  sistema `proforma`.
- **1.0** — Versión inicial.
