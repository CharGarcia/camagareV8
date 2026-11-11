---
titulo: Firmas electrónicas
resumen: Venta de firmas electrónicas a clientes — el cliente llena sus datos, sube sus documentos y paga desde un enlace; la empresa factura y registra la solicitud en Uanataca.
categoria: Ventas
ruta_modulo: modulos/firmas_electronicas
tipo: modulo
visibilidad: admin
etiquetas: firma electronica, comision por firma, vendedor de la firma, qr por vendedor, enlace por vendedor, negocio que vendio la firma, donde se genero la firma, boton renovar, renovar firma antes de que venza, nueva solicitud con estos datos, volver a pedir firma, reutilizar documentos, segunda firma del mismo cliente, solicitar una nueva firma, mismo enlace, firma para persona natural, firma para representante legal, tipo de persona de la firma, vender firmas, aviso de vencimiento al cliente, recordatorio de renovacion, renovar firma del cliente, firma por vencer, enlace de renovacion, api uanataca, uanacreditos, creditos uanataca, precompra, distribuidor uanataca, miembro de empresa, representante legal, solicitud de firma, tramite de firma, uanataca, enlace para el cliente, formulario de firma, link de firma, whatsapp, pagar firma, payphone, transferencia, comprobante de pago, confirmar correo, factura de la firma, registrar en uanataca, firma emitida, observada, corregir solicitud, enlace fijo, qr de firma, imprimir qr, cartel con qr, codigo qr, editar solicitud el cliente, corregir datos desde el enlace, certificado, p12, caducada, por caducar, renovar firma, firma invalida, xades, anf, security data, buscar firma, filtros, firmas por facturar
version: 3.18
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
| Aprobada | Uanataca aprobó la solicitud; falta solo la emisión. Ya no se edita | Uanataca |
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

Para representante legal se piden además el RUC, la razón social y el cargo. Al
escribir los 13 dígitos del RUC de la empresa (en el formulario del cliente y en
la ficha) el sistema consulta al SRI y llena la **razón social**; el cargo viene
lleno con **Representante legal** (se puede cambiar). El
motivo de uso que exige Uanataca ya no se escribe (ni el cliente ni en la ficha): se envía
«Facturación electrónica y firma de documentos» (las firmas antiguas conservan el que tenían
registrado). El formulario del cliente no pide observaciones; para persona natural
ofrece el interruptor **Firma electrónica con RUC**. El tipo **Miembro de empresa** ya no se ofrece en las
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
   Cuando el cliente termina de subir sus documentos, llega un **correo al correo
   de la empresa** (el de la ficha de la empresa) con el asunto *Nueva solicitud
   de firma electrónica*: trae el titular, la identificación, el tipo de firma, el
   estado del pago y el botón **Revisar solicitud**. Se envía una sola vez por
   solicitud y queda anotado en el historial interno de la firma. Si la empresa no
   tiene correo registrado, no se envía. También llega un correo (*Solicitud de
   firma electrónica corregida*, con lo que se le pidió corregir) cada vez que el
   cliente envía la corrección de una solicitud **observada**.
3. Si pagó con tarjeta, el sistema genera solo la factura (serie configurada),
   la envía al SRI y registra el cobro. Si subió un comprobante, aparece el
   aviso en la barra superior: abra la firma, pestaña **Trámite**, elija la
   cuenta donde llegó el dinero y pulse **Aprobar y facturar** (o
   **Rechazar** con el motivo; el cliente lo ve y puede subir otro).
   La imagen del comprobante se ve directamente en la pestaña **Pago** (un PDF se
   muestra en un visor); al tocarla se abre en tamaño completo. Se ve aunque la
   forma de pago aún no esté elegida.
   Si la conexión con Uanataca está activa, el recuadro de registrar el pago muestra
   el **saldo de Uanacréditos** (en verde si hay saldo, en rojo si está en cero), para
   saber antes de aprobar si alcanza para registrar la firma; el botón de flechas lo
   vuelve a consultar.
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

