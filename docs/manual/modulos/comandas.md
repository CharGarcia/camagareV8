---
titulo: Comandas
resumen: Pedido de una mesa: lo que consume el cliente antes de cobrarle.
categoria: Restaurante
ruta_modulo: modulos/comandas
tipo: modulo
visibilidad: todos
etiquetas: comandas, comanda, pedido, mesa, restaurante, cocina, anular, cerrar cuenta, servicio, 10%, propina, propina voluntaria, recargo, total con iva, turno de caja, punto de emision, mesa ocupada por otro usuario, doble cobro, cobro duplicado, tirilla, ticket, impresora termica, 80mm, imprimir cuenta, tirilla descuadrada, imprimir orden, orden de cocina, comanda en papel, reimprimir orden, copia, sin estacion, stock general, configuracion restaurante
version: 1.20
orden: 20
estado: activo
---

Una **comanda** es el pedido de una mesa: lo que el cliente va consumiendo,
mientras lo consume. Se abre al sentarse, se le van añadiendo productos y se
cierra al cobrar.

## El recorrido

1. Elija una **mesa disponible** y abra la comanda.
2. Añada los ítems: productos del catálogo o platos del menú.
3. Envíe a cocina lo que corresponda.
4. Al terminar, cierre la comanda y cobre.

## Hace falta un turno de caja abierto

No se abre una mesa sin **turno de caja abierto**. Al abrir la comanda, el
sistema la ata al turno del **punto de emisión que eligió ese usuario** al abrir
su caja, y es por ese punto de emisión que se emitirá el comprobante al cobrar.

Por eso, si el salón trabaja con varios puntos de emisión, la mesa se factura por
el del mesero que la abrió, no por el de quien pase después. Si no hay ningún
turno abierto, el sistema avisa —*"No hay un turno de caja abierto. Abre la caja
en Punto de Venta antes de abrir mesas"*— y la mesa no se abre: es preferible eso
a descubrir el problema recién al cobrar, con la mesa ya servida.

Si la comanda se abre desde el **QR de la mesa** (el cliente pidiendo por su
cuenta), el turno se resuelve solo, tomando el que esté abierto en el local: el
cliente no elige punto de emisión.

## Cuando dos personas atienden la misma mesa

Varios usuarios pueden entrar a la vez a una comanda. Al segundo se le muestra un
aviso: *"Esta mesa la está atendiendo …"*.

- **Se puede seguir tomando el pedido**: los dos agregan ítems. Un ítem de más se
  corrige anulando la línea.
- **No se puede cobrar**: mientras otro tenga la mesa abierta, el cobro se
  rechaza. Cobrar emite un comprobante electrónico, y dos cobros de la misma
  cuenta serían dos documentos por el mismo consumo.

El aviso desaparece cuando el otro usuario sale de la comanda, y como máximo a
los tres minutos sin actividad suya (por si cerró la tablet o perdió la red).

Aparte de eso, el propio cobro está protegido contra el doble clic y contra dos
cajas cobrando a la vez: la segunda recibe *"Esta cuenta se está cobrando en otro
dispositivo"* en lugar de emitir un segundo documento.

## Solo se modifica si está abierta

Una comanda cerrada **no admite cambios**: el sistema avisa de que *no está
abierta*. Si hay que corregir algo después de cerrarla, la corrección va sobre el
documento de venta, no sobre la comanda.

## El total que ve el mesero

Al pie de la comanda se muestra el **subtotal**, el **IVA** desglosado por tarifa
y el **Total**. Ese total es el valor **con impuestos incluidos**: es lo que el
cliente va a pagar, no la suma de precios sin IVA.

El IVA que se muestra aquí sirve para que nadie tenga que calcularlo de cabeza al
leer la cuenta, y es el mismo que sale en el comprobante: para los ítems de la
carta manda la **tarifa configurada en el ítem del Menú**, y solo si ese ítem no
tiene tarifa propia se usa la del producto vinculado. Si un plato aparece con un
IVA que no esperaba, la tarifa se corrige en **Menú**, no aquí.

