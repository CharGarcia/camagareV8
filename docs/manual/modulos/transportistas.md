---
titulo: Transportistas
resumen: Quienes trasladan la mercadería; se usan en las guías de remisión.
categoria: Ventas
ruta_modulo: modulos/transportistas
tipo: modulo
visibilidad: todos
etiquetas: transportistas, transportista, transporte, chofer, conductor, guia de remision, placa, correo, email, varios correos
version: 1.2
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
5. Deje encendido el interruptor **Estado** (*Activo*); apáguelo para dejar al
   transportista inactivo.
6. Pulse **Guardar**.

Mientras se guarda aparece el aviso **Guardando transportista...** (o
**Actualizando transportista...** si ya existía); espere a que termine. La ficha
**no se cierra** al guardar: queda abierta en modo edición y se cierra con
**Cancelar**.

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

- **1.2** — El **Estado** de la ficha pasa a ser un interruptor (*Activo*), igual que en Productos, Clientes, Proveedores, Vendedores y Empleados. Al guardar aparece el aviso *Guardando / Actualizando transportista...* mientras el servidor responde.
- **1.1** — El campo **Correo electrónico** admite **varios correos** (coma,
  punto y coma o espacio). Antes el formulario lo permitía, pero al guardar el
  servidor rechazaba la ficha con "El email no tiene un formato válido"; ahora
  valida cada correo por separado y los guarda normalizados. Aplica también al
  formulario que se abre desde la guía de remisión. Se corrige además que,
  tras guardar, buscar u ordenar, el listado quedaba **inactivo** (no se podía
  abrir ningún registro ni paginar hasta recargar la página). Al **Guardar**,
  el modal ya no se cierra: queda abierto en modo edición (como en Clientes) y
  se cierra con **Cancelar**.
- **1.0** — Versión inicial.
