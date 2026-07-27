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
    /**
     * Título de los eventos de catálogo dicho en términos del producto, no de la
     * tabla: el usuario ve "Producto creado", no "Crear".
     */
    private const TITULOS_CATALOGO = [
        'crear'      => 'Producto creado',
        'actualizar' => 'Ficha del producto modificada',
        'eliminar'   => 'Producto eliminado',
    ];

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
            $accion  = mb_strtolower(trim((string) $log['accion']), 'UTF-8');
            $cambios = $this->cambiosDeFicha($accion, $log['detalles']);

            $eventos[] = [
                'tipo'       => 'catalogo',
                'fecha_ts'   => strtotime($log['created_at']) ?: 0,
                'fecha'      => $log['created_at'],
                'titulo'     => self::TITULOS_CATALOGO[$accion] ?? AuditoriaEtiquetas::accion($accion),
                'usuario'    => $log['usuario_nombre'] ?? null,
                'cambios'    => $cambios,
                'resumen'    => $this->resumenFicha($accion, $cambios),
                'detalle'    => $this->detalleTexto($accion, $cambios),
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

        // Documentos previos sin impacto en inventario (pedidos, proformas, órdenes
        // de compra, guías de remisión): informativos, no entran en los KPIs de stock.
        $documentosPrevios = $this->repo->getDocumentosPrevios($idProducto, $idEmpresa, $filtros);
        foreach ($documentosPrevios as $d) {
            $eventos[] = [
                'tipo'            => 'documento',
                'fecha_ts'        => strtotime($d['fecha']) ?: 0,
                'fecha'           => date('d-m-Y H:i:s', strtotime($d['fecha'])),
                'titulo'          => $d['doc_label'],
                'cantidad'        => (float) $d['cantidad'],
                'precio_unitario' => $d['precio_unitario'] !== null ? (float) $d['precio_unitario'] : null,
                'usuario'         => $d['usuario_nombre'],
                'doc_numero'      => $d['doc_numero'],
                'doc_contraparte' => $d['doc_contraparte'],
                'doc_estado'      => $d['doc_estado'],
                'doc_ruta'        => $d['doc_ruta'],
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
     * En un alta o una baja el diff no aporta nada (compara el registro contra la
     * nada): el título ya lo dice todo. Solo la modificación lista campo por campo.
     *
     * @param array<int, array{campo:string,antes:?string,despues:?string}> $detalles
     */
    private function cambiosDeFicha(string $accion, array $detalles): array
    {
        return $accion === 'actualizar' ? $detalles : [];
    }

    /**
     * Frase que explica el evento cuando no hay una lista de cambios que mostrar.
     * Devuelve null si los cambios hablan por sí solos.
     */
    private function resumenFicha(string $accion, array $cambios): ?string
    {
        if ($accion === 'crear') {
            return 'Se dio de alta el producto en el catálogo.';
        }
        if ($accion === 'eliminar') {
            return 'El producto se marcó como eliminado. Su historial se conserva.';
        }
        if ($accion === 'actualizar' && empty($cambios)) {
            return 'Se guardó la ficha sin cambios en sus datos principales. Pueden haberse editado precios, bodegas, componentes o variantes.';
        }

        return null;
    }

    /** Mismo detalle que la pantalla, en una sola línea, para el PDF y el Excel. */
    private function detalleTexto(string $accion, array $cambios): string
    {
        if (empty($cambios)) {
            return (string) $this->resumenFicha($accion, $cambios);
        }

        $partes = [];
        foreach ($cambios as $c) {
            $partes[] = $c['campo'] . ': ' . ($c['antes'] ?? '-') . ' → ' . ($c['despues'] ?? '-');
        }

        return implode('; ', $partes);
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
