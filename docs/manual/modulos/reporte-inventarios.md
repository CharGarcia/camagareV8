---
titulo: Reporte de inventarios
resumen: Existencias y movimientos por producto y bodega, para cuadrar y valorar el stock.
categoria: Reportes
ruta_modulo: modulos/reporte_inventarios
tipo: modulo
visibilidad: todos
etiquetas: reporte de inventario, no descarga el excel, excel no descarga, excel en blanco, demasiados datos, excel muy grande, filtrar por año, no descarga el pdf, pdf en blanco, pdf muy grande, excel de la consignacion, numero de factura en el excel, numero de retorno en el excel, totales en el excel, existencias, stock por bodega, valorizacion, kardex, faltantes, exportar, auditoria, stock cacheado, corregir stock, consignaciones, stock por lote, por caducidad, que se vence, vencimientos, limpiar filtros, lento, tarda, se cuelga, tarda en abrir, tarda en entrar, busqueda lenta, se recarga la pagina, ordenar por columna, pierde el resultado, no puedo abrir otro modulo mientras carga, primeras 5000 filas, listado recortado, lote, nup, asesor, detalle de consignacion, totales del detalle, pdf del documento relacionado, permisos, pestañas, no veo la pestaña, no aparece consignaciones, no aparece existencias, acceso a inventario, permiso de inventario, permiso de consignaciones, pdf de la consignacion, estado de la consignacion, imprimir consignacion con saldo, consignacion completa, saldo en poder del cliente, no veo una bodega, bodegas asignadas, acceso a bodegas, solo mi bodega, falta una bodega, no aparece la bodega, codigo de producto en consignacion, codigo del producto en el detalle, codigo como primera columna, columna codigo, codigo de producto en el reporte, ordenar por codigo, lote mas consignacion, que lote tiene cada cliente, lote por cliente, consignacion por lote, con quien salio el lote, entregas por lote, se genera solo, se consulta solo, no muestra datos, boton mostrar, hay que pulsar mostrar, al elegir el producto se pone a cargar, al cambiar el anio se pone a cargar, no quiero que cargue solo, carga sola, consulta automatica, lotes en cero, lote agotado, no muestra lotes vacios, stock cero, lotes sin stock, filas en cero, por que no aparece el lote, lote desaparecio del reporte, boton mostrar bloqueado, no puedo pulsar mostrar, doble clic en mostrar, barra de progreso, porcentaje de avance, cuanto falta, se queda cargando, indicador de carga, stock negativo, por que esta en negativo, saldo negativo, negativo en existencias, seguimiento, trazabilidad del lote, de donde sale el negativo, lote sin entrada, lote duplicado, lote mal escrito, movimientos de otro ambiente, kardex de un lote, filtros no funcionan, no filtra, no coge los filtros, filtro de estado, filtro consignado, saldo a fecha, fecha de corte
version: 1.30
orden: 40
estado: activo
---

El **reporte de inventarios** muestra las existencias y los movimientos del
periodo, por producto y por bodega. Es la herramienta para el conteo físico y
para valorar lo que hay en almacén.

## El código del producto, siempre en la primera columna

En las cinco pestañas, el **código del producto es la primera columna** de la
tabla —también en el PDF y en el Excel—, para poder identificar el artículo sin
depender del nombre. Se exceptúan las dos vistas que llevan el **nombre** del
producto en una columna aparte y lejos del código: *Lote + consignación*
(pantalla, PDF y Excel) y el **PDF/Excel de Consignaciones detallado**. En esas
dos, el código va **pegado a la columna del nombre** en lugar de abrir la tabla,
para leer de corrido "código — producto".

Dónde aparece según lo que esté mostrando cada pestaña:

| Vista | Qué lleva en la primera columna |
|---|---|
| Existencias (Detallado, Por lotes, Por caducidad, Lote + caducidad) | Código del producto de la fila |
| Existencias (Lote + consignación) | Fecha del documento; el código va justo antes de *Descripción* |
| Movimientos (Detallado) | Código del producto del movimiento |
| Cualquier pestaña con *Agrupar por* en **Por Producto** | Código del producto, y el nombre pasa a la columna siguiente |
| Consignaciones (Detallado) | Códigos de los productos del documento, separados por coma. En el PDF y el Excel cada fila es una línea, y el código va justo antes de *Producto* |
| Auditoría | Código del producto de la discrepancia |

Cuando la fila **no es un producto** —agrupados por categoría, bodega, marca,
tipo, origen, fecha o mes— no hay un código que mostrar y la primera columna
sigue siendo la del grupo.

En **Existencias con *Agrupar por* en Detallado**, la columna Código también
ordena: un clic en su encabezado ordena el listado por código.

## Qué permite ver

- Existencias actuales por producto y bodega, y —si hace falta— desglosadas
  por lote, por caducidad o por ambos.
- Qué lote salió con qué cliente y en qué consignación (*Lote + consignación*).
- Movimientos del periodo: qué entró, qué salió y de dónde vino cada movimiento
  (columnas Entradas, Salidas y Saldo). En la vista **Detallado** los
  movimientos salen ordenados por **fecha, del más antiguo al más reciente**,
  mezclando productos y bodegas; el saldo de cada fila sigue siendo el saldo
  corrido de su propio producto y bodega. Lo mismo en el PDF y el Excel.
- Valor del inventario según el costo registrado.
- Consignaciones vigentes/entregadas, a nivel de cabecera con detalle por línea.
- Auditoría: diferencias entre el stock guardado y el saldo real del kardex.

## Ver el stock por lote o por caducidad (selector Detalle)

En **Existencias**, el primer selector (**Detalle**) decide hasta dónde se
desglosa el stock de cada producto. Las cuatro primeras opciones muestran
siempre el mismo total; lo que cambia es en cuántas filas se reparte:

