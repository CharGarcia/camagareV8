# Condominios — Cuotas extraordinarias a plazos

> Documento de diseño para revisión. **No es el manual del usuario** (ese se actualiza en
> `docs/manual/modulos/condominios-config.md` al implementar) y no se publica en
> `/documentacion`. Fecha: 17-11-2026. Estado: **borrador para aprobación**.
> Continúa `docs/diseno/condominios-fase1.md` §6.7 («Aportes con meta»).
> SQL: `database/migrations/20261117_condominios_cuotas_extraordinarias.sql`.

## 1. Objetivo

Controlar dentro de `modulos/condominios-config` las **cuotas extraordinarias aprobadas en
asamblea** que se pagan a plazos. Caso guía: el edificio decide pintar; varios condóminos ya
entregaron un anticipo y el saldo se divide en 24 cuotas mensuales.

El sistema debe saber, por proyecto y por inmueble: cuánto le toca a cada uno, cuánto anticipó,
en qué cuota va, qué se emitió, qué se cobró y cuánto falta. Además debe dejar de cobrar sola
al llegar a la última cuota.

Lo que existe hoy no lo resuelve:

| Hoy | Problema |
|---|---|
| **Generar cobro → Emitir ahora** | Es un cobro único: no reparte una meta ni lleva cuotas. |
| **Generar cobro → Agregar a suscripciones** | La línea queda para siempre. `suscripciones.fecha_fin` corta toda la suscripción (también la alícuota), no una línea. |
| «Cuota extraordinaria» de la pestaña Alícuota | Es un recargo **permanente** (% o fijo en cada cuota), sin meta ni plazos. |

## 2. Decisiones del usuario (17-11-2026)

| # | Tema | Decisión |
|---|---|---|
| 1 | Anticipos ya recibidos | Están registrados como **Anticipo de clientes** (ingreso con opción `ANTICIPO_CLIENTE`, pasivo). Se aplican al plan, no solo se restan. |
| 2 | Reparto de la meta | **Cuotas iguales** o **por % de alícuota**, con ajuste manual por inmueble. |
| 3 | Documento | Por cada cuota extraordinaria: **en el mismo recibo/factura de la alícuota** (que emite Suscripciones) **o en un documento nuevo aparte**. |
| 4 | Plazo | **Cada condómino elige** su número de cuotas entre los permitidos. |
| 5 | Venta del inmueble | Se elige **seguir con la deuda** (pasa al nuevo dueño) **o liquidarla** (documento al vendedor por el saldo). |
| 6 | Quién paga | **Por defecto el propietario**, con opción de que pague el arrendatario (por cuota extraordinaria y por inmueble). |

## 3. Modelo de datos

No hay tablas nuevas: se completan las que ya creó `20261005_condominios.sql` y que ningún
código usaba. Todas son operativas (`id_empresa`, auditoría, eliminación lógica).

### 3.1 `condominios_aportes` — la cuota extraordinaria

Se usa con `tipo = 'meta'`. La fila `permanente` queda para el fondo de reserva (§9).

| Campo | Uso |
|---|---|
| nombre, descripcion, id_producto | «Pintura del edificio». El producto (servicio de Productos) da nombre de línea, IVA y cuenta contable. |
| acta, fecha_acta | **Nuevo.** Respaldo de la asamblea; sale en la información adicional. |
| meta_total | Monto total a recaudar (obligatorio en tipo meta). |
| calculo | Reparto: `iguales` (**nuevo**) = meta ÷ inmuebles incluidos; `porcentaje` = meta × % alícuota ÷ Σ % de los incluidos; `manual`. Los otros valores existentes no se ofrecen en esta pantalla. |
| plazos_permitidos, plazo_defecto | `[1,6,12,24]` y 24. `plazo_defecto` es **nuevo**. |
| primer_periodo | **Nuevo.** AAAAMM de la primera cuota, por defecto para todos. |
| donde_cobro | **Nuevo.** `junto` (con la alícuota) \| `aparte`. |
| pagador_defecto | **Nuevo.** `propietario` \| `arrendatario`. |
| tipo_comprobante, id_punto_emision | **Nuevo.** Comprobante y serie de los documentos propios: cuotas aparte, abono inicial y liquidación. |
| id_forma_anticipo | **Nuevo.** Forma de cobro tipo ANTICIPO con la que se aplican los anticipos. |
| recargos_plazo, beneficio_anticipado_* | Ya existen; nacen vacíos/apagados. |
| estado | `activo` \| `cerrado`. |

