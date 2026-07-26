---
titulo: Egresos
resumen: Registro del dinero que sale: pagos a proveedores y empleados, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/egresos
tipo: modulo
visibilidad: todos
etiquetas: egresos, egreso, pago, pagar, dinero que sale, proveedor, empleado, cheque, transferencia, comprobante de egreso
version: 1.0
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
pago del egreso, o en lote desde el listado.

## Asiento contable

Cada egreso genera su asiento automáticamente según la configuración contable de
la empresa; al anularlo, el asiento se anula. En las líneas de concepto general
se puede elegir la cuenta contable línea por línea.

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

- **1.0** — Versión inicial.
