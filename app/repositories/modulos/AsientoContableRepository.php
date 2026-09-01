<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\models\AsientoContableCabecera;
use App\models\AsientoContableDetalle;

class AsientoContableRepository
{
    private AsientoContableCabecera $modelCabecera;
    private AsientoContableDetalle $modelDetalle;

    public function __construct()
    {
        $this->modelCabecera = new AsientoContableCabecera();
        $this->modelDetalle = new AsientoContableDetalle();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT id, fecha_asiento, tipo_comprobante, numero_comprobante, concepto, estado, modulo_origen, total_debe, total_haber 
                FROM asientos_contables_cabecera 
                WHERE id_empresa = :id_empresa AND eliminado = false 
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
                
        $params = [':id_empresa' => $idEmpresa];

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['numero_comprobante', 'concepto', 'modulo_origen'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $sql .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($sql, $params, $parsed['filtros'], [
            'texto'    => [
                'concepto' => 'concepto',
                'numero'   => 'numero_comprobante',
                'origen'   => 'modulo_origen',
            ],
            'exacto'   => [
                'estado' => 'estado',
                'tipo'   => 'tipo_comprobante',
            ],
            'fecha'    => [
                'fecha' => 'fecha_asiento',
            ],
            'numerico' => [
                'total' => 'total_debe',
            ],
        ]);

        $sqlCount = "SELECT COUNT(*) as total FROM ($sql) as sub";
        $pdo = \App\core\Database::getConnection();
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        // Debe cubrir TODAS las columnas con `data-sort` del thead de la vista: las que
        // falten se descartan acá en silencio y el listado se ordena por fecha_asiento, así
        // que el usuario ve el encabezado marcado pero las filas sin reordenar.
        // 'tipo_comprobante' y 'modulo_origen' faltaban y sí se ofrecen como ordenables.
        $ordenColPermitidas = [
            'fecha_asiento', 'numero_comprobante', 'tipo_comprobante',
            'concepto', 'modulo_origen', 'total_debe', 'estado',
        ];
        $ordenCol = in_array($ordenCol, $ordenColPermitidas, true) ? $ordenCol : 'fecha_asiento';
        $ordenDir = in_array(strtoupper($ordenDir), ['ASC', 'DESC'], true) ? strtoupper($ordenDir) : 'DESC';

        // Desempate por id: ninguna columna ordenable es única (varios asientos
        // comparten fecha_asiento), y sin un criterio estable LIMIT/OFFSET puede
        // repetir una fila en dos páginas y saltarse otra. La consulta no usa
        // alias de tabla, por eso la columna va suelta.
        $sql .= " ORDER BY {$ordenCol} {$ordenDir}, id DESC";
        
        if ($perPage > 0) {
            $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        }

        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getDetalleAsiento(int $idAsiento, int $idEmpresa): array
    {
        $sql = "SELECT a.* 
                FROM asientos_contables_cabecera a 
                WHERE a.id = :id AND a.id_empresa = :id_empresa AND a.eliminado = false
                AND a.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idAsiento, ':id_empresa' => $idEmpresa]);
        $cabecera = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cabecera) {
            return [];
        }

        // Obtener detalles con la cuenta contable, centro de costo y proyecto
        $sqlDet = "SELECT d.*, c.codigo as codigo_cuenta, c.nombre as nombre_cuenta,
                          cc.nombre as nombre_centro_costo, p.nombre as nombre_proyecto
                   FROM asientos_contables_detalle d
                   LEFT JOIN plan_cuentas c ON d.id_cuenta_contable = c.id
                   LEFT JOIN centro_costos cc ON d.id_centro_costo = cc.id
                   LEFT JOIN proyectos p ON d.id_proyecto = p.id
                   WHERE d.id_asiento = :id_asiento AND d.eliminado = false
                   ORDER BY CASE WHEN d.debe > 0 THEN 1 ELSE 2 END ASC, d.id ASC";
        $stmtDet = $pdo->prepare($sqlDet);
        $stmtDet->execute([':id_asiento' => $idAsiento]);
        $cabecera['detalles'] = $stmtDet->fetchAll(\PDO::FETCH_ASSOC);

