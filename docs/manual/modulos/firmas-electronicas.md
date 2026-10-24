---
titulo: Firmas electrónicas
resumen: Venta de firmas electrónicas a clientes — el cliente llena sus datos, sube sus documentos y paga desde un enlace; la empresa factura y registra la solicitud en Uanataca.
categoria: Ventas
ruta_modulo: modulos/firmas_electronicas
tipo: modulo
visibilidad: admin
etiquetas: firma electronica, vender firmas, aviso de vencimiento al cliente, recordatorio de renovacion, renovar firma del cliente, firma por vencer, enlace de renovacion, api uanataca, uanacreditos, creditos uanataca, precompra, distribuidor uanataca, miembro de empresa, representante legal, solicitud de firma, tramite de firma, uanataca, enlace para el cliente, formulario de firma, link de firma, whatsapp, pagar firma, payphone, transferencia, comprobante de pago, confirmar correo, factura de la firma, registrar en uanataca, firma emitida, observada, corregir solicitud, enlace fijo, qr de firma, certificado, p12, caducada, por caducar, renovar firma, firma invalida, xades, anf, security data, buscar firma, filtros, firmas por facturar
version: 2.9
orden: 20
estado: activo
---

Este módulo sirve para **vender firmas electrónicas a sus clientes** y llevar el
trámite de principio a fin: el cliente solicita y paga por su cuenta desde un
enlace, el sistema genera la factura y el cobro, y usted registra la solicitud
en el portal de Uanataca con los datos ya listos para copiar.

## Qué es y para qué sirve

Cada fila del listado es la solicitud de firma de una persona. El trámite pasa
por estos estados:

| Estado | Qué significa | Quién actúa |
|--------|---------------|-------------|
| Borrador | El cliente registró sus datos pero aún no envía sus documentos | Cliente |
| Pendiente de pago | Datos y documentos completos; falta el pago | Cliente |
| Por registrar | Pagada y facturada; falta ingresarla en Uanataca | Empresa |
| En trámite | Ya se ingresó en Uanataca; el cliente valida su identidad con ellos | Uanataca / cliente |
| Observada | Usted pidió una corrección; el cliente debe corregir y reenviar | Cliente |
| Emitida | La firma fue emitida (se registra su vencimiento) | — |
| Cancelada | Se canceló con un motivo | — |

El **pago** tiene su propio estado: Pendiente, En revisión (el cliente subió un
comprobante), Confirmado o Rechazado.

## Requisitos previos

- Los productos de firma (por ejemplo *Firma 1 año*, *Firma 2 años*) deben estar
  en una **categoría cuyo nombre contenga «FIRMA»**, activos y con su tarifa de
  IVA. El precio que ve el cliente se calcula igual que la factura.
- Configurar el módulo con el **engranaje** del listado (ver la sección
  *Configuración*).
- Para cobrar con tarjeta: Payphone configurado en la empresa y una forma de
  cobro de tipo **PAYPHONE**.
- Para enviar el enlace por WhatsApp de forma automática: la plantilla rápida
  **Solicitud de Firma Electrónica** creada en *Plantillas WhatsApp* y aprobada
  por Meta. Sin ella, el sistema abre su WhatsApp con el mensaje listo.

## Tipos de firma que puede pedir el cliente

| Tipo | Para quién | Documentos además de cédula y selfie |
|------|-----------|--------------------------------------|
| Persona natural | Uso personal o con RUC de persona natural | — |
| Representante legal | Quien consta en el nombramiento de la empresa | RUC, constitución, nombramiento y su aceptación (PDF) |

Para representante legal se piden además el RUC, la razón social, el cargo y el
motivo (uso de la firma). El tipo **Miembro de empresa** ya no se ofrece en las
solicitudes nuevas; las firmas que ya lo tenían lo conservan (con sus datos de
departamento y del representante legal). Uanataca exige también la
**fecha de nacimiento**, el **sexo**, los **dos apellidos por separado** y, con
cédula, el **código dactilar**.

## Cómo se usa