El mismo criterio se aplica en todo el recorrido del cobro: el importe de cada
ítem en la lista de la comanda, el importe de cada mesa en el tablero, el
**Total seleccionado** al dividir la cuenta y el **Total a cobrar** del pago
están todos con impuestos incluidos. Así, si una cuenta pasa el límite de venta
a Consumidor Final, el aviso salta al elegir los ítems y no recién al confirmar
el pago.

El importe que aparece a la derecha de cada ítem también es **con IVA**, igual
que el precio de las tarjetas del catálogo: lo que se ve al tocar el producto es
lo que se suma a la cuenta. Por eso la columna de importes **no suma el
subtotal** del pie — el pie es el desglose contable (subtotal, IVA, servicio,
total), la lista es lo que paga el cliente por cada cosa. El recargo por
servicio no se reparte entre los ítems: se cobra una sola vez, en su propia
línea del pie.

## El recargo por servicio (el 10%)

El recargo por servicio del salón se configura en **Empresa → Facturación →
Recargo por servicio (restaurantes)**.

Antes hay que activar, en esa misma pantalla, **¿Mostrar el campo de propina en
la factura?**. No es un capricho: el recargo se emite justamente en el campo de
propina del comprobante, así que sin él no hay dónde ponerlo. Mientras esté
apagado, las opciones del recargo se ven bloqueadas; y si se apaga después, el
recargo deja de cobrarse —también en las cuentas que ya estén abiertas en el
salón—, aunque el porcentaje configurado se conserva para cuando se vuelva a
activar.

Con la propina activa, el recargo tiene tres estados:

- **No se cobra**: la comanda no muestra ninguna línea de servicio.
- **Obligatorio**: toda comanda lo lleva y no se puede quitar desde el salón.
- **Opcional**: toda comanda lo lleva, pero el mesero puede retirarlo con el
  enlace **Quitar** del pie de la comanda cuando el cliente no quiere pagarlo, y
  volver a aplicarlo si se arrepiente.

El porcentaje también se configura ahí, y **no puede pasar del 10%**: en el
comprobante este valor viaja en el campo de **propina**, y el SRI rechaza un
comprobante cuya propina supere el 10% del subtotal.

### Cómo se calcula y dónde aparece

Se calcula sobre el **subtotal sin impuestos** y se suma **después del IVA**: no
forma parte de la base imponible, así que el 10% no paga IVA. En la factura o el
recibo aparece como una línea de propina bajo los impuestos.

El porcentaje queda **congelado al abrir la mesa**. Si mañana se cambia la
configuración, las cuentas que ya están abiertas en el salón conservan el
porcentaje que se les prometió al cliente; las nuevas nacen con el nuevo. Si una
comanda se abrió antes de que existiera el recargo y no tiene porcentaje propio,
se le aplica el vigente.

**Obligatorio manda sobre lo que diga la comanda**: al cambiar el
establecimiento a *obligatorio*, el recargo aparece de inmediato en todas las
cuentas abiertas, incluidas las que un mesero hubiera dejado sin recargo cuando
la configuración estaba en *opcional*. No hay que cerrar mesas ni reabrirlas.

Al **dividir la cuenta**, cada parte carga su propio recargo, proporcional a lo
que se le cobra. Lo mismo vale para el cliente que paga desde el QR de la mesa:
ve el recargo antes de confirmar y paga exactamente lo que dirá su comprobante.

## La propina voluntaria

Es la que el cliente deja por su cuenta, **además** del recargo por servicio. Se
escribe en el campo **Propina** del pie de la comanda: se guarda al salir del
campo o con Enter, se cambia escribiendo otro valor y se quita dejándolo en 0.
No tiene tope.

