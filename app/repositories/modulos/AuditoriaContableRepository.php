<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Repository del módulo Auditoría Contable.
 *
 * Detecta inconsistencias entre los documentos operativos y sus asientos
 * contables, y persiste los hallazgos en `auditoria_contable_incidencias`.
 *
 * Reglas clave (CLAUDE.md):
 *  - Multiempresa: TODA consulta filtra por id_empresa + eliminado = false.
 *  - Multi-ambiente: además filtra por el tipo_ambiente activo de la empresa
 *    ('1' Pruebas, '2' Producción), igual que el resto de listados operativos.
 *  - Acceso a BD por PDO preparado; los nombres de tabla/columna/origen que se
 *    interpolan provienen SIEMPRE de la whitelist $origenes, nunca del usuario.
 */
class AuditoriaContableRepository extends BaseRepository
{
    /** Subconsulta del ambiente activo de la empresa (usa el placeholder :id_empresa). */
    private const AMB = "(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

    /**
     * Configuración de los orígenes auditables. La clave es el `modulo_origen`
     * tal como lo graban los Services al crear el asiento.
     *
     *  - tabla          : tabla operativa (cabecera).
     *  - total          : expresión SQL del total del documento (alias d).
     *  - fecha          : columna de fecha del documento (alias d). Varía por módulo.
     *  - estado_filtro  : condición de "documento vigente que debe tener asiento"
     *                     (alias d). Replica el criterio del SincronizadorAsientosService.
     *  - tiene_estado   : si la cabecera tiene columna `estado` (para estado_incoherente).
     *  - chequear_monto : false cuando el total del documento NO es comparable con el
     *                     total del asiento (p. ej. nómina: el asiento incluye aportes;
     *                     cambios de producto: el asiento va a costo). Solo se detecta faltante.
     *  - regenerable    : si su Service implementa procesarAsientoContablePorSincronizacion.
     *                     Los que no, se auditan pero no se pueden regenerar desde aquí.
     *  - tiene_id_asiento: si la cabecera tiene columna id_asiento_contable (para desvincular).
     *  - entidad_mig    : valor de `migracion_mysql_map.entidad` de este origen (null si no aplica).
     *                     Los documentos que la migración desde MySQL INSERTÓ no generan asiento
     *                     propio (su contabilidad viene del histórico migrado), así que se
     *                     excluyen de la auditoría y de la regeneración. Mismos nombres que
     *                     usa SincronizadorAsientosService.
     */
    private array $origenes = [
        'factura_venta' => [
            'tabla'          => 'ventas_cabecera',
            'total'          => 'd.importe_total',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => "d.estado IN ('autorizado','contabilizado')",
            'tiene_estado'   => true,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'facturas',
        ],
        'compra' => [
            // compras_cabecera no tiene columna `estado` en BD (igual que el SincronizadorAsientosService,
            // que no filtra por estado para compras). Por eso estado_filtro=1=1 y tiene_estado=false.
            'tabla'          => 'compras_cabecera',
            'total'          => 'd.importe_total',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => '1=1',
            'tiene_estado'   => false,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'compras',
        ],
        'liquidacion_compra' => [
            'tabla'          => 'liquidaciones_cabecera',
            'total'          => 'd.importe_total',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => "d.estado IN ('autorizado','contabilizado')",
            'tiene_estado'   => true,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'liquidaciones',
        ],
        'nota_credito' => [
            'tabla'          => 'notas_credito_cabecera',
            'total'          => 'd.importe_total',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => "d.estado IN ('autorizado','contabilizado')",
            'tiene_estado'   => true,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'notas_credito',
        ],
        'retencion_venta' => [
            'tabla'          => 'retencion_venta_cabecera',
            'total'          => '(COALESCE(d.total_isd,0)+COALESCE(d.total_iva,0)+COALESCE(d.total_renta,0))',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => '1=1',
            'tiene_estado'   => false,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'retenciones_venta',
        ],
        'retencion_compra' => [
            'tabla'          => 'retencion_compra_cabecera',
            'total'          => 'd.total_retenido',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => '1=1', // el Sincronizador no filtra por estado en retenciones
            'tiene_estado'   => true,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'retenciones_compra',
        ],
        'ingreso' => [
            'tabla'          => 'ingresos_cabecera',
            'total'          => 'd.monto_total',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => "d.estado <> 'anulado'",
            'tiene_estado'   => true,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'ingresos',
        ],
        'egreso' => [
            'tabla'          => 'egresos_cabecera',
            'total'          => 'd.monto_total',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => "d.estado <> 'anulado'",
            'tiene_estado'   => true,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'egresos',
        ],
        'consignacion_venta' => [
            'tabla'          => 'consignaciones_ventas',
            'total'          => 'd.total',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => "d.estado <> 'Anulada'",
            'tiene_estado'   => true,
            'chequear_monto' => false, // el asiento es reclasificación a costo, no al total de venta
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'consignaciones',
        ],
        'recibo_venta' => [
            'tabla'          => 'recibos_venta_cabecera',
            'total'          => 'd.importe_total',
            'fecha'          => 'fecha_emision',
            // Igual que la factura: solo el documento vigente ya emitido debe tener asiento.
            // 'facturado' NO entra: al facturar se anula el asiento del recibo y manda el de la
            // factura (si se incluyera, se reportaría como faltante un asiento anulado a propósito).
            'estado_filtro'  => "d.estado = 'emitido'",
            'tiene_estado'   => true,
            'chequear_monto' => true,
            'regenerable'    => true,
            'tiene_id_asiento' => true,
            'entidad_mig'    => 'recibos',
        ],
        'retorno_cv' => [
            'tabla'          => 'retornos_cv',
            'total'          => 'd.total',
            'fecha'          => 'fecha_retorno',
            // Solo los 'Emitida' tienen impacto contable (RetornoCvService::procesarAsientoContable
            // retorna sin generar en Borrador). Con "<> 'Anulada'" los Borrador salían como faltantes.
            'estado_filtro'  => "d.estado = 'Emitida'",
            'tiene_estado'   => true,
            'chequear_monto' => false, // el asiento va a costo del inventario devuelto
            'regenerable'    => true,  // RetornoCvService ya expone procesarAsientoContablePorSincronizacion
            'tiene_id_asiento' => true,
            'entidad_mig'    => null,
        ],
        'cambio_producto_cv' => [
            'tabla'          => 'cambios_producto_cv',
            'total'          => '(COALESCE(d.subtotal_devuelto,0)+COALESCE(d.subtotal_entregado,0))',
            'fecha'          => 'fecha_cambio',
            // Solo los 'Emitida' tienen impacto contable (el service no genera en Borrador).
            'estado_filtro'  => "COALESCE(d.estado,'') = 'Emitida'",
            'tiene_estado'   => true,
            'chequear_monto' => false, // asiento a costo; el total del documento no es comparable
            'regenerable'    => true,  // CambioProductoCvService ya expone procesarAsientoContablePorSincronizacion
            'tiene_id_asiento' => true,
            'entidad_mig'    => null,
        ],
        'FACTURACION_CV' => [
            'tabla'          => 'consignaciones_facturas',
            'total'          => 'd.total',
            'fecha'          => 'fecha_emision',
            'estado_filtro'  => "d.estado <> 'anulada'",
            'tiene_estado'   => true,
            'chequear_monto' => true,
            'regenerable'    => true,  // ConsignacionFacturaService ya expone procesarAsientoContablePorSincronizacion
            'tiene_id_asiento' => false, // el enlace es id_asiento_reingreso, no id_asiento_contable
            'entidad_mig'    => null,
        ],
        'nomina' => [
            'tabla'          => 'rol_cabecera',
            'total'          => 'd.total_neto',
            'fecha'          => 'fecha_pago',
            'estado_filtro'  => "d.estado <> 'anulado'",
            'tiene_estado'   => true,
            'chequear_monto' => false, // el asiento incluye aportes/provisiones: no cuadra con total_neto
            'regenerable'    => false, // RolAsientoService::contabilizar() tiene otra firma
            'tiene_id_asiento' => false, // rol_cabecera no tiene id_asiento_contable
            'entidad_mig'    => null,
        ],
    ];

