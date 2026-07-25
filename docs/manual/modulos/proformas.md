---
titulo: Proformas
resumen: Cotizaciones al cliente y su conversión en factura de venta.
categoria: Ventas
ruta_modulo: modulos/proformas
tipo: modulo
visibilidad: todos
etiquetas: proforma, proformas, cotizacion, cotizar, presupuesto, oferta, convertir a factura
version: 1.0
orden: 15
estado: activo
---

Una **proforma** es la cotización que se entrega al cliente antes de vender. No
tiene efecto tributario ni contable: no se envía al SRI, no mueve inventario y no
genera cuentas por cobrar. Cuando el cliente acepta, se convierte en factura con
un clic.

## Estados y su recorrido

Una proforma pasa por estos estados, y el sistema controla el orden:

| Desde | Puede pasar a |
|-------|---------------|
| Borrador | Aprobada, Anulada |
| Aprobada | Rechazada, Anulada |

Cualquier otro salto se rechaza. El significado de cada uno:

- **Borrador**: se está preparando. **Es el único estado en el que se puede editar.**
- **Aprobada**: el cliente la aceptó. Ya se puede facturar.
- **Rechazada**: el cliente no la tomó. Queda como historial.
- **Anulada**: se descarta.
- **Convertida**: ya generó una factura.

## Cómo se usa

1. Pulse **Nuevo**.
2. Elija el cliente.
3. Agregue los productos con su cantidad, precio y descuento.
4. Guarde. La proforma queda en **borrador**.
5. Envíela al cliente. Si la acepta, cámbiela a **aprobada**.
6. Pulse **Convertir a factura**.

## Convertir en factura

Solo se factura una proforma **aprobada**. La factura se crea **en borrador**,
copiando cliente, productos, cantidades y precios, para que pueda revisarla antes
de enviarla al SRI.

Si intenta convertir una proforma que **ya generó una factura vigente**, el
sistema pide confirmación antes de crear otra. Es a propósito: evita duplicar una
venta por error, pero permite refacturar cuando de verdad hace falta (por ejemplo
si la factura anterior fue anulada).

## Editar

Solo se puede editar una proforma en **borrador**. Si ya está aprobada y necesita
cambiarla, tiene dos caminos: anularla y crear una nueva, o —cuando el cambio es
menor— convertirla a factura y corregir en la factura antes de enviarla.

## Eliminar

**Una proforma convertida no se puede eliminar.** Existe una factura que nació de
ella, y borrarla dejaría esa factura sin su origen. Anúlela si ya no aplica.

En el resto de casos la eliminación es lógica: desaparece del listado pero se
conserva en la base de datos con el usuario y la fecha.

## Permisos

Con **acceso total** se ven las proformas de toda la empresa; sin él, cada
vendedor ve solo las suyas — que suele ser justo lo que se quiere en un equipo
comercial.

## Errores frecuentes

- **"La proforma debe estar aprobada para generar una factura"**: cámbiela a
  aprobada primero.
- **"Solo se pueden editar proformas en estado borrador"**: ya fue aprobada;
  anúlela y cree una nueva, o corrija en la factura resultante.
- **"No se puede eliminar una proforma ya convertida a factura"**: use anular.
- **El cliente no aparece**: regístrelo primero en Clientes, en esta misma empresa.

## Historial de cambios

- **1.0** — Versión inicial.
