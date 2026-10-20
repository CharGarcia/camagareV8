---
titulo: Resumen diario
resumen: Todo lo que se movió en un día: ventas con su saldo, compras, retenciones, cobros (del día y de días anteriores), pagos y traslados, con el saldo de cada forma de pago. En pantalla, PDF y Excel.
categoria: Reportes
ruta_modulo: modulos/reporte_resumen_diario
tipo: modulo
visibilidad: todos
etiquetas: resumen diario, cierre del dia, cierre de caja, abrir caja, cerrar caja, reabrir caja, arqueo, arqueo de caja, contar la caja, fondo de caja, faltante, sobrante, diferencia de caja, caja por usuario, caja por cajero, filtro por usuario, cuadre de caja, cuadre diario, libro de caja, movimiento del dia, que paso hoy, ventas del dia, compras del dia, ingresos del dia, egresos del dia, cobros del dia, cobros de dias anteriores, cobros atrasados, pagos del dia, facturas del dia, saldo de la factura, saldo pendiente, recibos, notas de credito, notas de debito, retenciones recibidas, retenciones emitidas, liquidaciones de compra, efectivo, tarjeta, transferencia, cheque, forma de pago, saldo inicial, saldo final, saldo de caja, saldo de apertura, apertura de caja, traslado, deposito del efectivo, depositar en el banco, retiro del banco, pasar de efectivo a banco, cuanto entro, cuanto salio, cuanto hay en caja, numero de ingreso, numero de egreso, recibido de, pagado a, firmas, realizado por, aprobado por, pdf, excel, imprimir
version: 1.5
orden: 12
estado: activo
---

El **Resumen diario** muestra en una sola hoja todo lo que se movió en **un día**:
lo que se vendió y lo que se compró (con sus notas de crédito y de débito y sus
retenciones), el dinero que entró y salió (Ingresos y Egresos), los traslados entre
formas de pago y el **saldo de cada forma de pago** (saldo inicial, movimientos del
día y saldo final). Reemplaza al antiguo botón *Resumen diario* del Reporte de Ventas.

## Qué es y para qué sirve

Sirve para cerrar el día como un libro de caja: revisar qué se emitió, qué se
registró de proveedores, qué se cobró (separando lo cobrado de las ventas de hoy de
lo cobrado de días anteriores), qué se pagó y con cuánto quedó cada forma de pago
(efectivo, tarjeta, transferencia, cheque…). Trabaja sobre la empresa activa.

## Requisitos previos

- La base de datos debe tener aplicado el SQL `database/20261008_caja_saldos_traslados.sql`
  (tablas de saldos de apertura y traslados). Sin él el resumen funciona, pero no se
  pueden registrar traslados ni saldos de apertura.
- Para que los saldos arranquen de un valor real, cargue el **saldo de apertura** de
  cada forma de pago (ver *Saldos de apertura*).
- Para abrir y cerrar caja, el SQL `database/20261020_caja_cierres_diarios.sql`
  (tablas de cierres de caja). Sin él, la opción *Cierre de caja* lo avisa y el resto
  del resumen funciona igual.

## Cómo se usa

1. Elija el **Día** (con las flechas ‹ › pasa al día anterior o al siguiente).
2. Si quiere contar también los comprobantes electrónicos que aún no se
   autorizan, elija **Con borradores**.
3. En **Caja** elija cómo ver la caja por forma de pago:
   - **Neto del día** (por defecto): solo lo que entró y salió ese día (ingresos,
     egresos, traslados y neto).
   - **Con saldos iniciales**: además, el saldo que traía cada forma de pago y el
     saldo final (ver *Saldo por forma de pago*).
   - **Cierre de caja**: abrir la caja del día con su saldo inicial y cerrarla con
     lo contado (ver *Abrir y cerrar caja*).
4. Con **acceso total** aparece el selector **Usuario**: *Todos* (por defecto, toda
   la empresa) o un usuario, para ver solo lo que registró ese usuario y su caja.