## Cómo avanza el trámite: siguiente paso y estados

Al abrir una firma, debajo de la fila de pasos (verde = cumplido, rojo = falta)
aparece el recuadro **Siguiente paso**: dice qué falta para que el trámite
avance, **quién debe hacerlo** (cliente, usted o Uanataca) y trae el enlace
**Ir a Trámite** (o **Ir a Uanataca**) hacia la pestaña donde se hace.

| Estado | Qué hace avanzar el trámite | Pasa a |
|--------|-----------------------------|--------|
| Borrador | El cliente sube y confirma sus documentos desde su enlace. Si usted los cargó en la ficha, cambie **Estado Trámite** a *Pendiente de pago* y guarde | Pendiente de pago |
| Pendiente de pago | El cliente paga desde su enlace, o usted registra el pago en **Trámite → Aprobar y facturar** (genera la factura y el cobro) | Por registrar |
| Por registrar | **Enviar a Uanataca** (API) o **Ya la registré en Uanataca** (manual), en la pestaña Trámite | En trámite |
| En trámite | Uanataca valida al cliente y aprueba (con la API se actualiza solo) | Aprobada |
| Aprobada / En trámite | **Marcar emitida** con la fecha de vencimiento (con la API se actualiza solo) | Emitida |
| Observada | El cliente corrige desde su enlace y pulsa **Enviar corrección** | Pendiente de pago o Por registrar |

**El estado avanza solo al cumplir cada paso.** Cambiar el **Estado Trámite** a
mano en la ficha solo cambia la etiqueta: **no registra el pago, no genera la
factura ni envía nada a Uanataca**. Si adelanta el estado sin el pago, el sistema
avisa al elegirlo y el recuadro *Siguiente paso* queda en rojo indicando que el
pago y la factura siguen pendientes; regístrelos en la pestaña **Trámite**.

### El pago dice «Confirmado» pero no se generó el cobro

Poner el **Estado Pago** en *Confirmado* a mano en la ficha tampoco ejecuta nada:
no crea el cobro en Ingresos ni la factura. En ese caso la pestaña Trámite muestra
en rojo el recuadro **Falta registrar el cobro**: elija la forma de cobro, la
referencia y pulse **Registrar cobro** (si la firma ya tiene factura, se cobra esa
misma; no se crea otra) o **Aprobar y facturar** (si aún no tiene factura).

Si el cobro de esa factura ya se registró directamente en el módulo **Ingresos**,
no lo registre otra vez desde aquí: quedaría duplicado.

### No aparece «Consultar estado ahora» en una firma En trámite

Ese botón solo existe cuando la solicitud se **envió a Uanataca por API**. Si la
firma llegó a *En trámite* porque se registró a mano en el portal (o porque se
cambió el estado a mano), el sistema no tiene qué consultar y la pestaña Trámite
muestra el recuadro *Esta firma no tiene una solicitud enviada a Uanataca por API*:

- **Aún no la registró en Uanataca**: pulse **Regresar a «Por registrar»**, luego
  **Guardar**, y en Trámite use **Enviar a Uanataca**. Desde ahí el estado se
  actualiza solo.
- **Ya la registró a mano en el portal**: el estado se lleva a mano; cuando
  Uanataca la emita, indique el vencimiento y pulse **Marcar emitida**.

En la lista de pasos, *Aprobada por Uanataca* con la flecha azul es el paso que
se está **esperando**, no uno cumplido (los cumplidos llevan el visto verde).

### El botón «Reenviar factura» sigue visible

Se muestra mientras el cliente no confirme su correo. La nota sobre el botón
indica a qué correo y cuándo se envió la factura: **reenviarla es opcional**, solo
si el cliente no la recibió. Lo que falta es que el cliente pulse *«Confirmo que
recibí mi factura»*, o que usted use **Marcar correo confirmado**.

## Configuración

