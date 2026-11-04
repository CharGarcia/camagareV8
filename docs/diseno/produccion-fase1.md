# Módulo Producción — Diseño (motor genérico)

> Documento de diseño para revisión. **No es el manual del usuario** (irá en
> `docs/manual/modulos/produccion-*.md` cuando exista) y no se publica en `/documentacion`.
> Versión 2 — 03-11-2026. Estado: **borrador para aprobación**; las decisiones marcadas ❓
> esperan confirmación.
>
> **v2:** el usuario pidió que el módulo cubra **cualquier tipo de producción**. Se pasó de un
> diseño "fórmula → producto" a un **motor genérico**: un solo modelo de datos que cubre
> ensamble, procesos por lote, desensamble, producción por pedido, maquila y ciclos largos,
> con funciones que se activan por empresa y por fórmula. No hay código por industria.

## 0. Estado de la implementación (fase 1, 03-11-2026)

Implementada la fase 1, con estos ajustes respecto del diseño original. Todos van a favor de
la regla de cero impacto (§10.4):

- **Ningún archivo existente se modificó.** Todo el módulo son archivos nuevos `Produccion*` / `produccion_*`.
  - **Inventario**: Producción escribe el kardex con las funciones públicas de `InventarioRepository` (como Transferencias de Inventario) y anula con `InventarioService::eliminarMovimiento()`. El costo de salida usa el mismo criterio que las ventas (promedio / FIFO / LIFO del establecimiento; sin historial, el costo de la ficha) dentro de `ProduccionOrdenService::costoSalida()`, sin tocar `InventarioService`.
  - **Contabilidad** (cambiado el 04-11-2026 a pedido del usuario): las cuentas se configuran en **Configuración Contable → Producción** (tipo `produccion`, 6 conceptos `PROD_*`, SQL `database/2026-11-04_produccion_contabilidad.sql`). El tipo y su interruptor solo aparecen en las empresas con `produccion_configuracion` (`TABLAS_POR_TIPO_ASIENTO` y el campo nuevo `solo_si_tabla` de `config/contabilidad_modulos.php`). El sincronizador genera las órdenes cerradas pendientes. Los asientos se numeran `PR-`. Esto agregó entradas en 9 archivos existentes, todas de solo agregar. Si un asiento falla, la orden guarda el motivo (`asiento_aviso`) y ofrece «Generar asiento».
  - **Productos**: el bloqueo del doble descuento se aplica al **activar la fórmula**, no en la ficha del producto.
- **Asientos**: siempre en dos pasos, también en producción rápida. Cada consumo genera Dr Productos en proceso / Cr Materia prima; el cierre, Dr Producto terminado (+ Merma) / Cr Productos en proceso + MO + CIF. En producción rápida PEP queda en cero el mismo día.
- **Entregas**: una sola por orden, al cerrar, con el costo real (las entregas parciales con costo estándar y su ajuste pasan a la fase 2). La producción diaria se cubre con **Producción rápida**: cada producción es una orden.
- **Estados**: `liberada` (se muestra como «Planificada») → `en_proceso` → `cerrada`; `anulada`. El estado `borrador` no se usa en la fase 1.
- **Menú y permisos**: no se crearon (submódulos, `modulos_asignados` ni `config/modulos_mvc.php`): los registra el usuario.
- **Pruebas**: hubo dos pruebas en la BD local, dentro de una transacción revertida.
  - De punta a punta: 52 OK, con el kardex y los asientos en el mismo conteo antes y después. Cubrió configuración, fórmula, orden, consumo por peso y lote, reintentos, cierre con subproducto y merma, cuadre al centavo, asientos, producción rápida, anulación, bloqueo por venta posterior, doble descuento, listados, PDF y reportes.
  - De costeo puro: 11 OK, con coproductos por valor, unidades y %, y fórmulas en % del lote.

## 1. Objetivo

Que cualquier empresa que transforma algo —una imprenta, un laboratorio de cosméticos, una
panadería, una carpintería a medida, una embotelladora, un camal, una camaronera— registre su
producción con costo real, kardex en todas las puntas, trazabilidad por lote, control de
calidad cuando lo necesita y asiento contable automático.

