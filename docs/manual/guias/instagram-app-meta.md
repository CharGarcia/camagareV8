---
titulo: App de Meta para Instagram (CRM)
resumen: Cómo crea la empresa su app de Meta (Unified login), la registra en Configuración del CRM con su ID de configuración y conecta su cuenta de Instagram para recibir mensajes, publicar y cumplir la eliminación de datos.
categoria: Ventas
tipo: guia
visibilidad: todos
etiquetas: meta, socio, asignar socio, portafolio comercial, no se pudo conectar, configuracion basica, politica de privacidad, facebook developers, app de meta, instagram api, unified login, inicio de sesion unificado, id de configuracion, config_id, configuracion de inicio de sesion, usuario del sistema, suat, instagram login, app id, app secret, redirect uri, url de redireccion, oauth, webhook, token de verificacion, verify token, app review, revision de la app, permisos instagram, instagram_manage_insights, evaluador de instagram, instagram tester, permitir acceso a los mensajes, human agent, eliminacion de datos, data deletion, desautorizar, deauthorize, privacidad, crm instagram, conectar instagram, publicar en instagram, estadisticas, app_url
version: 3.1
orden: 50
estado: activo
---

Para usar Instagram en el CRM, **cada empresa** registra su propia app de Meta en
**Configuración del CRM → Canales → App de Meta de la empresa**. Lo puede hacer
cualquier usuario con permiso en ese módulo. Los datos quedan guardados solo
para esa empresa, y el App Secret se guarda cifrado y no se vuelve a mostrar.

En esa tarjeta, el botón **Paso a paso para configurar la app en Meta** muestra
estos mismos pasos como tarjetas, con la ruta de menús de Meta y cada dato listo
para copiar.

> Desde noviembre de 2026 Meta crea las apps nuevas con **«API setup with Unified
> login»** (inicio de sesión unificado): ya no ofrece «Instagram login» a apps
> nuevas. Esta guía describe esa forma. Si su app es anterior y usa «API setup with
> Instagram login», vea la sección **Apps anteriores a noviembre de 2026**.

## Requisitos previos

- Una cuenta de Instagram **profesional** (Empresa o Creador). No hace falta página
  de Facebook.
- Un **portafolio comercial** de Meta (Business Manager), de preferencia verificado.
- Acceso a **developers.facebook.com** con la cuenta de Facebook que administra el
  portafolio.
- **Quien conecte debe entrar con una cuenta de Facebook** que sea administradora
  del portafolio dueño de la app **y** tenga rol en la app. Una identidad de solo
  Instagram (la que aparece como «@cuenta» en Business) **no puede** crear el
  usuario del sistema que Meta necesita, y la conexión falla al pulsar *Guardar*
  (ver **Errores frecuentes**).

## 1. Crear la app en Meta

1. En **developers.facebook.com → Mis apps → Crear app**, póngale un nombre que **no**
   lleve «Instagram», «Facebook», «Meta» ni «WhatsApp» (Meta lo rechaza), por
   ejemplo «MiEmpresa CRM».
2. Caso de uso: **Administrar mensajes y contenido en Instagram**.
3. Portafolio comercial: el de la empresa (si está verificado, mejor).
4. Si la empresa ya tiene una app de Meta para **WhatsApp**, cree **otra** para el
   CRM: así la revisión de Instagram no toca la de WhatsApp.
5. Al añadir casos de uso marque **solo** ese: ni anuncios (API de marketing), ni
   WhatsApp, ni inicio de sesión con Facebook.
6. **Configuración de la app → Básica** — complétela **antes** de conectar; si queda
   vacía, Meta corta la conexión con el error genérico «No se pudo conectar…»:

| Campo | Valor |
|---|---|
| Dominios de la app | el dominio del sistema, p. ej. `erp.camagare.com.ec` |
| URL de la política de privacidad | la página pública de privacidad de la empresa |
| Eliminación de datos de usuario | *URL de devolución de llamada* → la «URL de solicitud de eliminación de datos» del sistema (paso 3) |
| Categoría | Empresas y páginas (o la más parecida) |
| Ícono de la app | el logo de la empresa (1024×1024) |

## 2. Crear la configuración de inicio de sesión

