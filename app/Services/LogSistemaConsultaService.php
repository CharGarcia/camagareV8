<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\LogSistemaRepository;

/**
 * Lógica de consulta (solo lectura) de la bitácora de auditoría.
 * Orquesta el repositorio y reutiliza LogSistemaService para el diff legible.
 */
class LogSistemaConsultaService
{
    private LogSistemaRepository $repo;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->repo = new LogSistemaRepository();
        $this->logService = new LogSistemaService();
    }

    /**
     * Listado paginado para la tabla.
     *
     * @param array{nivel:int,id_empresa:int} $scope
     * @return array{rows: array, total: int}
     */
    public function getListado(
        array $scope,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        array $filtros = []
    ): array {
        return $this->repo->getListado($scope, $buscar, $page, $perPage, $ordenCol, $ordenDir, $filtros);
    }

    /**
     * Opciones para los selects de la barra de filtros.
     * @param array{nivel:int,id_empresa:int} $scope
     */
    public function getOpcionesFiltros(array $scope): array
    {
        return $this->repo->getOpcionesFiltros($scope);
    }

    /**
     * Etiqueta amigable del módulo a partir del código opaco de filtro (para metadata).
     * @param array{nivel:int,id_empresa:int} $scope
     */
    public function etiquetaModulo(array $scope, string $codigo): string
    {
        $tabla = $this->repo->resolverTablaFiltro($scope, $codigo);
        if ($tabla === null || $tabla === '__sin_coincidencia__') {
            return '';
        }
        return \App\Helpers\AuditoriaEtiquetas::tabla($tabla);
    }

    /**
     * Filas para exportación (Excel/PDF), respetando alcance y filtros.
     *
     * @param array{nivel:int,id_empresa:int} $scope
     * @return array{rows: array, total: int, truncado: bool}
     */
    public function getParaExportar(array $scope, string $buscar, string $ordenCol, string $ordenDir, int $limit = 10000, array $filtros = []): array
    {
        return $this->repo->getParaExportar($scope, $buscar, $ordenCol, $ordenDir, $limit, $filtros);
    }

    /**
     * Detalle de un registro con el diff antes/después ya formateado.
     *
     * @param array{nivel:int,id_empresa:int} $scope
     * @return array|null  El registro con claves extra: 'cambios', 'antes_json', 'despues_json'.
     */
    public function getDetalle(int $id, array $scope): ?array
    {
        $row = $this->repo->getPorId($id, $scope);
        if ($row === null) {
            return null;
        }

        $antes   = !empty($row['datos_anteriores']) ? json_decode($row['datos_anteriores'], true) : null;
        $despues = !empty($row['datos_nuevos']) ? json_decode($row['datos_nuevos'], true) : null;

        $row['cambios']      = $this->logService->formatearCambios($antes, $despues);
        $row['antes_json']   = $antes  !== null ? json_encode($antes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;
        $row['despues_json'] = $despues !== null ? json_encode($despues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;

        return $row;
    }
}
