# Optimización del servidor — OPcache y caché de assets

Runbook para el droplet de producción (`/var/www/sistema`, Ubuntu 24.04, **Apache + mod_php 8.3**).
Dos cambios independientes, ambos reversibles en menos de un minuto.

| Parte | Qué hace | Riesgo | Reversión |
|-------|----------|--------|-----------|
| **A · OPcache** | PHP deja de recompilar 4.583 archivos en cada petición | Bajo | `rm` del .ini + `systemctl restart apache2` |
| **B · Cache-Control** | El navegador deja de *preguntar* por cada CSS/JS | Bajo-medio | `a2disconf cmg-cache` + `systemctl reload apache2` |

**Infraestructura confirmada el 13-09-2026** (no asumir, se verificó): el ERP es
**`https://erp.camagare.com.ec`** (`www.camagare.com.ec` es el sitio de marketing en Astro, otro
servidor con nginx). Lo sirve **Apache/2.4.58 con mod_php** (`php_module (shared)`), **PHP 8.3.6**,
vhosts `sistema.conf` / `sistema-le-ssl.conf`. `headers_module` **no** está cargado → la Parte B
necesita `a2enmod headers`. nginx está `failed` pero `enabled`, con un vhost propio para
`erp.camagare.com.ec` — ver la advertencia del final.

> **Hacer una parte por vez**, medir, y solo entonces la siguiente. Si algo va mal hay que saber qué fue.

Ninguna de las dos edita un archivo existente del sistema: cada una **crea** un archivo nuevo y se
activa/desactiva con un comando. Eso es lo que las hace reversibles de verdad.

---

# PASO A PASO COMPLETO

## Orden obligatorio

```
1. Subir el código  →  2. Desplegar  →  3. Verificar  →  4. OPcache  →  5. Cache-Control
```

**El código va primero, sin excepción.** Si el `Cache-Control` se aplica antes de que el código con
`asset_ver()` esté en el servidor, cada carga de página generaría una URL nueva marcada como
"cachear para siempre" y llenaría de basura el navegador de los clientes (ver la advertencia de la
Parte B).

## Paso 1 — En tu PC: subir el código

Trabajas en dos máquinas, así que primero traer lo que haya:

```bash
git pull --rebase origin main
```

Preparar los cambios **excluyendo el submódulo `web`** (aparece siempre como modificado y no es parte
del ERP; commitearlo borraría 1.723 archivos del sitio web):

```bash
git add -A && git reset -q web && git status --short | grep -c "^[MA]"
```

Debe decir **138** (136 archivos modificados + 2 nuevos). Si dice 139, `web` se colló en el commit:
repite el `git reset -q web` y vuelve a contar. Comprobado en tu working tree antes de darte el
comando.

