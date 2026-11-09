<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CrmCatalogoRepository;
use App\repositories\modulos\CrmEmbudoRepository;
use App\Rules\modulos\CrmEmbudoRules;
use App\Services\LogSistemaService;

/**
 * CRM: configuración por empresa — embudos, etapas, orígenes y motivos de
 * pérdida — y la siembra inicial al abrir el CRM por primera vez.
 * Diseño: docs/diseno/crm-redes-fase1.md §5.5 y §9.3.
 */
class CrmConfiguracionService
{
    /** Embudo que se siembra la primera vez: [nombre, probabilidad, tipo, color]. */
    private const ETAPAS_VENTAS = [
        ['Nuevo',       10, 'abierta', '#6c757d'],
        ['Contactado',  20, 'abierta', '#0dcaf0'],
        ['Calificado',  40, 'abierta', '#0d6efd'],
        ['Propuesta',   60, 'abierta', '#6f42c1'],
        ['Negociación', 80, 'abierta', '#fd7e14'],
        ['Ganado',     100, 'ganada',  '#198754'],
        ['Perdido',      0, 'perdida', '#dc3545'],
    ];

    /** Etapas con que nace un embudo creado a mano. */
    private const ETAPAS_NUEVO_EMBUDO = [
        ['Nuevo',      10, 'abierta', '#6c757d'],
        ['Calificado', 40, 'abierta', '#0d6efd'],
        ['Propuesta',  60, 'abierta', '#6f42c1'],
        ['Ganado',    100, 'ganada',  '#198754'],
        ['Perdido',     0, 'perdida', '#dc3545'],
    ];

    private const ORIGENES = [
        ['Instagram (mensaje)',    'instagram_dm'],
        ['Instagram (comentario)', 'instagram_comentario'],
        ['LinkedIn',               'linkedin'],
        ['WhatsApp',               'whatsapp'],
        ['Referido',               'referido'],
        ['Sitio web',              'web'],
        ['Otro',                   'otro'],
    ];

    private const MOTIVOS = [
        'Precio', 'Eligió a la competencia', 'Sin presupuesto', 'No respondió', 'No era el momento', 'Otro',
    ];