En la app: **Casos de uso → Administrar mensajes y contenido en Instagram →
Personalizar → API setup with Unified login → Crear nueva configuración**.

| Campo | Valor |
|---|---|
| Variación de inicio de sesión | General |
| Identificador de acceso | **Usuario del sistema** (SUAT) |
| Caducidad | **Nunca** |
| Activos | **Cuentas de Instagram** (las cuentas publicitarias no hacen falta) |
| Permisos | `instagram_basic`, `instagram_manage_messages`, `instagram_manage_comments`, `instagram_content_publish`, `instagram_manage_insights` |

Meta crea la configuración con `ads_management` y `ads_read` y sin
`instagram_manage_insights`. Pulse **Editar permisos** en la misma tarjeta: quite los
dos de anuncios (el sistema no los usa y piden más en la revisión) y agregue
`instagram_manage_insights` (sin él no hay **métricas**). El ID no cambia. La línea
«Cuenta publicitaria (opcional)» de los activos puede quedar: al conectar no se
marca ninguna.

En **Casos de uso → Personalizar → Permisos y funciones**, los cinco permisos deben
decir **Listo para prueba**.

No use **Crear otra configuración** para cambiar algo: hace una copia igual. Las
configuraciones se ven y editan en **Inicio de sesión con Facebook para empresas →
Ajustes**.

Al guardar, en **URL de redirección** registre y **seleccione** la dirección
«URL de redirección» que muestra el sistema (paso 3). Meta genera entonces una
**URL de inicio de sesión**: el número que va después de `config_id=` es el
**ID de configuración**.

## 3. Registrarla en el sistema

1. En **Configuración del CRM → Canales → App de Meta de la empresa** escriba:
   - **App ID** y **App Secret**: los de la app (*Configuración de la app → Básica*).
   - **ID de configuración**: el del paso 2.
2. Pulse **Guardar**. Aparecen las direcciones para Meta y el **token de
   verificación del webhook**, cada uno con su botón para copiar.

Para cambiar algo basta editarlo; el App Secret se deja vacío para conservar el
guardado. **Cambiar de forma** (poner o quitar el ID de configuración) solo se
permite sin cuentas conectadas: desconéctelas, guarde y vuelva a conectar.

## 4. Pegar los datos en la app de Meta

| En Meta | Qué pegar |
|---|---|
| API setup with Unified login → la configuración → **URL de redirección** | la «URL de redirección» |
| API setup with Unified login → **Configurar webhooks** → URL de devolución de llamada | la «URL de devolución de llamada (webhook)» |
| Configurar webhooks → Token de verificación | el «Token de verificación del webhook» |
| Configurar webhooks → campos | suscriba **messages**, **messaging_seen** y **comments** |
| Configuración de la app → Básica → Eliminación de datos | la «URL de solicitud de eliminación de datos» |

Al guardar el webhook, Meta comprueba el token; si no coincide responde
«Forbidden». Si regenera el token en el sistema, péguelo otra vez en Meta.

## 5. Probar con la cuenta de la empresa (modo desarrollo)

Mientras la app no pasa la revisión de Meta, solo funcionan las cuentas con un rol
en la app:

1. En **API setup with Unified login → Generar identificadores de acceso → Añadir
   cuenta**, agregue la cuenta de Instagram de la empresa con el rol **Evaluador de
   Instagram**.
2. Acepte la invitación en **instagram.com/accounts/manage_access → Invitaciones de
   evaluador**.
3. Si la cuenta de Instagram es de **otro portafolio** que el de la app, compártala
   antes como **socio** (sección siguiente).
4. En el sistema pulse **Conectar Instagram** y luego **Abrir y conectar**. Meta se
   abre en una ventana aparte:
   - Pulse **Continuar con Facebook** (no «Continuar con Instagram») y entre con la
     cuenta de Facebook **administradora del portafolio dueño de la app**.
   - **Portafolio comercial**: el **dueño de la app**.
   - **Cuentas de Instagram**: la de la empresa.
   - **Cuentas publicitarias**: **ninguna**.
   - **Continuar → Guardar**. La ventana se cierra sola y el sistema muestra el
     resultado.

No hace falta generar ni copiar ningún token en Meta: el sistema obtiene y guarda
el acceso solo, cifrado.

