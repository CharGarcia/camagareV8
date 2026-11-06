---
titulo: Métricas del sistema
resumen: Tablero de toda la plataforma — usuarios conectados y activos, empresas con movimiento, documentos emitidos, productos más vendidos, SRI y errores.
categoria: Configuración
ruta_modulo: config/metricas-sistema
tipo: modulo
visibilidad: superadmin
etiquetas: métricas, salud del sistema, rendimiento, base de datos, servidor, cron, módulos más usados, módulos sin uso, mrr, ingreso recurrente, suscripciones atrasadas, morosidad, payphone, nuvei, tendencias, crecimiento, caída, abandono, histórico, comparativo, empresas conectadas, horas de uso, tiempo de uso, dau, mau, estadísticas, tablero, dashboard, indicadores, kpi, usuarios conectados, en línea, activos, inactivos, empresas activas, movimiento, ranking, productos más vendidos, documentos emitidos, facturas emitidas, sri, rechazos, errores, uso del sistema, plataforma, superadmin
version: 1.4
orden: 0
estado: activo
---

Tablero de solo lectura con las cifras de **toda la plataforma**: cruza todas las
empresas a la vez. Sirve al superadministrador para saber cuánta gente está usando el
sistema, qué empresas trabajan y cuáles no, cuántos documentos se emiten y cómo
responde el SRI. Complementa a **Errores del sistema** y a **Auditoría del sistema**.

## Qué es y para qué sirve

Es una tarjeta de **Configuración** que reúne en pestañas los indicadores globales:

- **Usuarios**: conectados ahora (web y móvil), activos hoy, en 7 y en 30 días,
  usuarios que no ingresan hace más de 30 o 90 días y los que nunca ingresaron.
  Gráficos de inicios de sesión por día y por hora del día, y la empresa en la que
  está trabajando cada usuario conectado.
- **Empresas**: activas, **conectadas ahora** (con quiénes están trabajando), con y
  sin movimiento en el periodo, empresas con mayor uso (horas estimadas), ranking de las que más
  documentos emiten, vigencia vencida o por vencer, cupo de usuarios casi lleno y
  altas por mes.
- **Documentos**: facturas, notas de crédito y de débito, retenciones, guías,
  liquidaciones y facturas de reembolso autorizadas, más los documentos internos
  (compras, recibos, proformas, pedidos). Facturas del POS frente a facturas normales.
- **Productos**: los más vendidos en todo el sistema, por cantidad y por monto, con la columna **Empresa** (las empresas que vendieron ese producto en el rango).
- **SRI**: comprobantes autorizados, rechazados y en proceso, tasa de rechazo y los
  mensajes de rechazo más frecuentes.
- **Errores**: errores técnicos por día y las rutas donde más se falla (con enlace a
  **Errores del sistema** para ver el detalle).
- **Tendencias**: evolución de documentos, ventas, empresas y usuarios activos, SRI y
  errores, comparada con el periodo anterior de igual largo, más las empresas que más
  crecen y las que más caen (riesgo de abandono), y el total de documentos por tipo.
  Admite **todo el historial** (opción *Todo el historial* del Periodo), con los
  documentos migrados incluidos; con más de 5 años los gráficos se agrupan por año.
- **Uso de módulos**: submódulos más abiertos, los que nadie abrió en el periodo
  (ordenados por cuántos usuarios tienen permiso: sirve para decidir qué mejorar o
  retirar), aperturas por módulo y lo que más se crea, edita o elimina según la
  auditoría.
- **Comercial**: suscripciones de la plataforma (activas, ingreso mensual recurrente
  sin IVA, cobros atrasados y los de los próximos 30 días), cobros por mes, pagos
  reportados por los clientes, empresas con plan sin costo o demo y cobros en línea
  (Payphone / Nuvei) de todas las empresas.
- **Salud técnica**: tamaño y conexiones de la base de datos, aciertos de caché,
  disco, servidor PHP (OPcache, APCu, límites), estado de la tarea automática (cron),
  comprobantes atascados en el SRI, consultas lentas en curso, consultas que más
  tiempo consumen (si la base tiene `pg_stat_statements`) y tablas más pesadas.

## Requisitos previos

- Ser usuario **nivel 3 (superadministrador)**. La tarjeta y los datos son exclusivos
  de ese nivel.

## Cómo se usa

