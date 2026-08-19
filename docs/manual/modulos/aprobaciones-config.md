---
titulo: Aprobaciones
resumen: Define qué procesos del sistema requieren autorización antes de ejecutarse y quién puede autorizarlos.
categoria: Configuración
ruta_modulo: modulos/aprobaciones-config
tipo: modulo
visibilidad: todos
etiquetas: aprobaciones, aprobar, autorizar, autorizacion, permisos de aprobacion, quien aprueba, aprobadores, visto bueno, revision, control interno, monto minimo, doble firma
version: 1.0
orden: 0
estado: activo
---

Hay procesos que no conviene que se ejecuten solos: una carga de inventario que
mueve el stock, un lote de pagos que se sube al banco. Este módulo es donde se
decide **cuáles de esos procesos exigen la autorización de otra persona** y
**quién puede darla**, empresa por empresa.

## Qué es y para qué sirve

El sistema trae una lista de procesos que se pueden someter a aprobación. Usted
elige cuáles activa en su empresa, quiénes son los aprobadores de cada uno y,
si quiere, desde qué monto se pide la autorización.

Cuando un proceso tiene aprobación activada, el documento **no se ejecuta al
guardarlo**: queda pendiente y los aprobadores reciben un correo con un enlace
para aprobarlo o rechazarlo. Recién cuando alguien aprueba, el proceso continúa
(el stock se mueve, el archivo del banco se genera, etc.).

Procesos disponibles hoy:

| Módulo | Proceso | Qué queda detenido |
|--------|---------|--------------------|
| Inventario | Cargas de inventario | La carga no afecta el stock hasta ser aprobada |
| Inventario | Nacionalización de importaciones | El costo no se postea al kardex hasta ser aprobado |
| Tesorería | Lotes de pago bancario | No se puede generar el archivo del banco hasta aprobar el lote |
| Compras | Registro de compras | La compra no se puede pagar, ni procesar su inventario, ni se asienta hasta ser aprobada |

La lista crece a medida que se enganchan más módulos: usted no la escribe, la
elige.

## Requisitos previos

- Tener una **empresa activa** seleccionada. La configuración es por empresa: lo
  que active en una no afecta a las demás.
- Tener **usuarios asignados a la empresa**. Solo ellos pueden ser aprobadores;
  si la empresa no tiene usuarios asignados, no podrá crear la aprobación.

## Cómo se usa

1. Entre al módulo **Aprobaciones**. Al inicio el listado está vacío: sin
   configuración, ningún proceso pide autorización.
2. Pulse **Nueva aprobación**.
3. Elija el **Proceso** en la lista. Debajo aparece una explicación de qué queda
   detenido si lo activa.
4. Escriba en **Aprobadores** el nombre de cada persona que podrá aprobar y
   selecciónela. Puede agregar varias: cualquiera de ellas basta para aprobar.
5. Si quiere que solo se pida autorización a partir de cierto valor, escriba el
   **Monto mínimo**. Déjelo vacío para que siempre se pida.
6. Guarde. La aprobación aparece en el listado y **entra en vigor de inmediato**.

Para cambiar los aprobadores o el monto de una aprobación existente, **haga clic
en su fila**. Para quitarla del todo, use **Eliminar** dentro de ese mismo modal.

### Buscar en el listado

El buscador acepta texto libre —que busca por proceso, módulo y **también por
nombre de aprobador**, para responder "¿qué aprueba fulano?"— y filtros
`clave:valor`:

| Filtro | Ejemplo | Qué hace |
|--------|---------|----------|
| `proceso:` | `proceso:cargas` | Por nombre del proceso |
| `modulo:` | `modulo:transferencias` | Por módulo dueño |
| `aprobador:` | `aprobador:"Ana Pérez"` | Aprobaciones donde esa persona autoriza |
| `estado:` | `estado:activa` | Activas o inactivas |
| `monto:` | `monto:>1000` | Por monto mínimo configurado |

El listado se ordena pulsando el encabezado de cada columna, y se exporta a
**PDF** y **Excel** respetando la búsqueda y el orden aplicados.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Proceso | Sí | El proceso que quedará detenido esperando autorización. Solo se ofrecen los que aún no ha configurado; al editar no se puede cambiar (cambiar el proceso sería otra aprobación distinta) |
| Aprobadores | Sí | Personas que pueden autorizar. Cualquiera de ellas basta. Reciben el correo con el enlace de aprobación |
| Monto mínimo | No | Desde qué valor se pide autorización. Vacío = siempre se pide. Por debajo de ese monto el proceso se ejecuta directo |
| Estado | No | **Activa**: el proceso pide autorización. **Inactiva**: no la pide, pero se conserva la lista de aprobadores para reactivarla después |

