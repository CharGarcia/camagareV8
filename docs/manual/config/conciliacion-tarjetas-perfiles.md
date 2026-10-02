---
titulo: Perfiles de Lectura de Tarjetas
resumen: Catálogo global con el formato del estado de cuenta de cada procesadora de tarjetas (Payphone, Nuvei, datáfono), que Conciliación de Tarjetas usa para leer el archivo.
categoria: Configuración
ruta_modulo: config/conciliacion-tarjetas-perfiles
requiere_permiso_modulo: no
tipo: modulo
visibilidad: superadmin
etiquetas: perfiles de lectura, perfil de tarjetas, recaps, recap diners, interdin, diners club, visa, mastercard, discover, varias hojas, columnas ocultas, columnas visibles, lote, fecha de pago, formato de procesadora, estado de cuenta tarjetas, liquidacion de tarjetas, reporte payphone, reporte nuvei, datafono, datafast, medianet, mapeo de columnas, conciliacion de tarjetas, excel csv pdf, regex, patron de linea, comision, retenciones
version: 1.1
orden: 0
estado: activo
---

Cada procesadora de tarjetas entrega su estado de cuenta (reporte de
liquidación) con su propio formato: columnas en otro orden, fechas escritas
distinto, montos con coma o con punto, o un PDF sin columnas. En esta pantalla
se describe **una sola vez** cómo leer ese archivo, y **Conciliación de
Tarjetas** (`modulos/conciliacion-tarjetas`) usa ese formato en todas las
empresas.

## Qué es y para qué sirve

Es un **catálogo global del sistema**: no pertenece a ninguna empresa y lo que se
configura aquí lo ven todas. Cada fila es un **perfil de lectura**: a qué
procesadora corresponde, el tipo de archivo y dónde está, dentro del archivo, la
fecha, la autorización, el bruto, la comisión, las retenciones y el neto de cada
línea.

Al cargar el estado de cuenta en una conciliación, el usuario elige el **Perfil
de lectura**. Esa lista sale de esta pantalla.

Solo el **nivel 3 (superadministrador)** entra aquí. Se abre desde
**Configuración**, tarjeta **Perfiles de lectura de tarjetas**.

No confundir con **Perfiles de mapeo de cobros** (`config/conciliacion-perfiles`),
que lee el extracto del **banco** para Conciliación de Cobros.

## Requisitos previos

- Un **archivo de muestra** de la procesadora: el mismo Excel, CSV o PDF que se
  descarga de su portal. Sirve para ver la estructura real y probar el mapeo
  antes de guardar. El archivo de muestra no se guarda.
- Si quiere atar el perfil a un banco (típico del datáfono), el banco debe
  existir en **Bancos del Ecuador**.

## Cómo se usa

1. Pulse **Crear nuevo**.
2. Escriba el nombre (p. ej. *Payphone - reporte mensual*) y elija la
   **Procesadora**: *Payphone*, *Nuvei*, *Tarjeta (datáfono)* o *Cualquiera*.
3. Si el formato depende del banco (datáfono), elija el **Banco**; si no, déjelo
   en *Cualquier banco*.
4. Elija el **Tipo de archivo** (Excel, CSV o PDF) y qué trae el archivo: **una
   línea por transacción** (cada cobro) o **los depósitos consolidados** (un
   total por día o lote).
5. Indique **dónde está cada dato** en la pestaña **Mapeo de columnas**:
   - **Excel / CSV**: el número de columna de cada dato. La primera columna es
     la **0**. Fecha y Bruto son obligatorios; deje vacío lo que el archivo no
     trae. En **Filas de encabezado a saltar** ponga cuántas filas hay antes de la
     primera línea de datos.
   - **Excel con varias hojas o columnas ocultas**: en **Hoja a leer** deje vacío
     para leer la primera hoja, escriba `*` para leer **todas** (cada hoja aplica
     las mismas filas a saltar y su nombre se agrega a la descripción de cada
     línea), o escriba el nombre de una hoja. Marque **Leer solo las columnas
     visibles** si el banco entrega el archivo con columnas ocultas: así se
     cuentan solo las que se ven, desde 0.
   - **PDF**: un patrón (regex) que reconozca cada línea de datos, con los grupos
     `(?<fecha>...)` y `(?<monto_bruto>...)` obligatorios, y opcionalmente
     `(?<autorizacion>...)`, `(?<referencia>...)`, `(?<comision>...)`,
     `(?<monto_neto>...)`, etc.
