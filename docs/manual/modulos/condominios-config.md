---
titulo: Administración del condominio
resumen: Todo el módulo Condominios en una pantalla: datos del condominio, condóminos (clientes y sus inmuebles), alícuota y fondo de reserva, mora y multas, reajuste de cuotas y cuotas extraordinarias a plazos (con anticipos). Guardarla activa el módulo.
categoria: Ventas
ruta_modulo: modulos/condominios-config
tipo: modulo
visibilidad: todos
etiquetas: administracion del condominio, administracion de condominios, configuracion de condominios, condominio, condominios, generar cobro, emitir recibos en bloque, facturas masivas, multas masivas, cuota extraordinaria, enviar por correo, emisiones, configuracion condominio, condominos, condomino, inmuebles, inmueble, departamento, parqueadero, bodega, local, propietario, arrendatario, inquilino, propiedad horizontal, edificio, conjunto, urbanizacion, expensas, administrador del condominio, alicuota, alicuotas, cuota de mantenimiento, cuotas extraordinarias, cuota extraordinaria, fondo de reserva, intereses de mora, tasa legal, multas, reglamento, pronto pago, pago anticipado, dias de gracia, restriccion areas comunes, cargar inmuebles excel, reajuste de cuotas, activar condominios, cuota extraordinaria a plazos, pintura del edificio, obra aprobada en asamblea, acta de asamblea, cuotas de una obra, dividir en cuotas, 24 cuotas, plan de pagos, anticipo de clientes, abono inicial, liquidar saldo, venta de inmueble deuda, cronograma de cuotas
version: 1.8
orden: 1
estado: activo
---

**Administración del condominio** es la pantalla única del módulo Condominios. La empresa es el
condominio: aquí se guardan sus reglas (administrador, alícuota, fondo de reserva, mora y multas),
a cada **cliente** se le asignan sus **inmuebles**, se reajustan las cuotas en bloque y se llevan
las **cuotas extraordinarias a plazos** (obras aprobadas en asamblea). **Guardarla activa el
módulo** para la empresa. La emisión mensual la hace **Suscripciones**, el cobro **Ingresos** y la
cartera **Cuentas por Cobrar**, como siempre.

## Qué es y para qué sirve

- Una sola pantalla en pestañas: **Condominio**, **Condóminos**, **Mora y multas**, **Reajuste de
  cuotas** y **Cuotas extraordinarias**. La alícuota y el fondo de reserva del condominio se
  configuran en la ventana de cualquier inmueble (pestaña **Alícuota y fondo**).
- **Cuotas extraordinarias** lleva las obras o gastos aprobados en asamblea que se pagan a plazos
  (p. ej. pintar el edificio): reparte el monto entre los inmuebles, aplica el anticipo que ya dio
  cada condómino y emite el saldo en las cuotas que elija cada uno, sin repetir ninguna.
- **Condóminos** lista **todos los clientes** de la empresa y, a cada uno, sus inmuebles
  (departamento, local, parqueadero, bodega…) como propietario o arrendatario, con quién paga,
  área, % de alícuota, historial de propietarios y restricción de áreas comunes.
- Lo que es de la emisión (recibo o factura, serie, periodicidad, día de cobro, valor de la
  cuota) **no está aquí**: lo define cada suscripción en el módulo Suscripciones.
- Todo lo opcional nace **apagado**: intereses, multas y restricción automática.
- Los conceptos que se cobran **no se crean aquí**: son servicios del módulo **Productos**. El
  nombre, el IVA y la cuenta contable salen del producto.

## Requisitos previos

- La empresa debe ser el condominio (RUC, establecimiento y serie propios).
- Los condóminos son **clientes**. Si no existen, la carga por Excel los crea.
- Si se cobran fondo de reserva, cuotas extraordinarias, intereses o multas, un servicio en
  **Productos** para cada uno.
- Para aplicar anticipos a una cuota extraordinaria: una forma de cobro de tipo **Anticipo** activa
  (la que se usa en Ingresos para los anticipos de clientes).
- Permiso **Actualizar** en este submódulo para guardar.

## Cómo se usa

1. **Condominio**: nombre, dirección, **administrador/a** (obligatorio: firma la liquidación para
   cobro judicial, art. 13 LPH), presidente de la asamblea (opcional), desde cuántas expensas
   vencidas se habilita la liquidación. **Guardar** activa el módulo.
