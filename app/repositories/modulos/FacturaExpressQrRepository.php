<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class FacturaExpressQrRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('factura_express_plantillas');
    }

    // ═══════════════════════════════════════════════════════
    // PLANTILLAS
    // ═══════════════════════════════════════════════════════

    public function getListadoPlantillas(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $colsValidas = ['nombre', 'activo', 'requiere_aprobacion', 'created_at'];
        if (!in_array($ordenCol, $colsValidas, true)) $ordenCol = 'created_at';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = 'WHERE p.id_empresa = :id_empresa AND p.eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $where .= ' AND (p.nombre ILIKE :buscar OR p.descripcion ILIKE :buscar)';
            $params[':buscar'] = "%{$buscar}%";
        }

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM factura_express_plantillas p $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $offset = ($page - 1) * $perPage;
            $sql = "SELECT p.*,
                           (SELECT COUNT(*) FROM factura_express_items
                            WHERE id_plantilla = p.id AND eliminado = false AND activo = true) AS total_items,
                           (SELECT COUNT(*) FROM factura_express_solicitudes
                            WHERE id_plantilla = p.id AND eliminado = false) AS total_solicitudes,
                           (SELECT COUNT(*) FROM factura_express_solicitudes
                            WHERE id_plantilla = p.id AND estado = 'pendiente' AND eliminado = false) AS solicitudes_pendientes
                    FROM factura_express_plantillas p
                    $where
                    ORDER BY p.{$ordenCol} {$ordenDir}
                    LIMIT $perPage OFFSET $offset";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPlantillaByToken(string $token): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.*, e.nombre AS empresa_nombre, e.ruc AS empresa_ruc
             FROM factura_express_plantillas p
             JOIN empresas e ON e.id = p.id_empresa
             WHERE p.token = :token AND p.eliminado = false AND p.activo = true"
        );
        $st->execute([':token' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertPlantilla(array $data): int
    {
        $st = $this->db->prepare(
            "INSERT INTO factura_express_plantillas
             (id_empresa, nombre, descripcion, token, activo, requiere_aprobacion,
              mensaje_bienvenida, mensaje_gracias, max_solicitudes_hora, campos_config,
              id_establecimiento, id_punto_emision, forma_pago,
              created_at, created_by)
             VALUES
             (:id_empresa, :nombre, :descripcion, :token, :activo, :requiere_aprobacion,
              :mensaje_bienvenida, :mensaje_gracias, :max_solicitudes_hora, :campos_config,
              :id_establecimiento, :id_punto_emision, :forma_pago,
              NOW(), :created_by)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa'           => $data['id_empresa'],
            ':nombre'               => $data['nombre'],
            ':descripcion'          => $data['descripcion'] ?? null,
            ':token'                => $data['token'],
            ':activo'               => $data['activo'] ? 'true' : 'false',
            ':requiere_aprobacion'  => $data['requiere_aprobacion'] ? 'true' : 'false',
            ':mensaje_bienvenida'   => $data['mensaje_bienvenida'] ?? null,
            ':mensaje_gracias'      => $data['mensaje_gracias'] ?? null,
            ':max_solicitudes_hora' => (int) ($data['max_solicitudes_hora'] ?? 10),
            ':campos_config'        => $data['campos_config'] ?? '{"nombre":true,"identificacion":true,"correo":true,"telefono":false}',
            ':id_establecimiento'   => !empty($data['id_establecimiento']) ? (int)$data['id_establecimiento'] : null,
            ':id_punto_emision'     => !empty($data['id_punto_emision'])   ? (int)$data['id_punto_emision']   : null,
            ':forma_pago'           => $data['forma_pago'] ?? '20',
            ':created_by'           => $data['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function updatePlantilla(int $id, int $idEmpresa, array $data): void
    {
        $st = $this->db->prepare(
            "UPDATE factura_express_plantillas SET
             nombre = :nombre, descripcion = :descripcion,
             activo = :activo, requiere_aprobacion = :requiere_aprobacion,
             mensaje_bienvenida = :mensaje_bienvenida, mensaje_gracias = :mensaje_gracias,
             max_solicitudes_hora = :max_solicitudes_hora, campos_config = :campos_config,
             id_establecimiento = :id_establecimiento, id_punto_emision = :id_punto_emision,
             forma_pago = :forma_pago,
             updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([
            ':nombre'               => $data['nombre'],
            ':descripcion'          => $data['descripcion'] ?? null,
            ':activo'               => $data['activo'] ? 'true' : 'false',
            ':requiere_aprobacion'  => $data['requiere_aprobacion'] ? 'true' : 'false',
            ':mensaje_bienvenida'   => $data['mensaje_bienvenida'] ?? null,
            ':mensaje_gracias'      => $data['mensaje_gracias'] ?? null,
            ':max_solicitudes_hora' => (int) ($data['max_solicitudes_hora'] ?? 10),
            ':campos_config'        => $data['campos_config'] ?? '{"nombre":true,"identificacion":true,"correo":true,"telefono":false}',
            ':id_establecimiento'   => !empty($data['id_establecimiento']) ? (int)$data['id_establecimiento'] : null,
            ':id_punto_emision'     => !empty($data['id_punto_emision'])   ? (int)$data['id_punto_emision']   : null,
            ':forma_pago'           => $data['forma_pago'] ?? '20',
            ':updated_by'           => $data['id_usuario'],
            ':id'                   => $id,
            ':id_empresa'           => $idEmpresa,
        ]);
    }

    public function deletePlantilla(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE factura_express_plantillas
             SET eliminado = true, deleted_at = NOW(), deleted_by = :uid
             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function tokenExiste(string $token): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM factura_express_plantillas WHERE token = :token");
        $st->execute([':token' => $token]);
        return (bool) $st->fetchColumn();
    }

    // ═══════════════════════════════════════════════════════
    // ÍTEMS DE PLANTILLA
    // ═══════════════════════════════════════════════════════

    public function getItemsPorPlantilla(int $idPlantilla): array
    {
        $st = $this->db->prepare(
            "SELECT i.*, p.nombre AS nombre_producto
             FROM factura_express_items i
             LEFT JOIN productos p ON p.id = i.id_producto
             WHERE i.id_plantilla = :id_plantilla AND i.eliminado = false
             ORDER BY i.orden ASC, i.id ASC"
        );
        $st->execute([':id_plantilla' => $idPlantilla]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItemsActivosPorPlantilla(int $idPlantilla): array
    {
        $st = $this->db->prepare(
            "SELECT i.*, p.nombre AS nombre_producto
             FROM factura_express_items i
             LEFT JOIN productos p ON p.id = i.id_producto
             WHERE i.id_plantilla = :id_plantilla AND i.eliminado = false AND i.activo = true
             ORDER BY i.orden ASC, i.id ASC"
        );
        $st->execute([':id_plantilla' => $idPlantilla]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertItem(array $data): int
    {
        $st = $this->db->prepare(
            "INSERT INTO factura_express_items
             (id_plantilla, id_empresa, id_producto, descripcion, precio_unitario,
              porcentaje_iva, cantidad_default, cantidad_editable, seleccionado_default,
              orden, activo, created_at, created_by)
             VALUES
             (:id_plantilla, :id_empresa, :id_producto, :descripcion, :precio_unitario,
              :porcentaje_iva, :cantidad_default, :cantidad_editable, :seleccionado_default,
              :orden, :activo, NOW(), :created_by)
             RETURNING id"
        );
        $st->execute([
            ':id_plantilla'        => $data['id_plantilla'],
            ':id_empresa'          => $data['id_empresa'],
            ':id_producto'         => $data['id_producto'] ?: null,
            ':descripcion'         => $data['descripcion'],
            ':precio_unitario'     => (float) $data['precio_unitario'],
            ':porcentaje_iva'      => (float) ($data['porcentaje_iva'] ?? 0),
            ':cantidad_default'    => (float) ($data['cantidad_default'] ?? 1),
            ':cantidad_editable'   => isset($data['cantidad_editable']) && $data['cantidad_editable'] ? 'true' : 'false',
            ':seleccionado_default'=> isset($data['seleccionado_default']) && $data['seleccionado_default'] ? 'true' : 'false',
            ':orden'               => (int) ($data['orden'] ?? 0),
            ':activo'              => 'true',
            ':created_by'          => $data['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function deleteItemsPorPlantilla(int $idPlantilla, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE factura_express_items
             SET eliminado = true, deleted_at = NOW(), deleted_by = :uid
             WHERE id_plantilla = :id_plantilla AND eliminado = false"
        );
        $st->execute([':uid' => $idUsuario, ':id_plantilla' => $idPlantilla]);
    }

    // ═══════════════════════════════════════════════════════
    // SOLICITUDES
    // ═══════════════════════════════════════════════════════

    public function getListadoSolicitudes(int $idEmpresa, string $buscar, string $estadoFiltro, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $colsValidas = ['nombre_cliente', 'identificacion', 'estado', 'monto_total', 'created_at'];
        if (!in_array($ordenCol, $colsValidas, true)) $ordenCol = 'created_at';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = 'WHERE s.id_empresa = :id_empresa AND s.eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        if ($estadoFiltro !== '' && $estadoFiltro !== 'todos') {
            $where .= ' AND s.estado = :estado';
            $params[':estado'] = $estadoFiltro;
        }
        if ($buscar !== '') {
            $where .= ' AND (s.nombre_cliente ILIKE :buscar OR s.identificacion ILIKE :buscar OR s.correo_cliente ILIKE :buscar)';
            $params[':buscar'] = "%{$buscar}%";
        }

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM factura_express_solicitudes s $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $offset = ($page - 1) * $perPage;
            $sql = "SELECT s.*, p.nombre AS nombre_plantilla
                    FROM factura_express_solicitudes s
                    LEFT JOIN factura_express_plantillas p ON p.id = s.id_plantilla
                    $where
                    ORDER BY s.{$ordenCol} {$ordenDir}
                    LIMIT $perPage OFFSET $offset";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function getSolicitudById(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT s.*, p.nombre AS nombre_plantilla, p.requiere_aprobacion
             FROM factura_express_solicitudes s
             LEFT JOIN factura_express_plantillas p ON p.id = s.id_plantilla
             WHERE s.id = :id AND s.id_empresa = :id_empresa AND s.eliminado = false"
        );
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getSolicitudByTokenCliente(string $token): ?array
    {
        $st = $this->db->prepare(
            "SELECT s.*, p.nombre AS nombre_plantilla, p.mensaje_gracias,
                    e.razon_social AS empresa_nombre
             FROM factura_express_solicitudes s
             LEFT JOIN factura_express_plantillas p ON p.id = s.id_plantilla
             LEFT JOIN empresas e ON e.id = s.id_empresa
             WHERE s.token_cliente = :token AND s.eliminado = false"
        );
        $st->execute([':token' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertSolicitud(array $data): int
    {
        $st = $this->db->prepare(
            "INSERT INTO factura_express_solicitudes
             (id_plantilla, id_empresa, nombre_cliente, identificacion, tipo_identificacion,
              correo_cliente, telefono_cliente, direccion_cliente, items_json, monto_total, estado,
              ip_origen, user_agent, token_cliente, created_at)
             VALUES
             (:id_plantilla, :id_empresa, :nombre_cliente, :identificacion, :tipo_identificacion,
              :correo_cliente, :telefono_cliente, :direccion_cliente, :items_json, :monto_total, 'pendiente',
              :ip_origen, :user_agent, :token_cliente, NOW())
             RETURNING id"
        );
        $st->execute([
            ':id_plantilla'        => $data['id_plantilla'],
            ':id_empresa'          => $data['id_empresa'],
            ':nombre_cliente'      => $data['nombre_cliente'],
            ':identificacion'      => $data['identificacion'],
            ':tipo_identificacion' => $data['tipo_identificacion'] ?? 'cedula',
            ':correo_cliente'      => $data['correo_cliente'] ?: null,
            ':telefono_cliente'    => $data['telefono_cliente'] ?: null,
            ':direccion_cliente'   => $data['direccion_cliente'] ?: null,
            ':items_json'          => $data['items_json'],
            ':monto_total'         => (float) $data['monto_total'],
            ':ip_origen'           => $data['ip_origen'] ?? null,
            ':user_agent'          => $data['user_agent'] ?? null,
            ':token_cliente'       => $data['token_cliente'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function updateEstadoSolicitud(int $id, string $estado, int $idUsuario, ?string $nota = null): void
    {
        $st = $this->db->prepare(
            "UPDATE factura_express_solicitudes
             SET estado = :estado, nota_aprobacion = :nota,
                 aprobado_por = :uid, aprobado_at = NOW(),
                 updated_at = NOW(), updated_by = :uid
             WHERE id = :id"
        );
        $st->execute([':estado' => $estado, ':nota' => $nota, ':uid' => $idUsuario, ':id' => $id]);
    }

    public function marcarFacturada(int $id, int $idFactura, int $idClienteSys, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE factura_express_solicitudes
             SET estado = 'facturada', id_factura = :id_factura, id_cliente_sys = :id_cliente,
                 aprobado_por = :uid, aprobado_at = NOW(),
                 updated_at = NOW(), updated_by = :uid
             WHERE id = :id"
        );
        $st->execute([':id_factura' => $idFactura, ':id_cliente' => $idClienteSys, ':uid' => $idUsuario, ':id' => $id]);
    }

    public function marcarCorreoEnviadoDueno(int $id): void
    {
        $this->db->prepare("UPDATE factura_express_solicitudes SET correo_enviado_dueno = true WHERE id = :id")
                 ->execute([':id' => $id]);
    }

    public function marcarCorreoEnviadoCliente(int $id): void
    {
        $this->db->prepare("UPDATE factura_express_solicitudes SET correo_enviado_cliente = true WHERE id = :id")
                 ->execute([':id' => $id]);
    }

    public function contarSolicitudesPorIp(string $ip, int $idPlantilla, int $ventanaMinutos = 60): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM factura_express_solicitudes
             WHERE ip_origen = :ip AND id_plantilla = :id_plantilla
               AND created_at > NOW() - INTERVAL ':minutos minutes'"
        );
        // INTERVAL no acepta parámetros bind en PostgreSQL, construimos la query segura
        $min = (int) $ventanaMinutos;
        $st  = $this->db->prepare(
            "SELECT COUNT(*) FROM factura_express_solicitudes
             WHERE ip_origen = :ip AND id_plantilla = :id_plantilla
               AND created_at > NOW() - INTERVAL '{$min} minutes'"
        );
        $st->execute([':ip' => $ip, ':id_plantilla' => $idPlantilla]);
        return (int) $st->fetchColumn();
    }
}
