<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CondominioCobroRepository;
use App\repositories\modulos\CondominioCuotaExtraRepository;
use App\Rules\modulos\CondominioCuotaExtraRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * Cuotas extraordinarias con meta pagadas a plazos (Configuración de condominios → pestaña
 * Cuotas extraordinarias). Diseño: docs/diseno/condominios-cuotas-extraordinarias.md.
 *
 *  - Una cuota extraordinaria (condominios_aportes, tipo 'meta') tiene una meta y un reparto
 *    (partes iguales o por % de alícuota). Al ACTIVAR los planes, cada inmueble recibe su plan
 *    (asignado − anticipo = saldo ÷ plazo) y TODAS sus cuotas como cargos pendientes.
 *  - El anticipo de clientes se APLICA: documento de abono inicial + ingreso que lo cobra con la
 *    forma de cobro ANTICIPO (cancela el pasivo). Bajo candado del anticipo del cliente.
 *  - Cada cuota sale una sola vez: «junto» la agrega Suscripciones al documento de la alícuota
 *    (SuscripcionFacturacionService → lineasDocumento/confirmarDocumento); «aparte» la emite la
 *    emisión en bloque (forma_valor 'cargos'). En ambos casos se marca en la MISMA transacción
 *    del documento.
 *  - Lo cobrado se deriva de Cuentas por Cobrar (no se guarda): en un documento compartido con
 *    la alícuota, lo abonado cubre primero las líneas que no son cuotas.
 */
class CondominioCuotaExtraService
{
    private const MODULO_GUARDADO = 'condominios_cuota_extra';
    private const MODULO_GUARDADO_EMISION = 'condominios_cuota_extra_emision';
    private const TABLA = 'condominios_aportes';

