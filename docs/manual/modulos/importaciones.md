---
titulo: Importaciones
resumen: Registro de la mercadería importada, prorrateo de los gastos de nacionalización y entrada al inventario con su costo real.
categoria: Compras
ruta_modulo: modulos/importaciones
tipo: modulo
visibilidad: todos
etiquetas: importaciones, importacion, importar mercaderia, nacionalizacion, nacionalizar, costeo, costo de importacion, costo nacionalizado, landed cost, costo real, prorrateo, prorratear gastos, repartir gastos, dai, declaracion aduanera, aduana, senae, arancel, ad valorem, fodinfa, isd, salida de divisas, iva de importacion, iva aduana, flete internacional, seguro, agente afianzado, agente de aduana, almacenaje, transporte interno, incoterm, fob, cif, proveedor del exterior, proveedor extranjero, factura del exterior, factura comercial, materia prima, producto terminado, activo fijo, cargar excel, cargar csv, plantilla excel, lote, caducidad, nup, bodega destino, kardex, entrada de inventario, asiento de importacion, declaracion de iva, formulario 104, credito tributario, registrada, borrador, pendiente de aprobacion, aprobar nacionalizacion, rechazar nacionalizacion, vincular compra, vincular liquidacion, cuentas por pagar exterior
version: 1.0
orden: 0
estado: activo
---

El módulo de **Importaciones** registra la mercadería que la empresa compra a un
proveedor del exterior y calcula su **costo nacionalizado**: el valor de la
factura del exterior más los gastos de nacionalización que forman parte del
costo (flete, seguro, arancel, agente afianzado, etc.), repartidos entre los
productos. Al nacionalizar, la mercadería entra al inventario con ese costo y se
genera el asiento contable. El IVA pagado en aduana pasa a la **Declaración de IVA**.

## Qué es y para qué sirve

Una importación reúne en un solo documento:

- Los **productos** importados, con su cantidad y precio FOB.
- Las **facturas del proveedor del exterior**.
- Los **gastos de nacionalización** (los rubros de la declaración aduanera y los
  servicios que se pagaron aparte).

Con esos datos el sistema reparte los gastos entre las líneas de producto
(**prorrateo**) y obtiene el costo unitario nacionalizado de cada una. Ese es el
costo con el que el producto entra al kardex.

La importación no es un comprobante electrónico del SRI: la serie solo sirve
para numerarla por sucursal.

## Requisitos previos

- **Proveedor del exterior**: el proveedor debe estar registrado con
  identificación de tipo **Exterior** (sin RUC ecuatoriano). Si no lo está, el
  sistema no deja guardar.
- **Productos**: cada línea debe estar vinculada a un producto del catálogo. Si
  el producto no existe, créelo primero.
- **Serie con secuencial de Importaciones**: el punto de emisión debe tener
  configurado el secuencial del documento *Importaciones* en **Empresa → Puntos
  de emisión**. Solo se ofrecen las series que lo tienen configurado.
- **Bodega**: debe existir al menos una bodega a la que el usuario tenga acceso.
- **Cuentas contables** (para el asiento): se configuran en **Contabilidad →
  Configuración contable**, concepto *Importaciones* (ver *Asiento contable*).

## Estados de una importación

| Estado | Qué significa | ¿Se puede editar? |
|--------|---------------|-------------------|
| Borrador | Se está preparando. | Sí |
| Registrada | Los datos están confirmados, pero todavía no se envía al inventario. Ya cuenta para la Declaración de IVA. | No (se puede volver a Borrador) |
| Pendiente aprobación | Se pidió nacionalizar y la empresa exige aprobación. El costo ya está calculado, pero el kardex todavía no se toca. | No |
| Nacionalizada | La mercadería ya entró al inventario y se generó el asiento. | No |

Recorrido:

- **Borrador → Registrada** con **Marcar como Registrada**, y de vuelta con
  **Volver a Borrador**.
- **Borrador o Registrada → Nacionalizada** con **Procesar Inventario /
  Nacionalizar**. Si la empresa exige aprobación, pasa antes por **Pendiente
  aprobación**.
- **Pendiente aprobación → Nacionalizada** al aprobar, o **→ Borrador** al rechazar.

