---
titulo: Punto de venta (POS)
resumen: Pantalla de venta rápida con apertura y cierre de caja por turno.
categoria: Ventas
ruta_modulo: modulos/caja-pos
tipo: modulo
visibilidad: todos
etiquetas: pos, punto de venta, caja, mostrador, venta rapida, apertura de caja, cierre de caja, arqueo, fondo inicial, servicio, 10%, propina, recargo, punto de emision, establecimiento, turno, restaurante, salon, volver al sistema
version: 1.3
orden: 25
estado: activo
---

El **punto de venta** es la pantalla de venta rápida: se abre en ventana propia,
se cobra en pocos clics y se emite la factura sin pasar por el formulario
completo.

## Abrir la caja

Antes de vender hay que **abrir la sesión de caja**:

1. Elija el **establecimiento** y el **punto de emisión**.
2. Indique el **fondo inicial** (el efectivo con el que arranca el turno).
3. Abra la sesión.

El fondo inicial debe ser numérico y no puede ser negativo. Puede ser cero.

Si la empresa tiene **un solo establecimiento con un solo punto de emisión**, la
pantalla los selecciona sola y se llega directo a abrir el turno (o a continuar
el que ya esté abierto). Cuando hay varios, hay que elegir: el punto de emisión
determina por dónde se emite, así que el sistema no elige por usted.

Si en ese punto de emisión **ya hay un turno abierto**, no se abre otro: la
pantalla muestra el turno vigente, con su cajero y su fondo, y el botón
**Continuar**. Un mismo local puede tener varios turnos abiertos a la vez, uno
por punto de emisión.

Esta pantalla se abre en ventana propia, sin el menú del sistema. Si entró y no
va a operar, use **Volver al sistema**, al pie de la tarjeta.

## Vender

Con la caja abierta, se buscan los productos, se arma el pedido, se elige la
forma de cobro y se emite el documento. La factura se genera en borrador con su
cobro ya registrado.

**Si se elimina esa factura, el ingreso asociado se revierte automáticamente.**

## El recargo por servicio (el 10%)

Si el establecimiento cobra recargo por servicio, el carrito lo muestra como una
línea propia entre el IVA y el total, y va incluido en el documento como
**propina**.

Se configura en **Empresa → Facturación → Recargo por servicio (restaurantes)**,
la misma configuración que usa el salón, y hace falta tener activo
**¿Mostrar el campo de propina en la factura?**:

- **Obligatorio**: toda venta lo lleva; el cajero no puede quitarlo.
- **Opcional**: la venta arranca con el recargo puesto y el cajero puede
  retirarlo con el enlace **Quitar** de los totales. Vale solo para esa venta:
  la siguiente vuelve a nacer con el recargo.

El valor se calcula sobre el subtotal y se suma después del IVA, así que ese
porcentaje no paga IVA. Quien decide el porcentaje —y si se puede quitar— es la
configuración, no la pantalla: aunque alguien manipule la petición, el servidor
vuelve a resolverlo antes de emitir.

## El turno que usa el salón

El **restaurante** comparte esta misma pantalla: el tablero de mesas no opera sin
un turno abierto, y cuando falta, su aviso trae aquí. Al llegar desde el salón,
**Continuar** devuelve al tablero de mesas en lugar de ir al mostrador.

Por eso, **quien tenga permiso sobre Mesas puede abrir y cerrar el turno aquí
aunque no tenga asignado el submódulo Cajas**: sin turno el salón no trabaja, y
el camino no puede depender de un permiso que el mesero no necesita para nada
más. Ese acceso alcanza solo al turno — la pantalla de **venta** del mostrador
sigue requiriendo permiso propio sobre el Punto de Venta.

Cada usuario elige su punto de emisión al abrir su turno, y las mesas que abra
después se facturan por ese punto.

## Cerrar la caja

Al terminar el turno se cierra la sesión declarando el **monto contado**: el
efectivo que hay físicamente. El sistema lo compara con lo que debería haber
según las ventas del turno y muestra la diferencia.

El monto contado es obligatorio y debe ser numérico. Ese arqueo es el que
permite detectar faltantes el mismo día, no a fin de mes.

## Errores frecuentes

- **No puedo vender**: la caja no está abierta, o no eligió punto de emisión al
  abrirla.
- **"El fondo inicial no puede ser negativo"**: revise el valor; si no hay fondo,
  ponga cero.
- **El arqueo no cuadra**: revise las ventas cobradas en efectivo del turno y si
  hubo salidas de dinero no registradas.
- **"Este punto de emisión ya tiene una caja abierta"**: hay un turno vigente ahí.
  Contínuelo, o ciérrelo antes de abrir uno nuevo.
- **Entré a la caja y no sé cómo volver**: use **Volver al sistema**, al pie de la
  tarjeta; la pantalla se abre sin el menú habitual.

## Historial de cambios

- **1.3** — La tirilla se maqueta para el ancho imprimible real de 72 mm y con
  columnas de ancho fijo: ya no sale reescalada ni con los importes corridos en
  impresoras térmicas de 80 mm.
- **1.2** — El establecimiento y el punto de emisión se seleccionan solos cuando
  hay uno solo de cada uno. Botón *Volver al sistema* para salir sin abrir caja.
  Quien tiene permiso sobre Mesas puede abrir y cerrar el turno aunque no tenga
  asignado el submódulo Cajas (solo el turno, no la venta de mostrador).
- **1.1** — Recargo por servicio (el 10%) también en el mostrador, cobrado como
  propina y con la misma configuración que el salón.
- **1.0** — Versión inicial.
