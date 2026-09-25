---
titulo: Retornos de consignación
resumen: Devolución de la mercadería consignada que el cliente no vendió.
categoria: Ventas
ruta_modulo: modulos/retornos-cv
tipo: modulo
visibilidad: todos
etiquetas: retorno, retornos, observaciones, columna observaciones, ver observaciones, notas del retorno, comentarios, columnas del listado, ordenar listado, ocultar columnas, buscar retorno, buscador, filtros, filtrar retornos, buscar por producto, buscar por lote, buscar por NUP, chips, devolucion de consignacion, mercaderia no vendida, reingreso, saldo consignado, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, buscar por numero de consignacion, agregar consignacion, numero de consignacion, serie inactiva, punto de emision inactivo, permiso actualizar, no puedo guardar, no tengo permiso para esta accion, costo del retorno, costo promedio, retorno a costo cero, aparecen documentos que no busque, resultados que no corresponden, buscar por producto en el listado, cambiar estado, estado del retorno, anular retorno, pasar a borrador, emitir retorno, selector de estado, columna bodega en el pdf, bodega del retorno, a que bodega regresa, total de cantidades, suma de cantidades, total del pdf, fila total, vencimiento, fecha de vencimiento, caducidad, fecha de caducidad, columna vencimiento, vence, lote vencido, no se ve el vencimiento, asiento sigue a la consignacion, no contabilizar consignaciones, retorno sin asiento, modulos que contabilizan, imprimir, impresora
version: 1.21
orden: 46
estado: activo
---

Un **retorno** es la devolución de la mercadería consignada que el cliente no
logró vender. Es la entrada espejo de la consignación: lo que salió del almacén
vuelve a entrar.

## Cómo funciona

1. Se **agrega la consignación por su número**. El **cliente se llena solo** con
   el de esa consignación: no hay que buscarlo aparte.
2. La tabla muestra **únicamente los ítems de esa consignación** que siguen
   pendientes de devolver.
3. Se indican las cantidades que regresan.
4. Al registrar el retorno, la mercadería **vuelve al inventario** al **mismo
   costo con que salió en la consignación**, así el costo promedio del producto no
   cambia. Si esa consignación es antigua y su salida quedó sin costo, entra al
   costo promedio del producto. Pasarlo a Borrador, anularlo o eliminarlo saca la
   mercadería con ese mismo costo.

Mientras un retorno esté **emitido o en borrador**, la consignación de origen no se
puede editar ni eliminar.

## El asiento sigue a la consignación de origen

El asiento del retorno es el inverso del de la consignación (Debe *Inventario* /
Haber *Mercadería en consignación*, a costo). Por eso **solo se genera por las
líneas cuya consignación de origen tiene asiento**.

Si la empresa no contabiliza las consignaciones (Configuración contable →
*Módulos que contabilizan*), la mercadería nunca salió de *Inventario* y el retorno
no genera asiento. La pestaña *Asiento contable* lo explica y el retorno no
aparece como pendiente en el aviso de Balance ni de Estados Financieros.

## Agregar la consignación por su número

El campo **Agregar consignación** busca entre las consignaciones **entregadas**
que aún tienen saldo por devolver. Sirve escribir:

- el número completo (`001-001-000000012`),
- solo el secuencial (`000000012` o `12` — los ceros de relleno no importan),
- o el nombre / identificación del cliente, si no se tiene el número a mano.

Al elegir una de la lista:

- el **Cliente** queda fijado con el de esa consignación (es un campo de solo
  lectura: lo determina la consignación, no se teclea);
- sus ítems pendientes se cargan en la tabla;
- aparece una **etiqueta con el número** encima de la tabla.

Se pueden agregar **varias consignaciones del mismo cliente** en un mismo
retorno: cada una suma sus ítems y su propia etiqueta. Para quitar una, se pulsa
la **×** de su etiqueta y sus filas salen de la tabla. Si se intenta agregar una
consignación de **otro cliente**, el sistema avisa y no la agrega — un retorno
es siempre de un solo cliente.

## Devoluciones parciales

