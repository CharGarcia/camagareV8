<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\ProduccionFormulaRepository;
use App\Rules\modulos\ProduccionFormulaRules;
use App\Services\LogSistemaService;

/**
 * Fórmulas de producción: alta, edición, versiones, activación y costo estimado.
 *
 * Una fórmula que ya se usó en una orden no se edita (las órdenes guardan su copia, pero
 * el historial debe poder explicar con qué receta se fabricó): se crea otra versión.
 */
class ProduccionFormulaService
{
    private ProduccionFormulaRepository $repo;
    private ProduccionFormulaRules $rules;
    private LogSistemaService $log;
    private ProduccionConfiguracionService $config;

    public function __construct(
        ?ProduccionFormulaRepository $repo = null,
        ?ProduccionFormulaRules $rules = null,
        ?LogSistemaService $log = null,
        ?ProduccionConfiguracionService $config = null
    ) {
        $this->repo   = $repo   ?? new ProduccionFormulaRepository();
        $this->rules  = $rules  ?? new ProduccionFormulaRules();
        $this->log    = $log    ?? new LogSistemaService();
        $this->config = $config ?? ProduccionConfiguracionService::crear();
    }

    public static function crear(): self
    {
        return new self();
    }

    public function repo(): ProduccionFormulaRepository
    {
        return $this->repo;
    }

