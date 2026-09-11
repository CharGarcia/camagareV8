---
titulo: Cuentas por pagar
resumen: Qué le debe la empresa a sus proveedores, con sus vencimientos, y registro del pago.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_pagar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por pagar, cxp, deudas, proveedores, saldo pendiente, vencimiento, pagar, obligaciones, fecha de corte, saldo a una fecha, fecha hasta, consolidado, establecimientos, sucursales, matriz, mismo ruc, deudas consolidadas, todas las sucursales, valores de terceros, otros conceptos, valores adicionales, bomberos, tasa de basura, planilla de luz, supera el saldo pendiente, filtrar por proveedor, error de conexion, serie, punto de emision, serie inactiva, registrar pago
version: 1.9
orden: 50
estado: activo
---

**Cuentas por pagar** es el espejo de las cuentas por cobrar: qué facturas de
compra siguen sin pagarse, de qué proveedor y cuándo vencen.

## De dónde sale el saldo

Del conjunto de:

- Las **compras** registradas y no pagadas.
- Los **saldos iniciales** de proveedores cargados al empezar.

Menos lo ya pagado mediante egresos.

El **vencimiento** se calcula con el *plazo* configurado en la ficha del
proveedor. Si un documento vence antes de lo que esperaba, ese es el campo a
revisar.

## Consolidado de establecimientos (solo desde la matriz)

Cuando un mismo RUC tiene varios establecimientos registrados como empresas
distintas (matriz y sucursales), las deudas de cada uno viven por separado.
Desde la **matriz** se puede ver la cartera por pagar de todo el grupo en una
sola pantalla con el filtro **Establecimientos**:

- **Solo este (matriz)**: comportamiento normal, únicamente los documentos de la
  empresa activa.
- **Consolidado (N establec.)**: suma las facturas de compra, liquidaciones,
  importaciones y saldos iniciales de todos los establecimientos del mismo RUC a
  los que el usuario tiene acceso. Las tarjetas, el gráfico de antigüedad, la
  vista *Por proveedor*, el PDF y el Excel consolidan de la misma forma. Aparece
  una tarjeta extra con la cantidad de establecimientos incluidos.

Reglas:

- El filtro **solo aparece en la matriz** del grupo (la empresa marcada como
  matriz en *Empresas*) y solo si existe al menos otro establecimiento accesible.
  En una sucursal no se muestra.
- Un usuario que no es superadministrador solo ve los establecimientos que tiene
  asignados; los demás no entran al consolidado aunque compartan RUC.
- Cada documento muestra un **badge con el código del establecimiento** (001,
  002, …) al inicio de la columna *Documento*; al pasar el mouse se ve el nombre.
- **Pagar un documento de otra sucursal desde la matriz**: el botón de pago de
  la fila abre el mismo modal, pero el egreso se registra **en los libros de la
  sucursal dueña del documento**: sus series (puntos de emisión), su secuencial
  de egresos, sus conceptos, sus formas de pago y su contabilidad. El modal lo
  avisa con una franja azul con el nombre del establecimiento. La matriz no
  registra nada propio: no hay asiento intercompañías.
- Para pagar en una sucursal el usuario necesita permiso de **crear** en
  Cuentas por Pagar **en esa sucursal** (superadministrador siempre puede). Si
  no lo tiene, el botón aparece deshabilitado con el aviso "Sin permiso para
  registrar pagos en el establecimiento…".
- El historial de pagos de un documento de otra sucursal se consulta desde la
  matriz. Al hacer clic en la fila, el panel de detalle muestra solo el resumen.
- El buscador de **Proveedor** busca en todos los establecimientos y muestra al
  proveedor una sola vez por identificación; al elegirlo, el filtro alcanza sus
  documentos en todas las sucursales (el cruce es por RUC, porque cada
  establecimiento tiene su propia lista de proveedores).
