<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;
use PDO;

class NotaCreditoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('notas_credito_cabecera');
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE nc.id_empresa = :id_empresa AND nc.eliminado = false AND nc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        // Parser de filtros
        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            $where .= " AND (nc.secuencial ILIKE :buscar
                          OR c.nombre ILIKE :buscar
                          OR c.identificacion ILIKE :buscar
                          OR nc.num_doc_modificado ILIKE :buscar
                          OR nc.motivo ILIKE :buscar)";
            $params[':buscar'] = "%$textoLibre%";
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'ci'             => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => 'nc.secuencial',
                'nro'            => 'nc.secuencial',
                'doc_modificado' => 'nc.num_doc_modificado',
                'motivo'         => 'nc.motivo',
                'usuario'        => 'u.nombre',
            ],
            'exacto' => [
                'estado' => 'nc.estado',
            ],
            'fecha' => [
                'fecha'         => 'nc.fecha_emision',
                'fecha_emision' => 'nc.fecha_emision',
            ],
            'numerico' => [
                'monto'    => 'nc.importe_total',
                'total'    => 'nc.importe_total',
                'subtotal' => 'nc.total_sin_impuestos',
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND nc.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        // Conteo total
        $sqlCount = "SELECT COUNT(*) FROM notas_credito_cabecera nc
                     LEFT JOIN clientes c ON nc.id_cliente = c.id
                     LEFT JOIN usuarios u ON nc.id_usuario = u.id
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Listado paginado
        $sql = "SELECT nc.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.email as cliente_email,
                       u.nombre as usuario_nombre,
                       e.tipo_ambiente, e.tipo_emision
                FROM notas_credito_cabecera nc
                LEFT JOIN clientes c ON nc.id_cliente = c.id
                LEFT JOIN usuarios u ON nc.id_usuario = u.id
                LEFT JOIN empresas e ON e.id = nc.id_empresa
                $where
                ORDER BY nc.$ordenCol $ordenDir
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT nc.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.direccion as cliente_direccion, c.telefono as cliente_telefono,
                       c.email as cliente_email
                FROM notas_credito_cabecera nc
                LEFT JOIN clientes c ON nc.id_cliente = c.id
                WHERE nc.id = ? AND nc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getDetalles(int $idNC): array
    {
        $sql = "SELECT * FROM notas_credito_detalle WHERE id_nota_credito = ? ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idNC]);
        return $st->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM notas_credito_detalle_impuestos WHERE id_nota_credito_detalle = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idDetalle]);
        return $st->fetchAll();
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO notas_credito_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    cod_doc_modificado, num_doc_modificado, fecha_emision_docs_sustento, motivo,
                    total_sin_impuestos, total_descuento, importe_total, estado, observaciones,
                    created_by, updated_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                ) RETURNING id";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_empresa'],
            $data['id_establecimiento'],
            $data['id_punto_emision'],
            $data['id_cliente'],
            $data['id_usuario'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['clave_acceso'] ?? null,
            $data['cod_doc_modificado'] ?? '01',
            $data['num_doc_modificado'],
            $data['fecha_emision_docs_sustento'],
            $data['motivo'],
            $data['total_sin_impuestos'],
            $data['total_descuento'],
            $data['importe_total'],
            $data['estado'] ?? 'borrador',
            $data['observaciones'] ?? null,
            $data['id_usuario'],
            $data['id_usuario']
        ]);

        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE notas_credito_cabecera SET
                    id_establecimiento = ?, id_punto_emision = ?, id_cliente = ?,
                    fecha_emision = ?, establecimiento = ?, punto_emision = ?, secuencial = ?,
                    cod_doc_modificado = ?, num_doc_modificado = ?, fecha_emision_docs_sustento = ?, motivo = ?,
                    total_sin_impuestos = ?, total_descuento = ?, importe_total = ?,
                    observaciones = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ?
                WHERE id = ?";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_establecimiento'],
            $data['id_punto_emision'],
            $data['id_cliente'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['cod_doc_modificado'] ?? '01',
            $data['num_doc_modificado'],
            $data['fecha_emision_docs_sustento'],
            $data['motivo'],
            $data['total_sin_impuestos'],
            $data['total_descuento'],
            $data['importe_total'],
            $data['observaciones'] ?? null,
            $data['id_usuario'],
            $id
        ]);
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO notas_credito_detalle (
                    id_nota_credito, id_producto, codigo_principal, codigo_auxiliar,
                    descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_nota_credito'],
            $data['id_producto'] ?? null,
            $data['codigo_principal'] ?? null,
            $data['codigo_auxiliar'] ?? null,
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'] ?? 0,
            $data['precio_total_sin_impuesto']
        ]);

        return (int) $st->fetchColumn();
    }

    public function deleteDetalles(int $idNC): void
    {
        $sql = "DELETE FROM notas_credito_detalle WHERE id_nota_credito = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idNC]);
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO notas_credito_detalle_impuestos (
                    id_nota_credito_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_nota_credito_detalle'],
            $data['codigo_impuesto'],
            $data['codigo_porcentaje'],
            $data['tarifa'],
            $data['base_imponible'],
            $data['valor']
        ]);
    }

    public function getFormasPago(): array
    {
        $sql = "SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        $sql = "SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnidadesMedida(): array
    {
        $sql = "SELECT * FROM unidades_medida WHERE eliminado = false AND status = true ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEstado(int $id, string $estado): void
    {
        $sql = "UPDATE notas_credito_cabecera SET estado = ? WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$estado, $id]);
    }

    public function updateAutorizacion(int $id, string $numero, string $fecha): void
    {
        $sql = "UPDATE notas_credito_cabecera SET numero_autorizacion = ?, fecha_autorizacion = ?, estado = 'autorizado' WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$numero, $fecha, $id]);
    }

    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $sql = "UPDATE notas_credito_cabecera SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = ? WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $id]);
    }

    public function getPorDocumentoModificado(string $numeroFactura, int $idEmpresa): array
    {
        $sql = "SELECT nc.*, u.nombre as usuario_nombre
                FROM notas_credito_cabecera nc
                LEFT JOIN usuarios u ON nc.id_usuario = u.id
                WHERE nc.num_doc_modificado = ? 
                  AND nc.id_empresa = ? 
                  AND nc.eliminado = false
                ORDER BY nc.fecha_emision DESC";
        $st = $this->db->prepare($sql);
        $st->execute([$numeroFactura, $idEmpresa]);
        return $st->fetchAll();
    }

    public function getSumaImporteNotasCredito(string $numeroFactura, int $idEmpresa, ?int $exceptoId = null): float
    {
        $sql = "SELECT SUM(importe_total) FROM notas_credito_cabecera 
                WHERE num_doc_modificado = ? 
                  AND id_empresa = ? 
                  AND eliminado = false
                  AND estado != 'anulado'";
        $params = [$numeroFactura, $idEmpresa];
        if ($exceptoId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptoId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (float) $st->fetchColumn();
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    public function updateDetalleXml(int $id, string $xml): void
    {
        try {
            $this->db->exec("ALTER TABLE notas_credito_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;");
        } catch (\Throwable) {}

        $st = $this->db->prepare(
            "UPDATE notas_credito_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }
}