**Principio rector:** el modelo de datos es completo y genérico **desde el primer día**
(varias salidas, etapas, recursos, controles, costos imputados), aunque algunas funciones se
muestren en fases posteriores. Así ninguna fase obliga a migrar datos de la anterior.

## 2. Tipos de producción que debe cubrir

| Tipo | Ejemplos | Qué lo distingue | Cómo lo cubre el motor |
|---|---|---|---|
| **Ensamble / discreta** | Cajas de cartulina, muebles en serie, calzado, confección | Muchas entradas → 1 producto contable en unidades | Fórmula con cantidades fijas por unidad + merma. |
| **Proceso por lote** | Cosméticos, alimentos, químicos, pinturas, farmacéuticos | Fórmula en %, tamaño de lote variable, granel → envasado, caducidad, calidad | Cantidades en % del lote, producto intermedio (granel), vida útil, cuarentena y controles. |
| **Desensamble / coproductos** | Camal (canal → cortes), aserradero (troza → tablas), piladora (arroz → arroz, polvillo, cascarilla) | 1 entrada → varias salidas con costo conjunto | Fórmula con varias **salidas** (principal, coproductos, subproductos) y reparto del costo conjunto. |
| **Por pedido (job shop)** | Imprenta por encargo, carpintería a medida, metalmecánica, construcción de piezas | Cada orden es única, nace de un pedido/proforma, acumula costos de compras y servicios de terceros | Orden **sin fórmula** o con fórmula editable, enlazada a pedido/cliente, con **costos imputados** desde Compras/Egresos/Nómina. |
| **Repetitiva / continua** | Embotelladora, agua, hielo, bloques, panadería diaria | Mismo producto todos los días, sin seguimiento por orden | **Producción rápida** (un paso) o parte diario por turno/línea. |
| **Maquila** | Envasar o confeccionar para un tercero con su materia prima | La MP no es propia: no tiene costo para la empresa; se cobra un servicio | Bodega de **terceros** (stock sin costo), orden marcada como maquila, factura del servicio. |
| **Ciclo largo / biológico** | Camarón por piscina, cultivos, crianza de pollos, ganado | Costos que se acumulan durante semanas/meses (alimento, larva, energía, nómina) hasta la cosecha | Orden de **larga duración**: consumos y costos imputados a lo largo del ciclo, saldo en Productos en Proceso, cosecha con varias salidas (tallas). |

## 3. Conceptos del motor

### 3.1 Fórmula (lista de materiales / receta / plantilla)

Una fórmula describe **entradas, salidas, etapas, recursos y controles**. Todo es opcional
salvo al menos una salida.

- **Entradas** (insumos, materia prima, empaque, subensambles). Por cada una:
  - *modo de cantidad*: **por unidad** de salida principal (0,352 kg por caja), **% del lote**
    (glicerina 5 %), o **fija por lote** (una carga de limpieza, un troquel), que no escala;
  - unidad de medida (con la conversión de unidades existente), merma %, etapa en que se
    consume, si exige lote.
- **Salidas**, cada una con su *rol*:
  - **principal**: lo que se quiere fabricar (define el rendimiento de la fórmula);
  - **coproducto**: también es objetivo y recibe parte del costo conjunto (cortes de carne,
    tallas de camarón, tablas de distinto grado);
  - **subproducto**: secundario, entra a valor neto realizable y **rebaja** el costo de los
    demás (retazo de cartulina, cascarilla, polvillo);
  - **desperdicio**: se registra la cantidad, sin entrar a inventario.
- **Etapas / operaciones** (opcional): nombre, orden, centro de trabajo, instrucciones
  (temperatura, tiempos, orden de adición), tiempo estándar. Sin etapas, la fórmula es de un
  solo paso.
- **Recursos** por etapa o por fórmula: mano de obra, máquina, CIF. Base de cálculo: por
  unidad, por lote, por hora (horas estándar × tarifa) o % de la MP.
- **Controles de calidad** (opcional): parámetros con unidad y rango (pH 5,5–6,5;
  viscosidad; peso neto; gramaje; humedad; microbiología) por etapa o al final.
