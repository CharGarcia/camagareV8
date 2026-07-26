---
titulo: Configuración contable
resumen: Qué cuentas usa cada tipo de documento al generar su asiento automático.
categoria: Contabilidad
ruta_modulo: modulos/configuracion-contable
tipo: modulo
visibilidad: admin
etiquetas: configuracion contable, cuentas por documento, asiento automatico, parametrizacion, ventas, compras, cierre
version: 1.0
orden: 5
estado: activo
---

Esta pantalla es la que hace que la contabilidad funcione sola. Define **qué
cuentas del plan usa cada tipo de documento** al generar su asiento: qué cuenta
se debita al facturar, cuál se acredita al cobrar, dónde va el IVA, dónde el
costo de ventas.

Sin esto configurado, los documentos no generan asiento.

## Cómo está organizado

Cada tipo de operación (venta con factura, compra, cobro, pago, traspaso,
consignación, cierre del ejercicio…) tiene su configuración con las cuentas que
necesita.

## De lo general a lo específico

Las cuentas se resuelven por **especificidad**: si una entidad concreta —un
producto, un cliente, una forma de pago— tiene su propia cuenta configurada, esa
manda sobre la configuración general.

Dicho al revés: la configuración general es el valor por defecto, y lo que se
configura en la ficha concreta lo sobreescribe. Es lo que permite que casi todo
funcione con una configuración única y solo las excepciones necesiten atención.

## Cierre del ejercicio

Entre los tipos configurables está el **cierre del ejercicio**, que necesita dos
cuentas: la de *resumen de resultados* y la de *resultado del ejercicio*. Son las
que permiten cerrar el año llevando la utilidad al patrimonio.

## Cuándo tocar esta pantalla

- Al poner en marcha la empresa.
- Al cambiar el plan de cuentas.
- Cuando un asiento automático va a una cuenta equivocada de forma sistemática.

Si el error es en un solo documento, el problema no está aquí sino en ese
documento o en la ficha de la entidad implicada.

## Errores frecuentes

- **Un documento no genera asiento**: falta configurar su tipo de operación.
- **El asiento va a una cuenta que no corresponde**: revise primero la ficha del
  producto, cliente o forma de pago; su cuenta manda sobre la general.

## Historial de cambios

- **1.0** — Versión inicial.
