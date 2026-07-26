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

## Errores frecuentes

- **"El número de documento debe tener el formato 000-000-000000000"**: respete
  establecimiento, punto de emisión y secuencial.
- **"Debe seleccionar un cliente registrado"**: regístrelo primero en Clientes.
- **El stock inicial no aparece**: compruebe que el producto sea inventariable y
  la bodega la correcta.

## Historial de cambios

- **1.0** — Versión inicial.
