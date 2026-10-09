---
titulo: Empresa
resumen: Datos de la empresa, sus establecimientos y la configuración que rige a todos los módulos.
categoria: Configuración de empresa
ruta_modulo: modulos/empresa
tipo: modulo
visibilidad: admin
etiquetas: empresa, datos de la empresa, ruc, suscripcion, suscripcion vencida, vigencia, vencimiento, pago pendiente, aviso de pago, renovacion, mensualidad, cuota del sistema, establecimiento, punto de emision, logo, logo por punto de emision, logo de la caja, logo por sucursal, otra marca, quitar logo, ambiente, pruebas, produccion, configuracion, correo, email, smtp, envio de correos, cuerpo del correo, asunto, plantilla de correo, remitente, documentos legales, acuerdo de uso de datos, contrato de uso del sistema, aceptacion de documentos, documentos firmados, documentos cargados, archivos de la empresa, secuenciales, numeracion, tipos de documento, codDoc, eliminar secuencial, crear secuenciales, agregar todos los faltantes, facturas de reembolso, punto unico por empresa, punto inactivo, eliminar punto de emision con documentos, puntos duplicados, secuencial inicial, numero inicial, hueco, huecos, rellenar hueco, salto de numeracion, siguiente numero, retomar numeracion, presentacion de los items, agrupar items, agrupar por nombre, agrupar por lote, agrupar por nup, agrupar por serie, juntar lineas repetidas, sumar items iguales, mostrar lote en la factura, mostrar caducidad, mostrar unidad de medida, mostrar nup, descripcion del item, tirilla, ticket, impresion termica, modo de numeracion, numeracion por fecha, secuencial por fecha, reiniciar numeracion, reinicio anual, reinicio mensual, numeracion anual, numeracion mensual, correlativo por año, correlativo por mes, empezar de cero cada año, prefijo del año, numero con el año, volver a empezar la numeracion
version: 1.35
orden: 5
estado: activo
---

El módulo de **Empresa** guarda los datos de la compañía y la configuración que
condiciona el comportamiento del resto del sistema. Es lo primero que se
configura y lo que hay que revisar cuando algo se comporta distinto de lo
esperado en varios módulos a la vez.

## Datos generales

Razón social, RUC, nombre comercial, dirección, contacto y **logo** (que aparece
en los documentos impresos).

## Establecimientos y puntos de emisión

Cada local es un **establecimiento**, y dentro de él hay uno o varios **puntos de
emisión**. La numeración de los comprobantes depende de esta estructura: el
`001-002-000000123` de una factura son precisamente el establecimiento, el punto
de emisión y el secuencial.

### Pestaña Puntos de Emisión y Secuenciales

Los puntos de emisión y sus secuenciales se administran en **una sola pestaña**,
**Puntos de Emisión y Secuenciales**:

- **A la izquierda**, la lista de puntos de emisión (activos primero), cada uno
  con su logo (o el ícono de tienda si usa el del establecimiento), su estado y
  el botón **editar** (lápiz), que abre el punto para cambiar nombre, código,
  estado y logo, o eliminarlo. Arriba está el botón **Nuevo Punto**.
