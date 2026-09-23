---
titulo: Perfiles de Mapeo de Cobros Bancarios
resumen: Catálogo global con el formato del estado de cuenta de cada banco (Excel/CSV o PDF), que Conciliación de Cobros usa para leer el extracto.
categoria: Configuración
ruta_modulo: config/conciliacion-perfiles
requiere_permiso_modulo: no
tipo: modulo
visibilidad: superadmin
etiquetas: perfiles de mapeo, formato del banco, formato de extracto, estado de cuenta, extracto bancario, mapeo de columnas, conciliacion de cobros, cobros bancarios, excel csv pdf, regex, patron de linea, pichincha, produbanco, guayaquil, pacifico
version: 1.0
orden: 0
estado: activo
---

Cada banco entrega el estado de cuenta con su propio formato: columnas en otro
orden, fechas escritas distinto, montos con coma o con punto, o un PDF sin
columnas. En esta pantalla se describe **una sola vez** cómo leer el extracto de
cada banco, y **Conciliación de Cobros** (`modulos/conciliacion-cobros`) usa ese
formato en todas las empresas.

## Qué es y para qué sirve

Es un **catálogo global del sistema**: no pertenece a ninguna empresa y lo que se
configura aquí lo ven todas. Cada fila es un **perfil de mapeo** (formato de
banco): el tipo de archivo y dónde está, dentro del archivo, la fecha, la
descripción, el monto y la referencia de cada movimiento.

Al subir un extracto en Conciliación de Cobros, el usuario elige la cuenta
bancaria y el **Formato del Banco**. Esa lista sale de esta pantalla.

Solo el **nivel 3 (superadministrador)** entra aquí. Se abre desde
**Configuración**, tarjeta **Perfiles de mapeo de cobros**.

## Requisitos previos

- Un **archivo de muestra** del banco: el mismo Excel/CSV o PDF que se descarga de
  la banca en línea. Sirve para ver la estructura real y probar el mapeo antes de
  guardar. El archivo de muestra no se guarda.
- Si quiere atar el perfil a un banco, el banco debe existir en **Bancos del
  Ecuador**. Un perfil sin banco queda como **genérico**.

## Cómo se usa

1. Pulse **Crear nuevo**.
2. Escriba el nombre (p. ej. *Banco Pichincha - Excel*) y elija el **Banco**.
3. Elija el **Tipo de archivo**: *Excel / CSV* o *PDF*.
4. Seleccione el archivo de muestra y pulse **Ver / Probar**: aparece el contenido
   del archivo tal como lo lee el sistema.
5. Indique **dónde está cada dato**:
   - **Excel / CSV**: el número de columna de la fecha, la descripción, el monto
     (crédito) y, si existe, la referencia. La primera columna es la **0**. En
     **Filas de encabezado a saltar** ponga cuántas hay antes del primer
     movimiento.
   - **PDF**: un patrón (regex) que reconozca la línea que cierra cada movimiento,
     con los grupos `(?<fecha>...)` y `(?<monto>...)` obligatorios. El botón
     **Sugerir patrón** analiza el PDF y propone uno; revíselo con **Probar**.
6. Revise la tabla **Resultado de aplicar el mapeo actual** y pulse **Crear**
   (al editar, **Guardar**).

Para cambiar un perfil, haga clic en su fila. Para dejar de ofrecerlo sin
borrarlo, cambie su **Estado** a *Inactivo* y guarde. Los encabezados de la tabla
ordenan el listado con un clic, y el orden elegido se recuerda para su usuario.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre del perfil | Sí | Cómo lo verá el usuario en Conciliación de Cobros, junto al banco |
| Banco | No | Banco al que corresponde. Vacío = genérico (se ofrece con cualquier cuenta) |
| Tipo de archivo | Sí | Excel / CSV o PDF |
| Separador decimal | Sí | Punto (1234.56) o coma (1234,56) en los montos del extracto |
| Formato de fecha | Sí | Cómo viene escrita la fecha, p. ej. `d/m/Y` para 31/12/2026 |
| Filas de encabezado a saltar | No | Solo Excel: filas de encabezado antes del primer movimiento |
| Estado | Sí | Activo se ofrece en Conciliación de Cobros; Inactivo no |
| Columnas Fecha / Descripción / Monto | Sí (Excel) | Número de columna de cada dato (0 = primera) |
| Columna Referencia | No | Número de documento o referencia del banco |
| Patrón (regex) de línea de datos | Sí (PDF) | Reconoce la línea con la fecha y el monto de cada movimiento |
| Valor "es crédito" | No | Solo PDF: si el patrón captura `(?<tipo>...)`, el valor que indica un ingreso (p. ej. `C`) |

## Permisos

Solo el **nivel 3** ve la tarjeta y puede crear, editar, activar/desactivar o
eliminar perfiles. Los niveles 1 y 2 no entran a esta pantalla: solo **eligen**
un perfil activo al subir el extracto en Conciliación de Cobros.

## Reglas de negocio

- Los perfiles son **globales**: un cambio aquí afecta a todas las empresas que lo
  usen desde ese momento. Las cargas ya procesadas no se vuelven a leer.
- En Conciliación de Cobros, al elegir la cuenta bancaria se muestran los perfiles
  **de ese banco** y los **genéricos**. Si el banco de la cuenta no tiene ninguno,
  se muestran todos.
- Solo se ofrecen los perfiles **activos**. Si un perfil se desactiva mientras un
  usuario tiene la pantalla abierta, al subir el extracto se rechaza con *El
  formato del banco seleccionado no existe o está inactivo*.
- **Eliminar** es lógico: el perfil deja de listarse, pero las cargas antiguas
  siguen mostrando su nombre en *Cargas anteriores*.
- Cada creación, cambio (incluido el de estado) y eliminación queda en el log
  del sistema (tabla `conciliacion_perfiles`, sin empresa).

## Integraciones con otros módulos

- **Conciliación de Cobros** (`modulos/conciliacion-cobros`): usa el perfil
  elegido para leer el extracto y sugerir el cliente/factura de cada línea.
- **Bancos del Ecuador**: de ahí sale la lista de bancos.

## Errores frecuentes

- **Falta indicar en qué columna está el campo "monto"**: en Excel, la fecha, la
  descripción y el monto son obligatorios.
- **El patrón debe incluir el grupo nombrado (?<fecha>...)**: el regex de PDF
  necesita los grupos `fecha` y `monto` con esos nombres exactos.
- **El patrón no encontró ninguna línea de datos**: el regex no coincide con el
  texto del PDF. Use **Sugerir patrón** o ajuste el patrón mirando el texto que
  muestra **Ver / Probar**.
- **Un perfil no aparece en Conciliación de Cobros**: está **inactivo**, o es de
  otro banco distinto al de la cuenta elegida (y ese banco sí tiene perfiles
  propios).

## Historial de cambios

- **1.0** — Versión inicial. Los perfiles de mapeo, que antes creaba cada empresa
  desde Conciliación de Cobros con el botón *Perfiles de Mapeo*, pasan a este
  catálogo global administrado por el nivel 3. Se agregan el banco del perfil, el
  estado activo/inactivo y la eliminación.
