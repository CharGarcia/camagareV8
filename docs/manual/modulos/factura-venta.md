---
titulo: Facturas de Venta
resumen: Emisión de facturas electrónicas: creación, envío al SRI, PDF, correo y anulación.
categoria: Ventas
ruta_modulo: modulos/factura-venta
tipo: modulo
visibilidad: todos
etiquetas: factura, facturar, venta, buscar factura, buscador, filtros, filtrar facturas, buscar por producto vendido, buscar por forma de pago, filtro de fechas, saldo pendiente, chips, ordenar por dos columnas, ordenar por estado de pago, ordenar por cliente y fecha, sri, comprobante electronico, xml, excel, anular, nota de credito, whatsapp, link de pago, payphone, nuvei, serie vacia, sin puntos de emision, secuencial repetido, secuencial ya existe, punto de emision, ambiente, pruebas, produccion, cambio de ambiente, clave de acceso en procesamiento, error 70, comprobante devuelto, reintento automatico, saldo, stock, existencias, cuanto queda, disponible, buscador de productos, lote, vencimiento, caducidad, fecha de vencimiento, lote y vencimiento, pdf, ride, columnas del pdf, subsidio, irbpnr, servicio, propina, codigo cortado, detalle adicional, forma de pago, plazo, dias credito, unidad de tiempo, meses, anios, informacion adicional, vendedor, cajero, no sale el vendedor, falta informacion en el pdf, se cierra el modal, autorizar, bloquear factura, no puedo editar
version: 2.9
orden: 20
estado: activo
---

El módulo de **Facturas de Venta** emite los comprobantes electrónicos de venta y
gestiona su ciclo completo: creación, autorización en el SRI, entrega al cliente
y anulación.

## Antes de empezar

Para emitir facturas electrónicas la empresa necesita tener configurado:

- Los datos tributarios de la empresa y su establecimiento.
- La **firma electrónica** vigente.
- El **secuencial** del documento y el ambiente (pruebas o producción).
- El cliente y los productos o servicios que va a facturar.

## Crear una factura

1. Pulse **Nuevo**.
2. Elija el cliente. Si no existe, regístrelo primero en el módulo de Clientes.
3. Agregue las líneas de detalle: producto, cantidad, precio y descuento.
4. Revise los totales y el IVA calculado.
5. Guarde la factura.

Una factura guardada queda en borrador hasta que se envía al SRI.

### Saldo del producto en el buscador

Al escribir en la columna **Descripción** para buscar un producto, cada resultado de la
lista muestra a la derecha el **saldo disponible** junto al precio: en verde si hay
existencias y en rojo si el saldo es cero o negativo. Así se ve cuánto queda **antes**
de elegir el producto, sin tener que agregarlo a la factura para enterarse.

- El saldo corresponde a la **bodega seleccionada en la cabecera** de la factura. Si se
  cambia la bodega, la siguiente búsqueda ya muestra el saldo de la nueva.
- Solo aparece en los productos de tipo **bien** marcados como inventariables. Los
  servicios no muestran saldo (no manejan existencias).
- Solo aparece si la empresa tiene activado **"La facturación afecta al inventario"**
  (módulo Empresa → pestaña Facturación). Si está apagado, la factura no descuenta stock
  y el buscador no muestra saldos.
- Al editar una factura ya guardada, el saldo se muestra **sin contar esa misma
  factura**, es decir, el stock que habría si el documento no existiera — el mismo
  criterio que usan los lotes y la validación de cantidades.

### Lote y fecha de vencimiento

En los productos que manejan lote, la columna **Vencimiento** depende del lote
elegido: mientras no se elija lote se listan todas las fechas disponibles del
producto en esa bodega, y al elegir uno la lista queda **acotada a la fecha de ese
lote**. Así no puede quedar facturada una combinación lote/vencimiento que no
exista en bodega. También funciona al revés: elegir la fecha selecciona su lote.
Para volver a ver todas las fechas, devuelva el lote a *Lote...*.

