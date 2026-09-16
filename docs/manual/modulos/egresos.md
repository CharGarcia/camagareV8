---
titulo: Egresos
resumen: Registro del dinero que sale: pagos a proveedores y empleados, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/egresos
tipo: modulo
visibilidad: todos
etiquetas: egresos, egreso, pago, buscar egreso, buscador, filtros, filtrar egresos, buscar cheque, buscar por compra pagada, buscar por beneficiario, filtro de fechas, chips, editar egreso, modificar egreso, corregir egreso, cambiar monto pagado, quitar factura del egreso, cambiar beneficiario, periodo cerrado, solo lectura, no deja editar, no puedo modificar, ordenar por dos columnas, ordenar por beneficiario y fecha, pagar, dinero que sale, proveedor, empleado, cheque, transferencia, comprobante de egreso, excel, exportar, anular cheque, cheque anulado, cheque dañado, reimprimir cheque, combinar conceptos, mezclar conceptos, otros conceptos, varios documentos, gasto sin factura, tipo real, tipo de egreso, decimo cuarto, decimo tercero, prestamos, rol de pago, numero de egreso, serie, secuencial, numero repetido, numero duplicado, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion
version: 1.14
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

## Combinar varios conceptos en un mismo egreso

Un egreso puede pagar **a la vez** una factura de compra, una liquidación,
roles pendientes y/o "Otros conceptos" (gastos sin documento, como un pago que
no tiene factura de compra que lo respalde) — no hace falta un egreso separado
por cada tipo. Los botones de concepto de la barra superior ya no son
excluyentes: cada uno **agrega** documentos a lo ya cargado, en vez de
reemplazarlo.

- **"Documentos"** y **"Otros conceptos"** se muestran siempre juntos: use el
  buscador de documentos pendientes para las facturas/liquidaciones/roles, y
  el botón **"+ Agregar línea"** de "Otros conceptos" para el resto.
- El **beneficiario es único por egreso** (un solo Proveedor o un solo
  Empleado, nunca ambos): si el concepto que elige implica un beneficiario
  distinto al ya usado (p. ej. pasar de un concepto de Proveedor a uno de
  Nómina), el sistema avisa que se perderá lo ya cargado antes de continuar.
- **Cuenta contable obligatoria por línea manual cuando se mezcla**: si el
  egreso combina un documento de módulo con líneas de "Otros conceptos", cada
  línea manual debe traer su propia cuenta contable (buscador integrado en esa
  misma cuadrícula). Sin eso, el sistema no sabe si ese gasto es parte de la
  cartera del documento (p. ej. Cuentas por Pagar de la compra) o una cuenta
  totalmente distinta, así que lo exige explícito antes de guardar.
- El total del egreso es la suma de **ambos bloques**.

### Botones que solo aparecen si hay algo que pagar

Los botones de concepto ligados a un documento (**Compra**, **Liquidación**,
**Nómina**) solo se muestran si la empresa tiene al menos un documento
pendiente de ese tipo. Si no hay ninguna factura de compra, liquidación o rol
pendiente, el botón correspondiente no aparece — no tendría nada que buscar.

Los demás conceptos (los que no dependen de buscar un documento, como
**Anticipo Proveedor**, o cualquiera del desplegable "Otro concepto…": SRI,
IESS, etc.) **siempre se muestran**, sin importar si hay pendientes o no.

### La columna "Tipo" del listado muestra el tipo real, no el botón usado

La columna **Tipo** del listado no repite el nombre del botón de concepto que
se usó para armar el egreso (eso solo decide qué buscador se abre) — muestra
el tipo **real** de lo que efectivamente se pagó, calculado a partir de los
documentos del detalle: **Compra**, **Liquidación**, **Rol de Pago**,
**Décimo Cuarto**, **Décimo Tercero**, **Préstamo Quirografario/Hipotecario/
Empresa**, **Anticipo Empleado**. El botón **Nómina**, por ejemplo, busca
indistintamente rol, décimos, préstamos y anticipos de empleado — pero cada
egreso que resulte de eso muestra en el listado cuál de esos fue realmente.

Si el egreso combina más de un tipo (ver "Combinar varios conceptos" arriba),
la columna los junta con `+` (p. ej. "Compra + Otros Conceptos"). Si es un
concepto sin documento (Anticipo Proveedor, SRI, IESS…), muestra directamente
el nombre del concepto elegido.

## Reglas que aplica el sistema

