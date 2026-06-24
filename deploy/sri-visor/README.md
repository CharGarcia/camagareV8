# Descarga asistida SRI — infraestructura del visor remoto

Esta carpeta contiene lo necesario para habilitar la **descarga asistida** en el servidor
(Ubuntu 24.04, DigitalOcean). El usuario ve el portal real del SRI por una pantalla remota
(noVNC) embebida en el sistema y hace el clic **CONSULTAR** sobre el portal; así el reCAPTCHA
del SRI lo evalúa como humano. Luego el sistema baja cada XML por el webservice oficial
(sin captcha) y lo registra.

```
navegador ─wss─> nginx (/sri-visor-ws/, valida token) ─> websockify(127.0.0.1:6080)
        └─ noVNC (vnc_lite.html)                          └─> x11vnc(:5900) ─> Xvfb(:99) ─> Chromium (scraper modo asistido)
```

> El código de la app ya está listo. Esto es **solo infraestructura del servidor**; nada de
> esto corre en Windows local (allí la descarga asistida no aplica).

---

## 1. Instalar paquetes

```bash
sudo apt-get update
sudo apt-get install -y xvfb x11vnc websockify novnc x11-utils
```

- `xvfb` — display virtual `:99` (ya se usaba para el modo automático).
- `x11vnc` — comparte ese display por VNC.
- `websockify` — puente WebSocket ↔ VNC.
- `novnc` — cliente web (queda en `/usr/share/novnc`, incluye `vnc_lite.html`).
- `x11-utils` — `xdpyinfo`, usado para esperar a que Xvfb esté listo.

## 2. Publicar el cliente noVNC bajo el sistema

Opción A (symlink, recomendada):
```bash
sudo ln -s /usr/share/novnc /var/www/sistema/public/novnc
```
Opción B: servirlo con el bloque `location /sistema/public/novnc/` del `nginx-sri-visor.conf`.

El frontend carga `‹BASE_URL›/novnc/vnc_lite.html`. Verifica que abra esa URL en el navegador.

## 3. Permisos (gotcha conocido)

El scraper corre como **www-data** (lo lanza PHP-FPM). Los perfiles persistentes deben ser de
www-data (el modo asistido usa un perfil dedicado `.sri_profile_asistido`, distinto del automático):
```bash
sudo chown -R www-data:www-data /var/www/sistema/scripts/.sri_profile
# El perfil del modo asistido se crea solo en la 1ª corrida; asegura permisos del directorio padre:
sudo chown -R www-data:www-data /var/www/sistema/scripts
sudo mkdir -p /var/www/sistema/storage/sri_visor
sudo chown -R www-data:www-data /var/www/sistema/storage/sri_visor
```
> No ejecutes el scraper como `root` sobre `.sri_profile` (crea archivos de root y luego
> www-data no puede abrir el navegador). Ver memoria `sri-descargas-produccion`.

## 4. Instalar los servicios systemd

```bash
sudo cp deploy/sri-visor/sri-xvfb.service       /etc/systemd/system/
sudo cp deploy/sri-visor/sri-x11vnc.service     /etc/systemd/system/
sudo cp deploy/sri-visor/sri-websockify.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now sri-xvfb sri-x11vnc sri-websockify
sudo systemctl status sri-xvfb sri-x11vnc sri-websockify --no-pager
```

Comprobaciones rápidas:
```bash
DISPLAY=:99 xdpyinfo >/dev/null && echo "Xvfb :99 OK"
ss -ltnp | grep -E '5900|6080'   # x11vnc en 5900, websockify en 6080 (loopback)
```

## 5. nginx

Añade el contenido de `nginx-sri-visor.conf` **dentro del `server { }`** del sitio con HTTPS,
ajusta el `proxy_pass` del bloque de validación si tu proyecto usa un vhost con `server_name`
propio, y recarga:
```bash
sudo nginx -t && sudo systemctl reload nginx
```

El endpoint de validación es `‹BASE_URL›/modulos/DescargasSri/validarVisorTokenAjax?token=…`
(responde 200/403). El WebSocket `/sri-visor-ws/` queda protegido por ese token efímero.

---

## 6. Suspender el modo automático (reversible) — ya hecho en código

La palanca está en `config/app.php`: `'sri_descarga_auto_suspendida' => true`. Con eso, tanto el
cron directo (`app/cron/sri_descarga_automatica.php`) como las automatizaciones
(`cron_runner.php` → `DescargasSriHandler`) no descargan nada.

Pasos operativos que faltan en el servidor:

1. **Dejar inactivas** las automatizaciones de descargas SRI (por empresa):
   ```sql
   UPDATE automatizaciones SET estado = 'inactivo' WHERE modulo = 'descargas_sri';
   ```
   (o desde la UI `/modulos/automatizaciones`).
2. **Comentar** la línea del cron directo en el crontab, si existía:
   ```bash
   sudo crontab -e
   # 0 2 * * *  php /var/www/sistema/app/cron/sri_descarga_automatica.php >> /var/log/sri_descarga.log 2>&1
   ```
   `cron_runner.php` se mantiene (sirve otras automatizaciones); el flag y el estado inactivo
   evitan que dispare descargas SRI.

**Reactivar** (si algún día se quiere volver al automático): flag a `false` y automatizaciones a `activo`.

---

## 7. Probar de punta a punta

1. Entra al módulo **Descargas SRI → Descarga Asistida (en vivo)**, elige período y pulsa
   **Iniciar sesión asistida**.
2. El modal debe mostrar el portal del SRI **ya logueado** con los filtros aplicados y el aviso
   "haz clic en CONSULTAR".
3. Haz clic en **CONSULTAR** dentro del visor. No debe salir "captcha incorrecta"; aparece la tabla.
4. El sistema lista las claves, baja los XML por webservice y registra. Revisa el resumen y la
   fila en `sri_descarga_auto_log` con `origen = 'asistido'`.
5. Confirma la suspensión del automático:
   ```bash
   php /var/www/sistema/app/cron/sri_descarga_automatica.php   # debe loguear "SUSPENDIDO" y salir
   ```

## 8. Troubleshooting

- **El modal queda negro / no conecta**: revisa `systemctl status sri-x11vnc sri-websockify` y
  que `ss -ltnp` muestre 5900 y 6080. Revisa la consola del navegador (errores wss).
- **403 al abrir el WS**: el token no se está validando. Prueba el endpoint
  `validarVisorTokenAjax?token=…` directo y revisa el `proxy_pass`/`Host` del bloque auth.
- **"Infraestructura del visor no disponible"**: faltan Xvfb :99 o websockify (paso 4).
- **"No se detectó la consulta"**: el humano no hizo clic en CONSULTAR dentro del tiempo (4 min),
  o el portal cambió de maquetado. Hay capturas en `public/sri_debug/` (prefijo `ASISTIDO_`).
- **Score del captcha sigue bajo**: limitación conocida (IP de datacenter). La interacción humana
  ayuda pero no garantiza; la palanca definitiva sería correr el navegador en la PC del usuario
  (IP residencial).

## 9. Concurrencia

Hay **una sola sesión asistida a la vez** (un único display `:99`). El servicio rechaza una
segunda sesión de otro usuario hasta que la primera termine o caduque (15 min). Para varias
sesiones simultáneas habría que replicar Xvfb/x11vnc/websockify en `:100/5901/6081`, etc.
(mejora futura).