No hace falta devolver todo de una vez: se pueden registrar varios retornos
parciales sobre la misma consignación. El sistema lleva el **saldo** de lo que
sigue en poder del cliente:

`saldo = consignado − retornado − facturado − entregado a cambio`

Ese saldo es el dato clave: cuadra siempre lo entregado con lo vendido, lo
devuelto y lo que el cliente se quedó como reposición en un
[Cambio de productos](modulos/cambio-producto-cv). Una unidad consignada que se
entregó a cambio ya es del cliente y **no aparece** para retornar: el cambio la
registra como facturada en *Facturación de consignaciones*, así que cuenta dentro
de *facturado*.

## Cambiar el estado del retorno

Al abrir un retorno ya guardado, su **Estado** (*Borrador*, *Emitida* o
*Anulada*) se cambia en el selector que está a la **derecha de la barra de
botones** (PDF, Excel, correo y WhatsApp), arriba del formulario. El cambio se
aplica en ese momento, sin pulsar Guardar.

- Pasar a *Anulada*, o de *Emitida* a *Borrador*, pide confirmación: si el
  retorno estaba emitido, la mercadería vuelve a salir del inventario y el saldo
  queda libre para otro retorno.
- Solo un retorno en *Borrador* se puede editar.
- Hace falta el permiso **Actualizar**; sin él, el selector se ve pero no se
  puede cambiar.
- En un retorno nuevo el selector no aparece: primero se guarda.

## Exportar

El botón **PDF** del comprobante descarga el retorno con su detalle en columnas:
**Código, Descripción, Bodega, Lote, NUP, Vence y Cantidad**. La columna *Bodega*
indica a qué bodega volvió cada producto y *Vence* la fecha de vencimiento de la
unidad; si la línea no tiene ese dato registrado se muestra un guion. El listado
cierra con una fila **TOTAL** que, bajo la columna *Cantidad*, suma todas las
cantidades retornadas. El PDF que se envía por correo es el mismo.

En la barra de acciones del comprobante, junto al botón **PDF**, hay un botón
**Excel** que descarga el detalle del retorno (código, descripción, lote, NUP,
vencimiento y cantidad) en una hoja de cálculo. Requiere que el retorno esté
guardado.

Cuando el retorno tiene **muchos productos**, el listado del PDF continúa en
las páginas siguientes y **cada página repite la fila de encabezados**. Ninguna
fila se parte entre dos hojas, y el motivo, las observaciones y las firmas se
mantienen completos: si no caben en lo que resta de página, pasan enteros a la
siguiente.

Arriba del listado hay otro par de botones **PDF** y **Excel** que exportan la
**lista completa de retornos** tal como se esté viendo: respetan el buscador,
los filtros y el orden aplicados, y salen todas las filas que calcen, no solo
la página en pantalla. Llevan la fecha, el secuencial, el cliente con su
identificación, el motivo, las observaciones, el total y el estado.

## Columnas del listado

La tabla muestra, por cada retorno: **Fecha**, **Secuencial**, **Cliente**,
**Motivo**, **Observaciones** y **Estado**.

- **Observaciones** muestra lo que se escribió en el campo *Observaciones* del
  retorno. Si el texto es largo se corta con puntos suspensivos (…); al pasar el
  mouse sobre la celda se lee completo. Si el retorno no tiene observaciones, la
  celda queda en blanco.
- Pulse el **encabezado** de una columna para ordenar por ella; un segundo clic
  invierte el orden. Al ordenar por **Motivo** u **Observaciones**, los retornos
  que no tienen ese dato quedan siempre al final.
- El botón de **columnas** (junto al buscador) permite ocultar o volver a mostrar
  cualquiera de ellas; la elección se guarda para cada usuario.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del retorno: fecha, número
