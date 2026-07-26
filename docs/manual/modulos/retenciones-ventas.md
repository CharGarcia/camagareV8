---
titulo: Retenciones de venta
resumen: Retenciones que los clientes le practican a la empresa y que se registran para descontarlas del cobro.
categoria: Ventas
ruta_modulo: modulos/retenciones_ventas
tipo: modulo
visibilidad: todos
etiquetas: retencion de venta, retenciones recibidas, cliente retiene, credito tributario, periodo fiscal, cobro
version: 1.0
orden: 40
estado: activo
---

Cuando un cliente que es agente de retención paga una factura, no entrega el
total: retiene una parte y entrega un **comprobante de retención**. Este módulo
registra esos comprobantes recibidos.

Es el espejo de las retenciones de compra: aquí la empresa es quien sufre la
retención, no quien la practica.

## Por qué hay que registrarlas

Por dos motivos, y ambos importan:

- **Para cobrar bien**: la factura se considera cobrada con el dinero recibido
  *más* la retención. Si no se registra, la factura queda con un saldo pendiente
  que nunca se va a cobrar.
- **Para la declaración**: la retención es crédito tributario de la empresa.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija el **cliente** que practicó la retención.
3. Complete **establecimiento**, **punto de emisión** y **secuencial** del
   comprobante que le entregaron.
4. Indique el **período fiscal** en formato **MM/YYYY** (por ejemplo `07/2026`).
5. Registre los valores retenidos de IVA y de renta.
6. Guarde.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Cliente | Obligatorio |
| Fecha de emisión | Obligatoria |
| Establecimiento | Obligatorio |
| Punto de emisión | Obligatorio |
| Secuencial | Obligatorio |
| Período fiscal | Obligatorio, con formato `MM/YYYY` |

## Relación con el cobro

Al registrar el ingreso que cobra esa factura, la retención se descuenta del
saldo pendiente. Por eso conviene registrarla **antes** de dar por cobrada la
factura: así los números cuadran solos.

## Errores frecuentes

- **"El período fiscal debe tener el formato MM/YYYY"**: escríbalo con mes y año,
  por ejemplo `07/2026`.
- **La factura queda con saldo pendiente que nadie va a pagar**: falta registrar
  la retención que le practicó el cliente.

## Historial de cambios

- **1.0** — Versión inicial.