2. **Condóminos**: asigne a cada cliente su inmueble (ver la sección propia más abajo).
3. **Alícuota y fondo de reserva** (en la ventana de cualquier inmueble de **Condóminos**;
   aplica a **todo el condominio** y se graba con **Guardar para el condominio**): método de
   alícuota del condominio (por %, por m² o manual; cada inmueble puede tener el suyo). Fondo de
   reserva: no / % sobre la alícuota / monto fijo, que se suma a **cada** cuota, con su
   producto. Debajo, **Valores que rigen**: la tarifa por m² y el monto mensual a repartir, cada
   uno con el **mes desde el que rige**. Se agregan con **Nuevo valor desde…**, que muestra una
   **vista previa** de la cuota de cada inmueble antes de guardar; el monto a repartir puede
   **tomarse de un presupuesto aprobado**. Los valores anteriores nunca se editan.
4. **Mora y multas**: interruptor de intereses (tasa legal vigente o % mensual fijo; en el
   siguiente recibo o en uno aparte; con su producto), interruptor de multas, restricción
   automática de áreas comunes al superar N meses de mora, **días de gracia** y el **catálogo de
   multas** del reglamento (nombre, valor, producto; se guarda por fila con *Guardar multa*).
5. **Reajuste de cuotas**: cambia el valor de un concepto en muchas suscripciones a la vez
   (monto fijo, aumento % o según el inmueble). **Vista previa** muestra cada suscripción con su
   valor actual y el nuevo (se pueden destildar); **Aplicar** cambia la línea del concepto en
   cada suscripción. Con una **fecha futura** queda **programado** y se aplica solo ese día.
6. **Cuotas extraordinarias**: obras o gastos aprobados en asamblea que se pagan a plazos (ver la
   sección propia más abajo).

El botón **Guardar** del pie graba la configuración. En la pestaña Condóminos no aparece: cada
inmueble se guarda en su propia ventana.

## Condóminos: clientes e inmuebles

La pestaña muestra **todos los clientes** de la empresa, tengan o no inmueble. Cada fila es un
cliente con su identificación, contacto, sus **inmuebles** como etiquetas, la suma de las
alícuotas de los inmuebles de los que es propietario y si tiene alguno restringido.

- **Clic en un cliente sin inmueble**: abre la ventana para asignarle uno, con el cliente ya
  puesto como propietario.
- **Clic en un cliente con un inmueble**: abre ese inmueble.
- **Clic en una etiqueta**: abre ese inmueble (útil cuando el cliente tiene varios).
- **Botón +** junto a las etiquetas de inmuebles: asigna otro inmueble al mismo cliente.

En las etiquetas, azul es propietario, celeste es arrendatario (*arr.*) y gris es un inmueble
inactivo. El ícono de billetes indica que ese cliente paga la expensa del inmueble; el ícono
tachado, que tiene restringidas las áreas comunes.

### Ventana del inmueble

| Pestaña | Qué tiene |
|---|---|
| General | Código, nombre, tipo, torre, piso, propietario / arrendatario / quién paga, área, alícuota, método, monto manual, fondo de reserva propio, estado y la cuota ordinaria estimada con el valor que rige hoy. Al cambiar las personas aparece **Rige desde**; si el inmueble tiene cuotas extraordinarias por cobrar a la persona anterior, al guardar se pregunta qué hacer con esa deuda. |
| Alícuota y fondo | Configuración **común a todos los inmuebles**: método de alícuota del condominio, fondo de reserva y **Valores que rigen**. Tiene su propio botón **Guardar para el condominio**; el Guardar del pie no aparece en esta pestaña. |
| Propietarios | Historial de propietario, arrendatario y pagador con desde / hasta. |
| Áreas comunes | Marcar o levantar la restricción (fecha de notificación y motivo obligatorios) y su bitácora. |
| Expensa | La suscripción que emite la cuota del inmueble. Se enlaza aquí una suscripción del pagador que aún no tenga inmueble. El inmueble que deba salir en el documento se escribe a mano en la **Información adicional** de la suscripción. |

