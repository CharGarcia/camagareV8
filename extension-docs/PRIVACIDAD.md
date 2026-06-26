# Política de privacidad — CaMaGaRe — Descarga SRI

_Última actualización: 2026-06-25_

Esta política describe cómo la extensión **"CaMaGaRe — Descarga SRI"** trata la información.
La extensión es una herramienta de trabajo que conecta el portal del SRI (Servicio de Rentas
Internas del Ecuador) con el sistema contable **CaMaGaRe** del propio usuario.

## Qué datos se tratan y para qué

La extensión solo trata datos cuando el usuario inicia una descarga desde su sistema CaMaGaRe:

- **Token de acceso del usuario:** un identificador que el sistema CaMaGaRe entrega a la extensión
  para autenticar las peticiones. Se guarda **localmente** en el navegador (`chrome.storage.local`).
- **Credenciales del SRI (RUC y clave):** la extensión las recibe **de tu propio servidor CaMaGaRe**
  únicamente para escribirlas en el formulario de inicio de sesión del portal del SRI. **No se
  almacenan** en la extensión: se usan en el momento y se descartan.
- **Claves de acceso de los comprobantes:** la extensión lee las claves de acceso (49 dígitos) que
  se muestran en la página de "Comprobantes electrónicos recibidos" del SRI y las envía a tu
  servidor CaMaGaRe para su registro.
- **Cookies del SRI:** al pulsar "Cerrar sesión SRI", la extensión elimina las cookies del dominio
  del SRI en tu navegador, para cerrar la sesión. No las lee ni las transmite a ningún lado.

## Con quién se comparten

**Con nadie más que tu propio sistema CaMaGaRe.** Los datos viajan únicamente entre tu navegador,
el portal del SRI y el servidor de CaMaGaRe que tú indicas. **No se envían a Anthropic, a los
autores de la extensión ni a terceros**, ni se usan para publicidad, analítica o perfilamiento.

## Almacenamiento

Lo único que se guarda de forma persistente es el **token** y la **URL de tu servidor**, en el
almacenamiento local del navegador. Puedes borrarlos quitando la extensión.

## Permisos

- `storage`: guardar el token y la URL del servidor.
- `cookies`: cerrar sesión en el SRI eliminando sus cookies.
- Acceso a `*.sri.gob.ec`: operar dentro del portal del SRI (login y lectura de comprobantes).
- Acceso al dominio de tu sistema CaMaGaRe: recibir el token y enviar las claves.

## Contacto

Para cualquier consulta sobre privacidad: **soporte@camagare.com.ec**.
