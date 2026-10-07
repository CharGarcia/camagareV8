---
titulo: App móvil CaMaGaRe (iPhone y Android)
resumen: Cómo funcionan los listados, las facturas de venta y el registro de entregas en la app del celular, y qué configuración del sistema respeta.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: total no cuadra en la app, iva en la app, se duplico la factura, factura repetida, doble toque, permisos de la app, proformas en la app, cotizacion desde el celular, cotizar, enviar proforma, aprobar proforma, convertir proforma, app movil, app móvil, aplicacion, celular, telefono, iphone, android, ios, app store, play store, listado, solo veo 20, solo salen 20, ver mas filas, cargar mas, mas registros, factura desde el celular, editar precio en la app, descuento en la app, entregas en la app, observacion de entrega, comentario de entrega, repartidor
version: 1.2
orden: 40
estado: activo
---

La app móvil de CaMaGaRe (iPhone y Android) trabaja con los mismos datos que
el sistema web: lo que se registra en el celular aparece en la web y al revés.
Esta guía explica lo que funciona distinto o merece aclararse en el celular.

## Qué es y para qué sirve

Permite consultar y registrar desde el celular clientes, proveedores,
productos, compras, facturas de venta, proformas, pedidos, órdenes de servicio
y entregas de consignaciones, con los mismos permisos que el usuario tiene en la web.

## Requisitos previos

- El usuario debe tener habilitado el acceso a la app móvil y permisos sobre
  cada módulo (`/config/permisos-modulos`).
- En **iPhone/iPad** la empresa debe tener un equipo de trabajo (al menos dos
  usuarios activos asignados). Si no, la app muestra *"La app para iPhone/iPad
  está disponible para equipos de trabajo"*; esa empresa puede usar el sistema
  desde la web o desde Android.

## Cómo se usa

### Listados: ver más filas

Cada listado (clientes, proveedores, productos, compras, facturas, proformas,
pedidos, órdenes de servicio y entregas) muestra primero los 20 registros más recientes.
Al **deslizar hacia abajo** y llegar al final, la app carga los 20 siguientes
automáticamente, y así hasta el último. Al pie de la lista se ve cuántos se
muestran de cuántos hay (p. ej. *Mostrando 40 de 135*). El buscador filtra sobre
todos los registros, no solo sobre los que ya se cargaron. Para recargar desde
el principio, deslice hacia abajo estando arriba de la lista.

### Facturas de venta: precio y descuento

En cada producto de la factura se puede cambiar la **cantidad**, el **precio
(sin IVA)** y el **descuento en dólares** de la línea, igual que en la web. La
app respeta la configuración del establecimiento de la factura (Empresa →
pestaña **Facturación**):

- **¿Se puede editar el precio de un producto o servicio en la factura?**:
  apagado, el precio aparece bloqueado y se usa el del catálogo.
- **¿Se puede editar el descuento en un producto o servicio en la factura?**:
  apagado, el campo *Descuento* no aparece.

Cada línea muestra su subtotal (ya con el descuento) y su total con IVA, y los
totales de la factura incluyen una fila *Descuento* cuando hay alguno.

### Proformas

La app trabaja con las mismas proformas que la web ([Proformas](../modulos/proformas.md)):

1. **Listado**: número, cliente, total, estado y si ya se envió por correo.
   Pulse **+ Nueva** para crear una.
2. **Crear / editar** (solo en *Borrador*): serie, fecha, cliente, vendedor,
   días de vigencia, observaciones y productos. En cada producto se cambian
   **cantidad, precio (sin IVA) y descuento en dólares** libremente, igual que
   en la web (las proformas no usan los interruptores de Facturación). El
   número definitivo lo asigna el sistema al guardar.
3. **Acciones** desde el detalle, según el estado:
   - *Borrador*: **Editar**, **Aprobar**, **Anular**.
   - *Aprobada*: **Convertir a factura** (crea la factura en borrador),
     **Reabrir** (solo administradores), **Rechazada por el cliente**, **Anular**.
   - **Enviar a pedidos** (borrador, aprobada o convertida), **Compartir PDF**,
     **Enviar por correo** (con la opción de adjuntar la ficha de productos; si
     está en borrador, el correo trae el botón para que el cliente la apruebe) y
     **Duplicar**.

