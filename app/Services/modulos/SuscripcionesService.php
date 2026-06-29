<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\SuscripcionesRepository;
use App\Rules\modulos\SuscripcionesRules;
use App\Services\LogSistemaService;
use Exception;

class SuscripcionesService
{
    private SuscripcionesRepository $repository;
    private SuscripcionesRules      $rules;
    private LogSistemaService       $logService;

    public function __construct(
        SuscripcionesRepository $repository,
        SuscripcionesRules      $rules,
        LogSistemaService       $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getPeriodicidades(): array
    {
        return $this->repository->getPeriodicidades();
    }

    public function getDetalle(int $idSuscripcion, int $idEmpresa): array
    {
        $susc = $this->repository->findById($idSuscripcion, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }
        return $this->repository->getDetalle($idSuscripcion);
    }

    public function getPagosPorSuscripcion(int $idSuscripcion, int $idEmpresa): array
    {
        $susc = $this->repository->findById($idSuscripcion, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }
        return $this->repository->getPagosPorSuscripcion($idSuscripcion);
    }

    public function crear(array $data): int
    {
        $detalle = $this->_extraerDetalle($data);
        $data['info_adicional'] = $this->_extraerInfoAdicional($data);
        $this->rules->validar($data, $detalle);
        $data['proximo_cobro'] = $data['proximo_cobro'] ?? $data['fecha_inicio'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);

            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            foreach ($detalle as $item) {
                $item['id_suscripcion'] = $id;
                $item['id_empresa']     = $idEmpresa;
                $item['created_by']     = $idUsuario;
                $this->repository->insertDetalle($item);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'suscripciones', $id, null, $data);

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $detalle = $this->_extraerDetalle($data);
        $data['info_adicional'] = $this->_extraerInfoAdicional($data);
        $this->rules->validar($data, $detalle);

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La suscripción no existe o ha sido eliminada.');
        }

        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);

            // Reemplazar detalle: soft-delete los existentes e insertar los nuevos
            $this->repository->deleteDetalle($id, $idUsuario);
            foreach ($detalle as $item) {
                $item['id_suscripcion'] = $id;
                $item['id_empresa']     = $idEmpresa;
                $item['created_by']     = $idUsuario;
                $this->repository->insertDetalle($item);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'suscripciones', $id, $antes, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $estadosValidos = ['activo', 'pausado', 'suspendido', 'cancelado'];
        if (!in_array($estado, $estadosValidos, true)) {
            throw new Exception("Estado '$estado' no válido.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('Suscripción no encontrada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->updateEstado($id, $estado, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'cambiar_estado', 'suscripciones', $id, $antes, ['estado' => $estado]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function guardarTokenKushki(int $id, int $idEmpresa, array $tokenData, int $idUsuario): void
    {
        $susc = $this->repository->findById($id, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->updateKushkiToken(
                $id,
                $idEmpresa,
                $tokenData['token'],
                $tokenData['last4'],
                $tokenData['brand'],
                $tokenData['card_holder_name'] ?? ''
            );
            $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar_tarjeta', 'suscripciones', $id, null, ['last4' => $tokenData['last4']]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La suscripción no existe o ya ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'suscripciones', $id, $antes, ['eliminado' => true]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function calcularProximoCobro(string $fechaActual, int $meses, string $codigo = ''): string
    {
        $dt = new \DateTime($fechaActual);
        if ($codigo === 'DIARIO') {
            $dt->modify('+1 day');
        } elseif ($codigo === 'SEMANAL') {
            $dt->modify('+7 days');
        } elseif ($codigo === 'QUINCENAL') {
            $dt->modify('+15 days');
        } else {
            $dt->modify("+{$meses} months");
        }
        return $dt->format('Y-m-d');
    }

    /**
     * Extrae las filas de detalle del array $data enviado por el formulario.
     * El frontend envía: detalle[0][id_producto], detalle[0][descripcion], etc.
     */
    private function _extraerDetalle(array &$data): array
    {
        $detalle = $data['detalle'] ?? [];
        unset($data['detalle']);

        // Filtrar filas vacías (sin producto)
        return array_values(array_filter($detalle, fn($item) => !empty($item['id_producto'])));
    }

    /**
     * Extrae las filas de información adicional (concepto/detalle) del formulario
     * y las devuelve como JSON listo para la columna info_adicional (o null si vacío).
     * El frontend envía: info_adicional[0][concepto], info_adicional[0][detalle], ...
     */
    private function _extraerInfoAdicional(array &$data): ?string
    {
        $filas = $data['info_adicional'] ?? [];
        unset($data['info_adicional']);

        $limpias = [];
        foreach ((array) $filas as $fila) {
            $concepto = trim((string) ($fila['concepto'] ?? ''));
            $detalle  = trim((string) ($fila['detalle']  ?? ''));
            if ($concepto !== '' || $detalle !== '') {
                $limpias[] = ['concepto' => $concepto, 'detalle' => $detalle];
            }
        }

        return $limpias ? json_encode($limpias, JSON_UNESCAPED_UNICODE) : null;
    }
}
