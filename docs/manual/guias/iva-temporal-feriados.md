---
titulo: Cambio temporal de IVA en feriados (empresas de turismo)
resumen: Cómo programar que los productos y servicios pasen al 8% de IVA durante un feriado y vuelvan solos a su tarifa al terminar.
categoria: Inventario
tipo: guia
visibilidad: todos
etiquetas: iva 8, iva 8%, iva del 8, iva reducido, iva diferenciado, tarifa especial, iva feriado, feriado, decreto ejecutivo, iva turismo, servicios turisticos, turismo, hotel, hostal, restaurante, agencia de viajes, alojamiento, alimentos y bebidas, bajar el iva, cambiar el iva, cambiar tarifa de iva, iva temporal, cambio masivo de iva, iva 15 a 8, registro de turismo, luaf, catastro turistico, carnaval, semana santa, noviembre, navidad
version: 1.0
orden: 40
estado: activo
---

En Ecuador, el Presidente puede bajar por **decreto ejecutivo** la tarifa de IVA
del 15% al **8%** para los **servicios turísticos** durante un feriado. Las
empresas de turismo pueden programar ese cambio desde **Productos**: eligen qué
tarifa cambiar, por cuál, a qué productos y entre qué fechas. Al terminar, cada
producto vuelve **solo** a la tarifa que tenía.

## A quién aplica el IVA del 8%

- Solo a los **servicios turísticos** del art. 5 de la Ley de Turismo:
  alojamiento, alimentos y bebidas, transporte turístico, alquiler de vehículos
  para turismo, operación e intermediación, agencias de viajes, organización de
  eventos y congresos, guianza, turismo comunitario, parques temáticos,
  balnearios y termas.
- La empresa debe tener **Registro de Turismo** y **LUAF**, y constar en el
  catastro del Ministerio de Turismo.
- La venta de **bienes** en general no se beneficia, aunque sea feriado.
- Cuenta la **fecha de emisión** del comprobante: debe estar dentro de las fechas
  del decreto (Res. SRI NAC-DGERCGC22-00000010 y sus reformas).

**La empresa es responsable** de elegir bien las fechas, la tarifa y los
productos. El sistema no compara las fechas con el decreto oficial.

## Requisitos previos

- La empresa debe estar marcada como **Empresa de turismo**. Esa marca la pone
  únicamente el superadministrador del sistema, en *Empresas del sistema*.
  Mientras no esté marcada, el botón no aparece.
- El usuario debe tener permiso de **modificar** productos y ser administrador
  de la empresa (nivel 2) o tener **acceso total** en Productos.

## Cómo se programa

1. Abra **Productos** y pulse el botón **IVA temporal** (junto a PDF y Excel).
2. Elija la **Tarifa actual** (por ejemplo 15%). Al lado se ve cuántos
   productos la tienen.
3. Elija la **Tarifa nueva** (por ejemplo 8%, «Tarifa Especial»).
4. En **Aplicar a**, elija *Todos*, *Solo servicios* o *Solo bienes*. Se basa en
   el campo *Tipo* (Bien / Servicio) de cada producto.
5. Indique **Desde** y **Hasta**. La fecha hasta se incluye: el día completo
   lleva la tarifa nueva.
6. Si desea, escriba en **Observaciones** el decreto o la norma (por ejemplo
   «Decreto Ejecutivo 526»).
7. Revise la línea que dice cuántos productos se cambiarán y pulse
   **Programar cambio**.

Si la fecha desde es **hoy**, los productos cambian en ese momento. Si es una
fecha futura, el cambio queda **Programado** y se aplica solo ese día a las
00:00.

## Qué pasa al terminar

Al día siguiente de la fecha hasta, cada producto vuelve a la tarifa **exacta**
que tenía antes del cambio. No hay que hacer nada.

- Si durante el feriado alguien cambió **a mano** la tarifa de un producto, ese
  producto **no** se toca al restaurar: se respeta la decisión posterior. En el
  historial aparece como *omitido*.
- Los productos creados durante el feriado no se cambian: quedan con la tarifa
  con que se crearon.

## Historial, cancelar y restaurar antes

Debajo del formulario está la lista de cambios con su estado:

| Estado | Qué significa |
|--------|---------------|
| Programado | Todavía no llega la fecha desde. Se puede **cancelar** (✕). |
| Aplicado | Los productos tienen la tarifa nueva. Se puede **restaurar ahora** (↺) sin esperar la fecha hasta. |
| Restaurado | Los productos volvieron a su tarifa. |
| Cancelado | Se canceló antes de aplicarse, o la empresa dejó de ser de turismo. |
| Vencido sin aplicar | El período pasó sin que el sistema se usara. No cambió nada. |

El botón de la lista (☰) muestra los productos del cambio con su tarifa anterior,
la aplicada y la que tienen hoy.

## Reglas

- No se pueden programar dos cambios con **fechas cruzadas**.
- La fecha desde no puede ser anterior a hoy, y el período no puede pasar de
  31 días.
- La tarifa nueva debe ser distinta de la actual y estar vigente.
- Todo queda en la bitácora del sistema: quién lo programó, canceló o restauró,
  y cuándo se aplicó y se restauró de forma automática (acciones
  `IVA_TEMPORAL_*` en `log_sistema`).

## Integraciones

El cambio se hace en la **tarifa de IVA del producto**, así que lo toman todas
las pantallas que venden: Facturas de Venta, Punto de Venta, Comandas,
Factura Express QR, Proformas, Recibos, Car-Wash, etc. En el XML del SRI, el 8%
viaja con el código de porcentaje `8`.

Los documentos **ya guardados** no cambian: conservan el IVA con que se
emitieron.

## Errores frecuentes

- **No veo el botón IVA temporal**: la empresa no está marcada como de turismo
  (pídalo al superadministrador), o su usuario no tiene permiso de modificar
  productos con nivel de administrador o acceso total.
- **«Ya hay un cambio de IVA programado o vigente…»**: las fechas se cruzan con
  otro cambio. Cancele o restaure el otro, o elija otras fechas.
- **Un producto no volvió a su tarifa**: alguien la cambió a mano durante el
  período. Revise el detalle del cambio (☰) y corríjala en la ficha.

## Historial de cambios

- **1.0** — Versión inicial.
