---
titulo: Transferencias de Inventario
resumen: Mueve mercadería de una bodega a otra —incluso a la bodega de otro establecimiento del mismo RUC— en un solo paso y con trazabilidad de lote y serie.
categoria: Inventarios
ruta_modulo: modulos/transferencias-inventario
tipo: modulo
visibilidad: todos
etiquetas: transferencia de inventario, traslado de mercaderia, mover stock, cambiar de bodega, pasar productos de una bodega a otra, entre bodegas, entre establecimientos, entre sucursales, entre locales, traspaso de inventario, guia de remision, acta de entrega, kardex, imprimir acta, pdf de la transferencia, logo en el acta, enviar por correo, mandar el acta por email, enlace del pdf, link para ver el acta, abrir el acta sin descargar, no llega el adjunto, confirmar recepcion, aprobar lo recibido, recibi conforme, acuse de recibo, aceptar la transferencia, rechazar la transferencia, quien recibio, conformidad del destino
version: 1.3
orden: 0
estado: activo
---

Sirve para **mover productos de una bodega a otra** sin tener que registrar una
salida y una entrada por separado en el Kardex. Si las dos bodegas están en
**establecimientos distintos** del mismo RUC, la transferencia lo detecta sola y
permite generar la **guía de remisión** con los mismos productos.

## Qué es y para qué sirve

Cada transferencia es un documento con su número (`TRF-000001`), su fecha, la
bodega de origen, la de destino, los responsables de entregar y recibir, y el
detalle de productos. Al guardarla, el sistema registra **en el mismo instante**
la salida en la bodega de origen y la entrada en la de destino: no hay estado
intermedio ni mercadería "en tránsito".

El lote, la fecha de caducidad y la serie/NUP **viajan con el producto**, así que
la trazabilidad no se pierde al cambiar de bodega.

## Requisitos previos

- Empresa activa con al menos **dos bodegas** (Inventarios → Bodegas).
- Para que una transferencia se considere **entre establecimientos**, cada bodega
  debe tener asignado su establecimiento en **Inventarios → Bodegas → campo
  Establecimiento**. Si las bodegas no tienen establecimiento, o tienen el mismo,
  la transferencia es interna y no ofrece guía de remisión.
- El usuario debe tener **acceso a las dos bodegas** (origen y destino).
- Los productos deben ser **inventariables** y tener existencias en la bodega de
  origen.

## Cómo se usa

1. Entre a **Inventarios → Transferencias de Inventario** y pulse **Nueva transferencia**.
2. Elija la **fecha**, la **bodega de origen** y la **bodega de destino**. Si las
   bodegas pertenecen a locales distintos aparece la etiqueta *Entre establecimientos*.
3. Escriba quién **entrega** y quién **recibe** (opcional, sale impreso en el acta).
4. Busque el producto por código o nombre en **Agregar producto**: la lista muestra
   el **código**, el nombre y el stock disponible en la bodega de origen, en tres
   columnas alineadas. Una vez agregado, el código queda en su propia columna del
   detalle.
5. Por cada línea, elija el **lote** (si el producto maneja lotes) y la **serie/NUP**
   (si maneja series), y escriba la **cantidad**. El sistema no deja pasar de lo
   disponible. Si el lote que busca tiene saldo en **otra bodega**, debajo del
   selector aparece una **advertencia amarilla**; púlsela para ver en qué bodega
   está y cuántas unidades tiene (ver *El lote que busco no aparece en la lista*).
6. Pulse **Registrar transferencia**. El stock se mueve en ese momento.
7. Desde el documento ya guardado puede **imprimir el acta** (PDF con las firmas de
   entrega y recepción) y, si cruza establecimientos, **Generar guía de remisión**.

## El acta en PDF

Es el documento que se imprime y se firma al entregar la mercadería. Contiene:

- **Encabezado**: el **logo** del establecimiento principal de la empresa, el nombre
  y el RUC, y a la derecha un recuadro con el tipo de documento, el número
  (*TRF-…*) y la fecha.
- **Origen y destino**: el **nombre de la bodega** de cada lado, más quién entrega,
  quién recibe, quién registró el documento y si el traslado cruza establecimientos.
  No se imprime el código ni el nombre del establecimiento junto a la bodega.
- **Detalle**: número de línea, código, producto y cantidad. Las columnas **Lote**,
  **Caducidad** y **Serie / NUP** solo aparecen si alguna línea las usa; cuando no,
  el nombre del producto ocupa ese espacio. El acta **no muestra costos**: es un
  documento de entrega física, no de valoración.
