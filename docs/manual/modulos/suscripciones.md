---
titulo: Suscripciones
resumen: Cobros recurrentes a clientes (mensual, trimestral, anual…): qué se factura, cada cuánto, cómo se cobra y qué facturas se le han emitido al cliente.
categoria: Ventas
ruta_modulo: modulos/suscripciones
tipo: modulo
visibilidad: todos
etiquetas: suscripciones, suscripcion, cobro recurrente, facturacion recurrente, factura recurrente, mensualidad, pension, plan mensual, membresia, renovacion, periodicidad, proximo cobro, generar documentos, generar facturas, facturacion automatica, facturas del cliente, facturas emitidas, historial de facturas, detalle de facturas, recibos del cliente, que le facture, saldo del cliente, facturas pendientes, facturas pagadas, facturas abonadas, cobro con tarjeta, debito automatico, nuvei, kushki, aviso de vencimiento, imprimir, impresora, excel, exportar, resumen de valores, total por periodicidad, proyeccion anual, ingresos recurrentes, iva por tarifa, resumen por concepto, detalle por cliente, que se le factura a cada cliente, items por cliente, informacion adicional en excel, resumen en pdf, detalle por cliente en pdf
version: 1.11
orden: 0
estado: activo
---

El módulo de **Suscripciones** guarda los cobros que se repiten: a qué cliente, qué
productos o servicios, cada cuánto y con qué documento (factura o recibo de venta).
Con esa información el sistema genera los documentos de cada período, a mano o de
forma automática, y desde la misma suscripción se ven las facturas que ya se le
emitieron al cliente, lo cobrado y lo que debe.

## Qué es y para qué sirve

Sirve para todo lo que se cobra de forma periódica: planes de un sistema,
mantenimientos, pensiones, arriendos, membresías, etc. Cada suscripción pertenece a
la empresa activa y define:

- el **cliente** y los **productos o servicios** que se le facturan en cada período,
  con su cantidad, precio e IVA;
- la **periodicidad** (diaria, semanal, quincenal, mensual, trimestral, semestral,
  anual o bianual) y la fecha del **próximo cobro**;
- el **documento** que se emite (Factura de Venta o Recibo de Venta) y la **forma de
  cobro** (crédito o tarjeta).

Para dar de alta muchas suscripciones a la vez existe la
[Carga de Suscripciones por Excel](modulos/carga-suscripciones).

## Requisitos previos

- El **cliente** y los **productos o servicios** deben existir en la empresa (se
  pueden crear desde la propia suscripción con los botones de la barra superior).
- Para generar documentos: una **serie** (establecimiento y punto de emisión) activa
  y su secuencial de Facturas o Recibos de venta configurado.

## Cómo se usa

1. Pulse **Nueva**.
2. En la pestaña **Detalle suscripción** busque el cliente por RUC o razón social,
   elija el comprobante, las fechas y la periodicidad. El **próximo cobro** se
   calcula solo a partir de la fecha de inicio (puede cambiarlo).
3. Agregue los productos o servicios con **Agregar línea** (cantidad, precio e IVA).
   Los totales se calculan igual que en la factura.
4. En la pestaña **Forma de pago** elija crédito o tarjeta y, si quiere, escriba
   observaciones.
5. Pulse **Guardar**. Para modificarla, haga clic en su fila del listado.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Cliente | Sí | A quién se le factura la suscripción. |
| Estado | Sí | Activo, Pausado, Suspendido o Cancelado. Solo las **activas** generan documentos. |
| Comprobante | Sí | Factura de Venta o Recibo de Venta. |
| Fecha inicio | Sí | Desde cuándo rige la suscripción. |
| Fecha fin | No | Hasta cuándo; debe ser posterior al inicio. Vacía = sin fin. |
| Periodicidad | Sí | Cada cuánto se cobra. |
| Próximo cobro | Sí | Fecha del siguiente período por facturar. Avanza sola cada vez que se genera el documento. |
| Productos / servicios | Sí | Al menos una línea con producto, cantidad mayor a cero y precio no negativo. |
| Información adicional | No | Pares concepto / detalle que se copian a cada documento generado. |
| Forma de cobro | Sí | Crédito (pago manual) o Tarjeta (cobro automático con Kushki o Nuvei). |
| Observaciones | No | Notas internas. |

## Facturas del cliente (pestaña Facturas)

