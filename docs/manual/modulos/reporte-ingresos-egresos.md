---
titulo: Reporte de ingresos y egresos
resumen: Todo el movimiento de dinero del periodo en un solo listado, filtrable y exportable.
categoria: Reportes
ruta_modulo: modulos/reporte_ingresos_egresos
tipo: modulo
visibilidad: todos
etiquetas: reporte de ingresos y egresos, anulados, ingresos anulados, egresos anulados, vigentes, movimiento de dinero, cobros y pagos, por tercero, forma de pago, concepto, vendedor, asesor, cobros por vendedor, comisiones, asesor del cliente, otros conceptos, saldo inicial, saldos iniciales, exportar, excel detallado, pdf, imprimir, logo en el pdf, totales en el pdf, reporte de ingresos por vendedor, reporte de egresos, pdf por vendedor, anticipo, anticipos, anticipo aplicado, cruce de anticipo, ingresos repetidos, ingreso duplicado, doble conteo, saldo de anticipos, anticipos por cliente, anticipos de proveedores, anticipos recibidos, anticipos entregados, entrada de dinero, salida de dinero, flujo de caja, monetario, no monetario, solo monetarios, cruces, compensaciones, favoritos, filtro favorito, detalle del ingreso, ver comprobante, clic en la fila, agrupado, por comprobante, una fila por ingreso, sin detallar facturas, como en el ingreso
version: 1.10
orden: 30
estado: activo
---

Este reporte junta **ingresos y egresos en un mismo listado**, al nivel de cada
línea de detalle. Es la vista completa del movimiento de dinero: qué entró, qué
salió, de quién y por qué concepto.

Al entrar, el reporte **no carga datos**: elija los filtros y presione **Buscar**
(o Enter en el cuadro de búsqueda). **Limpiar** devuelve los filtros a los valores
iniciales y deja la tabla en blanco hasta la siguiente búsqueda.

## Filtros combinables

| Filtro | Para qué |
|--------|----------|
| Fechas | El periodo |
| Tercero | Un cliente, proveedor o empleado concreto |
| Vendedor | Los cobros de las facturas y recibos de venta de un vendedor, y los cobros de saldos iniciales e ingresos por otros conceptos de sus clientes |
| Forma de pago | Efectivo, banco, tarjeta… |
| Operación bancaria | Transferencia, cheque, depósito |
| Concepto | El motivo del ingreso o egreso |
| Estado | *Vigentes* (por defecto) o *Anulados* |
| Movimientos | *Todos* (por defecto), *Solo monetarios* (sin lo cobrado o pagado con formas de tipo Anticipo u Otros) o *Solo no monetarios* (solo eso) |
| Tipo de documento | Qué documento originó el movimiento |
| Monto | Rango de valores |
| Texto libre | Búsqueda sobre la descripción |

**Favoritos**: la estrella junto a cada selector (Mostrar, Ver por, Estado,
Movimientos, Tipo de tercero, Vendedor, Forma de cobro/pago, Operación bancaria,
Concepto, Tipo de documento) guarda ese valor como su favorito: al entrar al
reporte, y al presionar **Limpiar**, el filtro toma su valor favorito. Cada usuario
tiene los suyos. Con un favorito puesto, el reporte igual no carga datos hasta
presionar **Buscar**.

Todos se combinan entre sí. La consulta típica —*todo lo que le pagué a este
proveedor por transferencia este trimestre*— sale con tres filtros.

## Ingresos y egresos anulados

El reporte muestra por defecto solo los comprobantes **vigentes**: un ingreso o
egreso anulado **no aparece ni suma** en los totales, las vistas resumidas, el
Excel ni el PDF. Así los valores cuadran con los módulos de Ingresos y Egresos.

Para revisar los anulados, elija **Anulados** en el filtro *Estado*: el reporte
muestra entonces solo esos. Nunca se mezclan vigentes y anulados en un mismo
resultado.

## Cobrado, no monetario y entrada de dinero

No todo lo que cancela un ingreso o egreso es dinero que entra o sale de caja o
bancos. El reporte considera **no monetarias** las formas de cobro/pago de tipo:

