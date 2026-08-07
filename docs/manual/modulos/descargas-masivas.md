---
titulo: Descargas Masivas
resumen: Descarga en un solo ZIP el PDF y/o XML de varios documentos (facturas, notas de crédito, retenciones, guías, etc.) de un rango de fechas.
categoria: Documentos
ruta_modulo: modulos/descargas-masivas
tipo: modulo
visibilidad: todos
etiquetas: descarga masiva, descargas masivas, zip, exportar facturas, exportar xml, exportar pdf, backup de comprobantes, descargar todas las facturas, descargar notas de crédito, descargar retenciones, descargar guías de remisión, descargar compras
version: 1.0
orden: 0
estado: activo
---

Descargas Masivas arma un ZIP con el PDF y/o XML de varios documentos electrónicos
de un mismo tipo, elegidos por un rango de fechas. Sirve para bajar de una sola
vez, por ejemplo, todas las facturas de un mes para entregarlas al contador, sin
tener que abrir documento por documento.

## Qué es y para qué sirve

Cubre 8 tipos de documento: Facturas de Venta, Notas de Crédito, Notas de
Débito, Guías de Remisión, Retenciones de Venta, Retenciones de Compra,
Liquidaciones de Compra y Compras (facturas recibidas de proveedor).

El archivo se genera **al momento** de la descarga y **no queda guardado** en
el sistema: no crea ninguna tabla ni deja un ZIP pendiente en el servidor. Cada
descarga es un proceso de una sola vez — ver "Reglas de negocio".

## Cómo se usa

1. Elegir el **tipo de documento**.
2. Elegir el **rango de fechas** (sin límite de tamaño: un año con pocos
   documentos es tan válido como un solo día con muchos).
3. Elegir el **formato**: PDF, XML o ambos.
4. Presionar **Verificar cantidad**. El sistema cuenta cuántos documentos
   cumplen el filtro sin generar nada todavía.
5. Si la cantidad está dentro del límite, aparece el botón **Descargar ZIP**.
   Si lo supera, hay que acortar el rango de fechas.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo de documento | Sí | Uno de los 8 tipos soportados; una descarga trae un solo tipo a la vez |
| Desde / Hasta | Sí | Rango de fecha de emisión del documento |
| Formato | Sí | PDF, XML, o ambos (un PDF y un XML por documento dentro del mismo ZIP) |

## Permisos

Requiere permiso de **ver** sobre `modulos/descargas-masivas`. Si el usuario
**no** tiene acceso total en este submódulo, la descarga solo incluye los
documentos que él mismo creó en el módulo de origen (mismo criterio de
"registros propios" que el resto del sistema). Con acceso total, incluye los de
toda la empresa. El permiso se administra en `/config/permisos-modulos`.

## Reglas de negocio

- **Sin límite de rango de fechas; sí de cantidad de documentos.** Cada
  descarga se acota por cuántos documentos matchean el filtro, no por cuántos
  días/meses se seleccionaron. El límite es configurable
  (`config/app.php` → `descargas_masivas.max_documentos_pdf` /
  `max_documentos_xml`, 500 y 2000 por defecto). XML tiene un tope más alto
  porque solo lee el XML ya autorizado; PDF genera el documento con TCPDF, más
  lento.
- **Solo documentos válidos.** Se excluyen borradores, anulados y rechazados
  (según el tipo); solo se incluyen los autorizados/aprobados.
- **Nada se guarda.** El ZIP se arma en un archivo temporal
  (`storage/tmp/descargas_masivas/`) y se borra apenas se transmite al
  navegador (incluso si la descarga falla a medio camino). No hay historial de
  descargas para volver a bajar el mismo ZIP: hay que generarlo de nuevo.
- **XML**: se sirve el XML autorizado ya guardado en el documento; si no está
  disponible, se regenera en memoria (sin guardarlo) para los tipos que lo
  permiten. Retenciones de Venta y Compras no regeneran XML: ese archivo lo
  emite la otra parte (el cliente que retiene, o el proveedor/SRI), así que si
  nunca se recibió, simplemente no hay XML que incluir para ese documento.
- **Plantillas de PDF personalizadas no se usan aquí**: la descarga masiva
  siempre usa el diseño de PDF estándar de cada tipo de documento, no la
  plantilla configurada en `/config/plantillas-pdf` (a diferencia de la
  descarga individual de cada módulo).
- Cada descarga queda registrada en la auditoría (`log_sistema`): usuario,
  tipo, rango de fechas, formato y cantidad de documentos.

## Integraciones con otros módulos

Lee de Facturas de Venta, Notas de Crédito, Notas de Débito, Guías de
Remisión, Retenciones de Venta, Retenciones de Compra, Liquidaciones de
Compra y Compras. No escribe en ninguno de ellos.

## Errores frecuentes

- **"Se encontraron X documentos y el máximo por descarga es Y"**: acortar el
  rango de fechas y volver a verificar.
- **"No se encontraron documentos con esos filtros"**: revisar que el tipo y
  el rango sean correctos, y que existan documentos **autorizados** (no
  borradores) en ese rango.
- **El PDF de algún documento sale sin logo o sin la leyenda personalizada**:
  la descarga masiva arma el encabezado de empresa con el primer
  establecimiento configurado; si el documento pertenece a otro
  establecimiento con logo/leyenda distintos, ese PDF individual puede no
  reflejarlos. Para ese caso puntual, usar la descarga individual del módulo
  de origen.

## Historial de cambios

- **1.0** — Versión inicial: 8 tipos de documento, descarga síncrona sin
  persistencia, límite configurable por cantidad de documentos.
