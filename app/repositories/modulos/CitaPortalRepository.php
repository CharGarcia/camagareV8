<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CitaPortalRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('citas_config_portal');
    }

    // ─── CONFIG DEL PORTAL ────────────────────────────────────────────────────

    public function getConfigBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, e.nombre AS nombre_empresa, e.mail AS email_empresa,
                   e.telefono AS telefono_empresa
            FROM citas_config_portal p
            JOIN empresas e ON e.id = p.id_empresa
            WHERE p.slug = :slug AND p.activo = true
        ");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPortalStats(int $idEmpresa): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) FILTER (WHERE origen = 'portal' AND eliminado = false) AS total_portal,
                COUNT(*) FILTER (WHERE origen = 'portal' AND estado = 'pendiente' AND eliminado = false) AS pendientes,
                COUNT(*) FILTER (WHERE origen = 'portal' AND estado = 'confirmada' AND eliminado = false) AS confirmadas,
                COUNT(*) FILTER (WHERE origen = 'portal' AND estado IN ('cancelada','no_asistio') AND eliminado = false) AS canceladas,
                COUNT(*) FILTER (WHERE origen = 'portal' AND eliminado = false AND created_at >= NOW() - INTERVAL '30 days') AS ultimos_30_dias
            FROM citas
            WHERE id_empresa = :ie
        ");
        $stmt->execute([':ie' => $idEmpresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getUltimasReservasPortal(int $idEmpresa, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.fecha_inicio, c.estado, c.created_at,
                   ct.nombre AS nombre_tipo,
                   cl.nombre AS nombre_cliente, cl.identificacion,
                   ce.nombres AS ext_nombres, ce.apellidos AS ext_apellidos, ce.identificacion AS ext_identificacion
            FROM citas c
            LEFT JOIN citas_tipos ct ON ct.id = c.id_tipo_cita
            LEFT JOIN clientes    cl ON cl.id = c.id_cliente
            LEFT JOIN citas_clientes_externos ce ON ce.id = c.id_cliente_externo
            WHERE c.id_empresa = :ie AND c.origen = 'portal' AND c.eliminado = false
            ORDER BY c.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':ie',  $idEmpresa, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── CATÁLOGOS PÚBLICOS ───────────────────────────────────────────────────

    public function getTiposActivos(int $idEmpresa): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre, descripcion, duracion_minutos, precio,
                   tipo_pago, anticipo_porcentaje, color
            FROM citas_tipos
            WHERE id_empresa = :ie AND status = 1 AND eliminado = false
            ORDER BY nombre
        ");
        $stmt->execute([':ie' => $idEmpresa]);
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Adjuntar recursos_ids a cada tipo
        if (!empty($tipos)) {
            $ids = implode(',', array_map('intval', array_column($tipos, 'id')));
            $tr  = $this->db->query(
                "SELECT id_tipo, id_recurso FROM citas_tipos_recursos WHERE id_tipo IN ($ids)"
            )->fetchAll(PDO::FETCH_ASSOC);
            $mapa = [];
            foreach ($tr as $r) {
                $mapa[(int)$r['id_tipo']][] = (int)$r['id_recurso'];
            }
            foreach ($tipos as &$t) {
                $t['recursos_ids'] = $mapa[(int)$t['id']] ?? [];
            }
            unset($t);
        }

        return $tipos;
    }

    public function getRecursosActivos(int $idEmpresa): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre, tipo, descripcion
            FROM citas_recursos
            WHERE id_empresa = :ie AND status = 1 AND eliminado = false
            ORDER BY nombre
        ");
        $stmt->execute([':ie' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna solo los recursos asociados a un tipo de cita específico.
     * Si el tipo no tiene restricciones (recursos_ids vacío), retorna todos.
     */
    public function getRecursosActivosPorTipo(int $idEmpresa, int $idTipo): array
    {
        // Verificar si el tipo tiene recursos asignados
        $st = $this->db->prepare(
            "SELECT id_recurso FROM citas_tipos_recursos WHERE id_tipo = :it"
        );
        $st->execute([':it' => $idTipo]);
        $ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id_recurso'));

        if (empty($ids)) {
            // Sin restricción → todos los recursos activos
            return $this->getRecursosActivos($idEmpresa);
        }

        $inList = implode(',', $ids);
        $stmt   = $this->db->prepare("
            SELECT id, nombre, tipo, descripcion
            FROM citas_recursos
            WHERE id_empresa = :ie AND status = 1 AND eliminado = false
              AND id IN ($inList)
            ORDER BY nombre
        ");
        $stmt->execute([':ie' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── DISPONIBILIDAD ───────────────────────────────────────────────────────

    /**
     * Retorna los bloques horarios vigentes para un dia_semana dado.
     * Prioriza horarios del recurso sobre los generales.
     */
    public function getHorariosParaDia(int $idEmpresa, int $diaSemana, ?int $idRecurso): array
    {
        // Intentar horarios específicos del recurso
        if ($idRecurso !== null) {
            $stmt = $this->db->prepare("
                SELECT hora_inicio, hora_fin FROM citas_horarios
                WHERE id_empresa = :ie AND dia_semana = :dia AND id_recurso = :rec AND eliminado = false
                ORDER BY hora_inicio
            ");
            $stmt->execute([':ie' => $idEmpresa, ':dia' => $diaSemana, ':rec' => $idRecurso]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) return $rows;
        }

        // Fallback: horarios generales (sin recurso)
        $stmt = $this->db->prepare("
            SELECT hora_inicio, hora_fin FROM citas_horarios
            WHERE id_empresa = :ie AND dia_semana = :dia AND id_recurso IS NULL AND eliminado = false
            ORDER BY hora_inicio
        ");
        $stmt->execute([':ie' => $idEmpresa, ':dia' => $diaSemana]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna las citas existentes para una fecha y empresa (no canceladas, no eliminadas).
     */
    public function getCitasOcupadas(int $idEmpresa, string $fecha, ?int $idRecurso): array
    {
        $sql = "
            SELECT fecha_inicio, fecha_fin FROM citas
            WHERE id_empresa = :ie
              AND eliminado = false
              AND estado NOT IN ('cancelada', 'no_asistio')
              AND DATE(fecha_inicio AT TIME ZONE 'America/Guayaquil') = :fecha
        ";
        $params = [':ie' => $idEmpresa, ':fecha' => $fecha];

        if ($idRecurso !== null) {
            $sql .= " AND id_recurso = :rec";
            $params[':rec'] = $idRecurso;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── CLIENTES ─────────────────────────────────────────────────────────────

    public function buscarClienteEnSistema(string $identificacion, string $email, int $idEmpresa): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre, identificacion, email, telefono
            FROM clientes
            WHERE id_empresa = :ie AND eliminado = false
              AND (identificacion = :ident OR (email <> '' AND email = :email))
            LIMIT 1
        ");
        $stmt->execute([':ie' => $idEmpresa, ':ident' => $identificacion, ':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createClienteExterno(array $d): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO citas_clientes_externos
                (id_empresa, nombres, apellidos, email, telefono, identificacion, id_cliente_sistema, created_at, updated_at, eliminado)
            VALUES
                (:ie, :nombres, :apellidos, :email, :telefono, :identificacion, :id_cliente_sistema, NOW(), NOW(), false)
            RETURNING id
        ");
        $stmt->execute([
            ':ie'               => $d['id_empresa'],
            ':nombres'          => $d['nombres'],
            ':apellidos'        => $d['apellidos'] ?? '',
            ':email'            => $d['email'] ?? '',
            ':telefono'         => $d['telefono'] ?? '',
            ':identificacion'   => $d['identificacion'] ?? '',
            ':id_cliente_sistema' => $d['id_cliente_sistema'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    // ─── CREAR CITA DESDE PORTAL ──────────────────────────────────────────────

    public function createCitaPortal(array $d): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO citas
                (id_empresa, id_tipo_cita, id_recurso, id_cliente, id_cliente_externo,
                 titulo, fecha_inicio, fecha_fin, estado, notas, origen,
                 created_at, updated_at, created_by, updated_by, eliminado)
            VALUES
                (:id_empresa, :id_tipo_cita, :id_recurso, :id_cliente, :id_cliente_externo,
                 :titulo, :fecha_inicio, :fecha_fin, :estado, :notas, 'portal',
                 NOW(), NOW(), 0, 0, false)
            RETURNING id
        ");
        $stmt->execute([
            ':id_empresa'         => $d['id_empresa'],
            ':id_tipo_cita'       => $d['id_tipo_cita'] ?: null,
            ':id_recurso'         => $d['id_recurso'] ?: null,
            ':id_cliente'         => $d['id_cliente'] ?: null,
            ':id_cliente_externo' => $d['id_cliente_externo'] ?: null,
            ':titulo'             => $d['titulo'] ?: null,
            ':fecha_inicio'       => $d['fecha_inicio'],
            ':fecha_fin'          => $d['fecha_fin'],
            ':estado'             => $d['requiere_confirmacion'] ? 'pendiente' : 'confirmada',
            ':notas'              => $d['notas'] ?: null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function getCitaById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, ct.nombre AS nombre_tipo, ct.duracion_minutos, ct.color,
                   cr.nombre AS nombre_recurso,
                   cl.nombre AS nombre_cliente,
                   ce.nombres AS ext_nombres, ce.apellidos AS ext_apellidos
            FROM citas c
            LEFT JOIN citas_tipos ct ON ct.id = c.id_tipo_cita
            LEFT JOIN citas_recursos cr ON cr.id = c.id_recurso
            LEFT JOIN clientes cl ON cl.id = c.id_cliente
            LEFT JOIN citas_clientes_externos ce ON ce.id = c.id_cliente_externo
            WHERE c.id = :id AND c.eliminado = false
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
