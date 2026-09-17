#!/usr/bin/env bash
# =============================================================================
# recolectar_servidor.sh — Diagnóstico de rendimiento, SOLO LECTURA (CaMaGaRe)
# -----------------------------------------------------------------------------
# Sirve para los dos droplets: el del ERP nuevo y el del sistema viejo. Detecta
# lo que haya instalado (Apache, nginx, PHP-FPM, MariaDB, XAMPP, paneles).
#
# QUÉ HACE: lee configuración, uso de CPU/RAM/disco, logs de los últimos días,
# y en el ERP mide cuánto cuesta conectarse a la base. NO modifica nada: no
# reinicia servicios, no edita archivos, no escribe en ninguna base.
# Contraseñas o tokens que aparezcan en crontabs/configs salen enmascarados.
#
# CÓMO CORRERLO (PowerShell, en la raíz del repo; IP = la del droplet):
#   scp -r scripts\diagnostico_servidor root@IP:/tmp/
#   ssh root@IP "sed -i 's/\r//g' /tmp/diagnostico_servidor/* && bash /tmp/diagnostico_servidor/recolectar_servidor.sh"
#   scp "root@IP:/tmp/diagnostico_*.txt" "$env:USERPROFILE\diagnostico_servidores\"
#
# Tarda 1-3 minutos y recorrer los logs gasta CPU: correrlo en horario tranquilo.
# Limpieza al terminar: ssh root@IP "rm -rf /tmp/diagnostico_servidor /tmp/diagnostico_*.txt"
# =============================================================================

exec </dev/null
export LC_ALL=C

DIR="$(cd "$(dirname "$0")" && pwd)"
HOST="$(hostname)"
SALIDA="/tmp/diagnostico_${HOST}_$(date +%Y%m%d_%H%M).txt"
TMP="$(mktemp)"
BAJA="nice -n 19"
command -v ionice >/dev/null 2>&1 && BAJA="nice -n 19 ionice -c3"

sec() { printf '\n\n==================== %s ====================\n' "$*"; }
run() { printf '\n$ %s\n' "$*"; eval "$*" 2>&1 | head -n 600; }
hay() { command -v "$1" >/dev/null 2>&1; }

memoria_por_proceso() {
    ps -eo rss=,comm= | awk '{ m[$2] += $1; c[$2]++ }
        END { for (k in m) printf "%8.0f MB  %4d proc  %7.1f MB/proc  %s\n", m[k]/1024, c[k], m[k]/c[k]/1024, k }' \
        | sort -nr | head -15
}

workers_apache() {
    ps -ylC apache2 --sort:rss 2>/dev/null | awk 'NR > 1 { s += $8; n++; if ($8 > mx) mx = $8 }
        END { if (n) printf "procesos apache2: %d | RSS medio: %.0f MB | RSS max: %.0f MB | total: %.0f MB\n", n, s/n/1024, mx/1024, s/1024 }'
}

php_config() {
    local dir ver bin
    for dir in /etc/php/*/apache2 /etc/php/*/fpm; do
        [ -f "$dir/php.ini" ] || continue
        ver="$(echo "$dir" | cut -d/ -f4)"
        bin="$(command -v "php$ver" || command -v php)"
        [ -n "$bin" ] || continue
        echo "-- $dir ($bin)"
        # PHP_INI_SCAN_DIR + -c: la config REAL de ese SAPI, no la del CLI
        PHP_INI_SCAN_DIR="$dir/conf.d" "$bin" -c "$dir/php.ini" -r '
            foreach (["memory_limit", "max_execution_time", "post_max_size", "upload_max_filesize",
                      "opcache.enable", "opcache.memory_consumption", "opcache.max_accelerated_files",
                      "opcache.validate_timestamps", "opcache.revalidate_freq", "opcache.jit",
                      "apc.enabled", "apc.shm_size", "realpath_cache_size", "session.save_handler",
                      "session.save_path", "session.gc_maxlifetime", "zlib.output_compression"] as $k)
                printf("   %-30s %s\n", $k, var_export(ini_get($k), true));' 2>&1 | grep -v "^PHP Warning"
    done
    if [ -x /opt/lampp/bin/php ]; then
        echo "-- XAMPP"
        /opt/lampp/bin/php -i 2>/dev/null | grep -E "^(memory_limit|max_execution_time|opcache.enable |opcache.memory_consumption) "
    fi
}