1. Pulse **Enviar link** (ícono de avión de papel, junto a los botones de PDF y Excel), escriba el nombre del cliente y elija **correo**,
   **WhatsApp** o ambos. Mientras se envía, el cuadro muestra un indicador de
   carga; al terminar, un aviso le dice qué se envió y le muestra el enlace
   para copiarlo. Si WhatsApp no tiene aprobada la plantilla
   `solicitud_firma` en Meta, el mensaje no sale solo: el aviso ofrece
   **Abrir WhatsApp** con el texto listo (no se abre ninguna página sin que
   usted lo pida). Desde la ficha de una firma, en la pestaña
   **Trámite**, puede enviar el enlace de esa misma solicitud.
   Con el botón de **enlaces enviados** (ícono de cadena, junto a Excel) ve todos
   los enlaces con su estado y la firma que generaron, y puede copiarlos o
   cancelar los que aún no se usan.
2. El cliente, desde su enlace:
   - llena sus datos y elige la validez de la firma;
   - sube su cédula (frente y posterior) y una selfie con la cédula; si es
     representante legal, además el RUC, la constitución, el nombramiento y su
     aceptación (en PDF). Cada archivo se guarda al subirlo;
   - paga **con tarjeta (Payphone)** o **sube el comprobante de la
     transferencia** (ve los datos bancarios que usted configuró).
3. Si pagó con tarjeta, el sistema genera solo la factura (serie configurada),
   la envía al SRI y registra el cobro. Si subió un comprobante, aparece el
   aviso en la barra superior: abra la firma, pestaña **Trámite**, elija la
   cuenta donde llegó el dinero y pulse **Aprobar y facturar** (o
   **Rechazar** con el motivo; el cliente lo ve y puede subir otro).
4. Con la factura autorizada, el cliente recibe un correo con la factura (PDF y
   XML) y el botón **«Confirmo que recibí mi factura»**. Así se comprueba que el
   correo es suyo: ahí le llegará la activación de Uanataca.
5. **Con la API de Uanataca activa**, la solicitud se crea sola en Uanataca
   (si activó el envío automático) o con el botón **Enviar a Uanataca** de la
   pestaña Trámite; desde ahí el estado se actualiza solo (ver *Envío
   automático a Uanataca*). **Sin la API** (o como respaldo), siga el paso 6.
6. Cuando el cliente confirma, la firma aparece en el aviso **Firmas por
   atender**. Abra la pestaña **Uanataca**: cada dato tiene un botón para
   copiarlo y están los documentos para descargar. Ingrese la solicitud en el
   portal de Uanataca y pulse **Ya la registré en Uanataca** (puede anotar el
   número de solicitud).
7. Cuando Uanataca emite la firma, indique la **fecha de vencimiento** y pulse
   **Marcar emitida**. El cliente recibe un correo en cada paso y, con su
   mismo enlace, ve el historial de su solicitud.

Si algo está mal (una foto ilegible, un dato equivocado), use **Pedir
corrección al cliente**: la solicitud pasa a *Observada*, el cliente recibe el
motivo por correo, corrige desde su enlace y la reenvía.

## Configuración

Botón del **engranaje**, junto a los botones de PDF y Excel (requiere permiso de modificar). Tiene cuatro pestañas: **General**, **Uanataca** (la conexión con ese proveedor de firmas; ver *Envío automático a Uanataca*), **Avisos** (avisos de vencimiento al cliente) y **Vigencia**; un solo **Guardar** guarda todas:

| Campo | Qué significa |
|-------|---------------|
| Vigencia de cada firma (pestaña **Vigencia**) | Cuánto dura cada tipo de firma. Vacío = se toma del nombre del producto ("1 año", "2 años", "30 días", "6 meses"); úselo cuando el nombre no la diga o sea distinta. |
| Avisos de vencimiento (pestaña **Avisos**) | Encendidos o apagados (apagados de inicio) y los días antes del vencimiento en que se avisa por correo (30,15,5,0) y por WhatsApp (30,5,0). 0 = el mismo día. |
| Serie de facturación | Establecimiento y punto con que se facturan las firmas. Sin elegir, la primera serie activa. El IVA sigue la configuración de facturación de esa serie. |
| Usuario responsable | Dueño de las solicitudes que llegan por el enlace fijo y de sus facturas. |
| Pago con tarjeta / por transferencia | Qué formas de pago ve el cliente. Al menos una. |
| Datos para la transferencia | Banco, cuenta y titular que el cliente ve para transferir. |
| Enlace público fijo | Un enlace permanente (para su web, redes o un QR) donde cualquiera inicia su solicitud. Se puede regenerar; el anterior deja de funcionar. Máximo 5 solicitudes por hora desde una misma conexión. |