## Cómo se usa

1. Pulse **Nueva**.
2. En **Datos generales** elija la **Serie**, la **Bodega destino** y el
   **Proveedor del exterior**. El **N° Importación** se asigna solo al guardar.
3. Elija el **Criterio de prorrateo** (ver *Cómo se reparten los gastos*).
4. En **Productos (FOB)** agregue las líneas: búsquelas en el catálogo con
   *Buscar producto en catálogo...*, con **Agregar línea** o cárguelas desde un
   archivo con **Cargar desde Excel/CSV**.
5. En **Facturas del exterior** registre las facturas del proveedor con
   **Agregar factura**.
6. En **Gastos de nacionalización** agregue cada rubro con **Agregar gasto**.
7. Pulse **Guardar**.
8. En **Prorrateo / Resumen** pulse **Calcular prorrateo** para revisar el costo
   que tendrá cada producto. Es solo una vista previa: no mueve nada.
9. Cuando todo esté correcto, pulse **Procesar Inventario / Nacionalizar** y
   confirme. Esta acción no se puede deshacer desde este módulo.

Si solo necesita confirmar los datos (por ejemplo, para que el IVA de aduana
entre en la declaración) sin enviar la mercadería al inventario, use **Marcar
como Registrada**.

## Campos del formulario

### Datos generales

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Serie | Sí | Establecimiento y punto de emisión con que se numera la importación. Se elige una sola vez, al crearla. |
| N° Importación | — | Número consecutivo de la serie. Lo asigna el sistema al guardar. |
| Referencia DAI | No | Número de la declaración aduanera. |
| Incoterm | No | Condición de entrega pactada (FOB, CFR, CIF, EXW, FCA, DAP, DDP…). |
| Bodega destino | Sí | Bodega a la que entra la mercadería. Cada línea puede indicar otra bodega en su propia columna **Bodega**. |
| Proveedor del exterior | Sí | Debe tener identificación tipo "Exterior". |
| Agente afianzado (opcional) | No | Proveedor local que hizo el trámite aduanero. |
| Criterio de prorrateo | Sí | Por valor FOB, por peso (Kg), por volumen (m3) o por cantidad. |
| Fecha embarque / Fecha llegada | No | Fechas informativas del envío. |
| Fecha nacionalización | No | Se actualiza automáticamente al procesar el inventario. |
| Observaciones | No | Texto libre. |

Debajo se ven los totales de solo lectura: **Subtotal FOB**, **Gastos
capitalizables**, **IVA**, **ISD**, **Otros gastos** y **Costo total nacionalizado**.

### Productos (FOB)

| Columna | Qué significa |
|---------|---------------|
| Producto / Descripción | Producto del catálogo. Toda línea debe estar vinculada a uno. |
| Cant. | Cantidad importada. Debe ser mayor a cero. |
| P. Unit. FOB / Total FOB | Precio FOB unitario y total de la línea. |
| Peso Kg | Obligatorio en todas las líneas si el criterio es por peso. |
| Vol. m3 | Obligatorio en todas las líneas si el criterio es por volumen. |
| Lote / Caducidad / NUP | Datos que acompañan la entrada al kardex. |
| Bodega | Bodega de la línea; si se deja vacía, se usa la bodega destino. |
| Tipo | **Producto Terminado** (por defecto), **Materia Prima** o **Activo Fijo**. Ver *Clasificación de las líneas*. |
| Costo Unit. Nac. / Costo Total Nac. | Costo nacionalizado de la línea, calculado por el prorrateo. |

### Facturas del exterior

| Columna | Qué significa |
|---------|---------------|
| Proveedor del exterior | Proveedor que emitió la factura. |
| N° Factura / Fecha | Datos de la factura comercial. |
| Monto USD | Valor de la factura. Debe ser mayor a cero. |
| Forma de pago | Texto libre. |
| Plazo (días) | Días de crédito; con la fecha de la factura definen su vencimiento en Cuentas por Pagar. |

### Gastos de nacionalización

