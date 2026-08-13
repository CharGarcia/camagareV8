---
titulo: Órdenes de compra
resumen: Pedido formal a un proveedor antes de recibir la mercadería y su factura.
categoria: Compras
ruta_modulo: modulos/ordenes-compra
tipo: modulo
visibilidad: todos
etiquetas: orden de compra, ordenes, pedido a proveedor, requisicion, compra pendiente, autorizar compra, vincular compra, recibido, pedido vs facturado, aprobacion por correo, enviado, aprobar orden
version: 1.6
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
Borrador → [Enviar correo] → Enviado → [proveedor aprueba, o botón Aprobar] → Aprobado → [se vincula con una compra] → Recibido
```

El campo **Estado** del modal solo deja elegir a mano **Borrador** o
**Anulado**; Enviado/Aprobado/Recibido los pone el sistema según la acción
correspondiente — no se pueden forzar desde el formulario (ni tampoco desde
el servidor, aunque se manipule la petición).

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
   — no basta con Enviado. Al vincularla pasa a **Recibido**.

**Desde Enviado en adelante, la orden es de solo lectura**: el modal muestra
un aviso y bloquea todos los campos y el detalle (no se puede editar ni
eliminar; los botones de PDF/Excel/Correo siguen disponibles). Esto también
se valida en el servidor, no solo en la pantalla. Para volver a editar una
orden **Recibida**, hay que **desvincularla** primero desde la compra (eso la
regresa a Aprobado); una orden **Enviada** o **Aprobada** que aún no se
vinculó a ninguna compra no tiene forma de "desbloquearse" — si de verdad
hace falta corregirla, hay que anular esta y crear una nueva.

## Cuando llega la factura del proveedor

Las compras de este sistema son documentos electrónicos que se cargan solos
desde el SRI (módulo **Compras**), no se "convierten" desde la orden. Para
cerrar el ciclo, abra esa compra ya cargada y use su pestaña **Orden de
Compra**:

1. Seleccione, en el buscador, la orden **Aprobada** del mismo proveedor que
   corresponde a esa factura (solo aparecen órdenes aprobadas que no estén ya
   vinculadas a otra compra) y pulse **Vincular**.
2. La orden pasa automáticamente a estado **Recibido** (con la fecha de hoy
   como fecha de recepción si no tenía una).
3. La pestaña muestra una tabla comparativa por producto: cantidad y precio
   **pedidos** (de la orden) vs. **facturados** (de la compra), con un
   estado por línea — *OK*, *Diferencia* (cantidad o precio no coincide),
   *Pendiente* (se pidió pero no llegó en esta factura) o *No pedido*
   (llegó algo que no estaba en la orden). Las líneas que no tienen un
   producto del catálogo vinculado en algún lado (ni en la orden ni por
   homologación del código del proveedor en la compra) se listan aparte, sin
   comparar automáticamente.
4. Si se vinculó por error, el botón **Desvincular** deshace el enlace y
   regresa la orden a **Aprobado**.

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
  la orden esté en estado **Aprobado** (Borrador y Enviado no bastan), que
  sea del mismo proveedor de la compra, y que no esté ya vinculada a otra
  compra.
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
