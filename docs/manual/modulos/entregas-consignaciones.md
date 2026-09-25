---
titulo: Entregas de Consignaciones
resumen: Consignaciones pendientes de entregar (por defecto) y entregadas, con la evidencia registrada desde la app móvil del repartidor o manualmente desde el sistema.
categoria: Ventas
ruta_modulo: modulos/entregas-consignaciones
tipo: modulo
visibilidad: todos
etiquetas: entregas, entrega, buscar entrega, buscar consignacion, buscador, filtros, filtrar entregas, buscar por producto, buscar por lote, con firma, sin firma, con gps, sin gps, chips, pendientes de entrega, por entregar, consignaciones, repartidor, GPS, firma, evidencia de entrega, app móvil, entregas confirmadas, resumen de entregas, aparecen documentos que no busque, resultados que no corresponden, buscar por producto en el listado
version: 1.10
orden: 0
estado: activo
---

Este módulo muestra las [Consignaciones en Ventas](consignaciones-ventas.md)
desde el punto de vista de la entrega. **Al abrirlo lista solo las consignaciones
pendientes de entregar** (estado *Emitida*): es la cola de trabajo del
repartidor. Con el selector de estado también se pueden ver las ya entregadas,
con la evidencia que registró el repartidor desde la app móvil (GPS y firma del
cliente) o la que quedó al marcarlas manualmente como "Entregada" desde el
sistema. Desde aquí el usuario puede **marcar cada pendiente como entregada**
(botón *Entregar* de la fila o del detalle), con la misma evidencia que deja el
marcado manual en Consignaciones en Ventas. No crea, edita ni elimina
consignaciones.

## Qué es y para qué sirve

Sirve para saber de un vistazo **qué queda por entregar** (a quién, a dónde,
para cuándo y hace cuántos días se emitió), y, cambiando el estado, revisar
cuántas entregas se hicieron, cuáles vinieron de la app móvil y cuáles se
marcaron manualmente, cuáles quedaron con evidencia incompleta (sin firma o sin
ubicación) y cuánto está tardando el proceso desde que se emite la consignación
hasta que se entrega.

## Requisitos previos

- Para que aparezcan filas en la vista por defecto debe existir al menos una
  consignación en estado *Emitida* (pendiente de entrega).
- Si un usuario debe ver **solo las consignaciones de ciertos repartidores**,
  vincúlelo a esos responsables de traslado desde `config/usuarios-sistema`
  (ver más abajo, sección Permisos). Sin vínculo ve todas las de la empresa.

## Cómo se usa

1. Abra el módulo desde el menú. La tabla muestra las consignaciones
   **pendientes de entregar**, las más recientes primero. La columna **Días**
   resalta en amarillo las que llevan 3 días o más sin entregar y en rojo las de
   7 o más.
2. Use el selector **Pendientes / Entregadas / Todas** (o el campo *Estado de
   entrega* del buscador) para cambiar qué se lista. Al volver a "Pendientes" el
   filtro desaparece porque es el valor por defecto.
3. Escriba en el buscador para filtrar el listado, o pulse el **embudo** para
   abrir la ventana de filtros (ver *Buscar y filtrar el listado*); también
   puede usar los selectores de **Año** / **Mes** como atajo rápido sobre la
   fecha de emisión.
4. Haga clic en una fila para ver el detalle: datos de la consignación (cliente,
   dirección, entrega programada, responsable, días en espera) y, si ya fue
   entregada, la evidencia: mapa con el punto de entrega, firma de recepción y
   quién y cuándo la registró.
5. Para **registrar una entrega**, pulse el botón **Entregar** (columna
   *Acciones*) de la fila pendiente, o abra el detalle y use **Marcar como
   entregada**. Se pide confirmación con una observación opcional (p. ej. quién
   recibió); el navegador solicita la ubicación (si se deniega o no hay GPS, la
   entrega se registra igual, solo con fecha/hora y usuario). Mientras se
   obtiene la ubicación se muestra la precisión alcanzada: el sistema espera
   hasta unos 20 segundos a que el GPS fije un punto de ±20 m y se queda con la
   lectura más precisa. Si aun así la precisión es peor que ±100 m, avisa que la
   ubicación es aproximada y deja **Reintentar**, **Registrar igual** o
   **Cancelar**. Al confirmar, la
   consignación pasa a *Entregada*, desaparece de la lista de pendientes y queda
   su evidencia con canal **Web**.
