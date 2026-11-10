---
titulo: Configuración del CRM
resumen: Conectar Instagram y LinkedIn, configurar el asistente virtual y ajustar embudos, etapas, orígenes y motivos de pérdida del CRM.
categoria: Ventas
ruta_modulo: modulos/crm_configuracion
tipo: modulo
visibilidad: todos
etiquetas: app de meta, app id, app secret, id de configuracion, config_id, unified login, inicio de sesion unificado, portafolio comercial, token de verificacion, configuracion crm, ajustes crm, conectar instagram, vincular instagram, cuenta de instagram, redes sociales, instagram empresa, desconectar instagram, reconectar, acceso vencido, token, embudo, embudos, etapas, etapas del embudo, pipeline, probabilidad, orden de etapas, origenes, origen del contacto, motivos de perdida, asignar conversaciones, repartir mensajes, turnos, human agent, siete dias, asistente virtual, bot, chatbot, respuesta automatica, probar asistente, consumo ia, linkedin, conectar linkedin, app de linkedin, client id, pagina de empresa, publicar
version: 1.4
orden: 15
estado: activo
---

Aquí se prepara el **CRM** de la empresa: qué cuenta de **Instagram** está
conectada, cómo se reparten las conversaciones que lleguen y cómo son los
**embudos** con sus **etapas**, los **orígenes** de los contactos y los
**motivos de pérdida**.

## Qué es y para qué sirve

- **Canales**: conectar la cuenta de Instagram de la empresa (sus mensajes
  directos y comentarios llegan a la **Bandeja social** y se puede publicar desde
  **Publicaciones**) y las cuentas de **LinkedIn** (perfil o página, solo para
  publicar).
- **Asistente virtual**: activar y ajustar el asistente con IA que responde solo
  los mensajes directos de Instagram (ver abajo y la guía **Asistente virtual de
  Instagram**).
- **Embudos y etapas**: los pasos por los que avanza una venta en el tablero del
  CRM, con su probabilidad de cierre y su color.
- **Orígenes** y **Motivos de pérdida**: las listas que se eligen en contactos y
  oportunidades.

La primera vez que se abre el CRM o esta pantalla, el sistema crea el embudo
**Ventas** con sus etapas, los orígenes y los motivos más comunes; desde aquí se
ajustan.

## Requisitos previos

Para conectar Instagram:

- La cuenta de Instagram debe ser **profesional** (Empresa o Creador). Una cuenta
  personal se cambia desde la app de Instagram: *Configuración → Tipo de cuenta*.
- Los mensajes y comentarios de la cuenta conectada se atienden en la
  **Bandeja social**.
- La empresa debe registrar **su propia app de Meta** (ver abajo). Mientras no lo
  haga, no aparece el botón **Conectar Instagram**.

## Cómo se usa

La pestaña **Canales** tiene dos secciones plegables: **Instagram (Meta)** y
**LinkedIn**. Cada una reúne la app de la empresa y sus cuentas conectadas, y su
encabezado resume cuántas hay conectadas (y si alguna tiene el acceso vencido)
aunque esté cerrada. Las secciones empiezan **cerradas**; pulse su encabezado para
abrirla. El sistema recuerda en ese navegador cuáles dejó abiertas.

### Registrar la app de Meta de la empresa

La tarjeta trae el botón **Paso a paso para configurar la app en Meta**. Abre una
ventana con **una tarjeta por paso** (crear la app, configuración de inicio de
sesión, URL de redirección e ID de configuración, webhooks, eliminación de datos,
cuenta para probar y conectar). Cada tarjeta muestra la **ruta de menús de Meta**
y los campos **con el mismo nombre que tienen allá**, con el valor del sistema y
su botón para copiar. Se avanza con **Siguiente** / **Anterior** o con los números
de arriba; el último paso tiene el botón **Conectar Instagram**.

Cada empresa usa su propia app de Meta. En **Canales → App de Meta de la
empresa**:

1. Escriba el **App ID** y el **App Secret** de la app (*Configuración de la app →
   Básica*, en developers.facebook.com) y el **ID de configuración** de inicio de
   sesión (está en la URL de inicio de sesión que da Meta, después de `config_id=`).
   Pulse **Guardar**.
