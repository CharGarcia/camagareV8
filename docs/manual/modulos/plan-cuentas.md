---
titulo: Plan de cuentas
resumen: Catálogo de cuentas contables de la empresa, organizado por niveles.
categoria: Contabilidad
ruta_modulo: modulos/plan-cuentas
tipo: modulo
visibilidad: todos
etiquetas: plan de cuentas, cuentas contables, catalogo de cuentas, codigo de cuenta, nivel, mayor, auxiliar, plan modelo, cargar plan modelo, configuracion contable automatica, tipos de asiento, map asiento, iva por tarifa
version: 1.1
orden: 10
estado: activo
---

El **plan de cuentas** es el catálogo contable de la empresa. Todo asiento se
escribe sobre estas cuentas, así que es lo primero que hay que tener en orden
antes de contabilizar nada.

## Estructura por niveles

Las cuentas se organizan en niveles, de lo general a lo específico: los primeros
niveles son grupos (activo, pasivo, patrimonio, ingresos, gastos) y los últimos
son las cuentas de movimiento donde realmente se registra.

El nivel se deduce del código, contando los segmentos separados por punto:

| Nivel | Formato | Ejemplo | Qué es |
|---|---|---|---|
| 1 | `N` | `1` | Grupo |
| 2 | `N.N` | `1.1` | Subgrupo |
| 3 | `N.N.N` | `1.1.1` | Cuenta mayor |
| 4 | `N.N.N.NN` | `1.1.1.01` | Subcuenta |
| 5 | `N.N.N.NN.NNN` | `1.1.1.01.001` | Cuenta de movimiento |

**Las cuentas de nivel 1 a 4 deben escribirse en MAYÚSCULAS.** El sistema lo
valida al guardar. Es una convención para que los grupos se distingan de un
vistazo de las cuentas de detalle.

Los códigos del SRI y de la Superintendencia de Compañías solo se guardan en las
cuentas de **nivel 5**: son las únicas que llevan saldo.

## Cómo se registra una cuenta

1. Pulse **Nuevo**.
2. Escriba el **código** siguiendo la estructura de su plan.
3. Escriba el **nombre** (en mayúsculas si es de nivel 1 a 4).
4. Indique el **nivel**.
5. Guarde.

Los tres campos son obligatorios.

## Cargar el plan modelo

Si la empresa todavía no tiene ninguna cuenta, el botón **Cargar Plan Modelo**
crea de una sola vez un plan contable comercial completo y, además, **deja
configurados los tipos de asiento** para que los documentos se contabilicen
solos desde el primer día.

Solo funciona como estructura inicial: si la empresa ya tiene cuentas cargadas,
el botón se rechaza para no mezclar dos planes distintos.

### Qué configura automáticamente

Al cargar el plan modelo se asignan las cuentas de estos grupos en
**Configuración Contable**:

| Grupo | Qué se configura |
|---|---|
| Facturas de venta | Cuenta por cobrar, ventas, costo, inventario, descuento, ICE, propina y ajuste por redondeo |
| Recibos de venta | Las mismas cuentas, en su propio catálogo |
| Compras | Cuenta por pagar, gasto, inventario, descuento, ICE, propina y ajuste por redondeo |
| Nómina | Los siete gastos (sueldos, décimos, vacaciones, fondos de reserva, aporte patronal, desahucio) y los siete pasivos por pagar, más anticipos a empleados |
| IVA por tarifa | IVA en ventas y en compras, tarifas 15% y 5%, para facturas y recibos |
| Cierre del ejercicio | Cuenta de Utilidad y cuenta de Pérdida del ejercicio |
| Formas de cobro y pago | Efectivo (Caja General) y las dos de Anticipos |
| Opciones de ingreso/egreso | Anticipos de clientes y de proveedores, más los conceptos SRI e IESS |

Las formas y opciones nacen sin cuenta al crear la empresa, porque en ese momento
todavía no existe ningún plan. Esta es la primera oportunidad de asignárselas.
**Tarjeta, Payphone y Nuvei quedan a propósito sin cuenta** (ver
[Formas de cobro y pago](formas-cobros-pagos.md)). Si los conceptos **SRI** o
**IESS** no existen —porque la empresa se creó antes de que se sembraran—, se
crean aquí como conceptos de egreso con su cuenta.