- Cada establecimiento se filtra por **su propio ambiente** (producción o
  pruebas), no por el de la matriz.
- En el PDF y el Excel, el encabezado indica *Alcance: Consolidado por RUC* con
  la lista de establecimientos, y se agrega la columna **Estab.**

## Fecha Hasta como fecha de corte

El filtro **Fecha Hasta** no solo limita qué documentos se muestran (los
emitidos hasta esa fecha): también es la **fecha de corte del saldo**. Los
pagos, retenciones y notas de crédito o débito fechados **después** de esa
fecha no se descuentan, así el listado muestra lo que se debía **ese día**.

Ejemplo: una compra pagada el 31 de mayo aparece pendiente, con su saldo
completo, en cualquier consulta con Fecha Hasta igual o anterior al 30 de mayo,
y desaparece de los pendientes a partir del 31.

La regla es la misma que en Cuentas por Cobrar y aplica por igual a las
compras, liquidaciones, importaciones y **saldos iniciales**; las tarjetas
superiores, el gráfico de antigüedad y las exportaciones respetan el corte.
Sin Fecha Hasta, el saldo es el actual.

La fecha que manda para un pago es la **fecha del egreso**. Si el egreso se
generó automáticamente (descarga del SRI o *Generar pagos pendientes*), esa
fecha es la de la compra, aunque el cheque tenga fecha posterior.

## Notas de crédito y débito del proveedor

Las notas de crédito y débito que emite el proveedor **no aparecen como
documentos sueltos** en este listado: se restan (o suman) directamente al saldo
de la factura que modifican. Así el listado muestra lo que realmente se le debe a
cada proveedor, y no tres líneas que hay que compensar mentalmente.

## Registrar el pago

Se registra desde el propio listado. Equivale a crear un egreso: reduce el saldo
del documento, deja constancia de la forma de pago y genera el asiento contable.

En las **planillas de luz y agua**, el saldo del documento incluye los rubros que
la distribuidora recauda para terceros (bomberos, tasa de basura): no están
dentro del importe declarado al SRI, pero sí se pagan. Ver *Planillas de luz y
agua: valores de terceros* en el manual de Compras.

También queda disponible el **historial de pagos** de cada documento, útil cuando
una factura se pagó en varias partes.

### Serie del pago: solo puntos de emisión activos

La lista **Serie** del modal muestra únicamente los puntos de emisión en estado
**activo**; los inactivos no aparecen. Es el mismo criterio de Egresos y
Cuentas por Cobrar, porque el pago emite un egreso nuevo con el secuencial de
esa serie. En el consolidado, la lista es la de la sucursal dueña del documento.

- Para usar una serie que no aparece, actívela en **Empresa**, pestaña
  **Puntos de Emisión**.
- Si la empresa no tiene ningún punto activo, la lista muestra *Sin series
  activas* y el pago no se puede registrar.
- Si una serie se inactiva con el modal ya abierto, al guardar el sistema
  rechaza el pago con el aviso *La serie (punto de emisión) no es válida o
  está inactiva*: cierre el modal y vuelva a abrirlo.

## Errores frecuentes

- **Un documento aparece vencido antes de tiempo**: revise el *plazo* del
  proveedor.
- **El saldo no coincide con lo que dice el proveedor**: compruebe si hay notas
  de crédito aplicadas a esa factura.
- **Pagué y sigue pendiente**: verifique que el egreso quedó aplicado a ese
  documento y no registrado como concepto general.
- **Una serie no aparece en el modal de pago**: está **inactiva**. Solo se
  ofrecen los puntos de emisión activos; actívela en Empresa, pestaña Puntos de
  Emisión (ver *Serie del pago: solo puntos de emisión activos*).

## Qué comprobantes de compra aparecen

