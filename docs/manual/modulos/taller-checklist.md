---
titulo: Checklist de recepción
resumen: Define qué revisa el taller al recibir cada vehículo; se copia a la orden y queda como evidencia del estado en que llegó.
categoria: Operaciones
ruta_modulo: modulos/taller-checklist
tipo: modulo
visibilidad: todos
etiquetas: checklist, recepcion, inventario del vehiculo, accesorios, carroceria, documentos, niveles, taller, mecanica, llanta de emergencia, gata, evidencia, estado del vehiculo
version: 1.0
orden: 4
estado: activo
---

Es la lista de lo que el taller revisa cuando entra un vehículo: accesorios,
estado de la carrocería, documentos y niveles. Sirve para dejar constancia de
cómo llegó el carro y evitar discusiones al entregarlo.

## Qué es y para qué sirve

Cuando el asesor recibe un vehículo, esta lista aparece en la orden y él va
marcando **Sí / No / N/A** en cada punto, con una observación si hace falta.

Lo importante: la lista se **copia** a la orden en ese momento. Si mañana el
taller agrega o quita puntos de revisión, las órdenes ya registradas conservan
exactamente lo que se revisó ese día. Es evidencia, no una referencia viva.

El checklist se imprime en la **orden de trabajo** que firma el cliente.

## Requisitos previos

Ninguno. Conviene configurarlo antes de empezar a recibir vehículos, junto con
[Departamentos del taller](modulos/taller-departamentos).

## Cómo se usa

1. **Nuevo punto de revisión** → elegir el grupo y escribir qué se revisa.
2. El **orden** define la secuencia en que aparece al recibir el vehículo. Deje
   huecos (10, 20, 30…) para poder intercalar después sin renumerar todo. Si se
   deja en cero, el punto se agrega al final.
3. Un punto **inactivo** deja de aparecer en las órdenes nuevas, pero sigue
   visible en las que ya lo usaron.
4. Se edita tocando cualquier fila de la lista.

También se puede agregar un punto **sin salir de la orden**: el botón con el
ícono de lista en la barra de la orden de trabajo lo crea aquí y lo suma al
checklist de esa orden. Requiere permiso de creación sobre este módulo.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Grupo | Sí | Accesorios, Carrocería, Documentos o Niveles |
| Qué se revisa | Sí | El texto que verá el asesor. Ej. «Llanta de emergencia» |
| Orden | No | Posición en la secuencia de revisión |
| Activo | No | Un punto inactivo no aparece en las órdenes nuevas |

### Qué suele ponerse en cada grupo

- **Accesorios**: llanta de emergencia, gata, llave de ruedas, triángulos,
  extintor, botiquín, radio, alfombras, herramientas.
- **Carrocería**: rayones y abolladuras, parabrisas, espejos, luces.
- **Documentos**: matrícula, SOAT o seguro.
- **Niveles**: aceite, refrigerante, líquido de frenos.

## Permisos

- **Ver**: consultar la lista.
- **Crear** / **Modificar**: administrar los puntos de revisión.
- **Eliminar**: quitar un punto del catálogo.

## Reglas de negocio

- No puede repetirse el mismo punto dentro de un grupo. Sí puede existir el
  mismo texto en grupos distintos.
- Al eliminar, el punto se marca como eliminado: las órdenes que lo revisaron
  siguen mostrándolo.
- Los cambios **solo afectan a las órdenes nuevas**. Ninguna orden ya registrada
  cambia porque se modifique la plantilla.

## Integraciones con otros módulos

- **Órdenes de Trabajo**: consume esta lista al recibir el vehículo y la guarda
  con lo marcado. Se imprime en el PDF de la orden.

## Errores frecuentes

- **«Ese punto de revisión ya existe en el grupo …»**: hay uno igual. Búsquelo:
  puede estar inactivo, y conviene reactivarlo en lugar de crear otro.
- **El checklist no aparece al recibir un vehículo**: no hay puntos activos.
  Créelos aquí, o use el botón *Cargar plantilla* de la orden.

## Historial de cambios

- **1.0** — Versión inicial como módulo propio. Antes se administraba dentro de
  Departamentos del taller; se separó porque son cosas distintas y cada una
  necesita sus propios permisos.
