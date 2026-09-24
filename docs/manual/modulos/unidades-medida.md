---
titulo: Unidades de medida
resumen: Unidades en las que se vende cada producto, agrupadas por tipo de medida. Cada empresa nace con un catálogo listo.
categoria: Inventario
ruta_modulo: modulos/unidades-medida
tipo: modulo
visibilidad: todos
etiquetas: unidades, unidad de medida, medidas, kilo, litro, caja, unidad, peso, volumen, longitud, area, tiempo, empaque, quintal, arroba, libra, galon, caneca, factor, conversion, unidad base, importar medidas, vender por caja, caja x100, presentacion, comprar por unidad, contenido de la caja
version: 1.3
orden: 60
estado: activo
---

Las **unidades de medida** definen en qué se vende cada producto: unidades,
cajas, kilos, litros, metros. Aparecen en la factura junto a la cantidad.

## Dos niveles: tipo y unidad

El catálogo tiene dos niveles:

- **Tipo de medida**: la magnitud. Peso, volumen, longitud, cantidad.
- **Unidad**: la medida concreta dentro de ese tipo. Dentro de peso: gramo, kilo,
  quintal.

Al crear una unidad hay que elegir a qué tipo pertenece. Esta separación permite
que, al configurar un producto, primero se elija la magnitud y después solo se
ofrezcan las unidades que tienen sentido para ella.

## La unidad base y el factor

Dentro de cada tipo, una unidad es la **base**: la referencia con la que se
comparan las demás. El kilogramo es la base del peso, el litro la del volumen, el
metro la de la longitud.

El **factor** de cada unidad dice *cuántas unidades base equivale una de ella*.
La libra lleva 0.453592 porque 1 lb = 0.453592 kg. La base siempre tiene factor
1, y **solo puede haber una base por tipo de medida**.

Con eso el sistema convierte precios entre unidades: un producto cargado a
$10 el kilo se puede vender por libras y el precio sale solo.

## Catálogo que ya viene cargado

Cada empresa nueva recibe este catálogo automáticamente; no hay que crear nada
para empezar. Las empresas que ya existían lo reciben la próxima vez que se
guarde su ficha en Configuración → Empresas.

| Tipo | Unidades | Base |
|------|----------|------|
| UNIDAD | unidad, par, docena, ciento, millar | unidad |
| PESO | miligramo, gramo, onza, libra, kilogramo, arroba, quintal, tonelada | kilogramo |
| VOLUMEN | mililitro, litro, galón, caneca, metro cúbico | litro |
| LONGITUD | milímetro, centímetro, pulgada, pie, yarda, metro, kilómetro | metro |
| ÁREA | centímetro cuadrado, metro cuadrado, pie cuadrado, hectárea | metro cuadrado |
| TIEMPO | minuto, hora, día, semana, mes | hora |
| EMPAQUE | caja, paquete, funda, saco, rollo, juego | caja |

La **arroba** son 25 libras (11.3398 kg) y el **quintal** 100 libras
(45.3592 kg), que es el uso comercial ecuatoriano. La **caneca** son 18.9271
litros (equivalente a 5 galones), medida usual para combustibles, aceites y
químicos.

En empresas más antiguas el tipo **UNIDAD** puede aparecer con el nombre
**CANTIDAD**: es el mismo tipo y funciona igual.

El tipo **TIEMPO** sirve para cobrar mano de obra y servicios por duración
(taller, car-wash, alquileres).

Nada de esto es obligatorio: lo que no use, desactívelo o elimínelo.

> Las unidades de **EMPAQUE** son presentaciones comerciales, no magnitudes: una
> caja no equivale a un saco. Van todas con factor 1 y el sistema no las
> convierte entre sí. Sirven para indicar cómo se entrega el producto. Para
> vender por cajas con un contenido fijo, vea la sección siguiente.

## Comprar y vender por cajas

Caso típico: el proveedor factura por cajas de un contenido fijo (p. ej. guantes
en cajas de 100), el stock se cuenta por unidades y se vende tanto por caja como
suelto.

**1. Configuración (una sola vez)**

- En el **producto**: tipo de medida **UNIDAD** y unidad **UNIDAD**. El stock
  siempre se lleva en unidades sueltas.
- En este módulo, dentro del tipo **UNIDAD**, cree la unidad **CAJA X100** con
  **factor 100** (sin marcar como base). Si otro producto viene en cajas de 50,
  cree **CAJA X50** con factor 50: el factor es el contenido de la caja.

No use la **caja** del tipo EMPAQUE para esto: está en otro tipo de medida, así
que no aparece para un producto por UNIDAD y no convierte.

**2. Compra**

