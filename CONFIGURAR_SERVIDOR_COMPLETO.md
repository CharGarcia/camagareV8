# Configuración completa del servidor DigitalOcean

Sigue estos pasos **en orden** desde la terminal SSH (`ssh root@TU_IP`).

---

## FASE 1: Limpiar lo anterior

### 1.1 Salir de MySQL (si estás dentro)
```bash
EXIT;
```

### 1.2 Borrar proyecto y preparar base de datos
```bash
# Borrar carpeta del proyecto
rm -rf /var/www/sistema

# Entrar a MySQL
mysql -u root -p
```

Dentro de MySQL:
```sql
DROP DATABASE IF EXISTS camagare_v8;
DROP USER IF EXISTS 'camagare_user'@'localhost';
CREATE DATABASE camagare_v8 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'camagare_user'@'localhost' IDENTIFIED BY 'Camagare2024!';
GRANT ALL PRIVILEGES ON camagare_v8.* TO 'camagare_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
*(Cambia `Camagare2024!` por tu contraseña)*

---

## FASE 2: Actualizar e instalar servicios

### 2.1 Actualizar sistema
```bash
apt update && apt upgrade -y
```

### 2.2 Instalar Nginx, PHP, MySQL, Git
```bash
apt install -y nginx php-fpm php-mysql php-mbstring php-xml php-curl php-json php-zip git unzip
```

### 2.3 Ver versión de PHP
```bash
ls /var/run/php/
```
Anota el nombre (ej: `php8.2-fpm.sock` o `php8.1-fpm.sock`), lo usarás después.

---

## FASE 3: Clonar y configurar el proyecto

### 3.1 Clonar desde GitHub
```bash
cd /var/www
git clone https://github.com/CharGarcia/camagareV8.git sistema
```

### 3.2 Permisos
```bash
chown -R www-data:www-data /var/www/sistema
chmod -R 755 /var/www/sistema
```

### 3.3 Configurar base de datos
```bash
nano /var/www/sistema/config/database.php
```
Borra el contenido y pega (usa la contraseña que creaste en 1.2):
```php
<?php
return [
    'host' => '127.0.0.1',
    'user' => 'camagare_user',
    'pass' => 'Camagare2024!',
    'name' => 'camagare_v8',
    'charset' => 'utf8mb4',
];
```
Guarda: `Ctrl+O` → Enter → `Ctrl+X`

### 3.4 Configurar BASE_URL (sitio en raíz)
```bash
nano /var/www/sistema/bootstrap.php
```
Cambia la línea a:
```php
define('BASE_URL', '');
```

```bash
nano /var/www/sistema/public/index.php
```
Cambia a:
```php
$app = new Application('');
```

### 3.5 Configurar Nginx
```bash
nano /etc/nginx/sites-available/default
```
Borra todo y pega (reemplaza `TU_IP` por la IP de tu servidor y `php8.2` por tu versión si es diferente):
```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name TU_IP _;
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
Guarda: `Ctrl+O` → Enter → `Ctrl+X`

### 3.6 Reiniciar servicios
```bash
nginx -t
systemctl reload nginx
systemctl enable nginx php8.2-fpm
systemctl start php8.2-fpm
```

---

## FASE 4: Importar tu base de datos

### En tu PC (Windows, XAMPP)
```powershell
cd c:\xampp\mysql\bin
.\mysqldump.exe -u root camagare_v8 > C:\xampp\htdocs\backup_camagare.sql
```

### Copiar al servidor
Desde PowerShell en tu PC:
```powershell
scp C:\xampp\htdocs\backup_camagare.sql root@TU_IP:/root/
```

### En el servidor
```bash
mysql -u camagare_user -p camagare_v8 < /root/backup_camagare.sql
```
*(Te pedirá la contraseña de camagare_user)*

---

## Verificar

Abre en el navegador: `http://TU_IP`

Deberías ver la pantalla de login de CaMaGaRe.
