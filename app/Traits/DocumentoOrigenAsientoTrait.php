<?php

declare(strict_types=1);

namespace App\Traits;

use App\core\Database;
use App\Helpers\DocumentoOrigenAsiento;
use PDO;

/**
 * Arma el JOIN que recupera, desde el documento que originó el asiento, el tercero y el número
 * de documento que la línea (asientos_contables_detalle) no tiene guardados.
 *
 * Lo usan los reportes que listan movimientos contables (Mayores, el mayor auxiliar de Estados
 * Financieros). Quien lo use expone el alias `doc` con tipo_entidad, id_entidad y
 * numero_documento, y debe resolver sus columnas en cascada, por ejemplo:
 *
 *   COALESCE(ad.tipo_entidad, doc.tipo_entidad)
 *   COALESCE(NULLIF(ad.documento_referencia, ''), NULLIF(doc.numero_documento, ''), ac.numero_comprobante)
 *
 * Requiere en la consulta los alias `ad` (línea), `ac` (cabecera del asiento) y que la cabecera
 * traiga modulo_origen / id_referencia_origen / id_empresa.
 */
trait DocumentoOrigenAsientoTrait
{
    /** JOIN sin resolución: una fila de NULLs para que las referencias a `doc` sigan siendo válidas. */
    protected static string $docOrigenNeutro = "LEFT JOIN LATERAL (
                    SELECT NULL::varchar AS tipo_entidad, NULL::bigint AS id_entidad,
                           NULL::varchar AS numero_documento, NULL::varchar AS modulo_doc, NULL::bigint AS id_doc
                ) doc ON true";

    /** Cache por request: tablas de documento existentes y enlaces con índice. */
    private static ?array $docOrigenTablas = null;
    private static ?array $docOrigenIndexados = null;

    /**
     * JOIN que trae del documento origen el tercero y el número de documento de la línea que no
     * los tiene guardados (mapa en App\Helpers\DocumentoOrigenAsiento).
     *
     * Además del dato que falte, expone `modulo_doc` / `id_doc`: con eso el reporte enlaza la
     * columna "Documento Ref." al modal que muestra el documento (DocumentoOrigenService).
     *
     * Cada rama se cierra por `modulo_origen`, de modo que un asiento consulta una sola tabla:
     *   - Asientos de módulo: por `id_referencia_origen`, es decir por clave primaria.
     *   - Asientos migrados: por el enlace inverso `documento.id_asiento_contable = asiento.id`.
     *     Esa rama se agrega SOLO si la columna tiene índice, porque sin él cada línea migrada
     *     obligaría a recorrer la tabla entera. Los crea
     *     database/migrations/20260725_backfill_asientos_detalle_tercero.sql, así que la
     *     resolución de migrados se activa sola cuando esa migración esté desplegada.
     *
     * Las expresiones vienen del mapa del helper —constantes del código, no entrada del
     * usuario—; lo variable (empresa, fechas, filtros) va siempre por parámetros preparados.
     */
    protected function sqlDocumentoOrigenAsiento(): string
    {
        $db = Database::getConnection();
        $existentes = $this->tablasDocumentoOrigen($db);
        $indexadas = $this->enlacesAsientoIndexados($db);
        $ramas = [];

        foreach (DocumentoOrigenAsiento::DOCUMENTOS as $modulo => $d) {
            if (!in_array($d['tabla'], $existentes, true)) {
                continue;
            }

            $select = "SELECT ({$d['tipo']})::varchar AS tipo_entidad,
                              ({$d['entidad']})::bigint AS id_entidad,
                              ({$d['numero']})::varchar AS numero_documento,
                              " . $db->quote((string) $modulo) . "::varchar AS modulo_doc,
                              t.id::bigint AS id_doc
                       FROM {$d['tabla']} t
                       WHERE t.id_empresa = ac.id_empresa";

            $ramas[] = $select . " AND ac.modulo_origen = " . $db->quote((string) $modulo)
                . " AND t.id = ac.id_referencia_origen";

            if (in_array($d['tabla'] . '.' . $d['col_asiento'], $indexadas, true)) {
                $ramas[] = $select . " AND ac.modulo_origen = 'migracion' AND t.{$d['col_asiento']} = ac.id";
            }
        }

        if (empty($ramas)) {
            return self::$docOrigenNeutro;
        }

        // Sin ORDER BY a propósito: por el filtro de modulo_origen a lo sumo una rama devuelve
        // fila, y así el LIMIT 1 corta sin evaluar las demás.
        return "LEFT JOIN LATERAL (
                    SELECT q.tipo_entidad, q.id_entidad, q.numero_documento, q.modulo_doc, q.id_doc
                    FROM (" . implode("\n UNION ALL \n", $ramas) . ") q
                    LIMIT 1
                ) doc ON true";
    }

    /**
     * Tablas de documento del mapa que existen realmente en esta base. Un módulo cuya migración
     * SQL todavía no se desplegó no debe romper el reporte.
     */
    private function tablasDocumentoOrigen(PDO $db): array
    {
        if (self::$docOrigenTablas !== null) {
            return self::$docOrigenTablas;
        }

        $tablas = array_values(array_unique(DocumentoOrigenAsiento::tablas()));
        $ph = implode(',', array_fill(0, count($tablas), '?'));
        $st = $db->prepare("SELECT table_name FROM information_schema.tables
                            WHERE table_schema = 'public' AND table_name IN ($ph)");
        $st->execute($tablas);

        self::$docOrigenTablas = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return self::$docOrigenTablas;
    }

    /** Enlaces documento → asiento que tienen índice, como "tabla.columna". */
    private function enlacesAsientoIndexados(PDO $db): array
    {
        if (self::$docOrigenIndexados !== null) {
            return self::$docOrigenIndexados;
        }

        // indkey[0]: solo sirve el índice que tiene la columna del enlace como primera clave.
        $st = $db->query("SELECT c.relname || '.' || a.attname
                          FROM pg_index i
                          JOIN pg_class c ON c.oid = i.indrelid
                          JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = i.indkey[0]
                          WHERE a.attname IN ('id_asiento_contable', 'id_asiento_reingreso')");

        self::$docOrigenIndexados = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return self::$docOrigenIndexados;
    }
}
