---
titulo: Comprobantes eliminados y números del SRI
resumen: Qué pasa con el número de una factura, nota de crédito, retención u otro comprobante electrónico que se elimina, cuándo se vuelve a usar y cómo el superadministrador lo restaura.
categoria: Ventas
tipo: concepto
visibilidad: todos
etiquetas: secuencial, numero saltado, se salto un numero, falta un numero, no sigue el secuencial, secuencial no consecutivo, secuencial inicial, numero omitido, eliminada, eliminado, documento eliminado, ver eliminadas, estado:eliminada, restaurar, recuperar documento eliminado, revivir factura, deshacer eliminar, no autorizada, no autorizado, devuelta, error 45, secuencial registrado, reusar numero, volver a usar numero, factura, nota de credito, nota de debito, retencion, guia de remision, liquidacion de compra, factura de reembolso, sri
version: 1.0
orden: 6
estado: activo
---

Esto aplica a los siete comprobantes que se envían a autorizar al SRI: **factura
de venta**, **nota de crédito**, **nota de débito**, **retención de compra**,
**guía de remisión**, **liquidación de compra** y **factura de reembolso**.

## Qué pasa con el número de un comprobante eliminado

El SRI no guarda el número suelto: guarda la **clave de acceso**, que se arma con
la fecha de emisión, el RUC, el ambiente, la serie, el número y un código. Por eso
lo que importa es lo **último** que respondió el SRI antes de eliminar el
comprobante:

| Último resultado del SRI | ¿Se vuelve a usar el número? |
|---|---|
| Nunca se envió | Sí: el siguiente comprobante nuevo lo toma. |
| **DEVUELTA** | Sí: el SRI no lo registró. |
| **NO AUTORIZADA** | Sí: el SRI acepta ese número con otra clave de acceso (otra fecha). |
| **RECIBIDA** o **EN PROCESAMIENTO** | No: el SRI todavía puede autorizarlo. Se salta. |
| **AUTORIZADA** | No: el número ya es de ese comprobante. Se salta. |

Un número que se salta, usado en un comprobante nuevo, sería rechazado con
*«ERROR 45 SECUENCIAL REGISTRADO»*. Por eso el sistema propone el siguiente libre.

El número de un comprobante nuevo sale del **secuencial inicial** configurado en
la empresa: desde ahí se toma el primer número libre (un hueco que dejó un
comprobante eliminado también cuenta como libre). Solo si no hay ninguno se usa
"el último + 1".

## El triángulo amarillo junto al Secuencial

Cuando el número de un comprobante nuevo **salta** uno o más números porque el SRI
los tiene recibidos o autorizados, junto al campo **Secuencial** aparece un
**triángulo amarillo** y el número se ve con borde amarillo. Al pulsarlo se ve qué
número se omitió, qué respondió el SRI y cuándo se eliminó el comprobante.

## Ver los comprobantes eliminados (superadministrador)

En el buscador del listado de cada uno de esos módulos, el superadministrador
escribe:

```
estado:eliminada
```

Aparecen los comprobantes eliminados del ambiente actual, atenuados y con el
número tachado, con dos etiquetas: **Eliminada** y lo que pasó con él en el SRI
(**No enviada al SRI**, **SRI: NO AUTORIZADA**, **SRI: RECIBIDA**, etc.). Se
combina con los demás filtros, por ejemplo `estado:eliminada serie:001-001`. Para
los demás usuarios este filtro no muestra nada.

## Restaurar un comprobante eliminado

Solo el **superadministrador** restaura. Se hace con un clic en la fila del
listado `estado:eliminada`, o con el botón **Restaurar** de la ventana del
triángulo amarillo. El comprobante vuelve como **borrador**, con su mismo número,
para corregirlo y reenviarlo al SRI. Al reenviarlo con la fecha de hoy la clave de
acceso cambia, y el SRI lo acepta con el mismo número cuando antes quedó NO
AUTORIZADO.

No se restaura si:

- No se eliminó en borrador (o, en retenciones, no autorizada).
- Su **período contable está cerrado**.
- Al eliminarlo se **revirtió inventario** (p. ej. una factura del POS o una nota
  de crédito que devolvió mercadería): hay que emitirlo de nuevo.
- Tiene un vínculo que el borrado deshizo y no se puede rehacer solo (p. ej. una
  factura generada desde Facturación de consignaciones o desde una orden Car-Wash).
  El mensaje dice cuál.
- Otro comprobante vigente ya usa ese número. Pasa con los que nunca llegaron al
  SRI o quedaron NO AUTORIZADOS: su número quedó libre y el siguiente comprobante
  nuevo lo tomó.

Lo que se anuló al eliminarlo (cobros, pagos) **no se recupera**: si hace falta,
se registra de nuevo. La restauración queda en el historial del sistema con la
acción *RESTAURAR*.
