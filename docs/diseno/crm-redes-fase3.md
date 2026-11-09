# CRM y redes sociales — Fase 3: publicaciones en Instagram y LinkedIn, y métricas

Estado: **aprobado** (08-11-2026): las 8 decisiones de §7 con la opción recomendada en cada una
(LinkedIn perfil ya + página cuando la apruebe LinkedIn; app de LinkedIn por empresa; imagen, carrusel
y reel; videos de hasta 100 MB y 90 s con purga a 90 días; aprobación previa opcional, apagada por
defecto; redacción con IA como borrador; métricas de Instagram y de la página de LinkedIn; atribución
al CRM). Sigue a `crm-redes-fase1.md` (CRM + Bandeja) y
`crm-redes-fase2.md` (asistente virtual), ambas terminadas en código.

## 1. Objetivo

Que la empresa **prepare, programe y publique** desde el sistema en su cuenta de Instagram y en
LinkedIn, vea **cómo le fue** a cada publicación y, sobre todo, **qué ventas trajo**: cuántos
contactos, conversaciones y oportunidades del CRM nacieron de cada una. Esto último es lo que una
herramienta de redes suelta no puede dar y el sistema sí, porque ya tiene el CRM y la Bandeja.

## 2. Lo que permiten las plataformas (verificado en noviembre de 2026)

### Instagram (API con Instagram Login — la misma de las fases 1 y 2)

- Permiso nuevo: `instagram_business_content_publish` (y `instagram_business_manage_insights` para
  métricas). Las cuentas ya conectadas tendrán que **reconectarse** una vez para darlos, y la app de
  Meta de cada empresa debe tenerlos aprobados (App Review) para usarse con su cuenta en producción.
- Flujo: crear un *contenedor* (`POST /{ig-id}/media`) → esperar a que esté `FINISHED`
  (`GET /{contenedor}?fields=status_code`) → publicar (`POST /{ig-id}/media_publish`).
- Formatos: imagen, **carrusel** (hasta 20 piezas, todas recortadas a la proporción de la primera),
  **reel** (video) e **historia**. Imágenes **solo JPEG**.
- **El archivo debe estar en una URL pública** cuando Meta lo descarga: se reutiliza el enlace firmado
  y temporal que ya usa la Bandeja para enviar imágenes (`/instagram-datos/imagen`), ampliado a medios
  de publicaciones.
- **No hay programación nativa**: la hace el sistema (cron). Tope de publicaciones por API en 24 h
  (la documentación dice 100 en un lugar y 50 en otro): se consulta
  `GET /{ig-id}/content_publishing_limit` antes de publicar.
- `alt_text` (texto alternativo) en imágenes; `is_ai_generated` para declarar contenido hecho con IA.
- Métricas por publicación: `GET /{media-id}/insights` (alcance, vistas, me gusta, comentarios,
  guardados, compartidos…). Las historias solo dan métricas durante 24 h.

### LinkedIn (Posts API, `/rest/posts`)

- **Perfil personal** de quien conecta: producto *Share on LinkedIn* (`w_member_social`),
  **autoservicio, sin revisión**. Texto, imagen (Images API: `initializeUpload` y luego subir el
  archivo), documento PDF. Las **métricas** de publicaciones personales están restringidas: no se
  pueden leer por API sin aprobación.
- **Página de empresa**: requiere `w_organization_social`, que solo da la *Community Management API*
  con **aprobación de LinkedIn** (sitio web y dominio verificados, la app verificada por la página, y la
  persona que conecta debe ser administradora de la página). Sin plazo publicado de aprobación. Con ella
  sí hay métricas de la página.
- Igual que con Meta: **cada empresa registra su propia app de LinkedIn** (Client ID y Client Secret)
  en Configuración del CRM.

## 3. Cómo funciona (flujo)

1. **Biblioteca de medios** de la empresa: se suben imágenes (se convierten a JPEG y se ajustan al
   tamaño admitido) y videos para reels. Quedan en `storage/` (no público).
