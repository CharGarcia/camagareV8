# Seguridad — Fase 3: protección CSRF

Continúa `SEGURIDAD_FASE2.md`.

---

## El problema

Un usuario con sesión abierta en el ERP visita otra página en otra pestaña. Esa
página envía por detrás un `POST` al sistema y **el navegador adjunta la cookie de
sesión automáticamente**. Sin un token que solo el propio sistema conoce, el ERP no
puede distinguir esa petición de una legítima: crea el egreso, cambia la
configuración o elimina el registro, y en `log_sistema` queda a nombre del usuario.

`CLAUDE.md` §6 declara CSRF obligatorio, pero no existía en ningún POST del sistema.

## La solución: un punto único, cero cambios en los módulos

Con 1.030 `fetch`, 225 `XMLHttpRequest`, 410 `FormData` y 132 formularios
repartidos por ~80 módulos, tocarlos uno por uno no era viable ni mantenible. En su
lugar:

| Pieza | Qué hace |
|---|---|
| `app/helpers/Csrf.php` | Genera y valida el token (uno por sesión, comparado con `hash_equals`) |
| `public/js/csrf.js` | Envuelve `fetch` y `XMLHttpRequest` y añade el campo oculto a los formularios: el token viaja solo |
| `app/views/partials/csrf.php` | Inyecta el token y carga el interceptor. Va en `<head>`, antes que cualquier otro script |
| `Application::run()` | Valida en un solo sitio, antes de instanciar el controlador |

**Un módulo nuevo no tiene que hacer nada**: si usa el layout estándar, queda
protegido automáticamente.

### El token nunca sale del sitio

`csrf.js` compara el origen de cada petición y **solo adjunta el token a URLs del
propio dominio**. Las llamadas a servicios externos (SRI, Payphone, Kushki,
`api.qrserver.com`, CDNs) salen sin él. Enviarlo a un tercero equivaldría a
regalárselo a un atacante.

### Qué queda fuera de la validación, y por qué

- **`/api/v1/*`** — es stateless y se autentica con `Bearer` JWT. Sin cookie no hay
  CSRF posible: el navegador de la víctima no adjunta el token de la app móvil.
- **Controladores públicos** (webhooks de WhatsApp/Payphone/Nuvei, portal de
  reservas, asistencia, factura express, aprobaciones por enlace) — no hay sesión
  autenticada que suplantar, y sus vistas no cargan el layout que inyecta el token.
- **Endpoints del agente de Chrome** — se autentican con su propio token.

> **El login (`Auth`) también queda fuera en esta fase.** Su vista es *standalone* y
> no carga el layout. Protegerlo exige inyectarle el token y verificar que funciona;
> si algo fallara, nadie podría entrar al sistema. El riesgo que queda (*login CSRF*)
> es menor que el de dejar a todos los clientes fuera, así que se hará por separado
> y con verificación propia.

### Vistas standalone

Diez vistas autenticadas arman su propio `<head>` y no pasan por el layout: POS
(`caja_sesion`), KDS, mesas, comandas, soporte IA y las tres del manual. A todas se
les añadió el `require` del partial. **Al crear una vista standalone nueva hay que
incluirlo**, o sus peticiones serán rechazadas en modo `enforce`:

```php
<?php require MVC_APP . "/views/partials/csrf.php"; ?>
```

Facturas de venta y recibos aparecían en la búsqueda inicial, pero su `<head>` está
dentro de un template de impresión en JavaScript: usan el layout normal y ya estaban
cubiertas.

---

## Despliegue: primero observar, después bloquear

Igual que con la CSP, la validación arranca **sin bloquear**. En
`config/app.php` → `security.csrf`:

| Modo | Comportamiento |
|---|---|
| `log` (por defecto) | No rechaza nada. Anota en `storage/logs/csrf.log` lo que se habría rechazado |
| `enforce` | Rechaza con HTTP 419 |
| `off` | Desactivado |

### Paso 1 — Desplegar y observar

```bash
cd /var/www/sistema && git pull origin main && systemctl reload php8.2-fpm
```

Usar el sistema con normalidad unos días —facturación, compras, inventario,
tesorería, contabilidad, POS, restaurante, el manual— y revisar:

```bash
cat /var/www/sistema/storage/logs/csrf.log
```

- **Archivo vacío** → todo el sistema está enviando el token. Continuar al paso 2.
- **Con líneas** → cada una indica un punto que aún no lo envía. Anotar el
  controlador y la acción; lo habitual será una vista standalone nueva a la que le
  falta el `require` del partial.

### Paso 2 — Activar el bloqueo

En `config/local.php` **del servidor**:

```php
'security' => ['csrf' => 'enforce'],
```

```bash
systemctl reload php8.2-fpm
```

Conviene revisar `csrf.log` los primeros días también después de activarlo.

### Qué ve el usuario si su token caduca

Un HTTP 419 con el mensaje *"Su sesión expiró o la página estuvo abierta demasiado
tiempo. Recargue (F5) e inténtelo otra vez."* — en JSON si la petición era AJAX, o
como página si era un formulario. Además, `csrf.js` muestra una barra roja fija
ofreciendo recargar. El caso típico no es un ataque, sino una pestaña abierta desde
ayer.

---

## Verificación realizada

Lógica del helper: **22 comprobaciones, todas correctas** — qué métodos y
controladores se validan, comparación del token (correcto, falso, vacío, nulo,
alterado en un carácter), lectura desde cabecera y desde formulario.

Flujo HTTP real, con sesión y contra una acción inexistente para no tocar datos
(419 = bloqueado; 404 = el CSRF dejó pasar):

| Caso | Resultado |
|---|---|
| POST sin token (simula el ataque) | **419 bloqueado** |
| POST con token falso | **419 bloqueado** |
| POST con token en la cabecera | 404 → pasó |
| POST con token en el formulario | 404 → pasó |
| GET normal | no se valida |
| POST a webhook público | no se valida |

En modo `log` la misma petición no se bloqueó y quedó registrada en `csrf.log` con
usuario, ruta, origen y referer.

---

## Lo que esto no cubre

`'unsafe-inline'` sigue siendo necesario en la CSP por los 1.617 `onclick` de las
vistas, así que **un XSS dentro del propio sistema podría leer el token** y saltarse
esta protección. CSRF y XSS son defensas distintas: esta fase cierra la primera.