| Campo | Obligatorio | Qué significa |
|---|---|---|
| Código | Sí | Único en el condominio (DPTO-302, P-12). Se puede reutilizar el de un inmueble eliminado. |
| Tipo | Sí | Departamento, local, oficina, parqueadero, bodega, casa, otro. |
| Área m² / Alícuota % | Según método | Obligatorios según el método efectivo (m² o %). |
| Propietario | Sí | Cliente. Siempre se guarda. |
| Arrendatario / Paga el | No | Si paga el arrendatario, el documento sale a su nombre. |
| Rige desde | Al cambiar personas | Fecha desde la que rige el nuevo propietario, arrendatario o pagador. |
| Método / Monto manual | No | Vacío = el del condominio. Con método manual el monto es obligatorio. |
| Fondo de reserva propio | No | Valor propio del inmueble; vacío = regla del condominio. |
| Estado | Sí | Inactivo = no cuenta para el reparto ni para el reajuste. |

### Buscar, filtrar y ordenar condóminos

**Búsqueda libre** en nombre, identificación, correo y teléfono del cliente, y en código, nombre
y torre de sus inmuebles, por palabras sueltas y sin distinguir tildes.

| Bloque | Filtros |
|---|---|
| Cliente | Nombre, identificación, activo / inactivo |
| Inmueble | Con / sin inmueble, rol (propietario, arrendatario, ambos, ninguno), código o nombre del inmueble, torre / bloque, número de inmuebles (rango), con / sin restricción |

Sintaxis directa: `con_inmueble:no`, `rol:arrendatario`, `torre:"Torre B"`, `inmuebles:>=2`,
`restringida:si`. Clic en un título ordena por esa columna; con **Shift+clic** se encadenan hasta
tres. **PDF** y **Excel** exportan con la misma búsqueda, filtros y orden: una fila por cliente
con sus inmuebles en una celda. Para ver solo los restringidos, filtre con `restringida:si`.

### Generar cobro a condóminos

Marque los condóminos en el listado (la casilla del encabezado marca la página; las marcas se
conservan al cambiar de página o de filtro) y pulse **Generar cobro**. También puede cobrar a
**todos los del filtro actual** sin marcar uno por uno. Hay dos caminos:

**Emitir ahora** (multas, un cobro único o cualquier servicio). Genera de una sola vez un
recibo de venta o una factura por condómino, o por cada inmueble que paga, **sin pasar por
Suscripciones**.

1. Elija el **concepto** (un servicio de Productos) o una **multa del reglamento**, que completa
   concepto, valor y descripción.
2. Indique el **valor sin IVA**: el mismo para todos, o la **cuota del inmueble** con el valor que
   rige (solo con «un documento por inmueble»).
3. Elija **comprobante**, **serie** y **descripción**; esta última sale en la información
   adicional como «Concepto», junto con el inmueble.
4. Marque **Enviar cada documento por correo** si corresponde.
5. **Vista previa** muestra cada documento con su valor, su correo y sus avisos. Destilde los que
   no van y pulse **Emitir**.

Los documentos se generan en **segundo plano**: puede cerrar la ventana y seguir trabajando. Las
facturas se envían al SRI y su correo sale cuando quedan autorizadas. Los recibos se envían en el
acto. Si un condómino **ya cobra ese concepto en una suscripción**, la fila viene desmarcada y con
aviso, para no cobrarle dos veces.

**Agregar a suscripciones** (cobro recurrente, p. ej. la alícuota). Lleva el concepto a las
suscripciones de los condóminos **sin duplicar**:

| Situación del condómino o inmueble | Qué se hace |
|---|---|
| Ya tiene el concepto con el mismo valor | Nada (sin cambio). |
| Ya tiene el concepto con otro valor | Se actualiza el valor. |
| Tiene suscripción, pero sin ese concepto | Se le agrega la línea. |
| No tiene suscripción | Se crea una, con la periodicidad, el comprobante y el primer cobro indicados, enlazada a su inmueble. |

La emisión mensual sigue siendo de **Suscripciones**. Para un cambio de valor masivo con fecha
futura, use la pestaña **Reajuste de cuotas**.

Los documentos emitidos se consultan en **Recibos de Venta** o **Facturas de Venta**.

### Cargar inmuebles desde Excel

