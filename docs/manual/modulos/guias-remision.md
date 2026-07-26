---
titulo: Guías de remisión
resumen: Documento electrónico que ampara el traslado de mercadería entre dos puntos.
categoria: Ventas
ruta_modulo: modulos/guias_remision
tipo: modulo
visibilidad: todos
etiquetas: guia de remision, guias, traslado, transporte, envio, placa, transportista, sri, mercaderia en transito
version: 1.0
orden: 55
estado: activo
---

La **guía de remisión** es el documento electrónico que respalda el traslado de
mercadería: quién la envía, quién la recibe, quién la transporta y en qué
vehículo. Se envía al SRI como cualquier otro comprobante electrónico.

## Antes de emitirla

Necesita tener registrados:

- El **cliente** que recibe la mercadería (destinatario).
- El **transportista**, con su identificación.
- El establecimiento, punto de emisión y secuencial configurados.

## Cómo se emite

1. Pulse **Nuevo**.
2. Elija **establecimiento** y **punto de emisión**; revise el **secuencial**.
3. Seleccione el **destinatario**.
4. Seleccione el **transportista** e ingrese la **placa** del vehículo.
5. Indique la **fecha de emisión** y la **fecha de inicio del transporte**.
6. Añada los productos que se trasladan.
7. Guarde y envíe al SRI.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Establecimiento y punto de emisión | Obligatorios |
| Secuencial | Obligatorio |
| Destinatario | Obligatorio |
| Transportista | Obligatorio |
| Placa del vehículo | Obligatoria |
| Fecha de emisión | Obligatoria |
| Fecha de inicio de transporte | Obligatoria |

## La guía no mueve inventario

Emitir una guía **no descuenta stock**: solo ampara el traslado. El movimiento de
inventario lo genera el documento que corresponda (la factura de venta, o el
traslado entre bodegas).

## Errores frecuentes

- **"Ingrese la placa del vehículo transportista"**: es obligatoria aunque el
  transporte sea propio.
- **"Seleccione el transportista"**: regístrelo primero en Transportistas.
- **El stock no bajó al emitir la guía**: es correcto, la guía no mueve
  inventario.

## Historial de cambios

- **1.0** — Versión inicial.
