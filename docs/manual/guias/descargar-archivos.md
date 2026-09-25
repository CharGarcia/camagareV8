---
titulo: Descargar archivos (Excel, PDF, XML)
resumen: Qué pasa al pulsar Excel, PDF, XML o una plantilla en cualquier módulo, y qué hacer si la descarga no sale.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: descargar, descarga, exportar, excel, pdf, xml, csv, zip, plantilla, generando, no descarga, no se descarga, pestaña en blanco, pagina en blanco, se queda cargando, demasiados datos, archivo muy grande, error al descargar, reporte muy grande
version: 1.0
orden: 30
estado: activo
---

Todos los botones de descarga del sistema (Excel, PDF, XML, CSV, ZIP y plantillas)
funcionan igual: el archivo se prepara en el servidor y se descarga en la misma
pantalla, sin abrir otra pestaña.

## Mientras se genera el archivo

Al pulsar el botón aparece el aviso **Generando Excel…** (o PDF, XML…) con un
indicador de carga. Con muchos datos puede tardar unos segundos o hasta un
minuto; no hace falta volver a pulsar. Cuando el archivo está listo, el aviso se
cierra solo y el navegador lo descarga.

## Si la descarga no sale

En vez de una pestaña en blanco, el motivo aparece en un aviso:

| Aviso | Qué significa | Qué hacer |
|-------|---------------|-----------|
| **Demasiados datos para Excel / PDF** | El reporte supera lo que se puede armar en un solo archivo | Acote los filtros: por año o por meses, por bodega, por cliente… y descargue cada parte por separado. El Excel admite muchas más filas que el PDF |
| **No se pudo generar el archivo** con un motivo | El módulo explicó el problema (sin permiso, documento no encontrado, sesión vencida…) | Siga lo que dice el mensaje |
| **No se pudo generar el archivo** sin motivo | El servidor falló al armarlo | Si el reporte es grande, acote los filtros y vuelva a intentar. Si se repite con pocos datos, avise a soporte |
| **Error de conexión** | No hubo comunicación con el servidor | Revise la conexión a internet y vuelva a intentar |

## Documentos que se abren para ver

Algunos PDF se abren en otra pestaña para verlos o imprimirlos, y no se
descargan: pedidos, órdenes de compra, órdenes de taller, car wash, servicio
externo, el reporte de trazabilidad, el log del sistema y la impresión de
cheques. En esos no aparece el aviso de *Generando*.

## Abrir el archivo en otra pestaña

En los botones PDF/Excel de los listados, Ctrl+clic (o clic con la rueda del
ratón) sigue abriendo el archivo en otra pestaña, como antes.

## Historial de cambios

- **1.0** — Aviso *Generando…* y mensajes de error en todas las descargas del
  sistema, en lugar de abrir una pestaña nueva.