**Cargar Excel → Descargar plantilla** entrega un archivo con una fila por inmueble y filas de
ejemplo (bórrelas). Columnas: `CODIGO`, `NOMBRE`, `TIPO`, `TORRE_BLOQUE`, `PISO`, `AREA_M2`,
`ALICUOTA_PCT`, `PROPIETARIO_IDENTIFICACION`, `PROPIETARIO_NOMBRE`, `PROPIETARIO_EMAIL`,
`PROPIETARIO_TELEFONO`, `ARRENDATARIO_IDENTIFICACION`, `ARRENDATARIO_NOMBRE`, `PAGADOR`,
`METODO`, `MONTO_MANUAL`, `FONDO_RESERVA_PROPIO` (fondo de reserva propio), `OBSERVACIONES`.

- El propietario y el arrendatario se cruzan con **Clientes por cédula/RUC**; si no existen se
  crean con el nombre, correo y teléfono del archivo.
- Si el código ya existe, el inmueble se **actualiza**; si cambian las personas, se abre una fila
  nueva en el historial.
- **Vista previa** antes de grabar, con la acción por fila, los clientes nuevos y los errores.
  Nada se graba hasta **Aplicar**.

## Cuotas extraordinarias a plazos (pintura, ascensor, obras)

Sirve para lo que la asamblea aprueba una sola vez y se paga en cuotas. Ejemplo: pintar el edificio
cuesta $30.000; varios condóminos ya dieron un anticipo y el saldo se divide en 24 cuotas. El
sistema reparte el monto, aplica los anticipos, emite cada cuota una sola vez y muestra cuánto se
emitió, cuánto se cobró y cuánto falta, por inmueble y en total.

### Crear la cuota extraordinaria

Pestaña **Cuotas extraordinarias** → **Nueva cuota extraordinaria**:

1. **Nombre**, **concepto** (un servicio de Productos: su nombre, IVA y cuenta contable salen de
   ahí), **acta** y su fecha. Si el servicio aún no existe, créelo con el botón de la caja
   (**Registrar nuevo producto o servicio**) de la barra superior de la ventana: al guardarlo
   queda puesto como concepto.
2. **Monto aprobado** y **reparto**: en **partes iguales** o **por % de alícuota**.
3. **Cuotas permitidas** (p. ej. `1, 6, 12, 24`) y las **cuotas por defecto**, que se escriben
   libremente: si el número no está en la lista, se agrega. Cada condómino puede elegir otro
   número de la lista.
4. **Primera cuota** (mes) y **quién paga**: el propietario (lo normal) o el arrendatario.
5. **Dónde sale cada cuota**: en el **recibo o factura de la alícuota** que ya emite
   Suscripciones, o en un **documento aparte**.
6. **Documento propio** (recibo o factura) y **serie**: se usan para el abono inicial, las cuotas
   que salen aparte y las liquidaciones. **Forma de cobro de anticipos**: la de tipo Anticipo con
   la que se aplica lo que cada condómino ya entregó.

Pulse **Guardar**. Todavía no se crea ninguna cuota.

### Armar el plan de cada inmueble

En la pestaña **Inmuebles** pulse **Calcular reparto**. Aparece una fila por inmueble activo sin plan:

| Columna | Qué significa |
|---|---|
| Casilla | Destíldela si el inmueble no participa (p. ej. parqueaderos). El monto se reparte entre los marcados. |
| Paga | Propietario o arrendatario (solo si tiene uno registrado). |
| Asignado | Lo que le toca. En blanco se calcula solo; escriba un valor para fijarlo a mano y el resto se reparte entre los demás. |
| Anticipo | Cuánto del anticipo de clientes se usa para esta obra. **Disponible** muestra el saldo de anticipo del cliente; clic en él para usarlo completo. |
| Cuotas | En cuántas cuotas paga lo que queda. |
| Cuota | Valor de cada cuota. La última absorbe los centavos (p. ej. 23 de $8,33 y una de $8,41). |

Arriba se ve la **diferencia** contra el monto aprobado: debe quedar en $0,00 para activar. Al
pulsar **Activar planes**:

- se crean todas las cuotas de cada inmueble, mes a mes, desde la primera;
- si el inmueble tiene anticipo, se emite un **abono inicial** por ese valor y se cobra con la
  forma **Anticipo**: el anticipo deja de estar pendiente y queda aplicado a la obra.

