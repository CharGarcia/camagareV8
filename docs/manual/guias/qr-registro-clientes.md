---
titulo: QR de registro de clientes
resumen: Un código QR de la empresa para que sus clientes registren o corrijan sus datos desde el celular, sin que nadie los digite.
categoria: Ventas
ruta_modulo: modulos/clientes
tipo: guia
visibilidad: todos
etiquetas: qr de registro, registro por qr, autorregistro, codigo qr clientes, el cliente llena sus datos, el cliente escribe sus datos, formulario para clientes, registrar clientes, actualizar datos del cliente, corregir datos, correo para la factura, datos para la factura, mostrador, caja, tienda, imprimir qr, cartel qr, desactivar qr, enlace publico, link para clientes
version: 1.0
orden: 0
estado: activo
---

El **QR de registro** permite que el propio cliente escriba sus datos (cédula o
RUC, nombre, correo, teléfono y dirección) desde su celular. Al enviarlos queda
registrado en [Clientes](modulos/clientes) al instante, y quien factura solo
tiene que buscarlo. Sirve, por ejemplo, para dejar el QR impreso en la caja de
una tienda.

## Qué es y para qué sirve

- Es **un solo QR por empresa**: el mismo código sirve en todos los
  establecimientos, porque los clientes son de la empresa.
- El QR **no cambia**: lo imprime una vez y lo deja en el mostrador.
- Si el cliente **ya existe** (misma identificación en la empresa), lo que envía
  **actualiza** su ficha. Por eso, si se equivocó, basta con que vuelva a llenar
  el formulario con los datos correctos.

## Requisitos previos

- Permiso de **ver** Clientes para abrir el QR. La primera vez el QR se genera
  solo si quien lo abre tiene permiso de **crear** o **modificar** clientes.
- La empresa debe estar **activa**.

## Cómo se usa

1. En **Clientes**, pulse **QR de registro** (junto a *Mapa*).
2. Revise o cambie el **Título** y el **Mensaje** del impreso y pulse
   **Guardar**.
3. Pulse **Imprimir QR**. Se genera una hoja A4 con el logo y el nombre de la
   empresa, el título, el mensaje y el código; elija **Imprimir**,
   **Descargar** o **Ver**.
4. Coloque la hoja donde el cliente la vea. También puede **copiar el enlace** y
   enviarlo por WhatsApp o correo.

## Lo que ve el cliente

1. Al escanear, ve el título y el mensaje que usted configuró y el formulario.
2. Elige **Cédula**, **RUC** o **Pasaporte** y escribe su número. Con la cédula
   o el RUC completos, el sistema consulta al SRI y, si lo encuentra, llena el
   **nombre** cuando está vacío.
3. Completa **correo electrónico** (obligatorio; ahí recibirá sus facturas) y,
   si quiere, **teléfono** y **dirección**.
4. Pulsa **Enviar mis datos** y ve *«¡Registro completo!»* (cliente nuevo) o
   *«¡Datos actualizados!»* (cliente que ya existía), con el enlace
   **¿Te equivocaste? Corregir mis datos**.

Por privacidad, el formulario **nunca muestra** los datos que la empresa ya
tiene de un cliente: aunque alguien escriba una cédula registrada, no ve el
nombre ni el correo guardados.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo | Sí | Cédula, RUC o Pasaporte |
| Identificación | Sí | Cédula de 10 dígitos, RUC de 13 terminado en 001 o 002, o pasaporte (letras, números y guiones, máximo 20) |
| Nombre completo o razón social | Sí | Máximo 300 caracteres |
| Correo electrónico | Sí | Donde el cliente recibe sus facturas |
| Teléfono | No | Números, espacios, +, - y paréntesis |
| Dirección | No | Máximo 300 caracteres |

## Permisos

| Permiso en Clientes | Qué permite |
|---------------------|-------------|
| Ver | Abrir la ventana, ver el QR, copiar el enlace e imprimirlo |
| Crear o Modificar | Generar el QR la primera vez |
| Modificar | Cambiar el título y el mensaje, y activar o desactivar el QR |

El cliente que llena el formulario no necesita usuario ni contraseña.

## Reglas de negocio

- **Cliente nuevo**: se crea con el tipo e identificación, nombre, correo,
  teléfono y dirección enviados; sin vendedor, con plazo 0 y activo.
- **Cliente existente** (misma identificación): se actualizan **nombre** y
  **correo**, y también **teléfono** y **dirección** si los envió (si los deja
  vacíos se conservan los que tenía). Nunca cambian el tipo ni el número de
  identificación, el vendedor, el plazo ni la configuración de cobros. Si la
  ficha estaba eliminada, se reactiva.
- No se acepta la identificación de **Consumidor Final** (todo 9).
- Cada envío queda en la **auditoría** del cliente (Información / historial),
  con el origen *QR de registro de clientes* y la IP; se atribuye al usuario que
  generó el QR.
- Desde un mismo celular o red se aceptan hasta **10 envíos por hora**.
- Con el QR **desactivado** o la empresa **inactiva**, el enlace se comporta como
  si no existiera.

## Integraciones con otros módulos

- **Clientes**: es donde se crean y actualizan las fichas.
- **Facturas de Venta y demás documentos**: el cliente registrado se busca como
  cualquier otro; el correo que escribió es el que recibe la factura.

## Errores frecuentes

- **«Este enlace no está disponible o ha sido desactivado.»**: el QR está
  apagado en Clientes → QR de registro, o la empresa está inactiva.
- **«Has enviado demasiados registros. Espera un momento e intenta
  nuevamente.»**: se superaron los 10 envíos por hora desde esa red; espere o
  registre al cliente desde la ficha.
- **«Ingresa tu propia cédula, RUC o pasaporte.»**: se escribió la
  identificación de Consumidor Final.
- **«Aún no se ha generado el QR de registro de esta empresa.»**: lo debe abrir
  primero un usuario con permiso de crear o modificar clientes.
- **Un cliente cambió el correo de otro**: como el QR es público, quien conozca
  la cédula de un cliente puede enviar datos a su nombre. Revise la auditoría
  del cliente (muestra fecha, IP y los datos anteriores) y corrija la ficha.

## Historial de cambios

- **1.0** (26-11-2026) — Versión inicial.
