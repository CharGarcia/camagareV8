<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\BodegaRepository;
use App\repositories\modulos\InventarioEnceradoRepository;
use App\repositories\modulos\InventarioRepository;
use App\Rules\modulos\InventarioEnceradoRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * «Encerar existencias» (Movimientos de Inventario, solo niveles 2 y 3).
 *
 * Deja en CERO, a la fecha de corte, el saldo de cada producto / bodega / lote / NUP de las
 * bodegas elegidas. No borra nada: registra en el kardex un movimiento que compensa cada saldo
 * (salida si es positivo, entrada si es negativo), fechado al cierre del día de corte y valorado
 * al costo promedio de entradas del producto en esa bodega. No genera asiento contable.
 * Los movimientos posteriores a la fecha de corte siguen vigentes: el stock de hoy queda igual
 * a lo que se movió después del corte.
 *
 * Revertir anula todos los movimientos del encerado (el stock vuelve a como estaba). Solo se
 * revierte el último encerado vigente de sus bodegas.
 */
class InventarioEnceradoService
{
    use \App\Traits\PeriodoContableTrait;

    private const MODULO_GUARDADO = 'inventario_encerado';

    /**
     * Tope de pares producto/bodega por encerado: cada uno toma su candado de stock dentro de
     * la misma transacción y PostgreSQL tiene un límite de candados simultáneos.
     */
    public const MAX_PARES = 2500;

    /** Filas de detalle que devuelve la vista previa (el resumen siempre es completo). */
    private const MAX_DETALLE_PREVIA = 300;

    private InventarioEnceradoRepository $repo;
    private InventarioRepository $invRepo;
    private InventarioEnceradoRules $rules;
    private LogSistemaService $log;

    /** true si el último encerar() devolvió uno ya registrado (reintento del mismo formulario). */
    public bool $ultimoGuardadoRepetido = false;

    public function __construct(
        ?InventarioEnceradoRepository $repo = null,
        ?InventarioRepository $invRepo = null,
        ?InventarioEnceradoRules $rules = null,
        ?LogSistemaService $log = null
    ) {
        $this->repo    = $repo    ?? new InventarioEnceradoRepository();
        $this->invRepo = $invRepo ?? new InventarioRepository();
        $this->rules   = $rules   ?? new InventarioEnceradoRules();
        $this->log     = $log     ?? new LogSistemaService();
    }

    private function requerirTabla(): void
    {
        if (!$this->repo->tablaDisponible()) {
            throw new \Exception('Falta aplicar el SQL database/2026-11-18_inventario_encerados.sql para usar «Encerar existencias».');
        }
    }

