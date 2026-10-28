---
titulo: Reporte de ingresos y egresos
resumen: Todo el movimiento de dinero del periodo en un solo listado, filtrable y exportable.
categoria: Reportes
ruta_modulo: modulos/reporte_ingresos_egresos
tipo: modulo
visibilidad: todos
etiquetas: reporte de ingresos y egresos, anulados, ingresos anulados, egresos anulados, vigentes, movimiento de dinero, cobros y pagos, por tercero, forma de pago, concepto, vendedor, asesor, cobros por vendedor, comisiones, asesor del cliente, otros conceptos, saldo inicial, saldos iniciales, exportar, excel detallado, pdf, imprimir, logo en el pdf, totales en el pdf, reporte de ingresos por vendedor, reporte de egresos, pdf por vendedor, anticipo, anticipos, anticipo aplicado, cruce de anticipo, ingresos repetidos, ingreso duplicado, doble conteo
version: 1.9
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
| Tipo de documento | Qué documento originó el movimiento |
| Monto | Rango de valores |
| Texto libre | Búsqueda sobre la descripción |

Todos se combinan entre sí. La consulta típica —*todo lo que le pagué a este
proveedor por transferencia este trimestre*— sale con tres filtros.

## Ingresos y egresos anulados

El reporte muestra por defecto solo los comprobantes **vigentes**: un ingreso o
egreso anulado **no aparece ni suma** en los totales, las vistas resumidas, el
Excel ni el PDF. Así los valores cuadran con los módulos de Ingresos y Egresos.

Para revisar los anulados, elija **Anulados** en el filtro *Estado*: el reporte
muestra entonces solo esos. Nunca se mezclan vigentes y anulados en un mismo
resultado.

## Anticipos aplicados: no se cuentan dos veces

Un anticipo pasa por dos comprobantes:

1. **Cuando el cliente entrega el anticipo** (o la empresa se lo entrega al
   proveedor) se registra un ingreso (o egreso) con un concepto de anticipo. Ahí
   **sí entra (o sale) dinero**, y el reporte lo suma.
2. **Cuando ese anticipo se aplica a una factura** (o a una compra) se registra
   otro ingreso (o egreso) que la cancela con la forma de cobro/pago de tipo
   **Anticipo**. Ese comprobante **no mueve caja**: el dinero ya se contó en el
   paso 1.

Por eso, lo cobrado o pagado con una forma de tipo Anticipo **no suma** en el
reporte:

- **Vista Documento**: la línea se sigue viendo (para saber qué factura se canceló
  con el anticipo), con monto **$0.00** y la marca **Anticipo aplicado $…** debajo.
  Si el comprobante se pagó una parte con anticipo y otra en efectivo u otra forma,
  el monto es solo esa otra parte; con varias líneas, lo aplicado se reparte entre
  ellas en proporción a su valor.
- **Totales, Tercero, Fecha y Mes**: no suman lo aplicado del anticipo. Un
  comprobante pagado entero con anticipo tampoco cuenta en el número de
  comprobantes.
- **Forma de cobro/pago**: la forma de tipo Anticipo aparece con su valor, en gris
  y con la nota *no suma*; en el PDF va entre paréntesis y fuera de la fila TOTALES.
- **Excel**: las hojas *Ingresos y Egresos*, *Detalle completo* y *Cobros y pagos*
  llevan una columna **Anticipo aplicado** aparte de *Ingreso* / *Egreso*, y el
  detalle del comprobante indica *(anticipo aplicado $…)*.

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

- **Documento (detalle)**: cada línea de cada comprobante, con su asesor. Es la
  vista de auditoría.
- **Tercero (resumen)**: agrupado por cliente, proveedor o empleado, para saber
  cuánto se movió con cada uno.
- **Forma de cobro/pago**: cuánto entró y salió por cada forma (efectivo, cada
  banco, tarjeta…).
- **Fecha** y **Mes**: ingresos, egresos y neto por día o por mes.

## Exportar a Excel y PDF

El **Excel** sale en varias hojas:

- **Ingresos y Egresos** (primera hoja): una fila por cada ingreso o egreso, con
  **Tipo** (ingreso o egreso), **número**, **fecha**, **cliente / proveedor /
  empleado**, **valor**, **detalle** (el cuerpo del comprobante: cada línea en un
  renglón, con el documento cobrado o pagado, su descripción y su valor),
  **observaciones** y **asesor**. Arriba lleva los filtros aplicados (periodo,
  vendedor, tercero…) y los totales de ingresos, egresos y neto. El valor de cada
  fila es la suma de sus líneas que cumplen los filtros, así cuadra con los totales.
- **Por tercero / Por forma de pago / Por día / Por mes**: solo cuando en pantalla
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
- **Banda de indicadores**: comprobantes, total de ingresos, total de egresos y
  neto (con un solo flujo, solo los de ese flujo).
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
- La vista Documento sale en hoja **horizontal**; las vistas resumidas, en
  vertical. La cabecera de la tabla se repite en cada página y abajo va
  *Página X/Y*.

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
- **Un cobro sale en $0.00 con la marca "Anticipo aplicado"**: es lo esperado; se
  pagó con un anticipo que ya sumó cuando se recibió (ver *Anticipos aplicados*).
- **Los totales no cuadran con el módulo de Ingresos o Egresos**: esos módulos
  muestran el total de cada comprobante, incluido lo pagado con anticipo; este
  reporte no lo suma para no contar dos veces el mismo dinero.
- **Al elegir un vendedor no aparecen egresos**: es lo esperado; los egresos no
  tienen vendedor.
- **Falta un cobro de un vendedor**: revise que la factura cobrada tenga ese
  vendedor asignado. El reporte toma el vendedor de la factura, no el del cliente.
- **Un ingreso por otros conceptos o un cobro de saldo inicial sale sin asesor**:
  el ingreso no tiene cliente o el cliente no tiene vendedor asignado. Asigne el vendedor en la ficha del
  cliente; el reporte lo toma de ahí al instante.

## Historial de cambios

- **1.9** — Los **anticipos aplicados ya no se cuentan dos veces**: lo cobrado o
  pagado con una forma de tipo Anticipo no suma en totales, vistas resumidas,
  Excel ni PDF, porque ese dinero ya se contó al recibir o entregar el anticipo.
  En la vista Documento la línea se sigue viendo, marcada *Anticipo aplicado*, y
  el Excel trae una columna **Anticipo aplicado**. Además, el reporte **ya no se
  carga solo al entrar** ni al presionar *Limpiar*: los datos salen al presionar
  **Buscar**.
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
