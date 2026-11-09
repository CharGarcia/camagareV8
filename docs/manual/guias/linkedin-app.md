---
titulo: App de LinkedIn para publicar (CRM)
resumen: Cómo crea la empresa su app de LinkedIn y la registra en Configuración del CRM para publicar en el perfil personal y, con aprobación de LinkedIn, en la página de empresa.
categoria: Ventas
tipo: guia
visibilidad: todos
etiquetas: linkedin, app de linkedin, linkedin developers, client id, client secret, redirect url, oauth, share on linkedin, sign in with linkedin, openid connect, community management api, pagina de empresa, company page, perfil personal, publicar en linkedin, w_member_social, w_organization_social, conectar linkedin, crm linkedin
version: 1.0
orden: 51
estado: activo
---

Para publicar en LinkedIn desde **Publicaciones**, cada empresa registra **su
propia app de LinkedIn** en **Configuración del CRM → Canales → App de LinkedIn de
la empresa**. LinkedIn solo se usa para **publicar**: sus mensajes y comentarios no
llegan al sistema.

## 1. Crear la app en LinkedIn

1. Entre a **linkedin.com/developers** → **Create app**.
2. Nombre, la **página de LinkedIn de la empresa** (obligatoria; LinkedIn pide que
   la página verifique la app), logo y aceptar los términos.
3. En la pestaña **Products** agregue:
   - **Sign In with LinkedIn using OpenID Connect**.
   - **Share on LinkedIn**.

   Estos dos son de **autoservicio**: no requieren revisión y alcanzan para
   publicar en el **perfil personal** de quien conecta.
4. En **Auth** copie el **Client ID** y el **Primary Client Secret**.

## 2. Registrarla en el sistema

1. En **Configuración del CRM → Canales → App de LinkedIn de la empresa**, pegue
   el Client ID y el Client Secret y pulse **Guardar**. El secreto se guarda
   cifrado y no se vuelve a mostrar (para cambiarlo, escriba uno nuevo; vacío lo conserva).
2. Copie la **Dirección de retorno** que muestra la tarjeta y péguela en la app de
   LinkedIn: **Auth → OAuth 2.0 settings → Authorized redirect URLs for your app**.
   Debe coincidir exacto (con https).

## 3. Conectar

- **Conectar mi perfil**: LinkedIn pide iniciar sesión y autorizar. Al volver, el
  perfil aparece en Canales como cuenta de LinkedIn (solo para publicar).
- **Conectar perfil y páginas**: aparece solo si marcó que la app tiene aprobada la
  publicación en páginas (ver abajo). Conecta su perfil y **cada página de empresa
  que usted administra**.

Una página o perfil conectado en otra empresa del sistema no se conecta aquí
(el sistema avisa).

## Publicar en la página de empresa

Publicar como **página** exige el producto **Community Management API**, que
LinkedIn **aprueba caso por caso**:

- Se solicita en **Products → Community Management API**. LinkedIn pide, entre
  otras cosas, sitio web y dominio de la empresa verificados y que la app esté
  verificada por la página.
- No hay un plazo publicado de aprobación.
- La persona que conecta debe ser **administradora** de la página en LinkedIn.
- Cuando LinkedIn lo apruebe, marque en la tarjeta **«LinkedIn ya aprobó
  Community Management API en esta app»**, guarde y use **Conectar perfil y páginas**.

Sin esa aprobación se puede publicar igual en el **perfil personal**.

## Estadísticas

- **Página de empresa**: el sistema toma alcance, impresiones, clics, me gusta,
  comentarios y compartidos a las 24 horas, 7 y 30 días.
- **Perfil personal**: LinkedIn no permite leer sus estadísticas por la API.

## Duración del acceso

El acceso dura unos **60 días** y LinkedIn no permite que el sistema lo renueve
solo. La tarjeta de la cuenta avisa **10 días antes**: pulse **Reconectar**. Si
vence, la cuenta queda en **Acceso vencido** y lo programado para ella queda con
error hasta reconectar (luego use **Reintentar** en la publicación).

## Errores frecuentes

- **LinkedIn dice que la dirección de retorno no coincide**: copie de nuevo la
  «Dirección de retorno» del sistema en *Authorized redirect URLs*.
- **«LinkedIn no concedió el permiso de publicar (w_member_social)»**: la app no
  tiene el producto *Share on LinkedIn*; agréguelo y reconecte.
- **«La app … no tiene aprobada la publicación en páginas»**: falta la aprobación
  de *Community Management API* o no marcó la casilla.
- **«No se encontraron páginas de empresa que usted administre»**: la persona que
  conectó no es administradora de la página.
- **«Hay cuentas de LinkedIn conectadas con esta app. Desconéctelas primero.»**: al
  intentar quitar la app.

## Historial de cambios

- **1.0** — Versión inicial.