| Regla | Qué significa |
|-------|---------------|
| La fecha no puede ser futura | No se registran pagos con fecha posterior a hoy |
| El monto no puede superar el saldo pendiente | En cada línea, no se paga más de lo que se debe. El aviso indica la línea y ambos montos |
| Al menos una línea de detalle | Un egreso vacío no se guarda |
| Al menos una forma de pago | Hay que declarar por dónde salió el dinero |
| Todos los montos mayores a cero | Ni líneas ni pagos en cero |
| El detalle debe cuadrar con lo pagado | Ambos totales tienen que ser iguales |

## Editar un egreso ya guardado

Al abrir un egreso desde el listado, el modal se titula **Editar Egreso** y
permite corregir prácticamente todo, sea cual sea su tipo (pago de compra,
liquidación, nómina, anticipo o gasto general):

- La **fecha de emisión**.
- El **beneficiario** (tipo Proveedor/Empleado y la persona).
- Las **observaciones**.
- Los **documentos pagados**: quitar uno, cambiar el monto pagado o agregar
  otro documento pendiente del mismo tipo (el botón del concepto activo en la
  barra superior vuelve a abrir el buscador). Al editar, el buscador y los
  saldos ya descuentan lo que este mismo egreso pagaba, así que el saldo
  disponible es el real.
- Las líneas de **Otros conceptos**, con su cuenta contable.
- Las **formas de pago** (los cheques ya anulados se conservan como historial).

Lo único que no cambia es la **identidad del documento**: la serie, el
secuencial y el concepto de cabecera (el que define el tipo del egreso y su
asiento). Si el concepto está mal, anule el egreso y registre uno nuevo.

Al pulsar **Actualizar** el sistema aplica las mismas reglas que al registrar
(cuadre, montos, cuenta contable por línea cuando se mezclan conceptos), vuelve
a comprobar el saldo real de cada documento (por si otro egreso lo pagó
mientras tanto) y el periodo contable, regenera el asiento contable y, si el
egreso paga nómina (roles semanales/quincenas, anticipos o préstamos),
resincroniza el rol afectado con lo realmente pagado. El cambio queda en el
historial de auditoría con los datos anteriores y los nuevos.

## El periodo contable manda

No se puede **registrar, modificar ni anular** un egreso si su periodo contable
está cerrado. En la modificación se valida tanto el periodo original como el
nuevo si se cambia la fecha.

Cuando el periodo de la fecha del egreso ya está cerrado, el modal **abre en
solo lectura**: muestra la etiqueta **PERIODO CERRADO** junto al número y un
aviso amarillo con el motivo, todos los campos quedan bloqueados y no aparecen
los botones **Actualizar** ni **Anular** (tampoco los de editar o anular un
cheque). Así el bloqueo se ve al abrir, y no recién al intentar guardar.

Para corregir algo de un periodo cerrado hay que reabrirlo desde Periodos
Contables, con el criterio del contador, o registrar el ajuste en un periodo
abierto.

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

### Cómo saber si un cheque ya se cobró

La fecha que se captura en el egreso es la **fecha girada en el cheque** (la
posfechada); no significa que el banco ya lo haya pagado. El cheque cuenta como
**cobrado** cuando en [Control bancario](control-bancario.md) se le registró la
**Fecha Banco**, es decir, la fecha real en que el banco lo hizo efectivo.

Dónde verlo:

- **En el egreso**: la fila del pago siempre dice en qué estado está el cheque.
  - **✔ Cobrado el DD-MM-AAAA** (verde): el banco ya lo hizo efectivo en esa
    fecha. A partir de ahí desaparecen los botones de editar fecha, cambiar el
    nombre del beneficiario y anular el cheque.
  - **⏳ No cobrado** (ámbar): sigue en circulación, todavía se puede corregir
    o anular.
- **En Control bancario**: la columna **Fecha Banco** del movimiento, y en el
  reporte de Conciliación las secciones *Cheques emitidos en circulación (no
  cobrados)* y *Cheques cobrados por el banco en el período*.

Esto funciona igual en empresas que **no llevan contabilidad**: la marca de
cobrado se reconoce tanto si la conciliación quedó anclada al asiento como si
quedó anclada directamente al pago (cuentas sin cuenta contable).

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

### Numeración por fecha de emisión

Por defecto el número es un **correlativo corrido** que nunca se reinicia
(`000000017`). En **Empresa → Secuenciales** se puede configurar, para este tipo
de documento y por cada punto de emisión, que el correlativo **vuelva a empezar
en cada periodo** según la **fecha de emisión** del documento:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Cuando ese modo está activo, **al cambiar la fecha del documento su número se
recalcula solo**, para que caiga en el periodo correcto. Una vez guardado, el
número queda fijo aunque después se le cambie la fecha. Los documentos ya
emitidos conservan siempre el número que tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y
cuántos documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Asiento contable

