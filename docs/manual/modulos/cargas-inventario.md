---
titulo: Cargas de inventario
resumen: Movimientos masivos de stock desde un archivo, con aprobación previa si la empresa la exige.
categoria: Inventario
ruta_modulo: modulos/cargas-inventario
tipo: modulo
visibilidad: todos
etiquetas: carga de inventario, errores de carga, no se puede aprobar, lineas con error, comprobada, corregir carga, ajuste masivo, entrada masiva, salida masiva, conteo fisico, importar stock, aprobacion, buscar, filtrar, ordenar, columnas, observacion, creado por, aprobado por, exportar, buscador, filtros, filtrar cargas, buscar por producto, cargas pendientes, chips, detalle de la carga, lineas de la carga, ver lineas, motivo, fila del excel, descargar pdf, descargar excel, exportar lineas, cargando archivo, importar excel, rechazar carga, eliminar carga
version: 1.5
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
| Entrada | Suma stock |
| Salida | Resta stock |
| Ajuste | Corrige a la cantidad indicada |

## Cómo se usa

1. Pulse **Importar carga** y, si no la tiene, baje la **plantilla** de Excel. Cada fila del archivo es una línea: código del producto, bodega, cantidad y costo (más lote, caducidad, NUP y observación, opcionales).
2. Elija el **tipo de movimiento** y, si quiere, escriba una observación.
3. Seleccione el archivo y pulse **Importar**. Mientras el sistema lee el archivo y comprueba cada línea aparece el aviso **Cargando archivo…**; no cierre la ventana hasta que termine (con archivos grandes tarda unos segundos).
4. Al terminar, un aviso resume el resultado:
   - **Carga aplicada al inventario**: todas las líneas estaban bien y la empresa no exige aprobación; el stock ya se actualizó.
   - **Carga registrada**: queda pendiente de aprobación.
   - **Carga registrada con errores**: lista cada línea que falló con su número de fila del Excel; la carga no se podrá aprobar hasta corregirlas (ver *Errores frecuentes*).

El botón **Ver líneas** del aviso abre la carga recién importada. El listado se
actualiza solo, sin perder la búsqueda ni los filtros.

Todas las cantidades deben ser **mayores a cero**, y la carga debe tener al menos
una línea. Todos los avisos del módulo (resultado de la importación, confirmar
una aprobación, el motivo de un rechazo, eliminar, errores) salen en una ventana
emergente.

## Ver las líneas de una carga

Un clic en una fila del listado abre la carga. Arriba muestra la fecha, el tipo,
el estado, si está **comprobada** (todas sus líneas sin error), la observación, el
motivo del rechazo y, si está pendiente y usted no puede aprobarla, quién debe
hacerlo.

Debajo está la lista de líneas, en el mismo orden del archivo:

| Columna | Qué muestra |
|---------|-------------|
| Código | Código del producto; si el producto no se encontró, el código tal como venía en el archivo |
| Producto | Nombre del producto (vacío si el código no existe) |
| Bodega | Bodega de la línea |
| Cantidad | Cantidad del movimiento |
| Costo | Costo unitario |
| OK | Visto verde si la línea pasó la comprobación; X roja si tiene error |
| Motivo | Solo en las líneas con error: la fila del Excel y qué falló, por ejemplo *Fila 5: La bodega "Norte" no existe en la empresa.* |

La ventana **no crece** con la cantidad de líneas: la lista tiene su propio
desplazamiento vertical y los títulos de las columnas quedan fijos al bajar. Junto
a los botones de arriba se ve el total de líneas y cuántas tienen error.

**Descargar**: los botones **PDF** y **Excel** de la barra superior bajan esas
mismas columnas, con los datos de la carga (número, fecha, tipo, estado,
comprobada, líneas, creado por, aprobado por, observación y motivo de rechazo) al
inicio. En el Excel la cantidad y el costo quedan como números, y los códigos como
texto (un código como *0012* o *12E5* no se altera).