Botón del **engranaje**, junto a los botones de PDF y Excel (requiere permiso de modificar). Tiene seis pestañas: **General**, **Uanataca** (la conexión con ese proveedor de firmas; ver *Envío automático a Uanataca*), **Avisos** (avisos de vencimiento al cliente), **Vigencia**, **Tipo de persona** y **Vendedores** (un enlace y un QR por vendedor; ver *Enlaces y QR por vendedor*, sus botones actúan al instante); un solo **Guardar** guarda las demás:

| Campo | Qué significa |
|-------|---------------|
| Vigencia de cada firma (pestaña **Vigencia**) | Cuánto dura cada tipo de firma. Vacío = se toma del nombre del producto ("1 año", "2 años", "30 días", "6 meses"); úselo cuando el nombre no la diga o sea distinta. |
| Para quién es cada firma (pestaña **Tipo de persona**) | *Persona natural*, *Representante legal* o *Ambos* (por defecto). El cliente y la ficha solo muestran las firmas del tipo de persona elegido, y el sistema no deja guardar una combinación que no cuadre. |
| Avisos de vencimiento (pestaña **Avisos**) | Encendidos o apagados (apagados de inicio) y los días antes del vencimiento en que se avisa por correo (30,15,5,0) y por WhatsApp (30,5,0). 0 = el mismo día. |
| Serie de facturación | Establecimiento y punto con que se facturan las firmas. Sin elegir, la primera serie activa. El IVA sigue la configuración de facturación de esa serie. |
| Usuario responsable | Dueño de las solicitudes que llegan por el enlace fijo y de sus facturas. |
| Pago con tarjeta / por transferencia | Qué formas de pago ve el cliente. Al menos una. |
| Datos para la transferencia | Banco, cuenta y titular que el cliente ve para transferir. |
| Enlace público fijo | Un enlace permanente (para su web, redes o un QR) donde cualquiera inicia su solicitud. Se puede regenerar; el anterior deja de funcionar. Máximo 5 solicitudes por hora desde una misma conexión. |

### Imprimir el QR del enlace fijo

Junto al enlace público fijo está el botón **Imprimir QR**. Pide el **título del
cartel** (viene con *Firmas electrónicas desde aquí, rápido*; puede cambiarlo y
el navegador lo recuerda) y abre una hoja lista para imprimir con el título, el
código QR y el enlace escrito debajo. Quien lo escanea con la cámara del
celular llega al formulario para iniciar su solicitud.

- El enlace debe existir: active **Enlace público fijo** y pulse **Guardar**.
- Si **regenera** el enlace, los carteles ya impresos dejan de funcionar:
  imprímalos de nuevo.
- El navegador debe permitir ventanas emergentes para abrir la hoja.

## Enlaces y QR por vendedor (comisiones)

Sirve para saber **qué negocio o vendedor generó cada firma** y pagarle su
comisión. Cada vendedor tiene su propio enlace público y su propio QR.

1. Registre al negocio o persona en el módulo **Vendedores** (debe estar activo).
2. En el engranaje, pestaña **Vendedores**, pulse **Generar enlace** en su fila.
   Desde ahí puede **copiar** el enlace, **imprimir su QR** (con el título del
   cartel), **generar uno nuevo** o **quitarlo**.
3. Entregue el cartel con el QR al negocio. Toda solicitud que entre por ese
   enlace queda **a nombre de ese vendedor**, aunque el cliente la termine y la
   pague días después desde otro lugar.

También puede elegir el **Vendedor** al usar **Enviar link** (campo opcional): la
firma que nazca de ese enlace queda a su nombre.

| Dónde se ve | Qué muestra |
|-------------|-------------|
| Listado de firmas | Columna **Vendedor** (se puede ordenar y ocultar). El buscador encuentra por el nombre del vendedor, y `vendedor:nombre` filtra solo por él |
| Excel del listado | Columna **Vendedor**, para sumar las firmas de cada uno |
| Enlaces enviados | El vendedor de cada enlace, bajo el destinatario |
| Factura de la firma | Sale con ese **vendedor**: la comisión se calcula con los reportes de ventas por vendedor, solo sobre firmas pagadas |