2. **Nueva publicación**: texto, medios (orden arrastrable), **destinos** (cuenta de Instagram como
   publicación, carrusel, reel o historia; LinkedIn perfil o página) y fecha: *publicar ahora* o
   *programar*. Vista previa por destino (recorte de Instagram, límite de caracteres de cada red).
3. **Asistente de redacción (opcional)**: con la IA de la empresa (Anthropic), propone el texto, las
   variantes por red (Instagram más corto con hashtags, LinkedIn más profesional) y el texto
   alternativo de las imágenes. Siempre lo revisa y edita una persona antes de guardar.
4. **Aprobación (si la empresa la activa)**: borrador → *en revisión* → *aprobada*. Solo se publica lo
   aprobado.
5. El **cron** (cada minuto) publica lo que vence, una red a la vez, con el mismo patrón de las fases
   anteriores (candado, estado por destino, reintentos limitados, error visible). Cada destino tiene su
   propio estado: puede salir en Instagram y fallar en LinkedIn sin afectar al otro.
6. Al publicar se guarda el **id y el enlace** de la publicación en cada red.
7. **Métricas**: el cron las actualiza a las 24 h, 7 días y 30 días de publicada (y una vez al día la
   cantidad de seguidores de la cuenta).
8. **Atribución al CRM**: los comentarios de Instagram ya llegan a la Bandeja con el id de la
   publicación (`crm_conversaciones.id_publicacion_externa`). Con eso cada publicación muestra cuántas
   **conversaciones**, **contactos nuevos** y **oportunidades** (y cuánto valor ganado) salieron de ella.

## 4. Pantallas

- Submódulo nuevo **Publicaciones** (`modulos/crm_publicaciones`) bajo el menú CRM:
  - **Calendario** (mes/semana) con lo programado y lo publicado, por color de estado.
  - **Lista** estándar (§9: orden, buscador, filtros, PDF/Excel, columnas por usuario).
  - Modal de la publicación con pestañas *Contenido*, *Destinos y fecha*, *Vista previa*, *Resultados*
    (métricas + atribución al CRM).
  - **Biblioteca de medios**.
- **Configuración del CRM → Canales**: registrar la app de LinkedIn y conectar perfil / página;
  reconectar Instagram para los permisos nuevos.
- **CRM → Reportes**: pestaña *Redes* (publicaciones del período, alcance, interacción, y contactos y
  oportunidades atribuidos por red y por publicación).

## 5. Datos (SQL nuevo, idempotente, todo con `id_empresa` y campos de auditoría)

- `crm_medios`: archivos de la biblioteca (tipo, ruta, ancho/alto, duración, peso, texto alternativo).
- `crm_publicaciones`: texto base, estado (`borrador`, `en_revision`, `aprobada`, `programada`,
  `publicando`, `publicada`, `con_error`, `cancelada`), fecha programada, aprobada por/cuándo,
  `es_contenido_ia`.
- `crm_publicaciones_medios`: medios de cada publicación y su orden.
- `crm_publicaciones_destinos`: una fila por red/cuenta: canal, formato, texto propio de esa red,
  estado, intentos, error, id externo, enlace, publicada_at.
- `crm_publicaciones_metricas`: foto de las métricas de cada destino en cada corte (24 h, 7 d, 30 d).
- `crm_canales`: admite `tipo = 'linkedin'` (perfil u organización) y `crm_meta_apps` tiene su
  equivalente `crm_linkedin_apps` (Client ID + Secret cifrado, por empresa).

## 6. Seguridad y reglas

- Credenciales y tokens cifrados como en las fases anteriores; nunca al navegador ni a los logs.
- El enlace público de los medios es firmado, temporal y solo para medios de publicaciones de una
  empresa activa (mismo patrón que `/instagram-datos/imagen`).
