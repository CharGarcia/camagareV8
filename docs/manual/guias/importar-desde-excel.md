---
titulo: Importar datos desde Excel
resumen: Cargar clientes, vendedores, productos, proveedores, unidades de medida y otros catálogos en bloque a partir de una plantilla de Excel.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: importar, excel, xlsx, carga masiva, plantilla, subir datos, migrar, cargar clientes, cargar vendedores, asignar vendedor a clientes, cargar productos, unidades de medida, tipos de medida, importador, varios precios, lista de precios, precios por producto, mayorista
version: 1.3
orden: 20
estado: activo
---

El **Importador desde Excel** (Configuración → Importador desde Excel) sirve para
cargar catálogos completos sin escribirlos uno por uno. Se descarga una plantilla,
se llena en Excel y se sube. Está disponible para administradores y
superadministradores.

## Qué se puede importar

**Tablas operativas** (pertenecen a una empresa): clientes, vendedores,
productos, proveedores, empleados, vehículos, unidades y tipos de medida, plan
de cuentas.

**Tablas globales** (comunes a todo el sistema): retenciones del SRI.

## Cómo se usa

1. Elija si va a cargar una tabla **operativa** o **global**.
2. Elija la **entidad**. Si es operativa, elija además la **empresa de destino**
   y el ambiente.
3. Descargue la plantilla. **Descárguela siempre desde aquí**: cada archivo se
   genera para el establecimiento elegido y trae adentro las listas de códigos
   válidos de esa empresa.
4. Llene las filas en Excel sin tocar los títulos de las columnas ni los nombres
   de las hojas.
5. Suba el archivo e inicie la importación.

## Reglas que conviene saber antes

- **Todo o nada**: si una fila tiene un error, se cancela la importación completa
  y no se guarda ningún registro. El mensaje indica la fila —y la hoja, cuando la
  plantilla tiene varias— para que la corrija y vuelva a subir el archivo.
- **Cada plantilla pertenece a un establecimiento**. Si la carga en otro, el
  sistema la rechaza; descargue la plantilla correcta.
- **Las hojas de colores son de consulta**: traen los códigos válidos de tipos de
  identificación, tarifas de IVA, bancos, unidades de medida y demás. No se
  llenan, se consultan.
- Toda importación queda registrada en la auditoría del sistema, con el usuario,
  la fila de origen y el registro creado o actualizado.

## Vendedores y su asignación a clientes

Los vendedores se cargan con la entidad **Vendedores** y, una vez existen, cada
cliente puede quedar asignado a uno desde la plantilla de **Clientes**. El orden
importa: **primero vendedores, después clientes**.

### Columnas de la plantilla Vendedores

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| IDENTIFICACION | Sí | Cédula, RUC o código del vendedor. Letras, números y guiones (3 a 50 caracteres). Es la clave con la que se reconoce al vendedor |
| NOMBRE | Sí | Nombre visible (máximo 50 caracteres) |
| CORREO | No | Se valida el formato y se guarda en minúsculas |
| TELEFONO | No | Máximo 20 caracteres |
| DIRECCION | No | Máximo 100 caracteres |

Si ya existe un vendedor con la misma identificación en la empresa, **se
actualiza** con los datos del archivo (nombre, correo, teléfono y dirección); no
se duplica. Los vendedores cargados quedan **activos**.

### Columna VENDEDOR en la plantilla de clientes

La plantilla de clientes tiene al final la columna **VENDEDOR (opcional)**. Se
escribe la **identificación** del vendedor o su **nombre exacto** (sin distinguir
mayúsculas). Para no adivinar, la plantilla trae la hoja de consulta
**Vendedores** con los que ya tiene la empresa, su identificación y si están
activos.

- Si la celda viene **vacía**, un cliente nuevo queda sin vendedor y un cliente
  que ya existía **conserva el que tenía**. La plantilla no sirve para quitar un
  vendedor; eso se hace desde la ficha del cliente.
- Si se escribe un vendedor, tiene que existir en la empresa y estar **activo**.
- Si dos vendedores se llaman igual, hay que usar la identificación.
- Como la hoja Vendedores es propia de cada empresa, la plantilla de clientes
  también queda **atada al establecimiento** para el que se descargó.

Las plantillas de clientes descargadas antes de esta versión (sin la columna
VENDEDOR) siguen funcionando: simplemente no asignan vendedor.

## Productos con varios precios

Además del **precio base** de la hoja Datos, un producto puede tener otros
precios con nombre (Mayorista, Distribuidor, Promoción…), los mismos que se ven
en la pestaña *Precios* de la ficha del producto y que se eligen al facturar.
Para cargarlos, la plantilla de Productos trae una segunda hoja de datos
llamada **Precios**.

### Columnas de la hoja Precios

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| CODIGO_PRINCIPAL | Sí | Código del producto. Puede estar en la hoja Datos del mismo archivo o ya existir en la empresa |
| NOMBRE_PRECIO | Sí | Nombre con el que se elige el precio al vender (máximo 100 caracteres). La hoja de consulta *Nombres_Precio* lista los que la empresa ya usa, para escribirlos igual |
| PRECIO_SIN_IVA | Sí | Valor antes de impuestos |
| VALIDO_DESDE | No | Fecha desde la que aplica, en formato AAAA-MM-DD |
| VALIDO_HASTA | No | Fecha hasta la que aplica. No puede ser anterior a VALIDO_DESDE |
| ESTADO | No | Activo o Inactivo. Vacío equivale a Activo |

### Reglas de la hoja Precios

- La hoja es **opcional**: si se deja vacía, no se toca ningún precio. Las
  plantillas antiguas sin esta hoja siguen funcionando.
