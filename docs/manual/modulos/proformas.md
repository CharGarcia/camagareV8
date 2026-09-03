---
titulo: Proformas
resumen: Cotizaciones al cliente y su conversión en factura de venta.
categoria: Ventas
ruta_modulo: modulos/proformas
tipo: modulo
visibilidad: todos
etiquetas: proforma, proformas, cotizacion, cotizar, presupuesto, oferta, convertir a factura, enviar por whatsapp, exportar excel, info productos, ficha de productos, catalogo, imagenes de productos, informacion adicional, plantillas, plantilla de proforma, guardar como plantilla, condiciones, terminos y condiciones, anexo, pdf de condiciones, texto con formato, clausulas
version: 1.9
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

## Decimales y cálculo del IVA

La proforma usa la **misma configuración de la empresa que las facturas de
venta**, que se define en *Empresa → Establecimientos*:

| Configuración | Qué controla |
|---|---|
| **Decimales de cantidad** | Cuántos decimales se muestran y se escriben en la columna *Cant.* |
| **Decimales de precio** | Cuántos decimales se muestran en *P. Sin Imp.* y *P. Con Imp.* |
| **Cálculo del IVA** | *Línea por línea* (se redondea el IVA de cada renglón y se suman) o *Al subtotal* (se calcula sobre la base acumulada de cada tarifa) |

Los importes (descuento, subtotal y totales) siempre llevan 2 decimales, y cada
paso del cálculo se redondea a 2 decimales, exactamente igual que en facturas.

Por eso la pantalla, el **PDF** y el **Excel** muestran las mismas cifras: las
salidas no recalculan nada, leen los valores guardados de la proforma. El pie de
totales es el mismo en las tres: **Subtotal** (antes de descuento), un
**Subtotal por cada tarifa de IVA**, **(-) Descuento**, un **(+) IVA por cada
tarifa** y el **TOTAL**.

> Si cambia la configuración de decimales o de cálculo del IVA, las proformas ya
> guardadas conservan los valores con los que se grabaron. Se actualizan cuando
> se vuelve a abrir y guardar la proforma.

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

## Condiciones: anexo en PDF aparte de la proforma

En el pie del modal, junto a **Info. Adicional** y **Vigencia**, está la
sub-pestaña **Condiciones**. Es un editor de texto con formato (títulos,
negrita, cursiva, subrayado, color, alineación, listas, sangría y enlaces)
pensado para escribir todo lo que la cotización necesite aclarar y que no cabe
en la proforma: garantías, forma y plazos de pago, tiempos de entrega,
exclusiones, cláusulas, etc.

Lo que se escribe ahí **no se imprime dentro de la proforma**. Se genera como un
**PDF anexo independiente** (`Condiciones_<número>.pdf`) con el nombre de la
empresa, el número de la proforma, la fecha y el cliente en la cabecera, y el
texto con su formato debajo.

- **Descargar PDF**: el botón dentro de la misma pestaña descarga el anexo. Se
  genera a partir de lo **guardado**, así que hay que guardar la proforma antes
  (si se editaron las condiciones y no se ha guardado, el PDF sale con la
  versión anterior).
- **Correo**: el anexo viaja **siempre** junto al PDF de la proforma cuando la
  proforma tiene condiciones guardadas; no hay que marcar nada. El diálogo de
  envío lo avisa con la línea "Se adjuntará también el PDF de condiciones". Si
  la proforma no tiene condiciones, simplemente no se adjunta.
- **WhatsApp**: la plantilla de Meta admite un único documento, así que la
  proforma va en el mensaje de plantilla y las condiciones se envían como un
  **segundo mensaje** con el PDF. Meta solo acepta ese segundo mensaje si el
  cliente escribió a la empresa en las últimas 24 horas (conversación abierta);
  si lo rechaza, la proforma ya salió y el sistema avisa que el anexo no pudo
  enviarse por ese canal, para que se envíe por correo.
- **Info. Adicional**: el sistema **no** agrega ninguna fila automática. Si
  quiere que en la proforma impresa conste que existe el anexo, agregue a su
  criterio una línea en Info. Adicional (por ejemplo, concepto `Condiciones` y
  detalle `Ver anexo adjunto`).

Las condiciones solo se editan mientras la proforma está en **borrador**, igual
que el resto del documento. No admite imágenes: el contenido se guarda como
texto y una imagen incrustada haría crecer el registro sin control.

## Plantillas

Una plantilla guarda una "foto" del **detalle de ítems**, la **información
adicional**, la **vigencia** y las **condiciones** para reutilizarla y armar una
proforma nueva más rápido. No incluye cliente ni fechas — eso se define en cada
proforma.

Para crear una: en la pestaña **Plantillas** pulsa **"Nueva plantilla"**. Se
abre un formulario propio (nombre, vigencia, detalle de ítems con buscador de
producto e información adicional por línea, e información adicional de
cabecera) — es independiente de la proforma que estés editando en ese momento.

Para usarla: en la pestaña **Plantillas** pulsa **Usar** sobre la que
necesites. Si el detalle actual ya tiene datos, se pide confirmación porque
**reemplaza** por completo el detalle, la información adicional, la vigencia y
las condiciones — no los combina. Al aplicarla, el modal cambia automáticamente a la pestaña
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

- **1.9** — Nueva sub-pestaña **Condiciones** (junto a Vigencia): editor de texto
  con formato para las condiciones adicionales de la cotización. No se imprimen en
  la proforma: se generan como un **PDF anexo aparte**, descargable desde la misma
  pestaña, que acompaña siempre a la proforma al enviarla por correo (y por
  WhatsApp como segundo mensaje, cuando Meta lo permite).
  Las **plantillas** también guardan las condiciones y las precargan al usarlas.
  El sistema no agrega filas a Info. Adicional; esa referencia la escribe el
  usuario si la quiere.
- **1.8** — La proforma respeta la **configuración de la empresa** (decimales de
  cantidad, decimales de precio y modo de cálculo del IVA), la misma que usan las
  facturas de venta; antes usaba decimales fijos y sumaba el IVA siempre línea por
  línea. Los cálculos redondean a 2 decimales en cada paso, igual que en facturas, y
  el **PDF** y el **Excel** muestran el mismo pie de totales que la pantalla
  (Subtotal, subtotales por tarifa, descuento e IVA por tarifa) leyendo los valores
  guardados en vez de recalcularlos. Nueva sección *Decimales y cálculo del IVA*.
- **1.7** — El **PDF de la proforma** muestra los valores exactamente como se ven
  en pantalla: precio unitario con 4 decimales (antes se redondeaba a 2), subtotal
  de línea calculado igual que el modal (cantidad × precio − descuento) y el bloque
  de totales con el mismo desglose: **Subtotal**, un **Subtotal por cada tarifa de
  IVA**, el **(-) Descuento** y un **(+) IVA por cada tarifa**. El IVA ya no se
  deduce restando totales, se suma por línea.
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