- **Anticipo**: un anticipo pasa por dos comprobantes. Cuando el cliente lo
  entrega (o la empresa se lo entrega al proveedor) se registra un ingreso (o
  egreso) con un concepto de anticipo: ahí **sí entra (o sale) dinero**. Cuando
  después se aplica a una factura (o compra), otro ingreso (o egreso) la cancela con
  la forma de tipo Anticipo: ese **no mueve dinero**, el dinero ya entró antes. Si
  el reporte sumara los dos, el mismo dinero saldría dos veces.
- **Otros**: cruces, compensaciones y demás formas que no pasan por caja ni bancos.

Las formas de tipo Efectivo, Bancaria, Tarjeta, Payphone y Nuvei son monetarias.
El tipo de cada forma se ve y se cambia en *Formas de cobro y pago*, que indica junto al tipo si mueve dinero (ver el artículo *Formas de cobro y pago*).

Por eso el reporte muestra los valores por separado, arriba en los indicadores:

| Indicador | Qué suma |
|-----------|----------|
| **Ingresos cobrados / Egresos pagados** | Todo, como en los módulos de Ingresos y Egresos, incluidas las formas no monetarias |
| **No monetario** | La parte cancelada con formas de tipo Anticipo u Otros |
| **Entrada / salida de dinero** | Cobrado (o pagado) menos lo no monetario |
| **Neto (dinero)** | Entrada de dinero menos salida de dinero |

Con *Ingresos y Egresos* juntos, *No monetario* y *Entrada / salida de dinero*
muestran dos valores: ingresos / egresos.

En cada forma de ver:

- **Documento**: cada línea con su **monto completo**; debajo, las marcas
  **Anticipo aplicado $…** y **Otros (no monetario) $…** cuando corresponde. Si el
  comprobante se pagó una parte con esas formas y otra en efectivo o banco, la
  marca muestra solo esa parte; con varias líneas, se reparte entre ellas en
  proporción a su valor.
- **Tercero**: columnas *Total*, *No monetario* y *Entrada/salida de dinero*.
- **Fecha** y **Mes**: ingresos y egresos con su columna *No monetario*; el
  **Neto** es de dinero.
- **Forma de cobro/pago**: cada forma con su total real; las de tipo Anticipo u
  Otros van marcadas *no es entrada/salida de dinero*.
- **Excel**: las hojas *Ingresos y Egresos* (columnas *No monetario* y
  *Entrada/salida de dinero*), *Detalle completo* y *Cobros y pagos* (columna
  *No monetario*); arriba, los totales de cobrado, no monetario (con el desglose
  anticipo / otros) y dinero.

Así, al filtrar por la forma de cobro **Anticipo**, el reporte muestra lo que se
canceló con anticipos (*Ingresos cobrados*), y *Entrada de dinero* queda en cero o
en la parte pagada con otras formas.

### Filtro Movimientos: monetarios y no monetarios

El filtro **Movimientos** separa el reporte según la forma de cobro o pago:

| Opción | Qué muestra |
|--------|-------------|
| **Todos** (por defecto) | Todo, con las marcas de lo no monetario. Cuadra con los módulos de Ingresos y Egresos |
| **Solo monetarios** | Únicamente el dinero que entró o salió. Úselo para cuadrar con caja y bancos |
| **Solo no monetarios** | Únicamente lo cobrado o pagado con formas de tipo Anticipo u Otros |

Con *Solo monetarios* o *Solo no monetarios*:

- Cada línea muestra solo su parte de ese tipo (si un comprobante se pagó una parte
  en efectivo y otra con anticipo, cada opción muestra la suya). Las líneas que no
  tienen nada de ese tipo **no aparecen**.
- La vista *Forma de cobro/pago* y la hoja *Cobros y pagos* traen solo las formas
  de ese tipo.
- Los totales, el Excel y el PDF salen con el mismo criterio, y el filtro queda
  anotado en *Filtros aplicados*.
- *Solo monetarios* + *Solo no monetarios* = *Todos*.
- No afecta a la vista *Anticipos (saldos)*, que justamente muestra los anticipos.

## Anticipos: saldos por cliente y proveedor

