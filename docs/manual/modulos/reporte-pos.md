---
titulo: Reporte del punto de venta
resumen: Ventas y arqueos por turno de caja, para controlar cada sesión del POS.
categoria: Reportes
ruta_modulo: modulos/reporte-pos
tipo: modulo
visibilidad: todos
etiquetas: reporte pos, punto de venta, turno, caja, arqueo, cierre de caja, diferencia, cajero, ventas del dia
version: 1.0
orden: 60
estado: activo
---

Este reporte muestra lo ocurrido en cada **sesión de caja** del punto de venta:
qué se vendió, cómo se cobró y cómo cerró el arqueo.

## Qué muestra

- Ventas del turno, por forma de cobro.
- Fondo inicial declarado al abrir.
- Monto contado al cerrar.
- **Diferencia** entre lo que debería haber y lo que se contó.

## La diferencia es el dato

Un turno que cierra con diferencia cero es un turno cuadrado. Cualquier otra cosa
merece revisión el mismo día: a fin de mes ya nadie recuerda qué pasó.

Las causas habituales, en orden de frecuencia: un cobro registrado con una forma
de pago equivocada, una salida de efectivo no registrada, o un error de conteo.

## Filtros

Por rango de fechas, por caja o punto de emisión y por usuario.

## Errores frecuentes

- **Un turno aparece abierto desde hace días**: no se cerró la sesión; ciérrela
  para poder cuadrarla.
- **La diferencia es exactamente el importe de una venta**: probablemente esa
  venta se cobró con una forma de pago distinta a la real.

## Historial de cambios

- **1.0** — Versión inicial.