(serie y secuencial), cliente, motivo y observaciones. Puede escribir varias
palabras en cualquier orden y no importan mayúsculas ni tildes. Mientras busca,
aparece un **círculo girando** al final del cuadro y la tabla se ve atenuada.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** El cuadro devuelve
solo retornos donde se vea por qué coinciden; el resto se consulta en la ventana
de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Productos retornados, lote y NUP | Pestaña *Detalles* |
| N° de la consignación de origen | Pestaña *Detalles* |
| RUC o cédula del cliente | Pestaña *Retorno* → **RUC / cédula** |
| Puntos de partida y llegada, total, responsable y usuario | Pestaña *Retorno* |
| Estado | Pestaña *Retorno* |

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Retorno** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha del retorno (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, emitida, anulada), serie, Nº retorno, secuencial, consignación de origen, con o sin asiento contable, responsable de traslado, usuario que registró |
| Valores | Total, subtotal e IVA (cada uno con mínimo y máximo) |
| Cliente | Cliente, RUC / cédula, motivo, observaciones |

Los selectores *Serie*, *Responsable de traslado* y *Usuario que registró*
listan solo lo que la empresa ya usó en sus retornos.

**Pestaña Detalles** (lo que hay dentro del retorno). Es un único cuadro,
**Buscar libremente dentro de los retornos**: escriba un producto, un código, un
lote, un NUP, una fecha de caducidad, una bodega o el número de la consignación
de origen, y aparece la lista de **cada línea que coincide** con el retorno al
que pertenece (número, fecha, cliente y estado). Un clic en la fila deja el
listado mostrando solo ese retorno; el ícono de la derecha lo abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Quién ve cada documento

Depende del permiso **Acceso total** del módulo (se administra en
*Configuración → Permisos de módulos*):

- **Con acceso total**: ve y gestiona los documentos de toda la empresa.
- **Sin acceso total**: solo los que creó ese mismo usuario. El listado ya los
  filtraba; ahora el alcance es el mismo también al **abrir un documento, su
  PDF, su Excel, enviarlo por correo, cambiarle el estado o eliminarlo**. Si se
  intenta llegar a un documento ajeno por un enlace directo, el sistema
  responde *«No tiene permiso sobre este registro: lo creó otro usuario»*.

El superadministrador (nivel 3) siempre ve todo.

Para **guardar el cambio** de un retorno ya registrado basta el permiso
*Actualizar*: no hace falta tener además *Crear*.
## Errores frecuentes

- **El saldo no cuadra**: revise si falta registrar un retorno o si hay
  mercadería vendida sin facturar.
- **El stock no subió**: compruebe la bodega de destino del retorno.
- **No aparece la consignación**: puede estar ya liquidada por completo, no
  estar en estado **Entregada**, o no tener saldo pendiente (ya devuelta o
  facturada en su totalidad).
- **No aparece la serie que uso**: el selector **Serie** solo ofrece puntos de
  emisión **activos** y con el secuencial de retornos configurado. Si la serie
  se inactivó en *Empresa → Puntos de emisión*, ya no se puede usar para
  documentos nuevos; los retornos viejos que la tengan siguen mostrándola
  (marcada como *inactiva*) al abrirlos.

## El número no se puede repetir

El número que se ve al abrir un retorno nuevo es una **vista previa**: el número
definitivo lo asigna el sistema **al guardar**, no antes.

Por eso, si dos personas abren el formulario a la vez —o si uno lo deja abierto
un rato mientras otro emite—, cada retorno recibe un número distinto: el segundo
toma el siguiente libre en el momento de guardar. Puede entonces guardarse con un
número diferente al que mostraba la pantalla; el mensaje de confirmación dice cuál
quedó.

La base de datos rechaza además, por su cuenta, cualquier intento de guardar dos
retornos activos con el mismo número en la misma serie. Si eso llega a ocurrir
aparece *«El número … ya está en uso. Vuelva a guardar para tomar el siguiente
número libre»*: basta con volver a pulsar **Guardar**.

Un retorno **eliminado** libera su número, que se volverá a ofrecer; uno
**anulado** lo conserva.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Con ese modo activo, **al cambiar la fecha del documento su número se recalcula
solo**, para que caiga en el periodo correcto. Una vez guardado, el número queda
fijo aunque después se le cambie la fecha, y los documentos ya emitidos conservan
siempre el que tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Períodos contables cerrados

