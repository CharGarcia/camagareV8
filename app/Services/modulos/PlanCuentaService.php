<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\PlanCuentaRepository;
use App\Rules\modulos\PlanCuentaRules;
use App\Services\LogSistemaService;
use Exception;

class PlanCuentaService
{
    private PlanCuentaRepository $repository;
    private PlanCuentaRules $rules;
    private LogSistemaService $logService;

    public function __construct(PlanCuentaRepository $repository, PlanCuentaRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function crear(array $data): int
    {
        $this->rules->validate($data);
        
        $this->repository->beginTransaction();
        try {
            $data['created_by']  = $data['id_usuario'];
            $id = $this->repository->create($data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'CREAR',
                'plan_cuentas',
                (int)$id,
                null, // antes
                $data // despues
            );
            
            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validate($data);
        $old = $this->repository->findById($id, $idEmpresa);

        $this->repository->beginTransaction();
        try {
            $data['updated_by'] = $data['id_usuario'];
            $this->repository->update($id, $idEmpresa, $data);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$idEmpresa,
                'ACTUALIZAR',
                'plan_cuentas',
                $id,
                $old,
                $data
            );
            
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->findById($id, $idEmpresa);
        if (!$old) throw new Exception('Cuenta no encontrada.');

        if ($this->repository->tieneMovimientos($id, $idEmpresa)) {
            throw new Exception('No se puede eliminar: la cuenta ya tiene movimientos contables registrados.');
        }
        if ($this->repository->estaEnProgramacionContable($id, $idEmpresa)) {
            throw new Exception('No se puede eliminar: la cuenta está configurada en Programación Contable (asientos programados).');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'plan_cuentas',
                $id,
                $old,
                null
            );
            
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina (lógicamente) las cuentas del plan que NO han sido usadas.
     * Conserva las cuentas con movimientos contables y sus cuentas padre (jerarquía).
     * Devuelve ['eliminadas' => int, 'conservadas' => int].
     */
    public function eliminarCuentasNoUsadas(int $idEmpresa, int $idUsuario): array
    {
        $this->repository->beginTransaction();
        try {
            $totalAntes = $this->repository->contarPorEmpresa($idEmpresa);
            $eliminadas = $this->repository->eliminarCuentasNoUsadas($idEmpresa, $idUsuario);
            $conservadas = $totalAntes - $eliminadas;

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR NO USADAS',
                'plan_cuentas',
                null,
                null,
                ['eliminadas' => $eliminadas, 'conservadas' => $conservadas]
            );

            $this->repository->commit();
            return ['eliminadas' => $eliminadas, 'conservadas' => $conservadas];
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Repara la jerarquía del plan: por cada cuenta activa, garantiza que existan todas
     * sus cuentas padre (ancestros por prefijo de código). Las que existen pero están
     * eliminadas lógicamente se restauran; las que no existen se crean.
     * El nombre del padre se toma del plan modelo si el código coincide; si no, se usa
     * un nombre provisional ("CUENTA {codigo}") que el usuario puede renombrar luego.
     * Devuelve ['creadas' => int, 'restauradas' => int, 'detalle' => string[]].
     */
    public function repararCuentasFaltantes(int $idEmpresa, int $idUsuario): array
    {
        $mapa = $this->repository->getMapaCodigos($idEmpresa);

        // Cuentas activas (las visibles en el árbol): codigo => nombre
        $activos = [];
        foreach ($mapa as $codigo => $info) {
            if (!$info['eliminado']) $activos[$codigo] = $info['nombre'];
        }

        // Ancestros requeridos por cada cuenta activa (todos los prefijos).
        // Además, por cada ancestro guardamos el nombre del descendiente más profundo
        // (normalmente la cuenta de movimiento nivel 5) para usarlo como nombre por defecto.
        $requeridos = [];  // codigo => nivel
        $nombreDesc = [];  // codigo ancestro => nombre del descendiente más profundo
        $profDesc   = [];  // codigo ancestro => profundidad de ese descendiente
        foreach ($activos as $codigo => $nombre) {
            $codigo = (string) $codigo; // claves numéricas ("1") vuelven int en PHP
            $partes = explode('.', $codigo);
            $prof   = count($partes);
            for ($i = 1; $i < $prof; $i++) {
                $anc = implode('.', array_slice($partes, 0, $i));
                $requeridos[$anc] = $i; // nivel = número de segmentos
                if (!isset($profDesc[$anc]) || $prof > $profDesc[$anc]) {
                    $profDesc[$anc]   = $prof;
                    $nombreDesc[$anc] = $nombre;
                }
            }
        }

        // Ordenar por profundidad (padres primero) y luego por código
        uksort($requeridos, fn($a, $b) => (strlen((string) $a) <=> strlen((string) $b)) ?: strcmp((string) $a, (string) $b));

        // Lookup de nombres del plan modelo
        $nombresModelo = [];
        foreach (self::getCuentasModeloArray() as $m) {
            $nombresModelo[$m['codigo']] = $m['nombre'];
        }

        $creadas = 0;
        $restauradas = 0;
        $detalle = [];

        $this->repository->beginTransaction();
        try {
            foreach ($requeridos as $codigo => $nivel) {
                $codigo = (string) $codigo; // claves numéricas ("1") vuelven int en PHP
                // Ya existe y está activa: nada que hacer
                if (isset($mapa[$codigo]) && !$mapa[$codigo]['eliminado']) {
                    continue;
                }

                // Existe pero eliminada: restaurar
                if (isset($mapa[$codigo]) && $mapa[$codigo]['eliminado']) {
                    $this->repository->restaurarCuenta($mapa[$codigo]['id'], $idEmpresa, $idUsuario);
                    $mapa[$codigo]['eliminado'] = false;
                    $restauradas++;
                    $detalle[] = $codigo . ' (restaurada)';
                    continue;
                }

                // No existe: crear. Nombre por prioridad:
                //   1) plan modelo (si el código coincide)
                //   2) nombre de la cuenta descendiente más profunda (nivel 5)
                //   3) genérico
                $nombre = $nombresModelo[$codigo]
                    ?? ($nombreDesc[$codigo] ?? ('CUENTA ' . $codigo));
                if ($nivel >= 1 && $nivel <= 4) {
                    $nombre = mb_strtoupper($nombre);
                }

                $id = $this->repository->create([
                    'id_empresa'  => $idEmpresa,
                    'id_usuario'  => $idUsuario,
                    'codigo'      => $codigo,
                    'nivel'       => $nivel,
                    'nombre'      => $nombre,
                    'codigo_sri'  => '',
                    'status'      => 1,
                    'created_by'  => $idUsuario,
                    'id_centro_costos' => null,
                    'id_proyecto' => null,
                    'supercias_esf' => null,
                    'supercias_eri' => null,
                    'supercias_ecp_codigo' => null,
                    'supercias_ecp_subcodigo' => null,
                ]);
                $mapa[$codigo] = ['id' => (int) $id, 'eliminado' => false];
                $creadas++;
                $detalle[] = $codigo . ' - ' . $nombre;
            }

            if ($creadas > 0 || $restauradas > 0) {
                $this->logService->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'REPARAR JERARQUIA',
                    'plan_cuentas',
                    null,
                    null,
                    ['creadas' => $creadas, 'restauradas' => $restauradas, 'detalle' => $detalle]
                );
            }

            $this->repository->commit();
            return ['creadas' => $creadas, 'restauradas' => $restauradas, 'detalle' => $detalle];
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public static function getCuentasModeloArray(): array
    {
        return [
            ['codigo' => '1',              'nivel' => '1', 'nombre' => 'ACTIVOS',                                                   'codigo_sri' => ''],
            ['codigo' => '1.1',            'nivel' => '2', 'nombre' => 'ACTIVOS CORRIENTES',                                        'codigo_sri' => ''],
            ['codigo' => '1.1.1',          'nivel' => '3', 'nombre' => 'EFECTIVO Y EQUIVALENTES DE EFECTIVO',                       'codigo_sri' => ''],
            ['codigo' => '1.1.1.01',       'nivel' => '4', 'nombre' => 'CAJA GENERAL',                                              'codigo_sri' => ''],
            ['codigo' => '1.1.1.01.001',   'nivel' => '5', 'nombre' => 'Caja Chica',                                  'codigo_sri' => '311', 'supercias_esf' => '1010101', 'supercias_eri' => '6555', 'supercias_ecp_codigo' => '99', 'supercias_ecp_subcodigo' => '555'],
            ['codigo' => '1.1.1.01.002',   'nivel' => '5', 'nombre' => 'Caja General',                                'codigo_sri' => '311', 'supercias_esf' => '1010101', 'supercias_eri' => '6555', 'supercias_ecp_codigo' => '99', 'supercias_ecp_subcodigo' => '555'],
            ['codigo' => '1.1.1.02',       'nivel' => '4', 'nombre' => 'BANCOS LOCALES',                                            'codigo_sri' => ''],
            ['codigo' => '1.1.1.02.001',   'nivel' => '5', 'nombre' => 'Banco Pichincha',                             'codigo_sri' => '311', 'supercias_esf' => '1010103'],
            ['codigo' => '1.1.1.02.002',   'nivel' => '5', 'nombre' => 'Banco Guayaquil',                             'codigo_sri' => '311', 'supercias_esf' => '1010103'],
            ['codigo' => '1.1.2',          'nivel' => '3', 'nombre' => 'CUENTAS Y DOCUMENTOS POR COBRAR',                           'codigo_sri' => ''],
            ['codigo' => '1.1.2.01',       'nivel' => '4', 'nombre' => 'CLIENTES LOCALES',                                          'codigo_sri' => ''],
            ['codigo' => '1.1.2.01.001',   'nivel' => '5', 'nombre' => 'Cuentas por cobrar clientes',                 'codigo_sri' => '315', 'supercias_esf' => '10102050202', 'map_asiento' => 'PORCOBRARFACTURAVENTA,PORCOBRARRECIBOVENTA'],
            ['codigo' => '1.1.2.02',       'nivel' => '4', 'nombre' => 'ANTICIPOS A PROVEEDORES',                                   'codigo_sri' => ''],
            ['codigo' => '1.1.2.02.001',   'nivel' => '5', 'nombre' => 'Anticipo proveedores',                        'codigo_sri' => '325', 'supercias_esf' => '1010403'],
            ['codigo' => '1.1.2.03',       'nivel' => '4', 'nombre' => 'ANTICIPOS A EMPLEADOS',                                     'codigo_sri' => ''],
            ['codigo' => '1.1.2.03.001',   'nivel' => '5', 'nombre' => 'Anticipos a Empleados',                       'codigo_sri' => '', 'map_asiento' => 'ANTICIPOSDESCUENTOSNOMINA'],
            ['codigo' => '1.1.3',          'nivel' => '3', 'nombre' => 'INVENTARIOS',                                               'codigo_sri' => ''],
            ['codigo' => '1.1.3.01',       'nivel' => '4', 'nombre' => 'INVENTARIO DE MERCADERÍAS',                                'codigo_sri' => ''],
            ['codigo' => '1.1.3.01.001',   'nivel' => '5', 'nombre' => 'Mercadería para la Venta',                   'codigo_sri' => '342', 'supercias_esf' => '10103', 'map_asiento' => 'INVENTARIOFACTURAVENTA,INVENTARIORECIBOVENTA,INVENTARIOFACTURACOMPRA'],
            ['codigo' => '1.1.3.02',       'nivel' => '4', 'nombre' => 'INVENTARIO DE SUMINISTROS Y REPUESTOS',                     'codigo_sri' => ''],
            ['codigo' => '1.1.3.02.001',   'nivel' => '5', 'nombre' => 'Suministros y Herramientas',                  'codigo_sri' => '343', 'supercias_esf' => '1010311'],
            ['codigo' => '1.1.4',          'nivel' => '3', 'nombre' => 'IMPUESTOS POR RECUPERAR',                                   'codigo_sri' => ''],
            ['codigo' => '1.1.4.01',       'nivel' => '4', 'nombre' => 'IVA EN COMPRAS',                                            'codigo_sri' => ''],
            ['codigo' => '1.1.4.01.001',   'nivel' => '5', 'nombre' => 'Iva en Compras 15%',                          'codigo_sri' => '336', 'supercias_esf' => '1010501', 'map_iva' => 'compra:15'],
            ['codigo' => '1.1.4.01.002',   'nivel' => '5', 'nombre' => 'Iva en Compras 5%',                           'codigo_sri' => '336', 'supercias_esf' => '1010501', 'map_iva' => 'compra:5'],
            ['codigo' => '1.1.4.02',       'nivel' => '4', 'nombre' => 'RETENCIONES EN LA FUENTE POR COBRAR',                       'codigo_sri' => ''],
            ['codigo' => '1.1.4.02.001',   'nivel' => '5', 'nombre' => 'Retenciones de Renta en Ventas',              'codigo_sri' => '337', 'supercias_esf' => '1010502'],
            ['codigo' => '1.1.4.02.002',   'nivel' => '5', 'nombre' => 'Retenciones de IVA en Ventas',                'codigo_sri' => '336', 'supercias_esf' => '1010501'],
            ['codigo' => '1.2',            'nivel' => '2', 'nombre' => 'ACTIVOS NO CORRIENTES',                                     'codigo_sri' => ''],
            ['codigo' => '1.2.1',          'nivel' => '3', 'nombre' => 'PROPIEDADES, PLANTA Y EQUIPO',                              'codigo_sri' => ''],
            ['codigo' => '1.2.1.01',       'nivel' => '4', 'nombre' => 'TERRENOS',                                                  'codigo_sri' => ''],
            ['codigo' => '1.2.1.01.001',   'nivel' => '5', 'nombre' => 'Terrenos',                                    'codigo_sri' => '362', 'supercias_esf' => '1020101'],
            ['codigo' => '1.2.1.02',       'nivel' => '4', 'nombre' => 'EDIFICIOS Y OTROS INMUEBLES',                               'codigo_sri' => ''],
            ['codigo' => '1.2.1.02.001',   'nivel' => '5', 'nombre' => 'Edificios',                                   'codigo_sri' => '364', 'supercias_esf' => '1020102'],
            ['codigo' => '1.2.1.03',       'nivel' => '4', 'nombre' => 'MUEBLES Y ENSERES',                                         'codigo_sri' => ''],
            ['codigo' => '1.2.1.03.001',   'nivel' => '5', 'nombre' => 'Muebles de Oficina',                          'codigo_sri' => '373', 'supercias_esf' => '1020105'],
            ['codigo' => '1.2.1.04',       'nivel' => '4', 'nombre' => 'EQUIPOS DE COMPUTACIÓN',                                   'codigo_sri' => ''],
            ['codigo' => '1.2.1.04.001',   'nivel' => '5', 'nombre' => 'Computadoras y Software',                     'codigo_sri' => '374', 'supercias_esf' => '1020108'],
            ['codigo' => '1.2.1.05',       'nivel' => '4', 'nombre' => 'VEHÍCULOS Y EQUIPO DE TRANSPORTE',                         'codigo_sri' => ''],
            ['codigo' => '1.2.1.05.001',   'nivel' => '5', 'nombre' => 'Vehículos',                                  'codigo_sri' => '375', 'supercias_esf' => '1020109'],
            ['codigo' => '1.2.2',          'nivel' => '3', 'nombre' => 'DEPRECIACIÓN ACUMULADA',                                   'codigo_sri' => ''],
            ['codigo' => '1.2.2.01',       'nivel' => '4', 'nombre' => 'DEPRECIACIÓN ACUMULADA DE ACTIVOS FIJOS',                  'codigo_sri' => ''],
            ['codigo' => '1.2.2.01.001',   'nivel' => '5', 'nombre' => 'Depreciación Acum. Equipos Computación',    'codigo_sri' => '386', 'supercias_esf' => '1020112'],
            ['codigo' => '1.2.2.01.002',   'nivel' => '5', 'nombre' => 'Depreciación Acum. Muebles',                 'codigo_sri' => '386', 'supercias_esf' => '1020112'],
            ['codigo' => '2',              'nivel' => '1', 'nombre' => 'PASIVOS',                                                   'codigo_sri' => ''],
            ['codigo' => '2.1',            'nivel' => '2', 'nombre' => 'PASIVOS CORRIENTES',                                        'codigo_sri' => ''],
            ['codigo' => '2.1.1',          'nivel' => '3', 'nombre' => 'CUENTAS Y DOCUMENTOS POR PAGAR',                            'codigo_sri' => ''],
            ['codigo' => '2.1.1.01',       'nivel' => '4', 'nombre' => 'PROVEEDORES LOCALES',                                       'codigo_sri' => ''],
            ['codigo' => '2.1.1.01.001',   'nivel' => '5', 'nombre' => 'Cuentas por pagar proveedores',               'codigo_sri' => '513', 'supercias_esf' => '201030102', 'map_asiento' => 'PORPAGARFACTURACOMPRA'],
            ['codigo' => '2.1.1.02',       'nivel' => '4', 'nombre' => 'ANTICIPOS DE CLIENTES',                                     'codigo_sri' => ''],
            ['codigo' => '2.1.1.02.001',   'nivel' => '5', 'nombre' => 'Anticipo clientes',                           'codigo_sri' => '545', 'supercias_esf' => '2011001'],
            ['codigo' => '2.1.2',          'nivel' => '3', 'nombre' => 'OBLIGACIONES CON INSTITUCIONES',                            'codigo_sri' => ''],
            ['codigo' => '2.1.2.01',       'nivel' => '4', 'nombre' => 'OBLIGACIONES IESS',                                         'codigo_sri' => ''],
            ['codigo' => '2.1.2.01.001',   'nivel' => '5', 'nombre' => 'IESS por Pagar',                              'codigo_sri' => '534', 'supercias_esf' => '2010703', 'map_asiento' => 'IESSPORPAGARNOMINA'],
            ['codigo' => '2.1.2.02',       'nivel' => '4', 'nombre' => 'OBLIGACIONES BANCARIAS',                                    'codigo_sri' => ''],
            ['codigo' => '2.1.2.02.001',   'nivel' => '5', 'nombre' => 'Préstamos Bancarios Corto Plazo',            'codigo_sri' => '525', 'supercias_esf' => '2010401'],
            ['codigo' => '2.1.3',          'nivel' => '3', 'nombre' => 'OBLIGACIONES TRIBUTARIAS',                                  'codigo_sri' => ''],
            ['codigo' => '2.1.3.01',       'nivel' => '4', 'nombre' => 'IVA EN VENTAS',                                             'codigo_sri' => ''],
            ['codigo' => '2.1.3.01.001',   'nivel' => '5', 'nombre' => 'Iva en Ventas 15%',                           'codigo_sri' => '549', 'supercias_esf' => '2010701', 'map_iva' => 'venta:15,recibo:15'],
            ['codigo' => '2.1.3.01.002',   'nivel' => '5', 'nombre' => 'SRI por pagar',                               'codigo_sri' => '549', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.3.01.003',   'nivel' => '5', 'nombre' => 'Iva en Ventas 5%',                            'codigo_sri' => '549', 'supercias_esf' => '2010701', 'map_iva' => 'venta:5,recibo:5'],
            ['codigo' => '2.1.3.02',       'nivel' => '4', 'nombre' => 'RETENCIONES EN LA FUENTE POR PAGAR',                        'codigo_sri' => ''],
            ['codigo' => '2.1.3.02.001',   'nivel' => '5', 'nombre' => 'Retención IR por Pagar',                     'codigo_sri' => '532', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.3.02.002',   'nivel' => '5', 'nombre' => 'Retenciones de IVA por Pagar',                'codigo_sri' => '549', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.3.02.003',   'nivel' => '5', 'nombre' => 'Retención IR Empleados en Relación de Dependencia', 'codigo_sri' => '532', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.3.03',       'nivel' => '4', 'nombre' => 'IMPUESTO A LA RENTA POR PAGAR',                             'codigo_sri' => ''],
            ['codigo' => '2.1.3.03.001',   'nivel' => '5', 'nombre' => 'Impuesto a la Renta Ejercicio',               'codigo_sri' => '532', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.3.04',       'nivel' => '4', 'nombre' => 'ICE POR PAGAR',                                             'codigo_sri' => ''],
            ['codigo' => '2.1.3.04.001',   'nivel' => '5', 'nombre' => 'ICE por Pagar',                               'codigo_sri' => '', 'map_asiento' => 'ICEFACTURAVENTA,ICERECIBOVENTA'],
            ['codigo' => '2.1.4',          'nivel' => '3', 'nombre' => 'BENEFICIOS A EMPLEADOS POR PAGAR',                          'codigo_sri' => ''],
            ['codigo' => '2.1.4.01',       'nivel' => '4', 'nombre' => 'PARTICIPACIÓN TRABAJADORES',                               'codigo_sri' => ''],
            ['codigo' => '2.1.4.01.001',   'nivel' => '5', 'nombre' => 'Participación Trabajadores 15%',             'codigo_sri' => '533', 'supercias_esf' => '2010705'],
            ['codigo' => '2.1.4.02',       'nivel' => '4', 'nombre' => 'JUBILACIÓN PATRONAL Y DESAHUCIO',                          'codigo_sri' => ''],
            ['codigo' => '2.1.4.02.001',   'nivel' => '5', 'nombre' => 'Jubilación Patronal por Pagar',              'codigo_sri' => '535', 'supercias_esf' => '2010704'],
            ['codigo' => '2.1.4.02.002',   'nivel' => '5', 'nombre' => 'Desahucio por Pagar',                         'codigo_sri' => '574', 'supercias_esf' => '2010704', 'map_asiento' => 'DESAHUCIOPORPAGARNOMINA'],
            ['codigo' => '2.1.4.03',       'nivel' => '4', 'nombre' => 'SUELDOS Y BENEFICIOS SOCIALES POR PAGAR',                   'codigo_sri' => ''],
            ['codigo' => '2.1.4.03.001',   'nivel' => '5', 'nombre' => 'Sueldos por Pagar',                           'codigo_sri' => '536', 'supercias_esf' => '2010704', 'map_asiento' => 'SUELDOSPORPAGARNOMINA'],
            ['codigo' => '2.1.4.03.002',   'nivel' => '5', 'nombre' => 'Décimo Tercer Sueldo por Pagar',             'codigo_sri' => '536', 'supercias_esf' => '2010704', 'map_asiento' => 'DECIMOTERCEROPORPAGARNOMINA'],
            ['codigo' => '2.1.4.03.003',   'nivel' => '5', 'nombre' => 'Décimo Cuarto Sueldo por Pagar',             'codigo_sri' => '536', 'supercias_esf' => '2010704', 'map_asiento' => 'DECIMOCUARTOPORPAGARNOMINA'],
            ['codigo' => '2.1.4.03.004',   'nivel' => '5', 'nombre' => 'Vacaciones por Pagar',                        'codigo_sri' => '536', 'supercias_esf' => '2010704', 'map_asiento' => 'VACACIONESPORPAGARNOMINA'],
            ['codigo' => '2.1.4.03.005',   'nivel' => '5', 'nombre' => 'Fondos de Reserva por Pagar',                 'codigo_sri' => '536', 'supercias_esf' => '2010704', 'map_asiento' => 'FONDOSRESERVAPORPAGARNOMINA'],
            ['codigo' => '3',              'nivel' => '1', 'nombre' => 'PATRIMONIO',                                                'codigo_sri' => ''],
            ['codigo' => '3.1',            'nivel' => '2', 'nombre' => 'CAPITAL SOCIAL',                                            'codigo_sri' => ''],
            ['codigo' => '3.1.1',          'nivel' => '3', 'nombre' => 'CAPITAL SUSCRITO Y/O ASIGNADO',                             'codigo_sri' => ''],
            ['codigo' => '3.1.1.01',       'nivel' => '4', 'nombre' => 'CAPITAL SUSCRITO',                                          'codigo_sri' => ''],
            ['codigo' => '3.1.1.01.001',   'nivel' => '5', 'nombre' => 'Capital suscrito y/o asignado',               'codigo_sri' => '601', 'supercias_esf' => '30101', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '301'],
            ['codigo' => '3.1.2',          'nivel' => '3', 'nombre' => 'RESERVAS',                                                  'codigo_sri' => ''],
            ['codigo' => '3.1.2.01',       'nivel' => '4', 'nombre' => 'RESERVA LEGAL Y FACULTATIVA',                               'codigo_sri' => ''],
            ['codigo' => '3.1.2.01.001',   'nivel' => '5', 'nombre' => 'Reserva Legal',                               'codigo_sri' => '604', 'supercias_esf' => '30401', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '30401'],
            ['codigo' => '3.2',            'nivel' => '2', 'nombre' => 'RESULTADOS ACUMULADOS',                                     'codigo_sri' => ''],
            ['codigo' => '3.2.1',          'nivel' => '3', 'nombre' => 'GANANCIAS O PÉRDIDAS ACUMULADAS',                          'codigo_sri' => ''],
            ['codigo' => '3.2.1.01',       'nivel' => '4', 'nombre' => 'UTILIDADES DE EJERCICIOS ANTERIORES',                       'codigo_sri' => ''],
            ['codigo' => '3.2.1.01.001',   'nivel' => '5', 'nombre' => 'Utilidad Acumulada',                          'codigo_sri' => '611', 'supercias_esf' => '30601', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '30601'],
            ['codigo' => '3.3',            'nivel' => '2', 'nombre' => 'RESULTADOS DEL EJERCICIO',                                  'codigo_sri' => ''],
            ['codigo' => '3.3.1',          'nivel' => '3', 'nombre' => 'UTILIDAD O PÉRDIDA DEL EJERCICIO',                         'codigo_sri' => ''],
            ['codigo' => '3.3.1.01',       'nivel' => '4', 'nombre' => 'RESULTADO DEL EJERCICIO',                                   'codigo_sri' => ''],
            ['codigo' => '3.3.1.01.001',   'nivel' => '5', 'nombre' => 'Utilidad del Ejercicio',                      'codigo_sri' => '615', 'supercias_esf' => '30701', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '30701', 'map_asiento' => 'UTILIDADEJERCICIOCIERRE'],
            ['codigo' => '3.3.1.01.002',   'nivel' => '5', 'nombre' => 'Pérdida del Ejercicio',                      'codigo_sri' => '616', 'supercias_esf' => '30602', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '30702', 'map_asiento' => 'PERDIDAEJERCICIOCIERRE'],
            ['codigo' => '4',              'nivel' => '1', 'nombre' => 'INGRESOS',                                                  'codigo_sri' => ''],
            ['codigo' => '4.1',            'nivel' => '2', 'nombre' => 'INGRESOS OPERACIONALES',                                    'codigo_sri' => ''],
            ['codigo' => '4.1.1',          'nivel' => '3', 'nombre' => 'VENTAS LOCALES',                                            'codigo_sri' => ''],
            ['codigo' => '4.1.1.01',       'nivel' => '4', 'nombre' => 'VENTAS DEL NEGOCIO',                                        'codigo_sri' => ''],
            ['codigo' => '4.1.1.01.001',   'nivel' => '5', 'nombre' => 'VENTAS DEL NEGOCIO',                          'codigo_sri' => '6001', 'supercias_eri' => '40101', 'map_asiento' => 'SUBTOTALFACTURAVENTA,SUBTOTALRECIBOVENTA'],
            ['codigo' => '4.1.1.02',       'nivel' => '4', 'nombre' => 'DESCUENTOS EN VENTAS',                                      'codigo_sri' => ''],
            ['codigo' => '4.1.1.02.001',   'nivel' => '5', 'nombre' => 'Descuento en Ventas',                         'codigo_sri' => '', 'map_asiento' => 'DESCUENTOFACTURAVENTA,DESCUENTORECIBOVENTA'],
            ['codigo' => '4.1.1.03',       'nivel' => '4', 'nombre' => 'PROPINAS',                                                  'codigo_sri' => ''],
            ['codigo' => '4.1.1.03.001',   'nivel' => '5', 'nombre' => 'Propina en Ventas',                           'codigo_sri' => '', 'map_asiento' => 'PROPINAFACTURAVENTA,PROPINARECIBOVENTA'],
            ['codigo' => '4.2',            'nivel' => '2', 'nombre' => 'INGRESOS NO OPERACIONALES',                                 'codigo_sri' => ''],
            ['codigo' => '4.2.1',          'nivel' => '3', 'nombre' => 'INGRESOS FINANCIEROS',                                      'codigo_sri' => ''],
            ['codigo' => '4.2.1.01',       'nivel' => '4', 'nombre' => 'INTERESES GANADOS',                                         'codigo_sri' => ''],
            ['codigo' => '4.2.1.01.001',   'nivel' => '5', 'nombre' => 'Intereses de Bancos',                         'codigo_sri' => '6111', 'supercias_eri' => '4010603'],
            ['codigo' => '5',              'nivel' => '1', 'nombre' => 'COSTOS Y GASTOS',                                           'codigo_sri' => ''],
            ['codigo' => '5.1',            'nivel' => '2', 'nombre' => 'COSTO DE VENTAS Y PRODUCCIÓN',                             'codigo_sri' => ''],
            ['codigo' => '5.1.1',          'nivel' => '3', 'nombre' => 'COSTO DE VENTAS',                                           'codigo_sri' => ''],
            ['codigo' => '5.1.1.01',       'nivel' => '4', 'nombre' => 'COSTO DE VENTAS LOCALES',                                   'codigo_sri' => ''],
            ['codigo' => '5.1.1.01.001',   'nivel' => '5', 'nombre' => 'Costo de Mercadería',                        'codigo_sri' => '7004', 'supercias_eri' => '5010105', 'map_asiento' => 'COSTOFACTURAVENTA,COSTORECIBOVENTA'],
            ['codigo' => '5.1.1.02',       'nivel' => '4', 'nombre' => 'DESCUENTOS EN COMPRAS',                                     'codigo_sri' => ''],
            ['codigo' => '5.1.1.02.001',   'nivel' => '5', 'nombre' => 'Descuento en Compras',                        'codigo_sri' => '', 'map_asiento' => 'DESCUENTOFACTURACOMPRA'],
            ['codigo' => '5.2',            'nivel' => '2', 'nombre' => 'GASTOS OPERACIONALES',                                      'codigo_sri' => ''],
            ['codigo' => '5.2.1',          'nivel' => '3', 'nombre' => 'GASTOS DEL PERSONAL',                                       'codigo_sri' => ''],
            ['codigo' => '5.2.1.01',       'nivel' => '4', 'nombre' => 'SUELDOS Y SALARIOS',                                        'codigo_sri' => ''],
            ['codigo' => '5.2.1.01.001',   'nivel' => '5', 'nombre' => 'Sueldos y salarios',                          'codigo_sri' => '7041', 'supercias_eri' => '5020101', 'map_asiento' => 'GASTOSUELDOSNOMINA'],
            ['codigo' => '5.2.1.01.002',   'nivel' => '5', 'nombre' => 'Décimo Tercer Sueldo',                       'codigo_sri' => '7044', 'supercias_eri' => '5020103', 'map_asiento' => 'GASTODECIMOTERCERONOMINA'],
            ['codigo' => '5.2.1.01.003',   'nivel' => '5', 'nombre' => 'Décimo Cuarto Sueldo',                       'codigo_sri' => '7044', 'supercias_eri' => '5020103', 'map_asiento' => 'GASTODECIMOCUARTONOMINA'],
            ['codigo' => '5.2.1.01.004',   'nivel' => '5', 'nombre' => 'Vacaciones',                                  'codigo_sri' => '7044', 'supercias_eri' => '5020103', 'map_asiento' => 'GASTOVACACIONESNOMINA'],
            ['codigo' => '5.2.1.01.005',   'nivel' => '5', 'nombre' => 'Fondos de Reserva',                           'codigo_sri' => '7044', 'supercias_eri' => '5020102', 'map_asiento' => 'GASTOFONDOSRESERVANOMINA'],
            ['codigo' => '5.2.1.01.006',   'nivel' => '5', 'nombre' => 'Aporte Patronal al IESS',                     'codigo_sri' => '7047', 'supercias_eri' => '5020102', 'map_asiento' => 'GASTOAPORTEPATRONALNOMINA'],
            ['codigo' => '5.2.1.01.007',   'nivel' => '5', 'nombre' => 'Desahucio',                                   'codigo_sri' => '7043', 'supercias_eri' => '5020103', 'map_asiento' => 'GASTODESAHUCIONOMINA'],
            ['codigo' => '5.2.1.02',       'nivel' => '4', 'nombre' => 'HONORARIOS PROFESIONALES',                                  'codigo_sri' => ''],
            ['codigo' => '5.2.1.02.001',   'nivel' => '5', 'nombre' => 'Honorarios profesionales y dietas',           'codigo_sri' => '7049', 'supercias_eri' => '5020105'],
            ['codigo' => '5.2.1.03',       'nivel' => '4', 'nombre' => 'SERVICIOS BÁSICOS',                                        'codigo_sri' => ''],
            ['codigo' => '5.2.1.03.001',   'nivel' => '5', 'nombre' => 'Agua, Luz y Teléfono',                       'codigo_sri' => '7241', 'supercias_eri' => '5020218'],
            ['codigo' => '5.2.1.04',       'nivel' => '4', 'nombre' => 'DEPRECIACIÓN Y AMORTIZACIÓN',                             'codigo_sri' => ''],
            ['codigo' => '5.2.1.04.001',   'nivel' => '5', 'nombre' => 'Depreciación Activos Fijos',                 'codigo_sri' => '7067', 'supercias_eri' => '502022101'],
            ['codigo' => '5.2.1.04.002',   'nivel' => '5', 'nombre' => 'Amortización Intangibles',                   'codigo_sri' => '7094', 'supercias_eri' => '502022201'],
            ['codigo' => '5.2.1.05',       'nivel' => '4', 'nombre' => 'OTROS GASTOS ADMINISTRATIVOS',                              'codigo_sri' => ''],
            ['codigo' => '5.2.1.05.001',   'nivel' => '5', 'nombre' => 'Suministros y Materiales de Oficina',         'codigo_sri' => '7190', 'supercias_eri' => '5020127'],
            ['codigo' => '5.2.1.05.002',   'nivel' => '5', 'nombre' => 'Mantenimiento y Reparaciones',                'codigo_sri' => '7196', 'supercias_eri' => '5020208'],
            ['codigo' => '5.2.1.05.003',   'nivel' => '5', 'nombre' => 'Seguros y Reaseguros',                        'codigo_sri' => '7202', 'supercias_eri' => '5020214'],
            ['codigo' => '5.2.1.05.004',   'nivel' => '5', 'nombre' => 'Compras y Gastos Generales',                  'codigo_sri' => '', 'map_asiento' => 'SUBTOTALFACTURACOMPRA'],
            ['codigo' => '5.2.1.05.005',   'nivel' => '5', 'nombre' => 'ICE en Compras',                              'codigo_sri' => '', 'map_asiento' => 'ICEFACTURACOMPRA'],
            ['codigo' => '5.2.1.05.006',   'nivel' => '5', 'nombre' => 'Propina en Compras',                          'codigo_sri' => '', 'map_asiento' => 'PROPINAFACTURACOMPRA'],
            ['codigo' => '5.2.2',          'nivel' => '3', 'nombre' => 'GASTOS DE VENTAS',                                          'codigo_sri' => ''],
            ['codigo' => '5.2.2.01',       'nivel' => '4', 'nombre' => 'GASTOS DE COMERCIALIZACIÓN',                               'codigo_sri' => ''],
            ['codigo' => '5.2.2.01.001',   'nivel' => '5', 'nombre' => 'Promoción y Publicidad',                     'codigo_sri' => '7173', 'supercias_eri' => '5020211'],
            ['codigo' => '5.2.2.01.002',   'nivel' => '5', 'nombre' => 'Transporte',                                  'codigo_sri' => '7176', 'supercias_eri' => '5020215'],
            ['codigo' => '5.2.2.01.003',   'nivel' => '5', 'nombre' => 'Combustibles y Lubricantes',                  'codigo_sri' => '7178', 'supercias_eri' => '5020212'],
            ['codigo' => '5.2.2.01.004',   'nivel' => '5', 'nombre' => 'Ajuste por redondeo',                         'codigo_sri' => '7248', 'supercias_eri' => '5020229', 'map_asiento' => 'AJUSTEREDONDEOVENTA,AJUSTEREDONDEORECIBOVENTA,AJUSTEREDONDEOCOMPRA'],
        ];
    }
    public function cargarModelo(int $idEmpresa, int $idUsuario, bool $configurarAsientos): array
    {
        $db = \App\core\Database::getConnection();
        
        // Obtener todas las cuentas (tanto activas como eliminadas lógicamente) para la empresa
        $stmtAll = $db->prepare("SELECT id, codigo, eliminado, nombre FROM plan_cuentas WHERE id_empresa = ?");
        $stmtAll->execute([$idEmpresa]);
        $todasCuentas = $stmtAll->fetchAll(\PDO::FETCH_ASSOC);

        $mapaExistentes = []; // Guardará ID de cuentas activas
        $mapaEliminadas = [];  // Guardará datos de cuentas eliminadas
        foreach ($todasCuentas as $row) {
            if ((bool)$row['eliminado']) {
                $mapaEliminadas[$row['codigo']] = $row;
            } else {
                $mapaExistentes[$row['codigo']] = $row['id'];
            }
        }

        $cuentas = self::getCuentasModeloArray();

        $mapeos = [];        // codigo de asientos_tipo => id de plan_cuentas
        $mapeosIva = [];     // 'direccion:porcentaje' => id de plan_cuentas
        $idsPorCodigo = [];  // codigo de cuenta => id de plan_cuentas
        $nuevasCreadas = 0;
        $configuradas = 0;
        $respetadas = 0;
        $this->repository->beginTransaction();
        try {
            foreach ($cuentas as $c) {
                $nivel = (int)$c['nivel'];
                $sriVal = $nivel === 5 ? ($c['codigo_sri'] ?? '') : '';
                $esfVal = $nivel === 5 ? ($c['supercias_esf'] ?? null) : null;
                $eriVal = $nivel === 5 ? ($c['supercias_eri'] ?? null) : null;
                $ecpCod = $nivel === 5 ? ($c['supercias_ecp_codigo'] ?? null) : null;
                $ecpSub = $nivel === 5 ? ($c['supercias_ecp_subcodigo'] ?? null) : null;

                $id = null;

                if (isset($mapaExistentes[$c['codigo']])) {
                    $id = $mapaExistentes[$c['codigo']];
                    // Si ya existe activa: solo actualizamos referencias técnicas, NUNCA sobreescribimos el nombre
                    $stmtUpd = $db->prepare("UPDATE plan_cuentas SET codigo_sri = ?, supercias_esf = ?, supercias_eri = ?, supercias_ecp_codigo = ?, supercias_ecp_subcodigo = ? WHERE id = ?");
                    $stmtUpd->execute([$sriVal, $esfVal, $eriVal, $ecpCod, $ecpSub, $id]);
                } elseif (isset($mapaEliminadas[$c['codigo']])) {
                    // Si existe pero está eliminada lógicamente: la restauramos y actualizamos
                    $id = $mapaEliminadas[$c['codigo']]['id'];
                    $stmtRestore = $db->prepare("UPDATE plan_cuentas SET eliminado = false, nombre = ?, codigo_sri = ?, supercias_esf = ?, supercias_eri = ?, supercias_ecp_codigo = ?, supercias_ecp_subcodigo = ? WHERE id = ?");
                    $stmtRestore->execute([$c['nombre'], $sriVal, $esfVal, $eriVal, $ecpCod, $ecpSub, $id]);
                } elseif ($nivel === 5) {
                    // Buscar versión antigua de dos dígitos (ej. 1.1.1.01.01 para 1.1.1.01.001)
                    $partes = explode('.', $c['codigo']);
                    $ultimo = (int)array_pop($partes);
                    if ($ultimo < 100) {
                        $codigoViejo = implode('.', $partes) . '.' . sprintf('%02d', $ultimo);
                        if (isset($mapaExistentes[$codigoViejo])) {
                            $id = $mapaExistentes[$codigoViejo];
                            // Actualizar el código antiguo al nuevo en la BD
                            $db->prepare("UPDATE plan_cuentas SET codigo = ? WHERE id = ?")->execute([$c['codigo'], $id]);
                            // Solo actualizar referencias técnicas en la cuenta migrada, preservando su nombre
                            $stmtUpd = $db->prepare("UPDATE plan_cuentas SET codigo_sri = ?, supercias_esf = ?, supercias_eri = ?, supercias_ecp_codigo = ?, supercias_ecp_subcodigo = ? WHERE id = ?");
                            $stmtUpd->execute([$sriVal, $esfVal, $eriVal, $ecpCod, $ecpSub, $id]);
                            
                            $mapaExistentes[$c['codigo']] = $id;
                            unset($mapaExistentes[$codigoViejo]);
                        }
                    }
                }

                if ($id === null) {
                    // No existe en absoluto: la creamos nueva
                    $data = [
                        'id_empresa' => $idEmpresa,
                        'id_usuario' => $idUsuario,
                        'codigo' => $c['codigo'],
                        'nivel' => $nivel,
                        'nombre' => $c['nombre'],
                        'codigo_sri' => $sriVal,
                        'status' => 1,
                        'created_by' => $idUsuario,
                        'id_centro_costos' => null,
                        'id_proyecto' => null,
                        'supercias_esf' => $esfVal,
                        'supercias_eri' => $eriVal,
                        'supercias_ecp_codigo' => $ecpCod,
                        'supercias_ecp_subcodigo' => $ecpSub,
                    ];
                    $id = $this->repository->create($data);
                    $nuevasCreadas++;
                }

                $idsPorCodigo[$c['codigo']] = $id;

                // Una misma cuenta puede alimentar varios tipos de asiento (p. ej. la cuenta por
                // cobrar sirve a facturas y a recibos de venta): se listan separados por coma.
                if (!empty($c['map_asiento'])) {
                    foreach (explode(',', (string) $c['map_asiento']) as $codigoAsiento) {
                        $codigoAsiento = trim($codigoAsiento);
                        if ($codigoAsiento !== '') {
                            $mapeos[$codigoAsiento] = $id;
                        }
                    }
                }
                // El IVA no se configura por tipo de asiento sino por tarifa: 'direccion:porcentaje'
                // (venta|compra|recibo : 15|5|0…), también admite varios separados por coma.
                if (!empty($c['map_iva'])) {
                    foreach (explode(',', (string) $c['map_iva']) as $regla) {
                        $regla = trim($regla);
                        if ($regla !== '') {
                            $mapeosIva[$regla] = $id;
                        }
                    }
                }
            }

            if ($nuevasCreadas > 0) {
                $this->logService->registrar($idUsuario, $idEmpresa, 'CARGA MASIVA', 'plan_cuentas', 0, null, ['total_creadas' => $nuevasCreadas]);
            }

            if ($configurarAsientos) {
                $resumen = $this->sembrarConfiguracionContable($idEmpresa, $idUsuario, $mapeos, $mapeosIva);
                if ($resumen['creadas'] > 0 || $resumen['respetadas'] > 0) {
                    $this->logService->registrar($idUsuario, $idEmpresa, 'CONFIGURACION AUTO', 'asientos_programados', 0, null, $resumen);
                }
                $configuradas = $resumen['creadas'];
                $respetadas   = $resumen['respetadas'];

                $resumenFormas = $this->sembrarCuentasFormasYOpciones($idEmpresa, $idUsuario, $idsPorCodigo);
                if ($resumenFormas['asignadas'] > 0 || $resumenFormas['respetadas'] > 0) {
                    $this->logService->registrar($idUsuario, $idEmpresa, 'CONFIGURACION AUTO', 'empresa_formas_pago', 0, null, $resumenFormas);
                }
                $configuradas += $resumenFormas['asignadas'];
                $respetadas   += $resumenFormas['respetadas'];
            }

            $this->repository->commit();

            $mensaje = "Se validó el plan modelo. Cuentas nuevas insertadas: {$nuevasCreadas}.";
            if ($configurarAsientos) {
                $mensaje .= " Configuración contable: {$configuradas} tipo(s) de asiento configurado(s)";
                if ($respetadas > 0) {
                    $mensaje .= ", {$respetadas} ya estaban configurados y se respetaron";
                }
                $mensaje .= '.';
            }
            return ['status' => true, 'message' => $mensaje];
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Siembra la configuración contable (asientos_programados) a partir de los mapeos del plan
     * modelo. Se ejecuta dentro de la transacción abierta por cargarModelo().
     *
     * Dos formatos, porque el motor de asientos lee dos cosas distintas:
     *
     *  1. Regla por tipo de asiento (la mayoría). El lector exige LAS DOS condiciones
     *     (ver AsientoProgramadoRepository::getReglasPorTipoAsiento):
     *       ap.id_referencia = at.id
     *       AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)
     *     Escribir 'general' —como se hacía antes— dejaba filas que no lee nadie.
     *
     *  2. Regla de IVA por tarifa. No hay un asiento_tipo para el IVA de compras ni para el de
     *     ventas: el motor resuelve la cuenta por tarifa, con id_asiento_tipo = 0,
     *     id_referencia = código de la tarifa (tarifa_iva.codigo) y tipo_referencia =
     *     iva_ventas_factura | iva_compras_factura | iva_recibos_venta.
     *
     * Nunca sobrescribe: si el destino ya tiene regla, la respeta y la cuenta como tal.
     *
     * @param array<string,int> $mapeos    codigo de asientos_tipo => id de plan_cuentas
     * @param array<string,int> $mapeosIva 'direccion:porcentaje' => id de plan_cuentas
     * @return array{creadas:int,respetadas:int,sin_tipo:list<string>,sin_tarifa:list<string>}
     */
    private function sembrarConfiguracionContable(int $idEmpresa, int $idUsuario, array $mapeos, array $mapeosIva): array
    {
        $db = \App\core\Database::getConnection();

        $creadas = 0;
        $respetadas = 0;
        $sinTipo = [];
        $sinTarifa = [];

        $stTipo = $db->prepare("SELECT id, tipo_asiento FROM asientos_tipo WHERE codigo = ? AND eliminado = false LIMIT 1");
        $stExisteGeneral = $db->prepare(
            "SELECT id FROM asientos_programados
             WHERE id_empresa = ? AND id_asiento_tipo = ? AND eliminado = false
               AND (tipo_referencia = 'asientos tipo' OR tipo_referencia = ?)
             LIMIT 1"
        );
        $stInsert = $db->prepare(
            "INSERT INTO asientos_programados
                (id_empresa, id_usuario, id_asiento_tipo, id_cuenta, id_referencia, tipo_referencia, created_by, eliminado)
             VALUES (?, ?, ?, ?, ?, ?, ?, false)"
        );

        foreach ($mapeos as $codigoAsiento => $idCuenta) {
            $stTipo->execute([$codigoAsiento]);
            $tipo = $stTipo->fetch(\PDO::FETCH_ASSOC);
            if (!$tipo) {
                $sinTipo[] = $codigoAsiento;
                continue;
            }
            $idAsientoTipo = (int) $tipo['id'];
            $tipoAsiento   = (string) $tipo['tipo_asiento'];

            $stExisteGeneral->execute([$idEmpresa, $idAsientoTipo, $tipoAsiento]);
            if ($stExisteGeneral->fetchColumn()) {
                $respetadas++;
                continue;
            }

            $stInsert->execute([$idEmpresa, $idUsuario, $idAsientoTipo, $idCuenta, $idAsientoTipo, $tipoAsiento, $idUsuario]);
            $creadas++;
        }

        $tiposIva = [
            'venta'  => 'iva_ventas_factura',
            'compra' => 'iva_compras_factura',
            'recibo' => 'iva_recibos_venta',
        ];
        $stTarifa = $db->prepare("SELECT codigo FROM tarifa_iva WHERE porcentaje_iva = ? AND status = 1 ORDER BY codigo LIMIT 1");
        $stExisteIva = $db->prepare(
            "SELECT id FROM asientos_programados
             WHERE id_empresa = ? AND tipo_referencia = ? AND id_referencia = ? AND eliminado = false
             LIMIT 1"
        );

        foreach ($mapeosIva as $regla => $idCuenta) {
            [$direccion, $porcentaje] = array_pad(explode(':', $regla, 2), 2, '');
            $direccion  = strtolower(trim($direccion));
            $porcentaje = trim($porcentaje);

            if (!isset($tiposIva[$direccion]) || $porcentaje === '' || !is_numeric($porcentaje)) {
                $sinTarifa[] = $regla;
                continue;
            }
            $tipoReferencia = $tiposIva[$direccion];

            $stTarifa->execute([(int) $porcentaje]);
            $codigoTarifa = $stTarifa->fetchColumn();
            if ($codigoTarifa === false) {
                $sinTarifa[] = $regla;
                continue;
            }

            $stExisteIva->execute([$idEmpresa, $tipoReferencia, (int) $codigoTarifa]);
            if ($stExisteIva->fetchColumn()) {
                $respetadas++;
                continue;
            }

            $stInsert->execute([$idEmpresa, $idUsuario, 0, $idCuenta, (int) $codigoTarifa, $tipoReferencia, $idUsuario]);
            $creadas++;
        }

        return [
            'creadas'    => $creadas,
            'respetadas' => $respetadas,
            'sin_tipo'   => $sinTipo,
            'sin_tarifa' => $sinTarifa,
        ];
    }

    /**
     * Formas de cobro/pago que siembra EmpresaInicializadorService y la cuenta del plan modelo
     * que les corresponde. Se identifican por tipo + aplica_en + nombre, tal como las crea el
     * inicializador, para no tocar las que el usuario haya creado por su cuenta.
     *
     * Tarjeta, Payphone y Nuvei quedan deliberadamente fuera: el dinero de esas vías no entra
     * al instante ni por su valor nominal (llega con la liquidación del procesador, ya neta de
     * comisión), así que la cuenta se decide por empresa y se asigna a mano.
     */
    private const CUENTAS_FORMAS_PAGO = [
        ['tipo' => 'EFECTIVO', 'aplica_en' => 'AMBAS',   'nombre' => 'Efectivo',              'cuenta' => '1.1.1.01.002'],
        ['tipo' => 'ANTICIPO', 'aplica_en' => 'INGRESO', 'nombre' => 'Anticipos clientes',    'cuenta' => '2.1.1.02.001'],
        ['tipo' => 'ANTICIPO', 'aplica_en' => 'EGRESO',  'nombre' => 'Anticipos Proveedores', 'cuenta' => '1.1.2.02.001'],
    ];

    /**
     * Opciones de ingreso/egreso que llevan cuenta propia, y la cuenta del plan modelo que les
     * toca. Las de comportamiento ligado a un módulo (COMPRA, LIQUIDACION, FACTURA_VENTA,
     * RECIBO_VENTA, ROL) NO están aquí a propósito: heredan la cuenta de su módulo y el sistema
     * rechaza asignarles una aparte (ver AsientoProgramadoRepository::COMPORTAMIENTO_CUENTA_OFICIAL).
     *
     * 'crear_si_falta' es para los conceptos GENERAL: el inicializador los siembra en las empresas
     * nuevas, pero una empresa creada antes de ese cambio no los tiene y aquí se completan.
     */
    private const CUENTAS_OPCIONES = [
        ['comportamiento' => 'ANTICIPO_CLIENTE',   'nombre' => 'Anticipos Clientes',    'cuenta' => '2.1.1.02.001'],
        ['comportamiento' => 'ANTICIPO_PROVEEDOR', 'nombre' => 'Anticipos Proveedores', 'cuenta' => '1.1.2.02.001'],
        ['comportamiento' => 'GENERAL', 'nombre' => 'SRI',  'cuenta' => '2.1.3.01.002', 'crear_si_falta' => true, 'ingresos' => false, 'egresos' => true],
        ['comportamiento' => 'GENERAL', 'nombre' => 'IESS', 'cuenta' => '2.1.2.01.001', 'crear_si_falta' => true, 'ingresos' => false, 'egresos' => true],
    ];

    /**
     * Asigna la cuenta contable a las formas de cobro/pago y a las opciones de ingreso/egreso
     * que sembró EmpresaInicializadorService. Corre dentro de la transacción de cargarModelo().
     *
     * Por qué aquí y no en el inicializador: cuando se crea la empresa todavía no existe ningún
     * plan de cuentas, así que no hay cuenta que asignar. Este es el primer momento en que las
     * cuentas existen.
     *
     * La cuenta se escribe en id_cuenta_contable de cada tabla, no en asientos_programados: el
     * lector hace COALESCE(ap.id_cuenta, x.id_cuenta_contable), así que se ve igual en
     * Configuración Contable, y una sola fila cubre cobro y pago en las formas 'AMBAS'.
     *
     * Nunca sobrescribe: si ya hay cuenta asignada, la respeta.
     *
     * @param array<string,int> $idsPorCodigo código de cuenta => id de plan_cuentas
     * @return array{asignadas:int,respetadas:int,sin_cuenta:list<string>,sin_destino:list<string>}
     */
    private function sembrarCuentasFormasYOpciones(int $idEmpresa, int $idUsuario, array $idsPorCodigo): array
    {
        $db = \App\core\Database::getConnection();

        $asignadas = 0;
        $respetadas = 0;
        $sinCuenta = [];   // la cuenta del mapeo no existe en el plan
        $sinDestino = [];  // la forma/opción no existe en la empresa

        // ── Formas de cobro/pago ──
        $buscarForma = $db->prepare(
            "SELECT id, id_cuenta_contable FROM empresa_formas_pago
             WHERE id_empresa = ? AND tipo = ? AND aplica_en = ?
               AND UPPER(TRIM(nombre)) = ? AND eliminado = false
             ORDER BY id LIMIT 1"
        );
        $updForma = $db->prepare(
            "UPDATE empresa_formas_pago
             SET id_cuenta_contable = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );

        foreach (self::CUENTAS_FORMAS_PAGO as $f) {
            $idCuenta = $idsPorCodigo[$f['cuenta']] ?? null;
            if ($idCuenta === null) {
                $sinCuenta[] = "forma {$f['nombre']} → cuenta {$f['cuenta']}";
                continue;
            }
            $buscarForma->execute([$idEmpresa, $f['tipo'], $f['aplica_en'], mb_strtoupper($f['nombre'])]);
            $row = $buscarForma->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                $sinDestino[] = "forma {$f['nombre']}";
                continue;
            }
            if (!empty($row['id_cuenta_contable'])) {
                $respetadas++;
                continue;
            }
            $updForma->execute([$idCuenta, $idUsuario, (int) $row['id']]);
            $asignadas++;
        }

        // ── Opciones de ingreso/egreso ──
        $buscarPorComp = $db->prepare(
            "SELECT id, id_cuenta_contable FROM empresa_opciones_ingreso_egreso
             WHERE id_empresa = ? AND UPPER(comportamiento) = ? AND eliminado = false
             ORDER BY id LIMIT 1"
        );
        $buscarPorNombre = $db->prepare(
            "SELECT id, id_cuenta_contable FROM empresa_opciones_ingreso_egreso
             WHERE id_empresa = ? AND UPPER(TRIM(nombre)) = ? AND eliminado = false
             ORDER BY id LIMIT 1"
        );
        $updOpcion = $db->prepare(
            "UPDATE empresa_opciones_ingreso_egreso
             SET id_cuenta_contable = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $insOpcion = $db->prepare(
            "INSERT INTO empresa_opciones_ingreso_egreso (
                id_empresa, nombre, aplica_ingresos, aplica_egresos, comportamiento,
                id_cuenta_contable, estado, created_by, created_at, eliminado
             ) VALUES (?, ?, ?, ?, 'GENERAL', ?, 'ACTIVO', ?, CURRENT_TIMESTAMP, false)"
        );

        foreach (self::CUENTAS_OPCIONES as $o) {
            $idCuenta = $idsPorCodigo[$o['cuenta']] ?? null;
            if ($idCuenta === null) {
                $sinCuenta[] = "opción {$o['nombre']} → cuenta {$o['cuenta']}";
                continue;
            }

            // GENERAL no identifica a una sola opción: esas se buscan por nombre.
            if ($o['comportamiento'] === 'GENERAL') {
                $buscarPorNombre->execute([$idEmpresa, mb_strtoupper($o['nombre'])]);
                $row = $buscarPorNombre->fetch(\PDO::FETCH_ASSOC);
            } else {
                $buscarPorComp->execute([$idEmpresa, $o['comportamiento']]);
                $row = $buscarPorComp->fetch(\PDO::FETCH_ASSOC);
            }

            if (!$row) {
                if (empty($o['crear_si_falta'])) {
                    $sinDestino[] = "opción {$o['nombre']}";
                    continue;
                }
                $insOpcion->execute([
                    $idEmpresa,
                    $o['nombre'],
                    !empty($o['ingresos']) ? 'true' : 'false',
                    !empty($o['egresos']) ? 'true' : 'false',
                    $idCuenta,
                    $idUsuario,
                ]);
                $asignadas++;
                continue;
            }

            if (!empty($row['id_cuenta_contable'])) {
                $respetadas++;
                continue;
            }
            // Solo se completa la cuenta: si la opción ya existía, se respeta a qué aplica.
            $updOpcion->execute([$idCuenta, $idUsuario, (int) $row['id']]);
            $asignadas++;
        }

        return [
            'asignadas'   => $asignadas,
            'respetadas'  => $respetadas,
            'sin_cuenta'  => $sinCuenta,
            'sin_destino' => $sinDestino,
        ];
    }
}
