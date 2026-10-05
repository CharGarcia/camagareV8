# Módulo Condominios — Diseño de la fase 1

> Documento de diseño para revisión. **No es el manual del usuario** (ese irá en
> `docs/manual/modulos/condominios.md` cuando el módulo exista) y no se publica en
> `/documentacion`. Fecha: 05-10-2026. Estado: **borrador para aprobación**.

## 1. Objetivo

Administrar las expensas de un condominio (edificio, conjunto, urbanización) dentro de
CaMaGaRe reutilizando lo que ya existe: la emisión mensual la hace **Suscripciones**, el
cobro **Ingresos**, la cartera **Cuentas por Cobrar**, el estado de cuenta **Reporte de
Cartera**, el presupuesto **Presupuestos** y la contabilidad el motor contable actual. El
módulo nuevo aporta lo que ningún módulo tiene hoy: **unidades** (inmuebles), **alícuotas**,
**fondo de reserva y aportes**, **intereses y multas**, y las reglas del Decreto 462 /
Ley de Propiedad Horizontal (liquidación para cobro judicial y restricción de áreas comunes).
El orden legal de imputación de pagos **no** se implementa: eso lo resuelven por fuera con los
abogados; el cobro se aplica como hoy en Ingresos.

## 2. Decisiones tomadas (04/05-10-2026)

| # | Tema | Decisión |
|---|------|----------|
| 1 | Alcance multiempresa | **Cada condominio es una empresa** del sistema (RUC, contabilidad, secuenciales y usuarios propios). El módulo se activa al guardar su configuración. |
| 2 | Comprobante | **Recibo de venta o factura**; comprobante por defecto del condominio, cambiable por unidad. |
| 3 | Alícuota | Tres métodos: **por %**, **por m²**, **manual**. Método por defecto del condominio, cada unidad puede tener el suyo. |
| 4 | Quién paga | **Propietario siempre guardado**; arrendatario opcional; **pagador** = propietario o arrendatario. El documento sale al pagador; estado de cuenta y liquidación judicial muestran siempre al propietario. |
| 5 | Intereses | Interruptor por condominio (**apagado por defecto**). Si se cobran: línea en el siguiente recibo **o** recibo aparte. |
| 6 | Multas | Interruptor por condominio (apagado por defecto). Catálogo de multas del reglamento; se cargan a mano a una unidad. |
| 7 | Presupuesto | **No** vive en Condominios: se usa el módulo general **Presupuestos** (versiones aprobadas con acta). Condominios solo lee el presupuesto aprobado marcado como *base de alícuotas*. |
| 8 | Alícuotas manuales con presupuesto | Opción del condominio: **(a) repartir el resto entre las demás unidades** (por defecto) o (b) manuales aparte. Solo aplica cuando la base del método por % es un presupuesto. |
| 9 | Fondo de reserva | Aporte **permanente**, **línea separada** en el recibo: No / % sobre la alícuota ordinaria / monto fijo. La unidad puede tener un valor propio. |
| 10 | Otros aportes | Siempre **con meta** (monto total a recaudar), **nombre libre** («Fondo para pintar edificio», «Préstamo ascensores»). Reparto por %, m², fijo o manual; **plazos permitidos** que cada unidad elige; **recargo por plazo** en % o valor; **beneficio por pago anticipado** en % o valor. Absorbe las «expensas extraordinarias» (= aporte con meta y un solo plazo). |
| 11 | Parqueaderos y bodegas | Son **unidades propias** y **cada unidad emite su propio recibo o factura**. No hay «unidad principal» ni agrupación. |
| 12 | Vencimiento e intereses | Día de emisión (1–28, def. 1); vencimiento día fijo (def. 10) o N días tras emitir; días de gracia (def. 0; el interés se calcula desde el vencimiento); tasa % mensual o **tasa legal vigente** (tabla global por fecha; se guarda la tasa aplicada en cada cargo); interés simple proporcional a días sobre capital vencido (ordinaria + fondo + aportes), sin interés sobre interés; redondeo a 2 decimales. |
| 13 | Pronto pago / pago anticipado | **Configurable, apagado por defecto.** Pronto pago: % hasta el día N, aplicado al cobrar en Ingresos. Pago anticipado de N meses: un recibo con una línea por mes y descuento; esos meses quedan facturados y se devengan con el mecanismo de Suscripciones (modalidad anticipado). No aplica a intereses ni multas. |
| 14 | Sistema viejo | Solo aporta lo que ya trae `/config/migrar-mysql`: clientes, recibos/facturas y **programados** (→ suscripciones). **Unidades solo por Excel.** Un programado migrado se puede **enlazar** a una unidad (pasa a ser su expensa, monto como alícuota manual). Recibos históricos quedan al cliente; sin cruce por texto. |
| 15 | Restricción de áreas comunes | Marca en la unidad (desde fecha, motivo, quién notificó), manual o automática al superar N meses de mora (apagado por defecto); se quita al pagar; listado imprimible. Sin integración con control de acceso. |
| 16 | Liquidación judicial | PDF desde Cuentas por Cobrar con detalle por mes (ordinaria, fondo, aportes, intereses, multas), totales y pie de firma. **Administrador obligatorio, presidente de la asamblea opcional**, guardados en la configuración. |
| 17 | Cobro | **Siempre en Ingresos, exactamente como hoy** (la persona elige a qué documentos aplica el pago). **Sin** orden legal de imputación: eso lo manejan por fuera con los abogados. |
| 20 | Productos de los conceptos | Los conceptos (alícuotas, fondo, intereses, multas, aportes) son **productos/servicios creados en el módulo Productos** por el usuario (p. ej. «Alícuotas»). El módulo **no crea productos**: solo los **elige** con selectores (configuración, catálogo de multas, cada aporte). IVA, cuenta contable y descripción salen del producto. |
| 18 | Cartera | Gestión en **Cuentas por Cobrar**; estado de cuenta por unidad en **Reporte de Cartera**. |
| 19 | Unidades vs clientes | **Clientes = personas**, **Unidades = inmuebles**. Tabla propia. |

