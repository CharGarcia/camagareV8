---
titulo: Saldos iniciales
resumen: Carga del punto de partida al empezar a usar el sistema: cartera, bancos, efectivo e inventario.
categoria: Configuración de empresa
ruta_modulo: modulos/saldos-iniciales
tipo: modulo
visibilidad: admin
etiquetas: saldos iniciales, apertura, arranque, migracion, cartera inicial, stock inicial, empezar a usar el sistema
version: 1.0
orden: 10
estado: activo
---

Los **saldos iniciales** son la foto con la que la empresa arranca en el sistema:
lo que le deben, lo que debe, cuánto dinero tiene y qué mercadería hay en bodega.
Se cargan una sola vez, al empezar.

## Qué se puede cargar

| Pestaña | Qué carga |
|---------|-----------|
| Cuentas por cobrar | Facturas pendientes de cobro, por cliente |
| Cuentas por pagar | Facturas pendientes de pago, por proveedor |
| Bancos | Saldo de cada cuenta bancaria |
| Efectivo | Saldo de caja |
| Inventario | Existencias iniciales, como kardex de apertura |
| Consignaciones | Mercadería en consignación (solo registro) |

## Cartera: el cliente o proveedor es obligatorio

En cuentas por cobrar y por pagar hay que indicar **a quién** corresponde cada
saldo: no se admite un saldo suelto. Además, el **número de documento** debe
seguir el formato `000-000-000000000`.

Es la única forma de que después ese saldo aparezca en la cartera del cliente
correcto y se pueda cobrar contra él.

## Consignaciones: solo registro

Los saldos de consignación se registran para tenerlos identificados, pero **no
afectan al stock**: la mercadería en consignación no es existencia propia.

## Antes de cargar

Cargue los saldos iniciales **antes** de empezar a operar, y con fecha anterior
al primer documento real. Si se cargan después, los informes de los primeros días
saldrán incompletos y habrá que rehacerlos.

## Cómo se calcula el pendiente de un saldo inicial por cobrar

El valor de la columna **Pendiente** no es un dato guardado: se recalcula cada
vez, restando al saldo inicial todo lo que ya lo cubrió.

```
Pendiente = Saldo inicial − Cobrado − Retenciones − Notas de crédito
```

Las **retenciones** y las **notas de crédito** se enlazan por el **número de
documento**: una retención cuyo documento de sustento, o una nota de crédito cuyo
documento modificado, coincida con el número del saldo inicial lo abona
automáticamente. No hay que registrarlas dos veces.

Si ese mismo número existe además como **factura real** en el sistema, el abono
se aplica a la factura y no al saldo inicial, para no descontarlo dos veces.

Es el mismo cálculo que usa **Cuentas por Cobrar**, así que ambas pantallas
muestran siempre el mismo pendiente y el mismo estado.

## Errores frecuentes

- **"El número de documento debe tener el formato 000-000-000000000"**: respete
  establecimiento, punto de emisión y secuencial.
- **"Debe seleccionar un cliente registrado"**: regístrelo primero en Clientes.
- **El stock inicial no aparece**: compruebe que el producto sea inventariable y
  la bodega la correcta.
- **El pendiente no coincide con Cuentas por Cobrar**: ocurría cuando el saldo
  inicial tenía una **nota de crédito** aplicada: este listado no la restaba y
  Cuentas por Cobrar sí, así que el mismo documento mostraba dos valores. Ya
  está corregido; ambas pantallas usan el mismo cálculo.

## Historial de cambios

- **1.1** — El **Pendiente** de los saldos iniciales por cobrar descuenta también
  las **notas de crédito**, igual que ya hacía con los cobros y las retenciones
  (antes solo lo hacía Cuentas por Cobrar, y las dos pantallas discrepaban). El
  estado PENDIENTE / PARCIAL / PAGADO se calcula con el mismo criterio, y el
  monto máximo cobrable ya no incluye la parte cubierta por la nota — antes se
  podía registrar un cobro por el importe completo y sobrecobrar. Nueva sección
  *Cómo se calcula el pendiente de un saldo inicial por cobrar*.
- **1.0** — Versión inicial.
