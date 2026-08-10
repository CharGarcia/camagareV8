<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ReasignarEstablecimientoRepository;
use App\Services\LogSistemaService;
use Throwable;

/**
 * Reasigna el establecimiento (sucursal propia) de documentos ya registrados —típicamente
 * migrados/importados que quedaron en el establecimiento equivocado— sin cambiar su número.
 * Solo altera id_establecimiento del documento seleccionado; NO cascada a pagos, contabilidad,
 * retenciones de compra ni inventario (decisión de diseño). Todo en transacción + auditoría.
 */
class ReasignarEstablecimientoService
{
    private ReasignarEstablecimientoRepository $repo;
    private LogSistemaService $log;

    private const TABLA_LOG = [
        'compras'           => 'compras_cabecera',
        'retenciones_venta' => 'retencion_venta_cabecera',
    ];

    public function __construct(?ReasignarEstablecimientoRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo = $repo ?: new ReasignarEstablecimientoRepository();
        $this->log  = $log ?: new LogSistemaService();
        // La columna de retención de venta puede no existir aún: se asegura al instanciar el módulo.
        $this->repo->asegurarColumnaRetVenta();
    }

    public function repo(): ReasignarEstablecimientoRepository { return $this->repo; }

    /**
     * @return array{ok:bool, reasignados:int, mensaje:string}
     */
    public function reasignar(int $idEmpresa, string $tipo, array $ids, int $idEstDestino, int $idUsuario, ?int $idUsuarioFiltro): array
    {
        if (!in_array($tipo, ReasignarEstablecimientoRepository::tiposValidos(), true)) {
            return ['ok' => false, 'reasignados' => 0, 'mensaje' => 'Tipo de documento no válido.'];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) {
            return ['ok' => false, 'reasignados' => 0, 'mensaje' => 'No se seleccionó ningún documento.'];
        }
        if (!$this->repo->establecimientoValido($idEmpresa, $idEstDestino)) {
            return ['ok' => false, 'reasignados' => 0, 'mensaje' => 'El establecimiento destino no es válido para esta empresa.'];
        }

        $db = Database::getConnection();
        $tablaLog = self::TABLA_LOG[$tipo];
        try {
            $db->beginTransaction();
            // Estado previo (para auditoría por documento).
            $antes = $this->repo->establecimientoActualDe($idEmpresa, $tipo, $ids);
            $n = $this->repo->reasignar($idEmpresa, $tipo, $ids, $idEstDestino, $idUsuario, $idUsuarioFiltro);

            foreach ($antes as $idDoc => $estAnterior) {
                if ((int) $estAnterior === $idEstDestino) { continue; } // no cambió
                $this->log->registrar(
                    $idUsuario, $idEmpresa, 'reasignar_establecimiento', $tablaLog, (int) $idDoc,
                    ['id_establecimiento' => $estAnterior],
                    ['id_establecimiento' => $idEstDestino]
                );
            }
            $db->commit();
            return ['ok' => true, 'reasignados' => $n, 'mensaje' => "Se reasignaron {$n} documento(s) al establecimiento destino."];
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            return ['ok' => false, 'reasignados' => 0, 'mensaje' => 'Error al reasignar: ' . $e->getMessage()];
        }
    }
}
