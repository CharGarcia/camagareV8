---
titulo: Punto de venta (POS)
resumen: Pantalla de venta rápida con apertura y cierre de caja por turno.
categoria: Ventas
ruta_modulo: modulos/caja-pos
tipo: modulo
visibilidad: todos
etiquetas: pos, punto de venta, caja, mostrador, venta rapida, apertura de caja, cierre de caja, arqueo, fondo inicial, servicio, 10%, propina, recargo, punto de emision, establecimiento, turno, restaurante, salon, volver al sistema, sri, autorizacion sri, factura autorizada, numero de autorizacion, tirilla con autorizacion, enviar al sri, firma electronica, cierre de caja, arqueo, formas de pago, cobrado por forma de pago, correo de cierre, detalle del cierre
version: 1.7
orden: 25
estado: activo
---

El **punto de venta** es la pantalla de venta rápida: se abre en ventana propia,
se cobra en pocos clics y se emite la factura sin pasar por el formulario
completo.

## Abrir la caja

Antes de vender hay que **abrir la sesión de caja**:

1. Elija el **establecimiento** y el **punto de emisión**.
2. Indique el **fondo inicial** (el efectivo con el que arranca el turno).
3. Abra la sesión.

El fondo inicial debe ser numérico y no puede ser negativo. Puede ser cero.

Si la empresa tiene **un solo establecimiento con un solo punto de emisión**, la
pantalla los selecciona sola y se llega directo a abrir el turno (o a continuar
el que ya esté abierto). Cuando hay varios, hay que elegir: el punto de emisión
determina por dónde se emite, así que el sistema no elige por usted.

Si en ese punto de emisión **ya hay un turno abierto**, no se abre otro: la
pantalla muestra el turno vigente, con su cajero y su fondo, y el botón
**Continuar**. Un mismo local puede tener varios turnos abiertos a la vez, uno
por punto de emisión.

Esta pantalla se abre en ventana propia, sin el menú del sistema. Si entró y no
va a operar, use **Volver al sistema**, al pie de la tarjeta.

## Vender

Con la caja abierta, se buscan los productos, se arma el pedido, se elige la
forma de cobro y se emite el documento. La factura se genera con su cobro ya
registrado.

**Si se elimina esa factura, el ingreso asociado se revierte automáticamente.**

### La factura se envía al SRI en el momento

Al cobrar, la factura **se envía al SRI y se espera su autorización** antes de
mostrar el aviso de la venta; por eso el botón dice *Cobrando y autorizando…*
unos segundos más que antes. Cuando el SRI autoriza, el aviso lo confirma en
verde con la fecha y la **tirilla sale ya con el número de autorización**, la
fecha y el ambiente — la misma que antes había que ir a buscar a *Facturas de
Venta*.

Tres casos en los que no sale autorizada:

- **El local todavía no cargó su certificado de firma electrónica.** No se
  intenta el envío: la factura queda en borrador y se envía desde *Facturas de
  Venta*, como antes.
- **El SRI no autorizó la factura.** El aviso explica el motivo. Corríjala y
  reenvíela desde *Facturas de Venta*.
- **El SRI no respondió a tiempo.** Queda enviada y pendiente de autorización;
  se reenvía desde *Facturas de Venta*.

En los tres, **el cobro ya está hecho**: el documento está emitido, el inventario
descontado y el Ingreso registrado. La tirilla se puede imprimir igual, con el
botón avisando que va **sin autorización**.

Los **recibos de venta** no cambian: no son comprobantes electrónicos, no van al
SRI y el cobro sigue siendo instantáneo.

> Si al cobrar se corta la conexión, el sistema **ya no da un error a secas**:
> avisa de que la venta pudo haberse registrado igual y pide comprobarlo en
> *Facturas de Venta* antes de volver a cobrar. Cobrar de nuevo emitiría un
> segundo comprobante del mismo consumo.

## El recargo por servicio (el 10%)

Si el establecimiento cobra recargo por servicio, el carrito lo muestra como una
línea propia entre el IVA y el total, y va incluido en el documento como
**propina**.