En **Ver por → Anticipos (saldos)** el reporte muestra, por cliente (anticipos
recibidos) y por proveedor (anticipos entregados):

| Columna | Qué es |
|---------|--------|
| **Saldo anterior** | Saldos iniciales de anticipos y todo lo recibido menos lo aplicado antes de la fecha *Desde* |
| **Recibido / entregado** | Ingresos (o egresos) del periodo con un concepto de anticipo |
| **Aplicado** | Lo cobrado (o pagado) en el periodo con la forma de tipo Anticipo |
| **Saldo final** | Saldo anterior + recibido − aplicado: lo que aún queda a favor |

- **Con un cliente o proveedor elegido** en el filtro *Tercero*, la vista muestra
  **sus movimientos**: una fila de saldo anterior y cada anticipo recibido o
  aplicado (con la factura que canceló), con el saldo después de cada uno.
- Esta vista usa solo las **fechas**, **Mostrar** (ingresos, egresos o ambos), el
  **cliente/proveedor** y la **búsqueda** por nombre o identificación. Siempre son
  comprobantes vigentes. Los demás filtros no la afectan.
- Usa las mismas fuentes que la pestaña **Anticipos** de la ficha del cliente o
  proveedor y que el saldo de la forma de cobro tipo Anticipo, así que los saldos
  cuadran entre esas pantallas (salvo que la empresa esté en ambiente de pruebas:
  el reporte, como todos, cuenta solo documentos de producción).
- El PDF y el Excel salen con la misma tabla (hoja *Anticipos* en el Excel).

## Filtro por vendedor: cuánto cobró cada vendedor

El vendedor de un cobro es el **vendedor de la factura o del recibo de venta que
se cobró**, no el vendedor asignado al cliente. Es el mismo criterio de Cuentas
por Cobrar y del Reporte de Ventas por Vendedor, así que los valores cuadran entre
esas pantallas.

La excepción son los **ingresos por otros conceptos** (líneas de tipo *Otro*, que
no cobran ningún documento: anticipos, otros ingresos): como no hay factura de la
cual tomar el vendedor, se usa el **vendedor asignado al cliente** del ingreso. Si
el ingreso no tiene cliente, o el cliente no tiene vendedor, la línea queda sin
asesor.

Lo mismo pasa con los **cobros de saldos iniciales** (líneas *Saldo inicial*): el
saldo inicial no guarda vendedor, así que se usa el **vendedor asignado al cliente
del saldo inicial** (o, si el saldo no quedó enlazado a un cliente, el del cliente
del ingreso).

Ese vendedor se ve en la columna **Asesor** de la vista Documento, en el PDF y en
las hojas del Excel.

- Al elegir un vendedor, el reporte muestra **solo ingresos**: los egresos no
  tienen vendedor. Si además elige *Solo egresos*, el resultado queda vacío.
- Un ingreso que cobra facturas de varios vendedores se reparte: cada línea
  aparece con el vendedor de su propia factura.
- **No entran** los cobros que no cancelan una factura o un recibo con vendedor:
  facturas de reembolso y cobros de facturas sin vendedor asignado. Tampoco los
  cobros migrados del sistema anterior cuya factura no se migró. Los cobros de
  saldos iniciales y los ingresos por otros conceptos sí entran cuando el cliente
  tiene ese vendedor asignado.
- En la vista **Forma de cobro/pago** el valor es el del cobro completo: si un
  mismo ingreso cobra facturas de dos vendedores, su forma de pago aparece entera
  para cada uno.

## Formas de ver

- **Documento (agrupado)** — *la vista por defecto*: una fila por cada ingreso o
  egreso, **tal como se registró**, en lugar de una fila por cada factura cobrada
  o pagada. La columna
  *Documentos* junta las facturas, recibos o compras que cancela (con *N doc.* si
  son varias; al pasar el mouse se ve el valor de cada una) y el *Valor* es el del
  comprobante. Si un filtro (tipo de documento, monto, búsqueda) deja fuera
  alguna línea, el valor es la suma de las líneas que sí cumplen, igual que en
  los totales.
- **Documento (detalle)**: cada línea de cada comprobante (una por factura
  cobrada o pagada), con su asesor. Es la vista de auditoría.
