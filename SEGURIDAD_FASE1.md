# Seguridad — Fase 1: cierre de accesos públicos y secretos

Runbook de un solo uso. Ejecutar **en orden**. El paso 0 evita que el `git pull`
rompa el servidor.

---

## Qué cambió en este commit

**1. Se eliminaron 26 scripts de depuración versionados.** Los 14 que estaban en
`public/` eran URLs públicas en producción (el docroot es `/var/www/sistema/public`),
ejecutables por cualquiera **sin usuario ni contraseña**:

| Archivo | Qué permitía |
|---|---|
| `public/kill_locks.php` | `pg_terminate_backend` a todas las conexiones → caída de la BD de todos los clientes |
| `public/migracion.php` | `DROP TABLE` |
| `public/update_db.php` | Ejecutar DDL |
| `public/get_db.php` | Volcar una tabla completa a JSON |
| `public/schema*.php`, `public/get_*_schema.php` | Publicar el esquema de la BD |
| `public/log_login.php`, `public/test_login_post.php` | Probar credenciales con `display_errors=1` y sin límite de intentos |
| `public/test_session.php`, `public/test-menu.php`, `public/test-iconos.php` | Volcado de sesión y de configuración interna |

Los otros 12 estaban en la raíz (no alcanzables por web con el docroot actual,
pero sí desde XAMPP en local, y varios llevaban contraseñas escritas dentro):
`run_sql.php`, `zmod.php`, `schema.php`, `get_log.php`, `get_schema.php`,
`describe_tables.php`, `explore_excel.php`, `scratch.php`, `scratch2.php`,
`test.php`, `kill_locks_cli.php`, `composer-setup.php`.

Se conserva `diagnostico_rutas_modulos.php` (herramienta de diagnóstico en uso).

**2. Los secretos salieron de los archivos versionados.**
`config/database.php` ya no lleva la contraseña de PostgreSQL y `config/app.php`
ya no lleva la API key de 2captcha. Ambos resuelven la configuración así:

```
variable de entorno  >  config/parametros.xml  >  config/local.php  >  valor por defecto
```

**3. Los valores por defecto de `config/app.php` pasaron a ser los de producción**
(`env=production`, `debug=false`, `show_login_errors=false`, verificación TLS del
SMTP activa). Cada entorno activa lo suyo desde `config/local.php`, que **no se
versiona**. Efecto secundario útil: el servidor deja de tener `config/app.php`
modificado a mano, así que el `git pull` del despliegue ya no choca.

**4. `.gitignore` reforzado** para que no vuelvan a colarse scripts sueltos en `public/`.

---

## Paso 0 — ANTES del `git pull` (obligatorio)

En el servidor, comprobar si los archivos de configuración están modificados a mano.
Si lo están, el `git pull` fallará con *"local changes would be overwritten"*, o peor,
se perderán los valores de producción.

```bash
cd /var/www/sistema && git status --short config/
```

- **Si aparece `M config/database.php`** → el servidor tiene sus credenciales escritas
  dentro del archivo. Hay que moverlas antes:

  ```bash
  cd /var/www/sistema && grep -E "host|port|user|pass|name" config/database.php
  ```

  Copiar esos valores a `config/parametros.xml` (no versionado):

  ```bash
  nano /var/www/sistema/config/parametros.xml
  ```
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <parametros>
      <host_db>EL_HOST_REAL</host_db>
      <port_db>25060</port_db>
      <user_db>EL_USUARIO_REAL</user_db>
      <pass_db>LA_CONTRASENA_REAL</pass_db>
      <db_name>EL_NOMBRE_REAL</db_name>
  </parametros>
  ```

- **Si aparece `M config/app.php`** → ver qué valores propios tiene el servidor y
  copiarlos a `config/local.php` (paso 1):

  ```bash
  cd /var/www/sistema && git diff config/app.php
  ```

Con los valores ya a salvo, descartar las modificaciones locales:

```bash
cd /var/www/sistema && git checkout -- config/app.php config/database.php
```

## Paso 1 — Crear `config/local.php` en el servidor

```bash
cp /var/www/sistema/config/local.php.example /var/www/sistema/config/local.php
nano /var/www/sistema/config/local.php
```

Contenido mínimo para producción (ajustar con los valores reales del paso 0):

```php
<?php
declare(strict_types=1);

