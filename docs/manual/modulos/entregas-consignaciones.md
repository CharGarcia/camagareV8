---
titulo: Entregas de Consignaciones
resumen: Consignaciones pendientes de entregar (por defecto) y entregadas, con la evidencia registrada desde la app móvil del repartidor o manualmente desde el sistema.
categoria: Ventas
ruta_modulo: modulos/entregas-consignaciones
tipo: modulo
visibilidad: todos
etiquetas: entregas, entrega, pendientes de entrega, por entregar, consignaciones, repartidor, GPS, firma, evidencia de entrega, app móvil, entregas confirmadas, resumen de entregas
version: 1.4
orden: 0
estado: activo
---

Este módulo muestra las [Consignaciones en Ventas](consignaciones-ventas.md)
desde el punto de vista de la entrega. **Al abrirlo lista solo las consignaciones
pendientes de entregar** (estado *Emitida*): es la cola de trabajo del
repartidor. Con el selector de estado también se pueden ver las ya entregadas,
con la evidencia que registró el repartidor desde la app móvil (GPS y firma del
cliente) o la que quedó al marcarlas manualmente como "Entregada" desde el
sistema. Es de **solo lectura**: no crea, edita ni elimina nada; el registro de
la entrega en sí sigue haciéndose en Consignaciones en Ventas o en la app.

## Qué es y para qué sirve

Sirve para saber de un vistazo **qué queda por entregar** (a quién, a dónde,
para cuándo y hace cuántos días se emitió), y, cambiando el estado, revisar
cuántas entregas se hicieron, cuáles vinieron de la app móvil y cuáles se
marcaron manualmente, cuáles quedaron con evidencia incompleta (sin firma o sin
ubicación) y cuánto está tardando el proceso desde que se emite la consignación
hasta que se entrega.

## Requisitos previos

- Para que aparezcan filas en la vista por defecto debe existir al menos una
  consignación en estado *Emitida* (pendiente de entrega).
- Si un usuario debe ver **solo las consignaciones de ciertos repartidores**,
  vincúlelo a esos responsables de traslado desde `config/usuarios-sistema`
  (ver más abajo, sección Permisos). Sin vínculo ve todas las de la empresa.

## Cómo se usa

1. Abra el módulo desde el menú. La tabla muestra las consignaciones
   **pendientes de entregar**, las más recientes primero. La columna **Días**
   resalta en amarillo las que llevan 3 días o más sin entregar y en rojo las de
   7 o más.
2. Use el selector **Pendientes / Entregadas / Todas** (o el campo *Estado de
   entrega* del buscador) para cambiar qué se lista. Al volver a "Pendientes" el
   filtro desaparece porque es el valor por defecto.
3. Use el buscador para filtrar por cliente, dirección, N° de consignación,
   responsable, producto, canal o rango de fechas (emisión, entrega programada
   o entrega real); o use los selectores de **Año** / **Mes** como atajo rápido
   sobre la fecha de emisión.
4. Haga clic en una fila para ver el detalle: datos de la consignación (cliente,
   dirección, entrega programada, responsable, días en espera) y, si ya fue
   entregada, la evidencia: mapa con el punto de entrega, firma de recepción y
   quién y cuándo la registró.
5. Use los botones **PDF** / **Excel** para exportar el listado con el filtro
   actual aplicado (el reporte indica si contiene pendientes, entregadas o todas).

## Campos del listado

| Campo | Qué significa |
|-------|----------------|
| Emisión | Fecha de emisión de la consignación. |
| Consignación | Serie-secuencial de la consignación. |
| Cliente | Cliente de la consignación. |
| Dirección | Dirección registrada en la ficha del cliente (a dónde se entrega). |
| Responsable | Responsable de traslado (repartidor) asignado a la consignación. |
| Entrega programada | Fecha (y franja horaria, si se indicó) prevista para la entrega en la consignación. |
| Estado | **Pendiente** (Emitida), **Entregada** o **Facturada**. |
| Días | Días desde la emisión hasta la entrega; si sigue pendiente, hasta hoy. |
| Fecha/hora entrega | Momento en que se capturó la evidencia (hora del celular si vino de la app, o de guardado si fue manual). Vacío mientras esté pendiente. |
| Canal | **App móvil** (registrada por el repartidor con GPS/firma) o **Web** (marcada manualmente desde el sistema). |
| Firma | Si existe firma de recepción capturada. |
| GPS | Si existe ubicación (latitud/longitud) capturada. |
| Registrado por | Usuario que quedó como autor de la evidencia. |
| Observaciones | Nota libre de la entrega, si la hay. |

Los campos de evidencia (Fecha/hora entrega, Canal, Firma, GPS, Registrado
por, Observaciones) se muestran vacíos ("—") en las consignaciones pendientes.
Todas las columnas pueden ocultarse por usuario desde el botón de columnas.

### KPIs

Los KPIs se calculan sobre el rango filtrado (cliente, fechas, producto,
responsable…) **sin aplicar el filtro de estado**: siempre muestran pendientes y
entregadas a la vez, para tener la foto completa aunque la tabla liste solo una
parte.

- **Pendientes de entregar**: consignaciones en estado *Emitida*.
- **Entregadas**: consignaciones en estado *Entregada* o *Facturada*.
- **App móvil** / **Web (manual)**: desglose de las entregadas con evidencia,
  por canal.
- **Tiempo prom. emisión→entrega**: horas promedio entre la fecha de emisión y
  la fecha/hora en que se confirmó la entrega.
- **Evidencia incompleta**: entregadas sin evidencia registrada, sin GPS, o de
  canal móvil sin firma (una entrega web nunca captura firma, así que eso solo
  no cuenta como incompleta).

