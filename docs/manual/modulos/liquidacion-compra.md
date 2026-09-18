---
titulo: Liquidaciones de compra
resumen: Comprobante que emite la empresa cuando el proveedor no puede emitir factura.
categoria: Compras
ruta_modulo: modulos/liquidacion-compra
tipo: modulo
visibilidad: todos
etiquetas: liquidacion de compra, liquidacion, proveedor sin factura, comprobante 03, sri, sustento, eliminar, borrar, borrador, anular, buscar liquidacion, buscador, filtros, filtrar liquidaciones, buscar por producto, saldo pendiente, estado de pago, chips, aparecen documentos que no busque, resultados que no corresponden, la busqueda trae otros documentos
version: 1.7
orden: 40
estado: activo
---

La **liquidación de compra** es el comprobante que emite la propia empresa cuando
compra a alguien que no puede darle factura. En lugar de recibir el documento, la
empresa lo emite y lo envía al SRI.

## Cuándo se usa

En los casos que la normativa permite: proveedores sin RUC, personas naturales no
obligadas a facturar en ciertos supuestos, servicios ocasionales.

No es un sustituto de la factura: si el proveedor puede emitirla, corresponde
registrar una compra normal.

## Cómo se emite

1. Pulse **Nuevo**.
2. Elija el **proveedor**.
3. Indique la **fecha de emisión**.
4. Elija la **serie de emisión** y revise el **secuencial**.
5. Elija el **código de sustento tributario**.
6. Añada al menos un **ítem**.
7. Guarde y envíe al SRI.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Proveedor | Obligatorio |
| Fecha de emisión | Obligatoria |
| Serie de emisión | Obligatoria |
| Código de sustento tributario | Obligatorio |
| Secuencial | Obligatorio |
| Ítems | Al menos uno |

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la liquidación: N°
liquidación, secuencial, fecha, proveedor, identificación, subtotal, descuento,
total y usuario; además, en las observaciones. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes. Para limpiar, borre el texto
o pulse Escape en el cuadro. Mientras busca, aparece un **círculo girando** al
final del cuadro y la tabla se ve atenuada.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** Para que el cuadro
devuelva solo liquidaciones donde se vea por qué coinciden, estos datos se
consultan en la ventana de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Clave de acceso y N° de autorización | Pestaña *Liquidación* → **N° autorización** |
| Productos o servicios de la liquidación (código y descripción) | Pestaña *Detalles* |
| Correo y Estado | Pestaña *Liquidación* |

En un comprobante electrónico la clave de acceso y el número de autorización son
el mismo número de 49 dígitos, que lleva dentro la fecha, el RUC y el número del
documento (el filtro *N° autorización* busca en los dos). Al escribir un número de
documento en el cuadro aparecían liquidaciones ajenas cuya clave contenía por
casualidad esa secuencia; ahora ese número solo encuentra la liquidación que
realmente lo tiene.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Liquidación** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), serie, secuencial, estado (borrador, autorizado, anulado), estado de pago (pendiente / abonada / pagada), estado de correo (pendiente / enviado), N° liquidación, N° autorización o clave de acceso, sustento tributario, con o sin asiento contable, con o sin retención |
| Valores | Total, subtotal, descuento, saldo pendiente y valor retenido (cada uno con mínimo y máximo) |
| Proveedor | Proveedor, identificación, usuario, observaciones |

Los selectores *Serie*, *Sustento tributario* y *Usuario* listan solo lo que la
empresa ya usó. El *estado de pago* y el *saldo pendiente* se calculan con los
pagos registrados en Egresos y las retenciones no anuladas de la liquidación.

**Pestaña Detalles** (lo que hay dentro de la liquidación). Es un único cuadro,
**Buscar libremente dentro de las liquidaciones**: escriba un producto o
servicio, un código, una forma de pago SRI, un plazo o un dato de la
información adicional, y aparece la lista de **cada línea que coincide** con la
liquidación a la que pertenece (número, fecha, proveedor y estado). Un clic en
la fila deja el listado mostrando solo esa liquidación; el ícono de la derecha
la abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Documentos del módulo

Desde la liquidación guardada están disponibles el **PDF** del documento, su
**Excel**, su **XML** y el envío por **correo** o **WhatsApp**, en la barra de
acciones al inicio del formulario.

## Eliminar una liquidación

Solo se pueden eliminar las liquidaciones en estado **borrador** —incluidas las
que llegaron así desde una migración—. Una vez emitida al SRI (autorizada) el
comprobante ya no se borra: se **anula**.

El botón **Eliminar** aparece abajo a la izquierda del formulario, y solo si su
usuario tiene el permiso de eliminar en el módulo. Al confirmar:

- La liquidación deja de aparecer en el listado (eliminación lógica: el registro
  se conserva en la base y queda anotado en la auditoría).
