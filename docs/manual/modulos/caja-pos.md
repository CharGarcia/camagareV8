---
titulo: Punto de venta (POS)
resumen: Pantalla de venta rápida con apertura y cierre de caja por turno.
categoria: Ventas
ruta_modulo: modulos/caja-pos
tipo: modulo
visibilidad: todos
etiquetas: pos, punto de venta, caja, mostrador, venta rapida, apertura de caja, cierre de caja, arqueo, fondo inicial
version: 1.0
orden: 25
estado: activo
---

El **punto de venta** es la pantalla de venta rápida: se abre en ventana propia,
se cobra en pocos clics y se emite la factura sin pasar por el formulario
completo.

## Abrir la caja

Antes de vender hay que **abrir la sesión de caja**:

1. Elija el **punto de emisión**.
2. Indique el **fondo inicial** (el efectivo con el que arranca el turno).
3. Abra la sesión.

El fondo inicial debe ser numérico y no puede ser negativo. Puede ser cero.

## Vender

Con la caja abierta, se buscan los productos, se arma el pedido, se elige la
forma de cobro y se emite el documento. La factura se genera en borrador con su
cobro ya registrado.

**Si se elimina esa factura, el ingreso asociado se revierte automáticamente.**

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

## Historial de cambios

- **1.0** — Versión inicial.
