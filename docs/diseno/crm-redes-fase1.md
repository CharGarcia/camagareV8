# CRM y Redes Sociales — Diseño de la fase 1

> Documento de diseño para revisión. **No es el manual del usuario** (ese irá en
> `docs/manual/modulos/crm.md` y `docs/manual/modulos/bandeja-social.md` cuando los módulos
> existan) y no se publica en `/documentacion`. Fecha: 08-11-2026. Estado: **aprobado
> (08-11-2026)**: las 8 decisiones de la sección 3 se aprobaron tal como están propuestas.

## 1. Objetivo

Dar a cada empresa un **embudo de ventas (CRM)** dentro de CaMaGaRe y una **bandeja de
mensajes de Instagram** conectada a ese embudo, de modo que una persona que escribe por
Instagram (DM o comentario) quede registrada como **contacto**, se le pueda abrir una
**oportunidad**, se la siga con **actividades** y, al ganarla, termine como **cliente con su
proforma** usando los módulos que ya existen (Clientes, Proformas, Vendedores).

La fase 1 **no lleva IA ni publicaciones**: deja lista la base de datos, el webhook, la
bandeja y el embudo sobre los que la fase 2 montará el bot y la fase 3 las publicaciones.
Todo lo que se diseña aquí ya contempla esas fases (campos `autor = 'bot'`, pausa del bot,
cola de eventos), para no rehacer tablas después.

## 2. Lo que ya existe y se reutiliza

| Pieza | Dónde | Uso en esta fase |
|-------|-------|------------------|
| Webhook de Meta para WhatsApp | `app/controllers/WhatsappWebhookController.php` (público) | Modelo a seguir. Instagram llega por un webhook **nuevo** (otro `object`), no se toca este |
| Chat de WhatsApp | `WhatsappChatController` + `whatsapp_chats` / `whatsapp_mensajes` | Sigue igual. El CRM **enlaza** chats de WhatsApp a un contacto (ver decisión 2) |
| Respuestas rápidas | `WhatsappChatController::getRespuestasRapidas()` | Se reutiliza el mismo catálogo en la bandeja de Instagram |
| Cifrado | `App\Helpers\CryptoHelper::encriptar/desencriptar` | Tokens de Instagram cifrados en BD |
| Clientes / Vendedores / Proformas | `ClienteService`, tabla `vendedores`, `ProformaService::crear()` | Conversión de la oportunidad ganada |
| Avisos del navbar | Contador unificado (`avisos-navbar`) | Mensajes sin leer + actividades vencidas |
| Auditoría, permisos, guardado único | `LogSistemaService`, `BaseModuloController`, `GuardadoUnicoService` | Igual que cualquier módulo |

**Brechas del código actual que el diseño nuevo NO repite** (se anotan para corregir aparte):

- El webhook de WhatsApp **no valida la firma** `X-Hub-Signature-256` de Meta: cualquiera que
  conozca la URL puede inyectar mensajes falsos.
- No **deduplica** por `meta_message_id`: Meta reintenta los eventos y un reintento crea el
  mensaje dos veces.
- No filtra **empresa activa** al resolver `phone_number_id` → empresa (regla §6 de endpoints públicos).
- `WhatsappConfig` guarda el `access_token` **en texto plano** y arma el SQL con `escape()` en
  vez de consultas preparadas.

## 3. Decisiones tomadas (08-11-2026)

Aprobada la columna «Propuesta» en las 8; la alternativa queda solo como referencia.