- **Tercero (resumen)**: agrupado por cliente, proveedor o empleado, para saber
  cuánto se movió con cada uno.
- **Forma de cobro/pago**: cuánto entró y salió por cada forma (efectivo, cada
  banco, tarjeta…).
- **Fecha** y **Mes**: ingresos, egresos y neto por día o por mes.
- **Anticipos (saldos)**: saldo anterior, recibido/entregado, aplicado y saldo
  final de anticipos por cliente y proveedor (ver *Anticipos: saldos por cliente
  y proveedor*).

## Ver el detalle de un ingreso o egreso

En las vistas **Documento (agrupado)** y **Documento (detalle)**, y en los movimientos de la vista **Anticipos** con un
cliente o proveedor elegido, **haga clic en cualquier fila**: se abre a la derecha
un panel con el ingreso o egreso completo:

- **Encabezado**: número, fecha, cliente / proveedor / empleado, concepto, estado
  y observaciones.
- **Documentos cobrados o pagados**: cada línea con su valor y, si se pagó con
  formas que no mueven dinero, la parte de *Anticipo* u *Otros*.
- **Formas de cobro / pago**: cada forma con su valor, operación bancaria, número
  de cheque y referencia; las que no mueven dinero llevan la marca *No mueve
  dinero*.
- **Totales**: entrada/salida de dinero, no monetario y total del comprobante.

El panel muestra el comprobante entero aunque los filtros dejen ver solo una de
sus líneas. Lo puede abrir quien tiene acceso a este reporte, aunque no tenga
permiso en los módulos de Ingresos o Egresos.

## Exportar a Excel y PDF

El **Excel** sale en varias hojas:

- **Ingresos y Egresos** (primera hoja): una fila por cada ingreso o egreso, con
  **Tipo** (ingreso o egreso), **número**, **fecha**, **cliente / proveedor /
  empleado**, **valor**, **detalle** (el cuerpo del comprobante: cada línea en un
  renglón, con el documento cobrado o pagado, su descripción y su valor),
  **observaciones** y **asesor**. Arriba lleva los filtros aplicados (periodo,
  vendedor, tercero…) y los totales de ingresos, egresos y neto. El valor de cada
  fila es la suma de sus líneas que cumplen los filtros, así cuadra con los totales.
- **Por tercero / Por forma de pago / Por día / Por mes / Anticipos**: solo cuando en pantalla
  está esa forma de ver; es el mismo resumen que se ve.
- **Detalle completo**: una fila por cada línea de cada comprobante, con número,
  fecha, estado, concepto, tercero e identificación, vendedor, documento cobrado o
  pagado (tipo, número, fecha, valor, saldo anterior y saldo actual), ingreso o
  egreso en columnas separadas, total del comprobante, formas de cobro/pago (con
  operación bancaria, número de cheque y referencia), cuenta contable,
  observaciones, usuario que lo registró y fecha de registro.
- **Cobros y pagos**: una fila por cada forma de cobro o pago de los comprobantes,
  con la operación bancaria, número de cheque, referencia, fecha de cobro,
  beneficiario del cheque y los documentos que cancela.

Las fechas del Excel son fechas reales (se pueden ordenar y filtrar como fechas) y
los valores salen con dos decimales. El Excel no tiene tope de filas.

### Qué trae el PDF

El **PDF** es la vista de la pantalla en hoja, con el mismo formato que el
Reporte de Ventas:

- **Encabezado con el logo** del establecimiento, el nombre de la empresa y el
  título según lo que se muestra: *Reporte de Ingresos*, *Reporte de Egresos* o
  *Reporte de Ingresos y Egresos*, más la vista (*Por tercero*, *Por mes*…).
  Si se filtró por **vendedor** o por un **tercero**, su nombre va debajo del
  título (*Vendedor: Juan Pérez*).
- **Caja "Filtros aplicados"** con el periodo y todos los filtros usados.
- **Banda de indicadores**: ingresos cobrados, egresos pagados, entrada y salida
  de dinero y neto (con un solo flujo: comprobantes, lo cobrado o pagado, lo no
  monetario y la entrada o salida de dinero). En la vista Anticipos:
  saldo anterior, recibido/entregado, aplicado y saldo final.