## 3. Alcance de la fase 1

**Entra**

1. Configuración del condominio (activa el módulo).
2. Unidades: CRUD, carga por Excel, plantilla, enlace con programados migrados.
3. Alícuotas: método por condominio y por unidad; valores con **fecha desde la que rigen**
   e historial; vista previa antes de aplicar; base desde un presupuesto aprobado.
4. Fondo de reserva y otros aportes con meta (plan por unidad, cronograma, pago anticipado).
5. Emisión mensual **por dentro de Suscripciones**: una suscripción por unidad, generada y
   mantenida por el módulo; recibo o factura por unidad con una línea por concepto.
6. Intereses y multas (si el condominio los activa), generados por la automatización mensual.
7. Cuentas por Cobrar: filtro/columna Unidad, marca de restricción, **liquidación para cobro
   judicial** (PDF).
8. Reporte de Cartera: búsqueda por unidad y por propietario (todas sus unidades), líneas
   identificadas por concepto.
9. Ingresos: sin cambios en el cobro; solo el descuento por pronto pago si está activo.
10. Contabilidad: nada nuevo. La cuenta de cada concepto sale del producto elegido (cascada
    producto → categoría → configuración general); devengado de pagos anticipados con
    `suscripciones_devengos`.
11. Manual `docs/manual/modulos/condominios.md` + actualizaciones de los manuales tocados.

**Queda para fases siguientes**

- Convenios de pago (cronograma de refinanciación de deuda vencida).
- Informe para la asamblea (recaudación, cartera vencida, fondo, aportes).
- Portal del condómino (enlace público: estado de cuenta y pago con tarjeta).
- Consumos por medidor (agua, gas) como línea del recibo.
- Notificaciones automáticas de mora (correo/WhatsApp) con plantillas del reglamento.

## 4. Reparto por módulos

