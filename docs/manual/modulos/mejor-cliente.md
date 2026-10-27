---
titulo: Mejor Cliente
resumen: Ranking de clientes por monto neto o cantidad de documentos, con filtro por asesor y periodo.
categoria: Reportes
ruta_modulo: modulos/mejor_cliente
tipo: modulo
visibilidad: todos
etiquetas: mejor cliente, pdf mejor cliente, grafico mejores clientes, tipo de grafico, grafico de pastel, cambiar grafico, excel productos por cliente, descargar productos del cliente, imprimir ranking, top clientes, ranking de clientes, cliente top, cuanto compra cada cliente, mejores clientes, cliente estrella, cliente frecuente, cliente que mas compra, recibos en borrador, no salen los recibos, recibos no aparecen, que productos compra el cliente, productos por cliente, detalle de productos, que le vendi al cliente, documentos de pruebas
version: 1.5
orden: 15
estado: activo
---

El reporte **Mejor Cliente** responde a la pregunta de quiénes son los clientes
que más le compran, ya sea por dinero (monto neto) o por frecuencia (cantidad de
documentos), en el periodo que se indique.

## Qué es y para qué sirve

Arma un ranking de clientes a partir de las Facturas de Venta y/o los Recibos de
Venta vigentes (no anulados ni facturados), restando las Notas de Crédito de venta del mismo periodo. Sirve
para identificar a los clientes clave de la empresa (o de un asesor puntual) y
priorizar la atención comercial.

## Requisitos previos

El usuario necesita permiso de lectura sobre **Facturas de Venta** y/o
**Recibos de Venta**: el reporte solo ofrece como fuente aquellas que el usuario
puede consultar. Si no tiene acceso a ninguna de las dos, el reporte no puede
generarse.

## Cómo se usa

1. Elija la **Fuente**: Facturas, Recibos, o ambas (según lo que tenga habilitado).
2. Filtre por **Asesor/Vendedor** si quiere el ranking de un vendedor puntual, o
   deje "Todos" para ver el total de la empresa.
3. Elija el periodo con **Mes/Año** o con **Fecha Desde/Hasta**.
4. Elija el criterio de **Ordenar Por**: Monto Neto o Cantidad de Documentos.
5. Elija cuántos clientes mostrar en **Top** (10, 20, 50, 100 o Todos).
6. Presione **Mostrar** (el reporte también se actualiza solo al cambiar un filtro).
7. Haga clic sobre un cliente para ver **qué productos le vendió** (ver la sección siguiente).
8. Descargue el resultado en **PDF** o **Excel**, o envíelo por **Correo**.

## El gráfico de mejores clientes

Sobre la tabla aparece el gráfico de los 15 primeros clientes del ranking, por
monto neto o por cantidad de documentos según el criterio de **Ordenar Por**. Igual
que en Reporte de Ventas, viene **cerrado**: se abre o cierra con su título o con
la flecha de la derecha.

El selector junto al título cambia el **tipo de gráfico**: *Automático* (barras
horizontales, para que los nombres se lean completos), *Barras*, *Líneas*,
*Pastel* o *Dona*. Con la estrella puede dejar fijo su tipo preferido.

## Productos vendidos a un cliente

Al hacer clic sobre la fila de un cliente se abre una ventana con los productos
que se le vendieron, con **los mismos filtros del ranking** (periodo, vendedor y
fuentes Facturas/Recibos):

- **Código, Producto y Unidad** (la unidad de medida del producto).
- **Cantidad**: unidades vendidas menos las devueltas en Notas de Crédito.
- **Documentos**: en cuántas facturas/recibos aparece el producto.
- **Monto neto**: valor sin impuestos de las líneas, menos las Notas de Crédito.
- **% del cliente**: cuánto representa el producto en lo que compró el cliente.

El campo de búsqueda de la ventana filtra por código o nombre del producto. Las
líneas sin producto del catálogo (conceptos libres) aparecen agrupadas por su
código y descripción.

Los botones **PDF** y **Excel** de la parte superior de la ventana descargan ese
mismo detalle, con el cliente, el periodo, el vendedor y los documentos incluidos
en el encabezado. Si escribió algo en el buscador, se descargan solo los productos
filtrados (y el filtro aparece en el encabezado).

La suma de los productos coincide con el monto neto del cliente en el ranking. Si
no coincide (por ejemplo, documentos migrados que no trajeron su detalle), la
ventana muestra una línea **Diferencia** con el valor que falta.

## El PDF del reporte

El PDF (el que se descarga y el que se adjunta al correo) tiene el mismo formato
que los demás reportes del sistema y ocupa todo el ancho de la hoja:

- **Encabezado** con el logo de la empresa, su nombre, el título, el periodo y el
  vendedor consultado.
- **Filtros aplicados**: fechas desde/hasta, vendedor (o "Todos"), documentos
  incluidos (Facturas / Recibos), criterio de orden y cuántos clientes se muestran.
- **Indicadores**: clientes con ventas, documentos, monto neto total, venta
  promedio y el monto del mejor cliente. Se calculan sobre **todos** los clientes
  del periodo, no solo los del Top.