- **Las columnas que un filtro deja con un único valor no se repiten en cada
  fila**: al filtrar por vendedor desaparece la columna *Asesor*; al elegir un
  tercero, la de *Cliente / Proveedor / Empleado*; al elegir un concepto o un
  tipo de documento, esa columna. El estado no va como columna: todo el PDF es de
  vigentes o todo de anulados, según el filtro. Ese dato ya está en el encabezado.
- Con ingresos y egresos juntos, los valores van en **dos columnas, Ingreso y
  Egreso**, para poder sumar cada una; con *Solo ingresos* o *Solo egresos* va
  una sola. En *Por día* / *Por mes* se muestran solo las columnas del flujo
  elegido (y el neto cuando son los dos).
- Al final, la fila **TOTALES** con la suma de cada columna de valores y de
  conteos (en *Forma de cobro/pago* no se suman los comprobantes, porque uno
  cobrado con dos formas se contaría dos veces; sí los pagos).
- Las vistas Documento (agrupado y detalle) salen en hoja **horizontal**; las resumidas, en
  vertical. La cabecera de la tabla se repite en cada página y abajo va
  *Página X/Y*.
- El PDF se arma fila por fila, así que sale en segundos aunque traiga miles de
  líneas (1.000 líneas, unos 7 segundos).

## Para qué se usa

Para responder preguntas que ningún otro módulo responde de una vez: cuánto se le
ha pagado en total a un proveedor, cuánto entró por tarjeta el mes pasado, cuánto
cobró cada vendedor, qué movimientos hubo por encima de cierto monto.

## Errores frecuentes

- **Falta un movimiento**: revise las fechas; el reporte usa la fecha del ingreso
  o egreso, no la del documento cobrado o pagado. Si el comprobante está anulado,
  solo aparece con el filtro *Estado* en *Anulados*.
- **Los totales no cuadran con el flujo de caja**: compruebe si tiene filtros
  activos que estén acotando el resultado.
- **Un cliente aparece dos veces el mismo día por el mismo valor**: suele ser el
  ingreso que recibió el anticipo y el que lo aplicó a la factura. Son dos
  comprobantes distintos; el segundo lleva la marca *Anticipo aplicado* y no suma
  en *Entrada de dinero*. Con *Movimientos → Solo monetarios* el segundo no aparece.
- **La entrada de dinero no cuadra con el módulo de Ingresos o Egresos**: esos
  módulos muestran el total de cada comprobante, incluido lo pagado con anticipo u
  otras formas no monetarias. Compárelos con *Ingresos cobrados* / *Egresos
  pagados*, que sí lo incluyen.
- **Una forma que sí mueve dinero sale como no monetaria** (o al revés): revise su
  tipo en *Formas de cobro y pago*. Solo los tipos Anticipo y Otros son no
  monetarios.
- **El saldo de anticipos no cuadra con la ficha del cliente**: revise si la
  empresa está en ambiente de pruebas; el reporte cuenta solo documentos de
  producción.
- **Al elegir un vendedor no aparecen egresos**: es lo esperado; los egresos no
  tienen vendedor.
- **Falta un cobro de un vendedor**: revise que la factura cobrada tenga ese
  vendedor asignado. El reporte toma el vendedor de la factura, no el del cliente.
- **Un ingreso por otros conceptos o un cobro de saldo inicial sale sin asesor**:
  el ingreso no tiene cliente o el cliente no tiene vendedor asignado. Asigne el vendedor en la ficha del
  cliente; el reporte lo toma de ahí al instante.

## Historial de cambios

- **1.10** — Nueva forma de ver **Documento (agrupado)**, que ahora es la vista
  por defecto: una fila por ingreso o
  egreso, como se registró, con sus documentos juntos en una celda, en lugar de
  una fila por cada factura. Funciona también en el PDF (horizontal, columna
  *Documentos*); en el Excel ya era la primera hoja.

