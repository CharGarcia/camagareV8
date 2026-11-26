---
titulo: Configuración QR (Factura Express)
resumen: Cree códigos QR para que sus clientes pidan su factura desde el celular, sin cuenta en el sistema, eligiendo los productos o servicios que usted definió.
categoria: Ventas
ruta_modulo: modulos/factura-express-config
tipo: modulo
visibilidad: todos
etiquetas: factura express, factura express qr, codigo qr, qr, qr para facturar, qr clientes, qr solicitudes, plantilla qr, nueva plantilla qr, formulario publico, formulario sin login, autofacturacion, auto facturacion, cliente pide su factura, solicitar factura, pedir factura, factura desde el celular, escanear qr, cartel con qr, imprimir qr, copiar url, enlace publico, link para clientes, consultorio, mostrador, caja rapida, factura automatica, aprobacion manual, requiere aprobacion, serie de facturacion, establecimiento, punto de emision, forma de pago, limite de solicitudes, anti spam, solicitudes por hora, mensaje de bienvenida, mensaje de agradecimiento, campos visibles, telefono, direccion, productos del formulario, servicios del formulario, cantidad editable, precio editable, preseleccionado, iva del producto, plantilla inactiva, desactivar qr, eliminar plantilla, filtros, filtrar plantillas, embudo, buscar plantilla, plantillas con pendientes
version: 1.1
orden: 0
estado: activo
---

**Factura Express por QR** permite que sus clientes pidan su factura por su
cuenta: escanean un código QR con el celular, llenan sus datos, marcan lo que
compraron y envían la solicitud, sin tener usuario en el sistema. En este
módulo (**Configuración QR**) usted arma las *plantillas* que definen qué ve el
cliente en ese formulario y cómo se factura. Las solicitudes que llegan se
revisan en [Solicitudes Factura Express](modulos/factura-express-solicitudes).

## Qué es y para qué sirve

Cada fila del listado es una **plantilla QR**. Una plantilla define:

- La **serie** (establecimiento y punto de emisión) y la **forma de pago** con que
  se emitirán las facturas.
- Los **productos o servicios** que el cliente puede elegir en el formulario.
- Si cada solicitud **requiere aprobación manual** o se **factura sola**.
- Los mensajes que ve el cliente y los datos que se le piden.

Cada plantilla tiene su propio código QR y su propio enlace público. Una
empresa puede tener varias plantillas (por ejemplo, una por consultorio o por
caja).

## Requisitos previos

- Al menos un **establecimiento** y un **punto de emisión** activos en la
  empresa: son obligatorios para guardar la plantilla.
- Los productos o servicios que va a ofrecer, creados y activos en el catálogo
  de productos, con su tarifa de IVA. El IVA de cada línea se toma del producto.
- La empresa debe estar **activa**: si no lo está, el enlace público deja de
  funcionar.

## El listado de plantillas

El listado muestra estas columnas:

| Columna | Qué muestra |
|---------|-------------|
| Nombre | El nombre interno de la plantilla |
| Descripción | La descripción interna |
| Ítems | Cuántos productos o servicios tiene la plantilla |
| Solicitudes | Si hay solicitudes por revisar, un aviso amarillo con «N pendiente(s)»; si no, el total de solicitudes recibidas |
| Estado | **Activo** o **Inactivo** |
| Acciones | Los botones **QR Solicitudes** y **QR Clientes** |

Puede buscar con **Buscar plantilla...** (busca en el nombre y la descripción),
ordenar haciendo clic en los encabezados y ocultar columnas desde el botón de
columnas. Al hacer clic en una fila se abre la plantilla para editarla.

### Filtros

El botón de **Filtros** (embudo), a la izquierda del buscador, abre la ventana
*Filtros de plantillas QR*:

| Filtro | Qué hace |
|--------|----------|
| Nombre, Descripción | Texto de la plantilla |
| Estado | Activo o Inactivo |
| Aprobación manual | *Requiere aprobación* o *Se factura sola* |
| Ítems | Rango de cantidad de productos o servicios |
| Serie | Establecimiento y punto de emisión de la plantilla |
| Forma de pago | Forma de pago con la que se facturan las solicitudes |
| Solicitudes pendientes | *Con pendientes* o *Sin pendientes* |
| Total de solicitudes | Rango de solicitudes recibidas |
| Fecha de creación | Rango de fechas, con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado* y *Este año* |

