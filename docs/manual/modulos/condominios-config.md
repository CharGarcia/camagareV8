---
titulo: Configuración de condominios
resumen: Todo el módulo Condominios en una pantalla: datos del condominio, condóminos (clientes y sus inmuebles), alícuota y fondo, mora y multas, reajuste de cuotas y descuentos. Guardarla activa el módulo.
categoria: Ventas
ruta_modulo: modulos/condominios-config
tipo: modulo
visibilidad: todos
etiquetas: condominio, condominios, generar cobro, emitir recibos en bloque, facturas masivas, multas masivas, cuota extraordinaria, enviar por correo, emisiones, configuracion condominio, condominos, condomino, inmuebles, inmueble, departamento, parqueadero, bodega, local, propietario, arrendatario, inquilino, propiedad horizontal, edificio, conjunto, urbanizacion, expensas, administrador del condominio, alicuota, alicuotas, cuota de mantenimiento, fondo de reserva, intereses de mora, tasa legal, multas, reglamento, pronto pago, pago anticipado, dias de gracia, restriccion areas comunes, cargar inmuebles excel, reajuste de cuotas, activar condominios
version: 1.5
orden: 1
estado: activo
---

**Configuración de condominios** es la pantalla única del módulo Condominios. La empresa es el
condominio: aquí se guardan sus reglas (administrador, alícuota, fondo de reserva, mora, multas,
descuentos), a cada **cliente** se le asignan sus **inmuebles** y se reajustan las cuotas en
bloque. **Guardarla activa el módulo** para la empresa. La emisión mensual la hace
**Suscripciones**, el cobro **Ingresos** y la cartera **Cuentas por Cobrar**, como siempre.

## Qué es y para qué sirve

- Una sola pantalla en pestañas: **Condominio**, **Condóminos**, **Alícuota y fondo**, **Mora y
  multas**, **Reajuste de cuotas** y **Descuentos**.
- **Condóminos** lista **todos los clientes** de la empresa y, a cada uno, sus inmuebles
  (departamento, local, parqueadero, bodega…) como propietario o arrendatario, con quién paga,
  área, % de alícuota, historial de propietarios y restricción de áreas comunes.
- Lo que es de la emisión (recibo o factura, serie, periodicidad, día de cobro, valor de la
  cuota) **no está aquí**: lo define cada suscripción en el módulo Suscripciones.
- Todo lo opcional nace **apagado**: intereses, multas, descuentos y restricción automática.
- Los conceptos que se cobran **no se crean aquí**: son servicios del módulo **Productos**. El
  nombre, el IVA y la cuenta contable salen del producto.

## Requisitos previos

- La empresa debe ser el condominio (RUC, establecimiento y serie propios).
- Los condóminos son **clientes**. Si no existen, la carga por Excel los crea.
- Si se cobra fondo de reserva, intereses o multas, un servicio en **Productos** para cada uno.
- Permiso **Actualizar** en este submódulo para guardar.

## Cómo se usa

1. **Condominio**: nombre, dirección, **administrador/a** (obligatorio: firma la liquidación para
   cobro judicial, art. 13 LPH), presidente de la asamblea (opcional), desde cuántas expensas
   vencidas se habilita la liquidación. **Guardar** activa el módulo.
2. **Condóminos**: asigne a cada cliente su inmueble (ver la sección propia más abajo).
3. **Alícuota y fondo**: método de alícuota del condominio (por %, por m² o manual; cada
   inmueble puede tener el suyo). Fondo de reserva: no / % sobre la alícuota / monto fijo, con su
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
6. **Descuentos**: pronto pago (% hasta el día N) y pago anticipado de varios meses (% desde N
   meses). Ambos apagados por defecto.

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
- **Botón +** de la fila: asigna otro inmueble al mismo cliente.

En las etiquetas, azul es propietario, celeste es arrendatario (*arr.*) y gris es un inmueble
inactivo. El ícono de billetes indica que ese cliente paga la expensa del inmueble; el ícono
tachado, que tiene restringidas las áreas comunes.

### Ventana del inmueble

| Pestaña | Qué tiene |
|---|---|
| General | Código, nombre, tipo, torre, piso, propietario / arrendatario / quién paga, área, alícuota, método, monto manual, fondo propio, estado y la cuota ordinaria estimada con el valor que rige hoy. Al cambiar las personas aparece **Rige desde**. |
| Propietarios | Historial de propietario, arrendatario y pagador con desde / hasta. |
| Áreas comunes | Marcar o levantar la restricción (fecha de notificación y motivo obligatorios) y su bitácora. |
| Expensa | La suscripción que emite la cuota del inmueble. Se asocia desde Suscripciones (campo **Inmueble**) o se enlaza aquí una suscripción del pagador que aún no tenga inmueble. Al emitir, el inmueble y su propietario salen en la Información adicional del documento. |

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
con sus inmuebles en una celda. **Restringidas** entrega el PDF de inmuebles con áreas comunes
restringidas, para administración y guardianía.