6. Use los botones **PDF** / **Excel** para exportar el listado con el filtro
   actual aplicado (el reporte indica si contiene pendientes, entregadas o todas).

## Campos del listado

| Campo | Qué significa |
|-------|----------------|
| Emisión | Fecha de emisión de la consignación. |
| Consignación | Serie-secuencial de la consignación. |
| Cliente | Cliente de la consignación. |
| Dirección | Dirección registrada en la ficha del cliente (a dónde se entrega). |
| Responsable | Responsable de traslado (repartidor) asignado a la consignación. |
| Entrega programada | Fecha (y franja horaria, si se indicó) prevista para la entrega en la consignación. |
| Estado | **Pendiente** (Emitida), **Entregada** o **Facturada**. |
| Días | Días desde la emisión hasta la entrega; si sigue pendiente, hasta hoy. |
| Fecha/hora entrega | Momento en que se capturó la evidencia (hora del celular si vino de la app, o de guardado si fue manual). Vacío mientras esté pendiente. |
| Canal | **App móvil** (registrada por el repartidor con GPS/firma) o **Web** (marcada manualmente desde el sistema). |
| Firma | Si existe firma de recepción capturada. |
| GPS | Si existe ubicación (latitud/longitud) capturada. |
| Registrado por | Usuario que quedó como autor de la evidencia. |
| Observaciones | Nota libre de la entrega, si la hay. |
| Acciones | Botón **Entregar** en las pendientes (solo con permiso *Modificar*). |

Los campos de evidencia (Fecha/hora entrega, Canal, Firma, GPS, Registrado
por, Observaciones) se muestran vacíos ("—") en las consignaciones pendientes.
Todas las columnas pueden ocultarse por usuario desde el botón de columnas.

### KPIs

Los KPIs se calculan sobre el rango filtrado (cliente, fechas, producto,
responsable…) **sin aplicar el filtro de estado**: siempre muestran pendientes y
entregadas a la vez, para tener la foto completa aunque la tabla liste solo una
parte.

- **Pendientes de entregar**: consignaciones en estado *Emitida*.
- **Entregadas**: consignaciones en estado *Entregada* o *Facturada*.
- **App móvil** / **Web (manual)**: desglose de las entregadas con evidencia,
  por canal.
- **Tiempo prom. emisión→entrega**: horas promedio entre la fecha de emisión y
  la fecha/hora en que se confirmó la entrega.
- **Evidencia incompleta**: entregadas sin evidencia registrada, sin GPS, o de
  canal móvil sin firma (una entrega web nunca captura firma, así que eso solo
  no cuenta como incompleta).

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel; a su lado quedan los selectores
de estado, año y mes.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del listado: emisión,
consignación (serie y secuencial), cliente, dirección, responsable, entrega
programada, días, fecha/hora de entrega, registrado por y observaciones de la
entrega. Puede escribir varias palabras en cualquier orden y no importan
mayúsculas ni tildes. La búsqueda respeta el estado de entrega elegido (por
defecto, pendientes). Mientras busca, aparece un **círculo girando** al final del
cuadro y la tabla se ve atenuada.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** El cuadro devuelve
solo entregas donde se vea por qué coinciden; el resto se consulta en la ventana
de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Productos consignados, lote y NUP | Pestaña *Detalles* |
| Dispositivo de la app móvil | Pestaña *Detalles* |
| RUC o cédula del cliente | Pestaña *Entrega* → **RUC / cédula** |
| Observaciones y punto de llegada **de la consignación** | Listado de Consignaciones de Venta |
| Estado, Canal, Firma y GPS | Pestaña *Entrega* |

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Entrega**:

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), entrega programada, fecha de entrega real, estado de entrega (pendientes por defecto, entregadas o todas), canal (app móvil o web), Nº consignación, con o sin firma, con o sin GPS, días (mínimo y máximo), registrado por, producto |
| Cliente | Cliente, RUC / cédula, dirección, responsable de traslado, observaciones de la entrega |

