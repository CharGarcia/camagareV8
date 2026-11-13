# Cotizador de Planes — Diseño

> Documento de diseño para revisión. **No es el manual del usuario** (irá en
> `docs/manual/modulos/cotizador-planes.md` cuando exista) y no se publica en
> `/documentacion`.
> Versión 1 — 13-11-2026. Estado: **borrador para aprobación**. Las decisiones
> marcadas ❓ esperan confirmación.

## 1. Objetivo

Que el vendedor arme, frente al cliente, el precio del sistema según lo que esa
empresa realmente necesita, en lugar de usar una lista de precios fija. El
vendedor:

1. escribe el RUC y el sistema trae los datos del SRI;
2. responde unas pocas preguntas (tipo de contribuyente, establecimientos,
   usuarios, giro);
3. elige el plan y los módulos adicionales de cada establecimiento y los
   servicios que se le van a dar;
4. obtiene el precio **anual y mensual**, lo envía al cliente en PDF, por correo
   o por WhatsApp,
5. y hace el seguimiento en el **CRM** hasta cerrar la venta.

## 2. Piezas que ya existen y se reutilizan

| Pieza existente | Qué aporta al cotizador |
|---|---|
| `SriIdentificationService::consultar()` (ya usado en Empresas del sistema) | Con el RUC trae razón social, nombre comercial, dirección, **tipo de contribuyente** (persona natural, sociedad, unipersonal) y la **lista de establecimientos** del SRI con su estado (ABIERTO o CERRADO). El cotizador puede **contar solo los establecimientos abiertos**. Si el RUC ya es cliente de la empresa, avisa para no duplicarlo. |
| **CRM** (contactos, oportunidades, embudo, actividades) | El seguimiento. La oportunidad tiene valor estimado, cierre estimado, responsable, vendedor y origen. Hoy **una oportunidad admite una proforma**, y su valor sigue el total de esa proforma. |
| **Proformas** | PDF, correo con **botón para que el cliente apruebe**, WhatsApp, condiciones, versiones, conversión a factura y uso desde la **app móvil**. |
| **Suscripciones** | El cobro recurrente de cada establecimiento una vez cerrada la venta. |
| **Combos de submódulos** | Los permisos que se aplican cuando el cliente ya está dado de alta. |
| `GuardadoUnicoService`, `LogSistemaService`, `BaseModuloController`, pestaña Información | Guardado único, auditoría, permisos y registros propios (CLAUDE.md §6–§8). |

**Propuesta central:** el cotizador **no inventa un documento de venta
nuevo**. Calcula el precio y, al terminar, **genera una Proforma enlazada a la
oportunidad del CRM**. Así el PDF, el correo con aprobación del cliente, el
WhatsApp, la app móvil y el seguimiento en el CRM funcionan desde el primer
día, sin construirlos de nuevo.

❓ **D1.** ¿Se aprueba que la salida del cotizador sea una Proforma enlazada al
CRM (recomendado), o se quiere un documento de cotización propio?

## 3. Reglas de precio (ya definidas en la presentación de vendedores, v0.10)

| Regla | Valor |
|---|---|
| Unidad de cobro | Por establecimiento |
| Usuarios incluidos | 3 por establecimiento, sumados y compartidos a nivel de RUC |
| Usuario adicional | $20 al año |
| Precio mensual | Anual ÷ 12 × 1,20 (20 % de recargo) |
| Establecimiento principal | El de plan base más caro; paga completo |
| Establecimiento adicional | 20 % de descuento en el plan base, **solo en plan anual** |
| Módulos adicionales | Aparte en cada establecimiento, sin descuento |

Estos valores (20 %, 20 %, $20, 3 usuarios) **no se escriben en el código**:
van en una configuración editable para no programar de nuevo si cambian.

## 4. Lo que se configura (catálogo de precios)

Lo administran los niveles 2 y 3. El vendedor solo lo usa.

1. **Parámetros generales**: % de recargo mensual, % de descuento por
   establecimiento adicional, usuarios incluidos por establecimiento y precio
   del usuario adicional.
2. **Planes base**: nombre, descripción, **precio anual** y módulos principales
   que incluye (tabla 6.2 de la presentación).
3. **Módulos adicionales**: nombre, precio anual y de qué depende (por ejemplo,
   Restaurante requiere POS; Producción requiere Inventario). El cotizador
   **no deja elegir** un adicional sin su requisito.
4. **Factores de ajuste** — la parte variable que reemplaza a la lista fija.
   Cada factor suma un valor o multiplica el plan base. Ejemplos a definir:
   - tipo de contribuyente: persona natural no obligada a llevar contabilidad,
     persona natural obligada, sociedad;
   - volumen de comprobantes al mes (rangos);
   - giro o industria.
5. **Servicios de una sola vez** (no recurrentes): implementación, migración
   de datos, horas de capacitación, firma electrónica, etc.