Se configura en **Empresa → Facturación → Recargo por servicio (restaurantes)**,
la misma configuración que usa el salón, y hace falta tener activo
**¿Mostrar el campo de propina en la factura?**:

- **Obligatorio**: toda venta lo lleva; el cajero no puede quitarlo.
- **Opcional**: la venta arranca con el recargo puesto y el cajero puede
  retirarlo con el enlace **Quitar** de los totales. Vale solo para esa venta:
  la siguiente vuelve a nacer con el recargo.

El valor se calcula sobre el subtotal y se suma después del IVA, así que ese
porcentaje no paga IVA. Quien decide el porcentaje —y si se puede quitar— es la
configuración, no la pantalla: aunque alguien manipule la petición, el servidor
vuelve a resolverlo antes de emitir.

## El turno que usa el salón

El **restaurante** comparte esta misma pantalla: el tablero de mesas no opera sin
un turno abierto, y cuando falta, su aviso trae aquí. Al llegar desde el salón,
**Continuar** devuelve al tablero de mesas en lugar de ir al mostrador.

Por eso, **quien tenga permiso sobre Mesas puede abrir y cerrar el turno aquí
aunque no tenga asignado el submódulo Cajas**: sin turno el salón no trabaja, y
el camino no puede depender de un permiso que el mesero no necesita para nada
más. Ese acceso alcanza solo al turno — la pantalla de **venta** del mostrador
sigue requiriendo permiso propio sobre el Punto de Venta.

Cada usuario elige su punto de emisión al abrir su turno, y las mesas que abra
después se facturan por ese punto.

## Cerrar la caja

Al pulsar **Cerrar caja (arqueo)** la pantalla muestra el turno **forma de pago
por forma de pago**, en tres columnas:

| Columna | Qué es |
|---|---|
| **Forma de pago** | Efectivo, cada banco, Payphone… y entre paréntesis cuántos documentos |
| **Cobrado** | Lo que registró el sistema |
| **Contado** | Lo que usted confirma que realmente entró |

La columna **Contado** llega con el valor del sistema ya escrito: si todo cuadra
no hay nada que tocar, solo confirmar. Si en alguna no coincide, se corrige ese
número y la fila **se marca en rojo** para no tener que buscar dónde está el
descuadre.

**Ya no hay un campo de "monto contado" suelto**: el monto contado del cierre es
la **suma de esa columna**, y se ve en el pie de la tabla junto al total cobrado
y a la diferencia entre ambos, que se recalcula mientras escribe.

El **fondo inicial no entra en el arqueo** —no es un cobro—, pero se recuerda
bajo la tabla porque sí está en el cajón: al contar el efectivo hay que
separarlo de lo cobrado.

Bajo el total aparece **"Propina"**: cuánto de lo cobrado en el turno es
propina y se reparte al personal. Suma las dos que maneja el sistema —el
**recargo por servicio** y la **propina voluntaria** que deja el cliente—, que
viajan en sitios distintos del comprobante. **No se suma al total**: ya está
dentro de él.

Ese arqueo es el que permite detectar faltantes el mismo día, no a fin de mes; y
ahora dice además **por qué medio** falta el dinero, no solo cuánto.

La forma de pago sale del **Ingreso** que generó cada cobro, que es donde queda
registrado con qué se pagó. Los cobros cuyo Ingreso no llegó a generarse
aparecen agrupados como **"Sin forma de pago registrada"**, con un triángulo de
aviso: el dinero está contado en el total, pero no se sabe por dónde entró y hay
que registrarlo desde el módulo *Ingresos*.

### El cierre se envía por correo

Al confirmar, el sistema envía automáticamente el detalle del cierre al **correo
registrado en la empresa** (*Empresa → Datos generales*). El mensaje lleva el
turno y el cajero, las horas de apertura y cierre, el cobrado por forma de pago,
el total de propina del turno, y el arqueo completo: fondo inicial, esperado,
contado y diferencia, más las observaciones si las hubo.

