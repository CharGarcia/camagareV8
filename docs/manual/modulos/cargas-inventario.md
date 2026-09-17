---
titulo: Cargas de inventario
resumen: Movimientos masivos de stock desde un archivo, con aprobación previa si la empresa la exige.
categoria: Inventario
ruta_modulo: modulos/cargas-inventario
tipo: modulo
visibilidad: todos
etiquetas: carga de inventario, errores de carga, no se puede aprobar, lineas con error, comprobada, corregir carga, ajuste masivo, entrada masiva, salida masiva, conteo fisico, inventario fisico, toma fisica, cuadrar stock, saldo contado, diferencia de inventario, faltantes, sobrantes, importar stock, aprobacion, buscar, filtrar, ordenar, columnas, observacion, observaciones, observacion de la linea, creado por, aprobado por, exportar, buscador, filtros, filtrar cargas, buscar por producto, cargas pendientes, chips, detalle de la carga, lineas de la carga, ver lineas, motivo, fila del excel, descargar pdf, descargar excel, exportar lineas, cargando archivo, importar excel, rechazar carga, eliminar carga, anular carga, anular carga aprobada, reversar carga, revertir carga, deshacer carga, modificar carga aprobada, editar carga aprobada, corregir carga aprobada, carga anulada, productos ya usados, no se puede anular, nup, serie, series, serial, numero de serie, varias series, lote, ajuste por lote
version: 1.7
orden: 25
estado: activo
---

Este módulo registra **muchos movimientos de inventario de una vez** desde un
archivo. Es lo que se usa tras un conteo físico o para cargar un stock inicial
extenso.

## Tipos de movimiento

Cada carga es de un tipo, y solo se admiten tres:

| Tipo | Qué hace |
|------|----------|
| Entrada | Suma la cantidad de cada línea |
| Salida | Resta la cantidad de cada línea |
| Ajuste | Conteo físico: cada producto queda con la cantidad contada; se registra solo la diferencia (ver *Ajuste por conteo físico*) |

## Cómo se usa

1. Pulse **Importar carga** y, si no la tiene, baje la **plantilla** de Excel. Cada fila del archivo es una línea: código del producto, bodega, cantidad y costo (más lote, caducidad, NUP y observación, opcionales). Si arma su propio archivo, los títulos de las columnas pueden llevar tildes, mayúsculas o espacios: *Código producto*, *Costo unitario* u *Observación* (también *Observaciones*) se reconocen igual que `codigo_producto`, `costo_unitario` y `observacion`.
2. Elija el **tipo de movimiento** y, si quiere, escriba una observación.
3. Seleccione el archivo y pulse **Importar**. Mientras el sistema lee el archivo y comprueba cada línea aparece el aviso **Cargando archivo…**; no cierre la ventana hasta que termine (con archivos grandes tarda unos segundos).
4. Al terminar, un aviso resume el resultado:
   - **Carga aplicada al inventario**: todas las líneas estaban bien y la empresa no exige aprobación; el stock ya se actualizó.
   - **Carga registrada**: queda pendiente de aprobación.
   - **Carga registrada con errores**: lista cada línea que falló con su número de fila del Excel; la carga no se podrá aprobar hasta corregirlas (ver *Errores frecuentes*).

El botón **Ver líneas** del aviso abre la carga recién importada. El listado se
actualiza solo, sin perder la búsqueda ni los filtros.

En las cargas de entrada y salida las cantidades deben ser **mayores a cero**; en
un ajuste la cantidad es lo contado y puede ser 0. La carga debe tener al menos
una línea. Todos los avisos del módulo (resultado de la importación, confirmar
una aprobación, el motivo de un rechazo, eliminar, errores) salen en una ventana
emergente.

## Series (NUP)

La columna `nup` es opcional. Una celda puede traer una serie o varias, una por
renglón (en Excel, **Alt + Enter** dentro de la celda):

| La celda trae | Qué se registra |
|---------------|-----------------|
| Una serie | Un movimiento con la **cantidad completa** de la línea y esa serie (igual que en compras, ventas e importaciones) |
| Varias series | Un movimiento de **1 unidad por serie**. La cantidad de la línea debe ser igual al número de series; si no, la línea queda con error |

Por ejemplo, *cantidad 3* con las series *A1*, *A2* y *A3* en la misma celda entra
como tres unidades, una por serie; *cantidad 1* con dos series es un error.

Las cargas de ajuste no admiten NUP.

## Ajuste por conteo físico