| Módulo | Qué hace para condominios | Cambio |
|--------|---------------------------|--------|
| **Condominios** (nuevo, `modulos/condominios`) | Configuración, unidades, alícuotas, fondo y aportes, multas, restricción, tasa legal | Nuevo |
| **Suscripciones** | Emite el recibo/factura mensual de cada unidad. La suscripción de una unidad se marca `id_unidad` y la administra el módulo (no se edita a mano) | Columna `id_unidad`; filtro «Condominio»; bloqueo de edición manual de líneas gestionadas |
| **Ingresos** | Cobro, como hoy. Solo propone el descuento por pronto pago si el condominio lo activa | Descuento propuesto (opcional) |
| **Cuentas por Cobrar** | Gestión de cartera: unidad, restricción, liquidación judicial | Filtro/columna, 2 acciones, PDF |
| **Productos** | El usuario crea ahí los servicios de cada concepto («Alícuotas», «Fondo común de reserva», «Intereses por mora», multas, aportes) | Ninguno: el módulo solo los elige |
| **Reporte de Cartera** | Estado de cuenta por unidad o por propietario | Buscador por unidad; etiqueta de concepto por línea |
| **Presupuestos** | Base del método por %: presupuesto aprobado con marca `base_alicuotas` | Casilla «Base de alícuotas» (ya reservada) |
| **Automatizaciones** | Acción mensual `condominios → emitir_expensas` (emisión + intereses + multas pendientes + restricciones automáticas) | Handler nuevo |
| **Configuración Contable** | Nada nuevo: las cuentas se resuelven por el producto de cada concepto (ver §7 y §8) | — |
| **Carga Excel** | Plantilla y carga de unidades | Esquema nuevo (`CargaUnidadesEsquema`) |
| **Migración MySQL** | Nada nuevo. Los programados ya se migran como suscripciones | — |
| **Saldos Iniciales** | Solo si un condominio arranca sin historial migrado (`saldos_iniciales_cxc` por cliente, con nota de la unidad) | — |

Una sola lógica compartida (`CondominioCarteraService`) calcula la deuda de una unidad por
concepto y período: la usan Cuentas por Cobrar, Reporte de Cartera, la restricción automática y
el PDF de liquidación, así todos dan las mismas cifras.

## 5. Modelo de datos

Todas las tablas son **operativas**: llevan `id_empresa`, `created_at/by`, `updated_at/by`,
`eliminado`, `deleted_at/by`. Eliminación lógica. Nombres definitivos al escribir el SQL.

### 5.1 `condominios_config` (una fila por empresa)

| Campo | Tipo | Notas |
|-------|------|-------|
| id_empresa | int, UNIQUE | Activa el módulo |
| nombre_condominio, direccion | text | Para PDF |
| administrador_nombre, administrador_cedula, administrador_cargo | text | Obligatorios (firma liquidación) |
| presidente_nombre, presidente_cedula | text | Opcionales |
| comprobante_defecto | 'recibo' \| 'factura' | |
| id_punto_emision_defecto | int | Serie para emitir |
| metodo_alicuota | 'porcentaje' \| 'm2' \| 'manual' | Por defecto del condominio |
| reparto_manuales | 'repartir_resto' \| 'aparte' | Solo con presupuesto como base; def. repartir_resto |
| dia_emision | 1–28, def. 1 | |
| vencimiento_tipo | 'dia_fijo' \| 'dias' | def. dia_fijo |
| vencimiento_valor | int | def. 10 |
| dias_gracia | int, def. 0 | |
| cobra_intereses | bool, def. false | |
| interes_tipo | 'legal' \| 'fijo' | def. legal |
| interes_tasa_mensual | numeric(8,4) | Si fijo |
| interes_destino | 'siguiente_recibo' \| 'recibo_aparte' | def. siguiente_recibo |
| cobra_multas | bool, def. false | |
| fondo_reserva_tipo | 'no' \| 'porcentaje' \| 'fijo' | def. no |
| fondo_reserva_valor | numeric(14,2) | % o monto |
| id_producto_ordinaria (→ productos, NOT NULL) | int | Servicio creado en Productos (p. ej. «Alícuotas») |
| id_producto_fondo (→ productos) | int | Obligatorio si fondo_reserva_tipo ≠ 'no'; su nombre sale en el recibo |
| id_producto_interes (→ productos) | int | Obligatorio si cobra_intereses |
| pronto_pago_activo, pronto_pago_pct, pronto_pago_dia | bool/numeric/int | def. apagado |
| anticipado_activo, anticipado_pct, anticipado_meses_min | bool/numeric/int | def. apagado |
| restriccion_auto, restriccion_meses | bool/int | def. apagado |
| permite_vencimiento_por_unidad | bool, def. false | |