- **Guardado único** al crear publicaciones (§8) y candado por destino al publicar: un doble clic o dos
  cron simultáneos nunca publican dos veces.
- Topes: el de Instagram (consultado en vivo) y uno propio por empresa y día.
- Eliminar una publicación en el sistema **no** la borra de la red (se avisa); cancelar solo aplica a
  lo que aún no salió.
- La app móvil iOS no cambia (§13).

## 7. Decisiones para aprobar

1. **LinkedIn**: (a) **perfil personal ya** (autoservicio) y **página de empresa** cuando la empresa
   consiga la aprobación de LinkedIn — el mismo código sirve para ambos; o (b) solo página de empresa;
   o (c) LinkedIn manual (el sistema prepara el texto y la imagen, la persona los pega). **Recomendado: (a).**
2. **App de LinkedIn por empresa** (como la de Meta), registrada en Configuración del CRM.
   **Recomendado: sí.**
3. **Formatos de Instagram en esta fase**: imagen + carrusel + **reel**; las **historias** quedan para
   después (duran 24 h y casi no tienen métricas). **Recomendado.**
4. **Videos (reels)**: tope de **100 MB y 90 s** por video para no llenar el disco del servidor; los
   medios sin uso se purgan a los 90 días. ¿De acuerdo con esos topes?
5. **Aprobación previa**: configurable por empresa (apagada por defecto). Si está activa, publica solo
   lo que aprobó un usuario con permiso de **Modificar**. **Recomendado.**
6. **Asistente de redacción con IA**: sí, con la IA de la empresa (Anthropic), siempre como borrador
   editable. La casilla *«Contenido hecho con IA»* (que Instagram muestra como etiqueta) la marca la
   persona; el sistema la sugiere solo si la **imagen** la generó una IA, no por el texto.
7. **Métricas**: Instagram por publicación (24 h / 7 d / 30 d) + seguidores diarios; LinkedIn solo de
   la **página** (las de perfil personal no se pueden leer). **Recomendado.**
8. **Atribución al CRM** por publicación (conversaciones, contactos, oportunidades y valor ganado),
   también en *CRM → Reportes → Redes*. **Recomendado.**

## 8. Pasos (después de aprobar)

1. SQL + `InstagramApiService` (publicar, estado del contenedor, límite, insights) + cliente de LinkedIn
   (OAuth, Images API, Posts API) con pruebas de transporte falso.
2. Biblioteca de medios (subida, conversión a JPEG, validación de video, enlace firmado).
3. Publicaciones: servicio, reglas, guardado único, aprobación, publicación por cron con candado y reintentos.
4. Pantallas: calendario, lista, modal, biblioteca; LinkedIn en Canales; reconexión de Instagram.
5. Asistente de redacción con IA.
6. Métricas, atribución al CRM y pestaña *Redes* en Reportes.
7. Pruebas y manuales (`crm-publicaciones`, actualizar `crm-configuracion`, `crm`, guía de la app de
   LinkedIn).

## 9. Avance