| Detalle | Una fila por | Para qué sirve |
|---|---|---|
| **En general** | producto y bodega | El stock del día a día. Es el que permite editar mínimo, máximo y categoría al hacer clic en la fila. |
| **Por lotes** | producto, bodega y lote | Cuánto queda de cada lote. Si un lote entró con dos caducidades distintas, aquí se ven sumadas. |
| **Por caducidad** | producto, bodega y fecha de caducidad | Qué se vence y cuándo, sin importar de qué lote venga. |
| **Lote + caducidad** | cada combinación de lote, NUP y caducidad | El máximo detalle, para cuadrar un lote concreto. |
| **Lote + consignación** | cada lote/NUP **entregado en consignación** | Con qué cliente y con qué documento salió cada lote. Ver abajo. |

Mientras el Detalle no esté en *En general*, los selectores **Agrupar por** y
**Estado** quedan desactivados: el desglose ya define las filas por sí solo, y
el estado (quiebre, bajo mínimo, sobre máximo) se mide contra el mínimo y el
máximo del producto en la bodega, que no existen por lote ni por caducidad. El
selector **Consignado** sí aplica en los desgloses.

La columna **Consignación** acompaña al desglose elegido: si la fila no
distingue caducidad, lo consignado tampoco, de modo que el stock propio y lo
que está en poder de clientes siempre se pueden comparar en la misma fila.

> El desglose por lote/caducidad se calcula desde el kardex, no desde el stock
> guardado del producto: el sistema no almacena un stock por lote.

**Los lotes agotados no aparecen.** En los tres desgloses (*Por lotes*, *Por
caducidad* y *Lote + caducidad*) solo se listan las filas cuyo **stock total**
—lo que hay en bodega más lo que está en poder de clientes— es **distinto de
cero**. Un lote que entró y ya salió completo sumaría 0 y solo estorbaría: el
kardex conserva todos los lotes que alguna vez existieron, así que sin este
filtro el listado terminaba siendo casi todo histórico muerto. El total del
reporte no cambia (esas filas aportaban 0). Para ver el movimiento de un lote
ya agotado, está la pestaña **Movimientos**, filtrando por ese lote.

> *En general* sí conserva las filas en cero: ahí la fila es el producto en la
> bodega y "sin stock" (estado **QUIEBRE**) es justamente lo que se quiere ver.
> Y si una fila del desglose muestra un total **negativo**, se sigue mostrando:
> es una inconsistencia que hay que revisar en la pestaña *Auditoría*.

## Qué filtros aplican en Existencias

| Filtro | En general (detallado o agrupado) | Por lotes / caducidad / lote + caducidad | Lote + consignación |
|---|---|---|---|
| Bodega, Categoría, Marca, Producto | Sí | Sí | Sí |
| Lote, NUP, Caducidad desde/hasta | Sí | Sí | Sí |
| Estado | Sí | No (desactivado) | No (desactivado) |
| Consignado | Sí | Sí | Sí (por saldo) |
| Saldo a fecha | Sí | Sí | Sí (consignaciones emitidas hasta ese día) |

En los agrupados (*Por Producto*, *Por Categoría*, *Por Bodega*), **Estado** y
**Consignado** se evalúan en cada producto y bodega **antes** de sumar: el grupo
suma solo los que cumplen.

**Producto**: lo normal es elegirlo de la lista que aparece al escribir. Si se
escribe y se pulsa **Mostrar** sin elegirlo, el texto igual filtra, por nombre
o código que lo contenga. Al volver a escribir en el campo se descarta el
producto elegido antes.

**Saldo a fecha** incluye **todo el día** elegido: los movimientos de esa fecha
cuentan, sea cual sea su hora.

## Lote + consignación: qué lote tiene cada cliente

Las otras opciones de *Detalle* responden "cuánto queda"; esta responde **"con
quién salió"**. Cada fila es una **línea de consignación** —un producto con su
lote y su NUP dentro de un documento—, no un par producto/bodega:

| Columna | Qué contiene |
|---|---|
| Fecha / Secuencial | Fecha y número de la consignación |
| Cliente / Asesor | A quién se entregó y qué vendedor la hizo |
| Código / Descripción | Código y nombre del producto de esa línea, uno junto al otro |
| Lote / NUP | Lote y NUP de esa línea |
| Responsable traslado | Quien trasladó la mercadería |
| Bodega | Bodega de la que salió |
| Consignado | Cantidad entregada en esa línea |
| Retornado / Facturado / A cambio | Lo que ya salió del saldo: devuelto, facturado o entregado a cambio de otro producto |
| Saldo | Entregado − retornado − facturado − a cambio: lo que sigue en poder del cliente |

Un clic en la fila abre el **detalle completo de esa consignación**, el mismo
de la pestaña Consignaciones.

**Filtros que aplican**: Bodega, Categoría, Marca, Producto, Lote, NUP,
Caducidad y **Fecha de corte** (aquí significa *consignaciones emitidas hasta
esa fecha*). El selector **Consignado** filtra por saldo: *Con consignado*
deja solo lo que sigue en poder del cliente y *Sin consignado*, lo ya
liquidado. El selector **Estado** queda desactivado: quiebre o bajo mínimo son
estados del stock de un producto, y aquí la fila es una entrega.

> Esta opción aparece solo si el usuario también puede **ver Consignaciones de
> ventas**: muestra datos de ese módulo, no del kardex.

## El reporte solo se genera al pulsar Mostrar

Ninguna pestaña consulta por su cuenta. Elegir un producto o un cliente en el
buscador, cambiar el selector **Detalle** o cambiar **Año** y **Mes** solo deja
el filtro puesto: la tabla no se mueve hasta que se pulsa **Mostrar**.

Así se pueden acomodar varios filtros seguidos sin que cada cambio dispare una
búsqueda —una consulta sin acotar recorre todo el inventario o todo el kardex— y
sin quedarse esperando resultados que todavía no se pidieron.

Al abrir el módulo, y cada vez que se limpian los filtros, la tabla muestra
*Aplica los filtros y genera el reporte* hasta el primer Mostrar.

