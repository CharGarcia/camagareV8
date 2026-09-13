---
titulo: Responsables de Traslado
resumen: Quién lleva físicamente la mercadería en pedidos y consignaciones; aquí se registran, editan y dan de baja.
categoria: Ventas
ruta_modulo: modulos/responsables-traslados
tipo: modulo
visibilidad: todos
etiquetas: responsables de traslado, responsable de entrega, repartidor, chofer, conductor, entregador, asesor, mensajero, transportista interno, pedidos, consignaciones, entregas, app movil, usuarios vinculados, quien ve las entregas
version: 1.1
orden: 62
estado: activo
---

El **responsable de traslado** es la persona de la empresa que lleva físicamente
la mercadería al cliente: el repartidor, el chofer, el asesor que sale con el
producto. Es a quien se le asigna un **pedido** o una **consignación de venta**, y
es también el nombre que aparece en el documento de entrega.

Hasta ahora estos registros solo se podían **crear** desde el botón rápido (+) del
modal de Pedidos y de Consignaciones. Si se escribía mal un nombre o cambiaba un
teléfono, no había dónde corregirlo. Este módulo es esa pantalla: lista, busca,
edita y da de baja.

## Qué es y para qué sirve

- Alimenta el selector **Responsable de entrega** en Pedidos y **Responsable de
  traslado** en Consignaciones de Venta, Retornos y Cambios de Producto.
- Permite corregir datos ya registrados (nombre, identificación, teléfono, correo).
- Permite **desactivar** a quien ya no hace entregas, sin borrar el histórico.
- Es la lista de la que se eligen los responsables que se vinculan a un usuario de
  la **app móvil** (pestaña *Responsables de traslado* en la ficha del usuario):
  un repartidor sin acceso total solo ve, en su teléfono, las entregas de los
  responsables que se le hayan vinculado.

No confundir con **Transportistas**: el transportista es la empresa o persona
externa que figura en la **guía de remisión** ante el SRI. El responsable de
traslado es interno y no se declara.

## Requisitos previos

- Tener una empresa activa seleccionada.
- Tener permiso de **ver** sobre el submódulo *Responsables de traslados* (menú
  **Ventas**). Los permisos se asignan en `/config/permisos-modulos`.

## Cómo se usa

1. Entre a **Ventas → Responsables de traslados**.
2. Pulse **Nuevo** para registrar uno. Solo el **nombre** es obligatorio.
3. Para editar, haga clic en cualquier punto de su fila: se abre el mismo modal
   con los datos cargados.
4. Para que deje de aparecer en los formularios, cámbielo a estado **Inactivo** y
   guarde.
5. Para borrarlo definitivamente, use **Eliminar** (abajo a la izquierda del
   modal). Solo funciona si no está usado en ningún documento.

El listado tiene buscador, ordenamiento por cualquier columna, paginación,
exportación a **PDF** y **Excel**, y permite ocultar o redimensionar columnas: cada
usuario guarda su propia vista.

### Buscar

Escriba texto suelto para buscar por nombre, identificación, teléfono o correo —
palabras sueltas, en cualquier orden y sin importar las tildes. También admite
filtros con `clave:valor`:

| Ejemplo | Qué hace |
|---------|----------|
| `luis` | Busca "luis" en nombre, identificación, teléfono y correo |
| `estado:inactivo` | Solo los dados de baja |
| `nombre:"juan perez"` | Nombre que contenga esa frase |
| `identificacion:0102030405` | Por número de identificación |
| `creado:>=2026-01-01` | Registrados desde esa fecha |
| `-estado:inactivo` | Todos menos los inactivos |

## Usuarios vinculados: quién ve las entregas

En el modal de cada responsable hay una segunda pestaña, **Usuarios vinculados**.
Responde a la pregunta *"¿qué usuarios ven las entregas de este responsable?"*.

Sirve para el módulo **Entregas de Consignaciones** (app móvil y web). El sistema
mira el permiso de **acceso total** del usuario en ese módulo:

- **Con** acceso total → ve **todas** las entregas de la empresa, sin importar los
  vínculos.
- **Sin** acceso total → ve **solo** las entregas de los responsables que tenga
  vinculados.
- **Sin acceso total y sin ningún vínculo → no ve ninguna entrega.** Es la causa
  más común de "al repartidor no le aparece nada en el celular".

Por eso el listado tiene la columna **Usuarios**: muestra cuántos hay vinculados y
marca con un triángulo ámbar (⚠ 0) a los responsables **activos** que no tienen
ninguno. En los inactivos no avisa, porque ya no se usan.

En la tabla de la pestaña, la columna **App móvil** indica con un visto verde si
ese usuario tiene habilitado el acceso desde el teléfono. Si aparece un guion gris,
el vínculo es válido pero solo le servirá entrando por la web.

### Quién puede vincular

Vincular un usuario **no es editar un catálogo: es dar acceso a datos**. Por eso
hace falta **perfil de administrador (nivel 2) o superior**, además del permiso de
*modificar* sobre este módulo. Quien no lo tenga ve la pestaña en **solo lectura**:
puede consultar quién está vinculado, pero no cambiarlo.

Solo se ofrecen usuarios que **tengan asignada la empresa activa** y que no sean
superadministradores (un superadministrador ya ve todas las entregas, así que el
vínculo no le cambiaría nada). Si el usuario que busca no aparece, primero
asígnele la empresa en **Configuración → Usuarios del sistema**.

### La misma relación, desde el otro lado

Este vínculo también se administra en **Configuración → Usuarios del sistema →**
pestaña **Responsables de traslado**, pero al revés: ahí se parte del usuario y se
le agregan responsables. Es la misma información y ambos sitios se actualizan
mutuamente; use el que le resulte más natural:

| Quiere saber… | Vaya a |
|---------------|--------|
| Qué usuarios entregan lo de este responsable | Responsables de Traslado → pestaña *Usuarios vinculados* |
| Qué responsables representa este usuario | Configuración → Usuarios del sistema → pestaña *Responsables de traslado* |

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre completo | Sí | Nombre de la persona. Se guarda en MAYÚSCULAS. Máximo 100 caracteres. No puede repetirse dentro de la misma empresa |
| Identificación | No | Cédula, RUC o pasaporte. Máximo 20 caracteres, solo letras, números y guiones. Si se llena, no puede repetirse en la empresa |
| Teléfono | No | Número de contacto. Máximo 20 caracteres |
| Correo electrónico | No | Se guarda en minúsculas. Máximo 150 caracteres |
| Estado | Sí | **Activo** aparece en los formularios de Pedidos y Consignaciones; **Inactivo** no |

Al pie del modal se muestra, en letra pequeña, cuándo se creó y cuándo se modificó
por última vez el registro.

## Permisos

| Permiso | Qué habilita |
|---------|--------------|
| Ver | Entrar al módulo, buscar y exportar |
| Crear | Botón **Nuevo** (y el botón + de Pedidos y Consignaciones) |
| Modificar | Guardar cambios sobre un responsable existente |
| Eliminar | Botón **Eliminar** del modal |
| Acceso total | Ver los responsables de **toda la empresa**. Sin este permiso, el usuario solo ve los que él mismo creó |

Aparte de esos permisos, **vincular o quitar usuarios exige perfil de
administrador (nivel 2 o superior)**, porque concede acceso a datos. Con permiso de
modificar pero nivel 1, la pestaña *Usuarios vinculados* queda en solo lectura.

El **superadministrador** (nivel 3) ve siempre todos los registros de la empresa
activa.

## Reglas de negocio

- **El nombre no se repite** dentro de una empresa, sin distinguir mayúsculas. Dos
  responsables con el mismo nombre solo generan confusión al elegirlos en un pedido.
- **La identificación tampoco se repite**, cuando se llena. Como es opcional, los
  registros sin identificación no chocan entre sí.
- Un responsable **eliminado libera su identificación**: se puede volver a crear
  otro con la misma cédula.
