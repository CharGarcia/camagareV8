---
titulo: Facturas de Venta
resumen: Emisión de facturas electrónicas: creación, envío al SRI, PDF, correo y anulación.
categoria: Ventas
ruta_modulo: modulos/factura-venta
tipo: modulo
visibilidad: todos
etiquetas: factura, facturar, venta, sri, comprobante electronico, xml, excel, anular, nota de credito, whatsapp, link de pago, payphone, nuvei, serie vacia, sin puntos de emision, secuencial repetido, secuencial ya existe, punto de emision, ambiente, pruebas, produccion, cambio de ambiente, clave de acceso en procesamiento, error 70, comprobante devuelto, reintento automatico
version: 1.7
orden: 20
estado: activo
---

El módulo de **Facturas de Venta** emite los comprobantes electrónicos de venta y
gestiona su ciclo completo: creación, autorización en el SRI, entrega al cliente
y anulación.

## Antes de empezar

Para emitir facturas electrónicas la empresa necesita tener configurado:

- Los datos tributarios de la empresa y su establecimiento.
- La **firma electrónica** vigente.
- El **secuencial** del documento y el ambiente (pruebas o producción).
- El cliente y los productos o servicios que va a facturar.

## Crear una factura

1. Pulse **Nuevo**.
2. Elija el cliente. Si no existe, regístrelo primero en el módulo de Clientes.
3. Agregue las líneas de detalle: producto, cantidad, precio y descuento.
4. Revise los totales y el IVA calculado.
5. Guarde la factura.

Una factura guardada queda en borrador hasta que se envía al SRI.

## Barra de acciones del documento

En la parte superior del formulario están las acciones sobre el documento ya
guardado: generar el **PDF**, ver el **XML**, descargar un **Excel** con el
detalle y los totales, enviarlo por **correo** o por **WhatsApp** y remitirlo
al **SRI**. Cada acción comprueba primero que la factura esté guardada.

## Enviar la factura y el enlace de pago por WhatsApp

El botón de WhatsApp pide la plantilla aprobada y el número del cliente. Con las
plantillas de factura, el mensaje sale con el **PDF adjunto**.

Además hay dos plantillas especiales que, en lugar del PDF, envían un **enlace de
pago con tarjeta**: `link_pago_payphone` y `link_pago_nuvei`, una por cada
pasarela. Al elegirlas, el sistema muestra el **saldo pendiente** de la factura y
genera el enlace por ese valor:

- Solo se ofrecen si la factura está **autorizada** y tiene saldo pendiente.
- El enlace siempre es por el **total pendiente**: por esta vía no se cobra
  parcialmente.
- No se envía un segundo enlace si ya hay uno pendiente de los últimos **15
  minutos**.
- Requiere una **forma de cobro** de ese tipo (Payphone o Nuvei) activa y
  configurada en la empresa.
- El enlace de Payphone es de un solo uso y caduca a los pocos minutos; el de
  Nuvei se renueva cada vez que el cliente lo abre y deja de servir cuando el
  pago se registra.

Cuando el cliente paga, el cobro aparece en la pestaña **Pagos** de la factura,
igual que si el enlace se hubiera enviado por correo. Las plantillas se crean en
el módulo de Plantillas de WhatsApp.

## Envío al SRI

Al enviar, el sistema firma el XML y lo transmite al Servicio de Rentas Internas.
El comprobante puede quedar autorizado o devuelto con observaciones. Si es
devuelto, corrija lo que indique el mensaje y vuelva a enviar.

Cuando hay muchos documentos pendientes conviene usar el envío en lote, que los
procesa en segundo plano.

## Anular una factura

Una factura **autorizada** no se elimina: se anula, y esa anulación se informa al
SRI. Si lo que se necesita es corregir valores o devolver mercadería, el
documento correcto es una **nota de crédito**, no la anulación.

Anular una factura revierte también los movimientos asociados (inventario, cobro
y asiento contable) según la configuración de la empresa.

## Errores frecuentes

