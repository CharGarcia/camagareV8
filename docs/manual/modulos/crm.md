---
titulo: CRM
resumen: Embudo de ventas: contactos, oportunidades por etapas, actividades de seguimiento y paso a cliente y proforma.
categoria: Ventas
ruta_modulo: modulos/crm
tipo: modulo
visibilidad: todos
etiquetas: crm, reportes crm, tasa de cierre, conversion, ticket promedio, ventas por origen, ventas por vendedor, motivos de perdida, tiempo por etapa, embudo, embudo de ventas, pipeline, funnel, kanban, tablero, oportunidad, oportunidades, negocio, negocios, prospecto, prospectos, lead, leads, contacto, contactos, seguimiento, seguimiento de clientes, actividades, tareas, llamadas, reuniones, recordatorio, etapas, ganar, ganada, perder, perdida, motivo de perdida, conversion, convertir en cliente, crear proforma, valor ponderado, probabilidad, ventas, vendedor, responsable, instagram, linkedin, redes sociales, unir contactos, contactos duplicados, duplicado, fusionar, combinar contactos, asistente virtual, bot de instagram, publicaciones, reporte de redes, alcance, seguidores, atribucion
version: 1.7
orden: 14
estado: activo
---

El **CRM** sirve para seguir a cada persona interesada desde el primer contacto
hasta que compra. Cada interesado es un **contacto**; cada posible venta es una
**oportunidad** que avanza por las **etapas** de un **embudo** (Nuevo → Contactado
→ Calificado → Propuesta → Negociación) hasta quedar **ganada** o **perdida**. Al
ganarla, el contacto queda enlazado a un **cliente** del sistema y la oportunidad
puede tener su **proforma**.

## Qué es y para qué sirve

- Ver de un vistazo cuántas oportunidades hay en cada etapa y cuánto dinero
  representan (**Embudo**, en forma de tablero).
- No olvidar a nadie: cada oportunidad lleva sus **actividades** (llamar,
  reunirse, enviar un correo) con fecha, y el sistema marca las vencidas.
- Medir: valor del embudo, valor ponderado por probabilidad, ganadas y perdidas
  del mes.
- Pasar sin reescribir nada a **Clientes** y **Proformas**.

Con la cuenta de Instagram conectada (Configuración del CRM), cada persona que
escribe un mensaje directo o comenta una publicación queda registrada sola como
**contacto**, con su nombre y usuario de Instagram y el origen *Instagram
(mensaje)* o *Instagram (comentario)*, y asignada según el reparto elegido para
la cuenta. Las conversaciones se leen y responden en la **Bandeja social**, desde
donde también se crea la oportunidad con un clic. Los contactos de LinkedIn o de cualquier otro medio se
registran a mano.

Si la empresa activó el **asistente virtual** de Instagram, este puede crear la
oportunidad solo cuando el cliente quiere comprar: queda en la primera etapa del
embudo predeterminado, a cargo del responsable de la conversación, con la
descripción «Registrada por el asistente virtual desde Instagram». Ver la guía
**Asistente virtual de Instagram**.

## Requisitos previos

- La primera vez que alguien abre el CRM en una empresa, el sistema crea el
  embudo **Ventas** con sus etapas, los **orígenes** (Instagram, LinkedIn,
  WhatsApp, Referido, Sitio web, Otro) y los **motivos de pérdida**. Se ajustan
  en **Configuración del CRM**, donde también se conecta la cuenta de Instagram.
- Para **Crear proforma** hace falta permiso de crear en **Proformas** y una
  serie con secuencial de proformas.
- Para registrar un cliente desde el CRM hace falta permiso de crear en
  **Clientes**.

## Cómo se usa

1. **Nuevo contacto**: nombre y al menos un medio de contacto (teléfono, correo,
   Instagram o LinkedIn). Si ya existe otro contacto con el mismo correo o
   teléfono, el sistema avisa (no impide guardar).
2. **Nueva oportunidad**: título, contacto, embudo, etapa, valor estimado y
   fecha de cierre estimada. Si el contacto no existe, el botón junto al
   buscador lo crea y lo deja puesto en la oportunidad.
3. En **Embudo**, arrastre la tarjeta a la etapa en la que está la venta. El
   orden dentro de cada columna también se guarda.
4. Abra la oportunidad y, en **Actividades**, agende lo siguiente que hay que
   hacer. Las vencidas se marcan con un punto rojo en la tarjeta y suman en
   **Mis actividades**.
5. **Crear proforma** (barra superior de la oportunidad): pide el cliente si el
   contacto aún no tiene uno y abre **Proformas** con una proforma nueva para
   ese cliente. Al guardarla, queda enlazada.
6. **Ganar**: pide el cliente que compró (si es nuevo, regístrelo con el botón
   de al lado). **Perder**: pide el motivo y, si quiere, un detalle.