Los selectores *Responsable de traslado* y *Registrado por* listan solo los que
ya aparecen en las consignaciones y entregas de la empresa. Los selectores de
**estado**, **Año** y **Mes** del encabezado ponen el mismo filtro que la
ventana (Año y Mes arman el rango completo de fechas de emisión), y se
actualizan si el filtro se cambia desde la ventana o se quita su etiqueta.

**Pestaña Detalles**. Es un único cuadro, **Buscar libremente dentro de las
consignaciones**: escriba un producto, un código, un lote, un NUP, una bodega,
un dispositivo o un texto de la observación de una entrega, y aparece la lista
de **cada coincidencia** con la consignación a la que pertenece (número,
emisión, cliente y estado). Busca en pendientes y entregadas a la vez, dentro
del mismo alcance por responsables del listado. Un clic en la fila deja el
listado mostrando solo esa consignación (con el estado de entrega que le
corresponde); el ícono de la derecha abre su detalle directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. Los KPIs, el PDF y el Excel usan los mismos
filtros.

## Permisos

- **Ver**: abrir el módulo, listar, ver el detalle, exportar y ver firmas.
- **Modificar** (`u`): habilita el botón **Entregar** / **Marcar como
  entregada**. Sin este permiso el módulo es de solo lectura. El
  superadministrador (nivel 3) lo tiene siempre; al resto hay que asignárselo
  en `/config/permisos-modulos`, submódulo *Entrega de consignaciones*.
- *Crear* y *Eliminar* no se usan: desde aquí no se crean ni eliminan
  consignaciones.

El alcance de lo que se ve —y de lo que se puede marcar— **no** lo define el
flag "acceso total" del permiso, sino el **vínculo del usuario con
responsables de traslado** que se administra en
`config/usuarios-sistema` (ficha del usuario, pestaña *Responsables de
traslado*; tabla `usuarios_responsables_traslado`, la misma que usa la app
móvil de entregas):

- **Superadministrador (nivel 3)**: ve todas las consignaciones de la empresa,
  esté o no vinculado.
- **Usuario vinculado a uno o más responsables**: ve solo las consignaciones de
  esos responsables, aunque tenga "acceso total" en el módulo.
- **Usuario sin ningún vínculo**: ve todas las consignaciones de la empresa.

Ese alcance vale para todo lo que sirve el módulo, **incluida la imagen de la
firma** de una entrega: si la entrega no es de uno de sus responsables, la
firma no se muestra aunque se pida por su enlace directo.

El mismo alcance rige en la **app móvil** (`api/v1/entregas`): el repartidor
vinculado solo puede ver, abrir y registrar la entrega de las consignaciones
de sus responsables; sin vínculo (o nivel 3) puede con todas.
Una consignación fuera de su alcance responde *«Consignación no encontrada»*,
igual que una que no existe.

## Reglas de negocio

- La única escritura del módulo es **marcar una pendiente como entregada**. Usa
  exactamente el mismo flujo que el selector de estado de
  [Consignaciones en Ventas](consignaciones-ventas.md): pasa la consignación a
  *Entregada*, crea la evidencia con canal *Web* (fecha/hora, usuario,
  ubicación del navegador si la hay y la observación escrita) y deja el rastro
  en `log_sistema`. Si la consignación ya tenía una evidencia (p. ej. de la app
  móvil), no se duplica: solo se apunta a ella.
- Solo se puede marcar una consignación en estado *Emitida*. Si otra persona
  la entregó mientras tanto (desde la app o desde Consignaciones en Ventas), el
  botón responde con el estado actual y la lista se refresca.
- Una consignación con **factura asociada** (generada en vivo) no admite el
  cambio de estado, igual que en Consignaciones en Ventas.
- No se crean ni eliminan consignaciones desde aquí: la fuente de verdad son
  `consignaciones_ventas` (estado) y `consignaciones_ventas_entregas`
  (evidencia), alimentadas por Consignaciones en Ventas, por este módulo y por
  la app móvil.
- **Pendiente** = consignación en estado *Emitida*, el mismo criterio que usa
  la app móvil para armar la lista del repartidor. Las consignaciones en
  *Borrador* o *Anulada* no aparecen en ningún estado del filtro.