### 3.2 `condominios_aportes_unidades` — el plan de cada inmueble

| Campo | Uso |
|---|---|
| monto_asignado, ajuste_manual | Lo que le toca según el reparto; `ajuste_manual` (**nuevo**) indica que se escribió a mano. |
| plazo_elegido, recargo_aplicado, monto_total | `monto_total = asignado + recargo`. |
| anticipo_aplicado | **Nuevo.** Parte del anticipo del cliente que se usa en este plan. |
| saldo_plazos, cuota_valor, cuota_ultima | **Nuevo.** `saldo_plazos = monto_total − anticipo`; cuota = saldo ÷ plazo, y la última absorbe los centavos. |
| pagador, id_cliente | **Nuevo.** Quién paga y su cliente resuelto. |
| tipo_documento_abono, id_documento_abono, id_ingreso_abono | **Nuevo.** Documento del abono inicial y el ingreso que lo cruza con el anticipo. |
| primer_periodo | Primera cuota de este inmueble. |
| estado | `pendiente` \| `en_curso` \| `completo` (todas las cuotas emitidas) \| `liquidado` \| `cancelado`. |
| fecha_liquidacion, motivo_liquidacion, id_cliente_liquidacion | **Nuevo.** `pago_anticipado` \| `venta`. |

**Lo pagado no se guarda en el plan**: sale de Cuentas por Cobrar (§6), igual que hoy en toda la cartera.

### 3.3 `condominios_cargos` — una fila por cuota

Al **activar el plan** de un inmueble se crean de una vez todas sus cuotas (`concepto = 'aporte'`,
`subtipo = 'cuota'`, `numero_cuota` 1..N, `total_cuotas` N, `periodo` mes a mes, `estado = 'pendiente'`).
Así el cronograma es visible desde el primer día y la emisión solo marca qué salió.

| Campo nuevo | Uso |
|---|---|
| subtipo | `cuota` \| `abono` (abono inicial con anticipo) \| `liquidacion`. |
| numero_cuota, total_cuotas | «5/24». Índice único por plan + número: una cuota nunca existe dos veces. |
| id_cliente | A quién se cobra; cambia si hay venta o cambio de pagador (§7.5). |
| donde_cobro | Copiado del aporte al crear el plan. |
| id_suscripcion / id_emision_item | Por dónde salió: junto a la alícuota o en la emisión aparte. |

Los campos que ya existían cubren el resto: `tipo_documento`, `id_documento`, `fecha_emision` y `estado`.

### 3.4 `condominios_emisiones`

Se agregan `id_aporte`, `periodo` y `forma_valor = 'cargos'`: el valor de cada ítem es la suma
de las cuotas pendientes de ese inmueble hasta el período elegido. Las cuotas emitidas guardan
`id_emision_item`.

## 4. Flujos

### 4.1 Crear la cuota extraordinaria

Pestaña nueva **Cuotas extraordinarias** → **Nueva**. Se llenan nombre, producto, acta y fecha,
meta, reparto, plazos permitidos y por defecto, primer mes, dónde se cobra, quién paga y el
comprobante y la serie de los documentos propios. Se guarda como **activa sin planes**: todavía
no se crea ninguna cuota.

### 4.2 Armar los planes (vista previa)

**Asignar inmuebles** muestra una fila por inmueble activo, todos incluidos por defecto. Se
pueden destildar, por ejemplo, parqueaderos y bodegas si el acta no los incluye.

| Inmueble | Paga | Asignado | Anticipo disponible | Anticipo a aplicar | Plazo | Cuota | Desde |
|---|---|---|---|---|---|---|---|

- **Asignado** se calcula con el reparto. Al editarlo a mano, la fila queda como ajuste manual y
  el pie muestra la diferencia contra la meta. Para guardar, la suma debe igualar la meta, salvo
  los centavos, que se asignan al inmueble de mayor monto (como en §6.2 de la fase 1).
