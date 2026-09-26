---
titulo: Notas de crédito
resumen: Documento que anula o rebaja total o parcialmente una factura de venta ya autorizada.
categoria: Ventas
ruta_modulo: modulos/notas_credito
tipo: modulo
visibilidad: todos
etiquetas: nota de credito, notas de credito, devolucion, descuento, anular factura, corregir factura, sri, buscar nota de credito, buscador, filtros, filtrar notas de credito, buscar por producto, filtro de fechas, documento modificado, chips, aparecen notas que no busque, resultados que no corresponden, buscar por clave de acceso, lote, lotes, nup, serial, numero de serie, caducidad, vencimiento, fecha de vencimiento, devolver al inventario, reingreso de stock, devolucion de mercaderia, lote equivocado, bodega de reintegro, sin bodegas asignadas, no tiene bodegas, no se refleja en inventario, no aparece en inventario, no devolvio stock, informacion adicional, info adicional, limite de caracteres, maximo 300 caracteres, value too long, no se pudo guardar la nota, codigo, codigo del producto, columna codigo, buscar por codigo, iva, tarifa iva, iva 12, 12%, iva anterior, iva historico, factura año anterior, exento, no objeto de impuesto, tarifa 0, descuento por pronto pago, pronto pago, descuento posterior, descuento comercial, rebaja de precio, bonificacion, no afecta inventario, sin afectar inventario, sin devolver mercaderia, nota de credito sin productos, linea libre, linea manual, sin bodega, invalid input syntax for type integer, vendedor, asesor, vendedor de la nota de credito, cambiar vendedor, comision, motivo, cambiar vendedor nota autorizada, corregir vendedor, vendedor de la factura, vendedor equivocado, vendedor del cliente, imprimir, impresora
version: 1.25
orden: 30
estado: activo
---

La **nota de crédito** es el documento con el que se corrige una factura ya
emitida: una devolución de mercadería, un descuento posterior o un error en el
valor facturado.

No confundir con anular: la anulación deja la factura sin efecto por completo; la
nota de crédito rebaja una parte (o el total) dejando el rastro de por qué.

## Solo sobre facturas autorizadas

Únicamente se puede emitir una nota de crédito sobre una factura en estado
**autorizado**. Si la factura todavía está en borrador o fue rechazada por el
SRI, corrija la factura directamente: no hace falta nota de crédito.

El campo **Factura / Documento a modificar** lista solo las facturas (y saldos
iniciales) del cliente con **saldo pendiente mayor a cero**: total menos cobros,
notas de crédito y retenciones ya aplicadas. Cada opción muestra el total y el
saldo. Una factura ya pagada no aparece en la lista; si igual necesita emitir la
nota sobre ella, puede escribir el número a mano.

## No puede superar el total de la factura

La suma de **todas** las notas de crédito de una factura no puede exceder su
importe total. Si lo intenta, el sistema muestra el total acumulado y el de la
factura, y no deja guardar.

Esto contempla el caso de varias notas parciales: la tercera nota no puede
llevarse por delante lo que ya rebajaron las dos anteriores.

## Cómo se emite

1. Desde la factura, genere la nota de crédito (o, desde **Nueva Nota de Crédito**,
   elija el cliente y luego la factura).
2. Revise el **Vendedor** (junto a *Bodega Reintegro*): se llena solo y puede cambiarlo.
3. Indique el **motivo** de la devolución o el ajuste (campo *Motivo SRI*, a la derecha de
   la *Fecha del documento*).
4. Deje solo las líneas y cantidades que se devuelven o rebajan.
5. Revise el total.
6. Guarde y envíe al SRI.

### El vendedor de la nota

El campo **Vendedor** indica a qué asesor se le descuenta la nota en los reportes.

- Al elegir la **factura a modificar** se propone el **vendedor de esa factura**: la
  nota se le descuenta a quien hizo la venta, aunque hoy el cliente tenga asignado otro
  vendedor. Da lo mismo crear la nota desde este módulo o desde la factura o el recibo.
- Mientras no se elija la factura, o si el documento es un **saldo inicial** o una factura
  sin vendedor, queda el **vendedor asignado al cliente** (ficha del cliente).
- Se puede cambiar por otro o dejar en *Seleccione...* (sin vendedor).
- Solo se listan vendedores activos. Si el vendedor de la factura o de una nota ya
  guardada fue desactivado, se muestra igual con la marca *(inactivo)*.
