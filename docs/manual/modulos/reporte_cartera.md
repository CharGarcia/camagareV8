---
titulo: Reporte de Cartera
resumen: Estado de cuenta cronológico de un cliente o proveedor, con saldo corriendo, exportable a PDF/Excel y por correo.
categoria: Reportes
ruta_modulo: modulos/reporte_cartera
tipo: modulo
visibilidad: todos
etiquetas: cartera, estado de cuenta, filtro por documento, numero de factura, kardex de cliente, kardex de proveedor, saldo, cuentas por cobrar, cuentas por pagar, historial de pagos, historial de cobros, deuda, adeudado
version: 1.5
orden: 0
estado: activo
---

El Reporte de Cartera muestra, para uno o varios clientes o proveedores, el
historial completo de documentos (facturas, compras, notas de crédito/débito)
y de pagos o cobros, ordenado por fecha con un saldo que se va acumulando —
como el kardex de un producto, pero aplicado a la deuda de un tercero. Al
final se ve cuánto se le adeuda (o se le debe) hasta la fecha.

## Qué es y para qué sirve

Complementa a [Cuentas por Cobrar](cuentas_por_cobrar.md) y
[Cuentas por Pagar](cuentas_por_pagar.md): esos módulos listan **documentos
con saldo pendiente** (una fila por factura/compra); este módulo arma el
**historial cronológico de un tercero puntual** — cada cargo (lo que se le
factura) y cada abono (lo que paga o se le cobra) en el orden en que
ocurrieron, con el saldo corriendo después de cada movimiento. Sirve para
enviarle a un cliente su estado de cuenta, o para revisar con un proveedor en
qué quedó su saldo.

## Cómo se usa

1. Elegir el tipo: **Cliente** o **Proveedor**.
2. Buscar y seleccionar uno o varios (aparecen como chips debajo del
   buscador; se puede quitar cada uno con la X) — o marcar **Todos** para
   incluir automáticamente a todos los que tengan saldo pendiente a la fecha
   de corte, sin buscarlos uno por uno.
3. Ajustar el período con **Mes/Año** (atajo) o con **Fecha Desde/Fecha
   Hasta** directamente. Dejar las fechas vacías trae todo el historial.
4. Opcionalmente, elegir un **Documento**: el buscador (a la derecha del
   cliente/proveedor) lista las facturas, recibos, compras, liquidaciones,
   importaciones y saldos iniciales **del cliente o proveedor ya
   seleccionado**; se puede escribir parte del número (con o sin guiones ni
   ceros). Al elegir uno, el estado de cuenta muestra solo ese documento y
   los abonos que lo cancelan (cobros o pagos, retenciones, notas de
   crédito/débito). Con un documento fijado, **Backspace o Delete** limpia
   el filtro de una vez.
5. Hacer clic en **Generar**. Aparece una tarjeta por cada entidad
   incluida, con su tabla de movimientos y saldo final.
6. En el **encabezado de cada tarjeta**, antes del nombre, están los botones
   **PDF**, **Excel** y **Correo** de ese cliente o proveedor: exportan o
   envían el estado de cuenta de **esa entidad** con el período y el
   documento filtrados. El modal de correo precarga el email registrado en
   su ficha (se puede cambiar o agregar varios separados por coma).

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|----------------|
| Tipo | Sí | Cliente o Proveedor. Cambiarlo limpia la selección de entidades (son catálogos distintos). |
| Cliente(s) / Proveedor(es) | Sí, salvo que se use "Todos" | Uno o varios, por buscador predictivo. |
| Todos | No | Reemplaza a la selección manual: incluye a todo cliente/proveedor con saldo pendiente (mayor a cero) a la fecha de corte (Fecha Hasta, o la fecha actual si no se indica). Mientras está activo, el buscador se deshabilita. |
| Mes / Año | No | Atajo que llena Fecha Desde/Hasta con el mes o año elegido. |
| Fecha Desde / Fecha Hasta | No | Rango del estado de cuenta. Vacío en ambos = todo el historial. Si se llena Fecha Desde, el saldo antes de esa fecha se muestra como "Saldo Anterior". |
| Documento | No | Número de un documento del cliente/proveedor seleccionado (o de cualquiera, si está marcado "Todos"). Limita el estado de cuenta a ese documento y a los abonos que lo cancelan. El número se compara normalizado: `001-001-13`, `001001000000013` y `001-001-000000013` son el mismo documento. |

