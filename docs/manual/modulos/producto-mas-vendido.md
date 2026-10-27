---
titulo: Productos Más Vendidos
resumen: Ranking de los productos que más se venden, con filtros de cliente, producto, período y Top N; exporta a PDF/Excel y se envía por correo.
categoria: Reportes
ruta_modulo: modulos/producto_mas_vendido
tipo: modulo
visibilidad: todos
etiquetas: producto mas vendido, ranking de ventas, top productos, mejores productos, productos top, reporte de ventas por producto, best sellers, quien compra este producto, clientes por producto, a quien le vendi, grafico de productos, tipo de grafico, documentos de pruebas, recibos en borrador, no salen los recibos, recibos no aparecen
version: 1.2
orden: 0
estado: activo
---

Muestra qué productos se están vendiendo más, ordenados por cantidad, para
decidir qué reabastecer o promocionar. Se relaciona con [Reporte de
Ventas](reporte-ventas.md) (que analiza por cliente/fecha/variante) pero este
módulo se enfoca puntualmente en el ranking de productos.

## Qué es y para qué sirve

Calcula, para un rango de fechas y un tipo de documento (Facturas, Recibos, o
ambos), cuántas unidades se vendieron de cada producto, en cuántos documentos
apareció y cuánto se facturó por él. El resultado se ordena de mayor a menor
cantidad vendida (ranking #1, #2, #3…).

Solo considera documentos válidos: Facturas autorizadas y Recibos no anulados.
El recibo no pasa por el SRI ni tiene un paso de "emitir": se guarda en estado
*borrador* y solo pasa a *emitido* cuando queda pagado por completo; un recibo a
crédito sigue en borrador y cuenta igual. Si un recibo terminó
siendo facturado, se cuenta una sola vez como Factura, para no duplicar la venta.

## Cómo se usa

1. Elegir el **Tipo de Documento**: Facturas de Venta, Recibos de Venta, o
   ambos combinados.
2. Elegir cuántos productos mostrar (**Top 10/20/50/100** o **Todos**).
3. Acotar el período con **Mes/Año** (atajo que calcula el rango) o con
   **Fecha Desde/Hasta** directamente.
4. Opcional: filtrar por uno o varios **Clientes** o **Productos** puntuales
   (buscador con chips, se pueden combinar varios).
5. El ranking se genera automáticamente al cambiar el tipo, el Top, el mes/año o los
   chips; tras cambiar las fechas a mano, presionar **Mostrar**.
6. Hacer clic sobre un producto para ver **a qué clientes se vendió** (ver más abajo).
7. Exportar a **PDF** o **Excel**, o usar el botón **Correo** para enviarlo
   como adjunto a uno o varios destinatarios.

## El gráfico de productos

Sobre la tabla aparece el gráfico de los 15 productos más vendidos (por cantidad).
Igual que en Reporte de Ventas, viene **cerrado**: se abre o cierra con su título o
con la flecha de la derecha.

El selector junto al título cambia el **tipo de gráfico**: *Automático* (barras
horizontales, para que los nombres se lean completos), *Barras*, *Líneas*,
*Pastel* o *Dona*. Con la estrella puede dejar fijo su tipo preferido.

## Clientes que compraron un producto

Al hacer clic sobre la fila de un producto se abre una ventana con los clientes a
los que se les vendió, con **los mismos filtros del ranking** (tipo de documento,
periodo y clientes elegidos):

- **RUC/Cédula y Cliente**.
- **Cantidad vendida** y **N° de documentos** en los que el cliente lo compró.
- **Total vendido**, sin impuestos.
- **% del producto**: qué parte de las unidades vendidas del producto se llevó ese cliente.

La suma de los clientes coincide con la fila del producto en el ranking. El campo
de búsqueda de la ventana filtra por RUC o nombre del cliente.

Los botones **PDF** y **Excel** de la parte superior de la ventana descargan ese
mismo detalle, con el producto, el periodo y el tipo de documento en el
encabezado. Si escribió algo en el buscador, se descargan solo los clientes
filtrados (y el filtro aparece en el encabezado).

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo de Documento | Sí | Origen de las ventas a considerar: Facturas, Recibos, o ambos combinados. |
| Top | Sí | Cuántos productos mostrar en el ranking (10/20/50/100/Todos). |
| Mes / Año | No | Atajo que calcula Fecha Desde/Hasta automáticamente. |
| Fecha Desde / Hasta | No | Rango libre de fechas de emisión. Si se deja vacío, no filtra por fecha. |
| Cliente | No | Uno o varios clientes; solo se cuentan las ventas hechas a ellos. |
| Producto | No | Uno o varios productos puntuales a los que acotar el ranking. |

## Permisos

Solo requiere permiso de **lectura (VER)** sobre el módulo — es un reporte de
solo consulta, no crea, modifica ni elimina registros. El permiso de **acceso
total** no aplica aquí: los datos son agregados de toda la empresa, no
registros individuales con dueño.

## Reglas de negocio

- Solo se cuentan documentos del ambiente de **producción** del SRI, como en todos
  los reportes: lo emitido en pruebas no aparece aunque la empresa esté
  configurada en pruebas.
- Se excluyen documentos **eliminados**, **anulados** y **borradores**.
- Un recibo que ya fue facturado no se cuenta como recibo (evita duplicar la
  venta al combinar Facturas + Recibos).
- La cantidad vendida es la suma de las líneas de detalle del producto; el
  "N° Documentos" es la cantidad de comprobantes distintos donde apareció.
- El "Total Vendido" es la suma del valor sin impuestos de las líneas (no
  incluye IVA).
- El ranking se recalcula sobre el conjunto completo antes de recortar al Top
  N elegido, por lo que las tarjetas de estadísticas (productos distintos,
  unidades, total) reflejan siempre el total real, no solo lo mostrado en
  pantalla.

## Integraciones con otros módulos

Lee de `ventas_cabecera`/`ventas_detalle` (Facturas de Venta) y
`recibos_venta_cabecera`/`recibos_venta_detalle` (Recibos de Venta). No
escribe en ninguna tabla: es de solo lectura.

## Errores frecuentes

- **El correo no llega**: revisar la configuración SMTP en `correos_config`
  (código `notificaciones`); el mensaje de error del sistema indica la causa.
- **No aparecían las ventas por Recibo**: hasta la versión 1.0 se descartaban
  los recibos en *borrador*, que es el estado en que quedan todos los recibos
  creados en el sistema. Desde la 1.1 cuentan todos los no anulados ni facturados.
- **Un producto aparece con cantidad menor a la esperada**: verificar el tipo
  de documento elegido — si la venta se hizo por Recibo y luego se facturó,
  solo se contabiliza una vez, como Factura.

## Historial de cambios

- **1.2** — **Clientes por producto**: clic sobre un producto abre una ventana con
  los clientes que lo compraron (cantidad, documentos, total y %), descargable en PDF y
  Excel. El **gráfico** toma el diseño de los demás reportes (acordeón y selector de
  tipo: Automático, Barras, Líneas, Pastel o Dona). El reporte muestra **solo
  documentos de producción**. El botón de filtros pasa a llamarse **Mostrar**.

- **1.1** — **Corrección: los Recibos de Venta no aparecían** (ni sus años en el
  selector), porque se descartaban los recibos en *borrador*, que es el estado normal
  de un recibo creado en el sistema. Ahora cuenta todo recibo no anulado ni facturado.
- **1.0** — Versión inicial: ranking por cantidad, filtros de cliente/producto/
  período/tipo de documento, Top N, export a PDF/Excel y envío por correo.