Una carga de **Ajuste** sirve para cuadrar el sistema con lo que hay en la bodega.
La cantidad de cada línea es **lo que se contó**, no lo que se suma: al aprobar la
carga, el sistema compara lo contado con el saldo que tiene **en ese momento** y
registra solo la diferencia.

| Situación al aprobar | Qué se registra |
|----------------------|-----------------|
| Se contó más de lo que dice el sistema | Una **entrada** por la diferencia, al costo de la línea (si la línea no trae costo, al costo promedio) |
| Se contó menos | Una **salida** por la diferencia, al costo promedio |
| Coincide | Nada: la línea queda con diferencia 0 |

En el Kardex esos movimientos aparecen como entrada o salida, con la observación
*Ajuste por conteo físico, carga #N: saldo X, contado Y* (y la observación de la
línea, si tiene). Como son entradas y salidas normales, su costo cuenta en el
costo promedio.

**Reglas del archivo de ajuste:**

- La cantidad es obligatoria y puede ser **0** (no hay unidades). Una celda vacía
  no se toma como cero: es un error.
- **Lotes**: si el producto tiene stock repartido en lotes en esa bodega, cada
  línea debe indicar el lote contado y se ajusta el saldo de ese lote. Un lote que
  no está en el archivo no se toca. Si el producto no tiene lotes, la línea sin
  lote ajusta el total del producto en la bodega. Escribir *sin lote* en la
  columna equivale a dejarla vacía.
- Cada producto va **una sola vez** por bodega y lote, y se cuenta en total o por
  lotes, no de las dos formas en el mismo archivo.
- **Sin NUP**: las series se registran con cargas de entrada o de salida.
- Al entrar a un lote que ya existe sin indicar caducidad, se conserva la del lote.

**Antes de aprobar**, la ventana de una carga de ajuste pendiente muestra el
**saldo actual** de cada línea y la **diferencia estimada** si se aprobara ahora.
Es una estimación: si el stock cambia (una venta, una compra) antes de aprobar,
la diferencia se recalcula con el saldo de ese momento. Después de aprobada, la
ventana, el PDF y el Excel muestran el **saldo del sistema** y la **diferencia**
que se registraron.

## Ver las líneas de una carga

Un clic en una fila del listado abre la carga. Arriba muestra la fecha, el tipo,
el estado, si está **comprobada** (todas sus líneas sin error), la observación
general de la carga, el motivo del rechazo, quién la anuló y por qué (si está
anulada) y, si está pendiente y usted no puede aprobarla, quién debe hacerlo.

Debajo está la lista de líneas, en el mismo orden del archivo:

| Columna | Qué muestra |
|---------|-------------|
| Código | Código del producto; si el producto no se encontró, el código tal como venía en el archivo |
| Producto | Nombre del producto (vacío si el código no existe) |
| Bodega | Bodega de la línea |
| Cantidad | Cantidad del movimiento. En un ajuste se llama **Contado** |
| Saldo sistema / Saldo actual | Solo en ajustes: el saldo que tenía el sistema al aprobar o, si la carga está pendiente, el de hoy |
| Diferencia / Diferencia estimada | Solo en ajustes: lo que se registró (o se registraría hoy); positivo = entrada, negativo = salida |
| Costo | Costo unitario |
| Observación | La observación de esa línea en el archivo (columna `observacion`). Es distinta de la observación general que se escribe al importar, que aparece arriba |
| OK | Visto verde si la línea pasó la comprobación; X roja si tiene error |
| Motivo | Solo en las líneas con error: la fila del Excel y qué falló, por ejemplo *Fila 5: La bodega "Norte" no existe en la empresa.* |

La ventana **no crece** con la cantidad de líneas: la lista tiene su propio
desplazamiento vertical y los títulos de las columnas quedan fijos al bajar. Junto
a los botones de arriba se ve el total de líneas y cuántas tienen error.

**Descargar**: los botones **PDF** y **Excel** de la barra superior bajan esas
mismas columnas —incluida la **Observación** de cada línea y, en un ajuste, el
saldo y la diferencia—, con los datos de la carga (número, fecha, tipo, estado,
comprobada, líneas, creado por, aprobado por, observación, motivo de rechazo y, si
está anulada, quién la anuló, cuándo y el motivo) al inicio. En un ajuste pendiente
el archivo indica la fecha y hora de la estimación. En el Excel las cantidades y el
costo quedan como números, y los códigos como texto (un código como *0012* o *12E5*
no se altera).

