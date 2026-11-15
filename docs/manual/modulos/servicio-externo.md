---
titulo: Servicio externo
resumen: Órdenes de mantenimiento o reparación hechas en el sitio del cliente, con repuestos de bodega y mano de obra, que luego se cobran con factura electrónica o recibo de venta.
categoria: Servicios
ruta_modulo: modulos/servicio-externo
tipo: modulo
visibilidad: todos
etiquetas: servicio externo, servicio a domicilio, visita tecnica, mantenimiento, mantenimiento en sitio, reparacion, tecnico, orden de servicio, orden de trabajo, orden de mantenimiento, equipo, marca, modelo, numero de serie, direccion del servicio, trabajo realizado, repuestos, mano de obra, servicio libre, bodega, stock, inventario, descargo de inventario, facturar orden, factura electronica, recibo de venta, forma de pago, pdf de la orden, imprimir orden, enviar por correo, enviar por whatsapp, secuencial, serie, punto de emision, borrador, facturado, anulado, buscar orden, filtros, busqueda por detalle
version: 1.0
orden: 0
estado: activo
---

El módulo **Servicio externo** registra los trabajos de mantenimiento o
reparación que se hacen **fuera del local**, en el sitio del cliente: qué equipo
se atendió, dónde, qué se hizo y qué repuestos y mano de obra se usaron. Desde la
misma orden se emite la **Factura electrónica** o el **Recibo de venta** para
cobrarla.

## Qué es y para qué sirve

- Dejar constancia de cada visita técnica: cliente, equipo (descripción, marca,
  modelo y número de serie), dirección del servicio, trabajo realizado y
  observaciones.
- Cargar en la orden los **repuestos** (productos del catálogo, que salen de una
  bodega) y la **mano de obra o servicios**.
- Cobrar la orden sin volver a digitar nada: con un clic se genera la factura o el
  recibo con las mismas líneas.
- Entregar al cliente el **PDF de la orden**, por descarga, impresión o correo.

## Requisitos previos

- Un **punto de emisión con numeración para *Ordenes servicio externo***
  (Empresa → Secuenciales). En el selector **Serie** de la orden solo aparecen
  los puntos de emisión que tienen esa numeración configurada; si no hay
  ninguno, el sistema avisa *Secuenciales no configurados* y no deja guardar.
- Para cobrar: numeración de **Facturas de venta** o **Recibos de venta** en el
  mismo punto de emisión de la orden.
- Los **clientes** y los **productos** (repuestos y servicios) pueden crearse
  desde la propia orden (ver *Crear un cliente o un producto sin salir de la
  orden*).

## Cómo registrar una orden

1. Pulse **Nuevo**. La orden se abre con la fecha y hora actuales, la serie
   marcada (la favorita o la primera) y el siguiente **Secuencial** ya asignado.
2. Busque y elija el **Cliente** (escriba al menos dos letras).
3. Escriba el **Equipo** atendido y, si los tiene, la **Marca**, el **Modelo** y
   la **Serie** (número de serie). Elija la **Bodega** de donde salen los
   repuestos.
4. Anote la **Dirección del servicio**, el **Trabajo realizado** y las
   **Observaciones**.
5. En la grilla, escriba en **Descripción** para buscar un repuesto o servicio
   del catálogo; la lista muestra el código, el precio y, en los productos que
   controlan inventario, el **Stock** en la bodega elegida. Use **Agregar
   línea** para sumar más filas y el ícono de papelera para quitar una.
6. Si hace falta, agregue datos en la pestaña **Info. Adicional** (Concepto y
   Detalle).
7. Pulse **Guardar**. El sistema confirma *Orden registrada correctamente.*, la
   ventana se cierra y la orden aparece en el listado en estado **Borrador**.

Para abrir una orden, haga clic en su fila del listado.

## Repuestos y mano de obra