5. Pulse **Mostrar**. Arriba se actualizan los indicadores: **Ventas netas**,
   **Compras netas**, **Ingresos**, **Egresos**, **Neto del día** y, con saldos
   iniciales, **Saldo final** (en *Cierre de caja*, **Saldo esperado** y, con la caja
   cerrada, **Contado** y **Diferencia**).
6. Con **PDF** o **Excel** descarga el mismo resumen.
7. Con **Nuevo traslado** registra dinero que pasó de una forma de pago a otra; con
   **Saldos de apertura** fija el saldo con que arranca cada forma.

## Qué trae el resumen

**Ventas e ingresos**

| Sección | Columnas |
|---------|----------|
| Facturas de venta | Número, cliente, **total** y **saldo** (lo que falta cobrar hoy) |
| Recibos de venta | Número, cliente, **total** y **saldo** |
| Notas de débito | Número, cliente, documento que modifican y total |
| Notas de crédito | Número, cliente, documento que modifican y total (restan de las ventas) |
| Retenciones recibidas | Número, cliente, documento sustento, renta, IVA y total |
| Cobros de ventas del día | Recibido de, n.º de ingreso, detalle, forma de pago y valor |
| Cobros de días anteriores y otros | Igual que la anterior |

**Compras y egresos**

| Sección | Columnas |
|---------|----------|
| Compras | Número, proveedor, tipo de comprobante, subtotal, impuestos y total |
| Liquidaciones de compra | Número, proveedor, subtotal, impuestos y total |
| Notas de crédito de proveedores | Igual, más el documento que modifican (restan de las compras) |
| Retenciones emitidas | Número, proveedor, documento sustento, renta, IVA y total |
| Egresos (pagos) | Pagado a, n.º de egreso, detalle, forma de pago y valor |

**Traslados entre formas de pago**: desde, hacia, observaciones y valor.

Cada sección trae su total. **Solo se muestra lo que existe**: una sección sin
documentos ese día no aparece, y tampoco un bloque entero si no tiene nada. En el
resumen del día pasa lo mismo con cada línea. Si el día no tiene ningún movimiento,
sale el aviso *No hay documentos ni movimientos en este día*.

**Resumen del día**

- **Ventas**: facturas + recibos + notas de débito − notas de crédito =
  **Ventas netas**; aparte, las retenciones recibidas, lo **cobrado de las ventas del
  día** y el **saldo pendiente de las ventas del día**.
- **Compras**: compras + liquidaciones − notas de crédito de proveedores =
  **Compras netas**, y aparte el total de retenciones emitidas.
- **Caja por forma de pago**: por cada forma usada ese día, **ingresos − egresos ±
  traslados = neto del día**; con *Caja: Con saldos iniciales*, **saldo inicial +
  ingresos − egresos ± traslados = saldo final**. Con el total.

Los títulos van en negro, sin colores. El **PDF** lleva el logo y el nombre de la
empresa (el logo del establecimiento o, si ningún establecimiento tiene, el del
punto de emisión), la caja *Filtros aplicados*, los indicadores, todas las secciones
y al pie las firmas **Realizado por** (quien lo genera) y **Aprobado por** (en
blanco). El **Excel** trae las mismas secciones en una hoja, una debajo de otra, con
los valores como números.

## Saldo de la factura

El **saldo** de cada factura es lo que falta cobrar **hoy** (no al cierre de ese
día), con la misma cuenta que *Cuentas por Cobrar*: total + notas de débito − cobros
− retenciones − notas de crédito aplicadas. Nunca es negativo. En los recibos: total
− cobros.

## Cobros del día y de días anteriores

Los Ingresos del día se separan según **qué documentos cobraron**:

- **Cobros de ventas del día**: lo cobrado a facturas y recibos emitidos ese mismo día.
- **Cobros de días anteriores y otros**: lo cobrado a documentos de otras fechas,
  saldos iniciales u otros conceptos.

Si un mismo Ingreso cobró documentos de los dos grupos, cada forma de pago se reparte
en proporción a lo cobrado en cada uno. Ejemplo: un Ingreso de $40 (20 en efectivo y
20 por transferencia) que cobra $10 de una factura de hoy y $30 de una de ayer sale
con $5 + $5 en *ventas del día* y $15 + $15 en *días anteriores*. La suma de los dos
grupos es siempre lo que entró.