    /** Cache de existencia de la tabla del mapa de migración. */
    private ?bool $tieneMapMig = null;

    public function __construct()
    {
        parent::__construct('auditoria_contable_incidencias');
    }

    /** Lista de orígenes auditables (claves de modulo_origen). */
    public function getOrigenes(): array
    {
        return array_keys($this->origenes);
    }

    /** Valida que un origen pertenezca a la whitelist. */
    public function esOrigenValido(string $origen): bool
    {
        return isset($this->origenes[$origen]);
    }

    /** Devuelve la tabla operativa de un origen, o null si no existe. */
    public function getTablaOrigen(string $origen): ?string
    {
        return $this->origenes[$origen]['tabla'] ?? null;
    }

    /**
     * ¿Este origen puede regenerar su asiento desde aquí? Solo los módulos cuyo Service
     * implementa procesarAsientoContablePorSincronizacion. El resto se audita igual,
     * pero su asiento se corrige desde su propio módulo.
     */
    public function esOrigenRegenerable(string $origen): bool
    {
        return !empty($this->origenes[$origen]['regenerable']);
    }

    /** Orígenes que admiten regeneración (para el selector de regeneración masiva). */
    public function getOrigenesRegenerables(): array
    {
        return array_keys(array_filter($this->origenes, fn($c) => !empty($c['regenerable'])));
    }

    /** Ambiente activo de la empresa ('1' Pruebas, '2' Producción). */
    public function getAmbienteEmpresa(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?");
        $st->execute([$idEmpresa]);
        return (string) ($st->fetchColumn() ?: '1');
    }

    private function ejecutar(string $sql, array $params): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿Existe la tabla del mapa de migración desde MySQL? (se cachea) */
    private function tieneMapaMigracion(): bool
    {
        if ($this->tieneMapMig === null) {
            try {
                $this->tieneMapMig = (bool) $this->db->query("SELECT to_regclass('public.migracion_mysql_map')")->fetchColumn();
            } catch (\Throwable $e) {
                $this->tieneMapMig = false;
            }
        }
        return $this->tieneMapMig;
    }