| # | Tema | Decisión | Alternativa descartada |
|---|------|-----------|-------------|
| 1 | Canales de la fase 1 | **Instagram** (DM + comentarios) por API. **LinkedIn y otros** se registran **a mano** como origen del contacto (sin API: LinkedIn no abre su bandeja a terceros) | Esperar aprobación de LinkedIn como partner antes de arrancar |
| 2 | WhatsApp | **No se migra.** Su chat sigue en su módulo; el contacto del CRM guarda `id_whatsapp_chat` y la ficha muestra un botón «Abrir chat de WhatsApp». La bandeja unificada (IG + WA en una sola lista) queda para la fase 2 | Migrar WhatsApp a las tablas nuevas ya en fase 1 (más riesgo: el chat actual está en producción) |
| 3 | App de Meta | **Cambiada el 08-11-2026 por el usuario: cada empresa registra SU app de Meta** (App ID, App Secret, token del webhook) en Configuración del CRM, guardada por `id_empresa` en `crm_meta_apps` con secretos cifrados; la configura cualquier usuario con permiso en el módulo. El webhook y los callbacks de privacidad validan la firma con la app de la empresa dueña de la cuenta y acotan todo a las empresas de esa app. Cada empresa crea su app y pasa la revisión de Meta | (Aprobada originalmente: una sola app «CaMaGaRe» en `config/local.php` para todas las empresas) |
| 4 | Registros propios (§6) | Sin `t`: el usuario ve las oportunidades que **creó o que tiene asignadas** (`created_by = yo OR id_responsable = yo`). En un CRM el vendedor recibe leads que no creó; sin esto, nunca los vería | Estricto §6: solo `created_by` (el vendedor no vería los leads que le asignan) |
| 5 | Asignación de conversaciones nuevas | Configurable por empresa: **sin asignar** (todos los con permiso las ven en «Sin asignar»), **rotación** entre usuarios marcados, o **fija** a un usuario | — |
| 6 | Embudos | **Varios embudos por empresa** (p. ej. «Ventas», «Postventa»), cada uno con sus etapas. Al crear la empresa se siembra uno por defecto | Uno solo por empresa |
| 7 | Al ganar | **Ganar exige cliente**: si el contacto no tiene, se abre el modal de Clientes (crear al vuelo) y queda enlazado. La proforma es **opcional** (botón «Crear proforma» desde la oportunidad) | Crear la proforma automáticamente al ganar |
| 8 | Valor de la oportunidad | Monto estimado manual. Si tiene proforma enlazada, el valor pasa a ser el **total de la proforma** (solo lectura) | Siempre manual |

## 4. Alcance de la fase 1

**Entra**

1. **Configuración CRM** (`modulos/crm_configuracion`): conectar/desconectar Instagram,
   embudos y etapas, motivos de pérdida, orígenes, regla de asignación.
2. **Webhook de Instagram** público, con firma, deduplicación, empresa activa y cola.
3. **Bandeja social** (`modulos/bandeja_social`): lista de conversaciones de Instagram (DM y
   comentarios), lectura, respuesta, adjuntos de imagen, respuestas rápidas, asignar, cerrar,
   «Crear oportunidad» y enlazar a un contacto existente.
4. **CRM** (`modulos/crm`): vista **Kanban** (arrastrar entre etapas) y vista **Lista** estándar
   (§9: orden, buscador, paginación, PDF/Excel, columnas), ficha de oportunidad con pestañas
   *Datos*, *Conversación*, *Actividades*, *Historial*.
5. **Contactos CRM**: personas/empresas que aún no son clientes (lead). Se enlazan a `clientes`
   al convertir.
6. **Actividades**: tareas, llamadas, reuniones y notas con fecha y responsable; aviso en el
   navbar cuando vencen.
7. **Conversión**: ganar → cliente; proforma desde la oportunidad (con `id_oportunidad`).
8. **Reportes básicos dentro del CRM**: valor del embudo por etapa, ganadas/perdidas por mes,
   por origen y por responsable (tarjeta de control §9).
9. Cron de **renovación del token** de Instagram (dura 60 días; se renueva a los 50).
10. Manuales `docs/manual/modulos/crm.md`, `bandeja-social.md`, `config/crm-configuracion.md`.

**Queda para fases siguientes**

- Fase 2: bot con IA (respuesta automática, calificación, traspaso a humano), bandeja
  unificada con WhatsApp, Facebook Messenger.
