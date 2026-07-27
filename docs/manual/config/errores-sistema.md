---
titulo: Errores del sistema
etiquetas: [errores, log, bitácora, diagnóstico, fallos, excepciones, superadmin, técnico, depuración, sqlstate]
visibilidad: superadmin
---

# Errores del sistema

## Qué es

Es una **bitácora técnica de errores** del sistema, pensada para diagnóstico. Cada
vez que ocurre un fallo —una excepción capturada o un error fatal no capturado— se
guarda un registro con el mensaje, el tipo de error, el archivo y la línea, la ruta
en la que ocurrió, el usuario y la empresa (si había sesión), la URL y la traza
completa. Se consulta desde una tarjeta en **Configuración**, en modo solo lectura.

A diferencia de la **Auditoría del sistema** (que registra acciones de negocio:
crear, editar, eliminar), esta pantalla registra **errores técnicos**: sirve para
que el administrador vea qué falló en producción sin depender de que un usuario lo
reporte.

## Requisitos

- Ser usuario **nivel 3 (superadministrador)**. La tarjeta y la consulta son
  exclusivas de ese nivel.

## Cómo se usa

1. Entrar a **Configuración → Errores del sistema**.
2. El listado muestra los errores más recientes primero: fecha, tipo, SQLSTATE (si
   es error de base de datos), ruta, un extracto del mensaje y el usuario.
3. **Buscar**: escribir texto libre (busca en mensaje, clase, ruta y SQLSTATE) o usar
   claves:
   - `tipo:fatal` o `tipo:excepcion`
   - `sqlstate:22P02`
   - `ruta:factura`
   - `usuario:5`
   - `fecha:2026-07-01..2026-07-27` o `fecha:>=2026-07-01`
   - Negar con `-clave:valor`.
4. **Ordenar**: clic en las cabeceras Fecha, Tipo, SQLSTATE o Ruta.
5. **Ver detalle**: clic en una fila abre un modal con toda la información del error,
   incluida la **traza completa (stack trace)**.

## Campos

| Campo | Descripción |
|-------|-------------|
| Fecha | Cuándo ocurrió el error (`d-m-Y H:i:s`). |
| Tipo | `excepcion` (capturada en un try/catch), `fatal` (error/​excepción no capturada) o `manual`. |
| Clase / error | Clase de la excepción (p. ej. `PDOException`) o tipo de error fatal. |
| SQLSTATE | Código del error de base de datos cuando aplica (p. ej. `22P02`). |
| Mensaje | Texto del error. |
| Ruta / Acción | Módulo y operación en curso cuando falló (si el catch lo informó). |
| Archivo / Línea | Ubicación en el código. |
| URL / Método | Petición que se estaba atendiendo. |
| Usuario / Empresa | De la sesión, si había. Muchos errores no tienen (ocurren sin sesión). |
| IP / Navegador | Origen de la petición. |
| Traza | Stack trace completo (en el detalle). |

## Permisos

Solo **nivel 3**. No usa la tabla de permisos por submódulo; el acceso se controla
por el nivel del usuario, tanto en la visibilidad de la tarjeta (`nivel_minimo = 3`)
como en el propio controlador.

## Reglas de negocio

- Es **solo lectura**: no se crean, editan ni eliminan registros desde la pantalla.
- La tabla es **append-only** (solo se agrega); no tiene eliminación lógica.
- El **nivel 3 ve todos los errores** del sistema, sin filtrar por empresa, porque
  muchos errores ocurren sin empresa/sesión y el diagnóstico es global.
- Registrar un error **nunca interrumpe** la petición: si el propio registro fallara
  (p. ej. la base de datos caída), el error se manda al log de PHP y el sistema sigue.

## Integraciones

- **Manejador global**: al inicio de cada petición se registra un manejador que, al
  terminar, captura los errores **fatales** y las **excepciones no capturadas** y las
  guarda automáticamente (registro post-mortem; no cambia cómo se muestran los errores).
- **Captura en `catch`**: los bloques que atrapan errores pueden registrarlos
  explícitamente con `ErrorLogService::registrar($e, ['ruta' => ..., 'accion' => ...])`.
  El primer punto conectado fue el guardado de **facturas de venta**.

## Errores frecuentes

- *"No tiene permisos"*: la pantalla es solo para nivel 3.
- *No aparecen errores antiguos*: solo se registran los que ocurren **después** de
  desplegar este módulo; los anteriores no quedaron guardados.

## Historial de cambios

- **v1.0** (2026-07-27): Versión inicial. Tabla `errores_sistema`, servicio central de
  registro, manejador global de errores, captura en el guardado de facturas y tarjeta
  de consulta de solo lectura para nivel 3.
