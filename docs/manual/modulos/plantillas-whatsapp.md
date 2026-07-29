---
titulo: Plantillas de WhatsApp
resumen: Catálogo de mensajes aprobados por Meta para enviar documentos y enlaces de pago por WhatsApp.
categoria: Herramientas
ruta_modulo: modulos/plantillas-whatsapp
tipo: modulo
visibilidad: todos
etiquetas: whatsapp, plantillas, meta, mensajes aprobados, link de pago, enlace de pago, payphone, nuvei, enviar factura por whatsapp, enviar proforma por whatsapp, variables, aprobacion
version: 1.0
orden: 21
estado: activo
---

WhatsApp no permite escribirle primero a un cliente con texto libre: el primer
mensaje siempre sale de una **plantilla aprobada por Meta**. Este módulo es el
lugar donde esas plantillas se crean, se envían a revisión y se consultan. Todo
lo que el sistema manda por WhatsApp desde otros módulos (una factura, una
proforma, un enlace de pago) usa una plantilla registrada aquí.

## Qué es y para qué sirve

Cada plantilla es un texto fijo con **variables** (`{{1}}`, `{{2}}`…) que el
sistema rellena en el momento del envío con el nombre del cliente, el número del
documento, el monto o el enlace que corresponda. Meta revisa el texto y le pone
un estado: **APPROVED** (aprobada, ya se puede usar), **PENDING** (en revisión) o
**REJECTED** (rechazada). Solo las aprobadas aparecen en los selectores de los
demás módulos.

## Requisitos previos

- La empresa debe tener configurada su conexión de **WhatsApp Business API** en
  Configuración de WhatsApp: token de acceso, ID del número de teléfono y, para
  las plantillas con PDF adjunto, el **App ID** de Meta.
- Las plantillas son **por empresa**: cada empresa mantiene las suyas.

## Cómo se usa

1. Pulse **Nueva Plantilla**.
2. Elija el tipo:
   - **Plantilla Rápida**: una de las plantillas del sistema (lista más abajo).
     El nombre y las variables vienen fijados, porque el módulo que la usa
     rellena esas variables en ese orden exacto. El texto sí se puede redactar
     a gusto de la empresa.
   - **Plantilla Libre**: texto propio, con las variables que usted decida.
3. Revise el cuerpo del mensaje. Los botones bajo el cuadro de texto insertan
   cada variable en la posición del cursor.
4. Si la plantilla lleva **cabecera de documento**, suba un PDF de ejemplo (Meta
   lo exige para aprobar; al enviar mensajes reales se adjunta el PDF de verdad).
5. Pulse **Enviar a Revisión**. La plantilla queda en **PENDING** hasta que Meta
   la apruebe (suele tardar minutos).
6. Use **Sincronizar** para traer desde Meta el estado actualizado y las
   plantillas creadas fuera del sistema.

Sobre cada fila del listado: **ver detalles** muestra el mensaje tal como quedó,
**probar envío** lo manda a un número escribiendo los valores a mano, **editar**
cambia el texto (vuelve a revisión de Meta) y **eliminar** la quita del sistema,
con la opción de borrarla también en Meta.

## Plantillas rápidas del sistema

| Plantilla | Adjunta PDF | Variables (en orden) | Quién la usa |
|-----------|-------------|----------------------|--------------|
| `factura_venta` | Sí | Cliente, Número, Total | Facturas de Venta, POS |
| `factura_por_cobrar` | Sí | Cliente, Valor pendiente, Número | Facturas de Venta |
| `proforma` | Sí | Cliente, Número de proforma, Total | Proformas |
| `link_pago_payphone` | No | Cliente, Monto, Referencia, Enlace | Facturas de Venta, POS |
| `link_pago_nuvei` | No | Cliente, Monto, Referencia, Enlace | Facturas de Venta |
| `cuenta_por_cobrar` | No | Cliente, Total por cobrar | Cuentas por Cobrar |
| `renovacion_suscripcion` | No | Cliente, Fecha de renovación | Suscripciones |
| `renovacion_firma_electronica` | No | Cliente, Fecha de vencimiento | Firmas Electrónicas |
| `retencion_compra` | Sí | Proveedor, Número, Valor retenido | Retenciones en Compras |
| `nota_credito` | Sí | Cliente, Número, Total | Notas de Crédito |
| `nota_debito` | Sí | Cliente, Número, Total | Notas de Débito |
| `guia_remision` | Sí | Destinatario, Número, Motivo | Guías de Remisión |
| `rol_pagos` | Sí | Empleado, Periodo, Valor a recibir | Roles de Pago |
| `descuento_empleado` | Sí | Empleado, Concepto, Valor | Novedades / Roles de Pago |
| `aviso_mensajes_pendientes` | No | Cantidad, Minutos | Chat de WhatsApp |

