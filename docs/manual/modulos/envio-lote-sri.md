---
titulo: Envío en lote al SRI
resumen: Envío de muchos comprobantes al SRI en segundo plano, sin bloquear la pantalla.
categoria: Ventas
ruta_modulo: modulos/envio-lote-sri
tipo: modulo
visibilidad: todos
etiquetas: envio en lote, enviar al sri, masivo, cola, pendientes, autorizar comprobantes, segundo plano
version: 1.0
orden: 60
estado: activo
---

Cuando hay muchos comprobantes pendientes de enviar al SRI, hacerlo uno a uno es
inviable. Este módulo los envía **en lote y en segundo plano**: se arma la cola,
se lanza y se puede seguir trabajando mientras se procesa.

## Qué se puede enviar

Los cuatro tipos de comprobante electrónico del sistema: facturas de venta, notas
de crédito, retenciones y guías de remisión.

## Cómo se usa

1. Filtre los comprobantes pendientes que quiere enviar.
2. Arme el lote con los seleccionados.
3. Lánzelo.
4. Consulte el avance: cada comprobante muestra su resultado.

El proceso corre en el servidor, así que puede cerrar la pantalla sin
interrumpirlo.

## Resultados

Al terminar, cada comprobante queda **autorizado** o con el **motivo del
rechazo** del SRI. Los rechazados se corrigen en su propio módulo y se vuelven a
enviar; el lote no los reintenta solo.

## Errores frecuentes

- **Todo el lote falla**: casi siempre es la firma electrónica caducada o un
  problema de conexión con el SRI, no los comprobantes.
- **Un comprobante concreto se rechaza**: lea el motivo que devuelve el SRI;
  suele ser un dato del cliente o del secuencial.
- **El lote parece detenido**: el envío es en segundo plano y depende de los
  tiempos de respuesta del SRI.

## Historial de cambios

- **1.0** — Versión inicial.
