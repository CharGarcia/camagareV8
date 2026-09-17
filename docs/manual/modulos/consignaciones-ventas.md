---
titulo: Consignaciones de venta
resumen: Mercadería entregada a un cliente que sigue siendo de la empresa hasta que se vende.
categoria: Ventas
ruta_modulo: modulos/consignaciones-ventas
tipo: modulo
visibilidad: todos
etiquetas: consignacion, consignaciones, buscar consignacion, buscador, filtros, filtrar consignaciones, buscar por producto, buscar por lote, buscar por NUP, chips, asesor, vendedor, vendedor del cliente, asesor automatico, mercaderia en consignacion, entrega, deposito, liquidar, facturar consignacion, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, cargar desde pedido, llamar pedido, lote, vencimiento, caducidad, fecha de vencimiento, NUP, acceso total, registros propios, solo mis documentos, quien ve que, permiso actualizar, no puedo guardar, boton guardar no aparece, no tengo permiso para esta accion, demora al guardar, guardar lento, se queda guardando, estado del pedido, pedido procesado, pedido pendiente, eliminar consignacion, editar consignacion, no puedo eliminar la consignacion, documentos relacionados, el stock no volvio, devolver stock, costo promedio, kardex anulado
version: 1.20
orden: 45
estado: activo
---

Una **consignación** es mercadería entregada a un cliente que **sigue siendo de la
empresa** hasta que él la vende. No es una venta: es un traslado de custodia.

Por eso no genera factura al entregarla; la factura llega después, cuando el
cliente reporta lo vendido.

## El recorrido

1. **Entrega**: se registra qué se le deja al cliente.
2. **Seguimiento**: se controla qué le queda y qué vendió.
3. **Facturación**: lo vendido se factura desde el módulo de facturación de
   consignaciones.

## La entrega

Al registrar la entrega queda constancia de la mercadería, la fecha y el cliente.
Cuando la entrega se hace desde la aplicación móvil, se guarda además la
evidencia: ubicación, hora y firma de quien recibe.

Marcar una entrega como realizada desde la web también deja registro del usuario,
la hora y el canal.

## El asesor se llena solo al elegir el cliente

Al seleccionar un cliente en el buscador del comprobante, el campo **Asesor** se
completa automáticamente con el vendedor asignado a ese cliente (en *Clientes →
Vendedor*), igual que en las facturas de venta. Siempre se puede cambiar a mano
después.

Pasa lo mismo **al cargar un pedido**: el cliente entra desde el pedido y el
Asesor se completa con el vendedor de ese cliente. Si antes de cargar el pedido
ya había un asesor elegido, no se toca.

Si el vendedor asignado al cliente **no está en la lista** del campo —porque está
inactivo, o porque el usuario no tiene **Acceso total** y ese vendedor no le
pertenece— igualmente se selecciona: se agrega a la lista solo para ese
documento. Así la consignación queda con el asesor correcto del cliente en vez de
quedarse en blanco. Si el cliente no tiene vendedor asignado, el campo se deja
como estaba, para no borrar lo que ya estaba elegido ni el valor marcado como
favorito con la estrella.

## Cargar ítems desde un pedido

El botón **Cargar desde Pedido** trae a la consignación las líneas pendientes de un
pedido, con su cantidad y su precio ya cargados. De cada línea se elige la
cantidad a despachar y, en los productos que manejan lote, el **lote**, la **fecha
de vencimiento** y el **NUP**.

**El vencimiento depende del lote**, igual que al cargar una línea a mano en el
detalle de la consignación. Mientras no se elija lote, la lista muestra todas las
fechas disponibles del producto en esa bodega. Al elegir un lote, la lista de
vencimiento queda **acotada a la fecha de ese lote**: no es posible guardar una
combinación que no exista en bodega. También funciona al revés —elegir primero la
fecha selecciona su lote—. Para volver a ver todas las fechas, devuelva el lote a
*Lote...*.

Como al elegir el lote el vencimiento se llena solo, **el cursor salta directo al
NUP**: es el único dato que queda por teclear en esa línea. Lo mismo si se elige
primero la fecha. Si el lote no tiene vencimiento registrado, el cursor no se
mueve, porque esa fecha queda pendiente.

Al **abrir una consignación ya guardada**, cada línea muestra el lote y el
vencimiento **con los que se guardó**, aunque el inventario haya cambiado desde
entonces: manda el documento, no el catálogo.

