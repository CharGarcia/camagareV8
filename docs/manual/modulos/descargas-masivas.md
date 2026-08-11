---
titulo: Descargas Masivas
resumen: Descarga el PDF y/o XML de varios documentos (facturas, notas de crédito, retenciones, egresos, ingresos, cheques, etc.) de un rango de fechas o de números, en un solo PDF o en un ZIP.
categoria: Documentos
ruta_modulo: modulos/descargas-masivas
tipo: modulo
visibilidad: todos
etiquetas: descarga masiva, descargas masivas, zip, exportar facturas, exportar xml, exportar pdf, backup de comprobantes, descargar todas las facturas, descargar notas de crédito, descargar retenciones, descargar guías de remisión, descargar compras, descargar egresos, descargar ingresos, descargar cheques, rango de número, rango numérico, desde número hasta número
version: 2.0
orden: 0
estado: activo
---

Descargas Masivas arma el PDF y/o XML de varios documentos electrónicos de un
mismo tipo, elegidos por un rango de fechas **o** por un rango de número. Sirve
para bajar de una sola vez, por ejemplo, todas las facturas de un mes para
entregarlas al contador, o los egresos del 100 al 150, sin tener que abrir
documento por documento.

## Qué es y para qué sirve

Cubre 11 tipos de documento: Facturas de Venta, Notas de Crédito, Notas de
Débito, Guías de Remisión, Retenciones de Venta, Retenciones de Compra,
Liquidaciones de Compra, Compras (facturas recibidas de proveedor), **Egresos**,
**Ingresos** y **Cheques** (impresión de cheques emitidos desde Egresos).

El archivo se genera **al momento** de la descarga y **no queda guardado** en
el sistema: no crea ninguna tabla ni deja un archivo pendiente en el servidor.
Cada descarga es un proceso de una sola vez — ver "Reglas de negocio".

## Cómo se usa

1. Elegir el **tipo de documento**.
2. Elegir cómo filtrar: **Rango de fechas** o **Rango de número** (son
   excluyentes, no se combinan).
   - Rango de fechas: sin límite de tamaño, un año con pocos documentos es
     tan válido como un solo día con muchos.
   - Rango de número: descarga "desde el número X hasta el número Y" (sobre
     el secuencial del documento; en Cheques, sobre el número de cheque
     físico).
3. Elegir el **formato**: PDF, XML o ambos. Egresos, Ingresos y Cheques no son
   documentos electrónicos del SRI, así que **no tienen XML**: para esos tipos
   el formulario solo deja elegir PDF.
4. Presionar **Verificar cantidad**. El sistema cuenta cuántos documentos
   cumplen el filtro sin generar nada todavía, y adelanta si la descarga va a
   salir como **un solo PDF** o como **ZIP**.
5. Si la cantidad está dentro del límite, aparece el botón de descarga con la
   etiqueta correspondiente ("Descargar PDF" o "Descargar ZIP"). Si supera el
   límite, hay que acortar el rango.

## Un solo PDF o un ZIP, según la cantidad

- Si el formato es **PDF** y la cantidad de documentos es **menor o igual al
  umbral** (`config/app.php` → `descargas_masivas.umbral_pdf_unico`, 20 por
  defecto), se entrega **un solo archivo PDF** con todos los documentos
  fusionados en orden (uno detrás de otro, respetando sus páginas originales).
  No hay que descomprimir nada.
- En cualquier otro caso —más documentos que el umbral, o formato XML/Ambos—
  se entrega un **ZIP**, como siempre.
- La fusión de PDF usa la librería `setasign/fpdi` (ya declarada en
  `composer.json`); si el servidor no la tiene instalada, hay que correr
  `composer install` antes de usar esta función.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo de documento | Sí | Uno de los 11 tipos soportados; una descarga trae un solo tipo a la vez |
| Filtrar por | Sí | "Rango de fechas" o "Rango de número" (excluyentes) |
| Desde / Hasta (fecha) | Sí, si el filtro es por fecha | Rango de fecha de emisión del documento |
| Número desde / hasta | Sí, si el filtro es por número | Rango del secuencial del documento (o del número de cheque, en Cheques) |
| Formato | Sí | PDF, XML, o ambos (deshabilitado XML/Ambos para Egresos, Ingresos y Cheques) |

## Permisos

Requiere permiso de **ver** sobre `modulos/descargas-masivas`. Si el usuario
**no** tiene acceso total en este submódulo, la descarga solo incluye los
documentos que él mismo creó en el módulo de origen (mismo criterio de
"registros propios" que el resto del sistema). Con acceso total, incluye los de
toda la empresa. El permiso se administra en `/config/permisos-modulos`.

## Reglas de negocio

