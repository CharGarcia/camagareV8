<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\PeriodosContablesRepository;
use App\repositories\modulos\ProduccionFormulaRepository;
use App\repositories\modulos\ProduccionOrdenRepository;
use App\Rules\modulos\PeriodosContablesRules;
use App\Rules\modulos\ProduccionOrdenRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * Órdenes de producción: crear (copia escalada de la fórmula), consumir insumos, cerrar
 * (entregas al inventario con costo real repartido) y anular. Producción rápida = crear +
 * consumir lo teórico + cerrar, en una sola transacción.
 *
 * Inventario: usa SOLO las funciones públicas de InventarioRepository (lockStock,
 * getStockActual, registrarMovimiento, actualizarStock), como Transferencias de
 * Inventario, y para anular InventarioService::eliminarMovimiento(). No cambia ninguna
 * de ellas (docs/diseno/produccion-fase1.md §10.4). Sus movimientos llevan
 * referencia_tipo propio (produccion_consumo / produccion_entrega) y, por la regla
 * vigente del Kardex, no se pueden editar ni anular a mano desde Inventario.
 *
 * Concurrencia (§8 de CLAUDE.md): toda acción toma primero el candado de la orden
 * (FOR UPDATE) y los candados de stock de todos los pares producto/bodega que va a
 * tocar, en orden determinista, ANTES de leer saldos.
 */
class ProduccionOrdenService
{
    private const EPS = 0.000001;

    private ProduccionOrdenRepository $repo;
    private ProduccionFormulaRepository $formulas;
    private InventarioRepository $inv;
    private ProduccionOrdenRules $rules;
    private LogSistemaService $log;
    private ProduccionConfiguracionService $config;
    private ?InventarioService $inventarioService = null;
    private ?PeriodosContablesService $periodos = null;
    private bool $ultimoRepetido = false;

    /** @var array<int, string> método de costeo por establecimiento */
    private array $metodos = [];

    public function __construct(
        ?ProduccionOrdenRepository $repo = null,
        ?ProduccionFormulaRepository $formulas = null,
        ?InventarioRepository $inv = null,
        ?ProduccionOrdenRules $rules = null,
        ?LogSistemaService $log = null,
        ?ProduccionConfiguracionService $config = null
    ) {
        $this->repo     = $repo     ?? new ProduccionOrdenRepository();
        $this->formulas = $formulas ?? new ProduccionFormulaRepository();
        $this->inv      = $inv      ?? new InventarioRepository();
        $this->rules    = $rules    ?? new ProduccionOrdenRules();
        $this->log      = $log      ?? new LogSistemaService();
        $this->config   = $config   ?? ProduccionConfiguracionService::crear();
    }

    public static function crear(): self
    {
        return new self();
    }

    public function repo(): ProduccionOrdenRepository
    {
        return $this->repo;
    }

    public function config(): ProduccionConfiguracionService
    {
        return $this->config;
    }

    /** ¿La última acción era un reintento de un guardado ya hecho? */
    public function fueRepetido(): bool
    {
        return $this->ultimoRepetido;
    }

    private function inventarioService(): InventarioService
    {
        if ($this->inventarioService === null) {
            $this->inventarioService = new InventarioService($this->inv, $this->log);
        }
        return $this->inventarioService;
    }

    private function bodegas(): BodegaService
    {
        return new BodegaService(new \App\repositories\modulos\BodegaRepository(), new \App\Rules\modulos\BodegaRules(), $this->log);
    }

    private function periodos(): PeriodosContablesService
    {
        if ($this->periodos === null) {
            $this->periodos = new PeriodosContablesService(new PeriodosContablesRepository(), new PeriodosContablesRules(), $this->log);
        }
        return $this->periodos;
    }

    private function validarPeriodo(string $fecha, int $idEmpresa): void
    {
        $this->periodos()->validarFechaPermitida(
            $fecha,
            $idEmpresa,
            "La fecha {$fecha} corresponde a un período contable cerrado: no se puede registrar producción en esa fecha."
        );
    }

