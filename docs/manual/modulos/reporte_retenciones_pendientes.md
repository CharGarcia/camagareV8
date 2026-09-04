---
titulo: Retenciones de venta pendientes
resumen: Facturas de venta que aún no tienen comprobante de retención del cliente, con aviso por correo individual o agrupado.
categoria: Reportes
ruta_modulo: modulos/reporte_retenciones_pendientes
tipo: modulo
visibilidad: todos
etiquetas: retenciones pendientes, facturas sin retencion, retencion de venta faltante, cliente no envio retencion, comprobante de retencion, aviso por correo, reclamar retencion, agente de retencion, credito tributario
version: 1.0
orden: 51
estado: activo
---

Cuando un cliente que es agente de retención paga una factura, debe entregar el
comprobante de retención. Si no lo hace, la empresa pierde ese crédito
tributario y la factura queda con un saldo que en realidad nunca se va a
cobrar. Este reporte muestra **qué facturas de venta autorizadas todavía no
tienen ninguna retención registrada** y permite **avisar al cliente por
correo**, factura por factura o con un solo correo por cliente.

## Qué es y para qué sirve

Lista las facturas de venta **autorizadas** de la empresa activa que no tienen
ningún comprobante de retención asociado, ni registrado desde la factura ni
recibido electrónicamente del SRI. Sirve para:

- Revisar antes de declarar qué retenciones faltan por reclamar.
- Enviar al cliente un recordatorio con los datos de la factura para que
  remita el comprobante de retención electrónico.
- Llevar control de a qué facturas ya se les envió aviso, cuántas veces y
  cuándo fue la última vez.

El criterio de "tiene retención" es **exactamente el mismo** que usa Cuentas
por Cobrar para descontar lo retenido del saldo: una factura que en Cuentas por
Cobrar ya muestra un valor en la columna Retención **nunca** aparece aquí.

## Requisitos previos

- La empresa debe tener configurado el **correo saliente** (Configuración de la
  empresa, pestaña Correo) para poder enviar avisos. Sin eso el reporte se
  consulta igual, pero los envíos fallan con un mensaje de configuración.
- Los clientes deben tener **correo** en su ficha para que el aviso se
  precargue; si no lo tienen, el correo se puede escribir en el momento del
  envío (sin modificar la ficha).

## Cómo se usa

1. Elija el **Año**. Por defecto se muestra el año en curso.
2. Acote con **Mes** si quiere un mes puntual. Los campos **Desde / Hasta** se
   completan solos con ese rango y se pueden ajustar a mano para cualquier
   otro período (trimestre, semestre, etc.).
3. Opcionalmente filtre por **Aviso por correo** (sin aviso / con aviso) y por
   **Cliente** (buscador por nombre o RUC; Backspace o Suprimir quita la
   selección de una vez).
4. Presione **Buscar**. La tabla muestra una fila por factura con fecha, días
   transcurridos, cliente, correo, subtotal, impuestos, total y los avisos ya
   enviados.

### Ver por

- **Facturas**: una fila por factura (vista por defecto).
- **Por cliente**: agrupa las facturas de cada cliente con sus totales y
  cuántas ya tienen aviso. Clic en el grupo para desplegar sus facturas. El
  botón de sobre del grupo abre directamente el aviso agrupado de ese cliente.
- **Por mes**: agrupa por mes de emisión, útil para ver en qué período se
  concentran las retenciones faltantes.

### Enviar un aviso por una factura

Botón de **sobre** en la fila. Se abre una ventana con el correo del cliente
precargado (se pueden poner varios separados por coma), el asunto (si se deja
vacío se arma solo con el número de factura) y un mensaje adicional opcional.
El correo incluye número de factura, fecha, subtotal, impuestos, total y número
de autorización, y pide al cliente remitir el comprobante de retención
electrónico.

### Enviar un aviso agrupado (un correo por cliente)

1. Marque las facturas con la casilla de la primera columna (o **Todos**, o la
   casilla de un grupo en las vistas agrupadas).
2. Presione **Aviso agrupado**.
3. Se abre una ventana de revisión con **una fila por cliente**: cuántas
   facturas van, el total y el correo destinatario editable. Puede corregir un
   correo, poner varios, completar el de un cliente que no lo tiene o dejarlo
   vacío para omitirlo. Estos cambios aplican solo a este envío.
4. Opcionalmente escriba un mensaje adicional común y presione **Enviar**.