La pestaña **Facturas** del modal muestra las **facturas y recibos de venta emitidos
al cliente** de la suscripción, del más reciente al más antiguo. Es solo de consulta:
no modifica nada.

Cada fila es un documento, con su fecha, número, los productos o servicios que
incluye, su estado (borrador, autorizado, anulado…), el total, lo **cobrado**, el
**saldo** y el **estado de pago**:

- **Pagada**: el saldo llegó a cero.
- **Abonada**: tiene cobros, retenciones o notas de crédito, pero aún debe.
- **Pendiente**: todavía no se le ha aplicado nada.

**Haga clic en una fila** para ver el detalle del documento: código, descripción,
cantidad, precio unitario, descuento, subtotal e IVA de cada línea, los totales y de
dónde sale lo cobrado (cobros, retenciones, notas de crédito). El botón **Ver detalle
de todos** despliega todas las filas de la página a la vez, y el ícono rojo de cada
fila descarga el PDF del documento.

Al pie, el resumen suma **todos** los documentos del filtro (no solo los de la
página): cantidad de documentos, total emitido, cobrado y saldo pendiente (entre
paréntesis, cuántos documentos tienen saldo).

### Del cliente o de esta suscripción

- **Del cliente** (por defecto): todos los documentos del cliente, los haya generado
  esta suscripción o no. Los que generó esta suscripción llevan el ícono de
  suscripción junto al número.
- **De esta suscripción**: solo los documentos que generó esta suscripción. Se activa
  una vez guardada la suscripción.

La pestaña muestra los documentos del cliente elegido en el formulario: si cambia de
cliente, al volver a la pestaña se actualiza. En una suscripción nueva, elija primero
el cliente.

### Buscar en las facturas

El buscador acepta texto libre (número, producto o servicio, estado, fecha como se ve
en la tabla o un monto) y filtros:

| Filtro | Ejemplo | Qué hace |
|--------|---------|----------|
| `fecha:` | `fecha:2026-08` | Documentos de ese mes (también un día, un año o un rango `2026-01..2026-03`). |
| `pago:` | `pago:pendiente` | Por estado de pago: `pendiente`, `abonada`, `pagada` (acepta lista: `pago:pendiente,abonada`). |
| `estado:` | `estado:borrador` | Por estado del documento. |
| `tipo:` | `tipo:recibo` | Solo facturas o solo recibos. `-tipo:recibo` los excluye. |
| `total:` / `saldo:` | `saldo:>0` | Por monto: `>`, `<`, `>=`, `<=` o rango `100..500`. |
| `producto:` | `producto:soporte` | Documentos que incluyen ese producto o servicio. |

Haga clic en los títulos **Fecha**, **Documento**, **Total**, **Cobrado** o
**Saldo** para ordenar.

### Qué documentos se muestran

- Los de la **empresa activa** y del **ambiente actual** (pruebas o producción), igual
  que el listado de Facturas de Venta.
- Si el cliente está registrado dos veces, con su **cédula** y con su **RUC**, se
  muestran los documentos de ambas fichas.
- El saldo se calcula igual que en **Facturas de Venta**: a la factura se le restan
  los cobros, las notas de crédito y las retenciones; al recibo, sus cobros.
- Los documentos **anulados** y los recibos ya **facturados** (convertidos en factura)
  se listan tachados, pero no suman al total ni tienen saldo.

## Generar documentos

Los documentos de cada período se pueden generar de dos formas:

- **A mano**: botón **Generar Documentos** del listado. Elija la **serie** y la
  **periodicidad** a ejecutar; opcionalmente un texto que se agrega a cada ítem y una
  línea de información adicional. Se generan los documentos de las suscripciones
  **activas** de esa periodicidad cuyo **próximo cobro ya venció**, dentro de sus
  fechas de inicio y fin.
- **Automáticamente**: automatización **Suscripciones → Generar facturación**, que
  procesa todas las periodicidades y se pone al día con los períodos atrasados.

Cada documento generado nace en **borrador** (la factura se envía al SRI con la
automatización de Facturas de venta), avanza el próximo cobro de la suscripción y
queda enlazado a ella: por eso aparece marcado en la pestaña **Facturas**.

