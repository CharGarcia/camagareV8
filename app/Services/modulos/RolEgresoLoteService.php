<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\RolPagoRepository;
use App\repositories\modulos\EgresoRepository;
use App\Rules\modulos\EgresoRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;

/**
 * Genera en lote los egresos de nómina de un rol: un egreso individual por cada
 * empleado con saldo pendiente. Reutiliza EgresoService::registrar (numeración,
 * validación de período y asiento contable). Cada egreso es independiente: si uno
 * falla, los demás igual se crean y se reporta el error.
 */
class RolEgresoLoteService
{
    private LogSistemaService $log;

    public function __construct(?LogSistemaService $log = null)
    {
        $this->log = $log ?? new LogSistemaService();
    }

    /**
     * @param array $opts ['fecha'=>string, 'id_forma_pago'=>int, 'id_punto_emision'=>int,
     *                     'ids_detalle'=>int[] líneas marcadas; vacío = todos los pendientes]
     * @return array{creados:int,total:float,omitidos:int,errores:array<int,array{empleado:string,error:string}>}
     */
    public function generar(int $idRol, int $idEmpresa, int $idUsuario, array $opts): array
    {
        $db = Database::getConnection();
        $rolRepo = new RolPagoRepository();

        $cab = $rolRepo->findCabecera($idRol, $idEmpresa);
        if (!$cab) {
            throw new \Exception('Rol no encontrado.');
        }
        if (in_array($cab['estado'], ['borrador', 'anulado'], true)) {
            throw new \Exception('El rol debe estar generado para pagarlo. Estado actual: ' . $cab['estado'] . '.');
        }

        $fecha   = !empty($opts['fecha']) ? $opts['fecha'] : date('Y-m-d');
        $idForma = (int) ($opts['id_forma_pago'] ?? 0);
        $idPunto = (int) ($opts['id_punto_emision'] ?? 0);
        if ($idForma <= 0) throw new \Exception('Seleccione una forma de pago.');
        if ($idPunto <= 0) throw new \Exception('Seleccione un punto de emisión.');

        // Operación bancaria (cheque/transferencia). Cheque: se numera consecutivo por egreso.
        $tipoOp    = strtoupper(trim((string) ($opts['tipo_operacion_bancaria'] ?? '')));
        $chequeNum = (int) ($opts['numero_cheque_inicial'] ?? 0);
        if ($tipoOp === 'CHEQUE' && $chequeNum <= 0) {
            throw new \Exception('Ingrese el número inicial del cheque.');
        }
        // Fecha en que se podrá cobrar el cheque (posfechados). Si no viene, se usa la fecha del egreso.
        $fechaCobro = trim((string) ($opts['fecha_cobro'] ?? ''));

        // Concepto de egreso de Nómina (comportamiento ROL).
        $stC = $db->prepare("SELECT id FROM empresa_opciones_ingreso_egreso
                             WHERE id_empresa = :e AND comportamiento = 'ROL' AND aplica_egresos = true
                               AND eliminado = false AND UPPER(estado) = 'ACTIVO' LIMIT 1");
        $stC->execute([':e' => $idEmpresa]);
        $idConcepto = (int) $stC->fetchColumn();
        if ($idConcepto <= 0) {
            throw new \Exception('No existe un concepto de egreso de Nómina (comportamiento ROL). Créelo en opciones de ingreso/egreso.');
        }

        // Códigos de establecimiento/punto para el número de egreso.
        $stP = $db->prepare("SELECT e.codigo AS est, p.codigo_punto AS pto
                             FROM empresa_punto_emision p JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                             WHERE p.id = :idp");
        $stP->execute([':idp' => $idPunto]);
        $pRow = $stP->fetch(\PDO::FETCH_ASSOC);
        if (!$pRow) throw new \Exception('Punto de emisión no válido.');
        $est = str_pad((string) $pRow['est'], 3, '0', STR_PAD_LEFT);
        $pto = str_pad((string) $pRow['pto'], 3, '0', STR_PAD_LEFT);

        $detalle = $rolRepo->getDetalleCompleto($idRol, $idEmpresa); // incluye pagado/saldo por empleado
        $egSvc   = new EgresoService(new EgresoRepository(), new EgresoRules(), $this->log);
        $secSvc  = new SecuencialService();
        $periodo = str_pad((string) $cab['periodo_mes'], 2, '0', STR_PAD_LEFT) . '/' . $cab['periodo_anio'];

        // Empleados marcados por el usuario. Si no viene ninguno se procesan todos
        // los pendientes (comportamiento anterior). Se filtra contra el detalle real
        // del rol, así que un id ajeno simplemente no coincide con ninguna línea.
        $idsMarcados = array_values(array_unique(array_filter(
            array_map('intval', (array) ($opts['ids_detalle'] ?? [])),
            fn($v) => $v > 0
        )));
        $soloMarcados = !empty($idsMarcados);
        if ($soloMarcados) {
            $seleccion = array_flip($idsMarcados);
            $coinciden = array_filter($detalle, fn($d) => isset($seleccion[(int) $d['id']]));
            if (empty($coinciden)) {
                throw new \Exception('Los empleados seleccionados no pertenecen a este rol.');
            }
        }

        $creados = 0; $total = 0.0; $omitidos = 0; $noSeleccionados = 0; $errores = [];

        foreach ($detalle as $d) {
            $saldo = round((float) ($d['saldo'] ?? 0), 2);
            $nombre = (string) ($d['nombres_apellidos'] ?? ('#' . ($d['id_empleado'] ?? '')));
            if ($saldo <= 0.01) { $omitidos++; continue; } // ya pagado
            if ($soloMarcados && !isset($seleccion[(int) $d['id']])) {
                $noSeleccionados++; continue; // pendiente, pero el usuario no lo marcó
            }

            try {
                $sec = (int) ($secSvc->obtenerSiguienteSecuencial($idPunto, 'Egresos')['secuencial'] ?? 0);
                $numero = $est . '-' . $pto . '-' . str_pad((string) $sec, 9, '0', STR_PAD_LEFT);

                $pago = ['id_forma_pago' => $idForma, 'monto' => $saldo];
                if ($tipoOp !== '') {
                    $pago['tipo_operacion_bancaria'] = $tipoOp;
                    if ($tipoOp === 'CHEQUE') {
                        $pago['numero_cheque'] = (string) $chequeNum;
                        $pago['fecha_cobro']   = $fechaCobro !== '' ? $fechaCobro : $fecha;
                    }
                }

                $egSvc->registrar([
                    'id_empresa'        => $idEmpresa,
                    'usuario_id'        => $idUsuario,
                    'id_punto_emision'  => $idPunto,
                    'establecimiento'   => $est,
                    'punto_emision'     => $pto,
                    'secuencial'        => $sec,
                    'numero_egreso'     => $numero,
                    'fecha_emision'     => $fecha,
                    'tipo_egreso'       => 'ROL',
                    'tipo_sujeto'       => 'EMPLEADO',
                    'id_empleado'       => (int) $d['id_empleado'],
                    'id_egreso_concepto' => $idConcepto,
                    'monto_total'       => $saldo,
                    'observaciones'     => 'Pago de rol ' . $periodo . ' - ' . $nombre,
                    'detalles' => [[
                        'tipo_documento'          => 'ROL',
                        'id_referencia_documento' => (int) $d['id'],
                        'numero_documento'        => 'Rol ' . $periodo,
                        'monto_documento'         => (float) $d['neto'],
                        'saldo_anterior'          => $saldo,
                        'monto_pagado'            => $saldo,
                        'saldo_actual'            => 0,
                    ]],
                    'pagos' => [$pago],
                ]);
                $creados++;
                $total += $saldo;
                if ($tipoOp === 'CHEQUE') $chequeNum++; // el siguiente cheque solo si este se registró
            } catch (\Throwable $e) {
                $errores[] = ['empleado' => $nombre, 'error' => $e->getMessage()];
            }
        }

        $this->log->registrar($idUsuario, $idEmpresa, 'EGRESOS_LOTE', 'rol_cabecera', $idRol, null, [
            'creados'          => $creados,
            'total'            => round($total, 2),
            'omitidos'         => $omitidos,
            'no_seleccionados' => $noSeleccionados,
            'seleccion'        => $soloMarcados ? $idsMarcados : 'todos',
            'con_error'        => count($errores),
        ]);

        return [
            'creados'          => $creados,
            'total'            => round($total, 2),
            'omitidos'         => $omitidos,
            'no_seleccionados' => $noSeleccionados,
            'errores'          => $errores,
        ];
    }
}
