---
titulo: Pedidos
resumen: Registra lo que un cliente encarga, con fecha y hora de entrega y responsable, para después entregarlo en consignación o facturarlo.
categoria: Ventas
ruta_modulo: modulos/pedidos
tipo: modulo
visibilidad: todos
etiquetas: pedidos, pedido desde proforma, proforma a pedido, enviar a pedidos, generar pedido desde cotizacion, pedido de cliente, vendedor del cliente, asesor, quien atiende al cliente, solicitado por, quien hizo el pedido, usuario que registro el pedido, buscar pedidos, buscador, filtros, filtrar pedidos, buscar por producto, buscar por cliente, ordenar por estado y fecha de entrega, ordenar por dos columnas, encargo, orden de pedido, reserva, entregas, despacho, agenda de entrega, hora de entrega, responsable de entrega, rango horario, pedidos pendientes, aparecen pedidos que no busque, resultados que no corresponden, buscar por producto en el listado, imprimir, impresora
version: 1.11
orden: 0
estado: activo
---

Un pedido es el compromiso de entregar algo: qué productos, a qué cliente, qué día
y en qué rango de horas, y quién lo lleva. No mueve inventario ni maneja precios —
eso ocurre después, cuando el pedido se convierte en una **consignación de venta**
o en una **factura**. Sirve para organizar la agenda de entregas y para que nadie
despache de memoria.

## Qué es y para qué sirve

Está en el menú **Ventas → Pedidos**. Se usa para:

- Anotar el encargo del cliente con su fecha y su ventana horaria de entrega.
- Asignar el responsable que hará la entrega.
- Saber qué queda pendiente de despachar (el contador de pendientes del sistema
  se alimenta de aquí).
- Servir de origen para la consignación o la factura, sin volver a teclear las
  líneas.

Los pedidos son por empresa: solo se ven los de la empresa activa.

## Requisitos previos

- **Puntos de emisión con secuencial para Pedidos** (Empresa → Puntos de emisión).
  Sin eso el pedido no puede numerarse y el sistema lo avisa al abrir el formulario.
- **Clientes** registrados (se pueden crear desde el propio modal del pedido).
- **Productos** registrados.
- **Responsables de traslado**, que también se pueden crear al vuelo desde el pedido.

## Cómo se usa

1. **Nuevo** abre el formulario. La serie y el secuencial se llenan solos según el
   punto de emisión.
2. Busque el **cliente** por nombre o identificación. Al elegirlo, a la derecha del
   título *Cliente* —sobre el propio campo— aparece en letra pequeña el **vendedor
   (asesor)** que tiene asignado ese cliente. Es solo informativo —no se edita desde
   aquí— y si el cliente no tiene vendedor asignado, no aparece nada.
3. Ponga la **fecha del pedido**, la **fecha de entrega** y el **rango horario**
   (hora inicial y hora máxima).
4. Elija el **responsable de entrega**.
5. Agregue los **productos** con su cantidad. No se piden precios. Escriba dos o más
   caracteres en **Código** o en **Descripción** y aparece la lista de coincidencias
   del catálogo, justo debajo del campo (o encima, si abajo no cabe); toque o haga
   clic en una para cargarla en la línea. En el celular, si el teclado tapa el campo, el
   formulario lo sube solo para dejar sitio y la lista sale igualmente pegada a él.
6. Guarde. El pedido nace en estado **Pendiente**.
7. Para modificarlo, haga clic en la fila del listado.

Desde el formulario de un pedido guardado, la barra superior permite descargarlo en
**PDF** o **Excel** y **enviarlo por correo** al cliente (la dirección se puede
editar antes de enviar).

En el recuadro de datos del **PDF**, la última línea lleva el **Vendedor** del cliente
a la izquierda y, a la derecha, **Solicitado por**: el usuario que registró el pedido
en el sistema. Son dos cosas distintas — el asesor asignado al cliente y quien tomó el
encargo — y pueden no coincidir.

### Las pestañas del pedido

El formulario está dividido en dos pestañas, debajo de la barra de acciones:

- **General**: el pedido en sí — serie y secuencial, cliente, fechas y horas de
  entrega, responsable, observaciones y las líneas de productos. Es lo que se
  llena y se guarda, y es la pestaña que se abre siempre.
- **Detalle**: quién registró el pedido y qué se le ha cambiado desde entonces.
  No se edita nada aquí; es solo de consulta.

La pestaña **Detalle** se puede ocultar con el **engranaje** que está a la derecha
de las pestañas, y cada usuario decide si la quiere ver: la preferencia se guarda
para esa persona y esa empresa. *General* no se puede ocultar.