```bash
git commit -m "$(cat <<'EOF'
Caché de assets: reemplazar time() por el helper asset_ver()

Las 287 referencias a CSS/JS llevaban ?v=<?= time() ?>, distinto en cada
petición, así que el navegador redescargaba todo el CSS y el JS en cada carga
de página. El helper asset_ver() (app/helpers/helpers.php) devuelve el
filemtime del archivo: la URL cambia solo cuando el archivo cambia, y git pull
actualiza el mtime, así que se mantiene la garantía de que nadie se queda con
JS viejo tras un deploy.

Se versionaron además las 7 referencias que no llevaban ?v= (app.css y
theme.css en login/404, theme.css en head.php, face_asistencia.js,
reasignar-establecimiento.js): ahora el 100% de los assets propios lleva
versión, que es la condición para poder cachear con seguridad.

Incluye el SQL de los 5 índices multiempresa ya aplicados en producción, el
diagnóstico de escala y el runbook de optimización del servidor.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

```bash
git push origin main
```

## Paso 2 — En el servidor: desplegar

Conéctate como lo haces siempre y despliega:

```bash
cd /var/www/sistema && git pull origin main
```

**No hace falta nada más en este paso:**
- `composer.lock` no cambió → no hay que correr composer.
- El `.sql` que viaja en este commit son **los 5 índices que ya aplicaste el 12-09**. No lo vuelvas a
  ejecutar (y si lo hicieras, es idempotente: no haría nada).

## Paso 3 — Verificar que el cache-busting quedó activo

Esta es la comprobación que autoriza el paso 5. Ejecuta el mismo comando **dos veces seguidas**:

```bash
curl -s https://erp.camagare.com.ec/ | grep -oE "(app\.css|csrf\.js)\?v=[0-9]+" | sort -u
```

- Si las dos veces sale **el mismo número** → `asset_ver()` está funcionando. Puedes seguir.
- Si el número **cambia** entre una y otra → sigue el `time()`: el `git pull` no trajo el código o
  Apache sirve otra copia. **No continúes al paso 5** hasta resolverlo.

## Paso 4 — Medir la línea base

Apunta estos cinco números antes de tocar nada más:

```bash
for i in 1 2 3 4 5; do curl -s -o /dev/null -w "%{time_total}s\n" https://erp.camagare.com.ec/; done
```

## Paso 5 — Parte A (OPcache)

Sigue la **Parte A** de este documento, de principio a fin. Al terminar, repite el comando del paso 4
y compara. La primera petición tras el `restart` será más lenta (caché vacía): mira de la segunda en
adelante.

Después entra al sistema y usa algo real: abre una factura, guarda un registro, mira un reporte.

## Paso 6 — Parte B (Cache-Control)

Solo si el paso 3 salió bien y el paso 5 quedó estable. Sigue la **Parte B** de este documento.

## Paso 7 — Vigilar

**Las 2 horas siguientes:** entra a `/config/errores-sistema` y revisa que no aparezca nada nuevo, y
mira el log de Apache:

```bash
tail -n 50 /var/log/apache2/error.log
```

**A los 2-3 días:** el paso 5 de la Parte A (ocupación de la caché de OPcache) y la consulta de
`idx_scan` de los índices (en `database/20260912_indices_calientes_multiempresa.sql`, paso 6).

## Cuándo hacerlo

Martes a jueves, temprano. **Nunca del 1 al 9** (declaraciones al SRI) ni a fin de mes. Un domingo
también sirve: es el día de menos actividad.

## Si algo va mal

| Problema | Qué hacer |
|----------|-----------|
| El sistema no carga tras la Parte A | `rm -f /etc/php/8.3/apache2/conf.d/99-cmg-opcache.ini && systemctl restart apache2` |
| Estilos raros o assets que no se actualizan tras la Parte B | `a2disconf cmg-cache && systemctl reload apache2` |
| `configtest` no dice `Syntax OK` | **No recargues Apache.** Sin recargar, el sitio sigue con la configuración buena |
| Dudas sobre el código desplegado | `cd /var/www/sistema && git log --oneline -3` y, si hace falta, `git reset --hard <commit anterior>` |

---

## Antes de empezar: medir

Sin una medición previa no se puede saber si el cambio sirvió. Desde tu PC o desde el droplet:

```bash
for i in 1 2 3 4 5; do curl -s -o /dev/null -w "%{time_total}s\n" https://erp.camagare.com.ec/; done
```

Apunta los 5 números. Es el login: una página PHP real que no necesita sesión, así que mide
exactamente lo que arregla OPcache (el arranque y la compilación de PHP).

Y las cabeceras actuales de un asset:

```bash
curl -sI https://erp.camagare.com.ec/css/app.css | grep -iE "cache-control|expires|etag|last-modified"
```

Hoy no debería salir `Cache-Control` (por eso existe la Parte B).

---

# PARTE A · OPcache — ⚠️ YA ESTABA ACTIVO (verificado 13-09-2026)

**Conclusión tras aplicarlo: esta parte NO aporta la mejora que se esperaba, porque OPcache ya
funcionaba en producción desde el 27-05-2026.** Existe
`/etc/php/8.3/apache2/conf.d/10-opcache.ini → mods-available/opcache.ini` (instalado con la
extensión), y los valores por defecto de PHP ya son `opcache.enable=1` y `memory_consumption=128`.

Medido en caliente sobre Apache con `opcache_get_status()`: **72 archivos en caché, 19,1 MB usados de
128, 91,4% de hits**. El servidor no estaba recompilando PHP en cada petición.

El `99-cmg-opcache.ini` que se creó se deja puesto porque **fija explícitamente
`validate_timestamps=1` y `revalidate_freq=2`**, que es lo que hace que un `git pull` entre en vigor
sin recargar Apache. Hoy son los defaults, pero dejarlo escrito evita que una actualización de PHP o
de la distro cambie ese comportamiento sin que nadie se entere. Los otros dos valores propios
(`interned_strings_buffer=16`, `max_wasted_percentage=10`) son ajustes marginales.

**Por qué me equivoqué, para no repetirlo:** el diagnóstico decía "OPcache no aparece configurado en
ningún runbook", y de ahí inferí que faltaba. No aparecer en la documentación no es lo mismo que no
estar instalado. La verificación correcta habría sido `ls /etc/php/8.3/apache2/conf.d/` **antes** de
proponer el cambio.

**Implicación para el plan:** el margen de mejora del servidor no está aquí. Está en el polling de
5 segundos (`PLAN_ESCALA_5000.md`, A1), que sigue siendo el mayor consumidor de capacidad.

---

## Por qué (contexto original, ya resuelto)

El sistema tiene **4.583 archivos PHP** (1.484 propios + 3.099 de `vendor/`) y **50 MB de código
fuente**. Sin OPcache, PHP lee, parsea y compila a bytecode los archivos que necesita **en cada
petición**, y tira el resultado al terminar. OPcache guarda ese bytecode en memoria compartida y lo
reutiliza.

En un droplet de 1 vCPU esto es de los cambios con mejor relación beneficio/riesgo que existen.

## 1. Comprobar si ya está activo

```bash
php -m | grep -i opcache; ls /etc/php/8.3/apache2/conf.d/ | grep -i opcache
```

Si la extensión aparece pero `opcache.enable` no está en 1 para Apache, está cargada y apagada:
sigue igual con el paso 2.

## 2. Escribir la configuración

**No se edita `php.ini`.** En Ubuntu, PHP carga todo lo que haya en
`/etc/php/8.3/apache2/conf.d/`, así que se crea un archivo propio ahí. Ventajas: no se toca ningún
archivo del sistema, el prefijo `99-` garantiza que gana sobre cualquier valor anterior, y revertir
es borrar un archivo.

Pega esto completo en el servidor (crea el archivo de una vez, sin editor):

```bash
cat > /etc/php/8.3/apache2/conf.d/99-cmg-opcache.ini <<'EOF'
[opcache]
; OPcache guarda el bytecode compilado en memoria compartida (una sola copia para
; todos los procesos de Apache, no una por worker).
opcache.enable=1