- Cada consignación aparece **una sola vez**. Si tiene varias evidencias (p. ej.
  reintentos desde el celular), se muestra la confirmada; si no hay confirmada,
  la más reciente válida.
- El filtro por **producto** busca entre los productos que contiene la
  consignación (no un campo directo de la entrega).
- Los **Año/Mes** son un atajo sobre el mismo filtro de *Fecha emisión*; si se
  usa el rango desde/hasta del buscador, ese reemplaza al de Año/Mes (comparten
  el mismo criterio, no se combinan). El filtro *Fecha entrega real* solo
  aplica a las entregadas: combinado con "Pendientes" no devuelve filas.

## Integraciones con otros módulos

- **Consignaciones en Ventas**: fuente de los datos de cabecera (cliente,
  responsable, estado, entrega programada) y de la tabla de evidencia de entrega.
- **App móvil de entregas** (`api/v1/entregas`): origen de las entregas con
  canal `movil`; su lista de pendientes usa el mismo criterio que este módulo.
  Al abrir una entrega, la app afina el GPS con los mismos criterios que la web
  (hasta ~20 s, objetivo ±20 m, muestra la precisión en vivo); el botón
  *Confirmar entrega* se habilita cuando termina, y si la precisión quedó peor
  que ±100 m pide confirmar antes de registrar (o *Actualizar ubicación*).

## Errores frecuentes

- **"No hay consignaciones pendientes de entregar" pero sí hay entregas
  hechas**: es el comportamiento esperado; cambie el selector a *Entregadas* o
  *Todas* para verlas.
- **No aparecen consignaciones que sí existen**: revise en
  `config/usuarios-sistema` a qué responsables de traslado está vinculado el
  usuario: si tiene vínculos, solo ve las de esos responsables. Para que vea
  todas, quítele los vínculos (o hágalo nivel 3).
- **No aparece el botón "Entregar"**: el usuario no tiene el permiso
  *Modificar* en el submódulo *Entrega de consignaciones*
  (`/config/permisos-modulos`), o la fila no está pendiente.
- **"Solo se puede marcar la entrega de una consignación pendiente"**: alguien
  la entregó (o cambió su estado) después de que se cargó la lista; refresque.
- **La firma no carga en el detalle**: la entrega no tiene `firma_path` (las
  entregas registradas manualmente desde la web nunca tienen firma).
- **"Ubicación aproximada" al registrar la entrega / el punto del mapa no es el
  lugar real**: el dispositivo no está usando GPS (lo tiene apagado, está bajo
  techo o es un computador de escritorio, que ubica por la red a cientos de
  metros). Active el GPS, dé permiso de ubicación "precisa" al navegador (o a la
  app) y pulse **Reintentar** (en la app, **Actualizar ubicación**). La precisión con que se registró cada entrega se ve en
  el detalle (±m).

## Historial de cambios

- **1.10** — Corregido: la entrega registrada desde la web no guardaba la
  ubicación exacta, sino la primera lectura aproximada del navegador (por red,
  a cientos de metros). Ahora espera a que el GPS fije un punto preciso, se
  queda con la mejor lectura, muestra la precisión mientras la obtiene y avisa
  si la ubicación sigue siendo aproximada (con opción de reintentar). Lo mismo
  en la app móvil: afina el GPS antes de habilitar *Confirmar entrega* y pide
  confirmación si la ubicación es aproximada.

- **1.9** — Corregido: al registrar una entrega desde el detalle (**Marcar como
  entregada**), el cuadro de la observación no aceptaba texto —se veía, pero al
  escribir no pasaba nada—. Ahora se puede escribir la observación con normalidad
  y el cuadro queda listo para escribir apenas aparece. Desde el botón *Entregar*
  de la fila ya funcionaba.

- **1.8** — El cuadro de búsqueda del listado queda para lo que se ve en la tabla. Así el
  listado solo devuelve entregas donde
  se vea POR QUÉ coinciden. Los **productos** (con su lote y NUP) y los **documentos
  relacionados** pasan a la pestaña *Detalles* del modal de filtros, que además muestra
  cuál de ellos coincidió; el resto de datos que no son columna —RUC del cliente, montos,
  responsable, usuario— se consultan en sus filtros. Antes, el documento aparecía en la
  lista sin que se viera el motivo.
  Las observaciones y el punto de llegada de la CONSIGNACIÓN se buscan en su propio
  listado, y el dispositivo de la app móvil en la pestaña *Detalles*.

