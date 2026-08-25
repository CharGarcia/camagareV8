---
titulo: Empresas del Sistema
resumen: Panel del superadministrador para dar de alta empresas, gestionar sus establecimientos, usuarios, documentos y suscripción.
categoria: Configuración global
ruta_modulo: config/empresas-sistema
tipo: modulo
visibilidad: superadmin
etiquetas: empresas del sistema, crear empresa, alta de empresa, establecimientos, sucursales, matriz, usuarios asignados, documentos legales, suscripcion, empresas del grupo, eliminar establecimiento, establecimiento activo, un solo establecimiento activo
version: 1.6
orden: 1
estado: activo
---

**Empresas del Sistema** (`Configuración → Empresas del sistema`) es el panel
exclusivo del **superadministrador** (nivel 3) para administrar todas las
empresas que corren en la plataforma: crearlas, editar sus datos, asignarles
usuarios y establecimientos, y ver su estado de suscripción y de documentos
legales. Es distinto del módulo **Empresa** (autoservicio), que cada empresa
usa para configurarse a sí misma — aquí se administran **todas** las empresas
del sistema.

## Qué es y para qué sirve

Punto de entrada para dar de alta una empresa nueva (con su primer
establecimiento, un usuario administrador opcional y el envío de los
documentos legales), y para intervenir en la configuración de una empresa ya
existente cuando hace falta ayuda que el propio cliente no puede resolver
desde el módulo Empresa: agregar/quitar establecimientos, revisar usuarios
asignados, reenviar documentos legales, etc.

## Establecimientos de una empresa

Cada empresa puede tener uno o varios **establecimientos** (locales físicos)
registrados, aunque en la práctica solo opera con **uno a la vez**. Desde la
ficha de la empresa, pestaña Establecimiento, el superadministrador puede:

- **Crear** uno nuevo (código de 3 dígitos, nombre, dirección, tipo Matriz o
  Sucursal, estado).
- **Editar** cualquiera, incluido el matriz (código, tipo y estado).
- **Solo un establecimiento puede estar Activo por empresa a la vez.** Al
  marcar uno como Activo, el sistema pasa automáticamente a **Inactivo**
  cualquier otro establecimiento de esa empresa que lo estuviera. Ese cambio
  es exclusivo de este módulo: en **Empresa → pestaña Establecimiento**
  (autoservicio) el cliente solo ve y edita el establecimiento que está
  Activo, y ahí el código, el tipo y el estado son de solo lectura — edita
  nombre, dirección y logo.
- **Eliminar** uno, siempre que:
  - **no sea el matriz** (código `001` o tipo `Matriz`) — el matriz nunca se
    elimina, solo se puede marcar inactivo;
  - **quede al menos otro establecimiento** en la empresa (activo o
    inactivo) — una empresa no puede quedarse sin ningún establecimiento;
  - **ninguno de sus puntos de emisión tenga documentos ya emitidos** — si los
    tiene, el mensaje de error indica en cuál punto y qué módulo lo está
    usando.

  Al eliminar un establecimiento sin uso, se dan de baja en cascada sus
  puntos de emisión y los tipos de secuencial configurados en ellos, para no
  dejar registros huérfanos.

  Cuando no se puede eliminar (por ser el matriz, o por tener documentos),
  la alternativa es marcarlo **Inactivo** desde la misma edición — deja de
  ofrecerse para emitir documentos nuevos, sin perder el historial.

## Permisos

Exclusivo de nivel 3 (superadministrador) para crear empresas y eliminar
establecimientos/empresas. Algunas consultas (listar usuarios, documentos,
establecimientos de una empresa) están disponibles desde nivel 2 solo para
las empresas que ese usuario tiene asignadas.

## Errores frecuentes

- **"El establecimiento matriz no puede ser eliminado"**: es intencional — el
  código `001`/tipo `Matriz` está protegido. Si necesita desactivarlo, use el
  estado Inactivo en vez de eliminarlo.