## Si la cuenta de Instagram está en otro portafolio

Pasa cuando la cuenta de Instagram se creó o se administra desde Instagram: Meta le
arma un portafolio propio (con el nombre de la cuenta) donde la única administradora
es la identidad de Instagram, que no puede crear usuarios del sistema. Para unirla
con la app:

1. En el portafolio **dueño de la app** (entrando con Facebook): **Configuración →
   Información del negocio** → copie el **identificador del portafolio**.
2. En **business.facebook.com/settings**, entrando como la cuenta de Instagram, elija
   **su** portafolio → **Cuentas → Cuentas de Instagram** → la cuenta → **Asignar
   socio** → pegue ese identificador → **todos los permisos** → confirmar.
3. Conecte como en el paso 5, eligiendo el portafolio **dueño de la app**.

Si Meta responde «El identificador del negocio del socio no puede ser el tuyo», se
copió el número del mismo portafolio: compare las personas de cada uno (son
portafolios distintos aunque los nombres se parezcan).

## Cómo queda conectada la cuenta

- El acceso pertenece al **portafolio comercial**, no a la persona que conectó, y
  **no vence**: la pantalla muestra «acceso del portafolio (no vence)».
- Si en la ventana de Meta se comparten **varias cuentas**, el sistema crea un canal
  por cada una.
- **La ventana de 24 horas cuenta desde que la cuenta está conectada.** Los mensajes
  que llegaron antes de conectarla no se pueden responder desde el sistema: hay que
  esperar a que el cliente vuelva a escribir.
- Si el acceso deja de servir (alguien quitó la app del portafolio, o la cuenta pasó
  a otro portafolio), el canal queda **«Acceso vencido»** y pide **Reconectar**.

## Permisos y revisión de la app

| Permiso | Para qué |
|---|---|
| `instagram_basic` | Datos de la cuenta |
| `instagram_manage_messages` | Bandeja social: mensajes directos |
| `instagram_manage_comments` | Bandeja social: comentarios |
| `instagram_content_publish` | **Publicaciones**: imágenes, carruseles y reels |
| `instagram_manage_insights` | **Publicaciones**: estadísticas y seguidores |

Si a la configuración le falta alguno, la tarjeta de la cuenta lo avisa; agréguelo
en Meta y pulse **Reconectar**.

- Para conectar cuentas **sin rol** en la app hay que pasar la **revisión de la app**
  (App Review) de cada permiso y poner la app en modo **Live**: Meta pide política de
  privacidad pública, la URL de eliminación de datos (la entrega el sistema) y un
  video del flujo completo.
- **Human Agent** (responder hasta 7 días) es una función aparte que también se pide
  en la revisión. Solo cuando esté aprobada active «Responder hasta 7 días» en la
  cuenta.

## Qué hace el sistema con cada dirección

- **Webhook**: cada aviso de Meta llega firmado con el App Secret de **su** app. El
  sistema lo valida con la app de la empresa dueña de la cuenta; un aviso firmado
  por la app de otra empresa se rechaza y no puede meter mensajes en la suya.
- **Eliminación de datos**: si alguien pide a Meta borrar sus datos, el sistema borra
  lo que guardó de esa cuenta o persona **en las empresas que usan esa app** (texto
  y archivos de mensajes y comentarios, perfil y datos de Instagram de sus contactos)
  y entrega a Meta un **código de confirmación** con una página pública de estado.
  Lo que la empresa escribió a mano en un contacto se respeta.
- **Cancelar autorización**: la dirección se muestra por si Meta la pide. Con
  Unified login la pérdida de acceso se detecta sola (ver arriba).

## Apps anteriores a noviembre de 2026 (Instagram login)

Si la app tiene **«API setup with Instagram login»**:

- En el sistema use el **Instagram App ID** y el **Instagram App Secret** (de esa
  sección, no los de *Configuración → Básica*) y deje **vacío** el ID de
  configuración.
- Registre en *Instagram → Configuración de la API con inicio de sesión de
  Instagram*: la URI de redirección de OAuth, el webhook (con su token y los campos
  messages, messaging_seen y comments), Cancelar autorización y Solicitud de
  eliminación de datos.
