<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ProformaRepository;
use App\Rules\modulos\ProformaRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\core\Database;

class ProformaService
{
    private ProformaRepository $repository;
    private ProformaRules $rules;
    private LogSistemaService $log;

    public function __construct(
        ProformaRepository $repository,
        ProformaRules $rules,
        LogSistemaService $log
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->log        = $log;
    }

    /**
     * Crea una nueva proforma. Retorna el id creado.
     * @throws \RuntimeException si hay errores de validación o duplicado
     */
    public function crear(array $data): int
    {
        $errores = $this->rules->validar($data);
        if (!empty($errores)) {
            throw new \RuntimeException(implode(' | ', $errores));
        }

        $idEmpresa  = (int) $data['id_empresa'];
        $idUsuario  = (int) $data['id_usuario'];
        $idEstab    = (int) $data['id_establecimiento'];
        $idPunto    = (int) $data['id_punto_emision'];
        $secuencial = $data['secuencial'];

        if ($this->repository->existeSecuencial($idEmpresa, $idEstab, $idPunto, $secuencial)) {
            throw new \RuntimeException("El secuencial {$secuencial} ya está en uso.");
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $idProforma = $this->repository->insertCabecera($data);
            $this->guardarDetalles($idProforma, $data['detalles']);
            $this->guardarInfoAdicional($idProforma, $data['info_adicional'] ?? []);
            $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'crear',
                    'proformas_cabecera',
                    $idProforma,
                    null,
                    ['secuencial' => $secuencial, 'id_cliente' => $data['id_cliente']]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $idProforma;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza una proforma existente. Solo se puede editar si está en borrador.
     * @throws \RuntimeException
     */
    public function actualizar(int $id, array $data): int
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if (!in_array($proforma['estado'], ['borrador'], true)) {
            throw new \RuntimeException('Solo se pueden editar proformas en estado borrador.');
        }

        $errores = $this->rules->validar($data);
        if (!empty($errores)) {
            throw new \RuntimeException(implode(' | ', $errores));
        }

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        if ($this->repository->existeSecuencial(
            $idEmpresa,
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            $data['secuencial'],
            $id
        )) {
            throw new \RuntimeException("El secuencial {$data['secuencial']} ya está en uso.");
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->updateCabecera($id, $data);
            $this->repository->deleteDetalles($id);
            $this->guardarDetalles($id, $data['detalles']);
            $this->repository->deleteInfoAdicional($id);
            $this->guardarInfoAdicional($id, $data['info_adicional'] ?? []);
            $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'actualizar',
                    'proformas_cabecera',
                    $id,
                    $proforma,
                    ['secuencial' => $data['secuencial']]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el estado de una proforma.
     * Transiciones permitidas:
     *   borrador → aprobada | anulada
     *   aprobada → rechazada | anulada
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $idEmpresa, int $idUsuario): void
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }

        $estadoActual = $proforma['estado'];
        $permitidas = [
            'borrador' => ['aprobada', 'anulada'],
            'aprobada' => ['rechazada', 'anulada'],
        ];

        if (!isset($permitidas[$estadoActual]) || !in_array($nuevoEstado, $permitidas[$estadoActual], true)) {
            throw new \RuntimeException("No se puede cambiar de '{$estadoActual}' a '{$nuevoEstado}'.");
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->actualizarEstado($id, $nuevoEstado, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'cambiar_estado',
                'proformas_cabecera',
                $id,
                ['estado' => $estadoActual],
                ['estado' => $nuevoEstado]
            );
        } catch (\Throwable $e) { /* log falla silenciosamente */ }
    }

    /**
     * Elimina lógicamente una proforma (no permitido si está convertida).
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if ($proforma['estado'] === 'convertida') {
            throw new \RuntimeException('No se puede eliminar una proforma ya convertida a factura.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $ok = $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        if ($ok) {
            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'eliminar',
                    'proformas_cabecera',
                    $id,
                    $proforma,
                    null
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }
        }
        return $ok;
    }

    /**
     * Retorna los datos de una proforma formateados para pre-llenar el formulario de ventas.
     */
    public function getForConversion(int $id, int $idEmpresa): array
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if (!in_array($proforma['estado'], ['borrador', 'aprobada'], true)) {
            throw new \RuntimeException('Solo se pueden convertir proformas en borrador o aprobadas.');
        }

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$det) {
            $det['impuestos'] = $this->repository->getImpuestosDetalle((int) $det['id']);
        }
        unset($det);

        $adicional = $this->repository->getInfoAdicional($id);

        return [
            'proforma'       => $proforma,
            'detalles'       => $detalles,
            'info_adicional' => $adicional,
        ];
    }

    /**
     * Obtiene el siguiente secuencial para proformas en un punto de emisión.
     */
    public function getSiguienteSecuencial(int $idPunto): array
    {
        $secService = new SecuencialService();
        return $secService->obtenerSiguienteSecuencial($idPunto, 'Proformas');
    }

    private function guardarDetalles(int $idProforma, array $detalles): void
    {
        foreach ($detalles as $det) {
            $det['id_proforma'] = $idProforma;
            $idDetalle = $this->repository->insertDetalle($det);

            if (!empty($det['impuestos']) && is_array($det['impuestos'])) {
                foreach ($det['impuestos'] as $imp) {
                    if ((float) ($imp['valor'] ?? 0) == 0 && (float) ($imp['base_imponible'] ?? 0) == 0) continue;
                    $this->repository->insertImpuesto([
                        'id_proforma_detalle' => $idDetalle,
                        'codigo_impuesto'     => $imp['codigo_impuesto'] ?? '2',
                        'codigo_porcentaje'   => $imp['codigo_porcentaje'] ?? '2',
                        'tarifa'              => (float) ($imp['tarifa'] ?? 0),
                        'base_imponible'      => (float) ($imp['base_imponible'] ?? 0),
                        'valor'               => (float) ($imp['valor'] ?? 0),
                    ]);
                }
            }
        }
    }

    private function guardarInfoAdicional(int $idProforma, array $adicional): void
    {
        foreach ($adicional as $item) {
            $nombre = trim($item['nombre'] ?? '');
            $valor  = trim($item['valor'] ?? '');
            if ($nombre === '' || $valor === '') continue;
            $this->repository->insertInfoAdicional([
                'id_proforma' => $idProforma,
                'nombre'      => $nombre,
                'valor'       => $valor,
            ]);
        }
    }
}