- Fase 3: publicaciones programadas (Instagram, LinkedIn si aprueban), métricas de posts,
  comentarios de LinkedIn.
- Fase 4: automatizaciones de seguimiento (recordatorio si el lead no responde en N días),
  formularios web que crean leads, campañas.

## 5. Modelo de datos

Todas son **operativas**: `id_empresa` + `created_at, updated_at, created_by, updated_by,
eliminado, deleted_at, deleted_by` (§5). Se omiten abajo para abreviar.

### 5.1 `crm_canales` — cuenta conectada por empresa

| Campo | Tipo | Nota |
|-------|------|------|
| `id` | serial | |
| `tipo` | varchar(20) | `instagram` (fase 2: `whatsapp`, `messenger`) |
| `id_externo` | varchar(64) | IG user id de la cuenta profesional (resuelve empresa en el webhook) |
| `usuario` | varchar(100) | @usuario, para mostrar |
| `token_cifrado` | text | `CryptoHelper::encriptar()` del token de larga duración |
| `token_expira_at` | timestamp | para el cron de renovación |
| `permisos` | jsonb | scopes concedidos |
| `ventana_humana` | boolean | `true` si la app tiene aprobado el tag `HUMAN_AGENT` (7 días) |
| `estado` | varchar(15) | `activo` / `desconectado` / `token_vencido` |

Índice único parcial: `(tipo, id_externo) WHERE eliminado = false` — una cuenta de Instagram
no puede estar conectada a dos empresas a la vez.

### 5.2 `crm_contactos` — la persona (lead o cliente)

| Campo | Tipo | Nota |
|-------|------|------|
| `id` | serial | |
| `nombre` | varchar(200) | |
| `empresa_contacto` | varchar(200) | empresa donde trabaja (B2B, LinkedIn) |
| `cargo` | varchar(100) | |
| `telefono`, `correo` | varchar | |
| `instagram_id` | varchar(64) | IGSID (id del usuario en la conversación) |
| `instagram_usuario` | varchar(100) | @usuario |
| `linkedin_url` | varchar(300) | registro manual |
| `id_cliente` | int null | se llena al convertir; FK lógica a `clientes` |
| `id_whatsapp_chat` | int null | enlace al chat de WhatsApp existente (decisión 2) |
| `id_origen` | int | FK `crm_origenes` |
| `id_responsable` | int null | usuario |
| `notas` | text | |

Índice único parcial `(id_empresa, instagram_id) WHERE eliminado = false AND instagram_id IS NOT NULL`.

### 5.3 `crm_conversaciones` y `crm_mensajes`

`crm_conversaciones`:

| Campo | Tipo | Nota |
|-------|------|------|
| `id` | serial | |
| `id_canal` | int | FK `crm_canales` |
| `tipo` | varchar(15) | `dm` / `comentario` |
| `id_hilo_externo` | varchar(100) | IGSID del usuario (dm) o id del comentario raíz (comentario) |
| `id_publicacion_externa` | varchar(64) | media id del post (solo comentario) |
| `id_contacto` | int null | |
| `id_responsable` | int null | asignación |
| `estado` | varchar(15) | `abierta` / `cerrada` |
| `bot_pausado` | boolean | reservado fase 2 (default `false`) |
| `ultimo_mensaje` | varchar(200) | extracto para la lista |
| `ultimo_mensaje_at` | timestamp | orden de la bandeja |
| `ultima_entrada_at` | timestamp | último mensaje **del cliente** → ventana de 24 h |
| `sin_leer` | int | se incrementa con `UPDATE … SET sin_leer = sin_leer + 1` (atómico, sin leer-calcular-escribir) |

Único parcial `(id_empresa, id_canal, tipo, id_hilo_externo) WHERE eliminado = false`.

`crm_mensajes`:

| Campo | Tipo | Nota |
|-------|------|------|
| `id` | serial | |
| `id_conversacion` | int | |
| `direccion` | char(3) | `IN` / `OUT` |
| `autor` | varchar(10) | `cliente` / `usuario` / `bot` (fase 2) |
| `id_usuario` | int null | quién respondió (OUT) |
| `tipo` | varchar(15) | `text`, `image`, `video`, `audio`, `story_mention`, `story_reply`, `share`, `comment` |
| `texto` | text | |
| `adjunto_ruta` | varchar(300) | archivo descargado (las URL de Meta caducan) |
| `id_externo` | varchar(150) | `mid` del mensaje / id del comentario |
| `estado_envio` | varchar(15) | `enviado` / `leido` / `fallido` |
| `error` | text | |
| `contenido` | jsonb | payload crudo |
| `fecha` | timestamp | hora del evento en Meta |

**Único** `(id_empresa, id_externo)` (parcial, no nulo) → un reintento de Meta no duplica
(`INSERT … ON CONFLICT DO NOTHING`).

### 5.4 `crm_webhook_eventos` — cola y respaldo

Global de entrada (aún no se sabe la empresa cuando llega), **sin** `id_empresa` obligatorio:
`id, recibido_at, objeto, payload jsonb, firma_ok boolean, estado (pendiente/procesado/error/ignorado),
intentos, error, id_empresa null, procesado_at`. Se purga a los 30 días por cron. Permite
reprocesar si algo falló y, en fase 2, que el bot trabaje fuera del webhook.

### 5.5 Embudo

- `crm_embudos`: `id, nombre, es_defecto, orden, estado`.
- `crm_etapas`: `id, id_embudo, nombre, orden, probabilidad (0-100), tipo (abierta/ganada/perdida), color`.
  Cada embudo tiene exactamente **una** etapa `ganada` y **una** `perdida` (las valida Rules).
- `crm_origenes`: `id, nombre, tipo_canal` (sembrados: Instagram DM, Instagram comentario,
  LinkedIn, WhatsApp, Referido, Web, Otro).
- `crm_motivos_perdida`: `id, nombre`.

Sembrado por defecto al activar el módulo: embudo «Ventas» con *Nuevo (10%) → Contactado (20%)
→ Calificado (40%) → Propuesta (60%) → Negociación (80%) → Ganado (100%) / Perdido (0%)*.

### 5.6 `crm_oportunidades`

| Campo | Tipo | Nota |
|-------|------|------|
| `id` | serial | |
| `numero` | int | correlativo interno por empresa (no es documento SRI): `pg_advisory_xact_lock(hashtext('crm_oportunidad_num:' \|\| id_empresa))` + `MAX(numero)+1` en la misma transacción |
| `titulo` | varchar(200) | |
| `id_contacto` | int | |
| `id_embudo`, `id_etapa` | int | |
| `id_responsable` | int | usuario |
| `id_vendedor` | int null | vendedor del sistema (para reportes de ventas/comisiones) |
| `valor_estimado` | numeric(14,2) | |
| `fecha_cierre_estimada` | date | |
| `id_origen` | int | |
| `id_conversacion` | int null | la conversación que la originó |
| `id_proforma` | int null | |
| `id_cliente` | int null | al ganar |
| `estado` | varchar(10) | `abierta` / `ganada` / `perdida` (redundante con el tipo de etapa, para filtros rápidos) |
| `id_motivo_perdida`, `detalle_perdida` | | obligatorios al perder |
| `cerrada_at` | timestamp | |
| `etapa_desde` | timestamp | para «días en la etapa» |
| `orden_kanban` | numeric | posición dentro de la columna |

`crm_oportunidades_etapas_log`: `id_oportunidad, id_etapa_anterior, id_etapa_nueva, fecha,
id_usuario` → tiempo por etapa y tasa de conversión (además del `log_sistema`).

### 5.7 `crm_actividades`

`id, id_oportunidad null, id_contacto null, tipo (tarea/llamada/reunion/nota/correo),
titulo, descripcion, vence_at, id_responsable, completada, completada_at, completada_por`.

### 5.8 Cambios en tablas existentes

