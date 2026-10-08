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
     * Sugerencia de «Reglas por Proveedores»: copia al proveedor destino todas las cuentas propias
     * que el proveedor origen tiene en el asiento de compras (conceptos + IVA por tarifa).
     *
     * Solo aplica si el destino aún no tiene cuentas propias en ese asiento (no pisa una
     * configuración existente). Todo en una transacción, bajo candado del destino.
     *
     * @return int cuentas copiadas
     */
    public function copiarReglasCompraProveedor(int $idOrigen, int $idDestino, int $idEmpresa, int $idUsuario): int
    {
        if ($idOrigen <= 0 || $idDestino <= 0 || $idOrigen === $idDestino) {
            throw new Exception('Proveedores no válidos.');
        }
        $origen  = $this->repo->getNombreProveedorEmpresa($idEmpresa, $idOrigen);
        $destino = $this->repo->getNombreProveedorEmpresa($idEmpresa, $idDestino);
        if ($origen === null || $destino === null) {
            throw new Exception('El proveedor no existe o no pertenece a su empresa.');
        }

        $this->db->beginTransaction();
        try {
            $this->repo->lockReglasProveedor($idEmpresa, $idDestino);

            if ($this->repo->getReglasCompraProveedor($idEmpresa, $idDestino)) {
                throw new Exception("{$destino} ya tiene cuentas propias: revíselas en su tarjeta.");
            }
            $reglas = $this->repo->getReglasCompraProveedor($idEmpresa, $idOrigen);
            if (!$reglas) {
                throw new Exception("{$origen} ya no tiene cuentas propias que copiar.");
            }

            $creadas = [];
            foreach ($reglas as $r) {
                $creadas[] = $this->repo->create([
                    'id_empresa'        => $idEmpresa,
                    'id_usuario'        => $idUsuario,
                    'id_asiento_tipo'   => (int) $r['id_asiento_tipo'],
                    'id_cuenta'         => (int) $r['id_cuenta'],
                    'id_referencia'     => $idDestino,
                    'tipo_referencia'   => 'proveedor',
                    'referencia_texto'  => null,
                    'codigo_tarifa_iva' => $r['codigo_tarifa_iva'],
                    'direccion_iva'     => $r['direccion_iva'],
                    'created_by'        => $idUsuario,
                ]);
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'COPIAR REGLAS PROVEEDOR',
                'asientos_programados',
                $idDestino,
                null,
                [
                    'id_proveedor_origen'  => $idOrigen,
                    'proveedor_origen'     => $origen,
                    'id_proveedor_destino' => $idDestino,
                    'proveedor_destino'    => $destino,
                    'reglas_origen'        => array_column($reglas, 'id'),
                    'reglas_creadas'       => $creadas,
                ]
            );

            $this->db->commit();
            return count($creadas);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Concepto de Facturas de Venta => su equivalente de Recibos de Venta. Recibos tiene catálogo
     * propio (database/migrations/20260715_create_recibos_venta_asientos_tipo.sql), espejo del de
     * facturas; un concepto de facturas sin entrada aquí no se copia (se informa como omitido).
     */
    private const MAPA_CONCEPTOS_FACTURA_A_RECIBO = [
        'PORCOBRARFACTURAVENTA'  => 'PORCOBRARRECIBOVENTA',
        'SUBTOTALFACTURAVENTA'   => 'SUBTOTALRECIBOVENTA',
        'PROPINAFACTURAVENTA'    => 'PROPINARECIBOVENTA',
        'ICEFACTURAVENTA'        => 'ICERECIBOVENTA',
        'DESCUENTOFACTURAVENTA'  => 'DESCUENTORECIBOVENTA',
        'COSTOFACTURAVENTA'      => 'COSTORECIBOVENTA',
        'INVENTARIOFACTURAVENTA' => 'INVENTARIORECIBOVENTA',
        'AJUSTEREDONDEOVENTA'    => 'AJUSTEREDONDEORECIBOVENTA',
    ];

    /** Dimensiones de reglas de ventas que se copian (las mismas de la cascada de AsientoBuilderService). */
    private const DIMENSIONES_VENTA = ['cliente', 'producto', 'categoria', 'marca', 'tipo_produccion'];
    /** El IVA por dimensión no usa Tipo de Producción. */
    private const DIMENSIONES_IVA_VENTA = ['cliente', 'producto', 'categoria', 'marca'];

    private const ETIQUETA_DIMENSION = [
        'cliente' => 'Cliente', 'producto' => 'Producto', 'categoria' => 'Categoría',
        'marca' => 'Marca', 'tipo_produccion' => 'Tipo de producción',
    ];

    /**
     * Vista previa de «Copiar configuración de Facturas de Venta» en Recibos de Venta: qué se
     * crearía, qué cuentas cambiarían y qué reglas de Recibos sobran (no existen en Facturas).
     * No escribe nada.
     */
    public function previsualizarCopiaFacturaARecibo(int $idEmpresa): array
    {
        $plan = $this->planCopiaFacturaARecibo($idEmpresa);
        $this->describirPlan($idEmpresa, $plan);

        $lista = fn(array $items) => array_slice(array_column($items, 'texto'), 0, 60);
        return [
            'crear'      => count($plan['crear']),
            'actualizar' => count($plan['actualizar']),
            'sobrantes'  => count($plan['sobrantes']),
            'iguales'    => $plan['iguales'],
            'omitidas'   => array_slice($plan['omitidas'], 0, 30),
            'detalle'    => [
                'crear'      => $lista($plan['crear']),
                'actualizar' => $lista($plan['actualizar']),
                'sobrantes'  => $lista($plan['sobrantes']),
            ],
        ];
    }

    /**
     * Copia a Recibos de Venta la configuración contable de Facturas de Venta: General, IVA por
     * tarifa y reglas por Cliente/Producto/Categoría/Marca/Tipo de producción (y el IVA de esas
     * dimensiones).
     *
     *  - 'completar': solo crea lo que a Recibos le falta; no toca ninguna cuenta ya puesta.
     *  - 'igualar':   además reemplaza las cuentas distintas y elimina (lógicamente) las reglas de
     *                 Recibos que no existen en Facturas: Recibos queda idéntico a Facturas.
     *
     * Todo en una transacción, bajo candado de la configuración de recibos de la empresa.
     *
     * @return array{creadas:int, actualizadas:int, eliminadas:int, omitidas:array}
     */
    public function copiarConfiguracionFacturaARecibo(int $idEmpresa, int $idUsuario, string $modo): array
    {
        if (!in_array($modo, ['completar', 'igualar'], true)) {
            throw new Exception('Modo de copia no válido.');
        }

        $this->db->beginTransaction();
        try {
            $this->repo->lockConfiguracionTipoAsiento($idEmpresa, 'recibos_venta');
            // El plan se arma dentro del candado: lo que se lee es lo que se escribe.
            $plan = $this->planCopiaFacturaARecibo($idEmpresa);

            $creadas = [];
            foreach ($plan['crear'] as $c) {
                $creadas[] = $this->repo->create([
                    'id_empresa'        => $idEmpresa,
                    'id_usuario'        => $idUsuario,
                    'id_asiento_tipo'   => $c['destino']['id_asiento_tipo'],
                    'id_cuenta'         => $c['id_cuenta'],
                    'id_referencia'     => $c['destino']['id_referencia'],
                    'tipo_referencia'   => $c['destino']['tipo_referencia'],
                    'referencia_texto'  => null,
                    'codigo_tarifa_iva' => $c['destino']['codigo_tarifa_iva'],
                    'direccion_iva'     => $c['destino']['direccion_iva'],
                    'created_by'        => $idUsuario,
                ]);
            }

            $actualizadas = [];
            $eliminadas   = [];
            if ($modo === 'igualar') {
                foreach ($plan['actualizar'] as $a) {
                    $this->repo->actualizarCuentaRegla($a['id_destino'], $idEmpresa, $a['id_cuenta'], $idUsuario);
                    $actualizadas[] = ['id' => $a['id_destino'], 'id_cuenta_anterior' => $a['id_cuenta_anterior'], 'id_cuenta' => $a['id_cuenta']];
                }
                foreach ($plan['sobrantes'] as $s) {
                    $this->repo->delete($s['id_destino'], $idEmpresa, $idUsuario);
                    $eliminadas[] = ['id' => $s['id_destino'], 'id_cuenta' => $s['id_cuenta_anterior']];
                }
            }

            if ($creadas || $actualizadas || $eliminadas) {
                $this->logService->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'COPIAR CONFIGURACION FACTURA A RECIBO',
                    'asientos_programados',
                    null,
                    ['actualizadas' => $actualizadas, 'eliminadas' => $eliminadas],
                    [
                        'modo'                => $modo,
                        'reglas_creadas'      => $creadas,
                        'reglas_actualizadas' => array_column($actualizadas, 'id'),
                        'reglas_eliminadas'   => array_column($eliminadas, 'id'),
                    ]
                );
            }

            $this->db->commit();
            return [
                'creadas'      => count($creadas),
                'actualizadas' => count($actualizadas),
                'eliminadas'   => count($eliminadas),
                'omitidas'     => $plan['omitidas'],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Compara las reglas de Facturas de Venta con las de Recibos de Venta por una clave común
     * (concepto equivalente + dimensión + entidad, o tarifa de IVA) y arma qué crear, qué cuenta
     * cambiar y qué sobra en Recibos.
     */
    private function planCopiaFacturaARecibo(int $idEmpresa): array
    {
        $conceptosRecibo = $this->repo->getConceptosPorCodigo(array_values(self::MAPA_CONCEPTOS_FACTURA_A_RECIBO));

        $origen = [];
        $omitidas = [];
        foreach ($this->repo->getReglasParaCopiar($idEmpresa, 'ventas_factura', 'iva_ventas_factura', 'venta') as $r) {
            $destino = $this->destinoReglaRecibo($r, $conceptosRecibo, true);
            if ($destino === null) {
                continue; // forma que el asiento no lee (p. ej. reglas 'general' antiguas sin referencia)
            }
            if (isset($destino['error'])) {
                $omitidas[] = $destino['error'];
                continue;
            }
            if ($r['cuenta_codigo'] === null) {
                $omitidas[] = "{$destino['concepto']}: la cuenta configurada en Facturas ya no existe en el plan de cuentas.";
                continue;
            }
            if ($destino['id_asiento_tipo'] > 0 && !self::cuentaCompatible($destino['tipo_cuenta'], $r['cuenta_codigo'])) {
                $omitidas[] = "{$destino['concepto']}: la cuenta {$r['cuenta_codigo']} - {$r['cuenta_nombre']} no corresponde a la naturaleza del concepto en Recibos.";
                continue;
            }
            $origen[$destino['clave']] ??= ['regla' => $r, 'destino' => $destino];
        }

        $actual = [];
        $duplicadas = [];
        foreach ($this->repo->getReglasParaCopiar($idEmpresa, 'recibos_venta', 'iva_recibos_venta', 'recibo') as $r) {
            $destino = $this->destinoReglaRecibo($r, $conceptosRecibo, false);
            if ($destino === null || isset($destino['error'])) {
                continue;
            }
            if (isset($actual[$destino['clave']])) {
                $duplicadas[] = ['regla' => $r, 'destino' => $destino];
                continue;
            }
            $actual[$destino['clave']] = ['regla' => $r, 'destino' => $destino];
        }

        $plan = ['crear' => [], 'actualizar' => [], 'sobrantes' => [], 'iguales' => 0, 'omitidas' => $omitidas];
        foreach ($origen as $clave => $o) {
            $a = $actual[$clave] ?? null;
            $item = [
                'destino'    => $o['destino'],
                'id_cuenta'  => (int) $o['regla']['id_cuenta'],
                'cuenta'     => $o['regla']['cuenta_codigo'] . ' - ' . $o['regla']['cuenta_nombre'],
            ];
            if ($a === null) {
                $plan['crear'][] = $item;
            } elseif ((int) $a['regla']['id_cuenta'] !== $item['id_cuenta']) {
                $plan['actualizar'][] = $item + [
                    'id_destino'         => (int) $a['regla']['id'],
                    'id_cuenta_anterior' => (int) $a['regla']['id_cuenta'],
                    'cuenta_anterior'    => trim(($a['regla']['cuenta_codigo'] ?? '?') . ' - ' . ($a['regla']['cuenta_nombre'] ?? 'cuenta eliminada')),
                ];
            } else {
                $plan['iguales']++;
            }
        }
        // Sobran: reglas de Recibos sin equivalente en Facturas, y duplicados de una misma clave.
        foreach (array_merge(array_values(array_diff_key($actual, $origen)), $duplicadas) as $a) {
            $plan['sobrantes'][] = [
                'destino'            => $a['destino'],
                'id_destino'         => (int) $a['regla']['id'],
                'id_cuenta_anterior' => (int) $a['regla']['id_cuenta'],
                'cuenta_anterior'    => trim(($a['regla']['cuenta_codigo'] ?? '?') . ' - ' . ($a['regla']['cuenta_nombre'] ?? 'cuenta eliminada')),
            ];
        }
        return $plan;
    }

    /**
     * Traduce una regla (de Facturas si $esOrigen, de Recibos si no) a cómo se guarda en Recibos y
     * a su clave de comparación. null = forma de regla que el asiento no lee; ['error'=>…] = no se
     * puede copiar.
     */
    private function destinoReglaRecibo(array $r, array $conceptosRecibo, bool $esOrigen): ?array
    {
        $idAsientoTipo = (int) $r['id_asiento_tipo'];
        $tipoRef       = (string) $r['tipo_referencia'];
        $idRef         = (int) $r['id_referencia'];
        $tarifa        = $r['tarifa_iva'] !== null ? (string) $r['tarifa_iva'] : null;

        // Conceptos de asientos_tipo: General o por dimensión.
        if ($idAsientoTipo > 0) {
            $codigoRecibo = $esOrigen
                ? (self::MAPA_CONCEPTOS_FACTURA_A_RECIBO[$r['concepto_codigo']] ?? null)
                : (string) $r['concepto_codigo'];
            $concepto = $codigoRecibo !== null ? ($conceptosRecibo[$codigoRecibo] ?? null) : null;
            if ($concepto === null) {
                return $esOrigen
                    ? ['error' => "{$r['concepto']}: no tiene concepto equivalente en Recibos de Venta."]
                    : null;
            }
            $tipoAsientoRegla = $esOrigen ? 'ventas_factura' : 'recibos_venta';
            $esGeneral = in_array($tipoRef, ['asientos tipo', $tipoAsientoRegla], true) && $idRef === $idAsientoTipo;
            if (!$esGeneral && !(in_array($tipoRef, self::DIMENSIONES_VENTA, true) && $idRef > 0)) {
                return null;
            }
            $idConcepto = (int) $concepto['id'];
            return [
                'clave'             => $esGeneral ? "c|{$codigoRecibo}|general" : "c|{$codigoRecibo}|{$tipoRef}|{$idRef}",
                'concepto'          => (string) $concepto['referencia'],
                'tipo_cuenta'       => (string) $concepto['tipo_cuenta'],
                'dimension'         => $esGeneral ? null : $tipoRef,
                'id_entidad'        => $esGeneral ? null : $idRef,
                'id_asiento_tipo'   => $idConcepto,
                'id_referencia'     => $esGeneral ? $idConcepto : $idRef,
                'tipo_referencia'   => $esGeneral ? 'recibos_venta' : $tipoRef,
                'codigo_tarifa_iva' => null,
                'direccion_iva'     => null,
            ];
        }

        // IVA General por tarifa.
        if (in_array($tipoRef, ['iva_ventas_factura', 'iva_recibos_venta'], true)) {
            if ($idRef <= 0) {
                return null;
            }
            return [
                'clave'             => "ivag|{$idRef}",
                'concepto'          => 'IVA ' . ($tarifa ?? "tarifa {$idRef}"),
                'tipo_cuenta'       => '',
                'dimension'         => null,
                'id_entidad'        => null,
                'id_asiento_tipo'   => 0,
                'id_referencia'     => $idRef,
                'tipo_referencia'   => 'iva_recibos_venta',
                'codigo_tarifa_iva' => null,
                'direccion_iva'     => null,
            ];
        }

        // IVA por dimensión.
        $codigoTarifa = trim((string) $r['codigo_tarifa_iva']);
        if ($codigoTarifa === '' || !in_array($tipoRef, self::DIMENSIONES_IVA_VENTA, true) || $idRef <= 0) {
            return null;
        }
        return [
            'clave'             => "ivad|{$tipoRef}|{$idRef}|{$codigoTarifa}",
            'concepto'          => 'IVA ' . ($tarifa ?? "tarifa {$codigoTarifa}"),
            'tipo_cuenta'       => '',
            'dimension'         => $tipoRef,
            'id_entidad'        => $idRef,
            'id_asiento_tipo'   => 0,
            'id_referencia'     => $idRef,
            'tipo_referencia'   => $tipoRef,
            'codigo_tarifa_iva' => $codigoTarifa,
            'direccion_iva'     => 'recibo',
        ];
    }

    /** Agrega a cada ítem del plan un 'texto' legible: concepto · dimensión entidad: cuenta(s). */
    private function describirPlan(int $idEmpresa, array &$plan): void
    {
        $ids = [];
        foreach (['crear', 'actualizar', 'sobrantes'] as $grupo) {
            foreach ($plan[$grupo] as $it) {
                if ($it['destino']['dimension'] !== null) {
                    $ids[$it['destino']['dimension']][] = $it['destino']['id_entidad'];
                }
            }
        }
        $nombres = [];
        foreach ($ids as $dim => $lista) {
            $nombres[$dim] = $this->repo->getNombresDimension($idEmpresa, $dim, $lista);
        }

        foreach (['crear', 'actualizar', 'sobrantes'] as $grupo) {
            foreach ($plan[$grupo] as &$it) {
                $d = $it['destino'];
                $donde = $d['dimension'] === null
                    ? 'General'
                    : self::ETIQUETA_DIMENSION[$d['dimension']] . ' ' . ($nombres[$d['dimension']][$d['id_entidad']] ?? "#{$d['id_entidad']}");
                $cuentas = match ($grupo) {
                    'crear'      => $it['cuenta'],
                    'actualizar' => "{$it['cuenta_anterior']} → {$it['cuenta']}",
                    'sobrantes'  => $it['cuenta_anterior'],
                };
                $it['texto'] = "{$d['concepto']} · {$donde}: {$cuentas}";
            }
            unset($it);
        }
    }

    /**
     * Obtiene la preferencia de método de contabilización de la empresa para un tipo de asiento.
     */
    /**
     * Tablas cuyos registros «usan» cada tipo de asiento del selector de Configuración Contable.
     * Un tipo sin entrada aquí (p. ej. Cierre del Ejercicio) se muestra siempre.
     */
    public const TABLAS_POR_TIPO_ASIENTO = [
        'ventas_factura'             => ['ventas_cabecera', 'notas_credito_cabecera', 'nota_debito_cabecera'],
        'factura_reembolso'          => ['factura_reembolso_cabecera'],
        'recibos_venta'              => ['recibos_venta_cabecera'],
        'consignacion_venta'         => ['consignaciones_ventas'],
        'ajuste_inventario'          => ['inventario_kardex'],
        'adquisiciones_compras'      => ['compras_cabecera', 'liquidaciones_cabecera'],
        'adquisiciones_importacion'  => ['importaciones_cabecera'],
        'retenciones_venta'          => ['retencion_venta_cabecera'],
        'retenciones_compra'         => ['retencion_compra_cabecera'],
        'ingresos_egresos'           => ['ingresos_cabecera', 'egresos_cabecera'],
        'cobros_pagos'               => ['ingresos_cabecera', 'egresos_cabecera', 'traspasos_cabecera'],
        'nomina'                     => ['rol_cabecera'],
        'activos_fijos_alta'         => ['activos_fijos'],
        'activos_fijos_depreciacion' => ['activos_fijos'],
        'suscripciones_devengo'      => ['suscripciones'],
    ];

    /**
     * Tipos de asiento que la empresa todavía no usa: su módulo no tiene ningún registro y no
     * tienen ninguna cuenta configurada. El selector de Configuración Contable no los lista
     * (pedido del usuario: «Suscripciones - Devengo» solo si hay alguna suscripción, y lo mismo
     * para los demás). Con una cuenta configurada se siguen mostrando, para poder corregirla.
     *
     * @return string[]
     */
    public function tiposAsientoSinUso(int $idEmpresa): array
    {
        $conDatos   = $this->repo->tablasConRegistros($idEmpresa, array_merge(...array_values(self::TABLAS_POR_TIPO_ASIENTO)));
        $conCuentas = $this->repo->tiposAsientoConCuentas($idEmpresa);
        $sinUso = [];
        foreach (self::TABLAS_POR_TIPO_ASIENTO as $tipo => $tablas) {
            if (!array_intersect($tablas, $conDatos) && !in_array($tipo, $conCuentas, true)) {
                $sinUso[] = $tipo;
            }
        }
        return $sinUso;
    }

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
