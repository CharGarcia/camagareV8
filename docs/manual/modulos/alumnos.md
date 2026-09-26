---
titulo: Alumnos
resumen: Registro de alumnos de centros infantiles, escuelas y colegios, con matrícula, horario, salud, servicios y facturación de pensiones, y documentos.
categoria: Ventas
ruta_modulo: modulos/alumnos
tipo: modulo
visibilidad: todos
etiquetas: alumnos, estudiantes, matrícula, matricula, colegio, escuela, centro infantil, campus, sede, nivel, curso, representante, representantes, padres, autorizado, autorizados, retirar, retiro, quién retira, facturación, facturar, generar factura, facturar pensión, facturar mensualidad, facturas del alumno, horario, pensión, pension, imprimir, impresora
version: 1.6
orden: 0
estado: activo
---

El módulo Alumnos registra a los estudiantes de instituciones educativas
(centro infantil, escuela o colegio) y sirve de base para facturarles
servicios recurrentes (pensión, matrícula, materiales, transporte, etc.) a
través del módulo **Clientes** ya existente. Se relaciona con Clientes (el
representante que factura), Productos (servicios predeterminados) y con la
configuración de **Puntos de Emisión** (serie preferida de facturación).

## Qué es y para qué sirve

Permite mantener una ficha completa por alumno: datos personales, sus
representantes y las personas autorizadas a retirarlo, el cliente al que se
factura, historial de matrícula (campus y
nivel/curso por período lectivo), horario individual, información de salud y
contacto de emergencia, servicios/productos que se le facturan de forma
recurrente, y documentos adjuntos (partida de nacimiento, cédula, contratos,
etc.).

Un mismo alumno puede entrar y salir de la institución varias veces a lo
largo del tiempo (un año estudia, al siguiente no, y luego regresa): cada
ingreso queda registrado como un **período de matrícula** independiente, sin
perder el historial de los anteriores.

## Requisitos previos

- Al menos un **Cliente** registrado para usar como representante del
  alumno (el alumno se factura a nombre de ese cliente).
- Los catálogos de **Campus** (`modulos/alumnos-campus`) y **Niveles/Cursos**
  (`modulos/alumnos-niveles`) se pueden crear sobre la marcha desde el mismo
  modal del alumno, con los botones de la barra de acciones superior
  (íconos de sede y birrete), no es necesario precargarlos.
- Si se quiere fijar una serie de facturación preferida, debe existir un
  **Punto de Emisión** configurado en `/config` para la empresa.

## Cómo se usa

1. Ir a **Alumnos** y presionar **Nuevo**.
2. En la pestaña **General**, completar en la primera fila el tipo y número
   de identificación y el estado académico; en la segunda, nombres,
   apellidos y sexo; en la tercera, fecha de nacimiento, **campus** y
   **nivel/curso**; y en la cuarta, nacionalidad y observaciones. El campus y
   el nivel son los de la **matrícula vigente** (el período sin fecha de
   salida): si el alumno todavía no tiene una, al elegirlos se agrega sola en
   la pestaña Matrícula con fecha de ingreso de hoy. Si el tipo es **Cédula**, al completar los 10 dígitos el
   sistema consulta la cédula (el mismo servicio que usan Clientes y
   Empleados) y llena **Apellidos** y **Nombres**; junto al número aparece
   *Encontrado* o *No encontrado*. Solo se llenan los campos vacíos o los que
   llenó una consulta anterior: lo escrito a mano no se reemplaza. Conviene
   revisar el reparto cuando el nombre tiene tres palabras (no se puede saber
   si la del medio es apellido o nombre). Los campos con una **estrella** (tipo de identificación,
   estado académico, sexo, nacionalidad y serie de
   facturación) permiten fijar un valor favorito: al marcarla, ese valor se
   precarga en cada alumno nuevo. El **engranaje** a la derecha de las
   pestañas permite ocultar las que no se usen (Representantes, Transacciones,
   Matrícula, Horario, Salud, Documentos); la preferencia es por usuario.
3. En **Representantes**, presionar **Agregar representante** por cada
   persona: nombres y apellidos, identificación, teléfono, relación con el
   alumno y observación. Se escriben libremente, no hace falta que sean
   Clientes. Marcar **Puede retirar** en quienes están autorizados a retirar
   al alumno del centro educativo (por ejemplo, un abuelo o el transporte).
   En **Facturación**, buscar y seleccionar el Cliente al que se factura,
   opcionalmente la serie de facturación preferida, y agregar debajo los
   **servicios y productos** que se le facturan (ver *Facturar los servicios
   del alumno*). La relación de cada persona con el alumno se indica en
   **Representantes**.