- El **Reporte de Ventas** (filtro *Vendedor*) y el **Reporte de Ventas por Asesor**
  toman el vendedor guardado en la nota.

### Información adicional

Las filas de *Info. Adicional* (concepto / detalle) viajan en el XML y salen en el
RIDE. El **concepto** admite hasta **300 caracteres** y el **detalle** hasta
**500**; el campo no deja escribir más. Si un texto más largo llegara por otra
vía (por ejemplo, copiado desde la factura de origen), se recorta al tope en vez
de rechazar la nota.

### Columna Código

Cada línea del detalle muestra el **Código** del producto o servicio, igual que en la
factura de venta. Al cargar la factura de origen se llena solo. También sirve para
buscar: al escribir un código aparece la lista de productos y, al elegir uno, se
completan código, descripción, precio e IVA. Si escribe un código a mano sin elegir
de la lista, la línea queda como ítem libre con ese código. Editar la descripción
limpia el código, porque deja de corresponder al producto.

### El IVA de cada línea viene de la factura

Al cargar la factura de origen, cada línea toma la **tarifa de IVA con la que se
facturó**, no la tarifa actual del producto. Una factura de un año anterior con IVA
**12%** genera la nota con **12%**, aunque esa tarifa ya esté inactiva en el sistema;
en esa línea el selector de IVA muestra *12%* además de las tarifas vigentes. En una
línea nueva agregada a mano solo aparecen las tarifas vigentes.

La tarifa se reconoce por el **código del SRI**, no solo por el porcentaje: así una
línea **Exenta de IVA** o **No objeto de impuesto** conserva su código aunque las tres
tarifas de 0% tengan el mismo porcentaje.

### Cómo leer la columna Subtotal

La columna **Subtotal** de cada línea muestra el valor **neto: cantidad x precio
unitario, menos el descuento de esa línea, y sin IVA**. Es el mismo criterio de
la factura de venta y el mismo valor que viaja al XML del SRI y al RIDE.

En el pie, el **Subtotal** es el bruto (antes de descuentos) y el descuento se
resta en su propia línea, igual que en la factura de venta. Es decir: la suma de
la columna Subtotal del detalle equivale a **Subtotal menos (-) Descuento**. Si
la nota no tiene descuentos, ambos valores coinciden.

Los **Subtotal 15%**, **Subtotal 0%**, etc. del pie son bases imponibles netas:
ya tienen el descuento aplicado, de modo que el IVA que se muestra debajo es
exactamente el porcentaje de esa base.

## Si se cierra el modal sin guardar

Mientras se captura una nota de crédito nueva, el sistema va guardando un
borrador local en el navegador. Si el modal se cierra sin guardar (o si falla el
guardado por un corte de red), al volver a **Nueva nota de crédito** aparece un
aviso con el nombre del cliente y dos opciones: **Cargar borrador**, que repone
cliente, motivo, documento modificado, las líneas del detalle con sus cantidades,
precios, descuentos y tarifa de IVA, y la información adicional; o **Nueva
nota**, que descarta el borrador y empieza en blanco.

El borrador es por usuario y por empresa, y se descarta solo al guardar la nota
o al elegir "Nueva nota".

## Editar y eliminar

Solo se pueden **editar** o **eliminar** notas de crédito en estado **borrador**.
Una vez enviada y autorizada, el documento es definitivo ante el SRI: si está
mal, hay que gestionarlo como cualquier comprobante autorizado erróneo.

**Excepción: el vendedor.** En una nota ya autorizada (o anulada) el campo
**Vendedor** sigue habilitado: se elige el nuevo y se pulsa **Guardar**. Solo se
cambia el vendedor (el vendedor no va en el XML del SRI, solo se usa en los
reportes), y el cambio queda en el historial del registro. Requiere el permiso de
modificar. Funciona igual que en **Factura de Venta**.

## Efecto en el inventario y la cartera

Una nota de crédito por devolución **devuelve la mercadería al inventario** y
reduce el saldo por cobrar de esa factura. La devolución de stock ocurre solo si el
establecimiento tiene activado **"La facturación afecta al inventario"** (Empresa →
Facturación): con esa opción apagada la venta no descontó nada, así que tampoco hay
nada que devolver.

La mercadería vuelve **con el mismo lote, NUP / serial y fecha de caducidad** con que
salió en la factura que la nota modifica; no hay que digitarlos.

### La bodega de reintegro, cuando la nota devuelve productos

