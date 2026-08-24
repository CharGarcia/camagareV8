---
titulo: Carga de Facturas
resumen: Crea varias facturas de venta a la vez desde un archivo Excel, en estado borrador.
categoria: Ventas
ruta_modulo: modulos/carga-facturas
tipo: modulo
visibilidad: todos
etiquetas: carga masiva facturas, importar facturas excel, subir facturas, facturar en lote, plantilla facturas, cargar ventas desde excel, migrar facturas, xlsx facturas
version: 1.0
orden: 0
estado: activo
---

Cuando hay que emitir muchas facturas iguales —el corte mensual de un servicio,
las ventas de una jornada que se llevaron en papel, la carga inicial al entrar al
sistema—, hacerlas una por una en **Facturas de Venta** cuesta horas. Este módulo
las crea todas de golpe a partir de un Excel, y las deja en **borrador** para que
usted las revise antes de emitirlas.

## Qué es y para qué sirve

Toma un archivo Excel con una fila por factura y sus líneas de detalle, y crea
las facturas de venta correspondientes en la empresa activa. Cada factura se crea
exactamente igual que si la hubiera escrito en la pantalla de Facturas de Venta:
con su cliente, su punto de emisión, su secuencial, su IVA, su forma de pago, su
movimiento de inventario y su clave de acceso.

Dos cosas que **no** hace, a propósito:

- **No envía nada al SRI.** Las facturas quedan en borrador. La emisión se hace
  desde Facturas de Venta, documento por documento o como usted acostumbre.
- **No modifica ni elimina** facturas que ya existan. Solo crea.

## Requisitos previos

- Una **empresa activa** y permiso de creación en este módulo.
- Al menos un **punto de emisión** con el secuencial de *Facturas de venta*
  configurado. Los puntos que no lo tengan no aparecen en la plantilla y el
  sistema rechaza las filas que los usen.
- Los **productos con existencias** que vaya a facturar deben existir antes en el
  catálogo, con su stock ingresado (módulos **Productos** / **Carga de Productos**
  y **Cargas de Inventario**). Un código que no exista se crea aquí mismo, pero
  **sin existencias**.
- Si va a facturar a **Consumidor Final**, ese cliente debe estar registrado una
  vez en el módulo **Clientes**.

## Cómo se usa

1. **Descargue la plantilla.** Las hojas donde usted escribe vienen vacías; las
   hojas que empiezan con `Ref_` traen lo que no se puede adivinar: tarifas de
   IVA, puntos de emisión, bodegas y vendedores. Consúltelas, no las modifique.
   Clientes y productos no vienen en el libro: se escribe la identificación o el
   código y el sistema los busca en la base de datos.
2. **Llene la hoja `Facturas`**: una fila por factura. La columna `ID_FACTURA` es
   un identificador que usted inventa (`F1`, `F2`, `F3`…) y que solo sirve para
   enlazar la cabecera con sus líneas. **No es el número de la factura.**
3. **Llene la hoja `Detalles`**: una fila por producto o servicio, repitiendo el
   `ID_FACTURA` de la factura a la que pertenece.
4. **Opcional**: use la hoja `Info_Adicional` para los campos adicionales del
   documento (orden de compra, contrato, etc.).
5. **Suba el archivo y pulse "Revisar archivo".** No se crea nada todavía: el
   sistema revisa todo el archivo y le muestra qué facturas se van a crear, por
   cuánto, y qué filas tienen problemas.
6. **Pulse "Crear facturas"** cuando el informe le cuadre. Al terminar verá el
   número asignado a cada `ID_FACTURA`.

## Campos del formulario