### 5.2 `condominios_unidades`

| Campo | Notas |
|-------|-------|
| id_empresa, codigo (UNIQUE por empresa), nombre | «Dpto 302», «P-12» |
| tipo | departamento \| local \| oficina \| parqueadero \| bodega \| casa \| otro |
| torre_bloque, piso | opcionales |
| area_m2 numeric(10,2), alicuota_pct numeric(9,6) | De la escritura |
| id_propietario (→ clientes, NOT NULL), id_arrendatario (→ clientes, nullable) | |
| pagador | 'propietario' \| 'arrendatario' |
| comprobante | null = el del condominio |
| metodo_alicuota | null = el del condominio |
| monto_manual numeric(14,2) | Si manual |
| fondo_reserva_valor_propio numeric(14,2) | null = regla del condominio |
| dia_vencimiento_propio | solo si el condominio lo permite |
| id_suscripcion (→ suscripciones) | La expensa mensual; la crea el módulo |
| restringida bool, restringida_desde date, restringida_motivo, restringida_por | Áreas comunes |
| estado | activo \| inactivo (inactivo = no emite) |
| observaciones | |

### 5.3 `condominios_alicuotas_valores` (historial de lo que rige)

| Campo | Notas |
|-------|-------|
| id_empresa, vigente_desde date | Primer día de un mes |
| tarifa_m2 numeric(10,4) | Método m² |
| monto_a_repartir numeric(14,2) | Método %; manual **o** tomado del presupuesto |
| id_presupuesto, id_presupuesto_version | Si la base es un presupuesto aprobado |
| observacion, acta | Quién/por qué |

Las cuotas de cada mes se calculan con la fila vigente en ese mes. Cambiar un valor = insertar
una fila nueva con vista previa; nunca se edita la anterior.

### 5.4 `condominios_aportes` (fondo de reserva y otros aportes)

| Campo | Notas |
|-------|-------|
| id_empresa, nombre, descripcion | Nombre interno del aporte |
| id_producto (→ productos, NOT NULL) | Servicio creado en Productos para este aporte; su nombre sale en el recibo |
| tipo | 'permanente' (solo el fondo de reserva) \| 'meta' |
| calculo | 'pct_alicuota' \| 'fijo' \| 'porcentaje' \| 'm2' \| 'manual' |
| valor | % o monto según cálculo |
| meta_total numeric(14,2) | Solo tipo meta |
| vigente_desde, vigente_hasta | |
| plazos_permitidos | jsonb `[1,6,12,24]` |
| recargos_plazo | jsonb `{"6":{"tipo":"pct","valor":0}, "12":{"tipo":"pct","valor":4}, "24":{"tipo":"valor","valor":35.00}}` |
| beneficio_anticipado_tipo, beneficio_anticipado_valor | 'pct' \| 'valor' |
| estado | activo \| cerrado |

### 5.5 `condominios_aportes_unidades` (plan de cada unidad en un aporte con meta)

| Campo | Notas |
|-------|-------|
| id_aporte, id_unidad, id_empresa | UNIQUE (aporte, unidad) |
| monto_asignado | Lo que le toca por su alícuota/m²/manual |
| plazo_elegido, recargo_aplicado, monto_total | |
| estado | pendiente \| en_curso \| pagado \| cancelado |

### 5.6 `condominios_cargos` (cada concepto que se cobra a una unidad en un mes)

Es la pieza central: **lo que el módulo decide cobrar**, independiente del documento donde salga.

