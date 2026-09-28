---
titulo: Saldos iniciales
resumen: Carga del punto de partida al empezar a usar el sistema: cartera, bancos, efectivo e inventario.
categoria: Configuración de empresa
ruta_modulo: modulos/saldos-iniciales
tipo: modulo
visibilidad: admin
etiquetas: saldos iniciales, apertura, arranque, migracion, cartera inicial, stock inicial, empezar a usar el sistema, numero del cobro, numero del pago, vista previa del numero, secuencial del egreso, secuencial del ingreso, pago duplicado, cobro duplicado, doble clic, no deja pagar saldo inicial, disponible 0, saldo inicial duplicado, cargado dos veces, importar dos veces, documento repetido, ya esta registrado como saldo inicial, ya existe en el sistema
version: 1.4
orden: 10
estado: activo
---

Los **saldos iniciales** son la foto con la que la empresa arranca en el sistema:
lo que le deben, lo que debe, cuánto dinero tiene y qué mercadería hay en bodega.
Se cargan una sola vez, al empezar.

## Qué se puede cargar

| Pestaña | Qué carga |
|---------|-----------|
| Cuentas por cobrar | Facturas pendientes de cobro, por cliente |
| Cuentas por pagar | Facturas pendientes de pago, por proveedor |
| Bancos | Saldo de cada cuenta bancaria |
| Efectivo | Saldo de caja |
| Inventario | Existencias iniciales, como kardex de apertura |
| Consignaciones | Mercadería en consignación (solo registro) |

## Cartera: el cliente o proveedor es obligatorio

En cuentas por cobrar y por pagar hay que indicar **a quién** corresponde cada
saldo: no se admite un saldo suelto. Además, el **número de documento** debe
seguir el formato `000-000-000000000`.

Es la única forma de que después ese saldo aparezca en la cartera del cliente
correcto y se pueda cobrar contra él.

## Consignaciones: solo registro

Los saldos de consignación se registran para tenerlos identificados, pero **no
afectan al stock**: la mercadería en consignación no es existencia propia.

## Antes de cargar

Cargue los saldos iniciales **antes** de empezar a operar, y con fecha anterior
al primer documento real. Si se cargan después, los informes de los primeros días
saldrán incompletos y habrá que rehacerlos.

## Cómo se calcula el pendiente de un saldo inicial por cobrar

El valor de la columna **Pendiente** no es un dato guardado: se recalcula cada
vez, restando al saldo inicial todo lo que ya lo cubrió.

```
Pendiente = Saldo inicial − Cobrado − Retenciones − Notas de crédito
```

Las **retenciones** y las **notas de crédito** se enlazan por el **número de
documento**: una retención cuyo documento de sustento, o una nota de crédito cuyo
documento modificado, coincida con el número del saldo inicial lo abona
automáticamente. No hay que registrarlas dos veces.

Si ese mismo número existe además como **factura real** en el sistema, el abono
se aplica a la factura y no al saldo inicial, para no descontarlo dos veces.

Es el mismo cálculo que usa **Cuentas por Cobrar**, así que ambas pantallas
muestran siempre el mismo pendiente y el mismo estado. Desde la versión 1.4
también lo usan **Ingresos** y **Conciliación de Cobros**: antes no restaban las
notas de crédito y permitían volver a cobrar la parte que la nota ya canceló.

## Un documento no se puede cargar dos veces

Cada copia de un saldo inicial tiene su propio pendiente, así que un documento
cargado dos veces se podría cobrar (o pagar) dos veces. Por eso el sistema
rechaza, al **crear**, **editar** o **importar**:

- **El mismo documento repetido**: mismo cliente y número (por cobrar), o mismo
  proveedor, tipo y número (por pagar). El número se compara ya normalizado:
  `901` y `001-001-000000901` son el mismo documento.
