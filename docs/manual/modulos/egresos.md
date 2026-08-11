---
titulo: Egresos
resumen: Registro del dinero que sale: pagos a proveedores y empleados, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/egresos
tipo: modulo
visibilidad: todos
etiquetas: egresos, egreso, pago, pagar, dinero que sale, proveedor, empleado, cheque, transferencia, comprobante de egreso, excel, exportar, anular cheque, cheque anulado, cheque dañado, reimprimir cheque
version: 1.3
orden: 20
estado: activo
---

El módulo de **Egresos** registra todo el dinero que sale de la empresa: el pago
a un proveedor, a un empleado o cualquier otro desembolso. Es el reflejo exacto
de Ingresos, y funciona igual.

## Las tres partes de un egreso

Como en los ingresos, un egreso tiene tres partes que **deben cuadrar entre sí**:

1. **Cabecera**: fecha, número, tipo de egreso y a quién se le paga.
2. **Detalle**: qué se está pagando (documentos pendientes o conceptos).
3. **Formas de pago**: cómo salió el dinero (efectivo, transferencia, cheque…).

El total del detalle y el total pagado deben coincidir. Si no, el sistema muestra
ambas cifras y no deja guardar.

## A quién se le paga

Todo egreso necesita un **tipo de sujeto**:

- **Proveedor**: hay que elegir el proveedor.
- **Empleado**: hay que elegir el empleado.

Según lo que elija, el formulario pide uno u otro. No se puede dejar sin
seleccionar.

## Cómo se registra un pago

1. Pulse **Nuevo**.
2. Revise la fecha y el número (se genera automáticamente).
3. Elija el tipo de egreso y el sujeto: proveedor o empleado.
4. En el detalle, busque sus **documentos pendientes** y marque los que está
   pagando, total o parcialmente.
5. En formas de pago, indique cómo salió el dinero. Puede combinar varias.
6. Guarde.

## Reglas que aplica el sistema

| Regla | Qué significa |
|-------|---------------|
| La fecha no puede ser futura | No se registran pagos con fecha posterior a hoy |
| El monto no puede superar el saldo pendiente | En cada línea, no se paga más de lo que se debe. El aviso indica la línea y ambos montos |
| Al menos una línea de detalle | Un egreso vacío no se guarda |
| Al menos una forma de pago | Hay que declarar por dónde salió el dinero |
| Todos los montos mayores a cero | Ni líneas ni pagos en cero |
| El detalle debe cuadrar con lo pagado | Ambos totales tienen que ser iguales |

## El periodo contable manda

No se puede **registrar ni anular** un egreso si su periodo contable está
cerrado. Para corregir algo de un periodo cerrado hay que reabrirlo desde
Periodos Contables, con el criterio del contador, o registrar el ajuste en un
periodo abierto.

## Anular, no eliminar

Un egreso se **anula**, no se borra. Al anularlo se libera el saldo de los
documentos que había pagado y se anula su asiento contable.

## Cheques

Cuando el pago sale por cheque se puede registrar su **fecha de cobro**, para
saber cuándo se hizo efectivo. Los cheques se imprimen desde la propia fila de
pago del egreso, o en lote desde el listado (botón **Imprimir cheques**), tanto
a PDF (descarga) como directo a la impresora (abre el diálogo de impresión del
navegador). Cada impresión queda registrada (control anti-reimpresión): si un
cheque ya se imprimió, el sistema avisa y pide confirmar antes de reimprimirlo.

### Anular un cheque

Si un cheque se dañó al imprimir, se emitió con datos equivocados o por
cualquier motivo no se va a usar, se puede **anular** desde el icono
<i class="bi bi-ban"></i> junto a su fila, en la pestaña **Formas de Pago** del
egreso. El sistema pide el **motivo** de la anulación.

Anular un cheque **no anula el egreso**: el documento sigue vigente, solo se
anula ese cheque puntual. El cheque anulado:

- Queda visible como historial (tachado, con motivo y fecha) en una tabla
  aparte, **"Cheques anulados"**, debajo de las formas de pago activas — nunca
  se borra.
- Deja de contarse en el total pagado: si el egreso queda sin cobertura por esa
  diferencia, el total de formas de pago se marca en rojo hasta que se agregue
  otra forma de pago (u otro cheque) por el mismo valor, en la misma pantalla.
- Su número **no se reutiliza**: el siguiente cheque autogenerado sigue la
  secuencia normal, saltándose el anulado.
- Deja de aparecer en Control Bancario y en el listado de "Cheques por
  imprimir"; si ya se había impreso, tampoco se puede volver a imprimir.

No se puede anular un cheque si:

- El egreso ya está anulado.
- El cheque ya fue reportado como **cobrado** (conciliado en Control
  Bancario) — a esa altura ya no es una anulación, es un ajuste bancario.
- El periodo contable del egreso está cerrado.

### Configurar impresión por banco

Cada banco tiene su propio formato de cheque preimpreso (posición del
beneficiario, el monto, la fecha, etc.). Junto al botón de imprimir cheque (fila
individual) y en el modal de impresión en lote (junto al selector "Cuenta /
Banco") hay un icono **engranaje** ("Configurar impresión"): abre el diseñador
visual del módulo **Plantillas de Documentos**, ya listo para el banco de esa
cuenta. Si el banco no tiene todavía una plantilla propia, el sistema crea una
automáticamente (hoja A4 vertical, con los campos en una posición inicial) y la
activa; desde ahí se arrastran los campos a su posición exacta.

Los ajustes que se guarden ahí **aplican a todos los cheques de ese banco**, sin
afectar a los de otros bancos. Ver también
[Plantillas de Documentos](modulos/plantillas-pdf).

## Asiento contable

Cada egreso genera su asiento automáticamente según la configuración contable de
la empresa; al anularlo, el asiento se anula. En las líneas de concepto general
se puede elegir la cuenta contable línea por línea.

## Comprobante en PDF y Excel

Al abrir un egreso ya guardado, la barra de acciones superior del modal
muestra el botón **PDF** (comprobante de egreso) y, junto a él, el botón
**Excel**: descarga el mismo comprobante (cabecera, documentos pagados y
formas de pago) en un archivo `.xlsx`. Ambos botones quedan ocultos mientras
el egreso es nuevo y no se ha guardado.

## Permisos

Con **acceso total** se ven los egresos de toda la empresa; sin él, cada usuario
ve solo los que registró.

## Errores frecuentes

- **"El total detallado no coincide con el total pagado"**: revise ambas
  columnas; el mensaje muestra las dos cifras.
- **"El monto a pagar no puede superar el saldo pendiente"**: está pagando de más
  en esa línea; el aviso indica cuál y cuánto se debe realmente.
- **"La fecha de emisión no puede ser posterior a la fecha actual"**: corrija la
  fecha.
- **"Debe seleccionar el Proveedor / el Empleado"**: falta el sujeto del pago.
- **"El periodo contable está cerrado"**: la fecha cae en un mes ya cerrado.
- **No encuentro la factura a pagar**: compruebe que esté a nombre de ese
  proveedor, que no esté ya pagada y que la compra no fuera anulada.

## Historial de cambios

- **1.3** — Anular un cheque puntual sin anular el egreso: queda como
  historial visible, deja de contarse en el total y se puede cubrir con otra
  forma de pago desde el mismo modal.
- **1.2** — Botón para exportar el comprobante a Excel, junto al de PDF, en la
  barra de acciones superior del modal.
- **1.1** — Configurar impresión de cheque por banco desde la fila de pago y el
  modal de impresión en lote (abre el diseñador visual, crea la plantilla del
  banco si no existe).
- **1.0** — Versión inicial.