Aparece como cuenta por pagar todo comprobante de compra que genera una
obligación con el proveedor: la factura, la **nota de venta**, los documentos de
instituciones financieras, las planillas de servicios básicos y los demás tipos
autorizados por el SRI, además de las liquidaciones de compra, importaciones y
saldos iniciales. Una liquidación de compra se muestra desde que se autoriza y
sigue visible cuando pasa a *contabilizado*; solo sale de la lista al anularse o
al quedar pagada. Las compras anuladas o rechazadas no se muestran. No aparecen
como fila las notas de crédito y de débito recibidas: esas ajustan el saldo de
la factura que modifican. Mismo criterio que
el Reporte de Cartera y que el asiento contable de la compra.

## Historial de cambios

- **1.9** — La lista **Serie** del modal de pago ya no ofrece puntos de emisión
  **inactivos**: solo los activos, igual que Egresos y Cuentas por Cobrar. El
  servidor también rechaza un pago con una serie inactiva, y si la empresa no
  tiene ninguna activa la lista lo indica (*Sin series activas*). Nueva sección
  *Serie del pago: solo puntos de emisión activos*.
- **1.8** — Se corrigió el filtro por **proveedor**: al elegir uno, la pantalla
  mostraba «Error de conexión» en vez de sus documentos. Las tarjetas de resumen y
  la antigüedad de saldos también vuelven a filtrarse por el proveedor elegido.
- **1.7** — El listado abre **mucho más rápido**. Con varios miles de compras, la
  pantalla podía tardar más de diez segundos en mostrar aunque solo hubiera cuatro
  documentos pendientes; ahora responde en décimas. También se corrigió el orden:
  las filas con la misma fecha de vencimiento ya no cambian de posición entre una
  carga y otra.
- **1.6** — Al **registrar el pago** de una planilla de luz o agua, el saldo que
  se valida ya incluye los **valores de terceros** (bomberos, tasa de basura). El
  listado sí los mostraba, pero al guardar el pago se rechazaba el monto por
  "superar el saldo pendiente"; ahora ambos usan la misma cifra.
- **1.5** — Consolidado, fase 2: desde la matriz ya se puede **registrar el
  pago** de una factura de compra, liquidación, importación o saldo inicial de
  otra sucursal. El egreso se registra en los libros de la sucursal dueña (sus
  series, secuencial, conceptos, formas de pago y contabilidad) y exige permiso
  de crear en esa sucursal.
- **1.4** — Nuevo filtro **Establecimientos** para ver las deudas **consolidadas
  de todos los establecimientos del mismo RUC**, disponible solo desde la
  **matriz** del grupo (fase 1, solo lectura): los documentos de las sucursales
  se listan con el badge de su establecimiento, suman en tarjetas, antigüedad,
  PDF y Excel, y permiten ver su historial, pero el pago se registra desde la
  empresa dueña del documento. El buscador de proveedor cruza por identificación
  entre establecimientos.
- **1.3** — El PDF y el Excel exportados muestran, bajo el encabezado, los
  **filtros aplicados** (tipo de documento, estado, período y proveedor), para
  que quien lo reciba sepa exactamente qué cartera está viendo. En el Excel los
  montos ahora son celdas numéricas con dos decimales y sin separador de miles,
  listas para sumar.
- **1.2** — Se listan y se pueden pagar todos los comprobantes de compra que generan deuda (notas de venta, documentos financieros, planillas, etc.), no solo la factura. Las **liquidaciones de compra ya contabilizadas** vuelven a aparecer (antes desaparecían de la cartera al registrarse su asiento). Las compras anuladas o rechazadas dejan de mostrarse como deuda. También aplica al saldo de la ficha del proveedor y al pago automático a proveedores.
- **1.1** — Los **saldos iniciales** respetan la fecha de corte igual que las
  compras: con Fecha Hasta, un pago posterior a esa fecha ya no descuenta el
  saldo inicial (antes se usaba el acumulado pagado sin importar la fecha).
  Aplica al listado, a las tarjetas y al gráfico de antigüedad.
- **1.0** — Versión inicial.
