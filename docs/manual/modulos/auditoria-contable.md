---
titulo: Auditoría contable
resumen: Verifica que cada documento tenga su asiento correcto y permite corregirlos y regenerarlos en masa.
categoria: Contabilidad
ruta_modulo: modulos/auditoria_contable
tipo: modulo
visibilidad: admin
etiquetas: auditoria contable, revisar asientos, documentos sin asiento, descuadres, regenerar contabilidad, hallazgos, monto no coincide, cartera del asiento, diferencia asiento documento, ingresos diferidos, devengo de suscripciones, meses sin devengar, cuadre con el mayor
version: 1.3
orden: 70
estado: activo
---

La **auditoría contable** revisa toda la contabilidad de la empresa y compara
cada documento con su asiento: busca documentos sin asiento, asientos
descuadrados, asientos de documentos anulados y otras inconsistencias.

Es la herramienta a la que hay que ir cuando el balance no cuadra y no se sabe
por dónde empezar.

## Cómo se usa

1. Ejecute la revisión sobre el periodo que le interese.
2. Revise los **hallazgos** agrupados por tipo.
3. Corrija los que correspondan, en masa o uno a uno.

## Monto no coincide

El hallazgo **Monto no coincide** compara el total del documento con su asiento:

- En **facturas y recibos de venta, compras y liquidaciones** se compara con la
  **Cuenta por Cobrar o por Pagar** del asiento (las cuentas configuradas para
  eso en Configuración Contable), no con el total del Debe. El Debe de una venta
  incluye además el Costo de Ventas y los descuentos, así que no sirve para
  comparar. Es el mismo criterio que muestra la pestaña *Asiento contable* del
  documento ("Cartera del asiento: … · diferencia: …").
- Si el asiento no usa ninguna cuenta de cartera configurada, o el documento no
  tiene cuenta de cartera, se compara con el total del Debe.
- Diferencias de hasta 3 centavos son redondeo y no se reportan.

Si aparece, las causas habituales son: el documento se modificó después y su
asiento no se actualizó, el asiento se editó a mano, o la Cuenta por Cobrar/Pagar
que usa el asiento ya no es la configurada hoy.

## Devengo de suscripción

Revisa el ingreso diferido de las [suscripciones](modulos/suscripciones) que reconocen
el ingreso durante el período (NIIF 15). Hay tres casos:

- **Factura o recibo cuyo asiento no refleja su cronograma**: lo que el asiento
  acredita a *Ingresos diferidos* (o a *Ingresos devengados por facturar*) no es lo que
  dice el cronograma. Pasa si el asiento se generó antes que el cronograma, si se
  cambió la cuenta o si se editó a mano. Se corrige con el botón **Regenerar** de la fila.
- **Meses ya cumplidos sin devengar** (sin asiento en la fila): el devengo automático
  diario no pudo hacerlos, casi siempre porque falta la cuenta de Ingresos diferidos o el
  período está cerrado. Corrija eso; el devengo los toma al día siguiente, o en el momento
  desde el Reporte de Ingresos Diferidos → **Devengo del mes**.
- **Cuadre con el mayor** (origen *Devengo de suscripciones*): al cierre del mes, el
  saldo del cronograma no coincide con el del mayor en la cuenta de ingresos diferidos o
  en la de por facturar. Suele deberse a asientos manuales en esa cuenta. El detalle está
  en el [Reporte de Ingresos Diferidos](modulos/reporte_ingresos_diferidos).

Al corregir, la siguiente revisión los marca como resueltos. Requiere el SQL
`database/migrations/20261004_auditoria_tipo_devengo_suscripcion.sql`; sin él, esta
revisión simplemente no se hace.

## Regenerar toda la contabilidad

Existe la opción de **regenerar toda la contabilidad** por lotes: borra y vuelve
a crear los asientos automáticos a partir de los documentos.

Dos advertencias importantes:

- **Nunca toca los asientos de tipo Diario.** Los asientos que escribió el
  contador a mano se respetan siempre.
- Es una operación pesada. Ejecútela con la contabilidad ya revisada y fuera del
  horario de trabajo si la empresa tiene mucho volumen.

## Documentos migrados

Los documentos que vinieron de otro sistema **no generan asiento propio**: su
contabilidad ya vino migrada. La auditoría los excluye a propósito, así que no
aparecerán como "documentos sin asiento".

## Errores frecuentes

- **Muchos documentos sin asiento**: normalmente hay configuración contable
  faltante para ese tipo de documento.
- **Un asiento descuadrado por centavos**: el sistema absorbe los redondeos; un
  descuadre mayor indica un problema real en el documento.

## Historial de cambios

- **1.3** — Nuevo hallazgo **Devengo de suscripción**: asiento vs cronograma, meses cumplidos sin devengar y cuadre con el mayor de las cuentas de ingresos diferidos.

- **1.2** — *Monto no coincide* en ventas, recibos, compras y liquidaciones compara la Cuenta por Cobrar o por Pagar del asiento, no el Debe total: antes marcaba como error toda factura con costo de ventas.
- **1.1** — **Regenerar** ya no toca los documentos cuya contabilidad vino de la migración aunque el documento haya existido antes de migrar (la migración solo lo enlazó a su asiento histórico): se avisa que su contabilidad es la del histórico migrado. Al anular asientos duplicados o regenerar, nunca se suelta el enlace del documento con su asiento migrado.
- **1.0** — Versión inicial.
