---
titulo: Facturación de Consignaciones
resumen: Factura la mercadería consignada que el cliente sí vendió y genera la factura de venta.
categoria: Ventas
ruta_modulo: modulos/facturacion-cv
tipo: modulo
visibilidad: todos
etiquetas: facturacion de consignacion, buscar facturacion, buscador, filtros, filtrar facturaciones, buscar por producto, buscar por lote, buscar por consignacion, chips, facturar consignacion, consignacion vendida, liquidacion de consignacion, cobrar consignacion, descuento en consignacion, descuento por linea, descuento porcentaje, aplicar descuento a todos, precio de lista en consignacion, generar factura, borrador, saldo facturable, observaciones en la factura, informacion adicional, info adicional, cajero, vendedor en la factura
version: 1.9
orden: 47
estado: activo
---

Cuando el cliente **sí vende** la mercadería que se le dejó en consignación, hay
que cobrársela. Este módulo arma ese cobro: se eligen las líneas vendidas de una
o varias consignaciones, se fijan precio y descuento, y al confirmarlo el sistema
emite la **factura de venta** correspondiente. Es la contraparte de
[Retornos de consignación](modulos/retornos-cv), que devuelve lo que **no** se
vendió.

## Qué es y para qué sirve

Es un documento propio, con su **fecha, serie y secuencial** independientes de la
factura de venta. Sirve para:

- Juntar en un solo cobro líneas de **varias consignaciones**, incluso de
  clientes distintos.
- Facturar a un **cliente distinto** del que recibió la consignación.
- Facturar **solo una parte** de lo consignado (el resto queda con saldo).
- Ajustar el **precio** y aplicar **descuentos** antes de emitir la factura.

El saldo facturable de cada línea es
`consignado − retornado − facturado − entregado a cambio`, y solo descuenta
documentos ya **facturados**: un borrador no reserva saldo. Lo *entregado a
cambio* es lo que el cliente se quedó como reposición en un
[Cambio de productos](modulos/cambio-producto-cv): ya es suyo y no se factura
por aquí.

## Requisitos previos

- Consignaciones en estado **Entregada** con saldo pendiente de facturar.
- Un **secuencial propio** configurado por punto de emisión, del tipo
  *Facturación consignaciones ventas* (Empresa → Secuenciales). Sin él no
  aparecen series en el modal y no se puede guardar.
- El secuencial de **Facturas de venta**, porque el segundo paso emite una
  factura normal.
- Permiso sobre el submódulo en `/config/permisos-modulos`.

## Cómo se usa

El listado muestra **Fecha, Secuencial, Cliente, Factura, Observaciones y
Estado**. Todas esas columnas se pueden ordenar, ocultar y redimensionar por
usuario, y el buscador filtra el listado (ver *Buscar y filtrar el listado*).
Al hacer clic en una fila se abre el documento.

El flujo tiene **dos pasos** a propósito: primero se arma y revisa el documento,
y solo cuando está correcto se emite la factura.

1. **Nueva** abre el modal. Se elige fecha y serie; el secuencial lo asigna el
   servidor al guardar.
2. **Cargar consignación**: se busca por número de consignación o por cliente y
   se marcan las líneas a facturar. En esa misma pantalla se define, por línea,
   el **precio** (el de la consignación o uno de la lista de precios), la
   **cantidad** (nunca mayor al saldo) y el **descuento**.
3. **Agregar seleccionados** lleva las líneas a la tabla del documento. Si aún no
   hay cliente, se toma el de la consignación junto con su vendedor, días de
   crédito, forma de pago y correo.
4. Se completan Info. Adicional, Forma de pago SRI y Crédito en el pie, igual que en una factura de venta. En *Info. Adicional* aparecen además, con un candado, las líneas que el sistema mantiene solo (correo del cliente, observaciones, vendedor y cajero): no se editan ahí, se cambian en su propio campo, y son las que viajarán a la factura.
5. **Guardar** deja el documento en **Borrador**: todavía no toca inventario ni
   emite nada, y se puede seguir editando.