Lo que mueve inventario o contabilidad no puede tocar un período ya cerrado.
Registrarlo (por su fecha de retorno), modificarlo y eliminarlo se rechazan si la
fecha cae en un mes cerrado.

Al modificar se revisan **las dos fechas** —la nueva y aquella con la que está
registrado—: mover un documento de un mes cerrado a uno abierto lo alteraría
igual. Los períodos se abren y se cierran en **Contabilidad → Períodos
Contables**; reabrir el período permite la operación de inmediato.

## Historial de cambios

- **1.21** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.20** — El asiento del retorno **sigue a la consignación de origen**: si
  la empresa no contabiliza las consignaciones (*Módulos que contabilizan*), no
  se genera ni figura como pendiente.
- **1.19** — Se muestra la **fecha de vencimiento** de cada unidad: columna nueva en la
  grilla de productos del formulario (después de *Lote / NUP*), en el PDF del
  comprobante (*Vence*, entre el NUP y la cantidad) y en su Excel. Es la fecha de la
  línea de consignación de la que salió la unidad; no se escribe a mano y aparece un
  guion cuando esa línea no la tiene. El listado no la lleva porque es una fila por
  retorno, no por producto.

  Junto con esto: los retornos **migrados desde el sistema anterior** habían quedado sin
  esa fecha, aunque la consignación de origen sí la tuviera. Se corrigió la migración (la
  fecha se toma de esa línea de consignación, igual que ya se hacía con la bodega) y los
  retornos ya cargados se completan al volver a migrar, sin necesidad de *Eliminar
  migrados*.

- **1.18** — El PDF del comprobante muestra la columna **Bodega** entre *Descripción*
  y *Lote*, así se ve a qué bodega volvió cada producto, y termina el listado con una
  fila **TOTAL** con la suma de todas las cantidades retornadas. Aplica también al PDF
  que se manda por correo. El Excel del retorno mantiene sus columnas de siempre.

- **1.17** — En el formulario, el **Estado** pasa a la derecha de la barra de
  botones (PDF, Excel, correo y WhatsApp) y *Observaciones* sube a la fila de
  *Cliente* y *Motivo*. La ventana ya no tiene barra de desplazamiento propia: la
  tabla de productos se ajusta al alto de la pantalla y, si hay muchos, solo ella
  se desplaza. Nueva sección *Cambiar el estado del retorno*.

- **1.16** — El cuadro de búsqueda del listado queda para lo que se ve en la tabla: fecha,
  número, cliente, motivo y observaciones. Así el listado solo devuelve retornos donde
  se vea POR QUÉ coinciden. Los **productos** (con su lote y NUP) y los **documentos
  relacionados** pasan a la pestaña *Detalles* del modal de filtros, que además muestra
  cuál de ellos coincidió; el resto de datos que no son columna —RUC del cliente, montos,
  responsable, usuario— se consultan en sus filtros. Antes, el documento aparecía en la
  lista sin que se viera el motivo.
  El número de la consignación de origen se busca ahora en la pestaña *Detalles*.

- **1.15** — El listado muestra la nueva columna **Observaciones**, con lo que se
  escribió en el retorno (si el texto es largo se corta y se lee completo al pasar
  el mouse). Se ordena pulsando su encabezado y se puede ocultar desde el botón de
  columnas. Las exportaciones del listado a **PDF** y **Excel** también la
  incluyen; en el PDF, un motivo u observación largos se parten en varias líneas
  dentro de su columna (antes estiraban la tabla y las últimas columnas quedaban
  fuera de la hoja). Además, pulsar el encabezado **Motivo** ya ordena por motivo
  (antes el listado seguía ordenado por fecha).
- **1.14** — La búsqueda del listado y la de la pestaña **Detalles** responden más
  rápido en empresas con muchos retornos, y encuentran exactamente lo mismo. Si se
  sigue escribiendo mientras busca, la búsqueda anterior se cancela y solo se
  muestra la última.