| Campo | Notas |
|-------|-------|
| id_empresa, id_unidad, periodo (AAAAMM) | |
| concepto | 'ordinaria' \| 'fondo_reserva' \| 'aporte' \| 'interes' \| 'multa' \| 'descuento' |
| id_aporte, id_aporte_unidad, id_multa_catalogo | según concepto |
| descripcion | Texto de la línea del recibo |
| base, tasa, dias, desde, hasta | Solo intereses (para explicar el cálculo) |
| monto numeric(14,2) | |
| id_producto | Producto elegido para el concepto (configuración, multa o aporte; ver §7) |
| estado | pendiente \| emitido \| anulado |
| id_documento_tipo ('recibo' \| 'factura'), id_documento, id_documento_detalle | Dónde salió |
| fecha_vencimiento | Copia de la del documento |

### 5.7 Otras tablas

- `condominios_multas_catalogo`: id_empresa, nombre, valor, descripcion, id_producto (→ productos,
  el servicio con el que se factura esa multa), estado.
- `condominios_restricciones_log`: id_unidad, accion (marcar/quitar), fecha, motivo, usuario.
- `tasas_interes_legal` (**global**, sin id_empresa): vigente_desde, tasa_mensual, fuente,
  observación. Se mantiene desde `/config`; la configuración del condominio con tipo 'legal'
  toma la vigente a la fecha del cargo.

### 5.8 Cambios en tablas existentes

- `suscripciones.id_unidad` (→ condominios_unidades, nullable): marca la suscripción como
  gestionada por Condominios.
- `suscripciones_detalle.id_cargo` (→ condominios_cargos, nullable): enlaza la línea con el
  cargo que la originó (así la NC o la anulación saben qué cargo liberar).
- `presupuestos.base_alicuotas` ya existe (reservado en la fase 1 de Presupuestos).

## 6. Flujos

### 6.1 Activar el condominio

1. Crear en **Productos** los servicios de los conceptos que se vayan a cobrar (al menos
   «Alícuotas»). Guardar `condominios_config` (administrador y producto de alícuota
   obligatorios). A partir de ahí los demás módulos detectan «empresa condominio» con
   `CondominioService::activo($idEmpresa)` (cacheado en sesión).
2. Crear o cargar unidades (Excel). Al guardar una unidad con propietario, si el pagador tiene
   un programado migrado sin unidad, se ofrece **enlazarlo**: la suscripción recibe `id_unidad`
   y su monto actual queda como alícuota manual de la unidad hasta que se defina el método.
3. Registrar el valor que rige (`condominios_alicuotas_valores`) con vista previa: tabla
   unidad → cuota ordinaria → fondo → total, y suma frente al monto a repartir / presupuesto.

### 6.2 Cálculo de la cuota ordinaria de una unidad para un mes

```
método = unidad.metodo_alicuota ?? config.metodo_alicuota
m2         → tarifa_m2(vigente) × area_m2
porcentaje → monto_a_repartir(vigente) × alicuota_pct / 100
manual     → monto_manual
```

Con base en presupuesto y `reparto_manuales = repartir_resto`: `monto_a_repartir_efectivo =
monto − Σ manuales`, y el % de cada unidad no manual se normaliza sobre `Σ % no manuales`.
Redondeo a 2 decimales por unidad; la diferencia de centavos frente al total se absorbe en la
unidad de mayor alícuota (queda explicado en la vista previa).

Fondo de reserva: `pct_alicuota` → cuota ordinaria × %; `fijo` → valor; la unidad puede tener
`fondo_reserva_valor_propio`.

### 6.3 Emisión mensual (automatización `emitir_expensas`, día `dia_emision`)

Para cada unidad activa:

1. Generar los **cargos** del período: ordinaria, fondo de reserva, cuota del mes de cada
   aporte con plan en curso, intereses pendientes (si `interes_destino = siguiente_recibo`),
   multas pendientes.
