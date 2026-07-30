---
titulo: Plantillas PDF
resumen: Diseño de los documentos impresos: facturas, comprobantes, cheques.
categoria: Configuración de empresa
ruta_modulo: modulos/plantillas-pdf
tipo: modulo
visibilidad: admin
etiquetas: plantillas pdf, diseno de factura, formato de impresion, cheques, calibrar, membrete, personalizar documento, banco, plantilla por banco, plantilla original, tipos de documento, diseñador
version: 1.2
orden: 30
estado: activo
---

Este módulo controla **cómo se ven los documentos impresos** de la empresa: 17
tipos de documento en total (factura, cheque, comprobantes de ingreso/egreso,
retenciones, guía de remisión, y más — ver la lista completa más abajo).

## Para qué se usa

- Ajustar el diseño de la factura (o cualquier otro documento) al papel
  membretado de la empresa.
- **Calibrar la impresión de cheques**, que es el caso más delicado: los campos
  tienen que caer exactamente en las casillas preimpresas del talonario.
- Personalizar comprobantes internos (egresos, ingresos, consignaciones,
  retenciones…) que antes solo tenían un diseño fijo de fábrica.

## Tipos de documento soportados

| Documento | Tiene plantilla original | Notas |
|---|---|---|
| Factura de Venta | Sí | |
| Nota de Crédito | Sí | |
| Nota de Débito | No | Sin flujo de emisión propio todavía; el tipo existe pero no se usa. |
| Guía de Remisión | Sí | |
| Liquidación de Compra | Sí | |
| Compras (documento recibido) | Sí | Reconstruye el PDF a partir del XML del proveedor; la plantilla cambia la presentación, no los datos. |
| Retención en Compras | Sí | |
| Retención en Ventas | Sí | Solo aplica a retenciones **manuales**; las que tienen XML autorizado del SRI siempre se imprimen desde ese XML. |
| Recibo de Venta | Sí | |
| Egreso | Sí | Incluye tabla del asiento contable. |
| Ingreso | Sí | Incluye tabla del asiento contable. |
| Traspaso | Sí | Incluye tabla del asiento contable. |
| Proforma | Sí | |
| Retorno de Consignación | Sí | |
| Consignación en Ventas | Sí | |
| Facturación de Consignación | Sí | |
| Cambio de Productos | Sí | Dos tablas (lo que devuelve / lo que entrega a cambio). |
| Cheque | Sí | Única con plantilla **por banco** además de por empresa (ver más abajo). |

Si una empresa no tiene ninguna plantilla activa para un tipo, el sistema usa
el diseño de fábrica de siempre (nada cambia hasta que se crea y activa una
plantilla propia).

## Calibrar un cheque

La calibración es un ajuste de milímetros: se imprime una prueba sobre un cheque
en blanco (o una fotocopia), se mide la desviación y se corrigen las posiciones.
Repetir hasta que cuadre.

Conviene hacerlo con la **misma impresora y el mismo talonario** que se usarán
después: un cambio de impresora suele obligar a recalibrar.

## Partir de la plantilla original o en blanco

Al pulsar **Nueva plantilla**, después de elegir el tipo de documento el
sistema pregunta de dónde partir:

- **Plantilla original del sistema**: la nueva plantilla nace con los mismos
  campos y el mismo orden que el diseño de fábrica de ese tipo de documento,
  ya colocados en la hoja. Es la opción recomendada: se ajustan posiciones en
  vez de armar el documento desde cero.
- **En blanco**: la plantilla nace vacía (hoja A4 en blanco), para armar el
  diseño completamente a gusto.

Si el tipo de documento todavía no tiene una plantilla original registrada
(hoy solo Nota de Débito, que no tiene flujo de emisión), la opción "Plantilla
original" queda deshabilitada y se avisa que se creará en blanco.

Importante: la plantilla original es una **aproximación** del diseño de
fábrica (mismos campos, mismo orden), no una copia exacta pixel a pixel —
varios diseños de fábrica ajustan alturas según el contenido, algo que una
posición fija no puede replicar del todo. Sirve como punto de partida para
ajustar, no como un clon perfecto.

## Por empresa

Las plantillas son de cada empresa, así que cada una puede tener su propio
diseño sin afectar a las demás.

## Plantilla de cheque por banco

Cada banco imprime su chequera con un formato distinto, así que el cheque es la
única plantilla que además se puede tener **una por banco** (las demás siguen
siendo una sola por empresa). Al crear o editar una plantilla de tipo **Cheque**
aparece el selector **Banco**:

- Con un banco elegido, esa plantilla es la que se usa para los cheques de ese
  banco (independientemente de si hay otra activa para otro banco).
- En blanco ("Genérica de la empresa"), es la que se usa cuando un banco no
  tiene su propia plantilla.

Activar una plantilla de un banco solo desactiva la anterior **de ese mismo
banco**; no afecta a las de otros bancos ni a la genérica.

La forma más rápida de llegar aquí es desde **Egresos**: el icono de engranaje
junto a "Imprimir cheque" (o junto al selector de Cuenta/Banco en el modal de
impresión en lote) crea automáticamente la plantilla del banco correspondiente
si todavía no existe (con una hoja A4 vertical y posiciones iniciales) y abre
este diseñador directamente. Ver [Egresos](modulos/egresos).

## Errores frecuentes

- **El cheque se imprime desplazado**: recalibre; compruebe también que la
  impresora no esté escalando la página (debe imprimir al 100%, sin "ajustar al
  papel").
- **La hoja sale en horizontal y el talonario es una hoja vertical**: en
  Página, cambie Orientación a "Vertical" y Formato a "A4" (o el que use su
  impresora), y reubique los campos donde caen en esa hoja. Las plantillas de
  cheque nuevas ya se crean así por defecto.
- **El logo no aparece**: revise que esté cargado en los datos de la empresa.
- **El documento sale con el diseño antiguo**: compruebe que la plantilla editada
  sea la de esa empresa y ese tipo de documento.

## Historial de cambios

- **1.2** — Se amplió de 6 a 17 tipos de documento soportados (retenciones,
  recibo de venta, egreso, ingreso, traspaso, proforma, retorno de
  consignación, consignación en ventas, facturación de consignación, cambio de
  productos, compras). Nueva opción al crear una plantilla: partir del diseño
  original del sistema o en blanco.
- **1.1** — Plantilla de cheque por banco: selector Banco al crear/editar,
  activación con alcance por banco, y creación automática desde Egresos.
- **1.0** — Versión inicial.
