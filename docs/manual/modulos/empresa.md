---
titulo: Empresa
resumen: Datos de la empresa, sus establecimientos y la configuración que rige a todos los módulos.
categoria: Configuración de empresa
ruta_modulo: modulos/empresa
tipo: modulo
visibilidad: admin
etiquetas: empresa, datos de la empresa, ruc, establecimiento, punto de emision, logo, ambiente, pruebas, produccion, configuracion, correo, email, smtp, envio de correos, cuerpo del correo, asunto, plantilla de correo, remitente, documentos legales, acuerdo de uso de datos, contrato de uso del sistema, aceptacion de documentos, documentos firmados, documentos cargados, archivos de la empresa
version: 1.6
orden: 5
estado: activo
---

El módulo de **Empresa** guarda los datos de la compañía y la configuración que
condiciona el comportamiento del resto del sistema. Es lo primero que se
configura y lo que hay que revisar cuando algo se comporta distinto de lo
esperado en varios módulos a la vez.

## Datos generales

Razón social, RUC, nombre comercial, dirección, contacto y **logo** (que aparece
en los documentos impresos).

## Establecimientos y puntos de emisión

Cada local es un **establecimiento**, y dentro de él hay uno o varios **puntos de
emisión**. La numeración de los comprobantes depende de esta estructura: el
`001-002-000000123` de una factura son precisamente el establecimiento, el punto
de emisión y el secuencial.

### Logo del establecimiento

En la pestaña **Establecimientos** se sube el logo que aparece en los documentos
impresos (factura, nota de crédito, retención, liquidación de compra, guía de
remisión y recibo de venta). El logo ocupa un recuadro de **81 mm × 25.4 mm**
(relación de aspecto aproximada **3.2 : 1**, panorámico): la imagen se ajusta
completa dentro de ese recuadro sin deformarse (se centra y se reduce si hace
falta), por lo que un logo **horizontal/apaisado** aprovecha mejor el espacio que
uno cuadrado o vertical. Formato **PNG con fondo transparente** (también acepta
JPG o GIF), máximo 2 MB; resolución sugerida ~960 × 300 px o mayor, misma
proporción, para que se vea nítido al imprimir. Junto a la miniatura hay un
enlace para **descargar el logo actualmente guardado**.

Si los comprobantes salen con una numeración que no esperaba, es aquí donde se
corrige.

## Ambiente: pruebas o producción

La empresa opera en **ambiente de pruebas** o en **producción**. Es la
configuración más delicada del sistema:

- En **pruebas**, los comprobantes van al entorno de pruebas del SRI y **no
  tienen validez tributaria**.
- En **producción**, son documentos reales.

Los documentos quedan marcados con el ambiente en el que se emitieron. Por eso,
al cambiar de pruebas a producción, los documentos anteriores dejan de verse en
los listados: siguen ahí, pero pertenecen al otro ambiente.

## Configuración por módulo

Desde aquí se ajustan comportamientos que afectan a módulos concretos: cómo se
presentan los ítems en la factura, si las cargas de inventario requieren
aprobación, los textos de los correos, entre otros.

## Correo de comprobantes electrónicos

En la pestaña **Configuración Correo** se define cómo salen los correos que el
sistema envía cuando el SRI autoriza un comprobante (facturas, notas de crédito
y débito, retenciones, guías de remisión y liquidaciones de compra).

- **Tipo de correo**: usar el correo de Camagare o el correo propio de la
  empresa (host, puerto, SSL, usuario y contraseña). Use **Probar Envío** antes
  de activar el envío automático.
- **Enviar correos de forma automática**: si está apagado, el comprobante no se
  envía solo al autorizarse; igual se puede enviar a mano desde el documento.
- **Asunto predeterminado del correo**: si se deja vacío, el sistema usa
  "Comprobante Electrónico Autorizado".
- **¿Cómo se envía el cuerpo del correo?**:
  - *Usar el diseño del sistema* (opción por defecto): el correo sale con el
    logo de la empresa en la cabecera, el nombre del documento y su número, una
    caja con la fecha de emisión, el número de autorización y el valor total, la
    firma con la razón social y el RUC, y un pie de confidencialidad. El texto
    que usted escriba en *Cuerpo del correo* reemplaza el mensaje por defecto,
    pero todo lo demás se mantiene.
  - *Enviar solo mi contenido*: se envía únicamente lo que usted escriba, sin el
    diseño del sistema. Escriba entonces el correo completo, con su saludo y su
    despedida. Si deja el cuerpo vacío, el sistema usa igualmente su diseño para
    no enviar un correo en blanco.
- **Cuerpo del correo**: editor de texto con formato (títulos, negritas,
  colores, alineación, listas, enlaces e imágenes).

El logo que aparece en la cabecera del correo es el del **establecimiento** que
emitió el documento (pestaña Establecimientos). Si el establecimiento no tiene
logo, la cabecera muestra el nombre de la empresa en texto.

El remitente que ve el destinatario es el nombre comercial de la empresa (o su
razón social si no tiene nombre comercial).