- **Vida útil** (meses/días) para calcular la caducidad del lote producido.
- Versiones (`borrador` / `vigente` / `inactiva`), una vigente por salida principal.

### 3.2 Orden de producción

Un documento único con **tipo** `rapida` / `orden` / `por_pedido` / `ciclo` / `maquila`
(el tipo solo decide qué pasos y pestañas muestra la pantalla; el motor es el mismo):

- Se crea **desde una fórmula** (copia y escala la receta: cambiar la fórmula después no
  altera la orden) o **sin fórmula** (por pedido: se van agregando consumos y costos).
- Puede enlazarse a un **pedido, proforma o cliente** (producción por pedido) y a centro de
  costo / proyecto.
- Estados: `borrador → liberada → en_proceso → terminada → cerrada`, y `anulada`.
  - *terminada*: ya entró todo lo producido; *cerrada*: el costo quedó fijado y contabilizado.
    En órdenes simples ambos pasos van juntos.
- **Movimientos de la orden** (todos con guardado único y candado de stock):
  - *Consumo*: salida de insumos (parcial o total, por cantidad o **por diferencia de peso**
    inicial − final), con lote; se puede consumir más o menos que lo teórico y agregar insumos
    fuera de fórmula.
  - *Imputación de costos*: compras, egresos, servicios de terceros (laminado, galvanizado,
    análisis de laboratorio) y, en fase 3, horas de nómina, imputados a la orden.
  - *Recursos*: horas reales o estándar por etapa.
  - *Entrega*: entrada de una o varias salidas (principal, coproductos, subproductos) con
    lote y caducidad. Varias entregas por orden (por turno, por día, por cosecha parcial).
  - *Control de calidad*: resultados de los parámetros por lote; aprobado / rechazado.
- **Cierre**: calcula el costo real total, lo reparte entre las salidas (§4) y ajusta el
  costo de las entregas hechas con costo provisional.

### 3.3 Configuración por empresa (`produccion_configuracion`)

Interruptores que adaptan el módulo sin código nuevo:

| Opción | Por defecto |
|---|---|
| Bodegas por defecto (insumos, terminado, cuarentena, terceros) | — |
| Permitir consumir sin stock | No |
| Exigir lote en insumos / en lo producido | No / Sí |
| Usar cuarentena (lo producido entra a la bodega de cuarentena hasta aprobación) | No |
| Usar etapas, recursos por hora, controles de calidad | No |
| Método de reparto del costo conjunto por defecto | Valor de venta relativo |
| Costo de las entregas antes del cierre | Estándar de la fórmula |
| Tipos de orden visibles en el menú | Rápida y Orden |

Una empresa que solo hace pan ve "Producción rápida" y nada más; un laboratorio activa lotes,
cuarentena, etapas y controles; una camaronera activa ciclos y coproductos.

## 4. Costeo

**Costo de la orden** = MP consumida (costo real de cada salida del kardex, con el método del
establecimiento: promedio / FIFO / LIFO) + costos imputados (compras, egresos, servicios) +
recursos (MO, máquina, CIF por estándar o real) − valor de los subproductos.

**Reparto entre salidas** (cuando hay coproductos) ❓, configurable por fórmula:
1. **Valor de venta relativo** (precio de venta × cantidad de cada coproducto) — recomendado
   por NIC 2.14 y el más usado en carnes y camarón por tallas.
2. **Unidades físicas** (kg, litros, unidades).
3. **% fijo** definido en la fórmula.

**Subproductos**: entran a su valor neto realizable (precio estimado − costo de venderlo),
que se resta del costo conjunto. **Desperdicio**: sin valor.

**Merma**: la normal (dentro de la merma % de la fórmula) sube el costo unitario; la anormal
se marca al entregar y va a gasto (NIC 2.16).

**Entregas antes del cierre**: entran al costo estándar de la fórmula; al cerrar, la
diferencia contra el costo real se ajusta así ❓: si el lote sigue en stock, se corrige el
costo de la entrada; si una parte ya se vendió, esa parte de la diferencia va a
**variación de costos de producción** (resultado), sin reabrir facturas.

