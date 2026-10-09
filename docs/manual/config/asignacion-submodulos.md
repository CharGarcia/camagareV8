---
titulo: Asignación masiva de submódulos
resumen: Asigna un submódulo a muchos usuarios y empresas de una sola vez, por usuario, nivel, empresa o según otro submódulo que ya tengan.
categoria: Configuración
ruta_modulo: config/asignacion-submodulos
tipo: modulo
visibilidad: superadmin
requiere_permiso_modulo: no
etiquetas: asignacion masiva, asignar submodulo, asignar modulo a todos, permisos en lote, permisos masivos, dar acceso a varios usuarios, modulo nuevo, submodulo nuevo, usuarios que tienen otro modulo, mismo acceso que otro modulo, copiar acceso, acompañar modulo, modulos asignados, administradores, usuarios nivel 1
version: 1.1
orden: 11
estado: activo
---

Herramienta del superadministrador para dar acceso a un submódulo a muchos
usuarios a la vez, en lugar de hacerlo uno por uno en
[Permisos de módulos](config/permisos-modulos).

## Qué es y para qué sirve

Cuando se publica un submódulo nuevo, o cuando un submódulo debe ir siempre
junto con otro (por ejemplo, un reporte que deben ver todos los que ya usan
Facturas de Venta), esta pantalla lo asigna en lote con los permisos elegidos.
Solo la ve el nivel 3 (superadministrador). Los superadministradores nunca son
destinatarios: ya tienen acceso a todo.

## Cómo se usa

1. **Submódulo a asignar**: elija el submódulo que quiere dar.
2. **Destinatarios**: elija una de las opciones:
   - **Usuarios específicos**: busque y marque los usuarios.
   - **Todos los administradores (nivel 2)**, **Todos los usuarios (nivel 1)** o **Todos**.
   - **Usuarios que ya tienen otro submódulo**: elija el submódulo de referencia;
     recibirán el nuevo todos los usuarios que hoy tienen asignado ese otro.
   - **Todos los usuarios de una empresa**.
3. Opcional: **Limitar a una empresa** (aplica a todas las opciones salvo "de una empresa").
4. **Permisos a otorgar**: Ver, Crear, Actualizar, Eliminar y Acceso total.
5. Opcional: **Sobrescribir** si el usuario ya tenía el submódulo con otros permisos.
6. Pulse **Previsualizar**, revise la lista (puede desmarcar filas) y luego **Aplicar asignación**.

## Usuarios que ya tienen otro submódulo

- El submódulo nuevo se asigna **en cada empresa donde el usuario tiene el de
  referencia**, no en todas sus empresas. Si Ana tiene Facturas de Venta en la
  empresa A pero no en la B, recibe el nuevo submódulo solo en A.
- Cuenta como "lo tiene" cualquier asignación con al menos un permiso marcado.
- Solo se toman usuarios activos, empresas activas y empresas que el usuario
  sigue teniendo asignadas.
- Los permisos que se otorgan son los marcados en el paso 3; no se copian los
  permisos que el usuario tenga en el submódulo de referencia.
- El submódulo de referencia debe ser distinto al que se asigna.

## Permisos

Exclusivo del superadministrador (nivel 3). No depende de `modulos_asignados`.

## Reglas de negocio

- La lista de destinatarios se vuelve a calcular en el servidor al aplicar; las
  filas desmarcadas en la previsualización solo pueden quitar destinatarios.
- Si el destino ya tenía el submódulo, se omite salvo que se marque **Sobrescribir**.
- Todo se guarda en una sola transacción: si algo falla, no se asigna nada.
- Cada aplicación queda registrada en el log del sistema (acción
  `asignacion_masiva_submodulo`), con el modo usado y el submódulo de referencia.

## Errores frecuentes

- **"No se encontraron destinatarios con los criterios elegidos."**: nadie (de
  nivel 1 o 2, activo) tiene el submódulo de referencia, o el filtro de empresa
  lo deja vacío.
- **"El submódulo de referencia debe ser distinto…"**: eligió el mismo submódulo
  en el paso 1 y como referencia.

## Historial de cambios

- **1.1** — Nueva opción de destinatarios: **Usuarios que ya tienen otro submódulo**.
- **1.0** — Versión inicial.
