---
titulo: Productos Más Vendidos
resumen: Ranking de los productos que más se venden, con filtros de cliente, producto, período y Top N; exporta a PDF/Excel y se envía por correo.
categoria: Reportes
ruta_modulo: modulos/producto_mas_vendido
tipo: modulo
visibilidad: todos
etiquetas: producto mas vendido, ranking de ventas, top productos, mejores productos, productos top, reporte de ventas por producto, best sellers
version: 1.0
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

Solo considera documentos válidos: Facturas autorizadas y Recibos emitidos o
facturados (no anulados ni borradores). Si un recibo terminó siendo facturado,
se cuenta una sola vez como Factura al elegir "Facturas + Recibos", para no
duplicar la venta.

## Cómo se usa

1. Elegir el **Tipo de Documento**: Facturas de Venta, Recibos de Venta, o
   ambos combinados.
2. Elegir cuántos productos mostrar (**Top 10/20/50/100** o **Todos**).
3. Acotar el período con **Mes/Año** (atajo que calcula el rango) o con
   **Fecha Desde/Hasta** directamente.
4. Opcional: filtrar por uno o varios **Clientes** o **Productos** puntuales
   (buscador con chips, se pueden combinar varios).
5. El ranking se genera automáticamente al cambiar cualquier filtro.
6. Exportar a **PDF** o **Excel**, o usar el botón **Correo** para enviarlo
   como adjunto a uno o varios destinatarios.

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
- **Un producto aparece con cantidad menor a la esperada**: verificar el tipo
  de documento elegido — si la venta se hizo por Recibo y luego se facturó,
  solo se contabiliza una vez, como Factura.

## Historial de cambios

- **1.0** — Versión inicial: ranking por cantidad, filtros de cliente/producto/
  período/tipo de documento, Top N, export a PDF/Excel y envío por correo.
