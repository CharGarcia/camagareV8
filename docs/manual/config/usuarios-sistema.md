---
titulo: Usuarios del Sistema
resumen: Panel para crear usuarios y asignarles a qué empresa(s) pueden acceder.
categoria: Configuración global
ruta_modulo: config/usuarios-sistema
tipo: modulo
visibilidad: admin
etiquetas: usuarios del sistema, crear usuario, asignar empresa, empresa a asignar, invitar usuario, limite de usuarios, usuario existente, que usuarios veo, no aparece el usuario, lista de usuarios, usuarios de mi empresa, cambiar cedula, editar cedula, identificacion del usuario, cedula repetida, usuario bloqueado, demasiados intentos, reiniciar intentos, desbloquear usuario, no puede iniciar sesion
version: 1.2
orden: 3
estado: activo
---

**Usuarios del Sistema** (`Configuración → Usuarios del sistema`) crea
usuarios y define a qué empresa(s) pueden acceder. Lo usa tanto el
**superadministrador** (nivel 3, ve todas las empresas activas) como un
**administrador** (nivel 2, solo ve las empresas que él mismo tiene
asignadas).

## Qué usuarios aparecen en la lista

Depende del nivel de quien entra:

- **Superadministrador (nivel 3)**: ve **todos** los usuarios del sistema,
  sin restricción.
- **Administrador (nivel 2)**: ve los usuarios **de las empresas que él
  tiene asignadas** — es decir, cualquier usuario que comparta con él al
  menos una empresa. Además ve **su propia ficha** y los usuarios que él
  mismo invitó aunque todavía no tengan ninguna empresa asignada (recién
  creados). **No** ve superadministradores ni usuarios de empresas que no
  le pertenecen.

Es el mismo criterio que usa **Permisos por módulo**
(`config/permisos-modulos`), así que un administrador ve exactamente el
mismo conjunto de usuarios en ambas pantallas.

Lo que un administrador **ve** es también lo único que puede **modificar**:
editar la ficha, activar/desactivar, reenviar la invitación o eliminar solo
funcionan sobre usuarios de sus empresas.

## Crear un usuario

Al crear, se pide correo, nombre y **la(s) empresa(s) a asignar**:

- **Si solo hay una empresa candidata** (un admin nivel 2 con una sola
  empresa), se asigna automáticamente, sin elegir nada.
- **Si hay varias**, aparece una lista de casillas para elegir una o más —
  **ninguna viene marcada por defecto**: hay que elegir a propósito cuáles
  corresponden al usuario que se está creando.
- El usuario recibe un correo de invitación para completar su registro
  (poner contraseña). Mientras no complete el registro, no puede iniciar
  sesión.
- **Correo ya registrado**: si el correo ya pertenece a un usuario existente,
  no se crea uno nuevo — se le asignan las empresas elegidas al que ya
  existe (opción "asignar si ya existe" en el formulario).

## Editar la identificación (cédula)

La **identificación** del usuario (cédula, RUC o pasaporte) se edita en la
pestaña **General** de su ficha. Es el número con el que esa persona
**inicia sesión**, así que al cambiarlo cambia también su usuario de
ingreso: avísele antes de guardar.

Puede editarla tanto el **administrador** como el **superadministrador**,
sobre los usuarios que cada uno gestiona.

Al guardar se comprueba que **ningún otro usuario del sistema tenga esa
misma identificación** — incluidos los usuarios **inactivos**, porque
conservan su credencial de ingreso por si se reactivan. Si ya está en uso,
el cambio se rechaza con *"Ya existe un usuario con esa identificación"* y
no se guarda nada.

Reglas del campo:

- No puede quedar vacío.
- Máximo **15 caracteres**; se admiten letras, números y guiones.
- En un usuario que **aún no completó su registro**, la identificación que
  se ve es provisional (se guarda su correo hasta que se registre) y él
  mismo la definirá al poner su contraseña. Editar el resto de su ficha
  sigue funcionando con normalidad.