| Columna | Qué significa |
|---------|---------------|
| Origen | **Manual DAI** (el rubro se digita aquí), **Vincular Compra** o **Vincular Liquidación** (el gasto ya está registrado como una factura de compra o una liquidación de compra). |
| Tipo de gasto | Arancel Ad-Valorem, FODINFA, IVA de importación, ISD, Flete internacional, Seguro, Agente afianzado, Almacenaje, Transporte interno u Otro. |
| Descripción / Documento vinculado | Texto libre o, si el origen es vinculado, el documento elegido. |
| Monto | Valor del gasto. Debe ser mayor a cero. Al vincular un documento, se llena con su total. |
| Prorrateable | Si el gasto se suma al costo del inventario. Ver *Cómo se reparten los gastos*. |

## Cómo se reparten los gastos (prorrateo)

El **costo total nacionalizado** es la suma de:

- el total de las **facturas del exterior**, y
- los **gastos capitalizables**.

Qué gastos son capitalizables:

- **IVA de importación** e **ISD** nunca se capitalizan, aunque se marque
  "Prorrateable". Quedan aparte (el IVA como crédito tributario).
- Los demás gastos de origen **Manual DAI** se capitalizan si están marcados como
  **Prorrateable**; si se desmarcan, van a **Otros gastos**.
- Los gastos **vinculados** a una Compra o Liquidación siempre se capitalizan. En
  ellos no se pueden elegir los tipos IVA de importación ni ISD, porque su propio
  documento ya registró el impuesto y la cuenta por pagar.

Ese total se reparte entre las líneas en proporción a la base que indique el
**Criterio de prorrateo**:

| Criterio | Base de reparto |
|----------|-----------------|
| Por valor FOB | Total FOB de cada línea |
| Por peso (Kg) | Peso de cada línea |
| Por volumen (m3) | Volumen de cada línea |
| Por cantidad | Cantidad de cada línea |

El costo de cada línea se redondea a dos decimales; la diferencia de centavos
que quede se ajusta en la línea de mayor base, para que la suma cuadre exacto con
el costo total. El costo unitario es el costo de la línea dividido para su cantidad.

La pestaña **Prorrateo / Resumen** muestra el resultado por producto y los
totales: **Total factura exterior**, **Capitalizable manual**, **Capitalizable
vinculado**, **IVA / ISD**, **Otros gastos** y **Costo total nacionalizado**.

## Clasificación de las líneas (Producto Terminado, Materia Prima, Activo Fijo)

La columna **Tipo** de cada línea influye en dos lugares:

- **Asiento contable**: si la empresa configuró **las dos** cuentas, *Inventario
  Materia Prima* e *Inventario Producto Terminado*, el costo se reparte entre
  ellas según el tipo de cada línea. Las líneas de Activo Fijo van con Producto
  Terminado. Si falta alguna de las dos cuentas, todo el costo va a la cuenta
  general *Inventario (costo nacionalizado)*.
- **Declaración de IVA**: el IVA de aduana se reparte entre las líneas de
  **Activo Fijo** y el resto, en proporción a su valor FOB, y cada parte se
  declara en su propia fila del Formulario 104.

## Cargar las líneas desde Excel o CSV

1. Pulse **Plantilla** para descargar el archivo de ejemplo. Trae la hoja
   *Datos* con las columnas y una segunda hoja *Productos* con los códigos
   válidos de la empresa.
2. Llene una fila por producto. Columnas:
   `codigo_producto, descripcion, cantidad, precio_unitario_fob, peso_kg, volumen_m3, numero_lote, fecha_caducidad, nup`.
   Los números aceptan coma o punto como separador decimal.
3. Pulse **Cargar desde Excel/CSV** y elija el archivo (.xlsx, .xls o .csv).

El sistema busca cada producto por su código y agrega las líneas a la tabla.
Las que tienen problemas quedan **marcadas en rojo** y el aviso indica cuántas
son: corríjalas antes de guardar. Las líneas no quedan guardadas hasta que pulse
**Guardar**.

## Aprobación de la nacionalización

Si en **Aprobaciones** está activa la aprobación de *Nacionalización de
importaciones* (con su monto mínimo, si lo tiene), al pulsar **Procesar
Inventario / Nacionalizar**:

1. El sistema calcula y guarda los costos, y la importación queda **Pendiente
   aprobación**. El kardex y el asiento todavía no se tocan.
2. Si la configuración lo indica, se envía un correo a los aprobadores con un
   enlace para **Aprobar** o **Rechazar** sin entrar al sistema. Al usuario que
   pidió la nacionalización no se le envía.
3. Un aprobador también puede abrir la importación y, en **Prorrateo /
   Resumen**, pulsar **Aprobar nacionalización** o **Rechazar**. Para rechazar
   hay que escribir el motivo.

Al aprobar, la mercadería entra al inventario y se genera el asiento. Al
rechazar, la importación vuelve a **Borrador** para corregirla.

Quien pidió la nacionalización no puede aprobarla ni rechazarla él mismo (salvo
el superadministrador).

## Integraciones con otros módulos

### Inventario (kardex)

Al nacionalizar, cada línea genera una **entrada** en el kardex de su bodega,
con su costo unitario nacionalizado, su lote, caducidad y NUP. La fecha de
nacionalización queda con la fecha del día.

### Asiento contable

Al nacionalizar se genera el asiento con la fecha de nacionalización. Las cuentas
salen de **Configuración contable**, concepto *Importaciones*:

| Concepto | Valor |
|----------|-------|
| Inventario (costo nacionalizado), o Inventario Materia Prima / Producto Terminado | Costo total nacionalizado |
| IVA Crédito Tributario (importación) | IVA de importación |
| ISD (gasto financiero) | ISD |
| Otros gastos de importación (no capitalizables) | Otros gastos |
| Cuentas por Pagar Proveedor Exterior | Total de las facturas del exterior |
| Cuentas por Pagar Tributos Aduaneros | Total de los gastos Manual DAI |
| Reclasificación gastos ya facturados (Compras/Liquidación) | Total de los gastos vinculados |

Si falta configurar una cuenta, la importación **sí** se nacionaliza y el
mensaje avisa que el asiento no se pudo generar. Una vez configurada la cuenta,
el asiento se genera solo la próxima vez que abra **Libro Diario / Asientos**
(botón **Sincronizar**).

Si la empresa apagó la contabilización de Importaciones en la configuración
contable, no se crea el asiento.

La pestaña **Asiento contable** del modal solo la ven los usuarios con acceso a
Asientos Contables.

### Declaración de IVA

El **IVA de importación** (gastos de tipo *IVA de importación* con origen
Manual DAI) cuenta como crédito tributario desde que la importación está
**Registrada** o **Nacionalizada**. Un borrador no cuenta.

- Se declara con la fecha de nacionalización de la importación.
- La parte de las líneas de **Activo Fijo** va a la fila de importaciones de
  activos fijos; el resto, a la fila de importaciones de bienes, según los
  casilleros configurados en **Empresa → Form 104 IVA**.
- Como el módulo no captura la base imponible, la base se calcula de forma
  referencial: IVA ÷ 15%. El concepto lo indica con *(Base referencial)*.

Si la importación vuelve a Borrador, sus valores salen de la declaración.

### Cuentas por Pagar

Las **facturas del exterior** aparecen como deuda con el proveedor del exterior
en **Cuentas por Pagar**, con la marca `IMP`. El vencimiento es la fecha de la
factura más el **Plazo (días)**.

### Compras y Liquidaciones de compra

Un gasto que ya se registró como factura de compra o liquidación de compra se
vincula con el origen **Vincular Compra** o **Vincular Liquidación** en vez de
digitarlo otra vez. Su valor se suma al costo del inventario y en el asiento se
reclasifica a la cuenta de inventario.

## Documentos del módulo

En la barra superior del modal, con la importación ya guardada:

- **PDF**: documento de la importación con productos, tipo de línea, costos,
  gastos de nacionalización y totales.
- **Excel**: el mismo contenido en una hoja de cálculo.

Desde esa barra también se puede registrar un proveedor o un producto nuevo, si
el usuario tiene permiso de crear en esos módulos.

## Buscar y filtrar el listado

El listado muestra N° Importación, Referencia DAI, Proveedor exterior,
Incoterm, Bodega destino, Fecha nacionalización, Subtotal FOB, Costo total y
Estado.

