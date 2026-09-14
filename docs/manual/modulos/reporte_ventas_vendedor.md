---
titulo: Reporte de Ventas por Vendedor
resumen: Ventas netas (facturas menos notas de crédito) desglosadas por asesor/vendedor, producto, marca o categoría.
categoria: Ventas
ruta_modulo: modulos/reporte_ventas_vendedor
tipo: modulo
visibilidad: todos
etiquetas: reporte de ventas por vendedor, reporte por asesor, comisiones, ventas netas, ventas por marca, ventas por categoría, rendimiento de vendedores, subtotal ventas menos notas de credito, subtotal sin impuestos, subtotal nc, total documentos por asesor, cuantas facturas hizo cada vendedor, saldo pendiente por vendedor, cartera por asesor, cuanto le deben a cada vendedor, facturas por cobrar por vendedor
version: 1.2
orden: 0
estado: activo
---

Reporte para analizar cuánto vendió cada asesor (vendedor) de la empresa, con la
posibilidad de acotar por período, por un vendedor específico o todos, y por
producto, marca o categoría. La métrica principal es la **venta neta sin
impuestos**: el subtotal de las facturas menos el subtotal de las notas de
crédito asociadas.

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
   derecha con el detalle documento por documento (factura, subtotal, NC,
   total neto y saldo) de ese vendedor. En el modo Detallado, el clic sobre una
   fila abre el detalle del documento (factura o nota de crédito).

   > Ojo con la palabra *subtotal*: en ese panel (y en la hoja "Detalle
   > Documentos" del Excel) las columnas Subtotal, NC y Total son importes
   > **con impuestos** —el total de cada factura, el de su nota de crédito y la
   > diferencia—, mientras que las columnas Subtotal y Subtotal NC de la tabla
   > principal son **sin impuestos** —Total incluido—. Por eso el total del
   > panel (con IVA) es mayor que el de la fila del asesor.
8. Al exportar a Excel agrupando **Por Vendedor**, el archivo incluye una
   segunda hoja ("Detalle Documentos") con esa misma información documento
   por documento —incluido el **Saldo** de cada factura—, de todos los
   vendedores o solo del filtrado.

## Columnas de la vista Por Vendedor

Agrupando **Por Vendedor** —la vista principal— la tabla muestra una fila por
asesor con estas cinco columnas, las mismas que salen en el **PDF** y en el
**Excel**:

| Columna | Qué muestra |
|---|---|
| Asesor | Nombre del vendedor. Las ventas sin vendedor asignado se agrupan en una fila "Sin vendedor asignado" |
| Total Documentos | Cuántas facturas del asesor entran en el período y filtros aplicados |
| Subtotal (sin impuestos) | Suma de las bases imponibles de esas facturas (base 0% / exento + base gravada), es decir el subtotal ANTES de IVA |
| Subtotal NC | Suma de las bases imponibles de las notas de crédito que afectan a esas facturas (también sin IVA). Va en rojo cuando hay alguna |
| Total | **Subtotal − Subtotal NC**: la venta neta del asesor, sin impuestos |

Notas:

- **Total Documentos cuenta solo facturas**, no las notas de crédito: cada NC
  se descuenta dentro de la fila de su factura (columna Subtotal NC) en vez de
  sumarse como un documento aparte. Así el número coincide con lo que se ve al
  hacer clic en la fila.
- **Las tres columnas de importes son SIN IVA.** El Total de esta vista no
  coincide con la tarjeta "Ventas Netas (Gran Total)" de arriba ni con el Gran
  Total de las demás agrupaciones, que sí incluyen impuestos: la diferencia es
  el IVA. El desglose de bases e IVA está en las tarjetas superiores.
- Las filas salen ordenadas de mayor a menor por la columna Total.
- Con el tipo de documento en *Solo Facturas*, Subtotal NC sale en 0 y el Total
  es el subtotal facturado. Con *Solo Notas de Crédito*, el subtotal de las NC
  se muestra en la columna Subtotal NC (la columna Subtotal queda en 0), porque
  esa columna siempre significa notas de crédito; el Total queda entonces en
  **negativo**, que es el resultado de aplicar la misma resta.
- Al pie del PDF se repiten los tres importes como **totales generales**.

## Columna Saldo (lo que falta por cobrar)

Las agrupaciones **Por Mes** y **Detallado** muestran una columna **Saldo** al
final, y la tarjeta superior resume el **Saldo Pendiente por Cobrar** de todo el
reporte (también cuando se agrupa Por Vendedor). El saldo se calcula igual que
en Facturas de Venta y en Cuentas por Cobrar:

> Saldo = Total de la factura + Notas de débito − Cobros − Retenciones − Notas de crédito

- En **Por Mes** es la suma de los saldos de las facturas de ese mes.
- En **Detallado**, en el panel de detalle por vendedor y en la segunda hoja del
  Excel es el saldo de cada factura: ahí se ve cuánta cartera dejó pendiente
  cada asesor, documento por documento.
- Nunca es negativo: si una factura quedó sobrecobrada se muestra en 0, igual
  que en el listado de Facturas de Venta.
- Las notas de crédito no tienen saldo propio (una NC no se cobra), así que
  aportan 0: su efecto ya está descontado dentro del saldo de la factura que
  modifican. Por eso el saldo no cambia entre *Ventas Netas* y *Solo Facturas*,
  y en *Solo Notas de Crédito* la columna sale en 0.
- Las columnas Saldo aparecen también en el **PDF** y en el **Excel** (en la hoja
  de Por Mes/Detallado y en la hoja "Detalle Documentos"), con el total general
  al pie. La vista Por Vendedor no lleva columna Saldo: ahí el dato se consulta
  en el panel de detalle o en la segunda hoja del Excel.

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
- El **Saldo** se mide a hoy, no a la fecha de corte del filtro: si se consulta
  un mes pasado, la columna muestra lo que sigue pendiente hoy de esas
  facturas, no lo que estaba pendiente al cierre de ese mes. Para un corte a
  una fecha concreta se usa **Cuentas por Cobrar**.
- El saldo solo cuenta cobros, retenciones y notas de crédito/débito que no
  estén anulados.

## Integraciones con otros módulos

- **Cuentas por Cobrar** (`modulos/cuentas_por_cobrar`): usa exactamente la
  misma fórmula de saldo, con corte por fecha y antigüedad de cartera.
- **Ingresos / Cobros**, **Retenciones en Ventas** y **Notas de Débito**:
  alimentan el saldo pendiente de cada factura.
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

- **1.2** — La vista **Por Vendedor** pasa a mostrar cinco columnas: Asesor,
  Total Documentos, Subtotal (sin impuestos), Subtotal NC y Total —este último
  es la resta de los dos anteriores, es decir la venta neta SIN impuestos—,
  tanto en pantalla como en el PDF y el Excel. Reemplazan al desglose anterior
  de Base 0% / Base IVA / Total IVA / Gran Total / Saldo, que sigue disponible
  en las demás agrupaciones y en las tarjetas superiores. El saldo por asesor se
  consulta ahora en el panel de detalle (clic en la fila) o en la hoja "Detalle
  Documentos" del Excel. Además, en el PDF los conteos de documentos ya no se
  imprimen con decimales.
- **1.1** — Se agrega la columna **Saldo** (lo pendiente de cobro) en las
  agrupaciones Por Vendedor, Por Mes y Detallado, en el panel de detalle por
  vendedor, en el PDF y en las dos hojas del Excel, más la tarjeta "Saldo
  Pendiente por Cobrar". Además se reescribieron las consultas del reporte para
  que filtren el período ANTES de agregar el detalle (antes sumaban todo el
  histórico de la empresa en cada consulta), lo que reduce mucho el tiempo de
  generación. Requiere ejecutar `database/reporte_ventas_vendedor_indices.sql`.
- **1.0** — Versión inicial: filtros por vendedor/producto/marca/categoría/
  fecha, cálculo de ventas netas, exportación a PDF/Excel y envío por correo.