        return $cabecera;
    }

    /**
     * Candado transaccional del asiento de UN documento. Regla §8: todo
     * "leer → decidir → escribir" sobre un valor compartido va bloqueado, y aquí el valor
     * compartido es «¿este documento ya tiene asiento?» — sin candado, dos procesos
     * simultáneos (dos corridas del sincronizador, o el sincronizador y un guardado) leen
     * ambos "no tiene" e insertan dos asientos para el mismo documento.
     *
     * Se libera solo con el COMMIT/ROLLBACK de la transacción en curso, así que **debe
     * llamarse con una transacción abierta**: fuera de transacción, PostgreSQL la cierra
     * en el mismo statement y el candado no protege nada.
     *
     * La clave incluye el módulo, así que no bloquea documentos que no se relacionan
     * entre sí (una compra no espera por una factura de venta).
     */
    public function lockAsientoOrigen(int $idEmpresa, string $moduloOrigen, int $idReferenciaOrigen): void
    {
        $sql = "SELECT pg_advisory_xact_lock(hashtext('asiento_origen:' || :e || ':' || :m || ':' || :r))";
        $stmt = \App\core\Database::getConnection()->prepare($sql);
        $stmt->execute([':e' => $idEmpresa, ':m' => $moduloOrigen, ':r' => $idReferenciaOrigen]);
    }

    /**
     * Candado del contador de `numero_comprobante`. `generarNumeroComprobante()` es un
     * MAX+1 (leer → calcular → escribir), así que sin candado dos asientos creados a la vez
     * en la misma empresa y tipo salen con el MISMO número. Va por (empresa, tipo) para no
     * serializar tipos distintos entre sí.
     */
    public function lockNumeroComprobante(int $idEmpresa, string $tipoComprobante): void
    {
        $sql = "SELECT pg_advisory_xact_lock(hashtext('asiento_numero:' || :e || ':' || :t))";
        $stmt = \App\core\Database::getConnection()->prepare($sql);
        $stmt->execute([':e' => $idEmpresa, ':t' => strtolower(trim($tipoComprobante))]);
    }

    public function getAsientoPorOrigen(string $moduloOrigen, int $idReferenciaOrigen, int $idEmpresa): ?array
    {
        $sql = "SELECT id FROM asientos_contables_cabecera
                WHERE modulo_origen = :modulo AND id_referencia_origen = :id_ref
                AND id_empresa = :id_empresa AND eliminado = false AND estado != 'anulado'
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                ORDER BY id DESC LIMIT 1";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':modulo' => $moduloOrigen,
            ':id_ref' => $idReferenciaOrigen,
            ':id_empresa' => $idEmpresa
        ]);
        
        $id = $stmt->fetchColumn();
        if ($id) {
            return $this->getDetalleAsiento((int)$id, $idEmpresa);
        }
        return null;
    }

    /**
     * Todos los ids de cabeceras ACTIVAS para un origen — a diferencia de getAsientoPorOrigen
     * (que asume una sola y trae la más reciente), un mismo origen puede tener varios asientos
     * a la vez (p. ej. nómina contabilizada por empleado, uno por cada uno).
     */
    public function getIdsAsientosPorOrigen(string $moduloOrigen, int $idReferenciaOrigen, int $idEmpresa): array
    {
        $sql = "SELECT id FROM asientos_contables_cabecera
                WHERE modulo_origen = :modulo AND id_referencia_origen = :id_ref
                  AND id_empresa = :id_empresa AND eliminado = false AND estado != 'anulado'
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                ORDER BY id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':modulo' => $moduloOrigen,
            ':id_ref' => $idReferenciaOrigen,
            ':id_empresa' => $idEmpresa
        ]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * id_entidad distintos (de un tipo_entidad dado) que cubren las líneas de un asiento ya
     * guardado — para emparejar, en una regeneración, cada asiento existente con "su" entidad
     * (p. ej. qué empleado le corresponde a cada asiento de nómina por empleado).
     */
    public function getEntidadesDeAsiento(int $idAsiento, string $tipoEntidad): array
    {
        $sql = "SELECT DISTINCT id_entidad FROM asientos_contables_detalle
                WHERE id_asiento = :id AND tipo_entidad = :tipo
                  AND eliminado = false AND id_entidad IS NOT NULL";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idAsiento, ':tipo' => $tipoEntidad]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * updated_at más reciente entre varias cabeceras. Sirve para resincronizar el updated_at
     * del documento de origen (p. ej. rol_cabecera) justo después de vincular su asiento — ver
     * RolAsientoService::contabilizar(), que lo necesita para no quedar "más nuevo" que su
     * propio asiento recién generado (eso lo marcaría como pendiente otra vez en
     * SincronizadorAsientosService, en un ciclo infinito).
     */
    public function getMaxUpdatedAt(array $ids): ?string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return null;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare("SELECT MAX(updated_at) FROM asientos_contables_cabecera WHERE id IN ($in)");
        $stmt->execute($ids);
        $v = $stmt->fetchColumn();
        return $v ?: null;
    }

    public function generarNumeroComprobante(int $idEmpresa, string $tipoComprobante): string
    {
        $tipoComprobante = strtolower(trim($tipoComprobante));
        $prefijos = [
            'diario'               => 'DI',
            'apertura'             => 'AP',
            'cierre'               => 'CI',
            'adquisiciones'        => 'AD', // valor histórico sin uso: los Services reales graban tipo_comprobante='compras'
            'compras'              => 'CO',
            'egresos'              => 'EG',
            'ingresos'             => 'IN',
            'ventas'               => 'VE',
            'retenciones_ventas'   => 'RV', // sin uso hoy: retenciones de venta graban tipo_comprobante='ventas'
            'retenciones_compras'  => 'RC', // sin uso hoy: retenciones de compra graban tipo_comprobante='compras'
            'nomina'               => 'NO',
            'consignacion'         => 'CV',
            'retorno_consignacion' => 'RT',
            'cambio_producto'      => 'CP',
            'declaracion_retenciones' => 'DR',
            'declaracion_iva'      => 'DV',
            'importaciones'        => 'IM',
            'traspasos'            => 'TR',
        ];
        // Sin esto, cualquier tipo_comprobante no listado cae en 'DI' y comparte numeración
        // con el diario real: dos documentos de tipos distintos pueden terminar con el MISMO
        // numero_comprobante (ej. una Compra y un asiento de diario, ambos "DI-000003").
        $prefijo = $prefijos[$tipoComprobante] ?? 'DI';

        // Filtra por el PREFIJO resuelto (no por tipo_comprobante crudo) y exige el formato
        // exacto "PREFIJO-NNNNNN". Antes tomaba "ORDER BY id DESC LIMIT 1" sin exigir formato:
        // si el último id de ese tipo_comprobante resultaba ser un código heredado de la
        // migración desde MySQL (ej. 'NCV2672', sin guion en esa posición), el parseo fallaba
        // silenciosamente y el contador volvía a 1, repitiendo números ya usados (ej. 'VE-000001'
        // otra vez para una factura nueva, chocando con una factura real de meses atrás).
        $sql = "SELECT numero_comprobante FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND numero_comprobante ~ :patron
                ORDER BY (SPLIT_PART(numero_comprobante, '-', 2))::int DESC
                LIMIT 1";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':patron'     => '^' . $prefijo . '-[0-9]+$',
        ]);
        $ultimo = $stmt->fetchColumn();

        $siguiente = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $siguiente = (int)end($partes) + 1;
        }

        return $prefijo . '-' . str_pad((string)$siguiente, 6, '0', STR_PAD_LEFT);
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO asientos_contables_cabecera (
            id_empresa, fecha_asiento, tipo_comprobante, numero_comprobante, 
            concepto, estado, modulo_origen, id_referencia_origen, total_debe, total_haber, observaciones, created_by, tipo_ambiente
        ) VALUES (
            :id_empresa, :fecha_asiento, :tipo_comprobante, :numero_comprobante, 
            :concepto, :estado, :modulo_origen, :id_referencia_origen, :total_debe, :total_haber, :observaciones, :created_by,
            (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
        ) RETURNING id";
        
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_empresa' => $data['id_empresa'],
            ':fecha_asiento' => $data['fecha_asiento'],
            ':tipo_comprobante' => $data['tipo_comprobante'],
            ':numero_comprobante' => $data['numero_comprobante'],
            ':concepto' => $data['concepto'],
            ':estado' => $data['estado'],
            ':modulo_origen' => $data['modulo_origen'],
            ':id_referencia_origen' => $data['id_referencia_origen'],
            ':total_debe' => $data['total_debe'],
            ':total_haber' => $data['total_haber'],
            ':observaciones' => $data['observaciones'],
            ':created_by' => $data['created_by'] ?? null
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE asientos_contables_cabecera SET 
            fecha_asiento = :fecha_asiento, tipo_comprobante = :tipo_comprobante,
            concepto = :concepto, estado = :estado, modulo_origen = :modulo_origen,
            id_referencia_origen = :id_referencia_origen, total_debe = :total_debe,
            total_haber = :total_haber, observaciones = :observaciones,
            updated_by = :updated_by, updated_at = :updated_at,
            tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = (SELECT id_empresa FROM asientos_contables_cabecera WHERE id = :id))
            WHERE id = :id";
            
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':fecha_asiento' => $data['fecha_asiento'],
            ':tipo_comprobante' => $data['tipo_comprobante'],
            ':concepto' => $data['concepto'],
            ':estado' => $data['estado'],
            ':modulo_origen' => $data['modulo_origen'],
            ':id_referencia_origen' => $data['id_referencia_origen'],
            ':total_debe' => $data['total_debe'],
            ':total_haber' => $data['total_haber'],
            ':observaciones' => $data['observaciones'],
            ':updated_by' => $data['updated_by'] ?? null,
            ':updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s')
        ]);
    }

    public function deleteDetalles(int $idAsiento): void
    {
        $sql = "DELETE FROM asientos_contables_detalle WHERE id_asiento = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idAsiento]);
    }

    public function insertDetalle(array $data): void
    {
        $sql = "INSERT INTO asientos_contables_detalle (
            id_empresa, id_asiento, id_cuenta_contable, id_centro_costo, id_proyecto,
            debe, haber, referencia_detalle, documento_referencia, id_entidad, tipo_entidad, created_by
        ) VALUES (
            :id_empresa, :id_asiento, :id_cuenta_contable, :id_centro_costo, :id_proyecto,
            :debe, :haber, :referencia_detalle, :documento_referencia, :id_entidad, :tipo_entidad, :created_by
        )";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_asiento' => $data['id_asiento'],
            ':id_cuenta_contable' => $data['id_cuenta_contable'],
            ':id_centro_costo' => $data['id_centro_costo'],
            ':id_proyecto' => $data['id_proyecto'],
            ':debe' => $data['debe'],
            ':haber' => $data['haber'],
            ':referencia_detalle' => $data['referencia_detalle'],
            ':documento_referencia' => $data['documento_referencia'],
            ':id_entidad' => $data['id_entidad'],
            ':tipo_entidad' => $data['tipo_entidad'],
            ':created_by' => $data['created_by'] ?? null
        ]);
    }

    /**
     * Tercero y número del documento que originó un asiento (factura, compra, egreso…), para
     * completar las líneas que no los traen. El mapa de módulos vive en
     * App\Helpers\DocumentoOrigenAsiento; devuelve null si el módulo no tiene documento con
     * tercero identificable (nómina, traspasos, activos fijos, declaraciones, asiento manual)
     * o si su tabla todavía no existe en esta instalación.
     *
     * @return array{tipo_entidad: ?string, id_entidad: ?int, numero_documento: ?string}|null
     */
    public function getDatosDocumentoOrigen(?string $moduloOrigen, int $idReferenciaOrigen, int $idEmpresa): ?array
    {
        $doc = \App\Helpers\DocumentoOrigenAsiento::paraModulo($moduloOrigen);
        if ($doc === null || $idReferenciaOrigen <= 0) {
            return null;
        }

        $pdo = \App\core\Database::getConnection();
        try {
            // Las expresiones del mapa son constantes del código; los valores van preparados.
            $sql = "SELECT ({$doc['tipo']})::varchar AS tipo_entidad,
                           ({$doc['entidad']})::bigint AS id_entidad,
                           ({$doc['numero']})::varchar AS numero_documento
                    FROM {$doc['tabla']} t
                    WHERE t.id = :id AND t.id_empresa = :id_empresa
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $idReferenciaOrigen, ':id_empresa' => $idEmpresa]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Tabla o columna inexistente en esta instalación: el asiento se guarda igual.
            error_log('Asientos: no se pudo leer el documento origen (' . $moduloOrigen . '). ' . $e->getMessage());
            return null;
        }

        if (!$row) {
            return null;
        }

        return [
            'tipo_entidad' => $row['tipo_entidad'] !== null && $row['tipo_entidad'] !== '' ? (string) $row['tipo_entidad'] : null,
            'id_entidad' => !empty($row['id_entidad']) ? (int) $row['id_entidad'] : null,
            'numero_documento' => !empty($row['numero_documento']) ? (string) $row['numero_documento'] : null,
        ];
    }

    /**
     * Importe del documento que originó el asiento (total de la factura, de la compra, del
     * ingreso…), con el que debe cuadrar el asiento. El mapa vive en
     * App\Helpers\CuadreDocumentoAsiento; devuelve null si ese origen no se compara con su
     * documento, si el documento ya no existe o si su tabla no está en esta instalación.
     */
    public function getTotalDocumentoOrigen(?string $moduloOrigen, int $idReferenciaOrigen, int $idEmpresa): ?float
    {
        $cfg = \App\Helpers\CuadreDocumentoAsiento::paraModulo($moduloOrigen);
        if ($cfg === null || $idReferenciaOrigen <= 0) {
            return null;
        }

        $pdo = \App\core\Database::getConnection();
        try {
            // La expresión del total es una constante del código; los valores van preparados.
            $sql = "SELECT ({$cfg['total']})::numeric AS total_documento
                    FROM {$cfg['tabla']} t
                    WHERE t.id = :id AND t.id_empresa = :id_empresa AND t.eliminado = false
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $idReferenciaOrigen, ':id_empresa' => $idEmpresa]);
            $total = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            // Tabla o columna inexistente en esta instalación: no se comprueba el cuadre.
            error_log('Asientos: no se pudo leer el total del documento origen (' . $moduloOrigen . '). ' . $e->getMessage());
            return null;
        }

        return $total === false || $total === null ? null : round((float) $total, 2);
    }

    /**
     * Cuentas contables con las que la empresa resuelve uno o varios slots de cartera
     * (PORCOBRARFACTURAVENTA, PORPAGARFACTURACOMPRA, FACTREEMB_CXC_CLIENTE…), según su
     * configuración contable. Mismo criterio que AsientoBuilderService::cuentasDelSlot(): sirve
     * para reconocer, dentro de un asiento editado a mano, cuáles de sus líneas son la cartera
     * del documento. Se aceptan varios códigos porque algunos documentos resuelven su cuenta con
     * fallback (la factura de reembolso usa la suya y, si no está configurada, la de ventas).
     *
     * @param string[] $codigosSlot
     * @return int[] ids de cuenta (vacío si la empresa no tiene ninguno de esos slots configurado)
     */
    public function getCuentasSlotCartera(int $idEmpresa, array $codigosSlot): array
    {
        $codigosSlot = array_values(array_filter($codigosSlot));
        if ($codigosSlot === []) {
            return [];
        }

        $pdo = \App\core\Database::getConnection();
        try {
            $placeholders = [];
            $params = [':id_empresa' => $idEmpresa];
            foreach ($codigosSlot as $i => $codigo) {
                $placeholders[] = ':codigo' . $i;
                $params[':codigo' . $i] = $codigo;
            }

            $stmt = $pdo->prepare(
                "SELECT DISTINCT ap.id_cuenta
                   FROM asientos_programados ap
                   JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                  WHERE ap.id_empresa = :id_empresa
                    AND at.codigo IN (" . implode(', ', $placeholders) . ")
                    AND ap.eliminado = false
                    AND ap.id_cuenta IS NOT NULL"
            );
            $stmt->execute($params);
            return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            // Sin catálogo de configuración contable no se puede identificar la cartera:
            // el llamador cae al criterio del total Debe.
            error_log('Asientos: no se pudieron leer las cuentas de los slots '
                . implode(', ', $codigosSlot) . '. ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Número del documento origen para los módulos que no están en DocumentoOrigenAsiento pero sí
     * definen su propia expresión en CuadreDocumentoAsiento (hoy, la factura de reembolso).
     * Solo se usa para nombrar el documento en los mensajes de cuadre.
     */
    public function getNumeroDocumentoCuadre(?string $moduloOrigen, int $idReferenciaOrigen, int $idEmpresa): ?string
    {
        $cfg = \App\Helpers\CuadreDocumentoAsiento::paraModulo($moduloOrigen);
        if ($cfg === null || empty($cfg['numero']) || $idReferenciaOrigen <= 0) {
            return null;
        }

        $pdo = \App\core\Database::getConnection();
        try {
            // La expresión del número es una constante del código; los valores van preparados.
            $stmt = $pdo->prepare(
                "SELECT ({$cfg['numero']})::varchar AS numero
                   FROM {$cfg['tabla']} t
                  WHERE t.id = :id AND t.id_empresa = :id_empresa
                  LIMIT 1"
            );
            $stmt->execute([':id' => $idReferenciaOrigen, ':id_empresa' => $idEmpresa]);
            $numero = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('Asientos: no se pudo leer el número del documento origen (' . $moduloOrigen . '). ' . $e->getMessage());
            return null;
        }

        return $numero === false || $numero === null || $numero === '' ? null : (string) $numero;
    }

    /**
     * Marca (o desmarca) el asiento como editado a mano. Mientras esté marcado, el service del
     * módulo dueño del documento NO lo regenera desde AsientoBuilderService al reguardar el
     * documento: la corrección del usuario manda. Ver database/asientos_editado_manual.sql.
     *
     * Si la base todavía no tiene la columna (migración pendiente), no rompe nada: se ignora
     * en silencio y el sistema se comporta como antes — el asiento se sigue regenerando.
     */
    public function setEditadoManual(int $idAsiento, int $idEmpresa, int $updatedBy, bool $valor): void
    {
        $sql = "UPDATE asientos_contables_cabecera
                SET editado_manual = :valor, updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        try {
            $stmt = \App\core\Database::getConnection()->prepare($sql);
            $stmt->execute([
                ':id' => $idAsiento,
                ':id_empresa' => $idEmpresa,
                ':updated_by' => $updatedBy,
                ':valor' => $valor ? 'true' : 'false',
            ]);
        } catch (\Throwable $e) {
            error_log('[Asientos] editado_manual no disponible (falta la migración): ' . $e->getMessage());
        }
    }

    /** ¿El asiento está marcado como editado a mano? false también si falta la columna. */
    public function esEditadoManual(int $idAsiento): bool
    {
        try {
            $stmt = \App\core\Database::getConnection()->prepare(
                "SELECT editado_manual FROM asientos_contables_cabecera WHERE id = :id"
            );
            $stmt->execute([':id' => $idAsiento]);
            $v = $stmt->fetchColumn();
            // PostgreSQL devuelve el boolean como 't'/'f' por PDO: false llega como cadena vacía.
            return $v === true || $v === 't' || $v === '1' || $v === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function updateEstado(int $idAsiento, string $estado, int $updatedBy): void
    {
        $sql = "UPDATE asientos_contables_cabecera SET estado = :estado, updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $idAsiento,
            ':estado' => $estado,
            ':updated_by' => $updatedBy
        ]);
    }

    /**
     * Desvincula el asiento contable de una factura de venta (pone id_asiento_contable = NULL).
     */
    public function desvincularAsientoVenta(int $idVenta): void
    {
        $sql = "UPDATE ventas_cabecera SET id_asiento_contable = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idVenta]);
    }

    public function desvincularAsientoRetencionVenta(int $idRetencion): void
    {
        $sql = "UPDATE retencion_venta_cabecera SET id_asiento_contable = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idRetencion]);
    }

    public function desvincularAsientoRetencionCompra(int $idRetencion): void
    {
        $sql = "UPDATE retencion_compra_cabecera SET id_asiento_contable = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idRetencion]);
    }

    public function desvincularAsientoIngreso(int $idIngreso): void
    {
        $sql = "UPDATE ingresos_cabecera SET id_asiento_contable = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idIngreso]);
    }

    public function desvincularAsientoEgreso(int $idEgreso): void
    {
        $sql = "UPDATE egresos_cabecera SET id_asiento_contable = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idEgreso]);
    }

    public function desvincularAsientoTraspaso(int $idTraspaso): void
    {
        $sql = "UPDATE traspasos_cabecera SET id_asiento_contable = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idTraspaso]);
    }

    /**
     * Desvincula genéricamente la columna de asiento de un documento (tabla + columna salen del
     * mapa fijo App\Helpers\DocumentoOrigenAsiento::DOCUMENTOS, nunca de entrada de usuario —
     * seguro interpolarlas). Evita repetir un método desvincularAsientoX() por cada módulo nuevo
     * que se agregue a ese mapa (ver AsientoContableService::anular()).
     */
    public function desvincularAsientoGenerico(string $tabla, string $columna, int $id): void
    {
        $sql = "UPDATE {$tabla} SET {$columna} = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
    }
}
