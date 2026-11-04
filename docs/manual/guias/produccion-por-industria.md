---
titulo: Cómo configurar la producción según su industria
resumen: Ejemplos paso a paso del módulo Producción para una imprenta (bobina de cartulina a cajas), un laboratorio de cosméticos (granel y envasado), un camal (coproductos) y una panadería (producción rápida).
categoria: Producción
tipo: guia
visibilidad: todos
etiquetas: ejemplo de produccion, como fabricar, imprenta, cajas de carton, bobina, cartulina, cosmeticos, crema, granel, envasado, laboratorio, camal, desposte, cortes de carne, coproductos, panaderia, pan, produccion diaria, fabrica, manufactura, formula en porcentaje, consumo por peso, retazo, subproducto
version: 1.0
orden: 60
estado: activo
---

El módulo Producción es uno solo para todas las industrias: lo que cambia es cómo
se escribe la fórmula y qué funciones se encienden en la **Configuración de
Producción**. Estos ejemplos sirven de punto de partida.

## Imprenta: bobina de cartulina a cajas

1. Cree la cartulina en Productos llevada en **kg** (las bobinas no pesan siempre lo mismo). Al comprarla, registre el número de bobina como **lote**.
2. Fórmula *Caja teclado*, rinde **1 caja**:
   - Cartulina **0,32 kg**, proporcional, merma **10 %** (refile y troquel).
   - Tinta 0,005 kg y goma 0,002 kg.
   - Otras salidas: **subproducto** *Retazo de cartulina* 0,03 kg con su valor de venta por kg.
   - Mano de obra 0,08 y costos indirectos 0,05 por unidad.
3. Orden de 5.000 cajas.
   - En **Consumir**, marque **Por peso** y anote el peso de la bobina antes y después.
   - Al cerrar, anote las cajas buenas, el retazo y las cajas dañadas como merma anormal.

## Cosméticos: granel y envasado

1. En la configuración, encienda **Exigir lote en los insumos** y, si aplica, **Usar cuarentena**.
2. Fórmula del **granel** (producto *Crema – granel* en kg), rinde **100 kg**:
   - Insumos en **% del lote** que sumen 100 %: agua 70 %, glicerina 5 %, …
   - **Vida útil** en días.
   - Pasos de fabricación en **Instrucciones**.
3. Fórmula de **envasado** *Crema 50 g*, rinde 1 unidad: granel 0,052 kg (con sobrellenado), frasco, tapa, etiqueta y estuche.
4. Produzca primero el granel y después el envasado: el granel debe estar en stock.
5. Imprima el **registro de lote** (PDF) para la auditoría de buenas prácticas.
6. Con cuarentena, libere el lote con una **Transferencia de inventario** a la bodega de producto terminado cuando Calidad lo apruebe.

## Camal o desposte: una entrada, varias salidas

1. Fórmula del corte principal (por ejemplo *Lomo*), rinde lo que sale de una canal. Insumo: *Canal* (1 unidad o su peso).
2. Otras salidas:
   - **coproductos** (costilla, molida…) con su cantidad esperada;
   - **subproducto** *Hueso* a su valor neto;
   - **desperdicio** (grasa sin valor).
3. El costo de la canal se reparte entre los cortes por **valor de venta** (recomendado), por kg o por % fijo, según la configuración o la fórmula.

## Panadería: producción diaria

1. Fórmula *Pan enrollado*, rinde **120 panes**, con harina, levadura, sal y manteca.
2. Cada día use **Producción rápida**: elija la fórmula, la cantidad y pulse **Producir**. El sistema consume los insumos y da entrada al pan en un solo paso.

## Qué no hay que hacer

- No ponga **componentes de venta** en la ficha de un producto que se fabrica con una fórmula: sus insumos se descontarían dos veces (al fabricar y al vender). El sistema no deja activar esa fórmula.
- No ajuste a mano el kardex para simular producción: use la orden, que deja el costo, el lote y el asiento.

## Historial de cambios

- **1.0** — Versión inicial.
