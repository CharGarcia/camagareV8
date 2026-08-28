---
titulo: Comandas
resumen: Pedido de una mesa: lo que consume el cliente antes de cobrarle.
categoria: Restaurante
ruta_modulo: modulos/comandas
tipo: modulo
visibilidad: todos
etiquetas: comandas, comanda, pedido, mesa, restaurante, cocina, anular, cerrar cuenta, servicio, 10%, propina, recargo, total con iva
version: 1.4
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

## Anular con motivo

Anular una comanda **que ya tiene ítems** exige indicar un **motivo**. No es
burocracia: es lo que permite después distinguir una mesa que se levantó sin
consumir de una anulación irregular.

Una comanda vacía se anula sin más.

## Validaciones

| Regla | Detalle |
|-------|---------|
| Mesa disponible | No se abre una comanda sobre una mesa ocupada |
| Comanda abierta | Solo se modifica mientras está abierta |
| Ítem | Hay que seleccionar un producto o un ítem del menú |
| Cantidad | Mayor a cero |
| Motivo de anulación | Obligatorio si la comanda ya tiene ítems |
| Recargo por servicio | Máximo 10% del subtotal; solo se puede quitar si el establecimiento lo tiene como opcional |

## Errores frecuentes

- **"La mesa no está disponible"**: ya tiene una comanda abierta.
- **"La comanda no está abierta; no se puede modificar"**: ya fue cerrada.
- **"Indica un motivo para anularla"**: la comanda tiene consumos registrados.
- **"El recargo por servicio es obligatorio en este establecimiento"**: así está
  configurado en Empresa → Facturación; cámbielo a *opcional* si el salón debe
  poder retirarlo.

## Historial de cambios

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
