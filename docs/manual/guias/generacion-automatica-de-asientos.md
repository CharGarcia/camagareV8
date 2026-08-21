---
titulo: Generación automática de asientos contables
resumen: Al abrir un módulo, el sistema genera solo y en silencio los asientos contables que le falten a ese módulo.
categoria: Contabilidad
tipo: guia
visibilidad: todos
etiquetas: asientos automáticos, generar asientos, contabilidad automática, asientos pendientes, asientos que faltan, contabilizar documentos, no se generó el asiento, factura sin asiento, sincronizar contabilidad, asientos en segundo plano
version: 1.0
orden: 30
estado: activo
---

Cuando usted abre **Facturas de Venta**, **Compras**, **Ingresos** o cualquier
otro módulo que lleve contabilidad, el sistema revisa por su cuenta si a esos
documentos les falta el asiento contable y lo genera mientras usted trabaja. No
aparece ningún mensaje, ni una barra de progreso, ni hay que hacer clic en nada:
es un trabajo de fondo. Esta guía explica cuándo ocurre, cuándo **no** ocurre y
dónde mirar si un documento sigue sin asiento.

## Qué es y para qué sirve

Cada documento que se emite genera su asiento contable en el momento. Pero hay
situaciones en las que ese asiento queda pendiente: la empresa todavía no tenía
configuradas las cuentas, el documento se cargó desde una migración, se corrigió
un rol de pago después de contabilizarlo, o el costo de una venta se calculó más
tarde.

Antes, esos documentos se quedaban esperando a que alguien entrara al módulo de
Asientos Contables y aceptara generarlos. Ahora, **cada módulo se ocupa de lo
suyo**: al abrirlo, se generan los asientos que le faltan a ese módulo y solo a
ese. Entrar a Compras no procesa las facturas de venta.

## Requisitos previos

Solo uno, y es el que decide todo: la empresa activa debe tener **configuración
contable** para ese módulo. Es decir, al menos una cuenta asignada en
**Configuración Contable** (o en Asientos Programados, según el módulo).

Si la empresa no lleva contabilidad, o todavía no configuró las cuentas de ese
módulo, no se genera nada y no ocurre absolutamente nada raro: el módulo abre y
funciona igual que siempre.

## Cómo se usa

No se usa: funciona solo. Lo único que hay que hacer es abrir el módulo.

1. Entre al módulo (por ejemplo, Facturas de Venta).
2. El listado carga normalmente y usted trabaja como siempre.
3. En segundo plano, el sistema busca documentos sin asiento y los contabiliza.
4. Los asientos nuevos aparecen en el Libro Diario y en los Estados Financieros.

Si quiere ver el resultado, revise **Asientos Contables** o **Mayores**.

## Módulos que generan asientos automáticamente

| Módulo | Qué contabiliza |
|--------|-----------------|
| Facturas de Venta | La venta, sus impuestos y el costo |
| Recibos de Venta | Igual que la factura, con su propio catálogo de cuentas |
| Notas de Crédito | La reversión de la venta |
| Retenciones en Ventas | La retención que le hizo el cliente |
| Facturas de Compra | La compra, el IVA y la cuenta por pagar |
| Liquidaciones de Compra | Igual que una compra |
| Retenciones en Compras | La retención efectuada al proveedor |
| Importaciones | Inventario nacionalizado, IVA e ISD |
| Ingresos | El cobro: concepto y forma de cobro |
| Egresos | El pago: concepto y forma de pago |
| Consignaciones en Ventas | Reclasificación de inventario a mercadería en consignación |
| Retornos de Consignaciones | La devolución del cliente |
| Cambios de Productos | El movimiento de inventario del cambio |
| Facturación de Consignaciones | El reingreso al facturar |
| Roles de Pago | El rol mensual (base devengado) |

## Reglas de negocio

- **Sin configuración contable, no se hace nada.** Es lo primero que se revisa.
- **Como máximo 50 documentos por vez**, del más antiguo al más nuevo. Si hay
  más pendientes, se continúa la próxima vez que se abra el módulo. Esto evita
  que abrir un módulo con miles de documentos atrasados vuelva lento el sistema.
- **Nunca se procesa dos veces lo mismo.** Si dos personas abren la misma
  pantalla al mismo tiempo, solo una genera; la otra sigue de largo.
- **Un documento que no se puede contabilizar no se reintenta indefinidamente.**
  Si falta una cuenta, o el período contable está cerrado, el documento queda
  anotado y se lo salta. **Se vuelve a intentar automáticamente en cuanto usted
  corrija la configuración contable** del módulo — no hay que pedirlo ni volver
  a nada.
- **El asiento queda a nombre del usuario que abrió el módulo**, que es quien
  disparó la generación.
- **Un documento anulado no se contabiliza nunca**, en ningún módulo. Tampoco los
  borradores: solo se contabiliza el documento vigente ya emitido o autorizado.
- **Los períodos contables cerrados se respetan**: un documento con fecha dentro
  de un período cerrado no se contabiliza.
- **Nada de esto reemplaza la revisión.** El sistema genera el asiento según las
  cuentas configuradas; si la configuración está mal, el asiento saldrá mal.

## Permisos

Se aplica el permiso del **módulo que usted abrió**, no el de contabilidad:
quien puede ver Facturas de Venta dispara la generación de los asientos de
facturas, aunque no tenga acceso al módulo de Asientos Contables. Quien no tiene
permiso para ver el módulo tampoco genera nada, porque ni siquiera puede abrirlo.

## Integraciones con otros módulos

- **Asientos Contables, Mayores, Estados Financieros y Balance de
  Comprobación** conservan su aviso de siempre: al entrar, preguntan si desea
  generar los asientos pendientes de **todos** los módulos y muestran el detalle
  de qué cuenta falta en cada caso. Ese aviso sigue siendo el lugar donde ver los
  problemas: la generación automática es silenciosa a propósito, pero no esconde
  nada.
- **Configuración Contable**: es donde se corrigen las cuentas. Cada cambio que
  haga ahí habilita el reintento de los documentos que habían fallado.
- **Períodos Contables**: define hasta qué fecha se puede contabilizar.

## Errores frecuentes

- **«Abrí el módulo y mi documento sigue sin asiento»**: casi siempre falta una
  cuenta. Entre a **Asientos Contables**: el aviso de asientos pendientes dice
  exactamente qué cuenta falta y en qué documentos. Corrija la cuenta en
  Configuración Contable y vuelva a abrir el módulo.
- **«Tengo cientos de documentos atrasados y solo se generaron algunos»**: es lo
  esperado, se procesan de a 50. Vuelva a entrar al módulo (o use el botón de
  generar de Asientos Contables, que los procesa todos con barra de progreso).
- **«No se generó nada y la empresa sí lleva contabilidad»**: revise que el
  módulo tenga sus cuentas configuradas. Sin ninguna cuenta asignada, el sistema
  entiende que esa empresa no contabiliza ese módulo.
- **«El documento es de un mes ya cerrado»**: no se contabilizará mientras el
  período siga cerrado. Reabra el período si corresponde.

## Historial de cambios

- **1.0** — Versión inicial: generación automática y silenciosa por módulo, con
  tope de 50 documentos por pasada y reintento de los fallidos al cambiar la
  configuración contable.
