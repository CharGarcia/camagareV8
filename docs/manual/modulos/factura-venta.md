---
titulo: Facturas de Venta
resumen: Emisión de facturas electrónicas: creación, envío al SRI, PDF, correo y anulación.
categoria: Ventas
ruta_modulo: modulos/factura-venta
tipo: modulo
visibilidad: todos
etiquetas: factura, facturar, venta, sri, comprobante electronico, xml, anular, nota de credito, whatsapp, link de pago, payphone, nuvei, reembolso, reembolsos, factura de reembolso, gastos por cuenta de terceros
version: 1.3
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
guardado: generar el **PDF**, ver el **XML**, enviarlo por **correo** o por
**WhatsApp** y remitirlo al **SRI**. Cada acción comprueba primero que la factura
esté guardada.

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
- **Secuencial repetido**: revise el secuencial configurado para el
  establecimiento y punto de emisión.
- **El cliente no recibe el correo**: verifique la dirección registrada en su ficha.

## Reembolsos (gastos pagados a nombre del cliente)

Cuando la empresa paga un gasto a un tercero **en nombre del cliente** (por
ejemplo una agencia de viajes, una gestoría o un servicio profesional que
adelanta pasajes, trámites u otros gastos) y luego lo re-factura sin que sea
ingreso propio ni lleve IVA propio, ese comprobante del tercero se declara en
la pestaña **Reembolsos**, dentro de la pestaña "Factura de venta" del modal.

- **Es informativo/tributario**: declara ante el SRI el sustento del reembolso.
  Si el valor reembolsado forma parte del total que paga el cliente, agréguelo
  también como un **ítem normal en el detalle** de la factura (la pestaña
  Reembolsos no suma al subtotal ni al IVA de la factura).
- **Vincular una compra registrada** (recomendado): use el buscador para
  encontrar la compra ya registrada en el módulo de **Compras** (por
  proveedor, RUC o número de documento). Al seleccionarla se autocompletan el
  proveedor, el tipo y número del comprobante, la fecha, la autorización y el
  detalle de impuestos — son los mismos datos que ya se reportan en el Anexo
  ATS de compras, así que no se digitan dos veces.
- **Agregar manual**: solo para cuando el comprobante del proveedor no está
  registrado en Compras. Pide los mismos datos (identificación del proveedor,
  tipo de proveedor, tipo/serie/secuencial/fecha/autorización del documento y
  la base e IVA reembolsado).
- Cada línea se puede quitar con el botón de la fila mientras la factura esté
  en borrador.

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

- **1.3** — Nueva pestaña **Reembolsos** (SRI): declara comprobantes de
  terceros pagados a nombre del cliente, vinculando compras ya registradas o
  ingresándolos manualmente. Requiere aplicar la migración
  `20260730_create_ventas_reembolso.sql`.
- **1.2** — Normativa SRI 2026: campo fijo "RUC Proveedor" en info adicional (no
  editable) y placa del vehículo obligatoria para operadoras de transporte
  comercial. Columnas "Precios" y "Medida" del detalle ahora solo aparecen cuando
  algún ítem las usa. Los ítems ya no aceptan cantidades, precios ni descuentos
  negativos. Al abrir una factura nueva se aplican los favoritos del usuario.
- **1.1** — Se documenta el envío por WhatsApp y el enlace de pago con tarjeta,
  disponible ahora también con **Nuvei** además de Payphone.
- **1.0** — Versión inicial.
