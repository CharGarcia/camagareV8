---
titulo: Transferencias de Inventario
resumen: Mueve mercadería de una bodega a otra —incluso a la bodega de otro establecimiento del mismo RUC— en un solo paso y con trazabilidad de lote y serie.
categoria: Inventarios
ruta_modulo: modulos/transferencias-inventario
tipo: modulo
visibilidad: todos
etiquetas: transferencia de inventario, traslado de mercaderia, mover stock, cambiar de bodega, pasar productos de una bodega a otra, entre bodegas, entre establecimientos, entre sucursales, entre locales, traspaso de inventario, guia de remision, acta de entrega, kardex
version: 1.0
orden: 0
estado: activo
---

Sirve para **mover productos de una bodega a otra** sin tener que registrar una
salida y una entrada por separado en el Kardex. Si las dos bodegas están en
**establecimientos distintos** del mismo RUC, la transferencia lo detecta sola y
permite generar la **guía de remisión** con los mismos productos.

## Qué es y para qué sirve

Cada transferencia es un documento con su número (`TRF-000001`), su fecha, la
bodega de origen, la de destino, los responsables de entregar y recibir, y el
detalle de productos. Al guardarla, el sistema registra **en el mismo instante**
la salida en la bodega de origen y la entrada en la de destino: no hay estado
intermedio ni mercadería "en tránsito".

El lote, la fecha de caducidad y la serie/NUP **viajan con el producto**, así que
la trazabilidad no se pierde al cambiar de bodega.

## Requisitos previos

- Empresa activa con al menos **dos bodegas** (Inventarios → Bodegas).
- Para que una transferencia se considere **entre establecimientos**, cada bodega
  debe tener asignado su establecimiento en **Inventarios → Bodegas → campo
  Establecimiento**. Si las bodegas no tienen establecimiento, o tienen el mismo,
  la transferencia es interna y no ofrece guía de remisión.
- El usuario debe tener **acceso a las dos bodegas** (origen y destino).
- Los productos deben ser **inventariables** y tener existencias en la bodega de
  origen.

## Cómo se usa

1. Entre a **Inventarios → Transferencias de Inventario** y pulse **Nueva transferencia**.
2. Elija la **fecha**, la **bodega de origen** y la **bodega de destino**. Si las
   bodegas pertenecen a locales distintos aparece la etiqueta *Entre establecimientos*.
3. Escriba quién **entrega** y quién **recibe** (opcional, sale impreso en el acta).
4. Busque el producto por código o nombre en **Agregar producto**: la lista muestra
   el stock disponible en la bodega de origen.
5. Por cada línea, elija el **lote** (si el producto maneja lotes) y la **serie/NUP**
   (si maneja series), y escriba la **cantidad**. El sistema no deja pasar de lo
   disponible.
6. Pulse **Registrar transferencia**. El stock se mueve en ese momento.
7. Desde el documento ya guardado puede **imprimir el acta** (PDF con las firmas de
   entrega y recepción) y, si cruza establecimientos, **Generar guía de remisión**.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fecha | Sí | Fecha del traslado. No puede ser posterior a hoy. |
| Bodega de origen | Sí | De dónde sale la mercadería. Define el stock, los lotes y el costo. |
| Bodega de destino | Sí | A dónde entra. Debe ser distinta de la de origen. |
| Entrega (responsable) | No | Quién despacha físicamente. Aparece en el acta. |
| Recibe (responsable) | No | Quién recibe físicamente. Aparece en el acta. |
| Observaciones | No | Motivo del traslado. |
| Producto | Sí | Producto inventariable con existencias en la bodega de origen. |
| Lote | Depende | Obligatorio si quiere descontar de un lote concreto; muestra el saldo de cada lote. |
| Caducidad | No | Se llena sola con la del lote; viaja a la bodega de destino. |
| Serie / NUP | Depende | Para artículos serializados. Una serie = una unidad (cantidad 1). |
| Cantidad | Sí | Unidades a mover. No puede superar lo disponible. |
| Costo unitario | — | **No se digita**: lo calcula el sistema con el costo de la bodega de origen. |

## Permisos

