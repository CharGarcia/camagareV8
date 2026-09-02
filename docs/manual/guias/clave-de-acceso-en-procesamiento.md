---
titulo: "\"Clave de acceso en procesamiento\": qué significa y qué hacer"
resumen: Por qué el SRI responde que la clave está en procesamiento, por qué no es un rechazo y por qué reenviar el comprobante no sirve de nada.
categoria: Ventas
tipo: guia
visibilidad: todos
etiquetas: clave de acceso en procesamiento, error 70, comprobante devuelto, el sri devolvio el comprobante con errores, reenviar al sri, reintento automatico, en cola, sin autorizacion, factura devuelta, nota de credito devuelta, retencion devuelta, guia devuelta
version: 1.0
orden: 30
estado: activo
---

Al enviar un comprobante, el SRI puede responder:

> **CLAVE DE ACCESO EN PROCESAMIENTO** — La clave de acceso 0209…8810 está en
> procesamiento.

Aunque llega con la etiqueta de error, **no es un rechazo**. Significa que el SRI
**ya recibió** el comprobante en un envío anterior y todavía no publica el
resultado. El documento está en su cola; lo único que falta es esperar la
resolución.

## Por qué reenviarlo no sirve

Cada comprobante viaja con su **clave de acceso**, que es única. Si el SRI ya
tiene esa clave en cola, cualquier reenvío recibe exactamente la misma respuesta:
no se puede "empujar" un comprobante que ya está adentro. Lo correcto es
consultar su autorización, no volver a mandarlo.

## Qué hace el sistema

Lo reconoce y **pasa directo a consultar la autorización**, en vez de darlo por
devuelto. A partir de ahí, dos finales:

- **El SRI ya resolvió**: el comprobante queda autorizado (o rechazado, con su
  motivo) en el momento.
- **El SRI todavía no resuelve**: el comprobante queda **en seguimiento** y el
  reintento automático vuelve a consultarlo por su cuenta hasta que haya
  respuesta. Si pasa una hora sin resolverse, el sistema avisa por correo.

En ninguno de los dos casos hay que corregir nada en el documento.

Aplica a **facturas de venta, notas de crédito, notas de débito, retenciones de
compra, guías de remisión, liquidaciones de compra y facturas de reembolso**.

## Qué hacer usted

Nada, normalmente: espere unos minutos y vuelva a mirar el estado del documento.
Si quiere apurarlo, use el botón de enviar al SRI del propio documento — no lo
reenviará, solo consultará su estado y lo actualizará si ya hay respuesta.

## No confundir con estos

| Mensaje del SRI | ¿Es un rechazo? | Qué hacer |
|---|---|---|
| **Clave de acceso en procesamiento** (70) | No | Esperar; el sistema lo sigue solo |
| **Clave de acceso registrada** (43) | Sí, definitivo | Ese número ya se usó: revisar el secuencial |
| **Firma inválida / caducada** | Sí | Renovar el certificado y volver a enviar |
| **Errores en el XML** | Sí | Corregir lo que indique el mensaje y reenviar |

## Historial de cambios

- **1.0** — Versión inicial. Antes de este cambio el mensaje se mostraba como
  *"El SRI devolvió el comprobante con errores"* y el documento quedaba marcado
  como devuelto; al estarlo, tampoco entraba en el reintento automático, así que
  nadie volvía a consultarlo y había que reenviarlo a mano (sin efecto).