Si la empresa no tiene correo configurado —o el envío falla— **la caja se cierra
igual**: solo aparece un aviso explicando que el detalle no salió. El cierre
nunca depende de que el correo funcione.

## Errores frecuentes

- **No puedo vender**: la caja no está abierta, o no eligió punto de emisión al
  abrirla.
- **"El fondo inicial no puede ser negativo"**: revise el valor; si no hay fondo,
  ponga cero.
- **El arqueo no cuadra**: revise las ventas cobradas en efectivo del turno y si
  hubo salidas de dinero no registradas.
- **"Este punto de emisión ya tiene una caja abierta"**: hay un turno vigente ahí.
  Contínuelo, o ciérrelo antes de abrir uno nuevo.
- **Entré a la caja y no sé cómo volver**: use **Volver al sistema**, al pie de la
  tarjeta; la pantalla se abre sin el menú habitual.

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
Si la empresa **no tiene ninguna forma de pago configurada** para cobros, el punto de
venta no deja cobrar y lo dice: hay que crearlas antes en **Formas de Cobros y
Pagos**. Antes se cobraba igual con un "Efectivo" inventado, y esa venta quedaba sin
su Ingreso —con la Cuenta por Cobrar abierta— sin avisar a nadie.
## Historial de cambios

- **1.7** — El cierre de caja se hace ahora **forma de pago por forma de pago**:
  la pantalla lista lo cobrado en cada una y una columna al lado para confirmar
  lo que realmente entró. **Desaparece el campo de "monto contado"** — el monto
  del cierre es la suma de esa columna, con la diferencia calculada en vivo y las
  filas descuadradas marcadas en rojo. Se muestra también el **total de propina**
  del turno, y al confirmar **se envía el detalle del cierre por correo** a la
  dirección registrada en la empresa.

- **1.6** — La ventana de la tirilla ya no desaparece al cancelar la
  impresión: antes el navegador avisaba igual al imprimir que al cancelar y la
  ventana desaparecía a los 2 segundos, obligando a pedir la tirilla otra vez.
  Ahora avisa de que se cerrará en 10 segundos y deja a mano **Imprimir de
  nuevo** —que reinicia la cuenta— y **Cerrar**.
- **1.5** — El cobro envía la factura al SRI y espera su autorización: la
  tirilla que se imprime desde el aviso de la venta ya sale con el número de
  autorización, sin pasar por *Facturas de Venta*. Si el SRI rechaza o no
  responde a tiempo, el aviso lo dice y deja imprimir sin autorización — el
  cobro no se pierde en ningún caso. Y si se corta la conexión al cobrar, ahora
  se avisa de que la venta pudo registrarse igual, en vez de dar un error seco
  que invitaba a cobrar dos veces.
- **1.4** — La forma de pago SRI del comprobante ya no sale solo del tipo de la
  forma cobrada: cuando ese tipo no la determina (efectivo u *Otro*), se toma la
  configurada en la ficha del cliente y, si no tiene, la de Empresa →
  Facturación. Antes se emitía siempre *01* en esos casos. Sin formas de pago
  configuradas el POS ya no cobra con un *Efectivo* inventado: avisa y no deja
  cerrar la venta, porque esa venta quedaba sin su Ingreso.
- **1.3** — La tirilla se adapta al ancho de papel del driver en vez de imponer el
  suyo, con columnas de ancho proporcional y tipografía sans-serif: ya no sale
  reescalada, con los importes corridos ni con la letra entrecortada en
  impresoras térmicas de 80 mm.
- **1.2** — El establecimiento y el punto de emisión se seleccionan solos cuando
  hay uno solo de cada uno. Botón *Volver al sistema* para salir sin abrir caja.
  Quien tiene permiso sobre Mesas puede abrir y cerrar el turno aunque no tenga
  asignado el submódulo Cajas (solo el turno, no la venta de mostrador).
- **1.1** — Recargo por servicio (el 10%) también en el mostrador, cobrado como
  propina y con la misma configuración que el salón.
- **1.0** — Versión inicial.