**Maquila**: la MP del cliente vive en una bodega de terceros sin costo; la orden acumula
solo los costos propios (empaque, MO, CIF), que se cargan al costo del servicio facturado.

## 5. Contabilidad

Nuevo `tipo_asiento = 'produccion'` en Configuración Contable, con la cascada habitual por
línea (producto → categoría → marca → general):

| Concepto | Uso |
|---|---|
| `INVENTARIO_MATERIA_PRIMA` | Haber del consumo (por insumo). |
| `PRODUCTOS_EN_PROCESO` | Debe de consumos, costos imputados y recursos; Haber de las entregas. |
| `INVENTARIO_PRODUCTO_TERMINADO` | Debe de cada entrega (por producto: principal, coproducto o subproducto). |
| `MANO_OBRA_APLICADA`, `CIF_APLICADOS` | Haber de los recursos aplicados. |
| `MERMA_ANORMAL` | Debe por la merma anormal (gasto). |
| `VARIACION_COSTOS_PRODUCCION` | Diferencia estándar vs. real de lo ya vendido al cerrar. |
| `COSTO_SERVICIO_MAQUILA` | Debe del costo de una orden de maquila al cerrar. |

Las compras/egresos imputados a una orden llevan su costo a `PRODUCTOS_EN_PROCESO` en su
propio asiento (la línea de gasto/inventario se reemplaza por la cuenta de PEP cuando la línea
está imputada a una orden). Producción rápida: un solo asiento directo. Un ciclo largo deja su
saldo en PEP a fin de mes, como pide NIC 2 / NIC 41 para activos biológicos en proceso ❓
(camarón y cultivos pueden requerir valor razonable: revisarlo con el contador antes de la
fase de ciclos).

Si falta una cuenta, el asiento queda pendiente con el aviso de cuentas faltantes estándar.
Respeta el interruptor de módulos que contabilizan y el sincronizador (clave `produccion`).

## 6. Modelo de datos

Tablas operativas con `id_empresa`, auditoría y eliminación lógica (§5 de CLAUDE.md).

| Tabla | Contenido |
|---|---|
| `produccion_configuracion` | Una fila por empresa con las opciones de §3.3. |
| `produccion_centros_trabajo` | Líneas, máquinas, áreas (con tarifa por hora). |
| `produccion_formulas` | Cabecera: código, nombre, versión, cantidad base del lote, unidad, vida útil, método de reparto, estado, instrucciones generales. |
| `produccion_formulas_etapas` | Etapas: orden, nombre, centro de trabajo, instrucciones, tiempo estándar. |
| `produccion_formulas_lineas` | Entradas y salidas: `rol` (`insumo` / `principal` / `coproducto` / `subproducto` / `desperdicio`), producto, `modo_cantidad` (`por_unidad` / `porcentaje` / `fija_lote`), cantidad, unidad, merma %, `id_etapa`, % de reparto fijo, valor neto realizable. |
| `produccion_formulas_recursos` | Recursos: tipo (`mo` / `maquina` / `cif`), etapa, base (`unidad` / `lote` / `hora` / `pct_mp`), cantidad, tarifa. |
| `produccion_formulas_controles` | Parámetros de calidad: etapa, nombre, unidad, mínimo, máximo, obligatorio. |
| `produccion_ordenes` | Cabecera: número `OP-000001`, tipo, fórmula y versión, pedido/cliente, centro de costo, proyecto, bodegas, cantidad planificada, fechas, estado, maquila, costos totales, `tipo_ambiente`, asientos. |
| `produccion_ordenes_lineas` | Copia escalada de la fórmula (teórico) + consumido / producido real. |
| `produccion_ordenes_movimientos` | Cada consumo y cada entrega: tipo, producto, bodega, cantidad, lote, caducidad, peso inicial/final, `id_kardex`, costo, etapa, fecha. Fuente de la trazabilidad. |
| `produccion_ordenes_costos` | Costos imputados: origen (`compra` / `egreso` / `servicio` / `nomina` / `manual`), documento, monto, concepto. |
| `produccion_ordenes_recursos` | Horas/recursos reales por etapa. |
| `produccion_ordenes_calidad` | Resultados de los controles por lote: valor, conforme, quién, cuándo; decisión aprobado / rechazado. |

