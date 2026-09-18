---
titulo: Productos
resumen: Catálogo de productos y servicios que se factura, se compra y se controla en inventario.
categoria: Inventario
ruta_modulo: modulos/productos
tipo: modulo
visibilidad: todos
etiquetas: productos, buscar producto, buscador, filtros, filtrar productos, productos bajo el minimo, reponer stock, buscar por variante, buscar por codigo de proveedor, kits, chips, ordenar por dos columnas, ordenar por categoria y descripcion, articulos, servicios, catalogo, precio, costo, iva, ice, stock, codigo de barras, inventariable, varios precios, lista de precios, mayorista, carga masiva, importar productos, precio editable, cambiar precio en la comanda, precio variable, envio a domicilio, delivery, servicio a domicilio, recargo por servicio, excluir propina, restaurante
version: 1.8
orden: 10
estado: activo
---

El módulo de **Productos** es el catálogo de todo lo que la empresa vende o
compra. Alimenta las facturas, proformas, compras e inventario: si algo no está
aquí, no se puede facturar ni controlar su stock.

## Producto o servicio

El primer campo del formulario decide el resto: **Tipo de producción**.

- **Producto / Bien**: algo físico. Puede llevar control de stock.
- **Servicio**: mano de obra, asesoría, mantenimiento. No se inventaría.

Al cambiarlo, el formulario muestra u oculta los campos que aplican a cada caso.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija si es producto o servicio.
3. Complete el **código principal** (el sistema propone el siguiente disponible),
   el **nombre** y la **categoría**.
4. Indique el **precio base** y, si aplica, el **costo**.
5. Elija la **tarifa de IVA** que le corresponde.
6. Guarde.

## Campos principales

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo de producción | Sí | Producto/Bien o Servicio |
| Código principal | Sí | Identificador con el que aparece en los documentos. Se propone automáticamente |
| Código auxiliar | No | Segundo código propio de la empresa |
| Código de barras | No | Para lectura con pistola en el punto de venta |
| Nombre | Sí | Descripción que sale impresa en la factura. Hasta 300 caracteres: es el máximo que el SRI admite en la descripción de cada ítem del comprobante electrónico |
| Categoría / Marca | No | Sirven para agrupar y filtrar el catálogo |
| Unidad de medida | No | Unidad en la que se vende (unidad, caja, kilo…) |
| Precio base | Sí | Precio de venta antes de impuestos |
| Costo | No | Costo de referencia del producto |
| Tarifa de IVA | Sí | La tarifa vigente que aplica al producto |
| ICE | No | Solo para productos gravados con este impuesto |
| Inventariable | No | Si se lleva control de existencias |
| Stock mínimo / máximo | No | Referencias para los avisos de reposición |
| Se compra / Se vende | No | En qué documentos aparece el producto al buscarlo |
| Cuenta de inventario | No | Cuenta contable donde se registra el producto |
| Cuenta de costo o gasto | No | Cuenta contable de su costo |
| Imagen | No | Se muestra en el punto de venta |
| Estado | Sí | Activo o inactivo |

## Inventariable

Marque **Inventariable** solo cuando quiera que el sistema lleve las existencias
del producto: cada compra suma y cada venta resta, y el movimiento queda en el
kardex.

Los servicios y los productos que no controla por unidades (por ejemplo, insumos
menores) no deben marcarse. Un producto no inventariable se puede facturar sin
problema; simplemente no genera movimiento de stock.

## Se compra / se vende

Estas dos casillas controlan dónde aparece el producto al buscarlo:

- **Se vende**: aparece en facturas, proformas y punto de venta.
- **Se compra**: aparece en compras y órdenes de compra.

Sirven para que quien factura no tenga que ver los insumos internos, y viceversa.

## Costo actual y actualización desde el Kardex

El campo **Costo** de la pestaña Inventario se puede escribir a mano o
recalcular con el botón **Actualizar desde Kardex**: toma el promedio
ponderado de todas las entradas registradas en el Kardex del producto (suma
todas las bodegas) y **guarda el valor de inmediato**, sin esperar a pulsar
Guardar. Si el producto no tiene entradas en el Kardex, el botón avisa y no
cambia nada.

Desde el listado, el botón **Actualizar Costos** (junto a PDF/Excel) hace lo
mismo para **todos los productos inventariables** de la empresa de una sola
vez, y muestra cuántos se actualizaron y cuántos no tenían movimientos. Un
usuario sin acceso total solo actualiza los productos que él mismo creó,
igual que en el listado.

## Varios precios por producto

Además del precio base, en la pestaña **Precios** de la ficha se pueden definir
otros precios con nombre (Mayorista, Distribuidor, Promoción…), cada uno con
vigencia opcional (desde / hasta) y estado. Al facturar se elige cuál aplicar.
Al guardar la ficha se guarda la lista completa de esa pestaña.

Para cargarlos en bloque hay dos caminos: la hoja **Precios** de la plantilla
de Productos en *Configuración → Importador desde Excel* (guía *Importar datos
desde Excel*), o el módulo *Carga de Productos por Excel* cuando además se
quieren cargar variantes, componentes o stock por bodega. En ambos, si un
producto aparece en la hoja de precios, esa es su lista completa; si no
aparece, conserva la que tenía.