Cada línea muestra el **stock disponible** en la bodega seleccionada (verde si
alcanza para todo lo pendiente, rojo si no). La casilla **Desagr.** parte la
línea en una fila por unidad, para asignar un NUP distinto a cada una; conviene
desmarcarla en cantidades grandes.

No es todo o nada: las filas a las que les falte lote, vencimiento o NUP (cuando
la empresa los exige) se omiten y se listan al final, pero las filas completas sí
se agregan.

### El pedido cambia de estado solo

Al guardar la consignación, el pedido pasa a **Procesado** cuando todas sus líneas
quedaron cubiertas por lo consignado, y vuelve a **Pendiente** si al editar o
eliminar la consignación alguna línea deja de estarlo. Solo se revisan los pedidos
que usa esa consignación: un pedido **anulado** no cambia, y el estado que se haya
puesto a mano en cualquier otro pedido tampoco.

## Contabilidad

Una consignación **no es una venta**, así que su asiento no registra ingresos: es
una **reclasificación de inventario a costo**, es decir, mercadería que sale del
almacén propio pero sigue siendo un activo de la empresa.

## Exportar

En la pestaña General del comprobante, junto al botón **PDF**, hay un botón
**Excel** que descarga el detalle de la consignación (producto, bodega, lote,
NUP, cantidad entregada, retornada y facturada) en una hoja de cálculo.
Requiere que la consignación esté guardada.

### Cómo se arma el PDF

Debajo del listado de productos, en este orden:

1. **TOTAL ÍTEMS**, la suma de las cantidades entregadas, al pie de la columna
   *Cantidad*.
2. Las **observaciones** del documento, a ancho completo (antes iban apretadas
   arriba, junto a los datos del cliente). Si la consignación no tiene
   observaciones, ese recuadro no se dibuja.
3. Las firmas.

Las firmas son tres en línea —**Emitido por**, **Responsable de traslado** y
**Recibí conforme**— y, debajo, una cuarta para la **verificación de
acondicionamiento**.

Bajo *Emitido por* sale el nombre del **usuario que registró la consignación**,
no el de quien imprime el documento ni el de la empresa: el dato queda fijo con
el comprobante, así que reimprimirlo meses después sigue mostrando a quien lo
emitió. *Recibí conforme* y la verificación de acondicionamiento van **sin
nombre impreso**: los escribe y firma a mano quien recibe la mercadería.

Si el contenido llega muy abajo, las firmas pasan a una página nueva en vez de
montarse sobre la tabla.

Cuando la consignación tiene **muchos productos**, el listado continúa en las
páginas siguientes y **cada página repite la fila de encabezados** (Código,
Descripción, Bodega, Lote, NUP, Cantidad, Ret, Fact, Acon.).
*Ret* es lo retornado y *Fact* lo facturado; la columna Descripción ocupa todo
el ancho que dejan libre las demás. La **fecha de caducidad no se imprime** en
el comprobante aunque la línea la tenga registrada: ese espacio lo aprovecha la
Descripción. La caducidad sigue visible en el modal del documento. Ninguna fila se parte entre dos hojas, y el TOTAL ÍTEMS, las
observaciones y las firmas se mantienen completos: si no caben en lo que resta
de página, pasan enteros a la siguiente.

Si la empresa usa una **plantilla propia** (módulo *Plantillas de Documentos*),
manda esa plantilla y no este diseño; ahí el usuario emisor es el campo
`{cg_emitido_por}` y el total de ítems, `{total_items}`.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la consignación:
fecha, número (serie y secuencial), cliente, RUC o cédula, asesor y
observaciones. Además busca en el responsable de traslado, los puntos de
partida y llegada, el total, el usuario que la registró, los **productos
consignados** (código, nombre, lote y NUP) y los **documentos relacionados**:
facturas de consignación (su número y el de la factura SRI), retornos y
cambios de producto. La columna **Estado** no entra en la búsqueda libre: para
filtrar por ella use la ventana de filtros. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes. Para limpiar, borre el
texto o pulse Escape en el cuadro. Mientras busca, aparece un **círculo
girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Consignación** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), fecha de entrega, estado (borrador, emitida, entregada, anulada), serie, Nº consignación, secuencial, con o sin asiento contable, con o sin factura de consignación, usuario que registró |
| Valores | Total, subtotal e IVA (cada uno con mínimo y máximo) |
| Cliente | Cliente, RUC / cédula, asesor, responsable de traslado, punto de llegada, observaciones |

