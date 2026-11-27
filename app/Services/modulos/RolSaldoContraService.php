<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\CatalogoNovedades;
use App\repositories\modulos\NovedadRepository;
use App\repositories\modulos\RolPagoRepository;
use App\Services\LogSistemaService;

/**
 * Empleados con valor a pagar EN CONTRA en el rol MENSUAL (neto negativo): mantiene, por
 * cada uno, una novedad de DESCUENTO en el mes siguiente por ese valor, marcada con el rol
 * que la generó (novedades.id_rol_origen).
 *
 * Se llama cada vez que el rol mensual se genera/recalcula, y al anularlo o eliminarlo:
 *   - empleado en contra sin descuento        → lo crea (Descuento, aplica al rol mensual).
 *   - empleado en contra con otro valor       → actualiza el valor.
 *   - empleado que ya no está en contra       → elimina el descuento (lógico).
 *   - rol anulado o eliminado                 → elimina todos sus descuentos.
 * Lo que se buscó por id_rol_origen se respeta aunque el usuario haya movido el descuento
 * de mes. No se toca un descuento cuyo rol (el del mes donde se descuenta) ya está pagado o
 * contabilizado para ese empleado: ahí ya se cobró (getDetalle lo avisa).
 *
 * Después regenera los roles del mes afectado, que a su vez sincronizan los suyos (cadena
 * hacia adelante; un candado por rol evita ciclos).
 *
 * Sin database/2026-11-27_roles_saldo_en_contra.sql no hace nada (solo queda el aviso).
 */
class RolSaldoContraService
{
    /** Roles que se están sincronizando en esta petición (evita reentrada). */
    private static array $enCurso = [];

    private RolPagoRepository $rolRepo;
    private NovedadRepository $novRepo;
    private LogSistemaService $log;

    public function __construct(RolPagoRepository $rolRepo, LogSistemaService $log, ?NovedadRepository $novRepo = null)
    {
        $this->rolRepo = $rolRepo;
        $this->log     = $log;
        $this->novRepo = $novRepo ?? new NovedadRepository();
    }

    public function disponible(): bool
    {
        return $this->novRepo->tieneRolOrigen();
    }

    /** Período siguiente: [anio, mes]. */
    public static function periodoSiguiente(int $anio, int $mes): array
    {
        return $mes >= 12 ? [$anio + 1, 1] : [$anio, $mes + 1];
    }

    public static function observacion(array $cab): string
    {
        $mes = CatalogoNovedades::MESES[(int) $cab['periodo_mes']] ?? $cab['periodo_mes'];
        return 'Saldo en contra del rol de ' . $mes . ' ' . $cab['periodo_anio']
            . ' (descuento automático: se actualiza o elimina solo si cambia ese rol).';
    }

    /**
     * Sincroniza los descuentos del rol. $anulado = true al anular/eliminar el rol (los
     * elimina todos). Luego regenera los roles mensuales donde cambió un descuento.
     */
    public function sincronizar(int $idRol, int $idEmpresa, int $idUsuario, bool $anulado = false): void
    {
        if (isset(self::$enCurso[$idRol]) || !$this->disponible()) {
            return;
        }
        $cab = $anulado ? null : $this->rolRepo->findCabecera($idRol, $idEmpresa);
        if (!$anulado) {
            if (!$cab || ($cab['tipo_rol'] ?? '') !== 'MENSUAL') return;
            if (($cab['estado'] ?? '') === 'anulado') $anulado = true;
        }

        self::$enCurso[$idRol] = true;
        try {
            $afectados = $this->aplicar($idRol, $idEmpresa, $idUsuario, $cab, $anulado);
            // Regenerar los roles mensuales donde cambió algún descuento (fuera de la transacción).
            if ($afectados) {
                $rolSvc = new RolPagoService($this->rolRepo, new \App\Rules\modulos\RolPagoRules(), $this->log);
                foreach ($afectados as [$anio, $mes]) {
                    $rolSvc->regenerarAfectados($idEmpresa, 'rol', $anio, $mes, $idUsuario);
                }
            }
        } finally {
            unset(self::$enCurso[$idRol]);
        }
    }