## Limpiar los filtros

Cada pestaña tiene, junto al botón **Mostrar**, un botón con un icono de goma
de borrar que devuelve todos sus filtros al valor inicial. No vuelve a consultar
solo: deja la tabla en blanco para que elija los filtros nuevos y pulse Mostrar.

## Ordenar por columna en Existencias

Con *Agrupar por* en **Detallado**, un clic en el encabezado de una columna
ordena el listado por esa columna y un segundo clic invierte el orden. La tabla
se vuelve a pedir con el nuevo orden sin recargar la página y sin perder los
filtros elegidos.

## Mientras se genera el reporte

Una búsqueda larga (un año de movimientos, todas las existencias, una
exportación) no deja esperando al resto del sistema: mientras carga se puede
abrir otro módulo en otra pestaña del navegador o usar los buscadores de
producto y cliente.

El botón **Mostrar** se **bloquea** mientras esa pestaña genera su reporte —se
pone en gris con un indicador girando— y se libera al llegar los datos o al
fallar la consulta. Así no se lanzan cuatro consultas pesadas por impaciencia.
Cada pestaña tiene su propio botón y su propio bloqueo: se puede dejar cargando
*Movimientos* e irse a pedir *Existencias*.

Con él se bloquean también las demás acciones de esa pestaña: **PDF**, **Excel**
y, en *Auditoría*, **Corregir todo** —que mientras se recalcula estaría operando
sobre el resultado anterior—. Todas vuelven a habilitarse al terminar.

En la tabla aparece mientras tanto un mensaje **"Generando el reporte…"** con
una barra de avance y su porcentaje. El avance tiene dos tramos y el pie de la
barra dice en cuál va:

| Tramo | Qué dice | De dónde sale el porcentaje |
|---|---|---|
| Hasta 80 % | *Consultando la base de datos · 3,2 s* | **Estimado.** El servidor arma la respuesta completa antes de mandar nada, así que no hay avance que informar. La referencia es lo que tardó la última consulta de esa misma pestaña en ese navegador. Si esta tarda más, la barra sigue avanzando cada vez más lento en lugar de quedarse quieta. Los **segundos** de al lado sí son exactos. |
| 80 – 99 % | *Recibiendo datos · 1,4 MB de 4,7 MB* | **Real.** Son los datos que ya llegaron al navegador sobre el total que el servidor anunció. |

La barra no llega a 100 %: al terminar, la tabla con los datos ocupa su lugar.

Si aun así se llegaran a solapar dos búsquedas de la misma pestaña, la anterior
se descarta y la tabla muestra solo el resultado de la última.

## Cómo se calcula el stock (saldo en vivo)

El **saldo de Movimientos** y el **stock de Existencias** se calculan siempre
en vivo, sumando y restando el kardex (`SUM(cantidad)`: entradas suman,
salidas restan) — nunca se confía en un campo de saldo guardado
(`stock_posterior` del kardex, `productos_bodegas.stock_actual`). Esto evita
que un stock cacheado desincronizado (por ejemplo, por una migración
incompleta) muestre un número que no corresponde a la suma real de
movimientos.

## Por qué una fila sale en negativo (seguimiento)

En **Existencias**, cuando el stock de una fila es **negativo** el número se ve
en rojo y se puede **pulsar**: abre el *Seguimiento del stock negativo*, que
muestra exactamente los movimientos de kardex que producen ese número.

Está disponible en *En general*, *Por lotes*, *Por caducidad* y *Lote +
caducidad*. No está en los agrupados (por producto, categoría o bodega): esa
fila resume varios productos o bodegas, así que no hay un historial único que
seguir. Pulsar el número **no** abre la edición de mínimo/máximo: esa se sigue
abriendo haciendo clic en el resto de la fila.

El seguimiento respeta la clave de la fila: si estás en *Por lotes*, sigue ese
lote (sumando todas sus caducidades, igual que la fila); si estás en *En
general*, sigue el producto en esa bodega con todos sus lotes. También aplica
la **fecha de corte** con la que se generó la tabla, para que el saldo final
del seguimiento sea el mismo número que estás mirando.

### Qué muestra

1. **Un diagnóstico arriba**, en una frase: en qué fecha y con qué documento el
   saldo cruzó a negativo por primera vez, y cuánto salió en ese movimiento.
2. **Los movimientos, en orden cronológico**, con su **saldo corrido**. La fila
   donde el saldo pasa a negativo va resaltada en rojo.
3. **Los otros lotes del mismo producto y bodega**, con su saldo, ordenados de
   más negativo a más positivo.

### Las tres causas que el seguimiento nombra solo

| Aviso | Qué significa | Qué revisar |
|---|---|---|
| **Esta clave no tiene ninguna entrada** | Solo hay salidas: el stock salió sin haber ingresado nunca con ese lote o esa caducidad | Suele ser una venta a la que se le asignó un lote que nunca se compró, o una compra/ingreso sin registrar |
| **N movimientos son de otro ambiente** | Hay movimientos con un `tipo_ambiente` distinto al de la empresa. **Suman en Existencias pero no se ven en la pestaña Movimientos**, así que el negativo parece salir de la nada si solo se mira allí | Van marcados en la tabla del seguimiento con la etiqueta *otro ambiente* |
| (sin aviso, pero visible abajo) **El mismo lote escrito de dos formas** | `A-123` y `A 123`, o uno con un espacio al final, se agrupan por separado: uno queda negativo y el otro con el positivo que le sobra | La tabla *Otros lotes del mismo producto y bodega* los pone juntos; se corrigen desde los documentos que los crearon |

La tabla de abajo es la única parte del modal que **ignora** los filtros de lote,
NUP y caducidad de la pantalla: si los aplicara, escondería justo el lote gemelo
que se está buscando. En *En general* esa tabla no muestra "los otros" lotes sino
**de qué se compone la fila**, y el título lo dice.

