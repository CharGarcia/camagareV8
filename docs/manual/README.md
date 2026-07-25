# Manual del Sistema — contenido

Esta carpeta es la **fuente** de los artículos del manual que los usuarios leen en
`/documentacion`. Cada archivo `.md` es un artículo.

## Cómo se publica

1. Se escribe o edita el `.md` **en el mismo cambio que el código** del módulo.
2. Se sube al repositorio y se hace `git pull` en el servidor.
3. Un superadministrador entra en `/documentacion/gestion` y pulsa **Sincronizar**.

El sincronizador cruza por `slug` y decide solo:

| Situación | Qué hace |
|-----------|----------|
| El artículo no existe | Lo **crea** |
| Existe y el archivo no cambió | No lo toca |
| Existe y el archivo cambió | Lo **actualiza** |
| Existe pero se escribió desde la pantalla | Lo **omite** (lo editado a mano manda) |
| El `.md` ya no está | Lo marca **obsoleto** (no lo borra) |

## Dónde va cada archivo

La ruta del archivo **es** la dirección del artículo:

```
docs/manual/modulos/clientes.md        →  /documentacion?slug=modulos/clientes
docs/manual/config/permisos-modulos.md →  /documentacion?slug=config/permisos-modulos
docs/manual/guias/cerrar-el-mes.md     →  /documentacion?slug=guias/cerrar-el-mes
```

- `modulos/` — un archivo por módulo, con el mismo nombre que su ruta MVC.
- `config/` — configuración. **Solo lo ve el superadministrador**, siempre: la
  visibilidad se fuerza por la ruta, no depende de lo que diga el front-matter.
- `guias/` — procesos que cruzan varios módulos ("cerrar el mes", "facturar rápido").
- `conceptos/` — explicaciones transversales (multiempresa, ambiente de pruebas…).

## Cómo escribir un artículo

Copie `_PLANTILLA.md`, complete el front-matter y respete las secciones. Los
archivos que empiezan por `_` y este README **no se publican**.

Cada `##` se indexa por separado: el buscador puede llevar al usuario
directamente a esa sección, así que conviene que los títulos digan de qué tratan
("Anular una factura" es mejor que "Consideraciones").

Escriba las **etiquetas** pensando en las palabras que usaría alguien que no
conoce el sistema: pesan igual que el título en la búsqueda. Si un usuario busca
"devolver mercadería" y usted solo escribió "nota de crédito", no lo encontrará.

Formato admitido: encabezados `##`/`###`/`####`, listas, tablas, `> citas`,
bloques de código, **negrita**, *cursiva*, `código` y enlaces. El HTML crudo se
muestra como texto: si necesita algo más, escriba ese artículo desde la pantalla
de gestión (pasará a origen "manual" y el sincronizador dejará de tocarlo).