- **A la derecha**, los secuenciales del punto seleccionado (ver "Secuenciales
  por punto de emisión").

Al hacer clic en un punto se cargan sus secuenciales; al crear o editar un punto,
la página vuelve a la pestaña con ese mismo punto seleccionado.

### Logo del establecimiento

En la pestaña **Establecimiento** se sube el logo que aparece en los documentos
impresos (factura, nota de crédito, retención, liquidación de compra, guía de
remisión y recibo de venta). El logo ocupa un recuadro de **81 mm × 25.4 mm**
(relación de aspecto aproximada **3.2 : 1**, panorámico): la imagen se ajusta
completa dentro de ese recuadro sin deformarse (se centra y se reduce si hace
falta), por lo que un logo **horizontal/apaisado** aprovecha mejor el espacio que
uno cuadrado o vertical. Formato **PNG con fondo transparente** (también acepta
JPG o GIF), máximo 2 MB; resolución sugerida ~960 × 300 px o mayor, misma
proporción, para que se vea nítido al imprimir. Junto a la miniatura hay un
enlace para **descargar el logo actualmente guardado**.

### Logo por punto de emisión

Cada punto de emisión puede tener **su propio logo** (por ejemplo, una caja o
sucursal que vende con otra marca). Se carga en la pestaña **Puntos de
Emisión y Secuenciales**: al editar un punto (lápiz), el campo **Logo del Punto** permite subir la
imagen (mismos formatos, tamaño máximo y espacio en el PDF que el logo del
establecimiento) o **Quitar logo**.

- Si el punto **tiene logo propio**, todos los documentos emitidos con esa
  serie lo usan: factura (incluida la app móvil), nota de crédito, nota de
  débito, retención, liquidación de compra, guía de remisión, recibo, factura
  de reembolso, facturación de consignaciones, proforma (y su ficha de
  productos y condiciones), pedido, orden de compra, importación, ingreso,
  egreso, traspaso, consignaciones, retornos, cambios de producto, órdenes de
  car-wash, taller y servicio externo. También el PDF que se envía por correo
  y la cabecera del correo.
- Si el punto **no tiene logo**, se sigue usando el **logo del
  establecimiento**, igual que siempre.
- Los **reportes y listados** (reporte de ventas, cartera, roles de pago,
  inventario, etc.) no pertenecen a un punto y siempre llevan el logo del
  establecimiento.

En la tarjeta de cada punto se ve su logo y la leyenda *Logo propio* o *Logo
del establecimiento*. Cada cambio o retiro del logo de un punto queda
registrado en el log del sistema.

Si los comprobantes salen con una numeración que no esperaba, es aquí donde se
corrige.

Esta pestaña siempre muestra el establecimiento marcado **Activo** (solo
puede haber uno activo por empresa a la vez; si por algún error de datos
hubiera más de uno registrado, se sigue mostrando el activo, y los demás
quedan fuera del alcance de este módulo). Desde aquí el cliente edita
**nombre**, **dirección** y **logo**; el **código**, el **tipo** (Matriz/
Sucursal) y el **estado** (Activo/Inactivo) son de solo lectura — se
administran exclusivamente desde **Configuración → Empresas del sistema**.

### Eliminar un punto de emisión con documentos

Por regla general, un punto de emisión que **ya tiene documentos emitidos**
(en cualquier módulo: facturas, ingresos, egresos, notas de crédito, guías de
remisión, liquidaciones de compra, retenciones en compras, órdenes de compra o
pedidos, en cualquier ambiente) **no se puede eliminar** — se perdería el
control de esa numeración. Sí se puede seguir editando el **nombre** y el
**estado** (activar/inhabilitar); el **código** queda bloqueado.

**Excepción**: si queda **al menos otro punto de emisión con el mismo
número** en algún otro establecimiento de la misma empresa, sí se permite
eliminarlo aunque tenga documentos. El modal muestra una advertencia roja
explicando el riesgo: si ese establecimiento y número específicos se vuelven
a usar más adelante (por ejemplo, se crea un punto nuevo con el mismo código
en el mismo establecimiento), el secuencial podría chocar con la numeración
de los documentos que ese punto ya tuvo. Es una decisión explícita de quien
elimina, no una limpieza automática — el sistema no reasigna ni fusiona esos
documentos a ningún otro punto, solo deja de listar el punto eliminado.

## Ambiente: pruebas o producción

La empresa opera en **ambiente de pruebas** o en **producción**. Es la
configuración más delicada del sistema:

- En **pruebas**, los comprobantes van al entorno de pruebas del SRI y **no
  tienen validez tributaria**.
- En **producción**, son documentos reales.

Los documentos quedan marcados con el ambiente en el que se emitieron. Por eso,
al cambiar de pruebas a producción, los documentos anteriores dejan de verse en
los listados: siguen ahí, pero pertenecen al otro ambiente.

## Configuración por módulo

Desde aquí se ajustan comportamientos que afectan a módulos concretos: cómo se
presentan los ítems en la factura, el método de costeo del inventario, los
textos de los correos, entre otros.

Las **aprobaciones ya no se configuran aquí**. Todo lo que antes estaba en las
pestañas *Inventario* (aprobación de cargas) y *Pagos al Banco* (aprobación de
lotes de transferencia) se centralizó en el módulo **Aprobaciones**
(`modulos/aprobaciones-config`), junto con los demás procesos que requieren
autorización.

## Correo de comprobantes electrónicos

En la pestaña **Configuración Correo** se define cómo salen los correos que el
sistema envía cuando el SRI autoriza un comprobante (facturas, notas de crédito
y débito, retenciones, guías de remisión y liquidaciones de compra).

- **Tipo de correo**: usar el correo de Camagare o el correo propio de la
  empresa (host, puerto, SSL, usuario y contraseña). Use **Probar Envío** antes
  de activar el envío automático: pide un correo de destino y envía un mensaje
  con los datos que están en pantalla (no hace falta guardar antes). Si el
  servidor no responde o rechaza los datos, la prueba se corta en pocos
  segundos y el mensaje indica qué revisar (host y puerto, SSL/TLS, usuario o
  contraseña) junto con el detalle técnico. Gmail y Outlook exigen una
  *contraseña de aplicación*, no la clave normal de la cuenta; el puerto 587 va
  con SSL/TLS activado y el 465 usa SSL implícito.
- **Enviar correos de forma automática**: si está apagado, el comprobante no se
  envía solo al autorizarse; igual se puede enviar a mano desde el documento.
- **Asunto predeterminado del correo**: si se deja vacío, el sistema usa
  "Comprobante Electrónico Autorizado".
- **¿Cómo se envía el cuerpo del correo?**:
  - *Usar el diseño del sistema* (opción por defecto): el correo sale con el
    logo de la empresa en la cabecera, el nombre del documento y su número, una
    caja con la fecha de emisión, el número de autorización y el valor total, la
    firma con la razón social y el RUC, y un pie de confidencialidad. El texto
    que usted escriba en *Cuerpo del correo* reemplaza el mensaje por defecto,
    pero todo lo demás se mantiene.
  - *Enviar solo mi contenido*: se envía únicamente lo que usted escriba, sin el
    diseño del sistema. Escriba entonces el correo completo, con su saludo y su
    despedida. Si deja el cuerpo vacío, el sistema usa igualmente su diseño para
    no enviar un correo en blanco.
- **Cuerpo del correo**: editor de texto con formato (títulos, negritas,
  colores, alineación, listas, enlaces e imágenes).

El logo que aparece en la cabecera del correo es el del **punto de emisión** del
documento si tiene uno propio; si no, el del **establecimiento** que lo emitió
(pestaña Establecimiento). Si ninguno tiene logo, la cabecera muestra el nombre
de la empresa en texto.

El remitente que ve el destinatario es el nombre comercial de la empresa (o su
razón social si no tiene nombre comercial).

En ambos modos el correo lleva adjuntos el **PDF** y el **XML** autorizado del
comprobante.

## Columna Adicional en el modal de factura

En la pestaña **Facturación**, el interruptor **¿Mostrar la columna Adicional en los ítems de
la factura?** decide si la tabla de productos del modal de Factura de Venta muestra la columna
*Adicional* (detalle adicional por ítem), y lo mismo en el modal de Recibos de Venta. Viene
**encendido**; apáguelo si su empresa no usa ese
dato y quiere más espacio para las demás columnas. Solo cambia la pantalla: lo que ya está
guardado en cada línea se conserva y sigue saliendo en el PDF y el XML cuando tiene contenido.

## Presentación de los ítems en el comprobante

En la pestaña **Facturación** se decide cómo salen las líneas de la factura en
el documento emitido. No cambia lo que se captura en el modal: la factura sigue
guardando sus líneas tal cual, con su lote y su NUP, para el inventario, la
cartera y la contabilidad. Solo cambia **lo que se imprime y lo que se envía**.

**Agrupar los ítems** (los tres interruptores son excluyentes: al encender uno
se apagan los otros):

| Opción | Qué hace |
|---|---|
| *(los tres apagados)* | Una línea por cada ítem capturado. Es el comportamiento por defecto. |
| **Por nombre** | Junta todas las líneas del mismo producto, **sin importar el lote ni el NUP**. |
| **Por lote** | Junta las líneas del mismo producto que comparten número de lote. |
| **Por NUP / Serie** | Junta las líneas del mismo producto que comparten NUP o serie. |

Al agrupar se **suman** las cantidades, los descuentos, los totales y los
impuestos. Dos líneas solo se fusionan si además coinciden en **precio unitario,
unidad de medida e impuestos**: si difieren, quedan separadas. Es a propósito —
fusionarlas obligaría a recalcular el precio unitario como total ÷ cantidad, que
casi nunca cuadra al centavo y puede hacer que el SRI rechace el comprobante. El
total del documento no cambia nunca: agrupar solo redistribuye las mismas líneas.

**Mostrar en cada ítem**: unidad de medida, lote, fecha de caducidad y NUP se
anexan a la **descripción** del ítem, por ejemplo *Aceite 20W50 (Gal | Lote:
L-2024A | Caduca: 31-12-2027)*. Si la línea agrupa varios lotes o caducidades,
se listan todos separados por coma.

Lo configurado aplica al **PDF**, al **XML enviado al SRI** y a las **tirillas
térmicas** de Facturas de Venta, Recibos de Venta, Comandas y el POS de caja: el
comprobante electrónico y la representación impresa siempre dicen lo mismo.

## Errores frecuentes

- **Los comprobantes salen con numeración equivocada**: revise establecimiento y
  punto de emisión.
- **Desaparecieron los documentos antiguos**: se cambió el ambiente; los
  documentos de pruebas no se ven en producción.
- **El logo no sale en el PDF**: compruebe que esté cargado y en un formato
  admitido.
- **Un documento sale con un logo distinto al del establecimiento**: su punto
  de emisión tiene logo propio. Ábralo (lápiz) en la pestaña **Puntos de Emisión y Secuenciales** y use
  **Quitar logo** si debe usar el del establecimiento.

- **El correo llega con dos saludos o dos despedidas**: está usando el diseño
  del sistema y además escribió su propio saludo o firma en el cuerpo. Quite esa
  parte de su texto, o cambie a *Enviar solo mi contenido*.
- **Las imágenes que inserté en el cuerpo no se ven**: el editor guarda las
  imágenes dentro del texto y la mayoría de los correos (Gmail, Outlook) las
  bloquea. Use el logo del establecimiento, que sí se envía correctamente.
## Suscripción y vigencia del sistema: avisos de vencimiento

La tarjeta **Suscripción y Vigencia del Sistema** (pestaña Información General)
muestra la suscripción con la que la empresa paga el uso del sistema. Se busca
así, en este orden:

1. La suscripción **asignada** a la empresa en *Configuración → Empresas del
   sistema* («Suscripción que cubre a esta empresa»).
2. Si se factura a un tercero (reventa), las suscripciones de ese cliente, sin
   mostrar montos. Si tiene varias y ninguna está asignada, la tarjeta pide
   asignarla.
3. Si no, la suscripción cuyo cliente tiene el **mismo RUC** que la empresa.
4. Si no hay ninguna, la fecha de vigencia escrita a mano en la empresa.

### Cuándo se considera vencida

- Si algún período ya facturado de la suscripción tiene **saldo pendiente**, se
  toma la fecha de ese documento (el más antiguo con saldo). Se muestra el saldo
  y el número del documento.
- Si todo está pagado, se toma la fecha del **próximo cobro**.
- A esa fecha se le suman **3 días de gracia**: esa es la **fecha límite de
  pago**. Recién al pasarla la suscripción se marca **vencida**; durante la
  gracia figura como «por vencer».
- Las suscripciones canceladas no generan aviso.

El próximo cobro avanza solo apenas se genera el documento del período, aunque
no se haya pagado. Por eso el sistema mira también el saldo: si no lo hiciera,
una suscripción impaga nunca aparecería como vencida.

### Aviso en la barra superior

Un ícono de escudo aparece para **todos los usuarios** de la empresa cuando la
suscripción está vencida (muestra **!**) o cerca de vencer (muestra los días):
en los últimos 5 días si es mensual, 10 si es trimestral y 15 si es semestral,
anual o manual. Al hacer clic se abre la ventana con el detalle.

### Ventana al ingresar al sistema

Cada vez que un usuario **inicia sesión** o **cambia de empresa**, si la
suscripción de esa empresa está vencida o le faltan **2 días o menos** para la
fecha límite de pago, aparece una ventana con la empresa, la fecha del período,
la fecha límite de pago, los días de atraso o los
que faltan, el saldo pendiente y la periodicidad. Se cierra con **Entendido** y
vuelve a salir en el siguiente ingreso mientras no se pague. Quien tiene acceso
al módulo Empresa ve además el botón **Ver detalle**.

Al registrar el cobro del documento pendiente, el aviso desaparece en pocos
minutos y la ventana deja de salir en el siguiente ingreso.

## Documentos Legales y Archivos de la Empresa

En la pestaña **Información General**, debajo de la tarjeta de Suscripción y
Vigencia, hay una tarjeta con dos bloques:

- **Documentos Legales**: el estado del **Acuerdo de Uso de Datos** y el
  **Contrato de Uso del Sistema**. Muestra un badge — **Sin enviar**,
  **Pendiente de aceptación** o **Aceptado** — la fecha de envío, el correo al
  que se enviaron y, si ya se aceptaron, quién los aceptó y cuándo. Cada
  documento tiene un botón **Ver PDF** para revisar su contenido: si ya se
  enviaron, abre la versión exacta que se envió; si todavía no se han enviado,
  abre la versión vigente, para poder revisarlos **antes** de enviarlos. Si
  hubo más de un envío, un desplegable "Ver envíos anteriores" muestra el
  historial.
  - **Botón "Enviar" / "Reenviar documentos legales"** (pequeño, debajo de la
    tabla de los dos documentos, para no confundirlo con *Guardar Información
    General*): aparece mientras el
    estado sea **Sin enviar** o **Pendiente de aceptación** (para poder
    insistir con un reenvío si el destinatario no llegó a aceptar). Cualquier
    usuario con permiso de actualizar sobre este módulo (no solo el
    superadministrador) puede enviarlos al correo registrado de la empresa
    desde aquí. El botón desaparece en cuanto el estado pasa a **Aceptado**:
    reenviar documentos ya aceptados sigue siendo exclusivo del
    superadministrador, desde **Configuración → Empresas del sistema**.
- **Otros Documentos Cargados**: los archivos que el superadministrador sube
  manualmente para la empresa (RUC, licencia, poder, contratos, etc.) desde
  **Configuración → Empresas del sistema**, con su tipo, descripción, fecha y
  un botón de descarga. Este bloque es solo de consulta: subir o eliminar
  estos archivos sigue siendo una acción exclusiva de Empresas del sistema.

## Secuenciales por punto de emisión

En la pestaña **Puntos de Emisión y Secuenciales** se configura, por cada **punto de emisión**, el
número inicial de cada tipo de comprobante (factura, nota de crédito, ingreso,
egreso, pedido, etc.).

### Cómo se elige el siguiente número

El sistema **no lleva un contador aparte**: cada vez que se va a emitir un
documento mira los que ya existen en ese punto de emisión y en ese ambiente
(pruebas o producción), y toma el **primer número libre a partir del inicial
configurado**. Dos reglas, y de ahí salen todos los casos:

1. **Si el número inicial está libre, ese es el que se usa** — aunque ya
   existan documentos con números mayores. El sistema rellena el hueco.
2. **Si está ocupado, se busca el primer libre por encima de él.** Si no hay
   ninguno, sigue al mayor emitido.

Nunca asigna un número **menor** al inicial configurado.

| Inicial configurado | Documentos que ya existen | Siguiente número |
|---|---|---|
| 5 | solo el 11 | **5** — el 5 está libre, se rellena el hueco |
| 1 | del 1 al 10 | **11** — no hay huecos, sigue al mayor |
| 5 | el 5, el 6 y el 11 | **7** — primer libre por encima del inicial |
| 20 | el 1, el 2 y el 3 | **20** — nunca por debajo del inicial |
| 5 | ninguno | **5** — primer documento de la serie |

Por eso, **subir el número inicial salta los números anteriores** y **bajarlo
hace que el sistema vuelva a ofrecer los huecos** que hayan quedado por
debajo. Es la forma de retomar una numeración que venía de otro sistema o de
un talonario físico.

Un documento **eliminado** libera su número: vuelve a estar disponible para el
siguiente que se emita en esa serie. Un documento **anulado**, en cambio,
conserva el suyo.

### Modo de numeración: consecutivo o por fecha de emisión

Cada tipo de documento elige **cómo** se calcula su siguiente número. La opción
está junto al número inicial, en la misma fila del tipo.

| Modo | Qué hace | Cómo se ve el número |
|---|---|---|
| **Consecutivo** (el de siempre) | Un correlativo corrido que nunca se reinicia | `000000017` |
| **Por fecha de emisión — Anual** | El correlativo vuelve a empezar cada año, según la fecha del documento | `202600017` (documento 17 del año 2026) |
| **Por fecha de emisión — Mensual** | El correlativo vuelve a empezar cada mes | `202609017` (documento 17 de septiembre de 2026) |

En modo **por fecha**, el periodo va como **prefijo** dentro de los mismos nueve
dígitos de siempre: el **año siempre con 4 dígitos** y, en mensual, el **mes con
2**. El correlativo se lleva lo que sobra — 5 dígitos en anual, 3 en mensual — así
que el número sigue siendo único dentro de la serie y nunca se repite entre
periodos.

Eso da **99.999 documentos al año**, o **999 al mes**, por punto de emisión. Si un
periodo llegara a quedarse corto, el número **gana un dígito** (`2026091000`) en
vez de invadir la numeración del periodo siguiente.

Todo lo demás funciona igual: dentro de cada periodo se sigue rellenando el
primer hueco libre y respetando el número inicial configurado. Lo único que
cambia es que el sistema **solo mira los documentos de ese mismo periodo** para
decidir el siguiente número.

**Qué tipos lo permiten.** Solo los documentos **internos**: Ingresos, Egresos,
Traspasos, Recibos de venta, Proformas, Pedidos, Órdenes de compra,
Importaciones, Consignaciones (ventas, retornos y facturación), Cambios de
productos y las órdenes de servicio (car-wash, taller, servicio externo). Los
que se envían al SRI — facturas, notas de crédito y débito, facturas de
reembolso, guías de remisión, liquidaciones de compra y retenciones de compra —
numeran **siempre** de forma consecutiva: su secuencial forma parte de la clave
de acceso y esa numeración no admite reinicios. En esos tipos la opción ni
siquiera aparece.

**Al cambiar el modo, tener en cuenta:**

- Solo afecta a los documentos **nuevos**. Los ya emitidos conservan su número.
- Al cambiar la **fecha** de un documento que se está creando, su número se
  recalcula solo, para que caiga en el periodo correcto. Una vez guardado, el
  número queda fijo aunque después se le cambie la fecha.
- Se puede volver a **Consecutivo** cuando se quiera: la serie retoma donde
  estaba (si iba por el 17, sigue en el 18), sin arrastrar los números con
  prefijo de periodo.
- Al pasar de **mensual** a **anual** dentro del mismo año, los números nuevos
  arrancan por debajo de los ya emitidos (`202600001` es menor que `202609017`).
  No se repiten y no hay ningún riesgo de choque, pero durante ese año el listado
  ordenado por número deja de coincidir con el orden cronológico. De un año al
  siguiente no pasa: como el año va delante y completo, `202700001` siempre queda
  por encima de cualquier número de 2026.

- **Agregar un tipo puntual**: el selector **"Agregar Tipo Documento"** solo
  ofrece los tipos que todavía faltan en ese punto, sea un punto nuevo (sin
  ningún secuencial) o uno que ya tiene varios. Sirve para volver a agregar
  un tipo que se eliminó, o para un tipo personalizado ("Otro").
- **Agregar todos los faltantes**: junto al botón "Agregar" hay un botón
  **"Agregar todos los faltantes"** que agrega de una vez todos los tipos que
  todavía faltan en ese punto, con el mismo resultado que agregarlos uno por
  uno desde el selector (mismas reglas de no duplicar ni mezclar codDoc).
- **Eliminar un tipo**: el ícono de papelera junto a cada tipo lo elimina
  (baja lógica) — solo ese tipo, no afecta a los demás. El sistema
  **bloquea la eliminación** si ese tipo ya tiene documentos emitidos en ese
  punto — hay que dejarlo como está, no se puede perder el control de una
  numeración ya usada.
- El nombre de cada tipo se edita con el ícono de lápiz, y debe coincidir
  **exacto** (mayúsculas y tildes incluidas) con el nombre que espera el
  módulo correspondiente; si no coincide, ese módulo no toma la numeración
  configurada aquí.
- **Eliminar el punto de emisión**: cuando un punto se queda **sin ningún**
  tipo de secuencial configurado (por ejemplo, tras eliminarlos todos), en su
  lugar aparece el botón **"Eliminar este punto de emisión"** — mismo
  resultado y misma validación que el botón **Eliminar** del punto (lápiz): si ya tiene documentos emitidos, solo se puede eliminar cuando
  queda **al menos otro punto con el mismo número** en otro establecimiento
  de la empresa (el modal muestra una advertencia roja en ese caso); si no
  queda ninguno, sigue bloqueado por completo. Ver más abajo, "Eliminar un
  punto de emisión con documentos".
- **Tipos con un único punto por empresa**: "Facturas de reembolso" solo
  puede estar configurada en **un** punto de emisión de toda la empresa (el
  resto del sistema asume que existe uno solo). Si ya está en otro punto, no
  aparece en el selector "Agregar Tipo Documento" de este; para moverla, hay
  que **eliminarla** del punto actual antes de poder agregarla en otro.
- **Puntos inactivos**: la lista de puntos de la izquierda muestra **todos**
  los puntos de emisión, activos e inactivos (badge **Inactivo**) — por
  ejemplo, el punto dedicado a **Facturas de Reembolso** que se crea
  automáticamente (inactivo) al dar de alta la empresa. Un punto inactivo se
  puede configurar aquí igual que cualquier otro, incluso con **otros tipos
  de documento** además del que le dio origen, pero no podrá **emitir**
  documentos hasta activarlo desde su botón **editar** (lápiz).

## Operadoras de transporte comercial (placa en la factura)

La marca **"Operadora de transporte comercial (excepto taxis)"** la define el
**superadministrador** al crear o editar la empresa en **Configuración → Empresas
del sistema**. Si la empresa está marcada, la factura pide la **placa del
vehículo** como campo obligatorio y la incluye en el XML y el PDF, según la Ficha
Técnica SRI v2.34 (Anexo 25). No aplica para taxis ni para socios o accionistas
de taxis.

## Historial de cambios

- **1.35** — La tarjeta Suscripción y Vigencia ya no le indica al cliente que configure
  *Empresas del sistema* (no tiene acceso): ve un texto neutro que lo remite a soporte. Las
  indicaciones de configuración quedan solo para el superadministrador.
- **1.34** — Se quita el interruptor **Cancelar renovación** de la tarjeta Suscripción y
  Vigencia (datos manuales). Guardar la ficha ya no modifica ese dato.
- **1.33** — Se agregan **3 días de gracia**: la suscripción se marca vencida recién 3 días
  después de la fecha del documento con saldo (o del próximo cobro); la ventana muestra la
  fecha límite de pago.
- **1.32** — Avisos de vencimiento de la suscripción del sistema: la suscripción se
  considera vencida si un período facturado tiene saldo pendiente (antes solo se miraba el
  próximo cobro, que avanza aunque no se pague). El ícono de la barra superior lo ven todos los
  usuarios de la empresa y abre el detalle; además, al iniciar sesión o cambiar de empresa sale
  una ventana si la suscripción está vencida o vence en 2 días o menos. El aviso usa ahora la
  misma búsqueda de suscripción que la tarjeta (antes no veía la reventa ni las sucursales con
  el mismo RUC). La tarjeta vuelve a mostrar el aviso «Falta asignar» cuando el cliente
  facturado tiene varias suscripciones.
- **1.31** — **Probar Envío** (Configuración Correo) ya no deja el sistema colgado cuando el
  host, el puerto o la clave están mal: la prueba se corta a los pocos segundos, el resto de la
  sesión sigue respondiendo mientras tanto y el error explica qué revisar.
- **1.30** — Nuevo interruptor en **Facturación**: *¿Mostrar la columna Adicional en los ítems
  de la factura?* Encendido por defecto; apagado, la tabla de productos del modal de Factura de
  Venta ya no muestra la columna *Adicional* (lo guardado y el PDF no cambian). Requiere el SQL
  `database/20261005_establecimiento_mostrar_columna_adicional.sql`.
- **1.29** — El **Cálculo del IVA** de Facturación aplica a los documentos
  emitidos con las series de ese establecimiento (antes los módulos usaban la del
  primer establecimiento). Ver [Cómo se calcula el
  IVA](conceptos/calculo-iva).
- **1.28** — Se quita de la pestaña **Inventario** el aviso "Aprobación de cargas de
  inventario"; esa configuración sigue en el módulo **Aprobaciones**.
- **1.27** — Las pestañas **Puntos de Emisión** y **Secuenciales** se unen en una sola,
  **Puntos de Emisión y Secuenciales**: a la izquierda los puntos (con su logo, estado,
  botón editar y **Nuevo Punto**) y a la derecha los secuenciales del punto elegido.
- **1.26** — Al guardar un cambio, el módulo se queda en la **misma pestaña** donde se
  estaba (antes, los guardados que recargan la página volvían a *Datos generales*). En
  **Secuenciales** también vuelve al mismo punto de emisión. Los enlaces de otros
  módulos a una pestaña concreta (p. ej. *Empresa → Establecimientos*) ahora abren
  esa pestaña.
- **1.25** — **Logo por punto de emisión**: cada punto puede tener su propio logo
  (pestaña Puntos de Emisión). Los documentos de ese punto, su PDF por correo y la
  cabecera del correo lo usan; si el punto no tiene logo, se sigue usando el del
  establecimiento. Ver "Logo por punto de emisión".
- **1.24** — Las opciones de la pestaña **Facturación** apagadas ahora se respetan en
  todo el inventario: con **"La facturación afecta al inventario"** apagada, facturas,
  recibos, notas de crédito y órdenes de car-wash, taller y servicio externo ya no
  mueven stock (antes lo movían igual porque la opción guardada como *false* se leía
  como activa); con **"Obligatorio usar Lotes"** apagado, la línea sin lote toma el lote
  que vence primero. Una empresa que trabaja con inventario debe tener la primera
  opción **activada**.
- **1.22** — Se documenta **Presentación de los ítems en el comprobante** y se
  agrega la opción **Agrupar los ítems por nombre**, que junta las líneas del
  mismo producto sin importar el lote ni el NUP (antes solo se podía agrupar
  por lote o por NUP). Lo configurado ahora también se aplica a las **tirillas
  térmicas** —Facturas de Venta, Recibos de Venta, Comandas y POS de caja—, que
  hasta ahora imprimían siempre una línea por ítem aunque el PDF saliera
  agrupado.

- **1.23** — Nueva opción **Modo de numeración** por tipo de documento: además del
  correlativo corrido de siempre, ahora se puede numerar **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Solo
  para documentos internos; los que se envían al SRI siguen numerando de forma
  consecutiva.
- **1.21** — Se explica con ejemplos **cómo se elige el siguiente número** de un
  comprobante: el sistema rellena el primer hueco libre a partir del inicial
  configurado y, si no hay huecos, sigue al mayor emitido. Se aclara qué pasa al
  subir o bajar el número inicial y la diferencia entre eliminar y anular.

- **1.20** — Nueva excepción a la regla de "no se puede eliminar un punto de
  emisión con documentos": ahora sí se permite, siempre que quede al menos
  otro punto con el mismo número en otro establecimiento de la empresa (con
  advertencia explícita del riesgo de numeración si ese establecimiento y
  número se reutilizan más adelante). Ver "Eliminar un punto de emisión con
  documentos".

- **1.19** — Pestaña Secuenciales: cuando el punto seleccionado se queda sin
  ningún tipo de secuencial configurado, aparece el botón **"Eliminar este
  punto de emisión"** — reutiliza la misma validación de la pestaña Puntos de
  Emisión (bloqueado si ya tiene documentos emitidos).

- **1.18** — Pestaña Secuenciales: corregido **"Agregar todos los
  faltantes"** — al crear varias filas nuevas en el mismo instante, podían
  quedar con la misma clave interna y el navegador solo enviaba la última de
  cada grupo repetido al guardar, perdiendo las demás en silencio. También:
  la lista de puntos de emisión ahora muestra primero los **Activos**, y cada
  uno lleva un badge — **verde** (Activo) o **rojo** (Inactivo) — en vez de
  mostrar el badge solo cuando estaba inactivo.

- **1.17** — Corregido un bug de fondo que afectaba a **todo el sistema**, no
  solo a este módulo: `App\models\Empresa::getEstablecimientos()` (usado por
  ~100 controladores — Factura de Venta, Ingresos, Egresos, Pedidos, etc. —
  para saber con qué establecimiento opera la empresa) ordenaba por código en
  vez de priorizar el Activo. Si una empresa tenía un establecimiento viejo
  con código más bajo pero inactivo, esos módulos seguían operando sobre él
  en vez del activo real — por ejemplo, dejando vacío el selector de Serie en
  Factura de Venta. Ver también [Facturas de Venta](factura-venta.md).

- **1.16** — Se retira el selector de establecimientos agregado en 1.14/1.15
  (no hacía falta: este módulo solo opera sobre el establecimiento Activo).
  Además de **Estado**, ahora **Código** y **Tipo** también son de solo
  lectura aquí — los tres se administran exclusivamente desde Empresas del
  sistema; el cliente solo edita nombre, dirección y logo.

- **1.15** — Corregido: el selector de establecimientos agregado en 1.14 no
  debía permitir cambiar cuál está Activo desde este módulo. El campo Estado
  ahora es de solo lectura aquí; activar/desactivar sigue siendo exclusivo de
  Empresas del sistema.

- **1.14** — Cuando una empresa tiene más de un establecimiento registrado
  (dato anómalo, p. ej. de una migración vieja), la pestaña Establecimientos
  muestra un selector para ver/editar cualquiera de ellos.

- **1.13** — La pestaña Establecimientos ahora siempre muestra el
  establecimiento marcado **Activo** de la empresa (antes mostraba el más
  antiguo, sin importar su estado). Solo puede haber un establecimiento
  activo por empresa a la vez.

- **1.12** — Corregido: al eliminar un punto de emisión, ahora también se dan
  de baja los tipos de secuencial que tenía configurados (antes quedaban
  huérfanos). Esto causaba que un tipo de "único punto por empresa" (p. ej.
  Facturas de reembolso) siguiera bloqueado en cualquier punto nuevo aunque
  el punto original que lo tenía ya se hubiera eliminado. Requiere correr en
  el servidor la migración
  `20260820_limpiar_secuenciales_huerfanos_punto_eliminado.sql` para reparar
  los huérfanos que ya existan en la base de datos.

- **1.11** — Pestaña Secuenciales: se quita el botón "CREAR TODOS LOS TIPOS DE
  SECUENCIALES" que solo aparecía en un punto sin secuenciales — ahora el
  selector "Agregar Tipo Documento" y "Agregar todos los faltantes" están
  siempre disponibles, incluso en un punto nuevo. Se agrega la regla de
  **"un único punto por empresa"** para tipos como Facturas de reembolso: no
  se puede configurar en más de un punto de emisión a la vez. Corregido
  además un bug donde eliminar un tipo podía dejar en blanco (visualmente,
  sin tocar la base de datos) el resto de tipos del mismo punto, por una
  llamada a una función ya retirada.

- **1.10** — Nuevo botón **"Agregar todos los faltantes"** junto a "Agregar"
  en la pestaña Secuenciales: agrega de una vez todos los tipos de documento
  que aún faltan en el punto seleccionado (aunque ya tenga algunos
  configurados), respetando las mismas reglas de no duplicar ni mezclar
  tipos del mismo codDoc SRI.

- **1.9** — Corregido: la pestaña Secuenciales ocultaba los puntos de emisión
  **inactivos** (incluido el punto dedicado a Facturas de Reembolso, que
  nace inactivo al crear la empresa), así que no se podían ver ni
  configurar. Ahora se listan todos, marcados con el badge **Inactivo**, y se
  pueden configurar con cualquier tipo de documento — no solo el que les dio
  origen — antes de activarlos.

- **1.8** — Pestaña Secuenciales: el botón que crea los secuenciales de un
  punto nuevo ahora crea **todos los tipos de documento soportados** (antes
  solo 10 "estándar"), sin duplicar los que ya existan ni mezclar tipos que
  comparten codDoc SRI. Se agrega el ícono de papelera para **eliminar** un
  tipo de secuencial ya configurado, bloqueado cuando ese tipo ya tiene
  documentos emitidos en ese punto.

- **1.7** — Las aprobaciones dejan de configurarse aquí: se retira la pestaña
  **Pagos al Banco** y el bloque de aprobación de la pestaña **Inventario**.
  Ambas se centralizaron en el módulo **Aprobaciones**.
- **1.6** — El botón de envío de documentos legales ahora también aparece en
  estado "Pendiente de aceptación" (antes solo en "Sin enviar"), como
  "Reenviar documentos legales", para poder insistir cuando el destinatario no
  llegó a aceptar. Sigue ocultándose una vez que el estado es "Aceptado".

- **1.5** — En la tarjeta "Documentos Legales" ahora se puede previsualizar el
  PDF de cada documento aunque todavía no se haya enviado (usa la versión
  vigente), y aparece un botón "Enviar documentos legales" mientras el estado
  sea "Sin enviar", disponible para cualquier usuario con permiso de
  actualizar sobre el módulo (antes solo se podía enviar desde Empresas del
  sistema).

- **1.4** — Se agrega, en Información General, la tarjeta de solo lectura
  "Documentos Legales" (estado del Acuerdo de Uso de Datos y el Contrato de
  Uso del Sistema, con enlaces a los PDF) y "Otros Documentos Cargados" (los
  archivos subidos manualmente desde Empresas del sistema).

- **1.3** — Se rediseña el correo de comprobantes autorizados (logo, datos del
  comprobante y firma de la empresa) y se documenta la pestaña Configuración
  Correo, incluida la nueva opción para enviar solo el contenido propio.

- **1.2** — Se documenta el tamaño exacto del logo en el PDF (81×25.4 mm) y el
  enlace para descargar el logo actualmente guardado en la pestaña
  Establecimientos.
- **1.1** — Se documenta la marca "Operadora de transporte comercial" (se define
  en Configuración → Empresas del sistema; placa del vehículo obligatoria en la
  factura, normativa SRI 2026).
- **1.0** — Versión inicial.
