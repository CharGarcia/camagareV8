<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;
use PDO;

class RetencionRepository extends BaseRepository
{
    public function __construct()
    {
        // Default table, but we use specific ones in methods
        parent::__construct('retencion_compra_cabecera');
    }

    public function insertRetencionCompra(array $data): int
    {
        $sql = "INSERT INTO retencion_compra_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_proveedor, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    estado, periodo_fiscal,
                    created_by, updated_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                ) RETURNING id";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_empresa'],
            $data['id_establecimiento'],
            $data['id_punto_emision'],
            $data['id_proveedor'],
            $data['id_usuario'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['clave_acceso'],
            $data['estado'] ?? 'borrador',
            $data['periodo_fiscal'] ?? null,
            $data['id_usuario'],
            $data['id_usuario']
        ]);

        return (int) $st->fetchColumn();
    }

    public function insertRetencionVenta(array $data): int
    {
        $sql = "INSERT INTO retencion_venta_cabecera (
                    id_empresa, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, numero_autorizacion,
                    estado,
                    created_by, updated_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                ) RETURNING id";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_empresa'],
            $data['id_cliente'],
            $data['id_usuario'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['numero_autorizacion'],
            $data['estado'] ?? 'registrado',
            $data['id_usuario'],
            $data['id_usuario']
        ]);

        return (int) $st->fetchColumn();
    }

    public function insertDetalleCompra(array $data): void
    {
        $sql = "INSERT INTO retencion_compra_detalle (
                    id_retencion_compra, codigo, codigo_retencion, base_imponible, porcentaje_retener, valor_retenido,
                    cod_doc_sustento, num_doc_sustento, fecha_emision_doc_sustento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_retencion_compra'],
            $data['codigo'],
            $data['codigo_retencion'],
            $data['base_imponible'],
            $data['porcentaje_retener'],
            $data['valor_retenido'],
            $data['cod_doc_sustento'] ?? null,
            $data['num_doc_sustento'] ?? null,
            $data['fecha_emision_doc_sustento'] ?? null
        ]);
    }

    public function insertDetalleVenta(array $data): void
    {
        $sql = "INSERT INTO retencion_venta_detalle (
                    id_retencion_venta, codigo, codigo_retencion, base_imponible, porcentaje_retener, valor_retenido
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            $data['id_retencion_venta'],
            $data['codigo'],
            $data['codigo_retencion'],
            $data['base_imponible'],
            $data['porcentaje_retener'],
            $data['valor_retenido']
        ]);
    }
}
