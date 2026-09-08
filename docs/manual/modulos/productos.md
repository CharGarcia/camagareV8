---
titulo: Productos
resumen: Catálogo de productos y servicios que se factura, se compra y se controla en inventario.
categoria: Inventario
ruta_modulo: modulos/productos
tipo: modulo
visibilidad: todos
etiquetas: productos, articulos, servicios, catalogo, precio, costo, iva, ice, stock, codigo de barras, inventariable, varios precios, lista de precios, mayorista, carga masiva, importar productos, precio editable, cambiar precio en la comanda, precio variable, envio a domicilio, delivery, servicio a domicilio, recargo por servicio, excluir propina, restaurante
version: 1.4
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
| Nombre | Sí | Descripción que sale impresa en la factura |
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
  botón junto a esa línea en la comanda para fijarle un precio distinto. Es para
  los servicios cuyo valor se pacta en cada venta —el **envío a domicilio**, que
  depende de la distancia—, no para negociar el precio de los platos.

La segunda marca **no cambia el precio del producto ni el de la carta**: afecta
solo a la línea de esa comanda. Los ítems del menú la heredan del producto que
tienen vinculado; un ítem de la carta sin producto vinculado no permite editar
el precio. Ver *Comandas* para cómo se usa en el salón.

## Buscar en el listado

Además del texto libre, el buscador acepta filtros `clave:valor`
(`categoria:Bebidas`, `codigo:0012`), rangos numéricos y negaciones con `-`.

El listado se puede ordenar por cualquier columna, ocultar columnas y **exportar
a PDF y Excel**. Cada usuario conserva su configuración de columnas. El Excel
respeta los filtros de búsqueda activos e incluye, además de los datos básicos,
**IVA, ICE, PVP, Costo, Margen y Utilidad %** de cada producto (Margen = Precio
Base − Costo; Utilidad % = Margen sobre el Costo).

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

- **1.4** — Nueva marca **"Permitir cambiar el precio de este producto en la
  comanda"** y se documenta la de *No aplicar el recargo por servicio*, que ya
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
