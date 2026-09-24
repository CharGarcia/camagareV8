---
titulo: Reporte de Ventas por Vendedor
resumen: Ventas netas (facturas menos notas de crédito) desglosadas por asesor/vendedor, producto, marca o categoría.
categoria: Ventas
ruta_modulo: modulos/reporte_ventas_vendedor
tipo: modulo
visibilidad: todos
etiquetas: reporte de ventas por vendedor, reporte por asesor, comisiones, ventas netas, ventas por marca, ventas por categoría, rendimiento de vendedores, subtotal ventas menos notas de credito, subtotal sin impuestos, subtotal nc, total documentos por asesor, cuantas facturas hizo cada vendedor, saldo pendiente por vendedor, cartera por asesor, cuanto le deben a cada vendedor, facturas por cobrar por vendedor, solo mis ventas, cada asesor ve lo suyo, el vendedor no debe ver las ventas de otros, mis comisiones, acceso total, permiso de ver todos, registros propios, cartera del vendedor, mis clientes, clientes asignados, vendedor vinculado, usuario del sistema, nivel de usuario, administrador ve todo, el asesor ve las ventas de todos, filtro vendedor fijo, no ver ventas de otros vendedores
version: 1.9
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
   fechas) o escribiendo directamente Fecha Desde/Hasta. Al abrir, el reporte
   ya viene con el **mes en curso** (del 1 al último día del mes); elija *Todos*
   en Año o Mes para ver todo el histórico.
4. Opcionalmente filtrar por Vendedor (uno específico o "Todos"), Marca,
   Categoría o Producto (buscador con autocompletado). Estos filtros solo
   acotan: no cambian qué ventas puede ver cada usuario. A un asesor sin
   *Acceso total* el filtro Vendedor le aparece fijo en su propio nombre (ver
   *Quién ve qué*).
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
- Las **retenciones** se descuentan con la misma regla de Cuentas por Cobrar: a
  cada factura se le resta lo retenido en las líneas de la retención que la
  sustentan (por número de comprobante, aunque venga sin guiones o sin ceros),
  y si una sola retención electrónica cubre varias facturas, cada una recibe
  solo su parte. Las notas de crédito y de débito se cruzan igual, por número
  normalizado.
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
| Vendedor | No | Un asesor específico o todos. A un usuario de nivel 1 sin *Acceso total* no le deja elegir: muestra fijo su propio vendedor (o *Sin vendedor vinculado*) |
| Marca / Categoría / Producto | No | Acotan el reporte a lo vendido de ese producto/marca/categoría |
| Fecha Desde / Hasta | No | Rango de fechas de emisión a incluir |

## Quién ve qué: nivel del usuario y permiso de Acceso total

Para entrar al reporte se necesita el permiso de **Ver** (`r`) del submódulo,
como en cualquier módulo. Lo que cada quien ve dentro del reporte sigue la
misma regla que **Reporte de Ventas** y **Cuentas por Cobrar**:

| Usuario | Qué ve |
|---|---|
| **Nivel 3 — Superadministrador** | Todas las ventas de la empresa |
| **Nivel 2 — Administrador** | Todas las ventas de la empresa, tenga o no marcado *Acceso total* |
| **Nivel 1 con Acceso total** | Todas las ventas de la empresa |
| **Nivel 1 sin Acceso total, que es vendedor** | **Solo lo de su vendedor**: las ventas que llevan **su nombre** en el campo *Vendedor* y, si una venta no tiene vendedor, las de los **clientes que tiene asignados** (campo *Vendedor* de la ficha del cliente). Nunca las que llevan el nombre de otro vendedor, aunque el cliente sea suyo |
| **Nivel 1 sin Acceso total, que no es vendedor** (un cajero, un digitador) | Solo los documentos que **él registró** |

- La regla se aplica en todo el módulo: la tabla, las tarjetas de totales, el
  resumen de estados, todas las agrupaciones, el detalle al hacer clic en una
  fila, el PDF, el Excel y el envío por correo.
