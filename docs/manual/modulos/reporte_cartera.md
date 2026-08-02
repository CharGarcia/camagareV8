---
titulo: Reporte de Cartera
resumen: Estado de cuenta cronológico de un cliente o proveedor, con saldo corriendo, exportable a PDF/Excel y por correo.
categoria: Reportes
ruta_modulo: modulos/reporte_cartera
tipo: modulo
visibilidad: todos
etiquetas: cartera, estado de cuenta, kardex de cliente, kardex de proveedor, saldo, cuentas por cobrar, cuentas por pagar, historial de pagos, historial de cobros, deuda, adeudado
version: 1.2
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
4. Hacer clic en **Generar Estado de Cuenta**. Aparece una tarjeta por cada
   entidad incluida, con su tabla de movimientos y saldo final.
5. Exportar a **PDF** o **Excel** (un PDF por entidad, con salto de página
   entre cada uno; el Excel trae una sección por entidad), o usar
   **Correo** para enviarlo directamente por email con los mismos filtros
   aplicados.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|----------------|
| Tipo | Sí | Cliente o Proveedor. Cambiarlo limpia la selección de entidades (son catálogos distintos). |
| Cliente(s) / Proveedor(es) | Sí, salvo que se use "Todos" | Uno o varios, por buscador predictivo. |
| Todos | No | Reemplaza a la selección manual: incluye a todo cliente/proveedor con saldo pendiente (mayor a cero) a la fecha de corte (Fecha Hasta, o la fecha actual si no se indica). Mientras está activo, el buscador se deshabilita. |
| Mes / Año | No | Atajo que llena Fecha Desde/Hasta con el mes o año elegido. |
| Fecha Desde / Fecha Hasta | No | Rango del estado de cuenta. Vacío en ambos = todo el historial. Si se llena Fecha Desde, el saldo antes de esa fecha se muestra como "Saldo Anterior". |

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

## Historial de cambios

- **1.2** — La columna "Cargo" se renombró a "Deuda Generada" en pantalla,
  PDF, Excel y correo (el nombre interno `CARGO`/`total_cargos` no cambió).
- **1.1** — Fecha Desde/Hasta se calculan por defecto desde Mes/Año (mes
  actual preseleccionado) en vez de fijarse aparte. Se agregó la opción
  **Todos**, que incluye automáticamente a los clientes/proveedores con
  saldo pendiente sin tener que buscarlos uno por uno.
- **1.0** — Versión inicial: estado de cuenta de cliente/proveedor con
  filtros de entidad (multi-selección) y fecha/mes/año, export a PDF/Excel y
  envío por correo.