## Permisos

Es un reporte de solo lectura: solo aplica el permiso **Ver** (`r`). No hay
distinción de "acceso total" — cualquier usuario con permiso de lectura sobre
el módulo puede generar el estado de cuenta de cualquier cliente o proveedor
de la empresa activa.

## Reglas de negocio

- **Deuda Generada** (llamada "Cargo" internamente; aumenta lo que la entidad debe): facturas de venta, recibos de
  venta, notas de débito emitidas y el saldo inicial de CxC (del lado
  cliente); facturas de compra, liquidaciones de compra, facturas de
  proveedor del exterior, notas de débito recibidas y el saldo inicial de CxP
  (del lado proveedor).
- **Abonos** (disminuyen la deuda): cobros, retenciones y notas de crédito
  emitidas (cliente); pagos, retenciones y notas de crédito recibidas
  (proveedor). Las notas de crédito/débito **recibidas** de un proveedor no
  tienen tabla propia — son filas de `compras_cabecera` distinguidas por
  `tipo_comprobante` (`04` NC, `05` ND).
- Se excluyen documentos anulados, en borrador o (en el caso de recibos de
  venta) ya facturados — el mismo criterio que usan Cuentas por Cobrar y
  Cuentas por Pagar.
- **A quién se atribuye cada abono**: al cliente o proveedor **del documento
  que cancela**, no al que quedó escrito en el propio abono. Un cobro se
  atribuye al cliente de la factura, recibo o saldo inicial que cobra; una
  retención de venta, al cliente de la venta a la que aplica (por su enlace
  directo o por el número de documento sustento); una nota de crédito o
  débito, al cliente de la factura que modifica. Del lado proveedor, un pago
  se atribuye al proveedor de la compra, liquidación, importación o saldo
  inicial que paga, y una retención al de la compra o liquidación retenida.
  Solo si el abono no tiene documento enlazado se usa el tercero indicado en
  el abono. Es el mismo enlace que usan Cuentas por Cobrar y Cuentas por
  Pagar, así los saldos cuadran entre los tres módulos.
- **Cobros y pagos a saldos iniciales** cuentan como abono (antes solo se
  restaban los cobros a facturas y recibos, y el saldo inicial quedaba
  siempre como deuda completa).
- **Ambiente**: solo entran los documentos del ambiente actual de la
  empresa (producción o pruebas), igual que Cuentas por Cobrar / Pagar. Un
  abono cuyo documento sea de otro ambiente tampoco se muestra, para no
  restar algo cuyo cargo no está en el estado de cuenta.
- En un mismo día, los cargos se listan antes que los abonos, así el saldo
  corriente no se ve negativo entre una factura y su cobro del mismo día.
- El **saldo corriente** de cada movimiento es el saldo anterior más el
  cargo o menos el abono, en orden cronológico. Si se filtra por Fecha Desde,
  el saldo de arranque ("Saldo Anterior") es la suma de todo lo ocurrido
  antes de esa fecha, para que el saldo corriente del rango visible sea
  correcto aunque no se vea el historial completo.
- Con varias entidades seleccionadas, cada una se calcula y se muestra por
  separado (no se mezclan saldos entre clientes/proveedores distintos); las
  tarjetas de KPI superiores sí suman los totales de todas las entidades
  incluidas.
