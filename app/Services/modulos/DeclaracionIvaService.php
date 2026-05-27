<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\DeclaracionIvaRepository;
use App\repositories\modulos\FacturaVentaRepository;

class DeclaracionIvaService
{
    private $repository;
    private $fvRepository;

    public function __construct(DeclaracionIvaRepository $repository)
    {
        $this->repository = $repository;
        $this->fvRepository = new FacturaVentaRepository();
    }

    /**
     * Ejecuta la auditoría para un periodo.
     */
    public function auditarPeriodo(int $idEmpresa, string $anio, string $mes): array
    {
        $fechaDesde = "{$anio}-{$mes}-01";
        $fechaHasta = date("Y-m-t", strtotime($fechaDesde));

        $descuadres = $this->repository->getDescuadresVentas($idEmpresa, $fechaDesde, $fechaHasta);
        
        return [
            'ok' => true,
            'descuadres' => $descuadres,
            'recuento' => count($descuadres)
        ];
    }

    /**
     * Regenera los casilleros para las facturas que presentan inconsistencias.
     */
    public function sincronizarPeriodo(int $idEmpresa, string $anio, string $mes, int $idUsuario): array
    {
        $fechaDesde = "{$anio}-{$mes}-01";
        $fechaHasta = date("Y-m-t", strtotime($fechaDesde));

        $descuadres = $this->repository->getDescuadresVentas($idEmpresa, $fechaDesde, $fechaHasta);
        
        if (empty($descuadres)) {
            return ['ok' => true, 'mensaje' => 'No se encontraron descuadres para sincronizar.'];
        }

        $fvService = new FacturaVentaService($this->fvRepository, new \App\Rules\modulos\FacturaVentaRules(), new \App\Services\LogSistemaService());
        
        $procesados = 0;
        foreach ($descuadres as $d) {
            $idVenta = (int)$d['id_venta'];
            
            // Obtener datos completos de la factura y llamar a la sincronización
            $factura = $this->fvRepository->getPorId($idVenta);
            if ($factura) {
                // Preparamos la data mínima requerida por registrarCasillerosFv
                $data = [
                    'id_empresa'         => $idEmpresa,
                    'id_establecimiento' => (int) $factura['id_establecimiento'],
                    'id_usuario'         => $idUsuario,
                    'fecha_emision'      => $factura['fecha_emision']
                ];
                
                // Llamamos al método (necesitamos que sea público)
                $fvService->sincronizarCasilleros($idVenta, $data);
                $procesados++;
            }
        }

        return [
            'ok' => true, 
            'mensaje' => "Se han sincronizado {$procesados} facturas correctamente.",
            'procesados' => $procesados
        ];
    }
}