## Fecha de vencimiento automática

- Al elegir la **validez de la firma** en la ficha, la fecha de vencimiento se
  llena sola: **hoy + la vigencia** de esa firma. Si usted la escribe a mano ya
  no se cambia, y una firma que ya tenía fecha la conserva.
- Al confirmarse el pago, si la firma aún no tiene fecha, se pone la estimada.
- Al **marcar la firma emitida**, la fecha se vuelve a contar **desde ese día**
  (puede corregirla antes de confirmar). Con la API de Uanataca se usa la
  vigencia que informa Uanataca; si no la informa, se cuenta desde la fecha de
  aprobación.
- Meses y años caen el mismo día del mes o, si ese día no existe, el último
  (31-01 + 1 mes = 28-02).

Esta fecha es la que usan los avisos de vencimiento.

## Avisos de vencimiento al cliente

Con los avisos encendidos (engranaje, pestaña **Avisos**), el sistema recuerda al titular que su
firma va a vencer, para que la renueve a tiempo con usted.

- **Cuándo**: por defecto, por correo 30, 15 y 5 días antes y el mismo día; por
  WhatsApp 30 y 5 días antes y el mismo día. Se revisa una vez al día desde las
  08:00.
- **A quién**: solo firmas **emitidas**, con fecha de vencimiento. Si el titular
  ya renovó (registró otra firma con la misma identificación y la pagó), no se
  le avisa más.
- **Una sola vez**: cada aviso (etapa y canal) sale una sola vez, aunque el
  proceso corra varias veces. Si un día el servidor no lo ejecutó, al siguiente
  sale el aviso de la etapa que corresponde ese día, no uno atrasado.
- **Enlace de renovación**: cada aviso lleva un enlace personal que abre el
  formulario con los datos del cliente ya llenos y el mismo tipo de firma; el
  cliente vuelve a subir sus documentos y paga. Esa solicitud aparece en el
  listado como una firma nueva en *Borrador*.
- **WhatsApp**: necesita la plantilla rápida **Renovación Firma Electrónica**
  (`renovacion_firma_electronica`) aprobada en Meta. Con la versión de 3
  variables lleva el enlace; una plantilla aprobada de 2 variables sigue
  funcionando, sin el enlace. Sin plantilla aprobada, solo sale el correo.
- **En la ficha**, pestaña **Trámite**: los avisos enviados (o el motivo si no
  salieron) y el botón **Enviar aviso ahora** para mandarlo a mano.
- Para ver las firmas por vencer use el filtro **Vigencia → Por vencer** del
  listado.

## Envío automático a Uanataca (API Precompra)

Si su empresa es distribuidora de Uanataca, el sistema crea la solicitud
directamente en Uanataca con los datos y documentos del cliente, sin copiar
nada a mano. Cada solicitud consume **Uanacréditos** (saldo prepago que se
compra en el portal de distribuidores de Uanataca).

**Puesta en marcha** (engranaje del listado, pestaña **Uanataca**):

1. Active **Enviar las solicitudes a Uanataca por API**, elija el ambiente
   (**Pruebas** mientras valida con Uanataca, luego **Producción**) y escriba el
   usuario y la clave de distribuidor que le entregó Uanataca. La clave se
   guarda cifrada; si deja el campo vacío se conserva la anterior.
2. Pulse **Probar conexión**: muestra el saldo de Uanacréditos.
3. En la sección **Productos de Uanataca**, al pie de la misma pestaña,
   enlace cada firma que vende con su producto de Uanataca (por ejemplo
   *Firma 1 año* → *Persona Natural Archivo 1 año*) y pulse **Guardar
   productos**. El catálogo se consulta al abrir la pestaña (botón ↻ para
   volver a pedirlo). Una firma sin enlace no se puede enviar.
