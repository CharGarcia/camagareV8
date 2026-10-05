<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\CruceRetencionSri;
use App\repositories\BaseRepository;
use PDO;

class AsientoProgramadoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('asientos_programados');
    }

    /**
     * Obtiene el listado de asientos programados con filtros y paginación.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $whitelist = ['id', 'asiento_tipo_codigo', 'cuenta_codigo', 'tipo_referencia'];
        if (!in_array($ordenCol, $whitelist)) {
            $ordenCol = 'id';
        }

        $params = [':id_empresa' => $idEmpresa];
        $whereSql = "WHERE ap.id_empresa = :id_empresa AND ap.eliminado = false";

        if ($buscar !== '') {
            $whereSql .= " AND (at.codigo ILIKE :buscar OR pc.codigo ILIKE :buscar OR pc.nombre ILIKE :buscar OR ap.tipo_referencia ILIKE :buscar)";
            $params[':buscar'] = "%{$buscar}%";
        }

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) 
                     FROM {$this->table} ap
                     INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                     INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                     {$whereSql}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;
        
        $orderExpr = match($ordenCol) {
            'asiento_tipo_codigo' => 'at.codigo',
            'cuenta_codigo'       => 'pc.codigo',
            'tipo_referencia'     => 'ap.tipo_referencia',
            default               => 'ap.id'
        };

        $sqlRows = "SELECT ap.*, 
                           at.codigo AS asiento_tipo_codigo, 
                           at.tipo_asiento AS asiento_tipo_concepto,
                           at.referencia AS asiento_tipo_referencia,
                           pc.codigo AS cuenta_codigo, 
                           pc.nombre AS cuenta_nombre,
                           c.nombre AS cliente_nombre,
                           p.razon_social AS proveedor_nombre
                    FROM {$this->table} ap
                    INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                    INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    LEFT JOIN clientes c ON c.id = ap.id_referencia AND ap.tipo_referencia = 'cliente'
                    LEFT JOIN proveedores p ON p.id = ap.id_referencia AND ap.tipo_referencia = 'proveedor'
                    {$whereSql}
                    ORDER BY {$orderExpr} {$ordenDir}, ap.id DESC";

        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $st = $this->db->prepare($sqlRows);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    /**
     * Busca un asiento programado por ID.
     */
    public function findByIdAndEmpresa(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT ap.*, 
                       at.codigo AS asiento_tipo_codigo,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM {$this->table} ap
                INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE ap.id = :id AND ap.id_empresa = :id_empresa AND ap.eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crea un nuevo asiento programado en la empresa.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, id_asiento_tipo, id_cuenta, id_referencia, tipo_referencia,
                    referencia_texto, codigo_tarifa_iva, direccion_iva, created_by, created_at, eliminado
                ) VALUES (
                    :id_empresa, :id_usuario, :id_asiento_tipo, :id_cuenta, :id_referencia, :tipo_referencia,
                    :referencia_texto, :codigo_tarifa_iva, :direccion_iva, :created_by, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $data['id_empresa'],
            ':id_usuario'       => $data['id_usuario'],
            ':id_asiento_tipo'  => $data['id_asiento_tipo'],
            ':id_cuenta'        => $data['id_cuenta'],
            ':id_referencia'    => $data['id_referencia'] ?: null,
            ':tipo_referencia'  => $data['tipo_referencia'] ?: null,
            ':referencia_texto' => !empty($data['referencia_texto']) ? trim((string) $data['referencia_texto']) : null,
            ':codigo_tarifa_iva' => !empty($data['codigo_tarifa_iva']) ? trim((string) $data['codigo_tarifa_iva']) : null,
            ':direccion_iva'    => !empty($data['direccion_iva']) ? trim((string) $data['direccion_iva']) : null,
            ':created_by'       => $data['created_by']
        ]);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un asiento programado.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_asiento_tipo = :id_asiento_tipo,
                    id_cuenta = :id_cuenta,
                    id_referencia = :id_referencia,
                    tipo_referencia = :tipo_referencia,
                    referencia_texto = :referencia_texto,
                    codigo_tarifa_iva = :codigo_tarifa_iva,
                    direccion_iva = :direccion_iva,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_asiento_tipo'  => $data['id_asiento_tipo'],
            ':id_cuenta'        => $data['id_cuenta'],
            ':id_referencia'    => $data['id_referencia'] ?: null,
            ':tipo_referencia'  => $data['tipo_referencia'] ?: null,
            ':referencia_texto' => !empty($data['referencia_texto']) ? trim((string) $data['referencia_texto']) : null,
            ':codigo_tarifa_iva' => !empty($data['codigo_tarifa_iva']) ? trim((string) $data['codigo_tarifa_iva']) : null,
            ':direccion_iva'    => !empty($data['direccion_iva']) ? trim((string) $data['direccion_iva']) : null,
            ':updated_by'       => $data['updated_by'],
            ':id'               => $id,
            ':id_empresa'       => $idEmpresa
        ]);
    }

    /**
     * Eliminación lógica de un asiento programado.
     */
    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                    eliminado = true, 
                    deleted_by = :deleted_by,
                    deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id'           => $id, 
            ':id_empresa'   => $idEmpresa,
            ':deleted_by'   => $idUsuario
        ]);
    }

    /**
     * Verifica si ya existe una regla para el mismo Asiento Tipo y misma Referencia (evitar duplicaciones).
     */
    public function existeRegla(int $idEmpresa, int $idAsientoTipo, ?int $idReferencia, ?string $tipoReferencia, ?int $idExcluir = null, ?string $referenciaTexto = null, ?string $codigoTarifaIva = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE id_empresa = :id_empresa
                  AND id_asiento_tipo = :id_asiento_tipo
                  AND eliminado = false";

        $params = [
            ':id_empresa' => $idEmpresa,
            ':id_asiento_tipo' => $idAsientoTipo
        ];

        // Reglas con clave de TEXTO (p. ej. 'item_compra'): se identifican por tipo + referencia_texto.
        if ($referenciaTexto !== null && trim($referenciaTexto) !== '') {
            $sql .= " AND tipo_referencia = :tipo_ref AND TRIM(referencia_texto) = :ref_txt";
            $params[':tipo_ref'] = $tipoReferencia;
            $params[':ref_txt'] = trim($referenciaTexto);
            if ($idExcluir !== null && $idExcluir > 0) {
                $sql .= " AND id != :id_exc";
                $params[':id_exc'] = $idExcluir;
            }
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return ((int) $st->fetchColumn()) > 0;
        }

        if ($idReferencia !== null && $idReferencia > 0) {
            if ($tipoReferencia !== 'cliente' && $tipoReferencia !== 'proveedor' && $tipoReferencia !== 'producto' && $tipoReferencia !== 'categoria' && $tipoReferencia !== 'marca' && $tipoReferencia !== 'iva' && $tipoReferencia !== 'empleado' && $tipoReferencia !== 'tipo_produccion') {
                // For general rules, check both new and old reference types to prevent duplicates
                $sql .= " AND id_referencia = :id_ref AND (tipo_referencia = :tipo_ref OR tipo_referencia = 'asientos tipo')";
            } else {
                $sql .= " AND id_referencia = :id_ref AND tipo_referencia = :tipo_ref";
            }
            $params[':id_ref'] = $idReferencia;
            $params[':tipo_ref'] = $tipoReferencia;
        } else {
            $sql .= " AND id_referencia IS NULL";
        }

        // Overrides de IVA por dimensión comparten id_asiento_tipo=0 entre todas las tarifas de una
        // misma entidad: sin este filtro, "tarifa 15% para el cliente X" y "tarifa 0% para el cliente X"
        // se detectarían como duplicados entre sí.
        if ($codigoTarifaIva !== null && $codigoTarifaIva !== '') {
            $sql .= " AND codigo_tarifa_iva = :tarifa";
            $params[':tarifa'] = $codigoTarifaIva;
        }

        if ($idExcluir !== null && $idExcluir > 0) {
            $sql .= " AND id != :id_exc";
            $params[':id_exc'] = $idExcluir;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ((int) $st->fetchColumn()) > 0;
    }

    /**
     * Obtiene todos los asientos tipo de un concepto y su homólogo programado a nivel general de empresa.
     */
    /** Overrides por empleado con datos de la cuenta: [id_asiento_tipo => [id_cuenta,codigo,nombre]]. */
    public function getReglasEmpleadoConCuenta(int $idEmpresa, int $idEmpleado): array
    {
        $st = $this->db->prepare("SELECT ap.id_asiento_tipo, ap.id_cuenta, pc.codigo, pc.nombre
                                  FROM {$this->table} ap JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                                  WHERE ap.id_empresa = :e AND ap.tipo_referencia = 'empleado' AND ap.id_referencia = :id AND ap.eliminado = false");
        $st->execute([':e' => $idEmpresa, ':id' => $idEmpleado]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id_asiento_tipo']] = ['id_cuenta' => (int) $r['id_cuenta'], 'codigo' => $r['codigo'], 'nombre' => $r['nombre']];
        }
        return $map;
    }

    /** Overrides de cuenta por empleado: [id_asiento_tipo => id_cuenta]. */
    public function getReglasEmpleado(int $idEmpresa, int $idEmpleado): array
    {
        $st = $this->db->prepare("SELECT id_asiento_tipo, id_cuenta FROM {$this->table}
                                  WHERE id_empresa = :e AND tipo_referencia = 'empleado' AND id_referencia = :id
                                    AND eliminado = false AND id_cuenta IS NOT NULL");
        $st->execute([':e' => $idEmpresa, ':id' => $idEmpleado]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id_asiento_tipo']] = (int) $r['id_cuenta'];
        }
        return $map;
    }

    public function getReglasGeneralesPorConcepto(int $idEmpresa, string $tipoAsiento): array
    {
        $sql = "SELECT at.id AS id_asiento_tipo,
                       at.tipo_asiento,
                       at.referencia AS concepto,
                       at.detalle,
                       at.codigo,
                       at.tipo_cuenta,
                       at.debe_haber,
                       ap.id AS id_programado,
                       ap.id_cuenta,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre,
                       CAST(ap.created_at AS DATE) AS asignada_desde
                FROM asientos_tipo at
                LEFT JOIN {$this->table} ap ON ap.id_asiento_tipo = at.id
                                           AND ap.id_empresa = :id_empresa 
                                           AND ap.id_referencia = at.id 
                                           AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento) 
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE at.tipo_asiento = :tipo_asiento AND at.eliminado = false
                ORDER BY at.codigo ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $idEmpresa,
            ':tipo_asiento'  => $tipoAsiento
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conceptos OPCIONALES: si la empresa no les asigna cuenta (ni en General ni en la regla de la
     * entidad), su valor se contabiliza en el concepto de respaldo, que es donde iba antes de que
     * existieran — así agregarlos nunca deja sin asiento a una empresa que no los configuró. Por eso
     * no se reportan como faltantes (si al respaldo le falta cuenta, ya se reporta el respaldo). El
     * respaldo lo aplica quien arma el asiento: RolAsientoService::devolverPrestamosSinCuenta().
     * código opcional => código de respaldo (mismo tipo_asiento).
     */
    public const CONCEPTOS_CON_RESPALDO = [
        'PRESTAMOQUIROGRAFARIONOMINA' => 'DESCUENTOSNOMINA',
        'PRESTAMOHIPOTECARIONOMINA'   => 'DESCUENTOSNOMINA',
        'PRESTAMOEMPRESANOMINA'       => 'DESCUENTOSNOMINA',
    ];

    /**
     * Anota en cada regla de getReglasGeneralesPorConcepto() el nombre de su concepto de respaldo
     * ('respaldo_concepto'; null si el concepto no es opcional), para que Configuración Contable no
     * marque como faltante un concepto opcional que se dejó sin cuenta.
     */
    public function anotarConceptosOpcionales(array $reglas): array
    {
        $nombres = array_column($reglas, 'concepto', 'codigo');
        foreach ($reglas as &$r) {
            $respaldo = self::CONCEPTOS_CON_RESPALDO[$r['codigo'] ?? ''] ?? null;
            $r['respaldo_concepto'] = $respaldo !== null ? ($nombres[$respaldo] ?? $respaldo) : null;
        }
        unset($r);
        return $reglas;
    }

    /**
     * comportamiento (empresa_opciones_ingreso_egreso) => [tipo_asiento, codigo] de la cuenta
     * "oficial" que ese módulo YA usa para su propia Cuenta por Pagar/Cobrar en Configuración
     * Contable. Solo cubre comportamientos con una ÚNICA cuenta oficial resoluble (compra,
     * liquidación, factura de venta, recibo de venta). ROL también tiene cuenta oficial, pero
     * repartida entre DOS cuentas de 'nomina' según el tipo de rol (ver
     * COMPORTAMIENTO_CUENTA_BLOQUEADA_SIN_OFICIAL) — no encaja en este mapa de "una sola cuenta".
     * Anticipos y préstamos (ANTICIPO_CLIENTE, ANTICIPO_PROVEEDOR, QUINCENA, PRESTAMO) sí quedan
     * fuera de ambos: son la transacción de origen y necesitan su propia cuenta configurable.
     */
    private const COMPORTAMIENTO_CUENTA_OFICIAL = [
        'COMPRA'        => ['adquisiciones_compras', 'PORPAGARFACTURACOMPRA'],
        'LIQUIDACION'   => ['adquisiciones_compras', 'PORPAGARFACTURACOMPRA'],
        'FACTURA_VENTA' => ['ventas_factura',        'PORCOBRARFACTURAVENTA'],
        'RECIBO_VENTA'  => ['recibos_venta',         'PORCOBRARRECIBOVENTA'],
    ];

    /**
     * Comportamientos con cuenta oficial pero SIN una única cuenta resoluble por
     * getCuentaOficialPorComportamiento(): ROL reparte su monto entre "Sueldos por Pagar" (rol
     * MENSUAL) y "Anticipos y Descuentos" (rol QUINCENA/SEMANAL), ambas de tipo_asiento='nomina'
     * — ver AsientoBuilderService::generarAsientoEgreso(), sumaRolMensualPorEgreso() /
     * sumaRolNoMensualPorEgreso(). Se bloquea su cuenta libre igual que los de
     * COMPORTAMIENTO_CUENTA_OFICIAL (tieneCuentaOficialPorComportamiento() los une a todos), pero
     * NO participan en getCuentaOficialPorComportamiento() — no hay una sola cuenta "oficial" que
     * asignarles ahí, la resolución ya ocurre por su cuenta en generarAsientoEgreso().
     */
    private const COMPORTAMIENTO_CUENTA_BLOQUEADA_SIN_OFICIAL = ['ROL'];

    /** ¿Este comportamiento tiene cuenta oficial y por tanto su cuenta libre queda bloqueada (ver ambas constantes arriba)? */
    public function tieneCuentaOficialPorComportamiento(string $comportamiento): bool
    {
        $comportamiento = strtoupper($comportamiento);
        return isset(self::COMPORTAMIENTO_CUENTA_OFICIAL[$comportamiento])
            || in_array($comportamiento, self::COMPORTAMIENTO_CUENTA_BLOQUEADA_SIN_OFICIAL, true);
    }

    /**
     * Resuelve la cuenta "oficial" (Configuración Contable) de un comportamiento de concepto de
     * Ingresos/Egresos, respetando la cascada por especificidad acordada para todo el sistema:
     *   1. Entidad del documento (cliente del ingreso / proveedor del egreso) — "la entidad manda".
     *   2. Regla General del slot.
     *   3. Cuenta única: si todas las reglas del slot coinciden en una sola cuenta, se usa.
     * Antes solo se consultaba el nivel General, así que una empresa con sus cuentas configuradas
     * por proveedor o por categoría resolvía id_cuenta=0 pese a tenerlas todas puestas.
     *
     * Devuelve null si ese comportamiento no tiene equivalente (sigue usando su propia cuenta
     * libre) o si tiene cuenta oficial pero repartida en varias cuentas sin una sola resoluble
     * (ROL — ver COMPORTAMIENTO_CUENTA_BLOQUEADA_SIN_OFICIAL); devuelve id_cuenta=0 si SÍ tiene
     * cuenta oficial pero la cascada no la resuelve (nada configurado, o varias cuentas posibles
     * sin General que desempate: ahí no se adivina y el documento queda pendiente).
     */
    public function getCuentaOficialPorComportamiento(
        int $idEmpresa,
        string $comportamiento,
        ?int $idEntidad = null,
        ?string $tipoEntidad = null
    ): ?array {
        $map = self::COMPORTAMIENTO_CUENTA_OFICIAL[strtoupper($comportamiento)] ?? null;
        if ($map === null) {
            return null;
        }
        [$tipoAsiento, $codigo] = $map;

        // 1. La ENTIDAD manda (cascada acordada, Opción 2): si el cliente del ingreso o el
        //    proveedor del egreso tiene su propia regla para este concepto, esa gana sobre General.
        if ($idEntidad !== null && $idEntidad > 0
            && in_array($tipoEntidad, ['cliente', 'proveedor'], true)) {
            $porEntidad = $this->getCuentaSlotPorEntidad($idEmpresa, $codigo, $idEntidad, $tipoEntidad);
            if ($porEntidad !== null) {
                return $porEntidad;
            }
        }

        // 2. General.
        foreach ($this->getReglasGeneralesPorConcepto($idEmpresa, $tipoAsiento) as $r) {
            if (($r['codigo'] ?? '') === $codigo && (int) ($r['id_cuenta'] ?? 0) > 0) {
                return [
                    'id_cuenta'     => (int) ($r['id_cuenta'] ?? 0),
                    'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                ];
            }
        }

        // 3. Sin General: si TODA la cascada de ese slot apunta a una sola cuenta, no hay
        //    ambigüedad y se usa. Un cobro/pago no tiene producto ni categoría, así que esos
        //    niveles no son evaluables desde aquí; pero si todos coinciden en la misma cuenta,
        //    exigir además la regla General sería pedir que se configure algo ya deducible.
        //    Con dos o más cuentas distintas NO se adivina: se devuelve 0 y el documento queda
        //    pendiente.
        $unica = $this->getCuentaUnicaDelSlot($idEmpresa, $codigo);
        if ($unica !== null) {
            return $unica;
        }

        return ['id_cuenta' => 0, 'cuenta_codigo' => '', 'cuenta_nombre' => ''];
    }

    /**
     * Cuenta que la entidad (cliente/proveedor) tiene configurada para un slot concreto, o null si
     * no tiene regla propia para ese concepto.
     */
    private function getCuentaSlotPorEntidad(int $idEmpresa, string $codigoSlot, int $idEntidad, string $tipoEntidad): ?array
    {
        $sql = "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                  FROM {$this->table} ap
                  JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                  JOIN plan_cuentas pc  ON pc.id = ap.id_cuenta
                 WHERE ap.id_empresa      = :emp
                   AND at.codigo          = :cod
                   AND ap.tipo_referencia = :tipo
                   AND ap.id_referencia   = :ref
                   AND ap.eliminado       = false
                   AND ap.id_cuenta IS NOT NULL
                 LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':cod' => $codigoSlot, ':tipo' => $tipoEntidad, ':ref' => $idEntidad]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id_cuenta'     => (int) $row['id_cuenta'],
            'cuenta_codigo' => $row['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $row['cuenta_nombre'] ?? '',
        ];
    }

    /**
     * Devuelve la cuenta del slot solo si TODAS sus reglas (cualquier nivel de la cascada)
     * apuntan a la misma; null si hay varias distintas o ninguna.
     */
    private function getCuentaUnicaDelSlot(int $idEmpresa, string $codigoSlot): ?array
    {
        $sql = "SELECT DISTINCT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                  FROM {$this->table} ap
                  JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                  JOIN plan_cuentas pc  ON pc.id = ap.id_cuenta
                 WHERE ap.id_empresa = :emp
                   AND at.codigo     = :cod
                   AND ap.eliminado  = false
                   AND ap.id_cuenta IS NOT NULL";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':cod' => $codigoSlot]);
        $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        if (count($filas) !== 1) {
            return null;
        }

        return [
            'id_cuenta'     => (int) $filas[0]['id_cuenta'],
            'cuenta_codigo' => $filas[0]['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $filas[0]['cuenta_nombre'] ?? '',
        ];
    }

    /**
     * comportamiento => [[etiqueta, tipo_asiento, codigo], ...] para los de
     * COMPORTAMIENTO_CUENTA_BLOQUEADA_SIN_OFICIAL: no tienen una única cuenta oficial, pero sí
     * varias — se muestran informativamente en Opciones de Ingreso/Egreso (autocompletado de solo
     * lectura, igual que getCuentaOficialPorComportamiento() para los de una sola cuenta), sin que
     * ninguna de ellas se use realmente para resolver el asiento (eso ya lo hace
     * AsientoBuilderService::generarAsientoEgreso() directamente por su cuenta).
     */
    private const COMPORTAMIENTO_CUENTAS_OFICIALES_MULTIPLES = [
        'ROL' => [
            ['Rol mensual',       'nomina', 'SUELDOSPORPAGARNOMINA'],
            ['Quincena / Semana', 'nomina', 'ANTICIPOSDESCUENTOSNOMINA'],
        ],
    ];

    /**
     * Resuelve, solo para mostrar (autocompletado de solo lectura en Opciones de Ingreso/Egreso),
     * las cuentas oficiales de un comportamiento con varias cuentas (ver
     * COMPORTAMIENTO_CUENTAS_OFICIALES_MULTIPLES). Array vacío si el comportamiento no aplica.
     */
    public function getCuentasOficialesMultiplesPorComportamiento(int $idEmpresa, string $comportamiento): array
    {
        $mapas = self::COMPORTAMIENTO_CUENTAS_OFICIALES_MULTIPLES[strtoupper($comportamiento)] ?? [];
        if (empty($mapas)) {
            return [];
        }
        $resultado = [];
        foreach ($mapas as [$etiqueta, $tipoAsiento, $codigo]) {
            $cuenta = ['id_cuenta' => 0, 'cuenta_codigo' => '', 'cuenta_nombre' => ''];
            foreach ($this->getReglasGeneralesPorConcepto($idEmpresa, $tipoAsiento) as $r) {
                if (($r['codigo'] ?? '') === $codigo) {
                    $cuenta = [
                        'id_cuenta'     => (int) ($r['id_cuenta'] ?? 0),
                        'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                        'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                    ];
                    break;
                }
            }
            $resultado[] = ['etiqueta' => $etiqueta] + $cuenta;
        }
        return $resultado;
    }

    /**
     * Obtiene las opciones de Ingresos/Egresos (módulo empresa_opciones_ingreso_egreso) activas
     * que aplican a la naturaleza indicada, cruzadas con su cuenta contable programada.
     * La cuenta se toma del asiento programado si existe; en su defecto, de la cuenta asignada
     * en el propio módulo de opciones (id_cuenta_contable).
     *
     * Excluye los comportamientos bloqueados (tieneCuentaOficialPorComportamiento): Compras,
     * Liquidaciones, Facturas de Venta y Recibos de Venta ya configuran su cuenta contable desde
     * la sección propia de ese módulo; Nómina (ROL) la resuelve sola (mensual → Sueldos por Pagar,
     * quincena/semana → Anticipos y Descuentos, ver AsientoBuilderService). guardarReglaOpcionAjax()
     * y OpcionIngresoEgresoService rechazan asignarles una cuenta aparte — mostrarlos en este
     * listado solo confundía al usuario.
     *
     * @param string $naturaleza 'ingreso' | 'egreso'
     */
    public function getReglasOpcionesIngresoEgreso(int $idEmpresa, string $naturaleza): array
    {
        $col     = $naturaleza === 'ingreso' ? 'aplica_ingresos' : 'aplica_egresos';
        $tipoRef = $naturaleza === 'ingreso' ? 'opcion_ingreso'  : 'opcion_egreso';

        $params = [
            ':id_empresa'    => $idEmpresa,
            ':id_empresa_ap' => $idEmpresa,
            ':tipo_ref'      => $tipoRef,
        ];
        $comportamientosOcultos = array_merge(
            array_keys(self::COMPORTAMIENTO_CUENTA_OFICIAL),
            self::COMPORTAMIENTO_CUENTA_BLOQUEADA_SIN_OFICIAL
        );
        $exclusiones = [];
        foreach ($comportamientosOcultos as $i => $comportamiento) {
            $ph = ":comp_modulo_{$i}";
            $exclusiones[] = $ph;
            $params[$ph] = $comportamiento;
        }
        $exclusionSql = implode(', ', $exclusiones);

        $sql = "SELECT o.id AS id_opcion,
                       o.nombre AS concepto,
                       o.comportamiento,
                       o.tipo_cuenta_contable,
                       ap.id AS id_programado,
                       COALESCE(ap.id_cuenta, o.id_cuenta_contable) AS id_cuenta,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso o
                LEFT JOIN {$this->table} ap ON ap.id_referencia = o.id
                                           AND ap.tipo_referencia = :tipo_ref
                                           AND ap.id_empresa = :id_empresa_ap
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap.id_cuenta, o.id_cuenta_contable)
                WHERE o.id_empresa = :id_empresa
                  AND o.{$col} = TRUE
                  AND UPPER(o.estado) = 'ACTIVO'
                  AND o.eliminado = FALSE
                  AND UPPER(o.comportamiento) NOT IN ({$exclusionSql})
                ORDER BY o.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las formas de cobro/pago (módulo empresa_formas_pago) activas que aplican al flujo
     * indicado, cruzadas con su cuenta contable programada. La cuenta se toma del asiento
     * programado si existe; en su defecto, de la cuenta asignada en el propio módulo de formas.
     *
     * @param string $flujo 'cobro' | 'pago'
     */
    public function getReglasFormasCobrosPagos(int $idEmpresa, string $flujo): array
    {
        $aplica  = $flujo === 'cobro' ? 'INGRESO'     : 'EGRESO';
        $tipoRef = $flujo === 'cobro' ? 'forma_cobro' : 'forma_pago';

        $sql = "SELECT f.id AS id_forma,
                       f.nombre AS concepto,
                       f.aplica_en,
                       f.tipo_cuenta_contable,
                       ap.id AS id_programado,
                       COALESCE(ap.id_cuenta, f.id_cuenta_contable) AS id_cuenta,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM empresa_formas_pago f
                LEFT JOIN {$this->table} ap ON ap.id_referencia = f.id
                                           AND ap.tipo_referencia = :tipo_ref
                                           AND ap.id_empresa = :id_empresa_ap
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap.id_cuenta, f.id_cuenta_contable)
                WHERE f.id_empresa = :id_empresa
                  AND f.activo = TRUE
                  AND f.eliminado = FALSE
                  AND (f.aplica_en = 'AMBAS' OR f.aplica_en = :aplica)
                ORDER BY f.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $idEmpresa,
            ':id_empresa_ap' => $idEmpresa,
            ':tipo_ref'      => $tipoRef,
            ':aplica'        => $aplica
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Formas de Cobro/Pago sobre las que tendría sentido replicar la cuenta que se acaba de
     * asignar a $idForma: la propia forma (que puede salir en los dos bloques si aplica_en =
     * 'AMBAS') y las formas hermanas que representan LA MISMA CUENTA BANCARIA — mismo banco y
     * mismo número de cuenta, ambas de tipo BANCO/CHEQUE (p. ej. "Cheques Pichincha" y
     * "Transferencias Pichincha": dos medios sobre la misma cuenta física). El número se compara
     * normalizado (sin guiones ni espacios) y debe existir: dos formas del mismo banco sin número
     * NO se emparejan, porque pueden ser cuentas distintas (corriente y ahorros).
     *
     * Devuelve, por cada forma, la cuenta vigente en cada flujo con el mismo COALESCE que usa el
     * listado (asiento programado si existe; si no, la cuenta del propio módulo de Formas). Debe
     * llamarse ANTES de guardar: al escribir se toca f.id_cuenta_contable, que es justamente lo
     * que el otro bloque muestra cuando no hay asiento programado propio.
     */
    public function getDestinosReplicaForma(int $idEmpresa, int $idForma): array
    {
        $sql = "WITH origen AS (
                    SELECT f.id,
                           UPPER(COALESCE(f.tipo, '')) AS tipo,
                           f.id_banco,
                           NULLIF(regexp_replace(COALESCE(f.numero_cuenta, ''), '[^0-9A-Za-z]', '', 'g'), '') AS cuenta_bancaria
                    FROM empresa_formas_pago f
                    WHERE f.id = :id_forma
                      AND f.id_empresa = :id_empresa_origen
                      AND f.eliminado = FALSE
                )
                SELECT f.id AS id_forma,
                       f.nombre AS concepto,
                       UPPER(COALESCE(f.aplica_en, '')) AS aplica_en,
                       CASE WHEN f.id = o.id THEN 1 ELSE 0 END AS es_misma_forma,
                       COALESCE(apc.id_cuenta, f.id_cuenta_contable) AS id_cuenta_cobro,
                       pcc.codigo AS cobro_codigo,
                       pcc.nombre AS cobro_nombre,
                       COALESCE(app.id_cuenta, f.id_cuenta_contable) AS id_cuenta_pago,
                       pcp.codigo AS pago_codigo,
                       pcp.nombre AS pago_nombre
                FROM origen o
                JOIN empresa_formas_pago f
                       ON f.id_empresa = :id_empresa_formas
                      AND f.eliminado = FALSE
                      AND f.activo = TRUE
                      AND (
                            f.id = o.id
                            OR (
                                 o.tipo IN ('BANCO', 'CHEQUE')
                             AND UPPER(COALESCE(f.tipo, '')) IN ('BANCO', 'CHEQUE')
                             AND o.id_banco IS NOT NULL
                             AND f.id_banco = o.id_banco
                             AND o.cuenta_bancaria IS NOT NULL
                             AND NULLIF(regexp_replace(COALESCE(f.numero_cuenta, ''), '[^0-9A-Za-z]', '', 'g'), '') = o.cuenta_bancaria
                               )
                          )
                LEFT JOIN {$this->table} apc ON apc.id_referencia = f.id
                                            AND apc.tipo_referencia = 'forma_cobro'
                                            AND apc.id_empresa = :id_empresa_ap_cobro
                                            AND apc.eliminado = false
                LEFT JOIN plan_cuentas pcc ON pcc.id = COALESCE(apc.id_cuenta, f.id_cuenta_contable)
                LEFT JOIN {$this->table} app ON app.id_referencia = f.id
                                            AND app.tipo_referencia = 'forma_pago'
                                            AND app.id_empresa = :id_empresa_ap_pago
                                            AND app.eliminado = false
                LEFT JOIN plan_cuentas pcp ON pcp.id = COALESCE(app.id_cuenta, f.id_cuenta_contable)
                ORDER BY CASE WHEN f.id = o.id THEN 0 ELSE 1 END, f.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_forma'            => $idForma,
            ':id_empresa_origen'   => $idEmpresa,
            ':id_empresa_formas'   => $idEmpresa,
            ':id_empresa_ap_cobro' => $idEmpresa,
            ':id_empresa_ap_pago'  => $idEmpresa
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta vigente de una opción de Ingreso/Egreso en una naturaleza concreta: la que el
     * usuario ve hoy en esa fila del modal (mismo COALESCE que getReglasOpcionesIngresoEgreso).
     * Se lee ANTES de guardar, por el mismo motivo que getDestinosReplicaForma. Aquí no hay
     * "hermanas" que valgan: una opción no representa una cuenta bancaria, así que el único
     * destino posible es la misma opción en el bloque contrario.
     *
     * @param string $naturaleza 'ingreso' | 'egreso'
     */
    public function getCuentaVigenteOpcion(int $idEmpresa, int $idOpcion, string $naturaleza): ?array
    {
        $tipoRef = $naturaleza === 'ingreso' ? 'opcion_ingreso' : 'opcion_egreso';

        $sql = "SELECT o.id AS id_opcion,
                       o.nombre AS concepto,
                       UPPER(COALESCE(o.comportamiento, '')) AS comportamiento,
                       CASE WHEN o.aplica_ingresos AND o.aplica_egresos THEN 1 ELSE 0 END AS aplica_ambos,
                       CASE WHEN UPPER(o.estado) = 'ACTIVO' THEN 1 ELSE 0 END AS activo,
                       COALESCE(ap.id_cuenta, o.id_cuenta_contable) AS id_cuenta,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso o
                LEFT JOIN {$this->table} ap ON ap.id_referencia = o.id
                                           AND ap.tipo_referencia = :tipo_ref
                                           AND ap.id_empresa = :id_empresa_ap
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap.id_cuenta, o.id_cuenta_contable)
                WHERE o.id = :id_opcion
                  AND o.id_empresa = :id_empresa
                  AND o.eliminado = FALSE
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':tipo_ref'      => $tipoRef,
            ':id_empresa_ap' => $idEmpresa,
            ':id_opcion'     => $idOpcion,
            ':id_empresa'    => $idEmpresa
        ]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene la regla (asiento programado) asociada a una referencia concreta
     * (opción de Ingreso/Egreso, forma de cobro/pago, etc.) por su tipo de referencia.
     */
    public function getReglaPorReferencia(int $idEmpresa, int $idReferencia, string $tipoReferencia): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empresa = :id_empresa
                  AND id_referencia = :id_referencia
                  AND tipo_referencia = :tipo_referencia
                  AND eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'      => $idEmpresa,
            ':id_referencia'   => $idReferencia,
            ':tipo_referencia' => $tipoReferencia
        ]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene una regla general específica por empresa y asiento tipo.
     */
    public function getReglaGeneralPorAsientoTipo(int $idEmpresa, int $idAsientoTipo): ?array
    {
        $sql = "SELECT ap.*, at.tipo_asiento FROM {$this->table} ap
                INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                WHERE ap.id_empresa = :id_empresa 
                  AND ap.id_asiento_tipo = :id_asiento_tipo 
                  AND ap.id_referencia = :id_asiento_tipo 
                  AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento) 
                  AND ap.eliminado = false 
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'      => $idEmpresa,
            ':id_asiento_tipo' => $idAsientoTipo
        ]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene el nombre del tipo de asiento de la tabla asientos_tipo.
     */
    public function getTipoAsientoNombre(int $idAsientoTipo): ?string
    {
        $sql = "SELECT tipo_asiento FROM asientos_tipo WHERE id = :id AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoTipo]);
        return $st->fetchColumn() ?: null;
    }

    /**
     * Datos que necesita el guardián de naturaleza de cuenta (ver
     * AsientoProgramadoService::validarNaturalezaCuenta): qué clases de cuenta admite el concepto
     * (asientos_tipo.tipo_cuenta, CSV: activo|pasivo|patrimonio|ingreso|costo|gasto) y qué código
     * tiene la cuenta que se le quiere asignar.
     *
     * @return array{concepto:string, codigo_concepto:string, tipo_cuenta:string, cuenta_codigo:string, cuenta_nombre:string}|null
     */
    public function getConceptoYCuentaParaValidar(int $idAsientoTipo, int $idCuenta, int $idEmpresa): ?array
    {
        $sql = "SELECT at.referencia AS concepto,
                       at.codigo     AS codigo_concepto,
                       COALESCE(at.tipo_cuenta, '') AS tipo_cuenta,
                       pc.codigo     AS cuenta_codigo,
                       pc.nombre     AS cuenta_nombre
                FROM asientos_tipo at
                JOIN plan_cuentas pc ON pc.id = :id_cuenta AND pc.id_empresa = :id_empresa AND pc.eliminado = false
                WHERE at.id = :id_tipo AND at.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_tipo'    => $idAsientoTipo,
            ':id_cuenta'  => $idCuenta,
            ':id_empresa' => $idEmpresa,
        ]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene la preferencia de método de contabilización de la empresa para un tipo de asiento.
     */
    public function getMetodoPreferencia(int $idEmpresa, string $tipoAsiento): string
    {
        $sql = "SELECT metodo FROM asientos_preferencia_empresa 
                WHERE id_empresa = :id_empresa AND tipo_asiento = :tipo_asiento AND eliminado = false 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':tipo_asiento' => $tipoAsiento
        ]);
        $val = $stmt->fetchColumn();
        return $val ?: 'general';
    }

    /**
     * Guarda o actualiza la preferencia de método de contabilización de la empresa.
     */
    public function guardarMetodoPreferencia(int $idEmpresa, string $tipoAsiento, string $metodo, int $idUsuario): void
    {
        $sqlCheck = "SELECT id FROM asientos_preferencia_empresa 
                     WHERE id_empresa = :id_empresa AND tipo_asiento = :tipo_asiento AND eliminado = false";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute([
            ':id_empresa' => $idEmpresa,
            ':tipo_asiento' => $tipoAsiento
        ]);
        $id = $stmtCheck->fetchColumn();

        if ($id) {
            $sql = "UPDATE asientos_preferencia_empresa 
                    SET metodo = :metodo, updated_at = NOW(), updated_by = :usuario 
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':metodo' => $metodo,
                ':usuario' => $idUsuario,
                ':id' => $id
            ]);
        } else {
            $sql = "INSERT INTO asientos_preferencia_empresa 
                    (id_empresa, tipo_asiento, metodo, created_by) 
                    VALUES (:id_empresa, :tipo_asiento, :metodo, :usuario)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':tipo_asiento' => $tipoAsiento,
                ':metodo' => $metodo,
                ':usuario' => $idUsuario
            ]);
        }
    }
    /**
     * Obtiene las reglas específicas para tarifas de IVA (ventas) de la empresa.
     */
    public function getReglasIvaVentas(int $idEmpresa): array
    {
        $sql = "SELECT 0 AS id_asiento_tipo,
                       'ventas_factura' AS tipo_asiento,
                       'Tarifa iva ' || t.tarifa AS concepto,
                       'Tarifa de iva en ventas ' || t.tarifa AS detalle,
                       'IVA-' || t.codigo AS codigo,
                       'pasivo' AS tipo_cuenta,
                       'haber' AS debe_haber,
                       ap.id AS id_programado,
                       ap.id_cuenta,
                       CAST(t.codigo AS INTEGER) AS id_referencia,
                       'iva_ventas_factura' AS tipo_referencia,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM tarifa_iva t
                LEFT JOIN {$this->table} ap ON ap.id_referencia = CAST(t.codigo AS INTEGER)
                                           AND ap.tipo_referencia = 'iva_ventas_factura'
                                           AND ap.id_empresa = :id_empresa 
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE t.porcentaje_iva > 0
                ORDER BY t.tarifa ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reglas de IVA por tarifa para COMPRAS (crédito tributario).
     * Espejo de getReglasIvaVentas, pero la cuenta es de naturaleza ACTIVO (IVA crédito
     * tributario) y va al DEBE. tipo_referencia = 'iva_compras_factura'.
     */
    public function getReglasIvaCompras(int $idEmpresa): array
    {
        $sql = "SELECT 0 AS id_asiento_tipo,
                       'adquisiciones_compras' AS tipo_asiento,
                       'Tarifa iva ' || t.tarifa AS concepto,
                       'IVA crédito tributario tarifa ' || t.tarifa AS detalle,
                       'IVA-' || t.codigo AS codigo,
                       'activo' AS tipo_cuenta,
                       'debe' AS debe_haber,
                       ap.id AS id_programado,
                       ap.id_cuenta,
                       CAST(t.codigo AS INTEGER) AS id_referencia,
                       'iva_compras_factura' AS tipo_referencia,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM tarifa_iva t
                LEFT JOIN {$this->table} ap ON ap.id_referencia = CAST(t.codigo AS INTEGER)
                                           AND ap.tipo_referencia = 'iva_compras_factura'
                                           AND ap.id_empresa = :id_empresa
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE t.porcentaje_iva > 0
                ORDER BY t.tarifa ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reglas de IVA por tarifa para RECIBOS DE VENTA. Independiente de getReglasIvaVentas():
     * catálogo propio (tipo_asiento='recibos_venta', tipo_referencia='iva_recibos_venta') para
     * que una empresa pueda configurar cuentas de IVA distintas para Recibos que para Facturas.
     */
    public function getReglasIvaRecibosVenta(int $idEmpresa): array
    {
        $sql = "SELECT 0 AS id_asiento_tipo,
                       'recibos_venta' AS tipo_asiento,
                       'Tarifa iva ' || t.tarifa AS concepto,
                       'Tarifa de iva en recibos de venta ' || t.tarifa AS detalle,
                       'IVA-' || t.codigo AS codigo,
                       'pasivo' AS tipo_cuenta,
                       'haber' AS debe_haber,
                       ap.id AS id_programado,
                       ap.id_cuenta,
                       CAST(t.codigo AS INTEGER) AS id_referencia,
                       'iva_recibos_venta' AS tipo_referencia,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM tarifa_iva t
                LEFT JOIN {$this->table} ap ON ap.id_referencia = CAST(t.codigo AS INTEGER)
                                           AND ap.tipo_referencia = 'iva_recibos_venta'
                                           AND ap.id_empresa = :id_empresa
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE t.porcentaje_iva > 0
                ORDER BY t.tarifa ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una regla específica de Tarifa IVA para ventas.
     */
    public function getReglaGeneralIva(int $idEmpresa, int $idTarifa): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND id_referencia = :id_ref 
                  AND tipo_referencia = 'iva_ventas_factura' 
                  AND eliminado = false 
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_ref'     => $idTarifa
        ]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Reglas de CONCEPTO (no IVA) ya guardadas para una entidad/ítem puntual de una dimensión
     * (cliente/proveedor/producto/categoria/marca, o 'item_compra' por texto). Espejo de
     * getReglasEmpleado() para el resto de dimensiones. @return array<int,int> [id_asiento_tipo => id_cuenta]
     */
    private function getReglasPorEntidad(int $idEmpresa, string $tipoReferencia, int $idReferencia, ?string $referenciaTexto): array
    {
        if ($referenciaTexto !== null) {
            $st = $this->db->prepare("SELECT id_asiento_tipo, id_cuenta FROM {$this->table}
                                      WHERE id_empresa = :e AND tipo_referencia = :tr AND TRIM(referencia_texto) = :rt
                                        AND id_asiento_tipo <> 0 AND eliminado = false AND id_cuenta IS NOT NULL");
            $st->execute([':e' => $idEmpresa, ':tr' => $tipoReferencia, ':rt' => trim($referenciaTexto)]);
        } else {
            $st = $this->db->prepare("SELECT id_asiento_tipo, id_cuenta FROM {$this->table}
                                      WHERE id_empresa = :e AND tipo_referencia = :tr AND id_referencia = :id
                                        AND id_asiento_tipo <> 0 AND eliminado = false AND id_cuenta IS NOT NULL");
            $st->execute([':e' => $idEmpresa, ':tr' => $tipoReferencia, ':id' => $idReferencia]);
        }
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id_asiento_tipo']] = (int) $r['id_cuenta'];
        }
        return $map;
    }

    /**
     * De un lote de documentos, cuántos son de un cliente/proveedor que tiene cuentas PROPIAS para
     * ese tipo de asiento (cascada "la entidad manda"). Lo usa el aviso de asientos pendientes
     * para decir "falta configurar … en algunos proveedores" en vez de culpar solo a la General.
     * $tablaDoc/$colEntidad son literales del código (SincronizadorAsientosService::AREAS), no
     * entrada del usuario. Falla en silencio (0) si la tabla/columna no existe.
     */
    /**
     * Nombre del cliente/proveedor de cada documento de un lote (id_documento => nombre), para que
     * el aviso de asientos pendientes diga DE QUIÉN son los documentos que no se pudieron generar.
     * $tablaDoc/$colEntidad son literales del código (SincronizadorAsientosService::AREAS). Falla en
     * silencio ([]) si la tabla/columna no existe.
     *
     * @return array<int,string>
     */
    public function getNombresEntidadPorDocumento(string $tablaDoc, string $colEntidad, string $tipoReferencia, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids || !preg_match('/^[a-z_]+$/', $tablaDoc) || !preg_match('/^[a-z_]+$/', $colEntidad)) {
            return [];
        }
        [$tablaEnt, $colNombre] = match ($tipoReferencia) {
            'proveedor' => ['proveedores', 'razon_social'],
            'cliente'   => ['clientes', 'nombre'],
            default     => [null, null],
        };
        if ($tablaEnt === null) {
            return [];
        }
        try {
            $st = $this->db->prepare(
                "SELECT d.id, e.{$colNombre} AS nombre
                   FROM {$tablaDoc} d
                   JOIN {$tablaEnt} e ON e.id = d.{$colEntidad}
                  WHERE d.id = ANY(string_to_array(:ids, ',')::int[])"
            );
            $st->execute([':ids' => implode(',', $ids)]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(int) $r['id']] = trim((string) $r['nombre']);
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function contarDocumentosConEntidadConReglas(string $tablaDoc, string $colEntidad, string $tipoReferencia, string $tipoAsiento, array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids || !preg_match('/^[a-z_]+$/', $tablaDoc) || !preg_match('/^[a-z_]+$/', $colEntidad)) {
            return 0;
        }
        try {
            $st = $this->db->prepare(
                "SELECT COUNT(*)
                   FROM {$tablaDoc} d
                  WHERE d.id = ANY(string_to_array(:ids, ',')::int[])
                    AND EXISTS (SELECT 1
                                  FROM {$this->table} ap
                                  JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                                 WHERE ap.id_empresa      = d.id_empresa
                                   AND ap.tipo_referencia = :tipo_ref
                                   AND ap.id_referencia   = d.{$colEntidad}
                                   AND at.tipo_asiento    = :tipo_asiento
                                   AND ap.eliminado       = false
                                   AND ap.id_cuenta IS NOT NULL)"
            );
            $st->execute([
                ':ids'          => implode(',', $ids),
                ':tipo_ref'     => $tipoReferencia,
                ':tipo_asiento' => $tipoAsiento,
            ]);
            return (int) $st->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Para una entidad/ítem puntual de una dimensión, calcula qué le faltaría configurar para que
     * el asiento quede completo SI esa entidad usa esta regla (Opción 2: "la entidad manda" —
     * ver AsientoBuilderService::generarAsientoSugerido()). Un concepto NO falta si lo cubre la
     * entidad O la regla GENERAL (lo no cubierto por la entidad cae a General); solo se reporta lo
     * que ninguna de las dos resuelve.
     *
     * El IVA por tarifa tiene su PROPIA cascada de 5 niveles (cliente/proveedor > producto >
     * categoría > marca > general), independiente de los demás conceptos — por eso se evalúa
     * aparte y no como un concepto más de asientos_tipo.
     *
     * @return array{conceptos: string[], iva: string[]}
     */
    public function getConceptosFaltantesEntidad(
        int $idEmpresa,
        string $tipoAsiento,
        string $tipoReferencia,
        int $idReferencia,
        ?string $referenciaTexto = null
    ): array {
        // 1. Conceptos normales: general (LEFT JOIN ya trae si tiene o no cuenta) + reglas propias.
        $generales = $this->getReglasGeneralesPorConcepto($idEmpresa, $tipoAsiento);
        $reglasEntidad = $this->getReglasPorEntidad($idEmpresa, $tipoReferencia, $idReferencia, $referenciaTexto);

        // Alcance real del motor (2026-08-01): el reparto COMPLETO por categoría ya está
        // implementado en AsientoBuilderService para 'ventas_factura' (también cubre Notas de
        // Crédito, que reusan la misma configuración), 'recibos_venta' y 'adquisiciones_compras'.
        // Actualizar este flag si se agregan más tipos.
        $repartoCompletoImplementado = in_array($tipoAsiento, ['ventas_factura', 'recibos_venta', 'adquisiciones_compras'], true);
        $esDimensionPorLinea = in_array($tipoReferencia, ['producto', 'categoria', 'marca', 'item_compra', 'tipo_produccion'], true);

        $conceptosFaltantes = [];
        foreach ($generales as $g) {
            $codigo = strtoupper($g['codigo'] ?? '');
            // El Ajuste por redondeo no se reporta: su ausencia solo importa en descuadres de
            // centavos y ya tiene su propio aviso en AsientoBuilderService::aplicarAjusteRedondeo().
            if (str_contains($codigo, 'REDONDEO')) continue;
            // Los conceptos opcionales tampoco: sin cuenta, su valor va al concepto de respaldo.
            if (array_key_exists($codigo, self::CONCEPTOS_CON_RESPALDO)) continue;

            $idTipo = (int) $g['id_asiento_tipo'];
            $conceptoLower = strtolower($g['concepto'] ?? '');
            $esSubtotal = str_contains($codigo, 'SUBTOTAL') || str_contains($conceptoLower, 'subtotal');
            // Propina y Descuento NUNCA se resuelven por dimensión, ni siquiera donde ya está el
            // reparto completo: Propina por decisión del usuario (no varía por línea); Descuento
            // porque su reparto por categoría todavía no está implementado (alcance pendiente). En
            // Compras, ICE tampoco se reparte (el subtotal ya viene neto por línea, "v1" — a
            // diferencia de Ventas/Recibos, donde ICE SÍ tiene reparto propio).
            $esIce = str_contains($codigo, 'ICE') || str_contains($conceptoLower, 'ice');
            $esPropinaODescuento = str_contains($codigo, 'PROPINA') || str_contains($conceptoLower, 'propina')
                                || str_contains($codigo, 'DESC')     || str_contains($conceptoLower, 'descuento')
                                || ($tipoAsiento === 'adquisiciones_compras' && $esIce);

            $entidadAplicaAEsteConcepto = !$esDimensionPorLinea
                ? true // Cliente/Proveedor: aplica a todos los conceptos, siempre.
                : ($repartoCompletoImplementado ? !$esPropinaODescuento : $esSubtotal);

            $tieneGeneral = !empty($g['id_cuenta']);
            $tieneEntidad = $entidadAplicaAEsteConcepto && isset($reglasEntidad[$idTipo]);
            if (!$tieneGeneral && !$tieneEntidad) {
                $conceptosFaltantes[] = $g['concepto'] ?? $g['codigo'] ?? 'sin nombre';
            }
        }

        // 2. IVA por tarifa: solo para los tipos de asiento que tienen cascada de IVA implementada.
        $mapaIvaGeneral = match ($tipoAsiento) {
            'adquisiciones_compras' => 'iva_compras_factura',
            'recibos_venta'         => 'iva_recibos_venta',
            'ventas_factura'        => 'iva_ventas_factura',
            default                 => null,
        };
        $ivaFaltantes = [];
        if ($mapaIvaGeneral !== null) {
            $direccionIva = match ($tipoAsiento) {
                'adquisiciones_compras' => 'compra',
                'recibos_venta'         => 'recibo',
                default                 => 'venta',
            };
            $condicionEntidad = $referenciaTexto !== null
                ? "TRIM(ap_ent.referencia_texto) = :ref_val"
                : "ap_ent.id_referencia = :ref_val";
            $sql = "SELECT t.codigo, t.tarifa,
                           ap_gen.id_cuenta AS id_cuenta_general,
                           ap_ent.id_cuenta AS id_cuenta_entidad
                    FROM tarifa_iva t
                    LEFT JOIN {$this->table} ap_gen
                           ON ap_gen.id_referencia = CAST(t.codigo AS INTEGER) AND ap_gen.tipo_referencia = :tref_gen
                          AND ap_gen.id_empresa = :emp1 AND ap_gen.eliminado = false
                    LEFT JOIN {$this->table} ap_ent
                           ON ap_ent.id_asiento_tipo = 0 AND ap_ent.tipo_referencia = :tref_ent
                          AND ap_ent.codigo_tarifa_iva = t.codigo::text AND ap_ent.direccion_iva = :dir
                          AND ap_ent.id_empresa = :emp2 AND ap_ent.eliminado = false AND {$condicionEntidad}
                    WHERE t.porcentaje_iva > 0
                    ORDER BY t.tarifa ASC";
            $st = $this->db->prepare($sql);
            $st->execute([
                ':tref_gen' => $mapaIvaGeneral,
                ':emp1'     => $idEmpresa,
                ':tref_ent' => $tipoReferencia,
                ':dir'      => $direccionIva,
                ':emp2'     => $idEmpresa,
                ':ref_val'  => $referenciaTexto !== null ? trim($referenciaTexto) : $idReferencia,
            ]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (empty($row['id_cuenta_general']) && empty($row['id_cuenta_entidad'])) {
                    $ivaFaltantes[] = 'IVA tarifa ' . $row['tarifa'];
                }
            }
        }

        return ['conceptos' => $conceptosFaltantes, 'iva' => $ivaFaltantes];
    }

    /**
     * Obtiene el listado de retenciones SRI aplicadas en ventas que le hayan hecho a la empresa,
     * cruzando con su homóloga programada en asientos_programados (Debe) y con la cuenta de cobrar facturas
     * de venta (Haber - PORCOBRARFACTURAVENTA).
     */
    public function getReglasRetencionesVenta(int $idEmpresa): array
    {
        // 1. Obtener la cuenta de cuentas por cobrar para ventas (PORCOBRARFACTURAVENTA)
        $sqlHaber = "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                     FROM asientos_programados ap
                     INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                     INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                     WHERE ap.id_empresa = :id_empresa 
                       AND at.codigo = 'PORCOBRARFACTURAVENTA'
                       AND ap.eliminado = false
                       AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = 'ventas_factura' OR ap.tipo_referencia = at.tipo_asiento)
                     LIMIT 1";
        $stHaber = $this->db->prepare($sqlHaber);
        $stHaber->execute([':id_empresa' => $idEmpresa]);
        $haberDefecto = $stHaber->fetch(PDO::FETCH_ASSOC) ?: [
            'id_cuenta' => null,
            'cuenta_codigo' => '',
            'cuenta_nombre' => 'No Configurada'
        ];

        // 2. Códigos de retención usados en ventas de esta empresa (o ya configurados)
        $conceptos = $this->getConceptosRetencion($idEmpresa, 'venta');
        $docsSinCatalogo = $this->getDocumentosSinCatalogo($idEmpresa, 'venta', $conceptos);

        $reglas = [];
        foreach ($conceptos as $c) {
            if (empty($c['id'])) {
                $reglas[] = $this->reglaRetencionSinCatalogo($c, 'retenciones_venta', $docsSinCatalogo[$c['codigo_usado']] ?? []);
                continue;
            }
            // Buscar la cuenta Debe configurada en asientos_programados para esta retención
            // Buscamos 'retenciones_venta_debe' o 'retenciones_venta' (por retrocompatibilidad)
            $sqlDebe = "SELECT ap.id AS id_programado, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                        FROM asientos_programados ap
                        INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                        WHERE ap.id_empresa = :id_empresa
                          AND (ap.tipo_referencia = 'retenciones_venta_debe' OR ap.tipo_referencia = 'retenciones_venta')
                          AND ap.id_referencia = :id_referencia
                          AND ap.eliminado = false
                        ORDER BY ap.tipo_referencia DESC, ap.id DESC LIMIT 1";
            $stDebe = $this->db->prepare($sqlDebe);
            $stDebe->execute([
                ':id_empresa' => $idEmpresa,
                ':id_referencia' => $c['id']
            ]);
            $debeRow = $stDebe->fetch(PDO::FETCH_ASSOC) ?: [
                'id_programado' => null,
                'id_cuenta' => null,
                'cuenta_codigo' => '',
                'cuenta_nombre' => ''
            ];

            // Buscar la cuenta Haber configurada específicamente para esta retención
            $sqlHaberEsp = "SELECT ap.id AS id_programado, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                            FROM asientos_programados ap
                            INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                            WHERE ap.id_empresa = :id_empresa
                              AND ap.tipo_referencia = 'retenciones_venta_haber'
                              AND ap.id_referencia = :id_referencia
                              AND ap.eliminado = false
                            LIMIT 1";
            $stHaberEsp = $this->db->prepare($sqlHaberEsp);
            $stHaberEsp->execute([
                ':id_empresa' => $idEmpresa,
                ':id_referencia' => $c['id']
            ]);
            $haberEspRow = $stHaberEsp->fetch(PDO::FETCH_ASSOC);

            // Si hay cuenta Haber específica guardada, la usamos; si no, usamos la de autocompletado por defecto
            $haberId = $haberEspRow ? $haberEspRow['id_cuenta'] : $haberDefecto['id_cuenta'];
            $haberCodigo = $haberEspRow ? $haberEspRow['cuenta_codigo'] : $haberDefecto['cuenta_codigo'];
            $haberNombre = $haberEspRow ? $haberEspRow['cuenta_nombre'] : $haberDefecto['cuenta_nombre'];
            $haberProgramadoId = $haberEspRow ? $haberEspRow['id_programado'] : null;

            $reglas[] = [
                'id_asiento_tipo'   => 0,
                'tipo_asiento'      => 'retenciones_venta',
                'concepto'          => $c['concepto_ret'],
                'detalle'           => $c['codigo_usado'] . ' - ' . $c['impuesto_ret'],
                'codigo'            => $c['codigo_usado'],
                'tipo_cuenta'       => 'activo',
                'debe_haber'        => 'debe',

                // Datos del Debe
                'id_programado'     => $debeRow['id_programado'],
                'id_cuenta'         => $debeRow['id_cuenta'],
                'cuenta_codigo'     => $debeRow['cuenta_codigo'],
                'cuenta_nombre'     => $debeRow['cuenta_nombre'],
                'id_referencia'     => $c['id'],
                'tipo_referencia'   => 'retenciones_venta_debe',

                // Datos del Haber
                'haber_id_programado'=> $haberProgramadoId,
                'haber_id_cuenta'   => $haberId,
                'haber_cuenta_codigo'=> $haberCodigo,
                'haber_cuenta_nombre'=> $haberNombre,
                'haber_is_custom'    => $haberEspRow ? true : false
            ];
        }

        return $reglas;
    }

    /**
     * Retenciones SRI que la empresa EFECTÚA en compras (al proveedor), cruzando con su homóloga
     * programada. En compras la retención es un PASIVO, por lo que se invierten los lados respecto a
     * ventas: Debe = Cuentas por Pagar (contraparte, por defecto PORPAGARFACTURACOMPRA) y Haber =
     * Retención por pagar (cuenta específica por concepto de retención).
     */
    public function getReglasRetencionesCompra(int $idEmpresa): array
    {
        // 1. Cuenta por pagar por defecto (PORPAGARFACTURACOMPRA) → contraparte del lado Debe.
        $sqlDebe = "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                    FROM asientos_programados ap
                    INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                    WHERE ap.id_empresa = :id_empresa
                      AND at.codigo = 'PORPAGARFACTURACOMPRA'
                      AND ap.eliminado = false
                      AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = 'adquisiciones_compras' OR ap.tipo_referencia = at.tipo_asiento)
                    LIMIT 1";
        $stDebe = $this->db->prepare($sqlDebe);
        $stDebe->execute([':id_empresa' => $idEmpresa]);
        $debeDefecto = $stDebe->fetch(PDO::FETCH_ASSOC) ?: [
            'id_cuenta' => null,
            'cuenta_codigo' => '',
            'cuenta_nombre' => 'No Configurada'
        ];

        // 2. Códigos de retención usados en compras de esta empresa (o ya configurados)
        $conceptos = $this->getConceptosRetencion($idEmpresa, 'compra');
        $docsSinCatalogo = $this->getDocumentosSinCatalogo($idEmpresa, 'compra', $conceptos);

        $reglas = [];
        foreach ($conceptos as $c) {
            if (empty($c['id'])) {
                $reglas[] = $this->reglaRetencionSinCatalogo($c, 'retenciones_compra', $docsSinCatalogo[$c['codigo_usado']] ?? []);
                continue;
            }
            // HABER: cuenta de la retención por pagar (específica por concepto).
            $sqlHaberEsp = "SELECT ap.id AS id_programado, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                            FROM asientos_programados ap
                            INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                            WHERE ap.id_empresa = :id_empresa
                              AND ap.tipo_referencia = 'retenciones_compra_haber'
                              AND ap.id_referencia = :id_referencia
                              AND ap.eliminado = false
                            ORDER BY ap.id DESC LIMIT 1";
            $stHaberEsp = $this->db->prepare($sqlHaberEsp);
            $stHaberEsp->execute([':id_empresa' => $idEmpresa, ':id_referencia' => $c['id']]);
            $haberRow = $stHaberEsp->fetch(PDO::FETCH_ASSOC) ?: [
                'id_programado' => null,
                'id_cuenta' => null,
                'cuenta_codigo' => '',
                'cuenta_nombre' => ''
            ];

            // DEBE: cuenta por pagar. Específica por concepto si existe; si no, el default.
            $sqlDebeEsp = "SELECT ap.id AS id_programado, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                           FROM asientos_programados ap
                           INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                           WHERE ap.id_empresa = :id_empresa
                             AND ap.tipo_referencia = 'retenciones_compra_debe'
                             AND ap.id_referencia = :id_referencia
                             AND ap.eliminado = false
                           LIMIT 1";
            $stDebeEsp = $this->db->prepare($sqlDebeEsp);
            $stDebeEsp->execute([':id_empresa' => $idEmpresa, ':id_referencia' => $c['id']]);
            $debeEspRow = $stDebeEsp->fetch(PDO::FETCH_ASSOC);

            $debeId     = $debeEspRow ? $debeEspRow['id_cuenta'] : $debeDefecto['id_cuenta'];
            $debeCodigo = $debeEspRow ? $debeEspRow['cuenta_codigo'] : $debeDefecto['cuenta_codigo'];
            $debeNombre = $debeEspRow ? $debeEspRow['cuenta_nombre'] : $debeDefecto['cuenta_nombre'];
            $debeProgId = $debeEspRow ? $debeEspRow['id_programado'] : null;

            $reglas[] = [
                'id_asiento_tipo'   => 0,
                'tipo_asiento'      => 'retenciones_compra',
                'concepto'          => $c['concepto_ret'],
                'detalle'           => $c['codigo_usado'] . ' - ' . $c['impuesto_ret'],
                'codigo'            => $c['codigo_usado'],
                'tipo_cuenta'       => 'pasivo',
                'debe_haber'        => 'haber',

                // Datos del Debe (Cuentas por Pagar proveedores)
                'id_programado'     => $debeProgId,
                'id_cuenta'         => $debeId,
                'cuenta_codigo'     => $debeCodigo,
                'cuenta_nombre'     => $debeNombre,
                'id_referencia'     => $c['id'],
                'tipo_referencia'   => 'retenciones_compra_debe',
                'debe_is_custom'    => $debeEspRow ? true : false,

                // Datos del Haber (Retención por pagar)
                'haber_id_programado'=> $haberRow['id_programado'],
                'haber_id_cuenta'   => $haberRow['id_cuenta'],
                'haber_cuenta_codigo'=> $haberRow['cuenta_codigo'],
                'haber_cuenta_nombre'=> $haberRow['cuenta_nombre'],
                'haber_is_custom'    => $haberRow['id_programado'] ? true : false
            ];
        }

        return $reglas;
    }

    /**
     * Códigos de retención a configurar para ventas o compras: los usados en las retenciones
     * de la empresa (cualquier ambiente: la cuenta contable no depende del ambiente y el
     * generador de asientos tampoco lo filtra) más los que ya tienen cuenta configurada.
     *
     * Cada código se cruza con retenciones_sri igual que el generador de asientos
     * (AsientoBuilderService, id más reciente): en RENTA el documento trae el código ATS
     * (cod_anexo_ret), que no siempre coincide con codigo_ret (p. ej. 323 vs. 323I); en IVA
     * trae codigo_ret (1, 2, 3…), cuyo código ATS es otro (725, 730…). Si el código no existe
     * en el catálogo se devuelve con id = null, para mostrarlo como aviso en lugar de
     * ocultarlo en silencio.
     */
    private function getConceptosRetencion(int $idEmpresa, string $lado): array
    {
        $esVenta  = $lado === 'venta';
        $tablaDet = $esVenta ? 'retencion_venta_detalle' : 'retencion_compra_detalle';
        $tablaCab = $esVenta ? 'retencion_venta_cabecera' : 'retencion_compra_cabecera';
        $tiposRef = $esVenta
            ? "'retenciones_venta', 'retenciones_venta_debe', 'retenciones_venta_haber'"
            : "'retenciones_compra_debe', 'retenciones_compra_haber'";

        $colIdSri = $esVenta ? null : 'd.id_retencion_sri';   // solo compras guarda el id del catálogo
        $cruce    = CruceRetencionSri::joinLateral('d.codigo_retencion', $colIdSri, 'rsl');
        $visible  = CruceRetencionSri::codigoVisible('rs');

        // Cada línea se resuelve a su fila del catálogo igual que el generador de asientos;
        // se agrupa por esa fila (o por el código, si no está en el catálogo).
        $sql = "SELECT COALESCE({$visible}, u.codigo_sin_catalogo) AS codigo_usado,
                       rs.id, rs.codigo_ret, rs.concepto_ret, rs.impuesto_ret
                FROM (
                    SELECT DISTINCT rsl.id AS id_sri,
                           CASE WHEN rsl.id IS NULL THEN d.codigo_retencion END AS codigo_sin_catalogo
                    FROM {$tablaDet} d
                    INNER JOIN {$tablaCab} c ON c.id = d.id_retencion
                    {$cruce}
                    WHERE c.id_empresa = :id_empresa
                      AND c.eliminado = false
                      AND COALESCE(TRIM(d.codigo_retencion), '') <> ''
                      -- Un código sin catálogo solo se avisa si retuvo valor: sin valor no hay
                      -- línea de asiento que se pierda ni cuenta que configurar.
                      AND (rsl.id IS NOT NULL OR COALESCE(d.valor_retenido, 0) > 0)
                    UNION
                    SELECT ap.id_referencia, NULL
                    FROM asientos_programados ap
                    WHERE ap.id_empresa = :id_empresa_conf
                      AND ap.eliminado = false
                      AND ap.tipo_referencia IN ({$tiposRef})
                ) u
                LEFT JOIN retenciones_sri rs ON rs.id = u.id_sri
                WHERE rs.id IS NOT NULL OR u.codigo_sin_catalogo IS NOT NULL
                ORDER BY (rs.id IS NULL) DESC, rs.impuesto_ret DESC, 1 ASC, rs.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_empresa_conf' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fila de aviso para un código de retención usado en documentos que no existe en el
     * catálogo retenciones_sri: no se puede configurar (la cuenta se guarda por id del catálogo)
     * y el asiento de esas retenciones saldrá sin esa línea.
     */
    private function reglaRetencionSinCatalogo(array $c, string $tipoAsiento, array $documentos = []): array
    {
        return [
            'id_asiento_tipo' => 0,
            'tipo_asiento'    => $tipoAsiento,
            'concepto'        => 'Código ' . $c['codigo_usado'],
            'detalle'         => 'No existe en el catálogo de retenciones SRI',
            'codigo'          => $c['codigo_usado'],
            'sin_catalogo'    => true,
            'id_referencia'   => null,
            'documentos'      => $documentos ?: ['total' => 0, 'items' => []],
        ];
    }

    /** Tope de documentos listados por código en el aviso (el resto se informa como total). */
    private const MAX_DOCS_SIN_CATALOGO = 50;

    /**
     * Retenciones (de venta o compra) con líneas cuyo código no existe en el catálogo
     * retenciones_sri, agrupadas por ese código: el aviso de Configuración Contable las lista
     * para que el usuario sepa qué documento corregir. Mismo cruce que getConceptosRetencion()
     * (y que el generador de asientos). Solo consulta si hay algún código sin catálogo.
     *
     * @return array<string, array{total:int, items:array<int, array<string, mixed>>}>
     */
    private function getDocumentosSinCatalogo(int $idEmpresa, string $lado, array $conceptos): array
    {
        $hay = false;
        foreach ($conceptos as $c) {
            if (empty($c['id'])) { $hay = true; break; }
        }
        if (!$hay) {
            return [];
        }

        $esVenta  = $lado === 'venta';
        $tablaDet = $esVenta ? 'retencion_venta_detalle' : 'retencion_compra_detalle';
        $tablaCab = $esVenta ? 'retencion_venta_cabecera' : 'retencion_compra_cabecera';
        $joinTer  = $esVenta
            ? 'LEFT JOIN clientes t ON t.id = c.id_cliente'
            : 'LEFT JOIN proveedores t ON t.id = c.id_proveedor';
        $colTer   = $esVenta ? 't.nombre' : 't.razon_social';
        $colIdSri = $esVenta ? null : 'd.id_retencion_sri';
        $cruce    = CruceRetencionSri::joinLateral('d.codigo_retencion', $colIdSri, 'rsl');

        $sql = "SELECT d.codigo_retencion AS codigo, c.id, c.fecha_emision, c.tipo_ambiente,
                       c.establecimiento || '-' || c.punto_emision || '-' || COALESCE(c.secuencial, '') AS numero,
                       {$colTer} AS tercero
                FROM {$tablaDet} d
                INNER JOIN {$tablaCab} c ON c.id = d.id_retencion
                {$cruce}
                {$joinTer}
                WHERE c.id_empresa = :id_empresa
                  AND c.eliminado = false
                  AND COALESCE(TRIM(d.codigo_retencion), '') <> ''
                  AND rsl.id IS NULL
                  AND COALESCE(d.valor_retenido, 0) > 0
                GROUP BY d.codigo_retencion, c.id, c.fecha_emision, c.tipo_ambiente,
                         c.establecimiento, c.punto_emision, c.secuencial, {$colTer}
                ORDER BY d.codigo_retencion, c.fecha_emision DESC, c.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $cod = $r['codigo'];
            $mapa[$cod] ??= ['total' => 0, 'items' => []];
            $mapa[$cod]['total']++;
            if (count($mapa[$cod]['items']) < self::MAX_DOCS_SIN_CATALOGO) {
                $mapa[$cod]['items'][] = [
                    'id'      => (int) $r['id'],
                    'numero'  => $r['numero'],
                    'fecha'   => $r['fecha_emision'] ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                    'tercero' => $r['tercero'] ?? '',
                    'pruebas' => (string) $r['tipo_ambiente'] === '1',
                ];
            }
        }
        return $mapa;
    }

    /**
     * Condición (sobre alias `ap`) de las reglas propias de un proveedor en el asiento de compras
     * (adquisiciones_compras, que cubre compras y liquidaciones): sus conceptos y sus overrides de IVA.
     * Requiere `LEFT JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo`.
     */
    private const COND_REGLA_COMPRA_PROVEEDOR = "ap.tipo_referencia = 'proveedor' AND ap.eliminado = false
          AND (at.tipo_asiento = 'adquisiciones_compras' OR (ap.id_asiento_tipo = 0 AND ap.direccion_iva = 'compra'))";

    /**
     * Sugerencias de «Reglas por Proveedores»: para cada proveedor SIN cuentas propias en el asiento
     * de compras, el proveedor YA configurado con el que comparte más ítems comprados (compras y
     * liquidaciones), para copiarle sus cuentas. Ej.: varias gasolineras que venden «EXTRA».
     *
     * El ítem se compara por la descripción normalizada (minúsculas, sin tildes ni signos), porque
     * en compras llega como texto libre del proveedor (compras_detalle.id_producto suele ser NULL).
     * Es solo una sugerencia: el usuario decide si la aplica.
     *
     * @return array<int, array<string, mixed>> id_destino, destino, id_origen, origen, comunes,
     *         items_destino, ejemplos (json), alternativas, cuentas_origen
     */
    public function getSugerenciasReglasProveedor(int $idEmpresa, ?int $anio = null): array
    {
        $norm = "NULLIF(TRIM(regexp_replace(lower(translate(COALESCE(d.descripcion, ''), 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun')), '[^a-z0-9]+', ' ', 'g')), '')";
        $params = [':e' => $idEmpresa];
        $filtroCompra = $filtroLiq = '';
        if ($anio !== null) {
            $filtroCompra = ' AND EXTRACT(YEAR FROM c.fecha_emision) = :anio';
            $filtroLiq    = ' AND EXTRACT(YEAR FROM l.fecha_emision) = :anio2';
            $params[':anio']  = $anio;
            $params[':anio2'] = $anio;
        }
        $cond = self::COND_REGLA_COMPRA_PROVEEDOR;

        $sql = "WITH items AS (
                    SELECT c.id_proveedor, {$norm} AS item
                    FROM compras_detalle d
                    INNER JOIN compras_cabecera c ON c.id = d.id_compra
                    WHERE c.id_empresa = :e AND c.eliminado = false{$filtroCompra}
                    UNION
                    SELECT l.id_proveedor, {$norm}
                    FROM liquidaciones_detalle d
                    INNER JOIN liquidaciones_cabecera l ON l.id = d.id_cabecera
                    WHERE l.id_empresa = :e2 AND l.eliminado = false{$filtroLiq}
                ),
                configurados AS (
                    SELECT ap.id_referencia AS id_proveedor, COUNT(*) AS cuentas
                    FROM asientos_programados ap
                    LEFT JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                    WHERE ap.id_empresa = :e3 AND {$cond}
                    GROUP BY ap.id_referencia
                ),
                comunes AS (
                    SELECT dst.id_proveedor AS id_destino, src.id_proveedor AS id_origen,
                           COUNT(*) AS comunes,
                           (array_agg(dst.item ORDER BY dst.item))[1:5] AS ejemplos
                    FROM items dst
                    INNER JOIN items src ON src.item = dst.item AND src.id_proveedor <> dst.id_proveedor
                    INNER JOIN configurados cf ON cf.id_proveedor = src.id_proveedor
                    WHERE dst.item IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM configurados x WHERE x.id_proveedor = dst.id_proveedor)
                    GROUP BY 1, 2
                ),
                ranking AS (
                    SELECT cm.*,
                           ROW_NUMBER() OVER (PARTITION BY cm.id_destino ORDER BY cm.comunes DESC, cm.id_origen) AS rn,
                           COUNT(*) OVER (PARTITION BY cm.id_destino) - 1 AS alternativas
                    FROM comunes cm
                )
                SELECT r.id_destino, pd.razon_social AS destino, pd.identificacion AS destino_identificacion,
                       r.id_origen, po.razon_social AS origen,
                       r.comunes, r.alternativas, cf.cuentas AS cuentas_origen,
                       (SELECT COUNT(*) FROM items i WHERE i.id_proveedor = r.id_destino AND i.item IS NOT NULL) AS items_destino,
                       array_to_json(r.ejemplos) AS ejemplos
                FROM ranking r
                INNER JOIN proveedores pd ON pd.id = r.id_destino AND pd.id_empresa = :e4 AND pd.eliminado = false
                INNER JOIN proveedores po ON po.id = r.id_origen AND po.id_empresa = :e5 AND po.eliminado = false
                INNER JOIN configurados cf ON cf.id_proveedor = r.id_origen
                WHERE r.rn = 1
                ORDER BY r.comunes DESC, pd.razon_social ASC
                LIMIT 300";
        $params += [':e2' => $idEmpresa, ':e3' => $idEmpresa, ':e4' => $idEmpresa, ':e5' => $idEmpresa];
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Documentos (cabecera, detalle, FK del detalle a la cabecera) de los que sale cada tipo de asiento. */
    private const DOCS_POR_TIPO_ASIENTO = [
        'adquisiciones_compras' => [
            ['compras_cabecera', 'compras_detalle', 'id_compra'],
            ['liquidaciones_cabecera', 'liquidaciones_detalle', 'id_cabecera'],
        ],
        'ventas_factura' => [['ventas_cabecera', 'ventas_detalle', 'id_venta']],
        'recibos_venta'  => [['recibos_venta_cabecera', 'recibos_venta_detalle', 'id_recibo']],
    ];

    /** Dimensiones que admite la tabla de reglas (la de producto en compras es el ítem por texto). */
    public const DIMS_TABLA_REGLAS = ['proveedor', 'cliente', 'producto', 'categoria', 'marca', 'tipo_produccion'];

    /**
     * Tabla de reglas por entidad (Proveedores, Clientes, Productos/Ítems, Categorías, Marcas):
     * las entidades con movimiento en los documentos del tipo de asiento (opcionalmente de un año;
     * categorías y marcas sin año: todas), paginado y con buscador. Primero las que aún no tienen
     * cuenta propia en ninguno de los conceptos principales ($principales, códigos de asientos_tipo:
     * las columnas de la tabla), cada grupo de la A a la Z.
     *
     * En compras la dimensión «producto» es el ÍTEM de compra (texto libre, regla 'item_compra'):
     * la clave es la descripción, no un id.
     *
     * @param string[] $principales
     * @return array{rows: array<int, array{clave:string, nombre:string, identificacion:?string, tiene_principal:bool}>, total: int}
     */
    public function listarEntidadesRegla(
        int $idEmpresa,
        string $dim,
        string $tipoAsiento,
        array $principales,
        string $buscar,
        ?int $anio,
        int $page,
        int $perPage
    ): array {
        $docs = self::DOCS_POR_TIPO_ASIENTO[$tipoAsiento] ?? null;
        if ($docs === null || !in_array($dim, self::DIMS_TABLA_REGLAS, true)) {
            throw new \InvalidArgumentException('Regla no disponible para este tipo de asiento.');
        }
        $esCompra = $tipoAsiento === 'adquisiciones_compras';
        if (($dim === 'proveedor' && !$esCompra) || (in_array($dim, ['cliente', 'tipo_produccion'], true) && $esCompra)) {
            throw new \InvalidArgumentException('Regla no disponible para este tipo de asiento.');
        }
        $esItem = $dim === 'producto' && $esCompra;

        $params = [];
        $n = 0;
        $p = function ($valor) use (&$params, &$n): string {
            $k = ':p' . (++$n);
            $params[$k] = $valor;
            return $k;
        };

        // Movimiento en los documentos del tipo de asiento (cabecera c, detalle d).
        $existeMov = function (string $condicion, bool $conDetalle) use ($docs, $anio, $idEmpresa, $p): string {
            $partes = [];
            foreach ($docs as [$cab, $det, $fk]) {
                $join = $conDetalle ? " INNER JOIN {$det} d ON d.{$fk} = c.id" : '';
                $filtroAnio = $anio !== null ? ' AND EXTRACT(YEAR FROM c.fecha_emision) = ' . $p($anio) : '';
                $partes[] = "EXISTS (SELECT 1 FROM {$cab} c{$join}
                                     WHERE c.id_empresa = {$p($idEmpresa)} AND c.eliminado = false
                                       AND {$condicion}{$filtroAnio})";
            }
            return '(' . implode(' OR ', $partes) . ')';
        };

        if ($dim === 'tipo_produccion') {
            // Sin catálogo: dos valores fijos; id_referencia 1 = Bien, 2 = Servicio (mismo mapeo que
            // cargarReglasDimensionAjax y AsientoBuilderService::repartirVentasCascada()). Siempre los dos.
            $base = "SELECT v.clave, v.nombre, NULL::text AS identificacion
                     FROM (VALUES ('1', 'Bien'), ('2', 'Servicio')) AS v(clave, nombre)";
        } elseif ($esItem) {
            $uniones = [];
            foreach ($docs as [$cab, $det, $fk]) {
                $filtroAnio = $anio !== null ? ' AND EXTRACT(YEAR FROM c.fecha_emision) = ' . $p($anio) : '';
                $uniones[] = "SELECT TRIM(d.descripcion) AS clave
                              FROM {$det} d INNER JOIN {$cab} c ON c.id = d.{$fk}
                              WHERE c.id_empresa = {$p($idEmpresa)} AND c.eliminado = false
                                AND COALESCE(TRIM(d.descripcion), '') <> ''{$filtroAnio}";
            }
            $base = "SELECT u.clave, u.clave AS nombre, NULL::text AS identificacion
                     FROM (" . implode(' UNION ', $uniones) . ") u";
        } else {
            [$tabla, $colNombre, $colIdent] = match ($dim) {
                'proveedor' => ['proveedores', 'e.razon_social', 'e.identificacion'],
                'cliente'   => ['clientes', 'e.nombre', 'e.identificacion'],
                'producto'  => ['productos', 'e.nombre', 'e.codigo'],
                'categoria' => ['categorias', 'e.nombre', 'NULL::text'],
                'marca'     => ['marcas', 'e.nombre', 'NULL::text'],
            };
            $where = "e.id_empresa = {$p($idEmpresa)} AND e.eliminado = false";
            if ($dim === 'proveedor' || $dim === 'cliente') {
                $where .= ' AND ' . $existeMov("c.id_{$dim} = e.id", false);
            } elseif ($dim === 'producto') {
                $where .= ' AND ' . $existeMov('d.id_producto = e.id', true);
            } elseif ($anio !== null) {
                // Categorías y marcas: sin año, todas (se pueden configurar por adelantado).
                $col = $dim === 'categoria' ? 'id_categoria' : 'id_marca';
                $where .= ' AND ' . $existeMov("EXISTS (SELECT 1 FROM productos pr WHERE pr.id = d.id_producto AND pr.{$col} = e.id)", true);
            }
            $base = "SELECT e.id::text AS clave, {$colNombre} AS nombre, {$colIdent}::text AS identificacion
                     FROM {$tabla} e WHERE {$where}";
        }

        $filtro = '';
        if (trim($buscar) !== '') {
            $cond = \App\Helpers\FiltrosBusqueda::condicionTexto(['x.nombre', 'x.identificacion'], $buscar, $params, 'bq');
            if ($cond !== '') {
                $filtro = " WHERE {$cond}";
            }
        }

        $st = $this->db->prepare("SELECT COUNT(*) FROM ({$base}) x{$filtro}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        // ¿Ya tiene cuenta propia en algún concepto principal (columna de la tabla)?
        $codigos = array_values(array_filter($principales, fn($c) => is_string($c) && preg_match('/^[A-Z0-9_]{1,60}$/', $c)));
        $condCodigo = $codigos
            ? 'AND at.codigo IN (' . implode(', ', array_map($p, $codigos)) . ')'
            : '';
        $condRef = $esItem
            ? "ap.tipo_referencia = 'item_compra' AND TRIM(ap.referencia_texto) = x.clave"
            : "ap.tipo_referencia = {$p($dim)} AND ap.id_referencia::text = x.clave";
        $tienePrincipal = "EXISTS (SELECT 1 FROM asientos_programados ap
                                   INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                                   WHERE ap.id_empresa = {$p($idEmpresa)} AND ap.eliminado = false
                                     AND at.tipo_asiento = {$p($tipoAsiento)} {$condCodigo}
                                     AND {$condRef})";

        $perPage = max(1, $perPage);
        $offset  = (max(1, $page) - 1) * $perPage;
        $sql = "SELECT y.* FROM (
                    SELECT x.clave, x.nombre, x.identificacion, {$tienePrincipal} AS tiene_principal
                    FROM ({$base}) x{$filtro}
                ) y
                ORDER BY y.tiene_principal ASC, UPPER(y.nombre) ASC, y.clave ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Diagnóstico de las líneas que no resolvieron cuenta en el reparto por línea (ventas, recibos,
     * NC): por cada producto, su categoría/marca y si el tipo de asiento de $idAsientoTipo se
     * contabiliza por categoría/marca (hay alguna regla de esa dimensión con cuenta). Sirve para que
     * el mensaje diga lo que realmente falta (p. ej. "no tiene categoría") y no solo la cuenta.
     *
     * @param int[] $idsProductos
     * @return array{usa_categoria:bool, usa_marca:bool, productos: array<int, array{nombre:string, categoria:?string, marca:?string}>}
     */
    public function getDiagnosticoProductosSinCuenta(int $idEmpresa, int $idAsientoTipo, array $idsProductos): array
    {
        $out = ['usa_categoria' => false, 'usa_marca' => false, 'productos' => []];
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsProductos))));
        try {
            $st = $this->db->prepare(
                "SELECT ap.tipo_referencia
                   FROM asientos_programados ap
                  WHERE ap.id_empresa = :e AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
                    AND ap.tipo_referencia IN ('categoria', 'marca')
                    AND ap.id_asiento_tipo IN (SELECT id FROM asientos_tipo
                                                WHERE tipo_asiento = (SELECT tipo_asiento FROM asientos_tipo WHERE id = :t))
                  GROUP BY ap.tipo_referencia"
            );
            $st->execute([':e' => $idEmpresa, ':t' => $idAsientoTipo]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $dim) {
                $out['usa_' . $dim] = true;
            }
            if ($ids) {
                $in = implode(',', array_map(fn($i) => ':p' . $i, array_keys($ids)));
                $params = [':e' => $idEmpresa];
                foreach ($ids as $i => $id) {
                    $params[':p' . $i] = $id;
                }
                $st = $this->db->prepare(
                    "SELECT p.id, p.nombre, c.nombre AS categoria, m.nombre AS marca
                       FROM productos p
                       LEFT JOIN categorias c ON c.id = p.id_categoria AND c.eliminado = false AND c.id_empresa = p.id_empresa
                       LEFT JOIN marcas m     ON m.id = p.id_marca     AND m.eliminado = false AND m.id_empresa = p.id_empresa
                      WHERE p.id_empresa = :e AND p.id IN ({$in})
                      ORDER BY p.nombre"
                );
                $st->execute($params);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $out['productos'][(int) $r['id']] = [
                        'nombre'    => (string) $r['nombre'],
                        'categoria' => $r['categoria'] !== null ? (string) $r['categoria'] : null,
                        'marca'     => $r['marca'] !== null ? (string) $r['marca'] : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('getDiagnosticoProductosSinCuenta: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Si la empresa contabiliza un tipo de asiento POR CATEGORÍA (o POR MARCA) — tiene al menos una
     * regla de categoría/marca con cuenta en ese tipo, incluido el IVA por tarifa de ventas/compras —,
     * todos sus productos y servicios deben tener asignada una categoría (o marca) válida. Devuelve,
     * por dimensión usada, los productos que no la tienen. Se excluyen los productos con regla
     * propia en ese tipo de asiento (no dependen de la categoría/marca).
     *
     * @return array{categoria: ?array{total:int, items:array}, marca: ?array{total:int, items:array}}
     *         null = la empresa no contabiliza ese tipo por esa dimensión.
     */
    public function getProductosSinClasificacion(int $idEmpresa, string $tipoAsiento, int $limite = 100): array
    {
        $direccionIva = ['ventas_factura' => 'venta', 'recibos_venta' => 'recibo', 'adquisiciones_compras' => 'compra'][$tipoAsiento] ?? null;
        $condRegla = "ap.id_empresa = :e AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
                      AND (ap.id_asiento_tipo IN (SELECT id FROM asientos_tipo WHERE tipo_asiento = :ta)"
                   . ($direccionIva ? " OR (ap.id_asiento_tipo = 0 AND ap.direccion_iva = :dir)" : '') . ")";
        $params = [':e' => $idEmpresa, ':ta' => $tipoAsiento];
        if ($direccionIva) {
            $params[':dir'] = $direccionIva;
        }
        $dims = [
            'categoria' => ['tabla' => 'categorias', 'col' => 'id_categoria'],
            'marca'     => ['tabla' => 'marcas',     'col' => 'id_marca'],
        ];
        $limite = max(1, $limite);
        $out = ['categoria' => null, 'marca' => null];
        try {
            foreach ($dims as $dim => $d) {
                $st = $this->db->prepare("SELECT EXISTS (SELECT 1 FROM asientos_programados ap
                                                          WHERE {$condRegla} AND ap.tipo_referencia = '{$dim}')");
                $st->execute($params);
                if (!$st->fetchColumn()) {
                    continue;
                }
                $st = $this->db->prepare(
                    "SELECT p.codigo, p.nombre, p.tipo_produccion, COUNT(*) OVER () AS total
                       FROM productos p
                       LEFT JOIN {$d['tabla']} x ON x.id = p.{$d['col']} AND x.eliminado = false AND x.id_empresa = p.id_empresa
                      WHERE p.id_empresa = :e AND p.eliminado = false AND x.id IS NULL
                        AND NOT EXISTS (SELECT 1 FROM asientos_programados ap
                                         WHERE {$condRegla} AND ap.tipo_referencia = 'producto' AND ap.id_referencia = p.id)
                      ORDER BY p.nombre
                      LIMIT {$limite}"
                );
                $st->execute($params);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                $out[$dim] = [
                    'total' => $rows ? (int) $rows[0]['total'] : 0,
                    'items' => array_map(fn($r) => [
                        'codigo'   => (string) $r['codigo'],
                        'nombre'   => (string) $r['nombre'],
                        'servicio' => (string) $r['tipo_produccion'] === '02',
                    ], $rows),
                ];
            }
        } catch (\Throwable $e) {
            error_log('getProductosSinClasificacion: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Productos/servicios activos que, en una factura de venta, NO encontrarían cuenta en ningún nivel
     * de la cascada (AsientoBuilderService::repartirVentasCascada y el IVA por tarifa): ni por el
     * propio producto, ni su categoría, ni su marca, ni su tipo de producción (no aplica al IVA), ni
     * la General. Sus facturas no generan asiento (se bloquea). Caso típico: la empresa configura
     * todo por categoría (o por marca) y un producto quedó SIN categoría / SIN marca (GOLIFE,
     * «SERVICIO GO WOMAN»).
     *
     * Solo se revisa un concepto si NO tiene cuenta General: con General, todo producto resuelve.
     * Las reglas por Cliente no se consideran (dependen de quién compre, no del producto).
     *
     * @return array<int, array{codigo:string, nombre:string, sin_categoria:bool, sin_marca:bool, faltan:string}>
     */
    public function getProductosSinCuentaVentas(int $idEmpresa, int $limite = 100): array
    {
        $tipos = [];
        foreach ($this->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura') as $r) {
            $tipos[(string) ($r['codigo'] ?? '')] = [
                'id'      => (int) ($r['id_asiento_tipo'] ?? 0),
                'general' => (int) ($r['id_cuenta'] ?? 0) > 0,
                'nombre'  => (string) ($r['concepto'] ?? ''),
            ];
        }
        $checks = [];
        $params = [':e' => $idEmpresa];
        foreach (['PORCOBRARFACTURAVENTA' => 'Cuenta por cobrar', 'SUBTOTALFACTURAVENTA' => 'Subtotal (venta)'] as $cod => $etiqueta) {
            $t = $tipos[$cod] ?? null;
            if ($t === null || $t['id'] <= 0 || $t['general']) {
                continue; // con General, cualquier producto resuelve
            }
            $k = ':at' . count($checks);
            $params[$k] = $t['id'];
            $checks[] = "CASE WHEN NOT EXISTS (
                            SELECT 1 FROM asientos_programados ap
                             WHERE ap.id_empresa = p.id_empresa AND ap.eliminado = false
                               AND ap.id_cuenta IS NOT NULL AND ap.id_asiento_tipo = {$k}
                               AND (   (ap.tipo_referencia = 'producto'  AND ap.id_referencia = p.id)
                                    OR (ap.tipo_referencia = 'categoria' AND ap.id_referencia = c.id)
                                    OR (ap.tipo_referencia = 'marca'     AND ap.id_referencia = m.id)
                                    OR (ap.tipo_referencia = 'tipo_produccion'
                                        AND ap.id_referencia = (CASE p.tipo_produccion WHEN '02' THEN 2 WHEN '01' THEN 1 END))))
                         THEN '{$etiqueta}' END";
        }
        // IVA de la tarifa del producto (solo tarifas con porcentaje > 0): cascada propia producto →
        // categoría → marca → General (iva_ventas_factura); el tipo de producción NO participa.
        $checks[] = "CASE WHEN COALESCE(t.porcentaje_iva, 0) > 0 AND NOT EXISTS (
                        SELECT 1 FROM asientos_programados ap
                         WHERE ap.id_empresa = p.id_empresa AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
                           AND (   (ap.id_asiento_tipo = 0 AND ap.direccion_iva = 'venta'
                                    AND ap.codigo_tarifa_iva = p.tarifa_iva::text
                                    AND (   (ap.tipo_referencia = 'producto'  AND ap.id_referencia = p.id)
                                         OR (ap.tipo_referencia = 'categoria' AND ap.id_referencia = c.id)
                                         OR (ap.tipo_referencia = 'marca'     AND ap.id_referencia = m.id)))
                                OR (ap.tipo_referencia = 'iva_ventas_factura' AND ap.id_referencia = p.tarifa_iva)))
                     THEN 'IVA ' || t.tarifa END";

        $limite = max(1, $limite);
        $sql = "SELECT x.codigo, x.nombre, x.sin_categoria, x.sin_marca, array_to_string(x.faltan, ', ') AS faltan
                  FROM (
                    SELECT p.codigo, p.nombre, (c.id IS NULL) AS sin_categoria, (m.id IS NULL) AS sin_marca,
                           array_remove(ARRAY[" . implode(', ', $checks) . "], NULL) AS faltan
                      FROM productos p
                      LEFT JOIN categorias c ON c.id = p.id_categoria AND c.eliminado = false AND c.id_empresa = p.id_empresa
                      LEFT JOIN marcas m ON m.id = p.id_marca AND m.eliminado = false AND m.id_empresa = p.id_empresa
                      LEFT JOIN tarifa_iva t ON t.codigo = p.tarifa_iva::text
                     WHERE p.id_empresa = :e AND p.eliminado = false
                  ) x
                 WHERE cardinality(x.faltan) > 0
                 ORDER BY x.sin_categoria DESC, x.sin_marca DESC, x.nombre
                 LIMIT {$limite}";
        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return array_map(fn($r) => [
                'codigo'        => (string) $r['codigo'],
                'nombre'        => (string) $r['nombre'],
                'sin_categoria' => (bool) $r['sin_categoria'],
                'sin_marca'     => (bool) $r['sin_marca'],
                'faltan'        => (string) $r['faltan'],
            ], $st->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            error_log('getProductosSinCuentaVentas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Reglas propias del proveedor en el asiento de compras (conceptos + overrides de IVA de compra),
     * con los campos necesarios para replicarlas en otro proveedor.
     */
    public function getReglasCompraProveedor(int $idEmpresa, int $idProveedor): array
    {
        $cond = self::COND_REGLA_COMPRA_PROVEEDOR;
        $sql = "SELECT ap.id, ap.id_asiento_tipo, ap.id_cuenta, ap.codigo_tarifa_iva, ap.direccion_iva
                FROM asientos_programados ap
                LEFT JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                WHERE ap.id_empresa = :e AND ap.id_referencia = :id AND {$cond}
                ORDER BY ap.id";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':id' => $idProveedor]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Candado transaccional sobre las reglas de un proveedor (se libera al COMMIT/ROLLBACK):
     * serializa «¿ya tiene reglas? → copiar» para que dos copias simultáneas no las dupliquen.
     */
    public function lockReglasProveedor(int $idEmpresa, int $idProveedor): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('reglas_proveedor:' || :e || ':' || :id))");
        $st->execute([':e' => $idEmpresa, ':id' => $idProveedor]);
    }

    /**
     * Reglas vivas de un tipo de asiento, para copiarlas a otro (Configuración Contable → «Copiar
     * configuración de Facturas de Venta» en Recibos): conceptos de asientos_tipo (General y por
     * dimensión), IVA General por tarifa ($tipoRefIva) e IVA por dimensión ($direccionIva).
     * La cuenta debe existir y estar viva en el plan de la empresa (si no, cuenta_codigo = NULL).
     */
    public function getReglasParaCopiar(int $idEmpresa, string $tipoAsiento, string $tipoRefIva, string $direccionIva): array
    {
        $sql = "SELECT ap.id, ap.id_asiento_tipo, ap.id_cuenta, ap.id_referencia, ap.tipo_referencia,
                       ap.codigo_tarifa_iva,
                       at.codigo AS concepto_codigo, at.referencia AS concepto,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                       ti.tarifa AS tarifa_iva
                FROM asientos_programados ap
                LEFT JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo AND at.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta AND pc.id_empresa = ap.id_empresa AND pc.eliminado = false
                -- LATERAL: solo la etiqueta de la tarifa, sin multiplicar filas si el catálogo repite código.
                LEFT JOIN LATERAL (
                    SELECT t.tarifa FROM tarifa_iva t
                     WHERE ltrim(t.codigo, '0') = ltrim(CASE WHEN ap.tipo_referencia = :tref_iva_t
                                                             THEN ap.id_referencia::text
                                                             ELSE ap.codigo_tarifa_iva END, '0')
                     LIMIT 1
                ) ti ON true
                WHERE ap.id_empresa = :e AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
                  AND (   at.tipo_asiento = :ta
                       OR (ap.id_asiento_tipo = 0 AND ap.tipo_referencia = :tref_iva_w)
                       OR (ap.id_asiento_tipo = 0 AND ap.direccion_iva = :dir AND ap.codigo_tarifa_iva IS NOT NULL))
                ORDER BY ap.id";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':ta' => $tipoAsiento, ':tref_iva_t' => $tipoRefIva, ':tref_iva_w' => $tipoRefIva, ':dir' => $direccionIva]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conceptos de asientos_tipo (tabla global) por código: [codigo => {id, referencia, tipo_cuenta}].
     */
    public function getConceptosPorCodigo(array $codigos): array
    {
        $codigos = array_values(array_unique(array_filter(array_map('strval', $codigos))));
        if (!$codigos) {
            return [];
        }
        $st = $this->db->prepare("SELECT id, codigo, referencia, COALESCE(tipo_cuenta, '') AS tipo_cuenta
                                    FROM asientos_tipo
                                   WHERE eliminado = false AND codigo = ANY(string_to_array(:c, ','))");
        $st->execute([':c' => implode(',', $codigos)]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(string) $r['codigo']] = $r;
        }
        return $out;
    }

    /**
     * Nombres de las entidades de una dimensión de reglas (cliente, producto, categoría, marca),
     * acotados a la empresa: [id => nombre]. Tipo de producción no tiene tabla (1 Bien, 2 Servicio).
     */
    public function getNombresDimension(int $idEmpresa, string $dimension, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        if ($dimension === 'tipo_produccion') {
            return array_intersect_key([1 => 'Bien', 2 => 'Servicio'], array_flip($ids));
        }
        $tabla = ['cliente' => 'clientes', 'producto' => 'productos', 'categoria' => 'categorias', 'marca' => 'marcas'][$dimension] ?? null;
        if ($tabla === null) {
            return [];
        }
        $st = $this->db->prepare("SELECT id, nombre FROM {$tabla}
                                   WHERE id_empresa = :e AND id = ANY(string_to_array(:ids, ',')::int[])");
        $st->execute([':e' => $idEmpresa, ':ids' => implode(',', $ids)]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = trim((string) $r['nombre']);
        }
        return $out;
    }

    /** Cambia solo la cuenta de una regla viva de la empresa. */
    public function actualizarCuentaRegla(int $id, int $idEmpresa, int $idCuenta, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table}
                                     SET id_cuenta = :c, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false");
        return $st->execute([':c' => $idCuenta, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /**
     * Candado transaccional sobre la configuración de un tipo de asiento de la empresa (se libera al
     * COMMIT/ROLLBACK): serializa «leer reglas → copiar» para que dos copias simultáneas no dupliquen.
     */
    public function lockConfiguracionTipoAsiento(int $idEmpresa, string $tipoAsiento): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('config_contable:' || :e || ':' || :ta))");
        $st->execute([':e' => $idEmpresa, ':ta' => $tipoAsiento]);
    }

    /**
     * De las tablas operativas dadas, cuáles tienen al menos un registro vivo de la empresa.
     * Los nombres de tabla vienen de una lista fija del código (AsientoProgramadoService::
     * TABLAS_POR_TIPO_ASIENTO), nunca del usuario; una tabla que no existe cuenta como vacía.
     *
     * @param string[] $tablas
     * @return string[]
     */
    public function tablasConRegistros(int $idEmpresa, array $tablas): array
    {
        $ramas = [];
        foreach (array_unique($tablas) as $t) {
            if (!preg_match('/^[a-z_]+$/', $t) || !$this->tablaExiste($t)) {
                continue;
            }
            $ramas[] = "SELECT '{$t}' AS tabla WHERE EXISTS (SELECT 1 FROM {$t} WHERE id_empresa = :e AND eliminado = false)";
        }
        if (!$ramas) {
            return [];
        }
        $st = $this->db->prepare(implode(' UNION ALL ', $ramas));
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Tipos de asiento (asientos_tipo.tipo_asiento) con al menos una cuenta configurada en la
     * empresa, más los que se configuran por referencia (formas de cobro/pago y conceptos de
     * Ingresos/Egresos no pasan por asientos_tipo).
     *
     * @return string[]
     */
    public function tiposAsientoConCuentas(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT DISTINCT at.tipo_asiento
             FROM {$this->table} ap
             JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo AND at.eliminado = false
             WHERE ap.id_empresa = :e AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
             UNION
             SELECT CASE WHEN ap.tipo_referencia IN ('opcion_ingreso', 'opcion_egreso') THEN 'ingresos_egresos'
                         ELSE 'cobros_pagos' END
             FROM {$this->table} ap
             WHERE ap.id_empresa = :e2 AND ap.eliminado = false AND ap.id_cuenta IS NOT NULL
               AND ap.tipo_referencia IN ('opcion_ingreso', 'opcion_egreso', 'forma_cobro', 'forma_pago')"
        );
        $st->execute([':e' => $idEmpresa, ':e2' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Razón social del proveedor si pertenece a la empresa y no está eliminado; null si no. */
    public function getNombreProveedorEmpresa(int $idEmpresa, int $idProveedor): ?string
    {
        $st = $this->db->prepare("SELECT razon_social FROM proveedores WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $idProveedor, ':e' => $idEmpresa]);
        $nombre = $st->fetchColumn();
        return $nombre === false ? null : (string) $nombre;
    }
}