7. **Reabrir** devuelve una oportunidad ganada o perdida a la última etapa
   abierta del embudo.

## Vistas

- **Embudo**: una columna por etapa abierta, con cantidad, suma de valores y
  probabilidad. Cada tarjeta muestra contacto, valor, días en la etapa, origen y
  responsable. Se muestran hasta 200 tarjetas por columna; el resto, en Lista.
- **Lista**: todas las oportunidades (abiertas, ganadas o perdidas según el
  filtro Estado) con orden por una o varias columnas (Shift + clic), columnas
  que cada usuario puede ocultar, filas por página y exportación a **PDF** y
  **Excel** con los filtros y el orden de la pantalla.
- **Contactos**: todos los contactos, con su cliente enlazado y cuántas
  oportunidades abiertas tienen.
- **Mis actividades**: lo pendiente asignado a usted, por fecha.
- **Reportes**: ver la sección siguiente.

## Unir contactos duplicados

La misma persona puede quedar registrada dos veces: Instagram le da un
identificador distinto cuando escribe por **mensaje directo** y cuando **comenta**
una publicación, o alguien la registró a mano antes de que escribiera.

1. Abra el contacto que quiere **conservar** (vista Contactos).
2. Pulse **Unir duplicado** (pie del modal) y busque el otro contacto.
3. Pulse **Unir** y confirme.

El contacto elegido le pasa a este sus **oportunidades**, **conversaciones** y
**actividades**; los datos que aquí estén vacíos (correo, teléfono, empresa,
cliente…) se completan con los del otro, y las notas de los dos se juntan. Lo que
este ya tenía no se cambia. Luego el otro contacto se elimina (eliminación lógica).

Si los dos tenían Instagram, el del otro queda **unido** a este: los mensajes y
comentarios que lleguen por cualquiera de las dos cuentas entran a este contacto
y no se vuelve a crear el duplicado. La ficha lo indica con «Unido con N cuenta(s)
de Instagram más».

No se pueden unir dos contactos enlazados a **clientes distintos** (no parecen
la misma persona). La unión no se deshace desde la pantalla; queda en el
registro de auditoría.

## Reportes del embudo

En la vista **Reportes** se elige el período (**Desde / Hasta**; si no se indica,
los últimos 6 meses) y se respetan el embudo, el responsable y el origen de la
tarjeta superior. Las oportunidades **creadas** cuentan si entraron en el período;
las **ganadas** y **perdidas**, si se cerraron en el período.

- **Indicadores**: creadas, ganadas y su valor, perdidas y su valor, **tasa de
  cierre** (ganadas ÷ ganadas + perdidas), **ticket promedio** (valor ganado ÷
  ganadas) y **días hasta ganar** (promedio desde que se creó).
- **Embudo hoy, por etapa**: cantidad, valor y valor ponderado de lo abierto.
- **Días promedio en cada etapa**: cuánto tarda una oportunidad en salir de cada
  etapa (útil para ver dónde se estanca la venta).
- **Por mes**: creadas, ganadas, valor ganado y perdidas de cada mes.
- **Por origen** y **por responsable**: qué canal y qué persona traen y cierran
  más, con su tasa de cierre y lo que tienen abierto.
- **Motivos de pérdida**: por qué se pierde, con su porcentaje.
- **Redes sociales** (si se usa **Publicaciones**): por red, cuántas
  publicaciones salieron en el período, su alcance e interacciones (última
  estadística tomada) y lo que trajeron al CRM por los comentarios de Instagram:
  conversaciones, contactos nuevos, oportunidades y valor ganado. Además, las 10
  publicaciones que más trajeron y los seguidores de Instagram al inicio y al
  final del período.

El período máximo es de 2 años. Sin acceso total, los reportes incluyen solo las
oportunidades creadas por usted o asignadas a usted.

## Avisos en la barra superior

- **CRM** (ícono de tablero): actividades del CRM a su cargo que ya vencieron.
- **Instagram**: conversaciones de la Bandeja social con mensajes sin leer que
  le tocan (asignadas a usted o sin asignar).

Cada aviso aparece solo si tiene permiso de ver su módulo y se actualiza solo.

## Buscar y filtrar

La tarjeta superior filtra por embudo, estado, responsable, origen y fecha de
cierre estimada. El buscador acepta texto libre (título, contacto, teléfono,
@instagram, número) y filtros `clave:valor`:

| Clave | Ejemplo |
|-------|---------|
| `etapa`, `embudo`, `origen`, `responsable`, `vendedor`, `contacto`, `titulo` | `etapa:propuesta` |
| `estado` | `estado:ganada` |
| `numero` | `numero:15` |
| `valor`, `probabilidad` | `valor:>=1000`, `valor:500..2000` |
| `cierre`, `creado`, `cerrada` | `cierre:2026-12`, `creado:>=2026-11-01` |