    // ────────────────────────────────────────────────────────────────
    // LECTURA
    // ────────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro, array $filtros): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $idUsuarioFiltro, $filtros);
    }

    public function getResumen(int $idEmpresa, ?int $idUsuarioFiltro, array $filtros, string $buscar): array
    {
        return $this->repo->getResumen($idEmpresa, $idUsuarioFiltro, $filtros, $buscar);
    }

    /** Orden completa para el modal. */
    public function getCompleta(int $id, int $idEmpresa): ?array
    {
        $o = $this->repo->getPorId($id, $idEmpresa);
        if (!$o) {
            return null;
        }
        $o['lineas']       = $this->repo->getLineas($id, $idEmpresa);
        $o['recursos']     = $this->repo->getRecursos($id, $idEmpresa);
        $o['movimientos']  = $this->repo->getMovimientos($id, $idEmpresa);
        $o['trazabilidad'] = $this->repo->getTrazabilidad($id, $idEmpresa);
        $ids = array_filter(array_map(fn($l) => $l['rol'] === 'insumo' ? (int) $l['id_producto'] : 0, $o['lineas']));
        $o['stocks'] = $o['id_bodega_insumos'] ? $this->repo->getStocks($ids, (int) $o['id_bodega_insumos'], $idEmpresa) : [];
        return $o;
    }

    /**
     * Teórico de una fórmula para una cantidad, con el stock de la bodega de insumos y
     * los faltantes (pantalla "nueva orden" y producción rápida, antes de confirmar).
     */
    public function vistaPrevia(int $idFormula, float $cantidad, int $idBodegaInsumos, int $idEmpresa): array
    {
        $f = $this->formulas->getPorId($idFormula, $idEmpresa);
        if (!$f) {
            throw new \DomainException('La fórmula no existe.');
        }
        $lineas = $this->escalar($f, $this->formulas->getLineas($idFormula, $idEmpresa), $cantidad);
        $ids = array_filter(array_map(fn($l) => $l['rol'] === 'insumo' ? (int) $l['id_producto'] : 0, $lineas));
        $stocks = $idBodegaInsumos > 0 ? $this->repo->getStocks($ids, $idBodegaInsumos, $idEmpresa) : [];
        $productos = $this->formulas->getProductos(array_filter(array_column($lineas, 'id_producto')), $idEmpresa);
        $faltan = 0;
        foreach ($lineas as &$l) {
            $p = $productos[(int) ($l['id_producto'] ?? 0)] ?? [];
            $l['producto_codigo'] = $p['codigo'] ?? '';
            $l['producto_nombre'] = $p['nombre'] ?? ($l['descripcion'] ?? '');
            $l['medida_nombre']   = $p['medida_nombre'] ?? '';
            if ($l['rol'] === 'insumo') {
                $l['stock'] = $stocks[(int) $l['id_producto']] ?? 0.0;
                $l['falta'] = max(0.0, round($l['cantidad_teorica'] - $l['stock'], 6));
                if ($l['falta'] > self::EPS) {
                    $faltan++;
                }
            }
        }
        unset($l);

        $vida = $f['vida_util_dias'] !== null ? (int) $f['vida_util_dias'] : null;
        return [
            'formula'          => $f,
            'lineas'           => $lineas,
            'faltantes'        => $faltan,
            'caducidad_sugerida' => $vida !== null ? date('Y-m-d', strtotime("+{$vida} days")) : null,
        ];
    }

    /**
     * Copia escalada de la fórmula: cantidades teóricas de entradas y salidas.
     *
     * @return array<int, array>
     */
    private function escalar(array $formula, array $lineasFormula, float $cantidad): array
    {
        $base = (float) $formula['cantidad_base'];
        $out = [];
        foreach ($lineasFormula as $i => $l) {
            $out[] = [
                'rol'                   => $l['rol'],
                'id_producto'           => $l['id_producto'] ? (int) $l['id_producto'] : null,
                'descripcion'           => $l['descripcion'],
                'cantidad_teorica'      => ProduccionCosteoService::cantidadTeorica($l, $base, $cantidad),
                'merma_pct'             => (float) $l['merma_pct'],
                'exige_lote'            => !empty($l['exige_lote']),
                'reparto_pct'           => $l['reparto_pct'],
                'valor_neto_realizable' => $l['valor_neto_realizable'],
                'orden'                 => $i + 1,
            ];
        }
        return $out;
    }

    // ────────────────────────────────────────────────────────────────
    // CREAR
    // ────────────────────────────────────────────────────────────────

    /**
     * Crea la orden (estado Planificada) con la copia escalada de la fórmula y sus costos
     * estándar de MO/CIF. Guardado único por formulario.
     */
    public function crearOrden(array $in, int $idEmpresa, int $idUsuario, int $nivel): int
    {
        $cfg = $this->config->requerir($idEmpresa);
        $d = $this->normalizarCreacion($in, $cfg);
        $formula = $d['id_formula'] > 0 ? $this->formulas->getPorId($d['id_formula'], $idEmpresa) : null;
        $this->rules->validarCreacion($d, $formula);
        $this->validarBodegas($d, $idEmpresa, $idUsuario, $nivel);

        $db = Database::getConnection();
        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }
        $this->ultimoRepetido = false;
        $guardado = new GuardadoUnicoService();
        try {
            if ($previo = $guardado->previo($d['token_guardado'], $idEmpresa, 'produccion_ordenes')) {
                if ($propia) {
                    $db->rollBack();
                }
                $this->ultimoRepetido = true;
                return $previo['id_registro'];
            }

            $id = $this->insertarOrden($d, $formula, $idEmpresa, $idUsuario);

            $guardado->registrar($d['token_guardado'], $idEmpresa, 'produccion_ordenes', $id, $this->repo->getPorId($id, $idEmpresa)['numero'] ?? null, $idUsuario);
            if ($propia) {
                $db->commit();
            }
            return $id;
        } catch (\Throwable $e) {
            if ($propia && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function normalizarCreacion(array $in, array $cfg): array
    {
        $num = static fn($v): float => (float) str_replace(',', '.', (string) ($v ?? 0));
        $fecha = trim((string) ($in['fecha_inicio'] ?? '')) ?: date('Y-m-d');
        $destino = (int) ($in['id_bodega_destino'] ?? 0);
        if ($destino <= 0) {
            $usaCuarentena = !empty($cfg['usar_cuarentena']) && !empty($cfg['id_bodega_cuarentena']);
            $destino = (int) ($usaCuarentena ? $cfg['id_bodega_cuarentena'] : ($cfg['id_bodega_terminado'] ?? 0));
        }
        return [
            'tipo'                 => (string) ($in['tipo'] ?? 'orden') === 'rapida' ? 'rapida' : 'orden',
            'id_formula'           => (int) ($in['id_formula'] ?? 0),
            'cantidad_planificada' => round($num($in['cantidad_planificada'] ?? 0), 6),
            'id_bodega_insumos'    => (int) ($in['id_bodega_insumos'] ?? 0) ?: (int) ($cfg['id_bodega_insumos'] ?? 0),
            'id_bodega_destino'    => $destino,
            'fecha_inicio'         => $fecha,
            'lote'                 => trim((string) ($in['lote'] ?? '')),
            'fecha_caducidad'      => trim((string) ($in['fecha_caducidad'] ?? '')) ?: null,
            'responsable'          => trim((string) ($in['responsable'] ?? '')) ?: null,
            'observaciones'        => trim((string) ($in['observaciones'] ?? '')) ?: null,
            'token_guardado'       => (string) ($in['token_guardado'] ?? ''),
        ];
    }

    private function validarBodegas(array $d, int $idEmpresa, int $idUsuario, int $nivel): void
    {
        $bodegas = $this->bodegas();
        foreach (['id_bodega_insumos' => 'de insumos', 'id_bodega_destino' => 'de destino'] as $campo => $nombre) {
            if (!$this->config->repo()->bodegaValida((int) $d[$campo], $idEmpresa)) {
                throw new \DomainException("La bodega {$nombre} no existe en esta empresa.");
            }
            if (!$bodegas->validarAccesoUsuario($idUsuario, (int) $d[$campo], $idEmpresa, $nivel)) {
                throw new \DomainException("No tiene acceso a la bodega {$nombre}.");
            }
        }
    }

    /** Inserta cabecera + líneas + recursos (ya dentro de la transacción). */
    private function insertarOrden(array $d, array $formula, int $idEmpresa, int $idUsuario): int
    {
        $ambiente   = $this->repo->getTipoAmbiente($idEmpresa);
        $secuencial = $this->repo->siguienteSecuencial($idEmpresa, $ambiente);
        $numero     = 'OP-' . str_pad((string) $secuencial, 6, '0', STR_PAD_LEFT);

        $caducidad = $d['fecha_caducidad'];
        if ($caducidad === null && $formula['vida_util_dias'] !== null) {
            $caducidad = date('Y-m-d', strtotime($d['fecha_inicio'] . ' +' . (int) $formula['vida_util_dias'] . ' days'));
        }

        $id = $this->repo->insertarCabecera([
            'secuencial'           => $secuencial,
            'numero'               => $numero,
            'tipo'                 => $d['tipo'],
            'id_formula'           => (int) $formula['id'],
            'version_formula'      => (int) $formula['version'],
            'id_producto'          => (int) $formula['id_producto'],
            'cantidad_planificada' => $d['cantidad_planificada'],
            'id_bodega_insumos'    => $d['id_bodega_insumos'],
            'id_bodega_destino'    => $d['id_bodega_destino'],
            'id_establecimiento'   => $this->repo->getEstablecimientoBodega($d['id_bodega_insumos'], $idEmpresa),
            'fecha_inicio'         => $d['fecha_inicio'],
            'lote'                 => $d['lote'] !== '' ? mb_substr($d['lote'], 0, 50) : $numero,
            'fecha_caducidad'      => $caducidad,
            'responsable'          => $d['responsable'] !== null ? mb_substr($d['responsable'], 0, 150) : null,
            'metodo_reparto'       => $formula['metodo_reparto'] ?: null,
            'estado'               => 'liberada',
            'tipo_ambiente'        => $ambiente,
            'observaciones'        => $d['observaciones'],
        ], $idEmpresa, $idUsuario);

        foreach ($this->escalar($formula, $this->formulas->getLineas((int) $formula['id'], $idEmpresa), $d['cantidad_planificada']) as $l) {
            $this->repo->insertarLinea($l, $id, $idEmpresa, $idUsuario);
        }
        foreach ($this->formulas->getRecursos((int) $formula['id'], $idEmpresa) as $i => $r) {
            $this->repo->insertarRecurso([
                'tipo' => $r['tipo'], 'descripcion' => $r['descripcion'], 'base' => $r['base'],
                'cantidad' => (float) $r['cantidad'], 'tarifa' => (float) $r['tarifa'], 'orden' => $i + 1,
            ], $id, $idEmpresa, $idUsuario);
        }

        $this->log->registrar($idUsuario, $idEmpresa, 'CREAR_ORDEN_PRODUCCION', 'produccion_ordenes', $id, null, [
            'numero' => $numero, 'tipo' => $d['tipo'], 'formula' => $formula['codigo'] . ' v' . $formula['version'],
            'cantidad' => $d['cantidad_planificada'], 'id_bodega_insumos' => $d['id_bodega_insumos'],
            'id_bodega_destino' => $d['id_bodega_destino'],
        ]);
        return $id;
    }

    // ────────────────────────────────────────────────────────────────
    // CONSUMIR
    // ────────────────────────────────────────────────────────────────

    /**
     * Registra un consumo de insumos (parcial o total, más o menos que lo teórico, o un
     * insumo fuera de fórmula). Cada ítem: id_linea | id_producto, cantidad (o peso_inicial
     * y peso_final), numero_lote opcional.
     *
     * @return int grupo de movimientos creado
     */
    public function consumir(int $idOrden, array $items, string $fecha, string $token, int $idEmpresa, int $idUsuario, int $nivel): int
    {
        $cfg = $this->config->requerir($idEmpresa);
        $this->rules->validarFecha($fecha, 'del consumo');
        $this->validarPeriodo($fecha, $idEmpresa);

        $db = Database::getConnection();
        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }
        $this->ultimoRepetido = false;
        $guardado = new GuardadoUnicoService();
        try {
            if ($previo = $guardado->previo($token, $idEmpresa, 'produccion_consumo')) {
                if ($propia) {
                    $db->rollBack();
                }
                $this->ultimoRepetido = true;
                return $previo['id_registro'];
            }

            $orden = $this->repo->getParaActualizar($idOrden, $idEmpresa);
            if (!$orden) {
                throw new \DomainException('La orden no existe.');
            }
            $this->rules->validarAbierta($orden, 'consumir');
            $this->validarAccesoOrden($orden, $idEmpresa, $idUsuario, $nivel);

            $grupo = $this->consumirInterno($orden, $items, $fecha, $cfg, $idEmpresa, $idUsuario);

            $guardado->registrar($token, $idEmpresa, 'produccion_consumo', $grupo, $orden['numero'], $idUsuario);
            if ($propia) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($propia && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($propia) {
            (new ProduccionAsientoService())->contabilizarSeguro($idOrden, $idEmpresa, $idUsuario);
        }
        return $grupo;
    }

    /** Consumo ya dentro de la transacción, con la orden bloqueada. */
    private function consumirInterno(array $orden, array $items, string $fecha, array $cfg, int $idEmpresa, int $idUsuario): int
    {
        $idOrden = (int) $orden['id'];
        $bodega  = (int) $orden['id_bodega_insumos'];
        $lineas  = [];
        foreach ($this->repo->getLineas($idOrden, $idEmpresa) as $l) {
            $lineas[(int) $l['id']] = $l;
        }

        // Normalizar ítems (cantidad por diferencia de peso si viene).
        $norm = [];
        foreach ($items as $it) {
            $idLinea = (int) ($it['id_linea'] ?? 0);
            $linea   = $lineas[$idLinea] ?? null;
            if ($linea && $linea['rol'] !== 'insumo') {
                throw new \DomainException('Solo se consumen insumos.');
            }
            $idProducto = $linea ? (int) $linea['id_producto'] : (int) ($it['id_producto'] ?? 0);
            if ($idProducto <= 0) {
                continue;
            }
            $pi = ($it['peso_inicial'] ?? '') !== '' ? (float) $it['peso_inicial'] : null;
            $pf = ($it['peso_final'] ?? '') !== '' ? (float) $it['peso_final'] : null;
            $cantidad = ($pi !== null && $pf !== null) ? $pi - $pf : (float) ($it['cantidad'] ?? 0);
            if ($pi !== null && $pf !== null && $pf > $pi) {
                throw new \DomainException('El peso final no puede ser mayor que el inicial.');
            }
            $cantidad = round($cantidad, 6);
            if ($cantidad <= 0) {
                continue;
            }
            $norm[] = [
                'linea' => $linea, 'id_producto' => $idProducto, 'cantidad' => $cantidad,
                'lote' => trim((string) ($it['numero_lote'] ?? '')), 'peso_inicial' => $pi, 'peso_final' => $pf,
            ];
        }
        if ($norm === []) {
            throw new \DomainException('Indique al menos un insumo con cantidad a consumir.');
        }

        // Insumos fuera de fórmula: se agregan como línea nueva de la orden.
        $productos = $this->formulas->getProductos(array_column($norm, 'id_producto'), $idEmpresa);
        foreach ($norm as &$n) {
            if (!isset($productos[$n['id_producto']])) {
                throw new \DomainException('Uno de los insumos no existe en esta empresa.');
            }
            $p = $productos[$n['id_producto']];
            $inventariable = $p['inventariable'] === true || $p['inventariable'] === 't';
            if (!$inventariable || (string) $p['tipo_produccion'] === '02') {
                throw new \DomainException("«{$p['nombre']}» no es un bien inventariable.");
            }
            if ($n['linea'] === null) {
                $idNueva = $this->repo->insertarLinea([
                    'rol' => 'insumo', 'id_producto' => $n['id_producto'], 'cantidad_teorica' => 0,
                    'fuera_formula' => true, 'orden' => 900,
                ], $idOrden, $idEmpresa, $idUsuario);
                $n['linea'] = ['id' => $idNueva, 'exige_lote' => false, 'rol' => 'insumo'];
            }
        }
        unset($n);

        // Candados de stock en orden determinista, antes de leer saldos.
        $ids = array_values(array_unique(array_column($norm, 'id_producto')));
        sort($ids);
        foreach ($ids as $idP) {
            $this->inv->lockStock($idP, $bodega, $idEmpresa);
        }

        $grupo  = $this->repo->siguienteGrupo($idOrden, $idEmpresa);
        $metodo = $this->metodoCosteo($orden['id_establecimiento'] ? (int) $orden['id_establecimiento'] : null);
        $permitirNeg = !empty($cfg['permitir_sin_stock']);
        $exigirLoteCfg = !empty($cfg['exigir_lote_insumos']);
        $fechaMov = $this->fechaMovimiento($fecha);

        foreach ($norm as $n) {
            $p = $productos[$n['id_producto']];
            $exigeLote = $exigirLoteCfg || !empty($n['linea']['exige_lote']);
            foreach ($this->tramosConsumo($n['id_producto'], $bodega, $n['cantidad'], $n['lote'], $exigeLote, $permitirNeg, (string) $p['nombre'], $idEmpresa) as $t) {
                $costoU = $this->costoSalida($n['id_producto'], $bodega, $metodo, $p, $idEmpresa);
                $costoT = round($t['cantidad'] * $costoU, 2);
                $stock  = $this->inv->getStockActual($n['id_producto'], $bodega, $idEmpresa);
                $idKardex = $this->inv->registrarMovimiento([
                    'id_empresa'       => $idEmpresa,
                    'id_producto'      => $n['id_producto'],
                    'id_bodega'        => $bodega,
                    'tipo_movimiento'  => 'salida',
                    'referencia_tipo'  => ProduccionOrdenRepository::REF_CONSUMO,
                    'referencia_id'    => $idOrden,
                    'fecha_movimiento' => $fechaMov,
                    'cantidad'         => -$t['cantidad'],
                    'costo_unitario'   => $costoU,
                    // Convención del kardex: costo_total positivo también en las salidas.
                    'costo_total'      => $costoT,
                    'stock_anterior'   => $stock,
                    'stock_posterior'  => round($stock - $t['cantidad'], 6),
                    'numero_lote'      => $t['lote'],
                    'fecha_caducidad'  => $t['caducidad'],
                    'id_medida'        => $p['id_medida'] ? (int) $p['id_medida'] : null,
                    'observaciones'    => "Consumo de producción {$orden['numero']}",
                    'id_usuario'       => $idUsuario,
                ]);
                $this->inv->actualizarStock($n['id_producto'], $bodega, $idEmpresa, round($stock - $t['cantidad'], 6), $idUsuario);

                $this->repo->insertarMovimiento([
                    'id_orden' => $idOrden, 'id_linea' => (int) $n['linea']['id'], 'grupo' => $grupo, 'tipo' => 'consumo',
                    'id_producto' => $n['id_producto'], 'id_bodega' => $bodega, 'cantidad' => $t['cantidad'],
                    'numero_lote' => $t['lote'], 'fecha_caducidad' => $t['caducidad'],
                    'peso_inicial' => $n['peso_inicial'], 'peso_final' => $n['peso_final'],
                    'costo_unitario' => $costoU, 'costo_total' => $costoT, 'id_kardex' => $idKardex, 'fecha' => $fecha,
                ], $idEmpresa, $idUsuario);
                $this->repo->sumarRealLinea((int) $n['linea']['id'], $t['cantidad'], $costoT, $idEmpresa, $idUsuario);
            }
        }

        if ($orden['estado'] === 'liberada') {
            $this->repo->actualizarCabecera($idOrden, ['estado' => 'en_proceso'], $idEmpresa, $idUsuario);
        }
        $this->log->registrar($idUsuario, $idEmpresa, 'CONSUMO_ORDEN_PRODUCCION', 'produccion_ordenes', $idOrden, null, [
            'numero' => $orden['numero'], 'grupo' => $grupo,
            'insumos' => array_map(fn($n) => ['id_producto' => $n['id_producto'], 'cantidad' => $n['cantidad'], 'lote' => $n['lote']], $norm),
        ]);
        return $grupo;
    }

    /**
     * De qué lotes sale un consumo. Con lote indicado: de ese lote. Sin lote: si el insumo
     * maneja lotes y se exige, error; si no, el que vence primero (FEFO) y, si no alcanza,
     * los siguientes. Valida el stock salvo que la configuración permita consumir sin stock.
     *
     * @return array<int, array{cantidad: float, lote: ?string, caducidad: ?string}>
     */
    private function tramosConsumo(int $idProducto, int $bodega, float $cantidad, string $lote, bool $exigeLote, bool $permitirNeg, string $nombre, int $idEmpresa): array
    {
        $lotes = array_values(array_filter(
            $this->inv->getLotesDisponibles($idProducto, $bodega, $idEmpresa),
            fn($l) => !in_array(strtoupper((string) $l['numero_lote']), ['SIN_LOTE', 'SIN LOTE', ''], true)
        ));

        if ($lote !== '') {
            $stockLote = $this->inv->getStockLote($idProducto, $bodega, $idEmpresa, $lote);
            if (!$permitirNeg && $cantidad > $stockLote + self::EPS) {
                throw new \DomainException("«{$nombre}»: el lote {$lote} solo tiene " . $this->fmt($stockLote) . ' y se pide consumir ' . $this->fmt($cantidad) . '.');
            }
            $cad = null;
            foreach ($lotes as $l) {
                if ((string) $l['numero_lote'] === $lote) {
                    $cad = $l['fecha_caducidad'] ?: null;
                }
            }
            return [['cantidad' => $cantidad, 'lote' => $lote, 'caducidad' => $cad]];
        }

        $stock = $this->inv->getStockActual($idProducto, $bodega, $idEmpresa);
        if (!$permitirNeg && $cantidad > $stock + self::EPS) {
            throw new \DomainException("Stock insuficiente de «{$nombre}»: disponible " . $this->fmt($stock) . ', se pide consumir ' . $this->fmt($cantidad) . '.');
        }
        if ($lotes === []) {
            return [['cantidad' => $cantidad, 'lote' => null, 'caducidad' => null]];
        }
        if ($exigeLote) {
            throw new \DomainException("«{$nombre}» maneja lotes: indique de qué lote sale.");
        }

        $tramos = [];
        $resta = $cantidad;
        foreach ($lotes as $l) {
            if ($resta <= self::EPS) {
                break;
            }
            $toma = min($resta, (float) $l['stock_lote']);
            if ($toma <= self::EPS) {
                continue;
            }
            $tramos[] = ['cantidad' => round($toma, 6), 'lote' => (string) $l['numero_lote'], 'caducidad' => $l['fecha_caducidad'] ?: null];
            $resta = round($resta - $toma, 6);
        }
        if ($resta > self::EPS) {
            // Lo que no cubren los lotes sale sin lote (stock sin lote o, si se permite, en negativo).
            $tramos[] = ['cantidad' => $resta, 'lote' => null, 'caducidad' => null];
        }
        return $tramos;
    }

    /** Costo de salida según el método del establecimiento (mismo criterio que las ventas). */
    private function costoSalida(int $idProducto, int $bodega, string $metodo, array $producto, int $idEmpresa): float
    {
        $entradas = match ($metodo) {
            'fifo'  => $this->inv->getEntradasFIFO($idProducto, $bodega, $idEmpresa),
            'lifo'  => $this->inv->getEntradasLIFO($idProducto, $bodega, $idEmpresa),
            default => null,
        };
        $costo = $entradas === null
            ? $this->inv->getCostoPromedio($idProducto, $bodega, $idEmpresa)
            : (float) ($entradas[0]['costo_unitario'] ?? 0);
        if ($costo <= 0) {
            // Sin historial de costo en la bodega: el costo de la ficha (como Transferencias).
            $costo = (float) ($producto['costo_producto'] ?? 0);
        }
        return round($costo, 6);
    }

    private function metodoCosteo(?int $idEstablecimiento): string
    {
        if (!$idEstablecimiento) {
            return 'promedio';
        }
        if (!isset($this->metodos[$idEstablecimiento])) {
            $cfg = (new EmpresaRepository())->getEstablecimientoConfig($idEstablecimiento);
            $this->metodos[$idEstablecimiento] = (string) ($cfg['metodo_costeo'] ?? 'promedio') ?: 'promedio';
        }
        return $this->metodos[$idEstablecimiento];
    }

    // ────────────────────────────────────────────────────────────────
    // CERRAR
    // ────────────────────────────────────────────────────────────────

    /**
     * Cierra la orden: (opcional) consume lo teórico pendiente, fija MO/CIF, calcula el
     * costo real, lo reparte entre las salidas y les da entrada al inventario.
     *
     * $salidas: [id_linea => ['cantidad' => float, 'numero_lote' => ?, 'fecha_caducidad' => ?], …]
     * $recursos: [['tipo','descripcion','base','cantidad','tarifa'], …] (null = los de la orden)
     */
    public function cerrar(int $idOrden, array $salidas, float $mermaAnormal, ?array $recursos, bool $consumirPendiente,
                           string $fecha, string $token, int $idEmpresa, int $idUsuario, int $nivel): void
    {
        $cfg = $this->config->requerir($idEmpresa);
        $this->rules->validarFecha($fecha, 'de cierre');
        $this->validarPeriodo($fecha, $idEmpresa);

        $db = Database::getConnection();
        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }
        $this->ultimoRepetido = false;
        $guardado = new GuardadoUnicoService();
        try {
            if ($guardado->previo($token, $idEmpresa, 'produccion_cierre')) {
                if ($propia) {
                    $db->rollBack();
                }
                $this->ultimoRepetido = true;
                return;
            }
            $orden = $this->repo->getParaActualizar($idOrden, $idEmpresa);
            if (!$orden) {
                throw new \DomainException('La orden no existe.');
            }
            $this->rules->validarAbierta($orden, 'cerrar');
            $this->validarAccesoOrden($orden, $idEmpresa, $idUsuario, $nivel);
            if ($fecha < (string) $orden['fecha_inicio']) {
                throw new \DomainException('La fecha de cierre no puede ser anterior a la fecha de la orden.');
            }

            $this->cerrarInterno($orden, $salidas, $mermaAnormal, $recursos, $consumirPendiente, $fecha, $cfg, $idEmpresa, $idUsuario);

            $guardado->registrar($token, $idEmpresa, 'produccion_cierre', $idOrden, $orden['numero'], $idUsuario);
            if ($propia) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($propia && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($propia) {
            (new ProduccionAsientoService())->contabilizarSeguro($idOrden, $idEmpresa, $idUsuario);
        }
    }

    private function cerrarInterno(array $orden, array $salidasIn, float $mermaAnormal, ?array $recursosIn, bool $consumirPendiente,
                                   string $fecha, array $cfg, int $idEmpresa, int $idUsuario): void
    {
        $idOrden = (int) $orden['id'];

        // 1) Consumir lo teórico que falta (producción rápida, o "consumir lo pendiente").
        if ($consumirPendiente) {
            $pend = [];
            foreach ($this->repo->getLineas($idOrden, $idEmpresa) as $l) {
                if ($l['rol'] === 'insumo') {
                    $falta = round((float) $l['cantidad_teorica'] - (float) $l['cantidad_real'], 6);
                    if ($falta > self::EPS) {
                        $pend[] = ['id_linea' => (int) $l['id'], 'cantidad' => $falta];
                    }
                }
            }
            if ($pend !== []) {
                $this->consumirInterno($orden, $pend, $fecha, $cfg, $idEmpresa, $idUsuario);
                $orden['estado'] = 'en_proceso';
            }
        }

        $lineas = $this->repo->getLineas($idOrden, $idEmpresa);
        $costoMp = 0.0;
        $hayConsumo = false;
        foreach ($lineas as $l) {
            if ($l['rol'] === 'insumo') {
                $costoMp += (float) $l['costo_real'];
                $hayConsumo = $hayConsumo || (float) $l['cantidad_real'] > 0;
            }
        }
        if (!$hayConsumo) {
            throw new \DomainException('La orden no tiene consumos: registre lo que se consumió o marque «Consumir lo pendiente».');
        }
        $costoMp = round($costoMp, 2);

        // 2) Salidas reales.
        $salidas = [];
        $principal = 0.0;
        foreach ($lineas as $l) {
            if ($l['rol'] === 'insumo') {
                continue;
            }
            $in = $salidasIn[(int) $l['id']] ?? $salidasIn[(string) $l['id']] ?? null;
            $cant = $in !== null ? round((float) str_replace(',', '.', (string) ($in['cantidad'] ?? 0)), 6) : 0.0;
            if ($cant < 0) {
                throw new \DomainException('Las cantidades producidas no pueden ser negativas.');
            }
            if ($l['rol'] === 'principal') {
                $principal = $cant;
            }
            $salidas[] = [
                'key' => (string) $l['id'], 'linea' => $l, 'rol' => $l['rol'], 'cantidad' => $cant,
                'precio' => (float) ($l['precio_base'] ?? 0), 'reparto_pct' => $l['reparto_pct'],
                'vnr' => $l['valor_neto_realizable'],
                'lote' => trim((string) ($in['numero_lote'] ?? '')) ?: (string) $orden['lote'],
                'caducidad' => trim((string) ($in['fecha_caducidad'] ?? '')) ?: ($orden['fecha_caducidad'] ?: null),
            ];
        }
        if ($principal <= 0) {
            throw new \DomainException('Indique cuánto se produjo del producto principal.');
        }
        if ($mermaAnormal < 0) {
            throw new \DomainException('La merma anormal no puede ser negativa.');
        }

        // 3) Recursos (MO / máquina / CIF): los editados en el cierre o los de la orden.
        $base = (float) ($this->formulas->getPorId((int) $orden['id_formula'], $idEmpresa)['cantidad_base'] ?? $orden['cantidad_planificada']);
        $recursos = $recursosIn ?? array_map(fn($r) => [
            'tipo' => $r['tipo'], 'descripcion' => $r['descripcion'], 'base' => $r['base'],
            'cantidad' => (float) $r['cantidad'], 'tarifa' => (float) $r['tarifa'],
        ], $this->repo->getRecursos($idOrden, $idEmpresa));
        $this->repo->borrarRecursos($idOrden, $idEmpresa, $idUsuario);
        $mo = 0.0;
        $cif = 0.0;
        foreach ($recursos as $i => $r) {
            $tipo = in_array($r['tipo'] ?? '', ['mo', 'maquina', 'cif'], true) ? $r['tipo'] : 'mo';
            $baseR = in_array($r['base'] ?? '', ['unidad', 'lote', 'hora', 'pct_mp'], true) ? $r['base'] : 'unidad';
            $r = ['tipo' => $tipo, 'base' => $baseR, 'descripcion' => $r['descripcion'] ?? null,
                  'cantidad' => max(0.0, (float) ($r['cantidad'] ?? 0)), 'tarifa' => max(0.0, (float) ($r['tarifa'] ?? 0))];
            $r['importe'] = ProduccionCosteoService::importeRecurso($r, $base, $principal, $costoMp);
            $r['orden'] = $i + 1;
            $this->repo->insertarRecurso($r, $idOrden, $idEmpresa, $idUsuario);
            if ($tipo === 'mo') {
                $mo += $r['importe'];
            } else {
                $cif += $r['importe'];
            }
        }
        $otros = round($this->repo->getCostosImputados($idOrden, $idEmpresa), 2);

        // 4) Costeo y reparto.
        $metodo = (string) ($orden['metodo_reparto'] ?: $cfg['metodo_reparto'] ?: 'valor_venta');
        $costeo = ProduccionCosteoService::costearCierre($costoMp, $mo, $cif, $otros, $salidas, $metodo, $mermaAnormal);

        // 5) Entradas al inventario (desperdicio: solo cantidad).
        $destino = (int) $orden['id_bodega_destino'];
        $ids = array_values(array_unique(array_filter(array_map(
            fn($s) => $s['rol'] !== 'desperdicio' && $s['cantidad'] > 0 ? (int) $s['linea']['id_producto'] : 0,
            $salidas
        ))));
        sort($ids);
        foreach ($ids as $idP) {
            $this->inv->lockStock($idP, $destino, $idEmpresa);
        }
        $grupo = $this->repo->siguienteGrupo($idOrden, $idEmpresa);
        $fechaMov = $this->fechaMovimiento($fecha);
        $productos = $this->formulas->getProductos($ids, $idEmpresa);

        foreach ($salidas as $s) {
            $c = $costeo['salidas'][$s['key']] ?? ['costo_total' => 0.0, 'costo_unitario' => 0.0];
            $this->repo->fijarRealLinea((int) $s['linea']['id'], $s['cantidad'], $c['costo_total'], $c['costo_unitario'], $idEmpresa, $idUsuario);
            if ($s['rol'] === 'desperdicio' || $s['cantidad'] <= 0 || empty($s['linea']['id_producto'])) {
                continue;
            }
            $idP = (int) $s['linea']['id_producto'];
            $p = $productos[$idP] ?? [];
            $stock = $this->inv->getStockActual($idP, $destino, $idEmpresa);
            $idKardex = $this->inv->registrarMovimiento([
                'id_empresa'       => $idEmpresa,
                'id_producto'      => $idP,
                'id_bodega'        => $destino,
                'tipo_movimiento'  => 'entrada',
                'referencia_tipo'  => ProduccionOrdenRepository::REF_ENTREGA,
                'referencia_id'    => $idOrden,
                'fecha_movimiento' => $fechaMov,
                'cantidad'         => $s['cantidad'],
                'costo_unitario'   => $c['costo_unitario'],
                'costo_total'      => $c['costo_total'],
                'stock_anterior'   => $stock,
                'stock_posterior'  => round($stock + $s['cantidad'], 6),
                'numero_lote'      => $s['lote'] !== '' ? mb_substr($s['lote'], 0, 50) : null,
                'fecha_caducidad'  => $s['caducidad'],
                'id_medida'        => !empty($p['id_medida']) ? (int) $p['id_medida'] : null,
                'observaciones'    => "Entrega de producción {$orden['numero']}",
                'id_usuario'       => $idUsuario,
            ]);
            $this->inv->actualizarStock($idP, $destino, $idEmpresa, round($stock + $s['cantidad'], 6), $idUsuario);
            $this->repo->insertarMovimiento([
                'id_orden' => $idOrden, 'id_linea' => (int) $s['linea']['id'], 'grupo' => $grupo, 'tipo' => 'entrega',
                'id_producto' => $idP, 'id_bodega' => $destino, 'cantidad' => $s['cantidad'],
                'numero_lote' => $s['lote'], 'fecha_caducidad' => $s['caducidad'],
                'costo_unitario' => $c['costo_unitario'], 'costo_total' => $c['costo_total'],
                'id_kardex' => $idKardex, 'fecha' => $fecha,
            ], $idEmpresa, $idUsuario);
        }

        $this->repo->actualizarCabecera($idOrden, [
            'estado'             => 'cerrada',
            'fecha_fin'          => $fecha,
            'cantidad_producida' => $principal,
            'cantidad_merma'     => round($mermaAnormal, 6),
            'metodo_reparto'     => $metodo,
            'costo_materiales'   => $costoMp,
            'costo_mano_obra'    => round($mo, 2),
            'costo_cif'          => round($cif, 2),
            'costo_otros'        => $otros,
            'valor_subproductos' => $costeo['subproductos'],
            'costo_merma'        => $costeo['merma'],
            'costo_total'        => round($costeo['total'] - $costeo['merma'], 2),
        ], $idEmpresa, $idUsuario);

        $this->log->registrar($idUsuario, $idEmpresa, 'CERRAR_ORDEN_PRODUCCION', 'produccion_ordenes', $idOrden,
            ['estado' => $orden['estado']],
            ['estado' => 'cerrada', 'fecha_fin' => $fecha, 'producida' => $principal, 'merma' => $mermaAnormal,
             'materiales' => $costoMp, 'mano_obra' => round($mo, 2), 'cif' => round($cif, 2), 'costeo' => $costeo]);
    }

    // ────────────────────────────────────────────────────────────────
    // PRODUCCIÓN RÁPIDA
    // ────────────────────────────────────────────────────────────────

    /**
     * Un solo paso: crea la orden, consume lo teórico (lote que vence primero) y la cierra
     * con lo producido. Todo o nada, en una transacción.
     *
     * @param array $salidas por rol/posición: [['rol'=>…, 'orden'=>n, 'cantidad'=>…], …]; vacío = teórico
     */
    public function produccionRapida(array $in, array $salidas, float $mermaAnormal, int $idEmpresa, int $idUsuario, int $nivel): int
    {
        $cfg = $this->config->requerir($idEmpresa);
        $in['tipo'] = 'rapida';
        $d = $this->normalizarCreacion($in, $cfg);
        $formula = $d['id_formula'] > 0 ? $this->formulas->getPorId($d['id_formula'], $idEmpresa) : null;
        $this->rules->validarCreacion($d, $formula);
        $this->validarBodegas($d, $idEmpresa, $idUsuario, $nivel);
        $this->validarPeriodo($d['fecha_inicio'], $idEmpresa);

        $db = Database::getConnection();
        $db->beginTransaction();
        $this->ultimoRepetido = false;
        $guardado = new GuardadoUnicoService();
        try {
            if ($previo = $guardado->previo($d['token_guardado'], $idEmpresa, 'produccion_ordenes')) {
                $db->rollBack();
                $this->ultimoRepetido = true;
                return $previo['id_registro'];
            }
            $id = $this->insertarOrden($d, $formula, $idEmpresa, $idUsuario);
            $orden = $this->repo->getParaActualizar($id, $idEmpresa);

            // Salidas: lo indicado por el usuario o, si no, lo teórico.
            $porLinea = [];
            foreach ($this->repo->getLineas($id, $idEmpresa) as $l) {
                if ($l['rol'] === 'insumo') {
                    continue;
                }
                $cant = (float) $l['cantidad_teorica'];
                foreach ($salidas as $s) {
                    if (($s['orden'] ?? null) !== null && (int) $s['orden'] === (int) $l['orden']) {
                        $cant = (float) str_replace(',', '.', (string) $s['cantidad']);
                    }
                }
                $porLinea[(int) $l['id']] = ['cantidad' => $cant];
            }

            $this->cerrarInterno($orden, $porLinea, $mermaAnormal, null, true, $d['fecha_inicio'], $cfg, $idEmpresa, $idUsuario);

            $guardado->registrar($d['token_guardado'], $idEmpresa, 'produccion_ordenes', $id, $orden['numero'], $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        (new ProduccionAsientoService())->contabilizarSeguro($id, $idEmpresa, $idUsuario);
        return $id;
    }

    // ────────────────────────────────────────────────────────────────
    // ANULAR / ELIMINAR
    // ────────────────────────────────────────────────────────────────

    /**
     * Anula la orden: revierte primero las entregas (si lo producido ya se vendió o se
     * consumió, el stock quedaría negativo y se rechaza) y después los consumos; anula
     * sus asientos en la misma transacción.
     */
    public function anular(int $idOrden, int $idEmpresa, int $idUsuario, int $nivel): void
    {
        $this->config->requerir($idEmpresa);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $orden = $this->repo->getParaActualizar($idOrden, $idEmpresa);
            if (!$orden) {
                throw new \DomainException('La orden no existe.');
            }
            if ($orden['estado'] === 'anulada') {
                throw new \DomainException('La orden ya está anulada.');
            }
            $this->validarAccesoOrden($orden, $idEmpresa, $idUsuario, $nivel);

            $movs = $this->repo->getMovimientos($idOrden, $idEmpresa);
            foreach ($movs as $m) {
                $this->validarPeriodo((string) $m['fecha'], $idEmpresa);
            }
            usort($movs, fn($a, $b) => [$a['tipo'] === 'entrega' ? 0 : 1, -(int) $a['id']] <=> [$b['tipo'] === 'entrega' ? 0 : 1, -(int) $b['id']]);

            $inv = $this->inventarioService();
            foreach ($movs as $m) {
                if (empty($m['id_kardex'])) {
                    continue;
                }
                try {
                    $inv->eliminarMovimiento((int) $m['id_kardex'], $idEmpresa, $idUsuario, true);
                } catch (\Throwable $e) {
                    if ($m['tipo'] === 'entrega') {
                        throw new \DomainException(
                            "No se puede anular: «{$m['producto_nombre']}» producido en esta orden ya se vendió o se usó "
                            . '(' . $e->getMessage() . '). Reverse primero los documentos que lo consumieron.'
                        );
                    }
                    throw $e;
                }
            }

            (new ProduccionAsientoService())->anularAsientos($orden, $movs, $idEmpresa, $idUsuario);

            $this->repo->anularMovimientos($idOrden, $idEmpresa, $idUsuario);
            $this->repo->actualizarCabecera($idOrden, ['estado' => 'anulada', 'asiento_aviso' => null], $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ANULAR_ORDEN_PRODUCCION', 'produccion_ordenes', $idOrden,
                ['estado' => $orden['estado'], 'numero' => $orden['numero']], ['estado' => 'anulada']);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Eliminación lógica: solo una orden sin movimientos vigentes (planificada o anulada). */
    public function eliminar(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        $orden = $this->repo->getPorId($idOrden, $idEmpresa);
        if (!$orden) {
            throw new \DomainException('La orden no existe.');
        }
        if (!in_array($orden['estado'], ['liberada', 'anulada'], true) || $this->repo->getMovimientos($idOrden, $idEmpresa) !== []) {
            throw new \DomainException('Solo se elimina una orden planificada sin consumos o una orden anulada. Anúlela primero.');
        }
        $this->repo->eliminar($idOrden, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR_ORDEN_PRODUCCION', 'produccion_ordenes', $idOrden, $orden, null);
    }

    // ────────────────────────────────────────────────────────────────
    // APOYO
    // ────────────────────────────────────────────────────────────────

    private function validarAccesoOrden(array $orden, int $idEmpresa, int $idUsuario, int $nivel): void
    {
        $bodegas = $this->bodegas();
        foreach (['id_bodega_insumos', 'id_bodega_destino'] as $campo) {
            if (!empty($orden[$campo]) && !$bodegas->validarAccesoUsuario($idUsuario, (int) $orden[$campo], $idEmpresa, $nivel)) {
                throw new \DomainException('No tiene acceso a una de las bodegas de esta orden.');
            }
        }
    }

    /** Fecha del documento con la hora actual (el kardex ordena por fecha y hora). */
    private function fechaMovimiento(string $fecha): string
    {
        return $fecha . ' ' . date('H:i:s');
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
    }
}