| Paso | Estado | Dónde |
|------|--------|-------|
| 1 SQL + clientes de API | **Hecho** (08-11-2026) | `database/2026-11-08_crm_fase3_publicaciones.sql` (8 tablas); `InstagramApiService` (crearContenedor imagen/reel/carrusel, estadoContenedor, publicarContenedor, limitePublicacion, datosPublicacion, metricasPublicacion, seguidores); `LinkedInApiService` (OAuth perfil/página, perfil, paginasAdministradas, subirImagen, publicar, textoLinkedIn); `CrmLinkedinAppRepository`; `config/app.php` → permisos nuevos de Instagram y bloque `linkedin` |
| 2 Biblioteca de medios | **Hecho** (08-11-2026) | `CrmMedioService`, `CrmMedioRepository`, `CrmMedioRules`, `App\Helpers\Mp4Info`; endpoints en `CrmPublicacionesController` (mediosAjax, subirMedioAjax, guardarMedioAjax, eliminarMedioAjax, medio); enlace público `/instagram-datos/medio?t=` (2 h, admite Range); purga diaria en el cron |
| 3 Servicio de publicaciones + cron | **Hecho** (08-11-2026) | `CrmPublicacionService` (crear con guardado único, editar, revisión/aprobación, programar, desprogramar, cancelar, reintentar, eliminar), `CrmPublicadorService` (cron), `CrmPublicacionRepository`, `CrmPublicacionRules`; endpoints en `CrmPublicacionesController` (datosAjax, listarAjax, calendarioAjax, publicacionAjax, guardarAjax, accionAjax, eliminarAjax, guardarConfigAjax) |
| 4 Pantallas | **Hecho** (08-11-2026) | `views/modulos/crm_publicaciones/` (index + modal_publicacion), `public/js/modulos/crm_publicaciones.js`; `CrmPublicacionesController::index/resumenAjax/exportExcel/exportPdf`; LinkedIn en Configuración del CRM (`CrmLinkedinService`, `linkedinAppAjax`, `guardarLinkedinAppAjax`, `quitarLinkedinAppAjax`, `conectarLinkedinAjax`, `linkedinCallback`); submódulo «Publicaciones» en `database/2026-11-08_crm_menu.sql` |
| 5 Redacción con IA | **Hecho** (08-11-2026) | `CrmRedaccionService`, `AnthropicProvider::generarJson` (`IaProviderJsonInterface`, salida con esquema JSON + imágenes en base64), `CrmPublicacionesController::proponerIaAjax`, panel «Proponer con IA» en el modal |
| 6 Métricas y atribución | **Hecho** (08-11-2026) | `CrmMetricaService` + `CrmMetricaRepository` (cron: cortes y seguidores), `LinkedInApiService::estadisticasPublicacion`, «Resultados» en el modal (`publicacionAjax` → `resultados`), bloque «Redes sociales» en CRM → Reportes (`CrmReporteService::redes`) |
| 7 Pruebas y manuales | **Hecho** (08-11-2026) | Nuevos: `docs/manual/modulos/crm-publicaciones.md`, `docs/manual/guias/linkedin-app.md`. Actualizados: `guias/instagram-app-meta` 2.1, `crm-configuracion` 1.3, `crm` 1.7, `bandeja-social` 1.4 |

**Fase 3 terminada en código** (08-11-2026). Nada se probó en el navegador ni contra Instagram/LinkedIn reales (todas las pruebas con transportes/APIs falsos y transacciones revertidas).

Para ponerla en marcha (producción):
1. `git pull` y `composer install` (SDK de Anthropic de la fase 2).
2. SQL, en orden: `2026-11-08_crm_fase1.sql`, `2026-11-08_crm_fase2_bot.sql`, `2026-11-08_crm_fase3_publicaciones.sql` y `2026-11-08_crm_menu.sql` (crea/actualiza el módulo CRM con sus 4 submódulos).
3. `config/local.php` → `app_url` con https (Instagram descarga los medios desde ahí).
4. Registrar `modulos/crm_publicaciones` en `config/modulos_mvc.php` y asignar permisos.
5. Cada empresa: agregar a su app de Meta los permisos `instagram_business_content_publish` e `instagram_business_manage_insights` y reconectar Instagram; registrar su app de LinkedIn.
6. Sincronizar el manual en `/documentacion/gestion`.

Notas del paso 1:
- Las cuentas de Instagram ya conectadas deben **reconectarse** para conceder `instagram_business_content_publish` e `instagram_business_manage_insights`; la app de Meta de cada empresa debe tenerlos (agregarlo a la guía `instagram-app-meta` en el paso 7).
- Canales de LinkedIn: `crm_canales.tipo = 'linkedin_perfil' | 'linkedin_pagina'`, `id_externo` = URN de la persona u organización.
- LinkedIn: hashtags como plantilla `{hashtag|\#|x}` y caracteres reservados escapados; la subida de imágenes (lleva el token) solo se acepta a HTTPS de `linkedin.com`/`licdn.com`.
- `http()` de los dos clientes es `protected` solo para sustituirlo en las pruebas. Pruebas: `test_fase3_paso1.php` 38/38.