- **Repuestos**: son productos del catálogo. Al elegirlos se cargan su precio,
  su tarifa de IVA, su lista de precios, sus variantes y su unidad de medida, y
  debajo de la descripción se ve el **Saldo** en la bodega de la orden.
- Si elige un producto sin existencias, aparece el aviso *Sin stock* («… no tiene
  stock disponible en la bodega seleccionada»), pero la línea se agrega igual.
  Que se pueda guardar o no depende de la configuración de inventario del
  establecimiento (ver *Reglas de negocio*).
- **Mano de obra o servicio libre**: si la empresa tiene activada la opción
  *Permitir ingreso de registros libremente (solo servicios)* (Empresa), al
  escribir una descripción que no está en el catálogo aparece la opción
  **«Registrar como servicio libre»**. La fila se pinta en amarillo y se le pone
  el precio a mano. Estas líneas no mueven inventario.
- Las columnas **Lote**, **Caducidad** y **NUP / Serial** aparecen solo si la
  empresa las tiene como obligatorias, igual que en la factura.
- Si la configuración lo permite, en cada línea se puede cambiar el precio (sin
  impuesto o con impuesto), el descuento y la tarifa de IVA.

## Crear un cliente o un producto sin salir de la orden

En la barra superior de la orden hay dos botones:

- **Registrar nuevo cliente** (ícono de persona con +): abre la ficha del
  cliente. Al guardarlo, queda **seleccionado en la orden** (solo si es un
  cliente nuevo y la orden se puede editar).
- **Registrar nuevo repuesto/servicio** (ícono de caja): abre la ficha del
  producto. Al guardarlo, se coloca en la primera fila vacía de la grilla o en
  una fila nueva. Si no aparece en el buscador de la orden, el sistema avisa
  *Producto creado. Búscalo en el detalle para agregarlo.*

## Estados de la orden

| Estado | Qué significa |
|--------|---------------|
| **Borrador** | La orden está registrada y todavía no se ha cobrado. Se puede modificar, eliminar y facturar. |
| **Facturado** | Ya se generó la factura o el recibo. La orden queda en solo lectura y no se puede eliminar. |
| **Anulado** | La orden está anulada: no se puede editar ni facturar, y lo que había consumido del inventario vuelve al stock. |

El estado **Facturado** lo pone el sistema al generar el documento; no se asigna
a mano.

## Cobrar la orden con factura o recibo

1. Abra la orden ya guardada (en estado **Borrador**).
2. En la barra superior pulse **Factura** (Factura electrónica) o **Recibo**
   (Recibo de venta).
3. Elija la **Forma de pago** y pulse **Generar**. La ventana recuerda que *el
   inventario se descarga de la bodega seleccionada en la orden.*
4. El sistema muestra «Factura … generado correctamente.» o «Recibo …
   generado correctamente.» con el número del documento. Pulse **Ver PDF** para
   ver el documento recién emitido.

Qué se genera:

- El documento toma el **cliente**, todas las **líneas** (repuestos y
  servicios, con sus precios, descuentos e IVA) y la **información adicional**
  de la orden.
- Se numera con la serie (punto de emisión) de la orden y con la **fecha del
  día**.
- En sus observaciones queda el texto *Generado desde orden de servicio externo*
  seguido del número de la orden.
- El **recibo** se crea en estado borrador.
- La factura o el recibo se consultan después en sus propios módulos
  ([Facturas de venta](modulos/factura-venta) y
  [Recibos de venta](modulos/recibos-venta)).

Cada orden genera **un solo** documento: si ya tiene uno, los botones
**Factura** y **Recibo** quedan desactivados.

## PDF, correo y WhatsApp

Los botones de la barra superior se activan cuando la orden ya está guardada:

- **PDF del documento** (ícono rojo): genera el **PDF de la orden** y pregunta
  si desea Imprimir, Descargar o Ver.
- **Enviar por correo**: envía el PDF de la orden. Propone el correo del
  cliente; puede escribir otro o varios, **separados por coma**. Si el cliente no
  tiene correo y no se escribe ninguno, el sistema lo indica.
