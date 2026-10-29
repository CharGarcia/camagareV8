<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos de la cola de comprobantes emitidos entre empresas del sistema
 * (tabla sri_documentos_entre_empresas) y de su configuración por empresa receptora
 * (sri_recepcion_entre_empresas_config). Ver DocumentoEntreEmpresasService.
 */
class DocumentoEntreEmpresasRepository extends BaseRepository
{
    /**
     * Tablas de documentos emitidos de las que se puede leer el XML autorizado.
     * Whitelist: el nombre de la tabla se interpola en el SQL.
     */
    public const TABLAS_ORIGEN = [
        'factura_venta'      => 'ventas_cabecera',
        'factura_reembolso'  => 'factura_reembolso_cabecera',
        'nota_credito'       => 'notas_credito_cabecera',
        'nota_debito'        => 'nota_debito_cabecera',
        'retencion_compra'   => 'retencion_compra_cabecera',
        'liquidacion_compra' => 'liquidaciones_cabecera',
    ];

    public function __construct()
    {
        parent::__construct('sri_documentos_entre_empresas');
    }

    /** Sin la migración aplicada todo el flujo se apaga en silencio. */
    public function disponible(): bool
    {
        return $this->tablaExiste('sri_documentos_entre_empresas');
    }

    /** XML autorizado (sobre detalle_xml) y clave del documento emitido. */
    public function getDocumentoOrigen(string $tabla, int $id, int $idEmpresaEmisora): ?array
    {
        if (!in_array($tabla, self::TABLAS_ORIGEN, true)) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT id, clave_acceso, detalle_xml
               FROM {$tabla}
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresaEmisora]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getRucEmpresa(int $idEmpresa): ?string
    {
        $st = $this->db->prepare("SELECT ruc FROM empresas WHERE id = ? AND eliminado = false");
        $st->execute([$idEmpresa]);
        $ruc = $st->fetchColumn();
        return $ruc === false ? null : (string) $ruc;
    }

