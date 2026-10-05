---
titulo: Reporte de Ingresos Diferidos
resumen: Saldo de ingresos cobrados por adelantado que aún no se devengan (y del servicio de mes caído por facturar) al cierre de un mes, con su cuadre contra el mayor.
categoria: Reportes
ruta_modulo: modulos/reporte_ingresos_diferidos
tipo: modulo
visibilidad: todos
etiquetas: ingresos diferidos, ingreso diferido, ingresos anticipados, cobrado por adelantado, pasivo del contrato, devengado, devengo, niif 15, seccion 23, suscripciones, por facturar, ingresos devengados por facturar, mes caido, corriente, no corriente, conciliacion, cuadre con el mayor, saldo diferido
version: 1.0
orden: 0
estado: activo
---

Muestra cuánto de lo facturado por adelantado en las [suscripciones](modulos/suscripciones)
todavía no se ha ganado (ingreso diferido) y cuánto servicio de mes caído ya se prestó
pero aún no se factura, al cierre del mes que usted elija. Compara esos saldos con la
contabilidad. Es solo de consulta: no registra nada.

## Qué es y para qué sirve

Las suscripciones que reconocen el ingreso **durante el período** (NIIF 15) dejan en el
pasivo *Ingresos diferidos* la parte de cada factura que corresponde a meses futuros, y
el sistema la pasa al ingreso cada mes de forma automática. Este reporte responde:

- ¿Cuánto debemos todavía en servicio a los clientes? Separado en **corriente** (se
  devenga en los próximos 12 meses) y **no corriente** (después), como se presenta en el
  balance.
- ¿Cuánto servicio de mes caído ya prestamos y falta facturar?
- ¿Cuadra eso con las cuentas contables?

## Requisitos previos

- Suscripciones con *Reconocimiento del ingreso = Durante el período*.
- Las cuentas de *Ingresos diferidos* y *Ingresos devengados por facturar* en
  [Configuración Contable](modulos/configuracion-contable) → **Suscripciones - Devengo**
  (sin ellas no hay con qué comparar el mayor).

## Cómo se usa

1. Elija el mes en **Saldos al cierre de** (por defecto, el mes anterior). Sirve para
   meses pasados: los saldos se reconstruyen con las fechas de los asientos.
2. Si quiere, elija un **Cliente** (Backspace o Suprimir quita la selección).
3. Pulse **Mostrar**.
4. Haga clic en una fila para ver la factura o el recibo.

## Qué muestra

- **Indicadores**: diferido corriente, diferido no corriente, devengado por facturar,
  número de documentos y de clientes.
- **Conciliación con el mayor**: para cada cuenta, el saldo según el cronograma, el saldo
  según el mayor y la diferencia (en rojo si no cuadra). Con un cliente elegido, o si
  usted solo ve sus propias suscripciones, el mayor no se compara: es de toda la empresa.
- **Detalle por documento**: cliente, suscripción, factura o recibo, fecha, último mes por
  devengar y los tres saldos. Las provisiones de mes caído aún sin factura salen como
  *Sin facturar (mes caído)*. Tiene filtro de texto, orden por columna (clic en el
  encabezado), páginas de 50 filas y columnas que se pueden ocultar.
- **PDF** y **Excel** (con una hoja de conciliación) del filtro vigente.

## Permisos

| Permiso | Qué permite |
|---------|-------------|
| Ver | Abrir el reporte, exportar a PDF y Excel. |
| Acceso total | Ver las suscripciones de toda la empresa (sin él, solo las que usted registró). |

## Reglas de negocio

- El saldo de cada mes se reconstruye con las fechas de los asientos: lo devengado o
  devuelto con nota de crédito **después** del corte todavía cuenta como diferido a esa
  fecha.
- Las facturas o recibos anulados o eliminados no cuentan (su asiento tampoco está en el
  mayor).
- Lo diferido de documentos que aún no tienen asiento (borradores) no cuenta: todavía no
  está en la contabilidad.

## Integraciones con otros módulos

- [Suscripciones](modulos/suscripciones): de ahí salen el cronograma, el devengo
  automático y la apertura.
- [Auditoría Contable](modulos/auditoria-contable): revisa el mismo cuadre y lo reporta
  como hallazgo *Devengo de suscripción*.

## Errores frecuentes

- **La conciliación no cuadra**: hay asientos hechos a mano en la cuenta de ingresos
  diferidos (o por facturar), una factura de suscripción cuyo asiento se generó antes que
  su cronograma (Auditoría Contable la marca y permite regenerarla) o se cambió la cuenta
  a mitad de camino.
- **"Según mayor" muestra —**: falta configurar la cuenta, hay un cliente elegido o usted
  solo ve sus propias suscripciones.

## Historial de cambios

- **1.0** — Versión inicial (antes era un botón dentro de Suscripciones).