- Las notas de crédito cuentan para el **vendedor de la propia nota** (campo
  *Vendedor* del modal de la NC) y, si la nota no tiene vendedor, para el
  vendedor asignado a su cliente.
- **El filtro *Vendedor* queda fijo** para estos usuarios: el vendedor muestra
  su propio nombre y quien no es vendedor ve *Sin vendedor vinculado*. No
  pueden elegir otro, y el sistema ignora cualquier otro vendedor que se le
  envíe.
- En la vista **Por Vendedor**, un asesor ve su propia fila y, si tiene ventas
  sin vendedor de sus clientes, la fila *Sin vendedor asignado*. Nunca ve filas
  de otros vendedores.
- Para que un asesor vea solo lo suyo, su usuario debe ser de **nivel 1** y
  **no** tener marcado *Acceso total* en este submódulo (*Configuración →
  Permisos por módulo*).

**Cómo se sabe qué vendedor es cada usuario.** Por dos vías, en este orden:

1. **El campo *Usuario del sistema*** de la ficha del vendedor
   (`modulos/vendedores`): ahí el administrador declara qué cuenta es ese
   asesor. Es lo que manda.
2. **La cédula**, si no hay vínculo declarado: el registro de Vendedores de la
   empresa activa cuya *Identificación* sea la misma cédula con la que el
   usuario inicia sesión (se comparan solo los dígitos, así que dan igual
   guiones o espacios; y también calza si uno está registrado con el RUC de
   persona natural y el otro con la cédula, p. ej. 1712345678 y
   1712345678001).

No se usa el correo a propósito: es común que varios usuarios compartan el
correo de la empresa y el cruce le entregaría a un asesor las ventas de otro.

Si un usuario de nivel 1 sin Acceso total no tiene vendedor por ninguna de las
dos vías, el reporte lo trata como alguien que **no es vendedor** y le muestra
solo los documentos que él registró. Para que vea las ventas de su vendedor,
abrir su vendedor en **Vendedores** y elegirlo en *Usuario del sistema* (o
corregirle la identificación para que sea su cédula).

Para usuarios sin vendedor, una advertencia sobre documentos antiguos: los que
se **migraron** desde el sistema anterior quedaron a nombre del usuario que
corrió la migración, así que solo él (o alguien con acceso total) los verá.

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
- **Vendedores** (`modulos/vendedores`): catálogo de asesores. Su campo
  *Usuario del sistema* dice qué vendedor es cada usuario.
- **Clientes** (`modulos/clientes`): el campo *Vendedor* de la ficha decide a
  qué asesor corresponden las ventas del cliente que no tienen vendedor.
- **Facturas de Venta** y **Notas de Crédito** (ventas): fuente de los datos.
- **Productos, Marcas y Categorías**: catálogos usados para filtrar/agrupar.
- **Configuración de Correo** de la empresa: usada para el envío del reporte.

## Errores frecuentes

- **Una nota de crédito no se resta de ningún vendedor**: ocurre cuando esa
  NC no pudo vincularse a la factura original (el número de documento
  modificado no corresponde a ninguna factura de la empresa; desde la versión
  1.8 el formato —con o sin guiones, con o sin ceros— ya no importa).
- **El saldo de una factura no cuadra con Cuentas por Cobrar**: desde la
  versión 1.8 ambos usan el mismo cálculo de retenciones y notas. Si aún
  difiere, revise en *Retenciones en ventas* que el número de sustento apunte
  a esa factura.
- **El correo no se envía**: revisar que la empresa tenga configurado el
  correo de envío de documentos en `/config`.
- **Un asesor no ve sus ventas**: abrir su ficha en **Vendedores** y comprobar
  que el campo *Usuario del sistema* apunte a su cuenta (o que la
  identificación sea su cédula); sin eso el sistema no lo reconoce como
  vendedor y solo le muestra lo que él registró. Revisar también que las
  facturas lleven su nombre en el campo Vendedor: una venta a su cliente
  emitida a nombre de otro vendedor no le aparece.
