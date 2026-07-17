<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AprobacionesRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Services\LogSistemaService;

/**
 * Configuración del motor de Aprobaciones: catálogo de checkpoints y, por
 * empresa, qué tipo exige aprobación y quiénes son los aprobadores.
 *
 * Expone `requiereAprobacion()` / `esAprobador()` para que un módulo futuro
 * pueda consultar la configuración antes de decidir su propio flujo de
 * aprobación (la bandeja/solicitudes se retiró; solo queda la configuración).
 */
class AprobacionesService
{
    private AprobacionesRepository $repo;
    private LogSistemaService $log;
    private ?EmpresaRepository $empRepo = null;

    public function __construct(
        ?AprobacionesRepository $repo = null,
        ?LogSistemaService $log = null
    ) {
        $this->repo = $repo ?? new AprobacionesRepository();
        $this->log  = $log  ?? new LogSistemaService();
    }

    private function empresaRepo(): EmpresaRepository
    {
        if ($this->empRepo === null) {
            $this->empRepo = new EmpresaRepository();
        }
        return $this->empRepo;
    }

    /** Catálogo de tipos con su config en la empresa activa (para la pantalla de configuración). */
    public function getConfigEmpresa(int $idEmpresa): array
    {
        $rows = $this->repo->getConfigEmpresa($idEmpresa);
        foreach ($rows as &$r) {
            $aprob = json_decode($r['usuarios_aprobadores'] ?? '[]', true);
            $r['usuarios_aprobadores'] = is_array($aprob) ? array_values(array_map('intval', $aprob)) : [];
            $r['requiere_aprobacion'] = !empty($r['requiere_aprobacion']) && $r['requiere_aprobacion'] !== 'f';
        }
        return $rows;
    }

    /** Usuarios de la empresa (para elegir aprobadores en la config). */
    public function getUsuariosEmpresa(int $idEmpresa): array
    {
        return $this->empresaRepo()->getUsuariosAsignados($idEmpresa);
    }

    public function guardarConfig(int $idEmpresa, int $idTipo, array $data, int $idUsuario): void
    {
        if (!$this->repo->getTipoPorId($idTipo)) {
            throw new \InvalidArgumentException('Tipo de aprobación no encontrado.');
        }
        $aprobadores = array_values(array_filter(array_map('intval', $data['usuarios_aprobadores'] ?? [])));
        if (!empty($data['requiere_aprobacion']) && empty($aprobadores)) {
            throw new \InvalidArgumentException('No se puede activar sin al menos un usuario aprobador agregado.');
        }
        $this->repo->upsertConfig($idEmpresa, $idTipo, $data, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'configurar', 'aprobaciones_config', $idTipo, null, $data);
    }

    /** Config resuelta y normalizada de un tipo (por código) para la empresa. Null si el tipo no existe. */
    private function configPorCodigo(string $codigoTipo, int $idEmpresa): ?array
    {
        $tipo = $this->repo->getTipoPorCodigo($codigoTipo);
        if (!$tipo) return null;
        $cfg = $this->repo->getConfigPorTipoId($idEmpresa, (int) $tipo['id']);

        $aprob = json_decode($cfg['usuarios_aprobadores'] ?? '[]', true);
        return [
            'id_tipo'     => (int) $tipo['id'],
            'nombre'      => $tipo['nombre'],
            'requiere'    => !empty($cfg['requiere_aprobacion']) && $cfg['requiere_aprobacion'] !== 'f',
            'aprobadores' => is_array($aprob) ? array_values(array_map('intval', $aprob)) : [],
            'umbral'      => isset($cfg['umbral_monto']) ? (float) $cfg['umbral_monto'] : null,
        ];
    }

    /** ¿El tipo exige aprobación en esta empresa? Si hay umbral configurado, solo por encima de él. */
    public function requiereAprobacion(string $codigoTipo, int $idEmpresa, ?float $monto = null): bool
    {
        $cfg = $this->configPorCodigo($codigoTipo, $idEmpresa);
        if (!$cfg || !$cfg['requiere']) return false;
        if ($cfg['umbral'] !== null && $monto !== null && $monto < $cfg['umbral']) return false;
        return true;
    }

    /** ¿El usuario puede aprobar solicitudes de este tipo? (aprobador configurado o super admin). */
    public function esAprobador(string $codigoTipo, int $idEmpresa, int $idUsuario, int $nivel = 1): bool
    {
        if ($nivel >= 3) return true;
        $cfg = $this->configPorCodigo($codigoTipo, $idEmpresa);
        return $cfg !== null && in_array($idUsuario, $cfg['aprobadores'], true);
    }
}