## Detalle de ingresos y egresos

Cada Ingreso o Egreso sale **una vez por forma de pago** (si se cobró la mitad en
efectivo y la mitad con transferencia, son dos filas).

- **Recibido de / Pagado a**: el nombre escrito en el comprobante o, si está
  vacío, el cliente, proveedor o empleado.
- **Detalle**: lo que cobró o pagó, con su valor, por ejemplo
  *Fact. 001-001-000000123 (11.50), Fact. 001-001-000000124 (20.00)*. Si el
  comprobante no tiene documentos, su concepto o sus observaciones.
- **Forma de pago**: el nombre de la forma y, si tiene, el número de cheque o la
  referencia de la transferencia.

## Saldo por forma de pago

Se ve al elegir **Caja: Con saldos iniciales**; con *Neto del día* la caja muestra
solo los movimientos del día, sin saldos.

El **saldo inicial** de cada forma de pago es lo que quedó al cierre del día anterior:
su **saldo de apertura** más todos los Ingresos, menos todos los Egresos y +/− los
traslados desde la fecha de apertura hasta el día anterior. El **saldo final** de hoy
es el saldo inicial de mañana. Ejemplo: si ayer cerró en −100 y hoy entran 300 y
salen 50, hoy queda en 150.

- Sin saldo de apertura, el saldo se acumula desde el primer Ingreso o Egreso
  registrado de esa forma de pago.
- Solo salen las formas de pago **usadas ese día** (con algún ingreso, egreso o
  traslado). Una forma con saldo pero sin movimiento ese día no aparece, y su saldo
  tampoco entra en el indicador **Saldo final**, que suma solo las formas mostradas.
- Los Ingresos sin forma de pago salen como *Sin forma de pago registrada* y no
  tienen saldo.
- El saldo solo lo ve quien tiene **acceso total** en el módulo: con registros
  propios se ven los ingresos, egresos y traslados del usuario, sin saldos.

## Abrir y cerrar caja

Con **Caja: Cierre de caja** el resumen sirve para cuadrar la caja del día. Las
opciones *Neto del día* y *Con saldos iniciales* siguen igual que siempre.

**De quién es la caja.** Hay una caja por día y por alcance:

- **Todos**: la caja de toda la empresa.
- **Un usuario** (selector *Usuario*, con acceso total): cuenta solo los ingresos,
  egresos y traslados que registró ese usuario. Sirve para cuadrar a cada cajero.
- Sin acceso total no hay selector: cada usuario abre y cierra **su propia** caja.

Junto al título del día aparece el estado: *Caja sin abrir*, *Caja abierta* o *Caja
cerrada* (al pasar el mouse dice quién y cuándo).

**Abrir caja.** Botón **Abrir caja** (pide permiso de crear). Muestra las formas de
pago con el **saldo inicial propuesto**, que puede corregir (en efectivo, el fondo):

- Si esa caja ya se cerró antes, lo **contado en el último cierre** más lo que se
  movió en los días que quedaron sin cerrar.
- Si nunca se cerró: para *Todos*, el saldo inicial del resumen (saldo de apertura +
  movimientos); para un usuario, cero.

Mientras la caja no se abre, la tabla muestra el saldo inicial como *propuesto*.

**Cerrar caja.** Botón **Cerrar caja** (pide permiso de crear). Por cada forma de pago
muestra el **esperado** = saldo inicial + ingresos − egresos ± traslados del día, y se
escribe lo **contado** (el efectivo del cajón, el saldo del banco…); la diferencia se
calcula sola (en rojo si falta, en verde si sobra). **Copiar esperado** pone el
esperado en todas las formas para corregir solo las que no cuadran. Todas las formas
deben tener un valor. Al guardar, el sistema vuelve a calcular el esperado.

Con la caja cerrada, *Caja por forma de pago* trae además **Contado** y
**Diferencia**, igual en el PDF y el Excel, y en *Filtros aplicados* sale quién la
cerró y cuándo.

