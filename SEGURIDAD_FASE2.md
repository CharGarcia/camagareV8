# Seguridad — Fase 2: servidor y cabeceras

Continúa `SEGURIDAD_FASE1.md`. Aplicar **después** de haber desplegado la Fase 1.

---

## Parte A — Lo que ya viene en el código (se despliega con `git pull`)

### A.1 Cabeceras de seguridad

Nuevo `app/helpers/CabecerasSeguridad.php`, invocado desde `Application::run()`.
Se aplican desde PHP —y no desde Nginx— para que viajen con el despliegue y valgan
también en XAMPP, sin depender de que alguien recuerde editar el servidor.

| Cabecera | Para qué |
|---|---|
| `X-Frame-Options: SAMEORIGIN` + `frame-ancestors 'self'` | Clickjacking: nadie puede incrustar el sistema en su web y engañar al usuario para que haga clic |
| `X-Content-Type-Options: nosniff` | Que el navegador no "adivine" el tipo de un archivo subido y lo ejecute como HTML |
| `Referrer-Policy: strict-origin-when-cross-origin` | No filtrar la URL completa (lleva ids de documento) a sitios externos |
| `Permissions-Policy` | Cámara y ubicación permitidas solo al propio sistema; micrófono, pagos y sensores desactivados |
| `Strict-Transport-Security` | Solo bajo HTTPS: el navegador recuerda no volver a entrar por HTTP |
| `Content-Security-Policy` | Limita de dónde se cargan scripts, estilos y marcos. **Arranca en modo aviso** (ver A.3) |

Calibradas contra el uso real: el portal de reservas (`/reservas/{slug}`) queda
**exento** del bloqueo de framing porque el módulo *Portal de citas* entrega un
`<iframe>` para que el cliente lo incruste en su propia web. Cámara y GPS siguen
permitidos porque los usan clientes, proveedores, puntos de servicio,
consignaciones, empleados y la asistencia pública.

### A.2 Cookie de sesión `Secure`

`Application.php` marcaba la cookie con `secure => false`, así que podía viajar en
claro. Ahora se marca `Secure` automáticamente cuando la petición llega por HTTPS
(detecta también el proxy inverso vía `X-Forwarded-Proto`) y se deja en `false` en
desarrollo, donde no hay TLS y si no se perdería la sesión.

### A.3 Calibrar la CSP y activarla de verdad

La CSP sale en modo **aviso** (`Content-Security-Policy-Report-Only`): el navegador
no bloquea nada, solo anota las violaciones en su consola. Esto es a propósito —
una CSP mal calibrada rompe pantallas de forma silenciosa.

1. Tras desplegar, navegar el sistema completo con la consola del navegador abierta
   (F12 → Consola): facturas, compras, inventario, contabilidad, reportes, POS,
   restaurante y los portales públicos.
2. Anotar los mensajes que empiecen por *"Content Security Policy"*. Si alguno
   señala un dominio legítimo que falta, agregarlo a la constante correspondiente
   de `CabecerasSeguridad.php` (`CDN_SCRIPTS`, `CDN_ESTILOS`, `MARCOS_PERMITIDOS`…).
3. Cuando no queden violaciones propias, activarla en `config/local.php` del servidor:

```php
'security' => ['csp' => 'enforce'],
```

> Mientras las vistas tengan `onclick=` inline (hoy son 1.617), la CSP necesita
> `'unsafe-inline'` en `script-src`, lo que reduce su eficacia contra XSS. Quitarlo
> exige refactorizar las vistas; no es trabajo de esta fase.

### A.4 Corrección de subida de archivos (encontrada durante esta fase)

`EmpresaService::saveEstablecimiento()` tomaba la extensión **del nombre que envía
el usuario, sin lista blanca**, y guardaba el archivo en `public/uploads/logos/`.
Un usuario con permiso de actualizar la empresa podía subir `shell.php` y luego
abrirlo por URL: **ejecución de código en el servidor**. Ahora valida extensión
(jpg/jpeg/png/gif/webp) y tamaño (2 MB), igual que productos y menú.

Se auditaron los 11 puntos de subida del sistema: los demás validan correctamente
o escriben en `storage/`, que está fuera del docroot. La regla de Nginx del punto
B.2 añade una segunda capa por si aparece otro caso.

---

## Parte B — Nginx

### B.1 HTTPS con certificado

Si aún se entra por IP o por HTTP, primero el certificado:

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d www.camagare.com.ec -d camagare.com.ec
systemctl status certbot.timer    # la renovación automática debe estar "active"
```

### B.2 Configuración endurecida

```bash
cp /etc/nginx/sites-available/default /root/nginx-default.bak   # respaldo
nano /etc/nginx/sites-available/default
```

Reemplazar por (ajustar `php8.2` a la versión instalada; certbot ya habrá dejado
las líneas `ssl_certificate`, conservarlas):

```nginx
# Límite de intentos de login por IP (fuerza bruta). 20 por minuto es holgado
# para una oficina con IP compartida y absurdo para un atacante.
limit_req_zone $binary_remote_addr zone=login:10m rate=20r/m;

