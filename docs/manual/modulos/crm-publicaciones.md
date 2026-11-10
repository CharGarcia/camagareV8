---
titulo: Publicaciones
resumen: Preparar, aprobar, programar y publicar en Instagram y LinkedIn desde el sistema, con biblioteca de imágenes y videos, redacción con IA, estadísticas y los clientes que trajo cada publicación.
categoria: Ventas
ruta_modulo: modulos/crm_publicaciones
tipo: modulo
visibilidad: todos
etiquetas: publicaciones, publicar, enlace, link, url, pagina web, tarjeta con link, redes sociales, instagram, linkedin, programar publicacion, calendario de contenidos, calendario editorial, post, posteo, reel, carrusel, historia, biblioteca de medios, imagenes, videos, subir fotos, aprobar publicacion, aprobacion, redactar con ia, inteligencia artificial, hashtags, texto alternativo, estadisticas, metricas, alcance, me gusta, seguidores, resultados, marketing, community manager, crm
version: 1.2
orden: 17
estado: activo
---

**Publicaciones** sirve para preparar lo que la empresa publica en **Instagram** y
**LinkedIn**, programarlo para el día y la hora que convenga y ver después cómo le
fue: cuántas personas lo vieron y, sobre todo, cuántos **contactos** y
**oportunidades** del CRM trajo.

## Qué es y para qué sirve

- Tener en un **calendario** todo lo que se va a publicar y lo que ya salió.
- Publicar en varias cuentas a la vez, con un texto distinto para cada red si hace falta.
- Que alguien **apruebe** antes de publicar (opcional).
- Que la **IA** proponga el texto, los hashtags y el texto alternativo de las imágenes.
- Medir: alcance, vistas, me gusta, comentarios, guardados y, en Instagram, las
  conversaciones, contactos y ventas que vinieron de los comentarios.

## Requisitos previos

- Las cuentas conectadas en **Configuración del CRM → Canales**:
  - **Instagram**: cuenta profesional conectada con la app de Meta de la empresa.
    Si la cuenta se conectó antes de existir este módulo, hay que pulsar
    **Reconectar** una vez y aceptar los permisos nuevos (publicar y estadísticas).
  - **LinkedIn**: la app de LinkedIn de la empresa y el perfil (o la página)
    conectados. Ver la guía **App de LinkedIn para publicar (CRM)**.
- El servidor debe tener configurada su dirección pública con **https**
  (`app_url`): Instagram descarga desde ahí las imágenes y videos. Si falta, la
  publicación queda con error y el mensaje lo dice.
- Para **Proponer con IA**: la IA de la empresa configurada con **Anthropic
  (Claude)** en **IA Soporte → Configuración**.

## Cómo se usa

1. **Biblioteca** → **Subir**: elija imágenes o videos (puede elegir varios).
2. **Nueva publicación**:
   - **Contenido**: nombre interno (no se publica), texto y los archivos (botón
     **Elegir de la biblioteca**; el orden es el del carrusel).
   - **Dónde**: marque las cuentas, el formato de cada una y, si quiere, un texto
     propio para esa red.
   - **Vista previa**: cómo se verá aproximadamente en cada red.
3. Pulse **Guardar** (queda en **Borrador**).
4. Elija la fecha y hora y pulse **Programar**; con la fecha vacía el botón dice
   **Publicar ahora** y sale en el siguiente minuto.
5. En **Resultados** se ve el estado de cada red, el enlace a la publicación y,
   con el tiempo, sus estadísticas.

En el **Calendario**, el doble clic en un día crea una publicación para ese día.

## Formatos que se pueden publicar

| Red | Formato | Qué lleva |
|-----|---------|-----------|
| Instagram | Imagen | Exactamente una imagen |
| Instagram | Carrusel | De 2 a 20 imágenes o videos (todas se recortan a la proporción de la primera) |
| Instagram | Reel | Un video vertical |
| LinkedIn | Solo texto | Sin archivos |
| LinkedIn | Con imágenes | De 1 a 20 imágenes (sin video por ahora) |

Límites de texto: Instagram hasta 2200 caracteres y 30 hashtags; LinkedIn hasta
3000 caracteres. El modal cuenta los caracteres de cada red mientras escribe.

## Biblioteca de imágenes y videos