> El seguimiento **no corrige nada**: solo explica. La corrección se hace en el
> documento que originó el movimiento, o —cuando lo que está desfasado es el
> stock guardado frente al kardex— desde la pestaña **Auditoría**.

> No confundir con *Auditoría*: esa pestaña compara el stock **guardado** con el
> del kardex y no detecta negativos (si los dos valores coinciden en −50, para
> ella no hay discrepancia). El seguimiento es lo contrario: da por bueno el
> kardex y explica cómo llegó a ser negativo.


## Para el conteo físico

El uso más común: se imprime el listado de existencias, se cuenta en bodega, se
anotan las diferencias y se ajustan en Inventario. Es lo que convierte un conteo
en una corrección trazable en lugar de un número cambiado a mano.

## Solo productos inventariables

Únicamente aparecen los productos marcados como **inventariables**. Si un
artículo no está en el reporte, revise su ficha antes de dar por perdido el
stock.

## Exportar

Disponible en **PDF** y **Excel**. Para el conteo, el PDF es el más práctico.

### Excel o PDF con muchos datos

Cada descarga tiene un máximo de filas:

| Formato | Máximo por descarga | Por qué |
|---|---|---|
| **Excel** | Depende de las columnas de la vista: unas **20.000 filas** en Movimientos detallado | Más filas agotan la memoria del servidor |
| **PDF** | **1.000 filas** | El PDF tarda cada vez más con el largo: 1.000 filas ya son cerca de un minuto |

Si el reporte lo supera, en vez de descargar aparece el aviso **Demasiados datos
para Excel** (o **para PDF**) con el total de filas y el máximo permitido. Si el
PDF no alcanza, pruebe con el Excel, que admite muchas más filas.

Para descargarlo, parta el reporte y exporte cada parte por separado:

- **Movimientos y Consignaciones**: filtre por año con *Fecha desde* y *Fecha
  hasta* (del 01-01 al 31-12 de un mismo año). Si un año sigue siendo mucho,
  hágalo por meses o trimestres.
- **Existencias y Valorización**: acote por bodega, categoría o producto, o use
  *Agrupar por* en lugar del detalle.

## Pestaña Consignaciones

Muestra la mercadería que está **en poder de clientes**: lo entregado en
consignación menos lo devuelto y lo facturado.

### Qué muestra cada fila

Cada fila es una **consignación** (un documento), con:

| Columna | Qué contiene |
|---|---|
| Código | Códigos de los productos de la consignación, separados por coma |
| Fecha | Fecha de emisión y, debajo, el secuencial del documento |
| Cliente | Nombre y, debajo, la identificación |
| Asesor | Vendedor asignado a la consignación |
| Responsable traslado | Quien trasladó la mercadería |
| Lote | Lotes de las líneas de la consignación, separados por coma |
| NUP | NUP de las líneas, separados por coma |
| Total productos | **Suma de las cantidades entregadas** y, debajo, cuántas líneas tiene el documento. Al pasar el mouse se ve el desglose: consignado, retornado y facturado |
| Saldo | Entregado − devuelto − facturado: lo que sigue en poder del cliente |
| Estado | Entregada, Emitida o Anulada |

Cuando la consignación mezcla varios códigos, lotes o NUP, la celda los muestra
separados por coma y recorta con puntos suspensivos; el valor completo aparece
al pasar el mouse.

### Búsqueda y filtros

Los filtros de **Producto, Bodega, Lote, NUP y Caducidad** actúan sobre las
líneas: si busca un lote, el "Total productos" y el "Saldo" de cada fila suman
**solo** las líneas de ese lote, no el documento entero. Los de **Cliente,
Asesor, Responsable, Estado, fechas y N° Consignación** actúan sobre el
documento completo. El N° de consignación acepta tanto el secuencial solo
(`000000113`) como el número con serie (`001-001-000000113`).

### Detalle de una consignación

Al hacer clic en una fila se abre el detalle con sus líneas de producto:
**código del producto** (primera columna), producto, bodega, lote, NUP,
consignado, retornado, facturado, a cambio y saldo, con una fila de
**totales** al pie.

La barra superior del detalle tiene un botón **PDF** que descarga el **estado
completo de la consignación**: el mismo diseño del comprobante de Consignaciones
de Ventas, pero con las columnas *Ret* (retornado), *Fact* (facturado) y
**Saldo** llenas por cada línea, una fila de totales y, al cierre, el resumen:
consignado, devuelto, facturado, entregado a cambio y **saldo en poder del
cliente**. Ese PDF es siempre el documento **entero**, aunque el detalle esté
filtrado por lote, producto o bodega, y usa el modelo general del sistema aunque
la empresa tenga una plantilla de diseño activa para la consignación. Se descarga
sin firmas: es un estado del documento, no un comprobante de entrega.

Junto al PDF está el botón **Excel**, que descarga también el documento
**entero**: una fila por línea con consignado, retornado, facturado, a cambio y
saldo, más dos columnas con los **números de los retornos** (*N.º retornos*) y
de las **facturas de venta** (*N.º facturas*) que explican esas cantidades. Al
final lleva una fila de **totales**.

Si hay filtros de línea activos, el detalle muestra **solo las líneas que
coinciden** — así los totales del detalle cuadran con los de la fila del
listado — y avisa con un enlace **Ver todas las líneas** para mostrar el
documento completo.

### Excel de la pestaña Consignaciones

En la vista **Detallado**, el Excel (y el PDF) de la pestaña trae una fila por
línea de producto con las mismas columnas que el Excel de una consignación:
*N.º retornos* junto a *Retornado*, *N.º facturas* junto a *Facturado* y una
fila de **TOTALES** al final (consignado, retornado, facturado, a cambio y
saldo). Si una línea tiene varios documentos, van separados por coma. Ya no
lleva la columna *Valor a costo*, ni tampoco las vistas agrupadas (por cliente o
por producto).

### De dónde salen "Retornado" y "Facturado"