Se suma al total **tal cual, sin impuestos y sin afectar al recargo**. Si la
cuenta con todo iba en $100 y el cliente deja $5, el total es **$105** exacto —
el recargo por servicio se sigue calculando sobre el consumo, no sobre la
propina.

### Por qué aparece como un ítem de la factura

El comprobante electrónico tiene **un solo** campo de propina, y ese ya lo ocupa
el recargo por servicio (que además no puede pasar del 10%). Para una segunda
propina no queda lugar, así que se emite como **una línea más del detalle**, con
un producto de tipo servicio e IVA 0%.

Ese producto se busca una vez en **Empresa → Facturación → Propina voluntaria**
(el buscador solo ofrece productos marcados como servicio). Mientras no esté
configurado, el campo no aparece en la comanda. Debe ser un servicio (no
inventariable), con IVA 0% y precio 0: el monto lo pone el mesero en cada
comanda.

Dos consecuencias de emitirla así, que conviene conocer:

- La propina **entra en las ventas de la empresa**: figura en el asiento contable
  y en la casilla de ventas 0% de la declaración de IVA. El campo de propina del
  comprobante no funciona así — el SRI lo trata aparte.
- Al **dividir la cuenta** se comporta como cualquier ítem: en *partes iguales*
  se reparte entre las partes; en *por ítems* se marca en la cuenta que se
  quiera.

La propina no se envía a cocina ni pasa por preparación, y no se le puede aplicar
descuento. Se cambia desde su campo del pie, y se quita de dos formas: dejando
ese campo en 0, o con la **x** de su propia fila en la lista de ítems. El cliente
también puede quitarla desde el QR con esa misma x, aunque ya la haya dejado. En
todos los casos la línea desaparece, no queda como un ítem anulado.

Se puede cambiar o quitar **aunque el cliente ya haya pedido su cuenta**, mientras
esa cuenta no se haya cobrado: si decide dejar más propina, el mesero pone el
nuevo valor y la cuenta se recalcula sola. Una vez cobrada, la propina pertenece
al documento emitido y ya no se toca.

En pantalla y en la tirilla, la propina se muestra **sin cantidad**: es un valor
único, no un consumo de N unidades.

### El cliente también puede dejarla desde el QR

Al pulsar **Pedir mi cuenta** en el portal de la mesa, el cliente ve un campo
*¿Dejas propina?*: lo que escriba se suma al total antes de confirmar y entra en
**la misma cuenta** que va a pagar, así viaja en el mismo documento. Si paga con
tarjeta, el monto que se le cobra ya la incluye.

La propina es **una sola por comanda**: si el cliente escribe un valor y el
mesero ya había puesto otro, queda el último. El campo solo aparece si el
establecimiento tiene configurado el producto de propina.

Si el mesero **quita la propina** desde el salón, la cuenta que el cliente está
mirando en su celular se actualiza sola en unos segundos, incluso con el modal de
la cuenta abierto (salvo que en ese momento esté escribiendo en el campo, para no
pisarle lo que teclea).

## El mesero se entera de lo que se pide por el QR

Cuando el cliente **confirma su pedido** desde el celular, la mesa muestra en el
tablero un aviso azul con el ícono de QR, parpadeando. Sin eso el pedido pasaría
desapercibido en el salón: los ítems se van solos a la pantalla de cocina —o
quedan servidos, si no pasan por estación— y nadie del salón se enteraría.

El aviso se apaga solo cuando un mesero **entra a esa comanda** desde el tablero,
que es la señal de que alguien ya lo vio.

El cliente puede **quitar sus ítems hasta que confirma el pedido**; después ya no,
y lo que quiera cambiar pasa por el mesero.

## Con la cuenta pedida, el cliente ya no agrega

Una vez que el cliente **pide su cuenta** desde el QR, deja de poder agregar
ítems: el menú sigue a la vista pero el botón *Agregar* queda deshabilitado y un
aviso le dice que le avise al mesero. Lo mismo si la cuenta ya se cobró.