- **Enviar por WhatsApp**: se activa solo cuando la orden ya generó una
  **factura**. Se elige una **Plantilla** y el **Teléfono**, y se envía usando el
  módulo de WhatsApp de la empresa.

El PDF de la orden lleva el encabezado de la empresa, el título **SERVICIO
EXTERNO** con el número y el estado de la orden, los datos del equipo (descripción,
marca y modelo), fecha, cliente con su identificación, dirección del servicio,
la tabla de repuestos y servicios (Descripción, Cant., P. Unit, Desc., IVA %,
Total), los totales con el valor en letras, la **Información adicional** y dos
espacios de firma: **Técnico** y **Cliente / Recibí conforme**. El trabajo
realizado y las observaciones no salen en el PDF.

## Buscar órdenes

El listado muestra **Fecha**, **N° Orden**, **Equipo**, **Cliente**, **Total** y
**Estado**. Se ordena con clic en el encabezado de cada columna y cada usuario
puede ocultar las columnas que no usa.

- **Búsqueda libre**: escriba en el cuadro *Buscar en todas las columnas...*.
- **Filtros** (botón del embudo, ventana *Filtros de servicio externo*):
  - *Orden*: Fecha del servicio, Estado, Serie, N° Orden, Secuencial, Total,
    Documento de venta (con o sin documento generado), Tipo de documento
    (Factura o Recibo de venta), N° documento generado, Fecha de finalización y
    Usuario que registró.
  - *Equipo*: Equipo, Marca, Modelo y N° de serie del equipo.
  - *Cliente y trabajo*: Cliente, RUC / Cédula, Dirección del servicio y Trabajo
    / observaciones.
- **Búsqueda por detalle**: pestaña de la misma ventana para buscar dentro de
  las líneas de las órdenes (servicio, producto, código, monto…). Muestra Tipo,
  Código, Descripción, Cant., Total, Orden, Fecha, Equipo, Cliente y Estado, y
  desde ahí se puede filtrar por esa orden o abrirla.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fecha servicio | Sí | Fecha y hora en que se hizo el servicio. Se llena con el momento actual. |
| Serie | Sí | Punto de emisión que numera la orden (y luego la factura o el recibo). Tiene estrella para dejar una serie favorita. No se cambia en una orden ya guardada. |
| Secuencial | Sí | Número de la orden. Lo asigna el sistema; no se escribe. |
| Cliente | Sí | Cliente al que se le hizo el servicio. Debajo se muestran su dirección, correo y teléfono. |
| Equipo | Sí | Descripción del equipo atendido. |
| Marca / Modelo / Serie | No | Datos del equipo; *Serie* es el número de serie del equipo. |
| Bodega | Para repuestos con inventario | Bodega de donde salen los repuestos. Tiene estrella para dejar una bodega favorita. |
| Dirección del servicio | No | Sitio donde se realizó el mantenimiento. |
| Trabajo realizado | No | Detalle del mantenimiento o reparación. |
| Observaciones | No | Notas adicionales. |
| Líneas (Descripción, Cant., precios, Desc., Iva) | Al menos una | Repuestos y servicios de la orden. Cada línea necesita descripción y cantidad mayor a 0. |
| Info. Adicional | No | Pares Concepto / Detalle que se imprimen en el PDF y pasan al documento de venta. |

## Permisos

- **Ver**: entrar al módulo, abrir órdenes y sacar su PDF o enviarlas por correo.
- **Crear**: ver el botón **Nuevo**, registrar órdenes y generar la factura o el
  recibo.
- **Actualizar**: modificar una orden en **Borrador**. Sin este permiso la orden
  se abre en solo lectura.
- **Eliminar**: ver el botón **Eliminar** (abajo a la izquierda) en las órdenes
  que todavía no generaron documento.