- **Sin límite de rango; sí de cantidad de documentos.** Cada descarga se
  acota por cuántos documentos matchean el filtro, no por cuántos días/meses o
  números se seleccionaron. El límite es configurable
  (`config/app.php` → `descargas_masivas.max_documentos_pdf` /
  `max_documentos_xml`, 500 y 2000 por defecto). XML tiene un tope más alto
  porque solo lee el XML ya autorizado; PDF genera el documento con TCPDF, más
  lento.
- **Rango de fechas y rango de número son excluyentes.** Se elige un modo u
  otro; no se puede acotar por fecha Y número a la vez en la misma descarga.
- **Solo documentos válidos.** Se excluyen borradores, anulados y rechazados
  (según el tipo); solo se incluyen los autorizados/aprobados/registrados.
  Cheques excluye los que pertenecen a un egreso anulado.
- **Nada se guarda.** El PDF/ZIP se arma en un archivo temporal
  (`storage/tmp/descargas_masivas/`) y se borra apenas se transmite al
  navegador (incluso si la descarga falla a medio camino). No hay historial de
  descargas para volver a bajar el mismo archivo: hay que generarlo de nuevo.
- **XML**: se sirve el XML autorizado ya guardado en el documento; si no está
  disponible, se regenera en memoria (sin guardarlo) para los tipos que lo
  permiten. Retenciones de Venta y Compras no regeneran XML: ese archivo lo
  emite la otra parte (el cliente que retiene, o el proveedor/SRI), así que si
  nunca se recibió, simplemente no hay XML que incluir para ese documento.
  Egresos, Ingresos y Cheques no tienen XML en absoluto (no son documentos del
  SRI).
- **Plantillas de PDF personalizadas no se usan aquí, salvo en Cheques**: la
  descarga masiva siempre usa el diseño de PDF estándar de cada tipo de
  documento, no la plantilla configurada en `/config/plantillas-pdf` (a
  diferencia de la descarga individual de cada módulo). La excepción es
  **Cheques**: como el formato del cheque físico varía por banco y no existe
  un "diseño estándar" separado, se usa la plantilla activa configurada por
  banco (`/config/plantillas-pdf`, tipo `cheque`) — la misma que usa
  `Egresos → Imprimir cheques`.
- **Descargar un cheque aquí no lo marca como impreso.** A diferencia de
  imprimirlo desde `Egresos → Imprimir cheques`, esta descarga es de solo
  lectura: no registra nada en `cheques_impresos`, así que no afecta el
  control de reimpresión.
- Cada descarga queda registrada en la auditoría (`log_sistema`): usuario,
  tipo, rango (de fecha o de número), formato y cantidad de documentos.

## Integraciones con otros módulos

Lee de Facturas de Venta, Notas de Crédito, Notas de Débito, Guías de
Remisión, Retenciones de Venta, Retenciones de Compra, Liquidaciones de
Compra, Compras, Egresos, Ingresos y Cheques (a través de Egresos). No escribe
en ninguno de ellos.

## Errores frecuentes

- **"Se encontraron X documentos y el máximo por descarga es Y"**: acortar el
  rango (de fecha o de número) y volver a verificar.
- **"No se encontraron documentos con esos filtros"**: revisar que el tipo y
  el rango sean correctos, y que existan documentos **autorizados/registrados**
  (no borradores) en ese rango.
- **"Este tipo de documento no tiene XML disponible"**: Egresos, Ingresos y
  Cheques solo admiten formato PDF; el formulario ya deshabilita XML/Ambos
  para estos tipos, pero un llamado directo al endpoint con `formato=xml`
  devuelve este error.
- **El PDF de algún documento sale sin logo o sin la leyenda personalizada**:
  la descarga masiva arma el encabezado de empresa con el primer
  establecimiento configurado; si el documento pertenece a otro
  establecimiento con logo/leyenda distintos, ese PDF individual puede no
  reflejarlos. Para ese caso puntual, usar la descarga individual del módulo
  de origen.
- **Error al generar el PDF único (fusión)**: si el servidor no tiene
  instalada la librería `setasign/fpdi` (`composer install` pendiente), la
  fusión de PDF falla. Como alternativa temporal, subir la cantidad de
  documentos por encima del umbral fuerza la salida en ZIP (que no depende de
  esa librería).

## Historial de cambios

- **2.0** — Se agregan Egresos, Ingresos y Cheques (11 tipos en total). Nuevo
  filtro por rango de número (alternativo al de fechas). Si el formato es PDF
  y la cantidad cabe en el umbral configurado, se entrega un solo PDF
  fusionado en vez de un ZIP (`setasign/fpdi`).
- **1.0** — Versión inicial: 8 tipos de documento, descarga síncrona sin
  persistencia, límite configurable por cantidad de documentos.
