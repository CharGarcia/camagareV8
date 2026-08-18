---
titulo: Cargas de inventario
resumen: Movimientos masivos de stock desde un archivo, con aprobación previa si la empresa la exige.
categoria: Inventario
ruta_modulo: modulos/cargas-inventario
tipo: modulo
visibilidad: todos
etiquetas: carga de inventario, ajuste masivo, entrada masiva, salida masiva, conteo fisico, importar stock, aprobacion
version: 1.1
orden: 25
estado: activo
---

Este módulo registra **muchos movimientos de inventario de una vez** desde un
archivo. Es lo que se usa tras un conteo físico o para cargar un stock inicial
extenso.

## Tipos de movimiento

Cada carga es de un tipo, y solo se admiten tres:

| Tipo | Qué hace |
|------|----------|
| Entrada | Suma stock |
| Salida | Resta stock |
| Ajuste | Corrige a la cantidad indicada |

## Cómo se usa

1. Prepare el archivo con los productos y cantidades.
2. Elija el **tipo de movimiento** y la **bodega**.
3. Suba el archivo y revise las líneas.
4. Procese la carga.

Todas las cantidades deben ser **mayores a cero**, y la carga debe tener al menos
una línea.

## Aprobación

La empresa puede exigir que las cargas de inventario sean **aprobadas** antes de
afectar el stock. Con esa opción activa, la carga queda pendiente hasta que un
aprobador la revise, y se avisa por correo a quien corresponda.

Es una medida sensata: una carga masiva mal hecha altera el stock de cientos de
productos de golpe.

Se configura en el módulo **Aprobaciones** (`modulos/aprobaciones-config`): ahí
se activa el proceso *Cargas de inventario*, se eligen los aprobadores y, si se
quiere, un **monto mínimo** por debajo del cual la carga se aplica directamente.
Antes esta configuración estaba en *Empresa → Inventario*.

## Errores frecuentes

- **"Tipo de movimiento inválido"**: debe ser entrada, salida o ajuste.
- **"La carga no contiene líneas para procesar"**: el archivo llegó vacío o
  ninguna fila se pudo interpretar.
- **"La cantidad debe ser mayor a cero"**: revise las filas en cero o negativas.
- **La carga no afecta el stock**: puede estar pendiente de aprobación.

## Historial de cambios

- **1.0** — Versión inicial.
- **1.1** — La configuración de la aprobación se movió al módulo **Aprobaciones**; se agrega monto mínimo.