### Hoja `Facturas` (una fila por factura)

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| ID_FACTURA | Sí | Identificador que usted inventa para enlazar la factura con sus líneas. Único dentro del archivo. No es el número de la factura. |
| FECHA_EMISION | Sí | Fecha del documento. No puede ser posterior a hoy. |
| IDENTIFICACION_CLIENTE | Sí | Cédula, RUC o pasaporte de un cliente **ya registrado**. Si no existe, esa factura no se crea. |
| NOMBRE_CLIENTE | No | Solo informativo, para que usted lea el archivo con comodidad. El sistema no lo usa: manda la identificación. |
| ESTABLECIMIENTO | Sí | Código de 3 dígitos (001, 002…). Vea la hoja `Ref_Puntos_Emision`. |
| PUNTO_EMISION | Sí | Código de 3 dígitos del punto de emisión. |
| BODEGA | Solo si hay productos con inventario | Nombre de la bodega de donde sale la mercadería. |
| VENDEDOR | No | Nombre del vendedor. Si no existe, la factura queda sin vendedor y se avisa. |
| DIAS_CREDITO | No | Días de plazo. Vacío o 0 = contado. |
| OBSERVACIONES | No | Texto libre que se guarda en la factura. |
| PROPINA | No | Valor de propina, si aplica. |
| TOTAL_ESPERADO | No | Control de cuadre: si lo llena, el sistema compara con el total que calcula y bloquea la factura si no coinciden. |

### Hoja `Detalles` (una fila por producto o servicio)

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| ID_FACTURA | Sí | El mismo identificador de la hoja `Facturas`. |
| CODIGO_PRODUCTO | No | Código del catálogo. Si se deja vacío, la línea se factura como ítem libre. Si el código no existe, se crea según lo que diga `TIPO`. |
| TIPO | No | `Producto` o `Servicio`. **Solo decide cómo se crea un código que no existe todavía**; si el código ya está en el catálogo, manda el catálogo. Vacío = Servicio. |
| DESCRIPCION | Sí | Lo que aparece en la factura. Si el producto existe y esto queda vacío, se usa el nombre del producto. |
| CANTIDAD | Sí | Debe ser mayor que cero. |
| PRECIO_UNITARIO | Sí | **Sin IVA** (es la base imponible), igual que en la pantalla de Factura de Venta. |
| DESCUENTO | No | Valor **en dólares** que se resta a la línea, no un porcentaje. No puede superar el valor de la línea. |
| CODIGO_IVA | Sí | Código de la tarifa. Vea la hoja `Ref_tarifa_iva`, que lista cada código con su tarifa y su porcentaje. |
| LOTE | Según configuración | Obligatorio si el establecimiento exige lotes y el producto maneja inventario. |
| CADUCIDAD | Según configuración | Obligatoria si el establecimiento la exige y el producto maneja inventario. |
| NUP | Según configuración | Número de serie. Obligatorio si el establecimiento lo exige. |
| INFO_ADICIONAL | No | Texto adicional de la línea. |

### Hoja `Info_Adicional` (opcional)

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| ID_FACTURA | Sí | Factura a la que pertenece el dato. |
| NOMBRE | Sí | Etiqueta del campo adicional (por ejemplo, *Orden de compra*). |
| VALOR | Sí | Contenido del campo. |

## Permisos

- **Ver**: entrar al módulo y descargar la plantilla.
- **Crear**: subir el archivo, revisarlo y crear las facturas.
- Los permisos de **actualizar** y **eliminar** no se usan: desde aquí no se
  modifica ni se borra nada.
- El permiso de **acceso total** no cambia nada en este módulo. Las facturas se
  crean a nombre del usuario que hace la carga, y quien luego pueda verlas en
  Facturas de Venta depende de los permisos de *ese* módulo.

## Reglas de negocio

- **Las facturas se crean en estado borrador.** Nada se envía al SRI desde aquí.
- **El secuencial lo asigna el sistema**, tomando el siguiente número del punto de
  emisión indicado. No hay columna de secuencial y no se puede forzar uno.
- **El cliente debe estar registrado.** Se busca por identificación en la base de
  la empresa. Si no está, esa factura no se crea y se le indica en el informe;
  regístrelo primero en Clientes. Si el cliente existe pero fue eliminado, el
  mensaje se lo dice para que lo restaure en vez de crear uno nuevo.
- **Un código de producto que no exista se crea automáticamente**, según la
  columna `TIPO`: como servicio (sin inventario) o como bien **con inventario y
  stock en cero**. Un bien nuevo no trae existencias: se ingresan aparte, desde
  Cargas de Inventario.
