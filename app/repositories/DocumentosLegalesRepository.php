<?php
/**
 * Repositorio de documentos legales (acuerdo de datos y contrato de uso).
 *
 * documentos_legales         -> configuración GLOBAL versionada (sin id_empresa).
 * empresas_documentos_envios -> envío/aceptación por empresa.
 *
 * Único punto de acceso a BD de esta funcionalidad. Sin lógica de negocio.
 */

declare(strict_types=1);

namespace App\repositories;

use App\repositories\BaseRepository;
use PDO;

class DocumentosLegalesRepository extends BaseRepository
{
    public const TIPOS = ['acuerdo_datos', 'contrato_uso'];

    public function __construct()
    {
        parent::__construct('documentos_legales');
    }

    // ─── Textos legales (global) ────────────────────────────────────────────

    /** Versión vigente de un tipo ('acuerdo_datos' | 'contrato_uso'). */
    public function getVigente(string $tipo): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM documentos_legales
              WHERE tipo = :tipo AND vigente = TRUE AND eliminado = FALSE
              LIMIT 1"
        );
        $st->execute([':tipo' => $tipo]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Las versiones vigentes de ambos tipos, indexadas por tipo. */
    public function getVigentes(): array
    {
        $st = $this->db->query(
            "SELECT * FROM documentos_legales
              WHERE vigente = TRUE AND eliminado = FALSE"
        );
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['tipo']] = $r;
        }

        return $out;
    }

    public function getPorId(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM documentos_legales WHERE id = :id AND eliminado = FALSE LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Historial de versiones de un tipo (más reciente primero). */
    public function getHistorial(string $tipo): array
    {
        $st = $this->db->prepare(
            "SELECT id, tipo, version, titulo, vigente, created_at, created_by
               FROM documentos_legales
              WHERE tipo = :tipo AND eliminado = FALSE
              ORDER BY version DESC"
        );
        $st->execute([':tipo' => $tipo]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Publica una NUEVA versión del texto: desactiva la vigente e inserta la nueva.
     * El versionado protege la evidencia: los envíos anteriores siguen apuntando
     * a la versión que realmente se envió.
     *
     * @return int id de la nueva versión
     */
    public function publicarNuevaVersion(string $tipo, string $titulo, string $contenido, int $idUsuario): int
    {
        $stMax = $this->db->prepare("SELECT COALESCE(MAX(version), 0) AS v FROM documentos_legales WHERE tipo = :tipo");
        $stMax->execute([':tipo' => $tipo]);
        $nueva = (int) ($stMax->fetch(PDO::FETCH_ASSOC)['v'] ?? 0) + 1;

        $stOff = $this->db->prepare(
            "UPDATE documentos_legales
                SET vigente = FALSE, updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE tipo = :tipo AND vigente = TRUE AND eliminado = FALSE"
        );
        $stOff->execute([':tipo' => $tipo, ':u' => $idUsuario]);

        $st = $this->db->prepare(
            "INSERT INTO documentos_legales (tipo, version, titulo, contenido, vigente, created_by)
             VALUES (:tipo, :version, :titulo, :contenido, TRUE, :u)"
        );
        $st->execute([
            ':tipo'      => $tipo,
            ':version'   => $nueva,
            ':titulo'    => $titulo,
            ':contenido' => $contenido,
            ':u'         => $idUsuario,
        ]);

        return $this->lastInsertId('documentos_legales_id_seq');
    }

    // ─── Envíos / aceptaciones por empresa ──────────────────────────────────

    public function registrarEnvio(
        int $idEmpresa,
        ?int $idAcuerdo,
        ?int $idContrato,
        string $correo,
        string $token,
        int $idUsuario
    ): int {
        $st = $this->db->prepare(
            "INSERT INTO empresas_documentos_envios
                (id_empresa, id_acuerdo, id_contrato, correo_destino, token, estado, enviado_by, created_by)
             VALUES (:e, :a, :c, :correo, :token, 'enviado', :u, :u)"
        );
        $st->execute([
            ':e'      => $idEmpresa,
            ':a'      => $idAcuerdo,
            ':c'      => $idContrato,
            ':correo' => $correo,
            ':token'  => $token,
            ':u'      => $idUsuario,
        ]);

        return $this->lastInsertId('empresas_documentos_envios_id_seq');
    }

    /** Envío por token, con datos de la empresa y de los documentos enviados. */
    public function getEnvioPorToken(string $token): ?array
    {
        $st = $this->db->prepare(
            "SELECT ev.*, e.nombre AS empresa_nombre, e.ruc AS empresa_ruc,
                    e.direccion AS empresa_direccion, e.nom_rep_legal AS empresa_representante
               FROM empresas_documentos_envios ev
               INNER JOIN empresas e ON e.id = ev.id_empresa
              WHERE ev.token = :t AND ev.eliminado = FALSE
              LIMIT 1"
        );
        $st->execute([':t' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function marcarAceptado(int $idEnvio, string $nombre, string $identificacion, string $ip, string $userAgent): bool
    {
        $st = $this->db->prepare(
            "UPDATE empresas_documentos_envios
                SET estado = 'aceptado',
                    aceptado_at = CURRENT_TIMESTAMP,
                    aceptado_nombre = :n,
                    aceptado_identificacion = :ci,
                    aceptado_ip = :ip,
                    aceptado_user_agent = :ua,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND estado <> 'aceptado' AND eliminado = FALSE"
        );
        $st->execute([
            ':n'  => $nombre,
            ':ci' => $identificacion,
            ':ip' => substr($ip, 0, 64),
            ':ua' => $userAgent,
            ':id' => $idEnvio,
        ]);

        return $st->rowCount() > 0;
    }

    /** Historial de envíos de una empresa (más reciente primero). */
    public function getEnviosDeEmpresa(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT ev.*, da.version AS acuerdo_version, dc.version AS contrato_version
               FROM empresas_documentos_envios ev
               LEFT JOIN documentos_legales da ON da.id = ev.id_acuerdo
               LEFT JOIN documentos_legales dc ON dc.id = ev.id_contrato
              WHERE ev.id_empresa = :e AND ev.eliminado = FALSE
              ORDER BY ev.enviado_at DESC"
        );
        $st->execute([':e' => $idEmpresa]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estado resumido por empresa para pintar el listado de empresas-sistema.
     * @return array<int, array{estado:string, enviado_at:?string, aceptado_at:?string}>
     */
    public function getEstadoPorEmpresa(): array
    {
        $sql = "SELECT DISTINCT ON (id_empresa) id_empresa, estado, enviado_at, aceptado_at
                  FROM empresas_documentos_envios
                 WHERE eliminado = FALSE
                 ORDER BY id_empresa, enviado_at DESC";
        $out = [];
        foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_empresa']] = [
                'estado'      => $r['estado'],
                'enviado_at'  => $r['enviado_at'],
                'aceptado_at' => $r['aceptado_at'],
            ];
        }

        return $out;
    }

    /** Datos de la empresa necesarios para armar los documentos. */
    public function getEmpresaParaDocumento(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, nombre_comercial, ruc, direccion, telefono, mail, nom_rep_legal
               FROM empresas WHERE id = :id LIMIT 1"
        );
        $st->execute([':id' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