- **Anticipo disponible** = saldo de anticipo del cliente pagador
  (`FormaPagoRepository::getSaldoAnticipo`). **Anticipo a aplicar** viene en 0 y el usuario
  escribe cuánto corresponde a esta obra, porque el cliente puede tener anticipos por otros
  motivos. Hay un botón «usar todo el disponible» por fila y otro general.
- **Plazo** es un selector con los plazos permitidos. Cambiarlo recalcula la cuota en el acto.
- **Paga**: propietario o arrendatario (este último solo si el inmueble lo tiene).

**Activar planes** (servidor, una transacción por lote; guardado único `condominios_cuota_extra`):

1. Revalida la meta y, **bajo candado** `pg_advisory_xact_lock(hashtext('anticipo:'||empresa||':'||cliente))`,
   que el anticipo a aplicar ≤ saldo de anticipo (§8 CLAUDE.md: leer → calcular → escribir).
2. Por inmueble: graba el plan y crea sus N cargos `pendiente`.
3. Si `anticipo_aplicado > 0`: crea el cargo `abono` y emite **un documento** (recibo o factura
   del aporte) por ese valor, a crédito. Luego registra **un ingreso** que lo cobra con la forma
   **ANTICIPO** (`IngresoService::crear`, mismo patrón que `CobroAutomaticoClienteService`).
   El efecto contable:
   - documento: Debe CxC / Haber cuenta del producto;
   - ingreso: Debe Anticipo de clientes / Haber CxC.

   Neto: el pasivo de anticipo pasa a la cuenta del producto y el documento queda saldado.
4. `log_sistema` con el plan completo.

Una factura de abono se envía al SRI **después del commit**, como en la emisión en bloque.

### 4.3 Cuotas «junto» a la alícuota

El enganche va en `SuscripcionFacturacionService::generarUnPeriodo()`, el punto único que usan
el handler mensual (`SuscripcionesHandler`) y la generación manual (`SuscripcionesController`):

1. Si la suscripción es numérica y existe algún `condominios_unidades.id_suscripcion = susc.id`,
   se piden a un servicio nuevo los cargos `pendiente`, `donde_cobro = 'junto'`, de esas unidades,
   con `id_cliente = susc.id_cliente` y `periodo ≤` el mes facturado (así se ponen al día las
   atrasadas). Se toman con `FOR UPDATE`.
2. Cada cargo se agrega al `$detalle` **antes** de calcular totales: producto del aporte,
   precio = monto, descripción «Pintura del edificio – cuota 5/24». El IVA sale de
   `CalculoIvaDocumento`, como el resto de las líneas.
3. En la **misma transacción** del documento, los cargos pasan a `emitido` con
   `tipo_documento`, `id_documento`, `id_suscripcion` y `fecha_emision`. Si el documento falla,
   el rollback deja las cuotas pendientes para la próxima corrida.
4. Cuando se emite la última cuota, el plan pasa a `completo` y no se agrega nada más.

Las suscripciones sintéticas de la emisión en bloque (`'emisión N'`) no entran en este enganche.

**Cuándo una cuota «junto» se emite aparte:** si su pagador no es el cliente de la suscripción
de alícuota (paga el propietario y la alícuota la paga el arrendatario) o si el inmueble no tiene
suscripción. La pantalla la marca «sin suscripción, se emite aparte» y la incluye en §4.4.

### 4.4 Cuotas «aparte» (y las «junto» que no pueden ir juntas)

Botón **Emitir cuotas del mes** en la cuota extraordinaria: elige el mes y muestra la vista
previa, con un documento por inmueble y la suma de sus cuotas pendientes hasta ese mes. Luego
**Emitir** usa el emisor en bloque existente (`CondominioCobroService` + worker en segundo plano,
SRI, correo) con `forma_valor = 'cargos'`. Al generar cada documento, sus cargos pasan a
`emitido` con `id_emision_item`, en la misma transacción.

Primera versión: la emisión aparte es **manual**. Una emisión automática por día del mes queda como punto abierto (§10).

### 4.5 Liquidar el saldo (pago total anticipado)