Los selectores *Serie*, *Usuario que registró*, *Asesor* y *Responsable de
traslado* listan solo lo que la empresa ya usó en sus consignaciones. *Con
factura de consignación* cuenta las facturas que no están anuladas.

**Pestaña Detalles** (lo que hay dentro de la consignación). Es un único cuadro,
**Buscar libremente dentro de las consignaciones**: escriba un producto, un
código, un lote, un NUP, una bodega o el número de una factura, retorno o cambio
relacionado, y aparece la lista de **cada coincidencia** con la consignación a
la que pertenece (número, fecha, cliente y estado). Un clic en la fila deja el
listado mostrando solo esa consignación; el ícono de la derecha la abre
directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. El PDF y el Excel del listado salen con los
mismos filtros.

## Qué habilita cada permiso

- **Ver**: abrir el listado y los documentos, con su PDF, su Excel y el envío
  por correo.
- **Crear**: el botón **Nueva**.
- **Actualizar**: guardar los cambios de un documento existente y mover su
  **estado** (entregar / anular). **No hace falta tener además *Crear***.
- **Eliminar**: el botón **Eliminar** del documento.

El formulario muestra solo lo que el permiso permite: sin *Actualizar*, los
campos, el botón **Guardar** y el selector de estado quedan en solo lectura; sin
*Eliminar*, el botón no aparece.

*Acceso total* no reemplaza a ninguno de los cuatro: solo amplía **qué
documentos** se ven (ver el punto siguiente).

## Quién ve cada documento

Depende del permiso **Acceso total** del módulo (se administra en
*Configuración → Permisos de módulos*):

- **Con acceso total**: ve y gestiona los documentos de toda la empresa.
- **Sin acceso total**: solo los que creó ese mismo usuario. El listado ya los
  filtraba; ahora el alcance es el mismo también al **abrir un documento, su
  PDF, su Excel, enviarlo por correo, cambiarle el estado o eliminarlo**. Si se
  intenta llegar a un documento ajeno por un enlace directo, el sistema
  responde *«No tiene permiso sobre este registro: lo creó otro usuario»*.

El superadministrador (nivel 3) siempre ve todo.

## Editar o eliminar una consignación

Solo se edita una consignación en **Borrador** (el selector de estado la devuelve a
Borrador). Al guardar la edición, o al eliminarla, las salidas de inventario que
tenía **se anulan**: la mercadería vuelve a la bodega exactamente como salió y al
mismo costo, y al editar se registra de nuevo con las cantidades nuevas. En el
Kardex esas salidas quedan como **ANULADO** (se ven con *Ver anulados*), igual que
cuando se edita una factura de venta. El asiento de la consignación se calcula
solo con las salidas vigentes.

Las consignaciones editadas antes de este cambio pueden tener en el Kardex
entradas **Consignación (editada)**: eran el reverso que se registraba al
editar. Al volver a editar o eliminar una de ellas, esas entradas también se
anulan junto con las salidas, así que el stock queda bien.

**No se puede editar ni eliminar** mientras la consignación tenga documentos
vigentes que la usan: retornos (emitidos o en borrador), facturaciones de
consignación (en borrador o facturadas) o cambios de productos que entregan desde
ella. El mensaje dice cuáles son: hay que anularlos o eliminarlos primero. Los
documentos **anulados** no cuentan.

## Errores frecuentes

- **«No se puede eliminar (o editar) la consignación porque tiene documentos
  relacionados»**: la consignación ya tiene retornos, facturaciones o cambios de
  productos vigentes. Anule o elimine los documentos que nombra el mensaje y
  vuelva a intentarlo.
- **La consignación no aparece en ventas**: es correcto, no es una venta hasta
  que se factura.
- **El stock bajó pero no hay venta**: es el comportamiento esperado; la
  mercadería salió de la bodega.
- **No puedo facturar lo consignado**: use el módulo de facturación de
  consignaciones, no el de facturas de venta.

## El número no se puede repetir

El número que se ve al abrir una consignación nueva es una **vista previa**: el
número definitivo lo asigna el sistema **al guardar**, no antes.

Por eso, si dos personas abren el formulario a la vez —o si uno lo deja abierto
un rato mientras otro emite—, cada consignación recibe un número distinto: la
segunda toma el siguiente libre en el momento de guardar. Puede entonces guardarse
con un número diferente al que mostraba la pantalla; el mensaje de confirmación
dice cuál quedó.