- El enlace público fijo de la pestaña General sigue funcionando: sus solicitudes
  quedan **sin vendedor**.
- Una **renovación** (botón Renovar, *Nueva solicitud con estos datos*, el enlace
  de los avisos de vencimiento o **Solicitar una nueva firma** del cliente)
  conserva el vendedor de la firma anterior.
- Si **regenera o quita** el enlace de un vendedor, sus carteles impresos dejan de
  funcionar; las firmas ya registradas conservan su vendedor.
- Si el vendedor se desactiva o se elimina, su enlace deja de funcionar y las
  facturas nuevas ya no salen a su nombre.
- Si a la factura se le cambia el vendedor en Facturas de Venta, el listado
  muestra el de la factura.
- El límite de 5 solicitudes por hora desde una misma conexión también aplica a
  estos enlaces (por ejemplo, varios clientes usando el wifi del mismo local).
- Requiere `database/20261110_firmas_enlaces_vendedor.sql`.

## Cómo corrige el cliente su solicitud

El cliente edita con **el mismo enlace** que recibió, sin pedir otro:

| Estado | Qué puede cambiar el cliente |
|--------|------------------------------|
| Borrador / Pendiente de pago | Botón **Editar** en la tarjeta *Tus datos* (abre el formulario; al final **Guardar cambios**) y tocar cualquier documento para reemplazarlo |
| Observada | El formulario aparece abierto con el motivo; corrige y pulsa **Enviar corrección** |
| Por registrar (pagada, aún sin enviar a Uanataca) | Sus datos del titular (botón **Editar**) y sus documentos (los reemplaza; no puede dejarlos vacíos). **No** puede cambiar la validez de la firma ni los datos de la factura |
| En trámite en adelante | Nada: solo ve el estado y el historial. Para corregir, use **Pedir corrección al cliente** (pestaña Trámite) mientras no esté aprobada ni emitida |

Cuando el cliente cambia algo **después de pagar**:

- La empresa recibe el correo *Solicitud de firma modificada por el cliente* (uno
  cada 10 minutos como máximo por firma, aunque cambie varios documentos seguidos) y
  cada cambio queda en el historial interno de la ficha. Revise la ficha antes de
  registrar la firma en Uanataca.
- Si cambia su **correo**, la confirmación anterior se anula: se le reenvía la
  factura al correo nuevo con el botón **«Confirmo que recibí mi factura»** y el
  enlace del correo anterior deja de servir.
- La edición se cierra sola en cuanto la solicitud se envía a Uanataca (por API)
  o usted pulsa **Ya la registré en Uanataca**. Con el envío automático activo,
  ese margen puede ser de pocos minutos.

**Cada firma tiene su propio enlace.** Un enlace corresponde a una sola solicitud:
si la misma persona pide dos firmas (dos veces desde el enlace fijo o el QR, o con
**Solicitar una nueva firma**), cada una queda con su enlace y se edita desde el
suyo. El enlace de cada solicitud llega en los correos de esa solicitud y usted
puede copiarlo desde **Enlaces enviados**.

## Fecha de vencimiento automática

- Al elegir la **validez de la firma** en la ficha, la fecha de vencimiento se
  llena sola: **hoy + la vigencia** de esa firma. Si usted la escribe a mano ya
  no se cambia, y una firma que ya tenía fecha la conserva.
- Lo mismo cuando el **cliente llena la solicitud desde el enlace** (o se crea una
  renovación): la fecha queda puesta con la vigencia de la validez que eligió, y si
  después cambia la validez desde el enlace, la fecha se vuelve a calcular. Al abrir
  una ficha que llegó sin fecha, se completa sola y queda guardada al pulsar Guardar.
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
  formulario con los datos del cliente ya llenos, el mismo tipo de firma y sus
  **documentos ya cargados** (ver *Nueva solicitud con los datos y documentos del
  cliente*); el cliente revisa, cambia lo que haga falta y paga. Esa solicitud
  aparece en el listado como una firma nueva en *Borrador*.
