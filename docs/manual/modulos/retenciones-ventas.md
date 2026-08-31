---
titulo: Retenciones de venta
resumen: Retenciones que los clientes le practican a la empresa y que se registran para descontarlas del cobro.
categoria: Ventas
ruta_modulo: modulos/retenciones_ventas
tipo: modulo
visibilidad: todos
etiquetas: retencion de venta, retenciones recibidas, cliente retiene, credito tributario, periodo fiscal, cobro
version: 1.3
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
5. Registre los valores retenidos de IVA y de renta. Al buscar el código de
   retención puede escribir cualquier dato del catálogo —código, concepto,
   porcentaje (`1.75`, `1,75` o `1.75 %`), impuesto o código del anexo—: se busca
   en todas esas columnas a la vez y admite varias palabras en cualquier orden.
   Solo se ofrecen los códigos vigentes a la fecha de emisión; la vigencia se
   revisa en **Configuración → Retenciones SRI**.
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

## Exportar el comprobante

En el modal de una retención ya guardada, junto al botón **PDF** hay un botón
**Excel** que descarga el mismo comprobante en formato `.xlsx` (archivo
`Retencion_Venta_001-001-000000123.xlsx`): datos del cliente y del período
fiscal, el detalle de las líneas retenidas (documento sustento, código,
concepto, base imponible, porcentaje y valor) y los totales de renta, IVA, ISD
y total retenido. Ambos botones solo aparecen cuando la retención ya se guardó.

## Qué pasa al eliminar una retención

Eliminar una retención **anula también su asiento contable**, en el mismo paso.
Antes el asiento quedaba vivo y seguía sumando en el Balance aunque la retención
ya no existiera.

El asiento no se borra: queda en estado **anulado**, así que el rastro se conserva
y deja de afectar los reportes.

Si la fecha de la retención cae en un **período contable cerrado**, la eliminación
se rechaza. Reabra el período si realmente necesita eliminarla.

## Errores frecuentes

- **"El período fiscal debe tener el formato MM/YYYY"**: escríbalo con mes y año,
  por ejemplo `07/2026`.
- **La factura queda con saldo pendiente que nadie va a pagar**: falta registrar
  la retención que le practicó el cliente.
- **"No se puede registrar el asiento: la fecha ... corresponde a un período
  contable cerrado"** al eliminar: la eliminación anula el asiento, y eso no se
  puede hacer en un período cerrado.

## Historial de cambios

- **1.3** — El buscador del código de retención cubre todas las columnas del
  catálogo del SRI (código, concepto, porcentaje, impuesto y código del anexo).
- **1.2** — Eliminar una retención ahora **anula su asiento contable**. Antes el
  asiento sobrevivía a la retención y seguía afectando el Balance. Efecto
  secundario esperado: ya no se puede eliminar una retención cuya fecha esté en un
  período contable cerrado.
- **1.1** — Nuevo botón **Excel** junto al de PDF en el modal: descarga el
  comprobante de la retención en `.xlsx` con el mismo detalle y totales.
- **1.0** — Versión inicial.