6. **Generar factura** reingresa la mercadería al inventario y emite la
   **factura de venta**. El documento pasa a **Facturada** y queda ligado a esa
   factura (el número se ve en la barra superior del modal).
7. Si el usuario tiene permiso de ver **Facturas de Venta**, al terminar el
   sistema pregunta si desea **ir a ese módulo**: al aceptar, se abre el listado
   de facturas ya filtrado por el número recién generado. Si no tiene ese
   permiso, solo se muestra la confirmación y se queda en Facturación de
   Consignaciones.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del documento: fecha,
número (serie y secuencial), cliente, RUC o cédula, factura de venta y
observaciones. Además busca en el vendedor, el total, el usuario que lo
registró, la información adicional, los **productos facturados** (código,
nombre, lote y NUP) y el número de las **consignaciones de origen**. La columna
**Estado** no entra en la búsqueda libre: para filtrar por ella use la ventana
de filtros. Puede escribir varias palabras en cualquier orden y no importan
mayúsculas ni tildes. Mientras busca, aparece un **círculo girando** al final
del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Facturación** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, facturada, anulada), serie, Nº documento, secuencial, factura de venta, consignación de origen, con o sin asiento de reingreso, vendedor, usuario que registró |
| Valores | Total, subtotal e IVA (cada uno con mínimo y máximo) |
| Cliente | Cliente, RUC / cédula, observaciones |

Los selectores *Serie*, *Vendedor* y *Usuario que registró* listan solo lo que
la empresa ya usó en estos documentos.

**Pestaña Detalles** (lo que hay dentro del documento). Es un único cuadro,
**Buscar libremente dentro de las facturaciones**: escriba un producto, un
código, un lote, un NUP, una bodega, el número de una consignación de origen o
un dato de la información adicional (por ejemplo un correo), y aparece la lista
de **cada coincidencia** con el documento al que pertenece (número, fecha,
cliente y estado). Un clic en la fila deja el listado mostrando solo ese
documento; el ícono de la derecha lo abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. El PDF y el Excel del listado salen con los
mismos filtros.

## Descuentos

El descuento funciona igual que en [Facturas de Venta](modulos/factura-venta):

- **Columna Desc. editable** en la tabla del documento. Se escribe el valor en
  dólares de esa línea y el subtotal y los totales se recalculan al instante. No
  hace falta quitar la línea y volver a cargarla para corregir un descuento.
- **Botón `+` de descuento rápido**, al lado del campo. Abre una ventanita con:
  - **Modo**: *Porcentaje (%)* o *Valor ($)*.
  - **Ingreso**: lo que se teclea, y al lado el **Calculado ($)** que resultará.
  - **Aplicar a todos los ítems**: repite el mismo criterio en todas las líneas
    (si es porcentaje, cada línea calcula el suyo sobre su propio subtotal).
- El mismo botón está disponible en el sub-modal **Cargar consignación**, para
  dejar el descuento puesto desde el momento de agregar las líneas.
- El descuento **nunca puede superar el subtotal** de su línea (precio ×
  cantidad): si se excede, el sistema lo recorta a ese tope. El IVA se calcula
  siempre sobre la base ya descontada.
- Si en **Empresa** está apagado *«¿Se puede editar el descuento en un producto o
  servicio en la factura?»*, el campo y el botón no aparecen y el descuento se
  muestra solo como dato, exactamente igual que en la factura de venta.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fecha | Sí | Fecha de emisión del documento. |
| Serie | Sí | Punto de emisión con secuencial de facturación de consignaciones. |
| Secuencial | — | Lo asigna el servidor al guardar; no se teclea. |
| Vendedor | No | Se precarga con el vendedor del cliente. |
| Observaciones | No | Nota del documento. **Sale en la factura** como una línea de información adicional con el concepto *Observaciones*. |
| Cliente a facturar | Sí | A quién se le emite. Puede ser distinto del cliente de la consignación. |
| Precio | Sí | Precio de la consignación o uno de la lista de precios del producto. |
| Cant. | Sí | Nunca mayor al saldo facturable de esa línea. |
| Desc. | No | Descuento en dólares de la línea; tope = precio × cantidad. |
| Info. Adicional | No | Pares concepto/detalle que viajan a la factura. Las filas con candado (correo del cliente, observaciones, vendedor, cajero) las completa el sistema. |
| Forma de pago SRI | No | Una o varias formas con su valor. Si no se indica ninguna, se emite una sola por el total. |
| Días de crédito / Plazo | No | Se precargan con el plazo del cliente. |