    /** Bodegas que el usuario puede encerar (id => nombre). */
    public function bodegasPermitidas(int $idUsuario, int $idEmpresa, int $nivel): array
    {
        $out = [];
        foreach ((new BodegaRepository())->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel) as $b) {
            $out[(int) $b['id']] = (string) $b['nombre'];
        }
        return $out;
    }

    private function validarDatos(array $data, int $idEmpresa, int $idUsuario, int $nivel): array
    {
        $this->rules->validarNivel($nivel);
        $this->requerirTabla();
        $permitidas = $this->bodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        return $this->rules->validarEncerado($data, array_keys($permitidas));
    }

    /** Resume las filas de saldo: lo que el encerado haría, sin escribir nada. */
    private function resumir(array $filas): array
    {
        $pares = [];
        $us = $ue = $vs = $ve = 0.0;
        foreach ($filas as $f) {
            $saldo = (float) $f['saldo'];
            $costo = (float) $f['costo_promedio'];
            $pares[$f['id_producto'] . ':' . $f['id_bodega']] = true;
            if ($saldo > 0) {
                $us += $saldo;
                $vs += round($saldo * $costo, 2);
            } else {
                $ue += -$saldo;
                $ve += round(-$saldo * $costo, 2);
            }
        }
        return [
            'total_movimientos' => count($filas),
            'total_productos'   => count($pares),
            'unidades_salida'   => round($us, 4),
            'unidades_entrada'  => round($ue, 4),
            'valor_salida'      => round($vs, 2),
            'valor_entrada'     => round($ve, 2),
        ];
    }

    /**
     * Vista previa: qué saldos se encerarían y cuánto suman. No escribe nada.
     */
    public function previsualizar(array $data, int $idEmpresa, int $idUsuario, int $nivel): array
    {
        $d = $this->validarDatos($data, $idEmpresa, $idUsuario, $nivel);
        $filas = $this->repo->getSaldosAFecha($idEmpresa, $d['todas_bodegas'] ? null : $d['ids_bodegas'], $d['fecha_corte']);

        $resumen = $this->resumir($filas);
        $detalle = array_map(static fn(array $f) => [
            'producto' => trim(($f['producto_codigo'] ? $f['producto_codigo'] . ' - ' : '') . ($f['producto_nombre'] ?? ('#' . $f['id_producto']))),
            'bodega'   => (string) ($f['bodega_nombre'] ?? ('#' . $f['id_bodega'])),
            'lote'     => $f['lote'],
            'nup'      => $f['nup'],
            'saldo'    => (float) $f['saldo'],
            'costo'    => (float) $f['costo_promedio'],
        ], array_slice($filas, 0, self::MAX_DETALLE_PREVIA));

        return $resumen + [
            'detalle'        => $detalle,
            'detalle_parcial'=> count($filas) > self::MAX_DETALLE_PREVIA,
            'excede_tope'    => $resumen['total_productos'] > self::MAX_PARES,
            'max_pares'      => self::MAX_PARES,
        ];
    }

    /**
     * Aplica el encerado. Devuelve ['id' => int, 'resumen' => array].
     */
    public function encerar(array $data, int $idEmpresa, int $idUsuario, int $nivel): array
    {
        $this->ultimoGuardadoRepetido = false;
        $d = $this->validarDatos($data, $idEmpresa, $idUsuario, $nivel);
        $this->validarPeriodoContable(
            $d['fecha_corte'],
            $idEmpresa,
            'No se puede encerar el inventario: la fecha de corte corresponde a un período contable cerrado.'
        );
        $idsBodegas = $d['todas_bodegas'] ? null : $d['ids_bodegas'];

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        $guardado = new GuardadoUnicoService();
        try {
            // Guardado único (§8): un reintento del mismo formulario devuelve el encerado ya hecho.
            if ($previo = $guardado->previo($data['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO)) {
                if ($managed) $db->rollBack();
                $this->ultimoGuardadoRepetido = true;
                return ['id' => (int) $previo['id_registro'], 'resumen' => $previo['respuesta'] ?? []];
            }

            // 1) Qué pares producto/bodega tienen saldo, para tomar sus candados ANTES de leer
            //    los saldos con los que se calcula (§8). Orden fijo: sin interbloqueos.
            $pares = [];
            foreach ($this->repo->getSaldosAFecha($idEmpresa, $idsBodegas, $d['fecha_corte']) as $f) {
                $pares[$f['id_producto'] . ':' . $f['id_bodega']] = [(int) $f['id_producto'], (int) $f['id_bodega']];
            }
            if ($pares === []) {
                throw new \Exception('No hay existencias distintas de cero a esa fecha en las bodegas elegidas: no hay nada que encerar.');
            }
            if (count($pares) > self::MAX_PARES) {
                throw new \Exception('Son ' . count($pares) . ' productos/bodegas con saldo; el máximo por encerado es '
                    . self::MAX_PARES . '. Encere menos bodegas a la vez.');
            }
            ksort($pares, SORT_STRING);
            foreach ($pares as [$idProd, $idBod]) {
                $this->invRepo->lockStock($idProd, $idBod, $idEmpresa);
            }

            // 2) Saldos releídos bajo candado.
            $filas = $this->repo->getSaldosAFecha($idEmpresa, $idsBodegas, $d['fecha_corte']);
            if ($filas === []) {
                throw new \Exception('No hay existencias distintas de cero a esa fecha en las bodegas elegidas: no hay nada que encerar.');
            }

            $idEncerado = $this->repo->insertCabecera([
                'id_empresa'    => $idEmpresa,
                'fecha_corte'   => $d['fecha_corte'],
                'todas_bodegas' => $d['todas_bodegas'],
                'ids_bodegas'   => $d['ids_bodegas'],
                'observacion'   => $d['observacion'],
                'id_usuario'    => $idUsuario,
            ]);

            // Saldo total de cada par a la fecha (suma de sus lotes/NUP con saldo).
            $saldoPar = [];
            foreach ($filas as $f) {
                $k = $f['id_producto'] . ':' . $f['id_bodega'];
                $saldoPar[$k] = ($saldoPar[$k] ?? 0.0) + (float) $f['saldo'];
            }

            $fechaMov = $d['fecha_corte'] . ' 23:59:59';
            $obs = 'Encerado de existencias #' . $idEncerado . ' al '
                 . date('d-m-Y', strtotime($d['fecha_corte'])) . ': ' . $d['observacion'];
            $bodegasAfectadas = [];
            $paresTocados = [];

            // 3) Un movimiento por saldo, que lo deja en cero.
            foreach ($filas as $f) {
                $idProd = (int) $f['id_producto'];
                $idBod  = (int) $f['id_bodega'];
                $k      = $idProd . ':' . $idBod;
                $saldo  = (float) $f['saldo'];
                $costo  = (float) $f['costo_promedio'];
                $antes  = round($saldoPar[$k], 4);
                $saldoPar[$k] = $antes - $saldo;

                $this->invRepo->registrarMovimiento([
                    'id_empresa'       => $idEmpresa,
                    'id_producto'      => $idProd,
                    'id_bodega'        => $idBod,
                    'tipo_movimiento'  => $saldo > 0 ? 'salida' : 'entrada',
                    'referencia_tipo'  => InventarioEnceradoRepository::REFERENCIA_TIPO,
                    'referencia_id'    => $idEncerado,
                    'cantidad'         => -$saldo,
                    'costo_unitario'   => $costo,
                    'costo_total'      => round(abs($saldo) * $costo, 2),
                    'stock_anterior'   => $antes,
                    'stock_posterior'  => round($saldoPar[$k], 4),
                    'numero_lote'      => $f['lote'],
                    'fecha_caducidad'  => $f['fecha_caducidad'],
                    'nup'              => $f['nup'],
                    'observaciones'    => mb_substr($obs, 0, 500),
                    'id_usuario'       => $idUsuario,
                    'fecha_movimiento' => $fechaMov,
                ]);
                $bodegasAfectadas[$idBod] = true;
                $paresTocados[$k] = [$idProd, $idBod];
            }

            // 4) Caché de stock: se recalcula desde el kardex (incluye lo posterior al corte).
            foreach ($paresTocados as [$idProd, $idBod]) {
                $real = $this->invRepo->getStockActual($idProd, $idBod, $idEmpresa);
                $this->invRepo->actualizarStock($idProd, $idBod, $idEmpresa, $real, $idUsuario);
            }

            $resumen = $this->resumir($filas);
            $idsAfectadas = array_map('intval', array_keys($bodegasAfectadas));
            sort($idsAfectadas);
            $this->repo->actualizarTotales($idEncerado, $idEmpresa, $resumen + [
                // De «todas» se guardan las bodegas que efectivamente se encerraron.
                'ids_bodegas' => $d['todas_bodegas'] ? $idsAfectadas : $d['ids_bodegas'],
            ]);

            $this->log->registrar($idUsuario, $idEmpresa, 'ENCERAR_INVENTARIO', 'inventario_encerados', $idEncerado, null, [
                'fecha_corte'      => $d['fecha_corte'],
                'todas_bodegas'    => $d['todas_bodegas'],
                'ids_bodegas'      => $d['todas_bodegas'] ? $idsAfectadas : $d['ids_bodegas'],
                'observacion'      => $d['observacion'],
            ] + $resumen);

            $guardado->registrar($data['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO, $idEncerado, (string) $idEncerado, $idUsuario, $resumen);

            if ($managed) $db->commit();
            return ['id' => $idEncerado, 'resumen' => $resumen];
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Historial para la pestaña del modal. */
    public function listar(int $idEmpresa, int $nivel): array
    {
        $this->rules->validarNivel($nivel);
        if (!$this->repo->tablaDisponible()) {
            return [];
        }
        return $this->repo->listar($idEmpresa);
    }

    /**
     * Revierte un encerado: anula todos sus movimientos y recalcula el stock.
     */
    public function revertir(int $id, string $motivo, int $idEmpresa, int $idUsuario, int $nivel): void
    {
        $this->rules->validarNivel($nivel);
        $this->requerirTabla();

        $cab = $this->repo->getById($id, $idEmpresa);
        if (!$cab) {
            throw new \Exception('Encerado no encontrado.');
        }
        $motivo = $this->rules->validarReversion($cab, $motivo);
        $this->validarPeriodoContable(
            (string) $cab['fecha_corte'],
            $idEmpresa,
            'No se puede revertir el encerado: su fecha de corte corresponde a un período contable cerrado.'
        );

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            // Releído con FOR UPDATE: dos reversiones simultáneas no anulan dos veces.
            $cab = $this->repo->getById($id, $idEmpresa, true);
            if (!$cab) {
                throw new \Exception('Encerado no encontrado.');
            }
            $this->rules->validarReversion($cab, $motivo);

            if ($posterior = $this->repo->getPosteriorVigente($cab, $idEmpresa)) {
                throw new \Exception('Primero revierta el encerado #' . $posterior['id']
                    . ' (corte ' . date('d-m-Y', strtotime((string) $posterior['fecha_corte']))
                    . '): es posterior y comparte bodegas con este.');
            }

            $pares = $this->repo->getParesMovimientos($id, $idEmpresa);
            foreach ($pares as $p) {
                $this->invRepo->lockStock((int) $p['id_producto'], (int) $p['id_bodega'], $idEmpresa);
            }

            $anulados = $this->repo->anularMovimientos($id, $idEmpresa, $idUsuario);

            foreach ($pares as $p) {
                $idProd = (int) $p['id_producto'];
                $idBod  = (int) $p['id_bodega'];
                $real = $this->invRepo->getStockActual($idProd, $idBod, $idEmpresa);
                $this->invRepo->actualizarStock($idProd, $idBod, $idEmpresa, $real, $idUsuario);
            }

            $this->repo->marcarRevertido($id, $idEmpresa, $idUsuario, $motivo);

            $this->log->registrar($idUsuario, $idEmpresa, 'REVERTIR_ENCERADO_INVENTARIO', 'inventario_encerados', $id, $cab, [
                'estado'               => 'revertido',
                'motivo_reversion'     => $motivo,
                'movimientos_anulados' => $anulados,
            ]);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