2. Copie las direcciones y el **token de verificación** que aparecen y péguelos en
   la app de Meta. El paso a paso está en la guía **App de Meta para Instagram (CRM)**.

Las apps de Meta creadas desde noviembre de 2026 usan **Unified login** y necesitan
el ID de configuración. Una app anterior con «Instagram login» usa el *Instagram*
App ID y Secret y deja vacío ese campo; una etiqueta junto a los campos indica cuál
de las dos formas usa la empresa. Poner o quitar el ID de configuración solo se
permite sin cuentas conectadas.

El App Secret se guarda cifrado y no vuelve a mostrarse: para cambiarlo, escriba
uno nuevo; si deja el campo vacío, se conserva el guardado. El botón de flechas
junto al token genera otro (hay que pegarlo de nuevo en Meta). La papelera quita la
app, solo si no hay cuentas conectadas con ella.

### Conectar la cuenta de Instagram

1. Pestaña **Canales** → **Conectar Instagram** (aparece cuando la app está registrada).
   Un aviso explica lo que va a pasar; pulse **Abrir y conectar**.
2. Meta se abre en una **ventana aparte** (el sistema queda abierto detrás): inicie
   sesión (con Instagram o con Facebook), elija el **portafolio comercial** y la
   **cuenta de Instagram**, y acepte.
3. Al terminar, **esa ventana se cierra sola** y el sistema muestra *«Cuenta @…
   conectada»*; la cuenta aparece con estado **Conectada**. Si se compartieron varias
   cuentas, aparece una tarjeta por cada una. Si el navegador bloquea las ventanas
   emergentes, Meta se abre en la misma pestaña y, al terminar, vuelve sola al sistema.
   Si cierra la ventana de Meta antes de terminar, no se conecta nada.

Con **Unified login** el acceso es del portafolio y **no vence** («acceso del
portafolio (no vence)»). Deja de servir solo si alguien quita la app del portafolio
o la cuenta pasa a otro portafolio: entonces la cuenta queda **Acceso vencido** y
pide **Reconectar**. Con una app anterior («Instagram login») el acceso dura unos 60
días y **el sistema lo renueva solo**.

Los mensajes que llegaron **antes de conectar** la cuenta no se pueden responder
desde el sistema (la ventana de 24 h de Instagram cuenta desde la conexión).

Si a la configuración de la app en Meta le falta un permiso (por ejemplo
`instagram_manage_insights`, el de las métricas), la tarjeta lo avisa: agréguelo en
Meta y pulse **Reconectar**.

**Permisos para publicar**: si la cuenta se conectó antes de existir
**Publicaciones**, la tarjeta pide **Reconectar** para aceptar los permisos de
publicar y de estadísticas. Los mensajes siguen funcionando mientras tanto.

### Conectar LinkedIn

En **Canales → App de LinkedIn de la empresa**:

1. Pegue el **Client ID** y el **Client Secret** de la app de LinkedIn y pulse
   **Guardar** (el secreto se guarda cifrado; vacío al editar lo conserva).
2. Copie la **Dirección de retorno** en la app de LinkedIn (*Auth → Authorized
   redirect URLs*). El paso a paso está en la guía **App de LinkedIn para publicar (CRM)**.
3. **Conectar mi perfil** publica en su perfil personal. Si LinkedIn aprobó la
   *Community Management API* en la app, marque la casilla y use **Conectar perfil
   y páginas** para publicar también en las páginas de empresa que administra.
   Igual que con Instagram, LinkedIn se abre en una **ventana aparte** que se cierra
   sola al terminar.

Las cuentas de LinkedIn sirven solo para **publicar** (no reciben mensajes ni se
reparten). Su acceso dura unos 60 días y LinkedIn no permite renovarlo solo: la
tarjeta avisa 10 días antes para **Reconectar**.

### Repartir las conversaciones nuevas

En la tarjeta de la cuenta, **Conversaciones nuevas**:

- **Sin asignar**: llegan a «Sin asignar» y cualquiera del equipo las toma.
- **Repartir por turnos**: se asignan una a una entre los usuarios marcados.
- **Siempre a un usuario**: todas a la misma persona.

**Responder hasta 7 días (Human Agent)**: Instagram solo deja responder dentro de
las 24 horas siguientes al último mensaje del cliente. Si la app de Meta de la
empresa tiene aprobada la función *Human Agent*, una persona (nunca un bot) puede
responder hasta 7 días después. Actívelo solo cuando Meta haya aprobado esa
función en su app.

### Asistente virtual

En la pestaña **Asistente virtual** se elige la cuenta de Instagram y se configura
el asistente que responde solo sus mensajes directos: si está activo, su nombre,
tono y presentación, lo que debe saber, los temas de los que no habla, cuándo
responde (siempre o solo fuera del horario de atención), si dice la
disponibilidad de los productos, si crea oportunidades y sus topes de respuestas.

- Para activarlo, la IA de la empresa debe ser **Anthropic (Claude)**, configurada
  en **IA Soporte → Configuración**.
- **Probar** abre una conversación simulada con la configuración guardada: no
  envía nada a Instagram ni guarda nada en el CRM (sí consume la clave de IA;
  máximo 30 pruebas por hora).
- A la derecha se ve el consumo del mes y su costo estimado.

El detalle de cómo responde y cuándo pasa a una persona está en la guía
**Asistente virtual de Instagram**.

### Embudos y etapas

- **+** crea un embudo nuevo (nace con Nuevo, Calificado, Propuesta, Ganado y
  Perdido).
- Cambie nombre, estado o **Predeterminado** y pulse **Guardar**.
- Las etapas intermedias se **arrastran** para ordenarlas; cada una tiene nombre,
  color y probabilidad (%). **Ganado** (100 %) y **Perdido** (0 %) siempre
  existen y no se eliminan.

### Orígenes y motivos de pérdida

Escriba en **Agregar…** y pulse **+**. Puede renombrar, desactivar (deja de
ofrecerse) o eliminar; las oportunidades que ya lo usan lo conservan.

## Permisos

- **Ver**: consultar la configuración (incluidas las direcciones y el token para
  Meta y el consumo del asistente) y **Probar** la conexión.
- **Crear**: registrar la app de Meta y la de LinkedIn, conectar Instagram y
  LinkedIn, crear embudos, etapas, orígenes y motivos.
- **Modificar**: editar las apps de Meta y LinkedIn y regenerar el token de Meta,
  editar lo anterior, ordenar etapas, los ajustes de la cuenta (reparto, 7 días) y
  configurar y **Probar** el asistente virtual.
- **Eliminar**: quitar las apps de Meta o LinkedIn, desconectar cuentas y eliminar
  embudos, etapas, orígenes y motivos.

Todo es **por empresa**: cada empresa ve y cambia solo su app, sus cuentas y sus
catálogos.

## Reglas de negocio

- Una cuenta de Instagram solo puede estar conectada a **una empresa** del
  sistema a la vez. Para moverla, primero se desconecta en la empresa donde está.
- **Desconectar** borra el acceso: dejan de llegar y de enviarse mensajes por esa
  cuenta, pero el historial se conserva. Si se vuelve a conectar la misma cuenta
  en la misma empresa, las conversaciones siguen donde quedaron.
- El acceso a Instagram y el App Secret se guardan cifrados y nunca se muestran
  en pantalla.
- Los mensajes que llegan de Meta se aceptan solo si vienen firmados por la app de
  la empresa dueña de la cuenta: la app de otra empresa no puede meter mensajes
  ni borrar datos en la suya.
- Si el dueño de la cuenta quita la app desde Instagram, la cuenta aparece aquí como
  **Desconectada**. Si pide a Meta borrar sus datos, además se vacían sus
  conversaciones y la cuenta queda sin @usuario.
- No se elimina un embudo que es el predeterminado ni uno con oportunidades; no
  se elimina una etapa con oportunidades ni la última etapa intermedia.
- Cada embudo tiene siempre una etapa Ganado y una Perdido.

## Integraciones con otros módulos

