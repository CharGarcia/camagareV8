# CaMaGaRe ERP — Diagnóstico y plan para 5.000 clientes

**Fecha:** 12-09-2026 · **Alcance:** revisión completa del repositorio (2.404 archivos versionados,
~700.000 líneas propias), de la base local (350 tablas) y de la infraestructura documentada.

> Todo lo marcado como **medido** sale de una comprobación directa sobre el repo o la base local.
> Lo marcado como **estimado** es un cálculo de orden de magnitud: no se ha hecho prueba de carga real.

---

## 0. Veredicto en una página

**Funcionalmente, CaMaGaRe ya está en condiciones de competir con cualquier software contable del
Ecuador y de ganarle a la mayoría.** 113 módulos operativos, facturación electrónica completa,
contabilidad con asientos automáticos, nómina con IESS, inventario con kardex y lotes, POS,
restaurante con KDS y comandas, taller, car-wash, citas, consignaciones, declaraciones (IVA, 103,
ATS, ADI), SuperCías (ESF/ERI/ECP/EFE), activos fijos, app móvil nativa y manual de usuario con
cobertura del 100% de los módulos. Eso no lo tiene casi nadie en el mercado local.

**Técnicamente, el sistema NO está en condiciones de albergar 5.000 clientes.** No por el código de
negocio —que está mejor ordenado de lo habitual— sino por cuatro cosas concretas:

| # | Problema | Situación medida | Riesgo a 5.000 clientes |
|---|----------|------------------|-------------------------|
| 1 | **Polling cada 5 segundos** | 2 endpoints golpeados cada 5 s por *cada pestaña abierta* | ~600 req/s de puro ruido; el servidor actual da del orden de 20-50 req/s |
| 2 | **Infraestructura de 1 vCPU / 1,9 GB con Apache + mod_php** | medido en jul-2026; nginx instalado pero caído | techo de ~25 peticiones concurrentes; sin escalado horizontal posible |
| 3 | **Cero pruebas automatizadas y cero CI** | 0 tests en ~700k líneas | cada despliegue es una apuesta; un error de IVA afectaría a 5.000 empresas a la vez |
| 4 | **62 tablas multiempresa sin índice por `id_empresa`** | incluye `modulos_asignados`, `plan_cuentas`, `productos_bodegas`, `usuarios_preferencias` | escaneo completo de tabla en cada carga de página cuando haya miles de empresas |

Hoy no se nota nada de esto porque la base tiene 2 empresas y 64 MB. Los cuatro problemas son de
los que **no avisan**: funcionan perfecto hasta que dejan de funcionar de golpe, todos a la vez.

**La buena noticia:** ninguno requiere reescribir el sistema. Son trabajos acotados de
infraestructura, índices y disciplina de pruebas. La arquitectura MVC propia aguanta.

---

## 1. Lo que ya está bien y NO hay que tocar

Esto es importante decirlo porque define qué no rehacer:

- **Separación de capas respetada de verdad** (medido): 136 de 137 controladores de módulo extienden
  `BaseModuloController`; solo 12 de 216 controladores tienen SQL directo; **0 de 286 Services**
  tienen SQL. En proyectos de este tamaño esto casi nunca se cumple.
- **142 de 169 repositorios** extienden `BaseRepository`.
- **Multiempresa coherente**: 240 de 350 tablas llevan `id_empresa`; solo 1 repositorio de módulo no
  lo menciona en absoluto.
- **Seguridad con recorrido hecho**: CSRF centralizado, cabeceras de seguridad, freno de fuerza bruta
  en el login, sesión única por usuario, auditoría en `log_sistema`, eliminación lógica en todas las
  tablas operativas, bloqueo de concurrencia con `pg_advisory_xact_lock`.
- **Documentación de usuario completa**: 115 artículos para 113 módulos. Esto es un activo comercial
  real: los competidores venden con manuales en PDF viejo o sin manual.
- **App móvil moderna**: Expo 54 / React Native 0.81 / React 19, con API JWT propia (14 controladores).
- **Convenciones escritas y cumplidas** (`CLAUDE.md`): por eso el sistema creció a 113 módulos sin
  volverse incoherente.

---

## 2. El techo real de hoy (los números)

**Lo que consume una sola pestaña abierta, sin que el usuario haga nada** (medido en
`app/views/partials/scripts.php` y `navbar.php`):

- `/auth/verificar-sesion` → cada 5 s
- `CMG_refreshContadores()` (avisos del navbar) → cada 5 s

Son **24 peticiones por minuto por pestaña**. Cada una arranca PHP completo, lee `config/app.php`
dos veces, abre una conexión PDO y consulta la base. La de sesión no tiene caché.

**Proyección** (estimado): 5.000 clientes × 3 usuarios = 15.000 usuarios. Con un pico de
concurrencia conservador del 10% son 1.500 pestañas abiertas → **600 peticiones por segundo solo de
polling**, antes de que nadie emita una factura.

