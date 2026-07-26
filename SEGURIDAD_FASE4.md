# Seguridad — Fase 4: freno de fuerza bruta y CSRF en el login

Continúa `SEGURIDAD_FASE3.md`.

---

## Contexto: la contraseña sigue en 4 caracteres

Por decisión del propietario, el mínimo de contraseña **se mantiene en 4
caracteres**; no se cambió nada al respecto.

Conviene tenerlo presente al leer el resto: sin límite de intentos, probar todas
las combinaciones de 4 caracteres es cuestión de minutos, y las cédulas
ecuatorianas —el usuario de acceso— son de formato predecible. **El bloqueo por
intentos pasa a ser la defensa principal de la puerta de entrada**, no un extra.
Por eso se configuró más estricto de lo habitual (5 intentos, no 10) y por eso
conviene revisar de vez en cuando quién acumula fallos (consulta al final).

Si más adelante se sube el mínimo a 8, la protección es de otro orden. Queda a su
criterio.

---

## Parte A — Freno de fuerza bruta en el login

Antes, `AuthController::login()` no tenía límite de intentos, ni retardo, ni
bloqueo: se podían lanzar miles de combinaciones por minuto contra cualquier
cédula.

### Cómo funciona

Dos frenos independientes, ambos comprobados **antes** de tocar la contraseña
(un atacante bloqueado no consume ni una comparación de hash):

| Freno | Umbral | Bloqueo | Qué detiene |
|---|---|---|---|
| Por cuenta | 5 fallos en 15 min | 15 min | El ataque contra un usuario concreto |
| Por IP | 20 fallos en 15 min | 30 min | El ataque que va rotando cédulas desde el mismo origen |

El umbral por IP es holgado a propósito: en una oficina con IP compartida, varias
personas equivocándose no deben dejar fuera a toda la oficina.

**Un acceso correcto pone el contador a cero.** Los fallos se cuentan desde el
último éxito, así que quien se equivocó ayer y entró bien no arrastra ese saldo.

Piezas: `app/Services/LoginRateLimitService.php`,
`app/repositories/LoginIntentoRepository.php` y la tabla `login_intentos`.
Parámetros en `config/app.php` → `security.login`.

### El mensaje al usuario no filtra información

*"Demasiados intentos fallidos. Por seguridad, el acceso quedó bloqueado
temporalmente. Vuelva a intentarlo en 15 minutos."* — el mismo texto tanto si la
cédula existe como si no, para no confirmarle a nadie qué cuentas son reales.

### Tolerante a que la tabla no exista

Si `login_intentos` todavía no está creada, el login **funciona con normalidad**
(sin freno) en vez de fallar. Verificado renombrando la tabla y probando. Esto
permite desplegar el código antes que el SQL sin riesgo.

---

## Parte B — El login ya está protegido con CSRF

En la Fase 3 el login quedó deliberadamente fuera, por el riesgo de dejar a todos
los clientes sin acceso si algo fallaba. Ahora sí está cubierto, verificado:

- Las tres vistas de `auth/` llevan el token: `login.php` y `confirmUser.php`
  (standalone, se les añadió el partial) y `cambiarClave.php` (usa el layout).
- `Auth` sigue siendo público para la **autenticación** (hay que poder llegar al
  login sin sesión) pero ya **no está exento de CSRF**. En `Application::run()`
  son ahora dos listas distintas.

Esto cierra el *login-CSRF*: que una web maliciosa fuerce a la víctima a iniciar
sesión con la cuenta del atacante, de modo que todo lo que haga después quede
registrado en esa cuenta ajena.

> Recordatorio: el CSRF sigue arrancando en modo `log`. Hasta que se ponga en
> `enforce` (ver Fase 3), esta protección registra pero no bloquea.

---

## Despliegue

### 1. Código

```bash
cd /var/www/sistema && git pull origin main && systemctl reload php8.2-fpm
```

El login sigue funcionando aunque el paso 2 todavía no se haya hecho.

### 2. Base de datos

```bash
psql "$DATABASE_URL" -f /var/www/sistema/database/migrations/create_login_intentos.sql
```

O desde el cliente que use habitualmente, ejecutando
`database/migrations/create_login_intentos.sql`. Crea la tabla y sus tres índices;
es `IF NOT EXISTS`, se puede repetir sin daño.

### 3. Comprobar

Con una cédula que no exista, equivocarse 5 veces seguidas: al sexto intento debe
aparecer el aviso de bloqueo. Después:

```sql
SELECT identificador, ip, exitoso, created_at
  FROM login_intentos ORDER BY created_at DESC LIMIT 10;
```

---

## Operación

**Desbloquear a alguien que quedó fuera** (se equivocó de verdad y no quiere
esperar los 15 minutos):

```sql
DELETE FROM login_intentos WHERE identificador = '0102030405' AND exitoso = FALSE;
```

**Ver quién está bloqueado ahora mismo:**

```sql
SELECT identificador, COUNT(*) AS fallos, MAX(created_at) AS ultimo
  FROM login_intentos
 WHERE exitoso = FALSE AND created_at > NOW() - INTERVAL '15 minutes'
 GROUP BY identificador
HAVING COUNT(*) >= 5
 ORDER BY ultimo DESC;
```

**Detectar un ataque en curso** (muchos fallos desde una IP):

```sql
SELECT ip, COUNT(*) AS intentos, COUNT(DISTINCT identificador) AS cuentas_probadas
  FROM login_intentos
 WHERE exitoso = FALSE AND created_at > NOW() - INTERVAL '1 day'
 GROUP BY ip HAVING COUNT(*) > 50
 ORDER BY intentos DESC;
```

Si aparece una IP así, bloquearla en el firewall: `ufw deny from LA_IP`.

**Desactivar el freno temporalmente** (si diera problemas), en
`config/local.php` del servidor:

```php
'security' => ['login' => ['activo' => false]],
```

El historial se purga solo a los 90 días (`security.login.dias_retencion`).

---

## Verificación realizada

Contra la base de datos real, con el sistema levantado:

| Prueba | Resultado |
|---|---|
| 5 intentos fallidos seguidos | pasan (respuesta normal de credenciales) |
| 6.º y 7.º intento | **bloqueado**, "Vuelva a intentarlo en 15 minutos" |
| Otra cédula distinta durante ese bloqueo | no afectada — el bloqueo es por cuenta |
| 20 fallos desde la misma IP | **bloquea incluso una cédula nunca usada**, 30 min |
| Acceso correcto tras 5 fallos | contador a 0; el siguiente fallo cuenta 1, no 6 |
| Con la tabla renombrada (simula pre-despliegue) | login normal, sin errores |
| POST al login sin token CSRF (modo enforce) | **419 rechazado** |
| POST al login con el token de la página | 200, procesa normal |

Los registros de prueba se borraron de la tabla al terminar.