4. Decida si el envío es **automático** (al confirmarse el pago) y si debe
   **esperar a que el cliente confirme su correo** (recomendado: ahí le llega
   la activación de Uanataca).

**Cómo se refleja el estado de Uanataca** (se consulta cada pocos minutos;
también con **Consultar estado ahora** en la pestaña Trámite):

| Estado en Uanataca | Qué pasa en el sistema |
|--------------------|------------------------|
| Nueva / En validación / Aprobada | Trámite *En trámite* |
| Corrección solicitada o Rechazada | Pasa a *Observada*; el cliente recibe por correo el comentario de Uanataca |
| Corregida | Vuelve a *En trámite* |
| Emitida | Pasa a *Emitida* con la fecha de vencimiento que informa Uanataca |
| Cancelada | Queda en el historial interno para que usted decida |

Una solicitud ya creada en Uanataca **no se vuelve a enviar**: si el cliente
corrige algo después, actualice los documentos en el portal de distribuidores
de Uanataca. Si una caída de conexión impide guardar la respuesta, al
reintentar el sistema encuentra la solicitud existente y la enlaza en vez de
crear otra. Las fotos de más de 1 MB se reducen antes de enviarlas.

## Permisos

- **Ver**: listado, fichas y documentos, incluido el **PDF de la factura** de
  la firma (botón en la pestaña Facturación: ver, imprimir o descargar), sin
  necesitar permiso en Facturas de Venta.
- **Crear**: registrar firmas a mano, enviar enlaces y generar la factura en
  borrador desde la pestaña Facturación.
- **Modificar**: aprobar o rechazar pagos, avanzar el trámite, reenviar el
  correo de confirmación y la configuración del módulo.
- Sin **acceso total**, el usuario ve solo las solicitudes que él creó o cuyos
  enlaces envió; las del enlace fijo pertenecen al usuario responsable.

## Reglas de negocio

- Un enlace genera **una sola** solicitud; un pago genera **una sola** factura y
  un solo cobro aunque se repita el clic o Payphone avise dos veces.
- El cliente puede corregir sus datos y documentos mientras la solicitud está
  en *Borrador*, *Pendiente de pago* u *Observada*. Después de pagar no puede
  cambiar la validez elegida.
- El precio cobrado por Payphone es el total de la factura (IVA según la serie
  configurada).
- La **dirección** del titular es obligatoria y debe tener al menos 10
  caracteres (calle, número, sector), tanto en el formulario del cliente como
  en la ficha.
- Una firma con factura activa (borrador o autorizada) **no se elimina**. Mientras
  **no esté emitida**, sí se pueden corregir los datos del titular, el **tipo de
  firma**, el estado y las observaciones. La factura no cambia: queda con el
  tipo y el valor con que se hizo (si el precio del nuevo tipo es distinto,
  ajústelo con una nota de crédito o anulando la factura). El estado del pago y
  las pestañas Pago y Facturación quedan como se facturaron. Una vez
  **emitida**, la ficha queda de solo lectura hasta que se elimine o anule la
  factura.
- Los documentos se validan por su contenido real: cédula y selfie deben ser
  fotos (JPG, PNG, WEBP); los de la empresa, PDF; máximo 5 MB.
- Si el SRI no responde al momento del pago, la factura queda en borrador y el
  sistema la reintenta automáticamente (hasta 3 días); el correo de
  confirmación sale cuando queda autorizada.

## Integraciones con otros módulos

- **Facturas de Venta**: cada pago confirmado crea la factura (forma de pago
  SRI 19 para tarjeta, 20 para transferencia) y el **cliente** si no existía.
- **Ingresos**: el cobro de la factura queda registrado en la forma de cobro
  elegida (Payphone o la cuenta bancaria de la transferencia).
- **Plantillas WhatsApp**: plantilla rápida *Solicitud de Firma Electrónica*.
- **Avisos de la barra superior**: ícono de pluma con las firmas por atender
  (comprobantes en revisión y firmas listas para registrar en Uanataca).

## Errores frecuentes

- **El cliente no ve el pago con tarjeta**: Payphone no está configurado o
  activo en la empresa, falta una forma de cobro tipo PAYPHONE o está apagado
  en la configuración del módulo.
