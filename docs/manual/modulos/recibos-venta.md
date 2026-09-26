---
titulo: Recibos de venta
resumen: Documento de venta interno, con o sin impuestos, que no se envía al SRI.
categoria: Ventas
ruta_modulo: modulos/recibo-venta
tipo: modulo
visibilidad: todos
etiquetas: recibo de venta, recibos, buscar recibos, buscador, filtros, filtrar recibos, buscar por cliente, buscar por producto, estado de pago, saldo pendiente, nota de venta, venta sin factura, documento interno, sin impuestos, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, lote, vencimiento, caducidad, fecha de vencimiento, lote y vencimiento, ancho de columna, agrandar columna, ensanchar, codigo cortado, descripcion cortada, no se ve la descripcion completa, redimensionar, precio con impuestos, precio con iva, sale en cero, tipo de identificacion, tipo de documento del cliente, ruc o cedula, es ruc o cedula, cedula o pasaporte, consumidor final, no se cual identificacion tiene el cliente, buscador de clientes, buscar cliente, elegir cliente, datos del cliente, imprimir, impresora
version: 1.17
orden: 35
estado: activo
---

El **recibo de venta** es un documento de venta interno: sirve para respaldar una
entrega y su cobro **sin emitir un comprobante electrónico**. No se envía al SRI.

Funciona como una factura en todo lo demás: descuenta inventario, registra el
cobro y genera su asiento contable.

## Con o sin impuestos

El recibo tiene un interruptor para emitirlo **con o sin impuestos**. Al
cambiarlo, los totales se recalculan al momento.

Es la diferencia principal con la factura, que siempre sigue las reglas
tributarias. Elija según lo que respalde el documento.

## Cómo se emite

1. Pulse **Nuevo**.
2. Elija el cliente.
3. Añada los productos.
4. Decida si lleva impuestos.
5. Registre el cobro.
6. Guarde.

### Tipo de identificación del cliente

Al escribir en el buscador de clientes, cada resultado muestra a la derecha la
**identificación** y, justo después, el **tipo de identificación** (*Ruc*,
*Cedula*, *Pasaporte*, *Venta a consumidor final*, *Identificación del exterior*).
Así se distingue de un vistazo un RUC de una cédula antes de elegir, sin abrir la
ficha del cliente.

Una vez elegido el cliente, el tipo se mantiene visible en la línea de datos que
aparece bajo el buscador, después de la identificación, junto a la dirección y el
correo. Lo mismo al abrir un recibo ya guardado.

El tipo es el que tenga registrado el cliente en su ficha; si no tiene ninguno,
no se muestra nada (no aparece una etiqueta vacía).

### Lote y fecha de vencimiento

En los productos que manejan lote, la columna **Vencimiento** depende del lote
elegido: mientras no se elija lote se listan todas las fechas disponibles del
producto en esa bodega, y al elegir uno la lista queda **acotada a la fecha de ese
lote**. Así no puede quedar registrada una combinación lote/vencimiento que no
exista en bodega. También funciona al revés: elegir la fecha selecciona su lote.
Para volver a ver todas las fechas, devuelva el lote a *Lote...*.

**Cuando el establecimiento no exige lote**, el sistema elige uno solo al descontar:
toma el **lote real que vence primero** y solo usa el grupo *sin lote* si el producto no
tiene ningún lote en esa bodega. Y si no se eligió vencimiento, la línea queda **sin
vencimiento**: ya no se guarda la fecha del día. Es el mismo criterio de la factura de
venta.

Al **abrir un recibo ya guardado**, la línea muestra el lote y el vencimiento **con
los que se emitió**, aunque el inventario haya cambiado desde entonces.

### Ver el código y la descripción completos

En la tabla de productos del recibo:

- La **descripción** crece en alto con su texto, así que se lee completa sin
  desplazarse dentro del campo. Si es muy larga (más de unas diez líneas), el
  campo muestra una barra para desplazarse.
- Las columnas **Código** y **Descripción** se pueden ensanchar o estrechar:
  - **Arrastrando** el borde derecho de su encabezado.
  - Con **doble clic** en ese mismo borde, la columna se ajusta sola al texto más
    largo del recibo (la descripción, hasta un máximo; lo que no quepa baja a
    otra línea).