Para que vuelva a pedir, el mesero **deshace la cuenta** desde la comanda (el
botón de la flecha en el grupo pendiente): al quedar anulada, el portal se reabre
solo en unos segundos.

Del lado del salón pasa lo simétrico: mientras haya una cuenta pedida desde el QR
esperando cobro, el botón **Cobrar** del pie de la comanda queda deshabilitado.
Esa cuenta ya está armada y se cobra con el botón del propio grupo — así no se
arma otra por encima y no se pasa por alto lo que pidió el cliente. Las cuentas
que arma el mesero no bloquean nada: puede seguir armando las que necesite.

## Cómo piensa pagar el cliente (QR)

Al pedir su cuenta desde el celular, el cliente elige **cómo piensa pagar** de
la lista de formas de pago de la empresa. Es **solo una sugerencia**: el mesero
la ve en dos lugares —en la cuenta pendiente ("Sugiere pagar con…") y dentro del
modal **Registrar cobro**, bajo el selector de forma de pago— y la encuentra ya
seleccionada, pero puede cambiarla; quien decide la forma real sigue siendo él.

La nota del modal dice expresamente que viene del cliente: sin eso el mesero no
distinguiría una sugerencia del QR de su propia forma favorita.

Si el cliente sugirió una forma, esa gana sobre la **forma favorita** del cajero:
es específica de esa cuenta. El cliente también puede dejarlo en *Lo decido con
el mesero* y no sugerir nada.

Desde el portal QR **ya no se paga en línea**: el cliente avisa que quiere su
cuenta y el cobro lo cierra el mesero.

## Ítems que no pasan por cocina

Un ítem cuyo **Preparar en** en el Menú está en *Ninguna* no se prepara: entra a la
comanda **ya entregado**. No suma al botón **Enviar a preparación** ni ofrece el
botón **Entregar**, porque no hay nada que esperar — es el caso de una bebida
embotellada o de un servicio.

Si un plato no llega a la pantalla de cocina, casi siempre es esto: revise su
campo *Preparar en* en el módulo **Menú**.

## Forma de pago favorita

En el modal **Registrar cobro**, la estrella junto a *Forma de pago* fija la que
usa habitualmente: queda preseleccionada cada vez que se abre el modal. Es por
usuario y por empresa, igual que el resto de los favoritos del sistema. Se quita
pulsando la estrella de nuevo sobre la misma forma.

## Qué forma de pago SRI sale en el comprobante

La forma de pago que elige el cajero (Efectivo, un banco, Tarjeta, Payphone) es la
**forma de cobro de tesorería**: dice a qué caja o cuenta entra el dinero. El
comprobante electrónico necesita además un **código de forma de pago del SRI**, y
ese lo decide el sistema solo, con este orden:

1. **El tipo de la forma cobrada**, cuando ya no deja lugar a dudas:

   | Forma cobrada | Código SRI |
   |---------------|------------|
   | Banco (transferencia, depósito, débito, cheque) | 20 — Otros con utilización del sistema financiero |
   | Tarjeta de crédito | 19 — Tarjeta de crédito |
   | Tarjeta de débito | 16 — Tarjeta de débito |
   | Payphone | 19 — Tarjeta de crédito |
   | Nuvei | 19 — Tarjeta de crédito (16 si la forma se configuró como débito) |

2. **La forma de pago SRI de la ficha del cliente** (Clientes → pestaña
   Comercial → *Forma de Pago SRI (Predeterminada)*).
3. **La configurada en la empresa**: Empresa → Facturación → *Forma de Pago SRI
   por defecto*.
4. Si no hay ninguna, **01 — Sin utilización del sistema financiero**.