Cada plantilla rápida se ofrece **solo en su módulo**: las de link de pago no
aparecen al enviar una proforma ni una orden de taller, y la de proforma no
aparece en el modal de facturas.

### Enlaces de pago (Payphone y Nuvei)

Son dos plantillas equivalentes, una por pasarela, y funcionan igual: el módulo
genera el enlace de pago del documento y lo manda como texto en la variable
`{{4}}` (WhatsApp vuelve clicable la dirección, por eso la plantilla no lleva
botón). La diferencia está en el enlace:

- **Payphone**: es de un solo uso y caduca a los pocos minutos. Si el cliente lo
  deja pasar, hay que enviarle uno nuevo.
- **Nuvei**: se renueva solo cada vez que el cliente lo abre, así que no caduca
  por tiempo; deja de servir cuando el pago se registra.

Para usarlas, la empresa necesita además una **forma de cobro** de ese tipo
(Payphone o Nuvei) activa y configurada. Redacte el texto acorde a la pasarela:
no prometa una caducidad que no aplica.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo de plantilla | Sí | Rápida (del sistema) o libre (texto propio) |
| Plantilla del sistema | Sí, si es rápida | Cuál de las plantillas del catálogo va a crear |
| Nombre | Sí | Identificador en Meta. Solo minúsculas, números y guion bajo. En las rápidas viene fijado |
| Categoría | Sí | Marketing, Utilidad o Autenticación. Los avisos transaccionales son **Utilidad** |
| Idioma | Sí | Idioma con el que Meta registra la plantilla (normalmente `es`) |
| Tipo de cabecera | No | Ninguna, o Documento (PDF) cuando el mensaje adjunta un comprobante |
| PDF de ejemplo | Sí, si la cabecera es Documento | Muestra que Meta exige para aprobar |
| Cuerpo del mensaje | Sí | Texto con las variables entre llaves dobles |

## Permisos

- **Ver**: consultar el listado, ver detalles y probar envíos.
- **Crear**: enviar plantillas nuevas a revisión.
- **Actualizar**: editar el texto y sincronizar con Meta.
- **Eliminar**: quitar la plantilla del sistema (y opcionalmente de Meta).

Sin **acceso total**, el usuario ve solo las plantillas que él mismo registró.

## Reglas de negocio

- **Las variables van numeradas y consecutivas desde `{{1}}`**. Si el texto salta
  un número, el sistema no lo acepta.
- **Meta no permite que el mensaje empiece o termine con una variable**: siempre
  debe haber texto antes y después.
- En una **plantilla rápida** solo se pueden usar las variables de su ficha, y el
  nombre no se puede cambiar: los módulos rellenan las variables por posición, así
  que alterar el orden cambiaría lo que lee el cliente.
- **Editar el texto vuelve a mandar la plantilla a revisión**; mientras Meta no la
  apruebe otra vez, no se puede usar.
- Eliminar una plantilla es **eliminación lógica** en el sistema. Si además la
  borra en Meta, deja de existir para siempre y habrá que crearla de nuevo.

## Integraciones con otros módulos

- **Facturas de Venta**: envía la factura con el PDF adjunto y, con las plantillas
  de link de pago, el enlace de cobro por el saldo pendiente.
- **Proformas**: envía la proforma con su PDF.
- **POS / Caja**: envía la factura de la venta y el enlace de pago del carrito.
- **Taller**, **Cuentas por Cobrar**, **Suscripciones**, **Roles de Pago** y
  **Automatizaciones**: cada uno consume la plantilla que le corresponde.
- **Chat de WhatsApp**: todos los envíos quedan registrados en la conversación del
  cliente.

## Errores frecuentes

- **"Plantilla no válida o no aprobada"**: Meta aún no la aprobó o la rechazó.
  Sincronice para ver el estado real.
- **"La variable {{n}} no está permitida en esta plantilla rápida"**: está usando
  más variables de las que el módulo rellena. Quite las sobrantes.
- **"Debe configurar el App ID de Meta"**: falta ese dato en Configuración de
  WhatsApp y sin él no se pueden subir los PDF de ejemplo.
- **"No hay una forma de cobro de tipo Payphone/Nuvei activa"**: registre y active
  la forma de cobro antes de enviar enlaces de pago.
- **La plantilla no aparece en el módulo**: revise que esté **APPROVED** y que sea
  la plantilla que ese módulo espera (cada uno ofrece solo las suyas).

## Historial de cambios

- **1.0** — Versión inicial. Incluye las plantillas rápidas `proforma` y
  `link_pago_nuvei` (enlace de pago con Nuvei), además de las ya existentes.