Los botones de abajo dependen del estado: **Aprobar** y **Rechazar** (solo quien
puede aprobar; el rechazo pide el motivo) y **Eliminar** (cargas no aprobadas).
Cada uno pide confirmación y muestra el resultado en una ventana emergente.

## El listado

La pantalla principal muestra una carga por fila, con estas columnas:

| Columna | Qué muestra |
|---------|-------------|
| N° | Número interno de la carga, correlativo por empresa |
| Fecha | Fecha de la carga |
| Tipo | Entrada, Salida o Ajuste |
| Líneas | Cuántas filas trae el archivo |
| Estado | Pendiente, Aprobada o Rechazada. El triángulo naranja avisa que hay líneas con error y que la carga no se podrá aprobar hasta corregirlas |
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
| Carga | Fecha de la carga (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), tipo (entrada / salida / ajuste), estado (pendiente / aprobada / rechazada), N° de carga y líneas (con mínimo y máximo), pendientes con líneas en error, observación o referencia, motivo de rechazo |
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

## Errores frecuentes

- **"Tipo de movimiento inválido"**: debe ser entrada, salida o ajuste.
- **"La carga no contiene líneas para procesar"**: el archivo llegó vacío o ninguna fila se pudo interpretar.
- **"La cantidad debe ser mayor a cero"**: revise las filas en cero o negativas.
- **La carga no afecta el stock**: puede estar pendiente de aprobación.
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
  | *La cantidad debe ser mayor a cero* | La celda está vacía, en cero, negativa o con texto | Escriba una cantidad numérica mayor a cero |

  Las líneas de una carga **no se editan** desde el sistema: corrija el archivo,
  **elimine** la carga pendiente (botón Eliminar del detalle) e impórtela de
  nuevo. Las líneas correctas no se aplican al stock hasta que toda la carga se
  apruebe, así que eliminarla no deja movimientos a medias.

## Historial de cambios

- **1.0** — Versión inicial.
- **1.1** — La configuración de la aprobación se movió al módulo **Aprobaciones**; se agrega monto mínimo.
- **1.2** — El listado pasa al estándar del sistema: buscador por campos con filtros rápidos, ordenamiento por encabezado (incluido el orden por varias columnas con Shift + clic), paginación sin recargar la página y nueva columna **Observación**, también en el PDF y el Excel. En las cargas migradas esa columna muestra solo la referencia.
- **1.3** — El detalle de una carga pendiente con errores muestra la lista completa de errores de comprobación y una columna **Motivo** por línea (antes solo se veían al pasar el cursor sobre la X roja). Se documentan los mensajes y cómo corregir cada uno.
- **1.5** — La ventana de una carga muestra las líneas con las columnas **Código**, Producto, Bodega, Cantidad, Costo, OK y Motivo (el motivo incluye la fila del Excel), en una lista con desplazamiento propio: la ventana ya no crece con la cantidad de líneas. Nuevos botones **PDF** y **Excel** para bajar esas líneas. Al importar aparece el aviso **Cargando archivo…** y el resultado se muestra en una ventana emergente con el botón **Ver líneas**; todos los avisos del módulo (confirmar aprobación, motivo del rechazo, eliminar, errores) pasan a ventanas emergentes y el listado se actualiza sin recargar la página. Sin acceso total, solo se abren y descargan las cargas propias, igual que en el listado.
- **1.4** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias; lo que se escribe se busca en las columnas del listado (incluida la fecha), el motivo de rechazo y los productos de las líneas, salvo Tipo y Estado. Los filtros pasan a una **ventana propia** (botón del embudo, se aplican con *Aplicar*) con dos pestañas: **Carga** (criterios nuevos: pendientes con líneas en error, motivo de rechazo, creado por y aprobado por como lista, fecha de aprobación, producto y bodega de las líneas) y **Detalles**, un cuadro de **búsqueda libre dentro de las líneas** de las cargas que dice a qué carga pertenece cada coincidencia. Los botones rápidos pasan a ser opciones de los selectores de estado y tipo. Los filtros activos se ven como etiquetas dentro del cuadro y la tabla se atenúa mientras busca.