En el texto del ítem y en la información adicional se pueden usar marcadores del
período facturado, por ejemplo `{mes}`, `{MES}`, `{anio}`, `{mes_anio}`, `{fecha}` y
sus equivalentes del período anterior (`{mes_ant}`, `{anio_ant}`…).

## Cobro con tarjeta

Con la forma de cobro **Tarjeta** se elige la pasarela. Con **Nuvei**, al crear la
suscripción se envía al cliente un enlace para registrar su tarjeta (se puede
reenviar desde la pestaña **Forma de pago** o usar una tarjeta que el cliente ya
registró). El cargo lo hace la automatización **Cobrar suscripciones (Nuvei)**, aparte
de la generación del documento.

## Buscar y filtrar el listado

El cuadro de búsqueda busca en las columnas del listado, en los productos de cada
suscripción, en sus observaciones e información adicional y en los números de los
documentos generados. El botón del embudo abre los filtros (próximo cobro, estado,
periodicidad, comprobante, forma de cobro, monto, etc.). El listado se exporta a PDF
y Excel con los filtros aplicados.

Las **tres hojas** del Excel (listado, Resumen y Detalle por cliente) toman exactamente
las suscripciones que deja el **filtro de búsqueda** vigente al momento de pulsar el
botón, y cada hoja indica arriba el filtro aplicado y cuántas suscripciones incluye. El
filtro elige suscripciones: de cada una salen todos sus ítems (buscar `Honorarios` trae
las suscripciones que tienen ese servicio, con todo lo que se les factura).

### Resumen de valores (Excel y PDF)

El **PDF** del listado trae, en hojas aparte al final, el mismo **Resumen de valores**
que el Excel (mismos bloques y mismos totales) y el **Detalle por cliente**, también según
el filtro de búsqueda. En el PDF el detalle va compacto: una fila por cliente con su
identificación y correo, debajo una línea por ítem (periodicidad, estado, próximo cobro,
concepto, cantidad, precio, subtotal, tarifa, IVA, total y proyección anual), la
información adicional en una línea debajo de cada suscripción, el total por cliente y
el total general.

#### Hoja «Resumen» del Excel

El Excel trae una segunda hoja, **Resumen**, con los valores de las mismas
suscripciones exportadas (respeta la búsqueda y los filtros). Los valores son los de
**un cobro** de cada suscripción (cantidad × precio, más su IVA) y tiene cuatro bloques:

- **Por periodicidad**: número de suscripciones, subtotal, IVA y total por cobro, cuántos
  cobros genera en un año y la **proyección mensual y anual**.
- **Por concepto**: cada producto o servicio con sus suscripciones, cantidad, subtotal,
  IVA, total y proyección anual.
- **Conceptos por periodicidad**: el detalle anterior separado por periodicidad, con su
  tarifa de IVA y un subtotal por periodicidad.
- **Por tarifa de IVA**: base imponible e IVA de cada tarifa (15 %, 0 %, exento…), por
  cobro y proyectados al año.

La proyección anual multiplica el total de cada cobro por los cobros del año (mensual
12, trimestral 4, anual 1, semanal 52, quincenal 24, diario 365); no considera fechas de
inicio o fin ni el estado de la suscripción: si solo quiere las activas, filtre
`estado:activo` antes de exportar.

### Hoja «Detalle por cliente» del Excel

La tercera hoja lista, cliente por cliente (en orden alfabético), **cada ítem que se
factura** en sus suscripciones: periodicidad, estado, comprobante, próximo cobro,
código y concepto, cantidad, precio unitario, subtotal, tarifa y valor del IVA, total
por cobro y proyección anual. Al final van las columnas de **información adicional**
de la suscripción: un par *Concepto* / *Detalle* por cada línea (si alguna suscripción
tiene varias, se numeran: Info adicional 1, 2…). Cada cliente cierra con una fila **Total** (con el número
de suscripciones que tiene) y al final va el **TOTAL GENERAL**, que coincide con los
totales de la hoja Resumen. Una suscripción sin productos aparece como *Sin ítems
registrados*.

## Permisos

- **Ver**, **crear**, **modificar** y **eliminar** según el permiso del módulo.
- Sin **acceso total**, el usuario solo ve y gestiona las suscripciones que él
  registró.
- La pestaña **Facturas** aparece solo si el usuario puede ver **Facturas de Venta**
  o **Recibos de Venta**, y muestra solo los tipos de documento que puede ver. Sin
  acceso total en uno de esos módulos, de ese tipo solo ve los documentos que él
  registró (igual que en el listado del módulo); la nota al pie de la pestaña lo
  indica.

