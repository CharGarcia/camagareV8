<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ProduccionOrdenRepository;
use App\Services\LogSistemaService;

/**
 * Asientos de producción (docs/diseno/produccion-fase1.md §5):
 *  - por cada consumo (grupo):  Dr Productos en proceso / Cr Inventario de materia prima;
 *  - al cerrar la orden:        Dr Inventario de producto terminado (cada salida) + Dr Merma anormal
 *                               / Cr Productos en proceso (materiales) + Cr Mano de obra aplicada
 *                               + Cr Costos indirectos aplicados + Cr Productos en proceso (otros).
 * La cuenta de inventario de cada producto sale de su ficha (productos.id_cuenta_inventario) y,
 * si no tiene, de Configuración Contable → Producción (tipo de asiento 'produccion', que solo ven
 * las empresas que usan el módulo). Respeta el interruptor «Producción» de Módulos que contabilizan
 * y el sincronizador genera los pendientes de las órdenes cerradas. Se genera DESPUÉS del commit del
 * movimiento y un fallo no lo revierte (patrón AjusteInventarioAsientoService::procesarSeguro):
 * el motivo queda en produccion_ordenes.asiento_aviso y la orden ofrece «Generar asiento».
 * La anulación va DENTRO de la transacción de la orden.
 */
class ProduccionAsientoService
{
    public const ORIGEN_CONSUMO = 'produccion_consumo';
    public const ORIGEN_CIERRE  = 'produccion_cierre';
    /** Tipo de asiento de Configuración Contable y clave de «Módulos que contabilizan». */
    public const TIPO_ASIENTO = 'produccion';
    public const CLAVE        = 'produccion';

    /** Campo de cuenta => código del concepto en asientos_tipo. */
    public const CONCEPTOS = [
        'id_cuenta_materia_prima' => 'PROD_MATERIA_PRIMA',
        'id_cuenta_en_proceso'    => 'PROD_EN_PROCESO',
        'id_cuenta_terminado'     => 'PROD_TERMINADO',
        'id_cuenta_mano_obra'     => 'PROD_MANO_OBRA',
        'id_cuenta_cif'           => 'PROD_CIF',
        'id_cuenta_merma'         => 'PROD_MERMA',
    ];

    private ProduccionOrdenRepository $repo;
    private ProduccionConfiguracionService $config;
    private LogSistemaService $log;

    public function __construct(?ProduccionOrdenRepository $repo = null, ?ProduccionConfiguracionService $config = null, ?LogSistemaService $log = null)
    {
        $this->repo   = $repo   ?? new ProduccionOrdenRepository();
        $this->config = $config ?? ProduccionConfiguracionService::crear();
        $this->log    = $log    ?? new LogSistemaService();
    }

    /** Igual que contabilizar(), pero un fallo contable no interrumpe al llamador. */
    public function contabilizarSeguro(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        try {
            $this->contabilizar($idOrden, $idEmpresa, $idUsuario);
            $this->repo->actualizarCabecera($idOrden, ['asiento_aviso' => null], $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            try {
                $this->repo->actualizarCabecera($idOrden, ['asiento_aviso' => mb_substr($e->getMessage(), 0, 1000)], $idEmpresa, $idUsuario);
            } catch (\Throwable $ignorado) {
                // La orden ya quedó guardada; solo no se pudo anotar el motivo.
            }
            error_log("[Produccion] Asiento no generado para la orden {$idOrden}: " . $e->getMessage());
        }
    }

    /** Punto de entrada del sincronizador: el asiento queda a nombre de quien creó la orden. */
    public function procesarAsientoContablePorSincronizacion(int $idOrden): void
    {
        $st = Database::getConnection()->prepare("SELECT id_empresa, created_by FROM produccion_ordenes WHERE id = ? AND eliminado = false");
        $st->execute([$idOrden]);
        $o = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$o) {
            return;
        }
        $this->contabilizar($idOrden, (int) $o['id_empresa'], (int) ($o['created_by'] ?? 0));
        $this->repo->actualizarCabecera($idOrden, ['asiento_aviso' => null], (int) $o['id_empresa'], (int) ($o['created_by'] ?? 0));
    }