6. Cada plan, módulo o servicio se enlaza a un **producto/servicio del
   catálogo** de la empresa, para que la Proforma, la factura y la suscripción
   salgan con los productos correctos y su IVA.

❓ **D2.** ¿Qué factores cambian el precio y cómo? Por ejemplo, ¿una sociedad
paga un % más que una persona natural? ¿Hay rangos por número de comprobantes
al mes?

❓ **D3.** ¿Qué servicios de una sola vez se cobran (implementación, migración,
capacitación…)? ¿Tienen precio fijo o por horas?

## 5. Flujo del vendedor (asistente paso a paso)

1. **RUC o cédula** → consulta al SRI. Se muestran la razón social, el tipo de
   contribuyente y los establecimientos abiertos. Si ya existe como cliente o
   como contacto del CRM, se avisa y se enlaza.
2. **Perfil**: se confirman o corrigen el tipo de contribuyente, si está
   obligado a llevar contabilidad, el giro y el volumen aproximado de
   comprobantes.
3. **Establecimientos**: lista precargada desde el SRI. El vendedor marca
   cuáles se contratan y, para cada uno, elige el **plan base** y los
   **módulos adicionales**.
4. **Usuarios**: total de usuarios del RUC. El cotizador resta los cupos
   incluidos y calcula los adicionales.
5. **Servicios** de una sola vez.
6. **Resumen**: **anual y mensual lado a lado**, con el ahorro del pago anual
   y el detalle por establecimiento. El principal se marca solo, por ser el de
   plan más caro.
7. **Generar proforma** → queda enlazada a la oportunidad del CRM. Si no hay
   oportunidad, se crea con el contacto, el responsable (el vendedor) y el
   valor estimado.

La cotización se guarda en borrador en cualquier paso y se retoma después.
Se puede **duplicar** para ofrecer otra alternativa al mismo cliente.

❓ **D4.** ¿El vendedor puede dar un **descuento extra** por negociación? Si
puede, ¿hasta qué % sin autorización? Sobre ese límite, se pediría
aprobación con el módulo **Aprobaciones** que ya existe.

❓ **D5.** ¿En la proforma se ofrece una sola modalidad (anual o mensual) o las
dos para que el cliente elija?

## 6. Integración con el CRM (seguimiento)

- Cada cotización pertenece a una **oportunidad**. El valor estimado de la
  oportunidad es el total anual de la cotización (o el de la modalidad
  elegida).
- Las **actividades** del CRM (llamar, reunión, enviar correo) quedan en la
  misma oportunidad, con su fecha y su aviso de vencimiento.
- Cuando el cliente **aprueba la proforma** (botón del correo o el vendedor),
  la oportunidad se puede marcar **Ganada**.
- Reportes del embudo por vendedor: cotizaciones enviadas, ganadas, perdidas
  y valor.

## 7. Permisos y seguridad

- Módulo operativo con `id_empresa`: cada empresa que vende el sistema
  (incluidos los revendedores) tiene **su propio catálogo de precios**.
- Vendedor (nivel 1 sin acceso total) → ve **solo sus cotizaciones**
  (§6, registros propios). Supervisor con acceso total → todas.
- El catálogo de precios lo editan solo los niveles 2 y 3.
- Toda cotización guarda una **foto de los precios** usados, para que un
  cambio posterior del catálogo no altere lo ya enviado.
- Auditoría, eliminación lógica, guardado único y transacciones (§5, §7, §8).

## 8. Fases propuestas

| Fase | Alcance |
|---|---|
| **1** | Catálogo de precios + asistente de 7 pasos + consulta SRI + cálculo anual/mensual + generación de Proforma enlazada a oportunidad del CRM + manual del módulo |
| **2** | Al ganar: crear las **suscripciones** de cada establecimiento y dejar lista el alta de las empresas con su combo de módulos |
| **3** | Cotizador en la **app móvil** del vendedor, reportes de conversión y control automático de cupos de usuarios por RUC |

⚠️ La app móvil en iOS **no puede mostrar precios para comprar CaMaGaRe ni
enlaces de pago de CaMaGaRe** (CLAUDE.md §13). La fase 3 debe revisarse con
cuidado antes de implementarla: el cotizador es una herramienta interna del
vendedor, no una compra del usuario de la app, pero hay que confirmarlo
antes de exponerlo en iOS.

## 9. Decisiones pendientes (resumen)

| # | Pregunta |
|---|---|
| D1 | ¿La salida es una Proforma enlazada al CRM (recomendado)? |
| D2 | ¿Qué factores de ajuste cambian el precio y cuánto? |
| D3 | ¿Qué servicios de una sola vez se cobran y cómo? |
| D4 | ¿Descuento extra del vendedor? ¿Límite y aprobación? |
| D5 | ¿La proforma ofrece anual, mensual o las dos? |
| D6 | ¿Lo usan solo los vendedores propios o también los revendedores, cada uno con su catálogo? |