Kardex: `referencia_tipo = 'produccion_consumo'` y `'produccion_entrega'`, `referencia_id =
id de la orden`. La numeración usa candado `pg_advisory_xact_lock(hashtext('produccion_orden:'
‖ empresa ‖ ':' ‖ ambiente))`, como `TransferenciaInventarioRepository::siguienteSecuencial()`.

## 7. Reglas clave (`ProduccionRules`)

- **Doble descuento**: una salida con fórmula vigente no puede tener componentes de venta
  (`productos_componentes`, que se descuentan al vender: kits, platos, Taller), y viceversa.
  Los componentes de venta siguen igual; el módulo no los toca.
- Salidas inventariables de tipo bien; al menos una salida principal; % de reparto fijo suma
  100; fórmula en % suma 100 (con tolerancia configurable).
- Sin ciclos entre fórmulas (A usa B y B usa A).
- Acceso del usuario a todas las bodegas que toca; período contable abierto en cada fecha.
- Consumo sin stock bloqueado salvo que la configuración lo permita (revalidado bajo candado).
- Con cuarentena activa, lo producido no se puede vender ni transferir fuera de cuarentena
  hasta la aprobación de calidad.
- Anular una entrega/orden solo si lo producido no tiene consumo posterior
  (`getConsumoPosteriorPorReferencia`); revierte kardex y asientos en la misma transacción.

## 8. Pantallas

- **Fórmulas**: listado estándar; modal con pestañas ocultables *Entradas*, *Salidas*,
  *Etapas*, *Recursos*, *Calidad*, *Costo estimado*. Las pestañas de funciones apagadas en la
  configuración no se muestran. Entrada en % o en cantidad. Crear producto al vuelo.
- **Órdenes**: listado estándar con estado y tipo; modal con barra de acciones (PDF de la
  orden, **registro de lote** con lotes pesados y firmas, hoja de requisición) y pestañas
  *Plan*, *Consumos*, *Entregas*, *Costos*, *Calidad*, *Asiento*. Botones según estado.
- **Producción rápida**: modal corto desde el listado.
- **Calidad** (si está activa): bandeja de lotes en cuarentena para aprobar/rechazar.
- **Reportes**: producción por producto y período, costo real vs. estándar, consumo vs.
  teórico, merma, rendimiento de coproductos, órdenes abiertas (saldo en PEP).
- **Trazabilidad**: desde un lote, hacia atrás (insumos y sus lotes, proveedores) y hacia
  adelante (clientes y facturas que lo recibieron) — retiro de mercado.
- Fase 3: tablero de planta (como `taller-tablero`) y captura por tablet en cada etapa.

## 9. Fases

El modelo de datos de §6 se crea completo en la fase 1.

| Fase | Contenido | Tipos que quedan cubiertos |
|---|---|---|
| **1. Núcleo** | Configuración; fórmulas con entradas (por unidad / % / fija), salidas con **coproductos y subproductos**, vida útil e instrucciones; producción rápida; órdenes con consumos parciales (incl. por peso), **entregas múltiples**, cierre con reparto de costos; lotes y caducidad; cuarentena por bodega; registro de lote en PDF; asiento; reporte básico; trazabilidad por lote. | Ensamble, proceso por lote (sin análisis), desensamble, repetitiva. |
| **2. Pedido, maquila y calidad** | Órdenes por pedido (desde pedido/proforma) e **imputación de compras/egresos/servicios**; maquila con bodega de terceros; controles de calidad con resultados y liberación; reserva de stock; sugerir compras de faltantes; NSO / registro sanitario en el producto. | Por pedido, maquila, cosméticos/alimentos con BPM completas. |
| **3. Planta y ciclos** | Etapas con centros de trabajo y recursos por hora reales; MO real desde Marcaciones/Roles; tablero de planta; órdenes de ciclo largo (camarón, cultivos) con cosecha por tallas; plan de producción (MRP) desde pedidos y stock mínimo. | Ciclo largo, planta con rutas. |