## Permisos

Módulo de solo lectura: solo existe el permiso **Ver**. El alcance de lo que se
ve **no** lo define el flag "acceso total" del permiso, sino el **vínculo del
usuario con responsables de traslado** que se administra en
`config/usuarios-sistema` (ficha del usuario, pestaña *Responsables de
traslado*; tabla `usuarios_responsables_traslado`, la misma que usa la app
móvil de entregas):

- **Superadministrador (nivel 3)**: ve todas las consignaciones de la empresa,
  esté o no vinculado.
- **Usuario vinculado a uno o más responsables**: ve solo las consignaciones de
  esos responsables, aunque tenga "acceso total" en el módulo.
- **Usuario sin ningún vínculo**: ve todas las consignaciones de la empresa.

Ese alcance vale para todo lo que sirve el módulo, **incluida la imagen de la
firma** de una entrega: si la entrega no es de uno de sus responsables, la
firma no se muestra aunque se pida por su enlace directo.

El mismo alcance rige en la **app móvil** (`api/v1/entregas`): el repartidor
vinculado solo puede ver, abrir y registrar la entrega de las consignaciones
de sus responsables; sin vínculo (o nivel 3) puede con todas.
Una consignación fuera de su alcance responde *«Consignación no encontrada»*,
igual que una que no existe.

## Reglas de negocio

- No se puede crear, editar ni eliminar nada desde aquí: la fuente de verdad son
  `consignaciones_ventas` (estado) y `consignaciones_ventas_entregas`
  (evidencia), alimentadas por
  [Consignaciones en Ventas](consignaciones-ventas.md) y por la app móvil.
- **Pendiente** = consignación en estado *Emitida*, el mismo criterio que usa
  la app móvil para armar la lista del repartidor. Las consignaciones en
  *Borrador* o *Anulada* no aparecen en ningún estado del filtro.
- Cada consignación aparece **una sola vez**. Si tiene varias evidencias (p. ej.
  reintentos desde el celular), se muestra la confirmada; si no hay confirmada,
  la más reciente válida.
- El filtro por **producto** busca entre los productos que contiene la
  consignación (no un campo directo de la entrega).
- Los **Año/Mes** son un atajo sobre el mismo filtro de *Fecha emisión*; si se
  usa el rango desde/hasta del buscador, ese reemplaza al de Año/Mes (comparten
  el mismo criterio, no se combinan). El filtro *Fecha entrega real* solo
  aplica a las entregadas: combinado con "Pendientes" no devuelve filas.

## Integraciones con otros módulos

- **Consignaciones en Ventas**: fuente de los datos de cabecera (cliente,
  responsable, estado, entrega programada) y de la tabla de evidencia de entrega.
- **App móvil de entregas** (`api/v1/entregas`): origen de las entregas con
  canal `movil`; su lista de pendientes usa el mismo criterio que este módulo.

## Errores frecuentes

- **"No hay consignaciones pendientes de entregar" pero sí hay entregas
  hechas**: es el comportamiento esperado; cambie el selector a *Entregadas* o
  *Todas* para verlas.
- **No aparecen consignaciones que sí existen**: revise en
  `config/usuarios-sistema` a qué responsables de traslado está vinculado el
  usuario: si tiene vínculos, solo ve las de esos responsables. Para que vea
  todas, quítele los vínculos (o hágalo nivel 3).
- **La firma no carga en el detalle**: la entrega no tiene `firma_path` (las
  entregas registradas manualmente desde la web nunca tienen firma).

## Historial de cambios

- **1.4** — El listado pasa a ser de **consignaciones**, no de evidencias, y
  **por defecto muestra solo las pendientes de entregar** (estado *Emitida*).
  Selector *Pendientes / Entregadas / Todas* (filtro `estado:` del buscador).
  Columnas nuevas: Emisión, Dirección, Entrega programada, Estado y Días en
  espera (con resaltado a partir de 3 y 7 días). Filtros nuevos: dirección,
  fecha de emisión y entrega programada; Año/Mes ahora filtran por fecha de
  emisión. El detalle muestra los datos de la consignación y, si está
  entregada, su evidencia. Cada consignación aparece una sola vez aunque
  tenga varias evidencias. Los KPIs cuentan consignaciones (Entregadas =
  estado *Entregada* o *Facturada*; Evidencia incompleta incluye entregadas
  sin evidencia) y no dependen del estado seleccionado. PDF/Excel exportan
  las columnas nuevas y titulan según el estado filtrado.

- **1.3** — El alcance del listado, los KPIs, las exportaciones y la firma ya
  no depende del flag "acceso total" sino del **vínculo con responsables de
  traslado** de `config/usuarios-sistema`: nivel 3 y usuarios sin vínculo ven
  todas las entregas de la empresa; usuarios vinculados, solo las de sus
  responsables. Antes, un usuario sin acceso total y sin vínculo no veía nada.
  La misma regla aplica en la app móvil (listado, detalle y registro de la
  entrega).

- **1.2** — En la **app móvil**, abrir una consignación y registrar su entrega
  respetan el alcance por responsable de traslado, igual que el listado: antes,
  un repartidor sin acceso total podía abrir cualquier consignación de la
  empresa —y marcarla como entregada con su firma— si llegaba a ella por su
  número interno.

- **1.1** — La imagen de la **firma** de una entrega respeta el mismo alcance
  que el listado: sin acceso total solo se ve la de los responsables de
  traslado del usuario.

- **1.0** — Versión inicial: KPIs, listado filtrable (fecha, año/mes, producto,
  cliente, responsable, canal), detalle con mapa y firma, export PDF/Excel.
