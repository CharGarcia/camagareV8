<?php

declare(strict_types=1);

namespace App\repositories\modulos;

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
                    ORDER BY {$orderExpr} {$ordenDir}";

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
                       pc.nombre AS cuenta_nombre
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

        // 2. Obtener los conceptos de retenciones sri que han sido usados en retenciones en venta de esta empresa en su ambiente actual (únicos por código de retención)
        $sqlConceptos = "SELECT DISTINCT ON (rs.codigo_ret) rs.id, rs.codigo_ret, rs.concepto_ret, rs.impuesto_ret
                         FROM retencion_venta_detalle d
                         INNER JOIN retencion_venta_cabecera c ON c.id = d.id_retencion
                         INNER JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion
                         WHERE c.id_empresa = :id_empresa 
                           AND c.eliminado = false
                           AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                         ORDER BY rs.codigo_ret ASC, rs.id DESC";
        $stConceptos = $this->db->prepare($sqlConceptos);
        $stConceptos->execute([':id_empresa' => $idEmpresa]);
        $conceptos = $stConceptos->fetchAll(PDO::FETCH_ASSOC);

        $reglas = [];
        foreach ($conceptos as $c) {
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
                'detalle'           => $c['codigo_ret'] . ' - ' . $c['impuesto_ret'],
                'codigo'            => $c['codigo_ret'],
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

        // 2. Conceptos de retención usados en compras de esta empresa (ambiente actual, únicos por código).
        $sqlConceptos = "SELECT DISTINCT ON (rs.codigo_ret) rs.id, rs.codigo_ret, rs.concepto_ret, rs.impuesto_ret
                         FROM retencion_compra_detalle d
                         INNER JOIN retencion_compra_cabecera c ON c.id = d.id_retencion
                         INNER JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion
                         WHERE c.id_empresa = :id_empresa
                           AND c.eliminado = false
                           AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                         ORDER BY rs.codigo_ret ASC, rs.id DESC";
        $stConceptos = $this->db->prepare($sqlConceptos);
        $stConceptos->execute([':id_empresa' => $idEmpresa]);
        $conceptos = $stConceptos->fetchAll(PDO::FETCH_ASSOC);

        $reglas = [];
        foreach ($conceptos as $c) {
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
                'detalle'           => $c['codigo_ret'] . ' - ' . $c['impuesto_ret'],
                'codigo'            => $c['codigo_ret'],
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
}
