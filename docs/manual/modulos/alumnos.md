---
titulo: Alumnos
resumen: Registro de alumnos de centros infantiles, escuelas y colegios, con matrícula, horario, salud, servicios a facturar y documentos.
categoria: Ventas
ruta_modulo: modulos/alumnos
tipo: modulo
visibilidad: todos
etiquetas: alumnos, estudiantes, matrícula, matricula, colegio, escuela, centro infantil, campus, sede, nivel, curso, representante, horario, pensión, pension
version: 1.1
orden: 0
estado: activo
---

El módulo Alumnos registra a los estudiantes de instituciones educativas
(centro infantil, escuela o colegio) y sirve de base para facturarles
servicios recurrentes (pensión, matrícula, materiales, transporte, etc.) a
través del módulo **Clientes** ya existente. Se relaciona con Clientes (el
representante que factura), Productos (servicios predeterminados) y con la
configuración de **Puntos de Emisión** (serie preferida de facturación).

## Qué es y para qué sirve

Permite mantener una ficha completa por alumno: datos personales, el
representante/padre que se factura, historial de matrícula (campus y
nivel/curso por período lectivo), horario individual, información de salud y
contacto de emergencia, servicios/productos que se le facturan de forma
recurrente, y documentos adjuntos (partida de nacimiento, cédula, contratos,
etc.).

Un mismo alumno puede entrar y salir de la institución varias veces a lo
largo del tiempo (un año estudia, al siguiente no, y luego regresa): cada
ingreso queda registrado como un **período de matrícula** independiente, sin
perder el historial de los anteriores.

## Requisitos previos

- Al menos un **Cliente** registrado para usar como representante del
  alumno (el alumno se factura a nombre de ese cliente).
- Los catálogos de **Campus** (`modulos/alumnos-campus`) y **Niveles/Cursos**
  (`modulos/alumnos-niveles`) se pueden crear sobre la marcha desde el mismo
  modal del alumno, con los botones de la barra de acciones superior
  (íconos de sede y birrete), no es necesario precargarlos.
- Si se quiere fijar una serie de facturación preferida, debe existir un
  **Punto de Emisión** configurado en `/config` para la empresa.

## Cómo se usa

1. Ir a **Alumnos** y presionar **Nuevo**.
2. En la pestaña **General**, completar nombres, apellidos, identificación,
   fecha de nacimiento, sexo y estado académico.
3. En **Representante**, buscar y seleccionar el Cliente que se factura, su
   relación con el alumno y, opcionalmente, la serie de facturación preferida.
4. En **Matrícula**, presionar **Matricular / agregar período** y elegir
   campus, nivel/curso, año lectivo y fecha de ingreso. Si el campus o el
   nivel no existen todavía, se crean al vuelo desde los botones de la barra
   de acciones superior del modal, sin salir del formulario del alumno. Esos
   botones se muestran solo con su ícono: al pasar el puntero sobre cada uno
   aparece qué hace (registrar nuevo cliente, campus o nivel/curso).
5. Completar, si aplica, **Horario**, **Salud** y **Servicios y Productos**
   (los productos que se le facturarán de forma recurrente).
6. Guardar. Una vez guardado el alumno, se habilita la pestaña
   **Documentos** para adjuntar archivos (PDF o imagen).
7. Para dar de baja al alumno sin eliminarlo, editar el período de matrícula
   abierto y registrar su **fecha de salida** y motivo; para que vuelva a
   estudiar más adelante, agregar un nuevo período.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombres / Apellidos | Sí | Nombre completo del alumno. |
| Tipo / Número de identificación | No | Documento propio del alumno (no el del representante). El tipo solo ofrece **Cédula** o **Pasaporte**, porque el alumno es siempre una persona natural; RUC, consumidor final e identificación del exterior quedan para el representante, que es un Cliente. La cédula se valida a 10 dígitos y el pasaporte admite hasta 20 caracteres. |
| Fecha de nacimiento / Sexo / Nacionalidad | No | Datos personales del alumno. |
| Estado académico | Sí | Activo, retirado, egresado o suspendido. No reemplaza a la eliminación lógica del registro. |
| Cliente / Representante | Sí | Cliente ya existente a cuyo nombre se factura al alumno. |
| Relación con el alumno | No | Padre, madre, tutor, abuelo/a u otro. |
| Serie / Punto de emisión preferido | No | Si se deja vacío, se usa el predeterminado de la empresa al facturar. |
| Campus / Nivel-Curso (por período) | Sí, al matricular | De dónde y qué estudia el alumno en ese período lectivo. |
| Fecha de ingreso / salida (por período) | Ingreso obligatorio | Salida vacía = matrícula vigente. Solo puede haber un período vigente por alumno. |
| Horario (día, hora inicio/fin, jornada) | No | Horario propio del alumno, no se hereda de un curso compartido. |
| Tipo de sangre / Alergias / Contacto de emergencia | No | Información útil en caso de emergencia. |
| Servicios y Productos | No | Productos/servicios (del catálogo de Productos) que se facturan de forma recurrente al alumno, con cantidad, frecuencia y precio opcional distinto del precio base. |
| Documentos | No | Archivos adjuntos (PDF/imagen) asociados al alumno. |

