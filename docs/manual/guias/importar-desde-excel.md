---
titulo: Importar datos desde Excel
resumen: Cargar clientes, productos, proveedores, unidades de medida y otros catálogos en bloque a partir de una plantilla de Excel.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: importar, excel, xlsx, carga masiva, plantilla, subir datos, migrar, cargar clientes, cargar productos, unidades de medida, tipos de medida, importador
version: 1.0
orden: 20
estado: activo
---

El **Importador desde Excel** (Configuración → Importador desde Excel) sirve para
cargar catálogos completos sin escribirlos uno por uno. Se descarga una plantilla,
se llena en Excel y se sube. Está disponible para administradores y
superadministradores.

## Qué se puede importar

**Tablas operativas** (pertenecen a una empresa): clientes, productos,
proveedores, empleados, vehículos, unidades y tipos de medida, plan de cuentas.

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
- **Excel cambió mis códigos**: no reemplace las columnas ni pegue con formato;
  las celdas de la plantilla vienen como texto justamente para que códigos como
  "04" o "M3" no se transformen.

## Historial de cambios

- **1.0** — Versión inicial. Incluye la entidad unificada *Unidades y tipos de
  medida*, que antes eran dos importaciones separadas.