- **Acceso total**: con él se ven las órdenes de toda la empresa; sin él, cada
  usuario ve solo las órdenes que registró.

## Reglas de negocio

- Al **guardar**, la orden **descuenta del inventario** los repuestos del
  catálogo que controlan stock, siempre que el establecimiento tenga activada
  *La facturación afecta al inventario*. La mano de obra y los servicios libres
  no mueven stock.
- Con esa opción activa, la **bodega es obligatoria** para los repuestos que
  controlan inventario.
- Si el establecimiento tiene activado *¿Trabajar con stock positivo?*, no se
  puede guardar una orden que deje un repuesto en negativo.
- Al **modificar** una orden, el sistema devuelve lo que había descontado y vuelve
  a descontar con las líneas nuevas.
- Al **eliminar** una orden, lo que había consumido vuelve al stock. No se puede
  eliminar una orden que ya generó un documento.
- Al **generar la factura o el recibo**, la orden devuelve su salida de
  inventario y es el documento de venta el que hace su propia salida, así el
  stock no se descuenta dos veces. Si algo falla al generar, la orden queda
  exactamente como estaba.
- La orden **no genera asiento contable**; la contabilidad la produce la factura
  o el recibo al emitirse.
- El IVA de la orden se calcula según la forma de cálculo del establecimiento de
  la serie, igual que en la factura (ver
  [Cálculo del IVA](conceptos/calculo-iva)).
- Una orden facturada o anulada no se puede editar.

## Integraciones con otros módulos

- **Empresa → Secuenciales**: numeración *Ordenes servicio externo* y, para
  cobrar, *Facturas de venta* o *Recibos de venta*.
- **Inventario**: salida de repuestos al guardar la orden y devolución al
  modificarla, eliminarla, anularla o facturarla.
- [Facturas de venta](modulos/factura-venta) y
  [Recibos de venta](modulos/recibos-venta): reciben el documento generado
  desde la orden.
- [Clientes](modulos/clientes) y [Productos](modulos/productos): se pueden crear
  desde la orden.
- **WhatsApp**: envío de la factura generada con las plantillas de la empresa.

## Errores frecuentes

- **Secuenciales no configurados**: la serie no tiene numeración para *Ordenes
  servicio externo*. Configúrela en Empresa → Secuenciales.
- **El secuencial ya existe para este punto de emisión. Recargue e intente
  nuevamente.**: otra persona guardó una orden con el mismo número. Cierre y
  vuelva a abrir la orden nueva para tomar el siguiente número.
- **Seleccione un cliente.** / **Indique el equipo atendido.**: faltan el
  cliente o el equipo, que son obligatorios.
- **Seleccione la bodega para el producto "…"**: falta elegir la **Bodega** y
  el repuesto controla inventario.
- **Stock insuficiente para el producto '…'**: el establecimiento trabaja con
  stock positivo y no hay existencias suficientes en la bodega.
- **Agregue al menos un repuesto o servicio.**: la grilla está vacía o ninguna
  línea tiene descripción y cantidad mayor a 0.
- **La cantidad debe ser mayor a 0 en "…"**: una línea tiene descripción pero
  cantidad en 0.
- **No se puede editar una orden que ya generó un documento.**: la orden ya está
  facturada.
- **No se puede eliminar una orden que ya generó un documento. Anule primero el
  documento.**: la orden tiene factura o recibo.
- **Esta orden ya generó un documento (…)**: cada orden se cobra una sola vez.
- **La orden está anulada; no se puede facturar.**
- **El cliente no tiene correo registrado. Ingrese uno para enviar.**: escriba
  el correo destino en la ventana de envío.
- **No se pudo enviar el correo. Verifica la configuración de correo o el
  destinatario.**: revise el correo del cliente y la configuración de correo de
  la empresa.
- **Aún no tiene configurada la API de WhatsApp. Actívela en su módulo de
  WhatsApp.**

## Historial de cambios

- **1.0** — Versión inicial del manual.
