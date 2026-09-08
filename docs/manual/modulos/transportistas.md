---
titulo: Transportistas
resumen: Quienes trasladan la mercadería; se usan en las guías de remisión.
categoria: Ventas
ruta_modulo: modulos/transportistas
tipo: modulo
visibilidad: todos
etiquetas: transportistas, transportista, transporte, chofer, conductor, guia de remision, placa, correo, email, varios correos
version: 1.1
orden: 60
estado: activo
---

Los **transportistas** son las personas o empresas que trasladan la mercadería.
Su único uso es la **guía de remisión**: sin transportista registrado no se puede
emitir una.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** (hasta 300 caracteres).
3. Indique el **tipo de identificación** y el número.
4. Registre el **correo electrónico**, la **placa** habitual y, si quiere,
   teléfono y dirección.
5. Guarde.

El mismo formulario se abre desde la guía de remisión con el botón
**Registrar nuevo transportista**; al guardarlo queda seleccionado en la guía.

## Correo electrónico: uno o varios

En **Correo electrónico** puede escribir **varios correos** separados por coma,
punto y coma o espacio (por ejemplo `chofer@empresa.com, despacho@empresa.com`).
Se guardan normalizados en minúsculas y sin repetidos, hasta 200 caracteres en
total.

Es el correo al que se envía la **guía de remisión** (XML y PDF) cuando el SRI
la autoriza, junto con el del cliente destinatario; si hay varios, llega a
todos. También aparece en el RIDE de la guía, debajo del nombre del
transportista, uno por línea.

## Tipos de identificación admitidos

Solo se aceptan los códigos del SRI:

| Código | Tipo |
|--------|------|
| 04 | RUC |
| 05 | Cédula |
| 06 | Pasaporte |

Si elige otro, el sistema lo rechaza indicando estos tres.

## Errores frecuentes

- **"El tipo de identificación no es válido"**: use 04, 05 o 06.
- **"Correo(s) con formato inválido: …"**: revise los correos que indica el
  mensaje; cada uno debe tener la forma `nombre@dominio.com`.
- **No aparece al emitir una guía de remisión**: pertenece a otra empresa o fue
  eliminado.

## Historial de cambios

- **1.1** — El campo **Correo electrónico** admite **varios correos** (coma,
  punto y coma o espacio). Antes el formulario lo permitía, pero al guardar el
  servidor rechazaba la ficha con "El email no tiene un formato válido"; ahora
  valida cada correo por separado y los guarda normalizados. Aplica también al
  formulario que se abre desde la guía de remisión.
- **1.0** — Versión inicial.
