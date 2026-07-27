---
titulo: Estación del taller
resumen: Pantalla para la tablet fija de cada departamento, donde el operario toma el trabajo, registra lo que hizo y pasa el vehículo al siguiente departamento.
categoria: Operaciones
ruta_modulo: modulos/taller-estacion
tipo: modulo
visibilidad: todos
etiquetas: estacion, tablet, taller, mecanica, operario, tecnico, pantalla, departamento, pintura, enderezada, tomar trabajo, terminar trabajo, repuestos usados
version: 1.0
orden: 3
estado: activo
---

Es la pantalla que se deja abierta en la tablet de cada departamento del taller.
Fondo oscuro, letra grande y botones amplios: está pensada para tocarse con el
dedo, incluso con las manos sucias.

## Qué es y para qué sirve

Cada departamento tiene la suya. Muestra únicamente los vehículos que están en
ese departamento ahora mismo, y desde ahí el operario:

- **Toma** el trabajo (arranca el registro de tiempo de la etapa).
- **Agrega** los repuestos y servicios que consumió, buscándolos en el catálogo.
- Deja **notas** y **fotos**, normalmente tomadas con la cámara de la tablet.
- **Termina y envía** el vehículo al siguiente departamento.

Todo lo que registra queda firmado con su departamento, su usuario y la hora, y
es lo que después aparece en el informe técnico que recibe el cliente.

## Requisitos previos

- **Departamentos del taller** creados en
  [Departamentos del taller](modulos/taller-departamentos).
- Órdenes enviadas a ese departamento desde
  [Órdenes de Trabajo](modulos/taller).
- Opcional: **empleados**, para elegir el técnico responsable de cada trabajo.

## Cómo se usa

1. En la tablet del departamento, abra la dirección con su departamento:
   `modulos/taller-estacion?id_departamento=N`, y **guárdela en marcadores**.
   La barra superior tiene también las pestañas de todos los departamentos.
2. El vehículo aparece como tarjeta cuando lo envían a ese departamento.
3. **Tomar** → empieza el trabajo.
4. **Registrar trabajo** → describir qué se hizo, agregar repuestos y servicios,
   dejar notas o fotos.
5. **Terminar y enviar** → elegir el siguiente departamento. Si no se elige
   ninguno, el vehículo queda listo para la entrega.

La pantalla se refresca sola cada 20 segundos. Mientras haya una ventana de
trabajo abierta el refresco se pausa, para no interrumpir a quien está
escribiendo.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Técnico responsable | No | Quién hizo el trabajo; alimenta el indicador de productividad |
| Trabajo realizado | Sí para cerrar | Lo que se hizo. Es el texto que lee el cliente en el informe técnico |
| Observaciones | No | Algo que deba saber el siguiente departamento |
| Enviar a | No | Departamento siguiente. Vacío = listo para entrega |
| Repuesto o trabajo | Sí | Del catálogo o escrito libre |
| Lo trajo el cliente | No | Se registra pero no se factura |

## Permisos

- **Ver**: abrir la pantalla y consultar los vehículos del departamento.
- **Crear**: agregar repuestos y servicios a la orden.
- **Modificar**: tomar el trabajo, guardar avances y cerrar la etapa.
- **Eliminar**: quitar una línea que se cargó por error.

Es un módulo aparte a propósito: aquí **no se puede facturar, aprobar
presupuestos ni eliminar órdenes**, aunque el usuario tenga permisos amplios en
esta ruta. Eso permite darle acceso al personal del taller sin exponerles la
parte comercial.

## Reglas de negocio

- **Sin el presupuesto aprobado no se puede tomar el trabajo.** El botón aparece
  bloqueado y la tarjeta se marca en ámbar.
- **Excepción**: el departamento marcado como *de diagnóstico* sí puede trabajar
  antes de la aprobación, porque su función es justamente producir el
  presupuesto que el cliente va a aprobar.
- **No se cierra una etapa sin describir el trabajo realizado.**
- Los repuestos que se agregan descuentan stock cuando la línea queda aprobada.
- Cada tablet queda fija en su departamento porque este viaja en la dirección,
  no en la sesión. Así puede haber una en pintura y otra en mecánica al mismo
  tiempo, cada una con lo suyo.

## Integraciones con otros módulos

- **Órdenes de Trabajo**: es la misma orden; lo que se registra aquí aparece
  allá en las pestañas Departamentos y Bitácora, y en el informe técnico.
- **Inventario**: los repuestos consumidos salen de la bodega indicada.
- **Empleados**: de ahí sale la lista de técnicos.
- **Tablero del taller**: refleja el movimiento del vehículo al cambiar de
  departamento.

## Errores frecuentes

- **«No hay departamentos configurados»**: cree al menos uno y déjelo activo.
- **La tablet no muestra vehículos**: revise que la dirección tenga el
  `?id_departamento=` correcto y que haya órdenes enviadas a ese departamento.
- **El botón Tomar está bloqueado**: falta la aprobación del cliente. El asesor
  debe registrarla en la orden.
- **«Describa el trabajo realizado antes de cerrar»**: es obligatorio porque ese
  texto sale en el informe técnico.

## Historial de cambios

- **1.0** — Versión inicial como módulo propio, separado de Órdenes de Trabajo
  para que el personal del taller tenga acceso solo a lo suyo.