### Qué muestra la pestaña Detalle

Arriba, dos fichas:

- **Creado**: quién levantó el pedido y cuándo.
- **Última edición**: quién lo modificó por última vez, cuándo, y —lo importante—
  **en qué consistió ese cambio**. No basta con el nombre: al pedido lo pueden
  modificar desde dos sitios distintos (ver más abajo), así que la ficha dice la
  acción y los campos que cambiaron, por ejemplo *Estado actualizado al guardar la
  consignación 001-001-000000123 · Estado: Pendiente → Procesado*.

Si el pedido nunca se modificó desde que se creó, la ficha lo dice. Y si el último
cambio es anterior al registro de cambios, o lo hizo una migración, avisa que ese
cambio **no quedó detallado** en vez de atribuirle algo al usuario.

Debajo, la lista de **ediciones del pedido**, de la más reciente a la más antigua.
Cada línea dice qué pasó, quién lo hizo, la fecha y hora, y **qué cambió**, campo
por campo, con el valor anterior tachado y el nuevo al lado.

### Quién puede modificar un pedido

El pedido cambia por dos caminos, y los dos quedan en la lista de ediciones:

| Qué ocurrió | Cómo aparece |
|---|---|
| Alguien abrió el pedido y lo guardó | *Pedido editado*, con los campos que tocó |
| Alguien guardó o eliminó una **consignación** que usa líneas de este pedido, y eso cambió su estado | *Estado actualizado al guardar la consignación 001-001-000000123* |

El segundo caso es automático: nadie abrió el pedido, pero el sistema recalcula si
quedó **Procesado** (todas sus líneas ya salieron en consignación) o vuelve a
**Pendiente**. Por eso la última edición puede estar a nombre de alguien que nunca
abrió el pedido; la ficha lo aclara diciendo qué hizo.

Dos aclaraciones más sobre la lista:

- Registra los cambios de la **cabecera** del pedido: cliente, fechas, horas,
  responsable, estado, serie y observaciones. Lo que ocurrió con cada **línea de
  producto** —si ya se entregó en una consignación o se facturó— se consulta con
  el ícono de historial de la propia línea, en *General*.
- Solo aparecen los cambios ocurridos **desde que el pedido empezó a registrarse en
  la bitácora**. Los pedidos más antiguos, y los que vinieron de una migración,
  pueden mostrar *No hay ediciones registradas*.

En un pedido nuevo, que todavía no se ha guardado, la pestaña avisa que aún no hay
nada que mostrar.

### El listado

- **Buscador**: texto libre, ventana de filtros y búsqueda dentro de los pedidos;
  se explica en *Buscar y filtrar el listado*.
- **Orden**: se ordena haciendo clic en cualquier encabezado y el sistema recuerda
  su elección para la próxima vez. De fábrica muestra **lo más reciente primero**
  (por fecha de emisión).
- **Orden por varias columnas**: mantenga presionada la tecla **Shift** (⇧) y pulse
  el título de una segunda columna para encadenarla — por ejemplo *Estado* y dentro
  de cada estado la *Fecha de entrega*, que es la vista natural para despachar. Se
  pueden encadenar hasta tres; el número junto a cada flecha indica cuál manda. Un
  tercer Shift+clic sobre la misma columna la saca del orden. Detalles en *Cómo
  ordenar los listados*.
- **La columna Estado no se ordena alfabéticamente**, sino por el flujo del pedido:
  ascendente muestra primero lo que falta atender — Pendiente, Procesado,
  Facturado, Anulado — y descendente lo invierte. Eso se mantiene también cuando
  el estado se combina con otras columnas.
- **Columnas**: cada usuario puede ocultar las que no usa y ajustar su ancho.
- **PDF y Excel** exportan lo que está en pantalla, con el mismo filtro y el mismo
  orden. El tope es de **500 filas**: si la búsqueda trae más, el sistema pide
  acotarla antes de descargar.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del pedido: número, fecha
de emisión, fecha de entrega, rango horario, cliente, responsable de entrega,
observaciones y observaciones internas. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes. Para limpiar, borre el texto
o pulse Escape en el cuadro. Mientras busca, aparece un **círculo girando** al
final del cuadro y la tabla se ve atenuada. Si sigue escribiendo, la búsqueda
anterior se descarta sola: siempre manda la última.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** El cuadro devuelve
solo pedidos donde se vea por qué coinciden; el resto se consulta en la ventana
de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Códigos y nombres de los productos pedidos | Pestaña *Detalles* |
| N° de las consignaciones y facturas que tomaron el pedido | Pestaña *Detalles* |
| Identificación / RUC del cliente | Pestaña *Pedido* → **RUC / cédula** |
| Usuario que registró | Pestaña *Pedido* |
| Estado | Pestaña *Pedido* |