Cada filtro activo aparece como una etiqueta junto al buscador; con su **×** se
quita. A la derecha del buscador están el botón de columnas, **PDF** y
**Excel**.

### Exportar a PDF y Excel

Los dos botones descargan **las mismas plantillas que ve en pantalla**: con la
búsqueda, los filtros y el orden aplicados, pero sin paginar.

- **PDF** (hoja horizontal): encabezado con el logo y el nombre de la empresa,
  la búsqueda y filtros aplicados, un resumen (plantillas, activas, inactivas y
  solicitudes pendientes) y el listado con nombre, descripción, serie, forma de
  pago, tipo de aprobación, ítems, solicitudes, pendientes y estado.
- **Excel**: además incluye el máximo de solicitudes por hora, la fecha de
  creación y el **enlace público** de cada plantilla (la dirección que abre el
  código QR de clientes).

## Cómo se crea una plantilla

1. Pulse **Nueva Plantilla QR**.
2. En la pestaña **Configuración** escriba el nombre, elija la **Serie de
   facturación** (primero el establecimiento, luego el punto de emisión) y la
   **Forma de pago**. Decida si la plantilla está activa y si requiere
   aprobación manual.
3. En la pestaña **Productos / Servicios** pulse **Agregar línea** y, en
   *Descripción / Servicio*, escriba parte del nombre o del código: el sistema
   busca en su catálogo y, al elegir uno, llena el precio y el IVA. Repita por
   cada producto o servicio.
4. Marque en cada línea si el cliente puede cambiar la cantidad o el precio y si
   aparece marcada desde el inicio.
5. (Opcional) En la pestaña **Mensajes** escriba el mensaje de bienvenida y el
   de agradecimiento.
6. Pulse **Guardar**. Verá el aviso *«Plantilla creada correctamente.»*

Para editarla, haga clic en su fila; el título del formulario cambia a
*Editar: (nombre)*. Al guardar verá *«Plantilla actualizada correctamente.»*

## Mostrar e imprimir el código QR

Cada fila tiene dos botones de QR. Ambos abren la ventana **Código QR** con la
imagen, el enlace y los botones **Copiar URL** e **Imprimir** (imprime una hoja
con el nombre, la descripción y el QR, para colocarla en el mostrador).

- **QR Clientes**: es el QR que se coloca a la vista del público. Lleva al
  formulario donde el cliente pide su factura. Debajo de la imagen aparece
  *«URL del formulario público:»*.
- **QR Solicitudes**: es un QR para el personal de la empresa. Lleva a la
  pantalla de [Solicitudes Factura Express](modulos/factura-express-solicitudes)
  y **requiere iniciar sesión** (*«URL del panel de solicitudes (requiere
  login):»*). Si quien lo escanea tiene asignada la empresa, el sistema la deja
  seleccionada; si no, avisa *«No tienes permiso para acceder a la empresa del
  código QR.»* Cuando hay solicitudes pendientes, el botón muestra un contador
  rojo.

## Campos del formulario

### Pestaña Configuración

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre de la plantilla | Sí | Solo para identificarla internamente (máximo 150 caracteres). Aparece como título del formulario que ve el cliente |
| Límite solicitudes/hora | Sí | Cuántas solicitudes se aceptan por hora desde una misma conexión (IP) para esta plantilla. Entre 1 y 200; por defecto 10. Sirve contra el spam |
| Descripción interna | No | Texto opcional. También se muestra bajo el título del formulario público y en la hoja impresa del QR |
| Plantilla activa | — | Si está apagada, el QR muestra un mensaje de no disponible |
| Requiere aprobación manual | — | Encendido (por defecto): cada solicitud espera a que alguien la apruebe. Apagado: la factura se genera automáticamente al recibir la solicitud |
| Serie de facturación — Establecimiento | Sí | Establecimiento con que se emiten las facturas de esta plantilla |
| Serie de facturación — Punto de emisión | Sí | Solo muestra los puntos del establecimiento elegido. La casilla *Serie* muestra el resultado (por ejemplo 001-002) |
| Forma de pago | Sí | Forma de pago del SRI que llevará la factura. Por defecto *20 - Otros con utilización del sistema financiero* |
| Campos visibles en el formulario público | — | Nombre, Identificación y Correo electrónico siempre se piden. Puede activar además **Teléfono** y **Dirección** |

