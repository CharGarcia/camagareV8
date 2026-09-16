---
titulo: Vendedores
resumen: Personas a las que se asignan las ventas, para medir su gestión comercial.
categoria: Ventas
ruta_modulo: modulos/vendedores
tipo: modulo
visibilidad: todos
etiquetas: vendedores, buscar vendedor, buscador, filtros, filtrar vendedores, vendedores sin clientes, chips, vendedor, comercial, agente, asesor, comision, ventas por vendedor, importar vendedores, carga masiva, excel, vincular usuario con vendedor, usuario del sistema, que el asesor vea solo sus ventas, quien es cada vendedor, cartera del vendedor, clientes asignados
version: 1.4
orden: 50
estado: activo
---

Los **vendedores** son las personas a quienes se atribuye cada venta. Se eligen
en la factura y permiten después analizar las ventas por vendedor.

Un vendedor no es lo mismo que un usuario del sistema: puede haber vendedores que
no entran al sistema, y usuarios que no venden. Cuando sí son la misma persona,
la ficha del vendedor permite decirlo (campo **Usuario del sistema**).

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** y la **identificación** (ambos obligatorios).
3. Añada el correo si quiere (se valida el formato).
4. Si ese vendedor entra al sistema con su propia cuenta, elíjala en **Usuario
   del sistema** (ver el apartado siguiente).
5. Guarde.

## Usuario del sistema (para que el asesor vea solo sus ventas)

El campo **Usuario del sistema** dice qué cuenta de usuario *es* este vendedor.
Se usa en el **Reporte de Ventas**, el **Reporte de Ventas por Vendedor** y
**Cuentas por Cobrar**: un usuario de **nivel 1** sin el permiso *Acceso
total* en esos módulos ve únicamente lo de su vendedor, es decir los documentos
que llevan su nombre y, si un documento no tiene vendedor, los de los clientes
que tiene asignados. El filtro *Vendedor* le aparece fijo en su nombre.

- La lista ofrece los usuarios **asignados a la empresa activa** y activos.
- Un usuario solo puede ser **un** vendedor dentro de la misma empresa: si ya
  está tomado, aparece deshabilitado indicando de qué vendedor se trata. En
  empresas distintas sí puede estar vinculado a vendedores distintos.
- Es **opcional**: si se deja en *Sin vincular*, el sistema igual reconoce al
  asesor cuando la **Identificación** del vendedor coincide con la **cédula** de
  su usuario (la misma con la que inicia sesión). El vínculo explícito sirve
  justamente para los casos en que esa cédula no está cargada o no coincide.
- No tiene nada que ver con quién creó la ficha: eso se sigue guardando aparte.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del listado (nombre,
identificación, correo y teléfono) y además en la dirección y en el usuario que
registró al vendedor. La columna **Estado** no entra en la búsqueda libre: para
filtrar por ella use la ventana de filtros. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes: *tipan luis* encuentra a
*Luis Tipan*. Para limpiar, borre el texto o pulse Escape en el cuadro. Mientras
busca, aparece un **círculo girando** al final del cuadro y la tabla se ve
atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento.
La ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Vendedor | Nombre, identificación, estado (activo / inactivo), con o sin clientes asignados |
| Contacto | Correo, con o sin correo registrado, teléfono, dirección |
| Usuario del sistema | Usuario del sistema vinculado y si está o no vinculado a un usuario (solo aparece cuando la base ya tiene el campo *Usuario del sistema*) |
| Registro | Fecha de registro (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), usuario que registró |

*Con clientes asignados* son los vendedores que figuran como vendedor de al
menos un cliente. Los selectores de usuario listan solo los usuarios que
aparecen en los vendedores de la empresa.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. Los botones de **PDF** y **Excel** exportan con
la misma búsqueda y filtros que haya en pantalla.

## Carga masiva desde Excel

Si tiene muchos vendedores, no hace falta registrarlos uno por uno: en
*Configuración → Importador desde Excel* está la entidad **Vendedores**. La
plantilla pide identificación y nombre (obligatorios) y correo, teléfono y
dirección (opcionales). Si la identificación ya existe, el vendedor se actualiza
en lugar de duplicarse.

En esa misma herramienta, la plantilla de **Clientes** trae la columna VENDEDOR
para asignar cada cliente a su vendedor por identificación o nombre. Detalle en
la guía *Importar datos desde Excel*.

## Errores frecuentes

- **"El formato del correo electrónico no es válido"**: revise la dirección.
- **No aparece al facturar**: pertenece a otra empresa o fue eliminado.
- **"Ese usuario ya está vinculado al vendedor …"**: esa cuenta ya es otro
  vendedor en esta empresa. Quite primero el vínculo en esa otra ficha.
- **"El usuario seleccionado no está asignado a esta empresa o no está
  activo"**: asigne primero el usuario a la empresa en *Configuración →
  Usuarios*.
- **No aparece el campo Usuario del sistema**: falta ejecutar en la base
  `database/vendedores_usuario_vinculado.sql`. Hasta entonces el asesor se
  reconoce solo por la cédula.

## Historial de cambios

- **1.4** — Nuevo buscador del listado: lo que se escribe se busca por palabras
  sueltas, en cualquier orden y sin tildes, en nombre, identificación, correo,
  teléfono, dirección y usuario que registró (antes solo la frase completa en
  nombre, identificación y correo). Los filtros pasan a una **ventana propia**
  (botón del embudo, se aplican con *Aplicar*) con criterios nuevos: con/sin
  clientes asignados, con/sin correo, usuario del sistema vinculado, fecha de
  registro y usuario que registró. Se corrigen tres fallas: el filtro de estado
  daba error, los filtros no refrescaban la tabla y, después de buscar o cambiar
  de página, las columnas quedaban descuadradas respecto de los títulos.
- **1.3** — El apartado *Usuario del sistema* explica dónde se usa ese vínculo:
  en el Reporte de Ventas, el Reporte de Ventas por Vendedor y Cuentas por
  Cobrar, para que el usuario de nivel 1 sin *Acceso total* vea solo lo de su
  vendedor.
- **1.2** — Nuevo campo **Usuario del sistema** en la ficha del vendedor: indica
  qué cuenta de usuario es ese asesor, para que en el Reporte de Ventas por
  Vendedor vea únicamente sus ventas. Un usuario no puede ser dos vendedores de
  la misma empresa. Requiere ejecutar
  `database/vendedores_usuario_vinculado.sql`.
- **1.1** — Carga masiva desde Excel: entidad *Vendedores* en el importador y
  columna VENDEDOR en la plantilla de clientes.
- **1.0** — Versión inicial.