Al **abrir una factura ya guardada**, la línea muestra el lote y el vencimiento
**con los que se emitió**, aunque el inventario haya cambiado desde entonces.

## Barra de acciones del documento

En la parte superior del formulario están las acciones sobre el documento ya
guardado: generar el **PDF**, ver el **XML**, descargar un **Excel** con el
detalle y los totales, enviarlo por **correo** o por **WhatsApp** y remitirlo
al **SRI**. Cada acción comprueba primero que la factura esté guardada.

## Qué pasa con el modal después de enviar al SRI

Al enviar la factura al SRI, **el modal se queda abierto** con cualquier
resultado. La factura sigue a la vista con su número de autorización y su pestaña
*SRI*, para revisarla, generar el PDF o enviarla por correo sin volver a abrirla.

Cuando el SRI **autoriza**, el documento queda **bloqueado para edición**: los
campos y las tablas de ítems, información adicional y formas de pago se
deshabilitan, desaparecen los botones de agregar y eliminar líneas, y el botón
*Enviar al SRI* se oculta. Se habilitan en su lugar *Correo*, *WhatsApp* y
*Anular*.

Lo único que sigue editable es el **vendedor**: el botón del pie pasa de
*Guardar* a *Actualizar*, que guarda el vendedor y genera el asiento contable si
la factura aún no lo tiene.

El **listado de fondo** se actualiza al momento (estado y badge de la fila). Al
cerrar el modal, la tabla se recarga conservando el orden, la dirección y la
página en la que estabas.

> Si el SRI **rechaza** o devuelve el comprobante, la factura sigue en *borrador*
> y editable, para corregirla y reenviarla.

## Qué columnas y totales muestra el PDF

El PDF (RIDE) imprime solo lo que la factura realmente usa, para no gastar ancho
ni líneas en campos vacíos:

- **Columnas del detalle**: *Cód. Principal*, *Cód. Auxiliar*, *Cantidad*,
  *Descripción*, *Detalle Adicional*, *Precio Unitario*, *Descuento* y
  *Precio Total*.
- **Cód. Auxiliar** y **Detalle Adicional** aparecen **solo si algún ítem de la
  factura trae ese dato**. Si no, la columna no se dibuja y su espacio pasa a la
  descripción.
- El ancho de **Cód. Principal** se ajusta al código más largo de la factura, de
  modo que el código se vea completo. Si es excepcionalmente largo, el texto se
  condensa dentro de su celda en lugar de invadir la columna siguiente.
- **Totales**: se imprimen los subtotales por tarifa de IVA, *Subtotal sin
  impuestos*, *Total descuento*, *ICE*, el *IVA* por tarifa y el *Valor total*.
- **Servicio** (propina) aparece **solo si el establecimiento tiene activada la
  propina** en *Empresa → Facturación*, o si la factura ya se emitió con un valor
  de servicio.
- En la tabla de **formas de pago**, la columna *Días Crédito* lleva la cantidad
  y la columna *Plazo* lleva **solo la unidad**: *Días*, *Meses* o *Años*. Un
  crédito a 15 días se lee «15» y «Días», no «15» y «15 dias». Cuando el pago es
  de contado (plazo 0), la columna *Plazo* muestra un guion.

### Vendedor y Cajero en la Información Adicional

El bloque *Información Adicional* del PDF imprime las filas guardadas con la
factura y, además, **completa el Vendedor y el Cajero tomándolos de la propia
factura** cuando no están guardados como fila.

Esto importa porque la fila de texto solo la crea la pantalla de Factura de
Venta, y solo mientras el establecimiento tenga activado su interruptor en
*Empresa → Facturación*. Las facturas emitidas por otra vía —facturación de
consignaciones, POS, API, cargas por Excel o migración— guardaban el vendedor en
la factura pero **no salía en el PDF**. Ahora sale siempre.

Reglas:

- Si la factura **ya tiene guardada** la fila *Vendedor* (o *Cajero*), se imprime
  **esa**, aunque después se haya cambiado el vendedor del documento: es el texto
  que viajó en el XML autorizado, y el PDF no puede decir algo distinto del
  comprobante que aprobó el SRI.
- Si **no** la tiene, se toma el vendedor de la factura, siempre que el
  establecimiento tenga activado *Mostrar vendedor en factura* (lo mismo para el
  cajero).
- Si el interruptor está apagado, no se imprime.

> Si en una factura antigua el vendedor impreso no coincide con el que muestra la
> pantalla, es que la fila guardada quedó desfasada al cambiar el vendedor. El PDF
> respeta lo emitido; para corregirlo hay que actualizar la fila en la factura.

Las columnas *Subsidio* y *Precio sin Subsidio* y la línea *IRBPNR* ya no se
imprimen: el sistema no factura bienes subsidiados ni ese impuesto, así que
salían siempre en 0,00. Cuando una factura sí tiene subsidio, el ahorro sigue
apareciendo en el bloque de totales (*Valor total sin subsidio* y *Ahorro por
subsidio*).

> Si usas una **plantilla de factura personalizada**, el diseño lo define la
> plantilla y no esta sección.

## Enviar la factura y el enlace de pago por WhatsApp

El botón de WhatsApp pide la plantilla aprobada y el número del cliente. Con las
plantillas de factura, el mensaje sale con el **PDF adjunto**.

Además hay dos plantillas especiales que, en lugar del PDF, envían un **enlace de
pago con tarjeta**: `link_pago_payphone` y `link_pago_nuvei`, una por cada
pasarela. Al elegirlas, el sistema muestra el **saldo pendiente** de la factura y
genera el enlace por ese valor:

- Solo se ofrecen si la factura está **autorizada** y tiene saldo pendiente.
- El enlace siempre es por el **total pendiente**: por esta vía no se cobra
  parcialmente.
- No se envía un segundo enlace si ya hay uno pendiente de los últimos **15
  minutos**.
- Requiere una **forma de cobro** de ese tipo (Payphone o Nuvei) activa y
  configurada en la empresa.
- El enlace de Payphone es de un solo uso y caduca a los pocos minutos; el de
  Nuvei se renueva cada vez que el cliente lo abre y deja de servir cuando el
  pago se registra.

Cuando el cliente paga, el cobro aparece en la pestaña **Pagos** de la factura,
igual que si el enlace se hubiera enviado por correo. Las plantillas se crean en
el módulo de Plantillas de WhatsApp.

## Envío al SRI

Al enviar, el sistema firma el XML y lo transmite al Servicio de Rentas Internas.
El comprobante puede quedar autorizado o devuelto con observaciones. Si es
devuelto, corrija lo que indique el mensaje y vuelva a enviar.

Cuando hay muchos documentos pendientes conviene usar el envío en lote, que los
procesa en segundo plano.

## Anular una factura

Una factura **autorizada** no se elimina: se anula, y esa anulación se informa al
SRI. Si lo que se necesita es corregir valores o devolver mercadería, el
documento correcto es una **nota de crédito**, no la anulación.

Anular una factura revierte también los movimientos asociados (inventario, cobro
y asiento contable) según la configuración de la empresa.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en **todas las columnas** de la factura y
en sus datos relacionados: número, secuencial, fecha, cliente, RUC o cédula,
vendedor, usuario, observaciones, subtotal, descuento, IVA, ICE, propina,
total, estado, estado de correo, clave de acceso, guía de remisión, placa y los
**códigos y descripciones de los productos vendidos**. Puede escribir varias
palabras en cualquier orden y no importan mayúsculas ni tildes. Para limpiar,
borre el texto o pulse Escape en el cuadro.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana no se cierra con un clic fuera ni con Escape,
solo con la X, Cancelar, Aplicar o Limpiar filtros.