- **Imágenes**: JPG, PNG, WEBP o GIF de hasta 20 MB y al menos 320 píxeles por
  lado. Se guardan siempre como **JPEG** (lo único que publica Instagram), giradas
  como en el celular, con fondo blanco donde había transparencia y con el lado
  mayor en 1440 píxeles.
- **Videos (reels)**: MP4 o MOV en H.264 o H.265, de **3 a 90 segundos**, hasta
  **100 MB** y 1920 píxeles por lado. Conviene vertical (9:16).
- El sistema avisa (sin impedir) si una imagen se va a recortar en el feed
  (Instagram admite de 4:5 vertical a 1,91:1 horizontal) o si el video no es vertical.
- Cada archivo tiene su **texto alternativo** (para personas con discapacidad
  visual) y la marca **«Lo generó una IA»**.
- Lo que no se usa en **90 días** se borra solo. No se puede eliminar un archivo
  que usa una publicación que todavía no sale.

## Poner un enlace (link)

- **En el texto**: escriba la dirección completa (`https://…`). **LinkedIn** la
  vuelve clicable. **Instagram** nunca hace clicables los enlaces del texto: lo
  habitual es escribir «enlace en la bio».
- **Sobre la imagen (solo LinkedIn)**: en **Dónde**, elija el formato **Enlace
  (tarjeta con link)** y llene la **Dirección del enlace** y el **Título de la
  tarjeta**. La publicación sale como una tarjeta: la primera imagen de
  «Contenido» y el título llevan a esa dirección.
  - LinkedIn usa **una sola imagen** en este formato (o ninguna); no se combina
    con varias imágenes.
  - El título lo escribe usted: LinkedIn no lo toma de la página.
  - En borrador pueden quedar vacíos; al **programar** o **enviar a revisión**
    se exigen los dos.
- **Instagram no permite** poner un enlace sobre la imagen de una publicación ni
  de un reel.

## Redactar con IA

En **Contenido → Proponer con IA**, escriba de qué trata (producto, oferta, a quién
va, tono). La IA propone un **texto general**, una versión para **Instagram**
(con 3 a 10 hashtags), otra para **LinkedIn** (más profesional) y, si lo marca,
el **texto alternativo** de cada imagen (la IA mira las imágenes).

- Cada propuesta (texto general, Instagram y LinkedIn) aparece en un **cuadro
  editable**: corríjala ahí mismo antes de usarla. El contador de al lado muestra
  los caracteres (y los hashtags, en Instagram).
- **Usar** lleva el texto —con sus correcciones— al campo general o al texto propio de esa red;
  **Aplicar a las imágenes** guarda los textos alternativos en la biblioteca.
- **No guarda nada en la publicación**: revise, corrija y pulse Guardar.
- No inventa precios, fechas ni descuentos: lo que falte lo deja entre
  corchetes, por ejemplo `[precio]`.
- Máximo **20 propuestas por hora** por empresa. El consumo lo cobra Anthropic
  directamente a la empresa.

La casilla **Contenido hecho con IA** (Instagram la muestra como etiqueta) se marca
a mano y corresponde a imágenes o videos generados con IA, no al texto.

## Aprobación previa

Con el engranaje de la tarjeta superior (permiso de Modificar) se activa **Exigir
aprobación antes de publicar**. Entonces:

1. Quien prepara la publicación pulsa **Enviar a revisión**.
2. Un usuario con permiso de **Modificar** pulsa **Aprobar** (o **Devolver a borrador**).
3. Solo lo **aprobado** se puede programar. Si se edita algo aprobado, vuelve a
   borrador y hay que aprobarlo de nuevo.

En la misma ventana se fija el **tope de publicaciones por día** de la empresa
(lo que pase del tope sale al día siguiente).

## Estados

| Estado | Qué significa |
|--------|---------------|
| Borrador | Se puede editar. |
| En revisión / Aprobada | Solo con aprobación previa activa. |
| Programada | Sale en la fecha indicada. **Desprogramar** la devuelve a borrador mientras nada haya salido. |
| Publicando | Saliendo en las redes (los videos pueden tardar unos minutos en procesarse). |
| Publicada | Salió en todas las cuentas elegidas. |
| Publicada en parte | Salió en algunas y en otras hubo error. |
| Con error | No salió. En **Resultados** está el motivo y el botón **Reintentar**. |
| Cancelada | Se canceló lo que faltaba. |

## Estadísticas y lo que trajo cada publicación

