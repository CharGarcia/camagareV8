---
titulo: Declaración de Impuesto a la Renta
resumen: Reporte anual que arma la declaración de renta (formulario 101 o 102) con las ventas, las compras y la contabilidad del ejercicio.
categoria: Impuestos
ruta_modulo: modulos/declaracion-renta
tipo: modulo
visibilidad: todos
etiquetas: impuesto a la renta, renta, declaracion de renta, formulario 101, formulario 102, sociedades, persona natural, obligado a llevar contabilidad, no obligado, ingresos, gastos, gastos deducibles, gastos personales, rebaja gastos personales, cargas familiares, canasta basica, tabla progresiva, tramos, fraccion basica, impuesto causado, retenciones que me hicieron, credito tributario, anticipo, saldo a favor, impuesto a pagar, conciliacion tributaria, participacion trabajadores, 15%, utilidad gravable, tarifa 25%, casillero, codigo sri, renta sri, xml sri, pdf, excel, deducible, declaracion de iva, gasto personal, notas de credito, liquidaciones de compra, anual, ejercicio fiscal, marzo, abril
version: 1.0
orden: 12
estado: activo
---

Este módulo es un **reporte anual** que prepara la declaración de Impuesto a la
Renta de la empresa activa con lo que ya está registrado en el sistema: ventas,
compras, liquidaciones de compra, retenciones que le hicieron y, cuando la
empresa lleva contabilidad, los saldos del plan de cuentas. Según el tipo de
contribuyente arma el **formulario 102** (personas naturales) o el **formulario
101** (sociedades). No guarda nada ni envía nada al SRI: es un apoyo para llenar
la declaración en el portal.

## Qué es y para qué sirve

Cada año (marzo para personas naturales, abril para sociedades) hay que declarar
el impuesto a la renta del ejercicio anterior. Este módulo responde en una sola
pantalla las preguntas que hacen falta para llenar el formulario: cuánto vendí,
cuánto gasté en el negocio, cuánto gasté en gastos personales, cuánto me
retuvieron y, con eso, **cuánto impuesto debo pagar o cuánto saldo queda a mi
favor**.

El tipo de formulario sale de la ficha de la empresa (campo *Tipo* del catálogo
de tipo de empresa y el campo *Obligado a llevar contabilidad*):

| Tipo de empresa | Formulario | Con qué se calcula |
|-----------------|------------|--------------------|
| Persona natural **no** obligada a llevar contabilidad | 102 | Documentos (ventas, compras, retenciones) |
| Persona natural **obligada** a llevar contabilidad | 102 | Documentos o contabilidad (se elige en pantalla), más los casilleros contables |
| Sociedad, contribuyente especial, sector público | 101 | Contabilidad (casilleros por código SRI) y conciliación tributaria |

## Requisitos previos

- La empresa debe tener bien configurado el **tipo de contribuyente** en su
  ficha. Si una persona natural aparece como sociedad, el módulo mostrará la
  conciliación de sociedades y no la tabla progresiva.
- **Compras marcadas**: cada compra lleva el campo *Deducible* con dos opciones.
  *Deducible para declaración de IVA* son los costos y gastos del negocio;
  *Gasto personal* son las compras que se usan para la rebaja de gastos
  personales (salud, educación, vivienda, alimentación, vestimenta, turismo).
  Las compras sin marca no entran en ningún bloque y el módulo lo avisa.
- Para personas naturales, la **tabla progresiva** del año y la **canasta
  básica** deben estar cargadas en *Configuración → Impuesto a la renta:
  tramos* (son las mismas que usa Nómina para la retención de empleados). Sin la
  tabla, el impuesto causado sale en 0 y aparece un aviso.
- Para sociedades y obligados a llevar contabilidad, cada cuenta de último
  nivel del **Plan de cuentas** debe tener su *Código SRI* (el número de
  casillero del formulario). Las cuentas con saldo y sin código se listan
  aparte para corregirlas.
- Permiso de **ver** en el módulo.

## Cómo se usa

1. Elija el **Ejercicio** (año). Por defecto se abre el año más reciente con
   movimientos.
2. Personas naturales: indique las **cargas familiares** (0 a 5 o más) y si
   aplica el **caso especial** (discapacidad o enfermedad catastrófica). Con eso
   se calcula el tope de gastos personales (canasta básica × número de
   canastas).
3. Obligados a llevar contabilidad: elija la **base de cálculo**. *Documentos*
   usa las ventas y compras registradas; *Contabilidad* usa los saldos de las
   cuentas de ingreso, costo y gasto del ejercicio.
4. Sociedades: revise el porcentaje de **participación a trabajadores** (15 %
   por defecto) y la **tarifa** del impuesto (25 % por defecto).
5. Presione **Mostrar**. Se llenan los indicadores y las pestañas.
6. En la pestaña **Liquidación del impuesto** escriba los valores que el sistema
   no conoce en los campos amarillos (anticipo pagado, crédito tributario de
   años anteriores, gastos no deducibles, otros ingresos, etc.) y vuelva a
   presionar **Mostrar** (o Enter dentro del campo) para recalcular.