- **Observaciones** (si las hay) y el bloque de **firmas** de entrega y recepción,
  que nunca se parte entre dos páginas.
- Si la transferencia está **anulada**, lo advierte en un recuadro rojo arriba.

Si la empresa no tiene logo cargado, el encabezado sale solo con el nombre y el RUC.
El logo se sube en **Configuración → Empresa → Establecimientos**.

## Enviar el acta por correo y confirmar la recepción

Desde el listado, cada fila tiene un botón de **sobre** (📧) que abre el envío del
acta por correo. El mismo botón está en la barra de acciones del documento abierto.

**Cómo se envía**

1. Pulse el sobre en la fila de la transferencia (o abra el documento y use el
   botón de correo de la barra superior).
2. En **Usuarios del sistema** elija a quién quiere enviarle: su correo se agrega
   a la lista de destinatarios. Aparecen primero los usuarios que tienen acceso a
   la **bodega de destino**. Puede elegir varios, uno tras otro.
3. En **Para** puede además escribir correos a mano, separados por coma o punto y
   coma (sirve para alguien que no es usuario del sistema).
4. Opcionalmente escriba un **mensaje adicional** y decida si adjunta el **acta en
   PDF** (viene marcado).
5. Pulse **Enviar**.

El correo sale con la **misma configuración de correo de la empresa** que usan las
facturas (Configuración → Empresa → Correo). Lleva el resumen del traslado, el
detalle de productos, un botón **Ver el acta en PDF** y —si el acta aún está
pendiente de confirmar— un botón **Confirmar recepción**.

**El acta va dos veces: adjunta y por enlace.** Además del PDF adjunto, el correo
incluye un **enlace para abrir el acta** en el navegador (también aparece escrito
completo, por si el botón no funciona). Sirve cuando el correo del destinatario
bloquea o recorta los adjuntos, o cuando la abre desde el teléfono. Es el mismo
documento que se descarga desde el sistema, y se abre sin usuario ni contraseña
con el mismo enlace del correo. Si desmarca *Adjuntar el acta en PDF*, el correo
va solo con el enlace. La página de confirmación también tiene el botón
**Ver el acta en PDF**.

**Cómo confirma quien recibe**

El destinatario **no necesita usuario ni contraseña**: el botón del correo abre
una página con el detalle de la transferencia y dos opciones.

- **Confirmar recepción**: escribe su nombre (viene propuesto el responsable que
  recibe) y, si quiere, una observación. La transferencia queda **Recibida**.
- **Rechazar**: escribe su nombre y el **motivo** (qué faltó o qué llegó distinto).
  La transferencia queda **Rechazada**.

Cualquiera de las dos respuestas queda guardada con **nombre, fecha, hora e IP**, se
registra en la auditoría del sistema y se imprime en el acta en PDF.

**Qué NO hace la confirmación**

Confirmar o rechazar **no mueve stock ni cambia el documento**: el inventario ya se
trasladó al registrar la transferencia. La recepción es la **constancia de
conformidad** del destino, equivalente a la firma del acta. Si lo recibido no
coincide y hay que corregir el inventario, la transferencia se **anula** desde el
sistema (eso sí devuelve el stock) y se registra una nueva con lo correcto.

**Estados de recepción**

| Estado | Qué significa |
|--------|---------------|
| Pendiente | Registrada, todavía no se envió el acta por correo. |
| Enviada | El acta se envió; se espera la respuesta del destino. |
| Recibida | El destinatario confirmó que recibió conforme. |
| Rechazada | El destinatario no aceptó lo recibido y dejó el motivo. |