Notas del paso 2:
- Imágenes: se aceptan JPG/PNG/WEBP/GIF (hasta 20 MB, mínimo 320 px por lado, máx. 40 Mpx) y se guardan **siempre como JPEG** (GD): giradas por EXIF, fondo blanco, lado mayor 1440 px, calidad 88. Aviso (no bloquea) si la proporción sale del rango del feed (4:5 a 1,91:1).
- Videos: MP4/MOV, H.264 o H.265 (`avc1/avc3/hvc1/hev1`), 3-90 s, hasta 100 MB y 1920 px; se leen con `Mp4Info` (cajas ISO BMFF, sin ffprobe). Aviso si no es vertical.
- Archivos en `storage/crm_media/{empresa}/pub/{AAAA-MM}/` (no públicos). Eliminar = fila lógica + borra el archivo tras confirmar. La purga diaria (desde las 03:00) borra lo que no se usa hace 90 días y ninguna publicación pendiente necesita.
- El enlace público usa otro contexto de firma que el de la Bandeja: un token de uno no sirve en el otro. Pruebas: `test_fase3_medios.php` 35/35.

Notas del paso 3:
- Formatos: Instagram imagen (1 imagen), carrusel (2-20, imágenes y videos), reel (1 video); LinkedIn texto o imagen (1-20 imágenes; sin video por ahora). Texto: Instagram ≤ 2200 y ≤ 30 hashtags; LinkedIn ≤ 3000. Cada destino puede llevar su propio texto.
- Programar exige: destinos válidos, cuenta `activo` y con el permiso de publicar concedido (`instagram_business_content_publish`, `w_member_social`, `w_organization_social`). Con aprobación activa, solo se programa lo aprobado; editar algo aprobado lo devuelve a borrador.
- Cola: `tomarDestinos()` alquila cada destino 10 min (`siguiente_intento_at`) con `FOR UPDATE SKIP LOCKED`, solo de empresas activas.
- Nunca dos veces: el destino pasa a `publicando` antes de la llamada que publica. Interrumpido ahí: Instagram consulta el contenedor (PUBLISHED → publicada; FINISHED → publica); LinkedIn queda `con_error` pidiendo revisar la red. Si `media_publish` falla pero el contenedor ya está PUBLISHED, se marca publicada sin repetir.
- Carrusel: los hijos se guardan en `contenedores` y no se recrean; se espera a que los videos estén FINISHED (hasta 30 esperas de 1 min).
- Reintentos: 3 (a los 5 y 15 min) para errores de la red; cuenta vencida, datos o configuración (p. ej. sin `app_url` https) → `con_error` de una vez. Topes: Instagram en vivo (espera 1 h) y el diario de la empresa (sale al día siguiente).
- Pruebas: `test_fase3_publicaciones.php` 42/42.

Notas del paso 4:
- Pantalla: tarjeta de control fija (estado, buscador, indicadores) y vistas Calendario (mes, lunes primero, doble clic en un día = nueva), Lista (orden multi-columna, columnas por usuario, filas por página, PDF/Excel) y Biblioteca (subir varios, datos del archivo, eliminar).
- Modal: barra de acciones arriba (Programar/Publicar ahora, Enviar a revisión, Aprobar, Devolver, Desprogramar, Cancelar lo pendiente), pestañas Contenido / Dónde / Vista previa / Resultados (las dos últimas se pueden ocultar por usuario), Eliminar al pie a la izquierda. Toda acción guarda antes lo editable. Mientras se publica, se refresca cada 15 s.
- LinkedIn: tarjeta «App de LinkedIn de la empresa» en Canales (Client ID/Secret cifrado, casilla «Community Management API aprobada», dirección de retorno para copiar). Conectar perfil o perfil + páginas. LinkedIn no da renovación a apps sin convenio: el cron solo marca `token_vencido` al vencer y la pantalla avisa 10 días antes.
- Instagram conectado antes de la fase 3: la tarjeta pide reconectar para publicar (`falta_publicar`). El asistente virtual lista solo cuentas de Instagram.
- Pruebas: `test_fase3_linkedin.php` 18/18 y acciones reales de los controladores con `run_pub_accion.php`.