4. En **Matrícula** se ven y editan los períodos (año lectivo, ingreso,
   salida, motivo y observación); **Matricular / agregar período** añade uno
   nuevo. El campus y el nivel del período vigente se eligen en **General**;
   los de los períodos cerrados se conservan como historial. Si el campus o el
   nivel no existen todavía, se crean al vuelo desde los botones de la barra
   de acciones superior del modal, sin salir del formulario del alumno, y
   quedan seleccionados en General. Esos
   botones se muestran solo con su ícono: al pasar el puntero sobre cada uno
   aparece qué hace (registrar nuevo cliente, campus o nivel/curso).
5. Completar, si aplica, **Horario** y **Salud**. En la pestaña
   **Documentos** se puede cargar la **foto del alumno** desde el alta.
6. Guardar. Una vez guardado el alumno, en la pestaña **Documentos** se
   habilita la parte de adjuntar archivos (PDF o imagen).
7. Para dar de baja al alumno sin eliminarlo, editar el período de matrícula
   abierto y registrar su **fecha de salida** y motivo; para que vuelva a
   estudiar más adelante, agregar un nuevo período.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombres / Apellidos | Sí | Nombre completo del alumno. |
| Tipo / Número de identificación | No | Documento propio del alumno (no el del representante). El tipo solo ofrece **Cédula** o **Pasaporte**, porque el alumno es siempre una persona natural; RUC, consumidor final e identificación del exterior quedan para el representante, que es un Cliente. La cédula se valida a 10 dígitos y el pasaporte admite hasta 20 caracteres. |
| Fecha de nacimiento / Sexo / Nacionalidad | No | Datos personales del alumno. |
| Estado académico | Sí | Activo, retirado, egresado o suspendido. No reemplaza a la eliminación lógica del registro. |
| Representantes (nombres, identificación, teléfono, relación, observación) | No | Personas a cargo del alumno, en la pestaña **Representantes**. Datos libres: no se registran como Clientes. Solo los nombres son obligatorios en cada fila. |
| Puede retirar | No | Marca a los representantes autorizados a retirar al alumno del centro educativo. |
| Cliente que factura | Sí | Cliente ya existente a cuyo nombre se factura al alumno (pestaña **Facturación**). |
| Serie | No | Serie preferida para facturar al alumno, con el formato de la factura de venta (por ejemplo, 001-001). Solo se ofrecen las series activas que tienen secuencial de Facturas de venta configurado. Si la empresa tiene **una sola serie**, viene elegida sola; con varias, hay que elegir una. Es la que viene elegida al generar la factura. |
| Campus / Nivel-Curso | No | De dónde y qué estudia el alumno. Se eligen en la pestaña General y se guardan en el período de matrícula vigente; cada período cerrado conserva los suyos. |
| Fecha de ingreso / salida (por período) | Ingreso obligatorio | Salida vacía = matrícula vigente. Solo puede haber un período vigente por alumno. |
| Horario (día, hora inicio/fin, jornada) | No | Horario propio del alumno, no se hereda de un curso compartido. |
| Tipo de sangre / Alergias / Contacto de emergencia | No | Información útil en caso de emergencia. |
| Servicios y productos (producto, cantidad, precio, activo) | No | En la pestaña **Facturación**, debajo del cliente. Productos o servicios del catálogo que se le facturan al alumno. Al elegir el producto (o al abrir un alumno cuya línea no tenía precio propio), el **precio** se llena con su precio base y se puede cambiar para este alumno. Ese precio queda guardado en la línea: si luego cambia el precio base del producto, hay que actualizarlo aquí. Si se borra, se usa el precio base vigente al facturar. El **IVA** viene de la tarifa del producto y se puede cambiar en la línea si la configuración de facturación lo permite; el **Total** se calcula solo. |
| Detalle del ítem | No | Texto que sale bajo la descripción de esa línea en la factura (máx. 300 caracteres). Admite marcadores, p. ej. `{alumno} - Pensión {MES} {anio}`. |
| Información adicional | No | Filas concepto / detalle que se agregan a cada factura del alumno (máx. 10). Un alumno nuevo viene con «Alumno: {alumno}». |
| Foto del alumno | No | Imagen del alumno. Se carga en la pestaña **Documentos**, también al crear el alumno. |
| Documentos | No | Archivos adjuntos (PDF/imagen) asociados al alumno. |

## Facturar los servicios del alumno