## Marcas para el restaurante

Al pie de la pestaña donde se carga la imagen hay dos marcas que solo tienen
efecto en el salón y en el punto de venta:

- **No aplicar el recargo por servicio (propina) a este producto**: el producto
  se cobra normal, pero queda fuera de la base sobre la que se calcula el 10%
  de servicio. Pensado para envases, empaques y similares.
- **Permitir cambiar el precio de este producto en la comanda**: habilita un
  botón junto a esa línea —tanto en la **comanda** del salón como en el
  **carrito del punto de venta**— para fijarle un precio distinto. Es para los
  servicios cuyo valor se pacta en cada venta —el **envío a domicilio**, que
  depende de la distancia—, no para negociar el precio de los platos.

La segunda marca **no cambia el precio del producto ni el de la carta**: afecta
solo a esa línea de esa venta, así que la siguiente vuelve a nacer con el precio
de lista. Los ítems del menú la heredan del producto que tienen vinculado; un
ítem de la carta sin producto vinculado no permite editar el precio. Ver
*Comandas* y *Punto de venta (POS)* para cómo se usa.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF, Excel y Actualizar Costos.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del listado: código,
código auxiliar, código de barras, descripción, categoría, marca, unidad de
medida, ubicación, precio base, valor del IVA, ICE, PVP final y stock mínimo y
máximo. Además busca en el nombre del ICE, el usuario que registró el producto,
sus **variantes** (por ejemplo *talla XL* o *color rojo*) y los **códigos con que
lo factura cada proveedor**. Las columnas **Tipo**, **Tipo IVA**, **Inv.** y
**Estado** no entran en la búsqueda libre, y tampoco el **Saldo**: para filtrar
por ellas use la ventana de filtros. Puede escribir varias palabras en cualquier
orden y no importan mayúsculas ni tildes. Para limpiar, borre el texto o pulse
Escape en el cuadro. Mientras busca, aparece un **círculo girando** al final del
cuadro y la tabla se ve atenuada.

Los buscadores de producto de las facturas, el punto de venta, las compras y
demás documentos no cambian: siguen buscando por descripción y códigos.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Producto:**

| Bloque | Filtros |
|--------|---------|
| Producto | Código, código auxiliar, código de barras, descripción |
| Clasificación | Tipo (bien / servicio), categoría, marca, estado (activo / inactivo) |
| Inventario | Inventariable, unidad de medida, ubicación, saldo en todas las bodegas, stock mínimo y máximo (cada uno con mínimo y máximo), saldo bajo el stock mínimo, kit con o sin componentes |
| Precios e impuestos | Precio base y PVP final (con mínimo y máximo), tipo de IVA, con o sin ICE, se vende, se compra, con o sin precios adicionales |
| Registro | Fecha de registro (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), usuario que registró |

Los selectores de categoría, marca, unidad de medida, tipo de IVA y usuario
listan solo lo que la empresa ya usa en sus productos. *Saldo bajo el stock
mínimo* trae los productos inventariables que tienen un stock mínimo definido y
cuyo saldo actual (suma de todas las bodegas) está por debajo: es la lista de lo
que hay que reponer. En *Estado*, **Inactivo** incluye cualquier producto que no
esté activo, igual que en la columna del listado.

**Pestaña Detalles** (lo que hay dentro del producto). Es un único cuadro,
**Buscar libremente dentro de los productos**: escriba una variante, el código o
nombre de un componente de un kit, el nombre de una lista de precios o el código
o nombre de un proveedor, y aparece la lista de **cada detalle que coincide**
con el producto al que pertenece (código, descripción y estado). Un clic en la
fila deja el listado mostrando ese producto; el ícono de la derecha abre
directamente su ficha. Por ejemplo, *mayorista* lista todos los productos que
tienen ese precio adicional.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. Quien ya conoce la sintaxis `clave:valor` puede
seguir escribiéndola (`categoria:Bebidas`, `stock:<=0`); los enlaces guardados
siguen funcionando.

El listado se puede ordenar por cualquier columna, ocultar columnas y **exportar
a PDF y Excel**. Cada usuario conserva su configuración de columnas. El Excel
respeta la búsqueda y los filtros activos e incluye, además de los datos
básicos, **IVA, ICE, PVP, Costo, Margen y Utilidad %** de cada producto (Margen
= Precio Base − Costo; Utilidad % = Margen sobre el Costo).

## Ordenar el listado

Pulse el título de una columna para ordenar por ella y vuelva a pulsarlo para
invertir el sentido. De fábrica el listado sale por descripción.

Puede **encadenar hasta tres columnas**: mantenga presionada la tecla **Shift**
(⇧) y pulse el título de la segunda.

| Para ver… | Ordene así |
|-----------|-----------|
| El catálogo agrupado por categoría, y dentro por descripción | *Categoría*, luego Shift+clic en *Descripción* |
| Lo que menos queda en bodega dentro de cada categoría | *Categoría*, luego Shift+clic en *Saldo* |
| Los productos más caros de cada marca | *Marca*, luego Shift+clic en *PVP* |

