---
titulo: Retenciones de compra
resumen: Comprobantes de retención que la empresa emite a sus proveedores y envía al SRI.
categoria: Compras
ruta_modulo: modulos/retenciones_compras
tipo: modulo
visibilidad: todos
etiquetas: retencion, retenciones, comprobante de retencion, proveedor, iva, renta, sustento tributario, sri
version: 1.4
orden: 30
estado: activo
---

Cuando la empresa está obligada a retener, al recibir una factura de compra debe
emitir un **comprobante de retención** al proveedor y enviarlo al SRI. Este
módulo gestiona esos comprobantes.

La retención siempre se apoya en un **documento de sustento**: la factura de
compra que la origina.

## Cómo se emite

Lo habitual es generarla desde la propia compra: así el documento de sustento y
los porcentajes del proveedor vienen ya cargados.

1. Abra la compra y genere la retención.
2. Revise el **proveedor** y la **fecha de emisión**.
3. Compruebe el **documento de sustento**: tipo, número y fecha de emisión.
4. Revise los porcentajes de IVA y de renta.
5. Guarde y envíe al SRI.

## Buscar en el listado

El buscador acepta texto libre —número de la retención, número del documento de
sustento, nombre o RUC del proveedor y período fiscal— y filtros `clave:valor`:

| Filtro | Ejemplo | Qué hace |
|--------|---------|----------|
| `proveedor:` | `proveedor:"Corporación Favorita"` | Por nombre del proveedor |
| `ruc:` | `ruc:1790016919001` | Por RUC o cédula del proveedor |
| `numero:` | `numero:298` | Por número de la retención |
| `secuencial:` | `secuencial:298` | Igual, sin escribir los ceros a la izquierda |
| `doc_sustento:` | `doc_sustento:001-001-000000123` | Por el documento retenido |
| `serie:` | `serie:001-001` | Establecimiento y punto de emisión |
| `estado:` | `estado:borrador` | Borrador, autorizada, anulada… |
| `periodo:` | `periodo:07/2026` | Por período fiscal |
| `fecha:` | `fecha:2026-08` · `fecha:>=2026-01-01` | Por fecha de emisión, con rangos |
| `monto:` / `total:` | `monto:>=100` · `monto:100..500` | Por el total retenido |
| `renta:` `iva:` `isd:` | `iva:>0` | Por el importe retenido de cada impuesto |
| `clave_acceso:` | `clave_acceso:2608…` | Por clave de acceso |
| `usuario:` | `usuario:ana` | Quién registró la retención |

Se pueden combinar (`proveedor:favorita estado:borrador`) y negar anteponiendo un
guion (`-estado:anulada`).

## Cómo buscar el código de retención

En las líneas de la retención puede buscar el código del catálogo del SRI desde
**cualquiera de las tres columnas** —Código, Concepto o **% Ret.**—: escriba en
la que le resulte más natural y aparecerá la lista de coincidencias.

- Desde **Código** o **Concepto** se busca en todas las columnas del catálogo a
  la vez: código, concepto, porcentaje, impuesto y código del anexo. Así, escribir
  `1.75` en el concepto encuentra los códigos que retienen ese porcentaje, y
  `iva 100` encuentra la retención del 100 % de IVA.
- Desde **% Ret.** se busca solo por porcentaje, para que teclear `2` no le
  devuelva todos los conceptos que contienen un 2. Puede escribir `1,75` o
  `1.75 %`: el signo y la coma decimal se interpretan igual.
- Se pueden escribir **varias palabras** en cualquier orden (`renta honorarios`)
  y no importan mayúsculas ni tildes.
- Elija una opción de la lista para que se completen el código, el concepto, el
  impuesto y el porcentaje. Con **Esc** se cierra la lista sin tocar lo escrito.

Solo se listan los códigos **vigentes a la fecha de emisión** de la retención. Si
alguno coincide pero está fuera de vigencia, la lista lo avisa al pie en lugar de
callarlo: es la causa habitual de "el código existe pero no me aparece". La
vigencia (Desde / Hasta) se revisa en **Configuración → Retenciones SRI**.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Proveedor | Obligatorio |
| Fecha de emisión | Obligatoria |
| Tipo de documento de sustento | Obligatorio, y debe ser uno de los códigos válidos del SRI |
| Número del documento de sustento | Obligatorio |
| Fecha de emisión del documento de sustento | Obligatoria |

