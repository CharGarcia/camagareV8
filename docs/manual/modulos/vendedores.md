---
titulo: Vendedores
resumen: Personas a las que se asignan las ventas, para medir su gestión comercial.
categoria: Ventas
ruta_modulo: modulos/vendedores
tipo: modulo
visibilidad: todos
etiquetas: vendedores, vendedor, comercial, agente, asesor, comision, ventas por vendedor, importar vendedores, carga masiva, excel, vincular usuario con vendedor, usuario del sistema, que el asesor vea solo sus ventas, quien es cada vendedor
version: 1.2
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
Se usa en el **Reporte de Ventas por Vendedor**
(`modulos/reporte_ventas_vendedor`), donde los usuarios de **nivel 1** ven
únicamente las ventas asignadas a su propio vendedor.

- La lista ofrece los usuarios **asignados a la empresa activa** y activos.
- Un usuario solo puede ser **un** vendedor dentro de la misma empresa: si ya
  está tomado, aparece deshabilitado indicando de qué vendedor se trata. En
  empresas distintas sí puede estar vinculado a vendedores distintos.
- Es **opcional**: si se deja en *Sin vincular*, el sistema igual reconoce al
  asesor cuando la **Identificación** del vendedor coincide con la **cédula** de
  su usuario (la misma con la que inicia sesión). El vínculo explícito sirve
  justamente para los casos en que esa cédula no está cargada o no coincide.
- No tiene nada que ver con quién creó la ficha: eso se sigue guardando aparte.

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

- **1.2** — Nuevo campo **Usuario del sistema** en la ficha del vendedor: indica
  qué cuenta de usuario es ese asesor, para que en el Reporte de Ventas por
  Vendedor vea únicamente sus ventas. Un usuario no puede ser dos vendedores de
  la misma empresa. Requiere ejecutar
  `database/vendedores_usuario_vinculado.sql`.
- **1.1** — Carga masiva desde Excel: entidad *Vendedores* en el importador y
  columna VENDEDOR en la plantilla de clientes.
- **1.0** — Versión inicial.
