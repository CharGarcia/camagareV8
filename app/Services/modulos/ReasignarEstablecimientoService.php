<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\AsientoContableRepository;
use App\repositories\modulos\ComprasRepository;
use App\repositories\modulos\EgresoRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\ReasignarEstablecimientoRepository;
use App\repositories\modulos\RetencionCompraRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Rules\modulos\EgresoRules;
use App\Services\LogSistemaService;
use Throwable;

/**
 * Reasigna el establecimiento de documentos ya registrados —típicamente migrados/importados que
 * quedaron en el establecimiento equivocado— sin cambiar su número. El destino puede ser un
 * establecimiento de la MISMA empresa o de OTRA empresa del mismo grupo RUC (varias filas de
 * `empresas` con el mismo RUC — ver EmpresaRepository::getIdsEmpresaMismoRuc()); en ese segundo
 * caso también se mueve id_empresa del documento.
 *
 * Solo se reasigna sin más trámite si el documento NO tiene contabilidad, pagos ni inventario
 * generados. Si los tiene, requiere confirmación explícita ($anularVinculos) y en ese caso se
 * anulan/revierten (reusando los mismos anular() de cada módulo, nunca lógica propia) ANTES de
 * reasignar, todo en una sola transacción por documento — nunca se regeneran automáticamente en
 * la empresa destino (otro plan de cuentas, otra bodega): el usuario los vuelve a generar allá.
 * La retención de compra generada NUNCA se anula automáticamente aquí (es un documento fiscal
 * propio, reportable en el F103): esos documentos quedan siempre bloqueados hasta que el usuario
 * la anule desde su propio módulo.
 */
class ReasignarEstablecimientoService
{
    private ReasignarEstablecimientoRepository $repo;
    private EmpresaRepository $empresaRepo;
    private LogSistemaService $log;

    private ?ComprasRepository $comprasRepo = null;
    private ?RetencionCompraRepository $retencionCompraRepo = null;
    private ?AsientoContableRepository $asientoRepo = null;
    private ?EgresoService $egresoService = null;
    private ?InventarioService $inventarioService = null;
    private ?AsientoContableService $asientoService = null;

    private const TABLA_LOG = [
        'compras'           => 'compras_cabecera',
        'retenciones_venta' => 'retencion_venta_cabecera',
    ];

    /** modulo_origen usado al persistir el asiento contable de cada tipo (ver getAsientoPorOrigen). */
    private const MODULO_ORIGEN_ASIENTO = [
        'compras'           => 'compra',
        'retenciones_venta' => 'retencion_venta',
    ];

    public function __construct(?ReasignarEstablecimientoRepository $repo = null, ?LogSistemaService $log = null, ?EmpresaRepository $empresaRepo = null)
    {
        $this->repo = $repo ?: new ReasignarEstablecimientoRepository();
        $this->log  = $log ?: new LogSistemaService();
        $this->empresaRepo = $empresaRepo ?: new EmpresaRepository();
        // La columna de retención de venta puede no existir aún: se asegura al instanciar el módulo.
        $this->repo->asegurarColumnaRetVenta();
    }

    public function repo(): ReasignarEstablecimientoRepository { return $this->repo; }

    /** Establecimientos de TODAS las empresas del grupo RUC de $idEmpresa (para el selector destino). */
    public function establecimientosGrupoRuc(int $idEmpresa): array
    {
        $idsGrupo = $this->empresaRepo->getIdsEmpresaMismoRuc($idEmpresa);
        return $this->repo->establecimientosGrupoRuc($idsGrupo);
    }

    private function comprasRepo(): ComprasRepository { return $this->comprasRepo ??= new ComprasRepository(); }
    private function retencionCompraRepo(): RetencionCompraRepository { return $this->retencionCompraRepo ??= new RetencionCompraRepository(); }
    private function asientoRepo(): AsientoContableRepository { return $this->asientoRepo ??= new AsientoContableRepository(); }

    private function egresoService(): EgresoService
    {
        return $this->egresoService ??= new EgresoService(new EgresoRepository(), new EgresoRules(), $this->log);
    }

    private function inventarioService(): InventarioService
    {
        return $this->inventarioService ??= new InventarioService(new InventarioRepository(), $this->log);
    }

    private function asientoService(): AsientoContableService
    {
        return $this->asientoService ??= new AsientoContableService($this->asientoRepo(), new AsientoContableRules(), $this->log);
    }

