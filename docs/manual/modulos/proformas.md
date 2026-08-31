---
titulo: Proformas
resumen: Cotizaciones al cliente y su conversión en factura de venta.
categoria: Ventas
ruta_modulo: modulos/proformas
tipo: modulo
visibilidad: todos
etiquetas: proforma, proformas, cotizacion, cotizar, presupuesto, oferta, convertir a factura, enviar por whatsapp, exportar excel, info productos, ficha de productos, catalogo, imagenes de productos, informacion adicional, plantillas, plantilla de proforma, guardar como plantilla
version: 1.5
orden: 15
estado: activo
---

Una **proforma** es la cotización que se entrega al cliente antes de vender. No
tiene efecto tributario ni contable: no se envía al SRI, no mueve inventario y no
genera cuentas por cobrar. Cuando el cliente acepta, se convierte en factura con
un clic.

## Estados y su recorrido

Una proforma pasa por estos estados, y el sistema controla el orden:

| Desde | Puede pasar a |
|-------|---------------|
| Borrador | Aprobada, Anulada |
| Aprobada | Rechazada, Anulada |

Cualquier otro salto se rechaza. El significado de cada uno:

- **Borrador**: se está preparando. **Es el único estado en el que se puede editar.**
- **Aprobada**: el cliente la aceptó. Ya se puede facturar.
- **Rechazada**: el cliente no la tomó. Queda como historial.
- **Anulada**: se descarta.
- **Convertida**: ya generó una factura.

## Cómo se usa

1. Pulse **Nuevo**.
2. Elija el cliente.
3. Agregue los productos con su cantidad, precio y descuento.
4. Guarde. La proforma queda en **borrador**.
5. Envíela al cliente. Si la acepta, cámbiela a **aprobada**.
6. Pulse **Convertir a factura**.

## Convertir en factura

Solo se factura una proforma **aprobada**. La factura se crea **en borrador**,
copiando cliente, productos, cantidades y precios, para que pueda revisarla antes
de enviarla al SRI.

Si intenta convertir una proforma que **ya generó una factura vigente**, el
sistema pide confirmación antes de crear otra. Es a propósito: evita duplicar una
venta por error, pero permite refacturar cuando de verdad hace falta (por ejemplo
si la factura anterior fue anulada).

## Editar

Solo se puede editar una proforma en **borrador**. Fuera de ese estado, el modal
se abre en modo solo lectura (cliente, detalle, información adicional y
vigencia bloqueados; sin botón Guardar) — no solo se rechaza al guardar, se ve
bloqueado desde que se abre. Si ya está aprobada y necesita cambiarla, tiene dos
caminos: anularla y crear una nueva, o —cuando el cambio es menor— convertirla a
factura y corregir en la factura antes de enviarla.

## Exportar a Excel

Desde la proforma guardada, el botón **Excel** (junto al de PDF, en la barra de
acciones superior del modal) descarga la cotización en formato `.xlsx`: datos
de la empresa, número y fecha de la proforma, cliente, el detalle de ítems
(código, descripción, cantidad, precio unitario, descuento, IVA % y subtotal),
los totales, la información adicional y las observaciones. Igual que el PDF,
no está disponible en una proforma nueva sin guardar.

## Información adicional por producto e Info Productos

Cada línea del detalle tiene una columna **Adicional** para anotar un dato libre
sobre ese ítem (por ejemplo color, talla, condición o una aclaración para el
cliente). Ese mismo dato se ve también en la pestaña **Info Productos**, que
muestra en formato de catálogo — con la imagen guardada en la ficha del
producto — cada línea de la proforma; el campo de información adicional se
puede editar desde cualquiera de las dos pestañas, es el mismo dato. Los ítems
sin producto vinculado (líneas libres) o sin imagen cargada se muestran con un
marcador genérico.

El botón **Descargar PDF** de esa pestaña descarga la ficha de productos sin
necesidad de enviarla por correo primero.

Como cualquier otra pestaña, **Info Productos** se puede ocultar desde el
engranaje junto a las pestañas si no se usa.

## Ficha de productos en el correo

Al enviar la proforma por correo, aparece la opción **"Adjuntar ficha de
productos con imágenes"**. Si se marca, además del PDF de la proforma se envía
un segundo PDF tipo catálogo con la imagen, código/nombre, cantidad e
información adicional de cada línea — útil para que el cliente reconozca
visualmente lo cotizado. Es opcional y no se adjunta si no se marca la casilla.

## Plantillas

Una plantilla guarda una "foto" del **detalle de ítems**, la **información
adicional** y la **vigencia** para reutilizarla y armar una proforma nueva más
rápido. No incluye cliente ni fechas — eso se define en cada proforma.

Para crear una: en la pestaña **Plantillas** pulsa **"Nueva plantilla"**. Se
abre un formulario propio (nombre, vigencia, detalle de ítems con buscador de
producto e información adicional por línea, e información adicional de
cabecera) — es independiente de la proforma que estés editando en ese momento.

