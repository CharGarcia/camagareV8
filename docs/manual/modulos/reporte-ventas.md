---
titulo: Reporte de ventas
resumen: Ventas del periodo con filtros por cliente, vendedor, producto y borradores, agrupables, ordenables y exportables.
categoria: Reportes
ruta_modulo: modulos/reporte_ventas
tipo: modulo
visibilidad: todos
etiquetas: reporte de ventas, ventas, cuanto vendi, por cliente, por vendedor, por producto, estadisticas, exportar, pdf, excel, establecimientos, sucursales, matriz, mismo ruc, consolidado por ruc, borradores, borrador, facturas en borrador, incluir borradores, documentos sin autorizar, pendientes de enviar al sri, ordenar, ordenamiento, ordenar por columna, de mayor a menor, quien compro mas, saldo por cobrar, saldo x cobrar, cuanto me debe el cliente, nro facturas, numero de documentos, cartera en el reporte de ventas, acceso total, permiso de ver todos, registros propios, solo mis ventas, no veo las ventas de otro, cada usuario ve lo suyo, documentos migrados no aparecen, cartera del vendedor, mis clientes, clientes asignados, vendedor vinculado, usuario del sistema, el vendedor no ve nada, asesor solo ve sus clientes, nivel de usuario, administrador ve todo, el asesor ve las ventas de todos, imprimir el reporte, logo en el pdf, el pdf sale angosto, el pdf no ocupa la hoja, nombre del producto cortado, filtros aplicados en el pdf, encabezado del pdf, totales repetidos en el pdf, pdf horizontal, numero de pagina, boton buscar, no se actualiza, no cambia al elegir, hay que pulsar buscar, boton amarillo, filtros sin aplicar, unidades vendidas, unidades por mes, cantidades por mes, cuantas unidades vendi, ventas por producto y mes, producto por mes, rotacion mensual, tabla por meses, una columna por mes, marca, categoria, filtrar por marca, filtrar por categoria, ventas de una marca, ventas de una categoria, linea de productos
version: 1.9
orden: 10
estado: activo
---

El **reporte de ventas** responde a la pregunta de cuánto se vendió, a quién y de
qué, en el periodo que se indique.

## Filtros

| Filtro | Para qué |
|--------|----------|
| Fecha desde / hasta | El periodo a consultar |
| Cliente | Ventas de un cliente concreto |
| Vendedor | Ventas de un vendedor concreto. Las notas de crédito no llevan vendedor propio: se les atribuye el vendedor de la factura que modifican, así que sí entran en el filtro (también en *Facturas − NC*) |
| Producto | Ventas de un producto concreto |
| Marca / Categoría | Solo los productos de esa marca o categoría (las de la ficha del producto). Están en la primera fila, a continuación de *Agrupar por*. Ver la sección *Filtrar por marca o categoría* |
| Borradores | *Sin borradores* (por defecto), *Con borradores* o *Solo borradores*. Ver la sección *Documentos en borrador* |

Los filtros se combinan: *las ventas del producto X al cliente Y en marzo*.

**El reporte solo se consulta al pulsar Buscar** (o Enter en el formulario).
Cambiar un filtro —tipo de documento, agrupación, marca, categoría, año, mes,
fechas, establecimientos, cliente, borradores, vendedor, producto, variante,
info adicional o los botones de limpiar— no actualiza la tabla: el botón
**Buscar se pone en ámbar** para avisar que hay filtros sin aplicar. Así se
pueden ajustar varios filtros seguidos y lanzar una sola consulta. Ordenar por
una columna sí regenera la tabla, siempre que no haya filtros pendientes; si
los hay, el orden queda guardado y se aplica al pulsar Buscar.

## Filtrar por marca o categoría

Los selectores **Marca** y **Categoría** (primera fila, después de *Agrupar por*)
acotan el reporte a los productos que tienen esa marca o esa categoría en su
ficha (módulo *Productos*). Solo listan las marcas y categorías de la empresa
activa, y se pueden combinar entre sí y con el resto de filtros. La estrella
junto a cada uno lo guarda como favorito.

Qué cambia según la vista:

- En **Por producto**, **Por variante** y **Unidades por producto / mes** se
  filtran las **líneas**: solo aparecen los productos de esa marca o categoría,
  con sus cantidades e importes.