- **Un documento que ya existe en el sistema**: una factura de venta del mismo
  cliente, o una compra o liquidación del mismo proveedor, con ese número. Su
  saldo ya está en la cartera; cargarlo además como saldo inicial lo duplicaría.

Al **importar** un Excel, las filas repetidas (dentro del mismo archivo o con
cargas anteriores) se informan como error de esa fila y el resto se carga
normalmente, así que volver a subir el mismo archivo no duplica nada.

## Errores frecuentes

- **"El documento … ya está registrado como saldo inicial de este
  cliente/proveedor"**: ese documento ya se cargó; búsquelo en el listado. Si la
  carga anterior estaba mal, edítela o elimínela.
- **"El documento … ya existe en el sistema como factura de venta / compra"**:
  no lo cargue como saldo inicial; su saldo ya aparece en Cuentas por Cobrar o
  por Pagar.
- **"… ya no tiene saldo suficiente (disponible: $0.00)" al pagar un saldo por
  pagar**: ocurría siempre, aunque el saldo tuviera valor; está corregido en la
  versión 1.3. Si aún aparece, otro pago ya cubrió ese saldo.
- **"El número de documento debe tener el formato 000-000-000000000"**: respete
  establecimiento, punto de emisión y secuencial.
- **"Debe seleccionar un cliente registrado"**: regístrelo primero en Clientes.
- **El stock inicial no aparece**: compruebe que el producto sea inventariable y
  la bodega la correcta.
- **El pendiente no coincide con Cuentas por Cobrar**: ocurría cuando el saldo
  inicial tenía una **nota de crédito** aplicada: este listado no la restaba y
  Cuentas por Cobrar sí, así que el mismo documento mostraba dos valores. Ya
  está corregido; ambas pantallas usan el mismo cálculo.
- **Al registrar un pago, el número que se veía no era el que quedaba**: en los
  saldos por pagar, la ventana mostraba como vista previa el siguiente número de
  **Ingresos** en vez del de **Egresos**. El documento siempre se guardó con el
  número correcto —el que muestra el mensaje al terminar—, pero la vista previa
  engañaba. Ya está corregido: cada tipo consulta su propia numeración.

## Historial de cambios

- **1.4** — Un documento ya no se puede cargar dos veces como saldo inicial, ni cargarse como saldo inicial si ya existe como factura, compra o liquidación en el sistema (al crear, editar e importar). Además, **Ingresos** y **Conciliación de Cobros** restan ahora las notas de crédito del saldo inicial, igual que este módulo: antes permitían volver a cobrar la parte ya cancelada por la nota. Nueva sección *Un documento no se puede cargar dos veces*.
- **1.3** — Corregido: el **pago** de un saldo por pagar se rechazaba siempre con  *"ya no tiene saldo suficiente (disponible: $0.00)"*. Ahora se valida contra su  saldo real. Además, el botón **Guardar** del cobro/pago ya no admite un doble  clic que registraba el movimiento dos veces.
- **1.2** — Al registrar un **pago** de un saldo por pagar, el número que se
  mostraba antes de guardar era el de la numeración de **Ingresos** y no la de
  **Egresos**. Solo afectaba a la vista previa —el egreso siempre se guardó con
  el número que le tocaba—, pero se notaba más desde que cada tipo de documento
  puede tener su propio **modo de numeración** (ver *Empresa → Secuenciales*).
- **1.1** — El **Pendiente** de los saldos iniciales por cobrar descuenta también
  las **notas de crédito**, igual que ya hacía con los cobros y las retenciones
  (antes solo lo hacía Cuentas por Cobrar, y las dos pantallas discrepaban). El
  estado PENDIENTE / PARCIAL / PAGADO se calcula con el mismo criterio, y el
  monto máximo cobrable ya no incluye la parte cubierta por la nota — antes se
  podía registrar un cobro por el importe completo y sobrecobrar. Nueva sección
  *Cómo se calcula el pendiente de un saldo inicial por cobrar*.
- **1.0** — Versión inicial.
