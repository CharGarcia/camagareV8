# CRM y Redes — Instagram con «Unified login» (adaptación)

> Estado: **aprobado el 09-11-2026** (las 7 decisiones tal cual).
> Antecedentes: fases 1-3 (`crm-redes-fase1.md`, `-fase2.md`, `-fase3.md`) programadas con la
> **API de Instagram con inicio de sesión de Instagram** (*Instagram Login*).

## 1. Por qué

Al crear la app de Meta en producción (09-11-2026), Meta **ya no ofrece** «API setup with
Instagram login» a las apps nuevas: el caso de uso *Administrar mensajes y contenido en
Instagram* solo trae **«API setup with Unified login»** (y la variante con Facebook Login, que
exige una página de Facebook). El panel lo dice así: *«API setup with Instagram login is not
available for newer apps. Your app uses API setup with Unified login instead.»* Las apps creadas
antes siguen funcionando con Instagram Login y Meta no anunció fecha de retiro.

Sin esta adaptación ninguna empresa con app nueva puede conectar Instagram: el sistema arma la
URL de `www.instagram.com/oauth/authorize`, que con el App ID de una app nueva responde
*«Invalid platform app»*.

## 2. Qué cambia en Meta (documentación oficial, nov. 2026)

| | Instagram Login (lo programado) | **Unified login** (apps nuevas) |
|---|---|---|
| Credenciales | *Instagram* App ID / Secret | App ID / Secret **de la app de Meta** (Configuración → Básica) |
| Inicio de sesión | `www.instagram.com/oauth/authorize?scope=…` | `www.facebook.com/{v}/dialog/oauth?client_id&config_id&redirect_uri&response_type=code&state` — **`config_id` reemplaza a `scope`** |
| Configuración | — | «Configuración» de Facebook Login for Business: token de **usuario del sistema (SUAT)**, caducidad **Nunca**, activo «Cuenta de Instagram», permisos |
| Canje del código | `api.instagram.com/oauth/access_token` → token 1 h → token 60 días | `GET graph.facebook.com/{v}/oauth/access_token?client_id&client_secret&code&redirect_uri` → **token de usuario del sistema de integración (BISU)** del portafolio del cliente |
| Vencimiento | 60 días; se renueva cada día que falten ≤ 10 | El que diga la configuración (**Nunca**). Deja de servir si el cliente quita la app de su portafolio o mueve la cuenta a otro portafolio |
| Host | `graph.instagram.com` | `graph.facebook.com` (`graph.instagram.com` **rechaza** este token) |
| `/me` | la cuenta de Instagram | **el usuario del sistema**, no la cuenta: todo va por `/{id-cuenta-instagram}` |
| Cuentas | una por token | `GET /me/assigned_instagram_accounts?fields=id,username`: **un token puede traer varias cuentas** |
| Permisos | `instagram_business_basic`, `_manage_messages`, `_manage_comments`, `_content_publish`, `_manage_insights` | `instagram_basic`, `instagram_manage_messages`, `instagram_manage_comments`, `instagram_content_publish`, `instagram_manage_insights` (mismo significado, otro nombre) |
| Webhooks | `POST graph.instagram.com/{v}/me/subscribed_apps` | `POST graph.facebook.com/{v}/{id-cuenta}/subscribed_apps?subscribed_fields=…` |
| Firma de webhooks | `X-Hub-Signature-256` con el App Secret | igual (App Secret de la app de Meta) |
| IGSID de los clientes | — | **los mismos** que con Instagram Login (los contactos no cambian) |
| Revisión (App Review) | por permiso | igual: acceso estándar solo para personas con rol en la app; acceso avanzado por permiso + app en modo Live |