- En el **Detallado**, **Por cliente**, **Por fecha** y **Por mes** se filtran
  los **documentos**: entra toda factura que tenga al menos una línea de esa
  marca o categoría, con su **total completo** (no solo la parte de esa marca).
  Es la misma regla que ya aplicaba el filtro *Producto*. Para ver únicamente
  lo vendido de la marca, use *Por producto* o *Unidades por producto / mes*.
- Las tarjetas de arriba (documentos y totales) siguen a los documentos, así que
  en las vistas por línea pueden ser mayores que la suma de la tabla.
- En **consolidado por RUC**, la marca o categoría elegida se cruza **por
  nombre** con las de los demás establecimientos (cada uno tiene su propia
  lista); si en una sucursal la marca se llama distinto, sus ventas no entran.
- Un producto **sin marca** (o sin categoría) en su ficha nunca sale al filtrar
  por marca (o categoría).
- El PDF indica la marca y la categoría usadas en la caja *Filtros aplicados*.

## El estado importa

Es lo que más cambia las cifras:

- **Autorizada**: la venta real, aprobada por el SRI. Es lo que hay que mirar
  para saber cuánto se vendió. En los recibos de venta, el equivalente es el
  recibo **emitido**.
- **Borrador**: guardada pero aún no enviada al SRI (o, en recibos, aún no
  emitida). Todavía puede cambiar.
- **Anulada**: dejada sin efecto. No es venta y nunca entra en el reporte; la
  tarjeta de documentos solo muestra cuántas hay (*Anul.*).

Por defecto el reporte suma únicamente lo que es venta: facturas y notas de
crédito autorizadas, y recibos emitidos. Un recibo que ya se facturó no se cuenta
dos veces: aparece como la factura.

Si el reporte no coincide con lo esperado, revise primero el selector
**Borradores**.

## Documentos en borrador

El selector **Borradores** (segunda fila de filtros, bajo *Tipo de documento*)
decide si los documentos en borrador entran al reporte:

- **Sin borradores** (por defecto): solo los documentos válidos, como siempre.
- **Con borradores**: agrega los borradores a la tabla, las tarjetas, el gráfico,
  el PDF y el Excel. Sirve para ver cuánto se vendería si se emiten los
  pendientes.
- **Solo borradores**: lista únicamente los borradores, para revisar qué falta
  enviar al SRI.

Cuando se incluyen borradores:

- En el detallado cada documento muestra su estado (*BORRADOR* en gris).
- La tarjeta de documentos cambia a *Doc. Aut. + Borr.* o *Doc. Borradores*, y
  el **Gran Total** indica *(con borradores)* o *(solo borradores)*.
- El PDF y el Excel llevan en el encabezado la línea *Estados*, y el Excel del
  detallado agrega la columna **Estado**.
- Con *Facturas − NC* la opción se aplica a ambos documentos: también se restan
  las notas de crédito en borrador.

La estrella junto al selector lo guarda como favorito, para que el reporte abra
siempre con esa opción. Solo aparecen los borradores del ambiente actual de la
empresa (producción o pruebas), igual que en el listado de Facturas de Venta.

## Consolidar varios establecimientos (solo desde la matriz)

Cuando un mismo RUC tiene varios establecimientos registrados como empresas
distintas (matriz y sucursales), el reporte muestra por defecto solo las ventas
de la empresa activa. Desde la **matriz** aparece el filtro **Establecimientos**,
el mismo que tienen Cuentas por Cobrar, Cuentas por Pagar y el Reporte
Consolidado:

- **Solo este (matriz)**: comportamiento normal.
- **Consolidado (N establec.)**: junta las ventas de todos los establecimientos
  del mismo RUC a los que el usuario tiene acceso. Tarjetas, tabla, gráfico, PDF
  y Excel consolidan de la misma forma; las agrupaciones (por cliente, producto,
  fecha o mes) suman los establecimientos en una sola fila.

Reglas:

- El filtro **solo aparece en la matriz** del grupo (la empresa marcada como
  matriz en *Empresas*) y solo si existe al menos otro establecimiento accesible.
- En el **detallado**, cada factura lleva un **badge con el código del
  establecimiento** (001, 002, …) delante del número. En el PDF y el Excel del
  detallado se agrega la columna **Estab.** y el encabezado indica *Alcance:
  Consolidado por RUC*.
- Los filtros **Cliente** y **Producto** se cruzan entre establecimientos por
  identificación y por código de producto (cada establecimiento tiene su propia
  lista). El filtro **Vendedor** es por establecimiento: en consolidado solo
  acota las ventas del establecimiento donde existe ese vendedor.