## Usuario bloqueado por intentos fallidos

Tras varios intentos de ingreso con contraseña equivocada, el sistema
**bloquea temporalmente** el acceso de esa identificación (freno contra el
adivinado de contraseñas). El usuario ve *"Demasiados intentos fallidos"* y
debe esperar unos minutos.

El **superadministrador** puede levantarlo al instante. En la ficha del
usuario, pestaña **General**, el bloque **Intentos de acceso** muestra:

- cuántos intentos fallidos lleva y de cuántos permitidos,
- si está **bloqueado** en este momento y cuántos minutos le faltan,
- la fecha del último fallo y la del último acceso correcto.

El botón **Reiniciar intentos** pone el contador a cero y el usuario puede
volver a intentar de inmediato. Los intentos **no se borran**: quedan
marcados como anulados, se siguen viendo en la auditoría de accesos y queda
registrado quién los reinició. La acción se anota además en la bitácora del
sistema (`log_sistema`).

El botón aparece solo para el superadministrador y queda deshabilitado
cuando el usuario no tiene intentos fallidos pendientes.

## Errores frecuentes

- **Un usuario nuevo quedó asignado a una empresa que no le correspondía**:
  revise qué casillas de empresa quedaron marcadas al crearlo — antes de la
  versión 1.0 de este artículo, la empresa que el creador tenía activa en su
  propia sesión venía **premarcada** por defecto en la lista, y si no se
  destildaba a propósito, el usuario nuevo quedaba asignado también a esa
  empresa sin relación con lo que se quería. Ya está corregido: ninguna
  casilla viene marcada por defecto. Para una empresa asignada de más, vaya
  a la ficha del usuario y quítesela.
- **"Alcanzó el límite de usuario(s)"**: la empresa que se le quiere
  asignar ya tiene el máximo de usuarios permitido (`max_usuarios` en la
  ficha de la empresa). Contacte al superadministrador para ampliarlo.
- **Un administrador no ve a un usuario que sí trabaja en su empresa**:
  revise que ese usuario tenga la empresa asignada en su ficha. La lista se
  arma por empresas compartidas, así que un usuario sin esa empresa
  asignada no aparece.
- **"No tiene permiso para gestionar ese usuario"**: se intentó editar,
  eliminar o reenviar la invitación a un usuario que no pertenece a
  ninguna de las empresas del administrador.
- **"Ya existe un usuario con esa identificación"**: otra ficha (activa o
  inactiva) ya usa esa cédula. Búsquela en la lista con el buscador: si es
  una cuenta duplicada en desuso, elimínela y vuelva a intentar.
- **El usuario dice que no puede entrar y su contraseña es correcta**:
  revise el bloque **Intentos de acceso** de su ficha. Si figura como
  bloqueado, use **Reiniciar intentos**.
- **"Falta aplicar la migración … 20260825_login_intentos_anulado.sql"**:
  el servidor todavía no tiene el cambio de base de datos que sostiene el
  reinicio de intentos. Aplíquelo y vuelva a intentar.

## Historial de cambios

- **1.2** — La **identificación (cédula)** pasa a ser editable desde la
  ficha, validando que no la use otro usuario del sistema (incluidos los
  inactivos). Se agregó el bloque **Intentos de acceso**, con el estado del
  bloqueo por intentos fallidos y el botón **Reiniciar intentos** para el
  superadministrador.
- **1.1** — La lista que ve un **administrador (nivel 2)** pasa a armarse
  por las **empresas que él tiene asignadas** (usuarios con empresa en
  común), en lugar de por la asignación interna administrador→usuario. Se
  agregó la sección "Qué usuarios aparecen en la lista" y las acciones
  sobre un usuario (editar, eliminar, reenviar invitación) ahora validan
  ese mismo criterio en el servidor.
- **1.0** — Primera versión del artículo. Documenta la corrección del
  premarcado accidental de la empresa activa en sesión al crear un usuario
  con varias empresas candidatas.
