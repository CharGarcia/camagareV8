---
titulo: Suscripciones
resumen: Cobros recurrentes a clientes (mensual, trimestral, anual…): qué se factura, cada cuánto, cómo se cobra y qué facturas se le han emitido al cliente.
categoria: Ventas
ruta_modulo: modulos/suscripciones
tipo: modulo
visibilidad: todos
etiquetas: suscripciones, suscripcion, cobro recurrente, facturacion recurrente, factura recurrente, mensualidad, pension, plan mensual, membresia, renovacion, periodicidad, proximo cobro, generar documentos, generar facturas, facturacion automatica, facturas del cliente, facturas emitidas, historial de facturas, detalle de facturas, recibos del cliente, que le facture, saldo del cliente, facturas pendientes, facturas pagadas, facturas abonadas, cobro con tarjeta, debito automatico, nuvei, kushki, aviso de vencimiento, imprimir, impresora, excel, exportar, resumen de valores, total por periodicidad, proyeccion anual, ingresos recurrentes, iva por tarifa, resumen por concepto, detalle por cliente, que se le factura a cada cliente, items por cliente, informacion adicional en excel, resumen en pdf, detalle por cliente en pdf, pdf de la suscripcion, imprimir suscripcion, contrato, ficha de la suscripcion, detalle de la suscripcion en pdf, devengado, devengo, ingreso diferido, ingresos diferidos, ingreso anticipado, cobro por adelantado, mes caido, mes vencido, facturacion vencida, niif 15, seccion 23, reconocimiento de ingresos, provision de ingresos, ingresos por facturar
version: 1.24
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
2. En la pestaña **Detalle suscripción** busque el cliente por RUC o razón social y,
   a su derecha, elija el comprobante. Debajo: modalidad de cobro, reconocimiento del
   ingreso, fechas y periodicidad. El **próximo cobro** se calcula solo a partir de la
   fecha de inicio (puede cambiarlo). El **Estado** está a la derecha de la barra
   superior del modal.
3. Agregue los productos o servicios con **Agregar línea** (cantidad, precio e IVA).
   Puede escribir el precio sin impuesto o el **precio con impuesto** (columna *P. con
   Imp.*): el otro se calcula con el IVA de la línea, igual que en la factura. Los
   totales se calculan igual que en la factura.

La **estrella** junto a Comprobante, Modalidad de cobro, Reconocimiento del ingreso y
Periodicidad guarda ese valor como favorito: cada suscripción nueva lo trae ya elegido.
4. En la pestaña **Forma de pago** elija crédito o tarjeta y, si quiere, escriba
   observaciones.
5. Pulse **Guardar**. Para modificarla, haga clic en su fila del listado.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Cliente | Sí | A quién se le factura la suscripción. |
| Inmueble | No (solo condominios) | Inmueble del condominio cuya expensa cobra esta suscripción. Aparece solo si la empresa tiene activo el módulo Condominios. Lista los inmuebles que **paga** el cliente; si paga uno solo, se asocia solo al guardar. Al generar el recibo o la factura, el inmueble, su propietario y el período salen en la **Información adicional** del documento (RIDE, XML y correo). |
| Estado | Sí | Activo, Pausado, Suspendido o Cancelado. Solo las **activas** generan documentos. |
| Comprobante | Sí | Factura de Venta o Recibo de Venta. |
| Fecha inicio | Sí | Desde cuándo rige la suscripción. |
| Fecha fin | No | Hasta cuándo; debe ser posterior al inicio. Vacía = sin fin. |
| Periodicidad | Sí | Cada cuánto se cobra. |
| Próximo cobro | Sí | Fecha del siguiente período por facturar. Avanza sola cada vez que se genera el documento. |
| Modalidad de cobro | Sí | **Por adelantado**: cada documento cubre el período que empieza en el próximo cobro. **Mes caído (vencido)**: cubre el período que termina el día anterior al próximo cobro (se factura lo ya prestado). |
| Reconocimiento del ingreso | Sí | **Al facturar**: todo el ingreso se registra el día de la factura. **Durante el período**: el ingreso de los servicios se reconoce mes a mes (ver *Reconocimiento del ingreso*). Al crear se propone *Durante el período* para las periodicidades de un mes o más. |
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

- **A mano**: botón **Generar Documentos** del listado (junto a PDF y Excel). Elija la **serie** y la
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

## Reconocimiento del ingreso (devengado)

