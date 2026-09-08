---
titulo: Guías de remisión
resumen: Documento electrónico que ampara el traslado de mercadería entre dos puntos.
categoria: Ventas
ruta_modulo: modulos/guias_remision
tipo: modulo
visibilidad: todos
etiquetas: guia de remision, guias, traslado, transporte, envio, placa, transportista, sri, mercaderia en transito, ride, pdf, imprimir guia, guia desde transferencia, traslado entre bodegas, traslado entre establecimientos
version: 1.8
orden: 55
estado: activo
---

La **guía de remisión** es el documento electrónico que respalda el traslado de
mercadería: quién la envía, quién la recibe, quién la transporta y en qué
vehículo. Se envía al SRI como cualquier otro comprobante electrónico.

## Antes de emitirla

Necesita tener registrados:

- El **cliente** que recibe la mercadería (destinatario).
- El **transportista**, con su identificación.
- El establecimiento, punto de emisión y secuencial configurados.

## Cómo se emite

1. Pulse **Nuevo**.
2. Elija **establecimiento** y **punto de emisión**; revise el **secuencial**.
3. Seleccione el **destinatario**.
4. Seleccione el **transportista** e ingrese la **placa** del vehículo.
5. Indique la **fecha de emisión** y la **fecha de inicio del transporte**.
6. Añada los productos que se trasladan.
7. Guarde y envíe al SRI.

### Valores que se completan solos en una guía nueva

Al crear una guía, tanto desde el módulo como desde el modal de Facturas de
Venta, el sistema propone:

| Campo | Valor propuesto |
|-------|-----------------|
| Motivo traslado | `VENTA` |
| Punto de partida | Dirección del establecimiento de la serie elegida (cambia si cambia la serie) |
| Punto de llegada (destino) | Dirección del cliente seleccionado |
| Ruta | Dirección del cliente seleccionado |

Son solo propuestas: si escribe otro texto, el sistema no lo vuelve a pisar
aunque cambie de cliente o de serie. Los favoritos de campo tienen prioridad
sobre estos valores.

### Crear el cliente o el transportista sin salir de la guía

Si el destinatario o el transportista todavía no existen, no hace falta ir a
sus módulos: en la **barra superior del modal** de la guía hay dos botones,
**Registrar nuevo cliente** (ícono de persona) y **Registrar nuevo
transportista** (ícono de camión), igual que en Facturas de Venta. Abren la
ficha correspondiente encima de la guía y, al guardarla, el registro nuevo
queda **seleccionado automáticamente** como destinatario o transportista (en
el caso del transportista también se copia su placa si el campo estaba vacío).

Cada botón aparece solo si el usuario tiene permiso de **crear** en
**Clientes** o en **Transportistas**, respectivamente.

### Guía enviada al SRI: solo lectura

Una vez que la guía se envió al SRI (autorizada, no autorizada, devuelta o
anulada) **ya no se puede editar**, igual que las facturas de venta: todos los
campos, las líneas de productos y la información adicional quedan bloqueados y
desaparecen los botones **Guardar**, **Agregar línea** y los de crear cliente o
transportista. El servidor aplica la misma regla, así que no hay forma de
modificarla desde otra pantalla. Solo se editan las guías en **borrador**.

## Correo al destinatario y al transportista

Al quedar **autorizada** por el SRI, la guía se envía por correo (XML y PDF)
si la empresa tiene activo el **envío automático** en su configuración de
correo. Los destinatarios son el **correo del cliente** (destinatario de la
mercadería) **y el correo del transportista**, tal como constan en sus fichas;
si alguna ficha tiene varios correos separados por coma, se envía a todos.

Para reenviarla en cualquier momento, en la barra superior del modal está el
botón **Enviar por correo** (ícono de sobre), disponible solo en guías
autorizadas. Propone esos mismos correos y permite editarlos antes de enviar.

El listado muestra la columna **Correo** (Pendiente / Enviado) y el buscador
admite el filtro `correo:pendiente` o `correo:enviado`.

## Documento de sustento

Es la factura (o el comprobante) que respalda el traslado. Escriba el número en
**Número documento** y elija uno de la lista: al seleccionarlo se completan
solos el **tipo**, la **fecha del documento** y su **número de autorización**, y
además se cargan el cliente, las direcciones y los productos de esa factura.

Si escribe el número a mano, indique también la **fecha doc. sustento**: el SRI
la exige en el XML y es la que aparece en el RIDE junto al número.

## Fechas de la guía