Cada cliente recibe **un solo correo** con la tabla de todas sus facturas sin
retención y los totales. Un cliente nunca ve facturas de otro cliente.

### Historial de avisos

La columna **Avisos** muestra cuántos avisos lleva la factura y la fecha del
último. El botón de **reloj** abre el detalle: fecha y hora, tipo (individual o
agrupado), correos a los que se envió, asunto y usuario que lo envió.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Año | Sí | Año de emisión de las facturas. |
| Mes | No | Un mes puntual del año elegido. |
| Desde / Hasta | No | Rango de fechas de emisión. Se completa solo desde Año/Mes y se puede ajustar a mano. |
| Aviso por correo | No | Todos, Sin aviso (nunca avisadas) o Con aviso (al menos uno). |
| Cliente | No | Limita el reporte a un cliente. |
| Filtrar tabla | No | Búsqueda instantánea sobre las filas ya cargadas (factura, cliente, RUC, correo). |

## Permisos

- **Ver**: consultar el reporte, exportar y enviar avisos (el aviso no modifica
  la factura; es una comunicación al cliente).
- **Acceso total**: ve las facturas de **toda la empresa**. Sin acceso total solo
  ve y avisa las facturas que **el propio usuario creó**.
- El nivel 3 (superadministrador) siempre ve todo.

## Reglas de negocio

- Solo entran facturas en estado **autorizado**. Borradores, anuladas y
  rechazadas no aparecen.
- Solo se consideran facturas del **ambiente actual** de la empresa (pruebas o
  producción).
- Una factura se considera **con retención** si existe una retención de venta
  registrada desde esa factura, o si alguna línea de una retención (por ejemplo
  la recibida electrónicamente del SRI) apunta a su número como documento de
  sustento. El número se compara normalizado a 15 dígitos, así que
  `001-001-1120` y `001001000001120` son la misma factura.
- Al enviar un aviso, el sistema **vuelve a comprobar** que la factura siga sin
  retención. Si entre la consulta y el envío alguien registró la retención, la
  factura se omite y el resultado lo informa.
- Máximo **300 facturas** por envío agrupado.
- El aviso **no cambia nada en la factura**: no la marca, no la cobra, no crea
  documentos. Solo queda registrado el envío para control.
- Cada envío exitoso queda en el historial de avisos de la factura y en la
  auditoría del sistema (usuario, fecha, correos, asunto).

## Integraciones con otros módulos

- **Retenciones de venta**: cuando allí se registra la retención (manual o
  electrónica), la factura desaparece de este reporte automáticamente.
- **Cuentas por Cobrar**: comparte el cálculo de "lo retenido por factura"; los
  dos módulos siempre coinciden.
- **Facturas de venta**: clic sobre una fila abre el panel lateral con el
  detalle de la factura (requiere permiso de ver en Facturas de Venta).
- **Configuración de correo de la empresa**: los avisos salen por el mismo
  correo saliente que el resto de documentos.

## Exportar

**PDF** y **Excel** con el listado completo según los filtros aplicados
(sin el tope de filas de la pantalla), incluyendo la columna de avisos y último
aviso.

## Errores frecuentes

- **"No se pudo enviar el correo. Verifique la configuración de correo de la
  empresa"**: la empresa no tiene correo saliente configurado o las
  credenciales fallan. Revíselo en Configuración de la empresa → Correo.
- **"La factura no está disponible… ya registra un comprobante de
  retención"**: alguien registró la retención después de cargar el reporte.
  Presione Buscar para actualizar.
- **Aparecen facturas que no deberían llevar retención** (consumidor final,
  personas naturales no obligadas a retener): es normal, el reporte lista toda
  factura autorizada sin retención. Use el buscador de **Cliente** o el filtro
  de la tabla para centrarse en los clientes que sí retienen.
- **Una factura con retención sigue apareciendo**: el número de sustento de la
  retención no coincide con el de la factura (otro establecimiento o punto de
  emisión). Verifíquelo en Retenciones de venta; también estará sin descontar
  en Cuentas por Cobrar.
- **Cliente sin correo**: complete el correo en la ficha del cliente o
  escríbalo directamente en la ventana de envío.

## Historial de cambios

- **1.0** — Versión inicial: listado de facturas sin retención por año, mes
  o rango de fechas; vistas por factura, por cliente y por mes; aviso
  por correo individual y agrupado por cliente; historial de avisos; exportación
  a PDF y Excel.