Se pueden activar más inmuebles después (por ejemplo, uno que se agregó): el cálculo reparte lo
que falta para llegar al monto aprobado.

### Cómo sale cada cuota

- **Con la alícuota**: cuando Suscripciones emite el recibo o factura del mes del inmueble, agrega
  una línea «Pintura del edificio – cuota 5/24». Si había cuotas atrasadas, salen también. Después
  de la última cuota no se agrega nada más.
- **Aparte**: en la pestaña **Cuotas**, elija el mes en **Emitir aparte hasta**, pulse **Vista
  previa** y luego **Emitir**. Sale un documento por inmueble con sus cuotas pendientes hasta ese
  mes, en segundo plano, como **Generar cobro** (SRI y correo incluidos).
- Una cuota marcada «con la alícuota» sale **aparte** cuando el inmueble no tiene una suscripción
  activa o cuando la alícuota la paga otra persona (p. ej. la paga el arrendatario y la obra el
  propietario). Esas cuotas aparecen en la vista previa de **Emitir aparte** con el motivo.

Cada cuota sale **una sola vez**: queda marcada con el documento en que salió.

### Seguimiento, cobro y acciones

La pestaña **Inmuebles** muestra por inmueble: asignado, anticipo, cuota, cuotas emitidas
(«5/24»), emitido, **cobrado** y **saldo**. Lo cobrado sale de los cobros registrados en Ingresos
(y retenciones o notas de crédito). Si la cuota salió en el mismo documento que la alícuota y el
condómino pagó solo una parte, ese pago cubre **primero la alícuota** y después la cuota.

En el menú de cada inmueble (⋮):

- **Liquidar el saldo**: emite un solo documento con todas las cuotas que faltan y cierra su plan.
- **Cambiar número de cuotas**: reparte lo que falta en otro número de cuotas (las ya emitidas no
  cambian; la numeración sigue, p. ej. 2/7…7/7).
- **Cambiar quién paga**: propietario o arrendatario, para las cuotas que faltan emitir.
- **Aplicar el anticipo (reintentar)**: aparece si el abono inicial no se pudo aplicar.

En la pestaña **Cuotas** está el cronograma completo con el documento de cada cuota. Si se anula
el documento de una cuota en Recibos o Facturas de Venta, la fila aparece tachada y el botón
**Volver a pendiente** la deja lista para la próxima emisión.

**Cerrar** (barra superior) termina la cuota extraordinaria: las cuotas que faltaban emitir se
anulan; lo emitido y lo cobrado se conservan. Los botones **PDF** y **Excel** entregan el estado
por inmueble.

### Venta del inmueble o cambio de arrendatario

Al guardar un inmueble con un propietario (o arrendatario) nuevo, si tiene cuotas extraordinarias
por cobrar a la persona anterior, el sistema pregunta por cada una:

- **Sigue con el inmueble**: las cuotas que faltan desde la fecha **Rige desde** pasan al nuevo
  pagador. Lo ya emitido sigue a nombre del anterior.