    /**
     * Vínculos de UN documento con otros módulos. `bloqueado_retencion` (solo compras) nunca se
     * auto-anula; los demás sí, si el llamador confirma $anularVinculos.
     *
     * @return array{id_asiento:?int, egresos:int[], detalle_ids_inventario:int[], bloqueado_retencion:bool}
     */
    private function vinculosDeUnDocumento(int $idEmpresa, string $tipo, int $id): array
    {
        $moduloAsiento = self::MODULO_ORIGEN_ASIENTO[$tipo] ?? null;
        $asiento   = $moduloAsiento ? $this->asientoRepo()->getAsientoPorOrigen($moduloAsiento, $id, $idEmpresa) : null;
        $idAsiento = ($asiento && ($asiento['estado'] ?? '') !== 'anulado') ? (int) $asiento['id'] : null;

        $egresos = [];
        $detalleIdsInventario = [];
        $bloqueadoRetencion = false;

        if ($tipo === 'compras') {
            $egresos = $this->comprasRepo()->getEgresosAsociados($id, $idEmpresa);
            $detalleIdsInventario = $this->repo->detalleIdsConInventario($id, $idEmpresa);
            $bloqueadoRetencion = $this->retencionCompraRepo()->existeRetencionParaCompra($id, $idEmpresa);
        }

        return [
            'id_asiento'             => $idAsiento,
            'egresos'                => $egresos,
            'detalle_ids_inventario' => $detalleIdsInventario,
            'bloqueado_retencion'    => $bloqueadoRetencion,
        ];
    }

    /**
     * Resumen de vínculos de un lote de documentos, para el aviso previo (antes de reasignar).
     *
     * @return array{con_asiento:int, con_egresos:int, con_inventario:int, bloqueados_retencion:int[]}
     */
    public function verificarVinculos(int $idEmpresa, string $tipo, array $ids): array
    {
        if (!in_array($tipo, ReasignarEstablecimientoRepository::tiposValidos(), true)) {
            return ['con_asiento' => 0, 'con_egresos' => 0, 'con_inventario' => 0, 'bloqueados_retencion' => []];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));