Los filtros permiten buscar por fechas de nacionalización, embarque y llegada,
estado, serie, número, Referencia DAI, incoterm, bodega, criterio de prorrateo,
si tiene asiento contable, agente afianzado, usuario que registró, rangos de
valores (costo nacionalizado, subtotal FOB, gastos capitalizables, IVA, ISD,
otros gastos), proveedor, identificación y observaciones.

La búsqueda libre dentro de las importaciones encuentra productos, códigos,
lotes, facturas del exterior, gastos y compras o liquidaciones vinculadas.

## Permisos

| Acción | Permiso |
|--------|---------|
| Ver el listado, abrir importaciones, calcular el prorrateo, PDF y Excel | Ver |
| Crear importaciones y cargar líneas desde Excel/CSV | Crear |
| Modificar, Marcar como Registrada, Volver a Borrador, Procesar Inventario / Nacionalizar | Actualizar |
| Aprobar o rechazar la nacionalización | Actualizar y ser aprobador configurado (o superadministrador) |
| Eliminar | Eliminar |

Sin **acceso total**, el usuario solo ve y gestiona las importaciones que él
registró.

## Reglas de negocio

- Solo se edita una importación en **Borrador**.
- El total FOB de las líneas debe coincidir con el total de las facturas del
  exterior, con una tolerancia de 0,50.
- Para nacionalizar debe haber al menos un gasto de nacionalización registrado.
- No se puede nacionalizar si el período contable del día está cerrado.
- Una importación no se nacionaliza dos veces: si dos usuarios lo intentan a la
  vez, el segundo recibe un aviso.
- El botón **Eliminar** solo aparece en Borrador. Una importación Registrada,
  Pendiente aprobación o Nacionalizada no se puede eliminar.

## Errores frecuentes

- **El proveedor de una importación debe tener identificación del exterior. "…"
  no está configurado como proveedor del exterior.**: cambie el tipo de
  identificación del proveedor a Exterior en Proveedores.
- **El total FOB de las líneas de producto ($…) no coincide con el total
  facturado por el proveedor del exterior ($…). Verifique los montos.**: la
  diferencia supera 0,50. Revise cantidades, precios o las facturas.
- **La línea #… debe estar vinculada a un producto del catálogo. Búsquelo y
  selecciónelo de la lista (o créelo primero con el botón "Crear producto").**:
  hay una línea escrita a mano o del Excel cuyo código no existe.
- **La línea #… debe tener peso (kg) porque el criterio de prorrateo es por
  peso.** (o volumen): complete ese dato en todas las líneas o cambie el criterio.
- **Debe registrar al menos una factura del proveedor del exterior.**: agregue
  la factura en su pestaña.
- **Debe registrar al menos un gasto de nacionalización antes de procesar el
  inventario (aunque sea el arancel o el flete).**: agregue al menos un gasto.
- **No se puede prorratear por '…': el total de esa base es cero en las
  líneas.**: las líneas no tienen peso, volumen o valor según el criterio.
- **No se puede modificar una importación pendiente de aprobación, registrada,
  nacionalizada, cerrada o anulada. Vuélvala a Borrador primero.**: use
  **Volver a Borrador** si está Registrada.
- **No se puede eliminar una importación Registrada. Vuélvala a Borrador
  primero.**: vuélvala a Borrador y luego elimínela.
- **No puede aprobar una importación que usted mismo solicitó nacionalizar.
  Debe aprobarla otro usuario autorizado.**: la aprobación la hace otro aprobador.
- **No se puede nacionalizar la importación porque el período contable de hoy
  está cerrado.**: pida al contador que abra el período.
- **No se ha configurado la cuenta contable para '…' del concepto
  Importaciones.**: configure esa cuenta en Configuración contable; la
  importación ya quedó nacionalizada y el asiento se genera al sincronizar.
- **No están configurados los secuenciales para esta serie.**: configure el
  secuencial de Importaciones en Empresa → Puntos de emisión.
- **El producto con código "…" no existe en la empresa.** (al cargar el Excel):
  use un código de la hoja *Productos* de la plantilla.

## Historial de cambios

- **1.0** — Versión inicial del manual.
