<?php
/**
 * Servicio de sincronización del Manual del Sistema.
 *
 * Publica en la base de datos los artículos que viven como archivos Markdown en
 * docs/manual/. Es la pieza que permite que la documentación se escriba JUNTO
 * con el código, viaje en el mismo commit y se publique con un clic tras el
 * "git pull" del servidor.
 *
 * ─── REGLAS DE LA SINCRONIZACIÓN ────────────────────────────────────────────
 *   - La clave del cruce es el slug (front-matter 'slug' o la ruta del archivo
 *     sin la extensión: docs/manual/modulos/clientes.md → 'modulos/clientes').
 *   - Artículo inexistente            → se CREA con origen='archivo'.
 *   - Existe con origen='manual'      → se OMITE. Lo escrito desde la pantalla
 *     de gestión manda sobre el repositorio y nunca se pisa.
 *   - Existe, origen='archivo', mismo hash  → sin cambios (no se toca).
 *   - Existe, origen='archivo', otro hash   → se ACTUALIZA.
 *   - Artículo de origen='archivo' cuyo .md ya no está → se marca 'obsoleto'
 *     (no se elimina: su contenido puede seguir sirviendo de referencia).
 *
 * Cada artículo se procesa en su propia transacción (dentro de
 * DocumentacionService): si uno falla, el resto se sincroniza igual y el error
 * se devuelve en el resumen.
 */

declare(strict_types=1);

namespace App\Services;

use App\lib\MarkdownSimple;
use App\models\Documentacion;

class DocumentacionSyncService
{
    /** Carpeta de los artículos, relativa a la raíz del proyecto. */
    public const DIR_DOCS = 'docs/manual';

    private Documentacion $model;
    private DocumentacionService $service;
    private LogSistemaService $log;

    public function __construct()
    {
        $this->model   = new Documentacion();
        $this->service = new DocumentacionService();
        $this->log     = new LogSistemaService();
    }

