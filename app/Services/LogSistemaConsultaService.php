<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\AuditoriaEtiquetas;
use App\Helpers\DocumentoOrigenAsiento;
use App\repositories\LogSistemaRepository;
use App\repositories\LoginIntentoRepository;
use App\repositories\modulos\AsientoContableRepository;
use App\Services\modulos\DocumentoOrigenService;

/**
 * Lógica de consulta (solo lectura) de la bitácora de auditoría.
 * Orquesta el repositorio y reutiliza LogSistemaService para el diff legible.
 */
class LogSistemaConsultaService
{
    private LogSistemaRepository $repo;
    private LogSistemaService $logService;
    private LoginIntentoRepository $loginRepo;

    public function __construct()
    {
        $this->repo = new LogSistemaRepository();
        $this->logService = new LogSistemaService();
        $this->loginRepo = new LoginIntentoRepository();
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
     * Listado paginado de intentos de login (pestaña "Intentos de login").
     * Sin scope de empresa: la tabla es global (nivel 3 únicamente, se valida en el controller).
     *
     * @param array{exitoso:?bool,desde:?string,hasta:?string} $filtros
     * @return array{rows: array, total: int}
     */
    public function getListadoIntentos(string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, array $filtros = []): array
    {
        return $this->loginRepo->getListado($buscar, $page, $perPage, $ordenCol, $ordenDir, $filtros);
    }

    /**
     * Detalle de un registro con el diff antes/después ya formateado.
     *
     * @param array{nivel:int,id_empresa:int} $scope
     * @return array|null  El registro con claves extra: 'cambios' (solo lo que cambió), 'datos'
     *                     (todo lo que guardó el evento, legible), 'antes_json', 'despues_json',
     *                     'documento' (resumen: número, eliminado y tercero; null si el módulo no
     *                     es de documentos) y 'documento_detalle' (el documento completo, o null).
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
        $row['datos']        = $this->logService->formatearDatosCompletos($antes, $despues);
        $row['antes_json']   = $antes  !== null ? json_encode($antes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;
        $row['despues_json'] = $despues !== null ? json_encode($despues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;

        // Documento afectado: el número va en el encabezado del detalle; el documento
        // completo, en "Ver información en detalle".
        $tabla      = (string) ($row['tabla_afectada'] ?? '');
        $idRegistro = $row['id_registro'] !== null ? (int) $row['id_registro'] : null;
        $idEmpresa  = $row['id_empresa'] !== null ? (int) $row['id_empresa'] : null;

        $row['documento'] = $this->repo->getResumenDocumento($tabla, $idRegistro, $idEmpresa);
        $row['documento_detalle'] = ($row['documento'] !== null && $idRegistro && $idEmpresa)
            ? $this->getDocumentoDetalle($tabla, $idRegistro, $idEmpresa, $row['documento'])
            : null;

        return $row;
    }

    /**
     * Documento afectado tal como está hoy, para "Ver información en detalle". Facturas,
     * compras, egresos… salen de DocumentoOrigenService (el mismo modal "Documento origen" de
     * Mayores) y los asientos, con sus líneas contables; del resto de documentos solo se
     * conoce el resumen (número y tercero).
     *
     * @param array{numero:?string,tercero:?string,tercero_identificacion:?string} $resumen
     * @return array|null Misma forma que DocumentoOrigenService::getDetalle(), más 'campos'
     *                    opcional (datos extra de la cabecera, label/valor).
     */
    private function getDocumentoDetalle(string $tabla, int $idRegistro, int $idEmpresa, array $resumen): ?array
    {
        try {
            foreach (DocumentoOrigenAsiento::DOCUMENTOS as $modulo => $doc) {
                if ($doc['tabla'] === $tabla) {
                    return (new DocumentoOrigenService())->getDetalle((string) $modulo, $idRegistro, $idEmpresa);
                }
            }
            if ($tabla === 'asientos_contables_cabecera') {
                $asiento = $this->getAsientoDetalle($idRegistro, $idEmpresa);
                if ($asiento !== null) {
                    return $asiento;
                }
            }
        } catch (\Throwable $e) {
            // El documento ya no está o su tabla no existe en esta instalación: queda el resumen.
        }

        if ($resumen['numero'] === null && $resumen['tercero'] === null) {
            return null;
        }

        return [
            'etiqueta'               => AuditoriaEtiquetas::tabla($tabla),
            'numero'                 => (string) ($resumen['numero'] ?? ''),
            'fecha'                  => '',
            'estado'                 => null,
            'observaciones'          => null,
            'tercero'                => $resumen['tercero'],
            'tercero_identificacion' => $resumen['tercero_identificacion'],
            'totales'                => [],
            'columnas'               => [],
            'lineas'                 => [],
        ];
    }

    /** Asiento contable afectado con sus líneas, en la misma forma que DocumentoOrigenService. */
    private function getAsientoDetalle(int $idAsiento, int $idEmpresa): ?array
    {
        $asiento = (new AsientoContableRepository())->getDetalleAsiento($idAsiento, $idEmpresa);
        if (empty($asiento)) {
            return null;
        }

        $detalles  = $asiento['detalles'] ?? [];
        $conCentro = array_filter($detalles, fn($d) => trim((string) ($d['nombre_centro_costo'] ?? '')) !== '') !== [];
        $conProy   = array_filter($detalles, fn($d) => trim((string) ($d['nombre_proyecto'] ?? '')) !== '') !== [];

        $columnas = [['label' => 'Cuenta', 'numerica' => false], ['label' => 'Detalle', 'numerica' => false]];
        if ($conCentro) {
            $columnas[] = ['label' => 'Centro de costo', 'numerica' => false];
        }
        if ($conProy) {
            $columnas[] = ['label' => 'Proyecto', 'numerica' => false];
        }
        $columnas[] = ['label' => 'Debe', 'numerica' => true];
        $columnas[] = ['label' => 'Haber', 'numerica' => true];

        $lineas = [];
        foreach ($detalles as $d) {
            $glosa = array_filter(
                [trim((string) ($d['referencia_detalle'] ?? '')), trim((string) ($d['documento_referencia'] ?? ''))],
                fn($v) => $v !== ''
            );
            $fila = [
                trim(($d['codigo_cuenta'] ?? '') . ' - ' . ($d['nombre_cuenta'] ?? ''), ' -'),
                implode(' · ', $glosa),
            ];
            if ($conCentro) {
                $fila[] = (string) ($d['nombre_centro_costo'] ?? '');
            }
            if ($conProy) {
                $fila[] = (string) ($d['nombre_proyecto'] ?? '');
            }
            $fila[] = self::dinero($d['debe'] ?? null);
            $fila[] = self::dinero($d['haber'] ?? null);
            $lineas[] = $fila;
        }

        $campos = [];
        if (trim((string) ($asiento['concepto'] ?? '')) !== '') {
            $campos[] = ['label' => 'Concepto', 'valor' => (string) $asiento['concepto']];
        }
        $origen = trim((string) ($asiento['modulo_origen'] ?? ''));
        if ($origen !== '') {
            $campos[] = [
                'label' => 'Origen',
                'valor' => DocumentoOrigenAsiento::paraModulo($origen)['etiqueta'] ?? ucfirst(str_replace('_', ' ', $origen)),
            ];
        }

        $tipo  = trim((string) ($asiento['tipo_comprobante'] ?? ''));
        $fecha = strtotime((string) ($asiento['fecha_asiento'] ?? ''));

        return [
            'etiqueta'               => 'Asiento contable' . ($tipo !== '' ? ' (' . $tipo . ')' : ''),
            'numero'                 => (string) ($asiento['numero_comprobante'] ?? ''),
            'fecha'                  => $fecha ? date('d-m-Y', $fecha) : '',
            'estado'                 => ($asiento['estado'] ?? '') !== '' ? (string) $asiento['estado'] : null,
            'observaciones'          => trim((string) ($asiento['observaciones'] ?? '')) !== '' ? (string) $asiento['observaciones'] : null,
            'tercero'                => null,
            'tercero_identificacion' => null,
            'totales'                => [
                ['label' => 'Total debe', 'valor' => self::dinero($asiento['total_debe'] ?? null)],
                ['label' => 'Total haber', 'valor' => self::dinero($asiento['total_haber'] ?? null)],
            ],
            'columnas'               => $columnas,
            'lineas'                 => $lineas,
            'campos'                 => $campos,
        ];
    }

    private static function dinero($valor): string
    {
        return is_numeric($valor) ? number_format((float) $valor, 2, '.', ',') : (string) ($valor ?? '');
    }
}
