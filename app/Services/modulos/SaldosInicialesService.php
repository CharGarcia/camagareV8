<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\SaldosInicialesRepository;
use App\Rules\modulos\SaldosInicialesRules;
use App\Services\LogSistemaService;
use App\core\Database;

class SaldosInicialesService
{
    private SaldosInicialesRepository $repo;
    private SaldosInicialesRules $rules;
    private LogSistemaService $log;

    public function __construct(
        SaldosInicialesRepository $repo,
        SaldosInicialesRules $rules,
        LogSistemaService $log
    ) {
        $this->repo  = $repo;
        $this->rules = $rules;
        $this->log   = $log;
    }

    // ─────────────────────────────────────────────────────────
    // CXC
    // ─────────────────────────────────────────────────────────

    public function getCxcListado(int $idEmpresa, array $filtros = []): array
    {
        return $this->repo->getCxcListado($idEmpresa, $filtros);
    }

    public function crearCxc(array $data): int
    {
        $this->rules->validarCxc($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->insertCxc($data);
            $this->log->registrar(
                (int)($data['created_by'] ?? 0),
                (int)$data['id_empresa'],
                'CREAR',
                'saldos_iniciales_cxc',
                $id,
                null,
                $data
            );
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizarCxc(int $id, int $idEmpresa, array $data): void
    {
        $registro = $this->repo->getCxcPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        if ($this->repo->tieneCxcCobros($id)) {
            throw new \RuntimeException('No se puede modificar: el registro ya tiene cobros registrados.');
        }
        $this->rules->validarCxc($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->updateCxc($id, $idEmpresa, $data);
            $this->log->registrar(
                (int)($data['updated_by'] ?? 0),
                $idEmpresa,
                'ACTUALIZAR',
                'saldos_iniciales_cxc',
                $id,
                $registro,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminarCxc(int $id, int $idEmpresa, int $idUsuario): void
    {
        $registro = $this->repo->getCxcPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        if ($this->repo->tieneCxcCobros($id)) {
            throw new \RuntimeException('No se puede eliminar: el registro ya tiene cobros registrados.');
        }
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->deleteCxc($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'saldos_iniciales_cxc', $id, $registro, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function importarCxcDesdeArray(int $idEmpresa, int $idUsuario, array $filas, string $nombreArchivo): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $errores   = [];
            $insertados = 0;
            $idLote    = null;

            foreach ($filas as $i => $fila) {
                $fila['id_empresa'] = $idEmpresa;
                $fila['created_by'] = $idUsuario;
                try {
                    $this->rules->validarCxc($fila);
                    if ($idLote === null) {
                        $idLote = $this->repo->insertLote($idEmpresa, 'CXC', $nombreArchivo, 0, $idUsuario);
                    }
                    $fila['id_lote'] = $idLote;
                    $this->repo->insertCxc($fila);
                    $insertados++;
                } catch (\Throwable $e) {
                    $errores[] = "Fila " . ($i + 2) . ": " . $e->getMessage();
                }
            }

            if ($idLote !== null) {
                $db->prepare("UPDATE saldos_iniciales_lotes SET total_registros = :n WHERE id = :id")
                   ->execute([':n' => $insertados, ':id' => $idLote]);
            }

            $db->commit();
            return ['insertados' => $insertados, 'errores' => $errores];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────
    // CXP
    // ─────────────────────────────────────────────────────────

    public function getCxpListado(int $idEmpresa, array $filtros = []): array
    {
        return $this->repo->getCxpListado($idEmpresa, $filtros);
    }

    public function crearCxp(array $data): int
    {
        $this->rules->validarCxp($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->insertCxp($data);
            $this->log->registrar(
                (int)($data['created_by'] ?? 0),
                (int)$data['id_empresa'],
                'CREAR',
                'saldos_iniciales_cxp',
                $id,
                null,
                $data
            );
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizarCxp(int $id, int $idEmpresa, array $data): void
    {
        $registro = $this->repo->getCxpPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        if ($this->repo->tieneCxpPagos($id)) {
            throw new \RuntimeException('No se puede modificar: el registro ya tiene pagos registrados.');
        }
        $this->rules->validarCxp($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->updateCxp($id, $idEmpresa, $data);
            $this->log->registrar(
                (int)($data['updated_by'] ?? 0),
                $idEmpresa,
                'ACTUALIZAR',
                'saldos_iniciales_cxp',
                $id,
                $registro,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminarCxp(int $id, int $idEmpresa, int $idUsuario): void
    {
        $registro = $this->repo->getCxpPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        if ($this->repo->tieneCxpPagos($id)) {
            throw new \RuntimeException('No se puede eliminar: el registro ya tiene pagos registrados.');
        }
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->deleteCxp($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'saldos_iniciales_cxp', $id, $registro, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function importarCxpDesdeArray(int $idEmpresa, int $idUsuario, array $filas, string $nombreArchivo): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $errores    = [];
            $insertados = 0;
            $idLote     = null;

            foreach ($filas as $i => $fila) {
                $fila['id_empresa'] = $idEmpresa;
                $fila['created_by'] = $idUsuario;
                try {
                    $this->rules->validarCxp($fila);
                    if ($idLote === null) {
                        $idLote = $this->repo->insertLote($idEmpresa, 'CXP', $nombreArchivo, 0, $idUsuario);
                    }
                    $fila['id_lote'] = $idLote;
                    $this->repo->insertCxp($fila);
                    $insertados++;
                } catch (\Throwable $e) {
                    $errores[] = "Fila " . ($i + 2) . ": " . $e->getMessage();
                }
            }

            if ($idLote !== null) {
                $db->prepare("UPDATE saldos_iniciales_lotes SET total_registros = :n WHERE id = :id")
                   ->execute([':n' => $insertados, ':id' => $idLote]);
            }

            $db->commit();
            return ['insertados' => $insertados, 'errores' => $errores];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────
    // BANCOS
    // ─────────────────────────────────────────────────────────

    public function getBancosDisponibles(int $idEmpresa): array
    {
        return $this->repo->getBancosDisponibles($idEmpresa);
    }

    public function guardarBancos(int $idEmpresa, int $idUsuario, array $cuentas): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            foreach ($cuentas as $cuenta) {
                $this->rules->validarBanco($cuenta);
                $this->repo->upsertBanco($idEmpresa, (int)$cuenta['id_forma_pago'], array_merge($cuenta, ['created_by' => $idUsuario]));
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminarBanco(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->deleteBanco($id, $idEmpresa, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────
    // COBROS / PAGOS
    // ─────────────────────────────────────────────────────────

    public function registrarCobroCxc(int $idSaldo, int $idEmpresa, int $idUsuario, array $datos): array
    {
        $saldo = $this->repo->getCxcPorId($idSaldo, $idEmpresa);
        if (!$saldo) {
            throw new \RuntimeException('Saldo inicial no encontrado.');
        }
        $saldoPendiente = (float)$saldo['saldo_pendiente'];
        $monto = (float)$datos['monto'];
        if ($monto <= 0) {
            throw new \RuntimeException('El monto debe ser mayor a 0.');
        }
        if ($monto > $saldoPendiente + 0.001) {
            throw new \RuntimeException("El monto ($monto) supera el saldo pendiente ($saldoPendiente).");
        }

        $punto = $datos['punto'];
        $codEst = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $codPto = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);

        $secuencialService = new \App\Services\SecuencialService();
        $secRes     = $secuencialService->obtenerSiguienteSecuencial((int)$datos['id_punto_emision'], 'Ingresos');
        $secuencial = $secRes['formateado'];
        $numDoc     = "{$codEst}-{$codPto}-{$secuencial}";

        $payload = [
            'id_empresa'         => $idEmpresa,
            'id_establecimiento' => (int)($punto['id_establecimiento'] ?? 0),
            'id_punto_emision'   => (int)$datos['id_punto_emision'],
            'id_cliente'         => !empty($saldo['id_cliente']) ? (int)$saldo['id_cliente'] : null,
            'id_usuario'         => $idUsuario,
            'fecha_emision'      => $datos['fecha_cobro'] ?: date('Y-m-d'),
            'establecimiento'    => $codEst,
            'punto_emision'      => $codPto,
            'secuencial'         => $secuencial,
            'numero_ingreso'     => $numDoc,
            'tipo_ingreso'       => 'SALDO_INICIAL',
            'id_ingreso_concepto'=> !empty($datos['id_ingreso_concepto']) ? (int)$datos['id_ingreso_concepto'] : null,
            'monto_total'        => $monto,
            'observaciones'      => $datos['observaciones'] ?: "Cobro saldo inicial {$saldo['nro_documento']}",
            'recibo_de'          => $saldo['nombre_cliente'],
            'id_recibo_cliente'  => !empty($saldo['id_cliente']) ? (int)$saldo['id_cliente'] : null,
            'detalles' => [[
                'tipo_documento'         => 'SALDO_INICIAL',
                'id_referencia_documento'=> $idSaldo,
                'numero_documento'       => $saldo['nro_documento'],
                'descripcion'            => "Cobro saldo inicial {$saldo['nro_documento']} — {$saldo['nombre_cliente']}",
                'monto_documento'        => $saldo['saldo_inicial'],
                'saldo_anterior'         => $saldoPendiente,
                'monto_cobrado'          => $monto,
                'saldo_actual'           => max(0.0, $saldoPendiente - $monto),
            ]],
            'pagos' => [[
                'id_forma_cobro'         => (int)$datos['id_forma_cobro'],
                'monto'                  => $monto,
                'fecha_cobro'            => $datos['fecha_cobro'] ?: date('Y-m-d'),
                'observaciones'          => $datos['observaciones'] ?: null,
                'tipo_operacion_bancaria'=> $datos['tipo_operacion_bancaria'] ?? null,
                'numero_cheque'          => $datos['numero_operacion'] ?? null,
                'referencia'             => $datos['numero_operacion'] ?? null,
            ]],
        ];

        $ingresoService = new IngresoService(
            new \App\repositories\modulos\IngresoRepository(),
            new \App\Rules\modulos\IngresoRules(),
            new \App\Services\LogSistemaService()
        );
        $idIngreso = $ingresoService->crear($payload);

        $this->repo->actualizarMontoCobradoCxc($idSaldo, $idEmpresa);

        $saldoActualizado = $this->repo->getCxcPorId($idSaldo, $idEmpresa);

        return [
            'id_ingreso'     => $idIngreso,
            'numero_ingreso' => $numDoc,
            'nuevo_saldo'    => number_format((float)$saldoActualizado['saldo_pendiente'], 2, '.', ''),
            'pagado'         => (float)$saldoActualizado['saldo_pendiente'] <= 0.001,
        ];
    }

    public function registrarPagoCxp(int $idSaldo, int $idEmpresa, int $idUsuario, array $datos): array
    {
        $saldo = $this->repo->getCxpPorId($idSaldo, $idEmpresa);
        if (!$saldo) {
            throw new \RuntimeException('Saldo inicial no encontrado.');
        }
        $saldoPendiente = (float)$saldo['saldo_pendiente'];
        $monto = (float)$datos['monto'];
        if ($monto <= 0) {
            throw new \RuntimeException('El monto debe ser mayor a 0.');
        }
        if ($monto > $saldoPendiente + 0.001) {
            throw new \RuntimeException("El monto ($monto) supera el saldo pendiente ($saldoPendiente).");
        }

        $punto = $datos['punto'];
        $codEst = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $codPto = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);

        $secuencialService = new \App\Services\SecuencialService();
        $secRes     = $secuencialService->obtenerSiguienteSecuencial((int)$datos['id_punto_emision'], 'Egresos');
        $secuencial = $secRes['formateado'];
        $numDoc     = "{$codEst}-{$codPto}-{$secuencial}";

        $tipoSujeto = !empty($saldo['id_proveedor']) ? 'PROVEEDOR' : 'OTRO';

        $payload = [
            'id_empresa'        => $idEmpresa,
            'id_punto_emision'  => (int)$datos['id_punto_emision'],
            'id_establecimiento'=> (int)($punto['id_establecimiento'] ?? 0),
            'fecha_emision'     => $datos['fecha_pago'] ?: date('Y-m-d'),
            'establecimiento'   => $codEst,
            'punto_emision'     => $codPto,
            'secuencial'        => $secuencial,
            'numero_egreso'     => $numDoc,
            'tipo_egreso'       => 'SALDO_INICIAL',
            'tipo_sujeto'       => $tipoSujeto,
            'id_proveedor'      => !empty($saldo['id_proveedor']) ? (int)$saldo['id_proveedor'] : null,
            'id_empleado'       => null,
            'id_egreso_concepto'=> !empty($datos['id_egreso_concepto']) ? (int)$datos['id_egreso_concepto'] : null,
            'monto_total'       => $monto,
            'observaciones'     => $datos['observaciones'] ?: "Pago saldo inicial {$saldo['nro_documento']}",
            'estado'            => 'registrado',
            'usuario_id'        => $idUsuario,
            'detalles' => [[
                'tipo_documento'         => 'SALDO_INICIAL',
                'id_referencia_documento'=> $idSaldo,
                'numero_documento'       => $saldo['nro_documento'],
                'descripcion'            => "Pago saldo inicial {$saldo['nro_documento']} — {$saldo['nombre_proveedor']}",
                'monto_documento'        => $saldo['saldo_inicial'],
                'saldo_anterior'         => $saldoPendiente,
                'monto_pagado'           => $monto,
                'saldo_actual'           => max(0.0, $saldoPendiente - $monto),
            ]],
            'pagos' => [[
                'id_forma_pago'          => (int)$datos['id_forma_pago'],
                'monto'                  => $monto,
                'referencia'             => $datos['numero_operacion'] ?? null,
                'tipo_operacion_bancaria'=> $datos['tipo_operacion_bancaria'] ?? null,
                'numero_cheque'          => $datos['numero_operacion'] ?? null,
            ]],
        ];

        $egresoService = new EgresoService(
            new \App\repositories\modulos\EgresoRepository(),
            new \App\Rules\modulos\EgresoRules(),
            new \App\Services\LogSistemaService()
        );
        $idEgreso = $egresoService->registrar($payload);

        $this->repo->actualizarMontoPagadoCxp($idSaldo, $idEmpresa);

        $saldoActualizado = $this->repo->getCxpPorId($idSaldo, $idEmpresa);

        return [
            'id_egreso'     => $idEgreso,
            'numero_egreso' => $numDoc,
            'nuevo_saldo'   => number_format((float)$saldoActualizado['saldo_pendiente'], 2, '.', ''),
            'pagado'        => (float)$saldoActualizado['saldo_pendiente'] <= 0.001,
        ];
    }
}
