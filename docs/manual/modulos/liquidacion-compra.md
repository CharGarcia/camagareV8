---
titulo: Liquidaciones de compra
resumen: Comprobante que emite la empresa cuando el proveedor no puede emitir factura.
categoria: Compras
ruta_modulo: modulos/liquidacion-compra
tipo: modulo
visibilidad: todos
etiquetas: liquidacion de compra, liquidacion, proveedor sin factura, comprobante 03, sri, sustento, eliminar, borrar, borrador, anular, buscar liquidacion, buscador, filtros, filtrar liquidaciones, buscar por producto, saldo pendiente, estado de pago, chips, aparecen documentos que no busque, resultados que no corresponden, la busqueda trae otros documentos, totales, subtotal, descuento, iva, redondeo, centavos, decimales, decimales de precio, calculo del iva, al subtotal, linea por linea, no cuadra, diferencia de un centavo, error en diferencias, exento, no objeto de iva, codigo del item, item sin codigo, item sin descripcion, falta el codigo, error en estructura de comprobante, rechazado por estructura, no autorizado, informacion adicional, ruc proveedor, campo que no se puede borrar, no me deja eliminar la fila, concepto muy largo, limite de caracteres, maximo 100 caracteres, value too long, no se pudo guardar la liquidacion
version: 1.13
orden: 40
estado: activo
---

La **liquidación de compra** es el comprobante que emite la propia empresa cuando
compra a alguien que no puede darle factura. En lugar de recibir el documento, la
empresa lo emite y lo envía al SRI.

## Cuándo se usa

En los casos que la normativa permite: proveedores sin RUC, personas naturales no
obligadas a facturar en ciertos supuestos, servicios ocasionales.

No es un sustituto de la factura: si el proveedor puede emitirla, corresponde
registrar una compra normal.

## Cómo se emite

1. Pulse **Nuevo**.
2. Elija el **proveedor**.
3. Indique la **fecha de emisión**.
4. Elija la **serie de emisión** y revise el **secuencial**.
5. Elija el **código de sustento tributario**.
6. Añada al menos un **ítem**.
7. Guarde y envíe al SRI.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Proveedor | Obligatorio |
| Fecha de emisión | Obligatoria |
| Serie de emisión | Obligatoria |
| Código de sustento tributario | Obligatorio |
| Secuencial | Obligatorio |
| Ítems | Al menos uno |
| Código de cada ítem | Obligatorio: el SRI lo exige en cada línea |
| Descripción de cada ítem | Obligatoria: el SRI la exige en cada línea |
| Cantidad de cada ítem | Mayor a cero |

### Ítems incompletos

**Cada ítem necesita código y descripción.** Son dos campos que el SRI exige en
todas las líneas del comprobante: si falta alguno, devuelve la liquidación con
*ERROR EN ESTRUCTURA DE COMPROBANTE* y no dice cuál es la línea que falla.

Para que eso no ocurra, el sistema avisa antes: al **guardar** y al **enviar al
SRI** revisa las líneas y, si encuentra alguna incompleta, muestra el número de
ítem y deja el cursor en el primer campo que falta. La misma comprobación se hace
en el servidor, así que una liquidación guardada hace tiempo con ítems sin código
también se detiene antes de firmarse y enviarse.

## Cómo se calculan los totales

Los totales salen de la **configuración de facturación de la empresa** (*Empresa →
Configuración*), la misma que usan las facturas de venta:

| Configuración | Qué controla en la liquidación |
|---------------|-------------------------------|
| Decimales de precio | Con cuántos decimales se muestra y se guarda el precio unitario |
| Decimales de cantidad | Con cuántos decimales se muestra la cantidad |
| Cálculo del IVA | **Línea por línea** (se redondea el IVA de cada ítem y se suman) o **Al subtotal** (se suma la base de cada tarifa y se calcula el IVA una sola vez sobre ese subtotal) |

Las dos formas de calcular el IVA pueden diferir en **un centavo**: es normal y
depende de lo que la empresa haya configurado. El mismo criterio se aplica en la
pantalla, en el PDF y en el XML que se envía al SRI, así que los tres muestran
siempre el mismo valor.

El **Subtotal** del pie del formulario es el subtotal **neto**: ya tiene restado el
descuento de cada línea. Es el número que viaja al SRI como *Subtotal sin
impuestos*, y el TOTAL es ese subtotal más el IVA.

Los subtotales por tarifa se muestran **por concepto**, no por porcentaje: *0%*,
*Exento de IVA* y *No objeto de impuesto* aparecen en líneas separadas aunque los
tres tengan tarifa cero, igual que en el PDF y en el XML.