7. Descargue el **PDF** o el **Excel** de apoyo. Si la empresa lleva
   contabilidad, el botón **Renta SRI** descarga el XML de casilleros para
   cargarlo en el portal del SRI (mismo formato que el botón del mismo nombre en
   Estados Financieros, más los casilleros de la conciliación).

## Pestañas

- **Liquidación del impuesto**: el cálculo paso a paso, con el signo de cada
  línea (+ suma, − resta, = subtotal) y el número de casillero cuando el
  formulario lo tiene fijo (101 y 102 de obligados). En el 102 de no obligados
  el SRI reorganiza los casilleros cada año, por eso no se muestran números.
- **Resumen de documentos**: cuántos documentos hay en cada bloque (facturas,
  notas de crédito, notas de débito, liquidaciones, retenciones) con su base sin
  IVA y su total con IVA.
- **Casilleros (contabilidad)**: cada casillero del formulario con las cuentas
  del plan que lo alimentan y su valor. Al final, las cuentas con saldo que no
  tienen casillero.
- **Detalle de documentos**: la lista documento a documento de cada bloque,
  para cuadrar contra el módulo de origen.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Ejercicio | Sí | Año fiscal que se declara (enero a diciembre) |
| Cargas familiares | No | Número de cargas para el tope de gastos personales (0 a 5 o más) |
| Caso especial | No | Discapacidad o enfermedad catastrófica: tope de gastos personales ampliado |
| Base de cálculo | No | Solo obligados a llevar contabilidad: documentos o contabilidad |
| Part. trabajadores % | No | Porcentaje de participación a trabajadores (15 % por ley) |
| Tarifa IR % | No | Tarifa del impuesto de sociedades (25 % general) |
| Otros ingresos gravados | No | Ingresos del año que no están registrados en el sistema |
| Otras deducciones | No | Gastos deducibles que no están registrados en el sistema |
| Gastos no deducibles | No | Gastos contabilizados que la ley no permite deducir |
| Otras rentas exentas | No | Ingresos contabilizados que no pagan impuesto (dividendos exentos, etc.) |
| Deducciones adicionales | No | Deducciones extra que concede la ley (nuevos empleos, discapacidad, etc.) |
| Amortización de pérdidas | No | Pérdidas tributarias de años anteriores que se amortizan este año |
| Anticipo pagado | No | Anticipo de impuesto a la renta pagado en el ejercicio |
| Crédito tributario de años anteriores | No | Saldo a favor arrastrado de declaraciones anteriores |
| Otros créditos y rebajas | No | Cualquier otro crédito, exoneración o rebaja aplicable |

## Permisos

Solo se necesita el permiso de **ver**. El reporte siempre muestra los datos de
**toda la empresa** (la declaración es de la empresa, no de un usuario), sin
importar el permiso de acceso total. No hay crear, modificar ni eliminar porque
el módulo no guarda nada.

## Reglas de negocio

**Período.** Siempre el año completo, del 1 de enero al 31 de diciembre, con los
documentos del ambiente activo de la empresa (producción o pruebas).

**Ingresos (documentos).** Facturas de venta autorizadas, menos notas de crédito
emitidas, más notas de débito emitidas. Se toma la base sin impuestos (ya neta
de descuentos). Las facturas en borrador o anuladas no cuentan.

**Costos y gastos del negocio (documentos).** Compras con *Deducible =
Declaración de IVA* más liquidaciones de compra autorizadas, menos notas de
crédito recibidas, más notas de débito recibidas (siempre en base sin IVA).

**Gastos personales.** Compras con *Deducible = Gasto personal*, menos sus notas
de crédito. Se toman **con IVA** (el valor total del comprobante, que es el que
se reporta al SRI). La rebaja es el porcentaje configurado (18 %) del **menor**
entre esos gastos y el tope (canasta básica × número de canastas según cargas
familiares). La rebaja nunca supera el impuesto causado.

**Gastos personales por rubro.** Debajo del total de gastos personales, la
liquidación y el resumen de documentos los desglosan por los rubros del SRI
(**vivienda, salud, educación, alimentación, vestimenta y turismo**), que es lo
que pide el Anexo de Gastos Personales. El Excel trae además una hoja **Gastos
personales** con los seis rubros (aunque estén en cero), el total, el tope y la
rebaja aplicada, y la hoja *Detalle documentos* tiene una columna **Rubro** para
cada compra de gasto personal; en pantalla, la pestaña *Detalle de documentos*
muestra el rubro como etiqueta (en rojo cuando falta). El rubro se elige en cada compra (campo
*Rubro gasto personal*, visible cuando Deducible = Gasto personal). Las compras
sin rubro aparecen como **Sin rubro (sin clasificar)** y el módulo avisa cuántas
son; para encontrarlas, en Compras filtre con `rubro:sin_rubro`. El desglose es
informativo: la rebaja se calcula sobre el total, sin tope por rubro.