- `proformas_cabecera`: columna `id_oportunidad int null` + índice. Al crear la proforma desde la
  oportunidad se llena; al aprobarse la proforma (flujo de aprobación existente) la oportunidad
  pasa a la etapa con tipo `abierta` y probabilidad más alta si estaba antes (configurable, apagado por defecto).

## 6. Webhook de Instagram

Controlador **público** nuevo `InstagramWebhookController` (agregar a `$publicControllers`),
URL `/instagram-webhook`. Se suscribe en la app de Meta a `messages`, `messaging_seen`,
`comments`, `mentions`.

```
GET  → verificación: hub_verify_token contra el token global de la app (config/app.php),
       no por empresa (decisión 3: una sola app).
POST → 1. Leer el cuerpo crudo y validar X-Hub-Signature-256 = HMAC-SHA256(app_secret).
          Firma inválida → 403, no se guarda nada.
       2. INSERT en crm_webhook_eventos (estado 'pendiente').
       3. Responder 200 de inmediato (Meta exige respuesta rápida o reintenta).
       4. Procesar en la misma petición tras fastcgi_finish_request() si está disponible;
          si no, lo toma el cron cada minuto. Los dos caminos usan el mismo Service.
```

`CrmWebhookService::procesar($idEvento)` (en transacción):

1. Resolver la empresa con `CrmCanalRepository::getByIdExterno($igUserId)` **con**
   `JOIN empresas … estado = '1' AND eliminado = false` y canal `activo`. Si no resuelve →
   evento `ignorado` (sin decir el motivo, regla §6).
2. Por cada mensaje: `pg_advisory_xact_lock(hashtext('crm_conv:' || id_canal || ':' || hilo))`
   → obtener o crear la conversación → `INSERT … ON CONFLICT DO NOTHING` del mensaje → si se
   insertó, actualizar extracto, `ultima_entrada_at` y `sin_leer + 1` en el mismo `UPDATE`.
3. Descargar adjuntos a `storage/crm_media/{id_empresa}/` (las URL de Meta expiran).
4. Contacto: si no existe uno con ese `instagram_id`, se crea con nombre/@usuario
   (consulta de perfil del IGSID) y origen *Instagram DM* / *Instagram comentario*; se aplica
   la regla de asignación (decisión 5).
5. Eventos `read` → `estado_envio = 'leido'` en los OUT anteriores.
6. Marcar el evento `procesado` (o `error` con el mensaje, para reintento por cron hasta 5 veces).

El canal se identifica por el `entry[].id` del payload (IG user id de la cuenta). Los
comentarios propios de la empresa (autor = la cuenta conectada) se ignoran para no
generar conversaciones consigo misma.

## 7. Envío de respuestas

`InstagramApiService` (único punto que habla con `graph.instagram.com`):

- `enviarMensaje($idCanal, $igsid, $texto|$imagen)` → `POST /{ig-user-id}/messages`.
- `responderComentario($idCanal, $idComentario, $texto)` → respuesta pública.
- `respuestaPrivada($idCanal, $idComentario, $texto)` → DM a quien comentó (Meta lo permite
  una vez por comentario y dentro de 7 días).
- `perfil($idCanal, $igsid)`, `renovarToken($idCanal)`, `descargarAdjunto(...)`.

**Ventana de 24 horas** (regla de Meta), validada en `CrmMensajeRules::puedeEnviar()`:

- `now() - ultima_entrada_at <= 24 h` → se envía normal.
- Entre 24 h y 7 días y el canal tiene `ventana_humana = true` → se envía con la etiqueta
  `HUMAN_AGENT` (solo usuarios, **nunca** el bot de la fase 2).
- Fuera de eso → el cuadro de texto se desactiva con el aviso *«Han pasado más de 24 h desde
  el último mensaje del cliente. Instagram no permite escribirle hasta que vuelva a escribir.»*