    /** Escribe los cambios en una transacción. @return array<string,array{int,int}> períodos afectados */
    private function aplicar(int $idRol, int $idEmpresa, int $idUsuario, ?array $cab, bool $anulado): array
    {
        $netos = $anulado ? [] : $this->rolRepo->getNetosDelRol($idRol, $idEmpresa);
        $enContra = [];
        foreach ($netos as $idEmp => $neto) {
            if ($neto < 0) $enContra[$idEmp] = round(-$neto, 2);
        }
        $existentes = [];
        foreach ($this->novRepo->getPorRolOrigen($idRol, $idEmpresa) as $n) {
            $existentes[(int) $n['id_empleado']] = $n;
        }
        if (!$enContra && !$existentes) return [];

        $afectados = [];
        $propia = $this->rolRepo->beginTransactionPropia();
        try {
            // Crear o actualizar.
            if ($enContra) {
                [$anioSig, $mesSig] = self::periodoSiguiente((int) $cab['periodo_anio'], (int) $cab['periodo_mes']);
                $obs = self::observacion($cab);
                foreach ($enContra as $idEmp => $valor) {
                    $n = $existentes[$idEmp] ?? null;
                    unset($existentes[$idEmp]);
                    if ($n === null) {
                        if ($this->congelado($idEmpresa, $idEmp, $anioSig, $mesSig)) continue;
                        $data = [
                            'id_empresa' => $idEmpresa, 'id_empleado' => $idEmp,
                            'tipo_codigo' => '2', 'tipo_nombre' => CatalogoNovedades::nombreTipo('2') ?? 'Descuento',
                            'fecha' => sprintf('%04d-%02d-01', $anioSig, $mesSig),
                            'periodo_mes' => $mesSig, 'periodo_anio' => $anioSig, 'valor' => $valor,
                            'aplica_en' => 'rol', 'observacion' => $obs, 'estado' => 'activo',
                            'id_usuario' => $idUsuario, 'id_rol_origen' => $idRol,
                        ];
                        $id = $this->novRepo->create($data);
                        $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'novedades', $id, null, $data);
                        $afectados[$anioSig . '-' . $mesSig] = [$anioSig, $mesSig];
                    } elseif (abs((float) $n['valor'] - $valor) > 0.004) {
                        if ($this->congelado($idEmpresa, $idEmp, (int) $n['periodo_anio'], (int) $n['periodo_mes'])) continue;
                        $this->novRepo->actualizarValorSaldoContra((int) $n['id'], $idEmpresa, $valor, $obs, $idUsuario);
                        $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'novedades', (int) $n['id'], $n, ['valor' => $valor]);
                        $afectados[$n['periodo_anio'] . '-' . $n['periodo_mes']] = [(int) $n['periodo_anio'], (int) $n['periodo_mes']];
                    }
                }
            }
            // Los que quedaron ya no están en contra (o el rol se anuló): eliminar.
            foreach ($existentes as $idEmp => $n) {
                if ($this->congelado($idEmpresa, $idEmp, (int) $n['periodo_anio'], (int) $n['periodo_mes'])) continue;
                $this->novRepo->deleteLogic((int) $n['id'], $idEmpresa, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'novedades', (int) $n['id'], $n, null);
                $afectados[$n['periodo_anio'] . '-' . $n['periodo_mes']] = [(int) $n['periodo_anio'], (int) $n['periodo_mes']];
            }
            if ($propia) $this->rolRepo->commit();
        } catch (\Throwable $e) {
            if ($propia) $this->rolRepo->rollBack();
            throw $e;
        }
        return $afectados;
    }

    /** ¿El rol mensual donde se descuenta ya está pagado/contabilizado para ese empleado? */
    private function congelado(int $idEmpresa, int $idEmpleado, int $anio, int $mes): bool
    {
        return $this->rolRepo->existeRolPagadoPeriodo($idEmpresa, $idEmpleado, 'MENSUAL', $anio, $mes);
    }

    /**
     * Para el aviso del rol: [id_empleado => ['valor','periodo_anio','periodo_mes']] de los
     * descuentos automáticos ya registrados por este rol.
     */
    public function descuentosDelRol(int $idRol, int $idEmpresa): array
    {
        if (!$this->disponible()) return [];
        $out = [];
        foreach ($this->novRepo->getPorRolOrigen($idRol, $idEmpresa) as $n) {
            $out[(int) $n['id_empleado']] = [
                'valor' => round((float) $n['valor'], 2),
                'periodo_anio' => (int) $n['periodo_anio'],
                'periodo_mes' => (int) $n['periodo_mes'],
            ];
        }
        return $out;
    }
}
