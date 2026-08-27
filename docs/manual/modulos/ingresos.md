---
titulo: Ingresos
resumen: Registro del dinero que entra: cobros de facturas, anticipos y otros ingresos, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/ingresos
tipo: modulo
visibilidad: todos
etiquetas: ingresos, cobro, cobrar, recibo, dinero que entra, anticipo, deposito, efectivo, transferencia, caja, excel, exportar, combinar conceptos, mezclar conceptos, otros conceptos, varios documentos, cobro sin factura, tipo real, tipo de ingreso, numero de ingreso, serie, secuencial, numero repetido, numero duplicado
version: 1.5
orden: 10
estado: activo
---

El módulo de **Ingresos** registra todo el dinero que entra a la empresa: el
cobro de una factura, un anticipo de cliente o cualquier otro ingreso. Cada
ingreso genera su asiento contable y, cuando cobra documentos, reduce las cuentas
por cobrar.

## Las tres partes de un ingreso

Un ingreso siempre tiene tres partes y **las tres deben cuadrar entre sí**:

1. **Cabecera**: fecha, secuencial, tipo de ingreso y de quién se recibe.
2. **Detalle**: qué se está cobrando (documentos pendientes o conceptos libres).
3. **Formas de cobro**: cómo entró el dinero (efectivo, transferencia, cheque…).

La suma del detalle y la suma de las formas de cobro deben ser **iguales al total
del ingreso**. Si no cuadran, el sistema no deja guardar y dice exactamente qué
suma no coincide.

## Cómo se registra un cobro

1. Pulse **Nuevo**.
2. Revise la fecha y el secuencial (se propone el siguiente).
3. Elija el **tipo de ingreso** y complete **Recibo de** (de quién viene el dinero).
4. En el detalle, busque los **documentos pendientes** del cliente y marque los
   que está cobrando, total o parcialmente.
5. En formas de cobro, indique cómo entró el dinero. Puede combinar varias.
6. Guarde.

Si es un ingreso que no cobra ninguna factura, elija el **concepto** que
corresponda en lugar de documentos pendientes.

## Combinar varios conceptos en un mismo ingreso

Un ingreso puede cobrar **a la vez** una factura de venta, un recibo de venta
y/o "Otros conceptos" (dinero recibido sin documento) — no hace falta un
ingreso separado por cada tipo. Los botones de concepto de la barra superior
ya no son excluyentes: cada uno **agrega** documentos a lo ya cargado, en vez
de reemplazarlo.

- **"Documentos"** y **"Otros conceptos"** se muestran siempre juntos: use el
  buscador de documentos pendientes para las facturas/recibos, y el botón
  **"+ Agregar línea"** de "Otros conceptos" para el resto.
- **Cuenta contable obligatoria por línea manual cuando se mezcla**: si el
  ingreso combina un documento de módulo con líneas de "Otros conceptos", cada
  línea manual debe traer su propia cuenta contable (buscador integrado en esa
  misma cuadrícula). Sin eso, el sistema no sabe si ese ingreso es parte de la
  cartera del documento (p. ej. Cuentas por Cobrar de la factura) o una cuenta
  totalmente distinta, así que lo exige explícito antes de guardar.
- El total del ingreso es la suma de **ambos bloques**.

### Botones que solo aparecen si hay algo que cobrar

Los botones de concepto ligados a un documento (**Factura de venta**,
**Recibo de venta**, **Factura de reembolso**) solo se muestran si la empresa
tiene al menos un documento pendiente de ese tipo. Si no hay ninguna factura,
recibo o reembolso pendiente, el botón correspondiente no aparece.

Los demás conceptos (los que no dependen de buscar un documento, como
**Anticipo Cliente**, o cualquiera del desplegable "Otro concepto…")
**siempre se muestran**, sin importar si hay pendientes o no.

### La columna "Tipo" del listado muestra el tipo real, no el botón usado

La columna **Tipo** del listado muestra el tipo **real** de lo que
efectivamente se cobró (Factura de Venta, Recibo de Venta, Factura de
Reembolso), calculado a partir de los documentos del detalle — no el botón de
concepto que se usó para armarlo. Si el ingreso combina más de un tipo (ver
"Combinar varios conceptos" arriba), la columna los junta con `+`. Si es un
concepto sin documento (Anticipo Cliente, Préstamos…), muestra directamente el
nombre del concepto elegido.

## Campos obligatorios

| Campo | Regla |
|-------|-------|
| Fecha de emisión | Obligatoria |
| Secuencial | Obligatorio |
| Tipo de ingreso | Obligatorio |
| Recibo de | Obligatorio |
| Concepto | Obligatorio cuando es "otros ingresos" |
| Detalle | Al menos una línea, con monto mayor a cero |
| Formas de cobro | Al menos una, con monto mayor a cero |
| Total | Mayor a cero |

## El periodo contable manda

No se puede **registrar, modificar ni anular** un ingreso si su periodo contable
está cerrado. El sistema lo comprueba en las tres operaciones, y en la
modificación valida tanto el periodo original como el nuevo si se cambia la
fecha.