Las cantidades **Retornado** y **Facturado** son enlaces: al hacer clic se
abren los documentos que las explican, con su fecha, número, cantidad, total y
un botón para **imprimir el PDF** de cada uno.

- **Retornado**: los retornos de consignación emitidos (módulo Retornos CV).
- **Facturado**: la **factura de venta** (su número real
  `establecimiento-punto-secuencial`), no el documento interno de facturación
  de consignación. Si la factura no está en estado *facturada*, se indica su
  estado debajo del número.

La columna **A cambio** es lo que el cliente se quedó como reposición en un
[Cambio de productos](modulos/cambio-producto-cv): también baja del saldo
(`saldo = consignado − retornado − facturado − a cambio`), aunque no abre
documentos al hacer clic. Los cambios emitidos desde la versión 1.16 registran
esa unidad como **facturada** en la factura de venta de lo que el cliente
devolvió: aparece en **Facturado**, con el número de esa factura, y no en *A
cambio*.

El PDF se abre en el módulo dueño del documento, así que el botón **solo
aparece si el usuario tiene permiso de ver** sobre **Facturas de Venta** o
**Retornos CV**, según el caso; si no lo tiene, la columna PDF no se muestra.
Si el documento fue eliminado, en lugar del botón aparece un guion.

## Pestaña Auditoría

Compara, para cada producto y bodega, el stock **guardado**
(`productos_bodegas.stock_actual`) contra el **real** (la suma en vivo del
kardex). Solo se listan las combinaciones que difieren.

El botón **Corregir** de cada fila deja el stock guardado igual al real del
kardex — es la única acción de escritura del módulo. Antes de corregir,
confirme que el kardex de ese producto/bodega está completo; si el kardex
tiene movimientos faltantes, "corregir" solo iguala el guardado a un kardex
incompleto, no repara el dato real. Ante la duda, un conteo físico es la única
forma de saber el stock verdadero.

Toda corrección queda registrada en la auditoría del sistema
(`log_sistema`), con el valor anterior y el nuevo.

**Corregir todo**: además del botón por fila, hay un botón **Corregir todo** en la
cabecera de la tabla que corrige de una vez todas las discrepancias visibles con
los filtros actuales (respeta bodega/producto/búsqueda si están puestos). Pide
confirmación mostrando cuántas va a corregir antes de ejecutar. Cada corrección
individual queda igual de auditada en `log_sistema` que si se hiciera fila por
fila.

La Auditoría siempre queda limitada a la empresa activa en sesión, sin importar
el nivel del usuario: cada empresa revisa su propio inventario.

### Por qué aparecen discrepancias

Además de migraciones incompletas, la causa más frecuente es una condición de
carrera: dos movimientos del mismo producto/bodega procesándose casi al mismo
tiempo (dos ventas simultáneas, una compra mientras se hace un ajuste, etc.)
podían leer el mismo stock de partida y uno sobrescribía silenciosamente el
resultado del otro en el caché — aunque el kardex sí quedaba completo. Se
corrigió con un bloqueo por producto/bodega (`InventarioRepository::lockStock()`)
en todos los puntos donde se lee el stock antes de escribirlo (ventas,
compras, consignaciones, retornos, cambios de producto y ajustes manuales).
Los productos con discrepancias que ya existían antes de esta corrección
siguen apareciendo aquí hasta que se corrigen manualmente.

## Cuántas filas se muestran

En pantalla, cada pestaña muestra **como máximo 5.000 filas**. Si el resultado
llega a ese tope, la tabla lo dice en su última fila: no es un error, es que
el filtro es demasiado amplio para leerlo en pantalla. Dos salidas:

- Afinar los filtros (una bodega, una categoría, un rango de fechas).
- Descargar el **Excel** o el **PDF**, que llegan hasta 50.000 filas y también
  avisan en su última línea si hubiera que recortar.

El desglose *Por lotes* y *Lote + caducidad* es el que más filas genera: cada
producto se multiplica por sus lotes en cada bodega. Conviene usarlo con un
producto o una bodega ya elegidos.

En **Movimientos**, el selector Año arranca en el año más reciente con
movimientos en vez de *Todos*: el saldo corrido obliga a recorrer todo el
histórico del kardex, y sin acotar la fecha la consulta tarda unos segundos
para luego mostrar un listado recortado igualmente. Se puede poner *Todos*
cuando haga falta.

## Qué bodegas ve cada usuario

El reporte muestra únicamente las bodegas a las que el usuario tiene acceso,
según *Bodegas → pestaña Accesos*. Por defecto todos ven todas las bodegas de su
empresa: solo se ocultan aquellas cuyo acceso se le haya quitado expresamente
ahí.

- El filtro **Bodega** de cada pestaña ofrece solo las bodegas permitidas.
- **También se aplica con el filtro en *Todas***: el listado, los indicadores, el
  PDF y el Excel de las cinco pestañas cuentan únicamente esas bodegas. Escribir
  a mano el número de una bodega ajena en la dirección no devuelve nada.
- Lo mismo vale para el detalle de una consignación y su PDF de estado: si todas
  sus líneas están en bodegas que el usuario no puede ver, responde como si el
  documento no existiera.
- Ajustar inventario, editar mínimo/máximo y corregir la auditoría rechazan una
  bodega sin acceso con *No tiene acceso a esa bodega*.
- **Administrador (nivel 2) y superadministrador (nivel 3)** ven todas las
  bodegas de la empresa, sin excepción.

## Permisos

- El permiso de **ver** sobre este módulo abre la página. Qué pestañas aparecen depende del acceso a los módulos de los que sale cada información:
  - **Existencias, Movimientos, Valorización y Auditoría** se muestran solo si el usuario puede **ver** el módulo **Inventario**.
  - **Consignaciones** se muestra solo si puede **ver** el módulo **Consignaciones de Ventas**.