- **No se puede eliminar un responsable que ya figura en documentos.** El sistema
  revisa pedidos, consignaciones de venta, retornos, cambios de producto y usuarios
  vinculados, y avisa cuántos encontró. Para sacarlo de circulación conservando el
  histórico, use el estado **Inactivo**.
- **Nada se borra físicamente.** Eliminar marca el registro como eliminado; los
  documentos antiguos siguen mostrando el nombre.
- Los responsables son **por empresa**: los de una empresa no se ven desde otra.
- Toda alta, cambio y baja queda registrada en la bitácora del sistema
  (`log_sistema`), con el usuario, la fecha y los valores anterior y nuevo.

## Integraciones con otros módulos

| Módulo | Qué usa |
|--------|---------|
| **Pedidos** | Selector *Responsable de entrega* (obligatorio al guardar un pedido). El botón + crea uno sin salir del pedido |
| **Consignaciones de Venta** | Selector *Responsable de traslado*, y sale impreso en el documento |
| **Retornos de consignación** y **Cambios de producto** | Heredan el responsable del documento de origen |
| **Entregas de Consignaciones** (app móvil) | Un usuario sin acceso total solo ve las entregas de los responsables que tenga vinculados (pestaña *Usuarios vinculados*) |
| **Usuarios del sistema** | Administra el mismo vínculo desde la ficha del usuario; los cambios hechos en cualquiera de los dos sitios se ven en el otro |
| **Reporte de Pedidos** y **Reporte de Inventarios** | Muestran y permiten filtrar por responsable |

El alta rápida desde Pedidos y desde Consignaciones aplica exactamente las mismas
validaciones que este módulo: son el mismo proceso por dentro.

## Errores frecuentes

- **"Ya existe un responsable de traslado con ese nombre en esta empresa"**: el
  nombre ya está registrado. Búsquelo en el listado; probablemente esté **Inactivo**
  y baste con reactivarlo.
- **"Ya existe otro responsable de traslado con esa identificación"**: la cédula o
  RUC está en otro registro activo de la misma empresa.
- **"No se puede eliminar: el responsable está siendo usado en N pedido(s)…"**: ya
  tiene documentos asociados. Cámbielo a **Inactivo** en lugar de eliminarlo.
- **No aparece en el selector de un pedido**: está en estado **Inactivo**, o
  pertenece a otra empresa, o lo creó otro usuario y usted no tiene el permiso de
  **acceso total**.
- **El menú muestra la opción pero al entrar dice que no tiene permiso**: falta
  asignar el submódulo en `/config/permisos-modulos`.
- **Al repartidor no le aparece ninguna entrega en el celular**: no tiene *acceso
  total* en Entregas de Consignaciones y no está vinculado a ningún responsable.
  Búsquelo en el listado por la columna **Usuarios** (⚠ 0) y vincúlelo.
- **"El usuario no tiene asignada esta empresa"**: solo se puede vincular a quien
  ya trabaje en la empresa activa. Asígnesela en Configuración → Usuarios del
  sistema y vuelva a intentarlo.
- **"Solo un administrador puede vincular usuarios"**: su perfil es de nivel 1.
  Puede consultar los vínculos, pero no cambiarlos.
- **No encuentro al usuario en la lista de "Agregar usuario"**: o ya está
  vinculado, o no tiene la empresa asignada, o es superadministrador (a ese no hace
  falta vincularlo: ya ve todas las entregas).

## Historial de cambios

- **1.1** — Pestaña *Usuarios vinculados* en el modal (vista inversa del vínculo de
  Usuarios del sistema, editable solo con perfil de administrador) y columna
  *Usuarios* en el listado, que avisa de los responsables activos sin ningún
  usuario vinculado.
- **1.0** — Módulo inicial: listado con búsqueda, orden, paginación, PDF y Excel;
  alta, edición y baja lógica; bloqueo de eliminación cuando el responsable ya está
  en uso. El alta rápida de Pedidos y Consignaciones pasa a usar la misma lógica.
