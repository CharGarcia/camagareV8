# Servidor nuevo desde cero - CaMaGaRe

Guía para un **droplet nuevo** en DigitalOcean. Sigue los pasos en orden.

---

## Paso 1: Crear droplet en DigitalOcean

1. DigitalOcean → **Create** → **Droplets**
2. Imagen: **Ubuntu 22.04** (o 24.04)
3. Plan: el que prefieras (ej: $12/mes 2GB RAM)
4. Datacenter: el más cercano
5. Crear droplet
6. Anota la **IP** y la **contraseña de root** (llegará por email)

---

## Paso 2: Conectarte por SSH

Desde PowerShell en Windows:
```powershell
ssh root@TU_IP
```
Escribe `yes` si pregunta. Luego la contraseña de root.

---

## Paso 3: Ejecutar TODO de una vez (copia y pega)

Copia este bloque completo en la terminal y presiona Enter:

```bash
# Actualizar sistema
apt update && apt upgrade -y

# Instalar servicios
apt install -y nginx php-fpm php-mysql php-mbstring php-xml php-curl php-json php-zip git unzip

# Ver versión PHP (anota: php8.1 o php8.2)
ls /var/run/php/

# Borrar contenido de html
rm -rf /var/www/html/*

# Crear BD y usuario (te pedirá contraseña de root MySQL - puede estar vacía, Enter)
mysql -u root << 'EOF'
DROP DATABASE IF EXISTS camagare_v8;
DROP USER IF EXISTS 'camagare_user'@'localhost';
CREATE DATABASE camagare_v8 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'camagare_user'@'localhost' IDENTIFIED BY 'Camagare2024!';
GRANT ALL PRIVILEGES ON camagare_v8.* TO 'camagare_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Clonar proyecto
cd /var/www
rm -rf sistema
git clone https://github.com/CharGarcia/camagareV8.git sistema

# Permisos
chown -R www-data:www-data /var/www/sistema
chmod -R 755 /var/www/sistema

echo "Listo. Ahora configura database.php, bootstrap, index.php y Nginx."
```

---

## Paso 4: Configurar la aplicación

### 4.1 Base de datos
```bash
cat > /var/www/sistema/config/database.php << 'EOF'
<?php
return [
    'host' => '127.0.0.1',
    'user' => 'camagare_user',
    'pass' => 'Camagare2024!',
    'name' => 'camagare_v8',
    'charset' => 'utf8mb4',
];
EOF
```

### 4.2 BASE_URL
```bash
sed -i "s|define('BASE_URL', '/sistema/public');|define('BASE_URL', '');|g" /var/www/sistema/bootstrap.php
sed -i "s|new Application('/sistema/public')|new Application('')|g" /var/www/sistema/public/index.php
```

### 4.3 Nginx (ajusta php8.2 si tienes php8.1)
```bash
PHP_SOCK=$(ls /var/run/php/php*.sock 2>/dev/null | head -1)
cat > /etc/nginx/sites-available/default << NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/sistema/public;
    index index.php;
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.ht {
        deny all;
    }
}
NGINX
```

### 4.4 Reiniciar
```bash
nginx -t && systemctl reload nginx
systemctl enable nginx php*-fpm
systemctl start php*-fpm
```

---

## Paso 5: Importar base de datos

**En tu PC** (exportar):
```powershell
cd c:\xampp\mysql\bin
.\mysqldump.exe -u root camagare_v8 > C:\xampp\htdocs\backup_camagare.sql
```

**Copiar al servidor:**
```powershell
scp C:\xampp\htdocs\backup_camagare.sql root@TU_IP:/root/
```

**En el servidor** (importar):
```bash
mysql -u camagare_user -pCamagare2024! camagare_v8 < /root/backup_camagare.sql
```

---

## Paso 6: Probar

Abre en el navegador: **http://TU_IP**

Deberías ver el login de CaMaGaRe.

---

## Resumen de contraseñas

| Dato | Valor |
|------|-------|
| Usuario MySQL | camagare_user |
| Contraseña MySQL | Camagare2024! |
| Base de datos | camagare_v8 |

*(Puedes cambiar la contraseña editando `config/database.php` y recreando el usuario en MySQL)*