- **Un asesor ve las ventas de todos**: revisar dos cosas. El nivel de su
  usuario en *Configuración → Usuarios* (los niveles 2 y 3 ven todo el reporte)
  y que no tenga marcado *Acceso total* en este submódulo (*Configuración →
  Permisos por módulo*).

## Historial de cambios

- **1.9** — Las **notas de crédito** se agrupan y filtran por **su propio vendedor** (nuevo
  campo *Vendedor* de la nota), ya no por el de la factura que modifican: si en la nota se
  eligió otro asesor, la rebaja se descuenta a ese asesor. Las notas ya emitidas reciben el
  vendedor de su factura (o el del cliente) con el script
  `database/20260924_nc_cabecera_id_vendedor.sql`.

- **1.8** — **Corrección del Saldo**: las retenciones se descontaban con el
  total de la retención a **cada** factura que sustentaba (una retención
  electrónica de dos facturas se restaba entera a las dos) y el cruce con
  retenciones, notas de crédito y notas de débito era por número literal, así
  que un comprobante con el número sin guiones o sin ceros no se restaba. Ahora
  usa la misma regla que Cuentas por Cobrar y el Reporte de Ventas (lo retenido
  en las líneas que sustentan la factura, número normalizado). Aplica al saldo
  del Detallado, Por Mes, el panel de detalle por vendedor, la tarjeta *Saldo
  Pendiente*, el PDF y el Excel.
- **1.7** — **Un saldo de $0.01 se muestra en rojo**, como
  pendiente, igual que en *Cuentas por Cobrar*. Antes salía en verde, como si
  estuviera cobrado.


- **1.6** — Lo que ve cada usuario sigue ahora la **misma regla que Reporte de
  Ventas y Cuentas por Cobrar**. Los niveles 2 y 3 siguen viendo todas las
  ventas. El nivel 1 vuelve a depender del permiso *Acceso total*: con él ve
  todo; sin él, si es vendedor, ve **solo lo de su vendedor** —las ventas con
  su nombre y, las que no tienen vendedor, las de sus clientes asignados— y, si
  no lo es, solo lo que registró. Antes el nivel 1 veía únicamente las facturas
  con su nombre, aunque tuviera acceso total, y el reporte salía vacío si no
  tenía vendedor. El filtro Vendedor sigue fijo para estos usuarios. Nueva
  sección *Quién ve qué*.
- **1.5** — El reporte abre con el **mes actual** precargado: Año y Mes en
  curso seleccionados y Fecha Desde/Hasta del 1 al último día del mes. Antes
  abría con *Todos* y sin fechas, cargando todo el histórico.
- **1.4** — La ficha del vendedor gana el campo **Usuario del sistema**
  (`modulos/vendedores`), que declara a mano qué cuenta es ese asesor y tiene
  prioridad sobre el cruce por cédula. Pensado para los asesores cuya
  identificación no está cargada o no coincide con la cédula de su usuario.
  Requiere ejecutar `database/vendedores_usuario_vinculado.sql`.
- **1.3** — El alcance de lo que ve cada usuario pasa a depender del **nivel**
  y no del permiso *Acceso total*: los niveles 2 (administrador) y 3
  (superadministrador) ven las ventas de todos los asesores, y el nivel 1 solo
  las suyas, aunque tenga marcado acceso total. Además, el vendedor de cada
  usuario ya no se busca por la columna `vendedores.id_usuario` —que en
  realidad guarda quién creó el registro, no a quién pertenece—, sino cruzando
  la **cédula** del usuario (la misma del inicio de sesión) con la
  *Identificación* del vendedor. Así el asesor ve su reporte sin que nadie
  tenga que vincularlo a mano.
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