Desde la ficha del alumno se genera la factura de sus servicios al **cliente
que factura** (pestaña Facturación).

1. En la pestaña **Facturación**, debajo del cliente que factura, agregue los
   productos o servicios con su cantidad, precio e IVA y, si quiere, un
   **detalle del ítem** (si el alumno no tiene ítems, ya aparece una fila vacía
   lista para escribir, también al quitar el último ítem; las filas sin producto
   no se guardan). Debajo, complete la **información adicional** y
   revise los **totales** (subtotal por tarifa de IVA, IVA y total, igual que
   en la factura de venta; solo cuentan los servicios activos). Guarde el
   alumno.
2. Pulse el botón **Generar factura** (ícono de recibo verde) de la barra
   superior del modal.
3. Elija la **serie**, el **mes facturado** y, si quiere, un **texto para los
   ítems sin detalle** (por ejemplo `Pensión {MES} {anio}` → «Pensión
   SEPTIEMBRE 2026»). Las líneas que tienen su propio *detalle del ítem* usan
   el suyo.
4. Revise las líneas marcadas y el resumen de facturas que se van a generar, y
   pulse **Generar**.

Cómo decide qué facturar:

- Solo aparecen los servicios **activos** y **guardados**: si acaba de cambiar
  algo en los servicios, guarde el alumno antes de generar.
- Todos los servicios activos vienen **marcados**: desmarque los que no
  correspondan ese mes (por ejemplo, una matrícula que ya se cobró). Para que
  un servicio deje de proponerse, desmarque **Activo** en la grilla.
- Si el mes elegido **ya se facturó** a alguno de los clientes, se muestra un
  aviso con el número de la factura anterior; se puede generar de todos modos
  (por ejemplo, un cobro adicional) confirmando el mensaje.
- Cada factura nace en **borrador**, con la fecha de hoy, y lleva la
  información adicional configurada en el alumno (más el correo del cliente,
  que se agrega solo). Se envía al SRI desde **Facturas
  de Venta** (o con su automatización), igual que cualquier otra.
- Si la factura no se puede emitir (por ejemplo, un Consumidor Final con un
  total de $50 o más), el mensaje indica por qué.

### Marcadores

En el detalle del ítem, en la información adicional (concepto y detalle) y en
el texto de la ventana de generación se pueden usar:

| Marcador | Se reemplaza por |
|----------|------------------|
| `{niño}` | **Niño** o **Niña** según el sexo guardado del alumno (Niño/a si no tiene). Aparece como sugerencia al escribir el concepto. |
| `{alumno}` | Apellidos y nombres del alumno. |
| `{nombres}` / `{apellidos}` | Por separado. |
| `{cedula}` | Número de identificación del alumno. |
| `{campus}` / `{curso}` | Campus y nivel/curso de la matrícula vigente. |
| `{mes}` / `{MES}` / `{anio}` / `{mes_anio}` | Mes facturado (septiembre / SEPTIEMBRE / 2026 / septiembre 2026). |

Ejemplo: concepto `{niño}` y detalle `{alumno}` → «Niña: PÉREZ ANA».

### Reglas de facturación

Los servicios del alumno siguen las mismas reglas que la **Factura de Venta**,
según la configuración de facturación del establecimiento:

- **Concepto libre**: si está activo «Permitir ingreso de registros
  libremente», al buscar un producto aparece la opción *Usar «…» como servicio
  libre*. El texto se limpia (sin espacios dobles ni saltos de línea), es
  obligatorio y admite hasta 300 caracteres; al guardar el alumno se crea como
  **servicio** en el catálogo de productos, con el precio y el IVA elegidos.
  Si la opción no está activa, solo se pueden elegir productos del catálogo.
- **IVA**: se puede cambiar en cada línea solo si está activo «editar IVA en la
  factura»; si no, el selector aparece bloqueado y se usa el IVA del producto.
  Si se elige el mismo IVA del producto, la línea sigue al producto.
- **Precio**: si la configuración no permite editar el precio, se usa el
  precio base del producto (los conceptos libres sí llevan su precio).

## Transacciones del alumno

La pestaña **Transacciones**, junto a **Facturación**, muestra el **detalle
de lo facturado** al alumno: **una fila por cada producto o servicio**, con la
referencia a la factura en que salió.

- De la factura: mes facturado, fecha de emisión, número (clic para abrir su
  PDF; al pasar el puntero se ve el cliente y cuándo se generó) y estado
  (borrador, autorizada, anulada).
- Del ítem: código, producto o servicio (con el texto del ítem debajo),
  cantidad, precio, descuento, subtotal, IVA y total.