El ancho que elija se **guarda para su usuario** y se mantiene en los siguientes
recibos, sin afectar a los demás usuarios, a las columnas del listado ni a los
anchos que tenga en Facturas de Venta (cada módulo guarda los suyos). Si al
ensanchar ya no cabe toda la tabla, aparece una barra para desplazarse a los lados
en lugar de achicar Cantidad, Precio o IVA.

## Qué genera

| Efecto | Detalle |
|--------|---------|
| Inventario | Descuenta stock igual que una factura |
| Cobro | Se registra como cobro de tipo recibo |
| Contabilidad | Genera asiento de venta |
| SRI | **No** se envía |

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del recibo: número, fecha,
cliente, identificación, subtotal, descuento, IVA, ICE, propina, total,
vendedor, observaciones y usuario. Además busca en el número de la **factura
generada** desde el recibo y en los **códigos y descripciones de los productos**
del recibo. Las columnas **Impuestos** (con o sin), **Estado de pago** y
**Estado** no entran en la búsqueda libre: para filtrar por ellas use la ventana
de filtros. Puede escribir varias palabras en cualquier orden y no importan
mayúsculas ni tildes. Para limpiar, borre el texto o pulse Escape en el cuadro.
Mientras busca, aparece un **círculo girando** al final del cuadro y la tabla se
ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Recibo** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, emitido, facturado, anulado), estado de pago (pendiente / abonado / pagado), serie, N° recibo, secuencial, con o sin impuestos, origen (directo, desde factura de venta, POS / caja), con o sin asiento contable, días de crédito |
| Valores | Total, saldo pendiente, subtotal, descuento, IVA, ICE y propina (cada uno con mínimo y máximo) |
| Tercero | Cliente, RUC / cédula, vendedor, usuario que registró, observaciones, factura generada |

Los selectores *Serie*, *Vendedor* y *Usuario que registró* listan solo lo que
la empresa ya usó en sus recibos. El *estado de pago* y el *saldo pendiente* se
calculan con la misma regla que la columna Estado de pago: los cobros de
Ingresos no anulados registrados contra el recibo.

**Pestaña Detalles** (lo que hay dentro del recibo). Es un único cuadro,
**Buscar libremente dentro de los recibos**: escriba un producto, un código, un
lote, una forma de pago, un plazo o un dato de la información adicional, y
aparece la lista de **cada línea que coincide** con el recibo al que pertenece
(número, fecha, cliente y estado). Un clic en la fila deja el listado mostrando
solo ese recibo; el ícono de la derecha lo abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Exportar el documento

En la barra de acciones superior del modal, junto al botón **PDF**, hay un
botón **Excel** (icono verde) que descarga el detalle, los totales y la forma
de pago de ese recibo puntual. Ambos se habilitan solo con el recibo ya
guardado.

## Cuándo no usarlo

Si la operación requiere comprobante válido para el cliente, hay que emitir
**factura**. El recibo no sustituye a un comprobante electrónico ante el SRI.

## Errores frecuentes

- **El cliente pide su factura y solo tiene un recibo**: emita la factura; el
  recibo es interno.
- **El stock bajó dos veces**: se emitió recibo *y* factura por la misma entrega.

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

- **1.17** — Con el IVA configurado **al subtotal**, el IVA de cada línea se reajusta al
  guardar para que su suma sea exactamente el IVA sobre el subtotal. La factura
  que se genera desde un recibo también respeta ahora ese modo (antes lo
  calculaba siempre línea por línea).
- **1.16** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.15** — El **buscador de clientes** muestra el **tipo de identificación**
  junto a la identificación en cada resultado, y también en la línea de datos
  del cliente una vez elegido. Sirve para distinguir un RUC de una cédula o de
  un consumidor final sin salir del recibo. Igual que en *Facturas de Venta*.

- **1.14** — **Un recibo con $0.01 de saldo queda como Abonada, no como
  Pagada.** Mismo criterio que *Cuentas por Cobrar* e *Ingresos*: hay saldo
  mientras quede al menos un centavo. Afecta al badge de la columna **Pago**, al
  filtro `pago:…` y al panel de cobro del modal.

