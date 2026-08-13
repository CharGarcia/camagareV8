---
titulo: Órdenes de compra
resumen: Pedido formal a un proveedor antes de recibir la mercadería y su factura.
categoria: Compras
ruta_modulo: modulos/ordenes-compra
tipo: modulo
visibilidad: todos
etiquetas: orden de compra, ordenes, pedido a proveedor, requisicion, compra pendiente, autorizar compra, vincular compra, recibido, pedido vs facturado, aprobacion por correo, enviado, aprobar orden, entrega parcial, recibido parcial, duplicar orden, cerrar orden
version: 1.8
orden: 15
estado: activo
---

La **orden de compra** es el pedido formal que la empresa hace a un proveedor:
qué se le compra, en qué cantidad y a qué precio, antes de que llegue la
mercadería.

No tiene efecto contable ni tributario: no genera cuentas por pagar ni mueve
inventario. Es un compromiso, no una compra.

## Para qué sirve

- Dejar por escrito lo pedido, para reclamar si llega otra cosa.
- Controlar qué está pedido y aún no ha llegado.
- Vincularla con la compra cuando llegue la factura electrónica del
  proveedor, para comparar cantidades y precios pedidos contra los
  facturados.

## Cómo se usa

1. Pulse **Nuevo**.
2. Elija el **proveedor**.
3. Añada los productos con cantidad y precio acordado. Al buscar un producto
   del catálogo, el precio unitario se precarga con su **precio de costo**
   (no el de venta) — es lo que se le paga al proveedor. Si el producto no
   tiene costo configurado se precarga en 0.00 y se escribe a mano.
4. Guarde. Mientras esté en **Borrador** puede seguir editándola libremente.

## Ciclo de vida y estados

```
Borrador → [Enviar correo] → Enviado → [proveedor aprueba, o botón Aprobar] → Aprobado
                                                                                   │
                                                            [se vincula 1ª compra] │
                                                                                   ▼
                              Recibido parcial ◄──────────────────────────────► Recibido
                        [falta saldo por entregar]      [se cubre / se cierra    [todo lo pedido llegó
                                                          manualmente]             en una o varias compras]
```

El campo **Estado** del modal solo deja elegir a mano **Borrador** o
**Anulado**; Enviado/Aprobado/Recibido parcial/Recibido los pone el sistema
según la acción correspondiente — no se pueden forzar desde el formulario (ni
tampoco desde el servidor, aunque se manipule la petición).

1. **Enviar correo**: con el botón de correo en la parte superior del modal
   se envía el PDF al proveedor (se precarga su correo si lo tiene
   registrado; igual se puede escribir uno distinto). La **primera vez** que
   se envía, la orden pasa a **Enviado** y el correo incluye un botón
   **"Aprobar esta orden de compra"** que el proveedor puede pulsar sin
   necesidad de iniciar sesión. Un reenvío posterior no repite este cambio de
   estado ni añade el botón otra vez.
2. **Aprobación**: puede llegar de dos formas —
   - El **proveedor** la aprueba desde el enlace del correo (ve el detalle de
     productos, cantidades y precios, y confirma con un clic).
   - Alguien del equipo la aprueba **manualmente** con el botón **Aprobar**
     que aparece junto al de correo (solo visible mientras está en Enviado)
     — útil si el proveedor confirmó por teléfono o WhatsApp en vez de por
     el enlace.
   En ambos casos la orden pasa a **Aprobado**.
3. **Vinculación con una compra**: cuando llega la factura electrónica real
   (ver más abajo), solo se puede vincular una orden que ya esté **Aprobada**
   o **Recibida parcial** — no basta con Enviado. Según cuánto cubra esa
   compra del total pedido, la orden pasa a **Recibido parcial** (falta algo)
   o **Recibido** (se completó).

**Desde Enviado en adelante, la orden es de solo lectura**: el modal muestra
un aviso y bloquea todos los campos y el detalle (no se puede editar; los
botones de PDF/Excel/Correo/Duplicar siguen disponibles). Esto también se
valida en el servidor, no solo en la pantalla. Para volver a editar una orden
**Recibida** o **Recibida parcial**, hay que **desvincular** sus compras
primero (ver "Entregas parciales" más abajo); una orden **Enviada** o
**Aprobada** que aún no tiene ninguna compra vinculada no tiene forma de
"desbloquearse" para editarla directamente — para eso está **Duplicar**.

## Entregas parciales

El proveedor no siempre entrega todo el pedido de una sola vez. Una misma
orden se puede vincular con **varias compras** a lo largo del tiempo (una
por cada entrega/factura), y el sistema lleva la cuenta:

- Al vincular una compra, se compara la cantidad facturada **acumulada de
  todas las compras vinculadas a esa orden** contra lo pedido, línea por
  línea (por producto del catálogo). Si falta saldo en alguna línea, la
  orden queda en **Recibido parcial**; si ya se cubrió todo, pasa a
  **Recibido**.
- Cada vez que se abre la pestaña "Orden de Compra" de una de esas compras,
  se muestra la lista de **todas** las compras vinculadas a la orden (no solo
  la que se está viendo), para tener el historial completo de entregas a la
  vista.
- **Cerrar orden** (botón que aparece en esa pestaña cuando la orden está en
  Recibido parcial): úselo cuando el proveedor ya no va a entregar el saldo
  pendiente. Fuerza la orden a **Recibido** aunque falte cantidad, y queda
  marcada como "cierre manual" para diferenciarla de una recibida por
  cantidades completas.
- Al **desvincular** una compra, el estado se recalcula con las que queden:
  puede volver a Aprobado (si no queda ninguna), seguir en Recibido parcial,
  o incluso bajar de Recibido a Recibido parcial si esa compra era la que
  completaba el pedido.
- Las líneas de la orden que **no tienen un producto del catálogo
  vinculado** no se pueden rastrear automáticamente (no hay forma de saber
  cuánto de ellas llegó); en cuanto la orden tiene alguna compra vinculada,
  esas líneas se dan por recibidas sin poder distinguir si fue parcial.

## Duplicar una orden

Una orden Enviada, Aprobada o Recibida parcialmente ya no se puede editar.
Si hace falta corregir algo (precio, cantidad, agregar/quitar un ítem) o
pedir el saldo que el proveedor no entregó, el botón **Duplicar** (barra
superior del modal) crea una **orden nueva en Borrador** para seguir desde
ahí:

- Desde **Enviado** o **Aprobado** (nada recibido todavía): copia todos los
  ítems tal cual. Se pregunta si además se quiere **anular la orden
  original** (casilla marcada por defecto) — recomendable para no dejar dos
  órdenes "vivas" con la misma intención.
- Desde **Recibido parcial**: copia solo el **saldo pendiente** de cada
  línea (lo pedido menos lo ya recibido en las compras vinculadas), no el
  pedido completo — para no duplicar lo que ya llegó. En este caso no se
  ofrece anular la original, porque ya tiene entregas reales asociadas.
- La orden nueva queda totalmente editable: cambie lo que necesite y
  vuelva a enviarla normalmente.

## Eliminar vs. Anular

No son lo mismo, y cuál está disponible depende del estado:

- **Eliminar** (barra superior del modal): solo aparece y solo funciona con
  la orden en **Borrador**. Es lo único que realmente saca el registro de la
  lista (baja lógica, como el resto del sistema).
- **Anular** (barra superior, junto a Enviar correo/Aprobar/Duplicar):
  aparece cuando la orden ya está **Enviada** o **Aprobada** (sin ninguna
  compra vinculada todavía) — en esos estados ya no se puede eliminar, solo
  anular. El registro se conserva con estado **Anulado** y queda de solo
  lectura, igual que el resto de estados avanzados.
- Una orden **Recibida** o **Recibida parcial** no tiene ni Eliminar ni
  Anular disponibles: hay que desvincular sus compras primero (o cerrarla
  manualmente), y desde Aprobado sí se puede anular si hace falta.
- Una orden ya **Anulada** no tiene más acciones sobre su estado.

## Cuando llega la factura del proveedor

Las compras de este sistema son documentos electrónicos que se cargan solos
desde el SRI (módulo **Compras**), no se "convierten" desde la orden. Para
cerrar el ciclo, abra esa compra ya cargada y use su pestaña **Orden de
Compra**:

1. Seleccione, en el buscador, la orden **Aprobada** o **Recibida parcial**
   del mismo proveedor que corresponde a esa factura y pulse **Vincular**.
   Una misma orden admite varias compras vinculadas a lo largo del tiempo
   (entregas parciales) — ver "Entregas parciales" arriba.
2. Según cuánto cubra esta compra del total pedido, la orden pasa a
   **Recibido parcial** (aún falta algo) o **Recibido** (se completó), con la
   fecha de hoy como fecha de recepción si no tenía una.