- Al pie, la cantidad de facturas vigentes y la suma de subtotal, IVA y total.
  Las facturas **anuladas** se ven tachadas y no suman.

## Permisos

Sigue el esquema estándar de permisos por submódulo (`r/w/u/d/t`). Con
**acceso total** (`t`) se ven y gestionan los alumnos de toda la empresa; sin
él, cada usuario solo ve y gestiona los alumnos que él mismo registró
(`created_by`). Los catálogos de Campus y Niveles/Cursos tienen sus propios
permisos independientes (`modulos/alumnos-campus`, `modulos/alumnos-niveles`),
aunque normalmente se gestionan desde el propio modal de Alumno.

Para **generar facturas** desde el alumno hace falta, además, permiso para
**crear** en **Facturas de Venta**; sin él no aparece el botón. El PDF de la
pestaña Transacciones aparece solo si el usuario puede **ver** Facturas de Venta.

## Reglas de negocio

- El alumno **siempre** requiere un Cliente representante para poder
  facturarlo; no se duplica su identificación de cobro.
- Un alumno no puede tener más de un período de matrícula **vigente**
  (sin fecha de salida) al mismo tiempo; para volver a matricularlo hay que
  cerrar primero el período abierto.
- Los períodos de matrícula de un mismo alumno no pueden solaparse en fechas.
- El campus y el nivel/curso **actuales** que se muestran en el listado se
  calculan a partir del período de matrícula vigente (o el más reciente si no
  hay ninguno vigente); no se guardan como valor fijo en la ficha del alumno,
  para evitar que queden desactualizados si el historial de matrícula cambia.
- Los documentos adjuntos solo se pueden subir después de guardar el alumno
  por primera vez.
- No se puede eliminar un Campus o Nivel/Curso que tenga alumnos matriculados.

## Integraciones con otros módulos

- **Clientes**: el alumno referencia al cliente que se factura (representante).
- **Productos**: los servicios predeterminados del alumno referencian
  productos/servicios del catálogo general.
- **Configuración de Puntos de Emisión**: fuente de las series de facturación
  disponibles para fijar como preferida.
- **Facturas de Venta**: recibe las facturas generadas desde el alumno (en
  borrador). Se arman con el mismo generador que usa **Suscripciones**:
  secuencial, IVA según la configuración del establecimiento y XML.

## Errores frecuentes

- **"El cliente que factura (pestaña Facturación) es obligatorio"**: no se
  seleccionó ningún cliente en la pestaña Facturación, o se escribió texto
  en el buscador sin hacer clic en un resultado de la lista.
- **"El alumno no puede tener más de una matrícula vigente"**: ya existe un
  período de matrícula sin fecha de salida; hay que cerrarlo (ponerle fecha
  de salida) antes de agregar uno nuevo.
- **En Documentos no se puede adjuntar archivos**: el alumno todavía no se
  ha guardado por primera vez; guardarlo primero. La foto sí se puede cargar
  antes de guardar.

- **No aparece el botón Generar factura**: falta permiso para crear en
  Facturas de Venta.
- **"Los servicios del alumno cambiaron"**: se guardó el alumno (o se
  desactivó un servicio) con la ventana de generación abierta. Ciérrela y
  vuelva a abrirla.
- **"Para ventas mayores o iguales a $50.00 no se permite el uso de
  Consumidor Final"**: el cliente de esa factura es Consumidor Final; elija
  un cliente con identificación en la pestaña Facturación.
- **"Falta ejecutar en la base el script …20260924_alumnos_facturacion.sql"**:
  el administrador debe ejecutar ese script en la base de datos.
- **La cédula muestra "No encontrado"**: el número no está en el registro
  consultado o el servicio no respondió; se pueden escribir los nombres a
  mano y guardar normalmente.

## Historial de cambios

- **1.6** — Con el IVA configurado **al subtotal**, el IVA de cada línea de las facturas
  generadas se reajusta para que su suma sea exactamente el IVA sobre el
  subtotal. Antes, con muchas líneas, el XML y el PDF podían no cuadrar con el
  total.
