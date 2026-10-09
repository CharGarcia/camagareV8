---
titulo: Cierre del Ejercicio
resumen: Cierra el año contable: salda las cuentas de resultados, abre el año siguiente con los saldos de balance y bloquea el año cerrado.
categoria: Contabilidad
ruta_modulo: modulos/cierre_ejercicio
tipo: modulo
visibilidad: todos
etiquetas: cierre del ejercicio, apertura duplicada, ya existe apertura, cierre manual, apertura manual, saldos iniciales duplicados, cierre anual, cierre contable, cerrar el año, cierre de año, apertura, asiento de apertura, asiento de cierre, saldos iniciales del año, pasar de un año a otro, arrastrar saldos, resultados acumulados, utilidad acumulada, perdida acumulada, utilidad del ejercicio, perdida del ejercicio, resumen de resultados, cuentas de resultados en cero, cerrar periodo, bloquear año, revertir cierre, reabrir año, nuevo año contable
version: 1.1
orden: 35
estado: activo
---

El **Cierre del Ejercicio** pasa la contabilidad de un año al siguiente. En un
solo paso registra el asiento de cierre al 31 de diciembre y el asiento de
apertura al 1 de enero del año siguiente, y bloquea el año cerrado en
**Períodos Contables**. Se puede revertir.

## Qué es y para qué sirve

Los reportes contables (Estados Financieros, Balance de Comprobación, Mayores)
suman los asientos **del rango de fechas** que se consulta. No arrastran solos
el saldo de los años anteriores. Para que el balance de un año nuevo empiece con
lo que había al cierre del anterior, hace falta un **asiento de apertura**. Este
módulo lo genera, junto con el asiento de cierre, sin armarlos a mano.

Al cerrar el año **AAAA** se registran dos asientos:

1. **Asiento de cierre (31-12-AAAA, tipo Cierre).** Deja en cero cada cuenta de
   resultados (ingresos, costos y gastos, y la *Resumen de Resultados* de los
   datos migrados) con su movimiento del año. La diferencia va a la cuenta de
   **Utilidad del Ejercicio** si hubo ganancia o a la de **Pérdida del Ejercicio**
   si hubo pérdida.
2. **Asiento de apertura (01-01-AAAA+1, tipo Apertura).** Arrastra el saldo de
   cada cuenta de activo, pasivo y patrimonio. Lo que quedó en Utilidad/Pérdida
   del Ejercicio pasa a **Utilidades Acumuladas** o **Pérdidas Acumuladas**, así
   el año nuevo arranca con esas dos cuentas en cero.

Después cierra los períodos contables de ese año. Si ningún período cubre el año
completo, crea uno llamado *Ejercicio AAAA (cierre)*.

## Cómo se ven los reportes después de cerrar

- **El año cerrado se ve igual que antes de cerrarlo.** Los reportes ignoran el
  asiento de cierre: el Estado de Resultados de AAAA no sale en cero y el balance
  sigue mostrando la utilidad del año.
- **El año siguiente arranca con la apertura.** Un balance de AAAA+1 incluye los
  saldos al 31-12-AAAA.
- **Un rango que cruza años no cuenta dos veces.** Si consulta de 2025 a 2026,
  la apertura de 2026 no se suma (ya está incluida en los movimientos de 2025).
- **Los Mayores y el Libro Diario sí muestran los dos asientos**, porque son
  movimientos reales de las cuentas.
- **Las comprobaciones con contabilidad no los cuentan.** Bancos, cartera,
  inventario, flujo de caja, consignaciones y devengo de suscripciones ya suman
  todo el histórico, y la apertura repetiría esos saldos.

## Requisitos previos

- La empresa debe estar en **ambiente de producción**: el cierre se hace sobre la
  contabilidad de producción, que es la que muestran los reportes.
