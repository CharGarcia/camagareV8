<?php
/**
 * Doctor del Manual del Sistema.
 *
 * Cruza las pantallas que REALMENTE existen (submódulos del menú + rutas
 * registradas en config/modulos_mvc.php) contra los artículos publicados, y
 * responde a la única pregunta que importa para que el manual no se pudra:
 * ¿qué falta por documentar?
 *
 * Es la pieza que sostiene la regla del §12 de CLAUDE.md. Sin una pantalla que
 * enseñe los huecos, la regla se cumple las dos primeras semanas.
 *
 * ─── QUÉ DETECTA ────────────────────────────────────────────────────────────
 *   - Sin documentar : la pantalla existe y no hay artículo para ella.
 *   - Huérfanos      : el artículo apunta a una ruta que no existe (error de
 *                      tecleo o módulo retirado).
 *   - Incompletos    : el artículo existe pero le faltan resumen, categoría o
 *                      etiquetas — sin etiquetas casi no se encuentra buscando.
 *   - Obsoletos      : marcados por el sincronizador porque su .md desapareció.
 *
 * ─── QUÉ NO DETECTA (y por qué) ─────────────────────────────────────────────
 * No compara la fecha del artículo con la del código para avisar de
 * documentación "desactualizada". En el servidor se despliega con "git pull",
 * que reescribe la fecha de TODOS los archivos: la señal marcaría el sistema
 * entero como desactualizado en cada despliegue. Una alarma en la que no se
 * puede confiar es peor que no tenerla.
 */

declare(strict_types=1);

namespace App\Services;

use App\models\Documentacion;
use App\models\ModuloSubmodulo;

class DocumentacionDoctorService
{
    private Documentacion $model;
    private ModuloSubmodulo $submodulos;

    public function __construct()
    {
        $this->model      = new Documentacion();
        $this->submodulos = new ModuloSubmodulo();
    }

