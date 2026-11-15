---
titulo: Solicitudes Factura Express
resumen: Revise las solicitudes de factura que sus clientes envían desde el código QR y apruébelas para emitir la factura, o recházelas con un motivo.
categoria: Ventas
ruta_modulo: modulos/factura-express-solicitudes
tipo: modulo
visibilidad: todos
etiquetas: factura express, solicitudes factura express, solicitud de factura, solicitudes qr, qr, aprobar solicitud, aprobar y facturar, rechazar solicitud, motivo del rechazo, solicitudes pendientes, pendientes, facturadas, rechazadas, aprobadas, autofacturacion, cliente pidio factura, pedido de factura, factura desde el celular, campana, notificacion, aviso en la barra, contador de pendientes, correo de nueva solicitud, editar items antes de facturar, cambiar precio, cambiar cantidad, agregar linea, cliente nuevo, crear cliente automatico, estado de la solicitud, consultar estado, enlace de estado, panel movil, celular, qr solicitudes, buscar solicitud, buscar por cedula, buscar por correo
version: 1.0
orden: 0
estado: activo
---

Aquí llegan las **solicitudes de factura** que sus clientes envían al escanear
el código QR de una plantilla de Factura Express. Desde esta pantalla usted
revisa cada solicitud, corrige los ítems si hace falta y la **aprueba** para
emitir la factura de venta, o la **rechaza** indicando el motivo. Las plantillas
y sus QR se configuran en
[Configuración QR](modulos/factura-express-config).

## Qué es y para qué sirve

Cada fila es la solicitud de un cliente: sus datos (nombre, identificación,
correo, teléfono), la plantilla por la que llegó, los ítems que eligió y el
monto. Una solicitud puede estar en estos estados:

| Estado | Qué significa |
|--------|---------------|
| Pendiente | El cliente la envió y espera revisión |
| Facturada | Se aprobó y se generó la factura de venta |
| Rechazada | Se rechazó con un motivo |
| Aprobada | Estado que el sistema reconoce en los filtros y en la página del cliente. Al aprobar desde esta pantalla la solicitud pasa directamente a **Facturada** |

Si la plantilla tiene apagado **Requiere aprobación manual**, las solicitudes
se facturan solas al llegar y aparecen ya como **Facturada**.

## Requisitos previos

- Una plantilla creada en [Configuración QR](modulos/factura-express-config)
  con su serie (establecimiento y punto de emisión) y forma de pago.
- Esa serie debe seguir **activa** y pertenecer a la empresa al momento de
  aprobar.
- La empresa debe estar **activa**.

## Cómo se entera de una solicitud nueva

- En la barra superior aparece un ícono con el número de solicitudes
  **pendientes** (*Solicitudes Factura Express*). Al pulsarlo entra a este
  módulo. Solo lo ven los usuarios con permiso de ver este módulo.
- La empresa recibe un correo con el asunto *Nueva solicitud Factura Express*
  y el botón **Ver solicitud en el sistema**. Se envía al correo registrado en
  los datos de la empresa.
- En [Configuración QR](modulos/factura-express-config), la columna
  *Solicitudes* y el botón **QR Solicitudes** muestran cuántas hay pendientes.
  Ese QR, escaneado con el celular, abre esta pantalla (pide iniciar sesión).

## El listado

Al entrar se muestran las solicitudes **Pendientes**. Con los botones de
arriba puede cambiar a **Aprobadas**, **Rechazadas**, **Facturadas** o
**Todas**.

| Columna | Qué muestra |
|---------|-------------|
| Fecha | Fecha y hora en que el cliente envió la solicitud |
| Cliente | Nombre que escribió el cliente |
| Identificación | Cédula, RUC o pasaporte |
| Correo | Correo del cliente |
| Plantilla | Plantilla QR por la que llegó |
| Monto | Total de la solicitud |
| Estado | Pendiente, Aprobada, Rechazada o Facturada |

El buscador **Buscar solicitud...** busca por nombre del cliente,
identificación o correo. Puede ordenar con clic en los encabezados y ocultar
columnas desde el botón de columnas. El botón **Configuración QR** lleva al
módulo de plantillas.

### En el celular

En pantallas pequeñas el listado se muestra como **tarjetas**, con los mismos
filtros de estado, el buscador *Buscar cliente, cédula o correo...* y el botón
**Cargar más** para ver más solicitudes. Al tocar una tarjeta se abre la
solicitud con los mismos botones de aprobar y rechazar.

## Revisar y aprobar una solicitud

1. Haga clic en la fila. Se abre **Detalle de Solicitud** con los datos del
   cliente (nombre, identificación, tipo, correo, teléfono y plantilla).
2. Si la solicitud está **pendiente** y usted tiene permiso de actualizar, los
   ítems aparecen en la tabla **Ítems a facturar (editable)**. Puede:
   - Cambiar la **cantidad**, el **precio unitario** o el **IVA** de una línea.
   - Pulsar **Agregar línea** y escribir al menos dos letras para buscar un
     producto del catálogo; al elegirlo se llenan el nombre, el precio y el IVA.
     Si no hay coincidencias verá *«Sin coincidencias (puede escribir libre)»*
     y puede dejar la descripción escrita a mano.
   - Quitar una línea con el ícono de la papelera.
   - Ver el **Total** recalculado con IVA.
3. Pulse **Aprobar y Facturar** y confirme en *«¿Aprobar y facturar?»* (*«Se
   generará la factura con los ítems mostrados.»*) con **Sí, aprobar**.
4. Verá *«Solicitud aprobada y factura generada correctamente.»* y la
   solicitud pasa a **Facturada**.

