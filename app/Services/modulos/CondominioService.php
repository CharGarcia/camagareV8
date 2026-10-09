<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ClienteRepository;
use App\repositories\modulos\CondominioRepository;
use App\Rules\modulos\CondominioRules;
use App\Services\LogSistemaService;

/**
 * Condominios — fase 1 (módulo base): configuración del condominio, inmuebles (inmuebles) con
 * su historial de propietarios, catálogo de multas, restricción de áreas comunes y enlace de
 * suscripciones (programados migrados) a un inmueble.
 *
 * Reglas:
 *  - Guardar la configuración ACTIVA el módulo para la empresa (`activo()`); los demás módulos
 *    solo muestran lo de condominios cuando está activo.
 *  - Los conceptos (alícuota, fondo, intereses, multas) se facturan con productos que el usuario
 *    crea en Productos; aquí solo se guardan sus id y se valida que existan y sean servicios.
 *  - Cada cambio de propietario/arrendatario/pagador abre una fila en el historial: la deuda es
 *    del inmueble, no de la persona.
 *  - Un inmueble con cargos emitidos no se elimina (se inactiva).
 */
class CondominioService
{
    /** Cache por proceso: la pregunta «¿es condominio?» la hacen varios módulos por petición. */
    private static array $activoCache = [];