- **Listado** con el puesto, cliente, RUC/Cédula, documentos, monto neto, venta
  promedio y **% del total** (participación del cliente en el monto neto del
  periodo). La fila final suma solo los clientes listados; con un Top, su
  porcentaje indica cuánto de la venta concentran esos mejores clientes.

El logo es el del establecimiento (o, si no tiene, el del punto de emisión)
cargado en la configuración de la empresa; sin logo, el encabezado sale centrado.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fuente (Facturas / Recibos) | Sí (al menos una) | Qué documentos cuentan como venta del cliente |
| Asesor/Vendedor | No | Acota el ranking a las ventas de un vendedor específico |
| Ordenar Por | Sí | Monto Neto (dinero) o Cantidad de Documentos (frecuencia) |
| Top | Sí | Cuántos clientes mostrar del ranking |
| Fecha Desde / Hasta | Sí | El periodo a consultar |

## Permisos

Sigue el permiso propio del módulo (ver/crear/actualizar/eliminar no aplica más
allá de "ver", ya que es un reporte de solo lectura). El **acceso total** no
cambia la fuente de datos: el reporte de Mejor Cliente siempre mira los
documentos de toda la empresa, según lo que el usuario tenga permitido ver en
Facturas/Recibos.

## Reglas de negocio

- El **monto neto** es la suma de Facturas y/o Recibos (campo `total_sin_impuestos`,
  es decir sin IVA) menos las Notas de Crédito de venta del mismo cliente y
  periodo.
- Solo se cuentan documentos del ambiente de **producción** del SRI, como en todos
  los reportes: lo emitido en pruebas no aparece aunque la empresa esté
  configurada en pruebas.
- Solo se cuentan documentos **autorizados** (Facturas y Notas de Crédito) y
  los Recibos **no anulados ni facturados**. El recibo no pasa por el SRI ni tiene
  aprobación: se guarda en estado *borrador* y solo pasa a *emitido* cuando queda
  pagado por completo; un recibo a crédito sigue en borrador y cuenta igual. Las facturas y notas de crédito en borrador no cuentan.
- La **venta promedio** es el monto neto dividido entre la cantidad de
  documentos (Facturas/Recibos, sin contar las Notas de Crédito como documento).
- Al filtrar por **Asesor/Vendedor**, las Notas de Crédito se restan igual al
  cliente sin distinguir de qué vendedor era la venta original (la Nota de
  Crédito no guarda ese dato). En la práctica solo distorsiona el ranking en el
  caso raro de que un cliente compre a más de un vendedor y devuelva algo del
  otro mientras se filtra por uno solo.

## Integraciones con otros módulos

- Lee de **Facturas de Venta** y **Recibos de Venta** (`ventas_cabecera`,
  `recibos_venta_cabecera`) y de **Notas de Crédito en Ventas**
  (`notas_credito_cabecera`); el detalle por producto, de sus líneas
  (`ventas_detalle`, `recibos_venta_detalle`, `notas_credito_detalle`) y del
  catálogo de **Productos**.
- El filtro de Asesor/Vendedor usa el catálogo de **Vendedores**.

## Errores frecuentes

- **No aparece la opción de Recibos (o Facturas)**: el usuario no tiene permiso
  de lectura sobre ese módulo.
- **Las cifras no coinciden con Reporte de Ventas**: Reporte de Ventas usa el
  total con impuestos por documento; Mejor Cliente usa el monto **sin
  impuestos** y además resta las Notas de Crédito.
- **No aparecían los recibos**: hasta la versión 1.0 el reporte descartaba los
  recibos en *borrador*, que es el estado en que quedan todos los recibos creados en
  el sistema. Desde la 1.1 cuentan todos los no anulados ni facturados.
- **Un cliente aparece con monto negativo o menor al esperado**: revise si tiene
  Notas de Crédito grandes en el periodo.

## Historial de cambios

- **1.5** — El **gráfico** toma el diseño de los demás reportes: se abre y cierra
  como acordeón y permite elegir el **tipo de gráfico** (Automático, Barras, Líneas,
  Pastel o Dona), con estrella para guardar el preferido.

- **1.4** — Los **productos vendidos a un cliente** se pueden descargar en **PDF** y
  **Excel** desde su ventana (respetan el filtro del buscador).

- **1.3** — **Productos vendidos por cliente**: clic sobre un cliente abre una ventana
  con sus productos (cantidad, documentos, monto neto y % del cliente), con los mismos
  filtros del ranking. El reporte ahora muestra **solo documentos de producción**, como
  los demás reportes. El botón de filtros pasa a llamarse **Mostrar**.

- **1.2** — **Nuevo diseño del PDF**: logo de la empresa, caja de filtros aplicados
  (incluido el vendedor), indicadores del periodo, columna **% del total** y tabla a
  todo el ancho de la hoja.
- **1.1** — **Corrección: los Recibos de Venta no aparecían** en el ranking (ni sus
  años en el selector), porque se descartaban los recibos en *borrador*, que es el
  estado normal de un recibo creado en el sistema. Ahora cuenta todo recibo no
  anulado ni facturado (un recibo facturado ya está en su factura).
- **1.0** — Versión inicial.
