---
titulo: Plan de cuentas
resumen: Catálogo de cuentas contables de la empresa, organizado por niveles.
categoria: Contabilidad
ruta_modulo: modulos/plan-cuentas
tipo: modulo
visibilidad: todos
etiquetas: plan de cuentas, cuentas contables, catalogo de cuentas, codigo de cuenta, nivel, mayor, auxiliar
version: 1.0
orden: 10
estado: activo
---

El **plan de cuentas** es el catálogo contable de la empresa. Todo asiento se
escribe sobre estas cuentas, así que es lo primero que hay que tener en orden
antes de contabilizar nada.

## Estructura por niveles

Las cuentas se organizan en niveles, de lo general a lo específico: los primeros
niveles son grupos (activo, pasivo, patrimonio, ingresos, gastos) y los últimos
son las cuentas de movimiento donde realmente se registra.

**Las cuentas de nivel 1 a 4 deben escribirse en MAYÚSCULAS.** El sistema lo
valida al guardar. Es una convención para que los grupos se distingan de un
vistazo de las cuentas de detalle.

## Cómo se registra una cuenta

1. Pulse **Nuevo**.
2. Escriba el **código** siguiendo la estructura de su plan.
3. Escriba el **nombre** (en mayúsculas si es de nivel 1 a 4).
4. Indique el **nivel**.
5. Guarde.

Los tres campos son obligatorios.

## Antes de empezar

Vale la pena dedicar tiempo a esto al inicio. Cambiar el plan de cuentas cuando
ya hay asientos registrados obliga a revisar la configuración contable de cada
módulo y, en el peor caso, a reclasificar movimientos.

Si viene de otro sistema, conviene importar su plan de cuentas antes que
capturarlo a mano.

## Errores frecuentes

- **"Las cuentas de nivel 1 al 4 deben estar en MAYÚSCULAS"**: escriba el nombre
  en mayúsculas o baje el nivel de la cuenta.
- **La cuenta no aparece al configurar un asiento**: revise su nivel; en las
  configuraciones se eligen normalmente cuentas de movimiento, no grupos.

## Historial de cambios

- **1.0** — Versión inicial.