- **WhatsApp**: necesita la plantilla rápida **Renovación Firma Electrónica**
  (`renovacion_firma_electronica`) aprobada en Meta. Con la versión de 3
  variables lleva el enlace; una plantilla aprobada de 2 variables sigue
  funcionando, sin el enlace. Sin plantilla aprobada, solo sale el correo.
- **En la ficha**, pestaña **Trámite**: los avisos enviados (o el motivo si no
  salieron) y el botón **Enviar aviso ahora** para mandarlo a mano.
- Para ver las firmas por vencer use el filtro **Vigencia → Por vencer** del
  listado.

## Nueva solicitud con los datos y documentos del cliente

Cuando un cliente que ya pidió una firma vuelve a pedir otra (renovación, otra
validez, la anterior se canceló…), no hace falta que llene todo de nuevo.

1. Abra la firma anterior del cliente, pestaña **Trámite**, y pulse **Nueva
   solicitud con estos datos** (requiere permiso de crear; no aparece mientras la
   firma está en *Borrador*).
2. Se crea una firma nueva en *Borrador* con los datos del titular, de facturación
   y de la empresa (si es representante legal), y con sus **documentos ya
   cargados**: cédula, selfie, RUC, constitución, nombramiento, etc.
3. Se abre el cuadro **Enviar enlace** para mandarle al cliente su enlace por
   correo o WhatsApp. En el portal ve sus datos y documentos listos, con el aviso de
   revisar que sigan vigentes; si alguno cambió, sube el nuevo y luego paga.

**Botón Renovar.** Al pie de la ficha, a la izquierda de **Cancelar**, el botón
**Renovar** hace lo mismo sin entrar a la pestaña Trámite: crea la solicitud de
renovación con los datos y documentos del cliente y abre **Enviar enlace**. Se
puede renovar aunque la firma **todavía no haya vencido**: en ese caso aparece
el aviso *La firma aún no está vencida* con la fecha y los días que faltan, y
usted decide con **Renovar de todos modos**. Requiere permiso de crear y solo
aparece en firmas **Emitidas**; para los demás estados use **Nueva solicitud con
estos datos** en la pestaña Trámite.

Qué **no** se copia: el **pago** (ni el comprobante de transferencia), la factura,
el cobro y el trámite en Uanataca; todo eso empieza de cero.

- **Un clic = una solicitud**: si pulsa el botón otra vez (o ya existe una nueva
  solicitud en curso creada desde esa firma), se abre la misma, no se crea otra. Si
  la nueva ya se pagó o está en trámite, el sistema avisa: para pedir otra firma
  más, hágalo desde la más reciente.
- **Los documentos no se duplican en el servidor**: la solicitud nueva usa el mismo
  archivo que la anterior. Si el cliente reemplaza un documento, el archivo viejo
  se borra del servidor apenas ninguna solicitud lo usa; mientras la firma anterior
  lo tenga, se conserva (es lo que se envió a Uanataca en su momento).
- Lo mismo pasa con el **enlace de renovación** de los avisos de vencimiento.

**El cliente también puede pedirla solo, con su mismo enlace.** Cuando su firma está
*Emitida* o *Cancelada*, al abrir el enlace que ya tenía ve el botón **Solicitar una
nueva firma**: se crea la solicitud nueva con sus datos y documentos y lo lleva a su
enlace nuevo, donde revisa y paga. Si ya la había iniciado, el botón dice **Ver mi
nueva solicitud** y lo lleva a esa (no se crea otra). Mientras la solicitud actual siga
en curso, el botón no aparece. La empresa recibe el correo de siempre cuando el cliente
termina de enviar sus documentos.

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
| Nueva / En validación | Trámite *En trámite* |
| Aprobada | Pasa a *Aprobada*: la solicitud ya no se puede editar ni eliminar, solo falta la emisión |
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
- **Crear**: registrar firmas a mano, enviar enlaces, crear una **nueva solicitud
  con los datos y documentos** de otra firma y generar la factura en borrador
  desde la pestaña Facturación.