Fuentes: *Instagram Platform overview*; *Instagram API with Unified Login: Get started,
Authentication, Permissions, Migration*; *Webhooks setup* (developers.facebook.com,
actualizadas oct.-nov. 2026). Reportes de otros desarrolladores (postiz #2350, openreply #104).

### 2.1 Lo que la documentación no deja claro (se verifica antes de programar, §5 paso 1)

1. **Enviar mensajes**: la página de mensajería solo documenta Instagram Login
   (`graph.instagram.com/{ig}/messages`). Lo esperable, por la regla «todo por el id de la
   cuenta», es `POST graph.facebook.com/{v}/{id-cuenta}/messages` con el BISU. Igual para la
   respuesta privada (`recipient.comment_id`) y la etiqueta `HUMAN_AGENT`.
2. **`business_management`**: un desarrollador reporta que sin ese permiso
   `/me/assigned_instagram_accounts` devuelve `[]`; la documentación oficial solo pide
   `instagram_basic`. Si viene vacío, plan B: `GET /debug_token` → `granular_scopes[].target_ids`
   trae los ids de las cuentas autorizadas.
3. **Perfil de quien escribe** (`GET /{igsid}?fields=name,username`) con el BISU.
4. En Instagram, la cuenta debe tener activado **«Permitir acceso a los mensajes»**
   (Configuración → Mensajes → Herramientas conectadas); si no, la API de mensajes falla con
   `(#200)` (confirmado en *Eligibility and troubleshooting*).

Otros puntos de *Eligibility and troubleshooting*: la configuración debe ser **«Instagram Login
Compatible»** para que aparezca la opción de entrar con Instagram; si quien conecta ya tiene
sesión de Facebook en ese navegador, sin `prompt=login` entra como esa cuenta (el sistema lo
agrega siempre); la cuenta debe ser profesional (`(#10) … not business or creator`).

## 3. Decisiones (recomendación)

1. **Las dos formas conviven.** La app de la empresa que tenga **ID de configuración
   (`config_id`)** usa Unified login; la que no, sigue con Instagram Login (lo ya probado, para
   apps viejas). Un solo campo nuevo decide el modo; nada de lo existente se rompe.
   *Alternativa*: solo Unified login (más simple, pero deja sin salida a una app vieja).
2. **Permisos de la configuración en Meta**: `instagram_basic`, `instagram_manage_messages`,
   `instagram_manage_comments`, `instagram_content_publish`, `instagram_manage_insights`.
   **Quitar** `ads_management` y `ads_read` (vienen por defecto; el sistema no hace anuncios y
   pedirlos complica la revisión). `business_management` solo si la verificación (§2.1-2) muestra
   que hace falta.
3. **Un token con varias cuentas** → un **canal por cuenta** (cada uno guarda el mismo token
   cifrado). Si alguna ya está conectada a otra empresa, se omite con aviso (regla actual).
4. **Sin renovación**: el canal unificado guarda `token_expira_at = NULL` (el cron de renovación
   ya ignora los NULL) y la pantalla muestra «No vence».
5. **Acceso revocado**: si Meta responde *token inválido* (código 190) en cualquier llamada, el
   canal pasa a `token_vencido` y la pantalla pide **Reconectar** (hoy solo se detecta al vencer).
   Aplica a los dos modos.
6. **Eliminación de datos / desautorizar**: las direcciones públicas siguen igual. Con Unified
   login el `user_id` que manda Meta es el de la persona de Facebook, que no coincide con
   nada guardado: se responde con su código y estado «sin datos». La pérdida real de acceso se
   detecta por la decisión 5.
7. **Verificar antes de programar** (§5 paso 1) con el token de prueba que da el panel de Meta
   (modo desarrollo), con 5 llamadas `curl` que el usuario corre en el servidor. Así los puntos
   dudosos de §2.1 se resuelven con la respuesta real, no a ciegas.

## 4. Cambios en el sistema

**Base de datos** (`database/2026-11-09_crm_unified_login.sql`, idempotente):
`crm_meta_apps.config_id VARCHAR(40) NULL`. El modo y el id del portafolio del cliente
(`client_business_id`) de cada canal van en `crm_canales.config` (JSONB que ya existe).

**`InstagramApiService`** (modo según `config_id`):
- `urlAutorizacion()`: diálogo de Facebook con `config_id` (sin `scope`) y `prompt=login`.
- `canjearCodigo()`: canje en `graph.facebook.com`; sin token largo.
- Nuevo `cuentasAsignadas()` (+ plan B con `debug_token`) y `permisosConcedidos()` (`debug_token`).
- Host `graph.facebook.com` y **id de la cuenta en lugar de `me`** en perfil, suscripción a
  webhooks, mensajes, respuesta privada y seguidores (las llamadas reciben el id del canal).
- Detección del código 190 → excepción propia para la decisión 5.
- Publicar, métricas, comentarios: mismas rutas, otro host.

**`CrmCanalService::completarConexion()`**: en modo unificado, canje → cuentas asignadas →
por cada cuenta: perfil + suscripción a webhooks + alta/reconexión. `renovarTokens()` sin cambios.

**Llamadores** que hoy pasan solo el token (`CrmBandejaService`, `CrmWebhookService`,
`CrmMetricaService`, `CrmPublicadorService`, `CrmBotService` vía Bandeja): pasan además el id de
la cuenta (ya lo tienen en el canal).

**Pantalla** (Configuración del CRM → Canales → App de Meta): campo **ID de configuración**,
textos «App ID / App Secret de la app de Meta (Configuración → Básica)», la URL de retorno se
presenta como «URL de redirección de la configuración», y «No vence» en el canal.

**Manuales**: `guias/instagram-app-meta.md` 3.0 (pasos de Unified login, permisos, quitar
anuncios, «Permitir acceso a los mensajes», cuenta evaluadora); `crm-configuracion.md`.

**Sin cambios**: tablas del CRM, Bandeja, asistente virtual, publicaciones, métricas (salvo el
host), webhook público y su firma, LinkedIn.

## 5. Plan

1. **Verificación en Meta** (usuario + yo): crear la configuración, generar el token de prueba y
   correr las llamadas de verificación en el servidor. Resultado anotado en §6.
2. SQL + `InstagramApiService` modo unificado + pruebas con API falsa.
3. `CrmCanalService` (conexión de varias cuentas, 190) + llamadores + pruebas.
4. Pantalla Canales + guía y manuales.
5. Prueba real: conectar la cuenta de la empresa (evaluadora, modo desarrollo), recibir y
   responder un DM, publicar una imagen.

## 6. Avance

| Paso | Estado |
|---|---|
| 1 Verificación en Meta | **Hecho** (09-11-2026) |
| 2 Servicio de API | **Hecho** (09-11-2026): SQL `2026-11-09_crm_unified_login.sql` (`crm_meta_apps.config_id`); `InstagramApiService` en dos modos (`unificado()` = hay config_id): diálogo de Facebook con config_id, canje en graph.facebook.com, `cuentasAsignadas()`, `permisosConcedidos()`, `clienteBusinessId()`, todo por `/{id-cuenta}` (`nodo()`), token como access_token; error 190 → `InstagramTokenInvalidoException`; 2534022 con mensaje claro; `taparTokens()` (EAA…/IGAA…); red aislada en `transporte()`. `config/app.php` → `scopes_unificado`. Pruebas: `test_unified_paso2.php` 46/46 + suites anteriores en verde; token falso contra Meta real → 190 en los dos hosts |
| 3 Conexión y llamadores | **Hecho** (09-11-2026): `CrmCanalService::completarConexionUnificada()` (canje → permisos → portafolio → cuentas; un canal por cuenta con el mismo token, `token_expira_at` NULL, `config.modo=unificado` + `client_business_id`; cuentas de otra empresa se omiten con aviso; si todas, error); alta/reconexión común en `guardarCuenta()`; `getCanales()` mide permisos según el modo de cada canal (`unificada`); `accesoRevocado()` + `CrmCanalRepository::marcarRevocado()` (activo → token_vencido, una vez, log `TOKEN_REVOCADO`) desde Bandeja, publicador, seguidores y «Probar»; Bandeja/métricas pasan el id de la cuenta. Pruebas: `test_unified_paso3.php` 28/28 + 13 suites anteriores en verde |
| 4 Pantalla y manuales | **Hecho** (09-11-2026): campo **ID de configuración** en Canales → App de Meta (etiqueta del modo, direcciones con su lugar en Meta según el modo); `CrmMetaAppService::guardar(…, $configId)` + `CrmCanalRules::validarConfigId/validarCambioModo` (poner/quitar el ID solo sin cuentas conectadas) + `CrmMetaAppRepository::admiteConfigId/guardarConfigId`; tarjeta de la cuenta: «acceso del portafolio (no vence)», permisos que faltan en la configuración, acceso revocado. Manuales: `guias/instagram-app-meta` 3.0, `crm-configuracion` 1.4, `bandeja-social` 1.5. Pruebas: `test_unified_paso4.php` 12/12 + vista en los dos modos |
| 5 Prueba real | **Hecho** (09-11-2026): @camagare.ec conectada por Unified login (evaluadora, modo desarrollo), con `instagram_manage_insights` agregado a la configuración; DM entrante llega a la Bandeja y la respuesta desde la Bandeja llega a Instagram. Publicación de una imagen en @camagare.ec OK (antes falló porque `CrmPublicacionService::PERMISO_PUBLICAR` solo conocía `instagram_business_content_publish`; ahora acepta también `instagram_content_publish`) |

### 6.1 Resultado de la verificación (09-11-2026, app `2284857582363461`, config `1455752466529399`)

Token de prueba del panel (evaluadora @camagare.ec), llamadas desde el servidor de producción:

| Llamada (graph.facebook.com/v27.0) | Resultado |
|---|---|
| `GET /me?fields=id,name,client_business_id` | `{"client_business_id": …}` — solo eso (sin `id`/`name`): es el usuario del sistema |
| `GET /me/assigned_instagram_accounts?fields=id,username` | la cuenta, con su id `1784…` y usuario. **Sin `business_management`** (§2.1-2 resuelto: no hace falta) |
| `GET /debug_token?input_token=T&access_token=T` | `(#100)` pide token de app → en el sistema usar `APPID|APPSECRET` o, mejor, `/me/permissions` |
| `GET /me/permissions` | lista `permission/status` (los de la configuración + `public_profile`). **Se usa este** para saber qué concedió la empresa |
| `GET /{ig}?fields=id,username,name,followers_count,profile_picture_url` | OK. (No se pidió `account_type`: en modo unificado no se valida, solo hay cuentas profesionales) |
| `GET /{ig}/subscribed_apps` / `POST …?subscribed_fields=messages,messaging_seen,comments` | `{"data":[]}` / `{"success":true}` |
| `GET /{ig}/conversations?platform=instagram&fields=participants` | OK, IGSID iguales a los de Instagram Login. Sin `(#200)` |
| `POST /{ig}/messages` `{recipient:{id:IGSID}, message:{text}}` | **antes de suscribir la cuenta**: `(#10) … fuera del período permitido` (subcódigo 2534022) aunque el cliente escribió minutos antes. **Después de suscribir y de un mensaje nuevo del cliente**: `{"recipient_id","message_id"}` ✔ |
| `GET /{igsid}?fields=name,username` | OK |
| `GET /{ig}/content_publishing_limit?fields=quota_usage,config` | `quota_total` 100, `quota_duration` 86400 |

Conclusiones para programar:
- Mensajes, respuesta privada y suscripción: `/{id-cuenta}/…` en `graph.facebook.com` (no `me`).
- **La ventana de 24 h solo cuenta mensajes recibidos con la cuenta ya suscrita a la app**: lo
  escrito antes de conectar no se puede responder por el sistema (avisarlo en la guía y en el
  error 2534022 de la Bandeja).
- Meta **repite el token completo** en el error de un token mal formado: nunca registrar
  mensajes de error de Meta sin pasarlos por el enmascarado (`mensajeError` ya lo hace para
  `access_token=`; agregar `EAA…`).
- Pendiente de la configuración en Meta: quitar `ads_management`/`ads_read` y agregar
  `instagram_manage_insights` (sin él no hay métricas).
- El token de prueba quedó expuesto en el chat (Meta lo repitió en un error): se **revocó** el
  09-11-2026 quitando la integración «CaMaGaRe CRM» del portafolio de camagare (Configuración del
  negocio → Integraciones). La app y su configuración siguen; al conectar desde el sistema se
  vuelve a agregar con un token nuevo.
- **Puesta en marcha (09-11-2026, producción)**: los primeros DM entrantes quedaron en error en
  `crm_webhook_eventos` («value too long for type character varying(150)»): con Unified login el
  `mid` mide **164** caracteres. `crm_mensajes.id_externo` pasa a `VARCHAR(500)` (agregado a
  `2026-11-09_crm_unified_login.sql` y a la definición de la fase 1). En producción se aplicó el
  ALTER y se reencolaron los eventos (`estado='pendiente', intentos=0`): llegaron a la Bandeja.
- **Eco de los mensajes propios (09-11-2026)**: con Unified login el `message_id` que devuelve
  `POST /{ig}/messages` y el `mid` del eco que llega por el webhook son el **mismo mensaje con otra
  codificación** (base64 de `new_ig_dm_item:…` vs `ig_dm_item:…`), así que no coinciden. El eco de
  cada respuesta del asistente se tomaba como «respondió una persona desde la app» y lo **pausaba**.
  `CrmConversacionRepository::adoptarEco()` ahora reconoce el eco por texto/tipo entre los salientes
  del sistema de los últimos 10 min sin eco (`contenido.eco_mid`), aunque ya tengan id; guarda el
  `mid` del eco en `contenido.eco_mid` y reconoce sus reintentos. Prueba: `test_eco_unified.php`.
- **App bloqueada y app nueva (10-12/11/2026)**: la app `2284857582363461` quedó con «API access
  blocked» (código 200, fbtrace `AuUp_F6cRo4byBycptQNKUV`) sin causa en el código; Meta no da
  soporte directo. Se eliminó y se creó **CaMaGaRe Redes** (app `1878953656853084`, configuración
  `1443086370677400`). Al conectar, Meta respondía «No se pudo conectar CaMaGaRe Redes… no pudimos
  establecer conexión con nuestro servidor» (también desde *Generar identificador* del panel; el
  callback nunca llegaba). Causas encontradas, en orden:
  1. **Configuración de la app → Básica vacía** (dominio, privacidad, eliminación de datos,
     categoría): al completarla el diálogo pasó a la pantalla de resumen.
  2. **Portafolio de la cuenta ≠ portafolio de la app**: `@camagare.ec` está en el portafolio
     «CaMaGaRe» cuya única administradora es la identidad de Instagram (no puede crear usuarios
     del sistema: «Agregar» desactivado); la app es de «CMG Business Administration» (Mauricio
     Garcia, Facebook). Fallaba justo al pulsar **Guardar** (creación del usuario del sistema).
     Prueba que lo aisló: un ajuste con *identificador de acceso de usuario* sí completaba.
     Solución: en «CaMaGaRe» → Cuentas de Instagram → `@camagare.ec` → **Asignar socio** con el ID
     de CMG Business Administration, y conectar entrando con **Facebook** (Mauricio) eligiendo
     **CMG Business Administration**. Conectado el 12-11-2026.
  Quedan para limpiar en Meta: ajustes de prueba `1656603572793632` y `1788601369040496`, la
  integración de prueba «Mauricio Garcia → CaMaGaRe Redes» y `ads_*` de la configuración.
  Documentado en `guias/instagram-app-meta` 3.1 y en dos tarjetas nuevas del paso a paso.
- **Mensajes que no llegaban (12-11-2026)**: conectada y sin avisos, pero Meta no enviaba
  ningún evento real (ni DM, ni eco propio, ni comentario); el `POST` de prueba del panel sí
  llegaba (200) y `scripts/crm_diagnostico_instagram.php` mostró suscripción, permisos y perfil
  correctos; publicar la app tampoco cambió nada. Causa: `@camagare.ec` era **propiedad** de
  «CaMaGaRe» y llegaba a la app solo como **socio** de CMG Business Administration. Solución: quitarla
  de «CaMaGaRe» (Cuentas de Instagram → Eliminar), **añadirla como propia** en CMG Business
  Administration (inicio de sesión de Instagram) y Reconectar → llegaron los mensajes. Regla: la
  cuenta de Instagram debe ser **propiedad del portafolio dueño de la app** (guía 3.1). App
  publicada (Live) el 12-11-2026, con ícono `mobile/assets/icon.png` sin fondo y política de privacidad.