return [
    'base_url' => '',
    'app_url'  => 'https://www.camagare.com.ec',

    'api_jwt_secret'   => 'EL_SECRETO_JWT_QUE_YA_USA_EL_SERVIDOR',
    '2captcha_api_key' => 'LA_NUEVA_KEY_DEL_PASO_4',
];
```

> **No** incluir `debug`, `env` ni `show_login_errors`: en producción deben quedar
> en los valores por defecto (desactivados).

Permisos restrictivos (contiene secretos):

```bash
chown root:www-data /var/www/sistema/config/local.php /var/www/sistema/config/parametros.xml
chmod 640 /var/www/sistema/config/local.php /var/www/sistema/config/parametros.xml
```

## Paso 2 — Desplegar

```bash
cd /var/www/sistema && git pull origin main && systemctl reload php8.2-fpm
```

## Paso 3 — Verificar que los scripts ya no se ejecutan

Nginx envía a `index.php` todo lo que no existe (`try_files`), así que estas URLs
seguirán respondiendo **200 con la pantalla de login** — eso es lo correcto. Lo que
hay que confirmar es que **no devuelvan el resultado del script**:

```bash
for f in kill_locks.php migracion.php update_db.php get_db.php schema.php log_login.php test_login_post.php; do echo -n "$f -> "; curl -s "https://www.camagare.com.ec/$f" | grep -o "<title>[^<]*</title>" | head -1; done
```

Todas deben responder `<title>Iniciar sesión | CaMaGaRe ERP</title>`. Si alguna
devuelve JSON, un volcado de tablas o "Conexiones terminadas", el archivo sigue en
el servidor: borrarlo a mano con `rm`.

Comprobar también que ya no quedan sueltos en el servidor:

```bash
ls /var/www/sistema/public/*.php   # debe listar solo index.php
```

Y confirmar que el sistema entra con normalidad, que se emite un documento de
prueba y que salen los correos (si fallan, ver *Correo* al final).

---

## Paso 4 — Rotar los secretos expuestos

Borrar los archivos **no borra el historial de Git**: la contraseña y la API key
siguen siendo legibles con `git log -p`. Por eso rotarlas no es opcional.

### 4.1 Confirmar si el repositorio es público

En GitHub → `CharGarcia/camagareV8` → si dice **Public**, la contraseña de la base
de datos de producción ha estado disponible para cualquiera. En ese caso, cambiarla
**hoy** y pasar el repositorio a *Private* (Settings → General → Danger Zone).

### 4.2 Contraseña de PostgreSQL

La base es *Managed PostgreSQL* de DigitalOcean. Lo mínimo es cambiar la contraseña
del usuario actual; lo correcto es dejar de usar el superusuario para la aplicación:

```sql
-- Usuario propio para la app, sin permisos de administración del clúster
CREATE USER camagare_app WITH PASSWORD 'GENERAR_UNA_LARGA_Y_ALEATORIA';
GRANT CONNECT ON DATABASE camagare_v8 TO camagare_app;
GRANT USAGE ON SCHEMA public TO camagare_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO camagare_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO camagare_app;
-- Que aplique también a las tablas que se creen después
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO camagare_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO camagare_app;
```

Generar la contraseña con `openssl rand -base64 32`, ponerla en
`config/parametros.xml` y recargar: `systemctl reload php8.2-fpm`.

Si prefiere solo cambiar la contraseña del usuario actual:

```sql
ALTER USER el_usuario_actual WITH PASSWORD 'LA_NUEVA';
```

> La contraseña `CmGr1980` se reutiliza en `parametros.xml` para el **FTP de
> documentos** y para la **base MySQL legacy**. Cambiar solo la de PostgreSQL deja
> las otras dos abiertas con la misma clave conocida: rotar las tres.

### 4.3 API key de 2captcha

En `2captcha.com` → cuenta → regenerar la API key. La anterior
(`f40ccb…e8fa`) quedó publicada en el repositorio y puede consumir el saldo de la
cuenta. Pegar la nueva en `config/local.php` del servidor y en el de desarrollo.

---

## Notas

**Correo.** El valor por defecto ahora **verifica** el certificado TLS del servidor
SMTP (antes se saltaba la verificación en todos los entornos, lo que permite
interceptar las credenciales del correo). Si tras el despliegue los correos dejan de
salir, es que el SMTP tiene un certificado que PHP no valida: la solución correcta es
arreglar el certificado del proveedor, no volver a desactivar la comprobación.

**Entorno de desarrollo.** `config/local.php` local ya quedó configurado con
`debug`, `show_login_errors`, las opciones SMTP de XAMPP y la key de 2captcha, así
que XAMPP funciona igual que antes. La base se sigue leyendo de `config/parametros.xml`.

**Historial de Git.** Si además de rotar quiere borrar los secretos del historial,
se hace con `git filter-repo` y obliga a reescribir `main`. Solo tiene sentido
después de rotar; con las credenciales ya cambiadas, lo del historial pasa a ser
higiene, no urgencia.