- En la agrupación **por producto**, un mismo producto puede aparecer una vez
  por establecimiento si su código no coincide entre ellos.
- Cada establecimiento se filtra por **su propio ambiente** (producción o
  pruebas), no por el de la matriz.

## Agrupación

Los resultados se pueden agrupar (por cliente, por producto, por periodo) para
pasar del detalle al resumen sin cambiar de pantalla. Es lo que permite ver de un
vistazo qué cliente compra más o qué producto rota mejor.

### Por cliente: la columna Saldo x Cobrar

En la agrupación **Por cliente**, junto al nombre va el **Saldo x Cobrar**: lo
que queda pendiente de los documentos incluidos en el reporte. Así, en la misma
fila, se ve cuánto compró el cliente y cuánto de eso sigue sin cobrarse.

- Es el saldo de **los documentos del reporte**, con los filtros aplicados (mes,
  vendedor, establecimiento…), no la cartera histórica del cliente: por eso
  cuadra con el *Gran Total* de su propia fila.
- Se calcula igual que en **Cuentas por Cobrar** —total + notas de débito −
  cobros − retenciones − notas de crédito—, así que el mismo cliente muestra el
  mismo saldo en los dos módulos. Si tuvo notas de débito, el saldo puede quedar
  algo por encima del total facturado: esa es la razón.
- Con el tipo de documento **Notas de crédito** la columna sale en cero (una nota
  de crédito no genera saldo por cobrar), y en **Recibos de venta** es el total
  del recibo menos lo cobrado.
- El **número de documentos** de cada cliente, que antes ocupaba esta columna,
  sigue disponible: aparece al pasar el mouse por el nombre del cliente.
- La columna ordena el reporte como cualquier otra, y sale igual en el PDF y en
  el Excel (con su total al pie, en el PDF).

### Unidades por producto / mes

La agrupación **Unidades por Producto / Mes** responde a *cuántas unidades de
cada producto se vendieron cada mes*. Es una tabla de cantidades, no de dinero:

- Una **fila por producto** con su **código**, su **descripción**, **una columna
  por cada mes** del período elegido y, al final, la columna **Total** con las
  unidades del producto en todo el período.
- Los meses son los que abarcan las fechas *Desde* y *Hasta* del filtro,
  **tengan o no ventas** (un mes sin ventas sale en cero, en gris). Con el año
  completo salen doce columnas; con un solo mes, una. Al elegir esta agrupación,
  el selector *Mes* pasa a *Todos* para abrir el año entero, pero se puede volver
  a acotar. Si se deja alguna fecha vacía, ese extremo se toma del primer o
  último mes con ventas.
- **Solo aparecen los productos que vendieron algo** en el período (total mayor
  que cero). Los que no se movieron no ocupan fila.
- La última fila, **TOTAL**, suma cada mes y el total de todos los productos
  listados, y el título indica cuántos productos son.
- Las cantidades se muestran sin decimales cuando son enteras y con dos cuando
  no (p. ej. 2.50 kg).
- El producto se identifica por su **código**: en consolidado por RUC, el mismo
  código de distintos establecimientos se suma en una sola fila. Una línea sin
  producto del catálogo (concepto libre) muestra el código y la descripción que
  se escribieron en la factura.
- Con **Facturas − NC**, a cada mes se le restan las unidades devueltas en notas
  de crédito de ese mes; un producto que quede en cero o en negativo no aparece.
- Con **Recibos de venta** o **Notas de crédito** se cuentan las unidades de
  esos documentos.
- El gráfico muestra la **curva de unidades vendidas por mes** (la suma de todos
  los productos de la tabla), sin signo de dólar.
- Se combina con todos los filtros, en especial **Marca** y **Categoría** (ver
  *Filtrar por marca o categoría*): *cuántas unidades de la marca X vendí cada
  mes de este año*.
- Ordena por código, descripción, total o **por cualquier mes** (clic en la
  cabecera del mes). El PDF y el Excel salen con las mismas columnas, la misma
  fila TOTAL y el mismo orden; con más de seis meses el PDF sale en hoja
  horizontal.

## Ordenar los resultados

Los títulos de las columnas ordenan el reporte: un clic ordena de menor a mayor y
otro clic invierte el orden. La flecha del título indica por cuál se está
ordenando. Funciona en el detallado y en todas las agrupaciones: por ejemplo,
*Por cliente* ordenado por **Gran Total** deja arriba al cliente que más compró.