Es decir: cobrar con tarjeta o por banco manda siempre, porque el medio de pago ya
está determinado. Cobrar en **efectivo** (o con una forma de tipo *Otro*) es lo que
abre la cascada: ahí sí se respeta lo configurado en el cliente y, si el cliente no
tiene nada, lo configurado en la empresa.

Es la misma precedencia que aplican la pantalla de **Facturas de Venta** y la
**Carga de Facturas por Excel**, así que un mismo cliente se declara igual se le
facture desde donde se le facture.
## El cobro siempre emite Factura

El modal **Registrar cobro** ya no pregunta el tipo de documento: toda cuenta del
salón se cobra con **Factura**. Vale también para los pedidos que llegan desde el
QR de la mesa, aunque el cliente haya marcado "recibo" al pedir.

## Anular con motivo

Anular una comanda **que ya tiene ítems** exige indicar un **motivo**. No es
burocracia: es lo que permite después distinguir una mesa que se levantó sin
consumir de una anulación irregular.

Una comanda vacía se anula sin más.

## Validaciones

| Regla | Detalle |
|-------|---------|
| Turno de caja | Debe haber uno abierto para abrir la mesa y para cobrarla |
| Mesa libre para cobrar | No se cobra mientras otro usuario tiene la comanda abierta |
| Mesa disponible | No se abre una comanda sobre una mesa ocupada |
| Comanda abierta | Solo se modifica mientras está abierta |
| Ítem | Hay que seleccionar un producto o un ítem del menú |
| Cantidad | Mayor a cero |
| Motivo de anulación | Obligatorio si la comanda ya tiene ítems |
| Recargo por servicio | Máximo 10% del subtotal; solo se puede quitar si el establecimiento lo tiene como opcional |
| Propina voluntaria | No puede ser negativa; no tiene tope. Requiere el producto configurado en Empresa → Facturación |

## La cuenta que ve el cliente

El botón del recibo, junto a *Cobrar*, imprime la **cuenta** para que el cliente
la revise antes de pagar. No es un documento válido: es la previa.

Sale con **los mismos valores que muestra la pantalla**: cada ítem con su precio
unitario y su importe **con IVA incluido**, y al pie el subtotal, el IVA
desglosado por tarifa, el recargo por servicio si aplica y el total. Así el
cliente puede cuadrar el papel con lo que tiene el mesero en pantalla.

### La tirilla del documento cobrado

Al registrar el cobro aparece un aviso con el número del documento y un botón
**Imprimir tirilla**. Esa tirilla ya no es la cuenta previa: es la **factura o el
recibo emitido**, con su formato fiscal.

Si era la última cuenta de la mesa, la comanda se cierra y la mesa queda libre,
pero **la pantalla no se va hasta que usted cierre ese aviso**: puede imprimir
las copias que necesite antes de volver al tablero.

Y si se le pasó, la tirilla se puede reimprimir en cualquier momento desde
*Facturas de Venta* o *Recibos de Venta*, con el botón del recibo en la barra de
acciones del documento.

## Locales que no trabajan con preparación

Si **ningún ítem de la carta ni ninguna categoría está enrutado a una estación**
(*Menú → Preparar en*), el sistema entiende que el local entrega todo directo: no
aparece el botón *Enviar a preparación* ni el aviso de "por enviar" del tablero de
mesas. El pedido se toma y se cobra, sin cocina de por medio.

**El botón de imprimir sí sigue apareciendo**, y ahí funciona sin restricciones:
no hay que enviar nada primero, y saca la comanda entera. La imprime **este mismo
equipo** —el del mesero o el de caja—, no una pantalla de cocina: en un local que
no prepara nada, esa pantalla no tiene por qué estar abierta. De la **estación
predeterminada** se toma el formato: el ancho del papel y cuántas copias.

En ese caso los ítems que agrega el mesero **quedan entregados de una vez**: no
hay ningún paso posterior que los mueva de estado, y dejarlos pendientes los haría
figurar en los avisos y bloquearía que el cliente pidiera su cuenta desde el QR
—que exige que todo esté entregado—.