; En CLI no: los scripts de cron y los workers son procesos de una sola ejecución,
; así que cachear no les sirve de nada y solo consumiría memoria.
opcache.enable_cli=0

; 128 MB de memoria compartida. El código fuente son 50 MB; el bytecode ocupa del
; orden de 2-3x, pero una petición solo carga una fracción, así que 128 MB sobran
; para lo que se usa de verdad. Verificar con el paso 4 y subir a 192 si se llena.
opcache.memory_consumption=128

; Tabla de cadenas compartidas (nombres de clases, funciones, literales).
opcache.interned_strings_buffer=16

; Número máximo de archivos cacheados. Hay 4.583; PHP redondea este valor al
; siguiente primo de su lista interna (10000 -> 16229 huecos), así que queda
; margen de sobra para crecer.
opcache.max_accelerated_files=10000

; ── CLAVE PARA EL DESPLIEGUE ────────────────────────────────────────────────
; validate_timestamps=1 hace que PHP compruebe la fecha del archivo y recompile
; solo si cambió. Con esto un `git pull` entra en vigor SOLO, sin recargar Apache.
;
; Poner 0 da algo más de rendimiento, pero entonces el código nuevo NO se activa
; hasta un reload manual: si alguna vez se olvida, el servidor sigue sirviendo la
; versión vieja y el síntoma es desconcertante ("desplegué y no cambió nada").
; No vale la pena por unos milisegundos: se deja en 1.
opcache.validate_timestamps=1

; Cada cuántos segundos se comprueba esa fecha. 2 s es imperceptible y significa
; que un despliegue está activo como máximo 2 segundos después del git pull.
opcache.revalidate_freq=2

; Conservar los docblocks: hay librerías que los leen por reflexión.
opcache.save_comments=1