Los botones de abajo dependen del estado:

| Estado | Botones |
|--------|---------|
| Pendiente | **Aprobar** y **Rechazar** (solo quien puede aprobar; el rechazo pide el motivo) y **Eliminar** |
| Rechazada | **Eliminar** |
| Aprobada | **Anular** (ver *Anular o corregir una carga aprobada*) |
| Anulada | Ninguno: queda como constancia |

Cada uno pide confirmación y muestra el resultado en una ventana emergente.

## El listado

La pantalla principal muestra una carga por fila, con estas columnas:

| Columna | Qué muestra |
|---------|-------------|
| N° | Número interno de la carga, correlativo por empresa |
| Fecha | Fecha de la carga |
| Tipo | Entrada, Salida o Ajuste |
| Líneas | Cuántas filas trae el archivo |
| Estado | Pendiente, Aprobada, Rechazada o Anulada. El triángulo naranja avisa que hay líneas con error y que la carga no se podrá aprobar hasta corregirlas. En una carga anulada, el motivo se lee al dejar el cursor sobre el estado |
| Creado por | Usuario que subió la carga |
| Aprobado por | Usuario que la aprobó (vacío mientras esté pendiente) |
| Observación | El comentario escrito al importar. En las cargas que vienen de la migración del sistema anterior muestra **solo la referencia**: el texto completo del registro migrado aparece al dejar el cursor sobre la celda, y también en el detalle de la carga |

Con el botón de columnas se **oculta o muestra** cada una, y el ancho de cada
columna se puede arrastrar; ambas cosas quedan guardadas para el usuario.

**Ordenar**: un clic en el encabezado ordena por esa columna (otro clic invierte
el sentido). Con **Shift + clic** se encadenan hasta tres columnas —por ejemplo
Estado y, dentro de cada estado, Fecha—; el número junto a la flecha indica la
prioridad de cada una. El orden elegido se conserva para la próxima vez y viaja
también a los archivos de PDF y Excel.

**Buscar y filtrar**: ver la sección siguiente, *Buscar y filtrar el listado*.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del listado: número, fecha
(tal como se ve, por ejemplo *08-07-2026*), cantidad de líneas, creado por,
aprobado por y observación. Además busca en el motivo del rechazo y en los
**productos de las líneas** (el código que traía el archivo, el código del
sistema y el nombre). Las columnas **Tipo** y **Estado** ya no entran en la
búsqueda libre: para filtrar por ellas use la ventana de filtros. Puede escribir
varias palabras en cualquier orden y no importan mayúsculas ni tildes. Para
limpiar, borre el texto o pulse Escape en el cuadro. Mientras busca, aparece un
**círculo girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros. Los antiguos botones rápidos (*Pendientes*, *Aprobadas*,
*Entradas*…) pasan a ser opciones de los selectores de estado y tipo.

**Pestaña Carga:**

| Bloque | Filtros |
|--------|---------|
| Carga | Fecha de la carga (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), tipo (entrada / salida / ajuste), estado (pendiente / aprobada / rechazada / anulada), N° de carga y líneas (con mínimo y máximo), pendientes con líneas en error, observación o referencia, motivo de rechazo |
| Personas | Creado por, aprobado por, fecha de aprobación |
| Líneas | Producto (código o nombre) y bodega: la carga aparece si **alguna** de sus líneas coincide |

Los selectores de *Creado por*, *Aprobado por* y *Bodega* listan solo lo que
aparece en las cargas de la empresa. *Pendiente con líneas en error* son las
cargas que muestran el triángulo naranja en la columna Estado.

**Pestaña Detalles** (lo que hay dentro de la carga). Es un único cuadro,
**Buscar libremente dentro de las cargas**: escriba un código o nombre de
producto, una bodega, un lote, un NUP, una observación de línea, un mensaje de
error o una cantidad, y aparece la lista de **cada línea que coincide** con la
carga a la que pertenece (número, fecha, tipo y estado). Un clic en la fila deja
el listado mostrando solo esa carga; el ícono de la derecha abre directamente
su detalle.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. Quien ya conoce la sintaxis `clave:valor` puede
seguir escribiéndola (`estado:pendiente`, `numero:10..30`, `creado:"maria perez"`,
`-estado:aprobada`); los enlaces guardados siguen funcionando.

**Exportar**: los botones **PDF** y **Excel** bajan lo que está en pantalla —con
la búsqueda, los filtros y el orden aplicados, y con la columna Observación
incluida—.

