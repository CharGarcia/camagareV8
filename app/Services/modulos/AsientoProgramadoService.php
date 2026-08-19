<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\AsientoProgramadoRepository;
use App\Rules\modulos\AsientoProgramadoRules;
use App\Services\LogSistemaService;
use Exception;
use PDO;

class AsientoProgramadoService
{
    private PDO $db;
    private AsientoProgramadoRepository $repo;
    private AsientoProgramadoRules $rules;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->repo = new AsientoProgramadoRepository();
        $this->rules = new AsientoProgramadoRules();
        $this->logService = new LogSistemaService();
    }

    /**
     * Registra un asiento programado dentro de una transacción.
     */
    public function registrar(array $data, int $idEmpresa, int $idUsuario): int
    {
        $this->rules->validar($data);
        $this->validarNaturalezaCuenta($data, $idEmpresa);

        $idAsientoTipo = (int) $data['id_asiento_tipo'];
        $idReferencia = !empty($data['id_referencia']) ? (int) $data['id_referencia'] : null;
        $tipoReferencia = !empty($data['tipo_referencia']) ? trim($data['tipo_referencia']) : null;
        $referenciaTexto = !empty($data['referencia_texto']) ? trim((string) $data['referencia_texto']) : null;

        // Resolving legacy or general 'asientos tipo' to actual concept name dynamically
        if ($tipoReferencia === 'asientos tipo' && $idAsientoTipo > 0) {
            $tipoNombre = $this->repo->getTipoAsientoNombre($idAsientoTipo);
            if ($tipoNombre) {
                $tipoReferencia = $tipoNombre;
                $data['tipo_referencia'] = $tipoNombre;
            }
        }

        // Validar si ya existe una regla idéntica para evitar redundancia
        $codigoTarifaIva = !empty($data['codigo_tarifa_iva']) ? trim((string) $data['codigo_tarifa_iva']) : null;
        if ($this->repo->existeRegla($idEmpresa, $idAsientoTipo, $idReferencia, $tipoReferencia, null, $referenciaTexto, $codigoTarifaIva)) {
            throw new Exception('Ya existe un asiento programado con la misma configuración para el tipo de asiento y entidad seleccionados.');
        }

        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = $idUsuario;
        $data['created_by'] = $idUsuario;

        $this->db->beginTransaction();
        try {
            $id = $this->repo->create($data);

            // Obtener datos insertados para la auditoría
            $nuevo = $this->repo->findByIdAndEmpresa($id, $idEmpresa);

            // Registrar log de auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR ASIENTO PROGRAMADO',
                'asientos_programados',
                $id,
                null,
                $nuevo
            );

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza un asiento programado dentro de una transacción.
     */
    public function actualizar(int $id, array $data, int $idEmpresa, int $idUsuario): bool
    {
        $this->rules->validar($data);
        $this->validarNaturalezaCuenta($data, $idEmpresa);

        $idAsientoTipo = (int) $data['id_asiento_tipo'];
        $idReferencia = !empty($data['id_referencia']) ? (int) $data['id_referencia'] : null;
        $tipoReferencia = !empty($data['tipo_referencia']) ? trim($data['tipo_referencia']) : null;
        $referenciaTexto = !empty($data['referencia_texto']) ? trim((string) $data['referencia_texto']) : null;

        // Resolving legacy or general 'asientos tipo' to actual concept name dynamically
        if ($tipoReferencia === 'asientos tipo' && $idAsientoTipo > 0) {
            $tipoNombre = $this->repo->getTipoAsientoNombre($idAsientoTipo);
            if ($tipoNombre) {
                $tipoReferencia = $tipoNombre;
                $data['tipo_referencia'] = $tipoNombre;
            }
        }

        // Validar si ya existe otra regla idéntica que no sea la actual
        $codigoTarifaIva = !empty($data['codigo_tarifa_iva']) ? trim((string) $data['codigo_tarifa_iva']) : null;
        if ($this->repo->existeRegla($idEmpresa, $idAsientoTipo, $idReferencia, $tipoReferencia, $id, $referenciaTexto, $codigoTarifaIva)) {
            throw new Exception('Ya existe otro asiento programado configurado con el mismo tipo de asiento y entidad seleccionados.');
        }

        $data['updated_by'] = $idUsuario;

        $this->db->beginTransaction();
        try {
            $anterior = $this->repo->findByIdAndEmpresa($id, $idEmpresa);
            if (!$anterior) {
                throw new Exception('El asiento programado solicitado no existe o no pertenece a su empresa.');
            }

            $ok = $this->repo->update($id, $idEmpresa, $data);

            $nuevo = $this->repo->findByIdAndEmpresa($id, $idEmpresa);

            // Registrar log de auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ACTUALIZAR ASIENTO PROGRAMADO',
                'asientos_programados',
                $id,
                $anterior,
                $nuevo
            );

            $this->db->commit();
            return $ok;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Clase del plan de cuentas (primer dígito del código) que admite cada valor de
     * asientos_tipo.tipo_cuenta. Costo y gasto conviven en 5 o en 5/6 según el plan de cada
     * empresa (mismo criterio que PlanCuentaRepository::searchCuentas, que es quien filtra el
     * buscador de cuentas de la pantalla).
     */
    private const CLASES_POR_TIPO_CUENTA = [
        'activo'      => ['1'],
        'pasivo'      => ['2'],
        'patrimonio'  => ['3'],
        'ingreso'     => ['4'],
        'costo'       => ['5', '6'],
        'gasto'       => ['5', '6'],
        'costo_gasto' => ['5', '6'],
    ];

    /**
     * ¿Una cuenta del plan encaja en la naturaleza que declara un concepto?
     *
     * Punto único de la comprobación: lo usan el guardado de reglas (validarNaturalezaCuenta),
     * la importación de configuración del sistema viejo (MigracionConfigContableService) y el
     * aviso previo de la sincronización de asientos (SincronizadorAsientosService).
     *
     * Devuelve true —permisivo— cuando no hay con qué comparar: concepto sin `tipo_cuenta`
     * declarado, valor no reconocido por este código, o código de cuenta vacío. Solo devuelve
     * false ante una incompatibilidad segura.
     *
     * @param string|null $tipoCuenta   CSV de asientos_tipo.tipo_cuenta (activo,pasivo,…)
     * @param string|null $codigoCuenta código de plan_cuentas (su primer dígito es la clase)
     */
    public static function cuentaCompatible(?string $tipoCuenta, ?string $codigoCuenta): bool
    {
        $tipoCuenta   = trim((string) $tipoCuenta);
        $codigoCuenta = trim((string) $codigoCuenta);
        if ($tipoCuenta === '' || $codigoCuenta === '') {
            return true;
        }

        $clasesPermitidas = [];
        foreach (explode(',', strtolower($tipoCuenta)) as $tipo) {
            $tipo = trim($tipo);
            if ($tipo === '') {
                continue;
            }
            if (!isset(self::CLASES_POR_TIPO_CUENTA[$tipo])) {
                return true; // valor no reconocido: no bloquear por algo que este código no sabe interpretar
            }
            $clasesPermitidas = array_merge($clasesPermitidas, self::CLASES_POR_TIPO_CUENTA[$tipo]);
        }
        if (empty($clasesPermitidas)) {
            return true;
        }

        return in_array(substr($codigoCuenta, 0, 1), $clasesPermitidas, true);
    }

    /**
     * Impide guardar una cuenta cuya naturaleza contradice al concepto (p. ej. una cuenta de
     * Ingresos 4.x en el concepto "Cuenta por cobrar"). El buscador de cuentas de la pantalla ya
     * acota las opciones por asientos_tipo.tipo_cuenta, pero es solo del lado del cliente: sin
     * esta comprobación, cualquier desajuste del catálogo (o una petición hecha a mano) deja la
     * cuenta mal grabada y TODAS las facturas de esa empresa se contabilizan mal sin ningún aviso
     * —fue exactamente lo ocurrido con reglas por Cliente/Producto del slot PORCOBRARFACTURAVENTA;
     * ver database/diagnosticos/20260819_cxc_ventas_cuenta_incorrecta.sql.
     *
     * Solo valida cuando el concepto declara `tipo_cuenta` y todas sus entradas son conocidas: un
     * concepto sin restricción declarada (o con un valor nuevo) se deja pasar como hasta ahora.
     */
    private function validarNaturalezaCuenta(array $data, int $idEmpresa): void
    {
        $idAsientoTipo = (int) ($data['id_asiento_tipo'] ?? 0);
        $idCuenta      = (int) ($data['id_cuenta'] ?? 0);
        // Las reglas sin concepto base (IVA por tarifa, retenciones, formas y opciones) usan
        // id_asiento_tipo = 0: no hay naturaleza declarada contra la cual comparar.
        if ($idAsientoTipo <= 0 || $idCuenta <= 0) {
            return;
        }

        $info = $this->repo->getConceptoYCuentaParaValidar($idAsientoTipo, $idCuenta, $idEmpresa);
        if ($info === null) {
            return;
        }
        if (self::cuentaCompatible($info['tipo_cuenta'], $info['cuenta_codigo'])) {
            return;
        }

        throw new Exception(sprintf(
            'La cuenta %s - %s no corresponde al concepto «%s», que admite cuentas de tipo: %s. '
            . 'Elija una cuenta de esa naturaleza (o corrija el tipo de cuenta del concepto en Asientos Tipo).',
            $info['cuenta_codigo'],
            $info['cuenta_nombre'],
            $info['concepto'],
            str_replace(',', ', ', (string) $info['tipo_cuenta'])
        ));
    }

    /**
     * Elimina lógicamente un asiento programado dentro de una transacción.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $this->db->beginTransaction();
        try {
            $anterior = $this->repo->findByIdAndEmpresa($id, $idEmpresa);
            if (!$anterior) {
                throw new Exception('El asiento programado solicitado no existe o no pertenece a su empresa.');
            }

            $ok = $this->repo->delete($id, $idEmpresa, $idUsuario);

            // Registrar log de auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR ASIENTO PROGRAMADO',
                'asientos_programados',
                $id,
                $anterior,
                null
            );

            $this->db->commit();
            return $ok;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene la preferencia de método de contabilización de la empresa para un tipo de asiento.
     */
    public function getMetodoPreferencia(int $idEmpresa, string $tipoAsiento): string
    {
        return $this->repo->getMetodoPreferencia($idEmpresa, $tipoAsiento);
    }

    /**
     * Guarda la preferencia de método de contabilización de la empresa.
     */
    public function guardarMetodoPreferencia(int $idEmpresa, string $tipoAsiento, string $metodo, int $idUsuario): void
    {
        $this->db->beginTransaction();
        try {
            $this->repo->guardarMetodoPreferencia($idEmpresa, $tipoAsiento, $metodo, $idUsuario);

            // Registrar log de auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CAMBIAR PREFERENCIA CONTABILIZACION',
                'asientos_preferencia_empresa',
                0,
                null,
                ['tipo_asiento' => $tipoAsiento, 'metodo' => $metodo]
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