- **Modificar**: aprobar o rechazar pagos, avanzar el trámite, reenviar el
  correo de confirmación y la configuración del módulo.
- Sin **acceso total**, el usuario ve solo las solicitudes que él creó o cuyos
  enlaces envió; las del enlace fijo pertenecen al usuario responsable.

## Reglas de negocio

- **Identificación del titular** (en la ficha y en el enlace del cliente): solo
  **Cédula** o **Pasaporte**. Con cédula solo se pueden escribir **10 dígitos**; con
  pasaporte, letras y números hasta **20 caracteres**. Al cambiar el tipo, lo escrito
  se ajusta (por ejemplo, al pasar de pasaporte a cédula se quitan las letras). Con
  pasaporte no se consultan datos en el SRI. Quien tiene RUC como persona natural se
  identifica con su cédula y activa **Firma electrónica con RUC**. El RUC sí se puede
  usar en los datos de **facturación** (factura a otro nombre).
- Un enlace genera **una sola** solicitud; un pago genera **una sola** factura y
  un solo cobro aunque se repita el clic o Payphone avise dos veces.
- El cliente puede corregir sus datos y documentos mientras la solicitud está
  en *Borrador*, *Pendiente de pago*, *Observada* o *Por registrar* (pagada y aún
  sin enviar a Uanataca). Después de pagar no puede cambiar la validez elegida ni
  los datos de la factura (ver *Cómo corrige el cliente su solicitud*).
- El precio cobrado por Payphone es el total de la factura (IVA según la serie
  configurada).
- La **dirección** del titular es obligatoria y debe tener al menos 10
  caracteres (calle, número, sector), tanto en el formulario del cliente como
  en la ficha.
- Una vez **aprobada por Uanataca** o **emitida**, la solicitud **no se edita
  ni se elimina** (tampoco el cliente desde su enlace, ni se le puede pedir
  corrección). La ficha queda de solo lectura y el avance (marcar emitida,
  notas, avisos) se lleva desde la pestaña **Trámite**.
- Una firma con factura activa (borrador o autorizada) **no se elimina**. Mientras
  no esté aprobada ni emitida, sí se pueden corregir los datos del titular, el
  **tipo de firma**, el estado y las observaciones. La factura no cambia: queda
  con el tipo y el valor con que se hizo (si el precio del nuevo tipo es
  distinto, ajústelo con una nota de crédito o anulando la factura). El estado
  del pago y las pestañas Pago y Facturación quedan como se facturaron.
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
- **Correo automático a la empresa**: un primer aviso **15 días antes** si la
  empresa es **sociedad** o **5 días antes** si es **persona natural**, y un
  segundo aviso **2 días antes** con el enlace para **solicitar la firma nueva**.
  Llega al correo registrado en la ficha de la empresa (pestaña *Datos
  generales*, campo **Correo**), aunque nadie haya iniciado sesión.

Cómo funciona el correo:

| Regla | Detalle |
|-------|---------|
| Primer aviso | **Sociedad** (RUC con tercer dígito 9 o 6): **15 días** antes. **Persona natural** (tercer dígito 0 a 5): **5 días** antes. Si el RUC no se puede leer, se usa el *Tipo de contribuyente* de la ficha. |
| Segundo aviso | **2 días** antes (o el día que se revise si ya faltan menos), con el botón **Solicitar mi firma electrónica**: abre la solicitud de firma de la empresa que le vende las firmas (su controladora de suscripciones o, si no tiene, la administradora). La solicitud aparece en *Enlaces enviados* de esa empresa con la etiqueta **Aviso de vencimiento**. Si esa empresa no tiene configurado el módulo, el correo sale igual, sin el botón. |
| A quién | Al correo de la ficha de la empresa. Si está vacío o no es válido, no se envía y queda una traza en el log del servidor. |
| Cuántas veces | **Una vez por aviso y por firma.** Cada envío queda en la bitácora del sistema (tabla *Firma electrónica de la empresa*, acciones *Aviso caducidad correo* y *Aviso caducidad enlace*) y esa marca evita repetirlo. |
| Si ya caducó | Si la firma venció hace **hasta 7 días** y no se envió el segundo aviso (por ejemplo, el correo del servidor estaba caído), se envía igual. Firmas vencidas hace más tiempo no generan correo. |
| Hora | La revisión corre una vez al día, a partir de las **06:00**, sobre el cron del servidor. |
| Firma nueva | Al cargar y activar la firma renovada, los avisos se reinician: la nueva firma recibirá los suyos cuando le toque. |

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
  *notificaciones* del sistema esté configurado. Cada aviso (el de 15 o 5 días y
  el de 2 días) se envía una sola vez por firma; si ya se envió, no se repite
  aunque la firma siga caducada.
- **El correo de 2 días llegó sin el botón para solicitar la firma**: la empresa
  que vende las firmas no tiene configurado el módulo Firmas electrónicas (o
  está inactiva), o el servidor no tiene definida su dirección pública
  (`APP_URL`), necesaria para armar el enlace desde el cron.

## Historial de cambios

- **3.18** — Ficha de la firma: recuadro **Siguiente paso** bajo la fila de pasos (qué falta, quién actúa y enlace a la pestaña); aviso al adelantar el Estado Trámite a mano sin el pago registrado. Nueva sección *Cómo avanza el trámite: siguiente paso y estados*. En Trámite: recuadro que explica cómo seguir cuando una firma *En trámite* no tiene solicitud por API (con **Regresar a «Por registrar»**) y nota que aclara que la factura ya se envió junto al botón de reenviar. Un pago marcado «Confirmado» a mano, sin cobro, ahora se puede registrar desde Trámite (**Falta registrar el cobro**).
- **3.17** — **Enlaces y QR por vendedor**: pestaña **Vendedores** en el engranaje (un enlace y un QR por vendedor), campo **Vendedor** en *Enviar link*, columna **Vendedor** en el listado, en el Excel y en *Enlaces enviados*; la factura de la firma sale con ese vendedor y las renovaciones lo conservan. Requiere `database/20261110_firmas_enlaces_vendedor.sql`.
- **3.16** — Botón **Renovar** al pie de la ficha de una firma emitida (junto a Cancelar): crea la solicitud de renovación con los datos y documentos del cliente; si la firma aún no vence, avisa antes con la fecha y los días que faltan.
- **3.15** — El cliente puede corregir sus datos y reemplazar sus documentos **después de pagar**, mientras la firma esté *Por registrar* (antes de enviarse a Uanataca); la validez y la factura no cambian. La empresa recibe un correo de aviso y, si el cliente cambia su correo, debe confirmarlo de nuevo.
- **3.14** — Botón **Imprimir QR** junto al enlace público fijo (engranaje, pestaña General): cartel con título editable, QR y enlace. Nueva sección *Cómo corrige el cliente su solicitud*.
- **3.13** — El PDF de la factura que se genera por la venta de una firma usa la configuración del **establecimiento de la serie** de la factura (decimales, dirección, leyendas), no la del primer establecimiento de la empresa. El logo propio del punto de emisión sigue mandando.
- **3.12** — Número de identificación: con cédula solo se pueden escribir 10 dígitos
  y con pasaporte letras y números hasta 20, tanto en la ficha como en el enlace del
  cliente (antes se podía escribir cualquier cosa y el error salía recién al guardar).
  El enlace del cliente ya no ofrece RUC como tipo de identificación del titular.