**Impuesto causado de personas naturales.** Base imponible = ingresos gravados −
costos y gastos deducibles (si da negativo, la base es 0 y se informa la
pérdida). Sobre la base se aplica la tabla progresiva del año: impuesto de la
fracción básica + (base − fracción básica) × porcentaje del excedente. Si la base
supera el último tramo cargado, aparece un aviso y el impuesto queda en 0 hasta
completar la tabla.

**Obligados a llevar contabilidad con base «Contabilidad».** Ingresos = cuentas
de ingreso; costos y gastos = cuentas de costo y gasto menos los gastos no
deducibles; a la utilidad se le resta la participación a trabajadores antes de
aplicar la tabla.

**Sociedades (conciliación tributaria).** Utilidad del ejercicio = ingresos −
costos − gastos (asientos contabilizados). Menos participación a trabajadores,
menos rentas exentas, más gastos no deducibles, menos deducciones adicionales,
menos amortización de pérdidas = utilidad gravable. Impuesto causado = utilidad
gravable × tarifa. Si la utilidad gravable es negativa, se muestra como pérdida
sujeta a amortización y el impuesto es 0.

**Créditos.** Al impuesto causado (después de la rebaja en personas naturales)
se le restan las retenciones de renta que le hicieron en el año (comprobantes
de retención recibidos, solo impuesto renta), el anticipo pagado, el crédito de
años anteriores y otros créditos. Si el resultado es positivo es **impuesto a
pagar**; si es negativo, **saldo a favor**.

**Casilleros contables.** Se agrupan solo las cuentas de último nivel (hojas)
por su código SRI, para no sumar dos veces el saldo de una cuenta padre. Activo,
pasivo y patrimonio van con el saldo del ejercicio (igual que Estados
Financieros, que parte del asiento de apertura del año).

**Varios establecimientos del mismo RUC.** La declaración de renta se presenta
por RUC, no por establecimiento. Si la empresa activa pertenece a un RUC con
varios establecimientos registrados como empresas, el módulo **consolida** los
que el usuario tiene asignados (el superadministrador ve todos): suma ventas,
compras, liquidaciones y retenciones de todos, y en la contabilidad suma los
casilleros de cada plan de cuentas. Las cuentas marcadas en *Consolidación de
grupos* con modo **ÚNICA** (capital, resultados acumulados…) se cuentan una
sola vez, desde su establecimiento fuente. En la cabecera aparece el aviso
"Consolidado: N establecimientos", el detalle de documentos muestra de qué
establecimiento viene cada uno y, si al usuario le falta acceso a algún
establecimiento del RUC, se avisa que la declaración está incompleta.

**RIMPE.** Si la empresa está en régimen RIMPE, el módulo calcula igual con la
tabla y tarifa del régimen general y lo avisa: el impuesto real debe revisarse
con la tabla RIMPE vigente.

## Integraciones con otros módulos

- **Compras** (campo *Deducible*), **Liquidaciones de compra**, **Facturas de
  venta**, **Notas de crédito** y **Notas de débito**: de ahí salen los
  documentos.
- **Retenciones de venta** (las que le hacen los clientes): crédito tributario.
- **Estados Financieros**: los casilleros contables usan el mismo cálculo y la
  misma agrupación por código SRI que su botón *Renta SRI*.
- **Plan de cuentas**: campo *Código SRI* de cada cuenta.
- **Configuración → Impuesto a la renta: tramos**: tabla progresiva, canasta
  básica, porcentaje de rebaja y factores por cargas (compartidos con Nómina).

## Errores frecuentes

- **El impuesto causado sale en 0 aunque hay base imponible**: no está cargada
  la tabla progresiva del año, o la base supera el último tramo cargado. Cargue
  o complete la tabla en Configuración → Impuesto a la renta: tramos.
- **Aparece «compras sin la marca Deducible»**: compras antiguas o migradas que
  no tienen *Declaración de IVA* ni *Gasto personal*. Ábralas en Compras y
  marque la opción correcta; hasta entonces no suman en ningún bloque.
- **Faltan gastos del negocio**: revise que las compras estén marcadas como
  *Deducible para declaración de IVA* y no como *Gasto personal*.
- **La rebaja por gastos personales es 0**: no hay compras marcadas *Gasto
  personal* en el año, o el impuesto causado es 0 (la rebaja no puede superar
  el impuesto).
- **Cuentas sin casillero SRI**: en Plan de cuentas asigne el *Código SRI* a
  cada cuenta de último nivel con saldo.
- **Me calcula como sociedad siendo persona natural** (o al revés): corrija el
  tipo de contribuyente en la ficha de la empresa.
- **No sale el botón Renta SRI**: el XML de casilleros solo aplica a empresas
  con contabilidad (sociedades y personas naturales obligadas).

## Historial de cambios

- **1.1** — Consolidación por RUC (varios establecimientos) y desglose de gastos
  personales por rubro (vivienda, salud, educación, alimentación, vestimenta,
  turismo) tomado del nuevo campo *Rubro gasto personal* de Compras.
- **1.0** — Versión inicial: formulario 102 (personas naturales, con y sin
  contabilidad) y formulario 101 (sociedades), resumen de documentos,
  liquidación con ajustes manuales, casilleros contables, detalle de documentos,
  PDF, Excel y XML de casilleros.