- En **Configuración Contable → Cierre del Ejercicio** deben estar configuradas:
  - *Cuenta de Utilidad del Ejercicio* y/o *Cuenta de Pérdida del Ejercicio*.
  - *Cuenta de Utilidades Acumuladas* y/o *Cuenta de Pérdidas Acumuladas*.

  Si solo configura una de cada par, se usa para los dos signos. Las de
  resultados acumulados deben ser distintas de las del ejercicio.
- Revise antes que no queden **documentos sin asiento** del año (aviso de
  asientos pendientes en Estados Financieros) ni **asientos en borrador**: lo que
  no esté contabilizado no entra al cierre.

## Cómo se usa

1. Pulse **Cerrar un ejercicio**.
2. Elija el **año a cerrar**. Se sugiere el siguiente al último cerrado o, si
   nunca se cerró ninguno, el último año terminado.
3. Revise **Saldos de balance desde** (ver *Campos*). Casi siempre basta con el
   valor sugerido.
4. El sistema calcula la **vista previa**: utilidad o pérdida del año, resultados
   anteriores que se trasladan, totales de activo, pasivo y patrimonio, y las
   líneas de los dos asientos, cada uno en su pestaña.
5. Si hay errores (cuentas sin configurar, cuentas eliminadas con saldo,
   asientos descuadrados) se muestran en rojo y el botón queda desactivado.
   Corríjalos y vuelva a elegir el año.
6. Pulse **Generar cierre** y confirme.

Para ver un cierre, haga clic en su fila: muestra los dos asientos, quién lo
registró y cuándo.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Año a cerrar | Sí | Año contable que se cierra. Solo años ya terminados. |
| Saldos de balance desde | No | Desde qué fecha se suman los movimientos de activo, pasivo y patrimonio para la apertura. Si el año anterior se cerró con este módulo, es su apertura y no se puede cambiar. Si no, se sugiere la fecha de la **primera apertura del último grupo de aperturas** registradas hasta el 31-12 del año (ver *Si ya hay asientos de apertura o de cierre*). Si nunca hubo una, se suma todo el histórico. |
| Todo el histórico | No | Suma los saldos desde el primer asiento de la empresa. |
| Observaciones | No | Nota libre que queda en el registro del cierre. |

**Cuándo cambiar "Saldos desde":** si la empresa registró a mano un asiento de
apertura con los saldos de un año que también está cargado en el sistema, ponga
la fecha de esa apertura. Si no, esos saldos se sumarían dos veces. El aviso
*resultados anteriores que se trasladan* ayuda a detectarlo: normalmente es cero
o corresponde a años que nunca se cerraron.

## Si ya hay asientos de apertura o de cierre

Si la empresa ya tenía asientos de apertura o de cierre registrados a mano o
traídos de la migración:

- **Apertura del año siguiente ya registrada** (por ejemplo, una apertura manual
  al 01-01-2026 al cerrar 2025): **el cierre se bloquea** y muestra su número.
  El módulo genera su propia apertura y los saldos iniciales de ese año quedarían
  duplicados. Anule esa apertura en Asientos Contables y vuelva a calcular.
- **Aperturas del año que se cierra o de años anteriores**: son el punto de
  partida de los saldos. Si hay varias con hasta 31 días de diferencia (bancos el
  01-01, cartera el 02-01), se toma la primera. Una apertura de un año anterior no
  se junta con la más reciente.
- **Apertura a mitad del año** (la empresa empezó a usar el sistema en marzo): el
  cierre y la apertura solo toman los movimientos desde esa fecha. Lo anterior del
  año queda resumido en esa apertura y la vista previa lo avisa.
- **Cierre manual del año** (un asiento al 31-12 que llevó ingresos, costos y
  gastos al patrimonio, o el cierre del sistema anterior hacia *Resumen de
  Resultados*): el módulo lo detecta. Su asiento de cierre solo salda lo que haya
  quedado (normalmente nada) y la **utilidad o pérdida del año** que muestra
  incluye lo que movió ese asiento. La vista previa lo lista. Como ese asiento sí
  cuenta en los reportes, el Estado de Resultados de ese año sale en cero, igual
  que antes de usar el módulo.