La mercadería vuelve a la bodega elegida en **Bodega Reintegro**. Ese campo es
obligatorio **cuando la nota lleva al menos una línea con un producto inventariable**:
sin bodega, la nota no deja guardar. Si todas las líneas son libres (sin producto) o
de servicios, la bodega no hace falta, porque la nota no mueve inventario (ver la
sección siguiente).

El combo solo muestra las bodegas a las que el usuario tiene acceso (**Bodegas →
Accesos**). Si al usuario le denegaron **todas** las bodegas, puede emitir notas por
descuento, pero no notas que devuelvan productos: al guardar aparece el aviso *"Esta
nota de crédito devuelve productos al inventario y usted no tiene bodegas
asignadas"*, y un administrador debe asignarle al menos la bodega a la que regresa la
mercadería. Una empresa que no tiene ninguna bodega (solo servicios) no se ve afectada.

La nota **guarda la bodega** a la que devolvió la mercadería: al abrirla de nuevo, el
combo muestra esa bodega, y al editar un borrador el reingreso vuelve a ella salvo
que se cambie a propósito. Si el usuario ya no tiene acceso a esa bodega, aparece con
la marca *(sin acceso)*; en un borrador hay que elegir otra para poder guardar.

### Cada ítem recuerda su línea de la factura

Al cargar la factura en la nota de crédito, cada ítem queda enlazado —sin que se vea en
pantalla— a la línea de la factura de la que viene, y devuelve **el lote y el NUP de esa
línea**. Por eso, para una devolución parcial basta con dejar las líneas que realmente
regresan y borrar las demás:

| Factura | El cliente devuelve | En la nota de crédito | Vuelve al inventario |
|---|---|---|---|
| Línea 1: lote A x 5 · Línea 2: lote B x 5 | 3 unidades del lote B | Borrar la línea 1 y poner 3 en la línea 2 | 3 al **lote B** |
| Seriales S1, S2 y S3, una línea cada uno | El serial S3 | Dejar solo la línea de S3 | El **serial S3** |

Algunos casos no tienen una línea de factura de la cual tomar el lote, y se reparten
entre los lotes que sacó la factura **en el orden en que salieron**, descontando lo que
otras notas de crédito de la misma factura ya devolvieron:

- ítems agregados con **Agregar línea manual**;
- ítems a los que se les cambió el producto o se les editó la descripción (eso rompe el
  enlace con la línea);
- líneas cuyo lote eligió el sistema al facturar, porque el establecimiento no exige lote.

Si un ítem devuelve más de lo que salió de su lote, el excedente sigue ese mismo reparto.
Lo que no se pueda atribuir a ningún lote (nota sobre un saldo inicial, factura sin lote,
o unidades de más sobre lo vendido) entra al inventario sin lote.

## Nota de crédito por descuento, sin afectar el inventario

Para rebajar el valor de una factura **sin que regrese mercadería** (descuento por
pronto pago, descuento comercial posterior, rebaja de precio acordada con el cliente,
bonificación), la nota **no debe llevar los productos de la factura**: si los lleva,
el sistema entiende que el cliente los devolvió y los vuelve a sumar al stock.

Cómo hacerla:

1. Cree la nota de crédito y elija la factura a modificar, igual que siempre.
2. Si al cargar la factura aparecen sus líneas, **bórrelas todas**.
3. Pulse **Agregar línea manual** y llene una sola línea:
   - **Descripción**: el motivo, por ejemplo *"Descuento por pronto pago factura
     001-001-000000123"*. Escríbala sin elegir nada de la lista de productos.
   - **Código**: vacío o uno propio (por ejemplo `DPP`), escrito a mano.
   - **Cantidad**: 1.
   - **Precio**: el valor del descuento **sin IVA**.
   - **IVA**: la misma tarifa con la que se facturó (si la factura tenía varias
     tarifas, una línea por tarifa con su parte del descuento).
4. **Bodega Reintegro** puede quedar vacía: una nota sin productos no la necesita.
5. Guarde y envíe al SRI como cualquier otra nota.

Una línea **sin producto** nunca mueve inventario. Tampoco lo mueve un producto marcado
como **no inventariable** (un servicio): si hace este tipo de notas a menudo, puede crear
un servicio llamado *"Descuento por pronto pago"* en Productos y elegirlo en la línea.

Lo demás funciona igual que en una nota por devolución: se autoriza en el SRI, rebaja el
saldo por cobrar de la factura, genera su asiento contable y entra en la declaración de IVA.

## Exportar el documento

