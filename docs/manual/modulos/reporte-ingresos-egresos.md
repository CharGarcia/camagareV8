---
titulo: Reporte de ingresos y egresos
resumen: Todo el movimiento de dinero del periodo en un solo listado, filtrable y exportable.
categoria: Reportes
ruta_modulo: modulos/reporte_ingresos_egresos
tipo: modulo
visibilidad: todos
etiquetas: reporte de ingresos y egresos, movimiento de dinero, cobros y pagos, por tercero, forma de pago, concepto, vendedor, asesor, cobros por vendedor, comisiones, exportar, excel detallado
version: 1.3
orden: 30
estado: activo
---

Este reporte junta **ingresos y egresos en un mismo listado**, al nivel de cada
línea de detalle. Es la vista completa del movimiento de dinero: qué entró, qué
salió, de quién y por qué concepto.

## Filtros combinables

| Filtro | Para qué |
|--------|----------|
| Fechas | El periodo |
| Tercero | Un cliente, proveedor o empleado concreto |
| Vendedor | Los cobros de las facturas y recibos de venta de un vendedor |
| Forma de pago | Efectivo, banco, tarjeta… |
| Operación bancaria | Transferencia, cheque, depósito |
| Concepto | El motivo del ingreso o egreso |
| Tipo de documento | Qué documento originó el movimiento |
| Monto | Rango de valores |
| Texto libre | Búsqueda sobre la descripción |

Todos se combinan entre sí. La consulta típica —*todo lo que le pagué a este
proveedor por transferencia este trimestre*— sale con tres filtros.

## Filtro por vendedor: cuánto cobró cada vendedor

El vendedor de un cobro es el **vendedor de la factura o del recibo de venta que
se cobró**, no el vendedor asignado al cliente. Es el mismo criterio de Cuentas
por Cobrar y del Reporte de Ventas por Vendedor, así que los valores cuadran entre
esas pantallas.

- Al elegir un vendedor, el reporte muestra **solo ingresos**: los egresos no
  tienen vendedor. Si además elige *Solo egresos*, el resultado queda vacío.
- Un ingreso que cobra facturas de varios vendedores se reparte: cada línea
  aparece con el vendedor de su propia factura.
- **No entran** los cobros que no cancelan una factura o un recibo con vendedor:
  saldos iniciales, facturas de reembolso, ingresos por otros conceptos y cobros
  de facturas sin vendedor asignado. Tampoco los cobros migrados del sistema
  anterior cuya factura no se migró.
- En la vista **Forma de cobro/pago** el valor es el del cobro completo: si un
  mismo ingreso cobra facturas de dos vendedores, su forma de pago aparece entera
  para cada uno.

## Formas de ver

- **Documento (detalle)**: cada línea de cada comprobante. Es la vista de auditoría.
- **Tercero (resumen)**: agrupado por cliente, proveedor o empleado, para saber
  cuánto se movió con cada uno.
- **Forma de cobro/pago**: cuánto entró y salió por cada forma (efectivo, cada
  banco, tarjeta…).
- **Fecha** y **Mes**: ingresos, egresos y neto por día o por mes.

## Exportar a Excel y PDF

El **Excel** sale con todo el detalle disponible, en varias hojas:

- **Primera hoja**: lo que está viendo en pantalla. Arriba lleva los filtros
  aplicados (periodo, vendedor, tercero…) y los totales de ingresos, egresos y neto.
- **Detalle**: una fila por cada línea de cada comprobante, con número, fecha,
  estado, concepto, tercero e identificación, vendedor, documento cobrado o pagado
  (tipo, número, fecha, valor, saldo anterior y saldo actual), ingreso o egreso en
  columnas separadas, total del comprobante, formas de cobro/pago (con operación
  bancaria, número de cheque y referencia), cuenta contable, observaciones, usuario
  que lo registró y fecha de registro. En la vista Documento esta es la primera hoja.
- **Cobros y pagos**: una fila por cada forma de cobro o pago de los comprobantes,
  con la operación bancaria, número de cheque, referencia, fecha de cobro,
  beneficiario del cheque y los documentos que cancela.

Las fechas del Excel son fechas reales (se pueden ordenar y filtrar como fechas) y
los valores salen con dos decimales. El Excel no tiene tope de filas.

El **PDF** muestra la vista en pantalla, con los filtros aplicados en el encabezado.

## Para qué se usa

Para responder preguntas que ningún otro módulo responde de una vez: cuánto se le
ha pagado en total a un proveedor, cuánto entró por tarjeta el mes pasado, cuánto
cobró cada vendedor, qué movimientos hubo por encima de cierto monto.

## Errores frecuentes

- **Falta un movimiento**: revise las fechas; el reporte usa la fecha del ingreso
  o egreso, no la del documento cobrado o pagado.
- **Los totales no cuadran con el flujo de caja**: compruebe si tiene filtros
  activos que estén acotando el resultado.
- **Al elegir un vendedor no aparecen egresos**: es lo esperado; los egresos no
  tienen vendedor.
- **Falta un cobro de un vendedor**: revise que la factura cobrada tenga ese
  vendedor asignado. El reporte toma el vendedor de la factura, no el del cliente.

## Historial de cambios

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
  forma de pago y operación bancaria (mismo criterio que el asiento contable y Control
  Bancario).
- **1.1** — El detalle exportado a Excel/PDF ya no se corta en 5000 filas; el tope
  de 5000 se mantiene solo para la vista en pantalla.
- **1.0** — Versión inicial.