- **1.13** — El retorno entra al inventario al costo con que salió en la
  consignación (antes entraba a costo 0 y bajaba el costo promedio del producto).
  Sus reversos —pasarlo a Borrador, anularlo o eliminarlo— usan ese mismo costo.
  La consignación de origen ya no se puede editar ni eliminar mientras el retorno
  esté emitido o en borrador.
- **1.12** — Lo entregado a cambio desde una consignación ahora cuenta como
  *facturado* (el cambio lo registra en Facturación de consignaciones). El saldo
  retornable no cambia: esa unidad sigue sin ofrecerse para retornar.
- **1.11** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca por palabras (en cualquier orden y sin importar
  tildes) en las columnas del retorno y además en los productos retornados
  (código, nombre, lote, NUP), las consignaciones de origen, el responsable y el
  usuario; la columna Estado ya no entra en la búsqueda libre. Los filtros pasan
  a una **ventana propia** (botón del embudo, se aplican con *Aplicar*) con dos
  pestañas: **Retorno** (filtros por campo, con criterios nuevos: fecha, Nº
  retorno, consignación de origen, con/sin asiento, responsable y usuario como
  listas, total, subtotal, IVA, RUC y observaciones) y **Detalles**, un cuadro de
  **búsqueda libre dentro de los retornos** que dice a qué retorno pertenece cada
  línea. Nueva sección *Buscar y filtrar el listado*.
- **1.10** — El saldo pendiente de retornar descuenta también lo que el cliente
  recibió **a cambio** desde la consignación (módulo Cambios de productos):
  esas unidades ya no se ofrecen en el buscador ni en la grilla.
- **1.9** — El PDF de un retorno con muchos productos ya no sale troceado. Al
  pasar de unas 45 líneas el documento se partía en decenas de hojas con un solo
  dato cada una (60 productos llegaban a producir 74 páginas). Además, las
  firmas ya no se dibujan encima del listado cuando este llega al pie de la
  página: pasan completas a la hoja siguiente, igual que el motivo y las
  observaciones.

- **1.8** — El permiso **Actualizar** ya sirve por sí solo: para guardar el
  cambio de un retorno existente también se exigía *Crear*, así que quien solo
  podía corregir recibía *«No tiene permiso para esta acción»*.

- **1.7** — El listado ya **no muestra la columna Total**: el valor del retorno se
  consulta abriendo el documento. Las columnas del listado son ahora Fecha,
  Secuencial, Cliente, Motivo y Estado. Las exportaciones a **PDF** y **Excel**
  del listado siguen incluyendo el total.

- **1.6** — El **número ya no se puede repetir**: lo asigna el servidor al
  guardar (antes se guardaba el de la vista previa, así que dos formularios
  abiertos a la vez podían tomar el mismo) y la base lo rechaza si aun así
  coincidiera. El mensaje de confirmación indica con qué número quedó.

- **1.5** — El retorno se arma **a partir del número de consignación**: se
  agrega la consignación por su número (completo, solo el secuencial, o por
  cliente), el **cliente se llena automáticamente** con el de esa consignación
  y la tabla muestra **solo los ítems de esa consignación** (antes había que
  buscar el cliente y salían todas sus consignaciones pendientes juntas). Se
  pueden agregar varias del mismo cliente y quitarlas con la **×** de su
  etiqueta. Además, el selector **Serie** ya **no muestra series inactivas**.

- **1.4** — Los botones **PDF** y **Excel** del listado ya funcionan (antes
  daban error al pulsarlos). Además, el permiso **Acceso total** manda ahora
  también fuera del listado: sin él no se puede abrir, exportar, enviar ni
  eliminar un retorno creado por otro usuario.

- **1.3** — El módulo respeta ahora el **cierre contable**: no se puede operar
  sobre un retorno cuyo período esté cerrado. Antes no se comprobaba.
- **1.2** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.1** — Nuevo botón **Excel** en la barra de acciones del comprobante,
  junto al de PDF: descarga el detalle del retorno en una hoja de cálculo.
- **1.0** — Versión inicial.