La base de datos rechaza además, por su cuenta, cualquier intento de guardar dos
consignaciones activas con el mismo número en la misma serie. Si eso llega a
ocurrir aparece *«El número … ya está en uso. Vuelva a guardar para tomar el
siguiente número libre»*: basta con volver a pulsar **Guardar**.

Una consignación **eliminada** libera su número, que se volverá a ofrecer; una
**anulada** lo conserva.

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

Lo que mueve inventario o contabilidad no puede tocar un período ya cerrado.
Registrarla, modificarla y eliminarla se rechazan si la fecha cae en un mes
cerrado.

Al modificar se revisan **las dos fechas** —la nueva y aquella con la que está
registrado—: mover un documento de un mes cerrado a uno abierto lo alteraría
igual. Los períodos se abren y se cierran en **Contabilidad → Períodos
Contables**; reabrir el período permite la operación de inmediato.

## Historial de cambios

- **1.20** — La búsqueda del listado y la de la pestaña **Detalles** ya no se quedan
  cargando en empresas con muchas consignaciones: antes, con decenas de miles de
  consignaciones, podían tardar minutos sin mostrar respuesta; ahora contestan en
  alrededor de un segundo y encuentran exactamente lo mismo. Si se sigue escribiendo
  mientras busca, la búsqueda anterior se cancela y solo se muestra la última.
- **1.19** — Eliminar una consignación ahora **devuelve el inventario a la
  bodega** (antes el stock quedaba descontado). Editarla ya no duplica el costo del
  asiento ni baja el costo promedio de los productos: las salidas anteriores se
  anulan (quedan como ANULADO en el Kardex) en lugar de registrar entradas de
  reverso sin costo (las de consignaciones editadas antes también se anulan al
  volver a editarlas o eliminarlas). No se puede editar ni eliminar mientras tenga retornos,
  facturaciones o cambios de productos vigentes. Al editar, el stock disponible
  que muestra el formulario cuenta lo que la propia consignación ya tiene tomado.
  Nueva sección *Editar o eliminar una consignación*.
- **1.18** — Guardar, editar o eliminar una consignación ya no demora en las
  empresas que trabajan con pedidos: en cada guardado se revisaban **todos** los
  pedidos de la empresa enlazados alguna vez a una consignación, y podía tardar
  minutos; ahora solo los que usa esa consignación. Un pedido **anulado** ya no
  vuelve a Pendiente ni a Procesado al editar o eliminar la consignación que lo
  consumía. Nueva sección *El pedido cambia de estado solo*.
- **1.17** — Lo que un [Cambio de productos](modulos/cambio-producto-cv) entrega
  desde la consignación ahora queda registrado en Facturación de consignaciones:
  en el resumen de la consignación, el PDF y el Excel aparece como **Facturación**
  (con la factura de venta de lo que el cliente devolvió) en lugar de *Cambio de
  producto*. El saldo es el mismo. Como toda consignación con factura asociada,
  ya no se puede cambiar su estado.
- **1.16** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas de la consignación y además en los
  productos consignados (código, nombre, lote, NUP) y en las facturas, retornos
  y cambios relacionados, salvo la columna Estado. Los filtros pasan a una
  **ventana propia** (botón del embudo, se aplican con *Aplicar*) con dos
  pestañas: **Consignación** (filtros por campo, con criterios nuevos: fecha de
  entrega, Nº consignación, con/sin asiento, con/sin factura de consignación,
  usuario, subtotal, IVA, punto de llegada, observaciones, y asesor y
  responsable de traslado como listas) y **Detalles**, un cuadro de **búsqueda
  libre dentro de las consignaciones** que dice a qué consignación pertenece
  cada coincidencia. Los filtros activos se ven como etiquetas dentro del
  cuadro. Nueva sección *Buscar y filtrar el listado*.

- **1.15** — PDF del documento: se quita la columna **Caducidad**; el ancho
  que se libera lo toma la **Descripción**. La fecha de caducidad se sigue
  guardando y mostrando en el modal, solo deja de imprimirse.
- **1.14** — PDF del documento: se quita la columna **Cambio** y las columnas
  *Retorno* y *Facturados* pasan a llamarse **Ret** y **Fact**; el ancho que
  se libera lo toma la **Descripción**, que ahora se lee completa con menos
  cortes de línea. Lo entregado a cambio sigue descontándose del saldo.
