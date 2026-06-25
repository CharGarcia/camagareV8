# Publicar "CaMaGaRe — Descarga SRI" en la Chrome Web Store

Todo lo de aquí es para **copiar/pegar** en el formulario de la tienda. Yo ya dejé el paquete
listo (`extension.zip`); tú haces los pasos de cuenta, subida y envío.

---

## 0. Lo que necesitas hacer tú (resumen)
1. Crear cuenta de **desarrollador** en la Chrome Web Store (pago único **US$5**).
2. Subir el archivo **`extension.zip`**.
3. Pegar los textos de abajo en el formulario.
4. Subir **capturas de pantalla** (ver sección 5).
5. Poner la **URL de la política de privacidad** (ver sección 6).
6. Enviar a revisión. Google suele tardar de **horas a unos días**.

Panel: https://chrome.google.com/webstore/devconsole/

---

## 1. Datos básicos
- **Nombre:** CaMaGaRe — Descarga SRI
- **Categoría sugerida:** Productividad (Workflow & Planning)
- **Idioma principal:** Español (Latinoamérica)

## 2. Descripción corta (máx. 132 caracteres)
```
Descarga tus comprobantes recibidos del SRI y los envía a tu sistema CaMaGaRe con un clic.
```

## 3. Descripción larga (para el campo "Descripción")
```
CaMaGaRe — Descarga SRI agiliza la descarga de tus comprobantes electrónicos recibidos del portal del SRI (Ecuador) hacia tu sistema contable CaMaGaRe.

Cómo funciona:
1. En tu sistema CaMaGaRe pulsas "Generar descarga del SRI".
2. La extensión abre el portal del SRI, ingresa con el RUC y la clave de la empresa activa y te lleva a "Comprobantes electrónicos recibidos".
3. Eliges el período (año, mes, día) y el tipo de documento y das clic en Consultar (tú resuelves el captcha, como siempre).
4. Pulsas "Enviar comprobantes al sistema" y la extensión recolecta las claves de acceso y las envía a tu sistema, que descarga y registra los XML.

Ventajas:
- Sin teclear el RUC y la clave en cada empresa.
- Te lleva directo al listado de comprobantes recibidos.
- Descarga varios períodos seguidos.
- Botón para cerrar sesión y cambiar de empresa rápido.

Requiere una cuenta activa en el sistema CaMaGaRe. La extensión no funciona por sí sola: es un complemento de ese sistema.
```

## 4. Propósito único (campo "Single purpose")
```
Conectar el portal del SRI (Ecuador) con el sistema contable CaMaGaRe del usuario: ingresar al SRI con las credenciales de la empresa activa, ubicar los comprobantes recibidos y enviar sus claves de acceso al sistema del usuario para su registro.
```

## 5. Justificación de permisos (campo por campo)
- **storage:** "Guardar localmente el token de acceso y la URL del sistema del usuario, para no pedirlos cada vez."
- **cookies:** "Permitir cerrar la sesión del SRI eliminando sus cookies, para que el usuario pueda cambiar de empresa de forma limpia."
- **Host `*.sri.gob.ec`:** "Operar dentro del portal del SRI: completar el inicio de sesión y leer las claves de acceso de los comprobantes recibidos."
- **Host del sistema CaMaGaRe (erp.camagare.com.ec):** "Recibir el token del usuario y enviar las claves de acceso al sistema para su registro."
- **Uso de código remoto:** No. Todo el código va dentro del paquete.

> Nota: si Google observa los permisos `http://localhost/*` y `http://127.0.0.1/*` (son solo para
> pruebas en desarrollo), podemos quitarlos del paquete y volver a subir. Avísame y te paso un zip
> sin ellos.

## 6. Política de privacidad (OBLIGATORIA)
Google exige una **URL pública** con la política. Sube el contenido de `PRIVACIDAD.md` a una página
de tu sitio, por ejemplo:
```
https://erp.camagare.com.ec/privacidad-extension
```
y pega esa URL en el campo "Política de privacidad" del formulario. (Si quieres, te armo esa página
dentro del sistema para que quede publicada con esa URL.)

## 7. Capturas de pantalla (necesitas subir al menos 1)
Tamaño: **1280×800** o **640×400** px. Sugeridas (las tomas tú, de tu pantalla):
1. La pantalla "Descargas SRI" de tu sistema con el botón "Generar descarga del SRI".
2. El portal del SRI ya en "Comprobantes recibidos" con el botón azul "Enviar comprobantes al sistema".
3. El aviso verde de resultado ("Nuevos / Ya existían / Errores").

## 8. Visibilidad
Si es solo para tus clientes y no quieres que aparezca en búsquedas, elige **"No listada"
(Unlisted)**: solo entra quien tenga el enlace. Si quieres que cualquiera la encuentre, **"Pública"**.

---

## 9. Después de publicar
Me pasas el **enlace** de la extensión publicada y yo:
- Reemplazo el enlace placeholder en la pantalla de Descargas SRI por el real.
- Actualizo el texto para que diga "Instalar con un clic".
