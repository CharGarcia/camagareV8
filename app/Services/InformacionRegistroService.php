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
}