- El reporte se abre en la primera pestaña permitida, en el orden de la barra. Si el usuario no tiene acceso a ninguna, en lugar de las pestañas ve un aviso que indica qué permiso falta.
- La misma regla vale para los datos, el PDF y el Excel de cada pestaña y para sus acciones (editar mínimo/máximo, ajustar inventario, corregir la auditoría, ver el detalle de una consignación): una dirección escrita a mano responde *No tiene permiso para esta acción*.
- Editar mínimo/máximo y categoría, ajustar inventario y corregir la auditoría exigen además el permiso de **actualizar** sobre este reporte.
- El **superadministrador** (nivel 3) ve las cinco pestañas siempre.
- Los permisos se asignan en *Configuración → Permisos por módulo*.

## Errores frecuentes

- **Un producto no aparece**: no es inventariable.
- **Falta una bodega, o su stock no suma en los totales**: a ese usuario se le
  quitó el acceso a esa bodega en *Bodegas → Accesos*. Es una restricción de
  acceso, no un filtro: poner *Todas* en el selector no la trae de vuelta.
- **El stock está en otra bodega**: revise el filtro de bodega.
- **El valor no coincide con la contabilidad**: compare contra el mayor de la
  cuenta de inventario; las diferencias suelen venir de compras sin procesar sus
  entradas.
- **Existencias y Valorización vacías, pero Movimientos (Kardex) sí muestra
  datos**: en empresas migradas desde el sistema anterior, el kardex migrado no
  actualizaba el stock cacheado del producto/bodega del que leen estas dos
  pestañas. Se corrigió para migraciones nuevas; las empresas ya migradas antes
  de la corrección necesitan el script de reparación
  `database/migrations/20260730_backfill_productos_bodegas_migracion.sql`.
- **El módulo tarda en abrir** (la página demora antes de mostrarse): falta
  ejecutar `database/20260916_reporte_inventarios_indices_ajuste.sql` en esa
  base. Sin sus índices el reporte funciona y da los mismos datos, pero llenar
  los selectores Usuario y Año recorre el kardex completo de la empresa.

## Historial de cambios

- **1.30** — **Consignaciones en Excel**: el Excel del Detallado lleva los
  números de retornos y de facturas de cada línea y una fila de totales, y ya no
  la columna *Valor a costo* (tampoco en las vistas agrupadas). Nuevo botón **Excel** en el detalle de cada
  consignación.
- **1.29** — **PDF con muchos datos**: el PDF admite hasta 1.000 filas; si el
  reporte las supera aparece el mismo aviso que en el Excel, en lugar de una
  pestaña en blanco. El Excel y el PDF se descargan sin abrir otra pestaña.
- **1.28** — **Excel con muchos datos**: si el reporte supera el máximo de filas
  que se puede armar en Excel, ya no se abre una pestaña en blanco: aparece un
  aviso con el total y cómo acotarlo (por año en Movimientos y Consignaciones).
- **1.27** — **Existencias: filtros que no se aplicaban**. *Estado* no filtraba
  en los agrupados (Por Producto / Categoría / Bodega). *Consignado* no filtraba
  en *Por lotes*, *Por caducidad* ni *Lote + caducidad*, y *Estado* seguía
  activo en esos desgloses sin hacer nada; ahora queda desactivado. El producto
  escrito sin elegirlo de la lista se descartaba; ahora filtra por nombre o
  código, también en el PDF y el Excel. Al reescribir el producto ya no se
  queda puesto el elegido antes. *Saldo a fecha* dejaba fuera los movimientos
  del mismo día; ahora cuenta el día completo. Se añadió la sección *Qué
  filtros aplican en Existencias*.
- **1.26** — **Movimientos (Detallado)**: el listado, el PDF y el Excel se
  ordenan por **fecha del movimiento, del más antiguo al más reciente**. Antes
  se agrupaban por producto y bodega y solo dentro de cada uno iban por fecha.
- **1.25** — **Existencias**: el stock negativo de una fila ahora se puede
  pulsar y abre el **Seguimiento del stock negativo**: los movimientos de
  kardex que componen esa fila, en orden y con saldo corrido, con la fila donde
  el saldo cruzó a negativo resaltada, y los demás lotes del mismo producto y
  bodega debajo. Nombra solo las dos causas que no se ven en la tabla: que la
  clave no tenga ninguna entrada, y que haya movimientos de **otro ambiente**
  (que suman en Existencias pero no aparecen en la pestaña Movimientos).
  Disponible en *En general* y en los tres desgloses; respeta la fecha de corte
  con la que se generó la tabla.
- **1.24** — **Mientras carga**: el botón **Mostrar** de cada pestaña queda
  bloqueado —junto con su PDF, su Excel y el *Corregir todo* de Auditoría—
  mientras esa pestaña genera su reporte, así que ya no se pueden
  encadenar consultas pesadas a fuerza de clics. En la tabla aparece un mensaje
  *Generando el reporte…* con barra de avance y porcentaje: hasta el 80 % es una
  estimación por tiempo (con los segundos transcurridos al lado, que sí son
  exactos) y del 80 al 99 % es el avance real de la descarga de los datos.
- **1.23** — **Existencias**: los desgloses *Por lotes*, *Por caducidad* y
  *Lote + caducidad* ya no listan los lotes agotados. Solo salen las filas con
  **stock total distinto de cero** (bodega + consignado). El kardex guarda
  todos los lotes que alguna vez entraron, así que el listado venía cargado de
  filas en 0 que no aportaban nada. Aplica también al PDF y al Excel. *En
  general* no cambia: ahí las filas en cero (QUIEBRE) se siguen viendo, y un
  total negativo en el desglose también, por ser una inconsistencia.
- **1.22** — El **código del producto** pasa a ir pegado al nombre en las dos
  vistas donde estaban separados: en *Lote + consignación* queda justo antes de
  **Descripción** (pantalla, PDF y Excel), y en el **PDF/Excel de
  Consignaciones detallado**, justo antes de **Producto**. Así se leen de
  corrido "código — producto" en lugar de tener que cruzar la fila entera.