Al editar desde la app se conservan las condiciones, la información adicional
y las líneas de texto libre que se cargaron en la web.

### Entregas de consignaciones

Al registrar una entrega se captura la ubicación GPS y se puede escribir una
**observación opcional** (hasta 500 caracteres, p. ej. quién recibió). La app ya
no pide la firma de quien recibe. Ver
[Entregas de Consignaciones](../modulos/entregas-consignaciones.md).

## Totales e IVA en la app

Los totales que se ven en facturas y proformas antes de guardar se calculan
con la **configuración de facturación de la serie** elegida, igual que en la
web: el modo del IVA (al subtotal o línea por línea) y los decimales de precio
y cantidad. Lo que muestra la pantalla coincide con lo que se guarda y con el
PDF. Ver [Cálculo del IVA](../conceptos/calculo-iva.md).

## Guardar una sola vez

Al pulsar **Guardar** el botón queda bloqueado hasta que termina, así que un
doble toque no crea dos documentos. Si la conexión falla justo al guardar y el
usuario vuelve a intentar, el sistema reconoce el intento: si el documento ya
se había guardado, avisa *"ya estaba registrado; no se creó otro"* y lo abre.
Aplica a facturas, proformas, pedidos y al **cobro de facturas** (un cobro
repetido por un reintento no se registra dos veces).

## Permisos del celular

La app pide solo **cámara** (código QR y selfie de asistencia, foto de
productos), **fotos** (imagen de un producto) y **ubicación** mientras se usa
la app (al confirmar una entrega y al marcar asistencia). No usa el micrófono
ni Face ID.

## Reglas de negocio

- El servidor vuelve a validar todo al guardar: si el establecimiento no
  permite editar el precio o el descuento, la factura se guarda con el precio
  del catálogo y sin descuento aunque el celular los envíe.
- El descuento de una línea no puede ser negativo ni mayor que cantidad ×
  precio; el precio no puede ser negativo.
- Los cambios de la app llegan con cada versión nueva publicada en la App Store
  y Google Play: hay que tener la app actualizada para verlos.

## Integraciones con otros módulos

- **Empresa → Facturación**: de ahí salen los permisos de editar precio y
  descuento, por establecimiento.
- **Facturas de Venta**: las facturas creadas o editadas en la app se ven y se
  gestionan igual en la web, con el mismo precio, descuento y totales.

## Errores frecuentes

- **"No hay series con secuencial de Proformas configurado"**: la empresa no
  tiene un punto de emisión con secuencial de Proformas (Empresa → Secuenciales).
- **"Stock insuficiente" al convertir a factura**: la empresa exige stock y no
  hay saldo; la app lista los productos que faltan.
- **"No se puede enviar a pedidos"**: la proforma tiene líneas de texto libre;
  el pedido solo admite productos del catálogo.

- **Solo veo 20 registros**: deslice hasta el final de la lista para cargar
  más. Si la app no carga más, actualícela desde la tienda: las versiones
  anteriores mostraban solo los 20 más recientes.
- **No puedo cambiar el precio o no aparece el descuento**: el establecimiento
  lo tiene apagado en Empresa → Facturación.
- **"El descuento de … debe estar entre $0 y $…"**: el descuento supera el
  valor de la línea (cantidad × precio).

## Historial de cambios

- **1.2** — Totales de facturas y proformas según la configuración de
  facturación de la serie (modo del IVA y decimales); un doble toque o un
  reintento ya no crea documentos ni cobros repetidos; la app deja de pedir micrófono y
  Face ID y los avisos de permisos están en español.
- **1.1** — Proformas en la app: listado, crear y editar borradores, aprobar,
  anular, rechazar y reabrir, PDF, envío por correo con aprobación del cliente,
  duplicar y convertir a factura o pedido.
- **1.0** — Versión inicial: listados con carga de más filas al deslizar,
  precio y descuento editables en facturas según la configuración del
  establecimiento, y observación en las entregas (sin firma).
