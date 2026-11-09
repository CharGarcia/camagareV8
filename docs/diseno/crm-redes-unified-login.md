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
| 1 Verificación en Meta | en curso |
| 2 Servicio de API | pendiente |
| 3 Conexión y llamadores | pendiente |
| 4 Pantalla y manuales | pendiente |
| 5 Prueba real | pendiente |