**Reabrir caja.** Botón **Reabrir caja** (pide permiso de modificar y acceso total) y
un motivo. Borra el arqueo (contado y diferencia) y conserva el saldo inicial; luego
se vuelve a cerrar.

**Reglas**

- Cerrar la caja **no bloquea** nada: se pueden seguir registrando o anulando
  Ingresos y Egresos de ese día. Si después del cierre cambian, el resumen avisa con
  el esperado de hoy y el que había al cerrar; reabra la caja para volver a cuadrar.
- No toca los *Saldos de apertura* ni los traslados, no genera asientos ni
  movimientos en *Control Bancario*.
- Es independiente del cierre de turno de la *Caja POS* (Restaurante): lo cobrado en
  el POS también es un Ingreso y aparece en la caja del cajero que lo cobró.
- Abrir, cerrar y reabrir quedan en el historial (*log_sistema*). Un doble clic o un
  reintento tras un error de conexión no abre dos cajas.

## Saldos de apertura

Botón **Saldos de apertura** (pide permiso de modificar y acceso total). Muestra
todas las formas de pago activas; para cada una se escribe la **fecha** y el
**saldo** con que arrancó **al inicio** de esa fecha. Ejemplo: «al 01-10-2026 había
$500 en efectivo». Desde esa fecha el saldo se acumula solo. Para quitar una
apertura, borre su fecha y guarde. Hay una sola apertura vigente por forma de pago:
si la cambia, el saldo se recalcula desde la nueva fecha.

## Traslados entre formas de pago

Botón **Nuevo traslado** (pide permiso de crear). Sirve para el dinero que pasa de
una forma de pago a otra **sin ser ingreso ni egreso**: depositar el efectivo en el
banco, retirar del banco a caja, etc.

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fecha | Sí | Día del traslado (por defecto, el día que está viendo) |
| Valor | Sí | Mayor que cero |
| Desde | Sí | Forma de pago de donde sale el dinero |
| Hacia | Sí | Forma de pago a donde entra; distinta de la de origen |
| Observaciones | No | Por ejemplo, el número de la papeleta de depósito |

El traslado resta de la forma de origen y suma a la de destino; el total de la caja
no cambia. **No genera asiento contable** ni movimiento en *Control Bancario*. Se
elimina con el ícono de la papelera en la sección *Traslados* (pide permiso de
eliminar). Un doble clic o un reintento tras un error de conexión no lo duplica.

## Permisos

- **Ver**: abrir el resumen, PDF y Excel.
- **Crear**: registrar traslados; abrir y cerrar caja. **Eliminar**: eliminar traslados.
- **Modificar** + **Acceso total**: saldos de apertura (son de toda la empresa) y
  reabrir una caja cerrada.
- Con **Acceso total** se ve todo lo de la empresa, con saldos, y se puede elegir un
  **Usuario** para ver solo lo suyo y abrir o cerrar su caja. Sin él, solo lo que
  registró el propio usuario y sin saldos (en *Cierre de caja*, su propia caja); el
  PDF y el Excel lo indican en *Filtros aplicados*.

## Reglas de negocio

- Es **por día**: todo se toma por la **fecha de emisión** del documento. Una
  compra se ubica en la fecha de la factura del proveedor, no en el día en que se
  registró.
- Solo cuentan documentos de **producción**, aunque la empresa esté configurada
  en pruebas (ver *Los reportes solo muestran documentos de producción*). Los
  traslados y aperturas no tienen ambiente.
- **No cuentan**: los anulados; los recibos ya facturados (están en la factura);
  las compras rechazadas; las formas de pago reemplazadas al editar un egreso y los
  cheques anulados.
- Facturas, notas de crédito y de débito, liquidaciones y retenciones emitidas
  cuentan cuando están **autorizadas**; con *Con borradores* también las que siguen
  en borrador, que salen marcadas *(borrador)* junto al número. Los recibos cuentan
  siempre que no estén anulados ni facturados (un recibo a crédito queda en
  borrador y es venta igual).