- **1.21** — Ninguna pestaña vuelve a consultar sola: elegir un producto o un
  cliente en el buscador, cambiar el selector *Detalle* o cambiar *Año* y *Mes*
  ya no lanza la búsqueda. La tabla se actualiza únicamente al pulsar
  **Mostrar**, así se pueden acomodar varios filtros seguidos sin disparar una
  consulta en cada cambio.
- **1.20** — Nueva opción **Lote + consignación** en el selector *Detalle* de
  Existencias: una fila por lote/NUP entregado, con fecha, secuencial, cliente,
  asesor, responsable de traslado, bodega y el desglose consignado / retornado
  / facturado / a cambio / saldo. Responde "con qué cliente y con qué documento
  salió este lote". Solo la ven los usuarios que además pueden ver
  Consignaciones de ventas.
- **1.19** — El **código del producto pasa a ser la primera columna** de todos
  los reportes del módulo: Existencias (detallado y los tres desgloses),
  Movimientos, Valorización, Consignaciones y Auditoría, y también en sus
  exportaciones a PDF y Excel. En los agrupados *Por Producto*, el código deja
  de ir pegado al nombre ("COD - Nombre") y ocupa su propia columna. En
  Auditoría deja de mostrarse en letra pequeña bajo el nombre. En Existencias
  detallado, la columna Código es ordenable.
- **1.18** — Pestaña **Consignaciones**: el detalle de una consignación muestra
  el **código del producto** como primera columna, y la ventana del detalle es
  más ancha para que la columna nueva quepa sin apretar el nombre del producto.
- **1.17** — El reporte respeta las **bodegas asignadas al usuario**. Antes el
  selector de bodega ya venía filtrado, pero los datos no: con el filtro en
  *Todas* —o escribiendo el número de la bodega en la dirección de una
  exportación— se veía el stock, el kardex y las consignaciones de bodegas sin
  acceso. Ahora la restricción se aplica a las cinco pestañas, a sus
  indicadores, a su PDF y Excel, al detalle de una consignación y a las acciones
  que escriben (ajuste, mínimo/máximo y corrección de auditoría).
- **1.16** — Lo que un cambio de productos entrega desde una consignación ahora
  se registra en Facturación de consignaciones y se ve en **Facturado** (con la
  factura de venta de lo devuelto), no en *A cambio*. El saldo no cambia.
- **1.15** — **El reporte abre y busca mucho más rápido.** Medido sobre 1,8
  millones de movimientos de kardex (una empresa con 300.000, entre otras 100
  empresas) y 20.000 líneas de consignación, con resultados idénticos a la
  versión anterior: Movimientos del año en curso de 5,1 s a 0,8 s (*Por mes*, de
  4,7 s a 0,4 s); Existencias de 2,0 s a 1,3 s, y filtrando **dejan de recalcular
  la empresa entera** (1 categoría: de 2,6 s a 0,06 s; 1 producto: de 1,1 s a
  0,01 s); Valorización y el valor de inventario de *Índices financieros*, de 1,3 s
  a 1,0 s; Consignaciones, de 1,1 s a 0,5 s. Otros cambios que se notan: ordenar
  una columna de Existencias ya no recarga la página ni borra el resultado;
  mientras un reporte o una exportación se genera, el usuario puede seguir
  usando el sistema en otra pestaña (antes todo quedaba esperando a que
  terminara); si se pulsa Mostrar varias veces, gana siempre la última búsqueda;
  y la respuesta de cada búsqueda viaja comprimida y sin una copia de los datos
  que la pantalla no usaba (5.000 filas: de 7 MB a unos cientos de KB).
  **Requiere ejecutar** `database/20260916_reporte_inventarios_indices_ajuste.sql`,
  que **reemplaza** al SQL de las versiones 1.7 y 1.9: crea los índices de los
  selectores Usuario y Año y **quita** `idx_kardex_stock_por_bodega`, que con el
  código anterior dejaba la pestaña Consignaciones en ~45 s. Sin ese SQL el
  reporte funciona igual, solo que tarda más en abrir.
- **1.14** — PDF del estado de la consignación: sin la columna *Cambio*;
  *Retorno* y *Facturados* se abrevian a **Ret** y **Fact** y la Descripción
  toma el ancho sobrante. Lo entregado a cambio se sigue viendo en el resumen
  final y sigue descontado del saldo.
- **1.13** — Pestaña **Consignaciones**: el detalle de una consignación trae un
  botón **PDF** que descarga el estado completo del documento (mismo diseño del
  comprobante de Consignaciones de Ventas) con lo retornado, lo facturado, lo
  entregado a cambio y el saldo por línea, y el resumen del saldo en poder del
  cliente.
- **1.12** — Pestaña **Consignaciones**: el saldo descuenta también lo entregado
  **a cambio** (Cambios de productos); nueva columna *A cambio* en el detalle,
  los totales y el Excel. El consignado que resta la pestaña Existencias aplica
  el mismo descuento.
- **1.11** — Las pestañas se muestran según el acceso del usuario a otros módulos: **Existencias, Movimientos, Valorización y Auditoría** solo si puede ver **Inventario**, y **Consignaciones** solo si puede ver **Consignaciones de Ventas**. El reporte abre en la primera pestaña permitida y, sin ninguna, muestra un aviso. Nueva sección *Permisos*.
- **1.10** — Pestaña **Consignaciones**: al buscar ya se ve en pantalla lo que se
  buscó. El listado agrega las columnas **Lote** y **NUP**, "Productos" pasa a
  mostrar la **suma de las cantidades entregadas** (con el desglose
  consignado/retornado/facturado al pasar el mouse y, debajo, el número de líneas
  del documento) y "Vendedor" se llama **Asesor**, como en el resto del sistema.
  El detalle de una consignación ahora respeta los filtros de línea (antes la
  fila sumaba solo el lote buscado y el detalle mostraba el documento entero, con
  otro total) y cierra con una fila de totales; un enlace permite ver igualmente
  todas las líneas. En Retornado/Facturado, cada documento relacionado trae un
  botón para imprimir su PDF —visible solo para quien tenga permiso de ver el
  módulo dueño del documento—, y en Facturado se muestra el número de la
  **factura de venta** en lugar del documento interno de facturación de
  consignación. El Excel y el PDF del reporte incluyen ahora Asesor, Lote, NUP,
  Retornado y Facturado.