## 10. Organización como módulo aparte

**Producción es un módulo propio del menú** (grupo *Producción* en el menú, como *Taller* o
*Citas*), con sus submódulos, sus tablas `produccion_*` y su prefijo `Produccion` en cada
capa. Se vende y se activa por empresa como cualquier otro: asignando sus submódulos (o un
**combo** "Producción" en `/config/combos-submodulos`). Una empresa sin esos submódulos no ve
nada ni cambia nada en su operación.

### 10.1 Submódulos del menú

| Submódulo | Ruta MVC | Fase | Nota |
|---|---|---|---|
| Órdenes de producción | `modulos/produccion-ordenes` | 1 | Incluye el botón **Producción rápida** (permiso `w`); no es un submódulo aparte. |
| Fórmulas | `modulos/produccion-formulas` | 1 | |
| Reportes de producción | `modulos/produccion-reportes` | 1 | Con pestañas por usuario (`PestanasModulo::CATALOGO`): Producción, Costos, Consumo vs. teórico, Merma, Órdenes abiertas, Trazabilidad. |
| Configuración | `modulos/produccion-configuracion` | 1 | Interruptores de §3.3. El módulo queda activo al guardarla la primera vez; sin ella, Órdenes muestra un aviso con el enlace. |
| Calidad | `modulos/produccion-calidad` | 2 | Bandeja de lotes en cuarentena. En fase 1 la cuarentena es solo la bodega. |
| Centros de trabajo | `modulos/produccion-centros-trabajo` | 3 | La tabla se crea en fase 1; la pantalla llega con las etapas. |

Cada submódulo se registra en `config/modulos_mvc.php` (con `id_submodulo = 0`, se resuelve
por la ruta) y en `submodulos_menu` bajo el nuevo módulo *Producción*.

### 10.2 Archivos por capa

El Router resuelve `/modulos/{nombre}` a `App\controllers\modulos\{Nombre}Controller`, así
que los controladores van planos con prefijo, igual que `Taller*` y `Cita*`. Las demás capas
siguen el mismo prefijo para que todo el módulo se encuentre buscando `Produccion`:

```
database/2026MMDD_produccion.sql                      todas las tablas produccion_* (§6)
config/modulos_mvc.php                                + 6 rutas modulos/produccion-*
config/contabilidad_modulos.php                       + clave 'produccion'

app/controllers/modulos/
  ProduccionOrdenesController.php
  ProduccionFormulasController.php
  ProduccionReportesController.php
  ProduccionConfiguracionController.php
  ProduccionCalidadController.php            (fase 2)
  ProduccionCentrosTrabajoController.php     (fase 3)

app/repositories/modulos/
  ProduccionConfiguracionRepository.php
  ProduccionFormulaRepository.php             cabecera + líneas + etapas + recursos + controles
  ProduccionOrdenRepository.php               cabecera + líneas + movimientos + costos + calidad
  ProduccionReporteRepository.php             solo lectura (AmbienteReporte: solo producción)
  ProduccionCentroTrabajoRepository.php

app/Rules/modulos/
  ProduccionFormulaRules.php                  sumas %, ciclos, doble descuento, salidas
  ProduccionOrdenRules.php                    estados, bodegas, período, stock

app/Services/modulos/
  ProduccionConfiguracionService.php
  ProduccionFormulaService.php                versiones, escalado, costo estimado
  ProduccionOrdenService.php                  liberar, consumir, entregar, cerrar, anular
  ProduccionCosteoService.php                 costo de la orden y reparto entre salidas (§4)
  ProduccionAsientoService.php                patrón AjusteInventarioAsientoService
  ProduccionOrdenPdfService.php               orden, registro de lote, requisición
  ProduccionReporteService.php

app/views/modulos/
  produccion_ordenes/       index.php, modal_orden.php, modal_rapida.php
  produccion_formulas/      index.php, modal_formula.php
  produccion_reportes/      index.php (cmg-control-card)
  produccion_configuracion/ index.php

public/js/modulos/
  produccion_ordenes.js, produccion_formulas.js, produccion_reportes.js

docs/manual/modulos/
  produccion-ordenes.md, produccion-formulas.md, produccion-reportes.md,
  produccion-configuracion.md
docs/manual/guias/produccion-por-industria.md  ejemplos: cartulina, cosméticos, camal, pan
```