La pestaña *Detalles* es además más clara para eso: muestra **qué línea o qué
documento coincidió**, mientras que desde el cuadro el pedido aparecía en la
lista sin que se viera el motivo.

La búsqueda responde en milésimas de segundo aunque la empresa tenga decenas de
miles de pedidos.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Pedido** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (pendiente, procesado, anulado), serie, N° pedido, secuencial, fecha de entrega, usuario que registró, documentos generados (con consignación o factura / sin documentos), total (mínimo y máximo) |
| Cliente | Cliente, RUC / cédula, responsable de entrega, observaciones, observaciones internas |

Los selectores *Serie*, *Usuario que registró* y *Responsable de entrega* listan
solo lo que la empresa ya usó en sus pedidos. El *total* es la suma de las líneas
del pedido.

**Pestaña Detalles** (lo que hay dentro del pedido). Es un único cuadro,
**Buscar libremente dentro de los pedidos**: escriba un producto, un código, una
cantidad o el número de una consignación o factura, y aparece la lista de **cada
línea que coincide** con el pedido al que pertenece (número, fecha, cliente y
estado). Para las consignaciones y facturas se muestra además su fecha y su
estado. Un clic en la fila deja el listado mostrando solo ese pedido; el ícono
de la derecha lo abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Serie | Sí | Punto de emisión que numera el pedido. Se puede marcar uno como favorito para que venga elegido. |
| Secuencial | Sí | Número del pedido. Lo asigna el sistema y no se escribe a mano. |
| Estado | Sí | Pendiente, Procesado o Anulado. Nace en Pendiente. |
| Cliente | Sí | A quién se le entrega. Se busca por nombre o identificación. A la derecha del título del campo se muestra el **vendedor (asesor)** asignado a ese cliente, solo como referencia. |
| Fecha Pedido | Sí | Cuándo se tomó el encargo. |
| Fecha de Entrega | Sí | Cuándo se entrega. No puede ser anterior a hoy ni al día del pedido. |
| Hora Inicial / Hora Máxima | Sí | Ventana horaria de la entrega, en formato 00:00. La inicial no puede ser mayor que la máxima; pueden ser la misma hora si la entrega es a una hora exacta. |
| Responsable de Entrega | Sí | Quién lleva el pedido. Se puede crear uno nuevo sin salir del formulario. |
| Observaciones Generales | No | Notas que acompañan al pedido (salen en el PDF). |
| Observaciones Internas | No | Notas para el equipo. |
| Productos: Descripción y Cantidad | Sí | Al menos un producto con cantidad mayor a cero. Sin precios. |

## Permisos

Se administran en **Configuración → Permisos por módulo**, sobre la ruta
`modulos/pedidos`:

- **Ver**: entrar al listado y abrir pedidos.
- **Crear**, **Modificar**, **Eliminar**: las acciones correspondientes.
- **Acceso total**: sin este permiso, el usuario solo ve y gestiona **los pedidos
  que él mismo creó**; con él, ve los de toda la empresa. El superadministrador
  siempre ve todo.

## Reglas de negocio

- **Fechas y horas**: la entrega no puede quedar antes de hoy ni antes de la fecha
  del pedido; las horas deben tener formato `00:00` y la inicial no puede ser
  mayor que la máxima (sí pueden ser iguales, para una entrega a hora exacta).
- **Al menos un producto**, y toda cantidad debe ser mayor a cero.
- **El secuencial no se repite**: al guardar, el sistema toma el siguiente número
  bajo un bloqueo, de modo que dos personas guardando a la vez no obtienen el mismo.
- **Un pedido a la vez**: si otra persona lo está editando (aquí o en Consignaciones
  de Venta), el sistema lo avisa con su nombre y no deja guardar encima.
- **Lo ya despachado no se puede deshacer desde el pedido**: si una línea ya fue
  entregada en una consignación o facturada, no se la puede quitar, ni cambiarle el
  producto, ni bajarle la cantidad por debajo de lo ya consumido. Aumentar la
  cantidad o agregar líneas nuevas sí se puede.
- **Eliminar es lógico**: el pedido deja de verse pero no se borra de la base. Y no
  se permite eliminarlo si alguna de sus líneas ya tiene consumo.
- **Estado automático**: al registrar entregas en una consignación, el pedido pasa a
  **Procesado** cuando todas sus líneas quedan completas, y vuelve a **Pendiente**
  si aún falta algo. *Facturado* no se asigna desde este módulo; existe como filtro
  del buscador para los pedidos que se resolvieron por factura.