- **El PDF y el Excel salen con ese mismo orden**, el que esté marcado en la
  pantalla al momento de descargarlos.
- Cada agrupación ordena por sus propias columnas. Si cambia de agrupación y la
  columna elegida no existe en la nueva, se vuelve al orden habitual de esa
  vista: el detallado por fecha, *Por cliente* por total, *Por producto* y *Por
  variante* por cantidad vendida, *Por fecha* / *Por mes* por el periodo y
  *Unidades por producto / mes* por el total de unidades.
- En *Unidades por producto / mes* también ordena **cada columna de mes**. Si
  después cambia el período y ese mes ya no está, se vuelve al orden por total.
- El orden elegido **se recuerda** para la próxima vez que abra el reporte.

## Quién ve qué: nivel del usuario y permiso de Acceso total

El reporte respeta el nivel del usuario y el permiso **Acceso total** del módulo
(*Configuración → Permisos por módulo*), con la misma regla que Cuentas por
Cobrar y el Reporte de Ventas por Vendedor:

- **Administradores (nivel 2) y superadministradores (nivel 3)**: ven todas las
  ventas de la empresa (y del grupo, en consolidado), tengan o no marcado
  *Acceso total*.
- **Usuarios de nivel 1 con acceso total**: también ven todas las ventas.
- **Nivel 1 sin acceso total y el usuario es un vendedor**: ve **solo lo de su
  vendedor**, es decir las ventas que llevan **su nombre** en el campo *Vendedor*
  y, si una venta no tiene vendedor, las de los **clientes que tiene asignados**
  (campo *Vendedor* de la ficha del cliente). Nunca ve las que llevan el nombre de
  otro vendedor, aunque el cliente sea suyo. Las notas de crédito, que no llevan
  vendedor, cuentan para el de la factura que modifican y, si esa factura no tiene
  vendedor o no se encuentra, para el vendedor asignado al cliente de la nota.
- **Nivel 1 sin acceso total y el usuario no es vendedor** (un cajero, un
  digitador): ve solo los documentos que **él registró** — las facturas, los
  recibos de venta y las notas de crédito que emitió.
- En los dos casos, lo que no sale en la tabla tampoco entra en las tarjetas de
  arriba, en el resumen de estados, en ninguna agrupación (*Por cliente*, *Por
  producto*, *Por mes*…), en el neto *Facturas − NC*, ni en el PDF y el Excel:
  todo parte del mismo filtro. Los buscadores de *Producto* e *Información
  adicional* solo sugieren valores tomados de esos documentos. El resumen de
  ventas de la **app móvil** aplica la misma regla.
- A estos usuarios **el filtro *Vendedor* les queda fijo**: el vendedor ve su
  propio nombre y quien no es vendedor ve *Sin vendedor vinculado*. No pueden
  elegir otro, y el sistema ignora cualquier otro vendedor que se le envíe.

**Cómo sabe el sistema qué vendedor es el usuario.** Se resuelve en este orden:
primero el campo **Usuario del sistema** de la ficha del vendedor (módulo
Vendedores), que es lo que el administrador declaró a mano; si no está, la
**cédula del usuario** contra la identificación del vendedor (también calza si
uno tiene el RUC de persona natural y el otro la cédula). En consolidado por
RUC se busca el vendedor en cada establecimiento. Para quien ve todo, el filtro
*Vendedor* de la pantalla sirve para acotar y no cambia quién ve qué.

Para usuarios sin vendedor, una advertencia sobre documentos antiguos: los que
se **migraron** desde el sistema anterior quedaron a nombre del usuario que
corrió la migración, así que solo él (o alguien con acceso total) los verá.

## Exportar

El reporte se exporta a **PDF** y **Excel**, con las mismas filas, los mismos
filtros y el mismo orden que se ve en pantalla. El Excel es el que conviene
cuando se va a seguir analizando por fuera.

### Qué trae el PDF

El PDF es la misma pantalla en hoja, pensado para imprimir o enviar por correo:

- **Encabezado con el logo** del establecimiento a la izquierda del nombre de la
  empresa, el título del reporte con la agrupación elegida (*Reporte de Ventas ·
  Por producto*) y la fecha y hora en que se generó. Si el establecimiento no
  tiene logo cargado, el encabezado sale centrado, sin espacio vacío.