## Campos de la oportunidad

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Oportunidad | Sí | Nombre corto de la posible venta. |
| Contacto | Sí | La persona interesada. |
| Embudo / Etapa | Sí | Dónde está la venta. Al cambiar de embudo pasa a la etapa que elija del nuevo. |
| Valor estimado | No | Cuánto se espera vender. Con proforma enlazada lo fija la proforma. |
| Cierre estimado | No | Cuándo se espera cerrar. |
| Responsable | No | Usuario a cargo. Si se deja vacío, queda quien la crea. |
| Vendedor | No | Vendedor del sistema (para reportes de ventas). |
| Origen | No | Por dónde llegó: Instagram, LinkedIn, referido… |

## Permisos

- **Ver**: entrar al CRM y consultar.
- **Crear**: contactos, oportunidades y actividades.
- **Modificar**: editar, mover de etapa, ganar, perder, reabrir, completar
  actividades y crear la proforma.
- **Eliminar**: contactos, oportunidades y actividades (eliminación lógica).
- **Unir duplicados**: requiere **Modificar** y **Eliminar**, y ver los dos contactos.
- **Acceso total**: ve todo lo de la empresa. **Sin acceso total**, el usuario ve
  lo que **creó** y lo que tiene **asignado como responsable** (así un vendedor
  ve los prospectos que le asignan aunque no los haya registrado).

## Reglas de negocio

- Cada embudo tiene siempre una etapa **Ganado** y una **Perdido**; no se crean
  ni se eliminan a mano y no se llega a ellas arrastrando: se usa Ganar o Perder.
- Una oportunidad ganada o perdida no se edita; hay que reabrirla.
- **Ganar** exige un cliente; el contacto queda enlazado a ese cliente.
- **Perder** exige un motivo.
- Cada oportunidad tiene un número correlativo por empresa.
- Una oportunidad admite **una** proforma; con proforma, el valor es el total de
  la proforma y la sigue cuando esta se edita.
- No se elimina un contacto que tiene oportunidades.
- Un doble clic o un reintento al guardar no crea dos contactos ni dos
  oportunidades.

## Integraciones con otros módulos

- **Clientes**: al ganar o al crear la proforma, el contacto queda enlazado al
  cliente; se puede registrar un cliente nuevo sin salir del CRM.
- **Proformas**: «Crear proforma» abre una proforma nueva con el cliente puesto;
  al guardarla, queda enlazada a la oportunidad.
- **Vendedores**: la oportunidad puede llevar el vendedor del sistema.
- **Publicaciones**: las oportunidades que salen de los comentarios de una
  publicación de Instagram se le atribuyen (se ven en sus Resultados y en Reportes).
- Toda acción queda en el registro de auditoría del sistema.

## Errores frecuentes

- **«El CRM todavía no está instalado en la base de datos»**: falta aplicar el
  SQL del módulo; avise al administrador del sistema.
- **«Para ganar o perder una oportunidad use las acciones Ganar / Perder»**: se
  intentó arrastrar o mover la tarjeta a Ganado/Perdido.
- **«La oportunidad está cerrada. Reábrala para modificarla»**: está ganada o
  perdida.
- **«La oportunidad ya tiene una proforma enlazada»**: abra la proforma
  existente desde Proformas.
- **No veo una oportunidad que sí existe**: sin acceso total solo se ven las que
  usted creó o tiene asignadas.

## Historial de cambios

- **1.7** — Reportes: bloque **Redes sociales** (publicaciones del período, alcance,
  interacciones y contactos, oportunidades y ventas que trajeron).
- **1.6** — El **asistente virtual** de Instagram puede crear oportunidades (una por
  conversación) y guardar en el contacto los datos que el cliente da en el chat.
- **1.5** — **Unir contactos duplicados**: pasa oportunidades, conversaciones y
  actividades al contacto que queda y conserva el Instagram del otro, para que
  no se vuelva a duplicar.
- **1.4** — Vista **Reportes** (conversión, embudo por etapa, días por etapa, por mes,
  por origen, por responsable y motivos de pérdida) y avisos en la barra superior
  (actividades vencidas y conversaciones de Instagram sin leer).
- **1.3** — Las conversaciones de Instagram se atienden en la **Bandeja social**;
  desde ahí se crea la oportunidad, enlazada a la conversación.
- **1.2** — Quien escribe o comenta en la cuenta de Instagram conectada se registra
  solo como contacto, con su usuario de Instagram y el responsable que indique el
  reparto de la cuenta.
- **1.1** — Embudos, etapas, orígenes y motivos de pérdida se editan en la nueva
  pantalla **Configuración del CRM**, que además conecta la cuenta de Instagram.
- **1.0** — Versión inicial: embudos y etapas, contactos, oportunidades
  (Embudo, Lista con PDF/Excel), actividades, ganar/perder/reabrir y enlace con
  Clientes y Proformas.