El mensaje OUT se guarda primero en estado `enviando`, se llama a la API y se actualiza con
`id_externo` o `fallido` + error. Botón «Reintentar» en los fallidos. Anti doble envío: la
capa global `anti-doble-envio.js` + bandera `enviando` en la vista.

## 8. Capas (§3)

| Capa | Archivos |
|------|----------|
| Controllers | `controllers/InstagramWebhookController.php` (público) · `controllers/modulos/CrmController.php` · `BandejaSocialController.php` · `CrmConfiguracionController.php` (incluye el callback OAuth `conectarInstagramCallback`) |
| Services | `Services/modulos/CrmOportunidadService.php` (crear, mover etapa, ganar, perder, reabrir) · `CrmContactoService.php` · `CrmActividadService.php` · `CrmConversacionService.php` (responder, asignar, cerrar, crear oportunidad desde conversación) · `CrmWebhookService.php` · `CrmConfiguracionService.php` (canales, embudos, sembrado) · `Services/InstagramApiService.php` |
| Rules | `Rules/modulos/CrmOportunidadRules.php` (etapa pertenece al embudo, motivo obligatorio al perder, cliente obligatorio al ganar) · `CrmEmbudoRules.php` (una ganada y una perdida por embudo, no borrar etapa con oportunidades) · `CrmMensajeRules.php` (ventana 24 h) |
| Repositories | `repositories/modulos/CrmOportunidadRepository.php` (`MAPA_ORDEN`, `FiltrosBusqueda`) · `CrmContactoRepository` · `CrmConversacionRepository` · `CrmMensajeRepository` · `CrmActividadRepository` · `CrmCanalRepository` · `CrmEmbudoRepository` · `CrmWebhookEventoRepository` |
| Vistas | `views/modulos/crm/` (index con Kanban/Lista, modal oportunidad, modal contacto, modal actividad) · `views/modulos/bandeja_social/` · `views/modulos/crm_configuracion/` |
| JS | `public/js/modulos/crm.js` (Kanban con SortableJS vía CDN permitido), `bandeja_social.js`, `crm_configuracion.js` |
| Cron | `crm_procesar_eventos` (cada minuto), `crm_renovar_tokens` (diario), `crm_purgar_eventos` (semanal), `crm_actividades_vencidas` (aviso navbar) |

## 9. Pantallas

### 9.1 CRM (`/modulos/crm`)

- Tarjeta de control fija (§9 `cmg-control-card`): selector de **embudo**, responsable,
  origen, rango de fecha de cierre, buscador (`FiltrosBusqueda`: `etapa:`, `origen:`,
  `responsable:`, `valor:>1000`, `cierre:2026-11..2026-12`), botones Limpiar / **Mostrar**.
  Pie: oportunidades abiertas, valor total, valor ponderado (valor × probabilidad), ganadas del mes.
- Conmutador **Kanban / Lista** (se recuerda en preferencias).
- **Kanban**: una columna por etapa abierta con suma de valor; tarjeta = título, contacto,
  valor, días en etapa, ícono del origen, avatar del responsable, punto rojo si tiene actividad
  vencida. Arrastrar → `moverEtapaAjax`. Soltar en **Ganado** abre el diálogo de ganar (exige
  cliente); en **Perdido**, el de motivo.
- **Lista**: tabla estándar con `cmg-table-card`, orden multi-columna, columnas ocultables,
  PDF/Excel.
- **Modal oportunidad**: barra de acciones arriba (Crear proforma, Ganar, Perder, Abrir
  conversación, Abrir chat de WhatsApp); pestañas *Datos*, *Conversación* (mensajes de la
  conversación de origen, solo lectura con enlace a la bandeja), *Actividades*, *Historial*
  (cambios de etapa). Eliminar en el footer a la izquierda. Sin pestaña «Información».
  Guardado único con `token_guardado`.

### 9.2 Bandeja social (`/modulos/bandeja_social`)

Diseño de tres columnas (como el chat de WhatsApp actual):

1. **Lista** de conversaciones con filtros *Mías / Sin asignar / Todas*, *DM / Comentarios*,
   *Abiertas / Cerradas*, buscador; contador de no leídos.
