<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CajaMovimientoRepository;
use App\Rules\modulos\CajaMovimientoRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * Traslados entre formas de pago y saldos de apertura del Resumen Diario. Cada guardado
 * va en una transacción con su registro en log_sistema. No generan asientos contables.
 */
class CajaMovimientoService
{
    private CajaMovimientoRepository $repo;
    private CajaMovimientoRules $rules;
    private LogSistemaService $log;

    public function __construct(?CajaMovimientoRepository $repo = null)
    {
        $this->repo  = $repo ?? new CajaMovimientoRepository();
        $this->rules = new CajaMovimientoRules($this->repo);
        $this->log   = new LogSistemaService();
    }

    /**
     * Registra un traslado. Con la misma clave de formulario ($token) un reintento devuelve
     * el traslado ya creado en vez de crear otro (§8, guardado único).
     *
     * @return array{id: int, ya_existia: bool}
     */
    public function crearTraslado(array $datos, string $token, int $idEmpresa, int $idUsuario): array
    {
        $guardado = new GuardadoUnicoService();
        $this->repo->beginTransaction();
        try {
            if ($previo = $guardado->previo($token, $idEmpresa, 'caja_traslados')) {
                $this->repo->rollBack();
                return ['id' => (int) $previo['id_registro'], 'ya_existia' => true];
            }
            $t  = $this->rules->validarTraslado($datos, $idEmpresa);
            $id = $this->repo->insertarTraslado($t, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR_TRASLADO_CAJA', 'caja_traslados', $id, null, $t);
            $guardado->registrar($token, $idEmpresa, 'caja_traslados', $id, (string) $id, $idUsuario);
            $this->repo->commit();
            return ['id' => $id, 'ya_existia' => false];
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina (lógicamente) un traslado. $idUsuarioFiltro: sin acceso total solo los propios.
     */
    public function eliminarTraslado(int $id, int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro): void
    {
        $this->repo->beginTransaction();
        try {
            $antes = $this->repo->getTraslado($id, $idEmpresa);
            if (!$antes || ($idUsuarioFiltro !== null && (int) $antes['created_by'] !== $idUsuarioFiltro)) {
                throw new \InvalidArgumentException('El traslado no existe o ya fue eliminado.');
            }
            $this->repo->eliminarTraslado($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR_TRASLADO_CAJA', 'caja_traslados', $id, $antes, null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Formas de pago activas con su apertura vigente (fecha y valor vacíos si no tiene). */
    public function listarAperturas(int $idEmpresa): array
    {
        $aperturas = $this->repo->getAperturas($idEmpresa);
        return array_map(static fn (array $f): array => [
            'id_forma_pago' => (int) $f['id'],
            'forma'         => (string) $f['nombre'],
            'fecha'         => (string) ($aperturas[(int) $f['id']]['fecha'] ?? ''),
            'valor'         => isset($aperturas[(int) $f['id']]) ? (float) $aperturas[(int) $f['id']]['valor'] : null,
        ], $this->repo->getFormasPago($idEmpresa));
    }

    /**
     * Guarda las aperturas del formulario: crea, actualiza o quita (fila sin fecha) la de
     * cada forma de pago. Solo toca las que cambiaron. Devuelve cuántas cambió.
     */
    public function guardarAperturas(array $filas, int $idEmpresa, int $idUsuario): int
    {
        $this->repo->beginTransaction();
        try {
            $nuevas    = $this->rules->validarAperturas($filas, $idEmpresa);
            $vigentes  = $this->repo->getAperturas($idEmpresa);
            $cambios   = 0;
            foreach ($nuevas as $idForma => $n) {
                $actual = $vigentes[$idForma] ?? null;
                if ($n === null) {
                    if ($actual) {
                        $this->repo->eliminarApertura((int) $actual['id'], $idEmpresa, $idUsuario);
                        $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR_APERTURA_CAJA', 'caja_saldos_apertura', (int) $actual['id'], $actual, null);
                        $cambios++;
                    }
                    continue;
                }
                if (!$actual) {
                    $id = $this->repo->insertarApertura($idEmpresa, $idForma, $n['fecha'], $n['valor'], $idUsuario);
                    $this->log->registrar($idUsuario, $idEmpresa, 'CREAR_APERTURA_CAJA', 'caja_saldos_apertura', $id, null, ['id_forma_pago' => $idForma] + $n);
                    $cambios++;
                } elseif ($actual['fecha'] !== $n['fecha'] || round((float) $actual['valor'], 2) !== $n['valor']) {
                    $this->repo->actualizarApertura((int) $actual['id'], $idEmpresa, $n['fecha'], $n['valor'], $idUsuario);
                    $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_APERTURA_CAJA', 'caja_saldos_apertura', (int) $actual['id'], $actual, ['id_forma_pago' => $idForma] + $n);
                    $cambios++;
                }
            }
            $this->repo->commit();
            return $cambios;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }
}