En el listado hay una columna **Recepción** con ese estado y un filtro para ver, por
ejemplo, solo las que están **pendientes de confirmar**. Al abrir el documento, una
franja de color arriba muestra a quién se le envió o quién confirmó, con la fecha y
el comentario. Las transferencias **anuladas** no se pueden enviar ni confirmar.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fecha | Sí | Fecha del traslado. No puede ser posterior a hoy. |
| Bodega de origen | Sí | De dónde sale la mercadería. Define el stock, los lotes y el costo. |
| Bodega de destino | Sí | A dónde entra. Debe ser distinta de la de origen. |
| Entrega (responsable) | No | Quién despacha físicamente. Aparece en el acta. |
| Recibe (responsable) | No | Quién recibe físicamente. Aparece en el acta. |
| Observaciones | No | Motivo del traslado. |
| Código | — | **No se digita**: es el código del producto, en su propia columna. Si el producto no tiene código, sale un guion. |
| Producto | Sí | Producto inventariable con existencias en la bodega de origen. |
| Lote | Depende | Obligatorio si quiere descontar de un lote concreto; muestra el saldo de cada lote. |
| Caducidad | No | Se llena sola con la del lote; viaja a la bodega de destino. |
| Serie / NUP | Depende | Para artículos serializados. Una serie = una unidad (cantidad 1). |
| Cantidad | Sí | Unidades a mover. No puede superar lo disponible. |
| Costo unitario | — | **No se digita**: lo calcula el sistema con el costo de la bodega de origen. |

## Permisos

- **Ver** (r): entrar, listar transferencias, abrir el detalle e **enviar el acta
  por correo**. Quien recibe el correo confirma sin permisos ni sesión: le basta
  el enlace, que es único por transferencia.
- **Crear** (w): registrar transferencias nuevas.
- **Actualizar** (u): anular una transferencia registrada.
- **Eliminar** (d): eliminar del listado una transferencia **ya anulada**.
- **Acceso total** (t): con este permiso ve las transferencias de toda la empresa;
  sin él solo ve las que registró el propio usuario.

Además, el usuario solo puede transferir entre bodegas a las que tenga acceso
(Inventarios → Bodegas → pestaña Accesos).

## Reglas de negocio

- **Un solo paso**: la salida de origen y la entrada de destino se graban juntas.
  Si una falla, no se graba ninguna.
- **No se edita**: una transferencia registrada no se modifica. Si algo quedó mal,
  se **anula** y se registra una nueva.
- **Anular devuelve el stock**: al anular se deshacen los dos movimientos del
  Kardex y el stock vuelve a la bodega de origen. Si la mercadería que entró al
  destino **ya se vendió o consumió**, el sistema **no deja anular** e indica que
  primero hay que reversar los documentos que la usaron.
- **Costo**: sale al costo promedio de la bodega de origen (o del lote elegido);
  si esa bodega no tiene historial de costos, se usa el costo del producto. Así el
  inventario no cambia de valor por moverse de sitio.
- **Sin asiento contable**: la mercadería no cambia de dueño ni de valor, solo de
  ubicación dentro de la misma empresa.
- **Stock exacto**: no se puede transferir más de lo disponible, ni de un lote ni
  de una serie. La validación se hace con el saldo real del Kardex y bloqueando el
  producto/bodega, de modo que dos usuarios transfiriendo el mismo producto a la
  vez no puedan dejar el stock en negativo.
- **Fecha**: se admite fecha anterior a hoy (para regularizar traslados ya hechos),
  pero nunca futura.

## Integraciones con otros módulos

- **Kardex / Movimientos de Inventario**: cada línea genera dos movimientos con
  tipo `transferencia` y referencia al documento. Se ven en Inventarios → Kardex.
- **Stock por bodega**: actualiza `productos_bodegas`, que es lo que consultan
  facturación, POS y los reportes de existencias.
- **Bodegas**: de ahí sale el establecimiento de cada bodega, que es lo que define
  si una transferencia cruza de un local a otro.
- **Guías de Remisión**: en transferencias entre establecimientos, el botón
  *Guía de remisión* abre ese módulo con la fecha, el motivo, las direcciones de
  partida y destino y los productos ya cargados. El destinatario, el transportista
  y la placa se completan ahí, y la guía se emite al SRI desde su propio módulo.
- **Reporte de Inventarios**: las transferencias aparecen como movimientos y
  cuentan en las entradas/salidas por bodega.

## Errores frecuentes

- **«Stock insuficiente en la bodega de origen»**: el saldo real del Kardex es
  menor que lo que se quiere mover. Puede que otro usuario haya facturado ese
  producto mientras el modal estaba abierto; recargue y vuelva a intentar.
- **«La bodega de origen y la de destino no pueden ser la misma»**: elija bodegas
  distintas; para corregir cantidades dentro de una misma bodega use un ajuste en
  Movimientos de Inventario.
- **«No tiene acceso a la bodega…»**: pida que le habiliten esa bodega en
  Inventarios → Bodegas → Accesos.