1. Entrar a **Configuración → Métricas del sistema**.
2. Elegir el **Periodo** (Hoy, Últimos 7 o 30 días, Este mes, Mes anterior, Este año,
   Últimos 12 meses) o poner las fechas **Desde** / **Hasta** a mano.
3. Pulsar **Mostrar**. El pie de la tarjeta superior muestra el resumen y la pestaña
   abierta se recalcula. Las demás pestañas se calculan al abrirlas.
4. El botón con la flecha circular **recalcula sin usar la caché** (ver *Reglas*).
5. A la derecha de los filtros se indica cuándo se calcularon los datos y si vienen
   de la caché.

## Campos del formulario

| Campo | Qué hace |
|---|---|
| Periodo | Atajo que llena Desde y Hasta. |
| Desde / Hasta | Rango de fechas. Máximo 366 días (sin tope en Tendencias; si se elige más de un año, la pantalla pasa sola a esa pestaña). Los números de cada pestaña son **del rango elegido**: para ver documentos antiguos (p. ej. migrados) hay que ampliar las fechas o usar *Todo el historial*. |
| Mostrar | Calcula con el rango elegido. |
| Recalcular | Igual que Mostrar, pero ignora la caché. |

## Permisos

Solo nivel 3. Un usuario de otro nivel que abra la dirección vuelve a Configuración
con el aviso *"No tiene permisos"*.

## Reglas de negocio

- **Solo producción**: los documentos se cuentan únicamente si son del ambiente de
  producción del SRI, igual que todos los reportes del sistema.
- **Emitido** = documento autorizado por el SRI. Los borradores y pendientes salen
  aparte, en la columna *Sin autorizar*. Los documentos eliminados no cuentan.
- **Conectado ahora** = usuario con una sesión abierta que tuvo actividad en los
  últimos 15 minutos (el sistema registra la actividad cada 5 minutos).
- **Usuario activo hoy / 7 / 30 días**: según la última actividad de sus sesiones.
- **Empresa con documentos**: emitió al menos un documento de producción en el rango.
- **Empresa conectada** = algún usuario trabajó en ella en los últimos 15 minutos. El
  sistema anota, cada 5 minutos como máximo, en qué empresa está trabajando cada
  usuario (web y app móvil). **No cuenta a los superadministradores**, que entran a
  cualquier empresa para dar soporte.
- **Horas de uso** (estimadas): cada anotación equivale a una ventana de 5 minutos
  con actividad; las horas son la suma de esas ventanas.
- **Empresa sin movimiento**: activa, no demo, sin documentos **y** sin usuarios
  activos en el rango.
- La actividad por empresa se registra **desde el día en que se activó** (se indica
  en pantalla); no hay datos anteriores.
  Cada establecimiento cuenta como una empresa; el indicador *RUC distintos* agrupa
  los establecimientos de un mismo RUC.
- **Productos más vendidos**: cada empresa tiene su propio catálogo, así que se
  agrupan por la descripción del producto (sin distinguir mayúsculas ni espacios).
- **SRI**: se toma el **último estado** de cada comprobante enviado en el rango. Si
  un comprobante fue devuelto y luego autorizado, cuenta como autorizado.
- **Caché**: cada cálculo se guarda 2 minutos para no recargar la base de datos si
  varias personas consultan a la vez. Una consulta que tarde más de 25 segundos se
  corta y pide un rango menor.
- Con rangos de más de 3 meses los gráficos se agrupan por mes en lugar de por día.
- **Tendencias usa un resumen diario precalculado** (tabla `metricas_diarias`), que la
  tarea automática del servidor genera cada noche desde la 01:00 y recalcula los
  últimos 3 días (por si un documento se autorizó o anuló con atraso). El día de hoy
  no se incluye.
- **El historial se rellena solo**: entre las 00:00 y las 05:59, en cada pasada de la
  tarea automática se calcula un mes hacia atrás, hasta llegar al documento más
  antiguo del sistema (los migrados incluidos). Varios años de historia quedan listos
  en una sola madrugada; después se vuelve a revisar una vez por día por si se migró
  una empresa con documentos más antiguos. Si se quiere tenerlo antes, se puede
  forzar por consola: `php app/cron/metricas_rellenar.php 2017-01-01` (se puede
  repetir sin duplicar).
