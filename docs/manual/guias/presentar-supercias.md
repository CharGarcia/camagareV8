---
titulo: Presentar los estados financieros a Supercías
resumen: Recorrido completo para generar los cuatro archivos de la Superintendencia de Compañías (ESF, ERI, ECP, EFE) desde el sistema, y cómo usar la revisión automática para dejar la empresa lista.
categoria: Contabilidad
tipo: guia
visibilidad: todos
etiquetas: supercias, superintendencia de compañias, balances anuales, presentar estados financieros, txt supercias, esf, eri, ecp, efe, casilleros, mapeo de cuentas, revisar supercias, que falta configurar, codigos de cuentas, estado de flujos de efectivo, estado de cambios en el patrimonio
version: 1.0
orden: 40
estado: activo
---

Cada año la Superintendencia de Compañías recibe cuatro estados financieros
bajo NIIF como archivos de texto: Situación Financiera (ESF), Resultado
Integral (ERI), Cambios en el Patrimonio (ECP) y Flujos de Efectivo (EFE). El
sistema los genera desde **Estados Financieros** a partir de la contabilidad,
sin que haya que llenar nada a mano, siempre que cada cuenta del plan tenga su
casillero. Esta guía explica el recorrido y cómo el propio sistema le dice qué
falta.

## Qué necesita cada cuenta

Los casilleros se asignan en la ficha de la cuenta (Plan de Cuentas, o pulsando
el código de la cuenta en el balance), en la sección *Configurar códigos
entidades de control*:

| Tipo de cuenta | Campo que necesita |
|---|---|
| Activo, pasivo y patrimonio (empiezan por 1, 2, 3) | **Supercias ESF** |
| Ingresos, costos y gastos (empiezan por 4, 5, 6) | **Supercias ERI** |
| Patrimonio, además | **Supercias ECP Columna** (se deduce sola del ESF al guardar) |
| Caja y bancos | ESF 1010101, 1010102 o 1010103; con eso el EFE sabe qué es efectivo |

Los casilleros de totales (activo, pasivo, ganancia antes de impuestos, etc.)
no se asignan a cuentas: los calcula la estructura global con fórmulas, que
administra el superadministrador en `/config/supercias`.

## El recorrido

1. **Genere los asientos pendientes** al entrar a Estados Financieros, si el
   sistema lo pregunta. Sin eso los archivos saldrán incompletos.
2. Ponga el **rango del ejercicio** (por ejemplo, del 1 de enero al 31 de
   diciembre) y genere el Estado de Situación Financiera.
3. Pulse **Revisar Supercias**. Aparece la lista de hallazgos, en tres colores:
   - **Rojo, por corregir**: hay valores que no saldrán en los archivos.
     Cuentas con movimiento sin casillero, casilleros que no existen, cuentas
     mapeadas a un casillero de totales, patrimonio sin columna ECP, caja y
     bancos sin identificar.
   - **Amarillo, advertencia**: el archivo sale, pero algo no cuadra o falta
     una configuración: cuenta de cierre del ejercicio, saldos iniciales sin
     tipo *apertura*, impuesto o participación registrados solo como pasivo,
     fórmulas de totales pendientes, o diferencias en los cuadres.
   - **Verde, en orden**.
4. En cada hallazgo de cuentas, el sistema **sugiere el casillero** a partir
   del nombre de la cuenta. Con permiso de actualizar en Plan de Cuentas puede
   pulsar **Aplicar** en cada fila, o **Aplicar todas las sugerencias** del
   hallazgo. La sugerencia nunca se guarda sola. Si no está de acuerdo, abra
   la ficha con el lápiz y elija otro casillero. Sin ese permiso, entregue la
   lista a quien administre el plan.
5. Pulse **Volver a revisar** hasta que no quede nada en rojo. Los cuadres
   (balance, ECP y EFE) deben quedar en verde; si no, **Ver ECP** y **Ver EFE**
   muestran qué cuentas o asientos producen la diferencia.
6. Descargue los cuatro archivos con los botones **Supercias ESF, ERI, ECP y
   EFE**. Al pulsar cualquiera, el sistema hace la revisión de nuevo; si hay
   algo en rojo la muestra y usted decide si descarga de todos modos.
7. Cargue los archivos en el portal de Supercías.

## Casos que la revisión detecta y cómo se resuelven

- **Cuenta con movimiento sin casillero**: acepte la sugerencia o asigne el
  casillero en la ficha.
- **Casillero que no existe**: suele ser un código escrito a mano. Reemplácelo
  por el sugerido.
- **Cuenta mapeada a un casillero de totales**: por ejemplo, reservas mapeadas
  a 304 en vez de 30401. El total tiene fórmula y no toma valores de cuentas;
  hay que usar el casillero de detalle.
- **Caja y bancos sin identificar**: sin ESF 10101 el EFE sale en cero.
- **Cuenta de cierre del ejercicio no configurada**: el resultado no llega al
  ESF ni al ECP. Se configura en Configuración Contable, Cierre del Ejercicio.
- **Saldos iniciales sin tipo apertura**: el ECP los toma como cambios del año
  y el EFE como movimiento de efectivo. Cambie el tipo del asiento a
  *apertura*.
- **Impuesto o participación solo como pasivo**: típico de un cierre migrado,
  que reparte el resultado en impuesto por pagar, reserva y utilidad sin pasar
  el impuesto por el gasto. Registre al último día del período un asiento con
  Debe en la cuenta de gasto (ERI 603 o 601) y Haber en la cuenta pivote de
  cierre. El balance no cambia y el ERI y el EFE quedan completos.
- **Fórmulas de totales pendientes**: es configuración global. Pida al
  administrador que las complete en `/config/supercias`.

## Lo que el sistema no puede decidir solo

- En el **ECP**, si un movimiento de patrimonio es un dividendo, una
  capitalización de aportes o una corrección de errores. Se resuelve con
  cuentas separadas y su *Fila de cambios*, o retocando en el portal.
- En el **EFE**, los ajustes sin efectivo que la contabilidad no identifica
  (deterioro, provisiones, diferencias de cambio). Se completan con fórmula en
  la estructura.

## Historial de cambios

- **1.0** — Versión inicial, junto con la revisión automática *Revisar
  Supercias* de Estados Financieros.