**Capacidad actual** (estimado sobre la infra medida en jul-2026: 1 vCPU, 1,9 GB, Apache prefork con
mod_php): del orden de **20-50 req/s** para peticiones ligeras, y un techo duro de **~25 peticiones
concurrentes** por el límite de conexiones de la base gestionada.

**Falta un factor de 20 a 50x.** Y el limitante no es el ERP: es el diseño de refresco y el hardware.

Añádase que **69 controladores generan PDF y 48 generan Excel de forma síncrona dentro del request
web** (medido), con `set_time_limit(1800)` y `set_time_limit(0)` en algunos casos. En Apache prefork,
un solo usuario exportando 500 documentos bloquea un worker durante minutos. Con 5.000 clientes eso
no es un riesgo: es una certeza diaria.

---

## 3. Bloque A — Escalabilidad (sin esto no hay 5.000 clientes)

Ordenado por relación impacto/esfuerzo. **A1 a A4 son de días, no de meses, y multiplican la
capacidad varias veces cada uno.**

### A1. Matar el polling de 5 segundos ⭐ máxima prioridad

- Unificar los dos sondeos en **uno solo** y subir el intervalo a 30-60 s.
- Devolver `ETag` / `304 Not Modified` cuando no cambió nada: la respuesta pasa de consultar la base
  a no tocarla.
- Mover la validación de sesión a la **misma** respuesta de contadores (hoy son dos viajes).
- Para lo que sí necesita ser instantáneo (comandas, KDS, chat, WhatsApp), usar **SSE**
  (`text/event-stream`) en lugar de polling: una conexión abierta en vez de 12 peticiones por minuto.
- **Efecto estimado: reduce el tráfico de infraestructura entre 6x y 12x.** Es el cambio más barato
  y más grande de toda la lista.

### A2. Pasar de Apache + mod_php a nginx + PHP-FPM con OPcache

- Los runbooks del repo (`DEPLOY_DIGITALOCEAN.md`, `CONFIGURAR_SERVIDOR_COMPLETO.md`) ya describen
  nginx + php-fpm, pero **producción corre Apache con mod_php y nginx está caído**: la documentación
  de infra no refleja la realidad. Cerrar esa brecha.
- mod_php prefork reserva un proceso PHP completo por conexión, incluso para servir un CSS.
  PHP-FPM con `pm=dynamic` atiende mucho más con la misma RAM.
- **OPcache no aparece configurado en ningún runbook.** En un sistema de 700k líneas, compilar PHP en
  cada petición es de los desperdicios más grandes que hay. Activarlo es una línea de `php.ini`.
- **Efecto estimado: 3-5x más peticiones con el mismo hardware.**

### A3. Arreglar el `?v=time()` de los assets ⭐ muy barato

- **290 ocurrencias en 110 archivos** (medido): CSS y JS llevan la hora actual como parámetro, así
  que **el navegador vuelve a descargar todo en cada carga de página**. Nunca hay caché.
- Reemplazar por la versión del despliegue (hash del commit o `filemtime`), y servir con
  `Cache-Control: immutable`.
- **Efecto: menos ancho de banda, páginas visiblemente más rápidas, menos trabajo del servidor.
  Es media hora de trabajo.**

### A4. Pasar el pool de PostgreSQL a modo transaction

- Hoy el pool está en modo `Session` **obligado** porque `app/core/Database.php` ejecuta
  `SET TIME ZONE 'America/Guayaquil'` en cada conexión. En modo Session el pool multiplexa mucho
  peor: una conexión queda amarrada a un cliente.
- **Solución concreta:** fijar el timezone en el servidor, no por conexión —
  `ALTER ROLE <usuario_app> SET timezone = 'America/Guayaquil';` — y quitar el `SET TIME ZONE` del
  código. Entonces el pool puede ir en modo `transaction`.
- **Efecto estimado: el mismo límite de conexiones atiende del orden de 10x más peticiones.**

### A5. Caché compartida (Redis) en lugar de APCu

- `app/helpers/Cache.php` envuelve APCu y se usa en 7 sitios. APCu es **por proceso y por servidor**:
  con dos servidores web cada uno tiene su propia caché, y no hay forma de invalidar la del vecino.
- Redis permite además: sesiones compartidas (requisito para escalar horizontalmente), caché de menú
  y permisos, rate limiting, y cola de trabajos.

### A6. Cola de trabajos para todo lo pesado

- PDF y Excel masivos, envío al SRI, correos, importaciones, migraciones: a una cola con workers
  (Redis + un worker PHP, o `pg_cron` si se quiere evitar dependencias nuevas).
- El usuario recibe "su reporte se está generando, le avisamos" y el servidor web queda libre.
- Ya existe el precedente: `scripts/procesar_lote_sri.php`. Generalizarlo.

### A7. Escalado horizontal (cuando A1-A6 estén hechos)

Requisitos previos, en este orden:

1. **Sesiones fuera del disco local** (hoy en archivos, incluido `storage/sessions_api`) → Redis.
2. **Archivos fuera del disco local** → DigitalOcean Spaces / S3. Hoy `storage/` guarda **firmas
   electrónicas .p12, XMLs autorizados, selfies de asistencia y comprobantes** en el disco del
   droplet. Si ese disco se pierde, se pierden las firmas de los clientes y la evidencia legal.
   El backup de la base gestionada **no cubre** esa carpeta.
3. Recién entonces: 2+ servidores de aplicación detrás de un balanceador.

### A8. Dimensionamiento objetivo (estimado, para presupuestar)

Para 5.000 clientes con ~1.500 usuarios concurrentes en pico, orden de magnitud:

- 2-3 servidores de aplicación de 4 vCPU / 8 GB con PHP-FPM + OPcache.
- Balanceador gestionado.
- PostgreSQL gestionado de 4 vCPU / 8-16 GB con **réplica de lectura** para los reportes.
- Pool en modo transaction, Redis gestionado, Spaces para archivos.
- Esto se construye por etapas: no hace falta comprarlo hoy, pero sí diseñar para ello.

---

## 4. Bloque B — Confiabilidad (el riesgo más grande del negocio)

### B1. Suite de pruebas automatizadas ⭐ prioridad crítica

**Medido: 0 tests en el proyecto.** Un ERP que calcula IVA, retenciones, asientos contables,
secuenciales del SRI y stock, con ~700k líneas y 818 commits en 90 días, sin una sola prueba.

Con 2 empresas un error se descubre y se corrige. Con 5.000 empresas, un error en el cálculo del IVA
se descubre cuando 5.000 contadores ya presentaron la declaración. **Ese es el riesgo que puede
hundir el proyecto, y es el único de esta lista que no se arregla con dinero.**

No hace falta cubrir todo. Hace falta cubrir **lo que no puede fallar**, en este orden:

1. Cálculo de impuestos (IVA por `codigoPorcentaje`, ICE, retenciones, base imponible).
2. Generación del XML del SRI y la clave de acceso (por tipo de documento).
3. Asientos automáticos: cuadre debe = haber, cascada de resolución de cuentas, redondeo.
4. Secuenciales: unicidad por punto de emisión y ambiente, sin huecos ni duplicados.
5. Stock: kardex vs `productos_bodegas.stock_actual` bajo concurrencia.
6. **Aislamiento multiempresa**: una prueba que recorra todos los listados y verifique que la
   empresa A nunca ve un registro de la empresa B. Con 5.000 clientes, una fuga de datos entre
   empresas es un incidente legal, no un bug.

Herramienta: PHPUnit o Pest, con el patrón de PDO en sandbox con savepoint que ya se usa en el
proyecto para probar Services.

### B2. Integración continua

No hay CI (medido: solo `tools/deploy.ps1`). Un GitHub Action que en cada push corra `php -l` sobre
los archivos cambiados + la suite de tests + un linter, y **bloquee el merge si algo falla**. Es
medio día de trabajo y evita el 80% de los despliegues roto.

### B3. Control de migraciones de base de datos ⭐

**Medido: 449 archivos .sql, sin tabla de control de migraciones aplicadas, y solo 6 de 123 con
convención de fecha en el nombre.** Ya hay constancia de que **28 índices definidos en el repo no
existían en la base local**, y que "falta en local" no dice nada sobre producción.

Con una sola base compartida por 5.000 clientes, no saber qué se aplicó es el riesgo operativo más
grande del día a día. Hace falta:

- Tabla `schema_migrations` (nombre de archivo + hash + fecha aplicada).
- Convención obligatoria `YYYYMMDD_descripcion.sql`, migraciones **idempotentes**.
- Un comando que diga "faltan estas 3 por aplicar" y las aplique en orden, con registro.
- Esto es compatible con el flujo manual por pgAdmin: el comando solo tiene que *saber* y *registrar*.

### B4. Entorno de pruebas (staging)

Un droplet pequeño con copia de la base, donde el `git pull` va primero. Hoy el salto es de la
máquina de desarrollo directo a los clientes.

### B5. Observabilidad

- **`pg_stat_statements` no está instalado** (medido): hoy es imposible saber cuál es la consulta más
  lenta del sistema. Instalarlo es el primer paso de cualquier optimización seria.
- Registro de peticiones lentas (>1 s) y un panel con: peticiones por segundo, latencia p95, errores
  por minuto, conexiones a la base. Ya existe `errores_sistema`: falta la vista de tendencia.
- Alertas: "la base pasó de 80% de conexiones", "la cola tiene 500 trabajos pendientes".

### B6. Backups verificados de `storage/`

La base gestionada tiene backup. Las firmas .p12, los XMLs autorizados y las evidencias, no.
Y un backup no verificado no es un backup: hace falta una restauración de prueba periódica.

---

## 5. Bloque C — Base de datos

### C1. Los 62 índices faltantes ⭐ prioridad alta, esfuerzo bajo

**Medido:** 62 tablas tienen `id_empresa` pero **ningún índice que empiece por esa columna**. Entre
ellas, tablas que se consultan en cada carga de página o en cada venta:

| Tabla | Por qué duele |
|-------|---------------|
| `modulos_asignados` | permisos: se consulta en **cada petición** de cada usuario |
| `usuarios_preferencias` | columnas y vistas: se consulta en **cada listado** |
| `plan_cuentas` | contabilidad: en cada asiento |
| `productos_bodegas` | stock: en cada venta y cada movimiento |
| `productos_precios` | en cada línea de factura |
| `empresa_secuencial`, `empresa_punto_emision`, `empresa_establecimiento`, `empresa_formas_pago` | en cada documento emitido |
| `proveedores`, `vendedores`, `marcas`, `centro_costos`, `periodos_contables` | listados y selectores |

Con 2 empresas, un escaneo completo de `plan_cuentas` son 200 filas: instantáneo. Con 5.000 empresas
son 1.000.000 de filas escaneadas **para mostrar el selector de cuentas**. Multiplicado por cada
usuario y cada pantalla.

Además (medido): **57 tablas no tienen ningún índice más allá de la clave primaria**, y hay **40+
claves foráneas sin índice** (`productos_bodegas.id_bodega`, `productos_precios.id_producto`,
`plan_cuentas.id_centro_costos`…), lo que vuelve lento cada JOIN y cada borrado en cascada.

**Cómo hacerlo bien** (ya documentado en el proyecto): reusar nombre y definición exacta si el índice
ya existe en otro .sql, detectar equivalentes por su primera columna antes de crear (el
`IF NOT EXISTS` solo compara nombres y deja duplicados), usar `CREATE INDEX CONCURRENTLY` en
producción, y validar con `EXPLAIN` que el planificador los usa.

### C2. Retención y particionado de las tablas que crecen sin fin

Sin política de purga ni particionado (medido, salvo los 90 días de `login_intentos`). A 5.000
clientes, estas tablas crecen a cientos de millones de filas:

- `log_sistema` — guarda el "antes" y "después" de cada acción en JSON.
- `inventario_kardex` — cada movimiento de cada producto.
- Cabeceras y detalles de ventas y compras.
- El XML completo del SRI guardado en la base (`detalle_xml`).

Plan: particionar por rango de fechas (`pg_partman` o particiones declarativas), archivar el detalle
de auditoría de más de 2 años a almacenamiento frío, y mover los XML a Spaces dejando solo la
referencia en la base.

### C3. Réplica de lectura para reportes

Los reportes consolidados, estados financieros y auditoría contable son consultas caras. En una
réplica dejan de competir con la facturación por CPU.

---

## 6. Bloque D — Producto: lo que falta para ser el mejor del Ecuador

Ordenado por lo que más mueve la aguja comercial.

### D1. API pública documentada + webhooks ⭐ el mayor diferenciador disponible

Existe `/api/v1/*` con 14 controladores, pero **sin documentación OpenAPI** y pensada solo para la
app propia. Abrirla y documentarla convierte el ERP en plataforma: el cliente conecta su tienda en
línea, su marketplace, su Excel, su app. Los webhooks ("te aviso cuando se autorice una factura")
son lo que hace que un cliente no se vaya nunca. **Casi ningún competidor ecuatoriano ofrece esto
bien documentado.**

### D2. Alta de clientes en autoservicio

Hoy el alta de empresa la hace el superadministrador. Para llegar a 5.000 clientes eso no escala
—ni el tiempo del equipo, ni el embudo comercial—. Hace falta: registro con RUC, prueba de 15 días
que se crea sola, plantillas por giro de negocio (ya existen los módulos de restaurante, taller,
car-wash, comercio), asistente de primeros pasos y cobro automático de la suscripción al vencer
(`SuscripcionFacturacionService` ya existe: falta el ciclo completo autónomo).

### D3. Portal del cliente final

El cliente de nuestro cliente entra y ve sus facturas, su estado de cuenta, y **paga en línea**
(Payphone y Nuvei ya están integrados). Reduce la cartera de nuestros clientes, que es su dolor
número uno. Ya existen las piezas: factura express por QR, portal de citas, pedidos por QR.

### D4. Conciliación bancaria automática

Hoy es por carga de archivos. Integrar las APIs de los bancos grandes (Pichincha, Produbanco,
Guayaquil) o al menos los formatos de cash management, y el pago masivo a proveedores por archivo
bancario. Es de las cosas que un contador valora de inmediato.

### D5. IA con propósito claro

Ya hay base (`IaSoporteService`, `procesar_documento_ia`). Los tres usos que venden solos:

1. **Lectura de facturas de compra** (foto o PDF → documento registrado con su asiento).
2. **Asistente de consultas en lenguaje natural**: "¿cuánto le debo a este proveedor?",
   "¿por qué no cuadra el balance?".
3. **Alertas predictivas**: flujo de caja proyectado, clientes que van a caer en mora,
   obligaciones del SRI por vencer.

### D6. Inteligencia de negocio

Un tablero con indicadores comparables (ventas vs. mes anterior, margen por producto, rotación,
antigüedad de cartera) y suscripción por correo. Los módulos de reporte ya existen: falta la capa
que los convierte en decisiones.

