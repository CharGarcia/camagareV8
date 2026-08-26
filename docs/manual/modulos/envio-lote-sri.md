---
titulo: Envío en lote al SRI
resumen: Envío de muchos comprobantes al SRI en segundo plano, sin bloquear la pantalla.
categoria: Ventas
ruta_modulo: modulos/envio-lote-sri
tipo: modulo
visibilidad: todos
etiquetas: envio en lote, enviar al sri, masivo, cola, pendientes, autorizar comprobantes, segundo plano, guias de remision, guia de remision, transporte
version: 1.1
orden: 60
estado: activo
---

Cuando hay muchos comprobantes pendientes de enviar al SRI, hacerlo uno a uno es
inviable. Este módulo los envía **en lote y en segundo plano**: se arma la cola,
se lanza y se puede seguir trabajando mientras se procesa.

## Qué se puede enviar

Los seis tipos de comprobante electrónico del sistema:

- Facturas de venta
- Notas de crédito
- Notas de débito
- Retenciones de compra
- Liquidaciones de compra
- **Guías de remisión**

Se listan solo los que están **pendientes de autorización** (no aparecen los ya
autorizados ni los anulados), del ambiente configurado en la empresa y dentro del
rango de fechas elegido.

## Cómo se usa

1. Elija el rango de fechas y marque los **tipos de comprobante** que quiere ver.
2. Filtre los comprobantes pendientes que quiere enviar.
3. Arme el lote con los seleccionados.
4. Lánzelo.
5. Consulte el avance: cada comprobante muestra su resultado.

El proceso corre en el servidor, así que puede cerrar la pantalla sin
interrumpirlo.

## Guías de remisión

Las guías se seleccionan y se envían igual que el resto, con dos diferencias
propias del comprobante:

- **No muestran importe**. La guía de remisión no lleva totales —el SRI no los
  pide—, por eso la columna *Total* aparece con un guion.
- **La fecha que manda es la de inicio de transporte**. Para el SRI, la fecha del
  comprobante de una guía es la de **inicio de transporte**, no la de emisión. Si
  esa fecha ya pasó, el SRI rechaza la guía por extemporánea. El listado marca
  esas guías con un **triángulo de advertencia** junto al número: corrija la
  fecha en el módulo *Guías de remisión* antes de incluirlas en el lote.

## Resultados

Al terminar, cada comprobante queda **autorizado** o con el **motivo del
rechazo** del SRI. Los rechazados se corrigen en su propio módulo y se vuelven a
enviar; el lote no los reintenta solo.

## Errores frecuentes

- **Todo el lote falla**: casi siempre es la firma electrónica caducada o un
  problema de conexión con el SRI, no los comprobantes.
- **Un comprobante concreto se rechaza**: lea el motivo que devuelve el SRI;
  suele ser un dato del cliente o del secuencial.
- **Una guía falla con "la fecha de inicio de transporte ya pasó"**: es la
  advertencia descrita arriba. Corrija la fecha en la guía y vuelva a enviarla.
- **El lote parece detenido**: el envío es en segundo plano y depende de los
  tiempos de respuesta del SRI.

## Historial de cambios

- **1.1** — Se agregan las **guías de remisión** al envío en lote (con aviso por
  fecha de inicio de transporte vencida). Se corrige la lista de tipos
  soportados, que omitía notas de débito y liquidaciones de compra.
- **1.0** — Versión inicial.