### Generar cobro a condóminos

Marque los condóminos en el listado (la casilla del encabezado marca la página; las marcas se
conservan al cambiar de página o de filtro) y pulse **Generar cobro**. También puede cobrar a
**todos los del filtro actual** sin marcar uno por uno. Hay dos caminos:

**Emitir ahora** (multas, cuotas extraordinarias o cualquier servicio). Genera de una sola vez un
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

**Emisiones** (botón junto al buscador) muestra cada emisión en bloque con su avance, los
documentos generados, los errores y los correos enviados. Al abrir una se ve el detalle por
condómino, con el número de documento y el motivo de cada error. Una emisión en curso se puede
**cancelar**: lo ya generado se conserva y lo que falta no se emite.

### Cargar inmuebles desde Excel

**Cargar Excel → Descargar plantilla** entrega un archivo con una fila por inmueble y filas de
ejemplo (bórrelas). Columnas: `CODIGO`, `NOMBRE`, `TIPO`, `TORRE_BLOQUE`, `PISO`, `AREA_M2`,
`ALICUOTA_PCT`, `PROPIETARIO_IDENTIFICACION`, `PROPIETARIO_NOMBRE`, `PROPIETARIO_EMAIL`,
`PROPIETARIO_TELEFONO`, `ARRENDATARIO_IDENTIFICACION`, `ARRENDATARIO_NOMBRE`, `PAGADOR`,
`METODO`, `MONTO_MANUAL`, `FONDO_RESERVA_PROPIO`, `OBSERVACIONES`.

- El propietario y el arrendatario se cruzan con **Clientes por cédula/RUC**; si no existen se
  crean con el nombre, correo y teléfono del archivo.
- Si el código ya existe, el inmueble se **actualiza**; si cambian las personas, se abre una fila
  nueva en el historial.
- **Vista previa** antes de grabar, con la acción por fila, los clientes nuevos y los errores.
  Nada se graba hasta **Aplicar**.

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
| Fondo de reserva | — | No / % sobre la alícuota ordinaria / monto fijo por inmueble. Producto opcional. |
| Valores que rigen | — | Tarifa por m² y monto mensual a repartir, cada uno con el mes desde el que rige. |
| Reajuste de cuotas | — | Concepto, forma (fijo / % / según inmueble), parámetro, fecha de aplicación, descripción o acta, incluir suscripciones sin inmueble. |
| Cobra intereses de mora | — | Apagado por defecto. Tasa legal vigente o % mensual fijo (0,01–20). Interés simple sobre el capital vencido. Exige su producto. |
| Dónde se cobra el interés | — | Línea en el siguiente recibo o recibo aparte. |
| Cobra multas | — | Apagado por defecto. Habilita cargar multas del catálogo a un inmueble. |
| Restricción automática | — | Marca la restricción de áreas comunes al superar N meses de mora; se quita al quedar al día. |
| Pronto pago | — | % de descuento si paga hasta el día N del mes. No aplica a intereses ni multas. |
| Pago anticipado | — | % de descuento al pagar N meses o más de una vez. |

## Permisos

| Permiso | Qué permite |
|---|---|
| Ver | Consultar la configuración, los condóminos, sus inmuebles, PDF y Excel. |
| Crear | Asignar inmuebles, cargar Excel, crear multas y **emitir cobros en bloque**. |
| Actualizar | **Guardar la configuración**, editar inmuebles, marcar o levantar restricciones, enlazar suscripciones, editar multas, registrar valores que rigen, aplicar o programar reajustes y cancelar emisiones en curso. |
| Crear + Actualizar | **Agregar a suscripciones** (crea suscripciones y cambia valores). |
| Eliminar | Eliminar inmuebles sin expensas emitidas, multas y valores que rigen. |

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

## Integraciones con otros módulos

- **Clientes**: los condóminos son los clientes de la empresa.
- **Productos**: fuente de los conceptos.
- **Suscripciones**: emite la cuota de cada inmueble; el campo **Inmueble** de la suscripción la
  asocia y el reajuste de cuotas escribe el precio de su línea.
- **Presupuestos**: un presupuesto aprobado puede ser la base del monto a repartir.
- **Ingresos / Cuentas por Cobrar / Reporte de Cartera**: cobro y cartera, como siempre.
- **Recibos de Venta / Facturas de Venta / SRI**: los cobros en bloque crean documentos normales
  de esos módulos (se ven, se cobran y se anulan allí); las facturas se envían al SRI y el correo
  usa la configuración de correo de la empresa.

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

## Historial de cambios

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