- **Liquidarla**: se emite al anterior un documento con todo lo que falta y su plan se cierra.
- **Decidir después**: no cambia nada; las cuotas siguen a nombre del anterior.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|---|---|---|
| Nombre del condominio, dirección | No | Por defecto, el nombre de la empresa y la dirección de su establecimiento principal. |
| Administrador/a, cédula, cargo | Nombre sí | Firma la liquidación para cobro judicial. Por defecto, el representante legal de la empresa. |
| Presidente/a de la asamblea | No | Segunda firma opcional en la liquidación. |
| Liquidación judicial desde | Sí | Expensas vencidas mínimas para habilitar la liquidación (por defecto 1). |
| Días de gracia | Sí | 0–60, contados desde el vencimiento del recibo. Si paga dentro no hay interés. |
| Método de alícuota | Sí | **Por %**: monto a repartir × % del inmueble. **Por m²**: tarifa × área. **Manual**: monto acordado por inmueble. |
| Manuales con presupuesto | — | Solo con base en un presupuesto aprobado: *repartir el resto* (por defecto) o *manuales aparte*. |
| Fondo de reserva | — | No / % sobre la alícuota ordinaria / monto fijo por inmueble, en cada cuota. Producto opcional. |
| Valores que rigen | — | Tarifa por m² y monto mensual a repartir, cada uno con el mes desde el que rige. |
| Reajuste de cuotas | — | Concepto, forma (fijo / % / según inmueble), parámetro, fecha de aplicación, descripción o acta, incluir suscripciones sin inmueble. |
| Cobra intereses de mora | — | Apagado por defecto. Tasa legal vigente o % mensual fijo (0,01–20). Interés simple sobre el capital vencido. Exige su producto. |
| Dónde se cobra el interés | — | Línea en el siguiente recibo o recibo aparte. |
| Cobra multas | — | Apagado por defecto. Habilita cargar multas del catálogo a un inmueble. |
| Restricción automática | — | Marca la restricción de áreas comunes al superar N meses de mora; se quita al quedar al día. |
| Cuota extraordinaria: nombre, concepto, monto aprobado | Sí | El concepto es un servicio de Productos. Con planes activados, el monto, el reparto, el concepto y la primera cuota ya no se cambian. |
| Cuota extraordinaria: reparto | Sí | Partes iguales o por % de alícuota, con ajuste manual por inmueble. |
| Cuota extraordinaria: cuotas permitidas / por defecto | Sí | Números de cuotas que puede elegir cada condómino (1 a 120). Las de por defecto se escriben libremente y se suman a las permitidas si no estaban. |
| Cuota extraordinaria: serie | Sí | Se muestra como «001-001 · Principal (establecimiento)». |
| Cuota extraordinaria: dónde sale | Sí | Con la alícuota (Suscripciones) o en un documento aparte. |
| Cuota extraordinaria: documento propio y serie | Sí | Para el abono inicial, las cuotas aparte y las liquidaciones. |
| Cuota extraordinaria: forma de cobro de anticipos | No | Vacía = la primera forma de cobro activa de tipo Anticipo. |

## Permisos

| Permiso | Qué permite |
|---|---|
| Ver | Consultar la configuración, los condóminos, sus inmuebles, PDF y Excel. |
| Crear | Asignar inmuebles, cargar Excel, crear multas, **emitir cobros en bloque**, crear cuotas extraordinarias, **activar planes** (con su abono inicial) y emitir cuotas aparte. |
| Actualizar | **Guardar la configuración**, editar inmuebles, marcar o levantar restricciones, enlazar suscripciones, editar multas, registrar valores que rigen, aplicar o programar reajustes, editar o **cerrar** una cuota extraordinaria, cambiar el número de cuotas o quién paga y volver a pendiente una cuota. |
| Crear + Actualizar | **Agregar a suscripciones** (crea suscripciones y cambia valores), **liquidar** el saldo de un plan y decidir la deuda al vender un inmueble. |
| Eliminar | Eliminar inmuebles sin expensas emitidas, multas, valores que rigen y cuotas extraordinarias sin planes. |

Los registros propios no aplican: la cartera del condominio es una sola y todos los usuarios con
permiso ven todos los condóminos.

## Reglas de negocio

- Hay **una configuración por empresa**; guardarla por primera vez activa el módulo. Sin ella
  no se pueden asignar inmuebles y la pestaña Condóminos lo avisa.
- Los productos elegidos deben ser **servicios** de la misma empresa.
- Código de inmueble único entre los no eliminados. Un inmueble con expensas emitidas **no se
  elimina**: se marca inactivo.
- Cada cambio de propietario, arrendatario o pagador cierra la fila vigente del historial y abre
  otra desde la fecha indicada.
- Para que pague el arrendatario primero debe registrarse uno. El pagador define el cliente del
  documento; el propietario siempre queda guardado.
- Solo se enlazan suscripciones **del pagador** que no pertenezcan a otro inmueble.
- Restringir áreas comunes exige la **fecha de notificación** y el **motivo**.
- Un reajuste siempre se recalcula en el servidor al aplicar. Los programados los aplica el cron
  diario; si uno falla queda en estado *Error* con el motivo.
- No puede haber dos valores que rijan desde el mismo mes. Sin un valor vigente, los inmuebles
  por % y por m² no tienen cuota estimada (los manuales sí).
