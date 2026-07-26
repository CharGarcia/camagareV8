---
titulo: Descargas del SRI
resumen: Descarga de los comprobantes electrónicos que otros emitieron a nombre de la empresa.
categoria: Compras
ruta_modulo: modulos/descargas-sri
tipo: modulo
visibilidad: todos
etiquetas: descargas sri, comprobantes recibidos, xml, facturas de proveedores, importar compras, portal sri
version: 1.0
orden: 50
estado: activo
---

Este módulo trae automáticamente los **comprobantes electrónicos que otros
emitieron a nombre de la empresa**: facturas de proveedores, notas de crédito,
retenciones que le practicaron. Evita capturarlas a mano.

## Qué hace

Se conecta al portal del SRI con las credenciales de la empresa y descarga los
comprobantes recibidos del periodo indicado. De cada uno guarda el XML íntegro
tal como lo entrega el SRI.

Desde ahí, los comprobantes se pueden registrar como compras sin volver a
escribir nada.

## Duplicados

El sistema evita registrar dos veces el mismo comprobante, aunque se descargue
varias veces o dos personas lo hagan a la vez. Si un documento ya existe, no se
duplica.

## Después de descargar

Descargar **no es lo mismo que registrar la compra**. El comprobante queda
disponible para convertirlo en compra; solo entonces afecta a cuentas por pagar y
puede generar entrada de inventario.

Recuerde que las líneas traen los **códigos del proveedor**: para que la
mercadería entre al inventario hay que vincularlas con productos de su catálogo.

## Errores frecuentes

- **No descarga nada**: revise las credenciales del SRI de la empresa y el rango
  de fechas.
- **El comprobante está descargado pero no aparece en compras**: falta
  registrarlo como compra.
- **La descarga tarda**: el portal del SRI impone sus propios tiempos; para
  periodos largos conviene descargar por tramos.

## Historial de cambios

- **1.0** — Versión inicial.
