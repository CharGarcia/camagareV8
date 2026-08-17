---
titulo: Auditoría del sistema
etiquetas: [auditoría, bitácora, log, historial, quién hizo qué, rastreo, trazabilidad, cambios, log_sistema, intentos de login, buscar por contenido, buscar dentro del mensaje, concepto, proveedor, comprobante]
visibilidad: superadmin
---

# Auditoría del sistema

## Qué es

Es la **bitácora de todo lo que ocurre en el sistema**: cada vez que alguien crea,
modifica, elimina, anula o aprueba algo, queda un registro con la fecha y hora, el
usuario, la empresa, la acción, el módulo afectado, el número del registro, la IP y
el navegador. Además guarda **los datos del evento**: cómo estaba el registro antes
y cómo quedó después.

Se consulta desde una tarjeta en **Configuración** y es **solo lectura**: no se
puede editar ni borrar nada desde aquí.

No confundir con **Errores del sistema**, que registra fallos técnicos. Esta
pantalla registra **acciones de negocio**.

## Requisitos

- Ser **nivel 2 (administrador)** o **nivel 3 (superadministrador)**.
- Tener una empresa activa seleccionada (nivel 2).

## Cómo se usa

1. Entre a **Configuración → Auditoría del sistema**.
2. Por defecto verá los eventos del año en curso. Ajuste **Desde** y **Hasta** para
   acotar el rango: mientras más corto el rango, más rápida la consulta.
3. Use los filtros de la barra superior para acotar por **Usuario**, **Empresa**
   (solo nivel 3), **Acción** o **Módulo**.
4. Para encontrar un evento por lo que dice adentro, escriba en **Contenido del
   mensaje** (ver más abajo).
5. Haga clic en cualquier fila para abrir el **detalle del evento**: verá la tabla
   de cambios campo por campo (antes / después) y, desplegando *Ver datos crudos*,
   el contenido completo del evento.
6. Los botones de **PDF** y **Excel** exportan exactamente lo que está filtrado en
   pantalla.

### Buscar por contenido del mensaje

Los filtros de la barra (usuario, acción, módulo) responden a *quién* y *qué tipo de
acción*. Cuando lo que usted recuerda es el **contenido** — el nombre de un
proveedor, el número de un comprobante, un importe, el concepto — use el campo
**Contenido del mensaje**.

Ese campo busca **dentro de los datos guardados del evento**. Por ejemplo, si el
evento guardó algo así:

```
{
    "estado": "contabilizado",
    "concepto": "Compra # 001-003-048182378 - Proveedor: DELIVERY HERO DH E-COMMERCE ECUADOR S.A.S.",
    "total_debe": 0.88,
    "total_haber": 0.88,
    "fecha_asiento": "2026-07-04",
    "modulo_origen": "compra",
    "tipo_comprobante": "compras",
    "numero_comprobante": "CO-000039"
}
```

…lo encuentra escribiendo `DELIVERY HERO`, `CO-000039`, `048182378`,
`contabilizado` o incluso `0.88`.

Cómo se comporta:

- **Todas las palabras que escriba deben aparecer**, en cualquier orden. Escribir
  `hero delivery` encuentra el ejemplo de arriba igual que `delivery hero`.
- **No distingue mayúsculas ni tildes**: `garcia` encuentra `GARCÍA`.
- Se combina con el resto de filtros: puede pedir "eventos de María, del módulo
  Compras, en julio, que mencionen DELIVERY HERO".
- Necesita **al menos 3 caracteres** para lanzar la búsqueda mientras escribe. Con
  **Enter** se ejecuta de inmediato.
- También funciona como clave en el buscador general:
  `contenido:"DELIVERY HERO"` o su alias `datos:CO-000039`.

> Es la búsqueda **más pesada** de la pantalla, porque revisa el texto completo de
> cada evento. Acote siempre el rango de fechas antes de usarla.

## Campos

