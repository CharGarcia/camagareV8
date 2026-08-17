---
titulo: Configuración contable
resumen: Qué cuentas usa cada tipo de documento al generar su asiento automático.
categoria: Contabilidad
ruta_modulo: modulos/configuracion-contable
tipo: modulo
visibilidad: admin
etiquetas: configuracion contable, cuentas por documento, asiento automatico, parametrizacion, ventas, compras, cierre, tipo de produccion, bien, servicio, filtro por año, periodo, listado de proveedores, listado de clientes
version: 1.2
orden: 5
estado: activo
---

Esta pantalla es la que hace que la contabilidad funcione sola. Define **qué
cuentas del plan usa cada tipo de documento** al generar su asiento: qué cuenta
se debita al facturar, cuál se acredita al cobrar, dónde va el IVA, dónde el
costo de ventas.

Sin esto configurado, los documentos no generan asiento.

## Cómo está organizado

Cada tipo de operación (venta con factura, compra, cobro, pago, traspaso,
consignación, cierre del ejercicio…) tiene su configuración con las cuentas que
necesita.

## De lo general a lo específico

Las cuentas se resuelven por **especificidad**: si una entidad concreta —un
producto, un cliente, una forma de pago— tiene su propia cuenta configurada, esa
manda sobre la configuración general.

Dicho al revés: la configuración general es el valor por defecto, y lo que se
configura en la ficha concreta lo sobreescribe. Es lo que permite que casi todo
funcione con una configuración única y solo las excepciones necesiten atención.

En **Ventas con Factura** (incluye Notas de Crédito, que reusan la misma
configuración) y **Recibos de Venta**, el orden exacto de la cascada es:

1. **Cliente** — si el cliente del documento tiene reglas, todo el documento se
   contabiliza con sus cuentas; lo que no le configuró pasa directo a General.
2. **Producto → Categoría → Marca** — solo si el documento no tiene cliente con
   reglas. Cada línea usa la cuenta de su producto; si no tiene, la de su
   categoría; si no, la de su marca.
3. **Tipo de Producción (Bien / Servicio)** — solo para las líneas que no
   resolvieron cuenta en el paso anterior. Es la dimensión menos específica
   (solo existen dos valores posibles), por eso se evalúa al final, justo antes
   de caer a General.
4. **General** — lo que ningún nivel anterior resolvió.

## Filtrar los listados por año

En las reglas por **Proveedor**, **Cliente**, **Producto**, **Categoría** y
**Marca** hay un selector de año junto al botón que abre el listado (*Proveedores
con compras*, *Clientes con ventas*, *Ítems de compras*, *Categorías*,
*Marcas*…). Ese selector muestra solo los años en los que la empresa tuvo
movimientos.

Al elegir un año, el listado muestra únicamente las entidades que tuvieron
movimiento en ese año: proveedores con compras del año, clientes con ventas del
año, ítems comprados ese año, y las categorías y marcas de los productos que se
movieron ese año. El año elegido aparece como etiqueta en el título del listado.

Con **Todos los años** el listado se comporta como siempre: todas las entidades
con movimiento (y, en el caso de categorías y marcas, todas las registradas, para
poder configurarlas por adelantado).

El módulo del que salen los movimientos depende del tipo de asiento: en
*Adquisiciones de Compras/Servicios* se miran las compras; en *Ventas con
Factura* y *Recibos de Venta*, las ventas.

## Cierre del ejercicio

Entre los tipos configurables está el **cierre del ejercicio**, que necesita dos
cuentas: la de *resumen de resultados* y la de *resultado del ejercicio*. Son las
que permiten cerrar el año llevando la utilidad al patrimonio.

## Cuándo tocar esta pantalla

- Al poner en marcha la empresa.
- Al cambiar el plan de cuentas.
- Cuando un asiento automático va a una cuenta equivocada de forma sistemática.

Si el error es en un solo documento, el problema no está aquí sino en ese
documento o en la ficha de la entidad implicada.

## Errores frecuentes

- **Un documento no genera asiento**: falta configurar su tipo de operación.
- **El asiento va a una cuenta que no corresponde**: revise primero la ficha del
  producto, cliente o forma de pago; su cuenta manda sobre la general.

## Historial de cambios

- **1.2** — Los listados de entidades (Proveedores con compras, Clientes con
  ventas, Ítems de compras, Categorías, Marcas) ahora respetan el selector de
  año. Se agregó ese selector a las reglas por Categoría y por Marca.
- **1.1** — Se agregó la regla por Tipo de Producción (Bien / Servicio) en la
  cascada de Ventas con Factura, Notas de Crédito y Recibos de Venta, entre
  Producto/Categoría/Marca y General.
- **1.0** — Versión inicial.