2. **Hilo**: burbujas IN/OUT, adjuntos, indicador de ventana (*«Puedes responder durante 18 h
   más»*), respuestas rápidas, envío de imagen. En comentarios: la miniatura del post arriba y
   dos botones de respuesta: **Pública** / **Privada (DM)**.
3. **Panel del contacto**: datos, oportunidades abiertas, botones *Crear oportunidad*,
   *Enlazar a contacto existente*, *Asignar*, *Cerrar conversación*.

Actualización por **sondeo cada 10 s** de `getNovedadesAjax?desde=…` (igual que el chat de
WhatsApp); websockets no se justifican en esta fase.

### 9.3 Configuración CRM (`/modulos/crm_configuracion`)

- **Canales**: botón *Conectar Instagram* (OAuth Instagram Login → callback guarda el token
  cifrado), estado, vencimiento del token, *Desconectar*, *Probar envío*.
- **Embudos y etapas**: CRUD con orden arrastrable, probabilidad y color.
- **Orígenes**, **motivos de pérdida**, **regla de asignación**, **usuarios en rotación**.

## 10. Permisos (§6)

Submódulos nuevos en `submodulos_menu` y `config/modulos_mvc.php`:

| Ruta | Para qué | Notas |
|------|----------|-------|
| `modulos/crm` | Embudo, contactos, actividades | `d` elimina oportunidades; sin `t` → creadas por él **o** asignadas (decisión 4) |
| `modulos/bandeja_social` | Atender mensajes | sin `t` → solo conversaciones asignadas a él + «Sin asignar» |
| `modulos/crm_configuracion` | Canales, embudos | Normalmente solo administradores |

Si se aprueba la decisión 4, `getBaseWhere()` no cambia: el repositorio del CRM añade su
propia condición `(created_by = :u OR id_responsable = :u)` cuando `$idUsuarioFiltro !== null`,
documentada en el propio repositorio.

## 11. Auditoría y transacciones

- `LogSistemaService::registrar()` en: crear/editar/eliminar oportunidad, cambio de etapa,
  ganar/perder/reabrir, conectar/desconectar canal, asignar conversación, enviar mensaje
  (solo id, no el texto completo, para no duplicar datos personales en el log).
- Toda escritura en transacción. Candados: conversación por hilo (§6 de este documento),
  `numero` de oportunidad por candado de empresa (§5.6), mover etapa con `SELECT … FOR UPDATE` de la
  oportunidad (dos usuarios arrastrando la misma tarjeta).

## 12. Seguridad y privacidad

- Firma HMAC obligatoria en el webhook; `app_secret` y `verify_token` en `config/app.php`
  (fuera del repositorio), nunca en BD.
- Tokens de Instagram cifrados (`CryptoHelper`); nunca se envían al navegador.
- Callback OAuth con `state` aleatorio guardado en sesión (anti-CSRF del flujo OAuth) y
  ligado a la empresa activa.
- Adjuntos servidos por un endpoint con permiso (`serveMediaAjax`), no por URL pública
  directa, igual que `WhatsappChatController::serveMedia()`.
- **Eliminación de datos (requisito de Meta)**: endpoint público `data-deletion` que recibe
  el `signed_request` de Meta y marca como eliminados los mensajes y el contacto de ese IGSID
  (eliminación lógica + borrado del texto y adjuntos, que es lo que Meta exige).
- Desconectar el canal deja de recibir eventos; el historial queda.

## 13. Trámites con Meta (en paralelo al desarrollo)

1. Verificar el negocio en Meta Business Manager.
2. App «CaMaGaRe» tipo Business con producto **Instagram** (API con Instagram Login).
3. Permisos a solicitar en App Review: `instagram_business_basic`,
   `instagram_business_manage_messages`, `instagram_business_manage_comments`
   (+ `Human Agent` opcional). Requiere video mostrando el flujo completo, política de
   privacidad pública y URL de eliminación de datos.
