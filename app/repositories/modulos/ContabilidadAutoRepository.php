<?php
/**
 * Acceso a datos de la generación automática de asientos contables.
 *
 * Tres responsabilidades, todas sobre la empresa activa:
 *
 *   1. FIRMA DE CONFIGURACIÓN — ¿esta empresa tiene cuentas configuradas para
 *      este módulo, y cuáles? Devuelve un hash. Si no hay ninguna cuenta,
 *      devuelve null y el servicio no genera nada (la compuerta que pidió el
 *      usuario: "consulta si hay configuración contable para el módulo").
 *   2. FALLOS — qué documentos no se pudieron contabilizar, para no reintentarlos
 *      en cada carga de página mientras la configuración siga igual.
 *   3. ESTADO — cuándo corrió cada módulo por última vez (throttle) y el candado
 *      que impide dos pasadas simultáneas sobre los mismos documentos.
 *
 * Ver database/migrations/20260821_contabilidad_auto.sql
 */

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ContabilidadAutoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('contabilidad_auto_estado');
    }

    public function getConnection(): PDO
    {
        return $this->db;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Firma de la configuración contable
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Hash de las cuentas contables configuradas para un módulo, o null si no
     * hay ninguna.
     *
     * El hash cambia cuando el usuario agrega, quita o corrige una cuenta; eso
     * es lo que habilita el reintento de los documentos que habían fallado. Se
     * calcula sobre las MISMAS fuentes que lee AsientoBuilderService al armar el
     * asiento (asientos_programados por concepto o por referencia, más la cuenta
     * propia del módulo cuando existe), para que "hay configuración" signifique
     * exactamente lo mismo aquí y allá.
     *
     * @param array $definicion Entrada de config/contabilidad_modulos.php.
     */
    public function firmaConfiguracion(int $idEmpresa, array $definicion): ?string
    {
        $piezas = [];

        foreach ($this->filasAsientosProgramados($idEmpresa, $definicion) as $f) {
            $piezas[] = 'ap:' . $f['clave'] . ':' . $f['id_cuenta'];
        }

        foreach ((array) ($definicion['tablas'] ?? []) as $fuente) {
            foreach ($this->filasTablaPropia($idEmpresa, $fuente) as $f) {
                $piezas[] = $fuente['tabla'] . ':' . $f['clave'] . ':' . $f['id_cuenta'];
            }
        }

        if ($piezas === []) {
            return null; // sin ninguna cuenta configurada: no hay nada que generar
        }

        // Conceptos de respaldo: no abren la compuerta (ya está abierta si llegamos
        // aquí), pero entran en la firma para que corregir una cuenta ahí también
        // dispare el reintento de lo que había fallado.
        $respaldo = (array) ($definicion['conceptos_firma'] ?? []);
        if ($respaldo !== []) {
            foreach ($this->filasAsientosProgramados($idEmpresa, ['conceptos' => $respaldo]) as $f) {
                $piezas[] = 'fb:' . $f['clave'] . ':' . $f['id_cuenta'];
            }
        }

        sort($piezas);
        return hash('sha256', implode('|', $piezas));
    }

    /**
     * Cuentas configuradas en asientos_programados, sea por concepto
     * (asientos_tipo.tipo_asiento) o por tipo de referencia.
     *
     * @return array<int,array{clave:string,id_cuenta:int}>
     */
    private function filasAsientosProgramados(int $idEmpresa, array $definicion): array
    {
        $conceptos   = array_values(array_filter((array) ($definicion['conceptos'] ?? [])));
        $referencias = array_values(array_filter((array) ($definicion['referencias'] ?? [])));

        if ($conceptos === [] && $referencias === []) {
            return [];
        }

        $params = [':id_empresa' => $idEmpresa];
        $ramas  = [];

        if ($conceptos !== []) {
            $ph = [];
            foreach ($conceptos as $i => $c) {
                $ph[] = ":concepto_{$i}";
                $params[":concepto_{$i}"] = $c;
            }
            $ramas[] = 'at.tipo_asiento IN (' . implode(', ', $ph) . ')';
        }

        if ($referencias !== []) {
            $ph = [];
            foreach ($referencias as $i => $r) {
                $ph[] = ":referencia_{$i}";
                $params[":referencia_{$i}"] = $r;
            }
            $ramas[] = 'ap.tipo_referencia IN (' . implode(', ', $ph) . ')';
        }

        $sql = "SELECT COALESCE(at.tipo_asiento, ap.tipo_referencia) || '#'
                       || COALESCE(ap.id_asiento_tipo, 0) || '#'
                       || COALESCE(ap.id_referencia, 0) AS clave,
                       ap.id_cuenta
                FROM asientos_programados ap
                LEFT JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo AND at.eliminado = false
                WHERE ap.id_empresa = :id_empresa
                  AND ap.eliminado = false
                  AND ap.id_cuenta IS NOT NULL
                  AND (" . implode(' OR ', $ramas) . ")";

        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // Migración pendiente en esta base: se comporta como "sin configurar".
            return [];
        }
    }

    /**
     * Cuentas que el usuario asignó desde el propio módulo (formas de cobro/pago
     * y conceptos de Ingresos/Egresos), no desde Configuración Contable.
     *
     * @param array{tabla:string,col_cuenta:string,filtro?:string} $fuente
     * @return array<int,array{clave:string,id_cuenta:int}>
     */
    private function filasTablaPropia(int $idEmpresa, array $fuente): array
    {
        // La tabla y la columna vienen del archivo de configuración del sistema,
        // nunca de la petición; aun así se restringe el formato para que no
        // pueda colarse SQL por un archivo mal editado.
        $tabla  = (string) ($fuente['tabla'] ?? '');
        $columna = (string) ($fuente['col_cuenta'] ?? '');
        if (!preg_match('/^[a-z0-9_]+$/', $tabla) || !preg_match('/^[a-z0-9_]+$/', $columna)) {
            return [];
        }

        $filtro = trim((string) ($fuente['filtro'] ?? ''));
        $filtroSql = $filtro !== '' ? " AND ({$filtro})" : '';

        $sql = "SELECT id::text AS clave, {$columna} AS id_cuenta
                FROM {$tabla}
                WHERE id_empresa = :id_empresa
                  AND eliminado = FALSE
                  AND {$columna} IS NOT NULL{$filtroSql}";

        try {
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Documentos pendientes y fallos
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ids de documentos sin asiento de un módulo, excluyendo los que ya fallaron
     * con la configuración vigente y acotado al tope de la pasada.
     *
     * El SQL de detección es el del trabajo en SincronizadorAsientosService: no
     * se duplica aquí, se recibe tal cual y se envuelve.
     *
     * @return int[]
     */
    public function idsPendientes(string $sqlDeteccion, array $params, int $idEmpresa, string $moduloClave, ?string $hashConfig, int $limite): array
    {
        $sql = "SELECT _p.id
                FROM ({$sqlDeteccion}) AS _p
                WHERE NOT EXISTS (
                        SELECT 1 FROM contabilidad_auto_fallos f
                        WHERE f.id_empresa = ?
                          AND f.modulo_clave = ?
                          AND f.id_documento = _p.id
                          AND f.eliminado = FALSE
                          AND f.hash_config IS NOT DISTINCT FROM ?
                      )
                ORDER BY _p.id ASC
                LIMIT {$limite}";

        $st = $this->db->prepare($sql);
        $st->execute(array_merge(array_values($params), [$idEmpresa, $moduloClave, $hashConfig]));

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Cuáles de los documentos que se intentaron siguen sin asiento.
     *
     * Varios services devuelven en silencio (sin excepción) cuando el asiento
     * queda vacío por falta de reglas, así que "no lanzó error" no alcanza para
     * darlo por generado — mismo criterio que usa SincronizadorAsientosService.
     *
     * @param int[] $ids
     * @return int[]
     */
    public function idsSinAsiento(string $tabla, string $colAsiento, array $ids): array
    {
        if ($ids === [] || !preg_match('/^[a-z0-9_]+$/', $tabla) || !preg_match('/^[a-z0-9_]+$/', $colAsiento)) {
            return [];
        }

        $ph = implode(', ', array_fill(0, count($ids), '?'));
        try {
            $st = $this->db->prepare("SELECT id FROM {$tabla} WHERE id IN ({$ph}) AND {$colAsiento} IS NULL");
            $st->execute(array_map('intval', $ids));
            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Deja registrado que un documento no se pudo contabilizar, con la
     * configuración vigente al momento del fallo. Mientras esa firma no cambie,
     * idsPendientes() lo salta.
     */
    public function registrarFallo(int $idEmpresa, string $moduloClave, int $idDocumento, string $motivo, ?string $hashConfig, int $idUsuario): void
    {
        $sql = "INSERT INTO contabilidad_auto_fallos
                    (id_empresa, modulo_clave, id_documento, motivo, intentos, ultimo_intento, hash_config, created_by, updated_by)
                VALUES (:emp, :clave, :doc, :motivo, 1, NOW(), :hash, :usuario, :usuario)
                ON CONFLICT (id_empresa, modulo_clave, id_documento) DO UPDATE
                SET motivo         = EXCLUDED.motivo,
                    hash_config    = EXCLUDED.hash_config,
                    intentos       = contabilidad_auto_fallos.intentos + 1,
                    ultimo_intento = NOW(),
                    updated_at     = NOW(),
                    updated_by     = EXCLUDED.updated_by,
                    eliminado      = FALSE,
                    deleted_at     = NULL,
                    deleted_by     = NULL";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp'     => $idEmpresa,
            ':clave'   => $moduloClave,
            ':doc'     => $idDocumento,
            ':motivo'  => mb_substr($motivo, 0, 1000),
            ':hash'    => $hashConfig,
            ':usuario' => $idUsuario,
        ]);
    }

    /** Un documento que sí se generó deja de estar en la lista de fallos. */
    public function limpiarFallos(int $idEmpresa, string $moduloClave, array $ids, int $idUsuario): void
    {
        if ($ids === []) {
            return;
        }
        $ph = implode(', ', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare(
            "UPDATE contabilidad_auto_fallos
                SET eliminado = TRUE, deleted_at = NOW(), deleted_by = ?, updated_at = NOW(), updated_by = ?
              WHERE id_empresa = ? AND modulo_clave = ? AND eliminado = FALSE AND id_documento IN ({$ph})"
        );
        $st->execute(array_merge([$idUsuario, $idUsuario, $idEmpresa, $moduloClave], array_map('intval', $ids)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Estado y candado
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{ultima_corrida:?string,ultimo_hash_config:?string,quedan_pendientes:bool}|null */
    public function getEstado(int $idEmpresa, string $moduloClave): ?array
    {
        $st = $this->db->prepare(
            "SELECT ultima_corrida, ultimo_hash_config, quedan_pendientes
               FROM contabilidad_auto_estado
              WHERE id_empresa = :emp AND modulo_clave = :clave AND eliminado = FALSE
              LIMIT 1"
        );
        $st->execute([':emp' => $idEmpresa, ':clave' => $moduloClave]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function guardarEstado(int $idEmpresa, string $moduloClave, ?string $hashConfig, int $generados, int $fallidos, bool $quedanPendientes, int $idUsuario): void
    {
        $sql = "INSERT INTO contabilidad_auto_estado
                    (id_empresa, modulo_clave, ultima_corrida, ultimo_hash_config, generados, fallidos, quedan_pendientes, created_by, updated_by)
                VALUES (:emp, :clave, NOW(), :hash, :gen, :fall, :quedan, :usuario, :usuario)
                ON CONFLICT (id_empresa, modulo_clave) DO UPDATE
                SET ultima_corrida     = NOW(),
                    ultimo_hash_config = EXCLUDED.ultimo_hash_config,
                    generados          = EXCLUDED.generados,
                    fallidos           = EXCLUDED.fallidos,
                    quedan_pendientes  = EXCLUDED.quedan_pendientes,
                    updated_at         = NOW(),
                    updated_by         = EXCLUDED.updated_by,
                    eliminado          = FALSE";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp'     => $idEmpresa,
            ':clave'   => $moduloClave,
            ':hash'    => $hashConfig,
            ':gen'     => $generados,
            ':fall'    => $fallidos,
            ':quedan'  => $quedanPendientes ? 'true' : 'false',
            ':usuario' => $idUsuario,
        ]);
    }

    /**
     * Candado por empresa+módulo para que dos usuarios que abren la misma
     * pantalla a la vez no procesen los mismos documentos (regla de concurrencia
     * del sistema: todo "leer → calcular → escribir" va bloqueado).
     *
     * Es pg_try_advisory_lock (no la variante _xact) a propósito: la generación
     * abre y cierra sus propias transacciones documento por documento, así que
     * el candado tiene que sobrevivir a esos COMMIT. Por eso hay que soltarlo
     * explícitamente con liberarCandado() — el servicio lo hace en su finally.
     *
     * @return bool false si otro proceso ya lo tiene: en ese caso no se espera,
     *              simplemente no se hace nada (la próxima carga reintenta).
     */
    public function intentarCandado(int $idEmpresa, string $moduloClave): bool
    {
        $st = $this->db->prepare("SELECT pg_try_advisory_lock(hashtext(:clave))");
        $st->execute([':clave' => "contabilidad_auto:{$idEmpresa}:{$moduloClave}"]);
        return (bool) $st->fetchColumn();
    }

    public function liberarCandado(int $idEmpresa, string $moduloClave): void
    {
        try {
            $st = $this->db->prepare("SELECT pg_advisory_unlock(hashtext(:clave))");
            $st->execute([':clave' => "contabilidad_auto:{$idEmpresa}:{$moduloClave}"]);
        } catch (\Throwable $e) {
            // La conexión se cierra al terminar la petición y el candado cae con ella.
        }
    }
}
