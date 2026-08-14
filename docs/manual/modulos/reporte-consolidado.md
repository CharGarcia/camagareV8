---
titulo: Reporte consolidado de transacciones
resumen: Compras, ventas, retenciones, notas de crédito/débito y liquidaciones en un solo reporte, con Excel detallado por hoja.
categoria: Reportes
ruta_modulo: modulos/reporte_consolidado
tipo: modulo
visibilidad: todos
etiquetas: reporte consolidado, todas las transacciones, resumen general, compras, ventas, facturas, recibos, retenciones, notas de credito, notas de debito, liquidaciones de compra, cierre de periodo
version: 1.0
orden: 51
estado: activo
---

Este reporte junta en una sola pantalla, un solo PDF y un solo Excel los 8 tipos
de documentos transaccionales del sistema, para tener la foto completa de un
período sin abrir cada reporte por separado.

## Qué es y para qué sirve

Sirve para una revisión general de un período (por ejemplo, antes de cerrar un
mes o entregar información al contador): cuántos documentos hubo de cada tipo,
cuánto suman, y el detalle línea por línea de cada uno en el Excel. No reemplaza
a los reportes individuales (Reporte de Ventas, Reporte de Compras, Reporte de
Retenciones, etc.) — estos siguen siendo la fuente para análisis más específicos
(por producto, por vendedor, por agrupación); el consolidado es la vista de
conjunto.

## Qué documentos incluye y de dónde sale cada uno

| Hoja / grupo | Fuente |
|---|---|
| Compras | Facturas de compra (`compras_cabecera`, tipo `01`) |
| Retenciones de Compra | Retenciones que la empresa practicó a sus proveedores |
| Facturas de Venta | Facturas emitidas a clientes |
| Recibos de Venta | Recibos de venta (documento interno, sin autorización SRI) |
| Retenciones de Venta | Retenciones que los clientes le practicaron a la empresa |
| Notas de Crédito | Las que la empresa emite a clientes **y** las que recibe de proveedores, en la misma hoja (columna "Origen" distingue Venta/Compra) |
| Notas de Débito | Igual que Notas de Crédito: emitidas y recibidas juntas, distinguidas por "Origen" |
| Liquidaciones de Compra | Liquidaciones de compra (documento propio, no es una compra normal) |

Las Notas de Crédito y Débito **recibidas de proveedores** se registran en el
sistema como una compra especial (`compras_cabecera` con `tipo_comprobante`
`04`/`05`) — por eso no aparecen también dentro de la hoja "Compras": ésta se
limita a las facturas de compra normales para que ningún monto se cuente dos
veces entre hojas.

## Filtros

- **Rango de fechas** (obligatorio, por defecto el mes en curso).
- **Buscar**: nombre o identificación del tercero, o número de documento.
- **Incluir anulados**: desactivado por defecto — los documentos anulados no sirven
  para un cuadre financiero, pero puede activarse si se necesita verlos.
- **Documentos a incluir**: casillas para armar el reporte solo con un
  subconjunto de los 8 tipos (por ejemplo, solo lo relacionado a ventas).

## Cómo se usa

1. Ajuste el rango de fechas y, si hace falta, los demás filtros.
2. Haga clic en **Buscar**. La tabla y los indicadores (documentos, total ventas,
   total compras, neto) se actualizan.
3. La tabla en pantalla y el PDF muestran un resumen a nivel de documento
   (cabecera). Para ver el detalle línea por línea (productos, impuestos,
   retenciones), descargue el **Excel**.

## Exportar

- **PDF**: resumen con indicadores por tipo de documento y el listado a nivel de
  cabecera (una fila por documento).
- **Excel**: un libro con **8 hojas**, una por tipo de documento, cada una con el
  detalle línea por línea (producto, cantidad, impuestos, retenciones, etc.)
  según lo que tenga esa fuente.

## Permisos

Solo requiere permiso de **ver** (`r`) sobre el módulo — es un reporte de solo
lectura, no crea, modifica ni elimina nada. No aplica la distinción de
"registros propios" (§6 de las reglas del sistema): siempre muestra los
documentos de toda la empresa a quien tenga acceso al reporte.

## Errores frecuentes

- **Un documento no aparece**: revise que su casilla esté marcada en
  "Documentos a incluir" y que no esté anulado (o marque "Incluir anulados").
- **El total no cuadra con un reporte individual**: los reportes individuales
  (Compras, Ventas) pueden tener otros filtros por defecto (por ejemplo,
  incluir NC de compra dentro del mismo total). Compare con el mismo rango de
  fechas y revise el desglose por tipo antes de asumir una diferencia real.

## Historial de cambios

- **1.0** — Versión inicial.