- **Auditoría**: crear, actualizar y eliminar un pedido quedan registrados en la
  bitácora del sistema (`/config/log-sistema`, módulo *Pedidos*).

## Pedidos que nacen de una proforma

Además de crearlo a mano, un pedido puede venir de una **proforma**: en el modal
de la proforma, el botón del **carrito** lo genera con el mismo cliente,
productos, cantidades y precios. Sirve tanto una proforma **aprobada** como una
que todavía está en **borrador** —para ir preparando la entrega mientras se
negocia—, pero en ese caso tenga presente que el pedido es una copia del momento:
si la proforma cambia después, el pedido no se actualiza solo. Ese pedido entra al listado como cualquier otro,
en estado **Pendiente**, y en sus observaciones queda anotado de qué proforma
salió.

Tres cosas que conviene saber cuando un pedido llega por esa vía:

- **Los datos de entrega vienen vacíos** (fecha, rango horario y responsable),
  porque la proforma no los tiene. Hay que abrir el pedido y completarlos, como en
  cualquier pedido nuevo.
- **La fecha del pedido queda con la hora exacta** en que se generó. Un pedido
  creado a mano guarda solo el día (el formulario pide una fecha, no una hora), así
  que queda a las 00:00; uno que viene de una proforma conserva el minuto en que se
  mandó a despachar. El listado muestra el día en ambos casos. Si reabre ese pedido
  y lo guarda, pasa a comportarse como los demás y la hora se pierde.
- **Trae los precios de la proforma**, así que el listado muestra su valor. Si
  vuelve a guardar ese pedido desde este módulo —que no captura precios— esos
  valores quedan en cero; los productos y las cantidades se conservan.

Una proforma con **ítems de concepto libre** (líneas escritas a mano, sin producto
del catálogo) no se puede enviar a pedidos: el sistema lo bloquea y pide corregir
esas líneas primero. El detalle está en el manual de **Proformas**, sección
*Enviar a pedidos*.

## Integraciones con otros módulos

- **Proformas**: una proforma —en borrador o aprobada— puede enviarse a pedidos
  con un clic (ver la sección anterior). El pedido queda enlazado a la proforma
  que lo originó, y la proforma lista sus pedidos en la pestaña *Pedidos* de su
  modal.
- **Consignaciones de Venta**: la entrega real se registra allí; cada línea
  entregada queda enlazada a la línea del pedido y de ahí sale el control de lo ya
  despachado y el cambio de estado a Procesado.
- **Factura de Venta**: "Facturar desde pedido" arrastra las líneas al formulario de
  la factura y conserva el enlace con la línea de origen, de modo que lo facturado
  también cuenta como consumido.
- **Reporte de Pedidos**: vista de análisis sobre estos mismos datos.
- **Clientes**, **Productos** y **Responsables de Traslado**: catálogos de los que
  se alimenta el formulario.

## Errores frecuentes

- **"Este pedido lo está usando ahora mismo <usuario>"**: alguien lo tiene abierto
  en Pedidos o en Consignaciones de Venta. Espere a que lo cierre.
- **"El secuencial ... ya está en uso para esta serie"**: otro pedido tomó ese
  número mientras usted tenía el formulario abierto. Recargue y guarde de nuevo.
- **"Secuenciales no configurados"**: falta configurar el secuencial de Pedidos para
  ese punto de emisión, en Empresa → Puntos de emisión.
- **"No se puede quitar / cambiar / reducir ... ya tiene N registrado en una
  consignación o factura"**: esa línea ya se despachó. Corrija primero el documento
  que la consumió.
- **"La fecha de entrega no puede ser menor a la fecha actual"**: la entrega quedó
  en el pasado; corrija la fecha.
- **La descarga pide acotar la búsqueda**: el listado supera las 500 filas. Use el
  buscador (por ejemplo, un rango de fechas) y vuelva a exportar.
- **En el celular no aparece la lista de productos al buscar**: si su navegador
  quedó con la versión anterior guardada en caché, ciérrelo y vuelva a abrirlo (o
  recargue la pantalla). Resuelto en la versión 1.2.

## Historial de cambios

- **1.11** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.10** — Un pedido puede **nacer de una proforma** (botón del carrito
  en el modal de Proformas): llega con cliente, productos, cantidades y precios ya
  cargados, con la **fecha y hora** en que se generó, en estado *Pendiente* y con los
  datos de entrega por completar. Nueva
  sección *Pedidos que nacen de una proforma*. Requiere aplicar la migración
  `20260921_add_id_proforma_to_pedidos.sql`.