Si al abrir una liquidación ya autorizada los totales no coinciden al centavo con
lo que usted recuerda, es porque la configuración del cálculo del IVA cambió
después de emitirla: el documento conserva los valores con los que se emitió y no
se recalcula.

## Información adicional

En la pestaña **Info. Adicional** se añaden los datos extra que acompañan al
comprobante (referencias, observaciones, datos de contacto). Son hasta 15 campos,
el tope que admite el SRI. El **concepto** admite hasta **100 caracteres** y el
**detalle** hasta **300**: el campo no deja escribir más, y si un texto más largo
llegara por otra vía se recorta al tope en vez de rechazar la liquidación.

Dos filas las controla el sistema y se reconocen por el icono de la derecha:

| Fila | Icono | Qué se puede hacer |
|------|-------|--------------------|
| **Correo del proveedor** | Candado | El concepto es fijo; el detalle se puede corregir a mano. La repone el sistema al elegir el proveedor |
| **RUC Proveedor** | Escudo | Ni se edita ni se elimina |

**RUC Proveedor** es un campo obligatorio para el SRI (Resolución
NAC-DGERCGC26-00000027): identifica al proveedor del sistema de facturación
electrónica, no al proveedor de la compra. Lo toma de la configuración general
(*Configuración → RUC Proveedor (SRI)*, solo superadministrador) y lo vuelve a
poner en cada guardado, así que la fila no tiene botón para borrarla y su valor no
se puede escribir: cualquier cambio quedaría sin efecto en el comprobante.

La fila aparece mientras la liquidación se pueda editar. En una **autorizada o
anulada** solo se ve si el campo estaba guardado: los documentos emitidos antes de
esa resolución no lo llevan, y la pantalla muestra lo que realmente viajó al SRI.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la liquidación: N°
liquidación, secuencial, fecha, proveedor, identificación, subtotal, descuento,
total y usuario; además, en las observaciones. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes. Para limpiar, borre el texto
o pulse Escape en el cuadro. Mientras busca, aparece un **círculo girando** al
final del cuadro y la tabla se ve atenuada.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** Para que el cuadro
devuelva solo liquidaciones donde se vea por qué coinciden, estos datos se
consultan en la ventana de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Clave de acceso y N° de autorización | Pestaña *Liquidación* → **N° autorización** |
| Productos o servicios de la liquidación (código y descripción) | Pestaña *Detalles* |
| Correo y Estado | Pestaña *Liquidación* |

En un comprobante electrónico la clave de acceso y el número de autorización son
el mismo número de 49 dígitos, que lleva dentro la fecha, el RUC y el número del
documento (el filtro *N° autorización* busca en los dos). Al escribir un número de
documento en el cuadro aparecían liquidaciones ajenas cuya clave contenía por
casualidad esa secuencia; ahora ese número solo encuentra la liquidación que
realmente lo tiene.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Liquidación** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), serie, secuencial, estado (borrador, autorizado, anulado), estado de pago (pendiente / abonada / pagada), estado de correo (pendiente / enviado), N° liquidación, N° autorización o clave de acceso, sustento tributario, con o sin asiento contable, con o sin retención |
| Valores | Total, subtotal, descuento, saldo pendiente y valor retenido (cada uno con mínimo y máximo) |
| Proveedor | Proveedor, identificación, usuario, observaciones |

Los selectores *Serie*, *Sustento tributario* y *Usuario* listan solo lo que la
empresa ya usó. El *estado de pago* y el *saldo pendiente* se calculan con los
pagos registrados en Egresos y las retenciones no anuladas de la liquidación.

**Pestaña Detalles** (lo que hay dentro de la liquidación). Es un único cuadro,
**Buscar libremente dentro de las liquidaciones**: escriba un producto o
servicio, un código, una forma de pago SRI, un plazo o un dato de la
información adicional, y aparece la lista de **cada línea que coincide** con la
liquidación a la que pertenece (número, fecha, proveedor y estado). Un clic en
la fila deja el listado mostrando solo esa liquidación; el ícono de la derecha
la abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Documentos del módulo

Desde la liquidación guardada están disponibles el **PDF** del documento, su
**Excel**, su **XML** y el envío por **correo** o **WhatsApp**, en la barra de
acciones al inicio del formulario.

## Eliminar una liquidación

Solo se pueden eliminar las liquidaciones en estado **borrador** —incluidas las
que llegaron así desde una migración—. Una vez emitida al SRI (autorizada) el
comprobante ya no se borra: se **anula**.

El botón **Eliminar** aparece abajo a la izquierda del formulario, y solo si su
usuario tiene el permiso de eliminar en el módulo. Al confirmar:

- La liquidación deja de aparecer en el listado (eliminación lógica: el registro
  se conserva en la base y queda anotado en la auditoría).
- Se **anula el asiento contable** de la liquidación, si tenía uno.
- Se **anulan los pagos (egresos) vinculados** que no estuvieran ya anulados.
- Se limpian los casilleros de la declaración de IVA correspondientes.

Si la liquidación tiene una **retención asociada**, el sistema no la deja
eliminar: primero elimine la retención desde el módulo *Retenciones de compra* y
vuelva a intentarlo.

## Errores frecuentes

- **"Solo se pueden eliminar liquidaciones en estado borrador"**: el comprobante
  ya fue emitido. Use **Anular** en la barra de acciones.
- **"No se puede eliminar la liquidación porque tiene una retención asociada"**:
  elimine primero esa retención en el módulo *Retenciones de compra*.
- **"La liquidación debe tener al menos un ítem"**: añada el detalle antes de
  guardar.
- **"El ítem N no tiene código"** o **"…no tiene descripción"**: complete esa
  línea. El SRI exige los dos campos y rechazaría el comprobante.
- **"No se puede enviar al SRI: el ítem N no tiene código…"**: la liquidación se
  guardó antes con esa línea incompleta. Ábrala, corrija el ítem, guarde y vuelva
  a enviarla.
- **No aparece el botón para borrar la fila "RUC Proveedor"**: es correcto, ese
  campo es obligatorio para el SRI y el sistema lo repone en cada guardado.
- **"Debe seleccionar el código de sustento tributario"**: es obligatorio para
  que el SRI acepte el comprobante.
- **El SRI rechaza el comprobante**: revise que los datos del proveedor sean
  correctos y que el sustento elegido corresponda al tipo de compra.
- **Aviso de asiento pendiente aunque el proveedor tiene sus cuentas**: desde la
  versión 1.13 el asiento resuelve las cuentas igual que una compra: primero las
  del **proveedor**; si no tiene, las de cada línea por **ítem, categoría o
  marca**; y al final la configuración General. Si el aviso persiste,
  "Ver detalle" indica la cuenta que realmente falta (p. ej. el IVA de una tarifa).

## Períodos contables cerrados

Un documento que mueve cartera, inventario o contabilidad no puede tocar un
período ya cerrado. El sistema lo comprueba en las cuatro operaciones:

| Operación | Qué se comprueba |
|-----------|------------------|
| Emitir | Que la fecha de emisión no caiga en un período cerrado |
| Modificar | La fecha nueva **y** aquella con la que está registrado |
| Anular | La fecha del documento (anular revierte sus movimientos) |
| Eliminar | La fecha del documento |

Al modificar se revisan las dos fechas a propósito: mover un documento de un
mes cerrado a uno abierto lo alteraría igual. Los períodos se abren y se
cierran en **Contabilidad → Períodos Contables**; reabrir el período permite
la operación de inmediato.

## Historial de cambios

- **1.13** — Corregido: el asiento contable de la liquidación ignoraba las cuentas
  configuradas en el **proveedor** y solo leía la configuración General, por lo
  que aparecían avisos de asientos pendientes de liquidaciones cuyo proveedor ya
  tenía sus cuentas. Ahora se aplica la misma cascada que en Compras: si el
  proveedor tiene cuentas propias, mandan; si no, cada línea toma la cuenta de su
  **ítem → categoría → marca**, y por último la General (por pagar, gasto e
  inventario).
- **1.12** — Las filas de **Info. Adicional** tienen tope en pantalla (concepto
  100 caracteres, detalle 300). Antes un concepto de más de 100 caracteres hacía
  fallar el guardado completo de la liquidación sin explicación; ahora el campo
  no deja pasar el tope y, si un texto más largo llega por otra vía, se recorta.

- **1.11** — **Aviso de ítems incompletos y campo *RUC Proveedor* protegido.**
  - Si un ítem no tiene **código** o **descripción**, el sistema avisa al guardar y
    al enviar al SRI, indicando el número de ítem y dejando el cursor en el campo
    que falta. Antes la liquidación se guardaba y se numeraba sin ruido, y el error
    aparecía recién al enviarla a autorizar, como *ERROR EN ESTRUCTURA DE
    COMPROBANTE*, sin decir qué línea lo causaba. La comprobación también corre en
    el servidor, así que alcanza a las liquidaciones guardadas antes de este cambio.
  - La fila **RUC Proveedor** de *Info. Adicional* ya **no se puede eliminar ni
    editar**: es un campo obligatorio del SRI que el sistema repone en cada
    guardado. Antes se mostraba como una fila normal, con su botón de borrar, y
    borrarla dejaba el comprobante sin el dato.