## Permisos

| Permiso | Qué habilita |
|---------|--------------|
| Ver | Consultar el listado de aprobaciones configuradas |
| Crear | Botón **Nueva aprobación** |
| Modificar | Editar aprobadores, monto mínimo y estado de una aprobación existente |
| Eliminar | Quitar una aprobación del listado |

El permiso de **acceso total** no cambia nada aquí: la configuración es de la
empresa, no de cada usuario, así que todos los que ven el módulo ven las mismas
aprobaciones.

Aparte de esto, los **superadministradores (nivel 3) siempre pueden aprobar**
cualquier proceso, estén o no en la lista de aprobadores. Es la salida de
emergencia para cuando el aprobador configurado no está disponible.

## Reglas de negocio

- **No se puede guardar una aprobación sin aprobadores.** Activar el control sin
  decir quién autoriza dejaría los documentos trabados sin salida.
- Solo se aceptan como aprobadores **usuarios asignados a esa empresa**. Si un
  usuario deja de estar asignado, deja de contar como aprobador.
- **Siempre se notifica por correo** a los aprobadores cuando algo queda
  pendiente. No hay forma de activar la aprobación sin avisar: sería dejar el
  documento detenido sin que nadie se entere.
- El **monto mínimo** compara contra el valor del documento (costo total de la
  carga, costo nacionalizado de la importación, monto total del lote de pagos).
  Por debajo de ese monto el proceso se ejecuta directamente, sin aprobación.
- **Eliminar una aprobación no borra nada de lo ya aprobado**: solo hace que ese
  proceso deje de pedir autorización de aquí en adelante. Los documentos que
  quedaron pendientes siguen pendientes.
- Poner una aprobación en **Inactiva** equivale a eliminarla en cuanto al efecto,
  pero conserva la configuración para volver a encenderla sin rehacerla.
- Cada cambio queda registrado en el historial del sistema (quién, cuándo y qué
  cambió).

## Integraciones con otros módulos

- **Cargas de Inventario**: si el proceso está activo, la carga nace *pendiente*
  y no toca el stock. Al aprobarse se aplica cada línea al kardex.
- **Importaciones**: si está activo, la nacionalización queda *pendiente de
  aprobación* y el costo no se postea hasta autorizarla.
- **Transferencias (Pagos al Banco)**: si está activo, el lote queda *pendiente*
  y el archivo bancario no se genera hasta la aprobación.
- **Compras**: si está activo, **toda compra nueva** queda *pendiente* —tanto la
  capturada a mano como la descargada del SRI—; no se puede pagar, ni procesar su
  inventario, ni se genera su asiento contable hasta autorizarla. Cuando entra un
  lote de comprobantes del SRI sale **un solo correo** con todas las pendientes.
  Quedan fuera los documentos históricos (migraciones e importaciones de datos
  antiguos), que entran ya registrados.
- En todos los casos, el aprobador puede resolver desde el **enlace del correo**,
  sin necesidad de iniciar sesión.
- **Quien registra un documento no puede aprobarlo** (salvo un
  superadministrador): la autorización debe venir de otra persona.

## Errores frecuentes

- **"Agrega al menos un usuario aprobador de esta empresa"**: no seleccionó
  aprobadores, o los que eligió ya no están asignados a la empresa activa. Revise
  la asignación de usuarios de la empresa.
- **No aparece el proceso que quiero en la lista**: o ya lo configuró (búsquelo
  en el listado y edítelo), o ese proceso todavía no está disponible para
  aprobación en el sistema.
- **"No hay procesos disponibles"**: ya configuró todos los procesos aprobables.
- **Activé la aprobación y el documento se ejecutó igual**: revise el **monto
  mínimo**. Si el documento está por debajo de ese valor, se ejecuta sin pedir
  autorización.
- **El aprobador no recibe el correo**: verifique que el usuario tenga correo
  registrado y que la configuración de correo de la empresa esté operativa.

## Historial de cambios

- **1.0** — Versión inicial. Se centraliza aquí la configuración de aprobaciones
  que antes vivía repartida en *Empresa → Inventario* (cargas de inventario) y
  *Empresa → Pagos al Banco* (lotes de transferencia). Se separan Cargas de
  Inventario e Importaciones en dos procesos independientes (antes compartían un
  solo interruptor), se agrega el **monto mínimo** y se retira el interruptor de
  "notificar por correo": ahora siempre se notifica.