### D7. Huecos funcionales detectados

- **Sin multimoneda** (medido: 0 referencias). Cierra la puerta a importadores y exportadores.
- Presupuestos y centros de costo existen pero con poco desarrollo (15 y 24 archivos).
- Sin firma electrónica de documentos internos (aprobaciones con validez legal).
- Revisar la cobertura de archivos para el IESS más allá de lo actual (sectoriales, avisos).

---

## 7. Bloque E — Facilidad de uso

### E1. Búsqueda global (Ctrl+K) ⭐ barato y muy visible

Con 113 módulos, el menú ya no alcanza. Un buscador único que encuentre módulos, clientes, productos
y documentos por número. No existe hoy (medido).

### E2. Un solo componente de tabla

**Medido:** `exportarExcel` reimplementado en 18 archivos, `buscar` en 21, `cambiarPagina` en 16, y
solo 3 componentes JS compartidos. Cada listado nuevo recopia lo mismo, y cada arreglo hay que
hacerlo N veces (ya pasó: el "dropdown de pestañas roto en 15 módulos").
Un componente único de tabla —ordenar, buscar, paginar, exportar, columnas, anchos— haría que los
listados nuevos sean de horas y que un arreglo valga para todos.

### E3. Partir las vistas monolíticas

`factura_venta/index.php` tiene **8.101 líneas**; `recibos_venta/index.php`, 6.481. Hay **28
archivos de más de 1.500 líneas** y **~68.000 líneas de JavaScript embebido dentro de las vistas**
(medido), que además no se puede cachear ni minificar. Cada cambio en esos archivos es una ruleta.
Sacar el JS a `public/js/modulos/` y partir por pestañas o secciones.

### E4. Dejar de depender de CDNs externos

Bootstrap, Bootstrap Icons, Font Awesome, Google Fonts, SweetAlert2 y Tom Select se cargan desde
jsdelivr y Google en cada página. Si un CDN falla o está bloqueado en la red del cliente, el ERP se
ve roto. Servirlos localmente: menos latencia, funciona en redes restringidas y habilita una CSP
estricta. Además **se cargan dos librerías de iconos completas** (Bootstrap Icons + Font Awesome):
elegir una ahorra cientos de KB por página.

### E5. Limpieza que también es rendimiento

- `app/views/modulos/factura_venta/index.php.bak` (5.030 líneas) versionado.
- **10 ramas `claude/*` sin integrar.**
- ~200 scripts sueltos en la raíz del proyecto (`fix_*.php`, `test_*.php`, `patch_*.php`,
  `check_*.php`) y **66 archivos .php en `public/`**. Buena noticia: esos **no están versionados**,
  así que no llegan a producción por `git pull`. Pero conviene borrarlos del entorno de desarrollo
  para que nadie los suba por accidente —ya pasó una vez, y en Fase 1 hubo que borrar 26 scripts
  que sí estaban publicados.
- **Sí llegan a producción y no deberían:** `public/supercias_produccion.sql` y
  `public/casilleros_dump.json`, descargables por cualquiera desde el navegador.
- Mensajes de commit de una palabra ("ret", "libre", "por cobra"): cuando haya que averiguar qué
  cambió en la versión que rompió algo, el historial no va a ayudar.

### E6. Accesibilidad y móvil

1.771 `onclick` inline (medido) impiden una CSP estricta y complican la accesibilidad por teclado.
Migrar a `addEventListener` por delegación de eventos, módulo por módulo, sin prisa.

---

## 8. Bloque F — Seguridad y confianza (lo que se vende, no solo lo que se protege)

Las fases 1 a 4 del plan de seguridad están hechas. Quedan dos cosas que ahora sí pesan, porque a
5.000 clientes el perfil de riesgo cambia:

### F1. Poner CSRF en `enforce` y CSP en `enforce`

Ambos están en modo "solo registrar" (`csrf => 'log'`, `csp => 'report-only'`). El mecanismo está
construido y probado: lo que falta es revisar que los registros estén vacíos y dar el paso. Mientras
estén en modo log, la protección existe en el código pero **no protege**.

### F2. Segundo factor y sesiones visibles

Con 5.000 clientes, una credencial robada de un usuario nivel 3 es un incidente que afecta a todos.
2FA al menos para nivel 3, y una pantalla de "mis sesiones y dispositivos". *(Fase 6 del plan, que en
su momento se decidió no hacer; se menciona solo porque el objetivo de 5.000 clientes cambia el
cálculo — la decisión sigue siendo del dueño del producto.)*

### F3. Confianza como argumento de venta

Página de estado del servicio, compromiso de disponibilidad publicado, artículo de seguridad en el
manual, y aviso por correo de acceso desde un dispositivo nuevo. Los competidores no lo tienen y es
lo primero que pregunta un cliente mediano antes de firmar.

---

## 9. Orden de ejecución sugerido

**Fase 1 — Cimientos (2-4 semanas). Sin esto, lo demás es construir sobre arena.**