El número pequeño junto a cada flecha indica qué columna manda (`1`) y cuál
desempata (`2`). Un tercer Shift+clic sobre la misma columna la saca del orden, y
un clic normal en cualquier encabezado vuelve a dejar una sola.

Las columnas calculadas se ordenan por su valor real, no por el texto: el
**Saldo** por las existencias, y el **PVP** y el **IVA** por el importe.

El orden se guarda para usted y las exportaciones salen con ese mismo orden.
Detalles en *Cómo ordenar los listados*.

## Permisos

Con **acceso total** se ven los productos de toda la empresa. Sin él, cada
usuario ve solo los que él mismo creó — lo que en un catálogo compartido suele
ser indeseable, así que revise este permiso si alguien reporta que "faltan
productos".

## Eliminar

La eliminación es **lógica**: el producto desaparece del listado pero los
documentos que ya lo usan siguen intactos. Si solo quiere dejar de usarlo,
prefiera cambiar su **estado a inactivo**: así conserva el historial y deja de
aparecer al facturar.

## Errores frecuentes

- **No aparece al facturar**: no tiene marcado *Se vende*, está inactivo, o
  pertenece a otra empresa.
- **Sale con IVA equivocado**: revise la tarifa de IVA del producto. Un producto
  con tarifa mal configurada arrastra el error a todas las facturas nuevas.
- **Un servicio descuadra el inventario**: no debería estar marcado como
  inventariable. Desmárquelo y revise su kardex.
- **El stock no cuadra**: compruebe que el producto es inventariable y que las
  compras que lo afectan quedaron vinculadas a este producto del catálogo.

## Historial de cambios

- **1.8** — El **nombre del producto admite hasta 300 caracteres** (antes 200), el
  mismo máximo que el SRI acepta en la descripción de cada ítem de la factura. Aplica
  al formulario de Productos, a la creación rápida desde los documentos (facturas,
  compras, proformas…) y a las cargas por Excel.

- **1.7** — **Las búsquedas de productos dejan de tardar.** El buscador de los
  documentos (factura, punto de venta, compras, comandas) responde en milésimas de
  segundo aunque el catálogo tenga decenas de miles de productos, y **la búsqueda del
  listado**, que con catálogos grandes podía tardar más de medio minuto, ahora contesta
  en menos de medio segundo. Encuentran exactamente lo mismo que antes. Además, si se
  sigue escribiendo, la búsqueda anterior se descarta sola, y buscar ya no deja en
  espera a las demás pantallas del mismo usuario.

- **1.6** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas del listado (incluidos valor del
  IVA, ICE y PVP), las variantes y los códigos de proveedor, salvo Tipo, Tipo
  IVA, Inv., Estado y Saldo. Los filtros pasan a una **ventana propia** (botón
  del embudo, se aplican con *Aplicar*) con dos pestañas: **Producto** (criterios
  nuevos: categoría, marca, unidad de medida y tipo de IVA como lista, saldo bajo
  el stock mínimo, kit, PVP final, con/sin ICE, se vende, se compra, precios
  adicionales, fecha de registro y usuario) y **Detalles**, un cuadro de
  **búsqueda libre dentro de los productos** (variantes, componentes, precios
  adicionales y códigos de proveedor). *Estado = Inactivo* ahora incluye a todos
  los productos que no están activos, y *Inventariable = No* a los que no tienen
  el dato, igual que en las columnas. Los filtros activos se ven como etiquetas
  dentro del cuadro y la tabla se atenúa mientras busca.
- **1.5** — El listado se puede **ordenar por hasta tres columnas a la vez**:
  Shift+clic en el título de la segunda columna la encadena a la primera (por
  ejemplo *Categoría* y, dentro de cada una, la *Descripción*). Cada encabezado
  activo muestra un número con su prioridad, y el orden se respeta al exportar.
  Nueva sección *Ordenar el listado*.

- **1.4** — Nueva marca **"Permitir cambiar el precio de este producto en la
  comanda"**, que también habilita el botón de precio en el carrito del punto de
  venta, y se documenta la de *No aplicar el recargo por servicio*, que ya
  existía sin explicación en el manual. Ver *Marcas para el restaurante*.

- **1.3** — Sección *Varios precios por producto* y carga masiva de precios
  desde la hoja *Precios* del Importador desde Excel.
- **1.2** — Corregidos los filtros `tipo:`, `estado:`/`status:` y `stock:` del
  buscador: `tipo:bien`/`tipo:servicio` y `estado:activo`/`estado:inactivo` no
  encontraban nada (o rompían la búsqueda) porque comparaban la etiqueta
  amigable directamente contra el código guardado en la base; `stock:` apuntaba
  a una columna que no existe y producía un error. Los tres ya funcionan.
- **1.1** — Botón para recalcular el costo desde el Kardex (individual y masivo
  para todo el catálogo). El Excel del listado ahora incluye IVA, ICE, PVP,
  Costo, Margen y Utilidad %, respetando los filtros de búsqueda activos.
- **1.0** — Versión inicial.