Deben ir en orden: **emisión → salida → llegada**. La fecha de salida no puede
ser anterior a la de emisión, y la de llegada no puede ser anterior a la de
salida (pueden ser el mismo día). El modal ya no deja elegir fechas fuera de ese
orden y el sistema lo vuelve a comprobar al guardar.

La **fecha de salida** es además la que el SRI toma como fecha del comprobante,
así que al enviar debe ser hoy o posterior.

## Anular una guía

Solo se anulan las guías **autorizadas**: en el resto de estados el botón no
aparece, y una guía en borrador se descarta eliminándola.

La anulación se hace primero en el **portal del SRI**; aquí solo se refleja. Si
el SRI todavía reporta la guía como autorizada, el sistema no deja anularla y lo
avisa. El plazo es hasta el **día 7 del mes siguiente** al de emisión (o el
siguiente día hábil), igual que en las facturas de venta.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Establecimiento y punto de emisión | Obligatorios |
| Secuencial | Obligatorio |
| Destinatario | Obligatorio |
| Transportista | Obligatorio |
| Placa del vehículo | Obligatoria |
| Fecha de emisión | Obligatoria |
| Fecha de inicio de transporte | Obligatoria |

## El PDF de la guía (RIDE)

El botón **PDF** del modal **descarga** el RIDE (archivo
`guia_remision_001-001-000000123.pdf`) con el mismo diseño de los demás
comprobantes electrónicos (factura, nota de crédito, retención):

- **Encabezado**: el logo del **establecimiento** que emite la guía —el mismo
  que se configura en Empresa → Establecimientos y que sale en la factura; si no
  hay ninguno se imprime *SIN LOGO*—, datos del emisor
  (nombre comercial, razón social, dirección matriz y de sucursal, contribuyente
  especial, obligado a llevar contabilidad, agente de retención y régimen RIMPE)
  y, a la derecha, RUC, número de la guía, número de autorización, fecha y hora
  de autorización, ambiente, tipo de emisión y la clave de acceso con su código
  de barras.
- **Transportista y traslado**: nombre del transportista y, debajo, su
  **correo** (si en su ficha hay varios correos, se imprimen uno por línea);
  luego identificación, placa, fechas de inicio y fin del transporte y punto
  de partida. En plantillas personalizadas el correo está disponible como el
  campo `{gr_transportista_email}`.
- **Destinatario**: nombre, identificación, punto de llegada, motivo del
  traslado, ruta, documento aduanero, código de establecimiento de destino y el
  documento de sustento (tipo, número, fecha y autorización) cuando se registró.
- **Detalle**: código principal, código auxiliar, cantidad y descripción.
- **Pie**: información adicional, observaciones y el mensaje personalizado que
  la empresa haya configurado para sus PDF.

La clave de acceso, el número y la fecha de autorización se toman de la guía; si
esas columnas están vacías pero la guía tiene guardado el sobre del SRI, se leen
de ahí. Una guía que nunca se envió al SRI no tiene autorización que mostrar: el
código de barras aparece solo cuando existe clave de acceso.

Cuando la empresa tiene una **plantilla PDF activa** para guías de remisión, se
usa esa plantilla en lugar de este formato.

## Exportar a Excel

Junto al botón **PDF** hay un botón **Excel** que descarga el mismo comprobante
en formato `.xlsx` (archivo `Guia_Remision_001-001-000000123.xlsx`). Como una
guía de remisión no tiene totales monetarios —es un documento de transporte, no
de venta—, el Excel muestra: fecha de emisión, estado, destinatario, direcciones
de partida y destino, motivo del traslado, ruta, transportista, placa y fechas
de inicio/fin de transporte, seguido del detalle de productos y cantidades
transportadas, y la información adicional si la guía la tiene. Ambos botones
solo aparecen en una guía ya guardada.

## La guía no mueve inventario

Emitir una guía **no descuenta stock**: solo ampara el traslado. El movimiento de
inventario lo genera el documento que corresponda (la factura de venta, o el
traslado entre bodegas).

## Errores frecuentes

- **"Ingrese la placa del vehículo transportista"**: es obligatoria aunque el
  transporte sea propio.
- **"Seleccione el transportista"**: regístrelo primero en Transportistas o con
  el botón **Registrar nuevo transportista** de la barra del modal.
- **"La fecha de inicio de transporte ya pasó"**: el SRI toma esa fecha como la
  fecha del comprobante —es la que va dentro de la clave de acceso—, así que
  debe ser hoy o posterior. Corríjala en la guía y vuelva a enviar.