    /**
     * Fragmento SQL que excluye los documentos INSERTADOS por la migración desde MySQL.
     * Replica el criterio de SincronizadorAsientosService: los `vinculado = true` son
     * documentos NATIVOS que la migración solo enlazó por número SRI y sí generan asiento;
     * los demás traen su contabilidad del histórico migrado y nunca generan asiento propio.
     * $origen viene de la whitelist y $idExpr es literal del código → seguros de interpolar.
     */
    private function sqlExcluirMigrados(string $origen, string $idExpr): string
    {
        $entidad = $this->origenes[$origen]['entidad_mig'] ?? null;
        if ($entidad === null || !$this->tieneMapaMigracion()) {
            return '';
        }
        return " AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map mm
                                 WHERE mm.entidad = '{$entidad}'
                                   AND mm.id_destino = {$idExpr}
                                   AND mm.vinculado IS NOT TRUE) ";
    }

    /** ¿Este documento lo insertó la migración desde MySQL? (no debe regenerarse) */
    public function esDocumentoMigrado(string $origen, int $idDocumento): bool
    {
        $entidad = $this->origenes[$origen]['entidad_mig'] ?? null;
        if ($entidad === null || !$this->tieneMapaMigracion()) {
            return false;
        }
        $st = $this->db->prepare(
            "SELECT 1 FROM migracion_mysql_map
             WHERE entidad = :ent AND id_destino = :id AND vinculado IS NOT TRUE LIMIT 1"
        );
        $st->execute([':ent' => $entidad, ':id' => $idDocumento]);
        return $st->fetchColumn() !== false;
    }

    /**
     * SQL para obtener el número del documento (serie-secuencial) y el nombre de la
     * entidad (cliente / proveedor / empleado) de cada origen. El alias del documento es `d`.
     * Todo es literal del código, nunca entrada del usuario.
     */
    private function sqlDocumentoInfo(string $origen): array
    {
        $serie   = "CONCAT(d.establecimiento,'-',d.punto_emision,'-',d.secuencial)";
        $joinCli = 'LEFT JOIN clientes e ON e.id = d.id_cliente';
        $joinPrv = 'LEFT JOIN proveedores e ON e.id = d.id_proveedor';

        return match ($origen) {
            // Documentos de cliente con serie estándar
            'factura_venta', 'nota_credito', 'retencion_venta', 'consignacion_venta',
            'recibo_venta', 'retorno_cv', 'cambio_producto_cv', 'FACTURACION_CV' => [
                'numero' => $serie, 'entidad_join' => $joinCli, 'entidad' => 'e.nombre',
            ],
            // Compras guarda la serie del proveedor en columnas *_prov
            'compra' => [
                'numero' => "CONCAT(d.establecimiento_prov,'-',d.punto_emision_prov,'-',d.secuencial_prov)",
                'entidad_join' => $joinPrv, 'entidad' => 'e.razon_social',
            ],
            'liquidacion_compra', 'retencion_compra' => [
                'numero' => $serie, 'entidad_join' => $joinPrv, 'entidad' => 'e.razon_social',
            ],
            // Ingresos: cliente o, si no hay, el texto libre "recibo de"
            'ingreso' => [
                'numero' => $serie, 'entidad_join' => $joinCli,
                'entidad' => 'COALESCE(e.nombre, d.recibo_de)',
            ],
            // Egresos: puede ser a proveedor o a empleado
            'egreso' => [
                'numero' => $serie,
                'entidad_join' => 'LEFT JOIN proveedores e ON e.id = d.id_proveedor '
                                . 'LEFT JOIN empleados em ON em.id = d.id_empleado',
                'entidad' => 'COALESCE(e.razon_social, em.nombres_apellidos)',
            ],
            // El rol de pagos no tiene entidad única: se identifica por su descripción/período
            'nomina' => [
                'numero' => "COALESCE(NULLIF(d.descripcion,''), CONCAT('Rol ', d.periodo_mes, '/', d.periodo_anio))",
                'entidad_join' => '', 'entidad' => 'NULL',
            ],
            default => ['numero' => 'NULL', 'entidad_join' => '', 'entidad' => 'NULL'],
        };
    }

    /**
     * Completa los hallazgos con el número del documento y el nombre de la entidad.
     * Hace una sola consulta por origen presente (no una por fila).
     */
    private function enriquecerHallazgos(array $hallazgos): array
    {
        $porOrigen = [];
        foreach ($hallazgos as $i => $h) {
            $origen = (string) $h['modulo_origen'];
            if (!empty($h['id_documento']) && $this->esOrigenValido($origen)) {
                $porOrigen[$origen][(int) $h['id_documento']][] = $i;
            }
        }

        foreach ($porOrigen as $origen => $mapIds) {
            $datos = $this->getDatosDocumentos($origen, array_keys($mapIds));
            foreach ($mapIds as $idDoc => $indices) {
                foreach ($indices as $i) {
                    $hallazgos[$i]['documento_numero'] = $datos[$idDoc]['numero'] ?? null;
                    $hallazgos[$i]['entidad_nombre']   = $datos[$idDoc]['entidad'] ?? null;
                }
            }
        }
        return $hallazgos;
    }

    /** @return array<int,array{numero:?string,entidad:?string}> indexado por id de documento */
    private function getDatosDocumentos(string $origen, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids) || !$this->esOrigenValido($origen)) {
            return [];
        }
        $info  = $this->sqlDocumentoInfo($origen);
        $tabla = $this->origenes[$origen]['tabla'];
        $ph    = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT d.id,
                       NULLIF(REPLACE({$info['numero']}, '--', ''), '') AS numero,
                       {$info['entidad']} AS entidad
                FROM {$tabla} d
                {$info['entidad_join']}
                WHERE d.id IN ($ph)";
        try {
            $rows = $this->ejecutar($sql, $ids);
        } catch (\Throwable $e) {
            return []; // un origen sin esas columnas no debe romper la auditoría
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = ['numero' => $r['numero'], 'entidad' => $r['entidad']];
        }
        return $out;
    }

    /**
     * Fragmento SQL de rango de fechas sobre $col. Añade los binds a $params.
     * $col es literal del código; las fechas van como parámetros preparados.
     */
    private function sqlRangoFecha(string $col, ?string $desde, ?string $hasta, array &$params): string
    {
        $sql = '';
        if (!empty($desde)) { $sql .= " AND {$col} >= :f_desde"; $params[':f_desde'] = $desde; }
        if (!empty($hasta)) { $sql .= " AND {$col} <= :f_hasta"; $params[':f_hasta'] = $hasta; }
        return $sql;
    }

    // ==================================================================
    //  DETECCIÓN DE HALLAZGOS
    // ==================================================================

    /**
     * Corre los 8 chequeos sobre el ambiente activo de la empresa y devuelve
     * los hallazgos normalizados. Cada elemento tiene la forma:
     *   tipo_hallazgo, modulo_origen, id_documento, id_asiento,
     *   monto_documento, monto_asiento, diferencia, detalle, fecha_documento
     *
     * @param string|null $soloOrigen Si se indica, limita a ese modulo_origen.
     * @param string|null $fechaDesde Acota por fecha del documento/asiento (AAAA-MM-DD).
     * @param string|null $fechaHasta Idem.
     */
    public function detectarTodos(int $idEmpresa, ?string $soloOrigen = null,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $origenes = $soloOrigen !== null && $this->esOrigenValido($soloOrigen)
            ? [$soloOrigen]
            : $this->getOrigenes();

        $hallazgos = [];
        foreach ($origenes as $origen) {
            $hallazgos = array_merge($hallazgos, $this->detectarFaltantesYMonto($idEmpresa, $origen, $fechaDesde, $fechaHasta));
            $hallazgos = array_merge($hallazgos, $this->detectarHuerfanos($idEmpresa, $origen, $fechaDesde, $fechaHasta));
            $hallazgos = array_merge($hallazgos, $this->detectarEstadoIncoherente($idEmpresa, $origen, $fechaDesde, $fechaHasta));
            $hallazgos = array_merge($hallazgos, $this->detectarAmbienteIncoherente($idEmpresa, $origen, $fechaDesde, $fechaHasta));
        }
        // Chequeos sobre el asiento, no dependientes del origen (se limitan a la lista cuando hay filtro).
        $hallazgos = array_merge($hallazgos, $this->detectarDuplicados($idEmpresa, $soloOrigen, $fechaDesde, $fechaHasta));
        $hallazgos = array_merge($hallazgos, $this->detectarDescuadrados($idEmpresa, $soloOrigen, $fechaDesde, $fechaHasta));
        $hallazgos = array_merge($hallazgos, $this->detectarCabVsDetalle($idEmpresa, $soloOrigen, $fechaDesde, $fechaHasta));

        // Número de documento y nombre del cliente/proveedor, para que el listado sea legible.
        return $this->enriquecerHallazgos($hallazgos);
    }

    /**
     * Faltante (documento vigente sin asiento) y monto_no_coincide
     * (documento con asiento cuyo total_debe difiere del total del documento).
     */
    private function detectarFaltantesYMonto(int $idEmpresa, string $origen,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $cfg   = $this->origenes[$origen];
        $tabla = $cfg['tabla'];
        $total = $cfg['total'];
        $colFecha = $cfg['fecha'];
        $amb   = self::AMB;

        $params = [':id_empresa' => $idEmpresa];
        $rango  = $this->sqlRangoFecha("d.{$colFecha}", $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT d.id AS id_documento,
                       {$total} AS monto_documento,
                       a.id AS id_asiento,
                       a.total_debe AS monto_asiento,
                       d.{$colFecha} AS fecha_documento
                FROM {$tabla} d
                LEFT JOIN asientos_contables_cabecera a
                       ON a.modulo_origen = '{$origen}'
                      AND a.id_referencia_origen = d.id
                      AND a.eliminado = false
                      AND a.estado <> 'anulado'
                      AND a.id_empresa = d.id_empresa
                      AND CAST(a.tipo_ambiente AS VARCHAR(1)) = {$amb}
                WHERE d.id_empresa = :id_empresa
                  AND d.eliminado = false
                  AND CAST(d.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND {$cfg['estado_filtro']}
                  AND COALESCE({$total}, 0) > 0
                  {$rango}"
             . $this->sqlExcluirMigrados($origen, 'd.id');

        $rows = $this->ejecutar($sql, $params);

        $out = [];
        foreach ($rows as $r) {
            $montoDoc = (float) $r['monto_documento'];
            if ($r['id_asiento'] === null) {
                $out[] = $this->normalizar('faltante', $origen, (int) $r['id_documento'], null,
                    $montoDoc, null, $montoDoc,
                    "Documento vigente sin asiento contable.", $r['fecha_documento']);
                continue;
            }
            // Orígenes cuyo total no es comparable con el del asiento (nómina, cambios a costo,
            // consignaciones) solo se auditan por «faltante».
            if (empty($cfg['chequear_monto'])) {
                continue;
            }
            $montoAsiento = (float) $r['monto_asiento'];
            // Una diferencia de hasta 3 centavos es redondeo legítimo absorbido por la cuenta de
            // «Ajuste por redondeo» del asiento (mismo tope que AsientoBuilderService); solo es
            // hallazgo real cuando la diferencia supera ese margen.
            if (abs(round($montoDoc - $montoAsiento, 2)) > 0.03) {
                $out[] = $this->normalizar('monto_no_coincide', $origen, (int) $r['id_documento'], (int) $r['id_asiento'],
                    $montoDoc, $montoAsiento, round($montoDoc - $montoAsiento, 2),
                    "El total del documento no coincide con el total del asiento.", $r['fecha_documento']);
            }
        }
        return $out;
    }

    /** Asiento huérfano: referencia a un documento inexistente o eliminado. */
    private function detectarHuerfanos(int $idEmpresa, string $origen,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $tabla = $this->origenes[$origen]['tabla'];
        $amb   = self::AMB;

        $params = [':id_empresa' => $idEmpresa];
        $rango  = $this->sqlRangoFecha('a.fecha_asiento', $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT a.id AS id_asiento,
                       a.id_referencia_origen AS id_documento,
                       a.total_debe AS monto_asiento,
                       a.fecha_asiento AS fecha_documento
                FROM asientos_contables_cabecera a
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado <> 'anulado'
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND a.modulo_origen = '{$origen}'
                  AND a.id_referencia_origen IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM {$tabla} d
                                  WHERE d.id = a.id_referencia_origen
                                    AND d.eliminado = false)
                  {$rango}";

        $rows = $this->ejecutar($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('huerfano', $origen,
                $r['id_documento'] !== null ? (int) $r['id_documento'] : null,
                (int) $r['id_asiento'],
                null, (float) $r['monto_asiento'], null,
                "El asiento referencia un documento inexistente o eliminado.", $r['fecha_documento']);
        }
        return $out;
    }

    /** Documento anulado que conserva un asiento vivo (no anulado). */
    private function detectarEstadoIncoherente(int $idEmpresa, string $origen,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        if (!$this->origenes[$origen]['tiene_estado']) {
            return [];
        }
        $tabla = $this->origenes[$origen]['tabla'];
        $colFecha = $this->origenes[$origen]['fecha'];
        $amb   = self::AMB;

        $params = [':id_empresa' => $idEmpresa];
        $rango  = $this->sqlRangoFecha("d.{$colFecha}", $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT a.id AS id_asiento,
                       d.id AS id_documento,
                       a.total_debe AS monto_asiento,
                       d.{$colFecha} AS fecha_documento
                FROM asientos_contables_cabecera a
                JOIN {$tabla} d ON d.id = a.id_referencia_origen
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado <> 'anulado'
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND a.modulo_origen = '{$origen}'
                  AND d.eliminado = false
                  AND LOWER(CAST(d.estado AS VARCHAR)) IN ('anulado','anulada')
                  {$rango}";

        $rows = $this->ejecutar($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('estado_incoherente', $origen, (int) $r['id_documento'], (int) $r['id_asiento'],
                null, (float) $r['monto_asiento'], null,
                "El documento está anulado pero su asiento sigue activo.", $r['fecha_documento']);
        }
        return $out;
    }

    /**
     * Asiento cuyo tipo_ambiente no coincide con el del documento origen.
     * La incidencia se registra en el ambiente del DOCUMENTO (fuente de verdad),
     * limitándose al ambiente activo de la empresa para encajar con la corrida.
     */
    private function detectarAmbienteIncoherente(int $idEmpresa, string $origen,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $tabla = $this->origenes[$origen]['tabla'];
        $colFecha = $this->origenes[$origen]['fecha'];
        $amb   = self::AMB;

        $params = [':id_empresa' => $idEmpresa];
        $rango  = $this->sqlRangoFecha("d.{$colFecha}", $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT a.id AS id_asiento,
                       d.id AS id_documento,
                       a.total_debe AS monto_asiento,
                       CAST(a.tipo_ambiente AS VARCHAR(1)) AS amb_asiento,
                       CAST(d.tipo_ambiente AS VARCHAR(1)) AS amb_doc,
                       d.{$colFecha} AS fecha_documento
                FROM asientos_contables_cabecera a
                JOIN {$tabla} d ON d.id = a.id_referencia_origen
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado <> 'anulado'
                  AND a.modulo_origen = '{$origen}'
                  AND d.eliminado = false
                  AND CAST(d.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) <> CAST(d.tipo_ambiente AS VARCHAR(1))
                  {$rango}";

        $rows = $this->ejecutar($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('ambiente_incoherente', $origen, (int) $r['id_documento'], (int) $r['id_asiento'],
                null, (float) $r['monto_asiento'], null,
                "El ambiente del asiento ({$r['amb_asiento']}) difiere del documento ({$r['amb_doc']}).",
                $r['fecha_documento']);
        }
        return $out;
    }

    /** Más de un asiento vivo para el mismo documento (modulo_origen + id_referencia_origen). */
    private function detectarDuplicados(int $idEmpresa, ?string $soloOrigen,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $amb = self::AMB;
        $filtroOrigen = '';
        if ($soloOrigen !== null && $this->esOrigenValido($soloOrigen)) {
            $filtroOrigen = " AND modulo_origen = '{$soloOrigen}'";
        }

        $params = [':id_empresa' => $idEmpresa];
        $rango  = $this->sqlRangoFecha('fecha_asiento', $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT modulo_origen,
                       id_referencia_origen AS id_documento,
                       COUNT(*) AS n,
                       MIN(fecha_asiento) AS fecha_documento
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND estado <> 'anulado'
                  AND CAST(tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND modulo_origen IS NOT NULL
                  AND modulo_origen <> 'manual'
                  AND id_referencia_origen IS NOT NULL
                  {$filtroOrigen}
                  {$rango}
                GROUP BY modulo_origen, id_referencia_origen
                HAVING COUNT(*) > 1";

        $rows = $this->ejecutar($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('duplicado', (string) $r['modulo_origen'], (int) $r['id_documento'], null,
                null, null, null,
                "Existen {$r['n']} asientos para el mismo documento.", $r['fecha_documento']);
        }
        return $out;
    }

    /** Cabecera descuadrada: total_debe <> total_haber. */
    private function detectarDescuadrados(int $idEmpresa, ?string $soloOrigen,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $amb = self::AMB;
        $filtroOrigen = '';
        if ($soloOrigen !== null && $this->esOrigenValido($soloOrigen)) {
            $filtroOrigen = " AND modulo_origen = '{$soloOrigen}'";
        }

        $params = [':id_empresa' => $idEmpresa];
        $rango  = $this->sqlRangoFecha('fecha_asiento', $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT id AS id_asiento,
                       modulo_origen,
                       id_referencia_origen AS id_documento,
                       total_debe, total_haber,
                       fecha_asiento AS fecha_documento
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND estado <> 'anulado'
                  AND CAST(tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND ROUND(total_debe, 2) <> ROUND(total_haber, 2)
                  {$filtroOrigen}
                  {$rango}";

        $rows = $this->ejecutar($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('descuadrado', (string) ($r['modulo_origen'] ?: 'manual'),
                $r['id_documento'] !== null ? (int) $r['id_documento'] : null,
                (int) $r['id_asiento'],
                (float) $r['total_debe'], (float) $r['total_haber'],
                round((float) $r['total_debe'] - (float) $r['total_haber'], 2),
                "El asiento no cuadra: debe ({$r['total_debe']}) ≠ haber ({$r['total_haber']}).",
                $r['fecha_documento']);
        }
        return $out;
    }

    /** Suma del detalle distinta de los totales de la cabecera. */
    private function detectarCabVsDetalle(int $idEmpresa, ?string $soloOrigen,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $amb = self::AMB;
        $filtroOrigen = '';
        if ($soloOrigen !== null && $this->esOrigenValido($soloOrigen)) {
            $filtroOrigen = " AND a.modulo_origen = '{$soloOrigen}'";
        }

        $params = [':id_empresa' => $idEmpresa];
        $rango  = $this->sqlRangoFecha('a.fecha_asiento', $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT a.id AS id_asiento,
                       a.modulo_origen,
                       a.id_referencia_origen AS id_documento,
                       a.total_debe, a.total_haber,
                       COALESCE(SUM(ad.debe), 0)  AS sum_debe,
                       COALESCE(SUM(ad.haber), 0) AS sum_haber,
                       a.fecha_asiento AS fecha_documento
                FROM asientos_contables_cabecera a
                LEFT JOIN asientos_contables_detalle ad
                       ON ad.id_asiento = a.id AND ad.eliminado = false
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado <> 'anulado'
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  {$filtroOrigen}
                  {$rango}
                GROUP BY a.id, a.modulo_origen, a.id_referencia_origen, a.total_debe, a.total_haber, a.fecha_asiento
                HAVING ROUND(a.total_debe, 2)  <> ROUND(COALESCE(SUM(ad.debe), 0), 2)
                    OR ROUND(a.total_haber, 2) <> ROUND(COALESCE(SUM(ad.haber), 0), 2)";

        $rows = $this->ejecutar($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('cab_vs_detalle', (string) ($r['modulo_origen'] ?: 'manual'),
                $r['id_documento'] !== null ? (int) $r['id_documento'] : null,
                (int) $r['id_asiento'],
                (float) $r['total_debe'], (float) $r['sum_debe'],
                round((float) $r['total_debe'] - (float) $r['sum_debe'], 2),
                "La cabecera no coincide con la suma del detalle (debe {$r['total_debe']} vs {$r['sum_debe']}).",
                $r['fecha_documento']);
        }
        return $out;
    }

    /** Normaliza un hallazgo al formato uniforme usado por el Service. */
    private function normalizar(string $tipo, string $origen, ?int $idDoc, ?int $idAsiento,
        ?float $montoDoc, ?float $montoAsiento, ?float $diferencia, string $detalle, ?string $fecha): array
    {
        return [
            'tipo_hallazgo'    => $tipo,
            'modulo_origen'    => $origen,
            'id_documento'     => $idDoc,
            'id_asiento'       => $idAsiento,
            'monto_documento'  => $montoDoc,
            'monto_asiento'    => $montoAsiento,
            'diferencia'       => $diferencia,
            'detalle'          => $detalle,
            'fecha_documento'  => $fecha,
            // Los completa enriquecerHallazgos() antes de persistir.
            'documento_numero' => null,
            'entidad_nombre'   => null,
        ];
    }

    // ==================================================================
    //  PERSISTENCIA DE INCIDENCIAS (upsert manual preservando revisión)
    // ==================================================================

    /** Clave lógica de una incidencia (debe coincidir con uq_aci_clave_logica). */
    public function claveLogica(array $h): string
    {
        return $h['tipo_hallazgo'] . '|' . $h['modulo_origen'] . '|'
            . ((int) ($h['id_documento'] ?? 0)) . '|' . ((int) ($h['id_asiento'] ?? 0));
    }

    /**
     * Incidencias abiertas (no resueltas) del ambiente activo, indexadas por
     * clave lógica → id. Sirve al Service para diferenciar contra la detección.
     */
    public function getIncidenciasAbiertas(int $idEmpresa, string $ambiente,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        // Si la corrida se acota por fechas, solo se consideran las incidencias de ese
        // rango: las de fuera no se detectan en esta pasada y NO deben darse por resueltas.
        $params = [':id_empresa' => $idEmpresa, ':amb' => $ambiente];
        $rango  = $this->sqlRangoFecha('fecha_documento', $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT id, tipo_hallazgo, modulo_origen,
                       COALESCE(id_documento, 0) AS id_documento,
                       COALESCE(id_asiento, 0)   AS id_asiento
                FROM auditoria_contable_incidencias
                WHERE id_empresa = :id_empresa
                  AND tipo_ambiente = :amb
                  AND eliminado = false
                  AND estado_revision <> 'resuelta'
                  {$rango}";
        $rows = $this->ejecutar($sql, $params);
        $map = [];
        foreach ($rows as $r) {
            $clave = $r['tipo_hallazgo'] . '|' . $r['modulo_origen'] . '|'
                . (int) $r['id_documento'] . '|' . (int) $r['id_asiento'];
            $map[$clave] = (int) $r['id'];
        }
        return $map;
    }

    /**
     * Inserta o actualiza una incidencia. Si ya existe una viva con la misma
     * clave lógica, actualiza montos/detalle y re-sella detectado_at, pero
     * PRESERVA estado_revision y la nota del usuario.
     */
    public function upsertIncidencia(int $idEmpresa, string $ambiente, array $h, int $idUsuario): void
    {
        $existenteId = $this->buscarIdPorClave($idEmpresa, $ambiente, $h);

        if ($existenteId !== null) {
            $sql = "UPDATE auditoria_contable_incidencias
                    SET monto_documento = :md, monto_asiento = :ma, diferencia = :dif,
                        detalle = :det, fecha_documento = :fec,
                        documento_numero = :num, entidad_nombre = :ent,
                        detectado_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP,
                        updated_by = :uid, estado = 'activo'
                    WHERE id = :id";
            $st = $this->db->prepare($sql);
            $st->execute([
                ':md'  => $h['monto_documento'], ':ma' => $h['monto_asiento'], ':dif' => $h['diferencia'],
                ':det' => $h['detalle'], ':fec' => $h['fecha_documento'],
                ':num' => $h['documento_numero'] ?? null, ':ent' => $h['entidad_nombre'] ?? null,
                ':uid' => $idUsuario, ':id' => $existenteId,
            ]);
            return;
        }

        $sql = "INSERT INTO auditoria_contable_incidencias
                    (id_empresa, tipo_ambiente, tipo_hallazgo, modulo_origen,
                     id_documento, id_asiento, monto_documento, monto_asiento, diferencia,
                     detalle, fecha_documento, documento_numero, entidad_nombre,
                     estado_revision, detectado_at,
                     created_at, updated_at, created_by, updated_by)
                VALUES
                    (:emp, :amb, :tipo, :origen,
                     :iddoc, :idas, :md, :ma, :dif,
                     :det, :fec, :num, :ent,
                     'pendiente', CURRENT_TIMESTAMP,
                     CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :uid, :uid)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp' => $idEmpresa, ':amb' => $ambiente, ':tipo' => $h['tipo_hallazgo'], ':origen' => $h['modulo_origen'],
            ':iddoc' => $h['id_documento'], ':idas' => $h['id_asiento'],
            ':md' => $h['monto_documento'], ':ma' => $h['monto_asiento'], ':dif' => $h['diferencia'],
            ':det' => $h['detalle'], ':fec' => $h['fecha_documento'],
            ':num' => $h['documento_numero'] ?? null, ':ent' => $h['entidad_nombre'] ?? null,
            ':uid' => $idUsuario,
        ]);
    }

    private function buscarIdPorClave(int $idEmpresa, string $ambiente, array $h): ?int
    {
        $sql = "SELECT id FROM auditoria_contable_incidencias
                WHERE id_empresa = :emp AND tipo_ambiente = :amb AND eliminado = false
                  AND tipo_hallazgo = :tipo AND modulo_origen = :origen
                  AND COALESCE(id_documento, 0) = :iddoc
                  AND COALESCE(id_asiento, 0)   = :idas
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp' => $idEmpresa, ':amb' => $ambiente,
            ':tipo' => $h['tipo_hallazgo'], ':origen' => $h['modulo_origen'],
            ':iddoc' => (int) ($h['id_documento'] ?? 0), ':idas' => (int) ($h['id_asiento'] ?? 0),
        ]);
        $id = $st->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** Marca como resueltas las incidencias cuyos ids ya no fueron detectados. */
    public function marcarResueltas(array $ids, int $idUsuario): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE auditoria_contable_incidencias
                SET estado_revision = 'resuelta', estado = 'resuelto',
                    updated_at = CURRENT_TIMESTAMP, updated_by = ?
                WHERE id IN ($ph) AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([$idUsuario], $ids));
        return $st->rowCount();
    }

    // ==================================================================
    //  LECTURA PARA LA VISTA
    // ==================================================================

    /** Conteo de incidencias abiertas por tipo de hallazgo (para las tarjetas-resumen). */
    public function getResumenPorTipo(int $idEmpresa, string $ambiente): array
    {
        $sql = "SELECT tipo_hallazgo, COUNT(*) AS n
                FROM auditoria_contable_incidencias
                WHERE id_empresa = :emp AND tipo_ambiente = :amb
                  AND eliminado = false AND estado_revision <> 'resuelta'
                GROUP BY tipo_hallazgo";
        $rows = $this->ejecutar($sql, [':emp' => $idEmpresa, ':amb' => $ambiente]);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['tipo_hallazgo']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Completa número de documento y nombre de entidad en incidencias que aún no los tienen
     * (las ya resueltas no se vuelven a detectar, así que no se enriquecen solas).
     * Se ejecuta al correr la auditoría. Devuelve cuántas actualizó.
     */
    public function backfillDatosDocumento(int $idEmpresa, string $ambiente): int
    {
        $sql = "SELECT id, modulo_origen, id_documento
                FROM auditoria_contable_incidencias
                WHERE id_empresa = :emp AND tipo_ambiente = :amb AND eliminado = false
                  AND id_documento IS NOT NULL AND documento_numero IS NULL";
        $pendientes = $this->ejecutar($sql, [':emp' => $idEmpresa, ':amb' => $ambiente]);
        if (empty($pendientes)) {
            return 0;
        }

        $porOrigen = [];
        foreach ($pendientes as $p) {
            $porOrigen[(string) $p['modulo_origen']][(int) $p['id_documento']][] = (int) $p['id'];
        }

        $st = $this->db->prepare(
            "UPDATE auditoria_contable_incidencias
             SET documento_numero = :num, entidad_nombre = :ent, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $n = 0;
        foreach ($porOrigen as $origen => $mapIds) {
            if (!$this->esOrigenValido($origen)) {
                continue;
            }
            $datos = $this->getDatosDocumentos($origen, array_keys($mapIds));
            foreach ($mapIds as $idDoc => $idsIncidencia) {
                if (!isset($datos[$idDoc])) {
                    continue;
                }
                foreach ($idsIncidencia as $idInc) {
                    $st->execute([
                        ':num' => $datos[$idDoc]['numero'],
                        ':ent' => $datos[$idDoc]['entidad'],
                        ':id'  => $idInc,
                    ]);
                    $n++;
                }
            }
        }
        return $n;
    }

    /** Cuenta incidencias por estado ('pendientes' | 'corregidas') para las pestañas. */
    public function contarPorVista(int $idEmpresa, string $ambiente, string $vista,
        ?string $fechaDesde = null, ?string $fechaHasta = null): int
    {
        $params = [':emp' => $idEmpresa, ':amb' => $ambiente];
        $cond = $vista === 'corregidas' ? "= 'resuelta'" : "<> 'resuelta'";
        $rango = $this->sqlRangoFecha('fecha_documento', $fechaDesde, $fechaHasta, $params);

        $sql = "SELECT COUNT(*) AS n FROM auditoria_contable_incidencias
                WHERE id_empresa = :emp AND tipo_ambiente = :amb AND eliminado = false
                  AND estado_revision {$cond} {$rango}";
        return (int) ($this->ejecutar($sql, $params)[0]['n'] ?? 0);
    }

    /**
     * Listado paginado de incidencias con buscador (FiltrosBusqueda) y ordenamiento.
     * Filtra por id_empresa + tipo_ambiente activo (y registros propios si aplica).
     */
    /**
     * @param string $vista 'pendientes' (default): lo que falta por corregir;
     *                      'corregidas': el histórico ya resuelto; 'todas': ambas.
     */
    public function getListado(int $idEmpresa, string $ambiente, string $buscar = '',
        int $page = 1, int $perPage = 20, string $ordenCol = 'detectado_at',
        string $ordenDir = 'DESC', ?int $idUsuarioFiltro = null,
        ?string $fechaDesde = null, ?string $fechaHasta = null, string $vista = 'pendientes'): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa, ':amb' => $ambiente];

        $where = $this->getBaseWhere($idEmpresa, 'i', $idUsuarioFiltro)
               . " AND i.tipo_ambiente = :amb"
               . $this->sqlRangoFecha('i.fecha_documento', $fechaDesde, $fechaHasta, $params);

        // Una incidencia corregida deja de aparecer en el listado principal y pasa a «Corregidas».
        if ($vista === 'pendientes') {
            $where .= " AND i.estado_revision <> 'resuelta'";
        } elseif ($vista === 'corregidas') {
            $where .= " AND i.estado_revision = 'resuelta'";
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (i.detalle ILIKE :buscar OR i.modulo_origen ILIKE :buscar
                          OR i.entidad_nombre ILIKE :buscar OR i.documento_numero ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => [
                'detalle'   => 'i.detalle',
                'cliente'   => 'i.entidad_nombre',
                'proveedor' => 'i.entidad_nombre',
                'nombre'    => 'i.entidad_nombre',
                'numero'    => 'i.documento_numero',
                'nro'       => 'i.documento_numero',
            ],
            'exacto'   => [
                'tipo'   => 'i.tipo_hallazgo', 'tipo_hallazgo' => 'i.tipo_hallazgo',
                'origen' => 'i.modulo_origen', 'modulo' => 'i.modulo_origen',
                'revision' => 'i.estado_revision', 'estado' => 'i.estado_revision',
            ],
            'fecha'    => ['fecha' => 'i.fecha_documento', 'detectado' => 'i.detectado_at'],
            'numerico' => ['diferencia' => 'i.diferencia', 'documento' => 'i.id_documento', 'asiento' => 'i.id_asiento'],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM auditoria_contable_incidencias i $where";
        $total = (int) $this->ejecutar($sqlCount, $params)[0]['count'];

        $allowed = ['detectado_at', 'tipo_hallazgo', 'modulo_origen', 'id_documento', 'id_asiento',
                    'diferencia', 'fecha_documento', 'estado_revision', 'documento_numero',
                    'entidad_nombre', 'revisado_at'];
        if (!in_array($ordenCol, $allowed, true)) {
            $ordenCol = 'detectado_at';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        // documento_numero y entidad_nombre se guardan al detectar (ver enriquecerHallazgos).
        $sql = "SELECT i.*, u.nombre AS revisado_por_nombre
                FROM auditoria_contable_incidencias i
                LEFT JOIN usuarios u ON i.revisado_por = u.id
                $where
                ORDER BY i.$ordenCol $ordenDir
                LIMIT $perPage OFFSET $offset";

        return ['rows' => $this->ejecutar($sql, $params), 'total' => $total];
    }

    public function getIncidenciaPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM auditoria_contable_incidencias
                WHERE id = :id AND id_empresa = :emp AND eliminado = false";
        $rows = $this->ejecutar($sql, [':id' => $id, ':emp' => $idEmpresa]);
        return $rows[0] ?? null;
    }

    /** Cambia el estado de revisión de una incidencia (revisada/justificada). */
    public function actualizarRevision(int $id, int $idEmpresa, string $estadoRevision, ?string $nota, int $idUsuario): bool
    {
        $sql = "UPDATE auditoria_contable_incidencias
                SET estado_revision = :rev, nota_revision = :nota,
                    revisado_por = :uid, revisado_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                WHERE id = :id AND id_empresa = :emp AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':rev' => $estadoRevision, ':nota' => $nota, ':uid' => $idUsuario,
            ':id' => $id, ':emp' => $idEmpresa,
        ]);
    }

    // ==================================================================
    //  CORRIDAS (historial)
    // ==================================================================

    public function registrarCorrida(array $c, int $idUsuario): int
    {
        $sql = "INSERT INTO auditoria_contable_corridas
                    (id_empresa, tipo_ambiente, tipo_corrida, modulo_origen, fecha_desde, fecha_hasta,
                     total_documentos, total_detectadas, total_anulados, total_regenerados, total_omitidos,
                     estado, mensaje, ejecutado_at, created_at, updated_at, created_by, updated_by)
                VALUES
                    (:emp, :amb, :tipo, :origen, :fd, :fh,
                     :tdoc, :tdet, :tanu, :treg, :tomi,
                     :estado, :msg, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :uid, :uid)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp' => $c['id_empresa'], ':amb' => $c['tipo_ambiente'], ':tipo' => $c['tipo_corrida'],
            ':origen' => $c['modulo_origen'] ?? null, ':fd' => $c['fecha_desde'] ?? null, ':fh' => $c['fecha_hasta'] ?? null,
            ':tdoc' => $c['total_documentos'] ?? 0, ':tdet' => $c['total_detectadas'] ?? 0,
            ':tanu' => $c['total_anulados'] ?? 0, ':treg' => $c['total_regenerados'] ?? 0, ':tomi' => $c['total_omitidos'] ?? 0,
            ':estado' => $c['estado'] ?? 'ok', ':msg' => $c['mensaje'] ?? null, ':uid' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function getCorridas(int $idEmpresa, string $ambiente, int $limit = 50): array
    {
        $sql = "SELECT c.*, u.nombre AS ejecutado_por
                FROM auditoria_contable_corridas c
                LEFT JOIN usuarios u ON c.created_by = u.id
                WHERE c.id_empresa = :emp AND c.tipo_ambiente = :amb AND c.eliminado = false
                ORDER BY c.ejecutado_at DESC
                LIMIT " . (int) $limit;
        return $this->ejecutar($sql, [':emp' => $idEmpresa, ':amb' => $ambiente]);
    }

    // ==================================================================
    //  REGENERACIÓN MASIVA (helpers; la orquestación va en el Service)
    // ==================================================================

    /**
     * Asientos vivos de un origen en el ambiente activo, opcionalmente acotados
     * por rango de fecha_asiento. Devuelve id, id_referencia_origen y fecha.
     */
    public function getAsientosDeOrigen(int $idEmpresa, string $origen, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $amb = self::AMB;
        $params = [':id_empresa' => $idEmpresa];
        $rango = '';
        if ($fechaDesde !== null) { $rango .= " AND fecha_asiento >= :fd"; $params[':fd'] = $fechaDesde; }
        if ($fechaHasta !== null) { $rango .= " AND fecha_asiento <= :fh"; $params[':fh'] = $fechaHasta; }

        // Los documentos migrados desde MySQL nunca se regeneran: su contabilidad
        // viene del histórico migrado (mismo criterio que SincronizadorAsientosService).
        $sql = "SELECT id, id_referencia_origen, fecha_asiento
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND modulo_origen = '{$origen}'
                  AND CAST(tipo_ambiente AS VARCHAR(1)) = {$amb}
                  {$rango}"
             . $this->sqlExcluirMigrados($origen, 'id_referencia_origen');
        return $this->ejecutar($sql, $params);
    }

    /**
     * Asientos vivos asociados a un documento concreto (para resolver duplicados:
     * el usuario ve la lista y elige cuál anular).
     */
    public function getAsientosDeDocumento(int $idEmpresa, string $origen, int $idDocumento): array
    {
        if (!$this->esOrigenValido($origen)) {
            return [];
        }
        $amb = self::AMB;
        $sql = "SELECT id, numero_comprobante, tipo_comprobante, fecha_asiento,
                       total_debe, total_haber, estado, concepto, created_at, created_by
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND estado <> 'anulado'
                  AND modulo_origen = '{$origen}'
                  AND id_referencia_origen = :iddoc
                  AND CAST(tipo_ambiente AS VARCHAR(1)) = {$amb}
                ORDER BY id ASC";
        return $this->ejecutar($sql, [':id_empresa' => $idEmpresa, ':iddoc' => $idDocumento]);
    }

    /**
     * ¿La fecha cae dentro de un período contable CERRADO (status = 0)?
     * Se usa como salvaguarda antes de anular/regenerar.
     */
    public function fechaEnPeriodoCerrado(int $idEmpresa, string $fecha): bool
    {
        $sql = "SELECT 1 FROM periodos_contables
                WHERE id_empresa = :emp AND eliminado = false AND status = 0
                  AND :fec BETWEEN fecha_inicial AND fecha_final
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':fec' => $fecha]);
        return $st->fetchColumn() !== false;
    }

    /** Anula lógicamente un asiento (cabecera y detalle). */
    public function anularAsiento(int $idAsiento, int $idEmpresa, int $idUsuario): void
    {
        $sqlCab = "UPDATE asientos_contables_cabecera
                   SET eliminado = true, estado = 'anulado',
                       deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid,
                       updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                   WHERE id = :id AND id_empresa = :emp AND eliminado = false";
        $st = $this->db->prepare($sqlCab);
        $st->execute([':uid' => $idUsuario, ':id' => $idAsiento, ':emp' => $idEmpresa]);

        $sqlDet = "UPDATE asientos_contables_detalle
                   SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid,
                       updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                   WHERE id_asiento = :id AND eliminado = false";
        $st = $this->db->prepare($sqlDet);
        $st->execute([':uid' => $idUsuario, ':id' => $idAsiento]);
    }

    /**
     * Corrige el ambiente de un asiento heredándolo del documento origen
     * (la fuente de verdad). Solo afecta asientos vivos. Devuelve filas afectadas.
     */
    public function corregirAmbienteAsiento(int $idAsiento, string $origen, int $idEmpresa, int $idUsuario): int
    {
        $tabla = $this->getTablaOrigen($origen);
        if ($tabla === null) {
            return 0;
        }
        $sql = "UPDATE asientos_contables_cabecera a
                SET tipo_ambiente = CAST(d.tipo_ambiente AS VARCHAR(1)),
                    updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                FROM {$tabla} d
                WHERE a.id = :id AND a.id_empresa = :emp AND a.eliminado = false
                  AND d.id = a.id_referencia_origen AND d.eliminado = false
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) <> CAST(d.tipo_ambiente AS VARCHAR(1))";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $idUsuario, ':id' => $idAsiento, ':emp' => $idEmpresa]);
        return $st->rowCount();
    }

    /** Desvincula el asiento del documento (deja id_asiento_contable en NULL). */
    public function desvincularDocumento(string $origen, int $idDocumento, int $idEmpresa): void
    {
        $tabla = $this->getTablaOrigen($origen);
        // Algunas cabeceras (rol_cabecera, consignaciones_facturas) no tienen id_asiento_contable:
        // el vínculo vive solo en el asiento, así que no hay nada que desvincular.
        if ($tabla === null || empty($this->origenes[$origen]['tiene_id_asiento'])) {
            return;
        }
        $sql = "UPDATE {$tabla} SET id_asiento_contable = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :emp";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idDocumento, ':emp' => $idEmpresa]);
    }
}
