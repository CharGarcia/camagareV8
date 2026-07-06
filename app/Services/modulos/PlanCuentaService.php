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
     * Elimina (lógicamente) el plan de cuentas completo de la empresa.
     * Solo procede si ninguna cuenta tiene movimientos contables; de lo contrario
     * lanza una excepción listando las cuentas que impiden el borrado.
     * Devuelve el número de cuentas eliminadas.
     */
    public function eliminarPlanCompleto(int $idEmpresa, int $idUsuario): int
    {
        $usadas = $this->repository->getCuentasUsadas($idEmpresa);
        if (!empty($usadas)) {
            $ejemplos = array_slice($usadas, 0, 5);
            $lista = implode(', ', array_map(
                fn($c) => trim(($c['codigo'] ?? '') . ' ' . ($c['nombre'] ?? '')),
                $ejemplos
            ));
            $restantes = count($usadas) - count($ejemplos);
            $extra = $restantes > 0 ? " y {$restantes} más" : '';
            throw new Exception("No se puede eliminar el plan de cuentas: hay cuentas con movimientos contables ({$lista}{$extra}).");
        }

        $this->repository->beginTransaction();
        try {
            $afectadas = $this->repository->eliminarTodasPorEmpresa($idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR PLAN',
                'plan_cuentas',
                null,
                null,
                ['total_eliminadas' => $afectadas]
            );

            $this->repository->commit();
            return $afectadas;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public static function getCuentasModeloArray(): array
    {
        return [
            ['codigo' => '1',             'nivel' => '1', 'nombre' => 'ACTIVOS',                                         'codigo_sri' => ''],
            ['codigo' => '1.1',           'nivel' => '2', 'nombre' => 'ACTIVOS CORRIENTES',                              'codigo_sri' => ''],
            ['codigo' => '1.1.1',         'nivel' => '3', 'nombre' => 'EFECTIVO Y EQUIVALENTES DE EFECTIVO',             'codigo_sri' => ''],
            ['codigo' => '1.1.1.01',      'nivel' => '4', 'nombre' => 'CAJA GENERAL',                                    'codigo_sri' => ''],
            ['codigo' => '1.1.1.01.001',  'nivel' => '5', 'nombre' => 'Caja Chica',                    'codigo_sri' => '311', 'supercias_esf' => '1010101', 'supercias_eri' => '6555', 'supercias_ecp_codigo' => '99', 'supercias_ecp_subcodigo' => '555'],
            ['codigo' => '1.1.1.01.002',  'nivel' => '5', 'nombre' => 'Caja General',                  'codigo_sri' => '311', 'supercias_esf' => '1010101', 'supercias_eri' => '6555', 'supercias_ecp_codigo' => '99', 'supercias_ecp_subcodigo' => '555'],
            ['codigo' => '1.1.1.02',      'nivel' => '4', 'nombre' => 'BANCOS LOCALES',                                  'codigo_sri' => ''],
            ['codigo' => '1.1.1.02.001',  'nivel' => '5', 'nombre' => 'Banco Pichincha',               'codigo_sri' => '311', 'supercias_esf' => '1010103'],
            ['codigo' => '1.1.1.02.002',  'nivel' => '5', 'nombre' => 'Banco Guayaquil',               'codigo_sri' => '311', 'supercias_esf' => '1010103'],
            ['codigo' => '1.1.2',         'nivel' => '3', 'nombre' => 'CUENTAS Y DOCUMENTOS POR COBRAR',                 'codigo_sri' => ''],
            ['codigo' => '1.1.2.01',      'nivel' => '4', 'nombre' => 'CLIENTES LOCALES',                                'codigo_sri' => ''],
            ['codigo' => '1.1.2.01.001',  'nivel' => '5', 'nombre' => 'Cuentas por cobrar clientes',   'codigo_sri' => '315', 'supercias_esf' => '10102050202', 'map_asiento' => 'PORCOBRARFACTURAVENTA'],
            ['codigo' => '1.1.3',         'nivel' => '3', 'nombre' => 'INVENTARIOS',                                     'codigo_sri' => ''],
            ['codigo' => '1.1.3.01',      'nivel' => '4', 'nombre' => 'INVENTARIO DE MERCADERÍAS',                       'codigo_sri' => ''],
            ['codigo' => '1.1.3.01.001',  'nivel' => '5', 'nombre' => 'Mercadería para la Venta',      'codigo_sri' => '342', 'supercias_esf' => '10103', 'map_asiento' => 'INVENTARIOFACTURAVENTA'],
            ['codigo' => '1.1.3.02',      'nivel' => '4', 'nombre' => 'INVENTARIO DE SUMINISTROS Y REPUESTOS',           'codigo_sri' => ''],
            ['codigo' => '1.1.3.02.001',  'nivel' => '5', 'nombre' => 'Suministros y Herramientas',    'codigo_sri' => '343', 'supercias_esf' => '1010311'],
            ['codigo' => '1.1.4',         'nivel' => '3', 'nombre' => 'IMPUESTOS POR RECUPERAR',                         'codigo_sri' => ''],
            ['codigo' => '1.1.4.01',      'nivel' => '4', 'nombre' => 'IVA EN COMPRAS',                                  'codigo_sri' => ''],
            ['codigo' => '1.1.4.01.001',  'nivel' => '5', 'nombre' => 'Iva en Compras 15%',            'codigo_sri' => '336', 'supercias_esf' => '1010501'],
            ['codigo' => '1.1.4.01.002',  'nivel' => '5', 'nombre' => 'Iva en Compras 5%',             'codigo_sri' => '336', 'supercias_esf' => '1010501'],
            ['codigo' => '1.1.4.02',      'nivel' => '4', 'nombre' => 'RETENCIONES EN LA FUENTE POR COBRAR',             'codigo_sri' => ''],
            ['codigo' => '1.1.4.02.001',  'nivel' => '5', 'nombre' => 'Retenciones de Renta en Ventas','codigo_sri' => '337', 'supercias_esf' => '1010502'],
            ['codigo' => '1.1.4.02.002',  'nivel' => '5', 'nombre' => 'Retenciones de IVA en Ventas',  'codigo_sri' => '336', 'supercias_esf' => '1010501'],
            ['codigo' => '1.2',           'nivel' => '2', 'nombre' => 'ACTIVOS NO CORRIENTES',                           'codigo_sri' => ''],
            ['codigo' => '1.2.1',         'nivel' => '3', 'nombre' => 'PROPIEDADES, PLANTA Y EQUIPO',                    'codigo_sri' => ''],
            ['codigo' => '1.2.1.01',      'nivel' => '4', 'nombre' => 'TERRENOS',                                        'codigo_sri' => ''],
            ['codigo' => '1.2.1.01.001',  'nivel' => '5', 'nombre' => 'Terrenos',                      'codigo_sri' => '362', 'supercias_esf' => '1020101'],
            ['codigo' => '1.2.1.02',      'nivel' => '4', 'nombre' => 'EDIFICIOS Y OTROS INMUEBLES',                     'codigo_sri' => ''],
            ['codigo' => '1.2.1.02.001',  'nivel' => '5', 'nombre' => 'Edificios',                     'codigo_sri' => '364', 'supercias_esf' => '1020102'],
            ['codigo' => '1.2.1.03',      'nivel' => '4', 'nombre' => 'MUEBLES Y ENSERES',                               'codigo_sri' => ''],
            ['codigo' => '1.2.1.03.001',  'nivel' => '5', 'nombre' => 'Muebles de Oficina',            'codigo_sri' => '373', 'supercias_esf' => '1020105'],
            ['codigo' => '1.2.1.04',      'nivel' => '4', 'nombre' => 'EQUIPOS DE COMPUTACIÓN',                          'codigo_sri' => ''],
            ['codigo' => '1.2.1.04.001',  'nivel' => '5', 'nombre' => 'Computadoras y Software',       'codigo_sri' => '374', 'supercias_esf' => '1020108'],
            ['codigo' => '1.2.1.05',      'nivel' => '4', 'nombre' => 'VEHÍCULOS Y EQUIPO DE TRANSPORTE',                'codigo_sri' => ''],
            ['codigo' => '1.2.1.05.001',  'nivel' => '5', 'nombre' => 'Vehículos',                     'codigo_sri' => '375', 'supercias_esf' => '1020109'],
            ['codigo' => '1.2.2',         'nivel' => '3', 'nombre' => 'DEPRECIACIÓN ACUMULADA',                          'codigo_sri' => ''],
            ['codigo' => '1.2.2.01',      'nivel' => '4', 'nombre' => 'DEPRECIACIÓN ACUMULADA DE ACTIVOS FIJOS',         'codigo_sri' => ''],
            ['codigo' => '1.2.2.01.001',  'nivel' => '5', 'nombre' => 'Depreciación Acum. Equipos Computación', 'codigo_sri' => '386', 'supercias_esf' => '1020112'],
            ['codigo' => '1.2.2.01.002',  'nivel' => '5', 'nombre' => 'Depreciación Acum. Muebles',    'codigo_sri' => '386', 'supercias_esf' => '1020112'],
            ['codigo' => '2',             'nivel' => '1', 'nombre' => 'PASIVOS',                                         'codigo_sri' => ''],
            ['codigo' => '2.1',           'nivel' => '2', 'nombre' => 'PASIVOS CORRIENTES',                              'codigo_sri' => ''],
            ['codigo' => '2.1.1',         'nivel' => '3', 'nombre' => 'CUENTAS Y DOCUMENTOS POR PAGAR',                  'codigo_sri' => ''],
            ['codigo' => '2.1.1.01',      'nivel' => '4', 'nombre' => 'PROVEEDORES LOCALES',                             'codigo_sri' => ''],
            ['codigo' => '2.1.1.01.001',  'nivel' => '5', 'nombre' => 'Cuentas por pagar proveedores', 'codigo_sri' => '513', 'supercias_esf' => '201030102'],
            ['codigo' => '2.1.2',         'nivel' => '3', 'nombre' => 'OBLIGACIONES CON INSTITUCIONES',                  'codigo_sri' => ''],
            ['codigo' => '2.1.2.01',      'nivel' => '4', 'nombre' => 'OBLIGACIONES IESS',                               'codigo_sri' => ''],
            ['codigo' => '2.1.2.01.001',  'nivel' => '5', 'nombre' => 'IESS por Pagar',                'codigo_sri' => '534', 'supercias_esf' => '2010703'],
            ['codigo' => '2.1.2.02',      'nivel' => '4', 'nombre' => 'OBLIGACIONES BANCARIAS',                          'codigo_sri' => ''],
            ['codigo' => '2.1.2.02.001',  'nivel' => '5', 'nombre' => 'Préstamos Bancarios Corto Plazo','codigo_sri' => '525', 'supercias_esf' => '2010401'],
            ['codigo' => '2.1.3',         'nivel' => '3', 'nombre' => 'OBLIGACIONES TRIBUTARIAS',                        'codigo_sri' => ''],
            ['codigo' => '2.1.3.01',      'nivel' => '4', 'nombre' => 'IVA EN VENTAS',                                   'codigo_sri' => ''],
            ['codigo' => '2.1.3.01.001',  'nivel' => '5', 'nombre' => 'Iva en Ventas 15%',             'codigo_sri' => '549', 'supercias_esf' => '2010701', 'map_asiento' => 'IVAFACTURAVENTA'],
            ['codigo' => '2.1.3.02',      'nivel' => '4', 'nombre' => 'RETENCIONES EN LA FUENTE POR PAGAR',              'codigo_sri' => ''],
            ['codigo' => '2.1.3.02.001',  'nivel' => '5', 'nombre' => 'Retención IR por Pagar',        'codigo_sri' => '532', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.3.02.002',  'nivel' => '5', 'nombre' => 'Retenciones de IVA por Pagar',  'codigo_sri' => '549', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.3.02.003',  'nivel' => '5', 'nombre' => 'Retención IR Empleados en Relación de Dependencia', 'codigo_sri' => '532', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.3.03',      'nivel' => '4', 'nombre' => 'IMPUESTO A LA RENTA POR PAGAR',                   'codigo_sri' => ''],
            ['codigo' => '2.1.3.03.001',  'nivel' => '5', 'nombre' => 'Impuesto a la Renta Ejercicio', 'codigo_sri' => '532', 'supercias_esf' => '2010701'],
            ['codigo' => '2.1.4',         'nivel' => '3', 'nombre' => 'BENEFICIOS A EMPLEADOS POR PAGAR',                'codigo_sri' => ''],
            ['codigo' => '2.1.4.01',      'nivel' => '4', 'nombre' => 'PARTICIPACIÓN TRABAJADORES',                      'codigo_sri' => ''],
            ['codigo' => '2.1.4.01.001',  'nivel' => '5', 'nombre' => 'Participación Trabajadores 15%','codigo_sri' => '533', 'supercias_esf' => '2010705'],
            ['codigo' => '2.1.4.02',      'nivel' => '4', 'nombre' => 'JUBILACIÓN PATRONAL Y DESAHUCIO',                 'codigo_sri' => ''],
            ['codigo' => '2.1.4.02.001',  'nivel' => '5', 'nombre' => 'Jubilación Patronal por Pagar', 'codigo_sri' => '535', 'supercias_esf' => '2010704'],
            ['codigo' => '2.1.4.02.002',  'nivel' => '5', 'nombre' => 'Desahucio por Pagar',           'codigo_sri' => '574', 'supercias_esf' => '2010704'],
            ['codigo' => '2.1.4.03',      'nivel' => '4', 'nombre' => 'SUELDOS Y BENEFICIOS SOCIALES POR PAGAR',         'codigo_sri' => ''],
            ['codigo' => '2.1.4.03.001',  'nivel' => '5', 'nombre' => 'Sueldos por Pagar',             'codigo_sri' => '536', 'supercias_esf' => '2010704'],
            ['codigo' => '2.1.4.03.002',  'nivel' => '5', 'nombre' => 'Décimo Tercer Sueldo por Pagar','codigo_sri' => '536', 'supercias_esf' => '2010704'],
            ['codigo' => '2.1.4.03.003',  'nivel' => '5', 'nombre' => 'Décimo Cuarto Sueldo por Pagar','codigo_sri' => '536', 'supercias_esf' => '2010704'],
            ['codigo' => '2.1.4.03.004',  'nivel' => '5', 'nombre' => 'Vacaciones por Pagar',          'codigo_sri' => '536', 'supercias_esf' => '2010704'],
            ['codigo' => '2.1.4.03.005',  'nivel' => '5', 'nombre' => 'Fondos de Reserva por Pagar',   'codigo_sri' => '536', 'supercias_esf' => '2010704'],
            ['codigo' => '3',             'nivel' => '1', 'nombre' => 'PATRIMONIO',                                      'codigo_sri' => ''],
            ['codigo' => '3.1',           'nivel' => '2', 'nombre' => 'CAPITAL SOCIAL',                                  'codigo_sri' => ''],
            ['codigo' => '3.1.1',         'nivel' => '3', 'nombre' => 'CAPITAL SUSCRITO Y/O ASIGNADO',                   'codigo_sri' => ''],
            ['codigo' => '3.1.1.01',      'nivel' => '4', 'nombre' => 'CAPITAL SUSCRITO',                                'codigo_sri' => ''],
            ['codigo' => '3.1.1.01.001',  'nivel' => '5', 'nombre' => 'Capital suscrito y/o asignado', 'codigo_sri' => '601', 'supercias_esf' => '30101', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '301'],
            ['codigo' => '3.1.2',         'nivel' => '3', 'nombre' => 'RESERVAS',                                        'codigo_sri' => ''],
            ['codigo' => '3.1.2.01',      'nivel' => '4', 'nombre' => 'RESERVA LEGAL Y FACULTATIVA',                     'codigo_sri' => ''],
            ['codigo' => '3.1.2.01.001',  'nivel' => '5', 'nombre' => 'Reserva Legal',                 'codigo_sri' => '604', 'supercias_esf' => '30401', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '30401'],
            ['codigo' => '3.2',           'nivel' => '2', 'nombre' => 'RESULTADOS ACUMULADOS',                           'codigo_sri' => ''],
            ['codigo' => '3.2.1',         'nivel' => '3', 'nombre' => 'GANANCIAS O PÉRDIDAS ACUMULADAS',                 'codigo_sri' => ''],
            ['codigo' => '3.2.1.01',      'nivel' => '4', 'nombre' => 'UTILIDADES DE EJERCICIOS ANTERIORES',             'codigo_sri' => ''],
            ['codigo' => '3.2.1.01.001',  'nivel' => '5', 'nombre' => 'Utilidad Acumulada',            'codigo_sri' => '611', 'supercias_esf' => '30601', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '30601'],
            ['codigo' => '3.3',           'nivel' => '2', 'nombre' => 'RESULTADOS DEL EJERCICIO',                        'codigo_sri' => ''],
            ['codigo' => '3.3.1',         'nivel' => '3', 'nombre' => 'UTILIDAD O PÉRDIDA DEL EJERCICIO',                'codigo_sri' => ''],
            ['codigo' => '3.3.1.01',      'nivel' => '4', 'nombre' => 'RESULTADO DEL EJERCICIO',                         'codigo_sri' => ''],
            ['codigo' => '3.3.1.01.001',  'nivel' => '5', 'nombre' => 'Utilidad del Ejercicio',        'codigo_sri' => '615', 'supercias_esf' => '30701', 'supercias_ecp_codigo' => '990101', 'supercias_ecp_subcodigo' => '30701'],
            ['codigo' => '4',             'nivel' => '1', 'nombre' => 'INGRESOS',                                        'codigo_sri' => ''],
            ['codigo' => '4.1',           'nivel' => '2', 'nombre' => 'INGRESOS OPERACIONALES',                          'codigo_sri' => ''],
            ['codigo' => '4.1.1',         'nivel' => '3', 'nombre' => 'VENTAS LOCALES',                                  'codigo_sri' => ''],
            ['codigo' => '4.1.1.01',      'nivel' => '4', 'nombre' => 'VENTAS TARIFA 15%',                               'codigo_sri' => ''],
            ['codigo' => '4.1.1.01.001',  'nivel' => '5', 'nombre' => 'Ventas de Mercadería 15%',      'codigo_sri' => '6001', 'supercias_eri' => '40101', 'map_asiento' => 'SUBTOTALFACTURAVENTA'],
            ['codigo' => '4.1.1.01.002',  'nivel' => '5', 'nombre' => 'Ventas de Servicios 15%',       'codigo_sri' => '6005', 'supercias_eri' => '4010204'],
            ['codigo' => '4.1.1.02',      'nivel' => '4', 'nombre' => 'VENTAS TARIFA 0%',                                'codigo_sri' => ''],
            ['codigo' => '4.1.1.02.001',  'nivel' => '5', 'nombre' => 'Ventas de Mercadería 0%',       'codigo_sri' => '6003', 'supercias_eri' => '40101'],
            ['codigo' => '4.1.1.02.002',  'nivel' => '5', 'nombre' => 'Ventas de Servicios 0%',        'codigo_sri' => '6007', 'supercias_eri' => '4010204'],
            ['codigo' => '4.2',           'nivel' => '2', 'nombre' => 'INGRESOS NO OPERACIONALES',                       'codigo_sri' => ''],
            ['codigo' => '4.2.1',         'nivel' => '3', 'nombre' => 'INGRESOS FINANCIEROS',                            'codigo_sri' => ''],
            ['codigo' => '4.2.1.01',      'nivel' => '4', 'nombre' => 'INTERESES GANADOS',                               'codigo_sri' => ''],
            ['codigo' => '4.2.1.01.001',  'nivel' => '5', 'nombre' => 'Intereses de Bancos',           'codigo_sri' => '6111', 'supercias_eri' => '4010603'],
            ['codigo' => '5',             'nivel' => '1', 'nombre' => 'COSTOS Y GASTOS',                                 'codigo_sri' => ''],
            ['codigo' => '5.1',           'nivel' => '2', 'nombre' => 'COSTO DE VENTAS Y PRODUCCIÓN',                   'codigo_sri' => ''],
            ['codigo' => '5.1.1',         'nivel' => '3', 'nombre' => 'COSTO DE VENTAS',                                 'codigo_sri' => ''],
            ['codigo' => '5.1.1.01',      'nivel' => '4', 'nombre' => 'COSTO DE VENTAS LOCALES',                         'codigo_sri' => ''],
            ['codigo' => '5.1.1.01.001',  'nivel' => '5', 'nombre' => 'Costo de Mercadería',           'codigo_sri' => '7004', 'supercias_eri' => '5010105', 'map_asiento' => 'COSTOFACTURAVENTA'],
            ['codigo' => '5.2',           'nivel' => '2', 'nombre' => 'GASTOS OPERACIONALES',                            'codigo_sri' => ''],
            ['codigo' => '5.2.1',         'nivel' => '3', 'nombre' => 'GASTOS DE ADMINISTRACIÓN',                        'codigo_sri' => ''],
            ['codigo' => '5.2.1.01',      'nivel' => '4', 'nombre' => 'SUELDOS Y SALARIOS',                              'codigo_sri' => ''],
            ['codigo' => '5.2.1.01.001',  'nivel' => '5', 'nombre' => 'Sueldos Administrativos',       'codigo_sri' => '7040', 'supercias_eri' => '5020101'],
            ['codigo' => '5.2.1.01.002',  'nivel' => '5', 'nombre' => 'Décimo Tercer Sueldo',          'codigo_sri' => '7043', 'supercias_eri' => '5020103'],
            ['codigo' => '5.2.1.01.003',  'nivel' => '5', 'nombre' => 'Décimo Cuarto Sueldo',          'codigo_sri' => '7043', 'supercias_eri' => '5020103'],
            ['codigo' => '5.2.1.01.004',  'nivel' => '5', 'nombre' => 'Vacaciones',                    'codigo_sri' => '7043', 'supercias_eri' => '5020103'],
            ['codigo' => '5.2.1.01.005',  'nivel' => '5', 'nombre' => 'Fondos de Reserva',             'codigo_sri' => '7043', 'supercias_eri' => '5020102'],
            ['codigo' => '5.2.1.01.006',  'nivel' => '5', 'nombre' => 'Aporte Patronal al IESS',       'codigo_sri' => '7046', 'supercias_eri' => '5020102'],
            ['codigo' => '5.2.1.02',      'nivel' => '4', 'nombre' => 'HONORARIOS PROFESIONALES',                        'codigo_sri' => ''],
            ['codigo' => '5.2.1.02.001',  'nivel' => '5', 'nombre' => 'Honorarios profesionales y dietas', 'codigo_sri' => '7049', 'supercias_eri' => '5020105'],
            ['codigo' => '5.2.1.03',      'nivel' => '4', 'nombre' => 'SERVICIOS BÁSICOS',                               'codigo_sri' => ''],
            ['codigo' => '5.2.1.03.001',  'nivel' => '5', 'nombre' => 'Agua, Luz y Teléfono',          'codigo_sri' => '7241', 'supercias_eri' => '5020218'],
            ['codigo' => '5.2.1.04',      'nivel' => '4', 'nombre' => 'DEPRECIACIÓN Y AMORTIZACIÓN',                     'codigo_sri' => ''],
            ['codigo' => '5.2.1.04.001',  'nivel' => '5', 'nombre' => 'Depreciación Activos Fijos',    'codigo_sri' => '7067', 'supercias_eri' => '502022101'],
            ['codigo' => '5.2.1.04.002',  'nivel' => '5', 'nombre' => 'Amortización Intangibles',      'codigo_sri' => '7094', 'supercias_eri' => '502022201'],
            ['codigo' => '5.2.1.05',      'nivel' => '4', 'nombre' => 'OTROS GASTOS ADMINISTRATIVOS',                    'codigo_sri' => ''],
            ['codigo' => '5.2.1.05.001',  'nivel' => '5', 'nombre' => 'Suministros y Materiales de Oficina', 'codigo_sri' => '7190', 'supercias_eri' => '5020127'],
            ['codigo' => '5.2.1.05.002',  'nivel' => '5', 'nombre' => 'Mantenimiento y Reparaciones',  'codigo_sri' => '7196', 'supercias_eri' => '5020208'],
            ['codigo' => '5.2.1.05.003',  'nivel' => '5', 'nombre' => 'Seguros y Reaseguros',          'codigo_sri' => '7202', 'supercias_eri' => '5020214'],
            ['codigo' => '5.2.2',         'nivel' => '3', 'nombre' => 'GASTOS DE VENTAS',                                'codigo_sri' => ''],
            ['codigo' => '5.2.2.01',      'nivel' => '4', 'nombre' => 'GASTOS DE COMERCIALIZACIÓN',                      'codigo_sri' => ''],
            ['codigo' => '5.2.2.01.001',  'nivel' => '5', 'nombre' => 'Promoción y Publicidad',        'codigo_sri' => '7173', 'supercias_eri' => '5020211'],
            ['codigo' => '5.2.2.01.002',  'nivel' => '5', 'nombre' => 'Transporte',                    'codigo_sri' => '7176', 'supercias_eri' => '5020215'],
            ['codigo' => '5.2.2.01.003',  'nivel' => '5', 'nombre' => 'Combustibles y Lubricantes',    'codigo_sri' => '7178', 'supercias_eri' => '5020212'],
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

        $mapeos = [];
        $nuevasCreadas = 0;
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

                if (isset($c['map_asiento'])) {
                    $mapeos[$c['map_asiento']] = $id;
                }
            }
            
            if ($nuevasCreadas > 0) {
                $this->logService->registrar($idUsuario, $idEmpresa, 'CARGA MASIVA', 'plan_cuentas', 0, null, ['total_creadas' => $nuevasCreadas]);
            }

            if ($configurarAsientos && !empty($mapeos)) {
                $db = \App\core\Database::getConnection();
                
                // Limpiar mapeos anteriores para evitar duplicados en configuraciones generales
                $db->prepare("DELETE FROM asientos_programados WHERE id_empresa = ? AND tipo_referencia = 'general'")->execute([$idEmpresa]);
                
                foreach ($mapeos as $codigoAsiento => $idCuenta) {
                    // Buscar el id_asiento_tipo
                    $st = $db->prepare("SELECT id FROM asientos_tipo WHERE codigo = ?");
                    $st->execute([$codigoAsiento]);
                    $idAsientoTipo = $st->fetchColumn();
                    if ($idAsientoTipo) {
                        $stIns = $db->prepare("INSERT INTO asientos_programados (id_empresa, id_usuario, id_asiento_tipo, id_cuenta, tipo_referencia, created_by) VALUES (?, ?, ?, ?, 'general', ?)");
                        $stIns->execute([$idEmpresa, $idUsuario, $idAsientoTipo, $idCuenta, $idUsuario]);
                    }
                }
                $this->logService->registrar($idUsuario, $idEmpresa, 'CONFIGURACION AUTO', 'asientos_programados', 0, null, ['mapeos' => $mapeos]);
            }

            $this->repository->commit();
            return ['status' => true, 'message' => "Se validó el plan modelo. Cuentas nuevas insertadas: {$nuevasCreadas}."];
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