Para usarla: en la pestaña **Plantillas** pulsa **Usar** sobre la que
necesites. Si el detalle actual ya tiene datos, se pide confirmación porque
**reemplaza** por completo el detalle, la información adicional y la vigencia
— no los combina. Al aplicarla, el modal cambia automáticamente a la pestaña
**Proforma** para que veas el resultado.

Para modificarla, pulsa el ícono de lápiz — abre el mismo formulario con sus
datos cargados. Eliminar una plantilla no afecta a las proformas que ya se
generaron con ella.

El IVA de cada línea de la plantilla se recalcula con la tarifa **vigente** al
usarla, no con la que tenía cuando se guardó — así una plantilla antigua no
aplica un IVA desactualizado.

## Enviar la proforma por WhatsApp

Desde la proforma guardada, el botón de **WhatsApp** la manda al cliente con su
PDF adjunto. Se elige la plantilla aprobada y el número (viene precargado el de
la ficha del cliente, con el código de país).

Para esto existe la plantilla del sistema **`proforma`**, que se crea en el módulo
de Plantillas de WhatsApp y rellena, en este orden, el **nombre del cliente**, el
**número de la proforma** y el **valor total**. Mientras Meta no la apruebe no
aparece en la lista. Las plantillas de enlace de pago no se ofrecen aquí: una
proforma todavía no es un cobro.

El mensaje enviado queda registrado en la conversación del cliente dentro del
Chat de WhatsApp.

## Eliminar

**Una proforma convertida no se puede eliminar.** Existe una factura que nació de
ella, y borrarla dejaría esa factura sin su origen. Anúlela si ya no aplica.

En el resto de casos la eliminación es lógica: desaparece del listado pero se
conserva en la base de datos con el usuario y la fecha.

## Permisos

Con **acceso total** se ven las proformas de toda la empresa; sin él, cada
vendedor ve solo las suyas — que suele ser justo lo que se quiere en un equipo
comercial.

## Serie y secuencial

La proforma se numera con la **serie** (establecimiento + punto de emisión) y su
**secuencial**. En el selector *Serie* del modal solo aparecen los puntos de
emisión que ya tienen configurado el secuencial del documento **Proformas** en
*Empresa → Puntos de emisión*; el resto se oculta porque no podrían numerar.

Al abrir una **proforma nueva** el sistema pide el siguiente número disponible de
esa serie y lo muestra en *Secuencial* (campo de solo lectura). Si detecta un
número faltante (hueco) en la numeración lo recupera y marca el campo en
**amarillo**; al pasar el cursor se indica el motivo.

Si la serie elegida **no tiene secuencial configurado**, o la empresa no tiene
ninguna serie disponible para proformas, el sistema **avisa apenas se abre el
modal** y **no deja guardar** hasta configurarlo. Es el mismo comportamiento que
en Facturas de Venta.

## Errores frecuentes

- **"La proforma debe estar aprobada para generar una factura"**: cámbiela a
  aprobada primero.
- **"Solo se pueden editar proformas en estado borrador"**: ya fue aprobada;
  anúlela y cree una nueva, o corrija en la factura resultante.
- **"No se puede eliminar una proforma ya convertida a factura"**: use anular.
- **"Secuencial no configurado"**: la serie elegida no tiene numeración para
  proformas, o no hay ninguna serie disponible. Configúrela en *Empresa → Puntos
  de emisión* antes de emitir.
- **El cliente no aparece**: regístrelo primero en Clientes, en esta misma empresa.

## Historial de cambios

- **1.6** — En el **PDF de la proforma** el logo de la empresa ocupa todo el
  espacio superior izquierdo: a lo ancho hasta donde arranca la tarjeta
  "PROFORMA" y a lo alto hasta el nombre de la empresa. La imagen se ajusta
  dentro de ese recuadro sin deformarse, así que un logo horizontal (proporción
  ancha) es el que mejor aprovecha el espacio.
- **1.5** — Se documenta la **serie y el secuencial**: solo se ofrecen series con
  numeración de proformas configurada, y el sistema avisa y bloquea el guardado
  cuando no la hay (incluido el caso de no tener ninguna serie disponible).
- **1.4** — Pestaña **Plantillas**: guarda detalle + información adicional +
  vigencia como plantilla reutilizable y la aplica para armar proformas más
  rápido.
- **1.3** — Pestaña **Info Productos** (catálogo con imagen por línea), campo de
  información adicional por producto persistido, y ficha de productos con
  imágenes como adjunto opcional del correo.
- **1.2** — Se agrega el botón **Excel** para exportar la proforma a `.xlsx`.
- **1.1** — Se documenta el envío de la proforma por WhatsApp y la plantilla del
  sistema `proforma`.
- **1.0** — Versión inicial.