Al pasar la compra al inventario, elija la medida **CAJA X100** en la fila y
deje la cantidad y el costo como vienen en la factura (10 cajas a $15,00). El
sistema los convierte solo: entran **1.000 unidades a $0,15** y el total sigue
siendo $150,00. Bajo la cantidad se ve lo que realmente entrará. Detalle en
[Compras](modulos/compras).

**3. Venta**

En la factura, recibo o POS elija la unidad **CAJA X100** en la línea: el precio
se multiplica por 100 y, al guardar, el inventario descuenta **100 unidades** por
cada caja. También se puede vender suelto (unidad UNIDAD) desde el mismo stock.
Detalle en [Factura de venta](modulos/factura-venta).

## Cómo se registra

1. Cree primero el **tipo de medida** si aún no existe (nombre de hasta 100
   caracteres; el código admite hasta 50).
2. Cree la **unidad** dentro de ese tipo, con su abreviatura y su factor.

El **código de la unidad no puede repetirse en toda la empresa**, ni siquiera
entre tipos distintos: al importar productos desde Excel la unidad se busca solo
por ese código, así que dos unidades con el mismo código harían imposible saber
cuál corresponde.

## Cargar muchas de una vez

En **Configuración → Importador desde Excel**, la entidad *Unidades y tipos de
medida* descarga una plantilla con las dos hojas en un mismo archivo y con las
instrucciones adentro. Sirve tanto para cargar un catálogo nuevo como para
corregir el que ya existe, porque los códigos repetidos se actualizan en lugar de
duplicarse. Los detalles están en la guía [Importar datos desde Excel](guias/importar-desde-excel).

## Permisos

Se administran como cualquier módulo, en Configuración → Permisos por módulo. Con
**acceso total** se ven las unidades de toda la empresa; sin él, solo las que
creó el propio usuario.

## Reglas de negocio

- Un tipo de medida **no se puede eliminar si tiene unidades asociadas**.
- Una unidad **no se puede eliminar si algún producto o componente la usa**.
- La unidad marcada como base guarda siempre factor 1, aunque se escriba otro.
- Solo una unidad base por tipo de medida.
- Los servicios no llevan unidad de medida: el campo solo aplica a productos.

## Integraciones con otros módulos

- **Productos**: cada producto elige un tipo de medida y una unidad.
- **Facturación**: la unidad se muestra junto a la cantidad cuando el
  establecimiento tiene activada la opción *mostrar unidad de medida*.
- **Ventas** (factura, recibo, POS y nota de crédito): una línea vendida en otra
  unidad del mismo tipo convierte el precio y descuenta el stock en la unidad del
  producto (cantidad × factor de la línea ÷ factor del producto).
- **Compras**: al pasar la compra al inventario con una medida distinta a la del
  producto, la cantidad se multiplica y el costo se reparte por el mismo factor
  (el total no cambia).

## Errores frecuentes

- **"Debe seleccionar un tipo de medida"**: está creando una unidad sin indicar a
  qué magnitud pertenece.
- **"Ya existe una unidad base para este tipo de medida"**: el tipo ya tiene su
  referencia. Deje la nueva unidad sin marcar como base e indique su factor.
- **No aparece la unidad al configurar un producto**: compruebe el tipo de medida
  elegido en el producto; solo se muestran las unidades de ese tipo.
- **Los precios convertidos salen mal**: revise el factor. Debe indicar cuántas
  unidades base equivale una de esa unidad, no al revés.
- **Vendí una caja y el stock bajó 1 en lugar de 100**: la unidad de la caja no es
  del mismo tipo que la del producto (p. ej. se usó la *caja* de EMPAQUE) o tiene
  factor 1. Use una unidad del tipo UNIDAD con el contenido de la caja como factor.
- **Cambiar el factor de una unidad ya usada** afecta a las ventas que se editen
  después: se vuelven a descontar con el factor nuevo. Si el contenido de la caja
  cambia, cree una unidad nueva (CAJA X120) en lugar de modificar la existente.

## Historial de cambios

- **1.3** — Las ventas en otra unidad (caja, docena, ciento) descuentan el stock
  convertido por el factor, y la entrada de una compra en otra unidad también se
  convierte (cantidad × factor, costo ÷ factor). Nueva sección *Comprar y vender
  por cajas*. El tipo base del catálogo figura como **UNIDAD** (antes CANTIDAD).
- **1.2** — Se agregó la **caneca** (18.9271 litros) al catálogo por defecto
  de volumen.
- **1.1** — Catálogo por defecto en cada empresa (7 tipos, 39 unidades),
  explicación de la unidad base y el factor, y carga masiva desde Excel con la
  entidad unificada *Unidades y tipos de medida*.
- **1.0** — Versión inicial.
