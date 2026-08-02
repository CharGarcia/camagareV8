---
titulo: Reporte de Ventas por Vendedor
resumen: Ventas netas (facturas menos notas de crédito) desglosadas por asesor/vendedor, producto, marca o categoría.
categoria: Ventas
ruta_modulo: modulos/reporte_ventas_vendedor
tipo: modulo
visibilidad: todos
etiquetas: reporte de ventas por vendedor, reporte por asesor, comisiones, ventas netas, ventas por marca, ventas por categoría, rendimiento de vendedores, subtotal ventas menos notas de credito
version: 1.0
orden: 0
estado: activo
---

Reporte para analizar cuánto vendió cada asesor (vendedor) de la empresa, con la
posibilidad de acotar por período, por un vendedor específico o todos, y por
producto, marca o categoría. La métrica principal es la **venta neta**: el
subtotal de las facturas menos el subtotal de las notas de crédito asociadas.

## Qué es y para qué sirve

Sirve para evaluar el desempeño de ventas de cada asesor (por ejemplo, para
calcular comisiones o metas), y para cruzar esa información con qué se vendió
(producto, marca, categoría) en un período dado. Toma como fuente las
**Facturas de Venta** y las **Notas de Crédito en Ventas** ya emitidas; no
incluye Recibos de Venta.

## Requisitos previos

- Tener registrados los vendedores en el catálogo de **Vendedores**
  (`modulos/vendedores`) y asignado el campo Vendedor en las facturas que
  correspondan. Las facturas sin vendedor asignado se agrupan como "Sin
  vendedor asignado".
- Para filtrar/agrupar por Marca o Categoría, los productos deben tener esos
  campos completados en su ficha (`modulos/productos`).
- Para el envío por correo, la empresa debe tener configurado el correo de
  envío de documentos (`/config` → Correo) o usar el correo general del
  sistema.

## Cómo se usa

1. Elegir el **Tipo de Documento**: *Ventas Netas (Facturas − NC)* (por
   defecto), *Solo Facturas* o *Solo Notas de Crédito*.
2. Elegir cómo **Agrupar** el resultado: por Vendedor (vista principal), por
   Producto, por Marca, por Categoría, por Mes, o Detallado (documento por
   documento).
3. Acotar el período con Mes/Año (calculan automáticamente el rango de
   fechas) o escribiendo directamente Fecha Desde/Hasta.
4. Opcionalmente filtrar por Vendedor (uno específico o "Todos"), Marca,
   Categoría o Producto (buscador con autocompletado).
5. Hacer clic en **Aplicar y Generar**.
6. Exportar a **PDF** o **Excel**, o usar **Correo** para enviar el PDF del
   reporte (con los filtros actuales) a un destinatario.
7. En la vista Por Vendedor, hacer clic sobre una fila abre un panel a la
   derecha con el detalle documento por documento (factura, subtotal, NC y
   total neto) de ese vendedor. En el modo Detallado, el clic sobre una fila
   abre el detalle del documento (factura o nota de crédito).
8. Al exportar a Excel agrupando **Por Vendedor**, el archivo incluye una
   segunda hoja ("Detalle Documentos") con esa misma información documento
   por documento, de todos los vendedores o solo del filtrado.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo de Documento | No (por defecto Ventas Netas) | Si se resta la nota de crédito del subtotal de la factura, o se ve cada documento por separado |
| Agrupar Por | No (por defecto Vendedor) | El nivel de detalle de las filas del reporte |
| Vendedor | No | Un asesor específico o todos |
| Marca / Categoría / Producto | No | Acotan el reporte a lo vendido de ese producto/marca/categoría |
| Fecha Desde / Hasta | No | Rango de fechas de emisión a incluir |

## Permisos

Sigue el esquema estándar de permisos por submódulo (`modulos_asignados`):
requiere el permiso de **Ver** (`r`). El permiso **Acceso total (t)** sí tiene
efecto aquí, con un significado distinto al habitual (no es "quién creó el
documento", sino "a quién está asignada la venta"):

- **Con acceso total**: ve las ventas de todos los vendedores de la empresa y
  puede usar el filtro Vendedor libremente.
- **Sin acceso total**: se le fuerza a ver únicamente las ventas cuyo campo
  Vendedor (`ventas_cabecera.id_vendedor`) coincide con el vendedor vinculado
  a su propia cuenta de usuario (`vendedores.id_usuario`) — sin importar qué
  usuario haya facturado/tecleado el documento. El filtro Vendedor desaparece
  del formulario (no puede ver otros vendedores) y se muestra un aviso. Si su
  usuario no está vinculado a ningún vendedor, el reporte se muestra vacío en
  vez de mostrar datos de otros por defecto.
- Nivel 3 (superadmin) siempre tiene acceso total.

Para vincular un usuario a un vendedor: editar el registro en **Vendedores**
(`modulos/vendedores`) y asignarle el campo Usuario.

## Reglas de negocio

- **Venta neta** = subtotal de facturas autorizadas − subtotal de notas de
  crédito autorizadas, en el mismo rango de filtros. Se calcula ejecutando la
  misma consulta contra Facturas y contra Notas de Crédito, y restando fila a
  fila (por vendedor, producto, marca, categoría o mes, según la agrupación).
- Las Notas de Crédito no tienen un vendedor propio: se resuelve identificando
  la factura original que modifican (por número de documento) y tomando el
  vendedor de esa factura. Si la nota de crédito no referencia una factura del
  sistema (o la referencia no se encuentra), no se le puede atribuir vendedor
  y no aparece si se filtra por un vendedor específico.
- Solo se consideran documentos con estado autorizado (no borradores ni
  anulados) para el cálculo de subtotales; el resumen de estados (Autorizados/
  Borradores/Anulados) de las tarjetas superiores sí refleja los tres estados.
- El envío por correo genera el mismo PDF que la descarga, con los filtros
  vigentes en el formulario al momento de enviar.

## Integraciones con otros módulos

- **Vendedores** (`modulos/vendedores`): catálogo de asesores.
- **Facturas de Venta** y **Notas de Crédito** (ventas): fuente de los datos.
- **Productos, Marcas y Categorías**: catálogos usados para filtrar/agrupar.
- **Configuración de Correo** de la empresa: usada para el envío del reporte.

## Errores frecuentes

- **Una nota de crédito no se resta de ningún vendedor**: ocurre cuando esa
  NC no pudo vincularse a la factura original (por ejemplo, si el número de
  documento modificado no coincide con ninguna factura de la empresa).
- **El correo no se envía**: revisar que la empresa tenga configurado el
  correo de envío de documentos en `/config`.
- **Un vendedor no ve ninguna venta (reporte vacío) aunque sí facturó**: si no
  tiene acceso total en este submódulo, revisar que su usuario esté vinculado
  al registro correspondiente en Vendedores (campo Usuario) y que las ventas
  tengan ese mismo vendedor asignado en el campo Vendedor de la factura.

## Historial de cambios

- **1.0** — Versión inicial: filtros por vendedor/producto/marca/categoría/
  fecha, cálculo de ventas netas, exportación a PDF/Excel y envío por correo.
