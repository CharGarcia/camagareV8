---
titulo: Cómo buscar en el sistema
resumen: Escriba las palabras sueltas, en el orden que quiera y sin tildes; también puede filtrar por campo con "clave:valor" en los listados.
categoria: Primeros pasos
tipo: concepto
visibilidad: todos
etiquetas: buscar, busqueda, buscador, no encuentra, no me aparece, no sale el cliente, tildes, acentos, ñ, mayusculas, filtrar, lupa, buscar cliente, buscar producto, buscar proveedor, autocompletar, sugerencias, palabras sueltas, orden de las palabras, filtros, clave valor
version: 1.2
orden: 15
estado: activo
---

Todos los buscadores del sistema funcionan igual: usted escribe lo que recuerda
y el sistema busca **cada palabra por separado**. No hace falta escribir el
nombre completo, ni en el orden exacto, ni con tildes.

## Escriba las palabras que recuerde, en cualquier orden

El buscador exige que **todas** las palabras que usted escribió aparezcan en el
registro, pero **no** que estén juntas ni en ese orden. Para el cliente
*CARLOS MAURICIO GARCÍA REVELO*, todas estas búsquedas lo encuentran:

| Lo que usted escribe | Por qué lo encuentra |
|----------------------|----------------------|
| `carlos garcia`      | Están las dos palabras, aunque en medio haya otra |
| `garcia carlos`      | El orden no importa |
| `revelo mauricio`    | Sirve cualquier par de palabras del nombre |
| `1712345678`         | También busca por número de identificación |

Lo que **no** encuentra es una palabra incompleta al inicio partida a medias en
varios trozos: `car los garcia` busca "car", "los" y "garcia" por separado.

## Tildes, eñes y mayúsculas

No importan. `compania` encuentra *COMPAÑÍA*, `garcia` encuentra *GARCÍA* y
`MARIA` encuentra *maría*. Escriba como le resulte más cómodo desde el teclado
del celular.

> Si en su servidor las tildes sí marcan diferencia, falta habilitar un
> complemento de la base de datos: avise al administrador del sistema.

## Dónde funciona así

En todos los buscadores del sistema:

- El **buscador de los listados** (la caja con la lupa arriba de cada tabla).
- Los **buscadores de cliente** de Pedidos, Car-Wash, Taller, Servicio Externo,
  Facturación de Consignaciones, Cambio de Producto, Citas, Saldos Iniciales y
  los reportes que filtran por cliente, proveedor o empleado.
- Los **buscadores de producto** de los modales, que además buscan por código.
- Los selectores de **cliente, responsable y usuario** de Tareas y Obligaciones.
- Los buscadores de **Configuración Contable**: cliente, proveedor, producto,
  categoría, marca, empleado, tarifa de IVA y descripciones de ítems.
- El catálogo de **retenciones del SRI** (busca por código y por concepto).
- El buscador de **empresas** del panel de plataforma.
- Los filtros de **Cuentas por Cobrar** (cliente y producto) y de **Cuentas por
  Pagar** (proveedor).

Lo mismo en la aplicación móvil: usa los mismos buscadores del servidor.

## Elegir de la lista de sugerencias

Los buscadores de cliente y de producto muestran una lista debajo del campo
apenas usted escribe **dos letras o más**. Al tocar una opción, el campo queda
con esa selección fija.

Para cambiarla, pulse **Retroceso** o **Suprimir**: con una selección ya hecha,
esas teclas **limpian el campo completo** de una vez, para que empiece a buscar
de nuevo. Mientras usted está escribiendo la búsqueda (todavía sin elegir a
nadie), esas mismas teclas borran letra por letra, como en cualquier campo.

## Filtrar por un campo concreto en los listados

Además del texto libre, el buscador de los listados admite filtros con la forma
`campo:valor`. Se pueden combinar con el texto y entre sí.

| Ejemplo | Qué hace |
|---------|----------|
| `garcia estado:activo` | Texto libre + filtro por estado |
| `cliente:"garcia revelo"` | Valor con espacios: entre comillas |
| `fecha:>=2026-01-01` | Desde esa fecha (también `<=`, `>`, `<`, `=`) |
| `fecha:2026-01..2026-03` | Rango (sirve con números: `100..500`) |
| `estado:borrador,anulado` | Cualquiera de esos valores |
| `-estado:anulado` | Excluye ese valor |

Cada módulo acepta sus propios campos; los que no reconoce, los ignora sin
avisar. Si un filtro no parece surtir efecto, pruebe escribiéndolo como texto
libre.

## Cuando el buscador no encuentra algo

Antes de dar por perdido un registro, revise:

- **Escribió menos de dos letras**: la lista de sugerencias aparece a partir de
  la segunda.
- **El registro está eliminado**: los buscadores nunca muestran registros
  eliminados.
- **El cliente está inactivo**: la mayoría de los buscadores muestran solo
  clientes activos. Actívelo desde **Clientes** si lo necesita.
- **Está en otra empresa**: cada búsqueda trae únicamente datos de la empresa
  activa en ese momento.
- **No lo creó usted**: si su usuario no tiene el permiso de *acceso total* en
  ese módulo, solo ve los registros que creó.

## Historial de cambios

| Versión | Cambio |
|---------|--------|
| 1.2 | Se suman los filtros de Cuentas por Cobrar (cliente y producto) y de Cuentas por Pagar (proveedor), que hasta ahora exigían escribir el texto exacto con sus tildes. |
| 1.1 | Se suman Tareas y Obligaciones, Configuración Contable, retenciones del SRI y el buscador de empresas. |
| 1.0 | Primera versión: búsqueda por palabras sueltas, sin tildes, en todos los buscadores de clientes y terceros. |