## Permisos

- **Ver** muestra el listado y permite abrir los documentos.
- **Crear** habilita *Nueva* y *Crear nueva desde esta* (duplicar).
- **Actualizar** permite editar un borrador y **Generar factura**. Para guardar
  el cambio de un documento existente basta este permiso: no hace falta tener
  además *Crear*.
- **Eliminar** permite borrar un borrador (nunca un documento ya facturado).
- Sin **acceso total**, el usuario ve y edita solo los documentos que él creó;
  con acceso total, los de toda la empresa. El superadministrador ve todo.

## Reglas de negocio

- Solo se puede editar o eliminar un documento en **Borrador**.
- Al guardar y al generar la factura se **revalida el saldo** de cada línea: si
  entretanto otro documento consumió ese saldo, el sistema avisa y no deja
  continuar.
- El descuento se valida también en el servidor: si supera el subtotal de la
  línea, el documento se rechaza con el nombre del producto.
- La base imponible de cada línea es `precio × cantidad − descuento`, redondeada
  a centavos **antes** de calcular el IVA, para que el total del modal coincida
  al centavo con la factura emitida.
- Al generar la factura, el sistema completa su **información adicional** con lo que el documento ya tiene, sin teclear nada:
  - **Consignación**: el número de cada consignación facturada, solo el secuencial, sin la serie y sin los ceros de relleno (`001-001-000000012` se escribe `12`), separados por coma si son varias.
  - **Observaciones**: lo escrito en el campo *Observaciones* del documento.
  - **Vendedor** y **Cajero**: el vendedor del documento y el usuario que genera la factura, siempre que la empresa los tenga activados en su ficha (*¿Mostrar el cajero / el vendedor en la factura?*).
  - **Correo del cliente** y **RUC Proveedor**: los agrega la factura de venta, igual que en cualquier otra factura.
- Si el documento ya trae una línea de información adicional escrita a mano con uno de esos conceptos, manda la suya: el sistema no la duplica ni la pisa. Todas estas líneas salen en el **RIDE** y viajan en el **XML** autorizado.
- **Crear nueva desde esta** (duplicar) solo aparece en documentos *facturada* o
  *anulada*. Recorta cada cantidad al saldo vigente y **escala el descuento en la
  misma proporción**; las líneas sin saldo se omiten.
- Estados: **Borrador** → **Facturada** → **Anulada**.

## Integraciones con otros módulos

- **Inventario**: al generar la factura, la mercadería consignada **reingresa** a
  su bodega de origen (movimiento `FACTURACION_CV`) y acto seguido sale como
  venta normal por la factura.
- **Contabilidad**: se registra el asiento de reversa de la consignación (Debe
  *Inventario* / Haber *Mercadería en consignación*, a costo). La pestaña
  **Asiento contable** del modal lo muestra a quien tenga acceso a
  Contabilidad → Asientos Contables.
- **Facturas de Venta**: el documento genera una factura de venta normal, con su
  propia numeración, que sigue el circuito habitual de firma y envío al SRI.
- **Consignaciones de venta**: la pestaña *Facturación* del modal de la
  consignación muestra estos documentos como historial de solo lectura.
- Si la factura de venta se **anula o elimina**, el sistema deshace el reingreso,
  anula el asiento, deja este documento en **Anulada** y libera el saldo.

## Errores frecuentes

- **No aparecen series en el modal**: falta configurar el secuencial *Facturación
  consignaciones ventas* en el punto de emisión.
- **«No puede facturar X de "Producto": el saldo facturable es Y»**: otro
  documento consumió el saldo mientras el borrador estaba abierto. Vuelva a
  cargar la consignación y ajuste la cantidad.
