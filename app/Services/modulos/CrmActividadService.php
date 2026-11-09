<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CrmActividadRepository;
use App\repositories\modulos\CrmCatalogoRepository;
use App\repositories\modulos\CrmContactoRepository;
use App\repositories\modulos\CrmOportunidadRepository;
use App\Rules\modulos\CrmActividadRules;
use App\Services\LogSistemaService;

/**
 * CRM: actividades de una oportunidad o contacto. Diseño: docs/diseno/crm-redes-fase1.md §5.7.
 * El controlador comprueba antes que el usuario puede ver la oportunidad/contacto.
 */
class CrmActividadService
{
    public function __construct(
        private CrmActividadRepository $repo,
        private CrmOportunidadRepository $oportunidades,
        private CrmContactoRepository $contactos,
        private CrmCatalogoRepository $catalogos,
        private CrmActividadRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(
            new CrmActividadRepository(), new CrmOportunidadRepository(), new CrmContactoRepository(),
            new CrmCatalogoRepository(), new CrmActividadRules(), new LogSistemaService()
        );
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getPorId($id, $idEmpresa);
    }

    public function getPorOportunidad(int $idOportunidad, int $idEmpresa): array
    {
        return $this->repo->getPorOportunidad($idOportunidad, $idEmpresa);
    }

    public function getPendientesUsuario(int $idEmpresa, int $idUsuario): array
    {
        return $this->repo->getPendientesUsuario($idEmpresa, $idUsuario);
    }

    public function contarVencidasUsuario(int $idEmpresa, int $idUsuario): int
    {
        return $this->repo->contarVencidasUsuario($idEmpresa, $idUsuario);
    }

    public function guardar(array $d, int $idEmpresa, int $idUsuario): int
    {
        $d = $this->normalizar($d, $idEmpresa, $idUsuario);
        $this->rules->validar($d);
        $id = (int) ($d['id'] ?? 0);

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            if ($id > 0) {
                $antes = $this->repo->getPorId($id, $idEmpresa);
                if (!$antes) {
                    throw new \RuntimeException('Actividad no encontrada.');
                }
                $this->repo->actualizar($id, $d, $idEmpresa, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_actividades', $id, $antes, $this->paraLog($d));
            } else {
                $id = $this->repo->insertar($d, $idEmpresa, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'crm_actividades', $id, null, $this->paraLog($d));
            }
            if ($managed) $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function completar(int $id, bool $completada, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $antes = $this->repo->getPorId($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Actividad no encontrada.');
            }
            $this->repo->marcarCompletada($id, $idEmpresa, $completada, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $completada ? 'COMPLETAR' : 'REABRIR', 'crm_actividades', $id,
                ['completada' => (bool) $antes['completada']], ['completada' => $completada]);
            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $antes = $this->repo->getPorId($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Actividad no encontrada.');
            }
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_actividades', $id, $antes, null);
            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function normalizar(array $d, int $idEmpresa, int $idUsuario): array
    {
        $d['tipo'] = (string) ($d['tipo'] ?? 'tarea');
        $d['titulo'] = trim((string) ($d['titulo'] ?? ''));
        $d['descripcion'] = trim((string) ($d['descripcion'] ?? '')) ?: null;
        $vence = trim((string) ($d['vence_at'] ?? ''));
        $d['vence_at'] = $vence !== '' ? (CrmActividadRules::normalizarFechaHora($vence) ?? $vence) : null;

        $idOp = (int) ($d['id_oportunidad'] ?? 0);
        if ($idOp > 0 && !$this->oportunidades->getPorId($idOp, $idEmpresa)) {
            throw new \InvalidArgumentException('Oportunidad no encontrada.');
        }
        $d['id_oportunidad'] = $idOp ?: null;

        $idCt = (int) ($d['id_contacto'] ?? 0);
        if ($idCt > 0 && !$this->contactos->getPorId($idCt, $idEmpresa)) {
            throw new \InvalidArgumentException('Contacto no encontrado.');
        }
        $d['id_contacto'] = $idCt ?: null;

        $resp = (int) ($d['id_responsable'] ?? 0);
        if ($resp > 0 && $resp !== $idUsuario && !$this->catalogos->usuarioPerteneceEmpresa($resp, $idEmpresa)) {
            throw new \InvalidArgumentException('El responsable seleccionado no pertenece a esta empresa.');
        }
        $d['id_responsable'] = $resp ?: $idUsuario;
        return $d;
    }

    private function paraLog(array $d): array
    {
        return array_intersect_key($d, array_flip(['tipo', 'titulo', 'vence_at', 'id_responsable', 'id_oportunidad', 'id_contacto']));
    }
}