1. A1 Matar el polling de 5 s (1 endpoint, 30-60 s, ETag) — *el cambio más rentable de la lista*
2. A3 Arreglar `?v=time()` — *medio día*
3. C1 Los 62 índices por `id_empresa` + las FK sin índice
4. A2 nginx + PHP-FPM + OPcache
5. A4 Pool en modo transaction (timezone en el rol)
6. B5 Instalar `pg_stat_statements` y registrar peticiones lentas

**Fase 2 — Red de seguridad (4-6 semanas). Sin esto, crecer multiplica los errores.**

7. B1 Pruebas de lo que no puede fallar (impuestos, XML, asientos, secuenciales, stock, aislamiento)
8. B2 Integración continua que bloquee el merge
9. B3 Control de migraciones con `schema_migrations`
10. B4 Entorno de staging
11. F1 CSRF y CSP en `enforce`

**Fase 3 — Capacidad real (6-10 semanas).**

12. A5 Redis (sesiones + caché compartida)
13. A6 Cola de trabajos para PDF/Excel/SRI/correos
14. A7 Archivos a Spaces y escalado horizontal
15. C2 Retención y particionado
16. C3 Réplica de lectura

**Fase 4 — Ventaja competitiva (continuo).**

17. D1 API pública documentada + webhooks
18. D2 Alta de clientes en autoservicio con cobro automático
19. E1 Búsqueda global · E2 Componente único de tabla
20. D3 Portal del cliente final · D5 IA con propósito · D4 Conciliación bancaria

---

## 10. Análisis de riesgo con 100 empresas ya trabajando en producción

Con clientes activos, la pregunta no es "¿esto mejora el sistema?" sino "¿qué pasa si sale mal y
cuánto tardo en volver atrás?". Cada cambio de la lista se clasifica abajo por eso.

**Corrección al orden de la sección 9:** el plan original pone la Fase 1 (rendimiento) antes de la
Fase 2 (red de seguridad). Con 100 empresas en producción eso está mal ordenado. Lo correcto es:
**primero lo que tiene riesgo cero (staging, CI, tests), después lo reversible, y al final lo
irreversible.** Ver el orden corregido al cierre de esta sección.

### 10.1 Riesgo ALTO — pueden tumbar el sistema o corromper datos

#### A4 · Pool de PostgreSQL en modo `transaction` — **NO hacerlo todavía**

Esto lo presenté como un cambio de días. Revisado el código, **no lo es**.

- **Medido: 97 llamadas a `lastInsertId()` en 73 archivos.** En PostgreSQL, `PDO::lastInsertId()` sin
  nombre de secuencia ejecuta un `SELECT lastval()` aparte, que es **por sesión**. En modo
  `transaction` el pool puede entregar una conexión distinta entre el `INSERT` y ese `lastval()`
  cuando el INSERT no va dentro de una transacción explícita. Resultado: o falla con
  *"lastval is not yet defined in this session"*, o **devuelve el id de otro usuario** y se guarda
  un detalle colgado de la cabecera equivocada.
- **No se cae: corrompe en silencio.** Es el peor tipo de fallo posible en un ERP contable.
- Además se pierden el `SET TIME ZONE 'America/Guayaquil'` y el `SET client_encoding` de
  `app/core/Database.php` → todas las fechas pasarían a UTC y los acentos podrían romperse.

**Requisito previo:** migrar los 97 `lastInsertId()` a `INSERT … RETURNING id`, que es inmune porque
es una sola sentencia. **El patrón ya es mayoritario en el proyecto (210 usos de `RETURNING`)**, así
que no hay nada que inventar. Y mover timezone/encoding al rol o al DSN.
**Alternativa válida: no hacerlo.** Dejar el pool en `Session` y resolver el límite de conexiones con
más RAM y más servidores de aplicación. Sale más caro en hardware y es infinitamente más barato en riesgo.

#### A2 · nginx + PHP-FPM — hacerlo en un servidor nuevo, nunca convertir el que está vivo

- Si una regla de rewrite queda mal traducida, **el sistema entero responde 404**. Hay dos
  `.htaccess` que traducir.
- **El header `Authorization` de la app móvil depende de configuración explícita en nginx.** Hoy
  `ApiAuthMiddleware` tiene un fallback a `apache_request_headers()`, **que en PHP-FPM no existe**
  (`app/middleware/ApiAuthMiddleware.php:69`). Si el header no llega, la app móvil deja de
  autenticar por completo.
- `client_max_body_size` en nginx es 1 MB por defecto: **las cargas de XML, Excel y logos fallarían**
  con un error que no viene de PHP y no aparece en `errores_sistema`.

**Mitigación:** montar el droplet nuevo en paralelo, probar el sistema completo contra él, y cambiar
por DNS o por snapshot. Reversible en minutos **si se probó antes**; irreversible en caliente si no.