Desde el plan de un inmueble: **Liquidar saldo** suma las cuotas pendientes, aplica el beneficio
anticipado si está configurado y emite **un documento** por ese total. Ese documento queda
como cargo `liquidacion` y las cuotas pendientes pasan a `anulado`. El plan queda en `liquidado`.

### 4.6 Venta del inmueble o cambio de arrendatario

Al cambiar el propietario en la ficha del inmueble (lo que hoy abre una fila en el historial),
si hay planes `en_curso` cuyo pagador es el propietario, la ventana pregunta por cada plan:

- **Seguir con la deuda**: los cargos pendientes desde «Rige desde» cambian su `id_cliente`
  al nuevo propietario. Lo ya emitido sigue a nombre del vendedor.
- **Liquidar**: igual que §4.5, pero el documento sale al **vendedor** (`motivo = 'venta'`) y el plan
  queda cerrado para ese inmueble.

Si el pagador del plan es el arrendatario y cambia o se va el arrendatario, se pregunta lo mismo (seguir con el nuevo pagador o liquidar al saliente).

### 4.7 Anulación o nota de crédito del documento

No se engancha a la anulación de Recibos/Facturas (no se tocan esos módulos). El control
muestra **«documento anulado»** en la cuota cuyo documento está anulado o eliminado y ofrece
**Volver a pendiente**, que libera el cargo para que salga en la próxima emisión. Una NC parcial
se refleja en lo cobrado, no en la cuota (§6).

### 4.8 Cambiar el plazo después de activar

Solo sobre las cuotas **pendientes**: se recalcula el saldo pendiente ÷ nuevo número de cuotas
restantes; las pendientes se anulan y se crean las nuevas, que siguen la numeración
(«6/18»…). Las emitidas no se tocan.

## 5. Pantalla

Pestaña **Cuotas extraordinarias** en `condominios-config`, con el mismo diseño que las demás pestañas de la pantalla:

- **Listado**: nombre, acta, meta, reparto, dónde se cobra, inmuebles, emitido, cobrado,
  pendiente, % de avance y estado. Tiene buscador `FiltrosBusqueda`, `MAPA_ORDEN`, PDF y Excel.
- **Modal de la cuota extraordinaria** con pestañas:
  - **General**: los datos de §4.1.
  - **Inmuebles**: la grilla de §4.2 antes de activar. Después de activar muestra el plan de
    cada inmueble (asignado, anticipo, plazo, cuota, «X/N» emitidas, cobrado, saldo y estado)
    con las acciones Liquidar y Cambiar plazo.
  - **Cuotas**: el cronograma completo por mes e inmueble, con su documento y su estado, y el
    botón **Emitir cuotas del mes** (§4.4).
  - **Información**: la pieza común `InformacionRegistro`.
- La barra de acciones lleva el **PDF del estado de la cuota extraordinaria** (por inmueble o
  general) con `CMG_pdfDocumento`.
- En la **ficha del inmueble**, la pestaña Expensa muestra sus planes vigentes, y el cambio de
  propietario pregunta lo de §4.6.

## 6. Cobrado y saldo (cómo se calcula)

Sin cambios en Ingresos. Lo cobrado de cada cuota se **deriva** del documento en que salió:

- **Documento propio** (aparte, abono o liquidación): cobrado = total − saldo del documento en CxC.
- **Documento junto a la alícuota**: lo cobrado del documento cubre **primero las líneas que no
  son cuotas** (la alícuota) y después las cuotas, en orden de número. Ejemplo: un recibo de
  $103,40 ($85 de alícuota y $18,40 de cuota) al que abonaron $85 deja la cuota en $0 cobrado.

Los saldos usan las mismas fuentes que Cuentas por Cobrar (cobros, retenciones, NC). Una consulta del
repositorio los arma por cuota, sin columnas cacheadas que puedan desfasarse.

## 7. Reglas de negocio

1. Solo cuotas extraordinarias `activo` generan o emiten cuotas; `cerrado` es solo lectura.
2. La suma de lo asignado = meta, salvo los centavos, que van al de mayor monto.
3. Anticipo a aplicar ≤ saldo de anticipo del pagador y ≤ monto total del inmueble, revalidado bajo candado al activar.
4. Una cuota sale en un solo documento: índice único por plan y número, `FOR UPDATE` al
   emitir y marca en la misma transacción del documento.
