---
titulo: Factura de Reembolso
resumen: Factura electrónica para reembolsar gastos pagados a nombre del cliente (Anexo ATS código 41), con su propia numeración.
categoria: Ventas
ruta_modulo: modulos/factura-reembolso
tipo: modulo
visibilidad: todos
etiquetas: factura de reembolso, reembolso de gastos, ats 41, comprobante de venta emitido por reembolso, intermediario, terceros reembolsados, sri, comprobante electronico, buscar factura de reembolso, buscador, filtros, filtrar facturas de reembolso, buscar por proveedor, comprobante de terceros, filtro de fechas, chips
version: 1.3
orden: 21
estado: activo
---

El módulo de **Factura de Reembolso** es para empresas que actúan como
**intermediarias**: pagan gastos a proveedores terceros en nombre de su
cliente (por ejemplo, una agencia que paga hospedaje o pasajes) y luego se
los re-facturan. Es un documento **SRI real** (sigue siendo `codDoc=01`,
Factura), pero se declara ante el SRI con la clasificación **ATS código 41
"Comprobante de venta emitido por reembolso"**, y por eso vive como módulo
**separado** de Facturas de Venta, con su propia numeración.

## Qué es y para qué sirve

Cuando el valor que factura la empresa corresponde, en todo o en parte, a un
gasto que pagó por cuenta del cliente (no un ingreso propio), ese documento
se emite aquí en vez de en Factura de Venta. El sistema declara ante el SRI
tanto el detalle normal de la factura como el sustento de cada gasto
reembolsado (proveedor, documento y su IVA).

## Requisitos previos

- Firma electrónica de la empresa configurada.
- Secuencial inicial para el tipo de documento **"Facturas de reembolso"**
  configurado en **Empresa → Secuenciales** (independiente del secuencial de
  Facturas de Venta). Sin esto el botón **Nueva** queda deshabilitado.
- Al menos un establecimiento/punto de emisión con ese secuencial asignado.
- Si se quiere vincular un tercero a una compra ya registrada, esa compra
  debe existir en el módulo de **Compras**.

## Cómo se usa

1. Pulse **Nueva**.
2. Elija el cliente y la serie (punto de emisión).
3. En la pestaña **Factura de reembolso**, agregue una línea por cada gasto
   reembolsado (marcada como "Reembolso") y, si la empresa cobra honorarios
   propios por la gestión, una línea adicional sin marcar (esa sí lleva IVA
   normal).
4. En la pestaña **Terceros reembolsados**, agregue el sustento de cada
   gasto: busque la compra ya registrada (autocompleta proveedor, documento
   e impuestos) o ingrésela manualmente si el proveedor no está en Compras.
   **Es obligatorio al menos un tercero** — sin eso la factura no tiene
   sentido como reembolso.
5. Guarde. Con la factura guardada (borrador), use la barra de acciones
   superior para **Enviar al SRI**.
6. Autorizada, ya se pueden usar los botones de **PDF**, **XML**, **Excel** y
   **Correo** (el correo también se envía automáticamente al autorizarse).
   El botón **Excel** (icono verde) descarga el detalle, los terceros
   reembolsados y los totales en una hoja de cálculo.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la factura: número,
secuencial, fecha, cliente, identificación, cantidad de terceros, valor
reembolsado, total y usuario. Además busca en las observaciones, el número de
autorización, la clave de acceso, las **descripciones de las líneas** y los
**comprobantes de terceros** (proveedor, RUC y número del comprobante). La
columna **Estado** no entra en la búsqueda libre: para filtrar por ella use la
ventana de filtros. Puede escribir varias palabras en cualquier orden y no
importan mayúsculas ni tildes. Para limpiar, borre el texto o pulse Escape en
el cuadro. Mientras busca, aparece un **círculo girando** al final del cuadro y
la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Factura de reembolso** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, autorizado, anulado), correo (enviado o pendiente), serie, número, secuencial, con o sin asiento contable, fecha de autorización, usuario que registró |
| Valores | Total, reembolsado (base + IVA, como la columna), base del reembolso, subtotal, descuento y cantidad de comprobantes de terceros (cada uno con mínimo y máximo) |
| Terceros reembolsados | Proveedor del gasto, RUC del proveedor, N° del comprobante y su fecha (basta con que un comprobante coincida) |
| Cliente | Cliente, RUC / cédula, observaciones, N° autorización, clave de acceso |

El selector *Usuario que registró* lista solo a quienes ya registraron facturas
de reembolso en la empresa, y *Serie* solo las series con facturas guardadas.

**Pestaña Detalles** (lo que hay dentro de la factura). Es un único cuadro,
**Buscar libremente dentro de las facturas de reembolso**: escriba una
descripción, un proveedor, un RUC, el número de un comprobante de terceros,
una forma de pago o un dato de la información adicional, y aparece la lista de
**cada línea que coincide** con la factura a la que pertenece (número, fecha,
cliente y estado). Un clic en la fila deja el listado mostrando solo esa
factura; el ícono de la derecha la abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|----------------|
| Cliente | Sí | A quién se le factura el reembolso. |
| Serie / Secuencial | Sí | Numeración propia de Facturas de Reembolso. |
| Línea de detalle | Sí (≥1) | Cada ítem facturado; marcar "Reembolso" si es un gasto puro (sin IVA propio) o dejarlo sin marcar si es honorario propio (con IVA). |
| Tercero reembolsado | Sí (≥1) | El comprobante del proveedor que la empresa pagó a nombre del cliente: identificación, tipo, documento (serie/secuencial/fecha/autorización) y su base + IVA. |
| Formas de pago | Sí | Igual que cualquier factura; debe cuadrar con el total. |

