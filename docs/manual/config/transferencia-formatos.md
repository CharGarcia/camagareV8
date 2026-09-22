---
titulo: Formatos de Transferencia Bancaria
resumen: Catálogo global donde se define, columna por columna, el archivo que cada banco pide para subir un lote de transferencias (pagos a proveedores y nómina).
categoria: Configuración
ruta_modulo: config/transferencia-formatos
requiere_permiso_modulo: no
tipo: modulo
visibilidad: superadmin
etiquetas: formatos de transferencia, layout bancario, archivo del banco, cash management, pago masivo, carga de pagos, produbanco, pichincha, banco bolivariano, columnas del archivo, plantilla del banco, xlsx csv txt ancho fijo, transferencias, rol de pagos
version: 1.1
orden: 0
estado: activo
---

Cada banco pide su propio archivo para subir pagos en lote: distinto número de
columnas, distinto orden, distinta forma de escribir el monto y el tipo de
cuenta. Esta pantalla guarda esos layouts para que **Transferencias**
(`modulos/transferencias`) genere el archivo correcto sin que haya que tocar
código cada vez que aparece un banco nuevo.

## Qué es y para qué sirve

Es un **catálogo global del sistema**: no pertenece a ninguna empresa y lo que se
configura aquí lo ven todas. Cada fila es un formato — un banco, un tipo de
archivo y la lista ordenada de columnas que ese archivo lleva.

Al generar el archivo de un lote, Transferencias toma el formato elegido y
escribe una fila por cada pago del lote, resolviendo cada columna con el dato que
usted le haya asignado (nombre del beneficiario, número de cuenta, monto…).

Solo el **nivel 3 (superadministrador)** entra a esta pantalla.

## Requisitos previos

- El banco debe existir en el catálogo **Bancos del Ecuador** si quiere atar el
  formato a un banco. Un formato sin banco funciona igual: queda como genérico.
- Tener a mano la **plantilla oficial del banco** (el Excel o el instructivo que
  le entregaron), porque de ahí salen los nombres y el orden de las columnas.

## Cómo se usa

1. Entre a **Configuración → Formatos de Transferencia Bancaria** y pulse
   **Nuevo formato**.
2. Ponga el **nombre** (el que verá quien genere el lote), elija el **banco** y
   el **tipo de archivo** (Excel, CSV, TXT delimitado o TXT de ancho fijo).
3. Marque **Incluye encabezado** si el banco espera una primera fila con los
   nombres de las columnas. En Excel, indique el **nombre de la hoja**.
4. Agregue una fila por cada columna del archivo, **en el mismo orden que la
   plantilla del banco**, y en cada una elija de dónde sale el dato.
5. Guarde. El formato queda disponible en el módulo de Transferencias al crear
   un lote.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre | Sí | Cómo se llama el formato en la lista al crear un lote. |
| Banco | No | Banco al que corresponde. Vacío = formato genérico. |
| Tipo de archivo | Sí | Excel (.xlsx), CSV, TXT delimitado o TXT de ancho fijo. |
| Delimitador | Solo CSV/TXT | Carácter que separa las columnas (coma, punto y coma, tabulador…). |
| Nombre de la hoja | Solo Excel | Nombre de la pestaña del archivo generado. |
| Incluye encabezado | No | Escribe una primera fila con las etiquetas de las columnas. |
| Estado | Sí | Activo o inactivo. Un formato inactivo no aparece al crear lotes. |

### Cada columna del layout

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Etiqueta | Sí | Título de la columna, tal como lo pide el banco. |
| Origen del dato | Sí | Qué dato del pago va en esa columna (ver lista abajo). |
| Valor fijo | Solo *Texto fijo* | Texto que se repite en todas las filas (p. ej. `USD`, `PA`). |
| Tipo de dato | Sí | Texto, número o fecha. |
| Formato de número | No | *Decimal con punto* (`180.00`) o *entero en centavos* (`18000`). |
| Longitud fija / relleno / alineación | Solo ancho fijo | Rellena a un largo exacto (p. ej. código de banco a 4 dígitos con ceros). |
| Mayúsculas / quitar tildes / solo alfanumérico | No | Limpieza del texto antes de escribirlo. |
| Máx. caracteres | No | Corta el texto al largo que acepta el banco. |
| Mapeo de valores | No | Traduce el valor interno al del banco, una línea por regla: `ahorros=AHO`. |

### Orígenes de dato disponibles

Tipo de beneficiario, identificación, nombre del beneficiario, código del banco
del beneficiario, nombre del banco del beneficiario, tipo de cuenta, número de
cuenta, teléfono, **correo electrónico**, monto, concepto, número de egreso,
secuencial de la línea, número del lote, fecha de pago, cuenta de origen de la
empresa, moneda y texto fijo.

## Permisos

Solo **nivel 3**. No usa permisos por submódulo: quien no sea superadministrador
es devuelto a Configuración con un aviso.