En la barra de acciones superior del modal, además de **PDF** y **XML**, hay
un botón **Excel** (icono verde) que descarga el detalle y los totales de esa
nota de crédito puntual. Igual que PDF y XML, solo se habilita cuando el
documento ya está guardado.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la nota: N° nota,
secuencial, fecha, cliente, identificación, documento modificado, subtotal,
descuento, total, motivo, vendedor y usuario; además, en las observaciones. Puede escribir
varias palabras en cualquier orden y no importan mayúsculas ni tildes. Para
limpiar, borre el texto o pulse Escape en el cuadro. Mientras busca, aparece un
**círculo girando** al final del cuadro y la tabla se ve atenuada.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** Para que el cuadro
devuelva solo notas donde se vea por qué coinciden, estos datos se consultan en
la ventana de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Clave de acceso y N° de autorización | Pestaña *Nota de crédito* |
| Productos o servicios de la nota (código y descripción) | Pestaña *Detalles* |
| Correo y Estado | Pestaña *Nota de crédito* |

En un comprobante electrónico la clave de acceso y el número de autorización son
el mismo número de 49 dígitos, que lleva dentro la fecha, el RUC y el número del
documento. Al escribir un número de documento en el cuadro aparecían notas ajenas
cuya clave contenía por casualidad esa secuencia; ahora ese número solo encuentra
la nota que realmente lo tiene.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Nota de crédito** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, autorizado, anulado), correo (enviado o pendiente), serie, N° nota, secuencial, con o sin asiento contable, fecha de autorización, usuario que registró |
| Documento modificado | N° de la factura modificada, fecha de esa factura, motivo |
| Valores | Total, subtotal y descuento (cada uno con mínimo y máximo) |
| Cliente | Cliente, RUC / cédula, observaciones, N° autorización, clave de acceso, vendedor |

El selector *Usuario que registró* lista solo a quienes ya registraron notas
de crédito en la empresa, *Vendedor* solo a los vendedores que tienen notas, y
*Serie* solo las series con notas guardadas.

**Pestaña Detalles** (lo que hay dentro de la nota). Es un único cuadro,
**Buscar libremente dentro de las notas de crédito**: escriba un producto, un
código, una cantidad, un valor o un dato de la información adicional (por
ejemplo, un correo), y aparece la lista de **cada línea que coincide** con la
nota a la que pertenece (número, fecha, factura modificada, cliente y estado).
Un clic en la fila deja el listado mostrando solo esa nota; el ícono de la
derecha la abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Exportar el listado

Los botones **Excel** y **PDF** de la parte superior del listado exportan las
notas de crédito que coinciden con el buscador y el orden aplicados en ese
momento (no solo la página visible). Ambos incluyen la columna **Vendedor**.

## Errores frecuentes

- **"Solo se pueden generar notas de crédito para facturas en estado
  autorizado"**: la factura aún no fue autorizada por el SRI.
- **"La suma de las notas de crédito excede el total de la factura"**: ya hay
  notas anteriores sobre esa factura; revise cuánto queda por rebajar.
- **"Solo se pueden editar Notas de Crédito en estado borrador"**: ya fue
  enviada.
- **"Esta nota de crédito devuelve productos al inventario y usted no tiene bodegas
  asignadas"**: el usuario tiene denegadas todas las bodegas y la nota lleva productos.
  Pida al administrador que le asigne al menos una en **Bodegas → Accesos**. Si la nota
  es solo un descuento, quite los productos y use líneas libres.
- **"Seleccione la bodega de reintegro"**: la nota lleva productos inventariables y el
  campo **Bodega Reintegro** está vacío.
- **"invalid input syntax for type integer"** al guardar una nota con líneas sin
  producto: error corregido en la versión 1.20.
- **El descuento sumó stock al inventario**: la nota se hizo con los productos de la
  factura. Para un descuento use líneas sin producto (ver *Nota de crédito por
  descuento, sin afectar el inventario*).
- **La nota se emitió pero la mercadería no aparece en Inventarios**: revise que el
  establecimiento tenga activada *"La facturación afecta al inventario"* y que el
  producto sea inventariable. Las notas guardadas sin bodega antes de la versión 1.19
  no devolvieron stock y deben corregirse con una entrada manual en Inventarios.

## La fecha de emisión y el envío al SRI

Para enviar al SRI, la **fecha de emisión debe ser la de hoy**. Si intenta enviar
la nota de crédito fechada otro día, el envío se detiene antes de salir —sin gastar
un intento contra el SRI— con el aviso *"la fecha de emisión de la nota de crédito
(…) debe ser la fecha actual"*. Ponga la fecha de hoy, guarde y vuelva a enviar.

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

