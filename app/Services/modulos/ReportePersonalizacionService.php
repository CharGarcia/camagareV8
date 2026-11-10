<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\ColumnasReporte;
use App\repositories\modulos\ReportePersonalizacionRepository;
use App\Rules\modulos\ReportePersonalizacionRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Personalización de reportes por empresa: qué columnas se muestran en pantalla y Excel,
 * cuáles salen en el PDF y la orientación de la hoja, para cada vista de un reporte del
 * catálogo App\Helpers\ColumnasReporte. La configura un administrador (niveles 2 y 3)
 * con el botón «Personalizar» y aplica a todos los usuarios de la empresa.
 *
 * Sin configuración guardada, cada vista usa sus valores por defecto (todas las
 * columnas en pantalla y Excel; en el PDF, las que el catálogo no marca `pdf => false`;
 * orientación automática).
 */
class ReportePersonalizacionService
{
    /** Configuración ya leída en esta petición: "idEmpresa|modulo" => vista => config. */
    private static array $cache = [];

    public function __construct(
        private ReportePersonalizacionRepository $repository,
        private ReportePersonalizacionRules $rules,
        private LogSistemaService $logService
    ) {
    }

    /** Instancia lista para usar desde un controlador. */
    public static function crear(): self
    {
        return new self(new ReportePersonalizacionRepository(), new ReportePersonalizacionRules(), new LogSistemaService());
    }

    /**
     * Configuración vigente de cada vista del reporte (guardada o por defecto):
     * vista => ['ocultas' => [], 'ocultas_pdf' => [], 'orientacion' => 'auto', 'personalizada' => bool].
     * Las claves que ya no existen en el catálogo se ignoran.
     */
    public function configuracion(int $idEmpresa, string $modulo): array
    {
        $modulo = ColumnasReporte::normalizar($modulo);
        $k = $idEmpresa . '|' . $modulo;
        if (isset(self::$cache[$k])) {
            return self::$cache[$k];
        }

        $filas = $this->repository->getPorModulo($idEmpresa, $modulo);
        $out   = [];
        foreach (array_keys(ColumnasReporte::vistas($modulo)) as $vista) {
            $config = $this->porDefecto($modulo, $vista) + ['personalizada' => false];
            if (isset($filas[$vista])) {
                $guardada = json_decode((string) $filas[$vista]['configuracion'], true);
                if (is_array($guardada)) {
                    $columnas = ColumnasReporte::columnas($modulo, $vista);
                    // Claves vigentes del catálogo, más las que no existen en ese medio.
                    $validas  = static fn ($lista, string $medio = ''): array => array_values(array_unique(array_merge(
                        array_filter(
                            array_map('strval', is_array($lista) ? $lista : []),
                            static fn (string $c): bool => isset($columnas[$c]) && !$columnas[$c]['fija']
                        ),
                        $medio !== '' ? ColumnasReporte::noDisponibles($modulo, $vista, $medio) : []
                    )));
                    $orientacion = (string) ($guardada['orientacion'] ?? 'auto');
                    $config = [
                        'ocultas'       => $validas($guardada['ocultas'] ?? [], 'pantalla'),
                        'ocultas_excel' => ColumnasReporte::excelPropio($modulo, $vista)
                            ? $validas($guardada['ocultas_excel'] ?? $config['ocultas_excel'], 'excel')
                            : [],
                        'ocultas_pdf'   => $validas($guardada['ocultas_pdf'] ?? [], 'pdf'),
                        'orientacion'   => in_array($orientacion, ColumnasReporte::ORIENTACIONES, true) ? $orientacion : 'auto',
                        'personalizada' => true,
                    ];
                }
            }
            $out[$vista] = $config;
        }
        return self::$cache[$k] = $out;
    }

