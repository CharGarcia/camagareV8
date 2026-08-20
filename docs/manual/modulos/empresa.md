---
titulo: Empresa
resumen: Datos de la empresa, sus establecimientos y la configuración que rige a todos los módulos.
categoria: Configuración de empresa
ruta_modulo: modulos/empresa
tipo: modulo
visibilidad: admin
etiquetas: empresa, datos de la empresa, ruc, establecimiento, punto de emision, logo, ambiente, pruebas, produccion, configuracion, correo, email, smtp, envio de correos, cuerpo del correo, asunto, plantilla de correo, remitente, documentos legales, acuerdo de uso de datos, contrato de uso del sistema, aceptacion de documentos, documentos firmados, documentos cargados, archivos de la empresa, secuenciales, numeracion, tipos de documento, codDoc, eliminar secuencial, crear secuenciales, agregar todos los faltantes, facturas de reembolso, punto unico por empresa, punto inactivo
version: 1.11
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
presentan los ítems en la factura, el método de costeo del inventario, los
textos de los correos, entre otros.

Las **aprobaciones ya no se configuran aquí**. Todo lo que antes estaba en las
pestañas *Inventario* (aprobación de cargas) y *Pagos al Banco* (aprobación de
lotes de transferencia) se centralizó en el módulo **Aprobaciones**
(`modulos/aprobaciones-config`), junto con los demás procesos que requieren
autorización.

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

## Secuenciales por punto de emisión

En la pestaña **Secuenciales** se configura, por cada **punto de emisión**, el
número inicial de cada tipo de comprobante (factura, nota de crédito, ingreso,
egreso, pedido, etc.). El sistema detecta huecos desde ese número y nunca
asigna uno menor al configurado.

- **Agregar un tipo puntual**: el selector **"Agregar Tipo Documento"** solo
  ofrece los tipos que todavía faltan en ese punto, sea un punto nuevo (sin
  ningún secuencial) o uno que ya tiene varios. Sirve para volver a agregar
  un tipo que se eliminó, o para un tipo personalizado ("Otro").
- **Agregar todos los faltantes**: junto al botón "Agregar" hay un botón
  **"Agregar todos los faltantes"** que agrega de una vez todos los tipos que
  todavía faltan en ese punto, con el mismo resultado que agregarlos uno por
  uno desde el selector (mismas reglas de no duplicar ni mezclar codDoc).
- **Eliminar un tipo**: el ícono de papelera junto a cada tipo lo elimina
  (baja lógica) — solo ese tipo, no afecta a los demás. El sistema
  **bloquea la eliminación** si ese tipo ya tiene documentos emitidos en ese
  punto — hay que dejarlo como está, no se puede perder el control de una
  numeración ya usada.
- El nombre de cada tipo se edita con el ícono de lápiz, y debe coincidir
  **exacto** (mayúsculas y tildes incluidas) con el nombre que espera el
  módulo correspondiente; si no coincide, ese módulo no toma la numeración
  configurada aquí.
- **Tipos con un único punto por empresa**: "Facturas de reembolso" solo
  puede estar configurada en **un** punto de emisión de toda la empresa (el
  resto del sistema asume que existe uno solo). Si ya está en otro punto, no
  aparece en el selector "Agregar Tipo Documento" de este; para moverla, hay
  que **eliminarla** del punto actual antes de poder agregarla en otro.
- **Puntos inactivos**: la lista de puntos de la izquierda muestra **todos**
  los puntos de emisión, activos e inactivos (badge **Inactivo**) — por
  ejemplo, el punto dedicado a **Facturas de Reembolso** que se crea
  automáticamente (inactivo) al dar de alta la empresa. Un punto inactivo se
  puede configurar aquí igual que cualquier otro, incluso con **otros tipos
  de documento** además del que le dio origen, pero no podrá **emitir**
  documentos hasta activarlo en la pestaña **Puntos de Emisión**.

## Operadoras de transporte comercial (placa en la factura)

La marca **"Operadora de transporte comercial (excepto taxis)"** la define el
**superadministrador** al crear o editar la empresa en **Configuración → Empresas
del sistema**. Si la empresa está marcada, la factura pide la **placa del
vehículo** como campo obligatorio y la incluye en el XML y el PDF, según la Ficha
Técnica SRI v2.34 (Anexo 25). No aplica para taxis ni para socios o accionistas
de taxis.

## Historial de cambios

- **1.11** — Pestaña Secuenciales: se quita el botón "CREAR TODOS LOS TIPOS DE
  SECUENCIALES" que solo aparecía en un punto sin secuenciales — ahora el
  selector "Agregar Tipo Documento" y "Agregar todos los faltantes" están
  siempre disponibles, incluso en un punto nuevo. Se agrega la regla de
  **"un único punto por empresa"** para tipos como Facturas de reembolso: no
  se puede configurar en más de un punto de emisión a la vez. Corregido
  además un bug donde eliminar un tipo podía dejar en blanco (visualmente,
  sin tocar la base de datos) el resto de tipos del mismo punto, por una
  llamada a una función ya retirada.

- **1.10** — Nuevo botón **"Agregar todos los faltantes"** junto a "Agregar"
  en la pestaña Secuenciales: agrega de una vez todos los tipos de documento
  que aún faltan en el punto seleccionado (aunque ya tenga algunos
  configurados), respetando las mismas reglas de no duplicar ni mezclar
  tipos del mismo codDoc SRI.

- **1.9** — Corregido: la pestaña Secuenciales ocultaba los puntos de emisión
  **inactivos** (incluido el punto dedicado a Facturas de Reembolso, que
  nace inactivo al crear la empresa), así que no se podían ver ni
  configurar. Ahora se listan todos, marcados con el badge **Inactivo**, y se
  pueden configurar con cualquier tipo de documento — no solo el que les dio
  origen — antes de activarlos.

- **1.8** — Pestaña Secuenciales: el botón que crea los secuenciales de un
  punto nuevo ahora crea **todos los tipos de documento soportados** (antes
  solo 10 "estándar"), sin duplicar los que ya existan ni mezclar tipos que
  comparten codDoc SRI. Se agrega el ícono de papelera para **eliminar** un
  tipo de secuencial ya configurado, bloqueado cuando ese tipo ya tiene
  documentos emitidos en ese punto.

- **1.7** — Las aprobaciones dejan de configurarse aquí: se retira la pestaña
  **Pagos al Banco** y el bloque de aprobación de la pestaña **Inventario**.
  Ambas se centralizaron en el módulo **Aprobaciones**.
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