- El sistema toma las estadísticas **a las 24 horas, a los 7 días y a los 30
  días** de publicada. Se ven en **Resultados**.
  - **Instagram**: alcance, vistas, me gusta, comentarios, guardados y compartidos.
  - **LinkedIn**: solo de **páginas de empresa** (alcance, impresiones, clics, me
    gusta, comentarios, compartidos). LinkedIn no entrega las de perfiles personales.
- **Lo que trajo al CRM** (Instagram): las conversaciones que vinieron de los
  comentarios de esa publicación, los contactos nuevos y las oportunidades que
  salieron de ellos, con lo ganado. Los mensajes directos no se pueden atribuir a
  una publicación (Instagram no dice de cuál vienen).
- En **CRM → Reportes** está el resumen **Redes sociales** del período.

## Permisos

- **Ver**: calendario, lista, biblioteca y resultados.
- **Crear**: subir archivos, crear publicaciones, enviar a revisión, programar,
  desprogramar, cancelar lo pendiente, reintentar y proponer con IA.
- **Modificar**: editar publicaciones, **aprobar** y devolver, editar los datos
  de un archivo y la configuración (aprobación y tope).
- **Eliminar**: publicaciones y archivos de la biblioteca.
- **Acceso total**: ve las publicaciones de toda la empresa. **Sin acceso total**,
  las que creó o tiene a cargo.

## Reglas de negocio

- Un doble clic en Guardar no crea dos publicaciones, y una publicación nunca
  sale dos veces en la misma cuenta (aunque se corte la conexión justo al publicar).
- Instagram tiene su propio tope de publicaciones por API en 24 horas; si se
  alcanza, el sistema espera una hora y vuelve a intentar.
- Si falla la red, se reintenta sola hasta **3 veces** (a los 5 y 15 minutos).
  Una cuenta desconectada o un problema de configuración da error de inmediato.
- **Eliminar** una publicación la quita del sistema; lo que ya salió sigue en la
  red y hay que borrarlo allí.
- El acceso a LinkedIn dura unos 60 días y LinkedIn no permite renovarlo
  automáticamente: el sistema avisa 10 días antes para reconectar.

## Integraciones con otros módulos

- **Configuración del CRM**: cuentas de Instagram y LinkedIn.
- **IA Soporte**: la IA de la empresa para «Proponer con IA».
- **Bandeja social** y **CRM**: los comentarios de cada publicación llegan a la
  Bandeja; de ahí salen los contactos y oportunidades que se le atribuyen.

## Errores frecuentes

- **«La cuenta … no dio permiso para publicar»**: reconéctela en Configuración
  del CRM y acepte todos los permisos.
- **«El servidor no tiene configurada su dirección pública con https (app_url…)»**:
  avise al administrador del sistema.
- **«Una publicación de imagen en Instagram lleva exactamente una imagen»** (y
  similares): revise el formato elegido en «Dónde» y los archivos.
- **«Instagram: se alcanzó el tope de … publicaciones en 24 horas»**: se publicará
  sola cuando haya cupo.
- **«No se pudo confirmar si la publicación salió»** (LinkedIn): la conexión se
  cortó justo al publicar. Revise la red: si no salió, use **Reintentar**.
- **«El video debe durar entre 3 y 90 segundos»** / **«…H.264 o H.265»**:
  expórtelo de nuevo como MP4 en 1080p.
- **«Llegó al máximo de 20 propuestas por hora»**: espere un rato.
- **«El formato Enlace necesita la dirección web»** / **«…un título para la
  tarjeta»**: complételos en «Dónde», en la cuenta de LinkedIn.
- **«La dirección del enlace no es válida»**: escríbala completa, empezando por
  `https://`.
- **«Una publicación de enlace en LinkedIn lleva una sola imagen»**: deje una
  imagen en «Contenido» o cambie el formato a «Con imágenes».

## Historial de cambios

- **1.0** — Versión inicial: calendario, lista y biblioteca; Instagram (imagen,
  carrusel, reel) y LinkedIn (perfil y página); aprobación previa; redacción con
  IA; estadísticas y atribución al CRM.
- **1.1** — Las propuestas de la IA (texto general, Instagram y LinkedIn) se
  pueden editar en su propio cuadro antes de pulsar **Usar**.
- **1.2** — LinkedIn: formato **Enlace** (tarjeta con imagen que lleva a una
  dirección web). Un enlace con `#` escrito en el texto ya no se rompe.