    public function __construct(
        private CondominioRepository $repo,
        private CondominioRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CondominioRepository(), new CondominioRules(), new LogSistemaService());
    }

    public function repo(): CondominioRepository
    {
        return $this->repo;
    }

    /** ¿La empresa tiene el módulo Condominios activo (configuración guardada)? */
    public static function activo(int $idEmpresa): bool
    {
        if (!isset(self::$activoCache[$idEmpresa])) {
            try {
                self::$activoCache[$idEmpresa] = (new CondominioRepository())->getConfig($idEmpresa) !== null;
            } catch (\Throwable $e) {
                self::$activoCache[$idEmpresa] = false;
            }
        }
        return self::$activoCache[$idEmpresa];
    }

    public function instalado(): bool
    {
        return $this->repo->instalado();
    }

    // ── Configuración ────────────────────────────────────────────────────────

    public function getConfig(int $idEmpresa): ?array
    {
        return $this->repo->getConfig($idEmpresa);
    }

    public function guardarConfig(array $data, int $idEmpresa, int $idUsuario): array
    {
        $this->exigirInstalado();
        $c = $this->rules->validarConfig($data);
        if ($c['id_producto_fondo']) {
            $this->validarProducto($c['id_producto_fondo'], $idEmpresa, 'el fondo de reserva', '#cfg_prod_fondo_txt');
        }
        if ($c['id_producto_interes']) {
            $this->validarProducto($c['id_producto_interes'], $idEmpresa, 'los intereses de mora', '#cfg_prod_interes_txt');
        }
        $antes = $this->repo->getConfig($idEmpresa);
        $this->repo->beginTransaction();
        try {
            if ($antes) {
                $this->repo->updateConfig($idEmpresa, $c, $idUsuario);
                $id = (int) $antes['id'];
                $accion = 'Actualizar configuración de condominio';
            } else {
                $id = $this->repo->insertConfig($idEmpresa, $c, $idUsuario);
                $accion = 'Activar módulo Condominios';
            }
            $this->log->registrar($idUsuario, $idEmpresa, $accion, 'condominios_config', $id, $antes ? $this->soloCampos($antes, CondominioRepository::CAMPOS_CONFIG) : null, $c);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
        unset(self::$activoCache[$idEmpresa]);
        return $this->repo->getConfig($idEmpresa) ?? [];
    }

    /** Qué le falta a la configuración para poder emitir (se muestra como aviso en pantalla). */
    public function pendientesConfig(?array $cfg): array
    {
        if (!$cfg) {
            return ['Guarde la configuración del condominio para activar el módulo.'];
        }
        $p = [];
        if ($this->esTrue($cfg['cobra_intereses'] ?? false) && empty($cfg['id_producto_interes'])) {
            $p[] = 'Falta el producto para los intereses de mora.';
        }
        return $p;
    }

    // ── Inmuebles ─────────────────────────────────────────────────────────────

    /**
     * Pestaña Condóminos de Configuración de condominios: todos los clientes de la empresa con
     * los inmuebles que tienen como propietario o arrendatario.
     */
    public function getCondominos(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden): array
    {
        if (!$this->repo->instalado()) {
            return ['total' => 0, 'rows' => []];
        }
        $res = $this->repo->getCondominos($idEmpresa, $buscar, $page, $perPage, $orden);
        foreach ($res['rows'] as &$r) {
            $lista = json_decode((string) $r['lista'], true) ?: [];
            foreach ($lista as &$u) {
                $u['tipo_label']  = CondominioRules::TIPOS_LABEL[$u['tipo']] ?? $u['tipo'];
                $u['restringida'] = $this->esTrue($u['restringida'] ?? false);
                $u['paga']        = $this->esTrue($u['paga'] ?? false);
            }
            unset($u);
            $r['lista']       = $lista;
            $r['inmuebles']   = (int) $r['inmuebles'];
            $r['restringida'] = $this->esTrue($r['restringida']);
            $r['activo']      = (int) ($r['status'] ?? 1) === 1;
        }
        unset($r);
        return $res;
    }

    /** Cuota ordinaria por inmueble [id => cuota|null] con el valor que rige hoy (cobros en bloque). */
    public function cuotasVigentes(int $idEmpresa): array
    {
        $cfg = $this->repo->getConfig($idEmpresa);
        return $cfg ? $this->cuotasConValorVigente($idEmpresa, $cfg) : [];
    }

    /** Cuota ordinaria por inmueble [id => cuota|null] con el valor vigente a hoy. */
    private function cuotasConValorVigente(int $idEmpresa, array $cfg): array
    {
        $valor = $this->repo->getValorVigente($idEmpresa, date('Y-m-d'));
        $out = [];
        foreach ($this->calcularCuotas($this->repo->getUnidadesParaCuota($idEmpresa), $cfg, $valor)['filas'] as $f) {
            $out[(int) $f['id']] = $f['cuota'];
        }
        return $out;
    }

    public function getUnidad(int $id, int $idEmpresa): array
    {
        $u = $this->repo->getUnidad($id, $idEmpresa);
        if (!$u) {
            throw new \DomainException('El inmueble no existe.');
        }
        $u['restringida'] = $this->esTrue($u['restringida'] ?? false);
        $cfg = $this->repo->getConfig($idEmpresa);
        $u['cuota_estimada'] = $cfg ? ($this->cuotasConValorVigente($idEmpresa, $cfg)[$id] ?? null) : null;
        $u['tipo_label']   = CondominioRules::TIPOS_LABEL[$u['tipo']] ?? $u['tipo'];
        return [
            'unidad'          => $u,
            'historial'     => $this->repo->getHistorialPropietarios($id, $idEmpresa),
            'restricciones' => $this->repo->getRestriccionesLog($id, $idEmpresa),
            'suscripciones_enlazables' => $this->repo->getSuscripcionesSinUnidad((int) ($u['pagador'] === 'arrendatario' && $u['id_arrendatario'] ? $u['id_arrendatario'] : $u['id_propietario']), $idEmpresa),
        ];
    }

    public function crearUnidad(array $data, int $idEmpresa, int $idUsuario): int
    {
        $cfg = $this->exigirConfig($idEmpresa);
        $u = $this->rules->validarUnidad($data, $cfg);
        $this->validarPersonas($u, $idEmpresa);
        if ($this->repo->existeCodigo($idEmpresa, $u['codigo'])) {
            throw new \InvalidArgumentException("Ya existe un inmueble con el código «{$u['codigo']}».|#uni_codigo");
        }
        $desde = CondominioRules::fecha($data['propietario_desde'] ?? date('Y-m-d'), 'inicio del propietario', '#uni_propietario_desde');

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->insertUnidad($idEmpresa, $u, $idUsuario);
            $this->repo->abrirPropietario($id, $idEmpresa, $u['id_propietario'], $u['id_arrendatario'], $u['pagador'], $desde, null, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Crear inmueble de condominio', 'condominios_unidades', $id, null, $u);
            $this->repo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function actualizarUnidad(int $id, array $data, int $idEmpresa, int $idUsuario): void
    {
        $cfg = $this->exigirConfig($idEmpresa);
        $antes = $this->repo->getUnidad($id, $idEmpresa);
        if (!$antes) {
            throw new \DomainException('El inmueble no existe.');
        }
        $u = $this->rules->validarUnidad($data, $cfg);
        $this->validarPersonas($u, $idEmpresa);
        if ($this->repo->existeCodigo($idEmpresa, $u['codigo'], $id)) {
            throw new \InvalidArgumentException("Ya existe otro inmueble con el código «{$u['codigo']}».|#uni_codigo");
        }
        $cambioPersonas = (int) $antes['id_propietario'] !== $u['id_propietario']
            || (int) ($antes['id_arrendatario'] ?? 0) !== (int) ($u['id_arrendatario'] ?? 0)
            || $antes['pagador'] !== $u['pagador'];
        $desde = $cambioPersonas
            ? CondominioRules::fecha($data['propietario_desde'] ?? date('Y-m-d'), 'inicio del nuevo propietario/pagador', '#uni_propietario_desde')
            : null;

        $this->repo->beginTransaction();
        try {
            $this->repo->updateUnidad($id, $idEmpresa, $u, $idUsuario);
            if ($cambioPersonas) {
                $this->repo->abrirPropietario($id, $idEmpresa, $u['id_propietario'], $u['id_arrendatario'], $u['pagador'], $desde, trim((string) ($data['propietario_observacion'] ?? '')) ?: null, $idUsuario);
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'Actualizar inmueble de condominio', 'condominios_unidades', $id,
                $this->soloCampos($antes, CondominioRepository::CAMPOS_UNIDAD), $u);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function eliminarUnidad(int $id, int $idEmpresa, int $idUsuario): void
    {
        $u = $this->repo->getUnidad($id, $idEmpresa);
        if (!$u) {
            throw new \DomainException('El inmueble no existe.');
        }
        if ($this->repo->tieneCargosEmitidos($id, $idEmpresa)) {
            throw new \DomainException('El inmueble ya tiene expensas emitidas: no se puede eliminar. Márquela como inactiva para que deje de emitir.');
        }
        $this->repo->beginTransaction();
        try {
            if (!empty($u['id_suscripcion'])) {
                $this->repo->enlazarSuscripcion((int) $u['id_suscripcion'], $idEmpresa, null, $idUsuario);
            }
            $this->repo->deleteUnidad($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Eliminar inmueble de condominio', 'condominios_unidades', $id,
                $this->soloCampos($u, CondominioRepository::CAMPOS_UNIDAD), null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Marca o quita la restricción de áreas comunes (notificación previa obligatoria al marcar). */
    public function restringir(int $id, bool $restringir, ?string $fecha, ?string $motivo, int $idEmpresa, int $idUsuario): void
    {
        $u = $this->repo->getUnidad($id, $idEmpresa);
        if (!$u) {
            throw new \DomainException('El inmueble no existe.');
        }
        $f = CondominioRules::fecha($fecha ?: date('Y-m-d'), $restringir ? 'notificación' : 'levantamiento', '#restr_fecha');
        $motivo = trim((string) $motivo);
        if ($restringir && $motivo === '') {
            throw new \InvalidArgumentException('Indique el motivo que se notificó al condómino.|#restr_motivo');
        }
        $this->repo->beginTransaction();
        try {
            $this->repo->setRestriccion($id, $idEmpresa, $restringir, $f, $motivo ?: null, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $restringir ? 'Restringir áreas comunes' : 'Levantar restricción de áreas comunes',
                'condominios_unidades', $id, ['restringida' => $this->esTrue($u['restringida'])], ['restringida' => $restringir, 'fecha' => $f, 'motivo' => $motivo]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Enlaza (o desenlaza con $idSuscripcion = 0) una suscripción existente como expensa del inmueble. */
    public function enlazarSuscripcion(int $idUnidad, int $idSuscripcion, int $idEmpresa, int $idUsuario): void
    {
        $u = $this->repo->getUnidad($idUnidad, $idEmpresa);
        if (!$u) {
            throw new \DomainException('El inmueble no existe.');
        }
        $this->repo->beginTransaction();
        try {
            if (!empty($u['id_suscripcion'])) {
                $this->repo->enlazarSuscripcion((int) $u['id_suscripcion'], $idEmpresa, null, $idUsuario);
            }
            if ($idSuscripcion > 0) {
                $s = $this->repo->getSuscripcion($idSuscripcion, $idEmpresa);
                if (!$s) {
                    throw new \DomainException('La suscripción no existe.');
                }
                if (!empty($s['id_unidad']) && (int) $s['id_unidad'] !== $idUnidad) {
                    throw new \DomainException('Esa suscripción ya pertenece a otro inmueble.');
                }
                $pagador = (int) ($u['pagador'] === 'arrendatario' && $u['id_arrendatario'] ? $u['id_arrendatario'] : $u['id_propietario']);
                if ((int) $s['id_cliente'] !== $pagador) {
                    throw new \DomainException('La suscripción es de otro cliente; debe ser del pagador del inmueble.');
                }
                $this->repo->enlazarSuscripcion($idSuscripcion, $idEmpresa, $idUnidad, $idUsuario);
            }
            $this->repo->setSuscripcionUnidad($idUnidad, $idEmpresa, $idSuscripcion > 0 ? $idSuscripcion : null);
            $this->log->registrar($idUsuario, $idEmpresa, $idSuscripcion > 0 ? 'Enlazar suscripción a inmueble' : 'Desenlazar suscripción de inmueble',
                'condominios_unidades', $idUnidad, ['id_suscripcion' => $u['id_suscripcion']], ['id_suscripcion' => $idSuscripcion ?: null]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    // ── Enlace con Suscripciones ─────────────────────────────────────────────

    /** Inmuebles que paga el cliente (para el selector del modal de Suscripciones). */
    public function inmueblesDeCliente(int $idCliente, int $idEmpresa): array
    {
        if ($idCliente <= 0 || !self::activo($idEmpresa)) {
            return [];
        }
        return array_map(function (array $u) {
            $u['etiqueta'] = trim($u['codigo'] . ' · ' . $u['nombre'] . ($u['torre_bloque'] ? ' · ' . $u['torre_bloque'] : ''));
            return $u;
        }, $this->repo->getUnidadesPorCliente($idCliente, $idEmpresa));
    }

    /**
     * Resuelve el inmueble de una suscripción al guardarla: si el usuario eligió uno, se valida
     * que el cliente lo pague; si no eligió y el cliente paga exactamente UN inmueble, se asocia
     * solo. Devuelve null si la empresa no es condominio o no hay inmueble que asociar.
     */
    public function resolverInmuebleSuscripcion(int $idCliente, ?int $idUnidadElegido, int $idEmpresa): ?int
    {
        if (!self::activo($idEmpresa)) {
            return null;
        }
        $propios = $this->repo->getUnidadesPorCliente($idCliente, $idEmpresa);
        if ($idUnidadElegido) {
            foreach ($propios as $u) {
                if ((int) $u['id'] === $idUnidadElegido) {
                    return $idUnidadElegido;
                }
            }
            throw new \InvalidArgumentException('El inmueble elegido no lo paga este cliente. Revise el pagador del inmueble en Condominios.|#susc_id_unidad');
        }
        return count($propios) === 1 ? (int) $propios[0]['id'] : null;
    }

    /** Espeja el enlace en el inmueble (`id_suscripcion`) después de guardar la suscripción. */
    public function espejarSuscripcion(int $idSuscripcion, ?int $idUnidad, int $idEmpresa): void
    {
        if (self::activo($idEmpresa)) {
            $this->repo->espejarSuscripcionEnUnidad($idSuscripcion, $idUnidad, $idEmpresa);
        }
    }

    /**
     * Filas de «Información adicional» del recibo/factura de un inmueble: Inmueble, Propietario
     * y Período. Salen en el RIDE, el XML y el correo sin tocar la facturación.
     */
    public function infoAdicionalInmueble(int $idUnidad, int $idEmpresa, string $periodoTexto = ''): array
    {
        $u = $this->repo->getUnidad($idUnidad, $idEmpresa);
        if (!$u) {
            return [];
        }
        $ubic = array_filter([
            (CondominioRules::TIPOS_LABEL[$u['tipo']] ?? $u['tipo']) . ' ' . $u['nombre'],
            $u['torre_bloque'] ? 'Torre/Bloque ' . $u['torre_bloque'] : null,
            $u['piso'] !== null && $u['piso'] !== '' ? 'Piso ' . $u['piso'] : null,
        ]);
        $filas = [
            ['nombre' => 'Inmueble', 'valor' => implode(' · ', $ubic)],
            ['nombre' => 'Propietario', 'valor' => (string) ($u['propietario_nombre'] ?? '')],
        ];
        if ($periodoTexto !== '') {
            $filas[] = ['nombre' => 'Período', 'valor' => $periodoTexto];
        }
        return array_values(array_filter($filas, fn($f) => trim($f['valor']) !== ''));
    }

    // ── Multas ───────────────────────────────────────────────────────────────

    public function guardarMulta(array $data, int $idEmpresa, int $idUsuario): int
    {
        $this->exigirConfig($idEmpresa);
        $m = $this->rules->validarMulta($data);
        $this->validarProducto($m['id_producto'], $idEmpresa, 'la multa', '#multa_prod_txt');
        $id = (int) ($data['id'] ?? 0);
        $this->repo->beginTransaction();
        try {
            if ($id > 0) {
                $antes = $this->repo->getMulta($id, $idEmpresa);
                if (!$antes) {
                    throw new \DomainException('La multa no existe.');
                }
                $this->repo->updateMulta($id, $idEmpresa, $m, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'Actualizar multa de condominio', 'condominios_multas_catalogo', $id, $antes, $m);
            } else {
                $id = $this->repo->insertMulta($idEmpresa, $m, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'Crear multa de condominio', 'condominios_multas_catalogo', $id, null, $m);
            }
            $this->repo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function eliminarMulta(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repo->getMulta($id, $idEmpresa);
        if (!$antes) {
            throw new \DomainException('La multa no existe.');
        }
        $this->repo->deleteMulta($id, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'Eliminar multa de condominio', 'condominios_multas_catalogo', $id, $antes, null);
    }

    // ── Cálculo de la cuota ordinaria (vista previa en pantalla) ─────────────

    /**
     * Cuota ordinaria mensual de un inmueble según su método efectivo. $valores = fila de
     * condominios_alicuotas_valores vigente (tarifa_m2 / monto_a_repartir); si es null, solo
     * se puede resolver el método manual (los otros devuelven null = «sin valor vigente»).
     */
    public function cuotaOrdinaria(array $u, array $cfg, ?array $valores): ?float
    {
        $metodo = $u['metodo_alicuota'] ?? $cfg['metodo_alicuota'] ?? 'porcentaje';
        if ($metodo === 'manual') {
            return $u['monto_manual'] === null ? null : round((float) $u['monto_manual'], 2);
        }
        if (!$valores) {
            return null;
        }
        if ($metodo === 'm2') {
            return round((float) $valores['tarifa_m2'] * (float) $u['area_m2'], 2);
        }
        return round((float) $valores['monto_a_repartir'] * (float) $u['alicuota_pct'] / 100, 2);
    }

    /**
     * Cuota ordinaria de TODOS los inmuebles para un valor dado (vista previa y emisión).
     * - manual: su monto; m²: tarifa × área; %: monto a repartir × %.
     * - Si el monto a repartir viene de un PRESUPUESTO y la configuración dice «repartir el
     *   resto», a los inmuebles por % se les reparte (monto − Σ manuales) normalizando su % sobre
     *   la suma de % de los que van por %; los centavos sobrantes van al de mayor alícuota.
     * Devuelve filas [id, codigo, nombre, metodo, base, cuota|null, fondo, total] + totales.
     */
    public function calcularCuotas(array $unidades, array $cfg, ?array $valor): array
    {
        $metodoCfg = (string) ($cfg['metodo_alicuota'] ?? 'porcentaje');
        $tarifaM2  = $valor ? (float) $valor['tarifa_m2'] : null;
        $monto     = $valor ? (float) $valor['monto_a_repartir'] : null;
        $repartirResto = ($cfg['reparto_manuales'] ?? 'repartir_resto') === 'repartir_resto' && !empty($valor['id_presupuesto']);

        $filas = [];
        $sumManual = 0.0;
        $sumPctPorPct = 0.0;
        foreach ($unidades as $u) {
            $m = $u['metodo_alicuota'] ?: $metodoCfg;
            $filas[] = ['id' => (int) $u['id'], 'codigo' => $u['codigo'], 'nombre' => $u['nombre'], 'propietario' => $u['propietario_nombre'] ?? '',
                        'metodo' => $m, 'area_m2' => (float) $u['area_m2'], 'alicuota_pct' => (float) $u['alicuota_pct'], 'monto_manual' => $u['monto_manual'],
                        'fondo_propio' => $u['fondo_reserva_valor_propio'], 'cuota' => null, 'fondo' => 0.0];
            if ($m === 'manual' && $u['monto_manual'] !== null) {
                $sumManual += (float) $u['monto_manual'];
            } elseif ($m === 'porcentaje') {
                $sumPctPorPct += (float) $u['alicuota_pct'];
            }
        }
        $montoEfectivo = $monto;
        if ($monto !== null && $repartirResto) {
            $montoEfectivo = max(0.0, $monto - $sumManual);
        }
        $idxMayor = null;
        $sumPorPct = 0.0;
        foreach ($filas as $i => &$f) {
            if ($f['metodo'] === 'manual') {
                $f['cuota'] = $f['monto_manual'] === null ? null : round((float) $f['monto_manual'], 2);
            } elseif ($f['metodo'] === 'm2') {
                $f['cuota'] = $tarifaM2 === null ? null : round($tarifaM2 * $f['area_m2'], 2);
            } else {
                if ($montoEfectivo === null) {
                    $f['cuota'] = null;
                } elseif ($repartirResto) {
                    $f['cuota'] = $sumPctPorPct > 0 ? round($montoEfectivo * $f['alicuota_pct'] / $sumPctPorPct, 2) : 0.0;
                } else {
                    $f['cuota'] = round($montoEfectivo * $f['alicuota_pct'] / 100, 2);
                }
                if ($f['cuota'] !== null) {
                    $sumPorPct += $f['cuota'];
                    if ($idxMayor === null || $f['alicuota_pct'] > $filas[$idxMayor]['alicuota_pct']) {
                        $idxMayor = $i;
                    }
                }
            }
        }
        unset($f);
        // Reparto del resto: los centavos de redondeo van al inmueble de mayor alícuota, así la
        // suma de los que van por % cubre exactamente lo que falta del presupuesto.
        if ($repartirResto && $idxMayor !== null && $montoEfectivo !== null) {
            $dif = round($montoEfectivo - $sumPorPct, 2);
            if (abs($dif) >= 0.01 && abs($dif) < 1) {
                $filas[$idxMayor]['cuota'] = round($filas[$idxMayor]['cuota'] + $dif, 2);
            }
        }
        // Fondo de reserva por inmueble (línea separada).
        $tipoFondo = (string) ($cfg['fondo_reserva_tipo'] ?? 'no');
        $valFondo  = (float) ($cfg['fondo_reserva_valor'] ?? 0);
        $tot = ['cuotas' => 0.0, 'fondo' => 0.0, 'manuales' => $sumManual, 'inmuebles' => count($filas), 'sin_cuota' => 0];
        foreach ($filas as &$f) {
            if ($f['fondo_propio'] !== null && $f['fondo_propio'] !== '') {
                $f['fondo'] = round((float) $f['fondo_propio'], 2);
            } elseif ($tipoFondo === 'fijo') {
                $f['fondo'] = round($valFondo, 2);
            } elseif ($tipoFondo === 'porcentaje' && $f['cuota'] !== null) {
                $f['fondo'] = round($f['cuota'] * $valFondo / 100, 2);
            }
            $f['total'] = $f['cuota'] === null ? null : round($f['cuota'] + $f['fondo'], 2);
            if ($f['cuota'] === null) {
                $tot['sin_cuota']++;
            } else {
                $tot['cuotas'] += $f['cuota'];
                $tot['fondo']  += $f['fondo'];
            }
        }
        unset($f);
        $tot['cuotas'] = round($tot['cuotas'], 2);
        $tot['fondo']  = round($tot['fondo'], 2);
        $tot['total']  = round($tot['cuotas'] + $tot['fondo'], 2);
        $tot['monto_a_repartir'] = $monto;
        $tot['monto_efectivo']   = $montoEfectivo;
        $tot['repartir_resto']   = $repartirResto;
        $tot['diferencia']       = $monto === null ? null : round($tot['cuotas'] - $monto, 2);
        return ['filas' => $filas, 'totales' => $tot];
    }

    // ── Reajuste masivo de cuotas de las suscripciones ───────────────────────

    public const REAJUSTE_FORMAS = ['fijo', 'porcentaje', 'inmueble'];

    public function opcionesReajuste(int $idEmpresa): array
    {
        return [
            'productos' => $this->repo->getProductosEnSuscripciones($idEmpresa),
            'reajustes' => $this->repo->getReajustes($idEmpresa),
            'instalado' => $this->repo->tieneReajustes(),
        ];
    }

    /** Normaliza lo que llega del formulario de reajuste. */
    private function normalizarReajuste(array $d, int $idEmpresa): array
    {
        $idProducto = (int) ($d['id_producto'] ?? 0);
        if ($idProducto <= 0 || !$this->repo->getProductoLinea($idProducto, $idEmpresa)) {
            throw new \InvalidArgumentException('Elija el concepto (producto) que se va a reajustar.|#reaj_id_producto');
        }
        $forma = (string) ($d['forma'] ?? 'fijo');
        if (!in_array($forma, self::REAJUSTE_FORMAS, true)) {
            throw new \InvalidArgumentException('La forma de reajuste no es válida.|#reaj_forma');
        }
        $par = round((float) str_replace(',', '.', (string) ($d['parametro'] ?? 0)), 4);
        if ($forma === 'fijo' && $par < 0) {
            throw new \InvalidArgumentException('El monto fijo no puede ser negativo.|#reaj_parametro');
        }
        if ($forma === 'porcentaje' && ($par <= -100 || $par == 0)) {
            throw new \InvalidArgumentException('Indique el % de aumento (o de rebaja, negativo, mayor que −100).|#reaj_parametro');
        }
        $fecha = CondominioRules::fecha($d['fecha_aplicar'] ?? date('Y-m-d'), 'aplicación', '#reaj_fecha_aplicar');
        if ($fecha < date('Y-m-d')) {
            throw new \InvalidArgumentException('La fecha de aplicación no puede ser pasada.|#reaj_fecha_aplicar');
        }
        $desc = trim((string) ($d['descripcion'] ?? ''));
        if ($desc === '') {
            throw new \InvalidArgumentException('Describa el reajuste (p. ej. «Reajuste 2027, acta N.º 5»).|#reaj_descripcion');
        }
        $excluir = array_map('intval', is_array($d['excluir'] ?? null) ? $d['excluir'] : array_filter(explode(',', (string) ($d['excluir'] ?? ''))));
        return ['id_producto' => $idProducto, 'forma' => $forma, 'parametro' => $par, 'fecha_aplicar' => $fecha, 'descripcion' => mb_substr($desc, 0, 200),
                'incluir_sin_inmueble' => in_array($d['incluir_sin_inmueble'] ?? '', ['1', 'true', 'on'], true), 'excluir' => $excluir];
    }

    /**
     * Vista previa del reajuste: una fila por suscripción con valor actual → nuevo. Nada se graba.
     * - fijo: todas quedan en el monto (si no tienen la línea, se agregará).
     * - porcentaje: actual × (1 + %/100); sin línea → se omite (no hay base).
     * - inmueble: cuota calculada con el valor que rige en la fecha de aplicación; sin inmueble → se omite.
     */
    public function previsualizarReajuste(array $d, int $idEmpresa): array
    {
        $cfg = $this->exigirConfig($idEmpresa);
        $r = $this->normalizarReajuste($d, $idEmpresa);
        $susc = $this->repo->getSuscripcionesParaReajuste($idEmpresa, $r['id_producto'], $r['incluir_sin_inmueble']);
        $cuotas = [];
        if ($r['forma'] === 'inmueble') {
            $valor = $this->repo->getValorVigente($idEmpresa, $r['fecha_aplicar']);
            foreach ($this->calcularCuotas($this->repo->getUnidadesParaCuota($idEmpresa), $cfg, $valor)['filas'] as $f) {
                $cuotas[(int) $f['id']] = $f['cuota'];
            }
        }
        $filas = [];
        $omitidas = 0;
        $sumA = 0.0;
        $sumN = 0.0;
        foreach ($susc as $s) {
            $actual = $s['id_detalle'] ? round((float) $s['actual'], 2) : null;
            $nuevo = null;
            $motivo = null;
            if ($r['forma'] === 'fijo') {
                $nuevo = round($r['parametro'], 2);
            } elseif ($r['forma'] === 'porcentaje') {
                if ($actual === null) {
                    $motivo = 'Sin línea del concepto: no hay base para el %';
                } else {
                    $nuevo = round($actual * (1 + $r['parametro'] / 100), 2);
                }
            } else {
                if (empty($s['id_unidad'])) {
                    $motivo = 'Sin inmueble enlazado';
                } elseif (!isset($cuotas[(int) $s['id_unidad']]) || $cuotas[(int) $s['id_unidad']] === null) {
                    $motivo = 'El inmueble no tiene cuota con el valor vigente';
                } else {
                    $nuevo = $cuotas[(int) $s['id_unidad']];
                }
            }
            $excluida = in_array((int) $s['id_suscripcion'], $r['excluir'], true);
            if ($nuevo === null) {
                $omitidas++;
            }
            $filas[] = [
                'id_suscripcion' => (int) $s['id_suscripcion'], 'id_detalle' => $s['id_detalle'] ? (int) $s['id_detalle'] : null,
                'id_unidad' => $s['id_unidad'] ? (int) $s['id_unidad'] : null, 'cliente' => $s['cliente'], 'identificacion' => $s['identificacion'],
                'inmueble' => $s['unidad_codigo'] ? trim($s['unidad_codigo'] . ' · ' . $s['unidad_nombre']) : null,
                'actual' => $actual, 'nuevo' => $nuevo, 'cambia' => $nuevo !== null && $nuevo !== $actual, 'motivo' => $motivo, 'excluida' => $excluida,
            ];
            if ($nuevo !== null && !$excluida) {
                $sumA += (float) $actual;
                $sumN += $nuevo;
            }
        }
        $aplicables = array_filter($filas, fn($f) => $f['nuevo'] !== null && !$f['excluida']);
        return [
            'reajuste' => $r,
            'filas'    => $filas,
            'totales'  => ['total' => count($filas), 'aplicables' => count($aplicables), 'omitidas' => $omitidas, 'excluidas' => count($r['excluir']),
                           'suma_actual' => round($sumA, 2), 'suma_nueva' => round($sumN, 2), 'diferencia' => round($sumN - $sumA, 2),
                           'programado' => $r['fecha_aplicar'] > date('Y-m-d')],
        ];
    }

    /**
     * Aplica (hoy) o programa (fecha futura) el reajuste. Siempre recalcula en el servidor: lo que
     * se graba es lo que la vista previa mostró, menos las filas que el usuario destildó.
     */
    public function aplicarReajuste(array $d, int $idEmpresa, int $idUsuario): array
    {
        if (!$this->repo->tieneReajustes()) {
            throw new \DomainException('Falta aplicar database/migrations/20261006_condominios_reajustes.sql.');
        }
        $prev = $this->previsualizarReajuste($d, $idEmpresa);
        $filas = array_values(array_filter($prev['filas'], fn($f) => $f['nuevo'] !== null && !$f['excluida']));
        if (!$filas) {
            throw new \DomainException('No hay suscripciones a las que aplicar el reajuste.');
        }
        $r = $prev['reajuste'] + ['filas' => $filas, 'suma_actual' => $prev['totales']['suma_actual'], 'suma_nueva' => $prev['totales']['suma_nueva']];
        $programado = $prev['totales']['programado'];
        $r['estado'] = 'pendiente';

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->insertReajuste($idEmpresa, $r, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $programado ? 'Programar reajuste de cuotas' : 'Aplicar reajuste de cuotas', 'condominios_reajustes', $id, null,
                ['descripcion' => $r['descripcion'], 'forma' => $r['forma'], 'parametro' => $r['parametro'], 'fecha_aplicar' => $r['fecha_aplicar'], 'suscripciones' => count($filas), 'suma_actual' => $r['suma_actual'], 'suma_nueva' => $r['suma_nueva']]);
            $resultado = null;
            if (!$programado) {
                $resultado = $this->ejecutarReajuste($idEmpresa, $id, $filas, $r['id_producto'], $idUsuario);
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
        return ['id' => $id, 'programado' => $programado, 'suscripciones' => count($filas), 'resultado' => $resultado];
    }

    /**
     * Escribe el valor nuevo en cada suscripción (línea existente → precio; sin línea → se agrega
     * con el producto, IVA del producto y cantidad 1). Deja el reajuste en 'aplicado'. Debe
     * llamarse dentro de una transacción.
     */
    private function ejecutarReajuste(int $idEmpresa, int $idReajuste, array $filas, int $idProducto, int $idUsuario): string
    {
        $suscRepo = new \App\repositories\modulos\SuscripcionesRepository();
        $prod = $this->repo->getProductoLinea($idProducto, $idEmpresa);
        if (!$prod) {
            throw new \DomainException('El producto del reajuste ya no existe.');
        }
        $actualizadas = 0;
        $agregadas = 0;
        foreach ($filas as $f) {
            $nuevo = round((float) $f['nuevo'], 2);
            if (!empty($f['id_detalle'])) {
                $suscRepo->updatePrecioDetalle((int) $f['id_detalle'], $idEmpresa, $nuevo, $idUsuario);
                $actualizadas++;
            } else {
                $suscRepo->insertDetalle([
                    'id_suscripcion' => (int) $f['id_suscripcion'], 'id_empresa' => $idEmpresa, 'id_producto' => $idProducto,
                    'descripcion' => $prod['nombre'], 'cantidad' => 1, 'precio_unitario' => $nuevo,
                    'porcentaje_iva' => (float) $prod['porcentaje_iva'], 'id_tarifa_iva' => $prod['id_tarifa_iva'], 'orden' => 99, 'id_usuario' => $idUsuario,
                ]);
                $agregadas++;
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'Reajuste de cuota (condominio)', 'suscripciones', (int) $f['id_suscripcion'],
                ['precio_unitario' => $f['actual']], ['precio_unitario' => $nuevo, 'id_reajuste' => $idReajuste]);
        }
        $res = "{$actualizadas} línea(s) actualizada(s), {$agregadas} agregada(s).";
        $this->repo->marcarReajuste($idReajuste, 'aplicado', $res, $idUsuario);
        return $res;
    }

    public function cancelarReajuste(int $id, int $idEmpresa, int $idUsuario): void
    {
        $r = $this->repo->getReajuste($id, $idEmpresa);
        if (!$r) {
            throw new \DomainException('El reajuste no existe.');
        }
        if ($r['estado'] !== 'pendiente') {
            throw new \DomainException('Solo se cancelan reajustes programados que aún no se aplicaron.');
        }
        $this->repo->marcarReajuste($id, 'cancelado', 'Cancelado por el usuario.', $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'Cancelar reajuste programado', 'condominios_reajustes', $id, ['estado' => 'pendiente'], ['estado' => 'cancelado']);
    }

    /**
     * Cron fijo diario: aplica los reajustes programados cuya fecha llegó, de todas las empresas.
     * Cada uno en su propia transacción; un error no detiene a los demás (queda en 'error').
     * @return array<int,string> id_reajuste => mensaje
     */
    public function aplicarReajustesProgramados(): array
    {
        $out = [];
        foreach ($this->repo->getReajustesVencidos() as $r) {
            $idEmpresa = (int) $r['id_empresa'];
            $this->repo->beginTransaction();
            try {
                $res = $this->ejecutarReajuste($idEmpresa, (int) $r['id'], $r['filas'], (int) $r['id_producto'], (int) ($r['created_by'] ?? 0));
                $this->log->registrar((int) ($r['created_by'] ?? 0), $idEmpresa, 'Aplicar reajuste programado (automático)', 'condominios_reajustes', (int) $r['id'], null, ['resultado' => $res]);
                $this->repo->commit();
                $out[(int) $r['id']] = "Empresa {$idEmpresa}: {$r['descripcion']} → {$res}";
            } catch (\Throwable $e) {
                $this->repo->rollBack();
                try {
                    $this->repo->marcarReajuste((int) $r['id'], 'error', $e->getMessage(), (int) ($r['created_by'] ?? 0));
                } catch (\Throwable $e2) {
                    // sin más remedio: queda pendiente y se reintenta mañana
                }
                $out[(int) $r['id']] = "Empresa {$idEmpresa}: ERROR {$e->getMessage()}";
            }
        }
        return $out;
    }

    // ── Valores que rigen ────────────────────────────────────────────────────

    public function listarValores(int $idEmpresa): array
    {
        $hoy = date('Y-m-d');
        $vig = $this->repo->getValorVigente($idEmpresa, $hoy);
        $out = $this->repo->getValores($idEmpresa);
        foreach ($out as &$v) {
            $v['vigente'] = $vig && (int) $vig['id'] === (int) $v['id'];
        }
        unset($v);
        return $out;
    }

    /** Normaliza lo que llega del formulario de «Nuevo valor desde…». */
    private function normalizarValor(array $d, int $idEmpresa): array
    {
        $mes = trim((string) ($d['vigente_desde'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes .= '-01';
        }
        $mes = CondominioRules::fecha($mes, 'inicio de vigencia', '#val_vigente_desde');
        $mes = substr($mes, 0, 7) . '-01';
        $tarifa = round((float) str_replace(',', '.', (string) ($d['tarifa_m2'] ?? 0)), 4);
        $monto  = round((float) str_replace(',', '.', (string) ($d['monto_a_repartir'] ?? 0)), 2);
        $idPres = (int) ($d['id_presupuesto'] ?? 0) ?: null;
        $idVer  = null;
        if ($idPres) {
            $pres = null;
            foreach ($this->repo->getPresupuestosAprobados($idEmpresa) as $p) {
                if ((int) $p['id'] === $idPres) {
                    $pres = $p;
                }
            }
            if (!$pres) {
                throw new \InvalidArgumentException('El presupuesto elegido no está aprobado o no existe.|#val_id_presupuesto');
            }
            $idVer = (int) $pres['id_version'];
            // El monto mensual a repartir sale del presupuesto: gastos del mes desde el que rige.
            $monto = round((float) ($pres['gastos_mes'][substr($mes, 0, 7)] ?? 0), 2);
            if ($monto <= 0) {
                throw new \InvalidArgumentException('El presupuesto no tiene costos ni gastos presupuestados en ' . substr($mes, 0, 7) . '.|#val_id_presupuesto');
            }
        }
        if ($tarifa < 0 || $monto < 0) {
            throw new \InvalidArgumentException('Los valores no pueden ser negativos.|#val_monto_a_repartir');
        }
        if ($tarifa == 0 && $monto == 0) {
            throw new \InvalidArgumentException('Indique la tarifa por m², el monto a repartir, o elija un presupuesto.|#val_monto_a_repartir');
        }
        return ['vigente_desde' => $mes, 'tarifa_m2' => $tarifa, 'monto_a_repartir' => $monto, 'id_presupuesto' => $idPres, 'id_presupuesto_version' => $idVer,
                'acta' => mb_substr(trim((string) ($d['acta'] ?? '')), 0, 120) ?: null, 'observacion' => trim((string) ($d['observacion'] ?? '')) ?: null];
    }

    /** Vista previa: cuota de cada inmueble con el valor propuesto (nada se graba). */
    public function previsualizarValor(array $d, int $idEmpresa): array
    {
        $cfg = $this->exigirConfig($idEmpresa);
        $v = $this->normalizarValor($d, $idEmpresa);
        $calc = $this->calcularCuotas($this->repo->getUnidadesParaCuota($idEmpresa), $cfg, $v);
        $vig  = $this->repo->getValorVigente($idEmpresa, date('Y-m-d'));
        $calc['valor'] = $v;
        $calc['anterior'] = $vig ? $this->calcularCuotas($this->repo->getUnidadesParaCuota($idEmpresa), $cfg, $vig)['totales'] : null;
        return $calc;
    }

    public function guardarValor(array $d, int $idEmpresa, int $idUsuario): int
    {
        $this->exigirConfig($idEmpresa);
        $v = $this->normalizarValor($d, $idEmpresa);
        if ($this->repo->existeValorDesde($idEmpresa, $v['vigente_desde'])) {
            throw new \InvalidArgumentException('Ya hay un valor que rige desde ese mes. Elimínelo o elija otro mes.|#val_vigente_desde');
        }
        $this->repo->beginTransaction();
        try {
            $id = $this->repo->insertValor($idEmpresa, $v, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Nuevo valor de alícuota que rige', 'condominios_alicuotas_valores', $id, null, $v);
            $this->repo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function eliminarValor(int $id, int $idEmpresa, int $idUsuario): void
    {
        $v = $this->repo->getValor($id, $idEmpresa);
        if (!$v) {
            throw new \DomainException('El valor no existe.');
        }
        $this->repo->deleteValor($id, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'Eliminar valor de alícuota que rige', 'condominios_alicuotas_valores', $id, $v, null);
    }

    public function presupuestosParaValor(int $idEmpresa): array
    {
        return $this->repo->getPresupuestosAprobados($idEmpresa);
    }

    // ── Apoyo ────────────────────────────────────────────────────────────────

    private function exigirInstalado(): void
    {
        if (!$this->repo->instalado()) {
            throw new \DomainException('El módulo Condominios aún no está instalado en la base de datos (falta aplicar database/migrations/20261005_condominios.sql).');
        }
    }

    private function exigirConfig(int $idEmpresa): array
    {
        $this->exigirInstalado();
        $cfg = $this->repo->getConfig($idEmpresa);
        if (!$cfg) {
            throw new \DomainException('Primero guarde la configuración del condominio.');
        }
        return $cfg;
    }

    private function validarProducto(int $idProducto, int $idEmpresa, string $para, string $sel): void
    {
        $p = $this->repo->getProducto($idProducto, $idEmpresa);
        if (!$p) {
            throw new \InvalidArgumentException("El producto elegido para {$para} no existe en esta empresa.|{$sel}");
        }
        if (($p['tipo_produccion'] ?? '') !== '02') {
            throw new \InvalidArgumentException("El producto elegido para {$para} debe ser un servicio (en Productos, tipo «Servicio»).|{$sel}");
        }
    }

    private function validarPersonas(array $u, int $idEmpresa): void
    {
        if (!$this->repo->getCliente($u['id_propietario'], $idEmpresa)) {
            throw new \InvalidArgumentException('El propietario no existe como cliente en esta empresa.|#uni_propietario_txt');
        }
        if ($u['id_arrendatario'] && !$this->repo->getCliente($u['id_arrendatario'], $idEmpresa)) {
            throw new \InvalidArgumentException('El arrendatario no existe como cliente en esta empresa.|#uni_arrendatario_txt');
        }
    }

    private function soloCampos(array $fila, array $campos): array
    {
        return array_intersect_key($fila, array_flip($campos));
    }

    public function esTrue(mixed $v): bool
    {
        return in_array($v, [true, 1, '1', 't', 'true'], true);
    }

    // ── Excel de inmuebles ────────────────────────────────────────────────────

    public const EXCEL_CABECERAS = [
        'CODIGO', 'NOMBRE', 'TIPO', 'TORRE_BLOQUE', 'PISO', 'AREA_M2', 'ALICUOTA_PCT',
        'PROPIETARIO_IDENTIFICACION', 'PROPIETARIO_NOMBRE', 'PROPIETARIO_EMAIL', 'PROPIETARIO_TELEFONO',
        'ARRENDATARIO_IDENTIFICACION', 'ARRENDATARIO_NOMBRE', 'PAGADOR', 'METODO', 'MONTO_MANUAL',
        'FONDO_RESERVA_PROPIO', 'OBSERVACIONES',
    ];

    public function plantillaExcel(int $idEmpresa): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $h = $libro->getActiveSheet();
        $h->setTitle('Inmuebles');
        $h->fromArray([self::EXCEL_CABECERAS], null, 'A1');
        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::EXCEL_CABECERAS));
        $h->getStyle("A1:{$ultima}1")->getFont()->setBold(true);
        $h->fromArray([
            ['DPTO-101', 'Dpto 101', 'departamento', 'Torre A', '1', 95.50, 1.250000, '1712345678', 'Juan Pérez', 'juan@correo.com', '0991234567', '', '', 'propietario', '', '', '', ''],
            ['P-12', 'Parqueadero 12', 'parqueadero', 'Subsuelo', '-1', 12.00, 0.150000, '1712345678', 'Juan Pérez', '', '', '', '', 'propietario', '', '', '', ''],
            ['LOCAL-1', 'Local 1', 'local', 'Planta baja', '0', 60.00, 0.900000, '1790012345001', 'Comercial XYZ S.A.', '', '', '0987654321', 'María López', 'arrendatario', 'manual', 350.00, '', 'Monto acordado en asamblea 2026'],
        ], null, 'A2');
        foreach (range('A', $ultima) as $col) {
            $h->getColumnDimension($col)->setAutoSize(true);
        }
        $h->getStyle('A2:A1000')->getNumberFormat()->setFormatCode('@');
        $h->getStyle('H2:H1000')->getNumberFormat()->setFormatCode('@');
        $h->getStyle('L2:L1000')->getNumberFormat()->setFormatCode('@');

        $inst = $libro->createSheet();
        $inst->setTitle('Instrucciones');
        $inst->fromArray([
            ['CARGA DE UNIDADES DEL CONDOMINIO'],
            ['- Una fila por inmueble (departamento, local, oficina, parqueadero, bodega, casa, otro). Cada inmueble emite su propio recibo o factura.'],
            ['- CODIGO único por condominio (DPTO-302, P-12). NOMBRE opcional (si falta, se usa el código).'],
            ['- TIPO: departamento | local | oficina | parqueadero | bodega | casa | otro.'],
            ['- AREA_M2 y ALICUOTA_PCT según la escritura. La suma de ALICUOTA_PCT debería dar 100 (se avisa si no).'],
            ['- PROPIETARIO_IDENTIFICACION es obligatoria: se cruza con Clientes por cédula/RUC. Si el cliente no existe se crea con PROPIETARIO_NOMBRE (obligatorio en ese caso), EMAIL y TELEFONO.'],
            ['- ARRENDATARIO_* es opcional; igual cruce con Clientes. PAGADOR: propietario | arrendatario (vacío = propietario).'],
            ['- METODO: porcentaje | m2 | manual | vacío (= el del condominio). MONTO_MANUAL solo si METODO = manual. El comprobante (recibo/factura) se define en la suscripción del inmueble.'],
            ['- FONDO_RESERVA_PROPIO: valor propio del inmueble (vacío = regla del condominio).'],
            ['- Las filas de ejemplo se pueden borrar. No cambie los encabezados.'],
            ['- Si un inmueble ya existe (mismo CODIGO), se ACTUALIZA con los datos del archivo.'],
        ], null, 'A1');
        $inst->getColumnDimension('A')->setWidth(130);
        $libro->setActiveSheetIndex(0);
        return $libro;
    }

    /**
     * Lee el Excel y devuelve una vista previa: filas normalizadas con su acción (crear/actualizar),
     * avisos y errores por fila. No graba nada; `aplicarExcel()` graba lo aprobado.
     */
    public function leerExcel(string $ruta, int $idEmpresa): array
    {
        $cfg = $this->exigirConfig($idEmpresa);
        try {
            $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
        } catch (\Throwable $e) {
            throw new \RuntimeException('El archivo no es un Excel válido o está dañado.');
        }
        $h = $libro->getSheetByName('Inmuebles') ?? $libro->getSheet(0);
        $datos = $h->toArray(null, true, false, false);
        $cab = array_map(fn($v) => strtoupper(trim((string) $v)), $datos[0] ?? []);
        $idx = [];
        foreach (self::EXCEL_CABECERAS as $nombre) {
            $pos = array_search($nombre, $cab, true);
            if ($pos === false && in_array($nombre, ['CODIGO', 'TIPO', 'PROPIETARIO_IDENTIFICACION'], true)) {
                throw new \RuntimeException("Falta la columna {$nombre} en el Excel. Descargue la plantilla del módulo.");
            }
            $idx[$nombre] = $pos === false ? null : $pos;
        }
        $leer = fn(array $fila, string $col) => $idx[$col] === null ? '' : trim((string) ($fila[$idx[$col]] ?? ''));

        $clientes = new ClienteRepository();
        $filas = [];
        $errores = [];
        $codigos = [];
        $sumaPct = 0.0;
        foreach (array_slice($datos, 1) as $i => $fila) {
            $n = $i + 2;
            if (trim(implode('', array_map(fn($v) => (string) $v, $fila))) === '') {
                continue;
            }
            $codigo = $leer($fila, 'CODIGO');
            if ($codigo === '') {
                $errores[] = "Fila {$n}: falta el código.";
                continue;
            }
            if (isset($codigos[strtoupper($codigo)])) {
                $errores[] = "Fila {$n}: el código «{$codigo}» está repetido en el archivo.";
                continue;
            }
            $codigos[strtoupper($codigo)] = true;

            $identProp = preg_replace('/\D/', '', $leer($fila, 'PROPIETARIO_IDENTIFICACION'));
            if ($identProp === '') {
                $errores[] = "Fila {$n} ({$codigo}): falta la identificación del propietario.";
                continue;
            }
            $prop = $clientes->findByIdentificacion($idEmpresa, $identProp);
            $propNombre = $leer($fila, 'PROPIETARIO_NOMBRE');
            if (!$prop && $propNombre === '') {
                $errores[] = "Fila {$n} ({$codigo}): el propietario {$identProp} no existe como cliente y no viene su nombre para crearlo.";
                continue;
            }
            $identArr = preg_replace('/\D/', '', $leer($fila, 'ARRENDATARIO_IDENTIFICACION'));
            $arr = $identArr !== '' ? $clientes->findByIdentificacion($idEmpresa, $identArr) : null;
            $arrNombre = $leer($fila, 'ARRENDATARIO_NOMBRE');
            if ($identArr !== '' && !$arr && $arrNombre === '') {
                $errores[] = "Fila {$n} ({$codigo}): el arrendatario {$identArr} no existe como cliente y no viene su nombre para crearlo.";
                continue;
            }

            $d = [
                'codigo' => $codigo,
                'nombre' => $leer($fila, 'NOMBRE'),
                'tipo'   => strtolower($leer($fila, 'TIPO')) ?: 'departamento',
                'torre_bloque' => $leer($fila, 'TORRE_BLOQUE'),
                'piso' => $leer($fila, 'PISO'),
                'area_m2' => $leer($fila, 'AREA_M2'),
                'alicuota_pct' => $leer($fila, 'ALICUOTA_PCT'),
                'id_propietario' => $prop ? (int) $prop['id'] : -1, // -1 = se creará
                'id_arrendatario' => $arr ? (int) $arr['id'] : ($identArr !== '' ? -1 : 0),
                'pagador' => strtolower($leer($fila, 'PAGADOR')) ?: 'propietario',
                'metodo_alicuota' => strtolower($leer($fila, 'METODO')) === 'porcentaje' ? 'porcentaje' : (strtolower($leer($fila, 'METODO')) ?: null),
                'monto_manual' => $leer($fila, 'MONTO_MANUAL'),
                'fondo_reserva_valor_propio' => $leer($fila, 'FONDO_RESERVA_PROPIO'),
                'observaciones' => $leer($fila, 'OBSERVACIONES'),
                'estado' => 'activo',
            ];
            try {
                // Validación con ids provisionales (los -1 pasan como "existe"); la definitiva es al aplicar.
                $v = $this->rules->validarUnidad(array_merge($d, ['id_propietario' => $d['id_propietario'] === -1 ? 999999999 : $d['id_propietario'],
                    'id_arrendatario' => $d['id_arrendatario'] === -1 ? 999999998 : $d['id_arrendatario']]), $cfg);
            } catch (\InvalidArgumentException $e) {
                $errores[] = "Fila {$n} ({$codigo}): " . explode('|', $e->getMessage())[0];
                continue;
            }
            $sumaPct += $v['alicuota_pct'];
            $existe = $this->repo->existeCodigo($idEmpresa, $codigo);
            $filas[] = [
                'fila' => $n,
                'accion' => $existe ? 'actualizar' : 'crear',
                'datos' => $d,
                'propietario' => ['identificacion' => $identProp, 'nombre' => $prop ? $prop['nombre'] : $propNombre, 'crear' => !$prop,
                                  'email' => $leer($fila, 'PROPIETARIO_EMAIL'), 'telefono' => $leer($fila, 'PROPIETARIO_TELEFONO')],
                'arrendatario' => $identArr === '' ? null : ['identificacion' => $identArr, 'nombre' => $arr ? $arr['nombre'] : $arrNombre, 'crear' => !$arr],
                'cuota' => $v['metodo_alicuota'] === 'manual' || (!$v['metodo_alicuota'] && $cfg['metodo_alicuota'] === 'manual') ? $v['monto_manual'] : null,
            ];
        }
        $avisos = [];
        if ($filas && abs($sumaPct - 100) > 0.0001) {
            $avisos[] = 'La suma de alícuotas del archivo es ' . number_format($sumaPct, 4) . ' % (lo normal es 100 %). Revise la escritura si el método es por %.';
        }
        return ['filas' => $filas, 'errores' => $errores, 'avisos' => $avisos, 'total' => count($filas),
                'crear' => count(array_filter($filas, fn($f) => $f['accion'] === 'crear')),
                'clientes_nuevos' => count(array_filter($filas, fn($f) => $f['propietario']['crear'] || ($f['arrendatario']['crear'] ?? false)))];
    }

    /** Graba las filas de la vista previa (JSON de leerExcel) en una sola transacción. */
    public function aplicarExcel(array $filas, int $idEmpresa, int $idUsuario): array
    {
        $cfg = $this->exigirConfig($idEmpresa);
        $clientes = new ClienteRepository();
        $creadas = 0;
        $actualizadas = 0;
        $clientesNuevos = 0;
        $this->repo->beginTransaction();
        try {
            foreach ($filas as $f) {
                $d = $f['datos'];
                $d['id_propietario'] = $this->resolverCliente($clientes, $f['propietario'], $idEmpresa, $idUsuario, $clientesNuevos);
                $d['id_arrendatario'] = $f['arrendatario'] ? $this->resolverCliente($clientes, $f['arrendatario'], $idEmpresa, $idUsuario, $clientesNuevos) : 0;
                $u = $this->rules->validarUnidad($d, $cfg);
                $existente = $this->repo->getUnidadPorCodigo($idEmpresa, $u['codigo']);
                if ($existente) {
                    $this->repo->updateUnidad((int) $existente['id'], $idEmpresa, $u, $idUsuario);
                    $vig = $this->repo->getPropietarioVigente((int) $existente['id'], $idEmpresa);
                    if (!$vig || (int) $vig['id_propietario'] !== $u['id_propietario'] || (int) ($vig['id_arrendatario'] ?? 0) !== (int) ($u['id_arrendatario'] ?? 0) || $vig['pagador'] !== $u['pagador']) {
                        $this->repo->abrirPropietario((int) $existente['id'], $idEmpresa, $u['id_propietario'], $u['id_arrendatario'], $u['pagador'], date('Y-m-d'), 'Carga Excel', $idUsuario);
                    }
                    $actualizadas++;
                } else {
                    $id = $this->repo->insertUnidad($idEmpresa, $u, $idUsuario);
                    $this->repo->abrirPropietario($id, $idEmpresa, $u['id_propietario'], $u['id_arrendatario'], $u['pagador'], date('Y-m-d'), 'Carga Excel', $idUsuario);
                    $creadas++;
                }
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'Carga Excel de inmuebles', 'condominios_unidades', null, null,
                ['creadas' => $creadas, 'actualizadas' => $actualizadas, 'clientes_nuevos' => $clientesNuevos]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
        return ['creadas' => $creadas, 'actualizadas' => $actualizadas, 'clientes_nuevos' => $clientesNuevos];
    }

    /** Cliente por identificación; si no existe, lo crea con los datos mínimos del Excel. */
    private function resolverCliente(ClienteRepository $clientes, array $p, int $idEmpresa, int $idUsuario, int &$nuevos): int
    {
        $c = $clientes->findByIdentificacion($idEmpresa, $p['identificacion']);
        if ($c) {
            return (int) $c['id'];
        }
        $nombre = trim((string) ($p['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \DomainException("El cliente {$p['identificacion']} no existe y no viene su nombre para crearlo.");
        }
        $len = strlen($p['identificacion']);
        $tipoId = $len === 13 ? '04' : ($len === 10 ? '05' : '06');
        $id = $clientes->create([
            'id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario, 'nombre' => $nombre, 'tipo_id' => $tipoId,
            'identificacion' => $p['identificacion'], 'telefono' => $p['telefono'] ?? null, 'email' => $p['email'] ?? null,
            'direccion' => null, 'plazo' => 0, 'provincia' => null, 'ciudad' => null, 'status' => 1, 'id_vendedor' => null,
        ]);
        $this->log->registrar($idUsuario, $idEmpresa, 'Crear cliente (carga de inmuebles)', 'clientes', $id, null, ['nombre' => $nombre, 'identificacion' => $p['identificacion']]);
        $nuevos++;
        return $id;
    }
}