- **1.9** — Los **anticipos aplicados ya no se cuentan dos veces**: los
  indicadores separan *Ingresos cobrados / Egresos pagados* (todo, como en los
  módulos), *No monetario* (formas de tipo Anticipo y Otros) y *Entrada / salida
  de dinero*; el **Neto** es de dinero. Las vistas Tercero, Fecha y Mes, el Excel y
  el PDF traen las mismas columnas, y en la vista Documento las líneas pagadas con
  esas formas llevan su marca. Nuevo filtro **Movimientos** (*Todos*, *Solo monetarios*, *Solo no monetarios*), que
  separa el dinero que entró o salió de lo que no movió dinero. Nueva forma de ver **Anticipos (saldos)**:
  saldo anterior, recibido, aplicado y saldo final por cliente y proveedor, o los
  movimientos de uno solo. El **PDF ya no tarda minutos** con muchas líneas (se
  arma fila por fila). Además, el reporte **ya no se carga solo al entrar** ni al
  presionar *Limpiar*: los datos salen al presionar **Buscar**. Los filtros tienen **favoritos** (estrella) y, al hacer clic en una fila, se abre el **detalle del ingreso o egreso** con sus documentos y formas de cobro/pago.
- **1.8** — Los ingresos y egresos **anulados ya no aparecen ni suman** en el
  reporte. El filtro *Estado* ofrece *Vigentes* (por defecto) o *Anulados*; se
  quitó la opción *Todos*, que los mezclaba en los totales.
- **1.7** — El reporte muestra solo documentos de **producción**, aunque la empresa esté configurada en pruebas (ver el artículo *Los reportes solo muestran documentos de producción*).
- **1.6** — **PDF rediseñado** con el formato del Reporte de Ventas: logo del
  establecimiento, título según el flujo y la vista (con el vendedor o tercero
  filtrado debajo), caja de filtros aplicados, indicadores, columnas Ingreso y
  Egreso separadas, fila de **TOTALES** al final y sin repetir en cada fila las
  columnas que un filtro deja fijas (p. ej. *Asesor* al filtrar por vendedor).
  Las vistas resumidas salen en hoja vertical.
- **1.5** — Los **cobros de saldos iniciales** toman como asesor el vendedor
  asignado al cliente del saldo inicial, tanto en el filtro por vendedor como en
  la columna **Asesor**, el PDF y el Excel. Antes salían sin asesor y no entraban
  al filtrar por vendedor.
- **1.4** — Los **ingresos por otros conceptos** (líneas *Otro*) toman como asesor
  el vendedor asignado al cliente, tanto en el filtro por vendedor como en la
  columna **Asesor**, que ahora también se ve en la vista Documento y en el PDF.
  El **Excel** abre con una hoja nueva, *Ingresos y Egresos*, con una fila por
  comprobante: tipo, número, fecha, cliente/proveedor/empleado, valor, detalle
  (el cuerpo del comprobante), observaciones y asesor; la hoja de todas las
  columnas pasa a llamarse *Detalle completo*.
- **1.3** — Nuevo filtro **Vendedor**: muestra los cobros de las facturas y recibos
  de venta de ese vendedor. El **Excel** ahora sale con todo el detalle: filtros y
  totales arriba, hoja *Detalle* con todas las columnas (vendedor, documento
  cobrado o pagado con sus saldos, formas de cobro/pago, cuenta contable, usuario
  que registró…) y hoja *Cobros y pagos* con una fila por cada forma de cobro o
  pago. El PDF muestra los filtros aplicados y sus fechas pasan a día-mes-año con
  guiones. Corregido: elegir *Solo egresos* junto con un cliente daba error en
  lugar de un resultado vacío.
- **1.2** — Al filtrar por forma de pago u operación bancaria, la vista Detalle ya no
  incluye egresos cuya forma de pago fue cambiada (los pagos anteriores quedan
  eliminados lógicamente y no deben cumplir el filtro). Los cheques anulados desde el
  egreso tampoco cuentan: no suman en la vista Por forma ni cumplen los filtros de
  forma de pago y operación bancaria (mismo criterio que el asiento contable y Conciliación
  Bancaria).
- **1.1** — El detalle exportado a Excel/PDF ya no se corta en 5000 filas; el tope
  de 5000 se mantiene solo para la vista en pantalla.
- **1.0** — Versión inicial.
