---
titulo: Cargas de inventario
resumen: Movimientos masivos de stock desde un archivo, con aprobación previa si la empresa la exige.
categoria: Inventario
ruta_modulo: modulos/cargas-inventario
tipo: modulo
visibilidad: todos
etiquetas: carga de inventario, errores de carga, no se puede aprobar, lineas con error, comprobada, corregir carga, ajuste masivo, entrada masiva, salida masiva, conteo fisico, importar stock, aprobacion, buscar, filtrar, ordenar, columnas, observacion, creado por, aprobado por, exportar
version: 1.3
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

1. Prepare el archivo con los productos y cantidades.
2. Elija el **tipo de movimiento** y la **bodega**.
3. Suba el archivo y revise las líneas.
4. Procese la carga.

Todas las cantidades deben ser **mayores a cero**, y la carga debe tener al menos
una línea.

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

**Buscar**: escriba texto libre para buscar en el número, el tipo, el estado, la
observación y el nombre de quien creó o aprobó la carga. Los botones rápidos
(*Pendientes*, *Aprobadas*, *Rechazadas*, *Entradas*, *Salidas*) filtran de un
clic, y el buscador admite además filtros por campo:

| Filtro | Ejemplo |
|--------|---------|
| Estado | `estado:pendiente` |
| Tipo | `tipo:entrada` |
| Número | `numero:15` · `numero:10..30` |
| Fecha | `fecha:2026-01-01..2026-03-31` |
| Líneas | `lineas:1..50` |
| Observación o referencia | `observacion:conteo` · `observacion:INV-00123` |
| Creado por | `creado:"maria perez"` |
| Aprobado por | `aprobado:lopez` |

Anteponer un guion niega el filtro: `-estado:aprobada` deja fuera las aprobadas.

**Exportar**: los botones **PDF** y **Excel** bajan lo que está en pantalla —con
la búsqueda y el orden aplicados, y con la columna Observación incluida—.

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
- **"La carga no contiene líneas para procesar"**: el archivo llegó vacío o
  ninguna fila se pudo interpretar.
- **"La cantidad debe ser mayor a cero"**: revise las filas en cero o negativas.
- **La carga no afecta el stock**: puede estar pendiente de aprobación.
- **"La carga no está comprobada: corrija las líneas con error antes de aprobar"**
  (o el botón **Aprobar** aparece deshabilitado y la carga tiene el triángulo
  naranja en el listado): al importar, cada fila del archivo se comprobó contra
  la empresa y al menos una falló. Abra la carga desde el listado: arriba de la
  tabla se muestra la **lista completa de errores**, con el número de fila del
  Excel (la fila 1 es el encabezado, así que "Fila 5" es la quinta fila del
  archivo), y en cada línea con X roja la columna **Motivo** dice qué falló.
  Los motivos posibles son:

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