    /**
     * Lo que el controlador del reporte necesita para pintar una vista:
     *   'pantalla'    columnas visibles en pantalla (clave => definición),
     *   'excel'       columnas del Excel: las de pantalla, salvo que la vista lo elija aparte,
     *   'pdf'         columnas del PDF (clave => definición),
     *   'orientacion' 'auto' | 'P' | 'L' tal como la eligió el administrador,
     *   'horizontal'  la orientación ya resuelta con el automático del catálogo. Un
     *                 reporte cuyo automático depende de los datos usa 'orientacion'.
     * Vista desconocida: columnas vacías.
     */
    public function resolver(int $idEmpresa, string $modulo, string $vista): array
    {
        $columnas = ColumnasReporte::columnas($modulo, $vista);
        $config   = $this->configuracion($idEmpresa, $modulo)[$vista]
            ?? ['ocultas' => [], 'ocultas_excel' => [], 'ocultas_pdf' => [], 'orientacion' => 'auto'];
        $sin      = static fn (array $ocultas, string $medio): array => ColumnasReporte::ordenar($modulo, $vista, $medio, array_filter(
            $columnas,
            static fn (string $clave): bool => !in_array($clave, $ocultas, true),
            ARRAY_FILTER_USE_KEY
        ));
        $auto     = (string) (ColumnasReporte::vistas($modulo)[$vista]['auto'] ?? 'L');
        $pantalla = $sin($config['ocultas'], 'pantalla');

        return [
            'pantalla'    => $pantalla,
            'excel'       => ColumnasReporte::excelPropio($modulo, $vista) ? $sin($config['ocultas_excel'], 'excel') : $pantalla,
            'pdf'         => $sin($config['ocultas_pdf'], 'pdf'),
            'orientacion' => $config['orientacion'],
            'horizontal'  => ($config['orientacion'] === 'auto' ? $auto : $config['orientacion']) === 'L',
        ];
    }

    /**
     * Columnas de una vista para los módulos que arman la tabla en el navegador: TODAS las
     * del catálogo, en el orden de la pantalla, con `visible` (según la empresa) y
     * `defecto` (visible sin personalizar), más lo que la tabla necesita de cada una.
     */
    public function pantallaJs(int $idEmpresa, string $modulo, string $vista): array
    {
        $visibles = $this->resolver($idEmpresa, $modulo, $vista)['pantalla'];
        $out      = [];
        foreach (ColumnasReporte::ordenar($modulo, $vista, 'pantalla', ColumnasReporte::columnas($modulo, $vista)) as $clave => $col) {
            $out[] = [
                'clave'   => $clave,
                'titulo'  => $col['titulo'],
                'clase'   => ColumnasReporte::claseAlinear($col['alinear']),
                'px'      => $col['px'],
                'sort'    => $col['sort'],
                'tip'     => $col['tip'],
                'visible' => isset($visibles[$clave]),
                'defecto' => $col['pantalla'],
            ];
        }
        return $out;
    }

    /**
     * Datos de la ventana de personalización: por cada vista, los medios que se eligen
     * (pantalla, Excel, PDF), sus columnas con la marca de cada medio y la orientación.
     */
    public function paraModal(int $idEmpresa, string $modulo): array
    {
        $config = $this->configuracion($idEmpresa, $modulo);
        $out    = [];
        foreach (ColumnasReporte::vistas($modulo) as $vista => $def) {
            $c           = $config[$vista];
            $excelPropio = ColumnasReporte::excelPropio($modulo, $vista);
            $cols        = [];
            foreach (ColumnasReporte::ordenar($modulo, $vista, 'pantalla', ColumnasReporte::columnas($modulo, $vista)) as $clave => $col) {
                $cols[] = [
                    'clave'       => $clave,
                    'titulo'      => $col['nombre'],
                    'fija'        => $col['fija'],
                    'pantalla'    => !in_array($clave, $c['ocultas'], true),
                    'excel'       => !in_array($clave, $c['ocultas_excel'], true),
                    'pdf'         => !in_array($clave, $c['ocultas_pdf'], true),
                    'pdf_defecto' => $col['pdf'],
                    // Medios donde la columna no existe: la ventana muestra una raya.
                    'no'          => $col['no'],
                ];
            }
            $out[] = [
                'vista'         => $vista,
                'titulo'        => $def['titulo'],
                'medios'        => $excelPropio
                    ? [['clave' => 'pantalla', 'titulo' => 'Pantalla'], ['clave' => 'excel', 'titulo' => 'Excel'], ['clave' => 'pdf', 'titulo' => 'PDF']]
                    : [['clave' => 'pantalla', 'titulo' => 'Pantalla y Excel'], ['clave' => 'pdf', 'titulo' => 'PDF']],
                'auto'          => $def['auto'] ?? 'L',
                'auto_txt'      => $def['auto_txt'] ?? null,
                'orientacion'   => $c['orientacion'],
                'personalizada' => $c['personalizada'],
                'columnas'      => $cols,
            ];
        }
        return $out;
    }