        $out = ['con_asiento' => 0, 'con_egresos' => 0, 'con_inventario' => 0, 'bloqueados_retencion' => []];
        foreach ($ids as $id) {
            $v = $this->vinculosDeUnDocumento($idEmpresa, $tipo, $id);
            if ($v['id_asiento'] !== null) { $out['con_asiento']++; }
            if (!empty($v['egresos'])) { $out['con_egresos']++; }
            if (!empty($v['detalle_ids_inventario'])) { $out['con_inventario']++; }
            if ($v['bloqueado_retencion']) { $out['bloqueados_retencion'][] = $id; }
        }
        return $out;
    }

    /**
     * @param bool $anularVinculos Si true, anula/revierte contabilidad+pagos+inventario del
     *             documento (reusando los anular() de cada módulo) antes de reasignar. Si false,
     *             los documentos con vínculos se omiten (se listan en 'omitidos_con_vinculos').
     *             La retención de compra NUNCA se anula aquí; esos documentos van siempre a
     *             'bloqueados_retencion', ignorando este flag.
     * @return array{ok:bool, reasignados:int, bloqueados_retencion:int[], bloqueados_colision:int[], omitidos_con_vinculos:int[], anulados:array, errores:array, mensaje:string}
     */
    public function reasignar(int $idEmpresa, string $tipo, array $ids, int $idEstDestino, int $idUsuario, ?int $idUsuarioFiltro, bool $anularVinculos = false): array
    {
        if (!in_array($tipo, ReasignarEstablecimientoRepository::tiposValidos(), true)) {
            return ['ok' => false, 'reasignados' => 0, 'mensaje' => 'Tipo de documento no válido.'];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) {
            return ['ok' => false, 'reasignados' => 0, 'mensaje' => 'No se seleccionó ningún documento.'];
        }

        // El destino puede ser un establecimiento de OTRA empresa del mismo grupo RUC, no solo
        // de la empresa activa — ver EmpresaRepository::getIdsEmpresaMismoRuc().
        $idsGrupo = $this->empresaRepo->getIdsEmpresaMismoRuc($idEmpresa);
        $idEmpresaDestino = $this->repo->establecimientoValidoGrupo($idsGrupo, $idEstDestino);
        if ($idEmpresaDestino === null) {
            return ['ok' => false, 'reasignados' => 0, 'mensaje' => 'El establecimiento destino no es válido para este grupo RUC.'];
        }
        $cambiaEmpresa = $idEmpresaDestino !== $idEmpresa;

        $db = Database::getConnection();
        $tablaLog = self::TABLA_LOG[$tipo];

        $reasignados = 0;
        $bloqueadosRetencion = [];
        $bloqueadosColision = [];
        $omitidosConVinculos = [];
        $anulados = ['asientos' => 0, 'egresos' => 0, 'inventario' => 0];
        $errores = [];

        $antes = $this->repo->establecimientoActualDe($idEmpresa, $tipo, $ids);

        foreach ($ids as $id) {
            $estAnterior = $antes[$id] ?? null;
            if (!$cambiaEmpresa && $estAnterior === $idEstDestino) { continue; } // ya está en el destino

            // Al cambiar de empresa, un documento con numeración propia (retención de venta) no
            // se puede mover si esa empresa destino ya usó ese mismo establecimiento-punto-secuencial.
            // Compras no aplica: su "número" es del proveedor, no depende de a qué empresa propia
            // esté atribuido el documento.
            if ($cambiaEmpresa) {
                $num = $this->repo->numeroPropioDe($tipo, $idEmpresa, $id);
                if ($num && $this->repo->existeNumeroEnEmpresa($tipo, $idEmpresaDestino, $num['establecimiento'], $num['punto_emision'], $num['secuencial'])) {
                    $bloqueadosColision[] = $id;
                    continue;
                }
            }

            $vinc = $this->vinculosDeUnDocumento($idEmpresa, $tipo, $id);
            if ($vinc['bloqueado_retencion']) {
                $bloqueadosRetencion[] = $id;
                continue;
            }

            $tieneVinculos = $vinc['id_asiento'] !== null || !empty($vinc['egresos']) || !empty($vinc['detalle_ids_inventario']);
            if ($tieneVinculos && !$anularVinculos) {
                $omitidosConVinculos[] = $id;
                continue;
            }

            try {
                $db->beginTransaction();

                if ($tieneVinculos) {
                    foreach ($vinc['egresos'] as $idEgreso) {
                        $this->egresoService()->anular($idEgreso, $idEmpresa, $idUsuario);
                        $anulados['egresos']++;
                    }
                    foreach ($vinc['detalle_ids_inventario'] as $idDetalle) {
                        // Mismo criterio que ComprasController::eliminarAjax(): referencia_tipo='compra',
                        // referencia_id=id de la línea (compras_detalle), no de la cabecera.
                        $this->inventarioService()->revertirMovimientosPorReferencia('compra', $idDetalle, $idEmpresa, $idUsuario, true);
                        $anulados['inventario']++;
                    }
                    if ($vinc['id_asiento'] !== null) {
                        $this->asientoService()->anular($vinc['id_asiento'], $idEmpresa, $idUsuario);
                        $anulados['asientos']++;
                    }
                }

                $n = $this->repo->reasignar($idEmpresa, $idEmpresaDestino, $tipo, [$id], $idEstDestino, $idUsuario, $idUsuarioFiltro);

                if ($n > 0) {
                    $this->log->registrar(
                        $idUsuario, $idEmpresa, 'reasignar_establecimiento', $tablaLog, $id,
                        ['id_establecimiento' => $estAnterior, 'id_empresa' => $idEmpresa],
                        ['id_establecimiento' => $idEstDestino, 'id_empresa' => $idEmpresaDestino]
                    );
                    $db->commit();
                    $reasignados++;
                } else {
                    // Sin permiso (registros propios) o ya no cumple el filtro: deshace lo anulado.
                    $db->rollBack();
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) { $db->rollBack(); }
                $errores[] = ['id' => $id, 'mensaje' => $e->getMessage()];
            }
        }

        $mensaje = "Se reasignaron {$reasignados} documento(s) al establecimiento destino.";
        if ($bloqueadosRetencion) { $mensaje .= ' ' . count($bloqueadosRetencion) . ' bloqueado(s) por tener retención de compra asociada.'; }
        if ($bloqueadosColision) { $mensaje .= ' ' . count($bloqueadosColision) . ' bloqueado(s) porque la empresa destino ya tiene un documento con ese mismo número.'; }
        if ($omitidosConVinculos) { $mensaje .= ' ' . count($omitidosConVinculos) . ' omitido(s) por tener contabilidad/pagos/inventario (confirme "Anular y reasignar").'; }
        if ($errores) { $mensaje .= ' ' . count($errores) . ' con error.'; }

        return [
            'ok'                    => true,
            'reasignados'           => $reasignados,
            'bloqueados_retencion'  => $bloqueadosRetencion,
            'bloqueados_colision'   => $bloqueadosColision,
            'omitidos_con_vinculos' => $omitidosConVinculos,
            'anulados'              => $anulados,
            'errores'               => $errores,
            'mensaje'               => $mensaje,
        ];
    }
}
