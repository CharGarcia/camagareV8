---
titulo: "\"Clave de acceso en procesamiento\": qué significa y qué hacer"
resumen: Por qué el SRI responde que la clave está en procesamiento, por qué no es un rechazo y por qué reenviar el comprobante no sirve de nada.
categoria: Ventas
tipo: guia
visibilidad: todos
etiquetas: clave de acceso en procesamiento, error 70, error 45, error 43, secuencial registrado, clave acceso registrada, comprobante devuelto, el sri devolvio el comprobante con errores, reenviar al sri, reintento automatico, en cola, sin autorizacion, factura devuelta, nota de credito devuelta, retencion devuelta, guia devuelta, no aparece en el portal, ambiente de pruebas, numero ya usado, no se puede eliminar
version: 1.1
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
| **Clave de acceso registrada** (43) | No | El SRI ya recibió esa misma clave en un envío anterior; el sistema la trata igual que la 70 y consulta la autorización |
| **Secuencial registrado** (45) | Sí | Ese número ya existe en el SRI con **otra** clave: ver la sección siguiente |
| **Firma inválida / caducada** | Sí | Renovar el certificado y volver a enviar |
| **Errores en el XML** | Sí | Corregir lo que indique el mensaje y reenviar |

## Error 45 "Secuencial registrado": el número ya existe en el SRI

El SRI responde **ERROR SECUENCIAL REGISTRADO** cuando en ese ambiente ya tiene
un comprobante del mismo tipo con el mismo número (establecimiento, punto de
emisión y secuencial) pero con **otra clave de acceso**. No es un problema del
XML ni de la firma: el número ya está ocupado y no se puede volver a usar en
ese ambiente.

**"Registrado" no es "autorizado".** El SRI ata el número a la clave de acceso
del **primer envío que recibe**, y lo conserva aunque ese comprobante haya
terminado **no autorizado**. Desde entonces solo acepta ese número con la clave
original; con otra clave responde 45. Por eso puede pasar que en el portal el
número no aparezca como autorizado y aun así el SRI lo rechace.

Las causas conocidas:

- **Se eliminó una retención (o factura) no autorizada y se creó de nuevo.** El
  SRI la había recibido con su clave; la nueva lleva el mismo número y otra
  clave (aunque sea el mismo día, cambia el código numérico aleatorio). Lo
  correcto era corregir la misma retención y reenviarla, no eliminarla.

- **Se eliminó un documento que ya se había enviado.** Un borrador enviado al
  SRI y luego eliminado en el sistema liberaba su número, y el siguiente
  documento lo volvía a tomar. Hoy eso ya no pasa: el sistema **no reutiliza el
  número de un documento eliminado si el SRI ya lo recibió, lo tiene en cola o
  lo autorizó**, y tampoco deja **eliminar** un borrador en esa situación (hay
  que anularlo o esperar la resolución del SRI).
- **Se cambió la fecha de emisión de un borrador después de enviarlo.** La clave
  de acceso lleva la fecha, así que cambia; el número no. La clave vieja sigue
  en el SRI.

Por qué "en el portal no aparece": el portal del SRI muestra solo los
comprobantes de **producción** y se busca por la clave o la fecha **actuales**.
Si la empresa está en ambiente de **pruebas**, el comprobante nunca va a salir
en el portal aunque el SRI de pruebas lo tenga registrado. Y si la clave cambió,
hay que buscar por número y por la **fecha del envío original**. El mensaje que
muestra el sistema ya indica el número y el ambiente afectados.

Qué hacer: asignar al documento el **siguiente secuencial libre** (se puede
subir el secuencial inicial de la serie en la configuración del punto de
emisión) y volver a enviar. Si la empresa debía estar en producción y no en
pruebas, cambiar el ambiente en la ficha de la empresa y volver a guardar el
borrador: la clave se regenera con el ambiente nuevo y en producción ese número
está libre.

## Historial de cambios

- **1.1** — Nueva sección sobre el **error 45 "Secuencial registrado"**: el
  sistema ya no reutiliza el número de un documento eliminado que el SRI ya
  recibió, no deja eliminar un borrador en esa situación y explica el error con
  el número y el ambiente afectados. El error **43 "Clave de acceso registrada"**
  pasa a tratarse como la 70 (consulta la autorización en vez de marcar el
  documento como devuelto).

- **1.0** — Versión inicial. Antes de este cambio el mensaje se mostraba como
  *"El SRI devolvió el comprobante con errores"* y el documento quedaba marcado
  como devuelto; al estarlo, tampoco entraba en el reintento automático, así que
  nadie volvía a consultarlo y había que reenviarlo a mano (sin efecto).
