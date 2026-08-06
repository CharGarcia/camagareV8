<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;
use PDO;

class NotaDebitoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('nota_debito_cabecera');
        try {
            $this->db->exec("ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
        } catch (\Throwable $e) {}
        try {
            $this->db->exec("ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS estado_correo VARCHAR(20) DEFAULT 'pendiente';");
        } catch (\Throwable $e) {}
        try {
            $this->db->exec("ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;");
        } catch (\Throwable $e) {}
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS nota_debito_adicional (
                id SERIAL PRIMARY KEY,
                id_nota_debito INTEGER NOT NULL,
                nombre VARCHAR(300) NOT NULL,
                valor VARCHAR(500)
            );");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_nd_adicional_nd ON nota_debito_adicional(id_nota_debito);");
        } catch (\Throwable $e) {}
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE nd.id_empresa = :id_empresa AND nd.eliminado = false AND nd.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['nd.secuencial', 'c.nombre', 'c.identificacion', 'nd.num_doc_modificado'],
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'ci'             => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => 'nd.secuencial',
                'nro'            => 'nd.secuencial',
                'doc_modificado' => 'nd.num_doc_modificado',
                'usuario'        => 'u.nombre',
            ],
            'exacto' => [
                'estado' => 'nd.estado',
            ],
            'fecha' => [
                'fecha'         => 'nd.fecha_emision',
                'fecha_emision' => 'nd.fecha_emision',
            ],
            'numerico' => [
                'monto'    => 'nd.importe_total',
                'total'    => 'nd.importe_total',
                'subtotal' => 'nd.total_sin_impuestos',
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND nd.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $sqlCount = "SELECT COUNT(*) FROM nota_debito_cabecera nd
                     LEFT JOIN clientes c ON nd.id_cliente = c.id
                     LEFT JOIN usuarios u ON nd.id_usuario = u.id
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $ordenExpr = match ($ordenCol) {
            'cliente_nombre'  => 'c.nombre',
            'cliente_ruc'     => 'c.identificacion',
            'usuario_nombre'  => 'u.nombre',
            'numero'          => 'nd.secuencial',
            'secuencial', 'fecha_emision', 'total_sin_impuestos',
            'importe_total', 'estado', 'estado_correo', 'num_doc_modificado'
                              => "nd.$ordenCol",
            default           => 'nd.fecha_emision',
        };
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT nd.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.email as cliente_email,
                       u.nombre as usuario_nombre,
                       e.tipo_ambiente, e.tipo_emision
                FROM nota_debito_cabecera nd
                LEFT JOIN clientes c ON nd.id_cliente = c.id
                LEFT JOIN usuarios u ON nd.id_usuario = u.id
                LEFT JOIN empresas e ON e.id = nd.id_empresa
                $where
                ORDER BY $ordenExpr $ordenDir" . ($perPage > 0 ? " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset : "");

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
        $sql = "SELECT nd.*, c.nombre as cliente_nombre, c.identificacion as cliente_ruc,
                       c.direccion as cliente_direccion, c.telefono as cliente_telefono,
                       c.email as cliente_email, c.tipo_id as cliente_tipo_id
                FROM nota_debito_cabecera nd
                LEFT JOIN clientes c ON nd.id_cliente = c.id
                WHERE nd.id = ? AND nd.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getMotivos(int $idND): array
    {
        $sql = "SELECT * FROM nota_debito_motivos WHERE id_nota_debito = ? ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idND]);
        return $st->fetchAll();
    }

    public function getImpuestos(int $idND): array
    {
        $sql = "SELECT * FROM nota_debito_impuestos WHERE id_nota_debito = ? ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idND]);
        return $st->fetchAll();
    }

    public function getPagos(int $idND): array
    {
        $sql = "SELECT * FROM nota_debito_pagos WHERE id_nota_debito = ? ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idND]);
        return $st->fetchAll();
    }

    // ── Información adicional ──────────────────────────────────────────────────

    public function getInfoAdicional(int $idND): array
    {
        try {
            $sql = "SELECT * FROM nota_debito_adicional WHERE id_nota_debito = ? ORDER BY id ASC";
            $st = $this->db->prepare($sql);
            $st->execute([$idND]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO nota_debito_adicional (id_nota_debito, nombre, valor) VALUES (?, ?, ?)";
        $st = $this->db->prepare($sql);
        $st->execute([$data['id_nota_debito'], $data['nombre'], $data['valor']]);
    }

    public function deleteInfoAdicional(int $idND): void
    {
        $st = $this->db->prepare("DELETE FROM nota_debito_adicional WHERE id_nota_debito = ?");
        $st->execute([$idND]);
    }

    public function updateAsientoContable(int $idNotaDebito, int $idAsiento): void
    {
        $st = $this->db->prepare("UPDATE nota_debito_cabecera SET id_asiento_contable = ? WHERE id = ?");
        $st->execute([$idAsiento, $idNotaDebito]);
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO nota_debito_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    cod_doc_modificado, num_doc_modificado, fecha_emision_docs_sustento,
                    total_sin_impuestos, importe_total, estado, observaciones,
                    created_by, updated_by, tipo_ambiente
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
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
            $data['total_sin_impuestos'],
            $data['importe_total'],
            $data['estado'] ?? 'borrador',
            $data['observaciones'] ?? null,
            $data['id_usuario'],
            $data['id_usuario'],
            $data['tipo_ambiente'] ?? '1'
        ]);

        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE nota_debito_cabecera SET
                    id_establecimiento = ?, id_punto_emision = ?, id_cliente = ?,
                    fecha_emision = ?, establecimiento = ?, punto_emision = ?, secuencial = ?,
                    cod_doc_modificado = ?, num_doc_modificado = ?, fecha_emision_docs_sustento = ?,
                    total_sin_impuestos = ?, importe_total = ?,
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
            $data['total_sin_impuestos'],
            $data['importe_total'],
            $data['observaciones'] ?? null,
            $data['id_usuario'],
            $id
        ]);
    }

    public function insertMotivo(array $data): void
    {
        $sql = "INSERT INTO nota_debito_motivos (
                    id_nota_debito, razon, valor
                ) VALUES (?, ?, ?)";

        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_nota_debito'],
            $data['razon'],
            $data['valor']
        ]);
    }

    public function deleteMotivos(int $idND): void
    {
        $sql = "DELETE FROM nota_debito_motivos WHERE id_nota_debito = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idND]);
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO nota_debito_impuestos (
                    id_nota_debito, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";

        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_nota_debito'],
            $data['codigo_impuesto'],
            $data['codigo_porcentaje'],
            $data['tarifa'],
            $data['base_imponible'],
            $data['valor']
        ]);
    }

    public function deleteImpuestos(int $idND): void
    {
        $sql = "DELETE FROM nota_debito_impuestos WHERE id_nota_debito = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idND]);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO nota_debito_pagos (
                    id_nota_debito, forma_pago, total, plazo, unidad_tiempo
                ) VALUES (?, ?, ?, ?, ?)";

        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_nota_debito'],
            $data['forma_pago'],
            $data['total'],
            $data['plazo'] ?? 0,
            $data['unidad_tiempo'] ?? 'dias'
        ]);
    }

    public function deletePagos(int $idND): void
    {
        $sql = "DELETE FROM nota_debito_pagos WHERE id_nota_debito = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idND]);
    }

    public function getFormasPago(): array
    {
        $sql = "SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        $sql = "SELECT * FROM tarifa_iva ORDER BY status DESC, porcentaje_iva ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEstado(int $id, string $estado): void
    {
        $sql = "UPDATE nota_debito_cabecera SET estado = ? WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$estado, $id]);
    }

    public function updateAutorizacion(int $id, string $numero, string $fecha): void
    {
        $sql = "UPDATE nota_debito_cabecera SET numero_autorizacion = ?, fecha_autorizacion = ?, estado = 'autorizado' WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$numero, $fecha, $id]);
    }

    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $sql = "UPDATE nota_debito_cabecera SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = ? WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $id]);
    }

    public function getPorDocumentoModificado(string $numeroFactura, int $idEmpresa): array
    {
        $sql = "SELECT nd.*, u.nombre as usuario_nombre
                FROM nota_debito_cabecera nd
                LEFT JOIN usuarios u ON nd.id_usuario = u.id
                WHERE nd.num_doc_modificado = ?
                  AND nd.id_empresa = ?
                  AND nd.eliminado = false
                ORDER BY nd.fecha_emision DESC";
        $st = $this->db->prepare($sql);
        $st->execute([$numeroFactura, $idEmpresa]);
        return $st->fetchAll();
    }

    public function getSumaImporteNotasDebito(string $numeroFactura, int $idEmpresa, ?int $exceptoId = null): float
    {
        $sql = "SELECT SUM(importe_total) FROM nota_debito_cabecera
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
        $st = $this->db->prepare(
            "UPDATE nota_debito_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }
}