Los porcentajes se proponen desde la ficha del proveedor, pero se pueden cambiar
en cada retención.

## Una sola retención por documento y proveedor

No se puede emitir dos veces la retención del mismo documento **al mismo
proveedor**: el sistema lo bloquea al guardar e indica cuál es la retención que ya
lo cubre (su número, su fecha y su estado), para que pueda encontrarla.

Lo que identifica al documento es la combinación **proveedor + tipo de documento +
número**. Dos proveedores distintos pueden entregarle facturas con el mismo número
—cada uno numera por su cuenta— y ambas se pueden retener sin problema.

Detalles que conviene conocer:

- Un **borrador** ya reserva el documento. Si le aparece el bloqueo y no encuentra
  la retención, búsquela por el número del documento en el listado: puede estar sin
  emitir, o haberla creado otro usuario.
- Las retenciones **eliminadas** o **anuladas** no bloquean: el documento vuelve a
  quedar libre.
- Las retenciones de **otro ambiente** (las de pruebas cuando la empresa ya está en
  producción) tampoco bloquean, igual que no aparecen en el listado.
- La regla la sostiene también la base de datos, así que dos personas guardando la
  misma retención a la vez no pueden colar un duplicado: la segunda recibe el mismo
  aviso.

## Relación con la compra

Una compra que ya tiene retención **no se puede eliminar**: primero hay que
eliminar la retención. Es una protección deliberada, porque la retención declara
al SRI una compra que dejaría de existir.

## Documentos del módulo

Desde la retención guardada están disponibles el **PDF** del comprobante, su
**Excel**, su **XML** y el envío por **correo**, en la barra de acciones al
inicio del formulario.

## Errores frecuentes

- **"El tipo de documento de sustento no es válido"**: use uno de los códigos
  admitidos por el SRI.
- **Los porcentajes salen equivocados**: revise las retenciones predeterminadas
  en la ficha del proveedor.
- **No puedo eliminar la compra**: elimine antes su retención.
- **"El documento de sustento ... ya está retenido en la retención ..."**: ese
  proveedor ya tiene una retención viva sobre ese mismo documento. El mensaje dice
  cuál es; si es un borrador que no sirve, elimínelo y vuelva a intentar. Recuerde
  que dos proveedores distintos **sí** pueden tener el mismo número de factura.
- **No aparece un código de retención que sí existe**: casi siempre es la
  **vigencia**. Solo se listan los códigos vigentes a la fecha de emisión de la
  retención; la lista avisa cuántos quedaron fuera por ese motivo. Corrija la
  fecha de emisión o la vigencia del código en Configuración → Retenciones SRI.

## Historial de cambios

- **1.4** — Corregido el buscador del listado: los filtros `monto:`, `total:`, `renta:`,
  `iva:` e `isd:` apuntaban a columnas que no existen en la tabla y rompían la búsqueda
  con un error de base de datos. Ahora `monto:` y `total:` usan el total retenido, y
  `renta:`, `iva:` e `isd:` se calculan desde el detalle de la retención.
- **1.3** — La unicidad del documento de sustento ahora considera al **proveedor**:
  antes bastaba con que otro proveedor tuviera una factura con el mismo número para
  que el sistema bloqueara la retención. Se aplica también al editar un borrador, no
  bloquean ya las retenciones de otro ambiente, y el mensaje dice cuál es la retención
  que ocupa el documento. La regla queda respaldada además por un índice único en la
  base, para que dos guardados simultáneos tampoco puedan duplicarla.
- **1.2** — El código de retención se puede buscar desde las columnas Código,
  Concepto o % Ret., y la búsqueda cubre todas las columnas del catálogo. La lista
  avisa cuando hay códigos que coinciden pero no están vigentes a la fecha de emisión.
- **1.1** — Nuevo botón Excel en el documento de la retención (junto a PDF y XML).
- **1.0** — Versión inicial.