- **1.13** — Corregido: al **abrir un recibo guardado** —o al recuperar un
  borrador— la columna **P. Con Imp.** salía en 0.00 en todas las líneas. El
  precio con impuestos se calcula ahora también en esos dos casos. No cambia
  ningún valor guardado: es solo lo que se ve en pantalla. Mismo arreglo que en
  Facturas de Venta.

- **1.12** — Igual que en la factura de venta: cuando el establecimiento **no exige
  lote**, al descontar se elige el **lote real que vence primero** en vez del grupo *sin
  lote*, y si no se eligió vencimiento la línea queda sin vencimiento (antes se guardaba
  la fecha del día).
- **1.11** — En la tabla de productos del recibo, la **descripción** se ve
  completa: crece en alto con su texto (antes quedaba en poco más de una línea y el
  resto se escondía tras una barra). Las columnas **Código** y **Descripción** se
  ensanchan arrastrando el borde del encabezado, o con doble clic para ajustarlas
  al texto más largo; el ancho se guarda por usuario. Es el mismo comportamiento de
  Facturas de Venta. Ver *"Ver el código y la descripción completos"*.
- **1.10** — Con **"La facturación afecta al inventario"** apagado en Empresa, el
  recibo ya no descuenta stock (lo hacía igual por un error al leer la opción). Con
  **"Obligatorio usar Lotes"** apagado, la línea sin lote toma el lote que vence primero.
- **1.9** — **Búsqueda del listado más rápida**: el conteo y la página salen
  de una sola consulta y el total cobrado se calcula solo para los 20 recibos visibles (antes, para todos los de la empresa en cada carga). Las fechas y los montos solo se comparan
  cuando lo escrito tiene números, así que buscar un nombre o un producto responde
  antes. Si se sigue escribiendo, la búsqueda anterior se cancela. Los resultados
  son los mismos que antes.
- **1.8** — Nuevo **buscador del listado**: la búsqueda libre recorre todas las
  columnas (incluido el IVA), la factura generada y los productos del recibo; el
  botón del embudo abre la ventana de filtros con pestañas *Recibo* y *Detalles*,
  y los filtros activos se ven como etiquetas dentro del cuadro. Se agregan los
  filtros de saldo pendiente, origen, asiento contable, días de crédito,
  vendedor y usuario como selectores y factura generada; el estado
  *Autorizado*, que un recibo nunca tiene, se reemplaza por los estados reales.
- **1.7** — La columna **Vencimiento** de cada línea se limita ahora al **lote
  seleccionado** (y elegir la fecha selecciona su lote). Antes se ofrecían todas
  las fechas del producto, con lo que podía registrarse una combinación
  lote/vencimiento inexistente en bodega. Al reabrir un recibo se conserva el
  vencimiento con el que se emitió. Las fechas se muestran en formato `d-m-a`.
- **1.6** — El módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar un recibo cuyo período esté cerrado. Antes no se
  comprobaba en ninguna de las cuatro operaciones.
- **1.5** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.4** — La **tirilla** respeta la *Presentación de los ítems* configurada en
  el módulo Empresa (pestaña Facturación): agrupa las líneas por nombre, lote o
  NUP y anexa a la descripción la unidad, el lote, la caducidad o el NUP, igual
  que ya lo hacían el PDF y el XML. Con la configuración por defecto la tirilla
  sale exactamente igual que antes: una línea por ítem.

- **1.3** — La ventana de la tirilla ya no desaparece al cancelar la
  impresión: antes el navegador avisaba igual al imprimir que al cancelar y la
  ventana desaparecía a los 2 segundos, obligando a pedir la tirilla otra vez.
  Ahora avisa de que se cerrará en 10 segundos y deja a mano **Imprimir de
  nuevo** —que reinicia la cuenta— y **Cerrar**.
- **1.2** — La tirilla se adapta al ancho de papel del driver en vez de imponer el
  suyo, con columnas de ancho proporcional y tipografía sans-serif: ya no sale
  reescalada, con los importes corridos ni con la letra entrecortada en
  impresoras térmicas de 80 mm.
- **1.1** — Botón **Excel** en la barra de acciones del modal, para exportar
  el detalle, totales y forma de pago de un recibo puntual.
- **1.0** — Versión inicial.