**Pestaña Factura** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado, estado de pago (pendiente / abonada / pagada), correo, serie, Nº de factura, secuencial, fecha de autorización, origen (directa, desde proforma, desde pedido, POS / caja, cotización de publicidad), con o sin asiento contable, ambiente, días de crédito |
| Valores | Total, saldo pendiente, subtotal, descuento, IVA, ICE, propina (cada uno con mínimo y máximo) |
| Tercero | Cliente, RUC / cédula, vendedor, usuario que registró, observaciones, placa, guía de remisión, clave de acceso |

El *estado de pago* y el *saldo pendiente* se calculan con la misma regla que
la columna Saldo: cobros de Ingresos, notas de crédito y retenciones.

**Pestaña Detalles** (lo que hay dentro de la factura). Es un único cuadro,
**Buscar libremente dentro de las facturas**: escriba un producto, un código,
un lote, un NUP, una forma de pago, un plazo o un dato de la información
adicional, y aparece la lista de **cada línea que coincide** con la factura a
la que pertenece (número, fecha, cliente y estado). Un clic en la fila deja el
listado mostrando solo esa factura; el ícono de la derecha la abre
directamente. Por ejemplo, *aceite* lista todas las facturas donde se vendió
ese producto, y *transferencia* las que se pagaron así.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**, delante del texto que escribe. La **×** de la etiqueta quita solo
ese filtro, y con el cuadro vacío la tecla **Retroceso** quita el último. Pulsar
la etiqueta vuelve a abrir la ventana para ajustarla. El embudo muestra cuántos
hay activos, y el botón **Limpiar filtros** de la ventana los borra todos.

La búsqueda libre y los filtros se combinan entre sí, y los botones **PDF** y
**Excel** del listado exportan exactamente lo que se ve.

## Ordenar el listado

Pulse el título de una columna para ordenar por ella y vuelva a pulsarlo para
invertir el sentido. De fábrica el listado muestra **lo más reciente primero**.

Puede **encadenar hasta tres columnas**: mantenga presionada la tecla **Shift**
(⇧) y pulse el título de la segunda. Usos típicos en facturación:

| Para ver… | Ordene así |
|-----------|-----------|
| Lo que falta cobrar, y dentro lo de mayor valor | *Estado de pago*, luego Shift+clic en *Total* |
| El movimiento de cada cliente en el tiempo | *Cliente*, luego Shift+clic en *Fecha* |
| Los borradores pendientes por antigüedad | *Estado*, luego Shift+clic en *Fecha* |

El número pequeño junto a cada flecha indica qué columna manda (`1`) y cuál
desempata (`2`). Un tercer Shift+clic sobre la misma columna la saca del orden, y
un clic normal en cualquier encabezado vuelve a dejar una sola.

Las columnas calculadas también se ordenan por su valor real: el **Estado de
pago** va de pendiente a pagada y anulada al final (no alfabéticamente), y el
**IVA** por el importe, no por el texto.

El orden se guarda para usted y los botones de **PDF** y **Excel** exportan con
ese mismo orden. Detalles en *Cómo ordenar los listados*.

## Errores frecuentes