    /**
     * Guarda la personalización de las vistas recibidas (vista => config). Una vista
     * que queda igual a sus valores por defecto no ocupa fila: si tenía una, se elimina
     * (lógicamente). Las vistas no enviadas no se tocan.
     *
     * @param array<string,array> $vistas
     */
    public function guardar(int $idActor, int $nivelActor, int $idEmpresa, string $modulo, array $vistas): void
    {
        $modulo = ColumnasReporte::normalizar($modulo);
        $this->rules->validarActor($nivelActor);
        $this->rules->validarModulo($modulo);
        if (!$this->repository->disponible()) {
            throw new Exception('Falta ejecutar database/2026-11-10_reportes_personalizacion.sql en la base de datos.');
        }
        if (!$vistas) {
            throw new Exception('No se recibió ninguna vista para guardar.');
        }

        $nuevas = [];
        foreach ($vistas as $vista => $config) {
            $nuevas[(string) $vista] = $this->rules->normalizarVista($modulo, (string) $vista, is_array($config) ? $config : []);
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->lock($idEmpresa, $modulo);
            $filas = $this->repository->getPorModulo($idEmpresa, $modulo);

            foreach ($nuevas as $vista => $config) {
                $actual    = $filas[$vista] ?? null;
                $esDefecto = $config == $this->porDefecto($modulo, $vista);
                $json      = json_encode($config, JSON_UNESCAPED_UNICODE);
                $datos     = ['modulo' => $modulo, 'vista' => $vista, 'configuracion' => $config];

                if ($actual === null) {
                    if (!$esDefecto) {
                        $id = $this->repository->crear($idEmpresa, $modulo, $vista, $json, $idActor);
                        $this->logService->registrar($idActor, $idEmpresa, 'crear', 'reportes_personalizacion', $id, null, $datos);
                    }
                    continue;
                }

                $antes = ['modulo' => $modulo, 'vista' => $vista, 'configuracion' => json_decode((string) $actual['configuracion'], true)];
                // Lo guardado antes de que existiera `ocultas_excel` equivale a tenerla vacía.
                if (is_array($antes['configuracion'])) {
                    $antes['configuracion'] += ['ocultas_excel' => []];
                }
                if ($esDefecto) {
                    $this->repository->eliminar((int) $actual['id'], $idEmpresa, $idActor);
                    $this->logService->registrar($idActor, $idEmpresa, 'eliminar', 'reportes_personalizacion', (int) $actual['id'], $antes, null);
                } elseif ($antes['configuracion'] != $config) {
                    $this->repository->actualizar((int) $actual['id'], $idEmpresa, $json, $idActor);
                    $this->logService->registrar($idActor, $idEmpresa, 'actualizar', 'reportes_personalizacion', (int) $actual['id'], $antes, $datos);
                }
            }

            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
        unset(self::$cache[$idEmpresa . '|' . $modulo]);
    }

    /** Devuelve todas las vistas del reporte a sus valores por defecto. */
    public function restablecer(int $idActor, int $nivelActor, int $idEmpresa, string $modulo): void
    {
        $modulo = ColumnasReporte::normalizar($modulo);
        $vistas = [];
        foreach (array_keys(ColumnasReporte::vistas($modulo)) as $vista) {
            $vistas[$vista] = $this->porDefecto($modulo, $vista);
        }
        $this->guardar($idActor, $nivelActor, $idEmpresa, $modulo, $vistas);
    }

    /** Configuración por defecto de una vista. */
    private function porDefecto(string $modulo, string $vista): array
    {
        return ColumnasReporte::configPorDefecto($modulo, $vista);
    }
}