2. Sincronizar la **suscripción de la unidad**: una línea por cargo (`id_producto` del concepto,
   descripción «Alícuota ordinaria Dpto 302 – octubre 2026», precio = monto, IVA según el
   producto; las expensas normalmente van con IVA 0 %, el producto lo define).
3. Dejar que **Suscripciones emita** el recibo o factura por su flujo normal (secuencial,
   SRI si es factura, correo, asiento). El cargo pasa a `emitido` con `id_documento` y
   `fecha_vencimiento` = regla del condominio (se escribe en `recibos_venta_cabecera.plazo`/
   `dias_credito` o `ventas_cabecera` según corresponda, como hace Suscripciones hoy).
4. Si `interes_destino = recibo_aparte`, los cargos de interés se emiten en un segundo
   documento del mismo día.

Reemitir el mes es idempotente: los cargos ya emitidos no se repiten; los nuevos (una multa
cargada después) esperan al mes siguiente o se emiten a mano desde la unidad.

### 6.4 Intereses de mora (misma automatización, antes de emitir)

Por cada documento vencido del condominio con saldo (`fecha_vencimiento + dias_gracia <
hoy`): `interes = saldo_capital × tasa_mensual / 30 × días`, con días desde el último corte
de interés (o el vencimiento) hasta la fecha de corte del mes, tasa = fija o legal vigente.
Solo sobre capital (cargos ordinaria/fondo/aporte/multa), nunca sobre intereses. Se genera un
cargo `interes` por documento vencido, con base, tasa, días, desde y hasta.

### 6.5 Cobro en Ingresos (y desde Cuentas por Cobrar)

Flujo normal de Ingresos, **sin cambios**: la persona elige a qué documentos aplica el pago. No
hay orden legal de imputación en el sistema (decisión del usuario: eso se maneja por fuera con
los abogados). Lo único que agrega el condominio: si `pronto_pago_activo` y la fecha de pago ≤
día N del mes de la expensa, el sistema **propone** el descuento como descuento del documento;
la persona lo acepta o lo quita antes de guardar.

### 6.6 (eliminado)

El apartado «Orden legal de imputación» se retiró del diseño.

### 6.7 Aportes con meta

1. Crear el aporte (meta, cálculo, plazos, recargos, beneficio).
2. **Asignar**: el sistema calcula `monto_asignado` por unidad (vista previa; la suma debe
   igualar la meta salvo centavos, que se absorben como en §6.2).
3. Cada unidad elige su **plazo** (por defecto el mayor permitido); se arma su cronograma de
   cuotas mensuales = (asignado + recargo) / plazo, que entran como cargos `aporte` en la
   emisión mensual.
4. **Pago anticipado**: desde la unidad, «Liquidar aporte»: saldo pendiente − beneficio
   (% o valor) → un cargo único que se emite en un documento aparte; las cuotas futuras pasan
   a `cancelado`.
5. Seguimiento: recaudado vs. meta, unidades al día / atrasadas.

### 6.8 Nota de crédito y anulación

Si se anula o se emite NC sobre un documento de expensas, los cargos enlazados por
`suscripciones_detalle.id_cargo` vuelven a `pendiente` (se reemiten el mes siguiente) o a
`anulado` si la NC es por error del cargo. Los intereses ya calculados sobre ese documento se
anulan. Esto reutiliza los enganches que ya tienen Factura/Recibo/NC con Suscripciones.

### 6.9 Restricción de áreas comunes

Manual desde la unidad o Cuentas por Cobrar (fecha de notificación obligatoria, motivo), o
automática en la emisión mensual si `restriccion_auto` y la unidad supera `restriccion_meses`
de expensas vencidas. Se quita a mano o automáticamente al quedar sin saldo vencido. Listado
imprimible «Unidades con restricción» (PDF/Excel) para administración y guardianía. Todo queda
en `condominios_restricciones_log` y en `log_sistema`.

### 6.10 Liquidación para cobro judicial