6. En la pestaña **Probar con archivo**, seleccione el archivo de muestra y pulse
   **Ver / Probar**: aparece el archivo tal como lo lee el sistema y, debajo, las
   líneas que se importarían con el mapeo actual. Luego pulse **Crear** (al
   editar, **Guardar**).

Para cambiar un perfil, haga clic en su fila. Para dejar de ofrecerlo sin
borrarlo, cambie su **Estado** a *Inactivo* y guarde. Los encabezados de la tabla
ordenan el listado con un clic, y el orden elegido se recuerda para su usuario.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre | Sí | Cómo lo verá el usuario al cargar el estado de cuenta |
| Procesadora | No | Payphone, Nuvei o Tarjeta (datáfono). *Cualquiera* = se ofrece con todas |
| Banco | No | Banco al que corresponde el formato. *Cualquier banco* = sin restricción |
| Tipo de archivo | Sí | Excel, CSV o PDF |
| El archivo trae | Sí | Una línea por transacción, o los depósitos consolidados |
| Formato de fecha | Sí | Cómo viene escrita la fecha, p. ej. `d/m/Y` para 31/12/2026 |
| Separador decimal | Sí | Punto (1234.56) o coma (1234,56) en los montos |
| Filas de encabezado a saltar | No | Solo Excel/CSV: filas antes de la primera línea de datos |
| Hoja a leer | No | Solo Excel. Vacío = la primera hoja; `*` = todas; un nombre = esa hoja (basta con parte del nombre: `MASTER` encuentra *MASTERD CARD*) |
| Leer solo las columnas visibles | No | Solo Excel. Ignora las columnas ocultas y numera las visibles desde 0 |
| Estado | Sí | Activo se ofrece en Conciliación de Tarjetas; Inactivo no |
| Fecha / Bruto | Sí (Excel/CSV) | Número de columna de cada dato (0 = primera) |
| Autorización, Referencia, Descripción | No | Datos que ayudan a cruzar la línea con el cobro |
| Lote / Recap, Fecha de pago | No | Se agregan a la descripción de la línea (*Lote 1678 · Pago 01-09-2026*). La fecha de pago usa el mismo formato de fecha |
| Comisión, IVA comisión, Retención renta, Retención IVA, Otros descuentos, Neto | No | Descuentos de la procesadora y valor depositado |
| Patrón (regex) de línea de datos | Sí (PDF) | Reconoce cada línea de datos del PDF |

## Perfiles incluidos: recaps de Diners / Interdin

Diners (también para VISA, Mastercard y Discover que liquida Interdin) entrega
los **recaps** en un Excel con **una hoja por marca** y la mayoría de columnas
ocultas. Ya vienen configurados cinco perfiles de *Tarjeta (datáfono)*, sin banco:

| Perfil | Hoja que lee |
|--------|--------------|
| Diners/Interdin - Recaps (todas las marcas) | Todas (`*`); la marca va a la descripción |
| Diners/Interdin - Recaps: hoja DINERS | DINERS |
| Diners/Interdin - Recaps: hoja DISCOVER | DISCOVER |
| Diners/Interdin - Recaps: hoja VISA | VISA |
| Diners/Interdin - Recaps: hoja MASTERCARD | La que contenga *MASTER* |

Use el de **todas las marcas** si en la empresa una sola forma de cobro con tarjeta
recibe todas las marcas; use el de **cada hoja** si cada marca tiene su propia
forma de cobro.

Todos leen solo las columnas visibles, desde la fila 2, con fecha `Ymd`
(*20260825*):

| Columna visible | Dato |
|-----------------|------|
| 0 Fecha del vale | Fecha de la línea (la que se cruza con el cobro) |
| 1 Fecha del pago | Fecha de pago, a la descripción |
| 2 Número Recap o Lote | Lote, a la descripción |
| 3 Número vale | Referencia |
| 4 Valor Bruto Cuota | Bruto |
| 5 Valor Comisión Cuota | Comisión |
| 6 Valor Retención IRF Cuota | Retención renta |
| 7 Valor Pago Cuota | Neto (bruto − comisión − retención) |

