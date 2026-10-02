---
titulo: Comprobar un módulo con la contabilidad
resumen: Cómo leer la pantalla "Comprobación con Contabilidad" de Inventarios, Cuentas por Cobrar y Cuentas por Pagar, que compara el saldo del módulo con el de sus cuentas contables documento por documento.
categoria: Contabilidad
tipo: concepto
visibilidad: todos
etiquetas: comprobar con contabilidad, cuadre de caja, caja vs contabilidad, anticipos vs contabilidad, comprobacion con contabilidad, cuadre contable, cuadrar con contabilidad, conciliar con contabilidad, no cuadra con contabilidad, diferencia con contabilidad, saldo contable, mayor, mayor comparado, cartera vs contabilidad, inventario vs contabilidad, kardex vs contabilidad, cuentas por cobrar vs mayor, cuentas por pagar vs mayor, sin asiento contable, solo en contabilidad, asiento sin documento, documento anulado con asiento, monto distinto, fecha en otro periodo, empieza descuadrado, diferencia acumulada, cuenta contable del modulo, configuracion contable, auditoria contable, saldos iniciales, apertura
version: 1.3
orden: 30
estado: activo
---

Varios módulos llevan un saldo propio que la contabilidad también registra:
lo que vale el inventario, lo que deben los clientes, lo que se debe a los
proveedores, lo que hay en el banco. El botón **Comprobar con Contabilidad**
compara los dos y, si no cuadran, muestra **qué documento** causa la
diferencia. No modifica nada: es solo una revisión.

## En qué módulos está

| Módulo | Dónde está el botón | Qué compara |
|--------|---------------------|-------------|
| [Reporte de inventarios](../modulos/reporte-inventarios.md) | Pestaña **Auditoría**, en la cabecera de la tabla | Valor del inventario según el kardex contra las cuentas de inventario |
| [Cuentas por cobrar](../modulos/cuentas-por-cobrar.md) | En la cabecera de la tabla, junto a PDF y Excel | Saldo de la cartera de clientes contra las cuentas por cobrar |
| [Cuentas por pagar](../modulos/cuentas-por-pagar.md) | En la cabecera de la tabla, junto a PDF y Excel | Saldo por pagar a proveedores contra las cuentas por pagar |
| [Control bancario](../modulos/control-bancario.md) | Arriba, en la barra de botones | Saldo del banco según Ingresos/Egresos contra la cuenta contable del banco |
| [Activos fijos](../modulos/activos-fijos.md) | En la cabecera de la tabla, junto a PDF y Excel | Costo contra la cuenta del activo, y depreciación acumulada contra su cuenta |
| [Estados financieros](../modulos/estados-financieros.md) | Botón **Cuadre con Módulos** | Todos los anteriores en una sola tabla, vistos desde la contabilidad, más **cada caja** (formas de pago que no son banco) los **anticipos** de clientes y de proveedores y las **tarjetas por liquidar**, que solo se comparan aquí; *Ver detalle* abre la comparación de cada uno |

La comprobación siempre abarca **la empresa activa completa**: no aplica los
filtros de la pantalla (cliente, proveedor, bodega, producto, vendedor ni el
consolidado), porque la cuenta contable no los distingue.

## Qué cuentas contables se comparan

Las que están configuradas en **Configuración Contable** para los conceptos
del módulo, en **cualquier nivel** (General, Cliente o Proveedor, Producto,
Categoría, Marca…):

| Módulo | Conceptos de Configuración Contable |
|--------|-------------------------------------|
| Inventarios | Todos los de inventario: compras, ventas (costo), recibos, importaciones y consignaciones |
| Cuentas por cobrar | Cuenta por cobrar de Facturas de Venta y de Recibos de Venta |
| Cuentas por pagar | Cuenta por pagar de Compras y de Importaciones (proveedor del exterior) |

Si varias reglas usan cuentas distintas, se suman todas. La tabla **Cuentas
contables comparadas** muestra cada una con su saldo al inicio y al final del
período. Si el módulo no tiene ninguna cuenta configurada, la pantalla lo avisa
y no compara nada.

## Elegir el período

Arriba del modal están **Desde** y **Hasta**. Se precargan con las fechas del
filtro del módulo (o, si no hay, desde el 1 de enero del año de la fecha
Hasta hasta hoy). Cámbielas y presione **Mostrar** para volver a comprobar.

## Cómo leer el resumen

La primera tabla tiene tres líneas, cada una con su diferencia:

| Línea | Qué compara |
|-------|-------------|
| Saldo al inicio del período | Todo lo anterior a la fecha Desde |
| Movimiento del período | Lo registrado entre Desde y Hasta |
| Saldo al final del período | Todo hasta la fecha Hasta |

La columna del módulo (por ejemplo **Según Cartera** o **Según Kardex**) es
el saldo tal como lo calcula ese módulo; la nota debajo de la tabla explica
exactamente qué suma en cada caso.

