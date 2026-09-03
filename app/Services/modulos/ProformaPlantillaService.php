<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ProformaPlantillaRepository;
use App\Rules\modulos\ProformaRules;
use App\Services\LogSistemaService;
use App\core\Database;

class ProformaPlantillaService
{
    private ProformaPlantillaRepository $repository;
    private LogSistemaService $log;

    public function __construct(ProformaPlantillaRepository $repository, LogSistemaService $log)
    {
        $this->repository = $repository;
        $this->log        = $log;
    }

    public function listar(int $idEmpresa): array
    {
        return $this->repository->getListado($idEmpresa);
    }

    public function obtener(int $id, int $idEmpresa): ?array
    {
        $plantilla = $this->repository->getPorId($id, $idEmpresa);
        if (!$plantilla) return null;

        $plantilla['detalles']       = $this->repository->getDetalles($id);
        $plantilla['info_adicional'] = $this->repository->getInfoAdicional($id);
        return $plantilla;
    }

    /** Crea una plantilla a partir del detalle/adicional/vigencia actuales del modal. */
    public function crear(array $data): int
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \RuntimeException('El nombre de la plantilla es obligatorio.');
        }
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new \RuntimeException('La plantilla debe tener al menos un ítem en el detalle.');
        }

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $idPlantilla = $this->repository->insertCabecera([
                'id_empresa'      => $idEmpresa,
                'id_usuario'      => $idUsuario,
                'nombre'          => $nombre,
                'dias_vigencia'   => $data['dias_vigencia'] ?? 15,
                'vigencia_unidad' => $data['vigencia_unidad'] ?? 'dias',
                'condiciones_html' => ProformaRules::sanitizarCondiciones($data['condiciones_html'] ?? null),
            ]);

            foreach ($data['detalles'] as $det) {
                $det['id_plantilla'] = $idPlantilla;
                $this->repository->insertDetalle($det);
            }

            foreach ($data['info_adicional'] ?? [] as $item) {
                $itemNombre = trim($item['nombre'] ?? '');
                $itemValor  = trim($item['valor'] ?? '');
                if ($itemNombre === '' || $itemValor === '') continue;
                $this->repository->insertInfoAdicional([
                    'id_plantilla' => $idPlantilla,
                    'nombre'       => $itemNombre,
                    'valor'        => $itemValor,
                ]);
            }

            $db->commit();

            try {
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'proformas_plantillas', $idPlantilla, null, ['nombre' => $nombre]);
            } catch (\Throwable $e) { /* log no crítico */ }

            return $idPlantilla;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Actualiza una plantilla existente (reemplaza detalle/adicional, igual que Proforma::actualizar). */
    public function actualizar(int $id, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $plantilla = $this->repository->getPorId($id, $idEmpresa);
        if (!$plantilla) {
            throw new \RuntimeException('Plantilla no encontrada.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \RuntimeException('El nombre de la plantilla es obligatorio.');
        }
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new \RuntimeException('La plantilla debe tener al menos un ítem en el detalle.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->updateCabecera($id, [
                'id_usuario'      => $idUsuario,
                'nombre'          => $nombre,
                'dias_vigencia'   => $data['dias_vigencia'] ?? 15,
                'vigencia_unidad' => $data['vigencia_unidad'] ?? 'dias',
                'condiciones_html' => ProformaRules::sanitizarCondiciones($data['condiciones_html'] ?? null),
            ]);

            $this->repository->deleteDetalles($id);
            foreach ($data['detalles'] as $det) {
                $det['id_plantilla'] = $id;
                $this->repository->insertDetalle($det);
            }

            $this->repository->deleteInfoAdicional($id);
            foreach ($data['info_adicional'] ?? [] as $item) {
                $itemNombre = trim($item['nombre'] ?? '');
                $itemValor  = trim($item['valor'] ?? '');
                if ($itemNombre === '' || $itemValor === '') continue;
                $this->repository->insertInfoAdicional([
                    'id_plantilla' => $id,
                    'nombre'       => $itemNombre,
                    'valor'        => $itemValor,
                ]);
            }

            $db->commit();

            try {
                $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'proformas_plantillas', $id, ['nombre' => $plantilla['nombre']], ['nombre' => $nombre]);
            } catch (\Throwable $e) { /* log no crítico */ }
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $plantilla = $this->repository->getPorId($id, $idEmpresa);
        if (!$plantilla) {
            throw new \RuntimeException('Plantilla no encontrada.');
        }
        $this->repository->eliminar($id, $idEmpresa, $idUsuario);

        try {
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'proformas_plantillas', $id, ['nombre' => $plantilla['nombre']], null);
        } catch (\Throwable $e) { /* log no crítico */ }
    }
}