Cada egreso genera su asiento automáticamente según la configuración contable de
la empresa; al anularlo, el asiento se anula. En las líneas de concepto general
se puede elegir la cuenta contable línea por línea.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del egreso y en sus datos
relacionados: número, serie, secuencial, fecha, beneficiario (proveedor,
empleado u otro), RUC o cédula del beneficiario, observaciones, monto, usuario
que lo registró y los **números de los documentos pagados** (compras,
liquidaciones, roles…). Las columnas **Tipo** y **Estado** no entran en la
búsqueda libre: para filtrar por ellas use la ventana de filtros. Puede escribir
varias palabras en cualquier orden y no importan mayúsculas ni tildes. Para
limpiar, borre el texto o pulse Escape en el cuadro. Mientras busca, aparece un
**círculo girando** al final del cuadro y la tabla se ve atenuada; cuando
desaparece, el listado ya muestra el resultado.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Egreso** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado, tipo de egreso, serie, Nº de egreso, secuencial, monto (mínimo y máximo), concepto de egreso, con o sin asiento contable, usuario que registró |
| Beneficiario | Tipo de beneficiario (proveedor, empleado u otro), beneficiario, RUC / cédula, observaciones |

El selector *Tipo de egreso* lista solo los tipos que la empresa ya usó.

**Pestaña Detalles** (lo que hay dentro del egreso). Es un único cuadro,
**Buscar libremente dentro de los egresos**: escriba un número de compra o de
rol, una descripción, una cuenta contable, una forma de pago, una referencia,
un número de cheque, el beneficiario del cheque o un monto, y aparece la lista
de **cada documento o pago que coincide** con el egreso al que pertenece
(número, fecha, beneficiario y estado). Un clic en la fila deja el listado
mostrando solo ese egreso; el ícono de la derecha lo abre directamente. Por
ejemplo, *cheque 22* muestra en qué egreso se giró ese cheque.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** de la etiqueta quita solo ese filtro, y con el cuadro vacío
la tecla **Retroceso** quita el último. Pulsar la etiqueta vuelve a abrir la
ventana. El embudo muestra cuántos hay activos.

La búsqueda libre y los filtros se combinan entre sí, y los botones **PDF** y
**Excel** del listado exportan exactamente lo que se ve.

## Ordenar el listado

Pulse el título de una columna para ordenar por ella y vuelva a pulsarlo para
invertir el sentido. De fábrica el listado muestra **lo más reciente primero**.

Puede **encadenar hasta tres columnas**: mantenga presionada la tecla **Shift**
(⇧) y pulse el título de la segunda. Usos típicos en pagos:

| Para ver… | Ordene así |
|-----------|-----------|
| Todo lo pagado a cada beneficiario, en orden de fecha | *Beneficiario*, luego Shift+clic en *Fecha* |
| Los pagos más altos dentro de cada tipo | *Tipo*, luego Shift+clic en *Monto* |
| Lo anulado y lo vigente por separado, por fecha | *Estado*, luego Shift+clic en *Fecha* |

El número pequeño junto a cada flecha indica qué columna manda (`1`) y cuál
desempata (`2`). Un tercer Shift+clic sobre la misma columna la saca del orden, y
un clic normal en cualquier encabezado vuelve a dejar una sola.

La columna **Beneficiario** se ordena por lo que se ve, sea proveedor, empleado o
un beneficiario escrito a mano.

El orden se guarda para usted y los botones de PDF y Excel del listado exportan
con ese mismo orden. Detalles en *Cómo ordenar los listados*.

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

- **1.14** — Corregido: sin **acceso total**, el listado mostraba los egresos de
  toda la empresa, aunque esta guía (sección *Permisos*) ya decía que cada usuario
  ve solo los que registró. Ahora el listado, la búsqueda y la exportación a PDF
  y Excel muestran únicamente los egresos creados por el usuario. Con acceso total
  (y el superadministrador) se sigue viendo toda la empresa.