- **Cada factura lleva una sola forma de pago**, por el total del documento, y no
  se escribe en el archivo: el sistema la elige igual que la pantalla de Factura
  de Venta — primero la configurada en la **ficha del cliente**, si no la del
  **establecimiento**. Si no hay ninguna de las dos, la factura se bloquea. En el
  informe previo verá qué forma de pago se va a usar y de dónde salió.
- **Los importes los calcula el sistema**, no el Excel. El subtotal de cada línea
  es `CANTIDAD × PRECIO_UNITARIO − DESCUENTO`, y el IVA se aplica sobre ese
  subtotal según el `CODIGO_IVA`. Si llenó `TOTAL_ESPERADO` y no coincide, la
  factura se bloquea.
- **El stock se revisa sumando todo el archivo.** Si veinte facturas piden el
  mismo producto, lo que se compara con el saldo es el total de las veinte, no
  cada una por separado. Esto solo aplica si el establecimiento descuenta
  inventario al facturar y no permite stock negativo.
- **Se aplica el límite de Consumidor Final** del establecimiento: una factura a
  consumidor final por encima de ese valor se bloquea.
- **Se revisa el formato que exige el SRI** antes de crear nada, para que ninguna
  factura nazca condenada a ser rechazada al enviarla:
  - Textos que exceden lo permitido: descripción y campos adicionales (300
    caracteres), código de producto (25), nombre del cliente (300).
  - Cantidades y precios con más de 6 decimales, que descuadrarían el total de la
    línea en el comprobante.
  - Identificación del cliente incoherente con su tipo (un RUC que no tiene 13
    dígitos, una cédula que no tiene 10, un Consumidor Final cuya identificación
    no es `9999999999999`) o un tipo fuera del catálogo del SRI.
  - Más de 13 campos de información adicional (el SRI admite 15 y el sistema
    reserva 2 para el correo del cliente y el RUC del proveedor).
  - Los datos de la **empresa emisora**: RUC de 13 dígitos, razón social y
    dirección presentes y dentro de los límites. Esto se revisa una sola vez y,
    si falla, se rechaza el archivo entero: ninguna factura sería aceptada.
- **Aplicación parcial**: las facturas con errores se omiten y las correctas se
  crean igual. Si una falla al escribirse, se informa y la carga continúa con las
  siguientes.
- **No se puede cargar dos veces lo mismo.** Hay dos controles:
  - Si sube **el mismo archivo** que ya aplicó, se rechaza entero, diciéndole
    cuándo se cargó y qué facturas creó.
  - Si edita el archivo y lo vuelve a subir, se comparan las facturas contra las
    ya emitidas: una factura al mismo cliente, con la misma fecha, el mismo total
    y el mismo número de líneas se bloquea, nombrando la que ya existe. Si de
    verdad necesita emitirla otra vez, hágalo desde Facturas de Venta.
- **Un archivo aplicado no se puede volver a aplicar.** Si quiere corregir algo,
  ajuste el Excel y súbalo de nuevo — pero quite las filas de las facturas que sí
  se crearon.
- Los productos que se crean al vuelo **se crean antes que las facturas**. Si
  después una factura falla, el producto ya quedó en el catálogo.

## Integraciones con otros módulos

- **Facturas de Venta**: es donde aparecen las facturas creadas, listas para
  revisar, emitir al SRI, imprimir o enviar por correo.
- **Inventario**: cada factura descuenta stock igual que una hecha a mano, con su
  movimiento de kardex, si el establecimiento está configurado para hacerlo.
- **Clientes**: los clientes deben existir antes; esta carga no crea ninguno.
- **Productos**: recibe las altas automáticas de los códigos que no existían.
- **Contabilidad**: los asientos no se generan aquí. Se generan cuando la factura
  se autoriza y corre la sincronización de contabilidad, igual que cualquier otra
  factura.
- **Carga de Productos**: úselo antes si necesita que los artículos sean bienes
  con inventario y no servicios.
- **Auditoría**: cada factura queda registrada en `log_sistema`, y además se
  registra la carga completa con su resumen.

## Errores frecuentes