## Reglas de negocio

- Un formato debe tener **al menos una columna**; si no, no se guarda.
- En **TXT de ancho fijo** toda columna necesita su longitud, o el guardado falla
  indicando cuál le falta.
- **No se puede eliminar** un formato que ya tenga lotes de transferencia
  asociados. Desactívelo en su lugar.
- El campo **Genérico (Excel)** viene precargado y se usa como red de seguridad
  cuando un lote no tiene formato asignado.
- Algunas filas pueden traer una **clase PHP** (badge *clase PHP*): son layouts
  que no se pueden expresar solo con columnas. De esas filas solo se puede
  cambiar nombre, banco y estado.
- Toda creación, edición, cambio de estado y eliminación queda registrada en el
  **log del sistema**.

## Formato precargado: Produbanco (Cash Management)

Corresponde a la plantilla de carga de pagos de Produbanco (20 columnas). Sirve
tanto para pagos a proveedores como para nómina: el layout es el mismo.

| # | Columna | De dónde sale | Detalle |
|---|---------|----------------|---------|
| 1 | TIPO: PAGOS | Texto fijo | Siempre `PA`. |
| 2 | NUMERO DE CUENTA DE EMPRESA | Cuenta de origen | La cuenta emisora de la forma de pago del lote, tal como está registrada. |
| 3 | NUMERO SECUENCIAL | Secuencial | 1, 2, 3… dentro del lote. |
| 4 | NUMERO DE COMPROBANTE DE PAGO | Número de egreso | Opcional para el banco. |
| 5 | CODIGO DE EMPLEADO | Identificación | Cédula o RUC del beneficiario. |
| 6 | MONEDA | Moneda | Siempre `USD`. |
| 7 | VALOR | Monto | Entero en centavos, sin punto: $180.00 se escribe `18000`. |
| 8 | FORMA DE PAGO | Texto fijo | Siempre `CTA` (crédito a cuenta). |
| 9 | CODIGO DE BANCO | Código del banco | 4 dígitos con ceros a la izquierda: `0036`, `0010`. |
| 10 | TIPO DE CUENTA | Tipo de cuenta | `AHO` ahorros, `CTE` corriente. |
| 11 | NUMERO DE CUENTA | Número de cuenta | Cuenta del beneficiario. |
| 12 | TIPO DE DOCUMENTO DE EMPLEADO | Tipo de beneficiario | `C` para empleados, `R` para proveedores. |
| 13 | NUMERO DE CEDULA DE EMPLEADO | Identificación | Máximo 13 caracteres. |
| 14 | NOMBRES DE EMPLEADO | Nombre del beneficiario | Mayúsculas, sin Ñ, tildes ni signos; máximo 40 caracteres. |
| 15-16 | DIRECCION / CIUDAD EMPLEADO | — | Se dejan en blanco (opcionales). |
| 17 | TELEFONO EMPLEADO | Teléfono | Opcional. |
| 18 | LOCALIDAD DE COBRO | — | Se deja en blanco. |
| 19 | REFERENCIA | Concepto | Motivo del pago, en mayúsculas y sin tildes; máximo 200. |
| 20 | REFERENCIA ADICIONAL | Correo electrónico | Correo del proveedor o empleado; el banco lo usa para notificar el pago. Máximo 100. |

El archivo sale con **una sola fila de encabezado** (los nombres de la tabla de
arriba) y los datos desde la segunda fila. Las filas decorativas de la plantilla
del banco (el título, la numeración 1…20 y la fila MANDATORIO/OPCIONAL) no se
reproducen.

## Integraciones con otros módulos

- **Transferencias** (`modulos/transferencias`): al crear un lote se elige uno de
  estos formatos y con él se genera el archivo que se sube a la banca en línea.
- **Bancos del Ecuador**: de ahí sale el código de 4 dígitos del banco del
  beneficiario.
- **Proveedores y Empleados**: de ahí salen el nombre, la identificación, el
  banco, el tipo y número de cuenta, el teléfono y el correo del beneficiario.

## Errores frecuentes

- **El banco rechaza el archivo por el monto**: revise si esa columna debe ir en
  *entero en centavos* o en *decimal con punto*.
- **El banco rechaza el código de banco**: casi siempre falta el relleno a 4
  dígitos con ceros a la izquierda (longitud fija 4, relleno `0`, alineación a la
  derecha).
- **Sale vacía la columna del correo**: el proveedor o el empleado no tiene
  correo registrado en su ficha.
- **El nombre del beneficiario sale cortado**: es intencional cuando el banco
  limita el largo (Produbanco: 40 caracteres).
- **No puedo eliminar un formato**: ya hay lotes que lo usan. Desactívelo.

## Historial de cambios

- **1.1** — Se agrega el origen de dato **correo electrónico del beneficiario** y
  se precarga el formato **Produbanco (Cash Management)** con sus 20 columnas.
- **1.0** — Versión inicial.
