---
titulo: Firmas electrónicas
resumen: Certificado de firma con el que se firman los comprobantes antes de enviarlos al SRI.
categoria: Configuración de empresa
ruta_modulo: modulos/firmas_electronicas
tipo: modulo
visibilidad: admin
etiquetas: firma electronica, certificado, p12, token, caducada, firmar comprobantes, sri, firma invalida, xades, no se ajusta a xades, certificado de firma, anf, uanataca, security data, banco central
version: 1.1
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

## El SRI responde "FIRMA INVALIDA"

Cuando el SRI devuelve:

> **[ERROR] FIRMA INVALIDA** — La firma es invalida [Firma inválida. La
> información sobre el certificado de firma no se ajusta a XAdES.]

quiere decir que el comprobante **sí se firmó**, pero los datos del certificado
que viajan dentro de la firma no le cuadran al SRI con el certificado usado.

Que el error aparezca con una firma y no con otra es normal: depende de **quién
emitió** el certificado. Cada entidad certificadora (ANF, UANATACA, Security
Data, Banco Central) escribe internamente el nombre de su autoridad emisora de
una forma distinta, y el SRI lo compara carácter por carácter.

Si aparece este error con una firma recién cargada, avise a soporte indicando
**qué entidad emitió el certificado**. No es un problema de la clave ni del
archivo, y volver a cargar la firma no lo soluciona.

## Errores frecuentes

- **Todos los comprobantes empiezan a fallar el mismo día**: lo primero a revisar
  es si la firma caducó.
- **"La cédula debe tener exactamente 10 dígitos numéricos"**: revise el número
  o cambie el tipo de identificación.
- **El sistema no acepta el archivo**: compruebe que la clave sea la correcta y
  que el archivo no esté dañado.
- **"FIRMA INVALIDA / no se ajusta a XAdES"**: ver la sección anterior. La firma
  y la clave están bien; es la forma en que el sistema describe a la entidad
  emisora.

## Historial de cambios

- **1.1** — Se corrigió el rechazo "FIRMA INVALIDA / la información sobre el
  certificado de firma no se ajusta a XAdES" con certificados de ANF Ecuador
  (y de cualquier entidad cuyo emisor incluya atributos poco comunes). El
  nombre de la autoridad emisora ahora se lee del propio certificado.
- **1.0** — Versión inicial.
