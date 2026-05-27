<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\RetencionVentaRepository;
use App\Rules\modulos\RetencionVentaRules;
use App\Services\LogSistemaService;
use App\core\Database;

class RetencionVentaService
{
    private RetencionVentaRepository $repository;
    private RetencionVentaRules      $rules;
    private LogSistemaService        $logService;

    public function __construct(
        RetencionVentaRepository $repository,
        RetencionVentaRules      $rules,
        LogSistemaService        $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? 0);

        // Validar duplicado por número del cliente
        $this->validarDuplicado($idEmpresa, $data);

        // Validar clave de acceso única si viene
        if (!empty($data['clave_acceso'])) {
            if ($this->repository->existeClaveAcceso($data['clave_acceso'], $idEmpresa)) {
                throw new \Exception('Ya existe una retención registrada con esa clave de acceso.');
            }
        }

        $data = $this->calcularTotales($data);
        $data['id_usuario'] = $idUsuario;

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idRetencion = $this->repository->insertCabecera($data);
            $this->guardarLineas($idRetencion, $data['lineas'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'CREAR', 'retencion_venta_cabecera', $idRetencion,
                null, ['total_renta' => $data['total_renta'] ?? 0, 'total_iva' => $data['total_iva'] ?? 0]
            );

            if ($managed) $db->commit();
            return $idRetencion;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTUALIZAR
    // ─────────────────────────────────────────────────────────────────────────

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? 0);

        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Retención no encontrada.');
        }

        $this->rules->validar($data);
        $this->validarDuplicado($idEmpresa, $data, $id);

        if (!empty($data['clave_acceso'])) {
            if ($this->repository->existeClaveAcceso($data['clave_acceso'], $idEmpresa, $id)) {
                throw new \Exception('Ya existe otra retención con esa clave de acceso.');
            }
        }

        $data = $this->calcularTotales($data);
        $data['id_usuario'] = $idUsuario;

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $this->repository->updateCabecera($id, $idEmpresa, $data);
            $this->repository->deleteDetalle($id);
            $this->guardarLineas($id, $data['lineas'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'MODIFICAR', 'retencion_venta_cabecera', $id,
                $cabecera, ['total_renta' => $data['total_renta'] ?? 0, 'total_iva' => $data['total_iva'] ?? 0]
            );

            if ($managed) $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ELIMINAR (lógico)
    // ─────────────────────────────────────────────────────────────────────────

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Retención no encontrada.');
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $this->repository->eliminarLogico($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'ELIMINAR', 'retencion_venta_cabecera', $id,
                $cabecera, ['eliminado' => true]
            );

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREAR DESDE XML (usado por DocumentoAutomatedRegisterService)
    // ─────────────────────────────────────────────────────────────────────────

    public function crearDesdeXml(array $data): int
    {
        $data['origen'] = 'electronico';
        return $this->crear($data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    /** Método público para calcular totales desde servicios externos */
    public function calcularTotalesPublic(array $data): array
    {
        return $this->calcularTotales($data);
    }

    private function calcularTotales(array $data): array
    {
        $totalRenta = 0;
        $totalIva   = 0;
        $totalIsd   = 0;

        if (isset($data['lineas']) && is_array($data['lineas'])) {
            foreach ($data['lineas'] as $i => $linea) {
                $base = (float)($linea['base_imponible']   ?? 0);
                $porc = (float)($linea['porcentaje_retencion'] ?? 0);
                $val  = round(($base * $porc) / 100, 2);

                $data['lineas'][$i]['valor_retenido'] = $val;

                $codImp = strtoupper((string)($linea['codigo_impuesto'] ?? ''));
                if ($codImp === '1' || $codImp === 'RENTA') {
                    $totalRenta += $val;
                } elseif ($codImp === '2' || $codImp === 'IVA') {
                    $totalIva += $val;
                } elseif ($codImp === '6' || $codImp === 'ISD') {
                    $totalIsd += $val;
                }
            }
        }

        $data['total_renta'] = round($totalRenta, 2);
        $data['total_iva']   = round($totalIva,   2);
        $data['total_isd']   = round($totalIsd,   2);
        return $data;
    }

    private function guardarLineas(int $idRetencion, array $lineas): void
    {
        foreach ($lineas as $linea) {
            $linea['id_retencion'] = $idRetencion;
            $this->repository->insertDetalle($linea);
        }
    }

    private function validarDuplicado(int $idEmpresa, array $data, ?int $excluirId = null): void
    {
        $existe = $this->repository->existeNumero(
            $idEmpresa,
            $data['establecimiento'] ?? '',
            $data['punto_emision']   ?? '',
            $data['secuencial']      ?? '',
            (int)($data['id_cliente'] ?? 0),
            $excluirId
        );

        if ($existe) {
            $num = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');
            throw new \Exception("Ya existe una retención registrada con el número {$num} para este cliente.");
        }
    }
}