Las normas contables (NIIF 15 y NIIF para PYMES, Sección 23) piden reconocer el
ingreso de un servicio **a medida que se presta**, no el día en que se factura. Una
suscripción anual facturada en enero es ingreso de enero a diciembre, un mes a la vez.

Con **Reconocimiento del ingreso = Durante el período**:

- **Cobro por adelantado**: de cada documento, la parte que corresponde a **meses
  posteriores** al de la factura queda como **Ingreso diferido** (un pasivo) y pasa
  al ingreso mes a mes. Lo del mes de la factura se reconoce de inmediato.
- **Mes caído**: el servicio ya prestado y aún no facturado se registra al cierre del
  mes como **Ingreso devengado por facturar**; la factura del período lo cancela.
- Solo se difieren los **servicios**. Los **bienes** se reconocen siempre al facturar.
- Las periodicidades diaria, semanal y quincenal se reconocen al facturar.
- El **IVA no cambia**: se declara en el mes de la factura, por el valor completo.

Las dos cuentas (Ingresos diferidos e Ingresos devengados por facturar) se
configuran en [Configuración Contable](modulos/configuracion-contable), tipo de asiento
**Suscripciones - Devengo**.

### Pestaña Devengo

Muestra el cronograma de la suscripción: una fila por mes y documento, con el monto,
su estado (**Por devengar**, **Devengado**, **Facturado** o **Anulado**) y el asiento que
lo registró. Arriba, los totales diferido, devengado, por devengar y anulado. Es solo
de consulta.

## Devengar el mes (asiento mensual)

Lo diferido pasa al ingreso con un **asiento consolidado por mes** (fecha: último día
del mes):

| Cuenta | Debe | Haber |
|---|---|---|
| Ingresos diferidos por suscripciones | lo diferido del mes | |
| Ingresos devengados por facturar | provisión de mes caído | |
| Ingreso de cada servicio | | la suma |

La cuenta de ingreso de cada servicio es la misma que usa su factura (por cliente,
producto, categoría, marca, tipo de producción o General).

- **Es automático**: el sistema lo hace solo todos los días, en todas las empresas, sin
  configurar nada. Procesa hasta el mes anterior y se pone al día con los meses que
  hayan quedado pendientes. Corre antes que las automatizaciones de la empresa, así que
  el día 1 la provisión de mes caído ya existe cuando *Generar facturación* emite la
  factura que la cancela. Si a la empresa le falta una cuenta o el período está cerrado,
  ese mes queda pendiente y se reintenta al día siguiente.
- Para **adelantar** un mes (por ejemplo, el mes en curso al cerrarlo), ver su vista
  previa o **revertirlo**: [Reporte de Ingresos Diferidos](modulos/reporte_ingresos_diferidos)
  → **Devengo del mes**.

Reglas:

- Solo se devenga lo de facturas o recibos que **ya tienen asiento**. Lo de documentos
  en borrador o sin asiento espera y se devenga en una corrida posterior (aparece
  como *Esperando asiento*).
- Se puede correr más de una vez en el mes: cada corrida toma lo que quedó pendiente.
- **Mes caído**: al cierre de cada mes ya prestado y aún no facturado se provisiona su
  porción (base del servicio ÷ meses de la periodicidad). Cuando se genera la factura
  del período, la provisión queda **Facturada** y la factura acredita *Ingresos
  devengados por facturar* en vez del ingreso.
- Con el interruptor **Suscripciones (devengo de ingresos)** apagado en *Módulos que
  contabilizan*, no se difiere nada nuevo ni se provisiona el mes caído; lo ya
  diferido se sigue devengando.

## Saldos de ingresos diferidos

Los saldos al cierre de un mes (diferido corriente y no corriente, por facturar) y su
cuadre con el mayor se consultan en el
[Reporte de Ingresos Diferidos](modulos/reporte_ingresos_diferidos).

## Apertura: facturas emitidas antes de activar el devengado

Las facturas y recibos que una suscripción emitió **antes** de pasar a *Durante el
período* ya reconocieron todo como ingreso. Para llevar al pasivo la parte de los meses
que faltan: [Reporte de Ingresos Diferidos](modulos/reporte_ingresos_diferidos) → **Apertura**.

- Elija el **mes de corte**: se listan los documentos de suscripciones que hoy reconocen
  durante el período (por adelantado, mensual o mayor), ya contabilizados y sin
  cronograma, que todavía cubren meses posteriores al corte. Lo de los meses hasta el
  corte se queda como ingreso.