5. Plazo elegido ∈ plazos permitidos.
6. No se elimina una cuota extraordinaria con cuotas emitidas; se **cierra**. Las pendientes se anulan con confirmación.
7. Pagador arrendatario solo si el inmueble tiene arrendatario vigente.
8. Todo cambio va a `log_sistema` (aporte, planes, cuotas, liquidaciones, cambio de pagador).
9. Permisos: Ver (listado, estado, PDF/Excel), Crear (nueva cuota extraordinaria, activar
   planes, emitir), Actualizar (editar, cambiar plazo, pagador, volver a pendiente), Eliminar
   (cerrar o eliminar sin cuotas emitidas).

## 8. Contabilidad

Sin tipos de asiento nuevos:

| Momento | Asiento (motor actual) |
|---|---|
| Documento de cuota / abono / liquidación | Debe CxC / Haber **cuenta del producto** del aporte |
| Ingreso que aplica el anticipo | Debe **Anticipo de clientes** (cuenta de la forma ANTICIPO) / Haber CxC |
| Cobro normal de una cuota | Debe Caja/Banco / Haber CxC |

El contador decide en el **producto** si la obra va a un ingreso o a un pasivo o fondo
(«Fondo para pintura»), igual que con el resto de conceptos del condominio.

**A verificar antes de programar:**
- que un ingreso con forma de cobro tipo ANTICIPO aplicado a un recibo de venta (no solo a
  facturas) genere bien el asiento y descuente el saldo de anticipo. Una nota anterior lo
  marcaba como pendiente;
- que el ingreso valide el saldo disponible.

## 9. Renombre: «Fondo de reserva»

La configuración que la versión 1.6 llamó «Cuotas extraordinarias» (recargo permanente % o fijo
en cada cuota, columnas `fondo_reserva_*`) vuelve a llamarse **Fondo de reserva** en pantalla y
manual. «Cuota extraordinaria» queda solo para lo de este documento. Es un cambio de etiquetas,
sin SQL.

## 10. Puntos abiertos (no bloquean el inicio)

- Emisión **automática** de las cuotas aparte en un día fijo del mes (cron), en vez del botón.
- Recargo por plazo largo y beneficio por pago anticipado: las columnas existen; se dejan ocultas
  hasta que un condominio lo pida.
- Carga del plan por Excel (anticipo y plazo por inmueble) para condominios grandes.
- Mostrar las cuotas extraordinarias en el estado de cuenta del Reporte de Cartera.

## 11. Entregables y orden de trabajo

1. SQL `20261117_condominios_cuotas_extraordinarias.sql`: lo aplica el usuario en pgAdmin, local y producción.
2. Verificación de la aplicación del anticipo a recibos (§8).
3. `CondominioCuotaExtraRepository` (MAPA_ORDEN, FiltrosBusqueda, planes, cargos, cobrado §6),
   `CondominioCuotaExtraRules`, `CondominioCuotaExtraService` (reparto, activar, abono, liquidar,
   cambiar plazo, venta, cerrar).
4. Enganche en `SuscripcionFacturacionService::generarUnPeriodo()` (§4.3) y `forma_valor = 'cargos'` en `CondominioCobroService` y su worker (§4.4).
5. Acciones en `CondominiosConfigController`, pestaña y modal en `condominios_config/`, JS en
   `public/js/modulos/condominios_cuotas_extra.js` (con `asset_ver`), botón con bandera «en curso» y `token_guardado`.
6. Pregunta de venta en la ficha del inmueble (§4.6) y renombre a Fondo de reserva (§9).
7. Registro en `InformacionRegistro::CATALOGO`.
8. Prueba sandbox (savepoint): reparto iguales/%, anticipo, 24 cuotas con centavos, emisión
   junto y aparte, cuota que no se duplica, liquidación, venta, cobrado parcial.
9. Manual `condominios-config.md` (nueva sección, historial v1.8) y, si cambia lo que se ve en Suscripciones, `suscripciones.md`.