- **1.25** — La nota de crédito respeta ahora el cálculo del IVA configurado en la
  empresa (**línea por línea** o **al subtotal**), igual que la factura.
  Antes siempre calculaba línea por línea: al devolver completa una
  factura grande hecha al subtotal, la NC podía salir unos centavos por
  encima de la factura y el sistema la rechazaba por exceder su total.
- **1.24** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.23** — El **Vendedor** de la nota se toma de la **factura que modifica**, no del
  vendedor actual del cliente. Antes, al crear la nota desde este módulo, quedaba el
  vendedor de la ficha del cliente, y si se lo habían cambiado después de la venta, la
  nota se le descontaba a otro asesor en los reportes. Saldos iniciales y facturas sin
  vendedor siguen usando el del cliente.

- **1.22** — En una nota ya **autorizada o anulada** se puede cambiar el **Vendedor** y
  guardar (antes el campo quedaba bloqueado). Solo se actualiza el vendedor; el resto del
  documento sigue sin poder editarse. Mismo comportamiento que Factura de Venta. El
  listado suma la columna **Vendedor** (ordenable, en PDF y Excel), el filtro **Vendedor**
  en la ventana de filtros y el vendedor entra en la búsqueda libre.

- **1.21** — Nuevo campo **Vendedor** en la nota, junto a *Bodega Reintegro*: se propone
  el vendedor del cliente (o el de la factura/recibo cuando la nota se genera desde ahí) y
  se puede cambiar. El Reporte de Ventas y el Reporte de Ventas por Asesor usan ahora este
  vendedor. El **Motivo SRI** pasa a la fila del documento modificado, a la derecha de la
  *Fecha del documento*; el cursor arranca en *Cliente* y con Enter recorre factura →
  fecha → motivo → detalle. Requiere el script `database/20260924_nc_cabecera_id_vendedor.sql`,
  que completa el vendedor de las notas ya emitidas (el de su factura o, si no tiene, el
  del cliente).

- **1.20** — **Notas de crédito por descuento** (pronto pago, rebajas posteriores) sin
  afectar el inventario: una nota cuyas líneas son todas libres o de servicios ya no exige
  **Bodega Reintegro**, y un usuario sin bodegas asignadas puede emitirla (antes el botón
  **Nueva Nota de Crédito** lo bloqueaba). La bodega sigue siendo obligatoria cuando la nota
  lleva algún producto inventariable. Se corrige además el error *"invalid input syntax for
  type integer"* al guardar una nota con líneas sin producto.

- **1.19** — La **bodega de reintegro** pasa a ser obligatoria. Antes, un usuario con todas
  las bodegas denegadas veía el combo vacío y la nota se guardaba y autorizaba sin devolver
  la mercadería al inventario, sin ningún aviso. Ahora el botón **Nueva Nota de Crédito**
  avisa *"Sin bodegas asignadas"* y el guardado se rechaza si no hay bodega o si el usuario
  no tiene acceso a la elegida. Además la nota **guarda la bodega de reintegro** y la
  muestra al reabrirla (antes no se guardaba: al editar un borrador el reingreso iba a la
  bodega que tuviera el combo en ese momento). Requiere el script
  `database/20260924_nc_cabecera_id_bodega.sql`, que completa la bodega de las notas ya
  emitidas a partir del kardex.

- **1.18** — Al cargar la factura de origen, el IVA de cada línea se reconoce por el
  código del SRI de esa línea: las líneas en **0%**, **Exento** o **No objeto** ya no
  se confunden entre sí (antes podían salir todas como *No objeto*). Las facturas con
  IVA de años anteriores (12%) mantienen su tarifa.

- **1.17** — El buscador **Factura / Documento a modificar** muestra solo documentos con
  saldo pendiente mayor a cero, e indica el saldo de cada uno.

- **1.16** — El **PDF (RIDE)** de la nota usa el mismo formato de ítems que la factura de
  venta: filas compactas (antes salían demasiado altas), columna *Detalle Adicional* solo
  si algún ítem la trae y ancho del código según su contenido.

- **1.15** — El detalle muestra la columna **Código** (antes no se veía), editable y con
  búsqueda de productos, igual que en la factura de venta.

- **1.14** — Las filas de **Info. Adicional** tienen tope en pantalla (concepto
  300 caracteres, detalle 500), el largo que admite cada línea del comprobante.
  Un texto más largo que llegue por otra vía se recorta en lugar de hacer fallar
  el guardado completo de la nota sin explicación.