    /**
     * Cuentas de producción de la empresa: reglas generales del tipo «Producción» de
     * Configuración Contable. Mientras una cuenta no esté allí se usa la que hubiera quedado
     * en produccion_configuracion (versión anterior del módulo), para no romper la transición.
     *
     * @return array<string, int> campo de cuenta => id_cuenta
     */
    public function cuentas(int $idEmpresa): array
    {
        $porCodigo = [];
        foreach ((new \App\repositories\modulos\AsientoProgramadoRepository())->getReglasGeneralesPorConcepto($idEmpresa, self::TIPO_ASIENTO) as $r) {
            if (!empty($r['id_cuenta'])) {
                $porCodigo[strtoupper((string) $r['codigo'])] = (int) $r['id_cuenta'];
            }
        }
        $cfg = $this->config->get($idEmpresa) ?? [];
        $out = [];
        foreach (self::CONCEPTOS as $campo => $codigo) {
            $out[$campo] = $porCodigo[$codigo] ?? (int) ($cfg[$campo] ?? 0);
        }
        return $out;
    }

    /**
     * Genera los asientos que le falten a la orden (cada consumo sin asiento y, si está
     * cerrada, el de cierre). No hace nada si «Producción» está apagado en Configuración
     * Contable → Módulos que contabilizan (los asientos ya creados se conservan).
     */
    public function contabilizar(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        if (!$this->config->get($idEmpresa)) {
            return;
        }
        if (!ContabilidadInterruptorService::crear()->contabiliza($idEmpresa, self::CLAVE)) {
            return;
        }
        $cfg = $this->cuentas($idEmpresa);
        $faltan = [];
        foreach (['id_cuenta_materia_prima', 'id_cuenta_en_proceso', 'id_cuenta_terminado'] as $campo) {
            if (empty($cfg[$campo])) {
                $faltan[] = '«' . ProduccionConfiguracionService::ETIQUETAS_CUENTA[$campo] . '»';
            }
        }
        if ($faltan !== []) {
            throw new \DomainException('Falta configurar la cuenta ' . implode(', ', $faltan) . ' en Configuración Contable → Producción.');
        }
        $orden = $this->repo->getPorId($idOrden, $idEmpresa);
        if (!$orden || $orden['estado'] === 'anulada') {
            return;
        }

        $movs = $this->repo->getMovimientos($idOrden, $idEmpresa);
        $cuentasProducto = [];
        foreach ($this->repo->getLineas($idOrden, $idEmpresa) as $l) {
            if (!empty($l['id_producto'])) {
                $cuentasProducto[(int) $l['id_producto']] = (int) ($l['id_cuenta_inventario'] ?? 0);
            }
        }

        // ── Consumos: un asiento por grupo ──
        $grupos = [];
        foreach ($movs as $m) {
            if ($m['tipo'] === 'consumo' && empty($m['id_asiento'])) {
                $grupos[(int) $m['grupo']][] = $m;
            }
        }
        foreach ($grupos as $grupo => $lista) {
            $this->asientoConsumo($orden, $grupo, $lista, $cfg, $cuentasProducto, $idEmpresa, $idUsuario);
        }

        // ── Cierre ──
        if ($orden['estado'] === 'cerrada' && empty($orden['id_asiento_cierre'])) {
            $entregas = array_values(array_filter($movs, fn($m) => $m['tipo'] === 'entrega'));
            $this->asientoCierre($orden, $entregas, $cfg, $cuentasProducto, $idEmpresa, $idUsuario);
        }
    }

