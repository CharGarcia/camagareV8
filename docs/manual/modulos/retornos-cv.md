---
titulo: Retornos de consignación
resumen: Devolución de la mercadería consignada que el cliente no vendió.
categoria: Ventas
ruta_modulo: modulos/retornos-cv
tipo: modulo
visibilidad: todos
etiquetas: retorno, retornos, devolucion de consignacion, mercaderia no vendida, reingreso, saldo consignado, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, buscar por numero de consignacion, agregar consignacion, numero de consignacion, serie inactiva, punto de emision inactivo, permiso actualizar, no puedo guardar, no tengo permiso para esta accion
version: 1.10
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
4. Al registrar el retorno, la mercadería **vuelve al inventario**.

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
entregó a cambio ya es del cliente y **no aparece** para retornar.

## Exportar

En la barra de acciones del comprobante, junto al botón **PDF**, hay un botón
**Excel** que descarga el detalle del retorno (código, descripción, lote, NUP
y cantidad) en una hoja de cálculo. Requiere que el retorno esté guardado.

Cuando el retorno tiene **muchos productos**, el listado del PDF continúa en
las páginas siguientes y **cada página repite la fila de encabezados**. Ninguna
fila se parte entre dos hojas, y el motivo, las observaciones y las firmas se
mantienen completos: si no caben en lo que resta de página, pasan enteros a la
siguiente.

Arriba del listado hay otro par de botones **PDF** y **Excel** que exportan la
**lista completa de retornos** tal como se esté viendo: respetan el buscador,
los filtros y el orden aplicados, y salen todas las filas que calcen, no solo
la página en pantalla.

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
