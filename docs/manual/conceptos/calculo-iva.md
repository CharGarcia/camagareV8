---
titulo: Cómo se calcula el IVA (al subtotal o línea por línea)
resumen: El IVA de facturas, recibos, notas de crédito, proformas y demás documentos se calcula según la configuración de facturación del establecimiento de la serie con que se emite.
categoria: Ventas
tipo: concepto
visibilidad: todos
etiquetas: iva, calculo del iva, impuesto, 15%, al subtotal, linea por linea, linea a linea, item por item, redondeo, centavo, un centavo de diferencia, no cuadra el iva, iva mal calculado, 14.73, 14.72, configuracion de facturacion, establecimiento, serie, punto de emision, sri, total de la factura
version: 1.1
orden: 20
estado: activo
---

El IVA de un documento de venta no se calcula siempre igual: depende de cómo
esté configurado el **establecimiento** con el que se emite. Esta página explica
las dos formas, de dónde sale la configuración y por qué a veces hay un centavo
de diferencia.

## Las dos formas de calcular el IVA

Se elige en **Empresa → Facturación → Cálculo del IVA**:

- **Al subtotal**: se suman las bases de todas las líneas de la misma tarifa
  (cantidad × precio − descuento) y el IVA se calcula **una sola vez** sobre esa
  suma, redondeado a 2 decimales.
- **Línea por línea**: se calcula el IVA de cada línea, se redondea cada uno a 2
  decimales y luego se suman.

Ejemplo con tres líneas de 32,70 al 15% (subtotal 98,10):

| Forma | Cálculo | IVA | Total |
|-------|---------|-----|-------|
| Al subtotal | 98,10 × 15% = 14,715 → **14,72** | 14,72 | 112,82 |
| Línea por línea | 4,905 → 4,91, tres veces | 14,73 | 112,83 |

Las dos son válidas para el SRI. La diferencia es de centavos y aparece cuando
varias líneas redondean hacia el mismo lado.

## De dónde sale la configuración: la serie del documento

La configuración de facturación es **por establecimiento**. El sistema usa la del
establecimiento al que pertenece la **serie (punto de emisión)** que se elige en
el documento. Si una empresa tiene un establecimiento "al subtotal" y otro "línea
por línea", cada factura se calcula según la serie con que se emite, y al cambiar
de serie los totales se recalculan en pantalla.

Aplica a: Facturas de Venta, Recibos de Venta, Notas de Crédito, Notas de Débito,
Proformas, Facturación CV, Retornos CV, Cambio de productos CV, Liquidaciones de Compra, Servicio Externo, Car-Wash,
Caja POS, Comandas, Taller (orden y factura), Factura Express, Cotización de
Publicidad → Factura, Suscripciones, Alumnos, Carga de facturas por Excel y la
app móvil.

## Redondeo

Todo valor se redondea a 2 decimales con el criterio normal: de 0,5 en adelante
sube (14,715 → 14,72). La pantalla y el servidor usan el mismo redondeo, así que
lo que se ve al emitir es lo que se guarda y se envía al SRI.

En modo "al subtotal" el comprobante igual lleva el IVA de cada línea (el SRI lo
pide). El sistema reparte los centavos entre las líneas para que la suma de los
IVA de las líneas sea exactamente el IVA de la tarifa: en el ejemplo, las líneas
quedan 4,90 + 4,91 + 4,91 = 14,72.

## Lo que se ve es lo que se guarda y se imprime

- Al guardar una factura, nota de crédito, recibo o nota de débito, el sistema
  **vuelve a calcular el IVA** con la configuración de la serie, venga de donde
  venga el documento (pantalla, Caja POS, Taller, app móvil, etc.). Así nunca se
  guarda un IVA calculado de otra forma.
- Si por redondeo el total cambia en unos centavos (hasta 0,05), la **última forma
  de pago** se ajusta sola para que cuadre. Una diferencia mayor no es redondeo: el
  documento no se guarda y el sistema pide volver a abrirlo para recalcular.
- Los PDF (RIDE, proforma, suscripción) muestran el IVA **guardado**; no lo
  recalculan al imprimir.

## Errores frecuentes

- **El IVA sale un centavo más alto de lo esperado**: el establecimiento de la
  serie está configurado "línea por línea". Revise **Empresa → Facturación** del
  establecimiento de esa serie.
- **Un documento ya emitido muestra otro IVA que uno nuevo**: los documentos
  guardan el IVA con que se emitieron; cambiar la configuración solo afecta a los
  documentos nuevos.
- **"El IVA de la factura no coincide con la configuración de facturación…"**: el
  documento llegó con un IVA que difiere en más de 0,05 del que corresponde. Cierre
  y vuelva a abrir el documento (o cambie de serie y regrese) para que la pantalla
  recalcule los totales, y guarde de nuevo.

## Historial de cambios

- **1.1** — El servidor recalcula el IVA al guardar facturas, notas de crédito,
  recibos y notas de débito, y ajusta la última forma de pago hasta 0,05. El PDF de
  proformas muestra lo guardado y el de suscripciones usa la configuración de
  facturación.
- **1.0** — Versión inicial. El modo se toma de la serie del documento (antes, del
  primer establecimiento de la empresa) y lo respetan también Caja POS, Comandas,
  Taller, Factura Express, Cotización de Publicidad y la app móvil. Redondeo
  corregido en pantalla (98,10 × 15% daba 14,71 en algunos módulos).
