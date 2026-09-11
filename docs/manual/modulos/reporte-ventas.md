---
titulo: Reporte de ventas
resumen: Ventas del periodo con filtros por cliente, vendedor, producto y borradores, agrupables, ordenables y exportables.
categoria: Reportes
ruta_modulo: modulos/reporte_ventas
tipo: modulo
visibilidad: todos
etiquetas: reporte de ventas, ventas, cuanto vendi, por cliente, por vendedor, por producto, estadisticas, exportar, pdf, excel, establecimientos, sucursales, matriz, mismo ruc, consolidado por ruc, borradores, borrador, facturas en borrador, incluir borradores, documentos sin autorizar, pendientes de enviar al sri, ordenar, ordenamiento, ordenar por columna, de mayor a menor, quien compro mas
version: 1.4
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
| Borradores | *Sin borradores* (por defecto), *Con borradores* o *Solo borradores*. Ver la sección *Documentos en borrador* |

Los filtros se combinan: *las ventas del producto X al cliente Y en marzo*.

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
  variante* por cantidad vendida, y *Por fecha* / *Por mes* por el periodo.
- El orden elegido **se recuerda** para la próxima vez que abra el reporte.

## Exportar

El reporte se exporta a **PDF** y **Excel**, con las mismas filas, los mismos
filtros y el mismo orden que se ve en pantalla. El Excel es el que conviene
cuando se va a seguir analizando por fuera.

## Errores frecuentes

- **Las cifras no coinciden con la contabilidad**: revise el selector
  **Borradores**; con borradores las cifras incluyen documentos que todavía no
  son ventas. Las anuladas nunca cuentan.
- **Falta una venta**: compruebe su fecha de emisión y que no esté anulada. Si
  está en borrador, elija *Con borradores*.
- **No veo las ventas de otros vendedores**: sin el permiso de *acceso total*
  cada usuario ve solo lo que registró.
- **El Excel salió en otro orden**: se exporta con el orden que estaba marcado en
  la pantalla; si cambió la agrupación después de ordenar, revise la flecha de la
  cabecera antes de descargar.

## Historial de cambios

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