| Campo | Qué significa |
|-------|---------------|
| Buscar | Texto libre sobre acción, módulo, usuario, empresa e IP. Admite claves `clave:valor` |
| Contenido del mensaje | Busca dentro de los datos guardados del evento (concepto, proveedor, comprobante, importes, estado) |
| Usuario | Solo los eventos generados por esa persona |
| Empresa | Solo nivel 3. Acota a una empresa concreta |
| Acción | Crear, modificar, eliminar, anular, login, etc. |
| Módulo | En qué parte del sistema ocurrió el evento |
| Desde / Hasta | Rango de fechas. Si no se indica ninguno, se muestran los últimos 30 días |

### Claves del buscador general

| Clave | Ejemplo | Qué hace |
|-------|---------|----------|
| `usuario:` | `usuario:maria` | Filtra por nombre de usuario |
| `accion:` | `accion:eliminar` | Filtra por tipo de acción |
| `registro:` | `registro:34766` | Filtra por el número del registro afectado |
| `ip:` | `ip:190.1` | Filtra por dirección IP |
| `contenido:` | `contenido:"DELIVERY HERO"` | Busca dentro de los datos del evento |
| `datos:` | `datos:CO-000039` | Alias de `contenido:` |
| `fecha:` | `fecha:2026-07-01..2026-07-08` | Rango de fechas. También `fecha:>=2026-07-01` |

Anteponga un guion para negar (`-accion:login`) y use comillas para valores con
espacios.

## Permisos

- **Nivel 1 (usuario)**: sin acceso.
- **Nivel 2 (administrador)**: ve los eventos de **su empresa activa** más los
  eventos globales (inicios de sesión, catálogos generales). No puede ver eventos de
  otras empresas ni abrir por número un evento fuera de su alcance. El filtro de
  empresa no aparece.
- **Nivel 3 (superadministrador)**: ve **todo**, puede filtrar por empresa y accede
  además a la pestaña **Intentos de login**.

## Reglas de negocio

- La bitácora es **solo lectura**. Ningún usuario puede editar ni eliminar registros
  desde el sistema.
- Si no se indica ningún filtro de fecha, la pantalla muestra automáticamente los
  **últimos 30 días** para no cargar la bitácora completa.
- El alcance por empresa se aplica también al abrir el detalle: un administrador no
  puede leer un evento de otra empresa aunque conozca su número.
- Los nombres internos de las tablas no se muestran: se traducen a nombres de
  módulo entendibles.
- Las exportaciones tienen tope de seguridad: **10.000 filas** en Excel y **2.000**
  en PDF. Si se supera, el archivo incluye un aviso y hay que acotar el filtro.

## Integraciones

- Todos los módulos del sistema escriben aquí a través del servicio de auditoría.
- La pestaña **Intentos de login** (nivel 3) muestra los intentos de inicio de
  sesión, exitosos y fallidos, con identificador, IP y navegador.

## Errores frecuentes

- **"No se encontraron registros de auditoría en el rango seleccionado"**: casi
  siempre es el rango de fechas. Amplíe **Desde**/**Hasta** o pulse *Limpiar*.
- **La búsqueda por contenido tarda**: es normal, revisa el texto completo de cada
  evento. Acote el rango de fechas y, si puede, el módulo.
- **Escribo en Contenido del mensaje y no pasa nada**: necesita al menos 3
  caracteres. Pulse **Enter** para forzar la búsqueda.
- **No encuentro un evento que sé que existe**: verifique que no esté buscando
  fuera de su alcance de empresa (nivel 2) y que la palabra esté realmente en los
  datos guardados —abra un evento parecido y mire *Ver datos crudos*.
- **"Registro no encontrado o fuera de su alcance"**: el evento pertenece a otra
  empresa.

## Historial de cambios

- **1.1** — Se agrega la búsqueda por **contenido del mensaje** (campo en la barra
  de filtros y claves `contenido:` / `datos:` en el buscador), que revisa los datos
  guardados de cada evento. Se aplica también a las exportaciones a PDF y Excel.
- **1.0** — Versión inicial.