- **Si un producto aparece en la hoja, esa es su lista completa de precios**: se
  reemplaza la que tenía. Un producto que no aparece conserva la suya. Es la
  misma regla que la pestaña Precios de la ficha, donde se guarda la lista
  entera.
- Un mismo nombre de precio va **una sola vez por producto** en la hoja.
- La hoja Datos se procesa primero, así que se pueden crear productos y sus
  precios en el mismo archivo. Todo va en una sola transacción: si una fila de
  Precios falla, tampoco se guardan los productos.
- El resultado indica cuántos precios se guardaron y en cuántos productos.

> Para cargas completas del catálogo (variantes, componentes, stock por bodega,
> homologaciones) existe el módulo *Carga de Productos por Excel*, que trae una
> plantilla pre-poblada con todo lo que la empresa ya tiene.

## Unidades y tipos de medida

Esta entidad carga las dos tablas del catálogo de medidas **en un solo archivo**,
porque no tiene sentido separarlas: una unidad no existe sin su tipo. La
plantilla trae seis hojas:

| Hoja | Para qué sirve |
|------|----------------|
| Instrucciones | Cómo llenar el archivo, con el establecimiento de destino |
| Tipos_Medida | Se llena: una fila por magnitud (peso, volumen, longitud) |
| Unidades | Se llena: una fila por unidad (kilogramo, litro, metro) |
| Catalogo_Sugerido | Catálogo completo listo para copiar y pegar en las dos hojas anteriores |
| Ya_Registrado | Lo que la empresa ya tiene, para saber qué códigos están ocupados |

Puede llenar una hoja o las dos. Los tipos se procesan primero, así que **una
unidad puede apuntar a un tipo creado en ese mismo archivo**.

### Columnas de la hoja Tipos_Medida

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| CODIGO_TIPO | Sí | Código corto y sin espacios (PESO, VOL, LONG). Es el que se escribe en la hoja Unidades |
| NOMBRE_TIPO | Sí | Nombre visible de la magnitud (PESO, VOLUMEN) |

### Columnas de la hoja Unidades

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| CODIGO_TIPO | Sí | Código del tipo al que pertenece la unidad |
| CODIGO_UNIDAD | Sí | Código corto de la unidad (KG, LB, LT). No puede repetirse en toda la empresa |
| NOMBRE_UNIDAD | Sí | Nombre visible (KILOGRAMO) |
| ABREVIATURA | Sí | Lo que se muestra junto a la cantidad (kg) |
| FACTOR_BASE | No | Cuántas unidades base equivale 1 de esta unidad. 1 lb = 0.453592 kg. Vacío equivale a 1 |
| ES_BASE | No | SI en la unidad de referencia del tipo. Solo una por tipo, y su factor siempre es 1 |

### A diferencia de las demás entidades

Si el código ya existe, **el registro se actualiza** con los datos del archivo en
lugar de rechazar la carga. Así se puede usar la misma plantilla para corregir
nombres, abreviaturas o factores del catálogo actual.

## Errores frecuentes

- **"Este archivo fue generado para el establecimiento X"**: descargó la
  plantilla para una empresa y la está subiendo en otra. Vuelva a descargarla con
  la empresa de destino correcta.
- **"El código ya está usado por otra unidad de un tipo de medida distinto"**: los
  códigos de unidad son únicos en toda la empresa, porque al importar productos la
  unidad se busca solo por su código. Use otro código.
- **"El tipo ya tiene a X como unidad base"**: cada magnitud tiene una sola
  referencia. Ponga NO en ES_BASE e indique el factor respecto a la base actual.
- **"No existe un tipo de medida con código X"**: créelo en la hoja Tipos_Medida
  del mismo archivo o en el módulo de Unidades de medida.
- **"La unidad de medida con código X no existe o está inactiva"** al importar
  productos: cargue primero las unidades y después los productos.
- **"No existe un vendedor con identificación o nombre X en esta empresa"** al
  importar clientes: cargue primero la entidad Vendedores, o copie el valor tal
  cual aparece en la hoja Vendedores de la plantilla.
- **"Hay N vendedores llamados X"**: hay homónimos. Escriba la identificación
  del vendedor en lugar del nombre.
- **"El vendedor X está inactivo y no puede asignarse"**: actívelo en el módulo
  de Vendedores o deje la celda VENDEDOR vacía.
- **"Hoja Precios, fila N: No existe un producto con CODIGO_PRINCIPAL X"**: el
  código no está en la hoja Datos ni en la empresa. Revise que sea el código
  principal, no el auxiliar ni el de barras.
- **"El precio X ya aparece en la fila N para el producto Y"**: el mismo
  nombre de precio está repetido para ese producto. Deje una sola fila.
- **"VALIDO_DESDE no es una fecha válida"**: escriba la fecha como
  AAAA-MM-DD (por ejemplo 2026-01-31) y mantenga la celda como texto.
- **Excel cambió mis códigos**: no reemplace las columnas ni pegue con formato;
  las celdas de la plantilla vienen como texto justamente para que códigos como
  "04" o "M3" no se transformen.

## Historial de cambios

- **1.3** — La plantilla de *Empleados* incorpora la columna opcional
  CARGAS_FAMILIARES (número entero).
- **1.2** — La plantilla de *Productos* incorpora la hoja de datos *Precios*
  (varios precios con nombre por producto, con vigencia y estado) y la hoja de
  consulta *Nombres_Precio*.
- **1.1** — Nueva entidad *Vendedores* (crea o actualiza por identificación).
  La plantilla de *Clientes* incorpora la columna opcional VENDEDOR, la hoja de
  consulta *Vendedores* de la empresa y queda atada al establecimiento de
  destino, igual que la de productos.
- **1.0** — Versión inicial. Incluye la entidad unificada *Unidades y tipos de
  medida*, que antes eran dos importaciones separadas.