- **1.13** — Cada ítem cargado desde la factura queda enlazado a su **línea de factura** y
  devuelve el **lote y el NUP de esa línea**. Antes, en una devolución parcial de una factura
  con varios lotes o seriales del mismo producto, la nota repartía por orden de salida y
  podía devolver al lote o al serial equivocado. Además, al generar la nota desde la
  **Factura de Venta**, el número del documento modificado ya viene lleno (antes quedaba vacío).
  Requiere el script `database/20260919_nc_detalle_linea_factura.sql`.
- **1.12** — La devolución de stock de la nota de crédito ahora regresa el producto con
  el **lote, NUP y fecha de caducidad** de la factura original (antes entraba sin ellos).
  Las notas ya emitidas se corrigen con el script `database/nc_reparar_lote_nup_caducidad.sql`.
- **1.11** — Corregido: al buscar un **número de documento** en el cuadro aparecían
  también notas que no lo tenían. La búsqueda libre miraba dentro de la **clave de
  acceso** y del **número de autorización** —el mismo número de 49 dígitos, que lleva
  la fecha, el RUC y el número del documento— y cualquier número corto caía ahí por
  casualidad. Ahora esos dos datos se consultan en la ventana de filtros, y los
  **productos o servicios** de la nota pasan a la pestaña *Detalles*, que sí muestra qué
  línea coincidió.

- **1.10** — La nota de crédito devuelve stock solo si **"La facturación afecta al
  inventario"** está activada en el establecimiento, igual que la factura lo descuenta
  solo en ese caso.
- **1.9** — **Búsqueda del listado más rápida**: el conteo y la página salen
  de una sola consulta y la condición de búsqueda se evalúa una sola vez (antes, dos: una para contar y otra para la página). Las fechas y los montos solo se comparan
  cuando lo escrito tiene números, así que buscar un nombre o un producto responde
  antes. Si se sigue escribiendo, la búsqueda anterior se cancela. Los resultados
  son los mismos que antes.
- **1.8** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas de la nota (y en autorización,
  clave de acceso, observaciones y productos), salvo Correo y Estado. Los
  filtros pasan a una **ventana propia** (botón del embudo, se aplican con
  *Aplicar*) con dos pestañas: **Nota de crédito** (filtros por campo, con
  criterios nuevos: correo, con/sin asiento, fecha de autorización, usuario
  como lista, fecha del documento modificado, descuento, observaciones,
  autorización y clave de acceso) y **Detalles** (búsqueda dentro de los
  productos e información adicional). Los filtros activos se ven como
  etiquetas dentro del cuadro y la tabla se atenúa mientras carga.
- **1.7** — El envío al SRI comprueba ahora que la **fecha de emisión sea la de
  hoy**, como ya hacían factura de venta y liquidación de compra. Antes el documento
  salía con cualquier fecha y era el propio SRI quien lo rechazaba.
- **1.6** — El módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar una nota de crédito cuyo período esté cerrado. Antes no se
  comprobaba en ninguna de las cuatro operaciones.
- **1.5** — Corregido el botón **Nueva Nota de Crédito**: después de enviar una
  nota al SRI, al crear la siguiente el modal conservaba datos de la anterior
  (estado *Autorizado*, botón **Guardar** deshabilitado, botones de Anular y
  del SRI activos, historial y asiento contable de la nota previa, y la
  pestaña **SRI** abierta si el envío había sido rechazado). Ya no hace falta
  recargar la pantalla: el modal arranca limpio y en borrador.
- **1.4** — Corregida la recuperación del borrador local: al elegir "Cargar
  borrador" el modal se abría vacío (el aviso además mostraba el cliente como
  "desconocido"). Ahora repone todos los campos y las líneas del detalle.
- **1.3** — Corregida la columna **Subtotal** del detalle en el modal: mostraba
  el valor con el IVA incluido, por lo que no cuadraba con el Subtotal del pie
  ni con el documento emitido. Ahora muestra el neto sin impuestos. Los
  subtotales por tarifa del pie pasan a calcularse sobre la base con descuento
  aplicado, y todos los importes se redondean a 2 decimales línea por línea.
- **1.2** — Botón **Excel** en la barra de acciones del modal, para exportar
  el detalle y totales de una nota de crédito puntual (antes solo existía
  para el listado completo).
- **1.1** — Agregada la exportación a Excel y PDF del listado (no existía).
- **1.0** — Versión inicial.
