<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AprobacionesRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Services\LogSistemaService;

/**
 * Motor de Aprobaciones — configuración por empresa.
 *
 * El catálogo de checkpoints (aprobaciones_tipos) lo define el desarrollo: cada
 * fila necesita que el Service del módulo dueño pregunte por ella antes de
 * ejecutar el proceso. El usuario decide, por empresa, cuáles activa, quién
 * aprueba y desde qué monto.
 *
 * Los módulos enganchados consultan `getConfigCheckpoint()` / `requiereAprobacion()`
 * / `esAprobador()`; el flujo de aprobación (estados, tokens, correos) sigue
 * viviendo en cada módulo.
 */
class AprobacionesService
{
    /** Códigos del catálogo, para que los módulos no escriban el string suelto. */
    public const CARGA_INVENTARIO = 'carga_inventario';
    public const IMPORTACIONES    = 'importaciones';
    public const PAGO_BANCARIO    = 'pago_bancario';
    public const COMPRAS          = 'aprobacion_compras';

    private AprobacionesRepository $repo;
    private LogSistemaService $log;
    private ?EmpresaRepository $empRepo = null;

    /** Cache por request: evita repetir la consulta cuando un flujo la pide varias veces. */
    private array $cacheConfig = [];

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

    // ─── Pantalla de configuración ──────────────────────────────────────────────

    /** Aprobaciones que la empresa configuró (listado del módulo). */
    public function getListado(int $idEmpresa): array
    {
        $rows = $this->repo->getConfiguradas($idEmpresa);
        foreach ($rows as &$r) {
            $r['usuarios_aprobadores'] = $this->decodeAprobadores($r['usuarios_aprobadores'] ?? '[]');
            $r['requiere_aprobacion']  = $this->esVerdadero($r['requiere_aprobacion'] ?? null);
        }
        return $rows;
    }

    /** Checkpoints que la empresa aún no configuró (select del modal "Nueva aprobación"). */
    public function getTiposDisponibles(int $idEmpresa): array
    {
        return $this->repo->getTiposDisponibles($idEmpresa);
    }

    /** Usuarios de la empresa (para elegir aprobadores en la config). */
    public function getUsuariosEmpresa(int $idEmpresa): array
    {
        return $this->empresaRepo()->getUsuariosAsignados($idEmpresa);
    }

    public function guardarConfig(int $idEmpresa, int $idTipo, array $data, int $idUsuario): void
    {
        $tipo = $this->repo->getTipoPorId($idTipo);
        if (!$tipo) {
            throw new \InvalidArgumentException('Proceso de aprobación no encontrado.');
        }
        if (!$this->esVerdadero($tipo['activo'] ?? null)) {
            throw new \InvalidArgumentException('Ese proceso de aprobación ya no está disponible.');
        }

        $aprobadores = $this->normalizarAprobadores($data['usuarios_aprobadores'] ?? [], $idEmpresa);
        if (empty($aprobadores)) {
            throw new \InvalidArgumentException('Agrega al menos un usuario aprobador de esta empresa.');
        }

        $umbral = $data['umbral_monto'] ?? '';
        if ($umbral !== '' && $umbral !== null && (float) $umbral < 0) {
            throw new \InvalidArgumentException('El monto mínimo no puede ser negativo.');
        }

        $antes = $this->repo->getConfigPorTipoId($idEmpresa, $idTipo);
        $this->repo->upsertConfig($idEmpresa, $idTipo, [
            'requiere_aprobacion'  => !empty($data['requiere_aprobacion']),
            'usuarios_aprobadores' => $aprobadores,
            'umbral_monto'         => $umbral,
        ], $idUsuario);

        $this->cacheConfig = [];
        $this->log->registrar(
            $idUsuario,
            $idEmpresa,
            $antes ? 'ACTUALIZAR_APROBACION' : 'CREAR_APROBACION',
            'aprobaciones_config',
            $idTipo,
            $antes,
            [
                'proceso'              => $tipo['codigo'],
                'requiere_aprobacion'  => !empty($data['requiere_aprobacion']),
                'usuarios_aprobadores' => $aprobadores,
                'umbral_monto'         => $umbral !== '' ? $umbral : null,
            ]
        );
    }

    /** Quita la aprobación del listado de la empresa (eliminación lógica). */
    public function eliminarConfig(int $idEmpresa, int $idTipo, int $idUsuario): void
    {
        $antes = $this->repo->getConfigPorTipoId($idEmpresa, $idTipo);
        if (!$antes) {
            throw new \InvalidArgumentException('Esa aprobación no está configurada en esta empresa.');
        }
        $this->repo->eliminarConfig($idEmpresa, $idTipo, $idUsuario);
        $this->cacheConfig = [];

        $tipo = $this->repo->getTipoPorId($idTipo);
        $this->log->registrar(
            $idUsuario,
            $idEmpresa,
            'ELIMINAR_APROBACION',
            'aprobaciones_config',
            $idTipo,
            $antes,
            ['proceso' => $tipo['codigo'] ?? null, 'eliminado' => true]
        );
    }