## Aprobación

La empresa puede exigir que las cargas de inventario sean **aprobadas** antes de
afectar el stock. Con esa opción activa, la carga queda pendiente hasta que un
aprobador la revise, y se avisa por correo a quien corresponda.

Es una medida sensata: una carga masiva mal hecha altera el stock de cientos de
productos de golpe.

Se configura en el módulo **Aprobaciones** (`modulos/aprobaciones-config`): ahí
se activa el proceso *Cargas de inventario*, se eligen los aprobadores y, si se
quiere, un **monto mínimo** por debajo del cual la carga se aplica directamente.
Antes esta configuración estaba en *Empresa → Inventario*.

## Anular o corregir una carga aprobada

Una carga aprobada ya movió el stock, así que **no se edita ni se elimina**: se
**anula**. Anular deshace sus movimientos de inventario —el stock vuelve a como
estaba antes de aplicarla— y la carga queda en el listado con estado **Anulada**,
con quién la anuló, cuándo y el motivo.

**Para corregir una carga aprobada** (cantidades, costos, lotes, productos o
bodegas equivocados): anúlela y luego importe el archivo corregido como una carga
nueva. Al terminar la anulación, el aviso ofrece el botón **Importar archivo
corregido**, que abre la importación con la observación *Corrige la carga #N*.

**Quién puede anular.** Los aprobadores de cargas de inventario (módulo
Aprobaciones) y el superadministrador, que además tengan permiso de **eliminar** en
este módulo. Igual que al aprobar, quien registró la carga no la anula: debe
hacerlo otro aprobador (salvo el superadministrador).

**Cómo se hace.** Abra la carga y pulse **Anular**. Antes de pedir el motivo, el
sistema comprueba si se puede; si se puede, indica cuántos movimientos se van a
reversar y pide el **motivo** (obligatorio). Un ajuste cuyo conteo coincidió en
todo con el sistema no movió el stock: se puede anular igual, y solo queda como
anulado.

**Cuándo no se puede anular:**

| Caso | Por qué |
|------|---------|
| Sus productos ya se usaron después de aplicarla | Ver el detalle abajo |
| La carga viene de la migración del sistema anterior | Sus movimientos de inventario no están enlazados a la carga: no hay nada que reversar desde aquí |
| Su período contable está cerrado | La fecha de la carga o la de su aprobación cae en un período cerrado |
| La carga se aplicó en otro ambiente | Se aprobó en pruebas y la empresa hoy está en producción, o al revés |
| No tiene acceso a una de sus bodegas | Usuarios con bodegas restringidas |

**Productos ya usados.** El sistema recorre, en orden cronológico, todos los
movimientos de cada producto en cada bodega de la carga y calcula el saldo que
habría habido **sin la carga**, desde el momento en que se aplicó. Si ese saldo
habría quedado negativo en algún momento, las unidades de la carga ya salieron
(en una factura, consignación, transferencia, etc.) y no se puede anular. Lo mismo
se revisa por **lote** y por **NUP** (serie) de cada línea.

- No basta con que hoy haya stock: si se vendieron 7 unidades de la carga y después
  entraron 20 más por una compra, la carga igual está usada.
- Si antes de la carga el saldo ya era negativo, la carga cubrió salidas anteriores
  y tampoco se puede anular.
- En una carga de **salida** con NUP, no se anula si la serie volvió a entrar
  después: quedaría dos veces en stock.