Cinco opciones **no** reciben cuenta y es intencional: Facturas de compras,
Liquidaciones, Facturas de venta, Recibos de venta y Nómina heredan la cuenta de
su propio módulo, y el sistema rechaza asignarles otra por separado.

**Nunca se pisa lo que ya estaba configurado.** Si un tipo de asiento ya tiene
cuenta asignada, se respeta y el mensaje final le indica cuántas se crearon y
cuántas se respetaron.

Los módulos de importaciones, consignaciones, facturas de reembolso, activos
fijos y las declaraciones **no** se configuran solos: sus cuentas se asignan a
mano en Configuración Contable.

### Por qué el IVA se configura aparte

El IVA no se asigna a un "tipo de asiento" como las demás cuentas, porque
necesita **una cuenta distinta por cada tarifa**. Por eso vive en su propia
sección de Configuración Contable, con una fila por tarifa (15%, 5%) y por
dirección (ventas, compras, recibos). Además admite excepciones por proveedor,
ítem, categoría o marca, que mandan sobre la configuración general.

## Importar su propio plan desde Excel

Si viene de otro sistema, conviene importar su plan antes que capturarlo a mano.

1. Pulse **Descargar ejemplo** para obtener el archivo con el formato correcto.
2. Reemplace las filas con su propio plan, respetando el orden de las columnas.
3. Pulse **Importar Excel**.

Las columnas que se guardan al importar son, en este orden: **Código, Nombre,
Código SRI, Supercías ESF, Supercías ERI, Supercías ECP Cod. y Supercías ECP
Sub.** Las columnas **Map Asiento** y **Map IVA** del archivo de ejemplo son
informativas: muestran cómo está armado el plan modelo, pero **no se guardan al
importar**.

La importación **inserta las cuentas nuevas y omite las que ya existen**: nunca
sobreescribe el nombre de una cuenta ya creada. Y a diferencia del plan modelo,
**la importación no configura los tipos de asiento**: eso se hace en
Configuración Contable.

## Antes de empezar

Vale la pena dedicar tiempo a esto al inicio. Cambiar el plan de cuentas cuando
ya hay asientos registrados obliga a revisar la configuración contable de cada
módulo y, en el peor caso, a reclasificar movimientos.

## Errores frecuentes

- **"Las cuentas de nivel 1 al 4 deben estar en MAYÚSCULAS"**: escriba el nombre
  en mayúsculas o baje el nivel de la cuenta.
- **La cuenta no aparece al configurar un asiento**: revise su nivel; en las
  configuraciones se eligen normalmente cuentas de movimiento, no grupos.
- **"El plan de cuentas ya tiene cuentas cargadas"**: el plan modelo solo se
  puede usar en una empresa sin cuentas. Si quiere partir de él, elimine primero
  el plan actual o importe las cuentas que le falten desde Excel.
- **Un documento no genera asiento**: revise en Configuración Contable que el
  tipo de asiento correspondiente tenga cuenta asignada. Si la empresa se cargó
  con el plan modelo antes de agosto de 2026, la configuración automática no
  llegó a guardarse y hay que asignarla a mano.

## Historial de cambios

- **1.1** — El botón *Cargar Plan Modelo* ahora configura automáticamente los
  tipos de asiento de ventas, recibos de venta, compras y nómina, y el IVA por
  tarifa, sin sobrescribir lo ya configurado. Se reemplazó el plan modelo por el
  plan contable definitivo de la empresa (148 cuentas), al que se sumaron las
  cuentas necesarias para cubrir esas configuraciones: descuentos y propinas de
  ventas y compras, ICE por pagar e ICE en compras, compras y gastos generales,
  IVA en ventas 5%, gasto de desahucio y anticipos a empleados. Los anticipos a
  proveedores y de clientes pasaron a grupos propios. La carga del plan modelo
  también asigna la cuenta de las formas de cobro/pago y de las opciones de
  ingreso/egreso que llevan cuenta propia, y crea los conceptos SRI e IESS si
  faltan. También deja configuradas las cuentas de Utilidad y Pérdida del
  cierre del ejercicio, que el Balance usa para mostrar el resultado.
- **1.0** — Versión inicial.