- Permisos: `instagram_business_basic`, `instagram_business_manage_messages`,
  `instagram_business_manage_comments`, `instagram_business_content_publish` e
  `instagram_business_manage_insights`, que se aceptan al conectar.
- El acceso dura ~60 días y el sistema lo **renueva solo** cuando faltan 10 días o
  menos.

## Errores frecuentes

- **«Registre primero la app de Meta de la empresa…»**: falta el paso 3.
- **«El App ID son solo números»** / **«El ID de configuración son solo números»**:
  copió otro dato. El ID de configuración está en la URL de inicio de sesión de Meta,
  después de `config_id=`.
- **Meta dice «Invalid platform app» al conectar**: la app es nueva y falta el **ID
  de configuración** en el sistema.
- **Meta dice «No se pudo conectar {app}. Es posible que no estés conectado a la
  red…»** (también desde *Generar identificador* del panel de Meta). No es la red ni
  el sistema: la ventana nunca vuelve al sistema. Revise, en este orden:
  1. **Configuración de la app → Básica** completa (dominio, privacidad, eliminación
     de datos, categoría, ícono).
  2. Quien conecta entra con **Facebook**, es **administrador del portafolio dueño de
     la app** y tiene rol en la app. Si entra como la identidad de Instagram, o la
     cuenta está en otro portafolio, falla justo al pulsar **Guardar** (Meta no puede
     crear el usuario del sistema): comparta la cuenta como **socio** (sección *Si la
     cuenta de Instagram está en otro portafolio*).
- **«Meta no compartió ninguna cuenta de Instagram»**: en la ventana de Meta no se
  eligió la cuenta, o la cuenta es personal. Vuelva a conectar y elíjala.
- **«Hay cuentas de Instagram conectadas con la forma anterior…»**: al poner o quitar
  el ID de configuración con cuentas conectadas. Desconéctelas primero.
- **«Instagram no permite responder: han pasado más de 24 h…»**: el cliente escribió
  hace más de 24 h o antes de conectar la cuenta.
- **Error (#200) al enviar mensajes**: en la app de Instagram del celular, active
  *Configuración → Mensajes y respuestas a historias → Herramientas conectadas →
  Permitir acceso a los mensajes*.
- **Meta dice «Forbidden» al guardar el webhook**: el token no coincide; cópielo de
  nuevo desde el sistema.
- **«Hay cuentas de Instagram conectadas con esta app. Desconéctelas primero.»**: al
  intentar quitar la app.

## Publicaciones: dirección pública del servidor

Para publicar, Instagram **descarga** cada imagen o video desde el servidor del
sistema mediante un enlace privado que vence a las 2 horas. Por eso el servidor
debe tener configurada su dirección pública con **https** (`app_url` en
`config/local.php`); sin ella, las publicaciones de Instagram quedan con error.

## Historial de cambios

- **3.1** — Configuración básica de la app obligatoria antes de conectar; conectar
  entrando con Facebook y eligiendo el portafolio dueño de la app; cuenta de
  Instagram de otro portafolio compartida como **socio**; error «No se pudo
  conectar…» con sus dos causas; editar permisos con «Editar permisos».

- **3.0** — **Unified login** para las apps nuevas de Meta (con paso a paso en pantalla y conexión en ventana aparte): campo **ID de
  configuración**, App ID/Secret de la app de Meta, configuración con usuario del
  sistema que no vence, una cuenta por canal, cuenta evaluadora para probar, ventana
  de 24 h desde la conexión, aviso de acceso revocado. La forma anterior queda como
  *Apps anteriores a noviembre de 2026*.
- **2.1** — Permisos de publicar (`instagram_business_content_publish`) y de
  estadísticas (`instagram_business_manage_insights`) para el módulo Publicaciones;
  las cuentas ya conectadas deben reconectarse. Requisito de `app_url` con https.
- **2.0** — La app de Meta la registra cada empresa en Configuración del CRM (antes
  era una sola app del sistema en `config/local.php`). Webhook, cancelación de
  autorización y eliminación de datos se validan con la app de la empresa dueña.
- **1.2** — URLs de eliminación de datos y de desautorización, con página pública de estado.
- **1.1** — Webhook activo en `/instagram-webhook`.
- **1.0** — Versión inicial.