- **3.11** — Botón **Nueva solicitud con estos datos** (pestaña Trámite): cuando el
  cliente vuelve a pedir una firma se reutilizan sus datos y documentos; el pago
  empieza de cero. El enlace de renovación de los avisos de vencimiento también trae
  los documentos ya cargados. Los documentos no se duplican en el servidor: la
  solicitud nueva usa el mismo archivo, y un documento reemplazado o eliminado se
  borra del servidor cuando ninguna solicitud lo usa. Desde el enlace de una firma
  emitida o cancelada, el cliente puede **Solicitar una nueva firma** él mismo.
- **3.10** — La fecha de vencimiento también se llena sola en las solicitudes hechas
  por el cliente desde el enlace y en las renovaciones (antes quedaban sin fecha).
  En la pestaña **Pago** se ve la imagen (o el PDF) del comprobante adjunto, aunque la
  forma de pago aún no esté elegida. Al registrar el pago (pestaña **Trámite**) se ve
  el saldo de Uanacréditos de Uanataca. Cuando un cliente completa su solicitud desde
  el enlace, llega un correo al correo de la empresa avisando la nueva solicitud (y otro
  cuando corrige una solicitud observada).
- **3.9** — Los WhatsApp enviados por la plantilla (enlace de solicitud y aviso de
  vencimiento) quedan registrados en el **chat de WhatsApp** de la empresa, igual que los
  recordatorios de Cuentas por Cobrar.
- **3.8** — Ficha de la firma: arriba se muestra el **historial del estado** en una sola fila de
  flechas (Datos registrados » Documentos » Pago » Facturado (o «Facturado y autorizado»
  cuando el SRI la autoriza) » Enviado a Uanataca » Firma aprobada » Firma emitida, en rojo mientras faltan y en verde
  cuando se cumplen) en lugar del aviso «ya está aprobada… no se puede editar»; la
  ficha sigue siendo de solo lectura en ese caso.
- **3.7** — Con «Esperar a que el cliente confirme su correo» desmarcado (pestaña
  Uanataca), la ficha ya no avisa que el cliente no confirmó su correo y el aviso
  de la barra superior cuenta esas firmas como listas para registrar.
- **3.6** — Formulario del cliente: el celular se muestra y se pide con 10 dígitos
  (0958924835, sin el código de país). Código dactilar en mayúsculas, solo letras
  y números (6 a 10), en el formulario del cliente y en la ficha. Direcciones en
  mayúsculas y correos en minúsculas (titular y facturación).
- **3.5** — RUC de la empresa (representante legal): consulta al SRI y llena la
  razón social, en el formulario del cliente (con botón de búsqueda) y en la
  ficha. Cargo por defecto: «Representante legal».
- **3.4** — Ficha de la firma: se quita el campo «Motivo (uso de la firma)» de
  representante legal; se usa el motivo por defecto (o el que ya tenía).
- **3.3** — Aviso de vencimiento de la firma de la empresa: primer correo 15
  días antes (sociedad) o 5 días antes (persona natural) y un segundo correo a 2
  días con el enlace para solicitar la firma nueva (antes: un solo correo un día
  antes).
- **3.2** — Formulario del cliente: se quitan «¿Para qué usará la firma?» y
  Observaciones; «¿Tienes RUC?» pasa a **Firma electrónica con RUC** (solo persona natural, también en la ficha). El motivo va por defecto a Uanataca y las
  observaciones internas de la ficha ya no se borran cuando el cliente guarda.
  Enviar link: celular de 10 dígitos (09…) y correo válido.
- **3.1** — Configuración, pestaña **Tipo de persona**: cada firma se marca para
  persona natural, representante legal o ambos; el cliente y la ficha solo
  muestran las firmas que corresponden y el servidor valida la combinación.
  Requiere `database/20261024_firmas_productos_tipo_persona.sql`.
- **3.0** — Nuevo estado **Aprobada**: cuando Uanataca aprueba la solicitud se
  marca igual en el sistema (consulta automática o *Consultar estado ahora*) y
  la solicitud deja de editarse; lo mismo al quedar emitida.
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