- En compras, **Impuestos** = total − subtotal (IVA, ICE y servicio).
- Traslados y saldos de apertura quedan en el historial (*log_sistema*); al eliminar
  un traslado no se borra, se marca como eliminado.
- Solo con *Caja: Cierre de caja* pide el efectivo contado y calcula la diferencia
  (ver *Abrir y cerrar caja*); los otros modos no lo hacen.

## Integraciones con otros módulos

Lee, sin modificar: Facturas de venta, Recibos de venta, Notas de crédito, Notas
de débito, Retenciones en ventas, Compras, Liquidaciones de compra, Retenciones en
compras, Ingresos y Egresos. Los traslados y saldos de apertura solo existen en este
módulo: no generan asientos ni movimientos en Control Bancario.

## Errores frecuentes

- **Una factura del día no aparece**: revise que esté autorizada (o elija *Con
  borradores*), que no esté anulada y que sea de producción.
- **Una compra no aparece en el día en que la registré**: el resumen usa la fecha
  de emisión de la factura del proveedor.
- **El saldo del efectivo crece sin parar**: el efectivo que se deposita en el banco
  debe registrarse como **traslado** (desde Efectivo hacia la cuenta del banco).
- **El saldo arranca con un valor que no es real**: no hay saldo de apertura y se
  está acumulando desde el primer movimiento (puede incluir datos migrados). Cargue
  la apertura con la fecha y el saldo reales.
- **"Falta aplicar en la base de datos el SQL…"**: falta el SQL de saldos y traslados
  (ver *Requisitos previos*).
- **No veo los saldos ni el botón Saldos de apertura**: hace falta *Acceso total* en
  este módulo (y permiso de modificar para la apertura).
- **Un Ingreso sale como "Sin forma de pago registrada"**: se guardó sin formas de
  pago. Edítelo en *Ingresos* y agregue la forma.

- **No aparece el botón Abrir caja / Cerrar caja**: elija *Caja: Cierre de caja* y
  pulse *Mostrar*; hace falta permiso de crear. Si la caja está cerrada, solo sale
  *Reabrir caja* (con acceso total).
- **"La caja de este día ya fue abierta"**: otro usuario la abrió mientras tanto;
  pulse *Mostrar* para verla.
- **El esperado de una caja cerrada no coincide con el de hoy**: después del cierre
  se registró, editó o anuló un Ingreso, Egreso o traslado de ese día. Reabra la caja
  y vuelva a cerrarla.

## Historial de cambios

- **1.5** — Nueva opción **Caja: Cierre de caja**: abrir la caja del día con su saldo
  inicial por forma de pago y cerrarla con lo contado (arqueo) y la diferencia; reabrir
  con motivo. Nuevo selector **Usuario** (acceso total) para ver lo de un usuario y
  manejar su caja. *Neto del día* y *Con saldos iniciales* no cambian.

- **1.4** — Selector **Caja**: *Neto del día* (por defecto) o *Con saldos iniciales*.
- **1.3** — *Caja por forma de pago* muestra solo las formas de pago usadas ese día.
- **1.2** — Saldo por forma de pago: saldo inicial (saldo de apertura + movimientos
  anteriores), ingresos, egresos, traslados y saldo final. Nuevos **traslados entre
  formas de pago** y **saldos de apertura**. Los cobros se separan en *Cobros de
  ventas del día* y *Cobros de días anteriores y otros*. Facturas y recibos muestran
  solo **total y saldo**; las notas, solo el total. El resumen de ventas agrega lo
  cobrado y lo pendiente de las ventas del día.
- **1.1** — Solo se muestran las secciones y líneas que tienen documentos. Títulos sin
  colores en pantalla, PDF y Excel. El PDF muestra el logo también cuando la empresa
  solo lo tiene cargado en el punto de emisión.
- **1.0** — Versión inicial: ventas, compras, retenciones, ingresos y egresos de un
  día, con el resumen por forma de pago, en pantalla, PDF y Excel. Reemplaza al
  botón *Resumen diario* del Reporte de Ventas.
