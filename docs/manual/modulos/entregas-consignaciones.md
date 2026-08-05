---
titulo: Entregas de Consignaciones
resumen: Resumen de solo lectura de las entregas de consignaciones, registradas desde la app móvil del repartidor o manualmente desde el sistema.
categoria: Ventas
ruta_modulo: modulos/entregas-consignaciones
tipo: modulo
visibilidad: todos
etiquetas: entregas, entrega, consignaciones, repartidor, GPS, firma, evidencia de entrega, app móvil, entregas confirmadas, resumen de entregas
version: 1.0
orden: 0
estado: activo
---

Este módulo muestra, en un solo lugar, todas las entregas de
[Consignaciones en Ventas](consignaciones-ventas.md) que ya se confirmaron —
sin importar si el repartidor las registró desde la app móvil (con GPS y firma
del cliente) o si se marcaron manualmente como "Entregada" desde el sistema.
Es de **solo lectura**: no crea, edita ni elimina nada; el registro/edición de
la entrega en sí sigue haciéndose en Consignaciones en Ventas.

## Qué es y para qué sirve

Sirve para revisar de un vistazo cuántas entregas se realizaron, cuáles vinieron
de la app móvil y cuáles se marcaron manualmente, cuáles quedaron con evidencia
incompleta (sin firma o sin ubicación) y cuánto está tardando el proceso desde
que se emite la consignación hasta que se entrega. Reutiliza la misma
información que ya se ve en la pestaña "Entrega" del modal de Consignaciones en
Ventas, pero a nivel de listado y con filtros propios.

## Requisitos previos

- Debe existir al menos una consignación marcada como "Entregada" (desde la app
  móvil o manualmente) para que aparezcan filas en el listado.
- Si un usuario no tiene el permiso de **acceso total** en este módulo, debe
  estar vinculado como responsable de traslado (ver más abajo, sección
  Permisos) para poder ver alguna entrega.

## Cómo se usa

1. Abra el módulo desde el menú. La tabla muestra las entregas más recientes
   primero.
2. Use el buscador para filtrar por cliente, N° de consignación, responsable,
   producto, canal o rango de fechas; o use los selectores de **Año** / **Mes**
   como atajo rápido de fecha.
3. Haga clic en una fila para ver el detalle completo: mapa con el punto de
   entrega, firma de recepción (si existe) y los datos de quién y cuándo la
   registró.
4. Use los botones **PDF** / **Excel** para exportar el listado con el filtro
   actual aplicado.

## Campos del listado

| Campo | Qué significa |
|-------|----------------|
| Fecha/hora entrega | Momento en que se capturó la evidencia (hora del celular si vino de la app, o de guardado si fue manual). |
| Consignación | Serie-secuencial de la consignación entregada. |
| Cliente | Cliente de la consignación. |
| Responsable | Responsable de traslado (repartidor) asignado a la consignación. |
| Canal | **App móvil** (registrada por el repartidor con GPS/firma) o **Web** (marcada manualmente desde el sistema). |
| Firma | Si existe firma de recepción capturada. |
| GPS | Si existe ubicación (latitud/longitud) capturada. |
| Registrado por | Usuario que quedó como autor de la evidencia. |
| Observaciones | Nota libre de la entrega, si la hay. |

### KPIs

- **Entregas**: total del rango filtrado (excluye evidencias anuladas).
- **App móvil** / **Web (manual)**: desglose por canal.
- **Pendientes**: consignaciones en estado *Emitida* que aún no se han entregado.
- **Tiempo prom. emisión→entrega**: horas promedio entre la fecha de emisión de
  la consignación y la fecha/hora en que se confirmó la entrega.
- **Evidencia incompleta**: entregas sin GPS, o de canal móvil sin firma (una
  entrega web nunca captura firma, así que eso solo no cuenta como incompleta).

## Permisos

Módulo de solo lectura: solo existe el permiso **Ver**. Lo que cambia con
**acceso total** es el alcance de lo que se ve:

- **Con acceso total**: ve las entregas de toda la empresa.
- **Sin acceso total**: ve solo las entregas de los responsables de traslado a
  los que está vinculado (tabla `usuarios_responsables_traslado`, la misma que
  usa la app móvil de entregas). Si no está vinculado a ningún responsable, no
  ve ninguna fila.

## Reglas de negocio

- No se puede crear, editar ni eliminar nada desde aquí: la fuente de verdad es
  `consignaciones_ventas_entregas`, alimentada por
  [Consignaciones en Ventas](consignaciones-ventas.md) y por la app móvil.
- El filtro por **producto** busca entre los productos que contiene la
  consignación entregada (no un campo directo de la entrega).
- Los **Año/Mes** son un atajo sobre el mismo filtro de fecha de entrega; si se
  usa el rango "Fecha entrega" (desde/hasta) del buscador, ese reemplaza al de
  Año/Mes (comparten el mismo criterio, no se combinan).

## Integraciones con otros módulos

- **Consignaciones en Ventas**: fuente de los datos de cabecera (cliente,
  responsable, estado) y de la tabla de evidencia de entrega.
- **App móvil de entregas** (`api/v1/entregas`): origen de las entregas con
  canal `movil`.

## Errores frecuentes

- **"No se encontraron entregas" con datos que sí existen**: revise si el
  usuario tiene acceso total; si no lo tiene, debe estar vinculado como
  responsable de traslado a las consignaciones que busca ver.
- **La firma no carga en el detalle**: la entrega no tiene `firma_path` (las
  entregas registradas manualmente desde la web nunca tienen firma).

## Historial de cambios

- **1.0** — Versión inicial: KPIs, listado filtrable (fecha, año/mes, producto,
  cliente, responsable, canal), detalle con mapa y firma, export PDF/Excel.
