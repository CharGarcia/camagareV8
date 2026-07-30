---
titulo: Unidades de medida
resumen: Unidades en las que se vende cada producto, agrupadas por tipo de medida. Cada empresa nace con un catálogo listo.
categoria: Inventario
ruta_modulo: modulos/unidades-medida
tipo: modulo
visibilidad: todos
etiquetas: unidades, unidad de medida, medidas, kilo, litro, caja, unidad, peso, volumen, longitud, area, tiempo, empaque, quintal, arroba, libra, galon, caneca, factor, conversion, unidad base, importar medidas
version: 1.2
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
| CANTIDAD | unidad, par, docena, ciento, millar | unidad |
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

El tipo **TIEMPO** sirve para cobrar mano de obra y servicios por duración
(taller, car-wash, alquileres).

Nada de esto es obligatorio: lo que no use, desactívelo o elimínelo.

> Las unidades de **EMPAQUE** son presentaciones comerciales, no magnitudes: una
> caja no equivale a un saco. Van todas con factor 1 y el sistema no las
> convierte entre sí. Sirven para indicar cómo se entrega el producto.

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
- **Inventario, compras y ventas**: la conversión por factor permite comprar en
  una unidad y vender en otra dentro del mismo tipo.

## Errores frecuentes

- **"Debe seleccionar un tipo de medida"**: está creando una unidad sin indicar a
  qué magnitud pertenece.
- **"Ya existe una unidad base para este tipo de medida"**: el tipo ya tiene su
  referencia. Deje la nueva unidad sin marcar como base e indique su factor.
- **No aparece la unidad al configurar un producto**: compruebe el tipo de medida
  elegido en el producto; solo se muestran las unidades de ese tipo.
- **Los precios convertidos salen mal**: revise el factor. Debe indicar cuántas
  unidades base equivale una de esa unidad, no al revés.

## Historial de cambios

- **1.2** — Se agregó la **caneca** (18.9271 litros) al catálogo por defecto
  de volumen.
- **1.1** — Catálogo por defecto en cada empresa (7 tipos, 39 unidades),
  explicación de la unidad base y el factor, y carga masiva desde Excel con la
  entidad unificada *Unidades y tipos de medida*.
- **1.0** — Versión inicial.
