<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ConfiguracionRestauranteRepository;
use App\Rules\modulos\ConfiguracionRestauranteRules;
use App\Services\LogSistemaService;
use PDO;
use Throwable;

/**
 * Configuración del restaurante: estaciones de preparación y su impresora.
 *
 * La pieza que justifica el módulo es la **estación predeterminada**: cierra la
 * cascada con la que una línea de comanda resuelve a dónde va a prepararse
 * (ítem del Menú → categoría del ítem → categoría del producto → esta). Sin
 * ella, un local que trabaja directo con el stock general no manda nada a
 * cocina, porque sus líneas nacen sin estación.
 */
class ConfiguracionRestauranteService
{
    private ConfiguracionRestauranteRepository $repository;
    private ConfiguracionRestauranteRules $rules;
    private LogSistemaService $logService;
    private PDO $db;

    public function __construct(
        ?ConfiguracionRestauranteRepository $repository = null,
        ?ConfiguracionRestauranteRules $rules = null,
        ?LogSistemaService $logService = null
    ) {
        $this->repository = $repository ?? new ConfiguracionRestauranteRepository();
        $this->rules      = $rules ?? new ConfiguracionRestauranteRules();
        $this->logService = $logService ?? new LogSistemaService();
        $this->db         = Database::getConnection();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function getEstaciones(int $idEmpresa): array
    {
        return $this->repository->getEstaciones($idEmpresa);
    }

    // ─── Configuración general del salón ─────────────────────────────────────

    /**
     * Ancho del papel de la tirilla (58 u 80 mm). Lo leen las pantallas que
     * imprimen cuenta, factura o recibo para ajustar el tamaño de letra: el
     * tamaño de página lo sigue mandando el driver de la impresora.
     */
    public function getAnchoTirilla(int $idEmpresa): int
    {
        return $this->repository->getAnchoTirilla($idEmpresa);
    }

    public function guardarAnchoTirilla(int $idEmpresa, int $idUsuario, int $ancho): void
    {
        if (!in_array($ancho, [58, 80], true)) {
            throw new \Exception('El ancho del papel debe ser 58 u 80 mm.');
        }

        $antes = $this->repository->getAnchoTirilla($idEmpresa);

        $this->db->beginTransaction();
        try {
            $this->repository->guardarAnchoTirilla($idEmpresa, $ancho, $idUsuario);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CONFIGURAR_ANCHO_TIRILLA',
                'restaurante_config',
                null,
                ['ancho_papel_tirilla' => $antes],
                ['ancho_papel_tirilla' => $ancho]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getEstacion(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    public function crearEstacion(int $idEmpresa, int $idUsuario, array $data): int
    {
        $limpio = $this->rules->validarEstacion(
            $data,
            $this->repository->existeNombre($idEmpresa, trim((string) ($data['nombre'] ?? '')))
        );

        $this->db->beginTransaction();
        try {
            $id = $this->repository->crear($limpio + [
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
            ]);

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_ESTACION', 'estaciones_impresion', $id, null, $limpio);
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizarEstacion(int $id, int $idEmpresa, int $idUsuario, array $data): void
    {
        $antes = $this->repository->find($id, $idEmpresa);
        if (!$antes) {
            throw new \Exception('La estación no existe.');
        }

        $limpio = $this->rules->validarEstacion(
            $data,
            $this->repository->existeNombre($idEmpresa, trim((string) ($data['nombre'] ?? '')), $id)
        );

        $this->db->beginTransaction();
        try {
            $this->repository->actualizar($id, $idEmpresa, $limpio + ['id_usuario' => $idUsuario]);

            // La predeterminada se marca desde la estrella del listado, no desde
            // este modal. Lo único que se vigila aquí es que una estación que se
            // desactiva deje de serlo: si no, dejaría de recoger nada y nadie lo
            // notaría hasta que la cocina reclamara los pedidos que no le llegan.
            $eraPredeterminada = !empty($antes['es_predeterminada']) && !in_array($antes['es_predeterminada'], ['f', 'false', '0'], true);
            if ($eraPredeterminada && empty($limpio['activo'])) {
                $this->repository->fijarPredeterminada(0, $idEmpresa, $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_ESTACION', 'estaciones_impresion', $id, $antes, $limpio);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function eliminarEstacion(int $id, int $idEmpresa, int $idUsuario): void
    {
        $estacion = $this->repository->find($id, $idEmpresa);
        $this->rules->validarPuedeEliminar($estacion, $this->repository->contarUsos($id, $idEmpresa));

        $this->db->beginTransaction();
        try {
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_ESTACION', 'estaciones_impresion', $id, $estacion, ['eliminado' => true]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** @param int $idEstacion 0 = la empresa se queda sin estación predeterminada. */
    public function fijarPredeterminada(int $idEstacion, int $idEmpresa, int $idUsuario): void
    {
        $estacion = $idEstacion > 0 ? $this->repository->find($idEstacion, $idEmpresa) : null;
        $this->rules->validarPredeterminada($idEstacion, $estacion);

        $this->db->beginTransaction();
        try {
            $this->repository->fijarPredeterminada($idEstacion, $idEmpresa, $idUsuario);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'FIJAR_ESTACION_PREDETERMINADA',
                'estaciones_impresion',
                $idEstacion ?: null,
                null,
                ['id_estacion' => $idEstacion ?: 'ninguna']
            );
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ¿Este local trabaja con preparación? Lo decide que haya ítems de la carta
     * o categorías enrutados a una estación activa — algo que mandar a cocina—,
     * no la simple existencia de estaciones. Si no hay nada así, la comanda
     * esconde ese flujo: el pedido se toma y se cobra, y lo que se imprime sale
     * por la estación predeterminada.
     */
    public function usaPreparacion(int $idEmpresa): bool
    {
        return $this->repository->tienePreparacionConfigurada($idEmpresa);
    }

    /**
     * Último eslabón de la cascada de estación. Lo consulta ComandaService al
     * agregar una línea que no resolvió estación por el Menú ni por la
     * categoría del producto.
     */
    public function getEstacionPredeterminada(int $idEmpresa): ?int
    {
        return $this->repository->getPredeterminada($idEmpresa);
    }
}