Lo que se pide **desde el QR sí sigue naciendo pendiente**, incluso sin
preparación: el cliente arma su pedido y lo confirma, y esa confirmación es la
que avisa al salón. Al confirmarlo, esos ítems pasan directo a entregados.

Basta crear una estación y activarla para que el flujo vuelva a aparecer.
## Imprimir la orden de cocina

Si la estación tiene impresora configurada (*Configuración Restaurante*), al pulsar
**Enviar a preparación** la orden sale además en papel, en la impresora de cada
estación involucrada: cocina recibe lo suyo y la barra lo suyo, cada una en su
propio ticket, sin precios.

El botón de **impresora** junto a *Enviar a preparación* vuelve a sacar esa
orden, marcada como **COPIA**. Sirve para cuando el papel se atascó, la pantalla
de cocina estaba apagada en ese momento, o la estación está configurada para
imprimir solo a pedido.

### Todo se configura en Configuración Restaurante

Esta pantalla no decide nada de la impresión: **qué estación imprime, con qué
papel y cuántas copias** se define en *Configuración Restaurante*. Por eso el
botón no pregunta nada — manda la orden y ya.

Un local puede trabajar **solo con el stock general**, sin cargar la carta en
Menú. Para que esos ítems lleguen igual a la cocina, en *Configuración
Restaurante* se marca una **estación predeterminada**: recoge todo lo que no
tiene estación propia. Sin ella, esos ítems se dan por entregados al enviarlos y
no aparecen en ninguna pantalla ni se imprimen.

## Cómo sale la tirilla en la impresora térmica

Tanto la **cuenta** (el botón de vista previa, antes de cobrar) como la
**factura o el recibo** que se imprime al cobrar salen por la impresora térmica
del salón: se abre una ventana aparte y se lanza la impresión directamente, sin
generar un PDF intermedio.

La tirilla **no impone un ancho propio**: se adapta al papel que tenga
configurado el driver de la impresora. Eso es lo que evita que el navegador
reescale la página —que es lo que hacía que el texto saliera encogido y los
importes se corrieran de renglón—. Para que salga a escala 1:1, en el diálogo de
impresión del navegador conviene dejar:

| Opción | Valor |
| --- | --- |
| Márgenes | Ninguno |
| Escala | 100 % (no *Ajustar al área de impresión*) |
| Encabezados y pies de página | Desactivado |

El **ancho del papel** (58 u 80 mm) se elige en *Configuración Restaurante*, y
ajusta el tamaño de letra de la tirilla.

Como el tamaño de página lo manda el driver, **ahí es donde hay que dejarlo
bien**: Windows
→ *Dispositivos e impresoras* → propiedades de la impresora → tamaño de papel, y
elegir el de 80 mm que trae el fabricante —normalmente listado como
**80 × 297 mm** o **72,1 mm × recibo**—. Si ahí quedó un papel de 58 mm o una
hoja A4, la tirilla sale con el ancho equivocado por más que el sistema la genere
bien.

Marcando *Recordar* / *Establecer como predeterminado* en esas opciones, el
navegador las reutiliza en las siguientes impresiones y el cajero no tiene que
tocarlas cada vez.
## Errores frecuentes

- **"La mesa no está disponible"**: ya tiene una comanda abierta.
- **"La comanda no está abierta; no se puede modificar"**: ya fue cerrada.
- **"Indica un motivo para anularla"**: la comanda tiene consumos registrados.
- **"El recargo por servicio es obligatorio en este establecimiento"**: así está
  configurado en Empresa → Facturación; cámbielo a *opcional* si el salón debe
  poder retirarlo.
- **"No hay un producto configurado para la propina"**: falta elegirlo en Empresa
  → Facturación → Propina voluntaria. Debe ser un servicio con IVA 0%.