## Permisos

- **Ver**: consulta el listado y el detalle de los cierres.
- **Crear**: genera un cierre.
- **Eliminar**: revierte un cierre.
- Sin **acceso total**, el listado muestra solo los cierres que registró el propio
  usuario. Las validaciones (año ya cerrado, orden de los cierres) son siempre de
  toda la empresa.

## Reglas de negocio

- **Un cierre vigente por año.** Para volver a cerrar un año, primero se revierte.
- **En orden.** No se puede cerrar un año anterior al último cerrado, ni revertir
  un cierre si hay otro posterior vigente: la apertura del año siguiente parte de
  los saldos de este.
- **Años sin cerrar.** Si nunca cerró un año y cierra uno posterior, los
  resultados de los anteriores pasan también a Resultados Acumulados. Aparecen
  como *Resultados anteriores* en la vista previa.
- **Bloqueo del año.** El cierre bloquea el año en Períodos Contables. Nadie puede
  registrar, modificar ni anular documentos con fecha de ese año.
- **Los asientos no se tocan desde Asientos Contables.** Los asientos de cierre y
  apertura no se pueden editar ni anular desde el Libro Diario. Para cambiarlos,
  se revierte el cierre y se genera de nuevo.
- **Cambios posteriores.** Si alguien reabre un período del año cerrado y registra
  o modifica asientos, el cierre se marca con ⚠ en el listado y el detalle lo
  explica. La apertura ya no refleja esos saldos: revierta y vuelva a generar.
- **Doble clic y reintentos.** Un mismo formulario nunca crea dos cierres.
- **Revertir** anula los dos asientos, reabre los períodos que el cierre había
  cerrado, elimina el período que había creado y deja el registro como
  *Revertido*, con el motivo, en el historial.

## Integraciones con otros módulos

- **Asientos Contables**: los asientos aparecen con origen *Cierre del ejercicio*
  y *Apertura del ejercicio* (prefijos CI- y AP-).
- **Períodos Contables**: el año queda cerrado.
- **Estados Financieros**: el año siguiente arranca con la apertura. En el
  Supercías ECP, la apertura es el *saldo del período anterior*.
- **Anexo de Dividendos**: el saldo de resultados acumulados al cierre del año
  anterior parte de la última apertura generada.
- **Configuración Contable**: de ahí salen las cuentas del ejercicio y de
  resultados acumulados.

## Errores frecuentes

- **"La empresa está en ambiente de PRUEBAS"**: el cierre se hace sobre la
  contabilidad de producción. Pase la empresa a producción.
- **"Configure la cuenta de…"**: falta una cuenta en Configuración Contable →
  Cierre del Ejercicio.
- **"Estas cuentas están eliminadas del Plan de Cuentas pero tienen saldo"**: una
  cuenta borrada todavía tiene movimientos. Reactívela o pase su saldo a otra
  cuenta con un asiento.
- **"La contabilidad hasta el 31-12-AAAA no cuadra por…"**: hay asientos
  descuadrados. Búsquelos en Auditoría Contable o en el Balance de Comprobación.
- **"Ya existe un asiento de apertura del año…"**: hay una apertura del año
  siguiente registrada a mano o migrada. Anúlela en Asientos Contables: la genera
  el cierre.
- **"Ya existe el cierre del ejercicio…"**: hay un cierre posterior. Revierta
  primero los cierres más recientes.
- **"Este asiento lo generó el Cierre del Ejercicio"** (en Asientos Contables):
  esos asientos solo se cambian revirtiendo el cierre.

## Historial de cambios

- **1.1** — Bloquea el cierre si ya hay una apertura manual del año siguiente (duplicaría los
  saldos iniciales). Detecta los cierres manuales del año y muestra el resultado real. El
  punto de partida es la primera apertura del último grupo, incluida una a mitad de año.
- **1.0** — Versión inicial: cierre y apertura automáticos, bloqueo del año,
  reversión y aviso de cambios posteriores al cierre.