- **1.13** — La pestaña **Resumen** (kardex de la consignación) muestra un
  movimiento **Cambio de producto** por cada unidad que el cliente se quedó como
  reposición en un [Cambio de productos](modulos/cambio-producto-cv); baja del
  saldo igual que una facturación. El PDF y el Excel del documento tienen la
  columna **Cambio** junto a *Retorno* y *Facturados*.
- **1.12** — El PDF de una consignación con muchos productos ya no sale
  troceado. A partir de unas 18 líneas, el documento se partía en decenas de
  hojas con un solo dato cada una (25 productos llegaban a producir 23 páginas,
  60 productos, 338) y el total, las observaciones y las firmas quedaban sueltos
  en hojas aparte. Ahora el listado continúa de forma normal en las páginas
  siguientes, repitiendo los encabezados de columna, y esos tres bloques se
  dibujan completos. De paso, las descripciones largas ya no se recortan y un
  lote, NUP o código más ancho que su columna se ajusta dentro de la celda en
  vez de montarse sobre la siguiente.

- **1.11** — El permiso **Actualizar** ya sirve por sí solo: para guardar el
  cambio de una consignación existente también se exigía *Crear*, así que quien
  solo podía corregir recibía *«No tiene permiso para esta acción»*. Además, el
  formulario respeta los permisos: sin *Actualizar* los campos, **Guardar** y el
  selector de estado quedan en solo lectura, y **Eliminar** aparece solo con
  permiso de eliminar (antes se mostraban siempre y el error salía al pulsarlos).

- **1.10** — En el celular, la lista de productos ya no desaparece al buscar
  por código o descripción. Cuando el teclado tapa el campo del detalle —la tabla
  de productos queda en la parte baja del formulario— el formulario sube el campo
  por encima del teclado y la lista aparece pegada a él, justo debajo (o justo
  encima, si abajo no queda sitio).
- **1.9** — Cambios en el PDF: la firma **Entregado por** pasó a llamarse
  **Emitido por** y muestra el nombre del usuario que registró la consignación
  (antes salía el nombre de la empresa); se agregó una cuarta firma,
  **verificación de acondicionamiento**; **Recibí conforme** ya no imprime el
  nombre del cliente, se llena a mano; las **observaciones** se movieron debajo
  del listado de productos, a ancho completo; y al pie de la columna *Cantidad*
  aparece el **total de ítems**. Además, al cargar ítems desde un pedido, elegir
  el lote lleva el cursor directo al **NUP**.

- **1.8** — El **número ya no se puede repetir**: lo asigna el servidor al
  guardar (antes se guardaba el de la vista previa, así que dos formularios
  abiertos a la vez podían tomar el mismo) y la base lo rechaza si aun así
  coincidiera. Además, el **Asesor** también se completa al cargar un pedido, y
  el vendedor asignado al cliente se selecciona aunque no esté en la lista
  (inactivo o de otro usuario) en vez de dejar el campo en blanco.
- **1.7** — Al seleccionar un cliente, el campo **Asesor** se completa
  automáticamente con el vendedor asignado a ese cliente (antes había que
  elegirlo a mano en cada consignación).
- **1.6** — El permiso **Acceso total** ahora manda también fuera del
  listado: sin él, un usuario ya no puede abrir, exportar, enviar por correo,
  cambiar de estado ni eliminar una consignación creada por otro usuario, ni
  siquiera con el enlace directo. Igual para la firma de una entrega.
- **1.5** — La regla del vencimiento por lote se aplica también al **detalle de la
  consignación** (las líneas cargadas a mano), no solo a las que vienen de un
  pedido. Al abrir una consignación guardada se respeta el lote y el vencimiento
  con los que se registró.
- **1.4** — Al cargar ítems **desde un pedido**, la lista de **fecha de
  vencimiento** se limita ahora al **lote seleccionado** (y elegir la fecha
  selecciona su lote). Antes se ofrecían todas las fechas del producto, con lo
  que podía quedar guardada una combinación lote/vencimiento inexistente en
  bodega. Las fechas se muestran además en formato `d-m-a`.
- **1.3** — El módulo respeta ahora el **cierre contable**: no se puede operar
  sobre una consignación cuyo período esté cerrado. Antes no se comprobaba.
- **1.2** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.1** — Nuevo botón **Excel** en la barra de acciones del comprobante,
  junto al de PDF: descarga el detalle de la consignación en una hoja de
  cálculo.
- **1.0** — Versión inicial.
