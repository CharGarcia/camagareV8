---
titulo: Pantalla de cocina (KDS)
resumen: Pantalla donde la cocina ve los pedidos de las comandas y los marca como listos.
categoria: Restaurante
ruta_modulo: modulos/kds
tipo: modulo
visibilidad: todos
etiquetas: kds, cocina, pantalla de cocina, pedidos, comandas, preparacion, listo, despacho, imprimir ordenes, impresora de cocina, ticket de cocina, comanda en papel, impresora termica, kiosk-printing, reimprimir orden
version: 1.2
orden: 40
estado: activo
---

El **KDS** (pantalla de cocina) muestra en tiempo real lo que se ha pedido desde
las comandas, para que la cocina lo prepare sin depender de tickets en papel.

## Cómo funciona

1. El salón toma la comanda y envía los ítems a cocina.
2. Aparecen en la pantalla, en orden de llegada.
3. La cocina marca cada uno cuando está **listo**.
4. El salón ve que puede servirlo.

## Para qué sirve de verdad

Para dos cosas: que no se pierda ningún pedido, y que se vea **cuánto lleva
esperando cada uno**. Un plato que lleva demasiado tiempo en pantalla es un
cliente que se está impacientando, y eso se detecta antes de que reclame.

## Recomendaciones de uso

- Una pantalla por área de preparación (cocina fría, parrilla, bar) si el volumen
  lo justifica.
- Marcar como listo **cuando de verdad lo está**: una pantalla que no refleja la
  realidad deja de usarse en una semana.

## Imprimir las órdenes en papel

La pantalla puede además **sacar la orden por una impresora térmica**, para
cocinas que trabajan con la comanda pegada al pase. Pantalla y papel no son
excluyentes: el pedido sigue apareciendo en la tarjeta aunque también se imprima.

**Quién imprime, y por qué importa saberlo:** imprime *esta pantalla*, no el
servidor. El sistema corre fuera de la red del restaurante y no puede hablarle a
una impresora del local, así que la orden se pone en una cola y la recoge el
navegador que tiene abierto el KDS de esa estación, que la manda a la impresora
conectada a **ese mismo equipo**.

De eso se desprenden tres condiciones:

- La pantalla de esa estación tiene que estar **abierta**. Si nadie la tiene
  abierta, la orden espera en la cola y sale en cuanto se abra —no se pierde—,
  pero no sale antes.
- La impresora debe estar instalada en ese equipo y ser su **impresora
  predeterminada**.
- El usuario con el que está abierta la pantalla necesita permiso de
  **actualizar** en el módulo. Sin él la pantalla no imprime: es el mismo permiso
  con el que confirma que el papel salió, y sin esa confirmación la misma orden
  se reimprimiría en cada refresco.

**Para que no aparezca el diálogo de impresión** en cada orden, arranque Chrome
con la opción de impresión directa. En el acceso directo de Windows, agregue
`--kiosk-printing` al final del destino:

```
"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing
```

Sin esa opción todo funciona igual, pero alguien tiene que confirmar cada
impresión, lo que en cocina no es práctico.

### Qué sale impreso

Una orden de preparación, no una cuenta: **no lleva precios ni datos
fiscales**. Lleva el nombre de la estación, la mesa, el número de comanda, la
hora, el mesero y los ítems que le tocan a *esa* estación —cocina no recibe lo
de la barra— con la cantidad en grande y las observaciones del plato debajo.

Arriba de la pantalla se ve el estado: **Impresión activa** con el ancho de papel
configurado, o **Solo pantalla** si esa estación no imprime.

### Reimprimir

El botón de impresora en cada tarjeta vuelve a sacar la orden de esa comanda,
marcada como **COPIA**. Es para cuando el papel se atascó o se perdió en el pase.
El mesero también puede hacerlo desde la comanda.

### Se configura en Configuración Restaurante

Todo esto se activa por estación en **Configuración Restaurante**: si imprime,
el ancho del papel (58 u 80 mm), cuántas copias y si la orden sale sola al
enviar a cocina o solo cuando alguien la pide. Ahí también se elige la **estación
predeterminada**, que es la que recibe los ítems agregados desde el stock general
(sin pasar por la carta) — sin ella, esos ítems no llegan a ninguna pantalla.

## Errores frecuentes

- **No llegan pedidos**: revise que la comanda se haya enviado a cocina y que la
  pantalla esté configurada para esa área.
- **No sale la orden por la impresora**: la causa casi siempre es una de tres —
  la pantalla de esa estación no está abierta en el equipo de la impresora, la
  estación no tiene activada la impresión en *Configuración Restaurante*, o el usuario
  con el que está abierta la pantalla no tiene permiso de actualizar. La orden no
  se pierde: sigue en cola y sale al resolverlo.
- **Sale el diálogo de impresión en cada orden**: Chrome no se arrancó con
  `--kiosk-printing`.
- **La orden salió dos veces**: hay dos pantallas de la misma estación abiertas
  con impresión activa. Deje solo la del equipo que tiene la impresora.
- **Quedan pedidos antiguos en pantalla**: no se marcaron como listos.

## Historial de cambios

- **1.2** — Las estaciones y su impresora se configuran en el módulo
  **Configuración Restaurante** (antes en una pestaña del modal de Menú).
- **1.1** — La pantalla puede imprimir las órdenes de su estación en una
  impresora térmica (una cola por estación, con reimpresión marcada como copia).
  Se configura en *Configuración Restaurante*.
- **1.0** — Versión inicial.
