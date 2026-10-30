<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\VehiculoMantenimientoRepository;
use App\Rules\modulos\VehiculoMantenimientoRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Control de mantenimientos por KILOMETRAJE (aceite de motor, aceite de caja y los demás
 * servicios del catálogo de la empresa).
 *
 * Cómo funciona:
 *  - El catálogo (mantenimiento_servicios) dice qué servicios existen y su intervalo por
 *    defecto. La primera vez se crean "Aceite de motor" (5.000 km) y "Aceite de caja" (50.000 km).
 *  - Cada vehículo tiene su intervalo por servicio. Si no lo tiene, se SUGIERE el más usado
 *    entre los vehículos de la misma marca y modelo / motor (ver intervalosEfectivos()); si no
 *    hay ninguno, el del catálogo.
 *  - El cambio se registra en la orden de Car-Wash (casilla "¿Se cambió?"): km de la orden y
 *    próximo cambio (km + intervalo, editable). También a mano desde la ficha del vehículo.
 *  - No hay vínculo con productos: todo se controla con los km registrados.
 *  - El estado (al día / próximo / vencido) se calcula con el km actual conocido frente al
 *    próximo cambio y los km de aviso del servicio.
 *
 * El historial (vehiculos_mantenimientos_historial) es la fuente; vehiculos_mantenimientos
 * guarda una copia del último cambio vigente, que se recalcula bajo candado del vehículo.
 */
class VehiculoMantenimientoService
{
    /** Servicios que se crean la primera vez que la empresa usa el control. */
    public const SERVICIOS_INICIALES = [
        ['nombre' => 'Aceite de motor', 'intervalo_km' => 5000,  'aviso_km' => 500,  'orden' => 1],
        ['nombre' => 'Aceite de caja',  'intervalo_km' => 50000, 'aviso_km' => 3000, 'orden' => 2],
    ];

    private VehiculoMantenimientoRepository $repo;
    private VehiculoMantenimientoRules $rules;
    private LogSistemaService $log;

    public function __construct(?VehiculoMantenimientoRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo  = $repo ?? new VehiculoMantenimientoRepository();
        $this->rules = new VehiculoMantenimientoRules();
        $this->log   = $log ?? new LogSistemaService();
    }

    public function disponible(): bool
    {
        return $this->repo->disponible();
    }

    private function exigirDisponible(): void
    {
        if (!$this->disponible()) {
            throw new Exception('Falta aplicar el SQL de mantenimientos (database/migrations/20261030_vehiculos_mantenimientos.sql).');
        }
    }

