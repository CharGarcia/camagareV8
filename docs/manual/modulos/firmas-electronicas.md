---
titulo: Firmas electrónicas
resumen: Certificado de firma con el que se firman los comprobantes antes de enviarlos al SRI.
categoria: Configuración de empresa
ruta_modulo: modulos/firmas_electronicas
tipo: modulo
visibilidad: admin
etiquetas: firma electronica, certificado, p12, token, caducada, firmar comprobantes, sri
version: 1.0
orden: 20
estado: activo
---

La **firma electrónica** es el certificado con el que la empresa firma sus
comprobantes antes de enviarlos al SRI. Sin una firma vigente cargada, no se
puede emitir ninguna factura electrónica.

## Qué se registra

| Campo | Regla |
|-------|-------|
| Tipo de identificación | Debe ser uno de los válidos |
| Número de identificación | Obligatorio |
| Cédula | Exactamente **10 dígitos numéricos** |
| Pasaporte | Solo letras y números, máximo 20 caracteres |
| Usuario responsable | Obligatorio |

Se carga el archivo del certificado junto con su clave.

## Vigencia

El certificado **caduca**. Cuando vence, todos los envíos al SRI empiezan a
fallar de golpe, y el mensaje de error no siempre lo dice con claridad.

Conviene anotar la fecha de caducidad y renovar antes de que llegue: la
renovación con la entidad certificadora no es inmediata, y mientras tanto no se
puede facturar electrónicamente.

## Errores frecuentes

- **Todos los comprobantes empiezan a fallar el mismo día**: lo primero a revisar
  es si la firma caducó.
- **"La cédula debe tener exactamente 10 dígitos numéricos"**: revise el número
  o cambie el tipo de identificación.
- **El sistema no acepta el archivo**: compruebe que la clave sea la correcta y
  que el archivo no esté dañado.

## Historial de cambios

- **1.0** — Versión inicial.