4. Mientras no esté aprobado, la app funciona en **modo desarrollo** solo con cuentas de
   Instagram agregadas como evaluadoras: suficiente para construir y probar.

## 14. Plan de trabajo

| Paso | Contenido | Dependencia |
|------|-----------|-------------|
| 1 | SQL de tablas + sembrado + `proformas.id_oportunidad` | — |
| 2 | Embudos, contactos, oportunidades, actividades (CRM sin Instagram) | 1 |
| 3 | Kanban + lista + ficha + conversión a cliente/proforma | 2 |
| 4 | Configuración: conexión OAuth de Instagram y token | 1 + app de Meta en modo desarrollo |
| 5 | Webhook + cola + procesamiento | 4 |
| 6 | Bandeja social y envío | 5 |
| 7 | Reportes del embudo, avisos navbar, crons | 3, 6 |
| 8 | Manuales y registro en menú/permisos | todo |

Los pasos 2–3 (CRM puro) y 4–6 (Instagram) son independientes y pueden avanzar en paralelo.

## 15. Estado de avance (08-11-2026)

| Paso | Estado | Dónde |
|------|--------|-------|
| 1 SQL | Hecho (incluye el ALTER de `crm_mensajes.direccion` a VARCHAR) | `database/2026-11-08_crm_fase1.sql` |
| 2 CRM (servidor) | Hecho | `Crm*Repository`, `Crm*Rules`, `Crm*Service`, `CrmController`, `CrmConfiguracionController` |
| 3 Pantalla CRM + Proformas | Hecho (proforma por sesión, URL limpia) | `views/modulos/crm/`, `public/js/modulos/crm.js` |
| 4 Configuración + OAuth Instagram | Hecho (+ cron de renovación) | `views/modulos/crm_configuracion/`, `CrmCanalService`, `InstagramApiService` |
| 5 Webhook + cola | Hecho (`/instagram-webhook`, cron cada minuto) | `InstagramWebhookController`, `CrmWebhookService` |
| 6 Bandeja social | Hecho (texto e imágenes por DM) | `views/modulos/bandeja_social/`, `CrmBandejaService` |
| 7 Reportes y avisos | Hecho (pestaña Reportes; avisos «Instagram» y «CRM» en el navbar) | `CrmReporteService`, `ContadoresNavbarService` |
| 8 Manuales | Hechos (`crm`, `crm-configuracion`, `bandeja-social`, `config/instagram-app-meta`) | `docs/manual/` |

**Pendiente de la fase 1 (fuera de código o para después):**
- Registrar en el menú y permisos `modulos/crm`, `modulos/crm_configuracion` y `modulos/bandeja_social` (lo hace el administrador).
- Cada empresa crea su app de Meta y la registra en Configuración del CRM → Canales (decisión 3 cambiada: app por empresa, tabla `crm_meta_apps`; `config/app.php` → `meta_instagram` solo guarda lo técnico).
- ~~Endpoint de eliminación de datos (§12)~~ — hecho: `/instagram-datos/eliminar`, `/desautorizar` y `/estado` (`InstagramDatosController`, `CrmPrivacidadService`, tabla `crm_eliminaciones_datos`).
- ~~Enviar **imágenes** desde la Bandeja~~ — hecho: `enviarImagenAjax` guarda en `storage/crm_media` y Meta la descarga de `/instagram-datos/imagen?t=` (token `{id}.{vence}.{HMAC}` con `CryptoHelper::firma`, 30 min, solo salientes `tipo='image'` de empresas activas). El eco del webhook se adopta por `tipo='image' AND texto IS NULL`.
- ~~Unir contactos duplicados~~ — hecho: `CrmContactoService::unir()` (mueve oportunidades, conversaciones y actividades; completa vacíos; el IGSID del unido queda en `crm_contactos_alias`, que `getPorInstagramId()` también consulta). La eliminación de datos de Meta borra el alias (`quitarAlias`).
