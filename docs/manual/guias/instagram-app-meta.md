---
titulo: App de Meta para Instagram (CRM)
resumen: Cómo crea la empresa su app de Meta y la registra en Configuración del CRM para conectar su cuenta de Instagram, recibir mensajes y cumplir la eliminación de datos.
categoria: Ventas
tipo: guia
visibilidad: todos
etiquetas: meta, facebook developers, app de meta, instagram api, instagram login, app id, app secret, redirect uri, oauth, webhook, token de verificacion, verify token, app review, revision de la app, permisos instagram, human agent, eliminacion de datos, data deletion, desautorizar, deauthorize, privacidad, crm instagram, conectar instagram
version: 2.0
orden: 50
estado: activo
---

Para usar Instagram en el CRM, **cada empresa** registra su propia app de Meta en
**Configuración del CRM → Canales → App de Meta de la empresa**. Lo puede hacer
cualquier usuario con permiso en ese módulo. Los datos quedan guardados solo
para esa empresa, y el App Secret se guarda cifrado y no se vuelve a mostrar.

## 1. Crear la app en Meta

1. Entre a **developers.facebook.com** con la cuenta de Facebook que administra el
   negocio → *Mis apps* → **Crear app**, tipo **Empresa**, vinculada al portafolio
   comercial (Business Manager) de la empresa, de preferencia **verificado**.
2. Agregue el producto **Instagram** → *API con inicio de sesión de Instagram*.
3. Copie el **Instagram App ID** y el **Instagram App Secret** (no los de Facebook).

## 2. Registrarla en el sistema

1. En **Configuración del CRM → Canales**, pegue el App ID y el App Secret y pulse
   **Guardar**.
2. Aparecen los **datos para registrar en la app de Meta**: cuatro direcciones y el
   **token de verificación del webhook**. Cada uno tiene su botón para copiar.

Para cambiar el App ID basta editarlo; el App Secret se deja vacío para conservar
el guardado o se escribe uno nuevo para reemplazarlo.

## 3. Pegar los datos en la app de Meta

En la app, *Instagram → Configuración de la API con inicio de sesión de Instagram*:

| En Meta | Qué pegar |
|---------|-----------|
| Inicio de sesión empresarial → **URI de redirección de OAuth válidas** | la dirección «URI de redirección de OAuth válida» |
| **Webhooks** → URL de devolución de llamada | la dirección «URL de devolución de llamada (webhook)» |
| **Webhooks** → Token de verificación | el «Token de verificación del webhook» |
| Webhooks → campos | suscriba **messages**, **messaging_seen** y **comments** |
| Inicio de sesión empresarial → **Cancelar autorización** | la «URL para cancelar la autorización» |
| Inicio de sesión empresarial → **Solicitud de eliminación de datos** | la «URL de solicitud de eliminación de datos» |

Al guardar el webhook, Meta comprueba el token; si no coincide responde
«Forbidden». Si regenera el token en el sistema, péguelo otra vez en Meta.

## 4. Conectar la cuenta de Instagram

Con la app registrada aparece el botón **Conectar Instagram** (ver el manual
*Configuración del CRM*).

## Permisos de Meta y revisión de la app

El sistema pide `instagram_business_basic`, `instagram_business_manage_messages`
e `instagram_business_manage_comments`.

- Mientras la app está en **modo desarrollo**, solo funcionan las cuentas de
  Instagram agregadas como **evaluadoras** en la app. Alcanza para probar con la
  cuenta propia de la empresa.
- Para el uso normal hay que pasar la **revisión de la app** (App Review) de esos
  permisos en Meta: pide política de privacidad pública, la URL de eliminación de
  datos (ya la entrega el sistema) y un video del flujo completo.
- **Human Agent** (responder hasta 7 días) es una función aparte que también se pide
  en la revisión. Solo cuando esté aprobada active «Responder hasta 7 días» en la
  cuenta.

## Qué hace el sistema con cada dirección

- **Webhook**: cada aviso de Meta llega firmado con el App Secret de **su** app. El
  sistema lo valida con la app de la empresa dueña de la cuenta; un aviso firmado
  por la app de otra empresa se rechaza y no puede meter mensajes en la suya.
- **Cancelar autorización**: si alguien quita la app desde Instagram, la cuenta se
  desconecta (se borra su acceso; el historial queda).
- **Eliminación de datos**: si alguien pide a Meta borrar sus datos, el sistema borra
  lo que guardó de esa cuenta o persona **en las empresas que usan esa app** (texto
  y archivos de mensajes y comentarios, perfil y datos de Instagram de sus contactos)
  y entrega a Meta un **código de confirmación** con una página pública de estado.
  Lo que la empresa escribió a mano en un contacto se respeta.

## Renovación del acceso

El acceso de cada cuenta dura ~60 días y el sistema lo renueva solo, una vez al
día, cuando faltan 10 días o menos, con la app de su empresa. Si la empresa quita su
app, sus cuentas dejan de renovarse y hay que volver a registrarla y reconectar.

## Errores frecuentes

- **«Registre primero la app de Meta de la empresa…»**: falta el paso 2.
- **«El App ID de Instagram son solo números»**: copió otro dato (por ejemplo, el
  nombre de la app).
- **Meta dice «Forbidden» al guardar el webhook**: el token no coincide; cópielo de
  nuevo desde el sistema.
- **«Hay cuentas de Instagram conectadas con esta app. Desconéctelas primero.»**: al
  intentar quitar la app.

## Historial de cambios

- **2.0** — La app de Meta la registra cada empresa en Configuración del CRM (antes
  era una sola app del sistema en `config/local.php`). Webhook, cancelación de
  autorización y eliminación de datos se validan con la app de la empresa dueña.
- **1.2** — URLs de eliminación de datos y de desautorización, con página pública de estado.
- **1.1** — Webhook activo en `/instagram-webhook`.
- **1.0** — Versión inicial.
