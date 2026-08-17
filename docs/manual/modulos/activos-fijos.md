---
titulo: Activos fijos
resumen: Registro de los bienes de la empresa y su depreciación mensual.
categoria: Contabilidad
ruta_modulo: modulos/activos-fijos
tipo: modulo
visibilidad: todos
etiquetas: activos fijos, activo, depreciacion, bienes, maquinaria, vehiculos, muebles, vida util, linea recta
version: 1.0
orden: 60
estado: activo
---

El módulo de **Activos fijos** registra los bienes de la empresa —vehículos,
maquinaria, muebles, equipos— y calcula su **depreciación** mes a mes.

## Dar de alta un activo

Hay dos caminos:

- **Desde una compra**: se toma el bien de una factura de compra ya registrada,
  con su valor.
- **Manual**: se captura directamente, para bienes que ya existían antes de usar
  el sistema.

En ambos casos hay que elegir la **categoría** del activo, que debe existir y
estar **activa**.

## Las cuentas contables van en el activo

Las tres cuentas contables (activo, depreciación acumulada y gasto por
depreciación) se configuran **en cada activo**, no en su categoría. La categoría
agrupa y propone; el activo manda.

Es la parte que más confusión genera: si la depreciación va a una cuenta que no
esperaba, revise las cuentas del activo concreto, no las de la categoría.

## Depreciación

Se calcula por **línea recta** y se ejecuta **en lote, una vez al mes**,
generando un único asiento consolidado en lugar de un asiento por bien. Así la
contabilidad no se llena de cientos de movimientos mensuales.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Categoría | Obligatoria, debe existir y estar activa |
| Nombre / descripción | Obligatorio |

## Qué pasa al eliminar un activo

Un activo con **depreciaciones ya contabilizadas no se puede eliminar** — eso no
cambió.

Si todavía no depreció, sí se elimina, y al hacerlo se **anula también su asiento
de alta**. Antes ese asiento sobrevivía al activo y seguía sumando en el Balance.
El asiento no se borra: queda en estado **anulado**, así que el rastro se conserva.

Si la fecha de alta cae en un **período contable cerrado**, la eliminación se
rechaza. Reabra el período si realmente necesita eliminarlo.

## Errores frecuentes

- **"La categoría seleccionada está inactiva"**: actívela o elija otra.
- **La depreciación va a la cuenta equivocada**: revise las cuentas del activo,
  no las de la categoría.
- **Un activo no deprecia**: compruebe su fecha de alta, su vida útil y que no
  esté ya totalmente depreciado.
- **"No se puede eliminar: el activo ya tiene depreciaciones contabilizadas"**:
  es correcto. Dé de baja el activo en lugar de eliminarlo.
- **"No se puede registrar el asiento: la fecha ... corresponde a un período
  contable cerrado"** al eliminar: la eliminación anula el asiento de alta, y eso
  no se puede hacer en un período cerrado.

## Historial de cambios

- **1.1** — Eliminar un activo fijo ahora **anula su asiento de alta**. Antes ese
  asiento sobrevivía al activo y seguía sumando en el Balance. Efecto secundario
  esperado: ya no se puede eliminar un activo cuya fecha de alta esté en un
  período contable cerrado.
- **1.0** — Versión inicial.