## Buscar dónde se descuadra (saldo por saldo)

La tabla **Movimientos del período, saldo por saldo** se lee como un mayor de
los dos lados a la vez:

1. La primera fila es el **saldo al inicio**, de cada lado, con su diferencia.
2. Después viene **cada documento del período** (factura, cobro, retención,
   compra, consignación…) con lo que movió en el módulo y lo que movió su
   asiento en las cuentas contables, en orden de fecha. Cada fila arrastra el
   saldo de cada lado y la **diferencia acumulada**.
3. La última fila es el **saldo al final**.

La fila donde **cambia la diferencia acumulada** es la que descuadra: queda
marcada con una raya roja a la izquierda y, bajo la diferencia acumulada, se ve
lo que esa fila agrega. Un monto **tachado** tiene su fecha fuera del período,
así que no suma en ese lado. El número del asiento abre el asiento.

El interruptor **Ver solo las filas con diferencia** oculta las que cuadran;
los saldos acumulados siguen contando todas. La tabla muestra hasta 3000
movimientos; si hay más, acorte el período.

## Qué significa cada situación

| Situación | Qué pasa | Qué revisar |
|-----------|----------|-------------|
| Cuadra | El documento y su asiento mueven lo mismo | Nada |
| Documento sin asiento | El documento suma en el módulo, pero no tiene **ningún** asiento contabilizado (borrador, pendiente de contabilizar, o un movimiento que no genera asiento, como un ajuste) | Generarlo desde Auditoría Contable; en un borrador, se genera al autorizarlo |
| Asiento sin cuenta de *(inventario, por cobrar…)* | El documento **sí tiene asiento**, pero ninguna de sus líneas toca las cuentas comparadas. Típico de las **facturas migradas** del sistema anterior, que solo registraron la venta sin el costo contra inventario. El número del asiento (atenuado) lo abre | La cuenta en Configuración Contable y regenerar el asiento; en lo migrado, un asiento de ajuste a la fecha de la migración |
| Solo en contabilidad | Asiento sin un documento del módulo detrás (manual, migrado o de otro módulo) | Si el asiento manual corresponde, o si debió hacerse con un documento |
| Documento que no suma aquí | El asiento es de un documento que el módulo no cuenta: anulado, eliminado, en un estado que no suma, o que no se aplica a ningún documento del módulo (p. ej. una retención sin factura) | Anular el asiento del documento anulado, o enlazar el documento |
| Monto distinto | Los dos lados mueven montos distintos | El asiento (cuenta o valor) o el documento |
| Fecha en otro período | Mismo monto, pero las fechas caen en períodos distintos | La fecha del documento o del asiento |
| Asiento de documento anulado (bancos) | El ingreso/egreso está anulado o eliminado, pero su asiento sigue contabilizado | Anular o eliminar ese asiento |
| Cobrado/pagado con otra forma (bancos) | El asiento mueve la cuenta del banco, pero el documento se registró con otra forma de pago (p. ej. Efectivo) | La forma de pago del documento o la cuenta de su asiento |

Los **saldos iniciales** del módulo se comparan juntos, en una sola fila,
contra los asientos de **apertura**.

## Si el período ya empieza descuadrado

La diferencia viene de antes de la fecha Desde. Para encontrar la fila que la
causa, ponga como fecha Desde el comienzo de las operaciones y vuelva a
comprobar.

## Quién puede usarla

El botón aparece solo a quien tiene acceso a la **contabilidad**: permiso de
ver el módulo [Estados financieros](../modulos/estados-financieros.md) en la
empresa activa (el superadministrador siempre lo tiene). Es así porque la
comprobación muestra saldos contables y los totales de toda la empresa. Un
usuario sin ese permiso no ve el botón aunque use el módulo.

Además, en el **Reporte de inventarios** el botón está dentro de la pestaña
Auditoría, así que también hay que poder ver esa pestaña.

El permiso se asigna en **Configuración → Permisos de módulos**, en el
submódulo *Estados financieros*.

## Historial de cambios

- **1.3** — Comparación de **tarjetas por liquidar** (en Estados financieros) y de **activos fijos** (costo y depreciación acumulada).
- **1.2** — La situación *Sin asiento contable* se separa en **Documento sin asiento** (no tiene ninguno) y **Asiento sin cuenta de…** (tiene asiento, pero no toca las cuentas comparadas, como las facturas migradas sin costo de ventas), con enlace a ese asiento.
- **1.1** — Comparación de **caja** y de **anticipos** de clientes y proveedores. El mismo cuadre desde la contabilidad: botón **Cuadre con Módulos** en
  Estados financieros. La pantalla de detalle muestra también las situaciones
  propias de los bancos (asiento de documento anulado, cobrado o pagado con otra
  forma de pago).
- **1.0** — Versión inicial: comprobación en Reporte de inventarios, Cuentas por
  cobrar y Cuentas por pagar, con el mismo diseño que la de Control bancario.