- **1.9** — **El reporte tarda mucho menos en mostrar los datos.** Medido sobre
  una carga de prueba de 2.000 productos × 5 bodegas y 300.000 movimientos:
  Existencias pasó de 27 s a 1,6 s, Valorización de 30 s a 1,2 s y Auditoría
  de 4,9 s a 0,4 s. Tres motivos: (1) cada *Mostrar* calculaba además unos
  indicadores que la pantalla no enseña, repitiendo entera la consulta que
  acababa de hacer — ya no se calculan; (2) el stock, el costo y lo consignado
  se consultaban de nuevo para cada línea del listado, y ahora se calculan una
  sola vez para toda la empresa; (3) hay un tope de filas en pantalla, porque
  el desglose por lote podía generar cientos de miles de filas y dejar el
  navegador colgado (ver *Cuántas filas se muestran*). Además, **Movimientos**
  arranca con un año seleccionado en vez de *Todos*. **Requiere ejecutar**
  `database/20260914_indices_reporte_inventarios_arranque.sql`, que ahora trae
  un tercer índice (el del stock por producto y bodega); sin él el reporte da
  los mismos números, pero más despacio. *(Ese SQL quedó reemplazado en la
  versión 1.15: su tercer índice resultó dañino.)*
- **1.8** — **Existencias**: nuevo selector **Detalle** (En general / Por
  lotes / Por caducidad / Lote + caducidad) como primer filtro. Antes, las
  opciones *Por Lote*, *Por NUP* y *Por Caducidad* de **Agrupar por** hacían
  las tres exactamente lo mismo (una fila por cada combinación de lote, NUP y
  caducidad; solo cambiaba el orden), así que no había forma de ver el stock
  de un lote completo ni de saber qué se vence un día concreto. Esas tres
  opciones salen de *Agrupar por*, que se queda con las consolidaciones
  (Producto, Categoría, Bodega). **Consignaciones**: la columna *Productos*
  contaba productos distintos, así que una consignación con el mismo producto
  en varias líneas (una por lote) anunciaba menos líneas de las que luego
  aparecían al abrirla; ahora cuenta las líneas del documento. Además el
  **N° de consignación** pasa a ser el primer filtro, en **Movimientos** el
  **Tipo de movimiento** pasa a ser el primero, y las cinco pestañas tienen un
  botón para **limpiar todos los filtros** junto a *Mostrar*.
- **1.7** — El módulo **abre mucho más rápido**. Al entrar, la pantalla
  llenaba los selectores "Origen", "Usuario" y "Año" recorriendo TODOS los
  movimientos de kardex de la empresa (tres veces), y la lista de categorías
  contaba además cuántos productos tiene cada una, dato que ningún selector
  muestra. Medido sobre 600.000 movimientos: entre 0,7 y 1,3 segundos de
  espera antes de ver nada, creciendo cada mes. Ahora esos selectores se
  resuelven por índice (1,3 ms en la misma prueba) y la página ya no descarga
  una librería de gráficos externa que no usaba. **Requiere ejecutar**
  `database/20260914_indices_reporte_inventarios_arranque.sql` *(reemplazado en
  la versión 1.15)*; sin él el reporte funciona igual, solo que sin la mejora de
  velocidad. La misma
  corrección acelera la apertura del módulo **Inventario**, que llenaba dos
  de esos selectores de la misma forma.
- **1.6** — Pestaña **Consignaciones** mucho más rápida. El cálculo del saldo
  vigente repetía por cada línea la búsqueda del costo en el kardex sin ningún
  índice que la sostuviera, y además ejecutaba dos veces la consulta completa
  en cada "Mostrar" (una para la tabla y otra para unos indicadores que la
  pantalla no muestra). Se notaba sobre todo al filtrar por **Responsable
  traslado**. Requiere ejecutar `database/indices_reporte_consignaciones.sql`.
  Corrección relacionada: una consignación cuyo producto o cliente ya no
  existiera en su tabla desaparecía del listado sin aviso y su saldo no sumaba
  en los totales; ahora se muestra igual, con el nombre en blanco.
- **1.5** — Quitado el check **Todas las empresas** de Auditoría (era solo
  para Nivel 3): cada empresa audita y corrige únicamente su propio
  inventario, sin excepción de nivel.
- **1.4** — Botón **Corregir todo** en Auditoría (corrige de una vez todas las
  discrepancias filtradas) y check **Todas las empresas** para Nivel 3 (audita
  y corrige el sistema completo, no solo la empresa activa).
- **1.3** — Corregida la causa raíz más frecuente de las discrepancias que
  detecta Auditoría: una condición de carrera al escribir el stock guardado
  cuando dos movimientos del mismo producto/bodega se procesaban casi al
  mismo tiempo. Ver "Por qué aparecen discrepancias" arriba.
- **1.2** — Nueva pestaña **Auditoría** para revisar y corregir diferencias
  entre el stock cacheado y el kardex. El saldo de Movimientos y el stock de
  Existencias ahora se calculan siempre en vivo desde el kardex, en lugar de
  confiar en campos de saldo guardados. Nueva pestaña **Consignaciones** a
  nivel de cabecera con detalle por línea.
- **1.1** — Corrección: el kardex migrado desde el sistema anterior no
  sincronizaba el stock cacheado, dejando vacías Existencias y Valorización
  para empresas migradas.
- **1.0** — Versión inicial.