- **1.13** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas del egreso (incluidos el RUC del
  beneficiario, el usuario y los números de los documentos pagados), salvo Tipo
  y Estado. Los filtros pasan a una **ventana propia** (botón del embudo, se
  aplican con *Aplicar*) con dos pestañas: **Egreso** (filtros por campo, con
  criterios nuevos: tipo de egreso como lista, concepto, con/sin asiento,
  usuario, tipo de beneficiario, beneficiario y RUC) y **Detalles**, un cuadro
  de **búsqueda libre dentro de los egresos** (documentos pagados y formas de
  pago, incluidos cheques) que dice a qué egreso pertenece cada coincidencia.
  Corregido el filtro de estado, que ofrecía "Aprobado", un valor que los
  egresos no usan. Nueva sección *Buscar y filtrar el listado*.
- **1.12** — Al abrir un egreso guardado con una **serie que ya no se usa para
  emitir** (por ejemplo, migrados con `001-001` cuando la empresa ya trabaja con
  `001-101`, o un punto de emisión desactivado), el campo *Serie* mostraba en
  blanco; ahora muestra la serie original del documento.
- **1.11** — **Edición completa** de un egreso guardado, sea cual sea su tipo:
  además de fecha y formas de pago, ahora se pueden quitar o agregar documentos
  pagados, cambiar sus montos, el beneficiario, las observaciones y las líneas
  de "Otros conceptos" (antes, los egresos ligados a Compra, Liquidación o
  Nómina solo permitían tocar la fecha y las formas de pago). La única puerta
  es el **periodo contable**: si está cerrado, el modal abre en **solo lectura**
  con la etiqueta *PERIODO CERRADO* y un aviso del motivo, sin botones
  Actualizar ni Anular. Nueva sección *Editar un egreso ya guardado*.
- **1.10** — El listado se puede **ordenar por hasta tres columnas a la vez**:
  Shift+clic en el título de la segunda columna la encadena a la primera (por
  ejemplo *Beneficiario* y, dentro de cada uno, la *Fecha*). Cada encabezado
  activo muestra un número con su prioridad. El orden se guarda por usuario y se
  respeta al exportar a PDF y Excel. Nueva sección *Ordenar el listado*.
- **1.9** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.9** — Corregido un caso en que el número seguía repitiéndose pese a la
  corrección anterior: los egresos creados **automáticamente** (pago desde una
  compra, pago de declaraciones) guardaban el secuencial sin los ceros a la
  izquierda, y la validación que impide repetir un número compara el texto, así
  que `16` y `000000016` pasaban como si fueran distintos. Ahora el formato lo
  fija el sistema al escribir, venga el egreso del formulario o de un pago
  automático.
- **1.8** — Se documenta cómo se asigna el **número del documento**: la serie
  y el secuencial definitivos los pone el sistema al guardar, no al abrir el
  formulario, así que dos egresos creados a la vez ya no pueden salir con el
  mismo número.

- **1.7** — La fila del pago con cheque ahora indica siempre su estado:
  "Cobrado el DD-MM-AAAA" (con la fecha real del banco) o "No cobrado". Además,
  ese estado ya se reconoce en empresas sin contabilidad: antes nunca aparecía
  en cuentas bancarias sin cuenta contable, así que el sistema seguía dejando
  editarle la fecha o anular un cheque que el banco ya había pagado.
- **1.6** — La columna "Tipo" del listado muestra el tipo real del documento
  (Rol de Pago, Décimo Cuarto, Décimo Tercero, Préstamo...) en vez de repetir
  siempre el nombre del botón de concepto usado (p. ej. todo lo pagado desde
  "Nómina" salía como "Rol de Pago" aunque fuera un décimo o un préstamo).
- **1.5** — Los botones de concepto ligados a documento (Compra, Liquidación,
  Nómina) solo se muestran si hay algún pendiente de ese tipo en la empresa.
  Se quitó el botón "Agregar documentos" (redundante con los botones de
  concepto de la barra superior).
- **1.4** — Los conceptos del egreso (Compra, Liquidación, Nómina, Otros
  conceptos...) dejan de ser excluyentes: se pueden combinar en un mismo
  egreso (p. ej. una factura de compra + un gasto sin factura). Exige cuenta
  contable explícita en las líneas manuales cuando se mezclan con un
  documento de módulo.
- **1.3** — Anular un cheque puntual sin anular el egreso: queda como
  historial visible, deja de contarse en el total y se puede cubrir con otra
  forma de pago desde el mismo modal.
- **1.2** — Botón para exportar el comprobante a Excel, junto al de PDF, en la
  barra de acciones superior del modal.
- **1.1** — Configurar impresión de cheque por banco desde la fila de pago y el
  modal de impresión en lote (abre el diseñador visual, crea la plantilla del
  banco si no existe).
- **1.0** — Versión inicial.
