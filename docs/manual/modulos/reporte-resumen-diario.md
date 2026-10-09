---
titulo: Resumen diario
resumen: Todo lo que se movió en un día: ventas, compras, retenciones, cobros y pagos, con el neto de caja por forma de pago. En pantalla, PDF y Excel.
categoria: Reportes
ruta_modulo: modulos/reporte_resumen_diario
tipo: modulo
visibilidad: todos
etiquetas: resumen diario, cierre del dia, cierre de caja, cuadre de caja, cuadre diario, movimiento del dia, que paso hoy, ventas del dia, compras del dia, ingresos del dia, egresos del dia, cobros del dia, pagos del dia, facturas del dia, recibos, notas de credito, notas de debito, retenciones recibidas, retenciones emitidas, liquidaciones de compra, efectivo, tarjeta, transferencia, cheque, forma de pago, neto de caja, cuanto entro, cuanto salio, numero de ingreso, numero de egreso, recibido de, pagado a, firmas, realizado por, aprobado por, pdf, excel, imprimir
version: 1.1
orden: 12
estado: activo
---

El **Resumen diario** muestra en una sola hoja todo lo que se movió en **un día**:
lo que se vendió y lo que se compró (con sus notas de crédito y de débito y sus
retenciones) y el dinero que entró y salió (Ingresos y Egresos), con el neto de
caja por forma de pago. Reemplaza al antiguo botón *Resumen diario* del Reporte de
Ventas.

## Qué es y para qué sirve

Sirve para cerrar el día: revisar qué se emitió, qué se registró de proveedores,
qué se cobró y qué se pagó, y cuánto quedó por cada forma de pago (efectivo,
tarjeta, transferencia, cheque…). Trabaja sobre la empresa activa.

## Cómo se usa

1. Elija el **Día** (con las flechas ‹ › pasa al día anterior o al siguiente).
2. Si quiere contar también los comprobantes electrónicos que aún no se
   autorizan, elija **Con borradores**.
3. Pulse **Mostrar**. Arriba se actualizan los indicadores: **Ventas netas**,
   **Compras netas**, **Ingresos (cobros)**, **Egresos (pagos)** y **Neto de caja**.
4. Con **PDF** o **Excel** descarga el mismo resumen.

## Qué trae el resumen

**Ventas e ingresos**

| Sección | Columnas |
|---------|----------|
| Facturas de venta | Número, cliente, subtotal, impuestos y total |
| Recibos de venta | Número, cliente, subtotal, impuestos y total |
| Notas de débito | Igual, más el documento que modifican |
| Notas de crédito | Igual, más el documento que modifican (restan de las ventas) |
| Retenciones recibidas | Número, cliente, documento sustento, renta, IVA y total |
| Ingresos (cobros) | Recibido de, n.º de ingreso, detalle, forma de pago y valor |

**Compras y egresos**

| Sección | Columnas |
|---------|----------|
| Compras | Número, proveedor, tipo de comprobante, subtotal, impuestos y total |
| Liquidaciones de compra | Número, proveedor, subtotal, impuestos y total |
| Notas de crédito de proveedores | Igual, más el documento que modifican (restan de las compras) |
| Retenciones emitidas | Número, proveedor, documento sustento, renta, IVA y total |
| Egresos (pagos) | Pagado a, n.º de egreso, detalle, forma de pago y valor |

Cada sección trae su total. **Solo se muestra lo que existe**: una sección sin
documentos ese día no aparece, y tampoco un bloque entero si no tiene nada (un día
sin compras ni egresos no muestra *Compras y egresos*). En el resumen del día pasa
lo mismo con cada línea. Si el día no tiene ningún movimiento, sale el aviso *No
hay documentos ni movimientos en este día*.

**Resumen del día**

- **Ventas**: facturas + recibos + notas de débito − notas de crédito =
  **Ventas netas**, y aparte el total de retenciones recibidas.
- **Compras**: compras + liquidaciones − notas de crédito de proveedores =
  **Compras netas**, y aparte el total de retenciones emitidas.
- **Caja por forma de pago**: por cada forma, lo que entró (Ingresos), lo que salió
  (Egresos) y el **neto**, con el total.

Los títulos van en negro, sin colores. El **PDF** lleva el logo y el nombre de la
empresa (el logo del establecimiento o, si ningún establecimiento tiene, el del
punto de emisión), la caja *Filtros aplicados*,
los indicadores, todas las secciones y al pie las firmas **Realizado por** (quien
lo genera) y **Aprobado por** (en blanco). El **Excel** trae las mismas secciones
en una hoja, una debajo de otra, con los valores como números.

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

## Permisos

- Hace falta el permiso **Ver** del módulo.
- Con **Acceso total** se ve todo lo de la empresa. Sin él, solo lo que registró el
  propio usuario (sus facturas, compras, ingresos, egresos, etc.); el PDF y el Excel
  lo indican en *Filtros aplicados*.

## Reglas de negocio

- Es **por día**: todo se toma por la **fecha de emisión** del documento. Una
  compra se ubica en la fecha de la factura del proveedor, no en el día en que se
  registró.
- Solo cuentan documentos de **producción**, aunque la empresa esté configurada
  en pruebas (ver *Los reportes solo muestran documentos de producción*).
- **No cuentan**: los anulados; los recibos ya facturados (están en la factura);
  las compras rechazadas; las formas de pago reemplazadas al editar un egreso y los
  cheques anulados.
- Facturas, notas de crédito y de débito, liquidaciones y retenciones emitidas
  cuentan cuando están **autorizadas**; con *Con borradores* también las que siguen
  en borrador, que salen marcadas *(borrador)* junto al número. Los recibos cuentan
  siempre que no estén anulados ni facturados (un recibo a crédito queda en
  borrador y es venta igual).
- **Impuestos** = total − subtotal (IVA, ICE y servicio).
- Es solo de lectura: no modifica nada ni pide el efectivo contado (para el arqueo
  de caja está el cierre de la *Caja POS*).

## Integraciones con otros módulos

Lee, sin modificar: Facturas de venta, Recibos de venta, Notas de crédito, Notas
de débito, Retenciones en ventas, Compras, Liquidaciones de compra, Retenciones en
compras, Ingresos y Egresos.

## Errores frecuentes

- **Una factura del día no aparece**: revise que esté autorizada (o elija *Con
  borradores*), que no esté anulada y que sea de producción.
- **Una compra no aparece en el día en que la registré**: el resumen usa la fecha
  de emisión de la factura del proveedor.
- **El neto de caja no cuadra con las ventas**: el neto de caja es lo cobrado
  menos lo pagado ese día; las ventas a crédito no entran a caja hasta que se
  registra su Ingreso, y un Ingreso de hoy puede cobrar facturas de otros días.
- **Un Ingreso sale como "Sin forma de pago registrada"**: se guardó sin formas de
  pago. Edítelo en *Ingresos* y agregue la forma.
- **Solo veo lo mío**: no tiene *Acceso total* en este módulo.

## Historial de cambios

- **1.1** — Solo se muestran las secciones y líneas que tienen documentos. Títulos sin
  colores en pantalla, PDF y Excel. El PDF muestra el logo también cuando la empresa
  solo lo tiene cargado en el punto de emisión.
- **1.0** — Versión inicial: ventas, compras, retenciones, ingresos y egresos de un
  día, con el resumen por forma de pago, en pantalla, PDF y Excel. Reemplaza al
  botón *Resumen diario* del Reporte de Ventas.