    public function __construct(
        private CrmEmbudoRepository $embudos,
        private CrmCatalogoRepository $catalogos,
        private CrmEmbudoRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CrmEmbudoRepository(), new CrmCatalogoRepository(), new CrmEmbudoRules(), new LogSistemaService());
    }

    public function disponible(): bool
    {
        return $this->embudos->disponible();
    }

    /**
     * Siembra el embudo «Ventas», los orígenes y los motivos de pérdida si la
     * empresa todavía no tiene ningún embudo. Idempotente y segura ante dos
     * peticiones simultáneas (candado por empresa). Devuelve true si sembró.
     */
    public function asegurarInicial(int $idEmpresa, int $idUsuario): bool
    {
        if (!$this->disponible() || $this->embudos->contarEmbudos($idEmpresa) > 0) {
            return false;
        }
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $this->embudos->lockInicializacion($idEmpresa);
            if ($this->embudos->contarEmbudos($idEmpresa) > 0) {   // otra petición ganó la carrera
                if ($managed) $db->commit();
                return false;
            }

            $idEmbudo = $this->embudos->insertarEmbudo($idEmpresa, 'Ventas', true, 10, $idUsuario);
            $this->sembrarEtapas($idEmpresa, $idEmbudo, self::ETAPAS_VENTAS, $idUsuario);

            if (!$this->catalogos->listar('origenes', $idEmpresa, false)) {
                foreach (self::ORIGENES as [$nombre, $canal]) {
                    $this->catalogos->insertar('origenes', $idEmpresa, $nombre, $canal, $idUsuario);
                }
            }
            if (!$this->catalogos->listar('motivos', $idEmpresa, false)) {
                foreach (self::MOTIVOS as $nombre) {
                    $this->catalogos->insertar('motivos', $idEmpresa, $nombre, null, $idUsuario);
                }
            }

            $this->log->registrar($idUsuario, $idEmpresa, 'CRM_INICIALIZAR', 'crm_embudos', $idEmbudo, null,
                ['embudo' => 'Ventas', 'etapas' => count(self::ETAPAS_VENTAS)]);
            if ($managed) $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function sembrarEtapas(int $idEmpresa, int $idEmbudo, array $etapas, int $idUsuario): void
    {
        $orden = 0;
        foreach ($etapas as [$nombre, $prob, $tipo, $color]) {
            $orden += 10;
            $this->embudos->insertarEtapa($idEmpresa, $idEmbudo, [
                'nombre' => $nombre, 'orden' => $orden, 'probabilidad' => $prob, 'tipo' => $tipo, 'color' => $color,
            ], $idUsuario);
        }
    }

    // ── Lectura ──────────────────────────────────────────────────────────────

    /** Embudos con sus etapas, para el selector del CRM y la pantalla de configuración. */
    public function getEmbudosConEtapas(int $idEmpresa, bool $soloActivos = true): array
    {
        $out = [];
        foreach ($this->embudos->getEmbudos($idEmpresa, $soloActivos) as $e) {
            $e['etapas'] = $this->embudos->getEtapas((int) $e['id'], $idEmpresa);
            $out[] = $e;
        }
        return $out;
    }

    public function getCatalogos(int $idEmpresa, bool $soloActivos = true): array
    {
        return [
            'origenes'     => $this->catalogos->listar('origenes', $idEmpresa, $soloActivos),
            'motivos'      => $this->catalogos->listar('motivos', $idEmpresa, $soloActivos),
            'usuarios'     => $this->catalogos->usuariosEmpresa($idEmpresa),
            'vendedores'   => $this->catalogos->vendedores($idEmpresa),
        ];
    }

    // ── Embudos ──────────────────────────────────────────────────────────────

    public function guardarEmbudo(array $d, int $idEmpresa, int $idUsuario): int
    {
        $this->rules->validarEmbudo($d);
        $id = (int) ($d['id'] ?? 0);
        $nombre = trim((string) $d['nombre']);
        $orden = (int) ($d['orden'] ?? 0);
        $estado = (string) ($d['estado'] ?? 'activo');

        return $this->enTransaccion(function () use ($id, $nombre, $orden, $estado, $d, $idEmpresa, $idUsuario) {
            if ($id > 0) {
                $antes = $this->embudos->getEmbudo($id, $idEmpresa);
                if (!$antes) {
                    throw new \RuntimeException('Embudo no encontrado.');
                }
                $this->rules->validarCambioEstadoEmbudo($antes, $estado);
                $this->embudos->actualizarEmbudo($id, $idEmpresa, $nombre, $orden, $estado, $idUsuario);
                if (!empty($d['es_defecto']) && empty($antes['es_defecto'])) {
                    if ($estado !== 'activo') {
                        throw new \InvalidArgumentException('Un embudo inactivo no puede ser el predeterminado.');
                    }
                    $this->embudos->marcarDefecto($id, $idEmpresa, $idUsuario);
                }
                $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_embudos', $id, $antes,
                    $this->embudos->getEmbudo($id, $idEmpresa));
                return $id;
            }

            $nuevo = $this->embudos->insertarEmbudo($idEmpresa, $nombre, false, $orden, $idUsuario);
            $this->sembrarEtapas($idEmpresa, $nuevo, self::ETAPAS_NUEVO_EMBUDO, $idUsuario);
            if (!empty($d['es_defecto'])) {
                $this->embudos->marcarDefecto($nuevo, $idEmpresa, $idUsuario);
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'crm_embudos', $nuevo, null, ['nombre' => $nombre]);
            return $nuevo;
        });
    }

    public function eliminarEmbudo(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario) {
            $antes = $this->embudos->getEmbudo($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Embudo no encontrado.');
            }
            $this->rules->validarEliminarEmbudo($antes, $this->embudos->contarOportunidades($idEmpresa, $id));
            $this->embudos->eliminarEmbudo($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_embudos', $id, $antes, null);
        });
    }

    // ── Etapas ───────────────────────────────────────────────────────────────

    public function guardarEtapa(array $d, int $idEmpresa, int $idUsuario): int
    {
        $id = (int) ($d['id'] ?? 0);
        return $this->enTransaccion(function () use ($id, $d, $idEmpresa, $idUsuario) {
            if ($id > 0) {
                $antes = $this->embudos->getEtapa($id, $idEmpresa);
                if (!$antes) {
                    throw new \RuntimeException('Etapa no encontrada.');
                }
                $d['tipo'] = $antes['tipo'];   // el tipo no cambia
                $d['orden'] = $d['orden'] ?? $antes['orden'];
                // Ganado siempre 100 % y Perdido 0 %: es lo que significan.
                if ($antes['tipo'] === 'ganada')  $d['probabilidad'] = 100;
                if ($antes['tipo'] === 'perdida') $d['probabilidad'] = 0;
                $this->rules->validarEtapa($d);
                $d['nombre'] = trim((string) $d['nombre']);
                $this->embudos->actualizarEtapa($id, $idEmpresa, $d, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_etapas', $id, $antes, $d);
                return $id;
            }

            $idEmbudo = (int) ($d['id_embudo'] ?? 0);
            if (!$this->embudos->getEmbudo($idEmbudo, $idEmpresa)) {
                throw new \RuntimeException('Embudo no encontrado.');
            }
            $d['tipo'] = (string) ($d['tipo'] ?? 'abierta');   // Rules rechaza ganada/perdida
            $this->rules->validarCrearEtapa($d);
            $d['nombre'] = trim((string) $d['nombre']);
            $d['orden'] = $this->embudos->maxOrdenEtapa($idEmbudo, $idEmpresa) + 10;
            $nuevo = $this->embudos->insertarEtapa($idEmpresa, $idEmbudo, $d, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'crm_etapas', $nuevo, null, $d);
            return $nuevo;
        });
    }

    public function reordenarEtapas(int $idEmbudo, array $ids, int $idEmpresa, int $idUsuario): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $this->enTransaccion(function () use ($idEmbudo, $ids, $idEmpresa, $idUsuario) {
            if (!$this->embudos->getEmbudo($idEmbudo, $idEmpresa)) {
                throw new \RuntimeException('Embudo no encontrado.');
            }
            $this->embudos->reordenarEtapas($idEmbudo, $idEmpresa, $ids, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'REORDENAR', 'crm_etapas', $idEmbudo, null, ['orden' => $ids]);
        });
    }

    public function eliminarEtapa(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario) {
            $etapa = $this->embudos->getEtapa($id, $idEmpresa);
            if (!$etapa) {
                throw new \RuntimeException('Etapa no encontrada.');
            }
            $abiertas = array_filter(
                $this->embudos->getEtapas((int) $etapa['id_embudo'], $idEmpresa),
                fn($e) => $e['tipo'] === 'abierta' && (int) $e['id'] !== $id
            );
            $this->rules->validarEliminarEtapa($etapa, $this->embudos->contarOportunidades($idEmpresa, null, $id), count($abiertas));
            $this->embudos->eliminarEtapa($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_etapas', $id, $etapa, null);
        });
    }

    // ── Orígenes y motivos de pérdida ───────────────────────────────────────

    public function guardarCatalogo(string $catalogo, array $d, int $idEmpresa, int $idUsuario): int
    {
        $this->rules->validarCatalogo($catalogo, $d);
        $id = (int) ($d['id'] ?? 0);
        $nombre = trim((string) $d['nombre']);
        $tabla = $catalogo === 'origenes' ? 'crm_origenes' : 'crm_motivos_perdida';
        if ($this->catalogos->existeNombre($catalogo, $idEmpresa, $nombre, $id ?: null)) {
            throw new \InvalidArgumentException("Ya existe «{$nombre}».");
        }
        return $this->enTransaccion(function () use ($catalogo, $id, $nombre, $d, $tabla, $idEmpresa, $idUsuario) {
            if ($id > 0) {
                $antes = $this->catalogos->obtener($catalogo, $id, $idEmpresa);
                if (!$antes) {
                    throw new \RuntimeException('Registro no encontrado.');
                }
                $this->catalogos->actualizar($catalogo, $id, $idEmpresa, $nombre, (string) ($d['estado'] ?? 'activo'), $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', $tabla, $id, $antes,
                    ['nombre' => $nombre, 'estado' => $d['estado'] ?? 'activo']);
                return $id;
            }
            $nuevo = $this->catalogos->insertar($catalogo, $idEmpresa, $nombre, ($d['tipo_canal'] ?? '') ?: null, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', $tabla, $nuevo, null, ['nombre' => $nombre]);
            return $nuevo;
        });
    }

    /** Eliminación lógica: las oportunidades que lo usan conservan el id y siguen mostrando el nombre. */
    public function eliminarCatalogo(string $catalogo, int $id, int $idEmpresa, int $idUsuario): void
    {
        $tabla = $catalogo === 'origenes' ? 'crm_origenes' : 'crm_motivos_perdida';
        $this->enTransaccion(function () use ($catalogo, $id, $tabla, $idEmpresa, $idUsuario) {
            $antes = $this->catalogos->obtener($catalogo, $id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Registro no encontrado.');
            }
            $this->catalogos->eliminar($catalogo, $id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', $tabla, $id, $antes, null);
        });
    }

    private function enTransaccion(callable $fn)
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $r = $fn();
            if ($managed) $db->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