    public function __construct(
        private CondominioCuotaExtraRepository $repo,
        private CondominioCuotaExtraRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CondominioCuotaExtraRepository(), new CondominioCuotaExtraRules(), new LogSistemaService());
    }

    public function repo(): CondominioCuotaExtraRepository
    {
        return $this->repo;
    }

    public function instalado(): bool
    {
        return $this->repo->instalado();
    }

    private function exigirActivo(int $idEmpresa): void
    {
        if (!$this->repo->instalado()) {
            throw new \DomainException('Falta aplicar database/migrations/20261117_condominios_cuotas_extraordinarias.sql.');
        }
        if (!CondominioService::activo($idEmpresa)) {
            throw new \DomainException('Guarde primero la configuración del condominio.');
        }
    }

    private function db(): \PDO
    {
        return Database::getConnection();
    }

    // ── Catálogos y listado ─────────────────────────────────────────────────

    public function opciones(int $idEmpresa): array
    {
        return [
            'instalado' => $this->repo->instalado(),
            'series'    => (new \App\repositories\modulos\SuscripcionesRepository())->getSeriesActivas($idEmpresa),
            'formas_anticipo' => $this->repo->instalado() ? $this->repo->getFormasAnticipo($idEmpresa) : [],
            'calculos'  => CondominioCuotaExtraRules::CALCULOS,
            'donde'     => CondominioCuotaExtraRules::DONDE,
            'pagadores' => CondominioCuotaExtraRules::PAGADORES,
        ];
    }

    public function listar(int $idEmpresa, string $buscar = '', array $orden = []): array
    {
        return $this->repo->instalado() ? $this->repo->listar($idEmpresa, $buscar, $orden) : [];
    }

    private static function plazos(array $aporte): array
    {
        $p = $aporte['plazos_permitidos'] ?? [];
        return array_map('intval', is_array($p) ? $p : (json_decode((string) $p, true) ?: []));
    }

    // ── Crear / editar ──────────────────────────────────────────────────────

    public function guardarAporte(array $data, ?int $id, int $idEmpresa, int $idUsuario): int
    {
        $this->exigirActivo($idEmpresa);
        $d = $this->rules->normalizarAporte($data);
        $cobro = new CondominioCobroRepository();
        $prod = $cobro->getProductoCobro($d['id_producto'], $idEmpresa);
        if (!$prod) {
            throw new \InvalidArgumentException('El concepto elegido no existe en Productos.|#cex_producto_txt');
        }
        if ((string) ($prod['tipo_produccion'] ?? '') !== '02') {
            throw new \InvalidArgumentException("«{$prod['nombre']}» es un bien: el concepto debe ser un servicio.|#cex_producto_txt");
        }
        if (!$cobro->getSerie($d['id_punto_emision'], $idEmpresa)) {
            throw new \InvalidArgumentException('La serie elegida no existe o está inactiva.|#cex_id_punto_emision');
        }
        if ($d['id_forma_anticipo'] > 0 && !in_array($d['id_forma_anticipo'], array_map(fn($f) => (int) $f['id'], $this->repo->getFormasAnticipo($idEmpresa)), true)) {
            throw new \InvalidArgumentException('La forma de cobro de anticipos no es válida.|#cex_id_forma_anticipo');
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            if ($id === null) {
                $d['vigente_desde'] = substr($d['primer_periodo'], 0, 4) . '-' . substr($d['primer_periodo'], 4, 2) . '-01';
                $id = $this->repo->insertAporte($idEmpresa, $d, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'Crear cuota extraordinaria', self::TABLA, $id, null, $d);
            } else {
                $antes = $this->repo->getAporte($id, $idEmpresa, true);
                if (!$antes) {
                    throw new \DomainException('La cuota extraordinaria no existe.');
                }
                if ($antes['estado'] !== 'activo') {
                    throw new \DomainException('La cuota extraordinaria está cerrada: no se puede modificar.');
                }
                if ($this->repo->getPlanes($id, $idEmpresa)) {
                    // Con planes ya activados, la meta y el reparto quedan fijos (las cuotas ya existen).
                    foreach (['meta_total', 'calculo', 'primer_periodo', 'plazo_defecto'] as $k) {
                        $d[$k] = $k === 'meta_total' ? (float) $antes[$k] : ($k === 'plazo_defecto' ? (int) $antes[$k] : $antes[$k]);
                    }
                    $d['plazos_permitidos'] = array_values(array_unique(array_merge(self::plazos($antes), $d['plazos_permitidos'])));
                    sort($d['plazos_permitidos']);
                    if ($d['id_producto'] !== (int) $antes['id_producto']) {
                        throw new \InvalidArgumentException('El concepto no se puede cambiar: ya hay cuotas creadas con él.|#cex_producto_txt');
                    }
                    if ($d['donde_cobro'] !== $antes['donde_cobro']) {
                        $db->prepare("UPDATE condominios_cargos SET donde_cobro = :d, updated_at = CURRENT_TIMESTAMP
                                       WHERE id_aporte = :a AND estado = 'pendiente' AND subtipo = 'cuota' AND eliminado = false")
                           ->execute([':d' => $d['donde_cobro'], ':a' => $id]);
                    }
                }
                $this->repo->updateAporte($id, $idEmpresa, $d, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'Modificar cuota extraordinaria', self::TABLA, $id, $antes, $d);
            }
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Cierra la cuota extraordinaria: anula lo que falta emitir. Lo emitido se conserva. */
    public function cerrarAporte(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $a = $this->repo->getAporte($id, $idEmpresa, true);
            if (!$a) {
                throw new \DomainException('La cuota extraordinaria no existe.');
            }
            $anuladas = 0;
            foreach ($this->repo->getPlanes($id, $idEmpresa) as $pl) {
                $anuladas += $this->repo->anularPendientesPlan((int) $pl['id'], $idUsuario, 'Cuota extraordinaria cerrada.');
                if (in_array($pl['estado'], ['pendiente', 'en_curso'], true)) {
                    $this->repo->actualizarPlan((int) $pl['id'], ['estado' => 'cancelado'], $idUsuario);
                }
            }
            $this->repo->setEstadoAporte($id, $idEmpresa, 'cerrado', $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Cerrar cuota extraordinaria', self::TABLA, $id, ['estado' => $a['estado']], ['estado' => 'cerrado', 'cuotas_anuladas' => $anuladas]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function eliminarAporte(int $id, int $idEmpresa, int $idUsuario): void
    {
        $a = $this->repo->getAporte($id, $idEmpresa);
        if (!$a) {
            throw new \DomainException('La cuota extraordinaria no existe.');
        }
        if ($this->repo->getPlanes($id, $idEmpresa)) {
            throw new \DomainException('Ya tiene planes por inmueble: ciérrela en vez de eliminarla.');
        }
        $this->repo->eliminarAporte($id, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'Eliminar cuota extraordinaria', self::TABLA, $id, $a, null);
    }

    // ── Detalle (planes, avance y cobrado) ──────────────────────────────────

    public function detalle(int $id, int $idEmpresa): array
    {
        $a = $this->repo->getAporte($id, $idEmpresa);
        if (!$a) {
            throw new \DomainException('La cuota extraordinaria no existe.');
        }
        $a['plazos_permitidos'] = self::plazos($a);
        $planes = $this->repo->getPlanes($id, $idEmpresa);
        $cargos = $this->repo->getCargosAporte($id, $idEmpresa);
        $cobrado = $this->cobradoPorCargo($cargos, $idEmpresa);

        $porPlan = [];
        foreach ($cargos as &$c) {
            $c['cobrado'] = round($cobrado[(int) $c['id']] ?? 0.0, 2);
            $porPlan[(int) $c['id_aporte_unidad']] = ($porPlan[(int) $c['id_aporte_unidad']] ?? 0.0) + $c['cobrado'];
        }
        unset($c);
        $tot = ['asignado' => 0.0, 'anticipos' => 0.0, 'emitido' => 0.0, 'por_emitir' => 0.0, 'cobrado' => 0.0, 'saldo' => 0.0];
        foreach ($planes as &$p) {
            $p['cobrado'] = round($porPlan[(int) $p['id']] ?? 0.0, 2);
            $p['saldo'] = in_array($p['estado'], ['cancelado'], true) ? 0.0 : round((float) $p['monto_total'] - $p['cobrado'], 2);
            if ($p['estado'] === 'cancelado') {
                continue;
            }
            $tot['asignado']   += (float) $p['monto_total'];
            $tot['anticipos']  += (float) $p['anticipo_aplicado'];
            $tot['emitido']    += (float) $p['emitido'];
            $tot['por_emitir'] += (float) $p['por_emitir'];
            $tot['cobrado']    += $p['cobrado'];
            $tot['saldo']      += $p['saldo'];
        }
        unset($p);
        return ['aporte' => $a, 'planes' => $planes, 'cargos' => $cargos, 'totales' => array_map(fn($v) => round($v, 2), $tot)];
    }

    /**
     * Cobrado de cada cuota emitida (sin IVA). Lo abonado al documento (cobros, retenciones, NC)
     * cubre primero las líneas que no son cuotas extraordinarias y después las cuotas en orden.
     */
    public function cobradoPorCargo(array $cargos, int $idEmpresa): array
    {
        $claves = [];
        foreach ($cargos as $c) {
            if ($c['estado'] === 'emitido' && $c['tipo_documento'] && $c['id_documento']) {
                $claves[$c['tipo_documento'] . ':' . $c['id_documento']] = true;
            }
        }
        if (!$claves) {
            return [];
        }
        $claves = array_keys($claves);
        $ids = ['factura' => [], 'recibo' => []];
        foreach ($claves as $k) {
            [$t, $i] = explode(':', $k);
            $ids[$t][] = (int) $i;
        }
        $docs = $this->repo->getAbonosDocumentos($ids['factura'], $ids['recibo'], $idEmpresa);
        $cuotas = $this->repo->getCuotasPorDocumento($claves, $idEmpresa);
        $out = [];
        foreach ($claves as $k) {
            $doc = $docs[$k] ?? null;
            if (!$doc || in_array($doc['anulado'], [true, 't', 1, '1'], true)) {
                continue;
            }
            $lineas = $cuotas[$k] ?? [];
            $sumaCuotas = array_sum(array_map(fn($l) => (float) $l['monto_con_iva'], $lineas));
            $otros = max(0.0, (float) $doc['importe_total'] - $sumaCuotas);
            $disponible = max(0.0, (float) $doc['abonado'] - $otros);
            foreach ($lineas as $l) {
                $conIva = (float) $l['monto_con_iva'];
                $toma = min($conIva, $disponible);
                $disponible -= $toma;
                $out[(int) $l['id']] = $conIva > 0 ? round($toma * (float) $l['monto'] / $conIva, 2) : 0.0;
            }
        }
        return $out;
    }

    // ── Planes: vista previa y activación ───────────────────────────────────

    /**
     * Vista previa del reparto para los inmuebles que aún no tienen plan. $filas (de la vista)
     * trae lo que el usuario cambió por inmueble: incluir, monto manual, anticipo, plazo, pagador.
     */
    public function previsualizarPlanes(int $idAporte, array $filas, int $idEmpresa): array
    {
        $this->exigirActivo($idEmpresa);
        $a = $this->repo->getAporte($idAporte, $idEmpresa);
        if (!$a) {
            throw new \DomainException('La cuota extraordinaria no existe.');
        }
        if ($a['estado'] !== 'activo') {
            throw new \DomainException('La cuota extraordinaria está cerrada.');
        }
        return $this->armarPlanes($a, $filas, $idEmpresa);
    }

    private function armarPlanes(array $a, array $filas, int $idEmpresa): array
    {
        $a['plazos_permitidos'] = self::plazos($a);
        $cambios = [];
        foreach ($filas as $f) {
            if (is_array($f) && !empty($f['id_unidad'])) {
                $cambios[(int) $f['id_unidad']] = $this->rules->normalizarFila($f, $a);
            }
        }
        $existentes = $this->repo->getPlanes((int) $a['id'], $idEmpresa);
        $conPlan = [];
        $yaAsignado = 0.0;
        foreach ($existentes as $p) {
            $conPlan[(int) $p['id_unidad']] = true;
            if ($p['estado'] !== 'cancelado') {
                $yaAsignado += (float) $p['monto_total'];
            }
        }
        $meta = (float) $a['meta_total'];
        $formaAnt = (int) ($a['id_forma_anticipo'] ?? 0) ?: (int) ($this->repo->getFormasAnticipo($idEmpresa)[0]['id'] ?? 0);
        $fp = new \App\repositories\modulos\FormaPagoRepository();

        $rows = [];
        foreach ($this->repo->getInmueblesActivos($idEmpresa) as $u) {
            if (isset($conPlan[(int) $u['id']])) {
                continue;
            }
            $c = $cambios[(int) $u['id']] ?? $this->rules->normalizarFila(['id_unidad' => $u['id']], $a);
            $nota = [];
            $pagador = $c['pagador'];
            if ($pagador === 'arrendatario' && empty($u['id_arrendatario'])) {
                $pagador = 'propietario';
                $nota[] = 'No tiene arrendatario: paga el propietario.';
            }
            $idCli = (int) ($pagador === 'arrendatario' ? $u['id_arrendatario'] : $u['id_propietario']);
            $rows[] = $u + [
                'incluir' => $c['incluir'], 'manual' => $c['manual'] && $c['monto'] !== null, 'monto_manual' => $c['monto'],
                'anticipo' => $c['anticipo'], 'plazo' => $c['plazo'], 'pagador' => $pagador, 'id_cliente' => $idCli,
                'cliente_nombre' => $pagador === 'arrendatario' ? $u['arrendatario_nombre'] : $u['propietario_nombre'],
                'primer_periodo' => $c['primer_periodo'], 'nota' => $nota,
            ];
        }

        // Reparto: lo que no está fijado a mano se reparte entre los incluidos (iguales o por %).
        $restante = $meta - $yaAsignado;
        $auto = [];
        foreach ($rows as $i => $r) {
            if (!$r['incluir']) {
                continue;
            }
            if ($r['manual']) {
                $restante -= (float) $r['monto_manual'];
            } else {
                $auto[] = $i;
            }
        }
        $restante = round($restante, 2);
        $pesos = [];
        foreach ($auto as $i) {
            $pesos[$i] = $a['calculo'] === 'porcentaje' ? max(0.0, (float) $rows[$i]['alicuota_pct']) : 1.0;
        }
        $sumaPesos = array_sum($pesos);
        if ($a['calculo'] === 'porcentaje' && $auto && $sumaPesos <= 0) {
            throw new \DomainException('Los inmuebles incluidos no tienen % de alícuota: use el reparto en partes iguales o registre su %.');
        }
        $asignados = [];
        foreach ($auto as $i) {
            $asignados[$i] = $sumaPesos > 0 ? round(max(0.0, $restante) * $pesos[$i] / $sumaPesos, 2) : 0.0;
        }
        if ($auto && $restante > 0) {
            // Centavos al de mayor monto (mismo criterio que el reparto de la alícuota).
            $dif = round($restante - array_sum($asignados), 2);
            if (abs($dif) >= 0.01) {
                $mayor = array_keys($asignados, max($asignados))[0];
                $asignados[$mayor] = round($asignados[$mayor] + $dif, 2);
            }
        }

        $saldosAnt = [];
        $usoAnt = [];
        $tot = ['incluidos' => 0, 'asignado' => 0.0, 'anticipos' => 0.0, 'saldo_plazos' => 0.0];
        foreach ($rows as $i => &$r) {
            $r['monto'] = $r['manual'] ? round((float) $r['monto_manual'], 2) : ($asignados[$i] ?? 0.0);
            $cli = (int) $r['id_cliente'];
            if ($formaAnt > 0 && $cli > 0 && !isset($saldosAnt[$cli])) {
                $saldosAnt[$cli] = round($fp->getSaldoAnticipo($idEmpresa, $formaAnt, $cli), 2);
            }
            $r['anticipo_disponible'] = $saldosAnt[$cli] ?? 0.0;
            if (!$r['incluir']) {
                $r['saldo_plazos'] = 0.0;
                $r['cuota'] = $r['cuota_ultima'] = 0.0;
                $r['nota'] = implode(' ', $r['nota']);
                continue;
            }
            $r['bloquea'] = false;
            if ($cli <= 0) {
                $r['nota'][] = 'El inmueble no tiene a quién cobrar.';
                $r['bloquea'] = true;
            }
            if ($r['anticipo'] > $r['monto']) {
                $r['nota'][] = 'El anticipo supera lo asignado.';
                $r['bloquea'] = true;
            }
            $usoAnt[$cli] = ($usoAnt[$cli] ?? 0.0) + $r['anticipo'];
            if ($r['anticipo'] > 0 && $usoAnt[$cli] > $r['anticipo_disponible'] + 0.005) {
                $r['nota'][] = $formaAnt > 0 ? 'El cliente no tiene ese saldo de anticipo.' : 'No hay una forma de cobro tipo Anticipo activa.';
                $r['bloquea'] = true;
            }
            $r['saldo_plazos'] = round(max(0.0, $r['monto'] - $r['anticipo']), 2);
            $cu = CondominioCuotaExtraRules::cuotas($r['saldo_plazos'], $r['plazo']);
            $r['cuota'] = $cu['cuota'];
            $r['cuota_ultima'] = $cu['ultima'];
            $r['nota'] = implode(' ', $r['nota']);
            $tot['incluidos']++;
            $tot['asignado'] += $r['monto'];
            $tot['anticipos'] += $r['anticipo'];
            $tot['saldo_plazos'] += $r['saldo_plazos'];
        }
        unset($r);
        $tot = array_map(fn($v) => is_float($v) ? round($v, 2) : $v, $tot);
        $tot['meta'] = $meta;
        $tot['ya_asignado'] = round($yaAsignado, 2);
        $tot['diferencia'] = round($meta - $yaAsignado - $tot['asignado'], 2);
        $tot['forma_anticipo'] = $formaAnt;
        return ['aporte' => $a, 'filas' => array_values($rows), 'totales' => $tot];
    }

    /**
     * Crea los planes y todas sus cuotas en una transacción (guardado único). Después del COMMIT
     * aplica el anticipo de cada inmueble (documento de abono + ingreso con la forma ANTICIPO).
     *
     * @return array{planes:int, previo:bool, abonos:int, avisos:array}
     */
    public function activarPlanes(int $idAporte, array $filas, string $token, int $idEmpresa, int $idUsuario): array
    {
        $this->exigirActivo($idEmpresa);
        $db = $this->db();
        $guardado = new GuardadoUnicoService();
        $db->beginTransaction();
        $conAnticipo = [];
        try {
            $previo = $guardado->previo($token, $idEmpresa, self::MODULO_GUARDADO);
            if ($previo) {
                $db->rollBack();
                return ['planes' => 0, 'previo' => true, 'abonos' => 0, 'avisos' => []];
            }
            $this->repo->bloquearAporte($idAporte);
            $a = $this->repo->getAporte($idAporte, $idEmpresa, true);
            if (!$a || $a['estado'] !== 'activo') {
                throw new \DomainException('La cuota extraordinaria no existe o está cerrada.');
            }
            $res = $this->armarPlanes($a, $filas, $idEmpresa);
            $inc = array_values(array_filter($res['filas'], fn($r) => $r['incluir']));
            if (!$inc) {
                throw new \InvalidArgumentException('Marque al menos un inmueble.');
            }
            if (abs($res['totales']['diferencia']) >= 0.01) {
                throw new \InvalidArgumentException('La suma de lo asignado ($' . number_format($res['totales']['asignado'] + $res['totales']['ya_asignado'], 2)
                    . ') no iguala el monto aprobado ($' . number_format((float) $a['meta_total'], 2) . '). Ajuste los montos o los inmuebles incluidos.');
            }
            foreach ($inc as $r) {
                if (!empty($r['bloquea'])) {
                    throw new \InvalidArgumentException("{$r['codigo']}: {$r['nota']}");
                }
            }
            $nombre = (string) $a['nombre'];
            $creados = 0;
            foreach ($inc as $r) {
                if ($this->repo->existePlan($idAporte, (int) $r['id'])) {
                    continue; // otro usuario lo activó mientras tanto
                }
                $idPlan = $this->repo->insertPlan($idEmpresa, [
                    'id_aporte' => $idAporte, 'id_unidad' => (int) $r['id'], 'monto_asignado' => $r['monto'], 'ajuste_manual' => $r['manual'],
                    'plazo' => $r['plazo'], 'anticipo' => $r['anticipo'], 'saldo_plazos' => $r['saldo_plazos'], 'cuota_valor' => $r['cuota'],
                    'cuota_ultima' => $r['cuota_ultima'], 'primer_periodo' => $r['primer_periodo'], 'pagador' => $r['pagador'],
                    'id_cliente' => (int) $r['id_cliente'],
                ], $idUsuario);
                if ($r['saldo_plazos'] > 0) {
                    $this->crearCuotas($idPlan, $a, (int) $r['id'], (int) $r['id_cliente'], $r['saldo_plazos'], $r['plazo'], 0, $r['primer_periodo'], $idEmpresa, $idUsuario);
                }
                if ($r['anticipo'] > 0) {
                    $this->repo->insertCargo($idEmpresa, [
                        'id_unidad' => (int) $r['id'], 'periodo' => date('Ym'), 'subtipo' => 'abono', 'id_aporte' => $idAporte,
                        'id_aporte_unidad' => $idPlan, 'id_producto' => (int) $a['id_producto'],
                        'descripcion' => "{$nombre} – abono inicial (anticipo)", 'monto' => $r['anticipo'],
                        'id_cliente' => (int) $r['id_cliente'], 'donde_cobro' => 'aparte',
                    ], $idUsuario);
                    $conAnticipo[] = $idPlan;
                }
                $this->log->registrar($idUsuario, $idEmpresa, 'Activar plan de cuota extraordinaria', 'condominios_aportes_unidades', $idPlan, null, [
                    'cuota_extraordinaria' => $nombre, 'inmueble' => $r['codigo'], 'asignado' => $r['monto'], 'anticipo' => $r['anticipo'],
                    'plazo' => $r['plazo'], 'cuota' => $r['cuota'], 'ultima' => $r['cuota_ultima'], 'desde' => $r['primer_periodo'],
                    'pagador' => $r['pagador'], 'id_cliente' => $r['id_cliente'],
                ]);
                $creados++;
            }
            $guardado->registrar($token, $idEmpresa, self::MODULO_GUARDADO, $idAporte, null, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $avisos = [];
        $abonos = 0;
        foreach ($conAnticipo as $idPlan) {
            $r = $this->aplicarAbono($idPlan, $idEmpresa, $idUsuario);
            if ($r['ok']) {
                $abonos++;
            } else {
                $avisos[] = $r['mensaje'];
            }
        }
        return ['planes' => $creados, 'previo' => false, 'abonos' => $abonos, 'avisos' => $avisos];
    }

    /** Crea $n cuotas (cargos pendientes) desde $desde, numeradas desde $offset + 1. */
    private function crearCuotas(int $idPlan, array $a, int $idUnidad, int $idCliente, float $saldo, int $n, int $offset, string $desde, int $idEmpresa, int $idUsuario): void
    {
        $cu = CondominioCuotaExtraRules::cuotas($saldo, $n);
        $total = $offset + $n;
        for ($k = 1; $k <= $n; $k++) {
            $num = $offset + $k;
            $this->repo->insertCargo($idEmpresa, [
                'id_unidad' => $idUnidad, 'periodo' => CondominioCuotaExtraRules::sumarMeses($desde, $k - 1), 'subtipo' => 'cuota',
                'id_aporte' => (int) $a['id'], 'id_aporte_unidad' => $idPlan, 'id_producto' => (int) $a['id_producto'],
                'descripcion' => "{$a['nombre']} – cuota {$num}/{$total}", 'monto' => $k === $n ? $cu['ultima'] : $cu['cuota'],
                'numero_cuota' => $num, 'total_cuotas' => $total, 'id_cliente' => $idCliente, 'donde_cobro' => $a['donde_cobro'],
            ], $idUsuario);
        }
    }

    // ── Anticipo: abono inicial ─────────────────────────────────────────────

    /**
     * Emite el documento del abono inicial (si falta) y lo cobra con la forma ANTICIPO (si falta).
     * Todo bajo el candado del anticipo del cliente, revalidando el saldo. Nunca lanza: devuelve
     * {ok, mensaje} para mostrarlo; se puede reintentar desde el plan.
     */
    public function aplicarAbono(int $idPlan, int $idEmpresa, int $idUsuario): array
    {
        $pl = $this->repo->getPlan($idPlan, $idEmpresa);
        if (!$pl) {
            return ['ok' => false, 'mensaje' => 'El plan no existe.'];
        }
        $a = $this->repo->getAporte((int) $pl['id_aporte'], $idEmpresa);
        $monto = round((float) $pl['anticipo_aplicado'], 2);
        $etq = "{$pl['unidad_codigo']}";
        if (!$a || $monto <= 0) {
            return ['ok' => true, 'mensaje' => ''];
        }
        $idForma = (int) ($a['id_forma_anticipo'] ?? 0) ?: (int) ($this->repo->getFormasAnticipo($idEmpresa)[0]['id'] ?? 0);
        if ($idForma <= 0) {
            return ['ok' => false, 'mensaje' => "{$etq}: no hay una forma de cobro tipo Anticipo activa para aplicar el anticipo."];
        }
        $idCli = (int) $pl['id_cliente'];
        $this->repo->bloquearAnticipo($idEmpresa, $idCli);
        try {
            $cargoAbono = null;
            foreach ($this->repo->getCargosPlan($idPlan) as $c) {
                if ($c['subtipo'] === 'abono') {
                    $cargoAbono = $c;
                }
            }
            if (!$cargoAbono) {
                return ['ok' => false, 'mensaje' => "{$etq}: no se encontró el abono inicial."];
            }
            if (!empty($pl['id_ingreso_abono'])) {
                return ['ok' => true, 'mensaje' => ''];
            }
            $saldo = round((new \App\repositories\modulos\FormaPagoRepository())->getSaldoAnticipo($idEmpresa, $idForma, $idCli), 2);
            if ($saldo + 0.005 < $monto) {
                return ['ok' => false, 'mensaje' => "{$etq}: el cliente ya no tiene $" . number_format($monto, 2) . ' de anticipo (saldo $' . number_format($saldo, 2) . '). El abono no se aplicó.'];
            }
            // 1) Documento del abono (si aún no se emitió).
            if ($cargoAbono['estado'] === 'pendiente') {
                $res = $this->emitirCargos([(int) $cargoAbono['id']], $a, (int) $pl['id_unidad'], $idCli, $idEmpresa, $idUsuario);
                $tipo = $res['tipo'];
                $idDoc = (int) ($tipo === 'factura' ? $res['id_factura'] : $res['id_recibo']);
                $this->repo->actualizarPlan($idPlan, ['tipo_documento_abono' => $tipo, 'id_documento_abono' => $idDoc], $idUsuario);
            } else {
                $tipo = (string) $cargoAbono['tipo_documento'];
                $idDoc = (int) $cargoAbono['id_documento'];
            }
            // 2) Ingreso que lo cobra con la forma ANTICIPO (cancela el pasivo de anticipo de clientes).
            $doc = $this->documento($tipo, $idDoc, $idEmpresa);
            if (!$doc) {
                return ['ok' => false, 'mensaje' => "{$etq}: no se encontró el documento del abono."];
            }
            $numero = trim(($doc['establecimiento'] ?? '') . '-' . ($doc['punto_emision'] ?? '') . '-' . ($doc['secuencial'] ?? ''), '-');
            $cli = $this->repo->getEmpresaNombreCliente($idCli, $idEmpresa);
            $ing = (new CobroAutomaticoClienteService())->generarParaDocumento(
                ['id_forma_cobro' => $idForma, 'tipo_operacion' => null, 'plazo' => 0, 'id_ingreso_concepto' => null, 'nombre_cliente' => (string) ($cli['nombre'] ?? '')],
                $tipo === 'factura' ? 'FACTURA' : 'RECIBO', $idDoc, $numero, date('Y-m-d'), (float) $doc['importe_total'],
                min($monto, (float) $doc['importe_total']), $idCli, $idEmpresa, $idUsuario,
                "Aplicación de anticipo a {$a['nombre']} ({$etq})"
            );
            $this->repo->actualizarPlan($idPlan, ['id_ingreso_abono' => (int) $ing['id_ingreso']], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Aplicar anticipo a cuota extraordinaria', 'condominios_aportes_unidades', $idPlan, null, [
                'documento' => "{$tipo} {$numero}", 'ingreso' => $ing['numero_ingreso'], 'monto' => $monto,
            ]);
            return ['ok' => true, 'mensaje' => ''];
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'aplicarAbono#' . $idPlan]);
            return ['ok' => false, 'mensaje' => "{$etq}: no se pudo aplicar el anticipo (" . $e->getMessage() . '). Reintente desde el plan del inmueble.'];
        } finally {
            $this->repo->soltarAnticipo($idEmpresa, $idCli);
        }
    }

    // ── Emisión: enganche con la generación de documentos ───────────────────

    /**
     * Genera UN documento propio con los cargos indicados (abono, liquidación), con el
     * comprobante y la serie de la cuota extraordinaria. Los cargos se marcan en la misma
     * transacción del documento (ver lineasDocumento / confirmarDocumento).
     */
    private function emitirCargos(array $idsCargos, array $a, int $idUnidad, int $idCliente, int $idEmpresa, int $idUsuario): array
    {
        $cobro = new CondominioCobroRepository();
        $serie = $cobro->getSerie((int) $a['id_punto_emision'], $idEmpresa);
        if (!$serie) {
            throw new \DomainException('La serie de la cuota extraordinaria ya no existe o está inactiva.');
        }
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $cli = $this->repo->getEmpresaNombreCliente($idCliente, $idEmpresa);
        $info = [['concepto' => 'Cuota extraordinaria', 'detalle' => (string) $a['nombre'] . ($a['acta'] ? ' (acta ' . $a['acta'] . ')' : '')]];
        $susc = [
            'id' => 'cuota extraordinaria ' . $a['id'], 'id_cliente' => $idCliente, 'tipo_comprobante' => $a['tipo_comprobante'] ?: 'recibo',
            'forma_cobro' => 'credito', 'cliente_email' => (string) ($cli['email'] ?? ''), 'id_unidad' => $idUnidad,
            'info_adicional' => json_encode($info, JSON_UNESCAPED_UNICODE), 'periodicidad_meses' => 1, 'periodicidad_codigo' => '',
        ];
        return self::facturacion()->generarUnPeriodo($idEmpresa, $idUsuario, $susc, [], $serie, $empresa,
            ['condominio_cargos' => ['ids' => $idsCargos]], date('Y-m-d'));
    }

    public static function facturacion(): SuscripcionFacturacionService
    {
        $fac = new FacturaVentaService(new \App\repositories\modulos\FacturaVentaRepository(), new \App\Rules\modulos\FacturaVentaRules(), new LogSistemaService());
        $rec = new ReciboVentaService(new \App\repositories\modulos\ReciboVentaRepository(), new \App\Rules\modulos\ReciboVentaRules(), new LogSistemaService());
        return new SuscripcionFacturacionService($fac, new \App\Services\SecuencialService(), $rec);
    }

    private function documento(string $tipo, int $id, int $idEmpresa): ?array
    {
        $st = $this->db()->prepare('SELECT id, establecimiento, punto_emision, secuencial, importe_total FROM '
            . ($tipo === 'factura' ? 'ventas_cabecera' : 'recibos_venta_cabecera') . ' WHERE id = :id AND id_empresa = :e');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Enganche de SuscripcionFacturacionService::generarUnPeriodo(), DENTRO de su transacción:
     * toma (FOR UPDATE) los cargos que deben salir en el documento y devuelve sus líneas.
     *   $sel = ['suscripcion' => true]  → cuotas «junto» de los inmuebles de la suscripción $susc
     *        | ['item' => id]           → cuotas reservadas para un ítem de emisión aparte
     *        | ['ids' => [..]]          → cargos sueltos (abono inicial, liquidación)
     *
     * @return array{ids:int[], planes:int[], lineas:array}
     */
    public function lineasDocumento(array $sel, array $susc, string $periodoFecha, int $idEmpresa): array
    {
        if (!$this->repo->instalado()) {
            return ['ids' => [], 'planes' => [], 'lineas' => []];
        }
        if (!empty($sel['suscripcion'])) {
            if (!is_numeric($susc['id'] ?? null)) {
                return ['ids' => [], 'planes' => [], 'lineas' => []];
            }
            $periodo = $periodoFecha !== '' ? date('Ym', strtotime($periodoFecha)) : date('Ym');
            $cargos = $this->repo->tomarCargosSuscripcion((int) $susc['id'], (int) $susc['id_cliente'], $periodo, $idEmpresa);
        } elseif (!empty($sel['item'])) {
            $cargos = $this->repo->tomarCargosItem((int) $sel['item'], $idEmpresa);
        } else {
            $cargos = $this->repo->tomarCargosIds((array) ($sel['ids'] ?? []), $idEmpresa);
        }
        $lineas = [];
        foreach ($cargos as $c) {
            $lineas[] = [
                'id_producto' => (int) $c['id_producto'], 'codigo_producto' => (string) ($c['codigo_producto'] ?: '000'),
                'descripcion' => (string) $c['descripcion'], 'nombre_producto' => (string) $c['nombre_producto'],
                'cantidad' => 1, 'precio_unitario' => (float) $c['monto'], 'porcentaje_iva' => (float) $c['porcentaje_iva'],
                'codigo_porcentaje' => (string) $c['codigo_porcentaje'], 'id_tarifa_iva' => $c['id_tarifa_iva'] ? (int) $c['id_tarifa_iva'] : null,
            ];
        }
        return [
            'ids'    => array_map(fn($c) => (int) $c['id'], $cargos),
            'planes' => array_values(array_unique(array_map(fn($c) => (int) $c['id_aporte_unidad'], $cargos))),
            'lineas' => $lineas,
        ];
    }

    /** Enganche: marca los cargos tomados como emitidos, en la misma transacción del documento. */
    public function confirmarDocumento(array $tomados, string $tipo, int $idDocumento, $idSuscripcion, int $idUsuario): void
    {
        if (empty($tomados['ids'])) {
            return;
        }
        $this->repo->marcarEmitidos($tomados['ids'], $tipo, $idDocumento, is_numeric($idSuscripcion) ? (int) $idSuscripcion : null, $idUsuario);
        $this->repo->cerrarPlanesSinPendientes($tomados['planes']);
    }

    // ── Emisión aparte (emisión en bloque con forma_valor 'cargos') ─────────

    /** Un documento por inmueble y cliente con sus cuotas pendientes hasta $periodo (AAAA-MM). */
    public function previsualizarAparte(int $idAporte, string $periodo, int $idEmpresa): array
    {
        $this->exigirActivo($idEmpresa);
        $per = CondominioCuotaExtraRules::periodo($periodo);
        if ($per === '') {
            throw new \InvalidArgumentException('Elija el mes hasta el que se emiten las cuotas.|#cex_emi_periodo');
        }
        $a = $this->repo->getAporte($idAporte, $idEmpresa);
        if (!$a || $a['estado'] !== 'activo') {
            throw new \DomainException('La cuota extraordinaria no existe o está cerrada.');
        }
        $grupos = [];
        foreach ($this->repo->getCargosParaAparte($idAporte, $per, $idEmpresa) as $c) {
            $k = $c['id_cliente'] . ':' . $c['id_unidad'];
            if (!isset($grupos[$k])) {
                $grupos[$k] = ['clave' => $k, 'id_cliente' => (int) $c['id_cliente'], 'id_unidad' => (int) $c['id_unidad'],
                    'cliente' => $c['cliente_nombre'], 'identificacion' => (string) $c['cliente_identificacion'],
                    'email' => trim((string) $c['cliente_email']), 'inmueble' => $c['unidad_codigo'] . ($c['unidad_nombre'] && $c['unidad_nombre'] !== $c['unidad_codigo'] ? ' · ' . $c['unidad_nombre'] : ''),
                    'valor' => 0.0, 'cuotas' => [], 'ids' => [], 'nota' => (string) ($c['motivo_aparte'] ?? '')];
            }
            $grupos[$k]['valor'] = round($grupos[$k]['valor'] + (float) $c['monto'], 2);
            $grupos[$k]['cuotas'][] = $c['numero_cuota'] . '/' . $c['total_cuotas'];
            $grupos[$k]['ids'][] = (int) $c['id'];
        }
        $filas = array_values($grupos);
        foreach ($filas as &$f) {
            $f['cuotas'] = implode(', ', $f['cuotas']);
        }
        unset($f);
        return ['aporte' => ['id' => (int) $a['id'], 'nombre' => $a['nombre'], 'tipo_comprobante' => $a['tipo_comprobante'], 'serie' => $a['serie_texto']],
                'periodo' => $per, 'filas' => $filas,
                'totales' => ['documentos' => count($filas), 'valor' => round(array_sum(array_column($filas, 'valor')), 2)]];
    }

    /**
     * Crea la emisión en bloque (la procesa el worker de condominios) y reserva las cuotas de
     * cada ítem. Guardado único y candado de la cuota extraordinaria.
     *
     * @return array{id:int, items:int, previo:bool}
     */
    public function emitirAparte(int $idAporte, array $data, string $token, int $idEmpresa, int $idUsuario): array
    {
        $cobro = new CondominioCobroRepository();
        if (!$cobro->instalado()) {
            throw new \DomainException('Falta aplicar database/migrations/20261009_condominios_emisiones.sql.');
        }
        $seleccion = array_values(array_filter(array_map('trim', explode(',', (string) ($data['seleccion'] ?? ''))), fn($s) => $s !== ''));
        $enviarCorreo = in_array((string) ($data['enviar_correo'] ?? ''), ['1', 'true', 'on'], true);
        $guardado = new GuardadoUnicoService();
        $db = $this->db();
        $db->beginTransaction();
        try {
            $previo = $guardado->previo($token, $idEmpresa, self::MODULO_GUARDADO_EMISION);
            if ($previo) {
                $db->rollBack();
                return ['id' => (int) $previo['id_registro'], 'items' => 0, 'previo' => true];
            }
            $this->repo->bloquearAporte($idAporte);
            $prev = $this->previsualizarAparte($idAporte, (string) ($data['periodo'] ?? ''), $idEmpresa);
            $sel = array_flip($seleccion);
            $elegidas = array_values(array_filter($prev['filas'], fn($f) => isset($sel[$f['clave']]) && $f['valor'] > 0));
            if (!$elegidas) {
                throw new \InvalidArgumentException('No quedó ninguna cuota marcada para emitir.');
            }
            $a = $this->repo->getAporte($idAporte, $idEmpresa);
            $per = $prev['periodo'];
            $total = round(array_sum(array_column($elegidas, 'valor')), 2);
            $idEm = $cobro->insertEmision($idEmpresa, [
                'descripcion' => mb_substr("{$a['nombre']} – cuotas hasta " . substr($per, 4, 2) . '/' . substr($per, 0, 4), 0, 200),
                'tipo_comprobante' => $a['tipo_comprobante'] ?: 'recibo', 'id_punto_emision' => (int) $a['id_punto_emision'],
                'id_producto' => (int) $a['id_producto'], 'forma_valor' => 'cargos', 'valor' => 0, 'agrupar' => 'inmueble',
                'texto_item' => '', 'enviar_correo' => $enviarCorreo, 'total_items' => count($elegidas), 'total_valor' => $total,
            ], $idUsuario);
            $this->repo->vincularEmision($idEm, $idAporte, $per);
            foreach ($elegidas as $f) {
                $cobro->insertItem($idEm, $idEmpresa, $f, $enviarCorreo, $idUsuario);
                $this->repo->reservarCargos($f['ids'], (int) $this->repo->ultimoItemEmision($idEm));
            }
            $guardado->registrar($token, $idEmpresa, self::MODULO_GUARDADO_EMISION, $idEm, null, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Emitir cuotas extraordinarias aparte', 'condominios_emisiones', $idEm, null, [
                'cuota_extraordinaria' => $a['nombre'], 'hasta' => $per, 'documentos' => count($elegidas), 'total' => $total, 'enviar_correo' => $enviarCorreo,
            ]);
            $db->commit();
            return ['id' => $idEm, 'items' => count($elegidas), 'previo' => false];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    // ── Acciones sobre un plan ──────────────────────────────────────────────

    /**
     * Liquida el saldo: suma las cuotas pendientes en un solo cargo y lo emite en un documento.
     * Motivo 'pago_anticipado' (al mismo pagador) o 'venta' (al cliente indicado, el vendedor).
     */
    public function liquidar(int $idPlan, string $motivo, ?int $idClienteDestino, int $idEmpresa, int $idUsuario): array
    {
        $motivo = $motivo === 'venta' ? 'venta' : 'pago_anticipado';
        $db = $this->db();
        $db->beginTransaction();
        try {
            $pl = $this->repo->getPlan($idPlan, $idEmpresa, true);
            if (!$pl || !in_array($pl['estado'], ['pendiente', 'en_curso'], true)) {
                throw new \DomainException('El plan no existe o ya no tiene cuotas por emitir.');
            }
            $a = $this->repo->getAporte((int) $pl['id_aporte'], $idEmpresa);
            $pend = array_values(array_filter($this->repo->getCargosPlan($idPlan, true, true), fn($c) => $c['subtipo'] === 'cuota'));
            $suma = round(array_sum(array_map(fn($c) => (float) $c['monto'], $pend)), 2);
            if ($suma <= 0) {
                throw new \DomainException('El plan no tiene cuotas pendientes de emitir.');
            }
            $destino = $idClienteDestino ?: (int) $pl['id_cliente'];
            foreach ($pend as $c) {
                $db->prepare("UPDATE condominios_cargos SET estado = 'anulado', observacion = 'Incluida en la liquidación del saldo.',
                                     updated_at = CURRENT_TIMESTAMP, updated_by = :u WHERE id = :id")->execute([':u' => $idUsuario, ':id' => $c['id']]);
            }
            $idCargo = $this->repo->insertCargo($idEmpresa, [
                'id_unidad' => (int) $pl['id_unidad'], 'periodo' => date('Ym'), 'subtipo' => 'liquidacion', 'id_aporte' => (int) $a['id'],
                'id_aporte_unidad' => $idPlan, 'id_producto' => (int) $a['id_producto'],
                'descripcion' => "{$a['nombre']} – liquidación del saldo (" . count($pend) . ' cuota(s))', 'monto' => $suma,
                'id_cliente' => $destino, 'donde_cobro' => 'aparte',
            ], $idUsuario);
            $this->repo->actualizarPlan($idPlan, ['estado' => 'liquidado', 'fecha_liquidacion' => date('Y-m-d'), 'motivo_liquidacion' => $motivo,
                'id_cliente_liquidacion' => $destino], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Liquidar plan de cuota extraordinaria', 'condominios_aportes_unidades', $idPlan,
                ['estado' => $pl['estado'], 'cuotas_pendientes' => count($pend)], ['estado' => 'liquidado', 'motivo' => $motivo, 'monto' => $suma, 'id_cliente' => $destino]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        return $this->emitirCargoSuelto($idCargo, $idEmpresa, $idUsuario);
    }

    /** Emite un cargo suelto pendiente (liquidación, o abono sin documento). Reintentable. */
    public function emitirCargoSuelto(int $idCargo, int $idEmpresa, int $idUsuario): array
    {
        $c = $this->repo->getCargo($idCargo, $idEmpresa);
        if (!$c || $c['estado'] !== 'pendiente' || !in_array($c['subtipo'], ['liquidacion', 'abono'], true)) {
            throw new \DomainException('El cargo no está pendiente de emitir.');
        }
        if ($c['subtipo'] === 'abono') {
            $r = $this->aplicarAbono((int) $c['id_aporte_unidad'], $idEmpresa, $idUsuario);
            if (!$r['ok']) {
                throw new \DomainException($r['mensaje']);
            }
            return ['mensaje' => 'Abono inicial emitido y anticipo aplicado.'];
        }
        $a = $this->repo->getAporte((int) $c['id_aporte'], $idEmpresa);
        try {
            $res = $this->emitirCargos([$idCargo], $a, (int) $c['id_unidad'], (int) $c['id_cliente'], $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            throw new \DomainException('La liquidación quedó registrada pero su documento no se pudo emitir: ' . $e->getMessage() . ' Reintente con «Emitir» en la cuota.');
        }
        $doc = $this->documento($res['tipo'], (int) ($res['id_factura'] ?: $res['id_recibo']), $idEmpresa);
        $num = $doc ? "{$doc['establecimiento']}-{$doc['punto_emision']}-{$doc['secuencial']}" : '';
        return ['mensaje' => ($res['tipo'] === 'factura' ? 'Factura ' : 'Recibo ') . $num . ' emitido por $' . number_format((float) $res['importe'], 2) . '.'];
    }

    /** Cambia el número de cuotas de lo que falta: anula las pendientes y crea las nuevas. */
    public function cambiarPlazo(int $idPlan, int $nuevasCuotas, int $idEmpresa, int $idUsuario): void
    {
        if ($nuevasCuotas < 1 || $nuevasCuotas > CondominioCuotaExtraRules::PLAZO_MAX) {
            throw new \InvalidArgumentException('Indique cuántas cuotas faltan (1 a ' . CondominioCuotaExtraRules::PLAZO_MAX . ').');
        }
        $db = $this->db();
        $db->beginTransaction();
        try {
            $pl = $this->repo->getPlan($idPlan, $idEmpresa, true);
            if (!$pl || $pl['estado'] !== 'en_curso') {
                throw new \DomainException('Solo se cambia el plazo de un plan en curso.');
            }
            $a = $this->repo->getAporte((int) $pl['id_aporte'], $idEmpresa);
            $todas = $this->repo->getCargosPlan($idPlan, false, true);
            $pend = array_values(array_filter($todas, fn($c) => $c['subtipo'] === 'cuota' && $c['estado'] === 'pendiente'));
            if (!$pend) {
                throw new \DomainException('El plan no tiene cuotas pendientes.');
            }
            $emitidas = count(array_filter($todas, fn($c) => $c['subtipo'] === 'cuota' && $c['estado'] === 'emitido'));
            $saldo = round(array_sum(array_map(fn($c) => (float) $c['monto'], $pend)), 2);
            $desde = max((string) $pend[0]['periodo'], date('Ym'));
            $this->repo->anularPendientesPlan($idPlan, $idUsuario, "Plazo cambiado a {$nuevasCuotas} cuota(s) restantes.");
            $this->crearCuotas($idPlan, $a, (int) $pl['id_unidad'], (int) $pl['id_cliente'], $saldo, $nuevasCuotas, $emitidas, $desde, $idEmpresa, $idUsuario);
            $cu = CondominioCuotaExtraRules::cuotas($saldo, $nuevasCuotas);
            $this->repo->actualizarPlan($idPlan, ['plazo_elegido' => $emitidas + $nuevasCuotas, 'cuota_valor' => $cu['cuota'], 'cuota_ultima' => $cu['ultima']], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Cambiar plazo de cuota extraordinaria', 'condominios_aportes_unidades', $idPlan,
                ['plazo' => (int) $pl['plazo_elegido'], 'pendientes' => count($pend)], ['plazo' => $emitidas + $nuevasCuotas, 'pendientes' => $nuevasCuotas, 'saldo' => $saldo, 'desde' => $desde]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Cambia quién paga las cuotas pendientes (propietario / arrendatario actuales del inmueble). */
    public function cambiarPagador(int $idPlan, string $pagador, int $idEmpresa, int $idUsuario): void
    {
        $pagador = $pagador === 'arrendatario' ? 'arrendatario' : 'propietario';
        $db = $this->db();
        $db->beginTransaction();
        try {
            $pl = $this->repo->getPlan($idPlan, $idEmpresa, true);
            if (!$pl || !in_array($pl['estado'], ['pendiente', 'en_curso'], true)) {
                throw new \DomainException('El plan no está en curso.');
            }
            $idCli = (int) ($pagador === 'arrendatario' ? $pl['id_arrendatario'] : $pl['id_propietario']);
            if ($idCli <= 0) {
                throw new \DomainException('El inmueble no tiene ' . ($pagador === 'arrendatario' ? 'arrendatario' : 'propietario') . ' registrado.');
            }
            $n = $this->repo->cambiarClientePendientes($idPlan, $idCli, '000000', $idUsuario);
            $this->repo->actualizarPlan($idPlan, ['pagador' => $pagador, 'id_cliente' => $idCli], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Cambiar pagador de cuota extraordinaria', 'condominios_aportes_unidades', $idPlan,
                ['pagador' => $pl['pagador'], 'id_cliente' => $pl['id_cliente']], ['pagador' => $pagador, 'id_cliente' => $idCli, 'cuotas' => $n]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Devuelve a pendiente una cuota cuyo documento se anuló (sale en la próxima emisión). */
    public function volverAPendiente(int $idCargo, int $idEmpresa, int $idUsuario): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $c = $this->repo->getCargo($idCargo, $idEmpresa, true);
            if (!$c || $c['estado'] !== 'emitido' || !$c['tipo_documento']) {
                throw new \DomainException('La cuota no está emitida.');
            }
            if (!$this->repo->documentoAnulado((string) $c['tipo_documento'], (int) $c['id_documento'])) {
                throw new \DomainException('Su documento sigue vigente: anúlelo primero en Recibos o Facturas de Venta.');
            }
            $this->repo->volverAPendiente($idCargo, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Cuota extraordinaria vuelta a pendiente', 'condominios_cargos', $idCargo,
                ['estado' => 'emitido', 'documento' => $c['tipo_documento'] . ' #' . $c['id_documento']], ['estado' => 'pendiente']);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    // ── Venta del inmueble / cambio de arrendatario ─────────────────────────

    /**
     * Planes vigentes del inmueble cuyo cliente ya no coincide con la persona actual (propietario
     * o arrendatario, según su pagador). La ficha del inmueble pregunta por ellos tras guardar.
     */
    public function planesConCambioDePersona(int $idUnidad, int $idEmpresa): array
    {
        $u = (new \App\repositories\modulos\CondominioRepository())->getUnidad($idUnidad, $idEmpresa);
        if (!$u) {
            return [];
        }
        $out = [];
        foreach ($this->repo->getPlanesVigentesUnidad($idUnidad, $idEmpresa) as $p) {
            $actual = (int) ($p['pagador'] === 'arrendatario' ? ($u['id_arrendatario'] ?? 0) : ($u['id_propietario'] ?? 0));
            if ($actual !== (int) $p['id_cliente'] && (float) $p['por_emitir'] > 0) {
                $p['id_cliente_nuevo'] = $actual;
                $out[] = $p;
            }
        }
        return $out;
    }

    /** $decisiones: [{id_plan, accion: seguir|liquidar}]. $desde: AAAA-MM-DD (rige desde). */
    public function aplicarCambioDePersona(int $idUnidad, array $decisiones, string $desde, int $idEmpresa, int $idUsuario): array
    {
        $pendientes = [];
        foreach ($this->planesConCambioDePersona($idUnidad, $idEmpresa) as $p) {
            $pendientes[(int) $p['id']] = $p;
        }
        $per = preg_match('/^\d{4}-\d{2}/', $desde) ? substr($desde, 0, 4) . substr($desde, 5, 2) : date('Ym');
        $msgs = [];
        foreach ($decisiones as $d) {
            $p = $pendientes[(int) ($d['id_plan'] ?? 0)] ?? null;
            if (!$p) {
                continue;
            }
            if (($d['accion'] ?? '') === 'liquidar') {
                $r = $this->liquidar((int) $p['id'], 'venta', (int) $p['id_cliente'], $idEmpresa, $idUsuario);
                $msgs[] = "{$p['aporte_nombre']}: liquidada a {$p['cliente_nombre']}. {$r['mensaje']}";
                continue;
            }
            if ((int) $p['id_cliente_nuevo'] <= 0) {
                $msgs[] = "{$p['aporte_nombre']}: el inmueble no tiene a quién pasar la deuda.";
                continue;
            }
            $db = $this->db();
            $db->beginTransaction();
            try {
                $this->repo->getPlan((int) $p['id'], $idEmpresa, true);
                $n = $this->repo->cambiarClientePendientes((int) $p['id'], (int) $p['id_cliente_nuevo'], $per, $idUsuario);
                $this->repo->actualizarPlan((int) $p['id'], ['id_cliente' => (int) $p['id_cliente_nuevo']], $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'Cuota extraordinaria pasa al nuevo pagador', 'condominios_aportes_unidades', (int) $p['id'],
                    ['id_cliente' => (int) $p['id_cliente']], ['id_cliente' => (int) $p['id_cliente_nuevo'], 'desde' => $per, 'cuotas' => $n]);
                $db->commit();
                $msgs[] = "{$p['aporte_nombre']}: {$n} cuota(s) pendientes pasan al nuevo pagador.";
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        }
        return $msgs;
    }
}