    // ─── Consulta desde los módulos enganchados ─────────────────────────────────

    /**
     * Config resuelta de un checkpoint para la empresa. Devuelve siempre la misma
     * forma, aunque el tipo no exista o la empresa no lo haya configurado:
     *   requiere    bool   ¿el proceso queda pendiente de aprobación?
     *   notificar   bool   siempre true — si se exige aprobación, se avisa al aprobador.
     *   aprobadores int[]  ids de usuario que pueden aprobar.
     *   umbral      ?float monto desde el cual se exige aprobación (null = siempre).
     */
    public function getConfigCheckpoint(string $codigoTipo, int $idEmpresa): array
    {
        $clave = $codigoTipo . '|' . $idEmpresa;
        if (isset($this->cacheConfig[$clave])) {
            return $this->cacheConfig[$clave];
        }

        $vacio = [
            'id_tipo'     => 0,
            'nombre'      => '',
            'requiere'    => false,
            'notificar'   => true,
            'aprobadores' => [],
            'umbral'      => null,
        ];

        $tipo = $this->repo->getTipoPorCodigo($codigoTipo);
        if (!$tipo) {
            return $this->cacheConfig[$clave] = $vacio;
        }
        $vacio['id_tipo'] = (int) $tipo['id'];
        $vacio['nombre']  = $tipo['nombre'];

        $cfg = $this->repo->getConfigPorTipoId($idEmpresa, (int) $tipo['id']);
        if (!$cfg) {
            return $this->cacheConfig[$clave] = $vacio;
        }

        return $this->cacheConfig[$clave] = [
            'id_tipo'     => (int) $tipo['id'],
            'nombre'      => $tipo['nombre'],
            'requiere'    => $this->esVerdadero($cfg['requiere_aprobacion'] ?? null),
            // Se retiró el switch "notificar por correo": si la aprobación está
            // activada, siempre se avisa a los aprobadores (activarla sin avisar
            // solo deja el documento trabado sin que nadie se entere).
            'notificar'   => true,
            'aprobadores' => $this->decodeAprobadores($cfg['usuarios_aprobadores'] ?? '[]'),
            'umbral'      => isset($cfg['umbral_monto']) && $cfg['umbral_monto'] !== null ? (float) $cfg['umbral_monto'] : null,
        ];
    }

    /**
     * Igual que getConfigCheckpoint(), pero con `requiere` ya resuelto contra el
     * monto del documento: si hay monto mínimo configurado y el documento no lo
     * alcanza, el proceso no pide aprobación. Es lo que consultan los módulos
     * en su punto de decisión.
     */
    public function getConfigResuelta(string $codigoTipo, int $idEmpresa, ?float $monto = null): array
    {
        $cfg = $this->getConfigCheckpoint($codigoTipo, $idEmpresa);
        $cfg['requiere'] = $this->requiereAprobacion($codigoTipo, $idEmpresa, $monto);
        return $cfg;
    }

    /** ¿El checkpoint exige aprobación en esta empresa? Con umbral, solo desde ese monto. */
    public function requiereAprobacion(string $codigoTipo, int $idEmpresa, ?float $monto = null): bool
    {
        $cfg = $this->getConfigCheckpoint($codigoTipo, $idEmpresa);
        if (!$cfg['requiere'] || empty($cfg['aprobadores'])) return false;
        if ($cfg['umbral'] !== null && $monto !== null && $monto < $cfg['umbral']) return false;
        return true;
    }

    /** ¿El usuario puede aprobar este checkpoint? (aprobador configurado o super admin). */
    public function esAprobador(string $codigoTipo, int $idEmpresa, int $idUsuario, int $nivel = 1): bool
    {
        if ($nivel >= 3) return true;
        $cfg = $this->getConfigCheckpoint($codigoTipo, $idEmpresa);
        return in_array($idUsuario, $cfg['aprobadores'], true);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /** PostgreSQL puede devolver el boolean como 'f'/'t' o como bool según el driver. */
    private function esVerdadero($valor): bool
    {
        return !empty($valor) && $valor !== 'f';
    }

    private function decodeAprobadores($json): array
    {
        $arr = is_array($json) ? $json : json_decode((string) $json, true);
        return is_array($arr) ? array_values(array_unique(array_map('intval', $arr))) : [];
    }

    /** Solo se aceptan como aprobadores usuarios realmente asignados a la empresa. */
    private function normalizarAprobadores(array $ids, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) return [];

        $validos = array_map(static fn($u) => (int) $u['id'], $this->getUsuariosEmpresa($idEmpresa));
        return array_values(array_intersect($ids, $validos));
    }
}