### 10.3 Puntos de contacto con el resto del sistema

Regla: **el resto del sistema nunca depende de Producción.** Producción usa piezas existentes;
los únicos cambios fuera del módulo son estos, y todos degradan sin hacer nada si las tablas
`produccion_*` no existen o la empresa no usa el módulo:

| Archivo existente | Cambio | Fase |
|---|---|---|
| `InventarioService` | **Método público nuevo** que envuelve a `registrarSalidaIndividual()` (privada), para costear con el mismo método que las ventas sin duplicar la lógica (§8 de CLAUDE.md). La privada **no se toca**. | 1 |
| `InventarioService::TABLA_ORIGEN` | Una entrada nueva (`produccion_*` → `produccion_ordenes`) para que el Kardex reconozca una orden eliminada. | 1 |
| `ProductoRules` / modal de Productos | Impedir componentes de venta en un producto con fórmula vigente (§7); mostrar "Tiene fórmula de producción" en la ficha. | 1 |
| `AsientoBuilderService`, `SincronizadorAsientosService`, Configuración Contable | Tipo de asiento `produccion` y su armado. | 1 |

Ya no hace falta tocar `getTiposReferencia()`: arma la lista de tipos del kardex con lo que hay
en la tabla, así que los tipos nuevos aparecen solos.

### 10.4 Garantía de cero impacto en el sistema actual

**Requisito del usuario (03-11-2026): el módulo nuevo no debe afectar al sistema actual.** Se
cumple con estas reglas, que se revisan en cada entrega:

**1. Solo se agrega, nunca se modifica.**
- SQL de la fase 1: solo `CREATE TABLE IF NOT EXISTS produccion_*`, índices de esas tablas e
  `INSERT … WHERE NOT EXISTS` de menú, submódulos y conceptos contables. **Ningún `ALTER`,
  `UPDATE` ni `DELETE` sobre tablas existentes.** El campo de Compras/Egresos de la fase 2
  será una columna nueva opcional (`NULL`), con su propio análisis de impacto antes.
- En archivos existentes solo se agregan métodos, entradas de mapa o ramas nuevas. No se
  cambia la firma ni el comportamiento de ningún método que ya se use.

**2. Todo cambio fuera del módulo se activa únicamente con datos de Producción.** Para una
empresa que no usa el módulo, el sistema hace exactamente lo mismo que hoy:

| Cambio | Condición que lo activa | Hoy, en toda empresa |
|---|---|---|
| Bloqueo de componentes en Productos | El producto tiene una fórmula **vigente** | Ningún producto tiene fórmula → no se bloquea nada. |
| Tipo de asiento `produccion` en Configuración Contable | La empresa guardó la configuración de Producción | No aparece la sección ni sus cuentas en el aviso de **cuentas faltantes**. |
| Interruptor en "Módulos que contabilizan" | Ídem | No aparece. |
| Trabajo del sincronizador y conteo de pendientes (avisos de Balance/Mayores/EEFF) | Hay órdenes de producción sin asiento | Cuenta 0 → los avisos no cambian. |
| Menú *Producción* | Submódulos asignados (nivel 3 lo ve siempre, como todo) | Usuarios de nivel 1 y 2 no lo ven. |

**3. Si las tablas no existen, no pasa nada.** Todo punto de contacto comprueba que existan
las tablas `produccion_*` (una vez por proceso, como `tieneColumnasAjusteContable()`) y, si
faltan, se salta en silencio. Así subir el código antes que el SQL, o al revés, no rompe nada.

**4. El kardex de Producción no se mezcla con lo de hoy.**
- Usa sus propios `referencia_tipo` (`produccion_consumo`, `produccion_entrega`): ningún
  reporte, filtro ni asiento existente los toma por error.