- Se **anula el asiento contable** de la liquidación, si tenía uno.
- Se **anulan los pagos (egresos) vinculados** que no estuvieran ya anulados.
- Se limpian los casilleros de la declaración de IVA correspondientes.

Si la liquidación tiene una **retención asociada**, el sistema no la deja
eliminar: primero elimine la retención desde el módulo *Retenciones de compra* y
vuelva a intentarlo.

## Errores frecuentes

- **"Solo se pueden eliminar liquidaciones en estado borrador"**: el comprobante
  ya fue emitido. Use **Anular** en la barra de acciones.
- **"No se puede eliminar la liquidación porque tiene una retención asociada"**:
  elimine primero esa retención en el módulo *Retenciones de compra*.
- **"La liquidación debe tener al menos un ítem"**: añada el detalle antes de
  guardar.
- **"Debe seleccionar el código de sustento tributario"**: es obligatorio para
  que el SRI acepte el comprobante.
- **El SRI rechaza el comprobante**: revise que los datos del proveedor sean
  correctos y que el sustento elegido corresponda al tipo de compra.

## Períodos contables cerrados

Un documento que mueve cartera, inventario o contabilidad no puede tocar un
período ya cerrado. El sistema lo comprueba en las cuatro operaciones:

| Operación | Qué se comprueba |
|-----------|------------------|
| Emitir | Que la fecha de emisión no caiga en un período cerrado |
| Modificar | La fecha nueva **y** aquella con la que está registrado |
| Anular | La fecha del documento (anular revierte sus movimientos) |
| Eliminar | La fecha del documento |

Al modificar se revisan las dos fechas a propósito: mover un documento de un
mes cerrado a uno abierto lo alteraría igual. Los períodos se abren y se
cierran en **Contabilidad → Períodos Contables**; reabrir el período permite
la operación de inmediato.

## Historial de cambios

- **1.7** — Corregido: al buscar un **número de documento** en el cuadro aparecían
  también documentos que no lo tenían. La búsqueda libre miraba dentro de la **clave de
  acceso** (49 dígitos, que llevan la fecha, el RUC y el número del documento) y cualquier
  número corto caía ahí por casualidad. Ahora la clave se consulta en la ventana de
  filtros,
  junto con el **número de autorización** (el filtro *N° autorización* busca en los dos);
  los **productos o servicios** de la liquidación pasan a la pestaña *Detalles*, que sí
  muestra qué línea coincidió.

- **1.6** — **Búsqueda del listado más rápida**: el conteo y la página salen
  de una sola consulta y la condición de búsqueda se evalúa una sola vez (antes, dos: una para contar y otra para la página). Las fechas y los montos solo se comparan
  cuando lo escrito tiene números, así que buscar un nombre o un producto responde
  antes. Si se sigue escribiendo, la búsqueda anterior se cancela. Los resultados
  son los mismos que antes.
- **1.5** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias; lo que
  se escribe se busca en las columnas de la liquidación (y en autorización, clave de
  acceso, observaciones y productos), salvo Correo y Estado. Los filtros pasan a una
  **ventana propia** (botón del embudo, se aplican con *Aplicar*) con dos pestañas:
  **Liquidación** (criterios nuevos: estado de pago, estado de correo, autorización,
  sustento, con/sin asiento y retención, subtotal, descuento, saldo pendiente, valor
  retenido, usuario y observaciones) y **Detalles**, búsqueda libre dentro de los
  productos, formas de pago e información adicional. Los filtros activos se ven como
  etiquetas dentro del cuadro y la tabla se atenúa mientras busca.
- **1.4** — Corregido el **PDF** de las liquidaciones con muchas líneas. Cuando el detalle
  no cabía en una página, cada línea siguiente abría una página nueva casi vacía (41
  líneas daban 5 páginas; 80, 44), y a veces quedaba una página en blanco. Ahora el
  detalle sigue en la página siguiente con el encabezado de la tabla repetido, y el pie
  —totales, información adicional, observaciones y forma de pago— ya no se parte: si no
  cabe entero pasa completo a la página siguiente, y si cabe ya no salta sin necesidad.
  Además, una descripción o un código largos ya no se montan sobre la línea siguiente
  (pasaba con textos en mayúsculas).
- **1.3** — El módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar una liquidación cuyo período esté cerrado. Antes no se
  comprobaba en ninguna de las cuatro operaciones.
- **1.2** — Se puede eliminar una liquidación en estado borrador desde el formulario (botón Eliminar). Anula su asiento y sus pagos vinculados; bloqueada si tiene retención asociada.
- **1.1** — Nuevo botón Excel en el documento de la liquidación (junto a PDF y XML).
- **1.0** — Versión inicial.