3. La pestaña muestra una tabla comparativa por producto: cantidad y precio
   **pedidos** (de la orden) vs. **facturados** (acumulado de todas las
   compras vinculadas a la orden, no solo esta), con un estado por línea —
   *OK*, *Diferencia* (cantidad o precio no coincide), *Pendiente* (se pidió
   pero no llegó en ninguna factura) o *No pedido* (llegó algo que no estaba
   en la orden). Las líneas que no tienen un producto del catálogo vinculado
   en algún lado (ni en la orden ni por homologación del código del
   proveedor en la compra) se listan aparte, sin comparar automáticamente.
4. Si se vinculó por error, el botón **Desvincular esta compra** deshace ese
   enlace y recalcula el estado de la orden con las compras que le queden.

Esto es solo informativo: no bloquea guardar la compra, no mueve inventario
ni genera cuentas por pagar por sí mismo — eso sigue el flujo normal de
Compras (procesar entradas, retención, etc.).

## Errores frecuentes

- **El stock no cambió al crear la orden**: es correcto, la orden no mueve
  inventario. Eso ocurre al procesar las entradas de la compra.
- **No aparece en cuentas por pagar**: tampoco genera deuda; la deuda nace con la
  compra.
- **El proveedor no aparece**: regístrelo primero en Proveedores.
- **No aparece en el buscador de la pestaña "Orden de Compra"**: revise que
  la orden esté en estado **Aprobado** o **Recibido parcial** (Borrador y
  Enviado no bastan), y que sea del mismo proveedor de la compra. Si ya está
  en **Recibido** (completo), no debería vincularse una compra más.
- **La orden pasó a Recibido con una compra pero le faltaba mercadería**:
  revise que las líneas de la orden estén vinculadas a un producto del
  catálogo — sin esa vinculación no se puede rastrear la cantidad recibida,
  y la orden se da por recibida completa en cuanto tiene alguna compra
  vinculada (ver "Entregas parciales").
- **"El número de secuencial ya existe para este punto de emisión"**: dos
  guardados casi simultáneos compitieron por el mismo número. Recargue la
  página e intente de nuevo; el sistema le asignará el siguiente número
  disponible.
- **El proveedor dice que el enlace del correo no funciona / ya expiró**:
  cada envío inicial genera un enlace propio de esa orden; si el correo se
  perdió o el enlace da error, reenvíe el correo desde el modal (el botón de
  correo sigue funcionando aunque ya esté Enviada) o apruébela manualmente
  con el botón **Aprobar**.
- **El botón Aprobar no aparece**: solo se muestra mientras la orden está en
  estado **Enviado**. Si ya está Aprobada o Recibida, no hace falta (ya está
  aprobada); si sigue en Borrador, primero hay que enviarla por correo.

## Historial de cambios

- **1.8** — Entregas parciales: una orden ahora admite varias compras
  vinculadas (nuevo estado **Recibido parcial**, cierre manual "Cerrar
  orden" cuando el proveedor no entrega el saldo). Botón **Duplicar** para
  corregir/reenviar una orden que ya no se puede editar, copiando el saldo
  pendiente cuando aplica.
- **1.7** — Eliminar ahora solo funciona en Borrador; una orden Enviada o
  Aprobada se **Anula** en su lugar (nuevo botón junto a Eliminar), sin
  borrar el registro. Una orden Anulada también queda de solo lectura.
- **1.6** — Nuevo estado **Enviado**: se activa automáticamente al enviar el
  correo por primera vez y deja la orden de solo lectura desde ahí. El
  correo incluye un enlace para que el proveedor apruebe sin iniciar sesión;
  también hay un botón para aprobarla manualmente desde el sistema. Vincular
  con una compra ahora requiere que la orden esté **Aprobada** (ya no basta
  con Borrador).
- **1.5** — El buscador de productos del detalle precarga el precio unitario
  con el precio de costo del producto, no el de venta.
- **1.4** — Una orden en estado Recibido es de solo lectura (no se puede
  editar ni eliminar hasta desvincularla), validado en el modal y en el
  servidor.
- **1.3** — Mismo control anti-duplicado de secuencial que Factura de Venta:
  chequeo antes de guardar + índice único en la base de datos, para que dos
  guardados a la vez no puedan generar el mismo número de orden.
- **1.2** — Vinculación con Compras: pestaña "Orden de Compra" en el modal de
  Compras para enlazar la factura electrónica del proveedor con la orden que
  la originó y comparar cantidades/precios pedidos vs. facturados.
- **1.1** — Botones de PDF, Excel y Correo en el modal para descargar o enviar
  la orden guardada directamente al proveedor.
- **1.0** — Versión inicial.