- **Firma caducada**: renueve el certificado y vuelva a cargarlo en la empresa.
- **Secuencial repetido** (*"El número de secuencial ya existe para este punto
  de emisión"*): revise el secuencial configurado para el establecimiento y
  punto de emisión. Si el local **pasó de Pruebas a Producción** (o al revés) y
  el mensaje aparece con un número que en el ambiente activo está libre, es el
  caso corregido en la versión 1.6: la numeración de Pruebas y la de Producción
  son independientes, y antes el sistema las comparaba entre sí.
- **El selector de Serie aparece vacío** (no ofrece ningún punto de emisión):
  la empresa tiene más de un establecimiento y el que está realmente
  **Activo** no era el que se usaba para armar el combo (corregido; si
  persiste, confirme en el módulo Empresa → Establecimientos cuál es el
  Activo y que tenga un punto de emisión activo con "Facturas de venta"
  configurada en Secuenciales).
- **"Clave de acceso en procesamiento"**: no es un rechazo. El SRI ya tiene la
  factura en cola de un envío anterior y todavía no publica el resultado.
  Reenviarla no sirve de nada: devolvería el mismo mensaje. El sistema ahora la
  reconoce y pasa directo a consultar la autorización, y si el SRI aún no
  responde la deja en seguimiento para que el reintento automático la resuelva.
  Detalle en la guía *"Clave de acceso en procesamiento": qué significa y qué
  hacer* (`guias/clave-de-acceso-en-procesamiento`).
- **El cliente no recibe el correo**: verifique la dirección registrada en su ficha.

## Normativa SRI 2026: RUC del proveedor y placa de transporte

- **RUC Proveedor (todas las empresas)**: por la Resolución NAC-DGERCGC26-00000027,
  el sistema agrega automáticamente el campo **"RUC Proveedor"** en la información
  adicional del XML y del PDF de todos los comprobantes electrónicos. En la pestaña
  *Info. Adicional* del modal se muestra como una fila fija con candado: **no se
  puede editar ni eliminar**. El valor lo configura el superadministrador en
  `/config/sri-proveedor`.
- **Placa del vehículo (solo operadoras de transporte)**: si la empresa tiene activo
  el switch **"Operadora de transporte comercial (excepto taxis)"** (módulo Empresa →
  pestaña Facturación), la factura pide la **placa del vehículo** (obligatoria, formato
  `ABC1234` sin espacios ni guiones). La placa sale en el XML (tag `<placa>`) y en el
  PDF (casilla *Placa / Matrícula*). Ficha Técnica SRI v2.34, Anexo 25.

## Períodos contables cerrados

Un documento que mueve cartera, inventario o contabilidad no puede tocar un
período ya cerrado. El sistema lo comprueba en las cuatro operaciones:

| Operación | Qué se comprueba |
|-----------|------------------|
| Emitir | Que la fecha de emisión no caiga en un período cerrado |
| Modificar | La fecha nueva **y** aquella con la que está registrado |
| Anular | La fecha del documento (anular revierte sus movimientos) |
| Eliminar | La fecha del documento |

Al modificar se revisan las dos fechas a propósito: mover un documento de un
mes cerrado a uno abierto lo alteraría igual. Los períodos se abren y se
cierran en **Contabilidad → Períodos Contables**; reabrir el período permite
la operación de inmediato.

## Historial de cambios

- **2.9** — Nuevo buscador del listado: el cuadro de búsqueda ya no despliega
  sugerencias; lo que se escribe se busca **en todas las columnas** (incluidos
  los productos vendidos, el vendedor, el usuario, la clave de acceso, la guía
  y la placa). Los filtros pasan a una **ventana propia** (botón del embudo, se
  aplican con *Aplicar*) con dos pestañas: **Factura** (filtros por campo, con
  criterios nuevos: fecha de autorización, origen, con/sin asiento, ambiente,
  días de crédito, saldo pendiente, vendedor y usuario como lista, placa, guía
  de remisión y clave de acceso) y **Detalles**, un cuadro de **búsqueda libre
  dentro de las facturas** (productos, formas de pago e información adicional)
  que lista cada coincidencia y dice a qué factura pertenece. Los filtros
  activos se ven como etiquetas dentro del cuadro. Nueva sección *Buscar y
  filtrar el listado*.
- **2.8** — Al **enviar al SRI**, el modal ya **no se cierra** cuando la factura
  se autoriza: se queda abierto y el documento pasa a **solo lectura** (antes
  había que volver a abrirlo para verlo). El botón del pie cambia a *Actualizar*
  —el vendedor y el asiento son lo único editable de una factura autorizada—, el
  botón *Eliminar* se ajusta al estado y se libera el bloqueo de edición, que
  antes quedaba retenido mientras el modal siguiera abierto. Al cerrar el modal la
  tabla se recarga conservando orden y página, como antes. Ver *"Qué pasa con el
  modal después de enviar al SRI"*.
- **2.7** — El listado se puede **ordenar por hasta tres columnas a la vez**:
  Shift+clic en el título de la segunda columna la encadena a la primera (por
  ejemplo *Estado de pago* y, dentro, *Total*). Cada encabezado activo muestra un
  número con su prioridad. El orden se guarda por usuario y se respeta al
  exportar a PDF y Excel. Nueva sección *Ordenar el listado*.
- **2.6** — **PDF de la factura**. Se quitaron las columnas *Subsidio* y *Precio
  sin Subsidio* y la línea *IRBPNR* de los totales: iban siempre en 0,00. La
  columna *Cód. Principal* se ensancha ahora según el código más largo de la
  factura (antes un código largo se salía encima de la columna siguiente) y
  *Detalle Adicional* solo se dibuja si algún ítem lo trae. *Servicio* aparece
  solo si el establecimiento tiene activada la propina o la factura ya se emitió
  con servicio. En la tabla de formas de pago, la columna *Plazo* muestra ahora
  solo la unidad (*Días*, *Meses*, *Años*) en lugar de repetir el número que ya
  está en *Días Crédito* —antes se leía «15 dias»—, con la misma etiqueta para
  todas las variantes guardadas (`dias`, `DIAS`, `anios`, `AÑOS`…). Además, el
  **Vendedor** y el **Cajero** salen ahora en la *Información Adicional* aunque
  no estén guardados como fila: se toman de la propia factura. Antes solo
  aparecían si los había escrito la pantalla de Factura de Venta, así que las
  facturas hechas desde consignaciones, POS, API, carga por Excel o migración
  salían sin vendedor. El PDF que se envía por **correo desde Cuentas por
  Cobrar** también carga ya la configuración del establecimiento (decimales,
  presentación de ítems, vendedor/cajero y propina), que antes no leía. Ver
  *"Qué columnas y totales muestra el PDF"*.
- **2.5** — La columna **Vencimiento** de cada línea se limita ahora al **lote
  seleccionado** (y elegir la fecha selecciona su lote). Antes se ofrecían todas
  las fechas del producto, con lo que podía facturarse una combinación
  lote/vencimiento inexistente en bodega. Al reabrir una factura se conserva el
  vencimiento con el que se emitió. Las fechas se muestran en formato `d-m-a`.
- **2.4** — Errores **43 y 45 del SRI**. El número de una factura eliminada que el SRI ya
  recibió o autorizó ya no se reutiliza, no se puede eliminar un borrador en esa
  situación (salvo el borrado forzado del superadministrador) y el error 45
  *"Secuencial registrado"* se explica con el número y el ambiente afectados. El 43
  *"Clave de acceso registrada"* deja de marcar la factura como devuelta: se consulta la
  autorización, igual que con el 70. Aplica también a notas de crédito y débito,
  facturas de reembolso, retenciones, guías y liquidaciones. Ver la guía *"Clave de
  acceso en procesamiento"*.
- **2.3** — El módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar una factura cuyo período esté cerrado. Antes no se
  comprobaba en ninguna de las cuatro operaciones.
- **2.2** — La **tirilla** respeta la *Presentación de los ítems* configurada en
  el módulo Empresa (pestaña Facturación): agrupa las líneas por **nombre**
  —opción nueva, junta el mismo producto sin importar lote ni NUP—, por lote o
  por NUP, y anexa a la descripción la unidad, el lote, la caducidad o el NUP.
  Antes esa configuración solo llegaba al PDF y al XML, así que el ticket podía
  mostrar seis líneas donde la factura impresa mostraba una. Con la
  configuración por defecto la tirilla sale igual que antes: una línea por ítem.

- **2.1** — Al abrir una **guía de remisión desde la factura** (con sus líneas
  cargadas) y cerrarla, el modal de la factura quedaba inactivo hasta recargar
  la página: el cálculo de totales y el armado de las líneas leían las filas de
  detalle de **toda la pantalla**, incluidas las de la guía, que no tienen
  precio. Ahora solo leen las filas de la propia factura. Esto evitaba además
  que, en ese estado, las líneas de la guía pudieran colarse en la factura al
  guardar. Al crear la guía desde la factura se completa la fecha del documento
  de sustento y se copian dirección y correo del cliente.
- **2.0** — El **estado de pago** (pendiente / abonada / pagada) y las columnas
  de cobrado, notas y retención usan la misma regla que Cuentas por Cobrar: una
  retención que sustenta varias facturas reparte lo retenido por línea, y las
  retenciones y notas de crédito/débito se enlazan a la factura comparando solo
  los dígitos del número.
- **1.9** — El buscador de productos del detalle muestra el **saldo disponible** del
  producto en la bodega de la cabecera, para los bienes inventariables y solo cuando la
  facturación afecta al inventario.
- **1.8** — La ventana de la tirilla ya no desaparece al cancelar la
  impresión: antes el navegador avisaba igual al imprimir que al cancelar y la
  ventana desaparecía a los 2 segundos, obligando a pedir la tirilla otra vez.
  Ahora avisa de que se cerrará en 10 segundos y deja a mano **Imprimir de
  nuevo** —que reinicia la cuenta— y **Cerrar**.
- **1.7** — *"Clave de acceso en procesamiento"* ya no se trata como un rechazo.
  Es la respuesta del SRI cuando la factura ya está en su cola de un envío
  anterior: ahora el sistema lo reconoce y pasa a consultar la autorización en
  vez de darla por devuelta. Antes quedaba marcada como devuelta y, por serlo,
  fuera del reintento automático: nadie volvía a mirarla.
- **1.6** — El secuencial ahora es único **por ambiente**. Pruebas y Producción
  son numeraciones independientes en el SRI (el ambiente va dentro de la clave
  de acceso), así que al pasar un local a Producción la serie arranca de nuevo y
  repite números ya usados en Pruebas. El sistema los leía como duplicados y
  bloqueaba la emisión con *"El número de secuencial ya existe para este punto
  de emisión"*, aunque el número calculado sí estuviera libre en el ambiente
  activo. Dentro de un mismo ambiente la protección contra números repetidos
  sigue igual de estricta.
- **1.5** — La tirilla se adapta al ancho de papel del driver en vez de imponer el
  suyo, con columnas de ancho proporcional y tipografía sans-serif: ya no sale
  reescalada, con los importes corridos ni con la letra entrecortada en
  impresoras térmicas de 80 mm.
- **1.4** — Corregido: si la empresa tenía más de un establecimiento y el
  activo no era el que ordenaba primero por código, el selector de Serie
  quedaba vacío (tomaba los puntos de emisión del establecimiento
  equivocado, generalmente inactivo). Ahora siempre se arma con el
  establecimiento realmente Activo.
- **1.3** — Nuevo botón **Excel** en la barra de acciones, junto al de XML:
  descarga el detalle de la factura (líneas, totales y forma de pago) en una
  hoja de cálculo.
- **1.2** — Normativa SRI 2026: campo fijo "RUC Proveedor" en info adicional (no
  editable) y placa del vehículo obligatoria para operadoras de transporte
  comercial. Columnas "Precios" y "Medida" del detalle ahora solo aparecen cuando
  algún ítem las usa. Los ítems ya no aceptan cantidades, precios ni descuentos
  negativos. Al abrir una factura nueva se aplican los favoritos del usuario.
- **1.1** — Se documenta el envío por WhatsApp y el enlace de pago con tarjeta,
  disponible ahora también con **Nuvei** además de Payphone.
- **1.0** — Versión inicial.