    /**
     * Recorre docs/manual/ y publica los artículos.
     *
     * @return array{
     *   ok:bool, ruta:string, creados:int, actualizados:int, sin_cambios:int,
     *   obsoletos:int, omitidos:array<int,string>, errores:array<int,string>,
     *   detalle:array<int,array{archivo:string,slug:string,accion:string}>
     * }
     */
    public function sincronizar(int $idUsuario): array
    {
        $ruta = $this->rutaDocs();

        $resumen = [
            'ok'           => true,
            'ruta'         => self::DIR_DOCS,
            'creados'      => 0,
            'actualizados' => 0,
            'sin_cambios'  => 0,
            'obsoletos'    => 0,
            'omitidos'     => [],
            'errores'      => [],
            'detalle'      => [],
        ];

        if (!is_dir($ruta)) {
            $resumen['ok'] = false;
            $resumen['errores'][] = 'No existe la carpeta ' . self::DIR_DOCS
                . ' en el servidor. Haga "git pull" o cree la carpeta con los archivos .md.';
            return $resumen;
        }

        $archivos = $this->listarArchivos($ruta);
        if ($archivos === []) {
            $resumen['errores'][] = 'La carpeta ' . self::DIR_DOCS . ' no contiene archivos .md.';
            return $resumen;
        }

        $vistos = [];

        foreach ($archivos as $absoluto) {
            $relativo = $this->rutaRelativa($absoluto, $ruta);

            try {
                $procesado = $this->procesarArchivo($absoluto, $relativo, $idUsuario);
                $vistos[] = $relativo;

                $resumen['detalle'][] = [
                    'archivo' => $relativo,
                    'slug'    => $procesado['slug'],
                    'accion'  => $procesado['accion'],
                ];

                switch ($procesado['accion']) {
                    case 'creado':
                        $resumen['creados']++;
                        break;
                    case 'actualizado':
                        $resumen['actualizados']++;
                        break;
                    case 'omitido':
                        $resumen['omitidos'][] = $relativo . ' → se edita desde la pantalla (origen manual)';
                        break;
                    default:
                        $resumen['sin_cambios']++;
                }
            } catch (\Throwable $e) {
                $resumen['errores'][] = $relativo . ': ' . $e->getMessage();
            }
        }

        // Artículos de archivo que ya no tienen su .md → obsoletos.
        $resumen['obsoletos'] = $this->marcarHuerfanos($vistos, $idUsuario);

        try {
            $this->log->registrar($idUsuario, null, 'sincronizar', 'documentacion', null, null, [
                'creados'      => $resumen['creados'],
                'actualizados' => $resumen['actualizados'],
                'sin_cambios'  => $resumen['sin_cambios'],
                'obsoletos'    => $resumen['obsoletos'],
                'omitidos'     => count($resumen['omitidos']),
                'errores'      => count($resumen['errores']),
            ]);
        } catch (\Throwable $e) {
            // La auditoría no debe invalidar una sincronización correcta.
        }

        return $resumen;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Internos
    // ────────────────────────────────────────────────────────────────────

    /**
     * Lee un .md y lo publica según las reglas de sincronización.
     *
     * @return array{slug:string,accion:string}
     */
    private function procesarArchivo(string $absoluto, string $relativo, int $idUsuario): array
    {
        $crudo = file_get_contents($absoluto);
        if ($crudo === false) {
            throw new \RuntimeException('No se pudo leer el archivo.');
        }

        $hash = hash('sha256', $crudo);
        [$meta, $cuerpo] = MarkdownSimple::separarFrontMatter($crudo);

        $slug = trim((string) ($meta['slug'] ?? '')) ?: $this->slugDesdeRuta($relativo);
        if ($slug === '') {
            throw new \RuntimeException('No se pudo determinar la dirección (slug) del artículo.');
        }

        $existente = $this->model->findPorSlug($slug);

        // Lo editado desde la pantalla manda sobre el repositorio.
        if ($existente !== null && ($existente['origen'] ?? 'manual') !== 'archivo') {
            return ['slug' => $slug, 'accion' => 'omitido'];
        }

        // Mismo contenido que la última vez: no hay nada que hacer.
        if ($existente !== null && ($existente['hash_archivo'] ?? '') === $hash) {
            return ['slug' => $slug, 'accion' => 'sin_cambios'];
        }

        $datos = $this->datosDesdeMeta($meta, $cuerpo, $slug, $relativo, $hash);

        if ($existente === null) {
            $this->service->crear($datos, $idUsuario);
            return ['slug' => $slug, 'accion' => 'creado'];
        }

        $this->service->actualizar((int) $existente['id'], $datos, $idUsuario);
        return ['slug' => $slug, 'accion' => 'actualizado'];
    }

    /**
     * Traduce el front-matter y el cuerpo Markdown a la fila del artículo.
     *
     * @param array<string,string> $meta
     * @return array<string,mixed>
     */
    private function datosDesdeMeta(array $meta, string $cuerpo, string $slug, string $relativo, string $hash): array
    {
        $rutaModulo = trim((string) ($meta['ruta_modulo'] ?? ''));

        // Por defecto se exige el permiso del módulo documentado; el archivo
        // puede desactivarlo con "requiere_permiso_modulo: no".
        $requierePermiso = array_key_exists('requiere_permiso_modulo', $meta)
            ? $this->aBooleano($meta['requiere_permiso_modulo'])
            : ($rutaModulo !== '');

        return [
            'slug'                    => $slug,
            'titulo'                  => trim((string) ($meta['titulo'] ?? '')) ?: $this->tituloDesdeCuerpo($cuerpo, $slug),
            'resumen'                 => trim((string) ($meta['resumen'] ?? '')),
            'contenido_md'            => $cuerpo,
            'contenido_html'          => MarkdownSimple::aHtml($cuerpo),
            'categoria'               => trim((string) ($meta['categoria'] ?? '')),
            'ruta_modulo'             => $rutaModulo,
            'tipo'                    => trim((string) ($meta['tipo'] ?? 'modulo')),
            // La visibilidad de config/ la fuerza el Service igualmente.
            'visibilidad'             => trim((string) ($meta['visibilidad'] ?? 'todos')),
            'requiere_permiso_modulo' => $requierePermiso,
            'etiquetas'               => trim((string) ($meta['etiquetas'] ?? '')),
            'version'                 => trim((string) ($meta['version'] ?? '')),
            'orden'                   => (int) ($meta['orden'] ?? 0),
            'estado'                  => trim((string) ($meta['estado'] ?? 'activo')),
            'origen'                  => 'archivo',
            'archivo_origen'          => $relativo,
            'hash_archivo'            => $hash,
        ];
    }

    /**
     * Marca como obsoletos los artículos de origen 'archivo' cuyo .md ya no
     * está en el repositorio.
     *
     * @param array<int,string> $vistos rutas relativas procesadas en esta corrida
     */
    private function marcarHuerfanos(array $vistos, int $idUsuario): int
    {
        if ($vistos === []) {
            return 0; // Sin archivos leídos no se declara huérfano a nadie.
        }

        $indice = array_flip($vistos);
        $n = 0;

        foreach ($this->model->getDeOrigenArchivo() as $art) {
            $archivo = (string) ($art['archivo_origen'] ?? '');
            if ($archivo === '' || isset($indice[$archivo])) {
                continue;
            }
            if (($art['estado'] ?? '') === 'obsoleto') {
                continue;
            }
            if ($this->model->marcarObsoleto((int) $art['id'], $idUsuario)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Archivos .md de la carpeta, en orden estable. Se ignoran los que empiezan
     * por "_" (plantillas) y el README.
     *
     * @return array<int,string>
     */
    private function listarArchivos(string $ruta): array
    {
        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS)
        );

        $archivos = [];
        foreach ($iterador as $archivo) {
            if (!$archivo->isFile() || strtolower($archivo->getExtension()) !== 'md') {
                continue;
            }
            $nombre = $archivo->getFilename();
            if (str_starts_with($nombre, '_') || strcasecmp($nombre, 'README.md') === 0) {
                continue;
            }
            $archivos[] = $archivo->getPathname();
        }

        sort($archivos);
        return $archivos;
    }

    private function rutaDocs(): string
    {
        return rtrim(MVC_ROOT, '/\\') . '/' . self::DIR_DOCS;
    }

    /** Ruta del archivo relativa a docs/manual/, siempre con barras normales. */
    private function rutaRelativa(string $absoluto, string $base): string
    {
        $absoluto = str_replace('\\', '/', $absoluto);
        $base     = rtrim(str_replace('\\', '/', $base), '/') . '/';

        return str_starts_with($absoluto, $base)
            ? substr($absoluto, strlen($base))
            : basename($absoluto);
    }

    /** docs/manual/modulos/clientes.md → 'modulos/clientes' */
    private function slugDesdeRuta(string $relativo): string
    {
        return preg_replace('/\.md$/i', '', $relativo) ?? $relativo;
    }

    /** Si el archivo no declara título, se usa el primer encabezado del cuerpo. */
    private function tituloDesdeCuerpo(string $cuerpo, string $slug): string
    {
        if (preg_match('/^\s*#{1,3}\s+(.+)$/m', $cuerpo, $m) === 1) {
            return trim($m[1]);
        }
        $ultimo = basename($slug);
        return ucfirst(str_replace(['-', '_'], ' ', $ultimo));
    }

    private function aBooleano(string $valor): bool
    {
        return !in_array(strtolower(trim($valor)), ['no', 'false', '0', 'off', ''], true);
    }
}