- **Registrar apertura** arma su cronograma y registra un asiento al último día del mes
  de corte: Debe ingreso / Haber *Ingresos diferidos*. Desde el mes siguiente, el
  devengo mensual los pasa al ingreso como cualquier otro.
- El período de servicio se toma del documento; en los anteriores al devengado (que no
  lo guardaban) se cuenta desde la **fecha de emisión**.
- **Revertir apertura** la deshace, salvo que ya se haya devengado algún mes de esas
  filas o una nota de crédito las haya tomado.
- Si se vuelve a contabilizar una de esas facturas (Sincronizar, Auditoría Contable), su
  asiento no cambia: el pasivo de la apertura vive en su propio asiento.

Requiere el SQL `database/migrations/20261004_suscripciones_devengo_apertura.sql`.

## Notas de crédito, anulación y edición de documentos con ingreso diferido

- **Nota de crédito** sobre la factura: lo que devuelve de un servicio sale primero de
  lo **aún no devengado**, del último mes hacia atrás (en la pestaña Devengo esos meses
  quedan *Anulado*). Su asiento debita *Ingresos diferidos* por esa parte; solo lo que
  exceda reduce la cuenta de ingreso. Al anular o eliminar la NC, todo vuelve a *Por
  devengar*.
- **Anular o eliminar** la factura o el recibo: el cronograma queda *Anulado*. Si ya
  había meses devengados, se registra con la fecha del día un asiento de **reverso**
  (Debe ingreso / Haber Ingresos diferidos) por ese monto. Las provisiones de mes caído
  que el documento cancelaba vuelven a *por facturar*.
- **Modificar** una factura o recibo en borrador: el cronograma se rehace con las
  líneas nuevas. Si ya tiene meses devengados, no se puede modificar: primero revierta
  esos meses en el Reporte de Ingresos Diferidos → **Devengo del mes**.

## Cobro con tarjeta

Con la forma de cobro **Tarjeta** se elige la pasarela. Con **Nuvei**, al crear la
suscripción se envía al cliente un enlace para registrar su tarjeta (se puede
reenviar desde la pestaña **Forma de pago** o usar una tarjeta que el cliente ya
registró). El cargo lo hace la automatización **Cobrar suscripciones (Nuvei)**, aparte
de la generación del documento.

## PDF de la suscripción

En la barra superior del modal, junto a los botones de crear cliente y producto, el
botón rojo **PDF** genera un documento con el detalle de la suscripción abierta y
pregunta si desea **Imprimir**, **Descargar** o **Ver**. La suscripción debe estar
guardada; si es nueva, primero pulse **Guardar**.

El PDF lleva el **logo** y los datos de la empresa, el número y el estado de la
suscripción, y en orden:

- **Cliente**: nombre, RUC o cédula, correo, teléfono y dirección.
- **Datos de la suscripción**: periodicidad, comprobante, fecha de inicio, fecha de
  fin (o *Indefinida*) y próximo cobro.
- **Productos / servicios** que se facturan en cada cobro, con cantidad, precio, IVA
  y subtotal, y los totales: subtotal por tarifa de IVA, IVA y **total por cobro**.
- **Información adicional**, si la tiene.
- **Cobro y observaciones**: forma de cobro, tarjeta registrada (solo los 4 últimos
  dígitos), observaciones y quién la registró.
- **Historial de cobros**: fecha, factura o recibo generado con su estado, resultado
  del cobro y monto, con el total cobrado (solo cobros exitosos).

El PDF muestra lo que está **guardado**: los cambios sin guardar del formulario no
aparecen.

## Buscar y filtrar el listado

El cuadro de búsqueda busca en las columnas del listado, en los productos de cada
suscripción, en sus observaciones e información adicional y en los números de los
documentos generados. El botón del embudo abre los filtros (próximo cobro, estado,
periodicidad, comprobante, forma de cobro, modalidad de cobro, reconocimiento del ingreso, monto, etc.). El listado se exporta a PDF
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
- **Barra superior**: el ícono de flechas circulares avisa cuántas suscripciones de
  clientes están vencidas o por vencer (próximos 7 días). Lo ve quien tiene asignado
  este módulo en la empresa activa (el superadministrador lo ve siempre).

## Errores frecuentes