Notas del paso 5:
- Propone texto general, versión Instagram (3-10 hashtags, sin enlaces) y versión LinkedIn (profesional, 0-5 hashtags) de las redes elegidas, y el texto alternativo de hasta 10 imágenes (la IA las mira). Respuesta con esquema JSON (`output_config.format`), así que siempre es válida.
- No inventa datos: lo que falte lo deja como `[precio]`. Las indicaciones y el borrador van delimitados y marcados como contenido, no instrucciones.
- No guarda nada: la pantalla muestra la propuesta y «Usar» la lleva al texto general o al texto propio de cada red; «Aplicar a las imágenes» guarda los textos alternativos en la biblioteca (permiso Modificar).
- Tope de 20 propuestas por hora y empresa; el consumo (tokens, costo, modelo) queda en `log_sistema` con `accion = 'REDACTAR_IA'`, sin los textos. La marca «hecho con IA» de Instagram no se pone por el texto (decisión 6).
- Pruebas: `test_fase3_redaccion.php` 21/21.

Notas del paso 6:
- Cortes a las 24 h, 7 y 30 días (hasta 40 días después de publicada), de a 30 por tick; `ON CONFLICT … DO NOTHING` sobre el índice único (destino, corte). Si la red falla, el corte se guarda con `crudo.error` y no se reintenta cada minuto (el siguiente corte trae los datos). Perfil personal de LinkedIn: corte marcado `sin_datos` (LinkedIn no entrega esas estadísticas).
- Instagram: reach, views, likes, comments, saved, shares (y completa el enlace si faltaba). LinkedIn página: `organizationalEntityShareStatistics` (Rest.li `shares=List(urn)` / `ugcPosts=List(urn)`): alcance = únicas, vistas = impresiones, clics.
- Seguidores de Instagram una vez al día (desde las 06:00) en `crm_seguidores_diarios`.
- Atribución (solo Instagram, por comentarios): conversaciones con `id_publicacion_externa` = id de la publicación; contactos nuevos = creados desde que salió; oportunidades = de esas conversaciones o de esos contactos creadas después de publicar, con lo ganado. Los mensajes directos que provoca una publicación no se pueden atribuir (Instagram no dice de qué publicación vienen).
- Reportes: por red (publicaciones, alcance, interacciones, conversaciones, contactos, oportunidades, valor ganado), las 10 que más trajeron y seguidores al inicio/fin del período; respeta registros propios. Sin el SQL de la fase 3 el bloque no aparece.
- Pruebas: `test_fase3_metricas.php` 23/23.

## 10. Fuentes consultadas

- Meta — Content Publishing (Instagram Platform): https://developers.facebook.com/documentation/instagram-platform/content-publishing
- Meta — Instagram Media Insights: https://developers.facebook.com/docs/instagram-platform/reference/instagram-media/insights/
- Meta — Changelog Instagram Platform: https://developers.facebook.com/documentation/instagram-platform/changelog
- LinkedIn — Posts API: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api
- LinkedIn — Increasing access (Community Management API): https://learn.microsoft.com/nl-nl/linkedin/marketing/increasing-access
- LinkedIn — Images API: https://learn.microsoft.com/de-ch/linkedin/marketing/community-management/shares/images-api?view=li-lms-2024-10
- Guías de terceros usadas solo como contraste (no autoritativas): postproxy.dev, zernio.com, bundle.social.