- Desde el Kardex esos movimientos no se pueden editar ni eliminar a mano: la regla actual
  (`validarRestriccionMovimiento`) ya bloquea todo lo que no sea `ajuste_manual`.
- Las entregas de producción son entradas con costo y forman parte del costo promedio /
  FIFO / LIFO de **ese producto**, que es lo correcto. Productos que no se fabrican no se ven
  afectados.

**5. Cuidado con la concurrencia.** Los candados de stock son los mismos (`lockStock`), así
que una producción y una venta del mismo producto en la misma bodega se esperan entre sí en
vez de pisarse. No se agregan bloqueos nuevos sobre tablas existentes.

**6. Pruebas de regresión antes de entregar cada fase.** Antes y después de los cambios, en
la base local:
- `php tools/test_calculo_iva.php` → `TODO OK`.
- Una muestra de facturas, recibos, notas de crédito, compras y ajustes de inventario: el
  asiento sugerido y los movimientos de kardex quedan **idénticos** antes y después.
- Guardar un producto con y sin componentes, en una empresa sin Producción: igual que hoy.
- Configuración Contable y avisos de cuentas faltantes de una empresa sin Producción: igual
  que hoy.
- Venta POS, comanda y orden de Taller de un producto con componentes de venta: siguen
  descontando sus componentes como hoy.

**7. Cómo se apaga.** Quitar los submódulos del menú o la asignación deja el sistema como
estaba; las tablas `produccion_*` quedan sin uso. No hay que revertir nada en las tablas
existentes porque no se tocaron.

**8. Entrega controlada.** El trabajo se hace sin commits (lo revisa y confirma el usuario),
se sube a producción primero sin asignar el módulo a ninguna empresa y se prueba con una
empresa piloto antes de ofrecerlo.
| Compras, Egresos (línea) | Campo opcional "Orden de producción" para imputar costos. | 2 |
| Pedidos / Proformas | Botón "Crear orden de producción". | 2 |
| Reporte de Trazabilidad | Pestaña de lotes producidos. | 2 |
| Marcaciones / Roles de pago | Horas reales por orden. | 3 |

## 11. Archivos de la fase 1 en orden de construcción

1. SQL de todas las tablas + submódulos y rutas.
2. Configuración (repo → service → controller → vista).
3. Fórmulas (repo → rules → service → controller → vista/JS).
4. Cambio en `InventarioService` + Órdenes: producción rápida primero, luego orden por etapas.
5. Costeo y cierre con coproductos.
6. Asiento contable.
7. PDF (orden, registro de lote).
8. Reportes.
9. Manuales.

## 12. Pruebas

- Un caso por tipo de §2: caja de cartulina (bobina por peso + retazo), crema (granel en % →
  envasado 50 g y 200 g, cuarentena), desposte (1 canal → 5 cortes, reparto por valor), pan
  (producción rápida diaria).
- Costeo por método (promedio, FIFO, LIFO) al centavo; reparto de coproductos suma el costo
  conjunto exacto (el último coproducto absorbe el redondeo).
- Entregas con costo estándar + cierre: ajuste correcto con todo en stock y con parte vendida.
- Concurrencia sobre el mismo insumo; doble clic y reintento tras error de conexión.
- Asientos cuadran en cada momento; orden abierta a fin de mes deja saldo en PEP.
- Vender un producto fabricado no vuelve a descontar insumos.

## 13. Pendiente de confirmar

1. ¿El enfoque genérico con funciones activables por empresa (§3.3) y el modelo completo
   desde la fase 1?
2. ¿Contenido de cada fase (§9)? En especial: ¿coproductos y entregas múltiples en la fase 1?
3. Método de reparto del costo conjunto por defecto: valor de venta relativo (§4).
4. Ajuste de entregas a costo estándar cuando parte ya se vendió: a variación de costos (§4).
5. Ciclos biológicos (camarón, cultivos): ¿costo histórico en PEP o valor razonable (NIC 41)?
   Requiere opinión del contador.
6. Nombres de menú: "Producción" → *Fórmulas*, *Órdenes*, *Calidad*, *Reportes*, *Configuración*.
