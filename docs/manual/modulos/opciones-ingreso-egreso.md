---
titulo: Opciones de ingreso y egreso
resumen: Los conceptos por los que entra o sale dinero, y la cuenta contable con la que se registran.
categoria: Tesorería
ruta_modulo: modulos/opciones_ingreso_egreso
tipo: modulo
visibilidad: todos
etiquetas: opciones de ingreso, opciones de egreso, conceptos, motivos, concepto del ingreso, concepto del egreso, cuenta contable del concepto, anticipos, prestamo empleado, relacionado con modulos
version: 1.0
orden: 71
estado: activo
---

Las **opciones de ingreso y egreso** son los conceptos por los que entra o sale
el dinero: pago del SRI, aporte al IESS, arriendo, anticipo de un cliente, cobro
de una factura. Cada ingreso y cada egreso elige uno, y de él sale la
contrapartida contable del movimiento.

Van de la mano de [Formas de cobro y pago](formas-cobros-pagos.md): la forma dice
**por dónde** pasó el dinero (caja, banco, tarjeta) y el concepto dice **por qué**.

## Qué es y para qué sirve

Cada concepto define tres cosas:

- Si aparece al registrar un **ingreso** o un **egreso** (una sola de las dos).
- Con qué **cuenta contable** se registra la contrapartida del movimiento.
- Si está **relacionado con un módulo** del sistema, lo que activa lógica propia
  en las pantallas (por ejemplo, elegir la factura que se está cobrando).

## Conceptos libres y conceptos atados a un módulo

El campo *Relacionado con* separa dos mundos, y de él depende quién manda sobre la
cuenta contable:

- **Sin relación con módulos del sistema** (y también Anticipos, Préstamo a
  empleado, Quincena): son **conceptos libres**. Su cuenta se elige aquí, en la
  ficha del concepto.
- **Módulo de compras, liquidaciones de compra, facturas de venta, recibos de
  venta y nómina (roles)**: la cuenta **no se elige aquí**. La toman de la
  configuración de su propio módulo en
  [Configuración Contable](configuracion-contable.md) —la cartera de ventas, las
  cuentas por pagar de compras, las cuentas de nómina—, así que el campo aparece
  bloqueado y con un candado, mostrando la cuenta que realmente se usará. En
  Nómina se muestran las dos cuentas posibles (Sueldos por Pagar para el rol
  mensual, Anticipos y Descuentos para quincena o semana), porque el reparto
  depende del tipo de rol.

## Cuenta contable

Para los **conceptos libres**, la cuenta que se ve y se edita aquí es **la misma
que administra [Configuración Contable](configuracion-contable.md) en el bloque
*Ingresos y Egresos***. No son dos configuraciones distintas: lo que se cambia en
una pantalla se ve en la otra, y quitarla en una la quita en la otra.

Un concepto **sin cuenta** genera un asiento incompleto: el dinero entra o sale
por la cuenta de la forma de pago, pero la contrapartida se queda sin cuenta y el
documento se reporta como pendiente al contabilizar.

La cuenta que se ve aquí es exactamente la que usa el asiento del ingreso o del
egreso, sin intermediarios.

Si el concepto cambia de naturaleza (de Ingreso a Egreso o al revés), la
configuración contable de la naturaleza anterior se retira: ese concepto ya no
aparece en ese bloque.

## Cómo se usa

1. **Nueva** y escriba el nombre del concepto (máx. 20 caracteres: es lo que se
   ve en los combos de Ingresos y Egresos).
2. Elija en *Relacionado con* si el concepto es libre o pertenece a un módulo.
   Al elegir un módulo, el sistema ya marca la aplicación que corresponde
   (compras y nómina son egresos, facturas y recibos de venta son ingresos).
3. Marque si aplica a **Ingreso** o a **Egreso**.
4. Asigne la **cuenta contable** (si el concepto es libre).
5. Guarde. El concepto queda disponible en el combo de conceptos de Ingresos o
   de Egresos.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre / Concepto | Sí | Cómo se llama el concepto en los combos. Máx. 20 caracteres |
| Relacionado con | Sí | Si el concepto es libre o pertenece a un módulo del sistema (ver arriba) |
| Aplica para | Sí | Ingreso o Egreso — una sola de las dos |
| Cuenta contable | No | Contrapartida del movimiento. Bloqueada en los conceptos atados a un módulo |
| Estado | Sí | Un concepto inactivo deja de ofrecerse, pero conserva su historial |

## Permisos

Se administran en `/config/permisos-modulos` como en el resto de módulos: ver,
crear, actualizar y eliminar. Con **acceso total** se ven los conceptos de toda la
empresa; sin él, solo los creados por el propio usuario.

## Reglas de negocio

- Un concepto aplica a **una sola** naturaleza: Ingreso o Egreso.
- Los conceptos atados a un módulo **no guardan cuenta propia**: aunque se
  intente enviar una, el sistema la ignora.
- **No se puede eliminar** un concepto que ya está usado en ingresos o egresos.
  Si ya no debe usarse, márquelo como inactivo.
- La eliminación es lógica: el concepto deja de aparecer, pero los documentos
  antiguos conservan su referencia.

## Integraciones con otros módulos

- **Ingresos y Egresos**: cada documento elige un concepto de esta lista.
- **Configuración Contable**: comparte con este módulo la cuenta de los conceptos
  libres, en el bloque *Ingresos y Egresos*.
- **Anticipos**: los conceptos con comportamiento de anticipo (cliente o
  proveedor) alimentan el saldo de anticipos. Al asignarles cuenta —desde aquí o
  desde Configuración Contable— esa misma cuenta pasa automáticamente a las
  **formas de cobro/pago de anticipo** que aún no tengan una: el anticipo de un
  cliente se registra por el concepto y se aplica después por la forma, y con
  cuentas distintas quedaría partido en dos cuentas. Una forma que ya tenga su
  cuenta elegida a mano no se toca.

## Errores frecuentes

- **"Debe seleccionar al menos una aplicación (Ingresos o Egresos)"**: no se
  marcó ninguna de las dos.
- **"No se puede eliminar este concepto porque ya está asignado a
  transacciones"**: el concepto ya se usó. Desactívelo en vez de borrarlo.
- **La cuenta aparece bloqueada con un candado**: el concepto está relacionado con
  un módulo y su cuenta se configura en la sección de ese módulo dentro de
  Configuración Contable.
- **El asiento del ingreso o egreso sale sin contrapartida**: el concepto no tiene
  cuenta asignada, o —si está atado a un módulo— falta la cuenta en la
  configuración de ese módulo.

## Historial de cambios

- **1.0** — Versión inicial. Documenta además que la cuenta contable de los
  conceptos libres es la misma que la del bloque *Ingresos y Egresos* de
  Configuración Contable, sincronizada en las dos pantallas.