- **1.10** — Corregido: al **enviar la liquidación por correo** desde su ventana, el cuadro del
  correo del destinatario no aceptaba texto —se veía, pero al escribir no pasaba nada—.
  Ya se puede escribir la dirección con normalidad.

- **1.9** — **Los totales se calculan con la configuración de facturación de la
  empresa.** Antes la pantalla usaba siempre 2 decimales y siempre calculaba el IVA
  línea por línea, sin mirar la configuración: en una empresa con más decimales de
  precio el valor se recortaba al cargar el producto y al reabrir el documento, y en
  una configurada *Al subtotal* el IVA salía por el otro camino. Además:
  - El **Subtotal** del formulario ahora es el **neto** (con el descuento restado),
    que es lo que exige el SRI. Antes se enviaba el bruto y, con cualquier descuento,
    el comprobante se rechazaba por diferencias y el asiento contable quedaba
    descuadrado justo por el monto del descuento (la liquidación se guardaba sin
    asiento, en silencio).
  - El **IVA se guarda a centavos**. Antes se guardaba con todos sus decimales, así
    que el PDF, el XML, el asiento y los casilleros de la declaración de IVA —que lo
    suman— no cuadraban con el total del documento.
  - Los subtotales por tarifa se muestran **por concepto** (0%, Exento, No objeto)
    en vez de fundirse en un solo *Subtotal 0%*.
  - Ver *Cómo se calculan los totales*.

- **1.8** — **Una liquidación con $0.01 de saldo queda como Abonada,
  no como Pagada.** Mismo criterio que *Cuentas por Pagar* y *Egresos*: hay
  saldo mientras quede al menos un centavo. Afecta al filtro `pago:…` y al panel
  de pago del modal.


- **1.7** — Corregido: al buscar un **número de documento** en el cuadro aparecían
  también documentos que no lo tenían. La búsqueda libre miraba dentro de la **clave de
  acceso** (49 dígitos, que llevan la fecha, el RUC y el número del documento) y cualquier
  número corto caía ahí por casualidad. Ahora la clave se consulta en la ventana de
  filtros,
  junto con el **número de autorización** (el filtro *N° autorización* busca en los dos);
  los **productos o servicios** de la liquidación pasan a la pestaña *Detalles*, que sí
  muestra qué línea coincidió.

- **1.6** — **Búsqueda del listado más rápida**: el conteo y la página salen
  de una sola consulta y la condición de búsqueda se evalúa una sola vez (antes, dos: una para contar y otra para la página). Las fechas y los montos solo se comparan
  cuando lo escrito tiene números, así que buscar un nombre o un producto responde
  antes. Si se sigue escribiendo, la búsqueda anterior se cancela. Los resultados
  son los mismos que antes.
- **1.5** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias; lo que
  se escribe se busca en las columnas de la liquidación (y en autorización, clave de
  acceso, observaciones y productos), salvo Correo y Estado. Los filtros pasan a una
  **ventana propia** (botón del embudo, se aplican con *Aplicar*) con dos pestañas:
  **Liquidación** (criterios nuevos: estado de pago, estado de correo, autorización,
  sustento, con/sin asiento y retención, subtotal, descuento, saldo pendiente, valor
  retenido, usuario y observaciones) y **Detalles**, búsqueda libre dentro de los
  productos, formas de pago e información adicional. Los filtros activos se ven como
  etiquetas dentro del cuadro y la tabla se atenúa mientras busca.
- **1.4** — Corregido el **PDF** de las liquidaciones con muchas líneas. Cuando el detalle
  no cabía en una página, cada línea siguiente abría una página nueva casi vacía (41
  líneas daban 5 páginas; 80, 44), y a veces quedaba una página en blanco. Ahora el
  detalle sigue en la página siguiente con el encabezado de la tabla repetido, y el pie
  —totales, información adicional, observaciones y forma de pago— ya no se parte: si no
  cabe entero pasa completo a la página siguiente, y si cabe ya no salta sin necesidad.
  Además, una descripción o un código largos ya no se montan sobre la línea siguiente
  (pasaba con textos en mayúsculas).
- **1.3** — El módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar una liquidación cuyo período esté cerrado. Antes no se
  comprobaba en ninguna de las cuatro operaciones.
- **1.2** — Se puede eliminar una liquidación en estado borrador desde el formulario (botón Eliminar). Anula su asiento y sus pagos vinculados; bloqueada si tiene retención asociada.
- **1.1** — Nuevo botón Excel en el documento de la liquidación (junto a PDF y XML).
- **1.0** — Versión inicial.