- **Caja "Filtros aplicados"**, en dos columnas, con todo lo que se usó para
  armar ese reporte: alcance (este establecimiento o consolidado por RUC), tipo
  de documento, período, agrupación, borradores, vendedor, cliente y producto, y
  —solo si se usaron— variante, info adicional y estado. Así la hoja impresa se
  entiende sin tener el módulo abierto.
- **Banda de indicadores** con los mismos valores de las tarjetas de la pantalla:
  documentos, subtotal 0 %/exento, base con IVA, IVA y gran total.
- **El listado ocupa todo el ancho de la hoja.** En las vistas agrupadas la hoja
  va vertical y la columna descriptiva (producto, cliente) se lleva el espacio
  que sobra, para que los nombres largos se lean; en la vista **Detallado**, que
  tiene doce columnas, la hoja sale **horizontal**, igual que **Unidades por
  producto / mes** cuando el período pasa de seis meses (con más de doce, la
  letra se reduce un punto para que entren todas las columnas). Un nombre o un
  código más largo que su columna se parte en varias líneas: nunca se pisa con
  la columna vecina ni se sale de la hoja.
- Cada producto o cliente muestra su **código o RUC** debajo del nombre, igual
  que en la pantalla, y las filas van sombreadas de forma alterna.
- La **cabecera de la tabla se repite en cada página**, abajo a la derecha va
  *Página X/Y*, y la fila **TOTALES GENERALES** aparece una sola vez al final
  (con el número de productos, clientes o documentos que se sumaron).

## Errores frecuentes

- **Las cifras no coinciden con la contabilidad**: revise el selector
  **Borradores**; con borradores las cifras incluyen documentos que todavía no
  son ventas. Las anuladas nunca cuentan.
- **Falta una venta**: compruebe su fecha de emisión y que no esté anulada. Si
  está en borrador, elija *Con borradores*.
- **No veo las ventas de otros vendedores**: un usuario de nivel 1 sin el permiso
  de *acceso total* ve solo lo de su vendedor (ventas a su nombre y, sin
  vendedor, las de sus clientes) si es vendedor, y solo lo que registró si no lo
  es (ver *Quién ve qué: nivel del usuario y permiso de Acceso total*). Si un
  vendedor no ve nada, revise que su ficha tenga el *Usuario del sistema* o la
  misma cédula que su usuario, y que las ventas lleven su nombre. Si a un usuario
  sin vendedor le faltan documentos **antiguos**, suele ser porque se migraron a
  nombre de otro usuario.
- **Un vendedor no ve una venta a su cliente**: si la venta lleva el nombre de
  otro vendedor, es de ese vendedor y no le aparece, aunque el cliente esté
  asignado a él.
- **Un vendedor ve las ventas de todos**: revise el nivel de su usuario (los
  niveles 2 y 3 ven todo el reporte) y que no tenga marcado *Acceso total* en
  este módulo.
- **El Excel salió en otro orden**: se exporta con el orden que estaba marcado en
  la pantalla; si cambió la agrupación después de ordenar, revise la flecha de la
  cabecera antes de descargar.
- **Filtro por marca y el total no baja**: en el Detallado y en las vistas por
  cliente, fecha o mes, el filtro deja el documento completo si tiene una línea
  de esa marca. Para ver solo lo vendido de la marca use *Por producto* o
  *Unidades por producto / mes*.
- **Un producto no sale al filtrar por marca o categoría**: revise que las
  tenga asignadas en su ficha (módulo *Productos*).
- **En Unidades por producto / mes falta un producto**: solo se listan los que
  vendieron más de cero unidades en el período (con *Facturas − NC*, más de lo
  que se devolvió).
- **Cambié un filtro y la tabla no cambia**: los filtros no consultan solos;
  pulse **Buscar** (el botón queda en ámbar mientras haya cambios sin aplicar).

## Historial de cambios

- **1.9** — Los filtros **ya no consultan al cambiarlos**: el reporte se genera
  solo al pulsar **Buscar** (o Enter); mientras haya filtros sin aplicar el
  botón se muestra en ámbar (igual que en el Reporte de Compras). Nueva
  agrupación **Unidades por Producto / Mes**: una fila por
  producto (código y descripción), **una columna por cada mes** del período
  elegido con las unidades vendidas, y la columna **Total** del período; solo
  los productos con ventas, con fila TOTAL al pie, gráfico de unidades por mes,
  orden por cualquier mes y PDF/Excel con las mismas columnas (horizontal con
  más de seis meses). Nuevos filtros **Marca** y **Categoría** (primera fila,
  después de *Agrupar por*, con favorito), que aplican a todas las vistas, al
  PDF y al Excel; el buscador de *Cliente* ocupa el espacio que sobra de esa
  fila. Nuevas secciones *Filtrar por marca o categoría* y *Unidades
  por producto / mes*.