## Reglas de negocio

- Una suscripción necesita cliente, periodicidad, fecha de inicio y al menos un
  producto o servicio; la fecha de fin, si se indica, debe ser posterior al inicio.
- Con forma de cobro **Tarjeta** es obligatorio elegir la pasarela.
- Solo las suscripciones **activas** generan documentos.
- Eliminar una suscripción no borra los documentos que ya generó.

## Integraciones con otros módulos

- **Facturas de Venta / Recibos de Venta**: reciben los documentos generados (en
  borrador). La pestaña Facturas los consulta con las mismas reglas de saldo.
- **Ingresos**, **Retenciones** y **Notas de Crédito**: lo que se registra ahí se ve
  como cobrado en la pestaña Facturas.
- **Automatizaciones**: generación de documentos, cobro con Nuvei y avisos de
  vencimiento por correo o WhatsApp.
- **Empresa**: la tarjeta de suscripción y vigencia del sistema lee la suscripción
  del cliente.

## Errores frecuentes

- **No veo la pestaña Facturas**: le falta permiso para ver Facturas de Venta o
  Recibos de Venta, o la ocultó desde el menú de pestañas del modal (ícono de
  configuración a la derecha de las pestañas).
- **La pestaña Facturas está vacía o le faltan documentos**: sin acceso total en
  Facturas o Recibos de Venta solo se ven los que usted registró (los generados por
  la automatización los registra otro usuario). Tampoco aparecen los documentos de
  otro ambiente (pruebas / producción).
- **"De esta suscripción" está deshabilitado**: la suscripción todavía no se guardó.
- **Una factura aparece como Pendiente aunque el cliente pagó**: el cobro no se ha
  registrado en Ingresos, o se registró en otro documento.

## Historial de cambios

- **1.11** — El **PDF** del listado incluye también el **Detalle por cliente**: lo que se
  le factura a cada cliente, con IVA, información adicional y totales.

- **1.10** — El **PDF** del listado incluye al final el **Resumen de valores** (por
  periodicidad, por concepto, conceptos por periodicidad y por tarifa de IVA) y muestra
  el filtro de búsqueda aplicado.

- **1.9** — La hoja **Detalle por cliente** del Excel incluye al final la información
  adicional de cada suscripción (concepto y detalle).

- **1.8** — El Excel y el PDF se generan siempre con el filtro de búsqueda vigente al
  pulsar el botón (antes, si se pulsaba justo después de cambiar el filtro, podían salir
  con el anterior). Cada hoja del Excel muestra el filtro aplicado.

- **1.7** — Nueva hoja **Detalle por cliente** en el Excel del listado: cada cliente con
  lo que se le factura, línea por línea, con subtotal, IVA, total y proyección anual.

- **1.6** — El Excel del listado trae una nueva hoja **Resumen** con los valores por
  periodicidad, por concepto, conceptos por periodicidad y por tarifa de IVA, con
  proyección mensual y anual.

- **1.5** — Con el IVA configurado **al subtotal**, el IVA de cada línea de la factura
  generada se reajusta para que su suma sea exactamente el IVA sobre el
  subtotal. Antes, en facturas con muchas líneas, el XML y el PDF podían no
  cuadrar con el total.
- **1.4** — Los botones y enlaces de **PDF** de los documentos preguntan ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.3** — Corregido: al **enviar al cliente el enlace para registrar su tarjeta**, el
  cuadro del correo no aceptaba texto —se veía, pero al escribir no pasaba nada—. Ya se
  puede escribir la dirección con normalidad.

- **1.2** — **Una factura de suscripción con $0.01 de saldo queda como
  Abonado, no Pagado**, y sigue contando en el total de documentos con saldo.
  Mismo criterio que el resto del sistema: hay saldo mientras quede al menos un
  centavo.


- **1.1** — Nueva pestaña **Facturas** en el modal: facturas y recibos de venta
  emitidos al cliente, con su estado de pago, saldo y resumen; clic en una fila para
  ver el detalle de productos y servicios; filtro **Del cliente / De esta
  suscripción**, buscador con filtros, orden, paginación y PDF de cada documento.
- **1.0** — Versión inicial del manual.