**Alternativa de riesgo casi nulo con la mayor parte del beneficio: activar OPcache en el Apache
actual.** Es una línea de `php.ini` + `systemctl restart apache2`, reversible en 10 segundos, y da
buena parte de la ganancia de rendimiento. **Hacer esto primero, solo y medido.** Con OPcache hay
que asegurar que el despliegue recargue PHP (`systemctl reload`), o el código nuevo no entra.

#### F1 · CSP en `enforce` — posponer

Con **1.771 `onclick` inline**, una CSP estricta deja la interfaz muerta: los botones dejan de
responder en todo el sistema. Solo es viable con `'unsafe-inline'`, que equivale a casi no tener CSP.
Riesgo alto, beneficio bajo hoy. Va después de migrar los `onclick`, no antes.

#### E2 / E3 · Unificar el componente de tabla y partir las vistas de 8.000 líneas

**Sin pruebas automatizadas, esto es lo más peligroso de toda la lista**, porque toca justo lo que las
100 empresas usan cada día para facturar. No empezar hasta tener B1, y hacerlo módulo por módulo,
nunca en bloque.

### 10.2 Riesgo MEDIO — rompen un módulo, no el sistema

#### C1 · Los 62 índices

- **`CREATE INDEX` normal bloquea INSERT/UPDATE/DELETE de la tabla mientras se construye.** Un índice
  sobre `ventas_cabecera` congela la facturación de las 100 empresas el tiempo que tarde.
  **Medido: solo 26 de 750 `CREATE INDEX` del repo usan `CONCURRENTLY`.**
- **Usar siempre `CREATE INDEX CONCURRENTLY`**, uno por uno, fuera de transacción, en horario de baja
  carga. Y verificar después: `CONCURRENTLY` puede fallar y dejar el índice **inválido** en silencio
  (se detecta con `SELECT … FROM pg_index WHERE NOT indisvalid`; se arregla con `DROP` y recrear).
- **No aplicar los 62 de golpe.** Cada índice hace las escrituras algo más lentas y ocupa disco.
  Empezar por las tablas calientes (`modulos_asignados`, `usuarios_preferencias`, `plan_cuentas`,
  `productos_bodegas`, `productos_precios`), medir, y seguir.
- Riesgo de duplicar índices ya existentes con otro nombre: comprobar por primera columna antes de
  crear, no confiar en `IF NOT EXISTS`.
- Los 62 son índices **no únicos**: no pueden fallar por datos duplicados. (Un `UNIQUE` nuevo sí
  puede, y ya pasó con `uq_ingresos_secuencial_activo`.)

#### F1 · CSRF en `enforce`

- Si una vista standalone no lleva el token, **sus peticiones se rechazan con 419**.
- **Caso concreto encontrado: `app/views/videosAyuda/gestion.php` y `visor.php`** son standalone
  reales, hacen `fetch`, no son públicas y **no incluyen `partials/csrf.php`**. Se romperían.
  (Las de `factura_venta` y `recibos_venta` que parecían sospechosas son falsos positivos: su
  `<head>` está dentro de un template de JavaScript para imprimir. Esas están cubiertas por el layout.)
- **La fuente de verdad es `storage/logs/csrf.log` en producción:** cada registro ahí es un módulo que
  se rompería al cambiar el modo. Leerlo primero, arreglar lo que aparezca, y después cambiar.
- Reversible al instante: es un valor en `config/local.php`.

#### A1 · Bajar el polling a 30-60 s

- Nada se cae. Cambia la percepción: el aviso de "sesión cerrada desde otro dispositivo" y los badges
  del navbar tardarían hasta un minuto. En la práctica casi no se nota, porque ya existe un hook
  global que refresca los contadores después de cada escritura y al volver a la pestaña.
- **El riesgo real está en el `ETag`/304:** si la clave de caché no incluye `id_empresa` y
  `id_usuario`, **un usuario podría recibir los contadores de otra empresa.** Eso hay que
  implementarlo con cuidado y probarlo explícitamente.
- Reversible cambiando un número.

#### A5 · Redis para sesiones

Al migrar, **todas las sesiones activas se invalidan**: las 100 empresas van al login de golpe.
Hacerlo de noche, avisando. Y tener escrito el retorno a `session.save_handler = files`: si Redis se
cae sin respaldo configurado, **nadie puede entrar al sistema**.

#### A6 · Colas para PDF/Excel

Cambia un flujo que el usuario ya tiene aprendido ("descarga ahora" → "te avisamos"). Y si el worker
muere, los reportes dejan de generarse **sin error visible**: requiere monitoreo de la cola desde el
primer día.

### 10.3 Riesgo BAJO — reversibles en minutos

- **A3 · `?v=time()` → versión de despliegue.** Peor caso: alguien queda con CSS viejo y hace
  Ctrl+F5. Reversible al instante.
- **B5 · `pg_stat_statements`.** Extensión de solo lectura, ~1% de sobrecarga. Único costo: entra en
  `shared_preload_libraries`, así que **pide un reinicio de la base** (en la Managed DB de DO es un
  clic y un corte corto).