    /**
     * Empresa del sistema que recibe el comprobante: activa, con alguno de los RUC
     * indicados y la primera creada del RUC (menor id) cuando hay varias (establecimientos
     * que comparten RUC): registrarlo en todas duplicaría la compra en el ATS consolidado.
     * Devuelve también si tiene activada la recepción (sin fila de configuración = sí).
     *
     * @param string[] $rucs
     * @return array{id:int, ruc:string, recibir:bool}|null
     */
    public function buscarEmpresaReceptora(array $rucs): ?array
    {
        $rucs = array_values(array_unique(array_filter($rucs, static fn($r) => $r !== '')));
        if (!$rucs) {
            return null;
        }
        $ph = [];
        $params = [];
        foreach ($rucs as $i => $ruc) {
            $ph[] = ":ruc{$i}";
            $params[":ruc{$i}"] = $ruc;
        }
        $st = $this->db->prepare(
            "SELECT e.id, e.ruc, COALESCE(c.recibir, true) AS recibir
               FROM empresas e
          LEFT JOIN sri_recepcion_entre_empresas_config c
                 ON c.id_empresa = e.id AND c.eliminado = false
              WHERE e.ruc IN (" . implode(',', $ph) . ")
                AND e.estado = '1'
                AND e.eliminado = false
           ORDER BY e.id
              LIMIT 1"
        );
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id'      => (int) $row['id'],
            'ruc'     => (string) $row['ruc'],
            'recibir' => (bool) $row['recibir'],
        ];
    }

    /**
     * Usuario de la empresa receptora a cuyo nombre se registran los documentos
     * (created_by tiene FK a usuarios: 0 no sirve). El administrador asignado más
     * antiguo; si no hay, el usuario de nivel 1 más antiguo.
     */
    public function getUsuarioResponsable(int $idEmpresa): ?int
    {
        $st = $this->db->prepare(
            "SELECT u.id
               FROM empresa_asignada ea
               JOIN usuarios u ON u.id = ea.id_usuario
              WHERE ea.id_empresa = :id_empresa
                AND u.nivel IN (1, 2) AND u.estado = 1 AND u.eliminado = false
           ORDER BY u.nivel DESC, u.id
              LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Encola el comprobante; si ya estaba encolado para esa receptora no hace nada. */
    public function encolar(array $d): bool
    {
        $st = $this->db->prepare(
            "INSERT INTO sri_documentos_entre_empresas
                (id_empresa, id_empresa_emisora, tipo_comprobante, tabla_origen, id_origen,
                 clave_acceso, numero_documento, estado, created_by, updated_by)
             VALUES (:id_empresa, :emisora, :tipo, :tabla, :id_origen,
                     :clave, :numero, 'pendiente', :usr, :usr2)
             ON CONFLICT (id_empresa, clave_acceso) WHERE eliminado = FALSE DO NOTHING"
        );
        $st->execute([
            ':id_empresa' => $d['id_empresa'],
            ':emisora'    => $d['id_empresa_emisora'],
            ':tipo'       => $d['tipo_comprobante'],
            ':tabla'      => $d['tabla_origen'],
            ':id_origen'  => $d['id_origen'],
            ':clave'      => $d['clave_acceso'],
            ':numero'     => $d['numero_documento'],
            ':usr'        => $d['created_by'],
            ':usr2'       => $d['created_by'],
        ]);
        return $st->rowCount() > 0;
    }

    /**
     * Pendientes para el cron, de empresas receptoras que siguen activas. Los que
     * fallaron se reintentan cada 30 min hasta $maxIntentos (p. ej. periodo cerrado
     * que luego se reabre).
     */
    public function getPendientes(int $limite, int $maxIntentos): array
    {
        $st = $this->db->prepare(
            "SELECT q.*, e.ruc AS ruc_receptora
               FROM sri_documentos_entre_empresas q
               JOIN empresas e ON e.id = q.id_empresa
              WHERE q.eliminado = false
                AND e.estado = '1' AND e.eliminado = false
                AND (q.estado = 'pendiente'
                     OR (q.estado = 'error' AND q.intentos < :max
                         AND q.ultimo_intento < NOW() - INTERVAL '30 minutes'))
           ORDER BY q.id_empresa, q.id
              LIMIT :lim"
        );
        $st->bindValue(':max', $maxIntentos, PDO::PARAM_INT);
        $st->bindValue(':lim', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarResultado(int $id, string $estado, string $mensaje, ?int $idRegistro): void
    {
        $st = $this->db->prepare(
            "UPDATE sri_documentos_entre_empresas
                SET estado         = :estado,
                    mensaje        = :mensaje,
                    id_registro    = COALESCE(:id_registro, id_registro),
                    intentos       = intentos + 1,
                    ultimo_intento = NOW(),
                    procesado_at   = CASE WHEN :estado2 = 'error' THEN procesado_at ELSE NOW() END,
                    updated_at     = NOW()
              WHERE id = :id"
        );
        $st->execute([
            ':estado'      => $estado,
            ':estado2'     => $estado,
            ':mensaje'     => mb_substr($mensaje, 0, 2000),
            ':id_registro' => $idRegistro,
            ':id'          => $id,
        ]);
    }

    /** Recepción activada para la empresa (sin fila = sí). */
    public function getRecibir(int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT recibir FROM sri_recepcion_entre_empresas_config
              WHERE id_empresa = ? AND eliminado = false LIMIT 1"
        );
        $st->execute([$idEmpresa]);
        // fetch() y no fetchColumn(): el boolean false de la columna se confundiría
        // con el false de "no hay fila".
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? (bool) $row['recibir'] : true;
    }

    /** Fila actual de configuración (para la auditoría del cambio). */
    public function getConfig(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM sri_recepcion_entre_empresas_config
              WHERE id_empresa = ? AND eliminado = false LIMIT 1"
        );
        $st->execute([$idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function guardarRecibir(int $idEmpresa, bool $recibir, int $idUsuario): void
    {
        // Literal 'true'/'false': PDO manda el false de PHP como cadena vacía y
        // PostgreSQL lo rechaza.
        $params = [
            ':id_empresa' => $idEmpresa,
            ':recibir'    => $recibir ? 'true' : 'false',
            ':usr'        => $idUsuario,
        ];
        $st = $this->db->prepare(
            "UPDATE sri_recepcion_entre_empresas_config
                SET recibir = :recibir, updated_at = NOW(), updated_by = :usr
              WHERE id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute($params);
        if ($st->rowCount() > 0) {
            return;
        }
        $this->db->prepare(
            "INSERT INTO sri_recepcion_entre_empresas_config (id_empresa, recibir, created_by, updated_by)
             VALUES (:id_empresa, :recibir, :usr, :usr2)"
        )->execute($params + [':usr2' => $idUsuario]);
    }

    /** Resumen de los últimos recibidos por la empresa (para la tarjeta de Descargas SRI). */
    public function getResumenRecibidos(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT estado, COUNT(*) AS total
               FROM sri_documentos_entre_empresas
              " . $this->getBaseWhere($idEmpresa) . "
                AND created_at >= NOW() - INTERVAL '30 days'
           GROUP BY estado"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['estado']] = (int) $r['total'];
        }
        return $out;
    }
}
