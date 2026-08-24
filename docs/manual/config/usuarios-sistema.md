---
titulo: Usuarios del Sistema
resumen: Panel para crear usuarios y asignarles a qué empresa(s) pueden acceder.
categoria: Configuración global
ruta_modulo: config/usuarios-sistema
tipo: modulo
visibilidad: admin
etiquetas: usuarios del sistema, crear usuario, asignar empresa, empresa a asignar, invitar usuario, limite de usuarios, usuario existente
version: 1.0
orden: 3
estado: activo
---

**Usuarios del Sistema** (`Configuración → Usuarios del sistema`) crea
usuarios y define a qué empresa(s) pueden acceder. Lo usa tanto el
**superadministrador** (nivel 3, ve todas las empresas activas) como un
**administrador** (nivel 2, solo ve las empresas que él mismo tiene
asignadas).

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

## Historial de cambios

- **1.0** — Primera versión del artículo. Documenta la corrección del
  premarcado accidental de la empresa activa en sesión al crear un usuario
  con varias empresas candidatas.
