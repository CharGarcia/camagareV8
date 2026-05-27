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
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO nota_debito_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    total_sin_impuestos, importe_total, estado, 
                    num_doc_modificado, fecha_emision_docs_sustento,
                    created_by, updated_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
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
            $data['clave_acceso'],
            $data['total_sin_impuestos'],
            $data['importe_total'],
            $data['estado'] ?? 'borrador',
            $data['num_doc_modificado'] ?? null,
            $data['fecha_emision_docs_sustento'] ?? null,
            $data['id_usuario'],
            $data['id_usuario']
        ]);

        return (int) $st->fetchColumn();
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
}