- **CRM**: usa los embudos, etapas, orígenes y motivos de aquí.
- **Bandeja social**: recibe los mensajes y comentarios de la
  cuenta conectada y los reparte según la regla elegida.
- **Publicaciones**: publica en las cuentas de Instagram y LinkedIn conectadas aquí.

## Errores frecuentes

- **«La cuenta de Instagram es personal…»**: cámbiela a profesional y vuelva a
  conectar.
- **«La cuenta @… ya está conectada a otra empresa del sistema»**: desconéctela
  allí primero.
- **«No se concedieron todos los permisos…»**: vuelva a conectar y acepte todos.
- **«Instagram no aceptó la suscripción a mensajes»**: vuelva a conectar; si se
  repite, la app de Meta de la empresa aún no tiene aprobados los permisos de
  mensajes, o la cuenta no está como evaluadora mientras la app está en modo
  desarrollo.
- **«La conexión con Instagram venció o no se inició desde esta pantalla»**:
  pasaron más de 10 minutos entre pulsar Conectar y autorizar, o se usó un
  enlace viejo. Pulse **Conectar Instagram** otra vez.
- **«Registre primero la app de Meta de la empresa…»** o **«La empresa no tiene
  registrada su app de Meta»**: falta registrar la app en esta pestaña.
- **«Hay cuentas de Instagram conectadas con esta app. Desconéctelas primero.»**:
  al quitar la app.
- **Meta muestra «Invalid platform app» al conectar**: la app es nueva (Unified
  login) y falta el **ID de configuración**.
- **«Meta no compartió ninguna cuenta de Instagram»**: en la ventana de Meta no se
  eligió la cuenta (o es personal). Vuelva a conectar y elíjala.
- **«Hay cuentas de Instagram conectadas con la forma anterior…»**: al poner o quitar
  el ID de configuración. Desconecte las cuentas, guarde y vuelva a conectarlas.
- **«Para activar el asistente, configure Anthropic (Claude)…»**: la IA de la
  empresa no es Anthropic o no tiene clave (IA Soporte → Configuración).
- **«El asistente virtual todavía no está instalado en la base de datos»**: falta
  aplicar el SQL de la fase 2 del CRM; avise al administrador del sistema.
- **«La conexión con LinkedIn venció o no se inició desde esta pantalla»**: pulse
  **Conectar** otra vez (hay 10 minutos para autorizar).
- **«…ya está conectada a otra empresa del sistema; no se conectó aquí»** (LinkedIn):
  desconéctela en la otra empresa primero.
- **«LinkedIn todavía no está instalado en la base de datos»**: falta aplicar el
  SQL de la fase 3 del CRM; avise al administrador del sistema.

## Historial de cambios

- **1.4** — Sin el botón «Ir al CRM» en el encabezado (el CRM se abre desde el menú). Canales en **secciones plegables** (Instagram y LinkedIn). **Unified login** (apps de Meta nuevas): **paso a paso en un modal** (una tarjeta por paso, con la ruta de menús y los campos como en Meta), **Conectar** Instagram y LinkedIn en una **ventana aparte** que se cierra sola y devuelve el resultado al sistema, campo **ID de configuración**, App ID/Secret de la app de Meta, acceso del portafolio que no vence, una tarjeta por cuenta compartida, aviso de permisos que faltan en la configuración y de acceso revocado.
- **1.3** — **LinkedIn**: app de la empresa y conexión de perfil y páginas (solo
  para publicar). Aviso para reconectar Instagram y dar los permisos de publicar y
  de estadísticas.
- **1.2** — Pestaña **Asistente virtual**: configurar, probar y ver el consumo del
  asistente con IA que responde los mensajes directos de Instagram.
- **1.1** — Cada empresa registra **su propia app de Meta** (App ID, App Secret y
  token del webhook) en la pestaña Canales; las direcciones para Meta las ve
  cualquier usuario con acceso al módulo.
- **1.0** — Versión inicial: conexión de Instagram (con renovación automática del
  acceso), reparto de conversaciones, embudos y etapas, orígenes y motivos de
  pérdida.