- **«Configure el usuario responsable del módulo de firmas»**: guarde la
  configuración (engranaje) eligiendo un usuario.
- **El cliente no recibió el correo con la factura**: revise que la factura
  esté autorizada y el correo de la empresa configurado; en la pestaña Trámite
  use **Reenviar factura y confirmación** o, si el cliente lo confirmó por
  teléfono, **Marcar correo confirmado**.
- **«El servidor no respondió a tiempo» al enviar el enlace**: el servidor de
  correo tardó demasiado. Abra **Enlaces enviados**: si el enlace aparece, ya se
  creó (cópielo desde ahí o reenvíelo); si no, vuelva a intentar.
- **«Este enlace ya no está disponible»**: el enlace venció sin usarse (15
  días) o la empresa está inactiva. Envíe uno nuevo.
- **«Uanataca rechazó el usuario o la clave de la API»**: revise las
  credenciales y el ambiente (las de pruebas no sirven en producción).
- **«Uanataca no permite a este usuario de API crear solicitudes (HTTP 403)»**:
  el usuario y la clave son correctos (inicia sesión y ve el catálogo), pero su
  cuenta de distribuidor no tiene habilitada la creación de solicitudes en ese
  ambiente. Pida a Uanataca que la habilite, o revise en la pestaña
  **Uanataca** del engranaje que el ambiente (Pruebas/Producción) corresponda a
  esas credenciales.
- **«Falta para enviar a Uanataca: …»**: complete en la ficha los datos o
  documentos indicados, o enlace el producto en *Productos de Uanataca*.
- **El envío falla por saldo o por un dato que Uanataca no acepta**: el
  mensaje de Uanataca queda en la pestaña Trámite (*Último intento*); el envío
  automático se reintenta cada hora. Mientras tanto puede registrar la
  solicitud a mano en el portal y pulsar **Ya la registré en Uanataca (manual)**.
- **El WhatsApp se abrió en mi teléfono en lugar de enviarse solo**: la
  plantilla *solicitud_firma* no está aprobada en Meta; el mensaje sale desde su
  WhatsApp.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la firma: nombres y