- **No aparece el botón de guía de remisión**: las dos bodegas están en el mismo
  establecimiento, o alguna no tiene establecimiento asignado. Revise el campo
  *Establecimiento* de cada bodega.
- **«No se puede anular: la mercadería que entró en la bodega de destino ya fue
  utilizada»**: reverse primero las facturas, consumos o transferencias
  posteriores que usaron esa mercadería.
- **Una serie no aparece en la lista**: esa serie ya no tiene saldo en la bodega de
  origen (se vendió o se transfirió antes).
- **El lote que busco no aparece en la lista**: el selector solo ofrece lotes con
  saldo en la **bodega de origen** que eligió arriba. Si ese lote se agotó ahí,
  desaparece de la lista aunque el Reporte de Inventarios lo muestre con
  existencias, porque el reporte suma **todas** las bodegas. Debajo del selector
  aparece entonces una **advertencia amarilla** con un resumen (*«Sin stock aquí;
  hay en CENTRAL 2»*); **haga clic sobre ella** y se despliega el detalle: cada
  lote, en qué bodega está y cuántas unidades tiene. Cambie la **bodega de
  origen** a esa y el lote volverá a aparecer en la lista. Vuelva a pulsar la
  advertencia para plegarla. Dos precisiones:
  - El aviso solo nombra bodegas a las que usted tiene acceso.
  - Si el número que vio en el reporte era de las columnas **Consignación** o
    **Stock Total**, esa mercadería está en poder del cliente y no se puede
    transferir; solo es movible lo que aparece en la columna **Stock**.
- **«No se pudo enviar el correo. Verifique la configuración de correo de la
  empresa»**: falta o está mal la configuración de **Configuración → Empresa →
  Correo** (es la misma que usan las facturas). Pruebe primero enviando una
  factura por correo.
- **El destinatario dice que el enlace «no es válido o ya no está disponible»**:
  la transferencia se eliminó, o la empresa está inactiva. Si ya la confirmó o
  rechazó antes, la página se lo indica y no deja responder dos veces.
- **El correo llegó sin el botón de confirmar**: la recepción ya estaba resuelta
  (recibida o rechazada) al momento del envío; en ese caso el correo va solo como
  copia del acta.
- **No llega el correo**: revise la carpeta de correo no deseado del destinatario
  y que la dirección esté bien escrita. El envío queda registrado en el documento
  ("Acta enviada a…"), así que ahí se ve a qué dirección salió.

## Historial de cambios

- **1.3** — El selector de lote muestra una **advertencia** cuando el lote que se
  busca **tiene saldo en otra bodega**, para no dar por perdido un stock que solo
  está en otro sitio. Plegada resume la situación en una línea; al **hacer clic**
  se despliega el detalle con cada lote, su bodega y sus unidades. Solo se nombran
  bodegas a las que el usuario tiene acceso.
  El **código del producto** pasa a tener **columna propia** en el detalle de la
  transferencia (antes iba pegado al nombre), se alinea en su propia columna en el
  buscador de productos, y ahora también sale en el **correo del acta**, que hasta
  ahora listaba los productos solo por nombre.

- **1.2** — La transferencia se puede **enviar por correo** desde cada fila del
  listado (o desde el documento abierto), eligiendo usuarios del sistema o
  escribiendo correos a mano, con el acta en PDF **adjunta y también como
  enlace** para abrirla en el navegador. El destinatario
  **confirma o rechaza la recepción** desde un enlace del correo, sin necesidad de
  usuario, y esa respuesta queda con nombre, fecha, hora e IP, se ve en el listado
  (columna **Recepción**, con filtro) y se imprime en el acta.

- **1.1** — Se rehízo el **acta en PDF**: ahora lleva el logo de la empresa y un
  recuadro de documento con el número y la fecha; el origen y el destino muestran
  solo el nombre de la bodega (antes se añadía el establecimiento entre paréntesis);
  se quitaron las columnas de **costo unitario** y **costo total** del detalle, y
  las de lote, caducidad y serie solo salen cuando se usan. Además se corrigió el
  ancho de las tablas, que hacía que parte de la información quedara **fuera de la
  hoja** y no se imprimiera.
- **1.0** — Versión inicial: transferencias entre bodegas y entre establecimientos
  del mismo RUC, con lote/caducidad/serie, costo automático del origen, acta en
  PDF, anulación con reverso de stock y generación opcional de guía de remisión.
  Se agrega el campo **Establecimiento** al módulo de Bodegas.