La autorización viene en una columna oculta, así que el cruce automático usa el
**número de vale** (si se anotó como referencia del cobro) y, si no, el **valor y
la fecha del vale**. Los vales del mismo valor en fechas cercanas se cruzan a mano.

## Permisos

Solo el **nivel 3** ve la tarjeta y puede crear, editar, activar/desactivar o
eliminar perfiles. Los niveles 1 y 2 no entran a esta pantalla: solo **eligen**
un perfil activo al cargar el estado de cuenta en Conciliación de Tarjetas.

## Reglas de negocio

- Los perfiles son **globales**: un cambio aquí afecta a todas las empresas que lo
  usen desde ese momento. Los estados de cuenta ya cargados no se vuelven a leer.
- En Conciliación de Tarjetas se ofrecen los perfiles **activos** cuyo tipo de
  procesadora coincide con el de la forma de cobro que se concilia (o que son
  para *Cualquiera*), y cuyo banco coincide con el de la forma de cobro (o que
  son para *Cualquier banco*). Si la forma de cobro no tiene banco, no se filtra
  por banco.
- Al cargar el archivo se vuelve a comprobar: un perfil inactivo, eliminado o de
  otra procesadora se rechaza.
- **Eliminar** es lógico: el perfil deja de listarse, pero las conciliaciones
  antiguas siguen mostrando su nombre.
- Cada creación, cambio y eliminación queda en el log del sistema (tabla
  `conciliacion_tarjetas_perfiles`, sin empresa).

## Integraciones con otros módulos

- **Conciliación de Tarjetas** (`modulos/conciliacion-tarjetas`): usa el perfil
  elegido para leer el estado de cuenta y cruzar cada línea con los cobros.
- **Formas de Cobro/Pago**: el tipo (Payphone, Nuvei, Tarjeta) y el banco de la
  forma de cobro deciden qué perfiles se ofrecen.
- **Bancos del Ecuador**: de ahí sale la lista de bancos.

## Errores frecuentes

- **Falta indicar en qué columna está el campo "monto_bruto"**: en Excel/CSV, la
  fecha y el bruto son obligatorios.
- **El patrón debe incluir el grupo nombrado (?<monto_bruto>...)**: el regex de
  PDF necesita los grupos `fecha` y `monto_bruto` con esos nombres exactos.
- **Un perfil no aparece en Conciliación de Tarjetas**: está **inactivo**, es de
  otra procesadora, o es de un banco distinto al de la forma de cobro.
- **Las líneas salen con montos multiplicados por 100 o sin decimales**: revise el
  **Separador decimal**.
- **El archivo no tiene la hoja "…" que indica el perfil**: el banco cambió el
  nombre de la hoja. El mensaje lista las hojas del archivo; ajuste **Hoja a leer**
  o use el perfil de todas las hojas.
- **Los números de columna no coinciden con lo que se ve en Excel**: el archivo
  tiene columnas ocultas. Marque **Leer solo las columnas visibles** o cuente
  también las ocultas.

## Historial de cambios

- **1.1** — Opciones **Hoja a leer** (una, todas o la primera) y **Leer solo las
  columnas visibles** para Excel. Campos de mapeo **Lote / Recap** y **Fecha de
  pago**, que van a la descripción. Una fecha `aaaammdd` se reconoce aunque el
  perfil diga otro formato. La prueba con archivo muestra las hojas del libro, la
  referencia y la descripción. Se incluyen los perfiles de los **recaps de Diners /
  Interdin** (todas las marcas y uno por hoja). Se corrige el perfil *Diners Club -
  Reporte de establecimiento*: tomaba como neto la columna *Valor Total Pago*, que
  no descuenta la retención IR, y cada vale salía descuadrado por ese valor; ahora
  el neto se calcula (bruto − comisión − retenciones). Las conciliaciones en
  borrador cargadas con ese perfil deben volver a cargar el archivo.

- **1.0** — Versión inicial. Los perfiles de lectura, que antes creaba cada
  empresa desde *Configuración → Perfiles* dentro de Conciliación de Tarjetas,
  pasan a este catálogo global administrado por el nivel 3. El perfil se asocia a
  un tipo de procesadora y, opcionalmente, a un banco, en lugar de a una forma de
  cobro de la empresa.