La ventana lista cada producto con el problema, por ejemplo *P001 - Arroz en
Central, lote L1: sin esta carga el saldo del lote habría quedado en -5 el
12-09-2026 10:15:00 (Salida por Factura # 001-001-000000123)*. Para anularla,
primero anule o elimine esos documentos; o, sin anular la carga, corrija el stock
con una carga nueva de entrada o salida.

**En el Kardex**, los movimientos de una carga anulada dejan de contar para el
stock y se ven con el filtro *Ver anulados*, con la observación terminada en
*ANULADO*. Una carga anulada no genera movimientos nuevos de reverso.

## Errores frecuentes

- **"Tipo de movimiento inválido"**: debe ser entrada, salida o ajuste.
- **"La carga no contiene líneas para procesar"**: el archivo llegó vacío o ninguna fila se pudo interpretar.
- **"La cantidad debe ser mayor a cero"**: revise las filas en cero o negativas (en una carga de entrada o salida; en un ajuste el 0 es válido).
- **"La celda NUP trae N series y la cantidad es M"**: con varias series en la celda, la cantidad debe ser igual al número de series (ver *Series (NUP)*).
- **"Falta la cantidad contada o no es un número"**: en un ajuste la cantidad es obligatoria; si no hay unidades, escriba 0.
- **"El producto tiene stock por lotes en esta bodega (…): indique el lote que contó"**: en un ajuste, cuente ese producto por lote, una línea por lote.
- **"Este producto ya se contó en la fila N…"**: en un ajuste cada producto va una vez por bodega y lote, en total o por lotes.
- **"Una carga de ajuste no admite NUP"**: quite la serie o registre esas unidades con una carga de entrada o salida.
- **Un error de los anteriores aparece recién al aprobar** (termina en *Elimine la carga y vuelva a importarla corregida*): la carga se importó antes de que existiera esa regla, o el producto empezó a manejar lotes después de importar el ajuste. Elimínela e impórtela de nuevo.
- **"Para aprobar cargas de ajuste falta aplicar en la base de datos el script…"**: aviso para soporte técnico; falta una actualización de la base de datos.
- **La carga no afecta el stock**: puede estar pendiente de aprobación, o anulada.
- **No aparece la observación de las líneas**: en las cargas importadas antes de la versión 1.6 con un archivo propio cuyo título de columna llevaba tilde o era plural (*Observación*, *Observaciones*), la observación de cada línea no se guardó y no se puede recuperar. Desde la 1.6 esos títulos se reconocen.
- **"No se puede eliminar una carga ya aprobada"**: una carga aprobada se anula, no se elimina (ver *Anular o corregir una carga aprobada*).
- **"No se puede anular la carga #N: sus productos ya se usaron después de aplicarla"**: la lista indica qué producto, lote o NUP y qué documento lo consumió.
- **"No puede anular una carga que usted mismo registró"**: debe anularla otro aprobador.
- **"Solo los aprobadores de cargas de inventario pueden anular una carga aprobada"**: pida que lo agreguen como aprobador en el módulo Aprobaciones.
- **"Esta carga no tiene movimientos de inventario enlazados"**: es una carga migrada del sistema anterior; no se puede anular desde aquí.
- **"Para anular cargas aprobadas falta aplicar en la base de datos el script…"**: aviso para soporte técnico; falta una actualización de la base de datos.
- **"La carga no está comprobada: corrija las líneas con error antes de aprobar"** (o el botón **Aprobar** aparece deshabilitado y la carga tiene el triángulo naranja en el listado): al importar, cada fila del archivo se comprobó contra la empresa y al menos una falló.

El aviso que aparece al terminar la importación lista todos los errores; después,
al abrir la carga, cada línea con X roja trae en la columna **Motivo** el número de
fila del Excel y qué falló (la fila 1 es el encabezado, así que "Fila 5" es la
quinta fila del archivo). Con el botón **Excel** del detalle puede bajar la lista
para corregir el archivo. Los motivos posibles son:

  | Mensaje | Causa | Cómo corregirlo |
  |---------|-------|-----------------|
  | *Falta el código del producto* | La celda de código está vacía | Escriba el código principal del producto |
  | *El producto con código "X" no existe en la empresa* | El código no coincide con ninguno de la hoja **Productos** de la plantilla (se compara exacto, sin espacios al inicio o al final) | Copie el código tal como aparece en la hoja **Productos**, o cree primero el producto en el módulo Productos |
  | *El código "X" corresponde a un servicio y no puede cargarse al inventario* | El producto existe pero es de tipo servicio | Quite la fila o cambie el producto a tipo bien |
  | *Falta la bodega* | La celda de bodega está vacía | Escriba el nombre de la bodega |
  | *La bodega "X" no existe en la empresa* | El nombre no coincide con ninguna bodega activa de la empresa (no distingue mayúsculas, pero debe ser el nombre completo) | Use el nombre exacto de la hoja **Bodegas** de la plantilla |
  | *La cantidad debe ser mayor a cero* | La celda está vacía, en cero, negativa o con texto (entradas y salidas) | Escriba una cantidad numérica mayor a cero |
  | *La celda NUP trae N series y la cantidad es M* | Varias series en la celda con otra cantidad | Ponga como cantidad el número de series, o una serie por línea |
  | *Falta la cantidad contada…* / *La cantidad contada no puede ser negativa* | Ajuste con la cantidad vacía, con texto o negativa | Escriba lo contado; 0 si no hay unidades |
  | *El producto tiene stock por lotes en esta bodega…* | Ajuste sin lote de un producto que maneja lotes | Una línea por lote con lo contado de cada uno |
  | *Este producto ya se contó en la fila N…* | El mismo producto, bodega y lote dos veces, o contado en total y por lotes | Deje una sola forma y una sola línea |
  | *Una carga de ajuste no admite NUP* | Ajuste con series | Quite la serie o use una carga de entrada o salida |

  Las líneas de una carga **no se editan** desde el sistema: corrija el archivo,
  **elimine** la carga pendiente (botón Eliminar del detalle) e impórtela de
  nuevo. Las líneas correctas no se aplican al stock hasta que toda la carga se
  apruebe, así que eliminarla no deja movimientos a medias.

## Historial de cambios

- **1.0** — Versión inicial.
- **1.1** — La configuración de la aprobación se movió al módulo **Aprobaciones**; se agrega monto mínimo.
- **1.2** — El listado pasa al estándar del sistema: buscador por campos con filtros rápidos, ordenamiento por encabezado (incluido el orden por varias columnas con Shift + clic), paginación sin recargar la página y nueva columna **Observación**, también en el PDF y el Excel. En las cargas migradas esa columna muestra solo la referencia.
- **1.3** — El detalle de una carga pendiente con errores muestra la lista completa de errores de comprobación y una columna **Motivo** por línea (antes solo se veían al pasar el cursor sobre la X roja). Se documentan los mensajes y cómo corregir cada uno.
- **1.4** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias; lo que se escribe se busca en las columnas del listado (incluida la fecha), el motivo de rechazo y los productos de las líneas, salvo Tipo y Estado. Los filtros pasan a una **ventana propia** (botón del embudo, se aplican con *Aplicar*) con dos pestañas: **Carga** (criterios nuevos: pendientes con líneas en error, motivo de rechazo, creado por y aprobado por como lista, fecha de aprobación, producto y bodega de las líneas) y **Detalles**, un cuadro de **búsqueda libre dentro de las líneas** de las cargas que dice a qué carga pertenece cada coincidencia. Los botones rápidos pasan a ser opciones de los selectores de estado y tipo. Los filtros activos se ven como etiquetas dentro del cuadro y la tabla se atenúa mientras busca.
- **1.5** — La ventana de una carga muestra las líneas con las columnas **Código**, Producto, Bodega, Cantidad, Costo, OK y Motivo (el motivo incluye la fila del Excel), en una lista con desplazamiento propio: la ventana ya no crece con la cantidad de líneas. Nuevos botones **PDF** y **Excel** para bajar esas líneas. Al importar aparece el aviso **Cargando archivo…** y el resultado se muestra en una ventana emergente con el botón **Ver líneas**; todos los avisos del módulo (confirmar aprobación, motivo del rechazo, eliminar, errores) pasan a ventanas emergentes y el listado se actualiza sin recargar la página. Sin acceso total, solo se abren y descargan las cargas propias, igual que en el listado.
- **1.6** — La ventana de una carga, su PDF y su Excel muestran la **Observación de cada línea** (antes no aparecía en ninguno). Al importar, los títulos de columna con tildes, mayúsculas, espacios o en plural (*Observación*, *Observaciones*, *Código producto*, *Costo unitario*) se reconocen; antes esas columnas se ignoraban sin avisar. Nuevo botón **Anular** para cargas aprobadas: reversa su stock y deja la carga como **Anulada** con quién, cuándo y el motivo; solo si sus productos no se usaron después (se revisa el historial por producto, lote y NUP), el período contable está abierto y lo hace otro aprobador. Para corregir una carga aprobada se anula y se importa el archivo corregido. Nuevo estado *Anulada* en el listado, los filtros y las descargas; las cargas anuladas no se eliminan.
- **1.7** — **Ajuste por conteo físico**: una carga de tipo Ajuste ya no suma la cantidad; cada producto queda con lo contado y al aprobar se registra solo la diferencia (entrada o salida, así su costo cuenta en el costo promedio), por lote si el producto maneja lotes. Admite cantidad 0, no admite NUP y cada producto va una vez por bodega y lote. La ventana, el PDF y el Excel del ajuste muestran el saldo y la diferencia (estimados mientras está pendiente). **Series (NUP)**: una celda con una serie mueve la cantidad completa de la línea (antes entraba o salía solo 1 unidad); con varias series la cantidad debe coincidir con el número de series, o la línea queda con error. Dos aprobaciones simultáneas de la misma carga ya no la aplican dos veces.