- **"Al archivo le faltan hojas que no se deben borrar"**: se borró o renombró una
  hoja del libro. Descargue la plantilla otra vez y vuelva a llenarla.
- **"Esta plantilla se generó para otra empresa"**: está subiendo el archivo desde
  una empresa distinta a la que lo generó. Cambie de empresa o descargue la
  plantilla desde la correcta.
- **"Los encabezados de la hoja X fueron modificados"**: se renombró, eliminó o
  reordenó una columna. Los encabezados deben quedar tal cual.
- **"ID_FACTURA está repetido"**: dos filas de la hoja `Facturas` usan el mismo
  identificador. Cada factura necesita el suyo.
- **"ID_FACTURA no existe en la hoja Facturas"**: hay líneas de detalle apuntando
  a una factura que no escribió, normalmente por un error de tipeo.
- **"El punto de emisión no tiene configurado el secuencial de Facturas de
  venta"**: hay que configurarlo antes en la ficha del establecimiento.
- **"El CODIGO_IVA no existe"**: revise la hoja `Ref_tarifa_iva`. El código no es el
  porcentaje: `4` no significa 4%.
- **"El total calculado no coincide con TOTAL_ESPERADO"**: casi siempre es un
  descuento mal puesto (se espera un valor en dólares, no un porcentaje) o un
  código de IVA equivocado.
- **"Stock insuficiente: el archivo pide X entre todas sus facturas"**: sumadas,
  las facturas del archivo piden más de lo que hay. Reduzca cantidades, ingrese
  stock o divida la carga.
- **"No existe un cliente con la identificación X"**: el cliente no está
  registrado en esta empresa. Créelo en el módulo Clientes y vuelva a subir el
  archivo. Esta carga no crea clientes.
- **"El cliente con identificación X está ELIMINADO"**: el cliente existió y fue
  dado de baja. Restáurelo desde el módulo Clientes en vez de crear uno nuevo.
- **"No hay forma de pago"**: ni el cliente ni el establecimiento tienen una
  configurada. Asígnela en la ficha del cliente o en la configuración del
  establecimiento.
- **"El código X es nuevo y se crearía como Producto con stock cero"**: su
  establecimiento no permite facturar sin existencias. Cree el producto e ingrese
  su stock antes, o marque la línea como `TIPO = Servicio`.
- **"El código X aparece como Servicio y como Producto"**: el mismo código nuevo
  tiene distinto `TIPO` en dos líneas del archivo. Debe ser siempre el mismo.
- **"X tiene N caracteres y el SRI admite como máximo M"**: acorte el texto. Es un
  límite del comprobante electrónico, no del sistema.
- **"CANTIDAD/PRECIO_UNITARIO tiene N decimales"**: el comprobante solo admite 6.
  Con más, el SRI recalcula el total de la línea y no le cuadra. Redondee.
- **"El cliente está registrado como RUC pero su identificación no tiene 13
  dígitos"** (o la variante de cédula): la ficha del cliente tiene el tipo y el
  número descuadrados, algo típico de datos migrados. Corríjalo en Clientes.
- **"El RUC de la empresa no tiene 13 dígitos"** y similares: el problema está en
  la configuración de la empresa, no en el archivo. Ninguna factura se puede
  emitir así, por eso se rechaza el archivo completo.
- **"Este archivo YA SE CARGÓ el …"**: está subiendo un archivo que ya se aplicó.
  Las facturas están en Facturas de Venta; si necesita cargar otras, prepare un
  archivo solo con las filas nuevas.
- **"Ya existe una factura igual para este cliente"**: hay una factura emitida con
  el mismo cliente, fecha, total y número de líneas. Casi siempre es que se está
  recargando algo ya cargado. Compruébelo con el número que indica el mensaje.
- **"La carga expiró o no existe"**: pasaron más de dos horas entre revisar y
  crear, o cambió de empresa. Vuelva a subir el archivo.

## Historial de cambios

- **1.0** — Versión inicial: carga masiva de facturas en borrador con secuencial
  automático, forma de pago heredada del cliente o del establecimiento, alta
  automática de productos y servicios según la columna `TIPO`, validación previa
  completa y control de stock agregado del archivo. El cliente debe existir.