En ambos modos el correo lleva adjuntos el **PDF** y el **XML** autorizado del
comprobante.
## Errores frecuentes

- **Los comprobantes salen con numeración equivocada**: revise establecimiento y
  punto de emisión.
- **Desaparecieron los documentos antiguos**: se cambió el ambiente; los
  documentos de pruebas no se ven en producción.
- **El logo no sale en el PDF**: compruebe que esté cargado y en un formato
  admitido.

- **El correo llega con dos saludos o dos despedidas**: está usando el diseño
  del sistema y además escribió su propio saludo o firma en el cuerpo. Quite esa
  parte de su texto, o cambie a *Enviar solo mi contenido*.
- **Las imágenes que inserté en el cuerpo no se ven**: el editor guarda las
  imágenes dentro del texto y la mayoría de los correos (Gmail, Outlook) las
  bloquea. Use el logo del establecimiento, que sí se envía correctamente.
## Documentos Legales y Archivos de la Empresa

En la pestaña **Información General**, debajo de la tarjeta de Suscripción y
Vigencia, hay una tarjeta con dos bloques:

- **Documentos Legales**: el estado del **Acuerdo de Uso de Datos** y el
  **Contrato de Uso del Sistema**. Muestra un badge — **Sin enviar**,
  **Pendiente de aceptación** o **Aceptado** — la fecha de envío, el correo al
  que se enviaron y, si ya se aceptaron, quién los aceptó y cuándo. Cada
  documento tiene un botón **Ver PDF** para revisar su contenido: si ya se
  enviaron, abre la versión exacta que se envió; si todavía no se han enviado,
  abre la versión vigente, para poder revisarlos **antes** de enviarlos. Si
  hubo más de un envío, un desplegable "Ver envíos anteriores" muestra el
  historial.
  - **Botón "Enviar" / "Reenviar documentos legales"**: aparece mientras el
    estado sea **Sin enviar** o **Pendiente de aceptación** (para poder
    insistir con un reenvío si el destinatario no llegó a aceptar). Cualquier
    usuario con permiso de actualizar sobre este módulo (no solo el
    superadministrador) puede enviarlos al correo registrado de la empresa
    desde aquí. El botón desaparece en cuanto el estado pasa a **Aceptado**:
    reenviar documentos ya aceptados sigue siendo exclusivo del
    superadministrador, desde **Configuración → Empresas del sistema**.
- **Otros Documentos Cargados**: los archivos que el superadministrador sube
  manualmente para la empresa (RUC, licencia, poder, contratos, etc.) desde
  **Configuración → Empresas del sistema**, con su tipo, descripción, fecha y
  un botón de descarga. Este bloque es solo de consulta: subir o eliminar
  estos archivos sigue siendo una acción exclusiva de Empresas del sistema.

## Operadoras de transporte comercial (placa en la factura)

La marca **"Operadora de transporte comercial (excepto taxis)"** la define el
**superadministrador** al crear o editar la empresa en **Configuración → Empresas
del sistema**. Si la empresa está marcada, la factura pide la **placa del
vehículo** como campo obligatorio y la incluye en el XML y el PDF, según la Ficha
Técnica SRI v2.34 (Anexo 25). No aplica para taxis ni para socios o accionistas
de taxis.

## Historial de cambios

- **1.6** — El botón de envío de documentos legales ahora también aparece en
  estado "Pendiente de aceptación" (antes solo en "Sin enviar"), como
  "Reenviar documentos legales", para poder insistir cuando el destinatario no
  llegó a aceptar. Sigue ocultándose una vez que el estado es "Aceptado".

- **1.5** — En la tarjeta "Documentos Legales" ahora se puede previsualizar el
  PDF de cada documento aunque todavía no se haya enviado (usa la versión
  vigente), y aparece un botón "Enviar documentos legales" mientras el estado
  sea "Sin enviar", disponible para cualquier usuario con permiso de
  actualizar sobre el módulo (antes solo se podía enviar desde Empresas del
  sistema).

- **1.4** — Se agrega, en Información General, la tarjeta de solo lectura
  "Documentos Legales" (estado del Acuerdo de Uso de Datos y el Contrato de
  Uso del Sistema, con enlaces a los PDF) y "Otros Documentos Cargados" (los
  archivos subidos manualmente desde Empresas del sistema).

- **1.3** — Se rediseña el correo de comprobantes autorizados (logo, datos del
  comprobante y firma de la empresa) y se documenta la pestaña Configuración
  Correo, incluida la nueva opción para enviar solo el contenido propio.

- **1.2** — Se documenta el tamaño exacto del logo en el PDF (81×25.4 mm) y el
  enlace para descargar el logo actualmente guardado en la pestaña
  Establecimientos.
- **1.1** — Se documenta la marca "Operadora de transporte comercial" (se define
  en Configuración → Empresas del sistema; placa del vehículo obligatoria en la
  factura, normativa SRI 2026).
- **1.0** — Versión inicial.