- **Firma caducada**: renueve el certificado y vuelva a cargarlo en la empresa.
- **Secuencial repetido** (*"El número de secuencial ya existe para este punto
  de emisión"*): revise el secuencial configurado para el establecimiento y
  punto de emisión. Si el local **pasó de Pruebas a Producción** (o al revés) y
  el mensaje aparece con un número que en el ambiente activo está libre, es el
  caso corregido en la versión 1.6: la numeración de Pruebas y la de Producción
  son independientes, y antes el sistema las comparaba entre sí.
- **El selector de Serie aparece vacío** (no ofrece ningún punto de emisión):
  la empresa tiene más de un establecimiento y el que está realmente
  **Activo** no era el que se usaba para armar el combo (corregido; si
  persiste, confirme en el módulo Empresa → Establecimientos cuál es el
  Activo y que tenga un punto de emisión activo con "Facturas de venta"
  configurada en Secuenciales).
- **"Clave de acceso en procesamiento"**: no es un rechazo. El SRI ya tiene la
  factura en cola de un envío anterior y todavía no publica el resultado.
  Reenviarla no sirve de nada: devolvería el mismo mensaje. El sistema ahora la
  reconoce y pasa directo a consultar la autorización, y si el SRI aún no
  responde la deja en seguimiento para que el reintento automático la resuelva.
  Detalle en la guía *"Clave de acceso en procesamiento": qué significa y qué
  hacer* (`guias/clave-de-acceso-en-procesamiento`).
- **El cliente no recibe el correo**: verifique la dirección registrada en su ficha.

## Normativa SRI 2026: RUC del proveedor y placa de transporte

- **RUC Proveedor (todas las empresas)**: por la Resolución NAC-DGERCGC26-00000027,
  el sistema agrega automáticamente el campo **"RUC Proveedor"** en la información
  adicional del XML y del PDF de todos los comprobantes electrónicos. En la pestaña
  *Info. Adicional* del modal se muestra como una fila fija con candado: **no se
  puede editar ni eliminar**. El valor lo configura el superadministrador en
  `/config/sri-proveedor`.
- **Placa del vehículo (solo operadoras de transporte)**: si la empresa tiene activo
  el switch **"Operadora de transporte comercial (excepto taxis)"** (módulo Empresa →
  pestaña Facturación), la factura pide la **placa del vehículo** (obligatoria, formato
  `ABC1234` sin espacios ni guiones). La placa sale en el XML (tag `<placa>`) y en el
  PDF (casilla *Placa / Matrícula*). Ficha Técnica SRI v2.34, Anexo 25.

## Historial de cambios

- **1.7** — *"Clave de acceso en procesamiento"* ya no se trata como un rechazo.
  Es la respuesta del SRI cuando la factura ya está en su cola de un envío
  anterior: ahora el sistema lo reconoce y pasa a consultar la autorización en
  vez de darla por devuelta. Antes quedaba marcada como devuelta y, por serlo,
  fuera del reintento automático: nadie volvía a mirarla.
- **1.6** — El secuencial ahora es único **por ambiente**. Pruebas y Producción
  son numeraciones independientes en el SRI (el ambiente va dentro de la clave
  de acceso), así que al pasar un local a Producción la serie arranca de nuevo y
  repite números ya usados en Pruebas. El sistema los leía como duplicados y
  bloqueaba la emisión con *"El número de secuencial ya existe para este punto
  de emisión"*, aunque el número calculado sí estuviera libre en el ambiente
  activo. Dentro de un mismo ambiente la protección contra números repetidos
  sigue igual de estricta.
- **1.5** — La tirilla se adapta al ancho de papel del driver en vez de imponer el
  suyo, con columnas de ancho proporcional y tipografía sans-serif: ya no sale
  reescalada, con los importes corridos ni con la letra entrecortada en
  impresoras térmicas de 80 mm.
- **1.4** — Corregido: si la empresa tenía más de un establecimiento y el
  activo no era el que ordenaba primero por código, el selector de Serie
  quedaba vacío (tomaba los puntos de emisión del establecimiento
  equivocado, generalmente inactivo). Ahora siempre se arma con el
  establecimiento realmente Activo.
- **1.3** — Nuevo botón **Excel** en la barra de acciones, junto al de XML:
  descarga el detalle de la factura (líneas, totales y forma de pago) en una
  hoja de cálculo.
- **1.2** — Normativa SRI 2026: campo fijo "RUC Proveedor" en info adicional (no
  editable) y placa del vehículo obligatoria para operadoras de transporte
  comercial. Columnas "Precios" y "Medida" del detalle ahora solo aparecen cuando
  algún ítem las usa. Los ítems ya no aceptan cantidades, precios ni descuentos
  negativos. Al abrir una factura nueva se aplican los favoritos del usuario.
- **1.1** — Se documenta el envío por WhatsApp y el enlace de pago con tarjeta,
  disponible ahora también con **Nuvei** además de Payphone.
- **1.0** — Versión inicial.
