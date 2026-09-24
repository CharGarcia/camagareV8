---
titulo: Permisos de módulos
resumen: Cómo se asignan los accesos por submódulo y qué significa cada permiso.
categoria: Configuración
ruta_modulo: config/permisos-modulos
tipo: modulo
visibilidad: superadmin
requiere_permiso_modulo: no
etiquetas: permisos, accesos, roles, niveles, usuarios, modulos asignados, acceso total, buscar usuario, buscar por correo, buscar por cedula, identificacion, email, buscador, buscar empresa, ruc, razon social, nombre comercial, asignar empresa, empresa no asignada, crear usuario, invitacion, correo existente, pdf, imprimir, imprimir permisos, reporte de permisos, descargar permisos, acta de permisos
version: 1.7
orden: 10
estado: activo
---

Esta pantalla define **qué puede hacer cada usuario en cada submódulo** del
sistema, empresa por empresa.

## Niveles de usuario

- **Nivel 3 — Superadministrador**: acceso total a todos los módulos, empresas y
  configuraciones. No necesita asignaciones.
- **Nivel 2 — Administrador**: accede a los módulos y configuraciones que el
  superadministrador le asigne.
- **Nivel 1 — Usuario**: accede solo a los módulos y empresas asignados.

Ningún usuario, salvo el nivel 3, puede ver información de empresas que no tenga
asignadas.

## Los cinco permisos

- **Ver**: consultar el listado del submódulo.
- **Crear**: registrar nuevos documentos.
- **Modificar**: editar los existentes.
- **Eliminar**: dar de baja (siempre de forma lógica).
- **Acceso total**: ver los registros de *toda la empresa*.

## Registros propios frente a acceso total

Es la distinción que más consultas genera. Si un usuario **no** tiene acceso
total, ve únicamente *los registros que él mismo creó*. Con acceso total ve los
de todos los usuarios de esa empresa.

Por eso un vendedor nuevo entra y ve el listado vacío: no es un error, todavía no
ha creado nada y no tiene acceso total.

## Cómo asignar permisos

1. **Busque el usuario.** El primer campo es un buscador: escriba parte del
   **nombre**, de la **cédula / identificación** (cédula, RUC o pasaporte) o del
   **correo** y elija de la lista. Puede combinar varias palabras (por ejemplo
   `maria gmail`): se muestran los usuarios que contengan todas ellas en
   cualquiera de esos datos. Cada opción muestra el correo debajo del nombre.
   Arranca vacío (antes mostraba su propio nombre, lo que hacía pensar que no se
   podía cambiar). Si el usuario no
   aparece entre los primeros de la lista, siga escribiendo: al teclear dos o más
   letras se consulta el resto de usuarios.
2. Pulse **Seleccionar empresa** y elija la empresa (también es un buscador).
   Se puede escribir parte del **nombre comercial**, de la **razón social** o del
   **RUC**; cada opción muestra el nombre comercial con el RUC y, debajo, la razón
   social cuando es distinta. Si el usuario tiene una sola empresa asignada, se
   selecciona sola.
3. Marque los permisos submódulo por submódulo.
4. Guarde. El cambio se aplica en la siguiente pantalla que abra el usuario.

## Qué alcance tiene cada quien al buscar

El buscador de usuarios y el de empresas no muestran lo mismo según quién entre:

- **Superadministrador (nivel 3)**: busca entre **todos los usuarios activos del
  sistema** y entre **todas las empresas activas**, estén o no asignadas a ese
  usuario. En el desplegable de empresas se separan en dos grupos: *Empresas
  asignadas al usuario* y *Otras empresas del sistema*.
- **Administrador (nivel 2)**: solo las empresas que él mismo tiene asignadas y
  solo los usuarios de esas empresas. **Nunca ve superadministradores.** De esos
  usuarios administra únicamente los módulos que él tiene asignados en cada
  empresa: lo que el administrador no tiene, no aparece en la lista y no lo puede
  entregar.

En ningún caso se listan usuarios inactivos ni eliminados.

## Asignar una empresa desde esta pantalla

Cuando el superadministrador elige una empresa que el usuario todavía **no**
tiene asignada, la pantalla lo advierte y ofrece dos caminos, ambos válidos:

- **Asignar empresa ahora**: el botón del aviso crea la asignación de inmediato.
- **Marcar permisos y guardar**: la asignación se crea sola al guardar el primer
  permiso. No hace falta salir a *Asignar empresas*.

Esto importa porque el permiso y la asignación son cosas distintas: sin la
asignación de empresa, un usuario de nivel 1 o 2 no puede entrar a la empresa
aunque tenga permisos guardados en ella. Un superadministrador no necesita
asignación: siempre ve todas las empresas.

La asignación creada aquí queda registrada en el log del sistema, igual que si se
hubiera hecho desde *Asignar empresas*.

## Crear un usuario desde esta pantalla

El botón **Crear usuario** pide solo dos cosas: el **correo** y la **empresa**
que se le asignará (un buscador, igual que los demás). El administrador solo ve
ahí sus propias empresas; el superadministrador, cualquier empresa activa.

Qué pasa según el correo:

- **Correo nuevo**: se crea el usuario y se le envía la invitación para que
  complete su registro y defina su contraseña. El nombre no se pide aquí: queda
  uno provisional tomado del correo y la persona escribe el suyo al registrarse.