- Todo cambio queda en `log_sistema` con los valores anteriores y nuevos.
- **Cobros en bloque**: cada documento se genera con el mismo mecanismo que Suscripciones (IVA
  según la serie, secuencial único, forma de pago crédito). La vista previa se recalcula en el
  servidor al grabar; solo se emiten las filas marcadas que siguen siendo válidas.
- Un doble clic o un reintento tras «Error de conexión» no crea dos emisiones: se reconoce la
  misma emisión y no se repite.
- Si el proceso se corta a mitad de un documento, ese documento queda **para revisar** y nunca se
  vuelve a emitir solo (podría duplicarse). El sistema retoma lo pendiente en unos minutos.
- **Agregar a suscripciones** nunca crea una segunda suscripción para el mismo inmueble con el
  mismo concepto; aplicarlo dos veces no cambia nada.
- **Cuotas extraordinarias**: la suma de lo asignado debe igualar el monto aprobado (los centavos
  van al inmueble de mayor monto). El anticipo a aplicar no puede superar lo asignado ni el saldo
  de anticipo del cliente; se revalida al activar y otra vez al aplicarlo, sin que otro cobro use
  ese mismo saldo al mismo tiempo.
- Cada cuota sale en **un solo documento**: se marca en el mismo momento en que se graba el
  documento. Si el documento falla, la cuota sigue pendiente para la próxima emisión. Activar dos
  veces con el mismo clic o tras «Error de conexión» no crea planes repetidos.
- Lo cobrado de una cuota no se guarda: se calcula siempre desde los cobros, retenciones y notas
  de crédito del documento.
- Una cuota extraordinaria con planes no se elimina: se **cierra**.

## Integraciones con otros módulos

- **Clientes**: los condóminos son los clientes de la empresa.
- **Productos**: fuente de los conceptos.
- **Suscripciones**: emite la cuota de cada inmueble; se enlaza desde la pestaña Expensa del
  inmueble y el reajuste de cuotas escribe el precio de su línea. El inmueble se escribe a mano en
  la Información adicional de la suscripción.
- **Presupuestos**: un presupuesto aprobado puede ser la base del monto a repartir.
- **Ingresos / Cuentas por Cobrar / Reporte de Cartera**: cobro y cartera, como siempre.
- **Recibos de Venta / Facturas de Venta / SRI**: los cobros en bloque crean documentos normales
  de esos módulos (se ven, se cobran y se anulan allí); las facturas se envían al SRI y el correo
  usa la configuración de correo de la empresa.
- **Suscripciones (cuotas extraordinarias)**: al emitir el documento del mes de un inmueble,
  Suscripciones agrega sus cuotas extraordinarias «con la alícuota» que estén pendientes.
- **Ingresos (anticipos)**: el abono inicial de una cuota extraordinaria se cobra con un ingreso
  automático con la forma de cobro **Anticipo**, que descuenta el saldo de anticipo del cliente.
  Contablemente, el anticipo de clientes pasa a la cuenta del producto de la obra.

## Errores frecuentes

- **Una factura de la emisión quedó «Generado» con un mensaje del SRI**: la factura se creó pero
  no se autorizó en ese momento. Reenvíela desde **Facturas de Venta**; su correo sale al
  autorizarse si la empresa tiene el envío automático, o desde la misma factura.
- **Un documento aparece «Revisar»**: el proceso se interrumpió en ese documento. Verifique en
  Recibos o Facturas de Venta si se creó antes de emitirlo de nuevo.
- **Correo «Sin correo»**: el cliente no tiene un correo válido en Clientes; el documento sí se
  emitió.

- **«El módulo Condominios aún no está instalado»**: falta aplicar en la base de datos
  `database/migrations/20261005_condominios.sql`.
- **No veo el listado de Inmuebles en el menú**: ya no existe como pantalla aparte. Los inmuebles
  se ven y se asignan en la pestaña **Condóminos** de esta pantalla.
- **«Con método por % el inmueble necesita su % de alícuota»**: indique el % de la escritura o
  cambie el método de ese inmueble a m² o manual.
- **«El inmueble ya tiene expensas emitidas: no se puede eliminar»**: márquelo como inactivo.
- **«La suscripción es de otro cliente»**: solo se enlazan suscripciones del pagador del inmueble.
- **¿Dónde se define si es recibo o factura, la serie y el valor de la cuota?** En la suscripción
  de cada inmueble, en el módulo Suscripciones.