    public function config(): ProduccionConfiguracionService
    {
        return $this->config;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro, string $estado = ''): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $idUsuarioFiltro, $estado);
    }

    /** Fórmula completa para el modal, con su costo estimado por la cantidad base. */
    public function getCompleta(int $id, int $idEmpresa): ?array
    {
        $f = $this->repo->getPorId($id, $idEmpresa);
        if (!$f) {
            return null;
        }
        $f['lineas']   = $this->repo->getLineas($id, $idEmpresa);
        $f['recursos'] = $this->repo->getRecursos($id, $idEmpresa);
        $f['usada_en_ordenes']       = $this->repo->tieneOrdenes($id, $idEmpresa);
        $f['tiene_componentes_venta'] = $this->repo->tieneComponentesVenta((int) $f['id_producto'], $idEmpresa);
        return $f;
    }

    // ────────────────────────────────────────────────────────────────
    // GUARDAR
    // ────────────────────────────────────────────────────────────────

    /** Datos del formulario → estructura validable. */
    public function normalizar(array $in): array
    {
        $num = static fn($v): float => is_numeric(str_replace(',', '.', (string) $v)) ? (float) str_replace(',', '.', (string) $v) : 0.0;
        $numOpc = static fn($v): ?float => ($v === null || $v === '') ? null : (float) str_replace(',', '.', (string) $v);
        $txt = static fn($v): string => trim((string) ($v ?? ''));

        $lineasIn = $in['lineas'] ?? [];
        if (is_string($lineasIn)) {
            $lineasIn = json_decode($lineasIn, true) ?: [];
        }
        $recursosIn = $in['recursos'] ?? [];
        if (is_string($recursosIn)) {
            $recursosIn = json_decode($recursosIn, true) ?: [];
        }

        $d = [
            'codigo'         => mb_strtoupper($txt($in['codigo'] ?? '')),
            'nombre'         => $txt($in['nombre'] ?? ''),
            'id_producto'    => (int) ($in['id_producto'] ?? 0),
            'cantidad_base'  => $num($in['cantidad_base'] ?? 0),
            'vida_util_dias' => ($in['vida_util_dias'] ?? '') === '' ? null : (int) $in['vida_util_dias'],
            'metodo_reparto' => ($m = $txt($in['metodo_reparto'] ?? '')) !== '' && isset(ProduccionConfiguracionService::METODOS_REPARTO[$m]) ? $m : null,
            'instrucciones'  => $txt($in['instrucciones'] ?? '') ?: null,
            'observaciones'  => $txt($in['observaciones'] ?? '') ?: null,
            'lineas'         => [],
            'recursos'       => [],
        ];

        // La salida principal es siempre la de la cabecera (producto + cantidad que rinde).
        $d['lineas'][] = [
            'rol' => 'principal', 'id_producto' => $d['id_producto'], 'descripcion' => null,
            'modo_cantidad' => 'proporcional', 'cantidad' => $d['cantidad_base'], 'merma_pct' => 0,
            'exige_lote' => false, 'reparto_pct' => $numOpc($in['reparto_pct_principal'] ?? null),
            'valor_neto_realizable' => null, 'observaciones' => null,
        ];

        foreach ($lineasIn as $l) {
            $rol = $txt($l['rol'] ?? '');
            if ($rol === 'principal') {
                continue;
            }
            $d['lineas'][] = [
                'rol'                   => $rol,
                'id_producto'           => (int) ($l['id_producto'] ?? 0) ?: null,
                'descripcion'           => $txt($l['descripcion'] ?? '') ?: null,
                'modo_cantidad'         => $txt($l['modo_cantidad'] ?? 'proporcional') ?: 'proporcional',
                'cantidad'              => $num($l['cantidad'] ?? 0),
                'merma_pct'             => $rol === 'insumo' ? $num($l['merma_pct'] ?? 0) : 0,
                'exige_lote'            => !empty($l['exige_lote']) && $l['exige_lote'] !== 'false',
                'reparto_pct'           => $rol === 'coproducto' ? $numOpc($l['reparto_pct'] ?? null) : null,
                'valor_neto_realizable' => $rol === 'subproducto' ? $numOpc($l['valor_neto_realizable'] ?? null) : null,
                'observaciones'         => $txt($l['observaciones'] ?? '') ?: null,
            ];
        }

        foreach ($recursosIn as $r) {
            if ($num($r['tarifa'] ?? 0) <= 0 && $txt($r['descripcion'] ?? '') === '') {
                continue; // fila vacía
            }
            $d['recursos'][] = [
                'tipo'        => $txt($r['tipo'] ?? 'mo'),
                'descripcion' => $txt($r['descripcion'] ?? '') ?: null,
                'base'        => $txt($r['base'] ?? 'unidad'),
                'cantidad'    => $num($r['cantidad'] ?? 1),
                'tarifa'      => $num($r['tarifa'] ?? 0),
            ];
        }

        return $d;
    }

    /** Crea (id = 0) o actualiza una fórmula en borrador o vigente sin órdenes. */
    public function guardar(int $id, array $in, int $idEmpresa, int $idUsuario): int
    {
        $cfg = $this->config->requerir($idEmpresa);
        $d   = $this->normalizar($in);

        $ids = array_filter(array_map(fn($l) => (int) ($l['id_producto'] ?? 0), $d['lineas']));
        $productos = $this->repo->getProductos($ids, $idEmpresa);
        $this->rules->validar($d, $productos, $d['metodo_reparto'] ?? (string) $cfg['metodo_reparto']);

        $previo = null;
        if ($id > 0) {
            $previo = $this->getCompleta($id, $idEmpresa);
            if (!$previo) {
                throw new \DomainException('La fórmula no existe.');
            }
            if ($previo['usada_en_ordenes']) {
                throw new \DomainException('Esta versión ya se usó en órdenes de producción y no se puede modificar. Use «Nueva versión».');
            }
            if ((int) $previo['id_producto'] !== $d['id_producto'] && $previo['estado'] === 'vigente') {
                throw new \DomainException('Una fórmula vigente no puede cambiar de producto. Inactívela primero o cree otra fórmula.');
            }
        }

        $version = $previo ? (int) $previo['version'] : $this->repo->siguienteVersion($d['id_producto'], $idEmpresa);
        if ($this->repo->existeCodigo($d['codigo'], $idEmpresa, $id, $version)) {
            throw new \DomainException("Ya existe otra fórmula con el código «{$d['codigo']}» en la versión {$version}.");
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            if ($previo) {
                $this->repo->actualizarCabecera($id, $d, $idEmpresa, $idUsuario);
            } else {
                $id = $this->repo->insertarCabecera($d + ['version' => $version, 'estado' => 'borrador'], $idEmpresa, $idUsuario);
            }
            $this->repo->reemplazarDetalle($id, $d['lineas'], $d['recursos'], $idEmpresa, $idUsuario);

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                $previo ? 'ACTUALIZAR_FORMULA_PRODUCCION' : 'CREAR_FORMULA_PRODUCCION',
                'produccion_formulas',
                $id,
                $previo,
                $d
            );
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        return $id;
    }

    /** Copia una fórmula como nueva versión en borrador del mismo producto. */
    public function nuevaVersion(int $id, int $idEmpresa, int $idUsuario): int
    {
        $this->config->requerir($idEmpresa);
        $f = $this->getCompleta($id, $idEmpresa);
        if (!$f) {
            throw new \DomainException('La fórmula no existe.');
        }
        $version = $this->repo->siguienteVersion((int) $f['id_producto'], $idEmpresa);

        $lineas = array_map(fn($l) => [
            'rol' => $l['rol'], 'id_producto' => $l['id_producto'] ? (int) $l['id_producto'] : null,
            'descripcion' => $l['descripcion'], 'modo_cantidad' => $l['modo_cantidad'],
            'cantidad' => (float) $l['cantidad'], 'merma_pct' => (float) $l['merma_pct'],
            'exige_lote' => !empty($l['exige_lote']), 'reparto_pct' => $l['reparto_pct'],
            'valor_neto_realizable' => $l['valor_neto_realizable'], 'observaciones' => $l['observaciones'],
        ], $f['lineas']);
        $recursos = array_map(fn($r) => [
            'tipo' => $r['tipo'], 'descripcion' => $r['descripcion'], 'base' => $r['base'],
            'cantidad' => (float) $r['cantidad'], 'tarifa' => (float) $r['tarifa'],
        ], $f['recursos']);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $nuevo = $this->repo->insertarCabecera([
                'codigo' => $f['codigo'], 'nombre' => $f['nombre'], 'id_producto' => (int) $f['id_producto'],
                'cantidad_base' => (float) $f['cantidad_base'], 'vida_util_dias' => $f['vida_util_dias'],
                'metodo_reparto' => $f['metodo_reparto'], 'instrucciones' => $f['instrucciones'],
                'observaciones' => $f['observaciones'], 'version' => $version, 'estado' => 'borrador',
            ], $idEmpresa, $idUsuario);
            $this->repo->reemplazarDetalle($nuevo, $lineas, $recursos, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'NUEVA_VERSION_FORMULA_PRODUCCION', 'produccion_formulas', $nuevo,
                ['id_origen' => $id, 'version_origen' => $f['version']], ['version' => $version]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        return $nuevo;
    }

    /** Deja la fórmula vigente (y la versión vigente anterior del producto, inactiva). */
    public function activar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->config->requerir($idEmpresa);
        $f = $this->getCompleta($id, $idEmpresa);
        if (!$f) {
            throw new \DomainException('La fórmula no existe.');
        }
        if ($f['estado'] === 'vigente') {
            return;
        }
        $this->rules->validarActivacion(
            $f,
            $f['tiene_componentes_venta'],
            $this->generaCiclo($f, $idEmpresa)
        );

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            // Candado por producto: dos activaciones simultáneas no dejan dos vigentes.
            $db->prepare("SELECT pg_advisory_xact_lock(hashtext('produccion_formula_vigente:' || :e || ':' || :p))")
               ->execute([':e' => $idEmpresa, ':p' => (int) $f['id_producto']]);
            $this->repo->inactivarOtrasVigentes((int) $f['id_producto'], $id, $idEmpresa, $idUsuario);
            $this->repo->cambiarEstado($id, 'vigente', $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTIVAR_FORMULA_PRODUCCION', 'produccion_formulas', $id,
                ['estado' => $f['estado']], ['estado' => 'vigente']);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function inactivar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $f = $this->repo->getPorId($id, $idEmpresa);
        if (!$f) {
            throw new \DomainException('La fórmula no existe.');
        }
        $this->repo->cambiarEstado($id, 'inactiva', $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'INACTIVAR_FORMULA_PRODUCCION', 'produccion_formulas', $id,
            ['estado' => $f['estado']], ['estado' => 'inactiva']);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $f = $this->repo->getPorId($id, $idEmpresa);
        if (!$f) {
            throw new \DomainException('La fórmula no existe.');
        }
        if ($this->repo->tieneOrdenes($id, $idEmpresa)) {
            throw new \DomainException('La fórmula ya se usó en órdenes de producción: no se puede eliminar. Puede inactivarla.');
        }
        $this->repo->eliminar($id, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR_FORMULA_PRODUCCION', 'produccion_formulas', $id, $f, null);
    }

    // ────────────────────────────────────────────────────────────────
    // COSTO ESTIMADO
    // ────────────────────────────────────────────────────────────────

    /**
     * Costo estimado para fabricar $cantidad con los costos actuales de los insumos en la
     * bodega de insumos (costo promedio; si no hay historial, el costo de la ficha).
     * Es solo una referencia: el costo real sale de los consumos al cerrar la orden.
     */
    public function estimar(array $formula, float $cantidad, ?int $idBodega, int $idEmpresa): array
    {
        $inv = new InventarioRepository();
        $base = (float) $formula['cantidad_base'];
        $lineas = [];
        $mp = 0.0;
        foreach ($formula['lineas'] as $l) {
            $teorica = ProduccionCosteoService::cantidadTeorica($l, $base, $cantidad);
            $unit = 0.0;
            if ($l['rol'] === 'insumo' && !empty($l['id_producto'])) {
                $unit = $idBodega ? $inv->getCostoPromedio((int) $l['id_producto'], $idBodega, $idEmpresa) : 0.0;
                if ($unit <= 0) {
                    $unit = (float) ($l['costo_producto'] ?? 0);
                }
                $mp += $teorica * $unit;
            }
            $lineas[] = ['id' => $l['id'] ?? null, 'rol' => $l['rol'], 'cantidad' => $teorica, 'costo_unitario' => round($unit, 6),
                         'costo_total' => round($teorica * $unit, 2)];
        }
        $mp = round($mp, 2);
        $mo = 0.0;
        $cif = 0.0;
        foreach ($formula['recursos'] as $r) {
            $v = ProduccionCosteoService::importeRecurso($r, $base, $cantidad, $mp);
            if ($r['tipo'] === 'mo') {
                $mo += $v;
            } else {
                $cif += $v;
            }
        }
        $total = round($mp + $mo + $cif, 2);
        return [
            'cantidad'       => $cantidad,
            'materiales'     => $mp,
            'mano_obra'      => round($mo, 2),
            'cif'            => round($cif, 2),
            'total'          => $total,
            'costo_unitario' => $cantidad > 0 ? round($total / $cantidad, 6) : 0,
            'lineas'         => $lineas,
        ];
    }

    /** ¿Activar esta fórmula crea un ciclo con las vigentes? */
    private function generaCiclo(array $formula, int $idEmpresa): bool
    {
        $grafo = $this->repo->getGrafoVigentes($idEmpresa);
        $principal = (int) $formula['id_producto'];
        $grafo[$principal] = array_values(array_filter(array_map(
            fn($l) => $l['rol'] === 'insumo' ? (int) $l['id_producto'] : 0,
            $formula['lineas']
        )));

        // ¿Desde algún insumo se llega de vuelta al producto principal?
        $pila = $grafo[$principal];
        $visto = [];
        while ($pila) {
            $n = array_pop($pila);
            if ($n === $principal) {
                return true;
            }
            if (isset($visto[$n])) {
                continue;
            }
            $visto[$n] = true;
            foreach ($grafo[$n] ?? [] as $m) {
                $pila[] = $m;
            }
        }
        return false;
    }
}
