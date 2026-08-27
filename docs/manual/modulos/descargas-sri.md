---
titulo: Descargas del SRI
resumen: Descarga de los comprobantes electrónicos que otros emitieron a nombre de la empresa.
categoria: Compras
ruta_modulo: modulos/descargas-sri
tipo: modulo
visibilidad: todos
etiquetas: descargas sri, comprobantes recibidos, xml, facturas de proveedores, importar compras, portal sri
version: 1.1
orden: 50
estado: activo
---

Este módulo trae automáticamente los **comprobantes electrónicos que otros
emitieron a nombre de la empresa**: facturas de proveedores, notas de crédito,
retenciones que le practicaron. Evita capturarlas a mano.

## Qué hace

Se conecta al portal del SRI con las credenciales de la empresa y descarga los
comprobantes recibidos del periodo indicado. De cada uno guarda el XML íntegro
tal como lo entrega el SRI.

Desde ahí, los comprobantes se pueden registrar como compras sin volver a
escribir nada.

## Duplicados

El sistema evita registrar dos veces el mismo comprobante, aunque se descargue
varias veces o dos personas lo hagan a la vez. Si un documento ya existe, no se
duplica.

## Después de descargar

Descargar **no es lo mismo que registrar la compra**. El comprobante queda
disponible para convertirlo en compra; solo entonces afecta a cuentas por pagar y
puede generar entrada de inventario.

Recuerde que las líneas traen los **códigos del proveedor**: para que la
mercadería entre al inventario hay que vincularlas con productos de su catálogo.

## Permisos

| Permiso | Qué habilita |
|---|---|
| Ver | Abrir el módulo, ver la configuración, el historial y los documentos ignorados |
| Crear | Descargar y registrar comprobantes, procesar claves, TXT y XML, e ignorar documentos |
| Modificar | Guardar la configuración de descarga |
| Eliminar | Quitar documentos de la lista de ignorados |

La extensión de Chrome no usa estos permisos: se identifica con el token
personal del usuario, así que sigue funcionando igual.

## Errores frecuentes

- **No descarga nada**: revise las credenciales del SRI de la empresa y el rango
  de fechas.
- **"No tiene permiso para esta acción"**: pida que le asignen el submódulo en
  *Permisos de módulos*. Antes el módulo se abría sin permiso asignado; ahora lo
  exige, igual que los demás.
- **El comprobante está descargado pero no aparece en compras**: falta
  registrarlo como compra.
- **La descarga tarda**: el portal del SRI impone sus propios tiempos; para
  periodos largos conviene descargar por tramos.
- **"XML obtenido pero error en registro: el código del documento de sustento es
  obligatorio"**: ocurría al registrar retenciones cuyo XML no incluye el código
  del documento de sustento (el SRI lo permite: en la versión 1.0.0 del
  comprobante de retención ese dato es opcional). Ya no bloquea: si el XML no lo
  trae, el sistema toma el código presente en el propio comprobante y, si no hay
  ninguno, asume **01 – Factura**. Puede corregirlo después desde la retención.

## Historial de cambios

- **1.3** — Las facturas de **servicios básicos** (luz, agua) cargadas desde el
  SRI reconocen los **valores de terceros** (contribución bomberos, tasa de
  recolección de basura) que el emisor declara en la información adicional. Se
  totalizan aparte del importe de la factura y se suman al saldo por pagar. Ver
  *Compras → Planillas de luz y agua: valores de terceros*.
- **1.2** — El registro automático de retenciones ya no falla cuando el XML del
  SRI omite el código del documento de sustento (dato opcional en la versión
  1.0.0 del comprobante): se asume el del propio comprobante o **01 – Factura**.
- **1.1** — El módulo pasa a exigir el permiso del submódulo (antes entraba
  cualquier usuario con sesión). Nueva sección *Permisos*.
- **1.0** — Versión inicial.