- **Borrar `public/supercias_produccion.sql` y `public/casilleros_dump.json`**, limpiar el `.bak` y
  las 10 ramas sin integrar. Riesgo nulo.

### 10.4 Riesgo CERO — no tocan producción

**B1 pruebas, B2 integración continua, B3 control de migraciones, B4 staging.** No modifican una sola
línea de lo que ven los clientes. Y son exactamente lo que convierte todo lo anterior en seguro:
**son el prerrequisito, no el postre.**

Lo mismo para lo aditivo: búsqueda global, API documentada, y usar el componente de tabla nuevo solo
en módulos nuevos.

### 10.5 El riesgo de no hacer nada

No tocar nada **no es la opción segura**. Estimación con los datos de hoy: 100 empresas × 2-3
usuarios × 20 % de concurrencia ≈ 40-60 pestañas abiertas → **16-24 req/s solo de polling**, sobre
una capacidad estimada de 20-50 req/s. Es decir, entre un tercio y la totalidad del servidor se está
gastando en peticiones que no producen nada. Si ya hay lentitud en horas pico, esa es la causa más
probable.

Y el crecimiento no es lineal: es un muro. Pasar de 100 a 200 empresas duplica el polling sobre un
servidor que ya está al límite.

### 10.6 Reglas de operación (valen más que cualquier cambio de la lista)

1. **Un cambio por despliegue.** Si algo se rompe, hay que poder saber qué fue.
2. **Plan de reversión escrito antes de empezar**, y probado. Si no se puede revertir en menos de
   15 minutos, no entra en horario laboral.
3. **Snapshot del droplet + respaldo de la base antes de cada cambio de infraestructura.**
4. **Ventana horaria:** nunca del 1 al 9 (declaraciones al SRI) ni a fin de mes. Martes a jueves,
   temprano, es lo más tranquilo.
5. **Avisar a los clientes** con el módulo de Novedades del sistema.
6. **Vigilar `errores_sistema` y los logs las 2 horas siguientes** a cada cambio. Si no se mira, no
   cuenta como desplegado.
7. **Staging primero, siempre** — por eso B4 sube al principio del plan.

### 10.7 Orden corregido para un sistema con clientes activos

**Etapa 0 — riesgo cero (empezar aquí).** B4 staging · B2 integración continua · B1 pruebas de
impuestos, XML, asientos, secuenciales, stock y aislamiento multiempresa · B3 `schema_migrations` ·
B5 `pg_stat_statements` · limpieza de archivos del docroot.

**Etapa 1 — reversible en minutos.** A3 `?v=time()` · OPcache en el Apache actual · A1 polling
unificado a 30-60 s con `ETag` (con la clave por empresa y usuario probada) · C1 índices
`CONCURRENTLY`, los 5 más calientes primero y midiendo · leer `csrf.log` y arreglar `videosAyuda`.

**Etapa 2 — con staging ya funcionando.** F1 CSRF en `enforce` · A5 Redis (caché primero, sesiones
después y de noche) · A6 colas con monitoreo.

**Etapa 3 — solo con pruebas y staging consolidados.** Migrar los 97 `lastInsertId()` a `RETURNING` →
y solo entonces evaluar A4 pool en `transaction` · A2 nginx + PHP-FPM en servidor nuevo en paralelo ·
A7 escalado horizontal.

**Etapa 4 — nunca sin pruebas.** E2 componente único de tabla · E3 partir las vistas monolíticas ·
F1 CSP en `enforce` tras migrar los `onclick`.

---

## Anexo — Datos medidos

| Métrica | Valor |
|---------|-------|
| Archivos versionados | 2.404 |
| Líneas propias (PHP/JS/CSS/SQL/MD, sin vendor) | ~700.000 |
| Módulos operativos registrados | 113 |
| Controladores / Services / Repositorios / Rules | 216 / 286 / 169 / 97 |
| Vistas | 333 archivos, 143.267 líneas |
| JavaScript | 103 archivos (68.836 líneas) + ~68.000 líneas embebidas en vistas |
| Tablas en la base local | 350 (240 con `id_empresa`) |
| Tablas con `id_empresa` sin índice que lo lidere | **62** |
| Tablas sin índices más allá de la PK | **57** |
| Claves foráneas sin índice | 40+ |
| Índices declarados en el repo | 760 en 449 archivos .sql |
| Tabla de control de migraciones | **no existe** |
| Pruebas automatizadas | **0** |
| Integración continua | **no existe** |
| Sondeos globales cada 5 s | **2** (sesión + contadores) |
| `?v=time()` (rompe caché del navegador) | 290 en 110 archivos |
| `onclick` inline | 1.771 |
| Controladores que generan PDF / Excel de forma síncrona | 69 / 48 |
| Archivos de más de 1.500 líneas | 28 (el mayor: 8.101) |
| Artículos del manual de usuario | 115 (cobertura completa) |
| Commits totales / últimos 90 días | 1.064 / 818 |
| `pg_stat_statements` | no instalado |
| Infraestructura de producción (jul-2026) | 1 vCPU, 1,9 GB, Apache + mod_php |
