---
titulo: Facturación de Consignaciones
resumen: Factura la mercadería consignada que el cliente sí vendió y genera la factura de venta.
categoria: Ventas
ruta_modulo: modulos/facturacion-cv
tipo: modulo
visibilidad: todos
etiquetas: facturacion de consignacion, facturar consignacion, consignacion vendida, liquidacion de consignacion, cobrar consignacion, descuento en consignacion, descuento por linea, descuento porcentaje, aplicar descuento a todos, precio de lista en consignacion, generar factura, borrador, saldo facturable
version: 1.5
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

El saldo facturable de cada línea es `consignado − retornado − facturado`, y solo
descuenta documentos ya **facturados**: un borrador no reserva saldo.

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
usuario, y el buscador filtra por cada una de ellas (además de por *serie* y
*total*). Al hacer clic en una fila se abre el documento.

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
4. Se completan Info. Adicional, Forma de pago SRI y Crédito en el pie, igual que
   en una factura de venta.
5. **Guardar** deja el documento en **Borrador**: todavía no toca inventario ni
   emite nada, y se puede seguir editando.
6. **Generar factura** reingresa la mercadería al inventario y emite la
   **factura de venta**. El documento pasa a **Facturada** y queda ligado a esa
   factura (el número se ve en la barra superior del modal).

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
| Observaciones | No | Notas internas; no salen en la factura. |
| Cliente a facturar | Sí | A quién se le emite. Puede ser distinto del cliente de la consignación. |
| Precio | Sí | Precio de la consignación o uno de la lista de precios del producto. |
| Cant. | Sí | Nunca mayor al saldo facturable de esa línea. |
| Desc. | No | Descuento en dólares de la línea; tope = precio × cantidad. |
| Info. Adicional | No | Pares concepto/detalle que viajan a la factura; incluye el correo del cliente. |
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
- Al generar la factura, el sistema añade automáticamente a su **información
  adicional** una línea con el concepto **Consignación** y el número de cada
  consignación facturada: **solo el secuencial, sin la serie y sin los ceros de
  relleno** (`001-001-000000012` se escribe `12`), separados por coma si son
  varias. Esa línea se suma a la información adicional que ya tenga el documento.
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