## Permisos

Mismo esquema que el resto de módulos operativos: **ver/crear/actualizar/eliminar**
por submódulo, y **acceso total** para ver los documentos de toda la empresa
en vez de solo los propios. El permiso se administra en `/config/permisos-modulos`,
bajo el submódulo "Factura de Reembolso" (colgado junto a Factura de Venta).

## Reglas de negocio

- El detalle de las líneas marcadas "Reembolso" usa el código de IVA **6 – No
  objeto de impuesto** (no es una venta propia, no genera IVA de la empresa).
- Los 3 totales agregados que exige el SRI cuando `codDocReembolso = 41`
  (`totalComprobantesReembolso`, `totalBaseImponibleReembolso`,
  `totalImpuestoReembolso`) se calculan automáticamente desde los terceros —
  no son editables a mano.
- Solo se puede editar o eliminar una factura en estado **borrador**. Una vez
  autorizada por el SRI, solo se puede **anular**.
- El asiento contable se genera recién cuando el SRI autoriza el documento
  (no al guardar el borrador), para no contabilizar documentos que se anulan
  antes de enviarse.

## Integraciones con otros módulos

- **Compras**: el buscador de la pestaña "Terceros reembolsados" toma los
  datos del proveedor y sus impuestos de una compra ya registrada.
- **Contabilidad**: genera un asiento de "cuenta puente" — Debe Cuentas por
  Cobrar Cliente, Haber "Reembolso a Terceros" (el gasto pasado, que NO es
  ingreso propio) + Ingresos por Honorarios/IVA si hay línea de honorarios.
  Las cuentas se configuran en Contabilidad → Configuración contable,
  concepto **"Factura de Reembolso"**. Si no se configuran las propias, el
  sistema reutiliza las mismas cuentas de Cuentas por Cobrar/Ventas/IVA que
  ya tenga configuradas Factura de Venta (salvo la cuenta puente, que es
  nueva y no tiene equivalente).
- **Cuentas por Cobrar / Ingresos**: el cliente debe el total de la factura y
  se puede registrar su cobro desde Ingresos. *(Nota: por ahora el documento
  todavía no aparece en el listado principal ni en la antigüedad de saldos de
  Cuentas por Cobrar — es una integración más profunda pendiente.)*
- **Correo**: al autorizarse en el SRI, se envía automáticamente el PDF + XML
  al correo del cliente (o a los que se indiquen manualmente con el botón de
  correo).

## Errores frecuentes

- **Botón "Nueva" deshabilitado**: falta configurar el secuencial inicial
  para "Facturas de reembolso" en Empresa → Secuenciales.
- **"Debe agregar al menos un tercero reembolsado"**: es obligatorio — una
  factura de reembolso sin sustento de terceros no es válida.
- **El asiento contable no se genera / queda descuadrado**: falta asignar
  alguna cuenta del concepto "Factura de Reembolso" en Configuración
  Contable (especialmente la cuenta puente "Reembolso a Terceros", que no
  tiene una cuenta de respaldo automática).

## Períodos contables cerrados

Lo que mueve inventario o contabilidad no puede tocar un período ya cerrado.
Emitirla, modificarla, anularla y eliminarla se rechazan si la fecha cae en un
mes cerrado.

Al modificar se revisan **las dos fechas** —la nueva y aquella con la que está
registrado—: mover un documento de un mes cerrado a uno abierto lo alteraría
igual. Los períodos se abren y se cierran en **Contabilidad → Períodos
Contables**; reabrir el período permite la operación de inmediato.

## Historial de cambios

- **1.3** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas de la factura (incluidos terceros y
  reembolsado) y en autorización, clave de acceso, líneas y comprobantes de
  terceros, salvo Estado. Los filtros pasan a una **ventana propia** (botón del
  embudo, se aplican con *Aplicar*) con dos pestañas: **Factura de reembolso**
  (filtros por campo, con criterios nuevos: correo, con/sin asiento, fecha de
  autorización, usuario como lista, reembolsado, subtotal, descuento, cantidad
  de terceros, proveedor, RUC y comprobante de reembolso, observaciones,
  autorización y clave) y **Detalles** (búsqueda dentro de líneas, comprobantes
  de terceros, formas de pago e información adicional). Los filtros activos se
  ven como etiquetas dentro del cuadro y la tabla se atenúa mientras carga.
- **1.2** — El módulo respeta ahora el **cierre contable**: no se puede operar
  sobre una factura de reembolso cuyo período esté cerrado. Antes no se comprobaba.
- **1.0** — Versión inicial: creación, edición, terceros reembolsados
  (vinculados a Compras o manuales), envío al SRI, asiento contable de
  cuenta puente, PDF y correo. Sin WhatsApp e integración simple con
  Cuentas por Cobrar (sin aparecer aún en su listado/antigüedad de saldos).
- **1.1** — Botón **Excel** en la barra de acciones del modal: exporta
  detalle, terceros reembolsados, totales y forma de pago a una hoja de
  cálculo (mismo patrón que Factura de Venta).