- **La factura de una suscripción no genera su asiento («falta Ingresos diferidos por
  suscripciones»)**: la suscripción reconoce el ingreso durante el período y la empresa no
  tiene la cuenta configurada. Asígnela en Configuración Contable → **Suscripciones -
  Devengo**; el asiento se genera solo en la siguiente sincronización.
- **La pestaña Devengo está vacía**: la suscripción reconoce el ingreso al facturar, es de
  mes caído, es diaria/semanal/quincenal, solo factura bienes, o cada documento se emitió
  dentro del mismo mes que cubre (no hay meses futuros que diferir).

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

- **1.24** — Condominios: campo **Inmueble** en la suscripción (solo empresas con el módulo activo), con
  asociación automática cuando el cliente paga un solo inmueble; columna y filtro `inmueble:` en el
  listado; al generar el documento, Inmueble, Propietario y Período van en la Información adicional.
- **1.23** — Nueva columna **Total** en el listado (lo que se cobra en cada período: ítems con su IVA),
  ordenable y también en PDF y Excel.
- **1.22** — Corregido: al abrir una suscripción sin fecha fin (o sin fecha de inicio, comprobante,
  periodicidad o próximo cobro) el modal conservaba el valor de la suscripción abierta antes, y al
  guardar se grababa en esta.

- **1.21** — El botón *Devengar mes* y la *Apertura* salen de Suscripciones: están en el
  [Reporte de Ingresos Diferidos](modulos/reporte_ingresos_diferidos). El listado muestra las columnas
  **Modalidad** y **Reconocimiento** (también en PDF y Excel), con orden y filtro por ambas.
- **1.20** — El devengo mensual es **automático** (todos los días, sin configurar nada); el botón
  *Devengar mes* queda para la vista previa, adelantar o revertir. El reporte de saldos pasa a su
  propio módulo, [Reporte de Ingresos Diferidos](modulos/reporte_ingresos_diferidos).

- **1.19** — Botón **Ingresos diferidos**: saldos corriente / no corriente / por facturar al
  cierre de un mes, conciliación con el mayor y Excel. **Apertura** para las facturas emitidas
  antes de activar el devengado. La carga por Excel acepta Modalidad de cobro y Reconocimiento.

- **1.18** — Notas de crédito, anulación, eliminación y edición de documentos con ingreso
  diferido: la NC sale primero de lo no devengado, anular revierte lo devengado con un
  asiento propio y editar un borrador rehace el cronograma.

- **1.17** — Botón **Devengar mes** y automatización *Devengar ingresos del mes*:
  asiento mensual del ingreso diferido y provisión de mes caído; se puede revertir.
  En el modal: el **Estado** pasa a la barra superior, el **Comprobante** junto al
  buscador de cliente, nueva columna **P. con Imp.** en el detalle y **favoritos** en
  Comprobante, Modalidad de cobro, Reconocimiento del ingreso y Periodicidad. El botón
  **Generar Documentos** pasa junto a PDF y Excel.

- **1.16** — Reconocimiento del ingreso por devengado (NIIF 15): campos **Modalidad de
  cobro** (por adelantado o mes caído) y **Reconocimiento del ingreso**, cronograma
  mensual de los servicios facturados por adelantado y nueva pestaña **Devengo**. Cada
  documento generado guarda el período de servicio que cubre. Con mes caído, el último
  período (el que contiene la fecha de fin) ahora sí se factura. El asiento de la factura o
  recibo acredita a **Ingresos diferidos** la parte de meses futuros (y a *Ingresos
  devengados por facturar* la provisión de mes caído que cancela), y al ingreso solo el resto.

- **1.15** — El PDF de la suscripción calcula el IVA con la configuración de
  facturación (al subtotal o línea por línea), igual que la pantalla (antes, siempre
  línea por línea). Ver [Cómo se calcula el IVA](conceptos/calculo-iva).
- **1.14** — En la ventana de filtros, la pestaña *Detalles* se llama ahora
  **Búsqueda por detalle**, y la ventana ya no tiene barra de desplazamiento
  vertical propia: se muestra completa.
- **1.13** — El aviso de suscripciones vencidas o por vencer de la barra superior depende
  solo del permiso sobre Suscripciones: antes exigía además el módulo Empresa y quien
  tenía Suscripciones asignado sin Empresa no lo veía.
- **1.12** — Botón **PDF** en la barra superior del modal: documento con logo y el
  detalle de la suscripción (cliente, datos, productos con totales, información
  adicional, forma de cobro e historial de cobros).

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
