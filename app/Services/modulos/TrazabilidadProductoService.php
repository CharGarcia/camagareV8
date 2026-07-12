<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\AuditoriaEtiquetas;
use App\repositories\modulos\TrazabilidadProductoRepository;
use App\Services\LogSistemaService;

/**
 * Orquesta (solo lectura) la línea de tiempo de un producto: eventos de catálogo
 * (creación/modificación, desde log_sistema) + movimientos de inventario_kardex
 * resueltos contra su documento de origen.
 */
class TrazabilidadProductoService
{
    private TrazabilidadProductoRepository $repo;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->repo = new TrazabilidadProductoRepository();
        $this->logService = new LogSistemaService();
    }

    public function buscarProductos(int $idEmpresa, string $q): array
    {
        return $this->repo->buscarProductos($idEmpresa, trim($q));
    }

    /**
     * Línea de tiempo completa de un producto: ficha, resumen (KPIs) y eventos
     * ordenados cronológicamente (ascendente: creación primero).
     *
     * @param array{desde?:string,hasta?:string,tipo_movimiento?:string} $filtros
     */
    public function getLineaTiempo(int $idProducto, int $idEmpresa, array $filtros = []): ?array
    {
        $producto = $this->repo->getProducto($idProducto, $idEmpresa);
        if ($producto === null) {
            return null;
        }

        $movResult  = $this->repo->getMovimientos($idProducto, $idEmpresa, $filtros);
        $movimientos = $this->repo->resolverOrigenes($movResult['rows'], $idEmpresa);

        $eventosCatalogo = $this->logService->getHistorial('productos', $idProducto, $idEmpresa);

        $eventos = [];

        foreach ($eventosCatalogo as $log) {
            $eventos[] = [
                'tipo'       => 'catalogo',
                'fecha_ts'   => strtotime($log['created_at']) ?: 0,
                'fecha'      => $log['created_at'],
                'titulo'     => AuditoriaEtiquetas::accion((string) $log['accion']),
                'usuario'    => $log['usuario_nombre'] ?? null,
                'cambios'    => $log['detalles'],
            ];
        }

        foreach ($movimientos as $m) {
            $eventos[] = [
                'tipo'            => 'movimiento',
                'fecha_ts'        => strtotime($m['fecha_movimiento']) ?: 0,
                'fecha'           => date('d-m-Y H:i:s', strtotime($m['fecha_movimiento'])),
                'titulo'          => $m['doc_label'],
                'tipo_movimiento' => $m['tipo_movimiento'],
                'cantidad'        => (float) $m['cantidad'],
                'stock_anterior'  => (float) $m['stock_anterior'],
                'stock_posterior' => (float) $m['stock_posterior'],
                'costo_unitario'  => (float) $m['costo_unitario'],
                'bodega'          => $m['bodega_nombre'],
                'numero_lote'     => $m['numero_lote'],
                'fecha_caducidad' => $m['fecha_caducidad'],
                'nup'             => $m['nup'],
                'observaciones'   => $m['observaciones'],
                'usuario'         => $m['usuario_nombre'],
                'doc_numero'      => $m['doc_numero'],
                'doc_contraparte' => $m['doc_contraparte'],
                'doc_estado'      => $m['doc_estado'],
                'doc_ruta'        => $m['doc_ruta'],
            ];
        }

        usort($eventos, fn ($a, $b) => $a['fecha_ts'] <=> $b['fecha_ts']);

        return [
            'producto'  => $producto,
            'resumen'   => $this->getResumen($idProducto, $idEmpresa, $movimientos),
            'eventos'   => $eventos,
            'truncado'  => $movResult['truncado'],
        ];
    }

    /**
     * KPIs calculados sobre los movimientos ya cargados (sin consultas extra),
     * salvo el stock actual real que viene de la caché por bodega.
     */
    private function getResumen(int $idProducto, int $idEmpresa, array $movimientos): array
    {
        $entradas = 0.0;
        $salidas  = 0.0;
        $sumaCosto = 0.0;
        $cantEntradas = 0.0;
        $ultimaFecha = null;

        foreach ($movimientos as $m) {
            $cant = (float) $m['cantidad'];
            if ($cant >= 0) {
                $entradas += $cant;
                if ($m['tipo_movimiento'] === 'entrada') {
                    $sumaCosto += (float) $m['costo_total'];
                    $cantEntradas += $cant;
                }
            } else {
                $salidas += abs($cant);
            }
            if ($ultimaFecha === null || $m['fecha_movimiento'] > $ultimaFecha) {
                $ultimaFecha = $m['fecha_movimiento'];
            }
        }

        return [
            'stock_actual'    => $this->repo->getStockTotalCache($idProducto, $idEmpresa),
            'total_entradas'  => $entradas,
            'total_salidas'   => $salidas,
            'costo_promedio'  => $cantEntradas > 0 ? round($sumaCosto / $cantEntradas, 4) : 0.0,
            'ultimo_movimiento' => $ultimaFecha !== null ? date('d-m-Y H:i:s', strtotime($ultimaFecha)) : null,
            'total_movimientos' => count($movimientos),
        ];
    }
}