- **Ver** (r): entrar, listar transferencias y abrir el detalle.
- **Crear** (w): registrar transferencias nuevas.
- **Actualizar** (u): anular una transferencia registrada.
- **Eliminar** (d): eliminar del listado una transferencia **ya anulada**.
- **Acceso total** (t): con este permiso ve las transferencias de toda la empresa;
  sin él solo ve las que registró el propio usuario.

Además, el usuario solo puede transferir entre bodegas a las que tenga acceso
(Inventarios → Bodegas → pestaña Accesos).

## Reglas de negocio

- **Un solo paso**: la salida de origen y la entrada de destino se graban juntas.
  Si una falla, no se graba ninguna.
- **No se edita**: una transferencia registrada no se modifica. Si algo quedó mal,
  se **anula** y se registra una nueva.
- **Anular devuelve el stock**: al anular se deshacen los dos movimientos del
  Kardex y el stock vuelve a la bodega de origen. Si la mercadería que entró al
  destino **ya se vendió o consumió**, el sistema **no deja anular** e indica que
  primero hay que reversar los documentos que la usaron.
- **Costo**: sale al costo promedio de la bodega de origen (o del lote elegido);
  si esa bodega no tiene historial de costos, se usa el costo del producto. Así el
  inventario no cambia de valor por moverse de sitio.
- **Sin asiento contable**: la mercadería no cambia de dueño ni de valor, solo de
  ubicación dentro de la misma empresa.
- **Stock exacto**: no se puede transferir más de lo disponible, ni de un lote ni
  de una serie. La validación se hace con el saldo real del Kardex y bloqueando el
  producto/bodega, de modo que dos usuarios transfiriendo el mismo producto a la
  vez no puedan dejar el stock en negativo.
- **Fecha**: se admite fecha anterior a hoy (para regularizar traslados ya hechos),
  pero nunca futura.

## Integraciones con otros módulos

- **Kardex / Movimientos de Inventario**: cada línea genera dos movimientos con
  tipo `transferencia` y referencia al documento. Se ven en Inventarios → Kardex.
- **Stock por bodega**: actualiza `productos_bodegas`, que es lo que consultan
  facturación, POS y los reportes de existencias.
- **Bodegas**: de ahí sale el establecimiento de cada bodega, que es lo que define
  si una transferencia cruza de un local a otro.
- **Guías de Remisión**: en transferencias entre establecimientos, el botón
  *Guía de remisión* abre ese módulo con la fecha, el motivo, las direcciones de
  partida y destino y los productos ya cargados. El destinatario, el transportista
  y la placa se completan ahí, y la guía se emite al SRI desde su propio módulo.
- **Reporte de Inventarios**: las transferencias aparecen como movimientos y
  cuentan en las entradas/salidas por bodega.

## Errores frecuentes

- **«Stock insuficiente en la bodega de origen»**: el saldo real del Kardex es
  menor que lo que se quiere mover. Puede que otro usuario haya facturado ese
  producto mientras el modal estaba abierto; recargue y vuelva a intentar.
- **«La bodega de origen y la de destino no pueden ser la misma»**: elija bodegas
  distintas; para corregir cantidades dentro de una misma bodega use un ajuste en
  Movimientos de Inventario.
- **«No tiene acceso a la bodega…»**: pida que le habiliten esa bodega en
  Inventarios → Bodegas → Accesos.
- **No aparece el botón de guía de remisión**: las dos bodegas están en el mismo
  establecimiento, o alguna no tiene establecimiento asignado. Revise el campo
  *Establecimiento* de cada bodega.
- **«No se puede anular: la mercadería que entró en la bodega de destino ya fue
  utilizada»**: reverse primero las facturas, consumos o transferencias
  posteriores que usaron esa mercadería.
- **Una serie no aparece en la lista**: esa serie ya no tiene saldo en la bodega de
  origen (se vendió o se transfirió antes).

## Historial de cambios

- **1.0** — Versión inicial: transferencias entre bodegas y entre establecimientos
  del mismo RUC, con lote/caducidad/serie, costo automático del origen, acta en
  PDF, anulación con reverso de stock y generación opcional de guía de remisión.
  Se agrega el campo **Establecimiento** al módulo de Bodegas.
