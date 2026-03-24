# Guía: Desplegar el sistema CaMaGaRe en DigitalOcean con Git

Servidor: `root@ubuntu-s-2vcpu-4gb-amd-sfo3-01`

---

## Parte 1: Preparar el proyecto en tu PC (Windows)

### 1.1 Proteger archivos sensibles
- Asegúrate de que `.env` esté en `.gitignore` (ya incluido)
- Nunca subas contraseñas ni credenciales al repositorio

### 1.2 Crear el primer commit
```powershell
cd c:\xampp\htdocs\sistema
git add .
git status   # Revisa que no aparezca .env ni archivos sensibles
git commit -m "Primer commit - Sistema CaMaGaRe listo para deploy"
```

### 1.3 Crear repositorio en GitHub
1. Ve a [github.com](https://github.com) → **New repository**
2. Nombre sugerido: `sistema-camagare` (o el que prefieras)
3. **No** marques "Initialize with README" (ya tienes código)
4. Crea el repositorio

### 1.4 Vincular y subir
```powershell
# Reemplaza TU_USUARIO y TU_REPO con los tuyos
git remote add origin https://github.com/TU_USUARIO/TU_REPO.git
git branch -M main
git push -u origin main
```

Si GitHub pide autenticación:
- Usa un **Personal Access Token** en lugar de la contraseña
- GitHub → Settings → Developer settings → Personal access tokens

---

## Parte 2: Configurar el servidor en DigitalOcean

### 2.1 Conectarte por SSH
```bash
ssh root@ubuntu-s-2vcpu-4gb-amd-sfo3-01
```

(Necesitas la IP si el hostname no resuelve: `ssh root@IP_DEL_SERVIDOR`)

### 2.2 Actualizar el sistema
```bash
apt update && apt upgrade -y
```

### 2.3 Instalar Nginx, PHP, MySQL y Git
```bash
apt install -y nginx php-fpm php-mysql php-mbstring php-xml php-curl php-json php-zip git unzip
```

### 2.4 Crear base de datos MySQL
```bash
mysql -u root -p
```
Dentro de MySQL:
```sql
CREATE DATABASE camagare_v8 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'camagare_user'@'localhost' IDENTIFIED BY 'PON_AQUI_UNA_CLAVE_SEGURA';
GRANT ALL PRIVILEGES ON camagare_v8.* TO 'camagare_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2.5 Clonar el proyecto
```bash
cd /var/www
git clone https://github.com/TU_USUARIO/TU_REPO.git sistema
```

Si el repo es privado:
```bash
# Opción A: Usar token en la URL (solo para el clone inicial)
git clone https://TU_TOKEN@github.com/TU_USUARIO/TU_REPO.git sistema

# Opción B: Configurar credenciales con Git credential helper
```

### 2.6 Configurar permisos
```bash
chown -R www-data:www-data /var/www/sistema
chmod -R 755 /var/www/sistema
```

### 2.7 Configurar la aplicación para producción
Edita la base de datos:
```bash
nano /var/www/sistema/app/Config/database.php
```
Cambia los valores según lo creado en MySQL:
```php
return [
    'host' => '127.0.0.1',
    'user' => 'camagare_user',
    'pass' => 'PON_AQUI_UNA_CLAVE_SEGURA',
    'name' => 'camagare_v8',
];
```

**Si el sitio estará en la raíz** (`https://tudominio.com/`), edita 3 archivos:

1. `bootstrap.php` → cambia `define('BASE_URL', '/sistema/public');` por `define('BASE_URL', '');`
2. `public/index.php` → cambia `new Application('/sistema/public')` por `new Application('')`
3. `app/core/Application.php` línea 40 → cambia `header('Location: /sistema/public/');` por `header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/');`

**Si mantienes la ruta** (`https://tudominio.com/sistema/public/`), no cambies nada.

### 2.8 Configurar Nginx
```bash
nano /etc/nginx/sites-available/default
```

Reemplaza el contenido con (ajusta `server_name` a tu dominio o IP):
```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name TU_IP_O_DOMINIO;
    root /var/www/sistema/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;   # Ajusta versión si es necesario
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 2.9 Verificar y reiniciar servicios
```bash
nginx -t
systemctl reload nginx
# Verifica la versión de PHP instalada:
ls /var/run/php/
# Luego habilita e inicia (ajusta php8.1-fpm si usas otra versión):
systemctl enable nginx php8.1-fpm
systemctl start php8.1-fpm
```

### 2.10 Importar la base de datos (si tienes un dump)
```bash
# Desde tu PC, exporta:
# mysqldump -u root camagare_v8 > backup.sql

# En el servidor, importa:
mysql -u camagare_user -p camagare_v8 < backup.sql
```

Si no tienes dump, tendrás que crear las tablas manualmente o ejecutar migraciones si las tienes.

---

## Parte 3: Actualizaciones futuras (deploy con Git)

### Desde tu PC:
```powershell
cd c:\xampp\htdocs\sistema
git add .
git commit -m "Descripción del cambio"
git push
```

### En el servidor:
```bash
cd /var/www/sistema
git pull origin main
chown -R www-data:www-data /var/www/sistema
```

---

## Parte 4: SSL con Let's Encrypt (opcional)

```bash
apt install certbot python3-certbot-nginx -y
certbot --nginx -d tudominio.com
```

---

## Resumen de comandos rápidos

| Acción | Comando |
|--------|---------|
| Conectar SSH | `ssh root@IP` |
| Actualizar sitio | `cd /var/www/sistema && git pull` |
| Reiniciar PHP-FPM | `systemctl restart php8.1-fpm` |
| Ver logs Nginx | `tail -f /var/log/nginx/error.log` |
