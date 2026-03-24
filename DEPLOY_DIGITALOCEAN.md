# Guía: Configurar servidor DigitalOcean desde cero

**Servidor:** `root@ubuntu-s-2vcpu-4gb-amd-sfo3-01`  
**Repositorio:** https://github.com/CharGarcia/camagareV8

---

## Paso 1: Obtener la IP del servidor

1. Entra en [DigitalOcean](https://cloud.digitalocean.com/)
2. Droplets → selecciona tu droplet
3. Copia la **IP pública** (ej: `164.92.xxx.xxx`)

---

## Paso 2: Conectarte por SSH

Desde **PowerShell** o **CMD** en Windows:
```powershell
ssh root@TU_IP
```

Ejemplo: `ssh root@164.92.123.45`

- Si es la primera vez, preguntará si confías en el host → escribe `yes`
- Te pedirá la **contraseña de root** (la recibiste por email al crear el droplet, o está en DigitalOcean → Access → Root password)

---

## Paso 3: Configurar el servidor (comandos en orden)

### 3.1 Actualizar el sistema
```bash
apt update && apt upgrade -y
```

### 3.2 Instalar Nginx, PHP, MySQL y Git
```bash
apt install -y nginx php-fpm php-mysql php-mbstring php-xml php-curl php-json php-zip git unzip
```

### 3.3 Configurar MySQL (base de datos)
```bash
mysql -u root -p
```
*(La contraseña de root de MySQL suele estar vacía la primera vez, o en el email de DigitalOcean)*

Dentro de MySQL, ejecuta (cambia `TU_CLAVE_SEGURA` por una contraseña real):
```sql
CREATE DATABASE camagare_v8 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'camagare_user'@'localhost' IDENTIFIED BY 'TU_CLAVE_SEGURA';
GRANT ALL PRIVILEGES ON camagare_v8.* TO 'camagare_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.4 Clonar el proyecto
```bash
cd /var/www
git clone https://github.com/CharGarcia/camagareV8.git sistema
```

Si el repo es privado, usa tu token:
```bash
git clone https://TU_TOKEN@github.com/CharGarcia/camagareV8.git sistema
```

### 3.5 Configurar permisos
```bash
chown -R www-data:www-data /var/www/sistema
chmod -R 755 /var/www/sistema
```

### 3.6 Editar la base de datos
```bash
nano /var/www/sistema/config/database.php
```

Reemplaza el bloque `return [...]` al final con (usa la contraseña que definiste antes):
```php
return [
    'host' => '127.0.0.1',
    'user' => 'camagare_user',
    'pass' => 'TU_CLAVE_SEGURA',
    'name' => 'camagare_v8',
    'charset' => 'utf8mb4',
];
```
Guarda con `Ctrl+O`, `Enter`, y sal con `Ctrl+X`.

### 3.7 Configurar BASE_URL (sitio en raíz del dominio)

Si tu sitio estará en `https://tudominio.com/` (raíz):

**A)** `nano /var/www/sistema/bootstrap.php` → cambia la línea a:
```php
define('BASE_URL', '');
```

**B)** `nano /var/www/sistema/public/index.php` → cambia a:
```php
$app = new Application('');
```

Si el sitio estará en `https://tudominio.com/sistema/public/`, no cambies nada.

### 3.8 Configurar Nginx
```bash
nano /etc/nginx/sites-available/default
```

Borra todo y pega (reemplaza `TU_IP` por tu IP o dominio):
```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name TU_IP;
    root /var/www/sistema/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Verifica la versión de PHP instalada: `ls /var/run/php/`  
Si ves `php8.1-fpm.sock`, cambia la línea `fastcgi_pass` a esa versión.

### 3.9 Reiniciar servicios
```bash
nginx -t
systemctl reload nginx
systemctl enable nginx php8.2-fpm
systemctl start php8.2-fpm
```

### 3.10 Importar la base de datos (desde tu PC)

En tu PC (con XAMPP):
```powershell
cd c:\xampp\mysql\bin
.\mysqldump.exe -u root camagare_v8 > C:\xampp\htdocs\backup_db.sql
```

Copia el archivo al servidor (con SCP o WinSCP), luego en el servidor:
```bash
mysql -u camagare_user -p camagare_v8 < /ruta/al/backup_db.sql
```

---

## Resumen rápido

| Acción | Comando |
|--------|---------|
| Conectar | `ssh root@TU_IP` |
| Actualizar sitio | `cd /var/www/sistema && git pull && chown -R www-data:www-data .` |
| Reiniciar PHP | `systemctl restart php8.2-fpm` |
| Ver logs | `tail -f /var/log/nginx/error.log` |

---

## SSL (opcional, cuando tengas dominio)

```bash
apt install certbot python3-certbot-nginx -y
certbot --nginx -d tudominio.com
```
