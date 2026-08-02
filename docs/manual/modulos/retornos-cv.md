---
titulo: Retornos de consignación
resumen: Devolución de la mercadería consignada que el cliente no vendió.
categoria: Ventas
ruta_modulo: modulos/retornos-cv
tipo: modulo
visibilidad: todos
etiquetas: retorno, retornos, devolucion de consignacion, mercaderia no vendida, reingreso, saldo consignado
version: 1.1
orden: 46
estado: activo
---

Un **retorno** es la devolución de la mercadería consignada que el cliente no
logró vender. Es la entrada espejo de la consignación: lo que salió del almacén
vuelve a entrar.

## Cómo funciona

1. Se elige el cliente y la consignación de la que devuelve.
2. Se indican los productos y cantidades que regresan.
3. Al registrar el retorno, la mercadería **vuelve al inventario**.

## Devoluciones parciales

No hace falta devolver todo de una vez: se pueden registrar varios retornos
parciales sobre la misma consignación. El sistema lleva el **saldo** de lo que
sigue en poder del cliente.

Ese saldo es el dato clave: cuadra siempre lo entregado con lo vendido más lo
devuelto.

## Exportar

En la barra de acciones del comprobante, junto al botón **PDF**, hay un botón
**Excel** que descarga el detalle del retorno (código, descripción, lote, NUP
y cantidad) en una hoja de cálculo. Requiere que el retorno esté guardado.

## Errores frecuentes

- **El saldo no cuadra**: revise si falta registrar un retorno o si hay
  mercadería vendida sin facturar.
- **El stock no subió**: compruebe la bodega de destino del retorno.
- **No aparece la consignación**: puede estar ya liquidada por completo.

## Historial de cambios

- **1.1** — Nuevo botón **Excel** en la barra de acciones del comprobante,
  junto al de PDF: descarga el detalle del retorno en una hoja de cálculo.
- **1.0** — Versión inicial.
