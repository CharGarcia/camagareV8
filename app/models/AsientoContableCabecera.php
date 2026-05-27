<?php

declare(strict_types=1);

namespace App\models;

class AsientoContableCabecera extends BaseModel
{
    protected string $table = 'asientos_contables_cabecera';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'id_empresa',
        'fecha_asiento',
        'tipo_comprobante',
        'numero_comprobante',
        'concepto',
        'estado',
        'modulo_origen',
        'id_referencia_origen',
        'total_debe',
        'total_haber',
        'observaciones',
        'eliminado',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by'
    ];
}