- **1.8** — **PDF rediseñado**. Ahora lleva el **logo de la empresa** en el
  encabezado, una caja **"Filtros aplicados"** con todo lo que se usó para armar
  el reporte y una banda con los indicadores de las tarjetas. El listado **ocupa
  todo el ancho de la hoja** (antes quedaba angosto y con márgenes grandes), la
  columna del **nombre del producto se hizo mucho más ancha** y muestra el código
  debajo, y el **Detallado sale en hoja horizontal** para que entren sus doce
  columnas. Los textos largos se parten en varias líneas en lugar de invadir la
  columna vecina, la cabecera se repite en cada página, se numeran las páginas y
  la fila de totales aparece **una sola vez al final** (antes se repetía al pie
  de cada hoja con la cifra global, como si fuera el total de esa hoja). Sección
  *Exportar* ampliada.
- **1.7** — Los **administradores (nivel 2)** ven todas las ventas aunque no
  tengan marcado *Acceso total*, igual que el superadministrador. El permiso
  sigue decidiendo solo para los usuarios de nivel 1. El vendedor sin acceso total
  pasa a ver **solo lo de su vendedor**: las ventas a su nombre y, si no tienen
  vendedor, las de sus clientes asignados; ya no ve las ventas a sus clientes
  emitidas a nombre de otro vendedor. A estos usuarios el **filtro Vendedor** les
  aparece fijo en su nombre. Es la misma regla de Cuentas por Cobrar y del Reporte
  de Ventas por Vendedor, que ahora comparten los tres reportes. Sección *Quién ve
  qué* actualizada.
- **1.6** — El reporte respeta el permiso de **Acceso total**: sin él, el
  vendedor vinculado al usuario (campo *Usuario del sistema* de su ficha o la
  misma cédula) ve las ventas de sus **clientes asignados** y las emitidas a su
  nombre; quien no es vendedor ve solo lo que él registró. Aplica en la tabla,
  las tarjetas, el resumen de estados, todas las agrupaciones, el PDF, el Excel,
  los buscadores de Producto/Información adicional y el resumen de la app móvil.
  Antes el permiso no cambiaba nada: cualquiera con permiso de ver el reporte
  veía las ventas de toda la empresa. Nueva sección *Quién ve qué: el permiso de
  Acceso total*.
- **1.5** — En la agrupación **Por cliente**, la columna *Nro Facturas* se
  reemplazó por **Saldo x Cobrar**: lo que queda pendiente de los documentos del
  reporte, con el mismo cálculo de Cuentas por Cobrar. Aplica en pantalla, en el
  PDF (con su total al pie) y en el Excel; el número de documentos pasó al título
  del nombre del cliente. Nueva sección *Por cliente: la columna Saldo x Cobrar*.
- **1.4** — Las columnas de la tabla ahora **ordenan el reporte**: un clic ordena
  de menor a mayor y otro invierte, en el detallado y en todas las agrupaciones.
  El **PDF y el Excel respetan ese orden**, y la columna elegida se guarda como
  preferencia del usuario para la próxima vez.
- **1.3** — Nuevo selector **Borradores** (segunda fila de filtros): *Sin
  borradores* (por defecto, como antes), *Con borradores* o *Solo borradores*.
  Se aplica a la tabla, las tarjetas, el gráfico, el PDF y el Excel; el Excel
  del detallado agrega la columna Estado cuando hay borradores. La tabla de
  filtros ya no menciona un filtro "Estado", que no existía en pantalla.
- **1.2** — Nuevo filtro **Establecimientos** (solo desde la matriz del grupo
  RUC) para consolidar las ventas de todas las sucursales del mismo RUC: badge
  del establecimiento en el detallado, columna "Estab." en PDF y Excel, y línea
  "Alcance" en el encabezado. Los filtros Cliente y Producto cruzan por
  identificación y código entre establecimientos.
- **1.1** — Selector de **Vendedor** en los filtros (segunda fila, antes de Producto); los campos de esa fila se compactaron para caber en una sola línea. Las notas de crédito toman el vendedor de la factura que modifican, tanto para el filtro como para la columna Vendedor del detallado.
- **1.0** — Versión inicial.