### Pestaña Productos / Servicios

| Columna | Qué significa |
|---------|---------------|
| Descripción / Servicio | El producto o servicio. Escriba para buscarlo en el catálogo (solo productos activos de la empresa) |
| Cant. default | Cantidad con que aparece en el formulario. Debe ser mayor a cero |
| Precio Unit. | Precio unitario sin IVA. Se llena con el precio del producto y puede cambiarlo. No puede ser negativo |
| Cant. editable | El cliente puede cambiar la cantidad |
| Precio edit. | El cliente puede cambiar el precio |
| Preseleccionado | La línea aparece marcada por defecto en el formulario |
| IVA | Lo define el producto o servicio y **no se puede editar** aquí ni lo puede cambiar el cliente |

Abajo se ve el contador **Items** y el botón **Agregar línea**. Para quitar una
línea, use la **X** al final de la fila.

### Pestaña Mensajes

| Campo | Qué significa |
|-------|---------------|
| Mensaje de bienvenida | Aparece en la parte superior del formulario público (máximo 500 caracteres) |
| Mensaje de agradecimiento | Aparece en la pantalla de confirmación tras enviar la solicitud. Si lo deja vacío, el cliente ve *«¡Gracias por tu solicitud! La hemos recibido correctamente.»* |

Las pestañas **Productos / Servicios** y **Mensajes** se pueden ocultar por
usuario desde el botón de pestañas del formulario.

## Lo que ve el cliente al escanear el QR

1. El formulario muestra el nombre de la plantilla, la descripción y el
   mensaje de bienvenida.
2. En **Tus datos** el cliente elige el **Tipo de identificación** (Cédula, RUC
   o Pasaporte) y escribe su **Identificación**. Al completar los 10 dígitos de
   la cédula o los 13 del RUC, el sistema consulta al SRI y, si encuentra datos,
   llena el **Nombre completo** y el **Correo electrónico** cuando están vacíos.
3. Completa su correo y, si la plantilla los pide, teléfono y dirección.
4. En **Servicios / Productos** marca lo que compró y, si la plantilla lo
   permite, cambia cantidad o precio. Abajo ve el subtotal, el IVA por tarifa y
   el **TOTAL**, calculados según la configuración de IVA del establecimiento
   de la serie.
5. Pulsa **Enviar Solicitud**. Si pulsa **Cancelar**, los datos se descartan.
   Si falta un dato o está mal escrito, el formulario vuelve a aparecer con el
   aviso en rojo, por ejemplo *«La cédula debe tener 10 dígitos.»*, *«El RUC
   debe terminar en 001.»* o *«El correo electrónico no tiene un formato
   válido.»*
6. Ve la pantalla **¡Solicitud enviada!** con su mensaje de agradecimiento, un
   resumen y un enlace para **consultar el estado de su solicitud** en
   cualquier momento.

Después de mostrar la confirmación, el sistema envía dos correos: uno a la
empresa (al correo registrado en los datos de la empresa) con el asunto
*Nueva solicitud Factura Express* y el botón **Ver solicitud en el sistema**, y
otro al cliente con el asunto *Hemos recibido tu solicitud de factura* y el
botón **Ver estado de mi solicitud**. Si el envío de un correo falla, la
solicitud igual queda registrada.

## Permisos

| Permiso | Qué permite |
|---------|-------------|
| Ver | Entrar al módulo, ver el listado, abrir una plantilla y mostrar o imprimir sus QR |
| Crear | Ver el botón **Nueva Plantilla QR** y guardar plantillas nuevas |
| Actualizar | Guardar cambios en una plantilla existente |
| Eliminar | Ver el botón **Eliminar** dentro de la plantilla |

Todos los usuarios con permiso de ver ven **todas** las plantillas de la
empresa; el permiso de acceso total no cambia lo que se lista en este módulo.
La revisión de solicitudes tiene permisos aparte, en
[Solicitudes Factura Express](modulos/factura-express-solicitudes).