Desde Cuentas por Cobrar, por unidad, cuando hay ≥ 1 expensa vencida (umbral configurable;
el reglamento suele exigir 2 o más). PDF con: datos del condominio y de la unidad,
propietario (responsable legal) y pagador, tabla por mes y concepto (ordinaria, fondo,
aportes, intereses con base/tasa/días, multas), totales, fecha de corte, texto legal y firma
del administrador (+ presidente si está configurado). Se guarda copia en `storage/` y una
fila en `log_sistema`. El botón usa `CMG_pdfDocumento`.

## 7. Los conceptos son productos del módulo Productos

Cada concepto cobrable se factura con un **producto/servicio que el usuario crea en el módulo
Productos** (p. ej. «Alícuotas», «Fondo común de reserva», «Intereses por mora», «Multa por
ruido», «Fondo para pintar edificio»), normalmente con IVA 0 %. Así Suscripciones, los
documentos, el SRI y los asientos funcionan sin cambios, y el usuario administra sus servicios
en un solo lugar (puede reutilizar el «Alícuotas» que ya usan los condominios que facturan hoy).

**El módulo Condominios no crea productos; solo los elige:**

| Concepto | Dónde se elige el producto |
|---|---|
| Alícuota ordinaria | Configuración del condominio → `id_producto_ordinaria` (obligatorio) |
| Fondo de reserva | Configuración → `id_producto_fondo` (obligatorio si hay fondo) |
| Intereses de mora | Configuración → `id_producto_interes` (obligatorio si cobra intereses) |
| Multas | Catálogo de multas → `id_producto` por cada multa (puede ser uno genérico) |
| Cada aporte con meta | Al crear el aporte → `id_producto` |

- El selector es el buscador de productos tipo *chip* que ya usan las facturas, filtrado a
  **servicios activos** de la empresa, con **estrella de favorito**.
- Del producto salen **nombre, IVA, cuenta contable y descripción**. El texto de la línea lo
  arma el módulo: «{nombre del producto} – {unidad} – {mes año}».
- Si falta el producto, el módulo avisa «Falta el producto para X; créelo en Productos» con
  enlace, igual que el aviso de serie no configurada; no deja emitir hasta resolverlo.
- Sin prefijos ni productos automáticos; nada se agrega al módulo Productos.

## 8. Contabilidad

**Sin tipos de asiento nuevos.** El asiento lo sigue armando el motor actual a partir del
documento, y la cuenta de cada concepto se resuelve por la cascada que ya existe
(producto → categoría → configuración general): si el contador quiere que el fondo de reserva
vaya a un pasivo/patrimonio y los intereses a un ingreso financiero, le asigna esa cuenta al
producto correspondiente en Productos. Pago anticipado de N meses: la suscripción de la unidad se
marca `modalidad_cobro = anticipado` y el devengado mensual lo hace el mecanismo ya hecho
(`suscripciones_devengos`, cron fijo diario). Uso del fondo y de los aportes (compras, pagos al
banco): Compras y Contabilidad, sin nada nuevo.

## 9. Pantallas (módulo Condominios)

Listado estándar (§9 de CLAUDE.md: `cmg-table-card`, FiltrosModal, orden múltiple, columnas,
PDF/Excel) de **Unidades** como pantalla principal, con pestañas superiores (o accesos) a:

- **Configuración** (modal): datos del condominio, comprobante/serie, **productos de cada
  concepto** (alícuota, fondo, intereses; buscador tipo chip con favorito), método, vencimiento,
  intereses, multas, fondo, pronto pago, restricción, administrador/presidente.
- **Unidad** (modal estándar, botón Eliminar a la izquierda): pestañas *General* (datos,
  propietario/arrendatario/pagador con buscador de clientes tipo chip, método y monto),
  *Aportes* (planes de la unidad, liquidar aporte), *Cargos* (pendientes y emitidos por
  período, con enlace al documento), *Restricción*. Sin pestaña Información.
- **Valores que rigen** (modal): historial + «Nuevo valor desde…» con vista previa.
- **Aportes** (modal + listado): crear, asignar, seguimiento.
- **Multas**: catálogo y «Cargar multa a una unidad».
- **Emitir ahora**: vista previa del período y emisión manual (misma lógica que la automatización).
- **Carga Excel**: plantilla + carga (reusa `CargaExcel`).