    private function asientoConsumo(array $orden, int $grupo, array $movs, array $cfg, array $cuentasProducto, int $idEmpresa, int $idUsuario): void
    {
        $porCuenta = [];
        $total = 0.0;
        foreach ($movs as $m) {
            $cta = $cuentasProducto[(int) $m['id_producto']] ?: (int) $cfg['id_cuenta_materia_prima'];
            $porCuenta[$cta] = round(($porCuenta[$cta] ?? 0) + (float) $m['costo_total'], 2);
            $total = round($total + (float) $m['costo_total'], 2);
        }
        $ref = $orden['numero'] . ' / consumo ' . $grupo;
        if ($total <= 0) {
            // Insumos sin costo: no hay importe que contabilizar; se marca para no reintentar.
            $this->repo->fijarAsientoGrupo((int) $orden['id'], $grupo, 'consumo', 0, $idEmpresa);
            return;
        }
        $detalles = [[
            'id_cuenta_contable' => (int) $cfg['id_cuenta_en_proceso'], 'debe' => $total, 'haber' => 0,
            'referencia_detalle' => 'Productos en proceso', 'documento_referencia' => $ref,
        ]];
        foreach ($porCuenta as $cta => $v) {
            if ($v <= 0) {
                continue;
            }
            $detalles[] = [
                'id_cuenta_contable' => $cta, 'debe' => 0, 'haber' => $v,
                'referencia_detalle' => 'Materia prima consumida', 'documento_referencia' => $ref,
            ];
        }
        // El primer movimiento del grupo identifica el asiento (único por consumo).
        $idRef = (int) min(array_map(fn($m) => (int) $m['id'], $movs));
        $idAsiento = $this->guardar(self::ORIGEN_CONSUMO, $idRef, (string) $movs[0]['fecha'],
            "Consumo de producción {$orden['numero']} - " . ($orden['producto_nombre'] ?? ''), $detalles, $idEmpresa, $idUsuario);
        $this->repo->fijarAsientoGrupo((int) $orden['id'], $grupo, 'consumo', $idAsiento, $idEmpresa);
    }

    private function asientoCierre(array $orden, array $entregas, array $cfg, array $cuentasProducto, int $idEmpresa, int $idUsuario): void
    {
        $mp    = round((float) $orden['costo_materiales'], 2);
        $mo    = round((float) $orden['costo_mano_obra'], 2);
        $cif   = round((float) $orden['costo_cif'], 2);
        $otros = round((float) $orden['costo_otros'], 2);
        $merma = round((float) $orden['costo_merma'], 2);
        $ref   = (string) $orden['numero'];

        if ($mo > 0 && empty($cfg['id_cuenta_mano_obra'])) {
            throw new \DomainException('La orden tiene mano de obra aplicada: configure la cuenta «Mano de obra aplicada» en Configuración Contable → Producción.');
        }
        if ($cif > 0 && empty($cfg['id_cuenta_cif'])) {
            throw new \DomainException('La orden tiene costos indirectos: configure la cuenta «Costos indirectos aplicados» en Configuración Contable → Producción.');
        }
        if ($merma > 0 && empty($cfg['id_cuenta_merma'])) {
            throw new \DomainException('La orden tiene merma anormal: configure la cuenta «Merma anormal» en Configuración Contable → Producción.');
        }

        $detalles = [];
        $porCuenta = [];
        foreach ($entregas as $e) {
            $cta = $cuentasProducto[(int) $e['id_producto']] ?: (int) $cfg['id_cuenta_terminado'];
            $porCuenta[$cta] = round(($porCuenta[$cta] ?? 0) + (float) $e['costo_total'], 2);
        }
        foreach ($porCuenta as $cta => $v) {
            if ($v > 0) {
                $detalles[] = ['id_cuenta_contable' => $cta, 'debe' => $v, 'haber' => 0,
                               'referencia_detalle' => 'Producto terminado', 'documento_referencia' => $ref];
            }
        }
        if ($merma > 0) {
            $detalles[] = ['id_cuenta_contable' => (int) $cfg['id_cuenta_merma'], 'debe' => $merma, 'haber' => 0,
                           'referencia_detalle' => 'Merma anormal', 'documento_referencia' => $ref];
        }
        $haberPep = round($mp + $otros, 2);
        if ($haberPep > 0) {
            $detalles[] = ['id_cuenta_contable' => (int) $cfg['id_cuenta_en_proceso'], 'debe' => 0, 'haber' => $haberPep,
                           'referencia_detalle' => 'Productos en proceso', 'documento_referencia' => $ref];
        }
        if ($mo > 0) {
            $detalles[] = ['id_cuenta_contable' => (int) $cfg['id_cuenta_mano_obra'], 'debe' => 0, 'haber' => $mo,
                           'referencia_detalle' => 'Mano de obra aplicada', 'documento_referencia' => $ref];
        }
        if ($cif > 0) {
            $detalles[] = ['id_cuenta_contable' => (int) $cfg['id_cuenta_cif'], 'debe' => 0, 'haber' => $cif,
                           'referencia_detalle' => 'Costos indirectos aplicados', 'documento_referencia' => $ref];
        }

        $debe  = round(array_sum(array_column($detalles, 'debe')), 2);
        $haber = round(array_sum(array_column($detalles, 'haber')), 2);
        if ($debe <= 0 && $haber <= 0) {
            $this->repo->actualizarCabecera((int) $orden['id'], ['id_asiento_cierre' => 0], $idEmpresa, $idUsuario);
            return;
        }
        if (abs($debe - $haber) >= 0.005) {
            throw new \DomainException("El asiento de cierre no cuadra (Debe {$debe} / Haber {$haber}). Revise los costos de la orden.");
        }

        $idAsiento = $this->guardar(self::ORIGEN_CIERRE, (int) $orden['id'], (string) $orden['fecha_fin'],
            "Cierre de producción {$orden['numero']} - " . ($orden['producto_nombre'] ?? ''), $detalles, $idEmpresa, $idUsuario);
        $this->repo->actualizarCabecera((int) $orden['id'], ['id_asiento_cierre' => $idAsiento], $idEmpresa, $idUsuario);
    }