## Reglas de negocio

- Cada plantilla recibe al crearse un enlace propio, largo y aleatorio, que no
  cambia aunque edite la plantilla. El QR impreso sigue sirviendo.
- El enlace público solo funciona si la plantilla está **activa**, **no fue
  eliminada** y la **empresa está activa**. En cualquier otro caso el cliente
  ve *«Este enlace no está disponible o ha sido desactivado.»*
- El **IVA** de una línea ligada a un producto del catálogo es siempre la
  **tarifa actual del producto**: si cambia la tarifa del producto, la
  plantilla la toma sola. Una línea escrita a mano, sin elegir un producto,
  queda con IVA 0%.
- Al editar una plantilla, sus líneas se reemplazan por las que quedan en
  pantalla al guardar. Las solicitudes ya recibidas conservan los ítems que el
  cliente envió.
- Al **eliminar** una plantilla se pide confirmación (*«¿Eliminar
  plantilla?»*, *«Los datos históricos de solicitudes se conservarán.»*). La
  plantilla no se borra físicamente; deja de aparecer en el listado y su QR deja
  de funcionar.
- Con **Requiere aprobación manual** apagado, la factura se emite en el mismo
  momento en que el cliente envía la solicitud. Si la factura no se puede
  generar (por ejemplo, porque la serie ya no está activa), la solicitud **no
  se guarda** y el cliente ve un mensaje de error.
- Toda creación, edición y eliminación de plantillas queda registrada en la
  auditoría del sistema.

## Integraciones con otros módulos

- **Productos**: las líneas se buscan en el catálogo de productos y toman de
  ahí el precio y la tarifa de IVA.
- **Establecimientos y puntos de emisión**: la serie elegida es la que usa
  cada factura generada por la plantilla, y su configuración de cálculo del IVA
  define cómo se calcula el total (ver [Cálculo del IVA](conceptos/calculo-iva)).
- **Solicitudes Factura Express**: allí llegan las solicitudes de los clientes
  para aprobarlas o rechazarlas.
- **Facturas de venta**: cuando se aprueba una solicitud (o se factura sola), se
  crea la factura de venta. Ver [Facturas de venta](modulos/factura-venta).

## Errores frecuentes

- **«El nombre de la plantilla es obligatorio.»**: escriba un nombre en la
  pestaña Configuración.
- **«El límite de solicitudes por hora debe estar entre 1 y 200.»**: corrija el
  campo *Límite solicitudes/hora*.
- **«Debe seleccionar un establecimiento.» / «Debe seleccionar un punto de
  emisión.» / «Debe seleccionar una forma de pago.»**: complete la serie de
  facturación y la forma de pago; son obligatorias.
- **«La plantilla debe tener al menos un producto o servicio.»**: agregue al
  menos una línea con descripción en la pestaña Productos / Servicios.
- **«Ítem #N: la cantidad debe ser mayor a cero.»** o **«Ítem #N: el precio no
  puede ser negativo.»**: corrija la línea indicada.
- **«Plantilla no encontrada.»**: la plantilla fue eliminada por otro usuario o
  no pertenece a la empresa seleccionada. Recargue la pantalla.
- **El cliente ve «Este enlace no está disponible o ha sido desactivado.»**: la
  plantilla está inactiva o eliminada, o la empresa no está activa. Active la
  plantilla con **Plantilla activa**.
- **El cliente ve «Ocurrió un error al procesar tu solicitud. Por favor intenta
  nuevamente.»**: puede pasar si se superó el *Límite solicitudes/hora* desde
  esa conexión, o si la plantilla factura sola y la factura no se pudo generar.
  Revise la serie de la plantilla y, si hace falta, suba el límite.

## Historial de cambios

- **1.0** — Versión inicial del manual.
- **1.1** — Buscador con ventana de **Filtros** (estado, aprobación manual, serie, forma de pago, solicitudes pendientes, fechas y más). Ahora también se puede ordenar por Descripción, Ítems y Solicitudes. Los botones **PDF** y **Excel** ya descargan el listado con los filtros aplicados.