- **Todos**: la lista de entidades con saldo pendiente se calcula con una
  sola consulta agregada (no una por cliente/proveedor), así que no es más
  lenta cuantos más clientes tenga la empresa. Solo entran los que tienen
  saldo mayor a cero a la fecha de corte — un cliente sin movimientos, o que
  ya pagó todo, no aparece.

## Integraciones con otros módulos

Lee de Facturación, Recibos de Venta, Notas de Crédito/Débito, Compras,
Liquidaciones de Compra, Importaciones, Ingresos, Egresos, Retenciones de
venta y de compra, y Saldos Iniciales. No escribe en ninguna de esas tablas —
es puramente de lectura.

## Errores frecuentes

- **"Seleccione al menos un Cliente/Proveedor..."**: no se puede generar,
  exportar ni enviar el reporte sin al menos una entidad seleccionada.
- **El saldo no cuadra con Cuentas por Cobrar**: revisar el rango de fechas —
  si hay Fecha Desde, el saldo mostrado parte de "Saldo Anterior" (todo lo
  anterior a esa fecha), no de cero.

## Qué comprobantes de compra cuentan como deuda

En la cartera de proveedores entra como **cargo** todo comprobante de compra que
genera una obligación con el proveedor: la factura, la **nota de venta**, la
liquidación de compra, los documentos de instituciones financieras, las
planillas de servicios básicos y los demás tipos autorizados por el SRI. Solo
quedan fuera las notas de crédito (que son abono), las notas de débito (que se
suman a la factura que modifican), las guías de remisión y los comprobantes de
retención. La columna *Detalle* indica el tipo de cada documento. Es el mismo
criterio que usa Cuentas por Pagar y que el asiento contable de la compra, así
que los tres deben coincidir.

## Historial de cambios

- **1.5** — La cartera de proveedores incluye todos los comprobantes de compra que generan deuda (notas de venta, documentos financieros, planillas, etc.), no solo la factura, y las liquidaciones de compra ya contabilizadas. Las compras anuladas o rechazadas quedan fuera. Antes esos documentos tenían asiento de cuenta por pagar pero no aparecían en el reporte.
- **1.4** — Nuevo filtro **Documento**: buscador de los documentos del
  cliente/proveedor seleccionado; al elegir uno, el estado de cuenta se
  limita a ese documento y a sus abonos (cobros/pagos, retenciones, notas).
  Los botones **PDF / Excel / Correo** pasan al encabezado de cada estado de
  cuenta, antes del nombre del cliente o proveedor, y actúan sobre esa
  entidad; el correo precarga el email de su ficha.
- **1.3** — Corrección del cálculo de saldos: los abonos (cobros/pagos,
  retenciones, notas de crédito) se atribuyen ahora al tercero del documento
  que cancelan, igual que Cuentas por Cobrar / Pagar, en vez de filtrarse
  solo por el cliente/proveedor escrito en el abono. Se incorporan los
  cobros y pagos a **saldos iniciales**, que antes nunca se restaban. Se
  respeta el ambiente actual de la empresa en cargos y abonos, se excluyen
  las líneas de egreso eliminadas y las retenciones de compra en estado
  "anulada". La opción **Todos** usa exactamente las mismas reglas que el
  estado de cuenta individual.
- **1.2** — La columna "Cargo" se renombró a "Deuda Generada" en pantalla,
  PDF, Excel y correo (el nombre interno `CARGO`/`total_cargos` no cambió).
- **1.1** — Fecha Desde/Hasta se calculan por defecto desde Mes/Año (mes
  actual preseleccionado) en vez de fijarse aparte. Se agregó la opción
  **Todos**, que incluye automáticamente a los clientes/proveedores con
  saldo pendiente sin tener que buscarlos uno por uno.
- **1.0** — Versión inicial: estado de cuenta de cliente/proveedor con
  filtros de entidad (multi-selección) y fecha/mes/año, export a PDF/Excel y
  envío por correo.