; Si más del 10% de la memoria queda inservible por recompilaciones, OPcache se
; reinicia solo.
opcache.max_wasted_percentage=10
EOF
```

**El JIT se deja como viene (desactivado).** En aplicaciones web como esta no aporta nada medible y
añade una superficie de fallo que no compensa.

### Sobre la memoria en un servidor de 1,9 GB

Lo reservado son **~144 MB** de memoria compartida (128 de `memory_consumption` + 16 de
`interned_strings_buffer`), **una sola vez para todo Apache**, no por proceso.

Y el efecto neto sobre la RAM suele ser **a favor**: hoy, con Apache en modo prefork, cada worker
compila el bytecode en su propia memoria privada, así que hay tantas copias como workers. Con OPcache
ese bytecode pasa a la memoria compartida y los workers dejan de duplicarlo. Es habitual que el
consumo total baje en lugar de subir.

Para comprobarlo con datos, antes y después del `restart`:

```bash
free -m; ps -ylC apache2 --sort:rss | awk 'NR>1{s+=$8; n++} END{printf "workers: %d | RSS medio: %.0f MB | RSS total: %.0f MB\n", n, s/n/1024, s/1024}'
```

Si la memoria libre quedara ajustada, baja `opcache.memory_consumption` a 96 y vuelve a medir. Hay
2 GB de swap configurados como colchón, pero lo suyo es no llegar a usarlo.

## 3. Aplicar

```bash
apache2ctl configtest && systemctl restart apache2
```

`restart`, no `reload`: la memoria compartida de OPcache se reserva al arrancar el proceso.

## 4. Verificar que quedó bien

Vuelve a medir lo mismo de antes:

```bash
for i in 1 2 3 4 5; do curl -s -o /dev/null -w "%{time_total}s\n" https://erp.camagare.com.ec/; done
```

La primera petición tras el reinicio será igual o más lenta (la caché está vacía y se está
llenando). De la segunda en adelante es donde se ve la mejora.

Y comprueba que las páginas del sistema siguen funcionando: entra, abre una factura, guarda algo.

## 5. Revisar la ocupación de la caché a los 2-3 días

Para leer `opcache_get_status()` hace falta hacerlo desde Apache, no desde CLI (en CLI está
desactivado a propósito). **No dejes un script de diagnóstico en `public/`** — eso fue justo lo que
hubo que limpiar en la Fase 1 de seguridad. Crea el archivo, míralo y bórralo en el mismo minuto:

```bash
printf '<?php $s=opcache_get_status(); $m=$s["memory_usage"]; printf("usada: %%.1f MB | libre: %%.1f MB | desperdiciada: %%.1f%%%% | archivos: %%d/%%d | hits: %%.2f%%%%\\n", $m["used_memory"]/1048576, $m["free_memory"]/1048576, $m["current_wasted_percentage"], $s["opcache_statistics"]["num_cached_scripts"], $s["opcache_statistics"]["max_cached_keys"], $s["opcache_statistics"]["opcache_hit_rate"]);' > /var/www/sistema/public/_opc.php
curl -s https://erp.camagare.com.ec/_opc.php
rm -f /var/www/sistema/public/_opc.php
```

Cómo leerlo:

- **hits por encima del 95%** → funcionando como debe.
- **libre cerca de 0** o **archivos al tope** → subir `opcache.memory_consumption` a 192 y repetir.
- **desperdiciada alta** → normal justo después de un despliegue; se recicla sola.

## Reversión de la Parte A

Un comando y un reinicio. No hay nada que restaurar porque no se editó ningún archivo del sistema:

```bash
rm -f /etc/php/8.3/apache2/conf.d/99-cmg-opcache.ini && systemctl restart apache2
```

El sistema vuelve a como está hoy: más lento, nada más. OPcache no cambia ningún comportamiento,
solo evita recompilar.

---

# PARTE B · Cache-Control de los assets — ✅ APLICADO 13-09-2026

Verificado en producción justo después de aplicarlo:

```
curl -sI ".../css/app.css?v=123"  → Cache-Control: public, max-age=31536000, immutable
curl -sI ".../css/app.css"        → (ninguna cabecera Cache-Control)
```

Es decir: los assets versionados se cachean indefinidamente y los que no llevan `?v=` quedan fuera de
la regla, que es justo la salvaguarda contra congelar un archivo. `a2enmod headers` pidió un
`restart`, pero el `reload` (graceful) bastó para cargar el módulo.



## Por qué, y qué cambió antes para que esto sea seguro

Hasta el 12-09-2026 el CSS y el JS se servían con `?v=<?= time() ?>`: una URL distinta en cada
petición, así que el navegador **redescargaba todo en cada carga de página**. Ya está resuelto con
el helper `asset_ver()`, que pone la fecha de modificación del archivo.

Pero falta la otra mitad: **hoy Apache no manda `Cache-Control` para los assets**. Con lo ya hecho,
el navegador pide cada archivo y recibe un `304 Not Modified` — respuesta vacía, así que la
transferencia ya se ahorra. Lo que sigue costando es **la petición**: unas 8-10 por página, cada una
con su ida y vuelta, y cada una ocupando un proceso de Apache.

Con `Cache-Control` largo el navegador deja de preguntar: cero peticiones.

**Por qué es seguro ahora y no lo era antes:** el `max-age` largo se aplica **solo a las URLs que
traen `?v=`**, y desde el 12-09-2026 el 100% de los CSS y JS propios lo llevan (se verificó archivo
por archivo). Si un asset se sirviera sin `?v=`, entraría por la regla corta, no por la larga.

**`/uploads/` queda excluido a propósito:** los logos y las fotos de producto se reemplazan
conservando el nombre, así que un `max-age` largo dejaría al cliente viendo su logo anterior.

## ⚠️ El orden importa: primero el código, después esta parte

**Despliega el código con `asset_ver()` (`git pull`) ANTES de aplicar el `Cache-Control`.**

Si se hace al revés, durante ese rato las URLs seguirían llevando `?v=<hora actual>` — distinta en
cada carga de página — y cada una entraría marcada como `immutable`: el navegador del usuario
acumularía **una entrada de caché nueva por cada página que abre**, todas guardadas "para siempre".
No rompe el sistema ni se ve nada raro, pero llena de basura el disco de cada cliente.

Con el código desplegado primero, la URL solo cambia cuando el archivo cambia, que es justo lo que
hace seguro el `immutable`.

## Dónde ponerlo: en un `conf-available` propio, no en el vhost ni en el .htaccess

**No se edita ningún archivo existente.** Apache en Ubuntu tiene `/etc/apache2/conf-available/` para
exactamente esto: se crea un archivo nuevo y se activa con `a2enconf`.

Por qué así y no de las otras dos formas:

- **`.htaccess`**: un error de sintaxis devuelve **HTTP 500 en todo el sistema al instante** y no hay
  forma de validarlo antes. Descartado.
- **Editar el vhost**: se valida con `configtest`, pero hay que modificar un archivo que ya funciona,
  y un descuido al insertar el bloque lo rompe.
- **`conf-available` + `a2enconf`**: se valida con `configtest` igual, no se toca nada existente, y
  se desactiva con **un solo comando** (`a2disconf`). Es el mecanismo que Apache trae para esto.

Pega esto completo en el servidor (crea el archivo de una vez, sin editor). Cada `Header` va en una
sola línea, por larga que sea: la continuación con `\` funciona en Apache, pero un espacio invisible
tras la barra rompe la configuración y aquí no vale la pena el riesgo.

```bash
cat > /etc/apache2/conf-available/cmg-cache.conf <<'EOF'
# ── Caché de assets estáticos — CaMaGaRe ─────────────────────────────────────
# Las URLs de CSS/JS llevan ?v=<mtime> (helper asset_ver() en app/helpers/helpers.php):
# la URL cambia cuando el archivo cambia, así que se puede cachear indefinidamente.
<IfModule mod_headers.c>

    # 1) Assets versionados (?v=...), excepto /uploads/: caché para siempre.
    #    "immutable" le dice al navegador que no revalide ni al recargar.
    <FilesMatch "\.(css|js|woff2?|ttf|eot|otf)$">
        Header set Cache-Control "public, max-age=31536000, immutable" "expr=%{QUERY_STRING} =~ /(^|&)v=[^&]/ && %{REQUEST_URI} !~ m#/uploads/#"
    </FilesMatch>

    # 2) Assets SIN ?v= (imágenes del layout, iconos, modelos de reconocimiento
    #    facial): un día, y luego revalida. Suficiente para no pedirlos en cada
    #    página, y corto para que un cambio se vea el mismo día.
    <FilesMatch "\.(png|jpe?g|gif|webp|svg|ico|bin)$">
        Header set Cache-Control "public, max-age=86400" "expr=%{REQUEST_URI} !~ m#/uploads/#"
    </FilesMatch>

    # 3) /uploads/ — archivos que sube el usuario y se reemplazan con el mismo
    #    nombre (logos de empresa, fotos de producto). 5 minutos: el cambio se ve
    #    casi al instante y aun así no se pide en cada carga.
    <FilesMatch "\.(png|jpe?g|gif|webp|svg|ico|pdf)$">
        Header set Cache-Control "public, max-age=300, must-revalidate" "expr=%{REQUEST_URI} =~ m#/uploads/#"
    </FilesMatch>