    /** Ejecuta $fn en la transacción del llamador o, si no hay, en una propia. */
    private function enTransaccion(callable $fn)
    {
        $db = $this->repo->getDb();
        $propia = !$db->inTransaction();
        if ($propia) $db->beginTransaction();
        try {
            $r = $fn();
            if ($propia) $db->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($propia && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Catálogo de servicios ────────────────────────────────────────────────

    public function servicios(int $idEmpresa, bool $soloActivos = false): array
    {
        if (!$this->disponible()) return [];
        if ($this->repo->contarServiciosHistoricos($idEmpresa) === 0) {
            // Primera vez: se crean los dos servicios de aceite. Si la empresa los elimina
            // después, no vuelven a aparecer (se cuentan también los eliminados).
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            foreach (self::SERVICIOS_INICIALES as $s) {
                $this->repo->crearServicio($s + ['id_empresa' => $idEmpresa, 'activo' => true, 'id_usuario' => $idUsuario ?: null]);
            }
        }
        return array_map([$this, 'normalizarServicio'], $this->repo->getServicios($idEmpresa, $soloActivos));
    }

    private function normalizarServicio(array $s): array
    {
        return [
            'id'           => (int) $s['id'],
            'nombre'       => (string) $s['nombre'],
            'intervalo_km' => (int) $s['intervalo_km'],
            'aviso_km'     => (int) $s['aviso_km'],
            'orden'        => (int) $s['orden'],
            'activo'       => self::bool($s['activo']),
        ];
    }

    /** Crea ($id = 0) o edita un servicio del catálogo. Devuelve el servicio guardado. */
    public function guardarServicio(int $id, int $idEmpresa, int $idUsuario, array $data): array
    {
        $this->exigirDisponible();
        $datos = [
            'nombre'       => trim(preg_replace('/\s+/u', ' ', (string) ($data['nombre'] ?? ''))),
            'intervalo_km' => (int) ($data['intervalo_km'] ?? 0),
            'aviso_km'     => (int) ($data['aviso_km'] ?? 0),
            'orden'        => (int) ($data['orden'] ?? 0),
            'activo'       => !isset($data['activo']) || self::bool($data['activo']),
        ];
        $this->rules->validarServicio($datos);
        if ($this->repo->existeNombreServicio($idEmpresa, $datos['nombre'], $id)) {
            throw new Exception("Ya existe un servicio llamado «{$datos['nombre']}».");
        }

        return $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario, $datos) {
            if ($id > 0) {
                $antes = $this->repo->findServicio($id, $idEmpresa);
                if (!$antes) throw new Exception('Servicio no encontrado.');
                $this->repo->actualizarServicio($id, $idEmpresa, $datos + ['id_usuario' => $idUsuario]);
                $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'mantenimiento_servicios', $id, $antes, $datos);
            } else {
                $id = $this->repo->crearServicio($datos + ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);
                if ($id <= 0) throw new Exception("Ya existe un servicio llamado «{$datos['nombre']}».");
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'mantenimiento_servicios', $id, null, $datos);
            }
            return $this->normalizarServicio($this->repo->findServicio($id, $idEmpresa));
        });
    }

    /** Elimina (lógicamente) un servicio. Los cambios ya registrados se conservan en el historial. */
    public function eliminarServicio(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->exigirDisponible();
        $antes = $this->repo->findServicio($id, $idEmpresa);
        if (!$antes) throw new Exception('Servicio no encontrado.');
        $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario, $antes) {
            $this->repo->eliminarServicio($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'mantenimiento_servicios', $id, $antes, null);
        });
    }

    public function vehiculosConServicio(int $id, int $idEmpresa): int
    {
        return $this->disponible() ? $this->repo->contarVehiculosConServicio($id, $idEmpresa) : 0;
    }

    // ─── Estado del vehículo ──────────────────────────────────────────────────

    /**
     * Intervalo que se usa para cada servicio del vehículo:
     *   1. el propio del vehículo;
     *   2. el más usado entre los vehículos de la misma marca y modelo con el mismo motor,
     *      luego de la misma marca y modelo y luego de la misma marca;
     *   3. el del catálogo.
     *
     * @return array<int, array{intervalo_km:int, origen:string, detalle:string, propio:?int}>
     */
    public function intervalosEfectivos(int $idVehiculo, int $idEmpresa, array $servicios, ?array $estados = null, ?array $vehiculo = null): array
    {
        $estados  ??= $this->repo->getEstados($idVehiculo, $idEmpresa);
        $vehiculo ??= $this->repo->getVehiculo($idVehiculo, $idEmpresa) ?? [];
        $sugeridos = null;
        $out = [];
        foreach ($servicios as $s) {
            $sid = (int) $s['id'];
            $propio = isset($estados[$sid]['intervalo_km']) && $estados[$sid]['intervalo_km'] !== null ? (int) $estados[$sid]['intervalo_km'] : null;
            if ($propio !== null) {
                $out[$sid] = ['intervalo_km' => $propio, 'origen' => 'vehiculo', 'detalle' => 'Intervalo propio del vehículo', 'propio' => $propio];
                continue;
            }
            $sugeridos ??= $this->repo->sugerenciasIntervalo($idVehiculo, $idEmpresa);
            $mejor = null;
            foreach ($sugeridos[$sid] ?? [] as $c) {
                if ($mejor === null
                    || (int) $c['nivel'] < (int) $mejor['nivel']
                    || ((int) $c['nivel'] === (int) $mejor['nivel'] && ((int) $c['n'] > (int) $mejor['n']
                        || ((int) $c['n'] === (int) $mejor['n'] && (string) $c['ultimo'] > (string) $mejor['ultimo'])))) {
                    $mejor = $c;
                }
            }
            if ($mejor) {
                // Total de vehículos de ese nivel (todos los intervalos), para el texto.
                $total = 0;
                foreach ($sugeridos[$sid] as $c) if ((int) $c['nivel'] === (int) $mejor['nivel']) $total += (int) $c['n'];
                $out[$sid] = [
                    'intervalo_km' => (int) $mejor['intervalo_km'], 'origen' => 'sugerido', 'propio' => null,
                    'detalle' => $this->textoSugerencia((int) $mejor['nivel'], (int) $mejor['n'], $total, $vehiculo),
                ];
                continue;
            }
            $out[$sid] = ['intervalo_km' => (int) $s['intervalo_km'], 'origen' => 'catalogo', 'detalle' => 'Intervalo por defecto del servicio', 'propio' => null];
        }
        return $out;
    }

    private function textoSugerencia(int $nivel, int $n, int $total, array $v): string
    {
        $marca = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) ($v['marca'] ?? ''))), 'UTF-8');
        $grupo = match ($nivel) {
            1 => $marca . ' motor ' . mb_strtoupper(trim((string) ($v['motor'] ?? '')), 'UTF-8'),
            2 => $marca,
            default => explode(' ', $marca)[0],
        };
        $veh = $total === 1 ? 'vehículo' : 'vehículos';
        return $n === $total
            ? "Sugerido de {$total} {$veh} {$grupo}"
            : "Sugerido: lo usan {$n} de {$total} {$veh} {$grupo}";
    }

    /**
     * Estado de cada servicio para la pestaña Mantenimiento (orden o vehículo).
     *
     * $idOrden: la orden abierta. Su cambio va aparte (en_orden) y "último cambio" muestra
     * el anterior a ella, para que al editarla se vea de dónde se parte.
     */
    public function estado(int $idVehiculo, int $idEmpresa, int $idOrden = 0): array
    {
        if (!$this->disponible()) return ['disponible' => false, 'servicios' => []];
        $vehiculo = $this->repo->getVehiculo($idVehiculo, $idEmpresa);
        if (!$vehiculo) throw new Exception('Vehículo no encontrado.');

        $servicios = $this->servicios($idEmpresa);
        $estados   = $this->repo->getEstados($idVehiculo, $idEmpresa);
        $historial = $this->repo->getHistorialVehiculo($idVehiculo, $idEmpresa);
        $intervalos = $this->intervalosEfectivos($idVehiculo, $idEmpresa, $servicios, $estados, $vehiculo);
        $kmActual  = $this->repo->kmActual($idVehiculo, $idEmpresa, $idOrden);
        // Ficha del vehículo: el estado va con el km ESTIMADO de hoy (el mismo de los avisos y
        // del filtro del listado). En la orden manda el km escrito en ella (lo calcula la pantalla).
        $est = null;
        if ($idOrden === 0) {
            $est = $this->repo->estimacion($idVehiculo, $idEmpresa, MantenimientoAvisoService::kmDia(MantenimientoAvisoService::KM_MES_DEFECTO));
        }
        $kmRef = $est ? (int) $est['km_est'] : $kmActual;

        $enOrden = []; $previo = [];
        foreach ($historial as $h) { // ordenado por km descendente
            $sid = (int) $h['id_servicio'];
            if ($idOrden && (int) ($h['id_orden'] ?? 0) === $idOrden) { $enOrden[$sid] ??= $h; continue; }
            $previo[$sid] ??= $h;
        }

        $out = [];
        foreach ($servicios as $s) {
            $sid = (int) $s['id'];
            // Un servicio inactivo solo aparece si esta orden ya registró su cambio.
            if (!$s['activo'] && !isset($enOrden[$sid])) continue;
            $p   = $previo[$sid] ?? null;
            $eo  = $enOrden[$sid] ?? null;
            // Próximo vigente (con el cambio de la orden, si lo hay).
            $vigente = isset($estados[$sid]['proximo_km']) && $estados[$sid]['proximo_km'] !== null ? (int) $estados[$sid]['proximo_km'] : null;
            $out[] = [
                'id_servicio'      => $sid,
                'nombre'           => $s['nombre'],
                'activo'           => $s['activo'],
                'aviso_km'         => $s['aviso_km'],
                'intervalo_km'     => $intervalos[$sid]['intervalo_km'],
                'intervalo_propio' => $intervalos[$sid]['propio'],
                'intervalo_origen' => $intervalos[$sid]['origen'],
                'intervalo_detalle'=> $intervalos[$sid]['detalle'],
                'ultimo_km'        => $p ? (int) $p['km'] : null,
                'ultima_fecha'     => $p['fecha'] ?? null,
                'ultimo_orden'     => $p['numero_orden'] ?? null,
                'proximo_km'       => $p ? (int) $p['proximo_km'] : null,
                'en_orden'         => $eo ? ['km' => (int) $eo['km'], 'proximo_km' => (int) $eo['proximo_km'], 'intervalo_km' => $eo['intervalo_km'] !== null ? (int) $eo['intervalo_km'] : null] : null,
                'proximo_vigente'  => $vigente,
            ] + self::calcularEstado($kmRef, $vigente, (int) $s['aviso_km']);
        }

        return [
            'disponible' => true,
            'vehiculo'   => ['id' => (int) $vehiculo['id'], 'placa' => $vehiculo['placa'], 'marca' => $vehiculo['marca'], 'motor' => $vehiculo['motor'] ?? null],
            'km_actual'  => $kmActual,
            'estimacion' => $est ? [
                'km_est'      => (int) $est['km_est'],
                'km_ult'      => (int) $est['km_ult'],
                'fecha_ult'   => $est['f_ult'],
                'km_dia'      => (float) $est['km_dia'],
                'dia_defecto' => self::bool($est['dia_defecto']),
            ] : null,
            'servicios'  => $out,
        ];
    }

    /**
     * al_dia | proximo | vencido | sin_datos, y los km que faltan (negativo = pasado).
     * Mismo cálculo que la pantalla (mantEstado en JS).
     */
    public static function calcularEstado(?int $kmActual, ?int $proximoKm, int $avisoKm): array
    {
        if ($proximoKm === null) return ['estado' => 'sin_datos', 'faltan_km' => null];
        if ($kmActual === null) return ['estado' => 'al_dia', 'faltan_km' => null];
        $faltan = $proximoKm - $kmActual;
        $estado = $faltan <= 0 ? 'vencido' : ($faltan <= $avisoKm ? 'proximo' : 'al_dia');
        return ['estado' => $estado, 'faltan_km' => $faltan];
    }

    public function historial(int $idVehiculo, int $idEmpresa): array
    {
        return $this->disponible() ? $this->repo->getHistorialVehiculo($idVehiculo, $idEmpresa) : [];
    }

    // ─── Registro desde la orden de Car-Wash ──────────────────────────────────

    /**
     * Guarda los mantenimientos de una orden (pestaña Mantenimiento). Va dentro de la
     * transacción del guardado de la orden.
     *
     * $items: [{id_servicio, cambiado, intervalo_km, intervalo_editado, proximo_km}]
     *  - cambiado: se hizo el cambio en esta orden → km = km de la orden, próximo = el enviado
     *    o km + intervalo. Desmarcarlo da de baja el cambio que la orden tenía.
     *  - intervalo_editado: el usuario cambió "Cada (km)" → queda como intervalo propio del
     *    vehículo. Solo con $puedeConfigurar (permiso de modificar); si no, se ignora.
     *  Al registrar un cambio, el intervalo usado queda como propio del vehículo.
     */
    public function aplicarDesdeOrden(int $idOrden, int $idEmpresa, int $idUsuario, int $idVehiculo, ?int $km, string $fecha, array $items, bool $puedeConfigurar): void
    {
        if (!$this->disponible()) return;
        $fecha = substr($fecha, 0, 10);

        $this->enTransaccion(function () use ($idOrden, $idEmpresa, $idUsuario, $idVehiculo, $km, $fecha, $items, $puedeConfigurar) {
            $existentes = $this->repo->getHistorialOrden($idOrden, $idEmpresa);
            // Candado de cada vehículo tocado (el de la orden y, si se cambió de vehículo, el anterior).
            $vehiculos = array_unique(array_merge([$idVehiculo], array_map(fn($r) => (int) $r['id_vehiculo'], $existentes)));
            sort($vehiculos);
            foreach ($vehiculos as $v) $this->repo->bloquearVehiculo($v);

            $servicios = [];
            foreach ($this->servicios($idEmpresa) as $s) $servicios[$s['id']] = $s;
            $intervalos = $this->intervalosEfectivos($idVehiculo, $idEmpresa, array_values($servicios));

            $afectados = [];  // "vehiculo:servicio" => [v, s]
            $cambios   = [];  // para la auditoría
            $vistos    = [];
            foreach ($items as $it) {
                $sid = (int) ($it['id_servicio'] ?? 0);
                if (!isset($servicios[$sid]) || isset($vistos[$sid])) continue;
                $vistos[$sid] = true;
                $nombre = $servicios[$sid]['nombre'];

                $intervalo = $intervalos[$sid]['intervalo_km'];
                $editado   = $puedeConfigurar && self::bool($it['intervalo_editado'] ?? false) && (int) ($it['intervalo_km'] ?? 0) > 0;
                if ($editado) {
                    $intervalo = (int) $it['intervalo_km'];
                    $this->rules->validarIntervalo($intervalo, $nombre);
                }
                $previo = $existentes[$sid] ?? null;

                if (self::bool($it['cambiado'] ?? false)) {
                    $proximo = (int) ($it['proximo_km'] ?? 0);
                    if ($proximo <= 0 && $km !== null) $proximo = $km + $intervalo;
                    $this->rules->validarCambio($km, $proximo, $nombre);
                    $datos = ['id_vehiculo' => $idVehiculo, 'km' => $km, 'fecha' => $fecha, 'intervalo_km' => $intervalo, 'proximo_km' => $proximo, 'id_usuario' => $idUsuario];
                    if ($previo) {
                        $igual = (int) $previo['id_vehiculo'] === $idVehiculo && (int) $previo['km'] === $km
                            && (string) $previo['fecha'] === $fecha && (int) $previo['proximo_km'] === $proximo
                            && (int) ($previo['intervalo_km'] ?? 0) === $intervalo;
                        if (!$igual) {
                            $this->repo->actualizarHistorial((int) $previo['id'], $idEmpresa, $datos);
                            $cambios[] = ['servicio' => $nombre, 'accion' => 'actualizado', 'km' => $km, 'proximo_km' => $proximo];
                        }
                        $afectados[$previo['id_vehiculo'] . ':' . $sid] = [(int) $previo['id_vehiculo'], $sid];
                    } else {
                        $this->repo->crearHistorial($datos + ['id_empresa' => $idEmpresa, 'id_servicio' => $sid, 'origen' => 'orden', 'id_orden' => $idOrden]);
                        $cambios[] = ['servicio' => $nombre, 'accion' => 'registrado', 'km' => $km, 'proximo_km' => $proximo];
                    }
                    // El intervalo con que se hizo el cambio queda como el del vehículo.
                    if ($intervalos[$sid]['propio'] !== $intervalo) {
                        $this->repo->setIntervalo($idEmpresa, $idVehiculo, $sid, $intervalo, $idUsuario);
                    }
                    $afectados[$idVehiculo . ':' . $sid] = [$idVehiculo, $sid];
                } else {
                    if ($previo) {
                        $this->repo->bajaHistorial((int) $previo['id'], $idEmpresa, $idUsuario);
                        $cambios[] = ['servicio' => $nombre, 'accion' => 'quitado', 'km' => (int) $previo['km']];
                        $afectados[$previo['id_vehiculo'] . ':' . $sid] = [(int) $previo['id_vehiculo'], $sid];
                    }
                    if ($editado && $intervalos[$sid]['propio'] !== $intervalo) {
                        $this->repo->setIntervalo($idEmpresa, $idVehiculo, $sid, $intervalo, $idUsuario);
                        $cambios[] = ['servicio' => $nombre, 'accion' => 'intervalo', 'intervalo_km' => $intervalo];
                    }
                }
            }
            // La orden cambió de vehículo: los cambios que quedaron en el anterior (servicios
            // que no llegaron en $items) se dan de baja.
            foreach ($existentes as $sid => $r) {
                if (!isset($vistos[$sid]) && (int) $r['id_vehiculo'] !== $idVehiculo) {
                    $this->repo->bajaHistorial((int) $r['id'], $idEmpresa, $idUsuario);
                    $afectados[$r['id_vehiculo'] . ':' . $sid] = [(int) $r['id_vehiculo'], $sid];
                }
            }

            foreach ($afectados as [$v, $s]) $this->repo->recalcularEstado($idEmpresa, $v, $s, $idUsuario);
            if ($cambios) {
                $this->log->registrar($idUsuario, $idEmpresa, 'MANTENIMIENTOS_ORDEN_CARWASH', 'carwash_ordenes', $idOrden, null,
                    ['id_vehiculo' => $idVehiculo, 'km' => $km, 'cambios' => $cambios]);
            }
        });
    }

    /**
     * La orden se eliminó ($porAnulacion = false) o se anuló (true): sus cambios se dan de
     * baja y el próximo cambio vuelve al anterior. Los de una anulación vuelven si la orden
     * se reactiva (restaurarPorOrden).
     */
    public function darDeBajaPorOrden(int $idOrden, int $idEmpresa, int $idUsuario, bool $porAnulacion): void
    {
        if (!$this->disponible()) return;
        $this->enTransaccion(function () use ($idOrden, $idEmpresa, $idUsuario, $porAnulacion) {
            $rows = $this->repo->getHistorialOrden($idOrden, $idEmpresa);
            if (!$rows) return;
            $vehiculos = array_unique(array_map(fn($r) => (int) $r['id_vehiculo'], $rows));
            sort($vehiculos);
            foreach ($vehiculos as $v) $this->repo->bloquearVehiculo($v);
            foreach ($rows as $r) $this->repo->bajaHistorial((int) $r['id'], $idEmpresa, $idUsuario, $porAnulacion);
            foreach ($rows as $r) $this->repo->recalcularEstado($idEmpresa, (int) $r['id_vehiculo'], (int) $r['id_servicio'], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $porAnulacion ? 'MANTENIMIENTOS_ORDEN_ANULADA' : 'MANTENIMIENTOS_ORDEN_ELIMINADA',
                'carwash_ordenes', $idOrden, ['cambios' => array_values($rows)], null);
        });
    }

    /** La orden anulada se reactivó: vuelven los cambios que dio de baja la anulación. */
    public function restaurarPorOrden(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        if (!$this->disponible()) return;
        $this->enTransaccion(function () use ($idOrden, $idEmpresa, $idUsuario) {
            $rest = $this->repo->restaurarPorAnulacion($idOrden, $idEmpresa, $idUsuario);
            foreach ($rest as $r) {
                $this->repo->bloquearVehiculo((int) $r['id_vehiculo']);
                $this->repo->recalcularEstado($idEmpresa, (int) $r['id_vehiculo'], (int) $r['id_servicio'], $idUsuario);
            }
        });
    }

    // ─── Ficha del vehículo ───────────────────────────────────────────────────

    /** Cambio hecho fuera (o no registrado en una orden): km, fecha y próximo cambio. */
    public function registrarManual(int $idVehiculo, int $idEmpresa, int $idUsuario, array $data): int
    {
        $this->exigirDisponible();
        if (!$this->repo->getVehiculo($idVehiculo, $idEmpresa)) throw new Exception('Vehículo no encontrado.');
        $sid = (int) ($data['id_servicio'] ?? 0);
        $serv = $this->repo->findServicio($sid, $idEmpresa);
        if (!$serv) throw new Exception('Elija el servicio.');

        $km    = ($data['km'] ?? '') === '' ? null : (int) $data['km'];
        $fecha = trim((string) ($data['fecha'] ?? ''));
        $intervalo = (int) ($data['intervalo_km'] ?? 0);
        if ($intervalo <= 0) {
            $intervalo = $this->intervalosEfectivos($idVehiculo, $idEmpresa, [$this->normalizarServicio($serv)])[$sid]['intervalo_km'];
        }
        $this->rules->validarIntervalo($intervalo, (string) $serv['nombre']);
        $proximo = (int) ($data['proximo_km'] ?? 0);
        if ($proximo <= 0 && $km !== null) $proximo = $km + $intervalo;
        $this->rules->validarCambio($km, $proximo, (string) $serv['nombre'], $fecha);
        $obs = trim((string) ($data['observacion'] ?? ''));

        return $this->enTransaccion(function () use ($idVehiculo, $idEmpresa, $idUsuario, $sid, $km, $fecha, $intervalo, $proximo, $obs) {
            $this->repo->bloquearVehiculo($idVehiculo);
            $id = $this->repo->crearHistorial([
                'id_empresa' => $idEmpresa, 'id_vehiculo' => $idVehiculo, 'id_servicio' => $sid, 'km' => $km, 'fecha' => $fecha,
                'intervalo_km' => $intervalo, 'proximo_km' => $proximo, 'origen' => 'manual', 'observacion' => $obs, 'id_usuario' => $idUsuario,
            ]);
            $this->repo->setIntervalo($idEmpresa, $idVehiculo, $sid, $intervalo, $idUsuario);
            $this->repo->recalcularEstado($idEmpresa, $idVehiculo, $sid, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'vehiculos_mantenimientos_historial', $id, null,
                ['id_vehiculo' => $idVehiculo, 'id_servicio' => $sid, 'km' => $km, 'fecha' => $fecha, 'proximo_km' => $proximo, 'origen' => 'manual']);
            return $id;
        });
    }

    /** Quita un cambio registrado a mano (los de una orden se quitan desde la orden). */
    public function eliminarCambioManual(int $idHistorial, int $idEmpresa, int $idUsuario, ?int $idVehiculo = null): void
    {
        $this->exigirDisponible();
        $h = $this->repo->findHistorial($idHistorial, $idEmpresa);
        if (!$h) throw new Exception('Cambio no encontrado.');
        if ($idVehiculo !== null && (int) $h['id_vehiculo'] !== $idVehiculo) throw new Exception('El cambio no pertenece a este vehículo.');
        if ($h['origen'] !== 'manual') throw new Exception('Este cambio se registró en una orden de Car-Wash: quítelo desde la orden (pestaña Mantenimiento).');
        $this->enTransaccion(function () use ($h, $idHistorial, $idEmpresa, $idUsuario) {
            $this->repo->bloquearVehiculo((int) $h['id_vehiculo']);
            $this->repo->bajaHistorial($idHistorial, $idEmpresa, $idUsuario);
            $this->repo->recalcularEstado($idEmpresa, (int) $h['id_vehiculo'], (int) $h['id_servicio'], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'vehiculos_mantenimientos_historial', $idHistorial, $h, null);
        });
    }

    /**
     * Intervalo propio del vehículo para un servicio ($intervalo = null: lo quita y vuelve a
     * usar el sugerido). Los próximos cambios ya registrados no se tocan.
     */
    public function guardarIntervalo(int $idVehiculo, int $idServicio, int $idEmpresa, int $idUsuario, ?int $intervalo): void
    {
        $this->exigirDisponible();
        if (!$this->repo->getVehiculo($idVehiculo, $idEmpresa)) throw new Exception('Vehículo no encontrado.');
        $serv = $this->repo->findServicio($idServicio, $idEmpresa);
        if (!$serv) throw new Exception('Servicio no encontrado.');
        $this->rules->validarIntervalo($intervalo, (string) $serv['nombre']);
        $this->enTransaccion(function () use ($idVehiculo, $idServicio, $idEmpresa, $idUsuario, $intervalo) {
            $antes = $this->repo->getEstados($idVehiculo, $idEmpresa)[$idServicio]['intervalo_km'] ?? null;
            $this->repo->setIntervalo($idEmpresa, $idVehiculo, $idServicio, $intervalo, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'vehiculos_mantenimientos', $idVehiculo,
                ['id_servicio' => $idServicio, 'intervalo_km' => $antes], ['id_servicio' => $idServicio, 'intervalo_km' => $intervalo]);
        });
    }

    /** Cuántos vehículos más tienen la misma marca y modelo / la misma marca. */
    public function alcanceMismaMarca(int $idVehiculo, int $idEmpresa): array
    {
        $this->exigirDisponible();
        $v = $this->repo->getVehiculo($idVehiculo, $idEmpresa);
        if (!$v) throw new Exception('Vehículo no encontrado.');
        $marca = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $v['marca'])), 'UTF-8');
        $out = ['modelo' => ['texto' => $marca, 'n' => count($this->repo->vehiculosMismaMarca($idVehiculo, $idEmpresa, 'modelo'))]];
        $primera = explode(' ', $marca)[0];
        if ($primera !== $marca) {
            $out['marca'] = ['texto' => $primera, 'n' => count($this->repo->vehiculosMismaMarca($idVehiculo, $idEmpresa, 'marca'))];
        }
        return $out;
    }

    /**
     * Pone el mismo intervalo (este vehículo incluido) a los demás de la misma marca y modelo
     * ('modelo') o de la misma marca ('marca'). Devuelve cuántos vehículos se modificaron.
     */
    public function aplicarIntervaloMismaMarca(int $idVehiculo, int $idServicio, int $idEmpresa, int $idUsuario, string $alcance, int $intervalo): int
    {
        $this->exigirDisponible();
        $alcance = $alcance === 'marca' ? 'marca' : 'modelo';
        $serv = $this->repo->findServicio($idServicio, $idEmpresa);
        if (!$serv) throw new Exception('Servicio no encontrado.');
        if (!$this->repo->getVehiculo($idVehiculo, $idEmpresa)) throw new Exception('Vehículo no encontrado.');
        $this->rules->validarIntervalo($intervalo, (string) $serv['nombre']);
        $ids = $this->repo->vehiculosMismaMarca($idVehiculo, $idEmpresa, $alcance);
        $ids[] = $idVehiculo;
        return $this->enTransaccion(function () use ($ids, $idServicio, $idEmpresa, $idUsuario, $intervalo, $alcance, $idVehiculo) {
            foreach ($ids as $v) $this->repo->setIntervalo($idEmpresa, $v, $idServicio, $intervalo, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'APLICAR_INTERVALO_MISMA_MARCA', 'vehiculos_mantenimientos', $idVehiculo, null,
                ['id_servicio' => $idServicio, 'intervalo_km' => $intervalo, 'alcance' => $alcance, 'vehiculos' => $ids]);
            return count($ids);
        });
    }

    // ─── PDF / correo / factura de la orden ───────────────────────────────────

    /**
     * Mantenimientos de una orden: lo hecho en ella y el próximo cambio vigente de los demás.
     * @return array<int, array{servicio:string, realizado:bool, km_cambio:?int, proximo_km:?int}>
     */
    public function resumenOrden(int $idOrden, int $idVehiculo, int $idEmpresa): array
    {
        if (!$this->disponible() || $idVehiculo <= 0) return [];
        $out = [];
        foreach ($this->repo->resumenOrden($idOrden, $idVehiculo, $idEmpresa) as $r) {
            $realizado = $r['km_cambio'] !== null;
            $out[] = [
                'servicio'   => (string) $r['nombre'],
                'realizado'  => $realizado,
                'km_cambio'  => $realizado ? (int) $r['km_cambio'] : ($r['ultimo_km'] !== null ? (int) $r['ultimo_km'] : null),
                'proximo_km' => $realizado ? (int) $r['proximo_orden'] : ($r['proximo_km'] !== null ? (int) $r['proximo_km'] : null),
            ];
        }
        return $out;
    }

    public static function km(?int $km): string
    {
        return $km === null ? '' : number_format($km, 0, ',', '.') . ' km';
    }

    private static function bool($v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true' || $v === 'on';
    }
}