- **«El descuento de "Producto" no puede superar el subtotal»**: el descuento
  quedó por encima de precio × cantidad, normalmente tras bajar la cantidad
  después de fijarlo. Corrija el valor en la columna *Desc.*
- **No se ve la columna Desc. editable**: la empresa tiene apagada la opción
  *Editar descuento en factura*, o el documento ya no está en borrador.
- **Sin saldo facturable** al buscar una consignación: ya se facturó o se retornó
  toda su mercadería.
- **A un documento migrado del sistema anterior le faltan ítems**: ocurría con
  documentos que repiten el mismo producto en varias líneas (una por número de
  serie / NUP). Los ítems siempre estuvieron guardados; la pantalla los agrupaba
  por línea de consignación y mostraba solo el primero de cada grupo. Ya está
  corregido: basta recargar la página. Si además la consignación de origen
  aparece con saldo pendiente de mercadería que sí se facturó, hay que volver a
  ejecutar la migración de *Facturación de consignaciones* (y de *Retornos*),
  que repara el enlace de esas líneas.

## Historial de cambios

- **1.9** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas del documento y además en el
  vendedor, el usuario, la información adicional, los productos facturados
  (código, nombre, lote, NUP) y las consignaciones de origen, salvo la columna
  Estado. Los filtros pasan a una **ventana propia** (botón del embudo, se
  aplican con *Aplicar*) con dos pestañas: **Facturación** (filtros por campo,
  con criterios nuevos: Nº documento, consignación de origen, con/sin asiento de
  reingreso, vendedor y usuario como listas, subtotal, IVA y RUC) y
  **Detalles**, un cuadro de **búsqueda libre dentro de las facturaciones** que
  dice a qué documento pertenece cada coincidencia. Los filtros activos se ven
  como etiquetas dentro del cuadro. Nueva sección *Buscar y filtrar el listado*.
- **1.8** — Al **generar la factura**, si el usuario puede ver *Facturas de
  Venta*, se le pregunta si desea ir a ese módulo; al aceptar se abre el
  listado filtrado por la factura recién emitida. Sin ese permiso no se ofrece.
- **1.7** — El saldo facturable descuenta también lo entregado **a cambio**
  desde la consignación (módulo Cambios de productos), en el buscador, en el
  sub-modal *Cargar consignación* y al revalidar un borrador.
- **1.6** — La factura generada ya lleva en su información adicional las **Observaciones** del documento y, según la configuración de la empresa, el **Vendedor** y el **Cajero**. Antes las observaciones solo se veían en el PDF del sistema y no viajaban en el comprobante.

- **1.5** — El PDF de una facturación con muchos productos ya no sale troceado.
  A partir de unas 20 líneas el documento se partía en decenas de hojas con un
  solo dato cada una (40 productos llegaban a producir 126 páginas) y los
  totales, las observaciones y la información adicional quedaban sueltos en
  hojas aparte. Ahora el listado continúa de forma normal en las páginas
  siguientes, repitiendo los encabezados de columna, y esos bloques se dibujan
  completos. Además, las descripciones largas ya no se recortan y un lote o
  código más ancho que su columna se ajusta dentro de la celda en vez de
  montarse sobre la siguiente.

- **1.4** — El permiso **Actualizar** ya sirve por sí solo: para guardar el
  cambio de un documento existente también se exigía *Crear*, así que quien solo
  podía corregir recibía *«No tiene permiso para esta acción»*.

- **1.3** — Los documentos que repiten un producto en varias líneas (una por NUP)
  ya muestran todos sus ítems; antes se agrupaban por línea de consignación.
- **1.2** — El listado muestra **Observaciones** en lugar de *Total*.
- **1.1** — La línea *Consignación* de la información adicional de la factura
  ahora lleva solo el número de la consignación: sin la serie y sin los ceros de
  relleno.
- **1.0** — Versión inicial. Documenta el flujo de dos pasos y el descuento por
  línea editable con descuento rápido (% o $, con opción de aplicarlo a todos los
  ítems), igual que en Facturas de Venta.