- **No veo el campo de propina en la comanda**: no hay producto de propina
  configurado, o el usuario no tiene permiso para crear en comandas.
- **"La propina ya forma parte de una cuenta cobrada"**: se cobró junto con esa
  parte de la cuenta; la corrección va sobre el documento emitido.
- **"No hay un turno de caja abierto. Abre la caja en Punto de Venta antes de
  abrir mesas"**: no hay caja abierta en el local. Ábrala en *Punto de Venta →
  Cajas* y vuelva a intentarlo.
- **"El turno de caja indicado ya no está abierto"**: la caja se cerró mientras
  usted tenía el salón en pantalla. Vuelva a Cajas y elija su punto de emisión.
- **"Esta mesa la está atendiendo …"**: otro usuario tiene la comanda abierta.
  Puede seguir tomando el pedido; para cobrar, espere a que salga.
- **El catálogo aparece vacío al abrir la comanda**: el selector de la izquierda
  arranca en **Menú** —el salón trabaja con la carta— y esa empresa todavía no
  tiene ítems cargados en *Menú*. Los productos siguen ahí: cambie el filtro a
  **Todos** o **Stock general**. Si hay productos y ninguna carta, la pantalla ya
  cambia sola a *Todos*.
- **"No hay nada que imprimir todavía: primero pulse Enviar a preparación"**:
  la orden de cocina es lo que se mandó a preparar, así que hasta que no se envíe
  no hay ticket que sacar.
- **"Ninguna estación tiene impresora configurada"**: ninguna estación tiene
  activada la impresión de órdenes. Se activa en *Configuración Restaurante*.
- **"Esta comanda no tiene ítems enviados a preparación para imprimir"**: todavía
  no se envió nada a cocina, o lo enviado no va a ninguna estación con impresora.
- **Se envió a preparación pero no salió el papel**: la pantalla de esa estación
  no está abierta en el equipo de la impresora. La orden no se pierde: sale en
  cuanto se abra (ver el manual del KDS).
- **La tirilla sale descuadrada, con el texto encogido o los valores en otro
  renglón**: el navegador está reescalando la página. Revise el tamaño de papel
  del driver y ponga *Márgenes: Ninguno* y *Escala: 100 %* en el diálogo de
  impresión (ver *Cómo sale la tirilla en la impresora térmica*).
- **"Esta cuenta se está cobrando en otro dispositivo"**: dos cobros a la vez, o
  un doble clic. Espere unos segundos y **revise la comanda antes de reintentar**:
  es probable que el cobro ya se haya emitido.

## Historial de cambios

- **1.18** — La forma de pago SRI del comprobante ya no sale solo del tipo de
  la forma cobrada: cuando ese tipo no la determina (efectivo u *Otro*), se toma
  la configurada en la ficha del cliente y, si no tiene, la de Empresa →
  Facturación. Antes se emitía siempre *01* en esos casos. Si el cobro no puede
  generar su Ingreso, ahora se avisa en pantalla en vez de quedar solo en el log.
- **1.20** — Cobrada la última cuenta, la pantalla ya no vuelve al tablero a los
  1,5 segundos: espera a que se cierre el aviso del cobro, para que dé tiempo a
  imprimir la tirilla del documento. La cuenta impresa muestra los importes **con IVA**, igual que la
  pantalla: antes imprimía la base sin impuestos y las líneas no cuadraban con
  lo que veía el mesero.
- **1.19** — La comanda esconde el botón *Enviar a preparación* (y el aviso del
  tablero) cuando ningún ítem está enrutado a una estación: ahí los ítems del
  mesero nacen entregados y el botón de imprimir saca la comanda entera desde el
  propio equipo, con el formato de la estación predeterminada y sin exigir envío. Y la estación predeterminada se aplica también al enviar, así
  que configurarla arregla las mesas que ya estaban abiertas.