- **1.9** — Al elegir el **cliente** en el formulario, a la derecha del título del campo
  se muestra en letra pequeña el **vendedor (asesor)** que ese cliente tiene asignado,
  para saber de quién es la cuenta sin salir del pedido. En el **PDF**, en la misma línea
  del *Vendedor* y a su derecha, se agrega **Solicitado por** con el usuario que registró
  el pedido: así el documento distingue al asesor del cliente de quien tomó el encargo.

- **1.8** — El formulario del pedido se divide en dos pestañas. **General** tiene el
  pedido de siempre, sin cambios. La nueva pestaña **Detalle** muestra quién creó el
  pedido, quién lo editó por última vez y **en qué consistió ese cambio**, más la
  lista completa de ediciones. Se puede ocultar desde el engranaje de las pestañas.
  Junto con eso:
  - El **cambio de estado que provoca guardar o eliminar una consignación** ahora
    queda registrado en el historial del pedido, indicando de qué consignación vino.
    Antes ese cambio pisaba el "última edición por" del pedido sin dejar rastro, así
    que el pedido figuraba modificado por alguien que nunca lo había abierto.
  - El historial dejó de marcar como modificadas la *fecha del pedido* y las *horas
    de entrega* en cada guardado cuando nadie las había tocado.
  - Se muestra el **nombre del responsable de entrega** en vez de su número interno.

- **1.7** — El cuadro de búsqueda del listado queda para lo que se ve en la tabla:
  número, fechas, rango horario, cliente, responsable de entrega y las dos
  observaciones. Los **productos pedidos** y los **números de las consignaciones y
  facturas** que tomaron el pedido pasan a la pestaña *Detalles* del modal de filtros,
  que además muestra cuál coincidió; la **identificación del cliente** y el **usuario
  que registró** se consultan en sus filtros. Antes el pedido aparecía en la lista sin
  que se viera el motivo.

- **1.6** — La **búsqueda del listado es mucho más rápida**: encuentra exactamente
  lo mismo, pero deja de revisar todos los pedidos de la empresa en cada tecla.
  En la prueba con 20.000 pedidos pasó de 7 a 8 segundos por búsqueda a menos de
  una décima. Además, si se sigue escribiendo, la consulta anterior se cancela, y
  buscar ya no deja en espera a las demás pantallas del mismo usuario.

- **1.5** — La **hora inicial y la hora máxima de entrega pueden ser la misma**
  (por ejemplo 10:00 - 10:00, para una entrega a hora exacta). Antes el formulario
  lo rechazaba; sigue sin aceptarse una hora inicial mayor que la máxima.
- **1.4** — Nuevo **buscador del listado**: la búsqueda libre recorre todas las
  columnas, la identificación del cliente, el usuario, los productos y las
  consignaciones o facturas que tomaron el pedido; el botón del embudo abre la
  ventana de filtros con pestañas *Pedido* y *Detalles*, y los filtros activos se
  ven como etiquetas dentro del cuadro. Se agregan los filtros de N° pedido, RUC,
  usuario que registró, documentos generados, total y observaciones internas, y el
  responsable de entrega pasa a ser un selector. Se quita el estado *Facturado*,
  que un pedido nunca tiene.
- **1.3** — El listado se puede **ordenar por hasta tres columnas a la vez**:
  Shift+clic en el título de la segunda columna la encadena a la primera (por
  ejemplo *Estado* y, dentro de cada estado, la *Fecha de entrega*). Cada
  encabezado activo muestra un número con su prioridad, y el orden del estado
  sigue siendo por flujo del pedido, no alfabético. El orden se guarda por usuario
  y se respeta al exportar a PDF y Excel.
- **1.2** — En el celular, la lista de productos ya no desaparece al buscar por
  código o descripción. Antes, cuando el teclado tapaba el campo del detalle —lo
  habitual, porque la tabla de productos queda en la parte baja del formulario— la
  lista se ocultaba y se escribía sin ver ningún resultado. Ahora el formulario sube
  el campo por encima del teclado y la lista aparece pegada a él, justo debajo (o
  justo encima, si abajo no queda sitio).
- **1.1** — El listado abre ordenado por fecha de emisión, del más reciente al más
  antiguo (antes, por número de pedido ascendente). La columna por la que se está
  ordenando ahora se distingue con su flecha desde que se abre la pantalla, y
  ordenar ya no borra el filtro que tuviera puesto en el buscador. Se documentó que
  la columna Estado ordena por el flujo del pedido y no alfabéticamente.
- **1.0** — Versión inicial.
