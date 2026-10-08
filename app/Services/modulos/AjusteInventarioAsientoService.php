<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\InventarioRepository;
use App\Services\LogSistemaService;
use PDO;

/**
 * Asiento contable de los AJUSTES de inventario hechos desde el módulo Inventario.
 *
 * Cada ajuste es una fila de inventario_kardex (no hay cabecera de documento): el asiento se
 * enlaza por modulo_origen = 'ajuste_inventario' + id_referencia_origen = id del kardex, y en
 * inventario_kardex.id_asiento_contable. Solo se contabilizan las filas con
 * contabiliza_ajuste = true (los ajustes del módulo registrados desde este cambio).
 *
 * El armado (cuentas y montos) vive en AsientoBuilderService::generarAsientoAjusteInventario().
 * Disparadores: al crear/editar/habilitar el ajuste (fuera de su transacción, no fatal), la
 * generación automática al abrir el módulo y la sincronización de Estados Financieros
 * (SincronizadorAsientosService, clave 'ajustes_inventario'). La anulación va DENTRO de la
 * transacción del movimiento (anularAsiento()).
 */
class AjusteInventarioAsientoService
{
    public const MODULO_ORIGEN = 'ajuste_inventario';
    public const CLAVE         = 'ajustes_inventario';

    private InventarioRepository $repo;
    private LogSistemaService $log;

    public function __construct(?InventarioRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo = $repo ?? new InventarioRepository();
        $this->log  = $log  ?? new LogSistemaService();
    }

    /** Punto de entrada del sincronizador: el asiento queda a nombre de quien hizo el ajuste. */
    public function procesarAsientoContablePorSincronizacion(int $idKardex): void
    {
        $st = Database::getConnection()->prepare(
            "SELECT id_empresa, created_by FROM inventario_kardex WHERE id = ? AND eliminado = false"
        );
        $st->execute([$idKardex]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $this->procesarAsientoContable($idKardex, (int) $row['id_empresa'], (int) ($row['created_by'] ?? 0));
    }

    /** Igual que procesarAsientoContable(), pero un fallo contable no interrumpe al llamador. */
    public function procesarSeguro(int $idKardex, int $idEmpresa, int $idUsuario): void
    {
        try {
            $this->procesarAsientoContable($idKardex, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            error_log("[AjusteInventario] Asiento no generado para el movimiento {$idKardex}: " . $e->getMessage());
        }
    }

    /**
     * Crea o actualiza el asiento del ajuste. Solo se persiste completo (todas las líneas con
     * cuenta) y cuadrado; si falta una cuenta lanza una excepción que dice cuál configurar (el
     * sincronizador la muestra como motivo). Si el ajuste ya no tiene costo, anula su asiento.
     */
    public function procesarAsientoContable(int $idKardex, int $idEmpresa, int $idUsuario): void
    {
        if (!$this->repo->tieneColumnasAjusteContable()) {
            return;
        }
        $mov = $this->repo->find($idKardex, $idEmpresa);
        if (!$mov || empty($mov['contabiliza_ajuste']) || ($mov['referencia_tipo'] ?? '') !== 'ajuste_manual') {
            return;
        }

        // Interruptor por empresa (Configuración Contable → Módulos que contabilizan).
        if (ContabilidadInterruptorService::crear()->omitirGeneracion($idEmpresa, self::CLAVE, self::MODULO_ORIGEN, $idKardex)) {
            return;
        }

        $asientoService = $this->asientoService();
        $previo    = $asientoService->getAsientoPorOrigen(self::MODULO_ORIGEN, $idKardex, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $lineas = (new AsientoBuilderService())->generarAsientoAjusteInventario($idEmpresa, $idKardex);
        if ($lineas === []) {
            // Sin costo (p. ej. se editó a costo 0): no hay nada que contabilizar.
            if ($idAsiento > 0) {
                $this->anularAsiento($mov, $idEmpresa, $idUsuario);
            }
            return;
        }

        $faltan = [];
        foreach ($lineas as $l) {
            if ((int) $l['id_cuenta_contable'] <= 0) {
                $faltan[] = '«' . ($l['concepto'] ?? $l['referencia_detalle']) . '»';
            }
        }
        if ($faltan !== []) {
            throw new \Exception('Falta configurar la cuenta ' . implode(' y ', array_unique($faltan))
                . ' en Configuración Contable → Ajustes de Inventario.');
        }

        $producto = trim(($mov['producto_codigo'] ?? '') . ' - ' . ($mov['producto_nombre'] ?? ''), ' -');
        $tipo     = $mov['tipo_movimiento'] === 'salida' ? 'Salida' : 'Entrada';
        $ref      = 'Ajuste inventario # ' . $idKardex;

        $detalles = [];
        foreach ($lineas as $l) {
            $detalles[] = [
                'id_cuenta_contable'   => (int) $l['id_cuenta_contable'],
                'debe'                 => round((float) $l['debe'], 2),
                'haber'                => round((float) $l['haber'], 2),
                'referencia_detalle'   => $l['referencia_detalle'],
                'documento_referencia' => $ref,
            ];
        }

        $cabecera = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => substr((string) ($mov['fecha_movimiento'] ?? date('Y-m-d')), 0, 10),
            'tipo_comprobante'     => 'ajuste_inventario',
            'numero_comprobante'   => '',
            'concepto'             => "Ajuste de inventario # {$idKardex} ({$tipo}) - {$producto} - Bodega: "
                                    . ($mov['bodega_nombre'] ?? ''),
            'estado'               => 'contabilizado',
            'modulo_origen'        => self::MODULO_ORIGEN,
            'id_referencia_origen' => $idKardex,
            'observaciones'        => $mov['observaciones'] ?? null,
        ];

        $idGenerado = $asientoService->guardarAsiento($cabecera, $detalles, $idEmpresa, $idUsuario);
        $this->repo->updateAsientoContable($idKardex, $idEmpresa, $idGenerado);
    }

    /**
     * Anula y desvincula el asiento del ajuste. Se llama DENTRO de la transacción del movimiento
     * (anular/editar) y propaga el error —p. ej. período contable cerrado—, para que el
     * movimiento y su asiento cambien juntos o no cambie ninguno.
     *
     * @param array $mov Fila del kardex leída ANTES del cambio (find() filtra eliminado = false).
     */
    public function anularAsiento(array $mov, int $idEmpresa, int $idUsuario): void
    {
        if (!$this->repo->tieneColumnasAjusteContable()) {
            return;
        }
        $idKardex  = (int) $mov['id'];
        $asientos  = $this->asientoService();
        $idAsiento = (int) ($mov['id_asiento_contable'] ?? 0);
        if ($idAsiento <= 0) {
            $prev = $asientos->getAsientoPorOrigen(self::MODULO_ORIGEN, $idKardex, $idEmpresa);
            $idAsiento = $prev ? (int) $prev['id'] : 0;
        }
        if ($idAsiento <= 0) {
            return;
        }
        $asientos->anularDeDocumento($idAsiento, $idEmpresa, $idUsuario, 'del ajuste de inventario');
        $this->repo->updateAsientoContable($idKardex, $idEmpresa, null);
    }

    private function asientoService(): AsientoContableService
    {
        return new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->log
        );
    }
}