- **El SRI responde "error en la estructura de la clave de acceso"**: la clave
  dejó de corresponder con los datos de la guía. Al enviar se recalcula sola;
  si el error persiste, revise que la fecha de inicio de transporte sea válida.
- **El stock no bajó al emitir la guía**: es correcto, la guía no mueve
  inventario.

## Guía a partir de una transferencia de inventario

Si el traslado corresponde a una **Transferencia de Inventario entre
establecimientos**, no hace falta volver a escribir el detalle: en el documento
de la transferencia hay un botón **Guía de remisión** que abre este módulo con la
fecha, el motivo, las direcciones de partida y destino y los productos ya
cargados. Solo queda completar el **destinatario**, el **transportista** y la
**placa**, y emitirla como cualquier otra guía.

## Historial de cambios

- **1.8** — Al elegir el transportista, debajo de su nombre se muestra ahora su
  **correo** junto a la identificación y la placa; el RIDE imprime el correo
  debajo del nombre del transportista (varios correos, uno por línea) y las
  plantillas personalizadas cuentan con el campo `{gr_transportista_email}`.
  Al abrir una guía cuyo documento de sustento es una factura del sistema y no
  tiene grabada la **fecha del documento de sustento** (o su autorización), se
  completan desde la factura, tanto en el formulario como en el RIDE; al
  guardar la guía quedan registradas. Se corrige además que, al reabrir una
  guía guardada, la identificación del transportista aparecía vacía.
  El correo automático tras la autorización va ahora **también al
  transportista** (antes solo al cliente); se agrega el botón **Enviar por
  correo** en el modal y la columna **Correo** (con filtro `correo:`) en el
  listado. Una guía que ya se envió al SRI queda **totalmente en solo lectura**
  (antes algunos campos, como la fecha del documento de sustento, seguían
  editables y una guía nueva abierta después heredaba los controles
  bloqueados). Al crear la guía **desde el modal de Facturas de Venta**, la
  **fecha del documento de sustento** se completa con la fecha de la factura
  (antes quedaba vacía) y se copian también la dirección y el correo del
  cliente. Toda guía nueva propone **motivo VENTA**, **partida** = dirección
  del establecimiento y **destino** y **ruta** = dirección del cliente, sin
  pisar lo que el usuario escriba a mano.
- **1.7** — La barra superior del modal se ve también en una guía **nueva** e
  incluye los botones **Registrar nuevo cliente** y **Registrar nuevo
  transportista** (según permiso de crear en cada módulo). El registro creado
  queda seleccionado en la guía al guardarlo.
- **1.6** — La guía se puede precargar desde una **Transferencia de Inventario**
  entre establecimientos (productos, direcciones y motivo ya cargados).
- **1.5** — Nuevo botón **Excel** junto al de PDF en el modal: descarga el mismo
  comprobante en `.xlsx`, adaptado a un documento de transporte (sin totales
  monetarios): cabecera de traslado, transportista y detalle de productos/cantidades.
- **1.4** — Las fechas se validan en orden (emisión → salida → llegada) y el
  modal impide elegirlas fuera de ese orden. **Anular** queda disponible solo
  para guías autorizadas y comprueba contra el SRI que ya no lo estén, igual que
  en factura de venta. Al enviar, la clave de acceso se recalcula si quedó
  desfasada y se avisa si la fecha de salida ya pasó.
- **1.3** — Corregida la clave de acceso: lleva la **fecha de inicio del
  transporte** (la que el SRI considera fecha del comprobante en una guía) y el
  **ambiente** de la empresa, que antes el formulario fijaba siempre en
  «pruebas». La clave se regenera al editar el borrador conservando su código
  numérico. El XML incluye ahora agente de retención, régimen RIMPE y
  contribuyente especial del emisor, como el resto de comprobantes. Los errores
  que devuelve el SRI se muestran completos y sin entradas vacías.
- **1.2** — El documento de sustento guarda ahora su **fecha de emisión** y su
  **número de autorización** (se completan solos al elegir la factura), y el
  RIDE los imprime. La clave y la fecha de autorización se recuperan del sobre
  del SRI cuando faltan en la guía. En Información Adicional siempre hay una
  línea lista para escribir.
- **1.1** — El PDF pasa a usar el diseño estándar del sistema (mismo encabezado
  que factura y nota de crédito, con logo, datos del emisor, autorización y
  código de barras). Se añaden los bloques de transportista, destinatario y
  documento de sustento, y el mensaje personalizado de la empresa.
- **1.0** — Versión inicial.
