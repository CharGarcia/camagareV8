---
titulo: Configuración del CRM
resumen: Conectar la cuenta de Instagram de la empresa y ajustar embudos, etapas, orígenes y motivos de pérdida del CRM.
categoria: Ventas
ruta_modulo: modulos/crm_configuracion
tipo: modulo
visibilidad: todos
etiquetas: app de meta, app id, app secret, token de verificacion, configuracion crm, ajustes crm, conectar instagram, vincular instagram, cuenta de instagram, redes sociales, instagram empresa, desconectar instagram, reconectar, acceso vencido, token, embudo, embudos, etapas, etapas del embudo, pipeline, probabilidad, orden de etapas, origenes, origen del contacto, motivos de perdida, asignar conversaciones, repartir mensajes, turnos, human agent, siete dias
version: 1.1
orden: 15
estado: activo
---

Aquí se prepara el **CRM** de la empresa: qué cuenta de **Instagram** está
conectada, cómo se reparten las conversaciones que lleguen y cómo son los
**embudos** con sus **etapas**, los **orígenes** de los contactos y los
**motivos de pérdida**.

## Qué es y para qué sirve

- **Canales**: conectar la cuenta de Instagram de la empresa. Una vez conectada,
  sus mensajes directos y comentarios llegan a la **Bandeja social**.
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

### Registrar la app de Meta de la empresa

Cada empresa usa su propia app de Meta. En **Canales → App de Meta de la
empresa**:

1. Pegue el **Instagram App ID** y el **Instagram App Secret** de la app (se crean
   en developers.facebook.com) y pulse **Guardar**.
2. Copie las cuatro direcciones y el **token de verificación** que aparecen y
   péguelos en la app de Meta. El paso a paso está en la guía
   **App de Meta para Instagram (CRM)**.

El App Secret se guarda cifrado y no vuelve a mostrarse: para cambiarlo, escriba
uno nuevo; si deja el campo vacío, se conserva el guardado. El botón de flechas
junto al token genera otro (hay que pegarlo de nuevo en Meta). La papelera quita la
app, solo si no hay cuentas conectadas con ella.

### Conectar la cuenta de Instagram

1. Pestaña **Canales** → **Conectar Instagram** (aparece cuando la app está registrada).
2. Instagram pide iniciar sesión con la cuenta de la empresa y **autorizar** al
   sistema. Acepte **todos** los permisos: sin el de mensajes o el de
   comentarios, esos no llegarán.
3. Al volver, el sistema muestra *«Cuenta @… conectada»* y la cuenta aparece con
   estado **Conectada** y la fecha hasta la que vale el acceso.

El acceso dura unos 60 días y **el sistema lo renueva solo** cada día cuando
faltan 10 o menos. Si por algún motivo vence, la cuenta pasa a **Acceso vencido**:
pulse **Reconectar** y autorice de nuevo.

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
  Meta) y **Probar** la conexión.
- **Crear**: registrar la app de Meta, conectar Instagram, crear embudos, etapas,
  orígenes y motivos.
- **Modificar**: editar la app de Meta y regenerar su token, editar lo anterior,
  ordenar etapas y los ajustes de la cuenta (reparto, 7 días).
- **Eliminar**: quitar la app de Meta, desconectar Instagram y eliminar embudos,
  etapas, orígenes y motivos.

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

## Historial de cambios

- **1.1** — Cada empresa registra **su propia app de Meta** (App ID, App Secret y
  token del webhook) en la pestaña Canales; las direcciones para Meta las ve
  cualquier usuario con acceso al módulo.
- **1.0** — Versión inicial: conexión de Instagram (con renovación automática del
  acceso), reparto de conversaciones, embudos y etapas, orígenes y motivos de
  pérdida.