    /**
     * @return array{
     *   total_rutas:int, documentadas:int, cobertura:int,
     *   sin_documentar:array<int,array{ruta:string,nombre:string,modulo:string}>,
     *   huerfanos:array<int,array{slug:string,titulo:string,ruta_modulo:string}>,
     *   incompletos:array<int,array{slug:string,titulo:string,faltan:array<int,string>}>,
     *   obsoletos:array<int,array{slug:string,titulo:string,archivo:string}>
     * }
     */
    public function diagnostico(): array
    {
        $rutasSistema = $this->rutasDelSistema();
        $articulos    = $this->model->getAll('categoria', 'ASC', '');

        // Índice de rutas cubiertas por algún artículo. Se aceptan tanto la
        // ruta_modulo del artículo como su slug: documentar 'modulos/clientes'
        // cubre la pantalla 'modulos/clientes' aunque no se haya rellenado el
        // campo de ruta.
        $cubiertas = [];
        foreach ($articulos as $a) {
            if (($a['estado'] ?? '') === 'obsoleto') {
                continue;
            }
            foreach ([$a['ruta_modulo'] ?? '', $a['slug'] ?? ''] as $candidata) {
                $norm = $this->normalizarRuta((string) $candidata);
                if ($norm !== '') {
                    $cubiertas[$norm] = true;
                }
            }
        }

        // 1. Pantallas sin documentar.
        $sinDocumentar = [];
        foreach ($rutasSistema as $norm => $info) {
            if (!isset($cubiertas[$norm])) {
                $sinDocumentar[] = [
                    'ruta'   => $info['ruta'],
                    'nombre' => $info['nombre'],
                    'modulo' => $info['modulo'],
                ];
            }
        }

        // 2, 3, 4. Problemas de los artículos existentes.
        $huerfanos = [];
        $incompletos = [];
        $obsoletos = [];

        foreach ($articulos as $a) {
            $slug   = (string) ($a['slug'] ?? '');
            $titulo = (string) ($a['titulo'] ?? '');

            if (($a['estado'] ?? '') === 'obsoleto') {
                $obsoletos[] = [
                    'slug'    => $slug,
                    'titulo'  => $titulo,
                    'archivo' => (string) ($a['archivo_origen'] ?? ''),
                ];
                continue;
            }

            $ruta = $this->normalizarRuta((string) ($a['ruta_modulo'] ?? ''));
            if ($ruta !== '' && !isset($rutasSistema[$ruta])) {
                $huerfanos[] = [
                    'slug'        => $slug,
                    'titulo'      => $titulo,
                    'ruta_modulo' => (string) $a['ruta_modulo'],
                ];
            }

            $faltan = [];
            if (trim((string) ($a['resumen'] ?? '')) === '') {
                $faltan[] = 'resumen';
            }
            if (trim((string) ($a['categoria'] ?? '')) === '') {
                $faltan[] = 'categoría';
            }
            if (trim((string) ($a['etiquetas'] ?? '')) === '') {
                $faltan[] = 'etiquetas';
            }
            if ($faltan !== []) {
                $incompletos[] = ['slug' => $slug, 'titulo' => $titulo, 'faltan' => $faltan];
            }
        }

        $total        = count($rutasSistema);
        $documentadas = $total - count($sinDocumentar);

        return [
            'total_rutas'    => $total,
            'documentadas'   => $documentadas,
            'cobertura'      => $total > 0 ? (int) round($documentadas * 100 / $total) : 0,
            'sin_documentar' => $sinDocumentar,
            'huerfanos'      => $huerfanos,
            'incompletos'    => $incompletos,
            'obsoletos'      => $obsoletos,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    //  Internos
    // ────────────────────────────────────────────────────────────────────

    /**
     * Pantallas documentables del sistema, indexadas por su ruta normalizada.
     *
     * Se combinan dos fuentes porque ninguna está completa por sí sola:
     * submodulos_menu tiene el nombre legible que ve el usuario, y
     * config/modulos_mvc.php tiene módulos que pueden no estar aún en el menú.
     *
     * @return array<string,array{ruta:string,nombre:string,modulo:string}>
     */
    private function rutasDelSistema(): array
    {
        $rutas = [];

        foreach ($this->submodulos->getRutasConNombre() as $sm) {
            $ruta = (string) ($sm['ruta'] ?? '');
            $norm = $this->normalizarRuta($ruta);
            if ($norm === '' || !$this->esDocumentable($norm)) {
                continue;
            }
            $rutas[$norm] = [
                'ruta'   => $ruta,
                'nombre' => (string) ($sm['nombre_submodulo'] ?? $ruta),
                'modulo' => (string) ($sm['nombre_modulo'] ?? ''),
            ];
        }

        foreach ($this->rutasDeConfig() as $ruta) {
            $norm = $this->normalizarRuta($ruta);
            if ($norm === '' || isset($rutas[$norm]) || !$this->esDocumentable($norm)) {
                continue;
            }
            $rutas[$norm] = [
                'ruta'   => $ruta,
                'nombre' => ucfirst(str_replace(['-', '_'], ' ', basename($ruta))),
                'modulo' => 'No está en el menú',
            ];
        }

        ksort($rutas);
        return $rutas;
    }

    /** Claves de config/modulos_mvc.php (las rutas MVC registradas). @return array<int,string> */
    private function rutasDeConfig(): array
    {
        $archivo = MVC_CONFIG . '/modulos_mvc.php';
        if (!is_file($archivo)) {
            return [];
        }

        try {
            $config = require $archivo;
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($config) ? array_keys($config) : [];
    }

    /**
     * Descarta rutas que no son pantallas documentables: enlaces a archivos
     * legacy (.php) y rutas vacías o absolutas externas.
     */
    private function esDocumentable(string $rutaNormalizada): bool
    {
        if ($rutaNormalizada === '' || str_contains($rutaNormalizada, '.php')) {
            return false;
        }
        return !str_starts_with($rutaNormalizada, 'http');
    }

    /**
     * Normaliza una ruta para poder comparar submodulos_menu con las rutas MVC.
     *
     * Replica la lógica de PermisoSubmodulo::normalizarRutaSubmodulo() (que es
     * privada): minúsculas, sin prefijo 'sistema/', sin barra inicial y con '_'
     * unificado a '-', porque el Router trata ambos como equivalentes. Sin esto,
     * un submódulo guardado como 'modulos/unidades_medida' se reportaría como
     * no documentado aunque su artículo sea 'modulos/unidades-medida'.
     */
    private function normalizarRuta(string $ruta): string
    {
        $r = strtolower(trim($ruta));
        if ($r === '') {
            return '';
        }
        $r = str_replace(['../', './'], '', $r);
        $r = preg_replace('#^(sistema/)+#', '', $r) ?? $r;
        $r = ltrim($r, '/');

        return str_replace('_', '-', $r);
    }
}