    private function guardar(string $origen, int $idRef, string $fecha, string $concepto, array $detalles, int $idEmpresa, int $idUsuario): int
    {
        $asientos = $this->asientoService();
        $previo = $asientos->getAsientoPorOrigen($origen, $idRef, $idEmpresa);
        return $asientos->guardarAsiento([
            'id'                   => $previo ? (int) $previo['id'] : null,
            'fecha_asiento'        => substr($fecha, 0, 10),
            'tipo_comprobante'     => 'produccion',
            'numero_comprobante'   => '',
            'concepto'             => mb_substr($concepto, 0, 500),
            'estado'               => 'contabilizado',
            'modulo_origen'        => $origen,
            'id_referencia_origen' => $idRef,
        ], $detalles, $idEmpresa, $idUsuario);
    }

    /**
     * Anula los asientos de la orden. Se llama dentro de la transacción de la anulación:
     * si un período está cerrado, la anulación entera se revierte.
     */
    public function anularAsientos(array $orden, array $movs, int $idEmpresa, int $idUsuario): void
    {
        $ids = [];
        foreach ($movs as $m) {
            if (!empty($m['id_asiento'])) {
                $ids[(int) $m['id_asiento']] = true;
            }
        }
        if (!empty($orden['id_asiento_cierre'])) {
            $ids[(int) $orden['id_asiento_cierre']] = true;
        }
        $asientos = $this->asientoService();
        foreach (array_keys($ids) as $idAsiento) {
            if ($idAsiento > 0) {
                $asientos->anularDeDocumento($idAsiento, $idEmpresa, $idUsuario, 'de la orden de producción ' . $orden['numero']);
            }
        }
        if (!empty($orden['id_asiento_cierre'])) {
            $this->repo->actualizarCabecera((int) $orden['id'], ['id_asiento_cierre' => null], $idEmpresa, $idUsuario);
        }
    }

    /**
     * Asientos de la orden para la pestaña «Asiento» del modal.
     *
     * @return array<int, array> cabeceras con su detalle
     */
    public function getAsientos(array $orden, int $idEmpresa): array
    {
        $ids = [];
        foreach ($this->repo->getMovimientos((int) $orden['id'], $idEmpresa) as $m) {
            if (!empty($m['id_asiento'])) {
                $ids[(int) $m['id_asiento']] = true;
            }
        }
        if (!empty($orden['id_asiento_cierre'])) {
            $ids[(int) $orden['id_asiento_cierre']] = true;
        }
        if ($ids === []) {
            return [];
        }
        $repo = new \App\repositories\modulos\AsientoContableRepository();
        $out = [];
        foreach (array_keys($ids) as $id) {
            $a = $repo->getDetalleAsiento($id, $idEmpresa);
            if ($a) {
                $out[] = $a;
            }
        }
        return $out;
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