Permisos: `r` ver, `w` crear unidades/cargos, `u` editar, `d` eliminar, `t` acceso total
(configuración, valores que rigen, aportes, emisión manual, restricción). Registros propios no
aplica a unidades (la cartera del condominio es una sola); sí aplica a los documentos como hoy.

## 10. Carga Excel de unidades

Columnas: `codigo*, nombre, tipo*, torre_bloque, piso, area_m2, alicuota_pct, propietario_identificacion*,
propietario_nombre, arrendatario_identificacion, arrendatario_nombre, pagador (propietario|arrendatario),
comprobante (recibo|factura|vacío), metodo (porcentaje|m2|manual|vacío), monto_manual, fondo_reserva_valor_propio,
observaciones`. Reglas: cruce de clientes por identificación (crea si no existe, con nombre
obligatorio en ese caso); Σ alícuota_pct ≤ 100 (aviso si ≠ 100); código único; vista previa
y errores por fila como en las demás cargas.

## 11. Automatizaciones

Acción `emitir_expensas` del módulo `condominios` en `HandlerFactory` (handler
`CondominiosHandler`), frecuencia mensual el `dia_emision`. Parámetros: `calcular_intereses`
(def. según config), `aplicar_restricciones` (def. según config). Un «Emitir ahora» en pantalla
ejecuta lo mismo para el período elegido. La emisión del documento la hace `SuscripcionesHandler`
como hoy (Condominios solo prepara las suscripciones y cargos antes).

## 12. Multiempresa, seguridad, auditoría

Todo filtra `id_empresa` + `eliminado = false` (`getBaseWhere`); los módulos existentes solo
muestran lo nuevo cuando `CondominioService::activo()`. Toda acción relevante va a `log_sistema`
(configuración, valores que rigen, aportes, planes, cargos manuales, restricción, liquidación).
Cálculos que leen un agregado y escriben (cargos del período, asignación de aportes) van bajo
`pg_advisory_xact_lock('condominio:' || id_empresa || ':' || periodo)` dentro de transacción.

## 13. Entregables de la fase 1 y orden de trabajo

1. SQL idempotente para pgAdmin: `database/migrations/2026MMDD_condominios.sql` (tablas §5,
   columnas en suscripciones/suscripciones_detalle, tabla global de tasa legal).
2. Repository / Rules / Service / Controller / vistas / JS del módulo (`modulos/condominios`).
3. Servicio compartido `CondominioCarteraService` (deuda por unidad, concepto y período).
4. Enganches: Suscripciones (`id_unidad`, bloqueo), Cuentas por Cobrar (unidad, restricción,
   liquidación), Ingresos (solo descuento pronto pago), Reporte de Cartera (unidad),
   Presupuestos (casilla).
5. Handler de automatización + «Emitir ahora».
6. Carga Excel de unidades.
7. Pruebas en sandbox (PdoSandbox) de: cálculo de cuotas por los tres métodos y reparto del
   resto, fondo, aportes y cronogramas, intereses con gracia y tasa legal, emisión idempotente,
   NC/anulación, restricción automática, aviso de producto faltante.
8. Manuales: `condominios.md` nuevo; actualizar suscripciones, cuentas-por-cobrar,
   reporte-cartera, presupuestos, automatizaciones, productos (nota sobre los servicios de
   condominio) e ingresos (solo si se activa pronto pago).
9. El usuario registra ruta (`config/modulos_mvc.php`), submódulo y permisos, y aplica el SQL
   en local y producción.

## 14. Puntos abiertos (no bloquean el inicio)

- **Fuente de la tasa legal**: tabla global editable desde `/config`; la actualiza el usuario
  cada mes (BCE). Se evalúa después si se puede obtener automáticamente.
- **IVA de las expensas**: lo define el producto que el usuario crea en Productos (normalmente
  0 %); el módulo no lo impone.
- **Umbral de expensas vencidas** para habilitar la liquidación judicial (def. 1, configurable).