- **Correo que ya existe en el sistema**: **no** se crea otro usuario ni se le
  reenvía ninguna invitación. Solo se le asigna la empresa y la pantalla lo avisa
  ("el usuario ya existe y fue asignado a la empresa").

En ambos casos, al aceptar el mensaje la pantalla entra directamente a los
permisos de ese usuario en esa empresa, que es el paso que sigue.

El cupo de usuarios se valida contra la **empresa elegida en el modal**, no
contra la empresa en la que esté trabajando: un administrador puede tener una
empresa llena y otra con espacio.

## Imprimir los módulos asignados (PDF)

El botón **PDF** de la barra del listado descarga los permisos del usuario que
está en pantalla, en la empresa seleccionada. Sirve para dejar constancia de qué
se le entregó a cada persona: firmarlo, adjuntarlo a un expediente o revisarlo
sin entrar al sistema.

Qué trae el documento:

- Empresa y RUC, nombre y cédula del usuario, su nivel y la fecha de generación.
- La lista **agrupada por módulo**, con una **X** en cada permiso concedido
  (Ver, Crear, Actualizar, Eliminar y Ver Todo) y un guion donde no lo hay.
- Al pie, el recordatorio de qué significa *Ver Todo*.

Dos detalles a tener en cuenta:

- **Solo se imprime lo asignado.** Los submódulos sin ninguna casilla marcada no
  aparecen; el PDF es la lista de lo que el usuario puede hacer, no el catálogo
  completo del sistema.
- **Se imprime lo mismo que usted ve.** Un administrador obtiene solo los
  submódulos que él administra, igual que en la pantalla. Si necesita el detalle
  completo de un usuario, debe generarlo un superadministrador.

Para un usuario de nivel 3 el PDF indica que es superadministrador y que accede
a todo el sistema sin necesidad de asignación.

## Por qué un módulo manda al tablero

Cuando un usuario entra a un módulo y el sistema lo devuelve al tablero, es
porque no tiene el permiso **VER** de ese submódulo en la empresa activa. Revise
en este orden:

1. **El permiso está marcado para esa empresa.** Los permisos son por empresa: un
   usuario puede tener Movimientos de Inventario en una empresa y no en otra.
   Cambie la empresa en el selector de esta pantalla y confírmelo.
2. **Está marcado VER, no solo Crear o Modificar.** Sin VER no se abre el módulo.
   Desde esta versión el menú tampoco muestra los submódulos sin VER, así que un
   enlace que ya no aparece suele ser esto.
3. **El submódulo está activo** en el menú (los desactivados ya no se muestran).
4. **La ruta del submódulo coincide** con la del módulo (por ejemplo
   `modulos/inventario`). Si está mal escrita, el sistema no puede relacionar el
   permiso con el módulo.

El usuario debe recargar la pantalla después de que le asignen el permiso: el
cambio se aplica en la siguiente página que abra.

## Historial de cambios

- **1.7** — El buscador de usuario (selección principal y *Copiar permisos a
  otro usuario*) busca también por **correo**, además de nombre y cédula /
  identificación, y acepta varias palabras combinadas. Cada opción muestra el
  correo del usuario debajo del nombre.
- **1.6** — Nuevo botón **PDF**: descarga los módulos y submódulos asignados al
  usuario en la empresa seleccionada, agrupados por módulo y con el detalle de
  cada permiso. Incluye los datos del usuario y de la empresa para poder
  archivarlo o firmarlo.
- **1.5** — Los buscadores de empresa de esta pantalla (selección principal,
  *Copiar desde otra empresa*, *Copiar permisos a otro usuario* y *Crear usuario*)
  buscan por **nombre comercial, razón social y RUC**. Antes la razón social no se
  tenía en cuenta y una empresa cuyo nombre comercial no coincide con su razón
  social no se encontraba. Cada opción muestra ahora la razón social debajo del
  nombre comercial.
- **1.4** — El modal **Crear usuario** pide solo correo y empresa (buscador), con
  el alcance de cada nivel: el administrador solo puede asignar sus empresas. Si el
  correo ya está registrado no se reenvía la invitación: se asigna la empresa y se
  avisa que el usuario ya existía. Al terminar se entra directo a los permisos de
  ese usuario. El cupo de usuarios se valida contra la empresa elegida y no contra
  la empresa activa. Los usuarios inactivos o eliminados dejaron de aparecer en el
  buscador.
- **1.3** — El superadministrador busca ahora entre todos los usuarios activos y
  todas las empresas activas del sistema, no solo las empresas ya asignadas al
  usuario. Las empresas sin asignar aparecen en un grupo aparte del desplegable y
  se pueden asignar desde la misma pantalla (botón del aviso) o automáticamente al
  guardar el primer permiso. Antes, un usuario sin empresas asignadas dejaba la
  pantalla sin salida.
- **1.2** — El campo de usuario ahora se comporta como buscador: empieza vacío,
  con la indicación de escribir nombre o cédula, y busca también en el servidor
  cuando el usuario no está en la lista precargada. Antes aparecía seleccionado el
  usuario en sesión y daba la impresión de que no se podía buscar.
- **1.1** — El menú deja de mostrar submódulos sin permiso VER o desactivados
  (antes aparecían y al abrirlos devolvían al tablero). El permiso se relaciona
  con el módulo por su ruta registrada en el menú, no por un número fijo, así que
  ya no depende de que los identificadores coincidan entre instalaciones. Se
  amplió el apartado "Por qué un módulo manda al tablero".
- **1.0** — Versión inicial.