- En Tendencias, *SRI autorizados / rechazados* cuenta respuestas del SRI de ese día
  (un comprobante reenviado puede contar más de una vez), a diferencia de la pestaña
  SRI, que toma el último estado de cada comprobante. *Empresas activas* y *usuarios
  habilitados* solo existen desde que corre el resumen (no se pueden reconstruir
  hacia atrás).

## Integraciones con otros módulos

- **Errores del sistema**: la pestaña Errores resume esa bitácora y enlaza a ella.
- **Empresas del sistema**: vigencia, cupo de usuarios (*máximo de usuarios*) y
  estado salen de la ficha de cada empresa.

## Errores frecuentes

- *"El rango no puede superar 366 días"*: acortar las fechas.
- *"No se pudieron calcular las métricas. Intente con un rango menor."*: la consulta
  tardó demasiado; probar con un periodo más corto.
- *"El registro de actividad por empresa todavía no está activo"*: falta aplicar en la
  base el SQL `2026-11-05_metricas_actividad_usuario.sql`.
- *No salen los documentos migrados*: los indicadores son del rango de fechas elegido
  (por defecto, este mes). Elegir *Todo el historial* en Tendencias. Si aun así faltan,
  puede que se hayan migrado con la empresa en ambiente de pruebas: revisarlo con
  `database/diagnosticos/20261105_metricas_documentos_migrados.sql`.
- *Errores 24 h alto* (Salud técnica): cuenta los errores guardados en **Errores del
  sistema** en las últimas 24 horas (fallos fatales y excepciones que el sistema
  registra, incluidas validaciones rechazadas). Para ver cuáles son, agruparlos con
  `database/diagnosticos/20261105_errores_ultimas_24h.sql` o abrir la pestaña Errores.
- *Comercial vacío*: las suscripciones de la plataforma se buscan en la empresa que
  cobra (la elegida en *Empresas del sistema → Cobro y vigencia*, con todos sus
  establecimientos). Si ninguna empresa la tiene asignada, la pestaña lo avisa.
- *Uso de módulos en cero*: las aperturas se registran desde que existe Accesos
  frecuentes y no cuentan a los superadministradores.
- *Tareas automáticas «no visible desde la web»*: las marcas del cron viven en el
  temporal del servidor y la web puede no verlo. La señal fiable es el indicador
  *Tarea automática (cron)*, que mira la última vez que se calculó el resumen diario
  (*Atrasado* si pasaron más de 26 horas).
- *"El resumen diario todavía no está activo"* (Tendencias): falta aplicar
  `2026-11-05_metricas_diarias.sql`.
- *Tendencias sale en cero en fechas antiguas*: el relleno automático todavía no llegó
  a esas fechas (se completa en la primera madrugada) o la tarea automática no está
  corriendo; la nota de la pestaña indica desde qué fecha hay datos.
- *Empresas conectadas en 0 aunque hay gente trabajando*: si solo hay
  superadministradores conectados, no cuentan.

## Historial de cambios

- **v1.0** (2026-11-05): Versión inicial (fase A, cálculo en vivo). Pestañas Usuarios,
  Empresas, Documentos, Productos, SRI y Errores; índices por fecha en
  `sesiones_activas` y `sri_envio_log`.
- **v1.1** (2026-11-05): Actividad por empresa (fase B). Tabla
  `metricas_actividad_usuario`, que llenan la web y la app móvil; empresas conectadas
  ahora y hoy, empresas con mayor uso, horas de uso estimadas, empresa actual de cada
  usuario conectado y "sin movimiento" que también considera el uso.
- **v1.2** (2026-11-05): Resumen diario (fase C). Tabla `metricas_diarias`, paso
  nocturno en la tarea automática, script de relleno del historial y pestaña
  Tendencias (comparativo con el periodo anterior, empresas que crecen y que caen,
  rangos de hasta 5 años).
- **v1.3** (2026-11-05): Pestañas Uso de módulos, Comercial y Salud técnica (sin tablas
  nuevas). El historial del resumen diario se rellena solo desde la tarea automática
  (madrugada), incluidos los documentos migrados; el script de consola queda opcional.
  Tendencias admite todo el historial (agrupado por año) y muestra el total por tipo.
- **v1.4** (2026-11-06): La pantalla usa todo el ancho de la ventana. En Productos, las
  tablas de más vendidos (por cantidad y por monto) muestran el nombre de la empresa.