- **"No se puede eliminar: la empresa debe tener siempre al menos un
  establecimiento disponible"**: la empresa se quedaría sin ningún
  establecimiento activo. Cree o active otro antes de eliminar este.
- **"Ya tiene documentos emitidos"**: el establecimiento (o alguno de sus
  puntos de emisión) ya numeró comprobantes reales; no se puede perder esa
  numeración eliminándolo. Márquelo Inactivo en su lugar.
- **Cambié el código del establecimiento y no se actualizó en el módulo
  Empresa (autoservicio), pestaña Información General**: corregido desde la
  versión 1.5 — antes, ese campo (`empresas.establecimiento`, usado también
  en XML, clave de acceso, PDF y el navbar) no se resincronizaba al editar
  `empresa_establecimiento.codigo`. Si el problema persiste, es un dato
  desactualizado de antes del fix; edite el establecimiento una vez más
  (aunque sea el mismo valor) para forzar la resincronización.

## Historial de cambios

- **1.6** — La pestaña **Establecimientos** de la ficha de empresa (en este
  módulo) pasa a llamarse **Establecimiento** (singular), igual que en el
  módulo Empresa (autoservicio). Corregido además el sentido inverso del fix
  1.5: editar el campo **Establecimiento** desde la pestaña **General** de la
  misma ficha (columna `empresas.establecimiento`) ahora también actualiza el
  código del establecimiento Activo en la pestaña Establecimiento — antes solo
  se sincronizaba al revés. Si el nuevo código choca con el de otro
  establecimiento de la misma empresa, el guardado se rechaza con el mismo
  mensaje de error que ya usa la pestaña Establecimiento. La UI del modal
  también se refresca en vivo (sin recargar la página) al guardar desde
  cualquiera de las dos pestañas.

- **1.5** — Corregido: al editar el **código** de un establecimiento (o al
  activarlo) desde este módulo, el cambio no se reflejaba en la pestaña
  Información General del módulo Empresa (autoservicio), porque esa pestaña
  lee el campo `establecimiento` de la propia tabla `empresas` — separado de
  `empresa_establecimiento.codigo` — y no se resincronizaba. Ahora, cada vez
  que se guarda un establecimiento, el sistema actualiza `empresas.establecimiento`
  con el código del que quede Activo.

- **1.4** — El módulo Empresa (autoservicio) renombró su pestaña
  "Establecimientos" a "Establecimiento" (singular), ya que solo opera sobre
  el que está Activo. Se actualizaron las referencias a esa pestaña en este
  artículo. La pestaña propia de este módulo (Empresas del Sistema) no
  cambia de nombre.

- **1.3** — Aclarado: el módulo Empresa (autoservicio) no ofrece un selector
  entre varios establecimientos — solo ve y edita el que está Activo, con
  código, tipo y estado de solo lectura ahí. Cualquier otro dato/gestión de
  establecimientos queda exclusiva de este módulo.

- **1.2** — Aclarado: activar/desactivar un establecimiento es exclusivo de
  este módulo. El módulo Empresa (autoservicio) solo lo muestra de forma
  informativa (campo Estado de solo lectura), no lo puede cambiar.

- **1.1** — Nueva regla: **solo un establecimiento puede estar Activo por
  empresa a la vez**. Activar uno desactiva automáticamente los demás — este
  cambio es exclusivo de este módulo. El que está activo es siempre el que
  se muestra en el módulo Empresa (autoservicio), pestaña Establecimiento,
  donde el cliente puede consultarlo pero no cambiarlo.

- **1.0** — Primera versión del artículo: documenta la gestión de
  establecimientos, incluida la opción de **eliminar** un establecimiento
  (bloqueada para el matriz, si es el único, o si ya tiene documentos
  emitidos), con baja en cascada de sus puntos de emisión y secuenciales.