apellidos, identificación, teléfono, correo, vencimiento y fecha de registro
(como *29-04-2028*). Además busca en el RUC y nombre de la empresa del titular,
los datos de facturación (nombre e identificación), el número de la factura,
las observaciones y el usuario que registró la firma. Las columnas **Tipo
Firma**, **Pago**, **Factura** y **Estado** no entran en la búsqueda libre: para
filtrar por ellas use la ventana de filtros. Por seguridad, el código dactilar,
la fecha de nacimiento, la dirección y los archivos adjuntos no se buscan.
Puede escribir varias palabras en cualquier orden y no importan mayúsculas ni
tildes. Para limpiar, borre el texto o pulse Escape en el cuadro. Mientras
busca, aparece un **círculo girando** al final del cuadro y la tabla se ve
atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento.
La ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Firma | Fecha de registro (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado del trámite, estado del pago, factura (por facturar, borrador, facturado, anulado), tipo de firma, usuario que registró, fecha de vencimiento, vigencia (vigente, por vencer en 30 días, vencida, sin fecha) |
| Titular | Nombres, apellidos, identificación, tipo de identificación, tipo de persona, con o sin RUC, teléfono, correo, empresa (RUC o nombre) |

Los selectores *Tipo de firma* y *Usuario que registró* listan solo lo que ya
aparece en las firmas registradas.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Firma electrónica de la propia empresa

Esta sección trata del certificado con que **su empresa** firma sus propios
comprobantes (se carga en la ficha de la empresa), no de las firmas que vende.

### Vigencia

El certificado **caduca**. Cuando vence, todos los envíos al SRI empiezan a
fallar de golpe, y el mensaje de error no siempre lo dice con claridad.

Conviene anotar la fecha de caducidad y renovar antes de que llegue: la
renovación con la entidad certificadora no es inmediata, y mientras tanto no se
puede facturar electrónicamente.

### Aviso de caducidad: ícono del navbar y correo a la empresa

El sistema avisa de dos formas que la firma activa está por vencer:

- **Ícono en la barra superior** (junto a los demás avisos): aparece cuando la
  firma activa caduca en **5 días o menos**, cuando ya caducó, o cuando la
  empresa no tiene ninguna firma activa cargada. Al hacer clic lleva a la ficha
  de la empresa. Solo lo ve quien entra al sistema.
- **Correo automático a la empresa**: se envía **un día antes** de la fecha de
  caducidad al correo registrado en la ficha de la empresa (pestaña *Datos
  generales*, campo **Correo**). Llega aunque nadie haya iniciado sesión.

Cómo funciona el correo:

| Regla | Detalle |
|-------|---------|
| Cuándo | Cuando a la firma activa le queda **1 día o menos** (incluye el día de caducidad). |
| A quién | Al correo de la ficha de la empresa. Si está vacío o no es válido, no se envía y queda una traza en el log del servidor. |
| Cuántas veces | **Una sola vez por firma.** El envío queda en la bitácora del sistema (tabla *Firma electrónica de la empresa*, acción *Aviso caducidad correo*) y esa marca evita repetirlo. |
| Si ya caducó | Si la firma venció hace **hasta 7 días** y nunca se avisó (por ejemplo, el correo del servidor estaba caído), se envía igual indicando desde cuándo está caducada. Firmas vencidas hace más tiempo no generan correo. |
| Hora | La revisión corre una vez al día, a partir de las **06:00**, sobre el cron del servidor. |
| Firma nueva | Al cargar y activar la firma renovada, el aviso se reinicia: la nueva firma recibirá su propio correo cuando le toque. |

El correo indica la empresa, el RUC, la fecha de caducidad y qué hacer
(renovar con la entidad certificadora y cargar el nuevo `.p12` como firma
activa). No es configurable por empresa y no requiere programar nada.

### El SRI responde "FIRMA INVALIDA"

Cuando el SRI devuelve:

> **[ERROR] FIRMA INVALIDA** — La firma es invalida [Firma inválida. La
> información sobre el certificado de firma no se ajusta a XAdES.]

quiere decir que el comprobante **sí se firmó**, pero los datos del certificado
que viajan dentro de la firma no le cuadran al SRI con el certificado usado.

Que el error aparezca con una firma y no con otra es normal: depende de **quién
emitió** el certificado. Cada entidad certificadora (ANF, UANATACA, Security
Data, Banco Central) escribe internamente el nombre de su autoridad emisora de
una forma distinta, y el SRI lo compara carácter por carácter.

Si aparece este error con una firma recién cargada, avise a soporte indicando
**qué entidad emitió el certificado**. No es un problema de la clave ni del
archivo, y volver a cargar la firma no lo soluciona.

### Errores frecuentes de la firma de la empresa

- **Todos los comprobantes empiezan a fallar el mismo día**: lo primero a revisar
  es si la firma caducó.
- **"La cédula debe tener exactamente 10 dígitos numéricos"**: revise el número
  o cambie el tipo de identificación.
- **El sistema no acepta el archivo**: compruebe que la clave sea la correcta y
  que el archivo no esté dañado.
- **"FIRMA INVALIDA / no se ajusta a XAdES"**: ver la sección anterior. La firma
  y la clave están bien; es la forma en que el sistema describe a la entidad
  emisora.

- **No llegó el correo de aviso de caducidad**: revise que la ficha de la
  empresa tenga un correo válido en *Datos generales* y que el correo de
  *notificaciones* del sistema esté configurado. El aviso se envía una sola vez
  por firma; si ya se envió, no se repite aunque la firma siga caducada.

## Historial de cambios

- **2.9** — Pestaña Facturación: el botón «Ver en Facturas de Venta» se
  reemplaza por **PDF de la factura**. Tipo de persona: solo Persona natural y
  Representante legal (Miembro de empresa queda en las firmas que ya lo tenían).
  Se quita el botón «Abrir Uanataca». Mensajes con SweetAlert.
- **2.8** — Una firma con factura activa se puede editar mientras no esté
  emitida (datos del titular, tipo de firma, estado, observaciones); el pago y
  la facturación se conservan y la factura no se modifica. Se quitan «Otro
  teléfono» y «Correo alterno» del formulario; la dirección pide mínimo 10
  caracteres. Mensaje claro al crear en Uanataca con HTTP 403.
- **2.7** — El WhatsApp del enlace llega con un botón **Completar solicitud**
  cuando la plantilla `solicitud_firma` lo tiene (ver el manual de Plantillas de
  WhatsApp); las plantillas aprobadas sin botón siguen funcionando.
- **2.6** — Enviar enlace al cliente por correo ya no se queda «Enviando…»: el
  correo tiene un tiempo máximo de espera y, si el servidor no responde, se
  muestra un aviso. El botón pasa a llamarse **Enviar link**, el envío muestra
  un indicador de carga y el resultado sale en un aviso; WhatsApp ya no abre
  otra página por su cuenta.
- **2.5** — La configuración de la API de Uanataca pasa de la pestaña General a
  su propia pestaña **Uanataca** en el engranaje, que muestra además la tabla
  de **Productos de Uanataca** (antes en un modal aparte).
- **2.4** — En la ficha, el listado y los filtros, «Caducidad» pasa a llamarse **Vencimiento**.
- **2.3** — Avisos de vencimiento al cliente por correo y WhatsApp (días
  configurables, una sola vez por etapa) con enlace personal de renovación con
  los datos ya llenos; aviso manual desde la pestaña Trámite.
- **2.2** — La fecha de caducidad se autocompleta con la vigencia de cada firma
  (deducida del nombre del producto o ajustada en la configuración) y se
  recalcula al emitir.
- **2.1** — Integración con la API Precompra de Uanataca: credenciales por
  empresa, enlace de productos, envío de la solicitud con sus documentos
  (automático o con botón), consulta periódica del estado y saldo de
  Uanacréditos; el registro manual queda como respaldo. Nuevo tipo **Miembro de
  empresa**; los apellidos se piden por separado y la fecha de nacimiento, el
  sexo y el código dactilar pasan a ser obligatorios en el formulario del
  cliente.
- **2.0** — Autoservicio del cliente: enlace por correo y WhatsApp (y enlace
  público fijo), formulario por pasos con documentos que se guardan al subir,
  pago con Payphone o comprobante de transferencia, factura y cobro automáticos,
  correo con la factura para confirmar el correo, pestañas **Trámite** y
  **Uanataca** (datos para copiar), estados nuevos (Borrador, Por registrar,
  Observada), historial visible para el cliente, aviso *Firmas por atender* y
  configuración del módulo. La factura usa la serie configurada y el IVA de
  esa serie. Se corrigió que una factura autorizada no bloqueaba la edición.

- **1.4** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas de la firma (salvo Tipo Firma,
  Pago, Factura y Estado) y en empresa, datos de facturación, número de factura,
  observaciones y usuario. Los filtros pasan a una **ventana propia** (botón del
  embudo, se aplican con *Aplicar*) y **ahora sí se aplican** (antes los filtros
  del buscador se ignoraban); criterios nuevos: factura, tipo de firma y usuario
  como listas, fechas de registro y caducidad, vigencia, tipo de persona, con
  RUC y empresa. Los filtros activos se ven como etiquetas dentro del cuadro.
  Nueva sección *Buscar y filtrar el listado*.
- **1.3** — Nuevo aviso automático por correo: un día antes de la caducidad
  de la firma activa (o hasta 7 días después si nunca se avisó) se envía un
  correo al correo registrado en la ficha de la empresa. Una sola vez por
  firma; queda registrado en la bitácora del sistema.
- **1.2** — Se corrigió el número de serie del certificado en la firma. Los
  certificados con número de serie largo (ANF, entre otros) se firmaban con un
  valor mal calculado y el SRI los rechazaba; los de serie corta (Lazzate,
  Security Data) no se veían afectados, por eso el fallo parecía depender de la
  empresa.
- **1.1** — Se corrigió el rechazo "FIRMA INVALIDA / la información sobre el
  certificado de firma no se ajusta a XAdES" con certificados de ANF Ecuador
  (y de cualquier entidad cuyo emisor incluya atributos poco comunes). El
  nombre de la autoridad emisora ahora se lee del propio certificado.
- **1.0** — Versión inicial.