# HTTP → HTTPS
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name www.camagare.com.ec camagare.com.ec _;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2 default_server;
    listen [::]:443 ssl http2 default_server;
    server_name www.camagare.com.ec camagare.com.ec _;
    root /var/www/sistema/public;
    index index.php;

    # ssl_certificate / ssl_certificate_key: las gestiona certbot

    # No publicar la versión de Nginx
    server_tokens off;

    # Subidas: 32 MB general. Los videos de ayuda llegan a 500 MB y tienen su
    # propia excepción más abajo.
    client_max_body_size 32M;

    # Procesos largos: envío al SRI, ATS, regeneración contable
    fastcgi_read_timeout 300;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Freno de fuerza bruta solo en el POST del login
    location = /auth/login {
        limit_req zone=login burst=10 nodelay;
        try_files $uri /index.php?$query_string;
    }

    # Excepción de tamaño para la carga de videos de ayuda
    location ~* ^/videosayuda/(store|update) {
        client_max_body_size 512M;
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # CLAVE: nada se ejecuta dentro de las carpetas de subida. Si alguna vez se
    # cuela un .php ahí, se descarga como texto en lugar de ejecutarse.
    location ~* ^/(uploads|storage)/.*\.(php|phtml|php[0-9]|phar|pl|py|cgi|sh)$ {
        deny all;
    }

    # Archivos ocultos (.git, .env, .htaccess) y respaldos sueltos
    location ~ /\. { deny all; }
    location ~* \.(sql|bak|old|log|ini|sh|md)$ { deny all; }
}
```

Aplicar:

```bash
nginx -t && systemctl reload nginx
```

---

## Parte C — Sistema operativo

### C.1 Firewall

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
ufw status verbose
```

> La base es *Managed PostgreSQL* de DigitalOcean, así que no hace falta abrir
> 5432/25060: la salida ya está permitida. En el panel de DigitalOcean, en
> *Databases → Settings → Trusted sources*, dejar **solo** el droplet.

### C.2 fail2ban

```bash
apt install -y fail2ban
nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true

[nginx-limit-req]
enabled = true
```

```bash
systemctl enable --now fail2ban
fail2ban-client status
```

`nginx-limit-req` banea a quien dispare repetidamente el `limit_req` del login.
El bloqueo por *usuario* (no por IP) llega en la Fase 4, cuando la aplicación
registre los intentos fallidos.

### C.3 SSH: solo con clave, sin root

Primero, **desde su equipo**, generar y subir la clave (si aún no lo hizo):

```bash
ssh-keygen -t ed25519 -C "carlos-camagare"
ssh-copy-id usuario@www.camagare.com.ec
```

Comprobar que entra sin contraseña **antes** de continuar. Luego, en el servidor:

```bash
adduser carlos && usermod -aG sudo carlos    # si aún no existe un usuario propio
nano /etc/ssh/sshd_config
```

```
PermitRootLogin no
PasswordAuthentication no
```

```bash
sshd -t && systemctl restart ssh
```

> Dejar la sesión SSH actual **abierta** y verificar el acceso desde otra terminal
> antes de cerrarla. Si algo salió mal, la consola web del panel de DigitalOcean
> es la vía de rescate.

### C.4 Actualizaciones de seguridad automáticas

```bash
apt install -y unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades
```

---

## Parte D — Verificación

```bash
# Cabeceras (desde cualquier equipo)
curl -sI https://www.camagare.com.ec/ | grep -iE "x-frame|x-content|referrer|permissions|strict-transport|content-security"

# HTTP redirige a HTTPS
curl -sI http://www.camagare.com.ec/ | head -2

# La cookie de sesión viaja marcada Secure
curl -sI https://www.camagare.com.ec/ | grep -i set-cookie

# Nada se ejecuta en uploads (debe dar 403)
curl -s -o /dev/null -w "%{http_code}\n" https://www.camagare.com.ec/uploads/prueba.php

# Firewall y fail2ban
ufw status && fail2ban-client status sshd
```

Y en la aplicación: entrar, emitir un documento de prueba, subir un logo de
establecimiento (debe rechazar un archivo que no sea imagen) y abrir el portal de
reservas incrustado para confirmar que el `<iframe>` sigue funcionando.

Para una nota externa de las cabeceras: https://securityheaders.com y
https://www.ssllabs.com/ssltest/ (objetivo: A o superior en ambos).