- **Backspace no borra letra a letra en un buscador de cliente o producto**: es a propósito; con
  una selección hecha, Backspace o Supr limpian toda la selección.
- **«Falta aplicar database/migrations/20261117_condominios_cuotas_extraordinarias.sql»**: la
  pestaña Cuotas extraordinarias necesita ese SQL en la base de datos.
- **«La suma de lo asignado no iguala el monto aprobado»**: revise los montos escritos a mano o
  los inmuebles destildados hasta que la diferencia quede en $0,00.
- **«El cliente no tiene ese saldo de anticipo»**: el anticipo a aplicar supera lo que el cliente
  tiene disponible (pudo haberse usado en otro cobro). Baje el valor.
- **El anticipo de un inmueble quedó con ⚠ «aún no aplicado»**: el abono inicial no se pudo cobrar
  con la forma Anticipo. Use **Aplicar el anticipo (reintentar)** en el menú del inmueble.
- **Una cuota «con la alícuota» no apareció en el recibo del mes**: el inmueble no tiene una
  suscripción enlazada (pestaña Expensa) o la alícuota la paga otra persona. Esa cuota se emite
  desde **Emitir aparte**.

## Historial de cambios

- **1.8** — Pestaña **Cuotas extraordinarias**: obras aprobadas en asamblea pagadas a plazos, con
  reparto en partes iguales o por % de alícuota, aplicación del anticipo de clientes (abono
  inicial), número de cuotas por condómino, cuotas con la alícuota (Suscripciones) o aparte,
  cobrado y saldo por inmueble, liquidación del saldo, cambio de plazo o de pagador, PDF y Excel.
  Botón para crear el servicio desde la misma ventana (queda puesto como concepto). La pantalla
  pasa a llamarse **Administración del condominio** (antes «Configuración de condominios»).
  Al vender un inmueble se pregunta si la deuda sigue con el inmueble o se liquida. El recargo
  permanente que se sumaba a cada cuota vuelve a llamarse **Fondo de reserva** (pestaña
  **Alícuota y fondo**).

- **1.7** — El PDF de la factura o recibo que se envía por correo al cobrar usa la configuración del **establecimiento de la serie** del documento (decimales, dirección, leyendas), no la del primer establecimiento de la empresa. El logo propio del punto de emisión sigue mandando.
- **1.6** — «Fondo de reserva» pasa a llamarse **Cuotas extraordinarias** (pestaña **Alícuota y
  cuotas extraordinarias** y ventana del inmueble). Funciona igual: se suma a cada cuota. La
  pantalla ocupa todo el ancho. En Condóminos: el botón + pasa a la celda de inmuebles, **Generar
  cobro** va a la derecha junto al paginador y se retiran el resumen y los botones Restringidas y
  Emisiones. La pestaña **Alícuota y cuotas extraordinarias** pasa a la ventana del inmueble y se
  retira la pestaña **Descuentos** (los valores ya guardados se conservan). Suscripciones ya no tiene
  selector de inmueble ni agrega solo el inmueble a la Información adicional: se escribe a mano.

- **1.5** — **Generar cobro** en Condóminos: emisión en bloque de recibos o facturas sin pasar
  por Suscripciones, en segundo plano, con envío al SRI y por correo; **Agregar a suscripciones**
  sin duplicar; historial de **Emisiones** con detalle y cancelación.
- **1.4** — Pestaña **Condóminos**: lista todos los clientes con sus inmuebles y permite asignar,
  editar, restringir y enlazar cada inmueble, más PDF, Excel, Restringidas y carga por Excel. Se
  retira el submódulo aparte de Inmuebles (`modulos/condominios`).
- **1.3** — Se retira «Producto para la alícuota ordinaria»: lo define cada suscripción; el
  producto del fondo de reserva pasa a ser opcional.
- **1.2** — Pestaña **Reajuste de cuotas**: cambio masivo del valor de un concepto en las
  suscripciones, con vista previa, exclusiones, programación por fecha e historial.
- **1.1** — **Valores que rigen**, vista previa de la cuota por inmueble, base desde un
  presupuesto aprobado y reparto del resto. Sin pestaña Emisión.
- **1.0** — Versión inicial: configuración del condominio en pestañas y catálogo de multas.