- **1.18** — El botón de imprimir ya no pregunta a dónde mandar la orden: las
  impresoras se configuran en *Configuración Restaurante*, incluida la estación
  predeterminada que recoge los ítems del stock general.
- **1.16** — Si la empresa no tiene carta cargada, el catálogo de la comanda ya
  no aparece vacío: cae solo al filtro *Todos* y el mensaje explica dónde están
  los ítems.
- **1.15** — La orden de cocina se puede imprimir en papel: sale sola al enviar a
  preparación (si la estación tiene impresora configurada) y hay un botón para
  volver a imprimirla marcada como copia.
- **1.14** — La tirilla (cuenta y factura/recibo del cobro) se adapta al ancho de
  papel del driver en vez de imponer el suyo, con columnas de ancho proporcional
  y tipografía sans-serif: ya no sale reescalada, con los importes corridos ni
  con la letra entrecortada en impresoras térmicas de 80 mm.
- **1.13** — La comanda se ata al turno del punto de emisión que eligió el mesero
  (antes tomaba cualquier turno abierto del local, así que podía facturarse por
  otro punto) y no se abre sin turno. Aviso cuando otra persona atiende la misma
  mesa, con el cobro bloqueado mientras tanto, y protección contra el doble cobro
  simultáneo de una misma cuenta.
- **1.12** — El tablero avisa cuando el cliente confirma un pedido desde el QR
  (se apaga al entrar el mesero a la comanda). Los ítems sin estación vuelven a
  nacer pendientes: el cliente puede quitarlos y confirmarlos, y al confirmar
  quedan entregados sin pasar por cocina.
- **1.11** — Con la cuenta ya pedida desde el QR, el cliente no puede seguir
  agregando ítems y el botón Cobrar del pie queda deshabilitado (se cobra desde
  esa cuenta). La propina se puede quitar desde su fila, en el salón y en el QR,
  y se muestra sin cantidad.
- **1.10** — El cliente sugiere desde el QR cómo piensa pagar (el mesero decide
  la forma real) y se quitó el pago en línea del portal. La cuenta que ve el
  cliente se sincroniza cuando el mesero cambia o quita la propina.
- **1.9** — El cliente puede dejar propina desde el QR de la mesa, al pedir su
  cuenta. Se corrigió además el total del tablero de mesas y el del portal QR,
  que calculaban el recargo por servicio incluyendo la propina y resolvían el
  IVA con la tarifa del producto en vez de la del ítem del Menú.
- **1.8** — La forma de pago del cobro admite favorito (estrella): queda
  preseleccionada al abrir el modal.
- **1.7** — Los ítems sin estación de preparación ("Enviar a: Ninguna") entran ya
  entregados: no habilitan el botón Enviar a preparación ni el de Entregar.
- **1.6** — El cobro emite siempre Factura: se quitó la opción de Recibo de venta
  del modal Registrar cobro.
- **1.5** — Propina voluntaria: un monto libre que el mesero agrega en el pie de
  la comanda y se suma al total sin impuestos; se emite como una línea más del
  comprobante (el campo de propina ya lo ocupa el recargo por servicio) y no
  altera el cálculo de ese recargo.
- **1.4** — Los ítems de la carta se muestran y se facturan con la tarifa de IVA
  configurada en el ítem del Menú; antes se usaba la del producto vinculado y la
  del ítem se ignoraba.
- **1.3** — El importe de cada ítem de la comanda (y el de la lista de cobro) se
  muestra con IVA incluido, igual que el precio del catálogo. El pie mantiene el
  desglose de subtotal, IVA, servicio y total.
- **1.2** — Recargo por servicio (el 10%) cobrado como propina, supeditado al
  campo de propina de la factura: se configura por
  establecimiento como obligatorio u opcional, con su porcentaje.
- **1.1** — El total de la comanda se muestra con impuestos incluidos, con el
  subtotal y el IVA desglosado.
- **1.0** — Versión inicial.