</IfModule>
EOF
```

No se toca nada de `.php`: las páginas siguen sin cachearse, como ahora.

## Aplicar

```bash
a2enmod headers && a2enconf cmg-cache && apache2ctl configtest && systemctl reload apache2
```

Si `configtest` no dice `Syntax OK`, **no recargues**: mientras no recargues, el sitio sigue
funcionando con la configuración anterior, así que no hay prisa ni daño.

## Verificar

```bash
curl -sI "https://erp.camagare.com.ec/css/app.css?v=123" | grep -i cache-control
```
→ debe decir `public, max-age=31536000, immutable`

```bash
curl -sI "https://erp.camagare.com.ec/css/app.css" | grep -i cache-control
```
→ **no** debe decir `immutable` (sin `?v=` no entra en la regla larga)

```bash
curl -sI "https://erp.camagare.com.ec/image/logofinal.png" | grep -i cache-control
```
→ `public, max-age=86400`

Y la prueba de producto: pide a alguien que cambie el logo de su empresa y confirme que lo ve
actualizado en pocos minutos.

## Reversión de la Parte B

Un solo comando, sin editar nada:

```bash
a2disconf cmg-cache && systemctl reload apache2
```

Los navegadores que ya guardaron un asset con `immutable` lo conservarán hasta que su URL cambie —
pero como la URL lleva `?v=<mtime>`, basta con que el archivo cambie en el siguiente despliegue.
No hay forma de quedarse con una versión vieja de forma permanente.

---

## Qué esperar de los dos cambios juntos

- **Parte A**: menos CPU por petición. En un servidor de 1 vCPU es la diferencia entre atender del
  orden de 20-50 peticiones por segundo y bastantes más. Es el efecto que se nota en las horas pico.
- **Parte B**: entre 8 y 10 peticiones menos por cada carga de página. Eso también son procesos de
  Apache que quedan libres para atender trabajo de verdad.

Ninguno de los dos toca el polling de 5 segundos, que sigue siendo el mayor consumidor de
capacidad del sistema (ver `PLAN_ESCALA_5000.md`, A1). Estos dos cambios hacen que cada una de esas
peticiones cueste menos; el siguiente paso es que dejen de existir.

---

# ⚠️ RIESGO APARTE · nginx puede tumbar el ERP en el próximo reinicio

Detectado el 13-09-2026, **no tiene relación con las Partes A y B** pero es más urgente que ambas.

Estado comprobado en el droplet:

- `systemctl is-active nginx` → **failed** (no está corriendo)
- `systemctl is-enabled nginx` → **enabled** (systemd lo arrancará en cada reinicio)
- Existe `/etc/nginx/sites-enabled/erp.camagare.com.ec`

Apache está sirviendo el ERP en los puertos 80 y 443. nginx está configurado para el mismo dominio y
tiene orden de arrancar solo. **En el próximo reinicio del droplet los dos competirán por el 443**, y
quién gane depende del orden en que systemd los levante:

- Si Apache gana, nginx vuelve a quedar `failed` y no pasa nada (es la situación de hoy).
- Si nginx gana, **Apache no arranca y el ERP queda caído** para las 100 empresas, hasta que alguien
  entre al servidor a arreglarlo.

Es una lotería en cada reinicio, y hay un reinicio pendiente por actualización de kernel.

**Arreglo — un comando, reversible, sin efecto inmediato sobre nada:**

```bash
systemctl disable nginx
```

No desinstala nginx, no borra su configuración y no toca Apache: solo le quita la orden de arrancar
automáticamente. Si algún día se quiere poner nginx delante de Apache a propósito, se revierte con
`systemctl enable nginx` y se configura bien entonces.

Comprobar que quedó:

```bash
systemctl is-enabled nginx
```

Debe decir `disabled`.