Si necesita corregir un ingreso de un periodo cerrado, hay que reabrir el periodo
desde Periodos Contables (con el criterio del contador) o registrar el ajuste en
un periodo abierto.

## Anular, no eliminar

Un ingreso registrado **se anula**, no se borra. Al anularlo:

- Se libera el saldo de los documentos que había cobrado.
- Se anula su asiento contable.

**Caso especial — pagos con tarjeta**: si el ingreso vino de un cobro con
tarjeta, no se puede anular desde aquí. Primero hay que **reversar el pago desde
la factura, en la pestaña Pagos**; al hacerlo, el ingreso se anula solo. El
sistema lo avisa con ese mensaje si lo intenta al revés.

## El número del documento

Cada documento lleva una **serie** (establecimiento y punto de emisión, por
ejemplo `001-101`) y un **secuencial**, que juntos forman el Nº de documento:
`001-101-000000123`.

El número que se ve al abrir el formulario es solo una **vista previa**: el
definitivo lo asigna el sistema en el momento de guardar, tomando el siguiente
libre de esa serie. Por eso, si dos personas abren el formulario a la vez, el
segundo en guardar recibe el número siguiente y no el mismo — el documento no
se pierde ni se rechaza, simplemente sale con el número que le toca.

Si se elimina un documento, su número queda libre y el sistema lo vuelve a
ofrecer al siguiente que se cree en esa serie, para que la numeración no
quede con saltos.

## Asiento contable

Cada ingreso genera su asiento automáticamente según la configuración contable de
la empresa. Al modificarlo, el asiento se regenera; al anularlo, se anula.

En las líneas de concepto general se puede elegir la cuenta contable por línea,
cuando el concepto no tiene una cuenta fija.

## Comprobante en PDF y Excel

Al abrir un ingreso ya guardado, la barra de acciones superior del modal
muestra el botón **PDF** (comprobante de ingreso) y, junto a él, el botón
**Excel**: descarga el mismo comprobante (cabecera, documentos cobrados y
formas de cobro) en un archivo `.xlsx`. Ambos botones quedan ocultos mientras
el ingreso es nuevo y no se ha guardado.

## Permisos

Con **acceso total** se ven los ingresos de toda la empresa; sin él, cada usuario
ve solo los que registró. En una caja con varios turnos esto suele ser lo
deseable; para el contador o el administrador, active el acceso total.

## Errores frecuentes

- **"La suma de los detalles no coincide con el total"**: revise las líneas del
  detalle; suele faltar un documento o sobrar un centavo por redondeo.
- **"La suma de las formas de cobro no coincide con el total"**: el dinero
  declarado no llega al total cobrado. Ajuste los montos por forma de pago.
- **"El periodo contable está cerrado"**: la fecha cae en un mes ya cerrado.
- **"Debes reversar el pago con tarjeta primero"**: vaya a la factura, pestaña
  Pagos, y reverse ahí.
- **No encuentro la factura a cobrar**: compruebe que está a nombre de ese
  cliente, que no está ya cobrada y que no fue anulada.

## Historial de cambios

- **1.6** — Corregido un caso en que el número seguía repitiéndose pese a la
  corrección anterior: los ingresos creados **automáticamente** (cobro con
  tarjeta al facturar, cobro de suscripciones) guardaban el secuencial sin los
  ceros a la izquierda, y la validación que impide repetir un número compara el
  texto, así que `16` y `000000016` pasaban como si fueran distintos. Ahora el
  formato lo fija el sistema al escribir, venga el ingreso del formulario o de
  un cobro automático.
- **1.5** — Se documenta cómo se asigna el **número del documento**: la serie
  y el secuencial definitivos los pone el sistema al guardar, no al abrir el
  formulario, así que dos ingresos creados a la vez ya no pueden salir con el
  mismo número.

- **1.4** — La columna "Tipo" del listado muestra el tipo real del documento
  (Factura de Venta, Recibo de Venta, Factura de Reembolso) en vez de repetir
  siempre el `tipo_ingreso` de cabecera, que podía no coincidir con lo
  realmente cobrado.
- **1.3** — Los botones de concepto ligados a documento (Factura de venta,
  Recibo de venta, Factura de reembolso) solo se muestran si hay algún
  pendiente de ese tipo en la empresa. Se quitó el botón "Agregar documentos"
  (redundante con los botones de concepto de la barra superior).
- **1.2** — Los conceptos del ingreso (Factura de venta, Recibo de venta, Otros
  conceptos...) dejan de ser excluyentes: se pueden combinar en un mismo
  ingreso (p. ej. el cobro de una factura + un ingreso sin documento). Exige
  cuenta contable explícita en las líneas manuales cuando se mezclan con un
  documento de módulo.
- **1.1** — Botón para exportar el comprobante a Excel, junto al de PDF, en la
  barra de acciones superior del modal.
- **1.0** — Versión inicial.