- **1.7** — La búsqueda del listado, los indicadores de arriba y la pestaña
  **Detalles** responden más rápido en empresas con muchas consignaciones: con
  decenas de miles podían tardar varios segundos por búsqueda. Encuentran
  exactamente lo mismo. Si se sigue escribiendo mientras busca, la búsqueda
  anterior se cancela y solo se muestra la última.
- **1.6** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas del listado y en los productos
  consignados (código, nombre, lote, NUP), salvo Estado, Canal, Firma y GPS.
  Los filtros pasan a una **ventana propia** (botón del embudo, se aplican con
  *Aplicar*) con dos pestañas: **Entrega** (filtros por campo, con criterios
  nuevos: con/sin firma, con/sin GPS, días, RUC, observaciones, y responsable
  y *registrado por* como listas) y **Detalles**, un cuadro de **búsqueda libre
  dentro de las consignaciones** (productos y evidencias de entrega) que dice a
  qué consignación pertenece cada coincidencia. Los atajos *Hoy* / *Este mes*
  pasan a la fecha de emisión de la ventana; los selectores de estado, año y
  mes se mantienen. Nueva sección *Buscar y filtrar el listado*.

- **1.5** — El usuario puede **marcar cada consignación pendiente como
  entregada** desde el módulo: botón *Entregar* en la fila (columna *Acciones*)
  y *Marcar como entregada* en el detalle. Pide confirmación con observación
  opcional, captura la ubicación del navegador y reutiliza el mismo flujo de
  "Entregada" de Consignaciones en Ventas (evidencia canal *Web* + auditoría).
  Requiere el permiso **Modificar** del submódulo; respeta el alcance por
  responsable de traslado y solo admite consignaciones en estado *Emitida*.

- **1.4** — El listado pasa a ser de **consignaciones**, no de evidencias, y
  **por defecto muestra solo las pendientes de entregar** (estado *Emitida*).
  Selector *Pendientes / Entregadas / Todas* (filtro `estado:` del buscador).
  Columnas nuevas: Emisión, Dirección, Entrega programada, Estado y Días en
  espera (con resaltado a partir de 3 y 7 días). Filtros nuevos: dirección,
  fecha de emisión y entrega programada; Año/Mes ahora filtran por fecha de
  emisión. El detalle muestra los datos de la consignación y, si está
  entregada, su evidencia. Cada consignación aparece una sola vez aunque
  tenga varias evidencias. Los KPIs cuentan consignaciones (Entregadas =
  estado *Entregada* o *Facturada*; Evidencia incompleta incluye entregadas
  sin evidencia) y no dependen del estado seleccionado. PDF/Excel exportan
  las columnas nuevas y titulan según el estado filtrado.

- **1.3** — El alcance del listado, los KPIs, las exportaciones y la firma ya
  no depende del flag "acceso total" sino del **vínculo con responsables de
  traslado** de `config/usuarios-sistema`: nivel 3 y usuarios sin vínculo ven
  todas las entregas de la empresa; usuarios vinculados, solo las de sus
  responsables. Antes, un usuario sin acceso total y sin vínculo no veía nada.
  La misma regla aplica en la app móvil (listado, detalle y registro de la
  entrega).

- **1.2** — En la **app móvil**, abrir una consignación y registrar su entrega
  respetan el alcance por responsable de traslado, igual que el listado: antes,
  un repartidor sin acceso total podía abrir cualquier consignación de la
  empresa —y marcarla como entregada con su firma— si llegaba a ella por su
  número interno.

- **1.1** — La imagen de la **firma** de una entrega respeta el mismo alcance
  que el listado: sin acceso total solo se ve la de los responsables de
  traslado del usuario.

- **1.0** — Versión inicial: KPIs, listado filtrable (fecha, año/mes, producto,
  cliente, responsable, canal), detalle con mapa y firma, export PDF/Excel.
