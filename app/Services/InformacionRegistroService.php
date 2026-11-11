<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Booleano;
use App\Helpers\InformacionRegistro;
use App\repositories\InformacionRegistroRepository;

/**
 * Datos de la pestaña «Información» de los modales (ver App\Helpers\InformacionRegistro):
 * registro (quién/cuándo), historial de acciones (log_sistema) y movimientos de inventario.
 * Solo lectura; las fechas salen ya en d-m-Y H:i:s.
 */
class InformacionRegistroService
{
    private InformacionRegistroRepository $repository;
    private LogSistemaService $logService;

    public function __construct(?InformacionRegistroRepository $repository = null, ?LogSistemaService $logService = null)
    {
        $this->repository = $repository ?? new InformacionRegistroRepository();
        $this->logService = $logService ?? new LogSistemaService();
    }

    /**
     * @param string   $rutaModulo      ruta MVC del módulo (clave del catálogo).
     * @param int|null $idUsuarioFiltro registros propios (§6): null si el usuario tiene acceso total.
     * @return array|null null si el módulo no está en el catálogo o el registro no existe / no es visible.
     */
    public function obtener(string $rutaModulo, int $id, int $idEmpresa, ?int $idUsuarioFiltro = null): ?array
    {
        $cfg = InformacionRegistro::config($rutaModulo);
        if ($cfg === null || $id <= 0) {
            return null;
        }
        $empresaFiltro = !empty($cfg['global']) ? null : $idEmpresa;

        $registro = $this->repository->getDatosRegistro((string) $cfg['tabla'], $id, $empresaFiltro, $idUsuarioFiltro);
        if ($registro === null) {
            return null;
        }
        $fmt = static fn($f): string => !empty($f) ? date('d-m-Y H:i:s', strtotime((string) $f)) : '';
        $registro['created_at'] = $fmt($registro['created_at']);
        $registro['updated_at'] = $fmt($registro['updated_at']);

        $historial = [];
        foreach ($this->logService->getHistorial((string) $cfg['tabla'], $id, $empresaFiltro) as $log) {
            $historial[] = [
                'fecha'    => $log['created_at'], // getHistorial ya la entrega en d-m-Y H:i:s
                'usuario'  => $log['usuario_nombre'] ?? '',
                'accion'   => $log['accion'] ?? '',
                'etiqueta' => $this->etiquetaPropia((array) $cfg['etiquetas'], $log),
                'detalles' => $log['detalles'] ?? [],
            ];
        }

        $conInventario = !empty($cfg['kardex']);
        $inventario    = $conInventario ? $this->repository->getMovimientosInventario((array) $cfg['kardex'], $id, $idEmpresa, $cfg['kardex_detalle'] ?? null) : [];
        foreach ($inventario as &$m) {
            $m['created_at'] = $fmt($m['created_at']);
            $m['deleted_at'] = $fmt($m['deleted_at']);
            $m['eliminado']  = Booleano::es($m['eliminado']);
        }
        unset($m);

        return [
            'registro'       => $registro,
            'historial'      => $historial,
            'con_inventario' => $conInventario,
            'inventario'     => $inventario,
        ];
    }

    /**
     * Texto propio del módulo para la acción de un evento (catálogo → 'etiquetas'); null si el
     * módulo no definió ninguno para esa acción y se usa el texto común del JS.
     */
    private function etiquetaPropia(array $etiquetas, array $log): ?string
    {
        $accion = strtoupper((string) ($log['accion'] ?? ''));
        foreach ($etiquetas as $fragmento => $plantilla) {
            if (!str_contains($accion, strtoupper((string) $fragmento))) {
                continue;
            }
            $datos = json_decode((string) ($log['datos_nuevos'] ?? ''), true);
            $datos = is_array($datos) ? $datos : [];
            $texto = preg_replace_callback(
                '/\{(\w+)\}/',
                static fn(array $m): string => is_scalar($datos[$m[1]] ?? null) ? (string) $datos[$m[1]] : '',
                (string) $plantilla
            );
            return trim((string) preg_replace('/\s+/', ' ', (string) $texto));
        }
        return null;
    }
}
