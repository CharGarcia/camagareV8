---
titulo: Cuentas por pagar
resumen: Qué le debe la empresa a sus proveedores, con sus vencimientos, y registro del pago.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_pagar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por pagar, cxp, deudas, proveedores, saldo pendiente, vencimiento, pagar, obligaciones, fecha de corte, saldo a una fecha, fecha hasta
version: 1.3
orden: 50
estado: activo
---

**Cuentas por pagar** es el espejo de las cuentas por cobrar: qué facturas de
compra siguen sin pagarse, de qué proveedor y cuándo vencen.

## De dónde sale el saldo

Del conjunto de:

- Las **compras** registradas y no pagadas.
- Los **saldos iniciales** de proveedores cargados al empezar.

Menos lo ya pagado mediante egresos.

El **vencimiento** se calcula con el *plazo* configurado en la ficha del
proveedor. Si un documento vence antes de lo que esperaba, ese es el campo a
revisar.

## Fecha Hasta como fecha de corte

El filtro **Fecha Hasta** no solo limita qué documentos se muestran (los
emitidos hasta esa fecha): también es la **fecha de corte del saldo**. Los
pagos, retenciones y notas de crédito o débito fechados **después** de esa
fecha no se descuentan, así el listado muestra lo que se debía **ese día**.

Ejemplo: una compra pagada el 31 de mayo aparece pendiente, con su saldo
completo, en cualquier consulta con Fecha Hasta igual o anterior al 30 de mayo,
y desaparece de los pendientes a partir del 31.

La regla es la misma que en Cuentas por Cobrar y aplica por igual a las
compras, liquidaciones, importaciones y **saldos iniciales**; las tarjetas
superiores, el gráfico de antigüedad y las exportaciones respetan el corte.
Sin Fecha Hasta, el saldo es el actual.

La fecha que manda para un pago es la **fecha del egreso**. Si el egreso se
generó automáticamente (descarga del SRI o *Generar pagos pendientes*), esa
fecha es la de la compra, aunque el cheque tenga fecha posterior.

## Notas de crédito y débito del proveedor

Las notas de crédito y débito que emite el proveedor **no aparecen como
documentos sueltos** en este listado: se restan (o suman) directamente al saldo
de la factura que modifican. Así el listado muestra lo que realmente se le debe a
cada proveedor, y no tres líneas que hay que compensar mentalmente.

## Registrar el pago

Se registra desde el propio listado. Equivale a crear un egreso: reduce el saldo
del documento, deja constancia de la forma de pago y genera el asiento contable.

También queda disponible el **historial de pagos** de cada documento, útil cuando
una factura se pagó en varias partes.

## Errores frecuentes

- **Un documento aparece vencido antes de tiempo**: revise el *plazo* del
  proveedor.
- **El saldo no coincide con lo que dice el proveedor**: compruebe si hay notas
  de crédito aplicadas a esa factura.
- **Pagué y sigue pendiente**: verifique que el egreso quedó aplicado a ese
  documento y no registrado como concepto general.

## Qué comprobantes de compra aparecen

Aparece como cuenta por pagar todo comprobante de compra que genera una
obligación con el proveedor: la factura, la **nota de venta**, los documentos de
instituciones financieras, las planillas de servicios básicos y los demás tipos
autorizados por el SRI, además de las liquidaciones de compra, importaciones y
saldos iniciales. Una liquidación de compra se muestra desde que se autoriza y
sigue visible cuando pasa a *contabilizado*; solo sale de la lista al anularse o
al quedar pagada. Las compras anuladas o rechazadas no se muestran. No aparecen
como fila las notas de crédito y de débito recibidas: esas ajustan el saldo de
la factura que modifican. Mismo criterio que
el Reporte de Cartera y que el asiento contable de la compra.

## Historial de cambios

- **1.3** — El PDF y el Excel exportados muestran, bajo el encabezado, los
  **filtros aplicados** (tipo de documento, estado, período y proveedor), para
  que quien lo reciba sepa exactamente qué cartera está viendo. En el Excel los
  montos ahora son celdas numéricas con dos decimales y sin separador de miles,
  listas para sumar.
- **1.2** — Se listan y se pueden pagar todos los comprobantes de compra que generan deuda (notas de venta, documentos financieros, planillas, etc.), no solo la factura. Las **liquidaciones de compra ya contabilizadas** vuelven a aparecer (antes desaparecían de la cartera al registrarse su asiento). Las compras anuladas o rechazadas dejan de mostrarse como deuda. También aplica al saldo de la ficha del proveedor y al pago automático a proveedores.
- **1.1** — Los **saldos iniciales** respetan la fecha de corte igual que las
  compras: con Fecha Hasta, un pago posterior a esa fecha ya no descuenta el
  saldo inicial (antes se usaba el acumulado pagado sin importar la fecha).
  Aplica al listado, a las tarjetas y al gráfico de antigüedad.
- **1.0** — Versión inicial.