- **1.5** — Los botones y enlaces de **PDF** de los documentos preguntan ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.4** — **Facturación desde el alumno.** La antigua pestaña **Servicios**
  se une a **Facturación**: el cliente que factura, la serie y, debajo, los
  servicios y productos, que ahora muestran IVA y total de cada línea. Se
  quita la columna **Frecuencia**: al generar, todos los servicios activos
  vienen marcados y se desmarca lo que no corresponda. Nuevo botón **Generar
  factura** en la barra superior: factura al cliente que factura, en
  borrador, del mes elegido, con aviso si ese mes ya se facturó. Nueva pestaña **Transacciones**,
  junto a Facturación, con el detalle de lo facturado (una fila por ítem, con
  la referencia a su factura). Corregido: al editar un alumno, el buscador del cliente
  que factura aparecía vacío. La pestaña Facturación suma el **detalle de
  cada ítem**, la **información adicional** de la factura (con marcadores como
  `{alumno}` o `{niño}`, que pone Niño o Niña según el sexo), los
  **totales** como en la factura de venta, el **IVA por línea** y los
  **conceptos libres**, con las mismas reglas de facturación que la factura de
  venta. Todos los mensajes y confirmaciones del módulo (alumnos, campus y
  niveles) se muestran con ventanas emergentes del sistema en lugar de avisos
  del navegador. El campo «Serie / Punto de emisión preferido»
  pasa a llamarse **Serie** y muestra las series como en la factura de venta
  (001-001), solo las activas con secuencial de facturas; si hay una sola,
  viene elegida, y se quita la opción «Usar el predeterminado de la empresa». Al elegir un producto
  o servicio, y en las líneas ya guardadas sin precio, el campo Precio
  muestra su precio base. Las listas de resultados de los buscadores de
  clientes y productos se abren por encima del modal (antes quedaban
  recortadas dentro de él), y el cuerpo del modal ya no tiene barra de
  desplazamiento propia: si el contenido no cabe, se desplaza el modal
  completo. Requiere ejecutar en la base
  `database/migrations/20260924_alumnos_facturacion.sql`.
- **1.3** — La antigua pestaña **Representante** se divide en dos:
  **Representantes**, donde se registran varias personas con datos libres
  (nombres, identificación, teléfono, relación, observación) y se marca
  quién **puede retirar** al alumno del centro educativo; y **Facturación**,
  con el cliente al que se factura y la serie preferida. El selector
  «Relación con el alumno» del cliente que factura se retira: la relación se
  registra por persona en Representantes (las relaciones ya guardadas se
  conservan en la base, no se borran). Requiere ejecutar en la base
  `database/migrations/20260924_alumnos_representantes.sql`.
- **1.2** — La **foto del alumno** pasa de la pestaña **General** a la
  pestaña **Documentos**, junto a los archivos adjuntos. La pestaña
  Documentos queda activa desde el alta, así que la foto se puede cargar al
  crear el alumno; solo la parte de adjuntar archivos espera a que el alumno
  esté guardado. La pestaña General queda únicamente con los datos personales,
  reordenados: identificación y estado académico primero; luego nombres,
  apellidos y sexo; luego fecha de nacimiento, campus y nivel/curso; y al
  final nacionalidad y observaciones (ahora en una sola línea). Campus y
  nivel salen de la grilla de Matrícula y pasan a General: editan el período
  vigente y, si no hay, lo crean con fecha de ingreso de hoy. Se agregan, como en Proveedores,
  los **favoritos** de campo (estrella) y la opción de **mostrar u ocultar
  pestañas** del modal por usuario; General y Representante no se pueden
  ocultar porque llevan los datos obligatorios. Con tipo **Cédula**, el
  número se consulta automáticamente y se autocompletan apellidos y nombres.
- **1.1** — Los botones de la barra de acciones superior del modal (nuevo
  cliente, nuevo campus, nuevo nivel/curso) pasan a mostrarse solo con su
  ícono, sin texto, igual que el modal de Facturas de Venta. La descripción
  de cada botón queda en su tooltip. Se corrigió además el botón de **nuevo
  cliente**, que no abría la ficha de cliente: ahora se comporta igual que en
  Facturas de Venta y, al guardar, el cliente creado queda seleccionado como
  representante del alumno. El selector de **tipo de identificación** del
  alumno pasa a ofrecer solo **Cédula** y **Pasaporte**. Se elimina el campo
  **Código de alumno**: ya no aparece en el formulario, ni como columna del
  listado, ni en las exportaciones a PDF y Excel, y el buscador dejó de
  considerarlo. Nunca se asignaba de forma automática, así que en la práctica
  quedaba vacío; los alumnos se identifican por sus apellidos y nombres, su
  número de identificación o su representante.
- **1.0** — Versión inicial: datos generales, representante/facturación,
  matrícula por períodos, horario individual, salud y emergencia, servicios
  y productos predeterminados, y documentos adjuntos. Catálogos de Campus y
  Niveles/Cursos con alta rápida desde el modal.