Si la solicitud ya fue procesada, los ítems se muestran solo para consulta,
junto con el estado, la nota y la fecha en que se procesó.

## Rechazar una solicitud

1. Abra la solicitud pendiente y pulse **Rechazar**.
2. Escriba el **Motivo del rechazo** (es obligatorio; mientras escribe, el botón
   de aprobar queda bloqueado). Con **Cancelar** vuelve atrás sin rechazar.
3. Pulse **Guardar rechazo**. Verá *«Solicitud rechazada.»*

El motivo lo ve el cliente en la página de estado de su solicitud.

## Qué se genera al aprobar

- **El cliente**: el sistema busca en la empresa un cliente con la misma
  identificación. Si existe, lo reutiliza y actualiza su nombre, y también su
  correo y teléfono si el cliente los envió (si estaba eliminado, lo vuelve a
  activar). Si no existe, lo crea con los datos de la solicitud.
- **La factura de venta**: se crea con la **fecha de hoy**, la **serie** y la
  **forma de pago** de la plantilla (por el total, sin plazo), y con los ítems
  que quedaron en pantalla. El correo del cliente se guarda en la información
  adicional de la factura como *correo del cliente*.
- **El IVA**: en las líneas que son productos del catálogo se usa siempre la
  **tarifa actual del producto**, aunque haya cambiado el IVA en pantalla. Solo
  las líneas escritas a mano usan el IVA elegido. El cálculo sigue la
  configuración de IVA del establecimiento de la serie (ver
  [Cálculo del IVA](conceptos/calculo-iva)).
- **Líneas escritas a mano**: si una línea no corresponde a un producto del
  catálogo, al facturar se registra como un servicio nuevo en el catálogo.

La factura queda en [Facturas de venta](modulos/factura-venta) y el cliente,
en [Clientes](modulos/clientes).

## Lo que ve el cliente

Al enviar la solicitud, el cliente recibe en pantalla y por correo un enlace
para **consultar el estado**. Esa página muestra sus datos, los ítems, el monto
y el estado:

- **Pendiente de revisión**: *«Tu solicitud está siendo revisada. Te
  notificaremos por correo cuando esté lista.»*
- **Rechazada**: muestra el **Motivo** que usted escribió.
- **Facturada**: *«Tu factura ha sido emitida. La recibirás por correo
  electrónico.»*

## Permisos

| Permiso | Qué permite |
|---------|-------------|
| Ver | Entrar al módulo, ver el listado y abrir el detalle de las solicitudes (solo lectura). También ver el contador de pendientes en la barra superior |
| Actualizar | Editar los ítems de una solicitud pendiente, **Aprobar y Facturar** y **Rechazar** |

Todos los usuarios con permiso de ver ven **todas** las solicitudes de la
empresa; el permiso de acceso total no cambia lo que se lista. Las plantillas
tienen permisos aparte, en [Configuración QR](modulos/factura-express-config).

## Reglas de negocio

- Solo se pueden aprobar o rechazar solicitudes en estado **Pendiente**. Si dos
  usuarios procesan la misma solicitud a la vez, solo la primera acción tiene
  efecto; la otra recibe *«Esta solicitud ya fue procesada.»* Así no se emiten
  dos facturas por la misma solicitud.
- Para aprobar debe quedar al menos un ítem con descripción y cantidad mayor a
  cero.
- Antes de facturar, el sistema revisa que la empresa esté activa y que la
  serie de la plantilla esté activa y sea de la empresa.
- Si algo falla al facturar, verá el mensaje del error y la solicitud sigue
  **Pendiente**, para que pueda corregir la causa y volver a aprobarla.
- Las aprobaciones y los rechazos quedan registrados en la auditoría del
  sistema.

## Integraciones con otros módulos

- **Configuración QR**: de la plantilla salen la serie, la forma de pago y los
  ítems que el cliente pudo elegir.
- **Clientes**: al aprobar se reutiliza o se crea el cliente.
- **Productos**: el buscador de ítems usa el catálogo; las líneas escritas a
  mano se registran como servicio nuevo al facturar.
- **Facturas de venta**: cada aprobación crea una factura de venta.

## Errores frecuentes

- **«Esta solicitud ya fue procesada.»**: otro usuario ya la aprobó o la
  rechazó. Recargue el listado.
- **«Solicitud no encontrada.»**: la solicitud no pertenece a la empresa
  seleccionada. Revise qué empresa tiene activa.
- **«La plantilla no tiene una serie (establecimiento y punto de emisión)
  configurada. Por favor configúrela antes de aprobar.»**: abra la plantilla en
  [Configuración QR](modulos/factura-express-config), elija la serie y guarde.
- **«El establecimiento/punto de emisión configurado en la plantilla no está
  activo o no pertenece a esta empresa.»**: la serie de la plantilla fue
  desactivada. Elija otra serie activa en la plantilla.
- **«La empresa no está activa. No es posible generar la factura.»**: la
  empresa está inactiva; no se pueden emitir facturas hasta reactivarla.
- **«No se puede emitir la factura porque el período contable de esa fecha está
  cerrado.»**: la factura se emite con la fecha de hoy y ese período está
  cerrado en contabilidad.
- **«Debe haber al menos un ítem con descripción y cantidad mayor a cero.»**:
  agregue o corrija los ítems antes de aprobar.
- **«Debe ingresar el motivo del rechazo.»**: escriba el motivo antes de pulsar
  **Guardar rechazo**.
- **No veo los botones Aprobar y Facturar ni Rechazar**: la solicitud ya no está
  pendiente o su usuario no tiene permiso de actualizar en este módulo.

## Historial de cambios

- **1.0** — Versión inicial del manual.