## Permisos

Sigue el esquema estándar de permisos por submódulo (`r/w/u/d/t`). Con
**acceso total** (`t`) se ven y gestionan los alumnos de toda la empresa; sin
él, cada usuario solo ve y gestiona los alumnos que él mismo registró
(`created_by`). Los catálogos de Campus y Niveles/Cursos tienen sus propios
permisos independientes (`modulos/alumnos-campus`, `modulos/alumnos-niveles`),
aunque normalmente se gestionan desde el propio modal de Alumno.

## Reglas de negocio

- El alumno **siempre** requiere un Cliente representante para poder
  facturarlo; no se duplica su identificación de cobro.
- Un alumno no puede tener más de un período de matrícula **vigente**
  (sin fecha de salida) al mismo tiempo; para volver a matricularlo hay que
  cerrar primero el período abierto.
- Los períodos de matrícula de un mismo alumno no pueden solaparse en fechas.
- El campus y el nivel/curso **actuales** que se muestran en el listado se
  calculan a partir del período de matrícula vigente (o el más reciente si no
  hay ninguno vigente); no se guardan como valor fijo en la ficha del alumno,
  para evitar que queden desactualizados si el historial de matrícula cambia.
- Los documentos adjuntos solo se pueden subir después de guardar el alumno
  por primera vez.
- No se puede eliminar un Campus o Nivel/Curso que tenga alumnos matriculados.

## Integraciones con otros módulos

- **Clientes**: el alumno referencia al cliente que se factura (representante).
- **Productos**: los servicios predeterminados del alumno referencian
  productos/servicios del catálogo general.
- **Configuración de Puntos de Emisión**: fuente de las series de facturación
  disponibles para fijar como preferida.
- Este módulo registra los datos base del alumno; la generación de la
  factura periódica a partir de sus servicios predeterminados no está
  incluida en esta primera versión.

## Errores frecuentes

- **"El representante (cliente que factura) es obligatorio"**: no se
  seleccionó ningún cliente en la pestaña Representante, o se escribió texto
  en el buscador sin hacer clic en un resultado de la lista.
- **"El alumno no puede tener más de una matrícula vigente"**: ya existe un
  período de matrícula sin fecha de salida; hay que cerrarlo (ponerle fecha
  de salida) antes de agregar uno nuevo.
- **La pestaña Documentos aparece deshabilitada**: el alumno todavía no se ha
  guardado por primera vez; guardar los datos generales primero.

## Historial de cambios

- **1.1** — Los botones de la barra de acciones superior del modal (nuevo
  cliente, nuevo campus, nuevo nivel/curso) pasan a mostrarse solo con su
  ícono, sin texto, igual que el modal de Facturas de Venta. La descripción
  de cada botón queda en su tooltip. Se corrigió además el botón de **nuevo
  cliente**, que no abría la ficha de cliente: ahora se comporta igual que en
  Facturas de Venta y, al guardar, el cliente creado queda seleccionado como
  representante del alumno. El selector de **tipo de identificación** del
  alumno pasa a ofrecer solo **Cédula** y **Pasaporte**. Se elimina el campo
  **Código de alumno**: ya no aparece en el formulario, ni como columna del
  listado, ni en las exportaciones a PDF y Excel, y el buscador dejó de
  considerarlo. Nunca se asignaba de forma automática, así que en la práctica
  quedaba vacío; los alumnos se identifican por sus apellidos y nombres, su
  número de identificación o su representante.
- **1.0** — Versión inicial: datos generales, representante/facturación,
  matrícula por períodos, horario individual, salud y emergencia, servicios
  y productos predeterminados, y documentos adjuntos. Catálogos de Campus y
  Niveles/Cursos con alta rápida desde el modal.