logs_acceso() {
    local files
    files="$(ls -1 /var/log/apache2/*access*.log* /var/log/nginx/*access*.log* /opt/lampp/logs/access_log* 2>/dev/null)"
    if [ -z "$files" ]; then echo "no se encontraron logs de acceso"; return; fi
    ls -la $files
    $BAJA zcat -f $files 2>/dev/null | $BAJA awk '
        BEGIN { meses = "JanFebMarAprMayJunJulAugSepOctNovDec" }
        match($0, /\[[0-9]+\/[A-Za-z]+\/[0-9]+:[0-9]+:[0-9]+/) {
            split(substr($0, RSTART + 1, RLENGTH - 1), p, /[\/:]/)   # 16 Sep 2026 10 15
            dia = sprintf("%s-%02d-%02d", p[3], (index(meses, p[2]) + 2) / 3, p[1])
            total++; porDia[dia]++; porHora[p[4]]++; porMinuto[dia " " p[4] ":" p[5]]++
            esVhost = ($1 ~ /\.[a-z]+:[0-9]+$/)                       # formato vhost_combined
            ipDia[dia SUBSEP (esVhost ? $2 : $1)] = 1
            if (esVhost) sitios[$1]++
            if (match($0, /"[A-Z]+ [^ ]+ HTTP/)) {
                split(substr($0, RSTART + 1, RLENGTH - 6), r, " ")
                ruta = r[2]; sub(/\?.*/, "", ruta)
                split(ruta, s, "/"); prefijos["/" s[2]]++
                if (ruta ~ /\.(css|js|map|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot)$/) estaticas++
                else rutas[ruta]++
                resto = substr($0, RSTART + RLENGTH)
                if (match(resto, /" [0-9][0-9][0-9] /)) estados[substr(resto, RSTART + 2, 3)]++
            }
        }
        END {
            printf "peticiones: %d | estaticas: %d | dinamicas: %d\n", total, estaticas, total - estaticas
            for (k in ipDia) { split(k, q, SUBSEP); ips[q[1]]++ }
            print "\n-- por dia: peticiones | IPs distintas"; fflush()
            c = "sort"; for (d in porDia) printf "%s  %8d  %6d\n", d, porDia[d], ips[d] | c; close(c)
            print "\n-- por hora del dia (suma de todos los dias)"; fflush()
            c = "sort"; for (h in porHora) printf "%s  %8d\n", h, porHora[h] | c; close(c)
            print "\n-- los 10 minutos con mas peticiones (pico real)"; fflush()
            c = "sort -nr | head -10"; for (m in porMinuto) printf "%6d  %s\n", porMinuto[m], m | c; close(c)
            print "\n-- codigos HTTP"; fflush()
            c = "sort"; for (e in estados) printf "%s  %8d\n", e, estados[e] | c; close(c)
            print "\n-- 30 rutas dinamicas mas pedidas"; fflush()
            c = "sort -nr | head -30"; for (x in rutas) printf "%8d  %s\n", rutas[x], x | c; close(c)
            print "\n-- 15 prefijos de ruta (todas las peticiones)"; fflush()
            c = "sort -nr | head -15"; for (x in prefijos) printf "%8d  %s\n", prefijos[x], x | c; close(c)
            print "\n-- sitios (si el formato del log los trae)"; fflush()
            c = "sort -nr | head -10"; for (x in sitios) printf "%8d  %s\n", sitios[x], x | c; close(c)
        }'
}

logs_error() {
    local files actuales
    files="$(ls -1 /var/log/apache2/*error*.log* /var/log/nginx/*error*.log* /opt/lampp/logs/error_log* /var/log/php*-fpm.log* 2>/dev/null)"
    if [ -z "$files" ]; then echo "no se encontraron logs de error"; return; fi
    echo "-- señales de saturacion y fallos (todos los logs rotados)"
    $BAJA zcat -f $files 2>/dev/null \
        | grep -oE "AH00161|MaxRequestWorkers|max_children|PHP Fatal error|Allowed memory size|Maximum execution time|Segmentation fault|upstream timed out|too many connections|remaining connection slots|server closed the connection|SSL SYSCALL error" \
        | sort | uniq -c | sort -nr
    echo "-- ultimas 20 lineas relevantes del log actual"
    actuales="$(ls -1 /var/log/apache2/*error*.log /var/log/nginx/*error*.log /opt/lampp/logs/error_log 2>/dev/null)"
    [ -n "$actuales" ] && grep -hE "AH00161|max_children|PHP Fatal|Allowed memory|Maximum execution|Segmentation|timed out|too many connections|remaining connection slots" $actuales 2>/dev/null \
        | tail -20 | cut -c1-300
}

mariadb_local() {
    local cli sqlf="$DIR/diagnostico_mariadb_sistema_viejo.sql"
    if ! pgrep -x mysqld >/dev/null && ! pgrep -x mariadbd >/dev/null; then
        echo "no hay MariaDB/MySQL corriendo en este servidor"; return
    fi
    if [ ! -f "$sqlf" ]; then echo "falta $sqlf (copiar la carpeta completa)"; return; fi
    for cli in "mysql --defaults-file=/etc/mysql/debian.cnf" "mysql" "mariadb" "/opt/lampp/bin/mysql -u root"; do
        hay "${cli%% *}" || continue
        if timeout 10 $cli --connect-timeout=5 -N -e "SELECT 1" >/dev/null 2>&1; then
            echo "(conectado con: ${cli%% *})"
            timeout 600 $BAJA $cli --connect-timeout=5 --force -t < "$sqlf" 2>&1
            return
        fi
    done
    echo "No pude entrar a MariaDB sin contraseña: ejecuta diagnostico_mariadb_sistema_viejo.sql desde HeidiSQL/phpMyAdmin."
}

erp_latencia_bd() {
    [ -f /var/www/sistema/config/database.php ] || { echo "no es el servidor del ERP (no existe /var/www/sistema)"; return; }
    timeout 120 php <<'PHP' 2>&1
<?php
$cfg = require '/var/www/sistema/config/database.php';
$ms  = fn(int $a, int $b): float => ($b - $a) / 1e6;
$rep = function (string $n, array $v): void {
    $v = array_values(array_filter($v, fn($x) => !is_nan($x)));
    if (!$v) { printf("  %-34s sin conexion\n", $n); return; }
    sort($v);
    printf("  %-34s min %7.1f | mediana %7.1f | max %7.1f ms\n", $n, $v[0], $v[intdiv(count($v), 2)], end($v));
};
printf("Destino configurado: %s:%d (base %s)\n", $cfg['host'], $cfg['port'], $cfg['name']);

// 1) Red pura (solo abrir TCP): endpoint publico y privado (VPC) de la base gestionada
$hosts = [$cfg['host']];
if (str_ends_with($cfg['host'], '.db.ondigitalocean.com') && !str_starts_with($cfg['host'], 'private-')) {
    $hosts[] = 'private-' . $cfg['host'];
}
foreach ($hosts as $h) {
    $ip = gethostbyname($h);
    printf("%s -> %s\n", $h, $ip === $h ? 'NO RESUELVE' : $ip);
    if ($ip === $h) continue;
    foreach (array_unique([(int) $cfg['port'], 25060, 25061]) as $port) {
        $v = [];
        for ($i = 0; $i < 5; $i++) {
            $a = hrtime(true);
            $s = @stream_socket_client("tcp://$ip:$port", $en, $es, 3);
            $v[] = $s ? $ms($a, hrtime(true)) : NAN;
            if ($s) fclose($s);
        }
        $rep("TCP puerto $port", $v);
    }
}

// 2) Lo que paga cada peticion: app/core/Database.php abre una conexion nueva
//    por peticion y luego ejecuta dos SET por separado.
$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $cfg['host'], $cfg['port'], $cfg['name']);
$con = $set = $sel = [];
try {
    for ($i = 0; $i < 8; $i++) {
        $t0  = hrtime(true);
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $t1  = hrtime(true);
        $pdo->exec("SET client_encoding TO 'UTF8'");
        $pdo->exec("SET TIME ZONE 'America/Guayaquil'");
        $t2  = hrtime(true);
        for ($j = 0; $j < 5; $j++) {
            $a = hrtime(true);
            $pdo->query('SELECT 1')->fetchColumn();
            $sel[] = $ms($a, hrtime(true));
        }
        $con[] = $ms($t0, $t1);
        $set[] = $ms($t1, $t2);
        $pdo = null;
    }
    $rep('abrir conexion (TCP+TLS+login)', $con);
    $rep('los 2 SET de Database.php', $set);
    $rep('SELECT 1 (ida y vuelta)', $sel);
} catch (Throwable $e) {
    echo "Error midiendo la conexion: ", $e->getMessage(), "\n";
}
PHP
}

erp_ttfb_local() {
    [ -d /var/www/sistema ] || return
    for i in 1 2 3 4 5; do
        curl -sk -o /dev/null -m 20 --resolve erp.camagare.com.ec:443:127.0.0.1 \
            -w "login servido desde el propio droplet: http %{http_code} | ttfb %{time_starttransfer}s | total %{time_total}s\n" \
            https://erp.camagare.com.ec/
    done
}

{
echo "Diagnostico de rendimiento (solo lectura) — $(date '+%Y-%m-%d %H:%M:%S %Z') — servidor: $HOST"

sec "1. IDENTIDAD"
run 'uptime'
run 'hostnamectl 2>/dev/null | grep -E "Operating System|Kernel|Virtualization|Hardware"'
run 'for k in id hostname region interfaces/public/0/ipv4/address interfaces/private/0/ipv4/address; do printf "%-36s %s\n" "$k" "$(curl -s -m 3 http://169.254.169.254/metadata/v1/$k)"; done'
run 'nproc; lscpu | grep -E "^Model name|^CPU\(s\)|^Hypervisor|^Thread"'
run '[ -f /var/run/reboot-required ] && echo "REINICIO PENDIENTE" || echo "sin reinicio pendiente"'

sec "2. CPU Y MEMORIA (muestra de 30 s; columna st = CPU robada por el hipervisor)"
run 'free -m'
run 'swapon --show; echo "swappiness: $(cat /proc/sys/vm/swappiness)"'
run 'vmstat -w 5 7'
run 'for r in cpu memory io; do echo "-- presion $r"; cat /proc/pressure/$r 2>/dev/null; done'
run memoria_por_proceso
run 'ps -eo pid,user,%cpu,%mem,rss,etime,args --sort=-%cpu | head -12 | cut -c1-200'
run 'journalctl -k --since "-14 days" --no-pager 2>/dev/null | grep -iE "out of memory|oom-kill|killed process" | tail -15'

sec "3. DISCO"
run 'df -hT -x tmpfs -x devtmpfs -x squashfs -x overlay'
run 'df -i / | tail -1'
run "$BAJA du -sh /var/www/* /var/log /var/lib/php/sessions /var/lib/mysql /opt/lampp /home 2>/dev/null"
run 'echo "archivos de sesion PHP: $(find /var/lib/php/sessions -type f 2>/dev/null | wc -l)"'
run '[ -d /var/www/sistema/storage ] && du -sh /var/www/sistema/storage/* 2>/dev/null | sort -h | tail -12'

sec "4. SERVICIOS, PUERTOS Y TAREAS PROGRAMADAS"
run 'systemctl list-units --type=service --state=running --no-pager --no-legend --plain | cut -d" " -f1'
run 'systemctl --failed --no-pager --no-legend --plain'
run 'ss -ltnp'
run 'ss -s | head -3'
run 'systemctl list-timers --no-pager | head -25'
run 'for u in root www-data; do echo "-- crontab $u"; crontab -l -u "$u" 2>/dev/null | grep -vE "^[[:space:]]*(#|$)"; done'
run 'for f in /etc/cron.d/*; do echo "-- $f"; grep -vE "^[[:space:]]*(#|$)" "$f"; done'
run 'for d in /usr/local/psa /usr/local/cpanel /www/server/panel /usr/local/CyberCP /usr/local/hestia /usr/local/vesta /etc/webmin /opt/lampp; do [ -e "$d" ] && echo "detectado: $d"; done; echo "do-agent (graficas de memoria en DO): $(systemctl is-active do-agent 2>/dev/null)"'

sec "5. SERVIDOR WEB Y PHP"
if hay apache2ctl; then
    run 'apache2ctl -v | head -1; apache2ctl -V 2>/dev/null | grep -i "Server MPM"'
    run 'apache2ctl -S 2>&1 | head -40'
    run 'apache2ctl -M 2>/dev/null | sed 1d | cut -d" " -f2 | tr "\n" " "; echo'
    run 'grep -vhE "^[[:space:]]*(#|$)" /etc/apache2/mods-enabled/mpm_*.conf'
    run 'grep -rhiE "^[[:space:]]*(KeepAlive|MaxKeepAliveRequests|KeepAliveTimeout|Timeout)[[:space:]]" /etc/apache2/apache2.conf /etc/apache2/conf-enabled/ /etc/apache2/sites-enabled/'
    run 'grep -rhiE "^[[:space:]]*(LogFormat|CustomLog|ErrorLog)" /etc/apache2/apache2.conf /etc/apache2/conf-enabled/ /etc/apache2/sites-enabled/ | sed "s/^[[:space:]]*//" | sort | uniq -c'
    run workers_apache
    run 'curl -s -m 5 "http://127.0.0.1/server-status?auto" | grep -E "^(ServerUptime|Total Accesses|ReqPerSec|BusyWorkers|IdleWorkers|Scoreboard)"'
fi
if hay nginx; then
    run 'nginx -v; echo "activo: $(systemctl is-active nginx) | arranque automatico: $(systemctl is-enabled nginx)"'
    run 'nginx -T 2>/dev/null | grep -E "^[[:space:]]*(worker_processes|worker_connections|keepalive_timeout|client_max_body_size|gzip|server_name|root|listen|fastcgi_pass|proxy_pass)[[:space:]]" | sed "s/^[[:space:]]*//" | sort | uniq -c | head -60'
fi
run 'grep -rhE "^[[:space:]]*(listen|pm|pm\.max_children|pm\.start_servers|pm\.min_spare_servers|pm\.max_spare_servers|pm\.max_requests)[[:space:]]*=" /etc/php/*/fpm/pool.d/ 2>/dev/null'
run 'php -v 2>/dev/null | head -1; ls /etc/php 2>/dev/null'
run php_config

sec "6. LOGS DE ACCESO (lo que conserve logrotate, normalmente 14 dias)"
logs_acceso 2>&1

sec "7. LOGS DE ERROR"
logs_error 2>&1

sec "8. MARIADB / MYSQL LOCAL"
mariadb_local 2>&1

sec "9. ERP: RED Y COSTO DE CONECTAR A LA BASE"
erp_latencia_bd
erp_ttfb_local

printf '\nFin: %s\n' "$(date '+%Y-%m-%d %H:%M:%S')"
} > "$TMP" 2>&1

# Enmascarar cualquier secreto que se haya colado (URIs con clave, PASSWORD=..., mysql -p...)
sed -E \
    -e 's#(://[^:/@[:space:]]+:)[^@[:space:]]+@#\1****@#g' \
    -e 's/((PGPASSWORD|MYSQL_PWD|PASSWORD|PASSWD|PASS_DB|DB_PASS|SECRET|TOKEN|API_KEY)[[:space:]]*[=:][[:space:]]*)[^[:space:]]+/\1****/Ig' \
    -e 's/((mysql|mysqldump|mariadb|mariadb-dump)[^|;&]*[[:space:]]-p)[^[:space:]]+/\1****/g' \
    "$TMP" > "$SALIDA"
rm -f "$TMP"
chmod 600 "$SALIDA"
echo "Listo: $SALIDA ($(wc -l < "$SALIDA") lineas)"
